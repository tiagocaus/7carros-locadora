<?php

/**
 * Migration 00267: Criar tabela paises
 *
 * Tabela global (sem chave) com lista padronizada de paises.
 * Usa nome_i18n (JSON) para nomes traduzidos nos 5 idiomas do sistema.
 * codigo = ISO 3166-1 alpha-2 (UNIQUE) usado como valor nos formularios.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('paises')) {
            return;
        }

        $this->create('paises', function ($table) {
            $table->id();
            $table->string('codigo', 2);
            $table->string('codigo_telefone', 10)->nullable();
            $table->json('nome_i18n');
            $table->string('formato_cep', 30)->nullable();
            $table->string('situacao', 1)->default('A');
            $table->timestamps();
            $table->unique('codigo');
            $table->index('situacao');
        });

        $this->populatePaises();
    }

    public function down(): void
    {
        $this->drop('paises');
    }

    private function populatePaises(): void
    {
        $paises = $this->getCountryData();

        foreach ($paises as $country) {
            $this->db()->table('paises')
                ->insert([
                    'codigo' => $country['codigo'],
                    'codigo_telefone' => $country['telefone'],
                    'nome_i18n' => json_encode($country['nomes'], JSON_UNESCAPED_UNICODE),
                    'formato_cep' => $country['formato_cep'],
                    'situacao' => 'A',
                ]);
        }
    }

    private function getCountryData(): array
    {
        return [
            // =============================================
            // AMERICA DO SUL
            // =============================================
            ['codigo' => 'BR', 'telefone' => '+55', 'formato_cep' => '#####-###', 'nomes' => [
                'pt_BR' => 'Brasil', 'en_US' => 'Brazil', 'es_ES' => 'Brasil', 'it_IT' => 'Brasile', 'pt_PT' => 'Brasil',
            ]],
            ['codigo' => 'AR', 'telefone' => '+54', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Argentina', 'en_US' => 'Argentina', 'es_ES' => 'Argentina', 'it_IT' => 'Argentina', 'pt_PT' => 'Argentina',
            ]],
            ['codigo' => 'CL', 'telefone' => '+56', 'formato_cep' => '#######', 'nomes' => [
                'pt_BR' => 'Chile', 'en_US' => 'Chile', 'es_ES' => 'Chile', 'it_IT' => 'Cile', 'pt_PT' => 'Chile',
            ]],
            ['codigo' => 'UY', 'telefone' => '+598', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Uruguai', 'en_US' => 'Uruguay', 'es_ES' => 'Uruguay', 'it_IT' => 'Uruguay', 'pt_PT' => 'Uruguai',
            ]],
            ['codigo' => 'PY', 'telefone' => '+595', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Paraguai', 'en_US' => 'Paraguay', 'es_ES' => 'Paraguay', 'it_IT' => 'Paraguay', 'pt_PT' => 'Paraguai',
            ]],
            ['codigo' => 'CO', 'telefone' => '+57', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Colômbia', 'en_US' => 'Colombia', 'es_ES' => 'Colombia', 'it_IT' => 'Colombia', 'pt_PT' => 'Colômbia',
            ]],
            ['codigo' => 'PE', 'telefone' => '+51', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Peru', 'en_US' => 'Peru', 'es_ES' => 'Perú', 'it_IT' => 'Perù', 'pt_PT' => 'Peru',
            ]],
            ['codigo' => 'VE', 'telefone' => '+58', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Venezuela', 'en_US' => 'Venezuela', 'es_ES' => 'Venezuela', 'it_IT' => 'Venezuela', 'pt_PT' => 'Venezuela',
            ]],
            ['codigo' => 'BO', 'telefone' => '+591', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Bolívia', 'en_US' => 'Bolivia', 'es_ES' => 'Bolivia', 'it_IT' => 'Bolivia', 'pt_PT' => 'Bolívia',
            ]],
            ['codigo' => 'EC', 'telefone' => '+593', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Equador', 'en_US' => 'Ecuador', 'es_ES' => 'Ecuador', 'it_IT' => 'Ecuador', 'pt_PT' => 'Equador',
            ]],
            ['codigo' => 'GY', 'telefone' => '+592', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Guiana', 'en_US' => 'Guyana', 'es_ES' => 'Guyana', 'it_IT' => 'Guyana', 'pt_PT' => 'Guiana',
            ]],
            ['codigo' => 'SR', 'telefone' => '+597', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Suriname', 'en_US' => 'Suriname', 'es_ES' => 'Surinam', 'it_IT' => 'Suriname', 'pt_PT' => 'Suriname',
            ]],
            ['codigo' => 'GF', 'telefone' => '+594', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Guiana Francesa', 'en_US' => 'French Guiana', 'es_ES' => 'Guayana Francesa', 'it_IT' => 'Guyana Francese', 'pt_PT' => 'Guiana Francesa',
            ]],

            // =============================================
            // AMERICA CENTRAL E CARIBE
            // =============================================
            ['codigo' => 'MX', 'telefone' => '+52', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'México', 'en_US' => 'Mexico', 'es_ES' => 'México', 'it_IT' => 'Messico', 'pt_PT' => 'México',
            ]],
            ['codigo' => 'PA', 'telefone' => '+507', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Panamá', 'en_US' => 'Panama', 'es_ES' => 'Panamá', 'it_IT' => 'Panama', 'pt_PT' => 'Panamá',
            ]],
            ['codigo' => 'CR', 'telefone' => '+506', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Costa Rica', 'en_US' => 'Costa Rica', 'es_ES' => 'Costa Rica', 'it_IT' => 'Costa Rica', 'pt_PT' => 'Costa Rica',
            ]],
            ['codigo' => 'CU', 'telefone' => '+53', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Cuba', 'en_US' => 'Cuba', 'es_ES' => 'Cuba', 'it_IT' => 'Cuba', 'pt_PT' => 'Cuba',
            ]],
            ['codigo' => 'DO', 'telefone' => '+1', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'República Dominicana', 'en_US' => 'Dominican Republic', 'es_ES' => 'República Dominicana', 'it_IT' => 'Repubblica Dominicana', 'pt_PT' => 'República Dominicana',
            ]],
            ['codigo' => 'GT', 'telefone' => '+502', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Guatemala', 'en_US' => 'Guatemala', 'es_ES' => 'Guatemala', 'it_IT' => 'Guatemala', 'pt_PT' => 'Guatemala',
            ]],
            ['codigo' => 'HN', 'telefone' => '+504', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Honduras', 'en_US' => 'Honduras', 'es_ES' => 'Honduras', 'it_IT' => 'Honduras', 'pt_PT' => 'Honduras',
            ]],
            ['codigo' => 'NI', 'telefone' => '+505', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Nicarágua', 'en_US' => 'Nicaragua', 'es_ES' => 'Nicaragua', 'it_IT' => 'Nicaragua', 'pt_PT' => 'Nicarágua',
            ]],
            ['codigo' => 'SV', 'telefone' => '+503', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'El Salvador', 'en_US' => 'El Salvador', 'es_ES' => 'El Salvador', 'it_IT' => 'El Salvador', 'pt_PT' => 'El Salvador',
            ]],
            ['codigo' => 'BZ', 'telefone' => '+501', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Belize', 'en_US' => 'Belize', 'es_ES' => 'Belice', 'it_IT' => 'Belize', 'pt_PT' => 'Belize',
            ]],
            ['codigo' => 'JM', 'telefone' => '+1', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Jamaica', 'en_US' => 'Jamaica', 'es_ES' => 'Jamaica', 'it_IT' => 'Giamaica', 'pt_PT' => 'Jamaica',
            ]],
            ['codigo' => 'HT', 'telefone' => '+509', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Haiti', 'en_US' => 'Haiti', 'es_ES' => 'Haití', 'it_IT' => 'Haiti', 'pt_PT' => 'Haiti',
            ]],
            ['codigo' => 'TT', 'telefone' => '+1', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Trinidad e Tobago', 'en_US' => 'Trinidad and Tobago', 'es_ES' => 'Trinidad y Tobago', 'it_IT' => 'Trinidad e Tobago', 'pt_PT' => 'Trindade e Tobago',
            ]],
            ['codigo' => 'BB', 'telefone' => '+1', 'formato_cep' => 'BB#####', 'nomes' => [
                'pt_BR' => 'Barbados', 'en_US' => 'Barbados', 'es_ES' => 'Barbados', 'it_IT' => 'Barbados', 'pt_PT' => 'Barbados',
            ]],
            ['codigo' => 'BS', 'telefone' => '+1', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Bahamas', 'en_US' => 'Bahamas', 'es_ES' => 'Bahamas', 'it_IT' => 'Bahamas', 'pt_PT' => 'Bahamas',
            ]],

            // =============================================
            // AMERICA DO NORTE
            // =============================================
            ['codigo' => 'US', 'telefone' => '+1', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Estados Unidos', 'en_US' => 'United States', 'es_ES' => 'Estados Unidos', 'it_IT' => 'Stati Uniti', 'pt_PT' => 'Estados Unidos',
            ]],
            ['codigo' => 'CA', 'telefone' => '+1', 'formato_cep' => 'A#A #A#', 'nomes' => [
                'pt_BR' => 'Canadá', 'en_US' => 'Canada', 'es_ES' => 'Canadá', 'it_IT' => 'Canada', 'pt_PT' => 'Canadá',
            ]],

            // =============================================
            // EUROPA OCIDENTAL
            // =============================================
            ['codigo' => 'PT', 'telefone' => '+351', 'formato_cep' => '####-###', 'nomes' => [
                'pt_BR' => 'Portugal', 'en_US' => 'Portugal', 'es_ES' => 'Portugal', 'it_IT' => 'Portogallo', 'pt_PT' => 'Portugal',
            ]],
            ['codigo' => 'ES', 'telefone' => '+34', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Espanha', 'en_US' => 'Spain', 'es_ES' => 'España', 'it_IT' => 'Spagna', 'pt_PT' => 'Espanha',
            ]],
            ['codigo' => 'FR', 'telefone' => '+33', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'França', 'en_US' => 'France', 'es_ES' => 'Francia', 'it_IT' => 'Francia', 'pt_PT' => 'França',
            ]],
            ['codigo' => 'IT', 'telefone' => '+39', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Itália', 'en_US' => 'Italy', 'es_ES' => 'Italia', 'it_IT' => 'Italia', 'pt_PT' => 'Itália',
            ]],
            ['codigo' => 'DE', 'telefone' => '+49', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Alemanha', 'en_US' => 'Germany', 'es_ES' => 'Alemania', 'it_IT' => 'Germania', 'pt_PT' => 'Alemanha',
            ]],
            ['codigo' => 'GB', 'telefone' => '+44', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Reino Unido', 'en_US' => 'United Kingdom', 'es_ES' => 'Reino Unido', 'it_IT' => 'Regno Unito', 'pt_PT' => 'Reino Unido',
            ]],
            ['codigo' => 'IE', 'telefone' => '+353', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Irlanda', 'en_US' => 'Ireland', 'es_ES' => 'Irlanda', 'it_IT' => 'Irlanda', 'pt_PT' => 'Irlanda',
            ]],
            ['codigo' => 'NL', 'telefone' => '+31', 'formato_cep' => '#### AA', 'nomes' => [
                'pt_BR' => 'Países Baixos', 'en_US' => 'Netherlands', 'es_ES' => 'Países Bajos', 'it_IT' => 'Paesi Bassi', 'pt_PT' => 'Países Baixos',
            ]],
            ['codigo' => 'BE', 'telefone' => '+32', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Bélgica', 'en_US' => 'Belgium', 'es_ES' => 'Bélgica', 'it_IT' => 'Belgio', 'pt_PT' => 'Bélgica',
            ]],
            ['codigo' => 'LU', 'telefone' => '+352', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Luxemburgo', 'en_US' => 'Luxembourg', 'es_ES' => 'Luxemburgo', 'it_IT' => 'Lussemburgo', 'pt_PT' => 'Luxemburgo',
            ]],
            ['codigo' => 'CH', 'telefone' => '+41', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Suíça', 'en_US' => 'Switzerland', 'es_ES' => 'Suiza', 'it_IT' => 'Svizzera', 'pt_PT' => 'Suíça',
            ]],
            ['codigo' => 'AT', 'telefone' => '+43', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Áustria', 'en_US' => 'Austria', 'es_ES' => 'Austria', 'it_IT' => 'Austria', 'pt_PT' => 'Áustria',
            ]],
            ['codigo' => 'MC', 'telefone' => '+377', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Mônaco', 'en_US' => 'Monaco', 'es_ES' => 'Mónaco', 'it_IT' => 'Monaco', 'pt_PT' => 'Mónaco',
            ]],
            ['codigo' => 'AD', 'telefone' => '+376', 'formato_cep' => 'AD###', 'nomes' => [
                'pt_BR' => 'Andorra', 'en_US' => 'Andorra', 'es_ES' => 'Andorra', 'it_IT' => 'Andorra', 'pt_PT' => 'Andorra',
            ]],
            ['codigo' => 'SM', 'telefone' => '+378', 'formato_cep' => '4789#', 'nomes' => [
                'pt_BR' => 'San Marino', 'en_US' => 'San Marino', 'es_ES' => 'San Marino', 'it_IT' => 'San Marino', 'pt_PT' => 'San Marino',
            ]],
            ['codigo' => 'VA', 'telefone' => '+39', 'formato_cep' => '00120', 'nomes' => [
                'pt_BR' => 'Vaticano', 'en_US' => 'Vatican City', 'es_ES' => 'Ciudad del Vaticano', 'it_IT' => 'Città del Vaticano', 'pt_PT' => 'Vaticano',
            ]],
            ['codigo' => 'LI', 'telefone' => '+423', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Liechtenstein', 'en_US' => 'Liechtenstein', 'es_ES' => 'Liechtenstein', 'it_IT' => 'Liechtenstein', 'pt_PT' => 'Liechtenstein',
            ]],
            ['codigo' => 'MT', 'telefone' => '+356', 'formato_cep' => 'AAA ####', 'nomes' => [
                'pt_BR' => 'Malta', 'en_US' => 'Malta', 'es_ES' => 'Malta', 'it_IT' => 'Malta', 'pt_PT' => 'Malta',
            ]],

            // =============================================
            // EUROPA DO NORTE (ESCANDINÁVIA)
            // =============================================
            ['codigo' => 'SE', 'telefone' => '+46', 'formato_cep' => '### ##', 'nomes' => [
                'pt_BR' => 'Suécia', 'en_US' => 'Sweden', 'es_ES' => 'Suecia', 'it_IT' => 'Svezia', 'pt_PT' => 'Suécia',
            ]],
            ['codigo' => 'NO', 'telefone' => '+47', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Noruega', 'en_US' => 'Norway', 'es_ES' => 'Noruega', 'it_IT' => 'Norvegia', 'pt_PT' => 'Noruega',
            ]],
            ['codigo' => 'DK', 'telefone' => '+45', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Dinamarca', 'en_US' => 'Denmark', 'es_ES' => 'Dinamarca', 'it_IT' => 'Danimarca', 'pt_PT' => 'Dinamarca',
            ]],
            ['codigo' => 'FI', 'telefone' => '+358', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Finlândia', 'en_US' => 'Finland', 'es_ES' => 'Finlandia', 'it_IT' => 'Finlandia', 'pt_PT' => 'Finlândia',
            ]],
            ['codigo' => 'IS', 'telefone' => '+354', 'formato_cep' => '###', 'nomes' => [
                'pt_BR' => 'Islândia', 'en_US' => 'Iceland', 'es_ES' => 'Islandia', 'it_IT' => 'Islanda', 'pt_PT' => 'Islândia',
            ]],

            // =============================================
            // EUROPA ORIENTAL
            // =============================================
            ['codigo' => 'PL', 'telefone' => '+48', 'formato_cep' => '##-###', 'nomes' => [
                'pt_BR' => 'Polônia', 'en_US' => 'Poland', 'es_ES' => 'Polonia', 'it_IT' => 'Polonia', 'pt_PT' => 'Polónia',
            ]],
            ['codigo' => 'CZ', 'telefone' => '+420', 'formato_cep' => '### ##', 'nomes' => [
                'pt_BR' => 'República Tcheca', 'en_US' => 'Czech Republic', 'es_ES' => 'República Checa', 'it_IT' => 'Repubblica Ceca', 'pt_PT' => 'República Checa',
            ]],
            ['codigo' => 'SK', 'telefone' => '+421', 'formato_cep' => '### ##', 'nomes' => [
                'pt_BR' => 'Eslováquia', 'en_US' => 'Slovakia', 'es_ES' => 'Eslovaquia', 'it_IT' => 'Slovacchia', 'pt_PT' => 'Eslováquia',
            ]],
            ['codigo' => 'HU', 'telefone' => '+36', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Hungria', 'en_US' => 'Hungary', 'es_ES' => 'Hungría', 'it_IT' => 'Ungheria', 'pt_PT' => 'Hungria',
            ]],
            ['codigo' => 'RO', 'telefone' => '+40', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Romênia', 'en_US' => 'Romania', 'es_ES' => 'Rumanía', 'it_IT' => 'Romania', 'pt_PT' => 'Roménia',
            ]],
            ['codigo' => 'BG', 'telefone' => '+359', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Bulgária', 'en_US' => 'Bulgaria', 'es_ES' => 'Bulgaria', 'it_IT' => 'Bulgaria', 'pt_PT' => 'Bulgária',
            ]],
            ['codigo' => 'HR', 'telefone' => '+385', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Croácia', 'en_US' => 'Croatia', 'es_ES' => 'Croacia', 'it_IT' => 'Croazia', 'pt_PT' => 'Croácia',
            ]],
            ['codigo' => 'SI', 'telefone' => '+386', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Eslovênia', 'en_US' => 'Slovenia', 'es_ES' => 'Eslovenia', 'it_IT' => 'Slovenia', 'pt_PT' => 'Eslovénia',
            ]],
            ['codigo' => 'RS', 'telefone' => '+381', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Sérvia', 'en_US' => 'Serbia', 'es_ES' => 'Serbia', 'it_IT' => 'Serbia', 'pt_PT' => 'Sérvia',
            ]],
            ['codigo' => 'BA', 'telefone' => '+387', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Bósnia e Herzegovina', 'en_US' => 'Bosnia and Herzegovina', 'es_ES' => 'Bosnia y Herzegovina', 'it_IT' => 'Bosnia ed Erzegovina', 'pt_PT' => 'Bósnia e Herzegovina',
            ]],
            ['codigo' => 'ME', 'telefone' => '+382', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Montenegro', 'en_US' => 'Montenegro', 'es_ES' => 'Montenegro', 'it_IT' => 'Montenegro', 'pt_PT' => 'Montenegro',
            ]],
            ['codigo' => 'MK', 'telefone' => '+389', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Macedônia do Norte', 'en_US' => 'North Macedonia', 'es_ES' => 'Macedonia del Norte', 'it_IT' => 'Macedonia del Nord', 'pt_PT' => 'Macedónia do Norte',
            ]],
            ['codigo' => 'AL', 'telefone' => '+355', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Albânia', 'en_US' => 'Albania', 'es_ES' => 'Albania', 'it_IT' => 'Albania', 'pt_PT' => 'Albânia',
            ]],
            ['codigo' => 'XK', 'telefone' => '+383', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Kosovo', 'en_US' => 'Kosovo', 'es_ES' => 'Kosovo', 'it_IT' => 'Kosovo', 'pt_PT' => 'Kosovo',
            ]],
            ['codigo' => 'MD', 'telefone' => '+373', 'formato_cep' => 'MD-####', 'nomes' => [
                'pt_BR' => 'Moldávia', 'en_US' => 'Moldova', 'es_ES' => 'Moldavia', 'it_IT' => 'Moldavia', 'pt_PT' => 'Moldávia',
            ]],

            // =============================================
            // EUROPA - BÁLTICO
            // =============================================
            ['codigo' => 'EE', 'telefone' => '+372', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Estônia', 'en_US' => 'Estonia', 'es_ES' => 'Estonia', 'it_IT' => 'Estonia', 'pt_PT' => 'Estónia',
            ]],
            ['codigo' => 'LV', 'telefone' => '+371', 'formato_cep' => 'LV-####', 'nomes' => [
                'pt_BR' => 'Letônia', 'en_US' => 'Latvia', 'es_ES' => 'Letonia', 'it_IT' => 'Lettonia', 'pt_PT' => 'Letónia',
            ]],
            ['codigo' => 'LT', 'telefone' => '+370', 'formato_cep' => 'LT-#####', 'nomes' => [
                'pt_BR' => 'Lituânia', 'en_US' => 'Lithuania', 'es_ES' => 'Lituania', 'it_IT' => 'Lituania', 'pt_PT' => 'Lituânia',
            ]],

            // =============================================
            // RÚSSIA E EX-URSS
            // =============================================
            ['codigo' => 'RU', 'telefone' => '+7', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Rússia', 'en_US' => 'Russia', 'es_ES' => 'Rusia', 'it_IT' => 'Russia', 'pt_PT' => 'Rússia',
            ]],
            ['codigo' => 'UA', 'telefone' => '+380', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Ucrânia', 'en_US' => 'Ukraine', 'es_ES' => 'Ucrania', 'it_IT' => 'Ucraina', 'pt_PT' => 'Ucrânia',
            ]],
            ['codigo' => 'BY', 'telefone' => '+375', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Bielorrússia', 'en_US' => 'Belarus', 'es_ES' => 'Bielorrusia', 'it_IT' => 'Bielorussia', 'pt_PT' => 'Bielorrússia',
            ]],
            ['codigo' => 'GE', 'telefone' => '+995', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Geórgia', 'en_US' => 'Georgia', 'es_ES' => 'Georgia', 'it_IT' => 'Georgia', 'pt_PT' => 'Geórgia',
            ]],
            ['codigo' => 'AM', 'telefone' => '+374', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Armênia', 'en_US' => 'Armenia', 'es_ES' => 'Armenia', 'it_IT' => 'Armenia', 'pt_PT' => 'Arménia',
            ]],
            ['codigo' => 'AZ', 'telefone' => '+994', 'formato_cep' => 'AZ ####', 'nomes' => [
                'pt_BR' => 'Azerbaijão', 'en_US' => 'Azerbaijan', 'es_ES' => 'Azerbaiyán', 'it_IT' => 'Azerbaigian', 'pt_PT' => 'Azerbaijão',
            ]],
            ['codigo' => 'KZ', 'telefone' => '+7', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Cazaquistão', 'en_US' => 'Kazakhstan', 'es_ES' => 'Kazajistán', 'it_IT' => 'Kazakistan', 'pt_PT' => 'Cazaquistão',
            ]],
            ['codigo' => 'UZ', 'telefone' => '+998', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Uzbequistão', 'en_US' => 'Uzbekistan', 'es_ES' => 'Uzbekistán', 'it_IT' => 'Uzbekistan', 'pt_PT' => 'Uzbequistão',
            ]],

            // =============================================
            // EUROPA - GRÉCIA, CHIPRE, TURQUIA
            // =============================================
            ['codigo' => 'GR', 'telefone' => '+30', 'formato_cep' => '### ##', 'nomes' => [
                'pt_BR' => 'Grécia', 'en_US' => 'Greece', 'es_ES' => 'Grecia', 'it_IT' => 'Grecia', 'pt_PT' => 'Grécia',
            ]],
            ['codigo' => 'CY', 'telefone' => '+357', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Chipre', 'en_US' => 'Cyprus', 'es_ES' => 'Chipre', 'it_IT' => 'Cipro', 'pt_PT' => 'Chipre',
            ]],
            ['codigo' => 'TR', 'telefone' => '+90', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Turquia', 'en_US' => 'Turkey', 'es_ES' => 'Turquía', 'it_IT' => 'Turchia', 'pt_PT' => 'Turquia',
            ]],

            // =============================================
            // ORIENTE MÉDIO
            // =============================================
            ['codigo' => 'IL', 'telefone' => '+972', 'formato_cep' => '#######', 'nomes' => [
                'pt_BR' => 'Israel', 'en_US' => 'Israel', 'es_ES' => 'Israel', 'it_IT' => 'Israele', 'pt_PT' => 'Israel',
            ]],
            ['codigo' => 'AE', 'telefone' => '+971', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Emirados Árabes', 'en_US' => 'United Arab Emirates', 'es_ES' => 'Emiratos Árabes', 'it_IT' => 'Emirati Arabi Uniti', 'pt_PT' => 'Emirados Árabes',
            ]],
            ['codigo' => 'SA', 'telefone' => '+966', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Arábia Saudita', 'en_US' => 'Saudi Arabia', 'es_ES' => 'Arabia Saudita', 'it_IT' => 'Arabia Saudita', 'pt_PT' => 'Arábia Saudita',
            ]],
            ['codigo' => 'QA', 'telefone' => '+974', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Catar', 'en_US' => 'Qatar', 'es_ES' => 'Catar', 'it_IT' => 'Qatar', 'pt_PT' => 'Catar',
            ]],
            ['codigo' => 'KW', 'telefone' => '+965', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Kuwait', 'en_US' => 'Kuwait', 'es_ES' => 'Kuwait', 'it_IT' => 'Kuwait', 'pt_PT' => 'Kuwait',
            ]],
            ['codigo' => 'BH', 'telefone' => '+973', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Bahrein', 'en_US' => 'Bahrain', 'es_ES' => 'Baréin', 'it_IT' => 'Bahrein', 'pt_PT' => 'Barém',
            ]],
            ['codigo' => 'OM', 'telefone' => '+968', 'formato_cep' => '###', 'nomes' => [
                'pt_BR' => 'Omã', 'en_US' => 'Oman', 'es_ES' => 'Omán', 'it_IT' => 'Oman', 'pt_PT' => 'Omã',
            ]],
            ['codigo' => 'JO', 'telefone' => '+962', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Jordânia', 'en_US' => 'Jordan', 'es_ES' => 'Jordania', 'it_IT' => 'Giordania', 'pt_PT' => 'Jordânia',
            ]],
            ['codigo' => 'LB', 'telefone' => '+961', 'formato_cep' => '#### ####', 'nomes' => [
                'pt_BR' => 'Líbano', 'en_US' => 'Lebanon', 'es_ES' => 'Líbano', 'it_IT' => 'Libano', 'pt_PT' => 'Líbano',
            ]],
            ['codigo' => 'IQ', 'telefone' => '+964', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Iraque', 'en_US' => 'Iraq', 'es_ES' => 'Irak', 'it_IT' => 'Iraq', 'pt_PT' => 'Iraque',
            ]],
            ['codigo' => 'IR', 'telefone' => '+98', 'formato_cep' => '##########', 'nomes' => [
                'pt_BR' => 'Irã', 'en_US' => 'Iran', 'es_ES' => 'Irán', 'it_IT' => 'Iran', 'pt_PT' => 'Irão',
            ]],
            ['codigo' => 'SY', 'telefone' => '+963', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Síria', 'en_US' => 'Syria', 'es_ES' => 'Siria', 'it_IT' => 'Siria', 'pt_PT' => 'Síria',
            ]],
            ['codigo' => 'YE', 'telefone' => '+967', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Iêmen', 'en_US' => 'Yemen', 'es_ES' => 'Yemen', 'it_IT' => 'Yemen', 'pt_PT' => 'Iémen',
            ]],

            // =============================================
            // ÁSIA
            // =============================================
            ['codigo' => 'CN', 'telefone' => '+86', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'China', 'en_US' => 'China', 'es_ES' => 'China', 'it_IT' => 'Cina', 'pt_PT' => 'China',
            ]],
            ['codigo' => 'JP', 'telefone' => '+81', 'formato_cep' => '###-####', 'nomes' => [
                'pt_BR' => 'Japão', 'en_US' => 'Japan', 'es_ES' => 'Japón', 'it_IT' => 'Giappone', 'pt_PT' => 'Japão',
            ]],
            ['codigo' => 'KR', 'telefone' => '+82', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Coreia do Sul', 'en_US' => 'South Korea', 'es_ES' => 'Corea del Sur', 'it_IT' => 'Corea del Sud', 'pt_PT' => 'Coreia do Sul',
            ]],
            ['codigo' => 'IN', 'telefone' => '+91', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Índia', 'en_US' => 'India', 'es_ES' => 'India', 'it_IT' => 'India', 'pt_PT' => 'Índia',
            ]],
            ['codigo' => 'TH', 'telefone' => '+66', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Tailândia', 'en_US' => 'Thailand', 'es_ES' => 'Tailandia', 'it_IT' => 'Thailandia', 'pt_PT' => 'Tailândia',
            ]],
            ['codigo' => 'PH', 'telefone' => '+63', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Filipinas', 'en_US' => 'Philippines', 'es_ES' => 'Filipinas', 'it_IT' => 'Filippine', 'pt_PT' => 'Filipinas',
            ]],
            ['codigo' => 'MY', 'telefone' => '+60', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Malásia', 'en_US' => 'Malaysia', 'es_ES' => 'Malasia', 'it_IT' => 'Malesia', 'pt_PT' => 'Malásia',
            ]],
            ['codigo' => 'SG', 'telefone' => '+65', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Singapura', 'en_US' => 'Singapore', 'es_ES' => 'Singapur', 'it_IT' => 'Singapore', 'pt_PT' => 'Singapura',
            ]],
            ['codigo' => 'ID', 'telefone' => '+62', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Indonésia', 'en_US' => 'Indonesia', 'es_ES' => 'Indonesia', 'it_IT' => 'Indonesia', 'pt_PT' => 'Indonésia',
            ]],
            ['codigo' => 'VN', 'telefone' => '+84', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Vietnã', 'en_US' => 'Vietnam', 'es_ES' => 'Vietnam', 'it_IT' => 'Vietnam', 'pt_PT' => 'Vietname',
            ]],
            ['codigo' => 'PK', 'telefone' => '+92', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Paquistão', 'en_US' => 'Pakistan', 'es_ES' => 'Pakistán', 'it_IT' => 'Pakistan', 'pt_PT' => 'Paquistão',
            ]],
            ['codigo' => 'BD', 'telefone' => '+880', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Bangladesh', 'en_US' => 'Bangladesh', 'es_ES' => 'Bangladés', 'it_IT' => 'Bangladesh', 'pt_PT' => 'Bangladesh',
            ]],
            ['codigo' => 'LK', 'telefone' => '+94', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Sri Lanka', 'en_US' => 'Sri Lanka', 'es_ES' => 'Sri Lanka', 'it_IT' => 'Sri Lanka', 'pt_PT' => 'Sri Lanka',
            ]],
            ['codigo' => 'NP', 'telefone' => '+977', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Nepal', 'en_US' => 'Nepal', 'es_ES' => 'Nepal', 'it_IT' => 'Nepal', 'pt_PT' => 'Nepal',
            ]],
            ['codigo' => 'MM', 'telefone' => '+95', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Mianmar', 'en_US' => 'Myanmar', 'es_ES' => 'Myanmar', 'it_IT' => 'Myanmar', 'pt_PT' => 'Mianmar',
            ]],
            ['codigo' => 'KH', 'telefone' => '+855', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Camboja', 'en_US' => 'Cambodia', 'es_ES' => 'Camboya', 'it_IT' => 'Cambogia', 'pt_PT' => 'Camboja',
            ]],
            ['codigo' => 'TW', 'telefone' => '+886', 'formato_cep' => '###', 'nomes' => [
                'pt_BR' => 'Taiwan', 'en_US' => 'Taiwan', 'es_ES' => 'Taiwán', 'it_IT' => 'Taiwan', 'pt_PT' => 'Taiwan',
            ]],
            ['codigo' => 'HK', 'telefone' => '+852', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Hong Kong', 'en_US' => 'Hong Kong', 'es_ES' => 'Hong Kong', 'it_IT' => 'Hong Kong', 'pt_PT' => 'Hong Kong',
            ]],

            // =============================================
            // OCEANIA
            // =============================================
            ['codigo' => 'AU', 'telefone' => '+61', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Austrália', 'en_US' => 'Australia', 'es_ES' => 'Australia', 'it_IT' => 'Australia', 'pt_PT' => 'Austrália',
            ]],
            ['codigo' => 'NZ', 'telefone' => '+64', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Nova Zelândia', 'en_US' => 'New Zealand', 'es_ES' => 'Nueva Zelanda', 'it_IT' => 'Nuova Zelanda', 'pt_PT' => 'Nova Zelândia',
            ]],
            ['codigo' => 'FJ', 'telefone' => '+679', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Fiji', 'en_US' => 'Fiji', 'es_ES' => 'Fiyi', 'it_IT' => 'Figi', 'pt_PT' => 'Fiji',
            ]],

            // =============================================
            // ÁFRICA
            // =============================================
            ['codigo' => 'ZA', 'telefone' => '+27', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'África do Sul', 'en_US' => 'South Africa', 'es_ES' => 'Sudáfrica', 'it_IT' => 'Sudafrica', 'pt_PT' => 'África do Sul',
            ]],
            ['codigo' => 'AO', 'telefone' => '+244', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Angola', 'en_US' => 'Angola', 'es_ES' => 'Angola', 'it_IT' => 'Angola', 'pt_PT' => 'Angola',
            ]],
            ['codigo' => 'MZ', 'telefone' => '+258', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Moçambique', 'en_US' => 'Mozambique', 'es_ES' => 'Mozambique', 'it_IT' => 'Mozambico', 'pt_PT' => 'Moçambique',
            ]],
            ['codigo' => 'CV', 'telefone' => '+238', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Cabo Verde', 'en_US' => 'Cape Verde', 'es_ES' => 'Cabo Verde', 'it_IT' => 'Capo Verde', 'pt_PT' => 'Cabo Verde',
            ]],
            ['codigo' => 'GW', 'telefone' => '+245', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Guiné-Bissau', 'en_US' => 'Guinea-Bissau', 'es_ES' => 'Guinea-Bisáu', 'it_IT' => 'Guinea-Bissau', 'pt_PT' => 'Guiné-Bissau',
            ]],
            ['codigo' => 'ST', 'telefone' => '+239', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'São Tomé e Príncipe', 'en_US' => 'Sao Tome and Principe', 'es_ES' => 'Santo Tomé y Príncipe', 'it_IT' => 'São Tomé e Príncipe', 'pt_PT' => 'São Tomé e Príncipe',
            ]],
            ['codigo' => 'TL', 'telefone' => '+670', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Timor-Leste', 'en_US' => 'East Timor', 'es_ES' => 'Timor Oriental', 'it_IT' => 'Timor Est', 'pt_PT' => 'Timor-Leste',
            ]],
            ['codigo' => 'EG', 'telefone' => '+20', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Egito', 'en_US' => 'Egypt', 'es_ES' => 'Egipto', 'it_IT' => 'Egitto', 'pt_PT' => 'Egito',
            ]],
            ['codigo' => 'MA', 'telefone' => '+212', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Marrocos', 'en_US' => 'Morocco', 'es_ES' => 'Marruecos', 'it_IT' => 'Marocco', 'pt_PT' => 'Marrocos',
            ]],
            ['codigo' => 'NG', 'telefone' => '+234', 'formato_cep' => '######', 'nomes' => [
                'pt_BR' => 'Nigéria', 'en_US' => 'Nigeria', 'es_ES' => 'Nigeria', 'it_IT' => 'Nigeria', 'pt_PT' => 'Nigéria',
            ]],
            ['codigo' => 'KE', 'telefone' => '+254', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Quênia', 'en_US' => 'Kenya', 'es_ES' => 'Kenia', 'it_IT' => 'Kenya', 'pt_PT' => 'Quénia',
            ]],
            ['codigo' => 'GH', 'telefone' => '+233', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Gana', 'en_US' => 'Ghana', 'es_ES' => 'Ghana', 'it_IT' => 'Ghana', 'pt_PT' => 'Gana',
            ]],
            ['codigo' => 'TZ', 'telefone' => '+255', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Tanzânia', 'en_US' => 'Tanzania', 'es_ES' => 'Tanzania', 'it_IT' => 'Tanzania', 'pt_PT' => 'Tanzânia',
            ]],
            ['codigo' => 'ET', 'telefone' => '+251', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Etiópia', 'en_US' => 'Ethiopia', 'es_ES' => 'Etiopía', 'it_IT' => 'Etiopia', 'pt_PT' => 'Etiópia',
            ]],
            ['codigo' => 'TN', 'telefone' => '+216', 'formato_cep' => '####', 'nomes' => [
                'pt_BR' => 'Tunísia', 'en_US' => 'Tunisia', 'es_ES' => 'Túnez', 'it_IT' => 'Tunisia', 'pt_PT' => 'Tunísia',
            ]],
            ['codigo' => 'DZ', 'telefone' => '+213', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Argélia', 'en_US' => 'Algeria', 'es_ES' => 'Argelia', 'it_IT' => 'Algeria', 'pt_PT' => 'Argélia',
            ]],
            ['codigo' => 'SN', 'telefone' => '+221', 'formato_cep' => '#####', 'nomes' => [
                'pt_BR' => 'Senegal', 'en_US' => 'Senegal', 'es_ES' => 'Senegal', 'it_IT' => 'Senegal', 'pt_PT' => 'Senegal',
            ]],
            ['codigo' => 'CI', 'telefone' => '+225', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Costa do Marfim', 'en_US' => 'Ivory Coast', 'es_ES' => 'Costa de Marfil', 'it_IT' => "Costa d'Avorio", 'pt_PT' => 'Costa do Marfim',
            ]],
            ['codigo' => 'CM', 'telefone' => '+237', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Camarões', 'en_US' => 'Cameroon', 'es_ES' => 'Camerún', 'it_IT' => 'Camerun', 'pt_PT' => 'Camarões',
            ]],
            ['codigo' => 'UG', 'telefone' => '+256', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Uganda', 'en_US' => 'Uganda', 'es_ES' => 'Uganda', 'it_IT' => 'Uganda', 'pt_PT' => 'Uganda',
            ]],
            ['codigo' => 'CD', 'telefone' => '+243', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Congo (RDC)', 'en_US' => 'DR Congo', 'es_ES' => 'Congo (RDC)', 'it_IT' => 'Congo (RDC)', 'pt_PT' => 'Congo (RDC)',
            ]],
            ['codigo' => 'RW', 'telefone' => '+250', 'formato_cep' => null, 'nomes' => [
                'pt_BR' => 'Ruanda', 'en_US' => 'Rwanda', 'es_ES' => 'Ruanda', 'it_IT' => 'Ruanda', 'pt_PT' => 'Ruanda',
            ]],
        ];
    }
};
