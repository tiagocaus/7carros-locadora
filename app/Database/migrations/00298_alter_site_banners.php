<?php

/**
 * Migration: Adicionar colunas ao site_banners
 *
 * Adiciona alt_text, link_url, link_target, idioma, ativo e ordem
 * para suportar acessibilidade, links, multi-idioma e ordenacao.
 */

use App\Database\Migration;

return new class extends Migration
{
    private array $columns = ['alt_text', 'link_url', 'link_target', 'idioma', 'ativo', 'ordem'];

    public function up(): void
    {
        $alterations = [
            'alt_text'    => "ADD COLUMN `alt_text` VARCHAR(255) NULL COMMENT 'Texto alternativo (acessibilidade)' AFTER `mensagem`",
            'link_url'    => "ADD COLUMN `link_url` VARCHAR(500) NULL COMMENT 'URL de destino ao clicar' AFTER `alt_text`",
            'link_target' => "ADD COLUMN `link_target` ENUM('_self','_blank') DEFAULT '_blank' AFTER `link_url`",
            'idioma'      => "ADD COLUMN `idioma` VARCHAR(5) DEFAULT 'pt_BR' AFTER `link_target`",
            'ativo'       => "ADD COLUMN `ativo` TINYINT(1) DEFAULT 1 AFTER `idioma`",
            'ordem'       => "ADD COLUMN `ordem` INT UNSIGNED DEFAULT 0 AFTER `ativo`",
        ];

        foreach ($alterations as $column => $sql) {
            if (!$this->columnExists('site_banners', $column)) {
                $this->execute("ALTER TABLE `site_banners` {$sql}");
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->columns) as $column) {
            $this->dropColumnIfExists('site_banners', $column);
        }
    }
};
