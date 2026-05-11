-- =====================================================
-- Message Templates - Internationalization
-- Translations for: en_US, es_ES, it_IT, pt_PT
-- =====================================================

-- =====================================================
-- ENGLISH (en_US)
-- =====================================================

-- 1. Welcome - Email (template_type_id = 1)
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 1, 'en_US', 'email', 'Welcome to {{empresa.nome_fantasia}}!', '<p>Hello, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>Welcome to <strong>{{empresa.nome_fantasia}}</strong>!</p>\n\n            <p>We are very happy to have you as our customer. From now on, you will have access to the best vehicles and quality service you deserve.</p>\n\n            <p>If you have any questions, our team is available to help you.</p>', NULL, 1);

-- 2. Rental Confirmation - Email (template_type_id = 2)
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 2, 'en_US', 'email', 'Rental Confirmation #{{locacao.numero}}', '<p>Hello, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Your rental has been successfully confirmed. See details below:</p>\n\n            <div class="info-box">\n                <h3>📋 Rental Details</h3>\n                <table>\n                    <tr><td class="label">Number:</td><td class="value">{{locacao.numero}}</td></tr>\n                    <tr><td class="label">Total Amount:</td><td class="value">{{locacao.valor_total}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>🚗 Vehicle</h3>\n                <table>\n                    <tr><td class="label">Vehicle:</td><td class="value">{{veiculo.descricao_completa}}</td></tr>\n                    <tr><td class="label">License Plate:</td><td class="value">{{veiculo.placa}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>📍 Pickup</h3>\n                <table>\n                    <tr><td class="label">Date:</td><td class="value">{{locacao.data_retirada}}</td></tr>\n                    <tr><td class="label">Time:</td><td class="value">{{locacao.hora_retirada}}</td></tr>\n                    <tr><td class="label">Location:</td><td class="value">{{locacao.local_retirada}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>📍 Return</h3>\n                <table>\n                    <tr><td class="label">Date:</td><td class="value">{{locacao.data_devolucao}}</td></tr>\n                    <tr><td class="label">Time:</td><td class="value">{{locacao.hora_devolucao}}</td></tr>\n                    <tr><td class="label">Location:</td><td class="value">{{locacao.local_devolucao}}</td></tr>\n                </table>\n            </div>\n\n            <p><strong>Required documents:</strong> Valid driver\'\'s license and photo ID.</p>', NULL, 1);

-- 2. Rental Confirmation - SMS
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 2, 'en_US', 'sms', NULL, 'Rental #{{locacao.numero}} confirmed! Pickup: {{locacao.data_retirada}} {{locacao.hora_retirada}}. Vehicle: {{veiculo.placa}}. {{empresa.nome_fantasia}}', NULL, 1);

-- 2. Rental Confirmation - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 2, 'en_US', 'whatsapp', NULL, '✅ *Rental Confirmed!*

📋 Number: {{locacao.numero}}
🚗 Vehicle: {{veiculo.descricao_completa}}

📍 Pickup: {{locacao.data_retirada}} at {{locacao.hora_retirada}}
📍 Location: {{locacao.local_retirada}}

📍 Return: {{locacao.data_devolucao}} at {{locacao.hora_devolucao}}
📍 Location: {{locacao.local_devolucao}}

💰 Total Amount: {{locacao.valor_total}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 3. Contract Confirmation - Email (template_type_id = 3)
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 3, 'en_US', 'email', 'Contract #{{contrato.numero}} - {{empresa.nome_fantasia}}', '<p>Hello, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>Your contract has been successfully generated. Details below:</p>\n\n            <div class="info-box">\n                <p><strong>Contract Number:</strong> {{contrato.numero}}</p>\n                <p><strong>Period:</strong> {{contrato.data_inicio}} to {{contrato.data_fim}}</p>\n                <p><strong>Vehicle:</strong> {{veiculo.descricao_completa}}</p>\n                <p><strong>Total Amount:</strong> {{contrato.valor_total}}</p>\n            </div>', NULL, 1);

-- 4. Return Reminder - SMS
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 4, 'en_US', 'sms', NULL, 'Reminder: Return vehicle {{veiculo.placa}} on {{locacao.data_devolucao}} at {{locacao.hora_devolucao}}. {{empresa.nome_fantasia}}', NULL, 1);

-- 4. Return Reminder - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 4, 'en_US', 'whatsapp', NULL, '⏰ *Return Reminder*

Hello, {{cliente.primeiro_nome}}!

This is a reminder that the return of vehicle *{{veiculo.placa}}* is scheduled for:

📅 Date: {{locacao.data_devolucao}}
⏰ Time: {{locacao.hora_devolucao}}
📍 Location: {{locacao.local_devolucao}}

Questions? Contact us!
*{{empresa.nome_fantasia}}*', NULL, 1);

