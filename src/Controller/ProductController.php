<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    #[Route('api/products', name: 'api_products_get', methods: ['GET'])]
    public function getProducts(ProductRepository $products): JsonResponse
    {
        $activeProducts = $products->findActiveInStock();

        // this could also be done automatically by setting up symfony's serializer groups, but this will suffice.
        $data = array_map(static function (\App\Entity\Product $p) {
            return [
                'id'       => $p->getId(),
                'name'     => $p->getName(),
                'quantity' => $p->getQuantity(),
                'active'   => $p->isActive(),
                'price'    => $p->getPrice(),
            ];
        }, $activeProducts);

        return $this->json($data);
    }
}
