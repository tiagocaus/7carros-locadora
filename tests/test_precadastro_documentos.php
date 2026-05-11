<?php
/**
 * Teste: fluxo de pre-cadastro, upload de documentos e templates de reserva.
 *
 * Valida:
 *  1. Flags novas estao na tabela site_config
 *  2. Tabela locacoes_documentos existe
 *  3. Templates pedido_reserva e confirmacao_reserva estao cadastrados
 *  4. Permissao locacoes.confirmar existe
 *  5. LocacaoDocumento::upsert e listarPorLocacao funcionam
 *
 * Execute: php tests/test_precadastro_documentos.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/app/Helpers/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

use App\Core\Database;

$CHAVE_TESTE = '1111111111111';
$_SESSION['chave'] = $CHAVE_TESTE;

$falhas = 0;
$sucessos = 0;

function check(string $label, $atual, $esperado): bool {
    global $falhas, $sucessos;
    $ok = ($atual === $esperado);
    echo "   " . ($ok ? '✓ PASS' : '✗ FAIL') . " {$label} — esperado=" . var_export($esperado, true) . ", atual=" . var_export($atual, true) . "\n";
    if ($ok) $sucessos++; else $falhas++;
    return $ok;
}

echo "=== Teste pre-cadastro / documentos / templates ===\n\n";

// 1. Flags em site_config
echo "1. Colunas novas em site_config\n";
$cols = Database::fetchAll("
    SELECT column_name FROM information_schema.columns
    WHERE table_schema=DATABASE() AND table_name='site_config'
      AND column_name IN ('cadastro_simples','envio_documentos','doc_cnh_obrigatorio','doc_cpf_obrigatorio','doc_rg_obrigatorio','doc_comprovante_obrigatorio','reserva_requer_confirmacao')
");
check('as 7 flags presentes', count($cols), 7);
echo "\n";

// 2. Tabela locacoes_documentos
echo "2. Tabela locacoes_documentos existe\n";
$tbl = Database::fetchAll("SHOW TABLES LIKE 'locacoes_documentos'");
check('tabela existe', count($tbl), 1);
$colsLD = Database::fetchAll("
    SELECT column_name FROM information_schema.columns
    WHERE table_schema=DATABASE() AND table_name='locacoes_documentos'
");
check('schema tem colunas esperadas (>=6)', count($colsLD) >= 6, true);
echo "\n";

// 3. Templates pedido_reserva e confirmacao_reserva
echo "3. Templates cadastrados\n";
$types = Database::fetchAll("
    SELECT slug, category, channels FROM message_template_types
    WHERE slug IN ('pedido_reserva','confirmacao_reserva')
");
check('2 tipos cadastrados', count($types), 2);
$slugs = array_column($types, 'slug');
check("tipo pedido_reserva presente", in_array('pedido_reserva', $slugs, true), true);
check("tipo confirmacao_reserva presente", in_array('confirmacao_reserva', $slugs, true), true);

$templates = Database::fetchAll("
    SELECT mtt.slug, mt.channel, LENGTH(mt.content) AS tam
    FROM message_templates mt
    JOIN message_template_types mtt ON mtt.id = mt.template_type_id
    WHERE mtt.slug IN ('pedido_reserva','confirmacao_reserva')
      AND mt.chave = '0' AND mt.locale = 'pt_BR'
");
$chans = [];
foreach ($templates as $t) { $chans[$t['slug']][$t['channel']] = (int) $t['tam']; }
check("pedido_reserva email presente", !empty($chans['pedido_reserva']['email']), true);
check("pedido_reserva whatsapp presente", !empty($chans['pedido_reserva']['whatsapp']), true);
check("confirmacao_reserva email presente", !empty($chans['confirmacao_reserva']['email']), true);
check("confirmacao_reserva whatsapp presente", !empty($chans['confirmacao_reserva']['whatsapp']), true);
check("confirmacao_reserva sms presente", !empty($chans['confirmacao_reserva']['sms']), true);
echo "\n";

// 4. Permissao locacoes.confirmar
echo "4. Permissao locacoes.confirmar\n";
$perm = Database::fetchAll("SELECT id FROM permissions WHERE `key` = 'locacoes.confirmar'");
check('permissao cadastrada', count($perm), 1);
echo "\n";

// 5. LocacaoDocumento — upsert + listar (usa uma locacao existente do tenant; limpa ao final)
echo "5. LocacaoDocumento::upsert + listarPorLocacao\n";
$locExistente = Database::fetchAll(
    "SELECT id FROM locacoes WHERE chave = ? ORDER BY id DESC LIMIT 1",
    [$CHAVE_TESTE]
);
if (empty($locExistente)) {
    echo "   (pulando — nenhuma locacao no tenant de teste)\n";
} else {
    $locacaoId = (int) $locExistente[0]['id'];
    $model = new \App\Models\LocacaoDocumento();
    try {
        $model->upsert($locacaoId, 'cnh', 'cnh_fake_001.jpg');
        $model->upsert($locacaoId, 'cpf', 'cpf_fake_001.jpg');
        $list = $model->listarPorLocacao($locacaoId);
        check('upsert + listar retorna 2 docs', count($list), 2);

        // upsert do mesmo tipo substitui (nao duplica)
        $model->upsert($locacaoId, 'cnh', 'cnh_fake_002.jpg');
        $list2 = $model->listarPorLocacao($locacaoId);
        check('upsert substitui mesmo tipo (nao cria duplicata)', count($list2), 2);
        $cnh = array_values(array_filter($list2, fn($d) => $d['tipo'] === 'cnh'))[0] ?? null;
        check('upsert atualizou arquivo', $cnh['arquivo'] ?? null, 'cnh_fake_002.jpg');
    } finally {
        // Cleanup
        foreach (\App\Models\LocacaoDocumento::TIPOS as $tipo) {
            $r = $model->buscarPorLocacaoTipo($locacaoId, $tipo);
            if ($r && str_contains((string) $r['arquivo'], '_fake_')) {
                $model->excluir((int) $r['id']);
            }
        }
    }
}

echo "\n=== RESUMO ===\n";
echo "Sucessos: {$sucessos}\n";
echo "Falhas:   {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
