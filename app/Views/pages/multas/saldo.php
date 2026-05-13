@extends('layouts.iframe')

@section('title', t('modules.multas.saldo.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.multas.saldo.title') ?></h2>
    </div>

    <!-- Cards de Saldo e Resumo -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Saldo Atual -->
        <div class="bg-white shadow-md rounded-lg p-4 border-l-4 border-blue-500">
            <div class="text-sm text-slate-500 font-medium"><?= t('modules.multas.saldo.cards.current_balance') ?></div>
            <div class="text-2xl font-bold text-blue-600 mt-1" id="saldoAtual">
                <i class="fas fa-spinner fa-spin text-sm"></i>
            </div>
            <div class="flex gap-2 mt-3">
                <button id="btnRecarregarPix" class="btn-blue py-1.5 px-3 rounded text-xs font-medium flex items-center">
                    <i class="fas fa-qrcode mr-1"></i> <?= t('modules.multas.saldo.buttons.pix') ?>
                </button>
                <button id="btnRecarregarCartao" class="bg-purple-600 hover:bg-purple-700 text-white py-1.5 px-3 rounded text-xs font-medium flex items-center">
                    <i class="fas fa-credit-card mr-1"></i> <?= t('modules.multas.saldo.buttons.card') ?>
                </button>
            </div>
        </div>

        <!-- Total Gasto -->
        <div class="bg-white shadow-md rounded-lg p-4 border-l-4 border-red-400">
            <div class="text-sm text-slate-500 font-medium"><?= t('modules.multas.saldo.cards.total_spent') ?></div>
            <div class="text-xl font-bold text-red-500 mt-1" id="totalGasto">-</div>
            <div class="text-xs text-slate-400 mt-1" id="totalConsultasEventos">-</div>
        </div>

        <!-- Total Recarregado -->
        <div class="bg-white shadow-md rounded-lg p-4 border-l-4 border-green-400">
            <div class="text-sm text-slate-500 font-medium"><?= t('modules.multas.saldo.cards.total_recharged') ?></div>
            <div class="text-xl font-bold text-green-500 mt-1" id="totalRecarregado">-</div>
        </div>

        <!-- Precos -->
        <div class="bg-white shadow-md rounded-lg p-4 border-l-4 border-amber-400">
            <div class="text-sm text-slate-500 font-medium"><?= t('modules.multas.saldo.cards.prices_title') ?></div>
            <div class="mt-1 space-y-1">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600"><?= t('modules.multas.saldo.cards.query') ?></span>
                    <span class="font-medium" id="precoConsulta">-</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-600"><?= t('modules.multas.saldo.cards.event') ?></span>
                    <span class="font-medium" id="precoEvento">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-recarga -->
    <div class="bg-white shadow-md rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-700">
                <i class="fas fa-sync-alt mr-1 text-blue-500"></i> <?= t('modules.multas.saldo.auto_recharge.title') ?>
            </h3>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="toggleAutoRecarga" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
        </div>
        <div id="autoRecargaConfig" class="hidden">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-slate-500 mb-1"><?= t('modules.multas.saldo.auto_recharge.threshold_label') ?></label>
                    <input type="number" id="autoRecargaLimite" class="form-input-focus w-full text-sm" value="10.00" min="1" step="0.01">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1"><?= t('modules.multas.saldo.auto_recharge.value_label') ?></label>
                    <input type="number" id="autoRecargaValor" class="form-input-focus w-full text-sm" value="100.00" min="100" step="10">
                </div>
                <div class="flex items-end">
                    <button id="btnSalvarAutoRecarga" class="btn-blue py-2 px-4 rounded text-sm font-medium w-full">
                        <i class="fas fa-save mr-1"></i> <?= t('modules.multas.saldo.buttons.save') ?>
                    </button>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-2">
                <i class="fas fa-info-circle mr-1"></i>
                <?= t('modules.multas.saldo.auto_recharge.requires_card') ?>
            </p>
            <div id="cartaoSalvoInfo" class="mt-2 hidden">
                <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-green-50 text-green-700">
                    <i class="fas fa-check-circle mr-1"></i> <?= t('modules.multas.saldo.auto_recharge.card_saved') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Historico de Transacoes -->
    <h3 class="text-sm font-semibold text-slate-700 mb-3">
        <i class="fas fa-history mr-1 text-slate-500"></i> <?= t('modules.multas.saldo.history_title') ?>
    </h3>

    <!-- Filtros -->
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <select id="filtroTipo" class="form-input-focus text-sm w-44">
            <option value=""><?= t('modules.multas.saldo.filters.type_all') ?></option>
            <option value="consulta"><?= t('modules.multas.saldo.filters.type_queries') ?></option>
            <option value="evento"><?= t('modules.multas.saldo.filters.type_events') ?></option>
            <option value="recarga_pix"><?= t('modules.multas.saldo.filters.type_pix') ?></option>
            <option value="recarga_cartao"><?= t('modules.multas.saldo.filters.type_card') ?></option>
        </select>
        <input type="date" id="filtroDataInicio" class="form-input-focus text-sm w-40" title="Data inicio">
        <span class="text-xs text-slate-400"><?= t('modules.multas.saldo.filters.until') ?></span>
        <input type="date" id="filtroDataFim" class="form-input-focus text-sm w-40" title="Data fim">
    </div>

    <!-- Tabela -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.multas.saldo.table.date') ?></th>
                    <th class="table-header"><?= t('modules.multas.saldo.table.type') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.multas.saldo.table.description') ?></th>
                    <th class="table-header text-right"><?= t('modules.multas.saldo.table.value') ?></th>
                    <th class="table-header text-right hidden md:table-cell"><?= t('modules.multas.saldo.table.balance') ?></th>
                    <th class="table-header text-center"><?= t('modules.multas.saldo.table.status') ?></th>
                </tr>
            </thead>
            <tbody id="transacoesTableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <!-- Paginacao -->
    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label class="text-sm text-slate-600 mr-2"><?= t('modules.multas.saldo.pagination.rows') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="15" selected>15</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600"><span id="registrosInfo"></span></div>
        <nav><ul class="inline-flex items-center -space-x-px" id="paginationContainer"></ul></nav>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        loading: '<?= t('common.labels.loading') ?>',
        noTransactions: '<?= t('modules.multas.saldo.messages.no_transactions') ?>',
        paginationShowing: '<?= t('modules.multas.saldo.pagination.showing') ?>',
        badgeQuery: '<?= t('modules.multas.saldo.badges.query') ?>',
        badgeEvent: '<?= t('modules.multas.saldo.badges.event') ?>',
        badgePix: '<?= t('modules.multas.saldo.badges.pix') ?>',
        badgeCard: '<?= t('modules.multas.saldo.badges.card') ?>',
        badgeConfirmed: '<?= t('modules.multas.saldo.badges.confirmed') ?>',
        badgePending: '<?= t('modules.multas.saldo.badges.pending') ?>',
        badgeFailed: '<?= t('modules.multas.saldo.badges.failed') ?>',
        loadError: '<?= t('modules.multas.messages.load_error') ?>',
        serverError: '<?= t('modules.multas.messages.server_error') ?>',
        autoRechargeUpdated: '<?= t('modules.multas.saldo.messages.auto_recharge_updated') ?>',
        saveError: '<?= t('modules.multas.saldo.messages.save_error') ?>',
    };

    let currentPage = 1;
    let perPage = 15;
    let filtroTipo = '';
    let dataInicio = '';
    let dataFim = '';
    let saldoData = null;

    const tbody = document.getElementById('transacoesTableBody');

    // =================================================================
    // CARREGAR SALDO E RESUMO
    // =================================================================

    async function carregarSaldo() {
        try {
            const result = await API.get('/api/multas-online/saldo');
            if (!result.success) return;

            saldoData = result.data;

            // Saldo atual
            document.getElementById('saldoAtual').textContent = Currency.format(saldoData.saldo);

            // Resumo
            document.getElementById('totalGasto').textContent = Currency.format(saldoData.resumo.total_gasto);
            document.getElementById('totalConsultasEventos').textContent =
                saldoData.resumo.total_consultas + ' consultas, ' + saldoData.resumo.total_eventos + ' eventos';
            document.getElementById('totalRecarregado').textContent = Currency.format(saldoData.resumo.total_recarregado);

            // Precos
            document.getElementById('precoConsulta').textContent = Currency.format(saldoData.precos.consulta);
            document.getElementById('precoEvento').textContent = Currency.format(saldoData.precos.evento);
            // Auto-recarga
            const toggle = document.getElementById('toggleAutoRecarga');
            toggle.checked = saldoData.auto_recarga_ativo === 1;
            document.getElementById('autoRecargaConfig').classList.toggle('hidden', !toggle.checked);
            document.getElementById('autoRecargaValor').value = saldoData.auto_recarga_valor;
            document.getElementById('autoRecargaLimite').value = saldoData.auto_recarga_limite;

            if (saldoData.stripe_payment_method_id) {
                document.getElementById('cartaoSalvoInfo').classList.remove('hidden');
            }

        } catch (error) {
            console.error('Erro ao carregar saldo:', error);
        }
    }

    // =================================================================
    // TRANSACOES
    // =================================================================

    async function carregarTransacoes(page = 1, recordsPerPage = 15) {
        try {
            mostrarLoading();

            const params = { page, perPage: recordsPerPage };
            if (filtroTipo) params.tipo = filtroTipo;
            if (dataInicio) params.data_inicio = dataInicio;
            if (dataFim) params.data_fim = dataFim;

            const result = await API.get('/api/multas-online/transacoes', params);

            if (result.success) {
                renderTransacoes(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarErro(result.message || i18n.loadError);
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarErro(error.message || i18n.serverError);
        }
    }

    function mostrarLoading() {
        tbody.innerHTML = '<tr><td colspan="6" class="table-cell text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.loading + '</td></tr>';
    }

    function mostrarErro(msg) {
        tbody.innerHTML = '<tr><td colspan="6" class="table-cell text-center text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>' + escapeHtml(msg) + '</td></tr>';
    }

    function getTipoBadge(tipo) {
        const badges = {
            'consulta': '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"><i class="fas fa-search mr-1"></i>' + i18n.badgeQuery + '</span>',
            'evento': '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"><i class="fas fa-bell mr-1"></i>' + i18n.badgeEvent + '</span>',
            'recarga_pix': '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-qrcode mr-1"></i>' + i18n.badgePix + '</span>',
            'recarga_cartao': '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700"><i class="fas fa-credit-card mr-1"></i>' + i18n.badgeCard + '</span>',
        };
        return badges[tipo] || '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">' + escapeHtml(tipo) + '</span>';
    }

    function getStatusBadge(status) {
        if (status === 'confirmado') return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>' + i18n.badgeConfirmed + '</span>';
        if (status === 'pendente') return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"><i class="fas fa-clock mr-1"></i>' + i18n.badgePending + '</span>';
        if (status === 'falha') return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"><i class="fas fa-times mr-1"></i>' + i18n.badgeFailed + '</span>';
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">' + escapeHtml(status) + '</span>';
    }

    function isRecarga(tipo) {
        return tipo && tipo.startsWith('recarga_');
    }

    function formatarData(dt) {
        if (!dt) return '';
        const d = new Date(dt);
        if (isNaN(d.getTime())) return dt;
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    function ocultarNomeProvedor(texto) {
        if (!texto) return '';
        const provedor = ['S', 'ERPRO'].join('');
        const servico = ['e', 'Frotas'].join('');
        return String(texto)
            .replace(new RegExp(provedor + ' ' + servico, 'gi'), 'consultas online')
            .replace(new RegExp(servico, 'gi'), 'consultas online')
            .replace(new RegExp(provedor, 'gi'), 'consulta online');
    }

    function renderTransacoes(dados) {
        if (!dados || dados.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="table-cell text-center text-slate-500"><i class="fas fa-inbox mr-2"></i>' + i18n.noTransactions + '</td></tr>';
            return;
        }

        let rows = '';
        dados.forEach(item => {
            const recarga = isRecarga(item.tipo);
            const valorClass = recarga ? 'text-green-600' : 'text-red-500';
            const valorPrefix = recarga ? '+' : '-';
            const valorFormatado = Currency.format(item.valor_total || 0);

            rows += `
            <tr class="border-b border-slate-200 hover:bg-slate-50">
                <td class="table-cell text-sm">${formatarData(item.created_at)}</td>
                <td class="table-cell">${getTipoBadge(item.tipo)}</td>
                <td class="table-cell hidden sm:table-cell text-sm text-slate-600">${escapeHtml(ocultarNomeProvedor(item.descricao))}${item.referencia ? '<div class="text-xs text-slate-400 font-mono">' + escapeHtml(ocultarNomeProvedor(item.referencia)) + '</div>' : ''}</td>
                <td class="table-cell text-right font-medium ${valorClass}">${valorPrefix}${valorFormatado}</td>
                <td class="table-cell text-right hidden md:table-cell text-sm text-slate-500">${item.saldo_posterior != null ? Currency.format(item.saldo_posterior) : '-'}</td>
                <td class="table-cell text-center">${getStatusBadge(item.status)}</td>
            </tr>`;
        });

        tbody.innerHTML = rows;
    }

    function atualizarInfoRegistros(pagination) {
        const el = document.getElementById('registrosInfo');
        if (!el || !pagination) return;
        const { page, perPage, total } = pagination;
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);
        el.textContent = i18n.paginationShowing.replace(':start', start).replace(':end', end).replace(':total', total);
    }

    function atualizarPaginacao(pagination) {
        const container = document.getElementById('paginationContainer');
        if (!container || !pagination) return;

        const { page, totalPages, hasPrev, hasNext } = pagination;
        let html = '';
        html += `<li><button class="pagination-button arrow-button rounded-l-md ${!hasPrev ? 'opacity-50 cursor-not-allowed' : ''}" ${!hasPrev ? 'disabled' : ''} onclick="irParaPagina(${page - 1})"><i class="fas fa-chevron-left"></i></button></li>`;

        const maxBtns = 5;
        let startP = Math.max(1, page - Math.floor(maxBtns / 2));
        let endP = Math.min(totalPages || 1, startP + maxBtns - 1);
        if (endP - startP < maxBtns - 1) startP = Math.max(1, endP - maxBtns + 1);

        for (let i = startP; i <= endP; i++) {
            html += `<li><button class="pagination-button numbered ${i === page ? 'active' : ''}" onclick="irParaPagina(${i})">${i}</button></li>`;
        }

        html += `<li><button class="pagination-button arrow-button rounded-r-md ${!hasNext ? 'opacity-50 cursor-not-allowed' : ''}" ${!hasNext ? 'disabled' : ''} onclick="irParaPagina(${page + 1})"><i class="fas fa-chevron-right"></i></button></li>`;
        container.innerHTML = html;
    }

    window.irParaPagina = function(page) {
        currentPage = page;
        carregarTransacoes(currentPage, perPage);
    };

    // =================================================================
    // MODAIS PIX E CARTAO (abrem no parent via postMessage)
    // =================================================================

    function abrirModalPix() {
        window.parent.postMessage({
            action: 'openPixModal',
            recargaMinima: saldoData?.recarga_minima || 100
        }, '*');
    }

    function abrirModalCartao() {
        window.parent.postMessage({
            action: 'openCartaoModal',
            recargaMinima: saldoData?.recarga_minima || 100,
            stripePublicKey: '<?= env("STRIPE_PUBLIC_KEY", "") ?>'
        }, '*');
    }

    // =================================================================
    // AUTO-RECARGA
    // =================================================================

    async function salvarAutoRecarga() {
        try {
            const result = await API.post('/multas-online/saldo/auto-recarga', {
                auto_recarga_ativo: document.getElementById('toggleAutoRecarga').checked ? 1 : 0,
                auto_recarga_valor: parseFloat(document.getElementById('autoRecargaValor').value),
                auto_recarga_limite: parseFloat(document.getElementById('autoRecargaLimite').value),
            });

            if (result.success) {
                window.parent.postMessage({ action: 'showToast', type: 'success', message: i18n.autoRechargeUpdated }, '*');
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.saveError }, '*');
            }
        } catch (error) {
            window.parent.postMessage({ action: 'openAlert', message: error.message || i18n.saveError }, '*');
        }
    }

    // =================================================================
    // EVENT LISTENERS
    // =================================================================

    // Modais (abrem no parent via postMessage)
    document.getElementById('btnRecarregarPix')?.addEventListener('click', abrirModalPix);
    document.getElementById('btnRecarregarCartao')?.addEventListener('click', abrirModalCartao);
    document.getElementById('btnSalvarAutoRecarga')?.addEventListener('click', salvarAutoRecarga);

    document.getElementById('toggleAutoRecarga')?.addEventListener('change', function() {
        document.getElementById('autoRecargaConfig').classList.toggle('hidden', !this.checked);
    });

    // Escutar resultados dos modais no parent
    window.addEventListener('message', function(event) {
        if (event.data?.action === 'pixRecargaResult' && event.data.success) {
            carregarSaldo();
            carregarTransacoes(1, perPage);
        }
        if (event.data?.action === 'cartaoRecargaResult' && event.data.success) {
            carregarSaldo();
            carregarTransacoes(1, perPage);
        }
    });

    // Filtros
    document.getElementById('filtroTipo')?.addEventListener('change', function() {
        filtroTipo = this.value;
        currentPage = 1;
        carregarTransacoes(currentPage, perPage);
    });

    document.getElementById('filtroDataInicio')?.addEventListener('change', function() {
        dataInicio = this.value;
        currentPage = 1;
        carregarTransacoes(currentPage, perPage);
    });

    document.getElementById('filtroDataFim')?.addEventListener('change', function() {
        dataFim = this.value;
        currentPage = 1;
        carregarTransacoes(currentPage, perPage);
    });

    document.getElementById('rowsPerPage')?.addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        carregarTransacoes(currentPage, perPage);
    });

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Init
    carregarSaldo();
    carregarTransacoes(currentPage, perPage);
})();
</script>
@endsection
