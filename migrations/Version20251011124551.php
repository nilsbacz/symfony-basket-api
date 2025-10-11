<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251011124551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO product (name, quantity, active, price) VALUES
            ('Wireless Mouse', 50, 1, 2999),
            ('Mechanical Keyboard', 20, 1, 7999),
            ('USB-C Cable', 100, 1, 999),
            ('27-inch Monitor', 0, 1, 19999),
            ('Webcam FullHD', 15, 1, 4999)
            ('Webcam HD', 15, 0, 2999)
        ");
    }

    public function down(Schema $schema): void
    {
    }
}
