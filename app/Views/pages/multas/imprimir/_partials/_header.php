<?php
/**
 * Header padrao para impressao de multas.
 *
 * Variaveis esperadas:
 *   $multa, $empresa, $logoPath
 *   $qrPath (opcional) - caminho local do QR code de verificacao; se vazio, celula nao renderiza
 *
 * Flag de controle (definir antes do include):
 *   $_docTitulo (string) - titulo do documento
 */
$_docTitulo = $_docTitulo ?? 'MULTA';
$qrPath = $qrPath ?? '';
?>

<table class="header-table" cellpadding="0" cellspacing="0" style="width: 100%; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px;">
    <tr>
        <td style="width: 18%; vertical-align: middle; padding: 0;">
            <?php if (!empty($logoPath)): ?>
            <img src="<?= $logoPath ?>" class="logo-img" alt="Logo" style="max-height: 70px; max-width: 120px; margin-bottom: 5px;">
            <?php endif; ?>
        </td>
        <td style="width: 42%; vertical-align: top; padding: 0;">
            <div class="empresa-nome" style="font-size: 14pt; font-weight: bold; margin-bottom: 3px;"><?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? 'Locadora') ?></div>
            <div class="empresa-detalhe" style="font-size: 8pt; color: #666;">CNPJ: <?= htmlspecialchars($empresa['cpf_cnpj'] ?? '-') ?></div>
            <?php
                $endereco = trim(($empresa['rua'] ?? '') . ', ' . ($empresa['num'] ?? '') . ' - ' . ($empresa['bairro'] ?? ''), ' -,');
                $cidadeUf = trim(($empresa['cidade'] ?? '') . '/' . ($empresa['estado'] ?? ''), '/');
            ?>
            <?php if ($endereco): ?>
            <div class="empresa-detalhe" style="font-size: 8pt; color: #666;"><?= htmlspecialchars($endereco) ?></div>
            <?php endif; ?>
            <?php if ($cidadeUf): ?>
            <div class="empresa-detalhe" style="font-size: 8pt; color: #666;"><?= htmlspecialchars($cidadeUf) ?><?= !empty($empresa['cep']) ? ' - CEP: ' . htmlspecialchars($empresa['cep']) : '' ?></div>
            <?php endif; ?>
            <?php if (!empty($empresa['celular'])): ?>
            <div class="empresa-detalhe" style="font-size: 8pt; color: #666;">Tel: <?= htmlspecialchars($empresa['celular']) ?></div>
            <?php elseif (!empty($empresa['fixo'])): ?>
            <div class="empresa-detalhe" style="font-size: 8pt; color: #666;">Tel: <?= htmlspecialchars($empresa['fixo']) ?></div>
            <?php endif; ?>
        </td>
        <td style="width: 25%; vertical-align: top; padding: 0;">
            <div class="doc-titulo" style="font-size: 13pt; font-weight: bold; text-align: right; margin-bottom: 5px;"><?= htmlspecialchars($_docTitulo) ?></div>
            <?php if (!empty($multa['n_infracao'])): ?>
            <div class="doc-detalhe" style="font-size: 9pt; text-align: right; color: #555;"><strong><?= t('modules.multas.pdf.fine_number_label') ?></strong> <?= htmlspecialchars($multa['n_infracao']) ?></div>
            <?php endif; ?>
            <?php if (!empty($multa['numero_ait'])): ?>
            <div class="doc-detalhe" style="font-size: 9pt; text-align: right; color: #555;"><strong>AIT:</strong> <?= htmlspecialchars($multa['numero_ait']) ?></div>
            <?php endif; ?>
            <div class="doc-detalhe" style="font-size: 9pt; text-align: right; color: #555;"><strong><?= t('modules.multas.pdf.date_label') ?></strong> <?= date('d/m/Y', strtotime($multa['created_at'] ?? 'now')) ?></div>
        </td>
        <td style="width: 15%; text-align: right; vertical-align: top; padding: 0;">
            <?php if (!empty($qrPath)): ?>
            <img src="<?= $qrPath ?>" class="qr-img" alt="QR Code" style="width: 80px; height: 80px;">
            <?php endif; ?>
        </td>
    </tr>
</table>
