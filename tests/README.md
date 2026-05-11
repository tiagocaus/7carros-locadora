# Testes do Sistema de Mensageria

Arquivos de teste para simular envios de mensagens através do sistema de mensageria com RabbitMQ.

## Arquivos Disponíveis

### 1. `test_email_queue.php`
Testa o envio de emails através da fila.

**Uso:**
```bash
php tests/test_email_queue.php
```

**Testa:**
- Email simples
- Email com cópia (CC)
- Email com anexo
- Email completo (todos os campos)

### 2. `test_whatsapp_queue.php`
Testa o envio de mensagens WhatsApp através da fila.

**Uso:**
```bash
php tests/test_whatsapp_queue.php
```

**Testa:**
- Mensagem de texto simples
- Mensagem formatada (com emojis)
- Mensagem com mídia
- Notificação de contrato
- Diferentes formatos de número de telefone

**Importante:** Substitua o número de teste (`5511999999999`) por um número real para testes funcionais.

### 3. `test_sms_queue.php`
Testa o envio de SMS através da fila.

**Uso:**
```bash
php tests/test_sms_queue.php
```

**Testa:**
- SMS simples
- Código de verificação
- Lembrete de vencimento
- Confirmação de ação
- SMS longo (teste de limite de caracteres)

**Nota:** O serviço de SMS está com estrutura base e precisa de integração com provedor de SMS.

### 4. `test_all_messages.php`
Teste completo que envia todos os tipos de mensagens em uma única execução.

**Uso:**
```bash
php tests/test_all_messages.php
```

**Testa:**
- Múltiplos emails
- Múltiplos SMS
- Múltiplos WhatsApp

### 5. `check_queue_status.php`
Verifica o status das mensagens na fila consultando o banco de dados.

**Uso:**
```bash
php tests/check_queue_status.php
```

**Mostra:**
- Estatísticas gerais
- Mensagens por tipo
- Mensagens por status
- Mensagens pendentes
- Mensagens falhadas
- Mensagens enviadas hoje
- Mensagens com mais tentativas

## Fluxo de Teste Completo

### Passo 1: Adicionar Mensagens à Fila
```bash
# Teste individual
php tests/test_email_queue.php
php tests/test_whatsapp_queue.php
php tests/test_sms_queue.php

# Ou teste completo
php tests/test_all_messages.php
```

### Passo 2: Verificar Status das Mensagens
```bash
php tests/check_queue_status.php
```

### Passo 3: Processar Mensagens (Worker CRON)
```bash
php cron.php
```

### Passo 4: Monitorar Logs
```bash
tail -f storage/logs/cron/execution.log
```

### Passo 5: Verificar Status Novamente
```bash
php tests/check_queue_status.php
```

## Consultas SQL Úteis

### Ver todas as mensagens
```sql
SELECT * FROM messages_queue ORDER BY id DESC LIMIT 20;
```

### Mensagens por status
```sql
SELECT status, COUNT(*) as total 
FROM messages_queue 
GROUP BY status;
```

### Mensagens pendentes
```sql
SELECT * FROM messages_queue 
WHERE status = 'pending' 
ORDER BY created_at ASC;
```

### Mensagens falhadas
```sql
SELECT id, type, attempts, error_message, created_at 
FROM messages_queue 
WHERE status = 'failed' 
ORDER BY id DESC;
```

### Mensagens enviadas hoje
```sql
SELECT type, COUNT(*) as total 
FROM messages_queue 
WHERE status = 'sent' AND DATE(processed_at) = CURDATE()
GROUP BY type;
```

### Reprocessar mensagem falhada
```sql
UPDATE messages_queue 
SET status = 'pending', attempts = 0, error_message = NULL 
WHERE id = X;
```

## Troubleshooting

### Mensagens não são processadas
1. Verifique se o RabbitMQ está rodando e acessível
2. Verifique as credenciais no `.env`
3. Execute o worker manualmente: `php cron.php`
4. Verifique os logs: `tail -f storage/logs/cron/execution.log`

### Mensagens ficam em "pending"
- Verifique se o CRON está configurado e rodando
- Verifique se há erros nos logs
- Verifique a conexão com RabbitMQ

### Mensagens falham
- Verifique as configurações do serviço (SMTP, Evolution API)
- Verifique as credenciais no `.env`
- Consulte `error_message` na tabela `messages_queue`

## Configuração Necessária

Antes de executar os testes, certifique-se de que:

1. ✅ Variáveis de ambiente configuradas no `.env.development`
2. ✅ RabbitMQ está rodando e acessível
3. ✅ Banco de dados está configurado
4. ✅ Migração da tabela `messages_queue` foi executada
5. ✅ Dependências instaladas (`composer install`)

## Variáveis de Ambiente Necessárias

```env
# RabbitMQ
RABBITMQ_HOST=rabbitmq.hostcia.net
RABBITMQ_PORT=5672
RABBITMQ_USER=guestuser
RABBITMQ_PASSWORD=sua_senha
RABBITMQ_VHOST=/
RABBITMQ_QUEUE_NAME=locadora_messages_queue

# Email (SMTP)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu_email@gmail.com
MAIL_PASSWORD=sua_senha_app
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@7carros.com
MAIL_FROM_NAME="7Carros Locadora"

# WhatsApp (Evolution API)
MENSAGERIA_API_URL=https://mensageria.hostcia.net
MENSAGERIA_API_KEY=sua_chave_api
EVOLUTION_INSTANCE_NAME=7Carros
```

---

**Última atualização:** 2025-01-27

