<?php

declare(strict_types=1);

namespace MaxMind\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Order\OrderEntity;

class MaxMindAverageService
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getOverallRiskScore(Context $context, ?string $salesChannelId = null): float
    {
        $overallRiskScoreKey = 'MaxMind.config.overallRiskScore';
        $overallRiskScore = $this->systemConfigService->get($overallRiskScoreKey, $salesChannelId);

        if ($overallRiskScore !== null) {
            $this->logger->info("Using cached overall risk score: $overallRiskScore");
            return (float)$overallRiskScore;
        }

        $this->logger->info('No cached risk score found, calculating for the first time...');
        $averages = $this->calculateAverages($context, 1000);

        $newOverallRiskScore = $averages['overall_risk_average'];

        $this->systemConfigService->set('MaxMind.config.lastCalculationTime', time(), $salesChannelId);
        $this->systemConfigService->set($overallRiskScoreKey, $newOverallRiskScore, $salesChannelId);
        $this->systemConfigService->set(
            'MaxMind.config.fraudRiskAverage',
            $averages['fraud_risk_average'],
            $salesChannelId
        );
        $this->systemConfigService->set('MaxMind.config.ipRiskAverage', $averages['ip_risk_average'], $salesChannelId);

        $this->logger->info("Calculated and cached overall risk score: $newOverallRiskScore");

        return $newOverallRiskScore;
    }


    public function calculateAverages(Context $context, int $maxOrders = 10000, int $batchSize = 1000): array
    {
        $startTime = microtime(true);
        $this->logger->info(
            "Starting batch calculation of averages (max: $maxOrders orders, batch size: $batchSize)..."
        );

        $fraudRiskScores = [];
        $ipRiskScores = [];
        $offset = 0;
        $totalProcessed = 0;

        while ($offset < $maxOrders) {
            $criteria = new Criteria();
            $criteria->addFilter(new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('customFields.maxmind_fraud_risk', null),
                    new EqualsFilter('customFields.maxmind_ip_risk_score', null),
                ]
            ));

            $criteria->setLimit($batchSize);
            $criteria->setOffset($offset);
            $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

            $orders = $this->orderRepository->search($criteria, $context)->getEntities();

            if ($orders->count() === 0) {
                $this->logger->info("MaxMind: No more orders to process. Total processed: $totalProcessed");
                break;
            }

            foreach ($orders as $order) {
                /** @var OrderEntity $order */
                $customFields = $order->getCustomFields() ?? [];
                if (isset($customFields['maxmind_fraud_risk'])) {
                    $fraudRiskScores[] = (float)$customFields['maxmind_fraud_risk'];
                }
                if (isset($customFields['maxmind_ip_risk_score'])) {
                    $ipRiskScores[] = (float)$customFields['maxmind_ip_risk_score'];
                }
            }

            $totalProcessed += $orders->count();
            $offset += $batchSize;

            $this->logger->info("MaxMind: Processed batch - Total orders: $totalProcessed");
            unset($orders);
            gc_collect_cycles();
        }

        $fraudRiskAverage = !empty($fraudRiskScores) ? array_sum($fraudRiskScores) / \count($fraudRiskScores) : 0.0;
        $ipRiskAverage = !empty($ipRiskScores) ? array_sum($ipRiskScores) / \count($ipRiskScores) : 0.0;
        $overallRiskAverage = ($fraudRiskAverage + $ipRiskAverage) / 2;

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        $this->logger->info("Calculation completed in $executionTime seconds. Total orders: $totalProcessed");

        return [
            'fraud_risk_average' => round($fraudRiskAverage, 2),
            'ip_risk_average' => round($ipRiskAverage, 2),
            'overall_risk_average' => round($overallRiskAverage, 2),
            'total_orders_processed' => $totalProcessed,
        ];
    }
}
