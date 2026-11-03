#!/usr/bin/env php
<?php

/**
 * Manutencao pontual dos dois recebimentos da BRAVIK diagnosticados em 03/11/2026.
 * Executar na raiz do servidor, usando .env.production com DB_HOST=localhost:
 * php scripts/reparar-veiculos-financeiro-bravik.php --env=production
 * php scripts/reparar-veiculos-financeiro-bravik.php --env=production --apply
 * Nao usa temp-bd.txt, nao executa migrations e nao altera valores ou pagamentos.
 */

require_once __DIR__ . '/../vendor/autoload.php';
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/app/Helpers/helpers.php';

use App\Core\Database;
use App\Models\Model;
use App\Services\AuditLogService;

/** Retorna a previa ou aplica o reparo inteiro com auditoria na mesma transacao. */
function repararVeiculosFinanceiroBravik(mysqli $db, bool $aplicar = false): array
{
    $chave = '09B69ADCD7CBAE914C6204984097D2DA';
    $alvos = [
        [1088070, 161525, 'LGP4ED2N', 20365, 'OLN-6F49'],
        [1088065, 161530, 'LZQOAML7', 19965, 'OTK-6669'],
    ];
    $sessaoAnterior = $_SESSION ?? [];
    $resultado = [];
    $db->begin_transaction($aplicar ? 0 : MYSQLI_TRANS_START_READ_ONLY);
    try {
        $lock = $aplicar ? ' FOR UPDATE' : '';
        foreach ($alvos as [$id, $locacaoId, $codigo, $veiculoId, $placa]) {
            $stmt = $db->prepare('SELECT * FROM financeiro WHERE chave = ? AND id = ?' . $lock);
            $stmt->execute([$chave, $id]);
            $financeiro = $stmt->get_result()->fetch_assoc();
            if (!$financeiro || (int) $financeiro['id_locacao'] !== $locacaoId
                || $financeiro['tipo'] !== 'R' || $financeiro['pago'] !== 'S'
                || $financeiro['valor_total'] !== '500.00'
                || ($financeiro['id_veiculo'] !== null && (int) $financeiro['id_veiculo'] !== $veiculoId)) {
                throw new RuntimeException("Financeiro #{$id} diverge do diagnostico; operacao interrompida.");
            }

            $stmt = $db->prepare('SELECT codigo, status FROM locacoes WHERE chave = ? AND id = ?' . $lock);
            $stmt->execute([$chave, $locacaoId]);
            $locacao = $stmt->get_result()->fetch_assoc();
            if (!$locacao || $locacao['codigo'] !== $codigo || $locacao['status'] !== 'F') {
                throw new RuntimeException("Locacao {$codigo} diverge do diagnostico.");
            }

            // O diagnostico comprovou um unico vinculo em cada locacao. Nao
            // inferir automaticamente a distribuicao se o historico mudou.
            $stmt = $db->prepare('SELECT lv.id_veiculo, lv.data_entrada, v.placa
                FROM locacoes_veiculos lv
                LEFT JOIN veiculos v ON v.id = lv.id_veiculo AND v.chave = lv.chave
                WHERE lv.chave = ? AND lv.id_locacao = ?' . $lock);
            $stmt->execute([$chave, $locacaoId]);
            $historico = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if (count($historico) !== 1 || (int) $historico[0]['id_veiculo'] !== $veiculoId
                || $historico[0]['placa'] !== $placa || empty($historico[0]['data_entrada'])) {
                throw new RuntimeException("Historico de veiculo de {$codigo} diverge do diagnostico.");
            }

            $stmt = $db->prepare('SELECT id FROM financeiro_itens WHERE id_financeiro = ?' . $lock);
            $stmt->execute([$id]);
            if ($stmt->get_result()->num_rows > 0) {
                throw new RuntimeException("Financeiro #{$id} passou a possuir itens; revisar manualmente.");
            }
            $resultado[] = [
                'financeiro' => $id, 'locacao' => $codigo, 'veiculo' => $veiculoId,
                'placa' => $placa, 'anterior' => $financeiro['id_veiculo'],
                'situacao' => $financeiro['id_veiculo'] === null ? 'corrigir' : 'ja corrigido',
            ];
        }

        if ($aplicar) {
            $_SESSION = ['chave' => $chave, 'user_id' => 0];
            $stmt = $db->prepare('UPDATE financeiro SET id_veiculo = ?, updated_at = updated_at
                WHERE chave = ? AND id = ? AND id_veiculo IS NULL');
            foreach ($resultado as &$alvo) {
                if ($alvo['situacao'] === 'ja corrigido') {
                    continue;
                }
                $stmt->execute([$alvo['veiculo'], $chave, $alvo['financeiro']]);
                if ($stmt->affected_rows !== 1) {
                    throw new RuntimeException('Financeiro mudou durante o reparo.');
                }
                AuditLogService::registrarComCamposNaTransacao(
                    $db,
                    "Manutencao BRAVIK, corrigiu veiculo do financeiro #{$alvo['financeiro']} da locacao [{$alvo['locacao']}]",
                    [
                        AuditLogService::campo('id_veiculo', null, $alvo['veiculo'], 'Financeiro'),
                        AuditLogService::campo('Motivo', null, 'Parcela recriada apos encerramento sem vinculo veicular', 'Financeiro'),
                    ]
                );
                $alvo['situacao'] = 'corrigido';
            }
            unset($alvo);
            $db->commit();
        } else {
            $db->rollback();
        }
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $_SESSION = $sessaoAnterior;
    }
    return $resultado;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $opcoes = getopt('', ['env:', 'apply']);
        $ambiente = $opcoes['env'] ?? '';
        if (!in_array($ambiente, ['development', 'production'], true)
            || !is_file(APP_ROOT . '/.env.' . $ambiente)) {
            throw new RuntimeException('Informe --env=development ou --env=production com arquivo de ambiente existente.');
        }
        $_ENV['APP_ENV'] = $ambiente;
        if (Database::env('DB_HOST') !== 'localhost') {
            throw new RuntimeException('A manutencao exige DB_HOST=localhost no ambiente selecionado.');
        }
        $aplicar = array_key_exists('apply', $opcoes);
        $resultado = repararVeiculosFinanceiroBravik(Model::sharedMysqli(), $aplicar);
        echo $aplicar ? "APLICACAO concluida.\n" : "PREVIA: nenhum dado alterado.\n";
        foreach ($resultado as $alvo) {
            echo "Financeiro #{$alvo['financeiro']} / {$alvo['locacao']} / {$alvo['placa']}: {$alvo['situacao']}\n";
        }
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}
