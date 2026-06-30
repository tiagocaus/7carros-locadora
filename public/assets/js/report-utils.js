/**
 * ReportUtils - Utilitários compartilhados para relatórios
 *
 * Fornece helpers para:
 * - Gerenciamento de filtros
 * - Renderização de totalizadores (KPI cards)
 * - Cores e formatação
 * - Carregamento de filiais e grupos
 * - Estados de loading/error/empty
 */
const ReportUtils = {

    // Paleta de cores para gráficos
    COLORS: [
        '#0ea5e9', '#10b981', '#f59e0b', '#ef4444',
        '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16',
    ],
    COLORS_ALPHA: [
        'rgba(14,165,233,0.15)', 'rgba(16,185,129,0.15)', 'rgba(245,158,11,0.15)', 'rgba(239,68,68,0.15)',
        'rgba(139,92,246,0.15)', 'rgba(236,72,153,0.15)', 'rgba(6,182,212,0.15)', 'rgba(132,204,22,0.15)',
    ],

    /**
     * Inicializa event listeners dos filtros
     */
    initFilters() {
        const btnLimpar = document.getElementById('btnLimpar');
        if (!btnLimpar || btnLimpar.dataset.reportUtilsClearBound === '1') return;

        btnLimpar.dataset.reportUtilsClearBound = '1';
        btnLimpar.addEventListener('click', () => {
            document.querySelectorAll('select.chosen-select').forEach(select => {
                if (select.chosenSelect && typeof select.chosenSelect.clear === 'function') {
                    select.chosenSelect.clear();
                    return;
                }

                select.value = '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    },

    /**
     * Define período padrão (mês atual)
     */
    setDefaultPeriod() {
        const inicio = document.getElementById('filterDataInicio');
        const fim = document.getElementById('filterDataFim');
        if (inicio) inicio.value = DateHelper.startOfCurrentMonthISO();
        if (fim) fim.value = DateHelper.endOfCurrentMonthISO();
    },

    /**
     * Formata Date para YYYY-MM-DD
     */
    formatDateISO(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    },

    /**
     * Carrega filiais no select
     */
    async loadFiliais(selectId) {
        try {
            const select = document.getElementById(selectId);
            if (!select || select.dataset.chosenType === 'server-side') return;

            const result = await API.get('/api/matrizes-filiais/buscar');
            if (result.success && result.data) {
                result.data.forEach(filial => {
                    const option = document.createElement('option');
                    option.value = filial.id;
                    option.textContent = filial.nome;
                    select.appendChild(option);
                });
            }
        } catch (e) {
            console.error('Erro ao carregar filiais:', e);
        }
    },

    /**
     * Carrega grupos de veículos no select
     */
    async loadGrupos(selectId) {
        try {
            const select = document.getElementById(selectId);
            if (!select || select.dataset.chosenType === 'server-side') return;

            const result = await API.get('/api/grupos', { perPage: 100 });
            if (result.success && result.data) {
                result.data.forEach(grupo => {
                    const option = document.createElement('option');
                    option.value = grupo.id;
                    option.textContent = grupo.nome || grupo.descricao;
                    select.appendChild(option);
                });
            }
        } catch (e) {
            console.error('Erro ao carregar grupos:', e);
        }
    },

    /**
     * Constrói cards totalizadores HTML
     *
     * @param {Object} totals - Dados dos totalizadores
     * @param {Array} config - Configuração [{key, label, icon, format, color, colorByValue}]
     * @returns {string} HTML dos cards
     */
    buildTotalCards(totals, config) {
        return config.map(cfg => {
            const value = totals[cfg.key] ?? 0;
            let formattedValue = value;
            let colorClass = '';

            // Formatação
            if (cfg.format === 'currency') {
                formattedValue = Currency.format(value, true);
            } else if (cfg.format === 'percent') {
                formattedValue = `${Number(value).toFixed(1)}%`;
            } else if (cfg.format === 'number') {
                formattedValue = Number(value).toLocaleString((window.APP_CONFIG?.currency?.locale || 'pt_BR').replace('_', '-'));
            }

            // Cor do card
            if (cfg.colorByValue) {
                if (value >= 70) colorClass = 'text-green-600 bg-green-50';
                else if (value >= 50) colorClass = 'text-yellow-600 bg-yellow-50';
                else colorClass = 'text-red-600 bg-red-50';
            } else if (cfg.color === 'green') {
                colorClass = 'text-green-600 bg-green-50';
            } else if (cfg.color === 'red') {
                colorClass = 'text-red-600 bg-red-50';
            } else {
                colorClass = 'text-sky-600 bg-sky-50';
            }

            const iconBg = colorClass.split(' ')[1] || 'bg-sky-50';
            const iconColor = colorClass.split(' ')[0] || 'text-sky-600';

            return `
                <div class="bg-white rounded-lg shadow-sm p-3 border border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-full ${iconBg} ${iconColor}">
                            <i class="fas ${cfg.icon}"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500 truncate">${cfg.label}</p>
                            <p class="text-lg font-bold ${iconColor}">${formattedValue}</p>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    /**
     * Retorna classes CSS de cor baseado na taxa de ocupação
     */
    getOccupancyColor(taxa) {
        if (taxa >= 70) return 'bg-green-100 text-green-800';
        if (taxa >= 50) return 'bg-yellow-100 text-yellow-800';
        return 'bg-red-100 text-red-800';
    },

    /**
     * Mostra estado de loading
     */
    showLoading() {
        const emptyState = document.getElementById('reportEmptyState');
        if (emptyState) {
            emptyState.style.display = 'block';
            emptyState.innerHTML = '<div class="text-center py-12 text-slate-400"><i class="fas fa-spinner fa-spin fa-2x mb-3"></i><p class="text-sm">Carregando...</p></div>';
        }
        this.hideContent();
    },

    /**
     * Mostra erro
     */
    showError(message) {
        const emptyState = document.getElementById('reportEmptyState');
        if (emptyState) {
            emptyState.style.display = 'block';
            emptyState.innerHTML = `<div class="text-center py-12 text-red-400"><i class="fas fa-exclamation-triangle fa-2x mb-3"></i><p class="text-sm">${message}</p></div>`;
        }
        this.hideContent();
    },

    /**
     * Mostra conteúdo do relatório (esconde empty state)
     */
    showContent() {
        const emptyState = document.getElementById('reportEmptyState');
        if (emptyState) emptyState.style.display = 'none';

        const totals = document.getElementById('reportTotals');
        if (totals) totals.style.display = '';

        const exportBtns = document.getElementById('reportExportButtons');
        if (exportBtns) exportBtns.style.display = 'flex';
    },

    /**
     * Esconde conteúdo do relatório (mostra empty state)
     */
    hideContent() {
        document.querySelectorAll('[id^="reportTableContainer"]').forEach(container => {
            container.style.display = 'none';
        });

        document.querySelectorAll('[id^="reportChartContainer"]').forEach(container => {
            container.style.display = 'none';
        });

        const totals = document.getElementById('reportTotals');
        if (totals) {
            totals.style.display = 'none';
            totals.innerHTML = '';
        }

        const exportBtns = document.getElementById('reportExportButtons');
        if (exportBtns) exportBtns.style.display = 'none';

        const pagination = document.getElementById('reportPagination');
        if (pagination) pagination.style.display = 'none';
    },

    /**
     * Renderiza paginação
     */
    renderPagination(pagination, onPageChange) {
        const container = document.getElementById('reportPagination');
        if (!container || !pagination || pagination.totalPages <= 1) {
            if (container) container.style.display = 'none';
            return;
        }

        container.style.display = 'flex';

        // Info
        const start = ((pagination.page - 1) * pagination.perPage) + 1;
        const end = Math.min(pagination.page * pagination.perPage, pagination.total);
        const info = document.getElementById('registrosInfo');
        if (info) info.textContent = `${start}-${end} de ${pagination.total}`;

        // Botões
        const btnPrev = document.getElementById('btnPrevPage');
        const btnNext = document.getElementById('btnNextPage');
        if (btnPrev) {
            btnPrev.disabled = !pagination.hasPrev;
            btnPrev.onclick = () => onPageChange(pagination.page - 1);
        }
        if (btnNext) {
            btnNext.disabled = !pagination.hasNext;
            btnNext.onclick = () => onPageChange(pagination.page + 1);
        }

        // Números de página
        const ul = document.getElementById('paginationButtons');
        if (ul) {
            // Remove numbered buttons existentes
            ul.querySelectorAll('.numbered').forEach(btn => btn.closest('li').remove());

            const prevLi = btnPrev?.closest('li');
            const maxPages = Math.min(pagination.totalPages, 5);
            let startPage = Math.max(1, pagination.page - 2);
            let endPage = Math.min(pagination.totalPages, startPage + maxPages - 1);
            if (endPage - startPage < maxPages - 1) {
                startPage = Math.max(1, endPage - maxPages + 1);
            }

            for (let p = startPage; p <= endPage; p++) {
                const li = document.createElement('li');
                const btn = document.createElement('button');
                btn.className = `pagination-button numbered${p === pagination.page ? ' active' : ''}`;
                btn.textContent = p;
                btn.onclick = () => onPageChange(p);
                li.appendChild(btn);
                ul.insertBefore(li, btnNext?.closest('li'));
            }
        }

        // Rows per page
        const rowsSelect = document.getElementById('rowsPerPage');
        if (rowsSelect) {
            rowsSelect.value = pagination.perPage;
            rowsSelect.onchange = () => onPageChange(1, parseInt(rowsSelect.value));
        }
    },

    /**
     * Exporta relatório como PDF no modal de impressão
     *
     * @param {string} url - Rota do PDF (ex: '/relatorios/kpis/taxa-ocupacao/pdf')
     * @param {string} title - Título do modal
     */
    exportPdf(url, title) {
        const pdfUrl = new URL(url, window.location.origin);

        document.querySelectorAll('[id^="filter"]').forEach((field) => {
            const key = field.id
                .replace(/^filter/, '')
                .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
                .toLowerCase();
            const value = field.value;

            if (key && value && !pdfUrl.searchParams.has(key)) {
                pdfUrl.searchParams.set(key, value);
            }
        });

        const fullUrl = url.startsWith('http://') || url.startsWith('https://')
            ? pdfUrl.toString()
            : `${pdfUrl.pathname}${pdfUrl.search}${pdfUrl.hash}`;

        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openPrintModal',
                url: fullUrl,
                title: title
            }, '*');
        } else {
            window.open(fullUrl, '_blank');
        }
    },
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ReportUtils.initFilters());
} else {
    ReportUtils.initFilters();
}
