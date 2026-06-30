<?php

namespace App\Services\NFSe;

/**
 * Gestao de Certificado Digital para NFS-e
 *
 * Handles upload, validacao, extracao PEM e gerenciamento
 * de certificados .pfx/.p12 para assinatura e mTLS.
 *
 * Storage: storage/certificates/
 * Formato nome: {chave}_{id_matriz_filial}_{timestamp}.{extensao}
 * Permissao: 0600
 */
class NFSeCertificado
{
    private const SENHA_FORMATO_ATUAL = 'atual';
    private const SENHA_FORMATO_LEGADO = 'legado';
    private const LEGACY_PASSWORD_KEY = 'nfse_7carros_locadora_key';

    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 3) . '/storage/certificates';
    }

    /**
     * Upload de certificado .pfx/.p12
     *
     * @param array $file $_FILES['certificado']
     * @param int $idMatrizFilial ID da filial
     * @param string $chave Chave do tenant
     * @param string $senha Senha do certificado
     * @return array ['sucesso' => bool, 'arquivo' => string, 'dados' => array, 'mensagem' => string]
     */
    public function upload(array $file, int $idMatrizFilial, string $chave, string $senha): array
    {
        // Validar extensao
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pfx', 'p12'], true)) {
            return ['sucesso' => false, 'mensagem' => 'Formato inválido. Envie um arquivo .pfx ou .p12.'];
        }

        // Ler conteudo
        $pfxContent = file_get_contents($file['tmp_name']);
        if ($pfxContent === false) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao ler o arquivo enviado.'];
        }

        // Validar certificado e senha
        $validacao = $this->validar($pfxContent, $senha);
        if (!$validacao['sucesso']) {
            return $validacao;
        }

        // Criar diretorio de certificados
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0700, true);
        }

        // Salvar arquivo
        $nomeArquivo = $this->gerarNomeArquivo($chave, $idMatrizFilial, $ext);
        $caminhoCompleto = $this->caminhoCertificado($nomeArquivo);

        if (file_put_contents($caminhoCompleto, $pfxContent) === false) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao salvar o certificado.'];
        }

        chmod($caminhoCompleto, 0600);

        // Extrair dados
        $dados = $this->lerDados($pfxContent, $senha);

        return [
            'sucesso' => true,
            'arquivo' => $nomeArquivo,
            'dados' => $dados,
            'senha_criptografada' => encrypt($senha),
            'mensagem' => 'Certificado enviado com sucesso.',
        ];
    }

    /**
     * Valida certificado e senha
     */
    public function validar(string $pfxContent, string $senha): array
    {
        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            return ['sucesso' => false, 'mensagem' => 'Senha do certificado incorreta ou arquivo inválido.'];
        }

        if (empty($certs['cert']) || empty($certs['pkey'])) {
            return ['sucesso' => false, 'mensagem' => 'Certificado não contém chave pública ou privada.'];
        }

        // Verificar validade
        $certData = openssl_x509_parse($certs['cert']);
        if (!$certData) {
            return ['sucesso' => false, 'mensagem' => 'Não foi possível ler os dados do certificado.'];
        }

        $validoAte = $certData['validTo_time_t'] ?? 0;
        if ($validoAte < \App\Helpers\DateHelper::timestamp()) {
            return ['sucesso' => false, 'mensagem' => 'O certificado digital está vencido.'];
        }

        return ['sucesso' => true];
    }

    /**
     * Extrai dados do certificado
     */
    public function lerDados(string $pfxContent, string $senha): array
    {
        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            return [];
        }

        $certData = openssl_x509_parse($certs['cert']);
        if (!$certData) {
            return [];
        }

        // Extrair CNPJ do subject CN (formato: "RAZAO SOCIAL:CNPJ")
        $cn = $certData['subject']['CN'] ?? '';
        $cnpj = '';
        if (preg_match('/(\d{14})/', $cn, $matches)) {
            $cnpj = $matches[1];
        }

        // Razao social
        $razaoSocial = $cn;
        if (str_contains($cn, ':')) {
            $razaoSocial = trim(explode(':', $cn)[0]);
        }

        return [
            'cnpj' => $cnpj,
            'razao_social' => $razaoSocial,
            'valido_de' => \App\Helpers\DateHelper::formatTimestamp((int) ($certData['validFrom_time_t'] ?? 0), 'Y-m-d'),
            'valido_ate' => \App\Helpers\DateHelper::formatTimestamp((int) ($certData['validTo_time_t'] ?? 0), 'Y-m-d'),
            'emissor' => $certData['issuer']['CN'] ?? '',
            'serial' => $certData['serialNumberHex'] ?? '',
        ];
    }

    /**
     * Extrai chave privada e certificado publico para PEM temporarios
     *
     * Usados para cURL (mTLS). Devem ser removidos apos uso com limparPEM().
     *
     * @return array ['certPath' => string, 'keyPath' => string]
     */
    public function extrairPEM(string $chave, string $arquivo, string $senhaCriptografada): array
    {
        $caminhoCompleto = $this->caminhoCertificado($arquivo);

        if (!file_exists($caminhoCompleto)) {
            throw new \RuntimeException('Arquivo do certificado não encontrado.');
        }

        $pfxContent = file_get_contents($caminhoCompleto);
        if ($pfxContent === false) {
            throw new \RuntimeException('Erro ao ler o arquivo do certificado.');
        }

        $resultado = $this->lerCertificadoComSenhaCriptografada($pfxContent, $senhaCriptografada);
        if (!$resultado['sucesso']) {
            throw new \RuntimeException('Erro ao ler o certificado. Verifique a senha.');
        }
        $certs = $resultado['certs'];

        // Exportar PEM
        $pemPublica = '';
        openssl_x509_export($certs['cert'], $pemPublica);

        $pemPrivada = '';
        openssl_pkey_export($certs['pkey'], $pemPrivada);

        // Salvar em /tmp com nomes unicos
        $certPath = sys_get_temp_dir() . '/nfse_cert_' . uniqid() . '.pem';
        $keyPath = sys_get_temp_dir() . '/nfse_key_' . uniqid() . '.pem';

        file_put_contents($certPath, $pemPublica);
        file_put_contents($keyPath, $pemPrivada);

        chmod($certPath, 0600);
        chmod($keyPath, 0600);

        return [
            'certPath' => $certPath,
            'keyPath' => $keyPath,
        ];
    }

    /**
     * Remove arquivos PEM temporarios
     */
    public function limparPEM(string $certPath, string $keyPath): void
    {
        if (file_exists($certPath)) {
            unlink($certPath);
        }
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
    }

    /**
     * Verifica se certificado nao expirou
     */
    public function isValido(string $chave, string $arquivo, string $senhaCriptografada): bool
    {
        $analise = $this->analisar($chave, $arquivo, $senhaCriptografada);
        return $analise['status'] === 'valido';
    }

    /**
     * Dias ate o certificado expirar
     */
    public function diasParaExpirar(string $chave, string $arquivo, string $senhaCriptografada): int
    {
        $analise = $this->analisar($chave, $arquivo, $senhaCriptografada);
        return (int) ($analise['dias'] ?? 0);
    }

    /**
     * Analisa o certificado salvo e diferencia vencimento real de falhas de leitura/senha.
     *
     * @return array{
     *     status:string,
     *     valido:bool,
     *     dias:?int,
     *     validade:?string,
     *     mensagem:string,
     *     formato_senha:?string,
     *     senha?:string
     * }
     */
    public function analisar(string $chave, string $arquivo, string $senhaCriptografada, bool $incluirSenha = false): array
    {
        $caminhoCompleto = $this->caminhoCertificado($arquivo);
        if (!file_exists($caminhoCompleto)) {
            return $this->analiseErro('arquivo_ausente', 'Arquivo do certificado não encontrado.');
        }

        $pfxContent = file_get_contents($caminhoCompleto);
        if ($pfxContent === false) {
            return $this->analiseErro('leitura_invalida', 'Erro ao ler o arquivo do certificado.');
        }

        $resultado = $this->lerCertificadoComSenhaCriptografada($pfxContent, $senhaCriptografada);
        if (!$resultado['sucesso']) {
            $status = $resultado['possui_senha'] ? 'senha_invalida' : 'descriptografia_invalida';
            $mensagem = $resultado['possui_senha']
                ? 'Senha do certificado incorreta ou arquivo inválido.'
                : 'Erro ao descriptografar a senha do certificado.';

            return $this->analiseErro($status, $mensagem);
        }

        $certData = openssl_x509_parse($resultado['certs']['cert']);
        if (!$certData) {
            return $this->analiseErro('leitura_invalida', 'Não foi possível ler os dados do certificado.');
        }

        $validoAte = (int) ($certData['validTo_time_t'] ?? 0);
        $diff = $validoAte - \App\Helpers\DateHelper::timestamp();
        $dias = max(0, (int) floor($diff / 86400));
        $status = $diff > 0 ? 'valido' : 'vencido';

        $analise = [
            'status' => $status,
            'valido' => $status === 'valido',
            'dias' => $dias,
            'validade' => $validoAte > 0 ? \App\Helpers\DateHelper::formatTimestamp($validoAte, 'Y-m-d') : null,
            'mensagem' => $status === 'valido'
                ? 'Certificado digital válido.'
                : 'Certificado digital vencido.',
            'formato_senha' => $resultado['formato_senha'],
        ];

        if ($incluirSenha) {
            $analise['senha'] = $resultado['senha'];
        }

        return $analise;
    }

    /**
     * Remove arquivo de certificado
     */
    public function remover(string $chave, string $arquivo): bool
    {
        $caminhoCompleto = $this->caminhoCertificado($arquivo);

        if (file_exists($caminhoCompleto)) {
            return unlink($caminhoCompleto);
        }

        return true;
    }

    private function gerarNomeArquivo(string $chave, int $idMatrizFilial, string $ext): string
    {
        $timestamp = \App\Helpers\DateHelper::timestamp();

        do {
            $nomeArquivo = $chave . '_' . $idMatrizFilial . '_' . $timestamp . '.' . $ext;
            $timestamp++;
        } while (file_exists($this->caminhoCertificado($nomeArquivo)));

        return $nomeArquivo;
    }

    private function caminhoCertificado(string $arquivo): string
    {
        return $this->basePath . '/' . basename($arquivo);
    }

    private function analiseErro(string $status, string $mensagem): array
    {
        return [
            'status' => $status,
            'valido' => false,
            'dias' => null,
            'validade' => null,
            'mensagem' => $mensagem,
            'formato_senha' => null,
        ];
    }

    private function lerCertificadoComSenhaCriptografada(string $pfxContent, string $senhaCriptografada): array
    {
        $senhas = $this->senhasPossiveis($senhaCriptografada);
        if (empty($senhas)) {
            return ['sucesso' => false, 'possui_senha' => false];
        }

        foreach ($senhas as $candidate) {
            $certs = [];
            if (openssl_pkcs12_read($pfxContent, $certs, $candidate['senha'])) {
                return [
                    'sucesso' => true,
                    'possui_senha' => true,
                    'certs' => $certs,
                    'senha' => $candidate['senha'],
                    'formato_senha' => $candidate['formato'],
                ];
            }
        }

        return ['sucesso' => false, 'possui_senha' => true];
    }

    private function senhasPossiveis(string $senhaCriptografada): array
    {
        $senhas = [];

        $senhaAtual = decrypt($senhaCriptografada);
        if ($senhaAtual !== null) {
            $senhas[] = ['senha' => $senhaAtual, 'formato' => self::SENHA_FORMATO_ATUAL];
        }

        $senhaLegado = $this->descriptografarSenhaLegado($senhaCriptografada);
        if ($senhaLegado !== null && $senhaLegado !== $senhaAtual) {
            $senhas[] = ['senha' => $senhaLegado, 'formato' => self::SENHA_FORMATO_LEGADO];
        }

        return $senhas;
    }

    private function descriptografarSenhaLegado(string $valorBase64): ?string
    {
        $data = base64_decode($valorBase64, true);
        if ($data === false || strlen($data) < 17) {
            return null;
        }

        $key = hash('sha256', self::LEGACY_PASSWORD_KEY, true);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $senha = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

        return $senha !== false ? $senha : null;
    }
}
