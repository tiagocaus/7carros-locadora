<?php

namespace App\Models;

use App\Config\NFSe as NFSeConfig;
use App\Services\NFSe\NFSeContextoFiscal;
use App\Services\NFSe\Nacional\NFSeXMLNacional;
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

    public function listarPerfis(int $idMatrizFilial): array
    {
        return (new NFSePerfil())->listar($idMatrizFilial);
    }

    public function resolverPorCompetencia(int $idMatrizFilial, string $competencia, bool $persistir = true): array
    {
        $competencia = NFSeContextoFiscal::competencia($competencia);
        $root = $this->buscarPorMatrizFilial($idMatrizFilial);
        if (!$root) {
            throw new \InvalidArgumentException('Configure a emissão de NFS-e desta filial.');
        }
        $config = $this->resolverDados($root, $competencia);
        $pendencias = $this->pendenciasFiscais($config);
        if ($persistir && $pendencias) {
            throw new \InvalidArgumentException(implode(' ', $pendencias));
        }
        $infraestrutura = (new NFSePerfil())->disponivel();
        if ($persistir && !$infraestrutura && $config['tipo_emissao'] !== $root['tipo_emissao']) {
            throw new \InvalidArgumentException('A atualização necessária para esta emissão ainda não foi concluída. Contate o suporte.');
        }
        if ($persistir && empty($config['perfil_id']) && $infraestrutura) {
            $perfil = (new NFSePerfil())->garantir($idMatrizFilial, $config);
            $config = array_replace($config, $perfil, [
                'regime_tributario' => $root['regime_tributario'],
                'reg_apuracao_sn' => $root['reg_apuracao_sn'],
            ]);
            $config = $this->normalizarIBSLegado($config);
            $pendencias = $this->pendenciasFiscais($config);
            if ($pendencias) {
                throw new \InvalidArgumentException(implode(' ', $pendencias));
            }
        }
        return $config;
    }

    /** Resolve sem gravar: uma consulta de configuração nunca cria perfis. */
    private function resolverDados(array $root, string $competencia): array
    {
        $model = new NFSePerfil();
        $filial = (int) $root['id_matriz_filial'];
        $ambiente = (int) $root['ambiente'];
        $tipo = NFSeContextoFiscal::exigeNacional((int) $root['regime_tributario'], $competencia) ? 'nacional' : $root['tipo_emissao'];
        $perfis = $model->listar($filial);
        if ($tipo === 'nacional' && (int) $root['regime_tributario'] === 1 && $competencia < NFSeContextoFiscal::CORTE_SIMPLES) {
            $municipais = array_values(array_filter($perfis, static fn(array $p): bool => $p['tipo_emissao'] !== 'nacional' && $p['ambiente'] === $ambiente));
            if (count($municipais) > 1) {
                throw new \InvalidArgumentException('Há mais de uma configuração municipal para esta competência antiga. Contate o suporte.');
            }
            if ($municipais) {
                $tipo = $municipais[0]['tipo_emissao'];
            }
        }
        $perfil = null;
        foreach ($perfis as $item) {
            if ($item['tipo_emissao'] === $tipo && $item['ambiente'] === $ambiente) {
                $perfil = $item;
                break;
            }
        }
        $novoNacional = $tipo === 'nacional' && ((!$perfil && ($root['_tipo_origem'] ?? $root['tipo_emissao']) !== 'nacional') || !empty($perfil['_exigir_ctrib_nacional']));
        $config = array_replace($root, $perfil ?? [], [
            'tipo_emissao' => $tipo, 'ambiente' => $ambiente,
            'regime_tributario' => $root['regime_tributario'], 'reg_apuracao_sn' => $root['reg_apuracao_sn'],
            '_novo_nacional' => $novoNacional,
        ]);
        if ($novoNacional && !$perfil) {
            // Somente classificações explícitas são compartilháveis; nada é inferido de ISS/CNAE.
            $config = array_diff_key($config, array_flip(['perfil_id', 'item_lista_servico', 'codigo_cnae', 'codigo_tributacao_municipio']));
        }
        if ($model->disponivel()) {
            $config['numero_atual'] = $model->maiorNumeroConhecido($config, (int) ($config['numero_atual'] ?? 0));
        }
        return $this->normalizarIBSLegado($config);
    }

    private function normalizarIBSLegado(array $config): array
    {
        $completa = strlen(preg_replace('/\\D/', '', (string) ($config['c_ind_op_ibscbs'] ?? ''))) === 6
            && strlen(preg_replace('/\\D/', '', (string) ($config['cst_ibscbs'] ?? ''))) === 3
            && strlen(preg_replace('/\\D/', '', (string) ($config['c_class_trib_ibscbs'] ?? ''))) === 6;
        if (in_array((int) $config['regime_tributario'], [1, 4], true) && NFSeContextoFiscal::competencia() < '2027-01-01'
            && ($config['preencher_ibscbs'] ?? 'N') === 'S' && !$completa) {
            $config['preencher_ibscbs'] = 'N';
        }
        return $config;
    }

    public function configuracaoAtual(int $idMatrizFilial, ?string $competencia = null, array $ajustes = []): array
    {
        $competencia = NFSeContextoFiscal::competencia($competencia);
        $root = $this->buscarPorMatrizFilial($idMatrizFilial);
        if (!$root) {
            if (!$this->qb->table('matrizes_filiais')->where('id', '=', $idMatrizFilial)->first()) {
                throw new \InvalidArgumentException('Filial não encontrada nesta conta.');
            }
            $root = ['id_matriz_filial' => $idMatrizFilial, 'ativo' => 'N', 'ambiente' => 2,
                'tipo_emissao' => 'nacional', 'serie' => '', 'numero_atual' => 0, 'codigo_municipio' => '',
                'codigo_servico' => '1.1101.11', 'codigo_tributacao_nacional' => null,
                'regime_tributario' => 1, 'reg_apuracao_sn' => 1, 'trib_issqn' => 4, 'aliquota_iss' => 0,
                'emissao_auto' => 'N', 'enviar_email' => 'S', 'preencher_ibscbs' => 'N'];
        }
        $root['_tipo_origem'] = $root['tipo_emissao'];
        $root = array_replace($root, array_intersect_key($ajustes, array_flip(['regime_tributario', 'reg_apuracao_sn', 'ambiente'])));
        if (($ajustes['emissor_manual'] ?? 'N') === 'S' && !NFSeContextoFiscal::exigeNacional((int) $root['regime_tributario'], $competencia)) {
            $root['tipo_emissao'] = (string) ($ajustes['tipo_emissao'] ?? $root['tipo_emissao']);
        }
        $config = $this->resolverDados($root, $competencia);
        $pendencias = $this->pendenciasFiscais($config);
        if (!(new NFSePerfil())->disponivel() && $config['tipo_emissao'] !== $root['tipo_emissao']) {
            $pendencias[] = 'A atualização necessária para esta emissão ainda não foi concluída. Contate o suporte.';
        }
        if (empty($config['certificado_arquivo']) || empty($config['certificado_senha'])) {
            $pendencias[] = 'Envie o certificado digital A1 da filial.';
        } else {
            $certificado = new \App\Services\NFSe\NFSeCertificado();
            $analise = $certificado->analisar((string) $_SESSION['chave'], $config['certificado_arquivo'], $config['certificado_senha']);
            if (($analise['status'] ?? '') !== 'valido') {
                $pendencias[] = ($analise['status'] ?? '') === 'vencido' ? 'Atualize o certificado digital vencido.' : ($analise['mensagem'] ?? 'Verifique o certificado digital da filial.');
            }
        }
        $publica = array_intersect_key($config, array_flip(array_merge(NFSeContextoFiscal::CAMPOS, [
            'id_matriz_filial', 'ativo', 'numero_atual', 'emissao_auto', 'enviar_email',
        ])));
        return ['configuracao_atual' => $publica, 'emissor_efetivo' => $config['tipo_emissao'],
            'pendencias' => array_values(array_unique($pendencias)), 'data_competencia' => $competencia];
    }

    private function pendenciasFiscais(array $config): array
    {
        $pendencias = [];
        if (!in_array((int) ($config['ambiente'] ?? 0), [1, 2], true)) {
            $pendencias[] = 'Selecione Produção ou Homologação para a emissão.';
        }
        $tipo = (string) ($config['tipo_emissao'] ?? '');
        if (!in_array($tipo, ['nacional', 'betha', 'issnet'], true)) {
            $pendencias[] = 'O emissor municipal cadastrado não é suportado. Contate o suporte para identificar a integração correta.';
        }
        $serie = trim((string) ($config['serie'] ?? ''));
        if ($serie === '' || ($tipo === 'nacional' && !preg_match('/^[0-9]{1,5}$/D', $serie))) {
            $pendencias[] = $tipo === 'nacional' ? 'Informe uma série numérica de até 5 dígitos.' : 'Informe a série da emissão.';
        }
        if (!preg_match('/^[0-9]{7}$/D', (string) ($config['codigo_municipio'] ?? ''))) {
            $pendencias[] = 'Informe o código IBGE do município com 7 dígitos.';
        }
        $codigoServico = preg_replace('/\D/', '', (string) ($config['codigo_servico'] ?? ''));
        if (empty($config['codigo_servico']) || ($tipo === 'nacional' && !preg_match('/^[0-9]{7,9}$/D', $codigoServico))) {
            $pendencias[] = 'Informe o código do serviço (NBS) válido.';
        }
        $cTribNac = trim((string) ($config['codigo_tributacao_nacional'] ?? ''));
        if (($cTribNac !== '' || !empty($config['_novo_nacional'])) && !preg_match('/^[0-9]{6}$/D', $cTribNac)) {
            $pendencias[] = 'Informe o código de tributação nacional com 6 dígitos.';
        }
        if (!in_array((int) ($config['regime_tributario'] ?? 0), [1, 2, 3, 4], true)
            || ((int) ($config['regime_tributario'] ?? 0) === 1 && !in_array((int) ($config['reg_apuracao_sn'] ?? 0), [1, 2, 3], true))) {
            $pendencias[] = 'Informe o regime tributário e a apuração do Simples Nacional corretamente.';
        }
        if ($tipo === 'issnet' && empty($config['item_lista_servico'])) {
            $pendencias[] = 'Informe o item da lista de serviço municipal.';
        }
        if (($config['preencher_ibscbs'] ?? 'N') === 'S') {
            if ($tipo === 'issnet') {
                $pendencias[] = 'O envio de IBS/CBS não está disponível para este emissor municipal.';
            } else {
                $indicador = (string) ($config['c_ind_op_ibscbs'] ?? '');
                $cst = (string) ($config['cst_ibscbs'] ?? '');
                $classe = (string) ($config['c_class_trib_ibscbs'] ?? '');
                if (!NFSeConfig::cIndOpNT004Valido($indicador) || !preg_match('/^[0-9]{3}$/D', $cst)
                    || !preg_match('/^[0-9]{6}$/D', $classe) || !str_starts_with($classe, $cst)) {
                    $pendencias[] = 'Corrija o indicador da operação, CST e classificação tributária de IBS/CBS.';
                }
            }
        }
        return $pendencias;
    }

    /** A tela única grava os campos gerais na filial e os fiscais no emissor aplicável. */
    public function salvarAtual(int $idMatrizFilial, string $chave, array $dados): int
    {
        $competencia = NFSeContextoFiscal::competencia($dados['data_competencia'] ?? null);
        $root = $this->buscarPorMatrizFilial($idMatrizFilial);
        if (!$root) {
            if (NFSeContextoFiscal::exigeNacional((int) ($dados['regime_tributario'] ?? 1), $competencia)) {
                $dados['tipo_emissao'] = 'nacional';
            }
            $dados['perfil_operacional'] = 'N';
            $dados['_novo_nacional'] = ($dados['tipo_emissao'] ?? 'nacional') === 'nacional';
            if (($dados['ativo'] ?? 'N') === 'S' && ($pendencias = $this->pendenciasFiscais($dados))) {
                throw new \InvalidArgumentException(implode(' ', $pendencias));
            }
            return $this->salvar($idMatrizFilial, $chave, $dados);
        }
        $comuns = array_intersect_key($dados, array_flip(['ativo', 'ambiente', 'emissao_auto', 'enviar_email', 'regime_tributario', 'reg_apuracao_sn']));
        $base = array_replace($root, $comuns, ['_tipo_origem' => $root['tipo_emissao']]);
        // Para não optantes mantém a seleção municipal; os optantes usam a regra da competência.
        $escolhaManual = ($dados['emissor_manual'] ?? 'N') === 'S' && !NFSeContextoFiscal::exigeNacional((int) $base['regime_tributario'], $competencia);
        if ($escolhaManual && isset($dados['tipo_emissao'])) {
            $base['tipo_emissao'] = $dados['tipo_emissao'];
        }
        $efetiva = $this->resolverDados($base, $competencia);
        $campos = array_replace($efetiva, $dados, ['tipo_emissao' => $efetiva['tipo_emissao']]);
        $campos['perfil_operacional'] = !$escolhaManual && $efetiva['tipo_emissao'] !== $root['tipo_emissao'] ? 'S' : 'N';
        $serieAntes = trim((string) ($efetiva['serie'] ?? ''));
        $serieDepois = trim((string) ($campos['serie'] ?? ''));
        if ($campos['tipo_emissao'] === 'nacional' && ctype_digit($serieAntes) && ctype_digit($serieDepois)) {
            $mesmaSerie = (ltrim($serieAntes, '0') ?: '0') === (ltrim($serieDepois, '0') ?: '0');
        } else {
            $mesmaSerie = $serieAntes === $serieDepois;
        }
        if ((new NFSePerfil())->disponivel()) {
            $campos['numero_atual'] = (new NFSePerfil())->maiorNumeroConhecido($campos,
                max((int) ($campos['numero_atual'] ?? 0), $mesmaSerie ? (int) ($efetiva['numero_atual'] ?? 0) : 0));
        }

        if (($campos['ativo'] ?? 'N') === 'S') {
            $pendencias = $this->pendenciasFiscais($campos);
            if ($pendencias) {
                throw new \InvalidArgumentException(implode(' ', $pendencias));
            }
        }
        $campos['_configuracao_inativa'] = ($campos['ativo'] ?? 'N') !== 'S';
        return $this->transacionar(function () use ($idMatrizFilial, $chave, $campos, $comuns, $root): int {
            $this->qb->table('nfse_configuracoes')->where('id', '=', $root['id'])->update($comuns);
            return $this->salvar($idMatrizFilial, $chave, $campos);
        });
    }

    public function resolverHistorico(array $nfse): array
    {
        $filial = (int) $nfse['id_matriz_filial'];
        $root = $this->buscarPorMatrizFilial($filial);
        if (!$root) {
            throw new \InvalidArgumentException('Configuração da filial não encontrada para operar a NFS-e histórica.');
        }
        $perfil = (new NFSePerfil())->buscar($filial, (string) $nfse['tipo_emissao'], (int) $nfse['ambiente']);
        $historico = NFSeContextoFiscal::historico($nfse, array_replace($root, $perfil ?? []));
        if (isset($historico['perfil_id']) && (!$perfil || (int) $historico['perfil_id'] !== (int) $perfil['perfil_id'])) {
            $historico['_historico_reenvio_erro'] = 'Perfil histórico não corresponde ao perfil operacional disponível.';
        }
        return $historico;
    }

    /**
     * Salva configuracao (insert ou update)
     *
     * @return int ID do registro
     */
    public function salvar(int $idMatrizFilial, string $chave, array $dados): int
    {
        if ($chave !== (string) ($_SESSION['chave'] ?? '') || !$this->qb->table('matrizes_filiais')->where('id', '=', $idMatrizFilial)->first()) {
            throw new \InvalidArgumentException('Filial não encontrada neste tenant.');
        }
        $root = $this->buscarPorMatrizFilial($idMatrizFilial);
        $somentePerfil = ($dados['perfil_operacional'] ?? 'N') === 'S';
        if ($somentePerfil && !$root) {
            throw new \InvalidArgumentException('Salve primeiro a configuração geral da filial.');
        }
        $perfilModel = new NFSePerfil();
        $perfilExistente = $perfilModel->buscar($idMatrizFilial, (string) ($dados['tipo_emissao'] ?? 'nacional'), (int) ($dados['ambiente'] ?? 2));
        $existing = $root;
        if ($somentePerfil) {
            $dados['ativo'] = $root['ativo'];
        }
        $ativo = $dados['ativo'] ?? 'N';
        $validarCampos = $ativo === 'S' || ($somentePerfil && ($dados['_configuracao_inativa'] ?? false) !== true);
        $serie = trim((string) ($dados['serie'] ?? ''));
        $tipoEmissao = (string) ($dados['tipo_emissao'] ?? 'nacional');
        $codigoMunicipio = preg_replace('/\D/', '', (string) ($dados['codigo_municipio'] ?? ''));
        $codigoServico = trim((string) ($dados['codigo_servico'] ?? ''));
        $codigoTributacaoNacional = trim((string) ($dados['codigo_tributacao_nacional'] ?? ''));
        $preencherIBSCBS = ($dados['preencher_ibscbs'] ?? 'N') === 'S' ? 'S' : 'N';
        $cIndOpIBSCBS = preg_replace('/\D/', '', (string) ($dados['c_ind_op_ibscbs'] ?? ''));
        $cstIBSCBS = preg_replace('/\D/', '', (string) ($dados['cst_ibscbs'] ?? ''));
        $cClassTribIBSCBS = preg_replace('/\D/', '', (string) ($dados['c_class_trib_ibscbs'] ?? ''));

        if (!in_array($tipoEmissao, ['nacional', 'betha', 'issnet'], true)) {
            throw new \InvalidArgumentException('Tipo de emissão NFS-e não suportado.');
        }

        if (!in_array((int) ($dados['ambiente'] ?? 2), [1, 2], true)) {
            throw new \InvalidArgumentException('Ambiente da NFS-e inválido.');
        }
        if ($tipoEmissao === 'nacional' && $validarCampos) {
            NFSeXMLNacional::validarSerie($serie);

        }
        if (!in_array((int) ($dados['regime_tributario'] ?? 1), [1, 2, 3, 4], true)
            || !in_array((int) ($dados['reg_apuracao_sn'] ?? 1), [1, 2, 3], true)) {
            throw new \InvalidArgumentException('Regime tributário ou de apuração do Simples inválido.');
        }
        if ($validarCampos && $serie === '') {
            throw new \InvalidArgumentException('Série da NFS-e é obrigatória quando a emissão está ativa.');
        }
        if ($validarCampos && strlen($codigoMunicipio) !== 7) {
            throw new \InvalidArgumentException('Código IBGE do município deve ter 7 dígitos quando a emissão está ativa.');
        }
        if ($validarCampos && $codigoServico === '') {
            throw new \InvalidArgumentException('Código do serviço é obrigatório quando a emissão está ativa.');
        }
        if ($validarCampos && $codigoTributacaoNacional !== '' && !preg_match('/^\d{6}$/', $codigoTributacaoNacional)) {
            throw new \InvalidArgumentException('Código de tributação nacional deve ter 6 dígitos.');
        }
        if ($validarCampos && $tipoEmissao === 'issnet' && $preencherIBSCBS === 'S') {
            throw new \InvalidArgumentException('IBS/CBS não está homologado para a emissão ISSNet.');
        }
        if ($validarCampos && in_array($tipoEmissao, ['nacional', 'betha'], true) && $preencherIBSCBS === 'S') {
            if ($tipoEmissao === 'betha' && !preg_match('/^\d{6}$/', $codigoTributacaoNacional)) {
                throw new \InvalidArgumentException('Código de tributação nacional deve ter 6 dígitos para enviar IBS/CBS pela Betha.');
            }
            if (strlen($cIndOpIBSCBS) !== 6) {
                throw new \InvalidArgumentException('Código indicador da operação IBS/CBS deve ter 6 dígitos.');
            }
            if (!NFSeConfig::cIndOpNT004Valido($cIndOpIBSCBS)) {
                throw new \InvalidArgumentException('Código indicador da operação não é aceito pelo layout NT004 ativo na NFS-e.');
            }
            if (strlen($cstIBSCBS) !== 3) {
                throw new \InvalidArgumentException('CST do IBS/CBS deve ter 3 dígitos.');
            }
            if (strlen($cClassTribIBSCBS) !== 6) {
                throw new \InvalidArgumentException('Classificação tributária do IBS/CBS deve ter 6 dígitos.');
            }
            if (!str_starts_with($cClassTribIBSCBS, $cstIBSCBS)) {
                throw new \InvalidArgumentException('Os 3 primeiros dígitos da classificação tributária devem ser iguais ao CST do IBS/CBS.');
            }
        }
        if ($validarCampos && $tipoEmissao === 'issnet') {
            if (trim((string) ($dados['item_lista_servico'] ?? '')) === '') {
                throw new \InvalidArgumentException('Item da lista de serviço é obrigatório para ISSNet.');
            }
            if (trim((string) ($dados['inscricao_municipal'] ?? '')) === '' && trim((string) ($dados['enviar_im'] ?? 'N')) !== 'S') {
                // ISSNet/ABRASF exige IM no cadastro do prestador; o valor vem da matriz/filial.
                $dados['enviar_im'] = 'S';
            }
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
            'preencher_ibscbs' => $preencherIBSCBS,
            'c_ind_op_ibscbs' => $cIndOpIBSCBS !== '' ? $cIndOpIBSCBS : null,
            'cst_ibscbs' => $cstIBSCBS !== '' ? $cstIBSCBS : null,
            'c_class_trib_ibscbs' => $cClassTribIBSCBS !== '' ? $cClassTribIBSCBS : null,
            'aliquota_ibs' => $this->normalizarDecimal($dados['aliquota_ibs'] ?? 0),
            'aliquota_cbs' => $this->normalizarDecimal($dados['aliquota_cbs'] ?? 0),
            'exigibilidade_iss' => (int) ($dados['exigibilidade_iss'] ?? 1),
            'enviar_im' => $dados['enviar_im'] ?? 'N',
            'incentivo_fiscal' => $dados['incentivo_fiscal'] ?? 'N',
            'numero_atual' => (int) ($dados['numero_atual'] ?? 0),
            'item_lista_servico' => trim((string) ($dados['item_lista_servico'] ?? '')) ?: null,
            'codigo_cnae' => preg_replace('/\D/', '', (string) ($dados['codigo_cnae'] ?? '')) ?: null,
            'codigo_tributacao_municipio' => trim((string) ($dados['codigo_tributacao_municipio'] ?? '')) ?: null,
        ];

        // Mantem compatibilidade durante deploy em duas etapas (codigo antes da migracao).
        if ($this->possuiColunaCodigoTributacaoNacional()) {
            $campos['codigo_tributacao_nacional'] = $codigoTributacaoNacional !== '' ? $codigoTributacaoNacional : null;
        }

        return $this->transacionar(function () use ($somentePerfil, $root, $campos, $perfilModel, $idMatrizFilial, $existing, $tipoEmissao, $chave): int {
            if ($somentePerfil) {
                $this->qb->table('nfse_configuracoes')->where('id', '=', $root['id'])->update(['regime_tributario' => $campos['regime_tributario'], 'reg_apuracao_sn' => $campos['reg_apuracao_sn']]);
                $id = $perfilModel->salvar($idMatrizFilial, $campos, true);
            } else {
                if ($existing) {
                    // Não deixa um formulário antigo rebaixar a sequência já reservada.
                    if ($existing['tipo_emissao'] === $tipoEmissao && (int) $existing['ambiente'] === $campos['ambiente'] && $existing['serie'] === $campos['serie']) {
                        $campos['numero_atual'] = max($campos['numero_atual'], (int) $existing['numero_atual']);
                    }
                    $this->qb->table('nfse_configuracoes')->where('id', '=', $existing['id'])->update($campos);
                    $id = (int) $existing['id'];
                } else {
                    $id = $this->qb->table('nfse_configuracoes')->insert($campos + ['chave' => $chave, 'id_matriz_filial' => $idMatrizFilial]);
                }
                if ($perfilModel->disponivel()) {
                    $perfilModel->salvar($idMatrizFilial, $campos, true);
                }
            }
            return $id;
        });
    }

    private function transacionar(callable $acao): mixed
    {
        $mysqli = $this->getMysqli();
        $ponto = 'nfse_' . bin2hex(random_bytes(6));
        $mysqli->query('SAVEPOINT ' . $ponto);
        try {
            $mysqli->query('RELEASE SAVEPOINT ' . $ponto);
            $externa = true;
        } catch (\mysqli_sql_exception $e) {
            if ($e->getCode() !== 1305) {
                throw $e;
            }
            $externa = false;
        }
        if ($externa) {
            $mysqli->query('SAVEPOINT ' . $ponto);
        } else {
            $mysqli->begin_transaction();
        }
        try {
            $resultado = $acao();
            $externa ? $mysqli->query('RELEASE SAVEPOINT ' . $ponto) : $mysqli->commit();
            return $resultado;
        } catch (\Throwable $e) {
            $externa ? $mysqli->query('ROLLBACK TO SAVEPOINT ' . $ponto) : $mysqli->rollback();
            throw $e;
        }
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

    private function possuiColunaCodigoTributacaoNacional(): bool
    {
        static $existe = null;
        if ($existe === null) {
            $resultado = $this->getMysqli()->query(
                "SHOW COLUMNS FROM `nfse_configuracoes` LIKE 'codigo_tributacao_nacional'"
            );
            $existe = $resultado !== false && $resultado->num_rows > 0;
            if ($resultado instanceof \mysqli_result) {
                $resultado->free();
            }
        }

        return $existe;
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
