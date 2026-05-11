/**
 * Template Variables Selector
 *
 * Componente para exibir e inserir variáveis de template
 * Funciona tanto com TinyMCE quanto com textarea simples
 */

// Guard para evitar redeclaração
if (typeof window.VariableSelector === 'undefined') {

    class VariableSelector {
        /**
         * Cria uma nova instância do seletor de variáveis
         *
         * @param {string} containerId - ID do elemento container
         * @param {string} editorId - ID do editor/textarea alvo
         * @param {string} editorType - Tipo do editor: 'tinymce' ou 'textarea'
         */
        constructor(containerId, editorId, editorType = 'tinymce') {
            this.container = document.getElementById(containerId);
            this.editorId = editorId;
            this.editorType = editorType;
            this.variables = {};
            this.collapsedGroups = new Set();
        }

        /**
         * Carrega variáveis da API
         *
         * @param {string} typeSlug - Slug do tipo de template
         * @param {string} locale - Locale para labels (opcional)
         */
        async loadVariables(typeSlug, locale = 'pt_BR') {
            try {
                const result = await API.get(`/api/templates/variables/${typeSlug}?locale=${locale}`);
                if (result.success) {
                    this.variables = result.data;
                    this.render();
                }
            } catch (error) {
                console.error('Erro ao carregar variáveis:', error);
                this.renderError('Erro ao carregar variáveis');
            }
        }

        /**
         * Define as variáveis diretamente
         *
         * @param {object} variables - Objeto com variáveis por entidade
         */
        setVariables(variables) {
            this.variables = variables;
            this.render();
        }

        /**
         * Define o tipo de editor alvo
         *
         * @param {string} type - 'tinymce' ou 'textarea'
         */
        setEditorType(type) {
            this.editorType = type;
        }

        /**
         * Renderiza o seletor de variáveis
         */
        render() {
            if (!this.container) {
                console.error('Container não encontrado');
                return;
            }

            let html = '';

            if (!this.variables || Object.keys(this.variables).length === 0) {
                html = '<p class="text-xs text-slate-500 text-center py-4">Nenhuma variável disponível</p>';
                this.container.innerHTML = html;
                return;
            }

            for (const [entity, data] of Object.entries(this.variables)) {
                const entityVars = data.variables || [];
                const isCollapsed = this.collapsedGroups.has(entity);

                html += `
                <div class="variable-group" data-entity="${entity}">
                    <div class="variable-group-header" data-toggle="${entity}">
                        <span>${this.escapeHtml(data.label || entity)}</span>
                        <i class="fas fa-chevron-${isCollapsed ? 'right' : 'down'} text-xs"></i>
                    </div>
                    <div class="variable-group-items ${isCollapsed ? 'collapsed' : ''}" id="vars-${entity}">
            `;

                entityVars.forEach(v => {
                    html += `
                    <div class="variable-item" data-variable="${this.escapeHtml(v.variable)}" title="${this.escapeHtml(v.example || '')}">
                        <code>${this.escapeHtml(v.variable)}</code>
                        <span class="variable-label">${this.escapeHtml(v.label)}</span>
                    </div>
                `;
                });

                html += `
                    </div>
                </div>
            `;
            }

            this.container.innerHTML = html;
            this.bindEvents();
        }

        /**
         * Renderiza mensagem de erro
         *
         * @param {string} message - Mensagem de erro
         */
        renderError(message) {
            if (this.container) {
                this.container.innerHTML = `<p class="text-xs text-red-500 text-center py-4">${this.escapeHtml(message)}</p>`;
            }
        }

        /**
         * Vincula eventos aos elementos
         */
        bindEvents() {
            // Toggle grupos
            this.container.querySelectorAll('.variable-group-header').forEach(header => {
                header.addEventListener('click', (e) => {
                    e.preventDefault();
                    const entity = header.dataset.toggle;
                    this.toggleGroup(entity);
                });
            });

            // Inserir variável ao clicar
            this.container.querySelectorAll('.variable-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const variable = item.dataset.variable;
                    this.insertVariable(variable);
                });
            });
        }

        /**
         * Alterna visibilidade de um grupo
         *
         * @param {string} entity - Nome da entidade
         */
        toggleGroup(entity) {
            const items = document.getElementById(`vars-${entity}`);
            const header = this.container.querySelector(`[data-toggle="${entity}"]`);

            if (!items || !header) return;

            const icon = header.querySelector('i');

            if (this.collapsedGroups.has(entity)) {
                this.collapsedGroups.delete(entity);
                items.classList.remove('collapsed');
                if (icon) {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-down');
                }
            } else {
                this.collapsedGroups.add(entity);
                items.classList.add('collapsed');
                if (icon) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-right');
                }
            }
        }

        /**
         * Expande todos os grupos
         */
        expandAll() {
            this.collapsedGroups.clear();
            this.container.querySelectorAll('.variable-group-items').forEach(items => {
                items.classList.remove('collapsed');
            });
            this.container.querySelectorAll('.variable-group-header i').forEach(icon => {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-down');
            });
        }

        /**
         * Colapsa todos os grupos
         */
        collapseAll() {
            Object.keys(this.variables).forEach(entity => {
                this.collapsedGroups.add(entity);
            });
            this.container.querySelectorAll('.variable-group-items').forEach(items => {
                items.classList.add('collapsed');
            });
            this.container.querySelectorAll('.variable-group-header i').forEach(icon => {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
            });
        }

        /**
         * Insere uma variável no editor
         *
         * @param {string} variable - Variável a inserir (ex: {{cliente.nome}})
         */
        insertVariable(variable) {
            if (this.editorType === 'tinymce') {
                this.insertInTinyMCE(variable);
            } else {
                this.insertInTextarea(variable);
            }
        }

        /**
         * Insere variável no TinyMCE
         *
         * @param {string} variable - Variável a inserir
         */
        insertInTinyMCE(variable) {
            // Tentar usar o editor ativo primeiro
            if (tinymce.activeEditor) {
                tinymce.activeEditor.insertContent(variable);
                tinymce.activeEditor.focus();
                return;
            }

            // Fallback para editor específico
            const editor = tinymce.get(this.editorId);
            if (editor) {
                editor.insertContent(variable);
                editor.focus();
            } else {
                console.warn('Editor TinyMCE não encontrado:', this.editorId);
            }
        }

        /**
         * Insere variável em um textarea simples
         *
         * @param {string} variable - Variável a inserir
         */
        insertInTextarea(variable) {
            const textarea = document.getElementById(this.editorId);
            if (!textarea) {
                console.warn('Textarea não encontrado:', this.editorId);
                return;
            }

            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;

            // Inserir na posição do cursor
            textarea.value = text.substring(0, start) + variable + text.substring(end);

            // Posicionar cursor após a variável inserida
            const newPosition = start + variable.length;
            textarea.focus();
            textarea.setSelectionRange(newPosition, newPosition);

            // Disparar evento de input para atualizar contadores etc
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }

        /**
         * Escapa HTML para prevenir XSS
         *
         * @param {string} text - Texto a escapar
         * @returns {string} Texto escapado
         */
        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Busca variáveis que correspondem a um termo
         *
         * @param {string} term - Termo de busca
         * @returns {array} Array de variáveis correspondentes
         */
        search(term) {
            if (!term || term.length < 2) {
                return [];
            }

            const results = [];
            const termLower = term.toLowerCase();

            for (const [entity, data] of Object.entries(this.variables)) {
                const entityVars = data.variables || [];

                entityVars.forEach(v => {
                    if (v.variable.toLowerCase().includes(termLower) ||
                        v.label.toLowerCase().includes(termLower)) {
                        results.push({
                            ...v,
                            entity: entity,
                            entityLabel: data.label
                        });
                    }
                });
            }

            return results;
        }

        /**
         * Retorna todas as variáveis como array flat
         *
         * @returns {array} Array de todas as variáveis
         */
        getAllVariables() {
            const all = [];

            for (const [entity, data] of Object.entries(this.variables)) {
                const entityVars = data.variables || [];

                entityVars.forEach(v => {
                    all.push({
                        ...v,
                        entity: entity,
                        entityLabel: data.label
                    });
                });
            }

            return all;
        }
    }

    // Exportar para uso global
    window.VariableSelector = VariableSelector;

} // Fim do guard
