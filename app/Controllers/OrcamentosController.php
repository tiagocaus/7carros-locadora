<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\DateHelper;
use App\Helpers\FilialHelper;
use App\Helpers\PdfHelper;
use App\Models\Cliente;
use App\Models\ContatoEmail;
use App\Models\ContatoTelefone;
use App\Models\Grupo;
use App\Models\LocacaoTaxaServico;
use App\Models\MatrizFilial;
use App\Models\Orcamento;
use App\Models\TaxaServico;
use App\Models\Veiculo;
use App\Services\AuditLogService;
use App\Services\GrupoPrecoPeriodoService;
use App\Services\PromocaoAplicacaoService;
use App\Views\Template;

class OrcamentosController
{
    public function view(Request $request): void
    {
        if (!$this->permitido('orcamentos.visualizar')) return;
        Response::html(Template::render('pages.orcamentos.index'));
    }

    public function formView(Request $request): void
    {
        if (!$this->permitido('orcamentos.criar')) return;
        Response::html(Template::render('pages.orcamentos.adicionar'));
    }

    public function editView(Request $request, int $id): void
    {
        if (!$this->permitido('orcamentos.editar')) return;
        $orcamento = $this->buscarAcessivel($id);
        if (!$orcamento) {
            Response::redirect('/pages/orcamentos');
            return;
        }
        Response::html(Template::render('pages.orcamentos.adicionar', ['orcamento' => $orcamento]));
    }

