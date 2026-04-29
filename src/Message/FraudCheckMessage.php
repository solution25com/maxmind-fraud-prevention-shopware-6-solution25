<?php

declare(strict_types=1);

namespace MaxMind\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

final class FraudCheckMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $orderId,
        private readonly ?string $salesChannelId,
        private readonly ?string $capturedIp
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function getCapturedIp(): ?string
    {
        return $this->capturedIp;
    }
}
