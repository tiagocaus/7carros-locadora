/**
 * Screen Recorder - gravacao de tela com audio e upload em partes.
 */
const ScreenRecorder = (function() {
    'use strict';

    const MAX_DURATION = 5 * 60;
    const MAX_SIZE = 200 * 1024 * 1024;
    const UPLOAD_BASE_URL = '/api/gravacoes/uploads';
    const VIDEO_BITS_PER_SECOND = 3000000;
    const AUDIO_BITS_PER_SECOND = 128000;
    const MAX_CHUNK_RETRIES = 3;

    const fallbackMessages = {
        unsupported: 'Seu navegador nao suporta gravacao de tela. Use Edge, Chrome ou Firefox.',
        alreadyRunning: 'Ja existe uma gravacao ou upload em andamento.',
        permissionDenied: 'Permissao de gravacao negada.',
        microphoneRequired: 'A permissao do microfone e obrigatoria para iniciar a gravacao.',
        startError: 'Erro ao iniciar gravacao:',
        started: 'Gravacao iniciada com microfone.',
        noData: 'Nenhum dado gravado.',
        uploading: 'Enviando gravacao...',
        saved: 'Gravacao salva com sucesso!',
        uploadError: 'Nao foi possivel enviar a gravacao.',
        retry: 'Tentar novamente',
        downloadCopy: 'Baixar copia',
        discard: 'Descartar',
        localCopy: 'Copia local baixada.',
        tooLarge: 'A gravacao excedeu o limite de 200MB.',
        stop: 'Parar',
        stopTitle: 'Parar gravacao',
    };

    function t(key) {
        return window.APP_I18N?.screenRecorder?.[key] || fallbackMessages[key] || key;
    }

    let mediaRecorder = null;
    let recordedChunks = [];
    let displayStream = null;
    let microphoneStream = null;
    let recordingStream = null;
    let audioContext = null;
    let timerInterval = null;
    let remainingSeconds = MAX_DURATION;
    let isRecording = false;
    let isUploading = false;
    let uiContainer = null;
    let initiatingWindow = null;
    let pendingBlob = null;
    let pendingExtension = 'webm';
    let pendingUpload = null;

    async function start(sourceWindow = null) {
        if (isRecording || isUploading || pendingBlob) {
            showNotification(t('alreadyRunning'), 'warning');
            return;
        }

        if (!navigator.mediaDevices?.getDisplayMedia || !navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            showNotification(t('unsupported'), 'error');
            return;
        }

        initiatingWindow = sourceWindow;

        try {
            displayStream = await navigator.mediaDevices.getDisplayMedia({
                video: { cursor: 'always' },
                audio: true,
            });

            try {
                microphoneStream = await navigator.mediaDevices.getUserMedia({
                    video: false,
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true,
                    },
                });
            } catch (error) {
                stopTracks(displayStream);
                displayStream = null;
                const microphoneError = new Error(t('microphoneRequired'));
                microphoneError.name = 'MicrophonePermissionError';
                throw microphoneError;
            }

            recordingStream = await createRecordingStream(displayStream, microphoneStream);
            const mimeType = getSupportedMimeType();
            const options = {
                mimeType,
                videoBitsPerSecond: VIDEO_BITS_PER_SECOND,
                audioBitsPerSecond: AUDIO_BITS_PER_SECOND,
            };

            try {
                mediaRecorder = new MediaRecorder(recordingStream, options);
            } catch (error) {
                mediaRecorder = new MediaRecorder(recordingStream, { mimeType });
            }

            recordedChunks = [];
            mediaRecorder.ondataavailable = function(event) {
                if (event.data?.size > 0) {
                    recordedChunks.push(event.data);
                }
            };
            mediaRecorder.onstop = handleRecordingStop;

            const videoTrack = displayStream.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.onended = function() {
                    if (isRecording) stop();
                };
            }

            mediaRecorder.start(1000);
            isRecording = true;
            remainingSeconds = MAX_DURATION;
            createUI();
            startTimer();
            showNotification(t('started'), 'success');
        } catch (error) {
            console.error('Erro ao iniciar gravacao:', error);
            if (error.name === 'NotAllowedError') {
                showNotification(t('permissionDenied'), 'warning');
            } else if (error.name === 'MicrophonePermissionError') {
                showNotification(error.message, 'error');
            } else {
                showNotification(`${t('startError')} ${error.message}`, 'error');
            }
            cleanupCapture();
            removeUI();
        }
    }

    async function createRecordingStream(screen, microphone) {
        const output = new MediaStream();
        screen.getVideoTracks().forEach(track => output.addTrack(track));

        const screenAudioTracks = screen.getAudioTracks();
        const microphoneTracks = microphone.getAudioTracks();
        if (screenAudioTracks.length === 0) {
            microphoneTracks.forEach(track => output.addTrack(track));
            return output;
        }

        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) {
            microphoneTracks.forEach(track => output.addTrack(track));
            return output;
        }

        audioContext = new AudioContextClass();
        if (audioContext.state === 'suspended') {
            await audioContext.resume();
        }
        const destination = audioContext.createMediaStreamDestination();
        audioContext.createMediaStreamSource(new MediaStream(screenAudioTracks)).connect(destination);
        audioContext.createMediaStreamSource(new MediaStream(microphoneTracks)).connect(destination);
        destination.stream.getAudioTracks().forEach(track => output.addTrack(track));
        return output;
    }

    function stop() {
        if (!isRecording || !mediaRecorder) return;

        isRecording = false;
        stopTimer();
        if (mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        stopTracks(displayStream);
        stopTracks(microphoneStream);
    }

    async function handleRecordingStop() {
        if (recordedChunks.length === 0) {
            showNotification(t('noData'), 'warning');
            cleanupCapture();
            removeUI();
            return;
        }

        const mimeType = mediaRecorder?.mimeType || getSupportedMimeType();
        pendingBlob = new Blob(recordedChunks, { type: mimeType });
        pendingExtension = mimeType.includes('mp4') ? 'mp4' : 'webm';
        recordedChunks = [];
        cleanupCapture(false);

        if (pendingBlob.size > MAX_SIZE) {
            renderUploadFailure(new Error(t('tooLarge')));
            return;
        }

        await uploadPendingRecording();
    }

    async function uploadPendingRecording() {
        if (!pendingBlob || isUploading) return;
        isUploading = true;
        renderUploadProgress(0);

        try {
            if (!pendingUpload) {
                const initResult = await API.post(UPLOAD_BASE_URL, {
                    mime_type: pendingBlob.type,
                    size: pendingBlob.size,
                });
                ensureSuccess(initResult);
                pendingUpload = {
                    uploadId: initResult.data.upload_id,
                    chunkSize: Number(initResult.data.chunk_size),
                    totalChunks: Number(initResult.data.total_chunks),
                    nextIndex: 0,
                };
            }

            while (pendingUpload.nextIndex < pendingUpload.totalChunks) {
                const index = pendingUpload.nextIndex;
                const startByte = index * pendingUpload.chunkSize;
                const chunk = pendingBlob.slice(startByte, Math.min(startByte + pendingUpload.chunkSize, pendingBlob.size));
                const formData = new FormData();
                formData.append('index', String(index));
                formData.append('chunk', chunk, `chunk_${index}.part`);

                const result = await withRetry(
                    () => API.postForm(`${UPLOAD_BASE_URL}/${pendingUpload.uploadId}/chunks`, formData),
                    MAX_CHUNK_RETRIES
                );
                ensureSuccess(result);
                pendingUpload.nextIndex++;
                renderUploadProgress(Math.round((pendingUpload.nextIndex / pendingUpload.totalChunks) * 95));
            }

            const finalizeResult = await withRetry(
                () => API.post(`${UPLOAD_BASE_URL}/${pendingUpload.uploadId}/finalize`, {}),
                MAX_CHUNK_RETRIES
            );
            ensureSuccess(finalizeResult);
            renderUploadProgress(100);
            showNotification(t('saved'), 'success');
            notifyRecordingSaved(finalizeResult.data);
            resetPendingState();
            removeUI();
        } catch (error) {
            console.error('Erro ao enviar gravacao:', error);
            renderUploadFailure(error);
        } finally {
            isUploading = false;
        }
    }

    async function withRetry(callback, attempts) {
        let lastError;
        for (let attempt = 1; attempt <= attempts; attempt++) {
            try {
                const result = await callback();
                ensureSuccess(result);
                return result;
            } catch (error) {
                lastError = error;
                if (attempt < attempts) {
                    await new Promise(resolve => setTimeout(resolve, attempt * 1000));
                }
            }
        }
        throw lastError || new Error(t('uploadError'));
    }

    function ensureSuccess(result) {
        if (!result || result.success !== true) {
            throw new Error(result?.message || t('uploadError'));
        }
    }

    function createUI() {
        removeUI();
        ensureStyles();
        uiContainer = document.createElement('div');
        uiContainer.id = 'screen-recorder-ui';
        uiContainer.innerHTML = `
            <div class="sr-indicator"></div>
            <span class="sr-timer">${formatTime(remainingSeconds)}</span>
            <button class="sr-stop-btn" title="${t('stopTitle')}">
                <i class="fas fa-stop"></i> ${t('stop')}
            </button>
        `;
        document.body.appendChild(uiContainer);
        uiContainer.querySelector('.sr-stop-btn')?.addEventListener('click', stop);
    }

    function renderUploadProgress(percent) {
        ensureUI();
        uiContainer.innerHTML = `
            <div class="sr-progress">
                <div class="sr-spinner"></div>
                <div>
                    <span>${t('uploading')}</span>
                    <div class="sr-progress-track"><div class="sr-progress-bar" style="width:${percent}%"></div></div>
                </div>
                <strong>${percent}%</strong>
            </div>
        `;
    }

    function renderUploadFailure(error) {
        ensureUI();
        uiContainer.innerHTML = `
            <div class="sr-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span class="sr-error-message"></span>
                <div class="sr-error-actions">
                    <button type="button" class="sr-action-btn sr-retry-btn">${t('retry')}</button>
                    <button type="button" class="sr-action-btn sr-download-btn">${t('downloadCopy')}</button>
                    <button type="button" class="sr-action-btn sr-discard-btn">${t('discard')}</button>
                </div>
            </div>
        `;
        uiContainer.querySelector('.sr-error-message').textContent = error?.message || t('uploadError');
        uiContainer.querySelector('.sr-retry-btn')?.addEventListener('click', uploadPendingRecording);
        uiContainer.querySelector('.sr-download-btn')?.addEventListener('click', downloadPendingCopy);
        uiContainer.querySelector('.sr-discard-btn')?.addEventListener('click', discardPendingRecording);
        showNotification(error?.message || t('uploadError'), 'error');
    }

    function ensureUI() {
        if (uiContainer) return;
        ensureStyles();
        uiContainer = document.createElement('div');
        uiContainer.id = 'screen-recorder-ui';
        document.body.appendChild(uiContainer);
    }

    function ensureStyles() {
        if (document.getElementById('screen-recorder-styles')) return;
        const style = document.createElement('style');
        style.id = 'screen-recorder-styles';
        style.textContent = `
            @keyframes sr-fade-in { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
            @keyframes sr-pulse { 0%,100% { opacity:1; } 50% { opacity:.5; } }
            @keyframes sr-spin { to { transform:rotate(360deg); } }
            #screen-recorder-ui { position:fixed; bottom:20px; left:20px; max-width:min(620px,calc(100vw - 40px)); background:rgba(0,0,0,.9); color:white; padding:12px 16px; border-radius:8px; display:flex; align-items:center; gap:12px; z-index:999999; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; font-size:14px; box-shadow:0 4px 12px rgba(0,0,0,.3); animation:sr-fade-in .3s ease; }
            #screen-recorder-ui .sr-indicator { width:12px; height:12px; background:#ef4444; border-radius:50%; animation:sr-pulse 1s ease-in-out infinite; }
            #screen-recorder-ui .sr-timer { font-variant-numeric:tabular-nums; min-width:45px; }
            #screen-recorder-ui .sr-stop-btn { background:#ef4444; color:white; border:0; padding:6px 12px; border-radius:4px; cursor:pointer; display:flex; align-items:center; gap:6px; }
            #screen-recorder-ui .sr-stop-btn:hover { background:#dc2626; }
            #screen-recorder-ui .sr-progress { width:100%; display:flex; align-items:center; gap:10px; }
            #screen-recorder-ui .sr-progress > div:nth-child(2) { min-width:220px; flex:1; }
            #screen-recorder-ui .sr-spinner { width:16px; height:16px; border:2px solid rgba(255,255,255,.3); border-top-color:white; border-radius:50%; animation:sr-spin 1s linear infinite; }
            #screen-recorder-ui .sr-progress-track { height:5px; margin-top:6px; background:rgba(255,255,255,.2); border-radius:4px; overflow:hidden; }
            #screen-recorder-ui .sr-progress-bar { height:100%; background:#38bdf8; transition:width .2s ease; }
            #screen-recorder-ui .sr-error { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
            #screen-recorder-ui .sr-error > i { color:#fbbf24; }
            #screen-recorder-ui .sr-error-message { flex:1; min-width:220px; }
            #screen-recorder-ui .sr-error-actions { display:flex; flex-wrap:wrap; gap:6px; width:100%; }
            #screen-recorder-ui .sr-action-btn { border:0; border-radius:4px; padding:6px 10px; cursor:pointer; background:#334155; color:white; }
            #screen-recorder-ui .sr-retry-btn { background:#0284c7; }
            #screen-recorder-ui .sr-discard-btn { background:#b91c1c; }
        `;
        document.head.appendChild(style);
    }

    function downloadPendingCopy() {
        if (!pendingBlob) return;
        const url = URL.createObjectURL(pendingBlob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `gravacao_${DateHelper.nowISO().replace(/[-: ]/g, '').substring(0, 14)}.${pendingExtension}`;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(url);
        showNotification(t('localCopy'), 'info');
    }

    async function discardPendingRecording() {
        if (pendingUpload?.uploadId) {
            try {
                await API.post(`${UPLOAD_BASE_URL}/${pendingUpload.uploadId}/cancelar`, {});
            } catch (error) {
                console.warn('Nao foi possivel cancelar upload temporario:', error);
            }
        }
        resetPendingState();
        removeUI();
    }

    function notifyRecordingSaved(recording) {
        try {
            initiatingWindow?.postMessage({ action: 'screenRecordingSaved', recording }, '*');
        } catch (error) {
            console.warn('Nao foi possivel atualizar a lista de gravacoes:', error);
        }
    }

    function startTimer() {
        timerInterval = setInterval(function() {
            remainingSeconds--;
            if (remainingSeconds <= 0) {
                stop();
                return;
            }
            const timer = uiContainer?.querySelector('.sr-timer');
            if (timer) {
                timer.textContent = formatTime(remainingSeconds);
                if (remainingSeconds <= 30) timer.style.color = '#fbbf24';
            }
        }, 1000);
    }

    function stopTimer() {
        if (timerInterval) clearInterval(timerInterval);
        timerInterval = null;
    }

    function cleanupCapture(resetRecorder = true) {
        stopTimer();
        stopTracks(displayStream);
        stopTracks(microphoneStream);
        stopTracks(recordingStream);
        displayStream = null;
        microphoneStream = null;
        recordingStream = null;
        isRecording = false;
        remainingSeconds = MAX_DURATION;
        if (audioContext) {
            audioContext.close().catch(() => {});
            audioContext = null;
        }
        if (resetRecorder) mediaRecorder = null;
    }

    function stopTracks(mediaStream) {
        mediaStream?.getTracks().forEach(track => track.stop());
    }

    function resetPendingState() {
        pendingBlob = null;
        pendingUpload = null;
        pendingExtension = 'webm';
        mediaRecorder = null;
        initiatingWindow = null;
    }

    function removeUI() {
        uiContainer?.remove();
        uiContainer = null;
        document.getElementById('screen-recorder-styles')?.remove();
    }

    function getSupportedMimeType() {
        const types = [
            'video/webm;codecs=vp9,opus',
            'video/webm;codecs=vp8,opus',
            'video/webm;codecs=vp9',
            'video/webm;codecs=vp8',
            'video/webm',
            'video/mp4',
        ];
        return types.find(type => MediaRecorder.isTypeSupported(type)) || 'video/webm';
    }

    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    function showNotification(message, type = 'info') {
        if (typeof Notification !== 'undefined' && window.Notification?.show) {
            window.Notification.show(message, type);
            return;
        }
        const notification = document.createElement('div');
        notification.textContent = message;
        const colors = { success:'#10b981', error:'#ef4444', warning:'#f59e0b', info:'#3b82f6' };
        notification.style.cssText = `position:fixed;top:20px;right:20px;background:${colors[type] || colors.info};color:white;padding:12px 20px;border-radius:6px;z-index:999999;font:14px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;box-shadow:0 4px 12px rgba(0,0,0,.15);`;
        document.body.appendChild(notification);
        setTimeout(function() {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity .3s';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    return {
        start,
        stop,
        retryUpload: uploadPendingRecording,
        isRecording: () => isRecording,
        isUploading: () => isUploading,
    };
})();

window.ScreenRecorder = ScreenRecorder;
