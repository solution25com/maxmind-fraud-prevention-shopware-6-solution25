<?php

declare(strict_types=1);

namespace MaxMind\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class OrderDetailController extends AbstractController
{
    private EntityRepository $orderRepository;

    public function __construct(EntityRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    #[Route('/api/_action/maxmind/fraud-details/{orderId}', name: 'api.action.maxmind.fraud_details', methods: ['GET'])]
    public function getFraudDetails(string $orderId, Context $context)
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('customFields');

        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        return $this->json([
            'orderId'   => $order->getId(),
            'fraudRisk' => $order->getCustomFields()['maxmind_fraud_risk'] ?? 'N/A',
        ]);
    }
}
