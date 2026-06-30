<?php

namespace App\Models;

use App\Core\Auth;
use App\Helpers\FileHelper;
use App\Helpers\ImageHelper;

/**
 * Model Assinatura
 *
 * Gerencia assinaturas digitais de contratos e locacoes.
 * Armazena assinaturas em arquivos (nao base64) para melhor performance.
 */
class Assinatura extends Model
{
    /**
     * Busca assinatura por ID
     *
     * @param int $id ID da assinatura
     * @return array|null
     */
    public function buscarPorId(int $id): ?array
    {
        $assinatura = $this->qb
            ->table('assinaturas')
            ->where('id', '=', $id)
            ->first();

        if ($assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinatura;
    }

    /**
     * Busca assinatura por contrato
     *
     * @param int $idContrato ID do contrato
     * @param string $tipo Tipo de assinatura (cliente, testemunha, fiador, avalista)
     * @return array|null
     */
    public function buscarPorContrato(int $idContrato, string $tipo = 'cliente', ?string $chave = null): ?array
    {
        $query = $this->qb
            ->table('assinaturas')
            ->where('id_contrato', '=', $idContrato)
            ->where('tipo', '=', $tipo)
            ->orderBy('created_at', 'DESC');

        if ($chave !== null) {
            $query->withChave($chave);
        }

        $assinatura = $query->first();

        if ($assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinatura;
    }

    /**
     * Busca assinatura por locacao
     *
     * @param int $idLocacao ID da locacao
     * @param string $tipo Tipo de assinatura
     * @return array|null
     */
    public function buscarPorLocacao(int $idLocacao, string $tipo = 'cliente', ?string $chave = null): ?array
    {
        $query = $this->qb
            ->table('assinaturas')
            ->where('id_locacao', '=', $idLocacao)
            ->where('tipo', '=', $tipo)
            ->orderBy('created_at', 'DESC');

        if ($chave !== null) {
            $query->withChave($chave);
        }

        $assinatura = $query->first();

        if ($assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinatura;
    }

    /**
     * Lista todas assinaturas de um contrato
     *
     * @param int $idContrato ID do contrato
     * @return array
     */
    public function listarPorContrato(int $idContrato): array
    {
        $assinaturas = $this->qb
            ->table('assinaturas')
            ->where('id_contrato', '=', $idContrato)
            ->orderBy('created_at', 'ASC')
            ->get();

        foreach ($assinaturas as &$assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinaturas;
    }

    /**
     * Lista todas assinaturas de uma locacao
     *
     * @param int $idLocacao ID da locacao
     * @return array
     */
    public function listarPorLocacao(int $idLocacao): array
    {
        $assinaturas = $this->qb
            ->table('assinaturas')
            ->where('id_locacao', '=', $idLocacao)
            ->orderBy('created_at', 'ASC')
            ->get();

        foreach ($assinaturas as &$assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinaturas;
    }

    /**
     * Lista todas assinaturas de um cliente
     *
     * @param int $idCliente ID do cliente
     * @return array
     */
    public function listarPorCliente(int $idCliente): array
    {
        $assinaturas = $this->qb
            ->table('assinaturas')
            ->where('id_cliente', '=', $idCliente)
            ->orderBy('created_at', 'DESC')
            ->get();

        foreach ($assinaturas as &$assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinaturas;
    }

    /**
     * Salva uma nova assinatura
     *
     * @param array $dados Dados da assinatura:
     *   - base64: string (obrigatorio) - Imagem em base64
     *   - id_contrato: int|null
     *   - id_locacao: int|null
     *   - id_cliente: int|null
     *   - ip_address: string (obrigatorio)
     *   - user_agent: string|null
     *   - latitude: float|null
     *   - longitude: float|null
     *   - tipo: string (cliente|testemunha|fiador|avalista)
     *   - observacao: string|null
     *   - chave: string|null (usa Auth::chave() se nao fornecido)
     * @return int ID da assinatura criada
     * @throws \InvalidArgumentException Se dados obrigatorios faltarem
     */
    public function salvar(array $dados): int
    {
        // Validacoes
        if (empty($dados['base64'])) {
            throw new \InvalidArgumentException('Assinatura (base64) e obrigatoria');
        }

        if (empty($dados['id_contrato']) && empty($dados['id_locacao'])) {
            throw new \InvalidArgumentException('ID do contrato ou locacao e obrigatorio');
        }

        $chave = $dados['chave'] ?? Auth::chave();
        if (!$chave) {
            throw new \InvalidArgumentException('Chave do tenant e obrigatoria');
        }

        // Salva arquivo usando ImageHelper (converte para WebP automaticamente)
        $filename = ImageHelper::save($dados['base64'], 'assinatura', chave: $chave);
        if (!$filename) {
            throw new \RuntimeException('Erro ao salvar arquivo de assinatura');
        }

        // Gera hash do arquivo para verificacao de integridade
        $filepath = FileHelper::getPath($filename, $chave);
        $hashArquivo = file_exists($filepath) ? hash_file('sha256', $filepath) : null;

        // Em rotas publicas pode nao existir sessao; use a chave do registro assinado.
        $query = $this->qb->table('assinaturas');
        if ($chave) {
            $query->withChave($chave);
        }

        return $query->insert([
                'id_contrato' => $dados['id_contrato'] ?? null,
                'id_locacao' => $dados['id_locacao'] ?? null,
                'id_cliente' => $dados['id_cliente'] ?? null,
                'arquivo' => $filename,
                'hash_arquivo' => $hashArquivo,
                'ip_address' => $dados['ip_address'] ?? '0.0.0.0',
                'user_agent' => $dados['user_agent'] ?? null,
                'latitude' => $dados['latitude'] ?? null,
                'longitude' => $dados['longitude'] ?? null,
                'tipo' => $dados['tipo'] ?? 'cliente',
                'observacao' => $dados['observacao'] ?? null,
                'created_at' => now(),
            ]);
    }

    /**
     * Exclui uma assinatura (registro e arquivo)
     *
     * @param int $id ID da assinatura
     * @return bool
     */
    public function excluir(int $id): bool
    {
        $assinatura = $this->buscarPorId($id);
        if (!$assinatura) {
            return false;
        }

        // Remove arquivo
        if (!empty($assinatura['arquivo'])) {
            FileHelper::delete($assinatura['arquivo'], $assinatura['chave']);
        }

        // Remove registro
        return $this->qb
            ->table('assinaturas')
            ->where('id', '=', $id)
            ->delete() > 0;
    }

    /**
     * Exclui assinaturas de um contrato
     *
     * @param int $idContrato ID do contrato
     * @return int Quantidade de registros excluidos
     */
    public function excluirPorContrato(int $idContrato): int
    {
        $assinaturas = $this->listarPorContrato($idContrato);
        $count = 0;

        foreach ($assinaturas as $assinatura) {
            if ($this->excluir($assinatura['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Exclui assinaturas de uma locacao
     *
     * @param int $idLocacao ID da locacao
     * @return int Quantidade de registros excluidos
     */
    public function excluirPorLocacao(int $idLocacao): int
    {
        $assinaturas = $this->listarPorLocacao($idLocacao);
        $count = 0;

        foreach ($assinaturas as $assinatura) {
            if ($this->excluir($assinatura['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Verifica integridade do arquivo de assinatura
     *
     * @param int $id ID da assinatura
     * @return bool True se arquivo integro
     */
    public function verificarIntegridade(int $id): bool
    {
        $assinatura = $this->qb
            ->table('assinaturas')
            ->where('id', '=', $id)
            ->first();

        if (!$assinatura || empty($assinatura['hash_arquivo'])) {
            return false;
        }

        $filepath = FileHelper::getPath($assinatura['arquivo'], $assinatura['chave']);
        if (!file_exists($filepath)) {
            return false;
        }

        $currentHash = hash_file('sha256', $filepath);
        return hash_equals($assinatura['hash_arquivo'], $currentHash);
    }

    /**
     * Gera URL publica para o arquivo de assinatura
     *
     * @param array $assinatura Dados da assinatura
     * @return string URL publica ou string vazia
     */
    public function getUrl(array $assinatura): string
    {
        if (empty($assinatura['arquivo']) || empty($assinatura['chave'])) {
            return '';
        }

        return FileHelper::url($assinatura['arquivo'], $assinatura['chave']);
    }

    /**
     * Verifica se contrato tem assinatura
     *
     * @param int $idContrato ID do contrato
     * @return bool
     */
    public function contratoTemAssinatura(int $idContrato, ?string $chave = null): bool
    {
        $query = $this->qb
            ->table('assinaturas')
            ->where('id_contrato', '=', $idContrato);

        if ($chave !== null) {
            $query->withChave($chave);
        }

        return $query->count() > 0;
    }

    /**
     * Verifica se locacao tem assinatura
     *
     * @param int $idLocacao ID da locacao
     * @return bool
     */
    public function locacaoTemAssinatura(int $idLocacao, ?string $chave = null): bool
    {
        $query = $this->qb
            ->table('assinaturas')
            ->where('id_locacao', '=', $idLocacao);

        if ($chave !== null) {
            $query->withChave($chave);
        }

        return $query->count() > 0;
    }

    /**
     * Registra verificacao de assinatura
     *
     * @param int $id ID da assinatura
     * @return bool
     */
    public function registrarVerificacao(int $id): bool
    {
        return $this->qb
            ->table('assinaturas')
            ->where('id', '=', $id)
            ->update([
                'verificado_em' => now()
            ]) > 0;
    }

    /**
     * Gera token de verificacao para assinatura
     *
     * @param int $id ID da assinatura
     * @return string|null Token gerado ou null se falhar
     */
    public function gerarTokenVerificacao(int $id): ?string
    {
        $token = bin2hex(random_bytes(32));

        $updated = $this->qb
            ->table('assinaturas')
            ->where('id', '=', $id)
            ->update([
                'token_verificacao' => $token
            ]);

        return $updated > 0 ? $token : null;
    }

    /**
     * Busca assinatura por token de verificacao
     *
     * @param string $token Token de verificacao
     * @return array|null
     */
    public function buscarPorToken(string $token): ?array
    {
        $assinatura = $this->qb
            ->table('assinaturas')
            ->where('token_verificacao', '=', $token)
            ->first();

        if ($assinatura) {
            $assinatura['url'] = $this->getUrl($assinatura);
        }

        return $assinatura;
    }
}
