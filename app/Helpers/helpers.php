<?php

/**
 * Helper Functions
 *
 * Funções auxiliares globais disponíveis em toda a aplicação
 */

use App\Core\Session;
use App\Core\Database;
use App\Core\Cache;

if (!function_exists('old')) {
    /**
     * Obtém o valor antigo de um campo do formulário
     *
     * Usado para repopular formulários após erros de validação
     *
     * @param string $key Nome do campo
     * @param mixed $default Valor padrão se o campo não existir
     * @return mixed
     */
    function old(string $key, mixed $default = null): mixed
    {
        return Session::old($key, $default);
    }
}

if (!function_exists('session')) {
    /**
     * Obtém ou define um valor na sessão
     *
     * @param string|null $key Chave da sessão
     * @param mixed $default Valor padrão
     * @return mixed
     */
    function session(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return Session::all();
        }
        return Session::get($key, $default);
    }
}

if (!function_exists('flash')) {
    /**
     * Define uma mensagem flash
     *
     * @param string $key Chave da mensagem
     * @param mixed $value Valor da mensagem
     * @return void
     */
    function flash(string $key, mixed $value): void
    {
        Session::flash($key, $value);
    }
}

if (!function_exists('env')) {
    /**
     * Obtém uma variável de ambiente
     *
     * @param string $key Nome da variável
     * @param mixed $default Valor padrão
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Database::env($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Obtém uma configuração da aplicação
     *
     * @param string $key Nome da configuração
     * @param mixed $default Valor padrão
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        // TODO: Implementar sistema de configuração
        // Por enquanto, delega para env()
        return env($key, $default);
    }
}

if (!function_exists('asset')) {
    /**
     * Gera URL para um asset público com cache busting automático
     * Adiciona ?v=timestamp automaticamente baseado na data de modificação do arquivo
     *
     * @param string $path Caminho do asset (ex: 'css/style.css' ou 'js/app.js')
     * @param bool $versioned Se true, adiciona timestamp automaticamente (padrão: true)
     * @return string
     */
    function asset(string $path, bool $versioned = true): string
    {
        $path = ltrim($path, '/');
        $baseUrl = rtrim(env('APP_URL', ''), '/');
        $fullUrl = $baseUrl . '/assets/' . $path;
        
        // Se versioned está desabilitado, retorna URL sem timestamp
        if (!$versioned) {
            return $fullUrl;
        }
        
        // Caminho físico do arquivo
        $publicPath = APP_ROOT . '/public/assets/' . $path;
        
        // Verifica se o arquivo existe e obtém timestamp de modificação
        if (file_exists($publicPath)) {
            $timestamp = filemtime($publicPath);
            $fullUrl .= '?v=' . $timestamp;
        }
        
        return $fullUrl;
    }
}

if (!function_exists('image')) {
    /**
     * Gera URL para uma imagem com cache busting automático
     * Adiciona ?v=timestamp automaticamente baseado na data de modificação do arquivo
     *
     * @param string $path Caminho da imagem (ex: 'assets/img/foto.png' ou 'storage/uploads/foto.jpg')
     *                     Também aceita URLs completas (http/https) - nesse caso retorna como está
     * @param bool $versioned Se true, adiciona timestamp automaticamente (padrão: true)
     * @return string
     */
    function image(string $path, bool $versioned = true): string
    {
        // Se for uma URL completa (http/https) ou data URI, retorna como está
        if (preg_match('/^(https?:\/\/|data:)/i', $path)) {
            return $path;
        }
        
        $path = ltrim($path, '/');
        $baseUrl = rtrim(env('APP_URL', ''), '/');
        $fullUrl = $baseUrl . '/' . $path;
        
        // Se versioned está desabilitado, retorna URL sem timestamp
        if (!$versioned) {
            return $fullUrl;
        }
        
        // Caminho físico do arquivo - tenta diferentes localizações
        $publicPath = APP_ROOT . '/public/' . $path;
        $filePath = null;
        
        // Verifica se o arquivo existe em public
        if (file_exists($publicPath) && is_file($publicPath)) {
            $filePath = $publicPath;
        } else {
            // Se não encontrar em public, tenta em storage
            $storagePath = APP_ROOT . '/storage/' . str_replace('storage/', '', $path);
            if (file_exists($storagePath) && is_file($storagePath)) {
                $filePath = $storagePath;
            } else {
                // Tenta diretamente no caminho fornecido (caso seja relativo ao APP_ROOT)
                $directPath = APP_ROOT . '/' . $path;
                if (file_exists($directPath) && is_file($directPath)) {
                    $filePath = $directPath;
                }
            }
        }
        
        // Se encontrou o arquivo, adiciona timestamp de modificação
        if ($filePath !== null) {
            $timestamp = filemtime($filePath);
            // Adiciona timestamp se ainda não existir na URL
            $separator = strpos($fullUrl, '?') !== false ? '&' : '?';
            $fullUrl .= $separator . 'v=' . $timestamp;
        }
        
        return $fullUrl;
    }
}

