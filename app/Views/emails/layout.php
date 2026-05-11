<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{empresa.nome_fantasia}}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 16px; line-height: 1.6; color: #333333; background-color: #f4f4f4;">
    <!-- Container principal -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f4;">
        <tr>
            <td style="padding: 20px 10px;">
                <!-- Email wrapper -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- HEADER -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a56db 0%, #1e40af 100%); padding: 30px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                {{empresa.nome_fantasia}}
                            </h1>
                        </td>
                    </tr>

                    <!-- CONTEÚDO -->
                    <tr>
                        <td style="padding: 40px;">
                            {{content}}
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 30px 40px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <!-- Contatos -->
                                        <p style="margin: 0 0 10px 0; font-size: 14px; color: #64748b;">
                                            <span style="margin-right: 15px;">📞 {{empresa.telefone}}</span>
                                            <span>📧 {{empresa.email}}</span>
                                        </p>

                                        <!-- Dados da empresa -->
                                        <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                                            {{empresa.razao_social}}<br>
                                            CNPJ: {{empresa.cnpj}}<br>
                                            {{empresa.endereco_completo}}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- RODAPÉ LEGAL -->
                    <tr>
                        <td style="padding: 20px 40px; text-align: center;">
                            <p style="margin: 0; font-size: 11px; color: #94a3b8;">
                                Este email foi enviado automaticamente por {{empresa.nome_fantasia}}.<br>
                                Por favor, não responda diretamente a este email.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
