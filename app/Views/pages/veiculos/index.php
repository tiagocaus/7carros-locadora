@extends('layouts.iframe')

@section('title', t('modules.veiculos.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0">{{ t('modules.veiculos.title') }}</h2>
        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <div class="relative flex-grow sm:flex-grow-0">
                <input type="text" placeholder="{{ t('modules.veiculos.placeholders.search') }}" class="form-input-focus sm:w-72 pr-8" id="searchInput">
                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
            </div>
            <button id="btnNovoVeiculo" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i>{{ t('common.buttons.new') }}
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-slate-200">
            <thead class="table-header-custom">
                <tr>
                    <th class="table-header">{{ t('modules.veiculos.fields.plate') }}</th>
                    <th class="table-header hidden sm:table-cell">{{ t('modules.veiculos.fields.brand_model') }}</th>
                    <th class="table-header hidden lg:table-cell">{{ t('modules.veiculos.fields.group') }}</th>
                    <th class="table-header hidden lg:table-cell">{{ t('modules.veiculos.fields.branch_short') }}</th>
                    <th class="table-header">{{ t('modules.veiculos.fields.availability') }}</th>
                    <th class="table-header px-2 w-32 text-center">{{ t('common.labels.actions') }}</th>
                </tr>
            </thead>
            <tbody id="veiculosTableBody" class="bg-white divide-y divide-slate-200">
                <!-- Linhas da tabela serao inseridas aqui pelo JavaScript -->
            </tbody>
        </table>
    </div>

    <div class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center">
        <div>
            <label for="rowsPerPage" class="text-sm text-slate-600 mr-2">{{ t('modules.veiculos.pagination.rows_per_page') }}</label>
            <select id="rowsPerPage" class="form-input-focus select-pagination">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="text-sm text-slate-600 mt-2 sm:mt-0">
            <span id="registrosInfo">{{ t('modules.veiculos.pagination.showing_empty') }}</span>
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
<?php
$jsFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$disponibilidadeLabels = [
    'D' => ['label' => t('modules.veiculos.availability.available'), 'class' => 'bg-green-100 text-green-800'],
    'L' => ['label' => t('modules.veiculos.availability.rented'), 'class' => 'bg-blue-100 text-blue-800'],
    'R' => ['label' => t('modules.veiculos.availability.reserved'), 'class' => 'bg-purple-100 text-purple-800'],
    'O' => ['label' => t('modules.veiculos.availability.in_shop'), 'class' => 'bg-yellow-100 text-yellow-800'],
    'V' => ['label' => t('modules.veiculos.availability.sold'), 'class' => 'bg-slate-100 text-slate-800'],
    'AV' => ['label' => t('modules.veiculos.availability.for_sale'), 'class' => 'bg-orange-100 text-orange-800'],
    'UI' => ['label' => t('modules.veiculos.availability.internal_use'), 'class' => 'bg-cyan-100 text-cyan-800'],
    'RO' => ['label' => t('modules.veiculos.availability.stolen'), 'class' => 'bg-red-100 text-red-800'],
    'E' => ['label' => t('modules.veiculos.availability.excluded'), 'class' => 'bg-gray-100 text-gray-800'],
];
$i18nVeiculos = [
    'loading' => t('common.labels.loading'),
    'noVehicles' => t('modules.veiculos.messages.no_vehicles'),
    'loadError' => t('modules.veiculos.messages.load_error'),
    'connectionError' => t('modules.veiculos.messages.connection_error'),
    'editBtn' => t('common.buttons.edit'),
    'deleteBtn' => t('common.buttons.delete'),
    'consultCrlv' => t('modules.veiculos.fields.consult_crlv'),
    'crlvLoading' => t('modules.veiculos.messages.online_crlv_loading'),
    'crlvError' => t('modules.veiculos.messages.online_crlv_error'),
    'crlvNoPdf' => t('modules.veiculos.messages.online_crlv_no_pdf'),
    'crlvPopupBlocked' => t('modules.veiculos.messages.online_crlv_popup_blocked'),
    'noPlate' => t('modules.veiculos.messages.no_plate'),
    'thisVehicle' => t('modules.veiculos.messages.this_vehicle'),
    'deleteConfirm' => t('modules.veiculos.messages.delete_confirm'),
    'deleteError' => t('modules.veiculos.messages.delete_error'),
    'deleteHasLinksTitle' => t('modules.veiculos.messages.delete_has_links_title'),
    'deleteHasLinksConfirm' => t('modules.veiculos.messages.delete_has_links_confirm'),
    'deactivateButton' => t('modules.veiculos.messages.deactivate_button'),
    'deactivated' => t('modules.veiculos.messages.deactivated'),
    'deactivateError' => t('modules.veiculos.messages.deactivate_error'),
    'showingTpl' => t('modules.veiculos.pagination.showing'),
];
?>
<script>
(function () {
    // Estado da paginacao
    let currentPage = 1;
    let perPage = 10;
    let searchTerm = '';
    let searchTimeout = null;
    let pendingDeactivateVehicleId = null;

    // Elementos
    const tbody = document.getElementById('veiculosTableBody');

    const disponibilidadeLabels = <?= json_encode($disponibilidadeLabels, $jsFlags) ?>;
    const i18n = <?= json_encode($i18nVeiculos, $jsFlags) ?>;

    // ===== NAVEGACAO =====

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

    async function carregarVeiculos(page = 1, recordsPerPage = 10, search = '') {
        try {
            mostrarLoading();

            const result = await API.get('/api/veiculos', {
                page: page,
                perPage: recordsPerPage,
                search: search
            });

            if (result.success) {
                renderVeiculos(result.data);
                atualizarPaginacao(result.pagination);
                atualizarInfoRegistros(result.pagination);
            } else {
                mostrarMensagemErro(i18n.loadError + (result.message || ''));
            }
        } catch (error) {
            console.error('Erro ao buscar veiculos:', error);
            mostrarMensagemErro(error.message || i18n.connectionError);
        }
    }

    function mostrarLoading() {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="table-cell text-center text-slate-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>${i18n.loading}
                </td>
            </tr>
        `;
    }

    function mostrarMensagemErro(mensagem) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="table-cell text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${mensagem}
                </td>
            </tr>
        `;
    }

    function renderVeiculos(veiculos) {
        if (!veiculos || veiculos.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="table-cell text-center text-slate-500">
                        <i class="fas fa-car mr-2"></i>${i18n.noVehicles}
                    </td>
                </tr>
            `;
            return;
        }

        let tableRows = '';
        veiculos.forEach(v => {
            const placa = escapeHtml(v.placa || i18n.noPlate);
            const marca = escapeHtml(v.marca || '');
            const modelo = escapeHtml(v.modelo || '');
            const grupo = escapeHtml(v.grupo_nome || '-');
            const filial = escapeHtml(v.filial_nome || '-');
            const disponibilidade = v.disponibilidade || 'D';
            const dispInfo = disponibilidadeLabels[disponibilidade] || disponibilidadeLabels['D'];

            tableRows += `
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="font-medium">${placa}</div>
                        <div class="text-sm text-slate-500 sm:hidden">${marca} ${modelo}</div>
                    </td>
                    <td class="table-cell hidden sm:table-cell">
                        <div>${marca} ${modelo}</div>
                    </td>
                    <td class="table-cell hidden lg:table-cell">${grupo}</td>
                    <td class="table-cell hidden lg:table-cell">${filial}</td>
                    <td class="table-cell">
                        <span class="px-2 py-1 text-xs font-medium rounded-full ${dispInfo.class}">${dispInfo.label}</span>
                    </td>
                    <td class="table-cell px-2 w-36 text-right">
                        <button title="${i18n.consultCrlv}" class="btn-icon text-sky-600 hover:text-sky-800 btn-crlv" data-placa="${placa}"><i class="fas fa-id-card"></i></button>
                        <button title="${i18n.editBtn}" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit" data-id="${v.id}"><i class="fas fa-edit"></i></button>
                        <button title="${i18n.deleteBtn}" class="btn-icon text-red-600 hover:text-red-800 btn-delete" data-id="${v.id}" data-name="${placa}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = tableRows;

        // Event listeners para botoes de editar
        tbody.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                navegarPara('/pages/veiculos/' + id + '/editar');
            });
        });

        tbody.querySelectorAll('.btn-crlv').forEach(button => {
            button.addEventListener('click', function () {
                consultarCrlv(this.getAttribute('data-placa'), this);
            });
        });

        // Event listeners para botoes de excluir
        tbody.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name') || i18n.thisVehicle;

                window.parent.postMessage({
                    action: 'openDeleteModal',
                    recordId: id,
                    recordName: name,
                    recordType: 'veiculo',
                    confirmType: 'text'
                }, '*');
            });
        });
    }

    async function consultarCrlv(placa, button) {
        if (!placa || placa === i18n.noPlate) {
            mostrarAlerta(i18n.noPlate);
            return;
        }

        const originalHtml = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const result = await API.get(`/api/multas-online/crlv/${encodeURIComponent(placa)}`);
            if (!result.success) {
                mostrarAlerta(result.message || i18n.crlvError);
                return;
            }

            const pdfBase64 = result.data?.pdf_base64 || '';
            if (!pdfBase64) {
                mostrarAlerta(result.message || i18n.crlvNoPdf);
                return;
            }

            abrirPdfBase64(pdfBase64, `crlv-${placa}.pdf`);
        } catch (error) {
            console.error('Erro ao consultar CRLV:', error);
            mostrarAlerta(error.message || i18n.crlvError);
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    }

    function abrirPdfBase64(pdfBase64, fileName) {
        const cleanBase64 = String(pdfBase64).includes(',')
            ? String(pdfBase64).split(',').pop()
            : String(pdfBase64);
        const binary = atob(cleanBase64.replace(/\s/g, ''));
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }

        const blob = new Blob([bytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const janela = window.open(url, '_blank');
        if (!janela) {
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            link.remove();
            mostrarAlerta(i18n.crlvPopupBlocked);
        }

        setTimeout(() => URL.revokeObjectURL(url), 60000);
    }

    // ===== PAGINACAO =====

    function atualizarInfoRegistros(pagination) {
        const infoElement = document.getElementById('registrosInfo');
        if (!infoElement || !pagination) return;

        const { page, perPage, total } = pagination;
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);

        infoElement.textContent = i18n.showingTpl
            .replace(':start', start)
            .replace(':end', end)
            .replace(':total', total);
    }

    function atualizarPaginacao(pagination) {
        const paginationNav = document.querySelector('nav[aria-label="Page navigation"] ul');
        if (!paginationNav || !pagination) return;

        const { page, totalPages, hasPrev, hasNext } = pagination;

        let buttons = '';

        // Botao anterior
        buttons += `
            <li>
                <button class="pagination-button arrow-button rounded-l-md ${!hasPrev ? 'opacity-50 cursor-not-allowed' : ''}"
                        ${!hasPrev ? 'disabled' : ''}
                        onclick="irParaPagina(${page - 1})">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </li>
        `;

        // Botoes de paginas
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

        // Botao proximo
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
        carregarVeiculos(currentPage, perPage, searchTerm);
    };

    // ===== EVENT LISTENERS =====

    document.getElementById('rowsPerPage')?.addEventListener('change', function (e) {
        perPage = parseInt(e.target.value);
        currentPage = 1;
        carregarVeiculos(currentPage, perPage, searchTerm);
    });

    document.getElementById('searchInput')?.addEventListener('input', function (e) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        searchTimeout = setTimeout(() => {
            searchTerm = e.target.value;
            currentPage = 1;
            carregarVeiculos(currentPage, perPage, searchTerm);
        }, 300);
    });

    // Botao Novo Veiculo - Navega para pagina de adicionar (com verificação de limite do plano)
    document.getElementById('btnNovoVeiculo')?.addEventListener('click', async function () {
        // Verificar limite do plano
        const limiteResult = await API.get('/api/plano/verificar-limite', { recurso: 'veiculos' });
        if (limiteResult && !limiteResult.pode_adicionar) {
            if (limiteResult.redirect_url) {
                navegarPara(limiteResult.redirect_url);
            }
            return;
        }

        navegarPara('/pages/veiculos/adicionar');
    });

    // ===== EXCLUSAO =====

    async function excluirVeiculo(id) {
        try {
            const result = await API.post(`/veiculos/${id}/excluir`);

            if (result.success) {
                carregarVeiculos(currentPage, perPage, searchTerm);
            } else if (result.pode_desativar && result.vinculos && result.vinculos.length > 0) {
                pendingDeactivateVehicleId = id;
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.deleteHasLinksTitle,
                    message: i18n.deleteHasLinksConfirm.replace(':links', result.vinculos.join('\n')),
                    confirmText: i18n.deactivateButton
                }, '*');
            } else {
                let msg = result.message || i18n.deleteError;
                if (result.vinculos && result.vinculos.length > 0) {
                    msg += '\n\n' + result.vinculos.join('\n');
                }
                mostrarAlerta(msg);
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarAlerta(i18n.deleteError);
        }
    }

    async function desativarVeiculo(id) {
        try {
            const result = await API.post(`/veiculos/${id}/desativar`);

            if (result.success) {
                mostrarAlerta(result.message || i18n.deactivated);
                carregarVeiculos(currentPage, perPage, searchTerm);
            } else {
                mostrarAlerta(result.message || i18n.deactivateError);
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarAlerta(i18n.deactivateError);
        }
    }

    // ===== LISTENER DE MENSAGENS =====

    window.addEventListener('message', function(event) {
        if (!event.data || !event.data.action) return;

        // Confirmacao de exclusao do parent
        if (event.data.action === 'confirmDelete') {
            excluirVeiculo(event.data.recordId);
        }

        if (event.data.action === 'genericConfirmed' && pendingDeactivateVehicleId) {
            const id = pendingDeactivateVehicleId;
            pendingDeactivateVehicleId = null;
            desativarVeiculo(id);
        }

        if (event.data.action === 'genericModalClosed' && pendingDeactivateVehicleId) {
            pendingDeactivateVehicleId = null;
        }
    });

    // ===== HELPERS =====

    function mostrarAlerta(message) {
        window.parent.postMessage({
            action: 'openAlert',
            message: message
        }, '*');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Inicializacao
    carregarVeiculos(currentPage, perPage, searchTerm);
})();
</script>
@endsection
