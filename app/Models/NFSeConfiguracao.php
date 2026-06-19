<?php

namespace App\Models;

use App\Traits\Auditable;

/**
 * Model NFSeConfiguracao
 *
 * Gerencia configuracoes de NFS-e por empresa/filial.
 * Cada matriz_filial tem uma configuracao independente.
 */
class NFSeConfiguracao extends Model
{
    use Auditable;

    protected function getEntidadeAuditoria(): string
    {
        return 'a configuracao de NFS-e';
    }

    protected function getCampoIdentificador(): string
    {
        return 'id_matriz_filial';
    }

    /**
     * Busca configuracao por matriz_filial
     */
    public function buscarPorMatrizFilial(int $idMatrizFilial): ?array
    {
        return $this->qb
            ->table('nfse_configuracoes')
            ->where('id_matriz_filial', '=', $idMatrizFilial)
            ->first();
    }

    /**
     * Salva configuracao (insert ou update)
     *
     * @return int ID do registro
     */
    public function salvar(int $idMatrizFilial, string $chave, array $dados): int
    {
        $existing = $this->buscarPorMatrizFilial($idMatrizFilial);
        $ativo = $dados['ativo'] ?? 'N';
        $serie = trim((string) ($dados['serie'] ?? ''));

        if ($ativo === 'S' && $serie === '') {
            throw new \InvalidArgumentException('Série da NFS-e é obrigatória quando a emissão está ativa.');
        }

        $campos = [
            'ativo' => $ativo,
            'ambiente' => (int) ($dados['ambiente'] ?? 2),
            'tipo_emissao' => $dados['tipo_emissao'] ?? 'nacional',
            'serie' => $serie !== '' ? $serie : null,
            'emissao_auto' => $dados['emissao_auto'] ?? 'N',
            'enviar_email' => $dados['enviar_email'] ?? 'S',
            'codigo_municipio' => $dados['codigo_municipio'] ?? null,
            'codigo_servico' => $dados['codigo_servico'] ?? '1.1101.11',
            'descricao_servico' => $dados['descricao_servico'] ?? null,
            'regime_tributario' => (int) ($dados['regime_tributario'] ?? 1),
            'reg_apuracao_sn' => (int) ($dados['reg_apuracao_sn'] ?? 1),
            'trib_issqn' => (int) ($dados['trib_issqn'] ?? 4),
            'aliquota_iss' => (float) ($dados['aliquota_iss'] ?? 0),
            'exigibilidade_iss' => (int) ($dados['exigibilidade_iss'] ?? 1),
            'enviar_im' => $dados['enviar_im'] ?? 'N',
            'incentivo_fiscal' => $dados['incentivo_fiscal'] ?? 'N',
            'numero_atual' => (int) ($dados['numero_atual'] ?? 0),
        ];

        if ($existing) {
            $this->qb
                ->table('nfse_configuracoes')
                ->where('id', '=', $existing['id'])
                ->update($campos);

            return (int) $existing['id'];
        }

        $campos['chave'] = $chave;
        $campos['id_matriz_filial'] = $idMatrizFilial;

        return $this->qb
            ->table('nfse_configuracoes')
            ->insert($campos);
    }

    /**
     * Atualiza dados do certificado
     */
    public function atualizarCertificado(int $idMatrizFilial, array $dados): int
    {
        return $this->qb
            ->table('nfse_configuracoes')
            ->where('id_matriz_filial', '=', $idMatrizFilial)
            ->update([
                'certificado_arquivo' => $dados['certificado_arquivo'] ?? null,
                'certificado_senha' => $dados['certificado_senha'] ?? null,
                'certificado_validade' => $dados['certificado_validade'] ?? null,
            ]);
    }

    /**
     * Normaliza senha/validade de certificado importado do legado.
     */
    public function normalizarCertificado(int $idMatrizFilial, string $senhaCriptografada, ?string $validade): int
    {
        return $this->qb
            ->table('nfse_configuracoes')
            ->where('id_matriz_filial', '=', $idMatrizFilial)
            ->update([
                'certificado_senha' => $senhaCriptografada,
                'certificado_validade' => $validade,
            ]);
    }

    /**
     * Remove dados do certificado
     */
    public function removerCertificado(int $idMatrizFilial): int
    {
        return $this->qb
            ->table('nfse_configuracoes')
            ->where('id_matriz_filial', '=', $idMatrizFilial)
            ->update([
                'certificado_arquivo' => null,
                'certificado_senha' => null,
                'certificado_validade' => null,
            ]);
    }

    /**
     * Proximo numero sequencial (atomico)
     *
     * @param string $tipo 'nacional' ou 'betha'
     * @return int Proximo numero
     */
    public function proximoNumero(int $idMatrizFilial, string $tipo = 'nacional', ?string $chave = null): int
    {
        $campo = 'numero_atual';

        $mysqli = $this->getMysqli();

        // UPDATE atomico para evitar numeros duplicados em concorrencia
        if ($chave !== null) {
            $stmt = $mysqli->prepare(
                "UPDATE nfse_configuracoes SET {$campo} = {$campo} + 1 WHERE id_matriz_filial = ? AND chave = ?"
            );
            $stmt->bind_param('is', $idMatrizFilial, $chave);
        } else {
            $stmt = $mysqli->prepare(
                "UPDATE nfse_configuracoes SET {$campo} = {$campo} + 1 WHERE id_matriz_filial = ?"
            );
            $stmt->bind_param('i', $idMatrizFilial);
        }
        $stmt->execute();
        $stmt->close();

        // Buscar o valor atualizado
        $config = $this->qb
            ->table('nfse_configuracoes')
            ->select([$campo])
            ->where('id_matriz_filial', '=', $idMatrizFilial)
            ->first();

        return (int) ($config[$campo] ?? 1);
    }

    /**
     * Lista configuracoes ativas para CRON de emissao automatica
     * Cross-tenant: busca todas as empresas com emissao_auto = 'S'
     */
    public function listarAtivasParaCron(): array
    {
        return $this->qb
            ->table('nfse_configuracoes')
            ->withoutChave()
            ->where('ativo', '=', 'S')
            ->where('emissao_auto', '=', 'S')
            ->get();
    }

    /**
     * Lista configuracoes com envio de email ativo para CRON
     */
    public function listarComEmailParaCron(): array
    {
        return $this->qb
            ->table('nfse_configuracoes')
            ->withoutChave()
            ->where('ativo', '=', 'S')
            ->where('enviar_email', '=', 'S')
            ->get();
    }
}
