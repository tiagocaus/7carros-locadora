<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\DateHelper;
use App\Helpers\PdfHelper;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\MatrizFilial;
use App\Models\PortalAuditLog;
use App\Models\PortalRepository;
use App\Models\PortalSession;
use App\Models\SiteConfig;
use App\Services\PagamentoLinkSyncService;
use App\Services\PortalAuthException;
use App\Services\PortalAuthService;
use App\Services\PortalProfileNotificationService;

/**
 * API publica do portal, consumida somente pelos proxies PHP do website.
 */
class PortalController
{
    public function login(Request $request): void
    {
        try {
            $chave = $this->autenticarSite($request);
            $perfil = (string) $request->input('perfil', '');
            $usuario = trim((string) $request->input('usuario', ''));
            $senha = (string) $request->input('senha', '');

            if ($usuario === '' || $senha === '') {
                Response::json(['success' => false, 'message' => 'Usuario e senha obrigatorios.'], 422);
            }

            $data = (new PortalAuthService())->login(
                $chave,
                $perfil,
                $usuario,
                $senha,
                $this->portalClientIp($request),
                $this->portalClientAgent($request)
            );
            Response::json(['success' => true, 'data' => $data]);
        } catch (PortalAuthException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], $e->statusCode());
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Portal] Erro no login: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Nao foi possivel entrar no portal.'], 500);
        }
    }

    public function logout(Request $request): void
    {
        try {
            $chave = $this->autenticarSite($request);
            $token = (string) $request->header('X-Portal-Token', '');
            (new PortalSession())->revogar($chave, $token);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['success' => true]);
        }
    }

    public function sessao(Request $request): void
    {
        $auth = $this->autenticarPortal($request);
        $perfil = (new PortalRepository())->perfil($auth['perfil'], $auth['entidade_id']);
        if (!$perfil) {
            Response::json(['success' => false, 'message' => 'Cadastro nao encontrado.'], 404);
        }
        Response::json(['success' => true, 'data' => $perfil]);
    }

    public function dashboard(Request $request): void
    {
        try {
            $auth = $this->autenticarPortal($request);
            $repo = new PortalRepository();
            if ($auth['perfil'] === 'cliente') {
                $data = $repo->dashboardCliente($auth['entidade_id']);
            } else {
                [$inicio, $fim] = $this->periodo($request);
                $data = $repo->dashboardInvestidor($auth['entidade_id'], $inicio, $fim);
            }
            Response::json(['success' => true, 'data' => $data]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Portal] Dashboard: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao carregar o painel.'], 500);
        }
    }

    public function listar(Request $request, string $recurso): void
    {
        try {
            $auth = $this->autenticarPortal($request);
            $pagina = max(1, (int) $request->query('page', 1));
            $porPagina = min(100, max(1, (int) $request->query('per_page', 20)));
            $repo = new PortalRepository();

            if ($auth['perfil'] === 'cliente') {
                $data = $repo->listarCliente(
                    $recurso,
                    $auth['entidade_id'],
                    $pagina,
                    $porPagina
                );
            } else {
                [$inicio, $fim] = $this->periodo($request);
                $data = $repo->listarInvestidor(
                    $recurso,
                    $auth['entidade_id'],
                    $pagina,
                    $porPagina,
                    $inicio,
                    $fim
                );
            }
            Response::json(['success' => true] + $data);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Portal] Listagem ' . $recurso . ': ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao carregar os dados.'], 500);
        }
    }

    public function atualizarPerfil(Request $request): void
    {
        try {
            $auth = $this->autenticarPortal($request);
            $repo = new PortalRepository();
            $resultado = $repo->atualizarPerfil(
                $auth['perfil'],
                $auth['entidade_id'],
                $request->all()
            );

            if ($resultado['alterados'] !== []) {
                (new PortalAuditLog())->registrar(
                    $auth['chave'],
                    $auth['perfil'],
                    $auth['entidade_id'],
                    'perfil_atualizado',
                    $resultado['alterados'],
                    $this->portalClientIp($request),
                    $this->portalClientAgent($request)
                );

                $filialId = $this->filialParaNotificacao($auth['perfil'], $auth['entidade_id']);
                if ($filialId > 0) {
                    (new PortalProfileNotificationService())->notificar(
                        $auth['chave'],
                        $filialId,
                        $auth['perfil'],
                        (string) ($resultado['perfil']['nome'] ?? ''),
                        $resultado['alterados']
                    );
                }
            }

            Response::json([
                'success' => true,
                'message' => 'Dados atualizados com sucesso.',
                'data' => $resultado['perfil'],
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Portal] Atualizacao de perfil: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Nao foi possivel atualizar os dados.'], 500);
        }
    }

    public function trocarSenha(Request $request): void
    {
        try {
            $auth = $this->autenticarPortal($request);
            (new PortalAuthService())->trocarSenha(
                $auth['chave'],
                $auth['perfil'],
                $auth['entidade_id'],
                (string) $request->input('senha_atual', ''),
                (string) $request->input('nova_senha', '')
            );
            (new PortalAuditLog())->registrar(
                $auth['chave'],
                $auth['perfil'],
                $auth['entidade_id'],
                'senha_alterada',
                [['campo' => 'senha', 'de' => '', 'para' => 'alterada']],
                $this->portalClientIp($request),
                $this->portalClientAgent($request)
            );
            Response::json([
                'success' => true,
                'message' => 'Senha alterada. Entre novamente no portal.',
                'reauthenticate' => true,
            ]);
        } catch (PortalAuthException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], $e->statusCode());
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Portal] Troca de senha: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Nao foi possivel alterar a senha.'], 500);
        }
    }

    public function solicitarReset(Request $request): void
    {
        try {
            $chave = $this->autenticarSite($request);
            (new PortalAuthService())->solicitarReset(
                $chave,
                (string) $request->input('perfil', ''),
                trim((string) $request->input('usuario', '')),
                $this->portalClientIp($request)
            );
        } catch (\Throwable $e) {
            error_log('[Portal] Solicitacao de reset: ' . $e->getMessage());
        }

        Response::json([
            'success' => true,
            'message' => 'Se o cadastro for localizado, enviaremos as instrucoes para o e-mail registrado.',
        ]);
    }

    public function exibirReset(Request $request): void
    {
        $token = htmlspecialchars((string) $request->query('token', ''), ENT_QUOTES, 'UTF-8');
        $perfil = (string) $request->query('perfil', '');
        $perfilSeguro = in_array($perfil, ['cliente', 'investidor'], true) ? $perfil : '';
        $csrf = bin2hex(random_bytes(16));
        $_SESSION['portal_reset_csrf'] = $csrf;

        header('Content-Type: text/html; charset=UTF-8');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        echo $this->resetHtml($token, $perfilSeguro, $csrf);
        exit;
    }

    public function definirReset(Request $request): void
    {
        try {
            $csrf = (string) $request->input('_csrf', '');
            $csrfSession = (string) ($_SESSION['portal_reset_csrf'] ?? '');
            if ($csrfSession === '' || !hash_equals($csrfSession, $csrf)) {
                Response::json(['success' => false, 'message' => 'Sessao expirada. Abra o link novamente.'], 403);
            }
            $ok = (new PortalAuthService())->redefinirSenha(
                (string) $request->input('perfil', ''),
                (string) $request->input('token', ''),
                (string) $request->input('senha', '')
            );
            unset($_SESSION['portal_reset_csrf']);
            if (!$ok) {
                Response::json(['success' => false, 'message' => 'Link invalido ou expirado.'], 400);
            }
            Response::json(['success' => true, 'message' => 'Senha definida com sucesso.']);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('[Portal] Definicao de senha: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Nao foi possivel definir a senha.'], 500);
        }
    }

    public function linkPagamento(Request $request, int $id): void
    {
        try {
            $auth = $this->autenticarPortal($request);
            if ($auth['perfil'] !== 'cliente') {
                Response::json(['success' => false, 'message' => 'Recurso indisponivel.'], 403);
            }
            $financeiro = (new PortalRepository())->financeiroDoCliente($id, $auth['entidade_id']);
            if (!$financeiro) {
                Response::json(['success' => false, 'message' => 'Fatura nao encontrada.'], 404);
            }
            if (($financeiro['pago'] ?? 'N') === 'S') {
                Response::json(['success' => false, 'message' => 'Esta fatura ja foi paga.'], 422);
            }
            $link = (new PagamentoLinkSyncService())->obterOuCriarLinkAtualizado($id, $auth['chave']);
            Response::json(['success' => true, 'url' => $link['url']]);
        } catch (\Throwable $e) {
            error_log('[Portal] Link de pagamento: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Nao foi possivel gerar o link.'], 500);
        }
    }

    public function recibo(Request $request, int $id): void
    {
        try {
            $auth = $this->autenticarPortal($request);
            if ($auth['perfil'] !== 'cliente') {
                Response::html('<h1>Acesso negado</h1>', 403);
            }
            $fatura = (new PortalRepository())->financeiroDoCliente($id, $auth['entidade_id']);
            if (!$fatura || ($fatura['pago'] ?? 'N') !== 'S') {
                Response::html('<h1>Recibo nao encontrado</h1>', 404);
            }
            $perfil = (new PortalRepository())->perfil('cliente', $auth['entidade_id']);
            $html = $this->reciboHtml($fatura, $perfil ?? []);
            PdfHelper::outputInline($html, 'recibo-' . ($fatura['codigo'] ?? $id) . '.pdf');
            exit;
        } catch (\Throwable $e) {
            error_log('[Portal] Recibo: ' . $e->getMessage());
            Response::html('<h1>Erro ao gerar recibo</h1>', 500);
        }
    }

    private function autenticarPortal(Request $request): array
    {
        $chave = $this->autenticarSite($request);
        $token = (string) $request->header('X-Portal-Token', '');
        $session = (new PortalSession())->validar(
            $chave,
            $token,
            $this->portalClientAgent($request)
        );
        if (!$session) {
            Response::json(['success' => false, 'message' => 'Sessao expirada.', 'session_expired' => true], 401);
        }
        return [
            'chave' => $chave,
            'perfil' => (string) $session['perfil'],
            'entidade_id' => (int) $session['entidade_id'],
        ];
    }

    private function autenticarSite(Request $request): string
    {
        $chave = trim((string) ($request->query('chave') ?? $request->input('chave') ?? ''));
        $token = (string) $request->header('X-Site-Token', '');
        if ($chave === '' || $token === '') {
            Response::json(['success' => false, 'message' => 'Site nao autenticado.'], 401);
        }

        $config = (new SiteConfig())->buscarPorChaveExplicita($chave);
        $tokenConfigurado = $config ? decrypt($config['api_token'] ?? '') : null;
        if (
            !$config
            || ($config['status'] ?? '') !== 'ativo'
            || !$tokenConfigurado
            || !hash_equals((string) $tokenConfigurado, $token)
        ) {
            Response::json(['success' => false, 'message' => 'Site nao autenticado.'], 401);
        }
        $_SESSION['chave'] = $chave;
        DateHelper::clearCache();
        return $chave;
    }

    private function portalClientIp(Request $request): string
    {
        $encaminhado = trim((string) $request->header('X-Portal-Client-IP', ''));
        return filter_var($encaminhado, FILTER_VALIDATE_IP)
            ? $encaminhado
            : $request->ip();
    }

    private function portalClientAgent(Request $request): string
    {
        $encaminhado = str_replace(
            ["\r", "\n"],
            '',
            (string) $request->header('X-Portal-Client-Agent', '')
        );
        return mb_substr(
            $encaminhado !== '' ? $encaminhado : (string) $request->header('User-Agent', ''),
            0,
            500
        );
    }

    private function periodo(Request $request): array
    {
        $fimPadrao = DateHelper::todayForDatabase();
        $inicioPadrao = DateHelper::addMonthsForDatabase(-12, $fimPadrao);
        $inicio = (string) $request->query('data_inicio', $inicioPadrao);
        $fim = (string) $request->query('data_fim', $fimPadrao);
        if (!$this->dataValida($inicio) || !$this->dataValida($fim) || $inicio > $fim) {
            throw new \InvalidArgumentException('Periodo invalido.');
        }
        return [$inicio, $fim];
    }

    private function dataValida(string $data): bool
    {
        $obj = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        return $obj !== false && $obj->format('Y-m-d') === $data;
    }

    private function filialParaNotificacao(string $perfil, int $entidadeId): int
    {
        if ($perfil === 'cliente') {
            $cliente = (new Cliente())->buscarPorId($entidadeId);
            if (!empty($cliente['id_matriz_filial'])) {
                return (int) $cliente['id_matriz_filial'];
            }
        }
        $matriz = (new MatrizFilial())->buscarMatriz();
        return (int) ($matriz['id'] ?? 0);
    }

    private function resetHtml(string $token, string $perfil, string $csrf): string
    {
        $perfilEsc = htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8');
        $csrfEsc = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
        $invalido = $token === '' || $perfilEsc === '';
        $form = $invalido ? '<p>Link invalido ou incompleto.</p>' : <<<HTML
<form id="resetForm">
  <input type="hidden" name="token" value="{$token}">
  <input type="hidden" name="perfil" value="{$perfilEsc}">
  <input type="hidden" name="_csrf" value="{$csrfEsc}">
  <label>Nova senha<input type="password" name="senha" minlength="8" required autocomplete="new-password"></label>
  <label>Confirmar senha<input type="password" name="confirmacao" minlength="8" required autocomplete="new-password"></label>
  <button type="submit">Definir nova senha</button>
  <p id="message" role="status"></p>
</form>
HTML;
        return <<<HTML
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Definir nova senha</title>
<style>body{margin:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#263238}.card{max-width:430px;margin:8vh auto;background:#fff;padding:32px;border-radius:14px;box-shadow:0 18px 45px #1831531a}h1{font-size:25px}label{display:block;font-weight:600;margin:16px 0}input{display:block;width:100%;box-sizing:border-box;margin-top:7px;padding:12px;border:1px solid #ccd5df;border-radius:8px}button{width:100%;padding:13px;border:0;border-radius:8px;background:#087f8c;color:#fff;font-weight:700;cursor:pointer}#message{min-height:24px}.ok{color:#16803c}.err{color:#b42318}</style></head>
<body><main class="card"><h1>Definir nova senha</h1><p>Use pelo menos 8 caracteres.</p>{$form}</main>
<script>var f=document.getElementById('resetForm');if(f){f.addEventListener('submit',async function(e){e.preventDefault();var m=document.getElementById('message');if(f.senha.value!==f.confirmacao.value){m.textContent='As senhas nao coincidem.';m.className='err';return;}var b=new FormData(f);try{var r=await fetch('/api/public/portal/senha/definir',{method:'POST',body:b,headers:{Accept:'application/json'}});var j=await r.json();m.textContent=j.message||'Nao foi possivel concluir.';m.className=j.success?'ok':'err';if(j.success){f.querySelectorAll('input,button').forEach(function(el){el.disabled=true;});}}catch(x){m.textContent='Erro de rede.';m.className='err';}});}</script></body></html>
HTML;
    }

    private function reciboHtml(array $fatura, array $perfil): string
    {
        $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $valor = currency_format((float) ($fatura['valor_total'] ?? 0));
        $data = format_date((string) ($fatura['data_pago'] ?? ''));
        $codigo = $e($fatura['codigo'] ?? $fatura['id']);
        $nome = $e($perfil['nome'] ?? '');
        $documento = $e($perfil['cpf_cnpj'] ?? '');
        $descricao = $e($fatura['descricao'] ?? 'Pagamento');
        return <<<HTML
<html><head><style>body{font-family:sans-serif;color:#222}.box{border:1px solid #bbb;padding:24px}.title{text-align:center;font-size:22px}.row{margin:12px 0}.value{font-size:20px;font-weight:bold}</style></head>
<body><div class="box"><div class="title">RECIBO</div><p class="row">Recebemos de <strong>{$nome}</strong> ({$documento}) o valor de <span class="value">{$valor}</span>.</p><p class="row">Referente a: {$descricao}</p><p class="row">Documento: {$codigo}</p><p class="row">Pagamento em: {$data}</p></div></body></html>
HTML;
    }
}
