<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <title><?= t('modules.checklists.digital.linked_pending_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .app-header { position: sticky; top: 0; z-index: 40; background: #fff; border-bottom: 1px solid #e2e8f0; padding: 12px 16px; }
        .app-header-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
        .app-header h1 { font-size: 18px; font-weight: 700; color: #1e293b; margin: 0; }
        .btn-header { background: #475569; color: #fff; border: none; border-radius: 20px; padding: 6px 14px; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 4px; text-decoration: none; }
        .filters { display: grid; grid-template-columns: 1fr 150px; gap: 8px; }
        .input, .select { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 9px 12px; font-size: 14px; background: #f8fafc; color: #1e293b; }
        .card-list { padding: 12px 16px; }
        .ck-card { background: #fff; border: 1px solid #e2e8f0; border-left: 5px solid #3b82f6; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .ck-top { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; margin-bottom: 8px; }
        .ck-code { font-weight: 700; color: #1e293b; font-size: 14px; }
        .badge { font-size: 12px; font-weight: 700; border-radius: 999px; padding: 4px 8px; white-space: nowrap; }
        .badge.saida { color: #92400e; background: #fef3c7; }
        .badge.entrada { color: #075985; background: #e0f2fe; }
        .detail { color: #475569; font-size: 13px; margin-bottom: 3px; }
        .detail strong { color: #1e293b; }
        .btn-action { width: 100%; margin-top: 10px; background: #3b82f6; color: #fff; border: none; border-radius: 8px; padding: 10px; font-size: 14px; font-weight: 700; display: flex; justify-content: center; align-items: center; gap: 6px; }
        .empty, .loading { text-align: center; padding: 36px 16px; color: #94a3b8; font-size: 14px; }
    </style>
</head>
<body>
<div class="app-header">
    <div class="app-header-top">
        <h1><?= t('modules.checklists.digital.linked_pending_title') ?></h1>
        <a href="/checklists/digital" class="btn-header">
            <i class="fas fa-arrow-left"></i> <?= t('modules.checklists.digital.back') ?>
        </a>
    </div>
    <div class="filters">
        <input type="text" id="searchInput" class="input" placeholder="<?= t('modules.checklists.placeholders.search') ?>">
        <select id="statusFilter" class="select" aria-label="<?= t('modules.checklists.digital.filter_status') ?>">
            <option value="aguardando_saida"><?= t('modules.checklists.digital.waiting_departure') ?></option>
            <option value="aguardando_chegada"><?= t('modules.checklists.digital.waiting_arrival') ?></option>
        </select>
    </div>
</div>

<div class="card-list" id="cardList"></div>
<div id="loading" class="loading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> <?= t('modules.checklists.digital.loading') ?></div>
<div id="empty" class="empty" style="display:none;"><?= t('modules.checklists.digital.no_records') ?></div>

<script src="/assets/js/api.min.js"></script>
<script>
(function() {
    const i18n = {
        waitingDeparture: '<?= addslashes(t('modules.checklists.digital.waiting_departure')) ?>',
        waitingArrival: '<?= addslashes(t('modules.checklists.digital.waiting_arrival')) ?>',
        startDeparture: '<?= addslashes(t('modules.checklists.digital.start_departure')) ?>',
        startArrival: '<?= addslashes(t('modules.checklists.digital.start_arrival')) ?>',
    };
    const cardList = document.getElementById('cardList');
    const loading = document.getElementById('loading');
    const empty = document.getElementById('empty');
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    let timer = null;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    async function carregar() {
        loading.style.display = 'block';
        empty.style.display = 'none';
        cardList.innerHTML = '';

        try {
            const res = await API.get('/api/checklists/vinculados', {
                search: searchInput.value || '',
                status: statusFilter.value || 'aguardando_saida',
            });

            if (!res.success || !res.data || res.data.length === 0) {
                empty.style.display = 'block';
                return;
            }

            res.data.forEach(renderCard);
        } catch (e) {
            empty.style.display = 'block';
        } finally {
            loading.style.display = 'none';
        }
    }

    function renderCard(item) {
        const etapa = item.etapa === 'entrada' ? 'entrada' : 'saida';
        const label = etapa === 'entrada' ? i18n.waitingArrival : i18n.waitingDeparture;
        const button = etapa === 'entrada' ? i18n.startArrival : i18n.startDeparture;
        const href = item.checklist_id
            ? '/checklists/novo?retomar=' + encodeURIComponent(item.vinculo_codigo || item.codigo) + '&etapa=' + etapa + '&id_veiculo=' + encodeURIComponent(item.id_veiculo)
            : '/checklists/novo?tipo=V&etapa=' + etapa + '&vinculo=' + encodeURIComponent(item.vinculo_codigo || item.codigo) + '&id_veiculo=' + encodeURIComponent(item.id_veiculo);

        const card = document.createElement('div');
        card.className = 'ck-card';
        card.innerHTML =
            '<div class="ck-top">' +
                '<div class="ck-code">' + escapeHtml(item.codigo || item.vinculo_codigo || '-') + '</div>' +
                '<span class="badge ' + etapa + '">' + label + '</span>' +
            '</div>' +
            '<div class="detail"><strong>Cliente:</strong> ' + escapeHtml(item.cliente || '-') + '</div>' +
            '<div class="detail"><strong>Veículo:</strong> ' + escapeHtml(item.veiculo || '-') + '</div>' +
            '<button type="button" class="btn-action" data-href="' + href + '"><i class="fas fa-play"></i> ' + button + '</button>';

        card.querySelector('button').addEventListener('click', function() {
            window.location.href = this.dataset.href;
        });
        cardList.appendChild(card);
    }

    searchInput.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(carregar, 300);
    });
    statusFilter.addEventListener('change', carregar);
    carregar();
})();
</script>
</body>
</html>
