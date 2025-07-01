<?php
declare(strict_types=1);

namespace MaxMind;

use MaxMind\Service\CancelledStateInstaller;
use MaxMind\Service\CompleteStateInstaller;
use MaxMind\Service\FraudFailStateInstaller;
use MaxMind\Service\FraudPassStateInstaller;
use MaxMind\Service\FraudReviewCustomFieldsInstaller;
use MaxMind\Service\FraudReviewStateInstaller;
use MaxMind\Service\InProgressStateInstaller;
use MaxMind\Service\PendingFraudReviewStateInstaller;
use MaxMind\Service\StateInstallerHelper;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class MaxMind extends Plugin
{
    private ?StateInstallerHelper $stateInstallerHelper = null;

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $context = $installContext->getContext();
        $this->getOrderStateInstaller()->install($context);
        $this->getCustomFieldsInstaller()->install($context);
        $this->getFraudPassOrderStateInstaller()->install($context);
        $this->getFraudFailOrderStateInstaller()->install($context);
        $this->getCompleteOrderStateInstaller()->install($context);
        $this->getInProgressStateInstaller()->install($context);
        $this->getCancelledStateInstaller()->install($context);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        $context = $uninstallContext->getContext();
        $this->getOrderStateInstaller()->uninstall($context);
        $this->getCustomFieldsInstaller()->remove($context);
        $this->getPendingOrderStateInstaller()->uninstall($context);
        $this->getFraudPassOrderStateInstaller()->uninstall($context);
        $this->getFraudFailOrderStateInstaller()->uninstall($context);
        $this->getCompleteOrderStateInstaller()->uninstall($context);
        $this->getInProgressStateInstaller()->uninstall($context);
        $this->getCancelledStateInstaller()->uninstall($context);
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        $context = $updateContext->getContext();
        $this->getCancelledStateInstaller()->install($context);
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->getCustomFieldsInstaller()->addRelations($activateContext->getContext());
    }

    private function getCustomFieldsInstaller(): FraudReviewCustomFieldsInstaller
    {
        if ($this->container->has(FraudReviewCustomFieldsInstaller::class)) {
            $installer = $this->container->get(FraudReviewCustomFieldsInstaller::class);
            if ($installer instanceof FraudReviewCustomFieldsInstaller) {
                return $installer;
            }
        }

        return new FraudReviewCustomFieldsInstaller(
            $this->container->get('custom_field_set.repository'),
            $this->container->get('custom_field_set_relation.repository')
        );
    }

    private function getOrderStateInstaller(): FraudReviewStateInstaller
    {
        if ($this->container->has(FraudReviewStateInstaller::class)) {
            return $this->container->get(FraudReviewStateInstaller::class);
        }

        return new FraudReviewStateInstaller($this->getStateInstallerHelper());
    }

    private function getPendingOrderStateInstaller(): PendingFraudReviewStateInstaller
    {
        if ($this->container->has(PendingFraudReviewStateInstaller::class)) {
            $installer = $this->container->get(PendingFraudReviewStateInstaller::class);
            if ($installer instanceof PendingFraudReviewStateInstaller) {
                return $installer;
            }
        }

        return new PendingFraudReviewStateInstaller($this->getStateInstallerHelper());
    }

    private function getFraudPassOrderStateInstaller(): FraudPassStateInstaller
    {
        if ($this->container->has(FraudPassStateInstaller::class)) {
            $installer = $this->container->get(FraudPassStateInstaller::class);
            if ($installer instanceof FraudPassStateInstaller) {
                return $installer;
            }
        }

        return new FraudPassStateInstaller($this->getStateInstallerHelper());
    }

    private function getFraudFailOrderStateInstaller(): FraudFailStateInstaller
    {
        if ($this->container->has(FraudFailStateInstaller::class)) {
            $installer = $this->container->get(FraudFailStateInstaller::class);
            if ($installer instanceof FraudFailStateInstaller) {
                return $installer;
            }
        }

        return new FraudFailStateInstaller($this->getStateInstallerHelper());
    }

    private function getCompleteOrderStateInstaller(): CompleteStateInstaller
    {
        if ($this->container->has(CompleteStateInstaller::class)) {
            $installer = $this->container->get(CompleteStateInstaller::class);
            if ($installer instanceof CompleteStateInstaller) {
                return $installer;
            }
        }

        return new CompleteStateInstaller($this->getStateInstallerHelper());
    }

    private function getInProgressStateInstaller(): InProgressStateInstaller
    {
        if ($this->container->has(InProgressStateInstaller::class)) {
            $installer = $this->container->get(InProgressStateInstaller::class);
            if ($installer instanceof InProgressStateInstaller) {
                return $installer;
            }
        }

        return new InProgressStateInstaller($this->getStateInstallerHelper());
    }

    private function getCancelledStateInstaller(): CancelledStateInstaller
    {
        if ($this->container->has(CancelledStateInstaller::class)) {
            $installer = $this->container->get(CancelledStateInstaller::class);
            if ($installer instanceof CancelledStateInstaller) {
                return $installer;
            }
        }

        return new CancelledStateInstaller($this->getStateInstallerHelper());
    }

    private function getStateInstallerHelper(): StateInstallerHelper
    {
        if ($this->stateInstallerHelper instanceof StateInstallerHelper) {
            return $this->stateInstallerHelper;
        }

        if ($this->container->has(StateInstallerHelper::class)) {
            $this->stateInstallerHelper = $this->container->get(StateInstallerHelper::class);
        }

        if (!$this->stateInstallerHelper instanceof StateInstallerHelper) {
            $this->stateInstallerHelper = new StateInstallerHelper(
                $this->container->get('state_machine.repository'),
                $this->container->get('state_machine_state.repository'),
                $this->container->get('state_machine_transition.repository'),
                $this->container->get('state_machine_history.repository'),
            );
        }

        return $this->stateInstallerHelper;
    }
}
