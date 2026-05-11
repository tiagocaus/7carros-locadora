<?php

use App\Database\Migration;

/**
 * Migration: Seed de templates de temporadas do sistema
 *
 * Insere os templates padrão (chave='0') para cada país.
 * Estes registros são somente leitura e servem como catálogo
 * de feriados disponíveis para os clientes ativarem.
 */
return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            // ============================================
            // BRASIL (BR)
            // ============================================
            ['0', 'BR', 'Ano Novo', 12, 31, 1, 2, 0],
            ['0', 'BR', 'Carnaval', 2, 21, 3, 6, 0],
            ['0', 'BR', 'Semana Santa', 3, 28, 4, 5, 0],
            ['0', 'BR', 'Tiradentes', 4, 21, 4, 21, 0],
            ['0', 'BR', 'Dia do Trabalho', 5, 1, 5, 1, 0],
            ['0', 'BR', 'Corpus Christi', 5, 30, 6, 5, 0],
            ['0', 'BR', 'Independência do Brasil', 9, 7, 9, 7, 0],
            ['0', 'BR', 'Nossa Senhora Aparecida', 10, 12, 10, 12, 0],
            ['0', 'BR', 'Finados', 11, 2, 11, 2, 0],
            ['0', 'BR', 'Proclamação da República', 11, 15, 11, 15, 0],
            ['0', 'BR', 'Natal', 12, 24, 12, 26, 0],
            ['0', 'BR', 'Réveillon', 12, 29, 1, 3, 0],
            ['0', 'BR', 'Férias de Julho', 7, 1, 7, 31, 0],
            ['0', 'BR', 'Férias de Verão', 12, 15, 2, 15, 0],

            // ============================================
            // ESTADOS UNIDOS (US)
            // ============================================
            ['0', 'US', 'New Year', 12, 31, 1, 2, 0],
            ['0', 'US', 'Martin Luther King Jr. Day', 1, 15, 1, 21, 0],
            ['0', 'US', 'Presidents Day', 2, 15, 2, 21, 0],
            ['0', 'US', 'Memorial Day', 5, 25, 5, 31, 0],
            ['0', 'US', 'Independence Day', 7, 4, 7, 4, 0],
            ['0', 'US', 'Labor Day', 9, 1, 9, 7, 0],
            ['0', 'US', 'Columbus Day', 10, 8, 10, 14, 0],
            ['0', 'US', 'Veterans Day', 11, 11, 11, 11, 0],
            ['0', 'US', 'Thanksgiving', 11, 22, 11, 28, 0],
            ['0', 'US', 'Christmas', 12, 24, 12, 26, 0],
            ['0', 'US', 'Spring Break', 3, 10, 3, 24, 0],
            ['0', 'US', 'Summer Vacation', 6, 15, 8, 31, 0],

            // ============================================
            // ITÁLIA (IT)
            // ============================================
            ['0', 'IT', 'Capodanno', 12, 31, 1, 2, 0],
            ['0', 'IT', 'Epifania', 1, 6, 1, 6, 0],
            ['0', 'IT', 'Pasqua e Pasquetta', 3, 28, 4, 6, 0],
            ['0', 'IT', 'Liberazione', 4, 25, 4, 25, 0],
            ['0', 'IT', 'Festa del Lavoro', 5, 1, 5, 1, 0],
            ['0', 'IT', 'Festa della Repubblica', 6, 2, 6, 2, 0],
            ['0', 'IT', 'Ferragosto', 8, 15, 8, 15, 0],
            ['0', 'IT', 'Tutti i Santi', 11, 1, 11, 1, 0],
            ['0', 'IT', 'Immacolata Concezione', 12, 8, 12, 8, 0],
            ['0', 'IT', 'Natale', 12, 24, 12, 26, 0],
            ['0', 'IT', 'Vacanze Estive', 7, 15, 8, 31, 0],

            // ============================================
            // ESPANHA (ES)
            // ============================================
            ['0', 'ES', 'Año Nuevo', 12, 31, 1, 2, 0],
            ['0', 'ES', 'Reyes Magos', 1, 6, 1, 6, 0],
            ['0', 'ES', 'Semana Santa', 3, 28, 4, 6, 0],
            ['0', 'ES', 'Día del Trabajo', 5, 1, 5, 1, 0],
            ['0', 'ES', 'Asunción de la Virgen', 8, 15, 8, 15, 0],
            ['0', 'ES', 'Día de la Hispanidad', 10, 12, 10, 12, 0],
            ['0', 'ES', 'Todos los Santos', 11, 1, 11, 1, 0],
            ['0', 'ES', 'Día de la Constitución', 12, 6, 12, 6, 0],
            ['0', 'ES', 'Inmaculada Concepción', 12, 8, 12, 8, 0],
            ['0', 'ES', 'Navidad', 12, 24, 12, 26, 0],
            ['0', 'ES', 'Puente de Diciembre', 12, 6, 12, 9, 0],
            ['0', 'ES', 'Vacaciones de Verano', 7, 1, 8, 31, 0],

            // ============================================
            // PORTUGAL (PT)
            // ============================================
            ['0', 'PT', 'Ano Novo', 12, 31, 1, 2, 0],
            ['0', 'PT', 'Carnaval', 2, 21, 2, 25, 0],
            ['0', 'PT', 'Sexta-feira Santa e Páscoa', 3, 28, 4, 6, 0],
            ['0', 'PT', 'Dia da Liberdade', 4, 25, 4, 25, 0],
            ['0', 'PT', 'Dia do Trabalhador', 5, 1, 5, 1, 0],
            ['0', 'PT', 'Corpo de Deus', 5, 30, 6, 5, 0],
            ['0', 'PT', 'Dia de Portugal', 6, 10, 6, 10, 0],
            ['0', 'PT', 'Assunção de Nossa Senhora', 8, 15, 8, 15, 0],
            ['0', 'PT', 'Implantação da República', 10, 5, 10, 5, 0],
            ['0', 'PT', 'Todos os Santos', 11, 1, 11, 1, 0],
            ['0', 'PT', 'Restauração da Independência', 12, 1, 12, 1, 0],
            ['0', 'PT', 'Imaculada Conceição', 12, 8, 12, 8, 0],
            ['0', 'PT', 'Natal', 12, 24, 12, 26, 0],
            ['0', 'PT', 'Férias de Verão', 7, 15, 8, 31, 0],
        ];

        foreach ($templates as $t) {
            $this->db()->table('temporadas')->withoutChave()->insert([
                'chave' => $t[0],
                'pais' => $t[1],
                'nome' => $t[2],
                'mes_inicio' => $t[3],
                'dia_inicio' => $t[4],
                'mes_fim' => $t[5],
                'dia_fim' => $t[6],
                'ativo' => $t[7],
            ]);
        }
    }

    public function down(): void
    {
        $this->db()->withoutChave()->table('temporadas')->whereRaw("chave = '0'")->delete();
    }
};
