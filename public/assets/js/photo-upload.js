/**
 * Módulo PhotoUpload - Gerencia upload e captura de fotos
 *
 * Uso básico:
 * PhotoUpload.init({
 *     containerId: 'fotoContainer',
 *     previewId: 'fotoPreview',
 *     inputId: 'fotoInput',
 *     base64InputId: 'fotoBase64',
 *     defaultImage: '/assets/img/foto_padrao.png'
 * });
 *
 * Uso com configurações personalizadas:
 * PhotoUpload.init({
 *     containerId: 'fotoContainer',
 *     previewId: 'fotoPreview',
 *     inputId: 'fotoInput',
 *     base64InputId: 'fotoBase64',
 *     defaultImage: '/assets/img/foto_padrao.png',
 *     maxSize: 10 * 1024 * 1024,  // 10MB
 *     maxWidth: 1200,
 *     maxHeight: 1500,
 *     acceptedTypes: ['image/jpeg', 'image/png'],
 *     outputFormat: 'image/png',
 *     outputQuality: 0.8
 * });
 */
window.PhotoUpload = (function() {
    'use strict';

    // Configuração padrão
    const defaultConfig = {
        acceptedTypes: ['image/jpeg', 'image/png', 'image/webp'],
        acceptExtensions: '.jpg,.jpeg,.png,.webp',
        maxSize: 5 * 1024 * 1024, // 5MB
        maxWidth: 800,
        maxHeight: 1000,
        outputFormat: 'image/jpeg',
        outputQuality: 0.9
    };

    // Instâncias ativas
    const instances = {};

    // Verificar se está em iframe
    const isInIframe = window.parent !== window;

    // Listener global de mensagens (configurado uma vez)
    let messageListenerConfigured = false;

    /**
     * Configura o listener global de mensagens do parent
     */
    function setupGlobalMessageListener() {
        if (messageListenerConfigured) return;

        if (isInIframe) {
            window.addEventListener('message', function(event) {
                if (event.data && event.data.action === 'fotoModalActionResponse') {
                    const action = event.data.modalAction;
                    // Encontrar a instância ativa (última que abriu o modal)
                    const activeInstance = Object.values(instances).find(inst => inst.awaitingModalResponse);
                    if (activeInstance) {
                        activeInstance.awaitingModalResponse = false;
                        if (action === 'enviarArquivo') {
                            activeInstance.elements.input.click();
                        } else if (action === 'usarCamera') {
                            abrirCamera(activeInstance);
                        }
                    }
                } else if (event.data && event.data.action === 'cameraPhotoResponse') {
                    // Encontrar a instância que aguarda foto
                    const activeInstance = Object.values(instances).find(inst => inst.awaitingCameraResponse);
                    if (activeInstance) {
                        activeInstance.awaitingCameraResponse = false;
                        activeInstance.elements.preview.src = event.data.fotoBase64;
                        activeInstance.elements.base64Input.value = event.data.fotoBase64;
                    }
                }
            });
        }

        messageListenerConfigured = true;
    }

    /**
     * Valida o arquivo selecionado
     */
    function validateFile(file, config) {
        // Validar tipo
        if (!config.acceptedTypes.includes(file.type)) {
            const tipos = config.acceptedTypes.map(t => t.split('/')[1].toUpperCase()).join(', ');
            alert(`Formato não suportado. Use apenas: ${tipos}`);
            return false;
        }

        // Validar tamanho
        if (file.size > config.maxSize) {
            const maxMB = Math.round(config.maxSize / 1024 / 1024);
            alert(`A imagem é muito grande. Por favor, selecione uma imagem menor que ${maxMB}MB.`);
            return false;
        }

        return true;
    }

    /**
     * Processa e redimensiona a imagem
     */
    function processImage(file, config, callback) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                let width = img.width;
                let height = img.height;

                // Redimensionar se necessário
                if (width > config.maxWidth || height > config.maxHeight) {
                    const ratio = Math.min(config.maxWidth / width, config.maxHeight / height);
                    width = Math.round(width * ratio);
                    height = Math.round(height * ratio);
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                const base64 = canvas.toDataURL(config.outputFormat, config.outputQuality);
                callback(base64);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    /**
     * Abre o modal de escolha de foto
     */
    function abrirModalEscolha(instance) {
        if (isInIframe) {
            instance.awaitingModalResponse = true;
            window.parent.postMessage({ action: 'openFotoModal' }, '*');
        } else {
            // Se não estiver em iframe, abrir modal local (se existir)
            if (instance.elements.modalEscolha) {
                instance.elements.modalEscolha.classList.add('open');
                document.body.classList.add('modal-open');
            }
        }
    }

    /**
     * Fecha o modal de escolha de foto
     */
    function fecharModalEscolha(instance) {
        if (isInIframe) {
            window.parent.postMessage({ action: 'closeFotoModal' }, '*');
        } else {
            if (instance.elements.modalEscolha) {
                instance.elements.modalEscolha.classList.remove('open');
                document.body.classList.remove('modal-open');
            }
        }
    }

    /**
     * Abre a câmera
     */
    function abrirCamera(instance) {
        if (isInIframe) {
            instance.awaitingCameraResponse = true;
            window.parent.postMessage({ action: 'openCameraModal' }, '*');
        } else {
            // Lógica local de câmera (se necessário)
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Seu navegador não suporta acesso à câmera. Use a opção de enviar arquivo.');
                return;
            }
            // Implementação local omitida - usar modais globais do parent
        }
    }

    /**
     * Inicializa uma nova instância de PhotoUpload
     */
    function init(config) {
        // Mesclar configurações
        const mergedConfig = { ...defaultConfig, ...config };

        // Validar elementos obrigatórios
        const container = document.getElementById(config.containerId);
        const preview = document.getElementById(config.previewId);
        const input = document.getElementById(config.inputId);
        const base64Input = document.getElementById(config.base64InputId);

        if (!container || !preview || !input || !base64Input) {
            console.error('PhotoUpload: Elementos obrigatórios não encontrados.');
            return null;
        }

        // Criar instância
        const instanceId = config.containerId;
        const instance = {
            id: instanceId,
            config: mergedConfig,
            elements: {
                container,
                preview,
                input,
                base64Input,
                modalEscolha: document.getElementById(config.modalEscolhaId),
                modalCamera: document.getElementById(config.modalCameraId)
            },
            awaitingModalResponse: false,
            awaitingCameraResponse: false
        };

        // Configurar accept do input
        input.setAttribute('accept', mergedConfig.acceptExtensions);

        // Event: Clique no container abre modal de escolha
        container.addEventListener('click', function() {
            abrirModalEscolha(instance);
        });

        // Event: Arquivo selecionado
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && validateFile(file, mergedConfig)) {
                processImage(file, mergedConfig, function(base64) {
                    preview.src = base64;
                    base64Input.value = base64;
                });
            }
            input.value = ''; // Reset para permitir selecionar o mesmo arquivo
        });

        // Configurar listener global de mensagens
        setupGlobalMessageListener();

        // Armazenar instância
        instances[instanceId] = instance;

        return instance;
    }

    /**
     * Destrói uma instância de PhotoUpload
     */
    function destroy(instanceId) {
        if (instances[instanceId]) {
            delete instances[instanceId];
        }
    }

    /**
     * Obtém uma instância existente
     */
    function getInstance(instanceId) {
        return instances[instanceId] || null;
    }

    /**
     * Reseta a foto para a imagem padrão
     */
    function reset(instanceId) {
        const instance = instances[instanceId];
        if (instance && instance.config.defaultImage) {
            instance.elements.preview.src = instance.config.defaultImage;
            instance.elements.base64Input.value = '';
        }
    }

    /**
     * Define uma foto programaticamente
     */
    function setPhoto(instanceId, base64) {
        const instance = instances[instanceId];
        if (instance) {
            instance.elements.preview.src = base64;
            instance.elements.base64Input.value = base64;
        }
    }

    // API pública
    return {
        init,
        destroy,
        getInstance,
        reset,
        setPhoto,
        config: defaultConfig
    };
})();
