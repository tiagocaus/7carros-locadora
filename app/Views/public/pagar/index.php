@extends('layouts.public')

@section('title', 'Pagamento - ' . ($link['empresa_nome'] ?? '7Carros'))

@section('content')
<?php
$hojePagamento = today();
$vencimentoFinanceiro = $link['financeiro_vencimento'] ?? null;
$vencimentoGateway = \App\Helpers\DateHelper::normalizeDueDateForGateway($vencimentoFinanceiro, $hojePagamento);
?>
<!-- Header com logo da empresa -->
<div class="text-center mb-6">
    @if(!empty($link['empresa_logo']))
        <img src="<?= \App\Helpers\FileHelper::url($link['empresa_logo'], $link['empresa_chave']) ?>"
             alt="<?= htmlspecialchars($link['empresa_nome'] ?? 'Empresa') ?>"
             class="h-16 mx-auto mb-4 object-contain">
    @else
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
            <i class="fas fa-building text-2xl text-blue-600"></i>
        </div>
    @endif
    <h1 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($link['empresa_nome'] ?? 'Empresa') ?></h1>
    @if(!empty($link['empresa_cidade']) && !empty($link['empresa_uf']))
        <p class="text-sm text-slate-500"><?= htmlspecialchars($link['empresa_cidade']) ?>/<?= htmlspecialchars($link['empresa_uf']) ?></p>
    @endif
</div>

