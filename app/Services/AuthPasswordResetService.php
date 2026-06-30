<?php

namespace App\Services;

use App\Classes\QueryBuilder;
use App\Models\Model;
use App\Models\FuncionarioPasswordReset;

/**
 * Redefinicao de senha para funcionarios que acessam o painel.
 */
class AuthPasswordResetService
{
    private const TEMPLATE_SLUG = 'funcionario_nova_senha';

    private QueryBuilder $qb;

    public function __construct()
    {
        $this->qb = new QueryBuilder(Model::sharedMysqli());
        $this->qb->withoutChave();
    }

    public function requestReset(string $identifier, string $ipAddress): void
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return;
        }

        $funcionario = $this->buscarFuncionario($identifier);
        if (!$this->podeRedefinir($funcionario)) {
            return;
        }

        $resetModel = new FuncionarioPasswordReset();
        $tokenPlano = $resetModel->criar(
            (int) $funcionario['id'],
            (string) $funcionario['chave'],
            $ipAddress
        );

        try {
            $this->registrarLog($funcionario, $ipAddress, 'solicitada');
            $this->enfileirarEmail($funcionario, $tokenPlano);
        } catch (\Throwable $e) {
            error_log('[AuthPasswordReset] Falha ao solicitar redefinicao de senha: ' . $e->getMessage());
        }
    }

    public function resetWithToken(string $token, string $novaSenha, string $ipAddress): bool
    {
        $resetModel = new FuncionarioPasswordReset();
        $reset = $resetModel->validar($token);
        if (!$reset || strlen($novaSenha) < 8) {
            return false;
        }

        $funcionario = $this->buscarFuncionarioPorId(
            (int) $reset['id_funcionario'],
            (string) $reset['chave']
        );
        if (!$this->podeRedefinir($funcionario)) {
            return false;
        }

        $hash = password_hash($novaSenha, PASSWORD_ARGON2ID);
        $this->qb->beginTransaction();

        try {
            $this->qb
                ->table('funcionarios')
                ->withoutChave()
                ->where('id', '=', $funcionario['id'])
                ->where('chave', '=', $funcionario['chave'])
                ->update(['senha' => $hash]);

            $this->qb
                ->table('funcionarios_tokens')
                ->withoutChave()
                ->where('usuario_id', '=', $funcionario['id'])
                ->delete();

            $resetModel->marcarUsado((int) $reset['id']);
            $this->registrarLog($funcionario, $ipAddress, 'concluida');
            $this->qb->commit();
            return true;
        } catch (\Throwable $e) {
            $this->qb->rollback();
            error_log('[AuthPasswordReset] Falha ao aplicar nova senha: ' . $e->getMessage());
            return false;
        }
    }

    private function buscarFuncionario(string $identifier): ?array
    {
        return $this->qb
            ->table('funcionarios', 'f')
            ->withoutChave()
            ->select([
                'f.id',
                'f.chave',
                'f.id_matriz_filial',
                'f.nome',
                'f.email',
                'f.usuario',
                'f.status',
                'r.name AS role_name',
                'f.ui_locale',
            ])
            ->leftJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
            ->whereRaw('f.usuario = ? OR f.email = ?', [$identifier, $identifier])
            ->first();
    }

    private function buscarFuncionarioPorId(int $id, string $chave): ?array
    {
        return $this->qb
            ->table('funcionarios', 'f')
            ->withoutChave()
            ->select([
                'f.id',
                'f.chave',
                'f.id_matriz_filial',
                'f.nome',
                'f.email',
                'f.usuario',
                'f.status',
                'r.name AS role_name',
                'f.ui_locale',
            ])
            ->leftJoin('funcionarios_roles', 'r', 'f.id_role', '=', 'r.id')
            ->where('f.id', '=', $id)
            ->where('f.chave', '=', $chave)
            ->first();
    }

    private function podeRedefinir(?array $funcionario): bool
    {
        if (!$funcionario || ($funcionario['status'] ?? null) !== 'A') {
            return false;
        }

        return filter_var((string) ($funcionario['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;
    }

    private function registrarLog(array $funcionario, string $ipAddress, string $status): void
    {
        $this->qb
            ->table('logs')
            ->withoutChave()
            ->insert([
                'chave' => $funcionario['chave'],
                'id_funcionario' => (int) $funcionario['id'],
                'data' => now(),
                'ip' => $ipAddress,
                'mensagem' => '[Auth] Redefinicao de senha de funcionario ' . $status . ' via tela de login para usuario: ' . $funcionario['usuario'],
                'campos_alterados' => null,
            ]);
    }

    private function enfileirarEmail(array $funcionario, string $tokenPlano): void
    {
        $chave = (string) $funcionario['chave'];
        $baseUrl = rtrim((string) ($_ENV['APP_URL'] ?? 'https://locadora.7carros.com'), '/');
        $resetUrl = $baseUrl . '/auth/redefinir-senha?token=' . $tokenPlano;

        $templateService = new MessageTemplateService(null, $chave);
        $context = [
            'funcionario' => [
                'nome' => $funcionario['nome'] ?? '',
                'email' => $funcionario['email'] ?? '',
                'telefone' => '',
                'cargo' => $funcionario['role_name'] ?? '',
                'preferred_locale' => $funcionario['ui_locale'] ?? null,
            ],
            'outros' => [
                'data_atual' => format_date(today()),
                'hora_atual' => \App\Helpers\DateHelper::todayForDatabase('H:i'),
                'reset_url' => $resetUrl,
                'reset_expira_em' => FuncionarioPasswordReset::TTL_MINUTES . ' minutos',
            ],
            'id_matriz_filial' => $funcionario['id_matriz_filial'] ?? null,
        ];

        $rendered = $templateService->render(
            self::TEMPLATE_SLUG,
            'email',
            $context,
            $funcionario['ui_locale'] ?: null
        );

        if (!$rendered) {
            throw new \RuntimeException('Template funcionario_nova_senha nao encontrado');
        }

        if (!str_contains((string) ($rendered['content'] ?? ''), $resetUrl)) {
            $rendered['content'] .= '<p>Para definir uma nova senha, acesse: <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Redefinir senha</a></p>';
            $rendered['content_plain'] .= "\nRedefinir senha: {$resetUrl}";
        }

        $payload = [
            'to' => $funcionario['email'],
            'to_name' => $funcionario['nome'] ?? '',
            'subject' => $rendered['subject'] ?? 'Redefinicao de senha de acesso',
            'body' => $rendered['content'],
            'body_text' => $rendered['content_plain'],
            'id_matriz_filial' => $funcionario['id_matriz_filial'] ?? null,
        ];

        queue_message_service()->publish('email', $payload, $chave);
    }
}
