<?php

namespace App\Helpers;

use App\Classes\QueryBuilder;
use App\Core\Database;

/**
 * Helper centralizado para gerenciamento de sequencias numericas
 *
 * Cada matriz/filial tem suas proprias sequencias independentes:
 * - sequencia_locacoes
 * - sequencia_contratos
 * - sequencia_financeiro
 */
class SequenciaHelper
{
    private const TIPOS_VALIDOS = ['locacoes', 'contratos', 'financeiro'];

    /**
     * Obtem conexao mysqli singleton para uso no QueryBuilder
     */
    private static function getMysqliConnection(): \mysqli
    {
        static $mysqli = null;

        if ($mysqli === null || !$mysqli->ping()) {
            $host = Database::env('DB_HOST', 'localhost');
            $username = Database::env('DB_USERNAME');
            $password = Database::env('DB_PASSWORD');
            $database = Database::env('DB_DATABASE');
            $port = (int) Database::env('DB_PORT', '3306');

            $mysqli = new \mysqli($host, $username, $password, $database, $port);

            if ($mysqli->connect_error) {
                throw new \RuntimeException('Erro ao conectar com o banco de dados: ' . $mysqli->connect_error);
            }

            $mysqli->set_charset('utf8mb4');
        }

        return $mysqli;
    }

    /**
     * Gera proximo numero sequencial de forma atomica (thread-safe)
     *
     * @param string $chave Identificador do tenant
     * @param int $idMatrizFilial ID da matriz/filial
     * @param string $tipo Tipo: 'locacoes' | 'contratos' | 'financeiro'
     * @return int Proximo numero sequencial
     * @throws \RuntimeException Se matriz/filial nao for encontrada
     * @throws \InvalidArgumentException Se tipo for invalido
     */
    public static function proximaSequencia(string $chave, int $idMatrizFilial, string $tipo): int
    {
        return self::proximasSequencias($chave, $idMatrizFilial, $tipo, 1)[0];
    }

    /**
     * Reserva multiplos numeros sequenciais de forma atomica (thread-safe).
     *
     * Usa um unico lock em matrizes_filiais para reduzir contencao quando
     * fluxos geram muitas parcelas financeiras de uma vez.
     *
     * @param string $chave Identificador do tenant
     * @param int $idMatrizFilial ID da matriz/filial
     * @param string $tipo Tipo: 'locacoes' | 'contratos' | 'financeiro'
     * @param int $quantidade Quantidade de numeros a reservar
     * @return array<int,int> Sequencias reservadas em ordem crescente
     * @throws \RuntimeException Se matriz/filial nao for encontrada
     * @throws \InvalidArgumentException Se tipo ou quantidade forem invalidos
     */
    public static function proximasSequencias(string $chave, int $idMatrizFilial, string $tipo, int $quantidade): array
    {
        // Validar tipo
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new \InvalidArgumentException("Tipo de sequencia invalido: {$tipo}. Tipos validos: " . implode(', ', self::TIPOS_VALIDOS));
        }

        if ($quantidade < 1) {
            throw new \InvalidArgumentException('Quantidade de sequencias deve ser maior que zero');
        }

        $coluna = "sequencia_{$tipo}";
        $qb = new QueryBuilder(self::getMysqliConnection());

        $qb->beginTransaction();
        try {
            // Buscar valor atual com lock (SELECT FOR UPDATE)
            // Isso garante que nenhuma outra transacao leia/modifique ate o commit
            $mysqli = $qb->getMysqli();
            $stmt = $mysqli->prepare("SELECT {$coluna} FROM matrizes_filiais WHERE id = ? AND chave = ? FOR UPDATE");
            $stmt->bind_param('is', $idMatrizFilial, $chave);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$result) {
                throw new \RuntimeException("Matriz/Filial nao encontrada: id={$idMatrizFilial}, chave={$chave}");
            }

            $sequenciaAtual = (int) ($result[$coluna] ?? 0);
            $primeiroNumero = $sequenciaAtual + 1;
            $ultimoNumero = $sequenciaAtual + $quantidade;

