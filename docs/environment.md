# Environment Configuration

This document describes how to configure the application using environment variables and `.env` files.

## Overview

The 7Carros Locadora system uses `.env` files to manage environment-specific configuration (database credentials, API keys, feature flags, etc.).

**Benefits:**
- Separate configuration from code
- Environment-specific settings (development vs production)
- Keep sensitive credentials out of version control

## Environment Files

### File Structure

```
locadora.7carros.com/
├── .env.example          # Template with all available options (committed)
├── .env.development      # Development settings (NOT committed)
├── .env.production       # Production settings (NOT committed)
└── .env.local            # Local overrides (NOT committed)
```

### .env.example

Template file showing all available configuration options with placeholder values. **This file is committed to Git** to document available settings.

```env
# Application
APP_NAME="7Carros Locadora"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_HOST=localhost
DB_DATABASE=7carros_locadora
DB_USERNAME=your_username
DB_PASSWORD=your_password
DB_PORT=3306

# Email (SMTP)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@example.com
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@7carros.com
MAIL_FROM_NAME="7Carros Locadora"

# ... more settings
```

### .env.development

Development-specific configuration. **Never commit this file.**

```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_HOST=localhost
DB_DATABASE=7carros_locadora_dev
DB_USERNAME=7carros_locadora
DB_PASSWORD=wCz5Ex9jQ0Xped7

MAIL_HOST=mailhog
MAIL_PORT=1025
```

### .env.production

Production configuration with real credentials. **Never commit this file.**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://locadora.7carros.com

DB_HOST=production-db.example.com
DB_DATABASE=7carros_locadora
DB_USERNAME=prod_user
DB_PASSWORD=secure_production_password

MAIL_HOST=smtp.sendgrid.net
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.xxxxxxxxxxxx
```

## Configuration Categories

### Application Settings

```env
# Application identity and behavior
APP_NAME="7Carros Locadora"
APP_ENV=development              # development, production, testing
APP_DEBUG=true                   # Enable detailed error messages
APP_URL=http://localhost:8000    # Base URL of the application
APP_TIMEZONE=America/Sao_Paulo   # Default timezone
APP_LOCALE=pt_BR                 # Default locale
```

### Database Configuration

```env
# MySQL connection settings
DB_HOST=localhost                # Database server hostname
DB_DATABASE=7carros_locadora     # Database name
DB_USERNAME=7carros_locadora     # Database username
DB_PASSWORD=wCz5Ex9jQ0Xped7      # Database password
DB_PORT=3306                     # Database port (default: 3306)
DB_CHARSET=utf8mb4               # Character set
DB_COLLATION=utf8mb4_unicode_ci  # Collation
```

### Email Configuration (SMTP)

```env
# Email sending via SMTP
MAIL_HOST=smtp.gmail.com         # SMTP server
MAIL_PORT=587                    # SMTP port (587 for TLS, 465 for SSL)
MAIL_USERNAME=your@email.com     # SMTP username
MAIL_PASSWORD=your_password      # SMTP password or app-specific password
MAIL_ENCRYPTION=tls              # tls or ssl
MAIL_FROM_ADDRESS=noreply@7carros.com
MAIL_FROM_NAME="7Carros Locadora"
```

**Gmail Configuration Example:**
```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your.email@gmail.com
MAIL_PASSWORD=your_app_password  # Generate at https://myaccount.google.com/apppasswords
MAIL_ENCRYPTION=tls
```

### Payment Gateway Integrations

#### Asaas

```env
ASAAS_API_KEY=your_asaas_api_key
ASAAS_WALLET_ID=your_wallet_id
ASAAS_ENVIRONMENT=sandbox        # sandbox or production
```

#### Stripe

```env
STRIPE_PUBLIC_KEY=pk_test_xxxxxxx
STRIPE_SECRET_KEY=sk_test_xxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

#### Gerencianet

```env
GERENCIANET_CLIENT_ID=Client_Id_xxxxxxx
GERENCIANET_CLIENT_SECRET=Client_Secret_xxxxxxx
GERENCIANET_SANDBOX=true         # true for sandbox, false for production
GERENCIANET_CERTIFICATE=/path/to/cert.pem
```

#### Banco Inter (PIX)

```env
INTER_CLIENT_ID=your_client_id
INTER_CLIENT_SECRET=your_client_secret
INTER_CERTIFICATE=/path/to/cert.pem
INTER_CERTIFICATE_KEY=/path/to/key.key
INTER_CONTA_CORRENTE=12345678
```

