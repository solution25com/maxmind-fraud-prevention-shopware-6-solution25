<?php

declare(strict_types=1);

namespace MaxMind\Service\Installer;

use Exception;
use Shopware\Core\Framework\Context;

class FraudReviewStateInstaller extends StateInstaller
{
    protected const NEW_STATE_TECHNICAL_NAME = 'fraud_review';
    protected const NEW_STATE_NAME = 'Fraud Review';
    protected const TRANSITIONS = [
        'mark_as_fraud_review' => ['from' => 'open', 'to' => 'fraud_review'],
        'mark_as_open' => ['from' => 'fraud_review', 'to' => 'open'],
        'mark_as_cancel' => ['from' => 'fraud_review', 'to' => 'cancelled'],
        'mark_as_fraud_fail' => ['from' => 'fraud_review', 'to' => 'fraud_fail'],
        'mark_as_fraud_manual_pass' => ['from' => 'fraud_review', 'to' => 'fraud_pass'],
    ];

    /**
     * @param Context $context
     * @return void
     */
    public function uninstall(Context $context): void
    {
        try {
            $this->stateInstallerHelper->removeTransitions(self::TRANSITIONS, $context);
            $this->stateInstallerHelper->removeState(
                self::NEW_STATE_TECHNICAL_NAME,
                $this->getStateMachineIdOrFail($context),
                $context
            );
        } catch (Exception $exception) {
            // skip
        }
    }
}
