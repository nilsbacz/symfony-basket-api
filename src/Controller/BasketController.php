<?php

namespace App\Controller;

use App\Entity\Basket;
use App\Entity\BasketItem;
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
use Symfony\Bridge\Doctrine\Attribute\MapEntity;

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

    #[Route('/api/baskets/{id}', name: 'api_baskets_get', methods: ['GET'])]
    public function getOne(Basket $basket): JsonResponse
    {
        return $this->json($basket->toArray());
    }

    #[Route('/api/baskets/{id}/items', name: 'api_baskets_items_add', methods: ['POST'])]
    public function addItem(
        int $id,
        Request $request,
        ProductRepository $products,
        BasketRepository $baskets,
        BasketItemRepository $basketItemRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
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

        if (!$productToAdd->isActive()) {
            return $this->json(['error' => 'product is inactive'], 400);
        }

        $basketItem = $basketItemRepository->createByProduct($productToAdd, $amount);

        // only persist if a new line was actually added. Else, quantity was just updated.
        try {
            $line = $basket->addItem($basketItem);
        } catch (\DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }

        if ($line->getId() === null) {
            $entityManager->persist($line);
        }
        $entityManager->flush();

        return $this->json($basket->toArray());
    }

    #[Route('/api/baskets/{id}/items/{itemId}', name: 'api_baskets_items_delete', methods: ['DELETE'])]
    public function deleteItem(
        int $id,
        #[MapEntity(mapping: ['itemId' => 'id'])] ?BasketItem $basketItem,
        EntityManagerInterface $entityManager
    ): Response {
        if (null === $basketItem || !$basketItem->getBasket() || $basketItem->getBasket()->getId() !== $id) {
            return $this->json(['error' => 'item does not belong to this basket'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($basketItem);
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/baskets/{id}/items/{itemId}', name: 'api_baskets_items_update', methods: ['PATCH'])]
    public function updateItem(
        int $id,
        Request $request,
        #[MapEntity(mapping: ['itemId' => 'id'])] ?BasketItem $basketItem,
        EntityManagerInterface $entityManager
    ): Response {
        // 404s
        if (!$basketItem) {
            return $this->json(['error' => 'item not found'], 404);
        }
        if ($basketItem->getBasket()->getId() !== $id) {
            return $this->json(['error' => 'item does not belong to this basket'], 404);
        }

        $data = $request->toArray();
        $quantity = $data['quantity'] ?? null;
        if (!is_int($quantity)) {
            return $this->json(['error' => 'quantity is required and must be an integer'], 400);
        }
        if ($quantity < 0) {
            return $this->json(['error' => 'quantity must be >= 0'], 422);
        }

        $oldQty   = $basketItem->getQuantity();
        $newQty   = $quantity;
        $delta    = $newQty - $oldQty; // + => add more to basket (consume stock), - => reduce basket (return stock)
        $product  = $basketItem->getProduct();

        // Delete line when setting to 0
        if ($newQty === 0) {
            // return stock to product
            if ($oldQty > 0) {
                $product->setQuantity($product->getQuantity() + $oldQty);
            }
            $entityManager->remove($basketItem);
            $entityManager->flush();
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        if ($delta === 0) {
            // no-op
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        if ($delta > 0) {
            // Growing the line: need to consume `delta` units from product, ensure stock
            if ($product->getQuantity() < $delta) {
                return $this->json(['error' => 'product out of stock'], 422);
            }
            $product->setQuantity($product->getQuantity() - $delta);
        } else {
            // Shrinking the line: return stock to product
            $product->setQuantity($product->getQuantity() + abs($delta));
        }

        $basketItem->setQuantity($newQty);

        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
