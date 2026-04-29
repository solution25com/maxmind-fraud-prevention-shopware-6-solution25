<?php

declare(strict_types=1);

namespace MaxMind\Service;

use MaxMind\Exception\InvalidInputException;
use MaxMind\Exception\InvalidRequestException;
use MaxMind\MinFraud;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

class MaxMindFraudService
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly MaxMindAverageService $maxMindAverageService
    ) {
    }

    public function processFraudCheck(
        OrderEntity $order,
        Context $context,
        ?string $salesChannelId,
        ?string $capturedIp = null
    ): void {
        try {
            $orderId = $order->getId();
            $accountId = (int) $this->systemConfigService->get(
                'MaxMind.config.MaxMindConfigAccountId',
                $salesChannelId
            );
            $licenseKey = $this->systemConfigService->get(
                'MaxMind.config.MaxMindConfigLicenseKey',
                $salesChannelId
            );
            $riskThreshold = (float) $this->systemConfigService->get(
                'MaxMind.config.MaxMindConfigRiskThreshold',
                $salesChannelId
            );

            if ($accountId === 0 || empty($licenseKey)) {
                $this->logger->error("MaxMind Account ID or License Key is missing for Sales Channel $salesChannelId.");
                return;
            }

            $maxMindData = $this->callMinFraudApi(
                $order,
                $accountId,
                $licenseKey,
                $context,
                $salesChannelId,
                $capturedIp
            );
            if (($maxMindData['maxmind_check_failed'] ?? false) === true) {
                $this->logger->warning('MaxMind fraud check failed. Skipping fraud state transition.', [
                    'orderId' => $orderId,
                    'salesChannelId' => $salesChannelId,
                    'error' => $maxMindData['maxmind_error_message'] ?? null,
                ]);

                return;
            }
            $riskScore = $maxMindData['maxmind_fraud_risk'];

            $this->logger->info("Order $orderId has a MaxMind risk score of: $riskScore");

            $customFields = $order->getCustomFields() ?? [];
            $customFields = array_merge($customFields, $maxMindData);

            $this->orderRepository->update([
                [
                    'id' => $orderId,
                    'customFields' => $customFields,
                ],
            ], $context);

            if ($riskScore > $riskThreshold) {
                try {
                    $transition = new Transition('order', $orderId, 'mark_as_fraud_review', 'stateId');
                    $this->stateMachineRegistry->transition($transition, $context);
                    $this->logger->warning(
                        "Order $orderId has been transitioned to Fraud Review due to high risk score ($riskScore)."
                    );
                } catch (\Exception $e) {
                    $this->logger->error("Error transitioning order $orderId to Fraud Review: " . $e->getMessage());
                }
            } else {
                try {
                    $transition = new Transition('order', $orderId, 'mark_as_fraud_pass', 'stateId');
                    $this->stateMachineRegistry->transition($transition, $context);

                    $this->logger->info(
                        "Order $orderId has been transitioned to Fraud Pass due to low risk score ($riskScore)."
                    );
                } catch (\Exception $e) {
                    $this->logger->error("Error transitioning order $orderId to Fraud Pass: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing fraud check for order: ' . $e->getMessage());
        }
    }

    public function processFraudCheckByOrderId(
        string $orderId,
        Context $context,
        ?string $salesChannelId,
        ?string $capturedIp = null
    ): void {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('billingAddress.country.phoneCode');
        $criteria->addAssociation('billingAddress.countryState');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('transactions.customFields');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.shippingOrderAddress.countryState');
        $criteria->addAssociation('lineItems');

        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order instanceof OrderEntity) {
            return;
        }

        $this->processFraudCheck($order, $context, $salesChannelId, $capturedIp);
    }

    private function callMinFraudApi(
        OrderEntity $order,
        int $accountId,
        ?string $licenseKey,
        Context $context,
        ?string $salesChannelId,
        ?string $capturedIp = null
    ): array {
        try {
            $client = new MinFraud($accountId, $licenseKey);

            $ipAddress = $this->getClientIpForOrder($order, $capturedIp);

            $requestSections = [
                'device' => false,
                'event' => false,
                'account' => false,
                'email' => false,
                'billing' => false,
                'shipping' => false,
                'order' => false,
                'payment' => false,
                'custom_inputs' => false,
                'credit_card' => false,
                'shopping_cart_items' => 0,
            ];

            $request = $client;
            if ($this->isValidPublicIp($ipAddress)) {
                $request = $request->withDevice(
                    ipAddress: $ipAddress,
                );
                $requestSections['device'] = true;
            }

            $orderDateTime = $order->getOrderDateTime();
            $request = $request->withEvent(
                transactionId: (string) ($order->getOrderNumber() ?? $order->getId()),
                shopId: (string) $order->getSalesChannelId(),
                time: $orderDateTime->format('c'),
                type: 'purchase'
            );
            $requestSections['event'] = true;

            $orderCustomer = $order->getOrderCustomer();
            if ($orderCustomer?->getCustomerId() || $orderCustomer?->getEmail()) {
                $request = $request->withAccount(
                    userId: $orderCustomer->getCustomerId(),
                    usernameMd5: $orderCustomer->getEmail() ? md5($orderCustomer->getEmail()) : null
                );
                $requestSections['account'] = true;
            }

            $email = $orderCustomer?->getEmail();
            if (\is_string($email) && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $request = $request->withEmail(
                    address: $email,
                    domain: $this->getEmailDomain($email)
                );
                $requestSections['email'] = true;
            }

            $billing = $order->getBillingAddress();
            if ($billing) {
                $request = $request->withBilling(
                    firstName: $billing->getFirstName(),
                    lastName: $billing->getLastName(),
                    company: $billing->getCompany() ?? '',
                    address: $billing->getStreet(),
                    address2: $billing->getAdditionalAddressLine1() ?? '',
                    city: $billing->getCity(),
                    region: $this->normalizeRegionCode(
                        $billing->getCountryState()?->getShortCode()
                        ?? ($billing->getCountryState()?->getName() ?? '')
                    ),
                    country: $billing->getCountry()?->getIso() ?? '',
                    postal: $billing->getZipcode() ?? '',
                    phoneNumber: $billing->getPhoneNumber() ?? '',
                );
                $requestSections['billing'] = true;
            }

            $shippingAddress = $this->getPrimaryShippingAddress($order);
            if ($shippingAddress) {
                $request = $request->withShipping(
                    firstName: $shippingAddress->getFirstName(),
                    lastName: $shippingAddress->getLastName(),
                    company: $shippingAddress->getCompany() ?? '',
                    address: $shippingAddress->getStreet(),
                    address2: $shippingAddress->getAdditionalAddressLine1() ?? '',
                    city: $shippingAddress->getCity(),
                    region: $this->normalizeRegionCode(
                        $shippingAddress->getCountryState()?->getShortCode()
                        ?? ($shippingAddress->getCountryState()?->getName() ?? '')
                    ),
                    country: $shippingAddress->getCountry()?->getIso() ?? '',
                    postal: $shippingAddress->getZipcode() ?? '',
                    phoneNumber: $shippingAddress->getPhoneNumber() ?? '',
                );
                $requestSections['shipping'] = true;
            }

            $request = $request->withOrder(
                amount: $order->getAmountTotal(),
                currency: $order->getCurrency()?->getIsoCode() ?? 'USD'
            );
            $requestSections['order'] = true;

            foreach ($this->buildShoppingCartItems($order) as $item) {
                $request = $request->withShoppingCartItem(values: $item);
                $requestSections['shopping_cart_items']++;
            }

            $sectionCount = \count(array_filter([
                $requestSections['device'],
                $requestSections['event'],
                $requestSections['account'],
                $requestSections['email'],
                $requestSections['billing'],
                $requestSections['shipping'],
                $requestSections['order'],
                $requestSections['payment'],
                $requestSections['credit_card'],
            ])) + (int) $requestSections['shopping_cart_items'];
            /** @phpstan-ignore identical.alwaysFalse */
            if ($sectionCount === 0) {
                $this->logger->warning('MaxMind request skipped because no valid input values could be built.', [
                    'orderId' => $order->getId(),
                    'salesChannelId' => $salesChannelId,
                    'capturedIp' => $this->redactIp($capturedIp),
                    'orderCustomerRemoteAddress' => $this->redactIp($order->getOrderCustomer()?->getRemoteAddress()),
                ]);

                return [
                    'maxmind_fraud_risk' => 0.0,
                    'maxmind_overall_risk_score' => $this->maxMindAverageService
                        ->getOverallRiskScore($context, $salesChannelId),
                    'maxmind_ip_risk_score' => 0.0,
                    'maxmind_transaction_id' => '',
                    'maxmind_transaction_url' => '',
                    'maxmind_warnings_factors' => ['skipped_no_valid_input'],
                    'maxmind_check_failed' => true,
                    'maxmind_error_message' => 'No valid input values could be built.',
                ];
            }

            $this->logger->debug('Sending MaxMind minFraud Insights request.', [
                'orderId' => $order->getId(),
                'salesChannelId' => $salesChannelId,
                'sections' => $requestSections,
                'deviceIp' => $requestSections['device'] ? $this->redactIp($ipAddress) : null,
            ]);

            $response = $request->insights();

            $ipRiskScore = $response->ipAddress->risk ?? 0.0;
            $overallRiskScore = $this->maxMindAverageService->getOverallRiskScore($context, $salesChannelId);

            $data = [
                'maxmind_fraud_risk' => $response->riskScore ?? 0.0,
                'maxmind_overall_risk_score' => $overallRiskScore,
                'maxmind_ip_risk_score' => $ipRiskScore,
                'maxmind_transaction_id' => $response->id ?? '',
                'maxmind_transaction_url' => \sprintf(
                    'https://www.maxmind.com/en/accounts/%s/minfraud-interactive/transactions/%s',
                    $accountId,
                    $response->id ?? ''
                ),
                'maxmind_warnings_factors' => array_map(
                    fn ($warning) => $warning->warning ?? '',
                    $response->warnings ?? []
                ),
            ];

            $this->logger->info("MaxMind Insights Response for Order {$order->getId()}: " . json_encode($data));

            return $data;
        } catch (InvalidRequestException | InvalidInputException $e) {
            $this->logger->error('MaxMind request rejected: ' . $e->getMessage(), [
                'orderId' => $order->getId(),
                'salesChannelId' => $salesChannelId,
                'capturedIp' => $this->redactIp($capturedIp),
            ]);

            return [
                'maxmind_fraud_risk' => 0.0,
                'maxmind_overall_risk_score' => $this->maxMindAverageService
                    ->getOverallRiskScore($context, $salesChannelId),
                'maxmind_ip_risk_score' => 0.0,
                'maxmind_transaction_id' => '',
                'maxmind_transaction_url' => '',
                'maxmind_warnings_factors' => ['invalid_request'],
                'maxmind_check_failed' => true,
                'maxmind_error_message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error calling MaxMind minFraud API: ' . $e->getMessage());

            return [
                'maxmind_fraud_risk' => 0.0,
                'maxmind_overall_risk_score' => 0.0,
                'maxmind_ip_risk_score' => 0.0,
                'maxmind_transaction_id' => '',
                'maxmind_transaction_url' => '',
                'maxmind_warnings_factors' => ['api_error'],
                'maxmind_check_failed' => true,
                'maxmind_error_message' => $e->getMessage(),
            ];
        }
    }

    private function getClientIpForOrder(OrderEntity $order, ?string $capturedIp = null): string
    {
        if (\is_string($capturedIp)) {
            $capturedIp = trim($capturedIp);
        }

        if ($this->isValidPublicIp($capturedIp)) {
            return $capturedIp;
        }

        $remoteAddress = $order->getOrderCustomer()?->getRemoteAddress();
        if (\is_string($remoteAddress)) {
            $remoteAddress = trim($remoteAddress);
        }

        if ($this->isValidPublicIp($remoteAddress)) {
            return $remoteAddress;
        }

        return '';
    }

    private function isValidPublicIp(?string $ip): bool
    {
        if (!$ip) {
            return false;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function getEmailDomain(?string $email): string
    {
        if (!$email || !str_contains($email, '@')) {
            return '';
        }

        $domain = substr(strrchr($email, '@'), 1);
        return $domain ?: '';
    }

    private function getPrimaryShippingAddress(OrderEntity $order): ?OrderAddressEntity
    {
        $deliveries = $order->getDeliveries();
        if (!$deliveries || $deliveries->count() === 0) {
            return null;
        }

        /** @var OrderDeliveryEntity|null $delivery */
        $delivery = $deliveries->first();
        if (!$delivery) {
            return null;
        }

        return $delivery->getShippingOrderAddress();
    }

    private function buildShoppingCartItems(OrderEntity $order): array
    {
        $items = [];
        $lineItems = $order->getLineItems();
        if (!$lineItems) {
            return $items;
        }

        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() && $lineItem->getType() !== 'product') {
                continue;
            }

            $quantity = (int) $lineItem->getQuantity();
            if ($quantity <= 0) {
                continue;
            }

            $payload = $lineItem->getPayload();
            $sku = null;
            if ($payload !== null) {
                $sku = $payload['productNumber'] ?? $payload['sku'] ?? null;
            }

            $unitPrice = null;
            $lineItemUnitPrice = $lineItem->getUnitPrice();
            if ($lineItemUnitPrice !== 0.0) {
                $unitPrice = $lineItemUnitPrice;
            } elseif ($lineItem->getPrice() !== null) {
                $unitPrice = $lineItem->getPrice()->getUnitPrice();
            }

            $items[] = array_filter([
                'item_id' => $sku ?: $lineItem->getIdentifier(),
                'quantity' => $quantity,
                'price' => $unitPrice,
                'category' => null,
            ], static fn ($v) => $v !== null && $v !== '');
        }

        return $items;
    }

    private function redactIp(?string $ip): ?string
    {
        if (!$ip) {
            return $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = 'x';
                return implode('.', $parts);
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $parts = array_slice($parts, 0, 4);
            return implode(':', $parts) . '::';
        }

        return $ip;
    }

    private function normalizeRegionCode(?string $region): ?string
    {
        if (!$region) {
            return null;
        }

        $region = strtoupper(trim($region));

        if (preg_match('/^[A-Z]{2}-([0-9A-Z]{1,4})$/', $region, $m)) {
            $region = $m[1];
        }

        if (!preg_match('/^[0-9A-Z]{1,4}$/', $region)) {
            return null;
        }

        return $region;
    }
}
