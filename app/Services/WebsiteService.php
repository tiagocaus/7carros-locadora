<?php

namespace App\Services;

use App\Config\WebsiteThemes;
use App\Core\Database;
use App\Models\SiteConfig;
use App\Models\SiteCredencial;
use App\Models\SitePreset;

class WebsiteService
{
    /**
     * Verifica se um dominio tem registros DNS validos
     */
    public function verificarDominio(string $dominio): array
    {
        $dominio = trim(strtolower($dominio));
        $dominio = preg_replace('#^https?://#', '', $dominio);
        $dominio = rtrim($dominio, '/');

        if (empty($dominio)) {
            return ['valido' => false, 'registros' => [], 'mensagem' => t('modules.website.dns_empty')];
        }

        // Tentar A/CNAME primeiro, depois ANY para domínios sem A record
        $dns = @dns_get_record($dominio, DNS_A | DNS_CNAME);
        if (empty($dns)) {
            $dns = @dns_get_record($dominio, DNS_ANY);
        }

        // Verificar tambem via checkdnsrr (mais confiavel para dominios .com.br)
        if (empty($dns) && !checkdnsrr($dominio, 'ANY')) {
            return [
                'valido'    => false,
                'registros' => [],
                'mensagem'  => t('modules.website.dns_not_found'),
            ];
        }

        return [
            'valido'    => true,
            'registros' => $dns,
            'mensagem'  => t('modules.website.dns_valid'),
        ];
    }

    /**
     * Solicita ativacao do site — envia email para SAC e muda status para pendente
     */
    public function solicitarAtivacao(array $dados): array
    {
        $chave = $_SESSION['chave'];
        $sacEmail = Database::env('APP_COMPANY_EMAIL');

        // Montar corpo do email
        $body = "<h2>Solicitação de Ativação de Website</h2>";
        $body .= "<p><strong>Empresa:</strong> " . htmlspecialchars($dados['empresa'] ?? '') . "</p>";
        $body .= "<p><strong>Chave:</strong> " . htmlspecialchars($chave) . "</p>";
        $body .= "<p><strong>Domínio:</strong> " . htmlspecialchars($dados['dominio'] ?? '') . "</p>";
        $body .= "<p><strong>Plano:</strong> " . htmlspecialchars($dados['plano'] ?? '') . "</p>";
        $body .= "<p><strong>Registro de domínio:</strong> " . ($dados['quer_registro'] ? 'Sim' : 'Não') . "</p>";
        $body .= "<p><strong>Hospedagem:</strong> " . ($dados['quer_hospedagem'] ? 'Sim' : 'Não') . "</p>";

        // Enviar email via sistema (credenciais 7Carros)
        queue_system_message('email', [
            'to'      => $sacEmail,
            'subject' => "Ativação de Website - {$dados['empresa']} [{$chave}]",
            'body'    => $body,
        ]);

        // Atualizar status para pendente
        $configModel = new SiteConfig();
        $configModel->criarOuAtualizar([
            'dominio' => $dados['dominio'],
            'status'  => 'pendente',
        ]);

        return ['success' => true, 'message' => t('modules.website.activation_requested')];
    }

