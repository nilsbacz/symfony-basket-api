<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

class ProductControllerTest extends ApiTestCase
{
    public function testGetProductsReturnsActiveInStockOnly(): void
    {
        $res = $this->jsonRequest('GET', '/api/products');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $json = json_decode($res->getContent(), true);
        $this->assertIsArray($json);
        $this->assertNotEmpty($json);

        // Each product should have expected structure
        $first = $json[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('quantity', $first);
        $this->assertArrayHasKey('active', $first);
        $this->assertArrayHasKey('price', $first);

        // Ensure no inactive or out-of-stock product is returned
        foreach ($json as $product) {
            $this->assertTrue($product['active']);
            $this->assertGreaterThan(0, $product['quantity']);
        }
    }
}
