#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Crons\Jobs\ProcessMessageQueueJob;
use App\Models\ContatoTelefone;
use App\Models\MatrizFilial;
use App\Services\NotificationChannelPolicyService;

function assertNotificationPolicy(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$matrizFilial = new class extends MatrizFilial {
    public function __construct()
    {
    }

    public function buscarConfiguracoesNotificacao(int $id, ?string $chave = null): ?array
    {
        $empresas = [
            10 => [
                'id' => 10,
                'nome_fantasia' => 'Matriz Bloqueada',
                'notificacao_email' => 'N',
                'notificacao_sms' => 'N',
                'notificacao_whatsapp' => 'N',
            ],
            11 => [
                'id' => 11,
                'nome_fantasia' => 'Filial Ativa',
                'notificacao_email' => 'S',
                'notificacao_sms' => 'S',
                'notificacao_whatsapp' => 'S',
            ],
        ];

        return $empresas[$id] ?? null;
    }

    public function buscarConfiguracoesNotificacaoMatriz(string $chave): ?array
    {
        return $this->buscarConfiguracoesNotificacao(10, $chave);
    }
};

$policy = new NotificationChannelPolicyService($matrizFilial);

foreach (['email', 'sms', 'whatsapp'] as $channel) {
    $blocked = $policy->evaluate($channel, ['id_matriz_filial' => 10], '1111111111111');
    assertNotificationPolicy(!$blocked['allowed'], "Canal {$channel} deveria estar bloqueado na matriz 10.");

    $allowed = $policy->evaluate($channel, ['id_matriz_filial' => 11], '1111111111111');
    assertNotificationPolicy($allowed['allowed'], "Canal {$channel} deveria estar ativo na filial 11.");
}

$missing = $policy->evaluate('email', [], '1111111111111');
assertNotificationPolicy(!$missing['allowed'], 'Publicacao nova sem empresa/filial deve falhar fechada.');

$legacy = $policy->evaluate('email', [], '1111111111111', true);
assertNotificationPolicy(
    !$legacy['allowed'] && $legacy['id_matriz_filial'] === 10,
    'Mensagem legada deve usar somente a matriz do proprio tenant.'
);

$platform = $policy->evaluate('email', ['_company_channel_bypass' => 'platform'], null);
assertNotificationPolicy($platform['allowed'], 'Mensagem global da plataforma deveria usar bypass explicito.');

$passwordReset = $policy->evaluate('email', [
    '_email_preference_bypass' => 'cliente_password_reset',
], '1111111111111');
assertNotificationPolicy($passwordReset['allowed'], 'Recuperacao de senha deveria permanecer permitida.');

$telefoneModel = new class extends ContatoTelefone {
    public function __construct()
    {
    }

    public function listarParaEnvio(string $tipo, int $id, string $canal, ?string $chave = null): array
    {
        return $canal === 'whatsapp'
            ? [
                ['id' => 1, 'telefone' => '+55 (11) 99999-0001', 'descricao' => null, 'principal' => 'S'],
                ['id' => 2, 'telefone' => '+55 (11) 99999-0002', 'descricao' => null, 'principal' => 'N'],
            ]
            : [];
    }
};

assertNotificationPolicy(
    $telefoneModel->podeEnviarPara('cliente', 20, '5511999990002', 'whatsapp', '1111111111111'),
    'Comparacao de telefone deve ignorar mascara.'
);
assertNotificationPolicy(
    !$telefoneModel->podeEnviarPara('cliente', 20, '5511999990003', 'whatsapp', '1111111111111'),
    'Telefone nao autorizado nao pode ser enviado.'
);
assertNotificationPolicy(
    !$telefoneModel->podeEnviarPara('cliente', 20, '5511999990001', 'sms', '1111111111111'),
    'Autorizacao de WhatsApp nao pode liberar SMS.'
);

$workerPolicy = new class extends NotificationChannelPolicyService {
    public function __construct()
    {
    }

    public function evaluate(
        string $channel,
        array $payload,
        ?string $chave = null,
        bool $allowLegacyMatrixFallback = false
    ): array {
        return [
            'allowed' => false,
            'message' => 'Bloqueado no consumo',
            'id_matriz_filial' => 10,
        ];
    }
};

$worker = new ProcessMessageQueueJob($workerPolicy);
$processMessage = new ReflectionMethod($worker, 'processMessage');
$processMessage->setAccessible(true);
$workerResult = $processMessage->invoke($worker, 'email', [
    'to' => 'nao-enviar@example.com',
    'id_matriz_filial' => 10,
], '1111111111111');

assertNotificationPolicy(
    ($workerResult['skipped'] ?? false) === true
        && ($workerResult['message'] ?? '') === 'Bloqueado no consumo',
    'Worker deve marcar como skipped antes de chamar o provedor.'
);

echo "OK: bloqueio mestre, isolamento por filial, excecoes e revalidacao validados.\n";
