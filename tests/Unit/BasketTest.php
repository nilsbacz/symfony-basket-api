<?php

namespace App\Tests\Unit;

use App\Entity\Basket;
use App\Entity\BasketItem;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

final class BasketTest extends TestCase
{
    public function testAddItemMergesQuantityForSameProduct(): void
    {
        $product = (new Product())
            ->setName('Mouse')
            ->setPrice(2999)
            ->setQuantity(50)
            ->setActive(true);

        $basket = new Basket();

        $line1 = (new BasketItem())->setProduct($product)->setQuantity(2);
        $line2 = (new BasketItem())->setProduct($product)->setQuantity(3);

        $basket->addItem($line1);
        $basket->addItem($line2);

        $items = $basket->getItems();

        $this->assertCount(1, $items);
        $this->assertSame(5, $items[0]->getQuantity());

        $this->assertSame($basket, $items[0]->getBasket());
        $this->assertSame($product, $items[0]->getProduct());
    }
}
