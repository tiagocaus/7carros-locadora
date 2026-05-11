<?php

use App\Database\Migration;

/**
 * Migration: Converter assinaturas base64 para arquivos
 *
 * Converte os registros que ainda têm base64 no campo arquivo
 * para arquivos físicos salvos em storage/uploads/{chave}/
 */
return new class extends Migration
{
    private const UPLOAD_BASE = __DIR__ . '/../../../storage/uploads/';

    public function up(): void
    {
        // Buscar assinaturas que ainda têm base64
        $assinaturas = $this->db()
            ->table('assinaturas')
            ->select(['id', 'chave', 'arquivo'])
            ->whereRaw('arquivo LIKE "data:image%"')
            ->get();

        $total = count($assinaturas);
        echo "Encontradas {$total} assinaturas em base64\n";

        if ($total === 0) {
            echo "Nenhuma conversão necessária.\n";
            return;
        }

        $convertidas = 0;
        $erros = 0;

        foreach ($assinaturas as $assinatura) {
            try {
                $base64 = $assinatura['arquivo'];
                $chave = $assinatura['chave'];

                // Detectar extensão do base64
                $extension = $this->detectExtension($base64);
                if (!$extension) {
                    $erros++;
                    continue;
                }

                // Remover prefixo data URL
                $data = preg_replace('/^data:[a-z0-9\/+\-]+;base64,/i', '', $base64);
                $data = base64_decode($data);

                if ($data === false) {
                    $erros++;
                    continue;
                }

                // Criar diretório se não existir
                $uploadDir = self::UPLOAD_BASE . $chave . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Gerar nome único para o arquivo
                $filename = 'assinatura_' . $assinatura['id'] . '_' . uniqid() . '.' . $extension;
                $filepath = $uploadDir . $filename;

                // Salvar arquivo
                if (file_put_contents($filepath, $data) === false) {
                    $erros++;
                    continue;
                }

                // Calcular hash do arquivo
                $hash = hash('sha256', $data);

                // Atualizar registro com o nome do arquivo (não o path completo)
                $this->db()
                    ->table('assinaturas')
                    ->where('id', '=', $assinatura['id'])
                    ->update([
                        'arquivo' => $filename,
                        'hash_arquivo' => $hash,
                    ]);

                $convertidas++;
            } catch (\Exception $e) {
                echo "Erro na assinatura {$assinatura['id']}: {$e->getMessage()}\n";
                $erros++;
            }
        }

        echo "Assinaturas convertidas: {$convertidas}\n";
        echo "Erros: {$erros}\n";
    }

    public function down(): void
    {
        // Não é possível reverter - os arquivos já foram criados
        // e não temos mais o base64 original
        echo "Reversão não suportada para esta migration.\n";
    }

    /**
     * Detecta extensão a partir do base64
     */
    private function detectExtension(string $base64): ?string
    {
        $patterns = [
            'png' => '/^data:image\/png/i',
            'jpg' => '/^data:image\/jpe?g/i',
            'gif' => '/^data:image\/gif/i',
            'webp' => '/^data:image\/webp/i',
            'svg' => '/^data:image\/svg\+xml/i',
        ];

        foreach ($patterns as $ext => $pattern) {
            if (preg_match($pattern, $base64)) {
                return $ext;
            }
        }

        return null;
    }
};
