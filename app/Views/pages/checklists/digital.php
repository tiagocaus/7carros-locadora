<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <title><?= t('modules.checklists.digital.title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 0;
            padding-bottom: 110px;
            -webkit-tap-highlight-color: transparent;
        }

        .app-header {
            position: sticky;
            top: 0;
            z-index: 40;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 16px;
        }
        .app-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .app-header h1 { font-size: 18px; font-weight: 700; color: #1e293b; margin: 0; }
        .btn-header {
            background: #475569;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        .search-input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 9px 12px 9px 36px;
            font-size: 14px;
            background: #f8fafc;
            color: #1e293b;
        }
        .search-input:focus { outline: none; border-color: #3b82f6; }
        .search-wrap { position: relative; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px; }

        .card-list { padding: 12px 16px; }

        .ck-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-bottom: 10px;
            overflow: hidden;
            display: flex;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .ck-card-border {
            width: 5px;
            flex-shrink: 0;
        }
        .ck-card-border.vinculado { background: #3b82f6; }
        .ck-card-border.avulso { background: #c2825a; }
        .ck-card-body {
            flex: 1;
            padding: 12px 14px;
            position: relative;
        }
        .ck-card-top {
            text-align: right;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 2px;
        }
        .ck-card-code { font-weight: 700; color: #1e293b; }
        .ck-card-status { text-align: right; font-size: 13px; color: #64748b; margin-bottom: 6px; }
        .ck-card-status .badge-done { color: #16a34a; font-weight: 600; font-style: italic; }
        .ck-card-status .badge-pending { color: #f59e0b; font-weight: 600; font-style: italic; }
        .ck-card-detail { font-size: 13px; color: #475569; }
        .ck-card-detail strong { color: #1e293b; }
        .ck-card-action {
            position: absolute;
            bottom: 10px;
            right: 12px;
        }
        .ck-card-action button {
            background: none;
            border: none;
            color: #3b82f6;
            font-size: 20px;
            cursor: pointer;
            padding: 4px;
        }

        .bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 40;
            background: #fff;
            border-top: 1px solid #e2e8f0;
            padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px)) 16px;
        }
        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 8px;
            font-size: 12px;
            color: #64748b;
        }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; }
        .legend-dot.vinculado { background: #3b82f6; }
        .legend-dot.avulso { background: #c2825a; }

        .btn-novo {
            width: 100%;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .loading-more {
            text-align: center;
            padding: 16px;
            color: #94a3b8;
            font-size: 13px;
        }
        .no-records {
            text-align: center;
            padding: 40px 16px;
            color: #94a3b8;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="app-header">
    <div class="app-header-top">
        <h1><?= t('modules.checklists.digital.title') ?></h1>
        <div style="display:flex;gap:8px;">
            <?php if ($tem_dashboard): ?>
            <a href="/dashboard" class="btn-header">
                <i class="fas fa-arrow-left"></i> <?= t('modules.checklists.digital.back') ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="search-wrap">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchInput" class="search-input" placeholder="<?= t('modules.checklists.placeholders.search') ?>">
    </div>
</div>

<div class="card-list" id="cardList"></div>
<div id="loadingMore" class="loading-more" style="display:none;">
    <i class="fas fa-spinner fa-spin"></i> <?= t('modules.checklists.digital.loading') ?>
</div>
<div id="noRecords" class="no-records" style="display:none;">
    <i class="fas fa-inbox" style="font-size:32px;margin-bottom:8px;display:block;"></i>
    <?= t('modules.checklists.digital.no_records') ?>
</div>

<div class="bottom-bar">
    <div class="legend">
        <div class="legend-item"><div class="legend-dot vinculado"></div> <?= t('modules.checklists.digital.legend_linked') ?></div>
        <div class="legend-item"><div class="legend-dot avulso"></div> <?= t('modules.checklists.digital.legend_standalone') ?></div>
    </div>
    <button class="btn-novo" onclick="window.location.href='/checklists/novo'">
        <i class="fas fa-plus"></i> <?= t('modules.checklists.digital.new') ?>
    </button>
</div>

<script src="/assets/js/api.min.js"></script>
<script>
(function() {
    const i18n = {
        statusPending: '<?= addslashes(t('modules.checklists.digital.status_pending')) ?>',
        statusDone: '<?= addslashes(t('modules.checklists.digital.status_done')) ?>',
        continueLabel: '<?= addslashes(t('modules.checklists.digital.continue')) ?>',
        loading: '<?= addslashes(t('modules.checklists.digital.loading')) ?>',
    };

    let currentPage = 1;
    const perPage = 20;
    let searchTerm = '';
    let isLoading = false;
    let hasMore = true;
    let searchTimeout = null;

    const cardList = document.getElementById('cardList');
    const loadingMore = document.getElementById('loadingMore');
    const noRecords = document.getElementById('noRecords');

    async function carregarDados(page, append) {
        if (isLoading) return;
        isLoading = true;
        loadingMore.style.display = 'block';
        noRecords.style.display = 'none';

        try {
            const res = await API.get('/api/checklists', {
                page: page,
                perPage: perPage,
                search: searchTerm,
            });

            if (res.success) {
                if (!append) cardList.innerHTML = '';

                if (res.data.length === 0 && page === 1) {
                    noRecords.style.display = 'block';
                    hasMore = false;
                } else {
                    renderCards(res.data);
                    hasMore = res.pagination.hasNext;
                }
            }
        } catch (e) {
            console.error(e);
        } finally {
            isLoading = false;
            loadingMore.style.display = 'none';
        }
    }

    function renderCards(dados) {
        dados.forEach(item => {
            const isVinculado = item.tipo === 'V';
            const isPending = item.status === '1';
            const codigo = escapeHtml(item.codigo || '-');
            const placa = escapeHtml(item.placa || '-');
            const veiculoModelo = escapeHtml(item.veiculo_modelo || '');
            const marca = escapeHtml(item.marca || '');
            const modeloNome = escapeHtml(item.modelo_nome || '-');
            const data = formatarData(item.data_checklist || item.created_at);

            const card = document.createElement('div');
            card.className = 'ck-card';
            const statusHtml = isPending
                ? '<span class="badge-pending">' + i18n.statusPending + '</span>'
                : '<span class="badge-done">' + i18n.statusDone + '</span>';
            const actionHtml = isPending
                ? '<button onclick="window.location.href=\'/checklists/novo?retomar=' + item.id + '\'" title="' + i18n.continueLabel + '" style="color:#a855f7"><i class="fas fa-play-circle"></i></button>'
                : '<button onclick="abrirChecklist(' + item.id + ')" title="Visualizar"><i class="fas fa-eye"></i></button>';

            card.innerHTML =
                '<div class="ck-card-border ' + (isVinculado ? 'vinculado' : 'avulso') + '"></div>' +
                '<div class="ck-card-body">' +
                    '<div class="ck-card-top">' + data + ' - <span class="ck-card-code">' + codigo + '</span></div>' +
                    '<div class="ck-card-status">Status: ' + statusHtml + '</div>' +
                    '<div class="ck-card-detail"><strong>Veículo:</strong> ' + placa + ' - ' + veiculoModelo + '</div>' +
                    '<div class="ck-card-detail"><strong>Modelo:</strong> ' + modeloNome + '</div>' +
                    '<div class="ck-card-action">' + actionHtml + '</div>' +
                '</div>';

            cardList.appendChild(card);
        });
    }

    window.abrirChecklist = function(id) {
        window.location.href = '/checklists/visualizar/' + id;
    };

    function formatarData(dataStr) {
        if (!dataStr) return '-';
        const d = new Date(dataStr);
        if (isNaN(d.getTime())) return '-';
        const dia = String(d.getDate()).padStart(2, '0');
        const mes = String(d.getMonth() + 1).padStart(2, '0');
        const ano = d.getFullYear();
        const hora = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');
        return dia + '/' + mes + '/' + ano + ' - ' + hora + ':' + min;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Busca com debounce
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchTerm = this.value.trim();
            currentPage = 1;
            hasMore = true;
            carregarDados(1, false);
        }, 300);
    });

    // Infinite scroll
    window.addEventListener('scroll', function() {
        if (!hasMore || isLoading) return;
        const scrollBottom = window.innerHeight + window.scrollY;
        const docHeight = document.documentElement.scrollHeight;
        if (scrollBottom >= docHeight - 200) {
            currentPage++;
            carregarDados(currentPage, true);
        }
    });

    // Carregar dados iniciais
    carregarDados(1, false);
})();
</script>
</body>
</html>