if (!function_exists('img')) {
    /**
     * Alias para image() - Gera URL para uma imagem com cache busting automático
     *
     * @param string $path Caminho da imagem
     * @param bool $versioned Se true, adiciona timestamp automaticamente (padrão: true)
     * @return string
     */
    function img(string $path, bool $versioned = true): string
    {
        return image($path, $versioned);
    }
}

if (!function_exists('url')) {
    /**
     * Gera URL completa
     *
     * @param string $path Caminho relativo
     * @return string
     */
    function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $baseUrl = rtrim(env('APP_URL', ''), '/');
        return $baseUrl . '/' . $path;
    }
}

if (!function_exists('redirect')) {
    /**
     * Cria uma resposta de redirecionamento
     *
     * @param string $url URL de destino
     * @return void
     */
    function redirect(string $url): void
    {
        \App\Core\Response::redirect($url);
    }
}

if (!function_exists('back')) {
    /**
     * Redireciona para a página anterior
     *
     * @return void
     */
    function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and Die - Debug helper
     *
     * @param mixed ...$vars Variáveis para dump
     * @return void
     */
    function dd(...$vars): void
    {
        echo '<pre>';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
        die();
    }
}

if (!function_exists('dump')) {
    /**
     * Dump - Debug helper (sem parar execução)
     *
     * @param mixed ...$vars Variáveis para dump
     * @return void
     */
    function dump(...$vars): void
    {
        echo '<pre>';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
    }
}

if (!function_exists('e')) {
    /**
     * Escapa HTML entities
     *
     * @param string|null $value Valor para escapar
     * @return string
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('js_string')) {
    /**
     * Codifica uma string PHP como literal JavaScript seguro.
     */
    function js_string(?string $value): string
    {
        return json_encode(
            $value ?? '',
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_APOS
            | JSON_HEX_AMP
            | JSON_HEX_QUOT
        );
    }
}

if (!function_exists('js_t')) {
    /**
     * Traduz e codifica como literal JavaScript seguro.
     */
    function js_t(string $key, array $replace = [], ?string $locale = null): string
    {
        return js_string(t($key, $replace, $locale));
    }
}

if (!function_exists('now')) {
    /**
     * Retorna a data/hora atual
     *
     * @param string $format Formato da data (padrão: Y-m-d H:i:s)
     * @return string
     */
    function now(string $format = 'Y-m-d H:i:s'): string
    {
        return \App\Helpers\DateHelper::nowForDatabase($format);
    }
}

if (!function_exists('today')) {
    /**
     * Retorna a data atual
     *
     * @param string $format Formato da data (padrão: Y-m-d)
     * @return string
     */
    function today(string $format = 'Y-m-d'): string
    {
        return \App\Helpers\DateHelper::todayForDatabase($format);
    }
}

if (!function_exists('str_limit')) {
    /**
     * Limita o tamanho de uma string
     *
     * Versão PHP do helper de string. Para uso no JavaScript, veja `Str.limit()` em `components.js`.
     *
     * @param string $value String original
     * @param int $limit Tamanho máximo
     * @param string $end Sufixo (padrão: ...)
     * @return string
     *
     * @example
     * str_limit('Texto muito longo', 10) // "Texto mui..."
     *
     * @see public/assets/js/components.js - Objeto Str.limit() para JavaScript
     */
    function str_limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return mb_substr($value, 0, $limit) . $end;
    }
}

