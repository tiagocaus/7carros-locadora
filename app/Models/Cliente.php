<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\DetectsCrossTenant;

/**
 * Model Cliente
 *
 * Gerencia operações CRUD na tabela clientes
 */
class Cliente extends Model
{
    use Auditable;
    use DetectsCrossTenant;

    /**
     * Retorna o nome da entidade para auditoria
     */
    protected function getEntidadeAuditoria(): string
    {
        return 'o cliente';
    }

    /**
     * Retorna o campo identificador para auditoria
     */
    protected function getCampoIdentificador(): string
    {
        return 'nome_rsocial';
    }

    /**
     * Lista todos os clientes do tenant atual
     *
     * @param string|null $where Condição WHERE adicional
     * @param array $params Parâmetros para prepared statement
     * @param string|null $orderBy Ordenação (ex: 'nome_rsocial ASC')
     * @return array Lista de clientes
     */
    public function listar(?string $where = null, array $params = [], ?string $orderBy = 'nome_rsocial ASC'): array
    {
        $query = $this->qb
            ->table('clientes')
            ->select(['id', 'nome_rsocial', 'cpf_cnpj', 'situacao', 'foto']);

        if (!empty($where)) {
            $query->whereRaw($where, $params);
        }

        if (!empty($orderBy)) {
            $query->orderByRaw($orderBy);
        }

        return $query->get();
    }

    /**
     * Busca um cliente por ID
     *
     * @param int $id ID do cliente
     * @return array|null Dados do cliente ou null se não encontrado
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->qb
            ->table('clientes', 'c')
            ->select([
                'c.*',
                'mf.nome_fantasia as filial_nome'
            ])
            ->leftJoin('matrizes_filiais', 'mf', 'c.id_matriz_filial', '=', 'mf.id')
            ->where('c.id', '=', $id)
            ->first();
    }

    /**
     * Busca cliente por ID e hidrata com email e telefone principais
     * (vindos de contatos_emails / contatos_telefones).
     *
     * @return array|null Cliente com campos basicos + email + telefone (principais) ou null
     */
    public function buscarPorIdComContatos(int $id): ?array
    {
        $cliente = $this->buscarPorId($id);
        if (!$cliente) return null;

        $email = $this->qb
            ->table('contatos_emails')
            ->select(['email'])
            ->where('entidade_tipo', '=', 'cliente')
            ->where('entidade_id', '=', $id)
            ->orderByRaw("principal = 'S' DESC, id ASC")
            ->first();

        $telefone = $this->qb
            ->table('contatos_telefones')
            ->select(['telefone'])
            ->where('entidade_tipo', '=', 'cliente')
            ->where('entidade_id', '=', $id)
            ->orderByRaw("principal = 'S' DESC, id ASC")
            ->first();

        $cliente['email']    = $email['email']       ?? '';
        $cliente['telefone'] = $telefone['telefone'] ?? '';
        // Manter alias legado 'celular' apontando para o telefone principal.
        $cliente['celular']  = $cliente['telefone'];
        return $cliente;
    }

    /**
     * Cria um novo cliente
     *
     * @param array $dados Dados do cliente
     * @return int ID do cliente criado
     */
    public function criar(array $dados): int
    {
        // Adicionar data_cadastro automaticamente
        $dados['data_cadastro'] = today();

        // Definir situacao padrão se não fornecida
        if (!isset($dados['situacao'])) {
            $dados['situacao'] = 'A'; // Ativo
        }

        return $this->qb
            ->table('clientes')
            ->insert($dados);
    }

    /**
     * Busca documentos canonicos ja cadastrados no tenant atual.
     *
     * @param array<int,string> $documentos Documentos sem pontuacao
     * @return array<int,string>
     */
    public function buscarDocumentosExistentesParaImportacao(array $documentos): array
    {
        $encontrados = [];
        foreach (array_chunk(array_values(array_unique($documentos)), 200) as $lote) {
            if ($lote === []) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($lote), '?'));
            $rows = $this->qb
                ->table('clientes')
                ->select(['cpf_cnpj'])
                ->whereRaw(
                    "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '')) IN ({$placeholders})",
                    $lote
                )
                ->get();

