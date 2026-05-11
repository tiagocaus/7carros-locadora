<?php
/**
 * JSON-LD Schema.org (CarRental, LocalBusiness)
 * Variaveis esperadas: $config, $dados
 */
$empresa = $dados['empresa'] ?? [];
$filiais = $dados['filiais'] ?? [];

$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'CarRental',
    'name' => $config['nome_empresa'],
    'url' => 'https://' . $config['dominio'],
];

if (!empty($config['logo_url'])) {
    $schema['logo'] = $config['logo_url'];
}

if (!empty($empresa['telefone'])) {
    $schema['telephone'] = $empresa['telefone'];
}

if (!empty($empresa['endereco'])) {
    $schema['address'] = [
        '@type' => 'PostalAddress',
        'streetAddress' => $empresa['endereco'] ?? '',
        'addressLocality' => $empresa['cidade'] ?? '',
        'addressRegion' => $empresa['estado'] ?? '',
        'addressCountry' => $empresa['pais'] ?? 'BR',
    ];
}

if (!empty($filiais)) {
    $schema['openingHoursSpecification'] = [];
    foreach ($filiais as $filial) {
        if (!empty($filial['horarios'])) {
            foreach ($filial['horarios'] as $h) {
                $schema['openingHoursSpecification'][] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $h['dia'] ?? '',
                    'opens' => $h['abre'] ?? '',
                    'closes' => $h['fecha'] ?? '',
                ];
            }
        }
    }
}

$schema['priceRange'] = '$$';
?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