if (!function_exists('money_format')) {
    /**
     * Formata valor monetário para Real Brasileiro
     *
     * @param float $value Valor numérico
     * @param bool $symbol Incluir símbolo R$ (padrão: true)
     * @return string
     */
    function money_format(float $value, bool $symbol = true): string
    {
        $formatted = number_format($value, 2, ',', '.');
        return $symbol ? 'R$ ' . $formatted : $formatted;
    }
}

// ========================================
// CACHE HELPERS
// ========================================

if (!function_exists('cache')) {
    /**
     * Obtém ou define um valor no cache
     *
     * @param string|null $key Chave do cache
     * @param mixed $default Valor padrão ou callback
     * @param int|null $ttl Tempo de vida em segundos
     * @return mixed
     */
    function cache(?string $key = null, mixed $default = null, ?int $ttl = null): mixed
    {
        // Se não passar chave, retorna instância do Cache
        if ($key === null) {
            return Cache::class;
        }

        // Se passar callback como default, usa remember
        if (is_callable($default)) {
            return Cache::remember($key, $ttl ?? 3600, $default);
        }

        // Se passar valor e TTL, define no cache
        if ($default !== null && $ttl !== null) {
            Cache::set($key, $default, $ttl);
            return $default;
        }

        // Senão, apenas busca
        return Cache::get($key, $default);
    }
}

if (!function_exists('cache_remember')) {
    /**
     * Obtém do cache ou executa callback e armazena
     *
     * @param string $key Chave do cache
     * @param int $ttl Tempo de vida em segundos
     * @param callable $callback Função a executar
     * @return mixed
     */
    function cache_remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }
}

if (!function_exists('cache_forget')) {
    /**
     * Remove um valor do cache
     *
     * @param string $key Chave do cache
     * @return bool
     */
    function cache_forget(string $key): bool
    {
        return Cache::forget($key);
    }
}

if (!function_exists('cache_flush')) {
    /**
     * Limpa todo o cache (usar com cuidado!)
     *
     * @return bool
     */
    function cache_flush(): bool
    {
        return Cache::flush();
    }
}

if (!function_exists('cache_stats')) {
    /**
     * Obtém estatísticas de uso do cache
     *
     * @return array
     */
    function cache_stats(): array
    {
        return Cache::stats();
    }
}

// ========================================
// MESSAGE QUEUE HELPERS
// ========================================

if (!function_exists('queue_message_service')) {
    /**
     * Reaproveita o publisher RabbitMQ durante a mesma request/script.
     */
    function queue_message_service(): \App\Services\MessageQueueService
    {
        static $service = null;

        if ($service === null) {
            $service = new \App\Services\MessageQueueService();
        }

        return $service;
    }
}

if (!function_exists('queue_message')) {
    /**
     * Adiciona uma mensagem a fila para processamento em segundo plano
     *
     * O id_matriz_filial e injetado automaticamente da sessao se nao fornecido.
     * Ele e necessario para que os services (Email, WhatsApp, SMS) resolvam
     * as credenciais corretas do tenant.
     *
     * @param string $type Tipo de mensagem: 'email', 'sms', 'whatsapp'
     * @param array $payload Dados da mensagem (sera serializado como JSON)
     * @param string|null $chave Chave do tenant (opcional, usa $_SESSION se nao fornecido)
     * @param string|null $batchId Identificador de batch/lote (opcional, para rastreamento)
     * @return int ID da mensagem salva no banco de dados
     */
    function queue_message(string $type, array $payload, ?string $chave = null, ?string $batchId = null): int
    {
        // Injetar id_matriz_filial da sessao se nao fornecido
        if (!isset($payload['id_matriz_filial']) && !empty($_SESSION['id_matriz_filial'])) {
            $payload['id_matriz_filial'] = $_SESSION['id_matriz_filial'];
        }

        return queue_message_service()->publish($type, $payload, $chave, $batchId);
    }
}

