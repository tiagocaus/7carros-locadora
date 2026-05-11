<?php
/**
 * Partial: Conteudo do documento customizado para locacao
 *
 * Variaveis esperadas do controller:
 *   $documentoTexto, $empresa, $locacao, $assinatura, $logoPath, $qrPath
 */
?>

<?php if (!empty($documentoTexto['texto'])): ?>
    <?= $documentoTexto['texto'] ?>
<?php else: ?>
    <p style="text-align:center; color:#999; padding:40px;"><?= t('modules.locacoes.pdf.no_document_selected') ?></p>
<?php endif; ?>
