<?php

declare(strict_types=1);

namespace MaxMind\Service\Installer;

use Exception;
use Shopware\Core\Framework\Context;

class FraudFailStateInstaller extends StateInstaller
{
    protected const NEW_STATE_TECHNICAL_NAME = 'fraud_fail';
    protected const NEW_STATE_NAME = 'Fraud Fail';
    protected const TRANSITIONS = [
        'mark_as_fraud_fail' => ['from' => 'fraud_review', 'to' => 'fraud_fail'],
        'mark_as_fraud_review' => ['from' => 'fraud_fail', 'to' => 'fraud_review'],
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
