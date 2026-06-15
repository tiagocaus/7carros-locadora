<?php

namespace App\Services;

use App\Models\Multa;
use App\Models\SerproIndicacao;

/**
 * Sincroniza o status de indicacoes de condutor com o sistema de consultas online.
 */
class SerproIndicacaoStatusService
{
    public function sincronizar(array $indicacao): array
    {
        if (empty($indicacao['id'])) {
            return [
                'success' => false,
                'message' => 'Indicacao invalida',
            ];
        }

        if (empty($indicacao['chave_indicacao'])) {
            return [
                'success' => false,
                'message' => 'Indicacao sem identificador da consulta online',
            ];
        }

        $serpro = new SerproService();
        $resultado = $indicacao['tipo'] === 'real_infrator'
            ? $serpro->statusRealInfrator($indicacao['chave_indicacao'])
            : $serpro->statusPrincipalCondutor($indicacao['chave_indicacao']);

        if (!$resultado['success']) {
            return [
                'success' => false,
                'message' => $resultado['error'] ?? 'Erro ao consultar status no sistema de consultas online',
                'data' => $resultado['data'] ?? null,
            ];
        }

        $dadosApi = is_array($resultado['data'] ?? null) ? $resultado['data'] : [];
        $statusSerpro = $dadosApi['status'] ?? $dadosApi['situacao'] ?? null;
        $dadosUpdate = [];

        if ($statusSerpro) {
            $dadosUpdate['status_serpro'] = self::normalizarStatusSerpro((string) $statusSerpro);
        }

        if (!empty($dadosApi['motivoRejeicao'])) {
            $dadosUpdate['motivo_rejeicao'] = $dadosApi['motivoRejeicao'];
        }

        if (!empty($dadosApi['dataResposta'])) {
            $dadosUpdate['data_resposta'] = $dadosApi['dataResposta'];
        }

        if (!empty($dadosUpdate)) {
            $indicacaoModel = new SerproIndicacao();
            $indicacaoModel->atualizarStatus((int) $indicacao['id'], $dadosUpdate);

            if (($indicacao['tipo'] ?? '') === 'real_infrator' && !empty($indicacao['id_multa'])) {
                $statusMulta = self::mapStatusParaMulta($dadosUpdate['status_serpro'] ?? '');
                if ($statusMulta) {
                    $multaModel = new Multa();
                    $multaModel->atualizarDadosSerpro((int) $indicacao['id_multa'], [
                        'status_processamento' => $statusMulta,
                    ]);
                }
            }
        }

        return [
            'success' => true,
            'data' => [
                'status_serpro' => $dadosApi,
                'status_local' => $dadosUpdate['status_serpro'] ?? ($indicacao['status_serpro'] ?? null),
            ],
        ];
    }

    public static function normalizarStatusSerpro(string $status): string
    {
        $status = strtolower(trim($status));

        $mapa = [
            'aceita' => 'aceito',
            'aceito' => 'aceito',
            'aprovada' => 'aceito',
            'rejeitada' => 'rejeitado',
            'rejeitado' => 'rejeitado',
            'negada' => 'rejeitado',
            'pendente' => 'pendente',
            'processando' => 'processando',
            'em_processamento' => 'processando',
            'enviada' => 'enviado',
            'enviado' => 'enviado',
            'cancelada' => 'cancelado',
            'cancelado' => 'cancelado',
            'excluida' => 'excluido',
            'excluido' => 'excluido',
            'expirada' => 'expirado',
            'expirado' => 'expirado',
        ];

        return $mapa[$status] ?? $status;
    }

    public static function mapStatusParaMulta(string $statusIndicacao): ?string
    {
        $mapa = [
            'aceito' => 'indicacao_aceita',
            'rejeitado' => 'indicacao_rejeitada',
            'enviado' => 'indicacao_enviada',
            'pendente' => 'indicacao_enviada',
            'processando' => 'indicacao_enviada',
            'cancelado' => 'novo',
        ];

        return $mapa[$statusIndicacao] ?? null;
    }
}
