<?php
/**
 * Widget flutuante WhatsApp.
 * Variáveis esperadas (vêm do functions.php via /api/public/status runtime):
 * - $whatsappNumero   (string) — número com DDI
 * - $whatsappMensagem (string) — mensagem padrão opcional
 */
if (empty($whatsappNumero)) return;

$phone = preg_replace('/[^0-9]/', '', $whatsappNumero);
$msg = urlencode($whatsappMensagem ?? '');
$url = "https://api.whatsapp.com/send?phone={$phone}&text={$msg}";
?>
<a href="<?= $url ?>" target="_blank" class="whatsapp-float" aria-label="<?= t('whatsapp.mensagem_padrao') ?>"
   data-track="whatsapp_flutuante"
   data-gtm-category="contato" data-gtm-action="click" data-gtm-label="whatsapp-flutuante">
    <i class="fa fa-whatsapp"></i>
</a>
