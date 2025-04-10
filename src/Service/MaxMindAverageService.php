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
        $lastCalculationTimeKey = 'MaxMind.config.lastCalculationTime';
        $overallRiskScoreKey = 'MaxMind.config.overallRiskScore';

        $lastCalculationTime = $this->systemConfigService->get($lastCalculationTimeKey, $salesChannelId);
        $overallRiskScore = $this->systemConfigService->get($overallRiskScoreKey, $salesChannelId);

        $this->logger->info('Retrieved from SystemConfigService - Last calculation time: ' . ($lastCalculationTime ??
                'null') . ', Overall risk score: ' . ($overallRiskScore ?? 'null'));

        if ($lastCalculationTime && $overallRiskScore) {
            $currentTime = time();
            $timeDifference = $currentTime - $lastCalculationTime;

            $this->logger->info("Current time: $currentTime, Time difference: $timeDifference seconds");

            if ($timeDifference < 10800) {
                $this->logger->info("Using stored overall risk score: $overallRiskScore");

                return (float) $overallRiskScore;
            }
            $this->logger->info('Stored data is older than 3 hours, recalculating...');
        } else {
            $this->logger->info('No valid data in SystemConfigService, proceeding to calculate...');
        }

        $averages = $this->calculateAverages($context);

        $newLastCalculationTime = time();
        $newOverallRiskScore = $averages['overall_risk_average'];

        $this->logger->info("Saving new data to SystemConfigService - Last calculation time: $newLastCalculationTime, Overall risk score: $newOverallRiskScore");

        $this->systemConfigService->set($lastCalculationTimeKey, $newLastCalculationTime, $salesChannelId);
        $this->systemConfigService->set($overallRiskScoreKey, $newOverallRiskScore, $salesChannelId);

        $this->logger->info("Returning newly calculated overall risk score: $newOverallRiskScore");

        return $newOverallRiskScore;
    }

    public function calculateAverages(Context $context): array
    {
        $startTime = microtime(true);
        $this->logger->info('Starting calculation of averages...');

        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [
                new EqualsFilter('customFields.maxmind_fraud_risk', null),
                new EqualsFilter('customFields.maxmind_ip_risk_score', null),
            ]
        ));

        $criteria->setLimit(100000);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $orders = $this->orderRepository->search($criteria, $context)->getEntities();

        $fraudRiskScores = [];
        $ipRiskScores = [];

        foreach ($orders as $order) {
            $customFields = $order->getCustomFields() ?? [];
            if (isset($customFields['maxmind_fraud_risk'])) {
                $fraudRiskScores[] = (float) $customFields['maxmind_fraud_risk'];
            }
            if (isset($customFields['maxmind_ip_risk_score'])) {
                $ipRiskScores[] = (float) $customFields['maxmind_ip_risk_score'];
            }
        }

        $fraudRiskAverage = !empty($fraudRiskScores) ? array_sum($fraudRiskScores) / \count($fraudRiskScores) : 0.0;
        $ipRiskAverage = !empty($ipRiskScores) ? array_sum($ipRiskScores) / \count($ipRiskScores) : 0.0;
        $overallRiskAverage = ($fraudRiskAverage + $ipRiskAverage) / 2;

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        $this->logger->info("Calculation completed in $executionTime seconds");

        return [
            'fraud_risk_average' => round($fraudRiskAverage, 2),
            'ip_risk_average' => round($ipRiskAverage, 2),
            'overall_risk_average' => round($overallRiskAverage, 2),
        ];
    }
}
