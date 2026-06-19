<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\MatrizFilial;
use App\Models\MatrizFilialLocal;
use App\Models\HorarioFuncionamento;
use App\Models\HorarioExcecao;
use App\Models\Feriado;
use App\Models\ContatoEmail;
use App\Models\ContatoTelefone;
use App\Helpers\FileHelper;
use App\Helpers\FilialHelper;
use App\Helpers\PlanoLimiteHelper;
use App\Services\AuditLogService;

/**
 * Controller de Matrizes/Filiais
 *
 * Gerencia operações CRUD de matrizes e filiais
 */
class MatrizFilialController
{
    /**
     * Lista todas as matrizes/filiais (com paginação e busca)
     *
     * GET /api/matrizes-filiais
     */
    public function index(Request $request): void
    {
        try {
            if (!Auth::can('matrizes_filiais.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar matrizes/filiais'
                ], 403);
                return;
            }

            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = $request->query('search', '');

            $model = new MatrizFilial();
            $registros = $model->listarPaginado($page, $perPage, $search);
            $total = $model->contar($search);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            // Adiciona logo_url para cada registro
            foreach ($registros as &$registro) {
                $registro['logo_url'] = !empty($registro['logo'])
                    ? FileHelper::url($registro['logo'], $registro['chave'])
                    : '';
            }
            unset($registro);

            Response::json([
                'success' => true,
                'data' => $registros,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasNext' => $page < $totalPages,
                    'hasPrev' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar matrizes/filiais: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibe uma matriz/filial específica
     *
     * GET /api/matrizes-filiais/{id}
     */
    public function show(Request $request, int $id): void
    {
        try {
            if (!Auth::can('matrizes_filiais.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar matrizes/filiais'
                ], 403);
                return;
            }

            $model = new MatrizFilial();
            $registro = $model->buscarPorId($id);

            if (!$registro) {
                Response::json([
                    'success' => false,
                    'message' => 'Matriz/Filial não encontrada'
                ], 404);
                return;
            }

            // Adiciona logo_url
            $registro['logo_url'] = !empty($registro['logo'])
                ? FileHelper::url($registro['logo'], $registro['chave'])
                : '';
            // Carregar horários de funcionamento
            $horarioModel = new HorarioFuncionamento();
            $registro['horarios_funcionamento'] = $horarioModel->getHorariosFormatados($id);

            // Carregar exceções de horário
            $excecaoModel = new HorarioExcecao();
            $registro['horarios_excecoes'] = $excecaoModel->listarPorMatriz($id);

            // Carregar próximos feriados como sugestões
            $feriadoModel = new Feriado();
            $registro['proximos_feriados'] = $feriadoModel->listarProximos(
                5,
                $registro['estado'] ?? null,
                $registro['cidade'] ?? null
            );

            // Carregar emails de contato
            $emailModel = new ContatoEmail();
            $registro['emails'] = $emailModel->listarPorEntidade('matriz_filial', $id);

            // Carregar telefones de contato
            $telefoneModel = new ContatoTelefone();
            $registro['telefones'] = $telefoneModel->listarPorEntidade('matriz_filial', $id);

            // Carregar locais de atendimento (aliases)
            $registro['locais'] = (new MatrizFilialLocal())->listarPorFilial($id);

            Response::json([
                'success' => true,
                'data' => $registro
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar matriz/filial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria uma nova matriz/filial
     *
     * POST /matrizes-filiais/salvar
     */
    public function store(Request $request): void
    {
        try {
            if (!Auth::can('matrizes_filiais.criar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para criar matrizes/filiais'
                ], 403);
                return;
            }

            // Verificar limite do plano
            if (!PlanoLimiteHelper::podeAdicionar('matrizfilial')) {
                $usage = PlanoLimiteHelper::getUsage('matrizfilial');
                Response::json([
                    'success' => false,
                    'message' => "Limite de matrizes/filiais atingido. Seu plano {$usage['plano']} permite apenas {$usage['limite']} matrizes/filiais.",
                    'limite_atingido' => true,
                    'redirect_url' => PlanoLimiteHelper::getRedirectSeAtingido('matrizfilial')
                ], 403);
                return;
            }

            $dados = $this->mapearDados($request);

            if (($dados['status'] ?? 'A') === 'I') {
                $dados['status'] = 'A';
            }

            // Processar upload de logo usando FileHelper
            $logoBase64 = $request->input('logo_base64', '');
            if (!empty($logoBase64)) {
                $filename = FileHelper::save($logoBase64, 'logo');
                if ($filename) {
                    $dados['logo'] = $filename;
                }
            }

            // Remover campos vazios
            $dados = array_filter($dados, function($value) {
                return $value !== '' && $value !== null;
            });

            $model = new MatrizFilial();
            $id = $model->criarComAuditoria($dados);

            // Salvar horários de funcionamento
            $horarios = $request->input('horarios_funcionamento', []);
            if (!empty($horarios) && is_array($horarios)) {
                $horarioModel = new HorarioFuncionamento();
                $horarioModel->salvar($id, $horarios);
            }

            // Salvar exceções de horário
            $excecoes = $request->input('horarios_excecoes', []);
            if (!empty($excecoes) && is_array($excecoes)) {
                $excecaoModel = new HorarioExcecao();
                foreach ($excecoes as $excecao) {
                    $excecao['matriz_filial_id'] = $id;
                    $excecaoModel->salvar($excecao);
                }
            }

            // Salvar emails de contato
            $emails = $request->input('emails', '');
            if (!empty($emails)) {
                $emailsArray = is_string($emails) ? json_decode($emails, true) : $emails;
                if (!empty($emailsArray) && is_array($emailsArray)) {
                    $emailModel = new ContatoEmail();
                    $emailModel->salvar('matriz_filial', $id, $emailsArray);
                }
            }

            // Salvar telefones de contato
            $telefones = $request->input('telefones', '');
            if (!empty($telefones)) {
                $telefonesArray = is_string($telefones) ? json_decode($telefones, true) : $telefones;
                if (!empty($telefonesArray) && is_array($telefonesArray)) {
                    $telefoneModel = new ContatoTelefone();
                    $telefoneModel->salvar('matriz_filial', $id, $telefonesArray);
                }
            }

            // Sincronizar locais de atendimento (aliases)
            $this->sincronizarLocais($request, $id, $dados['chave']);

            Response::json([
                'success' => true,
                'message' => 'Matriz/Filial criada com sucesso',
                'data' => ['id' => $id]
            ], 201);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar matriz/filial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza uma matriz/filial existente
     *
     * POST /matrizes-filiais/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            if (!Auth::can('matrizes_filiais.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para editar matrizes/filiais'
                ], 403);
                return;
            }

            $model = new MatrizFilial();
            $registroExistente = $model->buscarPorId($id);

            if (!$registroExistente) {
                Response::json([
                    'success' => false,
                    'message' => 'Matriz/Filial não encontrada'
                ], 404);
                return;
            }

            $dados = $this->mapearDados($request);

            if (
                ($registroExistente['status'] ?? 'A') === 'A'
                && ($dados['status'] ?? 'A') === 'I'
                && $model->contarAtivas($registroExistente['chave']) <= 1
            ) {
                Response::json([
                    'success' => false,
                    'message' => 'Não é possível desativar a última matriz/filial ativa'
                ], 422);
                return;
            }

            // Processar upload de logo usando FileHelper
            $logoBase64 = $request->input('logo_base64', '');
            if (!empty($logoBase64)) {
                // Apagar logo antigo
                if (!empty($registroExistente['logo'])) {
                    FileHelper::delete($registroExistente['logo'], $registroExistente['chave']);
                }

                $filename = FileHelper::save($logoBase64, 'logo');
                if ($filename) {
                    $dados['logo'] = $filename;
                }
            }

            // Remover campos não fornecidos
            $dados = array_filter($dados, function($value) {
                return $value !== null && $value !== '';
            });

            $model->atualizarComAuditoria($id, $dados);

            // Verificar se campos de sequência foram alterados e renumerar se necessário
            $tiposSequencia = ['locacoes', 'contratos', 'financeiro'];
            foreach ($tiposSequencia as $tipo) {
                $campo = "sequencia_{$tipo}";
                $valorAnterior = $registroExistente[$campo] ?? null;
                $valorNovo = $dados[$campo] ?? null;

                // Se o campo foi enviado e é diferente do anterior, renumerar a partir do valor definido
                if ($valorNovo !== null && (int) $valorNovo !== (int) $valorAnterior) {
                    \App\Helpers\SequenciaHelper::renumerarSequencias(
                        $registroExistente['chave'],
                        $id,
                        $tipo,
                        (int) $valorNovo  // Valor inicial definido pelo usuário
                    );
                }
            }

            // Atualizar horários de funcionamento
            $horarios = $request->input('horarios_funcionamento', null);
            if ($horarios !== null && is_array($horarios)) {
                $horarioModel = new HorarioFuncionamento();
                $horarioModel->salvar($id, $horarios);
            }

            // Atualizar exceções de horário
            $excecoes = $request->input('horarios_excecoes', null);
            if ($excecoes !== null && is_array($excecoes)) {
                $excecaoModel = new HorarioExcecao();
                // Remover exceções existentes e recriar
                $excecaoModel->excluirPorMatriz($id);
                foreach ($excecoes as $excecao) {
                    $excecao['matriz_filial_id'] = $id;
                    $excecaoModel->salvar($excecao);
                }
            }

            // Atualizar emails de contato
            $emails = $request->input('emails', null);
            if ($emails !== null) {
                $emailsArray = is_string($emails) ? json_decode($emails, true) : $emails;
                if (is_array($emailsArray)) {
                    $emailModel = new ContatoEmail();
                    $emailModel->salvar('matriz_filial', $id, $emailsArray);
                }
            }

            // Atualizar telefones de contato
            $telefones = $request->input('telefones', null);
            if ($telefones !== null) {
                $telefonesArray = is_string($telefones) ? json_decode($telefones, true) : $telefones;
                if (is_array($telefonesArray)) {
                    $telefoneModel = new ContatoTelefone();
                    $telefoneModel->salvar('matriz_filial', $id, $telefonesArray);
                }
            }

            // Sincronizar locais de atendimento (aliases)
            $this->sincronizarLocais($request, $id, $registroExistente['chave']);

            // Log de auditoria com dados do frontend
            AuditLogService::registrarComAuditFrontend(
                ($_SESSION['user_name'] ?? 'Sistema') . ", atualizou matriz/filial [{$registroExistente['nome_fantasia']}]",
                null,
                $request->input('_audit_changes')
            );

            Response::json([
                'success' => true,
                'message' => 'Matriz/Filial atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar matriz/filial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui uma matriz/filial
     *
     * POST /matrizes-filiais/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            if (!Auth::can('matrizes_filiais.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para excluir matrizes/filiais'
                ], 403);
                return;
            }

            $model = new MatrizFilial();
            $registro = $model->buscarPorId($id);

            if (!$registro) {
                Response::json([
                    'success' => false,
                    'message' => 'Matriz/Filial não encontrada'
                ], 404);
                return;
            }

            // Verificar vínculos
            $verificacao = $this->verificarVinculos($id);
            if ($verificacao['temVinculos']) {
                Response::json([
                    'success' => false,
                    'message' => 'Não é possível excluir esta matriz/filial pois existem registros vinculados.',
                    'vinculos' => $verificacao['detalhes'],
                    'pode_desativar' => true
                ], 422);
                return;
            }

            // Apagar logo usando FileHelper
            if (!empty($registro['logo'])) {
                FileHelper::delete($registro['logo'], $registro['chave']);
            }

            // Apagar assinatura usando FileHelper
            if (!empty($registro['assinatura'])) {
                FileHelper::delete($registro['assinatura'], $registro['chave']);
            }

            $model->deletarComAuditoria($id);

            Response::json([
                'success' => true,
                'message' => 'Matriz/Filial excluída com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao excluir matriz/filial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desativa uma matriz/filial com historico vinculado.
     *
     * POST /matrizes-filiais/{id}/desativar
     */
    public function desativar(Request $request, int $id): void
    {
        try {
            if (!Auth::can('matrizes_filiais.excluir')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para desativar matrizes/filiais'
                ], 403);
                return;
            }

            $model = new MatrizFilial();
            $registro = $model->buscarPorId($id);

            if (!$registro) {
                Response::json([
                    'success' => false,
                    'message' => 'Matriz/Filial não encontrada'
                ], 404);
                return;
            }

            if (($registro['status'] ?? 'A') === 'I') {
                Response::json([
                    'success' => true,
                    'message' => 'Matriz/Filial já está inativa'
                ]);
                return;
            }

            if ($model->contarAtivas($registro['chave']) <= 1) {
                Response::json([
                    'success' => false,
                    'message' => 'Não é possível desativar a última matriz/filial ativa'
                ], 422);
                return;
            }

            $model->desativar($id);

            AuditLogService::registrar(
                ($_SESSION['user_name'] ?? 'Sistema') . ", desativou matriz/filial [{$registro['nome_fantasia']}]"
            );

            Response::json([
                'success' => true,
                'message' => 'Matriz/Filial desativada com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao desativar matriz/filial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Busca matrizes/filiais
     *
     * GET /api/matrizes-filiais/buscar?termo=xxx
     */
    public function buscar(Request $request): void
    {
        try {
            if (!Auth::can('matrizes_filiais.visualizar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para buscar matrizes/filiais'
                ], 403);
                return;
            }

            $termo = $request->query('q', '');

            // Verificar se tem permissão de listar todas as matrizes/filiais
            if (Auth::can('matrizes_filiais.listar_todas')) {
                $filialWhere = null;
                $filialParams = [];
            } else {
                // Obter filtro de filiais permitidas
                [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id');
            }

            $model = new MatrizFilial();
            $registros = $model->buscar($termo, $filialWhere, $filialParams);

            Response::json([
                'success' => true,
                'data' => $registros
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar matrizes/filiais: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista opções para selects (locales, moedas, formatos de data)
     *
     * GET /api/matrizes-filiais/opcoes
     */
    public function opcoes(): void
    {
        try {
            Response::json([
                'success' => true,
                'data' => [
                    'locales' => [
                        ['value' => 'pt_BR', 'label' => 'Português (Brasil)'],
                        ['value' => 'en_US', 'label' => 'English (US)'],
                        ['value' => 'es_ES', 'label' => 'Español (España)'],
                        ['value' => 'pt_PT', 'label' => 'Português (Portugal)'],
                        ['value' => 'it_IT', 'label' => 'Italiano (Italia)'],
                    ],
                    'moedas' => [
                        ['value' => 'BRL', 'label' => 'Real (R$)'],
                        ['value' => 'USD', 'label' => 'Dólar (US$)'],
                        ['value' => 'EUR', 'label' => 'Euro (€)'],
                    ],
                    'formatos_data' => [
                        ['value' => 'd/m/Y H:i:s', 'label' => 'DD/MM/AAAA HH:MM:SS'],
                        ['value' => 'd/m/Y H:i', 'label' => 'DD/MM/AAAA HH:MM'],
                        ['value' => 'd/m/Y', 'label' => 'DD/MM/AAAA'],
                        ['value' => 'Y-m-d H:i:s', 'label' => 'AAAA-MM-DD HH:MM:SS'],
                        ['value' => 'm/d/Y', 'label' => 'MM/DD/AAAA'],
                    ],
                    'tipos' => [
                        ['value' => 'M', 'label' => 'Matriz'],
                        ['value' => 'F', 'label' => 'Filial'],
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar opções: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula distância entre duas filiais via Google Maps Distance Matrix API
     *
     * GET /api/matrizes-filiais/distancia?origem={id}&destino={id}
     */
    public function calcularDistancia(Request $request): void
    {
        try {
            if (!Auth::can('locacoes.criar') && !Auth::can('locacoes.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para calcular distâncias'
                ], 403);
                return;
            }

            $origemId = (int) $request->query('origem', 0);
            $destinoId = (int) $request->query('destino', 0);

            if (!$origemId || !$destinoId) {
                Response::json([
                    'success' => false,
                    'message' => 'Parâmetros origem e destino são obrigatórios'
                ], 400);
                return;
            }

            if ($origemId === $destinoId) {
                Response::json([
                    'success' => true,
                    'data' => ['distancia_km' => 0, 'duracao_texto' => '']
                ]);
                return;
            }

            $model = new MatrizFilial();
            $origem = $model->buscarPorId($origemId);
            $destino = $model->buscarPorId($destinoId);

            if (!$origem || !$destino) {
                Response::json([
                    'success' => false,
                    'message' => 'Filial de origem ou destino não encontrada'
                ], 404);
                return;
            }

            // Montar endereços para a API
            $endOrigem = $this->montarEnderecoTexto($origem);
            $endDestino = $this->montarEnderecoTexto($destino);

            if (!$endOrigem || !$endDestino) {
                Response::json([
                    'success' => false,
                    'message' => 'Endereço incompleto em uma das filiais'
                ], 422);
                return;
            }

            $apiKey = env('GOOGLE_MAPS_API_KEY', '');
            if (empty($apiKey)) {
                Response::json([
                    'success' => false,
                    'message' => 'Chave da API do Google Maps não configurada'
                ], 500);
                return;
            }

            $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . http_build_query([
                'origins' => $endOrigem,
                'destinations' => $endDestino,
                'key' => $apiKey,
                'language' => 'pt-BR',
                'units' => 'metric'
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao consultar a API do Google Maps'
                ], 502);
                return;
            }

            $data = json_decode($response, true);

            if (($data['status'] ?? '') !== 'OK') {
                $errorDetail = ($data['error_message'] ?? '') ? ' - ' . $data['error_message'] : '';
                Response::json([
                    'success' => false,
                    'message' => 'Google Maps retornou erro: ' . ($data['status'] ?? 'desconhecido') . $errorDetail
                ], 502);
                return;
            }

            $element = $data['rows'][0]['elements'][0] ?? null;

            if (!$element || ($element['status'] ?? '') !== 'OK') {
                Response::json([
                    'success' => false,
                    'message' => 'Não foi possível calcular a distância entre as filiais'
                ], 422);
                return;
            }

            $distanciaMetros = $element['distance']['value'] ?? 0;
            $distanciaKm = round($distanciaMetros / 1000, 1);
            $duracaoTexto = $element['duration']['text'] ?? '';

            Response::json([
                'success' => true,
                'data' => [
                    'distancia_km' => $distanciaKm,
                    'duracao_texto' => $duracaoTexto
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao calcular distância: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Monta endereço em texto a partir dos dados da filial
     */
    private function montarEnderecoTexto(array $filial): string
    {
        $partes = array_filter([
            trim(($filial['rua'] ?? '') . ' ' . ($filial['num'] ?? '')),
            $filial['bairro'] ?? '',
            $filial['cidade'] ?? '',
            $filial['estado'] ?? '',
        ]);

        return implode(', ', $partes);
    }

    /**
     * Mapeia os dados do request para o formato do banco
     */
    private function mapearDados(Request $request): array
    {
        return [
            // Dados básicos
            'tipo' => $request->input('tipo'),
            'status' => in_array($request->input('status'), ['A', 'I'], true) ? $request->input('status') : 'A',
            'razao_social' => $request->input('razao_social'),
            'nome_fantasia' => $request->input('nome_fantasia'),
            'cpf_cnpj' => $request->input('cpf_cnpj'),
            'ins_muni' => $request->input('inscricao_municipal'),
            'ins_esta' => $request->input('inscricao_estadual'),

            // Endereço
            'cep' => $request->input('cep'),
            'rua' => $request->input('rua'),
            'num' => $request->input('numero'),
            'compl' => $request->input('complemento'),
            'bairro' => $request->input('bairro'),
            'cidade' => $request->input('cidade'),
            'estado' => $request->input('estado'),
            'pais' => $request->input('pais'),

            // Contato
            'fixo' => $request->input('telefone_fixo'),
            'celular' => $request->input('celular'),
            'email' => $request->input('email'),
            'site' => $request->input('site'),

            // Horários agora são salvos em tabela separada (horarios_funcionamento)
            // Ver métodos store() e update() para tratamento de horários

            // Configurações
            'locale' => $request->input('locale'),
            'currency_code' => $request->input('currency_code'),
            'date_format' => $request->input('date_format'),
            'datetime_format' => $request->input('datetime_format'),
            'sequencia_locacoes' => $request->input('sequencia_locacoes'),
            'sequencia_contratos' => $request->input('sequencia_contratos'),
            'sequencia_financeiro' => $request->input('sequencia_financeiro'),

            // Notificações
            'notificacao_sms' => $request->input('notificacao_sms'),
            'notificacao_email' => $request->input('notificacao_email'),
            'notificacao_whatsapp' => $request->input('notificacao_whatsapp'),
            'notificacao_titulo' => $request->input('notificacao_titulo'),

            // Impressão
            'impressao_variavel_negrito' => $request->input('impressao_variavel_negrito'),
            'impressao_remover_tarja_amarela' => $request->input('impressao_remover_tarja_amarela'),
        ];
    }

    /**
     * Verifica se há vínculos que impedem a exclusão
     */
    private function verificarVinculos(int $matrizId): array
    {
        $matrizModel = new MatrizFilial();
        return $matrizModel->verificarVinculos($matrizId);
    }

    /**
     * Sincroniza locais de atendimento de uma filial a partir do payload.
     * Aceita `locais` como array ou string JSON. Se nao vier no payload, nao altera nada.
     */
    private function sincronizarLocais(Request $request, int $filialId, string $chave): void
    {
        $locais = $request->input('locais', null);
        if ($locais === null) {
            return;
        }
        $arr = is_string($locais) ? json_decode($locais, true) : $locais;
        if (!is_array($arr)) {
            return;
        }
        (new MatrizFilialLocal())->sincronizar($filialId, $chave, $arr);
    }
}
