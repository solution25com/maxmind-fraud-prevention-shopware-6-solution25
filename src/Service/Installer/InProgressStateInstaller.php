<?php

declare(strict_types=1);

namespace MaxMind\Service\Installer;

use Exception;
use Shopware\Core\Framework\Context;

class InProgressStateInstaller extends StateInstaller
{
    protected const NEW_STATE_TECHNICAL_NAME = 'in_progress';
    protected const NEW_STATE_NAME = 'In Progress';
    protected const TRANSITIONS = [
        'mark_as_in_progress' => ['from' => 'fraud_pass', 'to' => 'in_progress'],
        'mark_as_in_progress_from_open' => ['from' => 'open', 'to' => 'in_progress'],
        'mark_as_fraud_pass_from_in_progress' => ['from' => 'in_progress', 'to' => 'fraud_pass'],
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
