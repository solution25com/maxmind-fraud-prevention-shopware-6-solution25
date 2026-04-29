<?php

declare(strict_types=1);

namespace MaxMind\MessageHandler;

use MaxMind\Message\FraudCheckMessage;
use MaxMind\Service\MaxMindFraudService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

final class FraudCheckMessageHandler
{
    public function __construct(
        private readonly MaxMindFraudService $maxMindFraudService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(FraudCheckMessage $message): void
    {
        try {
            $this->maxMindFraudService->processFraudCheckByOrderId(
                $message->getOrderId(),
                Context::createCLIContext(),
                $message->getSalesChannelId(),
                $message->getCapturedIp()
            );
        } catch (\Throwable $e) {
            $this->logger->error('MaxMind async fraud check failed', [
                'orderId' => $message->getOrderId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
