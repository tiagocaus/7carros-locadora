<?php

namespace App\Models;

/**
 * Model NFSeEvento
 *
 * Log de eventos/auditoria das NFS-e.
 * Cada operacao (emissao, cancelamento, consulta, erro, reenvio) gera um registro.
 */
class NFSeEvento extends Model
{
    /**
     * Registra um evento para uma NFS-e
     */
    public function registrar(
        int $idNfse,
        string $tipoEvento,
        ?string $codigoRetorno = null,
        ?string $mensagem = null,
        ?string $xmlEvento = null
    ): int {
        return $this->qb
            ->table('nfse_eventos')
            ->withoutChave()
            ->insert([
                'id_nfse' => $idNfse,
                'tipo_evento' => $tipoEvento,
                'codigo_retorno' => $codigoRetorno,
                'mensagem' => $mensagem,
                'xml_evento' => $xmlEvento,
            ]);
    }

    /**
     * Lista eventos de uma NFS-e em ordem cronologica
     */
    public function listarPorNfse(int $idNfse): array
    {
        return $this->qb
            ->table('nfse_eventos')
            ->withoutChave()
            ->where('id_nfse', '=', $idNfse)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
}
