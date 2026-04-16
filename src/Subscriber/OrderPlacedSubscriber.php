<?php

declare(strict_types=1);

namespace MaxMind\Subscriber;

use MaxMind\Service\MaxMindFraudService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly LoggerInterface $logger,
        private readonly MaxMindFraudService $maxMindFraudService,
        private readonly RequestStack $requestStack,
    ) {
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

            $capturedIp = null;
            $session = $this->requestStack->getSession();
            if ($session->has(ClientIpCaptureSubscriber::SESSION_KEY)) {
                $v = $session->get(ClientIpCaptureSubscriber::SESSION_KEY);
                if (\is_string($v) && $v !== '') {
                    $capturedIp = $v;
                }
            }

            $criteria = new Criteria([$orderId]);
            $criteria->addAssociation('billingAddress.country.phoneCode');
            $criteria->addAssociation('billingAddress.countryState');
            $criteria->addAssociation('orderCustomer');
            $criteria->addAssociation('orderCustomer.customer');
            $criteria->addAssociation('currency');
            $criteria->addAssociation('salesChannel');
            $criteria->addAssociation('transactions.paymentMethod');
            $criteria->addAssociation('deliveries.shippingOrderAddress.country');
            $criteria->addAssociation('deliveries.shippingOrderAddress.countryState');
            $criteria->addAssociation('lineItems');
            $criteria->addAssociation('customFields');
            $criteria->addAssociation('transactions.customFields');

            $orderSearchResult = $this->orderRepository->search($criteria, $context);
            $order = $orderSearchResult->first();

            if (!$order instanceof OrderEntity) {
                $this->logger->error('Order not found for ID: ' . $orderId);
                return;
            }

            $this->maxMindFraudService->processFraudCheck($order, $context, $event->getSalesChannelId(), $capturedIp);
        } catch (\Exception $e) {
            $this->logger->error('Error processing order: ' . $e->getMessage());
        }
    }
}
