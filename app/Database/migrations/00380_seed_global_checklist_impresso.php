<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $exists = $this->pdo
            ->query("SELECT id FROM checklist_modelos WHERE chave = '0' AND tipo = 1 AND nome = 'Impresso' LIMIT 1")
            ->fetch();

        if ($exists) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO checklist_modelos (chave, nome, tipo, status, questoes, vistoria, created_at, updated_at)
             VALUES (:chave, :nome, :tipo, :status, :questoes, :vistoria, NOW(), NOW())'
        );

        $stmt->execute([
            'chave' => '0',
            'nome' => 'Impresso',
            'tipo' => 1,
            'status' => 'A',
            'questoes' => '[{"content":"Documentos","id":5},{"content":"Chave de roda","id":3},{"content":"Chave do veículo","id":4},{"content":"Estepe","id":6},{"content":"Calota","id":2},{"content":"Extintor","id":7},{"content":"Macaco","id":8},{"content":"Antena","id":1},{"content":"Rádio","id":9},{"content":"Tapetes","id":10},{"content":"Triângulo","id":11},{"content":"Modelo:","id":12},{"content":"Cor:","id":13}]',
            'vistoria' => '[{"content":"Frente","id":1},{"content":"Lateral direita ","id":2},{"content":"Traseira","id":4},{"content":"Lateral esquerda","id":5}]',
        ]);
    }

    public function down(): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM checklist_modelos WHERE chave = '0' AND tipo = 1 AND nome = 'Impresso'");
        $stmt->execute();
    }
};
