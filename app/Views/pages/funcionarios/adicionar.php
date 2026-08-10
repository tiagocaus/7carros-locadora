@extends('layouts.iframe')

@section('title', t('modules.funcionarios.title_singular'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabeçalho com título e botão voltar -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.funcionarios.new_title') ?></h2>
        <button id="btnVoltarListaFuncionarios" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulário -->
    <form id="formFuncionario" method="POST">
        @csrf
        <input type="hidden" id="funcionarioId" name="id" value="">

        <div>
            <!-- Seção: Dados do Funcionário -->
            <div class="form-section mb-6 relative">
                <h3 class="form-section-title"><?= t('modules.funcionarios.sections.employee_data') ?></h3>

                <!-- Container da foto posicionado no canto superior direito -->
                <div class="fotoClientePreviewContainer absolute top-0 right-0 w-40 h-50 border-2 border-slate-300 rounded-md overflow-hidden bg-slate-100 cursor-pointer group z-10" id="fotoFuncionarioContainer" style="margin-top: 0.8rem; margin-right: 1.5rem;">
                    <img id="fotoFuncionarioPreview"
                        src="<?= image('assets/img/foto_padrao.png') ?>"
                        alt="<?= t('modules.funcionarios.photo.alt') ?>"
                        class="w-full h-full object-cover" style="width: 156px; height: 177px;">
                    <input type="file" id="fotoFuncionarioInput" name="foto" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                    <input type="hidden" id="fotoFuncionarioBase64" name="foto_base64">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex flex-col justify-end">
                        <div class="bg-black bg-opacity-40 text-white text-center py-2 text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                            <?= t('modules.funcionarios.photo.take_photo') ?>
                        </div>
                    </div>
                </div>

                <!-- Grid: Matriz/Filial + Status -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-6 form-input-group">
                        <label class="form-label-group"><?= t('modules.funcionarios.fields.branch') ?> <span class="text-red-500">*</span></label>
                        <div id="filiaisDropdown" class="filiais-dropdown">
                            <div class="filiais-dropdown-trigger" id="filiaisDropdownTrigger">
                                <span class="filiais-dropdown-text" id="filiaisDropdownText"><?= t('modules.funcionarios.branch_dropdown.loading') ?></span>
                                <i class="fas fa-chevron-down filiais-dropdown-icon"></i>
                            </div>
                            <div class="filiais-dropdown-menu" id="filiaisDropdownMenu">
                                <div class="filiais-loading">
                                    <i class="fas fa-spinner fa-spin"></i> <?= t('modules.funcionarios.branch_dropdown.loading_branches') ?>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="filiaisPermitidasJson" name="filiais_permitidas">
                        <input type="hidden" id="funcionarioMatrizFilial" name="matriz_filial">
                    </div>

                    <div class="md:col-span-4 form-input-group">
                        <label for="funcionarioStatus" class="form-label-group"><?= t('common.labels.status') ?></label>
                        <select id="funcionarioStatus" name="status" class="form-input-group-field">
                            <option value="A"><?= t('modules.funcionarios.status_options.active') ?></option>
                            <option value="S"><?= t('modules.funcionarios.status_options.inactive') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Grid: Nome | Email -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <div class="md:col-span-6 form-input-group">
                        <label for="funcionarioNome" class="form-label-group"><?= t('modules.funcionarios.fields.full_name') ?> <span class="text-red-500">*</span></label>
                        <input type="text" id="funcionarioNome" name="nome" class="form-input-group-field" required>
                        <span id="nomeErro" class="text-red-500 text-xs mt-1 hidden"><?= t('modules.funcionarios.messages.name_support_error') ?></span>
                    </div>

                    <div class="md:col-span-4 form-input-group">
                        <label for="funcionarioEmail" class="form-label-group"><?= t('modules.funcionarios.fields.email') ?> <span class="text-red-500">*</span></label>
                        <input type="email" id="funcionarioEmail" name="email" class="form-input-group-field" required>
                    </div>
                </div>

                <!-- Grid: Usuário | Senha | Confirmar Senha -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                    <div class="form-input-group">
                        <label for="funcionarioUsuario" class="form-label-group"><?= t('modules.funcionarios.fields.username') ?> <span class="text-red-500">*</span></label>
                        <input type="text" id="funcionarioUsuario" name="usuario" class="form-input-group-field" required>
                        <span id="usuarioErro" class="text-red-500 text-xs mt-1 hidden"><?= t('modules.funcionarios.messages.username_in_use') ?></span>
                    </div>

                    <div class="form-input-group">
                        <label for="funcionarioSenha" class="form-label-group">
                            <span id="senhaLabelTexto"><?= t('modules.funcionarios.fields.password') ?></span>
                            <span id="senhaRequired" class="text-red-500">*</span>
                            <span id="senhaHint" class="text-slate-400 text-xs hidden"><?= t('modules.funcionarios.fields.password_hint') ?></span>
                        </label>
                        <input type="password" id="funcionarioSenha" name="senha" class="form-input-group-field" required>
                    </div>

                    <div class="form-input-group">
                        <label for="funcionarioConfirmarSenha" class="form-label-group">
                            <span id="confirmarSenhaLabelTexto"><?= t('modules.funcionarios.fields.confirm_password') ?></span>
                            <span id="confirmarSenhaRequired" class="text-red-500">*</span>
                        </label>
                        <input type="password" id="funcionarioConfirmarSenha" name="confirmar_senha" class="form-input-group-field" required>
                        <span id="senhaErro" class="text-red-500 text-xs mt-1 hidden"><?= t('modules.funcionarios.messages.passwords_dont_match') ?></span>
                    </div>
                </div>

                <!-- Grid: Role (Função) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                    <div class="form-input-group">
                        <label for="funcionarioRole" class="form-label-group"><?= t('modules.funcionarios.fields.role') ?></label>
                        <div class="flex">
                            <select id="funcionarioRole" name="id_role" class="form-input-group-field rounded-r-none border-r-0">
                                <option value=""><?= t('modules.funcionarios.placeholders.select_role') ?></option>
                                <!-- Opções serão carregadas via JavaScript -->
                            </select>
                            <button type="button"
                                    id="btnAdicionarRole"
                                    class="flex items-center justify-center w-[31px] p-0 bg-[#87909d] hover:!bg-[#6b7480] active:!bg-[#5a626d] text-white border-0 rounded-none cursor-pointer transition-colors duration-200"
                                    title="<?= t('modules.funcionarios.actions.manage_roles') ?>">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção: Dados Pessoais -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.funcionarios.sections.personal_data') ?></h3>

                <!-- Grid: CPF | Nacionalidade | Sexo | Estado Civil -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <div class="form-input-group">
                        <label for="funcionarioCPF" class="form-label-group"><?= t('modules.funcionarios.fields.cpf') ?> <span class="text-red-500">*</span></label>
                        <input type="text" id="funcionarioCPF" name="cpf" class="form-input-group-field cpf" placeholder="000.000.000-00" required>
                    </div>

                    <div class="form-input-group">
                        <label for="funcionarioNacionalidade" class="form-label-group"><?= t('modules.funcionarios.fields.nationality') ?></label>
                        <input type="text" id="funcionarioNacionalidade" name="nascionalidade" class="form-input-group-field" placeholder="<?= t('modules.funcionarios.placeholders.nationality') ?>">
                    </div>

                    <div class="form-input-group">
                        <label for="funcionarioSexo" class="form-label-group"><?= t('modules.funcionarios.fields.gender') ?></label>
                        <select id="funcionarioSexo" name="sexo" class="form-input-group-field">
                            <option value=""><?= t('modules.funcionarios.placeholders.select_option') ?></option>
                            <option value="masculino"><?= t('modules.funcionarios.gender_options.male') ?></option>
                            <option value="feminino"><?= t('modules.funcionarios.gender_options.female') ?></option>
                        </select>
                    </div>

                    <div class="form-input-group">
                        <label for="funcionarioEstadoCivil" class="form-label-group"><?= t('modules.funcionarios.fields.marital_status') ?></label>
                        <select id="funcionarioEstadoCivil" name="e_civil" class="form-input-group-field">
                            <option value=""><?= t('modules.funcionarios.placeholders.select_option') ?></option>
                            <option value="solteiro"><?= t('modules.funcionarios.marital_options.single') ?></option>
                            <option value="casado"><?= t('modules.funcionarios.marital_options.married') ?></option>
                            <option value="divorciado"><?= t('modules.funcionarios.marital_options.divorced') ?></option>
                            <option value="viuvo"><?= t('modules.funcionarios.marital_options.widowed') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Seção: Carteira de Motorista -->
                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3"><?= t('modules.funcionarios.sections.drivers_license') ?></h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="form-input-group">
                            <label for="funcionarioCNH" class="form-label-group"><?= t('modules.funcionarios.fields.cnh_number') ?></label>
                            <input type="text" id="funcionarioCNH" name="cnh" class="form-input-group-field">
                        </div>

                        <div class="form-input-group">
                            <label for="funcionarioRegistroCNH" class="form-label-group"><?= t('modules.funcionarios.fields.cnh_registry') ?></label>
                            <input type="text" id="funcionarioRegistroCNH" name="registro_cnh" class="form-input-group-field">
                        </div>

                        <div class="form-input-group">
                            <label for="funcionarioValidadeCNH" class="form-label-group"><?= t('modules.funcionarios.fields.cnh_expiry') ?></label>
                            <input type="date" id="funcionarioValidadeCNH" name="validade_cnh" class="form-input-group-field">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção: Dados Trabalhistas -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.funcionarios.sections.employment_data') ?></h3>

                <!-- Grid: Carteira de Trabalho | PIS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div class="form-input-group">
                        <label for="funcionarioCTrabalho" class="form-label-group"><?= t('modules.funcionarios.fields.work_card') ?></label>
                        <input type="text" id="funcionarioCTrabalho" name="c_trabalho" class="form-input-group-field">
                    </div>

                    <div class="form-input-group">
                        <label for="funcionarioPIS" class="form-label-group"><?= t('modules.funcionarios.fields.pis') ?></label>
                        <input type="text" id="funcionarioPIS" name="pis" class="form-input-group-field">
                    </div>
                </div>

                <!-- Seção: Remuneração -->
                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3"><?= t('modules.funcionarios.sections.compensation') ?></h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="form-input-group">
                            <label for="funcionarioSalario" class="form-label-group"><?= t('modules.funcionarios.fields.salary') ?></label>
                            <input type="text" id="funcionarioSalario" name="salario" class="form-input-group-field input-moeda" placeholder="0,00">
                        </div>

                        <div class="form-input-group">
                            <label for="funcionarioTipoSalario" class="form-label-group"><?= t('modules.funcionarios.fields.salary_type') ?></label>
                            <select id="funcionarioTipoSalario" name="tipo_salario" class="form-input-group-field">
                                <option value=""><?= t('modules.funcionarios.placeholders.select_option') ?></option>
                                <option value="mensal"><?= t('modules.funcionarios.salary_type_options.monthly') ?></option>
                                <option value="quinzenal"><?= t('modules.funcionarios.salary_type_options.biweekly') ?></option>
                                <option value="semanal"><?= t('modules.funcionarios.salary_type_options.weekly') ?></option>
                                <option value="diario"><?= t('modules.funcionarios.salary_type_options.daily') ?></option>
                            </select>
                        </div>

                        <div class="form-input-group">
                            <label for="funcionarioDiaPagamento" class="form-label-group"><?= t('modules.funcionarios.fields.payment_day') ?></label>
                            <input type="number" id="funcionarioDiaPagamento" name="dia_pagamento" class="form-input-group-field" min="1" max="31" placeholder="<?= t('modules.funcionarios.placeholders.payment_day') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção: Endereço -->
            <div class="form-section mb-6">
                <h3 class="form-section-title"><?= t('modules.funcionarios.sections.address') ?></h3>

                <!-- Linha 1: CEP, Rua, Número -->
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 sm:col-span-3 lg:col-span-2 form-input-group">
                        <label for="cep" class="form-label-group"><?= t('modules.funcionarios.fields.zip_code') ?></label>
                        <input type="text" id="cep" name="cep" class="form-input-group-field cep" placeholder="00000-000">
                    </div>
                    <div class="col-span-12 sm:col-span-6 lg:col-span-8 form-input-group">
                        <label for="rua" class="form-label-group"><?= t('modules.funcionarios.fields.street') ?></label>
                        <input type="text" id="rua" name="rua" class="form-input-group-field">
                    </div>
                    <div class="col-span-12 sm:col-span-3 lg:col-span-2 form-input-group">
                        <label for="numero" class="form-label-group"><?= t('modules.funcionarios.fields.number') ?></label>
                        <input type="text" id="numero" name="num" class="form-input-group-field">
                    </div>
                </div>

                <!-- Linha 2: Complemento, Bairro, Cidade -->
                <div class="grid grid-cols-12 gap-4 mt-4">
                    <div class="col-span-12 sm:col-span-4 form-input-group">
                        <label for="complemento" class="form-label-group"><?= t('modules.funcionarios.fields.complement') ?></label>
                        <input type="text" id="complemento" name="comple" class="form-input-group-field">
                    </div>
                    <div class="col-span-12 sm:col-span-4 form-input-group">
                        <label for="bairro" class="form-label-group"><?= t('modules.funcionarios.fields.neighborhood') ?></label>
                        <input type="text" id="bairro" name="bairro" class="form-input-group-field">
                    </div>
                    <div class="col-span-12 sm:col-span-4 form-input-group">
                        <label for="cidade" class="form-label-group"><?= t('modules.funcionarios.fields.city') ?></label>
                        <input type="text" id="cidade" name="cidade" class="form-input-group-field">
                    </div>
                </div>

                <!-- Linha 3: Estado, País -->
                <div class="grid grid-cols-12 gap-4 mt-4">
                    <div class="col-span-12 sm:col-span-6 form-input-group">
                        <label for="uf" class="form-label-group"><?= t('modules.funcionarios.fields.state') ?></label>
                        <input type="text" id="uf" name="uf" class="form-input-group-field" maxlength="2" placeholder="SP">
                    </div>
                    <div class="col-span-12 sm:col-span-6 form-input-group">
                        <label for="pais" class="form-label-group"><?= t('modules.funcionarios.fields.country') ?></label>
                        <select id="pais" name="pais" class="form-input-group-field chosen-select"
                                data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                            <option value=""><?= t('common.labels.select') ?>...</option>
                            <?php foreach ($paises ?? [] as $p): ?>
                                <option value="<?= $p['codigo'] ?>" <?= ($p['codigo'] === 'BR') ? 'selected' : '' ?>>
                                    <?= \App\Models\Pais::getNome($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Seção: Contato -->
                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3"><?= t('modules.funcionarios.sections.contact') ?></h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="form-input-group">
                            <label for="funcionarioTelFixo" class="form-label-group"><?= t('modules.funcionarios.fields.landline') ?></label>
                            <input type="tel" id="funcionarioTelFixo" name="tel_fixo" class="form-input-group-field intltel">
                        </div>

                        <div class="form-input-group">
                            <label for="funcionarioTelCel" class="form-label-group"><?= t('modules.funcionarios.fields.mobile') ?></label>
                            <input type="tel" id="funcionarioTelCel" name="tel_cel" class="form-input-group-field intltel">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões de ação no final -->
        <div class="mt-6 flex justify-end space-x-3" id="actionButtons">
            <button type="button" id="btnCancelarFormFuncionario"
                class="btn-secondary hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" id="btnSalvar"
                class="btn-blue py-2 px-4 rounded-md text-sm font-medium shadow hover:shadow-md transition-shadow">
                <?= t('modules.funcionarios.buttons.save') ?>
            </button>
        </div>
    </form>

    <!-- Modal de escolha de foto -->
    <div id="modalEscolhaFoto" class="modal-overlay">
        <div class="modal-box" style="max-width: 400px;">
            <h3 class="modal-title"><?= t('modules.funcionarios.photo.choose_title') ?></h3>
            <p class="modal-message"><?= t('modules.funcionarios.photo.choose_method') ?></p>
            <div class="modal-actions" style="justify-content: center; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" id="btnEnviarArquivo" class="btn-blue py-2 px-6 rounded-md text-sm font-medium">
                    <i class="fas fa-upload mr-2"></i><?= t('modules.funcionarios.photo.upload_file') ?>
                </button>
                <button type="button" id="btnUsarCamera" class="btn-green py-2 px-6 rounded-md text-sm font-medium">
                    <i class="fas fa-camera mr-2"></i><?= t('modules.funcionarios.photo.use_camera') ?>
                </button>
            </div>
            <div class="modal-actions" style="margin-top: 1rem;">
                <button type="button" id="btnCancelarEscolhaFoto" class="btn-secondary">
                    <?= t('common.buttons.cancel') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de preview da câmera -->
    <div id="modalCameraPreview" class="modal-overlay">
        <div class="modal-box" style="max-width: 500px;">
            <h3 class="modal-title"><?= t('modules.funcionarios.photo.camera_title') ?></h3>
            <div style="margin-bottom: 1rem; display: flex; justify-content: center; align-items: center; background: #000; border-radius: 0.5rem; overflow: hidden;">
                <video id="videoCamera" autoplay playsinline style="width: 100%; max-height: 400px; display: block;"></video>
                <canvas id="canvasCamera" style="display: none;"></canvas>
            </div>
            <div class="modal-actions">
                <button type="button" id="btnCapturarFoto" class="btn-blue">
                    <i class="fas fa-camera mr-2"></i><?= t('modules.funcionarios.photo.capture') ?>
                </button>
                <button type="button" id="btnCancelarCamera" class="btn-secondary">
                    <?= t('common.buttons.cancel') ?>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="<?= asset('js/cep.min.js') ?>"></script>
<script>
    (function() {
        const i18n = {
            newTitle: '<?= addslashes(t('modules.funcionarios.new_title')) ?>',
            editTitle: '<?= addslashes(t('modules.funcionarios.edit_title')) ?>',
            viewTitle: '<?= addslashes(t('modules.funcionarios.view_title')) ?>',
            newPassword: '<?= addslashes(t('modules.funcionarios.fields.new_password')) ?>',
            confirmNewPassword: '<?= addslashes(t('modules.funcionarios.fields.confirm_new_password')) ?>',
            saveChanges: '<?= addslashes(t('modules.funcionarios.buttons.save_changes')) ?>',
            changePhoto: '<?= addslashes(t('modules.funcionarios.photo.change_photo')) ?>',
            close: '<?= addslashes(t('common.buttons.close')) ?>',
            notFound: '<?= addslashes(t('modules.funcionarios.messages.not_found')) ?>',
            serverError: '<?= addslashes(t('modules.funcionarios.messages.server_error')) ?>',
            formatNotSupported: '<?= addslashes(t('modules.funcionarios.messages.format_not_supported')) ?>',
            imageTooLarge: <?= js_t('modules.funcionarios.messages.image_too_large') ?>,
            cameraNotSupported: <?= js_t('modules.funcionarios.messages.camera_not_supported') ?>,
            cameraAccessDenied: <?= js_t('modules.funcionarios.messages.camera_access_denied') ?>,
            cameraNotFound: <?= js_t('modules.funcionarios.messages.camera_not_found') ?>,
            cameraError: '<?= addslashes(t('modules.funcionarios.messages.camera_error')) ?>',
            cameraInitializing: '<?= addslashes(t('modules.funcionarios.messages.camera_initializing')) ?>',
            passwordsDontMatch: '<?= addslashes(t('modules.funcionarios.messages.passwords_dont_match')) ?>',
            nameSupportError: <?= js_t('modules.funcionarios.messages.name_support_error') ?>,
            usernameSupportError: <?= js_t('modules.funcionarios.messages.username_support_error') ?>,
            passwordRequired: '<?= addslashes(t('modules.funcionarios.messages.password_required')) ?>',
            passwordMismatch: '<?= addslashes(t('modules.funcionarios.messages.password_mismatch')) ?>',
            saveError: '<?= addslashes(t('modules.funcionarios.messages.save_error')) ?>',
            updateError: <?= js_t('modules.funcionarios.messages.update_error') ?>,
            selectRole: '<?= addslashes(t('modules.funcionarios.placeholders.select_role')) ?>',
            manageRoles: '<?= addslashes(t('modules.funcionarios.actions.manage_roles')) ?>',
            branchLoadError: '<?= addslashes(t('modules.funcionarios.branch_dropdown.load_error')) ?>',
            branchLoadErrorDetail: '<?= addslashes(t('modules.funcionarios.branch_dropdown.load_error_detail')) ?>',
            branchNoBranches: '<?= addslashes(t('modules.funcionarios.branch_dropdown.no_branches')) ?>',
            branchNoBranchesShort: '<?= addslashes(t('modules.funcionarios.branch_dropdown.no_branches_short')) ?>',
            branchSetAsMain: '<?= addslashes(t('modules.funcionarios.actions.set_as_main')) ?>',
            branchSelect: <?= js_t('modules.funcionarios.placeholders.select_option') ?>,
        };

        // Detectar modo de edição via parâmetro ?id=
        const urlParams = new URLSearchParams(window.location.search);
        const funcionarioId = urlParams.get('id');
        const isViewMode = urlParams.get('mode') === 'view';
        const editando = funcionarioId !== null && funcionarioId !== '';

        // Variáveis para gerenciamento de foto
        const fotoContainer = document.getElementById('fotoFuncionarioContainer');
        const fotoPreview = document.getElementById('fotoFuncionarioPreview');
        const fotoInput = document.getElementById('fotoFuncionarioInput');
        const fotoBase64Input = document.getElementById('fotoFuncionarioBase64');
        const modalEscolhaFoto = document.getElementById('modalEscolhaFoto');
        const modalCameraPreview = document.getElementById('modalCameraPreview');
        const videoCamera = document.getElementById('videoCamera');
        const canvasCamera = document.getElementById('canvasCamera');
        let streamCamera = null;
        // Verificar se está dentro de um iframe
        const isInIframe = window.parent !== window;

        // Configurar modo de edição
        function configurarModoEdicao() {
            // Atualizar título
            document.getElementById('pageTitle').textContent = isViewMode ? i18n.viewTitle : i18n.editTitle;
            document.title = isViewMode ? i18n.viewTitle : i18n.editTitle;

            // Atualizar ID hidden
            document.getElementById('funcionarioId').value = funcionarioId;

            // Atualizar labels de senha
            document.getElementById('senhaLabelTexto').textContent = i18n.newPassword;
            document.getElementById('senhaRequired').classList.add('hidden');
            document.getElementById('senhaHint').classList.remove('hidden');
            document.getElementById('confirmarSenhaLabelTexto').textContent = i18n.confirmNewPassword;
            document.getElementById('confirmarSenhaRequired').classList.add('hidden');

            // Remover required dos campos de senha
            document.getElementById('funcionarioSenha').removeAttribute('required');
            document.getElementById('funcionarioConfirmarSenha').removeAttribute('required');

            // O nome de usuário é definido apenas no cadastro e é informativo na edição.
            const usuarioInput = document.getElementById('funcionarioUsuario');
            usuarioInput.disabled = true;
            usuarioInput.classList.add('bg-slate-100', 'cursor-not-allowed');

            // Campo imutável não deve aparecer entre as alterações auditadas.
            if (window.FormAudit && !FormAudit.CONFIG.ignoredFields.includes('usuario')) {
                FormAudit.CONFIG.ignoredFields.push('usuario');
            }

            // Atualizar botão de salvar
            document.getElementById('btnSalvar').textContent = i18n.saveChanges;

            // Hover da foto
            const fotoHoverText = fotoContainer.querySelector('.bg-black.bg-opacity-40');
            if (fotoHoverText) {
                fotoHoverText.textContent = i18n.changePhoto;
            }

            // Aplicar modo de visualização se necessário
            if (isViewMode) {
                aplicarModoVisualizacao();
            }
        }

        // Aplicar modo de visualização (campos desabilitados)
        function aplicarModoVisualizacao() {
            // Desabilitar todos os campos do formulário
            const form = document.getElementById('formFuncionario');
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.disabled = true;
                input.classList.add('bg-slate-100', 'cursor-not-allowed');
            });

            // Ocultar botão de salvar
            document.getElementById('btnSalvar').classList.add('hidden');

            // Alterar texto do botão cancelar para "Fechar"
            document.getElementById('btnCancelarFormFuncionario').textContent = i18n.close;

            // Desabilitar container de foto
            fotoContainer.classList.remove('cursor-pointer');
            fotoContainer.classList.add('cursor-default');
            const fotoHoverOverlay = fotoContainer.querySelector('.group-hover\\:bg-opacity-30');
            if (fotoHoverOverlay) fotoHoverOverlay.classList.add('hidden');

            // Ocultar botão de adicionar role
            document.getElementById('btnAdicionarRole')?.classList.add('hidden');
        }

        // Carregar dados do funcionário para edição
        async function carregarFuncionario() {
            if (!funcionarioId) return;

            try {
                const result = await API.get(`/api/funcionarios/${funcionarioId}`);

                if (result.success && result.data) {
                    preencherFormulario(result.data);
                } else {
                    alert(result.message || i18n.notFound);
                    voltarParaLista();
                }
            } catch (error) {
                console.error('Erro ao carregar funcionário:', error);
                alert(i18n.serverError);
            }
        }

        // Preencher formulário com dados do funcionário
        function preencherFormulario(dados) {
            document.getElementById('funcionarioId').value = dados.id || '';
            document.getElementById('funcionarioNome').value = dados.nome || '';
            document.getElementById('funcionarioEmail').value = dados.email || '';
            document.getElementById('funcionarioUsuario').value = dados.usuario || '';
            document.getElementById('funcionarioStatus').value = dados.status || 'A';
            document.getElementById('funcionarioCPF').value = dados.cpf || '';
            document.getElementById('funcionarioNacionalidade').value = dados.nascionalidade || '';
            document.getElementById('funcionarioSexo').value = dados.sexo || '';
            document.getElementById('funcionarioEstadoCivil').value = dados.e_civil || '';
            document.getElementById('funcionarioCNH').value = dados.cnh || '';
            document.getElementById('funcionarioRegistroCNH').value = dados.registro_cnh || '';
            document.getElementById('funcionarioValidadeCNH').value = dados.validade_cnh || '';
            document.getElementById('funcionarioCTrabalho').value = dados.c_trabalho || '';
            document.getElementById('funcionarioPIS').value = dados.pis || '';

            // Formatar salário usando Currency helper
            if (typeof Currency !== 'undefined' && dados.salario) {
                Currency.setValue('#funcionarioSalario', dados.salario);
            } else {
                document.getElementById('funcionarioSalario').value = dados.salario || '';
            }

            document.getElementById('funcionarioTipoSalario').value = dados.tipo_salario || '';
            document.getElementById('funcionarioDiaPagamento').value = dados.dia_pagamento || '';
            document.getElementById('cep').value = dados.cep || '';
            document.getElementById('rua').value = dados.rua || '';
            document.getElementById('numero').value = dados.num || '';
            document.getElementById('complemento').value = dados.comple || '';
            document.getElementById('bairro').value = dados.bairro || '';
            document.getElementById('cidade').value = dados.cidade || '';
            document.getElementById('uf').value = dados.uf || '';
            const paisSelect = document.getElementById('pais');
            paisSelect.value = dados.pais || 'BR';
            paisSelect.dispatchEvent(new Event('change'));
            if (typeof jQuery !== 'undefined') $(paisSelect).trigger('chosen:updated');
            document.getElementById('funcionarioTelFixo').value = dados.tel_fixo || '';
            document.getElementById('funcionarioTelCel').value = dados.tel_cel || '';

            // Re-detectar país nos campos IntlPhone após preencher valores
            document.querySelectorAll('.intltel').forEach(input => {
                if (input._intlPhone && typeof input._intlPhone.detectCountryFromValue === 'function') {
                    input._intlPhone.detectCountryFromValue();
                }
            });

            // Foto
            if (dados.foto_url) {
                fotoPreview.src = dados.foto_url;
            }

            // Role (será selecionado após carregar roles)
            if (dados.id_role) {
                document.getElementById('funcionarioRole').dataset.selectedValue = dados.id_role;
                // Tentar selecionar se já carregou
                const selectRole = document.getElementById('funcionarioRole');
                if (selectRole.options.length > 1) {
                    selectRole.value = dados.id_role;
                }
            }

            // Filiais permitidas e filial principal
            definirFiliaisSelecionadas(
                dados.filiais_permitidas || [],
                dados.id_matriz_filial
            );

            // A auditoria precisa comparar contra os dados carregados via AJAX,
            // e não contra o formulário vazio renderizado inicialmente.
            if (window.FormAudit) {
                FormAudit.recapture(document.getElementById('formFuncionario'));
            }
        }

        // Voltar para lista
        function voltarParaLista() {
            if (isInIframe) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: '/pages/funcionarios'
                }, '*');
            } else {
                window.location.href = '/pages/funcionarios';
            }
        }

        // Função para abrir modal
        function abrirModalEscolhaFoto() {
            // Não abrir modal em modo de visualização
            if (isViewMode) return;

            if (isInIframe) {
                // Se estiver em iframe, pedir para o parent criar o modal
                window.parent.postMessage({
                    action: 'openFotoModal'
                }, '*');
            } else {
                // Se não estiver em iframe, usar modal local
                modalEscolhaFoto.classList.add('open');
                document.body.classList.add('modal-open');
            }
        }

        // Função para fechar modal
        function fecharModalEscolhaFoto() {
            if (isInIframe) {
                // Se estiver em iframe, pedir para o parent fechar o modal
                window.parent.postMessage({
                    action: 'closeFotoModal'
                }, '*');
            } else {
                // Se não estiver em iframe, fechar modal local
                modalEscolhaFoto.classList.remove('open');
                document.body.classList.remove('modal-open');
            }
        }

        // Escutar mensagens do parent quando estiver em iframe
        if (isInIframe) {
            window.addEventListener('message', function(event) {
                if (event.data && event.data.action === 'fotoModalActionResponse') {
                    const action = event.data.modalAction;
                    if (action === 'enviarArquivo') {
                        fotoInput.click();
                    } else if (action === 'usarCamera') {
                        abrirCamera();
                    }
                } else if (event.data && event.data.action === 'cameraPhotoResponse') {
                    // Recebeu foto capturada do parent
                    fotoPreview.src = event.data.fotoBase64;
                    fotoBase64Input.value = event.data.fotoBase64;
                } else if (event.data && event.data.action === 'refreshRoles') {
                    // Atualizar select de roles quando houver alterações
                    carregarRoles();
                }
            });
        }

        // Abrir modal de escolha ao clicar na foto
        fotoContainer.addEventListener('click', function() {
            abrirModalEscolhaFoto();
        });

        // Botão enviar arquivo (apenas se não estiver em iframe)
        if (!isInIframe) {
            document.getElementById('btnEnviarArquivo').addEventListener('click', function() {
                fecharModalEscolhaFoto();
                fotoInput.click();
            });

            // Botão usar câmera (apenas se não estiver em iframe)
            document.getElementById('btnUsarCamera').addEventListener('click', function() {
                fecharModalEscolhaFoto();
                abrirCamera();
            });

            // Botão cancelar escolha (apenas se não estiver em iframe)
            document.getElementById('btnCancelarEscolhaFoto').addEventListener('click', function() {
                fecharModalEscolhaFoto();
            });

            // Fechar modal ao clicar fora (apenas se não estiver em iframe)
            modalEscolhaFoto.addEventListener('click', function(e) {
                if (e.target === modalEscolhaFoto) {
                    fecharModalEscolhaFoto();
                }
            });
        }

        // Tipos de imagem aceitos
        const tiposAceitos = ['image/jpeg', 'image/png', 'image/webp'];

        // Processar arquivo selecionado
        fotoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validar tipo de arquivo
                if (!tiposAceitos.includes(file.type)) {
                    alert(i18n.formatNotSupported);
                    fotoInput.value = '';
                    return;
                }

                // Validar tamanho (máximo 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert(i18n.imageTooLarge);
                    fotoInput.value = '';
                    return;
                }

                processarImagem(file);
            }
        });

        // Função para processar imagem (arquivo ou base64)
        function processarImagem(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Redimensionar imagem se necessário (máximo 800x1000px)
                const img = new Image();
                img.onload = function() {
                    const maxWidth = 800;
                    const maxHeight = 1000;
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth || height > maxHeight) {
                        const ratio = Math.min(maxWidth / width, maxHeight / height);
                        width = width * ratio;
                        height = height * ratio;
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    const fotoBase64 = canvas.toDataURL('image/jpeg', 0.9);
                    fotoPreview.src = fotoBase64;
                    fotoBase64Input.value = fotoBase64;
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        // Função para abrir câmera
        function abrirCamera() {
            // Se estiver em iframe, delegar ao parent para abrir modal em tela cheia
            if (isInIframe) {
                window.parent.postMessage({
                    action: 'openCameraModal'
                }, '*');
                return;
            }

            // Código local para quando não está em iframe
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert(i18n.cameraNotSupported);
                return;
            }

            const constraints = {
                video: {
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                }
            };

            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(stream) {
                    streamCamera = stream;
                    videoCamera.srcObject = stream;
                    abrirModalCamera();
                })
                .catch(function(err) {
                    console.error('Erro ao acessar câmera:', err);
                    let mensagem = i18n.cameraError;
                    if (err.name === 'NotAllowedError') {
                        mensagem = i18n.cameraAccessDenied;
                    } else if (err.name === 'NotFoundError') {
                        mensagem = i18n.cameraNotFound;
                    }
                    alert(mensagem);
                });
        }

        // Botão capturar foto
        document.getElementById('btnCapturarFoto').addEventListener('click', function() {
            if (!videoCamera.videoWidth || !videoCamera.videoHeight) {
                alert(i18n.cameraInitializing);
                return;
            }

            const context = canvasCamera.getContext('2d');
            canvasCamera.width = videoCamera.videoWidth;
            canvasCamera.height = videoCamera.videoHeight;

            // Desenhar a imagem do vídeo no canvas
            context.drawImage(videoCamera, 0, 0, canvasCamera.width, canvasCamera.height);

            // Converter para base64 (JPEG com qualidade 0.9 para melhor compressão)
            const fotoBase64 = canvasCamera.toDataURL('image/jpeg', 0.9);
            fotoPreview.src = fotoBase64;
            fotoBase64Input.value = fotoBase64;

            // Fechar câmera
            fecharCamera();
        });

        // Botão cancelar câmera
        document.getElementById('btnCancelarCamera').addEventListener('click', function() {
            fecharCamera();
        });

        // Fechar modal de câmera ao clicar fora
        modalCameraPreview.addEventListener('click', function(e) {
            if (e.target === modalCameraPreview) {
                fecharCamera();
            }
        });

        // Função para abrir modal de câmera
        function abrirModalCamera() {
            modalCameraPreview.classList.add('open');
            document.body.classList.add('modal-open');
        }

        // Função para fechar câmera
        function fecharCamera() {
            if (streamCamera) {
                streamCamera.getTracks().forEach(track => track.stop());
                streamCamera = null;
            }
            videoCamera.srcObject = null;
            modalCameraPreview.classList.remove('open');
            document.body.classList.remove('modal-open');
        }

        // Limpar recursos ao sair da página
        window.addEventListener('beforeunload', function() {
            fecharCamera();
        });

        // Botão voltar - Navega de volta para lista de funcionários
        document.getElementById('btnVoltarListaFuncionarios')?.addEventListener('click', function() {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: '/pages/funcionarios'
                }, '*');
            }
        });

        // Botão cancelar - Navega de volta para lista de funcionários
        document.getElementById('btnCancelarFormFuncionario')?.addEventListener('click', function() {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: '/pages/funcionarios'
                }, '*');
            }
        });

        // Validação de senha com feedback visual
        const senhaInput = document.getElementById('funcionarioSenha');
        const confirmarSenhaInput = document.getElementById('funcionarioConfirmarSenha');
        const senhaErro = document.getElementById('senhaErro');

        function validarSenhas() {
            const senha = senhaInput.value;
            const confirmar = confirmarSenhaInput.value;

            // Em modo de edição, ambos vazios é válido
            if (editando && senha.length === 0 && confirmar.length === 0) {
                confirmarSenhaInput.classList.remove('border-green-500', 'border-red-500');
                senhaErro?.classList.add('hidden');
                confirmarSenhaInput.setCustomValidity('');
                return;
            }

            if (confirmar.length === 0) {
                // Campo vazio - resetar
                confirmarSenhaInput.classList.remove('border-green-500', 'border-red-500');
                senhaErro?.classList.add('hidden');
                confirmarSenhaInput.setCustomValidity('');
                return;
            }

            if (senha !== confirmar) {
                // Senhas não coincidem
                confirmarSenhaInput.classList.remove('border-green-500');
                confirmarSenhaInput.classList.add('border-red-500');
                senhaErro?.classList.remove('hidden');
                confirmarSenhaInput.setCustomValidity(i18n.passwordsDontMatch);
            } else {
                // Senhas coincidem
                confirmarSenhaInput.classList.remove('border-red-500');
                confirmarSenhaInput.classList.add('border-green-500');
                senhaErro?.classList.add('hidden');
                confirmarSenhaInput.setCustomValidity('');
            }
        }

        senhaInput?.addEventListener('input', validarSenhas);
        confirmarSenhaInput?.addEventListener('input', validarSenhas);

        // Validação do nome - bloquear termo "suporte"
        const nomeInput = document.getElementById('funcionarioNome');
        const nomeErro = document.getElementById('nomeErro');

        nomeInput?.addEventListener('input', function() {
            this.classList.remove('border-red-500');
            nomeErro?.classList.add('hidden');

            if (this.value.toLowerCase().includes('suporte')) {
                this.classList.add('border-red-500');
                nomeErro?.classList.remove('hidden');
                this.setCustomValidity(i18n.nameSupportError);
            } else {
                this.setCustomValidity('');
            }
        });

        // Verificação de disponibilidade de usuário
        const usuarioInput = document.getElementById('funcionarioUsuario');
        const usuarioErro = document.getElementById('usuarioErro');
        let usuarioTimeout = null;

        usuarioInput?.addEventListener('input', function() {
            // Limpar timeout anterior
            if (usuarioTimeout) clearTimeout(usuarioTimeout);

            // Remover classes de feedback e esconder erro
            this.classList.remove('border-green-500', 'border-red-500');
            usuarioErro?.classList.add('hidden');

            const usuario = this.value.trim();
            if (usuario.length < 3) return;

            // Bloquear usuários com termo "suporte"
            if (usuario.toLowerCase().includes('suporte')) {
                this.classList.add('border-red-500');
                usuarioErro.textContent = i18n.usernameSupportError;
                usuarioErro?.classList.remove('hidden');
                this.setCustomValidity(i18n.usernameSupportError);
                return;
            }

            // Debounce de 500ms
            const inputElement = this;
            usuarioTimeout = setTimeout(async () => {
                try {
                    const params = { usuario: usuario };

                    const result = await API.get('/api/funcionarios/check-usuario', params);

                    if (result.success) {
                        if (result.disponivel) {
                            inputElement.classList.add('border-green-500');
                            usuarioErro?.classList.add('hidden');
                            inputElement.setCustomValidity('');
                        } else {
                            inputElement.classList.add('border-red-500');
                            usuarioErro.textContent = result.message;
                            usuarioErro?.classList.remove('hidden');
                            inputElement.setCustomValidity(result.message);
                        }
                    }
                } catch (error) {
                    console.error('Erro ao verificar usuário:', error);
                }
            }, 500);
        });

        // Submissão do formulário
        document.getElementById('formFuncionario')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Validar senhas antes de enviar
            const senha = senhaInput.value;
            const confirmarSenha = confirmarSenhaInput.value;

            // Em modo de adição, senha é obrigatória
            if (!editando && senha.length === 0) {
                alert(i18n.passwordRequired);
                senhaInput.focus();
                return;
            }

            // Se digitou senha, deve confirmar
            if (senha.length > 0 && senha !== confirmarSenha) {
                alert(i18n.passwordMismatch);
                confirmarSenhaInput.focus();
                return;
            }

            const formData = new FormData(this);

            // Definir endpoint baseado no modo
            const endpoint = editando
                ? `/funcionarios/${funcionarioId}/atualizar`
                : '/funcionarios/salvar';

            try {
                const result = await API.postForm(endpoint, formData);

                if (result.success) {
                    // Navegar de volta para lista após sucesso
                    voltarParaLista();
                } else {
                    alert((editando ? i18n.updateError : i18n.saveError).replace(':message', result.message || ''));
                }
            } catch (error) {
                console.error('Erro ao salvar funcionário:', error);
                alert(i18n.serverError);
            }
        });

        // Carregar roles disponíveis
        async function carregarRoles() {
            try {
                const result = await API.get('/api/funcionarios/roles');

                if (result.success && result.data) {
                    const selectRole = document.getElementById('funcionarioRole');
                    if (selectRole) {
                        // Limpar opções existentes (exceto a primeira)
                        selectRole.innerHTML = `<option value="">${i18n.selectRole}</option>`;

                        // Adicionar roles
                        result.data.forEach(role => {
                            const option = document.createElement('option');
                            option.value = role.id;
                            option.textContent = role.name;
                            if (role.description) {
                                option.title = role.description;
                            }
                            selectRole.appendChild(option);
                        });
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar roles:', error);
            }
        }

        // Carregar roles ao inicializar
        carregarRoles();

        // Botão para adicionar nova role (abre offcanvas de gerenciamento)
        const btnAdicionarRole = document.getElementById('btnAdicionarRole');
        if (btnAdicionarRole) {
            btnAdicionarRole.addEventListener('click', function() {
                if (window.parent !== window) {
                    // Está em iframe, envia mensagem para o parent abrir o offcanvas
                    window.parent.postMessage({
                        action: 'openOffcanvasIframe',
                        url: '/pages/roles/gerenciar',
                        title: i18n.manageRoles,
                        width: '600px'
                    }, '*');
                } else {
                    // Está no documento principal
                    if (typeof window.openOffcanvasIframe === 'function') {
                        window.openOffcanvasIframe('/pages/roles/gerenciar', i18n.manageRoles, '600px');
                    }
                }
            });
        }

        // ============================================
        // COMPONENTE DROPDOWN DE FILIAIS
        // ============================================
        let filiaisDisponiveis = [];
        let filialPrincipalId = null;
        let filiaisPermitidasIds = [];
        let dropdownAberto = false;

        const dropdown = document.getElementById('filiaisDropdown');
        const dropdownTrigger = document.getElementById('filiaisDropdownTrigger');
        const dropdownText = document.getElementById('filiaisDropdownText');
        const dropdownMenu = document.getElementById('filiaisDropdownMenu');

        // Toggle dropdown
        function toggleDropdown() {
            if (isViewMode) return;
            dropdownAberto = !dropdownAberto;
            dropdown.classList.toggle('open', dropdownAberto);
        }

        // Fechar dropdown
        function fecharDropdown() {
            dropdownAberto = false;
            dropdown.classList.remove('open');
        }

        // Event listeners do dropdown
        dropdownTrigger?.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleDropdown();
        });

        // Fechar ao clicar fora
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                fecharDropdown();
            }
        });

        // Fechar com Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                fecharDropdown();
            }
        });

        // Carregar filiais
        async function carregarMatrizFiliais() {
            try {
                const result = await API.get('/api/matrizes-filiais/buscar');

                if (result.success && result.data) {
                    filiaisDisponiveis = result.data;
                    renderizarFiliais();
                } else {
                    dropdownMenu.innerHTML = `<div class="filiais-dropdown-error">${i18n.branchLoadErrorDetail}</div>`;
                    dropdownText.textContent = i18n.branchLoadError;
                }
            } catch (error) {
                console.error('Erro ao carregar matriz/filiais:', error);
                dropdownMenu.innerHTML = `<div class="filiais-dropdown-error">${i18n.branchLoadErrorDetail}</div>`;
                dropdownText.textContent = i18n.branchLoadError;
            }
        }

        // Renderizar lista de filiais
        function renderizarFiliais() {
            if (filiaisDisponiveis.length === 0) {
                dropdownMenu.innerHTML = `<div class="filiais-dropdown-empty">${i18n.branchNoBranches}</div>`;
                dropdownText.textContent = i18n.branchNoBranchesShort;
                return;
            }

            let html = '';
            filiaisDisponiveis.forEach((filial, index) => {
                // Em modo de edição com filiais pré-selecionadas, usar filiaisPermitidasIds
                // Caso contrário (novo funcionário), selecionar a primeira por padrão
                let isChecked, isPrincipal;

                if (editando && filiaisPermitidasIds.length > 0) {
                    isChecked = filiaisPermitidasIds.includes(filial.id) || filiaisPermitidasIds.includes(String(filial.id));
                    isPrincipal = filialPrincipalId == filial.id;
                } else if (!editando) {
                    // Na criação, selecionar a primeira filial por padrão
                    isChecked = index === 0;
                    isPrincipal = index === 0;
                    if (isPrincipal) {
                        filialPrincipalId = filial.id;
                    }
                } else {
                    // Edição sem filiais pré-selecionadas
                    isChecked = false;
                    isPrincipal = false;
                }

                html += `
                    <div class="filial-item ${isChecked ? 'selected' : ''}" data-id="${filial.id}" data-nome="${filial.nome}">
                        <label class="filial-checkbox-label" onclick="event.stopPropagation()">
                            <input type="checkbox" class="filial-checkbox" value="${filial.id}" ${isChecked ? 'checked' : ''}>
                            <span class="filial-nome">${filial.nome}</span>
                        </label>
                        <button type="button" class="filial-star-btn ${isPrincipal ? 'active' : ''}"
                                data-id="${filial.id}" title="${i18n.branchSetAsMain}"
                                onclick="event.stopPropagation()">
                            <i class="fas fa-star"></i>
                        </button>
                    </div>
                `;
            });

            dropdownMenu.innerHTML = html;

            // Event listeners
            dropdownMenu.querySelectorAll('.filial-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', handleFilialCheckboxChange);
            });

            dropdownMenu.querySelectorAll('.filial-star-btn').forEach(btn => {
                btn.addEventListener('click', handleFilialStarClick);
            });

            atualizarTextoDropdown();
            atualizarHiddenInputs();
        }

        // Definir valores iniciais de filiais (chamada após carregar dados do funcionário)
        function definirFiliaisSelecionadas(filiaisPermitidas, idMatrizFilial) {
            filiaisPermitidasIds = filiaisPermitidas || [];
            filialPrincipalId = idMatrizFilial;

            // Se as filiais já foram carregadas, re-renderizar
            if (filiaisDisponiveis.length > 0) {
                renderizarFiliais();
            }
        }

        // Handler para checkbox
        function handleFilialCheckboxChange(e) {
            const checkbox = e.target;
            const filialItem = checkbox.closest('.filial-item');
            const filialId = parseInt(checkbox.value);
            const starBtn = filialItem.querySelector('.filial-star-btn');

            if (checkbox.checked) {
                filialItem.classList.add('selected');
                // Se não há principal, esta vira principal
                const temPrincipal = document.querySelector('.filial-star-btn.active');
                if (!temPrincipal) {
                    starBtn.classList.add('active');
                    filialPrincipalId = filialId;
                }
            } else {
                filialItem.classList.remove('selected');
                // Se esta era a principal, passar para outra
                if (starBtn.classList.contains('active')) {
                    starBtn.classList.remove('active');
                    const primeiroMarcado = document.querySelector('.filial-checkbox:checked');
                    if (primeiroMarcado) {
                        const novoItem = primeiroMarcado.closest('.filial-item');
                        novoItem.querySelector('.filial-star-btn').classList.add('active');
                        filialPrincipalId = parseInt(primeiroMarcado.value);
                    } else {
                        filialPrincipalId = null;
                    }
                }
            }

            atualizarTextoDropdown();
            atualizarHiddenInputs();
        }

        // Handler para estrela (principal)
        function handleFilialStarClick(e) {
            const btn = e.currentTarget;
            const filialItem = btn.closest('.filial-item');
            const checkbox = filialItem.querySelector('.filial-checkbox');
            const filialId = parseInt(btn.dataset.id);

            // Só pode marcar como principal se estiver selecionada
            if (!checkbox.checked) {
                checkbox.checked = true;
                filialItem.classList.add('selected');
            }

            // Remover active de todas as estrelas
            document.querySelectorAll('.filial-star-btn').forEach(b => b.classList.remove('active'));

            // Marcar esta como principal
            btn.classList.add('active');
            filialPrincipalId = filialId;

            atualizarTextoDropdown();
            atualizarHiddenInputs();
        }

        // Atualizar texto do dropdown fechado
        function atualizarTextoDropdown() {
            const selecionados = Array.from(document.querySelectorAll('.filial-checkbox:checked'))
                .map(cb => cb.closest('.filial-item').dataset.nome);

            if (selecionados.length === 0) {
                dropdownText.textContent = i18n.branchSelect;
            } else if (selecionados.length === 1) {
                dropdownText.textContent = selecionados[0];
            } else if (selecionados.length <= 2) {
                dropdownText.textContent = selecionados.join(', ');
            } else {
                dropdownText.textContent = `${selecionados[0]} +${selecionados.length - 1}`;
            }
        }

        // Atualizar inputs hidden
        function atualizarHiddenInputs() {
            const checkboxesMarcados = document.querySelectorAll('.filial-checkbox:checked');
            const filiais = Array.from(checkboxesMarcados).map(cb => cb.value);

            document.getElementById('filiaisPermitidasJson').value = JSON.stringify(filiais);
            document.getElementById('funcionarioMatrizFilial').value = filialPrincipalId || '';
        }

        // Inicialização
        async function inicializar() {
            // Aplicar máscara de moeda no campo salário
            if (typeof Currency !== 'undefined') {
                Currency.applyMaskToAll('input-moeda');
            }

            // Configurar modo de edição se necessário
            if (editando) {
                configurarModoEdicao();
            }

            // Carregar dados auxiliares primeiro
            await Promise.all([
                carregarRoles(),
                carregarMatrizFiliais()
            ]);

            // Se estiver em modo de edição, carregar dados do funcionário
            if (editando) {
                await carregarFuncionario();
            }
        }

        inicializar();
    })();
</script>
@endsection