<!-- Card principal -->
<div class="card p-6">
    <!-- Informacoes do pagamento -->
    <div class="border-b border-slate-200 pb-4 mb-4">
        <div class="flex justify-between items-start mb-3">
            <div>
                <p class="text-sm text-slate-500">Descricao</p>
                <p class="font-medium text-slate-800"><?= htmlspecialchars($link['descricao'] ?? $link['financeiro_descricao'] ?? 'Pagamento') ?></p>
            </div>
            <div class="text-right">
                <p class="text-sm text-slate-500">Valor</p>
                <p class="text-2xl font-bold text-green-600"><?= $valor_formatado ?></p>
            </div>
        </div>

        @if(!empty($link['cliente_nome']))
            <div class="bg-slate-50 rounded-lg p-3 mt-3">
                <p class="text-xs text-slate-500 mb-1">Cliente</p>
                <p class="text-sm font-medium text-slate-700"><?= htmlspecialchars($link['cliente_nome']) ?></p>
                @if(!empty($link['cliente_documento']))
                    <p class="text-xs text-slate-500"><?= htmlspecialchars($link['cliente_documento']) ?></p>
                @endif
            </div>
        @endif

        @if(!empty($link['financeiro_vencimento']))
            <div class="flex items-center mt-3 text-sm text-slate-600">
                <i class="fas fa-calendar-alt mr-2 text-slate-400"></i>
                Vencimento: <?= format_date($link['financeiro_vencimento']) ?>
            </div>
        @endif

        <?php
        $juros = (float) ($link['financeiro_juros'] ?? 0);
        $multa = (float) ($link['financeiro_multa'] ?? 0);
        $desconto = (float) ($link['financeiro_desconto'] ?? 0);
        ?>
        @if($juros > 0 || $multa > 0 || $desconto > 0)
            <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                @if($multa > 0)
                    <div class="flex justify-between gap-3">
                        <span>Multa</span>
                        <span class="font-medium"><?= currency_format($multa) ?></span>
                    </div>
                @endif
                @if($juros > 0)
                    <div class="flex justify-between gap-3">
                        <span>Juros</span>
                        <span class="font-medium"><?= currency_format($juros) ?></span>
                    </div>
                @endif
                @if($desconto > 0)
                    <div class="flex justify-between gap-3">
                        <span>Desconto</span>
                        <span class="font-medium text-green-700">- <?= currency_format($desconto) ?></span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Selecao de metodo de pagamento -->
    <div id="metodoSelection">
        <h2 class="font-semibold text-slate-800 mb-4">Escolha como pagar</h2>

        <div class="space-y-3">
            <!-- PIX -->
            @if(count($gateways_pix) > 0)
                <div class="payment-option rounded-lg p-4" data-metodo="pix" data-gateways='<?= htmlspecialchars(json_encode($gateways_pix)) ?>'>
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-qrcode text-green-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-slate-800">PIX</p>
                            <p class="text-sm text-slate-500">Pagamento instantaneo</p>
                        </div>
                        <i class="fas fa-chevron-right text-slate-400"></i>
                    </div>
                </div>
            @endif

            <!-- Boleto -->
            @if(count($gateways_boleto) > 0)
                <div class="payment-option rounded-lg p-4" data-metodo="boleto" data-gateways='<?= htmlspecialchars(json_encode($gateways_boleto)) ?>'>
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-barcode text-blue-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-slate-800">Boleto Bancario</p>
                            <p class="text-sm text-slate-500">Vencimento: <?= format_date($vencimentoGateway) ?></p>
                        </div>
                        <i class="fas fa-chevron-right text-slate-400"></i>
                    </div>
                </div>
            @endif

            <!-- Cartao -->
            @if(count($gateways_cartao) > 0)
                <div class="payment-option rounded-lg p-4" data-metodo="credit_card" data-gateways='<?= htmlspecialchars(json_encode($gateways_cartao)) ?>'>
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-credit-card text-purple-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-slate-800">Cartao de Credito</p>
                            <p class="text-sm text-slate-500">Pagamento instantaneo</p>
                        </div>
                        <i class="fas fa-chevron-right text-slate-400"></i>
                    </div>
                </div>
            @endif
        </div>

        @if(count($gateways_pix) === 0 && count($gateways_boleto) === 0 && count($gateways_cartao) === 0)
            <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-amber-500 text-3xl mb-3"></i>
                <p class="text-slate-600">Nenhum metodo de pagamento disponivel no momento.</p>
                <p class="text-sm text-slate-500 mt-1">Entre em contato com a empresa.</p>
            </div>
        @endif
    </div>

    <!-- Formulario de cartao (oculto inicialmente) -->
    <div id="cardFormSection" class="hidden">
        <div class="flex items-center mb-4">
            <button onclick="voltarSelecao()" class="text-slate-500 hover:text-slate-700 mr-3">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h2 class="font-semibold text-slate-800">Pagar com Cartao de Credito</h2>
        </div>

        <!-- Cartoes salvos -->
        <div id="savedCardsContainer" class="hidden mb-4">
            <p class="text-sm text-slate-600 mb-2">Seus cartoes salvos:</p>
            <div id="savedCardsList" class="space-y-2 mb-3"></div>
            <button type="button" id="useNewCardBtn" class="text-sm text-blue-600 hover:text-blue-800">
                <i class="fas fa-plus mr-1"></i> Usar outro cartao
            </button>
        </div>

        <!-- Formulario de novo cartao -->
        <div id="newCardForm">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nome no Cartao</label>
                    <input type="text" id="cardHolder" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Como esta no cartao" autocomplete="cc-name">
                </div>

                <!-- Stripe Card Element (para gateway Stripe) -->
                <div id="stripeCardContainer" class="hidden">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Dados do Cartao</label>
                    <div id="stripe-card-element" class="w-full px-3 py-3 border border-slate-300 rounded-lg bg-white focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500"></div>
                    <div id="stripe-card-errors" class="text-red-500 text-sm mt-1"></div>
                </div>

                <!-- Campos manuais (para outros gateways como Asaas) -->
                <div id="manualCardFields">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Numero do Cartao</label>
                            <input type="text" id="cardNumber" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="0000 0000 0000 0000" maxlength="19" autocomplete="cc-number">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Validade</label>
                                <input type="text" id="cardExpiry" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="MM/AA" maxlength="5" autocomplete="cc-exp">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">CVV</label>
                                <input type="text" id="cardCvv" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="000" maxlength="4" autocomplete="cc-csc">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">CPF do Titular</label>
                    <input type="text" id="cardCpf" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="000.000.000-00" maxlength="14" value="<?= htmlspecialchars($link['cliente_documento'] ?? '') ?>">
                </div>

                <!-- Opcao de salvar cartao -->
                <div id="saveCardOption" class="hidden">
                    <label class="flex items-center">
                        <input type="checkbox" id="saveCard" class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                        <span class="ml-2 text-sm text-slate-600">Salvar cartao para proximas cobranças</span>
                    </label>
                </div>
            </div>

            <button type="button" id="payWithCardBtn" class="w-full mt-6 py-3 px-4 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                <i class="fas fa-lock mr-2"></i>Pagar <?= $valor_formatado ?>
            </button>
        </div>

        <!-- Formulario para cartao salvo (so CVV) -->
        <div id="savedCardPayForm" class="hidden">
            <div class="bg-slate-50 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-credit-card text-slate-400 mr-3"></i>
                    <div>
                        <p id="selectedCardInfo" class="font-medium text-slate-700"></p>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">CVV do Cartao</label>
                <input type="text" id="savedCardCvv" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="000" maxlength="4">
            </div>

            <button type="button" id="payWithSavedCardBtn" class="w-full py-3 px-4 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                <i class="fas fa-lock mr-2"></i>Pagar <?= $valor_formatado ?>
            </button>

            <button type="button" id="cancelSavedCardBtn" class="w-full mt-2 py-2 text-sm text-slate-500 hover:text-slate-700">
                Usar outro cartao
            </button>
        </div>
    </div>

    <!-- Aviso de redirecionamento (para gateways sem checkout transparente) -->
    <div id="redirectNotice" class="hidden">
        <div class="flex items-center mb-4">
            <button onclick="voltarSelecao()" class="text-slate-500 hover:text-slate-700 mr-3">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h2 class="font-semibold text-slate-800">Pagar com Cartao de Credito</h2>
        </div>
        <div class="text-center py-6">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-purple-100 rounded-full mb-4">
                <i class="fas fa-external-link-alt text-2xl text-purple-600"></i>
            </div>
            <p class="text-slate-600 mb-4">Voce sera redirecionado para o ambiente seguro do processador de pagamento.</p>
            <button type="button" id="proceedToRedirectBtn" class="w-full py-3 px-4 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                <i class="fas fa-credit-card mr-2"></i>Continuar para pagamento
            </button>
        </div>
    </div>

    <!-- Area de resultado do pagamento (oculta inicialmente) -->
    <div id="paymentResult" class="hidden">
        <!-- Conteudo sera preenchido via JS -->
    </div>