if (!function_exists('validate_queue_message')) {
    /**
     * Valida uma mensagem antes de preparar anexos/arquivos caros.
     *
     * @param string $type Tipo de mensagem: 'email', 'sms', 'whatsapp'
     * @param array $payload Dados da mensagem
     * @param bool $validateContent Validar tambem subject/body/message/media_url
     */
    function validate_queue_message(string $type, array $payload, bool $validateContent = false): void
    {
        if (!isset($payload['id_matriz_filial']) && !empty($_SESSION['id_matriz_filial'])) {
            $payload['id_matriz_filial'] = $_SESSION['id_matriz_filial'];
        }

        queue_message_service()->validateForPublication($type, $payload, $validateContent);
    }
}

if (!function_exists('queue_system_message')) {
    /**
     * Envia mensagem da plataforma 7Carros para um tenant
     *
     * Usa credenciais do sistema (ENV): SMTP da 7Carros e WHATSAPP_API_INSTANCE_TOKEN.
     * Para WhatsApp, prefixa a mensagem com "*[7Carros]*\n".
     * Para emails, usa layout-system.php (branding 7Carros).
     *
     * @param string $type Tipo: 'email', 'whatsapp'
     * @param array $payload Dados da mensagem
     * @param string|null $chave Chave do tenant destinatario (opcional)
     * @return int ID da mensagem
     */
    function queue_system_message(string $type, array $payload, ?string $chave = null): int
    {
        $payload['_system_message'] = true;

        if ($type === 'whatsapp' && !empty($payload['message'])) {
            $payload['message'] = "*[7Carros]*\n" . $payload['message'];
        }
        if ($type === 'whatsapp' && !empty($payload['caption'])) {
            $payload['caption'] = "*[7Carros]*\n" . $payload['caption'];
        }

        return queue_message($type, $payload, $chave);
    }
}

if (!function_exists('queue_template_message')) {
    /**
     * Envia mensagem usando template traduzido para o idioma do cliente
     *
     * Esta função integra o sistema de templates (MessageTemplateService) com
     * a fila de mensagens (MessageQueueService), permitindo envio de mensagens
     * traduzidas automaticamente para o idioma preferido do destinatário.
     *
     * O idioma é determinado na seguinte ordem de prioridade:
     * 1. $context['cliente']['preferred_locale'] - Idioma preferido do cliente
     * 2. $context['empresa']['locale'] - Idioma da empresa
     * 3. Idioma atual da sessão (pt_BR como fallback)
     *
     * @param string $templateSlug Slug do template (ex: 'rental_confirmation', 'welcome')
     * @param string $channel Canal de envio: 'email', 'sms', 'whatsapp'
     * @param array $context Dados para renderização do template. Deve conter:
     *                       - cliente: array com dados do cliente (nome, email, telefone, preferred_locale)
     *                       - empresa: array com dados da empresa
     *                       - Outros dados conforme variáveis do template (locacao, veiculo, contrato, etc)
     * @param string|null $chave Chave do tenant (opcional, usa $_SESSION se não fornecido)
     * @param string|null $batchId Identificador de batch/lote para rastreamento (opcional)
     * @return int ID da mensagem na fila
     * @throws \RuntimeException Se template não for encontrado
     * @throws \InvalidArgumentException Se canal for inválido
     *
     * @example
     * // Enviar email de confirmação de locação (traduzido para idioma do cliente)
     * $cliente = $qb->getRow('clientes', ['*'], 'id = ?', [$clienteId]);
     * $empresa = $qb->getRow('empresas', ['*'], 'chave = ?', [$chave]);
     *
     * queue_template_message('rental_confirmation', 'email', [
     *     'cliente' => $cliente,
     *     'empresa' => $empresa,
     *     'locacao' => $locacaoData,
     *     'veiculo' => $veiculoData,
     * ]);
     * // Se cliente.preferred_locale = 'en_US', email será enviado em inglês
     */
    function queue_template_message(
        string $templateSlug,
        string $channel,
        array $context,
        ?string $chave = null,
        ?string $batchId = null
    ): int {
        // Validar canal
        $allowedChannels = ['email', 'sms', 'whatsapp'];
        if (!in_array($channel, $allowedChannels, true)) {
            throw new \InvalidArgumentException(
                "Canal inválido '{$channel}'. Use: " . implode(', ', $allowedChannels)
            );
        }

        // Obter chave do tenant
        $chave = $chave ?? ($_SESSION['chave'] ?? null);
        if (empty($chave)) {
            throw new \RuntimeException('Chave do tenant não definida');
        }

        $templateService = new \App\Services\MessageTemplateService(null, $chave);

        // Renderizar template (locale determinado automaticamente pelo contexto)
        $rendered = $templateService->render($templateSlug, $channel, $context);

        if (!$rendered) {
            throw new \RuntimeException(
                "Template '{$templateSlug}' não encontrado para canal '{$channel}'"
            );
        }

        // Extrair dados do destinatário do contexto
        $cliente = $context['cliente'] ?? [];

        // Montar payload conforme canal
        $payload = match($channel) {
            'email' => [
                'to' => $cliente['email'] ?? '',
                'to_name' => $cliente['nome'] ?? $cliente['razao_social'] ?? '',
                'subject' => $rendered['subject'] ?? '',
                'body' => $rendered['content'],
                'body_text' => $rendered['content_plain'],
            ],
            'whatsapp' => [
                'to' => $cliente['telefone'] ?? $cliente['celular'] ?? '',
                'message' => $rendered['content_plain'],
            ],
            'sms' => [
                'to' => $cliente['telefone'] ?? $cliente['celular'] ?? '',
                'message' => $rendered['content_plain'],
            ],
        };

        // Sem destinatario para o canal nao e erro: cliente sem email/celular
        // simplesmente nao recebe naquele canal.
        if (empty($payload['to'])) {
            return 0;
        }

        // Injetar id_matriz_filial do contexto
        $payload['id_matriz_filial'] = $context['id_matriz_filial']
            ?? $context['empresa']['id']
            ?? $_SESSION['id_matriz_filial']
            ?? null;

        // Enfileirar mensagem
        return queue_message($channel, $payload, $chave, $batchId);
    }
}

