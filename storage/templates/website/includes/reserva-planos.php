<?php
/** HTML inicial compartilhado pelo editor do painel e pela reserva pública. */
function websitePlanosInformacoesPadrao(array $traducoes): string
{
    $reserva = $traducoes['reserva'] ?? [];
    $paragrafos = [];
    foreach (['livre', 'controlado', 'pago'] as $plano) {
        $chave = 'plano_km_' . $plano;
        $titulo = htmlspecialchars($reserva[$chave] ?? '', ENT_QUOTES, 'UTF-8');
        $descricao = htmlspecialchars($reserva[$chave . '_desc'] ?? '', ENT_QUOTES, 'UTF-8');
        $paragrafos[] = '<p><strong>' . $titulo . ':</strong> ' . $descricao . '</p>';
    }
    return implode('', $paragrafos);
}
