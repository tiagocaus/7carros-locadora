/**
 * TinyMCE Initialization for Template Editor
 *
 * Configura o editor TinyMCE com suporte a variáveis de template
 */

/**
 * Inicializa o TinyMCE em um seletor específico
 *
 * @param {string} selector - Seletor CSS do textarea
 * @param {object} variables - Variáveis disponíveis organizadas por entidade
 * @param {function} onChangeCallback - Callback chamado quando o conteúdo muda
 * @param {object} options - Opções adicionais do editor
 * @returns {Promise} Promise que resolve quando o editor estiver pronto
 */
async function initTinyMCE(selector, variables, onChangeCallback, options = {}) {
    // Remover instância anterior se existir 
    const existingEditor = tinymce.get(selector.replace('#', ''));
    if (existingEditor) {
        existingEditor.remove();
    }

    const enableFontSize = options.enableFontSize === true;
    const toolbarFirstRow = enableFontSize
        ? 'undo redo | formatselect fontsize | bold italic underline strikethrough | forecolor backcolor'
        : 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor';

    return new Promise((resolve, reject) => {
        tinymce.init({
            selector: selector,
            license_key: window.TINYMCE_LICENSE_KEY || 'gpl',
            height: 350,
            language: 'pt-BR',
            language_url: '/assets/vendor/tinymce/js/tinymce/langs/pt-BR.js',
            base_url: '/assets/vendor/tinymce/js/tinymce',

            // Plugins essenciais
            plugins: [
                'noneditable',
                'lists',
                'link',
                'image',
                'table',
                'code',
                'fullscreen',
                'wordcount',
                'autolink',
                'autoresize'
            ],

            // Configuração de variáveis não-editáveis
            noneditable_regexp: /\{\{[a-zA-Z0-9_.]+\}\}/g,
            noneditable_class: 'mceNonEditable template-var',

            // Permitir atributos usados por variáveis e estilos aplicados no editor
            extended_valid_elements: 'span[class|contenteditable|style]',

            // Toolbar customizada
            toolbar: [
                toolbarFirstRow,
                'alignleft aligncenter alignright alignjustify | bullist numlist | link image table | variablesButton | code fullscreen'
            ].join(' | '),

            // Ocultar menu
            menubar: false,

            // Configurações de aparência
            skin: 'oxide',
            content_css: 'default',
            content_style: `
                .template-var {
                    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
                    border: 1px solid #93c5fd;
                    border-radius: 4px;
                    padding: 0px 6px;
                    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
                    font-size: 0.9em;
                    color: #1e40af;
                    cursor: default;
                    display: inline-block;
                    white-space: nowrap;
                }
                .template-var:hover {
                    background: linear-gradient(135deg, #bfdbfe 0%, #93c5fd 100%);
                }
            `,

            // Configurações de comportamento
            branding: false,
            promotion: false,
            statusbar: true,
            resize: true,
            autoresize_bottom_margin: 20,
            min_height: 250,
            max_height: 600,

            // Formatos de texto permitidos
            block_formats: 'Parágrafo=p; Cabeçalho 1=h1; Cabeçalho 2=h2; Cabeçalho 3=h3; Cabeçalho 4=h4',
            font_size_formats: '5pt 6pt 7pt 8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 24pt 28pt 32pt',
            font_size_input_default_unit: 'pt',

            // Configurações de links
            link_default_target: '_blank',
            link_assume_external_targets: true,

            // Configurações de imagens
            image_advtab: false,
            image_description: false,

            // Tabelas
            table_default_attributes: {
                border: '1'
            },
            table_default_styles: {
                'border-collapse': 'collapse',
                'width': '100%'
            },

            // Setup - adicionar botão de variáveis
            setup: function (editor) {
                // Registrar botão de menu de variáveis
                editor.ui.registry.addMenuButton('variablesButton', {
                    text: 'Variáveis',
                    icon: 'code-sample',
                    tooltip: 'Inserir variável de template',
                    fetch: function (callback) {
                        const items = buildVariableMenuItems(variables, editor);
                        callback(items);
                    }
                });

                // Callback de mudança
                if (typeof onChangeCallback === 'function') {
                    editor.on('change', function () {
                        onChangeCallback();
                    });
                    editor.on('keyup', function () {
                        onChangeCallback();
                    });
                }

                // Resolver promise quando inicializado
                editor.on('init', function () {
                    resolve(editor);
                });
            },

            // Tratamento de erros
            init_instance_callback: function (editor) {
                // Editor inicializado com sucesso
            }
        });
    });
}

/**
 * Constrói os itens do menu de variáveis para o TinyMCE
 *
 * @param {object} variables - Variáveis organizadas por entidade
 * @param {object} editor - Instância do editor TinyMCE
 * @returns {array} Array de itens de menu
 */
function buildVariableMenuItems(variables, editor) {
    const items = [];

    if (!variables || typeof variables !== 'object') {
        return [{
            type: 'menuitem',
            text: 'Nenhuma variável disponível',
            enabled: false
        }];
    }

    for (const [entity, data] of Object.entries(variables)) {
        const entityVars = data.variables || [];

        if (entityVars.length === 0) continue;

        items.push({
            type: 'nestedmenuitem',
            text: data.label || entity,
            getSubmenuItems: function () {
                return entityVars.map(v => ({
                    type: 'menuitem',
                    text: `${v.label}`,
                    onAction: function () {
                        // Inserir variável com span não-editável
                        const html = '<span class="mceNonEditable template-var" contenteditable="false">' + v.variable + '</span>&nbsp;';
                        editor.insertContent(html);
                    }
                }));
            }
        });
    }

    if (items.length === 0) {
        return [{
            type: 'menuitem',
            text: 'Nenhuma variável disponível',
            enabled: false
        }];
    }

    return items;
}

/**
 * Obtém o conteúdo do editor TinyMCE
 *
 * @param {string} editorId - ID do editor (sem #)
 * @returns {string} Conteúdo HTML do editor
 */
function getTinyMCEContent(editorId) {
    const editor = tinymce.get(editorId);
    return editor ? editor.getContent() : '';
}

/**
 * Define o conteúdo do editor TinyMCE
 *
 * @param {string} editorId - ID do editor (sem #)
 * @param {string} content - Conteúdo HTML a definir
 */
function setTinyMCEContent(editorId, content) {
    const editor = tinymce.get(editorId);
    if (editor) {
        editor.setContent(content || '');
    }
}

/**
 * Insere conteúdo na posição atual do cursor
 *
 * @param {string} editorId - ID do editor (sem #)
 * @param {string} content - Conteúdo a inserir
 */
function insertTinyMCEContent(editorId, content) {
    const editor = tinymce.get(editorId);
    if (editor) {
        editor.insertContent(content);
    }
}

/**
 * Destrói a instância do TinyMCE
 *
 * @param {string} editorId - ID do editor (sem #)
 */
function destroyTinyMCE(editorId) {
    const editor = tinymce.get(editorId);
    if (editor) {
        editor.remove();
    }
}

// Exportar funções para uso global
window.initTinyMCE = initTinyMCE;
window.getTinyMCEContent = getTinyMCEContent;
window.setTinyMCEContent = setTinyMCEContent;
window.insertTinyMCEContent = insertTinyMCEContent;
window.destroyTinyMCE = destroyTinyMCE;
