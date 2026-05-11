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
                itemToDeleteRow.remove();
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
    function displayAddClienteForm() {
        const activeSidebarTab = sidebarTabsContainer.querySelector('.sidebar-tab.active');
        if (activeSidebarTab && activeSidebarTab.dataset.tabId === 'clientes') {
            const clientesContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="clientes"]');
            if (clientesContent) {
                clientesContent.innerHTML = getAdicionarClienteFormHTML();
                setupFormTabs();
                const btnVoltar = clientesContent.querySelector('#btnVoltarListaClientes');
                if (btnVoltar) btnVoltar.addEventListener('click', () => {
                    clientesContent.innerHTML = getClientesListHTML();
                    attachClientListEventListeners(clientesContent);
                });
            }
        }
    }
    function attachClientListEventListeners(container) {
        const btnAdicionar = container.querySelector('#btnAdicionarCliente');
        if (btnAdicionar) btnAdicionar.addEventListener('click', displayAddClienteForm);
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
    function getClientesListHTML() {
        let tableRows = '';
        const clientesExemplo = [
            { id: 1, nome: 'João Silva', email: 'joao.silva@example.com', telefone: '(11) 98765-4321' },
            { id: 2, nome: 'Maria Oliveira', email: 'maria.oliveira@example.com', telefone: '(21) 91234-5678' },
            { id: 3, nome: 'Carlos Pereira', email: 'carlos.pereira@example.com', telefone: '(31) 95555-5555' },
            { id: 4, nome: 'Ana Costa', email: 'ana.costa@example.com', telefone: '(41) 98888-7777' },
            { id: 5, nome: 'Pedro Martins', email: 'pedro.martins@example.com', telefone: '(51) 97777-6666' }
        ];
        clientesExemplo.forEach(cliente => {
            tableRows += '<tr class="border-b border-slate-200 hover:bg-slate-50">' +
                '<td class="py-3 px-4 text-sm text-slate-700">' + cliente.id + '</td>' +
                '<td class="py-3 px-4 text-sm text-slate-700">' + cliente.nome + '</td>' +
                '<td class="py-3 px-4 text-sm text-slate-700 hidden md:table-cell">' + cliente.email + '</td>' +
                '<td class="py-3 px-4 text-sm text-slate-700 hidden lg:table-cell">' + cliente.telefone + '</td>' +
                '<td class="py-3 px-4 text-sm text-slate-700 text-center">' +
                '<button title="Ver Cliente" class="btn-icon text-sky-600 hover:text-sky-800"><i class="fas fa-eye"></i></button>' +
                '<button title="Editar Cliente" class="btn-icon text-amber-600 hover:text-amber-800"><i class="fas fa-edit"></i></button>' +
                '<button title="Excluir Cliente" class="btn-icon text-red-600 hover:text-red-800 btn-delete-client" data-client-id="' + cliente.id + '" data-client-name="' + cliente.nome + '"><i class="fas fa-trash"></i></button>' +
                '</td>' +
                '</tr>';
        });
        return '<div class="p-1 md:p-4">' +
            '<div class="clientes-header flex flex-col sm:flex-row justify-between items-center mb-6">' +
            '<h2 class="text-xl font-semibold text-slate-700 mb-3 sm:mb-0">Lista de Clientes</h2>' +
            '<div class="flex items-center space-x-3 w-full sm:w-auto">' +
            '<div class="relative flex-grow sm:flex-grow-0">' +
            '<input type="text" placeholder="Buscar cliente..." class="w-full sm:w-64 p-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 pr-8">' +
            '<i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>' +
            '</div>' +
            '<button id="btnAbreLateral" class="btn-yellow py-2 px-3 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap"><i class="fas fa-bars mr-2"></i>Abre Lateral</button>' +
            '<button id="btnAdicionarCliente" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow whitespace-nowrap"><i class="fas fa-plus mr-2"></i>Adicionar Cliente</button>' +
            '</div>' +
            '</div>' +
            '<div class="bg-white shadow-md rounded-lg overflow-x-auto">' +
            '<table class="w-full min-w-full divide-y divide-slate-200">' +
            '<thead class="table-header-custom">' +
            '<tr>' +
            '<th class="py-3 px-4 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">ID</th>' +
            '<th class="py-3 px-4 text-left text-xs font-medium text-slate-600 uppercase tracking-wider">Nome</th>' +
            '<th class="py-3 px-4 text-left text-xs font-medium text-slate-600 uppercase tracking-wider hidden md:table-cell">Email</th>' +
            '<th class="py-3 px-4 text-left text-xs font-medium text-slate-600 uppercase tracking-wider hidden lg:table-cell">Telefone</th>' +
            '<th class="py-3 px-4 text-center text-xs font-medium text-slate-600 uppercase tracking-wider">Ações</th>' +
            '</tr>' +
            '</thead>' +
            '<tbody class="bg-white divide-y divide-slate-200">' +
            tableRows +
            '</tbody>' +
            '</table>' +
            '</div>' +
            '<div class="mt-4 flex flex-wrap justify-between items-center">' +
            '<div>' +
            '<label for="rowsPerPage" class="text-sm text-slate-600 mr-2">Registos por página:</label>' +
            '<select id="rowsPerPage" class="p-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500" style="height: 32px; padding-top: 0.3rem; padding-bottom: 0.3rem;">' +
            '<option value="10">10</option>' +
            '<option value="20">20</option>' +
            '<option value="30">30</option>' +
            '<option value="50">50</option>' +
            '</select>' +
            '</div>' +
            '<nav aria-label="Page navigation" class="mt-2 sm:mt-0">' +
            '<ul class="inline-flex items-center -space-x-px">' +
            '<li><button class="pagination-button arrow-button rounded-l-md" disabled><i class="fas fa-chevron-left"></i></button></li>' +
            '<li><button class="pagination-button numbered active">1</button></li>' +
            '<li><button class="pagination-button numbered">2</button></li>' +
            '<li><button class="pagination-button numbered">3</button></li>' +
            '<li><button class="pagination-button numbered" disabled>...</button></li>' +
            '<li><button class="pagination-button numbered">10</button></li>' +
            '<li><button class="pagination-button arrow-button rounded-r-md"><i class="fas fa-chevron-right"></i></button></li>' +
            '</ul>' +
            '</nav>' +
            '</div>' +
            '</div>';
    }
    function getAdicionarClienteFormHTML() {
        return '<div class="form-container">' +
            '<div class="flex justify-between items-center mb-6">' +
            '<h2 class="text-2xl font-semibold text-slate-800">Adicionar Novo Cliente</h2>' +
            '<button id="btnVoltarListaClientes" class="py-2 px-4 border border-slate-300 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-100 flex items-center"><i class="fas fa-arrow-left mr-2"></i>Voltar</button>' +
            '</div>' +
            '<div class="mb-4 border-b border-slate-300">' +
            '<nav class="flex -mb-px" id="formClienteTabsNav">' +
            '<button data-form-tab-target="#formDadosCliente" class="form-tab-button active">Dados</button>' +
            '<button data-form-tab-target="#formArquivosCliente" class="form-tab-button">Arquivos</button>' +
            '<button data-form-tab-target="#formFaturasCliente" class="form-tab-button">Faturas</button>' +
            '</nav>' +
            '</div>' +
            '<div id="formDadosCliente" class="form-tab-content active">' +
            '<div class="form-section">' +
            '<h3 class="form-section-title">Dados do cliente</h3>' +
            '<div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">' +
            '<div class="md:col-span-2 flex flex-col items-center">' +
            '<img src="https://placehold.co/100x100/E0E0E0/757575?text=Foto" alt="Foto do Cliente" class="w-24 h-24 rounded-full object-cover mb-2 border border-slate-300">' +
            '<button class="text-sm text-sky-600 hover:text-sky-800"><i class="fas fa-camera mr-1"></i>Tirar foto</button>' +
            '</div>' +
            '<div class="md:col-span-5">' +
            '<label for="clienteMatriz" class="block text-sm font-medium text-slate-700 mb-1">Matriz/Filial</label>' +
            '<select id="clienteMatriz" class="w-full p-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500"><option>Matriz de teste</option></select>' +
            '</div>' +
            '<div class="md:col-span-5">' +
            '<label for="clienteSituacao" class="block text-sm font-medium text-slate-700 mb-1">Situação do cadastro</label>' +
            '<select id="clienteSituacao" class="w-full p-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500"><option>Ativo</option><option>Inativo</option></select>' +
            '</div>' +
            '</div>' +
            '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">' +
            '<div><label for="clienteTipo" class="block text-sm font-medium text-slate-700 mb-1">Tipo</label><select id="clienteTipo" class="w-full p-2 border border-slate-300 rounded-md text-sm"><option>Pessoa Física</option><option>Pessoa Jurídica</option></select></div>' +
            '<div><label for="clienteCPF" class="block text-sm font-medium text-slate-700 mb-1">CPF</label><input type="text" id="clienteCPF" placeholder="000.000.000-00" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div class="sm:col-span-2 lg:col-span-1"><label for="clienteNome" class="block text-sm font-medium text-slate-700 mb-1">Nome completo</label><input type="text" id="clienteNome" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '</div>' +
            '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-4">' +
            '<div><label for="clienteRG" class="block text-sm font-medium text-slate-700 mb-1">RG</label><input type="text" id="clienteRG" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteNascimento" class="block text-sm font-medium text-slate-700 mb-1">Nascimento</label><input type="date" id="clienteNascimento" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteSexo" class="block text-sm font-medium text-slate-700 mb-1">Sexo</label><select id="clienteSexo" class="w-full p-2 border border-slate-300 rounded-md text-sm"><option>Masculino</option><option>Feminino</option><option>Outro</option></select></div>' +
            '<div><label for="clienteEstadoCivil" class="block text-sm font-medium text-slate-700 mb-1">Estado civil</label><select id="clienteEstadoCivil" class="w-full p-2 border border-slate-300 rounded-md text-sm"><option>Solteiro(a)</option><option>Casado(a)</option><option>Divorciado(a)</option><option>Viúvo(a)</option></select></div>' +
            '<div><label for="clienteProfissao" class="block text-sm font-medium text-slate-700 mb-1">Profissão</label><input type="text" id="clienteProfissao" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '</div>' +
            '<div class="mt-4 w-full sm:w-1/2 lg:w-1/3"><label for="clienteSenha" class="block text-sm font-medium text-slate-700 mb-1">Senha</label><input type="password" id="clienteSenha" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '</div>' +
            '<div class="form-section">' +
            '<h3 class="form-section-title">Endereço</h3>' +
            '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">' +
            '<div><label for="clienteCEP" class="block text-sm font-medium text-slate-700 mb-1">CEP</label><input type="text" id="clienteCEP" placeholder="00000-000" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div class="lg:col-span-2"><label for="clienteRua" class="block text-sm font-medium text-slate-700 mb-1">Rua</label><input type="text" id="clienteRua" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteNumero" class="block text-sm font-medium text-slate-700 mb-1">Nº</label><input type="text" id="clienteNumero" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteComplemento" class="block text-sm font-medium text-slate-700 mb-1">Complemento</label><input type="text" id="clienteComplemento" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteBairro" class="block text-sm font-medium text-slate-700 mb-1">Bairro</label><input type="text" id="clienteBairro" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteCidade" class="block text-sm font-medium text-slate-700 mb-1">Cidade</label><input type="text" id="clienteCidade" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteEstado" class="block text-sm font-medium text-slate-700 mb-1">Estado</label><input type="text" id="clienteEstado" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clientePais" class="block text-sm font-medium text-slate-700 mb-1">País</label><input type="text" id="clientePais" value="Brasil" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '</div>' +
            '</div>' +
            '<div class="form-section">' +
            '<h3 class="form-section-title">Contato</h3>' +
            '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">' +
            '<div class="lg:col-span-2"><label for="clienteEmail" class="block text-sm font-medium text-slate-700 mb-1">E-mail</label><input type="email" id="clienteEmail" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteCelular" class="block text-sm font-medium text-slate-700 mb-1">Tel. Celular (Whatsapp)</label><input type="tel" id="clienteCelular" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteTelComercial" class="block text-sm font-medium text-slate-700 mb-1">Tel. Comercial</label><input type="tel" id="clienteTelComercial" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteTelResidencial" class="block text-sm font-medium text-slate-700 mb-1">Tel. Residencial</label><input type="tel" id="clienteTelResidencial" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '</div>' +
            '</div>' +
            '<div class="form-section">' +
            '<h3 class="form-section-title">Carteira de Motorista</h3>' +
            '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">' +
            '<div><label for="clienteCNH" class="block text-sm font-medium text-slate-700 mb-1">Nº da CNH</label><input type="text" id="clienteCNH" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteCNHCodSeg" class="block text-sm font-medium text-slate-700 mb-1">Código de Segurança</label><input type="text" id="clienteCNHCodSeg" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteCNHCategoria" class="block text-sm font-medium text-slate-700 mb-1">Categoria</label><input type="text" id="clienteCNHCategoria" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteCNHValidade" class="block text-sm font-medium text-slate-700 mb-1">Validade da CNH</label><input type="date" id="clienteCNHValidade" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '</div>' +
            '</div>' +
            '<div class="form-section">' +
            '<h3 class="form-section-title">Dados de Pagamento</h3>' +
            '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">' +
            '<div><label for="clienteCartaoBandeira" class="block text-sm font-medium text-slate-700 mb-1">Bandeira</label><select id="clienteCartaoBandeira" class="w-full p-2 border border-slate-300 rounded-md text-sm"><option>Visa</option><option>Mastercard</option><option>Elo</option></select></div>' +
            '<div class="lg:col-span-2"><label for="clienteCartaoNumero" class="block text-sm font-medium text-slate-700 mb-1">Número do Cartão</label><input type="text" id="clienteCartaoNumero" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteCartaoValidade" class="block text-sm font-medium text-slate-700 mb-1">Validade</label><input type="text" id="clienteCartaoValidade" placeholder="MM/AA" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '<div><label for="clienteCartaoCVV" class="block text-sm font-medium text-slate-700 mb-1">CVV</label><input type="text" id="clienteCartaoCVV" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '</div>' +
            '<div class="mt-4"><label for="clienteIBAN" class="block text-sm font-medium text-slate-700 mb-1">IBAN</label><input type="text" id="clienteIBAN" class="w-full p-2 border border-slate-300 rounded-md text-sm"></div>' +
            '</div>' +
            '</div>' +
            '<div id="formArquivosCliente" class="form-tab-content p-4">' +
            '<p class="text-slate-600">Conteúdo da aba Arquivos aqui...</p>' +
            '</div>' +
            '<div id="formFaturasCliente" class="form-tab-content p-4">' +
            '<p class="text-slate-600">Conteúdo da aba Faturas aqui...</p>' +
            '</div>' +
            '<div class="mt-6 flex justify-end space-x-3">' +
            '<button type="button" id="btnCancelarFormCliente" class="py-2 px-4 border border-slate-300 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">Cancelar</button>' +
            '<button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm font-medium shadow hover:shadow-md transition-shadow">Salvar Cliente</button>' +
            '</div>' +
            '</div>';
    }
    function setupFormTabs() {
        const formTabButtons = document.querySelectorAll('#formClienteTabsNav .form-tab-button');
        const formTabContents = document.querySelectorAll('#mainContentArea .tab-content[data-tab-content-id="clientes"] .form-tab-content');
        formTabButtons.forEach(button => {
            button.addEventListener('click', () => {
                formTabButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                formTabContents.forEach(content => content.classList.remove('active'));
                const targetId = button.dataset.formTabTarget;
                const targetContent = document.querySelector(targetId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });
    }
    window.openOrSwitchToTab = function (tabId, tabName, tabIconClass) {
        let existingTab = sidebarTabsContainer.querySelector('.sidebar-tab[data-tab-id="' + tabId + '"]');
        if (existingTab) {
            setActiveTab(existingTab);
            if (tabId === 'clientes') {
                const clientesContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="clientes"]');
                if (clientesContent && !clientesContent.querySelector('#formClienteTabsNav')) {
                    clientesContent.innerHTML = getClientesListHTML();
                    attachClientListEventListeners(clientesContent);
                }
            }
            return;
        }
        const newTabElement = document.createElement('div');
        newTabElement.classList.add('sidebar-tab');
        newTabElement.setAttribute('data-tab-id', tabId);
        newTabElement.innerHTML =
            '<div class="flex items-center">' +
            '<i class="' + tabIconClass + ' tab-icon"></i>' +
            '<span>' + tabName + '</span>' +
            '</div>' +
            '<i class="fas fa-times close-icon"></i>';
        sidebarTabsContainer.appendChild(newTabElement);
        const newContentElement = document.createElement('div');
        newContentElement.classList.add('tab-content');
        newContentElement.setAttribute('data-tab-content-id', tabId);
        if (tabId === 'clientes') {
            newContentElement.innerHTML = getClientesListHTML();
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
        if (tabId === 'clientes') {
            attachClientListEventListeners(newContentElement);
        }
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
        const tabIdToClose = tabElement.getAttribute('data-tab-id');
        const contentElementToClose = mainContentArea.querySelector('.tab-content[data-tab-content-id="' + tabIdToClose + '"]');
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
            const tabId = button.dataset.tabId;
            const tabName = button.dataset.tabName;
            const tabIcon = button.dataset.tabIcon;
            if (tabId && tabName && tabIcon) {
                openOrSwitchToTab(tabId, tabName, tabIcon);
            }
        });
    });
    const inicioTab = sidebarTabsContainer.querySelector('.sidebar-tab[data-tab-id="inicio"]');
    if (inicioTab) {
        inicioTab.addEventListener('click', () => setActiveTab(inicioTab));
    }
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
            displayAddClienteForm();
            return;
        }
        if (event.target.id === 'btnVoltarListaClientes' || event.target.closest('#btnVoltarListaClientes')) {
            event.preventDefault();
            const clientesContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="clientes"]');
            if (clientesContent) {
                clientesContent.innerHTML = getClientesListHTML();
                attachClientListEventListeners(clientesContent);
            }
            return;
        }
        if (event.target.id === 'btnAbreLateral' || event.target.closest('#btnAbreLateral')) {
            event.preventDefault();
            openOffcanvas();
            return;
        }
        const formTabButton = event.target.closest('#formClienteTabsNav .form-tab-button');
        if (formTabButton) {
            event.preventDefault();
            const formClienteContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="clientes"]');
            if (formClienteContent) {
                const allFormTabButtons = formClienteContent.querySelectorAll('#formClienteTabsNav .form-tab-button');
                const allFormTabContents = formClienteContent.querySelectorAll('.form-tab-content');
                allFormTabButtons.forEach(btn => btn.classList.remove('active'));
                formTabButton.classList.add('active');
                allFormTabContents.forEach(content => content.classList.remove('active'));
                const targetId = formTabButton.dataset.formTabTarget;
                const targetContent = formClienteContent.querySelector(targetId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            }
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
