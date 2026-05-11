/**
 * Chosen Select - Sistema de selects com busca
 * Suporta dois tipos:
 * 1. Normal: Mostra todos os registros
 * 2. Server-Side: Requer pelo menos 3 letras para buscar
 */

(function() {
    'use strict';

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
            this.selectedValue = this.select.value;
            
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
            this.display.textContent = this.getDisplayText();
            this.display.setAttribute('role', 'combobox');
            this.display.setAttribute('aria-expanded', 'false');
            
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
            this.container.appendChild(this.wrapper);
            this.container.appendChild(this.dropdown);
            
            // Inserir após o select original
            this.select.parentNode.insertBefore(this.container, this.select.nextSibling);
        }

        loadOptions() {
            this.allOptions = [];
            const options = this.select.querySelectorAll('option');
            
            options.forEach((option, index) => {
                if (option.value !== '') {
                    this.allOptions.push({
                        value: option.value,
                        text: option.textContent,
                        element: option,
                        index: index
                    });
                }
            });
            
            this.filteredOptions = [...this.allOptions];
            
            if (this.options.type === 'normal') {
                this.renderOptions();
            }
        }

        renderOptions() {
            this.optionsContainer.innerHTML = '';
            
            if (this.options.type === 'server-side' && this.searchTerm.length < this.options.minChars) {
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
            
            this.filteredOptions.forEach((option, index) => {
                const optionElement = document.createElement('div');
                optionElement.className = 'chosen-select-option';
                optionElement.textContent = option.text;
                optionElement.dataset.value = option.value;
                
                if (option.value === this.selectedValue) {
                    optionElement.classList.add('chosen-select-selected');
                }
                
                optionElement.addEventListener('click', () => {
                    this.selectOption(option.value);
                });
                
                this.optionsContainer.appendChild(optionElement);
            });
            
            this.highlightedIndex = -1;
        }

        filterOptions(searchTerm) {
            this.searchTerm = searchTerm.toLowerCase().trim();
            
            if (this.options.type === 'server-side') {
                if (this.searchTerm.length < this.options.minChars) {
                    this.filteredOptions = [];
                    this.renderOptions();
                    return;
                }
                
                // Busca server-side
                this.performServerSearch(this.searchTerm);
            } else {
                // Busca normal (client-side)
                this.filteredOptions = this.allOptions.filter(option => {
                    return option.text.toLowerCase().includes(this.searchTerm);
                });
                this.renderOptions();
            }
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
                const url = new URL(this.options.searchUrl, window.location.origin);
                url.searchParams.append('q', searchTerm);
                
                const response = await fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Erro na busca');
                }
                
                const data = await response.json();
                
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
                        text: item.text || item.name || item.label || String(item),
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

        selectOption(value) {
            this.selectedValue = value;
            this.select.value = value;
            this.display.textContent = this.getDisplayText();
            
            // Disparar evento change no select original
            const event = new Event('change', { bubbles: true });
            this.select.dispatchEvent(event);
            
            this.close();
        }

        getDisplayText() {
            if (!this.selectedValue) {
                return this.options.placeholder;
            }
            
            const selectedOption = this.allOptions.find(opt => opt.value === this.selectedValue);
            if (selectedOption) {
                return selectedOption.text;
            }
            
            // Se não encontrou nas opções carregadas, buscar no select original
            const option = this.select.querySelector(`option[value="${this.selectedValue}"]`);
            return option ? option.textContent : this.options.placeholder;
        }

        open() {
            if (this.isOpen) return;
            
            this.isOpen = true;
            this.wrapper.classList.add('chosen-select-open');
            this.dropdown.classList.add('chosen-select-open');
            this.display.setAttribute('aria-expanded', 'true');
            
            // Focar no campo de busca
            setTimeout(() => {
                this.searchInput.focus();
            }, 10);
            
            // Renderizar opções se for normal
            if (this.options.type === 'normal') {
                this.renderOptions();
            }
            
            // Fechar outros selects abertos
            this.closeOtherSelects();
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
                    this.selectHighlighted();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.close();
                }
            });
            
            // Click fora para fechar
            document.addEventListener('click', (e) => {
                if (!this.container.contains(e.target)) {
                    this.close();
                }
            });
            
            // Atualizar quando o select original mudar
            this.select.addEventListener('change', () => {
                this.selectedValue = this.select.value;
                this.display.textContent = this.getDisplayText();
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
            if (this.container && this.container.parentNode) {
                this.container.parentNode.removeChild(this.container);
            }
        }
    }

    // Inicialização automática
    function initChosenSelects() {
        document.querySelectorAll('select.chosen-select').forEach(select => {
            const type = select.dataset.chosenType || 'normal';
            const options = {
                type: type,
                minChars: parseInt(select.dataset.chosenMinChars) || 3,
                searchUrl: select.dataset.chosenSearchUrl || null,
                placeholder: select.dataset.chosenPlaceholder || 'Selecione uma opção...',
                noResultsText: select.dataset.chosenNoResults || 'Nenhum resultado encontrado',
                minCharsText: select.dataset.chosenMinCharsText || 'Digite pelo menos {min} letras para buscar...'
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
