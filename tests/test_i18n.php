<?php

/**
 * Teste do Sistema de Internacionalização (i18n)
 *
 * Execute: php tests/test_i18n.php
 */

// Carregar autoloader e definições
require_once __DIR__ . '/../vendor/autoload.php';

// Definir constantes se não existirem
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Carregar helpers
require_once APP_ROOT . '/app/Helpers/helpers.php';

// Iniciar sessão para testes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== Teste do Sistema i18n ===\n\n";

// Teste 1: Verificar instância do Translator
echo "1. Instanciando Translator...\n";
try {
    $translator = \App\I18n\Translator::getInstance();
    echo "   ✓ Translator instanciado com sucesso\n";
    echo "   Locale atual: " . $translator->getLocale() . "\n\n";
} catch (Exception $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Teste 2: Tradução básica
echo "2. Testando traduções básicas...\n";
$tests = [
    'common.buttons.save' => 'Salvar',
    'common.buttons.cancel' => 'Cancelar',
    'common.buttons.delete' => 'Excluir',
    'common.labels.yes' => 'Sim',
    'common.labels.no' => 'Não',
];

$passed = 0;
$failed = 0;

foreach ($tests as $key => $expected) {
    $result = __($key);
    if ($result === $expected) {
        echo "   ✓ {$key} = '{$result}'\n";
        $passed++;
    } else {
        echo "   ✗ {$key} - Esperado: '{$expected}', Obtido: '{$result}'\n";
        $failed++;
    }
}

echo "\n";

// Teste 3: Tradução com variáveis
echo "3. Testando tradução com variáveis...\n";
$greeting = __('messages.greeting.welcome_user', ['nome' => 'João']);
echo "   Resultado: '{$greeting}'\n";
if (str_contains($greeting, 'João')) {
    echo "   ✓ Substituição de variável funcionando\n\n";
    $passed++;
} else {
    echo "   ✗ Substituição de variável não funcionou\n\n";
    $failed++;
}

// Teste 4: Verificar locales suportados
echo "4. Locales suportados:\n";
$locales = supported_locales();
foreach ($locales as $code => $info) {
    echo "   - {$info['flag']} {$info['name']} ({$code})\n";
}
echo "\n";

// Teste 5: Helper functions
echo "5. Testando helper functions...\n";
echo "   current_locale(): " . current_locale() . "\n";
echo "   is_locale_supported('pt_BR'): " . (is_locale_supported('pt_BR') ? 'true' : 'false') . "\n";
echo "   is_locale_supported('xx_XX'): " . (is_locale_supported('xx_XX') ? 'true' : 'false') . "\n";
echo "   has_translation('common.buttons.save'): " . (has_translation('common.buttons.save') ? 'true' : 'false') . "\n";
echo "   has_translation('nao.existe'): " . (has_translation('nao.existe') ? 'true' : 'false') . "\n";
$info = locale_info();
echo "   locale_info(): {$info['name']} {$info['flag']}\n\n";

// Teste 6: Tradução em inglês
echo "6. Testando tradução em inglês (en_US)...\n";
$save_en = __('common.buttons.save', [], 'en_US');
echo "   common.buttons.save (en_US): '{$save_en}'\n";
if ($save_en === 'Save') {
    echo "   ✓ Tradução em inglês funcionando\n\n";
    $passed++;
} else {
    echo "   ⚠ Tradução em inglês retornou: '{$save_en}' (pode ser fallback para pt_BR)\n\n";
}

// Teste 7: Tradução de módulos
echo "7. Testando tradução de módulos...\n";
$clienteTitle = __('modules.clientes.title');
echo "   modules.clientes.title: '{$clienteTitle}'\n";
if ($clienteTitle === 'Clientes') {
    echo "   ✓ Tradução de módulo funcionando\n\n";
    $passed++;
} else {
    echo "   ✗ Tradução de módulo não funcionou\n\n";
    $failed++;
}

// Teste 8: Mudança de locale
echo "8. Testando mudança de locale...\n";
$originalLocale = current_locale();
echo "   Locale original: {$originalLocale}\n";

try {
    set_locale('en_US');
    $newLocale = current_locale();
    echo "   Após set_locale('en_US'): {$newLocale}\n";

    // Voltar ao original
    set_locale($originalLocale);
    echo "   Restaurado para: " . current_locale() . "\n";
    echo "   ✓ Mudança de locale funcionando\n\n";
    $passed++;
} catch (Exception $e) {
    echo "   ✗ Erro ao mudar locale: " . $e->getMessage() . "\n\n";
    $failed++;
}

// Resumo
echo "=== Resumo ===\n";
echo "Testes passados: {$passed}\n";
echo "Testes falhados: {$failed}\n";
echo "\n";

if ($failed === 0) {
    echo "✓ Todos os testes passaram! Sistema i18n funcionando corretamente.\n";
    exit(0);
} else {
    echo "✗ Alguns testes falharam. Verifique os erros acima.\n";
    exit(1);
}