// ========================================
// PLANOS HELPERS
// ========================================

if (!function_exists('plano_nome')) {
    /**
     * Obtém o nome do plano pelo código
     *
     * @param string $codigo Código do plano (ex: "P4", "G", "P1")
     * @return string Nome do plano ou código se não encontrado
     */
    function plano_nome(string $codigo): string
    {
        return \App\Config\Planos::getNome($codigo);
    }
}

if (!function_exists('plano_info')) {
    /**
     * Obtém todas as informações de um plano
     *
     * @param string $codigo Código do plano
     * @return array|null Array com informações do plano ou null se não encontrado
     */
    function plano_info(string $codigo): ?array
    {
        return \App\Config\Planos::getPlano($codigo);
    }
}

// ========================================
// CURRENCY HELPERS
// ========================================

if (!function_exists('currency_format')) {
    /**
     * Formata um valor monetário para exibição no front-end
     *
     * Usa a configuração de locale e moeda da empresa ativa na sessão.
     *
     * @param float|int|string|null $value Valor a formatar
     * @param bool $showSymbol Incluir símbolo da moeda (padrão: true)
     * @return string Valor formatado (ex: "R$ 1.234,56")
     *
     * @example
     * currency_format(1234.56)        // "R$ 1.234,56" (pt_BR)
     * currency_format(1234.56, false) // "1.234,56"
     */
    function currency_format(float|int|string|null $value, bool $showSymbol = true): string
    {
        return \App\Helpers\CurrencyHelper::format($value, $showSymbol);
    }
}

if (!function_exists('currency_parse')) {
    /**
     * Converte um valor formatado do front-end para float (formato internacional)
     *
     * Detecta automaticamente o formato baseado no locale da empresa.
     *
     * @param string|float|int|null $value Valor formatado (ex: "R$ 1.234,56")
     * @return float Valor numérico (ex: 1234.56)
     *
     * @example
     * currency_parse("R$ 1.234,56") // 1234.56
     * currency_parse("1.234,56")    // 1234.56
     * currency_parse("$1,234.56")   // 1234.56
     */
    function currency_parse(string|float|int|null $value): float
    {
        return \App\Helpers\CurrencyHelper::parse($value);
    }
}

