/**
 * FormAudit - Sistema de auditoria de formulários
 *
 * Captura campos de formulários com labels legíveis, detecta contexto de abas,
 * processa arrays de itens e gera JSON pronto para o banco de dados.
 *
 * @author 7Carros
 * @version 1.0.0
 */
window.FormAudit = (function() {
    'use strict';

    // Handlers especializados por página
    const handlers = {};

    // Configurações
    const CONFIG = {
        ignoredFields: [
            'id', 'chave', 'created_at', 'updated_at', 'data_cadastro',
            'senha', 'password', 'foto', 'foto_base64', 'foto_url', '_token',
            '_audit_data', '_audit_initial', '_audit_changes'
        ],
        ignoredTypes: ['hidden', 'submit', 'button', 'reset', 'file'],
        selectors: {
            tabContent: '.form-tab-content',
            tabButton: '.form-tab-button',
            tabTargetAttr: 'data-form-tab-target',
            inputGroup: '.form-input-group',
            labelGroup: '.form-label-group',
            sectionTitle: '.form-section-title'
        }
    };

    // Estado - armazena dados iniciais por form
    const initialData = {};

    /**
     * Registra um handler especializado para uma página
     * @param {string} pageId - Identificador da página (ex: 'financeiro-adicionar')
     * @param {Object} handler - Objeto com métodos capture, captureInitial, getChanges
     */
    function registerHandler(pageId, handler) {
        handlers[pageId] = handler;
    }

    /**
     * Detecta o identificador da página para selecionar o handler
     * @param {HTMLFormElement} form
     * @returns {string} Identificador da página
     */
    function detectPageHandler(form) {
        // 1. Data attribute explícito no form (maior prioridade)
        if (form && form.dataset && form.dataset.auditHandler) {
            return form.dataset.auditHandler;
        }

        // 2. Inferir da URL
        const path = window.location.pathname;
        const match = path.match(/\/pages\/([^\/]+)\/([^\.]+)/);
        if (match) {
            return `${match[1]}-${match[2]}`; // ex: "financeiro-adicionar"
        }

        // 3. Fallback para handler default
        return 'default';
    }

    /**
     * Obtém o handler para um formulário
     * @param {HTMLFormElement} form
     * @returns {Object|null} Handler especializado ou null para usar genérico
     */
    function getHandler(form) {
        const pageId = detectPageHandler(form);
        return handlers[pageId] || null;
    }

    /**
     * Limpa texto do label removendo asteriscos, avisos e espaços extras
     */
    function cleanLabel(text) {
        return text
            .replace(/\s*\*\s*$/, '')   // Remove asterisco de obrigatório
            .replace(/\s*\?.*$/, '')    // Remove aviso (texto após ?)
            .replace(/\s+/g, ' ')       // Normaliza espaços
            .trim();
    }

    /**
     * Formata nome de campo para label legível
     * Ex: "id_cliente" -> "Cliente", "data_venci" -> "Data Venci"
     */
    function formatFieldName(name) {
        if (!name) return 'Campo';

        return name
            .replace(/\[\d+\]\[(\w+)\]$/, ' $1') // itens[0][valor] -> itens valor
            .replace(/^id_/, '')                  // Remove prefixo id_
            .replace(/_/g, ' ')                   // Underscores para espaços
            .replace(/\b\w/g, c => c.toUpperCase()); // Capitaliza palavras
    }

    /**
     * Obtém label legível de um campo
     */
    function getFieldLabel(field) {
        const form = field.closest('form') || document;

        // 0. Atributo data-label (para campos hidden gerados dinamicamente)
        if (field.dataset && field.dataset.label) {
            return field.dataset.label;
        }

        // 1. Label com for="id"
        if (field.id) {
            const label = form.querySelector(`label[for="${field.id}"]`);
            if (label) return cleanLabel(label.textContent);
        }

        // 2. .form-label-group dentro do .form-input-group
        const inputGroup = field.closest(CONFIG.selectors.inputGroup);
        if (inputGroup) {
            const label = inputGroup.querySelector(CONFIG.selectors.labelGroup + ', label');
            if (label) return cleanLabel(label.textContent);
        }

        // 3. Buscar label anterior (padrão Bootstrap/comum)
        const prevLabel = field.previousElementSibling;
        if (prevLabel && prevLabel.tagName === 'LABEL') {
            return cleanLabel(prevLabel.textContent);
        }

        // 4. Placeholder como fallback
        if (field.placeholder && field.placeholder.length > 0 && field.placeholder.length < 50) {
            return field.placeholder;
        }

        // 5. Formatar nome do campo
        return formatFieldName(field.name);
    }

    /**
     * Obtém valor formatado do campo
     */
    function getFieldValue(field) {
        // SELECT: capturar texto visível
        if (field.tagName === 'SELECT') {
            const selected = field.options[field.selectedIndex];
            if (!selected || !selected.value) return '';
            return selected.text.trim();
        }

        // CHECKBOX
        if (field.type === 'checkbox') {
            return field.checked ? 'Sim' : 'Não';
        }

        // RADIO: buscar o checked do grupo
        if (field.type === 'radio') {
            const checked = document.querySelector(`input[name="${field.name}"]:checked`);
            if (!checked) return '';

            // Tentar pegar label do radio
            const radioLabel = document.querySelector(`label[for="${checked.id}"]`);
            return radioLabel ? cleanLabel(radioLabel.textContent) : checked.value;
        }

        // INPUT-MOEDA: formatar com símbolo de moeda
        if (field.classList.contains('input-moeda')) {
            if (!field.value || field.value === '0,00' || field.value === '0.00') return '';

            if (window.Currency) {
                const valor = Currency.parse(field.value);
                return Currency.format(valor, true);
            }
            return field.value;
        }

        // INPUT DATE: formatar dd/mm/yyyy
        if (field.type === 'date' && field.value) {
            const parts = field.value.split('-');
            if (parts.length === 3) {
                return `${parts[2]}/${parts[1]}/${parts[0]}`;
            }
            return field.value;
        }

        // INPUT DATETIME-LOCAL
        if (field.type === 'datetime-local' && field.value) {
            const [date, time] = field.value.split('T');
            const [y, m, d] = date.split('-');
            return `${d}/${m}/${y} ${time}`;
        }

        return field.value || '';
    }

    /**
     * Obtém nome da aba onde o campo está
     */
    function getTabName(field) {
        const tabContent = field.closest(CONFIG.selectors.tabContent);
        if (!tabContent || !tabContent.id) return null;

        const selector = `${CONFIG.selectors.tabButton}[${CONFIG.selectors.tabTargetAttr}="#${tabContent.id}"]`;
        const tabButton = document.querySelector(selector);

        if (tabButton) {
            // Clonar e remover ícones para pegar só o texto
            const clone = tabButton.cloneNode(true);
            clone.querySelectorAll('i, svg, .icon').forEach(el => el.remove());
            const text = clone.textContent.trim();
            return text || null;
        }
        return null;
    }

    /**
     * Verifica se o campo deve ser ignorado
     */
    function shouldIgnoreField(field) {
        // Sem nome
        if (!field.name) return true;

        // Tipos ignorados (exceto hidden que fazem parte de arrays como parcelas[0][valor])
        if (CONFIG.ignoredTypes.includes(field.type)) {
            // Permitir campos hidden que são arrays (ex: parcelas[0][parcela])
            if (field.type === 'hidden' && /^\w+\[\d+\]\[\w+\]$/.test(field.name)) {
                // Continua processamento - não ignora
            } else {
                return true;
            }
        }

        // Campos na lista de ignorados
        const baseName = field.name.replace(/\[\d+\]\[\w+\]$/, '').replace(/\[\]$/, '');
        if (CONFIG.ignoredFields.includes(baseName)) return true;
        if (CONFIG.ignoredFields.includes(field.name)) return true;

        // Campos de auditoria
        if (field.name.startsWith('_audit')) return true;

        // Nota: campos disabled são capturados para auditoria mesmo que não sejam
        // enviados no submit nativo (ex: Valor Total calculado automaticamente)

        return false;
    }

    /**
     * Obtém nome da aba para um array específico
     */
    function getArrayTabName(arrayName, form) {
        // Buscar primeiro campo do array e pegar sua aba
        const firstField = form.querySelector(`[name^="${arrayName}["]`);
        if (firstField) {
            return getTabName(firstField);
        }
        return null;
    }

    /**
     * Capitaliza primeira letra de uma string
     */
    function capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * Obtém label legível de um array buscando no DOM
     * Hierarquia: form-section-title > nome da aba > capitaliza nome
     */
    function getArrayLabel(arrayName, form) {
        // Buscar primeiro campo do array
        const firstField = form.querySelector(`[name^="${arrayName}["]`);
        if (!firstField) return capitalizeFirst(arrayName);

        // 1. Buscar .form-section-title na seção pai
        const section = firstField.closest('.form-section');
        if (section) {
            const title = section.querySelector(CONFIG.selectors.sectionTitle);
            if (title) {
                // Buscar primeiro span filho (contém o título sem botões)
                const titleSpan = title.querySelector(':scope > span');
                if (titleSpan) {
                    const clone = titleSpan.cloneNode(true);
                    // Remover ícones, avisos (spans internos) e botões
                    clone.querySelectorAll('i, svg, .icon, span, button').forEach(el => el.remove());
                    const text = clone.textContent.trim();
                    if (text) return text;
                }

                // Fallback: clonar título inteiro removendo elementos extras
                const clone = title.cloneNode(true);
                clone.querySelectorAll('i, svg, .icon, span, button, div').forEach(el => el.remove());
                const text = clone.textContent.trim();
                if (text) return text;
            }
        }

        // 2. Usar nome da aba como fallback
        const tabName = getTabName(firstField);
        if (tabName) return tabName;

        // 3. Capitalizar nome do array
        return capitalizeFirst(arrayName);
    }

    /**
     * Formata nome do array para label legível
     */
    function formatArrayName(name, form) {
        return getArrayLabel(name, form);
    }

    /**
     * Processa campos de arrays (itens dinâmicos) - retorna objeto agrupado por aba
     */
    function processArrayFields(form) {
        const arrays = {};
        const DEFAULT_TAB = 'Geral';

        // Detectar campos com pattern name="array[INDEX][campo]"
        const arrayFields = form.querySelectorAll('[name*="["][name*="]"]');

        arrayFields.forEach(field => {
            if (shouldIgnoreField(field)) return;

            // Pattern: arrayName[index][fieldName]
            const match = field.name.match(/^(\w+)\[(\d+)\]\[(\w+)\]$/);
            if (!match) return;

            const [, arrayName, index] = match;

            if (!arrays[arrayName]) arrays[arrayName] = {};
            if (!arrays[arrayName][index]) arrays[arrayName][index] = {};

            const label = getFieldLabel(field);
            const value = getFieldValue(field);

            // Só adicionar se tiver valor significativo
            if (value && value !== '' && value !== 'Não') {
                arrays[arrayName][index][label] = value;
            }
        });

        // Converter para formato agrupado por aba
        const grouped = {};

        // Verificar se há parcelas reais (usado para decidir se inclui config_parcelas)
        const temParcelas = arrays['parcelas'] &&
            Object.values(arrays['parcelas']).some(item => Object.keys(item).length > 0);

        for (const [name, itemsObj] of Object.entries(arrays)) {
            // Ignorar config_parcelas se não houver parcelas reais
            if (name === 'config_parcelas' && !temParcelas) {
                continue;
            }

            const items = Object.values(itemsObj).filter(item => Object.keys(item).length > 0);

            if (items.length > 0) {
                const tabName = getArrayTabName(name, form) || DEFAULT_TAB;
                if (!grouped[tabName]) grouped[tabName] = [];

                grouped[tabName].push({
                    label: formatArrayName(name, form),
                    de: null,
                    para: items
                });
            }
        }

        return grouped;
    }

    /**
     * Captura todos os campos do formulário (retorna objeto agrupado por aba)
     */
    function capture(form) {
        // Verificar se há handler especializado
        const handler = getHandler(form);
        if (handler && typeof handler.capture === 'function') {
            return handler.capture(form);
        }

        // Handler genérico
        const grouped = {};
        const processedArrays = new Set();
        const processedRadios = new Set();
        const DEFAULT_TAB = 'Geral';

        // Verificar se há parcelas reais no formulário
        const temParcelas = form.querySelectorAll('[name^="parcelas["]').length > 0;

        const fields = form.querySelectorAll('input, select, textarea');

        fields.forEach(field => {
            if (shouldIgnoreField(field)) return;

            // Ignorar campos config_parcelas se não houver parcelas reais
            if (field.name.startsWith('config_parcelas[') && !temParcelas) {
                return;
            }

            // Verificar se é campo de array
            const arrayMatch = field.name.match(/^(\w+)\[\d+\]/);
            if (arrayMatch) {
                processedArrays.add(arrayMatch[1]);
                return; // Arrays são processados separadamente
            }

            // Radio: processar apenas uma vez por grupo
            if (field.type === 'radio') {
                if (processedRadios.has(field.name)) return;
                processedRadios.add(field.name);
            }

            const value = getFieldValue(field);

            // Não adicionar campos vazios no cadastro
            if (value === '' || value === null) return;

            const tabName = getTabName(field) || DEFAULT_TAB;
            if (!grouped[tabName]) grouped[tabName] = [];

            grouped[tabName].push({
                label: getFieldLabel(field),
                de: null,
                para: value
            });
        });

        // Adicionar arrays processados (já retorna objeto agrupado)
        const arrays = processArrayFields(form);
        for (const [tabName, items] of Object.entries(arrays)) {
            if (!grouped[tabName]) grouped[tabName] = [];
            grouped[tabName].push(...items);
        }

        return grouped;
    }

    /**
     * Normaliza valor para comparação
     */
    function normalizeValue(val) {
        if (val === null || val === undefined || val === '') return '';
        if (Array.isArray(val)) return JSON.stringify(val);
        return String(val).trim();
    }

    /**
     * Detecta alterações entre estado inicial e atual (retorna objeto agrupado por aba)
     */
    function getChanges(form) {
        // Verificar se há handler especializado
        const handler = getHandler(form);
        if (handler && typeof handler.getChanges === 'function') {
            return handler.getChanges(form, initialData[form.id] || {});
        }

        // Handler genérico
        const current = capture(form);
        const initial = initialData[form.id] || {};
        const changes = {};

        // Obter todas as abas (inicial + atual)
        const allTabs = new Set([...Object.keys(initial), ...Object.keys(current)]);

        allTabs.forEach(tabName => {
            const initialFields = initial[tabName] || [];
            const currentFields = current[tabName] || [];

            // Verificar campos alterados ou novos
            currentFields.forEach(field => {
                const original = initialFields.find(f => f.label === field.label);
                const valorAntigo = original ? original.para : null;
                const valorNovo = field.para;

                if (normalizeValue(valorAntigo) !== normalizeValue(valorNovo)) {
                    if (!changes[tabName]) changes[tabName] = [];
                    changes[tabName].push({
                        label: field.label,
                        de: valorAntigo,
                        para: valorNovo
                    });
                }
            });

            // Verificar campos removidos (existiam no inicial mas não no atual)
            initialFields.forEach(field => {
                const exists = currentFields.find(f => f.label === field.label);
                if (!exists && field.para) {
                    if (!changes[tabName]) changes[tabName] = [];
                    changes[tabName].push({
                        label: field.label,
                        de: field.para,
                        para: null
                    });
                }
            });
        });

        return changes;
    }

    /**
     * Captura estado inicial do formulário
     */
    function captureInitial(form) {
        if (!form.id) form.id = 'form_' + Date.now() + '_' + Math.random().toString(36).substring(2, 11);

        // Verificar se há handler especializado
        const handler = getHandler(form);
        if (handler && typeof handler.captureInitial === 'function') {
            initialData[form.id] = handler.captureInitial(form);
        } else {
            initialData[form.id] = capture(form);
        }
    }

    /**
     * Injeta campos ocultos de auditoria antes do submit
     */
    function injectHiddenFields(form) {
        // Remover campos anteriores
        ['_audit_data', '_audit_initial', '_audit_changes'].forEach(name => {
            const existing = form.querySelector(`[name="${name}"]`);
            if (existing) existing.remove();
        });

        // Detectar modo: edição se tiver campo id com valor
        const idField = form.querySelector('input[name="id"]');
        const isEditMode = idField && idField.value && idField.value !== '';

        if (isEditMode) {
            // Modo edição: apenas alterações
            const changes = document.createElement('textarea');
            changes.name = '_audit_changes';
            changes.style.cssText = 'display:none';
            changes.value = JSON.stringify(getChanges(form), null, 2);
            form.appendChild(changes);
        } else {
            // Modo cadastro: todos os campos
            const auditData = document.createElement('textarea');
            auditData.name = '_audit_data';
            auditData.style.cssText = 'display:none';
            auditData.value = JSON.stringify(capture(form), null, 2);
            form.appendChild(auditData);
        }
    }

    /**
     * Inicializa auditoria em um formulário
     */
    function init(form) {
        if (form.dataset.auditInitialized) return;
        form.dataset.auditInitialized = 'true';

        // Gerar ID se não tiver
        if (!form.id) {
            form.id = 'form_' + Date.now() + '_' + Math.random().toString(36).substring(2, 11);
        }

        // Capturar estado inicial após carregamento completo
        // Delay para aguardar selects assíncronos (chosen-select, etc)
        setTimeout(() => {
            captureInitial(form);
        }, 800);

        // Interceptar submit nativo
        form.addEventListener('submit', function() {
            injectHiddenFields(form);
        }, true);
    }

    /**
     * Re-captura estado inicial (útil após carregar dados via AJAX)
     */
    function recapture(form) {
        if (!form) return;
        captureInitial(form);
    }

    /**
     * Obtém dados de auditoria para envio manual (AJAX)
     */
    function getAuditData(form) {
        const idField = form.querySelector('input[name="id"]');
        const isEditMode = idField && idField.value && idField.value !== '';

        if (isEditMode) {
            return {
                _audit_changes: JSON.stringify(getChanges(form))
            };
        } else {
            return {
                _audit_data: JSON.stringify(capture(form))
            };
        }
    }

    // Auto-inicializar em todos os forms quando DOM carregar
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form:not([data-no-audit])').forEach(form => {
            init(form);
        });
    });

    // API pública
    return {
        CONFIG,
        init,
        capture,
        captureInitial,
        recapture,
        getChanges,
        getAuditData,
        injectHiddenFields,
        // Sistema de handlers especializados
        registerHandler,
        getHandler,
        detectPageHandler,
        // Expor funções auxiliares para uso nos handlers
        helpers: {
            cleanLabel,
            formatFieldName,
            getFieldLabel,
            getFieldValue,
            getTabName,
            shouldIgnoreField,
            normalizeValue
        }
    };
})();
