<?php
declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;

class StateInstallerHelper
{
    private ?array $stateIds = null;

    public function __construct(
        private readonly EntityRepository $stateMachineRepository,
        private readonly EntityRepository $stateMachineStateRepository,
        private readonly EntityRepository $stateMachineTransitionRepository,
        private readonly EntityRepository $stateMachineHistoryRepository,
    ) {
    }

    public function getStateMachineId(string $technicalName, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        return $this->stateMachineRepository->searchIds($criteria, $context)->firstId();
    }

    public function createTransitions(array $actions, string $stateMachineId, Context $context): void
    {
        $transitions = [];

        $stateIds = $this->preloadStates($actions, $stateMachineId, $context);
        foreach ($actions as $actionName => $states) {
            $fromStateId = $stateIds[$states['from']] ?? null;
            $toStateId = $stateIds[$states['to']] ?? null;

            if ($fromStateId === null || $toStateId === null) {
                continue;
            }

            $transition = [
                'actionName' => $actionName,
                'fromStateId' => $fromStateId,
                'toStateId' => $toStateId,
                'stateMachineId' => $stateMachineId,
            ];

            $transitions[$actionName] = $transition;
        }

        $criteria = new Criteria();
        $filters = [];
        foreach ($transitions as $transition) {
            $filter = new AndFilter();
            foreach ($transition as $key => $value) {
                $filter->addQuery(new EqualsFilter($key, $value));
            }

            $filters[] = $filter;
        }
        $criteria->addFilter(new OrFilter($filters));
        $existingTransitions = $this->stateMachineTransitionRepository->search($criteria, $context);
        /** @var StateMachineTransitionEntity $existingTransition */
        foreach ($existingTransitions as $existingTransition) {
            unset($transitions[$existingTransition->getActionName()]);
        }
        $transitions = array_values($transitions);

        if (!empty($transitions)) {
            $this->stateMachineTransitionRepository->upsert($transitions, $context);
        }
    }

    public function createState(string $technicalName, string $name, string $stateMachineId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
        $existingState = $this->stateMachineStateRepository->search($criteria, $context)->first();

        if ($existingState) {
            return;
        }

        $stateData = [
            'technicalName' => $technicalName,
            'name' => $name,
            'stateMachineId' => $stateMachineId,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => $name],
            ],
        ];

        $this->stateMachineStateRepository->upsert([$stateData], $context);
    }

    public function removeTransitions(array $actions, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('actionName', array_keys($actions)));
        $deleteIds = $this->stateMachineTransitionRepository->searchIds($criteria, $context)->getIds();

        if (!empty($deleteIds)) {
            try {
                $this->stateMachineTransitionRepository->delete(
                    array_map(fn ($id) => ['id' => $id], $deleteIds),
                    $context
                );
            } catch (\Exception) {
            }
        }
    }

    public function removeState(string $technicalName, string $stateMachineId, Context $context): void
    {
        // Retrieve all states to be removed
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
        $stateDeleteIds = $this->stateMachineStateRepository->searchIds($criteria, $context)->getIds();

        $transitionCriteria = new Criteria();
        $transitionCriteria->addFilter(new OrFilter([
            new EqualsAnyFilter('fromStateId', $stateDeleteIds),
            new EqualsAnyFilter('toStateId', $stateDeleteIds),
        ]));
        $transitionDeleteIds = $this->stateMachineTransitionRepository->searchIds($transitionCriteria, $context)
            ->getIds();

        $historyCriteria = new Criteria();
        $historyCriteria->addFilter(new OrFilter([
            new EqualsAnyFilter('fromStateId', $stateDeleteIds),
            new EqualsAnyFilter('toStateId', $stateDeleteIds),
        ]));
        $historyDeleteIds = $this->stateMachineHistoryRepository->searchIds($historyCriteria, $context)->getIds();

        $stateDeleteIds = array_map(fn ($id) => ['id' => $id], $stateDeleteIds);
        $transitionDeleteIds = array_map(fn ($id) => ['id' => $id], $transitionDeleteIds);
        $historyDeleteIds = array_map(fn ($id) => ['id' => $id], $historyDeleteIds);

        // Perform batch deletions
        if (!empty($transitionDeleteIds)) {
            try {
                $this->stateMachineTransitionRepository->delete($transitionDeleteIds, $context);
            } catch (\Exception) {
                // Log error if necessary, but continue execution
            }
        }

        if (!empty($historyDeleteIds)) {
            try {
                $this->stateMachineHistoryRepository->delete($historyDeleteIds, $context);
            } catch (\Exception) {
                // Log error if necessary, but continue execution
            }
        }

        if (!empty($stateDeleteIds)) {
            try {
                $this->stateMachineStateRepository->delete($stateDeleteIds, $context);
            } catch (\Exception) {
                // Log error if necessary, but continue execution
            }
        }
    }

    private function preloadStates(array $actions, string $stateMachineId, Context $context): array
    {
        if (!$this->stateIds) {
            $this->stateIds = [];
        }

        $stateNames = [];
        foreach ($actions as $states) {
            $stateNames[$states['from']] = 1;
            $stateNames[$states['to']] = 1;
        }

        if (\array_key_exists($stateMachineId, $this->stateIds)) {
            foreach (array_keys($stateNames) as $stateName) {
                if (\array_key_exists($stateName, $this->stateIds[$stateMachineId])) {
                    unset($stateNames[$stateName]);
                }
            }
        }

        if (\count($stateNames) > 0) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('technicalName', array_keys($stateNames)));
            $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
            $this->stateIds[$stateMachineId] = [];

            $states = $this->stateMachineStateRepository->search($criteria, $context);
            foreach ($states as $state) {
                $this->stateIds[$stateMachineId][$state->getTechnicalName()] = $state->getId();
            }
        }

        return $this->stateIds[$stateMachineId];
    }
}
