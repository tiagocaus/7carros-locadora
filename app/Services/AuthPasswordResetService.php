<?php

namespace App\Services;

use App\Classes\QueryBuilder;
use App\Models\Model;

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

        $novaSenha = $this->gerarSenhaSegura();
        $hash = password_hash($novaSenha, PASSWORD_ARGON2ID);

        $this->qb->beginTransaction();

        try {
            $this->qb
                ->table('funcionarios')
                ->withoutChave()
                ->where('id', '=', $funcionario['id'])
                ->update(['senha' => $hash]);

            $this->qb
                ->table('funcionarios_tokens')
                ->withoutChave()
                ->where('usuario_id', '=', $funcionario['id'])
                ->delete();

            $this->registrarLog($funcionario, $ipAddress);
            $this->enfileirarEmail($funcionario, $novaSenha);

            $this->qb->commit();
        } catch (\Throwable $e) {
            $this->qb->rollback();
            error_log('[AuthPasswordReset] Falha ao redefinir senha: ' . $e->getMessage());
        }
    }

    private function buscarFuncionario(string $identifier): ?array
    {
        return $this->qb
            ->table('funcionarios')
            ->withoutChave()
            ->select([
                'id',
                'chave',
                'id_matriz_filial',
                'nome',
                'email',
                'usuario',
                'status',
                'funcao',
                'ui_locale',
            ])
            ->whereRaw('usuario = ? OR email = ?', [$identifier, $identifier])
            ->first();
    }

    private function podeRedefinir(?array $funcionario): bool
    {
        if (!$funcionario || ($funcionario['status'] ?? null) !== 'A') {
            return false;
        }

        return filter_var((string) ($funcionario['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;
    }

    private function gerarSenhaSegura(int $length = 16): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*+-';
        $max = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    private function registrarLog(array $funcionario, string $ipAddress): void
    {
        $this->qb
            ->table('logs')
            ->withoutChave()
            ->insert([
                'chave' => $funcionario['chave'],
                'id_funcionario' => (int) $funcionario['id'],
                'data' => date('Y-m-d H:i:s'),
                'ip' => $ipAddress,
                'mensagem' => '[Auth] Senha redefinida via tela de login para usuário: ' . $funcionario['usuario'],
                'campos_alterados' => null,
            ]);
    }

    private function enfileirarEmail(array $funcionario, string $novaSenha): void
    {
        $chave = (string) $funcionario['chave'];
        $templateService = new MessageTemplateService(null, $chave);
        $context = [
            'funcionario' => [
                'nome' => $funcionario['nome'] ?? '',
                'email' => $funcionario['email'] ?? '',
                'telefone' => '',
                'cargo' => $funcionario['funcao'] ?? '',
                'preferred_locale' => $funcionario['ui_locale'] ?? null,
            ],
            'outros' => [
                'data_atual' => date('d/m/Y'),
                'hora_atual' => date('H:i'),
                'nova_senha' => $novaSenha,
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

        $payload = [
            'to' => $funcionario['email'],
            'to_name' => $funcionario['nome'] ?? '',
            'subject' => $rendered['subject'] ?? 'Nova senha de acesso',
            'body' => $rendered['content'],
            'body_text' => $rendered['content_plain'],
            'id_matriz_filial' => $funcionario['id_matriz_filial'] ?? null,
        ];

        queue_message_service()->publish('email', $payload, $chave);
    }
}
