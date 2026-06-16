@extends('layouts.iframe')

@section('title', t('modules.clientes.new_title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabeçalho com título e botão voltar -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page">{{ t('modules.clientes.new_title') }}</h2>
        <button id="btnVoltarListaClientes" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>{{ t('common.buttons.back') }}
        </button>
    </div>

    <!-- Tabs internas -->
    <div class="mb-4 border-b border-slate-300">
        <nav class="flex -mb-px" id="formClienteTabsNav">
            <button data-form-tab-target="#formDadosCliente" class="form-tab-button active">{{ t('modules.clientes.tabs.data') }}</button>
            <button data-form-tab-target="#formArquivosCliente" class="form-tab-button">{{ t('modules.clientes.tabs.files') }}</button>
            <button data-form-tab-target="#formFaturasCliente" class="form-tab-button">{{ t('modules.clientes.tabs.invoices') }}</button>
        </nav>
    </div>

    <!-- Formulário -->
    <form id="formCliente" method="POST" action="/clientes/salvar">
        @csrf

        <!-- Tab: Dados -->
        <div id="formDadosCliente" class="form-tab-content active">
            <div>
                <!-- Seção: Dados do Cliente -->
                <div class="form-section mb-6 relative">
                    <h3 class="form-section-title">{{ t('modules.clientes.sections.customer_data') }}</h3>

                    <!-- Container da foto posicionado no canto superior direito -->
                    <div class="fotoClientePreviewContainer absolute top-0 right-0 w-40 h-50 border-2 border-slate-300 rounded-md overflow-hidden bg-slate-100 cursor-pointer group z-10" id="fotoClienteContainer">
                        <img id="fotoClientePreview"
                            src="<?= image('assets/img/foto_padrao.png') ?>"
                            alt="Foto do Cliente"
                            class="w-full h-full object-cover">
                        <input type="file" id="fotoClienteInput" name="foto" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                        <input type="hidden" id="fotoClienteBase64" name="foto_base64">
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex flex-col justify-end">
                            <div class="bg-black bg-opacity-40 text-white text-center py-2 text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                {{ t('modules.clientes.fields.take_photo') }}
                            </div>
                        </div>
                    </div>

                    <!-- Grid: Matriz/Filial + Situação -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                        <div class="md:col-span-7 form-input-group">
                            <label for="clienteMatriz" class="form-label-group">{{ t('modules.clientes.fields.branch') }}</label>
                            <select id="clienteMatriz" name="id_matriz_filial" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="{{ t('modules.clientes.placeholders.search_branch') }}">
                                <option value="">{{ t('common.labels.select_option') }}...</option>
                            </select>
                        </div>

                        <div class="md:col-span-3 form-input-group">
                            <label for="clienteSituacao" class="form-label-group">{{ t('modules.clientes.fields.registration_status') }}</label>
                            <select id="clienteSituacao" name="situacao" class="form-input-group-field">
                                <option value="">{{ t('common.labels.select_option') }}...</option>
                                <option value="A">{{ t('modules.clientes.fields.status_active') }}</option>
                                <option value="I">{{ t('modules.clientes.fields.status_inactive') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Grid: Tipo | Documento | Nome | Nome Fantasia -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 mt-4">
                        <div class="form-input-group sm:col-span-2">
                            <label for="clienteTipo" class="form-label-group">{{ t('modules.clientes.fields.type') }}</label>
                            <select id="clienteTipo" name="tipo" class="form-input-group-field">
                                <option value="">{{ t('common.labels.select_option') }}...</option>
                                <option value="PF">{{ t('modules.clientes.fields.type_pf') }}</option>
                                <option value="PJ">{{ t('modules.clientes.fields.type_pj') }}</option>
                                <option value="ES">{{ t('modules.clientes.fields.type_foreigner') }}</option>
                            </select>
                        </div>

                        <div class="form-input-group sm:col-span-3" id="campoDocumento">
                            <label for="clienteCPF" class="form-label-group">{{ t('modules.clientes.fields.cpf') }}</label>
                            <input type="text" id="clienteCPF" name="cpf_cnpj" class="form-input-group-field" placeholder="000.000.000-00">
                        </div>

                        <div class="form-input-group sm:col-span-5" id="campoNome">
                            <label for="clienteNome" class="form-label-group">{{ t('modules.clientes.fields.full_name') }}</label>
                            <input type="text" id="clienteNome" name="nome_rsocial" class="form-input-group-field">
                        </div>
                    </div>

                    <!-- Grid: Nome Fantasia | RG/IE | Nascimento | Sexo | Estado Civil | Profissão -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-4">
                        <!-- Campo Nome Fantasia (apenas PJ) -->
                        <div class="form-input-group" id="campoNomeFantasia" style="display: none;">
                            <label for="clienteNomeFantasia" class="form-label-group">{{ t('modules.clientes.fields.fantasy_name') }}</label>
                            <input type="text" id="clienteNomeFantasia" name="nome_fantasia" class="form-input-group-field">
                        </div>

                        <div class="form-input-group" id="campoRG">
                            <label for="clienteRG" class="form-label-group">{{ t('modules.clientes.fields.rg') }}</label>
                            <input type="text" id="clienteRG" name="rg_ie" class="form-input-group-field">
                        </div>

                        <div class="form-input-group" id="campoNascimento">
                            <label for="clienteNascimento" class="form-label-group">{{ t('modules.clientes.fields.birth_date') }}</label>
                            <input type="date" id="clienteNascimento" name="nascimento" class="form-input-group-field">
                        </div>

                        <div class="form-input-group" id="campoSexo">
                            <label for="clienteSexo" class="form-label-group">{{ t('modules.clientes.fields.gender') }}</label>
                            <select id="clienteSexo" name="sexo" class="form-input-group-field">
                                <option value="">{{ t('common.labels.select_option') }}...</option>
                                <option value="M">{{ t('modules.clientes.fields.gender_m') }}</option>
                                <option value="F">{{ t('modules.clientes.fields.gender_f') }}</option>
                            </select>
                        </div>

                        <div class="form-input-group" id="campoEstadoCivil">
                            <label for="clienteEstadoCivil" class="form-label-group">{{ t('modules.clientes.fields.marital_status') }}</label>
                            <select id="clienteEstadoCivil" name="estado_civil" class="form-input-group-field">
                                <option value="">{{ t('common.labels.select_option') }}...</option>
                                <option value="solteiro">{{ t('modules.clientes.fields.marital_single') }}</option>
                                <option value="casado">{{ t('modules.clientes.fields.marital_married') }}</option>
                                <option value="divorciado">{{ t('modules.clientes.fields.marital_divorced') }}</option>
                                <option value="viuvo">{{ t('modules.clientes.fields.marital_widowed') }}</option>
                            </select>
                        </div>

                        <div class="form-input-group" id="campoProfissao">
                            <label for="clienteProfissao" class="form-label-group">{{ t('modules.clientes.fields.profession') }}</label>
                            <input type="text" id="clienteProfissao" name="profissao" class="form-input-group-field">
                        </div>
                    </div>

                    <!-- Grid: Senha | Idioma Preferido -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                        <div class="form-input-group">
                            <label for="clienteSenha" class="form-label-group">{{ t('modules.clientes.fields.password') }}</label>
                            <input type="password" id="clienteSenha" name="senha" class="form-input-group-field" placeholder="{{ t('modules.clientes.fields.password_placeholder') }}">
                        </div>

                        <div class="form-input-group">
                            <label for="clienteIdioma" class="form-label-group">{{ t('modules.clientes.fields.preferred_locale') }}</label>
                            <select id="clienteIdioma" name="preferred_locale" class="form-input-group-field">
                                <option value="">{{ t('modules.clientes.fields.system_default') }}</option>
                                <option value="pt_BR">Português (Brasil)</option>
                                <option value="pt_PT">Português (Portugal)</option>
                                <option value="en_US">English (USA)</option>
                                <option value="es_ES">Español (España)</option>
                                <option value="it_IT">Italiano (Italia)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Seção: Endereço -->
                <div class="form-section mb-6">
                    <h3 class="form-section-title">{{ t('modules.clientes.sections.address') }}</h3>

                    <!-- Linha 1: CEP, Rua, Número -->
                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-12 sm:col-span-3 lg:col-span-2 form-input-group">
                            <label for="clienteCEP" class="form-label-group">{{ t('modules.clientes.fields.zip_code') }}</label>
                            <input type="text" id="cep" name="cep" class="form-input-group-field cep" placeholder="00000-000">
                        </div>
                        <div class="col-span-12 sm:col-span-6 lg:col-span-8 form-input-group">
                            <label for="clienteRua" class="form-label-group">{{ t('modules.clientes.fields.street') }}</label>
                            <input type="text" id="rua" name="rua" class="form-input-group-field">
                        </div>
                        <div class="col-span-12 sm:col-span-3 lg:col-span-2 form-input-group">
                            <label for="clienteNumero" class="form-label-group">{{ t('modules.clientes.fields.number') }}</label>
                            <input type="text" id="clienteNumero" name="numero" class="form-input-group-field">
                        </div>
                    </div>

                    <!-- Linha 2: Complemento, Bairro, Cidade -->
                    <div class="grid grid-cols-12 gap-4 mt-4">
                        <div class="col-span-12 sm:col-span-4 form-input-group">
                            <label for="clienteComplemento" class="form-label-group">{{ t('modules.clientes.fields.complement') }}</label>
                            <input type="text" id="clienteComplemento" name="complemento" class="form-input-group-field">
                        </div>
                        <div class="col-span-12 sm:col-span-4 form-input-group">
                            <label for="clienteBairro" class="form-label-group">{{ t('modules.clientes.fields.neighborhood') }}</label>
                            <input type="text" id="bairro" name="bairro" class="form-input-group-field">
                        </div>
                        <div class="col-span-12 sm:col-span-4 form-input-group">
                            <label for="clienteCidade" class="form-label-group">{{ t('modules.clientes.fields.city') }}</label>
                            <input type="text" id="cidade" name="cidade" class="form-input-group-field">
                        </div>
                    </div>

                    <!-- Linha 3: Estado, País -->
                    <div class="grid grid-cols-12 gap-4 mt-4">
                        <div class="col-span-12 sm:col-span-6 form-input-group">
                            <label for="clienteEstado" class="form-label-group">{{ t('modules.clientes.fields.state') }}</label>
                            <input type="text" id="uf" name="estado" class="form-input-group-field">
                        </div>
                        <div class="col-span-12 sm:col-span-6 form-input-group">
                            <label for="clientePais" class="form-label-group">{{ t('modules.clientes.fields.country') }}</label>
                            <select id="pais" name="pais" class="form-input-group-field chosen-select"
                                    data-chosen-placeholder="{{ t('common.labels.select') }}...">
                                <option value="">{{ t('common.labels.select') }}...</option>
                                <?php foreach ($paises ?? [] as $p): ?>
                                    <option value="<?= $p['codigo'] ?>" <?= ($p['codigo'] === 'BR') ? 'selected' : '' ?>>
                                        <?= \App\Models\Pais::getNome($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Seção: Contato -->
                <div class="form-section mb-6">
                    <h3 class="form-section-title">{{ t('modules.clientes.sections.contact') }}</h3>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Coluna: E-mails -->
                        <div class="border rounded-lg p-4 bg-slate-50">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-slate-700">
                                    <i class="fas fa-envelope mr-2 text-blue-500"></i>{{ t('modules.clientes.fields.email') }}
                                </h4>
                                <button type="button" id="btnAddEmail" class="btn-secondary text-sm py-1 px-3 rounded">
                                    <i class="fas fa-plus mr-1"></i>{{ t('common.buttons.add') }}
                                </button>
                            </div>
                            <div id="emailsContainer" class="space-y-3">
                                <!-- Emails serao renderizados aqui pelo JavaScript -->
                            </div>
                        </div>

                        <!-- Coluna: Telefones -->
                        <div class="border rounded-lg p-4 bg-slate-50">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-slate-700">
                                    <i class="fas fa-phone mr-2 text-green-500"></i>{{ t('modules.clientes.fields.phone') }}
                                </h4>
                                <button type="button" id="btnAddTelefone" class="btn-secondary text-sm py-1 px-3 rounded">
                                    <i class="fas fa-plus mr-1"></i>{{ t('common.buttons.add') }}
                                </button>
                            </div>
                            <div id="telefonesContainer" class="space-y-3">
                                <!-- Telefones serao renderizados aqui pelo JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção: Cartões de Crédito -->
                <div class="form-section mb-6" id="secaoCartoes" style="display: none;">
                    <div class="flex items-center justify-between" style="border-bottom: 1px solid #D1D5DB; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                        <h3 class="form-section-title" style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;">
                            <i class="fas fa-credit-card mr-2"></i>{{ t('modules.clientes.credit_cards.title') }}
                        </h3>
                        <button type="button" id="btnToggleAddCard"
                            class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center gap-2"
                            style="display: none;">
                            <i class="fas fa-plus"></i>
                            {{ t('modules.clientes.credit_cards.add_card') }}
                        </button>
                    </div>

                    <div id="cartoesAvisoSalvar" class="bg-slate-50 border border-slate-200 rounded-lg p-5 text-center" style="display: none;">
                        <i class="fas fa-credit-card text-4xl mb-3 block text-slate-300"></i>
                        <p class="text-slate-600 text-lg mb-2">{{ t('modules.clientes.credit_cards.save_first') }}</p>
                        <p class="text-slate-500 text-sm">{{ t('modules.clientes.credit_cards.save_first_detail') }}</p>
                    </div>

                    <div id="cartoesConteudo">
                    <!-- Formulário colapsável de adição de cartão -->
                    <div id="addCardFormWrapper" class="mb-4 bg-slate-50 border border-slate-200 rounded-lg p-4" style="display: none;">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-slate-700">
                                <i class="fas fa-plus-circle mr-1"></i>
                                {{ t('modules.clientes.credit_cards.add_card') }}
                            </h4>
                            <button type="button" id="btnCloseAddCard" class="text-slate-400 hover:text-slate-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- Seleção do gateway -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-3">
                            <div class="md:col-span-6 form-input-group">
                                <label for="cartaoGatewaySelect" class="form-label-group">{{ t('modules.clientes.credit_cards.gateway') }}</label>
                                <select id="cartaoGatewaySelect" class="form-input-group-field">
                                    <option value="">{{ t('modules.clientes.credit_cards.select_gateway') }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Container Stripe Elements -->
                        <div id="stripeCardContainer" style="display: none;">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                                <div class="md:col-span-8 form-input-group">
                                    <label class="form-label-group">{{ t('modules.clientes.credit_cards.card_number') }}</label>
                                    <div id="stripe-card-element" class="form-input-group-field bg-white" style="padding: 4px; min-height: 25px;"></div>
                                </div>
                                <div class="md:col-span-4 flex items-end">
                                    <button type="button" id="btnSalvarCartaoStripe"
                                        class="btn-blue w-full rounded-md text-sm font-medium flex items-center justify-center gap-2"
                                        style="height: 46px; margin-bottom: 3px;">
                                        <i class="fas fa-lock"></i>
                                        <span>{{ t('modules.clientes.credit_cards.save_card') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Container formulário manual (Asaas e outros) -->
                        <div id="manualCardContainer" style="display: none;">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                                <div class="md:col-span-4 form-input-group">
                                    <label for="cartaoTitular" class="form-label-group">{{ t('modules.clientes.credit_cards.card_holder') }}</label>
                                    <input type="text" id="cartaoTitular" class="form-input-group-field" placeholder="{{ t('modules.clientes.credit_cards.card_holder') }}">
                                </div>
                                <div class="md:col-span-4 form-input-group">
                                    <label for="cartaoCpf" class="form-label-group">{{ t('modules.clientes.credit_cards.card_holder_document') }}</label>
                                    <input type="text" id="cartaoCpf" class="form-input-group-field" maxlength="18" placeholder="{{ t('modules.clientes.credit_cards.card_holder_document_placeholder') }}" inputmode="numeric">
                                </div>
                                <div class="md:col-span-4 form-input-group">
                                    <label for="cartaoNumero" class="form-label-group">{{ t('modules.clientes.credit_cards.card_number') }}</label>
                                    <input type="text" id="cartaoNumero" class="form-input-group-field" maxlength="19" placeholder="0000 0000 0000 0000" inputmode="numeric">
                                </div>
                                <div class="md:col-span-2 form-input-group">
                                    <label for="cartaoValidade" class="form-label-group">{{ t('modules.clientes.credit_cards.card_expiry') }}</label>
                                    <input type="text" id="cartaoValidade" class="form-input-group-field" maxlength="5" placeholder="MM/AA" inputmode="numeric">
                                </div>
                                <div class="md:col-span-2 form-input-group">
                                    <label for="cartaoCVV" class="form-label-group">{{ t('modules.clientes.credit_cards.card_cvv') }}</label>
                                    <input type="text" id="cartaoCVV" class="form-input-group-field" maxlength="4" placeholder="000" inputmode="numeric">
                                </div>
                                <div class="md:col-span-2 flex items-end">
                                    <button type="button" id="btnSalvarCartaoManual"
                                        class="btn-blue w-full rounded-md text-sm font-medium flex items-center justify-center gap-2"
                                        style="height: 46px; margin-bottom: 3px;">
                                        <i class="fas fa-lock"></i>
                                        <span>{{ t('modules.clientes.credit_cards.save_card') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de cartões -->
                    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                        <table class="w-full min-w-full divide-y divide-slate-200">
                            <thead class="table-header-custom">
                                <tr>
                                    <th class="table-header">{{ t('modules.clientes.credit_cards.brand') }}</th>
                                    <th class="table-header text-center">{{ t('modules.clientes.credit_cards.last_digits') }}</th>
                                    <th class="table-header">{{ t('modules.clientes.credit_cards.gateway') }}</th>
                                    <th class="table-header w-24 text-center">{{ t('modules.clientes.credit_cards.default') }}</th>
                                    <th class="table-header w-28 text-center">{{ t('common.labels.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="cartoesTableBody" class="bg-white divide-y divide-slate-200">
                                <tr id="cartoesEmpty">
                                    <td colspan="5" class="table-cell text-center text-slate-500 py-8">
                                        <i class="fas fa-credit-card text-4xl mb-2 block text-slate-300"></i>
                                        {{ t('modules.clientes.credit_cards.no_cards') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    </div>
                </div>

                <!-- Seção: Carteira de Motorista -->
                <div class="form-section" id="secaoCNH">
                    <h3 class="form-section-title">{{ t('modules.clientes.sections.driver_license') }}</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="form-input-group">
                            <label for="clienteCNH" class="form-label-group">{{ t('modules.clientes.fields.cnh_number') }}</label>
                            <input type="text" id="clienteCNH" name="cnh_numero" class="form-input-group-field">
                        </div>

                        <div class="form-input-group">
                            <label for="clienteCNHCodSeg" class="form-label-group">{{ t('modules.clientes.fields.cnh_security_code') }}</label>
                            <input type="text" id="clienteCNHCodSeg" name="cnh_codigo_seguranca" class="form-input-group-field">
                        </div>

                        <div class="form-input-group">
                            <label for="clienteCNHCategoria" class="form-label-group">{{ t('modules.clientes.fields.cnh_category') }}</label>
                            <input type="text" id="clienteCNHCategoria" name="cnh_categoria" class="form-input-group-field">
                        </div>

                        <div class="form-input-group">
                            <label for="clienteCNHValidade" class="form-label-group">{{ t('modules.clientes.fields.cnh_expiration') }}</label>
                            <input type="date" id="clienteCNHValidade" name="cnh_validade" class="form-input-group-field">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botões de ação no final -->
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" id="btnCancelarFormCliente"
                    class="btn-secondary hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                    {{ t('common.buttons.cancel') }}
                </button>
                <button type="submit"
                    class="btn-blue py-2 px-4 rounded-md text-sm font-medium shadow hover:shadow-md transition-shadow">
                    {{ t('modules.clientes.messages.save_client') }}
                </button>
            </div>
        </div>

        <!-- Tab: Arquivos -->
        <div id="formArquivosCliente" class="form-tab-content p-4">
            <!-- Aviso para modo adicionar (novo cliente) -->
            <div id="arquivosAvisoSalvar" class="text-center py-12">
                <i class="fas fa-file-upload text-5xl text-slate-300 mb-4 block"></i>
                <p class="text-slate-600 text-lg mb-2">{{ t('modules.clientes.documents.save_first') }}</p>
                <p class="text-slate-500 text-sm mb-6">{{ t('modules.clientes.documents.save_first_detail') }}</p>
                <button type="button" id="btnSalvarEnviarDocs"
                    class="btn-green py-2 px-6 rounded-md text-sm font-medium shadow hover:shadow-md transition-shadow inline-flex items-center">
                    <i class="fas fa-save mr-2"></i>{{ t('modules.clientes.documents.save_and_upload') }}
                </button>
            </div>

            <!-- Conteudo para modo editar (cliente existente) -->
            <div id="arquivosConteudo" class="hidden">
                <!-- Secao de Upload -->
                <div class="form-section mb-6">
                    <h3 class="form-section-title">{{ t('modules.clientes.documents.upload_title') }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                        <div class="md:col-span-3 form-input-group">
                            <label for="arquivoTipo" class="form-label-group">{{ t('modules.clientes.documents.file_type') }}</label>
                            <select id="arquivoTipo" class="form-input-group-field">
                                <option value="">{{ t('modules.clientes.documents.select_type') }}</option>
                                <option value="1">{{ t('modules.clientes.documents.type_cnh') }}</option>
                                <option value="2">{{ t('modules.clientes.documents.type_cpf') }}</option>
                                <option value="3">{{ t('modules.clientes.documents.type_rg_passport') }}</option>
                                <option value="4">{{ t('modules.clientes.documents.type_address_proof') }}</option>
                                <option value="0">{{ t('modules.clientes.documents.type_other') }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-4 form-input-group">
                            <label for="arquivoNome" class="form-label-group">{{ t('modules.clientes.documents.file_name') }}</label>
                            <input type="text" id="arquivoNome" class="form-input-group-field" placeholder="{{ t('modules.clientes.documents.file_name_placeholder') }}">
                        </div>
                        <div class="md:col-span-5 flex gap-3 items-end">
                            <button type="button" id="btnArquivoCamera" class="btn-green px-4 rounded-md text-sm font-medium flex items-center" style="height: 46px;margin-bottom: 3px;">
                                <i class="fas fa-camera mr-2"></i>{{ t('modules.clientes.documents.use_camera') }}
                            </button>
                            <button type="button" id="btnArquivoUpload" class="btn-blue px-4 rounded-md text-sm font-medium flex items-center" style="height: 46px;margin-bottom: 3px;">
                                <i class="fas fa-upload mr-2"></i>{{ t('modules.clientes.documents.select_file') }}
                            </button>
                            <input type="file" id="arquivoFileInput" accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden">
                        </div>
                    </div>
                </div>

                <!-- Tabela de Arquivos -->
                <div class="form-section">
                    <h3 class="form-section-title">{{ t('modules.clientes.documents.sent_files') }}</h3>
                    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                        <table class="w-full min-w-full divide-y divide-slate-200">
                            <thead class="table-header-custom">
                                <tr>
                                    <th class="table-header w-56">{{ t('common.labels.type') }}</th>
                                    <th class="table-header">{{ t('modules.clientes.documents.file_name') }}</th>
                                    <th class="table-header w-28 text-center">{{ t('common.labels.status') }}</th>
                                    <th class="table-header w-32 text-center hidden sm:table-cell">{{ t('common.labels.date') }}</th>
                                    <th class="table-header w-28 text-center">{{ t('common.labels.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="arquivosTableBody" class="bg-white divide-y divide-slate-200">
                                <tr id="arquivosEmpty">
                                    <td colspan="5" class="table-cell text-center text-slate-500 py-8">
                                        <i class="fas fa-folder-open text-4xl mb-2 block text-slate-300"></i>
                                        {{ t('modules.clientes.documents.no_files') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Faturas -->
        <div id="formFaturasCliente" class="form-tab-content p-4">
            <div class="bg-white shadow-md rounded-lg overflow-x-auto">
                <table class="w-full min-w-full divide-y divide-slate-200">
                    <thead class="table-header-custom">
                        <tr>
                            <th class="table-header w-20 text-center">{{ t('modules.clientes.invoices.seq') }}</th>
                            <th class="table-header w-24 hidden sm:table-cell">{{ t('modules.clientes.invoices.code') }}</th>
                            <th class="table-header w-20 hidden lg:table-cell text-center">{{ t('modules.clientes.invoices.installment') }}</th>
                            <th class="table-header">{{ t('modules.clientes.invoices.description') }}</th>
                            <th class="table-header w-24 hidden lg:table-cell text-center">{{ t('modules.clientes.invoices.type') }}</th>
                            <th class="table-header w-24 text-center">{{ t('modules.clientes.invoices.status') }}</th>
                            <th class="table-header w-28 hidden sm:table-cell text-center">{{ t('modules.clientes.invoices.due_date') }}</th>
                            <th class="table-header w-28 hidden lg:table-cell text-center">{{ t('modules.clientes.invoices.payment_date') }}</th>
                            <th class="table-header w-40 text-right">{{ t('modules.clientes.invoices.amount') }}</th>
                            <th class="table-header w-52 text-center">{{ t('common.labels.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="faturasTableBody" class="bg-white divide-y divide-slate-200">
                        <tr>
                            <td colspan="10" class="table-cell text-center text-slate-500 py-8">
                                <i class="fas fa-file-invoice-dollar text-4xl mb-2 block text-slate-300"></i>
                                {{ t('modules.clientes.invoices.save_to_view') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <!-- Modal de escolha de foto -->
    <div id="modalEscolhaFoto" class="modal-overlay">
        <div class="modal-box" style="max-width: 400px;">
            <h3 class="modal-title">{{ t('modules.clientes.camera.choose_photo') }}</h3>
            <p class="modal-message">{{ t('modules.clientes.camera.choose_photo_message') }}</p>
            <div class="modal-actions" style="justify-content: center; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" id="btnEnviarArquivo" class="btn-blue py-2 px-6 rounded-md text-sm font-medium">
                    <i class="fas fa-upload mr-2"></i>{{ t('modules.clientes.camera.upload_file') }}
                </button>
                <button type="button" id="btnUsarCamera" class="btn-green py-2 px-6 rounded-md text-sm font-medium">
                    <i class="fas fa-camera mr-2"></i>{{ t('modules.clientes.camera.use_camera') }}
                </button>
            </div>
            <div class="modal-actions" style="margin-top: 1rem;">
                <button type="button" id="btnCancelarEscolhaFoto" class="btn-secondary">
                    {{ t('common.buttons.cancel') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de preview da câmera -->
    <div id="modalCameraPreview" class="modal-overlay">
        <div class="modal-box" style="max-width: 500px;">
            <h3 class="modal-title">{{ t('modules.clientes.camera.take_photo') }}</h3>
            <div style="margin-bottom: 1rem; display: flex; justify-content: center; align-items: center; background: #000; border-radius: 0.5rem; overflow: hidden;">
                <video id="videoCamera" autoplay playsinline style="width: 100%; max-height: 400px; display: block;"></video>
                <canvas id="canvasCamera" style="display: none;"></canvas>
            </div>
            <div class="modal-actions">
                <button type="button" id="btnCapturarFoto" class="btn-blue">
                    <i class="fas fa-camera mr-2"></i>{{ t('modules.clientes.camera.capture') }}
                </button>
                <button type="button" id="btnCancelarCamera" class="btn-secondary">
                    {{ t('common.buttons.cancel') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de câmera para arquivos (com seleção de dispositivo) -->
    <div id="modalCameraArquivo" class="modal-overlay">
        <div class="modal-box" style="max-width: 600px;">
            <h3 class="modal-title">{{ t('modules.clientes.camera.capture_file') }}</h3>

            <!-- Seletor de câmera -->
            <div class="mb-4">
                <label for="selectCameraArquivo" class="form-label-group">{{ t('modules.clientes.camera.device') }}</label>
                <select id="selectCameraArquivo" class="form-input-group-field">
                    <option value="">{{ t('modules.clientes.camera.loading') }}</option>
                </select>
            </div>

            <!-- Preview da câmera -->
            <div style="margin-bottom: 1rem; display: flex; justify-content: center; align-items: center; background: #000; border-radius: 0.5rem; overflow: hidden; min-height: 300px;">
                <video id="videoCameraArquivo" autoplay playsinline style="width: 100%; max-height: 450px; display: block;"></video>
                <canvas id="canvasCameraArquivo" style="display: none;"></canvas>
            </div>

            <div class="modal-actions">
                <button type="button" id="btnCapturarArquivo" class="btn-blue">
                    <i class="fas fa-camera mr-2"></i>{{ t('modules.clientes.camera.capture') }}
                </button>
                <button type="button" id="btnCancelarCameraArquivo" class="btn-secondary">
                    {{ t('common.buttons.cancel') }}
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
            viewTitle: '<?= t("modules.clientes.view_title_page") ?>',
            editTitle: '<?= t("modules.clientes.edit_title") ?>',
            unsupportedPhotoFormat: '<?= t("modules.clientes.messages.unsupported_photo_format") ?>',
            fileTooLarge: '<?= t("modules.clientes.messages.file_too_large") ?>',
            cameraNotSupported: '<?= t("modules.clientes.messages.camera_not_supported") ?>',
            cameraAccessError: '<?= t("modules.clientes.messages.camera_access_error") ?>',
            cameraPermissionDenied: '<?= t("modules.clientes.messages.camera_permission_denied") ?>',
            cameraNotFound: '<?= t("modules.clientes.messages.camera_not_found") ?>',
            cameraInitializing: '<?= t("modules.clientes.messages.camera_initializing") ?>',
            cameraWaitInit: '<?= t("modules.clientes.messages.camera_wait_init") ?>',
            cameraPermissionDeniedShort: '<?= t("modules.clientes.messages.camera_permission_denied_short") ?>',
            cameraErrorAccess: '<?= t("modules.clientes.messages.camera_error_access") ?>',
            cameraErrorStart: '<?= t("modules.clientes.messages.camera_error_start") ?>',
            noInvoices: '<?= t("modules.clientes.invoices.no_invoices") ?>',
            income: '<?= t("modules.clientes.invoices.income") ?>',
            expense: '<?= t("modules.clientes.invoices.expense") ?>',
            paid: '<?= t("modules.clientes.invoices.paid") ?>',
            pending: '<?= t("modules.clientes.invoices.pending") ?>',
            entry: '<?= t("modules.clientes.invoices.entry") ?>',
            thisEntry: '<?= t("modules.clientes.invoices.this_entry") ?>',
            sendChargeWhatsapp: '<?= t("modules.clientes.invoices.send_charge_whatsapp") ?>',
            paymentLink: '<?= t("modules.clientes.invoices.payment_link") ?>',
            editEntry: '<?= t("modules.clientes.invoices.edit_entry") ?>',
            deleteEntry: '<?= t("modules.clientes.invoices.delete_entry") ?>',
            chargeSent: '<?= t("modules.clientes.messages.charge_sent") ?>',
            errorSendingCharge: '<?= t("modules.clientes.messages.error_sending_charge") ?>',
            errorPaymentLink: '<?= t("modules.clientes.messages.error_payment_link") ?>',
            errorDeletingEntry: '<?= t("modules.clientes.messages.error_deleting_entry") ?>',
            errorUnknown: '<?= t("modules.clientes.messages.error_unknown") ?>',
            errorSending: '<?= t("modules.clientes.messages.error_sending") ?>',
            errorDeleting: '<?= t("modules.clientes.messages.error_deleting") ?>',
            connectionError: '<?= t("modules.clientes.messages.connection_error") ?>',
            errorSaving: '<?= t("modules.clientes.messages.error_saving") ?>',
            fillNameBeforeSave: '<?= t("modules.clientes.messages.fill_name_before_save") ?>',
            noEmail: '<?= t("modules.clientes.messages.no_email") ?>',
            noPhone: '<?= t("modules.clientes.messages.no_phone") ?>',
            emailDescPlaceholder: '<?= t("modules.clientes.messages.email_description_placeholder") ?>',
            phoneDescPlaceholder: '<?= t("modules.clientes.messages.phone_description_placeholder") ?>',
            primaryEmail: '<?= t("modules.clientes.messages.primary_email") ?>',
            primaryPhone: '<?= t("modules.clientes.messages.primary_phone") ?>',
            whatsappPhoneRequired: '<?= t("modules.clientes.messages.whatsapp_phone_required") ?>',
            whatsappNotFound: '<?= t("modules.clientes.messages.whatsapp_not_found") ?>',
            whatsappCheckError: '<?= t("modules.clientes.messages.whatsapp_check_error") ?>',
            tooltipRemove: '<?= t("modules.clientes.tooltips.remove") ?>',
            tooltipViewFile: '<?= t("modules.clientes.tooltips.view_file") ?>',
            tooltipDeleteFile: '<?= t("modules.clientes.tooltips.delete_file") ?>',
            noFiles: '<?= t("modules.clientes.documents.no_files") ?>',
            selectFileTypeFirst: '<?= t("modules.clientes.messages.select_file_type_first") ?>',
            fillFileName: '<?= t("modules.clientes.messages.fill_file_name") ?>',
            fillFileTypeAndName: '<?= t("modules.clientes.messages.fill_file_type_and_name") ?>',
            fileFormatNotAllowed: '<?= t("modules.clientes.messages.file_format_not_allowed") ?>',
            errorUploadingFile: '<?= t("modules.clientes.messages.error_uploading_file") ?>',
            errorDeletingFile: '<?= t("modules.clientes.messages.error_deleting_file") ?>',
            cameraSelectTypeFirst: '<?= t("modules.clientes.messages.camera_select_type_first") ?>',
            cameraFillNameFirst: '<?= t("modules.clientes.messages.camera_fill_name_first") ?>',
            cameraNoneFound: '<?= t("modules.clientes.camera.not_found") ?>',
            cameraDevice: '<?= t("modules.clientes.camera.device") ?>',
            noCards: '<?= t("modules.clientes.credit_cards.no_cards") ?>',
            cardAdded: '<?= t("modules.clientes.messages.card_added") ?>',
            cardDeactivated: '<?= t("modules.clientes.messages.card_deactivated") ?>',
            cardSetDefault: '<?= t("modules.clientes.messages.card_set_default") ?>',
            errorAddingCard: '<?= t("modules.clientes.messages.error_adding_card") ?>',
            cardTokenizationError: '<?= t("modules.clientes.messages.card_tokenization_error") ?>',
            cardProcessing: '<?= t("modules.clientes.credit_cards.processing") ?>',
            saveCard: '<?= t("modules.clientes.credit_cards.save_card") ?>',
            fillCardHolder: '<?= t("modules.clientes.messages.fill_card_holder") ?>',
            fillCardNumber: '<?= t("modules.clientes.messages.fill_card_number") ?>',
            fillCardExpiry: '<?= t("modules.clientes.messages.fill_card_expiry") ?>',
            fillCardCvv: '<?= t("modules.clientes.messages.fill_card_cvv") ?>',
            fillCardGateway: '<?= t("modules.clientes.messages.fill_card_gateway") ?>',
            fillCardHolderDocument: '<?= t("modules.clientes.messages.fill_card_holder_document") ?>',
            tooltipDeactivateCard: '<?= t("modules.clientes.tooltips.deactivate_card") ?>',
            tooltipSetDefaultCard: '<?= t("modules.clientes.tooltips.set_default_card") ?>',
            noGateways: '<?= t("modules.clientes.credit_cards.no_gateways") ?>',
            creditCardTitle: '<?= t("modules.clientes.credit_cards.title") ?>',
            cardDefault: '<?= t("modules.clientes.credit_cards.default") ?>',
        };

        // Botão voltar - Navega de volta para lista de clientes
        document.getElementById('btnVoltarListaClientes')?.addEventListener('click', function() {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: '/pages/clientes'
                }, '*');
            }
        });

        // Gerenciamento de tabs internas
        const formTabButtons = document.querySelectorAll('#formClienteTabsNav .form-tab-button');
        const formTabContents = document.querySelectorAll('.form-tab-content');
        formTabButtons.forEach(button => {
            button.addEventListener('click', () => {
                formTabButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                formTabContents.forEach(content => content.classList.remove('active'));

                const targetId = button.dataset.formTabTarget;
                const targetContent = document.querySelector(targetId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });

        // ========== CONTROLE DE VISIBILIDADE DE CAMPOS POR TIPO ==========

        // Configuração de visibilidade por tipo de cliente
        const visibilidadeCampos = {
            PF: {
                campoDocumento: { show: true, label: '{{ t("modules.clientes.fields.cpf") }}', required: true, placeholder: '000.000.000-00' },
                campoNome: { show: true, label: '{{ t("modules.clientes.fields.full_name") }}', required: true },
                campoNomeFantasia: { show: false },
                campoRG: { show: true, label: '{{ t("modules.clientes.fields.rg") }}' },
                campoNascimento: { show: true },
                campoSexo: { show: true },
                campoEstadoCivil: { show: true },
                campoProfissao: { show: true },
                secaoCNH: { show: true }
            },
            PJ: {
                campoDocumento: { show: true, label: '{{ t("modules.clientes.fields.cnpj") }}', required: true, placeholder: '00.000.000/0000-00' },
                campoNome: { show: true, label: '{{ t("modules.clientes.fields.company_name") }}', required: true },
                campoNomeFantasia: { show: true },
                campoRG: { show: true, label: '{{ t("modules.clientes.fields.ie") }}' },
                campoNascimento: { show: false },
                campoSexo: { show: false },
                campoEstadoCivil: { show: false },
                campoProfissao: { show: false },
                secaoCNH: { show: false }
            },
            ES: {
                campoDocumento: { show: true, label: '{{ t("modules.clientes.fields.passport") }}', required: true, placeholder: '' },
                campoNome: { show: true, label: '{{ t("modules.clientes.fields.full_name") }}', required: true },
                campoNomeFantasia: { show: false },
                campoRG: { show: false },
                campoNascimento: { show: true },
                campoSexo: { show: true },
                campoEstadoCivil: { show: true },
                campoProfissao: { show: true },
                secaoCNH: { show: true }
            }
        };

        function normalizarTipoCliente(tipo) {
            tipo = String(tipo || '').trim().toLowerCase();

            const tipos = {
                pf: 'PF',
                pj: 'PJ',
                estrangeiro: 'ES',
                es: 'ES'
            };

            return tipos[tipo] || tipo;
        }

        // Função para atualizar visibilidade dos campos
        function atualizarVisibilidadeCampos(tipo) {
            tipo = normalizarTipoCliente(tipo);
            if (!tipo || !visibilidadeCampos[tipo]) return;

            const config = visibilidadeCampos[tipo];

            Object.entries(config).forEach(([campoId, opcoes]) => {
                const elemento = document.getElementById(campoId);
                if (!elemento) return;

                // Mostrar/ocultar
                elemento.style.display = opcoes.show ? '' : 'none';

                // Atualizar label se definido
                if (opcoes.label) {
                    const label = elemento.querySelector('label');
                    if (label) {
                        label.textContent = opcoes.label + (opcoes.required ? ' *' : '');
                    }
                }

                // Atualizar placeholder se definido
                if (opcoes.placeholder !== undefined) {
                    const input = elemento.querySelector('input');
                    if (input) {
                        input.placeholder = opcoes.placeholder;
                    }
                }

                // Atualizar required nos inputs
                const inputs = elemento.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (opcoes.show && opcoes.required) {
                        input.required = true;
                    } else if (!opcoes.show) {
                        input.required = false;
                    }
                });
            });
        }

        // Listener no select de tipo
        document.getElementById('clienteTipo')?.addEventListener('change', function() {
            atualizarVisibilidadeCampos(this.value);
        });

        // Aplicar visibilidade ao carregar (modo edição)
        const tipoInicial = document.getElementById('clienteTipo')?.value;
        if (tipoInicial) {
            atualizarVisibilidadeCampos(tipoInicial);
        }

        // ========== FIM DO CONTROLE DE VISIBILIDADE ==========

        // Variáveis para gerenciamento de foto
        const fotoContainer = document.getElementById('fotoClienteContainer');
        const fotoPreview = document.getElementById('fotoClientePreview');
        const fotoInput = document.getElementById('fotoClienteInput');
        const fotoBase64Input = document.getElementById('fotoClienteBase64');
        const modalEscolhaFoto = document.getElementById('modalEscolhaFoto');
        const modalCameraPreview = document.getElementById('modalCameraPreview');
        const videoCamera = document.getElementById('videoCamera');
        const canvasCamera = document.getElementById('canvasCamera');
        let streamCamera = null;

        // Verificar se está dentro de um iframe
        const isInIframe = window.parent !== window;

        // Função para abrir modal
        function abrirModalEscolhaFoto() {
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
                    mostrarAlerta(i18n.unsupportedPhotoFormat);
                    fotoInput.value = '';
                    return;
                }

                // Validar tamanho (máximo 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    mostrarAlerta(i18n.fileTooLarge);
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
                mostrarAlerta(i18n.cameraNotSupported);
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
                    let mensagem = i18n.cameraAccessError;
                    if (err.name === 'NotAllowedError') {
                        mensagem = i18n.cameraPermissionDenied;
                    } else if (err.name === 'NotFoundError') {
                        mensagem = i18n.cameraNotFound;
                    }
                    mostrarAlerta(mensagem);
                });
        }

        // Botão capturar foto
        document.getElementById('btnCapturarFoto').addEventListener('click', function() {
            if (!videoCamera.videoWidth || !videoCamera.videoHeight) {
                mostrarAlerta(i18n.cameraInitializing);
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

        // ========== UTILIDADES ==========

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ========== SISTEMA DE CONTATOS (EMAILS E TELEFONES) ==========

        // Estado dos contatos
        let emails = [];
        let telefones = [];
        let viewMode = false;
        let editMode = false;
        let registroId = null;

        // Verificar se está em modo de edição
        const urlParams = new URLSearchParams(window.location.search);
        registroId = urlParams.get('id');
        viewMode = urlParams.get('mode') === 'view';

        if (registroId) {
            editMode = true;
            carregarDadosCliente(registroId);
        } else {
            // Novo registro: inicializar UI de contatos
            renderizarEmails();
            renderizarTelefones();
        }

        // Carregar dados do cliente para edição
        async function carregarDadosCliente(id) {
            try {
                const result = await API.get('/api/clientes/' + id);

                if (result.success && result.data) {
                    preencherFormularioCliente(result.data);
                    // Carregar faturas do cliente
                    carregarFaturas(id);
                } else {
                    console.error('Erro ao carregar dados:', result.message);
                }
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
            }
        }

        // Carregar faturas do cliente
        async function carregarFaturas(clienteId) {
            try {
                const result = await API.get('/api/clientes/' + clienteId + '/financeiro');
                if (result.success && result.data) {
                    renderizarFaturas(result.data);
                }
            } catch (error) {
                console.error('Erro ao carregar faturas:', error);
            }
        }

        // Renderizar faturas na tabela
        function renderizarFaturas(faturas) {
            const tbody = document.getElementById('faturasTableBody');
            if (!tbody) return;

            if (!faturas || faturas.length === 0) {
                tbody.innerHTML = `<tr>
                    <td colspan="10" class="table-cell text-center text-slate-500 py-8">
                        <i class="fas fa-check-circle text-green-500 text-4xl mb-2 block"></i>
                        ${i18n.noInvoices}
                    </td>
                </tr>`;
                return;
            }

            tbody.innerHTML = faturas.map(f => {
                const dataVenciFormatada = DateHelper.format(f.data_venci);
                const dataPagoFormatada = f.data_pago ? DateHelper.format(f.data_pago) : '-';
                const valorFormatado = Currency.format(f.valor_total || 0, true);
                const isPago = f.pago === 'S';
                const tipoLabel = f.tipo === 'R' ? i18n.income : i18n.expense;
                const tipoClass = f.tipo === 'R' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                const parcelaText = f.total_parcelas > 0 ? `${f.parcela}/${f.total_parcelas}` : '-';

                return `<tr class="hover:bg-slate-50 border-b border-slate-200">
                    <td class="table-cell w-14 text-center text-slate-500">${f.sequencia || '-'}</td>
                    <td class="table-cell w-24 hidden sm:table-cell text-slate-600 text-sm">${f.codigo || '-'}</td>
                    <td class="table-cell w-20 hidden lg:table-cell text-center text-slate-600 text-sm">${parcelaText}</td>
                    <td class="table-cell">
                        <div class="font-medium">${f.descricao || '-'}</div>
                        <div class="sm:hidden text-xs text-slate-500 mt-1">${dataVenciFormatada}</div>
                    </td>
                    <td class="table-cell w-24 hidden lg:table-cell text-center">
                        <span class="px-2 py-1 rounded text-xs font-medium ${tipoClass}">${tipoLabel}</span>
                    </td>
                    <td class="table-cell w-24 text-center">
                        <span class="px-2 py-1 rounded text-xs font-medium ${isPago ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            ${isPago ? i18n.paid : i18n.pending}
                        </span>
                    </td>
                    <td class="table-cell w-28 hidden sm:table-cell text-center text-slate-600 text-sm">${dataVenciFormatada}</td>
                    <td class="table-cell w-28 hidden lg:table-cell text-center text-slate-600 text-sm">${dataPagoFormatada}</td>
                    <td class="table-cell w-32 text-right font-medium">${valorFormatado}</td>
                    <td class="table-cell w-52 text-center whitespace-nowrap">
                        ${!isPago ? `<button type="button" class="btn-icon text-green-600 hover:text-green-800 btn-whatsapp-fatura" data-id="${f.id}" title="${i18n.sendChargeWhatsapp}">
                            <i class="fab fa-whatsapp"></i>
                        </button>` : ''}
                        ${!isPago ? `<button type="button" class="btn-icon text-blue-600 hover:text-blue-800 btn-payment-link-fatura" data-id="${f.id}" title="${i18n.paymentLink}">
                            <i class="fas fa-external-link-alt"></i>
                        </button>` : ''}
                        <button type="button" class="btn-icon text-amber-600 hover:text-amber-800 btn-edit-fatura" data-id="${f.id}" title="${i18n.editEntry}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-delete-fatura" data-id="${f.id}" data-name="${f.descricao || i18n.entry}" title="${i18n.deleteEntry}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');

            // Adicionar event listeners para os botões
            adicionarEventListenersFaturas();
        }

        // Event listeners para botões de ação das faturas
        function adicionarEventListenersFaturas() {
            const tbody = document.getElementById('faturasTableBody');
            if (!tbody) return;

            // Botão Editar
            tbody.querySelectorAll('.btn-edit-fatura').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    window.parent.openOrSwitchToTab('/pages/financeiro/adicionar?id=' + id, i18n.entry, 'fas fa-file-invoice-dollar');
                });
            });

            // Botão Excluir
            tbody.querySelectorAll('.btn-delete-fatura').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name') || i18n.thisEntry;

                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openDeleteModal',
                            recordId: id,
                            recordName: name,
                            recordType: i18n.entry,
                            confirmType: 'text'
                        }, '*');
                    }
                });
            });

            // Botão WhatsApp
            tbody.querySelectorAll('.btn-whatsapp-fatura').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    enviarCobrancaWhatsApp(id);
                });
            });

            // Botão Link de Pagamento
            tbody.querySelectorAll('.btn-payment-link-fatura').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    abrirLinkPagamentoFatura(id, this);
                });
            });
        }

        // Enviar cobrança via WhatsApp
        async function enviarCobrancaWhatsApp(financeiroId) {
            try {
                const result = await API.post('/api/clientes/financeiro/' + financeiroId + '/cobranca');

                if (result.success) {
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openAlert',
                            message: result.message || i18n.chargeSent
                        }, '*');
                    } else {
                        mostrarAlerta(result.message || i18n.chargeSent);
                    }
                } else {
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openValidationModal',
                            errors: [{
                                tabName: i18n.errorSending,
                                fields: [result.message || i18n.errorUnknown]
                            }]
                        }, '*');
                    } else {
                        mostrarAlerta(result.message || i18n.errorSendingCharge);
                    }
                }
            } catch (error) {
                console.error('Erro ao enviar cobrança:', error);
                mostrarAlerta(i18n.connectionError);
            }
        }

        // Abrir link de pagamento
        async function abrirLinkPagamentoFatura(financeiroId, button) {
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;

            try {
                const result = await API.get('/api/financeiro/' + financeiroId + '/link-pagamento');

                if (result.success && result.url) {
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openLinkModal',
                            url: result.url
                        }, '*');
                    } else {
                        window.open(result.url, '_blank');
                    }
                } else {
                    mostrarAlerta(result.message || i18n.errorPaymentLink);
                }
            } catch (error) {
                console.error('Erro ao gerar link:', error);
                mostrarAlerta(i18n.errorPaymentLink);
            } finally {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        }

        // Excluir fatura
        async function excluirFatura(id) {
            try {
                const result = await API.post('/financeiro/' + id + '/excluir');

                if (result.success) {
                    // Recarregar faturas usando id_cliente retornado pela API
                    if (result.id_cliente) {
                        carregarFaturas(result.id_cliente);
                    }
                } else {
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openValidationModal',
                            errors: [{
                                tabName: i18n.errorDeleting,
                                fields: [result.message || i18n.errorUnknown]
                            }]
                        }, '*');
                    } else {
                        mostrarAlerta(result.message || i18n.errorDeletingEntry);
                    }
                }
            } catch (error) {
                console.error('Erro ao excluir fatura:', error);
                mostrarAlerta(i18n.connectionError);
            }
        }

        // Listener para confirmação de exclusão do parent (apenas faturas, ignora quando há customAction)
        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'confirmDelete' && !event.data.customAction) {
                excluirFatura(event.data.recordId);
            }
        });

        // Preencher formulário com dados do cliente
        function preencherFormularioCliente(data) {
            // Dados básicos - Matriz/Filial (chosen-select server-side)
            if (data.id_matriz_filial && data.filial_nome) {
                const select = document.getElementById('clienteMatriz');
                select.innerHTML = `<option value="">{{ t('common.labels.select_option') }}...</option><option value="${data.id_matriz_filial}" selected>${escapeHtml(data.filial_nome)}</option>`;
                select.dispatchEvent(new Event('change'));
            }
            if (data.situacao) document.getElementById('clienteSituacao').value = data.situacao;
            if (data.preferred_locale) document.getElementById('clienteIdioma').value = data.preferred_locale;
            if (data.tipo) {
                const tipoCliente = normalizarTipoCliente(data.tipo);
                document.getElementById('clienteTipo').value = tipoCliente;
                // Aplicar visibilidade após definir o tipo
                atualizarVisibilidadeCampos(tipoCliente);
            }
            if (data.cpf_cnpj) document.getElementById('clienteCPF').value = data.cpf_cnpj;
            if (data.nome_rsocial) document.getElementById('clienteNome').value = data.nome_rsocial;
            if (data.nome_fantasia) document.getElementById('clienteNomeFantasia').value = data.nome_fantasia;
            if (data.rg_ie) document.getElementById('clienteRG').value = data.rg_ie;
            if (data.nascimento) document.getElementById('clienteNascimento').value = data.nascimento;
            if (data.sexo) document.getElementById('clienteSexo').value = data.sexo;
            if (data.estado_civil) document.getElementById('clienteEstadoCivil').value = data.estado_civil;
            if (data.profissao) document.getElementById('clienteProfissao').value = data.profissao;

            // Endereço
            if (data.cep) document.getElementById('cep').value = data.cep;
            if (data.rua) document.getElementById('rua').value = data.rua;
            if (data.numero) document.getElementById('clienteNumero').value = data.numero;
            if (data.complemento) document.getElementById('clienteComplemento').value = data.complemento;
            if (data.bairro) document.getElementById('bairro').value = data.bairro;
            if (data.cidade) document.getElementById('cidade').value = data.cidade;
            if (data.estado) document.getElementById('uf').value = data.estado;
            if (data.pais) {
                const paisSelect = document.getElementById('pais');
                paisSelect.value = data.pais;
                paisSelect.dispatchEvent(new Event('change'));
                if (typeof jQuery !== 'undefined') $(paisSelect).trigger('chosen:updated');
            }

            // CNH
            if (data.cnh_numero) document.getElementById('clienteCNH').value = data.cnh_numero;
            if (data.cnh_codigo_seguranca) document.getElementById('clienteCNHCodSeg').value = data.cnh_codigo_seguranca;
            if (data.cnh_categoria) document.getElementById('clienteCNHCategoria').value = data.cnh_categoria;
            if (data.cnh_validade) document.getElementById('clienteCNHValidade').value = data.cnh_validade;

            // Foto
            if (data.foto_url) {
                fotoPreview.src = data.foto_url;
            }

            // Emails de contato
            if (data.emails && Array.isArray(data.emails)) {
                emails = data.emails.map(e => ({
                    email: e.email || '',
                    descricao: e.descricao || '',
                    principal: e.principal || 'N'
                }));
            }
            renderizarEmails();

            // Telefones de contato
            if (data.telefones && Array.isArray(data.telefones)) {
                telefones = data.telefones.map(t => ({
                    telefone: t.telefone || '',
                    descricao: t.descricao || '',
                    whatsapp: t.whatsapp || 'N',
                    telegram: t.telegram || 'N',
                    sms: t.sms || 'N',
                    principal: t.principal || 'N'
                }));
            }
            renderizarTelefones();

            // Atualizar título se em modo de edição/visualização
            const pageTitle = document.querySelector('.title-page');
            if (pageTitle) {
                pageTitle.textContent = viewMode ? i18n.viewTitle : i18n.editTitle;
            }
        }

        // Renderizar emails
        function renderizarEmails() {
            const container = document.getElementById('emailsContainer');
            container.innerHTML = '';

            if (emails.length === 0) {
                container.innerHTML = '<p class="text-slate-400 text-sm text-center py-4">' + i18n.noEmail + '</p>';
                return;
            }

            emails.forEach((email, idx) => {
                const div = document.createElement('div');
                div.className = 'flex items-center gap-2 bg-white p-3 rounded border';
                div.innerHTML = `
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="email" class="email-input form-input-group-field text-sm"
                            data-idx="${idx}" data-field="email"
                            value="${email.email || ''}" placeholder="email@exemplo.com"
                            ${viewMode ? 'disabled' : ''}>
                        <input type="text" class="email-input form-input-group-field text-sm"
                            data-idx="${idx}" data-field="descricao"
                            value="${email.descricao || ''}" placeholder="${i18n.emailDescPlaceholder}"
                            ${viewMode ? 'disabled' : ''}>
                    </div>
                    <label class="flex items-center gap-1 text-sm whitespace-nowrap cursor-pointer" title="${i18n.primaryEmail}">
                        <input type="radio" name="email_principal" class="email-principal-radio"
                            data-idx="${idx}" ${email.principal === 'S' ? 'checked' : ''}
                            ${viewMode ? 'disabled' : ''}>
                        <i class="fas fa-star ${email.principal === 'S' ? 'text-yellow-500' : 'text-slate-300'}"></i>
                    </label>
                    ${!viewMode ? `
                        <button type="button" class="btn-remove-email text-red-500 hover:text-red-700"
                            data-idx="${idx}" title="${i18n.tooltipRemove}">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                `;
                container.appendChild(div);
            });

            // Event listeners
            container.querySelectorAll('.email-input').forEach(input => {
                input.addEventListener('input', function() {
                    const idx = parseInt(this.dataset.idx);
                    const field = this.dataset.field;
                    emails[idx][field] = this.value;
                });
            });

            container.querySelectorAll('.email-principal-radio').forEach(radio => {
                radio.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.idx);
                    emails.forEach(e => e.principal = 'N');
                    emails[idx].principal = 'S';
                    renderizarEmails();
                });
            });

            container.querySelectorAll('.btn-remove-email').forEach(btn => {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.dataset.idx);
                    emails.splice(idx, 1);
                    if (emails.length > 0 && !emails.some(e => e.principal === 'S')) {
                        emails[0].principal = 'S';
                    }
                    renderizarEmails();
                });
            });
        }

        function obterTelefoneLimpo(idx) {
            const input = document.querySelector(`#telefonesContainer .telefone-input[data-idx="${idx}"][data-field="telefone"]`);
            if (!input) {
                return '';
            }

            return input.value.trim() && input._intlPhone?.getCleanValue
                ? input._intlPhone.getCleanValue()
                : input.value.trim();
        }

        function setTelefoneWhatsappLoading(checkbox, loading) {
            const label = checkbox.closest('label');
            const icon = label?.querySelector('.telefone-whatsapp-icon');

            checkbox.disabled = loading;

            if (!icon) {
                return;
            }

            if (loading) {
                icon.className = 'telefone-whatsapp-icon fas fa-spinner fa-spin text-slate-500';
            } else {
                icon.className = 'telefone-whatsapp-icon fab fa-whatsapp text-green-500';
            }
        }

        async function validarTelefoneWhatsapp(idx, checkbox) {
            const telefone = obterTelefoneLimpo(idx);

            if (!telefone) {
                checkbox.checked = false;
                telefones[idx].whatsapp = 'N';
                mostrarAlerta(i18n.whatsappPhoneRequired);
                return;
            }

            telefones[idx].telefone = telefone;
            setTelefoneWhatsappLoading(checkbox, true);

            try {
                const result = await API.post('/api/whatsapp/check-number', { telefone });

                if (result.success && result.exists) {
                    checkbox.checked = true;
                    telefones[idx].whatsapp = 'S';
                    return;
                }

                checkbox.checked = false;
                telefones[idx].whatsapp = 'N';
                mostrarAlerta(result.message || i18n.whatsappNotFound);
            } catch (error) {
                console.error('Erro ao verificar WhatsApp:', error);
                checkbox.checked = false;
                telefones[idx].whatsapp = 'N';
                mostrarAlerta(i18n.whatsappCheckError);
            } finally {
                setTelefoneWhatsappLoading(checkbox, false);
            }
        }

        // Renderizar telefones
        function renderizarTelefones() {
            const container = document.getElementById('telefonesContainer');
            container.innerHTML = '';

            if (telefones.length === 0) {
                container.innerHTML = '<p class="text-slate-400 text-sm text-center py-4">' + i18n.noPhone + '</p>';
                return;
            }

            telefones.forEach((tel, idx) => {
                const div = document.createElement('div');
                div.className = 'bg-white p-3 rounded border';
                div.innerHTML = `
                    <div class="flex items-center gap-2 mb-2">
                        <input type="tel" class="telefone-input intltel form-input-group-field text-sm flex-1"
                            data-idx="${idx}" data-field="telefone"
                            value="${tel.telefone || ''}"
                            ${viewMode ? 'disabled' : ''}>
                        <input type="text" class="telefone-input form-input-group-field text-sm w-32"
                            data-idx="${idx}" data-field="descricao"
                            value="${tel.descricao || ''}" placeholder="${i18n.phoneDescPlaceholder}"
                            ${viewMode ? 'disabled' : ''}>
                        <label class="flex items-center gap-1 text-sm cursor-pointer" title="${i18n.primaryPhone}">
                            <input type="radio" name="telefone_principal" class="telefone-principal-radio"
                                data-idx="${idx}" ${tel.principal === 'S' ? 'checked' : ''}
                                ${viewMode ? 'disabled' : ''}>
                            <i class="fas fa-star ${tel.principal === 'S' ? 'text-yellow-500' : 'text-slate-300'}"></i>
                        </label>
                        ${!viewMode ? `
                            <button type="button" class="btn-remove-telefone text-red-500 hover:text-red-700"
                                data-idx="${idx}" title="${i18n.tooltipRemove}">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                    <div class="flex items-center gap-4 text-sm ml-4">
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" class="telefone-flag" data-idx="${idx}" data-flag="whatsapp"
                                ${tel.whatsapp === 'S' ? 'checked' : ''} ${viewMode ? 'disabled' : ''}>
                            <i class="telefone-whatsapp-icon fab fa-whatsapp text-green-500"></i> WhatsApp
                        </label>
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" class="telefone-flag" data-idx="${idx}" data-flag="telegram"
                                ${tel.telegram === 'S' ? 'checked' : ''} ${viewMode ? 'disabled' : ''}>
                            <i class="fab fa-telegram text-blue-400"></i> Telegram
                        </label>
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" class="telefone-flag" data-idx="${idx}" data-flag="sms"
                                ${tel.sms === 'S' ? 'checked' : ''} ${viewMode ? 'disabled' : ''}>
                            <i class="fas fa-sms text-purple-500"></i> SMS
                        </label>
                    </div>
                `;
                container.appendChild(div);
            });

            // Reinicializar IntlPhone nos novos campos
            if (typeof IntlPhone !== 'undefined') {
                container.querySelectorAll('.intltel:not(.intl-phone-initialized)').forEach(input => {
                    new IntlPhone(input);
                    input.classList.add('intl-phone-initialized');
                });
            }

            // Event listeners
            container.querySelectorAll('.telefone-input').forEach(input => {
                input.addEventListener('input', function() {
                    const idx = parseInt(this.dataset.idx);
                    const field = this.dataset.field;
                    telefones[idx][field] = this.value;
                });
            });

            container.querySelectorAll('.telefone-principal-radio').forEach(radio => {
                radio.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.idx);
                    telefones.forEach(t => t.principal = 'N');
                    telefones[idx].principal = 'S';
                    renderizarTelefones();
                });
            });

            container.querySelectorAll('.telefone-flag').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.idx);
                    const flag = this.dataset.flag;

                    if (flag === 'whatsapp' && this.checked) {
                        validarTelefoneWhatsapp(idx, this);
                        return;
                    }

                    telefones[idx][flag] = this.checked ? 'S' : 'N';
                });
            });

            container.querySelectorAll('.btn-remove-telefone').forEach(btn => {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.dataset.idx);
                    telefones.splice(idx, 1);
                    if (telefones.length > 0 && !telefones.some(t => t.principal === 'S')) {
                        telefones[0].principal = 'S';
                    }
                    renderizarTelefones();
                });
            });
        }

        // Botão adicionar email
        document.getElementById('btnAddEmail')?.addEventListener('click', function() {
            const isPrimeiro = emails.length === 0;
            emails.push({
                email: '',
                descricao: '',
                principal: isPrimeiro ? 'S' : 'N'
            });
            renderizarEmails();
            setTimeout(() => {
                const inputs = document.querySelectorAll('#emailsContainer .email-input[data-field="email"]');
                if (inputs.length > 0) inputs[inputs.length - 1].focus();
            }, 100);
        });

        // Botão adicionar telefone
        document.getElementById('btnAddTelefone')?.addEventListener('click', function() {
            const isPrimeiro = telefones.length === 0;
            telefones.push({
                telefone: '',
                descricao: '',
                whatsapp: 'N',
                telegram: 'N',
                sms: 'N',
                principal: isPrimeiro ? 'S' : 'N'
            });
            renderizarTelefones();
        });

        function sincronizarContatosDoFormulario() {
            const emailsSincronizados = [];
            document.querySelectorAll('#emailsContainer .email-input[data-field="email"]').forEach(input => {
                const idx = parseInt(input.dataset.idx);
                const descricaoInput = document.querySelector(`#emailsContainer .email-input[data-idx="${idx}"][data-field="descricao"]`);
                const principalInput = document.querySelector(`#emailsContainer .email-principal-radio[data-idx="${idx}"]`);

                emailsSincronizados[idx] = {
                    email: input.value || '',
                    descricao: descricaoInput?.value || '',
                    principal: principalInput?.checked ? 'S' : 'N'
                };
            });
            emails = emailsSincronizados.filter(Boolean);

            const telefonesSincronizados = [];
            document.querySelectorAll('#telefonesContainer .telefone-input[data-field="telefone"]').forEach(input => {
                const idx = parseInt(input.dataset.idx);
                const descricaoInput = document.querySelector(`#telefonesContainer .telefone-input[data-idx="${idx}"][data-field="descricao"]`);
                const principalInput = document.querySelector(`#telefonesContainer .telefone-principal-radio[data-idx="${idx}"]`);
                const flag = (nome) => document.querySelector(`#telefonesContainer .telefone-flag[data-idx="${idx}"][data-flag="${nome}"]`)?.checked ? 'S' : 'N';
                const telefone = input.value.trim() && input._intlPhone?.getCleanValue
                    ? input._intlPhone.getCleanValue()
                    : input.value;

                telefonesSincronizados[idx] = {
                    telefone: telefone || '',
                    descricao: descricaoInput?.value || '',
                    whatsapp: flag('whatsapp'),
                    telegram: flag('telegram'),
                    sms: flag('sms'),
                    principal: principalInput?.checked ? 'S' : 'N'
                };
            });
            telefones = telefonesSincronizados.filter(Boolean);

            if (emails.length > 0 && !emails.some(email => email.principal === 'S')) {
                emails[0].principal = 'S';
            }
            if (telefones.length > 0 && !telefones.some(telefone => telefone.principal === 'S')) {
                telefones[0].principal = 'S';
            }
        }

        // Interceptar submit do formulário para adicionar contatos
        document.getElementById('formCliente')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            sincronizarContatosDoFormulario();

            const formData = new FormData(this);
            const dados = {};

            // Converter FormData para objeto
            for (let [key, value] of formData.entries()) {
                dados[key] = value;
            }

            // Adicionar emails de contato (JSON)
            dados.emails = JSON.stringify(emails);

            // Adicionar telefones de contato (JSON)
            dados.telefones = JSON.stringify(telefones);

            try {
                let url = '/clientes/salvar';
                if (editMode && registroId) {
                    url = '/clientes/' + registroId + '/atualizar';
                }

                const result = await API.post(url, dados);

                if (result.success) {
                    if (!editMode && result.data?.id) {
                        const novoId = result.data.id;
                        if (window.parent !== window) {
                            window.parent.postMessage({
                                action: 'navigate',
                                page: `/pages/clientes/adicionar?id=${novoId}`
                            }, '*');
                        } else {
                            window.location.href = `/pages/clientes/adicionar?id=${novoId}`;
                        }
                        return;
                    }

                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'navigate',
                            page: '/pages/clientes'
                        }, '*');
                    }
                } else {
                    mostrarAlerta(i18n.errorSaving.replace(':message', result.message));
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                mostrarAlerta(i18n.connectionError);
            }
        });

        // Botão cancelar
        document.getElementById('btnCancelarFormCliente')?.addEventListener('click', function() {
            if (window.parent !== window) {
                window.parent.postMessage({
                    action: 'navigate',
                    page: '/pages/clientes'
                }, '*');
            }
        });

        // ========== SISTEMA DE ARQUIVOS DO CLIENTE ==========

        // Funcao helper para mostrar alertas usando o sistema nativo
        function mostrarAlerta(mensagem) {
            if (window.parent !== window) {
                window.parent.postMessage({ action: 'openAlert', message: mensagem }, '*');
            } else if (typeof window.openAlert === 'function') {
                window.openAlert(mensagem);
            } else {
                console.warn(mensagem);
            }
        }

        // Elementos da aba Arquivos
        const arquivosAvisoSalvar = document.getElementById('arquivosAvisoSalvar');
        const arquivosConteudo = document.getElementById('arquivosConteudo');
        const arquivosTableBody = document.getElementById('arquivosTableBody');
        const arquivoTipoSelect = document.getElementById('arquivoTipo');
        const arquivoNomeInput = document.getElementById('arquivoNome');
        const arquivoFileInput = document.getElementById('arquivoFileInput');
        const btnArquivoCamera = document.getElementById('btnArquivoCamera');
        const btnArquivoUpload = document.getElementById('btnArquivoUpload');
        const btnSalvarEnviarDocs = document.getElementById('btnSalvarEnviarDocs');

        // Elementos do modal de câmera para arquivos
        const modalCameraArquivo = document.getElementById('modalCameraArquivo');
        const selectCameraArquivo = document.getElementById('selectCameraArquivo');
        const videoCameraArquivo = document.getElementById('videoCameraArquivo');
        const canvasCameraArquivo = document.getElementById('canvasCameraArquivo');
        let streamCameraArquivo = null;
        let camerasDisponiveis = [];

        // Configurar visibilidade baseada no modo
        function configurarAbaArquivos() {
            if (editMode && registroId) {
                // Modo editar: mostrar conteúdo, ocultar aviso
                arquivosAvisoSalvar?.classList.add('hidden');
                arquivosConteudo?.classList.remove('hidden');
                btnSalvarEnviarDocs?.classList.add('hidden');
                carregarArquivosCliente(registroId);
            } else {
                // Modo adicionar: mostrar aviso, ocultar conteúdo
                arquivosAvisoSalvar?.classList.remove('hidden');
                arquivosConteudo?.classList.add('hidden');
                btnSalvarEnviarDocs?.classList.remove('hidden');
            }
        }

        // Verificar se deve abrir na aba Arquivos (via URL param)
        function verificarAbaInicial() {
            const tabParam = urlParams.get('tab');
            if (tabParam === 'arquivos' && editMode) {
                // Clicar no botão da aba Arquivos
                const btnArquivos = document.querySelector('[data-form-tab-target="#formArquivosCliente"]');
                if (btnArquivos) {
                    btnArquivos.click();
                }
            }
        }

        // Carregar arquivos do cliente
        async function carregarArquivosCliente(clienteId) {
            try {
                const result = await API.get('/api/clientes/' + clienteId + '/arquivos');
                if (result.success) {
                    renderizarArquivos(result.data);
                }
            } catch (error) {
                console.error('Erro ao carregar arquivos:', error);
            }
        }

        // Renderizar tabela de arquivos
        function renderizarArquivos(arquivos) {
            if (!arquivosTableBody) return;

            if (!arquivos || arquivos.length === 0) {
                arquivosTableBody.innerHTML = `<tr id="arquivosEmpty">
                    <td colspan="5" class="table-cell text-center text-slate-500 py-8">
                        <i class="fas fa-folder-open text-4xl mb-2 block text-slate-300"></i>
                        ${i18n.noFiles}
                    </td>
                </tr>`;
                return;
            }

            arquivosTableBody.innerHTML = arquivos.map(arq => {
                const dataFormatada = arq.created_at ? DateHelper.format(arq.created_at.split(' ')[0]) : '-';
                const statusClass = arq.status === 1 ? 'bg-green-100 text-green-800' :
                                   arq.status === 0 ? 'bg-red-100 text-red-800' :
                                   'bg-yellow-100 text-yellow-800';
                const isPdf = arq.arquivo && arq.arquivo.toLowerCase().endsWith('.pdf');

                return `<tr class="hover:bg-slate-50 border-b border-slate-200">
                    <td class="table-cell">
                        <span class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">${escapeHtml(arq.tipo_nome)}</span>
                    </td>
                    <td class="table-cell">
                        <a href="${arq.arquivo_url}" target="_blank" class="text-sky-600 hover:text-sky-800 hover:underline flex items-center gap-2">
                            <i class="fas ${isPdf ? 'fa-file-pdf text-red-500' : 'fa-image text-green-500'}"></i>
                            ${escapeHtml(arq.nome)}
                        </a>
                    </td>
                    <td class="table-cell text-center">
                        <span class="px-2 py-1 rounded text-xs font-medium ${statusClass}">${escapeHtml(arq.status_nome)}</span>
                    </td>
                    <td class="table-cell text-center hidden sm:table-cell text-slate-600 text-sm">${dataFormatada}</td>
                    <td class="table-cell text-center">
                        <a href="${arq.arquivo_url}" target="_blank" class="btn-icon text-sky-600 hover:text-sky-800" title="${i18n.tooltipViewFile}">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-excluir-arquivo" data-id="${arq.id}" data-nome="${escapeHtml(arq.nome)}" title="${i18n.tooltipDeleteFile}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');

            // Event listeners para excluir
            arquivosTableBody.querySelectorAll('.btn-excluir-arquivo').forEach(btn => {
                btn.addEventListener('click', function() {
                    const arquivoId = this.dataset.id;
                    const arquivoNome = this.dataset.nome;

                    // Usar modal de confirmação do sistema
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openDeleteModal',
                            recordId: arquivoId,
                            recordName: arquivoNome,
                            recordType: 'arquivo',
                            confirmType: 'text',
                            customAction: 'excluirArquivoCliente'
                        }, '*');
                    }
                });
            });
        }

        // Função para excluir arquivo (chamada após confirmação)
        async function excluirArquivoCliente(arquivoId) {
            try {
                const result = await API.post(`/api/clientes/${registroId}/arquivos/${arquivoId}/excluir`);
                if (result.success) {
                    carregarArquivosCliente(registroId);
                } else {
                    mostrarAlerta(i18n.errorDeletingFile.replace(':message', result.message));
                }
            } catch (error) {
                console.error('Erro ao excluir arquivo:', error);
                mostrarAlerta(i18n.connectionError);
            }
        }

        // Listener para confirmação de exclusão de arquivo
        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'confirmDelete' && event.data.customAction === 'excluirArquivoCliente') {
                excluirArquivoCliente(event.data.recordId);
            }
        });

        // ========== SISTEMA DE CARTÕES DE CRÉDITO ==========

        const BRAND_ICONS = {
            'VISA': 'fab fa-cc-visa text-blue-600',
            'MASTERCARD': 'fab fa-cc-mastercard text-red-500',
            'AMEX': 'fab fa-cc-amex text-blue-500',
            'DINERS': 'fab fa-cc-diners-club text-blue-400',
            'DISCOVER': 'fab fa-cc-discover text-orange-500',
        };

        function getBrandIcon(brand) {
            return BRAND_ICONS[brand] || 'fas fa-credit-card text-slate-400';
        }

        // Estado do gateway de cartão
        let gatewaysDisponiveis = [];
        let gatewayAtual = null;
        let stripeInstance = null;
        let stripeCardElement = null;
        let stripeJsCarregado = false;
        let tokenizandoCartao = false;

        function configurarSecaoCartoes() {
            const secao = document.getElementById('secaoCartoes');
            if (!secao) return;

            const btnAdd = document.getElementById('btnToggleAddCard');
            const avisoSalvar = document.getElementById('cartoesAvisoSalvar');
            const conteudoCartoes = document.getElementById('cartoesConteudo');

            if (editMode && registroId && !viewMode) {
                secao.style.display = '';
                if (btnAdd) btnAdd.style.display = '';
                if (avisoSalvar) avisoSalvar.style.display = 'none';
                if (conteudoCartoes) conteudoCartoes.style.display = '';
                carregarCartoesCliente(registroId);
                carregarGatewaysCartao();
            } else if (viewMode && registroId) {
                secao.style.display = '';
                if (btnAdd) btnAdd.style.display = 'none';
                if (avisoSalvar) avisoSalvar.style.display = 'none';
                if (conteudoCartoes) conteudoCartoes.style.display = '';
                carregarCartoesCliente(registroId);
            } else {
                secao.style.display = '';
                if (btnAdd) btnAdd.style.display = 'none';
                if (avisoSalvar) avisoSalvar.style.display = '';
                if (conteudoCartoes) conteudoCartoes.style.display = 'none';
            }

            // Botão toggle do formulário
            document.getElementById('btnToggleAddCard')?.addEventListener('click', function() {
                if (gatewaysDisponiveis.length === 0) {
                    mostrarAlerta(i18n.noGateways);
                    return;
                }
                const wrapper = document.getElementById('addCardFormWrapper');
                if (wrapper) wrapper.style.display = wrapper.style.display === 'none' ? '' : 'none';
            });

            // Botão fechar formulário
            document.getElementById('btnCloseAddCard')?.addEventListener('click', fecharFormCartao);

            // Máscara do número do cartão (espaço a cada 4 dígitos)
            document.getElementById('cartaoNumero')?.addEventListener('input', function(e) {
                let v = e.target.value.replace(/\D/g, '').substring(0, 16);
                v = v.replace(/(\d{4})(?=\d)/g, '$1 ');
                e.target.value = v;
            });

            // Máscara validade MM/AA
            document.getElementById('cartaoValidade')?.addEventListener('input', function(e) {
                let v = e.target.value.replace(/\D/g, '').substring(0, 4);
                if (v.length >= 3) v = v.substring(0, 2) + '/' + v.substring(2);
                e.target.value = v;
            });

            // Máscara CVV (somente números)
            document.getElementById('cartaoCVV')?.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
            });

            document.getElementById('cartaoCpf')?.addEventListener('input', function(e) {
                e.target.value = formatarDocumentoCartao(e.target.value);
            });

            // Evento do select de gateway
            document.getElementById('cartaoGatewaySelect')?.addEventListener('change', function() {
                selecionarGateway(this.value);
            });

            // Botões de salvar
            document.getElementById('btnSalvarCartaoStripe')?.addEventListener('click', tokenizarCartaoStripe);
            document.getElementById('btnSalvarCartaoManual')?.addEventListener('click', tokenizarCartaoManual);
        }

        function fecharFormCartao() {
            const wrapper = document.getElementById('addCardFormWrapper');
            if (wrapper) wrapper.style.display = 'none';
            // Resetar estado
            const select = document.getElementById('cartaoGatewaySelect');
            if (select) select.value = '';
            document.getElementById('stripeCardContainer').style.display = 'none';
            document.getElementById('manualCardContainer').style.display = 'none';
            document.getElementById('cartaoTitular') && (document.getElementById('cartaoTitular').value = '');
            document.getElementById('cartaoCpf') && (document.getElementById('cartaoCpf').value = '');
            document.getElementById('cartaoNumero') && (document.getElementById('cartaoNumero').value = '');
            document.getElementById('cartaoValidade') && (document.getElementById('cartaoValidade').value = '');
            document.getElementById('cartaoCVV') && (document.getElementById('cartaoCVV').value = '');
            gatewayAtual = null;
        }

        function somenteDigitosCartao(value) {
            return (value || '').replace(/\D/g, '');
        }

        function formatarDocumentoCartao(value) {
            const digits = somenteDigitosCartao(value).substring(0, 14);
            if (digits.length <= 11) {
                return digits
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }

            return digits
                .replace(/^(\d{2})(\d)/, '$1.$2')
                .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1/$2')
                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
        }

        function preencherDocumentoCartaoDoCliente() {
            const input = document.getElementById('cartaoCpf');
            if (!input || input.value.trim()) return;

            const documentoCliente = document.getElementById('clienteCPF')?.value || '';
            input.value = formatarDocumentoCartao(documentoCliente);
        }

        async function carregarGatewaysCartao() {
            try {
                const result = await API.get('/api/clientes/' + registroId + '/gateways-cartao');
                if (result.success) {
                    gatewaysDisponiveis = result.data || [];
                    const select = document.getElementById('cartaoGatewaySelect');

                    gatewaysDisponiveis.forEach(gw => {
                        const opt = document.createElement('option');
                        opt.value = gw.id;
                        opt.textContent = gw.nome;
                        opt.dataset.code = gw.gateway_code;
                        select.appendChild(opt);
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar gateways:', error);
            }
        }

        function selecionarGateway(gatewayId) {
            const stripeContainer = document.getElementById('stripeCardContainer');
            const manualContainer = document.getElementById('manualCardContainer');
            stripeContainer.style.display = 'none';
            manualContainer.style.display = 'none';
            gatewayAtual = null;

            if (!gatewayId) return;

            gatewayAtual = gatewaysDisponiveis.find(g => g.id == gatewayId);
            if (!gatewayAtual) return;

            if (gatewayAtual.gateway_code === 'stripe' && gatewayAtual.publishable_key) {
                stripeContainer.style.display = '';
                inicializarStripe(gatewayAtual.publishable_key);
            } else {
                manualContainer.style.display = '';
                preencherDocumentoCartaoDoCliente();
            }
        }

        function carregarStripeJs() {
            return new Promise((resolve, reject) => {
                if (stripeJsCarregado) { resolve(); return; }
                if (document.querySelector('script[src*="js.stripe.com"]')) {
                    stripeJsCarregado = true;
                    resolve();
                    return;
                }
                const script = document.createElement('script');
                script.src = 'https://js.stripe.com/v3/';
                script.onload = () => { stripeJsCarregado = true; resolve(); };
                script.onerror = () => reject(new Error('Falha ao carregar Stripe.js'));
                document.head.appendChild(script);
            });
        }

        async function inicializarStripe(publishableKey) {
            try {
                await carregarStripeJs();
                if (!stripeInstance || stripeInstance._apiKey !== publishableKey) {
                    stripeInstance = Stripe(publishableKey);
                }
                const elements = stripeInstance.elements();
                if (stripeCardElement) {
                    stripeCardElement.destroy();
                }
                stripeCardElement = elements.create('card', {
                    style: {
                        base: {
                            fontSize: '14px',
                            color: '#334155',
                            '::placeholder': { color: '#94a3b8' }
                        }
                    },
                    disableLink: true
                });
                stripeCardElement.mount('#stripe-card-element');
            } catch (error) {
                console.error('Erro ao inicializar Stripe:', error);
                mostrarAlerta(i18n.cardTokenizationError);
            }
        }

        function setCardButtonLoading(btnId, loading) {
            const btn = document.getElementById(btnId);
            if (!btn) return;
            btn.disabled = loading;
            const span = btn.querySelector('span');
            const icon = btn.querySelector('i');
            if (loading) {
                if (icon) icon.className = 'fas fa-spinner fa-spin';
                if (span) span.textContent = i18n.cardProcessing;
            } else {
                if (icon) icon.className = 'fas fa-lock';
                if (span) span.textContent = i18n.saveCard;
            }
        }

        async function tokenizarCartaoStripe() {
            if (tokenizandoCartao || !gatewayAtual || !stripeInstance || !stripeCardElement) return;
            tokenizandoCartao = true;
            setCardButtonLoading('btnSalvarCartaoStripe', true);

            try {
                const { paymentMethod, error } = await stripeInstance.createPaymentMethod({
                    type: 'card',
                    card: stripeCardElement,
                });

                if (error) {
                    mostrarAlerta(error.message);
                    return;
                }

                const result = await API.post('/api/clientes/' + registroId + '/cartoes/tokenizar', {
                    gateway_id: gatewayAtual.id,
                    payment_method_id: paymentMethod.id,
                    brand: paymentMethod.card?.brand || null,
                    last_digits: paymentMethod.card?.last4 || null,
                });

                if (result.success) {
                    fecharFormCartao();
                    carregarCartoesCliente(registroId);
                } else {
                    mostrarAlerta(i18n.errorAddingCard.replace(':message', result.message));
                }
            } catch (error) {
                console.error('Erro ao tokenizar cartão Stripe:', error);
                mostrarAlerta(i18n.cardTokenizationError);
            } finally {
                tokenizandoCartao = false;
                setCardButtonLoading('btnSalvarCartaoStripe', false);
            }
        }

        async function tokenizarCartaoManual() {
            if (tokenizandoCartao || !gatewayAtual) return;

            const holder = document.getElementById('cartaoTitular').value.trim();
            const cpf = somenteDigitosCartao(document.getElementById('cartaoCpf')?.value || '');
            const number = document.getElementById('cartaoNumero').value.replace(/\s/g, '');
            const expiry = document.getElementById('cartaoValidade').value.trim();
            const cvv = document.getElementById('cartaoCVV').value.trim();

            if (!holder) { mostrarAlerta(i18n.fillCardHolder); return; }
            if (gatewayAtual.gateway_code === 'asaas' && ![11, 14].includes(cpf.length)) {
                mostrarAlerta(i18n.fillCardHolderDocument);
                return;
            }
            if (!number || number.length < 13) { mostrarAlerta(i18n.fillCardNumber); return; }
            if (!expiry || !/^\d{2}\/\d{2}$/.test(expiry)) { mostrarAlerta(i18n.fillCardExpiry); return; }
            if (!cvv || cvv.length < 3) { mostrarAlerta(i18n.fillCardCvv); return; }

            const [expiryMonth, expiryYear] = expiry.split('/');

            tokenizandoCartao = true;
            setCardButtonLoading('btnSalvarCartaoManual', true);

            try {
                const result = await API.post('/api/clientes/' + registroId + '/cartoes/tokenizar', {
                    gateway_id: gatewayAtual.id,
                    holder: holder,
                    cpf: cpf,
                    cpf_cnpj: cpf,
                    documento_titular: cpf,
                    holder_document: cpf,
                    number: number,
                    expiry_month: expiryMonth,
                    expiry_year: '20' + expiryYear,
                    cvv: cvv,
                });

                if (result.success) {
                    fecharFormCartao();
                    carregarCartoesCliente(registroId);
                } else {
                    mostrarAlerta(i18n.errorAddingCard.replace(':message', result.message));
                }
            } catch (error) {
                console.error('Erro ao tokenizar cartão:', error);
                mostrarAlerta(i18n.cardTokenizationError);
            } finally {
                tokenizandoCartao = false;
                setCardButtonLoading('btnSalvarCartaoManual', false);
            }
        }

        async function carregarCartoesCliente(clienteId) {
            try {
                const result = await API.get('/api/clientes/' + clienteId + '/cartoes');
                if (result.success) {
                    renderizarCartoes(result.data);
                }
            } catch (error) {
                console.error('Erro ao carregar cartões:', error);
            }
        }

        function renderizarCartoes(cartoes) {
            const tbody = document.getElementById('cartoesTableBody');
            if (!tbody) return;

            if (!cartoes || cartoes.length === 0) {
                tbody.innerHTML = `<tr>
                    <td colspan="5" class="table-cell text-center text-slate-500 py-8">
                        <i class="fas fa-credit-card text-4xl mb-2 block text-slate-300"></i>
                        ${i18n.noCards}
                    </td>
                </tr>`;
                return;
            }

            tbody.innerHTML = cartoes.map(c => {
                const iconClass = getBrandIcon(c.bandeira);
                const isPadrao = c.padrao == 1;
                const gatewayLabel = c.gateway ? c.gateway.charAt(0).toUpperCase() + c.gateway.slice(1) : '-';

                return `<tr class="hover:bg-slate-50 border-b border-slate-200">
                    <td class="table-cell">
                        <div class="flex items-center gap-2">
                            <i class="${iconClass} text-xl"></i>
                            <span class="font-medium">${escapeHtml(c.bandeira)}</span>
                        </div>
                    </td>
                    <td class="table-cell text-center text-slate-600">**** ${escapeHtml(c.ultimos_digitos)}</td>
                    <td class="table-cell text-slate-600">${escapeHtml(gatewayLabel)}</td>
                    <td class="table-cell w-24 text-center">
                        ${isPadrao
                            ? '<span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800"><i class="fas fa-star mr-1"></i>' + i18n.cardDefault + '</span>'
                            : '<span class="text-slate-400 text-xs">-</span>'}
                    </td>
                    <td class="table-cell w-28 text-center">
                        ${!viewMode && !isPadrao ? `<button type="button" class="btn-icon text-yellow-500 hover:text-yellow-700 btn-padrao-cartao" data-id="${c.id}" title="${i18n.tooltipSetDefaultCard}">
                            <i class="fas fa-star"></i>
                        </button>` : ''}
                        ${!viewMode ? `<button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-desativar-cartao" data-id="${c.id}" data-name="${escapeHtml(c.bandeira)} **** ${escapeHtml(c.ultimos_digitos)}" title="${i18n.tooltipDeactivateCard}">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                    </td>
                </tr>`;
            }).join('');

            adicionarEventListenersCartoes();
        }

        function adicionarEventListenersCartoes() {
            const tbody = document.getElementById('cartoesTableBody');
            if (!tbody) return;

            tbody.querySelectorAll('.btn-padrao-cartao').forEach(btn => {
                btn.addEventListener('click', function() {
                    definirCartaoPadrao(this.getAttribute('data-id'));
                });
            });

            tbody.querySelectorAll('.btn-desativar-cartao').forEach(btn => {
                btn.addEventListener('click', function() {
                    const cardId = this.getAttribute('data-id');
                    const cardName = this.getAttribute('data-name');
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'openDeleteModal',
                            recordId: cardId,
                            recordName: cardName,
                            recordType: i18n.creditCardTitle,
                            confirmType: 'text',
                            customAction: 'desativarCartaoCliente'
                        }, '*');
                    }
                });
            });
        }

        async function desativarCartaoCliente(cartaoId) {
            try {
                const result = await API.post('/api/clientes/' + registroId + '/cartoes/' + cartaoId + '/desativar');
                if (result.success) {
                    carregarCartoesCliente(registroId);
                } else {
                    mostrarAlerta(result.message || i18n.cardTokenizationError);
                }
            } catch (error) {
                console.error('Erro ao desativar cartão:', error);
                mostrarAlerta(i18n.connectionError);
            }
        }

        async function definirCartaoPadrao(cartaoId) {
            try {
                const result = await API.post('/api/clientes/' + registroId + '/cartoes/' + cartaoId + '/padrao');
                if (result.success) {
                    carregarCartoesCliente(registroId);
                } else {
                    mostrarAlerta(result.message || i18n.cardTokenizationError);
                }
            } catch (error) {
                console.error('Erro ao definir padrão:', error);
                mostrarAlerta(i18n.connectionError);
            }
        }

        // Listener para confirmação de desativação de cartão
        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'confirmDelete' && event.data.customAction === 'desativarCartaoCliente') {
                desativarCartaoCliente(event.data.recordId);
            }
        });

        // ========== FIM SISTEMA DE CARTÕES ==========

        // Listener para receber foto capturada do modal global de câmera
        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'cameraArquivoPhotoResponse') {
                const base64 = event.data.fotoBase64;
                const tipo = event.data.arquivoTipo;
                const nome = event.data.arquivoNome;
                enviarArquivo(base64, nome, tipo);
            }
        });

        // Upload de arquivo via input file
        btnArquivoUpload?.addEventListener('click', function() {
            const tipo = arquivoTipoSelect?.value;
            const nome = arquivoNomeInput?.value?.trim();
            if (!tipo) {
                mostrarAlerta(i18n.selectFileTypeFirst);
                return;
            }
            if (!nome) {
                mostrarAlerta(i18n.fillFileName);
                arquivoNomeInput?.focus();
                return;
            }
            arquivoFileInput?.click();
        });

        // Processar arquivo selecionado
        arquivoFileInput?.addEventListener('change', async function() {
            const file = this.files[0];
            if (!file) return;

            const tipo = arquivoTipoSelect?.value;
            const nome = arquivoNomeInput?.value?.trim();
            if (!tipo || !nome) {
                mostrarAlerta(i18n.fillFileTypeAndName);
                this.value = '';
                return;
            }

            // Validar tamanho (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                mostrarAlerta(i18n.fileTooLarge);
                this.value = '';
                return;
            }

            // Validar tipo
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                mostrarAlerta(i18n.fileFormatNotAllowed);
                this.value = '';
                return;
            }

            // Converter para base64
            const reader = new FileReader();
            reader.onload = async function(e) {
                await enviarArquivo(e.target.result, nome, tipo);
            };
            reader.readAsDataURL(file);

            this.value = '';
        });

        // Enviar arquivo para o servidor
        async function enviarArquivo(base64, nome, tipo) {
            try {
                const result = await API.post(`/api/clientes/${registroId}/arquivos`, {
                    arquivo_base64: base64,
                    nome: nome,
                    tipo: tipo
                });

                if (result.success) {
                    carregarArquivosCliente(registroId);
                    arquivoTipoSelect.value = '';
                    if (arquivoNomeInput) arquivoNomeInput.value = '';
                } else {
                    mostrarAlerta(i18n.errorUploadingFile.replace(':message', result.message));
                }
            } catch (error) {
                console.error('Erro ao enviar arquivo:', error);
                mostrarAlerta(i18n.connectionError);
            }
        }

        // ========== CÂMERA PARA DOCUMENTOS ==========

        // Botão abrir câmera para arquivo
        btnArquivoCamera?.addEventListener('click', async function() {
            const tipo = arquivoTipoSelect?.value;
            const nome = arquivoNomeInput?.value?.trim();
            if (!tipo) {
                mostrarAlerta(i18n.cameraSelectTypeFirst);
                return;
            }
            if (!nome) {
                mostrarAlerta(i18n.cameraFillNameFirst);
                arquivoNomeInput?.focus();
                return;
            }

            // Se estiver em iframe, delegar ao parent para abrir modal em tela cheia
            if (isInIframe) {
                window.parent.postMessage({
                    action: 'openCameraArquivoModal',
                    arquivoTipo: tipo,
                    arquivoNome: nome
                }, '*');
                return;
            }

            // Comportamento local (quando não está em iframe)
            await listarCamerasDisponiveis();
            abrirModalCameraArquivo();
        });

        // Listar câmeras disponíveis
        async function listarCamerasDisponiveis() {
            try {
                // Solicitar permissão primeiro
                await navigator.mediaDevices.getUserMedia({ video: true });

                const devices = await navigator.mediaDevices.enumerateDevices();
                camerasDisponiveis = devices.filter(device => device.kind === 'videoinput');

                selectCameraArquivo.innerHTML = '';
                if (camerasDisponiveis.length === 0) {
                    selectCameraArquivo.innerHTML = '<option value="">' + i18n.cameraNoneFound + '</option>';
                    return;
                }

                camerasDisponiveis.forEach((camera, idx) => {
                    const option = document.createElement('option');
                    option.value = camera.deviceId;
                    option.textContent = camera.label || `${i18n.cameraDevice} ${idx + 1}`;
                    selectCameraArquivo.appendChild(option);
                });

                // Iniciar com a primeira câmera
                if (camerasDisponiveis.length > 0) {
                    await iniciarCameraArquivo(camerasDisponiveis[0].deviceId);
                }
            } catch (err) {
                console.error('Erro ao listar cameras:', err);
                if (err.name === 'NotAllowedError') {
                    mostrarAlerta(i18n.cameraPermissionDeniedShort);
                } else {
                    mostrarAlerta(i18n.cameraErrorAccess.replace(':error', err.message));
                }
            }
        }

        // Trocar câmera selecionada
        selectCameraArquivo?.addEventListener('change', async function() {
            if (this.value) {
                await iniciarCameraArquivo(this.value);
            }
        });

        // Iniciar câmera específica
        async function iniciarCameraArquivo(deviceId) {
            // Parar stream anterior se existir
            if (streamCameraArquivo) {
                streamCameraArquivo.getTracks().forEach(track => track.stop());
            }

            try {
                const constraints = {
                    video: {
                        deviceId: deviceId ? { exact: deviceId } : undefined,
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };

                streamCameraArquivo = await navigator.mediaDevices.getUserMedia(constraints);
                videoCameraArquivo.srcObject = streamCameraArquivo;
            } catch (err) {
                console.error('Erro ao iniciar camera:', err);
                mostrarAlerta(i18n.cameraErrorStart.replace(':error', err.message));
            }
        }

        // Abrir modal de câmera para arquivo
        function abrirModalCameraArquivo() {
            modalCameraArquivo?.classList.add('open');
            document.body.classList.add('modal-open');
        }

        // Fechar câmera de arquivo
        function fecharCameraArquivo() {
            if (streamCameraArquivo) {
                streamCameraArquivo.getTracks().forEach(track => track.stop());
                streamCameraArquivo = null;
            }
            videoCameraArquivo.srcObject = null;
            modalCameraArquivo?.classList.remove('open');
            document.body.classList.remove('modal-open');
        }

        // Botão capturar arquivo
        document.getElementById('btnCapturarArquivo')?.addEventListener('click', async function() {
            if (!videoCameraArquivo.videoWidth || !videoCameraArquivo.videoHeight) {
                mostrarAlerta(i18n.cameraWaitInit);
                return;
            }

            const context = canvasCameraArquivo.getContext('2d');
            canvasCameraArquivo.width = videoCameraArquivo.videoWidth;
            canvasCameraArquivo.height = videoCameraArquivo.videoHeight;
            context.drawImage(videoCameraArquivo, 0, 0, canvasCameraArquivo.width, canvasCameraArquivo.height);

            const base64 = canvasCameraArquivo.toDataURL('image/jpeg', 0.9);
            const tipo = arquivoTipoSelect?.value;
            const nome = arquivoNomeInput?.value?.trim();

            fecharCameraArquivo();
            await enviarArquivo(base64, nome, tipo);
        });

        // Botão cancelar câmera de arquivo
        document.getElementById('btnCancelarCameraArquivo')?.addEventListener('click', function() {
            fecharCameraArquivo();
        });

        // Fechar modal ao clicar fora
        modalCameraArquivo?.addEventListener('click', function(e) {
            if (e.target === modalCameraArquivo) {
                fecharCameraArquivo();
            }
        });

        // ========== BOTÃO "SALVAR E ENVIAR DOCUMENTOS" ==========

        btnSalvarEnviarDocs?.addEventListener('click', async function() {
            // Validar campos obrigatórios
            const nome = document.getElementById('clienteNome')?.value?.trim();
            const documento = document.getElementById('clienteCPF')?.value?.trim();

            if (!nome) {
                mostrarAlerta(i18n.fillNameBeforeSave);
                document.getElementById('clienteNome')?.focus();
                return;
            }

            // Coletar dados do formulário
            const form = document.getElementById('formCliente');
            const formData = new FormData(form);
            const dados = {};

            for (let [key, value] of formData.entries()) {
                dados[key] = value;
            }

            sincronizarContatosDoFormulario();

            dados.emails = JSON.stringify(emails);
            dados.telefones = JSON.stringify(telefones);

            try {
                const result = await API.post('/clientes/salvar', dados);

                if (result.success && result.data?.id) {
                    // Redirecionar para edição com aba Arquivos aberta
                    const novoId = result.data.id;
                    if (window.parent !== window) {
                        window.parent.postMessage({
                            action: 'navigate',
                            page: `/pages/clientes/adicionar?id=${novoId}&tab=arquivos`
                        }, '*');
                    } else {
                        window.location.href = `/pages/clientes/adicionar?id=${novoId}&tab=arquivos`;
                    }
                } else {
                    mostrarAlerta(i18n.errorSaving.replace(':message', result.message || i18n.errorUnknown));
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                mostrarAlerta(i18n.connectionError);
            }
        });

        // Inicializar aba de arquivos e seção de cartões
        configurarAbaArquivos();
        configurarSecaoCartoes();

        // Verificar se deve abrir na aba Arquivos (delay para garantir que o DOM está pronto)
        setTimeout(verificarAbaInicial, 100);

        // Limpar recursos de câmera ao sair
        window.addEventListener('beforeunload', function() {
            fecharCameraArquivo();
        });
    })();
</script>
@endsection
