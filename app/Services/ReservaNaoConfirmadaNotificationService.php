<?php
namespace App\Services;

use App\Exceptions\{NotificationChannelUnavailableException, NotificationRecipientUnavailableException};
use App\Models\{Cliente, MatrizFilial};

/** Notificacao opcional, enviada somente depois do commit da exclusao. */
class ReservaNaoConfirmadaNotificationService
{
    public function contexto(array $locacao): array
    {
        $cliente = (new Cliente())->buscarPorIdComContatos((int) ($locacao['id_cliente'] ?? 0)) ?? [];
        $filiais = new MatrizFilial();
        $empresa = $filiais->buscarDadosEmpresaPorChave($locacao['chave']) ?? [];
        $retirada = $filiais->buscarPorId((int) ($locacao['id_matriz_filial_retirada'] ?? 0)) ?? [];
        $devolucao = $filiais->buscarPorId((int) ($locacao['id_matriz_filial_devolucao'] ?? 0)) ?? [];
        $nome = $cliente['nome_rsocial'] ?? $locacao['cliente_nome'];
        return [
            'cliente' => [
                'id' => (int) ($cliente['id'] ?? 0),
                'nome' => $nome,
                'primeiro_nome' => explode(' ', trim($nome))[0],
                'preferred_locale' => ($cliente['preferred_locale'] ?? null) ?: ($empresa['locale'] ?? 'pt_BR'),
            ] + $cliente,
            'empresa' => $empresa,
            'id_matriz_filial' => (int) ($locacao['id_matriz_filial_retirada'] ?? 0),
            'locacao' => [
                'numero' => $locacao['codigo'],
                'data_retirada' => substr($locacao['data_saida'], 0, 10),
                'data_devolucao' => substr($locacao['data_prevista'], 0, 10),
                'hora_retirada' => substr($locacao['data_saida'], 11, 5),
                'hora_devolucao' => substr($locacao['data_prevista'], 11, 5),
                'local_retirada' => $retirada['nome_fantasia'] ?? '',
                'local_devolucao' => $devolucao['nome_fantasia'] ?? '',
                'quantidade_dias' => $locacao['dias'],
                'valor_total' => $locacao['total_pagar'],
            ],
        ];
    }

    protected function enfileirar(string $canal, array $contexto, string $chave): int
    {
        return queue_template_message('reserva_nao_confirmada', $canal, $contexto, $chave);
    }

    /** Resultados representam enfileiramento, nunca confirmacao de entrega. */
    public function notificar(array $contexto, string $chave): array
    {
        $canais = [];
        foreach (['email', 'whatsapp', 'sms'] as $canal) {
            try {
                if (empty($contexto['cliente']['id'])) {
                    $canais[$canal] = 'unavailable';
                    continue;
                }
                $canais[$canal] = $this->enfileirar($canal, $contexto, $chave) > 0 ? 'queued' : 'unavailable';
            } catch (NotificationChannelUnavailableException | NotificationRecipientUnavailableException $e) {
                $canais[$canal] = 'unavailable';
            } catch (\Throwable $e) {
                $canais[$canal] = 'failed';
                error_log('[ReservaNaoConfirmada] ' . json_encode([
                    'chave' => $chave, 'codigo' => $contexto['locacao']['numero'],
                    'canal' => $canal, 'erro' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE));
            }
        }
        $enfileirados = count(array_filter($canais, fn($status) => $status === 'queued'));
        return ['status' => $enfileirados === 3 ? 'queued' : ($enfileirados > 0 ? 'partial' : 'unavailable'), 'canais' => $canais];
    }
}