if (!function_exists('currency_config')) {
    /**
     * Retorna a configuração de moeda da empresa ativa na sessão
     *
     * Útil para passar ao front-end via JSON.
     *
     * @return array {locale, currency, symbol, decimal, thousands, symbolPosition}
     *
     * @example
     * $config = currency_config();
     * // ['locale' => 'pt_BR', 'currency' => 'BRL', 'symbol' => 'R$', ...]
     */
    function currency_config(): array
    {
        return \App\Helpers\CurrencyHelper::getConfig();
    }
}

if (!function_exists('currency_for_input')) {
    /**
     * Formata valor para exibição em input HTML (sem símbolo)
     *
     * @param float|int|string|null $value Valor a formatar
     * @return string Valor formatado sem símbolo
     *
     * @example
     * currency_for_input(1234.56) // "1.234,56" (pt_BR)
     */
    function currency_for_input(float|int|string|null $value): string
    {
        return \App\Helpers\CurrencyHelper::formatForInput($value);
    }
}

if (!function_exists('currency_format_extenso')) {
    /**
     * Formata valor monetário com valor por extenso entre parênteses
     *
     * Usa a configuração de locale e moeda da empresa ativa na sessão.
     *
     * @param float|int|string|null $value Valor a formatar
     * @return string Valor formatado com extenso
     *
     * @example
     * currency_format_extenso(1234.56)
     * // "R$ 1.234,56 (mil duzentos e trinta e quatro reais e cinquenta e seis centavos)"
     */
    function currency_format_extenso(float|int|string|null $value): string
    {
        return \App\Helpers\CurrencyHelper::formatWithWords($value);
    }
}

if (!function_exists('currency_extenso')) {
    /**
     * Retorna apenas o valor por extenso (sem o valor numérico formatado)
     *
     * Usa a configuração de locale e moeda da empresa ativa na sessão.
     *
     * @param float|int|string|null $value Valor a converter
     * @return string Valor por extenso
     *
     * @example
     * currency_extenso(1234.56)
     * // "mil duzentos e trinta e quatro reais e cinquenta e seis centavos"
     */
    function currency_extenso(float|int|string|null $value): string
    {
        return \App\Helpers\CurrencyHelper::toWords($value);
    }
}

// ========================================
// DATE HELPER FUNCTIONS
// ========================================

if (!function_exists('date_config')) {
    /**
     * Retorna a configuração de data da empresa ativa na sessão
     *
     * Útil para passar ao front-end via JSON.
     *
     * @return array {date_format, datetime_format, timezone, app_timezone}
     *
     * @example
     * $config = date_config();
     * // ['date_format' => 'd/m/Y', 'datetime_format' => 'd/m/Y H:i:s', 'timezone' => 'America/Sao_Paulo']
     */
    function date_config(): array
    {
        return \App\Helpers\DateHelper::getConfig();
    }
}

if (!function_exists('format_date')) {
    /**
     * Formata uma data para exibição no front-end
     *
     * @param string|null $date Data no formato internacional (Y-m-d)
     * @return string Data formatada conforme configuração da empresa
     *
     * @example
     * format_date('2024-01-15') // "15/01/2024" (d/m/Y)
     */
    function format_date(?string $date): string
    {
        return \App\Helpers\DateHelper::format($date);
    }
}

if (!function_exists('format_datetime')) {
    /**
     * Formata uma data/hora para exibição no front-end
     *
     * @param string|null $datetime Data/hora no formato internacional (Y-m-d H:i:s)
     * @return string Data/hora formatada conforme configuração da empresa
     *
     * @example
     * format_datetime('2024-01-15 14:30:00') // "15/01/2024 14:30:00" (d/m/Y H:i:s)
     */
    function format_datetime(?string $datetime): string
    {
        return \App\Helpers\DateHelper::formatDateTime($datetime);
    }
}

if (!function_exists('format_operational_datetime')) {
    /**
     * Formata data/hora operacional sem converter timezone.
     *
     * Use para horarios locais escolhidos pelo usuario e gravados no banco como
     * valor operacional: retirada/devolucao, inicio/fim de contrato, checklist,
     * multas, agenda e manutencoes programadas.
     */
    function format_operational_datetime(?string $datetime, bool $withoutSeconds = true, ?string $format = null): string
    {
        return \App\Helpers\DateHelper::formatOperationalDateTime($datetime, $withoutSeconds, $format);
    }
}

