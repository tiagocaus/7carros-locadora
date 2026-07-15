<?php

namespace App\Services;

use App\Models\Funcionario;
use Closure;

/**
 * Envia avisos internos sobre reservas criadas pelo site publico.
 */
class WebsiteReservationNotificationService
{
    public const PERMISSION = 'notificacoes.novas_reservas';

    private Funcionario $funcionarioModel;
    private Closure $publisher;

    public function __construct(?Funcionario $funcionarioModel = null, ?callable $publisher = null)
    {
        $this->funcionarioModel = $funcionarioModel ?? new Funcionario();
        $this->publisher = $publisher !== null
            ? Closure::fromCallable($publisher)
            : static fn(string $type, array $payload, string $chave): int => queue_message($type, $payload, $chave);
    }

    /**
     * @return array{destinatarios:int,enfileiradas:int,falhas:int}
     */
    public function notificarNovaReserva(string $chave, int $filialId, array $reserva): array
    {
        $funcionarios = $this->funcionarioModel->listarAtivosComPermissaoNaFilial(
            self::PERMISSION,
            $filialId
        );

        $emails = [];
        $enfileiradas = 0;
        $falhas = 0;

        foreach ($funcionarios as $funcionario) {
            $email = trim((string) ($funcionario['email'] ?? ''));
            $emailNormalizado = mb_strtolower($email);
            if ($email === '' || isset($emails[$emailNormalizado])) {
                continue;
            }
            $emails[$emailNormalizado] = true;

            try {
                ($this->publisher)('email', $this->buildPayload($funcionario, $filialId, $reserva), $chave);
                $enfileiradas++;
            } catch (\Throwable $e) {
                $falhas++;
                error_log('[Site/Publico] Erro ao notificar funcionario sobre nova reserva: ' . $e->getMessage());
            }
        }

        return [
            'destinatarios' => count($emails),
            'enfileiradas' => $enfileiradas,
            'falhas' => $falhas,
        ];
    }

    private function buildPayload(array $funcionario, int $filialId, array $reserva): array
    {
        $codigo = (string) ($reserva['codigo'] ?? '');
        $cliente = (string) ($reserva['cliente'] ?? '-');
        $clienteEmail = (string) ($reserva['cliente_email'] ?? '-');
        $clienteTelefone = (string) ($reserva['cliente_telefone'] ?? '-');
        $retirada = (string) ($reserva['retirada'] ?? '-');
        $devolucao = (string) ($reserva['devolucao'] ?? '-');
        $localRetirada = (string) ($reserva['local_retirada'] ?? '-');
        $situacao = (string) ($reserva['situacao'] ?? '-');
        $valor = currency_format((float) ($reserva['valor_total'] ?? 0));

        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $body = '<h2>Novo pedido de reserva</h2>'
            . '<p>Uma nova reserva foi registrada pelo site.</p>'
            . '<table cellpadding="6" cellspacing="0" border="0">'
            . '<tr><td><strong>Codigo</strong></td><td>' . $escape($codigo) . '</td></tr>'
            . '<tr><td><strong>Cliente</strong></td><td>' . $escape($cliente) . '</td></tr>'
            . '<tr><td><strong>E-mail</strong></td><td>' . $escape($clienteEmail) . '</td></tr>'
            . '<tr><td><strong>Telefone</strong></td><td>' . $escape($clienteTelefone) . '</td></tr>'
            . '<tr><td><strong>Retirada</strong></td><td>' . $escape($retirada) . '</td></tr>'
            . '<tr><td><strong>Local</strong></td><td>' . $escape($localRetirada) . '</td></tr>'
            . '<tr><td><strong>Devolucao</strong></td><td>' . $escape($devolucao) . '</td></tr>'
            . '<tr><td><strong>Valor</strong></td><td>' . $escape($valor) . '</td></tr>'
            . '<tr><td><strong>Situacao</strong></td><td>' . $escape($situacao) . '</td></tr>'
            . '</table>'
            . '<p>Acesse o painel de Locacoes para consultar o pedido.</p>';

        $bodyText = "Novo pedido de reserva\n"
            . "Codigo: {$codigo}\n"
            . "Cliente: {$cliente}\n"
            . "E-mail: {$clienteEmail}\n"
            . "Telefone: {$clienteTelefone}\n"
            . "Retirada: {$retirada}\n"
            . "Local: {$localRetirada}\n"
            . "Devolucao: {$devolucao}\n"
            . "Valor: {$valor}\n"
            . "Situacao: {$situacao}\n";

        return [
            'to' => trim((string) $funcionario['email']),
            'to_name' => (string) ($funcionario['nome'] ?? ''),
            'subject' => "Novo pedido de reserva #{$codigo}",
            'body' => $body,
            'body_text' => $bodyText,
            'id_matriz_filial' => $filialId,
        ];
    }
}
