<?php

declare(strict_types=1);

namespace MaxMind\Service\Installer;

use Shopware\Core\Framework\Context;

class CompleteStateInstaller extends StateInstaller
{
    protected const NEW_STATE_TECHNICAL_NAME = 'completed';
    protected const NEW_STATE_NAME = 'Completed';
    protected const TRANSITIONS = [
        'mark_as_completed' => ['from' => 'fraud_pass', 'to' => 'completed'],
        'mark_as_fraud_pass' => ['from' => 'completed', 'to' => 'fraud_pass'],
        'mark_as_cancel' => ['from' => 'fraud_fail', 'to' => 'cancelled'],
    ];

    /**
     * @param Context $context
     * @return void
     */
    public function uninstall(Context $context): void
    {
        $this->stateInstallerHelper->removeTransitions(self::TRANSITIONS, $context);
    }
}
