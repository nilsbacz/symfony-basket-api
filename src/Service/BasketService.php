<?php

namespace App\Service;

use App\Entity\Basket;
use Doctrine\ORM\EntityManagerInterface;

final class BasketService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function create(): Basket
    {
        $basket = new Basket();
        $this->entityManager->persist($basket);
        $this->entityManager->flush();

        return $basket;
    }
}
