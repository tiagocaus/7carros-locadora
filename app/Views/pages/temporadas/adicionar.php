@extends('layouts.iframe')

@section('title', '<?= t("modules.temporadas.new_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho com titulo e botao voltar -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.temporadas.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formTemporada" method="POST">
        @csrf
        <input type="hidden" id="temporadaId" name="id">

        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.temporadas.sections.season_data') ?></h3>

            <!-- Grid: Nome | Pais -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-8 form-input-group">
                    <label for="temporadaNome" class="form-label-group"><?= t('modules.temporadas.fields.name') ?> <span class="text-red-500">*</span></label>
                    <input type="text" id="temporadaNome" name="nome" class="form-input-group-field" placeholder="<?= t('modules.temporadas.placeholders.name_example') ?>" required>
                </div>

                <div class="md:col-span-4 form-input-group">
                    <label for="temporadaPais" class="form-label-group"><?= t('modules.temporadas.fields.country') ?></label>
                    <select id="temporadaPais" name="pais" class="form-input-group-field">
                        <option value="BR"><?= t('modules.temporadas.countries.BR') ?></option>
                        <option value="US"><?= t('modules.temporadas.countries.US') ?></option>
                        <option value="IT"><?= t('modules.temporadas.countries.IT') ?></option>
                        <option value="ES"><?= t('modules.temporadas.countries.ES') ?></option>
                        <option value="PT"><?= t('modules.temporadas.countries.PT') ?></option>
                    </select>
                </div>
            </div>

            <!-- Grid: Periodo -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.temporadas.fields.period_start') ?> <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <select id="diaInicio" name="dia_inicio" class="form-input-group-field" required>
                            <?php for ($i = 1; $i <= 31; $i++): ?>
                            <option value="<?= $i ?>"><?= str_pad($i, 2, '0', STR_PAD_LEFT) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="mesInicio" name="mes_inicio" class="form-input-group-field" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"><?= t("modules.temporadas.months.$m") ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.temporadas.fields.period_end') ?> <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <select id="diaFim" name="dia_fim" class="form-input-group-field" required>
                            <?php for ($i = 1; $i <= 31; $i++): ?>
                            <option value="<?= $i ?>"><?= str_pad($i, 2, '0', STR_PAD_LEFT) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="mesFim" name="mes_fim" class="form-input-group-field" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"><?= t("modules.temporadas.months.$m") ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Grid: Ativo -->
            <div class="mt-4">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="temporadaAtivo" name="ativo" value="1" class="mr-2" checked>
                    <span class="text-sm text-slate-700"><?= t('modules.temporadas.fields.active') ?></span>
                </label>
            </div>
        </div>

        <!-- Secao Ajustes por Grupo (so aparece ao editar) -->
        <div id="ajustesSection" class="form-section mb-6 hidden">
            <h3 class="form-section-title"><?= t('modules.temporadas.sections.group_adjustments') ?></h3>
            <p class="text-sm text-slate-500 mb-4">
                <i class="fas fa-info-circle mr-1"></i>
                <?= t('modules.temporadas.descriptions.adjustments') ?>
            </p>
            <div id="ajustesContainer">
                <div class="flex items-center justify-center py-4">
                    <i class="fas fa-spinner fa-spin text-slate-400 mr-2"></i>
                    <span class="text-slate-500"><?= t('modules.temporadas.messages.loading_groups') ?></span>
                </div>
            </div>
        </div>

        <!-- Botoes de acao -->
        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-6 rounded-md text-sm font-medium">
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
        editTitle: '<?= addslashes(t('modules.temporadas.edit_title')) ?>',
        loadSeasonError: '<?= addslashes(t('modules.temporadas.messages.load_season_error')) ?>',
        loadAdjustmentsError: '<?= addslashes(t('modules.temporadas.messages.load_adjustments_error')) ?>',
        noGroups: '<?= addslashes(t('modules.temporadas.messages.no_groups')) ?>',
        saving: '<?= addslashes(t('modules.temporadas.messages.saving')) ?>',
        saveError: '<?= addslashes(t('modules.temporadas.messages.save_error')) ?>',
        requestError: <?= js_t('modules.temporadas.messages.request_error') ?>,
        btnSave: '<?= addslashes(t('common.buttons.save')) ?>',
    };

    // Estado
    let currentId = null;
    let isEditMode = false;

    // Elementos
    const form = document.getElementById('formTemporada');
    const pageTitle = document.getElementById('pageTitle');
    const ajustesSection = document.getElementById('ajustesSection');
    const ajustesContainer = document.getElementById('ajustesContainer');

    // Verificar se esta em modo edicao (via query string)
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('id');

    if (editId) {
        isEditMode = true;
        currentId = editId;
        carregarTemporada(editId);
    }

    // Botao voltar - Navega de volta para lista
    document.getElementById('btnVoltar')?.addEventListener('click', function() {
        navegarParaLista();
    });

    // Botao cancelar
    document.getElementById('btnCancelar')?.addEventListener('click', function() {
        navegarParaLista();
    });

    function navegarParaLista() {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'navigate',
                page: '/pages/temporadas'
            }, '*');
        } else {
            window.location.href = '/pages/temporadas';
        }
    }

    // Carregar dados da temporada para edicao
    async function carregarTemporada(id) {
        try {
            const result = await API.get(`/api/temporadas/${id}`);

            if (!result.success || !result.data) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.loadSeasonError }, '*');
                navegarParaLista();
                return;
            }

            const t = result.data;

            // Preencher formulario
            document.getElementById('temporadaId').value = t.id;
            document.getElementById('temporadaNome').value = t.nome;
            document.getElementById('temporadaPais').value = t.pais;
            document.getElementById('diaInicio').value = t.dia_inicio;
            document.getElementById('mesInicio').value = t.mes_inicio;
            document.getElementById('diaFim').value = t.dia_fim;
            document.getElementById('mesFim').value = t.mes_fim;
            document.getElementById('temporadaAtivo').checked = t.ativo == 1;

            // Atualizar titulo
            pageTitle.textContent = i18n.editTitle.replace(':name', t.nome);

            // Mostrar secao de ajustes
            ajustesSection.classList.remove('hidden');
            carregarAjustes(id);

        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.loadSeasonError }, '*');
            navegarParaLista();
        }
    }

    // Carregar ajustes por grupo
    async function carregarAjustes(temporadaId) {
        try {
            const result = await API.get(`/api/temporadas/${temporadaId}/ajustes`);

            if (result.success && result.data) {
                renderizarAjustes(result.data);
            } else {
                ajustesContainer.innerHTML = `<p class="text-red-500 text-sm">${i18n.loadAdjustmentsError}</p>`;
            }
        } catch (error) {
            console.error('Erro:', error);
            ajustesContainer.innerHTML = `<p class="text-red-500 text-sm">${i18n.loadAdjustmentsError}</p>`;
        }
    }

    function renderizarAjustes(grupos) {
        if (!grupos || grupos.length === 0) {
            ajustesContainer.innerHTML = `<p class="text-slate-500 text-sm">${i18n.noGroups}</p>`;
            return;
        }

        let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

        grupos.forEach(g => {
            const valor = g.ajuste_percentual !== null ? g.ajuste_percentual : '';

            html += `
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg">
                    <span class="flex-1 text-sm text-slate-700 font-medium">${escapeHtml(g.grupo_nome)}</span>
                    <div class="flex items-center gap-1">
                        <input type="number" step="0.01" class="form-input-group-field w-20 text-right text-sm ajuste-input"
                            data-grupo="${g.id_grupo}" value="${valor}" placeholder="0">
                        <span class="text-sm text-slate-500">%</span>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        ajustesContainer.innerHTML = html;
    }

    // Submissao do formulario
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const btn = document.getElementById('btnSalvar');
        const originalText = btn.innerHTML;

        // Checkbox ativo
        if (!document.getElementById('temporadaAtivo').checked) {
            formData.set('ativo', '0');
        }

        const url = isEditMode ? `/temporadas/${currentId}/atualizar` : '/temporadas/salvar';

        try {
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                // Se estiver editando e tiver ajustes, salvar ajustes tambem
                if (isEditMode) {
                    await salvarAjustes();
                }

                window.parent.postMessage({ action: 'showToast', message: result.message || (isEditMode ? '<?= addslashes(t('modules.temporadas.messages.updated')) ?>' : '<?= addslashes(t('modules.temporadas.messages.created')) ?>') }, '*');
                navegarParaLista();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.saveError }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.requestError }, '*');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });

    // Salvar ajustes
    async function salvarAjustes() {
        const inputs = document.querySelectorAll('.ajuste-input');
        if (inputs.length === 0) return;

        const ajustes = {};
        inputs.forEach(input => {
            const grupoId = input.dataset.grupo;
            const valor = input.value.trim();
            ajustes[grupoId] = valor === '' ? null : parseFloat(valor);
        });

        try {
            await API.post(`/temporadas/${currentId}/ajustes`, { ajustes });
        } catch (error) {
            console.error('Erro ao salvar ajustes:', error);
        }
    }

    // Helper
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
@endsection
