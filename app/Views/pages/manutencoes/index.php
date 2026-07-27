@extends('layouts.iframe')

@section('title', t('modules.manutencao.title_list'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.manutencao.title_list') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.manutencao.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovaManutencao" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('modules.manutencao.actions.new') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto mt-4">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header w-36"><?= t('modules.manutencao.table.os') ?></th>
                    <th class="table-header"><?= t('modules.manutencao.table.vehicle') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.manutencao.table.workshop') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.manutencao.table.creation_date') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.manutencao.table.send_date') ?></th>
                    <th class="table-header hidden lg:table-cell text-right"><?= t('modules.manutencao.table.total') ?></th>
                    <th class="table-header w-28 text-center"><?= t('modules.manutencao.table.status') ?></th>
                    <th class="table-header px-2 w-40 text-center"><?= t('modules.manutencao.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="manutencoesTableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serao inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.manutencao.pagination.rows_per_page') ?></label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo"></span>
        </div>
        <nav aria-label="<?= htmlspecialchars(t('modules.manutencao.pagination.page_navigation'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 sm:mt-0">
            <ul class="inline-flex items-center -space-x-px" id="paginationContainer">
                <li><button class="pagination-button arrow-button rounded-l-md" disabled><i class="fas fa-chevron-left"></i></button></li>
                <li><button class="pagination-button numbered active">1</button></li>
                <li><button class="pagination-button arrow-button rounded-r-md" disabled><i class="fas fa-chevron-right"></i></button></li>
            </ul>
        </nav>
    </div>
</div>
@endsection

@section('scripts')
<?php
$jsText = static fn(string $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<script>
(function () {
    const i18n = <?= json_encode([
        'loadError' => t('modules.manutencao.messages.load_error'),
        'serverError' => t('modules.manutencao.messages.server_error'),
        'noRecords' => t('modules.manutencao.messages.no_records'),
        'deleteError' => t('modules.manutencao.messages.delete_error'),
        'statusCreated' => t('modules.manutencao.status_options.created'),
        'statusCreatedBySystem' => t('modules.manutencao.status_options.created_by_system'),
        'statusOpen' => t('modules.manutencao.status_options.open'),
        'statusClosed' => t('modules.manutencao.status_options.closed'),
        'actionPrint' => t('modules.manutencao.print.action'),
        'actionEdit' => t('common.buttons.edit'),
        'actionDelete' => t('common.buttons.delete'),
        'deleteFinancialLinked' => t('modules.manutencao.delete_options.financial_linked'),
        'restoreUsedStock' => t('modules.manutencao.delete_options.restore_stock'),
        'recordType' => t('modules.manutencao.record_type'),
        'showingPagination' => t('modules.manutencao.pagination.showing'),
        'printTitle' => t('modules.manutencao.print.title'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    // Estado
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;
    let manutencoesMap = new Map();

    // Elementos
    const tbody = document.getElementById('manutencoesTableBody');

    // ===== NAVEGACAO =====

    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: page }, '*');
        } else {
            window.location.href = page;
        }
    }

    // ===== CARREGAMENTO DE DADOS =====

    async function carregarManutencoes(page = 1, recordsPerPage = 10, search = '') {
        try {
            mostrarLoading();

            const params = {
                page: page,
                perPage: recordsPerPage,
                search: search
            };

            const result = await API.get('/api/manutencoes', params);

            if (result.success) {
                renderManutencoes(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarMensagemErro(i18n.loadError + ': ' + (result.message || ''));
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarMensagemErro(error.message || i18n.serverError);
        }
    }

    function renderManutencoes(manutencoes) {
        if (!manutencoes || manutencoes.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-8 text-slate-500">
                        <i class="fas fa-wrench text-4xl mb-3 opacity-30"></i>
                        <p>${i18n.noRecords}</p>
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        manutencoesMap = new Map();
        manutencoes.forEach(m => {
            manutencoesMap.set(Number(m.id), m);
            const veiculo = m.veiculo_placa ? `${m.veiculo_placa} - ${m.veiculo_marca} ${m.veiculo_modelo}` : '-';
            const oficina = m.oficina_nome || '-';

            // Status badge
            let statusBadge = '';
            switch (m.status) {
                case 'C':
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700" title="${i18n.statusCreatedBySystem}">${i18n.statusCreated}</span>`;
                    break;
                case 'A':
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">${i18n.statusOpen}</span>`;
                    break;
                case 'F':
                    statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${i18n.statusClosed}</span>`;
                    break;
            }

            // Botoes de acao
            let botoes = `<button onclick="imprimirManutencao(${m.id})" class="btn-icon text-blue-600 hover:text-blue-800" title="${i18n.actionPrint}"><i class="fas fa-print"></i></button>`;
            botoes += ` <button onclick="editarManutencao(${m.id})" class="btn-icon text-amber-600 hover:text-amber-800" title="${i18n.actionEdit}"><i class="fas fa-edit"></i></button>`;
            botoes += ` <button onclick="deletarManutencao(${m.id})" class="btn-icon text-red-600 hover:text-red-800" title="${i18n.actionDelete}"><i class="fas fa-trash"></i></button>`;

            html += `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="table-cell font-mono text-sm">${m.os}</td>
                    <td class="table-cell">${veiculo}</td>
                    <td class="table-cell hidden sm:table-cell">${oficina}</td>
                    <td class="table-cell hidden md:table-cell text-center">${m.created_at_formatted || '-'}</td>
                    <td class="table-cell hidden md:table-cell text-center">${m.data_enviado_formatted || '-'}</td>
                    <td class="table-cell hidden lg:table-cell text-right font-medium">${Currency.format(m.total_servicos, true)}</td>
                    <td class="table-cell text-center">${statusBadge}</td>
                    <td class="table-cell text-right">${botoes}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // ===== ACOES =====

    window.imprimirManutencao = function(id) {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openPrintModal',
                url: '/manutencoes/' + id + '/imprimir',
                title: i18n.printTitle
            }, '*');
        } else {
            window.open('/manutencoes/' + id + '/imprimir', '_blank');
        }
    };

    window.editarManutencao = function(id) {
        navegarPara('/pages/manutencoes/adicionar?id=' + id);
    };

    window.deletarManutencao = function(id) {
        const manutencao = manutencoesMap.get(Number(id)) || {};
        const options = [];

        if (manutencao.tem_financeiro_vinculado) {
            options.push({
                key: 'excluir_financeiro',
                label: i18n.deleteFinancialLinked,
                checked: true
            });
        }

        if (manutencao.tem_estoque_utilizado) {
            options.push({
                key: 'repor_estoque',
                label: i18n.restoreUsedStock,
                checked: true
            });
        }

        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openDeleteModal',
                recordId: id,
                recordName: manutencao.os || id,
                recordType: i18n.recordType,
                confirmType: 'text',
                options: options
            }, '*');
        }
    };

    async function excluirManutencao(id, deleteOptions = {}) {
        try {
            const result = await API.post('/manutencoes/' + id + '/excluir', {
                excluir_financeiro: deleteOptions.excluir_financeiro !== false,
                repor_estoque: deleteOptions.repor_estoque !== false
            });
            if (result.success) {
                carregarManutencoes(currentPage, perPage, searchTerm);
            } else {
                toast.error(result.message || i18n.deleteError);
            }
        } catch (error) {
            toast.error(error.message || i18n.serverError);
        }
    }

    // Listener para confirmacoes do parent
    window.addEventListener('message', function(event) {
        if (!event.data || !event.data.action) return;

        if (event.data.action === 'confirmDelete') {
            excluirManutencao(event.data.recordId, event.data.deleteOptions || {});
        }
    });

    // ===== PAGINACAO E HELPERS =====

    function mostrarLoading() {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-slate-400"></i>
                </td>
            </tr>
        `;
    }

    function mostrarMensagemErro(msg) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-8 text-red-500">
                    <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                    <p>${msg}</p>
                </td>
            </tr>
        `;
    }

    function atualizarPaginacao(pagination) {
        const container = document.getElementById('paginationContainer');
        if (!container || !pagination) return;

        let html = '';

        // Botao anterior
        html += `<li><button class="pagination-button arrow-button rounded-l-md" ${pagination.page <= 1 ? 'disabled' : ''} onclick="irParaPagina(${pagination.page - 1})">
            <i class="fas fa-chevron-left"></i>
        </button></li>`;

        // Paginas
        const maxPages = 5;
        let startPage = Math.max(1, pagination.page - Math.floor(maxPages / 2));
        let endPage = Math.min(pagination.totalPages, startPage + maxPages - 1);

        if (endPage - startPage < maxPages - 1) {
            startPage = Math.max(1, endPage - maxPages + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<li><button class="pagination-button numbered ${i === pagination.page ? 'active' : ''}" onclick="irParaPagina(${i})">${i}</button></li>`;
        }

        // Botao proximo
        html += `<li><button class="pagination-button arrow-button rounded-r-md" ${pagination.page >= pagination.totalPages ? 'disabled' : ''} onclick="irParaPagina(${pagination.page + 1})">
            <i class="fas fa-chevron-right"></i>
        </button></li>`;

        container.innerHTML = html;
    }

    window.irParaPagina = function(page) {
        currentPage = page;
        carregarManutencoes(currentPage, perPage, searchTerm);
    };

    function atualizarInfoRegistros(pagination) {
        const info = document.getElementById('registrosInfo');
        if (!info || !pagination) return;

        const inicio = (pagination.page - 1) * pagination.perPage + 1;
        const fim = Math.min(pagination.page * pagination.perPage, pagination.total);
        info.textContent = i18n.showingPagination.replace(':start', inicio).replace(':end', fim).replace(':total', pagination.total);
    }

    // ===== EVENT LISTENERS =====

    document.getElementById('btnNovaManutencao')?.addEventListener('click', function() {
        navegarPara('/pages/manutencoes/adicionar');
    });

    document.getElementById('searchInput')?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTerm = this.value;
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            carregarManutencoes(currentPage, perPage, searchTerm);
        }, 300);
    });

    document.getElementById('rowsPerPage')?.addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        carregarManutencoes(currentPage, perPage, searchTerm);
    });

    // ===== INICIALIZACAO =====

    carregarManutencoes();

})();
</script>
@endsection
