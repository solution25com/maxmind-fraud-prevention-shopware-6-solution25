<?php declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;

class FraudReviewStateInstaller
{
    private const STATE_MACHINE_TECHNICAL_NAME = 'order.state';
    private const NEW_STATE_TECHNICAL_NAME = 'fraud_review';
    private const NEW_STATE_NAME = 'Fraud Review';
    private const TRANSITIONS = [
        'mark_as_fraud_review' => ['from' => 'open', 'to' => 'fraud_review'],
        'mark_as_open' => ['from' => 'fraud_review', 'to' => 'open'],
        'mark_as_cancel' => ['from' => 'fraud_review', 'to' => 'cancelled'],
        'mark_as_fraud_fail' => ['from' => 'fraud_review', 'to' => 'fraud_fail'],
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
        $deleteIds = [];
        foreach (array_keys(self::TRANSITIONS) as $actionName) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('actionName', $actionName));
            $transitions = $this->stateMachineTransitionRepository->search($criteria, $context);
            $deleteIds = array_merge($deleteIds, $transitions->getIds());
        }

        if (!empty($deleteIds)) {
            try {
                $this->stateMachineTransitionRepository->delete(array_map(fn($id) => ['id' => $id], $deleteIds), $context);
            } catch (\Exception $e) {
            }
        }
    }

    private function removeState(string $stateMachineId, Context $context): void
    {
        // Retrieve all states to be removed
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::NEW_STATE_TECHNICAL_NAME));
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
        $states = $this->stateMachineStateRepository->search($criteria, $context);

        // Arrays to collect IDs for batch deletion
        $transitionDeleteIds = [];
        $historyDeleteIds = [];
        $stateDeleteIds = [];

        // Collect all IDs in one pass
        foreach ($states as $state) {
            $stateId = $state->getId();
            $stateDeleteIds[] = ['id' => $stateId];

            // Collect transition IDs
            $transitionCriteria = new Criteria();
            $transitionCriteria->addFilter(new OrFilter([
                new EqualsFilter('fromStateId', $stateId),
                new EqualsFilter('toStateId', $stateId),
            ]));
            $transitions = $this->stateMachineTransitionRepository->searchIds($transitionCriteria, $context);
            $transitionDeleteIds = array_merge($transitionDeleteIds, array_map(fn($id) => ['id' => $id], $transitions->getIds()));

            // Collect history entry IDs
            $historyCriteria = new Criteria();
            $historyCriteria->addFilter(new OrFilter([
                new EqualsFilter('fromStateId', $stateId),
                new EqualsFilter('toStateId', $stateId),
            ]));
            $historyEntries = $this->stateMachineHistoryRepository->searchIds($historyCriteria, $context);
            $historyDeleteIds = array_merge($historyDeleteIds, array_map(fn($id) => ['id' => $id], $historyEntries->getIds()));
        }

        // Perform batch deletions
        if (!empty($transitionDeleteIds)) {
            try {
                $this->stateMachineTransitionRepository->delete($transitionDeleteIds, $context);
            } catch (\Exception $e) {
                // Log error if necessary, but continue execution
            }
        }

        if (!empty($historyDeleteIds)) {
            try {
                $this->stateMachineHistoryRepository->delete($historyDeleteIds, $context);
            } catch (\Exception $e) {
                // Log error if necessary, but continue execution
            }
        }

        if (!empty($stateDeleteIds)) {
            try {
                $this->stateMachineStateRepository->delete($stateDeleteIds, $context);
            } catch (\Exception $e) {
                // Log error if necessary, but continue execution
            }
        }
    }
}