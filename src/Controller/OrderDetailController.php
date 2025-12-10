<?php

declare(strict_types=1);

namespace MaxMind\Controller;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class OrderDetailController extends AbstractController
{
    /** @var EntityRepository<OrderCollection> */
    private readonly EntityRepository $orderRepository;

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(EntityRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    #[Route(
        path: '/api/_action/maxmind/fraud-details/{orderId}',
        name: 'api.action.maxmind.fraud_details',
        methods: ['GET']
    )]
    public function getFraudDetails(string $orderId, Context $context): JsonResponse
    {
        $criteria = new Criteria([$orderId]);

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->get($orderId);
        if (!$order instanceof OrderEntity) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        $customFields = $order->getCustomFields() ?? [];
        $fraudRisk = $customFields['maxmind_fraud_risk'] ?? 'N/A';

        return $this->json([
            'orderId'   => $order->getId(),
            'fraudRisk' => $fraudRisk,
        ]);
    }
}
