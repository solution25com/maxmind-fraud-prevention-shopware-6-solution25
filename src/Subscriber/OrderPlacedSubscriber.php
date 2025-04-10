<?php declare(strict_types=1);

namespace MaxMind\Subscriber;

use MaxMind\MinFraud;
use MaxMind\Service\MaxMindAverageService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityRepository $orderRepository, private readonly SystemConfigService $systemConfigService, private readonly LoggerInterface $logger, private readonly StateMachineRegistry $stateMachineRegistry, private readonly MaxMindAverageService $maxMindAverageService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        try {
            $context = $event->getContext();
            $orderId = $event->getOrder()->getId();

            $criteria = new Criteria([$orderId]);
            $criteria->addAssociation('billingAddress.country.phoneCode');
            $criteria->addAssociation('orderCustomer');
            $criteria->addAssociation('currency');
            $criteria->addAssociation('salesChannel');

            $orderSearchResult = $this->orderRepository->search($criteria, $context);
            $order = $orderSearchResult->first();

            $salesChannelId = $event->getSalesChannelId();
            $accountId = (int) $this->systemConfigService->get('MaxMind.config.MaxMindConfigAccountId', $salesChannelId);
            $licenseKey = $this->systemConfigService->get('MaxMind.config.MaxMindConfigLicenseKey', $salesChannelId);
            $riskThreshold = (float) $this->systemConfigService->get('MaxMind.config.MaxMindConfigRiskThreshold', $salesChannelId);

            if ((int) $accountId === 0 || empty($licenseKey)) {
                $this->logger->error("MaxMind Account ID or License Key is missing for Sales Channel $salesChannelId.");

                return;
            }

            $maxMindData = $this->callMinFraudApi($order, $accountId, $licenseKey, $context, $salesChannelId);
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

                    $this->logger->warning("Order $orderId has been transitioned to Fraud Review due to high risk score ($riskScore).");
                } catch (\Exception $e) {
                    $this->logger->error("Error transitioning order $orderId to Fraud Review: " . $e->getMessage());
                }
            } else {
                try {
                    $transition = new Transition(
                        'order',
                        $orderId,
                        'mark_as_fraud_review',
                        'stateId'
                    );
                    $this->stateMachineRegistry->transition($transition, $context);
                    $transition = new Transition(
                        'order',
                        $orderId,
                        'mark_as_fraud_manual_pass',
                        'stateId'
                    );
                    $this->stateMachineRegistry->transition($transition, $context);
                    $this->logger->info("Order $orderId has been transitioned to Open due to low risk score ($riskScore).");
                } catch (\Exception $e) {
                    $this->logger->error("Error transitioning order $orderId to Open: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing order: ' . $e->getMessage());
        }
    }

    private function callMinFraudApi(OrderEntity $order, int $accountId, ?string $licenseKey, Context $context, ?string $salesChannelId): array
    {
        try {
            $client = new MinFraud($accountId, $licenseKey);
            $request = $client->withDevice(
                ipAddress: $order->getOrderCustomer()?->getRemoteAddress() ?? '127.0.0.1',
                sessionAge: 3600.5,
                sessionId: 'foobar',
                userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.89 Safari/537.36',
                acceptLanguage: 'en-US,en;q=0.8'
            );
            $request->withEvent(
                transactionId: $order->getOrderNumber(),
                shopId: $order->getSalesChannelId(),
                time: $order->getOrderDateTime()->format('c'),
                type: 'purchase'
            );
            $request->withAccount(
                userId: $order->getOrderCustomer()?->getCustomerId(),
                usernameMd5: md5($order->getOrderCustomer()?->getEmail() ?? '')
            );
            $request->withEmail(
                address: $order->getOrderCustomer()?->getEmail() ?? '',
                domain: substr(strrchr($order->getOrderCustomer()?->getEmail() ?? '', '@'), 1)
            );
            $request->withBilling(
                firstName: $order->getBillingAddress()?->getFirstName() ?? '',
                lastName: $order->getBillingAddress()?->getLastName() ?? '',
                company: $order->getBillingAddress()?->getCompany() ?? '',
                address: $order->getBillingAddress()?->getStreet() ?? '',
                address2: $order->getBillingAddress()?->getAdditionalAddressLine1() ?? '',
                city: $order->getBillingAddress()?->getCity() ?? '',
                country: $order->getBillingAddress()?->getCountry()?->getIso() ?? '',
                postal: $order->getBillingAddress()?->getZipcode() ?? '',
                phoneNumber: $order->getBillingAddress()?->getPhoneNumber() ?? '',
            );
            $request->withOrder(
                amount: $order->getAmountTotal(),
                currency: $order->getCurrency()?->getIsoCode() ?? 'USD'
            );

            $response = $request->insights();

            $ipRiskScore = $response->ipAddress->risk ?? 0.0;
            $overallRiskScore = $this->maxMindAverageService->getOverallRiskScore($context, $salesChannelId);

            $data = [
                'maxmind_fraud_risk' => $response->riskScore ?? 0.0,
                'maxmind_overall_risk_score' => $overallRiskScore,
                'maxmind_ip_risk_score' => $ipRiskScore,
                'maxmind_transaction_id' => $response->id ?? '',
                'maxmind_transaction_url' => \sprintf('https://www.maxmind.com/en/accounts/%s/minfraud-interactive/transactions/%s', $accountId, $response->id ?? ''),
                'maxmind_warnings_factors' => array_map(fn ($warning) => $warning->warning ?? '', $response->warnings ?? []),
            ];

            $this->logger->info("MaxMind Insights Response for Order {$order->getId()}: " . json_encode($data));

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Error calling MaxMind minFraud API: ' . $e->getMessage());

            return [
                'maxmind_fraud_risk' => 0.0,
                'maxmind_overall_risk_score' => 0.0,
                'maxmind_ip_risk_score' => 0.0,
                'maxmind_transaction_id' => '',
                'maxmind_transaction_url' => '',
                'maxmind_warnings_factors' => [],
            ];
        }
    }
}
