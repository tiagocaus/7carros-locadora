@extends('layouts.auth')

@section('title', t('common.auth.login_title'))

@section('content')
<div class="login-container">
    <!-- Seção de Atualizações (2/3) -->
    <div class="updates-section">
        <div class="changelog-header">
            <h2 class="changelog-title">
                <span class="gradient-text">Changelog</span>
            </h2>
            <p class="changelog-subtitle">
                <?= t('common.changelog.subtitle') ?>
            </p>
        </div>

        <div class="changelog-content" id="changelogContent">
            <!-- Carregando -->
            <div class="changelog-loading" id="changelogLoading">
                <i class="fas fa-spinner fa-spin"></i>
                <span><?= t('common.changelog.loading_updates') ?></span>
            </div>
        </div>
    </div>

    <!-- Seção de Login (1/3) -->
    <div class="login-section">
        <div class="login-logo">
            <h1>7Carros</h1>
            <p><?= t('common.auth.rental_system') ?></p>
        </div>

        @if($error)
            <div class="alert alert-error">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="alert-content">
                    <strong><?= t('common.labels.attention') ?></strong>
                    <span>{{ $error }}</span>
                </div>
            </div>
        @endif

        @if($success)
            <div class="alert alert-success">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-content">
                    <strong><?= t('common.labels.info') ?></strong>
                    <span>{{ $success }}</span>
                </div>
            </div>
        @endif

        <form method="POST" action="/login" id="loginForm">
            @csrf

            <div class="form-group">
                <label for="username" class="form-label"><?= t('common.auth.username_email') ?></label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-input"
                        placeholder="<?= t('common.auth.username_email_placeholder') ?>"
                        value="{{ old('username') }}"
                        required
                        autocomplete="username">
                </div>
                @if($errors['username'] ?? false)
                    <div class="error-message show">{{ $errors['username'] }}</div>
                @endif
            </div>

            <div class="form-group">
                <label for="password" class="form-label"><?= t('common.auth.password') ?></label>
                <div class="input-icon-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="<?= t('common.auth.password_placeholder') ?>"
                        required
                        autocomplete="current-password">
                </div>
                @if($errors['password'] ?? false)
                    <div class="error-message show">{{ $errors['password'] }}</div>
                @endif
            </div>

            <div class="form-checkbox-wrapper">
                <input type="checkbox" id="remember" name="remember" class="form-checkbox">
                <label for="remember" class="form-checkbox-label"><?= t('common.auth.remember_login') ?></label>
            </div>

            <button type="submit" class="btn-login" id="loginButton">
                <i class="fas fa-sign-in-alt mr-2"></i><?= t('common.buttons.login') ?>
            </button>

            <div class="forgot-password-link">
                <a href="#" id="forgotPasswordLink"><?= t('common.auth.forgot_password') ?></a>
            </div>
        </form>

        <div class="login-footer">
            <p><?= t('common.auth.management_system') ?></p>
        </div>
    </div>
</div>

