<?php

namespace App\Config;

class WebsiteThemes
{
    public const PRESETS = [
        'azul' => [
            '--cor-1'  => '#06858a',
            '--cor-2'  => '#10ffc8',
            '--cor-3'  => '#069da2',
            '--cor-4'  => '#007254',
            '--cor-5'  => '#04a482',
            '--cor-6'  => '#079fa1',
            '--cor-7'  => '#0062cc1a',
            '--cor-8'  => '#ede500',
            '--cor-9'  => '#ffc105',
            '--cor-10' => '#555',
        ],
        'vermelho' => [
            '--cor-1'  => '#8a0606',
            '--cor-2'  => '#ff1010',
            '--cor-3'  => '#a20606',
            '--cor-4'  => '#720007',
            '--cor-5'  => '#a40404',
            '--cor-6'  => '#a10707',
            '--cor-7'  => '#cc00001a',
            '--cor-8'  => '#ede500',
            '--cor-9'  => '#ffc105',
            '--cor-10' => '#555',
        ],
        'verde' => [
            '--cor-1'  => '#068a1e',
            '--cor-2'  => '#10ff6e',
            '--cor-3'  => '#06a22e',
            '--cor-4'  => '#005a07',
            '--cor-5'  => '#04a43a',
            '--cor-6'  => '#07a13b',
            '--cor-7'  => '#00cc1a1a',
            '--cor-8'  => '#ede500',
            '--cor-9'  => '#ffc105',
            '--cor-10' => '#555',
        ],
        'preto' => [
            '--cor-1'  => '#333333',
            '--cor-2'  => '#666666',
            '--cor-3'  => '#444444',
            '--cor-4'  => '#1a1a1a',
            '--cor-5'  => '#555555',
            '--cor-6'  => '#4a4a4a',
            '--cor-7'  => '#0000001a',
            '--cor-8'  => '#ede500',
            '--cor-9'  => '#ffc105',
            '--cor-10' => '#555',
        ],
    ];

    /**
     * Retorna as cores de um preset fixo ou null se nao existir
     */
    public static function getPreset(string $nome): ?array
    {
        return self::PRESETS[$nome] ?? null;
    }

    /**
     * Verifica se um nome eh um preset fixo do sistema
     */
    public static function isPresetFixo(string $nome): bool
    {
        return isset(self::PRESETS[$nome]);
    }

    /**
     * Retorna todos os nomes de presets fixos
     */
    public static function getPresetsFixos(): array
    {
        return array_keys(self::PRESETS);
    }
}
