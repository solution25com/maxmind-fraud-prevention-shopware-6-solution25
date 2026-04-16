<?php

declare(strict_types=1);

namespace MaxMind\Service\Installer;

use Exception;
use Shopware\Core\Framework\Context;

class FraudPassStateInstaller extends StateInstaller
{
    protected const NEW_STATE_TECHNICAL_NAME = 'fraud_pass';
    protected const NEW_STATE_NAME = 'Fraud Pass';
    protected const TRANSITIONS = [
        'mark_as_fraud_pass' => ['from' => 'open', 'to' => 'fraud_pass'],
        'mark_as_fraud_manual_review' => ['from' => 'fraud_pass', 'to' => 'fraud_review'],
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
