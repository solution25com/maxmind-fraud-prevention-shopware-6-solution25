<?php declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;

class InProgressStateInstaller
{
    private const STATE_MACHINE_TECHNICAL_NAME = 'order.state';
    private const NEW_STATE_TECHNICAL_NAME = 'in_progress';
    private const NEW_STATE_NAME = 'In Progress';
    private const TRANSITIONS = [
        'mark_as_in_progress' => [
            'from' => 'fraud_pass',
            'to' => 'in_progress',
        ],
        'mark_as_fraud_pass_from_in_progress' => [
            'from' => 'in_progress',
            'to' => 'fraud_pass',
        ]
    ];
    public function __construct(
        private readonly EntityRepository $stateMachineRepository,
        private readonly EntityRepository $stateMachineStateRepository,
        private readonly EntityRepository $stateMachineTransitionRepository,
        private readonly EntityRepository $stateMachineHistoryRepository
    ) {
    }

    public function managePresaleStatuses(Context $context, bool $isAdding): void
    {
        $stateMachineId = $this->getStateMachineId($this->stateMachineRepository, $context);
        //get default system language id
        //check if state machine is already installed get id of the state machine state
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::NEW_STATE_TECHNICAL_NAME));
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
        $state = $this->stateMachineStateRepository->search($criteria, $context)->first();

        $defaultLanguageId = Defaults::LANGUAGE_SYSTEM;
        if ($isAdding) {
            $array = [
                [
                    'technicalName' => self::NEW_STATE_TECHNICAL_NAME,
                    'name' => self::NEW_STATE_NAME,
                    'stateMachineId' => $stateMachineId,
                    'translations' => [
                        $defaultLanguageId => [
                            'name' => self::NEW_STATE_NAME
                        ]
                    ]
                ]
            ];
            if ($state) {
                $array[0]['id'] = $state->getId();
            }
            $this->stateMachineStateRepository->upsert($array, $context);

            $transitions = $this->buildTransitions($stateMachineId, $context);
            $this->stateMachineTransitionRepository->upsert($transitions, $context);
        } else {
            $this->removeTransitions($context);
            $this->removeState($context, $stateMachineId);
        }
    }

    private function getStateMachineId(EntityRepository $stateMachineRepository, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::STATE_MACHINE_TECHNICAL_NAME));
        $stateMachine = $stateMachineRepository->search($criteria, $context)->first();
        if (!$stateMachine) {
            throw new \RuntimeException(sprintf('State machine "%s" not found.', self::STATE_MACHINE_TECHNICAL_NAME));
        }

        return $stateMachine->getId();
    }

    private function getStateId(string $technicalName, string $stateMachineId, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
        $state = $this->stateMachineStateRepository->search($criteria, $context)->first();

        if (!$state) {
            throw new \RuntimeException(sprintf('State "%s" not found in state machine "%s".', $technicalName, $stateMachineId));
        }

        return $state->getId();
    }

    private function buildTransitions(string $stateMachineId, Context $context): array
    {
        $transitions = [];
        foreach (self::TRANSITIONS as $actionName => $states) {
            $transition = [
                'actionName' => $actionName,
                'fromStateId' => $this->getStateId($states['from'], $stateMachineId, $context),
                'toStateId' => $this->getStateId($states['to'], $stateMachineId, $context),
                'stateMachineId' => $stateMachineId,
            ];
            //check if transition already exists
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('actionName', $actionName));
            $criteria->addFilter(new EqualsFilter('fromStateId', $transition['fromStateId']));
            $criteria->addFilter(new EqualsFilter('toStateId', $transition['toStateId']));
            $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
            $transitionExists = $this->stateMachineTransitionRepository->search($criteria, $context)->first();
            if($transitionExists){
                $transition['id'] = $transitionExists->getId();
            }
            $transitions[] = $transition;
        }
        return $transitions;
    }

    private function removeTransitions(Context $context): void
    {
        foreach (array_keys(self::TRANSITIONS) as $actionName) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('actionName', $actionName));

            $transitions = $this->stateMachineTransitionRepository->search($criteria, $context);

            foreach ($transitions->getIds() as $transitionId) {
                $this->stateMachineTransitionRepository->delete([['id' => $transitionId]], $context);
            }
        }
    }

    private function removeState(Context $context, string $stateMachineId): void
    {
        return;//complete state is default on shopware we dont need to delete them
//        $criteria = new Criteria();
//        $criteria->addFilter(new EqualsFilter('technicalName', self::NEW_STATE_TECHNICAL_NAME));
//        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));
//
//        $states = $this->stateMachineStateRepository->search($criteria, $context);
//
//        foreach ($states->getIds() as $stateId) {
//            $this->removeStateMachineHistoryReferences($stateId, $context);
//            $this->stateMachineStateRepository->delete([['id' => $stateId]], $context);
//        }
    }

    private function removeStateMachineHistoryReferences(string $stateId, Context $context): void
    {
        //complete state is default on shopware we dont need to delete them
//        $criteria = new Criteria();
//        $criteria->addFilter(new OrFilter([
//            new EqualsFilter('fromStateId', $stateId),
//            new EqualsFilter('toStateId', $stateId),
//        ]));
//
//        $historyEntries = $this->stateMachineHistoryRepository->search($criteria, $context);
//
//        foreach ($historyEntries->getIds() as $entryId) {
//            $this->stateMachineHistoryRepository->delete([['id' => $entryId]], $context);
//        }
    }
}