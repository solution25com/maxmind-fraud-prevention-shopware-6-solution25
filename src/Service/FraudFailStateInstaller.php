<?php

declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Core\Framework\Context;

class FraudFailStateInstaller
{
    private const STATE_MACHINE_TECHNICAL_NAME = 'order.state';
    private const NEW_STATE_TECHNICAL_NAME = 'fraud_fail';
    private const NEW_STATE_NAME = 'Fraud Fail';
    private const TRANSITIONS
    = [
        'mark_as_fraud_fail' => ['from' => 'fraud_review', 'to' => 'fraud_fail'],
        'mark_as_fraud_review' => ['from' => 'fraud_fail', 'to' => 'fraud_review'],
    ];

    public function __construct(
        private readonly StateInstallerHelper $stateInstallerHelper,
    ) {
    }

    public function install(Context $context): void
    {
        $stateMachineId = $this->stateInstallerHelper->getStateMachineId(self::STATE_MACHINE_TECHNICAL_NAME, $context);
        if ($stateMachineId === null) {
            return;
        }

        $this->stateInstallerHelper->createState(
            self::NEW_STATE_TECHNICAL_NAME,
            self::NEW_STATE_NAME,
            $stateMachineId,
            $context
        );
        $this->stateInstallerHelper->createTransitions(self::TRANSITIONS, $stateMachineId, $context);
    }

    public function uninstall(Context $context): void
    {
        $stateMachineId = $this->stateInstallerHelper->getStateMachineId(self::STATE_MACHINE_TECHNICAL_NAME, $context);
        if ($stateMachineId === null) {
            return;
        }

        $this->stateInstallerHelper->removeTransitions(self::TRANSITIONS, $context);
        $this->stateInstallerHelper->removeState(self::NEW_STATE_TECHNICAL_NAME, $stateMachineId, $context);
    }
}
