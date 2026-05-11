@extends('layouts.iframe')

@php
    $isEdit = !empty($role);
    $isSystem = $role['is_system'] ?? false;
    $isCustomization = $role['is_customization'] ?? false;

    $title = $isEdit ? t('modules.roles.edit_title') : t('modules.roles.new_title');
    $successMessage = $isEdit ? t('modules.roles.messages.update_success') : t('modules.roles.messages.create_success');
    $formAction = $isEdit ? '/roles/' . $role['id'] . '/atualizar' : '/roles/salvar';

    if ($isEdit) {
        if ($isSystem) {
            $buttonText = t('modules.roles.actions.create_copy');
        } else {
            $buttonText = t('modules.roles.actions.save_changes');
        }
    } else {
        $buttonText = t('modules.roles.actions.save_role');
    }
@endphp

@section('title', $title)

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Tela de Sucesso (inicialmente oculta) -->
    <div id="successScreen" class="hidden">
        <div class="flex flex-col items-center justify-center py-16">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-check text-green-500 text-4xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-slate-700 mb-2">{{ $successMessage }}</h3>
            <p class="text-slate-500 text-sm" id="closingText"></p>
        </div>
    </div>

    <!-- Formulário -->
    <form id="formRole" method="POST" action="{{ $formAction }}">
        @csrf

        @if($isEdit && $isSystem)
        <!-- Aviso de Role de Sistema -->
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-shield-alt text-amber-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-amber-800"><?= t('modules.roles.warnings.system_role_title') ?></h3>
                    <p class="text-sm text-amber-700 mt-1">
                        <?= t('modules.roles.warnings.system_role_desc') ?>
                    </p>
                </div>
            </div>
        </div>
        @endif

        @if($isEdit && $isCustomization)
        <!-- Badge de customização -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-edit text-blue-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800"><?= t('modules.roles.warnings.custom_role_title') ?></h3>
                    <p class="text-sm text-blue-700 mt-1">
                        <?= t('modules.roles.warnings.custom_role_desc') ?>
                    </p>
                </div>
            </div>
        </div>
        @endif

        <div>
            <!-- Seção: Dados da Função -->
            <div class="form-section mb-6">
                <h3 class="form-section-title">
                    <?= t('modules.roles.sections.role_data') ?>
                    @if($isEdit && $isSystem)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                        <i class="fas fa-shield-alt mr-1"></i> <?= t('modules.roles.badges.system') ?>
                    </span>
                    @endif
                </h3>

                <!-- Campo: Nome -->
                <div class="grid grid-cols-1 gap-4 mt-4">
                    <div class="form-input-group">
                        <label for="roleName" class="form-label-group"><?= t('modules.roles.fields.name') ?> <span class="text-red-500">*</span></label>
                        @if($isEdit && $isCustomization)
                        <input type="text" id="roleName" name="name"
                               class="form-input-group-field bg-slate-100 cursor-not-allowed"
                               value="{{ $role['name'] ?? '' }}"
                               placeholder="<?= t('modules.roles.placeholders.name_full') ?>"
                               readonly
                               title="<?= t('modules.roles.warnings.name_locked_title') ?>"
                               required>
                        <p class="text-xs text-slate-500 mt-1">
                            <i class="fas fa-lock mr-1"></i><?= t('modules.roles.warnings.name_locked') ?>
                        </p>
                        @else
                        <input type="text" id="roleName" name="name"
                               class="form-input-group-field"
                               value="{{ $role['name'] ?? '' }}"
                               placeholder="<?= t('modules.roles.placeholders.name_full') ?>"
                               required
                               {{ !$isEdit ? 'autofocus' : '' }}>
                        @endif
                    </div>
                </div>

                <!-- Campo: Descrição -->
                <div class="grid grid-cols-1 gap-4 mt-4">
                    <div class="form-input-group">
                        <label for="roleDescription" class="form-label-group"><?= t('modules.roles.fields.description') ?></label>
                        <textarea id="roleDescription" name="description" class="form-input-group-field" rows="3"
                                  placeholder="<?= t('modules.roles.placeholders.description_full') ?>">{{ $role['description'] ?? '' }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Seção: Permissões -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.roles.sections.permissions') ?></h3>
                <p class="text-sm text-slate-500 mb-4"><?= t('modules.roles.sections.permissions_desc') ?></p>

                <!-- Container de permissões (carregado via JS) -->
                <div id="permissionsContainer">
                    <div class="flex items-center justify-center py-8">
                        <i class="fas fa-spinner fa-spin text-slate-400 mr-2"></i>
                        <span class="text-slate-500"><?= t('modules.roles.messages.loading_permissions') ?></span>
                    </div>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" id="btnCancelarFormRole" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('common.buttons.cancel') ?>
                </button>
                <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    {{ $buttonText }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const i18n = {
        saving: '<?= addslashes(t("common.labels.saving")) ?>',
        saveError: '<?= addslashes(t("modules.roles.messages.save_error")) ?>',
        processError: '<?= addslashes(t("modules.roles.messages.process_error")) ?>',
        loadPermissionsError: '<?= addslashes(t("modules.roles.messages.load_permissions_error")) ?>',
        noPermissions: '<?= addslashes(t("modules.roles.messages.no_permissions")) ?>',
        selectAll: '<?= addslashes(t("modules.roles.actions.select_all")) ?>',
        copyCreated: '<?= addslashes(t("modules.roles.messages.copy_created")) ?>',
        closingCountdown: '<?= addslashes(t("modules.roles.messages.closing_countdown")) ?>',
        moduleNames: <?= json_encode(t("modules.roles.module_names"), JSON_UNESCAPED_UNICODE) ?>,
    };

    const form = document.getElementById('formRole');
    const btnCancelar = document.getElementById('btnCancelarFormRole');
    const permissionsContainer = document.getElementById('permissionsContainer');

    // Modo de edição e permissões já selecionadas
    const isEdit = {{ $isEdit ? 'true' : 'false' }};
    const isSystem = {{ ($isEdit && $isSystem) ? 'true' : 'false' }};
    const selectedPermissions = @json($role['permissions'] ?? []);

    // Carregar permissões
    carregarPermissoes();

    // Botão Cancelar - fechar offcanvas
    btnCancelar.addEventListener('click', function() {
        fecharOffcanvas();
    });

    // Submit do formulário
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                // Esconder formulário
                form.classList.add('hidden');

                // Atualizar mensagem de sucesso se foi criada cópia customizada
                if (result.data && result.data.is_customized) {
                    document.querySelector('#successScreen h3').textContent = i18n.copyCreated;
                }

                // Mostrar tela de sucesso
                document.getElementById('successScreen').classList.remove('hidden');

                // Countdown e auto-close após 3 segundos
                let count = 3;
                const closingEl = document.getElementById('closingText');
                closingEl.textContent = i18n.closingCountdown.replace(':seconds', count);
                const interval = setInterval(() => {
                    count--;
                    closingEl.textContent = i18n.closingCountdown.replace(':seconds', count);
                    if (count <= 0) {
                        clearInterval(interval);
                        fecharOffcanvas();
                    }
                }, 1000);
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.saveError }, '*');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.processError }, '*');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    // Função para carregar permissões
    async function carregarPermissoes() {
        try {
            const result = await API.get('/api/permissions');

            if (result.success && result.data) {
                renderizarPermissoes(result.data);
            } else {
                permissionsContainer.innerHTML = `<p class="text-red-500 text-sm">${i18n.loadPermissionsError}</p>`;
            }
        } catch (error) {
            console.error('Erro ao carregar permissões:', error);
            permissionsContainer.innerHTML = '<p class="text-red-500 text-sm">Erro ao carregar permissões.</p>';
        }
    }

    // Função para renderizar permissões agrupadas por módulo
    function renderizarPermissoes(grupos) {
        if (!grupos || grupos.length === 0) {
            permissionsContainer.innerHTML = `<p class="text-slate-500 text-sm">${i18n.noPermissions}</p>`;
            return;
        }

        let html = '';

        grupos.forEach(grupo => {
            const moduleName = formatarNomeModulo(grupo.module);

            html += `
                <div class="mb-4 pb-4 border-b border-slate-200 last:border-b-0 last:pb-0 last:mb-0">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-sm font-semibold text-slate-700">${moduleName}</h4>
                        <label class="flex items-center text-xs text-slate-500 cursor-pointer hover:text-slate-700">
                            <input type="checkbox" class="mr-1 select-all-module" data-module="${grupo.module}">
                            ${i18n.selectAll}
                        </label>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            `;

            grupo.permissions.forEach(permission => {
                const isChecked = isEdit && (selectedPermissions.includes(permission.id) || selectedPermissions.includes(String(permission.id)));
                html += `
                    <label class="flex items-start p-2 rounded hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="${permission.id}"
                               class="mt-0.5 mr-2 permission-checkbox" data-module="${grupo.module}"
                               ${isChecked ? 'checked' : ''}>
                        <div>
                            <span class="text-sm text-slate-700">${permission.name}</span>
                            ${permission.description ? `<p class="text-xs text-slate-400">${permission.description}</p>` : ''}
                        </div>
                    </label>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        permissionsContainer.innerHTML = html;

        // Adicionar evento para "Selecionar todos" de cada módulo
        document.querySelectorAll('.select-all-module').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const module = this.dataset.module;
                const checked = this.checked;
                document.querySelectorAll(`.permission-checkbox[data-module="${module}"]`).forEach(cb => {
                    cb.checked = checked;
                });
            });
        });

        // Atualizar "Selecionar todos" quando checkboxes individuais mudam
        document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const module = this.dataset.module;
                updateSelectAllState(module);
            });
        });

        // Verificar estado inicial dos "Selecionar todos" (importante para modo edição)
        if (isEdit) {
            const modules = [...new Set(grupos.map(g => g.module))];
            modules.forEach(module => updateSelectAllState(module));
        }
    }

    // Função para atualizar estado do checkbox "Selecionar todos"
    function updateSelectAllState(module) {
        const allCheckboxes = document.querySelectorAll(`.permission-checkbox[data-module="${module}"]`);
        const checkedCheckboxes = document.querySelectorAll(`.permission-checkbox[data-module="${module}"]:checked`);
        const selectAllCheckbox = document.querySelector(`.select-all-module[data-module="${module}"]`);

        if (selectAllCheckbox) {
            selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
        }
    }

    // Função para formatar nome do módulo
    function formatarNomeModulo(module) {
        return i18n.moduleNames[module] || module.charAt(0).toUpperCase() + module.slice(1).replace(/_/g, ' ');
    }

    // Função para fechar o offcanvas
    function fecharOffcanvas() {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'closeOffcanvas' }, '*');
        }
    }
});
</script>
@endsection