-- 5. Payment Reminder - Email (template_type_id = 5)
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'en_US', 'email', 'Payment Reminder - Invoice #{{fatura.numero}}', '<p>Hello, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>This is a reminder that your invoice is due soon:</p>\n\n            <div class="info-box">\n                <p><strong>Invoice:</strong> #{{fatura.numero}}</p>\n                <p><strong>Amount:</strong> {{fatura.valor}}</p>\n                <p><strong>Due Date:</strong> {{fatura.data_vencimento}}</p>\n            </div>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Pay Now</a></p>', NULL, 1);

-- 5. Payment Reminder - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'en_US', 'whatsapp', NULL, '💳 *Payment Reminder*

Hello, {{cliente.primeiro_nome}}!

Your invoice #{{fatura.numero}} of {{fatura.valor}} is due on {{fatura.data_vencimento}}.

🔗 Payment link:
{{fatura.link_boleto}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 6. Invoice Generated - Email (template_type_id = 6)
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 6, 'en_US', 'email', 'New Invoice #{{fatura.numero}} - {{empresa.nome_fantasia}}', '<p>Hello, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>A new invoice has been generated for you:</p>\n\n            <div class="info-box">\n                <p><strong>Number:</strong> #{{fatura.numero}}</p>\n                <p><strong>Amount:</strong> {{fatura.valor}}</p>\n                <p><strong>Due Date:</strong> {{fatura.data_vencimento}}</p>\n            </div>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">View Invoice</a></p>', NULL, 1);

-- 6. Invoice Generated - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 6, 'en_US', 'whatsapp', NULL, '📄 *New Invoice Generated*

Hello, {{cliente.primeiro_nome}}!

Invoice #{{fatura.numero}}
💰 Amount: {{fatura.valor}}
📅 Due Date: {{fatura.data_vencimento}}

🔗 Pay here:
{{fatura.link_boleto}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 7. Overdue Notice - Email (template_type_id = 7)
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'en_US', 'email', 'Notice: Invoice #{{fatura.numero}} Overdue', '<p>Hello, <strong>{{cliente.nome}}</strong>.</p>\n\n            <p>We noticed that the following invoice is overdue:</p>\n\n            <div class="info-box">\n                <p><strong>Invoice:</strong> #{{fatura.numero}}</p>\n                <p><strong>Amount:</strong> {{fatura.valor}}</p>\n                <p><strong>Due Date:</strong> {{fatura.data_vencimento}}</p>\n                <p><strong>Days Overdue:</strong> {{fatura.dias_atraso}}</p>\n            </div>\n\n            <p>Please settle your account to avoid late fees and possible restrictions on future rentals.</p>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Pay Now</a></p>\n\n            <p><small>If you have already made the payment, please disregard this notice.</small></p>', NULL, 1);

-- 7. Overdue Notice - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'en_US', 'whatsapp', NULL, '⚠️ *Overdue Invoice*

Hello, {{cliente.primeiro_nome}}.

We noticed that invoice #{{fatura.numero}} of {{fatura.valor}} is overdue.

Please settle your account to avoid late fees.

🔗 Payment link:
{{fatura.link_boleto}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 8. License Expiring - Email (template_type_id = 8)
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 8, 'en_US', 'email', 'Attention: Your Driver''s License is Expiring Soon', '<h2 style="color: #f59e0b; margin: 0 0 20px 0;">⚠️ License Expiring Soon</h2>

<p>Hello, <strong>{{cliente.primeiro_nome}}</strong>!</p>

<p>We noticed that your driver''s license is about to expire:</p>

<div style="background: #fffbeb; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">
    <p style="margin: 5px 0;"><strong>License Number:</strong> {{cliente.cnh_numero}}</p>
    <p style="margin: 5px 0;"><strong>Expiration Date:</strong> {{cliente.cnh_validade}}</p>
</div>

<p>Remember to renew your license to continue renting vehicles with us without interruption.</p>

<p>After renewal, don''t forget to update your registration information!</p>', NULL, 1);

-- 8. License Expiring - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 8, 'en_US', 'whatsapp', NULL, '⚠️ *License Expiring Soon*

Hello, {{cliente.primeiro_nome}}!

Your driver''s license expires on {{cliente.cnh_validade}}.

Remember to renew it to continue renting with us!

*{{empresa.nome_fantasia}}*', NULL, 1);

-- =====================================================
-- SPANISH (es_ES)
-- =====================================================

-- 1. Welcome - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 1, 'es_ES', 'email', '¡Bienvenido a {{empresa.nome_fantasia}}!', '<p>Hola, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>¡Bienvenido(a) a <strong>{{empresa.nome_fantasia}}</strong>!</p>\n\n            <p>Estamos muy felices de tenerlo como nuestro cliente. A partir de ahora, tendrá acceso a los mejores vehículos y al servicio de calidad que merece.</p>\n\n            <p>Si tiene alguna pregunta, nuestro equipo está a su disposición para ayudarlo.</p>', NULL, 1);

