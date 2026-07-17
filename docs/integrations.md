# External Integrations

This document describes all external service integrations available in the 7Carros Locadora system.

## Overview

The system integrates with various third-party services for payment processing, communication, document generation, and API documentation.

## WhoisJSON — Disponibilidade de Domínio

O fluxo **Website > Ativar Website** usa o endpoint
`GET https://whoisjson.com/api/v1/domain-availability` para verificar se o
domínio solicitado pode ser registrado. A autenticação é enviada no header
`Authorization: TOKEN={APIWHOISJSON_API_KEY}`.

- `available = true`: domínio disponível para registro
- `available = false`: domínio já registrado
- `available = "unknown"`: resultado inconclusivo; não liberar a ativação
- Falhas de conexão, quota, autenticação ou resposta inválida retornam erro
  genérico ao usuário e são registradas sem incluir o token

O sistema não usa `_forceRefresh`, retries automáticos nem entrega a resposta
bruta do provedor ao frontend.

## n8n — Clientes novos

O n8n consulta empresas que completam determinados dias no sistema por meio de
uma rota pública autenticada:

```http
GET /api/n8n/novos-clientes?dias=1,5,10,15,30,60
X-N8N-Token: {N8N_API_TOKEN}
```

`dias` aceita até 50 inteiros entre 1 e 36500, separados por vírgula. A resposta é um
array JSON com uma linha para cada funcionário ativo cuja função seja
`Proprietário` e que tenha celular e e-mail preenchidos:

```json
[
  {
    "id": 123,
    "chave": "TENANT",
    "tel_cel": "5511999999999",
    "email": "cliente@empresa.com"
  }
]
```

A idade considera o cadastro da empresa, no timezone configurado para o tenant.
Empresas com vários Proprietários podem gerar várias linhas. O celular é
retornado apenas com dígitos. A rota responde `401` para token inválido, `400`
para o parâmetro `dias` inválido e `503` quando o segredo não está configurado.

## Table of Contents

