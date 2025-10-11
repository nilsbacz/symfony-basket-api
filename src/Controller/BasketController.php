<?php

namespace App\Controller;

use App\Entity\Basket;
use App\Service\BasketService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BasketController extends AbstractController
{
    #[Route('/api/baskets', name: 'api_baskets_create', methods: ['POST'])]
    public function create(BasketService $basketService): JsonResponse
    {
        $basket = $basketService->create();

        // Location header points to the GET endpoint for this basket
        $location = $this->generateUrl('api_baskets_get', ['id' => $basket->getId()]);

        return $this->json(
            [
                'id'        => $basket->getId(),
                'createdAt' => $basket->getCreatedAt()->format(DATE_ATOM),
            ],
            Response::HTTP_CREATED,
            ['Location' => $location]
        );
    }

    // Minimal GET so the Location header resolves
    #[Route('/api/baskets/{id}', name: 'api_baskets_get', methods: ['GET'])]
    public function getOne(Basket $basket): JsonResponse
    {
        return $this->json([
            'id'        => $basket->getId(),
            'createdAt' => $basket->getCreatedAt()->format(DATE_ATOM),
            'items'     => [], // TODO fill later when items exist
        ]);
    }
}
