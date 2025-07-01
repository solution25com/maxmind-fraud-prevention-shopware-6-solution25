<?php
declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Core\Framework\Context;

class CancelledStateInstaller
{
    private const STATE_MACHINE_TECHNICAL_NAME = 'order.state';
    private const NEW_STATE_TECHNICAL_NAME = 'completed';
    private const NEW_STATE_NAME = 'Completed';
    private const TRANSITIONS
        = [
            'set_cancel_from_fraud_pass' => [
                'from' => 'fraud_pass',
                'to' => 'cancelled',
            ],
            'set_cancel_from_fraud_review' => [
                'from' => 'fraud_review',
                'to' => 'cancelled',
            ]
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
    }
}