    /**
     * Processa callback do WHMCS para ativar site.
     *
     * $ftpData vem como array simples em query string — apenas `senha` chega
     * criptografada (AES-256-CBC, chave = sha256(TENANT_ONBOARD_SECRET)).
     */
    public function processarCallbackWhmcs(string $chave, string $secret, array $ftpData): array
    {
        // Validar secret
        $expectedSecret = Database::env('TENANT_ONBOARD_SECRET');
        if (empty($expectedSecret) || !hash_equals($expectedSecret, $secret)) {
            return ['success' => false, 'message' => 'Secret inválido'];
        }

        // Validar campos obrigatórios
        if (empty($ftpData['host']) || empty($ftpData['usuario']) || empty($ftpData['senha'])) {
            return ['success' => false, 'message' => 'Dados FTP incompletos'];
        }

        // Descriptografar apenas a senha (AES-256-CBC com TENANT_ONBOARD_SECRET)
        $senhaPlain = $this->decryptFtpSenha($ftpData['senha'], $expectedSecret);
        if ($senhaPlain === null) {
            return ['success' => false, 'message' => 'Falha ao descriptografar senha FTP'];
        }

        // Temporariamente setar chave na sessao para o QueryBuilder dos Models
        $oldChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;

        // Idempotência: se já está ativo, não refaz nada.
        $configAtual = (new SiteConfig())->buscarPorChave();
        if ($configAtual && ($configAtual['status'] ?? '') === 'ativo') {
            if ($oldChave !== null) {
                $_SESSION['chave'] = $oldChave;
            }
            return [
                'success'       => true,
                'already_active'=> true,
                'message'       => 'Site já está ativo — nenhuma ação realizada',
                'dominio'       => $configAtual['dominio'] ?? null,
                'versao'        => $configAtual['versao'] ?? null,
                'ultimo_deploy' => $configAtual['ultimo_deploy_em'] ?? null,
            ];
        }

        // Salvar credenciais (apenas senha criptografada com APP_KEY no BD)
        $credModel = new SiteCredencial();
        $credModel->salvar([
            'tipo'      => 'ftp',
            'host'      => $ftpData['host'],
            'porta'     => (int) ($ftpData['porta'] ?? 21),
            'usuario'   => $ftpData['usuario'],
            'senha'     => $senhaPlain,
            'diretorio' => !empty($ftpData['diretorio']) ? $ftpData['diretorio'] : '/public_html',
        ]);

        // Gerar api_token e ativar site
        $apiToken = bin2hex(random_bytes(32));
        $configModel = new SiteConfig();
        $configModel->criarOuAtualizar([
            'status'    => 'ativo',
            'api_token' => encrypt($apiToken),
        ]);

        // Seed de conteúdo padrão (idempotente — só popula se vazio)
        try {
            (new WebsiteSeedService())->seedTenant($chave);
        } catch (\Throwable $e) {
            error_log('[WHMCS site-ativacao] Seed falhou: ' . $e->getMessage());
        }

        // Deploy inicial para o FTP recém-recebido.
        // Build + upload FTP pode levar alguns segundos e consumir memória acima do default.
        // Falha não derruba a ativação — o tenant pode redeployar pela UI.
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        // O set_error_handler global (public/index.php) converte qualquer warning/notice
        // em HTTP 500 — o que quebra o deploy porque FTP/RecursiveIterator podem emitir
        // warnings rotineiros. Desativa temporariamente durante o deploy e restaura depois.
        set_error_handler(function () {
            return true; // engole warnings/notices; erros fatais continuam subindo
        });

        $deployResult = ['success' => false, 'message' => 'nao executado'];
        try {
            $builder = new WebsiteBuilderService();
            $deployResult = $builder->deploy($chave);
        } catch (\Throwable $e) {
            error_log('[WHMCS site-ativacao] Deploy inicial falhou: ' . $e->getMessage());
            $deployResult = ['success' => false, 'message' => $e->getMessage()];
        } finally {
            restore_error_handler();
        }

        // Restaurar sessao
        if ($oldChave !== null) {
            $_SESSION['chave'] = $oldChave;
        }

        return [
            'success'        => true,
            'message'        => 'Site ativado com sucesso',
            'deploy_success' => $deployResult['success'],
            'deploy_message' => $deployResult['message'],
        ];
    }

    /**
     * Descriptografa a senha FTP enviada pelo WHMCS.
     * Formato: base64(iv[16] . ciphertext) — AES-256-CBC, chave = sha256(secret).
     */
    private function decryptFtpSenha(string $senhaCifrada, string $secret): ?string
    {
        $bin = base64_decode($senhaCifrada, true);
        if ($bin === false || strlen($bin) < 17) {
            return null;
        }

        $key = hash('sha256', $secret, true);
        $iv = substr($bin, 0, 16);
        $ciphertext = substr($bin, 16);
        $plain = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $plain === false ? null : $plain;
    }

    /**
     * Resolve cores finais: preset base + override customizado
     *
     * 1. Se preset esta nos PRESETS fixos -> usa WebsiteThemes::PRESETS[$preset]
     * 2. Se nao esta nos fixos -> busca em site_presets pelo nome
     * 3. Se cores_customizadas nao eh NULL -> merge (override) sobre as cores do preset
     * 4. Resultado final: array completo --cor-1 a --cor-10
     */
    public function resolverCores(string $presetCor, ?array $coresCustomizadas = null): array
    {
        // 1. Tentar preset fixo
        $cores = WebsiteThemes::getPreset($presetCor);

        // 2. Se nao eh fixo, buscar customizado do tenant
        if ($cores === null) {
            $presetModel = new SitePreset();
            $preset = $presetModel->buscarPorNome($presetCor);
            $cores = $preset ? $preset['cores'] : WebsiteThemes::PRESETS['azul'];
        }

        // 3. Merge com override customizado
        if (!empty($coresCustomizadas)) {
            $cores = array_merge($cores, $coresCustomizadas);
        }

        return $cores;
    }
}
