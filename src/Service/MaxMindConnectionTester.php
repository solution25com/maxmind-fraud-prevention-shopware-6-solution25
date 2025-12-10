<?php

declare(strict_types=1);

namespace MaxMind\Service;

use InvalidArgumentException;
use MaxMind\Exception\AuthenticationException;
use MaxMind\Exception\WebServiceException;
use MaxMind\MinFraud;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class MaxMindConnectionTester
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws WebServiceException
     */
    public function assertCredentialsValid(?string $salesChannelId, ?int $accountId, ?string $licenseKey): void
    {
        $accountId ??= (int) $this->systemConfigService->get('MaxMind.config.MaxMindConfigAccountId', $salesChannelId);
        $licenseKey ??= (string) $this->systemConfigService->get('MaxMind.config.MaxMindConfigLicenseKey', $salesChannelId);

        if ($accountId === 0 || $licenseKey === '') {
            throw new InvalidArgumentException('MaxMind account ID or license key is missing.');
        }

        try {
            $client = new MinFraud($accountId, $licenseKey);

            $request = $client->withDevice(ipAddress: '8.8.8.8', acceptLanguage: 'en-US');
            $request = $request->withEvent(
                transactionId: 'test-' . uniqid('', true),
                shopId: 'credential-check',
                time: (new \DateTimeImmutable())->format('c'),
                type: 'purchase'
            );
            $request = $request->withAccount(userId: 'test-user');
            $request = $request->withEmail(address: 'test@example.com', domain: 'example.com');
            $request = $request->withBilling(
                firstName: 'Test',
                lastName: 'Customer',
                address: '101 Test Street',
                city: 'Testville',
                country: 'US',
                postal: '12345'
            );
            $request = $request->withOrder(amount: 10.0, currency: 'USD');

            $request->insights();
        } catch (AuthenticationException $exception) {
            $this->logger->warning('MaxMind credential check failed due to authentication error.', ['exception' => $exception]);

            throw $exception;
        } catch (WebServiceException $exception) {
            $this->logger->error('MaxMind credential check failed due to a web service error.', ['exception' => $exception]);

            throw $exception;
        }
    }
}
