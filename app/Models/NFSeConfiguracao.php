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
        $tipoEmissao = (string) ($dados['tipo_emissao'] ?? 'nacional');
        $codigoMunicipio = preg_replace('/\D/', '', (string) ($dados['codigo_municipio'] ?? ''));
        $codigoServico = trim((string) ($dados['codigo_servico'] ?? ''));

        if (!in_array($tipoEmissao, ['nacional', 'betha'], true)) {
            throw new \InvalidArgumentException('Tipo de emissão NFS-e não suportado.');
        }

        if ($ativo === 'S' && $serie === '') {
            throw new \InvalidArgumentException('Série da NFS-e é obrigatória quando a emissão está ativa.');
        }
        if ($ativo === 'S' && strlen($codigoMunicipio) !== 7) {
            throw new \InvalidArgumentException('Código IBGE do município deve ter 7 dígitos quando a emissão está ativa.');
        }
        if ($ativo === 'S' && $codigoServico === '') {
            throw new \InvalidArgumentException('Código do serviço é obrigatório quando a emissão está ativa.');
        }

        $campos = [
            'ativo' => $ativo,
            'ambiente' => (int) ($dados['ambiente'] ?? 2),
            'tipo_emissao' => $tipoEmissao,
            'serie' => $serie !== '' ? $serie : null,
            'emissao_auto' => $dados['emissao_auto'] ?? 'N',
            'enviar_email' => $dados['enviar_email'] ?? 'S',
            'codigo_municipio' => $codigoMunicipio !== '' ? $codigoMunicipio : null,
            'codigo_servico' => $codigoServico !== '' ? $codigoServico : '1.1101.11',
            'descricao_servico' => $dados['descricao_servico'] ?? null,
            'regime_tributario' => (int) ($dados['regime_tributario'] ?? 1),
            'reg_apuracao_sn' => (int) ($dados['reg_apuracao_sn'] ?? 1),
            'trib_issqn' => (int) ($dados['trib_issqn'] ?? 4),
            'aliquota_iss' => $this->normalizarDecimal($dados['aliquota_iss'] ?? 0),
            'preencher_ibscbs' => ($dados['preencher_ibscbs'] ?? 'N') === 'S' ? 'S' : 'N',
            'aliquota_ibs' => $this->normalizarDecimal($dados['aliquota_ibs'] ?? 0),
            'aliquota_cbs' => $this->normalizarDecimal($dados['aliquota_cbs'] ?? 0),
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

    private function normalizarDecimal(mixed $valor): float
    {
        $valor = trim((string) ($valor ?? '0'));
        if ($valor === '') {
            return 0.0;
        }

        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $valor = str_replace('.', '', $valor);
        }

        return (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $valor) ?? '0');
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
     * Consulta o próximo número sem reservar.
     */
    public function consultarProximoNumero(int $idMatrizFilial, ?string $chave = null): int
    {
        if ($chave !== null) {
            $mysqli = $this->getMysqli();
            $stmt = $mysqli->prepare(
                'SELECT numero_atual FROM nfse_configuracoes WHERE id_matriz_filial = ? AND chave = ? LIMIT 1'
            );
            $stmt->bind_param('is', $idMatrizFilial, $chave);
            $stmt->execute();
            $result = $stmt->get_result();
            $config = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            return ((int) ($config['numero_atual'] ?? 0)) + 1;
        }

        $config = $this->qb
            ->table('nfse_configuracoes')
            ->select(['numero_atual'])
            ->where('id_matriz_filial', '=', $idMatrizFilial)
            ->first();

        return ((int) ($config['numero_atual'] ?? 0)) + 1;
    }

    /**
     * Reserva de forma atomica um número previamente calculado.
     */
    public function reservarNumero(int $idMatrizFilial, int $numero, ?string $chave = null): bool
    {
        $numeroAnterior = max(0, $numero - 1);
        $mysqli = $this->getMysqli();

        if ($chave !== null) {
            $stmt = $mysqli->prepare(
                'UPDATE nfse_configuracoes SET numero_atual = ? WHERE id_matriz_filial = ? AND chave = ? AND numero_atual = ?'
            );
            $stmt->bind_param('iisi', $numero, $idMatrizFilial, $chave, $numeroAnterior);
        } else {
            $stmt = $mysqli->prepare(
                'UPDATE nfse_configuracoes SET numero_atual = ? WHERE id_matriz_filial = ? AND numero_atual = ?'
            );
            $stmt->bind_param('iii', $numero, $idMatrizFilial, $numeroAnterior);
        }

        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected === 1;
    }

    /**
     * Reserva e retorna o próximo número sequencial.
     *
     * @param string $tipo 'nacional' ou 'betha'
     * @return int Próximo número reservado
     */
    public function proximoNumero(int $idMatrizFilial, string $tipo = 'nacional', ?string $chave = null): int
    {
        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            $numero = $this->consultarProximoNumero($idMatrizFilial, $chave);
            if ($this->reservarNumero($idMatrizFilial, $numero, $chave)) {
                return $numero;
            }
        }

        throw new \RuntimeException('Não foi possível reservar número sequencial da NFS-e.');
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
