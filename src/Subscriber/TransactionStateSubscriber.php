<?php

declare(strict_types=1);

namespace MaxMind\Subscriber;

use MaxMind\Service\MaxMindFraudService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class TransactionStateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $orderTransactionRepository,
        private readonly LoggerInterface $logger,
        private readonly MaxMindFraudService $maxMindFraudService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onStateMachineTransition',
        ];
    }

    public function onStateMachineTransition(StateMachineTransitionEvent $event): void
    {
        try {
            if ($event->getEntityName() !== 'order_transaction') {
                return;
            }

            $toState = $event->getToPlace()->getTechnicalName();
            if ($toState === '') {
                return;
            }

            if (
                !\in_array($toState, [
                OrderTransactionStates::STATE_PAID,
                OrderTransactionStates::STATE_AUTHORIZED,
                OrderTransactionStates::STATE_IN_PROGRESS,
                OrderTransactionStates::STATE_PARTIALLY_PAID,
                ], true)
            ) {
                return;
            }

            $transactionId = $event->getEntityId();
            if ($transactionId === '') {
                return;
            }

            $context = $event->getContext();

            $criteria = new Criteria([$transactionId]);
            $criteria->addAssociation('order');
            $criteria->addAssociation('order.orderCustomer');
            $criteria->addAssociation('order.transactions.paymentMethod');
            $criteria->addAssociation('order.transactions.customFields');

            $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();
            if (!$transaction instanceof OrderTransactionEntity) {
                return;
            }

            $order = $transaction->getOrder();
            if ($order === null) {
                return;
            }

            $salesChannelId = $order->getSalesChannelId();

            $capturedIp = null;
            $session = $this->requestStack->getSession();
            if ($session->has(ClientIpCaptureSubscriber::SESSION_KEY)) {
                $v = $session->get(ClientIpCaptureSubscriber::SESSION_KEY);
                if (\is_string($v) && $v !== '') {
                    $capturedIp = $v;
                }
            }

            $orderCfs = $order->getCustomFields() ?? [];
            if (!empty($orderCfs['maxmind_transaction_id'] ?? null)) {
                return;
            }

            $this->maxMindFraudService->processFraudCheckByOrderId(
                $order->getId(),
                $context,
                $salesChannelId,
                $capturedIp
            );
        } catch (\Throwable $e) {
            $this->logger->error('MaxMind TransactionStateSubscriber error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
