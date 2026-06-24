<?php
/**
 * Partial: Conteudo completo do checklist de locacao
 *
 * Variaveis esperadas do controller:
 *   $locacao, $empresa, $veiculo, $assinatura, $checklistData, $checklistDigital,
 *   $diagramaPath, $checklistModeloQuestoes, $logoPath, $qrPath
 *
 * Flag de controle (definida pelo arquivo principal antes do include):
 *   $_checklistShowClienteData (bool) - true: mostra secao "Dados do Cliente"
 */
$_checklistShowClienteData = $_checklistShowClienteData ?? false;
$_checklistStandalone = $_checklistStandalone ?? false;
$checklistModeloQuestoes = $checklistModeloQuestoes ?? [];
?>

<!-- HEADER -->
<?php
    $_docTitulo = (!empty($checklistDigital) && !empty($checklistData)) ? t('modules.locacoes.pdf.checklist_digital_title') : t('modules.locacoes.pdf.checklist_vehicle_title');
    $_showQrCode = !empty($checklistDigital) && !empty($checklistData);
?>
<?php include __DIR__ . '/_header.php'; ?>

<?php if (!empty($checklistDigital) && !empty($checklistData)): ?>
    <!-- MODO DIGITAL (P3/P4) -->
    <?php
        $checklist = $checklistData['checklist'];
        $questoesSaida = $checklistData['questoesSaida'];
        $questoesChegada = $checklistData['questoesChegada'];
        $vistoriaSaida = $checklistData['vistoriaSaida'];
        $vistoriaChegada = $checklistData['vistoriaChegada'];

        if (!function_exists('renderRespostaBadge')) {
            function renderRespostaBadge(string $opt): string {
                switch ($opt) {
                    case '1': return '<span class="badge badge-confere">' . t('modules.locacoes.pdf.badge_ok') . '</span>';
                    case '2': return '<span class="badge badge-nao-confere">' . t('modules.locacoes.pdf.badge_not_ok') . '</span>';
                    case '3': return '<span class="badge badge-danificado">' . t('modules.locacoes.pdf.badge_damaged') . '</span>';
                    case '4': return '<span class="badge badge-na">' . t('modules.locacoes.pdf.badge_na') . '</span>';
                    default:  return '<span class="badge badge-na">-</span>';
                }
            }
        }

        $fotosSaida = array_values(array_filter($vistoriaSaida, fn($item) => !empty($item['img_path'])));
        $fotosChegada = array_values(array_filter($vistoriaChegada, fn($item) => !empty($item['img_path'])));
        $maxFotos = max(count($fotosSaida), count($fotosChegada));

        $tdLeft  = 'vertical-align: top; padding: 0 8px 8px 0;';
        $tdGap   = 'width: 2%;';
        $tdRight = 'vertical-align: top; padding: 0 0 8px 8px;';
    ?>

    <?php if ($_checklistShowClienteData): ?>
    <div class="section">
        <div class="section-title"><?= t('modules.locacoes.pdf.client_data') ?></div>
        <table class="veiculo-table">
            <tr>
                <td class="veiculo-label"><?= t('modules.locacoes.pdf.client_label') ?></td>
                <td class="veiculo-value"><?= htmlspecialchars($locacao['cliente_nome_completo'] ?? 'N/A') ?></td>
                <td class="veiculo-label"><?= t('modules.locacoes.pdf.cpf_cnpj_label') ?></td>
                <td class="veiculo-value"><?= htmlspecialchars($locacao['cliente_cpf_cnpj'] ?? 'N/A') ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Veiculo -->
    <?php if (!empty($checklist['placa'])): ?>
    <table class="veiculo-table">
        <tr>
            <td class="veiculo-label"><?= t('modules.locacoes.pdf.plate_field') ?></td>
            <td class="veiculo-value"><?= htmlspecialchars($checklist['placa'] ?? '-') ?></td>
            <td class="veiculo-label"><?= t('modules.locacoes.pdf.vehicle_field') ?>:</td>
            <td class="veiculo-value"><?= htmlspecialchars(($checklist['marca'] ?? '') . ' ' . ($checklist['veiculo_modelo'] ?? '')) ?></td>
            <td class="veiculo-label"><?= t('modules.locacoes.pdf.renavam_field') ?></td>
            <td class="veiculo-value"><?= htmlspecialchars($checklist['renavam'] ?? '-') ?></td>
        </tr>
    </table>
    <?php endif; ?>

    <!-- Layout lado a lado: SAIDA | CHEGADA -->
    <table style="width: 100%; border-collapse: collapse;">

        <!-- Titulos -->
        <tr>
            <td style="width: 49%; vertical-align: top; padding: 15px 0 8px 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td class="section-title" style="background: #e8e8e8;">
                            <?= t('modules.locacoes.pdf.departure') ?><?= !empty($checklist['data_saida']) ? ' - ' . date('d/m/Y H:i', strtotime($checklist['data_saida'])) : '' ?>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="<?= $tdGap ?>"></td>
            <td style="width: 49%; vertical-align: top; padding: 15px 0 8px 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td class="section-title" style="background: #e8e8e8;">
                            <?= t('modules.locacoes.pdf.arrival') ?><?= !empty($checklist['data_chegada']) ? ' - ' . date('d/m/Y H:i', strtotime($checklist['data_chegada'])) : '' ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Questionario -->
        <?php if (!empty($questoesSaida) || !empty($questoesChegada)): ?>
        <tr>
            <td style="<?= $tdLeft ?>">
                <?php if (!empty($questoesSaida)): ?>
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.locacoes.pdf.questionnaire') ?></div>
                <table class="questoes-table">
                    <tr>
                        <th style="width: 8%;"><?= t('modules.locacoes.pdf.item_num') ?></th>
                        <th style="width: 62%;"><?= t('modules.locacoes.pdf.item_label') ?></th>
                        <th style="width: 30%;"><?= t('modules.locacoes.pdf.answer_label') ?></th>
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
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.locacoes.pdf.questionnaire') ?></div>
                <table class="questoes-table">
                    <tr>
                        <th style="width: 8%;"><?= t('modules.locacoes.pdf.item_num') ?></th>
                        <th style="width: 62%;"><?= t('modules.locacoes.pdf.item_label') ?></th>
                        <th style="width: 30%;"><?= t('modules.locacoes.pdf.answer_label') ?></th>
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
                <div style="text-align: center; color: #999; padding: 20px; font-style: italic; font-size: 9pt;"><?= t('modules.locacoes.pdf.no_arrival_data') ?></div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>

        <!-- Vistoria (Fotos) -->
        <?php if ($maxFotos > 0): ?>
        <tr>
            <td style="<?= $tdLeft ?>">
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.locacoes.pdf.survey_photos') ?></div>
            </td>
            <td style="<?= $tdGap ?>"></td>
            <td style="<?= $tdRight ?>">
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.locacoes.pdf.survey_photos') ?></div>
            </td>
        </tr>
        <?php for ($i = 0; $i < $maxFotos; $i++): ?>
        <tr>
            <td style="<?= $tdLeft ?> text-align: center;">
                <?php if (isset($fotosSaida[$i])): ?>
                    <img src="<?= $fotosSaida[$i]['img_path'] ?>" class="foto-img" alt="<?= t('modules.locacoes.pdf.photo_alt') ?>">
                    <div class="foto-legenda"><?= htmlspecialchars($fotosSaida[$i]['content'] ?? '') ?></div>
                <?php endif; ?>
            </td>
            <td style="<?= $tdGap ?>"></td>
            <td style="<?= $tdRight ?> text-align: center;">
                <?php if (isset($fotosChegada[$i])): ?>
                    <img src="<?= $fotosChegada[$i]['img_path'] ?>" class="foto-img" alt="<?= t('modules.locacoes.pdf.photo_alt') ?>">
                    <div class="foto-legenda"><?= htmlspecialchars($fotosChegada[$i]['content'] ?? '') ?></div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endfor; ?>
        <?php endif; ?>

        <!-- Observacoes -->
        <?php if (!empty($checklist['obs']) || !empty($checklist['obs_chegada'])): ?>
        <tr>
            <td style="<?= $tdLeft ?>">
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.locacoes.pdf.observations_section') ?></div>
                <div class="obs-box"><?= nl2br(htmlspecialchars($checklist['obs'] ?? '')) ?></div>
            </td>
            <td style="<?= $tdGap ?>"></td>
            <td style="<?= $tdRight ?>">
                <div style="font-size: 9pt; font-weight: bold; margin-top: 8px; margin-bottom: 4px;"><?= t('modules.locacoes.pdf.observations_section') ?></div>
                <div class="obs-box"><?= nl2br(htmlspecialchars($checklist['obs_chegada'] ?? '')) ?></div>
            </td>
        </tr>
        <?php endif; ?>

    </table>

