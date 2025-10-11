<?php

namespace App\Repository;

use App\Entity\BasketItem;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BasketItem>
 */
class BasketItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BasketItem::class);
    }

    public function createByProduct(Product $product, int $quantity): BasketItem
    {
        $basketItem = new BasketItem();

        $basketItem->setProduct($product);
        $basketItem->setQuantity($quantity);

        return $basketItem;
    }
}
