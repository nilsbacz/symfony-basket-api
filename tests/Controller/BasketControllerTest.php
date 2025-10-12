<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class BasketControllerTest extends ApiTestCase
{
    public function testCreateBasket(): void
    {
        self::bootKernel();
        $res = $this->jsonRequest('POST', '/api/baskets');
        $this->assertResponseIsSuccessful();
        $json = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('createdAt', $json);
        $this->assertSame([], $json['items'] ?? []);
    }
}