<?php else: ?>
    <!-- MODO IMPRESSO (layout legado) -->
    <?php
        $veiculoInfo = '';
        if (!empty($veiculo)) {
            $veiculoInfo = htmlspecialchars(($veiculo['placa'] ?? $locacao['veiculo_placa'] ?? '-') . ' - ' . ($veiculo['ano'] ?? '-') . ' - ' . ($veiculo['cor'] ?? '-'));
        }
        $isElectric = !empty($veiculo['tipo_combustivel']) && $veiculo['tipo_combustivel'] === 'HE';
        $niveisLabel = $isElectric
            ? ['0%', '12%', '25%', '37%', '50%', '62%', '75%', '87%', '100%']
            : ['V', '1/8', '1/4', '3/8', '1/2', '5/8', '3/4', '7/8', 'C'];
        $nivelSaida = !empty($locacao['combustivel_ini']) ? (int) $locacao['combustivel_ini'] : -1;
    ?>

    <!-- LOCATARIO -->
    <table class="locatario-table">
        <thead>
            <tr>
                <th colspan="4"><?= t('modules.locacoes.pdf.tenant') ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width: 10%;"><strong><?= t('modules.locacoes.pdf.name_field') ?></strong></td>
                <td style="width: 10%;"><strong><?= t('modules.locacoes.pdf.cpf_cnpj_field') ?></strong></td>
                <td style="width: 10%;"><strong><?= t('modules.locacoes.pdf.phone_field') ?></strong></td>
                <td style="width: 10%;"><strong><?= t('modules.locacoes.pdf.vehicle_field') ?></strong></td>
            </tr>
            <tr>
                <td><?= htmlspecialchars($locacao['cliente_nome_completo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($locacao['cliente_cpf_cnpj'] ?? '-') ?></td>
                <td><?= htmlspecialchars($locacao['cliente_telefone'] ?? '-') ?></td>
                <td><?= $veiculoInfo ?: '-' ?></td>
            </tr>
        </tbody>
    </table>

    <!-- TRES COLUNAS: SAIDA | LEGENDA | CHEGADA -->
    <table cellpadding="0" cellspacing="0" style="width: 100%;">
        <tr>
            <!-- COLUNA SAIDA -->
            <td style="width: 42%; vertical-align: top; padding-right: 5px;">
                <div class="coluna-titulo"><?= t('modules.locacoes.pdf.checklist_departure') ?></div>
                <div class="campo-manual"><?= t('modules.locacoes.pdf.date_field') ?> ____/____/______ &nbsp; <?= t('modules.locacoes.pdf.time_field') ?> ____:____</div>
                <div class="campo-manual"><?= t('modules.locacoes.pdf.mileage_field') ?> _________________</div>

                <?php if (!empty($diagramaPath)): ?>
                <div class="diagrama-coluna">
                    <img src="<?= htmlspecialchars($diagramaPath) ?>" style="max-width: 100%; max-height: 420px; width: auto; height: auto;" alt="<?= t('modules.locacoes.pdf.vehicle_diagram_alt') ?>">
                </div>
                <?php endif; ?>

                <!-- TANQUE SAIDA -->
                <table class="tanque-table">
                    <thead>
                        <tr><th colspan="9"><?= $isElectric ? t('modules.locacoes.pdf.battery_tank') : t('modules.locacoes.pdf.fuel_tank') ?></th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($niveisLabel as $idx => $label): ?>
                            <?php $nivelIdx = 8 - $idx; ?>
                            <td class="<?= ($nivelSaida >= 0 && $nivelIdx <= $nivelSaida) ? 'filled' : '' ?>"><?= $label ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </td>

            <!-- LEGENDA DE AVARIAS (centro) -->
            <td style="width: 16%; vertical-align: middle; padding: 0 8px;">
                <div class="legenda-avarias">
                    <strong><?= t('modules.locacoes.pdf.damage_legend') ?></strong>
                    <p><strong>A</strong> = <?= t('modules.locacoes.pdf.damage_a') ?></p>
                    <p><strong>P</strong> = <?= t('modules.locacoes.pdf.damage_p') ?></p>
                    <p><strong>Q</strong> = <?= t('modules.locacoes.pdf.damage_q') ?></p>
                    <p><strong>R</strong> = <?= t('modules.locacoes.pdf.damage_r') ?></p>
                    <p><strong>T</strong> = <?= t('modules.locacoes.pdf.damage_t') ?></p>
                    <p><strong>F</strong> = <?= t('modules.locacoes.pdf.damage_f') ?></p>
                </div>
            </td>

            <!-- COLUNA CHEGADA -->
            <td style="width: 42%; vertical-align: top; padding-left: 5px;">
                <div class="coluna-titulo"><?= t('modules.locacoes.pdf.checklist_arrival') ?></div>
                <div class="campo-manual"><?= t('modules.locacoes.pdf.date_field') ?> ____/____/______ &nbsp; <?= t('modules.locacoes.pdf.time_field') ?> ____:____</div>
                <div class="campo-manual"><?= t('modules.locacoes.pdf.mileage_field') ?> _________________</div>

                <?php if (!empty($diagramaPath)): ?>
                <div class="diagrama-coluna">
                    <img src="<?= htmlspecialchars($diagramaPath) ?>" style="max-width: 100%; max-height: 420px; width: auto; height: auto;" alt="<?= t('modules.locacoes.pdf.vehicle_diagram_alt') ?>">
                </div>
                <?php endif; ?>

                <!-- TANQUE CHEGADA (vazio para preenchimento) -->
                <table class="tanque-table">
                    <thead>
                        <tr><th colspan="9"><?= $isElectric ? t('modules.locacoes.pdf.battery_tank') : t('modules.locacoes.pdf.fuel_tank') ?></th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($niveisLabel as $label): ?>
                            <td><?= $label ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- QUESTIONARIO -->
    <?php if (!empty($checklistModeloQuestoes)): ?>
    <table class="questionario-table">
        <thead>
            <tr>
                <th colspan="4"><?= t('modules.locacoes.pdf.questionnaire_title') ?></th>
            </tr>
            <tr>
                <th style="width: 40%;"><?= t('modules.locacoes.pdf.items_header') ?></th>
                <th style="width: 22%;"><?= t('modules.locacoes.pdf.status_departure') ?></th>
                <th style="width: 22%;"><?= t('modules.locacoes.pdf.status_arrival') ?></th>
                <th style="width: 16%;"><?= t('modules.locacoes.pdf.value_header') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($checklistModeloQuestoes as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['content'] ?? $item['name'] ?? '-') ?></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="text-align: center; color: #999; padding: 20px; font-size: 9pt;"><?= t('modules.locacoes.pdf.no_checklist_model') ?></p>
    <?php endif; ?>

<?php endif; ?>

<!-- ASSINATURAS -->
<?php if (!empty($_pdfFooterFixo)): ?>
    <?php /* footer fixo já configurado pelo wrapper */ ?>
<?php elseif ($_checklistStandalone): ?>
    <htmlpagefooter name="assinatura">
        <?php include __DIR__ . '/_footer_assinatura.php'; ?>
    </htmlpagefooter>
    <sethtmlpagefooter name="assinatura" value="on" show-this-page="1" />
<?php else: ?>
    <?php include __DIR__ . '/_footer_assinatura.php'; ?>
<?php endif; ?>
