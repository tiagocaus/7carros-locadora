<?php

namespace App\Services\Smtp;

/**
 * Factory para configuracoes de provedores SMTP
 *
 * Fornece configuracoes pre-definidas para provedores de email populares
 * e lista de provedores disponiveis para o frontend.
 */
class SmtpProviderFactory
{
    /**
     * Configuracoes pre-definidas dos provedores
     */
    private static array $providers = [
        'gmail' => [
            'name' => 'Gmail',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'help_url' => 'https://support.google.com/accounts/answer/185833',
            'help_text' => 'Use uma senha de aplicativo. Acesse: Conta Google > Seguranca > Senhas de app',
        ],
        'outlook' => [
            'name' => 'Outlook / Microsoft 365',
            'host' => 'smtp-mail.outlook.com',
            'port' => 587,
            'encryption' => 'tls',
            'help_url' => 'https://support.microsoft.com/office/pop-imap-and-smtp-settings-8361e398-8af4-4e97-b147-6c6c4ac95353',
            'help_text' => 'Use sua senha do Outlook ou senha de aplicativo se tiver 2FA ativo',
        ],
        'aws_ses' => [
            'name' => 'Amazon SES',
            'host' => 'email-smtp.us-east-1.amazonaws.com',
            'port' => 587,
            'encryption' => 'tls',
            'help_url' => 'https://docs.aws.amazon.com/ses/latest/dg/send-email-smtp.html',
            'help_text' => 'Use credenciais SMTP do IAM (diferentes das credenciais AWS normais)',
            'regions' => [
                'us-east-1' => 'email-smtp.us-east-1.amazonaws.com',
                'us-east-2' => 'email-smtp.us-east-2.amazonaws.com',
                'us-west-2' => 'email-smtp.us-west-2.amazonaws.com',
                'eu-west-1' => 'email-smtp.eu-west-1.amazonaws.com',
                'eu-central-1' => 'email-smtp.eu-central-1.amazonaws.com',
                'ap-south-1' => 'email-smtp.ap-south-1.amazonaws.com',
                'ap-southeast-2' => 'email-smtp.ap-southeast-2.amazonaws.com',
                'sa-east-1' => 'email-smtp.sa-east-1.amazonaws.com',
            ],
        ],
        'sendgrid' => [
            'name' => 'SendGrid',
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'encryption' => 'tls',
            'help_url' => 'https://docs.sendgrid.com/for-developers/sending-email/integrating-with-the-smtp-api',
            'help_text' => 'Username: apikey (literal). Senha: sua API Key do SendGrid',
        ],
        'mailgun' => [
            'name' => 'Mailgun',
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'tls',
            'help_url' => 'https://documentation.mailgun.com/en/latest/user_manual.html#sending-via-smtp',
            'help_text' => 'Use as credenciais SMTP do seu dominio em Mailgun',
        ],
        'smtp_custom' => [
            'name' => 'SMTP Personalizado',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'help_url' => null,
            'help_text' => 'Configure manualmente os dados do seu servidor SMTP',
        ],
    ];

    /**
     * Retorna todas as configuracoes de provedores disponiveis
     *
     * @return array
     */
    public static function getAvailableProviders(): array
    {
        $result = [];

        foreach (self::$providers as $key => $provider) {
            $result[] = [
                'value' => $key,
                'label' => $provider['name'],
                'host' => $provider['host'],
                'port' => $provider['port'],
                'encryption' => $provider['encryption'],
                'help_url' => $provider['help_url'],
                'help_text' => $provider['help_text'],
                'is_custom' => $key === 'smtp_custom',
            ];
        }

        return $result;
    }

    /**
     * Retorna configuracao padrao de um provedor especifico
     *
     * @param string $provider Nome do provedor
     * @return array|null Configuracao ou null se nao encontrado
     */
    public static function getProviderDefaults(string $provider): ?array
    {
        return self::$providers[$provider] ?? null;
    }

    /**
     * Verifica se o provedor requer configuracao manual
     *
     * @param string $provider Nome do provedor
     * @return bool
     */
    public static function isCustomProvider(string $provider): bool
    {
        return $provider === 'smtp_custom';
    }

    /**
     * Retorna lista de regioes AWS SES disponiveis
     *
     * @return array
     */
    public static function getAwsSesRegions(): array
    {
        return self::$providers['aws_ses']['regions'] ?? [];
    }

    /**
     * Obtem o host AWS SES para uma regiao especifica
     *
     * @param string $region Regiao AWS
     * @return string|null Host ou null se regiao invalida
     */
    public static function getAwsSesHost(string $region): ?string
    {
        return self::$providers['aws_ses']['regions'][$region] ?? null;
    }

    /**
     * Retorna portas SMTP comuns disponiveis
     *
     * @return array Lista de portas com descricao
     */
    public static function getAvailablePorts(): array
    {
        return [
            ['value' => 587, 'label' => '587 (TLS - Recomendado)'],
            ['value' => 465, 'label' => '465 (SSL)'],
            ['value' => 25, 'label' => '25 (Sem criptografia)'],
            ['value' => 2525, 'label' => '2525 (Alternativa)'],
        ];
    }

    /**
     * Retorna opcoes de criptografia disponiveis
     *
     * @return array Lista de opcoes de criptografia
     */
    public static function getAvailableEncryptions(): array
    {
        return [
            ['value' => 'tls', 'label' => 'TLS (Recomendado)'],
            ['value' => 'ssl', 'label' => 'SSL'],
            ['value' => 'none', 'label' => 'Nenhuma'],
        ];
    }
}
