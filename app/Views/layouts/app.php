<!DOCTYPE html>
@php
    $htmlLocale = locale_info()['code'] ?? 'pt-BR';
    $systemVersion = \App\Models\Changelog::getUltimaVersao();
@endphp
<html lang="<?= htmlspecialchars($htmlLocale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?= \App\Views\Template::yieldSection('title', t('modules.layout.app_title')); ?></title>
    @csrfMeta

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= asset('css/base.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/layout.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/components.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/utilities.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/custom-classes.min.css'); ?>" rel="stylesheet">
    <link href="<?= asset('css/chosen-select.min.css'); ?>" rel="stylesheet">
</head>
<body class="flex flex-col md:h-screen" style="background-color: #f5f7fa;">

    @php
        use App\Core\Auth;
        $user = Auth::user();
        $empresa = Auth::empresa();
        $role = $user ? Auth::getRole() : null;
        $footerFuncao = trim((string)($role['name'] ?? ''));
        if ($footerFuncao === '') {
            $footerFuncao = 'N/A';
        }
    @endphp

    @include('partials.navbar', ['user' => $user, 'notifications' => $notifications ?? []])

    <!-- Container principal -->
    <div id="mainLayoutContainer" class="flex-1 flex flex-col md:flex-row md:overflow-hidden">
        @include('partials.sidebar')

        <!-- Área de conteúdo principal -->
        <main id="mainContentArea" class="content-area flex-grow py-4 px-0 overflow-y-auto">
            <!-- Mensagens flash -->
            @if($success)
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle"></i>
                    {{ $success }}
                </div>
            @endif

            @if($error)
                <div class="alert alert-error mb-4">
                    <i class="fas fa-exclamation-circle"></i>
                    {{ $error }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Footer -->
    <footer class="footer-bar p-2 text-center md:flex md:justify-between md:items-center">
        <div class="flex flex-wrap justify-center md:justify-start gap-x-2 text-xs">
            @if($user)
                <span>[ <?= t('modules.layout.footer.user') ?>: {{ $user['nome'] }}@if(!empty($user['usuario'])) ({{ $user['usuario'] }})@endif ]</span>
                <span>-</span>
                <span>[ <?= t('modules.layout.footer.job_function') ?>: {{ $footerFuncao }} ]</span>
                @if($empresa)
                    <span>-</span>
                    <span>[ <?= t('modules.layout.footer.company') ?>: {{ $empresa['nome_fantasia'] ?? 'N/A' }} ]</span>
                @endif
                <span>-</span>
                <span>[ <?= t('modules.layout.footer.plan') ?>: {{ plano_nome($user['plano']) }} ]</span>
                <span>-</span>
                <span>[ <?= t('common.labels.language') ?>: <?= current_locale() ?> ]</span>
                <span>-</span>
                <span>[ <a href="#" onclick="openOrSwitchToTab('/pages/gravacoes', '<?= htmlspecialchars(t('modules.layout.footer.screen_recordings'), ENT_QUOTES, 'UTF-8') ?>', 'fas fa-video'); return false;" class="hover:underline"><i class="fas fa-video"></i> <?= t('modules.layout.footer.record_screen') ?></a> ]</span>
            @endif
        </div>
        <div class="mt-1 md:mt-0 text-xs">
            <span><?= t('modules.layout.footer.version') ?>: {{ $systemVersion }} - <a href="https://wa.me/5527998927997/?text=Suporte+7Carros+Locadora" target="_blank"><?= t('modules.layout.footer.support') ?></a></span>
        </div>
    </footer>

    <!-- Modal de Confirmação de Exclusão -->
    <div id="deleteConfirmationModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 500px;">
            <h3 class="modal-title" id="deleteModalTitle"><?= t('modules.layout.delete.title') ?></h3>
            <p class="modal-message" id="deleteModalMessage"><?= t('modules.layout.delete.default_message') ?></p>

            <!-- Campo de Confirmação -->
            <div class="mt-4 mb-4" id="confirmDeleteSection" style="display: none;">
                <label for="confirmDeleteInput" class="form-label">
                    <?= t('modules.layout.delete.confirm_label') ?> <strong id="confirmDeleteText" style="user-select: all; cursor: pointer;" title="<?= t('modules.layout.delete.select_title') ?>"><?= t('modules.layout.delete.confirm_text') ?></strong>:
                </label>
                <input
                    type="text"
                    id="confirmDeleteInput"
                    class="form-input-focus"
                    placeholder="<?= t('modules.layout.delete.placeholder', ['text' => t('modules.layout.delete.confirm_text')]) ?>"
                    autocomplete="off"
                >
            </div>

            <div class="modal-actions">
                <button id="cancelDeleteButton" onclick="closeGlobalDeleteModal()" class="btn-secondary">
                    <?= t('modules.layout.buttons.cancel') ?>
                </button>
                <button
                    id="confirmDeleteButton"
                    onclick="confirmGlobalDelete()"
                    class="btn-red py-2 px-4 rounded-md text-sm font-medium"
                >
                    <?= t('modules.layout.delete.button') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação Genérico -->
    <div id="genericConfirmModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 500px;">
            <h3 class="modal-title" id="genericModalTitle"><?= t('modules.layout.generic.confirm_title') ?></h3>
            <p class="modal-message" id="genericModalMessage"><?= t('modules.layout.generic.confirm_message') ?></p>
            <div class="modal-actions">
                <button id="cancelGenericButton" onclick="closeGenericConfirmModal()" class="btn-secondary">
                    <?= t('modules.layout.buttons.cancel') ?>
                </button>
                <button
                    id="confirmGenericButton"
                    onclick="confirmGenericAction()"
                    class="btn-blue py-2 px-4 rounded-md text-sm font-medium"
                >
                    <?= t('modules.layout.generic.confirm_title') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Validação de Formulário -->
    <div id="validationModal" class="modal-overlay">
        <div class="modal-box validation-modal-box">
            <div class="validation-modal-header">
                <i class="fas fa-exclamation-triangle validation-modal-icon"></i>
                <h3 class="modal-title"><?= t('modules.layout.validation.title') ?></h3>
            </div>
            <p class="modal-message"><?= t('modules.layout.validation.message') ?></p>
            <div class="validation-errors-container" id="validationErrorsList">
                <!-- Erros serão inseridos aqui via JS -->
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-blue py-2 px-6 rounded-md text-sm font-medium" id="validationModalCloseBtn">
                    <?= t('modules.layout.buttons.understood') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Alerta Global -->
    <div id="alertModal" class="modal-overlay">
        <div class="modal-box">
            <h3 class="modal-title" id="alertModalTitle"><?= t('modules.layout.alert.title') ?></h3>
            <p class="modal-message" id="alertModalMessage"></p>
            <div class="modal-actions" style="justify-content: center;">
                <button type="button" class="btn-blue py-2 px-6 rounded-md text-sm font-medium" id="alertModalOkBtn">OK</button>
            </div>
        </div>
    </div>

    <!-- Modal de Regularização de Autorenovação -->
    <div id="contratoRenovacaoSyncModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 760px; text-align: left;">
            <h3 class="modal-title">
                <i class="fas fa-sync-alt mr-2" style="color: #7c3aed;"></i><?= t('modules.layout.renewal.title') ?>
            </h3>
            <div id="contratoRenovacaoSyncLoading" class="py-6 text-center text-slate-500">
                <i class="fas fa-spinner fa-spin mr-2"></i><?= t('modules.layout.renewal.loading') ?>
            </div>
            <div id="contratoRenovacaoSyncContent" style="display: none;">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                    <div>
                        <div class="text-xs text-slate-500"><?= t('modules.layout.renewal.contract') ?></div>
                        <div class="font-semibold text-slate-800" id="renovacaoSyncContrato">-</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500"><?= t('modules.layout.renewal.client') ?></div>
                        <div class="font-semibold text-slate-800" id="renovacaoSyncCliente">-</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500"><?= t('modules.layout.renewal.expired') ?></div>
                        <div class="font-semibold text-red-700" id="renovacaoSyncVencida">-</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500"><?= t('modules.layout.renewal.next') ?></div>
                        <div class="font-semibold text-purple-700" id="renovacaoSyncProxima">-</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500"><?= t('modules.layout.renewal.cycles') ?></div>
                        <div class="font-semibold text-slate-800" id="renovacaoSyncCiclos">-</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500"><?= t('modules.layout.renewal.new_period') ?></div>
                        <div class="font-semibold text-slate-800" id="renovacaoSyncPeriodo">-</div>
                    </div>
                </div>

                <div class="mb-4 p-3 rounded-md" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div class="text-xs text-slate-500 mb-1"><?= t('modules.layout.renewal.installment_command') ?></div>
                    <div class="text-sm font-medium text-slate-700" id="renovacaoSyncComando">-</div>
                </div>

                <label class="flex items-center gap-2 mb-3 text-sm text-slate-700">
                    <input type="checkbox" id="renovacaoSyncGerarFinanceiro" class="w-4 h-4 text-purple-600 border-slate-300 rounded" checked>
                    <span><?= t('modules.layout.renewal.generate_financial') ?></span>
                </label>

                <div id="renovacaoSyncParcelasBox" class="mb-4">
                    <div class="text-sm font-semibold text-slate-700 mb-2"><?= t('modules.layout.renewal.installments_preview') ?></div>
                    <div class="overflow-x-auto rounded-md border border-slate-200">
                        <table class="w-full text-sm">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th class="text-left py-2 px-3">#</th>
                                    <th class="text-left py-2 px-3"><?= t('modules.layout.renewal.due_date') ?></th>
                                    <th class="text-right py-2 px-3"><?= t('modules.layout.renewal.value') ?></th>
                                </tr>
                            </thead>
                            <tbody id="renovacaoSyncParcelas"></tbody>
                        </table>
                    </div>
                </div>

                <div id="renovacaoSyncCanaisBox" class="mb-4">
                    <div class="text-sm font-semibold text-slate-700 mb-2"><?= t('modules.layout.renewal.billing_sending') ?></div>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" id="renovacaoSyncEmail" class="w-4 h-4 text-blue-600 border-slate-300 rounded">
                            <span>E-mail</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" id="renovacaoSyncWhatsapp" class="w-4 h-4 text-green-600 border-slate-300 rounded">
                            <span>WhatsApp</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" id="renovacaoSyncSms" class="w-4 h-4 text-purple-600 border-slate-300 rounded">
                            <span>SMS</span>
                        </label>
                    </div>
                </div>

                <p id="renovacaoSyncAviso" class="text-sm text-amber-700 mb-4" style="display: none;"></p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeContratoRenovacaoSyncModal()"><?= t('modules.layout.buttons.cancel') ?></button>
                <button type="button" id="renovacaoSyncConfirmBtn" class="py-2 px-4 rounded-md text-sm font-medium text-white" style="background: #7c3aed;" onclick="confirmContratoRenovacaoSync()">
                    <?= t('modules.layout.buttons.regularize') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Link de Pagamento -->
    <div id="linkModal" class="modal-overlay">
        <div class="modal-box">
            <h3 class="modal-title"><i class="fas fa-link mr-2"></i><?= t('modules.layout.links.payment_title') ?></h3>
            <div class="mb-4">
                <input type="text" id="linkModalUrl" class="form-input-focus w-full text-sm" readonly onclick="this.select()">
            </div>
            <div class="modal-actions flex gap-2 justify-center">
                <button type="button" class="btn-blue py-2 px-4 rounded-md text-sm font-medium" id="linkModalCopyBtn">
                    <i class="fas fa-copy mr-1"></i><?= t('modules.layout.buttons.copy') ?>
                </button>
                <button type="button" class="btn-green py-2 px-4 rounded-md text-sm font-medium" id="linkModalOpenBtn">
                    <i class="fas fa-external-link-alt mr-1"></i><?= t('modules.layout.buttons.open') ?>
                </button>
                <button type="button" class="btn-slate py-2 px-4 rounded-md text-sm font-medium" id="linkModalCloseBtn">
                    <?= t('modules.layout.buttons.close') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Link de Assinatura -->
    <div id="signatureLinkModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 42rem;">
            <h3 class="modal-title"><i class="fas fa-signature mr-2"></i><?= t('modules.layout.links.signature_title') ?></h3>
            <p class="text-sm text-slate-600 mt-3 mb-5"><?= t('modules.layout.links.signature_help') ?></p>
            <div class="mb-4 text-left">
                <p class="text-sm text-slate-600 mb-3">
                    <span id="signatureLinkModalTipo"><?= t('modules.layout.links.document') ?></span>: <strong id="signatureLinkModalCodigo">-</strong>
                </p>
                <input type="text" id="signatureLinkModalUrl" class="form-input-focus w-full text-sm" readonly onclick="this.select()">
            </div>
            <div class="modal-actions flex gap-2 justify-center">
                <button type="button" class="btn-green py-2 px-4 rounded-md text-sm font-medium" id="signatureLinkModalWhatsappBtn">
                    <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                </button>
                <button type="button" class="btn-blue py-2 px-4 rounded-md text-sm font-medium" id="signatureLinkModalCopyBtn">
                    <i class="fas fa-copy mr-1"></i><?= t('modules.layout.buttons.copy') ?>
                </button>
                <button type="button" class="btn-green py-2 px-4 rounded-md text-sm font-medium" id="signatureLinkModalOpenBtn">
                    <i class="fas fa-external-link-alt mr-1"></i><?= t('modules.layout.buttons.open') ?>
                </button>
                <button type="button" class="btn-slate py-2 px-4 rounded-md text-sm font-medium" id="signatureLinkModalCloseBtn">
                    <?= t('modules.layout.buttons.close') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Sessão Expirada -->
    <div id="sessionExpiredModal" class="modal-overlay">
        <div class="modal-box">
            <h3 class="modal-title">
                <i class="fas fa-clock"></i> <?= t('modules.layout.session.title') ?>
            </h3>
            <p class="modal-message">
                <?= t('modules.layout.session.message') ?>
            </p>
            <div class="modal-actions" style="justify-content: center;">
                <button type="button" class="btn-blue py-2 px-6 rounded-md text-sm font-medium" id="sessionExpiredReloadBtn">
                    <?= t('modules.layout.buttons.reload_page') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Integração (Website > Integrações) -->
    <div id="integracaoModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 640px;">
            <h3 class="modal-title" id="integracaoModalTitle"><?= t('modules.layout.integration.add_title') ?></h3>

            <div class="text-left mt-4 space-y-4">
                <input type="hidden" id="integracaoModalId" value="">

                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.layout.integration.description') ?></label>
                    <input type="text" id="integracaoModalDescricao" class="form-input-group-field" placeholder="Ex: Google Tag Manager">
                </div>

                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.layout.integration.type') ?></label>
                    <select id="integracaoModalTipo" class="form-input-group-field">
                        <option value="head"><?= htmlspecialchars(t('modules.layout.integration.head'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="body_inicio"><?= htmlspecialchars(t('modules.layout.integration.after_body'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="body_fim"><?= htmlspecialchars(t('modules.layout.integration.before_body_end'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>

                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.layout.integration.code') ?></label>
                    <textarea id="integracaoModalCodigo" rows="8" class="form-input-group-field font-mono text-sm" placeholder="<?= htmlspecialchars(t('modules.layout.integration.code_placeholder'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
                </div>

                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                    <span class="text-sm"><?= t('modules.layout.integration.active') ?></span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="integracaoModalAtivo" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>

            <div class="modal-actions mt-6">
                <button type="button" id="cancelIntegracaoButton" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('modules.layout.buttons.cancel') ?>
                </button>
                <button type="button" id="confirmIntegracaoButton" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-save mr-1"></i> <?= t('modules.layout.buttons.save') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Edição em Lote -->
    <div id="editBatchModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 450px;">
            <h3 class="modal-title" id="editBatchModalTitle"><?= t('modules.layout.batch.title') ?></h3>

            <div id="editBatchModalContent" class="text-left mt-4">
                <!-- Conteúdo será injetado via postMessage -->
            </div>

            <div class="modal-actions mt-6">
                <button type="button" id="cancelEditBatchButton" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('modules.layout.buttons.cancel') ?>
                </button>
                <button type="button" id="confirmEditBatchButton" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    <?= t('modules.layout.buttons.update') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Input Global -->
    <div id="inputModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 500px;">
            <h3 class="modal-title" id="inputModalTitle"><?= t('modules.layout.generic.edit') ?></h3>
            <div class="mt-4">
                <label id="inputModalLabel" class="form-label"><?= t('modules.layout.generic.name') ?></label>
                <input type="text" id="inputModalInput" class="form-input-focus w-full" maxlength="100">
            </div>
            <div class="modal-actions mt-6">
                <button id="cancelInputButton" class="btn-secondary"><?= t('modules.layout.buttons.cancel') ?></button>
                <button id="confirmInputButton" class="btn-blue py-2 px-4 rounded-md text-sm font-medium" disabled><?= t('modules.layout.buttons.save') ?></button>
            </div>
        </div>
    </div>

    <!-- Modal de Visualizacao de Assinatura -->
    <div id="assinaturaModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 28rem; text-align: left;">
            <h3 class="modal-title"><i class="fas fa-signature mr-2"></i><?= t('modules.layout.signature.title') ?> <span id="assinaturaModalTipoPreposicao"><?= t('modules.layout.signature.of_rental') ?></span> <span id="assinaturaModalTipoTitulo"><?= t('modules.layout.signature.rental') ?></span></h3>
            <div class="modal-message" style="margin-bottom: 1rem;">
                <p class="text-sm text-slate-600 mb-2"><span id="assinaturaModalTipoLabel"><?= t('modules.layout.signature.rental') ?></span>: <strong id="assinaturaModalCodigo">-</strong></p>
                <p class="text-sm text-slate-600 mb-2"><?= t('modules.layout.signature.signed_at') ?> <strong id="assinaturaModalData">-</strong></p>
                <p class="text-sm text-slate-600 mb-4">IP: <strong id="assinaturaModalIP">-</strong></p>
                <div class="border rounded-lg p-4 bg-slate-50 text-center">
                    <img id="assinaturaModalImagem" src="" alt="<?= t('modules.layout.signature.alt') ?>" class="max-w-full h-auto mx-auto" style="max-height: 150px;">
                </div>
                <input type="hidden" id="assinaturaModalContratoId">
                <input type="hidden" id="assinaturaModalLocacaoId">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary py-2 px-4 rounded-md" onclick="closeAssinaturaModal()"><?= t('modules.layout.buttons.close') ?></button>
                <button type="button" class="btn-red py-2 px-4 rounded-md" onclick="resetarAssinaturaModal()">
                    <i class="fas fa-redo mr-2"></i><?= t('modules.layout.buttons.reset') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Video -->
    <div id="videoModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 800px;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="modal-title"><?= t('modules.layout.video.title') ?></h3>
                <button type="button" id="closeVideoModalBtn" class="text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="videoContainer" class="bg-black rounded-lg overflow-hidden">
                <video id="videoPlayer" controls class="w-full" style="max-height: 500px;"></video>
            </div>
            <div class="modal-actions mt-4">
                <button type="button" id="closeVideoModalBtnBottom" class="btn-secondary">
                    <?= t('modules.layout.buttons.close') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Changelog (Adicionar/Editar) -->
    <div id="changelogModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 500px;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="modal-title" id="changelogModalTitle"><?= t('modules.layout.changelog.new_title') ?></h3>
                <button type="button" id="closeChangelogModalBtn" class="text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="changelogModalForm" class="space-y-4 text-left">
                <input type="hidden" id="changelogModalId" value="">

                <div>
                    <label for="changelogModalVersao" class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.layout.changelog.version') ?></label>
                    <input type="text" id="changelogModalVersao" class="form-input-focus w-full" placeholder="Ex: 8.4.0" maxlength="20" required>
                </div>

                <div>
                    <label for="changelogModalTipo" class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.layout.changelog.type') ?></label>
                    <select id="changelogModalTipo" class="form-input-focus w-full" required>
                        <option value=""><?= t('modules.layout.changelog.select') ?></option>
                        <option value="N"><?= t('modules.layout.changelog.new') ?></option>
                        <option value="A"><?= t('modules.layout.changelog.improved') ?></option>
                        <option value="C"><?= t('modules.layout.changelog.fix') ?></option>
                    </select>
                </div>

                <div>
                    <label for="changelogModalData" class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.layout.changelog.date') ?></label>
                    <input type="date" id="changelogModalData" class="form-input-focus w-full" required>
                </div>

                <div>
                    <label for="changelogModalMensagem" class="block text-sm font-medium text-slate-700 mb-1"><?= t('modules.layout.changelog.message') ?></label>
                    <textarea id="changelogModalMensagem" rows="3" class="form-input-focus w-full" placeholder="<?= t('modules.layout.changelog.message_placeholder') ?>" maxlength="255" required></textarea>
                    <p class="text-xs text-slate-400 mt-1"><span id="changelogModalContador">0</span>/255</p>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" id="cancelChangelogModalBtn" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                        <?= t('modules.layout.buttons.cancel') ?>
                    </button>
                    <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-save mr-2"></i><?= t('modules.layout.buttons.save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== MODAIS CENTRAL DE MULTAS ===== -->

    <!-- Modal Consulta Individual de Multas -->
    <div id="consultaMultasModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 450px; text-align: left;">
            <h3 class="modal-title"><i class="fas fa-satellite-dish mr-2"></i><?= t('modules.layout.fines.consult_title') ?></h3>
            <div class="modal-message" style="margin-bottom: 0;">
                <div id="consultaFormContainer">
                    <div class="mb-4">
                        <label class="block text-sm text-slate-600 mb-1"><?= t('modules.layout.fines.plate') ?></label>
                        <input type="text" id="consultaPlacaInput" class="form-input-focus w-full" placeholder="ABC1D23" maxlength="7">
                    </div>
                    <button onclick="executarConsultaMultas()" class="btn-blue w-full py-2.5 rounded text-sm font-medium">
                        <i class="fas fa-search mr-2"></i> <?= t('modules.layout.fines.consult') ?>
                    </button>
                </div>
                <div id="consultaLoadingContainer" class="hidden text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i>
                    <p class="text-sm text-slate-500 mt-2"><?= t('modules.layout.fines.consulting') ?></p>
                </div>
                <div id="consultaResultContainer" class="hidden text-center py-4">
                    <i class="fas fa-check-circle text-3xl text-green-500"></i>
                    <p class="text-sm font-medium text-green-700 mt-2" id="consultaResultMsg"></p>
                    <button onclick="resetConsultaMultasModal()" class="text-blue-600 hover:text-blue-800 text-sm mt-2">
                        <i class="fas fa-redo mr-1"></i> <?= t('modules.layout.buttons.new_search') ?>
                    </button>
                </div>
            </div>
            <div class="modal-actions mt-4" id="consultaActionsContainer">
                <button class="btn-secondary" onclick="closeConsultaMultasModal()"><?= t('modules.layout.buttons.close') ?></button>
            </div>
        </div>
    </div>

    <!-- Modal Consulta em Lote -->
    <div id="consultaLoteModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 450px; text-align: left;">
            <h3 class="modal-title"><i class="fas fa-layer-group mr-2"></i><?= t('modules.layout.fines.batch_title') ?></h3>
            <div class="modal-message" style="margin-bottom: 0;">
                <div id="loteInfoContainer">
                    <p class="text-sm text-slate-600 mb-3">
                        <?= t('modules.layout.fines.batch_text') ?>
                    </p>
                    <div class="bg-amber-50 border border-amber-200 rounded p-3 mb-4">
                        <p class="text-xs text-amber-700">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <?= t('modules.layout.fines.current_balance') ?> <strong id="loteModalSaldo">-</strong>
                        </p>
                    </div>
                    <button onclick="executarConsultaLote()" class="btn-blue w-full py-2.5 rounded text-sm font-medium">
                        <i class="fas fa-layer-group mr-2"></i> <?= t('modules.layout.fines.start_batch') ?>
                    </button>
                </div>
                <div id="loteLoadingContainer" class="hidden text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-purple-500"></i>
                    <p class="text-sm text-slate-500 mt-2"><?= t('modules.layout.fines.consulting_vehicles') ?></p>
                    <p class="text-xs text-slate-400 mt-1"><?= t('modules.layout.fines.may_take_minutes') ?></p>
                </div>
                <div id="loteResultContainer" class="hidden text-center py-4">
                    <i class="fas fa-check-circle text-3xl text-green-500"></i>
                    <p class="text-sm font-medium text-green-700 mt-2" id="loteResultMsg"></p>
                </div>
            </div>
            <div class="modal-actions mt-4">
                <button class="btn-secondary" onclick="closeConsultaLoteModal()"><?= t('modules.layout.buttons.close') ?></button>
            </div>
        </div>
    </div>

    <!-- Modal PIX Recarga -->
    <div id="pixModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 450px; text-align: left;">
            <h3 class="modal-title"><i class="fas fa-qrcode mr-2"></i><?= t('modules.layout.pix.title') ?></h3>
            <div class="modal-message" style="margin-bottom: 0;">
                <div id="pixFormContainer">
                    <div class="mb-4">
                        <label class="block text-sm text-slate-600 mb-1"><?= t('modules.layout.pix.amount') ?></label>
                        <input type="text" id="pixValorInput" class="form-input-focus w-full" inputmode="numeric">
                        <p class="text-xs text-slate-400 mt-1"><?= t('modules.layout.pix.minimum') ?> <span id="pixMinimoLabel">100,00</span></p>
                    </div>
                    <button onclick="executarGerarPix()" class="btn-blue w-full py-2.5 rounded text-sm font-medium">
                        <i class="fas fa-qrcode mr-2"></i> <?= t('modules.layout.pix.generate') ?>
                    </button>
                </div>
                <div id="pixLoadingContainer" class="hidden text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i>
                    <p class="text-sm text-slate-500 mt-2"><?= t('modules.layout.pix.generating') ?></p>
                </div>
                <div id="pixQrcodeContainer" class="hidden text-center">
                    <div class="mb-3">
                        <img id="pixQrcodeImg" src="" alt="QR Code PIX" class="mx-auto max-w-[250px] rounded border">
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs text-slate-500 mb-1"><?= t('modules.layout.pix.copy_paste') ?></label>
                        <div class="flex">
                            <input type="text" id="pixCodeText" class="form-input-focus flex-1 text-xs font-mono" readonly>
                            <button onclick="copiarCodigoPix()" class="bg-slate-100 hover:bg-slate-200 px-3 rounded-r text-sm" title="<?= t('modules.layout.buttons.copy') ?>">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">
                        <i class="fas fa-clock mr-1"></i> <?= t('modules.layout.pix.expires') ?>
                    </p>
                    <button onclick="resetPixModal()" class="text-blue-600 hover:text-blue-800 text-sm mt-3">
                        <i class="fas fa-redo mr-1"></i> <?= t('modules.layout.pix.generate_new') ?>
                    </button>
                </div>
            </div>
            <div class="modal-actions mt-4">
                <button class="btn-secondary" onclick="closePixModal()"><?= t('modules.layout.buttons.close') ?></button>
            </div>
        </div>
    </div>

    <!-- Modal Cartao (Stripe) -->
    <div id="cartaoModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 450px; text-align: left;">
            <h3 class="modal-title"><i class="fas fa-credit-card mr-2"></i><?= t('modules.layout.card.title') ?></h3>
            <div class="modal-message" style="margin-bottom: 0;">
                <div id="cartaoFormContainer">
                    <div class="mb-4">
                        <label class="block text-sm text-slate-600 mb-1"><?= t('modules.layout.card.amount') ?></label>
                        <input type="text" id="cartaoValorInput" class="form-input-focus w-full" inputmode="numeric">
                        <p class="text-xs text-slate-400 mt-1"><?= t('modules.layout.card.minimum') ?> <span id="cartaoMinimoLabel">100,00</span></p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm text-slate-600 mb-1"><?= t('modules.layout.card.data') ?></label>
                        <div id="stripe-card-element" class="form-input-focus p-3"></div>
                        <div id="card-errors" class="text-xs text-red-500 mt-1"></div>
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center text-sm text-slate-600">
                            <input type="checkbox" id="salvarCartaoCheck" class="mr-2 rounded">
                            <?= t('modules.layout.card.save_for_auto') ?>
                        </label>
                    </div>
                    <button onclick="executarPagarCartao()" class="bg-purple-600 hover:bg-purple-700 text-white w-full py-2.5 rounded text-sm font-medium">
                        <i class="fas fa-lock mr-2"></i> <?= t('modules.layout.card.pay') ?>
                    </button>
                </div>
                <div id="cartaoLoadingContainer" class="hidden text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-purple-500"></i>
                    <p class="text-sm text-slate-500 mt-2"><?= t('modules.layout.card.processing') ?></p>
                </div>
                <div id="cartaoSucessoContainer" class="hidden text-center py-6">
                    <i class="fas fa-check-circle text-4xl text-green-500"></i>
                    <p class="text-lg font-semibold text-green-700 mt-2"><?= t('modules.layout.card.success') ?></p>
                    <p class="text-sm text-slate-500 mt-1" id="cartaoSucessoMsg"></p>
                </div>
            </div>
            <div class="modal-actions mt-4">
                <button class="btn-secondary" onclick="closeCartaoModal()"><?= t('modules.layout.buttons.close') ?></button>
            </div>
        </div>
    </div>

    <!-- Modal Adicionar Cartao (Locacao - Bloqueio/Caucao) -->
    <div id="addCartaoLocacaoModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 450px; text-align: left;">
            <h3 class="modal-title"><i class="fas fa-credit-card mr-2"></i><?= t('modules.locacoes.block.add_card_title') ?></h3>
            <div class="modal-message" style="margin-bottom: 0;">
                <div class="form-input-group mb-4">
                    <label for="addCartaoGatewaySelect" class="form-label-group"><?= t('modules.locacoes.block.gateway') ?></label>
                    <select id="addCartaoGatewaySelect" class="form-input-group-field">
                        <option value=""><?= t('common.labels.loading') ?></option>
                    </select>
                </div>
                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.locacoes.block.card_data') ?></label>
                    <div id="addCartaoStripeElement" class="p-3"></div>
                    <p id="addCartaoStripeError" class="text-xs text-red-500 mt-1 hidden"></p>
                </div>
            </div>
            <div class="modal-actions mt-4">
                <button class="btn-secondary" onclick="closeAddCartaoLocacaoModal()"><?= t('common.buttons.cancel') ?></button>
                <button id="addCartaoSalvarBtn" class="btn-blue py-2 px-4 rounded-md text-sm font-medium shadow hover:shadow-md transition-shadow" onclick="salvarCartaoLocacaoModal()" disabled>
                    <i class="fas fa-save mr-1"></i><?= t('common.buttons.save') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Nova Indicacao -->
    <div id="indicacaoModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 500px; text-align: left;">
            <h3 class="modal-title"><i class="fas fa-user-shield mr-2"></i><?= t('modules.layout.indication.title') ?></h3>
            <div class="modal-message" style="margin-bottom: 0;">
                <form id="formIndicacaoModal" onsubmit="submitIndicacao(event)">
                    <div class="mb-4">
                        <label class="block text-sm text-slate-600 mb-1"><?= t('modules.layout.indication.type') ?></label>
                        <select id="indicacaoTipoSelect" class="form-input-focus w-full" onchange="toggleIndicacaoCampos()">
                            <option value="real_infrator"><?= t('modules.layout.indication.real_driver') ?></option>
                            <option value="principal_condutor"><?= t('modules.layout.indication.main_driver') ?></option>
                        </select>
                    </div>
                    <div id="indicacaoCamposRealInfrator">
                        <div class="mb-4">
                            <label class="block text-sm text-slate-600 mb-1"><?= t('modules.layout.indication.fine_select') ?></label>
                            <select id="indicacaoSelectMulta" class="form-input-focus w-full">
                                <option value=""><?= t('modules.layout.indication.loading_fines') ?></option>
                            </select>
                        </div>
                    </div>
                    <div id="indicacaoCamposPrincipalCondutor" class="hidden">
                        <div class="mb-4">
                            <label class="block text-sm text-slate-600 mb-1"><?= t('modules.layout.fines.plate') ?></label>
                            <input type="text" id="indicacaoPlacaInput" class="form-input-focus w-full" placeholder="ABC1D23" maxlength="7">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm text-slate-600 mb-1"><?= t('modules.layout.indication.cpf') ?></label>
                            <input type="text" id="indicacaoCpfInput" class="form-input-focus w-full" placeholder="000.000.000-00">
                        </div>
                        <div>
                            <label class="block text-sm text-slate-600 mb-1"><?= t('modules.layout.indication.name') ?></label>
                            <input type="text" id="indicacaoNomeInput" class="form-input-focus w-full" placeholder="<?= t('modules.layout.indication.full_name') ?>">
                        </div>
                    </div>
                    <div id="indicacaoCampoCnh" class="mb-4 hidden">
                        <label class="block text-sm text-slate-600 mb-1"><?= t('modules.layout.indication.cnh') ?></label>
                        <input type="text" id="indicacaoCnhInput" class="form-input-focus w-full" placeholder="<?= t('modules.layout.indication.cnh_placeholder') ?>">
                    </div>
                    <button type="submit" class="btn-blue w-full py-2.5 rounded text-sm font-medium">
                        <i class="fas fa-paper-plane mr-2"></i> <?= t('modules.layout.indication.send') ?>
                    </button>
                </form>
                <div id="indicacaoLoadingContainer" class="hidden text-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i>
                    <p class="text-sm text-slate-500 mt-2"><?= t('modules.layout.indication.sending') ?></p>
                </div>
            </div>
            <div class="modal-actions mt-4" id="indicacaoActionsContainer">
                <button class="btn-secondary" onclick="closeIndicacaoModal()"><?= t('modules.layout.buttons.close') ?></button>
            </div>
        </div>
    </div>

    <!-- Modal Instrucoes Indicacao -->
    <div id="indicacaoInstrucoesModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 700px; text-align: left; max-height: 90vh; overflow-y: auto;">
            <h3 class="modal-title"><i class="fas fa-info-circle mr-2"></i><?= t('modules.serpro_indicacoes.instructions.title') ?></h3>
            <div class="modal-message" style="margin-bottom: 0;">
                <div class="space-y-4 text-sm text-slate-700">

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-800 mb-2"><i class="fas fa-question-circle mr-1"></i> <?= t('modules.serpro_indicacoes.instructions.what_is_title') ?></h4>
                        <p class="text-blue-700"><?= t('modules.serpro_indicacoes.instructions.what_is_text') ?></p>
                    </div>

                    <div class="border border-slate-200 rounded-lg p-4">
                        <h4 class="font-semibold text-orange-700 mb-2"><i class="fas fa-user-shield mr-1"></i> <?= t('modules.serpro_indicacoes.instructions.real_infrator_title') ?></h4>
                        <p class="mb-2"><?= t('modules.serpro_indicacoes.instructions.real_infrator_desc') ?></p>
                        <ul class="list-disc list-inside space-y-1 text-slate-600 ml-2">
                            <li><strong><?= t('modules.serpro_indicacoes.instructions.label_when') ?></strong> <?= t('modules.serpro_indicacoes.instructions.real_infrator_when') ?></li>
                            <li><strong><?= t('modules.serpro_indicacoes.instructions.label_prereq') ?></strong> <?= t('modules.serpro_indicacoes.instructions.real_infrator_prereq') ?></li>
                            <li><strong><?= t('modules.serpro_indicacoes.instructions.label_fields') ?></strong> <?= t('modules.serpro_indicacoes.instructions.real_infrator_fields') ?></li>
                            <li><strong><?= t('modules.serpro_indicacoes.instructions.label_result') ?></strong> <?= t('modules.serpro_indicacoes.instructions.real_infrator_result') ?></li>
                        </ul>
                    </div>

                    <div class="border border-slate-200 rounded-lg p-4">
                        <h4 class="font-semibold text-indigo-700 mb-2"><i class="fas fa-id-card mr-1"></i> <?= t('modules.serpro_indicacoes.instructions.principal_title') ?></h4>
                        <p class="mb-2"><?= t('modules.serpro_indicacoes.instructions.principal_desc') ?></p>
                        <ul class="list-disc list-inside space-y-1 text-slate-600 ml-2">
                            <li><strong><?= t('modules.serpro_indicacoes.instructions.label_when') ?></strong> <?= t('modules.serpro_indicacoes.instructions.principal_when') ?></li>
                            <li><strong><?= t('modules.serpro_indicacoes.instructions.label_advantage') ?></strong> <?= t('modules.serpro_indicacoes.instructions.principal_advantage') ?></li>
                            <li><strong><?= t('modules.serpro_indicacoes.instructions.label_fields') ?></strong> <?= t('modules.serpro_indicacoes.instructions.principal_fields') ?></li>
                            <li><strong><?= t('modules.serpro_indicacoes.instructions.label_important') ?></strong> <?= t('modules.serpro_indicacoes.instructions.principal_important') ?></li>
                        </ul>
                    </div>

                    <div class="border border-slate-200 rounded-lg p-4">
                        <h4 class="font-semibold text-slate-800 mb-2"><i class="fas fa-tasks mr-1"></i> <?= t('modules.serpro_indicacoes.instructions.status_title') ?></h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"><?= t('modules.layout.status.sent') ?></span>
                                <span class="text-slate-500"><?= t('modules.serpro_indicacoes.instructions.status_enviado') ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"><?= t('modules.layout.status.processing') ?></span>
                                <span class="text-slate-500"><?= t('modules.serpro_indicacoes.instructions.status_processando') ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700"><?= t('modules.layout.status.accepted') ?></span>
                                <span class="text-slate-500"><?= t('modules.serpro_indicacoes.instructions.status_aceito') ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"><?= t('modules.layout.status.rejected') ?></span>
                                <span class="text-slate-500"><?= t('modules.serpro_indicacoes.instructions.status_rejeitado') ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600"><?= t('modules.layout.status.canceled') ?></span>
                                <span class="text-slate-500"><?= t('modules.serpro_indicacoes.instructions.status_cancelado') ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-50 text-red-500"><?= t('modules.layout.status.expired') ?></span>
                                <span class="text-slate-500"><?= t('modules.serpro_indicacoes.instructions.status_expirado') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <h4 class="font-semibold text-amber-800 mb-2"><i class="fas fa-exclamation-triangle mr-1"></i> <?= t('modules.serpro_indicacoes.instructions.important_title') ?></h4>
                        <ul class="list-disc list-inside space-y-1 text-amber-700 ml-2">
                            <li><?= t('modules.serpro_indicacoes.instructions.important_1') ?></li>
                            <li><?= t('modules.serpro_indicacoes.instructions.important_2') ?></li>
                            <li><?= t('modules.serpro_indicacoes.instructions.important_3') ?></li>
                            <li><?= t('modules.serpro_indicacoes.instructions.important_4') ?></li>
                            <li><?= t('modules.serpro_indicacoes.instructions.important_5') ?></li>
                        </ul>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                        <h4 class="font-semibold text-slate-800 mb-2"><i class="fas fa-route mr-1"></i> <?= t('modules.serpro_indicacoes.instructions.steps_title') ?></h4>
                        <ol class="list-decimal list-inside space-y-1 text-slate-600 ml-2">
                            <li><?= t('modules.serpro_indicacoes.instructions.step_1') ?></li>
                            <li><?= t('modules.serpro_indicacoes.instructions.step_2') ?></li>
                            <li><?= t('modules.serpro_indicacoes.instructions.step_3') ?></li>
                            <li><?= t('modules.serpro_indicacoes.instructions.step_4') ?></li>
                            <li><?= t('modules.serpro_indicacoes.instructions.step_5') ?></li>
                            <li><?= t('modules.serpro_indicacoes.instructions.step_6') ?></li>
                        </ol>
                    </div>

                </div>
            </div>
            <div class="modal-actions mt-4">
                <button class="btn-secondary" onclick="closeIndicacaoInstrucoes()"><?= t('common.buttons.close') ?></button>
            </div>
        </div>
    </div>

    <!-- Offcanvas Overlay -->
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    <!-- Offcanvas Panel -->
    <div id="offcanvasPanel" class="offcanvas-panel">
        <div class="flex justify-between items-center mb-6 border-b pb-3">
            <h3 class="text-lg font-semibold text-slate-700"><?= t('modules.layout.offcanvas.title') ?></h3>
            <button id="closeOffcanvasButton" class="text-slate-500 hover:text-slate-700">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <div>
            <p class="text-slate-600"><?= t('modules.layout.offcanvas.example_1') ?></p>
            <p class="mt-4 text-slate-600"><?= t('modules.layout.offcanvas.example_2') ?></p>
        </div>
    </div>

    <!-- Modal de Impressão Fullscreen -->
    <div id="printModal" class="print-modal-overlay">
        <div class="print-modal-container">
            <div class="print-modal-header">
                <h3 class="print-modal-title">
                    <i class="fas fa-print"></i>
                    <span id="printModalTitle"><?= t('modules.layout.print.title') ?></span>
                </h3>
                <button type="button" class="print-modal-close" onclick="closePrintModal()" title="<?= t('modules.layout.buttons.close') ?>">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="print-modal-body">
                <div id="printModalLoading" class="print-modal-loading">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p><?= t('modules.layout.print.loading') ?></p>
                </div>
                <iframe id="printModalIframe" class="print-modal-iframe"></iframe>
            </div>
            <div class="print-modal-footer">
                <button type="button" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium" onclick="closePrintModal()">
                    <?= t('modules.layout.buttons.close') ?>
                </button>
                <button type="button" class="btn-blue py-2 px-4 rounded-md text-sm font-medium" onclick="executePrint()">
                    <i class="fas fa-print mr-2"></i><?= t('modules.layout.buttons.print') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Busca Global (Spotlight) -->
    <div id="spotlightModal" class="modal-overlay" style="align-items: flex-start; padding-top: 3vh !important;">
        <div class="modal-box" style="max-width: 600px; padding: 0; overflow: hidden; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <div style="padding: 16px; border-bottom: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-search text-slate-400"></i>
                    <input type="text" id="spotlightInput"
                        placeholder="<?= t('modules.layout.spotlight.placeholder') ?>"
                        autocomplete="off"
                        style="flex: 1; border: none; outline: none; font-size: 16px; background: transparent;">
                    <kbd style="padding: 2px 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 11px; color: #94a3b8; background: #f8fafc;">ESC</kbd>
                </div>
            </div>
            <div id="spotlightResults" style="max-height: 400px; overflow-y: auto;"></div>
            <div id="spotlightFooter" style="padding: 8px 16px; border-top: 1px solid #e2e8f0; display: none;">
                <span style="font-size: 11px; color: #94a3b8;">
                    <kbd style="padding: 1px 5px; border: 1px solid #cbd5e1; border-radius: 3px; font-size: 10px; background: #f8fafc;">↑↓</kbd> <?= t('modules.layout.spotlight.navigate') ?>
                    &nbsp;&nbsp;
                    <kbd style="padding: 1px 5px; border: 1px solid #cbd5e1; border-radius: 3px; font-size: 10px; background: #f8fafc;">Enter</kbd> <?= t('modules.layout.spotlight.open') ?>
                    &nbsp;&nbsp;
                    <kbd style="padding: 1px 5px; border: 1px solid #cbd5e1; border-radius: 3px; font-size: 10px; background: #f8fafc;">Esc</kbd> <?= t('modules.layout.spotlight.close') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Modal Global: Local de Atendimento (matriz/filial) -->
    <div id="localAtendimentoModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 720px; text-align: left;">
            <h3 class="modal-title text-center" id="localAtendimentoModalTitle"><?= t('modules.layout.local.new_title') ?></h3>
            <div class="mt-4">
                <input type="hidden" id="localAtendimentoIdx" value="">

                <div class="form-input-group">
                    <label for="localAtendimentoNome" class="form-label-group"><?= t('modules.layout.local.name') ?> {!! aviso(t('modules.layout.local.name_hint')) !!}</label>
                    <input type="text" id="localAtendimentoNome" class="form-input-group-field" maxlength="100" placeholder="<?= t('modules.layout.local.name_placeholder') ?>">
                </div>

                <div class="grid grid-cols-12 gap-4 mt-4">
                    <div class="col-span-12 sm:col-span-4 form-input-group">
                        <label for="localAtendimentoCep" class="form-label-group"><?= t('modules.layout.local.zip') ?> {!! aviso(t('modules.layout.local.zip_hint')) !!}</label>
                        <input type="text" id="localAtendimentoCep" class="form-input-group-field cep" maxlength="9" placeholder="00000-000">
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-4 mt-4">
                    <div class="col-span-12 sm:col-span-7 form-input-group">
                        <label for="localAtendimentoRua" class="form-label-group"><?= t('modules.layout.local.street') ?></label>
                        <input type="text" id="localAtendimentoRua" class="form-input-group-field" maxlength="150">
                    </div>
                    <div class="col-span-12 sm:col-span-2 form-input-group">
                        <label for="localAtendimentoNumero" class="form-label-group"><?= t('modules.layout.local.number') ?></label>
                        <input type="text" id="localAtendimentoNumero" class="form-input-group-field" maxlength="15">
                    </div>
                    <div class="col-span-12 sm:col-span-3 form-input-group">
                        <label for="localAtendimentoComplemento" class="form-label-group"><?= t('modules.layout.local.complement') ?></label>
                        <input type="text" id="localAtendimentoComplemento" class="form-input-group-field" maxlength="100">
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-4 mt-4">
                    <div class="col-span-12 sm:col-span-5 form-input-group">
                        <label for="localAtendimentoBairro" class="form-label-group"><?= t('modules.layout.local.district') ?></label>
                        <input type="text" id="localAtendimentoBairro" class="form-input-group-field" maxlength="80" required>
                    </div>
                    <div class="col-span-12 sm:col-span-5 form-input-group">
                        <label for="localAtendimentoCidade" class="form-label-group"><?= t('modules.layout.local.city') ?></label>
                        <input type="text" id="localAtendimentoCidade" class="form-input-group-field" maxlength="80" required>
                    </div>
                    <div class="col-span-12 sm:col-span-2 form-input-group">
                        <label for="localAtendimentoEstado" class="form-label-group"><?= t('modules.layout.local.state') ?></label>
                        <input type="text" id="localAtendimentoEstado" class="form-input-group-field" maxlength="2" required>
                    </div>
                </div>
            </div>
            <div class="modal-actions mt-6">
                <button type="button" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium" onclick="closeLocalAtendimentoModal()"><?= t('modules.layout.buttons.cancel') ?></button>
                <button type="button" class="btn-blue py-2 px-4 rounded-md text-sm font-medium" onclick="saveLocalAtendimentoModal()"><?= t('modules.layout.local.save') ?></button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        window.APP_CONFIG = window.APP_CONFIG || {};
        window.APP_CONFIG.currency = <?= json_encode(currency_config()) ?>;
        window.APP_I18N = window.APP_I18N || {};
        window.APP_I18N.common = <?= json_encode(\App\I18n\Translator::getInstance()->getFile('common'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.APP_I18N.dashboard = <?= json_encode(\App\I18n\Translator::getInstance()->getFile('modules.dashboard'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.layoutI18n = <?= json_encode(\App\I18n\Translator::getInstance()->getFile('modules.layout'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.layoutLocale = <?= json_encode(current_locale()) ?>;
    </script>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="<?= asset('js/api.min.js'); ?>"></script>
    <script src="<?= asset('js/currency.min.js'); ?>"></script>
    <script src="<?= asset('js/chosen-select.min.js'); ?>"></script>
    <script src="<?= asset('js/toast.min.js'); ?>"></script>
    <script src="<?= asset('js/components.min.js'); ?>"></script>
    <script src="<?= asset('js/autocomplete-guard.min.js'); ?>"></script>
    <script src="<?= asset('js/dashboard.min.js'); ?>"></script>
    <script src="<?= asset('js/screen-recorder.min.js'); ?>"></script>

    <!-- Script do Modal Global de Exclusão -->
    <script>
    (function() {
        const layoutT = window.layoutT = function(path, replace) {
            const parts = path.split('.');
            let value = window.layoutI18n || {};
            for (const part of parts) {
                if (!value || typeof value !== 'object' || !(part in value)) return path;
                value = value[part];
            }
            if (typeof value !== 'string') return path;
            Object.entries(replace || {}).forEach(([key, item]) => {
                value = value.replaceAll(':' + key, item);
            });
            return value;
        };
        const formatLayoutCurrency = window.formatLayoutCurrency = function(value) {
            return window.Currency && typeof Currency.format === 'function'
                ? Currency.format(value, true)
                : String(value);
        };
        // Variáveis globais para o modal de exclusão
        let globalRecordId = null;
        let globalRecordName = null;
        let globalRecordType = null;
        let globalCustomAction = null;
        let globalSourceWindow = null; // Referência do iframe de origem
        const CONFIRM_TEXT = layoutT('delete.confirm_text');
        let globalConfirmType = 'text';
        let globalExpectedText = '';

        /**
         * Abre o modal de exclusão global
         */
        window.openGlobalDeleteModal = function(recordId, recordName, recordType = 'registro', confirmType = 'text', customAction = null) {
            globalRecordId = recordId;
            globalRecordName = recordName;
            globalRecordType = recordType;
            globalCustomAction = customAction;
            globalConfirmType = confirmType;

            const modal = document.getElementById('deleteConfirmationModal');
            const modalTitle = document.getElementById('deleteModalTitle');
            const modalMessage = document.getElementById('deleteModalMessage');
            const confirmSection = document.getElementById('confirmDeleteSection');
            const confirmInput = document.getElementById('confirmDeleteInput');
            const confirmText = document.getElementById('confirmDeleteText');
            const confirmButton = document.getElementById('confirmDeleteButton');

            // Validar que os elementos existem
            if (!modal || !modalTitle || !modalMessage) {
                return;
            }

            modalTitle.textContent = layoutT('delete.title');
            // Garantir que temos um nome válido
            const displayName = recordName && recordName !== 'undefined' && recordName !== '' ? recordName : layoutT('delete.record_fallback');
            modalMessage.textContent = layoutT('delete.message', { type: recordType, name: displayName });

            // Modo sem confirmação
            if (confirmType === 'none') {
                confirmSection.style.display = 'none';
                confirmButton.disabled = false;
                confirmButton.style.opacity = '1';
                confirmButton.style.cursor = 'pointer';
                confirmInput.removeEventListener('input', validateGlobalDeleteConfirmation);
                modal.classList.add('open');
                return;
            }

            // Modo com confirmação de texto (EXCLUIR)
            confirmSection.style.display = 'block';
            globalExpectedText = CONFIRM_TEXT;
            confirmText.textContent = CONFIRM_TEXT;
            confirmInput.placeholder = layoutT('delete.placeholder', { text: CONFIRM_TEXT });

            // Resetar campo e botão
            confirmInput.value = '';
            confirmButton.disabled = true;
            confirmButton.style.opacity = '0.5';
            confirmButton.style.cursor = 'not-allowed';

            // Remover listener anterior se existir
            confirmInput.removeEventListener('input', validateGlobalDeleteConfirmation);

            // Focar no campo de confirmação
            modal.classList.add('open');
            setTimeout(() => confirmInput.focus(), 100);

            // Validar enquanto digita ou cola
            confirmInput.addEventListener('input', validateGlobalDeleteConfirmation);
            confirmInput.addEventListener('paste', function() {
                setTimeout(validateGlobalDeleteConfirmation, 0);
            });
        };

        /**
         * Fecha o modal de exclusão global
         */
        window.closeGlobalDeleteModal = function() {
            const modal = document.getElementById('deleteConfirmationModal');
            const confirmSection = document.getElementById('confirmDeleteSection');
            const confirmInput = document.getElementById('confirmDeleteInput');

            if (confirmInput) {
                confirmInput.removeEventListener('input', validateGlobalDeleteConfirmation);
                confirmInput.value = '';
            }
            if (confirmSection) {
                confirmSection.style.display = 'none';
            }

            modal.classList.remove('open');
            globalRecordId = null;
            globalRecordName = null;
            globalRecordType = null;
            globalCustomAction = null;
            globalConfirmType = 'text';
            globalExpectedText = '';
        };

        /**
         * Valida o texto digitado no campo de confirmação
         */
        function validateGlobalDeleteConfirmation() {
            const confirmInput = document.getElementById('confirmDeleteInput');
            const confirmButton = document.getElementById('confirmDeleteButton');
            const inputValue = confirmInput.value.trim();

            // Comparação case-insensitive
            const matches = inputValue.toLowerCase() === globalExpectedText.toLowerCase();

            if (matches) {
                confirmButton.disabled = false;
                confirmButton.style.opacity = '1';
                confirmButton.style.cursor = 'pointer';
            } else {
                confirmButton.disabled = true;
                confirmButton.style.opacity = '0.5';
                confirmButton.style.cursor = 'not-allowed';
            }
        }

        /**
         * Confirma a exclusão e envia mensagem para o iframe
         */
        window.confirmGlobalDelete = function() {
            if (!globalRecordId) return;

            // Enviar para o iframe de origem (offcanvas) ou aba ativa
            const targetWindow = globalSourceWindow ||
                document.querySelector('.tab-content.active-content iframe')?.contentWindow;

            if (targetWindow) {
                targetWindow.postMessage({
                    action: 'confirmDelete',
                    recordId: globalRecordId,
                    recordName: globalRecordName,
                    recordType: globalRecordType,
                    customAction: globalCustomAction
                }, '*');
            }

            closeGlobalDeleteModal();
            globalSourceWindow = null; // Limpar referência
        };

        // ===== MODAL DE CONFIRMAÇÃO GENÉRICO =====
        let genericConfirmSourceIframe = null;

        /**
         * Abre o modal de confirmação genérico
         * @param {string} title - Título do modal
         * @param {string} message - Mensagem de confirmação
         * @param {Window} sourceIframe - ContentWindow do iframe de origem
         * @param {string} confirmText - Texto do botão de confirmação
         */
        window.openGenericConfirmModal = function(title, message, sourceIframe, confirmText = layoutT('generic.confirm_title')) {
            genericConfirmSourceIframe = sourceIframe;

            const modal = document.getElementById('genericConfirmModal');
            const modalTitle = document.getElementById('genericModalTitle');
            const modalMessage = document.getElementById('genericModalMessage');
            const confirmButton = document.getElementById('confirmGenericButton');

            if (!modal || !modalTitle || !modalMessage) return;

            modalTitle.textContent = title;
            modalMessage.textContent = message;
            confirmButton.textContent = confirmText;
            confirmButton.className = 'btn-blue py-2 px-4 rounded-md text-sm font-medium';
            modal.classList.add('open');
        };

        /**
         * Fecha o modal de confirmação genérico
         */
        window.closeGenericConfirmModal = function() {
            const modal = document.getElementById('genericConfirmModal');
            if (modal) modal.classList.remove('open');
            // Limpar flag de reset de assinatura se estava pendente
            window._pendingAssinaturaReset = false;
            // Notificar iframe que modal foi fechado sem confirmar
            if (genericConfirmSourceIframe) {
                genericConfirmSourceIframe.postMessage({ action: 'genericModalClosed' }, '*');
            }
            genericConfirmSourceIframe = null;
        };

        /**
         * Confirma a ação e envia mensagem para o iframe de origem
         */
        window.confirmGenericAction = function() {
            // Verificar se eh reset de assinatura (chamado do app.php, nao do iframe)
            if (window._pendingAssinaturaReset) {
                window._pendingAssinaturaReset = false;
                executeAssinaturaReset();
                closeGenericConfirmModal();
                return;
            }

            // Fluxo normal para iframes
            if (genericConfirmSourceIframe) {
                genericConfirmSourceIframe.postMessage({ action: 'genericConfirmed' }, '*');
            }
            closeGenericConfirmModal();
        };

        // ===== MODAL DE EDIÇÃO EM LOTE =====
        let editBatchCallback = null;

        window.openEditBatchModal = function(title, fields, callbackId) {
            const modal = document.getElementById('editBatchModal');
            const modalTitle = document.getElementById('editBatchModalTitle');
            const content = document.getElementById('editBatchModalContent');

            modalTitle.textContent = title;
            editBatchCallback = callbackId;

            // Gerar campos do formulário
            let html = '<div class="space-y-4">';
            fields.forEach(field => {
                html += `<div class="form-input-group">
                    <label class="form-label-group">${field.label}</label>`;
                if (field.type === 'select') {
                    html += `<select id="editBatch_${field.name}" class="form-input-group-field">`;
                    field.options.forEach(opt => {
                        html += `<option value="${opt.value}">${opt.label}</option>`;
                    });
                    html += '</select>';
                } else {
                    html += `<input type="${field.type}" id="editBatch_${field.name}" class="form-input-group-field">`;
                }
                if (field.hint) {
                    html += `<p class="text-xs text-slate-500 mt-1">${field.hint}</p>`;
                }
                html += '</div>';
            });
            html += '</div>';

            content.innerHTML = html;
            modal.classList.add('open');
            document.body.classList.add('modal-open');
        };

        window.closeEditBatchModal = function() {
            const modal = document.getElementById('editBatchModal');
            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
            editBatchCallback = null;
        };

        // ===== MODAL DE INTEGRAÇÃO (Website > Integrações) =====
        let integracaoModalSource = null;

        window.openIntegracaoModal = function(integracao, source) {
            integracaoModalSource = source;
            const isEdit = integracao && integracao.id;
            document.getElementById('integracaoModalTitle').textContent = isEdit ? layoutT('integration.edit_title') : layoutT('integration.add_title');
            document.getElementById('integracaoModalId').value = isEdit ? integracao.id : '';
            document.getElementById('integracaoModalDescricao').value = (integracao && integracao.descricao) || '';
            document.getElementById('integracaoModalTipo').value = (integracao && integracao.tipo) || 'head';
            document.getElementById('integracaoModalCodigo').value = (integracao && integracao.codigo) || '';
            document.getElementById('integracaoModalAtivo').checked = integracao ? integracao.ativo == 1 : true;

            document.getElementById('integracaoModal').classList.add('open');
            document.body.classList.add('modal-open');
        };

        window.closeIntegracaoModal = function() {
            document.getElementById('integracaoModal').classList.remove('open');
            document.body.classList.remove('modal-open');
            integracaoModalSource = null;
        };

        window.confirmIntegracao = function() {
            if (!integracaoModalSource) { closeIntegracaoModal(); return; }

            const data = {
                id:        document.getElementById('integracaoModalId').value || null,
                descricao: document.getElementById('integracaoModalDescricao').value,
                tipo:      document.getElementById('integracaoModalTipo').value,
                codigo:    document.getElementById('integracaoModalCodigo').value,
                ativo:     document.getElementById('integracaoModalAtivo').checked ? 1 : 0,
            };

            // Envia os dados de volta pro iframe fazer o POST
            integracaoModalSource.postMessage({
                action: 'integracaoModalConfirmado',
                data: data,
            }, '*');

            closeIntegracaoModal();
        };

        document.getElementById('cancelIntegracaoButton')?.addEventListener('click', closeIntegracaoModal);
        document.getElementById('confirmIntegracaoButton')?.addEventListener('click', confirmIntegracao);
        document.getElementById('integracaoModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeIntegracaoModal();
        });

        window.confirmEditBatch = function() {
            const content = document.getElementById('editBatchModalContent');
            const inputs = content.querySelectorAll('input, select');
            const values = {};

            inputs.forEach(input => {
                const name = input.id.replace('editBatch_', '');
                values[name] = input.value;
            });

            // Enviar valores para o iframe
            const iframe = document.querySelector('.tab-content.active-content iframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.postMessage({
                    action: 'editBatchConfirmed',
                    callbackId: editBatchCallback,
                    values: values
                }, '*');
            }

            closeEditBatchModal();
        };

        // Event listeners do modal de edição em lote
        document.getElementById('cancelEditBatchButton')?.addEventListener('click', closeEditBatchModal);
        document.getElementById('confirmEditBatchButton')?.addEventListener('click', confirmEditBatch);

        // ===== MODAL DE INPUT GLOBAL =====
        let inputModalCallback = null;
        let inputModalSource = null;

        window.openGlobalInputModal = function(title, label, currentValue, callbackAction) {
            inputModalSource = globalSourceWindow;
            inputModalCallback = callbackAction;

            document.getElementById('inputModalTitle').textContent = title;
            document.getElementById('inputModalLabel').textContent = label;

            const input = document.getElementById('inputModalInput');
            const confirmBtn = document.getElementById('confirmInputButton');

            input.value = currentValue || '';

            // Controlar estado inicial do botao baseado no valor
            const hasValue = (currentValue || '').trim().length > 0;
            confirmBtn.disabled = !hasValue;

            document.getElementById('inputModal').classList.add('open');
            document.body.classList.add('modal-open');
            setTimeout(() => input.focus(), 100);
        };

        function closeInputModal() {
            document.getElementById('inputModal').classList.remove('open');
            document.body.classList.remove('modal-open');
            document.getElementById('inputModalInput').value = '';
            inputModalCallback = null;
            inputModalSource = null;
        }

        function confirmInput() {
            const value = document.getElementById('inputModalInput').value.trim();
            if (!value) return; // Botao deveria estar desabilitado

            if (inputModalSource) {
                inputModalSource.postMessage({
                    action: inputModalCallback || 'inputConfirmed',
                    value: value
                }, '*');
            }
            closeInputModal();
        }

        document.getElementById('cancelInputButton')?.addEventListener('click', closeInputModal);
        document.getElementById('confirmInputButton')?.addEventListener('click', confirmInput);

        // Listener para habilitar/desabilitar botao baseado no valor do input
        document.getElementById('inputModalInput')?.addEventListener('input', function() {
            const confirmBtn = document.getElementById('confirmInputButton');
            confirmBtn.disabled = !this.value.trim();
        });

        // Fechar modal de input ao clicar fora
        document.getElementById('inputModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeInputModal();
            }
        });

        // Permitir salvar com Enter no input
        document.getElementById('inputModalInput')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmInput();
            }
        });

        // ===== MODAL REGULARIZACAO AUTORENOVACAO CONTRATO =====
        let renovacaoSyncSource = null;
        let renovacaoSyncContratoId = null;
        let renovacaoSyncPreview = null;

        window.openContratoRenovacaoSyncModal = async function(contratoId, source) {
            renovacaoSyncSource = source;
            renovacaoSyncContratoId = contratoId;
            renovacaoSyncPreview = null;

            const modal = document.getElementById('contratoRenovacaoSyncModal');
            const loading = document.getElementById('contratoRenovacaoSyncLoading');
            const content = document.getElementById('contratoRenovacaoSyncContent');
            const confirmBtn = document.getElementById('renovacaoSyncConfirmBtn');

            loading.style.display = 'block';
            content.style.display = 'none';
            confirmBtn.disabled = true;
            confirmBtn.style.opacity = '0.65';
            modal.classList.add('open');
            document.body.classList.add('modal-open');

            try {
                const result = await API.get('/api/contratos/' + contratoId + '/regularizacao-renovacao');
                if (!result.success) {
                    throw new Error(result.message || layoutT('renewal.load_error'));
                }

                renovacaoSyncPreview = result.data;
                preencherContratoRenovacaoSync(result.data);
                loading.style.display = 'none';
                content.style.display = 'block';
                confirmBtn.disabled = false;
                confirmBtn.style.opacity = '1';
            } catch (e) {
                closeContratoRenovacaoSyncModal();
                openAlertModal(e.message || layoutT('renewal.load_error'));
            }
        };

        function preencherContratoRenovacaoSync(data) {
            const contrato = data.contrato || {};
            const reg = data.regularizacao || {};
            const preview = data.preview_financeiro || null;
            const financeiroDisponivel = !!data.financeiro_disponivel;

            document.getElementById('renovacaoSyncContrato').textContent = contrato.codigo || '-';
            document.getElementById('renovacaoSyncCliente').textContent = contrato.cliente_nome || '-';
            document.getElementById('renovacaoSyncVencida').textContent = formatarDataRenovacaoSync(reg.data_renovacao_atual);
            document.getElementById('renovacaoSyncProxima').textContent = formatarDataRenovacaoSync(reg.nova_data_renovacao);
            document.getElementById('renovacaoSyncCiclos').textContent = (reg.ciclos || 0) + ' ' + layoutT('renewal.cycle') + ' ' + (reg.quantidade || 1) + ' ' + labelContagemRenovacaoSync(reg.contagem, reg.quantidade || 1);
            document.getElementById('renovacaoSyncPeriodo').textContent = formatarDataRenovacaoSync(reg.nova_data_ini) + ' ' + layoutT('renewal.until') + ' ' + formatarDataRenovacaoSync(reg.nova_data_fim);
            document.getElementById('renovacaoSyncComando').textContent = contrato.comando_parcela || layoutT('renewal.no_command');

            const gerarFinanceiro = document.getElementById('renovacaoSyncGerarFinanceiro');
            const aviso = document.getElementById('renovacaoSyncAviso');
            gerarFinanceiro.checked = financeiroDisponivel;
            gerarFinanceiro.disabled = !financeiroDisponivel;
            aviso.style.display = financeiroDisponivel ? 'none' : 'block';
            aviso.textContent = financeiroDisponivel ? '' : layoutT('renewal.no_payment_method');

            renderParcelasRenovacaoSync(preview?.parcelas || []);
            aplicarCanaisRenovacaoSync(data.canais_disponiveis || {});
            atualizarEstadoFinanceiroRenovacaoSync();
        }

        function aplicarCanaisRenovacaoSync(canais) {
            const mapa = {
                renovacaoSyncEmail: !!canais.email,
                renovacaoSyncWhatsapp: !!canais.whatsapp,
                renovacaoSyncSms: !!canais.sms
            };

            Object.keys(mapa).forEach(function(id) {
                const el = document.getElementById(id);
                if (!el) return;
                el.checked = false;
                el.dataset.canalDisponivel = mapa[id] ? '1' : '0';
                el.disabled = !mapa[id];
                const label = el.closest('label');
                if (label) {
                    label.classList.toggle('opacity-50', !mapa[id]);
                }
            });
        }

        function renderParcelasRenovacaoSync(parcelas) {
            const tbody = document.getElementById('renovacaoSyncParcelas');
            if (!parcelas.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="py-3 px-3 text-center text-slate-500">' + layoutT('renewal.no_installments') + '</td></tr>';
                return;
            }

            tbody.innerHTML = parcelas.map(function(p) {
                return '<tr class="border-t border-slate-100">'
                    + '<td class="py-2 px-3">' + (p.parcela || '-') + '/' + (p.total_parcelas || '-') + '</td>'
                    + '<td class="py-2 px-3">' + formatarDataRenovacaoSync(p.data_venci) + '</td>'
                    + '<td class="py-2 px-3 text-right">' + (p.valor_total_formatado || p.valor_subtotal_formatado || '-') + '</td>'
                    + '</tr>';
            }).join('');
        }

        function atualizarEstadoFinanceiroRenovacaoSync() {
            const gerar = document.getElementById('renovacaoSyncGerarFinanceiro').checked && !document.getElementById('renovacaoSyncGerarFinanceiro').disabled;
            document.getElementById('renovacaoSyncParcelasBox').style.display = gerar ? 'block' : 'none';
            document.getElementById('renovacaoSyncCanaisBox').style.opacity = gerar ? '1' : '0.45';
            ['renovacaoSyncEmail', 'renovacaoSyncWhatsapp', 'renovacaoSyncSms'].forEach(function(id) {
                const el = document.getElementById(id);
                const canalDisponivel = el.dataset.canalDisponivel !== '0';
                el.disabled = !gerar || !canalDisponivel;
                if (!gerar || !canalDisponivel) el.checked = false;
            });
        }

        document.getElementById('renovacaoSyncGerarFinanceiro')?.addEventListener('change', atualizarEstadoFinanceiroRenovacaoSync);

        window.closeContratoRenovacaoSyncModal = function() {
            document.getElementById('contratoRenovacaoSyncModal').classList.remove('open');
            document.body.classList.remove('modal-open');
            renovacaoSyncSource = null;
            renovacaoSyncContratoId = null;
            renovacaoSyncPreview = null;
        };

        window.confirmContratoRenovacaoSync = async function() {
            if (!renovacaoSyncContratoId) return;

            const confirmBtn = document.getElementById('renovacaoSyncConfirmBtn');
            confirmBtn.disabled = true;
            confirmBtn.style.opacity = '0.65';
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + layoutT('renewal.regularizing');

            try {
                const result = await API.post('/api/contratos/' + renovacaoSyncContratoId + '/regularizar-renovacao', {
                    gerar_financeiro: document.getElementById('renovacaoSyncGerarFinanceiro').checked,
                    enviar_email: document.getElementById('renovacaoSyncEmail').checked,
                    enviar_whatsapp: document.getElementById('renovacaoSyncWhatsapp').checked,
                    enviar_sms: document.getElementById('renovacaoSyncSms').checked
                });

                if (!result.success) {
                    throw new Error(result.message || layoutT('renewal.regularize_error'));
                }

                if (renovacaoSyncSource) {
                    renovacaoSyncSource.postMessage({ action: 'contratoRenovacaoRegularizada' }, '*');
                }
                closeContratoRenovacaoSyncModal();
                openAlertModal(result.message || layoutT('renewal.success'));
            } catch (e) {
                openAlertModal(e.message || layoutT('renewal.regularize_error'));
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.style.opacity = '1';
                confirmBtn.innerHTML = layoutT('buttons.regularize');
            }
        };

        document.getElementById('contratoRenovacaoSyncModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeContratoRenovacaoSyncModal();
            }
        });

        function formatarDataRenovacaoSync(data) {
            if (!data) return '-';
            const str = String(data).split(' ')[0];
            const parts = str.split('-');
            if (parts.length !== 3) return '-';
            const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            if (isNaN(d.getTime())) return '-';
            return d.toLocaleDateString((window.layoutLocale || 'pt_BR').replace('_', '-'));
        }

        function labelContagemRenovacaoSync(contagem, quantidade) {
            const singular = quantidade === 1;
            const labels = {
                dia: singular ? layoutT('renewal.units.dia.singular') : layoutT('renewal.units.dia.plural'),
                semana: singular ? layoutT('renewal.units.semana.singular') : layoutT('renewal.units.semana.plural'),
                mes: singular ? layoutT('renewal.units.mes.singular') : layoutT('renewal.units.mes.plural'),
                ano: singular ? layoutT('renewal.units.ano.singular') : layoutT('renewal.units.ano.plural')
            };
            return labels[contagem] || contagem || layoutT('renewal.units.dia.singular');
        }

        // Escutar mensagens do iframe para abrir o modal
        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'openDeleteModal') {
                globalSourceWindow = event.source; // Guardar referência do iframe de origem
                openGlobalDeleteModal(
                    event.data.recordId,
                    event.data.recordName,
                    event.data.recordType || 'registro',
                    event.data.confirmType || 'text',
                    event.data.customAction || null
                );
            } else if (event.data && event.data.action === 'openGenericConfirmModal') {
                // Abrir modal de confirmação genérico
                const sourceIframe = event.source;
                openGenericConfirmModal(
                    event.data.title || layoutT('generic.confirm_title'),
                    event.data.message || layoutT('generic.confirm_message'),
                    sourceIframe,
                    event.data.confirmText || layoutT('generic.confirm_title')
                );
            } else if (event.data && event.data.action === 'openIntegracaoModal') {
                // Abrir modal de integração fullscreen
                window.openIntegracaoModal(event.data.integracao || null, event.source);
            } else if (event.data && event.data.action === 'openEditBatchModal') {
                // Abrir modal de edição em lote
                openEditBatchModal(
                    event.data.title,
                    event.data.fields,
                    event.data.callbackId
                );
            } else if (event.data && event.data.action === 'openInputModal') {
                // Abrir modal de input global
                globalSourceWindow = event.source;
                openGlobalInputModal(
                        event.data.title || layoutT('generic.edit'),
                        event.data.label || layoutT('generic.name'),
                    event.data.value || '',
                    event.data.callbackAction || 'inputConfirmed'
                );
            } else if (event.data && event.data.action === 'openOffcanvasIframe') {
                // Abrir offcanvas com iframe
                if (typeof window.openOffcanvasIframe === 'function') {
                    window.openOffcanvasIframe(
                        event.data.url,
                        event.data.title || layoutT('generic.panel'),
                        event.data.width || '500px'
                    );
                }
            } else if (event.data && event.data.action === 'closeOffcanvas') {
                // Fechar offcanvas
                if (typeof window.closeOffcanvas === 'function') {
                    window.closeOffcanvas();
                }
            } else if (event.data && event.data.action === 'reloadMensageriaData') {
                // Repassar mensagem para o iframe principal ativo (mensageria)
                const mainContentArea = document.getElementById('mainContentArea');
                const activeTab = document.querySelector('.sidebar-tab.active');
                if (activeTab && mainContentArea) {
                    const tabId = activeTab.getAttribute('data-tab-id');
                    const tabContent = mainContentArea.querySelector('.tab-content[data-tab-content-id="' + tabId + '"]');
                    if (tabContent) {
                        const iframe = tabContent.querySelector('iframe');
                        if (iframe && iframe.contentWindow) {
                            iframe.contentWindow.postMessage({ action: 'reloadMensageriaData' }, '*');
                        }
                    }
                }
            } else if (event.data && event.data.action === 'openOffcanvasContent') {
                // Abrir offcanvas com conteúdo HTML direto
                if (typeof window.openOffcanvasContent === 'function') {
                    window.openOffcanvasContent(
                        event.data.content,
                        event.data.title || layoutT('generic.details'),
                        event.data.width || '500px'
                    );
                }
            } else if (event.data && event.data.action === 'updateOffcanvasContent') {
                // Atualizar conteúdo dentro do offcanvas
                const offcanvasPanel = document.getElementById('offcanvasPanel');
                if (offcanvasPanel && event.data.selector) {
                    const element = offcanvasPanel.querySelector(event.data.selector);
                    if (element) {
                        // Aplicar show ANTES do outerHTML, pois após substituição o element não é mais válido
                        if (event.data.show) {
                            element.classList.remove('hidden');
                        }
                        if (event.data.outerHtml) {
                            element.outerHTML = event.data.outerHtml;
                        } else if (event.data.html !== undefined) {
                            element.innerHTML = event.data.html;
                        }
                    }
                }
            } else if (event.data && event.data.action === 'refreshRoles') {
                // Repassar mensagem para o iframe principal atualizar select de roles
                const mainIframe = document.querySelector('.tab-content.active-content iframe');
                if (mainIframe && mainIframe.contentWindow) {
                    mainIframe.contentWindow.postMessage({ action: 'refreshRoles' }, '*');
                }
            } else if (event.data && event.data.action === 'refreshTiposPagamento') {
                // Repassar mensagem para o iframe principal atualizar select de tipos de pagamento
                const mainIframe = document.querySelector('.tab-content.active-content iframe');
                if (mainIframe && mainIframe.contentWindow) {
                    mainIframe.contentWindow.postMessage({ action: 'refreshTiposPagamento' }, '*');
                }
            } else if (event.data && event.data.action === 'templateActivated') {
                // Repassar mensagem para o iframe principal atualizar lista de temporadas
                const mainIframe = document.querySelector('.tab-content.active-content iframe');
                if (mainIframe && mainIframe.contentWindow) {
                    mainIframe.contentWindow.postMessage({ action: 'templateActivated' }, '*');
                }
            } else if (event.data && event.data.action === 'openFotoModal') {
                // Abrir modal de escolha de foto no documento pai
                abrirModalFotoEscolha(event.source);
            } else if (event.data && event.data.action === 'closeFotoModal') {
                // Fechar modal de escolha de foto
                fecharModalFotoEscolha();
            } else if (event.data && event.data.action === 'fotoModalAction') {
                // Ação do modal de foto (enviar arquivo ou usar câmera)
                const action = event.data.modalAction;
                const iframeSource = event.source;

                fecharModalFotoEscolha();

                // Enviar ação de volta para o iframe
                iframeSource.postMessage({
                    action: 'fotoModalActionResponse',
                    modalAction: action
                }, '*');
            } else if (event.data && event.data.action === 'openCameraModal') {
                // Abrir modal da câmera no documento pai
                abrirModalCamera(event.source);
            } else if (event.data && event.data.action === 'closeCameraModal') {
                // Fechar modal da câmera
                fecharModalCamera();
            } else if (event.data && event.data.action === 'openCameraArquivoModal') {
                // Abrir modal da câmera para arquivos no documento pai (com seletor de câmera)
                abrirModalCameraArquivo(event.source, event.data.arquivoTipo, event.data.arquivoNome);
            } else if (event.data && event.data.action === 'openValidationModal') {
                // Abrir modal de validação de formulário
                openValidationModal(event.data.errors);
            } else if (event.data && event.data.action === 'openAlert') {
                // Abrir modal de alerta global
                openAlertModal(event.data.message, event.data.callback, event.source);
            } else if (event.data && event.data.action === 'openContratoRenovacaoSyncModal') {
                // Abrir modal de regularizacao de autorrenovacao de contrato
                openContratoRenovacaoSyncModal(event.data.contratoId, event.source);
            } else if (event.data && event.data.action === 'openLinkModal') {
                // Abrir modal de link de pagamento
                openLinkModal(event.data.url);
            } else if (event.data && event.data.action === 'openSignatureLinkModal') {
                // Abrir modal de link de assinatura
                openSignatureLinkModal(event.data);
            } else if (event.data && event.data.action === 'openSessionExpiredModal') {
                // Abrir modal de sessão expirada
                openSessionExpiredModal();
            } else if (event.data && event.data.action === 'csrfTokenRefreshed' && event.data.csrfToken) {
                // Token CSRF renovado por um iframe - atualizar parent e broadcast para outros iframes
                if (window.API && typeof window.API.syncCsrfToken === 'function') {
                    window.API.syncCsrfToken(event.data.csrfToken);
                } else {
                    var meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.content = event.data.csrfToken;
                    document.querySelectorAll('input[name="_token"]').forEach(function(input) {
                        input.value = event.data.csrfToken;
                    });
                }
                document.querySelectorAll('iframe').forEach(function(iframe) {
                    try {
                        if (iframe.contentWindow && iframe.contentWindow !== event.source) {
                            iframe.contentWindow.postMessage({
                                action: 'csrfTokenRefreshed',
                                csrfToken: event.data.csrfToken
                            }, '*');
                        }
                    } catch (e) {}
                });
            } else if (event.data && event.data.action === 'openChangelogModal') {
                // Abrir modal de changelog
                openChangelogModal(event.data.dados, event.source);
            } else if (event.data && event.data.action === 'openVideoModal') {
                // Abrir modal de video
                openVideoModal(event.data.videoUrl);
            } else if (event.data && event.data.action === 'openDocumentoEscolhaModal') {
                // Abrir modal de escolha de criação de documento
                abrirModalDocumentoEscolha(event.source);
            } else if (event.data && event.data.action === 'openDocumentoUploadModal') {
                // Abrir modal de upload de documento
                abrirModalDocumentoUpload(event.source);
            } else if (event.data && event.data.action === 'closeDocumentoModal') {
                // Fechar modal de documento
                fecharModalDocumento();
            } else if (event.data && event.data.action === 'openPrintModal') {
                // Abrir modal de impressão fullscreen
                openPrintModal(event.data.url, event.data.title);
            } else if (event.data && event.data.action === 'openAssinaturaModal') {
                // Abrir modal de assinatura
                openAssinaturaModal(event.data, event.source);
            } else if (event.data && event.data.action === 'openConsultaMultasModal') {
                openConsultaMultasModal(event.source);
            } else if (event.data && event.data.action === 'openConsultaLoteModal') {
                openConsultaLoteModal(event.data, event.source);
            } else if (event.data && event.data.action === 'openPixModal') {
                openPixModal(event.data, event.source);
            } else if (event.data && event.data.action === 'openCartaoModal') {
                openCartaoModal(event.data, event.source);
            } else if (event.data && event.data.action === 'openIndicacaoModal') {
                openIndicacaoModal(event.data, event.source);
            } else if (event.data && event.data.action === 'openIndicacaoInstrucoes') {
                openIndicacaoInstrucoes();
            } else if (event.data && event.data.action === 'openSpotlight') {
                openSpotlight();
            } else if (event.data && event.data.action === 'openAddCartaoLocacaoModal') {
                openAddCartaoLocacaoModal(event.data, event.source);
            } else if (event.data && event.data.action === 'openLocalAtendimentoModal') {
                openLocalAtendimentoModal(event.data, event.source);
            } else if (event.data && event.data.action === 'showToast') {
                if (window.toast) {
                    window.toast.show(event.data.message || '', event.data.type || 'info');
                }
            }
        });

        // ===== MODAL ADICIONAR CARTAO (LOCACAO) =====

        let _addCartaoLocacaoSource = null;
        let _addCartaoStripeInstance = null;
        let _addCartaoCardElement = null;
        let _addCartaoClienteId = null;

        function openAddCartaoLocacaoModal(data, source) {
            _addCartaoLocacaoSource = source;
            _addCartaoClienteId = data.id_cliente || null;

            // Filtrar apenas gateways que suportam cartao (stripe, square)
            const cardGateways = (data.gateways || []).filter(g => g.gateway_code === 'stripe' || g.gateway_code === 'square');

            const select = document.getElementById('addCartaoGatewaySelect');
            const errorEl = document.getElementById('addCartaoStripeError');

            if (cardGateways.length > 0) {
                select.innerHTML = cardGateways.map(g =>
                    '<option value="' + g.id + '" data-code="' + g.gateway_code + '" data-pk="' + (g.publishable_key || '') + '">' + g.nome + '</option>'
                ).join('');
            } else {
                select.innerHTML = '<option value="">' + layoutT('card.no_gateway') + '</option>';
            }

            // Buscar publishable_key do Stripe
            const stripeGw = cardGateways.find(g => g.gateway_code === 'stripe' && g.publishable_key);
            const stripeKey = stripeGw ? stripeGw.publishable_key : null;

            // Abrir modal ANTES de montar Stripe (display:none impede mount)
            document.getElementById('addCartaoLocacaoModal').classList.add('open');
            document.body.classList.add('modal-open');

            // Inicializar Stripe Elements apos modal visivel
            if (stripeKey) {
                errorEl.classList.add('hidden');
                setTimeout(function() { _initAddCartaoStripe(stripeKey); }, 200);
            } else {
                // Mostrar erro se publishable_key nao configurada
                errorEl.textContent = layoutT('card.stripe_gateway_error');
                errorEl.classList.remove('hidden');
            }
        }

        window.closeAddCartaoLocacaoModal = function() {
            document.getElementById('addCartaoLocacaoModal').classList.remove('open');
            document.body.classList.remove('modal-open');
            if (_addCartaoCardElement) _addCartaoCardElement.clear();
            document.getElementById('addCartaoSalvarBtn').disabled = true;
            document.getElementById('addCartaoStripeError').classList.add('hidden');
            _addCartaoLocacaoSource = null;
        };

        // ===== MODAL LOCAL DE ATENDIMENTO (MATRIZ/FILIAL) =====
        let _localAtendimentoSource = null;

        window.openLocalAtendimentoModal = function(data, source) {
            _localAtendimentoSource = source;
            const l = data.local || {};
            const idx = (data.idx === null || data.idx === undefined) ? '' : String(data.idx);

            document.getElementById('localAtendimentoModalTitle').textContent = idx === '' ? layoutT('local.new_title') : layoutT('local.edit_title');
            document.getElementById('localAtendimentoIdx').value = idx;
            document.getElementById('localAtendimentoNome').value = l.nome || '';
            document.getElementById('localAtendimentoCep').value = l.cep || '';
            document.getElementById('localAtendimentoRua').value = l.rua || '';
            document.getElementById('localAtendimentoNumero').value = l.numero || '';
            document.getElementById('localAtendimentoComplemento').value = l.complemento || '';
            document.getElementById('localAtendimentoBairro').value = l.bairro || '';
            document.getElementById('localAtendimentoCidade').value = l.cidade || '';
            document.getElementById('localAtendimentoEstado').value = (l.estado || '').toUpperCase();

            document.getElementById('localAtendimentoModal').classList.add('open');
            document.body.classList.add('modal-open');
        };

        window.closeLocalAtendimentoModal = function() {
            document.getElementById('localAtendimentoModal').classList.remove('open');
            document.body.classList.remove('modal-open');
            _localAtendimentoSource = null;
        };

        window.saveLocalAtendimentoModal = function() {
            const bairro = document.getElementById('localAtendimentoBairro').value.trim();
            const cidade = document.getElementById('localAtendimentoCidade').value.trim();
            const estado = document.getElementById('localAtendimentoEstado').value.trim().toUpperCase();
            if (!bairro || !cidade || !estado) {
                openAlertModal(layoutT('local.required_error'));
                return;
            }
            const idxRaw = document.getElementById('localAtendimentoIdx').value;
            const idx = idxRaw === '' ? null : parseInt(idxRaw);
            const payload = {
                nome: document.getElementById('localAtendimentoNome').value.trim() || null,
                cep: document.getElementById('localAtendimentoCep').value.trim() || null,
                rua: document.getElementById('localAtendimentoRua').value.trim() || null,
                numero: document.getElementById('localAtendimentoNumero').value.trim() || null,
                complemento: document.getElementById('localAtendimentoComplemento').value.trim() || null,
                bairro, cidade, estado,
                pais: 'BR',
            };
            if (_localAtendimentoSource) {
                _localAtendimentoSource.postMessage({
                    action: 'localAtendimentoModalSaved',
                    idx: idx,
                    local: payload,
                }, '*');
            }
            closeLocalAtendimentoModal();
        };

        // Fechar ao clicar no overlay
        document.getElementById('localAtendimentoModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeLocalAtendimentoModal();
        });

        // CEP lookup (ViaCEP)
        document.getElementById('localAtendimentoCep')?.addEventListener('blur', function() {
            const cep = this.value.replace(/\D/g, '');
            if (cep.length !== 8) return;
            fetch('https://viacep.com.br/ws/' + cep + '/json/')
                .then(r => r.json())
                .then(d => {
                    if (d.erro) return;
                    if (d.logradouro) document.getElementById('localAtendimentoRua').value = d.logradouro;
                    if (d.bairro) document.getElementById('localAtendimentoBairro').value = d.bairro;
                    if (d.localidade) document.getElementById('localAtendimentoCidade').value = d.localidade;
                    if (d.uf) document.getElementById('localAtendimentoEstado').value = d.uf;
                })
                .catch(() => {});
        });

        document.getElementById('addCartaoGatewaySelect')?.addEventListener('change', function() {
            const opt = this.selectedOptions[0];
            if (opt?.dataset.code === 'stripe' && opt?.dataset.pk) {
                _initAddCartaoStripe(opt.dataset.pk);
            }
        });

        function _initAddCartaoStripe(publishableKey) {
            const container = document.getElementById('addCartaoStripeElement');
            if (!container || typeof Stripe === 'undefined') return;

            try {
                container.innerHTML = '';
                _addCartaoStripeInstance = Stripe(publishableKey);
                const elements = _addCartaoStripeInstance.elements();
                _addCartaoCardElement = elements.create('card', {
                    style: { base: { fontSize: '14px', color: '#334155' }, invalid: { color: '#ef4444' } },
                });
                _addCartaoCardElement.mount('#addCartaoStripeElement');
            } catch (e) {
                console.error('Erro ao inicializar Stripe:', e);
                document.getElementById('addCartaoStripeError').textContent = layoutT('card.load_form_error');
                document.getElementById('addCartaoStripeError').classList.remove('hidden');
            }

            _addCartaoCardElement.on('change', function(ev) {
                const errEl = document.getElementById('addCartaoStripeError');
                const btn = document.getElementById('addCartaoSalvarBtn');
                if (ev.error) {
                    errEl.textContent = ev.error.message;
                    errEl.classList.remove('hidden');
                    btn.disabled = true;
                } else {
                    errEl.classList.add('hidden');
                    btn.disabled = !ev.complete;
                }
            });
        }

        window.salvarCartaoLocacaoModal = async function() {
            if (!_addCartaoStripeInstance || !_addCartaoCardElement || !_addCartaoClienteId) return;

            const gatewayId = document.getElementById('addCartaoGatewaySelect')?.value;
            if (!gatewayId) return;

            const btn = document.getElementById('addCartaoSalvarBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> ' + layoutT('buttons.save') + '...';

            try {
                // 1. Criar PaymentMethod via Stripe.js
                const { paymentMethod, error } = await _addCartaoStripeInstance.createPaymentMethod({
                    type: 'card',
                    card: _addCartaoCardElement,
                });

                if (error) {
                    openAlertModal(error.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save mr-1"></i> <?= t('common.buttons.save') ?>';
                    return;
                }

                // 2. Tokenizar no backend
                const tokenResult = await API.post('/api/clientes/' + _addCartaoClienteId + '/cartoes/tokenizar', {
                    gateway_id: gatewayId,
                    payment_method_id: paymentMethod.id,
                });

                if (!tokenResult.success) {
                    openAlertModal(tokenResult.message || layoutT('card.save_error'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save mr-1"></i> <?= t('common.buttons.save') ?>';
                    return;
                }

                // 3. Notificar iframe que o cartao foi salvo
                if (_addCartaoLocacaoSource) {
                    _addCartaoLocacaoSource.postMessage({
                        action: 'cartaoLocacaoSalvo',
                        id_cliente: _addCartaoClienteId,
                    }, '*');
                }

                closeAddCartaoLocacaoModal();

            } catch (e) {
                console.error('Erro:', e);
                openAlertModal(layoutT('card.save_error'));
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save mr-1"></i> <?= t('common.buttons.save') ?>';
        };

        // Fechar ao clicar no overlay
        document.getElementById('addCartaoLocacaoModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeAddCartaoLocacaoModal();
        });

        // ===== MODAIS CENTRAL DE MULTAS =====

        let _multasModalSource = null;
        let _stripeInstance = null;
        let _cardElement = null;

        async function _multasFetch(url, method = 'GET', body = null) {
            if (method === 'GET') {
                return API.get(url);
            }
            return API.post(url, body || {});
        }

        function _openModal(id) {
            document.getElementById(id).classList.add('open');
            document.body.classList.add('modal-open');
        }

        function _closeModal(id) {
            document.getElementById(id).classList.remove('open');
            document.body.classList.remove('modal-open');
        }

        // --- Consulta Individual ---
        window.openConsultaMultasModal = function(source) {
            _multasModalSource = source;
            document.getElementById('consultaFormContainer').classList.remove('hidden');
            document.getElementById('consultaLoadingContainer').classList.add('hidden');
            document.getElementById('consultaResultContainer').classList.add('hidden');
            document.getElementById('consultaPlacaInput').value = '';
            _openModal('consultaMultasModal');
        };

        window.closeConsultaMultasModal = function() {
            _closeModal('consultaMultasModal');
            _multasModalSource = null;
        };

        window.resetConsultaMultasModal = function() {
            document.getElementById('consultaResultContainer').classList.add('hidden');
            document.getElementById('consultaFormContainer').classList.remove('hidden');
            document.getElementById('consultaPlacaInput').value = '';
        };

        window.executarConsultaMultas = async function() {
            const placa = document.getElementById('consultaPlacaInput').value.trim().toUpperCase();
            if (!placa || placa.length < 7) {
                openAlertModal(layoutT('fines.invalid_plate'));
                return;
            }
            document.getElementById('consultaFormContainer').classList.add('hidden');
            document.getElementById('consultaLoadingContainer').classList.remove('hidden');
            try {
                const result = await _multasFetch('/api/multas-online/consultar-infracoes', 'POST', { placa });
                document.getElementById('consultaLoadingContainer').classList.add('hidden');
                if (result.success) {
                    document.getElementById('consultaResultContainer').classList.remove('hidden');
                    const total = result.data?.total_multas || 0;
                    const novas = result.data?.novas || 0;
                    document.getElementById('consultaResultMsg').textContent =
                        layoutT('fines.consult_result', { total: total, new: novas });
                    if (_multasModalSource) _multasModalSource.postMessage({ action: 'consultaMultasResult', success: true }, '*');
                } else {
                    document.getElementById('consultaFormContainer').classList.remove('hidden');
                    openAlertModal(result.message || layoutT('fines.consult_error'));
                }
            } catch (e) {
                document.getElementById('consultaLoadingContainer').classList.add('hidden');
                document.getElementById('consultaFormContainer').classList.remove('hidden');
                openAlertModal(e.message || layoutT('fines.consult_error'));
            }
        };

        document.getElementById('consultaMultasModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeConsultaMultasModal();
        });

        // --- Consulta Lote ---
        window.openConsultaLoteModal = function(data, source) {
            _multasModalSource = source;
            document.getElementById('loteInfoContainer').classList.remove('hidden');
            document.getElementById('loteLoadingContainer').classList.add('hidden');
            document.getElementById('loteResultContainer').classList.add('hidden');
            if (data.saldo !== undefined) {
                document.getElementById('loteModalSaldo').textContent = data.saldo;
            }
            _openModal('consultaLoteModal');
        };

        window.closeConsultaLoteModal = function() {
            _closeModal('consultaLoteModal');
            _multasModalSource = null;
        };

        window.executarConsultaLote = async function() {
            document.getElementById('loteInfoContainer').classList.add('hidden');
            document.getElementById('loteLoadingContainer').classList.remove('hidden');
            try {
                const result = await _multasFetch('/api/multas-online/consultar-lote', 'POST', {});
                document.getElementById('loteLoadingContainer').classList.add('hidden');
                if (result.success) {
                    document.getElementById('loteResultContainer').classList.remove('hidden');
                    const d = result.data;
                    document.getElementById('loteResultMsg').textContent =
                        layoutT('fines.batch_result', { plates: (d?.total_consultadas || 0), new: (d?.total_novas_multas || 0) });
                    if (_multasModalSource) _multasModalSource.postMessage({ action: 'consultaLoteResult', success: true }, '*');
                } else {
                    document.getElementById('loteInfoContainer').classList.remove('hidden');
                    openAlertModal(result.message || layoutT('fines.batch_error'));
                }
            } catch (e) {
                document.getElementById('loteLoadingContainer').classList.add('hidden');
                document.getElementById('loteInfoContainer').classList.remove('hidden');
                openAlertModal(e.message || layoutT('fines.batch_error'));
            }
        };

        document.getElementById('consultaLoteModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeConsultaLoteModal();
        });

        // --- PIX ---
        let _pixRecargaMinima = 100;

        window.openPixModal = function(data, source) {
            _multasModalSource = source;
            _pixRecargaMinima = data.recargaMinima || 100;
            const minFmt = formatLayoutCurrency(_pixRecargaMinima);
            document.getElementById('pixMinimoLabel').textContent = minFmt;
            Currency.applyMask('#pixValorInput');
            Currency.setValue('#pixValorInput', _pixRecargaMinima);
            document.getElementById('pixFormContainer').classList.remove('hidden');
            document.getElementById('pixLoadingContainer').classList.add('hidden');
            document.getElementById('pixQrcodeContainer').classList.add('hidden');
            _openModal('pixModal');
        };

        window.closePixModal = function() {
            _closeModal('pixModal');
            if (_multasModalSource) _multasModalSource.postMessage({ action: 'pixModalClosed' }, '*');
            _multasModalSource = null;
        };

        window.resetPixModal = function() {
            document.getElementById('pixQrcodeContainer').classList.add('hidden');
            document.getElementById('pixFormContainer').classList.remove('hidden');
        };

        window.executarGerarPix = async function() {
            const valor = Currency.getValue('#pixValorInput');
            if (!valor || valor < _pixRecargaMinima) {
                openAlertModal(layoutT('pix.min_error', { value: formatLayoutCurrency(_pixRecargaMinima) }));
                return;
            }
            document.getElementById('pixFormContainer').classList.add('hidden');
            document.getElementById('pixLoadingContainer').classList.remove('hidden');
            try {
                const result = await _multasFetch('/multas-online/saldo/recarregar-pix', 'POST', { valor });
                if (result.success && result.data) {
                    document.getElementById('pixLoadingContainer').classList.add('hidden');
                    document.getElementById('pixQrcodeContainer').classList.remove('hidden');
                    if (result.data.pix_qrcode) {
                        const img = document.getElementById('pixQrcodeImg');
                        img.src = result.data.pix_qrcode.startsWith('data:')
                            ? result.data.pix_qrcode
                            : 'data:image/png;base64,' + result.data.pix_qrcode;
                    }
                    document.getElementById('pixCodeText').value = result.data.pix_code || '';
                    if (_multasModalSource) _multasModalSource.postMessage({ action: 'pixRecargaResult', success: true }, '*');
                } else {
                    document.getElementById('pixLoadingContainer').classList.add('hidden');
                    document.getElementById('pixFormContainer').classList.remove('hidden');
                    openAlertModal(result.message || layoutT('pix.generate_error'));
                }
            } catch (e) {
                document.getElementById('pixLoadingContainer').classList.add('hidden');
                document.getElementById('pixFormContainer').classList.remove('hidden');
                openAlertModal(e.message || layoutT('pix.generate_error'));
            }
        };

        window.copiarCodigoPix = function() {
            const code = document.getElementById('pixCodeText').value;
            if (code) {
                navigator.clipboard.writeText(code).then(() => {
                    openAlertModal(layoutT('pix.copied'));
                });
            }
        };

        document.getElementById('pixModal')?.addEventListener('click', function(e) {
            if (e.target === this) closePixModal();
        });

        // --- Cartao (Stripe) ---
        let _cartaoRecargaMinima = 100;

        window.openCartaoModal = function(data, source) {
            _multasModalSource = source;
            _cartaoRecargaMinima = data.recargaMinima || 100;
            const minFmt = formatLayoutCurrency(_cartaoRecargaMinima);
            document.getElementById('cartaoMinimoLabel').textContent = minFmt;
            Currency.applyMask('#cartaoValorInput');
            Currency.setValue('#cartaoValorInput', _cartaoRecargaMinima);
            document.getElementById('cartaoFormContainer').classList.remove('hidden');
            document.getElementById('cartaoLoadingContainer').classList.add('hidden');
            document.getElementById('cartaoSucessoContainer').classList.add('hidden');
            document.getElementById('card-errors').textContent = '';

            // Abrir modal PRIMEIRO para que o container fique visivel
            _openModal('cartaoModal');

            // Inicializar Stripe DEPOIS (precisa do container visivel para renderizar)
            if (!_stripeInstance && typeof Stripe !== 'undefined' && data.stripePublicKey) {
                _stripeInstance = Stripe(data.stripePublicKey);
                const elements = _stripeInstance.elements();
                _cardElement = elements.create('card', {
                    style: {
                        base: { fontSize: '14px', color: '#334155' },
                        invalid: { color: '#ef4444' }
                    }
                });
                _cardElement.mount('#stripe-card-element');
                _cardElement.on('change', function(ev) {
                    document.getElementById('card-errors').textContent = ev.error ? ev.error.message : '';
                });
            }

            // Aviso se Stripe nao configurado
            if (!data.stripePublicKey) {
                document.getElementById('card-errors').textContent = layoutT('card.stripe_env_error');
            }
        };

        window.closeCartaoModal = function() {
            _closeModal('cartaoModal');
            if (_multasModalSource) _multasModalSource.postMessage({ action: 'cartaoModalClosed' }, '*');
            _multasModalSource = null;
        };

        window.executarPagarCartao = async function() {
            const valor = Currency.getValue('#cartaoValorInput');
            if (!valor || valor < _cartaoRecargaMinima) {
                openAlertModal(layoutT('pix.min_error', { value: formatLayoutCurrency(_cartaoRecargaMinima) }));
                return;
            }
            if (!_stripeInstance || !_cardElement) {
                openAlertModal(layoutT('card.stripe_not_configured'));
                return;
            }
            document.getElementById('cartaoFormContainer').classList.add('hidden');
            document.getElementById('cartaoLoadingContainer').classList.remove('hidden');
            try {
                const { paymentMethod, error: pmError } = await _stripeInstance.createPaymentMethod({
                    type: 'card', card: _cardElement
                });
                if (pmError) throw new Error(pmError.message);

                const salvarCartao = document.getElementById('salvarCartaoCheck').checked;
                const result = await _multasFetch('/multas-online/saldo/recarregar-stripe', 'POST', {
                    valor,
                    payment_method_id: paymentMethod.id,
                    salvar_cartao: salvarCartao ? 1 : 0
                });
                if (!result.success) throw new Error(result.message || layoutT('card.process_error'));

                if (result.data.requires_action && result.data.client_secret) {
                    const { paymentIntent, error: confirmError } = await _stripeInstance.confirmCardPayment(result.data.client_secret);
                    if (confirmError) throw new Error(confirmError.message);
                    await _multasFetch('/multas-online/saldo/confirmar-stripe', 'POST', { payment_intent_id: paymentIntent.id });
                }

                document.getElementById('cartaoLoadingContainer').classList.add('hidden');
                document.getElementById('cartaoSucessoContainer').classList.remove('hidden');
                document.getElementById('cartaoSucessoMsg').textContent =
                    layoutT('card.balance_added', { value: formatLayoutCurrency(valor) });
                if (_multasModalSource) _multasModalSource.postMessage({ action: 'cartaoRecargaResult', success: true }, '*');
            } catch (e) {
                document.getElementById('cartaoLoadingContainer').classList.add('hidden');
                document.getElementById('cartaoFormContainer').classList.remove('hidden');
                openAlertModal(e.message || layoutT('card.payment_error'));
            }
        };

        document.getElementById('cartaoModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeCartaoModal();
        });

        // --- Indicacao ---
        window.openIndicacaoModal = async function(data, source) {
            if (data && typeof data.postMessage === 'function' && !source) {
                source = data;
                data = {};
            }
            data = data || {};
            const bloqueiaTipoRealInfrator = !!data.id_multa;
            const tipoSelect = document.getElementById('indicacaoTipoSelect');
            _multasModalSource = source;
            document.getElementById('formIndicacaoModal').classList.remove('hidden');
            document.getElementById('indicacaoLoadingContainer').classList.add('hidden');
            tipoSelect.value = bloqueiaTipoRealInfrator ? 'real_infrator' : (data.tipo || 'real_infrator');
            tipoSelect.disabled = bloqueiaTipoRealInfrator;
            document.getElementById('indicacaoCpfInput').value = '';
            document.getElementById('indicacaoNomeInput').value = '';
            document.getElementById('indicacaoCnhInput').value = '';
            document.getElementById('indicacaoPlacaInput').value = '';
            toggleIndicacaoCampos();
            _openModal('indicacaoModal');

            // Carregar multas para select
            const select = document.getElementById('indicacaoSelectMulta');
            select.innerHTML = '<option value="">' + layoutT('indication.loading_fines') + '</option>';
            try {
                const result = await _multasFetch('/api/central-multas/multas?perPage=100&pago=N');
                select.innerHTML = '<option value="">' + layoutT('indication.select_fine') + '</option>';
                if (result.success && result.data) {
                    result.data.forEach(function(m) {
                        if (!m.codigo_orgao || !m.numero_ait || !m.codigo_infracao) return;
                        if (!/^\d+$/.test(String(m.codigo_orgao)) || !/^\d+$/.test(String(m.codigo_infracao))) return;
                        const placa = m.veiculo_placa || '';
                        const ait = m.n_infracao || m.numero_ait || '';
                        const valor = m.valor ? formatLayoutCurrency(parseFloat(m.valor)) : '';
                        const opt = document.createElement('option');
                        opt.value = m.id;
                        opt.textContent = placa + ' - ' + ait + ' ' + valor;
                        select.appendChild(opt);
                    });
                }
                if (data.id_multa) {
                    const selectedId = String(data.id_multa);
                    if (!Array.from(select.options).some(function(opt) { return opt.value === selectedId; })) {
                        const opt = document.createElement('option');
                        opt.value = selectedId;
                        opt.textContent = data.multa_label || ('#' + selectedId);
                        select.appendChild(opt);
                    }
                    select.value = selectedId;
                }
            } catch (e) {
                select.innerHTML = '<option value="">Erro ao carregar multas</option>';
                console.error('Erro ao carregar multas:', e);
            }
        };

        window.closeIndicacaoModal = function() {
            _closeModal('indicacaoModal');
            _multasModalSource = null;
        };

        window.toggleIndicacaoCampos = function() {
            const isRealInfrator = document.getElementById('indicacaoTipoSelect').value === 'real_infrator';
            document.getElementById('indicacaoCamposRealInfrator').classList.toggle('hidden', !isRealInfrator);
            document.getElementById('indicacaoCamposPrincipalCondutor').classList.toggle('hidden', isRealInfrator);
            document.getElementById('indicacaoCampoCnh').classList.toggle('hidden', isRealInfrator);
        };

        window.submitIndicacao = async function(e) {
            e.preventDefault();
            const tipo = document.getElementById('indicacaoTipoSelect').value;
            const cpf = document.getElementById('indicacaoCpfInput').value.trim();
            const nome = document.getElementById('indicacaoNomeInput').value.trim();

            if (!cpf) { openAlertModal(layoutT('indication.cpf_required')); return; }

            document.getElementById('formIndicacaoModal').classList.add('hidden');
            document.getElementById('indicacaoLoadingContainer').classList.remove('hidden');

            try {
                let result;
                if (tipo === 'real_infrator') {
                    const idMulta = document.getElementById('indicacaoSelectMulta').value;
                    if (!idMulta) throw new Error(layoutT('indication.fine_required'));
                    result = await _multasFetch('/multas-online/indicacoes/real-infrator', 'POST', { id_multa: idMulta, cpf_indicado: cpf, nome_indicado: nome });
                } else {
                    const placa = document.getElementById('indicacaoPlacaInput').value.trim();
                    const cnh = document.getElementById('indicacaoCnhInput').value.trim();
                    if (!placa) throw new Error(layoutT('indication.plate_required'));
                    if (!cnh) throw new Error(layoutT('indication.cnh_required'));
                    result = await _multasFetch('/multas-online/indicacoes/principal-condutor', 'POST', { placa, cpf_indicado: cpf, nome_indicado: nome, cnh_indicado: cnh });
                }

                if (result.success) {
                    closeIndicacaoModal();
                    const indicacaoMessage = { action: 'indicacaoResult', success: true, message: result.message || layoutT('indication.sent') };
                    if (_multasModalSource) _multasModalSource.postMessage(indicacaoMessage, '*');
                    document.querySelectorAll('iframe').forEach((iframe) => {
                        if (iframe.contentWindow && iframe.contentWindow !== _multasModalSource) {
                            iframe.contentWindow.postMessage(indicacaoMessage, '*');
                        }
                    });
                } else {
                    throw new Error(result.message);
                }
            } catch (err) {
                document.getElementById('indicacaoLoadingContainer').classList.add('hidden');
                document.getElementById('formIndicacaoModal').classList.remove('hidden');
                openAlertModal(err.message || layoutT('indication.send_error'));
            }
        };

        document.getElementById('indicacaoModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeIndicacaoModal();
        });

        // --- Instrucoes Indicacao ---
        window.openIndicacaoInstrucoes = function() {
            _openModal('indicacaoInstrucoesModal');
        };

        window.closeIndicacaoInstrucoes = function() {
            _closeModal('indicacaoInstrucoesModal');
        };

        document.getElementById('indicacaoInstrucoesModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeIndicacaoInstrucoes();
        });

        // ===== MODAL DE IMPRESSÃO FULLSCREEN =====
        window.openPrintModal = function(url, title = layoutT('print.title')) {
            const modal = document.getElementById('printModal');
            const iframe = document.getElementById('printModalIframe');
            const titleEl = document.getElementById('printModalTitle');
            const loading = document.getElementById('printModalLoading');

            if (!modal || !iframe) return;

            titleEl.textContent = title;

            // Mostrar loading enquanto PDF gera
            if (loading) loading.style.display = 'flex';
            iframe.style.display = 'none';

            iframe.onload = function() {
                if (loading) loading.style.display = 'none';
                iframe.style.display = 'block';
            };

            iframe.src = url;
            modal.classList.add('open');
            document.body.classList.add('modal-open');
        };

        window.closePrintModal = function() {
            const modal = document.getElementById('printModal');
            const iframe = document.getElementById('printModalIframe');

            if (!modal) return;

            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
            iframe.src = '';
        };

        window.executePrint = function() {
            const iframe = document.getElementById('printModalIframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.print();
            }
        };

        // Fechar modal de impressao ao clicar no overlay
        document.getElementById('printModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closePrintModal();
            }
        });

        // ===== MODAL DE ASSINATURA =====
        let assinaturaModalIframeSource = null;

        window.openAssinaturaModal = function(data, source) {
            assinaturaModalIframeSource = source;
            const tipo = data.tipo === 'contrato' ? layoutT('signature.contract') : layoutT('signature.rental');
            const preposicao = data.tipo === 'contrato' ? layoutT('signature.of_contract') : layoutT('signature.of_rental');

            document.getElementById('assinaturaModalTipoPreposicao').textContent = preposicao;
            document.getElementById('assinaturaModalTipoTitulo').textContent = tipo;
            document.getElementById('assinaturaModalTipoLabel').textContent = tipo;
            document.getElementById('assinaturaModalCodigo').textContent = data.codigo || '-';
            document.getElementById('assinaturaModalData').textContent = data.data_assinatura || '-';
            document.getElementById('assinaturaModalIP').textContent = data.ip || '-';
            document.getElementById('assinaturaModalImagem').src = data.url || '';
            document.getElementById('assinaturaModalContratoId').value = data.contratoId || '';
            document.getElementById('assinaturaModalLocacaoId').value = data.locacaoId || '';

            document.getElementById('assinaturaModal').classList.add('open');
            document.body.classList.add('modal-open');
        };

        window.closeAssinaturaModal = function() {
            document.getElementById('assinaturaModal').classList.remove('open');
            document.body.classList.remove('modal-open');
            assinaturaModalIframeSource = null;
        };

        window.resetarAssinaturaModal = function() {
            // Fechar modal de assinatura primeiro
            document.getElementById('assinaturaModal').classList.remove('open');

            // Abrir modal de confirmacao
            const modal = document.getElementById('genericConfirmModal');
            const modalTitle = document.getElementById('genericModalTitle');
            const modalMessage = document.getElementById('genericModalMessage');
            const confirmButton = document.getElementById('confirmGenericButton');

            if (!modal || !modalTitle || !modalMessage) return;

            modalTitle.textContent = layoutT('signature.reset_title');
            modalMessage.textContent = layoutT('signature.reset_message');
            confirmButton.textContent = layoutT('buttons.reset');
            confirmButton.className = 'btn-red py-2 px-4 rounded-md text-sm font-medium';

            // Guardar callback para quando confirmar
            window._pendingAssinaturaReset = true;

            modal.classList.add('open');
        };

        function executeAssinaturaReset() {
            if (assinaturaModalIframeSource) {
                const contratoId = document.getElementById('assinaturaModalContratoId').value;
                const locacaoId = document.getElementById('assinaturaModalLocacaoId').value;
                assinaturaModalIframeSource.postMessage({
                    action: 'resetarAssinatura',
                    contratoId: contratoId,
                    locacaoId: locacaoId
                }, '*');
            }
            closeAssinaturaModal();
        }

        // Fechar modal de assinatura ao clicar no overlay
        document.getElementById('assinaturaModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAssinaturaModal();
            }
        });

        /**
         * Abre o modal de validação de formulário
         */
        function openValidationModal(errors) {
            const modal = document.getElementById('validationModal');
            const errorsList = document.getElementById('validationErrorsList');

            if (!modal || !errorsList) return;

            // Renderizar lista de erros
            let html = '';
            errors.forEach(group => {
                html += `
                    <div class="validation-error-group">
                        <h4 class="validation-error-tab-name">
                            <i class="fas fa-folder-open"></i>
                            ${group.tabName}
                        </h4>
                        <ul class="validation-error-list">
                            ${group.fields.map(field => `
                                <li class="validation-error-item">
                                    <i class="fas fa-times-circle"></i>
                                    <span>${field}</span>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                `;
            });
            errorsList.innerHTML = html;

            modal.classList.add('open');
            document.body.classList.add('modal-open');
        }

        /**
         * Fecha o modal de validação e notifica o iframe
         */
        function closeValidationModal() {
            const modal = document.getElementById('validationModal');
            if (!modal) return;

            modal.classList.remove('open');
            document.body.classList.remove('modal-open');

            // Notificar iframe que o modal foi fechado
            const iframe = document.querySelector('.tab-content.active-content iframe');
            if (iframe?.contentWindow) {
                iframe.contentWindow.postMessage({ action: 'validationModalClosed' }, '*');
            }
        }

        // Event listener para fechar modal de validação
        document.getElementById('validationModalCloseBtn')?.addEventListener('click', closeValidationModal);

        // ===== MODAL DE ALERTA GLOBAL =====
        let alertModalCallback = null;
        let alertModalIframeSource = null;

        function openAlertModal(message, callbackAction, iframeSource) {
            const modal = document.getElementById('alertModal');
            const messageEl = document.getElementById('alertModalMessage');

            if (!modal || !messageEl) return;

            messageEl.textContent = message;
            alertModalCallback = callbackAction;
            alertModalIframeSource = iframeSource;

            modal.classList.add('open');
            document.body.classList.add('modal-open');
        }

        function closeAlertModal() {
            const modal = document.getElementById('alertModal');
            if (!modal) return;

            modal.classList.remove('open');
            document.body.classList.remove('modal-open');

            if (alertModalCallback && alertModalIframeSource) {
                alertModalIframeSource.postMessage({
                    action: 'alertModalClosed',
                    callback: alertModalCallback
                }, '*');
            }
            alertModalCallback = null;
            alertModalIframeSource = null;
        }

        document.getElementById('alertModalOkBtn')?.addEventListener('click', closeAlertModal);

        // Fechar modal de alerta ao clicar fora
        document.getElementById('alertModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAlertModal();
            }
        });

        // ===== MODAL DE LINK DE PAGAMENTO =====
        function openLinkModal(url) {
            const modal = document.getElementById('linkModal');
            const urlInput = document.getElementById('linkModalUrl');
            if (!modal || !urlInput) return;

            urlInput.value = url;
            modal.classList.add('open');
            document.body.classList.add('modal-open');
        }

        function closeLinkModal() {
            const modal = document.getElementById('linkModal');
            if (!modal) return;

            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
        }

        document.getElementById('linkModalCopyBtn')?.addEventListener('click', function() {
            const url = document.getElementById('linkModalUrl').value;
            navigator.clipboard.writeText(url).then(() => {
                this.innerHTML = '<i class="fas fa-check mr-1"></i>' + layoutT('buttons.copied');
                setTimeout(() => {
                    this.innerHTML = '<i class="fas fa-copy mr-1"></i>' + layoutT('buttons.copy');
                }, 2000);
            });
        });

        document.getElementById('linkModalOpenBtn')?.addEventListener('click', function() {
            const url = document.getElementById('linkModalUrl').value;
            window.open(url, '_blank');
        });

        document.getElementById('linkModalCloseBtn')?.addEventListener('click', closeLinkModal);

        // Fechar modal de link ao clicar fora
        document.getElementById('linkModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeLinkModal();
            }
        });

        // ===== MODAL DE LINK DE ASSINATURA =====
        let signatureLinkModalData = null;

        function openSignatureLinkModal(data) {
            const modal = document.getElementById('signatureLinkModal');
            const urlInput = document.getElementById('signatureLinkModalUrl');
            const codigoEl = document.getElementById('signatureLinkModalCodigo');
            const tipoEl = document.getElementById('signatureLinkModalTipo');
            if (!modal || !urlInput || !codigoEl || !tipoEl) return;

            const tipo = data?.tipo === 'contrato' ? layoutT('signature.contract') : layoutT('signature.rental');
            signatureLinkModalData = data || null;
            tipoEl.textContent = tipo;
            codigoEl.textContent = data?.codigo || '-';
            urlInput.value = data?.url || '';
            modal.classList.add('open');
            document.body.classList.add('modal-open');
        }

        function closeSignatureLinkModal() {
            const modal = document.getElementById('signatureLinkModal');
            if (!modal) return;

            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
            signatureLinkModalData = null;
        }

        document.getElementById('signatureLinkModalWhatsappBtn')?.addEventListener('click', async function() {
            if (!signatureLinkModalData?.id || !signatureLinkModalData?.tipo) return;

            const endpointBase = signatureLinkModalData.tipo === 'contrato' ? '/contratos/' : '/locacoes/';
            const endpoint = endpointBase + signatureLinkModalData.id + '/enviar-link-assinatura';
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>' + layoutT('links.sending');

            try {
                const result = await API.post(endpoint, {
                    url: document.getElementById('signatureLinkModalUrl').value
                });
                openAlertModal(result.message || (result.success ? layoutT('links.sent') : layoutT('links.send_error')));
            } catch (e) {
                openAlertModal(layoutT('links.whatsapp_error'));
            } finally {
                this.disabled = false;
                this.innerHTML = originalHtml;
            }
        });

        document.getElementById('signatureLinkModalCopyBtn')?.addEventListener('click', function() {
            const url = document.getElementById('signatureLinkModalUrl').value;
            navigator.clipboard.writeText(url).then(() => {
                this.innerHTML = '<i class="fas fa-check mr-1"></i>' + layoutT('buttons.copied');
                setTimeout(() => {
                    this.innerHTML = '<i class="fas fa-copy mr-1"></i>' + layoutT('buttons.copy');
                }, 2000);
            });
        });

        document.getElementById('signatureLinkModalOpenBtn')?.addEventListener('click', function() {
            const url = document.getElementById('signatureLinkModalUrl').value;
            window.open(url, '_blank');
        });

        document.getElementById('signatureLinkModalCloseBtn')?.addEventListener('click', closeSignatureLinkModal);

        document.getElementById('signatureLinkModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeSignatureLinkModal();
            }
        });

        // ===== MODAL DE SESSÃO EXPIRADA =====
        function openSessionExpiredModal() {
            const modal = document.getElementById('sessionExpiredModal');
            if (!modal) return;
            modal.classList.add('open');
            document.body.classList.add('modal-open');
        }

        document.getElementById('sessionExpiredReloadBtn')?.addEventListener('click', function() {
            window.location.reload();
        });

        // Fechar modal de validação ao clicar fora
        document.getElementById('validationModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeValidationModal();
            }
        });

        // Fechar modais com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const printModal = document.getElementById('printModal');
                if (printModal && printModal.classList.contains('open')) {
                    closePrintModal();
                    return;
                }
                const validationModal = document.getElementById('validationModal');
                if (validationModal && validationModal.classList.contains('open')) {
                    closeValidationModal();
                    return;
                }
                const videoModal = document.getElementById('videoModal');
                if (videoModal && videoModal.classList.contains('open')) {
                    closeVideoModal();
                    return;
                }
            }
        });

        // Variável global para armazenar referência do iframe
        let globalFotoModalIframeSource = null;

        // Função para abrir modal de escolha de foto no documento pai
        function abrirModalFotoEscolha(iframeSource) {
            // Armazenar referência do iframe
            globalFotoModalIframeSource = iframeSource;
            
            // Remover modal existente se houver
            const existingModal = document.getElementById('globalFotoModalEscolha');
            if (existingModal) {
                existingModal.remove();
            }

            // Criar modal no documento pai
            const modal = document.createElement('div');
            modal.id = 'globalFotoModalEscolha';
            modal.className = 'modal-overlay open';
            modal.innerHTML = `
                <div class="modal-box" style="max-width: 400px;">
                    <h3 class="modal-title">${layoutT('photo.choose_title')}</h3>
                    <p class="modal-message">${layoutT('photo.choose_message')}</p>
                    <div class="modal-actions" style="justify-content: center; gap: 1rem; margin-top: 1.5rem;">
                        <button type="button" id="globalBtnEnviarArquivo" class="btn-blue py-2 px-6 rounded-md text-sm font-medium">
                            <i class="fas fa-upload mr-2"></i>${layoutT('photo.upload_file')}
                        </button>
                        <button type="button" id="globalBtnUsarCamera" class="btn-green py-2 px-6 rounded-md text-sm font-medium">
                            <i class="fas fa-camera mr-2"></i>${layoutT('photo.use_camera')}
                        </button>
                    </div>
                    <div class="modal-actions" style="margin-top: 1rem;">
                        <button type="button" id="globalBtnCancelarEscolhaFoto" class="btn-secondary">
                            ${layoutT('buttons.cancel')}
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            document.body.classList.add('modal-open');

            // Event listeners
            document.getElementById('globalBtnEnviarArquivo').addEventListener('click', function() {
                if (globalFotoModalIframeSource) {
                    globalFotoModalIframeSource.postMessage({
                        action: 'fotoModalActionResponse',
                        modalAction: 'enviarArquivo'
                    }, '*');
                }
                fecharModalFotoEscolha();
            });

            document.getElementById('globalBtnUsarCamera').addEventListener('click', function() {
                if (globalFotoModalIframeSource) {
                    globalFotoModalIframeSource.postMessage({
                        action: 'fotoModalActionResponse',
                        modalAction: 'usarCamera'
                    }, '*');
                }
                fecharModalFotoEscolha();
            });

            document.getElementById('globalBtnCancelarEscolhaFoto').addEventListener('click', function() {
                fecharModalFotoEscolha();
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    fecharModalFotoEscolha();
                }
            });
        }

        // Função para fechar modal de escolha de foto
        function fecharModalFotoEscolha() {
            const modal = document.getElementById('globalFotoModalEscolha');
            if (modal) {
                modal.remove();
                document.body.classList.remove('modal-open');
                globalFotoModalIframeSource = null;
            }
        }

        // ===== MODAIS DE DOCUMENTO (Upload/Escolha) =====
        let globalDocumentoModalIframeSource = null;

        // Função para abrir modal de escolha de criação de documento
        function abrirModalDocumentoEscolha(iframeSource) {
            globalDocumentoModalIframeSource = iframeSource;

            // Remover modal existente se houver
            const existingModal = document.getElementById('globalDocumentoModalEscolha');
            if (existingModal) existingModal.remove();

            // Criar modal no documento pai
            const modal = document.createElement('div');
            modal.id = 'globalDocumentoModalEscolha';
            modal.className = 'modal-overlay open';
            modal.innerHTML = `
                <div class="modal-box" style="max-width: 500px;">
                    <h3 class="modal-title">${layoutT('document.create_title')}</h3>
                    <p class="modal-message">${layoutT('document.create_message')}</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem;">
                        <div id="globalDocBtnUpload" style="border: 2px solid #e2e8f0; border-radius: 0.75rem; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.2s ease;">
                            <i class="fas fa-file-upload" style="font-size: 2.5rem; color: #3b82f6; margin-bottom: 0.75rem; display: block;"></i>
                            <div style="font-weight: 600; color: #334155; margin-bottom: 0.25rem;">${layoutT('document.import_file')}</div>
                            <div style="font-size: 0.75rem; color: #64748b;">${layoutT('document.pdf_or_word')}</div>
                        </div>
                        <div id="globalDocBtnManual" style="border: 2px solid #e2e8f0; border-radius: 0.75rem; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.2s ease;">
                            <i class="fas fa-edit" style="font-size: 2.5rem; color: #10b981; margin-bottom: 0.75rem; display: block;"></i>
                            <div style="font-weight: 600; color: #334155; margin-bottom: 0.25rem;">${layoutT('document.create_manually')}</div>
                            <div style="font-size: 0.75rem; color: #64748b;">${layoutT('document.text_editor')}</div>
                        </div>
                    </div>
                    <div class="modal-actions" style="margin-top: 1.5rem;">
                        <button type="button" id="globalDocBtnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                            <i class="fas fa-arrow-left mr-2"></i>${layoutT('buttons.back')}
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            document.body.classList.add('modal-open');

            // Hover effects
            const btnUpload = document.getElementById('globalDocBtnUpload');
            const btnManual = document.getElementById('globalDocBtnManual');

            btnUpload.addEventListener('mouseenter', () => { btnUpload.style.borderColor = '#3b82f6'; btnUpload.style.background = '#eff6ff'; });
            btnUpload.addEventListener('mouseleave', () => { btnUpload.style.borderColor = '#e2e8f0'; btnUpload.style.background = ''; });
            btnManual.addEventListener('mouseenter', () => { btnManual.style.borderColor = '#10b981'; btnManual.style.background = '#ecfdf5'; });
            btnManual.addEventListener('mouseleave', () => { btnManual.style.borderColor = '#e2e8f0'; btnManual.style.background = ''; });

            // Event listeners
            btnUpload.addEventListener('click', function() {
                fecharModalDocumento();
                abrirModalDocumentoUpload(globalDocumentoModalIframeSource);
            });

            btnManual.addEventListener('click', function() {
                if (globalDocumentoModalIframeSource) {
                    globalDocumentoModalIframeSource.postMessage({ action: 'documentoEscolhaManual' }, '*');
                }
                fecharModalDocumento();
            });

            document.getElementById('globalDocBtnVoltar').addEventListener('click', function() {
                if (globalDocumentoModalIframeSource) {
                    globalDocumentoModalIframeSource.postMessage({ action: 'documentoEscolhaVoltar' }, '*');
                }
                fecharModalDocumento();
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    if (globalDocumentoModalIframeSource) {
                        globalDocumentoModalIframeSource.postMessage({ action: 'documentoEscolhaVoltar' }, '*');
                    }
                    fecharModalDocumento();
                }
            });
        }

        // Função para abrir modal de upload de documento
        function abrirModalDocumentoUpload(iframeSource) {
            globalDocumentoModalIframeSource = iframeSource;

            // Remover modal existente se houver
            const existingModal = document.getElementById('globalDocumentoModalUpload');
            if (existingModal) existingModal.remove();

            // Criar modal no documento pai
            const modal = document.createElement('div');
            modal.id = 'globalDocumentoModalUpload';
            modal.className = 'modal-overlay open';
            modal.innerHTML = `
                <div class="modal-box" style="max-width: 500px;">
                    <h3 class="modal-title">${layoutT('document.import_title')}</h3>
                    <div id="globalDocDropZone" style="border: 2px dashed #cbd5e1; border-radius: 0.75rem; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.2s ease;">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #94a3b8; margin-bottom: 0.75rem; display: block;"></i>
                        <p style="color: #475569; font-weight: 500;">${layoutT('document.drop_file')}</p>
                        <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.5rem;">${layoutT('document.click_select')}</p>
                        <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">${layoutT('document.max_size')}</p>
                        <input type="file" id="globalDocFileInput" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" style="display: none;">
                    </div>
                    <div id="globalDocUploadProgress" style="display: none; margin-top: 1rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span id="globalDocFileName" style="font-size: 0.875rem; color: #475569; max-width: 80%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                            <span id="globalDocUploadStatus" style="font-size: 0.875rem; font-weight: 500; color: #3b82f6;">${layoutT('document.processing')}</span>
                        </div>
                        <div style="width: 100%; background: #e2e8f0; border-radius: 9999px; height: 0.5rem;">
                            <div id="globalDocUploadBar" style="background: #3b82f6; height: 100%; border-radius: 9999px; width: 0%; transition: width 0.3s;"></div>
                        </div>
                    </div>
                    <div class="modal-actions" style="margin-top: 1.5rem;">
                        <button type="button" id="globalDocBtnVoltarUpload" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                            <i class="fas fa-arrow-left mr-2"></i>${layoutT('buttons.back')}
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            document.body.classList.add('modal-open');

            const dropZone = document.getElementById('globalDocDropZone');
            const fileInput = document.getElementById('globalDocFileInput');

            // Drag & Drop
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropZone.style.borderColor = '#3b82f6';
                dropZone.style.background = '#eff6ff';
            });

            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropZone.style.borderColor = '#cbd5e1';
                dropZone.style.background = '';
            });

            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropZone.style.borderColor = '#cbd5e1';
                dropZone.style.background = '';
                const file = e.dataTransfer.files[0];
                if (file) processDocumentoUpload(file);
            });

            dropZone.addEventListener('click', function() {
                fileInput.click();
            });

            fileInput.addEventListener('change', function() {
                if (this.files[0]) processDocumentoUpload(this.files[0]);
            });

            // Voltar para modal de escolha
            document.getElementById('globalDocBtnVoltarUpload').addEventListener('click', function() {
                fecharModalDocumento();
                abrirModalDocumentoEscolha(globalDocumentoModalIframeSource);
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    fecharModalDocumento();
                    abrirModalDocumentoEscolha(globalDocumentoModalIframeSource);
                }
            });
        }

        // Processa upload de documento
        async function processDocumentoUpload(file) {
            const allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            const allowedExtensions = ['pdf', 'docx'];
            const maxSize = 10 * 1024 * 1024;
            const extension = file.name.split('.').pop().toLowerCase();

            // Validação client-side
            if (!allowedExtensions.includes(extension)) {
                openAlertModal(layoutT('document.invalid_type'));
                return;
            }
            if (file.size > maxSize) {
                openAlertModal(layoutT('document.too_large'));
                return;
            }

            // Mostrar progresso
            const uploadProgress = document.getElementById('globalDocUploadProgress');
            const uploadFileName = document.getElementById('globalDocFileName');
            const uploadStatus = document.getElementById('globalDocUploadStatus');
            const uploadBar = document.getElementById('globalDocUploadBar');

            if (uploadProgress) uploadProgress.style.display = 'block';
            if (uploadFileName) uploadFileName.textContent = file.name;
            if (uploadStatus) { uploadStatus.textContent = layoutT('document.processing'); uploadStatus.style.color = '#3b82f6'; }
            if (uploadBar) uploadBar.style.width = '30%';

            const formData = new FormData();
            formData.append('arquivo', file);

            try {
                if (uploadBar) uploadBar.style.width = '60%';

                const result = await API.postForm('/api/documentos/extrair-texto', formData);

                if (uploadBar) uploadBar.style.width = '100%';

                if (result.success) {
                    if (uploadStatus) { uploadStatus.textContent = layoutT('document.done'); uploadStatus.style.color = '#10b981'; }

                    setTimeout(() => {
                        if (globalDocumentoModalIframeSource) {
                            globalDocumentoModalIframeSource.postMessage({
                                action: 'documentoUploadSuccess',
                                html: result.data.html,
                                filename: file.name
                            }, '*');
                        }
                        fecharModalDocumento();
                    }, 500);
                } else {
                    if (uploadStatus) { uploadStatus.textContent = layoutT('document.error'); uploadStatus.style.color = '#ef4444'; }
                    openAlertModal(result.message || layoutT('document.process_error'));
                    setTimeout(() => {
                        if (uploadProgress) uploadProgress.style.display = 'none';
                        if (uploadBar) uploadBar.style.width = '0%';
                    }, 2000);
                }
            } catch (error) {
                console.error('Erro no upload:', error);
                if (uploadStatus) { uploadStatus.textContent = layoutT('document.error'); uploadStatus.style.color = '#ef4444'; }
                openAlertModal(layoutT('document.upload_error'));
                setTimeout(() => {
                    if (uploadProgress) uploadProgress.style.display = 'none';
                    if (uploadBar) uploadBar.style.width = '0%';
                }, 2000);
            }
        }

        // Função para fechar modal de documento
        function fecharModalDocumento() {
            const modalEscolha = document.getElementById('globalDocumentoModalEscolha');
            const modalUpload = document.getElementById('globalDocumentoModalUpload');
            if (modalEscolha) modalEscolha.remove();
            if (modalUpload) modalUpload.remove();
            document.body.classList.remove('modal-open');
        }

        // Variável global para armazenar referência do iframe da câmera
        let globalCameraIframeSource = null;
        let globalStreamCamera = null;

        // Função para abrir modal da câmera no documento pai
        function abrirModalCamera(iframeSource) {
            // Armazenar referência do iframe
            globalCameraIframeSource = iframeSource;

            // Remover modal existente se houver
            const existingModal = document.getElementById('globalCameraModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Criar modal da câmera no documento pai
            const modal = document.createElement('div');
            modal.id = 'globalCameraModal';
            modal.className = 'modal-overlay open';
            modal.innerHTML = `
                <div class="modal-box" style="max-width: 500px;">
                    <h3 class="modal-title">${layoutT('photo.take_title')}</h3>
                    <div style="margin-bottom: 1rem; display: flex; justify-content: center; align-items: center; background: #000; border-radius: 0.5rem; overflow: hidden;">
                        <video id="globalVideoCamera" autoplay playsinline style="width: 100%; max-height: 400px; display: block;"></video>
                        <canvas id="globalCanvasCamera" style="display: none;"></canvas>
                    </div>
                    <div class="modal-actions">
                        <button type="button" id="globalBtnCapturarFoto" class="btn-blue py-2 px-6 rounded-md text-sm font-medium">
                            <i class="fas fa-camera mr-2"></i>${layoutT('buttons.capture')}
                        </button>
                        <button type="button" id="globalBtnCancelarCamera" class="btn-secondary">
                            ${layoutT('buttons.cancel')}
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            document.body.classList.add('modal-open');

            // Iniciar câmera
            iniciarCameraGlobal();

            // Event listeners
            document.getElementById('globalBtnCapturarFoto').addEventListener('click', function() {
                capturarFotoGlobal();
            });

            document.getElementById('globalBtnCancelarCamera').addEventListener('click', function() {
                fecharModalCamera();
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    fecharModalCamera();
                }
            });
        }

        // Função para iniciar a câmera global
        function iniciarCameraGlobal() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                openAlertModal(layoutT('camera.not_supported'));
                fecharModalCamera();
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
                    globalStreamCamera = stream;
                    const videoElement = document.getElementById('globalVideoCamera');
                    if (videoElement) {
                        videoElement.srcObject = stream;
                    }
                })
                .catch(function(err) {
                    console.error('Erro ao acessar câmera:', err);
                    let mensagem = layoutT('camera.not_supported');
                    if (err.name === 'NotAllowedError') {
                        mensagem = layoutT('camera.not_allowed');
                    } else if (err.name === 'NotFoundError') {
                        mensagem = layoutT('camera.not_found');
                    }
                    openAlertModal(mensagem);
                    fecharModalCamera();
                });
        }

        // Função para capturar foto da câmera global
        function capturarFotoGlobal() {
            const videoElement = document.getElementById('globalVideoCamera');
            const canvasElement = document.getElementById('globalCanvasCamera');

            if (!videoElement || !videoElement.videoWidth || !videoElement.videoHeight) {
                openAlertModal(layoutT('camera.init_wait'));
                return;
            }

            const context = canvasElement.getContext('2d');
            canvasElement.width = videoElement.videoWidth;
            canvasElement.height = videoElement.videoHeight;

            // Desenhar a imagem do vídeo no canvas
            context.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);

            // Converter para base64 (JPEG com qualidade 0.9)
            const fotoBase64 = canvasElement.toDataURL('image/jpeg', 0.9);

            // Enviar foto de volta para o iframe
            if (globalCameraIframeSource) {
                globalCameraIframeSource.postMessage({
                    action: 'cameraPhotoResponse',
                    fotoBase64: fotoBase64
                }, '*');
            }

            // Fechar modal da câmera
            fecharModalCamera();
        }

        // Função para fechar modal da câmera
        function fecharModalCamera() {
            // Parar stream da câmera
            if (globalStreamCamera) {
                globalStreamCamera.getTracks().forEach(track => track.stop());
                globalStreamCamera = null;
            }

            const modal = document.getElementById('globalCameraModal');
            if (modal) {
                const videoElement = document.getElementById('globalVideoCamera');
                if (videoElement) {
                    videoElement.srcObject = null;
                }
                modal.remove();
                document.body.classList.remove('modal-open');
            }
            globalCameraIframeSource = null;
        }

        // ===== MODAL DE CÂMERA PARA ARQUIVOS (COM SELETOR DE CÂMERA) =====
        let globalCameraArquivoIframeSource = null;
        let globalStreamCameraArquivo = null;
        let globalCamerasDisponiveis = [];
        let globalArquivoTipo = null;
        let globalArquivoNome = null;

        // Função para abrir modal da câmera para arquivos
        function abrirModalCameraArquivo(iframeSource, arquivoTipo, arquivoNome) {
            globalCameraArquivoIframeSource = iframeSource;
            globalArquivoTipo = arquivoTipo;
            globalArquivoNome = arquivoNome;

            // Remover modal existente se houver
            const existingModal = document.getElementById('globalCameraArquivoModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Criar modal da câmera no documento pai
            const modal = document.createElement('div');
            modal.id = 'globalCameraArquivoModal';
            modal.className = 'modal-overlay open';
            modal.innerHTML = `
                <div class="modal-box" style="max-width: 600px;">
                    <h3 class="modal-title">${layoutT('camera.file_title')}</h3>
                    <div class="mb-4">
                        <label for="globalSelectCameraArquivo" class="form-label-group">${layoutT('camera.camera')}</label>
                        <select id="globalSelectCameraArquivo" class="form-input-group-field">
                            <option value="">${layoutT('camera.loading')}</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 1rem; display: flex; justify-content: center; align-items: center; background: #000; border-radius: 0.5rem; overflow: hidden; min-height: 300px;">
                        <video id="globalVideoCameraArquivo" autoplay playsinline style="width: 100%; max-height: 450px; display: block;"></video>
                        <canvas id="globalCanvasCameraArquivo" style="display: none;"></canvas>
                    </div>
                    <div class="modal-actions">
                        <button type="button" id="globalBtnCapturarArquivo" class="btn-blue py-2 px-6 rounded-md text-sm font-medium">
                            <i class="fas fa-camera mr-2"></i>${layoutT('buttons.capture')}
                        </button>
                        <button type="button" id="globalBtnCancelarCameraArquivo" class="btn-secondary">
                            ${layoutT('buttons.cancel')}
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            document.body.classList.add('modal-open');

            // Listar câmeras disponíveis
            listarCamerasDisponiveisGlobal();

            // Event listeners
            document.getElementById('globalSelectCameraArquivo').addEventListener('change', function() {
                if (this.value) {
                    iniciarCameraArquivoGlobal(this.value);
                }
            });

            document.getElementById('globalBtnCapturarArquivo').addEventListener('click', function() {
                capturarFotoArquivoGlobal();
            });

            document.getElementById('globalBtnCancelarCameraArquivo').addEventListener('click', function() {
                fecharModalCameraArquivo();
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    fecharModalCameraArquivo();
                }
            });
        }

        // Função para listar câmeras disponíveis
        async function listarCamerasDisponiveisGlobal() {
            const selectElement = document.getElementById('globalSelectCameraArquivo');

            try {
                // Solicitar permissão primeiro
                await navigator.mediaDevices.getUserMedia({ video: true });

                const devices = await navigator.mediaDevices.enumerateDevices();
                globalCamerasDisponiveis = devices.filter(device => device.kind === 'videoinput');

                selectElement.innerHTML = '';
                if (globalCamerasDisponiveis.length === 0) {
                    selectElement.innerHTML = '<option value="">' + layoutT('camera.none') + '</option>';
                    return;
                }

                globalCamerasDisponiveis.forEach((camera, idx) => {
                    const option = document.createElement('option');
                    option.value = camera.deviceId;
                    option.textContent = camera.label || (layoutT('camera.camera') + ' ' + (idx + 1));
                    selectElement.appendChild(option);
                });

                // Iniciar com a primeira câmera
                if (globalCamerasDisponiveis.length > 0) {
                    await iniciarCameraArquivoGlobal(globalCamerasDisponiveis[0].deviceId);
                }
            } catch (err) {
                console.error('Erro ao listar cameras:', err);
                if (err.name === 'NotAllowedError') {
                    openAlertModal(layoutT('camera.permission_denied'));
                } else {
                    openAlertModal(layoutT('camera.access_error', { message: err.message }));
                }
                fecharModalCameraArquivo();
            }
        }

        // Função para iniciar câmera específica
        async function iniciarCameraArquivoGlobal(deviceId) {
            // Parar stream anterior se existir
            if (globalStreamCameraArquivo) {
                globalStreamCameraArquivo.getTracks().forEach(track => track.stop());
            }

            const videoElement = document.getElementById('globalVideoCameraArquivo');
            if (!videoElement) return;

            try {
                const constraints = {
                    video: {
                        deviceId: deviceId ? { exact: deviceId } : undefined,
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };

                globalStreamCameraArquivo = await navigator.mediaDevices.getUserMedia(constraints);
                videoElement.srcObject = globalStreamCameraArquivo;
            } catch (err) {
                console.error('Erro ao iniciar camera:', err);
                openAlertModal(layoutT('camera.start_error', { message: err.message }));
            }
        }

        // Função para capturar foto da câmera de arquivos
        function capturarFotoArquivoGlobal() {
            const videoElement = document.getElementById('globalVideoCameraArquivo');
            const canvasElement = document.getElementById('globalCanvasCameraArquivo');

            if (!videoElement || !videoElement.videoWidth || !videoElement.videoHeight) {
                openAlertModal(layoutT('camera.init_wait'));
                return;
            }

            const context = canvasElement.getContext('2d');
            canvasElement.width = videoElement.videoWidth;
            canvasElement.height = videoElement.videoHeight;

            // Desenhar a imagem do vídeo no canvas
            context.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);

            // Converter para base64 (JPEG com qualidade 0.9)
            const fotoBase64 = canvasElement.toDataURL('image/jpeg', 0.9);

            // Enviar foto de volta para o iframe
            if (globalCameraArquivoIframeSource) {
                globalCameraArquivoIframeSource.postMessage({
                    action: 'cameraArquivoPhotoResponse',
                    fotoBase64: fotoBase64,
                    arquivoTipo: globalArquivoTipo,
                    arquivoNome: globalArquivoNome
                }, '*');
            }

            // Fechar modal da câmera
            fecharModalCameraArquivo();
        }

        // Função para fechar modal da câmera de arquivos
        function fecharModalCameraArquivo() {
            // Parar stream da câmera
            if (globalStreamCameraArquivo) {
                globalStreamCameraArquivo.getTracks().forEach(track => track.stop());
                globalStreamCameraArquivo = null;
            }

            const modal = document.getElementById('globalCameraArquivoModal');
            if (modal) {
                const videoElement = document.getElementById('globalVideoCameraArquivo');
                if (videoElement) {
                    videoElement.srcObject = null;
                }
                modal.remove();
                document.body.classList.remove('modal-open');
            }
            globalCameraArquivoIframeSource = null;
            globalArquivoTipo = null;
            globalArquivoNome = null;
        }

        // ===== MODAL DE VIDEO =====
        function openVideoModal(videoUrl) {
            const modal = document.getElementById('videoModal');
            const player = document.getElementById('videoPlayer');

            if (!modal || !player) return;

            player.src = videoUrl;
            modal.classList.add('open');
            document.body.classList.add('modal-open');
        }

        function closeVideoModal() {
            const modal = document.getElementById('videoModal');
            const player = document.getElementById('videoPlayer');

            if (player) {
                player.pause();
                player.src = '';
            }

            if (modal) {
                modal.classList.remove('open');
                document.body.classList.remove('modal-open');
            }
        }

        // Event listeners do modal de video
        document.getElementById('closeVideoModalBtn')?.addEventListener('click', closeVideoModal);
        document.getElementById('closeVideoModalBtnBottom')?.addEventListener('click', closeVideoModal);
        document.getElementById('videoModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeVideoModal();
            }
        });

        // ===== MODAL DE CHANGELOG =====
        let changelogIframeSource = null;

        function openChangelogModal(dados, iframeSource) {
            changelogIframeSource = iframeSource;

            const modal = document.getElementById('changelogModal');
            const title = document.getElementById('changelogModalTitle');
            const idInput = document.getElementById('changelogModalId');
            const versaoInput = document.getElementById('changelogModalVersao');
            const tipoSelect = document.getElementById('changelogModalTipo');
            const dataInput = document.getElementById('changelogModalData');
            const mensagemTextarea = document.getElementById('changelogModalMensagem');
            const contador = document.getElementById('changelogModalContador');

            if (!modal) return;

            // Preencher formulário
            if (dados && dados.id) {
                title.textContent = layoutT('changelog.edit_title');
                idInput.value = dados.id;
                versaoInput.value = dados.versao || '';
                tipoSelect.value = dados.tipo || '';
                dataInput.value = dados.data || '';
                mensagemTextarea.value = dados.mensagem || '';
            } else {
                title.textContent = layoutT('changelog.new_title');
                idInput.value = '';
                versaoInput.value = dados?.versao || '';
                tipoSelect.value = '';
                dataInput.value = new Date().toISOString().split('T')[0];
                mensagemTextarea.value = '';
            }

            contador.textContent = mensagemTextarea.value.length;

            modal.classList.add('open');
            document.body.classList.add('modal-open');
            versaoInput.focus();
        }

        function closeChangelogModal() {
            const modal = document.getElementById('changelogModal');
            if (modal) {
                modal.classList.remove('open');
                document.body.classList.remove('modal-open');
            }
            changelogIframeSource = null;
        }

        async function saveChangelog(e) {
            e.preventDefault();

            const id = document.getElementById('changelogModalId').value;
            const dados = {
                versao: document.getElementById('changelogModalVersao').value.trim(),
                tipo: document.getElementById('changelogModalTipo').value,
                data: document.getElementById('changelogModalData').value,
                mensagem: document.getElementById('changelogModalMensagem').value.trim(),
            };

            try {
                const url = id ? `/api/changelog/${id}/atualizar` : '/api/changelog';
                const result = await API.post(url, dados);

                if (result.success) {
                    // Notificar iframe para recarregar dados
                    if (changelogIframeSource) {
                        changelogIframeSource.postMessage({ action: 'changelogSaved' }, '*');
                    }
                    closeChangelogModal();
                } else {
                    openAlertModal(result.message || layoutT('changelog.save_error'));
                }
            } catch (error) {
                console.error('Erro ao salvar changelog:', error);
                openAlertModal(layoutT('changelog.save_try_error'));
            }
        }

        // Event listeners do modal de changelog
        document.getElementById('closeChangelogModalBtn')?.addEventListener('click', closeChangelogModal);
        document.getElementById('cancelChangelogModalBtn')?.addEventListener('click', closeChangelogModal);
        document.getElementById('changelogModalForm')?.addEventListener('submit', saveChangelog);
        document.getElementById('changelogModalMensagem')?.addEventListener('input', function() {
            document.getElementById('changelogModalContador').textContent = this.value.length;
        });

        // Fechar modal ao clicar fora
        document.getElementById('changelogModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeChangelogModal();
            }
        });

        // ===== OFFCANVAS FORM E BUTTON HANDLERS =====

        // Capturar submit de formularios no offcanvas
        document.getElementById('offcanvasPanel')?.addEventListener('submit', function(e) {
            if (e.target.tagName === 'FORM') {
                e.preventDefault();

                const formData = new FormData(e.target);
                const data = {
                    action: 'offcanvasFormSubmit',
                    formId: e.target.id
                };

                // Converter checkboxes multiplos para array
                const checkboxes = e.target.querySelectorAll('input[type="checkbox"]:checked');
                if (checkboxes.length > 0) {
                    data.filiais_ids = Array.from(checkboxes).map(cb => cb.value);
                }

                // Extrair todos os outros campos do formulario
                for (const [key, value] of formData.entries()) {
                    // Pular campos que terminam com [] (arrays) - ja tratados acima
                    if (!key.endsWith('[]') && !data.hasOwnProperty(key)) {
                        data[key] = value;
                    }
                }

                // Se tiver data-id, incluir
                if (e.target.dataset.id) {
                    data.id = e.target.dataset.id;
                }

                // Enviar para iframe ativo
                const iframe = document.querySelector('.tab-content.active-content iframe');
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.postMessage(data, '*');
                }
            }
        });

        // Capturar cliques em botoes no offcanvas
        document.getElementById('offcanvasPanel')?.addEventListener('click', function(e) {
            const button = e.target.closest('button[id]');
            if (button && button.id && !button.type?.includes('submit')) {
                const data = {
                    action: 'offcanvasButtonClick',
                    buttonId: button.id
                };

                // Capturar valores de inputs do offcanvas (ex: telefone para teste SMS)
                const offcanvas = document.getElementById('offcanvasPanel');
                if (offcanvas) {
                    const inputs = offcanvas.querySelectorAll('input[id], textarea[id], select[id]');
                    inputs.forEach(input => {
                        if (input.id && input.value) {
                            data[input.id] = input.value;
                        }
                    });
                }

                // Enviar evento de clique para iframe ativo
                const iframe = document.querySelector('.tab-content.active-content iframe');
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.postMessage(data, '*');
                }
            }
        });
    })();

    // ===== SPOTLIGHT (Busca Global) =====
    (function() {
        let _spotlightDebounce = null;
        let _spotlightIndex = -1;
        let _spotlightItems = [];

        window.openSpotlight = function() {
            const modal = document.getElementById('spotlightModal');
            const input = document.getElementById('spotlightInput');
            modal.classList.add('open');
            document.body.classList.add('modal-open');
            input.value = '';
            document.getElementById('spotlightResults').innerHTML = '';
            document.getElementById('spotlightFooter').style.display = 'none';
            _spotlightIndex = -1;
            _spotlightItems = [];
            setTimeout(() => input.focus(), 50);
        };

        window.closeSpotlight = function() {
            document.getElementById('spotlightModal').classList.remove('open');
            document.body.classList.remove('modal-open');
            clearTimeout(_spotlightDebounce);
        };

        // Atalho Ctrl+K / Cmd+K
        document.addEventListener('keydown', function(e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                const modal = document.getElementById('spotlightModal');
                modal.classList.contains('open') ? closeSpotlight() : openSpotlight();
            }
            if (e.key === 'Escape' && document.getElementById('spotlightModal').classList.contains('open')) {
                e.stopPropagation();
                closeSpotlight();
            }
        });

        // Input com debounce
        document.getElementById('spotlightInput')?.addEventListener('input', function() {
            clearTimeout(_spotlightDebounce);
            const q = this.value.trim();
            if (q.length < 2) {
                document.getElementById('spotlightResults').innerHTML = '';
                document.getElementById('spotlightFooter').style.display = 'none';
                _spotlightItems = [];
                _spotlightIndex = -1;
                return;
            }
            _spotlightDebounce = setTimeout(() => _spotlightSearch(q), 300);
        });

        // Navegacao por teclado
        document.getElementById('spotlightInput')?.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                _spotlightIndex = Math.min(_spotlightIndex + 1, _spotlightItems.length - 1);
                _spotlightHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                _spotlightIndex = Math.max(_spotlightIndex - 1, 0);
                _spotlightHighlight();
            } else if (e.key === 'Enter' && _spotlightIndex >= 0) {
                e.preventDefault();
                _spotlightSelect(_spotlightItems[_spotlightIndex]);
            }
        });

        async function _spotlightSearch(q) {
            try {
                const result = await API.get('/api/localizar', { q: q });
                if (result.success) {
                    _spotlightRender(result.data);
                }
            } catch (e) {
                // silently fail
            }
        }

        function _spotlightRender(groups) {
            const container = document.getElementById('spotlightResults');
            const footer = document.getElementById('spotlightFooter');
            _spotlightItems = [];
            _spotlightIndex = -1;

            if (!groups || groups.length === 0) {
                container.innerHTML = '<div style="padding: 24px; text-align: center; color: #94a3b8;">' + layoutT('spotlight.no_results') + '</div>';
                footer.style.display = 'none';
                return;
            }

            let html = '';
            groups.forEach(function(group) {
                if (!group.items || group.items.length === 0) return;
                html += '<div style="padding: 10px 16px 4px; display: flex; align-items: center; gap: 8px;">'
                    + '<i class="' + group.icon + '" style="color: #64748b; font-size: 13px; width: 16px; text-align: center;"></i>'
                    + '<span style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">' + group.label + '</span>'
                    + '</div>';
                group.items.forEach(function(item) {
                    const idx = _spotlightItems.length;
                    _spotlightItems.push(item);
                    html += '<div class="spotlight-item" data-idx="' + idx + '" style="padding: 6px 16px 6px 40px; cursor: pointer; border-radius: 6px; margin: 0 8px;">'
                        + '<div style="display: flex; align-items: baseline; gap: 8px;">'
                        + '<span style="color: #94a3b8; font-size: 8px; line-height: 1;">●</span>'
                        + '<div style="min-width: 0;">'
                        + '<div style="font-size: 14px; font-weight: 500; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + _spotlightEscape(item.title) + '</div>'
                        + (item.subtitle ? '<div style="font-size: 12px; color: #94a3b8;">' + _spotlightEscape(item.subtitle) + '</div>' : '')
                        + '</div></div></div>';
                });
            });

            container.innerHTML = html;
            footer.style.display = 'block';

            // Hover e click
            container.querySelectorAll('.spotlight-item').forEach(function(el) {
                el.addEventListener('mouseenter', function() {
                    _spotlightIndex = parseInt(this.dataset.idx);
                    _spotlightHighlight();
                });
                el.addEventListener('click', function() {
                    _spotlightSelect(_spotlightItems[parseInt(this.dataset.idx)]);
                });
            });
        }

        function _spotlightHighlight() {
            document.querySelectorAll('.spotlight-item').forEach(function(el, i) {
                el.style.background = parseInt(el.dataset.idx) === _spotlightIndex ? '#e2e8f0' : '';
            });
            const active = document.querySelector('.spotlight-item[data-idx="' + _spotlightIndex + '"]');
            if (active) active.scrollIntoView({ block: 'nearest' });
        }

        function _spotlightSelect(item) {
            closeSpotlight();
            openOrSwitchToTab(item.page, item.tabName, item.tabIcon);
        }

        function _spotlightEscape(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        // Fechar ao clicar no overlay
        document.getElementById('spotlightModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeSpotlight();
        });
    })();
    </script>

    @yield('scripts')
</body>
</html>
