/**
 * FormValidator - Sistema global de validação de formulários com suporte a abas
 *
 * Funcionalidades:
 * - Intercepta submit de todos os formulários automaticamente
 * - Detecta campos inválidos (required, email, pattern, etc.)
 * - Agrupa erros por aba (.form-tab-content)
 * - Exibe modal customizado com lista de erros
 * - Navega para primeira aba com erro ao fechar modal
 * - Destaca campos com erro (borda vermelha)
 * - Remove destaque quando campo é preenchido
 *
 * Opt-out: adicionar atributo data-no-validation no formulário
 */
window.FormValidator = (function () {
    'use strict';

    // =========================================
    // CONFIGURAÇÕES
    // =========================================
    const CONFIG = {
        selectors: {
            tabButton: '.form-tab-button',
            tabContent: '.form-tab-content',
            tabTargetAttr: 'data-form-tab-target'
        },
        classes: {
            fieldError: 'field-validation-error',
            labelError: 'label-validation-error',
            tabError: 'tab-has-errors'
        },
        modalId: 'formValidationModal'
    };

    // =========================================
    // ESTADO
    // =========================================
    let initialized = false;

    // =========================================
    // FUNÇÕES UTILITÁRIAS
    // =========================================

    /**
     * Encontra o .form-tab-content pai de um campo
     */
    function findParentTab(field) {
        return field.closest(CONFIG.selectors.tabContent);
    }

    /**
     * Encontra o botão da aba correspondente a um .form-tab-content
     */
    function findTabButton(tabContent) {
        if (!tabContent || !tabContent.id) return null;

        // Buscar em todo o documento (abas podem estar fora do form)
        return document.querySelector(
            `${CONFIG.selectors.tabButton}[${CONFIG.selectors.tabTargetAttr}="#${tabContent.id}"]`
        );
    }

    /**
     * Obtém o nome do campo a partir do label ou placeholder
     */
    function getFieldLabel(field) {
        const form = field.closest('form') || document;

        // 1. Label com for="id"
        if (field.id) {
            const label = form.querySelector(`label[for="${field.id}"]`);
            if (label) {
                return label.textContent.replace(/\s*\*\s*/g, '').trim();
            }
        }

        // 2. Label dentro do mesmo .form-input-group
        const inputGroup = field.closest('.form-input-group');
        if (inputGroup) {
            const label = inputGroup.querySelector('.form-label-group, label');
            if (label) {
                return label.textContent.replace(/\s*\*\s*/g, '').trim();
            }
        }

        // 3. Label pai (wrapper)
        const parentLabel = field.closest('label');
        if (parentLabel) {
            const clone = parentLabel.cloneNode(true);
            const inputs = clone.querySelectorAll('input, select, textarea');
            inputs.forEach(el => el.remove());
            return clone.textContent.replace(/\s*\*\s*/g, '').trim();
        }

        // 4. Label sibling anterior
        const prevSibling = field.previousElementSibling;
        if (prevSibling && prevSibling.tagName === 'LABEL') {
            return prevSibling.textContent.replace(/\s*\*\s*/g, '').trim();
        }

        // 5. Placeholder como fallback
        if (field.placeholder) {
            return field.placeholder;
        }

        // 6. Name como último recurso
        return field.name || field.id || 'Campo não identificado';
    }

    /**
     * Obtém o nome da aba a partir do botão
     */
    function getTabName(tabContent) {
        const button = findTabButton(tabContent);
        if (button) {
            return button.textContent.trim();
        }
        return 'Aba Principal';
    }

    // =========================================
    // VALIDAÇÃO
    // =========================================

    /**
     * Coleta todos os campos inválidos de um formulário
     */
    function getInvalidFields(form) {
        const invalidFields = [];

        // Selecionar todos os campos que podem ser validados
        const fields = form.querySelectorAll(
            'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), ' +
            'select, ' +
            'textarea'
        );

        fields.forEach(field => {
            // Pular campos desabilitados
            if (field.disabled) return;

            // Usar Constraint Validation API
            if (!field.checkValidity()) {
                invalidFields.push({
                    element: field,
                    message: field.validationMessage || 'Campo inválido',
                    label: getFieldLabel(field),
                    tab: findParentTab(field)
                });
            }
        });

        return invalidFields;
    }

    /**
     * Agrupa campos inválidos por aba
     */
    function groupByTab(invalidFields) {
        const groups = new Map();

        invalidFields.forEach(field => {
            const tabKey = field.tab ? field.tab.id : '_no_tab';
            const tabName = field.tab ? getTabName(field.tab) : 'Campos Gerais';

            if (!groups.has(tabKey)) {
                groups.set(tabKey, {
                    name: tabName,
                    tabContent: field.tab,
                    fields: []
                });
            }

            groups.get(tabKey).fields.push(field);
        });

        return groups;
    }

    // =========================================
    // DESTAQUE DE CAMPOS
    // =========================================

    /**
     * Adiciona destaque de erro a um campo
     */
    function highlightField(field) {
        const element = field.element;

        // Adicionar classe ao campo
        element.classList.add(CONFIG.classes.fieldError);

        // Adicionar classe ao .form-input-group pai (se existir)
        const inputGroup = element.closest('.form-input-group');
        if (inputGroup) {
            inputGroup.classList.add(CONFIG.classes.fieldError);
        }

        // Adicionar classe ao label
        const form = element.closest('form') || document;
        if (element.id) {
            const label = form.querySelector(`label[for="${element.id}"]`);
            if (label) {
                label.classList.add(CONFIG.classes.labelError);
            }
        }

        // Adicionar indicador de erro na aba
        if (field.tab) {
            const tabButton = findTabButton(field.tab);
            if (tabButton) {
                tabButton.classList.add(CONFIG.classes.tabError);
            }
        }
    }

    /**
     * Remove destaque de erro de um campo
     */
    function unhighlightField(element) {
        // Remover classe do campo
        element.classList.remove(CONFIG.classes.fieldError);

        // Remover classe do .form-input-group pai
        const inputGroup = element.closest('.form-input-group');
        if (inputGroup) {
            inputGroup.classList.remove(CONFIG.classes.fieldError);
        }

        // Remover classe do label
        const form = element.closest('form') || document;
        if (element.id) {
            const label = form.querySelector(`label[for="${element.id}"]`);
            if (label) {
                label.classList.remove(CONFIG.classes.labelError);
            }
        }

        // Verificar se ainda há erros na aba
        const tabContent = findParentTab(element);
        if (tabContent) {
            const stillHasErrors = tabContent.querySelector('.' + CONFIG.classes.fieldError);
            if (!stillHasErrors) {
                const tabButton = findTabButton(tabContent);
                if (tabButton) {
                    tabButton.classList.remove(CONFIG.classes.tabError);
                }
            }
        }
    }

    /**
     * Limpa todos os destaques de um formulário
     */
    function clearAllHighlights(form) {
        // Remover classes dos campos
        form.querySelectorAll('.' + CONFIG.classes.fieldError).forEach(el => {
            el.classList.remove(CONFIG.classes.fieldError);
        });

        // Remover classes dos labels
        form.querySelectorAll('.' + CONFIG.classes.labelError).forEach(el => {
            el.classList.remove(CONFIG.classes.labelError);
        });

        // Remover classes das abas
        const container = form.closest('body') || form.parentElement;
        if (container) {
            container.querySelectorAll(CONFIG.selectors.tabButton + '.' + CONFIG.classes.tabError).forEach(btn => {
                btn.classList.remove(CONFIG.classes.tabError);
            });
        }
    }

    /**
     * Configura listeners para remover destaque ao preencher
     */
    function setupFieldListeners(invalidFields) {
        invalidFields.forEach(field => {
            const element = field.element;

            // Função handler
            const handler = function (event) {
                const el = event.target;

                // Se o campo agora é válido, remover destaque
                if (el.checkValidity()) {
                    unhighlightField(el);

                    // Remover listeners
                    el.removeEventListener('input', handler);
                    el.removeEventListener('change', handler);
                }
            };

            // Adicionar listeners
            element.addEventListener('input', handler);
            element.addEventListener('change', handler);
        });
    }

    // =========================================
    // MODAL (via postMessage para parent)
    // =========================================

    /**
     * Exibe o modal com os erros via postMessage para o parent
     */
    function showModal(groups, firstTabWithError, form) {
        // Montar lista de erros para enviar ao parent
        const errors = [];
        groups.forEach((group) => {
            errors.push({
                tabName: group.name,
                tabId: group.tabContent?.id || null,
                fields: group.fields.map(f => `${f.label}: ${f.message}`)
            });
        });

        // Guardar referência para quando o modal fechar
        window._validationFormRef = form;
        window._validationFirstTab = firstTabWithError;

        // Enviar para parent abrir o modal
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openValidationModal',
                errors: errors
            }, '*');
        }
    }

    /**
     * Handler para quando o modal for fechado pelo parent
     */
    function handleModalClosed() {
        const form = window._validationFormRef;
        const firstTab = window._validationFirstTab;

        if (firstTab && firstTab.tabContent) {
            navigateToTab(firstTab.tabContent, form);

            // Focar no primeiro campo com erro dessa aba
            const firstField = firstTab.fields[0];
            if (firstField?.element) {
                setTimeout(() => {
                    firstField.element.focus();
                    firstField.element.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 150);
            }
        } else if (firstTab && firstTab.fields?.length > 0) {
            // Se não tem aba, apenas focar no primeiro campo
            const firstField = firstTab.fields[0];
            if (firstField?.element) {
                setTimeout(() => {
                    firstField.element.focus();
                    firstField.element.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 150);
            }
        }

        // Limpar referências
        window._validationFormRef = null;
        window._validationFirstTab = null;
    }

    /**
     * Navega para uma aba específica
     */
    function navigateToTab(tabContent, form) {
        if (!tabContent || !tabContent.id) return;

        const tabButton = findTabButton(tabContent);
        if (!tabButton) return;

        // Encontrar container das abas (pode estar fora do form)
        const container = form.closest('body') || form.parentElement;

        // Remover active de todas as abas
        const allTabButtons = container.querySelectorAll(CONFIG.selectors.tabButton);
        const allTabContents = container.querySelectorAll(CONFIG.selectors.tabContent);

        allTabButtons.forEach(btn => btn.classList.remove('active'));
        allTabContents.forEach(content => content.classList.remove('active'));

        // Ativar a aba desejada
        tabButton.classList.add('active');
        tabContent.classList.add('active');
    }

    // =========================================
    // INICIALIZAÇÃO
    // =========================================

    /**
     * Handler para o evento submit
     */
    function handleFormSubmit(event) {
        const form = event.target;

        // Verificar se é um formulário
        if (form.tagName !== 'FORM') return;

        // Ignorar formulários com atributo data-no-validation
        if (form.hasAttribute('data-no-validation')) {
            return;
        }

        // Limpar destaques anteriores
        clearAllHighlights(form);

        // Coletar campos inválidos
        const invalidFields = getInvalidFields(form);

        // Se não há campos inválidos, permitir submit
        if (invalidFields.length === 0) {
            return;
        }

        // Prevenir submit
        event.preventDefault();
        event.stopPropagation();

        // Agrupar por aba
        const groups = groupByTab(invalidFields);

        // Destacar campos
        invalidFields.forEach(field => highlightField(field));

        // Configurar listeners para remover destaque
        setupFieldListeners(invalidFields);

        // Encontrar primeira aba com erro
        let firstTabWithError = null;
        for (const [, group] of groups) {
            firstTabWithError = group;
            break;
        }

        // Exibir modal
        showModal(groups, firstTabWithError, form);
    }

    /**
     * Inicializa o validador em todos os formulários
     */
    function init() {
        // Evitar dupla inicialização
        if (initialized) return;
        initialized = true;

        // Desativar validação nativa em todos os formulários existentes
        // Isso evita o erro "An invalid form control is not focusable"
        document.querySelectorAll('form:not([data-no-validation])').forEach(form => {
            form.setAttribute('novalidate', '');
        });

        // Observer para formulários criados dinamicamente
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1) { // Element node
                        if (node.tagName === 'FORM' && !node.hasAttribute('data-no-validation')) {
                            node.setAttribute('novalidate', '');
                        }
                        // Também verificar formulários filhos
                        node.querySelectorAll?.('form:not([data-no-validation]):not([novalidate])').forEach(form => {
                            form.setAttribute('novalidate', '');
                        });
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // Usar event delegation no document para capturar todos os submits
        document.addEventListener('submit', handleFormSubmit, true);

        // Bloquear Enter em formulários (exceto textareas)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                if (e.target.closest('form')) {
                    e.preventDefault();
                }
            }
        }, true);

        // Listener para quando o modal for fechado pelo parent
        window.addEventListener('message', function (event) {
            if (event.data && event.data.action === 'validationModalClosed') {
                handleModalClosed();
            }
        });
    }

    /**
     * Valida um formulário manualmente
     */
    function validate(form) {
        const invalidFields = getInvalidFields(form);
        return invalidFields.length === 0;
    }

    // =========================================
    // API PÚBLICA
    // =========================================
    return {
        init: init,
        validate: validate,
        clearErrors: clearAllHighlights,
        CONFIG: CONFIG
    };
})();

// Auto-inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function () {
    window.FormValidator.init();
});
