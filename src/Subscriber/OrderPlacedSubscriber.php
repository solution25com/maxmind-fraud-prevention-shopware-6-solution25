<?php

declare(strict_types=1);

namespace MaxMind\Subscriber;

use MaxMind\Message\FraudCheckMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
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
            $capturedIp = null;
            $session = $this->requestStack->getSession();
            if ($session->has(ClientIpCaptureSubscriber::SESSION_KEY)) {
                $v = $session->get(ClientIpCaptureSubscriber::SESSION_KEY);
                if (\is_string($v) && $v !== '') {
                    $capturedIp = $v;
                }
            }

            $this->messageBus->dispatch(new FraudCheckMessage($event->getOrder()->getId(), $event->getSalesChannelId(), $capturedIp));
        } catch (\Throwable $e) {
            $this->logger->error('Error dispatching MaxMind fraud check message: ' . $e->getMessage());
        }
    }
}
