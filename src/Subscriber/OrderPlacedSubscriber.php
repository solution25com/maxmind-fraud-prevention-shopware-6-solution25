<?php

declare(strict_types=1);

namespace MaxMind\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MaxMind\MinFraud;
use MaxMind\MinFraud\Model\Score;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    private EntityRepository $orderRepository;
    private SystemConfigService $systemConfigService;
    private LoggerInterface $logger;
    private StateMachineRegistry $stateMachineRegistry;

    public function __construct(
        EntityRepository $orderRepository,
        SystemConfigService $systemConfigService,
        LoggerInterface $logger,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->orderRepository      = $orderRepository;
        $this->systemConfigService  = $systemConfigService;
        $this->logger               = $logger;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $context = $event->getContext();
        $orderId = $event->getOrder()->getId();

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('billingAddress.country.phoneCode');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('salesChannel');


        $orderSearchResult = $this->orderRepository->search($criteria, $context);
        /** @var OrderEntity|null $order */
        $order = $orderSearchResult->first();

        $salesChannelId = $event->getSalesChannelId();
        /** @var int $accountId */
        $accountId = (int)$this->systemConfigService->get('MaxMind.config.MaxMindConfigAccountId', $salesChannelId);

        /** @var string|null $licenseKey */
        $licenseKey = $this->systemConfigService->get('MaxMind.config.MaxMindConfigLicenseKey', $salesChannelId);

        /** @var float $riskThreshold */
        $riskThreshold = (float) $this->systemConfigService->get('MaxMind.config.MaxMindConfigRiskThreshold', $salesChannelId);

        if ((int)$accountId == 0 || empty($licenseKey)) {
            $this->logger->error("MaxMind Account ID or License Key is missing for Sales Channel $salesChannelId.");
            return;
        }
        $riskScore = $this->callMinFraudApi($order, $accountId, $licenseKey);

        $this->logger->info("Order $orderId has a MaxMind risk score of: $riskScore");

        $this->orderRepository->update([
            [
                'id'           => $orderId,
                'customFields' => [
                    'maxmind_fraud_risk' => $riskScore,
                ],
            ]
        ], $context);


        if ($riskScore > $riskThreshold) {
            try {
                $transition = new Transition(
                    'order',
                    $orderId,
                    'mark_as_fraud_review',
                    'stateId'
                );
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
                    'mark_as_fraud_pass',
                    'stateId'
                );
                $this->stateMachineRegistry->transition($transition, $context);

                $this->logger->info("Order $orderId has been transitioned to Open due to low risk score ($riskScore).");
            } catch (\Exception $e) {
                $this->logger->error("Error transitioning order $orderId to Open: " . $e->getMessage());
            }
        }
    }

    /**
     * Call the real MaxMind minFraud API (Score endpoint) using the official PHP library.
     * Returns the 'riskScore' (0.0 - 99).
     */
    private function callMinFraudApi(OrderEntity $order, int $accountId, ?string $licenseKey): float
    {
        $client  = new \MaxMind\MinFraud($accountId, $licenseKey);
        $request = $client->withDevice(
            ipAddress: $order->getOrderCustomer()?->getRemoteAddress() ?? '127.0.0.1',
            sessionAge: 3600.5,
            sessionId: 'foobar',
            userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.89 Safari/537.36',
            acceptLanguage: 'en-US,en;q=0.8'
        );
        $client->withEvent(
            transactionId: $order->getOrderNumber(),
            shopId: $order->getSalesChannelId(),
            time: $order->getOrderDateTime()->format('c'),
            type: 'purchase'
        );
        $client->withAccount(
            userId: $order->getOrderCustomer()?->getCustomerId(),
            usernameMd5: md5($order->getOrderCustomer()?->getEmail() ?? '')
        );
        $client->withEmail(
            address: $order->getOrderCustomer()?->getEmail() ?? '',
            domain: substr(strrchr($order->getOrderCustomer()?->getEmail() ?? '', "@"), 1)
        );
        //        $client->withCreditCard(
        ////            issuerIdNumber: '411111',
        //            lastDigits: '1118'
        //        );
        $client->withBilling(
            firstName: $order->getBillingAddress()?->getFirstName()             ?? '',
            lastName: $order->getBillingAddress()?->getLastName()               ?? '',
            company: $order->getBillingAddress()?->getCompany()                 ?? '',
            address: $order->getBillingAddress()?->getStreet()                  ?? '',
            address2: $order->getBillingAddress()?->getAdditionalAddressLine1() ?? '',
            city: $order->getBillingAddress()?->getCity()                       ?? '',
            country: $order->getBillingAddress()?->getCountry()?->getIso()      ?? '',
            postal: $order->getBillingAddress()?->getZipcode()                  ?? '',
            phoneNumber: $order->getBillingAddress()?->getPhoneNumber()         ?? '',
        );

        $client->withOrder(
            amount: $order->getAmountTotal(),
            currency: $order->getCurrency()?->getIsoCode() ?? 'USD'
        );
        /** @var Score $response */
        $response = $request->score();

        $score = $response->riskScore;
        return (float) $score;
    }
}
