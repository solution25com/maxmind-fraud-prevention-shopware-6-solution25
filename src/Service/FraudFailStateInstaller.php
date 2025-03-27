<?php declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;

class FraudFailStateInstaller
{
    private const STATE_MACHINE_TECHNICAL_NAME = 'order.state';
    private const NEW_STATE_TECHNICAL_NAME = 'fraud_fail';
    private const NEW_STATE_NAME = 'Fraud Fail';
    private const TRANSITIONS = [
        'mark_as_fraud_fail' => ['from' => 'fraud_review', 'to' => 'fraud_fail'],
        'mark_as_fraud_review' => ['from' => 'fraud_fail', 'to' => 'fraud_review'],
    ];

    public function __construct(
        private readonly EntityRepository $stateMachineRepository,
        private readonly EntityRepository $stateMachineStateRepository,
        private readonly EntityRepository $stateMachineTransitionRepository,
        private readonly EntityRepository $stateMachineHistoryRepository
    ) {
    }

    public function install(Context $context): void
    {
        $stateMachineId = $this->getStateMachineId($context);
        if ($stateMachineId === null) {
            return;
        }
        $this->createState($stateMachineId, $context);
        $this->createTransitions($stateMachineId, $context);
    }

    public function uninstall(Context $context): void
    {
        $stateMachineId = $this->getStateMachineId($context);
        if ($stateMachineId === null) {
            return;
        }
        $this->removeTransitions($context);
        $this->removeState($stateMachineId, $context);
    }

    private function getStateMachineId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::STATE_MACHINE_TECHNICAL_NAME));
        $stateMachine = $this->stateMachineRepository->search($criteria, $context)->first();
        return $stateMachine ? $stateMachine->getId() : null;
    }

    private function getStateId(string $technicalName, string $stateMachineId, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
        $state = $this->stateMachineStateRepository->search($criteria, $context)->first();
        return $state ? $state->getId() : null;
    }

    private function createState(string $stateMachineId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::NEW_STATE_TECHNICAL_NAME));
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
        $existingState = $this->stateMachineStateRepository->search($criteria, $context)->first();

        if ($existingState) {
            return;
        }

        $stateData = [
            'technicalName' => self::NEW_STATE_TECHNICAL_NAME,
            'name' => self::NEW_STATE_NAME,
            'stateMachineId' => $stateMachineId,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => self::NEW_STATE_NAME]
            ]
        ];

        $this->stateMachineStateRepository->upsert([$stateData], $context);
    }

    private function createTransitions(string $stateMachineId, Context $context): void
    {
        $transitions = [];
        foreach (self::TRANSITIONS as $actionName => $states) {
            $fromStateId = $this->getStateId($states['from'], $stateMachineId, $context);
            $toStateId = $this->getStateId($states['to'], $stateMachineId, $context);

            if ($fromStateId === null || $toStateId === null) {
                continue;
            }

            $transition = [
                'actionName' => $actionName,
                'fromStateId' => $fromStateId,
                'toStateId' => $toStateId,
                'stateMachineId' => $stateMachineId,
            ];

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('actionName', $actionName));
            $criteria->addFilter(new EqualsFilter('fromStateId', $transition['fromStateId']));
            $criteria->addFilter(new EqualsFilter('toStateId', $transition['toStateId']));
            $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
            $existingTransition = $this->stateMachineTransitionRepository->search($criteria, $context)->first();

            if ($existingTransition) {
                continue;
            }

            $transitions[] = $transition;
        }

        if (!empty($transitions)) {
            $this->stateMachineTransitionRepository->upsert($transitions, $context);
        }
    }

    private function removeTransitions(Context $context): void
    {
        foreach (array_keys(self::TRANSITIONS) as $actionName) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('actionName', $actionName));
            $transitions = $this->stateMachineTransitionRepository->search($criteria, $context);

            foreach ($transitions->getIds() as $transitionId) {
                try {
                    $this->stateMachineTransitionRepository->delete([['id' => $transitionId]], $context);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }

    private function removeState(string $stateMachineId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::NEW_STATE_TECHNICAL_NAME));
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
        $states = $this->stateMachineStateRepository->search($criteria, $context);

        foreach ($states as $state) {
            $stateId = $state->getId();

            try {
                $this->removeAllTransitionsForState($stateId, $context);
            } catch (\Exception $e) {
                continue;
            }

            try {
                $this->removeStateMachineHistoryReferences($stateId, $context);
            } catch (\Exception $e) {
                continue;
            }

            try {
                $this->stateMachineStateRepository->delete([['id' => $stateId]], $context);
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    private function removeAllTransitionsForState(string $stateId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('fromStateId', $stateId),
            new EqualsFilter('toStateId', $stateId),
        ]));
        $transitions = $this->stateMachineTransitionRepository->search($criteria, $context);

        foreach ($transitions->getIds() as $transitionId) {
            try {
                $this->stateMachineTransitionRepository->delete([['id' => $transitionId]], $context);
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    private function removeStateMachineHistoryReferences(string $stateId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('fromStateId', $stateId),
            new EqualsFilter('toStateId', $stateId),
        ]));
        $historyEntries = $this->stateMachineHistoryRepository->search($criteria, $context);

        foreach ($historyEntries->getIds() as $entryId) {
            try {
                $this->stateMachineHistoryRepository->delete([['id' => $entryId]], $context);
            } catch (\Exception $e) {
                continue;
            }
        }
    }
}