<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your need!
 */
class Version20131105203441 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is autogenerated, please modify it to your needs
        $this->addSql("ALTER TABLE  `course_material` ADD  `fileId` INT UNSIGNED NOT NULL AFTER  `description` ;");
    }

    public function down(Schema $schema)
    {
        // this down() migration is autogenerated, please modify it to your needs

    }
}
