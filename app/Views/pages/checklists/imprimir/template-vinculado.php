<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= t('modules.checklists.print.title_prefix') ?> <?= htmlspecialchars($checklist['codigo'] ?? '') ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
        }
        .header-table td {
            vertical-align: top;
            padding: 0;
        }
        .logo-img {
            max-height: 40px;
            max-width: 180px;
            margin-bottom: 5px;
        }
        .empresa-nome {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .empresa-detalhe {
            font-size: 8pt;
            color: #666;
        }
        .doc-titulo {
            font-size: 12pt;
            font-weight: bold;
            text-align: right;
        }
        .doc-detalhe {
            font-size: 8pt;
            text-align: right;
        }
        .qr-img {
            width: 80px;
            height: 80px;
        }
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            background: #f0f0f0;
            padding: 5px 10px;
            margin-bottom: 8px;
            margin-top: 15px;
            border-left: 3px solid #333;
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .section-title-table {
            width: 100%;
            border-collapse: collapse;
        }
        .veiculo-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            background: #f5f5f5;
        }
        .veiculo-table td {
            padding: 6px 10px;
            border: 1px solid #ddd;
        }
        .veiculo-label {
            font-weight: bold;
            color: #666;
            font-size: 8pt;
            width: 12%;
        }
        .veiculo-value {
            font-size: 10pt;
        }
        .questoes-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        .questoes-table th {
            background: #f0f0f0;
            padding: 5px 8px;
            text-align: left;
            font-size: 9pt;
            border: 1px solid #ddd;
        }
        .questoes-table td {
            padding: 4px 8px;
            font-size: 9pt;
            border: 1px solid #eee;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
        .badge-confere {
            background: #dcfce7;
            color: #166534;
        }
        .badge-nao-confere {
            background: #fef2f2;
            color: #991b1b;
        }
        .badge-danificado {
            background: #fff7ed;
            color: #9a3412;
        }
        .badge-na {
            background: #f1f5f9;
            color: #64748b;
        }
        .obs-box {
            border: 1px solid #ddd;
            padding: 10px;
            min-height: 30px;
            background: #fafafa;
            margin-bottom: 10px;
            font-size: 9pt;
            text-align: justify;
        }
        .foto-img {
            width: 100%;
            border: 1px solid #ddd;
        }
        .foto-legenda {
            font-size: 8pt;
            color: #666;
            margin-top: 3px;
        }
        .assinatura-img {
            max-height: 80px;
        }
        .assinatura-linha {
            border-top: 1px solid #333;
            padding-top: 5px;
            font-size: 8pt;
        }
        .tipo-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
        .tipo-vinculado {
            background: #dcfce7;
            color: #166534;
        }
    </style>
</head>
<body>
    <!-- Definicao do footer de assinaturas (nao aparece ate ser ativado com sethtmlpagefooter) -->
    <?php
    $temAssSaidaFooter = !empty($assinaturaPath);
    $temAssChegadaFooter = !empty($assinaturaChegadaPath);
    ?>
    <?php if ($temAssSaidaFooter || $temAssChegadaFooter): ?>
    <htmlpagefooter name="assinaturas">
        <table width="100%">
            <tr>
                <?php if ($temAssSaidaFooter && $temAssChegadaFooter): ?>
                <td style="width: 50%; text-align: center;">
                    <img src="<?= $assinaturaPath ?>" style="max-height: 60px;" alt="Assinatura">
                    <hr style="border: none; border-top: 1px solid #333; margin: 0 auto; width: 80%;">
                    <div style="padding-top: 5px; font-size: 8pt;"><strong><?= t('modules.checklists.print.signature_departure') ?></strong></div>
                </td>
                <td style="width: 50%; text-align: center;">
                    <img src="<?= $assinaturaChegadaPath ?>" style="max-height: 60px;" alt="Assinatura">
                    <hr style="border: none; border-top: 1px solid #333; margin: 0 auto; width: 80%;">
                    <div style="padding-top: 5px; font-size: 8pt;"><strong><?= t('modules.checklists.print.signature_arrival') ?></strong></div>
                </td>
                <?php elseif ($temAssSaidaFooter): ?>
                <td style="width: 100%; text-align: center;">
                    <img src="<?= $assinaturaPath ?>" style="max-height: 60px;" alt="Assinatura">
                    <hr style="border: none; border-top: 1px solid #333; margin: 0 auto; width: 80%;">
                    <div style="padding-top: 5px; font-size: 8pt;"><strong><?= t('modules.checklists.print.signature_departure') ?></strong></div>
                </td>
                <?php else: ?>
                <td style="width: 100%; text-align: center;">
                    <img src="<?= $assinaturaChegadaPath ?>" style="max-height: 60px;" alt="Assinatura">
                    <hr style="border: none; border-top: 1px solid #333; margin: 0 auto; width: 80%;">
                    <div style="padding-top: 5px; font-size: 8pt;"><strong><?= t('modules.checklists.print.signature_arrival') ?></strong></div>
                </td>
                <?php endif; ?>
            </tr>
        </table>
        <div style="text-align: center; font-size: 8pt; margin-top: 5px;">{PAGENO}/{nbpg}</div>
    </htmlpagefooter>
    <?php endif; ?>

    <htmlpagefooter name="paginacao">
        <div style="text-align: center; font-size: 8pt;">{PAGENO}/{nbpg}</div>
    </htmlpagefooter>

    <!-- Ativar paginacao em todas as paginas por padrao -->
    <sethtmlpagefooter name="paginacao" value="on" />

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <?php if (!empty($logoPath)): ?>
                    <img src="<?= $logoPath ?>" class="logo-img" alt="Logo"><br>
                <?php endif; ?>
                <div class="empresa-nome"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Locadora') ?></div>
                <div class="empresa-detalhe">CPF/CNPJ: <?= htmlspecialchars($empresa['cpf_cnpj'] ?? '-') ?></div>
            </td>
            <td style="width: 30%; text-align: right;">
                <div class="doc-titulo"><?= t('modules.checklists.print.doc_title') ?></div>
                <div class="doc-detalhe"><strong><?= t('modules.checklists.print.code') ?>:</strong> <?= htmlspecialchars($checklist['codigo'] ?? '-') ?></div>
                <div class="doc-detalhe"><strong><?= t('modules.checklists.print.type') ?>:</strong> <span class="tipo-badge tipo-vinculado"><?= t('modules.checklists.types.linked') ?></span></div>
                <div class="doc-detalhe"><strong><?= t('modules.checklists.print.date') ?>:</strong> <?= !empty($checklist['created_at']) ? date('d/m/Y H:i', strtotime($checklist['created_at'])) : '-' ?></div>
            </td>
            <td style="width: 15%; text-align: right;">
                <?php if (!empty($qrPath)): ?>
                    <img src="<?= $qrPath ?>" class="qr-img" alt="QR Code">
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- Veiculo -->
    <?php if (!empty($checklist['placa'])): ?>
    <table class="veiculo-table">
        <tr>
            <td class="veiculo-label"><?= t('modules.checklists.print.plate') ?>:</td>
            <td class="veiculo-value"><?= htmlspecialchars($checklist['placa'] ?? '-') ?></td>
            <td class="veiculo-label"><?= t('modules.checklists.print.vehicle') ?>:</td>
            <td class="veiculo-value"><?= htmlspecialchars(($checklist['marca'] ?? '') . ' ' . ($checklist['veiculo_modelo'] ?? '')) ?></td>
            <td class="veiculo-label"><?= t('modules.checklists.print.renavam') ?>:</td>
            <td class="veiculo-value"><?= htmlspecialchars($checklist['renavam'] ?? '-') ?></td>
        </tr>
    </table>
    <?php endif; ?>

    <?php
    function renderRespostaBadge(string $opt): string {
        switch ($opt) {
            case '1': return '<span class="badge badge-confere">' . t('modules.checklists.answers.matches') . '</span>';
            case '2': return '<span class="badge badge-nao-confere">' . t('modules.checklists.answers.not_matches') . '</span>';
            case '3': return '<span class="badge badge-danificado">' . t('modules.checklists.answers.damaged') . '</span>';
            case '4': return '<span class="badge badge-na">' . t('modules.checklists.answers.na') . '</span>';
            default:  return '<span class="badge badge-na">-</span>';
        }
    }

    // Inline styles apenas para layout lado a lado
    $tdLeft  = 'vertical-align: top; padding: 0 8px 8px 0;';
    $tdGap   = 'width: 2%;';
    $tdRight = 'vertical-align: top; padding: 0 0 8px 8px;';

    // Preparar fotos filtradas
    $fotosSaida = array_values(array_filter($vistoriaSaida, fn($item) => !empty($item['img_path'])));
    $fotosChegada = array_values(array_filter($vistoriaChegada, fn($item) => !empty($item['img_path'])));
    $maxFotos = max(count($fotosSaida), count($fotosChegada));
    ?>

    <!-- Layout lado a lado: cada secao em sua propria row -->
    <table style="width: 100%; border-collapse: collapse;">

        <!-- Row: Titulos -->
        <tr>
            <td style="width: 49%; vertical-align: top; padding: 15px 0 8px 0;">
                <table class="section-title-table">
                    <tr>
                        <td class="section-title" style="background: #e8e8e8;">
                            <?= t('modules.checklists.print.departure') ?><?= !empty($checklist['data_saida']) ? ' - ' . date('d/m/Y H:i', strtotime($checklist['data_saida'])) : '' ?>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="<?= $tdGap ?>"></td>
            <td style="width: 49%; vertical-align: top; padding: 15px 0 8px 0;">
                <table class="section-title-table">
                    <tr>
                        <td class="section-title" style="background: #e8e8e8;">
                            <?= t('modules.checklists.print.arrival') ?><?= !empty($checklist['data_chegada']) ? ' - ' . date('d/m/Y H:i', strtotime($checklist['data_chegada'])) : '' ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Row: Questionario -->
        <?php if (!empty($questoesSaida) || !empty($questoesChegada)): ?>
        <tr>
            <td style="<?= $tdLeft ?>">
                <?php if (!empty($questoesSaida)): ?>
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.checklists.print.questionnaire') ?></div>
                <table class="questoes-table">
                    <tr>
                        <th style="width: 8%;">#</th>
                        <th style="width: 62%;"><?= t('modules.checklists.print.item') ?></th>
                        <th style="width: 30%;"><?= t('modules.checklists.print.answer') ?></th>
                    </tr>
                    <?php foreach ($questoesSaida as $i => $q): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($q['content'] ?? '-') ?></td>
                        <td><?= renderRespostaBadge($q['opt'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </td>
            <td style="<?= $tdGap ?>"></td>
            <td style="<?= $tdRight ?>">
                <?php if (!empty($questoesChegada)): ?>
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.checklists.print.questionnaire') ?></div>
                <table class="questoes-table">
                    <tr>
                        <th style="width: 8%;">#</th>
                        <th style="width: 62%;"><?= t('modules.checklists.print.item') ?></th>
                        <th style="width: 30%;"><?= t('modules.checklists.print.answer') ?></th>
                    </tr>
                    <?php foreach ($questoesChegada as $i => $q): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($q['content'] ?? '-') ?></td>
                        <td><?= renderRespostaBadge($q['opt'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?>
                <div style="text-align: center; color: #999; padding: 20px; font-style: italic; font-size: 9pt;"><?= t('modules.checklists.print.no_arrival_data') ?></div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>

        <!-- Row: Label Vistoria -->
        <?php if ($maxFotos > 0): ?>
        <tr>
            <td style="<?= $tdLeft ?>">
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.checklists.print.inspection_photos') ?>:</div>
            </td>
            <td style="<?= $tdGap ?>"></td>
            <td style="<?= $tdRight ?>">
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.checklists.print.inspection_photos') ?>:</div>
            </td>
        </tr>

        <!-- Rows: Fotos (1 por row, lado a lado) -->
        <?php for ($i = 0; $i < $maxFotos; $i++): ?>
        <tr>
            <td style="<?= $tdLeft ?> text-align: center;">
                <?php if (isset($fotosSaida[$i])): ?>
                    <img src="<?= $fotosSaida[$i]['img_path'] ?>" class="foto-img" alt="Foto">
                    <div class="foto-legenda"><?= htmlspecialchars($fotosSaida[$i]['content'] ?? '') ?></div>
                <?php endif; ?>
            </td>
            <td style="<?= $tdGap ?>"></td>
            <td style="<?= $tdRight ?> text-align: center;">
                <?php if (isset($fotosChegada[$i])): ?>
                    <img src="<?= $fotosChegada[$i]['img_path'] ?>" class="foto-img" alt="Foto">
                    <div class="foto-legenda"><?= htmlspecialchars($fotosChegada[$i]['content'] ?? '') ?></div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endfor; ?>
        <?php endif; ?>

        <!-- Row: Observacoes -->
        <?php if (!empty($checklist['obs']) || !empty($checklist['obs_chegada'])): ?>
        <tr>
            <td style="<?= $tdLeft ?>">
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.checklists.print.observations') ?></div>
                <div class="obs-box">
                    <?= nl2br(htmlspecialchars($checklist['obs'] ?? '')) ?>
                </div>
            </td>
            <td style="<?= $tdGap ?>"></td>
            <td style="<?= $tdRight ?>">
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px"><?= t('modules.checklists.print.observations') ?></div>
                <div class="obs-box">
                    <?= nl2br(htmlspecialchars($checklist['obs_chegada'] ?? '')) ?>
                </div>
            </td>
        </tr>
        <?php endif; ?>


    </table>

    <!-- Ativar footer de assinaturas na ultima pagina -->
    <?php if (!empty($assinaturaPath) || !empty($assinaturaChegadaPath)): ?>
    <sethtmlpagefooter name="assinaturas" value="on" />
    <?php endif; ?>
</body>
</html>
