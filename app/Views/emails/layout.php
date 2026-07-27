<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{empresa.nome_fantasia}}</title>
    <style type="text/css">
        @media only screen and (max-width: 640px) {
            .email-container {
                width: 100% !important;
            }
            .email-header,
            .email-footer,
            .email-legal {
                padding-left: 16px !important;
                padding-right: 16px !important;
            }
            .email-content {
                padding: 24px 16px !important;
            }
            .invoice-table {
                table-layout: auto !important;
                font-size: 11px !important;
            }
            .invoice-table th,
            .invoice-table td {
                width: auto !important;
                padding: 5px 3px !important;
            }
            .invoice-nowrap {
                white-space: normal !important;
            }
        }
    </style>
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
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="%%EMAIL_LAYOUT_WIDTH%%" align="center" class="email-container" style="max-width: %%EMAIL_LAYOUT_MAX_WIDTH%%; width: %%EMAIL_LAYOUT_CSS_WIDTH%%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- HEADER -->
                    <tr>
                        <td class="email-header" style="background: linear-gradient(135deg, #1a56db 0%, #1e40af 100%); padding: 30px 40px; text-align: center;">
                            {{empresa.branding_header}}
                        </td>
                    </tr>

                    <!-- CONTEÚDO -->
                    <tr>
                        <td class="email-content" style="padding: 40px;">
                            {{content}}
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td class="email-footer" style="background-color: #f8fafc; padding: 30px 40px; border-top: 1px solid #e5e7eb;">
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
                        <td class="email-legal" style="padding: 20px 40px; text-align: center;">
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
