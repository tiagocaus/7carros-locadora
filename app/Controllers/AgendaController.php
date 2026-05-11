<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Views\Template;
use App\Models\Agenda;
use App\Models\Contrato;
use App\Models\Grupo;
use App\Models\Locacao;
use App\Models\Manutencao;
use App\Models\Veiculo;
use App\Helpers\FilialHelper;

/**
 * Controller de Agenda
 *
 * Agrega eventos de locacoes, reservas, contratos, manutencoes e agenda geral
 * numa arvore grupos -> veiculos -> eventos usada pela tela de agenda (timeline Gantt).
 *
 * Permissoes necessarias: agenda.visualizar
 */
class AgendaController
{
    /**
     * Renderiza a pagina da agenda (iframe)
     *
     * GET /pages/agenda
     */
    public function view(Request $request): void
    {
        $html = Template::render('pages.agenda.index');
        Response::html($html);
    }

    /**
     * Retorna os eventos agregados no shape esperado pela tela:
     *
     * {
     *   grupos: { [id]: { id, nome, veiculos: [...], reservas_sem_veiculo: [...] } },
     *   agenda_geral: [...]
     * }
     *
     * GET /api/agenda?inicio=YYYY-MM-DD&fim=YYYY-MM-DD
     */
    public function index(Request $request): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) {
                Response::json(['erro' => 'Nao autenticado'], 401);
                return;
            }

            $inicio = $request->query('inicio', date('Y-m-d') . ' 00:00:00');
            $fim = $request->query('fim', date('Y-m-d', strtotime('+6 months')) . ' 23:59:59');

            if (strlen($inicio) === 10) $inicio .= ' 00:00:00';
            if (strlen($fim) === 10)    $fim    .= ' 23:59:59';

            [$filialVeiculosWhere, $filialVeiculosParams] = FilialHelper::whereFiliais('id_matriz_filial', 'v');
            [$filialLocacoesWhere, $filialLocacoesParams] = FilialHelper::whereLocacoes('l');
            [$filialContratosWhere, $filialContratosParams] = FilialHelper::whereContratos('c');
            [$filialManutencoesWhere, $filialManutencoesParams] = FilialHelper::whereFiliais('id_matriz_filial');

            $grupoModel = new Grupo();
            $veiculoModel = new Veiculo();
            $locacaoModel = new Locacao();
            $contratoModel = new Contrato();
            $manutencaoModel = new Manutencao();
            $agendaModel = new Agenda();

            $grupos = $grupoModel->listar();
            $veiculos = $veiculoModel->listarParaAgenda($chave, $filialVeiculosWhere, $filialVeiculosParams);

            $locacoesEventos = $locacaoModel->listarEventosAgenda($chave, $inicio, $fim, $filialLocacoesWhere, $filialLocacoesParams);
            $contratosEventos = $contratoModel->listarEventosAgenda($chave, $inicio, $fim, $filialContratosWhere, $filialContratosParams);
            $manutencoesEventos = $manutencaoModel->listarEventosAgenda($chave, $inicio, $fim, $filialManutencoesWhere, $filialManutencoesParams);
            $agendaGeralEventos = $agendaModel->listarPorPeriodo($chave, $inicio, $fim);

            $result = $this->montarArvore(
                $grupos,
                $veiculos,
                $locacoesEventos,
                $contratosEventos,
                $manutencoesEventos
            );

            Response::json([
                'grupos' => $result['arvore'],
                'agenda_geral' => array_map([$this, 'formatarAgendaGeral'], $agendaGeralEventos),
                'reservas_orfas' => $result['reservas_orfas'],
            ]);
        } catch (\Exception $e) {
            Response::json(['erro' => 'Erro ao carregar agenda: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Monta a arvore grupos -> veiculos -> eventos e reservas_sem_veiculo por grupo.
     */
    private function montarArvore(
        array $grupos,
        array $veiculos,
        array $locacoes,
        array $contratos,
        array $manutencoes
    ): array {
        $arvore = [];
        foreach ($grupos as $grupo) {
            $arvore[$grupo['id']] = [
                'id' => (int) $grupo['id'],
                'nome' => $grupo['nome'],
                'veiculos' => [],
                'reservas_sem_veiculo' => [],
            ];
        }

        $veiculoIndex = [];
        foreach ($veiculos as $v) {
            $grupoId = (int) ($v['id_grupo'] ?? 0);
            if (!isset($arvore[$grupoId])) continue;

            $veiculoIndex[(int) $v['id']] = [
                'grupo_id' => $grupoId,
                'pos' => count($arvore[$grupoId]['veiculos']),
            ];

            $arvore[$grupoId]['veiculos'][] = [
                'id' => (int) $v['id'],
                'placa' => $v['placa'],
                'modelo' => trim(($v['marca'] ?? '') . ' ' . ($v['modelo'] ?? '')),
                'disponibilidade' => $v['disponibilidade'],
                'eventos' => [
                    'locacoes' => [],
                    'reservas' => [],
                    'contratos' => [],
                    'manutencoes_andamento' => [],
                    'manutencoes_programadas' => [],
                ],
            ];
        }

        $reservasOrfas = [];
        foreach ($locacoes as $loc) {
            $evento = $this->formatarLocacao($loc);
            $idVeiculo = $loc['id_veiculo'] !== null ? (int) $loc['id_veiculo'] : null;
            $idGrupo = $loc['id_grupo'] !== null ? (int) $loc['id_grupo'] : null;

            if ($idVeiculo !== null && isset($veiculoIndex[$idVeiculo])) {
                $idx = $veiculoIndex[$idVeiculo];
                $bucket = ($loc['status'] === 'R') ? 'reservas' : 'locacoes';
                $arvore[$idx['grupo_id']]['veiculos'][$idx['pos']]['eventos'][$bucket][] = $evento;
            } elseif ($loc['status'] === 'R' && $idGrupo !== null && isset($arvore[$idGrupo])) {
                $arvore[$idGrupo]['reservas_sem_veiculo'][] = $evento;
            } elseif ($loc['status'] === 'R') {
                // Reserva sem grupo nem veiculo (orfas) → vai para AGENDA GERAL
                $reservasOrfas[] = $evento;
            }
        }

        foreach ($contratos as $ct) {
            $idVeiculo = $ct['id_veiculo'] !== null ? (int) $ct['id_veiculo'] : null;
            if ($idVeiculo === null || !isset($veiculoIndex[$idVeiculo])) continue;

            $idx = $veiculoIndex[$idVeiculo];
            $arvore[$idx['grupo_id']]['veiculos'][$idx['pos']]['eventos']['contratos'][] = $this->formatarContrato($ct);
        }

        foreach ($manutencoes as $m) {
            $idVeiculo = (int) $m['id_veiculo'];
            if (!isset($veiculoIndex[$idVeiculo])) continue;

            $idx = $veiculoIndex[$idVeiculo];
            $bucket = ($m['status'] === 'A') ? 'manutencoes_andamento' : 'manutencoes_programadas';
            $arvore[$idx['grupo_id']]['veiculos'][$idx['pos']]['eventos'][$bucket][] = $this->formatarManutencao($m);
        }

        return ['arvore' => $arvore, 'reservas_orfas' => $reservasOrfas];
    }

    private function formatarLocacao(array $loc): array
    {
        // Ongoing: sem data_chegada e sem data_prevista futura → devolve null
        // para o frontend aplicar o fallback de "ainda em aberto".
        $dataFim = $loc['data_chegada'] ?: null;
        if (!$dataFim) {
            $prev = $loc['data_prevista'] ?? null;
            $dataFim = ($prev && strtotime($prev) >= time()) ? $prev : null;
        }
        $tipoLabel = $loc['status'] === 'R' ? 'Reserva' : 'Locacao';

        return [
            'id' => (int) $loc['id'],
            'tipo' => $loc['status'] === 'R' ? 'reserva' : 'locacao',
            'data_inicio' => $loc['data_saida'],
            'data_fim' => $dataFim,
            'obs' => $loc['obs'] ?? '',
            'cor' => $loc['status'] === 'R' ? 'agenda_azul' : 'agenda_vermelho',
            'codigo' => $loc['codigo'],
            'cliente_nome' => $loc['cliente_nome'],
            'url' => [
                'url' => '/pages/locacoes/editar/' . (int) $loc['id'],
                'title' => 'Locações/Reservas',
                'icon' => 'fas fa-file-invoice-dollar',
                'tabId' => 'locacoes',
                'class' => 'abreTab',
            ],
        ];
    }

    private function formatarContrato(array $ct): array
    {
        return [
            'id' => (int) $ct['id'],
            'tipo' => 'contrato',
            'data_inicio' => $ct['data_ini'],
            'data_fim' => $ct['data_fim'],
            'obs' => $ct['obs'] ?? '',
            'cor' => 'agenda_vermelho',
            'codigo' => $ct['codigo'],
            'cliente_nome' => $ct['cliente_nome'],
            'url' => [
                'url' => '/pages/contratos/editar/' . (int) $ct['id'],
                'title' => 'Contratos',
                'icon' => 'fas fa-file-signature',
                'tabId' => 'contratos',
                'class' => 'abreTab',
            ],
        ];
    }

    private function formatarManutencao(array $m): array
    {
        return [
            'id' => (int) $m['id'],
            'tipo' => $m['status'] === 'A' ? 'manutencao_andamento' : 'manutencao_programada',
            'data_inicio' => $m['data_enviado'],
            'data_fim' => $m['data_retorno'],
            'obs' => $m['motivo'] ?? '',
            'cor' => 'agenda_laranja',
            'codigo' => $m['os'],
            'titulo' => 'OS ' . $m['os'],
            'url' => [
                'url' => '/pages/manutencoes/adicionar?id=' . (int) $m['id'],
                'title' => 'Manutenções',
                'icon' => 'fas fa-tools',
                'tabId' => 'manutencoes',
                'class' => 'abreTab',
            ],
        ];
    }

    private function formatarAgendaGeral(array $a): array
    {
        return [
            'id' => (int) $a['id'],
            'data_inicio' => $a['data_ini'],
            'data_fim' => $a['data_fim'],
            'titulo' => $a['titulo'] ?? '',
            'label' => $a['label'] ?? '',
            'obs' => $a['obs'] ?? '',
            'cor' => $a['cor'] ?: 'agenda_roxo',
            'url' => [
                'url' => '/pages/agenda/editar/' . (int) $a['id'],
                'title' => 'Editar Agenda',
                'class' => 'abreLateral',
                'width' => '40%',
            ],
        ];
    }

    // ========================================
    // CRUD da tabela agenda
    // ========================================

    /**
     * Renderiza o formulario de nova agenda (usado no offcanvas lateral)
     * GET /pages/agenda/adicionar
     */
    public function formView(Request $request): void
    {
        $html = Template::render('pages.agenda.adicionar');
        Response::html($html);
    }

    /**
     * Renderiza o formulario com dados para edicao
     * GET /pages/agenda/editar/{id}
     */
    public function editView(Request $request, int $id): void
    {
        $chave = Auth::chave();
        $agenda = (new Agenda())->buscarPorId($id);

        if (!$agenda || $agenda['chave'] !== $chave) {
            Response::redirect('/pages/agenda');
            return;
        }

        $html = Template::render('pages.agenda.adicionar', ['agenda' => $agenda]);
        Response::html($html);
    }

    /**
     * Cria um evento na agenda
     * POST /agenda/salvar
     */
    public function store(Request $request): void
    {
        try {
            $chave = Auth::chave();
            if (!$chave) {
                Response::json(['success' => false, 'message' => 'Nao autenticado'], 401);
                return;
            }

            $dados = $this->extractDados($request);
            if (!$dados['titulo'] || !$dados['data_ini'] || !$dados['data_fim']) {
                Response::json(['success' => false, 'message' => 'Preencha titulo, data inicio e data fim'], 422);
                return;
            }

            $dados['chave'] = $chave;
            $id = (new Agenda())->criar($dados);

            Response::json(['success' => true, 'id' => $id]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza um evento da agenda
     * POST /agenda/{id}/atualizar
     */
    public function update(Request $request, int $id): void
    {
        try {
            $chave = Auth::chave();
            $agendaModel = new Agenda();
            $atual = $agendaModel->buscarPorId($id);

            if (!$atual || $atual['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Registro nao encontrado'], 404);
                return;
            }

            $dados = $this->extractDados($request);
            $agendaModel->atualizar($id, $dados);

            Response::json(['success' => true]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Exclui um evento da agenda
     * POST /agenda/{id}/excluir
     */
    public function destroy(Request $request, int $id): void
    {
        try {
            $chave = Auth::chave();
            $agendaModel = new Agenda();
            $atual = $agendaModel->buscarPorId($id);

            if (!$atual || $atual['chave'] !== $chave) {
                Response::json(['success' => false, 'message' => 'Registro nao encontrado'], 404);
                return;
            }

            $agendaModel->excluir($id);
            Response::json(['success' => true]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Extrai campos validos do request
     */
    private function extractDados(Request $request): array
    {
        return [
            'titulo' => trim((string) $request->input('titulo', '')),
            'data_ini' => $this->normalizarData((string) $request->input('data_ini', '')),
            'data_fim' => $this->normalizarData((string) $request->input('data_fim', '')),
            'label' => trim((string) $request->input('label', '')),
            'cor' => trim((string) $request->input('cor', 'agenda_roxo')),
            'obs' => $request->input('obs', null),
        ];
    }

    /**
     * Converte datetime-local (YYYY-MM-DDTHH:MM) para SQL datetime
     */
    private function normalizarData(string $data): ?string
    {
        if (empty($data)) return null;
        $data = str_replace('T', ' ', $data);
        if (strlen($data) === 16) $data .= ':00';
        return $data;
    }
}
