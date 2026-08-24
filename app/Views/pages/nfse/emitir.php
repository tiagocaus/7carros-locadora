@extends('layouts.iframe')

@section('title', t('modules.nfse.emit_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page"><?= t('modules.nfse.emit_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <form id="formEmitir">
        @csrf
        <input type="hidden" name="id_financeiro" id="inputIdFinanceiro">

        <div class="form-section mb-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Dados do Prestador (readonly) -->
                <div>
                    <h3 class="form-section-title">
                        <i class="fas fa-building mr-2"></i><?= t('modules.nfse.sections.prestador') ?>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-8 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.fields.prestador') ?></label>
                            <input type="text" id="prestadorNome" class="form-input-group-field bg-slate-50" readonly>
                        </div>
                        <div class="md:col-span-4 form-input-group">
                            <label class="form-label-group">CNPJ</label>
                            <input type="text" id="prestadorCnpj" class="form-input-group-field bg-slate-50" readonly>
                        </div>
                        <div class="md:col-span-6 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.fields.tipo_emissao') ?></label>
                            <input type="text" id="tipoEmissao" class="form-input-group-field bg-slate-50" readonly>
                        </div>
                        <div class="md:col-span-6 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.fields.ambiente') ?></label>
                            <input type="text" id="ambienteEmissao" class="form-input-group-field bg-slate-50" readonly>
                        </div>
                    </div>
                </div>

                <!-- Dados do Tomador -->
                <div>
                    <h3 class="form-section-title">
                        <i class="fas fa-user mr-2"></i><?= t('modules.nfse.sections.tomador') ?>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-7 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.fields.tomador_nome') ?></label>
                            <input type="text" name="tomador_nome" id="tomadorNome" class="form-input-group-field bg-slate-50" readonly>
                        </div>
                        <div class="md:col-span-5 form-input-group">
                            <label class="form-label-group" id="tomadorDocumentoLabel"><?= t('modules.nfse.fields.tomador_cpf_cnpj') ?></label>
                            <input type="text" name="tomador_cpf_cnpj" id="tomadorCpfCnpj" class="form-input-group-field bg-slate-50" readonly>
                        </div>
                        <div class="md:col-span-8 form-input-group">
                            <label class="form-label-group"><?= t('modules.nfse.fields.tomador_email') ?></label>
                            <input type="email" name="tomador_email" id="tomadorEmail" class="form-input-group-field">
                        </div>
                        <div class="md:col-span-4 form-input-group" id="tomadorMunicipioGroup">
                            <label class="form-label-group"><?= t('modules.nfse.fields.tomador_codigo_municipio') ?></label>
                            <input type="text" name="tomador_codigo_municipio" id="tomadorCodigoMunicipio" class="form-input-group-field" maxlength="7" placeholder="0000000">
                        </div>
                        <div class="md:col-span-4 form-input-group hidden" id="tomadorPaisGroup">
                            <label class="form-label-group"><?= t('modules.nfse.fields.tomador_pais') ?></label>
                            <input type="text" id="tomadorPais" class="form-input-group-field bg-slate-50" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Servico -->
        <div class="form-section mb-6">
            <h3 class="form-section-title">
                <i class="fas fa-concierge-bell mr-2"></i><?= t('modules.nfse.sections.servico') ?>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-12 form-input-group">
                    <label class="form-label-group"><?= t('modules.nfse.fields.descricao_servico') ?></label>
                    <textarea name="descricao_servico" id="inputDescricaoServico" class="form-input-group-field" rows="2"></textarea>
                </div>
            </div>
        </div>

        <!-- Itens do Lancamento -->
        <div class="form-section mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                <h3 class="form-section-title mb-0">
                    <i class="fas fa-list mr-2"></i>Itens do Lançamento
                </h3>
                <button type="button" id="btnAdicionarItemNaoTributavel" class="btn-secondary py-2 px-3 rounded-md text-sm font-medium">
                    <i class="fas fa-plus mr-2"></i>Adicionar item não tributável
                </button>
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-center w-24">Trib.?</th>
                            <th class="px-3 py-2 text-left">Descrição</th>
                            <th class="px-3 py-2 text-right w-40">Valor</th>
                            <th class="px-3 py-2 text-center w-12"></th>
                        </tr>
                    </thead>
                    <tbody id="tbodyItensLancamento" class="divide-y divide-slate-200">
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-slate-500">Carregando itens...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Valores -->
        <div class="form-section mb-6">
            <h3 class="form-section-title">
                <i class="fas fa-calculator mr-2"></i><?= t('modules.nfse.sections.valores') ?>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group">Valor total da NFS-e</label>
                    <div class="input-group-with-addon">
                        <span class="input-addon-left currency-symbol">R$</span>
                        <input type="text" id="valorServicos" class="form-input-group-field input-moeda pl-12 bg-slate-50" readonly>
                    </div>
                </div>
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group">Valor tributável</label>
                    <div class="input-group-with-addon">
                        <span class="input-addon-left currency-symbol">R$</span>
                        <input type="text" id="valorTributavel" class="form-input-group-field input-moeda pl-12 bg-slate-50" readonly>
                    </div>
                </div>
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group">Itens não tributáveis</label>
                    <div class="input-group-with-addon">
                        <span class="input-addon-left currency-symbol">R$</span>
                        <input type="text" name="valor_deducoes" id="valorDeducoes" class="form-input-group-field input-moeda pl-12 bg-slate-50" value="0,00" readonly>
                    </div>
                </div>
                <div class="md:col-span-3 form-input-group">
                    <label class="form-label-group"><?= t('modules.nfse.fields.base_calculo') ?></label>
                    <div class="input-group-with-addon">
                        <span class="input-addon-left currency-symbol">R$</span>
                        <input type="text" id="baseCalculo" class="form-input-group-field pl-12 bg-slate-50" readonly>
                    </div>
                </div>
                <div class="md:col-span-12 form-input-group">
                    <label class="flex items-center cursor-pointer mt-6">
                        <input type="checkbox" name="iss_retido" value="S" id="inputIssRetido" class="form-checkbox h-4 w-4 text-blue-600">
                        <span class="ml-2 text-sm text-slate-700"><?= t('modules.nfse.fields.iss_retido') ?></span>
                    </label>
                </div>
            </div>

            <!-- Resumo tributos -->
            <div class="mt-4 p-3 bg-slate-50 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-center">
                    <div>
                        <span class="text-slate-500">ISS</span>
                        <div class="font-medium" id="resumoISS">R$ 0,00</div>
                    </div>
                    <div id="resumoIBSBox" class="hidden">
                        <span class="text-slate-500" id="resumoIBSLabel">IBS</span>
                        <div class="font-medium" id="resumoIBS">R$ 0,00</div>
                    </div>
                    <div id="resumoCBSBox" class="hidden">
                        <span class="text-slate-500" id="resumoCBSLabel">CBS</span>
                        <div class="font-medium" id="resumoCBS">R$ 0,00</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info ambiente -->
        <div id="avisoHomologacao" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700">
            <i class="fas fa-exclamation-triangle mr-2"></i><?= t('modules.nfse.messages.homologacao_aviso') ?>
        </div>

        <div id="avisoConfiguracao" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <i class="fas fa-exclamation-circle mr-2"></i><span id="avisoConfiguracaoTexto"></span>
        </div>

        <!-- Botoes -->
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" class="btn-secondary py-2 px-4 rounded-md text-sm" id="btnCancelarForm">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm font-medium" id="btnEmitir">
                <i class="fas fa-paper-plane mr-2"></i><?= t('modules.nfse.buttons.emit') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
    let financeiroData = null;
    let configData = null;
    let aliquotaISS = 0;
    let emissaoBloqueada = false;
    let nfseDuplicadaParaVisualizar = null;

    // Pegar id_financeiro da URL
    const urlParams = new URLSearchParams(window.location.search);
    const idFinanceiro = urlParams.get('id_financeiro');

    if (!idFinanceiro) {
        window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.financeiro_required') ?>' }, '*');
        voltarParaLista();
        return;
    }

    document.getElementById('inputIdFinanceiro').value = idFinanceiro;

    // Init
    window.pageLoading.start();
    carregarDadosFinanceiro(idFinanceiro);
    setupEventListeners();

    function setupEventListeners() {
        document.getElementById('formEmitir').addEventListener('submit', emitirNfse);
        document.getElementById('btnVoltar').addEventListener('click', voltarParaLista);
        document.getElementById('btnCancelarForm').addEventListener('click', voltarParaLista);
        document.getElementById('btnAdicionarItemNaoTributavel').addEventListener('click', adicionarItemManualNaoTributavel);

        window.addEventListener('message', function (event) {
            if (event.data && event.data.action === 'genericConfirmed' && nfseDuplicadaParaVisualizar) {
                const nfse = nfseDuplicadaParaVisualizar;
                nfseDuplicadaParaVisualizar = null;
                abrirNfseEmNovaAba(nfse);
            }

            if (event.data && event.data.action === 'genericModalClosed') {
                nfseDuplicadaParaVisualizar = null;
            }
        });
    }

    async function carregarDadosFinanceiro(id) {
        try {
            // Carregar financeiro
            const result = await API.get(`/api/financeiro/${id}`);
            if (!result.success || !result.data) {
                window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.load_error') ?>' }, '*');
                voltarParaLista();
                return;
            }
            financeiroData = result.data;

            // Carregar config da filial. Ausencia de configuracao nao impede
            // renderizar o lancamento, apenas bloqueia a emissao.
            const filialId = financeiroData.id_matriz_filial;
            try {
                const configResult = await API.get('/api/nfse/configuracoes', { filial: filialId });
                configData = configResult.success ? configResult.data : null;
                if (!configResult.success) {
                    definirAvisoConfiguracao(configResult.message || 'Não foi possível consultar a configuração de NFS-e desta filial.');
                }
            } catch (configError) {
                configData = null;
                definirAvisoConfiguracao('Não foi possível consultar a configuração de NFS-e desta filial.');
            }

            preencherDados();
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.load_error') ?>' }, '*');
            voltarParaLista();
        } finally {
            window.pageLoading.done();
        }
    }

    function preencherDados() {
        if (!financeiroData) return;

        // Prestador
        document.getElementById('prestadorNome').value = financeiroData.filial_razao_social || financeiroData.filial_nome || '';
        document.getElementById('prestadorCnpj').value = financeiroData.filial_cnpj || '';

        // Tomador
        document.getElementById('tomadorNome').value = financeiroData.cliente_nome || '';
        document.getElementById('tomadorCpfCnpj').value = financeiroData.cliente_cpf_cnpj || '';
        document.getElementById('tomadorEmail').value = financeiroData.cliente_email || '';
        document.getElementById('tomadorCodigoMunicipio').value = financeiroData.cliente_codigo_municipio || '';
        const estrangeiro = financeiroData.cliente_tipo === 'ES';
        document.getElementById('tomadorDocumentoLabel').textContent = estrangeiro
            ? '<?= t('modules.nfse.fields.tomador_passaporte') ?>'
            : '<?= t('modules.nfse.fields.tomador_cpf_cnpj') ?>';
        document.getElementById('tomadorMunicipioGroup').classList.toggle('hidden', estrangeiro);
        document.getElementById('tomadorPaisGroup').classList.toggle('hidden', !estrangeiro);
        document.getElementById('tomadorPais').value = financeiroData.cliente_pais || '';

        // Valor
        const valorTotal = parseFloat(financeiroData.valor_total || 0);
        document.getElementById('valorServicos').value = Currency.format(valorTotal, false);

        // Descricao do servico (da config)
        if (configData && configData.descricao_servico) {
            document.getElementById('inputDescricaoServico').value = configData.descricao_servico;
        }

        // Aliquota ISS da config
        aliquotaISS = configData ? parseFloat(configData.aliquota_iss || 0) : 0;
        document.getElementById('tipoEmissao').value = getTipoEmissaoLabel(configData?.tipo_emissao);
        document.getElementById('ambienteEmissao').value = parseInt(configData?.ambiente || 2) === 1 ? 'Produção' : 'Homologação';

        // Aviso homologacao
        if (configData && parseInt(configData.ambiente) !== 1) {
            document.getElementById('avisoHomologacao').classList.remove('hidden');
        }

        atualizarEstadoConfiguracao();

        renderizarItensLancamento();
        calcularTributos();
    }

    function renderizarItensLancamento() {
        const tbody = document.getElementById('tbodyItensLancamento');
        const itens = Array.isArray(financeiroData?.itens) ? financeiroData.itens : [];

        if (itens.length === 0) {
            const descricao = financeiroData?.descricao || 'Serviço prestado';
            const valor = parseFloat(financeiroData?.valor_total || 0);
            tbody.innerHTML = montarLinhaItem({
                descricao,
                valor,
                tributavel: true,
                manual: false,
            });
        } else {
            tbody.innerHTML = itens.map(item => montarLinhaItem({
                descricao: item.descricao || financeiroData?.descricao || 'Serviço prestado',
                valor: parseFloat(item.valor || 0),
                tributavel: true,
                manual: false,
            })).join('');
        }

        vincularEventosItens();
    }

    function montarLinhaItem(item) {
        const descricao = escapeHtml(item.descricao || '');
        const valor = Number.isFinite(item.valor) ? item.valor : 0;

        if (item.manual) {
            return `
                <tr class="nfse-item-row" data-manual="1">
                    <td class="px-3 py-2 text-center">
                        <input type="checkbox" class="nfse-item-tributavel form-checkbox h-4 w-4 text-blue-600" disabled>
                    </td>
                    <td class="px-3 py-2">
                        <input type="text" class="nfse-item-descricao form-input-group-field text-sm" placeholder="Item não tributável manual">
                    </td>
                    <td class="px-3 py-2">
                        <input type="text" class="nfse-item-valor form-input-group-field input-moeda text-sm text-right" value="0,00">
                    </td>
                    <td class="px-3 py-2 text-center">
                        <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-item" title="Remover item">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }

        return `
            <tr class="nfse-item-row" data-manual="0">
                <td class="px-3 py-2 text-center">
                    <input type="checkbox" class="nfse-item-tributavel form-checkbox h-4 w-4 text-blue-600" checked>
                </td>
                <td class="px-3 py-2">
                    <span class="nfse-item-descricao-text">${descricao}</span>
                </td>
                <td class="px-3 py-2 text-right font-medium" data-valor="${valor}">
                    ${Currency.format(valor, true)}
                </td>
                <td class="px-3 py-2"></td>
            </tr>
        `;
    }

    function vincularEventosItens() {
        document.querySelectorAll('.nfse-item-tributavel').forEach(input => {
            input.addEventListener('change', calcularTributos);
        });

        document.querySelectorAll('.nfse-item-valor').forEach(input => {
            input.addEventListener('input', calcularTributos);
            input.addEventListener('change', calcularTributos);
        });

        document.querySelectorAll('.nfse-item-descricao').forEach(input => {
            input.addEventListener('input', calcularTributos);
        });

        document.querySelectorAll('.btn-remover-item').forEach(btn => {
            btn.addEventListener('click', function () {
                this.closest('tr')?.remove();
                calcularTributos();
            });
        });
    }

    function adicionarItemManualNaoTributavel() {
        document.getElementById('tbodyItensLancamento').insertAdjacentHTML('beforeend', montarLinhaItem({
            descricao: '',
            valor: 0,
            tributavel: false,
            manual: true,
        }));
        vincularEventosItens();
        calcularTributos();
    }

    function calcularTributos() {
        const valorServicos = parseFloat(financeiroData?.valor_total || 0);
        const valorDeducoes = calcularTotalNaoTributavel();
        const baseCalculo = valorServicos - valorDeducoes;

        document.getElementById('valorDeducoes').value = Currency.format(valorDeducoes, false);
        document.getElementById('valorTributavel').value = Currency.format(Math.max(0, baseCalculo), false);
        document.getElementById('baseCalculo').value = Currency.format(Math.max(0, baseCalculo), false);

        const tribISSQN = configData ? parseInt(configData.trib_issqn || 4) : 4;
        const valorISS = tribISSQN === 1 ? Math.max(0, baseCalculo) * (aliquotaISS / 100) : 0;
        const preencherIBSCBS = configData?.preencher_ibscbs === 'S';
        const aliquotaIBS = preencherIBSCBS ? parseFloat(configData?.aliquota_ibs || 0) : 0;
        const aliquotaCBS = preencherIBSCBS ? parseFloat(configData?.aliquota_cbs || 0) : 0;
        const valorIBS = preencherIBSCBS ? valorServicos * (aliquotaIBS / 100) : 0;
        const valorCBS = preencherIBSCBS ? valorServicos * (aliquotaCBS / 100) : 0;

        document.getElementById('resumoISS').textContent = Currency.format(valorISS, true);
        document.getElementById('resumoIBS').textContent = Currency.format(valorIBS, true);
        document.getElementById('resumoCBS').textContent = Currency.format(valorCBS, true);
        document.getElementById('resumoIBSLabel').textContent = `IBS (${aliquotaIBS.toFixed(2).replace('.', ',')}%)`;
        document.getElementById('resumoCBSLabel').textContent = `CBS (${aliquotaCBS.toFixed(2).replace('.', ',')}%)`;
        document.getElementById('resumoIBSBox').classList.toggle('hidden', !preencherIBSCBS);
        document.getElementById('resumoCBSBox').classList.toggle('hidden', !preencherIBSCBS);
    }

    function calcularTotalNaoTributavel() {
        return coletarItensNaoTributaveis()
            .reduce((total, item) => total + item.valor, 0);
    }

    function coletarItensNaoTributaveis() {
        const itens = [];

        document.querySelectorAll('.nfse-item-row').forEach(row => {
            const manual = row.dataset.manual === '1';
            const tributavelInput = row.querySelector('.nfse-item-tributavel');
            const tributavel = manual ? false : !!tributavelInput?.checked;

            if (tributavel) {
                return;
            }

            const descricao = manual
                ? (row.querySelector('.nfse-item-descricao')?.value || '').trim()
                : (row.querySelector('.nfse-item-descricao-text')?.textContent || '').trim();
            const valor = manual
                ? (Currency.parse(row.querySelector('.nfse-item-valor')?.value || '0') || 0)
                : parseFloat(row.querySelector('[data-valor]')?.dataset.valor || 0);

            if (descricao || valor > 0) {
                itens.push({ descricao, valor });
            }
        });

        return itens;
    }

    function validarItensNaoTributaveis(itensNaoTributaveis, valorServicos) {
        let total = 0;
        for (const item of itensNaoTributaveis) {
            if (!item.descricao || item.valor <= 0) {
                return 'Itens não tributáveis precisam ter descrição e valor maior que zero.';
            }
            total += item.valor;
        }

        if (total > valorServicos) {
            return 'Itens não tributáveis não podem ultrapassar o valor total da NFS-e.';
        }

        return '';
    }

    async function emitirNfse(e) {
        e.preventDefault();

        if (emissaoBloqueada) {
            const message = document.getElementById('avisoConfiguracaoTexto').textContent || 'Configure a NFS-e antes de emitir.';
            window.parent.postMessage({ action: 'openAlert', message }, '*');
            return;
        }

        const btn = document.getElementById('btnEmitir');
        const textoOriginal = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Emitindo...';
        btn.disabled = true;

        try {
            const itensNaoTributaveis = coletarItensNaoTributaveis();
            const valorServicos = parseFloat(financeiroData?.valor_total || 0);
            const erroItens = validarItensNaoTributaveis(itensNaoTributaveis, valorServicos);
            if (erroItens) {
                window.parent.postMessage({ action: 'openAlert', message: erroItens }, '*');
                return;
            }

            const valorDeducoes = itensNaoTributaveis.reduce((total, item) => total + item.valor, 0);
            const dados = {
                id_financeiro: idFinanceiro,
                valor_deducoes: valorDeducoes,
                itens_nao_tributaveis: itensNaoTributaveis,
                descricao_servico: document.getElementById('inputDescricaoServico').value,
                iss_retido: document.getElementById('inputIssRetido').checked ? 'S' : 'N',
                tomador_email: document.getElementById('tomadorEmail').value,
                tomador_codigo_municipio: document.getElementById('tomadorCodigoMunicipio').value.replace(/\D/g, ''),
            };

            const result = await API.post('/nfse/emitir', dados);

            if (result.success) {
                window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.emit_success') ?>' }, '*');
                // Navegar para visualizar
                if (result.data && result.data.id) {
                    window.parent.postMessage({ action: 'navigate', page: `/pages/nfse/${result.data.id}/visualizar` }, '*');
                } else {
                    voltarParaLista();
                }
            } else {
                if (ehDuplicidadeComNfse(result)) {
                    mostrarConfirmacaoNfseDuplicada(result.data);
                    return;
                }

                const msg = formatarErroNfse(result, <?= js_t('modules.nfse.messages.emit_error') ?>);
                window.parent.postMessage({ action: 'openAlert', message: msg }, '*');
            }
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: <?= js_t('modules.nfse.messages.emit_error') ?> }, '*');
        } finally {
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        }
    }

    function formatarErroNfse(result, fallback) {
        const erro = result?.erro || {};
        const errosApi = Array.isArray(result?.erros_api) ? result.erros_api : [];
        const linhas = [];

        if (result?.message) {
            linhas.push(result.message);
        } else if (erro?.mensagem) {
            linhas.push(erro.mensagem);
        }

        if (erro?.instrucao && !linhas.includes(erro.instrucao)) {
            linhas.push(`${<?= js_t('modules.nfse.messages.error_correction') ?>}: ${erro.instrucao}`);
        }

        if (erro?.explicacao && !linhas.includes(erro.explicacao)) {
            linhas.push(erro.explicacao);
        }

        if (linhas.length > 0) {
            const codigos = errosApi
                .map((item) => item?.codigo)
                .filter(Boolean)
                .filter((codigo, index, lista) => lista.indexOf(codigo) === index);

            if (codigos.length > 0) {
                linhas.push(`${<?= js_t('modules.nfse.messages.error_technical_code') ?>}: ${codigos.join(', ')}`);
            } else if (erro?.codigo) {
                linhas.push(`${<?= js_t('modules.nfse.messages.error_technical_code') ?>}: ${erro.codigo}`);
            }

            return linhas.join('\n\n');
        }

        const detalhes = errosApi
            .map((erro) => {
                const codigo = erro?.codigo ? `${erro.codigo}: ` : '';
                return `${codigo}${erro?.mensagem || ''}`.trim();
            })
            .filter(Boolean);

        if (detalhes.length > 0) {
            return detalhes.join('\n');
        }

        return result?.message || fallback;
    }

    function ehDuplicidadeComNfse(result) {
        const erro = result?.erro || {};
        return !!(
            result?.data?.id &&
            (erro.codigo === 'NOTA_DUPLICADA' || erro.categoria === 'duplicidade')
        );
    }

    function mostrarConfirmacaoNfseDuplicada(nfse) {
        nfseDuplicadaParaVisualizar = nfse;
        window.parent.postMessage({
            action: 'openGenericConfirmModal',
            title: 'NFS-e já emitida',
            message: 'Já existe uma NFS-e emitida para este lançamento. Deseja visualizar a nota?',
            confirmText: 'Visualizar'
        }, '*');
    }

    function abrirNfseEmNovaAba(nfse) {
        const id = parseInt(nfse?.id || 0, 10);
        if (!id) return;

        const numero = nfse?.numero ? String(nfse.numero) : String(id);
        const page = `/pages/nfse/${id}/visualizar`;
        const title = `NFS-e #${numero}`;

        if (window.parent && typeof window.parent.openOrSwitchToTab === 'function') {
            window.parent.openOrSwitchToTab(page, title, 'fas fa-file-invoice', `nfse-${id}`);
            return;
        }

        window.parent.postMessage({ action: 'navigate', page }, '*');
    }

    function voltarParaLista() {
        window.parent.postMessage({ action: 'navigate', page: '/pages/nfse' }, '*');
    }

    function getTipoEmissaoLabel(tipo) {
        if (tipo === 'betha') return 'Betha Cloud';
        if (tipo === 'issnet') return 'ISSNet';
        return 'Nacional';
    }

    function atualizarEstadoConfiguracao() {
        if (!configData) {
            definirAvisoConfiguracao('Configuração de NFS-e não encontrada para esta filial. Configure a NFS-e antes de emitir.');
            return;
        }

        if ((configData.ativo || 'N') !== 'S') {
            definirAvisoConfiguracao('Emissão de NFS-e desativada para esta filial.');
            return;
        }

        if (!configData.certificado_arquivo) {
            definirAvisoConfiguracao('Certificado digital não configurado. Configure o certificado antes de emitir.');
            return;
        }

        limparAvisoConfiguracao();
    }

    function definirAvisoConfiguracao(message) {
        emissaoBloqueada = true;
        document.getElementById('avisoConfiguracaoTexto').textContent = message;
        document.getElementById('avisoConfiguracao').classList.remove('hidden');
        const btn = document.getElementById('btnEmitir');
        btn.disabled = true;
        btn.classList.add('opacity-60', 'cursor-not-allowed');
    }

    function limparAvisoConfiguracao() {
        emissaoBloqueada = false;
        document.getElementById('avisoConfiguracaoTexto').textContent = '';
        document.getElementById('avisoConfiguracao').classList.add('hidden');
        const btn = document.getElementById('btnEmitir');
        btn.disabled = false;
        btn.classList.remove('opacity-60', 'cursor-not-allowed');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
</script>
@endsection