-- 1. Welcome - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 1, 'es_ES', 'whatsapp', NULL, '¡Hola, {{cliente.primeiro_nome}}! 👋

¡Bienvenido(a) a *{{empresa.nome_fantasia}}*!

Estamos felices de tenerte como cliente. Si necesitas ayuda, estamos aquí para ti.

📞 {{empresa.telefone}}
✉️ {{empresa.email}}', NULL, 1);

-- 2. Rental Confirmation - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 2, 'es_ES', 'email', 'Confirmación de Alquiler #{{locacao.numero}}', '<p>Hola, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Su alquiler ha sido confirmado exitosamente. Vea los detalles a continuación:</p>\n\n            <div class="info-box">\n                <h3>📋 Datos del Alquiler</h3>\n                <table>\n                    <tr><td class="label">Número:</td><td class="value">{{locacao.numero}}</td></tr>\n                    <tr><td class="label">Valor Total:</td><td class="value">{{locacao.valor_total}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>🚗 Vehículo</h3>\n                <table>\n                    <tr><td class="label">Vehículo:</td><td class="value">{{veiculo.descricao_completa}}</td></tr>\n                    <tr><td class="label">Matrícula:</td><td class="value">{{veiculo.placa}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>📍 Recogida</h3>\n                <table>\n                    <tr><td class="label">Fecha:</td><td class="value">{{locacao.data_retirada}}</td></tr>\n                    <tr><td class="label">Hora:</td><td class="value">{{locacao.hora_retirada}}</td></tr>\n                    <tr><td class="label">Lugar:</td><td class="value">{{locacao.local_retirada}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>📍 Devolución</h3>\n                <table>\n                    <tr><td class="label">Fecha:</td><td class="value">{{locacao.data_devolucao}}</td></tr>\n                    <tr><td class="label">Hora:</td><td class="value">{{locacao.hora_devolucao}}</td></tr>\n                    <tr><td class="label">Lugar:</td><td class="value">{{locacao.local_devolucao}}</td></tr>\n                </table>\n            </div>\n\n            <p><strong>Documentos necesarios:</strong> Licencia de conducir válida y documento de identidad con foto.</p>', NULL, 1);

-- 2. Rental Confirmation - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 2, 'es_ES', 'whatsapp', NULL, '✅ *¡Alquiler Confirmado!*

📋 Número: {{locacao.numero}}
🚗 Vehículo: {{veiculo.descricao_completa}}

📍 Recogida: {{locacao.data_retirada}} a las {{locacao.hora_retirada}}
📍 Lugar: {{locacao.local_retirada}}

📍 Devolución: {{locacao.data_devolucao}} a las {{locacao.hora_devolucao}}
📍 Lugar: {{locacao.local_devolucao}}

💰 Valor Total: {{locacao.valor_total}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 3. Contract Confirmation - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 3, 'es_ES', 'email', 'Contrato #{{contrato.numero}} - {{empresa.nome_fantasia}}', '<p>Hola, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>Su contrato ha sido generado exitosamente. Detalles:</p>\n\n            <div class="info-box">\n                <p><strong>Número del Contrato:</strong> {{contrato.numero}}</p>\n                <p><strong>Período:</strong> {{contrato.data_inicio}} a {{contrato.data_fim}}</p>\n                <p><strong>Vehículo:</strong> {{veiculo.descricao_completa}}</p>\n                <p><strong>Valor Total:</strong> {{contrato.valor_total}}</p>\n            </div>', NULL, 1);

-- 4. Return Reminder - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 4, 'es_ES', 'email', 'Recordatorio: Devolución del vehículo {{veiculo.placa}}', '<p>Hola, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Este es un recordatorio amigable sobre la devolución del vehículo:</p>\n\n            <div class="info-box">\n                <p><strong>🚗 Vehículo:</strong> {{veiculo.descricao_completa}}</p>\n                <p><strong>📅 Fecha:</strong> {{locacao.data_devolucao}}</p>\n                <p><strong>⏰ Hora:</strong> {{locacao.hora_devolucao}}</p>\n                <p><strong>📍 Lugar:</strong> {{locacao.local_devolucao}}</p>\n            </div>\n\n            <p>Recuerde devolver el vehículo con el mismo nivel de combustible que al recogerlo.</p>\n\n            <p>¿Dudas? ¡Contáctenos!</p>', NULL, 1);

-- 4. Return Reminder - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 4, 'es_ES', 'whatsapp', NULL, '⏰ *Recordatorio de Devolución*

Hola, {{cliente.primeiro_nome}}!

Le recordamos que la devolución del vehículo *{{veiculo.placa}}* está programada para:

📅 Fecha: {{locacao.data_devolucao}}
⏰ Hora: {{locacao.hora_devolucao}}
📍 Lugar: {{locacao.local_devolucao}}

¿Dudas? ¡Contáctenos!
*{{empresa.nome_fantasia}}*', NULL, 1);

-- 5. Payment Reminder - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'es_ES', 'email', 'Recordatorio de Pago - Factura #{{fatura.numero}}', '<p>Hola, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Le recordamos que su factura está próxima a vencer:</p>\n\n            <div class="info-box">\n                <p><strong>Factura:</strong> #{{fatura.numero}}</p>\n                <p><strong>Valor:</strong> {{fatura.valor}}</p>\n                <p><strong>Vencimiento:</strong> {{fatura.data_vencimento}}</p>\n            </div>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Pagar Ahora</a></p>', NULL, 1);

-- 5. Payment Reminder - SMS
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'es_ES', 'sms', NULL, 'Factura #{{fatura.numero}} de {{fatura.valor}} vence el {{fatura.data_vencimento}}. {{empresa.nome_fantasia}}', NULL, 1);

-- 5. Payment Reminder - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'es_ES', 'whatsapp', NULL, '💳 *Recordatorio de Pago*

Hola, {{cliente.primeiro_nome}}!

Su factura #{{fatura.numero}} de {{fatura.valor}} vence el {{fatura.data_vencimento}}.

🔗 Enlace de pago:
{{fatura.link_boleto}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 6. Invoice Generated - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 6, 'es_ES', 'email', 'Nueva Factura #{{fatura.numero}} - {{empresa.nome_fantasia}}', '<p>Hola, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>Se ha generado una nueva factura para usted:</p>\n\n            <div class="info-box">\n                <p><strong>Número:</strong> #{{fatura.numero}}</p>\n                <p><strong>Valor:</strong> {{fatura.valor}}</p>\n                <p><strong>Vencimiento:</strong> {{fatura.data_vencimento}}</p>\n            </div>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Ver Factura</a></p>', NULL, 1);

-- 7. Overdue Notice - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'es_ES', 'email', 'Aviso: Factura #{{fatura.numero}} en mora', '<p>Hola, <strong>{{cliente.nome}}</strong>.</p>\n\n            <p>Identificamos que la siguiente factura está en mora:</p>\n\n            <div class="info-box">\n                <p><strong>Factura:</strong> #{{fatura.numero}}</p>\n                <p><strong>Valor:</strong> {{fatura.valor}}</p>\n                <p><strong>Vencimiento:</strong> {{fatura.data_vencimento}}</p>\n                <p><strong>Días de mora:</strong> {{fatura.dias_atraso}}</p>\n            </div>\n\n            <p>Regularice su situación para evitar intereses y multas, además de posibles restricciones en futuros alquileres.</p>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Pagar Ahora</a></p>\n\n            <p><small>Si ya realizó el pago, por favor ignore este aviso.</small></p>', NULL, 1);

-- 7. Overdue Notice - SMS
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'es_ES', 'sms', NULL, 'Factura #{{fatura.numero}} en mora. Valor: {{fatura.valor}}. Regularice para evitar intereses. {{empresa.nome_fantasia}}', NULL, 1);

-- 7. Overdue Notice - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'es_ES', 'whatsapp', NULL, '⚠️ *Factura en Mora*

Hola, {{cliente.primeiro_nome}}.

Identificamos que la factura #{{fatura.numero}} de {{fatura.valor}} está en mora.

Regularice su situación para evitar intereses y multas.

🔗 Enlace de pago:
{{fatura.link_boleto}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 8. License Expiring - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 8, 'es_ES', 'email', 'Atención: Su licencia de conducir está por vencer', '<p>Hola, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Identificamos que su licencia de conducir está próxima a vencer:</p>\n\n            <div class="info-box">\n                <p><strong>Número de Licencia:</strong> {{cliente.cnh_numero}}</p>\n                <p><strong>Fecha de Vencimiento:</strong> {{cliente.cnh_validade}}</p>\n            </div>\n\n            <p>Recuerde renovar su licencia para continuar alquilando vehículos con nosotros sin interrupciones.</p>\n\n            <p>¡Después de la renovación, no olvide actualizar sus datos!</p>', NULL, 1);

-- =====================================================
-- ITALIAN (it_IT)
-- =====================================================

-- 1. Welcome - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 1, 'it_IT', 'email', 'Benvenuto in {{empresa.nome_fantasia}}!', '<p>Ciao, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>Benvenuto/a in <strong>{{empresa.nome_fantasia}}</strong>!</p>\n\n            <p>Siamo molto felici di averti come nostro cliente. Da ora in poi, avrai accesso ai migliori veicoli e al servizio di qualità che meriti.</p>\n\n            <p>Se hai domande, il nostro team è a tua disposizione per aiutarti.</p>', NULL, 1);

