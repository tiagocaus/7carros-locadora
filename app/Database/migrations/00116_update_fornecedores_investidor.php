<?php

/**
 * Migration 00116: Atualizar tabela fornecedores para suporte a investidores
 *
 * Alteracoes:
 * - Remove coluna 'comissao' (obsoleta, agora definida por grupo)
 * - Altera tipo de 'investidor' de VARCHAR para TINYINT
 * - Renomeia 'conta_split' para 'split_gateway_conta'
 * - Adiciona campos para split de pagamento (agnostico de gateway)
 * - Adiciona campos PIX e dados bancarios
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Converter investidor para TINYINT
        // Primeiro, atualizar valores existentes (funciona tanto para VARCHAR quanto para TINYINT)
        $this->execute("UPDATE fornecedores SET investidor = 1 WHERE investidor = 'S' OR investidor = '1'");
        $this->execute("UPDATE fornecedores SET investidor = 0 WHERE investidor IS NULL OR investidor = '' OR investidor = 'N' OR investidor = '0'");

        // Alterar tipo da coluna para TINYINT
        $this->execute("ALTER TABLE fornecedores MODIFY investidor TINYINT(1) NOT NULL DEFAULT 0");

        // 2. Remover coluna comissao se existir (agora definida por grupo)
        if ($this->columnExists('fornecedores', 'comissao')) {
            $this->execute("ALTER TABLE fornecedores DROP COLUMN comissao");
        }

        // 3. Renomear conta_split para split_gateway_conta
        if ($this->columnExists('fornecedores', 'conta_split') && !$this->columnExists('fornecedores', 'split_gateway_conta')) {
            $this->execute("ALTER TABLE fornecedores CHANGE COLUMN conta_split split_gateway_conta VARCHAR(255) DEFAULT NULL COMMENT 'ID da conta/wallet no gateway'");
        }

        // 4. Adicionar split_gateway (enum com gateways suportados)
        if (!$this->columnExists('fornecedores', 'split_gateway')) {
            $this->execute("
                ALTER TABLE fornecedores
                ADD COLUMN split_gateway ENUM('asaas', 'gerencianet', 'stripe', 'inter') DEFAULT NULL
                COMMENT 'Gateway usado para split de pagamento'
                AFTER investidor
            ");
        }

        // 5. Adicionar campos PIX
        if (!$this->columnExists('fornecedores', 'pix_chave')) {
            $this->execute("
                ALTER TABLE fornecedores
                ADD COLUMN pix_chave VARCHAR(255) DEFAULT NULL COMMENT 'Chave PIX do investidor'
                AFTER split_gateway_conta
            ");
        }

        if (!$this->columnExists('fornecedores', 'pix_tipo')) {
            $this->execute("
                ALTER TABLE fornecedores
                ADD COLUMN pix_tipo ENUM('cpf', 'cnpj', 'email', 'telefone', 'aleatoria') DEFAULT NULL
                COMMENT 'Tipo da chave PIX'
                AFTER pix_chave
            ");
        }

        // 6. Adicionar campos bancarios
        if (!$this->columnExists('fornecedores', 'banco_codigo')) {
            $this->execute("
                ALTER TABLE fornecedores
                ADD COLUMN banco_codigo VARCHAR(10) DEFAULT NULL COMMENT 'Codigo do banco'
                AFTER pix_tipo
            ");
        }

        if (!$this->columnExists('fornecedores', 'banco_agencia')) {
            $this->execute("
                ALTER TABLE fornecedores
                ADD COLUMN banco_agencia VARCHAR(10) DEFAULT NULL COMMENT 'Numero da agencia'
                AFTER banco_codigo
            ");
        }

        if (!$this->columnExists('fornecedores', 'banco_conta')) {
            $this->execute("
                ALTER TABLE fornecedores
                ADD COLUMN banco_conta VARCHAR(20) DEFAULT NULL COMMENT 'Numero da conta'
                AFTER banco_agencia
            ");
        }

        if (!$this->columnExists('fornecedores', 'banco_tipo')) {
            $this->execute("
                ALTER TABLE fornecedores
                ADD COLUMN banco_tipo ENUM('corrente', 'poupanca') DEFAULT NULL
                COMMENT 'Tipo da conta bancaria'
                AFTER banco_conta
            ");
        }

        // 7. Adicionar indice para investidores (ignorar erro se ja existir)
        try {
            $this->execute("
                CREATE INDEX idx_fornecedores_investidor
                ON fornecedores (chave, investidor)
            ");
        } catch (\Exception $e) {
            // Indice ja existe, ignorar
        }
    }

    public function down(): void
    {
        // Remover indice
        try {
            $this->execute("DROP INDEX idx_fornecedores_investidor ON fornecedores");
        } catch (\Exception $e) {
            // Indice nao existe, ignorar
        }

        // Remover campos bancarios
        if ($this->columnExists('fornecedores', 'banco_tipo')) {
            $this->execute("ALTER TABLE fornecedores DROP COLUMN banco_tipo");
        }
        if ($this->columnExists('fornecedores', 'banco_conta')) {
            $this->execute("ALTER TABLE fornecedores DROP COLUMN banco_conta");
        }
        if ($this->columnExists('fornecedores', 'banco_agencia')) {
            $this->execute("ALTER TABLE fornecedores DROP COLUMN banco_agencia");
        }
        if ($this->columnExists('fornecedores', 'banco_codigo')) {
            $this->execute("ALTER TABLE fornecedores DROP COLUMN banco_codigo");
        }

        // Remover campos PIX
        if ($this->columnExists('fornecedores', 'pix_tipo')) {
            $this->execute("ALTER TABLE fornecedores DROP COLUMN pix_tipo");
        }
        if ($this->columnExists('fornecedores', 'pix_chave')) {
            $this->execute("ALTER TABLE fornecedores DROP COLUMN pix_chave");
        }

        // Remover split_gateway
        if ($this->columnExists('fornecedores', 'split_gateway')) {
            $this->execute("ALTER TABLE fornecedores DROP COLUMN split_gateway");
        }

        // Reverter split_gateway_conta para conta_split
        if ($this->columnExists('fornecedores', 'split_gateway_conta')) {
            $this->execute("ALTER TABLE fornecedores CHANGE COLUMN split_gateway_conta conta_split VARCHAR(255) DEFAULT NULL");
        }

        // Restaurar coluna comissao
        if (!$this->columnExists('fornecedores', 'comissao')) {
            $this->execute("ALTER TABLE fornecedores ADD COLUMN comissao VARCHAR(6) DEFAULT NULL AFTER investidor");
        }

        // Reverter investidor para VARCHAR
        $this->execute("ALTER TABLE fornecedores MODIFY investidor VARCHAR(1) DEFAULT NULL");
    }
};
