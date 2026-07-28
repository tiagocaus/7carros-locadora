<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\DateHelper;
use App\Models\Cliente;
use App\Models\ClientePasswordReset;
use App\Models\ContatoEmail;
use App\Models\Fornecedor;
use App\Models\FornecedorPasswordReset;
use App\Models\LoginAttempt;
use App\Models\PortalSession;

class PortalAuthService
{
    private const MAX_ATTEMPTS = 5;
    private const BLOCK_MINUTES = 15;

    public function login(
        string $chave,
        string $perfil,
        string $usuario,
        string $senha,
        string $ip,
        ?string $userAgent
    ): array {
        if (!in_array($perfil, ['cliente', 'investidor'], true)) {
            throw new \InvalidArgumentException('Perfil invalido.');
        }

        $attemptKey = $this->attemptKey($chave, $perfil, $usuario);
        $attemptModel = new LoginAttempt();
        $bloqueio = $attemptModel->buscarBloqueio($attemptKey, $ip);
        if ($bloqueio && strtotime((string) $bloqueio['bloqueado_ate']) > DateHelper::timestamp()) {
            throw new PortalAuthException('Muitas tentativas. Aguarde 15 minutos.', 429);
        }

        if ($perfil === 'cliente') {
            $candidatos = (new Cliente())->buscarUsuariosParaLogin($usuario);
            $entidade = count($candidatos) === 1 ? $candidatos[0] : null;
            $ativo = $entidade && strtoupper((string) ($entidade['situacao'] ?? 'A')) === 'A';
        } else {
            $candidatos = (new Fornecedor())->buscarInvestidoresParaLogin($usuario);
            $entidade = count($candidatos) === 1 ? $candidatos[0] : null;
            $ativo = $entidade && (int) ($entidade['investidor'] ?? 0) === 1;
        }

        if (
            !$entidade
            || !$ativo
            || empty($entidade['senha'])
            || !password_verify($senha, (string) $entidade['senha'])
        ) {
            $this->registrarFalha($attemptModel, $attemptKey, $ip);
            throw new PortalAuthException('Credenciais invalidas.', 401);
        }

        $attemptModel->limpar($attemptKey, $ip);
        $id = (int) $entidade['id'];

        if (password_needs_rehash((string) $entidade['senha'], PASSWORD_ARGON2ID)) {
            $hash = password_hash($senha, PASSWORD_ARGON2ID);
            if ($perfil === 'cliente') {
                (new Cliente())->atualizar($id, ['senha' => $hash]);
            } else {
                (new Fornecedor())->atualizar($id, ['senha' => $hash]);
            }
        }

        $token = (new PortalSession())->criar($chave, $perfil, $id, $ip, $userAgent);
        $nome = trim((string) ($entidade['nome_fantasia'] ?? ''));
        if ($nome === '') {
            $nome = trim((string) ($entidade['nome_rsocial'] ?? ''));
        }

        return [
            'token' => $token,
            'perfil' => $perfil,
            'entidade_id' => $id,
            'nome' => $nome,
            'expires_in' => PortalSession::IDLE_SECONDS,
        ];
    }

    public function solicitarReset(
        string $chave,
        string $perfil,
        string $usuario,
        string $ip
    ): void {
        if (!in_array($perfil, ['cliente', 'investidor'], true)) {
            return;
        }

        if ($perfil === 'cliente') {
            $candidatos = (new Cliente())->buscarUsuariosParaLogin($usuario);
            if (count($candidatos) !== 1) {
                return;
            }
            $entidade = $candidatos[0];
            $id = (int) $entidade['id'];
            $emailRow = (new ContatoEmail())->getPrincipal('cliente', $id);
            $email = trim((string) ($emailRow['email'] ?? ''));
            if ($email === '') {
                return;
            }
            $token = (new ClientePasswordReset())->criar($id, $chave, $ip);
        } else {
            $candidatos = (new Fornecedor())->buscarInvestidoresParaLogin($usuario);
            if (count($candidatos) !== 1) {
                return;
            }
            $entidade = $candidatos[0];
            $id = (int) $entidade['id'];
            $email = trim((string) ($entidade['email'] ?? ''));
            if ($email === '') {
                return;
            }
            $token = (new FornecedorPasswordReset())->criar($id, $chave, $ip);
        }

        $baseUrl = rtrim((string) Database::env('APP_URL', 'https://locadora.7carros.com'), '/');
        $resetUrl = $baseUrl . '/public/portal/redefinir-senha?'
            . http_build_query(['token' => $token, 'perfil' => $perfil]);
        $nome = trim((string) ($entidade['nome_fantasia'] ?? ''));
        if ($nome === '') {
            $nome = trim((string) ($entidade['nome_rsocial'] ?? ''));
        }

        if (function_exists('queue_template_message')) {
            queue_template_message('cliente_nova_senha', 'email', [
                'cliente' => [
                    'id' => $id,
                    'nome' => $nome,
                    'primeiro_nome' => explode(' ', $nome)[0] ?? $nome,
                    'email' => $email,
                ],
                'outros' => [
                    'data_atual' => format_date(DateHelper::todayForDatabase()),
                    'reset_url' => $resetUrl,
                    'reset_expira_em' => '60 minutos',
                ],
            ], $chave);
        }
    }

