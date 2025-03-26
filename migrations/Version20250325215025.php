<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250325215025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invoices (id UUID NOT NULL, user_id UUID NOT NULL, payment_id UUID DEFAULT NULL, subscription_id UUID DEFAULT NULL, stripe_invoice_id VARCHAR(255) DEFAULT NULL, invoice_number VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(50) NOT NULL, invoice_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, pdf_url VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6A2F2F95A76ED395 ON invoices (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2F2F954C3A3BB ON invoices (payment_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2F2F959A1887DC ON invoices (subscription_id)');
        $this->addSql('COMMENT ON COLUMN invoices.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invoices.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invoices.payment_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invoices.subscription_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invoices.invoice_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invoices.paid_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invoices.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invoices.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE payments (id UUID NOT NULL, user_id UUID NOT NULL, stripe_id VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, description VARCHAR(255) DEFAULT NULL, payment_type VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_65D29B32A76ED395 ON payments (user_id)');
        $this->addSql('COMMENT ON COLUMN payments.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN payments.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN payments.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN payments.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE subscriptions (id UUID NOT NULL, user_id UUID NOT NULL, stripe_id VARCHAR(255) NOT NULL, stripe_plan_id VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, interval VARCHAR(50) NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, auto_renew BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4778A01A76ED395 ON subscriptions (user_id)');
        $this->addSql('COMMENT ON COLUMN subscriptions.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscriptions.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN subscriptions.start_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscriptions.end_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscriptions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscriptions.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user_jit" (id UUID NOT NULL, user_id UUID NOT NULL, jwt_id VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B24093A4A76ED395 ON "user_jit" (user_id)');
        $this->addSql('COMMENT ON COLUMN "user_jit".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "user_jit".user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "user_jit".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "users" (id UUID NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, otp VARCHAR(6) DEFAULT NULL, otp_expiration TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, provider JSON NOT NULL, reset_password_token VARCHAR(255) DEFAULT NULL, reset_password_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, last_login TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, failed_login_attempts INT NOT NULL, locked_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_sso_linked BOOLEAN NOT NULL, linked_accounts JSON NOT NULL, google_id VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON "users" (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "users" (email)');
        $this->addSql('COMMENT ON COLUMN "users".id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN "users".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F95A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F954C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F959A1887DC FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B32A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A01A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user_jit" ADD CONSTRAINT FK_B24093A4A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F95A76ED395');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F954C3A3BB');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F959A1887DC');
        $this->addSql('ALTER TABLE payments DROP CONSTRAINT FK_65D29B32A76ED395');
        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT FK_4778A01A76ED395');
        $this->addSql('ALTER TABLE "user_jit" DROP CONSTRAINT FK_B24093A4A76ED395');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP TABLE "user_jit"');
        $this->addSql('DROP TABLE "users"');
    }
}
