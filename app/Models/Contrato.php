<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\DetectsCrossTenant;
use App\Helpers\FilialHelper;

/**
 * Model Contrato
 *
 * Gerencia contratos de locacao de veiculos.
 * Cada contrato pode ter multiplos veiculos, taxas e servicos.
 *
 * Status:
 * - A = Ativo
 * - F = Finalizado (devolvido)
 *
 * Planos de Veiculo:
 * - KMC = Km Controlado
 * - KL = Km Livre
 * - KP = Km Pago
 */
class Contrato extends Model
{
    use Auditable;
    use DetectsCrossTenant;

    /**
     * Retorna o nome da entidade para auditoria
     */
    protected function getEntidadeAuditoria(): string
    {
        return 'o contrato';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    protected function getCampoIdentificador(): string
    {
        return 'codigo';
    }

    /**
     * Lista contratos do tenant com paginacao e busca
     *
     * @param string $chave Chave do tenant
     * @param int $page Pagina atual
     * @param int $perPage Registros por pagina
     * @param string $search Termo de busca (opcional)
     * @param string $filialWhere Filtro de filial (opcional)
     * @param array $filialParams Parametros do filtro de filial
     * @param string $status Filtro de status: 'A', 'F' ou '' para todos
     * @return array Lista de contratos
     */
    public function listarPaginado(
        string $chave,
        int $page,
        int $perPage,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $status = ''
    ): array {
        $query = $this->qb
            ->table('contratos', 'c')
            ->select([
                'c.*',
                'cl.nome_rsocial AS cliente_nome',
                'cl.cpf_cnpj AS cliente_cpf_cnpj',
                'mf.nome_fantasia AS filial_nome',
                'ct.nome AS conta_descricao',
                'fp.nome AS forma_pagamento_descricao'
            ])
            // Subquery para veiculo ativo
            ->selectSubquery(function ($q) {
                $q->table('contratos_veiculos', 'cv_ativo')
                  ->selectRaw("CONCAT(v.placa, ' - ', v.modelo)")
                  ->leftJoin('veiculos', 'v', 'cv_ativo.id_veiculo', '=', 'v.id')
                  ->whereRaw('cv_ativo.id_contrato = c.id')
                  ->whereNull('cv_ativo.data_entrada')
                  ->limit(1);
            }, 'veiculo_ativo')
            // Subquery para contar veiculos ativos
            ->selectSubquery(function ($q) {
                $q->table('contratos_veiculos', 'cv_count')
                  ->selectRaw('COUNT(*)')
                  ->whereRaw('cv_count.id_contrato = c.id')
                  ->whereNull('cv_count.data_entrada');
            }, 'qtd_veiculos_ativos')
            // Subquery para verificar se tem assinatura
            ->selectSubquery(function ($q) {
                $q->table('assinaturas', 'asn')
                  ->selectRaw('asn.id')
                  ->whereRaw('asn.id_contrato = c.id')
                  ->limit(1);
            }, 'id_assinatura')
            ->leftJoin('clientes', 'cl', 'c.id_cliente', '=', 'cl.id')
            ->leftJoin('matrizes_filiais', 'mf', 'c.id_matriz_filial_retirada', '=', 'mf.id')
            ->leftJoin('contas_bancarias', 'ct', 'c.id_conta', '=', 'ct.id')
            ->leftJoin('formas_pagamento', 'fp', 'c.id_forma_pagamento', '=', 'fp.id');

        // Filtro de busca
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('c.codigo', 'LIKE', $searchTerm)
                  ->orWhere('cl.nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('cl.cpf_cnpj', 'LIKE', $searchTerm);
            });
        }

        // Filtro de filial (permissoes do usuario)
        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        // Filtro de status
        if (!empty($status)) {
            $query->where('c.status', '=', $status);
        }

