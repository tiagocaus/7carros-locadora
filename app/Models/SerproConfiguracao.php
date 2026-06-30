<?php

namespace App\Models;

/**
 * Model SerproConfiguracao
 *
 * Gerencia configuracoes da integracao de consultas online por tenant.
 * Cada tenant tem seu CNPJ, configuracoes de auto-consulta e auto-eventos.
 */
class SerproConfiguracao extends Model
{
    /**
     * Busca configuracao do tenant atual
     */
    public function buscarPorChave(): ?array
    {
        return $this->qb
            ->table('serpro_configuracoes')
            ->first();
    }

    /**
     * Cria ou atualiza configuracao do tenant
     */
    public function salvar(array $dados): int
    {
        $existente = $this->buscarPorChave();

        if ($existente) {
            $dadosUpdate = [];

            if (array_key_exists('cnpj_empresa', $dados)) {
                $dadosUpdate['cnpj_empresa'] = preg_replace('/\D/', '', $dados['cnpj_empresa']);
            }
            if (array_key_exists('auto_consulta_ativo', $dados)) {
                $dadosUpdate['auto_consulta_ativo'] = (int) $dados['auto_consulta_ativo'];
            }
            if (array_key_exists('intervalo_dias_consulta', $dados)) {
                $dadosUpdate['intervalo_dias_consulta'] = max(1, (int) $dados['intervalo_dias_consulta']);
            }
            if (array_key_exists('auto_eventos_ativo', $dados)) {
                $dadosUpdate['auto_eventos_ativo'] = (int) $dados['auto_eventos_ativo'];
            }
            if (array_key_exists('webhook_registrado', $dados)) {
                $dadosUpdate['webhook_registrado'] = (int) $dados['webhook_registrado'];
            }
            if (array_key_exists('ultima_consulta_em', $dados)) {
                $dadosUpdate['ultima_consulta_em'] = $dados['ultima_consulta_em'];
            }

            if (empty($dadosUpdate)) {
                return 0;
            }

            $dadosUpdate['updated_at'] = now();

            return $this->qb
                ->table('serpro_configuracoes')
                ->where('id', '=', $existente['id'])
                ->update($dadosUpdate);
        }

        return $this->qb
            ->table('serpro_configuracoes')
            ->insert([
                'chave' => $_SESSION['chave'],
                'cnpj_empresa' => preg_replace('/\D/', '', $dados['cnpj_empresa'] ?? ''),
                'auto_consulta_ativo' => (int) ($dados['auto_consulta_ativo'] ?? 0),
                'intervalo_dias_consulta' => max(1, (int) ($dados['intervalo_dias_consulta'] ?? 7)),
                'auto_eventos_ativo' => (int) ($dados['auto_eventos_ativo'] ?? 0),
                'webhook_registrado' => 0,
                'created_at' => now(),
            ]);
    }

    /**
     * Atualiza data da ultima consulta automatica
     */
    public function atualizarUltimaConsulta(): int
    {
        return $this->qb
            ->table('serpro_configuracoes')
            ->update([
                'ultima_consulta_em' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Marca webhook como registrado/desregistrado
     */
    public function atualizarWebhookStatus(bool $registrado): int
    {
        return $this->qb
            ->table('serpro_configuracoes')
            ->update([
                'webhook_registrado' => $registrado ? 1 : 0,
                'updated_at' => now(),
            ]);
    }

    /**
     * Lista todos os tenants com auto-consulta ativa (para CRON)
     * Usa withoutChave() pois CRON opera cross-tenant
     */
    public function listarAutoConsultaAtivos(): array
    {
        return $this->qb
            ->table('serpro_configuracoes')
            ->withoutChave()
            ->where('auto_consulta_ativo', '=', 1)
            ->get();
    }

    /**
     * Busca configuracao de um tenant especifico (para CRON)
     * Usa withoutChave() pois CRON opera cross-tenant
     */
    public function buscarPorChaveEspecifica(string $chave): ?array
    {
        return $this->qb
            ->table('serpro_configuracoes')
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->first();
    }
}
