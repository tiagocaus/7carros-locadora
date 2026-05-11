<?php

/**
 * Migration: Migrar dados da tabela legada `site` para tabelas normalizadas
 *
 * Le cada registro de `site` e distribui para:
 * - site_config (flags, dominio, status, whatsapp)
 * - site_credenciais (login JSON -> encrypt de cada campo)
 * - site_aparencia (cor -> preset_cor)
 * - site_conteudos (texto_inicio JSON, texto_sobre, texto_reserva JSON)
 * - site_integracoes (header/footer decodificados)
 * - site_links (links JSON -> 1 registro por rede)
 * - site_idiomas (pt_BR como padrao para todos)
 * - site_banners (setar ativo=1, ordem=id order)
 */

use App\Database\Migration;

// Carregar helpers (encrypt/decrypt) que nao estao no autoload do Composer
if (!function_exists('encrypt')) {
    require_once __DIR__ . '/../../Helpers/helpers.php';
}

return new class extends Migration
{
    public function up(): void
    {
        // Pegar apenas o registro mais recente de cada chave (existem duplicatas)
        $stmt = $this->pdo->query("
            SELECT s.* FROM site s
            INNER JOIN (
                SELECT chave, MAX(id) AS max_id FROM site GROUP BY chave
            ) latest ON s.id = latest.max_id
        ");
        $sites = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($sites as $site) {
            $chave = $site['chave'];
            $hasLogin = !empty($site['login']) && $site['login'] !== 'null';
            $hasDominio = !empty($site['dominio']);

            // --- 1. site_config ---
            $status = 'inativo';
            if ($hasLogin) {
                $status = 'ativo';
            } elseif ($hasDominio) {
                $status = 'pendente';
            }

            // Extrair whatsapp do links JSON
            $links = !empty($site['links']) ? json_decode($site['links'], true) : [];
            $whatsappNumero = null;
            $whatsappMensagem = null;
            if (!empty($links['whatsapp'])) {
                // Extrair numero do URL: phone=+5527999999999 ou phone=5527999999999
                if (preg_match('/phone[=:][\+]?(\d+)/', $links['whatsapp'], $m)) {
                    $whatsappNumero = $m[1];
                }
                // Extrair mensagem: text=xxx ou text=[xxx]
                if (preg_match('/text[=:]([^&]*)/', $links['whatsapp'], $m)) {
                    $msg = urldecode($m[1]);
                    $msg = trim($msg, '[]');
                    if (!empty($msg)) {
                        $whatsappMensagem = $msg;
                    }
                }
            }

            $this->db()->table('site_config')->insert([
                'chave'                => $chave,
                'dominio'              => $hasDominio ? $site['dominio'] : null,
                'status'               => $status,
                'manutencao'           => $site['manutencao'] === 'S' ? 1 : 0,
                'reserva_online'       => $site['reserva_online'] === 'S' ? 1 : 0,
                'overbooking'          => $site['overbooking'] === 'S' ? 1 : 0,
                'pagamento_antecipado' => $site['pagamento_antecipado'] === 'S' ? 1 : 0,
                'idioma_padrao'        => 'pt_BR',
                'whatsapp_flutuante'   => !empty($whatsappNumero) ? 1 : 0,
                'whatsapp_numero'      => $whatsappNumero,
                'whatsapp_mensagem'    => $whatsappMensagem,
            ]);

            // --- 2. site_credenciais (somente se tem login) ---
            if ($hasLogin) {
                $loginData = json_decode($site['login'], true);
                if ($loginData && !empty($loginData['username'])) {
                    $this->db()->table('site_credenciais')->insert([
                        'chave'     => $chave,
                        'tipo'      => 'ftp',
                        'host'      => encrypt($hasDominio ? $site['dominio'] : 'localhost'),
                        'porta'     => 21,
                        'usuario'   => encrypt($loginData['username']),
                        'senha'     => encrypt($loginData['password'] ?? ''),
                        'diretorio' => encrypt('/public_html'),
                    ]);
                }
            }

            // --- 3. site_aparencia ---
            $this->db()->table('site_aparencia')->insert([
                'chave'      => $chave,
                'preset_cor' => $site['cor'] ?: 'azul',
            ]);

            // --- 4. site_conteudos ---
            $this->migrarConteudos($chave, $site);

            // --- 5. site_integracoes (header/footer) ---
            $this->migrarIntegracoes($chave, $site);

            // --- 6. site_links ---
            $this->migrarLinks($chave, $links);

            // --- 7. site_idiomas (pt_BR como padrao) ---
            $this->db()->table('site_idiomas')->insert([
                'chave'  => $chave,
                'idioma' => 'pt_BR',
                'ativo'  => 1,
                'ordem'  => 0,
            ]);
        }

        // --- 8. site_banners: setar ativo e ordem ---
        $this->execute("UPDATE site_banners SET ativo = 1 WHERE ativo IS NULL OR ativo = 0");

        // Setar ordem baseada no ID (compativel com MySQL 5.7, sem window functions)
        $this->execute("
            UPDATE site_banners b
            JOIN (
                SELECT b1.id,
                       (SELECT COUNT(*) FROM site_banners b2 WHERE b2.chave = b1.chave AND b2.id < b1.id) AS calc_ordem
                FROM site_banners b1
            ) r ON b.id = r.id
            SET b.ordem = r.calc_ordem
        ");
    }

    public function down(): void
    {
        // Limpar dados migrados (na ordem inversa de dependencia)
        $tables = [
            'site_idiomas',
            'site_links',
            'site_integracoes',
            'site_conteudos',
            'site_aparencia',
            'site_credenciais',
            'site_config',
        ];

        foreach ($tables as $table) {
            $this->execute("DELETE FROM `{$table}`");
        }

        // Reverter site_banners
        $this->execute("UPDATE site_banners SET ativo = 1, ordem = 0");
    }

    private function migrarConteudos(string $chave, array $site): void
    {
        // texto_inicio: JSON {"1":"html","2":"html","3":"html"}
        if (!empty($site['texto_inicio'])) {
            $secoes = json_decode($site['texto_inicio'], true);
            if (is_array($secoes)) {
                foreach ($secoes as $secao => $html) {
                    if (!empty($html)) {
                        $this->db()->table('site_conteudos')->insert([
                            'chave'    => $chave,
                            'idioma'   => 'pt_BR',
                            'pagina'   => 'inicio',
                            'secao'    => (string) $secao,
                            'conteudo' => html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        ]);
                    }
                }
            }
        }

        // texto_sobre: HTML puro (possivelmente com entities)
        if (!empty($site['texto_sobre'])) {
            $this->db()->table('site_conteudos')->insert([
                'chave'    => $chave,
                'idioma'   => 'pt_BR',
                'pagina'   => 'sobre',
                'secao'    => 'principal',
                'conteudo' => html_entity_decode($site['texto_sobre'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ]);
        }

        // texto_reserva: JSON similar ao texto_inicio
        if (!empty($site['texto_reserva'])) {
            $secoes = json_decode($site['texto_reserva'], true);
            if (is_array($secoes)) {
                foreach ($secoes as $secao => $html) {
                    if (!empty($html)) {
                        $this->db()->table('site_conteudos')->insert([
                            'chave'    => $chave,
                            'idioma'   => 'pt_BR',
                            'pagina'   => 'reserva',
                            'secao'    => (string) $secao,
                            'conteudo' => html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        ]);
                    }
                }
            }
        }
    }

    private function migrarIntegracoes(string $chave, array $site): void
    {
        // header -> tipo=head
        if (!empty($site['header'])) {
            $decoded = urldecode(base64_decode($site['header']));
            if (!empty($decoded)) {
                $this->db()->table('site_integracoes')->insert([
                    'chave'     => $chave,
                    'tipo'      => 'head',
                    'codigo'    => $decoded,
                    'descricao' => 'Migrado do campo header',
                    'ativo'     => 1,
                    'ordem'     => 0,
                ]);
            }
        }

        // footer -> tipo=body_fim
        if (!empty($site['footer'])) {
            $decoded = urldecode(base64_decode($site['footer']));
            if (!empty($decoded)) {
                $this->db()->table('site_integracoes')->insert([
                    'chave'     => $chave,
                    'tipo'      => 'body_fim',
                    'codigo'    => $decoded,
                    'descricao' => 'Migrado do campo footer',
                    'ativo'     => 1,
                    'ordem'     => 0,
                ]);
            }
        }
    }

    private function migrarLinks(string $chave, array $links): void
    {
        // Links de redes sociais (exceto whatsapp que ja foi para site_config)
        $ordem = 0;
        $redesSociais = ['instagram', 'facebook', 'twitter', 'youtube', 'linkedin', 'tiktok'];

        foreach ($redesSociais as $tipo) {
            if (!empty($links[$tipo])) {
                $this->db()->table('site_links')->insert([
                    'chave' => $chave,
                    'tipo'  => $tipo,
                    'url'   => $links[$tipo],
                    'ativo' => 1,
                    'ordem' => $ordem++,
                ]);
            }
        }
    }
};
