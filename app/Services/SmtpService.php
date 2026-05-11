<?php

namespace App\Services;

use App\Models\Smtp;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Service para operacoes SMTP
 *
 * Fornece metodos para testar conexoes SMTP e enviar emails
 * usando configuracoes dinamicas do banco de dados.
 */
class SmtpService
{
    /**
     * Testa conexao SMTP sem enviar email
     *
     * @param array $config Configuracao SMTP:
     *   - 'host': Servidor SMTP
     *   - 'port': Porta
     *   - 'encryption': tls, ssl ou none
     *   - 'username': Usuario/email
     *   - 'password': Senha (texto plano)
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(array $config): array
    {
        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->SMTPAuth = true;
            $mailer->Host = $config['host'];
            $mailer->Port = (int) $config['port'];
            $mailer->Username = $config['username'];
            $mailer->Password = $config['password'];

            // Configurar criptografia
            if ($config['encryption'] === 'none') {
                $mailer->SMTPSecure = '';
                $mailer->SMTPAutoTLS = false;
            } else {
                $mailer->SMTPSecure = $config['encryption'];
            }

            // Timeout de conexao
            $mailer->Timeout = 10;

            // Tenta conectar
            $connected = $mailer->smtpConnect();

            if ($connected) {
                $mailer->smtpClose();
                return [
                    'success' => true,
                    'message' => 'Conexao SMTP estabelecida com sucesso',
                ];
            }

            return [
                'success' => false,
                'message' => 'Nao foi possivel conectar ao servidor SMTP',
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $this->formatSmtpError($e->getMessage()),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $this->formatSmtpError($e->getMessage()),
            ];
        }
    }

    /**
     * Envia email de teste
     *
     * @param array $config Configuracao SMTP (mesma do testConnection)
     * @param string $toEmail Email destinatario
     * @param string $fromEmail Email remetente
     * @param string $fromName Nome remetente
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendTestEmail(array $config, string $toEmail, string $fromEmail, string $fromName): array
    {
        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->SMTPAuth = true;
            $mailer->Host = $config['host'];
            $mailer->Port = (int) $config['port'];
            $mailer->Username = $config['username'];
            $mailer->Password = $config['password'];
            $mailer->CharSet = 'UTF-8';

            // Configurar criptografia
            if ($config['encryption'] === 'none') {
                $mailer->SMTPSecure = '';
                $mailer->SMTPAutoTLS = false;
            } else {
                $mailer->SMTPSecure = $config['encryption'];
            }

            // Remetente
            $mailer->setFrom($fromEmail, $fromName);

            // Destinatario
            $mailer->addAddress($toEmail);

            // Conteudo
            $mailer->isHTML(true);
            $mailer->Subject = 'Teste de Conexao SMTP - 7Carros Locadora';
            $mailer->Body = $this->getTestEmailHtml($fromName);
            $mailer->AltBody = "Este e um email de teste enviado pelo sistema 7Carros Locadora para validar a configuracao SMTP de {$fromName}.";

            // Envia
            $mailer->send();

            return [
                'success' => true,
                'message' => "Email de teste enviado com sucesso para {$toEmail}",
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $this->formatSmtpError($e->getMessage()),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $this->formatSmtpError($e->getMessage()),
            ];
        }
    }

    /**
     * Envia email usando conexao SMTP configurada para uma filial
     *
     * @param array $payload Dados do email:
     *   - 'to': Email destinatario (obrigatorio)
     *   - 'to_name': Nome destinatario (opcional)
     *   - 'subject': Assunto (obrigatorio)
     *   - 'body': Corpo HTML (obrigatorio)
     *   - 'body_text': Corpo texto (opcional)
     *   - 'id_matriz_filial': ID da filial (obrigatorio)
     *   - 'cc': Array de emails em copia (opcional)
     *   - 'bcc': Array de emails em copia oculta (opcional)
     *   - 'attachments': Array de anexos (opcional)
     * @return array ['success' => bool, 'message' => string]
     */
    public function send(array $payload): array
    {
        try {
            // Valida dados obrigatorios
            if (empty($payload['to'])) {
                throw new \InvalidArgumentException("Campo 'to' e obrigatorio");
            }
            if (empty($payload['subject'])) {
                throw new \InvalidArgumentException("Campo 'subject' e obrigatorio");
            }
            if (empty($payload['body'])) {
                throw new \InvalidArgumentException("Campo 'body' e obrigatorio");
            }
            if (empty($payload['id_matriz_filial'])) {
                throw new \InvalidArgumentException("Campo 'id_matriz_filial' e obrigatorio");
            }

            // Busca conexao SMTP validada da filial
            $smtpModel = new Smtp();
            $smtpConfig = $smtpModel->buscarValidadaPorFilial((int) $payload['id_matriz_filial']);

            if (!$smtpConfig) {
                throw new \RuntimeException('Nenhuma conexao SMTP validada encontrada para esta filial');
            }

            // Descriptografa senha
            $password = decrypt($smtpConfig['password']);

            // Configura PHPMailer
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->SMTPAuth = true;
            $mailer->Host = $smtpConfig['host'];
            $mailer->Port = (int) $smtpConfig['port'];
            $mailer->Username = $smtpConfig['username'];
            $mailer->Password = $password;
            $mailer->CharSet = 'UTF-8';

            // Configurar criptografia
            if ($smtpConfig['encryption'] === 'none') {
                $mailer->SMTPSecure = '';
                $mailer->SMTPAutoTLS = false;
            } else {
                $mailer->SMTPSecure = $smtpConfig['encryption'];
            }

            // Remetente
            $mailer->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);

            // Reply-To
            if (!empty($smtpConfig['reply_to_email'])) {
                $mailer->addReplyTo(
                    $smtpConfig['reply_to_email'],
                    $smtpConfig['reply_to_name'] ?? ''
                );
            }

            // Destinatario principal
            $mailer->addAddress($payload['to'], $payload['to_name'] ?? '');

            // Copias (CC)
            if (!empty($payload['cc']) && is_array($payload['cc'])) {
                foreach ($payload['cc'] as $cc) {
                    if (is_array($cc)) {
                        $mailer->addCC($cc['email'], $cc['name'] ?? '');
                    } else {
                        $mailer->addCC($cc);
                    }
                }
            }

            // Copias ocultas (BCC)
            if (!empty($payload['bcc']) && is_array($payload['bcc'])) {
                foreach ($payload['bcc'] as $bcc) {
                    if (is_array($bcc)) {
                        $mailer->addBCC($bcc['email'], $bcc['name'] ?? '');
                    } else {
                        $mailer->addBCC($bcc);
                    }
                }
            }

            // Anexos
            if (!empty($payload['attachments']) && is_array($payload['attachments'])) {
                foreach ($payload['attachments'] as $attachment) {
                    if (is_array($attachment)) {
                        $path = $attachment['path'] ?? $attachment[0];
                        $name = $attachment['name'] ?? basename($path);
                        $mailer->addAttachment($path, $name);
                    } else {
                        $mailer->addAttachment($attachment);
                    }
                }
            }

            // Conteudo
            $mailer->isHTML(true);
            $mailer->Subject = $payload['subject'];
            $mailer->Body = $payload['body'];

            if (!empty($payload['body_text'])) {
                $mailer->AltBody = $payload['body_text'];
            }

            // Envia
            $mailer->send();

            return [
                'success' => true,
                'message' => 'Email enviado com sucesso',
            ];

        } catch (Exception $e) {
            error_log("Erro SMTP ao enviar email: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $this->formatSmtpError($e->getMessage()),
            ];
        } catch (\Exception $e) {
            error_log("Erro ao processar email: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Formata mensagem de erro SMTP para exibicao amigavel
     *
     * @param string $error Mensagem de erro original
     * @return string Mensagem formatada
     */
    private function formatSmtpError(string $error): string
    {
        // Erros comuns e mensagens amigaveis
        $errorMappings = [
            'Authentication failed' => 'Falha na autenticacao. Verifique usuario e senha.',
            'Could not authenticate' => 'Nao foi possivel autenticar. Verifique as credenciais.',
            'Connection refused' => 'Conexao recusada. Verifique host e porta.',
            'Connection timed out' => 'Tempo de conexao esgotado. Verifique o servidor.',
            'SMTP connect() failed' => 'Falha ao conectar ao servidor SMTP.',
            'Invalid address' => 'Endereco de email invalido.',
            'Recipient rejected' => 'Destinatario rejeitado pelo servidor.',
            'Message rejected' => 'Mensagem rejeitada pelo servidor.',
            'relay access denied' => 'Acesso de relay negado. Verifique permissoes.',
            'Username and Password not accepted' => 'Usuario ou senha incorretos.',
            'SSL: Connection reset by peer' => 'Conexao SSL interrompida. Verifique a porta e criptografia.',
            'certificate verify failed' => 'Falha na verificacao do certificado SSL.',
        ];

        foreach ($errorMappings as $pattern => $message) {
            if (stripos($error, $pattern) !== false) {
                return $message;
            }
        }

        // Se nao encontrou mapeamento, retorna erro simplificado
        // Remove informacoes tecnicas desnecessarias
        $error = preg_replace('/SMTP ERROR:?\s*/i', '', $error);
        $error = preg_replace('/\([^)]+\)/', '', $error);
        $error = trim($error);

        return $error ?: 'Erro desconhecido ao conectar ao servidor SMTP';
    }

    /**
     * Gera HTML do email de teste
     *
     * @param string $fromName Nome do remetente
     * @return string HTML do email
     */
    private function getTestEmailHtml(string $fromName): string
    {
        $date = date('d/m/Y H:i:s');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Teste de Conexao SMTP</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Teste de Conexao SMTP</h1>
    </div>

    <div style="background: #f9f9f9; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 8px 8px;">
        <p style="margin-top: 0;">Parabens! Sua configuracao SMTP esta funcionando corretamente.</p>

        <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 20px 0;">
            <strong style="color: #2e7d32;">Conexao validada com sucesso!</strong>
        </div>

        <p><strong>Detalhes:</strong></p>
        <ul style="background: white; padding: 15px 15px 15px 35px; border-radius: 4px; border: 1px solid #e0e0e0;">
            <li><strong>Remetente:</strong> {$fromName}</li>
            <li><strong>Data/Hora:</strong> {$date}</li>
        </ul>

        <p style="margin-bottom: 0; color: #666; font-size: 14px;">
            Este email foi enviado automaticamente pelo sistema 7Carros Locadora para validar a configuracao de email.
        </p>
    </div>

    <div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">
        <p style="margin: 0;">&copy; 7Carros Locadora - Sistema de Gestao para Locadoras</p>
    </div>
</body>
</html>
HTML;
    }
}
