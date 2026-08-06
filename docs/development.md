# Development Guide

This document contains essential commands and workflows for developing the 7Carros Locadora application.

## Quick Start

### First-Time Setup

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env.development
   # Edit .env.development with your local settings
   ```

3. **Run migrations:**
   ```bash
   php migrate.php
   ```

4. **Start development server:**
   ```bash
   php -S localhost:8000 -t public/
   ```

## Composer Commands

Composer manages PHP dependencies and class autoloading.

### Install Dependencies

Install all packages defined in `composer.json`:
```bash
composer install
```

### Update Dependencies

Update packages to their latest compatible versions:
```bash
composer update
```

Update a specific package:
```bash
composer update vendor/package-name
```

### Add New Package

```bash
composer require vendor/package-name
```

### Remove Package

```bash
composer remove vendor/package-name
```

### Regenerate Autoloader

**Run this command after adding new classes** to update PSR-4 autoloading:
```bash
composer dump-autoload
```

When to run:
- After creating new controller, model, or service classes
- After renaming or moving classes
- After changing namespace declarations

Example workflow:
```bash
# Create new file
echo "<?php namespace App\Services; class NovoService {}" > app/Services/NovoService.php

# Regenerate autoloader
composer dump-autoload

# Now you can use the class
```

### Development Mode

Install dev dependencies (PHPUnit, debugging tools):
```bash
composer install --dev
```

Production install (excludes dev dependencies):
```bash
composer install --no-dev
```

## Database Commands

### MySQL CLI Access

Connect to MySQL command line:
```bash
mysql -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora
```

Example queries:
```sql
-- Show all tables
SHOW TABLES;

-- Describe table structure
DESCRIBE clientes;

-- Check tenant data
SELECT chave, COUNT(*) FROM clientes GROUP BY chave;

-- Exit MySQL CLI
exit;
```

### Database Migrations

Run pending migrations:
```bash
php migrate.php
```

Run migrations for specific environment:
```bash
php migrate.php --env=development
```

Rollback last migration batch:
```bash
php migrate.php rollback
```

See `docs/migrations.md` for migration file format and standards.

### Database Backup

Create full database backup:
```bash
mysqldump -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora > backup_$(date +%Y%m%d_%H%M%S).sql
```

Backup with gzip compression:
```bash
mysqldump -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

Backup specific table:
```bash
mysqldump -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora clientes > backup_clientes.sql
```

### Database Restore

Restore from backup:
```bash
mysql -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora < backup.sql
```

Restore from gzipped backup:
```bash
gunzip < backup.sql.gz | mysql -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora
```

## Testing

### Syntax Check

Check PHP syntax for a single file:
```bash
php -l path/to/file.php
```

Check all PHP files in directory:
```bash
find app/ -name "*.php" -exec php -l {} \;
```

### PHPUnit Tests

Run all tests:
```bash
php vendor/bin/phpunit
```

Run specific test file:
```bash
php vendor/bin/phpunit tests/Unit/QueryBuilderTest.php
```

Run tests with coverage report:
```bash
php vendor/bin/phpunit --coverage-html coverage/
```

Run tests in specific group:
```bash
php vendor/bin/phpunit --group database
```

### Code Style

Check code style (if installed):
```bash
php vendor/bin/phpcs app/
```

Auto-fix code style:
```bash
php vendor/bin/phpcbf app/
```

## Cron Jobs

The cron system manages scheduled tasks. See `docs/cron.md` for comprehensive documentation.

### Execute All Cron Jobs

Run all registered cron jobs manually:
```bash
php cron.php
```

### Execute Specific Cron

Run a specific cron job by name:
```bash
php cron.php --job=BackupDatabase
```

### Test Cron Configuration

Validate cron configuration without executing:
```bash
php cron.php --validate
```

### View Cron Logs

```bash
tail -f storage/logs/cron.log
```

## Development Server

### Built-in PHP Server

Start development server on port 8000:
```bash
php -S localhost:8000 -t public/
```

Custom port:
```bash
php -S localhost:3000 -t public/
```

Bind to all interfaces (access from network):
```bash
php -S 0.0.0.0:8000 -t public/
```

### Apache/Nginx

For Apache, ensure `.htaccess` is configured and `mod_rewrite` is enabled.

For Nginx, configure document root to `public/` and set up URL rewriting:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Logging

### View Application Logs

Real-time log monitoring:
```bash
tail -f storage/logs/app.log
```

View last 100 lines:
```bash
tail -n 100 storage/logs/app.log
```

Search logs for errors:
```bash
grep "ERROR" storage/logs/app.log
```

### Clear Logs

```bash
> storage/logs/app.log
```

## Cache Management

### Clear Application Cache

```bash
rm -rf storage/cache/*
```

### Clear Specific Cache

