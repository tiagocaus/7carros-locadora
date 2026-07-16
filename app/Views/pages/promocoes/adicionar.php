@extends('layouts.iframe')

@section('title', '<?= t("modules.promocoes.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.promocoes.new_title') ?></h2>
        <button type="button" id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <form id="formPromocao" method="POST">
        <input type="hidden" id="id" name="id">

        <div class="form-section">
            <h3 class="form-section-title"><?= t('modules.promocoes.sections.promotion_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                <!-- Primeira linha: Filiais -->
                <div class="form-input-group md:col-span-5">
                    <label class="form-label-group"><?= t('modules.promocoes.fields.branches') ?> <span class="text-red-500">*</span></label>
                    <div id="filiaisDropdown" class="filiais-dropdown">
                        <div class="filiais-dropdown-trigger" id="filiaisDropdownTrigger">
                            <span class="filiais-dropdown-text" id="filiaisDropdownText"><?= t('modules.promocoes.placeholders.select_branches') ?></span>
                            <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                        </div>
                        <div class="filiais-dropdown-menu" id="filiaisDropdownMenu">
                            <div class="filiais-loading">
                                <i class="fas fa-spinner fa-spin"></i> <?= t('modules.promocoes.messages.loading_branches') ?>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="filiaisIdsJson" name="filiais_ids">
                </div>

                <div class="form-input-group md:col-span-5">
                    <label for="grupos" class="form-label-group">
                        <?= t('modules.promocoes.fields.groups') ?> {!! aviso(t('modules.promocoes.tooltips.groups')) !!}
                    </label>
                    <select id="grupos" name="grupos[]" class="form-input-group-field chosen-select" multiple
                            data-chosen-type="server-side"
                            data-chosen-search-url="/api/grupos"
                            data-chosen-placeholder="<?= t('modules.promocoes.placeholders.select_groups') ?>">
                    </select>
                </div>

                <!-- Segunda linha: Codigo, Nome e Validade -->
                <div class="form-input-group md:col-span-1">
                    <label for="codigo" class="form-label-group"><?= t('modules.promocoes.fields.code') ?> <span class="text-red-500">*</span></label>
                    <input type="text" id="codigo" name="codigo" class="form-input-group-field uppercase" required maxlength="15" placeholder="<?= t('modules.promocoes.placeholders.code_example') ?>">
                </div>

                <div class="form-input-group md:col-span-3">
                    <label for="nome" class="form-label-group"><?= t('modules.promocoes.fields.name') ?> <span class="text-red-500">*</span></label>
                    <input type="text" id="nome" name="nome" class="form-input-group-field" required maxlength="100" placeholder="<?= t('modules.promocoes.placeholders.name_example') ?>">
                </div>

                <div class="form-input-group md:col-span-1">
                    <label for="validade" class="form-label-group"><?= t('modules.promocoes.fields.validity') ?> {!! aviso(t('modules.promocoes.tooltips.validity')) !!}</label>
                    <input type="date" id="validade" name="validade" class="form-input-group-field">
                </div>

                <!-- Terceira linha: Dias Minimos, Tipo e Valor -->
                <div class="form-input-group md:col-span-1">
                    <label for="dias" class="form-label-group"><?= t('modules.promocoes.fields.minimum_days') ?> {!! aviso(t('modules.promocoes.tooltips.minimum_days')) !!}</label>
                    <input type="number" id="dias" name="dias" class="form-input-group-field" min="0" max="999" value="0" placeholder="0">
                </div>

                <div class="form-input-group md:col-span-1">
                    <label for="tipo" class="form-label-group"><?= t('modules.promocoes.fields.discount_type') ?></label>
                    <select id="tipo" name="tipo" class="form-input-group-field">
                        <option value="DFIX"><?= t('modules.promocoes.type_options.fixed') ?></option>
                        <option value="DPOR"><?= t('modules.promocoes.type_options.percentage') ?></option>
                    </select>
                </div>

                <div class="form-input-group md:col-span-1">
                    <label for="valor" class="form-label-group"><?= t('modules.promocoes.fields.discount_value') ?> <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span id="valorPrefix" class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valor" name="valor" class="form-input-group-field pl-10 input-moeda" placeholder="0,00">
                    </div>
                </div>

                <!-- Onde Exibir -->
                <div class="form-input-group md:col-span-2">
                    <label class="form-label-group"><?= t('modules.promocoes.fields.where_to_show') ?> {!! aviso(t('modules.promocoes.tooltips.where_to_show')) !!}</label>
                    <div id="ondeExibirDropdown" class="filiais-dropdown">
                        <div class="filiais-dropdown-trigger" id="ondeExibirDropdownTrigger">
                            <span class="filiais-dropdown-text" id="ondeExibirDropdownText"><?= t('modules.promocoes.display_options.system') ?></span>
                            <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                        </div>
                        <div class="filiais-dropdown-menu" id="ondeExibirDropdownMenu">
                            <div class="filial-item selected" data-id="SIS" data-nome="<?= t('modules.promocoes.display_options.system') ?>">
                                <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="onde-exibir-checkbox" value="SIS" checked>
                                    <span class="filial-nome"><?= t('modules.promocoes.display_options.system') ?></span>
                                </label>
                            </div>
                            <div class="filial-item" data-id="SITE" data-nome="<?= t('modules.promocoes.display_options.site') ?>">
                                <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="onde-exibir-checkbox" value="SITE">
                                    <span class="filial-nome"><?= t('modules.promocoes.display_options.site') ?></span>
                                </label>
                            </div>
                            <div class="filial-item" data-id="APP" data-nome="<?= t('modules.promocoes.display_options.app') ?>">
                                <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="onde-exibir-checkbox" value="APP">
                                    <span class="filial-nome"><?= t('modules.promocoes.display_options.app') ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="ondeExibirJson" name="onde_exibir">
                </div>

                <!-- Ultima linha: Status -->
                <div class="form-input-group md:col-span-5">
                    <label for="status" class="form-label-group"><?= t('modules.promocoes.fields.status') ?></label>
                    <select id="status" name="status" class="form-input-group-field">
                        <option value="A"><?= t('modules.promocoes.status_options.active') ?></option>
                        <option value="D"><?= t('modules.promocoes.status_options.disabled') ?></option>
                    </select>
                </div>
            </div>
        </div>

        <!-- ========== Valores por filial (so tipo DFIX) ========== -->
        <div id="valoresFiliaisSection" class="form-section mb-6 hidden">
            <h3 class="form-section-title">
                <i class="fas fa-coins mr-2"></i>
                <?= t('modules.promocoes.sections.values_by_branch') ?>
            </h3>
            <p class="text-sm text-slate-500 mb-4">
                <?= t('modules.promocoes.descriptions.values_by_branch') ?>
            </p>

            <div id="valoresFiliaisTabela" class="space-y-2">
                <p id="valoresFiliaisVazio" class="text-sm text-slate-400 italic">
                    <?= t('modules.promocoes.messages.select_branches_first') ?>
                </p>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        const i18n = {
            editTitle: '<?= addslashes(t('modules.promocoes.edit_title')) ?>',
            loadError: '<?= addslashes(t('modules.promocoes.messages.load_error')) ?>',
            notFound: '<?= addslashes(t('modules.promocoes.messages.not_found')) ?>',
            loadBranchesError: '<?= addslashes(t('modules.promocoes.messages.load_branches_error')) ?>',
            loadBranchesText: '<?= addslashes(t('modules.promocoes.messages.load_branches_text')) ?>',
            noBranches: '<?= addslashes(t('modules.promocoes.messages.no_branches')) ?>',
            noBranchesText: '<?= addslashes(t('modules.promocoes.messages.no_branches_text')) ?>',
            selectBranches: '<?= addslashes(t('modules.promocoes.placeholders.select_branches')) ?>',
            select: '<?= addslashes(t('modules.promocoes.placeholders.select')) ?>',
            displaySystem: '<?= addslashes(t('modules.promocoes.display_options.system')) ?>',
            displaySite: '<?= addslashes(t('modules.promocoes.display_options.site')) ?>',
            displayApp: '<?= addslashes(t('modules.promocoes.display_options.app')) ?>',
            displayAll: '<?= addslashes(t('modules.promocoes.display_options.all')) ?>',
            requiredFields: '<?= addslashes(t('modules.promocoes.messages.required_fields')) ?>',
            saving: '<?= addslashes(t('modules.promocoes.messages.saving')) ?>',
            saveError: '<?= addslashes(t('modules.promocoes.messages.save_error')) ?>',
            created: '<?= addslashes(t('modules.promocoes.messages.created')) ?>',
            updated: '<?= addslashes(t('modules.promocoes.messages.updated')) ?>',
            btnSave: '<?= addslashes(t('common.buttons.save')) ?>',
        };

        let registroId = null;

        // Estado filiais
        let filiaisDisponiveis = [];
        let filiaisSelecionadas = [];
        let dropdownFiliaisAberto = false;

        // Estado onde exibir
        let ondeExibirSelecionados = ['SIS'];
        let dropdownOndeExibirAberto = false;

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

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ===== FILIAIS =====

        async function carregarFiliais() {
            const dropdownMenu = document.getElementById('filiaisDropdownMenu');
            const dropdownText = document.getElementById('filiaisDropdownText');

            try {
                const result = await API.get('/api/matrizes-filiais/buscar');

                if (result.success && result.data) {
                    filiaisDisponiveis = result.data;
                    renderizarFiliais();
                } else {
                    dropdownMenu.innerHTML = '<div class="filiais-dropdown-error">' + i18n.loadBranchesError + '</div>';
                    dropdownText.textContent = i18n.loadBranchesText;
                }
            } catch (error) {
                console.error('Erro ao carregar filiais:', error);
                dropdownMenu.innerHTML = '<div class="filiais-dropdown-error">Erro ao carregar filiais</div>';
                dropdownText.textContent = 'Erro ao carregar';
            }
        }

        function renderizarFiliais() {
            const dropdownMenu = document.getElementById('filiaisDropdownMenu');
            const dropdownText = document.getElementById('filiaisDropdownText');

            if (filiaisDisponiveis.length === 0) {
                dropdownMenu.innerHTML = '<div class="filiais-dropdown-empty">' + i18n.noBranches + '</div>';
                dropdownText.textContent = i18n.noBranchesText;
                return;
            }

            let html = '';
            filiaisDisponiveis.forEach((filial) => {
                const isChecked = filiaisSelecionadas.includes(parseInt(filial.id));

                html += `
                    <div class="filial-item ${isChecked ? 'selected' : ''}" data-id="${filial.id}" data-nome="${escapeHtml(filial.nome)}">
                        <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                            <input type="checkbox" class="filial-checkbox" value="${filial.id}" ${isChecked ? 'checked' : ''}>
                            <span class="filial-nome">${escapeHtml(filial.nome)}</span>
                        </label>
                    </div>
                `;
            });

            dropdownMenu.innerHTML = html;

            // Event listeners
            dropdownMenu.querySelectorAll('.filial-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', handleFilialCheckboxChange);
            });

            atualizarTextoDropdownFiliais();
        }

        function handleFilialCheckboxChange(e) {
            const checkbox = e.target;
            const filialItem = checkbox.closest('.filial-item');
            const filialId = parseInt(checkbox.value);

            if (checkbox.checked) {
                filialItem.classList.add('selected');
                if (!filiaisSelecionadas.includes(filialId)) {
                    filiaisSelecionadas.push(filialId);
                }
            } else {
                filialItem.classList.remove('selected');
                filiaisSelecionadas = filiaisSelecionadas.filter(id => id !== filialId);
            }

            atualizarTextoDropdownFiliais();
            atualizarHiddenInputFiliais();

            // Se tipo=DFIX, atualiza tabela de valores por filial
            if (document.getElementById('tipo')?.value === 'DFIX') {
                renderizarTabelaValoresFiliais();
            }
        }

        function atualizarTextoDropdownFiliais() {
            const dropdownText = document.getElementById('filiaisDropdownText');
            const selecionados = Array.from(document.querySelectorAll('.filial-checkbox:checked'))
                .map(cb => cb.closest('.filial-item').dataset.nome);

            if (selecionados.length === 0) {
                dropdownText.textContent = i18n.selectBranches;
            } else if (selecionados.length <= 3) {
                dropdownText.textContent = selecionados.join(', ');
            } else {
                dropdownText.textContent = `${selecionados.slice(0, 2).join(', ')} +${selecionados.length - 2}`;
            }
        }

        function atualizarHiddenInputFiliais() {
            document.getElementById('filiaisIdsJson').value = JSON.stringify(filiaisSelecionadas);
        }

        function setFiliaisSelecionadas(filiais) {
            filiaisSelecionadas = filiais.map(f => parseInt(f.id));
            renderizarFiliais();
            atualizarHiddenInputFiliais();
        }

        function configurarDropdownFiliais() {
            const dropdown = document.getElementById('filiaisDropdown');
            const trigger = document.getElementById('filiaisDropdownTrigger');

            trigger?.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownFiliaisAberto = !dropdownFiliaisAberto;
                dropdown.classList.toggle('open', dropdownFiliaisAberto);
            });
        }

        // ===== ONDE EXIBIR =====

        function configurarDropdownOndeExibir() {
            const dropdown = document.getElementById('ondeExibirDropdown');
            const trigger = document.getElementById('ondeExibirDropdownTrigger');

            trigger?.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownOndeExibirAberto = !dropdownOndeExibirAberto;
                dropdown.classList.toggle('open', dropdownOndeExibirAberto);
            });

            // Event listeners para checkboxes
            document.querySelectorAll('.onde-exibir-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', handleOndeExibirCheckboxChange);
            });

            atualizarTextoDropdownOndeExibir();
            atualizarHiddenInputOndeExibir();
        }

        function handleOndeExibirCheckboxChange(e) {
            const checkbox = e.target;
            const item = checkbox.closest('.filial-item');
            const valor = checkbox.value;

            if (checkbox.checked) {
                item.classList.add('selected');
                if (!ondeExibirSelecionados.includes(valor)) {
                    ondeExibirSelecionados.push(valor);
                }
            } else {
                item.classList.remove('selected');
                ondeExibirSelecionados = ondeExibirSelecionados.filter(v => v !== valor);
            }

            atualizarTextoDropdownOndeExibir();
            atualizarHiddenInputOndeExibir();
        }

        function atualizarTextoDropdownOndeExibir() {
            const dropdownText = document.getElementById('ondeExibirDropdownText');
            const nomes = {
                'SIS': i18n.displaySystem,
                'SITE': i18n.displaySite,
                'APP': i18n.displayApp
            };

            const selecionados = ondeExibirSelecionados.map(v => nomes[v] || v);

            if (selecionados.length === 0) {
                dropdownText.textContent = i18n.select;
            } else if (selecionados.length === 3) {
                dropdownText.textContent = i18n.displayAll;
            } else {
                dropdownText.textContent = selecionados.join(', ');
            }
        }

        function atualizarHiddenInputOndeExibir() {
            document.getElementById('ondeExibirJson').value = ondeExibirSelecionados.join(',') || 'SIS';
        }

        function setOndeExibirSelecionados(valores) {
            ondeExibirSelecionados = valores;
            document.querySelectorAll('.onde-exibir-checkbox').forEach(checkbox => {
                const isChecked = valores.includes(checkbox.value);
                checkbox.checked = isChecked;
                checkbox.closest('.filial-item').classList.toggle('selected', isChecked);
            });
            atualizarTextoDropdownOndeExibir();
            atualizarHiddenInputOndeExibir();
        }

        // Fechar dropdowns ao clicar fora
        document.addEventListener('click', function(e) {
            const dropdownFiliais = document.getElementById('filiaisDropdown');
            const dropdownOndeExibir = document.getElementById('ondeExibirDropdown');

            if (dropdownFiliais && !dropdownFiliais.contains(e.target)) {
                dropdownFiliaisAberto = false;
                dropdownFiliais.classList.remove('open');
            }
            if (dropdownOndeExibir && !dropdownOndeExibir.contains(e.target)) {
                dropdownOndeExibirAberto = false;
                dropdownOndeExibir.classList.remove('open');
            }
        });

        // ===== VALOR =====

        function configurarCampoValor() {
            const valorPrefix = document.getElementById('valorPrefix');
            const tipoSelect = document.getElementById('tipo');

            tipoSelect.addEventListener('change', function() {
                aplicarVisibilidadeTipo(this.value);
            });
            aplicarVisibilidadeTipo(tipoSelect.value);
        }

        // Alterna entre input unico (DPOR) e tabela por filial (DFIX)
        function aplicarVisibilidadeTipo(tipo) {
            const valorPrefix = document.getElementById('valorPrefix');
            const valorInput = document.getElementById('valor');
            const valorInputContainer = valorInput?.closest('.form-input-group');
            const secaoFiliais = document.getElementById('valoresFiliaisSection');

            if (tipo === 'DPOR') {
                valorPrefix.textContent = '%';
                valorInput.disabled = false;
                valorInput.required = true;
                if (valorInputContainer) valorInputContainer.classList.remove('hidden');
                if (secaoFiliais) secaoFiliais.classList.add('hidden');
            } else { // DFIX
                valorPrefix.textContent = (typeof Currency !== 'undefined' && Currency.config) ? Currency.config.symbol : 'R$';
                // Oculta input unico — valores vao na tabela por filial
                valorInput.required = false;
                valorInput.disabled = true;
                if (valorInputContainer) valorInputContainer.classList.add('hidden');
                if (secaoFiliais) {
                    secaoFiliais.classList.remove('hidden');
                    renderizarTabelaValoresFiliais();
                }
            }
        }

        // Valores ja preenchidos (preservados entre re-renders)
        let valoresFiliaisCache = {};

        function renderizarTabelaValoresFiliais() {
            const container = document.getElementById('valoresFiliaisTabela');
            if (!container) return;

            // Captura valores atuais antes de re-renderizar
            container.querySelectorAll('.valor-filial-input').forEach(inp => {
                valoresFiliaisCache[parseInt(inp.dataset.filialId)] = inp.value;
            });

            // filiaisSelecionadas = [id, id, ...]. Cruzamos com filiaisDisponiveis
            // pra obter nome e moeda.
            const mapaFiliais = {};
            (filiaisDisponiveis || []).forEach(f => { mapaFiliais[parseInt(f.id)] = f; });

            const filiaisParticipantes = (filiaisSelecionadas || [])
                .map(id => {
                    const f = mapaFiliais[parseInt(id)];
                    if (!f) return null;
                    return {
                        id: parseInt(f.id),
                        nome: f.nome_fantasia || f.razao_social || f.nome || ('#' + f.id),
                        currency_code: f.currency_code || 'BRL',
                    };
                })
                .filter(Boolean);

            if (filiaisParticipantes.length === 0) {
                container.innerHTML = '<p class="text-sm text-slate-400 italic">' +
                    '<?= addslashes(t('modules.promocoes.messages.select_branches_first')) ?>' +
                    '</p>';
                return;
            }

            const symbolMap = { BRL: 'R$', EUR: '€', USD: '$', GBP: '£' };
            let html = '<div class="overflow-x-auto"><table class="w-full border-collapse">' +
                '<thead><tr class="border-b border-slate-200">' +
                '<th class="text-left py-2 px-3 text-sm font-medium text-slate-600">Filial</th>' +
                '<th class="text-left py-2 px-3 text-sm font-medium text-slate-600 w-64">Valor do desconto</th>' +
                '</tr></thead><tbody>';

            filiaisParticipantes.forEach(f => {
                const sym = symbolMap[f.currency_code] || f.currency_code;
                const val = valoresFiliaisCache[f.id] ?? '0,00';
                html += `
                    <tr class="border-b border-slate-100">
                        <td class="py-2 px-3 text-sm">${escapeHtml(f.nome)} <span class="text-xs text-slate-400">(${f.currency_code})</span></td>
                        <td class="py-2 px-3">
                            <div class="relative">
                                <span class="absolute top-1/2 transform -translate-y-1/2 left-3 text-slate-500 text-sm">${sym}</span>
                                <input type="text" class="form-input-group-field pl-10 input-moeda valor-filial-input"
                                       data-filial-id="${f.id}" value="${val}" placeholder="0,00">
                            </div>
                        </td>
                    </tr>`;
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;

            // Aplica mascara de moeda nos novos inputs
            container.querySelectorAll('.input-moeda').forEach(input => {
                input.addEventListener('input', function(e) {
                    let v = e.target.value.replace(/\D/g, '');
                    v = (parseInt(v || '0') / 100).toFixed(2);
                    v = v.replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
                    e.target.value = v;
                });
            });
        }

        // ===== CARREGAR DADOS =====

        async function carregarDados(id) {
            try {
                const result = await API.get(`/api/promocoes/${id}`);

                if (result.success && result.data) {
                    preencherFormulario(result.data);
                } else {
                    mostrarAlerta(i18n.loadError + ': ' + (result.message || i18n.notFound), function() {
                        navegarPara('/pages/promocoes');
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
                mostrarAlerta(i18n.loadError, function() {
                    navegarPara('/pages/promocoes');
                });
            }
        }

        function preencherFormulario(data) {
            document.getElementById('id').value = data.id;
            document.getElementById('codigo').value = data.codigo || '';
            document.getElementById('nome').value = data.nome || '';
            document.getElementById('tipo').value = data.tipo || 'DFIX';
            document.getElementById('status').value = data.status || 'A';
            document.getElementById('dias').value = data.dias || 0;

            // Validade
            if (data.validade && data.validade !== '0000-00-00') {
                document.getElementById('validade').value = data.validade;
            }

            // Valor formatado usando Currency helper
            Currency.setValue('#valor', data.valor || 0);

            // Atualizar prefixo
            const valorPrefix = document.getElementById('valorPrefix');
            valorPrefix.textContent = data.tipo === 'DPOR' ? '%' : ((typeof Currency !== 'undefined' && Currency.config) ? Currency.config.symbol : 'R$');

            // Filiais
            if (data.filiais && data.filiais.length > 0) {
                setFiliaisSelecionadas(data.filiais);
            }

            const gruposSelect = document.getElementById('grupos');
            Array.from(gruposSelect.options).forEach(option => { option.selected = false; });
            (data.grupos || []).forEach(grupo => {
                let option = Array.from(gruposSelect.options).find(item => item.value === String(grupo.id));
                if (!option) {
                    option = new Option(grupo.nome, grupo.id);
                    gruposSelect.add(option);
                }
                option.selected = true;
            });
            gruposSelect.chosenSelect?.refresh();

            // Onde Exibir
            if (data.onde_exibir) {
                setOndeExibirSelecionados(data.onde_exibir.split(','));
            }

            // Valores por filial (quando DFIX)
            valoresFiliaisCache = {};
            if (data.valores_filiais && typeof data.valores_filiais === 'object') {
                Object.entries(data.valores_filiais).forEach(([idFilial, valor]) => {
                    valoresFiliaisCache[parseInt(idFilial)] = Currency.format(valor);
                });
            }
            aplicarVisibilidadeTipo(document.getElementById('tipo').value);

            // Atualizar titulo
            document.getElementById('pageTitle').textContent = i18n.editTitle;
        }

        // ===== MODAL DE ALERTA =====

        function mostrarAlerta(mensagem, callbackAction = null) {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'openAlert',
                    message: mensagem,
                    callbackAction: callbackAction ? 'callback' : null
                }, '*');

                if (callbackAction) {
                    const handler = function(event) {
                        if (event.data && event.data.action === 'alertModalClosed') {
                            window.removeEventListener('message', handler);
                            callbackAction();
                        }
                    };
                    window.addEventListener('message', handler);
                }
            } else {
                console.error(mensagem);
                if (callbackAction) callbackAction();
            }
        }

        // ===== SALVAR =====

        async function salvar(e) {
            e.preventDefault();

            const form = document.getElementById('formPromocao');
            const formData = new FormData(form);
            const dados = Object.fromEntries(formData.entries());

            // Validar campos obrigatorios
            const erros = [];

            if (!dados.codigo || dados.codigo.trim() === '') {
                erros.push('- Codigo');
            }

            if (!dados.nome || dados.nome.trim() === '') {
                erros.push('- Nome');
            }

            if (filiaisSelecionadas.length === 0) {
                erros.push('- Filiais');
            }

            const tipoAtual = dados.tipo || 'DFIX';
            const valorInput = document.getElementById('valor');
            if (tipoAtual === 'DPOR') {
                // Percentual: exige valor unico preenchido
                if (!valorInput.value || valorInput.value.trim() === '' || valorInput.value === '0,00') {
                    erros.push('- Valor');
                }
            } else {
                // Fixo: exige ao menos 1 valor > 0 na tabela por filial
                const inputsFilial = document.querySelectorAll('.valor-filial-input');
                const temValorValido = inputsFilial.length > 0 && Array.from(inputsFilial).every(inp => {
                    const v = Currency.parse(inp.value || '0');
                    return v > 0;
                });
                if (!temValorValido) {
                    erros.push('- Valor do desconto por filial');
                }
            }

            if (erros.length > 0) {
                mostrarAlerta(i18n.requiredFields + '\n\n' + erros.join('\n'));
                return;
            }

            // Coleta valores por filial (quando DFIX); backend decide o que salvar
            dados.valores_filiais = {};
            document.querySelectorAll('.valor-filial-input').forEach(inp => {
                dados.valores_filiais[parseInt(inp.dataset.filialId)] = Currency.parse(inp.value || '0');
            });

            // Converter valor para formato backend (DPOR usa)
            dados.valor = tipoAtual === 'DPOR' ? Currency.parse(dados.valor) : 0;

            // Converter codigo para maiusculas
            dados.codigo = dados.codigo.toUpperCase();

            // Filiais
            dados.filiais_ids = document.getElementById('filiaisIdsJson').value || '[]';

            dados.grupos_ids = JSON.stringify(Array.from(document.getElementById('grupos').selectedOptions)
                .map(option => parseInt(option.value, 10))
                .filter(id => id > 0));

            // Onde Exibir
            dados.onde_exibir = document.getElementById('ondeExibirJson').value || 'SIS';

            try {
                const btnSalvar = document.getElementById('btnSalvar');
                btnSalvar.disabled = true;
                btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;

                let url;
                if (registroId) {
                    url = `/promocoes/${registroId}/atualizar`;
                } else {
                    url = '/promocoes/salvar';
                }

                const result = await API.post(url, dados);

                if (result.success) {
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'showToast',
                            type: 'success',
                            message: registroId ? i18n.updated : i18n.created
                        }, '*');
                    }
                    navegarPara('/pages/promocoes');
                } else {
                    mostrarAlerta(result.message || i18n.saveError);
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                mostrarAlerta(error.message || i18n.saveError);
            } finally {
                const btnSalvar = document.getElementById('btnSalvar');
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = '<i class="fas fa-save mr-2"></i>' + i18n.btnSave;
            }
        }

        // ===== INICIALIZACAO =====

        async function init() {
            await carregarFiliais();

            // Verificar se estamos editando
            const urlParams = new URLSearchParams(window.location.search);
            registroId = urlParams.get('id');

            if (registroId) {
                await carregarDados(registroId);
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            configurarDropdownFiliais();
            configurarDropdownOndeExibir();
            configurarCampoValor();

            document.getElementById('formPromocao').addEventListener('submit', salvar);

            document.getElementById('btnVoltar').addEventListener('click', function() {
                navegarPara('/pages/promocoes');
            });

            document.getElementById('btnCancelar').addEventListener('click', function() {
                navegarPara('/pages/promocoes');
            });

            init();
        });
    })();
</script>
@endsection
