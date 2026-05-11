/**
 * Screen Recorder - Sistema de gravacao de tela
 *
 * Usa a API getDisplayMedia do navegador para capturar a tela
 * e MediaRecorder para gravar o conteudo
 */
const ScreenRecorder = (function() {
    'use strict';

    // Configuracoes
    const MAX_DURATION = 5 * 60; // 5 minutos em segundos
    const UPLOAD_URL = '/api/gravacoes';

    // Estado
    let mediaRecorder = null;
    let recordedChunks = [];
    let stream = null;
    let timerInterval = null;
    let remainingSeconds = MAX_DURATION;
    let isRecording = false;
    let uiContainer = null;

    /**
     * Inicia a gravacao de tela
     */
    async function start() {
        if (isRecording) {
            console.warn('Gravacao ja em andamento');
            return;
        }

        // Verifica suporte do navegador
        if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
            showNotification('Seu navegador nao suporta gravacao de tela. Use Edge, Chrome ou Firefox.', 'error');
            return;
        }

        try {
            // Solicita permissao para capturar tela
            stream = await navigator.mediaDevices.getDisplayMedia({
                video: {
                    mediaSource: 'screen',
                    cursor: 'always'
                },
                audio: false
            });

            // Configura MediaRecorder
            const options = { mimeType: getSupportedMimeType() };
            mediaRecorder = new MediaRecorder(stream, options);
            recordedChunks = [];

            mediaRecorder.ondataavailable = function(event) {
                if (event.data && event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            };

            mediaRecorder.onstop = function() {
                handleRecordingStop();
            };

            // Detecta quando usuario para de compartilhar
            stream.getVideoTracks()[0].onended = function() {
                if (isRecording) {
                    stop();
                }
            };

            // Inicia gravacao
            mediaRecorder.start(1000); // Coleta dados a cada 1 segundo
            isRecording = true;
            remainingSeconds = MAX_DURATION;

            // Mostra UI
            createUI();
            startTimer();

            showNotification('Gravacao iniciada', 'success');

        } catch (error) {
            console.error('Erro ao iniciar gravacao:', error);

            if (error.name === 'NotAllowedError') {
                showNotification('Permissao de gravacao negada', 'warning');
            } else {
                showNotification('Erro ao iniciar gravacao: ' + error.message, 'error');
            }

            cleanup();
        }
    }

    /**
     * Para a gravacao
     */
    function stop() {
        if (!isRecording || !mediaRecorder) {
            return;
        }

        isRecording = false;
        stopTimer();

        if (mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }

        // Para todas as tracks do stream
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    }

    /**
     * Processa o fim da gravacao
     */
    async function handleRecordingStop() {
        if (recordedChunks.length === 0) {
            showNotification('Nenhum dado gravado', 'warning');
            cleanup();
            return;
        }

        // Cria blob do video
        const mimeType = getSupportedMimeType();
        const blob = new Blob(recordedChunks, { type: mimeType });

        // Mostra progresso de upload
        updateUIForUpload();

        try {
            await uploadVideo(blob);
            showNotification('Gravacao salva com sucesso!', 'success');
        } catch (error) {
            console.error('Erro ao enviar gravacao:', error);
            showNotification('Erro ao salvar gravacao: ' + error.message, 'error');

            // Oferece download local como fallback
            offerLocalDownload(blob);
        }

        cleanup();
    }

    /**
     * Envia video para o servidor
     */
    async function uploadVideo(blob) {
        const formData = new FormData();
        const extension = blob.type.includes('mp4') ? 'mp4' : 'webm';
        formData.append('video', blob, `gravacao.${extension}`);

        const result = await API.postForm(UPLOAD_URL, formData);

        if (result && result.success === false) {
            throw new Error(result.message || 'Erro no upload');
        }

        return result;
    }

    /**
     * Oferece download local se upload falhar
     */
    function offerLocalDownload(blob) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `gravacao_${formatDate(new Date())}.webm`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        showNotification('Video salvo localmente', 'info');
    }

    /**
     * Cria a UI flutuante
     */
    function createUI() {
        // Remove UI existente
        removeUI();

        uiContainer = document.createElement('div');
        uiContainer.id = 'screen-recorder-ui';
        uiContainer.innerHTML = `
            <div class="sr-indicator"></div>
            <span class="sr-timer">${formatTime(remainingSeconds)}</span>
            <button class="sr-stop-btn" title="Parar gravacao">
                <i class="fas fa-stop"></i> Parar
            </button>
        `;

        // Estilos
        uiContainer.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: sr-fade-in 0.3s ease;
        `;

        // Estilos do indicador pulsante
        const style = document.createElement('style');
        style.id = 'screen-recorder-styles';
        style.textContent = `
            @keyframes sr-fade-in {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes sr-pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
            #screen-recorder-ui .sr-indicator {
                width: 12px;
                height: 12px;
                background: #ef4444;
                border-radius: 50%;
                animation: sr-pulse 1s ease-in-out infinite;
            }
            #screen-recorder-ui .sr-timer {
                font-variant-numeric: tabular-nums;
                min-width: 45px;
            }
            #screen-recorder-ui .sr-stop-btn {
                background: #ef4444;
                color: white;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 13px;
                display: flex;
                align-items: center;
                gap: 6px;
                transition: background 0.2s;
            }
            #screen-recorder-ui .sr-stop-btn:hover {
                background: #dc2626;
            }
            #screen-recorder-ui .sr-progress {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            #screen-recorder-ui .sr-spinner {
                width: 16px;
                height: 16px;
                border: 2px solid rgba(255,255,255,0.3);
                border-top-color: white;
                border-radius: 50%;
                animation: sr-spin 1s linear infinite;
            }
            @keyframes sr-spin {
                to { transform: rotate(360deg); }
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(uiContainer);

        // Event listener do botao parar
        uiContainer.querySelector('.sr-stop-btn').addEventListener('click', stop);
    }

    /**
     * Atualiza UI para mostrar progresso de upload
     */
    function updateUIForUpload() {
        if (!uiContainer) return;

        uiContainer.innerHTML = `
            <div class="sr-progress">
                <div class="sr-spinner"></div>
                <span>Enviando gravacao...</span>
            </div>
        `;
    }

    /**
     * Remove a UI
     */
    function removeUI() {
        if (uiContainer) {
            uiContainer.remove();
            uiContainer = null;
        }

        const styles = document.getElementById('screen-recorder-styles');
        if (styles) {
            styles.remove();
        }
    }

    /**
     * Inicia o timer
     */
    function startTimer() {
        timerInterval = setInterval(function() {
            remainingSeconds--;

            if (remainingSeconds <= 0) {
                stop();
                return;
            }

            // Atualiza display
            const timerEl = uiContainer?.querySelector('.sr-timer');
            if (timerEl) {
                timerEl.textContent = formatTime(remainingSeconds);

                // Alerta visual quando restam 30 segundos
                if (remainingSeconds <= 30) {
                    timerEl.style.color = '#fbbf24';
                }
            }
        }, 1000);
    }

    /**
     * Para o timer
     */
    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    /**
     * Limpa recursos
     */
    function cleanup() {
        stopTimer();
        removeUI();

        mediaRecorder = null;
        recordedChunks = [];
        stream = null;
        isRecording = false;
        remainingSeconds = MAX_DURATION;
    }

    /**
     * Retorna mime type suportado pelo navegador
     */
    function getSupportedMimeType() {
        const types = [
            'video/webm;codecs=vp9',
            'video/webm;codecs=vp8',
            'video/webm',
            'video/mp4'
        ];

        for (const type of types) {
            if (MediaRecorder.isTypeSupported(type)) {
                return type;
            }
        }

        return 'video/webm';
    }

    /**
     * Formata segundos para MM:SS
     */
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    /**
     * Formata data para nome de arquivo
     */
    function formatDate(date) {
        const pad = n => n.toString().padStart(2, '0');
        return `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}_${pad(date.getHours())}${pad(date.getMinutes())}${pad(date.getSeconds())}`;
    }

    /**
     * Mostra notificacao
     */
    function showNotification(message, type = 'info') {
        // Tenta usar o sistema de notificacao do app se disponivel
        if (typeof Notification !== 'undefined' && window.Notification?.show) {
            window.Notification.show(message, type);
            return;
        }

        // Fallback: cria notificacao temporaria
        const notification = document.createElement('div');
        notification.textContent = message;

        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };

        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${colors[type] || colors.info};
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: sr-fade-in 0.3s ease;
        `;

        document.body.appendChild(notification);

        setTimeout(function() {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    // API publica
    return {
        start: start,
        stop: stop,
        isRecording: function() { return isRecording; }
    };

})();

// Expoe globalmente
window.ScreenRecorder = ScreenRecorder;
