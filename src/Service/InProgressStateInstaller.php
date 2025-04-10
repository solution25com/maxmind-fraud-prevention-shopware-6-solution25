<?php declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Core\Framework\Context;

class InProgressStateInstaller
{
    private const STATE_MACHINE_TECHNICAL_NAME = 'order.state';
    private const NEW_STATE_TECHNICAL_NAME = 'in_progress';
    private const NEW_STATE_NAME = 'In Progress';
    private const TRANSITIONS = [
        'mark_as_in_progress' => ['from' => 'fraud_pass', 'to' => 'in_progress'],
        'mark_as_fraud_pass_from_in_progress' => ['from' => 'in_progress', 'to' => 'fraud_pass'],
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