### WhatsApp Integration (Evolution API)

```env
EVOLUTION_API_KEY=https://evolution-api.example.com
EVOLUTION_API_KEY=your_api_key
EVOLUTION_INSTANCE_NAME=7carros_instance
```

**Note:** Para envio de mensagens WhatsApp, use o sistema de mensageria conforme [messaging.md](./messaging.md).

### RabbitMQ Configuration (Message Queue)

```env
# RabbitMQ Connection
RABBITMQ_HOST=rabbitmq.hostcia.net
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE_NAME=messages_queue

# Message Queue Processing
QUEUE_MAX_MESSAGES_PER_RUN=50      # Máximo de mensagens processadas por execução do CRON
QUEUE_MAX_ATTEMPTS=3                # Tentativas máximas antes de marcar como failed
QUEUE_CONSUME_TIMEOUT=30            # Timeout em segundos para aguardar mensagens
```

**Note:** Veja [messaging.md](./messaging.md) para documentação completa do sistema de mensageria.

### File Storage

```env
# Storage driver: local or s3
UPLOAD_DISK=local

# Local storage (if UPLOAD_DISK=local)
UPLOAD_PATH=/storage/uploads

# AWS S3 (if UPLOAD_DISK=s3)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=7carros-uploads
AWS_URL=https://s3.amazonaws.com/7carros-uploads

# Upload limits
UPLOAD_MAX_SIZE=5242880          # 5MB in bytes
UPLOAD_ALLOWED_EXTENSIONS=jpg,jpeg,png,pdf,doc,docx
```

### Session Configuration

As configurações efetivas de sessão são definidas em `app/Core/Session.php`.
Atualmente o sistema usa 4 horas de inatividade (`session.gc_maxlifetime`
e `session.cookie_lifetime` = `14400`) e heartbeat via `api.js` enquanto a
aba principal está visível.

> Observação: variáveis `SESSION_*` em `.env` não são lidas pelo código atual.
> Para alterar o lifetime, ajuste `Session.php` ou implemente leitura explícita
> dessas variáveis.

### Logging

```env
LOG_CHANNEL=file                 # file, syslog, errorlog
LOG_LEVEL=debug                  # debug, info, warning, error
LOG_PATH=/storage/logs/app.log
```

### Cache Configuration

```env
CACHE_DRIVER=file                # file, redis, memcached
CACHE_TTL=3600                   # Default TTL in seconds

# Redis (if CACHE_DRIVER=redis)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DATABASE=0
```

## Loading Environment Variables

### Using PHP's getenv()

```php
<?php
// Load .env file (typically in bootstrap or index.php)
// If using a library like vlucas/phpdotenv:
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Access variables
$appName = getenv('APP_NAME');
$dbHost = getenv('DB_HOST');
$debug = getenv('APP_DEBUG') === 'true';
```

### Using $_ENV or $_SERVER

Depending on configuration, variables may also be available in superglobals:

```php
<?php
$dbHost = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'];
```

### Helper Function

Create a helper function for consistent access:

```php
<?php
/**
 * Get environment variable with optional default.
 */
function env(string $key, mixed $default = null): mixed {
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    // Convert string booleans
    if ($value === 'true') return true;
    if ($value === 'false') return false;
    if ($value === 'null') return null;

    return $value;
}

// Usage
$debug = env('APP_DEBUG', false);
$dbHost = env('DB_HOST', 'localhost');
```

## Configuration Classes

### Database Configuration

```php
<?php
namespace App\Config;

class Database {
    public static function getConnection(): array {
        return [
            'host' => env('DB_HOST', 'localhost'),
            'database' => env('DB_DATABASE', '7carros_locadora'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'port' => env('DB_PORT', 3306),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci')
        ];
    }
}

// Usage
$config = Database::getConnection();
$mysqli = new mysqli(
    $config['host'],
    $config['username'],
    $config['password'],
    $config['database'],
    $config['port']
);
```

### Mail Configuration

```php
<?php
namespace App\Config;

use PHPMailer\PHPMailer\PHPMailer;

class Mail {
    public static function getMailer(): PHPMailer {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = env('MAIL_HOST');
        $mail->Port = env('MAIL_PORT', 587);
        $mail->SMTPAuth = true;
        $mail->Username = env('MAIL_USERNAME');
        $mail->Password = env('MAIL_PASSWORD');
        $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls');
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            env('MAIL_FROM_ADDRESS', 'noreply@7carros.com'),
            env('MAIL_FROM_NAME', '7Carros Locadora')
        );

        return $mail;
    }
}
```

