<?php
/**
 * SiteApi — Classe que faz chamadas a API do sistema com cache local em arquivo
 */
class SiteApi
{
    /** @var array */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Dados completos do site — filiais, grupos, precos, horarios, empresa
     */
    public function getDadosSite(): array
    {
        return $this->get('/api/public/dados-site', [], $this->config['cache_ttl']);
    }

    /**
     * Conteudos editaveis (textos, SEO, integracoes, banners, links)
     */
    public function getConteudos(string $idioma): array
    {
        return $this->get('/api/public/conteudos', ['idioma' => $idioma], $this->config['cache_ttl']);
    }

    /**
     * Disponibilidade de grupos por filial/periodo — SEM cache (depende do input do usuario).
     * $params: ['id_matriz_filial'=>int, 'data_saida'=>Y-m-d, 'hora_saida'=>H:i, 'data_prevista'=>Y-m-d, 'hora_devolucao'=>H:i]
     */
    public function getDisponibilidade(array $params): array
    {
        $params['chave'] = $this->config['chave'];
        $url = $this->config['api_url'] . '/api/public/disponibilidade?' . http_build_query($params);
        return $this->request('GET', $url);
    }

    /**
     * Verifica se existe cliente com esse CPF/CNPJ no tenant. SEM expor dados.
     */
    public function clienteExiste(string $documento): array
    {
        $url = $this->config['api_url'] . '/api/public/cliente-existe?' . http_build_query([
            'chave' => $this->config['chave'],
            'documento' => $documento,
        ]);
        return $this->request('GET', $url);
    }

    /**
     * Autentica cliente. Retorna {success, cliente:{id,nome}} ou erro.
     */
    public function clienteLogin(string $usuario, string $senha): array
    {
        return $this->post('/api/public/cliente-login', [
            'usuario' => $usuario,
            'senha' => $senha,
        ]);
    }

    /**
     * Solicita reset de senha — gera senha nova e envia por email.
     * Resposta sempre neutra para evitar enumeration.
     */
    public function clienteSenhaReset(string $documento): array
    {
        return $this->post('/api/public/cliente-senha-reset', [
            'documento' => $documento,
        ]);
    }

    /**
     * Flags runtime (manutencao, reserva_online, etc) — SEM cache.
     * Usado em cada page load para refletir mudanças do backoffice imediatamente.
     */
    public function getStatus(): array
    {
        $url = $this->config['api_url'] . '/api/public/status?' . http_build_query([
            'chave' => $this->config['chave'],
        ]);
        return $this->request('GET', $url);
    }

    /**
     * Criar reserva — SEM cache, chamada direta
     */
    public function criarReserva(array $dados): array
    {
        return $this->post('/api/public/reserva', $dados);
    }

    /**
     * Enviar formulario de contato — SEM cache
     */
    public function enviarContato(array $dados): array
    {
        return $this->post('/api/public/contato', $dados);
    }

    /**
     * Limpar cache local
     */
    public function limparCache(): void
    {
        $dir = $this->config['cache_dir'];
        if (is_dir($dir)) {
            array_map('unlink', glob("$dir*.cache"));
        }
    }

    private function get(string $endpoint, array $params, int $cacheTtl): array
    {
        // 'deploy' muda a cada publicação — invalida cache automaticamente
        $cacheKey = md5($endpoint . json_encode($params) . ($this->config['deploy'] ?? ''));
        $cacheFile = $this->config['cache_dir'] . $cacheKey . '.cache';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data !== null) {
                return $data;
            }
        }

        $params['chave'] = $this->config['chave'];
        $url = $this->config['api_url'] . $endpoint . '?' . http_build_query($params);
        $response = $this->request('GET', $url);

        if (!empty($response)) {
            if (!is_dir($this->config['cache_dir'])) {
                mkdir($this->config['cache_dir'], 0755, true);
            }
            file_put_contents($cacheFile, json_encode($response));
        }

        return $response;
    }

    private function post(string $endpoint, array $dados): array
    {
        $dados['chave'] = $this->config['chave'];
        $url = $this->config['api_url'] . $endpoint;
        return $this->request('POST', $url, $dados);
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Site-Token: ' . $this->config['api_token'],
            ],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }
}
