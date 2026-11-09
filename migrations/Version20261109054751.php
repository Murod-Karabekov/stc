<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261109054751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stc_exam_attempts ADD selection_decision VARCHAR(20) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE stc_exam_attempts ADD decision_changed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE stc_exam_attempts ADD decision_changed_by VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE stc_exams ADD published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stc_exam_attempts DROP selection_decision');
        $this->addSql('ALTER TABLE stc_exam_attempts DROP decision_changed_at');
        $this->addSql('ALTER TABLE stc_exam_attempts DROP decision_changed_by');
        $this->addSql('ALTER TABLE stc_exams DROP published_at');
    }
}
