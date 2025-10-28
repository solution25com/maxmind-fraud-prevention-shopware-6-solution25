<?php

declare(strict_types=1);

namespace MaxMind\Service;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

/** @phpstan-ignore-next-line Deprecated until migration to CookieGroupCollectEvent */
class CookieProvider implements CookieProviderInterface
{
  /** @phpstan-ignore-next-line Deprecated until migration to CookieGroupCollectEvent */
    public function __construct(private readonly CookieProviderInterface $inner)
    {
    }

    public function getCookieGroups(): array
    {
        $groups = $this->inner->getCookieGroups();

      /** @phpstan-ignore-next-line */
        foreach ($groups as &$group) {
          /** @phpstan-ignore-next-line */
            if (($group['snippet_name'] ?? null) !== 'cookie.groupRequired' || !\array_key_exists('entries', $group)) {
                continue;
            }

            $alreadyRegistered = \array_filter(
                $group['entries'],
                static fn (array $entry): bool => ($entry['snippet_name'] ?? '') === 'cookie.maxmind_fraud_cookie'
            );

            if ($alreadyRegistered !== []) {
                return $groups;
            }

            $group['entries'][] = [
                'snippet_name' => 'cookie.maxmind_fraud_cookie',
                'cookie' => 'maxmind_fraud_cookie',
                'value' => '1',
                'expiration' => '1',
            ];

            return $groups;
        }

        $groups[] = [
            'snippet_name' => 'cookie.groupRequired',
            'entries' => [
                [
                    'snippet_name' => 'cookie.maxmind_fraud_cookie',
                    'cookie' => 'maxmind_fraud_cookie',
                    'value' => '1',
                    'expiration' => '1',
                ],
            ],
        ];

        return $groups;
    }
}
