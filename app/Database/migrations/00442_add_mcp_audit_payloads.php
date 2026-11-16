<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfNotExists('mcp_audit', 'request_payload', 'LONGTEXT', ['null' => true]);
        $this->addColumnIfNotExists('mcp_audit', 'response_payload', 'LONGTEXT', ['null' => true]);
    }

    public function down(): void
    {
        $this->dropColumnIfExists('mcp_audit', 'response_payload');
        $this->dropColumnIfExists('mcp_audit', 'request_payload');
    }
};
