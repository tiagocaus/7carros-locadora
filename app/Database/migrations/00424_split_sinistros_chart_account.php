<?php

use AppCore\Cache;
use App\Database\Migration;

/**
 * Separa contabilmente avarias e sinistros e reclassifica o historico criado
 * pela migration 00423 apenas quando houver vinculo explicito com sinistros.
 */
return new class extends Migration
{
    private const PLANO_AVARIAS = '4.2.2.01';
    private const PLANO_SINISTROS = '4.2.2.05';

    public function up(): void
    {
        if (!$this->tableExists('planos_de_contas')) {
            return;
        }

        $this->restaurarPlanoAvarias(false);
        $idPlanoSinistros = $this->obterOuCriarPlanoSinistros();
        $idPlanoAvarias = $this->buscarPlanoGlobal(self::PLANO_AVARIAS)['id'] ?? null;

        if ($idPlanoAvarias && $this->tableExists('sinistros') && $this->tableExists('financeiro')) {
            $stmt = $this->pdo->prepare(<<<'SQL'
UPDATE financeiro f
INNER JOIN sinistros s
    ON s.id_financeiro = f.id
   AND s.chave = f.chave
SET f.id_plano_de_conta = ?
WHERE f.id_plano_de_conta = ?
SQL);
            $stmt->execute([$idPlanoSinistros, (int) $idPlanoAvarias]);
        }

        $this->atualizarPermissaoRelatorio(false);
        try { Cache::flush(); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        if (!$this->tableExists('planos_de_contas')) {
            return;
        }

        $planoAvarias = $this->buscarPlanoGlobal(self::PLANO_AVARIAS);
        $planoSinistros = $this->buscarPlanoGlobal(self::PLANO_SINISTROS);

        if ($planoAvarias && $planoSinistros && $this->tableExists('sinistros') && $this->tableExists('financeiro')) {
            $stmt = $this->pdo->prepare(<<<'SQL'
UPDATE financeiro f
INNER JOIN sinistros s
    ON s.id_financeiro = f.id
   AND s.chave = f.chave
SET f.id_plano_de_conta = ?
WHERE f.id_plano_de_conta = ?
SQL);
            $stmt->execute([(int) $planoAvarias['id'], (int) $planoSinistros['id']]);
        }

        if ($planoSinistros) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM planos_de_contas WHERE id = ? AND chave = '0' AND hierarquia = ?"
            );
            $stmt->execute([(int) $planoSinistros['id'], self::PLANO_SINISTROS]);
        }

        $this->restaurarPlanoAvarias(true);
        $this->atualizarPermissaoRelatorio(true);
        try { Cache::flush(); } catch (\Throwable $e) {}
    }

    private function obterOuCriarPlanoSinistros(): int
    {
        $existente = $this->buscarPlanoGlobal(self::PLANO_SINISTROS);
        if ($existente) {
            $descricao = json_decode((string) ($existente['descricao_i18n'] ?? ''), true);
            if (($existente['tipo'] ?? '') !== 'R' || ($descricao['pt_BR'] ?? '') !== 'Sinistros') {
                throw new \RuntimeException(
                    'A hierarquia ' . self::PLANO_SINISTROS . ' esta ocupada por outro plano de contas.'
                );
            }
            $stmt = $this->pdo->prepare(
                "UPDATE planos_de_contas SET descricao_i18n = ? WHERE id = ? AND chave = '0'"
            );
            $stmt->execute([$this->descricaoSinistros(), (int) $existente['id']]);
            return (int) $existente['id'];
        }

        $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO planos_de_contas (chave, hierarquia, descricao_i18n, tipo)
VALUES ('0', ?, ?, 'R')
SQL);
        $stmt->execute([self::PLANO_SINISTROS, $this->descricaoSinistros()]);
        return (int) $this->pdo->lastInsertId();
    }

    private function restaurarPlanoAvarias(bool $combinado): void
    {
        $descricao = $combinado ? [
            'pt_BR' => 'Avarias e Sinistros',
            'pt_PT' => 'Avarias e Sinistros',
            'en_US' => 'Damages and Claims',
            'es_ES' => 'Averías y Siniestros',
            'it_IT' => 'Danni e Sinistri',
        ] : [
            'pt_BR' => 'Avarias',
            'pt_PT' => 'Avarias',
            'en_US' => 'Damages',
            'es_ES' => 'Averías',
            'it_IT' => 'Danni',
        ];

        $stmt = $this->pdo->prepare(<<<'SQL'
UPDATE planos_de_contas
SET descricao_i18n = ?
WHERE chave = '0' AND hierarquia = ? AND tipo = 'R'
SQL);
        $stmt->execute([
            json_encode($descricao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            self::PLANO_AVARIAS,
        ]);
    }

    private function atualizarPermissaoRelatorio(bool $combinado): void
    {
        if (!$this->tableExists('permissions')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE permissions SET name = ?, description = ? WHERE `key` = ?'
        );
        $stmt->execute($combinado ? [
            'Relatorio Avarias e Sinistros',
            'Visualizar relatorio de avarias e sinistros nos veiculos',
            'relatorios.operacional.avarias_sinistros',
        ] : [
            'Relatorio Sinistros',
            'Visualizar relatorio de sinistros registrados em contratos e locacoes',
            'relatorios.operacional.avarias_sinistros',
        ]);
    }

    private function buscarPlanoGlobal(string $hierarquia): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, tipo, descricao_i18n FROM planos_de_contas WHERE chave = '0' AND hierarquia = ? LIMIT 1"
        );
        $stmt->execute([$hierarquia]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function descricaoSinistros(): string
    {
        return json_encode([
            'pt_BR' => 'Sinistros',
            'pt_PT' => 'Sinistros',
            'en_US' => 'Claims',
            'es_ES' => 'Siniestros',
            'it_IT' => 'Sinistri',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
};
