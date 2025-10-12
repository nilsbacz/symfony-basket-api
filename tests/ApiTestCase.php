<?php

namespace App\Tests;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        // Make sure a previous test's kernel is shut down
        self::ensureKernelShutdown();

        $this->client = static::createClient();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Fresh DB data per test
        $this->resetData();
        $this->seedProducts();
    }

    protected function resetData(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['basket_item', 'basket', 'product'] as $table) {
            $conn->executeStatement("TRUNCATE TABLE `$table`");
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function seedProducts(): void
    {
        $make = function(string $name, int $price, int $qty, bool $active = true): Product {
            $p = new Product();
            $p->setName($name);
            $p->setPrice($price);
            $p->setQuantity($qty);
            $p->setActive($active);
            return $p;
        };

        $this->em->persist($make('Wireless Mouse',      2999, 50, true));
        $this->em->persist($make('Mechanical Keyboard', 7999, 20, true));
        $this->em->persist($make('USB-C Cable',          999,100, true));
        $this->em->persist($make('Webcam HD',           4999, 15, true));
        $this->em->persist($make('Deprecated Monitor', 19999, 10, false));

        $this->em->flush();
        $this->em->clear();
    }

    protected function jsonRequest(string $method, string $uri, array $data = []): \Symfony\Component\HttpFoundation\Response
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $data ? json_encode($data) : null
        );

        return $this->client->getResponse();
    }
}
