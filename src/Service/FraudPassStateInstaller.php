<?php
declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Core\Framework\Context;

class FraudPassStateInstaller
{
    private const STATE_MACHINE_TECHNICAL_NAME = 'order.state';
    private const NEW_STATE_TECHNICAL_NAME = 'fraud_pass';
    private const NEW_STATE_NAME = 'Fraud Pass';
    private const TRANSITIONS
        = [
            'mark_as_fraud_pass' => ['from' => 'open', 'to' => 'fraud_pass'],
            'mark_as_fraud_manual_review' => ['from' => 'fraud_pass', 'to' => 'fraud_review'],
            'mark_as_fraud_manual_pass' => ['from' => 'fraud_review', 'to' => 'fraud_pass'],
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
