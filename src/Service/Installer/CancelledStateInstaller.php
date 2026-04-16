<?php

declare(strict_types=1);

namespace MaxMind\Service\Installer;

use Shopware\Core\Framework\Context;

class CancelledStateInstaller extends StateInstaller
{
    protected const NEW_STATE_TECHNICAL_NAME = 'cancelled';
    protected const NEW_STATE_NAME = 'Cancelled';
    protected const TRANSITIONS = [
        'set_cancel_from_fraud_pass' => [
            'from' => 'fraud_pass',
            'to' => 'cancelled',
        ],
        'set_cancel_from_fraud_review' => [
            'from' => 'fraud_review',
            'to' => 'cancelled',
        ],
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
