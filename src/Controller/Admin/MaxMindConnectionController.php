<?php

declare(strict_types=1);

namespace MaxMind\Controller\Admin;

use InvalidArgumentException;
use MaxMind\Exception\AuthenticationException;
use MaxMind\Exception\WebServiceException;
use MaxMind\Service\MaxMindConnectionTester;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class MaxMindConnectionController
{
    public function __construct(private readonly MaxMindConnectionTester $connectionTester)
    {
    }

    #[Route(path: '/api/_action/solu1-maxmind/test-connection', name: 'api.action.solu1-maxmind.test-connection', methods: ['POST'])]
    public function testConnection(Request $request): Response
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            $payload = $request->request->all();
        }

        $salesChannelId = $payload['salesChannelId'] ?? null;
        $accountId = $payload['MaxMind.config.MaxMindConfigAccountId']
            ?? $payload['MaxMindConfigAccountId']
            ?? $payload['accountId']
            ?? null;
        $licenseKey = $payload['MaxMind.config.MaxMindConfigLicenseKey']
            ?? $payload['MaxMindConfigLicenseKey']
            ?? $payload['licenseKey']
            ?? null;

        try {
            $this->connectionTester->assertCredentialsValid(
                is_string($salesChannelId) ? $salesChannelId : null,
                $accountId !== null ? (int) $accountId : null,
                $licenseKey !== null ? (string) $licenseKey : null
            );
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (AuthenticationException $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication with MaxMind failed. Please verify your Account ID and License Key.',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (WebServiceException $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Connection to MaxMind failed: ' . $exception->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Unexpected error during MaxMind connection test: ' . $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Successfully connected to MaxMind.',
        ]);
    }
}
