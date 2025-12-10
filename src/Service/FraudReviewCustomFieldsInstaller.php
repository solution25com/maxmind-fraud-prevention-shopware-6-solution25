<?php

declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
                Defaults::LANGUAGE_SYSTEM => 'Mention the fallback label here',
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
                        Defaults::LANGUAGE_SYSTEM => 'Mention the fallback label here',
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
                        Defaults::LANGUAGE_SYSTEM => 'Mention the fallback label here',
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
                        Defaults::LANGUAGE_SYSTEM => 'Mention the fallback label here',
                    ],
                    'customFieldPosition' => 3,
                ],
            ],
        ],
    ];

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository
    ) {
    }

    public function install(Context $context): void
    {
        $this->customFieldSetRepository->upsert([
            self::CUSTOM_FIELDSET,
        ], $context);
    }

    public function addRelations(Context $context): void
    {
        try {
            $this->customFieldSetRelationRepository->upsert(array_map(fn (string $customFieldSetId) => [
                'customFieldSetId' => $customFieldSetId,
                'entityName' => 'product',
            ], $this->getCustomFieldSetIds($context)), $context);
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
}