            // Incrementar contador na tabela
            $qb->table('matrizes_filiais')
                ->withoutChave()
                ->where('id', '=', $idMatrizFilial)
                ->where('chave', '=', $chave)
                ->update([$coluna => $ultimoNumero]);

            $qb->commit();

            return range($primeiroNumero, $ultimoNumero);
        } catch (\Exception $e) {
            $qb->rollback();
            throw $e;
        }
    }

    /**
     * Retorna o valor atual da sequencia sem incrementar
     *
     * @param string $chave Identificador do tenant
     * @param int $idMatrizFilial ID da matriz/filial
     * @param string $tipo Tipo: 'locacoes' | 'contratos' | 'financeiro'
     * @return int Valor atual da sequencia
     */
    public static function sequenciaAtual(string $chave, int $idMatrizFilial, string $tipo): int
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            throw new \InvalidArgumentException("Tipo de sequencia invalido: {$tipo}");
        }

        $coluna = "sequencia_{$tipo}";
        $qb = new QueryBuilder(self::getMysqliConnection());

        $result = $qb->table('matrizes_filiais')
            ->withoutChave()
            ->where('id', '=', $idMatrizFilial)
            ->where('chave', '=', $chave)
            ->first();

        return (int) ($result[$coluna] ?? 0);
    }

    /**
     * Renumera todos os registros de um tipo especifico para uma matriz/filial
     * Atribui sequencias a partir do valor inicial ordenadas por data de criacao
     *
     * @param string $chave Identificador do tenant
     * @param int $idMatrizFilial ID da matriz/filial
     * @param string $tipo 'locacoes' | 'contratos' | 'financeiro'
     * @param int $valorInicial Valor inicial da sequencia (ex: 1, 50, 100)
     * @return int Quantidade de registros renumerados
     * @throws \InvalidArgumentException Se tipo for invalido
     */
    public static function renumerarSequencias(string $chave, int $idMatrizFilial, string $tipo, int $valorInicial = 1): int
    {
        $tiposValidos = ['locacoes', 'contratos', 'financeiro'];
        if (!in_array($tipo, $tiposValidos, true)) {
            throw new \InvalidArgumentException("Tipo de sequencia invalido: {$tipo}");
        }

        $tabela = $tipo; // 'financeiro', 'locacoes', 'contratos'
        $coluna = "sequencia_{$tipo}";

        // Mapear coluna correta por tipo (locacoes/contratos usam id_matriz_filial_retirada)
        $colunaMatriz = match($tipo) {
            'locacoes', 'contratos' => 'id_matriz_filial_retirada',
            'financeiro' => 'id_matriz_filial',
            default => 'id_matriz_filial'
        };

        $qb = new QueryBuilder(self::getMysqliConnection());

        $qb->beginTransaction();
        try {
            // Buscar todos os registros dessa matriz/filial ordenados por data de criacao
            $mysqli = $qb->getMysqli();
            $stmt = $mysqli->prepare(
                "SELECT id FROM {$tabela} WHERE chave = ? AND {$colunaMatriz} = ? ORDER BY created_at ASC, id ASC"
            );
            $stmt->bind_param('si', $chave, $idMatrizFilial);
            $stmt->execute();
            $result = $stmt->get_result();

            $registros = [];
            while ($row = $result->fetch_assoc()) {
                $registros[] = $row['id'];
            }
            $stmt->close();

            // Renumerar cada registro a partir do valor inicial
            $sequencia = $valorInicial;
            foreach ($registros as $id) {
                $stmtUpdate = $mysqli->prepare("UPDATE {$tabela} SET sequencia = ? WHERE id = ?");
                $stmtUpdate->bind_param('ii', $sequencia, $id);
                $stmtUpdate->execute();
                $stmtUpdate->close();
                $sequencia++;
            }

            // Atualizar contador na matriz para o proximo numero (valor inicial + total)
            $proximoNumero = $valorInicial + count($registros);
            $qb->table('matrizes_filiais')
                ->withoutChave()
                ->where('id', '=', $idMatrizFilial)
                ->update([$coluna => $proximoNumero]);

            $qb->commit();

            return count($registros);
        } catch (\Exception $e) {
            $qb->rollback();
            throw $e;
        }
    }
}
