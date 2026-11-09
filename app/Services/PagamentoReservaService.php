<?php

namespace App\Services;

/** Confirmação de reserva compartilhada pelos pagamentos, preservando os hooks existentes. */
class PagamentoReservaService
{
    public function efetivar(int $idLocacao): void
    {
        try {
            $locacaoModel = new \App\Models\Locacao();

            // Busca locacao com sessao ja configurada (marcarFinanceiroPago definiu $_SESSION['chave'])
            $locacao = $locacaoModel->buscarPorId($idLocacao);
            if (!$locacao) {
                error_log("[Webhook/Reserva] Locacao {$idLocacao} nao encontrada");
                return;
            }

            // So efetiva se ainda estava pendente (P). Evita reenviar email se ja estava R.
            if ($locacao['status'] === 'R') {
                return;
            }

            $locacaoModel->atualizar($idLocacao, ['status' => 'R']);

            $chave = $locacao['chave'];

            // Monta contexto do template (apenas nome principal; MessageTemplateService enriquece empresa.*)
            $nomeCliente = $locacao['cliente_nome'] ?? '';
            $primeiroNome = explode(' ', trim($nomeCliente))[0] ?? '';

            $emailCliente = '';
            $telCliente = '';
            if (!empty($locacao['id_cliente'])) {
                try {
                    $em = (new \App\Models\ContatoEmail())->getPrincipal('cliente', (int) $locacao['id_cliente']);
                    $emailCliente = $em['email'] ?? '';
                    $tel = (new \App\Models\ContatoTelefone())->getPrincipal('cliente', (int) $locacao['id_cliente']);
                    $telCliente = $tel['telefone'] ?? '';
                } catch (\Throwable $e) { /* ignore */ }
            }

            $context = [
                'cliente' => [
                    'id'            => (int) ($locacao['id_cliente'] ?? 0),
                    'nome'          => $nomeCliente,
                    'primeiro_nome' => $primeiroNome,
                    'email'         => $emailCliente,
                    'telefone'      => $telCliente,
                    'celular'       => $telCliente,
                ],
                'empresa' => [
                    'id' => (int) ($locacao['id_matriz_filial_retirada'] ?? 0),
                ],
                'id_matriz_filial' => (int) ($locacao['id_matriz_filial_retirada'] ?? 0),
                'locacao' => [
                    'numero'          => $locacao['codigo'] ?? '',
                    'data_retirada'   => !empty($locacao['data_saida']) ? format_date($locacao['data_saida']) : '',
                    'hora_retirada'   => !empty($locacao['data_saida']) ? \App\Helpers\DateHelper::formatOperationalDateTime($locacao['data_saida'], true, 'H:i') : '',
                    'data_devolucao'  => !empty($locacao['data_prevista']) ? format_date($locacao['data_prevista']) : '',
                    'hora_devolucao'  => !empty($locacao['data_prevista']) ? \App\Helpers\DateHelper::formatOperationalDateTime($locacao['data_prevista'], true, 'H:i') : '',
                    'quantidade_dias' => (int) ($locacao['dias'] ?? 0),
                    'valor_total'     => (float) ($locacao['total_pagar'] ?? $locacao['total_fatura'] ?? 0),
                ],
                'outros' => [
                    'data_atual' => format_date(today()),
                ],
            ];

            if (function_exists('queue_template_message')) {
                foreach (['email', 'whatsapp', 'sms'] as $canal) {
                    try {
                        queue_template_message('confirmacao_reserva', $canal, $context, $chave);
                    } catch (\App\Exceptions\NotificationChannelUnavailableException|\App\Exceptions\NotificationRecipientUnavailableException) {
                        // Canal ou destinatario indisponivel: notificacao opcional ignorada.
                    } catch (\Throwable $e) {
                        error_log("[Webhook/Reserva] Erro ao enfileirar confirmacao_reserva/{$canal}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("[Webhook/Reserva] Erro ao efetivar locacao {$idLocacao}: " . $e->getMessage());
        }
    }
}
