@extends('layouts.iframe')

@section('title', t('modules.perfil.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Formulário de Dados do Perfil -->
    <form id="formPerfil" method="POST">
        @csrf

        <!-- Seção: Foto e Dados Pessoais -->
        <div class="form-section mb-6 relative">
            <h3 class="form-section-title"><?= t('modules.perfil.sections.my_data') ?></h3>

            <!-- Container da foto posicionado no canto superior direito -->
            <div class="fotoClientePreviewContainer absolute top-0 right-0 w-40 h-50 border-2 border-slate-300 rounded-md overflow-hidden bg-slate-100 cursor-pointer group z-10" id="fotoPerfilContainer" style="margin-top: 0.8rem; margin-right: 1.5rem;">
                <img id="fotoPerfilPreview"
                    src="<?= image('assets/img/foto_padrao.png') ?>"
                    alt="<?= t('modules.perfil.photo.alt') ?>"
                    class="w-full h-full object-cover" style="width: 156px; height: 177px;">
                <input type="file" id="fotoPerfilInput" name="foto" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                <input type="hidden" id="fotoPerfilBase64" name="foto_base64">
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex flex-col justify-end">
                    <div class="bg-black bg-opacity-40 text-white text-center py-2 text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <?= t('modules.perfil.photo.change') ?>
                    </div>
                </div>
            </div>

            <!-- Grid: Nome (readonly) | Email -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-5 form-input-group">
                    <label for="perfilNome" class="form-label-group"><?= t('modules.perfil.fields.full_name') ?></label>
                    <input type="text" id="perfilNome" name="nome" class="form-input-group-field bg-slate-100" readonly disabled>
                </div>

                <div class="md:col-span-5 form-input-group">
                    <label for="perfilEmail" class="form-label-group"><?= t('modules.perfil.fields.email') ?> <span class="text-red-500">*</span></label>
                    <input type="email" id="perfilEmail" name="email" class="form-input-group-field" required>
                </div>
            </div>

            <!-- Grid: Telefones -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                <div class="md:col-span-5 form-input-group">
                    <label for="perfilTelFixo" class="form-label-group"><?= t('modules.perfil.fields.phone_landline') ?></label>
                    <input type="tel" id="perfilTelFixo" name="tel_fixo" class="form-input-group-field intltel">
                </div>

                <div class="md:col-span-5 form-input-group">
                    <label for="perfilTelCel" class="form-label-group"><?= t('modules.perfil.fields.phone_mobile') ?></label>
                    <input type="tel" id="perfilTelCel" name="tel_cel" class="form-input-group-field intltel">
                </div>
            </div>
        </div>

        <!-- Seção: Informações da Conta (somente leitura) -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.perfil.sections.account_info') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-4 form-input-group">
                    <label for="perfilUsuario" class="form-label-group"><?= t('modules.perfil.fields.username') ?></label>
                    <input type="text" id="perfilUsuario" name="usuario" class="form-input-group-field bg-slate-100" readonly disabled>
                </div>

                <div class="md:col-span-4 form-input-group">
                    <label for="perfilFuncao" class="form-label-group"><?= t('modules.perfil.fields.role') ?></label>
                    <input type="text" id="perfilFuncao" name="funcao" class="form-input-group-field bg-slate-100" readonly disabled>
                </div>

                <div class="md:col-span-4 form-input-group">
                    <label for="perfilFilial" class="form-label-group"><?= t('modules.perfil.fields.main_branch') ?></label>
                    <input type="text" id="perfilFilial" name="filial" class="form-input-group-field bg-slate-100" readonly disabled>
                </div>
            </div>
        </div>

        <!-- Botões de Ação do Formulário de Dados -->
        <div class="flex items-center justify-end gap-3 mb-6">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" id="btnSalvarPerfil" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-save mr-2"></i><?= t('common.buttons.save_changes') ?>
            </button>
        </div>
    </form>

    <!-- Seção: Alterar Senha -->
    <form id="formAlterarSenha" method="POST">
        @csrf
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.perfil.sections.change_password') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-4 form-input-group">
                    <label for="senhaAtual" class="form-label-group"><?= t('modules.perfil.fields.current_password') ?> <span class="text-red-500">*</span></label>
                    <input type="password" id="senhaAtual" name="senha_atual" class="form-input-group-field" required>
                </div>

                <div class="md:col-span-4 form-input-group">
                    <label for="novaSenha" class="form-label-group"><?= t('modules.perfil.fields.new_password') ?> <span class="text-red-500">*</span></label>
                    <input type="password" id="novaSenha" name="nova_senha" class="form-input-group-field" required minlength="6">
                    <span class="text-slate-400 text-xs mt-1"><?= t('modules.perfil.password.min_chars') ?></span>
                </div>

                <div class="md:col-span-4 form-input-group">
                    <label for="confirmarSenha" class="form-label-group"><?= t('modules.perfil.fields.confirm_password') ?> <span class="text-red-500">*</span></label>
                    <input type="password" id="confirmarSenha" name="confirmar_senha" class="form-input-group-field" required>
                    <span id="senhaErro" class="text-red-500 text-xs mt-1 hidden"><?= t('modules.perfil.password.mismatch') ?></span>
                </div>
            </div>

            <!-- Botão de Alterar Senha -->
            <div class="flex items-center justify-end gap-3 mt-4">
                <button type="submit" id="btnAlterarSenha" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-key mr-2"></i><?= t('modules.perfil.password.change_button') ?>
                </button>
            </div>
        </div>
    </form>

    <!-- Modal de escolha de foto -->
    <div id="modalEscolhaFoto" class="modal-overlay">
        <div class="modal-box">
            <h3 class="modal-title"><?= t('modules.perfil.photo.modal_title') ?></h3>
            <div class="modal-actions" style="flex-direction: column; gap: 0.5rem;">
                <button type="button" id="btnEscolherArquivo" class="btn-blue" style="width: 100%;">
                    <i class="fas fa-folder-open mr-2"></i><?= t('modules.perfil.photo.choose_gallery') ?>
                </button>
                <button type="button" id="btnUsarCamera" class="btn-secondary" style="width: 100%;">
                    <i class="fas fa-camera mr-2"></i><?= t('modules.perfil.photo.use_camera') ?>
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
            <h3 class="modal-title"><?= t('modules.perfil.photo.take_photo') ?></h3>
            <div style="margin-bottom: 1rem; display: flex; justify-content: center; align-items: center; background: #000; border-radius: 0.5rem; overflow: hidden;">
                <video id="videoCamera" autoplay playsinline style="width: 100%; max-height: 400px; display: block;"></video>
                <canvas id="canvasCamera" style="display: none;"></canvas>
            </div>
            <div class="modal-actions">
                <button type="button" id="btnCapturarFoto" class="btn-blue">
                    <i class="fas fa-camera mr-2"></i><?= t('modules.perfil.photo.capture') ?>
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
<script>
(function() {
    const i18n = {
        saving: '<?= addslashes(t("common.labels.saving")) ?>',
        saveChanges: '<?= addslashes(t("common.buttons.save_changes")) ?>',
        changing: '<?= addslashes(t("modules.perfil.password.change_button")) ?>...',
        changePassword: '<?= addslashes(t("modules.perfil.password.change_button")) ?>',
        loadError: '<?= addslashes(t("modules.perfil.messages.load_error")) ?>',
        saveError: '<?= addslashes(t("modules.perfil.messages.save_error")) ?>',
        saveSuccess: '<?= addslashes(t("modules.perfil.messages.save_success")) ?>',
        passwordSuccess: '<?= addslashes(t("modules.perfil.messages.password_success")) ?>',
        passwordError: '<?= addslashes(t("modules.perfil.messages.password_error")) ?>',
        serverError: '<?= addslashes(t("modules.perfil.messages.server_error")) ?>',
        cameraError: '<?= addslashes(t("modules.perfil.photo.camera_error")) ?>',
    };

    // Variáveis para gerenciamento de foto
    const fotoContainer = document.getElementById('fotoPerfilContainer');
    const fotoPreview = document.getElementById('fotoPerfilPreview');
    const fotoInput = document.getElementById('fotoPerfilInput');
    const fotoBase64Input = document.getElementById('fotoPerfilBase64');
    const modalEscolhaFoto = document.getElementById('modalEscolhaFoto');
    const modalCameraPreview = document.getElementById('modalCameraPreview');
    const videoCamera = document.getElementById('videoCamera');
    const canvasCamera = document.getElementById('canvasCamera');
    let streamCamera = null;
    let fotoAlterada = false;

    // Verificar se está dentro de um iframe
    const isInIframe = window.parent !== window;

    // ========== CARREGAR DADOS DO PERFIL ==========
    async function carregarPerfil() {
        try {
            const result = await API.get('/api/perfil');

            if (result.success && result.data) {
                preencherFormulario(result.data);
            } else {
                mostrarErro(result.message || i18n.loadError);
            }
        } catch (error) {
            console.error('Erro ao carregar perfil:', error);
            mostrarErro(i18n.serverError);
        }
    }

    // Preencher formulário com dados do perfil
    function preencherFormulario(dados) {
        document.getElementById('perfilNome').value = dados.nome || '';
        document.getElementById('perfilEmail').value = dados.email || '';
        document.getElementById('perfilTelFixo').value = dados.tel_fixo || '';
        document.getElementById('perfilTelCel').value = dados.tel_cel || '';
        document.getElementById('perfilUsuario').value = dados.usuario || '';
        document.getElementById('perfilFuncao').value = dados.role_nome || dados.funcao || '';
        document.getElementById('perfilFilial').value = dados.filial_nome || '';

        // Foto
        if (dados.foto_url) {
            fotoPreview.src = dados.foto_url;
        }

        // Re-detectar país nos campos IntlPhone após preencher valores
        document.querySelectorAll('.intltel').forEach(input => {
            if (input._intlPhone && typeof input._intlPhone.detectCountryFromValue === 'function') {
                input._intlPhone.detectCountryFromValue();
            }
        });
    }

    // ========== SALVAR PERFIL ==========
    async function salvarPerfil(e) {
        e.preventDefault();

        const btnSalvar = document.getElementById('btnSalvarPerfil');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;

        try {
            const dados = {
                email: document.getElementById('perfilEmail').value,
                tel_fixo: document.getElementById('perfilTelFixo').value,
                tel_cel: document.getElementById('perfilTelCel').value,
                foto_base64: fotoBase64Input.value || ''
            };

            const result = await API.post('/perfil/atualizar', dados);

            if (result.success) {
                mostrarSucesso(result.message || i18n.saveSuccess);

                // Atualizar foto no header se foi alterada
                if (result.data && result.data.foto_url) {
                    atualizarFotoHeader(result.data.foto_url);
                }

                // Limpar o base64 após salvar
                fotoBase64Input.value = '';
                fotoAlterada = false;
            } else {
                mostrarErro(result.message || i18n.saveError);
            }
        } catch (error) {
            console.error('Erro ao salvar perfil:', error);
            mostrarErro(i18n.serverError);
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="fas fa-save mr-2"></i>' + i18n.saveChanges;
        }
    }

    // ========== ALTERAR SENHA ==========
    async function alterarSenha(e) {
        e.preventDefault();

        const novaSenha = document.getElementById('novaSenha').value;
        const confirmarSenha = document.getElementById('confirmarSenha').value;
        const senhaErro = document.getElementById('senhaErro');

        // Validar senhas
        if (novaSenha !== confirmarSenha) {
            senhaErro.classList.remove('hidden');
            return;
        }
        senhaErro.classList.add('hidden');

        const btnAlterar = document.getElementById('btnAlterarSenha');
        btnAlterar.disabled = true;
        btnAlterar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.changing;

        try {
            const dados = {
                senha_atual: document.getElementById('senhaAtual').value,
                nova_senha: novaSenha,
                confirmar_senha: confirmarSenha
            };

            const result = await API.post('/perfil/alterar-senha', dados);

            if (result.success) {
                mostrarSucesso(result.message || i18n.passwordSuccess);
                // Limpar campos de senha
                document.getElementById('senhaAtual').value = '';
                document.getElementById('novaSenha').value = '';
                document.getElementById('confirmarSenha').value = '';
            } else {
                mostrarErro(result.message || i18n.passwordError);
            }
        } catch (error) {
            console.error('Erro ao alterar senha:', error);
            mostrarErro(i18n.serverError);
        } finally {
            btnAlterar.disabled = false;
            btnAlterar.innerHTML = '<i class="fas fa-key mr-2"></i>' + i18n.changePassword;
        }
    }

    // ========== GERENCIAMENTO DE FOTO ==========
    function abrirModalEscolhaFoto() {
        modalEscolhaFoto.classList.add('open');
    }

    function fecharModalEscolhaFoto() {
        modalEscolhaFoto.classList.remove('open');
    }

    function abrirCamera() {
        fecharModalEscolhaFoto();
        modalCameraPreview.classList.add('open');

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
            .then(stream => {
                streamCamera = stream;
                videoCamera.srcObject = stream;
            })
            .catch(err => {
                console.error('Erro ao acessar câmera:', err);
                mostrarErro(i18n.cameraError);
                fecharCamera();
            });
    }

    function fecharCamera() {
        if (streamCamera) {
            streamCamera.getTracks().forEach(track => track.stop());
            streamCamera = null;
        }
        modalCameraPreview.classList.remove('open');
    }

    function capturarFoto() {
        const context = canvasCamera.getContext('2d');
        canvasCamera.width = videoCamera.videoWidth;
        canvasCamera.height = videoCamera.videoHeight;
        context.drawImage(videoCamera, 0, 0);

        const base64 = canvasCamera.toDataURL('image/jpeg', 0.8);
        fotoPreview.src = base64;
        fotoBase64Input.value = base64;
        fotoAlterada = true;

        fecharCamera();
    }

    function processarArquivoFoto(file) {
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            fotoPreview.src = e.target.result;
            fotoBase64Input.value = e.target.result;
            fotoAlterada = true;
        };
        reader.readAsDataURL(file);
    }

    // Atualizar foto no header principal (se existir)
    function atualizarFotoHeader(novaUrl) {
        if (isInIframe && window.parent) {
            window.parent.postMessage({
                action: 'updateUserPhoto',
                photoUrl: novaUrl
            }, '*');
        }
    }

    // ========== FEEDBACK ==========
    function mostrarSucesso(mensagem) {
        if (isInIframe && window.parent) {
            window.parent.postMessage({
                action: 'showToast',
                type: 'success',
                message: mensagem
            }, '*');
        } else {
            toast.success(mensagem);
        }
    }

    function mostrarErro(mensagem) {
        if (isInIframe && window.parent) {
            window.parent.postMessage({
                action: 'showToast',
                type: 'error',
                message: mensagem
            }, '*');
        } else {
            toast.error(mensagem);
        }
    }

    function fecharOffcanvas() {
        if (isInIframe && window.parent && typeof window.parent.closeOffcanvas === 'function') {
            window.parent.closeOffcanvas();
        }
    }

    // ========== EVENT LISTENERS ==========
    // Formulário de perfil
    document.getElementById('formPerfil').addEventListener('submit', salvarPerfil);

    // Formulário de senha
    document.getElementById('formAlterarSenha').addEventListener('submit', alterarSenha);

    // Validar senhas em tempo real
    document.getElementById('confirmarSenha').addEventListener('input', function() {
        const novaSenha = document.getElementById('novaSenha').value;
        const senhaErro = document.getElementById('senhaErro');
        if (this.value && this.value !== novaSenha) {
            senhaErro.classList.remove('hidden');
        } else {
            senhaErro.classList.add('hidden');
        }
    });

    // Botão cancelar
    document.getElementById('btnCancelar').addEventListener('click', fecharOffcanvas);

    // Foto - Clique no container
    fotoContainer.addEventListener('click', abrirModalEscolhaFoto);

    // Foto - Escolher arquivo
    document.getElementById('btnEscolherArquivo').addEventListener('click', function() {
        fecharModalEscolhaFoto();
        fotoInput.click();
    });

    // Foto - Input file change
    fotoInput.addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            processarArquivoFoto(e.target.files[0]);
        }
    });

    // Foto - Usar câmera
    document.getElementById('btnUsarCamera').addEventListener('click', abrirCamera);

    // Foto - Capturar
    document.getElementById('btnCapturarFoto').addEventListener('click', capturarFoto);

    // Foto - Cancelar escolha
    document.getElementById('btnCancelarEscolhaFoto').addEventListener('click', fecharModalEscolhaFoto);

    // Foto - Cancelar câmera
    document.getElementById('btnCancelarCamera').addEventListener('click', fecharCamera);

    // ========== INICIALIZAÇÃO ==========
    carregarPerfil();
})();
</script>
@endsection
