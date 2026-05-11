<?php

use App\Database\Migration;

/**
 * Migration: Adicionar colunas de configuração à tabela matrizes_filiais
 *
 * Migra as configurações da tabela `configuracoes` (JSON) para colunas
 * na própria tabela `matrizes_filiais`, usando locale para internacionalização.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Adicionar novas colunas à tabela matrizes_filiais
        $this->table('matrizes_filiais', function ($table) {
            // Configurações de Localização
            $table->string('locale', 10)->default('pt_BR')->after('assinatura');
            $table->string('currency_code', 3)->default('BRL')->after('locale');
            $table->string('date_format', 20)->default('d/m/Y H:i:s')->after('currency_code');

            // Sequências de Numeração
            $table->integer('sequencia_locacoes')->unsigned()->default(1)->after('date_format');
            $table->integer('sequencia_contratos')->unsigned()->default(1)->after('sequencia_locacoes');
            $table->integer('sequencia_financeiro')->unsigned()->default(1)->after('sequencia_contratos');

            // Configurações de Notificações
            $table->string('notificacao_sms', 1)->default('N')->after('sequencia_financeiro');
            $table->string('notificacao_email', 1)->default('N')->after('notificacao_sms');
            $table->string('notificacao_whatsapp', 1)->default('N')->after('notificacao_email');
            $table->string('notificacao_titulo', 100)->nullable()->after('notificacao_whatsapp');

            // Configurações de Impressão
            $table->string('impressao_variavel_negrito', 1)->default('N')->after('notificacao_titulo');
            $table->string('impressao_remover_tarja_amarela', 1)->default('N')->after('impressao_variavel_negrito');
        });

        // Migrar dados existentes da tabela configuracoes
        $this->migrateConfigData();
    }

    public function down(): void
    {
        // Remover colunas adicionadas
        $this->table('matrizes_filiais', function ($table) {
            $table->dropColumn('locale');
            $table->dropColumn('currency_code');
            $table->dropColumn('date_format');
            $table->dropColumn('sequencia_locacoes');
            $table->dropColumn('sequencia_contratos');
            $table->dropColumn('sequencia_financeiro');
            $table->dropColumn('notificacao_sms');
            $table->dropColumn('notificacao_email');
            $table->dropColumn('notificacao_whatsapp');
            $table->dropColumn('notificacao_titulo');
            $table->dropColumn('impressao_variavel_negrito');
            $table->dropColumn('impressao_remover_tarja_amarela');
        });
    }

    /**
     * Migra dados da tabela configuracoes (JSON) para as novas colunas
     */
    private function migrateConfigData(): void
    {
        // Verificar se a tabela configuracoes existe
        if (!$this->tableExists('configuracoes')) {
            return;
        }

        // Buscar todas as configurações existentes
        $configs = $this->db()->table('configuracoes')->select(['chave', 'data_array'])->whereRaw('deleted_at IS NULL')->get();

        foreach ($configs as $config) {
            $chave = $config['chave'];
            $dataArray = json_decode($config['data_array'], true);

            if (!is_array($dataArray)) {
                continue;
            }

            // Preparar dados para atualização
            $updateData = [];

            // Determinar locale a partir de language/country antigos (ou usar padrão)
            $updateData['locale'] = $this->determineLocale($dataArray);

            // Moeda
            if (isset($dataArray['formatting']['currency']['codigo'])) {
                $updateData['currency_code'] = $dataArray['formatting']['currency']['codigo'];
            }

            // Formato de data
            if (isset($dataArray['formatting']['date'])) {
                $updateData['date_format'] = $this->convertDateFormat($dataArray['formatting']['date']);
            }

            // Sequências
            if (isset($dataArray['sequencia_locacoes'])) {
                $updateData['sequencia_locacoes'] = (int) $dataArray['sequencia_locacoes'];
            }
            if (isset($dataArray['sequencia_contratos'])) {
                $updateData['sequencia_contratos'] = (int) $dataArray['sequencia_contratos'];
            }
            if (isset($dataArray['sequencia_financeiro'])) {
                $updateData['sequencia_financeiro'] = (int) $dataArray['sequencia_financeiro'];
            }

            // Notificações
            if (isset($dataArray['notificacoes']['sms'])) {
                $updateData['notificacao_sms'] = $dataArray['notificacoes']['sms'] === '1' ? 'S' : 'N';
            }
            if (isset($dataArray['notificacoes']['email'])) {
                $updateData['notificacao_email'] = $dataArray['notificacoes']['email'] === '1' ? 'S' : 'N';
            }
            if (isset($dataArray['notificacoes']['whatsapp'])) {
                $updateData['notificacao_whatsapp'] = $dataArray['notificacoes']['whatsapp'] === '1' ? 'S' : 'N';
            }
            if (isset($dataArray['notificacoes']['titulo'])) {
                $updateData['notificacao_titulo'] = $dataArray['notificacoes']['titulo'];
            }

            // Impressão
            if (isset($dataArray['impressao']['variavel_negrito'])) {
                $updateData['impressao_variavel_negrito'] = $dataArray['impressao']['variavel_negrito'];
            }
            if (isset($dataArray['impressao']['remover_tarja_amarela'])) {
                $updateData['impressao_remover_tarja_amarela'] = $dataArray['impressao']['remover_tarja_amarela'];
            }

            // Atualizar matrizes_filiais com os dados migrados
            if (!empty($updateData)) {
                $this->db()->table('matrizes_filiais')->whereRaw('chave = ?', [$chave])->update($updateData);
            }
        }
    }

    /**
     * Determina o locale baseado nos campos antigos language/country
     */
    private function determineLocale(array $dataArray): string
    {
        $language = $dataArray['language'] ?? 'pt_BR';
        $country = $dataArray['country'] ?? 'brasil';

        // Mapeamento de combinações comuns
        $localeMap = [
            'pt_BR' => 'pt_BR',
            'en_US' => 'en_US',
            'es_ES' => 'es_ES',
            'pt_PT' => 'pt_PT',
        ];

        // Se já está no formato locale, retorna
        if (isset($localeMap[$language])) {
            return $localeMap[$language];
        }

        // Inferir do país
        $countryLocaleMap = [
            'brasil' => 'pt_BR',
            'brazil' => 'pt_BR',
            'portugal' => 'pt_PT',
            'usa' => 'en_US',
            'united states' => 'en_US',
            'spain' => 'es_ES',
            'espanha' => 'es_ES',
        ];

        $countryLower = strtolower($country);
        if (isset($countryLocaleMap[$countryLower])) {
            return $countryLocaleMap[$countryLower];
        }

        return 'pt_BR'; // Padrão
    }

    /**
     * Converte formato de data legado para formato PHP
     */
    private function convertDateFormat(string $legacyFormat): string
    {
        // Mapear formatos conhecidos
        $formatMap = [
            'ddmmyyyyHHiiss' => 'd/m/Y H:i:s',
            'ddmmyyyy' => 'd/m/Y',
            'yyyymmdd' => 'Y-m-d',
            'mmddyyyy' => 'm/d/Y',
        ];

        return $formatMap[$legacyFormat] ?? 'd/m/Y H:i:s';
    }
};
