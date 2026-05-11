<?php

/**
 * Migration: adiciona flags de pre-cadastro e confirmacao de reserva em site_config.
 *
 *  - cadastro_simples:             passo 4 do site pede apenas CPF/CNPJ, nome, email, celular
 *  - envio_documentos:             habilita upload de CNH, CPF, RG/Passaporte e Comprovante
 *  - doc_<tipo>_obrigatorio:       se o respectivo upload eh obrigatorio (so faz sentido se envio_documentos=1)
 *  - reserva_requer_confirmacao:   reservas do site ficam status 'P' ate locadora confirmar no painel
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE site_config
                ADD COLUMN cadastro_simples             TINYINT(1) NOT NULL DEFAULT 0 AFTER pagamento_antecipado,
                ADD COLUMN envio_documentos             TINYINT(1) NOT NULL DEFAULT 0 AFTER cadastro_simples,
                ADD COLUMN doc_cnh_obrigatorio          TINYINT(1) NOT NULL DEFAULT 0 AFTER envio_documentos,
                ADD COLUMN doc_cpf_obrigatorio          TINYINT(1) NOT NULL DEFAULT 0 AFTER doc_cnh_obrigatorio,
                ADD COLUMN doc_rg_obrigatorio           TINYINT(1) NOT NULL DEFAULT 0 AFTER doc_cpf_obrigatorio,
                ADD COLUMN doc_comprovante_obrigatorio  TINYINT(1) NOT NULL DEFAULT 0 AFTER doc_rg_obrigatorio,
                ADD COLUMN reserva_requer_confirmacao   TINYINT(1) NOT NULL DEFAULT 0 AFTER doc_comprovante_obrigatorio
        ");
    }

    public function down(): void
    {
        $this->execute("
            ALTER TABLE site_config
                DROP COLUMN cadastro_simples,
                DROP COLUMN envio_documentos,
                DROP COLUMN doc_cnh_obrigatorio,
                DROP COLUMN doc_cpf_obrigatorio,
                DROP COLUMN doc_rg_obrigatorio,
                DROP COLUMN doc_comprovante_obrigatorio,
                DROP COLUMN reserva_requer_confirmacao
        ");
    }
};
