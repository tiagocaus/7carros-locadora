@extends('layouts.iframe')

@section('title', '<?= t("modules.checklists.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.checklists.title') ?></h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <button id="btnNovoChecklist" class="bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg flex items-center gap-1 whitespace-nowrap">
                <i class="fas fa-plus"></i> Novo
            </button>
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="<?= t('modules.checklists.placeholders.search') ?>" class="form-input-focus sm:w-64 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header"><?= t('modules.checklists.table.code') ?></th>
                    <th class="table-header hidden lg:table-cell"><?= t('modules.checklists.table.model') ?></th>
                    <th class="table-header hidden sm:table-cell"><?= t('modules.checklists.table.vehicle') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.checklists.table.date') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.checklists.table.type') ?></th>
                    <th class="table-header hidden md:table-cell text-center"><?= t('modules.checklists.table.status') ?></th>
                    <th class="table-header px-2 w-40 text-center"><?= t('modules.checklists.table.actions') ?></th>
                </tr>
            </thead>
            <tbody id="tableBody" class="bg-white divide-y divide-slate-200">
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.checklists.pagination.rows_per_page') ?></label>
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
        <nav aria-label="Page navigation" class="mt-2 sm:mt-0">
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
<script>
    (function() {
        const i18n = {
            loading: <?= json_encode(t('common.labels.loading'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            loadError: <?= json_encode(t('modules.checklists.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            serverError: <?= json_encode(t('modules.checklists.messages.server_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            noRecords: <?= json_encode(t('modules.checklists.messages.no_records'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            deleteError: <?= json_encode(t('modules.checklists.messages.delete_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            thisRecord: <?= json_encode(t('modules.checklists.messages.this_record'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            recordType: <?= json_encode(t('modules.checklists.record_type'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            typeLinked: <?= json_encode(t('modules.checklists.types.linked'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            typeStandalone: <?= json_encode(t('modules.checklists.types.standalone'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            actionPrint: <?= json_encode(t('common.buttons.print'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            actionDelete: <?= json_encode(t('common.buttons.delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            printLandscape: <?= json_encode(t('modules.checklists.print.landscape'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            printPortrait: <?= json_encode(t('modules.checklists.print.portrait'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            printTitlePrefix: <?= json_encode(t('modules.checklists.print.title_prefix'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            showingPagination: <?= json_encode(t('modules.checklists.pagination.showing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            statusPending: <?= json_encode(t('modules.checklists.digital.status_pending'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            statusDone: <?= json_encode(t('modules.checklists.digital.status_done'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            statusContinue: <?= json_encode(t('modules.checklists.digital.continue'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
            mobileOnly: <?= json_encode(t('modules.checklists.messages.mobile_only'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        };

        let currentPage = 1;
        let perPage = 10;
        let searchTerm = '';
        let searchTimeout = null;

        const tbody = document.getElementById('tableBody');
        const isMobile = /iPhone|iPad|iPod|Android|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || (navigator.maxTouchPoints > 1 && /Macintosh/i.test(navigator.userAgent));
        const planoCodigo = '<?= addslashes($_SESSION['user_plano'] ?? 'G') ?>';
        const canChecklist = isMobile && ['P3', 'P4'].includes(planoCodigo);

        async function carregarDados(page = 1, recordsPerPage = 10, search = '') {
            try {
                mostrarLoading();

                const result = await API.get('/api/checklists', {
                    page: page,
                    perPage: recordsPerPage,
                    search: search
                });

                if (result.success) {
                    renderTabela(result.data);
                    atualizarPaginacao(result.pagination);
                    atualizarInfoRegistros(result.pagination);
                } else {
                    mostrarMensagemErro(i18n.loadError + ': ' + (result.message || ''));
                }
            } catch (error) {
                console.error('Erro ao buscar dados:', error);
                mostrarMensagemErro(error.message || i18n.serverError);
            }
        }

        function mostrarLoading() {
            tbody.innerHTML = `
            <tr>
                <td colspan="7" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
        }

        function mostrarMensagemErro(mensagem) {
            tbody.innerHTML = `
            <tr>
                <td colspan="7" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
        }

        function getStatusBadge(status) {
            if (status === '1') {
                return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"><i class="fas fa-clock mr-1"></i>${i18n.statusPending}</span>`;
            }
            if (status === '2') {
                return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-check-circle mr-1"></i>${i18n.statusDone}</span>`;
            }
            return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">-</span>';
        }

        function getTipoBadge(tipo) {
            if (tipo === 'V') {
                return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><i class="fas fa-link mr-1"></i>${i18n.typeLinked}</span>`;
            }
            if (tipo === 'A') {
                return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"><i class="fas fa-file-alt mr-1"></i>${i18n.typeStandalone}</span>`;
            }
            return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">-</span>';
        }

        function formatarData(dataStr) {
            if (!dataStr) return '-';
            const data = new Date(dataStr);
            if (isNaN(data.getTime())) return '-';
            const dia = String(data.getDate()).padStart(2, '0');
            const mes = String(data.getMonth() + 1).padStart(2, '0');
            const ano = data.getFullYear();
            const hora = String(data.getHours()).padStart(2, '0');
            const min = String(data.getMinutes()).padStart(2, '0');
            return `${dia}/${mes}/${ano} ${hora}:${min}`;
        }

        function renderTabela(dados) {
            if (!dados || dados.length === 0) {
                tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="table-cell text-center text-slate-500">
                        <i class="fas fa-inbox mr-2"></i>${i18n.noRecords}
                    </td>
                </tr>
            `;
                return;
            }

            let tableRows = '';
            dados.forEach(item => {
                const codigo = escapeHtml(item.codigo || '-');
                const modeloNome = escapeHtml(item.modelo_nome || '-');
                const placa = escapeHtml(item.placa || '-');
                const veiculoModelo = escapeHtml(item.veiculo_modelo || '');
                const marca = escapeHtml(item.marca || '');
                const veiculoInfo = placa !== '-' ? `${placa} - ${marca} ${veiculoModelo}` : '-';

                tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="font-medium">${codigo}</div>
                    </td>
                    <td class="table-cell hidden lg:table-cell">${modeloNome}</td>
                    <td class="table-cell hidden sm:table-cell">
                        <div>${veiculoInfo}</div>
                    </td>
                    <td class="table-cell hidden md:table-cell text-center">${formatarData(item.created_at)}</td>
                    <td class="table-cell hidden md:table-cell text-center">${getTipoBadge(item.tipo)}</td>
                    <td class="table-cell hidden md:table-cell text-center">${getStatusBadge(item.status)}</td>
                    <td class="table-cell px-2 w-40 text-right">
                        <div class="inline-flex items-center justify-end gap-1">
                            ${item.status === '1' ? `<button title="${i18n.statusContinue}" class="btn-icon btn-status-pending ${canChecklist ? 'text-purple-500 hover:text-purple-700' : 'text-purple-400 cursor-not-allowed'}" data-id="${item.id}"><i class="fas fa-play-circle"></i></button>` : ''}
                            <div class="print-dropdown-wrap">
                                <button title="${i18n.actionPrint}" class="btn-icon text-blue-600 hover:text-blue-800 btn-print-toggle" data-id="${item.id}" data-codigo="${codigo}">
                                    <i class="fas fa-print"></i>
                                    <i class="fas fa-caret-down" style="font-size:10px;margin-left:2px;"></i>
                                </button>
                                <div class="print-dropdown hidden">
                                    <button class="print-dropdown-item" data-orientacao="L">
                                        <i class="fas fa-arrows-alt-h mr-2 text-slate-400"></i>${i18n.printLandscape}
                                    </button>
                                    <button class="print-dropdown-item" data-orientacao="P">
                                        <i class="fas fa-arrows-alt-v mr-2 text-slate-400"></i>${i18n.printPortrait}
                                    </button>
                                </div>
                            </div>
                            <button title="${i18n.actionDelete}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${item.id}" data-name="${codigo}"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `;
            });

            tbody.innerHTML = tableRows;

            // Dropdown de imprimir
            tbody.querySelectorAll('.btn-print-toggle').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    document.querySelectorAll('.print-dropdown').forEach(d => d.classList.add('hidden'));
                    const dropdown = this.nextElementSibling;
                    dropdown.classList.toggle('hidden');
                });
            });

            tbody.querySelectorAll('.print-dropdown-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const wrap = this.closest('.print-dropdown-wrap');
                    const btn = wrap.querySelector('.btn-print-toggle');
                    const id = btn.getAttribute('data-id');
                    const codigo = btn.getAttribute('data-codigo');
                    const orientacao = this.getAttribute('data-orientacao');
                    const url = '/checklists/' + id + '/imprimir?orientacao=' + orientacao;

                    this.closest('.print-dropdown').classList.add('hidden');

                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openPrintModal',
                            url: url,
                            title: i18n.printTitlePrefix + ' ' + codigo
                        }, '*');
                    } else {
                        window.open(url, '_blank');
                    }
                });
            });

            tbody.querySelectorAll('.btn-status-pending').forEach(button => {
                button.addEventListener('click', function() {
                    if (canChecklist) {
                        const id = this.getAttribute('data-id');
                        window.top.location.href = '/checklists/novo?retomar=' + id;
                    } else {
                        window.parent.postMessage({ action: 'openAlert', message: i18n.mobileOnly }, '*');
                    }
                });
            });

            tbody.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name') || i18n.thisRecord;

                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openDeleteModal',
                            recordId: id,
                            recordName: name,
                            recordType: i18n.recordType,
                            confirmType: 'text'
                        }, '*');
                    } else {
                        excluirRegistro(id);
                    }
                });
            });
        }

        function atualizarInfoRegistros(pagination) {
            const infoElement = document.getElementById('registrosInfo');
            if (!infoElement || !pagination) return;

            const { page, perPage, total } = pagination;
            const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
            const end = Math.min(page * perPage, total);

            infoElement.textContent = i18n.showingPagination.replace(':start', start).replace(':end', end).replace(':total', total);
        }

        function atualizarPaginacao(pagination) {
            const paginationNav = document.querySelector('nav[aria-label="Page navigation"] ul');
            if (!paginationNav || !pagination) return;

            const { page, totalPages, hasPrev, hasNext } = pagination;

            let buttons = '';

            buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-l-md ${!hasPrev ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasPrev ? 'disabled' : ''}
                        onclick="irParaPagina(${page - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </li>
        `;

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
            carregarDados(currentPage, perPage, searchTerm);
        };

        document.getElementById('rowsPerPage')?.addEventListener('change', function(e) {
            perPage = parseInt(e.target.value);
            currentPage = 1;
            carregarDados(currentPage, perPage, searchTerm);
        });

        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            searchTimeout = setTimeout(() => {
                searchTerm = e.target.value;
                currentPage = 1;
                carregarDados(currentPage, perPage, searchTerm);
            }, 300);
        });

        async function excluirRegistro(id) {
            try {
                const result = await API.post(`/checklists/${id}/excluir`);

                if (result.success) {
                    carregarDados(currentPage, perPage, searchTerm);
                } else {
                    window.parent.postMessage({
                        action: 'openAlert',
                        message: result.message || i18n.deleteError
                    }, '*');
                }
            } catch (error) {
                console.error('Erro:', error);
                window.parent.postMessage({
                    action: 'openAlert',
                    message: i18n.deleteError
                }, '*');
            }
        }

        window.addEventListener('message', function(event) {
            if (!event.data || !event.data.action) return;

            if (event.data.action === 'confirmDelete') {
                excluirRegistro(event.data.recordId);
            }
        });

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function() {
            document.querySelectorAll('.print-dropdown').forEach(d => d.classList.add('hidden'));
        });

        // Botao Novo Checklist
        (function() {
            const btn = document.getElementById('btnNovoChecklist');
            if (!btn) return;
            if (canChecklist) {
                btn.classList.add('hover:bg-blue-700', 'cursor-pointer');
                btn.onclick = function() { window.top.location.href = '/checklists/novo'; };
            } else {
                btn.classList.add('opacity-50', 'cursor-not-allowed');
                btn.onclick = function() {
                    window.parent.postMessage({ action: 'openAlert', message: i18n.mobileOnly }, '*');
                };
            }
        })();

        carregarDados(currentPage, perPage, searchTerm);
    })();
</script>
@endsection
