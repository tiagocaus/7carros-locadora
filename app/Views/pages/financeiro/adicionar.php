@extends('layouts.iframe')

@section('title', t('modules.financeiro.title_singular'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.financeiro.new_title') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('common.buttons.back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formFinanceiro" method="POST">
        @csrf
        <input type="hidden" id="financeiroId" name="id">

        <!-- Nav das Abas -->
        <div class="mb-4 border-b border-slate-300">
            <nav class="flex -mb-px" id="formTabsNav">
                <button type="button" data-form-tab-target="#tabPrincipal" class="form-tab-button active px-4 py-2 text-sm font-medium border-b-2 border-blue-500 text-blue-600">
                    <i class="fas fa-file-invoice-dollar mr-2"></i><?= t('modules.financeiro.tabs.main_data') ?>
                </button>
                <button type="button" data-form-tab-target="#tabParcelamento" class="form-tab-button px-4 py-2 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
                    <i class="fas fa-list-ol mr-2"></i><?= t('modules.financeiro.tabs.installments') ?>
                </button>
            </nav>
        </div>

        <!-- Aba Principal -->
        <div id="tabPrincipal" class="form-tab-content active">

        <!-- Secao 1: Dados Basicos -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-info-circle mr-2"></i><?= t('modules.financeiro.sections.basic_data') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Tipo -->
                <div class="form-input-group">
                    <label for="tipo" class="form-label-group">
                        <?= t('modules.financeiro.fields.type') ?> <span class="text-red-500">*</span>
                    </label>
                    <select id="tipo" name="tipo" class="form-input-group-field" required>
                        <option value="D"><?= t('modules.financeiro.fields.type_expense') ?></option>
                        <option value="R"><?= t('modules.financeiro.fields.type_revenue') ?></option>
                    </select>
                </div>

                <!-- Conta bancária -->
                <div class="form-input-group">
                    <label for="idConta" class="form-label-group"><?= t('modules.financeiro.fields.bank_account') ?> <span class="text-red-500">*</span></label>
                    <select id="idConta" name="id_conta" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contas-bancarias/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>

                <!-- Forma de Pagamento -->
                <div class="form-input-group">
                    <label for="idFormaPagamento" class="form-label-group"><?= t('modules.financeiro.fields.payment_method') ?> <span class="text-red-500">*</span></label>
                    <select id="idFormaPagamento" name="id_forma_pagamento" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/formas-pagamento/select" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>

                <!-- Plano de Contas -->
                <div class="form-input-group">
                    <label for="idPlanoDeConta" class="form-label-group"><?= t('modules.financeiro.fields.chart_of_accounts') ?> <span class="text-red-500">*</span></label>
                    <select id="idPlanoDeConta" name="id_plano_de_conta" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/financeiro/planos-de-contas" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>
            </div>

            <!-- Campo parcela oculto (mantido para salvar o valor) -->
            <input type="hidden" id="parcela" name="parcela">

            <div class="grid grid-cols-1 gap-4 mt-4">
                <!-- Descricao -->
                <div class="form-input-group">
                    <label for="descricao" class="form-label-group"><?= t('modules.financeiro.fields.description') ?> <span class="text-red-500">*</span></label>
                    <textarea id="descricao" name="descricao" class="form-input-group-field" rows="2" maxlength="5000"></textarea>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4 mb-6">
                <!-- Documento -->
                <div class="form-input-group">
                    <label for="documento" class="form-label-group"><?= t('modules.financeiro.fields.document') ?></label>
                    <input type="text" id="documento" name="documento" class="form-input-group-field" maxlength="50">
                </div>

                <!-- Data Criacao -->
                <div class="form-input-group">
                    <label for="dataCriada" class="form-label-group"><?= t('modules.financeiro.fields.creation_date') ?> <span class="text-red-500">*</span></label>
                    <input type="date" id="dataCriada" name="data_criada" class="form-input-group-field">
                </div>

                <!-- Data Vencimento -->
                <div class="form-input-group">
                    <label for="dataVenci" class="form-label-group">
                        <?= t('modules.financeiro.fields.due_date') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="dataVenci" name="data_venci" class="form-input-group-field" required>
                </div>

                <!-- Lancamento Pago -->
                <div class="form-input-group">
                    <label for="pago" class="form-label-group"><?= t('modules.financeiro.fields.is_paid') ?></label>
                    <select id="pago" name="pago" class="form-input-group-field">
                        <option value="N"><?= t('common.labels.no') ?></option>
                        <option value="S"><?= t('common.labels.yes') ?></option>
                        <option value="P"><?= t('modules.financeiro.status.partial_paid') ?></option>
                    </select>
                </div>

                <!-- Data Pagamento -->
                <div class="form-input-group hidden" id="dataPagoContainer">
                    <label for="dataPago" class="form-label-group"><?= t('modules.financeiro.fields.payment_date') ?></label>
                    <input type="date" id="dataPago" name="data_pago" class="form-input-group-field">
                </div>
            </div>

            <div id="pagamentoParcialContainer" class="hidden mt-5 border border-amber-200 bg-amber-50 rounded-md p-4">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h4 class="text-sm font-semibold text-amber-900">
                            <i class="fas fa-hand-holding-dollar mr-2"></i><?= t('modules.financeiro.sections.partial_payment') ?>
                        </h4>
                        <p class="text-xs text-amber-800 mt-1"><?= t('modules.financeiro.messages.partial_difference_hint') ?></p>
                    </div>
                    <span class="text-xs font-medium text-amber-700 bg-amber-100 border border-amber-200 rounded px-2 py-1"><?= t('modules.financeiro.buttons.create_difference') ?></span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="form-input-group">
                        <label for="valorOriginalParcial" class="form-label-group"><?= t('modules.financeiro.fields.original_invoice_value') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="valorOriginalParcial" class="form-input-group-field pl-10 bg-slate-100 input-moeda" value="0,00" disabled>
                        </div>
                    </div>

                    <div class="form-input-group">
                        <label for="valorPagoParcial" class="form-label-group"><?= t('modules.financeiro.fields.amount_received') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="valorPagoParcial" class="form-input-group-field pl-10 input-moeda" value="0,00">
                        </div>
                    </div>

                    <div class="form-input-group">
                        <label for="valorDiferencaParcial" class="form-label-group"><?= t('modules.financeiro.fields.difference_to_create') ?></label>
                        <div class="relative">
                            <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                            <input type="text" id="valorDiferencaParcial" class="form-input-group-field pl-10 bg-slate-100 input-moeda" value="0,00" disabled>
                        </div>
                    </div>

                    <div class="form-input-group">
                        <label for="dataVenciDiferenca" class="form-label-group"><?= t('modules.financeiro.fields.difference_due_date') ?></label>
                        <input type="date" id="dataVenciDiferenca" class="form-input-group-field">
                    </div>
                </div>

                <div class="flex justify-end mt-4">
                    <button type="button" id="btnCriarDiferenca" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i><?= t('modules.financeiro.buttons.create_difference') ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Secao 2: Vinculo(s) -->
        <div class="form-section mb-6 mt-6">
            <h3 class="form-section-title"><i class="fas fa-user mr-2"></i><?= t('modules.financeiro.sections.links') ?> <span class="text-xs font-normal text-slate-500">(<?= t('modules.financeiro.sections.links_hint') ?>)</span></h3>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Matriz/Filial -->
                <div class="form-input-group">
                    <label for="idMatrizFilial" class="form-label-group"><?= t('modules.financeiro.fields.branch') ?> <span class="text-red-500">*</span></label>
                    <select id="idMatrizFilial" name="id_matriz_filial" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>

                <!-- Cliente -->
                <div class="form-input-group" id="clienteContainer">
                    <label for="idCliente" class="form-label-group"><?= t('modules.financeiro.fields.client') ?></label>
                    <select id="idCliente" name="id_cliente" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/clientes/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>

                <!-- Fornecedor -->
                <div class="form-input-group" id="fornecedorContainer">
                    <label for="idFornecedor" class="form-label-group"><?= t('modules.financeiro.fields.supplier') ?></label>
                    <select id="idFornecedor" name="id_fornecedor" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/fornecedores/select" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>

                <!-- Funcionario -->
                <div class="form-input-group">
                    <label for="idFuncionario" class="form-label-group"><?= t('modules.financeiro.fields.employee') ?></label>
                    <select id="idFuncionario" name="id_funcionario" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/funcionarios/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>

                <!-- Veiculo -->
                <div class="form-input-group">
                    <label for="idVeiculo" class="form-label-group"><?= t('modules.financeiro.fields.vehicle') ?></label>
                    <select id="idVeiculo" name="id_veiculo" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/veiculos/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                        <option value=""><?= t('common.labels.select') ?>...</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Secao 3: Valores -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-dollar-sign mr-2"></i><?= t('modules.financeiro.sections.values') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Subtotal -->
                <div class="form-input-group">
                    <label for="valorSubtotal" class="form-label-group">
                        <?= t('modules.financeiro.fields.subtotal') ?> {!! aviso(t('modules.financeiro.hints.valor_subtotal')) !!}
                    </label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valorSubtotal" name="valor_subtotal" class="form-input-group-field pl-10 input-moeda" value="0,00">
                    </div>
                </div>

                <!-- Juros -->
                <div class="form-input-group">
                    <label for="juros" class="form-label-group"><?= t('modules.financeiro.fields.interest') ?></label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="juros" name="juros" class="form-input-group-field pl-10 input-moeda" value="0,00">
                    </div>
                </div>

                <!-- Multa -->
                <div class="form-input-group">
                    <label for="multa" class="form-label-group"><?= t('modules.financeiro.fields.penalty') ?></label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="multa" name="multa" class="form-input-group-field pl-10 input-moeda" value="0,00">
                    </div>
                </div>

                <!-- Desconto -->
                <div class="form-input-group">
                    <label for="desconto" class="form-label-group"><?= t('modules.financeiro.fields.discount') ?></label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="desconto" name="desconto" class="form-input-group-field pl-10 input-moeda" value="0,00">
                    </div>
                </div>

                <!-- Valor Total (calculado) -->
                <div class="form-input-group">
                    <label for="valorTotal" class="form-label-group"><?= t('modules.financeiro.fields.total_value') ?> {!! aviso(t('modules.financeiro.hints.valor_total')) !!}</label>
                    <div class="relative">
                        <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                        <input type="text" id="valorTotal" name="valor_total" class="form-input-group-field pl-10 bg-slate-100 input-moeda" value="0,00" disabled>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secao 4: Itens -->
        <div class="form-section mb-6">
            <h3 class="form-section-title flex justify-between items-center">
                <span><i class="fas fa-list mr-2"></i><?= t('modules.financeiro.sections.items') ?> <span class="text-xs font-normal text-slate-500">(<?= t('modules.financeiro.sections.items_hint') ?>)</span></span>
                <button type="button" id="btnAdicionarItem" class="btn-blue py-1 px-3 text-sm rounded">
                    <i class="fas fa-plus mr-1"></i><?= t('modules.financeiro.buttons.add_item') ?>
                </button>
            </h3>

            <!-- Wrapper para rolagem horizontal em mobile -->
            <div class="overflow-x-auto -mx-5 px-5">
                <!-- Cabecalho dos itens (fixo) -->
                <div id="itensHeader" class="hidden grid grid-cols-12 gap-3 text-xs text-slate-600 font-medium px-3 py-2 bg-slate-100 rounded-t-md border border-b-0 border-slate-200 min-w-[800px]">
                    <div class="col-span-5"><?= t('modules.financeiro.items_header.description') ?></div>
                    <div class="col-span-2"><?= t('modules.financeiro.items_header.vehicle') ?></div>
                    <div class="col-span-2"><?= t('modules.financeiro.items_header.chart_of_accounts') ?></div>
                    <div class="col-span-2"><?= t('modules.financeiro.items_header.value') ?></div>
                    <div class="col-span-1"></div>
                </div>

                <div id="itensContainer" class="border border-slate-200 rounded-b-md empty:border-0 min-w-[800px]">
                    <!-- Itens serao adicionados aqui dinamicamente -->
                </div>
            </div>

            <div id="semItens" class="text-center text-slate-500 py-4">
                <i class="fas fa-info-circle mr-2"></i><?= t('modules.financeiro.messages.no_items') ?>
            </div>
        </div>

        </div><!-- Fim Aba Principal -->

        <!-- Aba Parcelamento -->
        <div id="tabParcelamento" class="form-tab-content">
            <!-- Modo Adicionar: Formulario de geracao -->
            <div id="parcelamentoModoAdicionar">
                <div class="form-section mb-6">
                    <h3 class="form-section-title"><i class="fas fa-calculator mr-2"></i><?= t('modules.financeiro.sections.generate_installments') ?></h3>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3"></i>
                            <div class="text-sm text-blue-800">
                                <p class="font-medium mb-2"><?= t('modules.financeiro.installment_info.title') ?></p>
                                <ol class="list-decimal list-inside space-y-1 text-blue-700">
                                    <li><?= t('modules.financeiro.installment_info.step_1') ?></li>
                                    <li><?= t('modules.financeiro.installment_info.step_2') ?></li>
                                    <li><?= t('modules.financeiro.installment_info.step_3') ?></li>
                                    <li><?= t('modules.financeiro.installment_info.step_4') ?></li>
                                    <li><?= t('modules.financeiro.installment_info.step_5') ?></li>
                                    <li><?= t('modules.financeiro.installment_info.step_6') ?></li>
                                </ol>
                                <p class="mt-2 text-xs text-blue-600">
                                    <i class="fas fa-lightbulb mr-1"></i>
                                    <?= t('modules.financeiro.installment_info.tip') ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <!-- Numero de Parcelas -->
                        <div class="form-input-group">
                            <label for="numParcelas" class="form-label-group">
                                <?= t('modules.financeiro.fields.installment_count') ?> <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="numParcelas" name="config_parcelas[num_parcelas]" min="<?= \App\Models\Financeiro::MIN_PARCELAS ?>" max="<?= \App\Models\Financeiro::MAX_PARCELAS ?>" value="<?= \App\Models\Financeiro::MIN_PARCELAS ?>" class="form-input-group-field">
                        </div>

                        <!-- Valor Total (readonly) -->
                        <div class="form-input-group">
                            <label for="parcelaValorTotal" class="form-label-group"><?= t('modules.financeiro.fields.total_value') ?></label>
                            <div class="relative">
                                <span class="currency-symbol absolute top-1/2 transform -translate-y-1/2 text-slate-500">R$</span>
                                <input type="text" id="parcelaValorTotal" class="form-input-group-field pl-10 bg-slate-100 input-moeda" value="0,00" readonly>
                            </div>
                        </div>

                        <!-- Data da 1a Parcela -->
                        <div class="form-input-group">
                            <label for="dataPrimeiraParcela" class="form-label-group">
                                <?= t('modules.financeiro.fields.first_installment_date') ?> <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="dataPrimeiraParcela" name="config_parcelas[data_primeira]" class="form-input-group-field">
                        </div>

                        <!-- Intervalo -->
                        <div class="form-input-group">
                            <label for="intervaloValor" class="form-label-group">
                                <?= t('modules.financeiro.fields.interval') ?> <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="intervaloValor" name="config_parcelas[intervalo_valor]" min="1" max="12" value="1" class="form-input-group-field">
                        </div>

                        <!-- Tipo de Intervalo -->
                        <div class="form-input-group">
                            <label for="intervaloTipo" class="form-label-group">
                                <?= t('modules.financeiro.fields.interval_type') ?> <span class="text-red-500">*</span>
                            </label>
                            <select id="intervaloTipo" name="config_parcelas[intervalo_tipo]" class="form-input-group-field">
                                <option value="dias"><?= t('modules.financeiro.interval_types.days') ?></option>
                                <option value="semanas"><?= t('modules.financeiro.interval_types.weeks') ?></option>
                                <option value="meses"><?= t('modules.financeiro.interval_types.months') ?></option>
                                <option value="anos"><?= t('modules.financeiro.interval_types.years') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="button" id="btnGerarPreview" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                            <i class="fas fa-eye mr-2"></i><?= t('modules.financeiro.buttons.generate_preview') ?>
                        </button>
                    </div>
                </div>

                <!-- Preview das Parcelas -->
                <div id="previewParcelasContainer" class="form-section mb-6 hidden">
                    <h3 class="form-section-title"><i class="fas fa-list mr-2"></i><?= t('modules.financeiro.sections.installments_preview') ?></h3>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-100">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-slate-600"><?= t('modules.financeiro.table.installment') ?></th>
                                    <th class="px-4 py-2 text-left font-medium text-slate-600"><?= t('modules.financeiro.table.due_date') ?></th>
                                    <th class="px-4 py-2 text-right font-medium text-slate-600"><?= t('modules.financeiro.table.value') ?></th>
                                </tr>
                            </thead>
                            <tbody id="previewParcelasBody">
                                <!-- Linhas geradas dinamicamente -->
                            </tbody>
                            <tfoot class="bg-slate-50 font-medium">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 text-right"><?= t('common.labels.total') ?>:</td>
                                    <td id="previewTotal" class="px-4 py-2 text-right">R$ 0,00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modo Editar: Grid de parcelas existentes -->
            <div id="parcelamentoModoEditar" class="hidden">
                <div class="form-section mb-6">
                    <h3 class="form-section-title flex justify-between items-center">
                        <span><i class="fas fa-list-ol mr-2"></i><?= t('modules.financeiro.sections.installments_list') ?></span>
                        <div class="flex space-x-2">
                            <button type="button" id="btnEditarSelecionados" class="btn-secondary py-1 px-3 text-sm rounded hidden">
                                <i class="fas fa-edit mr-1"></i><?= t('modules.financeiro.buttons.edit_selected') ?>
                            </button>
                            <button type="button" id="btnExcluirSelecionados" class="btn-red py-1 px-3 text-sm rounded hidden">
                                <i class="fas fa-trash mr-1"></i><?= t('modules.financeiro.buttons.delete_selected') ?>
                            </button>
                        </div>
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="tabelaParcelas">
                            <thead class="bg-slate-100">
                                <tr>
                                    <th class="px-2 py-2 text-center w-10">
                                        <input type="checkbox" id="checkTodas" class="rounded border-slate-300">
                                    </th>
                                    <th class="px-4 py-2 text-left font-medium text-slate-600"><?= t('modules.financeiro.table.installment') ?></th>
                                    <th class="px-4 py-2 text-left font-medium text-slate-600"><?= t('modules.financeiro.table.due_date') ?></th>
                                    <th class="px-4 py-2 text-right font-medium text-slate-600"><?= t('modules.financeiro.table.value') ?></th>
                                    <th class="px-4 py-2 text-center font-medium text-slate-600"><?= t('common.labels.status') ?></th>
                                    <th class="px-4 py-2 text-center font-medium text-slate-600"><?= t('common.labels.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody id="parcelasBody">
                                <!-- Linhas carregadas dinamicamente -->
                            </tbody>
                        </table>
                    </div>

                    <div id="semParcelas" class="text-center text-slate-500 py-4 hidden">
                        <i class="fas fa-info-circle mr-2"></i><?= t('modules.financeiro.messages.no_installments') ?>
                    </div>
                </div>
            </div>
        </div><!-- Fim Aba Parcelamento -->

        <!-- Botoes -->
        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-6 rounded-md text-sm font-medium">
                <?= t('common.buttons.cancel') ?>
            </button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-6 rounded-md text-sm font-medium">
                <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
            </button>
        </div>
    </form>
</div>

<!-- Template de Item (oculto) -->
<template id="templateItem">
    <div class="item-row px-3 py-1 bg-white" data-index="__INDEX__">
        <div class="grid grid-cols-12 gap-3 items-top">
            <!-- Descricao -->
            <div class="col-span-5">
                <textarea name="itens[__INDEX__][descricao]" class="w-full text-sm resize-none border border-slate-300 rounded-md px-3 py-2" style="height: 39px;" maxlength="500" placeholder="<?= t('modules.financeiro.messages.item_description_placeholder') ?>"></textarea>
            </div>

            <!-- Veiculo - com chosen-select server-side -->
            <div class="col-span-2">
                <select name="itens[__INDEX__][id_veiculo]" class="form-input-group-field text-sm chosen-select item-veiculo" data-chosen-type="server-side" data-chosen-search-url="/api/veiculos/buscar" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                    <option value=""><?= t('common.labels.select') ?>...</option>
                </select>
            </div>

            <!-- Plano de Contas - com chosen-select server-side -->
            <div class="col-span-2">
                <select name="itens[__INDEX__][id_plano_de_conta]" class="form-input-group-field text-sm chosen-select item-plano" data-chosen-type="server-side" data-chosen-search-url="/api/financeiro/planos-de-contas" data-chosen-placeholder="<?= t('common.labels.select') ?>...">
                    <option value=""><?= t('common.labels.select') ?>...</option>
                </select>
            </div>

            <!-- Valor -->
            <div class="col-span-2">
                <div class="relative">
                    <span class="absolute left-2 top-1/2 transform -translate-y-1/2 text-slate-500 text-xs">R$</span>
                    <input type="text" name="itens[__INDEX__][valor]" class="w-full text-sm pl-8 border border-slate-300 rounded-md item-valor input-moeda" style="height: 39px; padding-right: 10px;" value="0,00">
                </div>
            </div>

            <!-- Botao Remover -->
            <div class="col-span-1 text-right">
                <button type="button" class="btn-remover-item text-red-600 hover:text-red-800 p-2" title="<?= t('modules.financeiro.buttons.remove_item') ?>">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>
@endsection

@section('scripts')
<script>
(function () {
    const i18n = {
        newTitle: '<?= t("modules.financeiro.new_title") ?>',
        editTitle: '<?= t("modules.financeiro.edit_title") ?>',
        notFound: '<?= t("modules.financeiro.messages.not_found") ?>',
        loadError: '<?= t("modules.financeiro.messages.load_single_error") ?>',
        saveError: '<?= t("modules.financeiro.messages.save_error") ?>',
        subtotalConverted: '<?= t("modules.financeiro.messages.subtotal_converted") ?>',
        informFirstDate: '<?= t("modules.financeiro.messages.inform_first_date") ?>',
        valueMustBePositive: '<?= t("modules.financeiro.messages.value_must_be_positive") ?>',
        installmentCountRange: '<?= t("modules.financeiro.messages.installment_count_range", ["min" => \App\Models\Financeiro::MIN_PARCELAS, "max" => \App\Models\Financeiro::MAX_PARCELAS]) ?>',
        selectInstallment: '<?= t("modules.financeiro.messages.select_installment") ?>',
        informFieldUpdate: '<?= t("modules.financeiro.messages.inform_field_update") ?>',
        installmentsUpdated: '<?= t("modules.financeiro.messages.installments_updated") ?>',
        installmentsUpdateError: <?= js_t("modules.financeiro.messages.installments_update_error") ?>,
        installmentsDeleted: '<?= t("modules.financeiro.messages.installments_deleted") ?>',
        installmentsDeleteError: <?= js_t("modules.financeiro.messages.installments_delete_error") ?>,
        statusPaid: '<?= t("modules.financeiro.status.paid") ?>',
        statusPending: '<?= t("modules.financeiro.status.pending") ?>',
        editModalTitle: '<?= t("modules.financeiro.installment_modal.edit_title") ?>',
        newDueDate: '<?= t("modules.financeiro.installment_modal.new_due_date") ?>',
        dueDateHint: '<?= t("modules.financeiro.installment_modal.due_date_hint") ?>',
        paymentStatus: '<?= t("modules.financeiro.installment_modal.payment_status") ?>',
        keepCurrent: '<?= t("modules.financeiro.installment_modal.keep_current") ?>',
        recordTypeInstallments: '<?= t("modules.financeiro.record_types.installments") ?>',
        requiredField: '<?= t("modules.financeiro.messages.required_field") ?>',
        fillAtLeastOneLink: '<?= t("modules.financeiro.messages.fill_at_least_one_link") ?>',
        informValueOrItem: '<?= t("modules.financeiro.messages.inform_value_or_item") ?>',
        paymentDateRequired: '<?= t("modules.financeiro.messages.payment_date_required") ?>',
        saveBeforePartial: '<?= t("modules.financeiro.messages.save_before_partial") ?>',
        partialValueInvalid: '<?= t("modules.financeiro.messages.partial_value_invalid") ?>',
        partialPaymentDateRequired: '<?= t("modules.financeiro.messages.partial_payment_date_required") ?>',
        partialDifferenceDueRequired: '<?= t("modules.financeiro.messages.partial_difference_due_required") ?>',
        partialSuccess: '<?= t("modules.financeiro.messages.partial_success") ?>',
        partialError: '<?= t("modules.financeiro.messages.partial_error") ?>',
        partialUseButton: '<?= t("modules.financeiro.messages.partial_use_button") ?>',
        vehicleLinkItemMismatch: '<?= t("modules.financeiro.messages.vehicle_link_item_mismatch") ?>',
        select: '<?= t("common.labels.select") ?>',
        edit: '<?= t("common.buttons.edit") ?>',
    };

    // Estado
    let isEditMode = false;
    let financeiroId = null;
    let itemIndex = 0;
    let valorSubtotalOriginal = 0;      // Guarda valor_subtotal original para conversao
    let valorTotalOriginal = 0;         // Guarda valor_total original para baixa parcial
    let pagoOriginal = 'N';             // Status original do lancamento carregado
    let itemPrincipalConvertido = false; // Flag para evitar criar multiplos itens automaticos
    let lancamentoTinhaItens = false;    // Flag para saber se lancamento ja tinha itens

    // ===== NAVEGACAO =====

    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: page }, '*');
        } else {
            window.location.href = page;
        }
    }

    // ===== INICIALIZACAO =====

    async function init() {
        // Verificar modo edicao
        const urlParams = new URLSearchParams(window.location.search);
        financeiroId = urlParams.get('id');
        isEditMode = !!financeiroId;

        if (isEditMode) {
            document.getElementById('pageTitle').textContent = i18n.editTitle;
            document.getElementById('financeiroId').value = financeiroId;
        }

        // Definir datas padrao (hoje)
        const hoje = DateHelper.todayInput();
        document.getElementById('dataCriada').value = hoje;
        document.getElementById('dataVenci').value = hoje;
        document.getElementById('dataPago').value = hoje;

        // Se modo edicao, carregar dados do lancamento
        if (isEditMode) {
            await carregarLancamento(financeiroId);
        }

        // Configurar eventos
        configurarEventos();
    }

    // ===== MODAL DE ALERTA (via parent) =====

    function mostrarAlerta(mensagem, callbackAction = null) {
        window.parent.postMessage({
            action: 'openAlert',
            message: mensagem,
            callback: callbackAction
        }, '*');
    }

    // Escutar mensagens do parent
    window.addEventListener('message', function(event) {
        if (event.data && event.data.action === 'alertModalClosed') {
            if (event.data.callback === 'navegarParaFinanceiro') {
                navegarPara('/pages/financeiro');
            }
        }

        // Confirmacao de exclusao em lote de parcelas
        if (event.data && event.data.action === 'confirmDelete') {
            // Verificar se eh exclusao de parcelas (recordId contem virgula = lote)
            if (event.data.recordId && typeof event.data.recordId === 'string' &&
                event.data.recordId.includes(',')) {
                excluirParcelasLote();
            }
        }

        // Confirmacao de edicao em lote de parcelas
        if (event.data && event.data.action === 'editBatchConfirmed') {
            if (event.data.callbackId === 'editarParcelasLote') {
                const { data_venci, pago } = event.data.values;

                if (!data_venci && !pago) {
                    Toast.warning(i18n.informFieldUpdate);
                    return;
                }

                const dados = { ids: parcelasSelecionadas };
                if (data_venci) dados.data_venci = data_venci;
                if (pago) dados.pago = pago;

                API.post('/financeiro/parcelas/atualizar-lote', dados)
                    .then(result => {
                        if (result.success) {
                            Toast.success(i18n.installmentsUpdated.replace(':count', result.data.atualizados));
                            carregarParcelas();
                        } else {
                            Toast.error(result.message || i18n.installmentsUpdateError);
                        }
                    })
                    .catch(e => {
                        console.error('Erro ao atualizar parcelas:', e);
                        Toast.error(i18n.installmentsUpdateError);
                    });
            }
        }
    });

    // ===== CARREGAR LANCAMENTO =====

    async function carregarLancamento(id) {
        try {
            const result = await API.get(`/api/financeiro/${id}`);
            if (result.success && result.data) {
                preencherFormulario(result.data);
            } else {
                mostrarAlerta(i18n.notFound, 'navegarParaFinanceiro');
            }
        } catch (e) {
            console.error('Erro ao carregar lancamento:', e);
            mostrarAlerta(i18n.loadError);
        }
    }

    function preencherFormulario(dados) {
        document.getElementById('tipo').value = dados.tipo || 'D';
        document.getElementById('documento').value = dados.documento || '';
        document.getElementById('parcela').value = dados.parcela || '';
        document.getElementById('descricao').value = dados.descricao || '';
        document.getElementById('dataCriada').value = dados.data_criada || '';
        document.getElementById('dataVenci').value = dados.data_venci || '';

        // Campos server-side: adicionar option com valor atual antes de selecionar
        if (dados.id_matriz_filial && dados.filial_nome) {
            const selectMatrizFilial = document.getElementById('idMatrizFilial');
            selectMatrizFilial.innerHTML = `<option value="">${i18n.select}...</option><option value="${dados.id_matriz_filial}" selected>${escapeHtml(dados.filial_nome)}</option>`;
            selectMatrizFilial.dispatchEvent(new Event('change'));
        }

        if (dados.id_cliente && dados.cliente_nome) {
            const selectCliente = document.getElementById('idCliente');
            selectCliente.innerHTML = `<option value="">${i18n.select}...</option><option value="${dados.id_cliente}" selected>${escapeHtml(dados.cliente_nome)}</option>`;
            selectCliente.dispatchEvent(new Event('change'));
        }

        if (dados.id_fornecedor && dados.fornecedor_nome) {
            const selectFornecedor = document.getElementById('idFornecedor');
            selectFornecedor.innerHTML = `<option value="">${i18n.select}...</option><option value="${dados.id_fornecedor}" selected>${escapeHtml(dados.fornecedor_nome)}</option>`;
            selectFornecedor.dispatchEvent(new Event('change'));
        }

        if (dados.id_funcionario && dados.funcionario_nome) {
            const selectFuncionario = document.getElementById('idFuncionario');
            selectFuncionario.innerHTML = `<option value="">${i18n.select}...</option><option value="${dados.id_funcionario}" selected>${escapeHtml(dados.funcionario_nome)}</option>`;
            selectFuncionario.dispatchEvent(new Event('change'));
        }

        if (dados.id_veiculo) {
            const selectVeiculo = document.getElementById('idVeiculo');
            const textoVeiculo = [dados.veiculo_placa, dados.veiculo_modelo]
                .filter(Boolean)
                .join(' - ');
            selectVeiculo.innerHTML = `<option value="">${i18n.select}...</option><option value="${dados.id_veiculo}" selected>${escapeHtml(textoVeiculo || dados.id_veiculo)}</option>`;
            selectVeiculo.dispatchEvent(new Event('change'));
        }

        if (dados.id_plano_de_conta && dados.plano_conta_descricao) {
            const selectPlano = document.getElementById('idPlanoDeConta');
            const texto = dados.plano_conta_hierarquia ? `${dados.plano_conta_hierarquia} - ${dados.plano_conta_descricao}` : dados.plano_conta_descricao;
            selectPlano.innerHTML = `<option value="">${i18n.select}...</option><option value="${dados.id_plano_de_conta}" selected>${escapeHtml(texto)}</option>`;
            selectPlano.dispatchEvent(new Event('change'));
        }

        if (dados.id_conta && dados.conta_descricao) {
            const selectConta = document.getElementById('idConta');
            selectConta.innerHTML = `<option value="">${i18n.select}...</option><option value="${dados.id_conta}" selected>${escapeHtml(dados.conta_descricao)}</option>`;
            selectConta.dispatchEvent(new Event('change'));
        }

        if (dados.id_forma_pagamento && dados.forma_pagamento_descricao) {
            const selectFormaPagamento = document.getElementById('idFormaPagamento');
            selectFormaPagamento.innerHTML = `<option value="">${i18n.select}...</option><option value="${dados.id_forma_pagamento}" selected>${escapeHtml(dados.forma_pagamento_descricao)}</option>`;
            selectFormaPagamento.dispatchEvent(new Event('change'));
        }

        pagoOriginal = dados.pago || 'N';
        document.getElementById('pago').value = pagoOriginal;
        if (dados.pago === 'S') {
            document.getElementById('dataPagoContainer').classList.remove('hidden');
            document.getElementById('dataPago').value = dados.data_pago || '';
        } else {
            document.getElementById('dataPagoContainer').classList.add('hidden');
        }

        document.getElementById('valorSubtotal').value = formatarMoedaInput(dados.valor_subtotal || 0);
        document.getElementById('juros').value = formatarMoedaInput(dados.juros || 0);
        document.getElementById('multa').value = formatarMoedaInput(dados.multa || 0);
        document.getElementById('desconto').value = formatarMoedaInput(dados.desconto || 0);
        valorTotalOriginal = parseFloat(dados.valor_total || 0);
        document.getElementById('valorOriginalParcial').value = formatarMoedaInput(valorTotalOriginal);
        document.getElementById('dataVenciDiferenca').value = dados.data_venci || '';

        // Guardar valor_subtotal original para possivel conversao em item
        valorSubtotalOriginal = parseFloat(dados.valor_subtotal) || 0;

        // Carregar itens
        if (dados.itens && dados.itens.length > 0) {
            lancamentoTinhaItens = true;
            dados.itens.forEach(item => adicionarItem(item));
        } else {
            lancamentoTinhaItens = false;
        }

        // Atualizar valor principal e total
        atualizarValorSubtotal();
        atualizarVisibilidadePagamentoParcial();
        calcularDiferencaParcial();
    }

    // ===== EVENTOS =====

    function configurarEventos() {
        // Voltar
        document.getElementById('btnVoltar')?.addEventListener('click', () => navegarPara('/pages/financeiro'));
        document.getElementById('btnCancelar')?.addEventListener('click', () => navegarPara('/pages/financeiro'));

        // Pago mudou
        document.getElementById('pago')?.addEventListener('change', function() {
            const container = document.getElementById('dataPagoContainer');
            if (this.value === 'S' || this.value === 'P') {
                container.classList.remove('hidden');
                // Se não tiver data preenchida, usar data de hoje
                const dataPago = document.getElementById('dataPago');
                if (!dataPago.value) {
                    const hoje = DateHelper.todayInput();
                    dataPago.value = hoje;
                }
            } else {
                container.classList.add('hidden');
            }
            atualizarVisibilidadePagamentoParcial();
        });

        document.getElementById('valorPagoParcial')?.addEventListener('input', calcularDiferencaParcial);
        document.getElementById('btnCriarDiferenca')?.addEventListener('click', criarDiferencaPagamentoParcial);

        // Adicionar item
        document.getElementById('btnAdicionarItem')?.addEventListener('click', () => adicionarItem());

        // Submit formulario
        document.getElementById('formFinanceiro')?.addEventListener('submit', salvarFormulario);

        // Mascara monetaria usando Currency.js
        document.querySelectorAll('.input-moeda').forEach(input => {
            Currency.applyMask(input);
            input.addEventListener('input', calcularTotal);
        });

        // Eventos das abas
        configurarAbas();

        // Eventos de parcelamento
        configurarParcelamento();
    }

    // ===== ABAS =====

    function configurarAbas() {
        const buttons = document.querySelectorAll('.form-tab-button');
        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.dataset.formTabTarget;

                // Remover active de todos os botoes
                buttons.forEach(b => {
                    b.classList.remove('active', 'border-blue-500', 'text-blue-600');
                    b.classList.add('border-transparent', 'text-slate-500');
                });

                // Adicionar active ao botao clicado
                this.classList.add('active', 'border-blue-500', 'text-blue-600');
                this.classList.remove('border-transparent', 'text-slate-500');

                // Ocultar todos os conteudos
                document.querySelectorAll('.form-tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });

                // Mostrar conteudo alvo
                document.querySelector(targetId).classList.add('active');

                // Se clicou na aba de parcelamento, atualizar dados
                if (targetId === '#tabParcelamento') {
                    atualizarAbaParcelamento();
                }
            });
        });
    }

    function atualizarAbaParcelamento() {
        // Atualizar valor total no campo de parcelamento
        const valorTotal = calcularValorFormulario();
        document.getElementById('parcelaValorTotal').value = formatarMoedaInput(valorTotal);

        // Data da primeira parcela = data de vencimento por padrao
        const dataVenci = document.getElementById('dataVenci').value;
        if (dataVenci && !document.getElementById('dataPrimeiraParcela').value) {
            document.getElementById('dataPrimeiraParcela').value = dataVenci;
        }

        // Alternar entre modo adicionar e editar
        if (isEditMode) {
            document.getElementById('parcelamentoModoAdicionar').classList.add('hidden');
            document.getElementById('parcelamentoModoEditar').classList.remove('hidden');
            carregarParcelas();
        } else {
            document.getElementById('parcelamentoModoAdicionar').classList.remove('hidden');
            document.getElementById('parcelamentoModoEditar').classList.add('hidden');
        }
    }

    function calcularValorFormulario() {
        // valorSubtotal já contém a soma dos itens quando há itens (calculado em atualizarValorSubtotal)
        const valorSubtotal = parseMoeda(document.getElementById('valorSubtotal').value);
        const juros = parseMoeda(document.getElementById('juros').value);
        const multa = parseMoeda(document.getElementById('multa').value);
        const desconto = parseMoeda(document.getElementById('desconto').value);

        return valorSubtotal + juros + multa - desconto;
    }

    // ===== PARCELAMENTO =====

    let parcelasPreview = [];
    let parcelasExistentes = [];
    let parcelasSelecionadas = [];
    const MIN_PARCELAS = <?= \App\Models\Financeiro::MIN_PARCELAS ?>;
    const MAX_PARCELAS = <?= \App\Models\Financeiro::MAX_PARCELAS ?>;

    function configurarParcelamento() {
        // Gerar preview
        document.getElementById('btnGerarPreview')?.addEventListener('click', gerarPreviewParcelas);

        // Eventos de selecao
        document.getElementById('checkTodas')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('#parcelasBody input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            atualizarBotoesSelecao();
        });

        // Editar selecionados
        document.getElementById('btnEditarSelecionados')?.addEventListener('click', abrirModalEdicaoLote);

        // Excluir selecionados
        document.getElementById('btnExcluirSelecionados')?.addEventListener('click', confirmarExclusaoLote);
    }

    function gerarPreviewParcelas() {
        const numParcelasInput = document.getElementById('numParcelas');
        const numParcelas = Number(numParcelasInput.value);
        const valorTotal = calcularValorFormulario();
        const dataPrimeira = document.getElementById('dataPrimeiraParcela').value;
        const intervaloValor = parseInt(document.getElementById('intervaloValor').value) || 1;
        const intervaloTipo = document.getElementById('intervaloTipo').value;

        if (!Number.isInteger(numParcelas) || numParcelas < MIN_PARCELAS || numParcelas > MAX_PARCELAS) {
            Toast.warning(i18n.installmentCountRange);
            numParcelasInput.focus();
            return;
        }

        if (!dataPrimeira) {
            Toast.warning(i18n.informFirstDate);
            return;
        }

        if (valorTotal <= 0) {
            Toast.warning(i18n.valueMustBePositive);
            return;
        }

        // Calcular valor de cada parcela
        const valorParcela = valorTotal / numParcelas;
        const valorParcelaArredondado = Math.floor(valorParcela * 100) / 100;
        const diferenca = valorTotal - (valorParcelaArredondado * numParcelas);

        parcelasPreview = [];
        let dataAtual = dataPrimeira;

        for (let i = 0; i < numParcelas; i++) {
            let valor = valorParcelaArredondado;
            // Adicionar diferenca na ultima parcela
            if (i === numParcelas - 1) {
                valor = valorParcelaArredondado + diferenca;
            }

            parcelasPreview.push({
                parcela: i + 1,
                totalParcelas: numParcelas,
                dataVenci: dataAtual,
                valor: valor
            });

            // Calcular proxima data
            dataAtual = adicionarIntervalo(dataAtual, intervaloValor, intervaloTipo);
        }

        renderizarPreview();
    }

    function adicionarIntervalo(data, valor, tipo) {
        switch (tipo) {
            case 'dias':
                return DateHelper.addDays(data, valor);
            case 'semanas':
                return DateHelper.addDays(data, valor * 7);
            case 'meses':
                return DateHelper.addMonths(data, valor);
            case 'anos':
                return DateHelper.addMonths(data, valor * 12);
        }
        return data;
    }

    function formatarDataISO(data) {
        return data;
    }

    function formatarDataBR(dataISO) {
        if (!dataISO) return '';
        const [ano, mes, dia] = dataISO.split('-');
        return `${dia}/${mes}/${ano}`;
    }

    function renderizarPreview() {
        const tbody = document.getElementById('previewParcelasBody');
        const container = document.getElementById('previewParcelasContainer');
        const tabParcelamento = document.getElementById('tabParcelamento');
        let total = 0;

        // Limpar inputs hidden de parcelas anteriores
        document.querySelectorAll('input[name^="parcelas["]').forEach(el => el.remove());

        tbody.innerHTML = parcelasPreview.map((p, index) => {
            total += p.valor;

            // Criar inputs hidden para auditoria dentro da aba Parcelamento
            const hiddenParcela = document.createElement('input');
            hiddenParcela.type = 'hidden';
            hiddenParcela.name = `parcelas[${index}][parcela]`;
            hiddenParcela.value = `${p.parcela}/${p.totalParcelas}`;
            hiddenParcela.dataset.label = 'Parcela';
            tabParcelamento.appendChild(hiddenParcela);

            const hiddenVenci = document.createElement('input');
            hiddenVenci.type = 'hidden';
            hiddenVenci.name = `parcelas[${index}][data_venci]`;
            hiddenVenci.value = p.dataVenci;
            hiddenVenci.dataset.label = 'Vencimento';
            tabParcelamento.appendChild(hiddenVenci);

            const hiddenValor = document.createElement('input');
            hiddenValor.type = 'hidden';
            hiddenValor.name = `parcelas[${index}][valor]`;
            hiddenValor.value = p.valor;
            hiddenValor.dataset.label = 'Valor';
            tabParcelamento.appendChild(hiddenValor);

            return `
                <tr class="border-b border-slate-100">
                    <td class="px-4 py-2">${p.parcela}/${p.totalParcelas}</td>
                    <td class="px-4 py-2">${formatarDataBR(p.dataVenci)}</td>
                    <td class="px-4 py-2 text-right">R$ ${formatarMoedaInput(p.valor)}</td>
                </tr>
            `;
        }).join('');

        document.getElementById('previewTotal').textContent = `R$ ${formatarMoedaInput(total)}`;
        container.classList.remove('hidden');
    }

    // ===== PARCELAS - MODO EDICAO =====

    async function carregarParcelas() {
        if (!financeiroId) return;

        try {
            const result = await API.get(`/api/financeiro/${financeiroId}/parcelas`);
            if (result.success) {
                parcelasExistentes = result.data || [];
                renderizarParcelas();
            }
        } catch (e) {
            console.error('Erro ao carregar parcelas:', e);
        }
    }

    function renderizarParcelas() {
        const tbody = document.getElementById('parcelasBody');
        const semParcelas = document.getElementById('semParcelas');

        if (parcelasExistentes.length === 0) {
            tbody.innerHTML = '';
            semParcelas.classList.remove('hidden');
            return;
        }

        semParcelas.classList.add('hidden');

        tbody.innerHTML = parcelasExistentes.map(p => {
            const status = p.pago === 'S'
                ? `<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">${i18n.statusPaid}</span>`
                : `<span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">${i18n.statusPending}</span>`;

            return `
                <tr class="border-b border-slate-100 hover:bg-slate-50" data-id="${p.id}">
                    <td class="px-2 py-2 text-center">
                        <input type="checkbox" class="parcela-check rounded border-slate-300" value="${p.id}">
                    </td>
                    <td class="px-4 py-2">${p.parcela}/${p.total_parcelas}</td>
                    <td class="px-4 py-2">${formatarDataBR(p.data_venci)}</td>
                    <td class="px-4 py-2 text-right">R$ ${formatarMoedaInput(p.valor_subtotal || 0)}</td>
                    <td class="px-4 py-2 text-center">${status}</td>
                    <td class="px-4 py-2 text-center">
                        <button type="button" class="btn-editar-parcela text-blue-600 hover:text-blue-800 p-1" data-id="${p.id}" title="${i18n.edit}">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        // Eventos dos checkboxes
        document.querySelectorAll('.parcela-check').forEach(cb => {
            cb.addEventListener('change', atualizarBotoesSelecao);
        });

        // Eventos dos botoes de editar
        document.querySelectorAll('.btn-editar-parcela').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                navegarPara(`/pages/financeiro/adicionar?id=${id}`);
            });
        });
    }

    function atualizarBotoesSelecao() {
        const selecionados = document.querySelectorAll('.parcela-check:checked');
        const btnEditar = document.getElementById('btnEditarSelecionados');
        const btnExcluir = document.getElementById('btnExcluirSelecionados');

        if (selecionados.length > 0) {
            btnEditar.classList.remove('hidden');
            btnExcluir.classList.remove('hidden');
        } else {
            btnEditar.classList.add('hidden');
            btnExcluir.classList.add('hidden');
        }

        parcelasSelecionadas = Array.from(selecionados).map(cb => parseInt(cb.value));
    }

    function abrirModalEdicaoLote() {
        if (parcelasSelecionadas.length === 0) {
            Toast.warning(i18n.selectInstallment);
            return;
        }

        // Usar modal global do parent via postMessage
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openEditBatchModal',
                title: i18n.editModalTitle.replace(':count', parcelasSelecionadas.length),
                callbackId: 'editarParcelasLote',
                fields: [
                    {
                        name: 'data_venci',
                        label: i18n.newDueDate,
                        type: 'date',
                        hint: i18n.dueDateHint
                    },
                    {
                        name: 'pago',
                        label: i18n.paymentStatus,
                        type: 'select',
                        options: [
                            { value: '', label: i18n.keepCurrent },
                            { value: 'S', label: i18n.statusPaid },
                            { value: 'N', label: i18n.statusPending }
                        ]
                    }
                ]
            }, '*');
        }
    }

    function confirmarExclusaoLote() {
        if (parcelasSelecionadas.length === 0) {
            Toast.warning(i18n.selectInstallment);
            return;
        }

        // Usar modal global do parent via postMessage
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openDeleteModal',
                recordId: parcelasSelecionadas.join(','),
                recordName: `${parcelasSelecionadas.length} parcela(s)`,
                recordType: i18n.recordTypeInstallments,
                confirmType: 'text'
            }, '*');
        }
    }

    async function excluirParcelasLote() {
        try {
            const result = await API.post('/financeiro/parcelas/excluir-lote', {
                ids: parcelasSelecionadas
            });

            if (result.success) {
                Toast.success(i18n.installmentsDeleted.replace(':count', result.data.excluidos));
                carregarParcelas();
            } else {
                Toast.error(result.message || i18n.installmentsDeleteError);
            }
        } catch (e) {
            console.error('Erro ao excluir parcelas:', e);
            Toast.error(i18n.installmentsDeleteError);
        }
    }


    // ===== ITENS =====

    function adicionarItem(dados = null) {
        const container = document.getElementById('itensContainer');
        const template = document.getElementById('templateItem');
        const semItens = document.getElementById('semItens');
        const itensHeader = document.getElementById('itensHeader');

        // Conversao automatica: se modo edicao, sem itens originais, e valor_subtotal > 0
        // Ao adicionar primeiro item, cria automaticamente um item com o valor_subtotal original
        const temItensExistentes = container.querySelectorAll('.item-row').length > 0;
        if (isEditMode && !temItensExistentes && !lancamentoTinhaItens && !itemPrincipalConvertido && valorSubtotalOriginal > 0) {
            itemPrincipalConvertido = true;
            // Criar item automatico com valor principal original
            adicionarItem({
                descricao: i18n.subtotalConverted,
                valor: valorSubtotalOriginal,
                id_veiculo: '',
                id_plano_de_conta: ''
            });
        }

        // Clonar template
        const clone = template.content.cloneNode(true);
        const itemRow = clone.querySelector('.item-row');

        // Obter HTML e substituir indice
        const itemHtml = itemRow.outerHTML.replace(/__INDEX__/g, itemIndex);

        // Adicionar ao container
        container.insertAdjacentHTML('beforeend', itemHtml);

        // Ajustar padding e margem conforme posição do item
        const novoItem = container.querySelector(`.item-row[data-index="${itemIndex}"]`);
        const gridDiv = novoItem.querySelector('.grid');
        if (container.querySelectorAll('.item-row').length === 1) {
            // Primeiro item: manter topo, reduzir baixo
            novoItem.classList.remove('py-1');
            novoItem.classList.add('pt-2', 'pb-1');
            gridDiv.classList.add('mt-2');
        }
        // Demais itens: mantém py-1 do template (topo e baixo reduzidos)

        // Mostrar cabecalho
        itensHeader.classList.remove('hidden');

        // Configurar select de plano de contas (server-side)
        const selectPlano = container.querySelector(`[name="itens[${itemIndex}][id_plano_de_conta]"]`);

        // Se ha dados, adicionar opcao pre-selecionada para plano de contas
        if (dados && dados.id_plano_de_conta) {
            const textoPlano = dados.plano_conta_hierarquia
                ? `${dados.plano_conta_hierarquia} - ${dados.plano_conta_descricao}`
                : dados.plano_conta_descricao;
            selectPlano.innerHTML = `<option value="">${i18n.select}...</option><option value="${dados.id_plano_de_conta}" selected>${escapeHtml(textoPlano)}</option>`;
        }

        // Configurar select de veiculos
        const selectVeiculo = container.querySelector(`[name="itens[${itemIndex}][id_veiculo]"]`);

        // Se ha dados, adicionar opcao pre-selecionada para veiculo
        if (dados && dados.id_veiculo) {
            const textoVeiculo = [dados.veiculo_placa, dados.veiculo_modelo]
                .filter(Boolean)
                .join(' - ');
            selectVeiculo.innerHTML = `<option value="">${i18n.select}...</option><option value="${dados.id_veiculo}" selected>${escapeHtml(textoVeiculo || dados.id_veiculo)}</option>`;
        }

        // Preencher dados se fornecidos
        if (dados) {
            selectVeiculo.value = dados.id_veiculo || '';
            container.querySelector(`[name="itens[${itemIndex}][descricao]"]`).value = dados.descricao || '';
            container.querySelector(`[name="itens[${itemIndex}][valor]"]`).value = formatarMoedaInput(dados.valor || 0);
        }

        // Inicializar chosen-select nos selects
        new ChosenSelect(selectPlano, {
            type: 'server-side',
            searchUrl: '/api/financeiro/planos-de-contas',
            placeholder: `${i18n.select}...`
        });
        new ChosenSelect(selectVeiculo, {
            type: 'server-side',
            searchUrl: '/api/veiculos/buscar',
            placeholder: `${i18n.select}...`
        });

        // Configurar mascara monetaria no novo campo usando Currency.js
        const inputValor = container.querySelector(`[name="itens[${itemIndex}][valor]"]`);
        Currency.applyMask(inputValor);
        inputValor.addEventListener('input', atualizarValorSubtotal);

        // Configurar botao remover
        const btnRemover = container.querySelector(`.item-row[data-index="${itemIndex}"] .btn-remover-item`);
        btnRemover.addEventListener('click', function() {
            this.closest('.item-row').remove();
            atualizarVisibilidadeSemItens();
            atualizarValorSubtotal();
        });

        itemIndex++;
        semItens.classList.add('hidden');
        atualizarValorSubtotal();
    }

    function atualizarVisibilidadeSemItens() {
        const container = document.getElementById('itensContainer');
        const semItens = document.getElementById('semItens');
        const itensHeader = document.getElementById('itensHeader');

        if (container.querySelectorAll('.item-row').length === 0) {
            semItens.classList.remove('hidden');
            itensHeader.classList.add('hidden');
        }
    }

    // ===== CALCULOS =====

    function obterSomaItens() {
        let soma = 0;
        document.querySelectorAll('.item-valor').forEach(input => {
            soma += parseMoeda(input.value);
        });
        return soma;
    }

    function atualizarValorSubtotal() {
        const inputValorSubtotal = document.getElementById('valorSubtotal');
        const somaItens = obterSomaItens();
        const temItens = document.querySelectorAll('.item-row').length > 0;

        if (isEditMode) {
            // Modo edicao: desabilitado
            inputValorSubtotal.disabled = true;
            inputValorSubtotal.classList.add('bg-slate-100');
            // Se tem itens, recalcular valor_subtotal = soma dos itens
            if (temItens) {
                inputValorSubtotal.value = formatarMoedaInput(somaItens);
            }
        } else if (temItens) {
            // Modo insercao com itens: desabilitado, valor = soma dos itens
            inputValorSubtotal.value = formatarMoedaInput(somaItens);
            inputValorSubtotal.disabled = true;
            inputValorSubtotal.classList.add('bg-slate-100');
        } else {
            // Modo insercao sem itens: habilitado para edicao manual
            inputValorSubtotal.disabled = false;
            inputValorSubtotal.classList.remove('bg-slate-100');
        }

        calcularTotal();
    }

    function calcularTotal() {
        const valorSubtotal = parseMoeda(document.getElementById('valorSubtotal').value);
        const juros = parseMoeda(document.getElementById('juros').value);
        const multa = parseMoeda(document.getElementById('multa').value);
        const desconto = parseMoeda(document.getElementById('desconto').value);

        // Valor total = valor_subtotal + juros + multa - desconto
        // (valor_subtotal ja inclui a soma dos itens quando ha itens)
        const total = valorSubtotal + juros + multa - desconto;
        document.getElementById('valorTotal').value = formatarMoedaInput(total);
    }

    function parseMoeda(valor) {
        return Currency.parse(valor);
    }

    function formatarMoedaInput(valor) {
        return Currency.format(parseFloat(valor) || 0, false);
    }

    // ===== PAGAMENTO PARCIAL =====

    function atualizarVisibilidadePagamentoParcial() {
        const container = document.getElementById('pagamentoParcialContainer');
        const pago = document.getElementById('pago')?.value || 'N';

        if (isEditMode && pagoOriginal !== 'S' && pago === 'P') {
            container?.classList.remove('hidden');
            calcularDiferencaParcial();
        } else {
            container?.classList.add('hidden');
        }
    }

    function calcularDiferencaParcial() {
        const valorPago = parseMoeda(document.getElementById('valorPagoParcial')?.value || '0');
        const diferenca = Math.max(0, valorTotalOriginal - valorPago);
        const inputDiferenca = document.getElementById('valorDiferencaParcial');

        if (inputDiferenca) {
            inputDiferenca.value = formatarMoedaInput(diferenca);
        }
    }

    async function criarDiferencaPagamentoParcial() {
        if (!isEditMode || !financeiroId) {
            Toast.warning(i18n.saveBeforePartial);
            return;
        }

        const valorPago = parseMoeda(document.getElementById('valorPagoParcial')?.value || '0');
        const dataPago = document.getElementById('dataPago')?.value || '';
        const dataVenciDiferenca = document.getElementById('dataVenciDiferenca')?.value || '';

        if (valorPago <= 0 || valorPago >= valorTotalOriginal) {
            Toast.warning(i18n.partialValueInvalid);
            return;
        }

        if (!dataPago) {
            Toast.warning(i18n.partialPaymentDateRequired);
            return;
        }

        if (!dataVenciDiferenca) {
            Toast.warning(i18n.partialDifferenceDueRequired);
            return;
        }

        try {
            const result = await API.post(`/financeiro/${financeiroId}/baixa-parcial`, {
                valor_pago: valorPago,
                data_pago: dataPago,
                data_venci_diferenca: dataVenciDiferenca
            });

            if (result.success) {
                Toast.success(result.message || i18n.partialSuccess);
                navegarPara('/pages/financeiro');
            } else {
                Toast.error(result.message || i18n.partialError);
            }
        } catch (e) {
            console.error('Erro ao registrar baixa parcial:', e);
            Toast.error(i18n.partialError);
        }
    }

    // ===== VALIDACAO =====

    function validarFormulario() {
        const erros = [];

        // Campos obrigatorios simples
        const camposObrigatorios = [
            { id: 'idConta', nome: i18n.select },
            { id: 'idFormaPagamento', nome: i18n.select },
            { id: 'idPlanoDeConta', nome: i18n.select },
            { id: 'descricao', nome: i18n.select },
            { id: 'dataCriada', nome: i18n.select },
            { id: 'idMatrizFilial', nome: i18n.select }
        ];

        camposObrigatorios.forEach(campo => {
            const elemento = document.getElementById(campo.id);
            if (!elemento || !elemento.value || elemento.value.trim() === '') {
                const label = elemento?.closest('.form-input-group')?.querySelector('.form-label-group')?.textContent?.replace('*', '').trim() || campo.id;
                erros.push(i18n.requiredField.replace(':field', label));
            }
        });

        // Validar vinculo (pelo menos um: Cliente, Fornecedor, Funcionario ou Veiculo)
        const idCliente = document.getElementById('idCliente')?.value || '';
        const idFornecedor = document.getElementById('idFornecedor')?.value || '';
        const idFuncionario = document.getElementById('idFuncionario')?.value || '';
        const idVeiculo = document.getElementById('idVeiculo')?.value || '';

        if (!idCliente && !idFornecedor && !idFuncionario && !idVeiculo) {
            erros.push(i18n.fillAtLeastOneLink);
        }

        if (idVeiculo) {
            const temItemComVeiculoDiferente = Array.from(document.querySelectorAll('.item-row')).some(row => {
                const index = row.dataset.index;
                const idVeiculoItem = document.querySelector(`[name="itens[${index}][id_veiculo]"]`)?.value || '';
                const descricaoItem = document.querySelector(`[name="itens[${index}][descricao]"]`)?.value?.trim() || '';
                const valorItem = parseMoeda(document.querySelector(`[name="itens[${index}][valor]"]`)?.value || '0');
                const itemValido = valorItem > 0 || descricaoItem !== '';
                return itemValido && idVeiculoItem && idVeiculoItem !== idVeiculo;
            });

            if (temItemComVeiculoDiferente) {
                erros.push(i18n.vehicleLinkItemMismatch);
            }
        }

        // Validar valor principal (obrigatorio quando nao ha itens)
        const itensRows = document.querySelectorAll('.item-row');
        const valorSubtotal = parseMoeda(document.getElementById('valorSubtotal').value);
        if (itensRows.length === 0 && valorSubtotal <= 0) {
            erros.push(i18n.informValueOrItem);
        }

        // Validar data de pagamento (obrigatoria se pago = 'S')
        const pago = document.getElementById('pago')?.value || 'N';
        const dataPago = document.getElementById('dataPago')?.value || '';

        if (pago === 'P') {
            erros.push(i18n.partialUseButton);
        }

        if (pago === 'S' && !dataPago) {
            erros.push(i18n.paymentDateRequired);
        }

        return erros;
    }

    // ===== SALVAR =====

    async function salvarFormulario(e) {
        e.preventDefault();

        // Validar campos obrigatorios
        const erros = validarFormulario();
        if (erros.length > 0) {
            Toast.warning(erros[0]);
            return;
        }

        const form = document.getElementById('formFinanceiro');
        const formData = new FormData(form);
        const dados = Object.fromEntries(formData.entries());

        // Converter valores monetarios
        dados.valor_subtotal = parseMoeda(dados.valor_subtotal);
        dados.juros = parseMoeda(dados.juros);
        dados.multa = parseMoeda(dados.multa);
        dados.desconto = parseMoeda(dados.desconto);

        // Obter valor do select pago
        dados.pago = document.getElementById('pago').value || 'N';

        // Coletar itens
        const itens = [];
        document.querySelectorAll('.item-row').forEach(row => {
            const index = row.dataset.index;
            itens.push({
                id_plano_de_conta: document.querySelector(`[name="itens[${index}][id_plano_de_conta]"]`)?.value || '',
                id_veiculo: document.querySelector(`[name="itens[${index}][id_veiculo]"]`)?.value || '',
                descricao: document.querySelector(`[name="itens[${index}][descricao]"]`)?.value || '',
                valor: parseMoeda(document.querySelector(`[name="itens[${index}][valor]"]`)?.value)
            });
        });
        dados.itens = itens;

        // Se tem parcelas no preview (modo adicionar), incluir no envio
        if (!isEditMode && parcelasPreview.length > 0) {
            dados.parcelas = parcelasPreview;
        }

        try {
            const url = isEditMode
                ? `/financeiro/${financeiroId}/atualizar`
                : '/financeiro/salvar';

            const result = await API.post(url, dados);

            if (result.success) {
                navegarPara('/pages/financeiro');
            } else {
                Toast.error(result.message || i18n.saveError);
            }
        } catch (error) {
            console.error('Erro:', error);
            Toast.error(i18n.saveError);
        }
    }

    // ===== HELPERS =====

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== MOEDA POR FILIAL =====
    const SYMBOL_MAP = { BRL: 'R$', USD: '$', EUR: '€', GBP: '£' };
    const filialConfigCache = {};

    async function atualizarSimbolosParaFilial(filialId) {
        if (!filialId) return;
        let cfg = filialConfigCache[filialId];
        if (!cfg) {
            try {
                const res = await API.get(`/api/matrizes-filiais/${filialId}`);
                const d = res?.data || res || {};
                cfg = {
                    currency_code: d.currency_code || 'BRL',
                    symbol: SYMBOL_MAP[d.currency_code || 'BRL'] || (d.currency_code || 'R$'),
                };
                filialConfigCache[filialId] = cfg;
            } catch (e) {
                return;
            }
        }
        document.querySelectorAll('.currency-symbol').forEach(el => {
            el.textContent = cfg.symbol;
        });
    }

    document.getElementById('idMatrizFilial')?.addEventListener('change', function() {
        atualizarSimbolosParaFilial(this.value);
    });

    // Inicializar
    init();
})();
</script>
@endsection
