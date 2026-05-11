<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renomear Bloqueio/Caução → Bloqueio
        $this->db()->table('planos_de_contas')
            ->whereRaw('id = ?', [116])
            ->update(['descricao_i18n' => '{"pt_BR":"Bloqueio","pt_PT":"Bloqueio","en_US":"Block","es_ES":"Bloqueo","it_IT":"Blocco"}']);

        $this->db()->table('planos_de_contas')
            ->whereRaw('id = ?', [117])
            ->update(['descricao_i18n' => '{"pt_BR":"Bloqueio entrada","pt_PT":"Bloqueio entrada","en_US":"Block in","es_ES":"Bloqueo entrada","it_IT":"Blocco entrata"}']);

        $this->db()->table('planos_de_contas')
            ->whereRaw('id = ?', [118])
            ->update(['descricao_i18n' => '{"pt_BR":"Bloqueio saída","pt_PT":"Bloqueio saída","en_US":"Block out","es_ES":"Bloqueo salida","it_IT":"Blocco uscita"}']);

        // 2. Criar grupo Caução
        $caucaoData = [
            ['chave' => '0', 'hierarquia' => '1.1.6', 'descricao_i18n' => '{"pt_BR":"Caução","pt_PT":"Caução","en_US":"Security Deposit","es_ES":"Depósito de garantía","it_IT":"Cauzione"}', 'tipo' => 'A'],
            ['chave' => '0', 'hierarquia' => '1.1.6.01', 'descricao_i18n' => '{"pt_BR":"Caução entrada","pt_PT":"Caução entrada","en_US":"Security Deposit in","es_ES":"Depósito de garantía entrada","it_IT":"Cauzione entrata"}', 'tipo' => 'A'],
            ['chave' => '0', 'hierarquia' => '1.1.6.02', 'descricao_i18n' => '{"pt_BR":"Caução saída","pt_PT":"Caução saída","en_US":"Security Deposit out","es_ES":"Depósito de garantía salida","it_IT":"Cauzione uscita"}', 'tipo' => 'A'],
        ];

        foreach ($caucaoData as $row) {
            $exists = $this->db()->table('planos_de_contas')
                ->select(['id'])
                ->whereRaw('hierarquia = ?', [$row['hierarquia']])
                ->first();

            if (!$exists) {
                $this->db()->table('planos_de_contas')->insert($row);
            }
        }

        // 3. Criar Licenciamento
        $exists = $this->db()->table('planos_de_contas')
            ->select(['id'])
            ->whereRaw('hierarquia = ?', ['3.3.1.09'])
            ->first();

        if (!$exists) {
            $this->db()->table('planos_de_contas')->insert([
                'chave' => '0',
                'hierarquia' => '3.3.1.09',
                'descricao_i18n' => '{"pt_BR":"Licenciamento","pt_PT":"Licenciamento","en_US":"Vehicle Licensing","es_ES":"Licencia vehicular","it_IT":"Licenza veicolare"}',
                'tipo' => 'D',
            ]);
        }
    }

    public function down(): void
    {
        // Reverter Bloqueio → Bloqueio/Caução
        $this->db()->table('planos_de_contas')
            ->whereRaw('id = ?', [116])
            ->update(['descricao_i18n' => '{"en_US":"Block/Deposit","es_ES":"Bloqueo/Depósito","it_IT":"Blocco/Cauzione","pt_BR":"Bloqueio/Caução","pt_PT":"Bloqueio/Caução"}']);

        $this->db()->table('planos_de_contas')
            ->whereRaw('id = ?', [117])
            ->update(['descricao_i18n' => '{"en_US":"Block/Deposit in","es_ES":"Bloqueo/Depósito entrada","it_IT":"Blocco/Cauzione entrata","pt_BR":"Bloqueio/Caução entrada","pt_PT":"Bloqueio/Caução entrada"}']);

        $this->db()->table('planos_de_contas')
            ->whereRaw('id = ?', [118])
            ->update(['descricao_i18n' => '{"en_US":"Block/Deposit out","es_ES":"Bloqueo/Depósito salida","it_IT":"Blocco/Cauzione uscita","pt_BR":"Bloqueio/Caução saída","pt_PT":"Bloqueio/Caução saída"}']);

        // Remover Caução e Licenciamento
        $this->db()->table('planos_de_contas')
            ->whereRaw('hierarquia IN (?, ?, ?, ?)', ['1.1.6', '1.1.6.01', '1.1.6.02', '3.3.1.09'])
            ->delete();
    }
};