    public function index(Request $request): void
    {
        if (!$this->permitidoJson('orcamentos.visualizar')) return;
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('perPage', 10)));
            $search = trim((string) $request->query('search', ''));
            $status = trim((string) $request->query('status', ''));
            [$where, $params] = FilialHelper::whereFiliais('id_matriz_filial_retirada', 'o');

            $model = new Orcamento();
            $items = $model->listarPaginado($page, $perPage, $search, $where, $params, $status);
            foreach ($items as &$item) {
                if (!in_array($item['status'], ['C', 'N', 'X'], true) && $item['validade'] < DateHelper::todayForDatabase()) {
                    $item['status_exibicao'] = 'X';
                } else {
                    $item['status_exibicao'] = $item['status'];
                }
            }
            unset($item);
            $total = $model->contar($search, $where, $params, $status);
            $totalPages = max(1, (int) ceil($total / $perPage));

            Response::json(['success' => true, 'data' => $items, 'pagination' => [
                'page' => $page, 'perPage' => $perPage, 'total' => $total,
                'totalPages' => $totalPages, 'hasNext' => $page < $totalPages, 'hasPrev' => $page > 1,
            ]]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao carregar orçamentos: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, int $id): void
    {
        if (!$this->permitidoJson('orcamentos.visualizar')) return;
        $orcamento = $this->buscarAcessivel($id);
        if (!$orcamento) {
            Response::json(['success' => false, 'message' => 'Orçamento não encontrado.'], 404);
            return;
        }
        Response::json(['success' => true, 'data' => $orcamento]);
    }

    public function calcular(Request $request): void
    {
        if (!$this->permitidoJson('orcamentos.criar')) return;
        try {
            $atual = null;
            $idAtual = (int) $request->input('id_orcamento', 0);
            if ($idAtual > 0) $atual = $this->buscarAcessivel($idAtual);
            $dados = $this->validarECalcular($request->all(), $atual);
            Response::json(['success' => true, 'data' => $dados]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Não foi possível calcular o orçamento.'], 500);
        }
    }

    public function store(Request $request): void
    {
        if (!$this->permitidoJson('orcamentos.criar')) return;
        try {
            $dados = $this->validarECalcular($request->all());
            $dados['chave'] = Auth::chave();
            $dados['id_funcionario'] = Auth::id();
            $id = (new Orcamento())->criar($dados);
            AuditLogService::registrar(($_SESSION['user_name'] ?? 'Sistema') . ", criou orçamento [{$dados['cliente_nome']}]" );
            Response::json(['success' => true, 'message' => 'Orçamento criado com sucesso.', 'data' => ['id' => $id]]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao criar orçamento: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): void
    {
        if (!$this->permitidoJson('orcamentos.editar')) return;
        $atual = $this->buscarAcessivel($id);
        if (!$atual) {
            Response::json(['success' => false, 'message' => 'Orçamento não encontrado.'], 404);
            return;
        }
        if (($atual['status'] ?? '') === 'C') {
            Response::json(['success' => false, 'message' => 'Orçamentos convertidos não podem ser editados.'], 422);
            return;
        }
        try {
            $dados = $this->validarECalcular($request->all(), $atual);
            (new Orcamento())->atualizar($id, $dados);
            AuditLogService::registrar(($_SESSION['user_name'] ?? 'Sistema') . ", atualizou orçamento [{$atual['codigo']}]" );
            Response::json(['success' => true, 'message' => 'Orçamento atualizado com sucesso.']);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao atualizar orçamento: ' . $e->getMessage()], 500);
        }
    }

    public function status(Request $request, int $id): void
    {
        if (!$this->permitidoJson('orcamentos.editar')) return;
        $orcamento = $this->buscarAcessivel($id);
        if (!$orcamento) {
            Response::json(['success' => false, 'message' => 'Orçamento não encontrado.'], 404);
            return;
        }
        if (($orcamento['status'] ?? '') === 'C') {
            Response::json(['success' => false, 'message' => 'A reserva deste orçamento já foi criada.'], 422);
            return;
        }
        $status = (string) $request->input('status', '');
        if (!in_array($status, ['R', 'E', 'A', 'N'], true)) {
            Response::json(['success' => false, 'message' => 'Status inválido.'], 422);
            return;
        }
        (new Orcamento())->alterarStatus($id, $status);
        Response::json(['success' => true, 'message' => 'Status atualizado.']);
    }

    public function duplicar(Request $request, int $id): void
    {
        if (!$this->permitidoJson('orcamentos.criar')) return;
        $origem = $this->buscarAcessivel($id);
        if (!$origem) {
            Response::json(['success' => false, 'message' => 'Orçamento não encontrado.'], 404);
            return;
        }
        unset($origem['id'], $origem['codigo'], $origem['id_locacao_convertida'], $origem['created_at'], $origem['updated_at']);
        $origem['status'] = 'R';
        $origem['validade'] = (new \DateTimeImmutable(DateHelper::todayForDatabase()))->modify('+3 days')->format('Y-m-d');
        $origem['id_funcionario'] = Auth::id();
        $idNovo = (new Orcamento())->criar($origem);
        Response::json(['success' => true, 'message' => 'Orçamento duplicado.', 'data' => ['id' => $idNovo]]);
    }

    public function converter(Request $request, int $id): void
    {
        if (!$this->permitidoJson('orcamentos.converter')) return;
        $orcamento = $this->buscarAcessivel($id);
        if (!$orcamento) {
            Response::json(['success' => false, 'message' => 'Orçamento não encontrado.'], 404);
            return;
        }
        try {
            $locacaoId = (new Orcamento())->converterEmReserva($id, (int) Auth::id());
            AuditLogService::registrar(($_SESSION['user_name'] ?? 'Sistema') . ", converteu orçamento [{$orcamento['codigo']}] em reserva" );
            Response::json(['success' => true, 'message' => 'Reserva criada com sucesso.', 'data' => ['id_locacao' => $locacaoId]]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao converter orçamento: ' . $e->getMessage()], 500);
        }
    }

    public function imprimir(Request $request, int $id): void
    {
        if (!$this->permitido('orcamentos.imprimir')) return;
        $orcamento = $this->buscarAcessivel($id);
        if (!$orcamento) {
            Response::html('<h1>Orçamento não encontrado</h1>', 404);
            return;
        }
        PdfHelper::outputInline($this->gerarPdfHtml($orcamento), 'orcamento-' . $orcamento['codigo'] . '.pdf');
    }

    public function enviar(Request $request, int $id): void
    {
        if (!$this->permitidoJson('orcamentos.enviar')) return;
        $orcamento = $this->buscarAcessivel($id);
        if (!$orcamento) {
            Response::json(['success' => false, 'message' => 'Orçamento não encontrado.'], 404);
            return;
        }

        try {
            $canal = (string) $request->input('canal', 'email');
            if (!in_array($canal, ['email', 'whatsapp', 'sms'], true)) {
                throw new \InvalidArgumentException('Canal de envio inválido.');
            }

            $clienteId = (int) $orcamento['id_cliente'];
            $chave = (string) $orcamento['chave'];
            $filialId = (int) $orcamento['id_matriz_filial_retirada'];
            if ($canal === 'email') {
                $contatos = (new ContatoEmail())->listarParaEnvio('cliente', $clienteId, $chave);
                $destinatario = (string) ($contatos[0]['email'] ?? '');
            } else {
                $contatos = (new ContatoTelefone())->listarParaEnvio('cliente', $clienteId, $canal, $chave);
                $destinatario = (string) ($contatos[0]['telefone'] ?? '');
            }
            if ($destinatario === '') {
                throw new \InvalidArgumentException($canal === 'email'
                    ? 'Cliente sem e-mail autorizado para envio.'
                    : "Cliente sem telefone autorizado para {$canal}.");
            }

            validate_queue_message($canal, ['to' => $destinatario, 'id_matriz_filial' => $filialId]);
            $filename = 'orcamento_' . $orcamento['codigo'] . '_' . DateHelper::timestamp() . '.pdf';
            $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            if ($documentRoot === '') $documentRoot = APP_ROOT . '/public';
            $tempDir = $documentRoot . '/storage/temp';
            if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
                throw new \RuntimeException('Não foi possível preparar o anexo do orçamento.');
            }
            $tempPath = $tempDir . '/' . $filename;
            file_put_contents($tempPath, PdfHelper::generateAsString($this->gerarPdfHtml($orcamento)));

            $empresa = (new MatrizFilial())->buscarPorId($filialId) ?? [];
            $empresaNome = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Locadora';
            $publicUrl = rtrim((string) env('APP_URL', ''), '/') . '/storage/temp/' . $filename;
            if ($canal === 'email') {
                queue_client_email($clienteId, [
                    'to' => $destinatario,
                    'to_name' => $orcamento['cliente_nome'],
                    'subject' => 'Orçamento ' . $orcamento['codigo'] . ' - ' . $empresaNome,
                    'body' => '<p>Olá, ' . htmlspecialchars($orcamento['cliente_nome']) . '.</p>'
                        . '<p>Segue em anexo o orçamento <strong>' . htmlspecialchars($orcamento['codigo']) . '</strong>, válido até '
                        . htmlspecialchars(DateHelper::format($orcamento['validade'])) . '.</p>',
                    'attachments' => [$tempPath],
                    'id_matriz_filial' => $filialId,
                ], $chave);
            } elseif ($canal === 'whatsapp') {
                queue_client_phone('whatsapp', $clienteId, [
                    'to' => $destinatario,
                    'media_url' => $publicUrl,
                    'caption' => 'Orçamento ' . $orcamento['codigo'] . ' - ' . $empresaNome,
                    'id_matriz_filial' => $filialId,
                ], $chave);
            } else {
                queue_client_phone('sms', $clienteId, [
                    'to' => $destinatario,
                    'message' => 'Orçamento ' . $orcamento['codigo'] . ' de ' . $empresaNome . ': ' . $publicUrl,
                    'id_matriz_filial' => $filialId,
                ], $chave);
            }

            if (($orcamento['status'] ?? '') !== 'C') (new Orcamento())->alterarStatus($id, 'E');
            Response::json(['success' => true, 'message' => 'Orçamento enfileirado para envio por ' . $canal . '.']);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao enviar orçamento: ' . $e->getMessage()], 500);
        }
    }

    private function validarECalcular(array $entrada, ?array $atual = null): array
    {
        $obrigatorios = [
            'id_cliente' => 'Selecione o cliente.',
            'id_matriz_filial_retirada' => 'Selecione a filial de retirada.',
            'id_matriz_filial_devolucao' => 'Selecione a filial de devolução.',
            'data_saida' => 'Informe a retirada.',
            'data_prevista' => 'Informe a devolução.',
            'id_grupo' => 'Selecione o grupo.',
        ];
        foreach ($obrigatorios as $campo => $mensagem) {
            if (empty($entrada[$campo])) throw new \InvalidArgumentException($mensagem);
        }

        $filialRetirada = (int) $entrada['id_matriz_filial_retirada'];
        $filialDevolucao = (int) $entrada['id_matriz_filial_devolucao'];
        if (!FilialHelper::temAcessoFilial($filialRetirada) || !FilialHelper::temAcessoFilial($filialDevolucao)) {
            throw new \InvalidArgumentException('Você não tem acesso a uma das filiais selecionadas.');
        }

        $cliente = (new Cliente())->buscarPorId((int) $entrada['id_cliente']);
        $grupo = (new Grupo())->buscarPorId((int) $entrada['id_grupo']);
        if (!$cliente || !$grupo) throw new \InvalidArgumentException('Cliente ou grupo inválido.');

        $saida = strtotime((string) $entrada['data_saida']);
        $prevista = strtotime((string) $entrada['data_prevista']);
        if ($saida === false || $prevista === false || $prevista <= $saida) {
            throw new \InvalidArgumentException('A devolução deve ser posterior à retirada.');
        }
        $dias = max(1, (int) ceil(($prevista - $saida) / 86400));
        $validade = (string) ($entrada['validade'] ?? '');
        if ($validade === '') {
            $validade = (new \DateTimeImmutable(DateHelper::todayForDatabase()))->modify('+3 days')->format('Y-m-d');
        }

        $plano = strtoupper((string) ($entrada['plano'] ?? 'KL'));
        if (!in_array($plano, ['KL', 'KMC', 'DI', 'KP'], true)) throw new \InvalidArgumentException('Plano inválido.');
        $preco = (new GrupoPrecoPeriodoService())->calcularValorPeriodo(
            (int) $grupo['id'], $filialRetirada, $plano, $dias,
            date('Y-m-d', $saida), Auth::chave()
        );
        $diaria = (($entrada['diaria_valor_origem'] ?? 'auto') === 'manual')
            ? currency_parse($entrada['diaria_valor'] ?? 0)
            : (float) ($preco['valor_dia'] ?? 0);
        $subtotalDiarias = (($entrada['diaria_valor_origem'] ?? 'auto') === 'manual')
            ? round($diaria * $dias, 2)
            : (float) ($preco['subtotal'] ?? 0);

        $seguroCarro = !empty($entrada['seguro_carro']) && $entrada['seguro_carro'] !== 'N';
        $seguroTerceiros = !empty($entrada['seguro_terceiros']) && $entrada['seguro_terceiros'] !== 'N';
        $valorSeguroCarro = $seguroCarro ? currency_parse($entrada['valor_seguro_carro'] ?? 0) : 0;
        $valorSeguroTerceiros = $seguroTerceiros ? currency_parse($entrada['valor_seguro_terceiros'] ?? 0) : 0;
        $subtotalSeguros = ($valorSeguroCarro + $valorSeguroTerceiros) * $dias;

        $taxasEntrada = $entrada['taxas'] ?? [];
        if (is_string($taxasEntrada)) $taxasEntrada = json_decode($taxasEntrada, true) ?: [];
        $taxas = [];
        $totalTaxas = 0.0;
        $taxaModel = new TaxaServico();
        $calculadoraTaxas = new LocacaoTaxaServico();
        foreach ((array) $taxasEntrada as $item) {
            $taxa = $taxaModel->buscarPorId((int) ($item['id_taxa'] ?? 0));
            if (!$taxa) continue;
            $filiais = array_column($taxa['filiais'] ?? [], 'id');
            if ($filiais && !in_array($filialRetirada, array_map('intval', $filiais), true)) continue;
            $snapshot = [
                'id_taxa' => (int) $taxa['id'], 'nome' => $taxa['nome'],
                'base_calculo' => $taxa['base_calculo'], 'tipo_valor' => $taxa['tipo_valor'],
                'quantidade' => max(1, (int) ($item['quantidade'] ?? 1)),
                'valor_unitario' => $taxaModel->resolverValor($taxa, $filialRetirada),
            ];
            $snapshot['valor_total'] = round($calculadoraTaxas->calcularValorTotalTaxa($snapshot, $dias, $subtotalDiarias), 2);
            $totalTaxas += $snapshot['valor_total'];
            $taxas[] = $snapshot;
        }

        $totalFatura = round($subtotalDiarias + $subtotalSeguros + $totalTaxas, 2);
        $codigoPromocao = PromocaoAplicacaoService::normalizarCodigo($entrada['promocao_codigo'] ?? '');
        $desconto = max(0, currency_parse($entrada['valor_desconto'] ?? 0));
        $codigoAtual = PromocaoAplicacaoService::normalizarCodigo($atual['promocao_codigo'] ?? '');
        $preservarPromocao = $atual !== null
            && $codigoPromocao !== ''
            && $codigoPromocao === $codigoAtual
            && (int) ($atual['id_grupo'] ?? 0) === (int) $grupo['id'];
        if ($preservarPromocao) {
            $codigoPromocao = $codigoAtual;
            $desconto = (float) ($atual['valor_desconto'] ?? 0);
        } elseif ($codigoPromocao !== '') {
            $promocao = (new PromocaoAplicacaoService())->validarECalcular(
                $codigoPromocao, $filialRetirada, $dias, $totalFatura, 'SIS', (int) $grupo['id']
            );
            $codigoPromocao = $promocao['codigo'];
            $desconto = (float) $promocao['valor_desconto'];
        }
        $desconto = min($desconto, $totalFatura);

        $status = (string) ($entrada['status'] ?? 'R');
        if (!in_array($status, ['R', 'E', 'A', 'N'], true)) $status = 'R';
        $veiculoId = !empty($entrada['id_veiculo']) ? (int) $entrada['id_veiculo'] : null;
        if ($veiculoId && !(new Veiculo())->buscarPorId($veiculoId)) $veiculoId = null;

        return [
            'status' => $status, 'validade' => $validade, 'origem' => trim((string) ($entrada['origem'] ?? '')) ?: null,
            'id_cliente' => (int) $cliente['id'], 'cliente_nome' => (string) ($cliente['nome_rsocial'] ?? ''),
            'id_matriz_filial_retirada' => $filialRetirada, 'id_matriz_filial_devolucao' => $filialDevolucao,
            'data_saida' => date('Y-m-d H:i:s', $saida), 'data_prevista' => date('Y-m-d H:i:s', $prevista), 'dias' => $dias,
            'id_grupo' => (int) $grupo['id'], 'grupo_nome' => (string) ($grupo['nome'] ?? ''), 'id_veiculo' => $veiculoId,
            'plano' => $plano, 'diaria_valor' => $diaria, 'km_franquia' => !empty($entrada['km_franquia']) ? (int) $entrada['km_franquia'] : null,
            'valor_km_excedente' => currency_parse($entrada['valor_km_excedente'] ?? 0),
            'seguro_carro' => $seguroCarro ? 1 : 0, 'valor_seguro_carro' => $valorSeguroCarro,
            'seguro_terceiros' => $seguroTerceiros ? 1 : 0, 'valor_seguro_terceiros' => $valorSeguroTerceiros,
            'id_conta' => !empty($entrada['id_conta']) ? (int) $entrada['id_conta'] : null,
            'id_forma_pagamento' => !empty($entrada['id_forma_pagamento']) ? (int) $entrada['id_forma_pagamento'] : null,
            'condicao_pagamento' => trim((string) ($entrada['condicao_pagamento'] ?? '')) ?: null,
            'promocao_codigo' => $codigoPromocao ?: null, 'valor_desconto' => $desconto, 'taxas' => $taxas,
            'observacoes_cliente' => trim((string) ($entrada['observacoes_cliente'] ?? '')) ?: null,
            'observacoes_internas' => trim((string) ($entrada['observacoes_internas'] ?? '')) ?: null,
            'subtotal_diarias' => $subtotalDiarias, 'subtotal_adicionais' => round($subtotalSeguros + $totalTaxas, 2),
            'total_fatura' => $totalFatura, 'total_pagar' => round($totalFatura - $desconto, 2),
        ];
    }

    private function buscarAcessivel(int $id): ?array
    {
        $orcamento = (new Orcamento())->buscarPorId($id);
        if (!$orcamento || !FilialHelper::temAcessoFilial($orcamento['id_matriz_filial_retirada'] ?? null)) return null;
        return $orcamento;
    }

    private function gerarPdfHtml(array $orcamento): string
    {
        $empresa = (new MatrizFilial())->buscarPorId((int) $orcamento['id_matriz_filial_retirada']) ?? [];
        $chave = (string) ($empresa['chave'] ?? $orcamento['chave'] ?? Auth::chave());
        $logoPath = PdfHelper::resolveImagePath($empresa['logo'] ?? null, $chave);
        ob_start();
        include __DIR__ . '/../Views/pages/orcamentos/imprimir.php';
        return (string) ob_get_clean();
    }

    private function permitido(string $permission): bool
    {
        if (Auth::can($permission)) return true;
        Response::redirect('/dashboard');
        return false;
    }

    private function permitidoJson(string $permission): bool
    {
        if (Auth::can($permission)) return true;
        Response::json(['success' => false, 'message' => 'Você não tem permissão para esta ação.'], 403);
        return false;
    }
}
