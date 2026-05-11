<?php

use App\Database\Migration;

/**
 * Migration: Popular tabela roles
 *
 * Extrai os valores únicos do campo 'funcao' da tabela funcionarios
 * e cria as roles correspondentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar valores únicos de funcao (ignorando NULL e vazios)
        $result = $this->db()
            ->table('funcionarios')
            ->select(['DISTINCT LOWER(TRIM(funcao)) as funcao_normalizada'])
            ->whereRaw('funcao IS NOT NULL AND TRIM(funcao) != \'\'')
            ->get();

        // Criar roles baseadas nos valores encontrados
        $rolesCreated = [];
        foreach ($result as $row) {
            $funcaoNormalizada = $row['funcao_normalizada'];

            // Evitar duplicatas (case-insensitive)
            if (in_array($funcaoNormalizada, $rolesCreated)) {
                continue;
            }

            // Capitalizar primeira letra
            $name = ucfirst($funcaoNormalizada);

            $this->db()->withoutChave()->table('roles')->insert([
                'chave' => '0',
                'name' => $name,
                'description' => "Função: {$name}"
            ]);

            $rolesCreated[] = $funcaoNormalizada;
        }

        // Criar role padrão para usuários sem função
        $this->db()->withoutChave()->table('roles')->insert([
            'chave' => '0',
            'name' => 'Sem Função',
            'description' => 'Função padrão para usuários sem função atribuída'
        ]);
    }

    public function down(): void
    {
        $this->db()->table('roles')->whereRaw('1=1')->delete();
    }
};
