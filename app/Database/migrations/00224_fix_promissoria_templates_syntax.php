<?php

use App\Database\Migration;

/**
 * Migration: Corrigir sintaxe dos templates de promissoria
 *
 * Remove sintaxe Mustache condicional {{#var}}...{{/var}} que nao e suportada
 * pelo TemplateRenderer e substitui por sintaxe simples {{var}}.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar todos os templates
        $templates = $this->db()->table('promissoria_templates')
            ->select(['id', 'content'])
            ->get();

        $updated = 0;
        foreach ($templates as $template) {
            $content = $template['content'];
            $newContent = $this->fixTemplateSyntax($content);

            if ($content !== $newContent) {
                $this->db()->table('promissoria_templates')
                    ->withoutChave()
                    ->where('id', '=', $template['id'])
                    ->update(['content' => $newContent, 'updated_at' => date('Y-m-d H:i:s')]);
                $updated++;
            }
        }

        echo "  - {$updated} templates corrigidos.\n";
    }

    public function down(): void
    {
        echo "  - Nenhuma acao no rollback (templates podem ser restaurados manualmente).\n";
    }

    /**
     * Remove sintaxe condicional Mustache e mantem apenas as variaveis
     */
    private function fixTemplateSyntax(string $content): string
    {
        // Padrao: {{#entidade.campo}}texto com {{entidade.campo}}{{/entidade.campo}}
        // Resultado: texto com {{entidade.campo}}

        // Remove blocos condicionais mantendo o conteudo interno
        $pattern = '/\{\{#([a-z_]+\.[a-z_]+)\}\}(.*?)\{\{\/\1\}\}/is';

        // Aplicar recursivamente ate nao haver mais matches
        $maxIterations = 10;
        $i = 0;
        while (preg_match($pattern, $content) && $i < $maxIterations) {
            $content = preg_replace($pattern, '$2', $content);
            $i++;
        }

        return $content;
    }
};
