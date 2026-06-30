<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= t('modules.checklists.digital.title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; padding: 0; }

        .header-card {
            margin: 12px;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            background: #fff;
        }
        .header-border { width: 6px; flex-shrink: 0; }
        .header-border.vinculado { background: #3b82f6; }
        .header-border.avulso { background: #c2825a; }
        .header-body { flex: 1; padding: 14px 16px; }
        .header-top { text-align: right; font-size: 13px; color: #64748b; margin-bottom: 6px; }
        .header-code { font-weight: 700; color: #1e293b; }
        .header-detail { font-size: 14px; color: #475569; margin-bottom: 2px; }
        .header-detail strong { color: #1e293b; }

        .section { padding: 0 16px; margin-bottom: 20px; }
        .section-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 10px; }
        .section-subtitle { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }

        /* Questionario */
        .q-item { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 14px; color: #475569; }
        .q-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
        .q-dot-1 { background: #22c55e; } /* Confere */
        .q-dot-2 { background: #ef4444; } /* Nao confere */
        .q-dot-3 { background: #f59e0b; } /* Danificado */
        .q-dot-4 { background: #6366f1; } /* N/A */

        .legend { margin-top: 12px; margin-bottom: 4px; }
        .legend-label { font-size: 13px; color: #64748b; margin-bottom: 6px; }
        .legend-badges { display: flex; gap: 6px; flex-wrap: wrap; }
        .legend-badge {
            font-size: 12px; font-weight: 600; color: #fff;
            padding: 4px 10px; border-radius: 6px;
        }
        .legend-badge.c1 { background: #22c55e; }
        .legend-badge.c2 { background: #ef4444; }
        .legend-badge.c3 { background: #f59e0b; }
        .legend-badge.c4 { background: #6366f1; }

        /* Vistoria */
        .foto-card {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .foto-card img { width: 100%; display: block; }
        .foto-card .caption { padding: 8px 12px; font-size: 13px; color: #475569; }

        .cols-2 { display: flex; gap: 10px; }
        .cols-2 > div { flex: 1; min-width: 0; }

        .back-bar {
            position: sticky;
            top: 0;
            z-index: 40;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
        }
        .back-bar h1 { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0; }
        .btn-back {
            background: #475569; color: #fff; border: none; border-radius: 20px;
            padding: 6px 14px; font-size: 13px; font-weight: 500; cursor: pointer;
            display: flex; align-items: center; gap: 4px; text-decoration: none;
        }
    </style>
</head>
<body>

<div class="back-bar">
    <h1><?= t('modules.checklists.digital.title') ?></h1>
    <a href="/checklists/digital" class="btn-back">
        <i class="fas fa-arrow-left"></i> <?= t('modules.checklists.digital.back') ?>
    </a>
</div>

<!-- Header Card -->
<div class="header-card">
    <div class="header-border <?= $isVinculado ? 'vinculado' : 'avulso' ?>"></div>
    <div class="header-body">
        <div class="header-top">
            <?php
                $dataExibir = $dataSaida ?: ($checklist['data_checklist'] ?? '');
                if ($dataExibir) {
                    echo format_operational_datetime($dataExibir);
                } elseif (!empty($checklist['created_at'])) {
                    echo format_datetime($checklist['created_at']);
                }
            ?>
            - <span class="header-code"><?= htmlspecialchars($checklist['codigo'] ?? '') ?></span>
        </div>
        <div class="header-top">
            Status: <strong style="color:<?= ($checklist['status'] ?? '') === '1' ? '#f59e0b' : '#16a34a' ?>">
                <?= ($checklist['status'] ?? '') === '1' ? t('modules.checklists.digital.status_pending') : t('modules.checklists.digital.status_done') ?>
            </strong>
        </div>
        <div class="header-detail"><strong><?= t('modules.checklists.digital.vehicle') ?>:</strong> <?= htmlspecialchars(($checklist['placa'] ?? '-') . ' - ' . ($checklist['veiculo_modelo'] ?? '')) ?></div>
        <div class="header-detail"><strong><?= t('modules.checklists.digital.checklist_model') ?>:</strong> <?= htmlspecialchars($checklist['modelo_nome'] ?? '-') ?></div>
    </div>
</div>

<!-- Questionario -->
<div class="section">
    <div class="section-title"><?= t('modules.checklists.digital.questionnaire') ?></div>

    <?php if ($isVinculado && !empty($questoesChegada)): ?>
    <div class="cols-2">
        <div>
            <div class="section-subtitle"><?= t('modules.checklists.digital.moment_departure') ?></div>
            <?php foreach ($questoesSaida as $q): ?>
            <div class="q-item">
                <div class="q-dot q-dot-<?= htmlspecialchars($q['opt'] ?? '1') ?>"></div>
                <?= htmlspecialchars($q['content'] ?? '') ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div>
            <div class="section-subtitle"><?= t('modules.checklists.digital.moment_arrival') ?></div>
            <?php foreach ($questoesChegada as $q): ?>
            <div class="q-item">
                <div class="q-dot q-dot-<?= htmlspecialchars($q['opt'] ?? '1') ?>"></div>
                <?= htmlspecialchars($q['content'] ?? '') ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
        <?php foreach ($questoesSaida as $q): ?>
        <div class="q-item">
            <div class="q-dot q-dot-<?= htmlspecialchars($q['opt'] ?? '1') ?>"></div>
            <?= htmlspecialchars($q['content'] ?? '') ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="legend">
        <div class="legend-label"><?= t('modules.checklists.print.questionnaire') ?></div>
        <div class="legend-badges">
            <span class="legend-badge c1"><?= t('modules.checklists.answers.matches') ?></span>
            <span class="legend-badge c2"><?= t('modules.checklists.answers.not_matches') ?></span>
            <span class="legend-badge c3"><?= t('modules.checklists.answers.damaged') ?></span>
            <span class="legend-badge c4"><?= t('modules.checklists.answers.na') ?></span>
        </div>
    </div>
</div>

<!-- Vistoria -->
<?php
$fotosSaida = array_filter($vistoriaSaida, fn($v) => !empty($v['img_url']));
$fotosChegada = array_filter($vistoriaChegada, fn($v) => !empty($v['img_url']));
?>
<?php if (!empty($fotosSaida) || !empty($fotosChegada)): ?>
<div class="section">
    <div class="section-title"><?= t('modules.checklists.digital.tab_inspection') ?></div>

    <?php if ($isVinculado && !empty($fotosChegada)): ?>
        <!-- Vinculado: lado a lado -->
        <div class="cols-2" style="margin-bottom:8px;">
            <div><div class="section-subtitle"><?= t('modules.checklists.digital.moment_departure') ?></div></div>
            <div><div class="section-subtitle"><?= t('modules.checklists.digital.moment_arrival') ?></div></div>
        </div>
        <?php
        $arrSaida = array_values($fotosSaida);
        $arrChegada = array_values($fotosChegada);
        $max = max(count($arrSaida), count($arrChegada));
        for ($i = 0; $i < $max; $i++):
        ?>
        <div class="cols-2">
            <div>
                <?php if (isset($arrSaida[$i])): ?>
                <div class="foto-card">
                    <img src="<?= htmlspecialchars($arrSaida[$i]['img_url']) ?>" alt="">
                    <div class="caption"><?= htmlspecialchars($arrSaida[$i]['content'] ?? '') ?></div>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <?php if (isset($arrChegada[$i])): ?>
                <div class="foto-card">
                    <img src="<?= htmlspecialchars($arrChegada[$i]['img_url']) ?>" alt="">
                    <div class="caption"><?= htmlspecialchars($arrChegada[$i]['content'] ?? '') ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endfor; ?>
    <?php else: ?>
        <!-- Avulso: fotos grandes, uma por linha -->
        <?php foreach ($fotosSaida as $foto): ?>
        <div class="foto-card">
            <img src="<?= htmlspecialchars($foto['img_url']) ?>" alt="">
            <div class="caption"><?= htmlspecialchars($foto['content'] ?? '') ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>