```bash
rm storage/cache/routes.cache
rm storage/cache/views/*.php
```

## File Permissions

Ensure storage directories are writable:
```bash
chmod -R 775 storage/
chmod -R 775 storage/cache/
chmod -R 775 storage/logs/
chmod -R 775 storage/uploads/
```

For development (be careful in production):
```bash
chmod -R 777 storage/
```

## Git Workflow (When Applicable)

### Check Status
```bash
git status
```

### Stage and Commit
```bash
git add .
git commit -m "Descrição das alterações"
```

### Pull Latest Changes
```bash
git pull origin main
```

### Push Changes
```bash
git push origin main
```

### Create Feature Branch
```bash
git checkout -b feature/nova-funcionalidade
```

## Debugging

### Enable PHP Error Display

In development, ensure errors are visible. Check `.env.development`:
```
APP_DEBUG=true
```

Or set in PHP:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Dump and Die

Quick debugging:
```php
var_dump($variable);
die();

// Or use helper (if available)
dd($variable);
```

### Debug Query Execution

Enable query logging in QueryBuilder (if implemented):
```php
$qb->enableQueryLog();
// ... run queries ...
var_dump($qb->getQueryLog());
```

## Performance Profiling

### Check Execution Time

```php
$start = microtime(true);
// ... code to profile ...
$time = microtime(true) - $start;
echo "Execution time: " . number_format($time, 4) . "s\n";
```

### Memory Usage

```php
echo "Memory used: " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "Peak memory: " . number_format(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
```

## Common Workflows

### Adding a New Feature

1. Create feature branch (if using Git)
2. If the feature reads, writes, or structures data, connect to the localhost database and inspect every affected table with `DESCRIBE`/`SHOW COLUMNS`
3. Test the intended query against the local schema; never infer column names from forms or existing code
4. Generate model/controller/service files
5. Run `composer dump-autoload`
6. Write tests
7. Implement feature
8. Test manually and with PHPUnit
9. Commit and push

If the local schema, migrations, and production schema disagree, stop and
investigate the divergence before implementing. `temp-bd.txt` may be used only
for read-only production diagnostics and never replaces the localhost check.

### Debugging Database Issues

1. Check MySQL connection: `mysql -u... -p...`
2. Verify table structure: `DESCRIBE table_name;`
3. Test query in MySQL CLI first
4. Enable QueryBuilder logging
5. Check `storage/logs/app.log`

### Deploying Changes

1. Pull latest code: `git pull`
2. Install dependencies: `composer install --no-dev`
3. Run migrations: `php migrate.php`
4. Clear cache: `rm -rf storage/cache/*`
5. Regenerate autoloader: `composer dump-autoload --optimize`
6. Restart web server if needed

### Publicacao FTP atomica de arquivos PHP

Publicacoes manuais com `temp-lftp.txt` devem preservar o caminho relativo e
nao podem substituir um PHP enquanto ele ainda esta sendo transferido. Para
cada arquivo de runtime:

1. validar localmente com `php -l`;
2. enviar no mesmo diretorio remoto com sufixo temporario `.uploading`;
3. depois que o upload terminar, renomear o temporario para o nome definitivo
   com `mv` no LFTP;
4. remover temporarios somente se uma publicacao falhar;
5. nunca enviar `temp-lftp.txt`, credenciais, testes ou arquivos fora do escopo.

Exemplo conceitual:

```lftp
put -o app/Services/MeuService.php.uploading app/Services/MeuService.php
mv app/Services/MeuService.php.uploading app/Services/MeuService.php
```

Para um conjunto de arquivos, conclua todos os `put` antes dos `mv`. Assim, o
servidor continua usando a versao anterior se alguma transferencia falhar.

## Environment-Specific Commands

### Development
```bash
# Use development environment
export APP_ENV=development

# Install with dev dependencies
composer install --dev
```

### Production
```bash
# Use production environment
export APP_ENV=production

# Install without dev dependencies
composer install --no-dev --optimize-autoloader
```

## Troubleshooting

### "Class not found" Error
```bash
composer dump-autoload
```

### Permission Denied on Storage
```bash
chmod -R 775 storage/
```

### Database Connection Failed
```bash
# Verify credentials in .env file
cat .env.development | grep DB_

# Test MySQL connection
mysql -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora -e "SELECT 1;"
```

### Composer Memory Limit
```bash
php -d memory_limit=-1 /usr/local/bin/composer install
```

## Related Documentation

- **Architecture:** `docs/architecture.md` - Project structure
- **Database:** `docs/database.md` - Migrations and models
- **QueryBuilder:** `docs/querybuilder.md` - Database layer
- **Cron Jobs:** `docs/cron.md` - Scheduled tasks
- **Environment:** `docs/environment.md` - Configuration
