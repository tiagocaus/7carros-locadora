<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('mcp_security_state')) return;
        $this->create('mcp_security_state', function ($t) {
            $t->id();
            $t->string('chave', 45);
            $t->integer('employee_id')->unsigned();
            $t->text('infractions');
            foreach (['suspended_until', 'hour_bucket', 'day_bucket', 'hour_records', 'day_records', 'hour_sensitive', 'day_sensitive'] as $column) {
                $t->integer($column)->unsigned()->default(0);
            }
            $t->timestamps();
            $t->unique(['chave', 'employee_id']);
        });
    }

    public function down(): void
    {
        $this->drop('mcp_security_state');
    }
};
