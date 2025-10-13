<?php

namespace App\Tests\Controller;

use App\Repository\BasketRepository;
use App\Tests\ApiTestCase;

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
}