    public function redefinirSenha(string $perfil, string $token, string $senha): bool
    {
        if (strlen($senha) < 8) {
            throw new \InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }

        if ($perfil === 'cliente') {
            $model = new ClientePasswordReset();
            $reset = $model->validar($token);
            if (!$reset) {
                return false;
            }
            $chave = (string) $reset['chave'];
            $_SESSION['chave'] = $chave;
            $id = (int) $reset['id_cliente'];
            (new Cliente())->atualizar($id, ['senha' => password_hash($senha, PASSWORD_ARGON2ID)]);
            $model->marcarUsado((int) $reset['id'], $chave);
        } elseif ($perfil === 'investidor') {
            $model = new FornecedorPasswordReset();
            $reset = $model->validar($token);
            if (!$reset) {
                return false;
            }
            $chave = (string) $reset['chave'];
            $_SESSION['chave'] = $chave;
            $id = (int) $reset['id_fornecedor'];
            (new Fornecedor())->atualizar($id, ['senha' => password_hash($senha, PASSWORD_ARGON2ID)]);
            $model->marcarUsado((int) $reset['id'], $chave);
        } else {
            return false;
        }

        (new PortalSession())->revogarEntidade($chave, $perfil, $id);
        return true;
    }

    public function trocarSenha(
        string $chave,
        string $perfil,
        int $entidadeId,
        string $senhaAtual,
        string $novaSenha
    ): void {
        if (strlen($novaSenha) < 8) {
            throw new \InvalidArgumentException('A nova senha deve ter pelo menos 8 caracteres.');
        }

        if ($perfil === 'cliente') {
            $entidade = (new Cliente())->buscarPorId($entidadeId);
        } else {
            $entidade = (new Fornecedor())->buscarPorId($entidadeId);
        }

        if (!$entidade || !password_verify($senhaAtual, (string) ($entidade['senha'] ?? ''))) {
            throw new PortalAuthException('Senha atual invalida.', 401);
        }

        $hash = password_hash($novaSenha, PASSWORD_ARGON2ID);
        if ($perfil === 'cliente') {
            (new Cliente())->atualizar($entidadeId, ['senha' => $hash]);
        } else {
            (new Fornecedor())->atualizar($entidadeId, ['senha' => $hash]);
        }
        (new PortalSession())->revogarEntidade($chave, $perfil, $entidadeId);
    }

    private function attemptKey(string $chave, string $perfil, string $usuario): string
    {
        return 'portal:' . substr(hash('sha256', $chave . '|' . $perfil . '|' . mb_strtolower(trim($usuario))), 0, 64);
    }

    private function registrarFalha(LoginAttempt $model, string $usuario, string $ip): void
    {
        $tentativa = $model->buscar($usuario, $ip);
        if (!$tentativa) {
            $model->registrar($usuario, $ip);
            return;
        }

        $qtd = (int) $tentativa['tentativas'] + 1;
        $bloqueadoAte = $qtd >= self::MAX_ATTEMPTS
            ? DateHelper::formatTimestamp(
                DateHelper::timestamp() + (self::BLOCK_MINUTES * 60),
                'Y-m-d H:i:s',
                false
            )
            : null;
        $model->incrementar($usuario, $ip, $qtd, $bloqueadoAte);
    }
}
