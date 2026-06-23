@extends('layouts.iframe')

@section('title', '<?= t("modules.roles.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Tela 1: Lista de Roles -->
    <div id="screenList">
        <!-- Header com botão adicionar -->
        <div class="mb-4">
            <button type="button" id="btnNovaRole" class="btn-blue py-2 px-4 rounded-md text-sm font-medium w-full">
                <i class="fas fa-plus mr-2"></i><?= t('modules.roles.new_title') ?>
            </button>
        </div>

        <!-- Lista de Roles -->
        <div id="rolesContainer">
            <div class="flex items-center justify-center py-8">
                <i class="fas fa-spinner fa-spin text-slate-400 mr-2"></i>
                <span class="text-slate-500"><?= t('modules.roles.messages.loading_roles') ?></span>
            </div>
        </div>
    </div>

    <!-- Tela 2: Adicionar Role -->
    <div id="screenAdd" class="hidden">
        <!-- Header com botão voltar -->
        <div class="flex items-center mb-4 pb-3 border-b border-slate-200">
            <button type="button" id="btnVoltarAdd" class="text-slate-500 hover:text-slate-700 mr-3">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h3 class="text-lg font-semibold text-slate-700"><?= t('modules.roles.new_title') ?></h3>
        </div>

        <!-- Formulário Adicionar -->
        <form id="formAddRole" method="POST" action="/roles/salvar">
            @csrf

            <!-- Campo: Nome -->
            <div class="form-input-group mb-4">
                <label for="addRoleName" class="form-label-group"><?= t('modules.roles.fields.name') ?> <span class="text-red-500">*</span></label>
                <input type="text" id="addRoleName" name="name" class="form-input-group-field" placeholder="<?= t('modules.roles.placeholders.name') ?>" required>
            </div>

            <!-- Campo: Descrição -->
            <div class="form-input-group mb-4">
                <label for="addRoleDescription" class="form-label-group"><?= t('modules.roles.fields.description') ?></label>
                <textarea id="addRoleDescription" name="description" class="form-input-group-field" rows="2" placeholder="<?= t('modules.roles.placeholders.description') ?>"></textarea>
            </div>

            <!-- Permissões -->
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-slate-700 mb-2"><?= t('modules.roles.sections.permissions') ?></h4>
                <div id="addPermissionsContainer">
                    <div class="flex items-center justify-center py-4">
                        <i class="fas fa-spinner fa-spin text-slate-400 mr-2"></i>
                        <span class="text-slate-500 text-sm"><?= t('modules.roles.messages.loading_permissions') ?></span>
                    </div>
                </div>
            </div>

            <!-- Botões -->
            <div class="flex justify-end space-x-3">
                <button type="button" id="btnCancelarAdd" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('common.buttons.cancel') ?>
                </button>
                <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('modules.roles.actions.save_role') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Tela 3: Editar Role -->
    <div id="screenEdit" class="hidden">
        <!-- Header com botão voltar -->
        <div class="flex items-center mb-4 pb-3 border-b border-slate-200">
            <button type="button" id="btnVoltarEdit" class="text-slate-500 hover:text-slate-700 mr-3">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h3 class="text-lg font-semibold text-slate-700"><?= t('modules.roles.edit_prefix') ?> <span id="editRoleTitle"></span></h3>
        </div>

        <!-- Formulário Editar -->
        <form id="formEditRole" method="POST">
            @csrf
            <input type="hidden" id="editRoleId" name="id">

            <!-- Campo: Nome -->
            <div class="form-input-group mb-4">
                <label for="editRoleName" class="form-label-group"><?= t('modules.roles.fields.name') ?> <span class="text-red-500">*</span></label>
                <input type="text" id="editRoleName" name="name" class="form-input-group-field" required>
            </div>

            <!-- Campo: Descrição -->
            <div class="form-input-group mb-4">
                <label for="editRoleDescription" class="form-label-group"><?= t('modules.roles.fields.description') ?></label>
                <textarea id="editRoleDescription" name="description" class="form-input-group-field" rows="2"></textarea>
            </div>

            <!-- Aviso para roles de sistema -->
            <div id="systemRoleWarning" class="hidden bg-amber-50 border border-amber-200 rounded-md p-3 mb-4">
                <p class="text-sm text-amber-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    <?= t('modules.roles.warnings.system_role_short') ?>
                </p>
            </div>

            <!-- Permissões -->
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-slate-700 mb-2"><?= t('modules.roles.sections.permissions') ?></h4>
                <div id="editPermissionsContainer">
                    <div class="flex items-center justify-center py-4">
                        <i class="fas fa-spinner fa-spin text-slate-400 mr-2"></i>
                        <span class="text-slate-500 text-sm"><?= t('modules.roles.messages.loading_permissions') ?></span>
                    </div>
                </div>
            </div>

            <!-- Botões -->
            <div class="flex justify-end space-x-3">
                <button type="button" id="btnCancelarEdit" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('common.buttons.cancel') ?>
                </button>
                <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('modules.roles.actions.save_changes') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div id="modalDelete" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-2"><?= t('modules.roles.actions.delete_role') ?></h3>
            <p class="text-slate-600 text-sm mb-4" id="deleteRoleMessage"></p>
            <p class="text-red-600 text-xs mb-4"><?= t('modules.roles.warnings.irreversible') ?></p>
            <div class="flex justify-end space-x-3">
                <button type="button" id="btnCancelarDelete" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('common.buttons.cancel') ?>
                </button>
                <button type="button" id="btnConfirmarDelete" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('common.buttons.delete') ?>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const i18n = {
        loadingRoles: '<?= addslashes(t("modules.roles.messages.loading_roles")) ?>',
        loadingPermissions: '<?= addslashes(t("modules.roles.messages.loading_permissions")) ?>',
        loadError: '<?= addslashes(t("modules.roles.messages.load_error")) ?>',
        loadRoleError: '<?= addslashes(t("modules.roles.messages.load_role_error")) ?>',
        loadPermissionsError: '<?= addslashes(t("modules.roles.messages.load_permissions_error")) ?>',
        noRecords: '<?= addslashes(t("modules.roles.messages.no_records")) ?>',
        noPermissions: '<?= addslashes(t("modules.roles.messages.no_permissions")) ?>',
        notFound: '<?= addslashes(t("modules.roles.messages.not_found")) ?>',
        saveError: '<?= addslashes(t("modules.roles.messages.save_error")) ?>',
        deleteError: <?= js_t("modules.roles.messages.delete_error") ?>,
        processError: <?= js_t("modules.roles.messages.process_error") ?>,
        deleting: '<?= addslashes(t("modules.roles.messages.deleting")) ?>',
        deleteConfirm: <?= js_t("modules.roles.messages.delete_confirm") ?>,
        saving: '<?= addslashes(t("common.labels.saving")) ?>',
        badgeSystem: '<?= addslashes(t("modules.roles.badges.system")) ?>',
        badgeCustom: '<?= addslashes(t("modules.roles.badges.custom")) ?>',
        actionEdit: '<?= addslashes(t("common.buttons.edit")) ?>',
        actionDelete: '<?= addslashes(t("common.buttons.delete")) ?>',
        selectAll: '<?= addslashes(t("modules.roles.actions.select_all_short")) ?>',
        moduleNames: <?= json_encode(t("modules.roles.module_names"), JSON_UNESCAPED_UNICODE) ?>,
    };

    // Elementos das telas
    const screenList = document.getElementById('screenList');
    const screenAdd = document.getElementById('screenAdd');
    const screenEdit = document.getElementById('screenEdit');
    const modalDelete = document.getElementById('modalDelete');

    // Containers
    const rolesContainer = document.getElementById('rolesContainer');
    const addPermissionsContainer = document.getElementById('addPermissionsContainer');
    const editPermissionsContainer = document.getElementById('editPermissionsContainer');

    // Formulários
    const formAdd = document.getElementById('formAddRole');
    const formEdit = document.getElementById('formEditRole');

    // Estado
    let allPermissions = [];
    let roleToDelete = null;
    let currentEditRole = null;

    // Inicialização
    carregarRoles();
    carregarPermissoes();

    // ===== NAVEGAÇÃO =====

    // Botão Nova Função
    document.getElementById('btnNovaRole').addEventListener('click', function() {
        mostrarTela('add');
        formAdd.reset();
        // Desmarcar todas as permissões
        addPermissionsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    });

    // Botões Voltar
    document.getElementById('btnVoltarAdd').addEventListener('click', () => mostrarTela('list'));
    document.getElementById('btnVoltarEdit').addEventListener('click', () => mostrarTela('list'));
    document.getElementById('btnCancelarAdd').addEventListener('click', () => mostrarTela('list'));
    document.getElementById('btnCancelarEdit').addEventListener('click', () => mostrarTela('list'));

    // Modal Delete
    document.getElementById('btnCancelarDelete').addEventListener('click', fecharModalDelete);
    document.getElementById('btnConfirmarDelete').addEventListener('click', confirmarExclusao);

    // ===== FORMULÁRIOS =====

    // Submit Adicionar
    formAdd.addEventListener('submit', async function(e) {
        e.preventDefault();
        await salvarRole(this, '/roles/salvar', 'Função criada com sucesso!');
    });

    // Submit Editar
    formEdit.addEventListener('submit', async function(e) {
        e.preventDefault();
        const id = document.getElementById('editRoleId').value;
        await salvarRole(this, `/roles/${id}/atualizar`, 'Função atualizada com sucesso!');
    });

    // ===== FUNÇÕES =====

    function mostrarTela(tela) {
        screenList.classList.add('hidden');
        screenAdd.classList.add('hidden');
        screenEdit.classList.add('hidden');

        if (tela === 'list') {
            screenList.classList.remove('hidden');
            carregarRoles(); // Recarregar lista
        } else if (tela === 'add') {
            screenAdd.classList.remove('hidden');
        } else if (tela === 'edit') {
            screenEdit.classList.remove('hidden');
        }
    }

    async function carregarRoles() {
        try {
            rolesContainer.innerHTML = `
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-slate-400 mr-2"></i>
                    <span class="text-slate-500">${i18n.loadingRoles}</span>
                </div>
            `;

            const result = await API.get('/api/roles');

            if (result.success && result.data) {
                renderizarRoles(result.data);
            } else {
                rolesContainer.innerHTML = `<p class="text-red-500 text-sm py-4">${i18n.loadError}</p>`;
            }
        } catch (error) {
            console.error('Erro ao carregar roles:', error);
            rolesContainer.innerHTML = '<p class="text-red-500 text-sm py-4">Erro ao carregar funções.</p>';
        }
    }

    function renderizarRoles(roles) {
        if (!roles || roles.length === 0) {
            rolesContainer.innerHTML = `<p class="text-slate-500 text-sm py-4 text-center">${i18n.noRecords}</p>`;
            return;
        }

        let html = '<div class="space-y-2">';

        roles.forEach(role => {
            const isSystem = role.is_system == 1 || role.chave === '0';
            const isCustomization = role.is_customization == 1;

            let badge = '';
            if (isSystem) {
                badge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">${i18n.badgeSystem}</span>`;
            } else if (isCustomization) {
                badge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${i18n.badgeCustom}</span>`;
            }

            html += `
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-700 truncate">${escapeHtml(role.name)}</span>
                            ${badge}
                        </div>
                        ${role.description ? `<p class="text-xs text-slate-500 truncate mt-0.5">${escapeHtml(role.description)}</p>` : ''}
                    </div>
                    <div class="flex items-center space-x-2 ml-2">
                        <button type="button" onclick="editarRole(${role.id})" class="text-slate-400 hover:text-blue-500 p-1" title="${i18n.actionEdit}">
                            <i class="fas fa-pen text-sm"></i>
                        </button>
                        ${!isSystem ? `
                            <button type="button" onclick="excluirRole(${role.id}, '${escapeHtml(role.name)}')" class="text-slate-400 hover:text-red-500 p-1" title="${i18n.actionDelete}">
                                <i class="fas fa-trash text-sm"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        rolesContainer.innerHTML = html;
    }

    async function carregarPermissoes() {
        try {
            const result = await API.get('/api/permissions');

            if (result.success && result.data) {
                allPermissions = result.data;
                renderizarPermissoes(addPermissionsContainer, result.data, []);
                renderizarPermissoes(editPermissionsContainer, result.data, []);
            }
        } catch (error) {
            console.error('Erro ao carregar permissões:', error);
        }
    }

    function renderizarPermissoes(container, grupos, selectedIds) {
        if (!grupos || grupos.length === 0) {
            container.innerHTML = `<p class="text-slate-500 text-sm">${i18n.noPermissions}</p>`;
            return;
        }

        let html = '<div class="max-h-64 overflow-y-auto border border-slate-200 rounded-md">';

        grupos.forEach(grupo => {
            const moduleName = formatarNomeModulo(grupo.module);
            const containerId = `module_${grupo.module}_${container.id}`;

            // Verificar se todas as permissões do módulo estão selecionadas
            const allSelected = grupo.permissions.every(p => selectedIds.includes(parseInt(p.id)));

            html += `
                <div class="border-b border-slate-200 last:border-b-0">
                    <div class="flex items-center justify-between p-2 bg-slate-50 hover:bg-slate-100">
                        <button type="button" class="flex-1 flex items-center text-left" onclick="toggleModule('${containerId}')">
                            <span class="text-sm font-medium text-slate-700">${moduleName}</span>
                        </button>
                        <label class="flex items-center text-xs text-slate-500 cursor-pointer hover:text-slate-700 mr-2" onclick="event.stopPropagation()">
                            <input type="checkbox" class="mr-1 select-all-module" data-container="${containerId}" ${allSelected ? 'checked' : ''}>
                            ${i18n.selectAll}
                        </label>
                        <button type="button" onclick="toggleModule('${containerId}')" class="p-1">
                            <i class="fas fa-chevron-down text-slate-400 text-xs" id="icon_${containerId}"></i>
                        </button>
                    </div>
                    <div id="${containerId}" class="hidden p-2 space-y-1">
            `;

            grupo.permissions.forEach(permission => {
                const checked = selectedIds.includes(parseInt(permission.id)) ? 'checked' : '';
                html += `
                    <label class="flex items-center p-1 rounded hover:bg-slate-50 cursor-pointer text-sm">
                        <input type="checkbox" name="permissions[]" value="${permission.id}" class="mr-2 permission-checkbox" data-container="${containerId}" ${checked}>
                        <span class="text-slate-600">${permission.name}</span>
                    </label>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

        // Adicionar eventos para "Selecionar todos"
        container.querySelectorAll('.select-all-module').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const containerId = this.dataset.container;
                const moduleContainer = document.getElementById(containerId);
                if (moduleContainer) {
                    moduleContainer.querySelectorAll('.permission-checkbox').forEach(cb => {
                        cb.checked = this.checked;
                    });
                }
            });
        });

        // Atualizar "Selecionar todos" quando checkboxes individuais mudam
        container.querySelectorAll('.permission-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const containerId = this.dataset.container;
                const moduleContainer = document.getElementById(containerId);
                const selectAllCheckbox = container.querySelector(`.select-all-module[data-container="${containerId}"]`);

                if (moduleContainer && selectAllCheckbox) {
                    const allCheckboxes = moduleContainer.querySelectorAll('.permission-checkbox');
                    const checkedCheckboxes = moduleContainer.querySelectorAll('.permission-checkbox:checked');
                    selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
                }
            });
        });
    }

    // Função global para toggle de módulo
    window.toggleModule = function(containerId) {
        const container = document.getElementById(containerId);
        const icon = document.getElementById('icon_' + containerId);
        if (container) {
            container.classList.toggle('hidden');
            if (icon) {
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-up');
            }
        }
    };

    // Função global para editar role
    window.editarRole = async function(id) {
        try {
            // Buscar dados da role via API
            const rolesResult = await API.get('/api/roles');
            if (!rolesResult.success) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.loadRoleError }, '*');
                return;
            }

            const role = rolesResult.data.find(r => r.id == id);
            if (!role) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.notFound }, '*');
                return;
            }

            currentEditRole = role;

            // Preencher formulário
            document.getElementById('editRoleId').value = role.id;
            document.getElementById('editRoleName').value = role.name;
            document.getElementById('editRoleDescription').value = role.description || '';
            document.getElementById('editRoleTitle').textContent = role.name;

            // Mostrar aviso se for role de sistema
            const isSystem = role.is_system == 1 || role.chave === '0';
            document.getElementById('systemRoleWarning').classList.toggle('hidden', !isSystem);

            // Definir action do form
            formEdit.action = `/roles/${role.id}/atualizar`;

            // Carregar permissões da role
            await carregarPermissoesRole(role.id);

            mostrarTela('edit');
        } catch (error) {
            console.error('Erro ao editar role:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.loadRoleError }, '*');
        }
    };

    async function carregarPermissoesRole(roleId) {
        try {
            // Buscar permissões da role específica
            const result = await API.get(`/api/roles/${roleId}/permissions`);
            const selectedIds = (result.success && result.data) ? result.data.map(p => parseInt(p.id)) : [];

            renderizarPermissoes(editPermissionsContainer, allPermissions, selectedIds);
        } catch (error) {
            console.error('Erro ao carregar permissões da role:', error);
            renderizarPermissoes(editPermissionsContainer, allPermissions, []);
        }
    }

    // Função global para excluir role
    window.excluirRole = function(id, name) {
        roleToDelete = id;
        document.getElementById('deleteRoleMessage').textContent = i18n.deleteConfirm.replace(':name', name);
        modalDelete.classList.remove('hidden');
    };

    function fecharModalDelete() {
        modalDelete.classList.add('hidden');
        roleToDelete = null;
    }

    async function confirmarExclusao() {
        if (!roleToDelete) return;

        const btn = document.getElementById('btnConfirmarDelete');
        const originalText = btn.innerHTML;

        try {
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.deleting}`;

            const result = await API.post(`/roles/${roleToDelete}/excluir`);

            if (result.success) {
                fecharModalDelete();
                carregarRoles();
                notificarParent();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.deleteError }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.processError }, '*');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    async function salvarRole(form, url, successMessage) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                mostrarTela('list');
                notificarParent();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.saveError }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.processError }, '*');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    function notificarParent() {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'refreshRoles' }, '*');
        }
    }

    function formatarNomeModulo(module) {
        return i18n.moduleNames[module] || module.charAt(0).toUpperCase() + module.slice(1).replace(/_/g, ' ');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endsection