- [WhoisJSON — Disponibilidade de Domínio](#whoisjson--disponibilidade-de-domínio)
- [n8n — Clientes novos](#n8n--clientes-novos)
- [Payment Gateways](#payment-gateways)
- [Communication](#communication)
- [Document Generation](#document-generation)
- [API Documentation](#api-documentation)

## Payment Gateways

O sistema possui um modulo completo de gateways de pagamento com 10 provedores suportados (Asaas, Stripe, Square, Cora, EfiPay, Inter, Bradesco, Itau, Bancard, Pagopar).

**Documentacao completa:** [gateways.md](./gateways.md) - Arquitetura, tabelas, rotas, fluxo de pagamento publico, webhooks e configuracao.

**Resumo:**
- Arquitetura: `PaymentGatewayInterface` + `AbstractPaymentGateway` + `GatewayFactory`
- Credenciais criptografadas com AES-256-CBC por tenant
- Link publico de pagamento (`/pagar/{codigo}`)
- Webhooks por gateway (`/webhook/{gateway_code}`)
- Suporte a PIX, Boleto, Cartao de Credito/Debito
- Tokenizacao de cartao e checkout transparente

## Communication

> **⚠️ IMPORTANTE:** Para envio de mensagens (email, SMS, WhatsApp), use o **[Sistema de Mensageria com RabbitMQ](./messaging.md)** que processa envios em segundo plano. Os services abaixo são usados internamente pelo sistema de mensageria.

### PHPMailer (Email)

**Purpose:** Send emails via SMTP.

**Note:** Este service é usado internamente pelo sistema de mensageria. Para enviar emails, use `queue_message('email', [...])` conforme documentado em [messaging.md](./messaging.md).

**Composer Package:** `phpmailer/phpmailer`

#### Installation

```bash
composer require phpmailer/phpmailer
```

#### Configuration

See `docs/environment.md` for SMTP configuration details.

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@7carros.com
MAIL_FROM_NAME="7Carros Locadora"
```

#### Usage Example

```php
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private PHPMailer $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true);

        $this->mailer->isSMTP();
        $this->mailer->Host = env('MAIL_HOST');
        $this->mailer->Port = env('MAIL_PORT');
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = env('MAIL_USERNAME');
        $this->mailer->Password = env('MAIL_PASSWORD');
        $this->mailer->SMTPSecure = env('MAIL_ENCRYPTION');
        $this->mailer->CharSet = 'UTF-8';

        $this->mailer->setFrom(
            env('MAIL_FROM_ADDRESS'),
            env('MAIL_FROM_NAME')
        );
    }

    public function enviarEmailBoasVindas(string $email, string $nome): void {
        try {
            $this->mailer->addAddress($email, $nome);
            $this->mailer->Subject = 'Bem-vindo à 7Carros Locadora';
            $this->mailer->Body = $this->renderTemplate('emails/boas-vindas', [
                'nome' => $nome
            ]);
            $this->mailer->isHTML(true);

            $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email error: {$this->mailer->ErrorInfo}");
            throw $e;
        }
    }

    private function renderTemplate(string $template, array $data): string {
        extract($data);
        ob_start();
        include __DIR__ . "/../Views/$template.php";
        return ob_get_clean();
    }
}
```

#### Email Templates

```php
<!-- app/Views/emails/boas-vindas.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bem-vindo, <?= htmlspecialchars($nome) ?>!</h1>
        <p>Obrigado por se cadastrar na 7Carros Locadora.</p>
    </div>
</body>
</html>
```

---

### WhatsApp API

**Purpose:** Send WhatsApp messages programmatically.

**Note:** Este service é usado internamente pelo sistema de mensageria. Para enviar mensagens WhatsApp, use `queue_message('whatsapp', [...])` conforme documentado em [messaging.md](./messaging.md). NUNCA chame o `WhatsAppService` diretamente nem cite o nome do provedor em código cliente-facing.

#### Configuration

```env
WHATSAPP_API_URL=https://provedor.example.com
WHATSAPP_API_ADMIN_TOKEN=token-admin-do-provedor
WHATSAPP_API_INSTANCE_TOKEN=token-da-instancia-system-7carros
WHATSAPP_API_PROXY_PROTOCOL=http
WHATSAPP_API_PROXY_HOST=
WHATSAPP_API_PROXY_PORT=
WHATSAPP_API_PROXY_USERNAME=
WHATSAPP_API_PROXY_PASSWORD=
```

#### Modelo de instância (multi-tenant)

Cada tenant que conecta um WhatsApp gera uma "instância" no provedor. A `whatsapp.instanceName` (`locadora_{uuid}`) serve como **token de autenticação** das chamadas de sessão e mensagem (header `token: <instanceName>`); o `whatsapp.instanceId` guarda o UUID que o provedor devolve na criação (necessário para chamadas administrativas como `DELETE /admin/users/{id}`).

#### Fluxo de envio

`WhatsAppService::send($payload)` é o ponto de entrada. Ele resolve a instância (por `id_matriz_filial` para tenants, ou `WHATSAPP_API_INSTANCE_TOKEN` para `_system_message`), formata o telefone (só dígitos, com código do país), e dispara `POST /chat/send/text`, `POST /chat/send/image` ou `POST /chat/send/document` conforme o conteúdo. Mídias precisam ser baixadas e convertidas para base64 (data URI) — o serviço cuida disso automaticamente quando `media_url` é fornecido.

#### Proxy de saída

Quando `WHATSAPP_API_PROXY_HOST` está definido, o `WhatsappController` configura o proxy via `POST /session/proxy` logo após criar a instância (faz parte do fluxo de "Adicionar conexão"). Mesmas credenciais para todas as instâncias (vindas do `.env`).

## Document Generation

### mPDF

**Purpose:** Generate PDF documents from HTML.

No projeto 7Carros Locadora, use sempre **`PdfHelper`** (watermark, imagens, margens para header/footer HTML). Ver [pdf.md](./pdf.md).

**Composer Package:** `mpdf/mpdf`

#### Installation

```bash
composer require mpdf/mpdf
```

#### Usage Example

```php
<?php
use Mpdf\Mpdf;

class PdfService {
    public function gerarContrato(array $contrato): string {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20
        ]);

        $html = $this->renderContratoHtml($contrato);

        $mpdf->WriteHTML($html);

        $filename = "contrato_{$contrato['id']}_" . date('YmdHis') . ".pdf";
        $filepath = __DIR__ . "/../../storage/uploads/$filename";

        $mpdf->Output($filepath, 'F');

        return $filename;
    }

    private function renderContratoHtml(array $contrato): string {
        ob_start();
        include __DIR__ . '/../Views/pdfs/contrato.php';
        return ob_get_clean();
    }
}
```

#### PDF Template

```php
<!-- app/Views/pdfs/contrato.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        h1 { text-align: center; }
        .info { margin: 20px 0; }
    </style>
</head>
<body>
    <h1>CONTRATO DE LOCAÇÃO DE VEÍCULOS</h1>

    <div class="info">
        <p><strong>Contrato Nº:</strong> <?= $contrato['id'] ?></p>
        <p><strong>Cliente:</strong> <?= htmlspecialchars($contrato['cliente_nome']) ?></p>
        <p><strong>CPF/CNPJ:</strong> <?= $contrato['cliente_cpf_cnpj'] ?></p>
        <p><strong>Veículo:</strong> <?= htmlspecialchars($contrato['veiculo_descricao']) ?></p>
        <p><strong>Placa:</strong> <?= $contrato['veiculo_placa'] ?></p>
        <p><strong>Período:</strong> <?= date('d/m/Y', strtotime($contrato['data_inicio'])) ?> a <?= date('d/m/Y', strtotime($contrato['data_fim'])) ?></p>
        <p><strong>Valor Total:</strong> R$ <?= number_format($contrato['valor_total'], 2, ',', '.') ?></p>
    </div>

    <p><!-- Cláusulas do contrato --></p>
</body>
</html>
```

---

### QR Code Generator

**Purpose:** Generate QR codes for PIX payments, contracts, etc.

**Composer Package:** `simplesoftwareio/simple-qrcode`

#### Installation

```bash
composer require simplesoftwareio/simple-qrcode
```

#### Usage Example

```php
<?php
use SimpleSoftwareIO\QrCode\Generator;

class QrCodeService {
    private Generator $qrCode;

    public function __construct() {
        $this->qrCode = new Generator();
    }

    public function gerarQrCodePix(string $pixCopiaECola): string {
        $filename = 'qrcode_' . uniqid() . '.png';
        $filepath = __DIR__ . "/../../storage/uploads/$filename";

        $this->qrCode->format('png')
            ->size(300)
            ->generate($pixCopiaECola, $filepath);

        return $filename;
    }

    public function gerarQrCodeContrato(int $contratoId): string {
        $url = env('APP_URL') . "/contratos/visualizar/$contratoId";

        $filename = "qrcode_contrato_{$contratoId}.png";
        $filepath = __DIR__ . "/../../storage/uploads/$filename";

        $this->qrCode->format('png')
            ->size(200)
            ->generate($url, $filepath);

        return $filename;
    }
}
```

## API Documentation

### Swagger PHP

**Purpose:** Generate API documentation from code annotations.

**Composer Package:** `zircote/swagger-php`

#### Installation

```bash
composer require zircote/swagger-php
```

#### Usage Example

```php
<?php
namespace App\Controllers;

/**
 * @OA\Info(
 *     title="7Carros Locadora API",
 *     version="1.0.0",
 *     description="API para gestão de locação de veículos"
 * )
 */

/**
 * @OA\Get(
 *     path="/api/clientes",
 *     summary="Listar clientes",
 *     tags={"Clientes"},
 *     @OA\Response(
 *         response=200,
 *         description="Lista de clientes",
 *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Cliente"))
 *     )
 * )
 */
class ClienteController {
    public function index() {
        // Implementation
    }
}

/**
 * @OA\Schema(
 *     schema="Cliente",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="nome_rsocial", type="string"),
 *     @OA\Property(property="cpf_cnpj", type="string")
 * )
 */
```

#### Generate Documentation

```bash
vendor/bin/openapi app/ --output public/docs/openapi.json
```

## Testing Integrations

### Mock External Services

For testing, mock external API calls:

```php
<?php
class MockAsaasService extends AsaasService {
    public function criarCliente(array $dados): array {
        return [
            'id' => 'mock_cus_' . uniqid(),
            'name' => $dados['nome_rsocial']
        ];
    }
}

// In tests
$service = new MockAsaasService();
```

### Use Sandbox Environments

Always test with sandbox/staging environments first:

```env
# Testing
ASAAS_ENVIRONMENT=sandbox
GERENCIANET_SANDBOX=true
STRIPE_SECRET_KEY=sk_test_xxxxx  # Test key
```

## Related Documentation

- **Environment:** `docs/environment.md` - Configuration details
- **Best Practices:** `docs/best-practices.md` - Security guidelines
- **Development:** `docs/development.md` - Setup commands
