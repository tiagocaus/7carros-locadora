<?php

/**
 * Migration 00268: Normalizar campo pais para codigo ISO alpha-2
 *
 * Converte os valores texto livres em clientes, matrizes_filiais,
 * funcionarios e fornecedores para codigos ISO 3166-1 alpha-2.
 * Valores nao reconheciveis sao convertidos para 'BR' (default).
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['clientes', 'matrizes_filiais', 'funcionarios', 'fornecedores'];
        $map = $this->getNormalizationMap();

        foreach ($tables as $table) {
            if (!$this->columnExists($table, 'pais')) {
                continue;
            }

            // Passo 1: Aplicar mapeamento especifico para valores reconheciveis
            foreach ($map as $isoCode => $variations) {
                foreach ($variations as $variation) {
                    $this->db()->table($table)
                        ->whereRaw('TRIM(LOWER(pais)) = LOWER(TRIM(?))', [$variation])
                        ->update(['pais' => $isoCode]);
                }
            }

            // Passo 2: Converter valores que ja sao codigos ISO validos (2 letras)
            // mas que nao estao no mapa (ex: 'fr', 'de' que ja estao corretos em lowercase)
            $codes = $this->db()->table('paises')->pluck('codigo');
            foreach ($codes as $code) {
                $this->db()->table($table)
                    ->whereRaw('UPPER(TRIM(pais)) = ? AND LENGTH(TRIM(pais)) = 2', [$code])
                    ->update(['pais' => $code]);
            }

            // Passo 3: Tudo que sobrou e nao eh um codigo ISO valido → 'BR'
            $this->db()->table($table)
                ->whereRaw("pais IS NULL OR TRIM(pais) = '' OR TRIM(pais) NOT IN (SELECT codigo FROM paises)")
                ->update(['pais' => 'BR']);
        }
    }

    public function down(): void
    {
        // Irreversivel - dados originais foram perdidos
        // Nao ha como reverter a normalizacao
    }

    private function getNormalizationMap(): array
    {
        return [
            // ============================
            // BRASIL (maior volume de variacoes)
            // ============================
            'BR' => [
                'Brasil', 'BRASIL', 'Br', 'brasi', 'BARSIL', 'BRSIL', 'brsail',
                'BRAIL', 'BRAISL', 'BTRASIL', 'BRASIUL', 'BRAASIL', 'BRASILE',
                'BRASIOL', 'VRASIL', 'BRTASIL', 'Brasill', 'barasil', 'Brazil',
                'BRAZIL', 'BRA', 'Brasilnet', 'brasil74988', 'BRASILVA',
                ' Brasil ', 'B', 'BRASILEIRO', 'BASIL', 'Brasi;', 'bras',
                'BRASIOL', ' Brasil', 'Brasil ', 'brasi', 'BRAIL',
            ],

            // ============================
            // ARGENTINA
            // ============================
            'AR' => [
                'Argentina', 'ARGETINA', 'arg', 'Argentino', 'Argentia',
                'Argentna', 'Argenina', 'Aregtnina', 'AREGENTINA',
                'Argentina juanpablo', 'Argentinaomarandresleiva@gmail.com',
                'Argentna', 'Argentia',
            ],

            // ============================
            // CHILE
            // ============================
            'CL' => [
                'Chile', 'Clile', 'Chille', ' Chile ',
            ],

            // ============================
            // URUGUAI
            // ============================
            'UY' => [
                'Uruguai', 'Uruguay', 'URY',
            ],

            // ============================
            // PARAGUAI
            // ============================
            'PY' => [
                'Paraguai', 'PARAGUAI', 'Paraguay', ' Paraguai',
            ],

            // ============================
            // ESTADOS UNIDOS
            // ============================
            'US' => [
                'Estados Unidos', 'EUA', 'Eua', 'estados unidos', 'USA',
                'United States', 'U.S.A.', 'ESTADOS UNIDOS DA AMERICA',
            ],

            // ============================
            // ITALIA
            // ============================
            'IT' => [
                'Italia', 'ITALIA', 'Itália', ' Italia',
            ],

            // ============================
            // PORTUGAL
            // ============================
            'PT' => [
                'PORTUGAL', 'PORTUGUAL', 'Portugal',
            ],

            // ============================
            // FRANCA
            // ============================
            'FR' => [
                'França', 'FRANCE', 'France',
            ],

            // ============================
            // ALEMANHA
            // ============================
            'DE' => [
                'Alemanha', 'ALEMANAHA', 'Aalemanha', 'GERMANY', ' Germany', 'Germany',
            ],

            // ============================
            // REINO UNIDO
            // ============================
            'GB' => [
                'INGLATERRA', 'INGLETERRA', 'REINO UNIDO', 'Reino Unido Inglaterra',
                ' Reino Unido', 'LONDON', 'UK',
            ],

            // ============================
            // ESPANHA
            // ============================
            'ES' => [
                'ESPANHA', 'Espanha', 'España',
            ],

            // ============================
            // COLOMBIA
            // ============================
            'CO' => [
                'Colombia', 'Colômbia',
            ],

            // ============================
            // PERU
            // ============================
            'PE' => [
                'Peru',
            ],

            // ============================
            // BOLIVIA
            // ============================
            'BO' => [
                'Bolivia', 'BOLIVIA',
            ],

            // ============================
            // EQUADOR
            // ============================
            'EC' => [
                'Equador ', 'Equador', 'Ecuador',
            ],

            // ============================
            // PAISES BAIXOS
            // ============================
            'NL' => [
                'Holanda', 'OLANDA', 'HOLLANDE', 'Hiolanda ', 'Holandabram',
            ],

            // ============================
            // SUICA
            // ============================
            'CH' => [
                'Suiça', 'Suíça',
            ],

            // ============================
            // BELGICA
            // ============================
            'BE' => [
                'Belgica', 'Bélgica',
            ],

            // ============================
            // AUSTRALIA
            // ============================
            'AU' => [
                'Australia', ' Austrália', 'Austrália',
            ],

            // ============================
            // NOVA ZELANDIA
            // ============================
            'NZ' => [
                'NEW ZEALAND', 'Nova Zelândia',
            ],

            // ============================
            // RUSSIA
            // ============================
            'RU' => [
                'RUSSIA ', 'Rusia', 'RUS', 'Rússia',
            ],

            // ============================
            // POLONIA
            // ============================
            'PL' => [
                'Polonia', 'Polônia',
            ],

            // ============================
            // IRLANDA
            // ============================
            'IE' => [
                'Irlanda', 'Ireland',
            ],

            // ============================
            // MEXICO
            // ============================
            'MX' => [
                'Mexico', 'México',
            ],

            // ============================
            // CANADA
            // ============================
            'CA' => [
                'Canada', 'Canadá',
            ],

            // ============================
            // ISRAEL
            // ============================
            'IL' => [
                'Israel',
            ],

            // ============================
            // ANGOLA
            // ============================
            'AO' => [
                'Angola',
            ],

            // ============================
            // SUECIA
            // ============================
            'SE' => [
                'Suecia ', 'Suécia',
            ],

            // ============================
            // REPUBLICA TCHECA
            // ============================
            'CZ' => [
                'CZECH REPUBLIC', 'Republika Ceska ', 'Republica Ceska ',
            ],

            // ============================
            // PANAMA
            // ============================
            'PA' => [
                'Panama ', 'Panamá',
            ],

            // ============================
            // VENEZUELA
            // ============================
            'VE' => [
                'Venezuela',
            ],

            // ============================
            // BULGARIA
            // ============================
            'BG' => [
                'Bulgaria', 'BUGARIA ', 'Bulgária',
            ],

            // ============================
            // ANDORRA
            // ============================
            'AD' => [
                'ANDORRA', 'Andorra',
            ],

            // ============================
            // SAN MARINO
            // ============================
            'SM' => [
                'San Marino',
            ],

            // ============================
            // UCRANIA
            // ============================
            'UA' => [
                'UCRANIA', 'Ucrânia',
            ],

            // ============================
            // COREIA DO SUL
            // ============================
            'KR' => [
                'COREIA DO SUL',
            ],

            // ============================
            // ALBANIA
            // ============================
            'AL' => [
                'ALBANIA', 'Albânia',
            ],

            // ============================
            // ESTONIA
            // ============================
            'EE' => [
                'Estônia', 'Estónia',
            ],

            // ============================
            // ESLOVENIA
            // ============================
            'SI' => [
                'Eslovenia', 'Eslovênia',
            ],

            // ============================
            // GEORGIA
            // ============================
            'GE' => [
                'Geogia', 'Geórgia',
            ],

            // ============================
            // BIELORRUSSIA
            // ============================
            'BY' => [
                'BLR', 'Bielorrússia',
            ],

            // ============================
            // ROMENIA
            // ============================
            'RO' => [
                'Romênia', 'Roménia',
            ],

            // ============================
            // DINAMARCA
            // ============================
            'DK' => [
                'Dinamarca',
            ],

            // ============================
            // REPUBLICA DOMINICANA
            // ============================
            'DO' => [
                'República Dominicana',
            ],

            // ============================
            // SENEGAL
            // ============================
            'SN' => [
                'Senegal',
            ],

            // ============================
            // EGITO
            // ============================
            'EG' => [
                'Egito',
            ],
        ];
    }
};
