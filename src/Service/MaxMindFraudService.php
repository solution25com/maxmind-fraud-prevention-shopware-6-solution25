<?php

declare(strict_types=1);

namespace MaxMind\Service;

use MaxMind\MinFraud;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\System\SystemConfig\SystemConfigService;

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

    public function processFraudCheck(OrderEntity $order, Context $context, ?string $salesChannelId): void
    {
        try {
            $orderId = $order->getId();
            $accountId = (int) $this->systemConfigService->get('MaxMind.config.MaxMindConfigAccountId', $salesChannelId);
            $licenseKey = (string) $this->systemConfigService->get('MaxMind.config.MaxMindConfigLicenseKey', $salesChannelId);
            $riskThreshold = (float) $this->systemConfigService->get('MaxMind.config.MaxMindConfigRiskThreshold', $salesChannelId);

            if ($accountId === 0 || empty($licenseKey)) {
                $this->logger->error("MaxMind Account ID or License Key is missing for Sales Channel $salesChannelId.");
                return;
            }

            $maxMindData = $this->callMinFraudApi($order, $accountId, $licenseKey, $context, $salesChannelId);
            $riskScore = (float) ($maxMindData['maxmind_fraud_risk'] ?? 0.0);
            $this->logger->info("Order {$orderId} has a MaxMind risk score of: {$riskScore}");
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
                    $this->logger->warning("Order {$orderId} transitioned to Fraud Review due to high risk score ({$riskScore}).");
                } catch (\Exception $e) {
                    $this->logger->error("Error transitioning order {$orderId} to Fraud Review: " . $e->getMessage());
                }
            } else {
                try {
                    $transition = new Transition('order', $orderId, 'mark_open_as_fraud_pass', 'stateId');
                    $this->stateMachineRegistry->transition($transition, $context);
                    $this->logger->info("Order $orderId moved to In Progress due to low risk score ($riskScore).");
                } catch (\Exception $e) {
                    $movedToInProgress = false;
                    $this->logger->error("Error transitioning order $orderId to In Progress: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing fraud check for order: ' . $e->getMessage());
        }
    }
    private function callMinFraudApi(
        OrderEntity $order,
        int $accountId,
        ?string $licenseKey,
        Context $context,
        ?string $salesChannelId
    ): array {
        try {
            $client = new MinFraud($accountId, (string) $licenseKey);
            $orderCustomer = $order->getOrderCustomer();
            /** @var OrderAddressEntity|null $billing */
            $billing = $order->getBillingAddress();
            $email = $orderCustomer?->getEmail() ?? '';
            $emailDomain = $email !== '' && str_contains($email, '@') ? substr(strrchr($email, '@'), 1) : '';
            $request = $client
                ->withDevice([
                    'ip_address'      => $orderCustomer?->getRemoteAddress() ?? '127.0.0.1',
                    'session_age'     => 3600.5,
                    'session_id'      => 'foobar',
                    'user_agent'      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.89 Safari/537.36',
                    'accept_language' => 'en-US,en;q=0.8',
                ])
                ->withEvent([
                    'transaction_id' => $order->getOrderNumber() ?? '',
                    'shop_id'        => $order->getSalesChannelId(),
                    'time'           => $order->getOrderDateTime()->format('c'),
                    'type'           => 'purchase',
                ])
                ->withAccount([
                    'user_id'      => $orderCustomer?->getCustomerId() ?? '',
                    'username_md5' => $email !== '' ? md5($email) : '',
                ])
                ->withEmail([
                    'address' => $email,
                    'domain'  => $emailDomain,
                ])
                ->withBilling([
                    'first_name'   => $billing?->getFirstName() ?? '',
                    'last_name'    => $billing?->getLastName() ?? '',
                    'company'      => $billing?->getCompany() ?? '',
                    'address'      => $billing?->getStreet() ?? '',
                    'address_2'    => $billing?->getAdditionalAddressLine1() ?? '',
                    'city'         => $billing?->getCity() ?? '',
                    'country'      => $billing?->getCountry()?->getIso() ?? '',
                    'postal'       => $billing?->getZipcode() ?? '',
                    'phone_number' => method_exists($billing, 'getPhoneNumber') ? ($billing->getPhoneNumber() ?? '') : '',
                ])
                ->withOrder([
                    'amount'   => (float) $order->getAmountTotal(),
                    'currency' => $order->getCurrency()?->getIsoCode() ?? 'USD',
                ]);
            $response = $request->insights();
            $ipRiskScore       = (float) ($response->ipAddress->risk ?? 0.0);
            $overallRiskScore  = (float) $this->maxMindAverageService->getOverallRiskScore($context, $salesChannelId);
            $data = [
                'maxmind_fraud_risk'         => (float) ($response->riskScore ?? 0.0),
                'maxmind_overall_risk_score' => $overallRiskScore,
                'maxmind_ip_risk_score'      => $ipRiskScore,
                'maxmind_transaction_id'     => (string) ($response->id ?? ''),
                'maxmind_transaction_url'    => \sprintf(
                    'https://www.maxmind.com/en/accounts/%s/minfraud-interactive/transactions/%s',
                    $accountId,
                    (string) ($response->id ?? '')
                ),
                'maxmind_warnings_factors'   => array_map(
                    static fn($w) => (string) ($w->warning ?? ''),
                    is_array($response->warnings ?? null) ? $response->warnings : []
                ),
            ];

            $this->logger->info("MaxMind Insights Response for Order {$order->getId()}: " . json_encode($data));

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Error calling MaxMind minFraud API: ' . $e->getMessage());

            return [
                'maxmind_fraud_risk'         => 0.0,
                'maxmind_overall_risk_score' => 0.0,
                'maxmind_ip_risk_score'      => 0.0,
                'maxmind_transaction_id'     => '',
                'maxmind_transaction_url'    => '',
                'maxmind_warnings_factors'   => [],
            ];
        }
    }
}
