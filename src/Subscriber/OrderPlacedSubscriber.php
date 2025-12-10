<?php

declare(strict_types=1);

namespace MaxMind\Subscriber;

use MaxMind\Service\MaxMindFraudService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly LoggerInterface $logger,
        private readonly MaxMindFraudService $maxMindFraudService
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

            $criteria = new Criteria([$orderId]);
            $criteria->addAssociation('billingAddress.country.phoneCode');
            $criteria->addAssociation('orderCustomer');
            $criteria->addAssociation('currency');
            $criteria->addAssociation('salesChannel');
            $criteria->addAssociation('transactions.paymentMethod');

            $orderSearchResult = $this->orderRepository->search($criteria, $context);
            $order = $orderSearchResult->first();

            if (!$order instanceof OrderEntity) {
                $this->logger->error('Order not found for ID: ' . $orderId);
                return;
            }
            $this->maxMindFraudService->processFraudCheck($order, $context, $event->getSalesChannelId());
        } catch (\Exception $e) {
            $this->logger->error('Error processing order: ' . $e->getMessage());
        }
    }
}
