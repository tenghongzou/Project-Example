<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260711050111 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'EventManage 模組：建立 event_manage schema 與 events 資料表';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA event_manage');
        $this->addSql('CREATE TABLE event_manage.events (id VARCHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, description TEXT DEFAULT NULL, scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, version INT DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_manage.events');
        $this->addSql('DROP SCHEMA event_manage');
    }
}
