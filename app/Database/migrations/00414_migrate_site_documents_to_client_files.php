<?php

use App\Database\Migration;

/**
 * Migra documentos enviados em reservas do site para a aba de arquivos do cliente.
 *
 * locacoes_documentos foi criada com um vinculo incorreto: esses arquivos pertencem
 * ao cadastro do cliente e devem ser consultados em clientes_arquivos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('locacoes_documentos')) {
            return;
        }

        $semCliente = $this->db()
            ->table('locacoes_documentos', 'ld')
            ->withoutChave()
            ->innerJoin('locacoes', 'l', 'l.id', '=', 'ld.id_locacao')
            ->whereRaw('l.chave = ld.chave')
            ->whereNested(static function ($query): void {
                $query->whereNull('l.id_cliente')
                    ->orWhere('l.id_cliente', '=', 0);
            })
            ->count();

        if ($semCliente > 0) {
            throw new RuntimeException(
                "Existem {$semCliente} documento(s) de reserva sem cliente; a migration foi cancelada."
            );
        }

        $arquivoLongo = $this->db()
            ->table('locacoes_documentos')
            ->withoutChave()
            ->whereRaw('CHAR_LENGTH(arquivo) > 100')
            ->exists();

        if ($arquivoLongo) {
            throw new RuntimeException(
                'Existem documentos cujo nome excede o limite de clientes_arquivos.arquivo.'
            );
        }

        $this->execute("
            INSERT INTO clientes_arquivos
                (chave, id_cliente, nome, arquivo, tipo, status, created_at)
            SELECT
                ld.chave,
                l.id_cliente,
                CONCAT(
                    CASE ld.tipo
                        WHEN 'cnh' THEN 'CNH'
                        WHEN 'cpf' THEN 'CPF'
                        WHEN 'rg' THEN 'RG_Passaporte'
                        WHEN 'comprovante' THEN 'Comprovante_Endereco'
                    END,
                    '_site_',
                    DATE_FORMAT(ld.created_at, '%Y%m%d_%H%i%s'),
                    CASE
                        WHEN LOCATE('.', ld.arquivo) > 0
                            THEN CONCAT('.', LOWER(SUBSTRING_INDEX(ld.arquivo, '.', -1)))
                        ELSE ''
                    END
                ),
                ld.arquivo,
                CASE ld.tipo
                    WHEN 'cnh' THEN 1
                    WHEN 'cpf' THEN 2
                    WHEN 'rg' THEN 3
                    WHEN 'comprovante' THEN 4
                END,
                NULL,
                ld.created_at
            FROM locacoes_documentos ld
            INNER JOIN locacoes l
                ON l.id = ld.id_locacao
               AND l.chave = ld.chave
            WHERE NOT EXISTS (
                SELECT 1
                FROM clientes_arquivos ca
                WHERE ca.chave = ld.chave
                  AND ca.id_cliente = l.id_cliente
                  AND ca.arquivo = ld.arquivo
            )
        ");

        $this->drop('locacoes_documentos');
    }

    public function down(): void
    {
        if ($this->tableExists('locacoes_documentos')) {
            return;
        }

        $this->execute("
            CREATE TABLE locacoes_documentos (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chave       VARCHAR(45) NOT NULL,
                id_locacao  INT UNSIGNED NOT NULL,
                tipo        ENUM('cnh','cpf','rg','comprovante') NOT NULL,
                arquivo     VARCHAR(255) NOT NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_locacao_tipo (id_locacao, tipo),
                INDEX idx_chave (chave),
                INDEX idx_locacao (id_locacao),
                CONSTRAINT fk_ld_locacao
                    FOREIGN KEY (id_locacao) REFERENCES locacoes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
};
