@extends('layouts.iframe')

@section('title', '<?= t("modules.fornecedores.title_singular") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.fornecedores.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formFornecedor" method="POST">
        @csrf
        <input type="hidden" id="registroId" name="id">

        <!-- Secao 1: Dados Basicos -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-user mr-2"></i><?= t('modules.fornecedores.sections.basic_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Tipo -->
                <div class="md:col-span-2 form-input-group">
                    <label for="tipo" class="form-label-group"><?= t('modules.fornecedores.fields.type') ?></label>
                    <select id="tipo" name="tipo" class="form-input-group-field">
                        <option value="PJ"><?= t('modules.fornecedores.type_options.PJ') ?></option>
                        <option value="PF"><?= t('modules.fornecedores.type_options.PF') ?></option>
                    </select>
                </div>

                <!-- CPF/CNPJ -->
                <div class="md:col-span-3 form-input-group">
                    <label for="cpf_cnpj" class="form-label-group" id="labelCpfCnpj"><?= t('modules.fornecedores.fields.cnpj') ?></label>
                    <input type="text" id="cpf_cnpj" name="cpf_cnpj" class="form-input-group-field" maxlength="18">
                </div>

                <!-- Nome/Razao Social -->
                <div class="md:col-span-4 form-input-group">
                    <label for="nome_rsocial" class="form-label-group" id="labelNome">
                        <?= t('modules.fornecedores.fields.company_name') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nome_rsocial" name="nome_rsocial" class="form-input-group-field" required maxlength="255">
                </div>

                <!-- Nome Fantasia -->
                <div class="md:col-span-3 form-input-group">
                    <label for="nome_fantasia" class="form-label-group"><?= t('modules.fornecedores.fields.trade_name') ?></label>
                    <input type="text" id="nome_fantasia" name="nome_fantasia" class="form-input-group-field" maxlength="255">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-[2fr_2fr_3fr_2.5fr_2.5fr] gap-4 mt-4">
                <!-- RG/IE -->
                <div class="form-input-group">
                    <label for="rg_ie" class="form-label-group" id="labelRgIe"><?= t('modules.fornecedores.fields.state_registration') ?></label>
                    <input type="text" id="rg_ie" name="rg_ie" class="form-input-group-field" maxlength="20">
                </div>

                <!-- Inscricao Municipal -->
                <div class="form-input-group">
                    <label for="ins_mun" class="form-label-group"><?= t('modules.fornecedores.fields.municipal_registration') ?></label>
                    <input type="text" id="ins_mun" name="ins_mun" class="form-input-group-field" maxlength="45">
                </div>

                <!-- Email -->
                <div class="form-input-group">
                    <label for="email" class="form-label-group"><?= t('modules.fornecedores.fields.email') ?></label>
                    <input type="email" id="email" name="email" class="form-input-group-field" maxlength="100">
                </div>

                <!-- Telefone 1 -->
                <div class="form-input-group">
                    <label for="tel1" class="form-label-group"><?= t('modules.fornecedores.fields.phone1') ?></label>
                    <input type="tel" id="tel1" name="tel1" class="form-input-group-field intltel" maxlength="45">
                </div>

                <!-- Telefone 2 -->
                <div class="form-input-group">
                    <label for="tel2" class="form-label-group"><?= t('modules.fornecedores.fields.phone2') ?></label>
                    <input type="tel" id="tel2" name="tel2" class="form-input-group-field intltel" maxlength="45">
                </div>
            </div>
        </div>

        <!-- Secao 2: Endereco -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-map-marker-alt mr-2"></i><?= t('modules.fornecedores.sections.address') ?></h3>

            <!-- Linha 1: CEP, Rua, Número -->
            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12 sm:col-span-3 lg:col-span-2 form-input-group">
                    <label for="cep" class="form-label-group"><?= t('modules.fornecedores.fields.zip_code') ?></label>
                    <input type="text" id="cep" name="cep" class="form-input-group-field" maxlength="15">
                </div>
                <div class="col-span-12 sm:col-span-6 lg:col-span-8 form-input-group">
                    <label for="rua" class="form-label-group"><?= t('modules.fornecedores.fields.street') ?></label>
                    <input type="text" id="rua" name="rua" class="form-input-group-field" maxlength="255">
                </div>
                <div class="col-span-12 sm:col-span-3 lg:col-span-2 form-input-group">
                    <label for="num" class="form-label-group"><?= t('modules.fornecedores.fields.number') ?></label>
                    <input type="text" id="num" name="num" class="form-input-group-field" maxlength="5">
                </div>
            </div>

            <!-- Linha 2: Complemento, Bairro, Cidade -->
            <div class="grid grid-cols-12 gap-4 mt-4">
                <div class="col-span-12 sm:col-span-4 form-input-group">
                    <label for="complemento" class="form-label-group"><?= t('modules.fornecedores.fields.complement') ?></label>
                    <input type="text" id="complemento" name="complemento" class="form-input-group-field" maxlength="100">
                </div>
                <div class="col-span-12 sm:col-span-4 form-input-group">
                    <label for="bairro" class="form-label-group"><?= t('modules.fornecedores.fields.neighborhood') ?></label>
                    <input type="text" id="bairro" name="bairro" class="form-input-group-field" maxlength="45">
                </div>
                <div class="col-span-12 sm:col-span-4 form-input-group">
                    <label for="cidade" class="form-label-group"><?= t('modules.fornecedores.fields.city') ?></label>
                    <input type="text" id="cidade" name="cidade" class="form-input-group-field" maxlength="45">
                </div>
            </div>

            <!-- Linha 3: Estado, País, Fornecedor de Carro -->
            <div class="grid grid-cols-12 gap-4 mt-4">
                <div class="col-span-12 sm:col-span-4 form-input-group">
                    <label for="estado" class="form-label-group"><?= t('modules.fornecedores.fields.state') ?></label>
                    <input type="text" id="estado" name="estado" class="form-input-group-field" maxlength="45">
                </div>
                <div class="col-span-12 sm:col-span-4 form-input-group">
                    <label for="pais" class="form-label-group"><?= t('modules.fornecedores.fields.country') ?></label>
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
                <div class="col-span-12 sm:col-span-4 form-input-group flex items-end pb-2">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="de_carro" name="de_carro" value="S" class="form-checkbox h-4 w-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-slate-700"><?= t('modules.fornecedores.fields.supplies_vehicles') ?></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Secao 3: Investidor -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-chart-line mr-2"></i><?= t('modules.fornecedores.sections.investor') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- E Investidor -->
                <div class="md:col-span-2 form-input-group flex items-end pb-2">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="investidor" name="investidor" value="1" class="form-checkbox h-4 w-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-slate-700 font-medium"><?= t('modules.fornecedores.fields.is_investor') ?></span>
                    </label>
                </div>
            </div>

            <!-- Campos de Investidor (aparecem apenas quando marcado) -->
            <div id="camposInvestidor" class="hidden mt-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <!-- Gateway Split -->
                    <div class="md:col-span-3 form-input-group">
                        <label for="split_gateway" class="form-label-group"><?= t('modules.fornecedores.fields.split_gateway') ?></label>
                        <select id="split_gateway" name="split_gateway" class="form-input-group-field">
                            <option value=""><?= t('modules.fornecedores.gateway_options.none') ?></option>
                            <option value="asaas"><?= t('modules.fornecedores.gateway_options.asaas') ?></option>
                            <option value="gerencianet"><?= t('modules.fornecedores.gateway_options.gerencianet') ?></option>
                            <option value="stripe"><?= t('modules.fornecedores.gateway_options.stripe') ?></option>
                            <option value="inter"><?= t('modules.fornecedores.gateway_options.inter') ?></option>
                        </select>
                    </div>

                    <!-- Conta Split -->
                    <div class="md:col-span-3 form-input-group">
                        <label for="split_gateway_conta" class="form-label-group"><?= t('modules.fornecedores.fields.split_account_id') ?></label>
                        <input type="text" id="split_gateway_conta" name="split_gateway_conta" class="form-input-group-field" maxlength="255" placeholder="<?= t('modules.fornecedores.placeholders.split_account') ?>">
                    </div>

                    <!-- Chave PIX -->
                    <div class="md:col-span-3 form-input-group">
                        <label for="pix_chave" class="form-label-group"><?= t('modules.fornecedores.fields.pix_key') ?></label>
                        <input type="text" id="pix_chave" name="pix_chave" class="form-input-group-field" maxlength="255">
                    </div>

                    <!-- Tipo PIX -->
                    <div class="md:col-span-3 form-input-group">
                        <label for="pix_tipo" class="form-label-group"><?= t('modules.fornecedores.fields.pix_key_type') ?></label>
                        <select id="pix_tipo" name="pix_tipo" class="form-input-group-field">
                            <option value=""><?= t('modules.fornecedores.placeholders.select') ?></option>
                            <option value="cpf"><?= t('modules.fornecedores.pix_type_options.cpf') ?></option>
                            <option value="cnpj"><?= t('modules.fornecedores.pix_type_options.cnpj') ?></option>
                            <option value="email"><?= t('modules.fornecedores.pix_type_options.email') ?></option>
                            <option value="telefone"><?= t('modules.fornecedores.pix_type_options.telefone') ?></option>
                            <option value="aleatoria"><?= t('modules.fornecedores.pix_type_options.aleatoria') ?></option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                    <!-- Banco -->
                    <div class="md:col-span-3 form-input-group">
                        <label for="banco_codigo" class="form-label-group"><?= t('modules.fornecedores.fields.bank_code') ?></label>
                        <input type="text" id="banco_codigo" name="banco_codigo" class="form-input-group-field" maxlength="10" placeholder="<?= t('modules.fornecedores.placeholders.bank_code') ?>">
                    </div>

                    <!-- Agencia -->
                    <div class="md:col-span-3 form-input-group">
                        <label for="banco_agencia" class="form-label-group"><?= t('modules.fornecedores.fields.bank_branch') ?></label>
                        <input type="text" id="banco_agencia" name="banco_agencia" class="form-input-group-field" maxlength="10">
                    </div>

                    <!-- Conta -->
                    <div class="md:col-span-3 form-input-group">
                        <label for="banco_conta" class="form-label-group"><?= t('modules.fornecedores.fields.bank_account') ?></label>
                        <input type="text" id="banco_conta" name="banco_conta" class="form-input-group-field" maxlength="20">
                    </div>

                    <!-- Tipo Conta -->
                    <div class="md:col-span-3 form-input-group">
                        <label for="banco_tipo" class="form-label-group"><?= t('modules.fornecedores.fields.bank_account_type') ?></label>
                        <select id="banco_tipo" name="banco_tipo" class="form-input-group-field">
                            <option value=""><?= t('modules.fornecedores.placeholders.select') ?></option>
                            <option value="corrente"><?= t('modules.fornecedores.account_type_options.corrente') ?></option>
                            <option value="poupanca"><?= t('modules.fornecedores.account_type_options.poupanca') ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secao 4: Observacoes -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-sticky-note mr-2"></i><?= t('modules.fornecedores.sections.observations') ?></h3>

            <div class="form-input-group">
                <textarea id="obs" name="obs" class="form-input-group-field" rows="3" maxlength="5000"></textarea>
            </div>
        </div>

        <!-- Botoes de Acao -->
        <div class="flex justify-end space-x-3 mt-6 mb-4">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-6 rounded-md text-sm font-medium flex items-center">
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
        editTitle: '<?= addslashes(t('modules.fornecedores.edit_title')) ?>',
        loadDataError: '<?= addslashes(t('modules.fornecedores.messages.load_data_error')) ?>',
        loadSupplierError: '<?= addslashes(t('modules.fornecedores.messages.load_supplier_error')) ?>',
        saving: '<?= addslashes(t('modules.fornecedores.messages.saving')) ?>',
        saveError: '<?= addslashes(t('modules.fornecedores.messages.save_error')) ?>',
        saveSupplierError: '<?= addslashes(t('modules.fornecedores.messages.save_supplier_error')) ?>',
        created: '<?= addslashes(t('modules.fornecedores.messages.created')) ?>',
        updated: '<?= addslashes(t('modules.fornecedores.messages.updated')) ?>',
        labelCpf: '<?= addslashes(t('modules.fornecedores.fields.cpf')) ?>',
        labelCnpj: '<?= addslashes(t('modules.fornecedores.fields.cnpj')) ?>',
        labelName: '<?= addslashes(t('modules.fornecedores.fields.name')) ?>',
        labelCompanyName: '<?= addslashes(t('modules.fornecedores.fields.company_name')) ?>',
        labelRg: '<?= addslashes(t('modules.fornecedores.fields.rg')) ?>',
        labelStateRegistration: '<?= addslashes(t('modules.fornecedores.fields.state_registration')) ?>',
        btnSave: '<?= addslashes(t('common.buttons.save')) ?>',
    };

    // Estado
    let editando = false;
    let registroId = null;

    // Elementos do formulario
    const form = document.getElementById('formFornecedor');
    const pageTitle = document.getElementById('pageTitle');
    const inputId = document.getElementById('registroId');

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

    function voltar() {
        navegarPara('/pages/fornecedores');
    }

    // ===== INICIALIZACAO =====

    async function init() {
        // Verificar se estamos editando
        const urlParams = new URLSearchParams(window.location.search);
        registroId = urlParams.get('id');

        if (registroId) {
            editando = true;
            pageTitle.textContent = i18n.editTitle;
            await carregarDados(registroId);
        }

        configurarEventos();
    }

    // ===== CARREGAR DADOS =====

    async function carregarDados(id) {
        try {
            const result = await API.get(`/api/fornecedores/${id}`);

            if (!result.success) {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.loadDataError }, '*');
                voltar();
                return;
            }

            preencherFormulario(result.data);
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.loadSupplierError }, '*');
            voltar();
        }
    }

    function preencherFormulario(data) {
        inputId.value = data.id || '';

        // Dados basicos
        document.getElementById('tipo').value = data.tipo || 'PJ';
        document.getElementById('cpf_cnpj').value = data.cpf_cnpj || '';
        document.getElementById('nome_rsocial').value = data.nome_rsocial || '';
        document.getElementById('nome_fantasia').value = data.nome_fantasia || '';
        document.getElementById('rg_ie').value = data.rg_ie || '';
        document.getElementById('ins_mun').value = data.ins_mun || '';
        document.getElementById('email').value = data.email || '';
        document.getElementById('tel1').value = data.tel1 || '';
        document.getElementById('tel2').value = data.tel2 || '';

        // Detectar país dos telefones preenchidos
        document.querySelectorAll('#tel1, #tel2').forEach(input => {
            if (input._intlPhone && typeof input._intlPhone.detectCountryFromValue === 'function') {
                input._intlPhone.detectCountryFromValue();
            }
        });

        // Endereco
        document.getElementById('cep').value = data.cep || '';
        document.getElementById('rua').value = data.rua || '';
        document.getElementById('num').value = data.num || '';
        document.getElementById('complemento').value = data.complemento || '';
        document.getElementById('bairro').value = data.bairro || '';
        document.getElementById('cidade').value = data.cidade || '';
        document.getElementById('estado').value = data.estado || '';
        const paisSelect = document.getElementById('pais');
        paisSelect.value = data.pais || 'BR';
        paisSelect.dispatchEvent(new Event('change'));
        if (typeof jQuery !== 'undefined') $(paisSelect).trigger('chosen:updated');

        // Opcoes
        document.getElementById('de_carro').checked = data.de_carro === 'S';
        document.getElementById('investidor').checked = data.investidor == 1;

        // Dados investidor
        if (data.investidor == 1) {
            document.getElementById('camposInvestidor').classList.remove('hidden');
        }
        document.getElementById('split_gateway').value = data.split_gateway || '';
        document.getElementById('split_gateway_conta').value = data.split_gateway_conta || '';
        document.getElementById('pix_chave').value = data.pix_chave || '';
        document.getElementById('pix_tipo').value = data.pix_tipo || '';
        document.getElementById('banco_codigo').value = data.banco_codigo || '';
        document.getElementById('banco_agencia').value = data.banco_agencia || '';
        document.getElementById('banco_conta').value = data.banco_conta || '';
        document.getElementById('banco_tipo').value = data.banco_tipo || '';

        // Observacoes
        document.getElementById('obs').value = data.obs || '';

        // Atualizar labels
        atualizarLabels();
    }

    // ===== CONFIGURAR EVENTOS =====

    function configurarEventos() {
        // Botao voltar
        document.getElementById('btnVoltar')?.addEventListener('click', voltar);
        document.getElementById('btnCancelar')?.addEventListener('click', voltar);

        // Toggle investidor
        document.getElementById('investidor')?.addEventListener('change', function() {
            const campos = document.getElementById('camposInvestidor');
            if (this.checked) {
                campos.classList.remove('hidden');
            } else {
                campos.classList.add('hidden');
            }
        });

        // Alterar labels baseado no tipo
        document.getElementById('tipo')?.addEventListener('change', atualizarLabels);

        // Mascara CPF/CNPJ
        document.getElementById('cpf_cnpj')?.addEventListener('input', function(e) {
            const tipo = document.getElementById('tipo').value;
            let value = e.target.value.replace(/\D/g, '');

            if (tipo === 'PF') {
                // CPF: 000.000.000-00
                if (value.length <= 11) {
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                }
            } else {
                // CNPJ: 00.000.000/0000-00
                if (value.length <= 14) {
                    value = value.replace(/(\d{2})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/(\d{3})(\d)/, '$1/$2');
                    value = value.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                }
            }

            e.target.value = value;
        });


        // Mascara CEP
        document.getElementById('cep')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/(\d{5})(\d{0,3})/, '$1-$2');
            }
            e.target.value = value;
        });

        // Buscar CEP
        document.getElementById('cep')?.addEventListener('blur', async function(e) {
            const cep = e.target.value.replace(/\D/g, '');
            if (cep.length === 8) {
                try {
                    const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                    const data = await response.json();
                    if (!data.erro) {
                        document.getElementById('rua').value = data.logradouro || '';
                        document.getElementById('bairro').value = data.bairro || '';
                        document.getElementById('cidade').value = data.localidade || '';
                        document.getElementById('estado').value = data.uf || '';
                    }
                } catch (error) {
                    console.error('Erro ao buscar CEP:', error);
                }
            }
        });

        // Submissao do formulario
        form.addEventListener('submit', salvar);
    }

    function atualizarLabels() {
        const tipo = document.getElementById('tipo').value;
        const labelCpfCnpj = document.getElementById('labelCpfCnpj');
        const labelNome = document.getElementById('labelNome');
        const labelRgIe = document.getElementById('labelRgIe');

        if (tipo === 'PF') {
            labelCpfCnpj.textContent = i18n.labelCpf;
            labelNome.innerHTML = i18n.labelName + ' <span class="text-red-500">*</span>';
            labelRgIe.textContent = i18n.labelRg;
        } else {
            labelCpfCnpj.textContent = i18n.labelCnpj;
            labelNome.innerHTML = i18n.labelCompanyName + ' <span class="text-red-500">*</span>';
            labelRgIe.textContent = i18n.labelStateRegistration;
        }
    }

    // ===== SALVAR =====

    async function salvar(e) {
        e.preventDefault();

        const btnSalvar = document.getElementById('btnSalvar');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + i18n.saving;

        try {
            const formData = new FormData(form);
            const dados = Object.fromEntries(formData.entries());

            // Ajustes
            dados.de_carro = document.getElementById('de_carro').checked ? 'S' : 'N';
            dados.investidor = document.getElementById('investidor').checked ? 1 : 0;

            // Limpar dados de investidor se nao for investidor
            if (!dados.investidor) {
                dados.split_gateway = '';
                dados.split_gateway_conta = '';
                dados.pix_chave = '';
                dados.pix_tipo = '';
                dados.banco_codigo = '';
                dados.banco_agencia = '';
                dados.banco_conta = '';
                dados.banco_tipo = '';
            }

            let url, method;
            if (editando && registroId) {
                url = `/fornecedores/${registroId}/atualizar`;
            } else {
                url = '/fornecedores/salvar';
            }

            const result = await API.post(url, dados);

            if (result.success) {
                // Notificar parent e voltar
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'showToast',
                        type: 'success',
                        message: editando ? i18n.updated : i18n.created
                    }, '*');
                }
                voltar();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.saveError }, '*');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.saveSupplierError }, '*');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="fas fa-save mr-2"></i>' + i18n.btnSave;
        }
    }

    // Inicializar
    init();
})();
</script>
@endsection