</div>
@endsection

@section('scripts')
<!-- Stripe.js para tokenização segura -->
<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
    const codigo = '<?= htmlspecialchars($codigo) ?>';
    let metodoSelecionado = null;
    let gatewaySelecionado = null;
    let formaPagamentoSelecionada = <?= !empty($link['id_forma_pagamento']) ? (int) $link['id_forma_pagamento'] : 'null' ?>;
    let gatewayCapabilities = null;
    let savedCards = [];
    let selectedSavedCard = null;
    let statusCheckInterval = null;

    // Variaveis para Stripe Elements
    let stripeInstance = null;
    let stripeCardElement = null;
    let isStripeGateway = false;

    // Event listeners para opcoes de pagamento
    document.querySelectorAll('.payment-option').forEach(option => {
        option.addEventListener('click', function() {
            const metodo = this.dataset.metodo;
            const gateways = JSON.parse(this.dataset.gateways);

            metodoSelecionado = metodo;

            // Se tem apenas um gateway, usar direto
            if (gateways.length === 1) {
                selecionarGateway(metodo, gateways[0].id, gateways[0].id_forma_pagamento || null);
            } else {
                // Mostrar selecao de gateway
                mostrarSelecaoGateway(metodo, gateways);
            }
        });
    });

    // Event listeners para formulario de cartao
    document.getElementById('payWithCardBtn')?.addEventListener('click', pagarComNovoCartao);
    document.getElementById('payWithSavedCardBtn')?.addEventListener('click', pagarComCartaoSalvo);
    document.getElementById('useNewCardBtn')?.addEventListener('click', mostrarFormularioNovoCartao);
    document.getElementById('cancelSavedCardBtn')?.addEventListener('click', mostrarFormularioNovoCartao);
    document.getElementById('proceedToRedirectBtn')?.addEventListener('click', () => processarPagamento('credit_card', gatewaySelecionado));

    // Mascara para numero do cartao
    document.getElementById('cardNumber')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
        e.target.value = value.substring(0, 19);
    });

    // Mascara para validade
    document.getElementById('cardExpiry')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        e.target.value = value;
    });

    // Mascara para CPF
    document.getElementById('cardCpf')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.substring(0, 11);
        if (value.length > 9) {
            value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        } else if (value.length > 6) {
            value = value.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
        } else if (value.length > 3) {
            value = value.replace(/(\d{3})(\d{1,3})/, '$1.$2');
        }
        e.target.value = value;
    });

    function mostrarSelecaoGateway(metodo, gateways) {
        let html = `
            <h2 class="font-semibold text-slate-800 mb-4">Escolha o processador</h2>
            <div class="space-y-2">
        `;

        gateways.forEach(gw => {
            html += `
                <button class="w-full text-left p-3 border rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-colors gateway-option"
                        data-metodo="${metodo}" data-gateway-id="${gw.id}" data-forma-id="${gw.id_forma_pagamento || ''}">
                    <span class="font-medium">${escapeHtml(gw.nome)}</span>
                    ${gw.forma_pagamento_nome ? `<span class="block text-sm text-slate-500">${escapeHtml(gw.forma_pagamento_nome)}</span>` : ''}
                </button>
            `;
        });

        html += `
            </div>
            <button class="mt-4 text-sm text-slate-500 hover:text-slate-700" onclick="voltarSelecao()">
                <i class="fas fa-arrow-left mr-1"></i> Voltar
            </button>
        `;

        document.getElementById('metodoSelection').innerHTML = html;

        // Adicionar event listeners para os botoes de gateway
        document.querySelectorAll('.gateway-option').forEach(btn => {
            btn.addEventListener('click', function() {
                selecionarGateway(this.dataset.metodo, parseInt(this.dataset.gatewayId), this.dataset.formaId || null);
            });
        });
    }

    async function selecionarGateway(metodo, gatewayId, formaPagamentoId = null) {
        gatewaySelecionado = gatewayId;
        if (formaPagamentoId) {
            formaPagamentoSelecionada = parseInt(formaPagamentoId);
        }

        // Se nao for cartao, processar direto
        if (metodo !== 'credit_card') {
            processarPagamento(metodo, gatewayId);
            return;
        }

        // Para cartao, verificar capacidades do gateway
        mostrarLoading('Carregando...');

        try {
            const response = await fetch(`/pagar/${codigo}/gateway/${gatewayId}/capabilities`);
            const result = await response.json();

            esconderLoading();

            if (!result.success) {
                mostrarErro('Erro ao carregar gateway');
                return;
            }

            gatewayCapabilities = result.data;

            if (gatewayCapabilities.supports_transparent) {
                // Gateway suporta checkout transparente - mostrar formulario
                await mostrarFormularioCartao(gatewayId);
            } else {
                // Gateway nao suporta - mostrar aviso de redirecionamento
                mostrarAvisoRedirecionamento();
            }
        } catch (error) {
            esconderLoading();
            mostrarErro('Erro de conexao');
            console.error(error);
        }
    }

    async function mostrarFormularioCartao(gatewayId) {
        document.getElementById('metodoSelection').classList.add('hidden');
        document.getElementById('cardFormSection').classList.remove('hidden');

        // Mostrar opcao de salvar se gateway suporta
        if (gatewayCapabilities.supports_storage) {
            document.getElementById('saveCardOption').classList.remove('hidden');
        } else {
            document.getElementById('saveCardOption').classList.add('hidden');
        }

        // Detectar se é gateway Stripe e inicializar Elements
        isStripeGateway = gatewayCapabilities.gateway_code === 'stripe';

        if (isStripeGateway && gatewayCapabilities.publishable_key) {
            // Mostrar Stripe Card Element, ocultar campos manuais
            document.getElementById('stripeCardContainer').classList.remove('hidden');
            document.getElementById('manualCardFields').classList.add('hidden');

            // Inicializar Stripe se ainda nao foi feito
            if (!stripeInstance) {
                stripeInstance = Stripe(gatewayCapabilities.publishable_key);
                const elements = stripeInstance.elements();
                stripeCardElement = elements.create('card', {
                    style: {
                        base: {
                            fontSize: '16px',
                            color: '#374151',
                            '::placeholder': { color: '#9CA3AF' }
                        },
                        invalid: {
                            color: '#EF4444',
                            iconColor: '#EF4444'
                        }
                    }
                });
                stripeCardElement.mount('#stripe-card-element');

                // Mostrar erros de validacao do cartao
                stripeCardElement.on('change', function(event) {
                    const errorElement = document.getElementById('stripe-card-errors');
                    if (event.error) {
                        errorElement.textContent = event.error.message;
                    } else {
                        errorElement.textContent = '';
                    }
                });
            }
        } else {
            // Mostrar campos manuais, ocultar Stripe Element
            document.getElementById('stripeCardContainer').classList.add('hidden');
            document.getElementById('manualCardFields').classList.remove('hidden');
        }

        // Buscar cartoes salvos
        try {
            const response = await fetch(`/pagar/${codigo}/cartoes?gateway_id=${gatewayId}`);
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                savedCards = result.data;
                mostrarCartoesSalvos(savedCards);
            }
        } catch (error) {
            console.error('Erro ao buscar cartoes salvos:', error);
        }
    }

    function mostrarCartoesSalvos(cartoes) {
        const container = document.getElementById('savedCardsContainer');
        const list = document.getElementById('savedCardsList');

        let html = '';
        cartoes.forEach(cartao => {
            const isPadrao = cartao.padrao === 'S';
            html += `
                <button class="w-full text-left p-3 border rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-colors saved-card-btn"
                        data-card-id="${cartao.id}" data-token="${cartao.token || ''}" data-brand="${cartao.bandeira}" data-last-digits="${cartao.ultimos_digitos}">
                    <div class="flex items-center">
                        <i class="fas fa-credit-card text-slate-400 mr-3"></i>
                        <span class="font-medium">${escapeHtml(cartao.bandeira)} **** ${escapeHtml(cartao.ultimos_digitos)}</span>
                        ${isPadrao ? '<span class="ml-auto text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded">Padrao</span>' : ''}
                    </div>
                </button>
            `;
        });

        list.innerHTML = html;
        container.classList.remove('hidden');

        // Adicionar event listeners
        document.querySelectorAll('.saved-card-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                selecionarCartaoSalvo({
                    id: this.dataset.cardId,
                    token: this.dataset.token,
                    brand: this.dataset.brand,
                    last_digits: this.dataset.lastDigits
                });
            });
        });
    }

    function selecionarCartaoSalvo(cartao) {
        selectedSavedCard = cartao;
        document.getElementById('newCardForm').classList.add('hidden');
        document.getElementById('savedCardsContainer').classList.add('hidden');
        document.getElementById('savedCardPayForm').classList.remove('hidden');
        document.getElementById('selectedCardInfo').textContent = `${cartao.brand} **** ${cartao.last_digits}`;
        document.getElementById('savedCardCvv').value = '';
        document.getElementById('savedCardCvv').focus();
    }

    function mostrarFormularioNovoCartao() {
        selectedSavedCard = null;
        document.getElementById('savedCardPayForm').classList.add('hidden');
        document.getElementById('newCardForm').classList.remove('hidden');
        if (savedCards.length > 0) {
            document.getElementById('savedCardsContainer').classList.remove('hidden');
        }
    }

    function mostrarAvisoRedirecionamento() {
        document.getElementById('metodoSelection').classList.add('hidden');
        document.getElementById('redirectNotice').classList.remove('hidden');
    }

    async function pagarComNovoCartao() {
        // Validar campos comuns
        const holder = document.getElementById('cardHolder').value.trim();
        const cpf = document.getElementById('cardCpf').value.replace(/\D/g, '');
        const saveCard = document.getElementById('saveCard')?.checked || false;

        if (!holder) {
            alert('Por favor, informe o nome no cartao.');
            return;
        }

        if (cpf.length !== 11) {
            alert('CPF invalido.');
            return;
        }

        mostrarLoading('Processando pagamento...');

        try {
            let tokenResult;

            // Fluxo diferente para Stripe (tokenização no frontend)
            if (isStripeGateway && stripeInstance && stripeCardElement) {
                // Tokenizar via Stripe.js (dados do cartao NAO passam pelo servidor)
                const { paymentMethod, error } = await stripeInstance.createPaymentMethod({
                    type: 'card',
                    card: stripeCardElement,
                    billing_details: {
                        name: holder
                    }
                });

                if (error) {
                    esconderLoading();
                    mostrarErro(error.message || 'Erro ao processar cartao');
                    return;
                }

                // Enviar payment_method_id ao servidor para validar e obter dados do cartao
                const tokenResponse = await fetch(`/pagar/${codigo}/tokenizar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        gateway_id: gatewaySelecionado,
                        id_forma_pagamento: formaPagamentoSelecionada,
                        payment_method_id: paymentMethod.id,
                        holder: holder,
                        cpf: cpf
                    })
                });

                tokenResult = await tokenResponse.json();

            } else {
                // Fluxo para outros gateways (Asaas, etc.) - tokenização no servidor
                const number = document.getElementById('cardNumber').value.replace(/\s/g, '');
                const expiry = document.getElementById('cardExpiry').value;
                const cvv = document.getElementById('cardCvv').value;

                if (!number || !expiry || !cvv) {
                    esconderLoading();
                    alert('Por favor, preencha todos os campos do cartao.');
                    return;
                }

                if (number.length < 13) {
                    esconderLoading();
                    alert('Numero do cartao invalido.');
                    return;
                }

                const [expiryMonth, expiryYear] = expiry.split('/');
                if (!expiryMonth || !expiryYear) {
                    esconderLoading();
                    alert('Data de validade invalida.');
                    return;
                }

                const tokenResponse = await fetch(`/pagar/${codigo}/tokenizar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        gateway_id: gatewaySelecionado,
                        id_forma_pagamento: formaPagamentoSelecionada,
                        holder: holder,
                        number: number,
                        expiry_month: expiryMonth,
                        expiry_year: '20' + expiryYear,
                        cvv: cvv,
                        cpf: cpf
                    })
                });

                tokenResult = await tokenResponse.json();
            }

            if (!tokenResult.success) {
                esconderLoading();
                mostrarErro(tokenResult.message || 'Erro ao processar cartao');
                return;
            }

            // 2. Processar pagamento com o token
            const payResponse = await fetch(`/pagar/${codigo}/processar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    metodo: 'credit_card',
                    gateway_id: gatewaySelecionado,
                    id_forma_pagamento: formaPagamentoSelecionada,
                    card_token: tokenResult.data.token
                })
            });

            const payResult = await payResponse.json();

            // 3. Salvar cartao se marcado e pagamento foi sucesso
            if (payResult.success && saveCard && gatewayCapabilities.supports_storage) {
                try {
                    await fetch(`/pagar/${codigo}/salvar-cartao`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            token: tokenResult.data.token,
                            gateway: gatewayCapabilities.gateway_code,
                            brand: tokenResult.data.brand,
                            last_digits: tokenResult.data.last_digits
                        })
                    });
                } catch (e) {
                    console.error('Erro ao salvar cartao:', e);
                }
            }

            esconderLoading();

            if (payResult.success) {
                mostrarPagamentoCartaoSucesso(payResult.data);
            } else {
                mostrarErro(payResult.message || 'Erro ao processar pagamento');
            }

        } catch (error) {
            esconderLoading();
            mostrarErro('Erro de conexao. Tente novamente.');
            console.error(error);
        }
    }

    async function pagarComCartaoSalvo() {
        const cvv = document.getElementById('savedCardCvv').value;

        if (!cvv || cvv.length < 3) {
            alert('Por favor, informe o CVV do cartao.');
            return;
        }

        mostrarLoading('Processando pagamento...');

        try {
            const response = await fetch(`/pagar/${codigo}/processar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    metodo: 'credit_card',
                    gateway_id: gatewaySelecionado,
                    id_forma_pagamento: formaPagamentoSelecionada,
                    card_token: selectedSavedCard.token
                })
            });

            const result = await response.json();
            esconderLoading();

            if (result.success) {
                mostrarPagamentoCartaoSucesso(result.data);
            } else {
                mostrarErro(result.message || 'Erro ao processar pagamento');
            }

        } catch (error) {
            esconderLoading();
            mostrarErro('Erro de conexao. Tente novamente.');
            console.error(error);
        }
    }

    function mostrarPagamentoCartaoSucesso(data) {
        document.getElementById('cardFormSection').classList.add('hidden');
        document.getElementById('redirectNotice').classList.add('hidden');
        const resultDiv = document.getElementById('paymentResult');
        resultDiv.classList.remove('hidden');

        const status = data.status || 'pending';

        if (status === 'paid') {
            resultDiv.innerHTML = `
                <div class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                        <i class="fas fa-check text-4xl text-green-600"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 mb-2">Pagamento Confirmado!</h2>
                    <p class="text-slate-500">Obrigado pelo seu pagamento.</p>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-purple-100 rounded-full mb-4">
                        <i class="fas fa-credit-card text-2xl text-purple-600"></i>
                    </div>
                    <h2 class="text-xl font-bold text-slate-800 mb-2">Pagamento em processamento</h2>
                    <p class="text-slate-500 mb-4">Seu pagamento esta sendo processado.</p>
                    <div id="statusContainer" class="mt-6 p-4 bg-amber-50 rounded-lg">
                        <div class="flex items-center justify-center">
                            <i class="fas fa-clock text-amber-500 mr-2"></i>
                            <span class="text-sm text-amber-700">Aguardando confirmacao...</span>
                        </div>
                    </div>
                </div>
            `;
            iniciarVerificacaoStatus();
        }
    }

    window.voltarSelecao = function() {
        location.reload();
    };

    window.processarPagamento = async function(metodo, gatewayId) {
        mostrarLoading('Processando pagamento...');

        try {
            const response = await fetch(`/pagar/${codigo}/processar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    metodo: metodo,
                    gateway_id: gatewayId,
                    id_forma_pagamento: formaPagamentoSelecionada
                })
            });

            const result = await response.json();
            esconderLoading();

            if (result.success) {
                mostrarResultado(metodo, result.data);
                if (metodo !== 'credit_card') {
                    iniciarVerificacaoStatus();
                }
            } else {
                mostrarErro(result.message || 'Erro ao processar pagamento');
            }
        } catch (error) {
            esconderLoading();
            mostrarErro('Erro de conexao. Tente novamente.');
            console.error(error);
        }
    };

    function mostrarResultado(metodo, data) {
        document.getElementById('metodoSelection').classList.add('hidden');
        document.getElementById('cardFormSection').classList.add('hidden');
        document.getElementById('redirectNotice').classList.add('hidden');
        const resultDiv = document.getElementById('paymentResult');
        resultDiv.classList.remove('hidden');

        let html = '';

        if (metodo === 'pix') {
            html = `
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                        <i class="fas fa-check text-2xl text-green-600"></i>
                    </div>
                    <h2 class="text-xl font-bold text-slate-800 mb-2">PIX gerado com sucesso!</h2>
                    <p class="text-sm text-slate-500 mb-6">Escaneie o QR Code ou copie o codigo</p>

                    ${data.pix_qrcode ? `
                        <div class="qrcode-container mb-4">
                            <img src="${getQrCodeImageSrc(data.pix_qrcode)}" alt="QR Code PIX" class="w-48 h-48 mx-auto">
                        </div>
                    ` : ''}

                    ${data.pix_code ? `
                        <div class="mb-4">
                            <p class="text-xs text-slate-500 mb-2">Codigo PIX Copia e Cola</p>
                            <div class="pix-code mb-2">${escapeHtml(data.pix_code)}</div>
                            <button onclick="copiarCodigo('${escapeHtml(data.pix_code)}')" class="btn-primary w-full">
                                <i class="fas fa-copy mr-2"></i>Copiar codigo PIX
                            </button>
                        </div>
                    ` : ''}

                    <div id="statusContainer" class="mt-6 p-4 bg-amber-50 rounded-lg">
                        <div class="flex items-center justify-center">
                            <i class="fas fa-clock text-amber-500 mr-2"></i>
                            <span class="text-sm text-amber-700">Aguardando pagamento...</span>
                        </div>
                    </div>
                </div>
            `;
        } else if (metodo === 'boleto') {
            html = `
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                        <i class="fas fa-barcode text-2xl text-blue-600"></i>
                    </div>
                    <h2 class="text-xl font-bold text-slate-800 mb-2">Boleto gerado com sucesso!</h2>
                    <p class="text-sm text-slate-500 mb-6">Pague ate o vencimento</p>

                    ${data.barcode ? `
                        <div class="mb-4">
                            <p class="text-xs text-slate-500 mb-2">Linha digitavel</p>
                            <div class="pix-code mb-2">${escapeHtml(data.barcode)}</div>
                            <button onclick="copiarCodigo('${escapeHtml(data.barcode)}')" class="btn-primary w-full mb-2">
                                <i class="fas fa-copy mr-2"></i>Copiar codigo
                            </button>
                        </div>
                    ` : ''}

                    ${data.boleto_url ? `
                        <a href="${data.boleto_url}" target="_blank" class="inline-flex items-center justify-center w-full py-3 px-4 border border-blue-600 text-blue-600 rounded-lg font-medium hover:bg-blue-50 transition-colors">
                            <i class="fas fa-download mr-2"></i>Baixar Boleto PDF
                        </a>
                    ` : ''}

                    <div id="statusContainer" class="mt-6 p-4 bg-amber-50 rounded-lg">
                        <div class="flex items-center justify-center">
                            <i class="fas fa-clock text-amber-500 mr-2"></i>
                            <span class="text-sm text-amber-700">Aguardando pagamento...</span>
                        </div>
                    </div>
                </div>
            `;
        } else if (metodo === 'credit_card' && data.payment_url) {
            // Cartao com redirecionamento (para gateways sem checkout transparente)
            html = `
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-purple-100 rounded-full mb-4">
                        <i class="fas fa-external-link-alt text-2xl text-purple-600"></i>
                    </div>
                    <h2 class="text-xl font-bold text-slate-800 mb-2">Redirecionando para pagamento...</h2>
                    <p class="text-sm text-slate-500 mb-6">Voce sera redirecionado para finalizar o pagamento</p>

                    <a href="${data.payment_url}" class="btn-primary inline-block">
                        <i class="fas fa-credit-card mr-2"></i>Pagar com Cartao
                    </a>
                </div>
            `;
            // Auto redirect
            setTimeout(() => {
                window.location.href = data.payment_url;
            }, 2000);
        }

        resultDiv.innerHTML = html;
    }

    function getQrCodeImageSrc(qrCode) {
        if (!qrCode) {
            return '';
        }

        return qrCode.startsWith('data:')
            ? qrCode
            : `data:image/png;base64,${qrCode}`;
    }

    window.copiarCodigo = function(codigo) {
        navigator.clipboard.writeText(codigo).then(() => {
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check mr-2"></i>Copiado!';
            btn.classList.add('bg-green-500');
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('bg-green-500');
            }, 2000);
        });
    };

    function iniciarVerificacaoStatus() {
        statusCheckInterval = setInterval(async () => {
            try {
                const response = await fetch(`/pagar/${codigo}/status`);
                const result = await response.json();

                if (result.success && result.data.status === 'paid') {
                    clearInterval(statusCheckInterval);
                    mostrarPagamentoConfirmado();
                }
            } catch (error) {
                console.error('Erro ao verificar status:', error);
            }
        }, 5000);
    }

    function mostrarPagamentoConfirmado() {
        const statusContainer = document.getElementById('statusContainer');
        if (statusContainer) {
            statusContainer.className = 'mt-6 p-4 bg-green-50 rounded-lg';
            statusContainer.innerHTML = `
                <div class="flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                    <span class="text-sm text-green-700 font-medium">Pagamento confirmado!</span>
                </div>
            `;
        }

        const resultDiv = document.getElementById('paymentResult');
        resultDiv.innerHTML = `
            <div class="text-center py-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                    <i class="fas fa-check text-4xl text-green-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800 mb-2">Pagamento Confirmado!</h2>
                <p class="text-slate-500">Obrigado pelo seu pagamento.</p>
            </div>
        `;
    }

    function mostrarErro(mensagem) {
        const resultDiv = document.getElementById('paymentResult');
        document.getElementById('metodoSelection').classList.add('hidden');
        document.getElementById('cardFormSection').classList.add('hidden');
        document.getElementById('redirectNotice').classList.add('hidden');
        resultDiv.classList.remove('hidden');
        resultDiv.innerHTML = `
            <div class="text-center py-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                    <i class="fas fa-times text-2xl text-red-600"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800 mb-2">Erro no pagamento</h2>
                <p class="text-slate-500 mb-4">${escapeHtml(mensagem)}</p>
                <button onclick="location.reload()" class="btn-primary">
                    <i class="fas fa-redo mr-2"></i>Tentar novamente
                </button>
            </div>
        `;
    }

    function mostrarLoading(mensagem) {
        const overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="spinner mb-4"></div>
                <p class="text-slate-600">${escapeHtml(mensagem)}</p>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    function esconderLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.remove();
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
@endsection
