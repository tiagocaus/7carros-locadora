<?php

use App\Database\Migration;

/**
 * Migration: Converter assinaturas PNG para WebP
 *
 * Converte todas as assinaturas existentes (PNG, JPG, GIF) para WebP
 * Economia estimada: ~30% do espaço em disco
 */
return new class extends Migration
{
    private const UPLOAD_BASE = __DIR__ . '/../../../storage/uploads/';

    public function up(): void
    {
        // Buscar assinaturas com extensões não-webp
        $assinaturas = $this->db()
            ->table('assinaturas')
            ->select(['id', 'chave', 'arquivo'])
            ->whereRaw('arquivo NOT LIKE "%.webp" AND arquivo NOT LIKE "%.pdf"')
            ->get();

        $total = count($assinaturas);
        echo "Encontradas {$total} assinaturas para converter\n";

        if ($total === 0) {
            echo "Nenhuma conversão necessária.\n";
            return;
        }

        $convertidas = 0;
        $erros = 0;
        $tamanhoAntes = 0;
        $tamanhoDepois = 0;

        foreach ($assinaturas as $assinatura) {
            try {
                $chave = $assinatura['chave'];
                $arquivoAntigo = $assinatura['arquivo'];

                // Caminho completo do arquivo original
                $uploadDir = self::UPLOAD_BASE . $chave . '/';
                $caminhoAntigo = $uploadDir . $arquivoAntigo;

                // Verificar se arquivo existe
                if (!file_exists($caminhoAntigo)) {
                    echo "  Arquivo não encontrado: {$caminhoAntigo}\n";
                    $erros++;
                    continue;
                }

                // Ler arquivo original
                $imageData = file_get_contents($caminhoAntigo);
                if ($imageData === false) {
                    $erros++;
                    continue;
                }

                $tamanhoAntes += strlen($imageData);

                // Converter para WebP
                $webpData = $this->toWebP($imageData);
                if ($webpData === null) {
                    echo "  Erro ao converter: {$arquivoAntigo}\n";
                    $erros++;
                    continue;
                }

                $tamanhoDepois += strlen($webpData);

                // Gerar novo nome (.webp)
                $nomeBase = pathinfo($arquivoAntigo, PATHINFO_FILENAME);
                $arquivoNovo = $nomeBase . '.webp';
                $caminhoNovo = $uploadDir . $arquivoNovo;

                // Salvar arquivo WebP
                if (file_put_contents($caminhoNovo, $webpData) === false) {
                    $erros++;
                    continue;
                }

                // Calcular hash
                $hash = hash('sha256', $webpData);

                // Atualizar banco
                $this->db()
                    ->table('assinaturas')
                    ->where('id', '=', $assinatura['id'])
                    ->update([
                        'arquivo' => $arquivoNovo,
                        'hash_arquivo' => $hash,
                    ]);

                // Remover arquivo antigo
                if ($caminhoAntigo !== $caminhoNovo) {
                    @unlink($caminhoAntigo);
                }

                $convertidas++;
            } catch (\Exception $e) {
                echo "  Erro na assinatura {$assinatura['id']}: {$e->getMessage()}\n";
                $erros++;
            }
        }

        // Estatísticas
        $economia = $tamanhoAntes > 0 ? round((1 - $tamanhoDepois / $tamanhoAntes) * 100, 1) : 0;
        $tamanhoAntesMB = round($tamanhoAntes / 1024 / 1024, 2);
        $tamanhoDepoisMB = round($tamanhoDepois / 1024 / 1024, 2);

        echo "\n";
        echo "Assinaturas convertidas: {$convertidas}\n";
        echo "Erros: {$erros}\n";
        echo "Tamanho antes: {$tamanhoAntesMB} MB\n";
        echo "Tamanho depois: {$tamanhoDepoisMB} MB\n";
        echo "Economia: {$economia}%\n";
    }

    public function down(): void
    {
        // Não é possível reverter - arquivos originais foram removidos
        echo "Reversão não suportada para esta migration.\n";
    }

    /**
     * Converte imagem para WebP
     */
    private function toWebP(string $imageData, int $quality = 80): ?string
    {
        $image = @imagecreatefromstring($imageData);
        if ($image === false) {
            return null;
        }

        // Preservar transparência
        imagesavealpha($image, true);
        imagealphablending($image, true);

        ob_start();
        imagewebp($image, null, $quality);
        $webpData = ob_get_clean();

        imagedestroy($image);

        return $webpData ?: null;
    }
};
