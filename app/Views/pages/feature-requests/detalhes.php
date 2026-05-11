@extends('layouts.iframe')

@section('title', '<?= t("modules.feature_requests.details_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-section"><?= t('modules.feature_requests.details_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Loading -->
    <div id="loadingContainer" class="flex justify-center items-center py-12">
        <i class="fas fa-spinner fa-spin text-2xl text-slate-400"></i>
    </div>

    <!-- Content -->
    <div id="contentContainer" class="hidden">
        <!-- Card Principal -->
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
            <!-- Header do Card -->
            <div class="p-6 border-b border-slate-200">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <span id="statusBadge" class="px-3 py-1 rounded-full text-sm font-medium"></span>
                            <span id="moduloBadge" class="ml-2 text-sm text-slate-500">
                                <i id="moduloIcone" class="mr-1"></i>
                                <span id="moduloNome"></span>
                            </span>
                        </div>
                        <h1 id="titulo" class="text-xl font-semibold text-slate-800 mb-2"></h1>
                        <div class="flex items-center text-sm text-slate-500">
                            <span id="dataCriacao"></span>
                            <span class="mx-2">|</span>
                            <span><?= t('modules.feature_requests.info.requested_by') ?> <span id="solicitante" class="font-medium"></span></span>
                        </div>
                    </div>

                    <!-- Contadores -->
                    <div class="flex items-center space-x-4 ml-4">
                        <div class="text-center">
                            <div id="totalVotos" class="text-2xl font-bold text-blue-600">0</div>
                            <div class="text-xs text-slate-500"><?= t('modules.feature_requests.info.votes_label') ?></div>
                        </div>
                        <div class="text-center">
                            <div id="totalSeguidores" class="text-2xl font-bold text-green-600">0</div>
                            <div class="text-xs text-slate-500"><?= t('modules.feature_requests.info.followers_label') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Descricao -->
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-sm font-semibold text-slate-700 mb-3"><?= t('modules.feature_requests.details.description') ?></h3>
                <div id="descricao" class="text-slate-600 whitespace-pre-wrap"></div>
            </div>

            <!-- Resposta Admin -->
            <div id="respostaContainer" class="hidden p-6 border-b border-slate-200 bg-blue-50">
                <h3 class="text-sm font-semibold text-blue-800 mb-3">
                    <i class="fas fa-comment-dots mr-2"></i><?= t('modules.feature_requests.details.admin_response') ?>
                </h3>
                <div id="respostaAdmin" class="text-blue-900 whitespace-pre-wrap"></div>
                <div id="respostaInfo" class="text-xs text-blue-600 mt-2"></div>
            </div>

            <!-- Acoes -->
            <div class="p-6 bg-slate-50">
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Botao Votar -->
                    <button id="btnVotar" class="btn-outline-blue py-2 px-4 rounded-md text-sm flex items-center">
                        <i class="far fa-thumbs-up mr-2"></i>
                        <span><?= t('modules.feature_requests.actions.vote') ?></span>
                    </button>

                    <!-- Botao Seguir -->
                    <button id="btnSeguir" class="btn-outline-green py-2 px-4 rounded-md text-sm flex items-center">
                        <i class="far fa-bell mr-2"></i>
                        <span><?= t('modules.feature_requests.actions.follow') ?></span>
                    </button>

                    <!-- Botao Editar (visivel apenas para quem pode editar) -->
                    <button id="btnEditar" class="hidden btn-outline-amber py-2 px-4 rounded-md text-sm flex items-center">
                        <i class="fas fa-edit mr-2"></i>
                        <span><?= t('common.buttons.edit') ?></span>
                    </button>

                    <!-- Info de status -->
                    <div id="votoInfo" class="hidden text-sm text-blue-600">
                        <i class="fas fa-check-circle mr-1"></i><?= t('modules.feature_requests.info.voted') ?>
                    </div>
                    <div id="seguindoInfo" class="hidden text-sm text-green-600">
                        <i class="fas fa-bell mr-1"></i><?= t('modules.feature_requests.info.following') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Painel Admin -->
        <div id="adminPanel" class="hidden mt-6 bg-amber-50 rounded-lg border border-amber-200 p-6">
            <h3 class="text-lg font-semibold text-amber-800 mb-4">
                <i class="fas fa-shield-alt mr-2"></i><?= t('modules.feature_requests.admin.panel_title') ?>
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <!-- Alterar Status -->
                <div>
                    <label class="form-label-group"><?= t('modules.feature_requests.admin.change_status') ?></label>
                    <select id="selectStatus" class="form-input-focus w-full">
                        <option value="pendente"><?= t('modules.feature_requests.status.pending') ?></option>
                        <option value="em_analise"><?= t('modules.feature_requests.status.in_review') ?></option>
                        <option value="em_desenvolvimento"><?= t('modules.feature_requests.status.in_development') ?></option>
                        <option value="concluido"><?= t('modules.feature_requests.status.completed') ?></option>
                        <option value="recusado"><?= t('modules.feature_requests.status.rejected') ?></option>
                        <option value="aguardando_info"><?= t('modules.feature_requests.status.awaiting_info_full') ?></option>
                    </select>
                </div>

                <!-- Prioridade -->
                <div>
                    <label class="form-label-group"><?= t('modules.feature_requests.admin.priority') ?></label>
                    <select id="selectPrioridade" class="form-input-focus w-full">
                        <option value="baixa"><?= t('modules.feature_requests.priorities.low') ?></option>
                        <option value="normal"><?= t('modules.feature_requests.priorities.normal') ?></option>
                        <option value="alta"><?= t('modules.feature_requests.priorities.high') ?></option>
                        <option value="critica"><?= t('modules.feature_requests.priorities.critical') ?></option>
                    </select>
                </div>
            </div>

            <!-- Resposta -->
            <div class="mb-4">
                <label class="form-label-group"><?= t('modules.feature_requests.admin.response') ?></label>
                <textarea id="txtResposta" class="form-input-focus w-full" rows="3"
                          placeholder="<?= t('modules.feature_requests.placeholders.admin_response') ?>"></textarea>
            </div>

            <!-- Notificar -->
            <div class="mb-4">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="chkNotificar" checked class="mr-2">
                    <span class="text-sm"><?= t('modules.feature_requests.admin.notify_followers') ?></span>
                </label>
            </div>

            <!-- Botao Salvar -->
            <div class="flex justify-end">
                <button id="btnSalvarAdmin" class="btn-blue py-2 px-6">
                    <i class="fas fa-save mr-2"></i><?= t('modules.feature_requests.actions.save_changes') ?>
                </button>
            </div>

            <!-- Lista de Seguidores -->
            <div class="mt-6 pt-4 border-t border-amber-200">
                <h4 class="text-sm font-semibold text-amber-800 mb-3">
                    <i class="fas fa-users mr-2"></i><?= t('modules.feature_requests.admin.followers_title') ?> (<span id="countSeguidores">0</span>)
                </h4>
                <div id="listaSeguidores" class="text-sm text-slate-600">
                    <p class="text-slate-400 italic"><?= t('modules.feature_requests.admin.no_followers') ?></p>
                </div>
            </div>
        </div>

        <!-- Informacoes Adicionais -->
        <div class="mt-6 bg-white rounded-lg border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4"><?= t('modules.feature_requests.details.additional_info') ?></h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-slate-500"><?= t('modules.feature_requests.details.id') ?></span>
                    <span id="pedidoId" class="font-mono ml-1"></span>
                </div>
                <div>
                    <span class="text-slate-500"><?= t('modules.feature_requests.details.priority') ?></span>
                    <span id="prioridade" class="ml-1"></span>
                </div>
                <div>
                    <span class="text-slate-500"><?= t('modules.feature_requests.details.updated') ?></span>
                    <span id="dataAtualizacao" class="ml-1"></span>
                </div>
                <div>
                    <span class="text-slate-500"><?= t('modules.feature_requests.details.email') ?></span>
                    <span id="emailSolicitante" class="ml-1"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Erro -->
    <div id="errorContainer" class="hidden">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
            <i class="fas fa-exclamation-circle text-4xl text-red-400 mb-3"></i>
            <p id="errorMessage" class="text-red-800"><?= t('modules.feature_requests.messages.not_found') ?></p>
            <button id="btnVoltarErro" class="mt-4 btn-secondary py-2 px-4">
                <i class="fas fa-arrow-left mr-2"></i><?= t('modules.feature_requests.messages.back_to_list') ?>
            </button>
        </div>
    </div>

    <!-- Modal Edicao -->
    <div id="modalEditar" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-800">
                    <i class="fas fa-edit mr-2 text-amber-500"></i><?= t('modules.feature_requests.edit_title') ?>
                </h3>
                <button type="button" id="btnFecharModal" class="text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="formEditar" class="p-6 space-y-4">
                <div>
                    <label class="form-label-group"><?= t('modules.feature_requests.edit.title_label') ?> <span class="text-red-500">*</span></label>
                    <input type="text" id="editTitulo" class="form-input-focus w-full" required>
                </div>
                <div>
                    <label class="form-label-group"><?= t('modules.feature_requests.edit.description_label') ?> <span class="text-red-500">*</span></label>
                    <textarea id="editDescricao" class="form-input-focus w-full" rows="6" required></textarea>
                </div>
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" id="btnCancelarEditar" class="btn-secondary py-2 px-6 rounded-md text-sm font-medium"><?= t('common.buttons.cancel') ?></button>
                    <button type="submit" id="btnSalvarEditar" class="btn-blue py-2 px-6 rounded-md text-sm font-medium flex items-center">
                        <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        notFound: '<?= addslashes(t('modules.feature_requests.messages.not_found')) ?>',
        idNotFound: '<?= addslashes(t('modules.feature_requests.messages.id_not_found')) ?>',
        loadError: '<?= addslashes(t('modules.feature_requests.messages.load_request_error')) ?>',
        notCategorized: '<?= addslashes(t('modules.feature_requests.info.not_categorized')) ?>',
        respondedAt: '<?= addslashes(t('modules.feature_requests.info.responded_at')) ?>',
        removeVote: '<?= addslashes(t('modules.feature_requests.actions.remove_vote')) ?>',
        vote: '<?= addslashes(t('modules.feature_requests.actions.vote')) ?>',
        unfollow: '<?= addslashes(t('modules.feature_requests.actions.unfollow')) ?>',
        follow: '<?= addslashes(t('modules.feature_requests.actions.follow')) ?>',
        voted: '<?= addslashes(t('modules.feature_requests.info.voted')) ?>',
        following: '<?= addslashes(t('modules.feature_requests.info.following')) ?>',
        voteAdded: '<?= addslashes(t('modules.feature_requests.messages.vote_added')) ?>',
        voteRemoved: '<?= addslashes(t('modules.feature_requests.messages.vote_removed')) ?>',
        voteError: '<?= addslashes(t('modules.feature_requests.messages.vote_error')) ?>',
        nowFollowing: '<?= addslashes(t('modules.feature_requests.messages.now_following')) ?>',
        unfollowed: '<?= addslashes(t('modules.feature_requests.messages.unfollowed')) ?>',
        processError: '<?= addslashes(t('modules.feature_requests.messages.process_error')) ?>',
        noFollowers: '<?= addslashes(t('modules.feature_requests.admin.no_followers')) ?>',
        notifyEmail: '<?= addslashes(t('modules.feature_requests.admin.notify_email')) ?>',
        notifyWhatsapp: '<?= addslashes(t('modules.feature_requests.admin.notify_whatsapp')) ?>',
        adminSaveSuccess: '<?= addslashes(t('modules.feature_requests.messages.admin_save_success')) ?>',
        adminSaveError: '<?= addslashes(t('modules.feature_requests.messages.admin_save_error')) ?>',
        adminSaveChangesError: '<?= addslashes(t('modules.feature_requests.messages.admin_save_changes_error')) ?>',
        saving: '<?= addslashes(t('modules.feature_requests.messages.saving')) ?>',
        saveChanges: '<?= addslashes(t('modules.feature_requests.actions.save_changes')) ?>',
        titleRequired: '<?= addslashes(t('modules.feature_requests.messages.title_required_edit')) ?>',
        descriptionRequired: '<?= addslashes(t('modules.feature_requests.messages.description_required_edit')) ?>',
        updateSuccess: '<?= addslashes(t('modules.feature_requests.messages.update_success')) ?>',
        updateError: '<?= addslashes(t('modules.feature_requests.messages.update_error')) ?>',
        updateRequestError: '<?= addslashes(t('modules.feature_requests.messages.update_request_error')) ?>',
        btnSave: '<?= addslashes(t('common.buttons.save')) ?>',
        priorityLow: '<?= addslashes(t('modules.feature_requests.priorities.low')) ?>',
        priorityNormal: '<?= addslashes(t('modules.feature_requests.priorities.normal')) ?>',
        priorityHigh: '<?= addslashes(t('modules.feature_requests.priorities.high')) ?>',
        priorityCritical: '<?= addslashes(t('modules.feature_requests.priorities.critical')) ?>',
    };

    // ===== VARIAVEIS =====
    let pedidoId = null;
    let pedido = null;
    let meusVotos = [];
    let meusPedidosSeguidos = [];
    let isAdmin = false;

    // ===== NAVEGACAO =====
    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: page }, '*');
        } else {
            window.location.href = page;
        }
    }

    // ===== OBTER ID DA URL =====
    function getIdFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return params.get('id');
    }

    // ===== CARREGAR DADOS =====
    async function carregarPedido() {
        pedidoId = getIdFromUrl();

        if (!pedidoId) {
            mostrarErro(i18n.idNotFound);
            return;
        }

        try {
            // Carregar pedido, votos do usuario e pedidos seguidos em paralelo
            const [resultPedido, resultVotos, resultSeguidos] = await Promise.all([
                API.get(`/api/feature-requests/${pedidoId}`),
                API.get('/api/feature-requests/meus-votos'),
                API.get('/api/feature-requests/meus-seguidos')
            ]);

            if (!resultPedido.success || !resultPedido.data) {
                mostrarErro(resultPedido.message || i18n.notFound);
                return;
            }

            pedido = resultPedido.data;
            meusVotos = resultVotos.success ? resultVotos.data : [];
            meusPedidosSeguidos = resultSeguidos.success ? resultSeguidos.data : [];
            isAdmin = resultPedido.is_admin || false;

            renderizarPedido();
            document.getElementById('loadingContainer').classList.add('hidden');
            document.getElementById('contentContainer').classList.remove('hidden');

        } catch (error) {
            console.error('Erro ao carregar pedido:', error);
            mostrarErro(i18n.loadError);
        }
    }

    // ===== RENDERIZAR =====
    function renderizarPedido() {
        // Titulo e descricao
        document.getElementById('titulo').textContent = pedido.titulo;
        document.getElementById('descricao').textContent = pedido.descricao;
        document.getElementById('pedidoId').textContent = '#' + pedido.id;

        // Status
        const statusBadge = document.getElementById('statusBadge');
        statusBadge.textContent = pedido.status_label || pedido.status;
        statusBadge.className = 'px-3 py-1 rounded-full text-sm font-medium ' + (pedido.status_cor || 'bg-gray-100 text-gray-800');

        // Modulo
        document.getElementById('moduloNome').textContent = pedido.modulo_nome || i18n.notCategorized;
        const moduloIcone = document.getElementById('moduloIcone');
        if (pedido.modulo_icone) {
            moduloIcone.className = pedido.modulo_icone + ' mr-1';
        } else {
            moduloIcone.className = 'fas fa-folder mr-1';
        }

        // Contadores
        document.getElementById('totalVotos').textContent = pedido.total_votos || 0;
        document.getElementById('totalSeguidores').textContent = pedido.total_seguidores || 0;

        // Datas
        document.getElementById('dataCriacao').textContent = formatarData(pedido.created_at);
        document.getElementById('dataAtualizacao').textContent = pedido.updated_at ? formatarData(pedido.updated_at) : '-';

        // Solicitante
        document.getElementById('solicitante').textContent = pedido.nome_solicitante || pedido.email_solicitante;
        document.getElementById('emailSolicitante').textContent = pedido.email_solicitante;

        // Prioridade
        const prioridadeLabels = {
            'baixa': i18n.priorityLow,
            'normal': i18n.priorityNormal,
            'alta': i18n.priorityHigh,
            'critica': i18n.priorityCritical
        };
        document.getElementById('prioridade').textContent = prioridadeLabels[pedido.prioridade] || pedido.prioridade;

        // Resposta admin
        if (pedido.resposta_admin) {
            document.getElementById('respostaAdmin').textContent = pedido.resposta_admin;
            const respostaInfo = [];
            if (pedido.respondido_em) {
                respostaInfo.push(i18n.respondedAt + ' ' + formatarData(pedido.respondido_em));
            }
            document.getElementById('respostaInfo').textContent = respostaInfo.join(' | ');
            document.getElementById('respostaContainer').classList.remove('hidden');
        }

        // Botoes de acao
        atualizarBotoesAcao();

        // Painel admin
        if (isAdmin) {
            renderizarPainelAdmin();
        }
    }

    function atualizarBotoesAcao() {
        const votou = meusVotos.includes(parseInt(pedidoId));
        const segue = meusPedidosSeguidos.includes(parseInt(pedidoId));

        // Botao votar
        const btnVotar = document.getElementById('btnVotar');
        const votoInfo = document.getElementById('votoInfo');

        if (votou) {
            btnVotar.innerHTML = `<i class="fas fa-thumbs-up mr-2"></i>${i18n.removeVote}`;
            btnVotar.classList.add('bg-blue-100', 'text-blue-700', 'border-blue-300');
            votoInfo.classList.remove('hidden');
        } else {
            btnVotar.innerHTML = `<i class="far fa-thumbs-up mr-2"></i>${i18n.vote}`;
            btnVotar.classList.remove('bg-blue-100', 'text-blue-700', 'border-blue-300');
            votoInfo.classList.add('hidden');
        }

        // Botao seguir
        const btnSeguir = document.getElementById('btnSeguir');
        const seguindoInfo = document.getElementById('seguindoInfo');

        if (segue) {
            btnSeguir.innerHTML = `<i class="fas fa-bell-slash mr-2"></i>${i18n.unfollow}`;
            btnSeguir.classList.add('bg-green-100', 'text-green-700', 'border-green-300');
            seguindoInfo.classList.remove('hidden');
        } else {
            btnSeguir.innerHTML = `<i class="far fa-bell mr-2"></i>${i18n.follow}`;
            btnSeguir.classList.remove('bg-green-100', 'text-green-700', 'border-green-300');
            seguindoInfo.classList.add('hidden');
        }

        // Botao editar (visivel apenas para quem pode editar)
        const btnEditar = document.getElementById('btnEditar');
        if (pedido.pode_editar) {
            btnEditar.classList.remove('hidden');
        } else {
            btnEditar.classList.add('hidden');
        }
    }

    // ===== MODAL EDICAO =====
    function abrirModalEditar() {
        document.getElementById('editTitulo').value = pedido.titulo || '';
        document.getElementById('editDescricao').value = pedido.descricao || '';
        document.getElementById('modalEditar').classList.remove('hidden');
    }

    function fecharModalEditar() {
        document.getElementById('modalEditar').classList.add('hidden');
    }

    async function salvarEdicao(e) {
        e.preventDefault();

        const titulo = document.getElementById('editTitulo').value.trim();
        const descricao = document.getElementById('editDescricao').value.trim();

        if (!titulo) {
            showToast(i18n.titleRequired, 'error');
            return;
        }

        if (!descricao) {
            showToast(i18n.descriptionRequired, 'error');
            return;
        }

        const btnSalvar = document.getElementById('btnSalvarEditar');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

        try {
            const result = await API.post(`/feature-requests/${pedidoId}/atualizar`, {
                titulo: titulo,
                descricao: descricao
            });

            if (result.success) {
                showToast(i18n.updateSuccess, 'success');
                fecharModalEditar();

                // Atualizar dados locais e re-renderizar
                pedido.titulo = titulo;
                pedido.descricao = descricao;
                document.getElementById('titulo').textContent = titulo;
                document.getElementById('descricao').textContent = descricao;
            } else {
                showToast(result.message || i18n.updateError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast(i18n.updateRequestError, 'error');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.btnSave}`;
        }
    }

    function renderizarPainelAdmin() {
        document.getElementById('adminPanel').classList.remove('hidden');

        // Preencher valores atuais
        document.getElementById('selectStatus').value = pedido.status;
        document.getElementById('selectPrioridade').value = pedido.prioridade;
        document.getElementById('txtResposta').value = pedido.resposta_admin || '';

        // Carregar seguidores
        carregarSeguidores();
    }

    async function carregarSeguidores() {
        try {
            const result = await API.get(`/api/feature-requests/${pedidoId}/seguidores`);
            if (result.success && result.data) {
                const lista = document.getElementById('listaSeguidores');
                const count = document.getElementById('countSeguidores');

                count.textContent = result.data.length;

                if (result.data.length === 0) {
                    lista.innerHTML = `<p class="text-slate-400 italic">${i18n.noFollowers}</p>`;
                } else {
                    lista.innerHTML = result.data.map(s => `
                        <div class="flex items-center justify-between py-2 border-b border-amber-100 last:border-0">
                            <div>
                                <span class="font-medium">${escapeHtml(s.email)}</span>
                                ${s.telefone ? `<span class="text-slate-400 ml-2">${escapeHtml(s.telefone)}</span>` : ''}
                            </div>
                            <div class="text-xs text-slate-400">
                                ${s.notificar_email ? `<i class="fas fa-envelope mr-1" title="${i18n.notifyEmail}"></i>` : ''}
                                ${s.notificar_whatsapp && s.telefone ? `<i class="fab fa-whatsapp text-green-500" title="${i18n.notifyWhatsapp}"></i>` : ''}
                            </div>
                        </div>
                    `).join('');
                }
            }
        } catch (error) {
            console.error('Erro ao carregar seguidores:', error);
        }
    }

    // ===== ACOES =====
    async function toggleVoto() {
        const votou = meusVotos.includes(parseInt(pedidoId));

        try {
            let result;
            if (votou) {
                result = await API.delete(`/feature-requests/${pedidoId}/voto`);
                if (result.success) {
                    meusVotos = meusVotos.filter(id => id !== parseInt(pedidoId));
                    pedido.total_votos = Math.max(0, (pedido.total_votos || 0) - 1);
                    showToast(i18n.voteRemoved, 'success');
                }
            } else {
                result = await API.post(`/feature-requests/${pedidoId}/votar`);
                if (result.success) {
                    meusVotos.push(parseInt(pedidoId));
                    pedido.total_votos = (pedido.total_votos || 0) + 1;
                    showToast(i18n.voteAdded, 'success');
                }
            }

            if (result.success) {
                document.getElementById('totalVotos').textContent = pedido.total_votos;
                atualizarBotoesAcao();
            } else {
                showToast(result.message || i18n.voteError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast('Erro ao processar voto', 'error');
        }
    }

    async function toggleSeguir() {
        const segue = meusPedidosSeguidos.includes(parseInt(pedidoId));

        try {
            let result;
            if (segue) {
                result = await API.delete(`/feature-requests/${pedidoId}/seguir`);
                if (result.success) {
                    meusPedidosSeguidos = meusPedidosSeguidos.filter(id => id !== parseInt(pedidoId));
                    pedido.total_seguidores = Math.max(0, (pedido.total_seguidores || 0) - 1);
                    showToast(i18n.unfollowed, 'success');
                }
            } else {
                result = await API.post(`/feature-requests/${pedidoId}/seguir`);
                if (result.success) {
                    meusPedidosSeguidos.push(parseInt(pedidoId));
                    pedido.total_seguidores = (pedido.total_seguidores || 0) + 1;
                    showToast(i18n.nowFollowing, 'success');
                }
            }

            if (result.success) {
                document.getElementById('totalSeguidores').textContent = pedido.total_seguidores;
                atualizarBotoesAcao();
                if (isAdmin) carregarSeguidores();
            } else {
                showToast(result.message || i18n.processError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast(i18n.processError, 'error');
        }
    }

    async function salvarAdmin() {
        const status = document.getElementById('selectStatus').value;
        const prioridade = document.getElementById('selectPrioridade').value;
        const resposta = document.getElementById('txtResposta').value.trim();
        const notificar = document.getElementById('chkNotificar').checked;

        const btnSalvar = document.getElementById('btnSalvarAdmin');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

        try {
            const result = await API.put(`/feature-requests/${pedidoId}/status`, {
                status: status,
                prioridade: prioridade,
                resposta_admin: resposta,
                notificar: notificar ? 1 : 0
            });

            if (result.success) {
                showToast(i18n.adminSaveSuccess, 'success');

                // Atualizar dados locais
                pedido.status = status;
                pedido.prioridade = prioridade;
                pedido.resposta_admin = resposta;

                // Re-renderizar
                renderizarPedido();
            } else {
                showToast(result.message || i18n.adminSaveError, 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showToast(i18n.adminSaveChangesError, 'error');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.saveChanges}`;
        }
    }

    // ===== ERRO =====
    function mostrarErro(mensagem) {
        document.getElementById('loadingContainer').classList.add('hidden');
        document.getElementById('contentContainer').classList.add('hidden');
        document.getElementById('errorMessage').textContent = mensagem;
        document.getElementById('errorContainer').classList.remove('hidden');
    }

    // ===== HELPERS =====
    function formatarData(dataStr) {
        if (!dataStr) return '-';
        const data = new Date(dataStr);
        return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        if (type === 'error') {
            window.parent.postMessage({ action: 'openAlert', message: message }, '*');
        } else {
            window.parent.postMessage({ action: 'showToast', message: message }, '*');
        }
    }

    // ===== EVENT LISTENERS =====
    document.getElementById('btnVoltar')?.addEventListener('click', function() {
        navegarPara('/pages/feature-requests');
    });

    document.getElementById('btnVoltarErro')?.addEventListener('click', function() {
        navegarPara('/pages/feature-requests');
    });

    document.getElementById('btnVotar')?.addEventListener('click', toggleVoto);
    document.getElementById('btnSeguir')?.addEventListener('click', toggleSeguir);
    document.getElementById('btnSalvarAdmin')?.addEventListener('click', salvarAdmin);

    // Event listeners do modal de edicao
    document.getElementById('btnEditar')?.addEventListener('click', abrirModalEditar);
    document.getElementById('btnFecharModal')?.addEventListener('click', fecharModalEditar);
    document.getElementById('btnCancelarEditar')?.addEventListener('click', fecharModalEditar);
    document.getElementById('formEditar')?.addEventListener('submit', salvarEdicao);

    // Fechar modal ao clicar fora
    document.getElementById('modalEditar')?.addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalEditar();
        }
    });

    // ===== INICIALIZACAO =====
    carregarPedido();
})();
</script>
@endsection
