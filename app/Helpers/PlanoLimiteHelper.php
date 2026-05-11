<?php

namespace App\Helpers;

use App\Config\Planos;
use App\Core\Auth;
use App\Models\Veiculo;
use App\Models\MatrizFilial;
use App\Models\Whatsapp;
use App\Models\Sms;
use App\Models\Smtp;

/**
 * Plano Limite Helper
 *
 * Helper para validar limites de recursos baseado no plano do tenant.
 * Os limites são sempre obtidos dinamicamente de Planos::PLANOS.
 */
class PlanoLimiteHelper
{
    /**
     * Recursos válidos e seus mapeamentos
     */
    private const RECURSOS = [
        'veiculos' => [
            'indice' => 'veiculos',
            'label' => 'veículos',
            'label_singular' => 'veículo'
        ],
        'matrizfilial' => [
            'indice' => 'matrizfilial',
            'label' => 'matrizes/filiais',
            'label_singular' => 'matriz/filial'
        ],
        'whatsapp' => [
            'indice' => 'whatsapp',
            'label' => 'conexões WhatsApp',
            'label_singular' => 'conexão WhatsApp'
        ],
        'sms' => [
            'indice' => 'sms',
            'label' => 'conexões SMS',
            'label_singular' => 'conexão SMS'
        ],
        'smtp' => [
            'indice' => 'smtp',
            'label' => 'conexões SMTP',
            'label_singular' => 'conexão SMTP'
        ]
    ];

    /**
     * Verifica se o tenant pode adicionar mais registros de um recurso
     *
     * @param string $recurso Nome do recurso (veiculos, matrizfilial, whatsapp, sms, smtp)
     * @return bool true se pode adicionar, false se limite atingido
     */
    public static function podeAdicionar(string $recurso): bool
    {
        $usage = self::getUsage($recurso);
        return $usage['atual'] < $usage['limite'];
    }

    /**
     * Retorna informações de uso do recurso (atual vs limite)
     *
     * @param string $recurso Nome do recurso
     * @return array ['atual' => int, 'limite' => int, 'plano' => string, 'plano_codigo' => string]
     */
    public static function getUsage(string $recurso): array
    {
        self::validarRecurso($recurso);

        $user = Auth::user();
        $planoCodigo = $user['plano'] ?? 'G';
        $configPlano = Planos::getPlano($planoCodigo);

        // Se plano não existe, usa gratuito como fallback
        if (!$configPlano) {
            $planoCodigo = 'G';
            $configPlano = Planos::getPlano('G');
        }

        $indice = self::RECURSOS[$recurso]['indice'];
        $limite = $configPlano[$indice] ?? 0;
        $atual = self::contarRegistros($recurso);

        return [
            'atual' => $atual,
            'limite' => $limite,
            'plano' => $configPlano['plano_nome'] ?? $planoCodigo,
            'plano_codigo' => $planoCodigo
        ];
    }

    /**
     * Retorna URL de redirecionamento se limite atingido, ou null se pode adicionar
     *
     * @param string $recurso Nome do recurso
     * @return string|null URL de redirect ou null
     */
    public static function getRedirectSeAtingido(string $recurso): ?string
    {
        if (self::podeAdicionar($recurso)) {
            return null;
        }

        $usage = self::getUsage($recurso);
        $label = self::RECURSOS[$recurso]['label'] ?? $recurso;

        return '/pages/limite-atingido?' . http_build_query([
            'recurso' => $recurso,
            'label' => $label,
            'limite' => $usage['limite'],
            'plano' => $usage['plano']
        ]);
    }

    /**
     * Retorna dados completos para resposta de API
     *
     * @param string $recurso Nome do recurso
     * @return array Dados para JSON response
     */
    public static function getApiResponse(string $recurso): array
    {
        $usage = self::getUsage($recurso);
        $podeAdicionar = $usage['atual'] < $usage['limite'];
        $label = self::RECURSOS[$recurso]['label'] ?? $recurso;

        return [
            'pode_adicionar' => $podeAdicionar,
            'atual' => $usage['atual'],
            'limite' => $usage['limite'],
            'plano' => $usage['plano'],
            'plano_codigo' => $usage['plano_codigo'],
            'recurso' => $recurso,
            'label' => $label,
            'redirect_url' => $podeAdicionar ? null : '/pages/limite-atingido?' . http_build_query([
                'recurso' => $recurso,
                'label' => $label,
                'limite' => $usage['limite'],
                'plano' => $usage['plano']
            ])
        ];
    }

    /**
     * Conta registros atuais do recurso para o tenant
     *
     * @param string $recurso Nome do recurso
     * @return int Quantidade atual
     */
    private static function contarRegistros(string $recurso): int
    {
        $chave = Auth::chave();

        switch ($recurso) {
            case 'veiculos':
                return (new Veiculo())->contarParaPlano($chave);

            case 'matrizfilial':
                return (new MatrizFilial())->contar();

            case 'whatsapp':
                return (new Whatsapp())->contar();

            case 'sms':
                return (new Sms())->contar();

            case 'smtp':
                return (new Smtp())->contar();

            default:
                return 0;
        }
    }

    /**
     * Valida se o recurso é válido
     *
     * @param string $recurso Nome do recurso
     * @throws \InvalidArgumentException Se recurso inválido
     */
    private static function validarRecurso(string $recurso): void
    {
        if (!isset(self::RECURSOS[$recurso])) {
            throw new \InvalidArgumentException(
                "Recurso '{$recurso}' inválido. Recursos válidos: " . implode(', ', array_keys(self::RECURSOS))
            );
        }
    }

    /**
     * Retorna label amigável do recurso
     *
     * @param string $recurso Nome do recurso
     * @return string Label
     */
    public static function getLabel(string $recurso): string
    {
        return self::RECURSOS[$recurso]['label'] ?? $recurso;
    }

    /**
     * Retorna label singular do recurso
     *
     * @param string $recurso Nome do recurso
     * @return string Label singular
     */
    public static function getLabelSingular(string $recurso): string
    {
        return self::RECURSOS[$recurso]['label_singular'] ?? $recurso;
    }
}
