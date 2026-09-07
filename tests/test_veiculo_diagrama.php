<?php
/** php tests/test_veiculo_diagrama.php — catálogo, legado e rejeição de caminhos. */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\VeiculoDiagramaHelper as Diagrama;

function verificar(bool $ok, string $mensagem): void {
    if (!$ok) throw new RuntimeException($mensagem);
}
$root = dirname(__DIR__);
$assets = array_map('basename', glob($root . '/public/assets/img/diagramas/*.jpg'));
$catalogo = array_keys(Diagrama::ARQUIVOS);
sort($assets); sort($catalogo);
verificar($assets === $catalogo, 'O catálogo deve incluir todos os diagramas existentes.');
foreach (Diagrama::ARQUIVOS as $arquivo => $chave) {
    verificar(strlen($arquivo) <= 25, 'Nome excede o limite do schema.');
    verificar(Diagrama::normalizar(strtoupper($arquivo)) === $arquivo, 'Nome legado não resolvido.');
    foreach (['pt_BR', 'pt_PT', 'en_US', 'es_ES', 'it_IT'] as $locale) {
        $traducoes = require "$root/app/Lang/$locale/modules/veiculos.php";
        verificar(!empty($traducoes['diagram']['options'][$chave]), "Tradução ausente: $locale/$chave");
    }
}
verificar(Diagrama::normalizar('Sedan.jpg') === 'sedan.jpg', 'Sedan legado');
verificar(Diagrama::normalizar('SUV.jpg') === 'suv.jpg', 'SUV legado');
foreach ([null, [], 1, '', '../sedan.jpg', '/sedan.jpg', 'https://example.com/sedan.jpg', 'sedan.jpg?x=1', 'inexistente.jpg'] as $valor) {
    verificar(Diagrama::normalizar($valor) === null, 'Valor inválido aceito');
}
echo "OK: catálogo completo, 5 idiomas, compatibilidade legada e entradas inválidas.\n";
