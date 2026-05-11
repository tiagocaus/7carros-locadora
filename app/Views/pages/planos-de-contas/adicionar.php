@extends('layouts.iframe')

@section('title', t('modules.planos_contas.title_singular'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle">{{ t('modules.planos_contas.new_title') }}</h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>{{ t('common.buttons.back') }}
        </button>
    </div>

    <!-- Formulario -->
    <form id="formPrincipal" method="POST">
        @csrf
        <input type="hidden" id="registroId" name="id">

        <!-- Secao: Dados Basicos -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-sitemap mr-2"></i>{{ t('modules.planos_contas.sections.basic_info') }}</h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Tipo (primeiro campo) -->
                <div class="md:col-span-3 form-input-group">
                    <label for="tipo" class="form-label-group">
                        {{ t('modules.planos_contas.fields.tipo') }} <span class="text-red-500">*</span>
                        {!! aviso(t('modules.planos_contas.tooltips.tipo')) !!}
                    </label>
                    <select id="tipo" name="tipo" class="chosen-select form-input-group-field" required
                            data-chosen-placeholder="{{ t('common.labels.select') }}..."
                            data-chosen-allow-clear="false">
                        <option value="">{{ t('common.labels.select') }}...</option>
                        <option value="A">{{ t('modules.planos_contas.fields.tipo_ativo') }}</option>
                        <option value="P">{{ t('modules.planos_contas.fields.tipo_passivo') }}</option>
                        <option value="D">{{ t('modules.planos_contas.fields.tipo_despesa') }}</option>
                        <option value="R">{{ t('modules.planos_contas.fields.tipo_receita') }}</option>
                    </select>
                </div>

                <!-- Conta Pai -->
                <div class="md:col-span-5 form-input-group">
                    <label for="conta_pai" class="form-label-group">
                        {{ t('modules.planos_contas.fields.conta_pai') }}
                        {!! aviso(t('modules.planos_contas.messages.conta_raiz')) !!}
                    </label>
                    <select id="conta_pai" name="conta_pai" class="form-input-group-field"
                            disabled>
                        <option value="">{{ t('modules.planos_contas.messages.conta_raiz') }}</option>
                    </select>
                </div>

                <!-- Hierarquia (Codigo) -->
                <div class="md:col-span-4 form-input-group">
                    <label for="hierarquia" class="form-label-group">
                        {{ t('modules.planos_contas.fields.hierarquia') }} <span class="text-red-500">*</span>
                        {!! aviso(t('modules.planos_contas.tooltips.hierarquia')) !!}
                    </label>
                    <div class="relative">
                        <input type="text" id="hierarquia" name="hierarquia" class="form-input-group-field font-mono pr-10" maxlength="30" required placeholder="<?= t('modules.planos_contas.placeholders.hierarquia') ?>">
                        <span id="codigoStatus" class="absolute right-3 top-1/2 transform -translate-y-1/2 hidden">
                            <i class="fas fa-spinner fa-spin text-slate-400"></i>
                        </span>
                    </div>
                    <p id="codigoFeedback" class="text-xs mt-1 hidden"></p>
                </div>
            </div>
        </div>

        <!-- Secao: Traducoes -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-language mr-2"></i>{{ t('modules.planos_contas.sections.translations') }}</h3>
            <p class="text-sm text-slate-600 mb-4">{{ t('modules.planos_contas.messages.translations_help') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Portugues Brasil (obrigatorio) -->
                <div class="md:col-span-6 form-input-group">
                    <label for="descricao_pt_BR" class="form-label-group">
                        <span class="inline-flex items-center">
                            <span class="mr-2">&#127463;&#127479;</span>
                            {{ t('modules.planos_contas.fields.descricao_pt_BR') }} <span class="text-red-500">*</span>
                        </span>
                    </label>
                    <input type="text" id="descricao_pt_BR" name="descricao_pt_BR" class="form-input-group-field" maxlength="100" required placeholder="{{ t('modules.planos_contas.placeholders.descricao') }}">
                </div>

                <!-- Ingles -->
                <div class="md:col-span-6 form-input-group">
                    <label for="descricao_en_US" class="form-label-group">
                        <span class="inline-flex items-center">
                            <span class="mr-2">&#127482;&#127480;</span>
                            {{ t('modules.planos_contas.fields.descricao_en_US') }}
                        </span>
                    </label>
                    <input type="text" id="descricao_en_US" name="descricao_en_US" class="form-input-group-field" maxlength="100" placeholder="{{ t('modules.planos_contas.placeholders.descricao_optional') }}">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                <!-- Espanhol -->
                <div class="md:col-span-4 form-input-group">
                    <label for="descricao_es_ES" class="form-label-group">
                        <span class="inline-flex items-center">
                            <span class="mr-2">&#127466;&#127480;</span>
                            {{ t('modules.planos_contas.fields.descricao_es_ES') }}
                        </span>
                    </label>
                    <input type="text" id="descricao_es_ES" name="descricao_es_ES" class="form-input-group-field" maxlength="100" placeholder="{{ t('modules.planos_contas.placeholders.descricao_optional') }}">
                </div>

                <!-- Italiano -->
                <div class="md:col-span-4 form-input-group">
                    <label for="descricao_it_IT" class="form-label-group">
                        <span class="inline-flex items-center">
                            <span class="mr-2">&#127470;&#127481;</span>
                            {{ t('modules.planos_contas.fields.descricao_it_IT') }}
                        </span>
                    </label>
                    <input type="text" id="descricao_it_IT" name="descricao_it_IT" class="form-input-group-field" maxlength="100" placeholder="{{ t('modules.planos_contas.placeholders.descricao_optional') }}">
                </div>

                <!-- Portugues Portugal -->
                <div class="md:col-span-4 form-input-group">
                    <label for="descricao_pt_PT" class="form-label-group">
                        <span class="inline-flex items-center">
                            <span class="mr-2">&#127477;&#127481;</span>
                            {{ t('modules.planos_contas.fields.descricao_pt_PT') }}
                        </span>
                    </label>
                    <input type="text" id="descricao_pt_PT" name="descricao_pt_PT" class="form-input-group-field" maxlength="100" placeholder="{{ t('modules.planos_contas.placeholders.descricao_optional') }}">
                </div>
            </div>
        </div>

        <!-- Botoes de Acao -->
        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6 rounded-md text-sm font-medium">
                {{ t('common.buttons.cancel') }}
            </button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-6 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-save mr-2"></i>{{ t('common.buttons.save') }}
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        const form = document.getElementById('formPrincipal');
        const registroId = document.getElementById('registroId');
        const pageTitle = document.getElementById('pageTitle');
        const tipoSelect = document.getElementById('tipo');
        const contaPaiSelect = document.getElementById('conta_pai');
        const hierarquiaInput = document.getElementById('hierarquia');
        const codigoStatus = document.getElementById('codigoStatus');
        const codigoFeedback = document.getElementById('codigoFeedback');

        let validacaoTimeout = null;

        // Obter ID da URL se estiver editando
        const urlParams = new URLSearchParams(window.location.search);
        const editId = urlParams.get('id');

        function navegarPara(page) {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: page
                }, '*');
            } else {
                window.location.href = page;
            }
        }

        function voltarParaLista() {
            navegarPara('/pages/planos-de-contas');
        }

        // Função auxiliar para limpar chosen-select completamente
        function limparChosenSelect(selectElement) {
            // Destruir instância se existir
            if (selectElement.chosenSelect) {
                try {
                    selectElement.chosenSelect.destroy();
                } catch (e) {
                    console.warn('Erro ao destruir ChosenSelect:', e);
                }
                selectElement.chosenSelect = null;
            }

            // Remover containers órfãos manualmente (precaução extra)
            const parent = selectElement.parentNode;
            if (parent) {
                const containers = parent.querySelectorAll('.chosen-select-container');
                containers.forEach(container => container.remove());
            }

            // Garantir que o select original esteja visível
            selectElement.style.display = '';
        }

        // Habilitar/recriar chosen-select de conta pai com tipo selecionado
        function habilitarContaPai(tipo) {
            // Sempre limpar o chosen-select existente primeiro
            limparChosenSelect(contaPaiSelect);

            if (!tipo) {
                // Desabilitar conta pai
                contaPaiSelect.disabled = true;
                contaPaiSelect.innerHTML = '<option value=""><?= t('modules.planos_contas.placeholders.selecione_tipo') ?></option>';
                return;
            }

            // Atualizar select
            contaPaiSelect.innerHTML = '<option value=""><?= t('modules.planos_contas.messages.conta_raiz') ?></option>';
            contaPaiSelect.disabled = false;

            // Criar novo chosen-select com a URL do tipo selecionado
            contaPaiSelect.chosenSelect = new ChosenSelect(contaPaiSelect, {
                type: 'server-side',
                searchUrl: `/api/planos-de-contas/por-tipo?tipo=${tipo}`,
                placeholder: '<?= t('modules.planos_contas.placeholders.conta_pai') ?>',
                allowClear: true
            });

            // Se não estiver editando, sugerir próximo código
            if (!editId) {
                sugerirProximoCodigo();
            }
        }

        // Sugerir proximo codigo
        async function sugerirProximoCodigo() {
            const tipo = tipoSelect.value;
            const pai = contaPaiSelect.value;

            if (!tipo) return;

            try {
                mostrarStatusCodigo('loading');

                const params = { tipo: tipo };
                if (pai) params.pai = pai;

                const result = await API.get('/api/planos-de-contas/proximo-codigo', params);

                if (result.success && result.data.codigo) {
                    hierarquiaInput.value = result.data.codigo;
                    mostrarStatusCodigo('success');
                }
            } catch (error) {
                console.error('Erro ao sugerir codigo:', error);
                mostrarStatusCodigo('hidden');
            }
        }

        // Validar formato do código: apenas números e pontos, padrão correto
        function validarFormatoCodigo(codigo) {
            // Regex: números e pontos, não começa/termina com ponto, sem pontos consecutivos
            // Exemplos válidos: "1", "1.1", "1.1.01", "12.34.56"
            const regex = /^[0-9]+(\.[0-9]+)*$/;
            return regex.test(codigo);
        }

        // Validar codigo em tempo real
        async function validarCodigo() {
            const codigo = hierarquiaInput.value.trim();

            if (!codigo) {
                mostrarStatusCodigo('hidden');
                mostrarFeedbackCodigo('hidden');
                return;
            }

            // Validar formato primeiro (apenas números e pontos, formato correto)
            if (!validarFormatoCodigo(codigo)) {
                mostrarFeedbackCodigo('error', '<?= t('modules.planos_contas.messages.formato_invalido') ?>');
                return;
            }

            try {
                mostrarStatusCodigo('loading');

                const params = { codigo: codigo };
                if (editId) params.excludeId = editId;

                const result = await API.get('/api/planos-de-contas/validar-codigo', params);

                if (result.success) {
                    if (result.data.disponivel) {
                        mostrarFeedbackCodigo('success', result.data.mensagem);
                    } else {
                        mostrarFeedbackCodigo('error', result.data.mensagem);
                    }
                }
            } catch (error) {
                console.error('Erro ao validar codigo:', error);
                mostrarStatusCodigo('hidden');
            }
        }

        function mostrarStatusCodigo(status) {
            if (status === 'loading') {
                codigoStatus.innerHTML = '<i class="fas fa-spinner fa-spin text-slate-400"></i>';
                codigoStatus.classList.remove('hidden');
            } else if (status === 'success') {
                codigoStatus.innerHTML = '<i class="fas fa-check text-green-500"></i>';
                codigoStatus.classList.remove('hidden');
            } else if (status === 'error') {
                codigoStatus.innerHTML = '<i class="fas fa-times text-red-500"></i>';
                codigoStatus.classList.remove('hidden');
            } else {
                codigoStatus.classList.add('hidden');
            }
        }

        function mostrarFeedbackCodigo(tipo, mensagem) {
            if (tipo === 'hidden') {
                codigoFeedback.classList.add('hidden');
                mostrarStatusCodigo('hidden');
                return;
            }

            codigoFeedback.textContent = mensagem;
            codigoFeedback.classList.remove('hidden', 'text-green-600', 'text-red-600');

            if (tipo === 'success') {
                codigoFeedback.classList.add('text-green-600');
                mostrarStatusCodigo('success');
            } else if (tipo === 'error') {
                codigoFeedback.classList.add('text-red-600');
                mostrarStatusCodigo('error');
            }
        }

        // Carregar dados se estiver editando
        async function carregarDados(id) {
            try {
                const result = await API.get(`/api/planos-de-contas/${id}`);

                if (result.success && result.data) {
                    const data = result.data;

                    registroId.value = data.id;
                    hierarquiaInput.value = data.hierarquia || '';

                    // Definir tipo e atualizar chosen-select
                    if (data.tipo) {
                        tipoSelect.value = data.tipo;
                        // Refresh chosen-select do tipo para mostrar valor selecionado
                        if (tipoSelect.chosenSelect) {
                            tipoSelect.chosenSelect.refresh();
                        }

                        // Determinar conta pai pela hierarquia
                        let paiHierarquia = '';
                        const hierarquiaParts = data.hierarquia.split('.');
                        if (hierarquiaParts.length > 1) {
                            hierarquiaParts.pop();
                            paiHierarquia = hierarquiaParts.join('.');
                        }

                        // Habilitar chosen-select de conta pai
                        habilitarContaPai(data.tipo);

                        // Definir valor da conta pai após chosen-select estar pronto
                        if (paiHierarquia) {
                            // Adicionar opção temporária e selecionar
                            setTimeout(() => {
                                const optionExists = Array.from(contaPaiSelect.options).some(opt => opt.value === paiHierarquia);
                                if (!optionExists) {
                                    const opt = document.createElement('option');
                                    opt.value = paiHierarquia;
                                    opt.textContent = paiHierarquia;
                                    opt.selected = true;
                                    contaPaiSelect.appendChild(opt);
                                } else {
                                    contaPaiSelect.value = paiHierarquia;
                                }
                                // Refresh chosen-select
                                if (contaPaiSelect.chosenSelect) {
                                    contaPaiSelect.chosenSelect.refresh();
                                }
                            }, 100);
                        }
                    }

                    // Preencher traducoes
                    if (data.traducoes) {
                        document.getElementById('descricao_pt_BR').value = data.traducoes.pt_BR || '';
                        document.getElementById('descricao_en_US').value = data.traducoes.en_US || '';
                        document.getElementById('descricao_es_ES').value = data.traducoes.es_ES || '';
                        document.getElementById('descricao_it_IT').value = data.traducoes.it_IT || '';
                        document.getElementById('descricao_pt_PT').value = data.traducoes.pt_PT || '';
                    }

                    pageTitle.textContent = '<?= t('modules.planos_contas.edit_title') ?>';

                    // Se for plano do sistema, desabilitar campos
                    if (data.chave === '0' || data.chave === 0) {
                        document.querySelectorAll('input, select, textarea').forEach(el => {
                            if (el.type !== 'hidden') {
                                el.disabled = true;
                            }
                        });
                        document.getElementById('btnSalvar').style.display = 'none';

                        // Mostrar aviso
                        const aviso = document.createElement('div');
                        aviso.className = 'bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-md mb-4';
                        aviso.innerHTML = '<i class="fas fa-info-circle mr-2"></i><?= t('modules.planos_contas.messages.system_readonly') ?>';
                        form.insertBefore(aviso, form.firstChild);
                    }
                } else {
                    toast.error(result.message || '<?= t('modules.planos_contas.messages.not_found') ?>');
                    voltarParaLista();
                }
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
                toast.error('<?= t('modules.planos_contas.messages.error_load') ?>');
                voltarParaLista();
            }
        }

        // Salvar formulario
        async function salvarFormulario(e) {
            e.preventDefault();

            const btnSalvar = document.getElementById('btnSalvar');
            const originalText = btnSalvar.innerHTML;
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i><?= t('common.labels.saving') ?>';
            btnSalvar.disabled = true;

            try {
                const formData = new FormData(form);
                const id = registroId.value;

                let url = '/planos-de-contas/salvar';
                if (id) {
                    url = `/planos-de-contas/${id}/atualizar`;
                }

                const result = await API.postForm(url, formData);

                if (result.success) {
                    // Notificar sucesso
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'showToast',
                            type: 'success',
                            message: result.message || '<?= t('modules.planos_contas.messages.saved') ?>'
                        }, '*');
                    }
                    voltarParaLista();
                } else {
                    toast.error(result.message || '<?= t('modules.planos_contas.messages.error_save') ?>');
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                toast.error('<?= t('modules.planos_contas.messages.error_save') ?>');
            } finally {
                btnSalvar.innerHTML = originalText;
                btnSalvar.disabled = false;
            }
        }

        // Event listeners
        document.getElementById('btnVoltar')?.addEventListener('click', voltarParaLista);
        document.getElementById('btnCancelar')?.addEventListener('click', voltarParaLista);
        form?.addEventListener('submit', salvarFormulario);

        // Ao mudar tipo, habilitar chosen-select de conta pai
        tipoSelect?.addEventListener('change', function() {
            habilitarContaPai(this.value);
        });

        // Ao mudar conta pai, sugerir proximo codigo
        contaPaiSelect?.addEventListener('change', function() {
            sugerirProximoCodigo();
        });

        // Validar codigo ao digitar (com debounce)
        hierarquiaInput?.addEventListener('input', function() {
            if (validacaoTimeout) {
                clearTimeout(validacaoTimeout);
            }
            validacaoTimeout = setTimeout(validarCodigo, 500);
        });

        // Auto-preencher outros idiomas quando pt_BR muda (se estiverem vazios)
        document.getElementById('descricao_pt_BR')?.addEventListener('blur', function() {
            const valor = this.value;
            ['en_US', 'es_ES', 'it_IT', 'pt_PT'].forEach(locale => {
                const campo = document.getElementById(`descricao_${locale}`);
                if (campo && !campo.value) {
                    campo.placeholder = valor || '<?= t('modules.planos_contas.placeholders.descricao_optional') ?>';
                }
            });
        });

        // Inicializar
        if (editId) {
            carregarDados(editId);
        }
    })();
</script>
@endsection
