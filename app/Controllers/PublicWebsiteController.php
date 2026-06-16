<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\SiteConfig;
use App\Models\SiteConteudo;
use App\Models\SiteSeo;
use App\Models\SiteIntegracao;
use App\Models\SiteBanner;
use App\Models\SiteLink;
use App\Models\MatrizFilial;
use App\Models\MatrizFilialLocal;
use App\Models\Grupo;
use App\Models\GrupoPrecoFilial;
use App\Models\TaxaServicoValorFilial;
use App\Models\Veiculo;
use App\Models\HorarioFuncionamento;
use App\Models\HorarioExcecao;
use App\Models\Feriado;
use App\Models\LocacaoDocumento;
use App\Helpers\ImageHelper;
use App\Helpers\FileHelper;
use App\Services\WebsiteReservaCalcService;

/**
 * Controller para API publica do site — sem auth de sessao
 *
 * Autenticacao via header X-Site-Token + validacao da chave.
 * Opera cross-tenant setando $_SESSION['chave'] temporariamente
 * para que os Models com filtro automatico funcionem corretamente.
 */
class PublicWebsiteController
{
    private ?string $previousChave = null;

    /**
     * GET /api/public/dados-site
     */
    public function dadosSite(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            $chave = $config['chave'];
            $appUrl = Database::env('APP_URL', 'https://locadora.7carros.com');

            $this->setTenantContext($chave);

            // Filiais via MatrizFilial Model
            $mfModel = new MatrizFilial();
            $filiais = $mfModel->listar(null, [], 'tipo DESC, nome_fantasia ASC');

            // Mapear e enriquecer filiais
            $currencyMap = [
                'BRL' => ['R$', ',', '.'], 'EUR' => ['€', ',', '.'],
                'USD' => ['US$', '.', ','], 'GBP' => ['£', '.', ','],
            ];

            $feriadoModel = new Feriado();
            $anoAtual     = (int) date('Y');

            $filiaisFormatadas = [];
            foreach ($filiais as $f) {
                $c = $currencyMap[$f['currency_code'] ?? 'BRL'] ?? $currencyMap['BRL'];

                $estado       = trim($f['estado'] ?? '');
                $cidade       = trim($f['cidade'] ?? '');
                $nomeFantasia = trim($f['nome_fantasia'] ?? $f['razao_social'] ?? '');
                $cidadeOuNome = $cidade !== '' ? $cidade : $nomeFantasia;

                // Pula filiais sem dados uteis pra exibir no select
                if ($estado === '' && $cidadeOuNome === '') {
                    continue;
                }

                $label = trim(implode(' - ', array_filter([$estado, $cidadeOuNome])));

                $filiaisFormatadas[] = [
                    'id'                => $f['id'],
                    'nome'              => $nomeFantasia,
                    'cidade'            => $cidade,
                    'estado'            => $estado,
                    'label'             => $label,
                    'email'             => $f['email'] ?? '',
                    'currency_code'     => $f['currency_code'] ?? 'BRL',
                    'locale'            => $f['locale'] ?? 'pt_BR',
                    'simbolo_moeda'     => $c[0],
                    'separador_decimal' => $c[1],
                    'separador_milhar'  => $c[2],
                    'horarios'          => $this->montarHorariosJs((int) $f['id']),
                    'excecoes'          => $this->montarExcecoesFuturas((int) $f['id']),
                    'feriados'          => $feriadoModel->listarPorAno(
                        $anoAtual,
                        $estado !== '' ? $estado : null,
                        $cidade !== '' ? $cidade : null
                    ),
                    'precos_grupos'     => $this->montarPrecosGruposJs((int) $f['id']),
                    'valores_servicos'  => $this->montarValoresServicosJs((int) $f['id']),
                    'locais'            => $this->montarLocaisJs((int) $f['id']),
                ];
            }

            // Grupos de veiculos via Grupo Model
            $grupoModel = new Grupo();
            $gruposRaw = $grupoModel->listar();

            $gruposFormatados = [];
            foreach ($gruposRaw as $g) {
                if (empty($g['visivel_no_site'])) continue;

                $gruposFormatados[] = [
                    'id'        => $g['id'],
                    'nome'      => $g['nome'] ?? '',
                    'descricao' => $g['descricao'] ?? '',
                    'foto_url'  => !empty($g['imagem']) ? $appUrl . '/uploads/' . $chave . '/' . $g['imagem'] : null,
                    'diaria'    => isset($g['valor_diaria']) ? (float) $g['valor_diaria'] : null,
                ];
            }

            // Servicos adicionais via SiteConfig (proxy para qb)
            $configModel = new SiteConfig();
            $servicos = $configModel->queryTable('taxaseservicos')
                ->select(['id', 'nome', 'valor', 'tipo_valor', 'base_calculo'])
                ->whereRaw("FIND_IN_SET('SITE', onde_usar)")
                ->orderBy('nome', 'ASC')
                ->get();

            foreach ($servicos as &$s) {
                $s['valor'] = (float) $s['valor'];
            }

            // Empresa (matriz principal) via MatrizFilial
            $empresaRaw = $mfModel->buscarMatriz();
            $empresa = $empresaRaw ? [
                'nome_fantasia' => $empresaRaw['nome_fantasia'] ?? '',
                'razao_social'  => $empresaRaw['razao_social'] ?? '',
                'email'         => $empresaRaw['email'] ?? '',
                'cidade'        => $empresaRaw['cidade'] ?? '',
                'estado'        => $empresaRaw['estado'] ?? '',
            ] : [];

            $this->restoreTenantContext();

            Response::json([
                'success'    => true,
                'filiais'    => $filiaisFormatadas,
                'grupos'     => $gruposFormatados,
                'servicos'   => $servicos,
                'empresa'    => $empresa,
                'overbooking' => (bool) ($config['overbooking'] ?? false),
                'cadastro_simples' => (bool) ($config['cadastro_simples'] ?? false),
                'envio_documentos' => (bool) ($config['envio_documentos'] ?? false),
                'doc_cnh_obrigatorio' => (bool) ($config['doc_cnh_obrigatorio'] ?? false),
                'doc_cpf_obrigatorio' => (bool) ($config['doc_cpf_obrigatorio'] ?? false),
                'doc_rg_obrigatorio' => (bool) ($config['doc_rg_obrigatorio'] ?? false),
                'doc_comprovante_obrigatorio' => (bool) ($config['doc_comprovante_obrigatorio'] ?? false),
                'reserva_requer_confirmacao' => (bool) ($config['reserva_requer_confirmacao'] ?? false),
            ]);

        } catch (\Exception $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/public/disponibilidade?id_matriz_filial=&data_saida=&hora_saida=&data_prevista=&hora_devolucao=
     * Retorna por grupo se ha veiculo livre no periodo. Se overbooking=true, todos os grupos retornam disponivel=true.
     */
    public function disponibilidade(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            $chave = $config['chave'];

            $filialId    = (int) ($request->query('id_matriz_filial') ?? 0);
            $dataSaida   = (string) ($request->query('data_saida') ?? '');
            $horaSaida   = (string) ($request->query('hora_saida') ?? '00:00');
            $dataPrev    = (string) ($request->query('data_prevista') ?? '');
            $horaDev     = (string) ($request->query('hora_devolucao') ?? '00:00');

            if ($filialId <= 0
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataSaida)
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPrev)
                || !preg_match('/^\d{2}:\d{2}$/', $horaSaida)
                || !preg_match('/^\d{2}:\d{2}$/', $horaDev)
            ) {
                Response::json(['success' => false, 'message' => 'Parametros invalidos'], 400);
                return;
            }

