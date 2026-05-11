<?php

/**
 * Migration: marca todas as conexoes WhatsApp como desconectadas.
 *
 * Disparada na troca de provedor de WhatsApp: as instancias vinculadas ao
 * provedor anterior nao existem mais no novo, entao cada tenant precisa
 * clicar em "Conectar" e escanear o QR code novamente para reativar.
 */

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->execute("
            UPDATE whatsapp
            SET status = 'disconnected',
                remoteJid = NULL,
                connected_at = NULL,
                updated_at = NOW()
        ");
    }

    public function down(): void
    {
        // Sem rollback significativo: o reset zera estado em runtime,
        // nao ha como restaurar conexoes que ja foram revogadas no provedor.
    }
};
