<?php

declare(strict_types=1);

namespace MaxMind\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ClientIpCaptureSubscriber implements EventSubscriberInterface
{
    public const SESSION_KEY = 'maxmind_client_ip';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session === null) {
            return;
        }

        $clientIp = $request->getClientIp();
        if (!\is_string($clientIp) || $clientIp === '') {
            return;
        }

        if ($session->has(self::SESSION_KEY)) {
            return;
        }

        $session->set(self::SESSION_KEY, $clientIp);
    }
}
