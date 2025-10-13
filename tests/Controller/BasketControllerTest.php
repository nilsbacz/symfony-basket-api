<?php

namespace App\Tests\Controller;

use App\Repository\BasketRepository;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Product;

class BasketControllerTest extends ApiTestCase
{
    public function testCreateBasket(): void
    {
        $res = $this->jsonRequest('POST', '/api/baskets');

        $created = json_decode($res->getContent(), true);
        $basketId = $created['id'];

        // verify it exists in DB
        $basketRepo = static::getContainer()->get(BasketRepository::class);
        $basket = $basketRepo->find($basketId);

        $this->assertNotNull($basket);
        $this->assertSame($basketId, $basket->getId());
        $this->assertCount(0, $basket->getItems());

        $this->assertResponseStatusCodeSame(201);
        $this->assertTrue($res->headers->has('Location'), 'Location header missing');

        $json = json_decode($res->getContent(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('createdAt', $json);
        $this->assertSame([], $json['items'] ?? []);

        $location = $res->headers->get('Location');
        $resGet = $this->jsonRequest('GET', $location);

        $this->assertResponseIsSuccessful();
        $jsonGet = json_decode($resGet->getContent(), true);

        $this->assertSame($json['id'], $jsonGet['id']);
        $this->assertArrayHasKey('createdAt', $jsonGet);
        $this->assertIsArray($jsonGet['items']);
        $this->assertCount(0, $jsonGet['items']);
    }

    public function testAddItemActiveProductOk(): void
    {
        $basketId = $this->createBasket();

        // add item to the basket
        $addRes = $this->addItemToBasket(2, 3, $basketId);

        $this->assertResponseIsSuccessful();
        $json = json_decode($addRes->getContent(), true);

        $this->assertSame($basketId, $json['id']);
        $this->assertIsString($json['createdAt']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T.*Z?[+\-]\d{2}:\d{2}$/', $json['createdAt']);
        $this->assertIsArray($json['items']);
        $this->assertCount(1, $json['items']);

        $item = $json['items'][0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('product', $item);
        $this->assertArrayHasKey('quantity', $item);

        $this->assertIsArray($item['product']);
        $this->assertSame(2, $item['product']['id']);
        $this->assertSame(3, $item['quantity']);

        //Check that the product’s quantity was reduced
        $before = 20; // defined in APITestCase.php
        $after = $this->getProductQty(2);
        $this->assertSame($before - 3, $after);
    }

    public function testAddItemActiveProductExceedStockReturnsError()
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(4, 99, $basketId);
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('product out of stock', $addRes->getContent());

        $this->em->clear();
        $product = $this->em->getRepository(Product::class)->find(4);
        $this->assertSame(15, $product->getQuantity());
    }

    protected function createBasket(): int
    {
        $res = $this->jsonRequest('POST', '/api/baskets');
        return json_decode($res->getContent(), true)['id'];
    }

    protected function addItemToBasket($productId, $amount, $basketId): Response
    {
        return $this->jsonRequest(
            'POST',
            "/api/baskets/{$basketId}/items",
            ['productId' => $productId, 'amount' => $amount]
        );
    }

    protected function getProductQty(int $id): int
    {
        $this->em->clear(); // read fresh from DB
        return $this->em->getRepository(Product::class)
            ->find($id)
            ->getQuantity();
    }
}
