@extends('layouts.iframe')

@section('title', '<?= t("modules.taxas_servicos.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.taxas_servicos.new_title') ?></h2>
        <button type="button" id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <form id="formTaxaServico" method="POST">
        <input type="hidden" id="id" name="id">

        <div class="form-section">
            <h3 class="form-section-title"><?= t('modules.taxas_servicos.sections.fee_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Nome -->
            <div class="form-input-group md:col-span-2">
                <label for="nome" class="form-label-group"><?= t('modules.taxas_servicos.fields.name') ?> <span class="text-red-500">*</span></label>
                <input type="text" id="nome" name="nome" class="form-input-group-field" required maxlength="100" placeholder="<?= t('modules.taxas_servicos.placeholders.name_example') ?>">
            </div>

            <!-- Filiais -->
            <div class="form-input-group md:col-span-2">
                <label class="form-label-group"><?= t('modules.taxas_servicos.fields.branches') ?> <span class="text-red-500">*</span></label>
                <div id="filiaisDropdown" class="filiais-dropdown">
                    <div class="filiais-dropdown-trigger" id="filiaisDropdownTrigger">
                        <span class="filiais-dropdown-text" id="filiaisDropdownText"><?= t('modules.taxas_servicos.placeholders.select_branches') ?></span>
                        <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                    </div>
                    <div class="filiais-dropdown-menu" id="filiaisDropdownMenu">
                        <div class="filiais-loading">
                            <i class="fas fa-spinner fa-spin"></i> <?= t('modules.taxas_servicos.messages.loading_branches') ?>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="filiaisIdsJson" name="filiais_ids">
            </div>

            <!-- Base de Calculo, Tipo de Valor e Valor na mesma linha -->
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Base de Calculo -->
                <div class="form-input-group">
                    <label for="base_calculo" class="form-label-group"><?= t('modules.taxas_servicos.fields.calculation_base') ?></label>
                    <select id="base_calculo" name="base_calculo" class="form-input-group-field">
                        <option value="FIX"><?= t('modules.taxas_servicos.calculation_options.fixed') ?></option>
                        <option value="PER"><?= t('modules.taxas_servicos.calculation_options.per_period') ?></option>
                        <option value="VLT"><?= t('modules.taxas_servicos.calculation_options.total_value') ?></option>
                    </select>
                </div>

                <!-- Tipo de Valor -->
                <div class="form-input-group">
                    <label for="tipo_valor" class="form-label-group"><?= t('modules.taxas_servicos.fields.value_type') ?></label>
                    <select id="tipo_valor" name="tipo_valor" class="form-input-group-field">
                        <option value="MON"><?= t('modules.taxas_servicos.value_type_options.monetary') ?></option>
                        <option value="POR"><?= t('modules.taxas_servicos.value_type_options.percentage') ?></option>
                    </select>
                </div>

                <!-- Valor -->
                <div class="form-input-group">
                    <label for="valor" class="form-label-group"><?= t('modules.taxas_servicos.fields.value') ?> <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span id="valorPrefix" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valor" name="valor" class="form-input-group-field pl-10 input-moeda" placeholder="0,00">
                    </div>
                </div>
            </div>

            <!-- Valores por filial (so tipo_valor=MON) -->
            <div id="valoresFiliaisSection" class="md:col-span-2 hidden">
                <h4 class="form-section-title mt-2 mb-2"><i class="fas fa-coins mr-2"></i><?= t('modules.taxas_servicos.sections.values_by_branch', [], 'Valores por filial') ?></h4>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.taxas_servicos.descriptions.values_by_branch', [], 'Como e valor monetario fixo, cada filial tem o valor na sua moeda.') ?></p>
                <div id="valoresFiliaisTabela" class="space-y-2">
                    <p class="text-sm text-slate-400 italic"><?= t('modules.taxas_servicos.messages.select_branches_first', [], 'Selecione ao menos uma filial para definir os valores.') ?></p>
                </div>
            </div>

            <!-- Aplicar Automaticamente -->
            <div class="form-input-group">
                <label for="aplicar" class="form-label-group"><?= t('modules.taxas_servicos.fields.auto_apply') ?> {!! aviso(t('modules.taxas_servicos.tooltips.auto_apply')) !!}</label>
                <select id="aplicar" name="aplicar" class="form-input-group-field">
                    <option value="N"><?= t('modules.taxas_servicos.apply_options.no') ?></option>
                    <option value="S"><?= t('modules.taxas_servicos.apply_options.yes') ?></option>
                </select>
            </div>

            <!-- Onde Usar -->
            <div class="form-input-group">
                <label class="form-label-group"><?= t('modules.taxas_servicos.fields.where_to_use') ?> {!! aviso(t('modules.taxas_servicos.tooltips.where_to_use')) !!}</label>
                <div id="ondeUsarDropdown" class="filiais-dropdown">
                    <div class="filiais-dropdown-trigger" id="ondeUsarDropdownTrigger">
                        <span class="filiais-dropdown-text" id="ondeUsarDropdownText"><?= t('modules.taxas_servicos.display_options.system') ?></span>
                        <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                    </div>
                    <div class="filiais-dropdown-menu" id="ondeUsarDropdownMenu">
                        <div class="filial-item selected" data-id="SIS" data-nome="<?= t('modules.taxas_servicos.display_options.system') ?>">
                            <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                                <input type="checkbox" class="onde-usar-checkbox" value="SIS" checked>
                                <span class="filial-nome"><?= t('modules.taxas_servicos.display_options.system') ?></span>
                            </label>
                        </div>
                        <div class="filial-item" data-id="SITE" data-nome="<?= t('modules.taxas_servicos.display_options.site') ?>">
                            <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                                <input type="checkbox" class="onde-usar-checkbox" value="SITE">
                                <span class="filial-nome"><?= t('modules.taxas_servicos.display_options.site') ?></span>
                            </label>
                        </div>
                        <div class="filial-item" data-id="APP" data-nome="<?= t('modules.taxas_servicos.display_options.app') ?>">
                            <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                                <input type="checkbox" class="onde-usar-checkbox" value="APP">
                                <span class="filial-nome"><?= t('modules.taxas_servicos.display_options.app') ?></span>
                            </label>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="ondeUsarJson" name="onde_usar">
            </div>
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
            editTitle: '<?= addslashes(t('modules.taxas_servicos.edit_title')) ?>',
            loadError: '<?= addslashes(t('modules.taxas_servicos.messages.load_error')) ?>',
            notFound: '<?= addslashes(t('modules.taxas_servicos.messages.not_found')) ?>',
            loadBranchesError: '<?= addslashes(t('modules.taxas_servicos.messages.load_branches_error')) ?>',
            loadBranchesText: '<?= addslashes(t('modules.taxas_servicos.messages.load_branches_text')) ?>',
            noBranches: '<?= addslashes(t('modules.taxas_servicos.messages.no_branches')) ?>',
            noBranchesText: '<?= addslashes(t('modules.taxas_servicos.messages.no_branches_text')) ?>',
            selectBranches: '<?= addslashes(t('modules.taxas_servicos.placeholders.select_branches')) ?>',
            allBranches: '<?= addslashes(t('modules.taxas_servicos.placeholders.all_branches')) ?>',
            select: '<?= addslashes(t('modules.taxas_servicos.placeholders.select')) ?>',
            displaySystem: '<?= addslashes(t('modules.taxas_servicos.display_options.system')) ?>',
            displaySite: '<?= addslashes(t('modules.taxas_servicos.display_options.site')) ?>',
            displayApp: '<?= addslashes(t('modules.taxas_servicos.display_options.app')) ?>',
            displayAll: '<?= addslashes(t('modules.taxas_servicos.display_options.all')) ?>',
            requiredFields: '<?= addslashes(t('modules.taxas_servicos.messages.required_fields')) ?>',
            saving: '<?= addslashes(t('modules.taxas_servicos.messages.saving')) ?>',
            saveError: '<?= addslashes(t('modules.taxas_servicos.messages.save_error')) ?>',
            created: '<?= addslashes(t('modules.taxas_servicos.messages.created')) ?>',
            updated: '<?= addslashes(t('modules.taxas_servicos.messages.updated')) ?>',
            btnSave: '<?= addslashes(t('common.buttons.save')) ?>',
        };

        let registroId = null;

        // Estado filiais
        let filiaisDisponiveis = [];
        let filiaisSelecionadas = [];
        let dropdownFiliaisAberto = false;

        // Estado onde usar
        let ondeUsarSelecionados = ['SIS'];
        let dropdownOndeUsarAberto = false;

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
                dropdownMenu.innerHTML = '<div class="filiais-dropdown-error">' + i18n.loadBranchesError + '</div>';
                dropdownText.textContent = i18n.loadBranchesText;
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

            // Re-render tabela de valores por filial se a secao estiver ativa
            const tipoValor = document.getElementById('tipo_valor')?.value;
            if (tipoValor === 'MON') renderizarTabelaValoresFiliais();
        }

        function atualizarTextoDropdownFiliais() {
            const dropdownText = document.getElementById('filiaisDropdownText');
            const selecionados = Array.from(document.querySelectorAll('.filial-checkbox:checked'))
                .map(cb => cb.closest('.filial-item').dataset.nome);

            if (selecionados.length === 0) {
                dropdownText.textContent = i18n.allBranches;
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
            const tipoValor = document.getElementById('tipo_valor')?.value;
            if (tipoValor === 'MON') renderizarTabelaValoresFiliais();
        }

        function configurarDropdownFiliais() {
            const dropdown = document.getElementById('filiaisDropdown');
            const trigger = document.getElementById('filiaisDropdownTrigger');

            trigger?.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownFiliaisAberto = !dropdownFiliaisAberto;
                if (dropdownFiliaisAberto) {
                    ajustarPosicaoMenu(dropdown);
                    attachMenuPositionListeners();
                } else {
                    limparPosicaoMenu(dropdown);
                    maybeDetachMenuPositionListeners();
                }
                dropdown.classList.toggle('open', dropdownFiliaisAberto);
            });
        }

        let menuPositionListenerAttached = false;

        function syncAbertosDropdownPosition() {
            if (dropdownFiliaisAberto) {
                ajustarPosicaoMenu(document.getElementById('filiaisDropdown'));
            }
            if (dropdownOndeUsarAberto) {
                ajustarPosicaoMenu(document.getElementById('ondeUsarDropdown'));
            }
        }

        function attachMenuPositionListeners() {
            if (menuPositionListenerAttached) return;
            menuPositionListenerAttached = true;
            window.addEventListener('resize', syncAbertosDropdownPosition);
            document.addEventListener('scroll', syncAbertosDropdownPosition, true);
        }

        function maybeDetachMenuPositionListeners() {
            if (dropdownFiliaisAberto || dropdownOndeUsarAberto) return;
            if (!menuPositionListenerAttached) return;
            menuPositionListenerAttached = false;
            window.removeEventListener('resize', syncAbertosDropdownPosition);
            document.removeEventListener('scroll', syncAbertosDropdownPosition, true);
        }

        function limparPosicaoMenu(dropdown) {
            if (!dropdown) return;
            const menu = dropdown.querySelector('.filiais-dropdown-menu');
            if (!menu) return;
            menu.classList.remove('filiais-dropdown-menu--fixed');
            menu.style.position = '';
            menu.style.left = '';
            menu.style.right = '';
            menu.style.top = '';
            menu.style.bottom = '';
            menu.style.width = '';
            menu.style.maxHeight = '';
            menu.style.zIndex = '';
            dropdown.classList.remove('open-up');
        }

        // Posicao fixed no viewport do iframe para o menu nao ser cortado por overflow de ancestrais
        function ajustarPosicaoMenu(dropdown) {
            const menu = dropdown.querySelector('.filiais-dropdown-menu');
            if (!menu) return;

            const rect = dropdown.getBoundingClientRect();
            const espacoAbaixo = window.innerHeight - rect.bottom;
            const espacoAcima = rect.top;
            const abrirPraCima = espacoAbaixo < 160 && espacoAcima > espacoAbaixo;
            dropdown.classList.toggle('open-up', abrirPraCima);

            const rem = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
            const pad = 0.5 * rem;

            menu.classList.add('filiais-dropdown-menu--fixed');
            menu.style.position = 'fixed';
            menu.style.left = (rect.left - pad) + 'px';
            menu.style.width = (rect.width + 2 * pad) + 'px';
            menu.style.right = 'auto';
            menu.style.zIndex = '99999';

            const disponivel = Math.max((abrirPraCima ? espacoAcima : espacoAbaixo) - 10, 80);
            menu.style.maxHeight = Math.min(disponivel, 200) + 'px';

            if (abrirPraCima) {
                menu.style.top = 'auto';
                menu.style.bottom = (window.innerHeight - rect.top + 1) + 'px';
            } else {
                menu.style.top = (rect.bottom + 1) + 'px';
                menu.style.bottom = 'auto';
            }
        }

        // ===== ONDE USAR =====

        function configurarDropdownOndeUsar() {
            const dropdown = document.getElementById('ondeUsarDropdown');
            const trigger = document.getElementById('ondeUsarDropdownTrigger');

            trigger?.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownOndeUsarAberto = !dropdownOndeUsarAberto;
                if (dropdownOndeUsarAberto) {
                    ajustarPosicaoMenu(dropdown);
                    attachMenuPositionListeners();
                } else {
                    limparPosicaoMenu(dropdown);
                    maybeDetachMenuPositionListeners();
                }
                dropdown.classList.toggle('open', dropdownOndeUsarAberto);
            });

            // Event listeners para checkboxes
            document.querySelectorAll('.onde-usar-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', handleOndeUsarCheckboxChange);
            });

            atualizarTextoDropdownOndeUsar();
            atualizarHiddenInputOndeUsar();
        }

        function handleOndeUsarCheckboxChange(e) {
            const checkbox = e.target;
            const item = checkbox.closest('.filial-item');
            const valor = checkbox.value;

            if (checkbox.checked) {
                item.classList.add('selected');
                if (!ondeUsarSelecionados.includes(valor)) {
                    ondeUsarSelecionados.push(valor);
                }
            } else {
                item.classList.remove('selected');
                ondeUsarSelecionados = ondeUsarSelecionados.filter(v => v !== valor);
            }

            atualizarTextoDropdownOndeUsar();
            atualizarHiddenInputOndeUsar();
        }

        function atualizarTextoDropdownOndeUsar() {
            const dropdownText = document.getElementById('ondeUsarDropdownText');
            const nomes = {
                'SIS': i18n.displaySystem,
                'SITE': i18n.displaySite,
                'APP': i18n.displayApp
            };

            const selecionados = ondeUsarSelecionados.map(v => nomes[v] || v);

            if (selecionados.length === 0) {
                dropdownText.textContent = i18n.select;
            } else if (selecionados.length === 3) {
                dropdownText.textContent = i18n.displayAll;
            } else {
                dropdownText.textContent = selecionados.join(', ');
            }
        }

        function atualizarHiddenInputOndeUsar() {
            document.getElementById('ondeUsarJson').value = ondeUsarSelecionados.join(',') || 'SIS';
        }

        function setOndeUsarSelecionados(valores) {
            ondeUsarSelecionados = valores;
            document.querySelectorAll('.onde-usar-checkbox').forEach(checkbox => {
                const isChecked = valores.includes(checkbox.value);
                checkbox.checked = isChecked;
                checkbox.closest('.filial-item').classList.toggle('selected', isChecked);
            });
            atualizarTextoDropdownOndeUsar();
            atualizarHiddenInputOndeUsar();
        }

        // Fechar dropdowns ao clicar fora
        document.addEventListener('click', function(e) {
            const dropdownFiliais = document.getElementById('filiaisDropdown');
            const dropdownOndeUsar = document.getElementById('ondeUsarDropdown');

            if (dropdownFiliais && !dropdownFiliais.contains(e.target)) {
                dropdownFiliaisAberto = false;
                dropdownFiliais.classList.remove('open');
                limparPosicaoMenu(dropdownFiliais);
                maybeDetachMenuPositionListeners();
            }
            if (dropdownOndeUsar && !dropdownOndeUsar.contains(e.target)) {
                dropdownOndeUsarAberto = false;
                dropdownOndeUsar.classList.remove('open');
                limparPosicaoMenu(dropdownOndeUsar);
                maybeDetachMenuPositionListeners();
            }
        });

        // ===== VALOR =====

        // Cache de valores digitados na tabela por filial (preservado entre re-renders)
        let valoresFiliaisCache = {};

        function configurarCampoValor() {
            const tipoValorSelect = document.getElementById('tipo_valor');
            tipoValorSelect.addEventListener('change', function() {
                aplicarVisibilidadeTipoValor(this.value);
            });
            aplicarVisibilidadeTipoValor(tipoValorSelect.value);
        }

        // Alterna entre input unico (POR) e tabela por filial (MON)
        function aplicarVisibilidadeTipoValor(tipo) {
            const valorPrefix = document.getElementById('valorPrefix');
            const valorInputContainer = document.getElementById('valor')?.closest('.form-input-group');
            const secaoFiliais = document.getElementById('valoresFiliaisSection');

            if (tipo === 'POR') {
                valorPrefix.textContent = '%';
                if (valorInputContainer) valorInputContainer.classList.remove('hidden');
                if (secaoFiliais) secaoFiliais.classList.add('hidden');
            } else { // MON
                valorPrefix.textContent = (typeof Currency !== 'undefined' && Currency.config) ? Currency.config.symbol : 'R$';
                // Oculta input unico — valores vao por filial
                if (valorInputContainer) valorInputContainer.classList.add('hidden');
                if (secaoFiliais) {
                    secaoFiliais.classList.remove('hidden');
                    renderizarTabelaValoresFiliais();
                }
            }
        }

        function renderizarTabelaValoresFiliais() {
            const container = document.getElementById('valoresFiliaisTabela');
            if (!container) return;

            // Captura valores atuais antes de re-renderizar
            container.querySelectorAll('.valor-filial-input').forEach(inp => {
                valoresFiliaisCache[parseInt(inp.dataset.filialId)] = inp.value;
            });

            const mapaFiliais = {};
            (filiaisDisponiveis || []).forEach(f => { mapaFiliais[parseInt(f.id)] = f; });

            const participantes = (filiaisSelecionadas || [])
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

            if (participantes.length === 0) {
                container.innerHTML = '<p class="text-sm text-slate-400 italic"><?= addslashes(t('modules.taxas_servicos.messages.select_branches_first', [], 'Selecione ao menos uma filial para definir os valores.')) ?></p>';
                return;
            }

            const symbolMap = { BRL: 'R$', EUR: '€', USD: '$', GBP: '£' };
            let html = '<div class="overflow-x-auto"><table class="w-full border-collapse">' +
                '<thead><tr class="border-b border-slate-200">' +
                '<th class="text-left py-2 px-3 text-sm font-medium text-slate-600">Filial</th>' +
                '<th class="text-left py-2 px-3 text-sm font-medium text-slate-600 w-64">Valor</th>' +
                '</tr></thead><tbody>';

            participantes.forEach(f => {
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

            // Mascara de moeda
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
                const result = await API.get(`/api/taxas-e-servicos/${id}`);

                if (result.success && result.data) {
                    preencherFormulario(result.data);
                } else {
                    mostrarAlerta(i18n.loadError + ': ' + (result.message || i18n.notFound), function() {
                        navegarPara('/pages/taxas-e-servicos');
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
                mostrarAlerta(i18n.loadError, function() {
                    navegarPara('/pages/taxas-e-servicos');
                });
            }
        }

        function preencherFormulario(data) {
            document.getElementById('id').value = data.id;
            document.getElementById('nome').value = data.nome || '';
            document.getElementById('base_calculo').value = data.base_calculo || 'FIX';
            document.getElementById('tipo_valor').value = data.tipo_valor || 'MON';
            document.getElementById('aplicar').value = data.aplicar || 'N';

            // Valor formatado usando Currency helper
            Currency.setValue('#valor', data.valor || 0);

            // Popular cache de valores por filial (pra tipo_valor=MON)
            valoresFiliaisCache = {};
            if (data.valores_filiais && typeof data.valores_filiais === 'object') {
                Object.keys(data.valores_filiais).forEach(fid => {
                    const v = parseFloat(data.valores_filiais[fid] || 0);
                    valoresFiliaisCache[parseInt(fid)] = v.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
                });
            }

            // Filiais (seta antes de aplicar visibilidade do tipo_valor)
            if (data.filiais && data.filiais.length > 0) {
                setFiliaisSelecionadas(data.filiais);
            }

            // Aplica visibilidade final (mostra tabela se MON, input unico se POR)
            aplicarVisibilidadeTipoValor(data.tipo_valor || 'MON');

            // Onde Usar
            if (data.onde_usar) {
                setOndeUsarSelecionados(data.onde_usar.split(','));
            }

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
                alert(mensagem);
                if (callbackAction) callbackAction();
            }
        }

        // ===== SALVAR =====

        async function salvar(e) {
            e.preventDefault();

            const form = document.getElementById('formTaxaServico');
            const formData = new FormData(form);
            const dados = Object.fromEntries(formData.entries());

            // Validar campos obrigatorios
            const erros = [];

            if (!dados.nome || dados.nome.trim() === '') {
                erros.push('- Nome');
            }

            if (filiaisSelecionadas.length === 0) {
                erros.push('- Filiais');
            }

            const tipoValor = document.getElementById('tipo_valor').value;

            if (tipoValor === 'POR') {
                const valorInput = document.getElementById('valor');
                if (!valorInput.value || valorInput.value.trim() === '' || valorInput.value === '0,00') {
                    erros.push('- Valor');
                }
            } else {
                // MON: todas as filiais selecionadas devem ter valor > 0
                const inputs = document.querySelectorAll('.valor-filial-input');
                let todosComValor = inputs.length > 0;
                inputs.forEach(inp => {
                    if (!inp.value || inp.value === '0,00' || Currency.parse(inp.value) <= 0) {
                        todosComValor = false;
                    }
                });
                if (!todosComValor) {
                    erros.push('- Valor (todas as filiais)');
                }
            }

            if (erros.length > 0) {
                mostrarAlerta(i18n.requiredFields + '\n\n' + erros.join('\n'));
                return;
            }

            // Converter valor para formato backend (POR usa campo unico; MON usa valores por filial)
            dados.valor = tipoValor === 'POR' ? Currency.parse(dados.valor) : null;

            // Coletar valores por filial (so quando tipo_valor=MON)
            if (tipoValor === 'MON') {
                const valoresFiliais = {};
                let primeiroValorFilial = null;
                document.querySelectorAll('.valor-filial-input').forEach(inp => {
                    const fid = parseInt(inp.dataset.filialId);
                    if (fid > 0) {
                        const valor = Currency.parse(inp.value);
                        valoresFiliais[fid] = valor;
                        if (primeiroValorFilial === null) primeiroValorFilial = valor;
                    }
                });
                dados.valores_filiais = valoresFiliais;
                dados.valor = primeiroValorFilial;
            }

            // Filiais
            dados.filiais_ids = document.getElementById('filiaisIdsJson').value || '[]';

            // Onde Usar
            dados.onde_usar = document.getElementById('ondeUsarJson').value || 'SIS';

            try {
                const btnSalvar = document.getElementById('btnSalvar');
                btnSalvar.disabled = true;
                btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;

                let url;
                if (registroId) {
                    url = `/taxas-e-servicos/${registroId}/atualizar`;
                } else {
                    url = '/taxas-e-servicos/salvar';
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
                    navegarPara('/pages/taxas-e-servicos');
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
            configurarDropdownOndeUsar();
            configurarCampoValor();

            document.getElementById('formTaxaServico').addEventListener('submit', salvar);

            document.getElementById('btnVoltar').addEventListener('click', function() {
                navegarPara('/pages/taxas-e-servicos');
            });

            document.getElementById('btnCancelar').addEventListener('click', function() {
                navegarPara('/pages/taxas-e-servicos');
            });

            init();
        });
    })();
</script>
@endsection
