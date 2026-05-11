<?php

use App\Database\Migration;

/**
 * Migration: Limpar HTML completo e dados duplicados dos templates i18n de email
 *
 * Os templates de email i18n (en_US, es_ES, it_IT, pt_PT) contêm:
 * 1. HTML completo (DOCTYPE, html, head, style, body) - deveria ser apenas conteúdo
 * 2. Dados duplicados da empresa (contato e footer) que já aparecem no layout base
 *
 * Esta migration extrai apenas o conteúdo relevante, removendo:
 * - Estrutura HTML completa
 * - Seção de contato (📞, ✉️, 🌐)
 * - Footer com dados da empresa
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->pdo->exec("SET NAMES utf8mb4");

        $locales = ['en_US', 'es_ES', 'it_IT', 'pt_PT'];

        foreach ($locales as $locale) {
            echo "  - Limpando templates de email {$locale}...\n";

            $stmt = $this->pdo->prepare("
                SELECT id, content
                FROM message_templates
                WHERE chave = '0'
                  AND locale = ?
                  AND channel = 'email'
                  AND content LIKE '%<!DOCTYPE%'
            ");
            $stmt->execute([$locale]);

            $count = 0;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $cleanContent = $this->cleanEmailContent($row['content']);

                if ($cleanContent !== $row['content']) {
                    $updateStmt = $this->pdo->prepare("
                        UPDATE message_templates
                        SET content = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$cleanContent, $row['id']]);
                    $count++;
                }
            }

            echo "    {$count} templates limpos.\n";
        }

        echo "  - Templates i18n de email limpos com sucesso!\n";
    }

    public function down(): void
    {
        // Não é prático reverter - os templates limpos são melhores
        echo "  - Rollback não implementado.\n";
    }

    /**
     * Limpa o conteúdo de um template de email
     *
     * 1. Extrai conteúdo da div.content
     * 2. Remove seção de contato
     */
    private function cleanEmailContent(string $html): string
    {
        // Se não tem DOCTYPE, já está limpo
        if (strpos($html, '<!DOCTYPE') === false) {
            return $html;
        }

        // Extrair conteúdo da div.content
        $content = $this->extractDivContent($html);

        // Remover seção de contato (Contact:, Contacto:, Contatto:, Contacte:)
        $content = $this->removeContactSection($content);

        return trim($content);
    }

    /**
     * Extrai o conteúdo da div.content
     */
    private function extractDivContent(string $html): string
    {
        // Padrão: <div class="content">...</div>
        if (preg_match('/<div class="content"[^>]*>(.*?)<\/div>\s*<div class="footer"/s', $html, $matches)) {
            return trim($matches[1]);
        }

        // Alternativa: tenta extrair entre content e footer
        if (preg_match('/<div class="content"[^>]*>(.*?)<\/div>/s', $html, $matches)) {
            return trim($matches[1]);
        }

        // Se não conseguiu extrair, retorna original
        return $html;
    }

    /**
     * Remove a seção de contato do template
     *
     * Padrões a remover:
     * - <p>...<strong>Contact:</strong>...{{empresa.telefone}}...{{empresa.email}}...</p>
     * - Variações em outros idiomas: Contacto:, Contatto:, Contacte:
     */
    private function removeContactSection(string $content): string
    {
        // Padrão multilíngue para a seção de contato
        $patterns = [
            // Inglês: Contact:
            '/<p>\s*<strong>Contact:<\/strong>.*?<\/p>/s',
            // Espanhol: Contacto:
            '/<p>\s*<strong>Contacto:<\/strong>.*?<\/p>/s',
            // Italiano: Contatto:
            '/<p>\s*<strong>Contatto:<\/strong>.*?<\/p>/s',
            // Português: Contacte: ou Contato:
            '/<p>\s*<strong>Contacte:<\/strong>.*?<\/p>/s',
            '/<p>\s*<strong>Contato:<\/strong>.*?<\/p>/s',
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        // Limpar espaços extras que podem ter ficado
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return trim($content);
    }
};
