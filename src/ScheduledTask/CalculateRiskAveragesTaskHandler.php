<?php

declare(strict_types=1);

namespace MaxMind\ScheduledTask;

use MaxMind\Service\MaxMindAverageService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CalculateRiskAveragesTask::class)]
final class CalculateRiskAveragesTaskHandler extends ScheduledTaskHandler
{
    private const BATCH_SIZE = 1000;
    private const MAX_ORDERS = 10000;

    public function __construct(
        $scheduledTaskRepository,
        private readonly MaxMindAverageService $maxMindAverageService,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        try {
            $this->logger->info('MaxMind: Starting scheduled risk averages calculation...');
            $startTime = microtime(true);

            $context = new Context(new SystemSource());

            $averages = $this->maxMindAverageService->calculateAverages(
                $context,
                self::MAX_ORDERS,
                self::BATCH_SIZE
            );

            $currentTime = time();
            $this->systemConfigService->set('MaxMind.config.lastCalculationTime', $currentTime);
            $this->systemConfigService->set('MaxMind.config.fraudRiskAverage', $averages['fraud_risk_average']);
            $this->systemConfigService->set('MaxMind.config.ipRiskAverage', $averages['ip_risk_average']);
            $this->systemConfigService->set('MaxMind.config.overallRiskScore', $averages['overall_risk_average']);

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            $this->logger->info(
                'MaxMind: Risk averages calculation completed successfully',
                [
                    'execution_time' => $executionTime . ' seconds',
                    'fraud_risk_average' => $averages['fraud_risk_average'],
                    'ip_risk_average' => $averages['ip_risk_average'],
                    'overall_risk_average' => $averages['overall_risk_average'],
                    'total_orders_processed' => $averages['total_orders_processed']
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error('MaxMind: Error calculating risk averages: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }
}
