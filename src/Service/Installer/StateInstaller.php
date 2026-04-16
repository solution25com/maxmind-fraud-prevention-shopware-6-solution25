<?php

declare(strict_types=1);

namespace MaxMind\Service\Installer;

use LogicException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\StateMachineException;

abstract class StateInstaller
{
    protected const STATE_MACHINE_TECHNICAL_NAME = 'order.state';
    protected const NEW_STATE_TECHNICAL_NAME = '';
    protected const NEW_STATE_NAME = '';
    protected const TRANSITIONS = [];

    /**
     * @param StateInstallerHelper $stateInstallerHelper
     */
    public function __construct(
        protected readonly StateInstallerHelper $stateInstallerHelper,
    ) {
    }

    /**
     * @param Context $context
     * @return void
     * @throws LogicException
     * @throws StateMachineException
     */
    public function install(Context $context): void
    {
        if (empty(static::NEW_STATE_TECHNICAL_NAME)) {
            throw new LogicException('State technical name cannot be empty');
        }

        if (empty(static::NEW_STATE_NAME)) {
            throw new LogicException('State name cannot be empty');
        }

        $this->stateInstallerHelper->createState(
            static::NEW_STATE_TECHNICAL_NAME,
            static::NEW_STATE_NAME,
            $this->getStateMachineIdOrFail($context),
            $context
        );
    }

    /**
     * @param Context $context
     * @return void
     * @throws LogicException
     * @throws StateMachineException
     */
    public function installTransitions(Context $context): void
    {
        if (empty(static::TRANSITIONS)) {
            throw new LogicException('Cannot install transitions: no transitions are defined');
        }

        $this->stateInstallerHelper->createTransitions(
            static::TRANSITIONS,
            $this->getStateMachineIdOrFail($context),
            $context
        );
    }

    /**
     * @param Context $context
     * @return void
     */
    abstract public function uninstall(Context $context): void;

    /**
     * @return string
     * @throws StateMachineException
     */
    protected function getStateMachineIdOrFail(Context $context): string
    {
        $stateMachineId = $this->stateInstallerHelper->getStateMachineId(
            static::STATE_MACHINE_TECHNICAL_NAME,
            $context
        );

        if ($stateMachineId === null) {
            throw StateMachineException::stateMachineNotFound(self::STATE_MACHINE_TECHNICAL_NAME);
        }

        return $stateMachineId;
    }
}
