<?php declare(strict_types=1);

namespace MaxMind;

use MaxMind\Service\CompleteStateInstaller;
use MaxMind\Service\FraudFailStateInstaller;
use MaxMind\Service\FraudPassStateInstaller;
use MaxMind\Service\InProgressStateInstaller;
use MaxMind\Service\PendingFraudReviewStateInstaller;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use MaxMind\Service\FraudReviewStateInstaller;
use MaxMind\Service\FraudReviewCustomFieldsInstaller;

class MaxMind extends Plugin
{
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
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->getCustomFieldsInstaller()->addRelations($activateContext->getContext());
    }

    private function getCustomFieldsInstaller(): object
    {
        if ($this->container->has(FraudReviewCustomFieldsInstaller::class)) {
            return $this->container->get(FraudReviewCustomFieldsInstaller::class);
        }
        return new FraudReviewCustomFieldsInstaller(
            $this->container->get('custom_field_set.repository'),
            $this->container->get('custom_field_set_relation.repository')
        );
    }

    private function getOrderStateInstaller(): object
    {
        if ($this->container->has(FraudReviewStateInstaller::class)) {
            return $this->container->get(FraudReviewStateInstaller::class);
        }
        return new FraudReviewStateInstaller(
            $this->container->get('state_machine.repository'),
            $this->container->get('state_machine_state.repository'),
            $this->container->get('state_machine_transition.repository'),
            $this->container->get('state_machine_history.repository')
        );
    }

    private function getPendingOrderStateInstaller(): object
    {
        if ($this->container->has(PendingFraudReviewStateInstaller::class)) {
            return $this->container->get(PendingFraudReviewStateInstaller::class);
        }
        return new PendingFraudReviewStateInstaller(
            $this->container->get('state_machine.repository'),
            $this->container->get('state_machine_state.repository'),
            $this->container->get('state_machine_transition.repository'),
            $this->container->get('state_machine_history.repository')
        );
    }

    private function getFraudPassOrderStateInstaller(): object
    {
        if ($this->container->has(FraudPassStateInstaller::class)) {
            return $this->container->get(FraudPassStateInstaller::class);
        }
        return new FraudPassStateInstaller(
            $this->container->get('state_machine.repository'),
            $this->container->get('state_machine_state.repository'),
            $this->container->get('state_machine_transition.repository'),
            $this->container->get('state_machine_history.repository')
        );
    }

    private function getFraudFailOrderStateInstaller(): object
    {
        if ($this->container->has(FraudFailStateInstaller::class)) {
            return $this->container->get(FraudFailStateInstaller::class);
        }
        return new FraudFailStateInstaller(
            $this->container->get('state_machine.repository'),
            $this->container->get('state_machine_state.repository'),
            $this->container->get('state_machine_transition.repository'),
            $this->container->get('state_machine_history.repository')
        );
    }

    private function getCompleteOrderStateInstaller(): object
    {
        if ($this->container->has(CompleteStateInstaller::class)) {
            return $this->container->get(CompleteStateInstaller::class);
        }
        return new CompleteStateInstaller(
            $this->container->get('state_machine.repository'),
            $this->container->get('state_machine_state.repository'),
            $this->container->get('state_machine_transition.repository'),
            $this->container->get('state_machine_history.repository')
        );
    }

    private function getInProgressStateInstaller(): object
    {
        if ($this->container->has(InProgressStateInstaller::class)) {
            return $this->container->get(InProgressStateInstaller::class);
        }
        return new InProgressStateInstaller(
            $this->container->get('state_machine.repository'),
            $this->container->get('state_machine_state.repository'),
            $this->container->get('state_machine_transition.repository'),
            $this->container->get('state_machine_history.repository')
        );
    }
}