-- 1. Welcome - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 1, 'it_IT', 'whatsapp', NULL, 'Ciao, {{cliente.primeiro_nome}}! 👋

Benvenuto/a in *{{empresa.nome_fantasia}}*!

Siamo felici di averti come cliente. Se hai bisogno di aiuto, siamo qui per te.

📞 {{empresa.telefone}}
✉️ {{empresa.email}}', NULL, 1);

-- 2. Rental Confirmation - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 2, 'it_IT', 'email', 'Conferma Noleggio #{{locacao.numero}}', '<p>Ciao, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Il tuo noleggio è stato confermato con successo. Ecco i dettagli:</p>\n\n            <div class="info-box">\n                <h3>📋 Dati del Noleggio</h3>\n                <table>\n                    <tr><td class="label">Numero:</td><td class="value">{{locacao.numero}}</td></tr>\n                    <tr><td class="label">Importo Totale:</td><td class="value">{{locacao.valor_total}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>🚗 Veicolo</h3>\n                <table>\n                    <tr><td class="label">Veicolo:</td><td class="value">{{veiculo.descricao_completa}}</td></tr>\n                    <tr><td class="label">Targa:</td><td class="value">{{veiculo.placa}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>📍 Ritiro</h3>\n                <table>\n                    <tr><td class="label">Data:</td><td class="value">{{locacao.data_retirada}}</td></tr>\n                    <tr><td class="label">Ora:</td><td class="value">{{locacao.hora_retirada}}</td></tr>\n                    <tr><td class="label">Luogo:</td><td class="value">{{locacao.local_retirada}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>📍 Restituzione</h3>\n                <table>\n                    <tr><td class="label">Data:</td><td class="value">{{locacao.data_devolucao}}</td></tr>\n                    <tr><td class="label">Ora:</td><td class="value">{{locacao.hora_devolucao}}</td></tr>\n                    <tr><td class="label">Luogo:</td><td class="value">{{locacao.local_devolucao}}</td></tr>\n                </table>\n            </div>\n\n            <p><strong>Documenti necessari:</strong> Patente valida e documento d\'\'identità con foto.</p>', NULL, 1);

-- 2. Rental Confirmation - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 2, 'it_IT', 'whatsapp', NULL, '✅ *Noleggio Confermato!*

📋 Numero: {{locacao.numero}}
🚗 Veicolo: {{veiculo.descricao_completa}}

📍 Ritiro: {{locacao.data_retirada}} alle {{locacao.hora_retirada}}
📍 Luogo: {{locacao.local_retirada}}

📍 Restituzione: {{locacao.data_devolucao}} alle {{locacao.hora_devolucao}}
📍 Luogo: {{locacao.local_devolucao}}

💰 Importo Totale: {{locacao.valor_total}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 3. Contract Confirmation - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 3, 'it_IT', 'email', 'Contratto #{{contrato.numero}} - {{empresa.nome_fantasia}}', '<p>Ciao, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>Il tuo contratto è stato generato con successo. Ecco i dettagli:</p>\n\n            <div class="info-box">\n                <p><strong>Numero Contratto:</strong> {{contrato.numero}}</p>\n                <p><strong>Periodo:</strong> {{contrato.data_inicio}} a {{contrato.data_fim}}</p>\n                <p><strong>Veicolo:</strong> {{veiculo.descricao_completa}}</p>\n                <p><strong>Importo Totale:</strong> {{contrato.valor_total}}</p>\n            </div>', NULL, 1);

-- 4. Return Reminder - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 4, 'it_IT', 'email', 'Promemoria: Restituzione veicolo {{veiculo.placa}}', '<p>Ciao, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Questo è un promemoria amichevole sulla restituzione del veicolo:</p>\n\n            <div class="info-box">\n                <p><strong>🚗 Veicolo:</strong> {{veiculo.descricao_completa}}</p>\n                <p><strong>📅 Data:</strong> {{locacao.data_devolucao}}</p>\n                <p><strong>⏰ Ora:</strong> {{locacao.hora_devolucao}}</p>\n                <p><strong>📍 Luogo:</strong> {{locacao.local_devolucao}}</p>\n            </div>\n\n            <p>Ricorda di restituire il veicolo con lo stesso livello di carburante del ritiro.</p>\n\n            <p>Domande? Contattaci!</p>', NULL, 1);

-- 4. Return Reminder - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 4, 'it_IT', 'whatsapp', NULL, '⏰ *Promemoria Restituzione*

Ciao, {{cliente.primeiro_nome}}!

Ti ricordiamo che la restituzione del veicolo *{{veiculo.placa}}* è prevista per:

📅 Data: {{locacao.data_devolucao}}
⏰ Ora: {{locacao.hora_devolucao}}
📍 Luogo: {{locacao.local_devolucao}}