## Security Best Practices

### Never Commit Sensitive Files

Ensure `.gitignore` includes:

```gitignore
# Environment files
.env
.env.local
.env.development
.env.production
.env.*.local

# Keep template
!.env.example
```

### Use Strong Credentials

```env
# Bad - weak password
DB_PASSWORD=123456

# Good - strong password
DB_PASSWORD=kX9$mP2#vL8@qR5^wN3!
```

### Restrict File Permissions

In production, restrict access to `.env` files:

```bash
chmod 600 .env.production
```

### Validate Required Variables

Check for required variables at startup:

```php
<?php
function validateEnvironment(): void {
    $required = [
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    $missing = [];
    foreach ($required as $var) {
        if (empty(getenv($var))) {
            $missing[] = $var;
        }
    }

    if (!empty($missing)) {
        throw new RuntimeException(
            'Missing required environment variables: ' . implode(', ', $missing)
        );
    }
}

validateEnvironment();
```

## Environment-Specific Behavior

### Debug Mode

```php
<?php
if (env('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
```

### Feature Flags

Enable/disable features per environment:

```env
# Development
FEATURE_BETA_DASHBOARD=true
FEATURE_NEW_REPORTS=true

# Production
FEATURE_BETA_DASHBOARD=false
FEATURE_NEW_REPORTS=false
```

```php
<?php
if (env('FEATURE_BETA_DASHBOARD', false)) {
    // Show new dashboard
} else {
    // Show old dashboard
}
```

## First-Time Setup

### Copy Example File

```bash
cp .env.example .env.development
```

### Edit Configuration

```bash
nano .env.development
# or
vim .env.development
```

### Set Database Credentials

```env
DB_HOST=localhost
DB_DATABASE=7carros_locadora
DB_USERNAME=7carros_locadora
DB_PASSWORD=wCz5Ex9jQ0Xped7
```

### Test Configuration

```bash
php -r "
require 'vendor/autoload.php';
\$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
\$dotenv->load();
echo 'DB_HOST: ' . getenv('DB_HOST') . PHP_EOL;
echo 'DB_DATABASE: ' . getenv('DB_DATABASE') . PHP_EOL;
"
```

## Deployment

### Production Deployment Checklist

1. **Copy .env.example to .env.production**
   ```bash
   cp .env.example .env.production
   ```

2. **Set production values**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://locadora.7carros.com
   ```

3. **Use strong credentials**
   - Generate strong database passwords
   - Use production API keys (not sandbox)
   - Enable HTTPS-only sessions

4. **Secure file permissions**
   ```bash
   chmod 600 .env.production
   chown www-data:www-data .env.production
   ```

5. **Test configuration**
   ```bash
   php artisan config:cache  # If using Laravel
   # or manually test database connection
   ```

6. **Set environment variable** to use production config
   ```bash
   export APP_ENV=production
   ```

## Troubleshooting

### Environment Variables Not Loading

**Problem:** `getenv()` returns `false`

**Solutions:**
1. Verify `.env` file exists and is readable
2. Check file permissions: `ls -la .env*`
3. Ensure variables don't have quotes (unless needed):
   ```env
   # Correct
   DB_HOST=localhost

   # May cause issues
   DB_HOST="localhost"
   ```

### Database Connection Failed

**Problem:** Can't connect to database

**Check:**
```php
<?php
echo "Host: " . getenv('DB_HOST') . PHP_EOL;
echo "Database: " . getenv('DB_DATABASE') . PHP_EOL;
echo "Username: " . getenv('DB_USERNAME') . PHP_EOL;

// Test connection
$mysqli = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD'),
    getenv('DB_DATABASE')
);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
echo "Connected successfully\n";
```

### Email Not Sending

**Check SMTP configuration:**
```bash
# Test SMTP connection
telnet smtp.gmail.com 587
```

**Enable debug output:**
```php
$mail->SMTPDebug = 2; // Enable verbose debug output
```

## Related Documentation

- **Development:** `docs/development.md` - Setup and commands
- **Best Practices:** `docs/best-practices.md` - Security guidelines
- **Integrations:** `docs/integrations.md` - External services
- **Architecture:** `docs/architecture.md` - System design
