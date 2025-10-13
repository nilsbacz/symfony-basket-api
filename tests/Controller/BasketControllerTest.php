<?php

namespace App\Tests\Controller;

use App\Entity\BasketItem;
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

    public function testAddItemActiveProductExceedStockReturnsError(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(4, 99, $basketId);
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('product out of stock', $addRes->getContent());

        $this->em->clear();
        $product = $this->em->getRepository(Product::class)->find(4);
        $this->assertSame(15, $product->getQuantity());
    }

    public function testAddItemNoBasketReturnsError(): void
    {
        $addRes = $this->addItemToBasket(1, 1, 1);
        $this->assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('basket not found', $addRes->getContent());

        $this->assertNoBasketData($addRes);
    }

    public function testAddItemNoProductReturnsError(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(10, 1, $basketId);
        $this->assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('product not found', $addRes->getContent());

        $this->assertNoBasketData($addRes);
    }

    public function testAddItemProductInactiveReturnsError(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(5, 1, $basketId);
        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('product is inactive', $addRes->getContent());

        $this->assertNoBasketData($addRes);
    }

    public function testDeleteItemSuccess(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(1, 1, $basketId);

        $added = json_decode($addRes->getContent(), true);

        // Get the created line item id from response
        $this->assertNotEmpty($added['items']);
        $itemId = $added['items'][0]['id'];

        $this->jsonRequest('DELETE', "/api/baskets/$basketId/items/1");

        $this->assertResponseStatusCodeSame(204);

        //verify the basket is empty
        $getRes = $this->jsonRequest('GET', "/api/baskets/{$basketId}");
        $this->assertResponseIsSuccessful();
        $after = json_decode($getRes->getContent(), true);
        $this->assertSame([], $after['items']);
    }

    public function testDeleteItemBasketItemNotFoundReturnsError(): void
    {
        $basketId = $this->createBasket();

        $this->jsonRequest('DELETE', "/api/baskets/$basketId/items/1");

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateItemSuccess(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(1, 1, $basketId);
        $this->assertResponseIsSuccessful();

        $basket = json_decode($addRes->getContent(), true);
        $itemId = $basket['items'][0]['id'];

        $basketItemRepo = $this->em->getRepository(BasketItem::class);
        $productRepo    = $this->em->getRepository(Product::class);

        // Read current basket item & its product (AFTER initial add)
        $beforeItem    = $basketItemRepo->find($itemId);
        $this->assertSame(1, $beforeItem->getQuantity());
        $productId     = $beforeItem->getProduct()->getId();

        $this->em->clear(); // ensure fresh reads
        $productBefore = $productRepo->find($productId);
        $this->assertNotNull($productBefore);
        $qtyBefore = $productBefore->getQuantity();

        $this->jsonRequest('PATCH', "/api/baskets/$basketId/items/$itemId", [
            'quantity' => 2,
        ]);
        $this->assertResponseStatusCodeSame(204);

        // Fresh reads after PATCH
        $this->em->clear();
        $afterItem   = $basketItemRepo->find($itemId);
        $productAfter = $productRepo->find($productId);

        $this->assertSame(2, $afterItem->getQuantity());

        $delta = 2 - 1; // newQty - oldQty
        $this->assertSame(
            $qtyBefore - $delta,
            $productAfter->getQuantity(),
            'Product stock should decrease by the quantity delta'
        );
    }

    public function testUpdateItemMisingQuantityReturnsError(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(1, 1, $basketId);
        $this->assertResponseIsSuccessful();

        $basket = json_decode($addRes->getContent(), true);
        $itemId = $basket['items'][0]['id'];

        $this->jsonRequest(
            'PATCH',
            "/api/baskets/$basketId/items/$itemId",
            ["notQuantity" => 2]
        );
        $this->assertResponseStatusCodeSame(400);
    }

    public function testUpdateItemBasketNotFoundReturnsError(): void
    {
        $this->jsonRequest(
            'PATCH',
            "/api/baskets/1/items/1",
            ["quantity" => 2]
        );
        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateItemInvalidQuantityReturnsError(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(1, 1, $basketId);
        $this->assertResponseIsSuccessful();

        $basket = json_decode($addRes->getContent(), true);
        $itemId = $basket['items'][0]['id'];

        $this->jsonRequest(
            'PATCH',
            "/api/baskets/$basketId/items/$itemId",
            ["quantity" => -1]
        );

        $this->assertResponseStatusCodeSame(422);
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

    protected function assertNoBasketData(Response $response): array
    {
        $json = json_decode($response->getContent(), true);

        $this->assertArrayNotHasKey('id', $json, 'Unexpected basket id in response');
        $this->assertArrayNotHasKey('items', $json, 'Unexpected basket items in response');

        return $json;
    }
}