if (!function_exists('parse_date')) {
    /**
     * Converte uma data do formato local para formato internacional
     *
     * Use ao salvar datas no banco de dados.
     *
     * @param string|null $date Data no formato local (ex: "15/01/2024")
     * @return string|null Data no formato internacional (Y-m-d)
     *
     * @example
     * parse_date('15/01/2024') // "2024-01-15"
     */
    function parse_date(?string $date): ?string
    {
        return \App\Helpers\DateHelper::parse($date);
    }
}

if (!function_exists('parse_datetime')) {
    /**
     * Converte uma data/hora do formato local para formato internacional
     *
     * Use ao salvar datas/horas no banco de dados.
     *
     * @param string|null $datetime Data/hora no formato local
     * @return string|null Data/hora no formato internacional (Y-m-d H:i:s)
     *
     * @example
     * parse_datetime('15/01/2024 14:30:00') // "2024-01-15 14:30:00"
     */
    function parse_datetime(?string $datetime): ?string
    {
        return \App\Helpers\DateHelper::parseDateTime($datetime);
    }
}

// ========================================
// I18N / TRANSLATION HELPERS
// ========================================

if (!function_exists('t')) {
    /**
     * Traduz uma chave de tradução (função principal)
     *
     * Função principal para internacionalização (i18n). Busca a tradução
     * nos arquivos de idioma e substitui variáveis.
     *
     * @param string $key Chave no formato 'arquivo.chave' ou 'arquivo.chave.subchave'
     * @param array $replace Variáveis para substituição no formato [:nome => valor]
     * @param string|null $locale Locale específico (opcional, usa atual se não informado)
     * @return string Texto traduzido ou a própria chave se não encontrado
     *
     * @example
     * t('common.buttons.save')                          // "Salvar"
     * t('messages.greeting', ['nome' => 'João'])        // "Olá, João!"
     * t('common.buttons.save', [], 'en_US')             // "Save"
     */
    function t(string $key, array $replace = [], ?string $locale = null): string
    {
        return \App\I18n\Translator::getInstance()->get($key, $replace, $locale);
    }
}

if (!function_exists('__')) {
    /**
     * Alias curto para t(), mantido para compatibilidade com testes e views.
     */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return t($key, $replace, $locale);
    }
}

if (!function_exists('t_choice')) {
    /**
     * Traduz uma chave com pluralização
     *
     * @param string $key Chave de tradução
     * @param int $count Quantidade para pluralização
     * @param array $replace Variáveis para substituição
     * @param string|null $locale Locale específico
     * @return string Texto traduzido
     *
     * @example
     * t_choice('messages.items', 1)  // "1 item"
     * t_choice('messages.items', 5)  // "5 itens"
     */
    function t_choice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        $replace['count'] = $count;
        $translation = t($key, $replace, $locale);

        // Se a tradução contiver |, fazer pluralização simples
        if (str_contains($translation, '|')) {
            $parts = explode('|', $translation);
            return $count === 1 ? $parts[0] : ($parts[1] ?? $parts[0]);
        }

        return $translation;
    }
}

if (!function_exists('current_locale')) {
    /**
     * Retorna o locale atual da interface
     *
     * @return string Locale atual (ex: 'pt_BR', 'en_US')
     *
     * @example
     * $locale = current_locale(); // "pt_BR"
     */
    function current_locale(): string
    {
        return \App\I18n\Translator::getInstance()->getLocale();
    }
}

if (!function_exists('set_locale')) {
    /**
     * Define o locale da interface
     *
     * Altera o idioma da interface e persiste na sessão.
     *
     * @param string $locale Código do locale (ex: 'pt_BR', 'en_US', 'es_ES')
     * @return void
     * @throws \InvalidArgumentException Se locale não for suportado
     *
     * @example
     * set_locale('en_US'); // Muda para inglês
     */
    function set_locale(string $locale): void
    {
        \App\I18n\Translator::getInstance()->setLocale($locale);
    }
}

