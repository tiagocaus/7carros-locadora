<?php
/**
 * Partial: Conteudo do documento customizado
 *
 * Variaveis esperadas do controller:
 *   $documentoTexto, $empresa, $contrato, $assinatura, $logoPath, $qrPath
 */
?>

<?php if (!empty($documentoTexto['texto'])): ?>
    <?= $documentoTexto['texto'] ?>
<?php else: ?>
    <p style="text-align:center; color:#999; padding:40px;"><?= t('modules.contratos.pdf.no_document_selected') ?></p>
<?php endif; ?>
