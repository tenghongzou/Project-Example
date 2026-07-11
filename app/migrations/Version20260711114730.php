<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260711114730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FlowEngine 模組：建立 flow_engine schema、flow_definitions 與 flow_instances 資料表';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA flow_engine');
        $this->addSql('CREATE TABLE flow_engine.flow_definitions (id VARCHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, steps JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE flow_engine.flow_instances (id VARCHAR(36) NOT NULL, definition_id VARCHAR(36) NOT NULL, status VARCHAR(20) NOT NULL, current_step_index INT NOT NULL, context JSON NOT NULL, error TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, version INT DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_flow_instances_definition_id ON flow_engine.flow_instances (definition_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE flow_engine.flow_definitions');
        $this->addSql('DROP TABLE flow_engine.flow_instances');
        $this->addSql('DROP SCHEMA flow_engine');
    }
}
