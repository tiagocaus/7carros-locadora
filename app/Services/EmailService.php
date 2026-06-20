<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Smtp;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Service para processar envio de emails
 *
 * Resolve credenciais SMTP na seguinte ordem:
 * 1. _system_message = true → usa ENV (SMTP da 7Carros)
 * 2. id_matriz_filial → busca Smtp::buscarValidadaPorFilial()
 *    - Encontrou SMTP validado → usa credenciais do tenant
 *    - Nao encontrou → fallback para ENV
 * 3. Sem id_matriz_filial → usa ENV
 */
class EmailService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    /**
     * Processa e envia um email
     *
     * @param array $payload Dados do email:
     *   - 'to': Email do destinatario (obrigatorio)
     *   - 'to_name': Nome do destinatario (opcional)
     *   - 'subject': Assunto do email (obrigatorio)
     *   - 'body': Corpo do email em HTML (obrigatorio)
     *   - 'body_text': Corpo do email em texto plano (opcional)
     *   - 'cc': Array de emails em copia (opcional)
     *   - 'bcc': Array de emails em copia oculta (opcional)
     *   - 'reply_to': Email para resposta (opcional)
     *   - 'reply_to_name': Nome para resposta (opcional)
     *   - 'attachments': Array de caminhos de arquivos para anexar (opcional)
     *   - 'id_matriz_filial': ID da filial para resolver SMTP do tenant (opcional)
     *   - '_system_message': Se true, usa SMTP do ENV (opcional)
     * @return array ['success' => bool, 'message' => string]
     */
    public function send(array $payload): array
    {
        try {
            if (empty($payload['to'])) {
                throw new \InvalidArgumentException("Campo 'to' e obrigatorio");
            }

            if (empty($payload['subject'])) {
                throw new \InvalidArgumentException("Campo 'subject' e obrigatorio");
            }

            if (empty($payload['body'])) {
                throw new \InvalidArgumentException("Campo 'body' e obrigatorio");
            }

            // Configura SMTP baseado no contexto do payload
            $this->configureSMTP($payload);

            // Limpa destinatarios anteriores
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            $this->mailer->clearAttachments();
            $this->mailer->clearReplyTos();

            // Destinatario principal
            $this->mailer->addAddress($payload['to'], $payload['to_name'] ?? '');

            // Copias (CC)
            if (!empty($payload['cc']) && is_array($payload['cc'])) {
                foreach ($payload['cc'] as $cc) {
                    if (is_array($cc)) {
                        $this->mailer->addCC($cc['email'], $cc['name'] ?? '');
                    } else {
                        $this->mailer->addCC($cc);
                    }
                }
            }

            // Copias ocultas (BCC)
            if (!empty($payload['bcc']) && is_array($payload['bcc'])) {
                foreach ($payload['bcc'] as $bcc) {
                    if (is_array($bcc)) {
                        $this->mailer->addBCC($bcc['email'], $bcc['name'] ?? '');
                    } else {
                        $this->mailer->addBCC($bcc);
                    }
                }
            }

            // Reply-To do payload (tem prioridade sobre o do SMTP)
            if (!empty($payload['reply_to'])) {
                $this->mailer->addReplyTo($payload['reply_to'], $payload['reply_to_name'] ?? '');
            }

            // Anexos
            $deleteAfterSend = [];
            if (!empty($payload['attachments']) && is_array($payload['attachments'])) {
                foreach ($payload['attachments'] as $attachment) {
                    if (is_array($attachment)) {
                        $path = $attachment['path'] ?? $attachment[0];
                        $name = $attachment['name'] ?? basename($path);
                        $this->mailer->addAttachment($path, $name);
                        if (!empty($attachment['delete_after_send'])) {
                            $deleteAfterSend[] = $path;
                        }
                    } else {
                        $this->mailer->addAttachment($attachment);
                    }
                }
            }

            // Assunto e corpo
            $this->mailer->Subject = $payload['subject'];
            $this->mailer->Body = $payload['body'];
            $this->mailer->isHTML(true);

            if (!empty($payload['body_text'])) {
                $this->mailer->AltBody = $payload['body_text'];
            }

            $this->mailer->send();

            foreach ($deleteAfterSend as $path) {
                if (is_string($path) && $path !== '' && file_exists($path)) {
                    @unlink($path);
                }
            }

            return [
                'success' => true,
                'message' => 'Email enviado com sucesso',
            ];
        } catch (Exception $e) {
            error_log("Erro ao enviar email: " . $this->mailer->ErrorInfo);
            return [
                'success' => false,
                'message' => 'Erro ao enviar email: ' . $this->mailer->ErrorInfo,
            ];
        } catch (\Exception $e) {
            error_log("Erro ao processar email: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao processar email: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Configura SMTP baseado no contexto do payload
     *
     * Prioridade:
     * 1. _system_message → ENV
     * 2. id_matriz_filial → SMTP do tenant (se validado)
     * 3. Fallback → ENV
     */
    private function configureSMTP(array $payload): void
    {
        $this->mailer->isSMTP();
        $this->mailer->SMTPAuth = true;

        // Desabilitar verificação de certificado SSL se configurado
        if (Database::env('MAIL_VERIFY_SSL', 'true') === 'false') {
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        // Mensagem de sistema → sempre ENV
        if (!empty($payload['_system_message'])) {
            $this->configureFromEnv();
            return;
        }

        // Tenta SMTP do tenant pela filial
        if (!empty($payload['id_matriz_filial'])) {
            $smtpModel = new Smtp();
            $smtpConnection = $smtpModel->buscarValidadaPorFilial((int) $payload['id_matriz_filial']);

            if ($smtpConnection) {
                $this->mailer->Host = $smtpConnection['host'];
                $this->mailer->Port = (int) $smtpConnection['port'];
                $this->mailer->SMTPSecure = $smtpConnection['encryption'] === 'none' ? '' : $smtpConnection['encryption'];
                $this->mailer->Username = $smtpConnection['username'];
                $this->mailer->Password = decrypt($smtpConnection['password']);
                $this->mailer->setFrom(
                    $smtpConnection['from_email'],
                    $smtpConnection['from_name']
                );

                if (!empty($smtpConnection['reply_to_email'])) {
                    $this->mailer->addReplyTo(
                        $smtpConnection['reply_to_email'],
                        $smtpConnection['reply_to_name'] ?? ''
                    );
                }

                return;
            }
        }

        // Fallback: usa ENV
        $this->configureFromEnv();
    }

    /**
     * Configura SMTP usando variaveis de ambiente (SMTP da 7Carros)
     */
    private function configureFromEnv(): void
    {
        $this->mailer->Host = Database::env('MAIL_HOST', 'smtp.gmail.com');
        $this->mailer->Port = (int) Database::env('MAIL_PORT', '587');
        $this->mailer->SMTPSecure = Database::env('MAIL_ENCRYPTION', 'tls');
        $this->mailer->Username = Database::env('MAIL_USERNAME');
        $this->mailer->Password = Database::env('MAIL_PASSWORD');
        $this->mailer->setFrom(
            Database::env('MAIL_FROM_ADDRESS', 'noreply@7carros.com'),
            Database::env('MAIL_FROM_NAME', '7Carros Locadora')
        );
    }
}