Domande? Contattaci!
*{{empresa.nome_fantasia}}*', NULL, 1);

-- 5. Payment Reminder - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'it_IT', 'email', 'Promemoria Pagamento - Fattura #{{fatura.numero}}', '<p>Ciao, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Ti ricordiamo che la tua fattura è in scadenza:</p>\n\n            <div class="info-box">\n                <p><strong>Fattura:</strong> #{{fatura.numero}}</p>\n                <p><strong>Importo:</strong> {{fatura.valor}}</p>\n                <p><strong>Scadenza:</strong> {{fatura.data_vencimento}}</p>\n            </div>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Paga Ora</a></p>', NULL, 1);

-- 5. Payment Reminder - SMS
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'it_IT', 'sms', NULL, 'Fattura #{{fatura.numero}} di {{fatura.valor}} scade il {{fatura.data_vencimento}}. {{empresa.nome_fantasia}}', NULL, 1);

-- 5. Payment Reminder - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'it_IT', 'whatsapp', NULL, '💳 *Promemoria Pagamento*

Ciao, {{cliente.primeiro_nome}}!

La tua fattura #{{fatura.numero}} di {{fatura.valor}} scade il {{fatura.data_vencimento}}.

🔗 Link per il pagamento:
{{fatura.link_boleto}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 6. Invoice Generated - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 6, 'it_IT', 'email', 'Nuova Fattura #{{fatura.numero}} - {{empresa.nome_fantasia}}', '<p>Ciao, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>È stata generata una nuova fattura per te:</p>\n\n            <div class="info-box">\n                <p><strong>Numero:</strong> #{{fatura.numero}}</p>\n                <p><strong>Importo:</strong> {{fatura.valor}}</p>\n                <p><strong>Scadenza:</strong> {{fatura.data_vencimento}}</p>\n            </div>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Visualizza Fattura</a></p>', NULL, 1);

-- 7. Overdue Notice - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'it_IT', 'email', 'Avviso: Fattura #{{fatura.numero}} scaduta', '<p>Ciao, <strong>{{cliente.nome}}</strong>.</p>\n\n            <p>Abbiamo notato che la seguente fattura è scaduta:</p>\n\n            <div class="info-box">\n                <p><strong>Fattura:</strong> #{{fatura.numero}}</p>\n                <p><strong>Importo:</strong> {{fatura.valor}}</p>\n                <p><strong>Scadenza:</strong> {{fatura.data_vencimento}}</p>\n                <p><strong>Giorni di ritardo:</strong> {{fatura.dias_atraso}}</p>\n            </div>\n\n            <p>Regolarizza la tua situazione per evitare interessi e multe, oltre a possibili restrizioni sui noleggi futuri.</p>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Paga Ora</a></p>\n\n            <p><small>Se hai già effettuato il pagamento, ignora questo avviso.</small></p>', NULL, 1);

-- 7. Overdue Notice - SMS
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'it_IT', 'sms', NULL, 'Fattura #{{fatura.numero}} scaduta. Importo: {{fatura.valor}}. Regolarizza per evitare interessi. {{empresa.nome_fantasia}}', NULL, 1);

-- 7. Overdue Notice - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'it_IT', 'whatsapp', NULL, '⚠️ *Fattura Scaduta*

Ciao, {{cliente.primeiro_nome}}.

Abbiamo notato che la fattura #{{fatura.numero}} di {{fatura.valor}} è scaduta.

Regolarizza la tua situazione per evitare interessi e multe.

🔗 Link per il pagamento:
{{fatura.link_boleto}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 8. License Expiring - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 8, 'it_IT', 'email', 'Attenzione: La tua patente sta per scadere', '<p>Ciao, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Abbiamo notato che la tua patente sta per scadere:</p>\n\n            <div class="info-box">\n                <p><strong>Numero Patente:</strong> {{cliente.cnh_numero}}</p>\n                <p><strong>Data di Scadenza:</strong> {{cliente.cnh_validade}}</p>\n            </div>\n\n            <p>Ricorda di rinnovare la patente per continuare a noleggiare veicoli con noi senza interruzioni.</p>\n\n            <p>Dopo il rinnovo, non dimenticare di aggiornare i tuoi dati!</p>', NULL, 1);

-- =====================================================
-- PORTUGUESE PORTUGAL (pt_PT)
-- =====================================================

-- 1. Welcome - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 1, 'pt_PT', 'email', 'Bem-vindo à {{empresa.nome_fantasia}}!', '<p>Olá, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>Seja bem-vindo(a) à <strong>{{empresa.nome_fantasia}}</strong>!</p>\n\n            <p>Estamos muito felizes por tê-lo como nosso cliente. A partir de agora, terá acesso aos melhores veículos e ao atendimento de qualidade que merece.</p>\n\n            <p>Se tiver qualquer dúvida, a nossa equipa está à disposição para ajudá-lo.</p>', NULL, 1);

