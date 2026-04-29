<?php

declare(strict_types=1);

namespace MaxMind\Service\Installer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class FraudReviewCustomFieldsInstaller
{
    private const CUSTOM_FIELDSET_NAME = 'fraud_review_custom_fields';

    private const CUSTOM_FIELDSET = [
        'name' => self::CUSTOM_FIELDSET_NAME,
        'config' => [
            'label' => [
                'en-GB' => 'Fraud Review',
                'de-DE' => 'Betrugsüberprüfung',
                Defaults::LANGUAGE_SYSTEM => 'Fraud Review',
            ],
        ],
        'customFields' => [
            [
                'name' => 'maxmind_fraud_risk',
                'type' => CustomFieldTypes::FLOAT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Is product preorder',
                        'de-DE' => 'Ist das Produkt vorbestellbar',
                        Defaults::LANGUAGE_SYSTEM => 'Fraud risk',
                    ],
                    'customFieldPosition' => 1,
                ],
            ],
            [
                'name' => 'maxmind_fraud_score',
                'type' => CustomFieldTypes::FLOAT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Is product preorder',
                        'de-DE' => 'Ist das Produkt vorbestellbar',
                        Defaults::LANGUAGE_SYSTEM => 'Fraud score',
                    ],
                    'customFieldPosition' => 2,
                ],
            ],
            [
                'name' => 'maxmind_fraud_details',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Is product preorder',
                        'de-DE' => 'Ist das Produkt vorbestellbar',
                        Defaults::LANGUAGE_SYSTEM => 'Fraud details',
                    ],
                    'customFieldPosition' => 3,
                ],
            ],
        ],
    ];

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository,
        private readonly ContainerInterface $container
    ) {
    }

    public function install(Context $context): void
    {
        $payload = self::CUSTOM_FIELDSET;

        $existingSetId = $this->getCustomFieldSetIdByName($context, self::CUSTOM_FIELDSET_NAME);
        if ($existingSetId !== null) {
            $payload['id'] = $existingSetId;
        }

        $existingCustomFieldIdsByName = $this->getCustomFieldIdsByName(
            $context,
            array_map(static fn (array $field) => (string) $field['name'], $payload['customFields'])
        );

        foreach ($payload['customFields'] as $idx => $field) {
            $name = (string) $field['name'];
            if (isset($existingCustomFieldIdsByName[$name])) {
                $payload['customFields'][$idx]['id'] = $existingCustomFieldIdsByName[$name];
            }
        }

        $this->customFieldSetRepository->upsert([$payload], $context);
    }

    public function addRelations(Context $context): void
    {
        try {
            $customFieldSetIds = $this->getCustomFieldSetIds($context);
            if ($customFieldSetIds === []) {
                return;
            }

            $this->customFieldSetRelationRepository->upsert(array_map(static fn(string $customFieldSetId) => [
                'customFieldSetId' => $customFieldSetId,
                'entityName' => 'order',
            ], $customFieldSetIds), $context);
        } catch (\Exception) {
            // do nothing
        }
    }

    public function remove(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));
        $customFieldSetIds = $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();

        if (!empty($customFieldSetIds)) {
            $this->customFieldSetRelationRepository->delete(
                array_map(fn ($id) => ['customFieldSetId' => $id], $customFieldSetIds),
                $context
            );

            $this->customFieldSetRepository->delete(
                array_map(fn ($id) => ['id' => $id], $customFieldSetIds),
                $context
            );
        }
    }

    /**
     * @return string[]
     */
    private function getCustomFieldSetIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        return $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();
    }

    private function getCustomFieldSetIdByName(Context $context, string $name): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $name));

        return $this->customFieldSetRepository->searchIds($criteria, $context)->firstId();
    }


    private function getCustomFieldIdsByName(Context $context, array $names): array
    {
        if ($names === []) {
            return [];
        }

        if (!$this->container->has('custom_field.repository')) {
            return [];
        }

        $customFieldRepository = $this->container->get('custom_field.repository');
        if (!$customFieldRepository instanceof EntityRepository) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $names));

        $result = [];
        $entities = $customFieldRepository->search($criteria, $context)->getEntities();
        foreach ($entities as $customFieldEntity) {
            $name = $customFieldEntity->get('name');
            if (is_string($name) && $customFieldEntity->getUniqueIdentifier()) {
                $result[$name] = $customFieldEntity->getUniqueIdentifier();
            }
        }

        return $result;
    }
}
