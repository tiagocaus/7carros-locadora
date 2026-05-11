<?php
$documento = $documento ?? [
    'tipo' => 'contrato',
    'tipo_label' => 'Contrato',
    'codigo' => $contrato['codigo'] ?? '',
    'cliente_nome' => $contrato['cliente_nome'] ?? 'N/A',
    'cliente_documento' => $contrato['cliente_cpf_cnpj'] ?? 'N/A',
    'veiculo_texto' => !empty($veiculo) ? trim(($veiculo['veiculo_placa'] ?? '') . ' - ' . ($veiculo['veiculo_modelo'] ?? '')) : '',
    'periodo' => (!empty($contrato['data_ini']) ? date('d/m/Y', strtotime($contrato['data_ini'])) : '-') . ' a ' . (!empty($contrato['data_fim']) ? date('d/m/Y', strtotime($contrato['data_fim'])) : '-'),
    'valor_total' => (float) ($contrato['total_pagar'] ?? 0),
];
$tipoLabel = $documento['tipo_label'] ?? 'Contrato';
$tipoLower = strtolower($tipoLabel);
$tipoArtigo = ($documento['tipo'] ?? 'contrato') === 'locacao' ? 'a' : 'o';
$tipoPreposicao = ($documento['tipo'] ?? 'contrato') === 'locacao' ? 'da' : 'do';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Assinatura de <?= htmlspecialchars($tipoLabel) ?> - <?= htmlspecialchars($documento['codigo']) ?></title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .signature-canvas {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            background: #fafafa;
            touch-action: none;
            cursor: crosshair;
        }

        .signature-canvas.has-signature {
            border-color: #10b981;
            border-style: solid;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #6b7280;
            font-size: 14px;
        }

        .info-value {
            color: #111827;
            font-weight: 500;
            text-align: right;
        }

        .success-animation {
            animation: successPop 0.5s ease;
        }

        @keyframes successPop {
            0% { transform: scale(0.5); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f4f6;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="p-4">
    <div class="max-w-lg mx-auto py-8">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full shadow-lg mb-4">
                <i class="fas fa-file-signature text-3xl text-purple-600"></i>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">Assinatura Digital</h1>
            <p class="text-purple-200"><?= htmlspecialchars($tipoLabel) ?> <?= htmlspecialchars($documento['codigo']) ?></p>
        </div>

        <!-- Card Principal -->
        <div class="card p-6 mb-6">
            <?php if ($jaAssinado): ?>
                <!-- Estado: Ja Assinado -->
                <div id="estadoAssinado" class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4 success-animation">
                        <i class="fas fa-check text-4xl text-green-600"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($tipoLabel) ?> Assinado</h2>
                    <p class="text-gray-600 mb-4">Este documento foi assinado digitalmente.</p>

                    <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
                        <p><strong>Data:</strong> <?= !empty($assinatura['created_at']) ? date('d/m/Y H:i', strtotime($assinatura['created_at'])) : '-' ?></p>
                        <p><strong>IP:</strong> <?= htmlspecialchars($assinatura['ip_address'] ?? '-') ?></p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Estado: Aguardando Assinatura -->
                <div id="estadoPendente">
                    <!-- Resumo do documento -->
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-info-circle text-purple-600 mr-2"></i>
                            Resumo <?= htmlspecialchars($tipoPreposicao) ?> <?= htmlspecialchars($tipoLabel) ?>
                        </h2>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="info-item">
                                <span class="info-label">Cliente</span>
                                <span class="info-value"><?= htmlspecialchars($documento['cliente_nome'] ?? 'N/A') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Documento</span>
                                <span class="info-value"><?= htmlspecialchars($documento['cliente_documento'] ?? 'N/A') ?></span>
                            </div>
                            <?php if (!empty($documento['veiculo_texto'])): ?>
                            <div class="info-item">
                                <span class="info-label">Veiculo</span>
                                <span class="info-value"><?= htmlspecialchars($documento['veiculo_texto']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <span class="info-label">Periodo</span>
                                <span class="info-value"><?= htmlspecialchars($documento['periodo'] ?? '-') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Valor Total</span>
                                <span class="info-value text-lg text-purple-600">
                                    R$ <?= number_format((float) ($documento['valor_total'] ?? 0), 2, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Area de Assinatura -->
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-pen text-purple-600 mr-2"></i>
                            Sua Assinatura
                        </h2>
                        <p class="text-sm text-gray-600 mb-4">
                            Desenhe sua assinatura no campo abaixo usando o dedo ou mouse.
                        </p>

                        <canvas id="signatureCanvas" class="signature-canvas w-full" height="200"></canvas>

                        <div class="flex gap-3 mt-4">
                            <button type="button" id="btnLimpar" class="btn btn-secondary flex-1">
                                <i class="fas fa-eraser"></i>
                                Limpar
                            </button>
                            <button type="button" id="btnAssinar" class="btn btn-primary flex-1" disabled>
                                <i class="fas fa-check"></i>
                                Assinar <?= htmlspecialchars($tipoLabel) ?>
                            </button>
                        </div>
                    </div>

                    <!-- Termos -->
                    <div class="text-xs text-gray-500 text-center">
                        <p>Ao assinar, declaro que li e concordo com os termos dest<?= htmlspecialchars($tipoArtigo) ?> <?= htmlspecialchars($tipoLower) ?>.</p>
                        <p class="mt-1">
                            <i class="fas fa-shield-alt text-green-500"></i>
                            Sua assinatura sera armazenada de forma segura.
                        </p>
                    </div>
                </div>

                <!-- Estado: Sucesso -->
                <div id="estadoSucesso" class="text-center py-8 hidden">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4 success-animation">
                        <i class="fas fa-check text-4xl text-green-600"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($tipoLabel) ?> Assinado!</h2>
                    <p class="text-gray-600 mb-4">Obrigado! Sua assinatura foi registrada com sucesso.</p>

                    <div id="infoAssinatura" class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600 mb-4">
                    </div>

                    <p class="text-sm text-gray-500">
                        Voce pode fechar esta pagina.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info Empresa -->
        <?php if ($empresa): ?>
        <div class="text-center text-purple-200 text-sm">
            <p class="font-medium"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social']) ?></p>
            <?php if (!empty($empresa['telefone'])): ?>
            <p><i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($empresa['telefone']) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="bg-white rounded-lg p-8 text-center">
            <div class="spinner mx-auto mb-4"></div>
            <p id="loadingText" class="text-gray-600">Processando assinatura...</p>
        </div>
    </div>

    <!-- Modal de Solicitar Localizacao (estado: prompt) -->
    <div id="locationPromptModal" class="loading-overlay">
        <div class="bg-white rounded-lg p-8 text-center max-w-sm mx-4">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                <i class="fas fa-map-marker-alt text-3xl text-blue-600"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Localizacao Necessaria</h3>
            <p id="locationPromptText" class="text-gray-600 mb-4 text-sm">
                Para garantir a validade juridica da sua assinatura, precisamos registrar sua localizacao.
                Este e um requisito obrigatorio.
            </p>
            <p id="locationPromptHint" class="text-xs text-gray-400 mb-3 hidden"></p>
            <button id="btnPermitirLocalizacao" class="btn btn-primary w-full">
                <i class="fas fa-location-arrow"></i>
                Permitir Localizacao
            </button>
            <p class="text-xs text-gray-400 mt-3">
                Sua localizacao sera usada apenas para validar a assinatura.
            </p>
        </div>
    </div>

    <!-- Modal de Localizacao Bloqueada (estado: denied) -->
    <div id="locationDeniedModal" class="loading-overlay">
        <div class="bg-white rounded-lg p-6 text-center max-w-md mx-4">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                <i class="fas fa-exclamation-triangle text-3xl text-red-600"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Localizacao Bloqueada</h3>
            <p class="text-gray-600 mb-4 text-sm">
                A permissao de localizacao foi negada. Para assinar est<?= htmlspecialchars($tipoArtigo) ?> <?= htmlspecialchars($tipoLower) ?>, voce precisa habilitar manualmente nas configuracoes do navegador (iOS/Android).
            </p>

            <div class="bg-gray-50 rounded-lg p-4 text-left text-sm mb-4">
                <p class="font-semibold text-gray-900 mb-2">Como habilitar:</p>
                <div id="instructionsChrome" class="hidden">
                    <p class="text-gray-600"><strong>Chrome:</strong></p>
                    <ol class="list-decimal list-inside text-gray-500 text-xs ml-2">
                        <li>Clique no icone de cadeado na barra de endereco</li>
                        <li>Clique em "Configuracoes do site"</li>
                        <li>Mude "Localizacao" para "Permitir"</li>
                        <li>Recarregue a pagina</li>
                    </ol>
                </div>
                <div id="instructionsSafari" class="hidden">
                    <p class="text-gray-600"><strong>Safari:</strong></p>
                    <ol class="list-decimal list-inside text-gray-500 text-xs ml-2">
                        <li>Va em Ajustes > Safari > Localizacao</li>
                        <li>Selecione "Permitir" ou "Perguntar"</li>
                        <li>Volte e recarregue a pagina</li>
                    </ol>
                </div>
                <div id="instructionsFirefox" class="hidden">
                    <p class="text-gray-600"><strong>Firefox:</strong></p>
                    <ol class="list-decimal list-inside text-gray-500 text-xs ml-2">
                        <li>Clique no icone de cadeado na barra de endereco</li>
                        <li>Clique em "Limpar permissoes"</li>
                        <li>Recarregue a pagina</li>
                    </ol>
                </div>
                <div id="instructionsMobile" class="hidden">
                    <p class="text-gray-600"><strong>Celular:</strong></p>
                    <ol class="list-decimal list-inside text-gray-500 text-xs ml-2">
                        <li>Va em Configuracoes do celular</li>
                        <li>Procure por "Localizacao" ou "Privacidade"</li>
                        <li>Encontre o navegador e permita acesso</li>
                        <li>Volte e recarregue a pagina</li>
                    </ol>
                </div>
            </div>

            <button id="btnRecarregarPagina" class="btn btn-primary w-full">
                <i class="fas fa-sync-alt"></i>
                Recarregar Pagina
            </button>
        </div>
    </div>

    <!-- Modal de Alerta -->
    <div id="signatureAlertModal" class="loading-overlay">
        <div class="bg-white rounded-lg p-6 text-center max-w-sm mx-4">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-amber-100 rounded-full mb-4">
                <i class="fas fa-exclamation-circle text-2xl text-amber-600"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Atencao</h3>
            <p id="signatureAlertMessage" class="text-gray-600 mb-5 text-sm"></p>
            <button id="signatureAlertOk" type="button" class="btn btn-primary w-full">
                OK
            </button>
        </div>
    </div>

    <!-- Modal de Confirmacao -->
    <div id="signatureConfirmModal" class="loading-overlay">
        <div class="bg-white rounded-lg p-6 text-center max-w-sm mx-4">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-100 rounded-full mb-4">
                <i class="fas fa-signature text-2xl text-blue-600"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Confirmar Assinatura</h3>
            <p id="signatureConfirmMessage" class="text-gray-600 mb-5 text-sm"></p>
            <div class="flex gap-3">
                <button id="signatureConfirmCancel" type="button" class="btn btn-secondary flex-1">
                    Cancelar
                </button>
                <button id="signatureConfirmOk" type="button" class="btn btn-primary flex-1">
                    Confirmar
                </button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const canvas = document.getElementById('signatureCanvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const btnLimpar = document.getElementById('btnLimpar');
        const btnAssinar = document.getElementById('btnAssinar');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const loadingText = document.getElementById('loadingText');
        const locationPromptModal = document.getElementById('locationPromptModal');
        const locationDeniedModal = document.getElementById('locationDeniedModal');
        const signatureAlertModal = document.getElementById('signatureAlertModal');
        const signatureAlertMessage = document.getElementById('signatureAlertMessage');
        const signatureAlertOk = document.getElementById('signatureAlertOk');
        const signatureConfirmModal = document.getElementById('signatureConfirmModal');
        const signatureConfirmMessage = document.getElementById('signatureConfirmMessage');
        const signatureConfirmCancel = document.getElementById('signatureConfirmCancel');
        const signatureConfirmOk = document.getElementById('signatureConfirmOk');
        const btnPermitirLocalizacao = document.getElementById('btnPermitirLocalizacao');
        const btnRecarregarPagina = document.getElementById('btnRecarregarPagina');
        const locationPromptText = document.getElementById('locationPromptText');
        const locationPromptHint = document.getElementById('locationPromptHint');
        const estadoPendente = document.getElementById('estadoPendente');
        const estadoSucesso = document.getElementById('estadoSucesso');

        let isDrawing = false;
        let hasSignature = false;
        let lastX = 0;
        let lastY = 0;

        // Geolocalizacao (obrigatoria)
        let userLatitude = null;
        let userLongitude = null;
        let locationObtained = false;
        let permissionState = 'prompt'; // prompt, granted, denied
        let confirmResolver = null;

        function showAlert(message) {
            if (!signatureAlertModal || !signatureAlertMessage) return;
            signatureAlertMessage.textContent = message;
            signatureAlertModal.classList.add('active');
        }

        function hideAlert() {
            signatureAlertModal?.classList.remove('active');
        }

        function showConfirm(message) {
            return new Promise((resolve) => {
                if (!signatureConfirmModal || !signatureConfirmMessage) {
                    resolve(false);
                    return;
                }

                confirmResolver = resolve;
                signatureConfirmMessage.textContent = message;
                signatureConfirmModal.classList.add('active');
            });
        }

        function closeConfirm(confirmed) {
            signatureConfirmModal?.classList.remove('active');
            if (confirmResolver) {
                confirmResolver(confirmed);
                confirmResolver = null;
            }
        }

        signatureAlertOk?.addEventListener('click', hideAlert);
        signatureConfirmCancel?.addEventListener('click', function() {
            closeConfirm(false);
        });
        signatureConfirmOk?.addEventListener('click', function() {
            closeConfirm(true);
        });

        function isSecureContextOk() {
            return window.isSecureContext && window.location.protocol === 'https:';
        }

        function isInIframe() {
            return window.top !== window.self;
        }

        function setPromptMessage(message, hint) {
            if (locationPromptText) {
                locationPromptText.textContent = message;
            }

            if (locationPromptHint) {
                locationPromptHint.textContent = hint || '';
                locationPromptHint.classList.toggle('hidden', !hint);
            }
        }

        // Detectar navegador para instrucoes
        function detectBrowser() {
            const ua = navigator.userAgent;
            const isMobile = /iPhone|iPad|iPod|Android/i.test(ua);

            if (isMobile) {
                document.getElementById('instructionsMobile').classList.remove('hidden');
            } else if (ua.includes('Chrome')) {
                document.getElementById('instructionsChrome').classList.remove('hidden');
            } else if (ua.includes('Safari')) {
                document.getElementById('instructionsSafari').classList.remove('hidden');
            } else if (ua.includes('Firefox')) {
                document.getElementById('instructionsFirefox').classList.remove('hidden');
            } else {
                document.getElementById('instructionsChrome').classList.remove('hidden');
            }
        }

        // Obter localizacao (retorna Promise, NAO mostra modais automaticamente)
        function getLocation() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject({ code: 0, message: 'Geolocalizacao nao suportada' });
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        userLatitude = position.coords.latitude;
                        userLongitude = position.coords.longitude;
                        locationObtained = true;
                        hideAllModals();
                        updateSignButtonState();
                        resolve(position);
                    },
                    (error) => {
                        reject(error);
                    },
                    {
                        // IMPORTANTE: Safari tem bugs com timeout muito alto
                        // 10 segundos eh um bom equilibrio
                        timeout: 10000,
                        enableHighAccuracy: true,
                        maximumAge: 0 // Forcar sempre uma nova requisicao para disparar prompt nativo
                    }
                );
            });
        }

        // Mostrar modal de solicitacao (erro tecnico, tentar novamente)
        function showPromptModal() {
            hideAllModals();
            locationPromptModal.classList.add('active');
        }

        // Mostrar modal de bloqueado (permissao negada)
        function showDeniedModal() {
            hideAllModals();
            detectBrowser();
            locationDeniedModal.classList.add('active');
        }

        // Esconder todos os modais
        function hideAllModals() {
            locationPromptModal.classList.remove('active');
            locationDeniedModal.classList.remove('active');
        }

        // Atualizar estado do botao assinar
        function updateSignButtonState() {
            btnAssinar.disabled = !hasSignature || !locationObtained;
        }

        // Iniciar verificacao de localizacao
        // ABORDAGEM ROBUSTA 2026: NAO confiar na Permissions API (Safari nao funciona)
        // Ref: https://developer.apple.com/forums/thread/751189
        // Ref: https://web.dev/articles/permissions-best-practices
        function initLocation() {
            // Verificar se geolocalizacao eh suportada
            if (!navigator.geolocation) {
                showAlert('Seu navegador nao suporta geolocalizacao. Por favor, use um navegador moderno.');
                return;
            }

            // SEMPRE mostrar modal explicativo primeiro
            // O usuario precisa clicar para acionar getCurrentPosition()
            // Isso funciona em TODOS os navegadores (Chrome, Safari, Firefox, iOS, Android)
            // NAO usamos navigator.permissions.query() porque Safari retorna valores incorretos
            showPromptModal();

            if (!isSecureContextOk()) {
                setPromptMessage(
                    'Para solicitar sua localizacao, este link precisa estar em HTTPS.',
                    'Acesse a pagina em um HTTPS real para que o aviso nativo do navegador apareca.'
                );
                lockLocationButtonForHttps();
                return;
            }

            if (isInIframe()) {
                setPromptMessage(
                    'Para garantir a validade juridica da sua assinatura, precisamos registrar sua localizacao.',
                    'Se esta pagina estiver dentro de um iframe, o navegador pode bloquear a localizacao. Abra em uma aba propria.'
                );
            } else {
                setPromptMessage(
                    'Para garantir a validade juridica da sua assinatura, precisamos registrar sua localizacao.',
                    ''
                );
            }
        }

        // Funcao auxiliar para resetar o botao ao estado inicial
        function resetLocationButton() {
            btnPermitirLocalizacao.disabled = false;
            btnPermitirLocalizacao.innerHTML = '<i class="fas fa-location-arrow"></i> Permitir Localizacao';
        }

        function lockLocationButtonForHttps() {
            btnPermitirLocalizacao.disabled = true;
            btnPermitirLocalizacao.innerHTML = '<i class="fas fa-lock"></i> Requer HTTPS';
        }

        // Evento do botao permitir localizacao
        // IMPORTANTE: getCurrentPosition() DEVE ser chamado DIRETAMENTE no handler
        // para preservar a cadeia de user gesture que navegadores móveis exigem
        btnPermitirLocalizacao.addEventListener('click', function() {
            // NÃO fechar modal aqui - manter aberto para preservar user gesture chain
            // NÃO usar async/await - quebra a cadeia de user gesture
            
            if (!navigator.geolocation) {
                showAlert('Seu navegador nao suporta geolocalizacao. Por favor, use um navegador moderno.');
                return;
            }

            if (!isSecureContextOk()) {
                setPromptMessage(
                    'Para solicitar sua localizacao, este link precisa estar em HTTPS.',
                    'Acesse a pagina em um HTTPS real para que o aviso nativo do navegador apareca.'
                );
                lockLocationButtonForHttps();
                return;
            }

            if (isInIframe()) {
                setPromptMessage(
                    'Para garantir a validade juridica da sua assinatura, precisamos registrar sua localizacao.',
                    'Se esta pagina estiver dentro de um iframe, o navegador pode bloquear a localizacao. Abra em uma aba propria.'
                );
            } else {
                setPromptMessage(
                    'Para garantir a validade juridica da sua assinatura, precisamos registrar sua localizacao.',
                    ''
                );
            }

            btnPermitirLocalizacao.disabled = true;
            btnPermitirLocalizacao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Obtendo...';

            // Chamar DIRETAMENTE no handler - sem Promise wrapper, sem async/await
            // Isso garante que o prompt nativo apareça em navegadores móveis
            navigator.geolocation.getCurrentPosition(
                // Success callback
                function(position) {
                    userLatitude = position.coords.latitude;
                    userLongitude = position.coords.longitude;
                    locationObtained = true;
                    hideAllModals();
                    updateSignButtonState();
                    resetLocationButton();
                },
                // Error callback
                function(error) {
                    console.log('Geolocation error:', error.code, error.message);
                    
                    if (error.code === 1) {
                        // PERMISSION_DENIED - usuario negou ou ja estava negado
                        permissionState = 'denied';
                        resetLocationButton();
                        showDeniedModal();
                    } else if (error.code === 2) {
                        // POSITION_UNAVAILABLE - GPS desligado ou indisponivel
                        resetLocationButton();
                        // Manter modal aberto para tentar novamente
                        showAlert('Nao foi possivel obter sua localizacao. Verifique se o GPS esta ativado.');
                    } else if (error.code === 3) {
                        // TIMEOUT - demorou muito
                        resetLocationButton();
                        // Manter modal aberto para tentar novamente
                        showAlert('Tempo esgotado ao obter localizacao. Tente novamente.');
                    } else {
                        // Erro desconhecido
                        resetLocationButton();
                    }
                },
                // Options
                {
                    timeout: 10000,
                    enableHighAccuracy: true,
                    maximumAge: 0 // Forcar sempre uma nova requisicao para disparar prompt nativo
                }
            );
        });

        // Evento do botao recarregar
        btnRecarregarPagina.addEventListener('click', function() {
            window.location.reload();
        });

        // Iniciar processo de localizacao
        initLocation();

        // Ajustar tamanho do canvas
        function resizeCanvas() {
            const rect = canvas.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;
            canvas.width = rect.width * dpr;
            canvas.height = 200 * dpr;
            ctx.scale(dpr, dpr);
            canvas.style.height = '200px';

            resetCanvasBackground();
            configureStrokeStyle();
        }

        function configureStrokeStyle() {
            ctx.strokeStyle = '#1f2937';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }

        function resetCanvasBackground() {
            ctx.save();
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.restore();
        }

        function exportSignatureData() {
            const outputCanvas = document.createElement('canvas');
            outputCanvas.width = canvas.width;
            outputCanvas.height = canvas.height;
            const outputCtx = outputCanvas.getContext('2d');
            outputCtx.fillStyle = '#ffffff';
            outputCtx.fillRect(0, 0, outputCanvas.width, outputCanvas.height);
            outputCtx.drawImage(canvas, 0, 0);

            return outputCanvas.toDataURL('image/png');
        }

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Obter posicao do toque/mouse
        function getPosition(e) {
            const rect = canvas.getBoundingClientRect();
            let clientX, clientY;

            if (e.touches && e.touches.length > 0) {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else {
                clientX = e.clientX;
                clientY = e.clientY;
            }

            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        // Iniciar desenho
        function startDrawing(e) {
            e.preventDefault();
            isDrawing = true;
            const pos = getPosition(e);
            lastX = pos.x;
            lastY = pos.y;
        }

        // Desenhar
        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();

            const pos = getPosition(e);

            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();

            lastX = pos.x;
            lastY = pos.y;

            if (!hasSignature) {
                hasSignature = true;
                canvas.classList.add('has-signature');
                updateSignButtonState();
            }
        }

        // Parar desenho
        function stopDrawing() {
            isDrawing = false;
        }

        // Eventos mouse
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Eventos touch
        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);
        canvas.addEventListener('touchcancel', stopDrawing);

        // Limpar canvas
        btnLimpar.addEventListener('click', function() {
            resetCanvasBackground();
            configureStrokeStyle();
            hasSignature = false;
            canvas.classList.remove('has-signature');
            updateSignButtonState();
        });

        // Assinar documento
        btnAssinar.addEventListener('click', async function() {
            if (!hasSignature) {
                showAlert('Por favor, desenhe sua assinatura antes de continuar.');
                return;
            }

            if (!locationObtained) {
                showPromptModal();
                return;
            }

            const confirmed = await showConfirm('Confirma a assinatura dest<?= addslashes($tipoArtigo) ?> <?= addslashes($tipoLower) ?>?');
            if (!confirmed) {
                return;
            }

            // Mostrar loading
            loadingOverlay.classList.add('active');
            loadingText.textContent = 'Processando assinatura...';
            btnAssinar.disabled = true;

            try {
                // Obter imagem do canvas
                const signatureData = exportSignatureData();

                // Enviar para servidor
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        assinatura: signatureData,
                        latitude: userLatitude,
                        longitude: userLongitude
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Mostrar sucesso
                    estadoPendente.classList.add('hidden');
                    estadoSucesso.classList.remove('hidden');

                    document.getElementById('infoAssinatura').innerHTML = `
                        <p><strong>Data:</strong> ${result.data.data_assinatura}</p>
                        <p><strong>IP:</strong> ${result.data.ip}</p>
                    `;
                } else {
                    showAlert(result.message || 'Erro ao processar assinatura.');
                    btnAssinar.disabled = false;
                }
            } catch (error) {
                console.error('Erro:', error);
                showAlert('Erro de conexao. Tente novamente.');
                updateSignButtonState();
            } finally {
                loadingOverlay.classList.remove('active');
            }
        });
    })();
    </script>
</body>
</html>
