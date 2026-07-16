<?php

namespace App\Services;

use App\Helpers\DateHelper;
use App\Models\Promocao;

/**
 * Regra unica de validacao e calculo de codigos promocionais.
 */
class PromocaoAplicacaoService
{
    public const CANAIS = ['SIS', 'SITE', 'APP'];

    public static function normalizarCodigo(?string $codigo): string
    {
        return mb_strtoupper(trim((string) $codigo), 'UTF-8');
    }

    /**
     * @return array{id:int,codigo:string,nome:string,tipo:string,valor_regra:float,valor_desconto:float,total_original:float,total_final:float}
     */
    public function validarECalcular(
        string $codigo,
        int $filialId,
        int $dias,
        float $totalOriginal,
        string $canal,
        int $grupoId = 0
    ): array {
        $codigo = self::normalizarCodigo($codigo);
        $canal = strtoupper(trim($canal));
        $dias = max(0, $dias);
        $totalOriginal = round(max(0, $totalOriginal), 2);

        if ($codigo === '' || mb_strlen($codigo) > 15 || !in_array($canal, self::CANAIS, true)) {
            throw new \InvalidArgumentException('Codigo promocional invalido ou indisponivel.');
        }
        if ($filialId <= 0) {
            throw new \InvalidArgumentException('Selecione a filial de retirada para aplicar a promocao.');
        }
        if ($totalOriginal <= 0) {
            throw new \InvalidArgumentException('A promocao exige uma reserva com valor positivo.');
        }

        $promocao = (new Promocao())->buscarPorCodigoComFilial($codigo, $filialId, $grupoId);
        if (!$promocao || ($promocao['status'] ?? '') !== 'A') {
            throw new \InvalidArgumentException('Codigo promocional invalido ou indisponivel.');
        }

        $canais = array_values(array_filter(array_map(
            static fn(string $item): string => strtoupper(trim($item)),
            explode(',', (string) ($promocao['onde_exibir'] ?? ''))
        )));
        if (!in_array($canal, $canais, true)) {
            throw new \InvalidArgumentException('Codigo promocional invalido ou indisponivel.');
        }

        $validade = trim((string) ($promocao['validade'] ?? ''));
        $hoje = DateHelper::todayForDatabase();
        if ($validade !== '' && $validade !== '0000-00-00' && $validade < $hoje) {
            throw new \InvalidArgumentException('Este codigo promocional expirou.');
        }

        $diasMinimos = max(0, (int) ($promocao['dias'] ?? 0));
        if ($dias < $diasMinimos) {
            throw new \InvalidArgumentException("Esta promocao exige no minimo {$diasMinimos} diaria(s).");
        }

        if (empty($promocao['filial_vinculada'])) {
            throw new \InvalidArgumentException('Este codigo promocional nao esta disponivel para a filial selecionada.');
        }

        if (empty($promocao['todos_grupos'])) {
            if ($grupoId <= 0) {
                throw new \InvalidArgumentException('Selecione o grupo da reserva para aplicar esta promocao.');
            }
            if (empty($promocao['grupo_vinculado'])) {
                throw new \InvalidArgumentException('Este codigo promocional nao esta disponivel para o grupo selecionado.');
            }
        }

        $tipo = strtoupper((string) ($promocao['tipo'] ?? ''));
        if ($tipo === 'DPOR') {
            $valorRegra = (float) ($promocao['valor'] ?? 0);
            if ($valorRegra <= 0 || $valorRegra > 100) {
                throw new \InvalidArgumentException('Codigo promocional invalido ou indisponivel.');
            }
            $desconto = $totalOriginal * ($valorRegra / 100);
        } elseif ($tipo === 'DFIX') {
            $valorRegra = (float) ($promocao['valor_filial'] ?? 0);
            if ($valorRegra <= 0) {
                throw new \InvalidArgumentException('Esta promocao nao possui valor configurado para a filial selecionada.');
            }
            $desconto = $valorRegra;
        } else {
            throw new \InvalidArgumentException('Codigo promocional invalido ou indisponivel.');
        }

        $desconto = round(min($totalOriginal, max(0, $desconto)), 2);

        return [
            'id' => (int) $promocao['id'],
            'codigo' => (string) $promocao['codigo'],
            'nome' => (string) $promocao['nome'],
            'tipo' => $tipo,
            'valor_regra' => round($valorRegra, 2),
            'valor_desconto' => $desconto,
            'total_original' => $totalOriginal,
            'total_final' => round($totalOriginal - $desconto, 2),
        ];
    }
}