            $inicio = $dataSaida . ' ' . $horaSaida . ':00';
            $fim    = $dataPrev  . ' ' . $horaDev  . ':00';

            $this->setTenantContext($chave);

            $overbooking = (bool) ($config['overbooking'] ?? false);

            $gruposDisponiveis = [];
            if ($overbooking) {
                // Com overbooking, todos os grupos visiveis sao considerados disponiveis
                $gruposRaw = (new Grupo())->listar();
                foreach ($gruposRaw as $g) {
                    if (empty($g['visivel_no_site'])) continue;
                    $gruposDisponiveis[(int) $g['id']] = true;
                }
            } else {
                $map = (new Veiculo())->gruposDisponiveisPorFilial($filialId, $inicio, $fim);
                foreach ($map as $idGrupo => $qtd) {
                    $gruposDisponiveis[(int) $idGrupo] = $qtd > 0;
                }
            }

            $this->restoreTenantContext();

            Response::json([
                'success'     => true,
                'overbooking' => $overbooking,
                'grupos'      => $gruposDisponiveis,
            ]);

        } catch (\Exception $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/public/conteudos
     */
    public function conteudos(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            $chave = $config['chave'];
            $idioma = $request->query('idioma', 'pt_BR');
            $appUrl = Database::env('APP_URL', 'https://locadora.7carros.com');

            $this->setTenantContext($chave);

            // Conteudos por pagina
            $conteudoModel = new SiteConteudo();
            $conteudosRaw = $conteudoModel->listarPorIdioma($idioma);
            $paginas = [];
            foreach ($conteudosRaw as $c) {
                $paginas[$c['pagina']][] = $c;
            }

            // SEO por pagina
            $seoModel = new SiteSeo();
            $seoRaw = $seoModel->listarPorIdioma($idioma);
            $seo = [];
            foreach ($seoRaw as $s) {
                $seo[$s['pagina']] = $s;
            }

            // Integracoes ativas agrupadas por tipo
            $intModel = new SiteIntegracao();
            $intRaw = $intModel->listarAtivos();
            $integracoes = ['head' => [], 'body_inicio' => [], 'body_fim' => []];
            foreach ($intRaw as $i) {
                $integracoes[$i['tipo']][] = $i;
            }

            // Banners ativos
            $bannerModel = new SiteBanner();
            $banners = $bannerModel->listarAtivos($idioma);
            foreach ($banners as &$b) {
                if (!empty($b['foto'])) {
                    $b['foto_url'] = $appUrl . '/uploads/' . $chave . '/' . $b['foto'];
                }
            }

            // Links ativos
            $linkModel = new SiteLink();
            $links = $linkModel->listarAtivos();

            $this->restoreTenantContext();

            Response::json([
                'success'      => true,
                'paginas'      => $paginas,
                'seo'          => $seo,
                'integracoes'  => $integracoes,
                'banners'      => $banners,
                'links'        => $links,
            ]);

        } catch (\Exception $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/public/status
     *
     * Flags runtime do site (manutenção, reserva_online, etc) — sem cache.
     * Consumido a cada page load pelo template, pra que mudanças no backoffice
     * reflitam imediatamente sem precisar redeploy.
     */
    public function status(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            $chave = $config['chave'];
            $appUrl = Database::env('APP_URL', 'https://locadora.7carros.com');

            // Aparência — runtime (logo, favicon, flags do logo)
            $this->setTenantContext($chave);
            $aparencia = (new \App\Models\SiteAparencia())->buscarPorChave() ?? [];
            $this->restoreTenantContext();

            $logoUrl = !empty($aparencia['logo'])
                ? $appUrl . '/uploads/' . $chave . '/' . $aparencia['logo']
                : '';
            $faviconUrl = !empty($aparencia['favicon'])
                ? $appUrl . '/uploads/' . $chave . '/' . $aparencia['favicon']
                : '';

            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');

            Response::json([
                'success'              => true,
                'status'               => $config['status'] ?? 'inativo',
                'manutencao'           => (bool) ($config['manutencao'] ?? false),
                'reserva_online'       => (bool) ($config['reserva_online'] ?? true),
                'overbooking'          => (bool) ($config['overbooking'] ?? false),
                'pagamento_antecipado' => (bool) ($config['pagamento_antecipado'] ?? false),
                'whatsapp_flutuante'   => (bool) ($config['whatsapp_flutuante'] ?? true),
                'whatsapp_numero'      => $config['whatsapp_numero'] ?? '',
                'whatsapp_mensagem'    => $config['whatsapp_mensagem'] ?? '',
                'logo_url'             => $logoUrl,
                'favicon_url'          => $faviconUrl,
                'logo_fundo_branco'    => (bool) ($aparencia['logo_fundo_branco'] ?? true),
                'logo_alinhamento'     => $aparencia['logo_alinhamento'] ?? 'centro',
            ]);
        } catch (\Throwable $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/public/reserva
     */
    public function criarReserva(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            $chave = $config['chave'];
            $dados = $request->all();

            // Campos base obrigatorios (independem do cadastro_simples)
            $obrigatorios = ['filial_retirada_id', 'data_saida', 'hora_saida', 'data_chegada', 'hora_chegada', 'grupo_id'];
            foreach ($obrigatorios as $campo) {
                if (empty($dados[$campo])) {
                    Response::json(['success' => false, 'message' => "Campo obrigatorio: {$campo}"], 422);
                    return;
                }
            }

            $clienteIdSessao = !empty($dados['cliente_id']) ? (int) $dados['cliente_id'] : 0;

            $this->setTenantContext($chave);

            $cliente = $dados['cliente'] ?? [];
            $clienteIdFinal = null;
            $clienteInfo = []; // usado p/ contexto de templates

            if ($clienteIdSessao > 0) {
                // Cliente logado pelo site. Carrega do BD (multi-tenant) e ignora form.
                $clienteModel = new \App\Models\Cliente();
                $clienteBD = $clienteModel->buscarPorId($clienteIdSessao);
                if (!$clienteBD || (($clienteBD['chave'] ?? '') !== $chave)) {
                    $this->restoreTenantContext();
                    Response::json(['success' => false, 'message' => 'Cliente invalido'], 401);
                    return;
                }
                $email = $this->qbPrimeiroContato('cliente', $clienteIdSessao, 'emails', 'email');
                $tel   = $this->qbPrimeiroContato('cliente', $clienteIdSessao, 'telefones', 'telefone');

                $clienteIdFinal = (int) $clienteBD['id'];
                $clienteInfo = [
                    'nome'      => $clienteBD['nome_rsocial'] ?? '',
                    'email'     => $email,
                    'telefone'  => $tel,
                    'documento' => $clienteBD['cpf_cnpj'] ?? '',
                ];
                $documentos = []; // cliente ja cadastrado nao reenvia docs
            } else {
                // Visitante novo — valida form completo e cria o cliente logo apos a locacao
                if (empty($cliente['nome']) || empty($cliente['email']) || empty($cliente['telefone']) || empty($cliente['documento'])) {
                    $this->restoreTenantContext();
                    Response::json(['success' => false, 'message' => 'Nome, email, celular e documento sao obrigatorios'], 422);
                    return;
                }
                if (empty($config['cadastro_simples'])) {
                    $endereco = $cliente['endereco'] ?? [];
                    foreach (['cep', 'rua', 'numero', 'bairro', 'cidade', 'estado', 'pais'] as $ef) {
                        if (empty($endereco[$ef])) {
                            $this->restoreTenantContext();
                            Response::json(['success' => false, 'message' => "Endereco: campo {$ef} obrigatorio"], 422);
                            return;
                        }
                    }
                }

                $documentos = $dados['documentos'] ?? [];
                if (!empty($config['envio_documentos'])) {
                    $mapObrig = [
                        'cnh' => !empty($config['doc_cnh_obrigatorio']),
                        'cpf' => !empty($config['doc_cpf_obrigatorio']),
                        'rg'  => !empty($config['doc_rg_obrigatorio']),
                        'comprovante' => !empty($config['doc_comprovante_obrigatorio']),
                    ];
                    foreach ($mapObrig as $tipo => $obr) {
                        if ($obr && empty($documentos[$tipo])) {
                            $this->restoreTenantContext();
                            Response::json(['success' => false, 'message' => "Documento obrigatorio: {$tipo}"], 422);
                            return;
                        }
                    }
                } else {
                    $documentos = [];
                }

                $clienteInfo = [
                    'nome'      => $cliente['nome'],
                    'email'     => $cliente['email'],
                    'telefone'  => $cliente['telefone'] ?? '',
                    'documento' => $cliente['documento'] ?? '',
                ];
                // $clienteIdFinal sera preenchido apos o INSERT do cliente (abaixo)
            }

            // Calcula dias entre data_saida e data_chegada (mínimo 1)
            $d1 = strtotime($dados['data_saida']);
            $d2 = strtotime($dados['data_chegada']);
            $dias = max(1, (int) ceil(($d2 - $d1) / 86400));

            // Codigo unico (prefixo R, 8 chars hex)
            $codigo = 'R' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));

            $requerConfirmacao = !empty($config['reserva_requer_confirmacao']);
            $statusInicial = $requerConfirmacao ? 'P' : 'R';

            // Se visitante novo, cria registro em `clientes` com senha padrao = CPF/CNPJ hash
            if ($clienteIdFinal === null) {
                $docSoDigitos = preg_replace('/\D/', '', (string) $clienteInfo['documento']);
                $clienteModel = new \App\Models\Cliente();
                $clienteIdFinal = $clienteModel->criar([
                    'chave'        => $chave,
                    'nome_rsocial' => $clienteInfo['nome'],
                    'cpf_cnpj'     => $docSoDigitos,
                    'senha'        => password_hash($docSoDigitos, PASSWORD_ARGON2ID),
                    'cep'          => ($cliente['endereco']['cep']     ?? ''),
                    'rua'          => ($cliente['endereco']['rua']     ?? ''),
                    'numero'       => ($cliente['endereco']['numero']  ?? ''),
                    'bairro'       => ($cliente['endereco']['bairro']  ?? ''),
                    'cidade'       => ($cliente['endereco']['cidade']  ?? ''),
                    'estado'       => ($cliente['endereco']['estado']  ?? ''),
                    'pais'         => ($cliente['endereco']['pais']    ?? ''),
                    'situacao'     => 'A',
                    'foto'         => '',
                ]);
                // Email + telefone em contatos (entidade_tipo='cliente')
                $this->inserirContato('cliente', $clienteIdFinal, 'emails',    'email',    $clienteInfo['email'],    $chave);
                $this->inserirContato('cliente', $clienteIdFinal, 'telefones', 'telefone', $clienteInfo['telefone'], $chave);
            }

            // Monta obs com dados funcionais (locacoes nao tem colunas dedicadas p/ isso)
            $obs = [
                'origem'    => 'site',
                'email'     => $clienteInfo['email'],
                'telefone'  => $clienteInfo['telefone'] ?? '',
                'documento' => $clienteInfo['documento'] ?? '',
                'endereco'  => $cliente['endereco'] ?? null,
            ];

            // CALCULO SERVER-SIDE do total (ignora qualquer valor enviado pelo JS)
            $calc = (new WebsiteReservaCalcService())->calcular([
                'filial_id' => (int) $dados['filial_retirada_id'],
                'grupo_id'  => (int) $dados['grupo_id'],
                'plano'     => (string) ($dados['plano'] ?? 'KML'),
                'dias'      => $dias,
                'servicos'  => $dados['servicos'] ?? [],
                'seguro_carro'     => !empty($dados['seguro_carro']),
                'seguro_terceiros' => !empty($dados['seguro_terceiros']),
            ]);
            $totalCalculado = (float) ($calc['total'] ?? 0);
            $obs['breakdown'] = $calc['breakdown'] ?? null;

            $configModel = new SiteConfig();
            $dataSaidaFull = $dados['data_saida'] . ' ' . $dados['hora_saida'] . ':00';
            $locacaoId = $configModel->queryTable('locacoes')
                ->insert([
                    'chave'                      => $chave,
                    'codigo'                     => $codigo,
                    'id_matriz_filial_retirada'  => (int) $dados['filial_retirada_id'],
                    'id_matriz_filial_devolucao' => (int) ($dados['filial_devolucao_id'] ?? $dados['filial_retirada_id']),
                    'data_saida'                 => $dataSaidaFull,
                    'data_prevista'              => $dados['data_chegada'] . ' ' . $dados['hora_chegada'] . ':00',
                    'dias'                       => $dias,
                    'cliente_nome'               => $clienteInfo['nome'],
                    'id_cliente'                 => $clienteIdFinal,
                    'status'                     => $statusInicial,
                    'obs'                        => json_encode($obs, JSON_UNESCAPED_UNICODE),
                    'total_fatura'               => $totalCalculado,
                    'total_pagar'                => $totalCalculado,
                    'created_at'                 => date('Y-m-d H:i:s'),
                ]);

            // Vincula a reserva ao grupo (sem veiculo especifico ainda).
            // Necessario para a agenda exibir a reserva sob o grupo correto.
            $configModel->queryTable('locacoes_veiculos')->insert([
                'chave'      => $chave,
                'id_locacao' => $locacaoId,
                'id_veiculo' => null,
                'id_grupo'   => (int) $dados['grupo_id'],
                'data_saida' => $dataSaidaFull,
                'plano'      => (string) ($dados['plano'] ?? 'KL'),
            ]);

            // Servicos adicionais — usa sincronizar() do LocacaoTaxaServico para
            // garantir preenchimento de todos os campos obrigatorios (nome, valores, etc).
            if (!empty($dados['servicos']) && is_array($dados['servicos'])) {
                $taxaServicoModel = new \App\Models\TaxaServico();
                $taxasMontadas = [];
                foreach ($dados['servicos'] as $servicoId) {
                    $taxa = $taxaServicoModel->buscarPorId((int) $servicoId);
                    if (!$taxa) continue;
                    $taxasMontadas[] = [
                        'id_taxa'        => (int) $taxa['id'],
                        'nome'           => $taxa['nome'] ?? '',
                        'base_calculo'   => $taxa['base_calculo'] ?? 'FIX',
                        'tipo_valor'     => $taxa['tipo_valor'] ?? 'MON',
                        'quantidade'     => 1,
                        'valor_unitario' => (float) ($taxa['valor'] ?? 0),
                    ];
                }
                if (!empty($taxasMontadas)) {
                    (new \App\Models\LocacaoTaxaServico())->sincronizar($locacaoId, $taxasMontadas, $chave);
                }
            }

            // Salva documentos via ImageHelper (aceita PDFs tambem)
            if (!empty($documentos) && is_array($documentos)) {
                $docModel = new LocacaoDocumento();
                foreach (LocacaoDocumento::TIPOS as $tipo) {
                    $base64 = $documentos[$tipo] ?? null;
                    if (empty($base64)) continue;
                    $arquivo = ImageHelper::save($base64, 'doc_' . $tipo, 'original', 80, $chave);
                    if ($arquivo) {
                        $docModel->upsert($locacaoId, $tipo, $arquivo);
                    }
                }
            }

            // Contexto para templates
            $matrizFilial = new MatrizFilial();
            $filialRet = $matrizFilial->buscarPorId((int) $dados['filial_retirada_id']);
            $filialDev = $matrizFilial->buscarPorId((int) ($dados['filial_devolucao_id'] ?? $dados['filial_retirada_id']));

            $primeiroNome = explode(' ', trim((string) ($clienteInfo['nome'] ?? '')))[0];
            $context = [
                'cliente' => [
                    'nome' => $clienteInfo['nome'] ?? '',
                    'primeiro_nome' => $primeiroNome,
                    'email' => $clienteInfo['email'] ?? '',
                    'telefone' => $clienteInfo['telefone'] ?? '',
                    'celular' => $clienteInfo['telefone'] ?? '',
                    'cpf_cnpj' => $clienteInfo['documento'] ?? '',
                ],
                'empresa' => [
                    'id' => (int) $dados['filial_retirada_id'],
                ],
                'id_matriz_filial' => (int) $dados['filial_retirada_id'],
                'locacao' => [
                    'numero' => $codigo,
                    'data_retirada' => date('d/m/Y', strtotime($dados['data_saida'])),
                    'hora_retirada' => $dados['hora_saida'],
                    'local_retirada' => trim(($filialRet['estado'] ?? '') . ' - ' . ($filialRet['cidade'] ?? $filialRet['nome_fantasia'] ?? ''), ' -'),
                    'data_devolucao' => date('d/m/Y', strtotime($dados['data_chegada'])),
                    'hora_devolucao' => $dados['hora_chegada'],
                    'local_devolucao' => trim(($filialDev['estado'] ?? '') . ' - ' . ($filialDev['cidade'] ?? $filialDev['nome_fantasia'] ?? ''), ' -'),
                    'quantidade_dias' => $dias,
                    'valor_total' => $totalCalculado,
                ],
                'outros' => [
                    'data_atual' => date('d/m/Y'),
                ],
            ];

            // Pagamento antecipado: cria financeiro (receita pendente) + link de pagamento.
            // Vinculamos financeiro a locacao via id_locacao — quando webhook confirmar pagamento,
            // o fluxo existente marca financeiro pago e a logica adicional atualiza status da
            // locacao (P -> R) e dispara confirmacao_reserva ao cliente.
            $pagamentoAntecipado = !empty($config['pagamento_antecipado']);
            $pagamentoUrl = null;
            if ($pagamentoAntecipado) {
                $hoje = date('Y-m-d');
                $vencimento = date('Y-m-d', strtotime('+2 days'));
                $idFinanceiro = $configModel->queryTable('financeiro')
                    ->insert([
                        'chave'            => $chave,
                        'id_matriz_filial' => (int) $dados['filial_retirada_id'],
                        'id_cliente'       => $clienteIdFinal,
                        'id_locacao'       => $locacaoId,
                        'descricao'        => 'Reserva ' . $codigo . ' — pagamento antecipado',
                        'tipo'             => 'R',
                        'pago'             => 'N',
                        'data_criada'      => $hoje,
                        'data_venci'       => $vencimento,
                        'parcela'          => 1,
                        'total_parcelas'   => 1,
                        'valor_total'      => $totalCalculado,
                        'valor_subtotal'  => $totalCalculado,
                    ]);

                $linkModel = new \App\Models\PagamentoLink();
                $linkId = $linkModel->criar([
                    'chave'         => $chave,
                    'id_financeiro' => $idFinanceiro,
                    'id_locacao'    => $locacaoId,
                    'id_cliente'    => $clienteIdFinal,
                    'valor'         => $totalCalculado,
                    'descricao'     => 'Reserva ' . $codigo,
                    'expires_at'    => date('Y-m-d H:i:s', strtotime('+48 hours')),
                ]);
                $link = $linkModel->buscarPorId($linkId);
                if ($link) {
                    $appUrl = Database::env('APP_URL', 'https://locadora.7carros.com');
                    $pagamentoUrl = rtrim($appUrl, '/') . '/pagar/' . $link['codigo'];
                }
            }

            $empresaMatriz = $matrizFilial->buscarMatriz();

            // Disparo de mensagens no contexto do tenant para validar canais por filial.
            //  - pedido_reserva: sempre (funciona como backup caso o cliente feche a tela)
            //  - confirmacao_reserva: so quando a reserva ja esta efetivamente confirmada
            //    (sem confirmacao manual pendente e sem pagamento antecipado pendente)
            if (function_exists('queue_template_message')) {
                foreach (['email', 'whatsapp'] as $canal) {
                    try {
                        queue_template_message('pedido_reserva', $canal, $context, $chave);
                    } catch (\Throwable $e) {
                        error_log("[Site/Publico] Erro ao enfileirar pedido_reserva/{$canal}: " . $e->getMessage());
                    }
                }

                if (!$requerConfirmacao && !$pagamentoAntecipado) {
                    foreach (['email', 'whatsapp', 'sms'] as $canal) {
                        try {
                            queue_template_message('confirmacao_reserva', $canal, $context, $chave);
                        } catch (\Throwable $e) {
                            error_log("[Site/Publico] Erro ao enfileirar confirmacao_reserva/{$canal}: " . $e->getMessage());
                        }
                    }
                }
            }

            // Notificacao 7Carros -> locadora (whatsapp sistema, dados da reserva)
            if (function_exists('queue_system_message') && !empty($empresaMatriz['celular'])) {
                $msgLocadora = "🔔 *Novo pedido de reserva*\n\n"
                    . "Codigo: *{$codigo}*\n"
                    . "Cliente: " . ($clienteInfo['nome'] ?? '-') . "\n"
                    . "Telefone: " . ($clienteInfo['telefone'] ?? '-') . "\n"
                    . "Retirada: " . date('d/m/Y', strtotime($dados['data_saida'])) . " {$dados['hora_saida']}\n"
                    . ($requerConfirmacao ? "\n⚠️ Aguarda sua confirmacao no painel." : '')
                    . ($pagamentoAntecipado ? "\n💳 Aguarda pagamento do cliente." : '');
                try {
                    queue_system_message('whatsapp', [
                        'to' => preg_replace('/\D/', '', (string) $empresaMatriz['celular']),
                        'message' => $msgLocadora,
                    ], $chave);
                } catch (\Throwable $e) {
                    error_log('[Site/Publico] Erro ao notificar locadora por WhatsApp: ' . $e->getMessage());
                }
            }

            $this->restoreTenantContext();

            Response::json([
                'success' => true,
                'codigo'  => $codigo,
                'status'  => $statusInicial,
                'requer_confirmacao' => $requerConfirmacao,
                'total'   => $totalCalculado,
                'pagamento_url' => $pagamentoUrl,
                'message' => $requerConfirmacao
                    ? 'Pedido de reserva registrado. Aguarde a confirmacao da locadora.'
                    : 'Reserva criada com sucesso',
            ]);

        } catch (\Exception $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/public/cliente-existe?chave=&documento=
     *
     * Retorna APENAS { existe: bool } — nao expoe nenhum dado pessoal.
     * Usado pelo site publico no passo 4 para decidir entre mostrar login ou form de pre-cadastro.
     */
    public function clienteExiste(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            $doc = preg_replace('/\D/', '', (string) ($request->query('documento') ?? ''));
            if (!$doc || strlen($doc) < 11) {
                Response::json(['success' => true, 'existe' => false]);
                return;
            }

            $this->setTenantContext($config['chave']);
            $row = (new \App\Models\Cliente())->buscarPorDocumentoExato($doc);
            $this->restoreTenantContext();

            Response::json(['success' => true, 'existe' => !empty($row)]);
        } catch (\Exception $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/public/cliente-login
     * body: { chave, usuario (cpf_ou_email), senha }
     *
     * Valida senha e retorna dados minimos do cliente. A sessao de navegacao
     * eh gerenciada pelo site do cliente (ajax-cliente-login.php grava
     * $_SESSION['cliente_id'] no hosting do cliente).
     */
    public function clienteLogin(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            $usuario = trim((string) ($request->input('usuario') ?? ''));
            $senha   = (string) ($request->input('senha') ?? '');
            if (!$usuario || !$senha) {
                Response::json(['success' => false, 'message' => 'Usuario e senha obrigatorios'], 422);
                return;
            }

            $this->setTenantContext($config['chave']);
            $cliente = (new \App\Models\Cliente())->buscarPorUsuarioParaLogin($usuario);

            if (!$cliente || empty($cliente['senha']) || !password_verify($senha, $cliente['senha'])) {
                $this->restoreTenantContext();
                Response::json(['success' => false, 'message' => 'Credenciais invalidas'], 401);
                return;
            }

            // Rehash transparente: migra hashes legados para Argon2id
            if (password_needs_rehash($cliente['senha'], PASSWORD_ARGON2ID)) {
                (new \App\Models\Cliente())->atualizar((int) $cliente['id'], [
                    'senha' => password_hash($senha, PASSWORD_ARGON2ID),
                ]);
            }

            $clienteId = (int) $cliente['id'];
            $emailPrincipal = (new \App\Models\ContatoEmail())->getPrincipal('cliente', $clienteId);
            $telPrincipal   = (new \App\Models\ContatoTelefone())->getPrincipal('cliente', $clienteId);
            $this->restoreTenantContext();

            Response::json([
                'success' => true,
                'cliente' => [
                    'id'       => $clienteId,
                    'nome'     => $cliente['nome_rsocial'] ?? '',
                    'email'    => $emailPrincipal['email'] ?? '',
                    'telefone' => $telPrincipal['telefone'] ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/public/cliente-senha-reset
     * body: { chave, documento }
     *
     * Gera uma senha aleatoria segura, grava hash em clientes.senha e dispara
     * o template cliente_nova_senha (email) com a senha em texto claro.
     * Resposta sempre neutra (success=true) independente de achar ou nao — evita enumeration.
     */
    public function clienteSenhaReset(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            $doc = preg_replace('/\D/', '', (string) ($request->input('documento') ?? ''));
            if (!$doc || strlen($doc) < 11) {
                Response::json(['success' => true]);
                return;
            }

            $chave = $config['chave'];
            $this->setTenantContext($chave);

            $clienteModel = new \App\Models\Cliente();
            $cliente = $clienteModel->buscarPorDocumentoExato($doc);

            if ($cliente && !empty($cliente['email'])) {
                // Gera token one-time em vez de senha. O email leva link de redefinicao.
                $resetModel = new \App\Models\ClientePasswordReset();
                $tokenPlano = $resetModel->criar((int) $cliente['id'], $chave, $request->ip());

                $baseUrl = rtrim((string) ($_ENV['APP_URL'] ?? 'https://locadora.7carros.com'), '/');
                $resetUrl = $baseUrl . '/public/redefinir-senha?token=' . $tokenPlano;

                if (function_exists('queue_template_message')) {
                    $context = [
                        'cliente' => [
                            'nome' => $cliente['nome_rsocial'] ?? '',
                            'primeiro_nome' => explode(' ', (string) ($cliente['nome_rsocial'] ?? ''))[0] ?? '',
                            'email' => $cliente['email'],
                        ],
                        'outros' => [
                            'data_atual' => date('d/m/Y'),
                            'reset_url' => $resetUrl,
                            'reset_expira_em' => \App\Models\ClientePasswordReset::TTL_MINUTES . ' minutos',
                        ],
                    ];
                    queue_template_message('cliente_nova_senha', 'email', $context, $chave);
                }
            }

            $this->restoreTenantContext();
            Response::json(['success' => true]);
        } catch (\Exception $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /public/redefinir-senha?token=XXX
     *
     * Renderiza form HTML minimalista para o cliente definir nova senha.
     * Nao depende do template do site — entrega branded simples, standalone.
     */
    public function exibirFormResetSenha(Request $request): void
    {
        $token = (string) $request->query('token', '');
        $resetModel = new \App\Models\ClientePasswordReset();
        $reset = $resetModel->validar($token);

        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        if (!$reset) {
            http_response_code(400);
            echo $this->renderResetPage([
                'titulo' => 'Link invalido ou expirado',
                'corpo' => '<p>Este link de redefinicao expirou ou ja foi usado. Solicite um novo pelo site.</p>',
                'form' => false,
                'token' => '',
            ]);
            return;
        }

        $csrfToken = bin2hex(random_bytes(16));
        \App\Core\Session::set('reset_csrf_token', $csrfToken);

        echo $this->renderResetPage([
            'titulo' => 'Definir nova senha',
            'corpo' => '<p>Digite sua nova senha abaixo. Minimo 8 caracteres.</p>',
            'form' => true,
            'token' => $token,
            'csrf' => $csrfToken,
        ]);
    }

    /**
     * POST /api/public/cliente-senha-definir
     *
     * Valida token, aplica nova senha e invalida o token.
     */
    public function clienteSenhaDefinir(Request $request): void
    {
        try {
            $token = (string) ($request->input('token') ?? '');
            $senha = (string) ($request->input('senha') ?? '');
            $csrf = (string) ($request->input('_csrf') ?? '');
            $csrfSession = \App\Core\Session::get('reset_csrf_token');

            if (!$csrfSession || !hash_equals((string) $csrfSession, $csrf)) {
                Response::json(['success' => false, 'message' => 'Sessao expirada. Abra o link novamente.'], 403);
                return;
            }

            if (strlen($senha) < 8) {
                Response::json(['success' => false, 'message' => 'A senha deve ter pelo menos 8 caracteres.'], 422);
                return;
            }

            $resetModel = new \App\Models\ClientePasswordReset();
            $reset = $resetModel->validar($token);

            if (!$reset) {
                Response::json(['success' => false, 'message' => 'Link invalido ou expirado.'], 400);
                return;
            }

            $chave = (string) $reset['chave'];
            $idCliente = (int) $reset['id_cliente'];

            $this->setTenantContext($chave);
            $hash = password_hash($senha, PASSWORD_ARGON2ID);
            (new \App\Models\Cliente())->atualizar($idCliente, ['senha' => $hash]);
            $resetModel->marcarUsado((int) $reset['id']);
            \App\Core\Session::forget('reset_csrf_token');
            $this->restoreTenantContext();

            Response::json(['success' => true, 'message' => 'Senha redefinida com sucesso.']);
        } catch (\Exception $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => 'Erro interno.'], 500);
        }
    }

    /**
     * Render interno do HTML da pagina de reset (standalone, sem template do site).
     */
    private function renderResetPage(array $data): string
    {
        $titulo = htmlspecialchars($data['titulo'], ENT_QUOTES, 'UTF-8');
        $corpo = $data['corpo']; // HTML controlado internamente
        $temForm = !empty($data['form']);
        $token = htmlspecialchars($data['token'] ?? '', ENT_QUOTES, 'UTF-8');
        $csrf = htmlspecialchars($data['csrf'] ?? '', ENT_QUOTES, 'UTF-8');

        $formHtml = '';
        if ($temForm) {
            $formHtml = <<<HTML
<form id="form-reset" method="post" action="/api/public/cliente-senha-definir">
    <input type="hidden" name="token" value="{$token}">
    <input type="hidden" name="_csrf" value="{$csrf}">
    <label>Nova senha</label>
    <input type="password" name="senha" minlength="8" required autofocus>
    <label>Confirmar senha</label>
    <input type="password" name="senha_confirmacao" minlength="8" required>
    <button type="submit">Salvar nova senha</button>
    <div id="msg"></div>
</form>
<script>
document.getElementById('form-reset').addEventListener('submit', async function(e) {
    e.preventDefault();
    var f = e.target;
    var msg = document.getElementById('msg');
    if (f.senha.value !== f.senha_confirmacao.value) {
        msg.textContent = 'As senhas nao coincidem.';
        msg.className = 'err';
        return;
    }
    msg.textContent = '';
    var fd = new FormData();
    fd.append('token', f.token.value);
    fd.append('_csrf', f._csrf.value);
    fd.append('senha', f.senha.value);
    try {
        var r = await fetch(f.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
        var j = await r.json();
        msg.textContent = j.message || (j.success ? 'Senha redefinida.' : 'Erro.');
        msg.className = j.success ? 'ok' : 'err';
        if (j.success) { f.querySelectorAll('input,button').forEach(el => el.disabled = true); }
    } catch (err) {
        msg.textContent = 'Erro de rede.';
        msg.className = 'err';
    }
});
</script>
HTML;
        }

        return <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$titulo}</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f5f7fa;margin:0;padding:40px 16px;color:#1f2937;}
.card{max-width:420px;margin:0 auto;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:32px;}
h1{margin-top:0;color:#1a56db;font-size:22px;}
label{display:block;margin-top:16px;margin-bottom:6px;font-weight:600;font-size:14px;}
input[type=password]{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:4px;font-size:15px;box-sizing:border-box;}
button{margin-top:20px;width:100%;background:#1a56db;color:#fff;border:0;padding:12px;border-radius:4px;font-size:15px;cursor:pointer;}
button:hover{background:#1e429f;}
button:disabled{background:#9ca3af;cursor:not-allowed;}
#msg{margin-top:14px;font-size:14px;}
#msg.err{color:#b91c1c;}
#msg.ok{color:#047857;}
</style>
</head>
<body>
<div class="card">
<h1>{$titulo}</h1>
{$corpo}
{$formHtml}
</div>
</body>
</html>
HTML;
    }

    /**
     * GET /api/public/cliente-por-documento?chave=&documento=
     *
     * Retorna cliente quando o CPF/CNPJ bate exato (apos normalizar para so-digitos).
     * Sempre responde success=true; data=null quando nao encontra (evita enumeration).
     * Expoe apenas campos funcionais do pre-cadastro (sem CNH, foto, docs, etc).
     */
    public function buscarClientePorDocumento(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            $chave = $config['chave'];
            $doc = preg_replace('/\D/', '', (string) ($request->query('documento') ?? ''));

            if (!$doc || strlen($doc) < 11) {
                Response::json(['success' => true, 'data' => null]);
                return;
            }

            $this->setTenantContext($chave);
            $cliente = (new \App\Models\Cliente())->buscarPorDocumentoExato($doc);
            $this->restoreTenantContext();

            if (!$cliente) {
                Response::json(['success' => true, 'data' => null]);
                return;
            }

            // Whitelist de campos expostos
            Response::json([
                'success' => true,
                'data' => [
                    'nome'     => $cliente['nome_rsocial'] ?? '',
                    'email'    => $cliente['email']        ?? '',
                    'telefone' => $cliente['telefone']     ?? '',
                    'cep'      => $cliente['cep']          ?? '',
                    'rua'      => $cliente['rua']          ?? '',
                    'numero'   => $cliente['numero']       ?? '',
                    'bairro'   => $cliente['bairro']       ?? '',
                    'cidade'   => $cliente['cidade']       ?? '',
                    'estado'   => $cliente['estado']       ?? '',
                    'pais'     => $cliente['pais']         ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/public/contato
     */
    public function contato(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            $chave = $config['chave'];
            $dados = $request->all();

            // Honeypot check
            if (!empty($dados['honeypot'] ?? $dados['website_url'] ?? '')) {
                Response::json(['success' => true, 'message' => 'Mensagem enviada']);
                return;
            }

            if (empty($dados['nome']) || empty($dados['email']) || empty($dados['mensagem'])) {
                Response::json(['success' => false, 'message' => 'Preencha todos os campos obrigatorios'], 422);
                return;
            }

            $this->setTenantContext($chave);

            // Buscar email do tenant (matriz) via MatrizFilial Model
            $mfModel = new MatrizFilial();
            $tenant = $mfModel->buscarMatriz();

            $this->restoreTenantContext();

            $emailDestino = $tenant['email'] ?? '';
            if (empty($emailDestino)) {
                Response::json(['success' => false, 'message' => 'Email de destino nao configurado'], 500);
                return;
            }

            $body = "<h2>Mensagem do Site</h2>";
            $body .= "<p><strong>Nome:</strong> " . htmlspecialchars($dados['nome']) . "</p>";
            $body .= "<p><strong>Email:</strong> " . htmlspecialchars($dados['email']) . "</p>";
            if (!empty($dados['telefone'])) {
                $body .= "<p><strong>Telefone:</strong> " . htmlspecialchars($dados['telefone']) . "</p>";
            }
            $body .= "<p><strong>Mensagem:</strong></p><p>" . nl2br(htmlspecialchars($dados['mensagem'])) . "</p>";

            if (function_exists('queue_message')) {
                queue_message('email', [
                    'to'       => $emailDestino,
                    'subject'  => 'Contato do Site - ' . htmlspecialchars($dados['nome']),
                    'body'     => $body,
                    'reply_to' => $dados['email'],
                ], $chave);
            }

            Response::json(['success' => true, 'message' => 'Mensagem enviada com sucesso']);

        } catch (\Exception $e) {
            $this->restoreTenantContext();
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/public/limpar-cache
     */
    public function limparCache(Request $request): void
    {
        try {
            $config = $this->autenticar($request);
            if (!$config) return;

            Response::json(['success' => true, 'message' => 'Cache invalidado']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Busca o primeiro contato (email/telefone) de uma entidade (cliente/matriz_filial).
     * Preferencia para `principal='S'` senao o primeiro cadastrado.
     */
    private function qbPrimeiroContato(string $entidadeTipo, int $entidadeId, string $tabela, string $campo): string
    {
        $sc = new SiteConfig();
        $row = $sc->queryTable("contatos_{$tabela}")
            ->select([$campo])
            ->where('entidade_tipo', '=', $entidadeTipo)
            ->where('entidade_id', '=', $entidadeId)
            ->orderByRaw("principal = 'S' DESC, id ASC")
            ->first();
        return (string) ($row[$campo] ?? '');
    }

    /**
     * Insere um contato (email ou telefone) marcado como principal.
     */
    private function inserirContato(string $entidadeTipo, int $entidadeId, string $tabela, string $campo, string $valor, string $chave): void
    {
        $valor = trim($valor);
        if ($valor === '') return;
        $sc = new SiteConfig();
        $sc->queryTable("contatos_{$tabela}")
            ->insert([
                'chave'         => $chave,
                'entidade_tipo' => $entidadeTipo,
                'entidade_id'   => $entidadeId,
                $campo          => $valor,
                'principal'     => 'S',
            ]);
    }

    private function setTenantContext(string $chave): void
    {
        $this->previousChave = $_SESSION['chave'] ?? null;
        $_SESSION['chave'] = $chave;
    }

    private function restoreTenantContext(): void
    {
        if ($this->previousChave !== null) {
            $_SESSION['chave'] = $this->previousChave;
        } else {
            unset($_SESSION['chave']);
        }
        $this->previousChave = null;
    }

    /**
     * Autentica via X-Site-Token + chave
     * Usa SiteConfig com withoutChave() para busca cross-tenant
     */
    private function autenticar(Request $request): ?array
    {
        $token = $request->header('X-Site-Token') ?? '';
        $chave = $request->query('chave') ?? $request->input('chave') ?? '';

        if (empty($chave)) {
            Response::json(['success' => false, 'message' => 'Chave nao fornecida'], 401);
            return null;
        }

        // Buscar config via SiteConfig Model com withoutChave (cross-tenant)
        $configModel = new SiteConfig();
        $config = $configModel->buscarPorChaveExplicita($chave);

        if (!$config || $config['status'] !== 'ativo') {
            Response::json(['success' => false, 'message' => 'Site nao encontrado ou inativo'], 401);
            return null;
        }

        // Validar token
        if (!empty($config['api_token'])) {
            $decryptedToken = decrypt($config['api_token']);
            if ($decryptedToken !== null && !hash_equals($decryptedToken, $token)) {
                Response::json(['success' => false, 'message' => 'Token invalido'], 401);
                return null;
            }
        }

        // CORS header
        if (!empty($config['dominio'])) {
            $origin = $request->header('Origin') ?? '';
            $allowedOrigin = 'https://' . $config['dominio'];
            if ($origin === $allowedOrigin || $origin === 'http://' . $config['dominio']) {
                header('Access-Control-Allow-Origin: ' . $origin);
            }
            header('Access-Control-Allow-Headers: Content-Type, X-Site-Token');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        }

        return $config;
    }

    /**
     * Monta horarios de uma filial no formato consumido pelo JS do site:
     *   { 0: [{abertura, fechamento}, ...], 1: [...], ..., 6: [...] }
     * Dias sem horario cadastrado sao arrays vazios (= fechado).
     */
    private function montarHorariosJs(int $idFilial): array
    {
        $agrupado = (new HorarioFuncionamento())->listarPorMatriz($idFilial);
        $out = [];
        for ($d = 0; $d <= 6; $d++) {
            $out[$d] = [];
            if (isset($agrupado[$d]['periodos'])) {
                foreach ($agrupado[$d]['periodos'] as $p) {
                    $out[$d][] = [
                        'abertura'   => $p['abertura'],
                        'fechamento' => $p['fechamento'],
                    ];
                }
            }
        }
        return $out;
    }

    /**
     * Retorna os precos de todos os grupos nessa filial, indexados por id_grupo.
     * Cada valor ja vem na moeda da filial (GrupoPrecoFilial — refactor multi-moeda).
     *
     * @return array<int, array> mapa id_grupo => { valor_plano_km_*, valor_seguro_*, ... }
     */
    private function montarPrecosGruposJs(int $idFilial): array
    {
        $linhas = (new GrupoPrecoFilial())->listarPorFilial($idFilial);
        $out = [];
        foreach ($linhas as $r) {
            $idGrupo = (int) $r['id_grupo'];
            $out[$idGrupo] = [
                'valor_plano_km_pago'       => (float) ($r['valor_plano_km_pago'] ?? 0),
                'valor_plano_km_controlado' => (float) ($r['valor_plano_km_controlado'] ?? 0),
                'valor_plano_km_livre'      => (float) ($r['valor_plano_km_livre'] ?? 0),
                'valor_km_excedente'        => (float) ($r['valor_km_excedente'] ?? 0),
                'km_franquia'               => (int) ($r['km_franquia'] ?? 0),
                'valor_seguro_carro'        => (float) ($r['valor_seguro_carro'] ?? 0),
                'valor_seguro_terceiros'    => (float) ($r['valor_seguro_terceiros'] ?? 0),
                'cobertura_carro'           => (float) ($r['cobertura_carro'] ?? 0),
                'cobertura_terceiros'       => (float) ($r['cobertura_terceiros'] ?? 0),
                'valor_km_retorno'          => (float) ($r['valor_km_retorno'] ?? 0),
                'valor_condutor_adicional'  => (float) ($r['valor_condutor_adicional'] ?? 0),
                'valor_tolerancia'          => (float) ($r['valor_tolerancia'] ?? 0),
                'minutos_tolerancia'        => (int) ($r['minutos_tolerancia'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Retorna os valores monetarios de taxas/servicos nessa filial, indexados por id_taxaservico.
     * Cobre apenas taxas MON (moeda). Taxas POR (porcentagem) usam o valor global de taxaseservicos.valor.
     *
     * @return array<int, float> mapa id_taxaservico => valor na moeda da filial
     */
    private function montarValoresServicosJs(int $idFilial): array
    {
        $linhas = (new TaxaServicoValorFilial())->listarPorFilial($idFilial);
        $out = [];
        foreach ($linhas as $r) {
            $out[(int) $r['id_taxaservico']] = (float) ($r['valor'] ?? 0);
        }
        return $out;
    }

    /**
     * Retorna locais de atendimento de uma filial, formatados para o site.
     * Cada local tem label (nome se preenchido, senao "Bairro, Cidade/UF").
     */
    private function montarLocaisJs(int $idFilial): array
    {
        $linhas = (new MatrizFilialLocal())->listarPorFilial($idFilial);
        $out = [];
        foreach ($linhas as $l) {
            $bairro = trim($l['bairro'] ?? '');
            $cidade = trim($l['cidade'] ?? '');
            $estado = trim($l['estado'] ?? '');
            $nome   = trim($l['nome'] ?? '');
            $label  = $nome !== ''
                ? $nome
                : trim(implode(', ', array_filter([$bairro, trim($cidade . ($estado ? '/' . $estado : ''))])));
            $out[] = [
                'id'          => (int) $l['id'],
                'nome'        => $nome,
                'label'       => $label,
                'bairro'      => $bairro,
                'cidade'      => $cidade,
                'estado'      => $estado,
                'cep'         => $l['cep'] ?? '',
                'rua'         => $l['rua'] ?? '',
                'numero'      => $l['numero'] ?? '',
                'complemento' => $l['complemento'] ?? '',
            ];
        }
        return $out;
    }

    /**
     * Retorna excecoes futuras de uma filial indexadas por data (Y-m-d).
     * Ignora excecoes ja passadas para nao inflar o payload.
     */
    private function montarExcecoesFuturas(int $idFilial): array
    {
        $lista = (new HorarioExcecao())->listarPorMatriz(
            $idFilial,
            date('Y-m-d'),
            null
        );
        $out = [];
        foreach ($lista as $e) {
            $out[$e['data']] = [
                'tipo'       => $e['tipo'],
                'abertura'   => $e['abertura'],
                'fechamento' => $e['fechamento'],
                'descricao'  => $e['descricao'],
            ];
        }
        return $out;
    }
}
