/**
 * Chosen Select - Sistema de selects com busca
 * Suporta dois tipos:
 * 1. Normal: Mostra todos os registros
 * 2. Server-Side: Requer pelo menos 3 letras para buscar
 */

(function () {
    'use strict';

    function normalizeText(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    class ChosenSelect {
        constructor(selectElement, options = {}) {
            this.select = selectElement;
            this.options = {
                type: options.type || 'normal', // 'normal' ou 'server-side'
                minChars: options.minChars || 3, // Mínimo de caracteres para server-side
                searchUrl: options.searchUrl || null, // URL para busca server-side
                placeholder: options.placeholder || 'Selecione uma opção...',
                noResultsText: options.noResultsText || 'Nenhum resultado encontrado',
                minCharsText: options.minCharsText || 'Digite pelo menos {min} letras para buscar...',
                allowClear: options.allowClear !== false,
                ...options
            };

            this.isOpen = false;
            this.searchTerm = '';
            this.filteredOptions = [];
            this.highlightedIndex = -1;
            this.allOptions = [];
            this.groups = [];
            this.filteredGroups = null;
            this.isMultiple = this.select.multiple;
            this.selectedValue = this.select.value;
            this.selectedValues = this.getSelectedValues();

            // Portal (dropdown fora do fluxo do container) para evitar corte por overflow
            this.isPortalMounted = false;
            this.dropdownOriginalParent = null;
            this.dropdownOriginalNextSibling = null;
            this._boundUpdateDropdownPosition = this.updateDropdownPosition.bind(this);

            this.init();
        }

        init() {
            // Criar estrutura HTML
            this.createWrapper();

            // Carregar opções iniciais
            this.loadOptions();

            // Adicionar event listeners
            this.attachEvents();

            // Esconder select original
            this.select.style.display = 'none';
        }

        createWrapper() {
            // Container principal
            this.container = document.createElement('div');
            this.container.className = 'chosen-select-container';

            // Wrapper do select customizado
            this.wrapper = document.createElement('div');
            this.wrapper.className = 'chosen-select-wrapper';

            // Select customizado (display)
            this.display = document.createElement('div');
            this.display.className = 'chosen-select';
            if (this.isMultiple) {
                this.display.classList.add('chosen-select-multiple');
            }
            this.updateDisplay();
            this.display.setAttribute('role', 'combobox');
            this.display.setAttribute('aria-expanded', 'false');

            // Botão limpar
            if (this.options.allowClear) {
                this.clearButton = document.createElement('span');
                this.clearButton.className = 'chosen-select-clear';
                this.clearButton.innerHTML = '&times;';
                this.clearButton.title = 'Limpar seleção';
                this.clearButton.style.display = this.hasSelection() ? 'block' : 'none';
            }

            // Dropdown
            this.dropdown = document.createElement('div');
            this.dropdown.className = 'chosen-select-dropdown';

            // Campo de busca
            this.searchContainer = document.createElement('div');
            this.searchContainer.className = 'chosen-select-search';
            this.searchInput = document.createElement('input');
            this.searchInput.type = 'text';
            this.searchInput.placeholder = this.options.type === 'server-side'
                ? this.options.minCharsText.replace('{min}', this.options.minChars)
                : 'Buscar...';
            this.searchInput.setAttribute('aria-label', 'Buscar opções');
            this.searchInput.setAttribute('autocomplete', 'off');
            this.searchInput.setAttribute('autocorrect', 'off');
            this.searchInput.setAttribute('autocapitalize', 'off');
            this.searchInput.setAttribute('spellcheck', 'false');
            this.searchContainer.appendChild(this.searchInput);

            // Container de opções
            this.optionsContainer = document.createElement('div');
            this.optionsContainer.className = 'chosen-select-options';

            // Mensagens
            this.noResults = document.createElement('div');
            this.noResults.className = 'chosen-select-no-results';
            this.noResults.style.display = 'none';
            this.noResults.textContent = this.options.noResultsText;

            this.minCharsMsg = document.createElement('div');
            this.minCharsMsg.className = 'chosen-select-min-chars';
            this.minCharsMsg.style.display = 'none';
            this.minCharsMsg.textContent = this.options.minCharsText.replace('{min}', this.options.minChars);

            this.loading = document.createElement('div');
            this.loading.className = 'chosen-select-loading';
            this.loading.style.display = 'none';
            this.loading.textContent = 'Carregando...';

            // Montar estrutura
            this.dropdown.appendChild(this.searchContainer);
            this.dropdown.appendChild(this.optionsContainer);
            this.dropdown.appendChild(this.noResults);
            this.dropdown.appendChild(this.minCharsMsg);
            this.dropdown.appendChild(this.loading);

            this.wrapper.appendChild(this.display);
            if (this.options.allowClear && this.clearButton) {
                this.wrapper.appendChild(this.clearButton);
            }
            this.container.appendChild(this.wrapper);
            this.container.appendChild(this.dropdown);

            // Inserir após o select original
            this.select.parentNode.insertBefore(this.container, this.select.nextSibling);
        }

        mountDropdownToBody() {
            if (this.isPortalMounted) return;
            if (!this.dropdown) return;

            this.dropdownOriginalParent = this.dropdown.parentNode;
            this.dropdownOriginalNextSibling = this.dropdown.nextSibling;

            this.dropdown.classList.add('is-portal');
            this.dropdown.style.position = 'fixed';
            this.dropdown.style.marginTop = '0';
            this.dropdown.style.left = '0px';
            this.dropdown.style.top = '0px';
            this.dropdown.style.width = '0px';
            this.dropdown.style.right = 'auto';

            document.body.appendChild(this.dropdown);
            this.isPortalMounted = true;
            this.addPortalListeners();
        }

        unmountDropdownFromBody() {
            if (!this.isPortalMounted) return;

            this.removePortalListeners();

            // Remover estilos de portal (evita afetar quando voltar pro container)
            this.dropdown.classList.remove('is-portal');
            this.dropdown.style.position = '';
            this.dropdown.style.marginTop = '';
            this.dropdown.style.left = '';
            this.dropdown.style.top = '';
            this.dropdown.style.width = '';
            this.dropdown.style.right = '';
            this.dropdown.style.bottom = '';
            this.dropdown.style.maxHeight = '';

            // Voltar para o local original
            if (this.dropdownOriginalParent) {
                if (this.dropdownOriginalNextSibling && this.dropdownOriginalNextSibling.parentNode === this.dropdownOriginalParent) {
                    this.dropdownOriginalParent.insertBefore(this.dropdown, this.dropdownOriginalNextSibling);
                } else {
                    this.dropdownOriginalParent.appendChild(this.dropdown);
                }
            }

            this.isPortalMounted = false;
            this.dropdownOriginalParent = null;
            this.dropdownOriginalNextSibling = null;
        }

        addPortalListeners() {
            if (this._portalListenersAttached) return;
            this._portalListenersAttached = true;

            // scroll em capture para pegar scroll dentro de containers (ex: overflow-x-auto)
            window.addEventListener('scroll', this._boundUpdateDropdownPosition, true);
            window.addEventListener('resize', this._boundUpdateDropdownPosition, { passive: true });
        }

        removePortalListeners() {
            if (!this._portalListenersAttached) return;
            this._portalListenersAttached = false;

            window.removeEventListener('scroll', this._boundUpdateDropdownPosition, true);
            window.removeEventListener('resize', this._boundUpdateDropdownPosition);
        }

        updateDropdownPosition() {
            if (!this.isOpen) return;
            if (!this.isPortalMounted) return;
            if (!this.wrapper || !this.dropdown) return;

            const rect = this.wrapper.getBoundingClientRect();

            // Posicionamento básico (fixed)
            this.dropdown.style.left = `${rect.left}px`;
            this.dropdown.style.width = `${rect.width}px`;
            this.dropdown.style.right = 'auto';

            // Escolher abrir pra baixo ou pra cima se faltar espaço
            const viewportH = window.innerHeight || document.documentElement.clientHeight || 0;
            const margin = 8;

            // Se ainda não tiver altura (primeiro frame), posiciona pra baixo e tenta novamente no próximo frame
            const dropdownH = this.dropdown.offsetHeight || 0;
            const spaceBelow = viewportH - rect.bottom - margin;
            const spaceAbove = rect.top - margin;

            const shouldOpenUp = dropdownH > 0 && dropdownH > spaceBelow && spaceAbove > spaceBelow;
            if (shouldOpenUp) {
                const top = Math.max(margin, rect.top - dropdownH);
                this.dropdown.style.top = `${top}px`;
                this.dropdown.style.bottom = 'auto';
            } else {
                const top = Math.min(viewportH - margin, rect.bottom);
                this.dropdown.style.top = `${top}px`;
                this.dropdown.style.bottom = 'auto';
            }

            if (dropdownH === 0) {
                requestAnimationFrame(() => this.updateDropdownPosition());
            }
        }

        loadOptions() {
            this.allOptions = [];
            this.groups = [];

            // Verificar se tem optgroups
            const optgroups = this.select.querySelectorAll('optgroup');

            if (optgroups.length > 0) {
                // Modo agrupado
                optgroups.forEach((optgroup, groupIndex) => {
                    const group = {
                        label: optgroup.label,
                        options: []
                    };

                    optgroup.querySelectorAll('option').forEach((option) => {
                        if (option.value !== '') {
                            const opt = {
                                value: option.value,
                                text: option.textContent,
                                displayText: option.dataset.displayText || null,
                                element: option,
                                groupIndex: groupIndex
                            };
                            group.options.push(opt);
                            this.allOptions.push(opt);
                        }
                    });

                    if (group.options.length > 0) {
                        this.groups.push(group);
                    }
                });
            } else {
                // Modo flat (sem grupos)
                const options = this.select.querySelectorAll('option');
                options.forEach((option, index) => {
                    if (option.value !== '') {
                        this.allOptions.push({
                            value: option.value,
                            text: option.textContent,
                            displayText: option.dataset.displayText || null,
                            element: option,
                            index: index
                        });
                    }
                });
            }

            this.filteredOptions = [...this.allOptions];
            this.filteredGroups = this.groups.length > 0 ? JSON.parse(JSON.stringify(this.groups)) : null;

            if (this.options.type === 'normal') {
                this.renderOptions();
            }
        }

        /**
         * Recarrega as opções do select original e sincroniza o estado
         * Útil quando opções são adicionadas dinamicamente após inicialização
         */
        refresh() {
            this.loadOptions();
            this.selectedValue = this.select.value;
            this.selectedValues = this.getSelectedValues();
            this.updateDisplay();
        }

        getSelectedValues() {
            return Array.from(this.select.selectedOptions || [])
                .map(option => option.value)
                .filter(value => value !== '');
        }

        hasSelection() {
            return this.isMultiple ? this.selectedValues.length > 0 : !!this.selectedValue;
        }

        findOptionElement(value) {
            return Array.from(this.select.options).find(option => option.value === String(value)) || null;
        }

        ensureOption(value, text = null) {
            let option = this.findOptionElement(value);
            if (!option && text) {
                option = document.createElement('option');
                option.value = value;
                option.textContent = text;
                this.select.appendChild(option);
            }
            return option;
        }

        renderOptions() {
            this.optionsContainer.innerHTML = '';

            // Server-side: mostrar mensagem de minChars apenas quando digitando (1-2 caracteres)
            // Quando searchTerm está vazio, mostra os registros do preload
            if (this.options.type === 'server-side' &&
                this.searchTerm.length > 0 &&
                this.searchTerm.length < this.options.minChars) {
                this.minCharsMsg.style.display = 'block';
                this.noResults.style.display = 'none';
                this.loading.style.display = 'none';
                return;
            }

            this.minCharsMsg.style.display = 'none';

            if (this.filteredOptions.length === 0) {
                this.noResults.style.display = 'block';
                this.loading.style.display = 'none';
                return;
            }

            this.noResults.style.display = 'none';
            this.loading.style.display = 'none';

            // Verificar se tem grupos para renderizar
            if (this.filteredGroups && this.filteredGroups.length > 0) {
                // Renderizar com grupos (optgroup)
                this.filteredGroups.forEach(group => {
                    if (group.options.length === 0) return;

                    // Header do grupo (nao clicavel)
                    const groupHeader = document.createElement('div');
                    groupHeader.className = 'chosen-select-optgroup';
                    groupHeader.textContent = group.label;
                    this.optionsContainer.appendChild(groupHeader);

                    // Opcoes do grupo
                    group.options.forEach(option => {
                        const optionElement = document.createElement('div');
                        optionElement.className = 'chosen-select-option chosen-select-option-grouped';
                        optionElement.textContent = option.text;
                        optionElement.dataset.value = option.value;

                        if (this.isOptionSelected(option.value)) {
                            optionElement.classList.add('chosen-select-selected');
                        }

                        optionElement.addEventListener('click', () => {
                            this.selectOption(option.value, option.text, option.displayText);
                        });

                        this.optionsContainer.appendChild(optionElement);
                    });
                });
            } else {
                // Renderizar flat (sem grupos)
                this.filteredOptions.forEach((option, index) => {
                    const optionElement = document.createElement('div');
                    optionElement.className = 'chosen-select-option';
                    optionElement.textContent = option.text;
                    optionElement.dataset.value = option.value;

                        if (this.isOptionSelected(option.value)) {
                            optionElement.classList.add('chosen-select-selected');
                        }

                    optionElement.addEventListener('click', () => {
                        this.selectOption(option.value, option.text, option.displayText);
                    });

                    this.optionsContainer.appendChild(optionElement);
                });
            }

            this.highlightedIndex = -1;
        }

        filterOptions(searchTerm) {
            this.searchTerm = normalizeText(searchTerm.trim());

            if (this.options.type === 'server-side') {
                // Se digitando menos que minChars, mostrar mensagem (não buscar)
                // Mas se campo estiver vazio, recarregar preload
                if (this.searchTerm.length > 0 && this.searchTerm.length < this.options.minChars) {
                    this.filteredOptions = [];
                    this.filteredGroups = null;
                    this.renderOptions();
                    return;
                }

                // Busca server-side (vazio = preload, >= minChars = busca filtrada)
                this.performServerSearch(this.searchTerm);
            } else {
                // Busca normal (client-side)
                this.filteredOptions = this.allOptions.filter(option => {
                    return normalizeText(option.text).includes(this.searchTerm);
                });

                // Se tiver grupos, filtrar mantendo estrutura
                if (this.groups && this.groups.length > 0) {
                    this.filteredGroups = this.groups.map(group => ({
                        label: group.label,
                        options: group.options.filter(opt =>
                            normalizeText(opt.text).includes(this.searchTerm)
                        )
                    })).filter(group => group.options.length > 0);
                }

                this.renderOptions();
            }
        }

        isOptionSelected(value) {
            return this.isMultiple
                ? this.selectedValues.includes(String(value))
                : String(value) === String(this.selectedValue);
        }

        async performServerSearch(searchTerm) {
            if (!this.options.searchUrl) {
                console.warn('ChosenSelect: searchUrl não configurada para server-side');
                this.filteredOptions = [];
                this.renderOptions();
                return;
            }

            this.loading.style.display = 'block';
            this.noResults.style.display = 'none';
            this.minCharsMsg.style.display = 'none';

            try {
                const result = await API.get(this.options.searchUrl, { q: searchTerm });

                // Suporta resposta como array direto ou objeto {success, data}
                const data = result.data || result;

                // Espera-se que a resposta seja um array de objetos {value, text}
                // ou um array de strings
                this.filteredOptions = Array.isArray(data) ? data.map((item, index) => {
                    if (typeof item === 'string') {
                        return {
                            value: item,
                            text: item,
                            element: null,
                            index: index
                        };
                    }
                    return {
                        value: item.value || item.id || index,
                        text: item.text || item.name || item.nome || item.label || String(item),
                        element: null,
                        index: index
                    };
                }) : [];

                this.renderOptions();
            } catch (error) {
                console.error('Erro na busca server-side:', error);
                this.filteredOptions = [];
                this.renderOptions();
            } finally {
                this.loading.style.display = 'none';
            }
        }

        selectOption(value, text = null, displayText = null) {
            const option = this.ensureOption(value, text);

            if (this.isMultiple) {
                if (option) {
                    option.selected = !option.selected;
                }
                this.selectedValues = this.getSelectedValues();
                this.updateDisplay();
            } else {
                this.selectedValue = value;
                this.select.value = value;
                this.updateDisplay(displayText || text || null);
            }

            // Mostrar botão limpar
            if (this.options.allowClear && this.clearButton) {
                this.clearButton.style.display = this.hasSelection() ? 'block' : 'none';
            }

            // Disparar evento change no select original
            const event = new Event('change', { bubbles: true });
            this.select.dispatchEvent(event);

            if (this.isMultiple) {
                this.renderOptions();
            } else {
                this.close();
            }
        }

        clear() {
            if (this.isMultiple) {
                Array.from(this.select.options).forEach(option => {
                    option.selected = false;
                });
                this.selectedValues = [];
            } else {
                this.selectedValue = '';
                this.select.value = '';
            }
            this.updateDisplay();

            // Esconder botão limpar
            if (this.clearButton) {
                this.clearButton.style.display = 'none';
            }

            // Disparar evento change no select original
            const event = new Event('change', { bubbles: true });
            this.select.dispatchEvent(event);
        }

        updateDisplay(overrideText = null) {
            if (!this.display) return;

            if (!this.isMultiple) {
                this.display.textContent = overrideText || this.getDisplayText();
                return;
            }

            this.display.innerHTML = '';
            if (this.selectedValues.length === 0) {
                const placeholder = document.createElement('span');
                placeholder.className = 'chosen-select-placeholder';
                placeholder.textContent = this.options.placeholder;
                this.display.appendChild(placeholder);
                return;
            }

            this.selectedValues.forEach(value => {
                const option = this.findOptionElement(value);
                const chip = document.createElement('span');
                chip.className = 'chosen-select-chip';
                const chipText = document.createElement('span');
                chipText.className = 'chosen-select-chip-text';
                chipText.textContent = option ? (option.dataset.displayText || option.textContent) : value;

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'chosen-select-chip-remove';
                remove.textContent = 'x';
                remove.setAttribute('aria-label', 'Remover seleção');
                remove.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.removeSelectedValue(value);
                });

                chip.appendChild(chipText);
                chip.appendChild(remove);
                this.display.appendChild(chip);
            });
        }

        removeSelectedValue(value) {
            if (!this.isMultiple) return;

            const option = this.findOptionElement(value);
            if (option) {
                option.selected = false;
            }
            this.selectedValues = this.getSelectedValues();
            this.updateDisplay();
            if (this.options.allowClear && this.clearButton) {
                this.clearButton.style.display = this.hasSelection() ? 'block' : 'none';
            }
            this.renderOptions();

            const event = new Event('change', { bubbles: true });
            this.select.dispatchEvent(event);
        }

        getDisplayText() {
            if (!this.selectedValue) {
                return this.options.placeholder;
            }

            const selectedOption = this.allOptions.find(opt => opt.value === this.selectedValue);
            if (selectedOption) {
                return selectedOption.displayText || selectedOption.text;
            }

            // Se não encontrou nas opções carregadas, buscar no select original
            const option = this.select.querySelector(`option[value="${this.selectedValue}"]`);
            if (option) {
                return option.dataset.displayText || option.textContent;
            }
            return this.options.placeholder;
        }

        open() {
            if (this.isOpen) return;

            this.isOpen = true;
            this.wrapper.classList.add('chosen-select-open');
            this.dropdown.classList.add('chosen-select-open');
            this.display.setAttribute('aria-expanded', 'true');

            // Portal: move dropdown para o body para evitar corte por overflow do container
            this.mountDropdownToBody();
            this.updateDropdownPosition();

            // Recarregar opções do select original (carregamento sob demanda)
            this.loadOptions();

            // Focar no campo de busca
            setTimeout(() => {
                this.searchInput.focus();
            }, 10);

            // Renderizar opções
            if (this.options.type === 'normal') {
                this.renderOptions();
            } else if (this.options.type === 'server-side') {
                // Server-side: carregar 50 registros iniciais (preload)
                this.searchTerm = '';
                this.performServerSearch('');
            }

            // Fechar outros selects abertos
            this.closeOtherSelects();

            // Garantir reposicionamento após render/paint
            requestAnimationFrame(() => this.updateDropdownPosition());
        }

        close() {
            if (!this.isOpen) return;

            this.isOpen = false;
            this.wrapper.classList.remove('chosen-select-open');
            this.dropdown.classList.remove('chosen-select-open');
            this.display.setAttribute('aria-expanded', 'false');
            this.searchInput.value = '';
            this.searchTerm = '';
            this.highlightedIndex = -1;

            // Voltar dropdown para dentro do container
            this.unmountDropdownFromBody();
        }

        closeOtherSelects() {
            document.querySelectorAll('.chosen-select-wrapper.chosen-select-open').forEach(wrapper => {
                if (wrapper !== this.wrapper) {
                    const container = wrapper.closest('.chosen-select-container');
                    if (container && container.chosenSelect) {
                        container.chosenSelect.close();
                    }
                }
            });
        }

        attachEvents() {
            // Click no display
            this.display.addEventListener('click', (e) => {
                e.stopPropagation();
                if (this.isOpen) {
                    this.close();
                } else {
                    this.open();
                }
            });

            // Click no botão limpar
            if (this.clearButton) {
                this.clearButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.clear();
                });
            }

            // Busca
            this.searchInput.addEventListener('input', (e) => {
                this.filterOptions(e.target.value);
            });

            // Navegação com teclado
            this.searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.highlightNext();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.highlightPrevious();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    // Enter desativado - usuário deve clicar para selecionar
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.close();
                }
            });

            // Click fora para fechar
            document.addEventListener('click', (e) => {
                const target = e.target;
                if (this.container.contains(target)) return;
                // Quando em portal, o dropdown está fora do container
                if (this.isPortalMounted && this.dropdown && this.dropdown.contains(target)) return;
                this.close();
            });

            // Atualizar quando o select original mudar
            this.select.addEventListener('change', () => {
                this.selectedValue = this.select.value;
                this.selectedValues = this.getSelectedValues();
                this.updateDisplay();
                if (this.clearButton) {
                    this.clearButton.style.display = this.hasSelection() ? 'block' : 'none';
                }
            });
        }

        highlightNext() {
            if (this.filteredOptions.length === 0) return;

            this.highlightedIndex = (this.highlightedIndex + 1) % this.filteredOptions.length;
            this.updateHighlight();
        }

        highlightPrevious() {
            if (this.filteredOptions.length === 0) return;

            this.highlightedIndex = this.highlightedIndex <= 0
                ? this.filteredOptions.length - 1
                : this.highlightedIndex - 1;
            this.updateHighlight();
        }

        updateHighlight() {
            const options = this.optionsContainer.querySelectorAll('.chosen-select-option');
            options.forEach((opt, index) => {
                opt.classList.remove('chosen-select-highlighted');
                if (index === this.highlightedIndex) {
                    opt.classList.add('chosen-select-highlighted');
                    opt.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            });
        }

        selectHighlighted() {
            if (this.highlightedIndex >= 0 && this.highlightedIndex < this.filteredOptions.length) {
                const option = this.filteredOptions[this.highlightedIndex];
                this.selectOption(option.value);
            }
        }

        destroy() {
            this.close();
            this.select.style.display = '';
            this.unmountDropdownFromBody();
            if (this.container && this.container.parentNode) {
                this.container.parentNode.removeChild(this.container);
            }
        }
    }

    // Inicialização automática
    function initChosenSelects() {
        document.querySelectorAll('select.chosen-select').forEach(select => {
            // Pular se já foi inicializado
            if (select.chosenSelect) {
                return;
            }

            const type = select.dataset.chosenType || 'normal';
            const options = {
                type: type,
                minChars: parseInt(select.dataset.chosenMinChars) || 3,
                searchUrl: select.dataset.chosenSearchUrl || null,
                placeholder: select.dataset.chosenPlaceholder || 'Selecione uma opção...',
                noResultsText: select.dataset.chosenNoResults || 'Nenhum resultado encontrado',
                minCharsText: select.dataset.chosenMinCharsText || 'Digite pelo menos {min} letras para buscar...',
                allowClear: select.dataset.chosenAllowClear !== 'false'
            };

            const chosenSelect = new ChosenSelect(select, options);
            select.chosenSelect = chosenSelect;
            // Armazenar referência no container se ele existir
            if (chosenSelect.container) {
                chosenSelect.container.chosenSelect = chosenSelect;
            }
        });
    }

    // Inicializar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChosenSelects);
    } else {
        initChosenSelects();
    }

    // Expor globalmente
    window.ChosenSelect = ChosenSelect;
    window.initChosenSelects = initChosenSelects;

})();