        return $query
            ->orderByDesc('c.data_ini')
            ->orderByDesc('c.id')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de contratos com filtros
     *
     * @param string $chave Chave do tenant
     * @param string $search Termo de busca
     * @param string $filialWhere Filtro de filial
     * @param array $filialParams Parametros do filtro
     * @param string $status Filtro de status
     * @return int Total de registros
     */
    public function contar(
        string $chave,
        string $search = '',
        string $filialWhere = '',
        array $filialParams = [],
        string $status = ''
    ): int {
        $query = $this->qb
            ->table('contratos', 'c')
            ->leftJoin('clientes', 'cl', 'c.id_cliente', '=', 'cl.id');

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('c.codigo', 'LIKE', $searchTerm)
                  ->orWhere('cl.nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('cl.cpf_cnpj', 'LIKE', $searchTerm);
            });
        }

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        if (!empty($status)) {
            $query->where('c.status', '=', $status);
        }

        return $query->count();
    }

    /**
     * Lista contratos ativos cujo intervalo [data_ini, data_fim] intersecta [$inicio, $fim].
     * Usado na tela de Agenda.
     *
     * Retorna uma linha por veiculo ativo do contrato (LEFT JOIN com contratos_veiculos).
     * Contratos sem veiculo ativo ainda retornam com id_veiculo=null.
     */
    public function listarEventosAgenda(
        string $chave,
        string $inicio,
        string $fim,
        string $filialWhere = '',
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('contratos', 'c')
            ->select([
                'c.id',
                'c.codigo',
                'c.status',
                'c.data_ini',
                'c.data_fim',
                'c.obs',
                'cv.id_veiculo',
                'cv.id_grupo',
            ])
            ->selectRaw('cl.nome_rsocial AS cliente_nome')
            ->leftJoinRaw('contratos_veiculos', 'cv', 'cv.id_contrato = c.id AND cv.data_entrada IS NULL')
            ->leftJoin('clientes', 'cl', 'c.id_cliente', '=', 'cl.id')
            ->where('c.status', '=', 'A')
            ->where('c.data_ini', '<=', $fim)
            ->where('c.data_fim', '>=', $inicio);

        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        return $query
            ->orderBy('c.data_ini', 'ASC')
            ->get();
    }

    /**
     * Busca um contrato por ID com dados relacionados
     *
     * @param int $id ID do contrato
     * @return array|null Dados do contrato ou null
     */
    public function buscarPorId(int $id): ?array
    {
        $contrato = $this->qb
            ->table('contratos', 'c')
            ->select([
                'c.*',
                'cl.nome_rsocial AS cliente_nome',
                'cl.cpf_cnpj AS cliente_cpf_cnpj',
                'mf.nome_fantasia AS filial_nome',
                'ct.nome AS conta_descricao',
                'fp.nome AS forma_pagamento_descricao',
                'func.nome AS funcionario_nome',
                'fpc.comando AS comando_parcela_comando',
                'fpc.descricao AS comando_parcela_descricao'
            ])
            ->leftJoin('clientes', 'cl', 'c.id_cliente', '=', 'cl.id')
            ->leftJoin('matrizes_filiais', 'mf', 'c.id_matriz_filial_retirada', '=', 'mf.id')
            ->leftJoin('contas_bancarias', 'ct', 'c.id_conta', '=', 'ct.id')
            ->leftJoin('formas_pagamento', 'fp', 'c.id_forma_pagamento', '=', 'fp.id')
            ->leftJoin('funcionarios', 'func', 'c.id_funcionario', '=', 'func.id')
            ->leftJoin('formas_pagamento_comandos', 'fpc', 'c.id_comando_parcela', '=', 'fpc.id')
            ->where('c.id', '=', $id)
            ->first();

        if ($contrato && !empty($contrato['id_cliente'])) {
            // Buscar email e telefone das tabelas normalizadas
            $emailModel = new ContatoEmail();
            $telefoneModel = new ContatoTelefone();

            $emailPrincipal = $emailModel->getPrincipal('cliente', (int) $contrato['id_cliente']);
            $telefonePrincipal = $telefoneModel->getPrincipal('cliente', (int) $contrato['id_cliente']);

            $contrato['cliente_email'] = $emailPrincipal['email'] ?? '';
            $contrato['cliente_telefone'] = $telefonePrincipal['telefone'] ?? '';
        }

        // Carregar dados do bloqueio ativo (pre-autorizacao)
        if ($contrato && !empty($contrato['id_bloqueio_ativo'])) {
            $bloqueioModel = new ContratoBloqueio();
            $bloqueio = $bloqueioModel->buscarPorId((int) $contrato['id_bloqueio_ativo']);
            if ($bloqueio) {
                $contrato['bloqueio_status'] = $bloqueio['status'];
                $contrato['bloqueio_hold_valor'] = $bloqueio['valor'];
                $contrato['bloqueio_valor_capturado'] = $bloqueio['valor_capturado'];
                $contrato['bloqueio_expira_em'] = $bloqueio['expira_em'];

                // Dados do cartao
                $cartaoModel = new \App\Models\ClienteCartao();
                $cartao = $cartaoModel->buscarPorId((int) $bloqueio['id_cartao']);
                $contrato['bloqueio_cartao_bandeira'] = $cartao['bandeira'] ?? null;
                $contrato['bloqueio_cartao_ultimos_digitos'] = $cartao['ultimos_digitos'] ?? null;
            }
        }

        return $contrato;
    }

    /**
     * Busca um contrato por codigo
     *
     * @param string $codigo Codigo do contrato
     * @return array|null Dados do contrato ou null
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $id = $this->qb
            ->table('contratos')
            ->select(['id'])
            ->where('codigo', '=', $codigo)
            ->first();

        if (!$id) {
            return null;
        }

        return $this->buscarPorId($id['id']);
    }

    /**
     * Busca contrato por codigo em contexto publico, sem depender da sessao atual.
     *
     * Usado em links publicos como /assinar/{codigo}, onde o visitante pode nao
     * ter sessao ou pode estar logado em outro tenant no mesmo navegador.
     */
    public function buscarPublicoPorCodigo(string $codigo): ?array
    {
        $row = $this->qb
            ->table('contratos')
            ->withoutChave()
            ->select(['id', 'chave'])
            ->where('codigo', '=', $codigo)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->buscarComChaveTemporaria((int) $row['id'], (string) $row['chave']);
    }

    /**
     * Executa buscarPorId usando a chave do registro publico e restaura a sessao.
     */
    private function buscarComChaveTemporaria(int $id, string $chave): ?array
    {
        $hadChave = isset($_SESSION['chave']);
        $previousChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;

        try {
            return $this->buscarPorId($id);
        } finally {
            if ($hadChave) {
                $_SESSION['chave'] = $previousChave;
            } else {
                unset($_SESSION['chave']);
            }
        }
    }

    /**
     * Busca um contrato completo com veiculos, taxas e odometros
     *
     * @param int $id ID do contrato
     * @return array|null Dados completos do contrato ou null
     */
    public function buscarCompleto(int $id): ?array
    {
        $contrato = $this->buscarPorId($id);

        if (!$contrato) {
            return null;
        }

        // Carregar veiculos
        $contratoVeiculo = new ContratoVeiculo();
        $contrato['veiculos'] = $contratoVeiculo->listarPorContrato($id);
        $contrato['veiculo_ativo'] = $contratoVeiculo->buscarAtivo($id);

        // Carregar taxas e servicos
        $contratoTaxa = new ContratoTaxaServico();
        $contrato['taxas'] = $contratoTaxa->listarPorContrato($id);

        return $contrato;
    }

    /**
     * Cria um novo contrato
     *
     * @param array $dados Dados do contrato
     * @return int ID criado
     */
    public function criar(array $dados): int
    {
        // Gerar codigo se nao fornecido
        $codigo = $dados['codigo'] ?? $this->gerarCodigo($dados['chave']);

        // Gerar sequencia
        $sequencia = $this->gerarSequencia($dados['chave']);

        return $this->qb
            ->table('contratos')
            ->insert([
                'chave' => $dados['chave'],
                'sequencia' => $sequencia,
                'codigo' => $codigo,
                'id_matriz_filial_retirada' => !empty($dados['id_matriz_filial_retirada']) ? (int) $dados['id_matriz_filial_retirada'] : null,
                'data_ini' => $dados['data_ini'] ?? date('Y-m-d H:i:s'),
                'data_fim' => $dados['data_fim'] ?? date('Y-m-d H:i:s'),
                'data_renovacao' => !empty($dados['data_renovacao']) ? $dados['data_renovacao'] : null,
                'contagem' => $dados['contagem'] ?? 'dia',
                'dias' => (int) ($dados['dias'] ?? 1),
                'auto_renovacao' => !empty($dados['auto_renovacao']) ? $dados['auto_renovacao'] : null,
                'id_cliente' => !empty($dados['id_cliente']) ? (int) $dados['id_cliente'] : null,
                'condutor_adicional' => $dados['condutor_adicional'] ?? null,
                'array_fiadores' => $dados['array_fiadores'] ?? null,
                'array_avalistas' => $dados['array_avalistas'] ?? null,
                'array_testemunhas' => $dados['array_testemunhas'] ?? null,
                'valor_desconto' => currency_parse($dados['valor_desconto'] ?? 0),
                'id_conta' => !empty($dados['id_conta']) ? (int) $dados['id_conta'] : null,
                'id_forma_pagamento' => !empty($dados['id_forma_pagamento']) ? (int) $dados['id_forma_pagamento'] : null,
                'id_comando_parcela' => !empty($dados['id_comando_parcela']) ? (int) $dados['id_comando_parcela'] : null,
                'obs' => $dados['obs'] ?? null,
                'total_fatura' => currency_parse($dados['total_fatura'] ?? 0),
                'primeiro_pagamento' => !empty($dados['primeiro_pagamento']) ? currency_parse($dados['primeiro_pagamento']) : null,
                'total_pagar' => currency_parse($dados['total_pagar'] ?? 0),
                'id_bloqueio_ativo' => !empty($dados['id_bloqueio_ativo']) ? (int) $dados['id_bloqueio_ativo'] : null,
                'status' => $dados['status'] ?? 'A',
                'id_funcionario' => !empty($dados['id_funcionario']) ? (int) $dados['id_funcionario'] : null,
            ]);
    }

    /**
     * Atualiza um contrato existente
     *
     * @param int $id ID do contrato
     * @param array $dados Dados a atualizar
     * @return int Linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        $contrato = $this->buscarPorId($id);
        if (!$contrato) {
            throw new \InvalidArgumentException('Contrato nao encontrado');
        }

        $dadosUpdate = [];

        // Relacionamentos
        if (isset($dados['id_matriz_filial_retirada'])) {
            $dadosUpdate['id_matriz_filial_retirada'] = !empty($dados['id_matriz_filial_retirada']) ? (int) $dados['id_matriz_filial_retirada'] : null;
        }
        if (isset($dados['id_cliente'])) {
            $dadosUpdate['id_cliente'] = !empty($dados['id_cliente']) ? (int) $dados['id_cliente'] : null;
        }
        if (isset($dados['id_conta'])) {
            $dadosUpdate['id_conta'] = !empty($dados['id_conta']) ? (int) $dados['id_conta'] : null;
        }
        if (isset($dados['id_forma_pagamento'])) {
            $dadosUpdate['id_forma_pagamento'] = !empty($dados['id_forma_pagamento']) ? (int) $dados['id_forma_pagamento'] : null;
        }
        if (isset($dados['id_comando_parcela'])) {
            $dadosUpdate['id_comando_parcela'] = !empty($dados['id_comando_parcela']) ? (int) $dados['id_comando_parcela'] : null;
        }
        if (isset($dados['id_funcionario'])) {
            $dadosUpdate['id_funcionario'] = !empty($dados['id_funcionario']) ? (int) $dados['id_funcionario'] : null;
        }

        // Datas e periodo
        if (isset($dados['data_ini'])) {
            $dadosUpdate['data_ini'] = $dados['data_ini'];
        }
        if (isset($dados['data_fim'])) {
            $dadosUpdate['data_fim'] = $dados['data_fim'];
        }
        if (array_key_exists('data_renovacao', $dados)) {
            $dadosUpdate['data_renovacao'] = !empty($dados['data_renovacao']) ? $dados['data_renovacao'] : null;
        }
        if (isset($dados['contagem'])) {
            $dadosUpdate['contagem'] = $dados['contagem'];
        }
        if (isset($dados['dias'])) {
            $dadosUpdate['dias'] = (int) $dados['dias'];
        }
        if (array_key_exists('auto_renovacao', $dados)) {
            $dadosUpdate['auto_renovacao'] = !empty($dados['auto_renovacao']) ? $dados['auto_renovacao'] : null;
        }

        // Arrays JSON
        if (array_key_exists('condutor_adicional', $dados)) {
            $dadosUpdate['condutor_adicional'] = $dados['condutor_adicional'];
        }
        if (array_key_exists('array_fiadores', $dados)) {
            $dadosUpdate['array_fiadores'] = $dados['array_fiadores'];
        }
        if (array_key_exists('array_avalistas', $dados)) {
            $dadosUpdate['array_avalistas'] = $dados['array_avalistas'];
        }
        if (array_key_exists('array_testemunhas', $dados)) {
            $dadosUpdate['array_testemunhas'] = $dados['array_testemunhas'];
        }

        // Valores
        if (isset($dados['valor_desconto'])) {
            $dadosUpdate['valor_desconto'] = currency_parse($dados['valor_desconto']);
        }
        if (isset($dados['total_fatura'])) {
            $dadosUpdate['total_fatura'] = currency_parse($dados['total_fatura']);
        }
        if (array_key_exists('primeiro_pagamento', $dados)) {
            $dadosUpdate['primeiro_pagamento'] = !empty($dados['primeiro_pagamento']) ? currency_parse($dados['primeiro_pagamento']) : null;
        }
        if (isset($dados['total_pagar'])) {
            $dadosUpdate['total_pagar'] = currency_parse($dados['total_pagar']);
        }

        // Bloqueio (pre-autorizacao)
        if (array_key_exists('id_bloqueio_ativo', $dados)) {
            $dadosUpdate['id_bloqueio_ativo'] = !empty($dados['id_bloqueio_ativo']) ? (int) $dados['id_bloqueio_ativo'] : null;
        }

        // Status e observacoes
        if (isset($dados['status'])) {
            $dadosUpdate['status'] = $dados['status'];
        }
        if (array_key_exists('obs', $dados)) {
            $dadosUpdate['obs'] = $dados['obs'];
        }

        if (empty($dadosUpdate)) {
            return 0;
        }

        $dadosUpdate['updated_at'] = date('Y-m-d H:i:s');

        return $this->qb
            ->table('contratos')
            ->where('id', '=', $id)
            ->update($dadosUpdate);
    }

    /**
     * Exclui um contrato e dados relacionados
     *
     * @param int $id ID do contrato
     * @return int Linhas afetadas
     */
    public function deletar(int $id): int
    {
        $contrato = $this->buscarPorId($id);
        if (!$contrato) {
            return 0;
        }

        // Liberar veiculos ativos (sem data_entrada) antes de deletar
        $veiculosAtivos = $this->qb
            ->table('contratos_veiculos')
            ->select(['id_veiculo'])
            ->where('id_contrato', '=', $id)
            ->whereNull('data_entrada')
            ->get();

        foreach ($veiculosAtivos as $v) {
            $this->qb
                ->table('veiculos')
                ->where('id', '=', $v['id_veiculo'])
                ->update(['disponibilidade' => 'D']);
        }

        // Excluir checklists vinculados e seus arquivos
        $checklistModel = new \App\Models\Checklist();
        $checklistModel->excluirPorContrato($id, $contrato['chave']);

        // Desvincular lancamentos financeiros
        $this->qb
            ->table('financeiro')
            ->where('id_contrato', '=', $id)
            ->update(['id_contrato' => null]);

        // Deletar veiculos (CASCADE nao eh automatico)
        $this->qb
            ->table('contratos_veiculos')
            ->where('id_contrato', '=', $id)
            ->delete();

        // Deletar taxas e servicos
        $this->qb
            ->table('contratos_taxaseservicos')
            ->where('id_contrato', '=', $id)
            ->delete();

        // Deletar contrato
        return $this->qb
            ->table('contratos')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Gera codigo unico para o contrato
     * Formato: C + ID (padded) + 2 letras aleatorias
     *
     * @param string $chave Chave do tenant
     * @return string Codigo gerado
     */
    public function gerarCodigo(string $chave): string
    {
        $maxId = $this->qb
            ->table('contratos')
            ->max('id');

        $proximoId = ($maxId ?? 0) + 1;
        $letras = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2);

        return 'C' . str_pad($proximoId, 5, '0', STR_PAD_LEFT) . $letras;
    }

    /**
     * Gera proxima sequencia para o tenant
     *
     * @param string $chave Chave do tenant
     * @return int Proxima sequencia
     */
    public function gerarSequencia(string $chave): int
    {
        $maxSequencia = $this->qb
            ->table('contratos')
            ->max('sequencia');

        return ($maxSequencia ?? 0) + 1;
    }

    /**
     * Atualiza status do contrato
     *
     * @param int $id ID do contrato
     * @param string $status Novo status (A/F)
     * @return int Linhas afetadas
     */
    public function atualizarStatus(int $id, string $status): int
    {
        return $this->qb
            ->table('contratos')
            ->where('id', '=', $id)
            ->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Salva assinatura digital do contrato
     *
     * @param int $id ID do contrato
     * @param string $base64 Imagem base64 da assinatura
     * @param string $ip IP do cliente
     * @param array $extras Dados extras (user_agent, latitude, longitude, tipo)
     * @return int ID da assinatura criada
     */
    public function salvarAssinatura(int $id, string $base64, string $ip, array $extras = []): int
    {
        $contrato = $this->buscarPorId($id);
        if (!$contrato) {
            throw new \InvalidArgumentException('Contrato nao encontrado');
        }

        $assinaturaModel = new Assinatura();

        return $assinaturaModel->salvar([
            'base64' => $base64,
            'id_contrato' => $id,
            'id_cliente' => $contrato['id_cliente'] ?? null,
            'ip_address' => $ip,
            'user_agent' => $extras['user_agent'] ?? null,
            'latitude' => $extras['latitude'] ?? null,
            'longitude' => $extras['longitude'] ?? null,
            'tipo' => $extras['tipo'] ?? 'cliente',
            'chave' => $contrato['chave'],
        ]);
    }

    /**
     * Limpa assinaturas digitais do contrato
     *
     * @param int $id ID do contrato
     * @return int Quantidade de assinaturas removidas
     */
    public function limparAssinatura(int $id): int
    {
        $assinaturaModel = new Assinatura();
        return $assinaturaModel->excluirPorContrato($id);
    }

    /**
     * Busca assinatura do contrato
     *
     * @param int $id ID do contrato
     * @param string $tipo Tipo de assinatura
     * @return array|null
     */
    public function buscarAssinatura(int $id, string $tipo = 'cliente'): ?array
    {
        $assinaturaModel = new Assinatura();
        return $assinaturaModel->buscarPorContrato($id, $tipo);
    }

    /**
     * Verifica se contrato tem assinatura
     *
     * @param int $id ID do contrato
     * @return bool
     */
    public function temAssinatura(int $id): bool
    {
        $assinaturaModel = new Assinatura();
        return $assinaturaModel->contratoTemAssinatura($id);
    }

    /**
     * Recalcula totais do contrato baseado nos veiculos e taxas
     *
     * @param int $id ID do contrato
     * @return array Totais calculados
     */
    public function recalcularTotais(int $id): array
    {
        $contrato = $this->buscarPorId($id);
        if (!$contrato) {
            return [];
        }

        // Buscar veiculos ativos (sem data_entrada, exclui substituidos)
        $contratoVeiculo = new ContratoVeiculo();
        $veiculos = $contratoVeiculo->listarAtivos($id);

        // Buscar taxas
        $contratoTaxa = new ContratoTaxaServico();
        $taxas = $contratoTaxa->listarPorContrato($id);

        // Calcular total veiculos por periodo
        $totalVeiculos = 0;
        $totalSeguros = 0;

        foreach ($veiculos as $veiculo) {
            // Valor do plano selecionado
            switch ($veiculo['plano']) {
                case 'KL':
                    $totalVeiculos += (float) $veiculo['valor_plano_km_livre'];
                    break;
                case 'KMC':
                    $totalVeiculos += (float) $veiculo['valor_plano_km_controlado'];
                    break;
                case 'KP':
                    $totalVeiculos += (float) $veiculo['valor_plano_km_pago'];
                    break;
            }

            // Seguros
            if ($veiculo['seguro_carro']) {
                $totalSeguros += (float) $veiculo['valor_seguro_carro'];
            }
            if ($veiculo['seguro_terceiros']) {
                $totalSeguros += (float) $veiculo['valor_seguro_terceiros'];
            }
        }

        // Calcular totais de veiculos
        $valorPorPeriodo = $totalVeiculos + $totalSeguros;
        $dias = (int) $contrato['dias'];
        $totalVeiculosPeriodo = $valorPorPeriodo * $dias;

        // Calcular dias reais para taxas PER (mesmo calculo que o JS)
        $multiplicadores = ['dia' => 1, 'semana' => 7, 'mes' => 30, 'ano' => 365];
        $contagem = $contrato['contagem'] ?? 'dia';
        $diasReais = $dias * ($multiplicadores[$contagem] ?? 1);

        // Calcular total taxas usando regras de calculo (base_calculo/tipo_valor)
        $totalTaxas = 0;
        foreach ($taxas as $taxa) {
            $valorCalculado = $contratoTaxa->calcularValorTotalTaxa($taxa, $diasReais, $totalVeiculosPeriodo);
            $totalTaxas += $valorCalculado;

            // Atualizar valor_total armazenado se divergir
            if (abs($valorCalculado - (float) $taxa['valor_total']) > 0.01) {
                $contratoTaxa->atualizarValorTotal((int) $taxa['id'], $valorCalculado);
            }
        }

        $totalFatura = $totalVeiculosPeriodo + $totalTaxas;
        $desconto = (float) $contrato['valor_desconto'];
        $totalPagar = $totalFatura - $desconto;

        // Atualizar contrato
        $this->qb
            ->table('contratos')
            ->where('id', '=', $id)
            ->update([
                'total_fatura' => $totalFatura,
                'total_pagar' => $totalPagar,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        return [
            'total_veiculos' => $totalVeiculos,
            'total_seguros' => $totalSeguros,
            'total_taxas' => $totalTaxas,
            'valor_por_periodo' => $valorPorPeriodo,
            'total_fatura' => $totalFatura,
            'desconto' => $desconto,
            'total_pagar' => $totalPagar
        ];
    }

    /**
     * Lista contratos proximos a vencer (autorenovacao)
     *
     * @param string $chave Chave do tenant
     * @param int $diasAntecedencia Dias de antecedencia
     * @return array Lista de contratos
     */
    public function listarProximosVencer(string $chave, int $diasAntecedencia = 7): array
    {
        $dataLimite = date('Y-m-d', strtotime("+{$diasAntecedencia} days"));

        return $this->qb
            ->table('contratos', 'c')
            ->select([
                'c.*',
                'cl.nome_rsocial AS cliente_nome',
                'cl.telefone AS cliente_telefone'
            ])
            ->leftJoin('clientes', 'cl', 'c.id_cliente', '=', 'cl.id')
            ->where('c.status', '=', 'A')
            ->where('c.auto_renovacao', '=', 'auto')
            ->whereNotNull('c.data_renovacao')
            ->where('c.data_renovacao', '<=', $dataLimite)
            ->where('c.data_renovacao', '>=', date('Y-m-d'))
            ->orderBy('c.data_renovacao', 'ASC')
            ->get();
    }

    /**
     * Lista contratos vencidos
     *
     * @param string $chave Chave do tenant
     * @return array Lista de contratos
     */
    public function listarVencidos(string $chave): array
    {
        return $this->qb
            ->table('contratos', 'c')
            ->select([
                'c.*',
                'cl.nome_rsocial AS cliente_nome'
            ])
            ->leftJoin('clientes', 'cl', 'c.id_cliente', '=', 'cl.id')
            ->where('c.status', '=', 'A')
            ->where('c.auto_renovacao', '=', 'auto')
            ->whereNotNull('c.data_renovacao')
            ->where('c.data_renovacao', '<', date('Y-m-d'))
            ->orderBy('c.data_renovacao', 'ASC')
            ->get();
    }

    /**
     * Calcula a regularizacao de autorenovacao aplicando ciclos de dias + contagem
     * ate que a proxima renovacao fique a frente da data de referencia.
     *
     * @param array $contrato Dados do contrato
     * @param string|null $dataReferencia Data base Y-m-d; padrao hoje
     * @return array Dados calculados da regularizacao
     */
    public function calcularRegularizacaoAutorenovacao(array $contrato, ?string $dataReferencia = null): array
    {
        if (($contrato['status'] ?? '') !== 'A') {
            throw new \InvalidArgumentException('Apenas contratos ativos podem ser regularizados');
        }

        if (($contrato['auto_renovacao'] ?? '') !== 'auto') {
            throw new \InvalidArgumentException('Contrato nao possui autorrenovacao ativa');
        }

        if (empty($contrato['data_ini']) || empty($contrato['data_fim']) || empty($contrato['data_renovacao'])) {
            throw new \InvalidArgumentException('Contrato nao possui datas suficientes para regularizacao');
        }

        $quantidade = max(1, (int) ($contrato['dias'] ?? 1));
        $contagem = $contrato['contagem'] ?? 'dia';
        $referencia = new \DateTime($dataReferencia ?? date('Y-m-d'));
        $referencia->setTime(0, 0, 0);

        $dataIni = new \DateTime($contrato['data_ini']);
        $dataFim = new \DateTime($contrato['data_fim']);
        $dataRenovacao = new \DateTime($contrato['data_renovacao']);
        $dataRenovacao->setTime(0, 0, 0);

        $dataIniOriginal = clone $dataIni;
        $dataFimOriginal = clone $dataFim;
        $dataRenovacaoOriginal = clone $dataRenovacao;

        $ciclos = 0;
        while ($dataRenovacao <= $referencia) {
            $this->avancarDataPorCiclo($dataIni, $quantidade, $contagem);
            $this->avancarDataPorCiclo($dataFim, $quantidade, $contagem);
            $this->avancarDataPorCiclo($dataRenovacao, $quantidade, $contagem);
            $ciclos++;

            if ($ciclos > 240) {
                throw new \RuntimeException('Limite de ciclos excedido ao calcular autorrenovacao');
            }
        }

        return [
            'ciclos' => $ciclos,
            'quantidade' => $quantidade,
            'contagem' => $contagem,
            'data_referencia' => $referencia->format('Y-m-d'),
            'data_ini_atual' => $dataIniOriginal->format('Y-m-d H:i:s'),
            'data_fim_atual' => $dataFimOriginal->format('Y-m-d H:i:s'),
            'data_renovacao_atual' => $dataRenovacaoOriginal->format('Y-m-d'),
            'nova_data_ini' => $dataIni->format('Y-m-d H:i:s'),
            'nova_data_fim' => $dataFim->format('Y-m-d H:i:s'),
            'nova_data_renovacao' => $dataRenovacao->format('Y-m-d'),
        ];
    }

    /**
     * Aplica um ciclo de renovacao em uma data.
     */
    private function avancarDataPorCiclo(\DateTime $data, int $quantidade, string $contagem): void
    {
        switch ($contagem) {
            case 'dia':
                $data->modify("+{$quantidade} days");
                break;
            case 'semana':
                $data->modify("+{$quantidade} weeks");
                break;
            case 'mes':
                $data->modify("+{$quantidade} months");
                break;
            case 'ano':
                $data->modify("+{$quantidade} years");
                break;
            default:
                $data->modify("+{$quantidade} days");
                break;
        }
    }

    /**
     * Verifica vinculos que impedem exclusao
     *
     * @param int $id ID do contrato
     * @return array ['temVinculos' => bool, 'detalhes' => [...]]
     */
    public function verificarVinculos(int $id): array
    {
        $vinculos = [];

        // Lancamentos financeiros
        $financeiros = $this->qb
            ->table('financeiro')
            ->where('id_contrato', '=', $id)
            ->count();

        if ($financeiros > 0) {
            $vinculos[] = "{$financeiros} lancamento(s) financeiro(s)";
        }

        // Veiculos
        $veiculos = $this->qb
            ->table('contratos_veiculos')
            ->where('id_contrato', '=', $id)
            ->count();

        if ($veiculos > 0) {
            $vinculos[] = "{$veiculos} veiculo(s) vinculado(s)";
        }

        return [
            'temVinculos' => count($vinculos) > 0,
            'detalhes' => $vinculos,
        ];
    }

    // ==================== GESTÃO FINANCEIRA DO CONTRATO ====================

    /**
     * Lista parcelas financeiras vinculadas ao contrato
     *
     * @param int $contratoId ID do contrato
     * @return array Lista de parcelas com dados relacionados
     */
    public function listarParcelasContrato(int $contratoId): array
    {
        return $this->qb
            ->table('financeiro', 'f')
            ->select([
                'f.id',
                'f.codigo',
                'f.parcela',
                'f.total_parcelas',
                'f.data_venci',
                'f.data_pago',
                'f.valor_subtotal',
                'f.juros',
                'f.multa',
                'f.desconto',
                'f.valor_total',
                'f.pago',
                'f.id_conta',
                'f.id_forma_pagamento',
                'cb.nome AS conta_nome',
                'fp.nome AS forma_pagamento_nome'
            ])
            ->leftJoin('contas_bancarias', 'cb', 'f.id_conta', '=', 'cb.id')
            ->leftJoin('formas_pagamento', 'fp', 'f.id_forma_pagamento', '=', 'fp.id')
            ->where('f.id_contrato', '=', $contratoId)
            ->orderBy('f.parcela', 'ASC')
            ->get();
    }

    /**
     * Retorna resumo financeiro do contrato
     *
     * @param int $contratoId ID do contrato
     * @return array Resumo com totais
     */
    public function resumoFinanceiroContrato(int $contratoId): array
    {
        $contrato = $this->buscarPorId($contratoId);
        if (!$contrato) {
            return [];
        }

        // Buscar totais das parcelas
        $totais = $this->qb
            ->table('financeiro')
            ->selectRaw('
                COUNT(*) AS total_parcelas,
                SUM(valor_total) AS total_lancado,
                SUM(CASE WHEN pago = "S" THEN valor_total ELSE 0 END) AS total_pago,
                SUM(CASE WHEN pago = "N" THEN valor_total ELSE 0 END) AS total_pendente,
                SUM(CASE WHEN pago = "N" AND data_venci < CURDATE() THEN valor_total ELSE 0 END) AS total_atrasado
            ')
            ->where('id_contrato', '=', $contratoId)
            ->first();

        return [
            'total_contrato' => (float) $contrato['total_pagar'],
            'total_lancado' => (float) ($totais['total_lancado'] ?? 0),
            'total_pago' => (float) ($totais['total_pago'] ?? 0),
            'total_pendente' => (float) ($totais['total_pendente'] ?? 0),
            'total_atrasado' => (float) ($totais['total_atrasado'] ?? 0),
            'total_parcelas' => (int) ($totais['total_parcelas'] ?? 0),
            'diferenca' => (float) $contrato['total_pagar'] - (float) ($totais['total_lancado'] ?? 0),
        ];
    }

    /**
     * Gera preview das parcelas (sem salvar no banco)
     *
     * @param int $contratoId ID do contrato
     * @param array $config Configuração de parcelamento
     * @return array Parcelas geradas para preview
     */
    public function gerarPreviewParcelas(int $contratoId, array $config): array
    {
        if ($contratoId > 0) {
            $contrato = $this->buscarPorId($contratoId);
            if (!$contrato) {
                throw new \InvalidArgumentException('Contrato não encontrado');
            }
            $valorTotal = (float) $contrato['total_pagar'];
            $dataFim = $config['data_fim'] ?? $contrato['data_fim'] ?? date('Y-m-d');
        } else {
            // Modo stateless: valores vem do config (contrato ainda nao salvo)
            $contrato = [];
            $valorTotal = (float) ($config['total_pagar'] ?? 0);
            $dataFim = $config['data_fim'] ?? date('Y-m-d');
        }

        $valorDesconto = currency_parse($config['valor_desconto'] ?? 0);
        $valorBase = $valorTotal - $valorDesconto;
        $idFormaPagamento = (int) ($config['id_forma_pagamento'] ?? 0);
        $idConta = (int) ($config['id_conta'] ?? 0);
        $primeiroVencimento = $config['primeiro_vencimento'] ?? date('Y-m-d');

        // Buscar comando de parcelas
        $formaPagamento = new FormaPagamento();
        $idComandoParcela = (int) ($config['id_comando_parcela'] ?? 0);
        $comandoStr = '0';
        if ($idComandoParcela > 0) {
            $comandoModel = new ComandoParcela();
            $comandoRegistro = $comandoModel->buscarPorId($idComandoParcela);
            $comandoStr = $comandoRegistro['comando'] ?? '0';
        }

        // Calcular numero de parcelas automaticamente ou usar valor fornecido
        if (!empty($config['num_parcelas'])) {
            $numParcelas = (int) $config['num_parcelas'];
        } elseif ($comandoStr) {
            $numParcelas = ComandoParcela::calcularNumParcelasAutomatico($comandoStr, $primeiroVencimento, $dataFim);
        } else {
            $numParcelas = 1;
        }

        // Calcular taxas da forma de pagamento
        $taxas = [];
        if ($idFormaPagamento > 0) {
            $taxas = $formaPagamento->calcularTaxas($idFormaPagamento, $valorBase, $numParcelas);
        }

        // Calcular datas de vencimento
        $datas = [];
        if ($idComandoParcela > 0 && $comandoStr) {
            $datas = ComandoParcela::calcularDatasVencimento($comandoStr, $primeiroVencimento, $numParcelas);
        } else {
            // Fallback: parcelas mensais
            $base = new \DateTime($primeiroVencimento);
            for ($i = 0; $i < $numParcelas; $i++) {
                $data = clone $base;
                $data->modify("+{$i} months");
                $datas[] = $data->format('Y-m-d');
            }
        }

        // Calcular valor de cada parcela
        $valorParcela = !empty($taxas['valor_parcela_com_taxa'])
            ? $taxas['valor_parcela_com_taxa']
            : round($valorBase / max(1, $numParcelas), 2);

        // Ajustar última parcela para cobrir diferença de arredondamento
        $valorTotal = round($valorParcela * $numParcelas, 2);
        $diferenca = round($valorBase + ($taxas['taxa_total'] ?? 0) - $valorTotal, 2);

        // Buscar nomes da forma de pagamento e conta bancaria
        $formaNome = '';
        if ($idFormaPagamento > 0) {
            $fp = $formaPagamento->buscarPorId($idFormaPagamento);
            $formaNome = $fp['nome'] ?? '';
        }
        $contaNome = '';
        if ($idConta > 0) {
            $conta = (new ContaBancaria())->buscarPorId($idConta);
            $contaNome = $conta['nome'] ?? '';
        }

        // Gerar parcelas
        $parcelas = [];
        for ($i = 0; $i < $numParcelas; $i++) {
            $valor = $valorParcela;
            if ($i === $numParcelas - 1 && $diferenca != 0) {
                $valor = round($valor + $diferenca, 2);
            }

            // Verificar se há override individual
            $overrideConta = $config['parcelas'][$i]['id_conta'] ?? $idConta;
            $overrideForma = $config['parcelas'][$i]['id_forma_pagamento'] ?? $idFormaPagamento;

            $parcelas[] = [
                'parcela' => $i + 1,
                'total_parcelas' => $numParcelas,
                'id_conta' => (int) $overrideConta,
                'id_forma_pagamento' => (int) $overrideForma,
                'forma_pagamento_nome' => $formaNome,
                'conta_nome' => $contaNome,
                'data_venci' => $datas[$i] ?? date('Y-m-d'),
                'valor_subtotal' => $valor,
                'valor_total' => $valor,
            ];
        }

        return [
            'parcelas' => $parcelas,
            'resumo' => [
                'valor_contrato' => $valorTotal,
                'desconto' => $valorDesconto,
                'valor_base' => $valorBase,
                'taxa_total' => $taxas['taxa_total'] ?? 0,
                'valor_final' => $taxas['valor_final'] ?? $valorBase,
                'num_parcelas' => $numParcelas,
            ],
        ];
    }

    /**
     * Salva parcelas financeiras do contrato
     *
     * @param int $contratoId ID do contrato
     * @param array $parcelas Lista de parcelas a salvar
     * @param string $chave Chave do tenant
     * @return array IDs das parcelas criadas
     */
    public function salvarParcelasContrato(int $contratoId, array $parcelas, string $chave): array
    {
        $contrato = $this->buscarPorId($contratoId);
        if (!$contrato) {
            throw new \InvalidArgumentException('Contrato não encontrado');
        }

        $ids = [];
        $financeiroModel = new Financeiro();
        $idPrimeiraParcela = null;

        $contratoVeiculoModel = new ContratoVeiculo();
        $veiculoAtivo = $contratoVeiculoModel->buscarAtivo($contratoId);
        $idVeiculoAtivo = $veiculoAtivo ? (int) $veiculoAtivo['id_veiculo'] : null;

        foreach ($parcelas as $index => $parcela) {
            $dados = [
                'chave' => $chave,
                'id_contrato' => $contratoId,
                'id_veiculo' => $idVeiculoAtivo,
                'id_cliente' => $contrato['id_cliente'],
                'id_conta' => $parcela['id_conta'] ?? null,
                'id_forma_pagamento' => $parcela['id_forma_pagamento'] ?? null,
                'tipo' => 'R', // Receita
                'pago' => 'N',
                'parcela' => $parcela['parcela'],
                'total_parcelas' => $parcela['total_parcelas'],
                'descricao' => "Contrato #{$contrato['codigo']} - Parcela {$parcela['parcela']}/{$parcela['total_parcelas']}",
                'data_venci' => $parcela['data_venci'],
                'valor_subtotal' => $parcela['valor_subtotal'],
                'valor_total' => $parcela['valor_total'] ?? $parcela['valor_subtotal'],
                'id_financeiro_origem' => $idPrimeiraParcela, // NULL para primeira, ID para demais
            ];

            $id = $financeiroModel->criar($dados);
            $ids[] = $id;

            // Guardar ID da primeira parcela para vincular as demais
            if ($index === 0) {
                $idPrimeiraParcela = $id;
            }
        }

        return $ids;
    }

    /**
     * Remove todas as parcelas pendentes do contrato
     *
     * @param int $contratoId ID do contrato
     * @return int Quantidade de parcelas removidas
     */
    public function limparParcelasPendentes(int $contratoId): int
    {
        return $this->qb
            ->table('financeiro')
            ->where('id_contrato', '=', $contratoId)
            ->where('pago', '=', 'N')
            ->delete();
    }

    /**
     * Recalcula parcelas pendentes quando valor do contrato muda
     *
     * @param int $contratoId ID do contrato
     * @param string $acao 'recalcular' ou 'manter'
     * @return array Resultado da operação
     */
    public function recalcularParcelasContrato(int $contratoId, string $acao = 'recalcular'): array
    {
        $contrato = $this->buscarPorId($contratoId);
        if (!$contrato) {
            return ['success' => false, 'message' => 'Contrato não encontrado'];
        }

        $resumo = $this->resumoFinanceiroContrato($contratoId);
        $diferenca = $resumo['diferenca'];

        if (abs($diferenca) < 0.01) {
            return [
                'success' => true,
                'message' => 'Valores já estão sincronizados',
                'diferenca' => 0,
            ];
        }

        if ($acao === 'manter') {
            return [
                'success' => true,
                'message' => 'Parcelas mantidas com diferença de R$ ' . number_format($diferenca, 2, ',', '.'),
                'diferenca' => $diferenca,
                'acao' => 'manter',
            ];
        }

        // Recalcular: distribuir diferença nas parcelas pendentes
        $parcelasPendentes = $this->qb
            ->table('financeiro')
            ->select(['id', 'valor_subtotal', 'valor_total'])
            ->where('id_contrato', '=', $contratoId)
            ->where('pago', '=', 'N')
            ->orderBy('parcela', 'ASC')
            ->get();

        if (empty($parcelasPendentes)) {
            return [
                'success' => false,
                'message' => 'Não há parcelas pendentes para recalcular',
                'diferenca' => $diferenca,
            ];
        }

        $qtdPendentes = count($parcelasPendentes);
        $ajustePorParcela = round($diferenca / $qtdPendentes, 2);
        $ajusteAcumulado = 0;

        foreach ($parcelasPendentes as $index => $parcela) {
            $ajuste = $ajustePorParcela;

            // Na última parcela, ajustar para cobrir diferença de arredondamento
            if ($index === $qtdPendentes - 1) {
                $ajuste = round($diferenca - $ajusteAcumulado, 2);
            }

            $novoValor = round((float) $parcela['valor_subtotal'] + $ajuste, 2);

            $this->qb
                ->table('financeiro')
                ->where('id', '=', $parcela['id'])
                ->update([
                    'valor_subtotal' => $novoValor,
                    'valor_total' => $novoValor,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $ajusteAcumulado += $ajuste;
        }

        return [
            'success' => true,
            'message' => "Recalculadas {$qtdPendentes} parcela(s)",
            'diferenca_original' => $diferenca,
            'parcelas_ajustadas' => $qtdPendentes,
            'acao' => 'recalcular',
        ];
    }

    /**
     * Adiciona parcela avulsa ao contrato
     *
     * @param int $contratoId ID do contrato
     * @param array $dados Dados da parcela
     * @param string $chave Chave do tenant
     * @return int ID da parcela criada
     */
    public function adicionarParcelaAvulsa(int $contratoId, array $dados, string $chave): int
    {
        $contrato = $this->buscarPorId($contratoId);
        if (!$contrato) {
            throw new \InvalidArgumentException('Contrato não encontrado');
        }

        // Buscar próximo número de parcela
        $maxParcela = $this->qb
            ->table('financeiro')
            ->where('id_contrato', '=', $contratoId)
            ->max('parcela');

        $proximaParcela = ($maxParcela ?? 0) + 1;

        // Buscar total_parcelas atual
        $totalParcelas = $this->qb
            ->table('financeiro')
            ->select(['total_parcelas'])
            ->where('id_contrato', '=', $contratoId)
            ->orderBy('id', 'DESC')
            ->first();

        $totalAtual = (int) ($totalParcelas['total_parcelas'] ?? 0);
        $novoTotal = max($totalAtual, $proximaParcela);

        // Atualizar total_parcelas de todas as parcelas existentes
        $this->qb
            ->table('financeiro')
            ->where('id_contrato', '=', $contratoId)
            ->update(['total_parcelas' => $novoTotal]);

        $financeiroModel = new Financeiro();

        $contratoVeiculoModel = new ContratoVeiculo();
        $veiculoAtivo = $contratoVeiculoModel->buscarAtivo($contratoId);
        $idVeiculoAtivo = $veiculoAtivo ? (int) $veiculoAtivo['id_veiculo'] : null;

        return $financeiroModel->criar([
            'chave' => $chave,
            'id_contrato' => $contratoId,
            'id_veiculo' => $idVeiculoAtivo,
            'id_cliente' => $contrato['id_cliente'],
            'id_conta' => $dados['id_conta'] ?? null,
            'id_forma_pagamento' => $dados['id_forma_pagamento'] ?? null,
            'tipo' => 'R',
            'pago' => 'N',
            'parcela' => $proximaParcela,
            'total_parcelas' => $novoTotal,
            'descricao' => $dados['descricao'] ?? "Contrato #{$contrato['codigo']} - Parcela avulsa {$proximaParcela}",
            'data_venci' => $dados['data_venci'],
            'valor_subtotal' => currency_parse($dados['valor']),
            'valor_total' => currency_parse($dados['valor']),
        ]);
    }

    /**
     * Busca contratos para select (chosen-select)
     *
     * @param string $termo Termo de busca
     * @param string $chave Chave do tenant
     * @param string $filialWhere Filtro de filial
     * @param array $filialParams Parametros do filtro
     * @return array Lista de contratos formatados para select
     */
    public function buscarParaSelect(
        string $termo,
        string $chave,
        string $filialWhere = '',
        array $filialParams = []
    ): array {
        $query = $this->qb
            ->table('contratos', 'c')
            ->select([
                'c.id',
                'c.codigo',
                'cl.nome_rsocial AS cliente_nome'
            ])
            ->leftJoin('clientes', 'cl', 'c.id_cliente', '=', 'cl.id');

        // Filtro de busca
        if (!empty($termo)) {
            $searchTerm = '%' . $termo . '%';
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('c.codigo', 'LIKE', $searchTerm)
                  ->orWhere('cl.nome_rsocial', 'LIKE', $searchTerm);
            });
        }

        // Filtro de filial
        if (!empty($filialWhere)) {
            $query->whereRaw($filialWhere, $filialParams);
        }

        $contratos = $query
            ->orderByDesc('c.id')
            ->limit(30)
            ->get();

        // Formatar para chosen-select: id e text
        return array_map(function ($c) {
            return [
                'id' => $c['codigo'],
                'text' => $c['codigo'] . ' - ' . ($c['cliente_nome'] ?? 'Sem cliente')
            ];
        }, $contratos);
    }

    /**
     * Contadores de contratos para o dashboard.
     *
     * Retorna total ativos e quantos vencem nos proximos 7 dias.
     */
    public function dashboardSummary(string $chave): array
    {
        $row = $this->qb
            ->table('contratos')
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status='A' THEN 1 ELSE 0 END), 0) AS active,
                COALESCE(SUM(CASE WHEN status='A' AND data_fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS expiring_soon
            ")
            ->first();

        return [
            'active' => (int) ($row['active'] ?? 0),
            'expiring_soon' => (int) ($row['expiring_soon'] ?? 0),
        ];
    }
}
