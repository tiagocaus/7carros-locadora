<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Funcionario;
use App\Services\WebsiteReservationNotificationService;

if (!function_exists('currency_format')) {
    function currency_format(float|int|string|null $value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}

function assertWebsiteReservationNotification(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$funcionarioModel = new class extends Funcionario {
    public function __construct()
    {
    }

    public function listarAtivosComPermissaoNaFilial(string $permission, int $filialId): array
    {
        assertWebsiteReservationNotification(
            $permission === WebsiteReservationNotificationService::PERMISSION,
            'Permissao incorreta ao buscar destinatarios.'
        );
        assertWebsiteReservationNotification($filialId === 14, 'Filial incorreta ao buscar destinatarios.');

        return [
            ['id' => 1, 'nome' => 'Gerente Um', 'email' => 'gerente@example.com', 'id_matriz_filial' => 14],
            ['id' => 2, 'nome' => 'Gerente Duplicado', 'email' => 'GERENTE@example.com', 'id_matriz_filial' => 14],
            ['id' => 3, 'nome' => 'Proprietario', 'email' => 'proprietario@example.com', 'id_matriz_filial' => 14],
        ];
    }
};

$mensagens = [];
$publisher = static function (string $type, array $payload, string $chave) use (&$mensagens): int {
    $mensagens[] = compact('type', 'payload', 'chave');
    return count($mensagens);
};

$service = new WebsiteReservationNotificationService($funcionarioModel, $publisher);
$resultado = $service->notificarNovaReserva('1111111111111', 14, [
    'codigo' => 'R12345678',
    'cliente' => 'Cliente <Teste>',
    'cliente_email' => 'cliente@example.com',
    'cliente_telefone' => '5511999999999',
    'retirada' => '20/07/2026 10:00',
    'devolucao' => '22/07/2026 10:00',
    'local_retirada' => 'SP - Campinas',
    'valor_total' => 1250.50,
    'situacao' => 'Aguardando confirmacao da locadora',
]);

assertWebsiteReservationNotification($resultado === [
    'destinatarios' => 2,
    'enfileiradas' => 2,
    'falhas' => 0,
], 'Resumo do envio incorreto.');
assertWebsiteReservationNotification(count($mensagens) === 2, 'E-mails duplicados nao foram eliminados.');
assertWebsiteReservationNotification($mensagens[0]['type'] === 'email', 'Canal interno deve ser email.');
assertWebsiteReservationNotification($mensagens[0]['chave'] === '1111111111111', 'Tenant incorreto no envio.');
assertWebsiteReservationNotification($mensagens[0]['payload']['id_matriz_filial'] === 14, 'SMTP deve usar a filial da retirada.');
assertWebsiteReservationNotification(
    $mensagens[0]['payload']['subject'] === 'Novo pedido de reserva #R12345678',
    'Assunto do email incorreto.'
);
assertWebsiteReservationNotification(
    str_contains($mensagens[0]['payload']['body'], 'Cliente &lt;Teste&gt;'),
    'Dados do cliente devem ser escapados no HTML.'
);
assertWebsiteReservationNotification(
    str_contains($mensagens[0]['payload']['body_text'], 'Aguardando confirmacao da locadora'),
    'Situacao da reserva ausente no texto.'
);

$tentativas = 0;
$publisherComFalha = static function () use (&$tentativas): int {
    $tentativas++;
    if ($tentativas === 1) {
        throw new RuntimeException('Falha simulada');
    }
    return $tentativas;
};
$serviceComFalha = new WebsiteReservationNotificationService($funcionarioModel, $publisherComFalha);
$resultadoComFalha = $serviceComFalha->notificarNovaReserva('1111111111111', 14, [
    'codigo' => 'R12345678',
]);

assertWebsiteReservationNotification($resultadoComFalha === [
    'destinatarios' => 2,
    'enfileiradas' => 1,
    'falhas' => 1,
], 'Falha de um destinatario nao pode impedir os demais envios.');

$publisherIndisponivel = static function (): int {
    throw new \App\Exceptions\NotificationRecipientUnavailableException('Email invalido');
};
$serviceIndisponivel = new WebsiteReservationNotificationService($funcionarioModel, $publisherIndisponivel);
$resultadoIndisponivel = $serviceIndisponivel->notificarNovaReserva('1111111111111', 14, [
    'codigo' => 'R12345678',
]);

assertWebsiteReservationNotification($resultadoIndisponivel === [
    'destinatarios' => 2,
    'enfileiradas' => 0,
    'falhas' => 0,
], 'Destinatario indisponivel deve ser ignorado sem virar falha tecnica.');

echo "OK: notificacao interna de reserva respeita permissao, filial e deduplicacao.\n";
