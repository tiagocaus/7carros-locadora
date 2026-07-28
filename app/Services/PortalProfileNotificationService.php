<?php

namespace App\Services;

use App\Models\Funcionario;

class PortalProfileNotificationService
{
    public const PERMISSION = 'notificacoes.alteracoes_portal';

    public function notificar(
        string $chave,
        int $filialId,
        string $perfil,
        string $nome,
        array $campos
    ): array {
        if ($campos === []) {
            return ['destinatarios' => 0, 'enfileiradas' => 0, 'falhas' => 0];
        }

        $funcionarios = (new Funcionario())->listarAtivosComPermissaoNaFilial(
            self::PERMISSION,
            $filialId
        );
        $destinatarios = [];
        $enfileiradas = 0;
        $falhas = 0;
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $linhasHtml = '';
        $linhasTexto = '';
        foreach ($campos as $campo) {
            $label = (string) ($campo['campo'] ?? '');
            $de = (string) ($campo['de'] ?? '');
            $para = (string) ($campo['para'] ?? '');
            $linhasHtml .= '<tr><td>' . $escape($label) . '</td><td>'
                . $escape($de) . '</td><td>' . $escape($para) . '</td></tr>';
            $linhasTexto .= "{$label}: {$de} -> {$para}\n";
        }

        foreach ($funcionarios as $funcionario) {
            $email = trim((string) ($funcionario['email'] ?? ''));
            $normalizado = mb_strtolower($email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($destinatarios[$normalizado])) {
                continue;
            }
            $destinatarios[$normalizado] = true;

            try {
                queue_message('email', [
                    'to' => $email,
                    'to_name' => (string) ($funcionario['nome'] ?? ''),
                    'subject' => "Dados alterados no portal - {$nome}",
                    'body' => '<h2>Alteracao cadastral no portal</h2>'
                        . '<p><strong>Perfil:</strong> ' . $escape(ucfirst($perfil)) . '</p>'
                        . '<p><strong>Cadastro:</strong> ' . $escape($nome) . '</p>'
                        . '<table cellpadding="6" cellspacing="0" border="1">'
                        . '<thead><tr><th>Campo</th><th>De</th><th>Para</th></tr></thead>'
                        . '<tbody>' . $linhasHtml . '</tbody></table>',
                    'body_text' => "Alteracao cadastral no portal\nPerfil: {$perfil}\nCadastro: {$nome}\n{$linhasTexto}",
                    'id_matriz_filial' => $filialId,
                ], $chave);
                $enfileiradas++;
            } catch (\Throwable $e) {
                $falhas++;
                error_log('[Portal] Falha ao notificar alteracao cadastral: ' . $e->getMessage());
            }
        }

        return [
            'destinatarios' => count($destinatarios),
            'enfileiradas' => $enfileiradas,
            'falhas' => $falhas,
        ];
    }
}
