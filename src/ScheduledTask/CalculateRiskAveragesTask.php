<?php

declare(strict_types=1);

namespace MaxMind\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CalculateRiskAveragesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'maxmind.calculate_risk_averages';
    }

    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
