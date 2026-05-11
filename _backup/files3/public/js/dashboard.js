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
        if (offcanvasPanel && offcanvasPanel !== exceptThisOne) closeOffcanvas();
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
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const flagEmoji = item.dataset.flag;
                const langCode = item.dataset.lang;
                if (activeLanguageFlag && flagEmoji) {
                    activeLanguageFlag.textContent = flagEmoji;
                    // Aqui você pode salvar o idioma selecionado (localStorage, cookie, etc.)
                    // localStorage.setItem('selectedLanguage', langCode);
                }
                // Fechar dropdown após seleção
                languageDropdown.classList.remove('open');
            });
        });
    }
    if (notificationsButton && notificationsDropdown) {
        notificationsButton.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleDropdown(notificationsDropdown);
        });
    }
    function openOffcanvas() {
        closeAllPopups();
        if (offcanvasPanel) offcanvasPanel.classList.add('open');
        if (offcanvasOverlay) offcanvasOverlay.classList.add('open');
    }
    function closeOffcanvas() {
        if (offcanvasPanel) offcanvasPanel.classList.remove('open');
        if (offcanvasOverlay) offcanvasOverlay.classList.remove('open');
    }
    if (closeOffcanvasButton) closeOffcanvasButton.addEventListener('click', closeOffcanvas);
    if (offcanvasOverlay) offcanvasOverlay.addEventListener('click', closeOffcanvas);
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
    function getProximasDevolucoesHTML() {
        let itemsHTML = '';
        const devolucoesExemplo = [
            { veiculo: 'Fiat Mobi (ABC-1234)', cliente: 'Ana Costa', status: 'Hoje', statusClass: 'bg-sky-100 text-sky-700' },
            { veiculo: 'VW Gol (DEF-5678)', cliente: 'Carlos Pereira', status: 'Amanhã', statusClass: 'bg-amber-100 text-amber-700' },
            { veiculo: 'Hyundai HB20 (GHI-9012)', cliente: 'João Silva', status: '24/05', statusClass: 'bg-slate-200 text-slate-600' },
            { veiculo: 'Ford Ka (JKL-3456)', cliente: 'Maria Oliveira', status: '25/05', statusClass: 'bg-slate-200 text-slate-600' }
        ];
        devolucoesExemplo.forEach(dev => {
            itemsHTML += '<li class="flex justify-between items-center text-sm py-2 border-b border-slate-100 last:border-b-0">' +
                '<div>' +
                '<p class="text-slate-700 font-medium">' + dev.veiculo + '</p>' +
                '<p class="text-xs text-slate-500">Cliente: ' + dev.cliente + '</p>' +
                '</div>' +
                '<span class="text-xs px-2 py-0.5 rounded-full ' + dev.statusClass + '">' + dev.status + '</span>' +
                '</li>';
        });
        return '<div class="kpi-card h-full">' +
            '<h4 class="text-base font-semibold text-slate-800 mb-3 border-b pb-2">Próximas Devoluções</h4>' +
            '<ul class="space-y-1">' + itemsHTML + '</ul>' +
            '</div>';
    }
    async function displayAddClienteForm() {
        const activeSidebarTab = sidebarTabsContainer.querySelector('.sidebar-tab.active');
        const tabPage = activeSidebarTab?.dataset.tabPage;
        const tabId = activeSidebarTab?.dataset.tabId;
        if (activeSidebarTab && (tabPage === 'clientes.html' || tabId === 'clientes')) {
            const clientesContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="clientes"]');
            if (clientesContent) {
                const iframe = clientesContent.querySelector('iframe');
                if (iframe) {
                    // Se está usando iframe, mudar o src
                    iframe.src = 'clientesAdicionar.html';
                } else {
                    // Método antigo (compatibilidade)
                    clientesContent.innerHTML = await getAdicionarClienteFormHTML();
                    executeScripts(clientesContent);
                    const btnVoltar = clientesContent.querySelector('#btnVoltarListaClientes');
                    if (btnVoltar) btnVoltar.addEventListener('click', async () => {
                        clientesContent.innerHTML = await getClientesListHTML();
                        executeScripts(clientesContent);
                        attachClientListEventListeners(clientesContent);
                    });
                }
            }
        }
    }
    function attachClientListEventListeners(container) {
        const btnAdicionar = container.querySelector('#btnAdicionarCliente');
        if (btnAdicionar) btnAdicionar.addEventListener('click', async () => {
            await displayAddClienteForm();
        });
        const btnAbreLateral = container.querySelector('#btnAbreLateral');
        if (btnAbreLateral) btnAbreLateral.addEventListener('click', openOffcanvas);
        const deleteButtons = container.querySelectorAll('.btn-delete-client');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function () {
                const clientId = this.dataset.clientId;
                const clientName = this.dataset.clientName;
                const row = this.closest('tr');
                openDeleteModal(clientId, clientName, row);
            });
        });
    }
    // Função auxiliar para executar scripts dentro de HTML inserido via innerHTML
    function executeScripts(container) {
        const scripts = container.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });
            newScript.appendChild(document.createTextNode(oldScript.innerHTML));
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    async function getClientesListHTML() {
        try {
            const response = await fetch('clientes.html');
            const html = await response.text();
            return html;
        } catch (error) {
            console.error('Erro ao carregar clientes.html:', error);
            return '<div class="p-4 text-red-600">Erro ao carregar a lista de clientes.</div>';
        }
    }
    async function getAdicionarClienteFormHTML() {
        try {
            const response = await fetch('clientesAdicionar.html');
            const html = await response.text();
            return html;
        } catch (error) {
            console.error('Erro ao carregar clientesAdicionar.html:', error);
            return '<div class="p-4 text-red-600">Erro ao carregar o formulário de adicionar cliente.</div>';
        }
    }
    window.openOrSwitchToTab = async function (tabPage, tabName, tabIconClass, tabId = null) {
        // Se não foi passado tabId, usar tabPage como identificador
        const identifier = tabId || tabPage;

        // Procurar por data-tab-page primeiro, depois data-tab-id para compatibilidade
        let existingTab = sidebarTabsContainer.querySelector('.sidebar-tab[data-tab-page="' + tabPage + '"]') ||
            sidebarTabsContainer.querySelector('.sidebar-tab[data-tab-id="' + identifier + '"]');

        if (existingTab) {
            setActiveTab(existingTab);
            // Se tem arquivo HTML, recarregar o conteúdo se necessário
            if (tabPage && tabPage.endsWith('.html')) {
                const tabPageId = tabPage.replace('.html', '');
                const pageContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="' + tabPageId + '"]');
                if (pageContent && !pageContent.querySelector('iframe')) {
                    // Se não tem iframe, criar um
                    const iframe = document.createElement('iframe');
                    iframe.src = tabPage;
                    iframe.style.width = '100%';
                    iframe.style.height = 'calc(100vh - 200px)';
                    iframe.style.minHeight = '600px';
                    iframe.style.border = 'none';
                    iframe.style.display = 'block';
                    iframe.style.overflowY = 'auto';
                    iframe.style.overflowX = 'hidden';
                    pageContent.innerHTML = '';
                    pageContent.appendChild(iframe);
                }
            }
            return;
        }

        const newTabElement = document.createElement('div');
        newTabElement.classList.add('sidebar-tab');
        if (tabPage && tabPage.endsWith('.html')) {
            newTabElement.setAttribute('data-tab-page', tabPage);
            const tabPageId = tabPage.replace('.html', '');
            newTabElement.setAttribute('data-tab-id', tabPageId);
        } else {
            newTabElement.setAttribute('data-tab-id', identifier);
        }
        newTabElement.innerHTML =
            '<div class="flex items-center">' +
            '<i class="' + tabIconClass + ' tab-icon"></i>' +
            '<span>' + tabName + '</span>' +
            '</div>' +
            '<i class="fas fa-times close-icon"></i>';
        sidebarTabsContainer.appendChild(newTabElement);

        const newContentElement = document.createElement('div');
        newContentElement.classList.add('tab-content');
        const contentId = tabPage && tabPage.endsWith('.html') ? tabPage.replace('.html', '') : identifier;
        newContentElement.setAttribute('data-tab-content-id', contentId);

        if (tabPage && tabPage.endsWith('.html')) {
            // Carregar via iframe
            const iframe = document.createElement('iframe');
            iframe.src = tabPage;
            iframe.style.width = '100%';
            iframe.style.height = 'calc(100vh - 200px)';
            iframe.style.minHeight = '600px';
            iframe.style.border = 'none';
            iframe.style.display = 'block';
            iframe.style.overflowY = 'auto';
            iframe.style.overflowX = 'hidden';
            newContentElement.appendChild(iframe);
        } else {
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
        const tabPage = tabElement.getAttribute('data-tab-page');
        const tabId = tabElement.getAttribute('data-tab-id');
        const contentId = tabPage ? tabPage.replace('.html', '') : tabId;
        const contentElement = mainContentArea.querySelector('.tab-content[data-tab-content-id="' + contentId + '"]');
        if (contentElement) {
            contentElement.classList.add('active-content');
        }
    }
    function closeTab(tabElement) {
        const wasActive = tabElement.classList.contains('active');
        const tabPage = tabElement.getAttribute('data-tab-page');
        const tabId = tabElement.getAttribute('data-tab-id');
        const contentId = tabPage ? tabPage.replace('.html', '') : tabId;
        const contentElementToClose = mainContentArea.querySelector('.tab-content[data-tab-content-id="' + contentId + '"]');
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
        if (event.data && event.data.action === 'navigate') {
            const page = event.data.page;
            const activeTab = sidebarTabsContainer.querySelector('.sidebar-tab.active');
            if (activeTab) {
                const tabPage = activeTab.getAttribute('data-tab-page');
                if (tabPage && tabPage.endsWith('.html')) {
                    const clientesContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="clientes"]');
                    if (clientesContent) {
                        const iframe = clientesContent.querySelector('iframe');
                        if (iframe) {
                            iframe.src = page;
                        }
                    }
                }
            }
        } else if (event.data && event.data.action === 'openDeleteModal') {
            // Abrir modal de confirmação de exclusão
            const clientId = event.data.clientId;
            const clientName = event.data.clientName;
            const rowIndex = event.data.rowIndex;
            const sourceIframe = event.source;

            // Armazenar referência do iframe e índice da linha para usar na confirmação
            itemToDeleteId = clientId;
            itemToDeleteRow = { iframe: sourceIframe, rowIndex: rowIndex };

            const modalTitle = deleteModal.querySelector('#deleteModalTitle');
            const modalMessage = deleteModal.querySelector('#deleteModalMessage');
            modalTitle.textContent = 'Confirmar Exclusão';
            modalMessage.textContent = 'Deseja realmente excluir o cliente "' + clientName + '" (ID: ' + clientId + ')?';
            if (deleteModal) deleteModal.classList.add('open');
        }
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
                if (subTabTargetId === '#inicioSubTabProximasDevolucoes') {
                    targetContentElement.innerHTML = getProximasDevolucoesHTML();
                } else if (targetContentElement.innerHTML.trim() === '' || targetContentElement.querySelector('p.text-slate-500')) {
                    targetContentElement.innerHTML = '<div class="h-64 border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50/50"><p class="text-slate-500">Conteúdo da sub-aba "' + clickedSubTabLink.textContent.trim() + '" aqui.</p></div>';
                }
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
                    // Se está usando iframe, mudar o src
                    iframe.src = 'clientes.html';
                } else {
                    // Método antigo (compatibilidade)
                    (async () => {
                        clientesContent.innerHTML = await getClientesListHTML();
                        executeScripts(clientesContent);
                        attachClientListEventListeners(clientesContent);
                    })();
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
        defaultSubTabContent.innerHTML = '<div class="h-64 border-2 border-dashed border-slate-300 rounded-lg flex items-center justify-center bg-slate-50/50"><p class="text-slate-500">Conteúdo da sub-aba "Reservas" aparecerá aqui.</p></div>';
    }
});