            foreach ($rows as $row) {
                $encontrados[] = (string) ($row['cpf_cnpj'] ?? '');
            }
        }

        return $encontrados;
    }

    /**
     * Importa clientes e seus contatos principais em uma unica transacao.
     *
     * @param array<int,array> $registros
     */
    public function importarLote(array $registros): int
    {
        $this->qb->beginTransaction();

        try {
            foreach ($registros as $registro) {
                $email = $registro['_email'] ?? null;
                $telefone = $registro['_telefone'] ?? null;
                unset($registro['_email'], $registro['_telefone']);

                $clienteId = $this->criar($registro);

                if (is_array($email)) {
                    $this->qb
                        ->table('contatos_emails')
                        ->insert([
                            'entidade_tipo' => 'cliente',
                            'entidade_id' => $clienteId,
                            'email' => $email['email'],
                            'descricao' => $email['descricao'],
                            'principal' => 'S',
                            'recebe_email' => $email['recebe_email'],
                        ]);
                }

                if (is_array($telefone)) {
                    $this->qb
                        ->table('contatos_telefones')
                        ->insert([
                            'entidade_tipo' => 'cliente',
                            'entidade_id' => $clienteId,
                            'telefone' => $telefone['telefone'],
                            'descricao' => $telefone['descricao'],
                            'principal' => 'S',
                            'whatsapp' => $telefone['whatsapp'],
                            'telegram' => $telefone['telegram'],
                            'sms' => $telefone['sms'],
                        ]);
                }
            }

            $this->qb->commit();
            return count($registros);
        } catch (\Throwable $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Atualiza um cliente existente
     *
     * @param int $id ID do cliente
     * @param array $dados Dados para atualizar
     * @return int Número de linhas afetadas
     */
    public function atualizar(int $id, array $dados): int
    {
        return $this->qb
            ->table('clientes')
            ->where('id', '=', $id)
            ->update($dados);
    }

    /**
     * Exclui um cliente (soft delete não implementado, delete real)
     *
     * @param int $id ID do cliente
     * @return int Número de linhas afetadas
     */
    public function deletar(int $id): int
    {
        return $this->qb
            ->table('clientes')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Busca cliente para login publico do site — aceita CPF/CNPJ (so-digitos) OU email.
     * Retorna o registro com o hash de senha para verificacao.
     */
    public function buscarPorUsuarioParaLogin(string $usuario): ?array
    {
        $usuario = trim($usuario);
        if ($usuario === '') return null;

        $soDigitos = preg_replace('/\D/', '', $usuario);
        $parecerDocumento = $soDigitos !== '' && strlen($soDigitos) >= 11;

        // Por email: join com contatos_emails (entidade_tipo='cliente')
        if (!$parecerDocumento && filter_var($usuario, FILTER_VALIDATE_EMAIL)) {
            $row = $this->qb
                ->table('clientes', 'c')
                ->select(['c.id', 'c.nome_rsocial', 'c.senha'])
                ->join('contatos_emails', 'ce', 'ce.entidade_id', '=', 'c.id')
                ->where('ce.entidade_tipo', '=', 'cliente')
                ->where('ce.email', '=', $usuario)
                ->first();
            return $row ?: null;
        }

        // Por CPF/CNPJ
        if ($parecerDocumento) {
            $row = $this->qb
                ->table('clientes')
                ->select(['id', 'nome_rsocial', 'senha'])
                ->where('cpf_cnpj', '=', $soDigitos)
                ->first();
            return $row ?: null;
        }

        return null;
    }

    /**
     * Busca cliente por CPF/CNPJ exato (normaliza para so-digitos).
     * Junta email e telefone marcados como principais (contatos_emails/telefones).
     * Multi-tenancy: respeita $_SESSION['chave'] via QueryBuilder.
     *
     * @return array|null Cliente com campos basicos + email/telefone principais ou null
     */
    public function buscarPorDocumentoExato(string $documento): ?array
    {
        $doc = preg_replace('/\D/', '', $documento);
        if (!$doc || strlen($doc) < 11) return null;

        $cliente = $this->qb
            ->table('clientes')
            ->select(['id', 'nome_rsocial', 'cpf_cnpj', 'cep', 'rua', 'numero', 'bairro', 'cidade', 'estado', 'pais'])
            ->where('cpf_cnpj', '=', $doc)
            ->first();

        if (!$cliente) return null;

        $email = $this->qb
            ->table('contatos_emails')
            ->select(['email'])
            ->where('entidade_tipo', '=', 'cliente')
            ->where('entidade_id', '=', (int) $cliente['id'])
            ->orderByRaw("principal = 'S' DESC, id ASC")
            ->first();

        $telefone = $this->qb
            ->table('contatos_telefones')
            ->select(['telefone'])
            ->where('entidade_tipo', '=', 'cliente')
            ->where('entidade_id', '=', (int) $cliente['id'])
            ->orderByRaw("principal = 'S' DESC, id ASC")
            ->first();

        $cliente['email']    = $email['email']       ?? '';
        $cliente['telefone'] = $telefone['telefone'] ?? '';
        return $cliente;
    }

    /**
     * Busca cliente por documento para prevenir cadastro duplicado na tela interna.
     *
     * @param string|null $extraWhere Condição WHERE adicional (ex: filtro de filiais)
     * @param array $extraParams Parâmetros para o WHERE adicional
     */
    public function buscarPorDocumentoCadastro(string $documento, ?string $extraWhere = null, array $extraParams = []): ?array
    {
        $documento = trim($documento);
        if ($documento === '') {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $documento);
        $query = $this->qb
            ->table('clientes')
            ->select(['id', 'nome_rsocial', 'cpf_cnpj', 'id_matriz_filial']);

        if ($digitos !== '' && strlen($digitos) >= 11) {
            $query->whereRaw(
                "(REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') = ? OR cpf_cnpj = ?)",
                [$digitos, $documento]
            );
        } else {
            $query->where('cpf_cnpj', '=', $documento);
        }

        if (!empty($extraWhere) && $extraWhere !== '1=1') {
            $query->whereRaw($extraWhere, $extraParams);
        }

        return $query->first();
    }

    /**
     * Busca clientes por termo de pesquisa
     *
     * @param string $termo Termo para buscar em nome, email ou CPF/CNPJ
     * @param string|null $extraWhere Condição WHERE adicional (ex: filtro de filiais)
     * @param array $extraParams Parâmetros para o WHERE adicional
     * @return array Lista de clientes encontrados
     */
    public function buscar(string $termo, ?string $extraWhere = null, array $extraParams = []): array
    {
        $searchTerm = "%{$termo}%";

        $query = $this->qb
            ->table('clientes')
            ->select(['id', 'nome_rsocial AS nome', 'cpf_cnpj', 'situacao'])
            ->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm);
            });

        // Se houver filtro extra (ex: filiais), adicionar
        if (!empty($extraWhere) && $extraWhere !== '1=1') {
            $query->whereRaw($extraWhere, $extraParams);
        }

        return $query
            ->orderBy('nome_rsocial', 'ASC')
            ->get();
    }

    /**
     * Lista clientes com paginação e busca
     *
     * @param int $page Página atual (começa em 1)
     * @param int $perPage Registros por página
     * @param string|null $search Termo de busca (opcional)
     * @param string|null $extraWhere Condição WHERE adicional (ex: filtro de filiais)
     * @param array $extraParams Parâmetros para o WHERE adicional
     * @return array Lista de clientes da página
     */
    public function listarPaginado(
        int $page = 1,
        int $perPage = 10,
        ?string $search = null,
        ?string $extraWhere = null,
        array $extraParams = []
    ): array {
        $query = $this->qb
            ->table('clientes')
            ->select(['id', 'nome_rsocial', 'cpf_cnpj', 'situacao', 'foto']);

        // Se houver termo de busca, adicionar condição WHERE
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm);
            });
        }

        // Se houver filtro extra (ex: filiais), adicionar
        if (!empty($extraWhere) && $extraWhere !== '1=1') {
            $query->whereRaw($extraWhere, $extraParams);
        }

        return $query
            ->orderBy('nome_rsocial', 'ASC')
            ->paginate($page, $perPage)
            ->get();
    }

    /**
     * Conta total de clientes (com filtro de busca opcional)
     *
     * @param string|null $search Termo de busca (opcional)
     * @param string|null $extraWhere Condição WHERE adicional (ex: filtro de filiais)
     * @param array $extraParams Parâmetros para o WHERE adicional
     * @return int Total de clientes
     */
    public function contar(?string $search = null, ?string $extraWhere = null, array $extraParams = []): int
    {
        $query = $this->qb->table('clientes');

        // Se houver termo de busca, adicionar condição WHERE
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereNested(function ($q) use ($searchTerm) {
                $q->where('nome_rsocial', 'LIKE', $searchTerm)
                  ->orWhere('cpf_cnpj', 'LIKE', $searchTerm);
            });
        }

        // Se houver filtro extra (ex: filiais), adicionar
        if (!empty($extraWhere) && $extraWhere !== '1=1') {
            $query->whereRaw($extraWhere, $extraParams);
        }

        return $query->count();
    }

    /**
     * Verifica vínculos do cliente com outras tabelas
     *
     * @param int $id ID do cliente
     * @return array Array com 'temVinculos' (bool) e 'detalhes' (array de contagens)
     */
    public function verificarVinculos(int $id): array
    {
        $vinculos = [];
        $temVinculos = false;

        // Verificar contratos
        $countContratos = $this->qb
            ->table('contratos')
            ->where('id_cliente', '=', $id)
            ->count();

        if ($countContratos > 0) {
            $vinculos['contratos'] = $countContratos;
            $temVinculos = true;
        }

        // Verificar locações
        $countLocacoes = $this->qb
            ->table('locacoes')
            ->where('id_cliente', '=', $id)
            ->count();

        if ($countLocacoes > 0) {
            $vinculos['locacoes'] = $countLocacoes;
            $temVinculos = true;
        }

        // Verificar financeiro
        $countFinanceiro = $this->qb
            ->table('financeiro')
            ->where('id_cliente', '=', $id)
            ->count();

        if ($countFinanceiro > 0) {
            $vinculos['financeiro'] = $countFinanceiro;
            $temVinculos = true;
        }

        // Verificar promissórias
        $countPromissorias = $this->qb
            ->table('promissorias')
            ->where('id_cliente', '=', $id)
            ->count();

        if ($countPromissorias > 0) {
            $vinculos['promissorias'] = $countPromissorias;
            $temVinculos = true;
        }

        // Verificar multas
        $countMultas = $this->qb
            ->table('multas')
            ->where('id_cliente', '=', $id)
            ->count();

        if ($countMultas > 0) {
            $vinculos['multas'] = $countMultas;
            $temVinculos = true;
        }

        return [
            'temVinculos' => $temVinculos,
            'detalhes' => $vinculos
        ];
    }

    /**
     * Lista arquivos do cliente
     *
     * @param int $id ID do cliente
     * @return array Lista de arquivos
     */
    public function listarArquivos(int $id): array
    {
        return $this->qb
            ->table('clientes_arquivos')
            ->select(['arquivo'])
            ->where('id_cliente', '=', $id)
            ->get();
    }

    /**
     * Exclui arquivos do cliente
     *
     * @param int $id ID do cliente
     * @return int Número de linhas afetadas
     */
    public function excluirArquivos(int $id): int
    {
        return $this->qb
            ->table('clientes_arquivos')
            ->where('id_cliente', '=', $id)
            ->delete();
    }

    /**
     * Tipos de arquivo disponíveis
     */
    public const TIPOS_ARQUIVO = [
        0 => 'Outros',
        1 => 'CNH',
        2 => 'CPF',
        3 => 'RG/Passaporte',
        4 => 'Comprovante de Endereço'
    ];

    /**
     * Status de arquivo
     */
    public const STATUS_ARQUIVO = [
        null => 'Aguardando',
        0 => 'Reprovado',
        1 => 'Aprovado'
    ];

    /**
     * Lista arquivos do cliente com todos os campos
     *
     * @param int $id ID do cliente
     * @return array Lista de arquivos com detalhes
     */
    public function listarArquivosCompleto(int $id): array
    {
        return $this->qb
            ->table('clientes_arquivos')
            ->select(['id', 'nome', 'arquivo', 'tipo', 'status', 'created_at', 'updated_at'])
            ->where('id_cliente', '=', $id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Busca um arquivo específico por ID
     *
     * @param int $arquivoId ID do arquivo
     * @return array|null Dados do arquivo ou null se não encontrado
     */
    public function buscarArquivo(int $arquivoId): ?array
    {
        return $this->qb
            ->table('clientes_arquivos')
            ->select(['id', 'id_cliente', 'nome', 'arquivo', 'tipo', 'status', 'created_at'])
            ->where('id', '=', $arquivoId)
            ->first();
    }

    /**
     * Insere um novo arquivo para o cliente
     *
     * @param int $clienteId ID do cliente
     * @param array $dados Dados do arquivo (nome, arquivo, tipo)
     * @return int ID do arquivo inserido
     */
    public function inserirArquivo(int $clienteId, array $dados): int
    {
        return $this->qb
            ->table('clientes_arquivos')
            ->insert([
                'chave' => \App\Core\Auth::chave(),
                'id_cliente' => $clienteId,
                'nome' => $dados['nome'],
                'arquivo' => $dados['arquivo'],
                'tipo' => $dados['tipo'],
                'status' => 1, // Aprovado por padrão quando enviado pelo sistema
                'created_at' => now()
            ]);
    }

    /**
     * Exclui um arquivo específico por ID
     *
     * @param int $arquivoId ID do arquivo
     * @return int Número de linhas afetadas
     */
    public function excluirArquivoPorId(int $arquivoId): int
    {
        return $this->qb
            ->table('clientes_arquivos')
            ->where('id', '=', $arquivoId)
            ->delete();
    }

    /**
     * Exclui cartões do cliente
     *
     * @param int $id ID do cliente
     * @return int Número de linhas afetadas
     */
    public function excluirCartoes(int $id): int
    {
        return $this->qb
            ->table('clientes_cartoes')
            ->where('id_cliente', '=', $id)
            ->delete();
    }

    /**
     * Lista financeiro do cliente
     *
     * @param int $id ID do cliente
     * @return array Lista de lançamentos financeiros
     */
    public function listarFinanceiro(int $id): array
    {
        return $this->qb
            ->table('financeiro')
            ->select(['id', 'sequencia', 'codigo', 'parcela', 'total_parcelas', 'descricao', 'tipo', 'pago', 'data_venci', 'data_pago', 'valor_total'])
            ->where('id_cliente', '=', $id)
            ->orderByDesc('data_venci')
            ->get();
    }

    /**
     * Conta clientes com CNH vencida.
     * Ignora empresas/PJ, datas zeradas e clientes inativos.
     */
    public function contarCnhVencidas(): int
    {
        return $this->qb
            ->table('clientes')
            ->whereNotNull('cnh_validade')
            ->whereRaw("(tipo IS NULL OR tipo <> 'PJ')")
            ->whereRaw("cnh_validade <> '0000-00-00'")
            ->whereRaw('cnh_validade < CURDATE()')
            ->whereRaw("(situacao IS NULL OR situacao <> 'I')")
            ->count();
    }

    /**
     * Lista clientes com CNH vencida para a tela de notificacoes.
     * Ignora empresas/PJ, datas zeradas e clientes inativos.
     */
    public function listarCnhVencidasParaNotificacoes(int $limit = 25, int $offset = 0): array
    {
        return $this->qb
            ->table('clientes')
            ->select(['id', 'nome_rsocial', 'cpf_cnpj', 'cnh_numero', 'cnh_categoria', 'cnh_validade'])
            ->whereNotNull('cnh_validade')
            ->whereRaw("(tipo IS NULL OR tipo <> 'PJ')")
            ->whereRaw("cnh_validade <> '0000-00-00'")
            ->whereRaw('cnh_validade < CURDATE()')
            ->whereRaw("(situacao IS NULL OR situacao <> 'I')")
            ->orderBy('cnh_validade', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }
}
