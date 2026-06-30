<?php

namespace App\Models\Security;

use App\Models\Model;

/**
 * Model para gerenciamento de Fingerprints de Requisição
 *
 * Tabela: security_request_fingerprints (sem coluna chave)
 */
class RequestFingerprint extends Model
{
    /**
     * Busca intervalos de timing para análise de anomalia
     *
     * @param string $ipAddress IP da requisição
     * @param string $endpoint Endpoint acessado
     * @param int $limit Quantidade de registros
     * @return array Lista de intervalos
     */
    public function buscarIntervalos(string $ipAddress, string $endpoint, int $limit): array
    {
        return $this->qb
            ->table('security_request_fingerprints')
            ->withoutChave()
            ->select(['interval_ms'])
            ->where('ip_address', '=', $ipAddress)
            ->where('endpoint', '=', $endpoint)
            ->whereNotNull('interval_ms')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Busca páginas acessadas recentemente
     *
     * @param string $ipAddress IP da requisição
     * @param string $endpoint Endpoint acessado
     * @param int $minutos Janela de tempo em minutos
     * @return array Lista de páginas
     */
    public function buscarPaginasRecentes(string $ipAddress, string $endpoint, int $minutos = 2): array
    {
        return $this->qb
            ->table('security_request_fingerprints')
            ->withoutChave()
            ->select(['page_number'])
            ->where('ip_address', '=', $ipAddress)
            ->where('endpoint', '=', $endpoint)
            ->whereNotNull('page_number')
            ->whereRaw('created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)', [$minutos])
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->get();
    }

    /**
     * Busca última requisição para calcular intervalo
     *
     * @param string $ipAddress IP da requisição
     * @param string $endpoint Endpoint acessado
     * @return array|null Dados da última requisição ou null
     */
    public function buscarUltimaRequisicao(string $ipAddress, string $endpoint): ?array
    {
        return $this->qb
            ->table('security_request_fingerprints')
            ->withoutChave()
            ->select(['request_time_ms'])
            ->where('ip_address', '=', $ipAddress)
            ->where('endpoint', '=', $endpoint)
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    /**
     * Registra um novo fingerprint
     *
     * @param array $dados Dados do fingerprint
     * @return int ID criado
     */
    public function registrar(array $dados): int
    {
        $dados['created_at'] = $dados['created_at'] ?? now();
        $dados['updated_at'] = $dados['updated_at'] ?? now();

        return $this->qb
            ->table('security_request_fingerprints')
            ->withoutChave()
            ->insert($dados);
    }

    /**
     * Remove registros antigos
     *
     * @param int $horas Horas para manter (padrão: 24)
     * @return int Número de registros removidos
     */
    public function limparAntigos(int $horas = 24): int
    {
        $cutoff = \App\Helpers\DateHelper::formatTimestamp(
            \App\Helpers\DateHelper::timestamp() - ($horas * 3600),
            'Y-m-d H:i:s',
            false
        );

        return $this->qb
            ->table('security_request_fingerprints')
            ->withoutChave()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
