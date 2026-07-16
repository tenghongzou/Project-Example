<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716041500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification 模組：建立 notification schema 與 notifications 資料表';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA notification');
        $this->addSql('CREATE TABLE notification.notifications (id VARCHAR(36) NOT NULL, channel VARCHAR(50) NOT NULL, subject VARCHAR(200) NOT NULL, body TEXT NOT NULL, context JSON NOT NULL, status VARCHAR(20) NOT NULL, error TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, version INT DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification.notifications');
        $this->addSql('DROP SCHEMA notification');
    }
}
