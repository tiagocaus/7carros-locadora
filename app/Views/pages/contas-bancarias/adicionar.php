@extends('layouts.iframe')

@section('title', '<?= t("modules.contas_bancarias.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.contas_bancarias.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formConta" method="POST">
        @csrf
        <input type="hidden" id="contaId" name="id">

        <!-- Secao: Dados da Conta -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-university mr-2"></i><?= t('modules.contas_bancarias.sections.account_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Nome -->
                <div class="md:col-span-5 form-input-group">
                    <label for="contaNome" class="form-label-group">
                        <?= t('modules.contas_bancarias.fields.name') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="contaNome" name="nome" class="form-input-group-field" required maxlength="100" placeholder="<?= t('modules.contas_bancarias.placeholders.name_example') ?>">
                </div>

                <!-- Tipo -->
                <div class="md:col-span-4 form-input-group">
                    <label for="contaTipo" class="form-label-group">
                        <?= t('modules.contas_bancarias.fields.type') ?> <span class="text-red-500">*</span>
                    </label>
                    <select id="contaTipo" name="e_conta_bancaria" class="form-input-group-field" required>
                        <option value="S"><?= t('modules.contas_bancarias.type_options.bank_account') ?></option>
                        <option value="N"><?= t('modules.contas_bancarias.type_options.cash') ?></option>
                    </select>
                </div>

                <!-- Status -->
                <div class="md:col-span-3 pb-2 flex items-end">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="contaAtivo" name="status" value="A" checked class="form-checkbox h-4 w-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-slate-700"><?= t('modules.contas_bancarias.badges.status_active') ?></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Secao: Dados Bancarios (visivel apenas se tipo = Conta Bancaria) -->
        <div class="form-section mb-6" id="secaoDadosBancarios">
            <h3 class="form-section-title"><i class="fas fa-credit-card mr-2"></i><?= t('modules.contas_bancarias.sections.bank_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Banco -->
                <div class="form-input-group">
                    <label for="contaBanco" class="form-label-group"><?= t('modules.contas_bancarias.fields.bank') ?></label>
                    <input type="text" id="contaBanco" name="banco" class="form-input-group-field" maxlength="30" placeholder="<?= t('modules.contas_bancarias.placeholders.bank_example') ?>">
                </div>

                <!-- Agencia -->
                <div class="form-input-group">
                    <label for="contaAgencia" class="form-label-group"><?= t('modules.contas_bancarias.fields.branch') ?></label>
                    <input type="text" id="contaAgencia" name="agencia" class="form-input-group-field" maxlength="10" placeholder="<?= t('modules.contas_bancarias.placeholders.branch_example') ?>">
                </div>

                <!-- Conta -->
                <div class="form-input-group">
                    <label for="contaConta" class="form-label-group"><?= t('modules.contas_bancarias.fields.account_number') ?></label>
                    <input type="text" id="contaConta" name="conta" class="form-input-group-field" maxlength="20" placeholder="<?= t('modules.contas_bancarias.placeholders.account_example') ?>">
                </div>
            </div>
        </div>

        <!-- Secao: Observacoes -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-sticky-note mr-2"></i><?= t('modules.contas_bancarias.sections.notes') ?></h3>

            <div class="form-input-group">
                <label for="contaObs" class="form-label-group"><?= t('modules.contas_bancarias.fields.notes') ?></label>
                <textarea id="contaObs" name="obs" class="form-input-group-field" rows="3" maxlength="255" placeholder="<?= t('modules.contas_bancarias.placeholders.notes_example') ?>"></textarea>
            </div>
        </div>

        <!-- Botoes -->
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
(function () {
    const i18n = {
        editTitle: '<?= addslashes(t('modules.contas_bancarias.edit_title')) ?>',
        notFound: '<?= addslashes(t('modules.contas_bancarias.messages.not_found')) ?>',
        loadError: '<?= addslashes(t('modules.contas_bancarias.messages.load_account_error')) ?>',
        nameRequired: '<?= addslashes(t('modules.contas_bancarias.messages.name_required')) ?>',
        saving: '<?= addslashes(t('modules.contas_bancarias.messages.saving')) ?>',
        saveError: '<?= addslashes(t('modules.contas_bancarias.messages.save_error')) ?>',
        saved: '<?= addslashes(t('modules.contas_bancarias.messages.saved')) ?>',
        btnSave: '<?= addslashes(t('common.buttons.save')) ?>',
    };

    // ID da conta (se editando)
    let contaId = null;

    // ===== NAVEGACAO =====

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

    // ===== TOGGLE SECAO DADOS BANCARIOS =====

    function toggleDadosBancarios() {
        const tipo = document.getElementById('contaTipo').value;
        const secao = document.getElementById('secaoDadosBancarios');

        if (tipo === 'S') {
            secao.style.display = 'block';
        } else {
            secao.style.display = 'none';
            // Limpar campos quando mudar para Caixa
            document.getElementById('contaBanco').value = '';
            document.getElementById('contaAgencia').value = '';
            document.getElementById('contaConta').value = '';
        }
    }

    // ===== CARREGAR CONTA (EDICAO) =====

    async function carregarConta(id) {
        try {
            const result = await API.get(`/api/contas-bancarias/${id}`);

            if (result.success && result.data) {
                preencherFormulario(result.data);
            } else {
                mostrarAlerta(result.message || i18n.notFound);
                navegarPara('/pages/contas-bancarias');
            }
        } catch (error) {
            console.error('Erro ao carregar conta:', error);
            mostrarAlerta(i18n.loadError);
            navegarPara('/pages/contas-bancarias');
        }
    }

    function preencherFormulario(dados) {
        document.getElementById('contaId').value = dados.id || '';
        document.getElementById('contaNome').value = dados.nome || '';
        document.getElementById('contaTipo').value = dados.e_conta_bancaria || 'S';
        document.getElementById('contaBanco').value = dados.banco || '';
        document.getElementById('contaAgencia').value = dados.agencia || '';
        document.getElementById('contaConta').value = dados.conta || '';
        document.getElementById('contaObs').value = dados.obs || '';
        document.getElementById('contaAtivo').checked = dados.status === 'A';

        // Atualizar titulo
        document.getElementById('pageTitle').textContent = i18n.editTitle;

        // Toggle secao dados bancarios
        toggleDadosBancarios();
    }

    // ===== SALVAR =====

    async function salvarConta(event) {
        event.preventDefault();

        const form = document.getElementById('formConta');
        const btnSalvar = document.getElementById('btnSalvar');

        // Validacao basica
        const nome = document.getElementById('contaNome').value.trim();
        if (!nome) {
            mostrarAlerta(i18n.nameRequired);
            document.getElementById('contaNome').focus();
            return;
        }

        // Desabilitar botao
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving}`;

        try {
            const dados = {
                nome: nome,
                e_conta_bancaria: document.getElementById('contaTipo').value,
                status: document.getElementById('contaAtivo').checked ? 'A' : 'I',
                banco: document.getElementById('contaBanco').value.trim() || null,
                agencia: document.getElementById('contaAgencia').value.trim() || null,
                conta: document.getElementById('contaConta').value.trim() || null,
                obs: document.getElementById('contaObs').value.trim() || null
            };

            let url = '/contas-bancarias/salvar';
            if (contaId) {
                url = `/contas-bancarias/${contaId}/atualizar`;
            }

            const result = await API.post(url, dados);

            if (result.success) {
                window.parent.postMessage({ action: 'showToast', message: result.message || i18n.saved }, '*');
                navegarPara('/pages/contas-bancarias');
            } else {
                mostrarAlerta(result.message || i18n.saveError);
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            mostrarAlerta(i18n.saveError);
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.btnSave}`;
        }
    }

    // ===== MODAL DE ALERTA =====

    function mostrarAlerta(mensagem, callbackAction = null) {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openAlertModal',
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

    // ===== EVENT LISTENERS =====

    // Toggle dados bancarios quando mudar tipo
    document.getElementById('contaTipo')?.addEventListener('change', toggleDadosBancarios);

    // Form submit
    document.getElementById('formConta')?.addEventListener('submit', salvarConta);

    // Botao voltar
    document.getElementById('btnVoltar')?.addEventListener('click', function () {
        navegarPara('/pages/contas-bancarias');
    });

    // Botao cancelar
    document.getElementById('btnCancelar')?.addEventListener('click', function () {
        navegarPara('/pages/contas-bancarias');
    });

    // ===== INICIALIZACAO =====

    // Verificar se esta editando
    const urlParams = new URLSearchParams(window.location.search);
    contaId = urlParams.get('id');

    if (contaId) {
        carregarConta(contaId);
    } else {
        // Toggle inicial para nova conta
        toggleDadosBancarios();
    }
})();
</script>
@endsection
