<?php

namespace App\Models;

use App\Config\NFSe as NFSeConfig;

/**
 * Model NFSeEvento
 *
 * Log de eventos/auditoria das NFS-e.
 * Cada operacao (emissao, cancelamento, consulta, erro, reenvio) gera um registro.
 */
class NFSeEvento extends Model
{
    private const TIPO_REENVIO_MANUAL = 'reenvio_manual';
    private const CODIGO_LIMITE_TECNICO = 'LIMITE_TECNICO';

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

    /**
     * Reserva atomicamente a excecao manual de reenvio para uma NFS-e do tenant.
     */
    public function reservarTentativaExtraManual(int $idNfse, string $chave, string $mensagem): bool
    {
        $mysqli = $this->getMysqli();
        $mysqli->begin_transaction();

        try {
            $stmtNfse = $mysqli->prepare(
                'SELECT id FROM nfse WHERE id = ? AND chave = ? FOR UPDATE'
            );
            $stmtNfse->bind_param('is', $idNfse, $chave);
            $stmtNfse->execute();
            $nfseExiste = $stmtNfse->get_result()->num_rows === 1;
            $stmtNfse->close();

            if (!$nfseExiste) {
                $mysqli->rollback();
                return false;
            }

            $stmtContagem = $mysqli->prepare(
                'SELECT COUNT(*) AS total FROM nfse_eventos'
                . ' WHERE id_nfse = ? AND tipo_evento = ? AND codigo_retorno = ?'
            );
            $tipoEvento = self::TIPO_REENVIO_MANUAL;
            $codigoRetorno = self::CODIGO_LIMITE_TECNICO;
            $stmtContagem->bind_param('iss', $idNfse, $tipoEvento, $codigoRetorno);
            $stmtContagem->execute();
            $total = (int) ($stmtContagem->get_result()->fetch_assoc()['total'] ?? 0);
            $stmtContagem->close();

            if ($total >= NFSeConfig::MAX_ENVIOS_EXTRAS_MANUAIS) {
                $mysqli->rollback();
                return false;
            }

            $stmtEvento = $mysqli->prepare(
                'INSERT INTO nfse_eventos (id_nfse, tipo_evento, codigo_retorno, mensagem)'
                . ' VALUES (?, ?, ?, ?)'
            );
            $stmtEvento->bind_param('isss', $idNfse, $tipoEvento, $codigoRetorno, $mensagem);
            $stmtEvento->execute();
            $stmtEvento->close();

            $mysqli->commit();
            return true;
        } catch (\Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }
}
