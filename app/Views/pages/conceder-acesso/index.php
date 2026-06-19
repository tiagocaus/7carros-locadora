@extends('layouts.iframe')

@section('title', t('modules.conceder_acesso.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.conceder_acesso.subtitle') ?></h2>
    </div>

    <!-- Card principal -->
    <div class="bg-white shadow-md rounded-lg p-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Coluna esquerda: Conteudo principal -->
            <div class="lg:col-span-3">
                <div id="loading" class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-slate-400"></i>
                    <p class="text-slate-500 mt-2"><?= t('common.labels.loading') ?></p>
                </div>

                <!-- Estado: Sem usuario de suporte -->
                <div id="estadoSemUsuario" class="hidden">
                    <div class="text-center py-8">
                        <i class="fas fa-user-shield text-6xl text-slate-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-slate-700 mb-2"><?= t('modules.conceder_acesso.no_user') ?></h3>
                        <p class="text-slate-500 mb-6"><?= t('modules.conceder_acesso.no_user_desc') ?></p>
                        <button id="btnCriar" class="btn-blue py-3 px-6 rounded-md text-sm font-medium flex items-center mx-auto shadow hover:shadow-md transition-shadow">
                            <i class="fas fa-user-plus mr-2"></i><?= t('modules.conceder_acesso.create_user') ?>
                        </button>
                    </div>
                </div>

                <!-- Estado: Com usuario de suporte -->
                <div id="estadoComUsuario" class="hidden">
                    <div class="text-center py-8">
                        <i class="fas fa-user-check text-6xl text-green-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-slate-700 mb-2"><?= t('modules.conceder_acesso.active_user') ?></h3>
                        <p class="text-slate-500 mb-4"><?= t('modules.conceder_acesso.active_user_desc') ?></p>

                        <div class="flex items-center justify-center gap-2 mb-6">
                            <div class="bg-slate-100 border border-slate-300 rounded-lg px-6 py-3">
                                <span id="usuarioNome" class="text-xl font-mono font-bold text-slate-800"></span>
                            </div>
                            <button id="btnCopiar" class="btn-outline py-3 px-4 rounded-md text-sm font-medium flex items-center hover:bg-slate-100 transition-colors" title="<?= t('common.buttons.copy') ?>">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>

                        <p class="text-xs text-slate-400 mb-6" id="criadoEm"></p>

                        <div class="border-t border-slate-200 pt-6">
                            <p class="text-slate-500 text-sm mb-4"><?= t('modules.conceder_acesso.delete_hint') ?></p>
                            <button id="btnExcluir" class="btn-red py-3 px-6 rounded-md text-sm font-medium flex items-center mx-auto shadow hover:shadow-md transition-shadow">
                                <i class="fas fa-trash mr-2"></i><?= t('modules.conceder_acesso.delete_user') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna direita: Instrucoes -->
            <div class="lg:col-span-1">
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 h-full">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                        <div class="text-sm text-blue-800">
                            <p class="font-medium mb-2"><?= t('modules.conceder_acesso.how_it_works') ?></p>
                            <p><?= t('modules.conceder_acesso.how_it_works_desc') ?></p>
                            <p class="mt-2 font-medium text-red-600"><?= t('modules.conceder_acesso.warning_text') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const i18n = {
        loadError: <?= json_encode(t('modules.conceder_acesso.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        serverError: <?= json_encode(t('modules.conceder_acesso.messages.server_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        createError: <?= json_encode(t('modules.conceder_acesso.messages.create_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        createUser: <?= json_encode(t('modules.conceder_acesso.create_user'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteUser: <?= json_encode(t('modules.conceder_acesso.delete_user'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        copied: <?= json_encode(t('modules.conceder_acesso.messages.copied'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        copiedShort: <?= json_encode(t('modules.conceder_acesso.messages.copied_short'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleteError: <?= json_encode(t('modules.conceder_acesso.messages.delete_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        createdAt: <?= json_encode(t('modules.conceder_acesso.created_at'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        confirmDeleteTitle: <?= json_encode(t('modules.conceder_acesso.confirm_delete_title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        confirmDeleteMessage: <?= json_encode(t('modules.conceder_acesso.confirm_delete_message'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        creating: <?= json_encode(t('common.labels.creating'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        deleting: <?= json_encode(t('common.labels.deleting'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    let usuarioAtual = null;

    const loading = document.getElementById('loading');
    const estadoSemUsuario = document.getElementById('estadoSemUsuario');
    const estadoComUsuario = document.getElementById('estadoComUsuario');
    const usuarioNome = document.getElementById('usuarioNome');
    const criadoEm = document.getElementById('criadoEm');
    const btnCriar = document.getElementById('btnCriar');
    const btnCopiar = document.getElementById('btnCopiar');
    const btnExcluir = document.getElementById('btnExcluir');

    let pendingDeleteResolve = null;

    function mostrarModalConfirmacao() {
        return new Promise((resolve) => {
            pendingDeleteResolve = resolve;
            window.parent.postMessage({
                action: 'openGenericConfirmModal',
                title: i18n.confirmDeleteTitle,
                message: i18n.confirmDeleteMessage,
                confirmText: <?= json_encode(t('common.buttons.delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
            }, '*');
        });
    }

    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'genericConfirmed') {
            if (pendingDeleteResolve) {
                pendingDeleteResolve(true);
                pendingDeleteResolve = null;
            }
        } else if (event.data && event.data.action === 'genericModalClosed') {
            if (pendingDeleteResolve) {
                pendingDeleteResolve(false);
                pendingDeleteResolve = null;
            }
        }
    });

    async function carregarStatus() {
        mostrarLoading();
        try {
            const result = await API.get('/api/conceder-acesso/status');
            if (result.success) {
                if (result.existe) {
                    usuarioAtual = result.usuario;
                    mostrarEstadoComUsuario(result.usuario, result.criado_em);
                } else {
                    mostrarEstadoSemUsuario();
                }
            } else {
                toast.error(i18n.loadError);
            }
        } catch (error) {
            console.error('Erro:', error);
            toast.error(i18n.serverError);
        }
    }

    function mostrarLoading() {
        loading.classList.remove('hidden');
        estadoSemUsuario.classList.add('hidden');
        estadoComUsuario.classList.add('hidden');
    }

    function mostrarEstadoSemUsuario() {
        loading.classList.add('hidden');
        estadoSemUsuario.classList.remove('hidden');
        estadoComUsuario.classList.add('hidden');
    }

    function mostrarEstadoComUsuario(usuario, dataCreated) {
        loading.classList.add('hidden');
        estadoSemUsuario.classList.add('hidden');
        estadoComUsuario.classList.remove('hidden');
        usuarioNome.textContent = usuario;
        if (dataCreated) {
            const data = new Date(dataCreated);
            criadoEm.textContent = i18n.createdAt + ' ' + data.toLocaleString('pt-BR');
        }
    }

    btnCriar?.addEventListener('click', async function() {
        btnCriar.disabled = true;
        btnCriar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.creating;

        try {
            const result = await API.post('/conceder-acesso/criar');
            if (result.success) {
                toast.success(result.message);
                carregarStatus();
            } else {
                toast.error(result.message || i18n.createError);
            }
        } catch (error) {
            console.error('Erro:', error);
            toast.error(i18n.createError);
        } finally {
            btnCriar.disabled = false;
            btnCriar.innerHTML = '<i class="fas fa-user-plus mr-2"></i>' + i18n.createUser;
        }
    });

    btnCopiar?.addEventListener('click', function() {
        if (usuarioAtual) {
            navigator.clipboard.writeText(usuarioAtual).then(() => {
                toast.success(i18n.copied);
                btnCopiar.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    btnCopiar.innerHTML = '<i class="fas fa-copy"></i>';
                }, 2000);
            }).catch(() => {
                const input = document.createElement('input');
                input.value = usuarioAtual;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                toast.success(i18n.copiedShort);
            });
        }
    });

    btnExcluir?.addEventListener('click', async function() {
        const confirmado = await mostrarModalConfirmacao();
        if (!confirmado) {
            return;
        }

        btnExcluir.disabled = true;
        btnExcluir.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.deleting;

        try {
            const result = await API.post('/conceder-acesso/excluir');
            if (result.success) {
                toast.success(result.message);
                usuarioAtual = null;
                carregarStatus();
            } else {
                toast.error(result.message || i18n.deleteError);
            }
        } catch (error) {
            console.error('Erro:', error);
            toast.error(i18n.deleteError);
        } finally {
            btnExcluir.disabled = false;
            btnExcluir.innerHTML = '<i class="fas fa-trash mr-2"></i>' + i18n.deleteUser;
        }
    });

    carregarStatus();
})();
</script>
@endsection
