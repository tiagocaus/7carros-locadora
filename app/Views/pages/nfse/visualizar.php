@extends('layouts.iframe')

@section('title', t('modules.nfse.view_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page"><?= t('modules.nfse.view_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Status e Acoes -->
    <div class="flex flex-wrap items-center justify-between mb-6 p-4 bg-white rounded-lg shadow" id="headerBar">
        <div class="flex items-center gap-3">
            <span id="statusBadge" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"></span>
            <span id="ambienteLabel" class="hidden text-xs font-bold text-red-500"></span>
        </div>
        <div class="flex flex-wrap gap-2 mt-3 sm:mt-0" id="acoesContainer"></div>
    </div>

    <div id="processamentoBethaAviso" class="hidden mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
        <div class="flex gap-3">
            <i class="fas fa-hourglass-half text-amber-600 mt-0.5"></i>
            <div>
                <div class="text-sm font-semibold text-amber-800" id="processamentoBethaTitulo"></div>
                <div class="text-sm text-amber-700" id="processamentoBethaMensagem"></div>
            </div>
        </div>
    </div>

    <!-- Identificacao -->
    <div class="form-section mb-6">
        <h3 class="form-section-title">
            <i class="fas fa-file-invoice mr-2"></i><?= t('modules.nfse.sections.identification') ?>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-2 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.numero') ?></label>
                <span class="text-lg font-bold" id="infoNumero">-</span>
            </div>
            <div class="md:col-span-2 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.serie') ?></label>
                <span id="infoSerie">-</span>
            </div>
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.data_emissao') ?></label>
                <span id="infoDataEmissao">-</span>
            </div>
            <div class="md:col-span-2 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.data_competencia') ?></label>
                <span id="infoCompetencia">-</span>
            </div>
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.tipo_emissao') ?></label>
                <span id="infoTipoEmissao">-</span>
            </div>
            <div class="md:col-span-6 form-input-group" id="chaveAcessoGroup">
                <label class="form-label-group"><?= t('modules.nfse.fields.chave_acesso') ?></label>
                <span class="text-xs break-all" id="infoChaveAcesso">-</span>
            </div>
            <div class="md:col-span-6 form-input-group" id="codVerifGroup">
                <label class="form-label-group"><?= t('modules.nfse.fields.codigo_verificacao') ?></label>
                <span id="infoCodigoVerificacao">-</span>
            </div>
        </div>
    </div>

    <!-- Prestador -->
    <div class="form-section mb-6">
        <h3 class="form-section-title">
            <i class="fas fa-building mr-2"></i><?= t('modules.nfse.sections.prestador') ?>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-8 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.prestador') ?></label>
                <span id="infoPrestadorNome">-</span>
            </div>
            <div class="md:col-span-4 form-input-group">
                <label class="form-label-group">CNPJ</label>
                <span id="infoPrestadorCnpj">-</span>
            </div>
        </div>
    </div>

    <!-- Tomador -->
    <div class="form-section mb-6">
        <h3 class="form-section-title">
            <i class="fas fa-user mr-2"></i><?= t('modules.nfse.sections.tomador') ?>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-5 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.tomador_nome') ?></label>
                <span id="infoTomadorNome">-</span>
            </div>
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.tomador_cpf_cnpj') ?></label>
                <span id="infoTomadorCpfCnpj">-</span>
            </div>
            <div class="md:col-span-4 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.tomador_email') ?></label>
                <span id="infoTomadorEmail">-</span>
            </div>
        </div>
    </div>

    <!-- Servico -->
    <div class="form-section mb-6">
        <h3 class="form-section-title">
            <i class="fas fa-concierge-bell mr-2"></i><?= t('modules.nfse.sections.servico') ?>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.codigo_servico') ?></label>
                <span id="infoCodigoServico">-</span>
            </div>
            <div class="md:col-span-9 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.descricao_servico') ?></label>
                <span id="infoDescricaoServico">-</span>
            </div>
        </div>
    </div>

    <!-- Valores -->
    <div class="form-section mb-6">
        <h3 class="form-section-title">
            <i class="fas fa-calculator mr-2"></i><?= t('modules.nfse.sections.valores') ?>
        </h3>
        <div class="bg-white rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <tbody>
                    <tr class="border-b">
                        <td class="py-2 px-4 text-slate-600"><?= t('modules.nfse.fields.valor_servicos') ?></td>
                        <td class="py-2 px-4 text-right font-medium" id="infoValorServicos">R$ 0,00</td>
                    </tr>
                    <tr class="border-b" id="rowDeducoes">
                        <td class="py-2 px-4 text-slate-600">(-) <?= t('modules.nfse.fields.valor_deducoes') ?></td>
                        <td class="py-2 px-4 text-right font-medium" id="infoValorDeducoes">R$ 0,00</td>
                    </tr>
                    <tr class="border-b bg-slate-50">
                        <td class="py-2 px-4 font-bold text-slate-700"><?= t('modules.nfse.fields.base_calculo') ?></td>
                        <td class="py-2 px-4 text-right font-bold" id="infoBaseCalculo">R$ 0,00</td>
                    </tr>
                    <tr class="border-b" id="rowISS">
                        <td class="py-2 px-4 text-slate-600">ISS (<span id="infoAliquotaISS">0,00</span>%)</td>
                        <td class="py-2 px-4 text-right font-medium" id="infoValorISS">R$ 0,00</td>
                    </tr>
                    <tr class="border-b hidden" id="rowIBS">
                        <td class="py-2 px-4 text-slate-600">IBS (<span id="infoAliquotaIBS">0,00</span>%)</td>
                        <td class="py-2 px-4 text-right font-medium" id="infoValorIBS">R$ 0,00</td>
                    </tr>
                    <tr class="hidden" id="rowCBS">
                        <td class="py-2 px-4 text-slate-600">CBS (<span id="infoAliquotaCBS">0,00</span>%)</td>
                        <td class="py-2 px-4 text-right font-medium" id="infoValorCBS">R$ 0,00</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Cancelamento -->
    <div class="form-section mb-6 hidden" id="sectionCancelamento">
        <h3 class="form-section-title" style="border-left-color: #dc3545">
            <i class="fas fa-ban mr-2 text-red-500"></i><?= t('modules.nfse.sections.cancelamento') ?>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-3 form-input-group">
                <label class="form-label-group">Data</label>
                <span id="infoCancelData">-</span>
            </div>
            <div class="md:col-span-9 form-input-group">
                <label class="form-label-group"><?= t('modules.nfse.fields.motivo_cancelamento') ?></label>
                <span id="infoCancelMotivo">-</span>
            </div>
        </div>
    </div>

    <!-- Erro (rejeitada) -->
    <div class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-lg" id="sectionErro">
        <div class="text-sm font-medium text-red-800 mb-1">Erro na emissão:</div>
        <div class="text-sm text-red-600" id="infoErroMensagem"></div>
    </div>

    <!-- Eventos -->
    <div class="form-section mb-6">
        <h3 class="form-section-title">
            <i class="fas fa-history mr-2"></i><?= t('modules.nfse.sections.eventos') ?>
        </h3>
        <div id="eventosContainer" class="space-y-2">
            <div class="text-sm text-slate-400 py-4 text-center"><?= t('common.labels.loading') ?>...</div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    // Extrair ID da URL
    const pathParts = window.location.pathname.split('/');
    const nfseId = pathParts[pathParts.indexOf('nfse') + 1];

    if (!nfseId || isNaN(nfseId)) {
        voltarParaLista();
        return;
    }

    window.pageLoading.start();
    carregarNfse(nfseId);

    document.getElementById('btnVoltar').addEventListener('click', voltarParaLista);

    async function carregarNfse(id) {
        try {
            const [nfseResult, eventosResult] = await Promise.all([
                API.get(`/api/nfse/${id}`),
                API.get(`/api/nfse/${id}/eventos`)
            ]);

            if (!nfseResult.success || !nfseResult.data) {
                window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.load_error') ?>' }, '*');
                voltarParaLista();
                return;
            }

            preencherDados(nfseResult.data);
            preencherEventos(eventosResult.success ? eventosResult.data : []);
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.load_error') ?>' }, '*');
        } finally {
            window.pageLoading.done();
        }
    }

    function preencherDados(n) {
        // Status badge
        const statusMap = {
            pendente: { bg: 'bg-yellow-100', text: 'text-yellow-700', icon: 'fa-clock', label: '<?= t('modules.nfse.status.pendente') ?>' },
            processando: { bg: 'bg-blue-100', text: 'text-blue-700', icon: 'fa-spinner', label: '<?= t('modules.nfse.status.processando') ?>' },
            autorizada: { bg: 'bg-green-100', text: 'text-green-700', icon: 'fa-check-circle', label: '<?= t('modules.nfse.status.autorizada') ?>' },
            rejeitada: { bg: 'bg-red-100', text: 'text-red-700', icon: 'fa-times-circle', label: '<?= t('modules.nfse.status.rejeitada') ?>' },
            cancelada: { bg: 'bg-slate-200', text: 'text-slate-600', icon: 'fa-ban', label: '<?= t('modules.nfse.status.cancelada') ?>' },
        };
        const s = statusMap[n.status] || statusMap.pendente;
        if (n.status === 'processando' && n.mensagem_processamento && n.mensagem_processamento !== '<?= t('modules.nfse.status.processando') ?>') {
            s.label = n.mensagem_processamento;
            s.icon = n.processamento_demorado ? 'fa-hourglass-half' : 'fa-clock';
            s.bg = n.processamento_demorado ? 'bg-amber-100' : 'bg-sky-100';
            s.text = n.processamento_demorado ? 'text-amber-700' : 'text-sky-700';
        }
        document.getElementById('statusBadge').className = `inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${s.bg} ${s.text}`;
        document.getElementById('statusBadge').innerHTML = `<i class="fas ${s.icon} mr-1"></i>${escapeHtml(s.label)}`;

        const avisoProcessamento = document.getElementById('processamentoBethaAviso');
        if (n.processamento_alerta) {
            document.getElementById('processamentoBethaTitulo').textContent = n.mensagem_processamento || 'Aguardando validação Betha';
            document.getElementById('processamentoBethaMensagem').textContent = n.mensagem_processamento_detalhe || 'DPS Betha recepcionada e aguardando validação do ambiente nacional.';
            avisoProcessamento.classList.remove('hidden');
        } else {
            avisoProcessamento.classList.add('hidden');
        }

        // Ambiente
        if (parseInt(n.ambiente) !== 1) {
            const el = document.getElementById('ambienteLabel');
            el.textContent = '<?= t('modules.nfse.messages.homologacao_aviso') ?>';
            el.classList.remove('hidden');
        }

        // Acoes
        const acoes = document.getElementById('acoesContainer');
        let acoesHtml = '';

        if (n.status === 'autorizada' || n.status === 'cancelada') {
            acoesHtml += `<button type="button" data-id="${n.id}" data-numero="${escapeAttr(n.numero || n.id)}" class="btn-download-pdf-nfse btn-purple py-1 px-3 rounded text-xs"><i class="fas fa-file-pdf mr-1"></i><?= t('modules.nfse.buttons.download_pdf') ?></button>`;
        }
        if (n.status === 'autorizada') {
            acoesHtml += `<button onclick="enviarEmail(${n.id})" class="btn-green py-1 px-3 rounded text-xs"><i class="fas fa-envelope mr-1"></i><?= t('modules.nfse.buttons.send_email') ?></button>`;
            acoesHtml += `<button onclick="consultarStatus(${n.id})" class="btn-secondary py-1 px-3 rounded text-xs"><i class="fas fa-sync mr-1"></i><?= t('modules.nfse.buttons.consult') ?></button>`;
            acoesHtml += `<button onclick="navegarPara('/pages/nfse/${n.id}/cancelar')" class="btn-red py-1 px-3 rounded text-xs"><i class="fas fa-ban mr-1"></i><?= t('modules.nfse.buttons.cancel_nfse') ?></button>`;
        }
        if (n.status === 'processando') {
            acoesHtml += `<button onclick="consultarStatus(${n.id})" class="btn-secondary py-1 px-3 rounded text-xs"><i class="fas fa-sync mr-1"></i><?= t('modules.nfse.buttons.consult') ?></button>`;
        }
        if (n.status === 'rejeitada') {
            acoesHtml += `<button onclick="reenviarNfse(${n.id})" class="btn-blue py-1 px-3 rounded text-xs"><i class="fas fa-redo mr-1"></i><?= t('modules.nfse.buttons.resend') ?></button>`;
        }
        acoes.innerHTML = acoesHtml;

        // Identificacao
        document.getElementById('infoNumero').textContent = n.numero || '-';
        document.getElementById('infoSerie').textContent = n.serie || '-';
        const dataBase = n.data_emissao || n.created_at || '';
        document.getElementById('infoDataEmissao').textContent = dataBase ? DateHelper.format(dataBase.substring(0, 10)) : '-';
        document.getElementById('infoCompetencia').textContent = n.data_competencia ? DateHelper.format(n.data_competencia) : '-';
        const tiposEmissao = {
            nacional: '<?= t('modules.nfse.tipo_emissao.nacional') ?>',
            betha: '<?= t('modules.nfse.tipo_emissao.betha') ?>',
            issnet: '<?= t('modules.nfse.tipo_emissao.issnet') ?>'
        };
        document.getElementById('infoTipoEmissao').textContent = tiposEmissao[n.tipo_emissao] || n.tipo_emissao || '-';

        if (n.chave_acesso) {
            document.getElementById('infoChaveAcesso').textContent = n.chave_acesso;
        } else {
            document.getElementById('chaveAcessoGroup').classList.add('hidden');
        }
        if (n.codigo_verificacao) {
            document.getElementById('infoCodigoVerificacao').textContent = n.codigo_verificacao;
        } else {
            document.getElementById('codVerifGroup').classList.add('hidden');
        }

        // Prestador / Tomador
        document.getElementById('infoPrestadorNome').textContent = n.prestador_razao_social || '-';
        document.getElementById('infoPrestadorCnpj').textContent = n.prestador_cnpj || '-';
        document.getElementById('infoTomadorNome').textContent = n.tomador_nome || '-';
        document.getElementById('infoTomadorCpfCnpj').textContent = n.tomador_cpf_cnpj || '-';
        document.getElementById('infoTomadorEmail').textContent = n.tomador_email || '-';

        // Servico
        document.getElementById('infoCodigoServico').textContent = n.codigo_servico || '-';
        document.getElementById('infoDescricaoServico').textContent = n.descricao_servico || '-';

        // Valores
        document.getElementById('infoValorServicos').textContent = Currency.format(parseFloat(n.valor_servicos || 0), true);
        document.getElementById('infoValorDeducoes').textContent = Currency.format(parseFloat(n.valor_deducoes || 0), true);
        document.getElementById('infoBaseCalculo').textContent = Currency.format(parseFloat(n.base_calculo || 0), true);
        document.getElementById('infoAliquotaISS').textContent = parseFloat(n.aliquota_iss || 0).toFixed(2).replace('.', ',');
        document.getElementById('infoValorISS').textContent = Currency.format(parseFloat(n.valor_iss || 0), true);
        const aliquotaIBS = parseFloat(n.aliquota_ibs || 0);
        const valorIBS = parseFloat(n.valor_ibs || 0);
        const aliquotaCBS = parseFloat(n.aliquota_cbs || 0);
        const valorCBS = parseFloat(n.valor_cbs || 0);
        document.getElementById('infoAliquotaIBS').textContent = aliquotaIBS.toFixed(2).replace('.', ',');
        document.getElementById('infoValorIBS').textContent = Currency.format(valorIBS, true);
        document.getElementById('infoAliquotaCBS').textContent = aliquotaCBS.toFixed(2).replace('.', ',');
        document.getElementById('infoValorCBS').textContent = Currency.format(valorCBS, true);
        document.getElementById('rowIBS').classList.toggle('hidden', aliquotaIBS <= 0 && valorIBS <= 0);
        document.getElementById('rowCBS').classList.toggle('hidden', aliquotaCBS <= 0 && valorCBS <= 0);

        if (parseFloat(n.valor_deducoes || 0) === 0) {
            document.getElementById('rowDeducoes').classList.add('hidden');
        }
        if (parseFloat(n.valor_iss || 0) === 0 && parseFloat(n.aliquota_iss || 0) === 0) {
            document.getElementById('rowISS').classList.add('hidden');
        }

        // Cancelamento
        if (n.status === 'cancelada') {
            document.getElementById('sectionCancelamento').classList.remove('hidden');
            document.getElementById('infoCancelData').textContent = n.data_cancelamento || '-';
            document.getElementById('infoCancelMotivo').textContent = n.motivo_cancelamento || '-';
        }

        // Erro
        if (n.status === 'rejeitada' && n.motivo_rejeicao) {
            document.getElementById('sectionErro').classList.remove('hidden');
            document.getElementById('infoErroMensagem').textContent = n.motivo_rejeicao;
        }
    }

    function preencherEventos(eventos) {
        const container = document.getElementById('eventosContainer');

        if (!eventos || eventos.length === 0) {
            container.innerHTML = `<div class="text-sm text-slate-400 py-4 text-center"><?= t('modules.nfse.messages.no_events') ?></div>`;
            return;
        }

        const iconMap = {
            emissao: 'fa-paper-plane text-green-500',
            cancelamento: 'fa-ban text-red-500',
            consulta: 'fa-search text-blue-500',
            email: 'fa-envelope text-purple-500',
            erro: 'fa-exclamation-circle text-red-500',
            reenvio: 'fa-redo text-orange-500',
        };

        let html = '';
        eventos.forEach(ev => {
            const icon = iconMap[ev.tipo_evento] || 'fa-info-circle text-slate-400';
            const data = ev.created_at ? ev.created_at.substring(0, 16).replace('T', ' ') : '';
            html += `<div class="flex items-start gap-3 p-3 bg-white rounded border border-slate-100">
                <i class="fas ${icon} mt-0.5"></i>
                <div class="flex-1">
                    <div class="text-sm text-slate-700">${escapeHtml(ev.mensagem || '')}</div>
                    ${ev.codigo_retorno ? `<div class="text-xs text-slate-400 mt-0.5">Código: ${escapeHtml(ev.codigo_retorno)}</div>` : ''}
                </div>
                <div class="text-xs text-slate-400 whitespace-nowrap">${data}</div>
            </div>`;
        });
        container.innerHTML = html;
    }

    // Funcoes globais
    window.navegarPara = function(page) {
        window.parent.postMessage({ action: 'navigate', page: page }, '*');
    };

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-download-pdf-nfse');
        if (!btn) return;

        e.preventDefault();
        baixarPdfNfse(btn.dataset.id, btn.dataset.numero);
    });

    window.enviarEmail = async function(id) {
        try {
            const result = await API.post(`/nfse/${id}/email`, {});
            const msg = result.success ? '<?= t('modules.nfse.messages.email_success') ?>' : (result.message || <?= js_t('modules.nfse.messages.email_error') ?>);
            window.parent.postMessage({ action: 'openAlert', message: msg }, '*');
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: <?= js_t('modules.nfse.messages.email_error') ?> }, '*');
        }
    };

    window.consultarStatus = async function(id) {
        try {
            const result = await API.post(`/nfse/${id}/consultar`, {});
            const msg = result.success ? '<?= t('modules.nfse.messages.consult_success') ?>' : (result.message || '<?= t('modules.nfse.messages.connection_error') ?>');
            window.parent.postMessage({ action: 'openAlert', message: msg }, '*');
            if (result.success) { location.reload(); }
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.connection_error') ?>' }, '*');
        }
    };

    window.reenviarNfse = async function(id) {
        try {
            const result = await API.post(`/nfse/${id}/reenviar`, {});
            const msg = result.success ? '<?= t('modules.nfse.messages.resend_success') ?>' : formatarErroNfse(result, '<?= t('modules.nfse.messages.resend_error') ?>');
            window.parent.postMessage({ action: 'openAlert', message: msg }, '*');
            if (result.success) { location.reload(); }
        } catch (e) {
            if (await confirmarReenvioAutorizado(id)) {
                window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.resend_success') ?>' }, '*');
                location.reload();
                return;
            }
            window.parent.postMessage({ action: 'openAlert', message: '<?= t('modules.nfse.messages.resend_error') ?>' }, '*');
        }
    };

    async function confirmarReenvioAutorizado(id) {
        try {
            const result = await API.get(`/api/nfse/${id}`);
            return result?.success && result?.data?.status === 'autorizada';
        } catch (e) {
            return false;
        }
    }

    function formatarErroNfse(result, fallback) {
        const errosApi = Array.isArray(result?.erros_api) ? result.erros_api : [];
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

    window.baixarPdfNfse = async function(id, numero) {
        try {
            const response = await fetch(`/nfse/${id}/pdf`, {
                method: 'GET',
                headers: API.getHeaders()
            });

            if (!response.ok) {
                throw new Error(await extrairMensagemErroDownload(response, '<?= t('modules.nfse.messages.load_error') ?>'));
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `nfse_${String(numero || id).replace(/[^A-Za-z0-9_.-]/g, '_')}.pdf`;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => window.URL.revokeObjectURL(url), 1000);
        } catch (e) {
            window.parent.postMessage({ action: 'openAlert', message: e.message || '<?= t('modules.nfse.messages.load_error') ?>' }, '*');
        }
    };

    async function extrairMensagemErroDownload(response, fallback) {
        try {
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const result = await response.json();
                return result.message || fallback;
            }

            const text = await response.text();
            return text || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function voltarParaLista() {
        window.parent.postMessage({ action: 'navigate', page: '/pages/nfse' }, '*');
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function escapeAttr(text) {
        return escapeHtml(text).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
})();
</script>
@endsection
