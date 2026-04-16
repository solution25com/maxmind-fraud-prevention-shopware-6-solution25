<?php

declare(strict_types=1);

namespace MaxMind\Service\Installer;

use Exception;
use Shopware\Core\Framework\Context;

class PendingFraudReviewStateInstaller extends StateInstaller
{
    protected const NEW_STATE_TECHNICAL_NAME = 'pending_fraud_review';
    protected const NEW_STATE_NAME = 'Pending';
    protected const TRANSITIONS = [
        'pending_as_fraud_review' => ['from' => 'pending_fraud_review', 'to' => 'fraud_review'],
        'mark_as_open' => ['from' => 'pending_fraud_review', 'to' => 'open'],
        'mark_as_cancel' => ['from' => 'pending_fraud_review', 'to' => 'cancelled'],
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
