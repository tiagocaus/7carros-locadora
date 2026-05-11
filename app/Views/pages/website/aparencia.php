@extends('layouts.iframe')

@section('title', '<?= t("modules.website.appearance_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page"><?= t('modules.website.appearance_title') ?></h2>
    </div>

    <form id="formAparencia" method="POST">
        @csrf

        <!-- Presets de Cor -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.website.color_preset') ?></h3>
            <div id="presetsContainer" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3"></div>
        </div>

        <!-- Cores Customizadas -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.website.custom_colors') ?></h3>
            <div id="coresContainer" class="grid grid-cols-2 sm:grid-cols-5 gap-4"></div>
        </div>

        <!-- Logo -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.website.site_logo') ?></h3>
            <p class="text-xs text-slate-400 mb-3"><?= t('modules.website.site_logo_help') ?></p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="form-input-group">
                    <label for="logoInput" class="form-label-group"><?= t('modules.website.site_logo') ?></label>
                    <input type="file" id="logoInput" accept="image/*" class="form-input-group-field">
                    <div id="logoPreview" class="mt-2 hidden">
                        <img id="logoImg" src="" alt="Logo" class="h-16 object-contain">
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                        <span class="text-sm font-medium"><?= t('modules.website.logo_white_bg') ?></span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="logo_fundo_branco" name="logo_fundo_branco" value="1" class="sr-only peer" checked>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <div class="form-input-group">
                        <label for="logo_alinhamento" class="form-label-group"><?= t('modules.website.logo_alignment') ?></label>
                        <select id="logo_alinhamento" name="logo_alinhamento" class="form-input-group-field">
                            <option value="centro"><?= t('modules.website.logo_center') ?></option>
                            <option value="esquerda"><?= t('modules.website.logo_left') ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fonte -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.website.primary_font') ?></h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="form-input-group">
                    <label for="fonte_primaria" class="form-label-group"><?= t('modules.website.primary_font') ?></label>
                    <input type="text" id="fonte_primaria" name="fonte_primaria" class="form-input-group-field" value="Titillium Web">
                </div>
                <div class="form-input-group">
                    <label for="fonte_url" class="form-label-group"><?= t('modules.website.font_url') ?></label>
                    <input type="text" id="fonte_url" name="fonte_url" class="form-input-group-field" placeholder="https://fonts.googleapis.com/css2?family=...">
                </div>
            </div>
        </div>

        <!-- CSS Customizado -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.website.custom_css') ?></h3>
            <div class="form-input-group">
                <textarea id="css_customizado" name="css_customizado" rows="8" class="form-input-group-field font-mono text-sm" placeholder="/* CSS customizado */"></textarea>
            </div>
            <div class="flex gap-2 mt-2">
                <button type="button" id="btnCssReset" class="text-sm text-red-500 hover:text-red-700">
                    <i class="fas fa-undo mr-1"></i> <?= t('modules.website.css_reset') ?>
                </button>
                <button type="button" id="btnCssUndo" class="text-sm text-blue-500 hover:text-blue-700 hidden">
                    <i class="fas fa-redo mr-1"></i> <?= t('modules.website.css_undo') ?>
                </button>
            </div>
        </div>

        <!-- Botao Salvar -->
        <div class="flex justify-end">
            <button type="submit" class="btn-blue py-2.5 px-6 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow">
                <i class="fas fa-save mr-2"></i> <?= t('common.buttons.save') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function() {
    let presetsFixos = {};
    let presetsCustom = [];
    let presetAtual = 'azul';
    let coresCustomizadas = {};

    pageLoading.start();
    carregarDados();

    async function carregarDados() {
        try {
            const result = await API.get('/api/website/aparencia');
            if (result.success) {
                presetsFixos = result.presets_fixos || {};
                presetsCustom = result.presets_custom || [];

                if (result.data) {
                    presetAtual = result.data.preset_cor || 'azul';
                    coresCustomizadas = result.data.cores_customizadas || {};

                    document.getElementById('css_customizado').value = result.data.css_customizado || '';
                    document.getElementById('fonte_primaria').value = result.data.fonte_primaria || 'Titillium Web';
                    document.getElementById('fonte_url').value = result.data.fonte_url || '';
                    document.getElementById('logo_fundo_branco').checked = result.data.logo_fundo_branco == 1;
                    if (result.data.logo_alinhamento) document.getElementById('logo_alinhamento').value = result.data.logo_alinhamento;

                    if (result.data.logo_url) {
                        document.getElementById('logoPreview').classList.remove('hidden');
                        document.getElementById('logoImg').src = result.data.logo_url;
                    }

                    if (result.data.css_customizado_backup) {
                        document.getElementById('btnCssUndo').classList.remove('hidden');
                    }
                }

                renderizarPresets();
                renderizarCores();
            }
        } catch (error) {
            console.error('Erro:', error);
            toast.error('Erro ao carregar aparencia');
        } finally {
            pageLoading.done();
        }
    }

    function renderizarPresets() {
        const container = document.getElementById('presetsContainer');
        container.innerHTML = '';

        // Presets fixos
        Object.entries(presetsFixos).forEach(([nome, cores]) => {
            container.appendChild(criarPresetCard(nome, cores, false));
        });

        // Presets customizados
        presetsCustom.forEach(p => {
            container.appendChild(criarPresetCard(p.nome, p.cores, true, p.id));
        });
    }

    function criarPresetCard(nome, cores, isDeletable, id) {
        const div = document.createElement('div');
        const isActive = nome === presetAtual;
        div.className = 'cursor-pointer rounded-lg p-3 border-2 transition-all ' +
            (isActive ? 'border-blue-500 shadow-md' : 'border-slate-200 hover:border-slate-400');
        div.innerHTML = `
            <div class="flex gap-1 mb-2">
                ${Object.values(cores).slice(0, 5).map(c => `<div class="w-4 h-4 rounded-full" style="background:${c}"></div>`).join('')}
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium capitalize">${nome}</span>
                ${isDeletable ? `<button type="button" class="text-red-400 hover:text-red-600 text-xs preset-delete" data-id="${id}"><i class="fas fa-times"></i></button>` : ''}
            </div>
        `;
        div.addEventListener('click', (e) => {
            if (e.target.closest('.preset-delete')) return;
            presetAtual = nome;
            coresCustomizadas = {};
            renderizarPresets();
            renderizarCores();
        });
        return div;
    }

    function renderizarCores() {
        const container = document.getElementById('coresContainer');
        container.innerHTML = '';

        // Resolver cores atuais
        let coresBase = presetsFixos[presetAtual] || {};
        if (!coresBase || Object.keys(coresBase).length === 0) {
            const custom = presetsCustom.find(p => p.nome === presetAtual);
            coresBase = custom ? custom.cores : presetsFixos['azul'];
        }
        const coresFinais = { ...coresBase, ...coresCustomizadas };

        for (let i = 1; i <= 10; i++) {
            const varName = '--cor-' + i;
            const valor = coresFinais[varName] || '#555555';
            const div = document.createElement('div');
            div.className = 'form-input-group';
            div.innerHTML = `
                <label class="form-label-group text-xs">${varName}</label>
                <div class="flex items-center gap-2">
                    <input type="color" class="w-10 h-8 rounded cursor-pointer cor-picker" data-var="${varName}" value="${valor.substring(0, 7)}">
                    <input type="text" class="form-input-group-field text-xs font-mono cor-text" data-var="${varName}" value="${valor}">
                </div>
            `;
            container.appendChild(div);
        }

        // Eventos de sync
        container.querySelectorAll('.cor-picker').forEach(el => {
            el.addEventListener('input', function() {
                const varName = this.dataset.var;
                coresCustomizadas[varName] = this.value;
                container.querySelector(`.cor-text[data-var="${varName}"]`).value = this.value;
            });
        });
        container.querySelectorAll('.cor-text').forEach(el => {
            el.addEventListener('change', function() {
                const varName = this.dataset.var;
                coresCustomizadas[varName] = this.value;
                const picker = container.querySelector(`.cor-picker[data-var="${varName}"]`);
                if (this.value.match(/^#[0-9a-fA-F]{6}$/)) picker.value = this.value;
            });
        });
    }

    // Delete preset customizado
    document.getElementById('presetsContainer').addEventListener('click', async function(e) {
        const btn = e.target.closest('.preset-delete');
        if (!btn) return;
        e.stopPropagation();

        try {
            await API.delete('/api/website/presets/' + btn.dataset.id);
            presetsCustom = presetsCustom.filter(p => p.id != btn.dataset.id);
            if (presetAtual === btn.closest('[class*=cursor-pointer]')?.querySelector('span')?.textContent) {
                presetAtual = 'azul';
            }
            renderizarPresets();
            toast.success('<?= t("common.messages.deleted") ?>');
        } catch (error) {
            toast.error('<?= t("common.messages.error") ?>');
        }
    });

    // CSS Reset
    document.getElementById('btnCssReset').addEventListener('click', async function() {
        try {
            await API.post('/api/website/aparencia/reset', { action: 'reset' });
            document.getElementById('css_customizado').value = '';
            document.getElementById('btnCssUndo').classList.remove('hidden');
            toast.success('<?= t("common.messages.saved") ?>');
        } catch (error) {
            toast.error('<?= t("common.messages.error") ?>');
        }
    });

    // CSS Undo
    document.getElementById('btnCssUndo').addEventListener('click', async function() {
        try {
            const result = await API.post('/api/website/aparencia/reset', { action: 'undo' });
            if (result.success) {
                carregarDados();
                toast.success('<?= t("common.messages.saved") ?>');
            }
        } catch (error) {
            toast.error('<?= t("common.messages.error") ?>');
        }
    });

    // Logo preview
    document.getElementById('logoInput').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('logoPreview').classList.remove('hidden');
                document.getElementById('logoImg').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // Submit
    document.getElementById('formAparencia').addEventListener('submit', async function(e) {
        e.preventDefault();

        const dados = {
            preset_cor: presetAtual,
            cores_customizadas: Object.keys(coresCustomizadas).length > 0 ? coresCustomizadas : null,
            css_customizado: document.getElementById('css_customizado').value || null,
            fonte_primaria: document.getElementById('fonte_primaria').value,
            fonte_url: document.getElementById('fonte_url').value || null,
            logo_fundo_branco: document.getElementById('logo_fundo_branco').checked ? 1 : 0,
            logo_alinhamento: document.getElementById('logo_alinhamento').value,
        };

        // Upload logo se selecionado (envia base64; backend converte em arquivo)
        const logoFile = document.getElementById('logoInput').files[0];
        if (logoFile) {
            const reader = new FileReader();
            reader.onload = async function(e) {
                dados.logo_base64 = e.target.result;
                await salvar(dados);
            };
            reader.readAsDataURL(logoFile);
        } else {
            await salvar(dados);
        }
    });

    async function salvar(dados) {
        try {
            const result = await API.post('/api/website/aparencia', dados);
            if (result.success) {
                toast.success('<?= t("common.messages.saved") ?>');
            } else {
                toast.error(result.message || '<?= t("common.messages.error") ?>');
            }
        } catch (error) {
            toast.error('<?= t("common.messages.error") ?>');
        }
    }
})();
</script>
@endsection
