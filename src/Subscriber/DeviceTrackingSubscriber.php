<?php

declare(strict_types=1);

namespace MaxMind\Subscriber;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class DeviceTrackingSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $systemConfigService;
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onPageLoaded'
        ];
    }

    public function onPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        $accountId      = (int) $this->systemConfigService->get('MaxMind.config.MaxMindConfigAccountId', $salesChannelId);

        $event->getPage()->addExtension(
            'maxmindJsSnippet',
            new ArrayStruct(['accountId' => $accountId])
        );
    }
}