if (!function_exists('locale_info')) {
    /**
     * Retorna informações sobre o locale atual ou especificado
     *
     * @param string|null $locale Locale (opcional, usa atual se não informado)
     * @return array|null Array com name, flag, code ou null se não suportado
     *
     * @example
     * $info = locale_info();
     * // ['name' => 'Português (Brasil)', 'flag' => '🇧🇷', 'code' => 'pt-BR']
     */
    function locale_info(?string $locale = null): ?array
    {
        return \App\I18n\Translator::getInstance()->getLocaleInfo($locale);
    }
}

if (!function_exists('supported_locales')) {
    /**
     * Retorna lista de locales suportados pelo sistema
     *
     * Útil para popular selects de idioma.
     *
     * @return array Array de locales com suas informações
     *
     * @example
     * $locales = supported_locales();
     * // ['pt_BR' => ['name' => 'Português (Brasil)', ...], ...]
     */
    function supported_locales(): array
    {
        return \App\I18n\Translator::getInstance()->getSupportedLocales();
    }
}

if (!function_exists('is_locale_supported')) {
    /**
     * Verifica se um locale é suportado pelo sistema
     *
     * @param string $locale Código do locale
     * @return bool True se suportado
     *
     * @example
     * is_locale_supported('pt_BR'); // true
     * is_locale_supported('xx_XX'); // false
     */
    function is_locale_supported(string $locale): bool
    {
        return \App\I18n\Translator::getInstance()->isSupported($locale);
    }
}

if (!function_exists('has_translation')) {
    /**
     * Verifica se uma tradução existe
     *
     * @param string $key Chave de tradução
     * @param string|null $locale Locale específico
     * @return bool True se tradução existe
     *
     * @example
     * has_translation('common.buttons.save'); // true
     * has_translation('inexistente.chave');   // false
     */
    function has_translation(string $key, ?string $locale = null): bool
    {
        return \App\I18n\Translator::getInstance()->has($key, $locale);
    }
}

// ========================================
// UI HELPERS
// ========================================

if (!function_exists('aviso')) {
    /**
     * Gera icone de ajuda [?] com popover de instrucao
     *
     * Sempre usar com sintaxe {!! !!} (raw output).
     *
     * @param string $texto Texto ou HTML da instrucao
     * @return string HTML do componente
     *
     * @example
     * {!! aviso(t('modules.financeiro.hints.valor_total')) !!}
     * {!! aviso('Texto explicativo aqui') !!}
     */
    function aviso(string $texto): string
    {
        static $contador = 0;
        $contador++;
        $id = 'helpHint' . $contador;

        return '<span class="help-hint" data-popover="' . $id . '">?</span>' .
               '<div id="' . $id . '" class="help-hint-popover">' . $texto . '</div>';
    }
}

// ========================================
// ENCRYPTION HELPERS
// ========================================

if (!function_exists('encrypt')) {
    /**
     * Criptografa uma string usando AES-256-CBC
     *
     * Usa a APP_KEY do ambiente como base para a chave de criptografia.
     * Compativel com decrypt().
     *
     * @param string $data Dados a criptografar
     * @return string Base64 encoded (IV + ciphertext)
     *
     * @example
     * $encrypted = encrypt('minha-api-key-secreta');
     * $decrypted = decrypt($encrypted); // 'minha-api-key-secreta'
     */
    function encrypt(string $data): string
    {
        $appKey = \App\Core\Database::env('APP_KEY', 'default-key-change-me');
        $key = hash('sha256', $appKey, true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }
}

if (!function_exists('decrypt')) {
    /**
     * Descriptografa uma string criptografada com encrypt()
     *
     * @param string $encrypted Base64 encoded (IV + ciphertext)
     * @return string|null Dados originais ou null se falhar
     *
     * @example
     * $decrypted = decrypt($encrypted);
     * if ($decrypted === null) {
     *     // Falha na descriptografia
     * }
     */
    function decrypt(string $encrypted): ?string
    {
        $data = base64_decode($encrypted);
        if ($data === false || strlen($data) < 17) {
            return null;
        }

        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $appKey = \App\Core\Database::env('APP_KEY', 'default-key-change-me');
        $key = hash('sha256', $appKey, true);
        $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : null;
    }
}
