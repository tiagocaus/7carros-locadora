<?php
/**
 * Teste: fluxo de pre-cadastro, upload de documentos e templates de reserva.
 *
 * Valida:
 *  1. Flags novas estao na tabela site_config
 *  2. Tabela clientes_arquivos suporta documentos do site
 *  3. Templates pedido_reserva e confirmacao_reserva estao cadastrados
 *  4. Permissao locacoes.confirmar existe
 *  5. Cliente::inserirArquivo aceita status aguardando
 *
 * Execute: php tests/test_precadastro_documentos.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/app/Helpers/helpers.php';

ini_set('session.save_path', sys_get_temp_dir());
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

// 2. Tabela clientes_arquivos
echo "2. Tabela clientes_arquivos suporta documentos do site\n";
$colsArquivos = Database::fetchAll("
    SELECT column_name FROM information_schema.columns
    WHERE table_schema=DATABASE() AND table_name='clientes_arquivos'
      AND column_name IN ('chave','id_cliente','nome','arquivo','tipo','status','created_at')
");
check('schema tem as 7 colunas esperadas', count($colsArquivos), 7);
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

// 5. Cliente::inserirArquivo com status aguardando (limpa ao final)
echo "5. Cliente::inserirArquivo com status aguardando\n";
$clienteExistente = Database::fetchAll(
    "SELECT id FROM clientes WHERE chave = ? ORDER BY id DESC LIMIT 1",
    [$CHAVE_TESTE]
);
if (empty($clienteExistente)) {
    echo "   (pulando — nenhum cliente no tenant de teste)\n";
} else {
    $clienteId = (int) $clienteExistente[0]['id'];
    $model = new \App\Models\Cliente();
    $arquivoId = null;
    try {
        $arquivoId = $model->inserirArquivo($clienteId, [
            'nome' => 'CNH_site_teste.jpg',
            'arquivo' => 'cnh_site_teste_nao_fisico.jpg',
            'tipo' => 1,
        ], null);
        $arquivo = $model->buscarArquivo($arquivoId);
        check('arquivo inserido para o cliente correto', (int) ($arquivo['id_cliente'] ?? 0), $clienteId);
        check('tipo CNH persistido', (int) ($arquivo['tipo'] ?? 0), 1);
        check(
            'status fica aguardando',
            array_key_exists('status', $arquivo ?? []) ? $arquivo['status'] : 'campo-ausente',
            null
        );
    } finally {
        if ($arquivoId !== null) {
            $model->excluirArquivoPorId($arquivoId);
        }
    }
}

echo "\n=== RESUMO ===\n";
echo "Sucessos: {$sucessos}\n";
echo "Falhas:   {$falhas}\n";
exit($falhas > 0 ? 1 : 0);