<!-- Modal de recuperação de senha -->
<div id="forgotPasswordModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h3 class="modal-title"><?= t('common.auth.forgot_password') ?></h3>
        <p class="modal-message"><?= t('common.auth.recovery_message') ?></p>

        <div class="form-group">
            <label for="recoveryEmail" class="form-label"><?= t('common.auth.email_or_username') ?></label>
            <div class="input-icon-wrapper">
                <i class="fas fa-envelope input-icon"></i>
                <input type="text" id="recoveryEmail" class="form-input" placeholder="<?= t('common.auth.email_or_username_placeholder') ?>" autocomplete="username">
            </div>
            <div class="error-message" id="recoveryError"></div>
            <div class="success-message" id="recoverySuccess"></div>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-modal btn-modal-secondary" id="cancelRecoveryButton"><?= t('common.buttons.cancel') ?></button>
            <button type="button" class="btn-modal btn-modal-primary" id="sendRecoveryButton">
                <i class="fas fa-paper-plane mr-2"></i><?= t('common.auth.send_link') ?>
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const loginI18n = <?= json_encode([
    'loadingMore' => t('common.changelog.loading_more'),
    'noneAvailable' => t('common.changelog.none_available'),
    'loadError' => t('common.changelog.load_error'),
    'new' => t('common.changelog.new'),
    'improved' => t('common.changelog.improved'),
    'fix' => t('common.changelog.fix'),
    'version' => t('common.changelog.version'),
    'mostRecent' => t('common.changelog.most_recent'),
    'current' => t('common.changelog.current'),
    'identifierRequired' => t('common.auth.identifier_required'),
    'sending' => t('common.auth.sending'),
    'resetRequestError' => t('common.auth.reset_request_error'),
    'resetError' => t('common.auth.reset_error'),
    'sendLink' => t('common.auth.send_link'),
    'signingIn' => t('common.auth.signing_in'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

document.addEventListener('DOMContentLoaded', function () {
    // Carregar changelog do banco de dados com scroll infinito
    const changelogContent = document.getElementById('changelogContent');
    const changelogLoading = document.getElementById('changelogLoading');

    const tipoConfig = {
        'N': { label: loginI18n.new, icon: 'fa-plus-circle', class: 'tipo-novo' },
        'A': { label: loginI18n.improved, icon: 'fa-arrow-up', class: 'tipo-aprimorado' },
        'C': { label: loginI18n.fix, icon: 'fa-wrench', class: 'tipo-correcao' }
    };

    // Estado da paginação
    let currentOffset = 0;
    const limite = 50;
    let isLoading = false;
    let hasMore = true;

    async function carregarChangelog(append = false) {
        if (isLoading || (!append && currentOffset > 0)) return;
        if (append && !hasMore) return;

        isLoading = true;

        // Mostrar loading
        if (!append) {
            changelogLoading.style.display = 'flex';
        } else {
            // Adicionar loading no final
            const loadingMore = document.createElement('div');
            loadingMore.id = 'loadingMore';
            loadingMore.className = 'changelog-loading-more';
            loadingMore.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + loginI18n.loadingMore;
            changelogContent.appendChild(loadingMore);
        }

        try {
            const response = await fetch(`/api/public/changelog?limite=${limite}&offset=${currentOffset}`);
            const result = await response.json();

            // Remover loading de "carregar mais"
            const loadingMore = document.getElementById('loadingMore');
            if (loadingMore) loadingMore.remove();

            if (result.success && result.data.length > 0) {
                hasMore = result.hasMore;
                renderChangelog(result.data, append);
                currentOffset += result.data.length;
            } else if (!append) {
                changelogContent.innerHTML = `
                    <div class="changelog-empty">
                        <i class="fas fa-info-circle"></i>
                        <span>${escapeHtml(loginI18n.noneAvailable)}</span>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Erro ao carregar changelog:', error);
            const loadingMore = document.getElementById('loadingMore');
            if (loadingMore) loadingMore.remove();

            if (!append) {
                changelogContent.innerHTML = `
                    <div class="changelog-empty">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>${escapeHtml(loginI18n.loadError)}</span>
                    </div>
                `;
            }
        } finally {
            isLoading = false;
            changelogLoading.style.display = 'none';
        }
    }

    function formatarData(dataStr) {
        if (!dataStr) return '';
        const partes = dataStr.split('-');
        if (partes.length !== 3) return dataStr;
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderChangelog(versoes, append = false) {
        let html = '';

        versoes.forEach((versao, index) => {
            const isDestaque = versao.destaque;
            const cardClass = isDestaque ? 'changelog-card-featured' : 'changelog-card-normal';
            const iconClass = isDestaque ? 'icon-featured' : 'icon-normal';
            const icon = isDestaque ? 'fa-star' : 'fa-code';

            html += `
                <div class="${cardClass}">
                    <div class="changelog-card-header">
                        <div class="changelog-icon ${iconClass}">
                            <i class="fas ${icon}"></i>
                        </div>
                        <div class="changelog-info">
                            <h3 class="changelog-version">
                                ${escapeHtml(loginI18n.version)} ${escapeHtml(versao.versao)}${isDestaque ? ' - ' + escapeHtml(loginI18n.mostRecent) : ''}
                            </h3>
                            <p class="changelog-date">${formatarData(versao.data)}</p>
                        </div>
                        ${isDestaque ? '<span class="changelog-badge">' + escapeHtml(loginI18n.current) + '</span>' : ''}
                    </div>

                    <div class="changelog-items">
            `;

            // Renderizar itens por tipo
            versao.itens.forEach(tipoData => {
                const config = tipoConfig[tipoData.tipo] || { label: tipoData.tipo_label, icon: 'fa-circle', class: '' };

                html += `
                    <div class="changelog-type-section ${isDestaque ? 'featured' : ''}">
                        <h4 class="changelog-type-title">
                            <i class="fas ${config.icon} ${config.class}"></i>
                            ${escapeHtml(tipoData.tipo_label)}
                        </h4>
                        <ul class="changelog-type-list">
                `;

                tipoData.mensagens.forEach(msg => {
                    html += `<li>${escapeHtml(msg)}</li>`;
                });

                html += `
                        </ul>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        if (append) {
            changelogContent.insertAdjacentHTML('beforeend', html);
        } else {
            changelogLoading.style.display = 'none';
            changelogContent.innerHTML = html;
        }
    }

    // Detectar scroll para carregar mais (no container updates-section)
    const updatesSection = document.querySelector('.updates-section');
    if (updatesSection) {
        updatesSection.addEventListener('scroll', function() {
            const { scrollTop, scrollHeight, clientHeight } = this;

            // Quando estiver a 100px do final, carregar mais
            if (scrollTop + clientHeight >= scrollHeight - 100) {
                carregarChangelog(true);
            }
        });
    }

    // Carregar inicial
    carregarChangelog();

    // Modal de recuperação de senha
    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const cancelRecoveryButton = document.getElementById('cancelRecoveryButton');
    const sendRecoveryButton = document.getElementById('sendRecoveryButton');

    forgotPasswordLink.addEventListener('click', function (e) {
        e.preventDefault();
        forgotPasswordModal.style.display = 'flex';
        document.getElementById('recoveryEmail').focus();
    });

    cancelRecoveryButton.addEventListener('click', function () {
        forgotPasswordModal.style.display = 'none';
        document.getElementById('recoveryEmail').value = '';
        document.getElementById('recoveryError').textContent = '';
        document.getElementById('recoverySuccess').textContent = '';
    });

    forgotPasswordModal.addEventListener('click', function (e) {
        if (e.target === forgotPasswordModal) {
            forgotPasswordModal.style.display = 'none';
        }
    });

    sendRecoveryButton.addEventListener('click', async function () {
        const emailInput = document.getElementById('recoveryEmail');
        const email = emailInput.value.trim();
        const errorDiv = document.getElementById('recoveryError');
        const successDiv = document.getElementById('recoverySuccess');
        const csrfToken = document.querySelector('input[name="_token"]')?.value || '';

        errorDiv.textContent = '';
        successDiv.textContent = '';
        errorDiv.classList.remove('show');
        successDiv.classList.remove('show');

        if (!email) {
            errorDiv.textContent = loginI18n.identifierRequired;
            errorDiv.classList.add('show');
            emailInput.focus();
            return;
        }

        sendRecoveryButton.disabled = true;
        sendRecoveryButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + loginI18n.sending;

        try {
            const response = await fetch('/auth/redefinir-senha', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ identifier: email })
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || loginI18n.resetRequestError);
            }

            successDiv.textContent = result.message;
            successDiv.classList.add('show');
            emailInput.value = '';
        } catch (error) {
            errorDiv.textContent = error.message || loginI18n.resetError;
            errorDiv.classList.add('show');
        } finally {
            sendRecoveryButton.disabled = false;
            sendRecoveryButton.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>' + loginI18n.sendLink;
        }
    });

    // Loading state no botão de login
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');

    loginForm.addEventListener('submit', function () {
        loginButton.disabled = true;
        loginButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + loginI18n.signingIn;
    });
});
</script>
@endsection
