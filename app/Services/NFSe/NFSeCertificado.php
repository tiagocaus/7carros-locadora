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
    private string $basePath;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__, 3) . '/storage/certificates';
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
        if ($validoAte < time()) {
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
            'valido_de' => date('Y-m-d', $certData['validFrom_time_t'] ?? 0),
            'valido_ate' => date('Y-m-d', $certData['validTo_time_t'] ?? 0),
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

        $senha = decrypt($senhaCriptografada);
        if ($senha === null) {
            throw new \RuntimeException('Erro ao descriptografar a senha do certificado.');
        }

        $pfxContent = file_get_contents($caminhoCompleto);
        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            throw new \RuntimeException('Erro ao ler o certificado. Verifique a senha.');
        }

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
        try {
            $caminhoCompleto = $this->caminhoCertificado($arquivo);
            $senha = decrypt($senhaCriptografada);
            if ($senha === null) {
                return false;
            }

            $pfxContent = file_get_contents($caminhoCompleto);
            $certs = [];
            if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
                return false;
            }

            $certData = openssl_x509_parse($certs['cert']);
            return ($certData['validTo_time_t'] ?? 0) > time();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Dias ate o certificado expirar
     */
    public function diasParaExpirar(string $chave, string $arquivo, string $senhaCriptografada): int
    {
        try {
            $caminhoCompleto = $this->caminhoCertificado($arquivo);
            $senha = decrypt($senhaCriptografada);
            if ($senha === null) {
                return 0;
            }

            $pfxContent = file_get_contents($caminhoCompleto);
            $certs = [];
            if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
                return 0;
            }

            $certData = openssl_x509_parse($certs['cert']);
            $validoAte = $certData['validTo_time_t'] ?? 0;

            $diff = $validoAte - time();
            return max(0, (int) floor($diff / 86400));
        } catch (\Throwable) {
            return 0;
        }
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
        $timestamp = time();

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
}
