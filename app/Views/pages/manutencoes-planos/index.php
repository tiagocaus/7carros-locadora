@extends('layouts.iframe')

@section('title', t('modules.manutencao_plano.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.manutencao_plano.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.manutencao_plano.search_placeholder') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('modules.manutencao_plano.btn_new') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.manutencao_plano.table_name') ?></th>
                    <th class="table-header hidden sm:table-cell text-center"><?= t('modules.manutencao_plano.table_items') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.manutencao_plano.table_status') ?></th>
                    <th class="table-header px-2 w-32 text-center"><?= t('modules.manutencao_plano.table_actions') ?></th>
                </tr>
            </thead>
            <tbody id="planosTableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serão inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.manutencao_plano.pagination_per_page') ?>:</label>
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
        <nav id="paginationNav" aria-label="<?= htmlspecialchars(t('modules.manutencao_plano.pagination_page_navigation'), ENT_QUOTES, 'UTF-8') ?>" class="mt-2 sm:mt-0">
            <ul class="inline-flex items-center -space-x-px">
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
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<script>
(function () {
    // Estado da paginação
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;

    // Traduções
    const translations = {
        tableEmpty: <?= json_encode(t('modules.manutencao_plano.table_empty'), $jsonFlags) ?>,
        tableLoading: <?= json_encode(t('modules.manutencao_plano.table_loading'), $jsonFlags) ?>,
        loadError: <?= json_encode(t('modules.manutencao_plano.messages.load_error'), $jsonFlags) ?>,
        statusActive: <?= json_encode(t('modules.manutencao_plano.field_status_active'), $jsonFlags) ?>,
        statusInactive: <?= json_encode(t('modules.manutencao_plano.field_status_inactive'), $jsonFlags) ?>,
        tooltipEdit: <?= json_encode(t('modules.manutencao_plano.tooltip_edit'), $jsonFlags) ?>,
        tooltipDelete: <?= json_encode(t('modules.manutencao_plano.tooltip_delete'), $jsonFlags) ?>,
        paginationInfo: <?= json_encode(t('modules.manutencao_plano.pagination_info'), $jsonFlags) ?>,
        deleteError: <?= json_encode(t('modules.manutencao_plano.messages.delete_error'), $jsonFlags) ?>,
        noName: <?= json_encode(t('modules.manutencao_plano.messages.no_name'), $jsonFlags) ?>,
        thisPlan: <?= json_encode(t('modules.manutencao_plano.messages.this_plan'), $jsonFlags) ?>
    };

    // Elementos
    const tbody = document.getElementById('planosTableBody');

    // ===== NAVEGAÇÃO =====

    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'navigate',
                page: page
            }, '*');
        } else {
            window.location.href = page;
        }
    }

    // ===== CARREGAMENTO DE DADOS =====

    async function carregarPlanos(page = 1, recordsPerPage = 10, search = '') {
        try {
            mostrarLoading();

            const result = await API.get('/api/manutencoes-planos', {
                page: page,
                perPage: recordsPerPage,
                search: search
            });

            if (result.success) {
                renderPlanos(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarMensagemErro(translations.loadError + ': ' + (result.message || ''));
            }
        } catch (error) {
            console.error('Erro ao buscar planos:', error);
            mostrarMensagemErro(error.message || translations.loadError);
        }
    }

    function mostrarLoading() {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${translations.tableLoading}
                </td>
            </tr>
        `;
    }

    function mostrarMensagemErro(mensagem) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
    }

    function renderPlanos(planos) {
        if (!planos || planos.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${translations.tableEmpty}
                    </td>
                </tr>
            `;
            return;
        }

        let tableRows = '';
        planos.forEach(p => {
            const nome = p.nome || translations.noName;
            const nomeEscapado = escapeHtml(nome);
            const itensConfigurados = p.itens_configurados || 0;
            const isAtivo = p.status === 'A';

            // Badge de status
            const statusBadge = isAtivo
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${translations.statusActive}</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">${translations.statusInactive}</span>`;

            // Badge de itens configurados
            const itensBadge = itensConfigurados > 0
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">${itensConfigurados}</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">0</span>`;

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="font-medium">${nomeEscapado}</div>
                    </td>
                    <td class="table-cell hidden sm:table-cell text-center">${itensBadge}</td>
                    <td class="table-cell hidden md:table-cell text-center">${statusBadge}</td>
                    <td class="table-cell px-2 w-32 text-right">
                        <button title="${translations.tooltipEdit}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${p.id}"><i class="fas fa-edit"></i></button>
                        <button title="${translations.tooltipDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${p.id}" data-name="${nomeEscapado}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners para botões de editar
        tbody.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                navegarPara('/pages/manutencoes-planos/adicionar?id=' + id);
            });
        });

        // Event listeners para botões de excluir
        tbody.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name') || translations.thisPlan;

                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'openDeleteModal',
                        recordId: id,
                        recordName: name,
                        recordType: 'manutencao_plano',
                        confirmType: 'none'
                    }, '*');
                } else if (window.toast) {
                    toast.error(translations.deleteError);
                }
            });
        });
    }

    // ===== PAGINAÇÃO =====

    function atualizarInfoRegistros(pagination) {
        const infoElement = document.getElementById('registrosInfo');
        if (!infoElement || !pagination) return;

        const { page, perPage, total } = pagination;
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);

        const info = translations.paginationInfo
            .replace(':start', start)
            .replace(':end', end)
            .replace(':total', total);

        infoElement.textContent = info;
    }

    function atualizarPaginacao(pagination) {
        const paginationNav = document.querySelector('#paginationNav ul');
        if (!paginationNav || !pagination) return;

        const { page, totalPages, hasPrev, hasNext } = pagination;

        let buttons = '';

        // Botão anterior
        buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-l-md ${!hasPrev ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasPrev ? 'disabled' : ''}
                        onclick="irParaPagina(${page - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </li>
        `;

        // Botões de páginas
        const maxButtons = 5;
        let startPage = Math.max(1, page - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages || 1, startPage + maxButtons - 1);

        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            buttons += `
                <li>
                    <button class="pagination-button numbered ${i === page ? 'active' : ''}"
                            onclick="irParaPagina(${i})">
                        ${i}
                    </button>
                </li>
            `;
        }

        // Botão próximo
        buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-r-md ${!hasNext ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasNext ? 'disabled' : ''}
                        onclick="irParaPagina(${page + 1})">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </li>
        `;

        paginationNav.innerHTML = buttons;
    }

    window.irParaPagina = function(page) {
        currentPage = page;
        carregarPlanos(currentPage, perPage, searchTerm);
    };

    // ===== EVENT LISTENERS =====

    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarPlanos(currentPage, perPage, searchTerm);
    });

    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarPlanos(currentPage, perPage, searchTerm);
        }, 300);
    });

    // Botão Novo - Navega para página de adicionar
    document.getElementById('btnNovo')?.addEventListener('click', function () {
        navegarPara('/pages/manutencoes-planos/adicionar');
    });

    // ===== EXCLUSÃO =====

    async function excluirPlano(id) {
        try {
            const result = await API.post(`/manutencoes-planos/${id}/excluir`);

            if (result.success) {
                carregarPlanos(currentPage, perPage, searchTerm);
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || translations.deleteError }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: translations.deleteError }, '*');
        }
    }

    // ===== LISTENER DE MENSAGENS =====

    window.addEventListener('message', function(event) {
        if (!event.data || !event.data.action) return;

        // Confirmação de exclusão do parent
        if (event.data.action === 'confirmDelete') {
            excluirPlano(event.data.recordId);
        }
    });

    // ===== HELPERS =====

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Inicialização
    carregarPlanos(currentPage, perPage, searchTerm);
})();
</script>
@endsection
