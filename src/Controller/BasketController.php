<?php

namespace App\Controller;

use App\Entity\Basket;
use App\Repository\BasketItemRepository;
use App\Repository\BasketRepository;
use App\Repository\ProductRepository;
use App\Service\BasketService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
                'id' => $basket->getId(),
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
            'id' => $basket->getId(),
            'createdAt' => $basket->getCreatedAt()->format(DATE_ATOM),
            'items' => $basket->getItems()->toArray(),
        ]);
    }

    #[Route('/api/baskets/{id}/items', name: 'api_baskets_items_add', methods: ['POST'])]
    public function addItem(
        int                    $id,
        Request                $request,
        ProductRepository      $products,
        BasketRepository       $baskets,
        BasketItemRepository   $basketItemRepository,
        EntityManagerInterface $entityManager): JsonResponse
    {
        $requestData = $request->toArray();
        $productId = $requestData['productId'];
        $amount = $requestData['amount'] ?? 1;

        $productToAdd = $products->find($productId);
        $basket = $baskets->find($id);

        if (!$basket) {
            return $this->json(['error' => 'basket not found'], 404);
        }

        if (!$productToAdd) {
            return $this->json(['error' => 'product not found'], 404);
        }

        $basketItem = $basketItemRepository->createByProduct($productToAdd, $amount);

        // only persist if a new line was actually added. Else, quantity was just updated.
        $line = $basket->addItem($basketItem);
        if ($line->getId() === null) {
            $entityManager->persist($line);
        }
        $entityManager->flush();

        return $this->json($basket->toArray());
    }
}
