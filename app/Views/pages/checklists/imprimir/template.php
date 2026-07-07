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
        }
        .fotos-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .fotos-table td {
            padding: 5px;
            text-align: center;
            vertical-align: top;
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
        .assinatura-table {
            width: 100%;
            margin-top: 20px;
        }
        .assinatura-table td {
            width: 45%;
            text-align: center;
            vertical-align: bottom;
            padding-top: 20px;
        }
        .assinatura-linha {
            border-top: 1px solid #333;
            padding-top: 5px;
            font-size: 8pt;
        }
        .assinatura-img {
            max-height: 120px;
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
        .tipo-avulso {
            background: #dbeafe;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <!-- Definicao do footer de assinaturas (nao aparece ate ser ativado com sethtmlpagefooter) -->
    <?php
    $isAvulsoFooter = ($checklist['tipo'] ?? '') === 'A';
    $temAssSaidaFooter = !empty($assinaturaPath);
    $temAssChegadaFooter = !empty($assinaturaChegadaPath) && !$isAvulsoFooter;
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
                    <div style="padding-top: 5px; font-size: 8pt;"><strong><?= t('modules.checklists.print.signature') ?></strong></div>
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
                <div class="doc-detalhe"><strong><?= t('modules.checklists.print.type') ?>:</strong>
                    <?php if (($checklist['tipo'] ?? '') === 'V'): ?>
                        <span class="tipo-badge tipo-vinculado"><?= t('modules.checklists.types.linked') ?></span>
                    <?php elseif (($checklist['tipo'] ?? '') === 'A'): ?>
                        <span class="tipo-badge tipo-avulso"><?= t('modules.checklists.types.standalone') ?></span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </div>
                <div class="doc-detalhe"><strong><?= t('modules.checklists.print.date') ?>:</strong> <?= !empty($checklist['created_at']) ? format_datetime($checklist['created_at']) : '-' ?></div>
            </td>
            <td style="width: 15%; text-align: right; vertical-align: top;">
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
    /**
     * Renderiza badge de resposta do questionario
     * opt: 1=Confere, 2=Nao confere, 3=Danificado, 4=N/A
     */
    function renderRespostaBadge(string $opt): string {
        switch ($opt) {
            case '1': return '<span class="badge badge-confere">' . t('modules.checklists.answers.matches') . '</span>';
            case '2': return '<span class="badge badge-nao-confere">' . t('modules.checklists.answers.not_matches') . '</span>';
            case '3': return '<span class="badge badge-danificado">' . t('modules.checklists.answers.damaged') . '</span>';
            case '4': return '<span class="badge badge-na">' . t('modules.checklists.answers.na') . '</span>';
            default:  return '<span class="badge badge-na">-</span>';
        }
    }

    /**
     * Renderiza secao de questionario + observacoes + fotos
     */
    function renderSecao(
        string $titulo,
        ?string $data,
        array $questoes,
        ?string $obs,
        array $vistoria
    ): void {
    ?>
    <div class="section-title"><?= $titulo ?> <?= !empty($data) ? '- ' . format_operational_datetime($data) : '' ?></div>

    <?php if (!empty($questoes)): ?>
    <table class="questoes-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 65%;"><?= t('modules.checklists.print.item') ?></th>
                <th style="width: 30%;"><?= t('modules.checklists.print.answer') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($questoes as $i => $q): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($q['name'] ?? '-') ?></td>
                <td><?= renderRespostaBadge($q['opt'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($obs)): ?>
    <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.checklists.print.observations') ?>:</div>
    <div class="obs-box">
        <?= nl2br(htmlspecialchars($obs)) ?>
    </div>
    <?php endif; ?>

    <?php
    // Filtrar apenas itens com foto
    $fotosComImagem = array_filter($vistoria, fn($item) => !empty($item['img_path']));
    if (!empty($fotosComImagem)):
    ?>
    <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.checklists.print.inspection_photos') ?>:</div>
    <table class="fotos-table">
        <?php
        $fotosArray = array_values($fotosComImagem);
        $totalFotos = count($fotosArray);
        $idx = 0;
        while ($idx < $totalFotos):
            // Detectar orientacao da foto para definir colunas por linha
            $imgPath = $fotosArray[$idx]['img_path'] ?? '';
            $cols = 3; // default: portrait (3 por linha)
            if ($imgPath && file_exists($imgPath)) {
                $size = @getimagesize($imgPath);
                if ($size && $size[0] > $size[1]) {
                    $cols = 2; // landscape: 2 por linha
                }
            }
            $tdWidth = floor(100 / $cols);
        ?>
        <tr>
            <?php for ($j = 0; $j < $cols && $idx < $totalFotos; $j++, $idx++): ?>
            <td style="width:<?= $tdWidth ?>%;">
                <img src="<?= $fotosArray[$idx]['img_path'] ?>" class="foto-img" alt="Foto">
                <div class="foto-legenda"><?= htmlspecialchars($fotosArray[$idx]['name'] ?? '') ?></div>
            </td>
            <?php endfor; ?>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php endif; ?>
    <?php } ?>

    <!-- Secao Saida -->
    <?php if (!empty($questoesSaida) || !empty($checklist['obs']) || !empty($vistoriaSaida)): ?>
    <?php renderSecao(
        t('modules.checklists.print.departure'),
        $checklist['data_saida'] ?? null,
        $questoesSaida,
        $checklist['obs'] ?? null,
        $vistoriaSaida
    ); ?>
    <?php endif; ?>

    <!-- Secao Chegada -->
    <?php if (!empty($questoesChegada) || !empty($checklist['obs_chegada']) || !empty($vistoriaChegada)): ?>
    <?php renderSecao(
        t('modules.checklists.print.arrival'),
        $checklist['data_chegada'] ?? null,
        $questoesChegada,
        $checklist['obs_chegada'] ?? null,
        $vistoriaChegada
    ); ?>
    <?php endif; ?>

    <!-- Ativar footer de assinaturas na ultima pagina -->
    <?php
    $isAvulso = ($checklist['tipo'] ?? '') === 'A';
    $temAssinaturaSaida = !empty($assinaturaPath);
    $temAssinaturaChegada = !empty($assinaturaChegadaPath) && !$isAvulso;
    ?>
    <?php if ($temAssinaturaSaida || $temAssinaturaChegada): ?>
    <sethtmlpagefooter name="assinaturas" value="on" />
    <?php endif; ?>
</body>
</html>
