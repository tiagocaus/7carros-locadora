document.addEventListener('DOMContentLoaded', function () {
    const hamburgerButton = document.getElementById('hamburgerButton');
    const mainNavLinks = document.getElementById('mainNavLinks');
    const sidebarTabsContainer = document.getElementById('sidebarTabsContainer');
    const mainContentArea = document.getElementById('mainContentArea');
    const secondaryNavButtons = document.querySelectorAll('.secondary-nav .icon-button');
    const mainNavItems = document.querySelectorAll('.main-nav-item');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const mainLayoutContainer = document.getElementById('mainLayoutContainer');
    const quickSearchForm = document.getElementById('quickSearchForm');
    // Função para alternar modo da sidebar
    function toggleSidebarMode() {
        const isHorizontal = mainLayoutContainer.classList.contains('sidebar-horizontal-mode');
        if (isHorizontal) {
            // Mudar para modo vertical (padrão)
            mainLayoutContainer.classList.remove('sidebar-horizontal-mode');
            localStorage.setItem('sidebarMode', 'vertical');
            if (sidebarToggleBtn) {
                sidebarToggleBtn.innerHTML = '<i class="fas fa-bars"></i><span class="hidden sm:inline">Modo Sidebar</span>';
            }
        } else {
            // Mudar para modo horizontal
            mainLayoutContainer.classList.add('sidebar-horizontal-mode');
            localStorage.setItem('sidebarMode', 'horizontal');
            if (sidebarToggleBtn) {
                sidebarToggleBtn.innerHTML = '<i class="fas fa-window-maximize"></i><span class="hidden sm:inline">Modo Sidebar</span>';
            }
        }
    }
    // Carregar preferência salva
    function loadSidebarPreference() {
        const savedMode = localStorage.getItem('sidebarMode');
        if (savedMode === 'horizontal') {
            mainLayoutContainer.classList.add('sidebar-horizontal-mode');
            if (sidebarToggleBtn) {
                sidebarToggleBtn.innerHTML = '<i class="fas fa-window-maximize"></i><span class="hidden sm:inline">Modo Sidebar</span>';
            }
        }
    }
    // Inicializar preferência ao carregar
    loadSidebarPreference();
    // Event listener para o botão de toggle
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSidebarMode();
        });
    }
    const languageButton = document.getElementById('languageButton');
    const languageDropdown = document.getElementById('languageDropdown');
    const notificationsButton = document.getElementById('notificationsButton');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const offcanvasOverlay = document.getElementById('offcanvasOverlay');
    const offcanvasPanel = document.getElementById('offcanvasPanel');
    const closeOffcanvasButton = document.getElementById('closeOffcanvasButton');
    const deleteModal = document.getElementById('deleteConfirmationModal');
    const cancelDeleteButton = document.getElementById('cancelDeleteButton');
    const confirmDeleteButton = document.getElementById('confirmDeleteButton');
    let itemToDeleteId = null;
    let itemToDeleteRow = null;
    function closeAllPopups(exceptThisOne = null) {
        const popups = [
            { button: languageButton, dropdown: languageDropdown },
            { button: notificationsButton, dropdown: notificationsDropdown },
            { button: hamburgerButton, dropdown: mainNavLinks }
        ];
        popups.forEach(popup => {
            if (popup.dropdown && popup.dropdown !== exceptThisOne) {
                popup.dropdown.classList.remove('open');
                if (popup.dropdown === mainNavLinks) {
                    popup.dropdown.classList.add('hidden');
                    mainNavItems.forEach(item => item.classList.remove('submenu-open'));
                }
            }
        });
        // Removido: offcanvas agora fecha apenas pelo botao X
        // if (offcanvasPanel && offcanvasPanel !== exceptThisOne) closeOffcanvas();
    }
    if (hamburgerButton && mainNavLinks) {
        hamburgerButton.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = mainNavLinks.classList.contains('open');
            closeAllPopups(mainNavLinks);
            if (isOpen) {
                mainNavLinks.classList.remove('open');
                mainNavLinks.classList.add('hidden');
            } else {
                mainNavLinks.classList.add('open');
                mainNavLinks.classList.remove('hidden');
            }
        });
    }
    mainNavItems.forEach(item => {
        const link = item.querySelector('a');
        const submenu = item.querySelector('.submenu');
        if (submenu) {
            // Para desktop: controlar abertura/fechamento dos submenus
            if (window.innerWidth > 768) {
                item.addEventListener('mouseenter', () => {
                    // Cancelar timeout de fechamento se existir
                    if (item._closeTimeout) {
                        clearTimeout(item._closeTimeout);
                        item._closeTimeout = null;
                    }
                    // Fechar todos os outros submenus
                    mainNavItems.forEach(otherItem => {
                        if (otherItem !== item) {
                            otherItem.classList.remove('submenu-open');
                            // Cancelar timeout dos outros itens também
                            if (otherItem._closeTimeout) {
                                clearTimeout(otherItem._closeTimeout);
                                otherItem._closeTimeout = null;
                            }
                            // Remover foco de links dentro do submenu fechado
                            // para desativar :focus-within do CSS
                            const links = otherItem.querySelectorAll('a');
                            links.forEach(link => link.blur());
                        }
                    });
                    // Abrir o submenu atual
                    item.classList.add('submenu-open');
                });
                item.addEventListener('mouseleave', (e) => {
                    // Verificar se o mouse está indo para o submenu
                    const relatedTarget = e.relatedTarget;
                    if (relatedTarget && (submenu.contains(relatedTarget) || submenu === relatedTarget)) {
                        return; // Não fechar se o mouse está indo para o submenu
                    }
                    // Fechar o submenu quando o mouse sai completamente
                    item._closeTimeout = setTimeout(() => {
                        item.classList.remove('submenu-open');
                        item._closeTimeout = null;
                    }, 100); // Pequeno delay para permitir transição suave
                });
                // Manter aberto quando o mouse está sobre o submenu
                submenu.addEventListener('mouseenter', () => {
                    if (item._closeTimeout) {
                        clearTimeout(item._closeTimeout);
                        item._closeTimeout = null;
                    }
                    item.classList.add('submenu-open');
                });
                submenu.addEventListener('mouseleave', () => {
                    item.classList.remove('submenu-open');
                });
            }
            if (link) {
                link.addEventListener('click', (event) => {
                    if (window.innerWidth <= 768 && mainNavLinks.classList.contains('open')) {
                        event.preventDefault();
                        event.stopPropagation();
                        const isCurrentlyOpen = item.classList.contains('submenu-open');
                        mainNavItems.forEach(otherItem => {
                            if (otherItem !== item) {
                                otherItem.classList.remove('submenu-open');
                            }
                        });
                        item.classList.toggle('submenu-open');
                    }
                });
            }
        }
    });
    // Close menu when clicking actual navigation links (not parent toggles)
    // Works for both mobile (hamburger menu) and desktop (submenus)
    mainNavLinks.addEventListener('click', (e) => {
        // Check if clicked element is a link
        const clickedLink = e.target.closest('a');
        if (!clickedLink) return;

        // Don't close if it's a submenu parent toggle (has chevron icon)
        const hasChevron = clickedLink.querySelector('i.fa-chevron-down, i.fa-chevron-right');
        if (hasChevron) return;

        // Don't close if it's a submenu-parent class (second level toggle)
        if (clickedLink.classList.contains('submenu-parent')) return;

        // Don't close if it's the main nav item link (first level with dropdown)
        const parentNavItem = clickedLink.closest('.main-nav-item');
        if (parentNavItem) {
            const isMainLink = clickedLink.parentElement === parentNavItem;
            const hasSubmenu = parentNavItem.querySelector('.submenu');
            if (isMainLink && hasSubmenu) return;
        }

        // This is an actual navigation link - close all menus
        // For mobile: close hamburger menu
        if (mainNavLinks.classList.contains('open')) {
            mainNavLinks.classList.remove('open');
            mainNavLinks.classList.add('hidden');
        }
        
        // For desktop: add class to disable hover temporarily
        // This is needed because CSS :hover keeps the menu open even after removing submenu-open
        mainNavItems.forEach(item => {
            item.classList.remove('submenu-open');
            item.classList.add('submenu-closing');
            // Clear any pending close timeouts
            if (item._closeTimeout) {
                clearTimeout(item._closeTimeout);
                item._closeTimeout = null;
            }
        });
        
        // Also close submenu-level-2
        const allSubmenuItemsWithSubmenu = document.querySelectorAll('.submenu-item-with-submenu');
        allSubmenuItemsWithSubmenu.forEach(item => {
            item.classList.remove('submenu-open');
            item.classList.add('submenu-closing');
        });
        
        // Remove the closing class after mouse leaves or after a delay
        setTimeout(() => {
            mainNavItems.forEach(item => item.classList.remove('submenu-closing'));
            allSubmenuItemsWithSubmenu.forEach(item => item.classList.remove('submenu-closing'));
        }, 300);
    });
    // Controlar submenus de segundo nível (para Relatórios)
    const submenuItemsWithSubmenu = document.querySelectorAll('.submenu-item-with-submenu');
    submenuItemsWithSubmenu.forEach(item => {
        const parentLink = item.querySelector('.submenu-parent');
        if (parentLink) {
            parentLink.addEventListener('click', (event) => {
                if (window.innerWidth <= 768) {
                    event.preventDefault();
                    event.stopPropagation();
                    // Fechar outros submenus de segundo nível no mesmo grupo
                    submenuItemsWithSubmenu.forEach(otherItem => {
                        if (otherItem !== item && otherItem.closest('.submenu-multilevel') === item.closest('.submenu-multilevel')) {
                            otherItem.classList.remove('submenu-open');
                        }
                    });
                    // Toggle do submenu atual
                    item.classList.toggle('submenu-open');
                }
            });
        }
        // Prevenir que cliques nos links do submenu-level-2 fechem o submenu
        const submenuLevel2Links = item.querySelectorAll('.submenu-level-2 a');
        submenuLevel2Links.forEach(link => {
            link.addEventListener('click', (event) => {
                if (window.innerWidth <= 768) {
                    event.stopPropagation();
                }
            });
        });
    });
    function toggleDropdown(dropdownToToggle) {
        const isOpen = dropdownToToggle.classList.contains('open');
        closeAllPopups();
        if (!isOpen) {
            dropdownToToggle.classList.add('open');
        }
    }
    if (languageButton && languageDropdown) {
        languageButton.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleDropdown(languageDropdown);
        });
        // Atualizar flag ativa quando um idioma é selecionado
        const languageItems = languageDropdown.querySelectorAll('.dropdown-item');
        const activeLanguageFlag = document.getElementById('activeLanguageFlag');
        languageItems.forEach(item => {
            item.addEventListener('click', async (e) => {
                e.preventDefault();
                const flagEmoji = item.dataset.flag;
                const locale = item.dataset.locale; // pt_BR, en_US, etc.

                if (!locale) return;

                // Atualizar UI imediatamente para feedback visual
                if (activeLanguageFlag && flagEmoji) {
                    activeLanguageFlag.textContent = flagEmoji;
                }

                // Marcar item como ativo
                languageItems.forEach(i => i.classList.remove('active'));
                item.classList.add('active');

                // Fechar dropdown
                languageDropdown.classList.remove('open');

                // Persistir no servidor via API
                try {
                    const result = await API.post('/api/locale/set', { locale: locale });
                    if (result.success) {
                        // Recarregar página para aplicar novo idioma
                        window.location.reload();
                    } else {
                        console.error('Erro ao salvar idioma:', result.message);
                    }
                } catch (error) {
                    console.error('Erro ao salvar idioma:', error);
                }
            });
        });
    }
    if (notificationsButton && notificationsDropdown) {
        notificationsButton.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleDropdown(notificationsDropdown);
            refreshNotificationCounts();
        });
    }

    async function refreshNotificationCounts() {
        try {
            const result = await API.get('/api/notifications/counts');
            if (result.success) {
                const d = result.data;
                const badge = document.getElementById('notifBadgeTotal');
                if (badge) badge.textContent = d.total;
                const header = document.getElementById('notifHeaderTotal');
                if (header) header.textContent = d.total;
                document.querySelectorAll('[data-notification-key]').forEach(el => {
                    const key = el.dataset.notificationKey;
                    if (d[key] !== undefined) {
                        el.textContent = d[key];
                        if (d[key] > 0) {
                            el.classList.add('bg-red-200', 'text-red-700');
                        } else {
                            el.classList.remove('bg-red-200', 'text-red-700');
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Failed to refresh notifications:', e);
        }
    }

    // Auto-refresh notificacoes a cada 60 segundos
    let notifPollingInterval = setInterval(refreshNotificationCounts, 60000);
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(notifPollingInterval);
            notifPollingInterval = null;
        } else {
            refreshNotificationCounts();
            notifPollingInterval = setInterval(refreshNotificationCounts, 60000);
        }
    });
    // Primeira carga via AJAX para garantir dados frescos
    refreshNotificationCounts();
    function openOffcanvas() {
        closeAllPopups();
        if (offcanvasPanel) offcanvasPanel.classList.add('open');
        if (offcanvasOverlay) offcanvasOverlay.classList.add('open');
    }
    function closeOffcanvas() {
        if (offcanvasPanel) offcanvasPanel.classList.remove('open');
        if (offcanvasOverlay) offcanvasOverlay.classList.remove('open');

        // Notificar iframe ativo que o offcanvas foi fechado
        const iframe = document.querySelector('.tab-content.active-content iframe');
        if (iframe && iframe.contentWindow) {
            iframe.contentWindow.postMessage({ action: 'offcanvasClosed' }, '*');
        }
    }
    // Expor closeOffcanvas globalmente
    window.closeOffcanvas = closeOffcanvas;

    /**
     * Abre o offcanvas com um iframe
     * @param {string} url - URL para carregar no iframe
     * @param {string} title - Título do painel
     * @param {string} width - Largura do painel (ex: '500px', '600px')
     */
    window.openOffcanvasIframe = function(url, title = 'Painel', width = '500px') {
        closeAllPopups();

        if (!offcanvasPanel || !offcanvasOverlay) return;

        // Define largura customizada
        offcanvasPanel.style.setProperty('--offcanvas-width', width);

        // Atualiza conteúdo do painel
        offcanvasPanel.innerHTML = `
            <div class="offcanvas-header">
                <h3 class="offcanvas-title">${title}</h3>
                <button onclick="closeOffcanvas()" class="offcanvas-close">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
            <div class="offcanvas-body">
                <iframe src="${url}" class="offcanvas-iframe"></iframe>
            </div>
        `;

        // Abre o painel
        offcanvasPanel.classList.add('open');
        offcanvasOverlay.classList.add('open');
    };

    /**
     * Abre o offcanvas com conteúdo HTML direto
     * @param {string} content - HTML para exibir no painel
     * @param {string} title - Título do painel
     * @param {string} width - Largura do painel (ex: '500px', '600px')
     */
    window.openOffcanvasContent = function(content, title = 'Detalhes', width = '500px') {
        closeAllPopups();

        if (!offcanvasPanel || !offcanvasOverlay) return;

        // Define largura customizada
        offcanvasPanel.style.setProperty('--offcanvas-width', width);

        // Atualiza conteúdo do painel
        offcanvasPanel.innerHTML = `
            <div class="offcanvas-header">
                <h3 class="offcanvas-title">${title}</h3>
                <button onclick="closeOffcanvas()" class="offcanvas-close">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
            <div class="offcanvas-body p-4">
                ${content}
            </div>
        `;

        // Abre o painel
        offcanvasPanel.classList.add('open');
        offcanvasOverlay.classList.add('open');
    };

    if (closeOffcanvasButton) closeOffcanvasButton.addEventListener('click', closeOffcanvas);
    // Removido: fechar ao clicar no overlay - agora fecha apenas pelo botao X
    // if (offcanvasOverlay) offcanvasOverlay.addEventListener('click', closeOffcanvas);
    function openDeleteModal(clientId, clientName, rowElement) {
        itemToDeleteId = clientId;
        itemToDeleteRow = rowElement;
        const modalTitle = deleteModal.querySelector('#deleteModalTitle');
        const modalMessage = deleteModal.querySelector('#deleteModalMessage');
        modalTitle.textContent = 'Confirmar Exclusão';
        modalMessage.textContent = 'Deseja realmente excluir o cliente "' + clientName + '" (ID: ' + clientId + ')?';
        if (deleteModal) deleteModal.classList.add('open');
    }
    function closeDeleteModal() {
        if (deleteModal) deleteModal.classList.remove('open');
        itemToDeleteId = null;
        itemToDeleteRow = null;
    }
    if (cancelDeleteButton) cancelDeleteButton.addEventListener('click', closeDeleteModal);
    if (confirmDeleteButton) {
        confirmDeleteButton.addEventListener('click', () => {
            if (itemToDeleteId && itemToDeleteRow) {
                console.log('Excluindo cliente com ID:', itemToDeleteId);

                // Verificar se é uma referência de iframe (objeto com iframe e rowIndex)
                if (itemToDeleteRow.iframe && typeof itemToDeleteRow.rowIndex === 'number') {
                    // Enviar mensagem para o iframe para remover a linha
                    itemToDeleteRow.iframe.postMessage({
                        action: 'confirmDelete',
                        rowIndex: itemToDeleteRow.rowIndex
                    }, '*');
                } else {
                    // Método antigo (compatibilidade quando não está em iframe)
                    if (itemToDeleteRow.remove) {
                        itemToDeleteRow.remove();
                    }
                }
            }
            closeDeleteModal();
        });
    }
    document.addEventListener('click', function (event) {
        let clickedInsideADropdownOrButton = false;
        [languageButton, languageDropdown, notificationsButton, notificationsDropdown, hamburgerButton, mainNavLinks].forEach(el => {
            if (el && el.contains(event.target)) {
                clickedInsideADropdownOrButton = true;
            }
        });
        if (mainNavLinks && mainNavLinks.classList.contains('open')) {
            mainNavItems.forEach(item => {
                if (item.classList.contains('submenu-open') && item.querySelector('.submenu').contains(event.target)) {
                    clickedInsideADropdownOrButton = true;
                }
            });
            // Verificar também se o clique foi dentro de um submenu de nível 2
            const submenuItemsWithSubmenu = document.querySelectorAll('.submenu-item-with-submenu');
            submenuItemsWithSubmenu.forEach(item => {
                if (item.classList.contains('submenu-open')) {
                    const submenuLevel2 = item.querySelector('.submenu-level-2');
                    if (submenuLevel2 && submenuLevel2.contains(event.target)) {
                        clickedInsideADropdownOrButton = true;
                    }
                }
                // Verificar também se o clique foi no parent link
                const parentLink = item.querySelector('.submenu-parent');
                if (parentLink && parentLink.contains(event.target)) {
                    clickedInsideADropdownOrButton = true;
                }
            });
        }
        if (!clickedInsideADropdownOrButton) {
            closeAllPopups();
        }
    });
    const dashboardSimpleSubtabs = {
        '#inicioSubTabReservas': {
            tab: 'reservas',
            title: 'Reservas',
            icon: 'fa-calendar-check',
            empty: 'Nenhuma reserva encontrada.',
            kind: 'locacao',
            dateLabel: 'Saída',
            filialField: 'filial_retirada'
        },
        '#inicioSubTabAlugados': {
            tab: 'alugados',
            title: 'Alugados',
            icon: 'fa-car-side',
            empty: 'Nenhuma locação aberta encontrada.',
            kind: 'locacao',
            dateLabel: 'Prevista',
            filialField: 'filial_devolucao'
        },
        '#inicioSubTabDisponiveis': {
            tab: 'disponiveis',
            title: 'Disponíveis',
            icon: 'fa-car',
            empty: 'Nenhum veículo disponível encontrado.',
            kind: 'veiculo'
        },
        '#inicioSubTabChegadaPendente': {
            tab: 'chegada_pendente',
            title: 'Chegada pendente',
            icon: 'fa-clock',
            empty: 'Nenhuma chegada pendente encontrada.',
            kind: 'locacao',
            dateLabel: 'Prevista',
            filialField: 'filial_devolucao'
        },
        '#inicioSubTabProximasDevolucoes': {
            tab: 'proximas_devolucoes',
            title: 'Próximas Devoluções',
            icon: 'fa-rotate-left',
            empty: 'Nenhuma devolução próxima encontrada.',
            kind: 'locacao',
            dateLabel: 'Prevista',
            filialField: 'filial_devolucao'
        }
    };

    function escapeHTML(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderDashboardSubtabLoading(config) {
        return '<div class="kpi-card min-h-32 flex items-center justify-center">' +
            '<div class="text-sm text-slate-500 flex items-center gap-2">' +
            '<i class="fas fa-spinner fa-spin"></i>' +
            '<span>Carregando ' + escapeHTML(config.title.toLowerCase()) + '...</span>' +
            '</div>' +
            '</div>';
    }

    function renderDashboardSubtabEmpty(config, updatedAt) {
        return '<div class="kpi-card min-h-32">' +
            renderDashboardSubtabHeader(config, updatedAt, 0) +
            '<div class="border-2 border-dashed border-slate-300 rounded-lg bg-slate-50/50 min-h-24 flex items-center justify-center">' +
            '<p class="text-sm text-slate-500">' + escapeHTML(config.empty) + '</p>' +
            '</div>' +
            '</div>';
    }

    function renderDashboardSubtabError(config, message) {
        return '<div class="kpi-card min-h-32">' +
            renderDashboardSubtabHeader(config, '', 0) +
            '<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">' +
            escapeHTML(message || 'Não foi possível carregar os dados desta aba.') +
            '</div>' +
            '</div>';
    }

    function renderDashboardSubtabHeader(config, updatedAt, count) {
        const updated = updatedAt ? '<span class="text-xs text-slate-400">Atualizado ' + escapeHTML(updatedAt) + '</span>' : '';
        return '<div class="flex flex-wrap items-center justify-between gap-2 mb-3 border-b border-slate-100 pb-2">' +
            '<h4 class="text-base font-semibold text-slate-800 flex items-center gap-2">' +
            '<i class="fas ' + config.icon + ' text-slate-400"></i>' +
            '<span>' + escapeHTML(config.title) + '</span>' +
            '<span class="text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">' + count + '</span>' +
            '</h4>' +
            updated +
            '</div>';
    }

    function getDashboardBadgeClass(label) {
        if (label === 'Hoje') return 'bg-sky-100 text-sky-700';
        if (label === 'Amanhã') return 'bg-amber-100 text-amber-700';
        if (label === 'Retirada pendente') return 'bg-orange-100 text-orange-700';
        if (label && label.includes('atraso')) return 'bg-red-100 text-red-700';
        if (label === 'Disponível') return 'bg-emerald-100 text-emerald-700';
        return 'bg-slate-100 text-slate-600';
    }

    function renderDashboardSubtab(config, rows, updatedAt) {
        if (!Array.isArray(rows) || rows.length === 0) {
            return renderDashboardSubtabEmpty(config, updatedAt);
        }

        const body = config.kind === 'veiculo'
            ? renderDashboardVehiclesRows(rows)
            : renderDashboardLocacoesRows(rows, config);

        return '<div class="kpi-card">' +
            renderDashboardSubtabHeader(config, updatedAt, rows.length) +
            '<div class="overflow-x-auto">' +
            '<table class="min-w-full text-sm">' +
            '<thead>' + renderDashboardSubtabHead(config) + '</thead>' +
            '<tbody class="divide-y divide-slate-100">' + body + '</tbody>' +
            '</table>' +
            '</div>' +
            '</div>';
    }

    function renderDashboardSubtabHead(config) {
        if (config.kind === 'veiculo') {
            return '<tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">' +
                '<th class="pb-2 pr-4">Placa</th>' +
                '<th class="pb-2 pr-4">Veículo</th>' +
                '<th class="pb-2 pr-4">Grupo</th>' +
                '<th class="pb-2 pr-4">Filial</th>' +
                '<th class="pb-2 pr-4">Odômetro</th>' +
                '<th class="pb-2 text-right">Ações</th>' +
                '</tr>';
        }

        return '<tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">' +
            '<th class="pb-2 pr-4">Código</th>' +
            '<th class="pb-2 pr-4">Veículo</th>' +
            '<th class="pb-2 pr-4">Cliente</th>' +
            '<th class="pb-2 pr-4">' + escapeHTML(config.dateLabel) + '</th>' +
            '<th class="pb-2 pr-4">Filial</th>' +
            '<th class="pb-2 pr-4">Prazo</th>' +
            '<th class="pb-2 text-right">Ações</th>' +
            '</tr>';
    }

    function renderDashboardLocacoesRows(rows, config) {
        return rows.map(row => {
            const vehicleLabel = row.placa ? row.veiculo + ' (' + row.placa + ')' : row.veiculo;
            const badgeClass = getDashboardBadgeClass(row.prazo_label);
            return '<tr class="text-slate-700">' +
                '<td class="py-3 pr-4 font-medium text-slate-800">' + escapeHTML(row.codigo || '-') + '</td>' +
                '<td class="py-3 pr-4">' + escapeHTML(vehicleLabel || '-') + '</td>' +
                '<td class="py-3 pr-4">' + escapeHTML(row.cliente || '-') + '</td>' +
                '<td class="py-3 pr-4 whitespace-nowrap">' + escapeHTML(row.data_referencia || '-') + '</td>' +
                '<td class="py-3 pr-4">' + escapeHTML(row[config.filialField] || '-') + '</td>' +
                '<td class="py-3 pr-4"><span class="text-xs px-2 py-0.5 rounded-full ' + badgeClass + '">' + escapeHTML(row.prazo_label || '-') + '</span></td>' +
                '<td class="py-3 text-right">' +
                '<button type="button" class="text-sky-600 hover:text-sky-800 font-medium" data-dashboard-row-action="locacao" data-id="' + escapeHTML(row.id) + '">Abrir</button>' +
                '</td>' +
                '</tr>';
        }).join('');
    }

    function renderDashboardVehiclesRows(rows) {
        return rows.map(row => {
            const badgeClass = getDashboardBadgeClass(row.prazo_label);
            return '<tr class="text-slate-700">' +
                '<td class="py-3 pr-4 font-medium text-slate-800 whitespace-nowrap">' + escapeHTML(row.placa || '-') + '</td>' +
                '<td class="py-3 pr-4">' + escapeHTML(row.veiculo || '-') + '</td>' +
                '<td class="py-3 pr-4">' + escapeHTML(row.grupo || '-') + '</td>' +
                '<td class="py-3 pr-4">' + escapeHTML(row.filial || '-') + '</td>' +
                '<td class="py-3 pr-4 whitespace-nowrap">' + escapeHTML(row.odometro || '-') + '</td>' +
                '<td class="py-3 text-right whitespace-nowrap">' +
                '<span class="text-xs px-2 py-0.5 rounded-full ' + badgeClass + ' mr-3">' + escapeHTML(row.prazo_label || 'Disponível') + '</span>' +
                '<button type="button" class="text-sky-600 hover:text-sky-800 font-medium" data-dashboard-row-action="veiculo" data-id="' + escapeHTML(row.id) + '">Abrir</button>' +
                '</td>' +
                '</tr>';
        }).join('');
    }

    async function loadDashboardSimpleSubtab(targetContentElement, config) {
        const requestId = String(Date.now());
        targetContentElement.dataset.requestId = requestId;
        targetContentElement.innerHTML = renderDashboardSubtabLoading(config);

        try {
            const result = await API.get('/api/dashboard/subtabs/' + config.tab);
            if (targetContentElement.dataset.requestId !== requestId) return;

            if (!result.success) {
                targetContentElement.innerHTML = renderDashboardSubtabError(config, result.message);
                return;
            }

            targetContentElement.innerHTML = renderDashboardSubtab(config, result.data || [], result.updated_at || '');
        } catch (error) {
            if (targetContentElement.dataset.requestId !== requestId) return;
            targetContentElement.innerHTML = renderDashboardSubtabError(config, error.message);
        }
    }
    async function displayAddClienteForm() {
        const activeSidebarTab = sidebarTabsContainer.querySelector('.sidebar-tab.active');
        const tabId = activeSidebarTab ? activeSidebarTab.dataset.tabId : null;
        if (activeSidebarTab && tabId === 'clientes') {
            const clientesContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="clientes"]');
            if (clientesContent) {
                const iframe = clientesContent.querySelector('iframe');
                if (iframe) {
                    // Mudar para a rota de adicionar cliente
                    iframe.src = '/pages/clientes/adicionar';
                }
            }
        }
    }
    window.openOrSwitchToTab = async function (tabPage, tabName, tabIconClass, tabId = null) {
        // Função para normalizar IDs removendo /pages/ e .html
        const normalizeId = (id) => {
            if (!id) return id;
            return id.replace('/pages/', '').replace('.html', '');
        };

        // Se não foi passado tabId, usar tabPage como identificador
        const identifier = tabId || tabPage;
        const normalizedIdentifier = normalizeId(identifier);

        // Procurar aba existente pelo ID normalizado
        let existingTab = sidebarTabsContainer.querySelector('.sidebar-tab[data-tab-id="' + normalizedIdentifier + '"]');

        if (existingTab) {
            setActiveTab(existingTab);
            return;
        }

        // Criar nova aba
        const newTabElement = document.createElement('div');
        newTabElement.classList.add('sidebar-tab');
        newTabElement.setAttribute('data-tab-id', normalizedIdentifier);
        newTabElement.innerHTML =
            '<div class="flex items-center">' +
            '<i class="' + tabIconClass + ' tab-icon"></i>' +
            '<span>' + tabName + '</span>' +
            '</div>' +
            '<i class="fas fa-times close-icon"></i>';
        sidebarTabsContainer.appendChild(newTabElement);

        // Criar conteúdo da aba
        const newContentElement = document.createElement('div');
        newContentElement.classList.add('tab-content');
        newContentElement.setAttribute('data-tab-content-id', normalizedIdentifier);

        if (tabPage && tabPage.startsWith('/pages/')) {
            // Criar loading spinner
            const loadingDiv = document.createElement('div');
            loadingDiv.classList.add('tab-loading');
            loadingDiv.innerHTML = '<div class="tab-loading-spinner"></div>';
            newContentElement.appendChild(loadingDiv);

            // Carregar via iframe para rotas /pages/
            const iframe = document.createElement('iframe');
            iframe.src = tabPage;
            iframe.style.width = '100%';
            iframe.style.flex = '1';
            iframe.style.minHeight = '0';
            iframe.style.border = 'none';
            iframe.style.display = 'none';

            // Guardar referência do loading para remover via postMessage
            iframe._loadingDiv = loadingDiv;

            newContentElement.appendChild(iframe);
        } else {
            // Conteúdo placeholder para outras abas
            newContentElement.innerHTML =
                '<div class="p-4">' +
                '<h2 class="text-xl font-semibold text-slate-700 mb-4">Conteúdo para ' + tabName + '</h2>' +
                '<div class="min-h-[24rem] border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50/50">' +
                '<p class="text-slate-500">Detalhes de ' + tabName + ' aparecerão aqui.</p>' +
                '</div>' +
                '</div>';
        }
        mainContentArea.appendChild(newContentElement);
        setActiveTab(newTabElement);
        newTabElement.querySelector('.close-icon').addEventListener('click', (e) => {
            e.stopPropagation();
            closeTab(newTabElement);
        });
        newTabElement.addEventListener('click', () => setActiveTab(newTabElement));
    }
    function setActiveTab(tabElement) {
        sidebarTabsContainer.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
        mainContentArea.querySelectorAll('.tab-content').forEach(c => {
            c.classList.remove('active-content');
        });
        tabElement.classList.add('active');

        const tabId = tabElement.getAttribute('data-tab-id');
        const contentElement = mainContentArea.querySelector('.tab-content[data-tab-content-id="' + tabId + '"]');
        if (contentElement) {
            contentElement.classList.add('active-content');
        }
    }
    function closeTab(tabElement) {
        const wasActive = tabElement.classList.contains('active');
        const tabId = tabElement.getAttribute('data-tab-id');
        const contentElementToClose = mainContentArea.querySelector('.tab-content[data-tab-content-id="' + tabId + '"]');

        tabElement.remove();
        if (contentElementToClose) contentElementToClose.remove();

        if (wasActive) {
            const remainingTabs = sidebarTabsContainer.querySelectorAll('.sidebar-tab');
            let nextActiveTab = sidebarTabsContainer.querySelector('.sidebar-tab[data-tab-id="inicio"]');
            if (!nextActiveTab && remainingTabs.length > 0) {
                nextActiveTab = remainingTabs[0];
            } else if (remainingTabs.length > 0) {
                nextActiveTab = sidebarTabsContainer.querySelector('.sidebar-tab[data-tab-id="inicio"]') || remainingTabs[remainingTabs.length - 1];
            }
            if (nextActiveTab) {
                setActiveTab(nextActiveTab);
            } else if (sidebarTabsContainer.children.length === 0) {
                mainContentArea.innerHTML = '<p class="p-4 text-slate-500">Nenhuma aba aberta.</p>';
            }
        }
    }
    secondaryNavButtons.forEach(button => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const tabPage = button.dataset.tabPage;
            const tabId = button.dataset.tabId;
            const tabName = button.dataset.tabName;
            const tabIcon = button.dataset.tabIcon;
            if ((tabPage || tabId) && tabName && tabIcon) {
                openOrSwitchToTab(tabPage || tabId, tabName, tabIcon, tabId);
            }
        });
    });
    const inicioTab = sidebarTabsContainer.querySelector('.sidebar-tab[data-tab-id="inicio"]');
    if (inicioTab) {
        inicioTab.addEventListener('click', () => setActiveTab(inicioTab));
    }
    // Escutar mensagens dos iframes para navegação e modais
    window.addEventListener('message', function (event) {
        // Iframe terminou de carregar - esconder loading e mostrar conteúdo
        if (event.data && event.data.action === 'iframeReady') {
            const iframes = document.querySelectorAll('iframe');
            iframes.forEach(iframe => {
                if (iframe.contentWindow === event.source) {
                    if (iframe._loadingDiv) {
                        iframe._loadingDiv.remove();
                        iframe._loadingDiv = null;
                    }
                    iframe.style.display = 'block';
                }
            });
        }
        if (event.data && event.data.action === 'navigate') {
            const page = event.data.page;
            const activeTab = sidebarTabsContainer.querySelector('.sidebar-tab.active');
            if (activeTab) {
                const tabId = activeTab.getAttribute('data-tab-id');
                const tabContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="' + tabId + '"]');
                if (tabContent) {
                    const iframe = tabContent.querySelector('iframe');
                    if (iframe) {
                        iframe.src = page;
                    }
                }
            }
        }
        // O tratamento de openDeleteModal foi movido para app.php (modal global)
        // Removido código antigo que usava clientId/clientName ao invés de recordId/recordName
    });
    mainContentArea.addEventListener('click', function (event) {
        const clickedSubTabLink = event.target.closest('#inicioSubTabsNav a.subtab-link');
        if (clickedSubTabLink) {
            event.preventDefault();
            const parentTabContent = clickedSubTabLink.closest('.tab-content[data-tab-content-id="inicio"]');
            if (!parentTabContent) return;
            const subTabContentArea = parentTabContent.querySelector('#inicioSubTabContentArea');
            if (!subTabContentArea) return;
            const currentNav = clickedSubTabLink.closest('nav');
            currentNav.querySelectorAll('a.subtab-link').forEach(t => {
                t.classList.remove('tab-active-main');
                t.classList.add('tab-inactive-main');
            });
            subTabContentArea.querySelectorAll('.subtab-content').forEach(c => {
                c.classList.add('hidden');
                c.classList.remove('active');
            });
            clickedSubTabLink.classList.add('tab-active-main');
            clickedSubTabLink.classList.remove('tab-inactive-main');
            const subTabTargetId = clickedSubTabLink.dataset.subtabTarget;
            const targetContentElement = subTabContentArea.querySelector(subTabTargetId);
            if (targetContentElement) {
                targetContentElement.classList.remove('hidden');
                targetContentElement.classList.add('active');
                const dashboardConfig = dashboardSimpleSubtabs[subTabTargetId];
                if (dashboardConfig) {
                    loadDashboardSimpleSubtab(targetContentElement, dashboardConfig);
                } else if (targetContentElement.innerHTML.trim() === '' || targetContentElement.querySelector('p.text-slate-500')) {
                    targetContentElement.innerHTML = '<div class="h-64 border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50/50"><p class="text-slate-500">Conteúdo da sub-aba "' + clickedSubTabLink.textContent.trim() + '" aqui.</p></div>';
                }
            }
            return;
        }
        const dashboardAction = event.target.closest('[data-dashboard-row-action]');
        if (dashboardAction) {
            event.preventDefault();
            const id = dashboardAction.dataset.id;
            const type = dashboardAction.dataset.dashboardRowAction;
            if (!id) return;

            if (type === 'locacao') {
                openOrSwitchToTab('/pages/locacoes/editar/' + id, 'Locação #' + id, 'fas fa-key', 'locacao-' + id);
            } else if (type === 'veiculo') {
                openOrSwitchToTab('/pages/veiculos/' + id + '/editar', 'Veículo #' + id, 'fas fa-car', 'veiculo-' + id);
            }
            return;
        }
        if (event.target.id === 'btnAdicionarCliente' || event.target.closest('#btnAdicionarCliente')) {
            event.preventDefault();
            (async () => {
                await displayAddClienteForm();
            })();
            return;
        }
        if (event.target.id === 'btnVoltarListaClientes' || event.target.closest('#btnVoltarListaClientes')) {
            event.preventDefault();
            const clientesContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="clientes"]');
            if (clientesContent) {
                const iframe = clientesContent.querySelector('iframe');
                if (iframe) {
                    // Voltar para a lista de clientes
                    iframe.src = '/pages/clientes';
                }
            }
            return;
        }
        if (event.target.id === 'btnAbreLateral' || event.target.closest('#btnAbreLateral')) {
            event.preventDefault();
            openOffcanvas();
            return;
        }
    });
    // Ativar a primeira sub-aba "Reservas" por defeito e carregar seu conteúdo
    const defaultSubTabLink = document.querySelector('#inicioSubTabsNav a[data-subtab-target="#inicioSubTabReservas"]');
    const defaultSubTabContent = document.getElementById('inicioSubTabReservas');
    const subTabContentArea = document.getElementById('inicioSubTabContentArea');
    if (defaultSubTabLink && defaultSubTabContent && subTabContentArea) {
        subTabContentArea.querySelectorAll('.subtab-content').forEach(c => {
            c.classList.add('hidden');
            c.classList.remove('active');
        });
        defaultSubTabLink.classList.add('tab-active-main');
        defaultSubTabLink.classList.remove('tab-inactive-main');
        defaultSubTabContent.classList.remove('hidden');
        defaultSubTabContent.classList.add('active');
        loadDashboardSimpleSubtab(defaultSubTabContent, dashboardSimpleSubtabs['#inicioSubTabReservas']);
    }

    // Botão de Perfil do Usuário - Abre offcanvas com página de perfil
    const btnPerfilUsuario = document.getElementById('btnPerfilUsuario');
    if (btnPerfilUsuario) {
        btnPerfilUsuario.addEventListener('click', function() {
            window.openOffcanvasIframe('/pages/perfil', 'Meu Perfil', '550px');
        });
    }
});
