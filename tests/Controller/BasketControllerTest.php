<?php

namespace App\Tests\Controller;

use App\Entity\BasketItem;
use App\Entity\Product;
use App\Repository\BasketRepository;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class BasketControllerTest extends ApiTestCase
{
    public function testCreateBasket(): void
    {
        $res = $this->jsonRequest('POST', '/api/baskets');

        $created = $this->decode($res);
        $basketId = $created['id'];

        // verify it exists in DB
        $basketRepo = static::getContainer()->get(BasketRepository::class);
        $basket = $basketRepo->find($basketId);

        $this->assertNotNull($basket);
        $this->assertSame($basketId, $basket->getId());
        $this->assertCount(0, $basket->getItems());

        $this->assertResponseStatusCodeSame(201);
        $this->assertTrue($res->headers->has('Location'));

        $json = $this->decode($res);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('createdAt', $json);
        $this->assertSame([], $json['items'] ?? []);

        $location = $res->headers->get('Location');
        $resGet = $this->jsonRequest('GET', $location);

        $this->assertResponseIsSuccessful();
        $jsonGet = $this->decode($resGet);

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
        $json = $this->decode($addRes);

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
        $before = 20;
        $after = $this->getProductQty(2);
        $this->assertSame($before - 3, $after);
    }

    public function testAddItemActiveProductExceedStockReturnsError(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(4, 99, $basketId);
        $this->assertSame(422, $addRes->getStatusCode());
        $this->assertStringContainsString('product out of stock', $addRes->getContent());

        $this->em->clear();
        $product = $this->em->getRepository(Product::class)->find(4);
        $this->assertSame(15, $product->getQuantity());
    }

    public function testAddItemNoBasketReturnsError(): void
    {
        $addRes = $this->addItemToBasket(1, 1, 1);
        $this->assertSame(404, $addRes->getStatusCode());
        $this->assertStringContainsString('basket not found', $addRes->getContent());

        $this->assertNoBasketData($addRes);
    }

    public function testAddItemNoProductReturnsError(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(10, 1, $basketId);
        $this->assertSame(404, $addRes->getStatusCode());
        $this->assertStringContainsString('product not found', $addRes->getContent());

        $this->assertNoBasketData($addRes);
    }

    public function testAddItemProductInactiveReturnsError(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(5, 1, $basketId);
        $this->assertSame(400, $addRes->getStatusCode());
        $this->assertStringContainsString('product is inactive', $addRes->getContent());

        $this->assertNoBasketData($addRes);
    }

    public function testDeleteItemSuccess(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(1, 1, $basketId);

        $added = $this->decode($addRes);

        // Get the created line item id from response
        $this->assertNotEmpty($added['items']);
        $itemId = $added['items'][0]['id'];

        $this->jsonRequest('DELETE', "/api/baskets/$basketId/items/$itemId");

        $this->assertResponseStatusCodeSame(204);

        //verify the basket is empty
        $getRes = $this->jsonRequest('GET', "/api/baskets/{$basketId}");
        $this->assertResponseIsSuccessful();
        $after = $this->decode($getRes);
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

        $basket = $this->decode($addRes);
        $itemId = $this->firstItemId($basket);

        $basketItemRepo = $this->em->getRepository(BasketItem::class);
        $productRepo = $this->em->getRepository(Product::class);

        $beforeItem = $basketItemRepo->find($itemId);
        $this->assertSame(1, $beforeItem->getQuantity());
        $productId = $beforeItem->getProduct()->getId();

        $this->em->clear();
        $productBefore = $productRepo->find($productId);
        $this->assertNotNull($productBefore);
        $qtyBefore = $productBefore->getQuantity();

        $this->patchItem($basketId, $itemId, ['quantity' => 2]);
        $this->assertResponseStatusCodeSame(204);

        $this->em->clear();
        $afterItem = $basketItemRepo->find($itemId);
        $productAfter = $productRepo->find($productId);

        $this->assertSame(2, $afterItem->getQuantity());

        $delta = 2 - 1;
        $this->assertSame($qtyBefore - $delta, $productAfter->getQuantity());
    }

    public function testUpdateItemMisingQuantityReturnsError(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(1, 1, $basketId);
        $this->assertResponseIsSuccessful();

        $basket = $this->decode($addRes);
        $itemId = $this->firstItemId($basket);

        $this->patchItem($basketId, $itemId, ['notQuantity' => 2]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testUpdateItemBasketNotFoundReturnsError(): void
    {
        $this->patchItem(1, 1, ['quantity' => 2]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateItemInvalidQuantityReturnsError(): void
    {
        $basketId = $this->createBasket();

        $addRes = $this->addItemToBasket(1, 1, $basketId);
        $this->assertResponseIsSuccessful();

        $basket = $this->decode($addRes);
        $itemId = $this->firstItemId($basket);

        $this->patchItem($basketId, $itemId, ['quantity' => -1]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateItemZeroQuantityDeletesLineAndRestoresStock()
    {
        $basketId = $this->createBasket();
        $this->addItemToBasket(2, 3, $basketId);

        $stockBefore = $this->getProductQty(2);

        $getRes = $this->jsonRequest('GET', "/api/baskets/{$basketId}");
        $lineId = $this->decode($getRes)['items'][0]['id'];

        $this->jsonRequest('PATCH', "/api/baskets/{$basketId}/items/{$lineId}", ['quantity' => 0]);
        $this->assertResponseStatusCodeSame(204);

        $getRes2 = $this->jsonRequest('GET', "/api/baskets/{$basketId}");
        $this->assertSame([], json_decode($getRes2->getContent(), true)['items']);

        $productAfterQty = $this->getProductQty(2);
        $this->assertSame($stockBefore + 3, $productAfterQty);
    }

    public function testUpdateItemNotInBasketReturnsError(): void
    {
        $basketA = $this->createBasket();
        $basketB = $this->createBasket();

        $this->addItemToBasket(3, 1, $basketA);
        $resBasket = $this->jsonRequest('GET', "/api/baskets/{$basketA}");
        $lineId = $this->decode($resBasket)['items'][0]['id'];

        $res = $this->jsonRequest('PATCH', "/api/baskets/{$basketB}/items/{$lineId}", ['quantity' => 2]);
        $this->assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('does not belong', $res->getContent());
    }

    protected function createBasket(): int
    {
        $res = $this->jsonRequest('POST', '/api/baskets');
        return $this->decode($res)['id'];
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
        $this->em->clear();
        return $this->em->getRepository(Product::class)
            ->find($id)
            ->getQuantity();
    }

    protected function assertNoBasketData(Response $response): array
    {
        $json = $this->decode($response);
        $this->assertArrayNotHasKey('id', $json);
        $this->assertArrayNotHasKey('items', $json);
        return $json;
    }

    protected function decode(Response $response): array
    {
        return json_decode($response->getContent(), true);
    }

    protected function firstItemId(array $basket): int
    {
        return $basket['items'][0]['id'];
    }

    protected function patchItem(int $basketId, int $itemId, array $payload): Response
    {
        return $this->jsonRequest('PATCH', "/api/baskets/$basketId/items/$itemId", $payload);
    }
}
