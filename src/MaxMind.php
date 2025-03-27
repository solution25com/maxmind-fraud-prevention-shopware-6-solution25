<?php declare(strict_types=1);

namespace MaxMind;

use MaxMind\Service\CompleteStateInstaller;
use MaxMind\Service\DoneCancelStateInstaller;
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
        $this->getOrderStateInstaller()->managePresaleStatuses($installContext->getContext(), true);
        $this->getCustomFieldsInstaller()->install($installContext->getContext());
        $this->getFraudPassOrderStateInstaller()->managePresaleStatuses($installContext->getContext(), true);
        $this->getFraudFailOrderStateInstaller()->managePresaleStatuses($installContext->getContext(), true);
        $this->getDoneCancelOrderStateInstaller()->managePresaleStatuses($installContext->getContext(), false);
        $this->getCompleteOrderStateInstaller()->managePresaleStatuses($installContext->getContext(), true);
        $this->getInProgressStateInstaller()->managePresaleStatuses($installContext->getContext(), true);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        $this->getOrderStateInstaller()->managePresaleStatuses($uninstallContext->getContext(), false);
        $this->getCustomFieldsInstaller()->remove($uninstallContext->getContext());
        $this->getPendingOrderStateInstaller()->managePresaleStatuses($uninstallContext->getContext(), false);
        $this->getFraudPassOrderStateInstaller()->managePresaleStatuses($uninstallContext->getContext(), false);
        $this->getFraudFailOrderStateInstaller()->managePresaleStatuses($uninstallContext->getContext(), false);
        $this->getDoneCancelOrderStateInstaller()->managePresaleStatuses($uninstallContext->getContext(), false);
        $this->getCompleteOrderStateInstaller()->managePresaleStatuses($uninstallContext->getContext(), false);
        $this->getInProgressStateInstaller()->managePresaleStatuses($uninstallContext->getContext(), false);
    }
    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        $this->getInProgressStateInstaller()->managePresaleStatuses($updateContext->getContext(), true);
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
    private function getDoneCancelOrderStateInstaller(): object
    {
        if ($this->container->has(DoneCancelStateInstaller::class)) {
            return $this->container->get(DoneCancelStateInstaller::class);
        }

        return new DoneCancelStateInstaller(
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