-- 1. Welcome - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 1, 'pt_PT', 'whatsapp', NULL, 'Olá, {{cliente.primeiro_nome}}! 👋

Seja bem-vindo(a) à *{{empresa.nome_fantasia}}*!

Estamos felizes por tê-lo como cliente. Se precisar de qualquer ajuda, estamos à disposição.

📞 {{empresa.telefone}}
✉️ {{empresa.email}}', NULL, 1);

-- 2. Rental Confirmation - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 2, 'pt_PT', 'email', 'Confirmação de Aluguer #{{locacao.numero}}', '<p>Olá, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>O seu aluguer foi confirmado com sucesso. Confira os detalhes abaixo:</p>\n\n            <div class="info-box">\n                <h3>📋 Dados do Aluguer</h3>\n                <table>\n                    <tr><td class="label">Número:</td><td class="value">{{locacao.numero}}</td></tr>\n                    <tr><td class="label">Valor Total:</td><td class="value">{{locacao.valor_total}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>🚗 Veículo</h3>\n                <table>\n                    <tr><td class="label">Veículo:</td><td class="value">{{veiculo.descricao_completa}}</td></tr>\n                    <tr><td class="label">Matrícula:</td><td class="value">{{veiculo.placa}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>📍 Levantamento</h3>\n                <table>\n                    <tr><td class="label">Data:</td><td class="value">{{locacao.data_retirada}}</td></tr>\n                    <tr><td class="label">Hora:</td><td class="value">{{locacao.hora_retirada}}</td></tr>\n                    <tr><td class="label">Local:</td><td class="value">{{locacao.local_retirada}}</td></tr>\n                </table>\n            </div>\n\n            <div class="info-box">\n                <h3>📍 Devolução</h3>\n                <table>\n                    <tr><td class="label">Data:</td><td class="value">{{locacao.data_devolucao}}</td></tr>\n                    <tr><td class="label">Hora:</td><td class="value">{{locacao.hora_devolucao}}</td></tr>\n                    <tr><td class="label">Local:</td><td class="value">{{locacao.local_devolucao}}</td></tr>\n                </table>\n            </div>\n\n            <p><strong>Documentos necessários:</strong> Carta de condução válida e documento de identificação com fotografia.</p>', NULL, 1);

-- 2. Rental Confirmation - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 2, 'pt_PT', 'whatsapp', NULL, '✅ *Aluguer Confirmado!*

📋 Número: {{locacao.numero}}
🚗 Veículo: {{veiculo.descricao_completa}}

📍 Levantamento: {{locacao.data_retirada}} às {{locacao.hora_retirada}}
📍 Local: {{locacao.local_retirada}}

📍 Devolução: {{locacao.data_devolucao}} às {{locacao.hora_devolucao}}
📍 Local: {{locacao.local_devolucao}}

💰 Valor Total: {{locacao.valor_total}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 3. Contract Confirmation - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 3, 'pt_PT', 'email', 'Contrato #{{contrato.numero}} - {{empresa.nome_fantasia}}', '<p>Olá, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>O seu contrato foi gerado com sucesso. Seguem os detalhes:</p>\n\n            <div class="info-box">\n                <p><strong>Número do Contrato:</strong> {{contrato.numero}}</p>\n                <p><strong>Período:</strong> {{contrato.data_inicio}} a {{contrato.data_fim}}</p>\n                <p><strong>Veículo:</strong> {{veiculo.descricao_completa}}</p>\n                <p><strong>Valor Total:</strong> {{contrato.valor_total}}</p>\n            </div>', NULL, 1);

-- 4. Return Reminder - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 4, 'pt_PT', 'email', 'Lembrete: Devolução do veículo {{veiculo.placa}}', '<p>Olá, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Este é um lembrete amigável sobre a devolução do veículo:</p>\n\n            <div class="info-box">\n                <p><strong>🚗 Veículo:</strong> {{veiculo.descricao_completa}}</p>\n                <p><strong>📅 Data:</strong> {{locacao.data_devolucao}}</p>\n                <p><strong>⏰ Hora:</strong> {{locacao.hora_devolucao}}</p>\n                <p><strong>📍 Local:</strong> {{locacao.local_devolucao}}</p>\n            </div>\n\n            <p>Lembre-se de devolver o veículo com o mesmo nível de combustível do levantamento.</p>\n\n            <p>Dúvidas? Entre em contacto connosco!</p>', NULL, 1);

-- 4. Return Reminder - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 4, 'pt_PT', 'whatsapp', NULL, '⏰ *Lembrete de Devolução*

Olá, {{cliente.primeiro_nome}}!

Lembramos que a devolução do veículo *{{veiculo.placa}}* está agendada para:

📅 Data: {{locacao.data_devolucao}}
⏰ Hora: {{locacao.hora_devolucao}}
📍 Local: {{locacao.local_devolucao}}

Dúvidas? Entre em contacto!
*{{empresa.nome_fantasia}}*', NULL, 1);

-- 5. Payment Reminder - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'pt_PT', 'email', 'Lembrete de Pagamento - Fatura #{{fatura.numero}}', '<p>Olá, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Lembramos que a sua fatura está próxima do vencimento:</p>\n\n            <div class="info-box">\n                <p><strong>Fatura:</strong> #{{fatura.numero}}</p>\n                <p><strong>Valor:</strong> {{fatura.valor}}</p>\n                <p><strong>Vencimento:</strong> {{fatura.data_vencimento}}</p>\n            </div>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Pagar Agora</a></p>', NULL, 1);

-- 5. Payment Reminder - SMS
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'pt_PT', 'sms', NULL, 'Fatura #{{fatura.numero}} de {{fatura.valor}} vence em {{fatura.data_vencimento}}. {{empresa.nome_fantasia}}', NULL, 1);

-- 5. Payment Reminder - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 5, 'pt_PT', 'whatsapp', NULL, '💳 *Lembrete de Pagamento*

Olá, {{cliente.primeiro_nome}}!

A sua fatura #{{fatura.numero}} no valor de {{fatura.valor}} vence em {{fatura.data_vencimento}}.

🔗 Link para pagamento:
{{fatura.link_boleto}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 6. Invoice Generated - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 6, 'pt_PT', 'email', 'Nova Fatura #{{fatura.numero}} - {{empresa.nome_fantasia}}', '<p>Olá, <strong>{{cliente.nome}}</strong>!</p>\n\n            <p>Uma nova fatura foi gerada para si:</p>\n\n            <div class="info-box">\n                <p><strong>Número:</strong> #{{fatura.numero}}</p>\n                <p><strong>Valor:</strong> {{fatura.valor}}</p>\n                <p><strong>Vencimento:</strong> {{fatura.data_vencimento}}</p>\n            </div>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Ver Fatura</a></p>', NULL, 1);

-- 7. Overdue Notice - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'pt_PT', 'email', 'Aviso: Fatura #{{fatura.numero}} em atraso', '<p>Olá, <strong>{{cliente.nome}}</strong>.</p>\n\n            <p>Identificámos que a fatura abaixo encontra-se em atraso:</p>\n\n            <div class="info-box">\n                <p><strong>Fatura:</strong> #{{fatura.numero}}</p>\n                <p><strong>Valor:</strong> {{fatura.valor}}</p>\n                <p><strong>Vencimento:</strong> {{fatura.data_vencimento}}</p>\n                <p><strong>Dias em atraso:</strong> {{fatura.dias_atraso}}</p>\n            </div>\n\n            <p>Regularize a sua situação para evitar a incidência de juros e multas, além de possíveis restrições em futuros alugueres.</p>\n\n            <p><a href="{{fatura.link_boleto}}" class="button">Pagar Agora</a></p>\n\n            <p><small>Se já efetuou o pagamento, por favor desconsidere este aviso.</small></p>', NULL, 1);

-- 7. Overdue Notice - SMS
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'pt_PT', 'sms', NULL, 'Fatura #{{fatura.numero}} em atraso. Valor: {{fatura.valor}}. Regularize para evitar juros. {{empresa.nome_fantasia}}', NULL, 1);

-- 7. Overdue Notice - WhatsApp
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 7, 'pt_PT', 'whatsapp', NULL, '⚠️ *Fatura em Atraso*

Olá, {{cliente.primeiro_nome}}.

Identificámos que a fatura #{{fatura.numero}} no valor de {{fatura.valor}} encontra-se em atraso.

Regularize a sua situação para evitar juros e multas.

🔗 Link para pagamento:
{{fatura.link_boleto}}

*{{empresa.nome_fantasia}}*', NULL, 1);

-- 8. License Expiring - Email
INSERT INTO message_templates (chave, template_type_id, locale, channel, subject, content, content_plain, is_active) VALUES
('0', 8, 'pt_PT', 'email', 'Atenção: A sua carta de condução está prestes a expirar', '<p>Olá, <strong>{{cliente.primeiro_nome}}</strong>!</p>\n\n            <p>Identificámos que a sua carta de condução está prestes a expirar:</p>\n\n            <div class="info-box">\n                <p><strong>Número da Carta:</strong> {{cliente.cnh_numero}}</p>\n                <p><strong>Data de Validade:</strong> {{cliente.cnh_validade}}</p>\n            </div>\n\n            <p>Lembre-se de renovar a sua carta para continuar a alugar veículos connosco sem interrupções.</p>\n\n            <p>Após a renovação, não se esqueça de atualizar os seus dados!</p>', NULL, 1);
