<?php

declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class FraudPassStateInstaller
{
    private const STATE_MACHINE_TECHNICAL_NAME = 'order.state';
    private const NEW_STATE_TECHNICAL_NAME     = 'fraud_pass';
    private const NEW_STATE_NAME               = 'Fraud Pass';
    private const TRANSITIONS                  = [
        'mark_as_fraud_pass' => [
            'from' => 'open',
            'to'   => 'fraud_pass',
        ],
        'mark_as_fraud_manual_review' => [
            'from' => 'fraud_pass',
            'to'   => 'fraud_review',
        ],
        'mark_as_fraud_manual_pass' => [
            'from' => 'fraud_review',
            'to'   => 'fraud_pass',
        ],
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
        $defaultLanguageId = Defaults::LANGUAGE_SYSTEM;
        if ($isAdding) {
            $this->stateMachineStateRepository->upsert([
                [
                    'technicalName'  => self::NEW_STATE_TECHNICAL_NAME,
                    'name'           => self::NEW_STATE_NAME,
                    'stateMachineId' => $stateMachineId,
                    'translations'   => [
                        $defaultLanguageId => [
                            'name' => self::NEW_STATE_NAME
                        ]
                    ]
                ]
            ], $context);

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
            $transitions[] = [
                'actionName'     => $actionName,
                'fromStateId'    => $this->getStateId($states['from'], $stateMachineId, $context),
                'toStateId'      => $this->getStateId($states['to'], $stateMachineId, $context),
                'stateMachineId' => $stateMachineId,
            ];
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
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::NEW_STATE_TECHNICAL_NAME));
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachineId));

        $states = $this->stateMachineStateRepository->search($criteria, $context);

        foreach ($states->getIds() as $stateId) {
            $this->removeStateMachineHistoryReferences($stateId, $context);
            $this->stateMachineStateRepository->delete([['id' => $stateId]], $context);
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
            $this->stateMachineHistoryRepository->delete([['id' => $entryId]], $context);
        }
    }
}
