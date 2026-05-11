# Visão Geral do Projeto

## O que é 7Carros Locadora?

**7Carros Locadora** é um sistema de gestão para locadoras de veículos (car rental management system) desenvolvido em PHP 8.3 com arquitetura multi-tenant.

O sistema permite que **múltiplas empresas de locação de veículos** utilizem a mesma instalação do software, mantendo dados completamente isolados entre si através de um mecanismo de tenant baseado em sessão.

## Status Atual

**🔧 Early Development Stage**

A estrutura base do projeto está estabelecida:
- ✅ Estrutura de diretórios definida
- ✅ Configurações de ambiente implementadas
- ✅ Sistema de autoloading PSR-4 configurado
- ✅ Dependências principais instaladas via Composer
- ⏳ A maioria dos arquivos de implementação ainda precisa ser criada

## Stack Tecnológico

### Backend
- **PHP:** 8.3
- **Database:** MySQL com charset utf8mb4
- **Package Manager:** Composer
- **Autoloading:** PSR-4 (`App\` → `app/`)

### Bibliotecas Principais

#### Pagamentos
- `codephix/asaas-sdk` - Gateway Asaas
- `gerencianet/gerencianet-sdk-php` - Gerencianet
- Stripe API
- Banco Inter PIX

#### Comunicação
- `phpmailer/phpmailer` - Envio de emails
- Evolution API - Mensagens WhatsApp

#### Geração de Documentos
- `mpdf/mpdf` - Geração de PDFs
- `simplesoftwareio/simple-qrcode` - QR codes

#### Ferramentas
- `zircote/swagger-php` - Documentação de API

## Arquitetura Multi-tenant

O sistema utiliza **isolamento baseado em sessão** através da variável `$_SESSION['chave']`:

- Cada locadora tem uma `chave` (key) única
- Todas as queries de banco de dados filtram automaticamente por `chave`
- O QueryBuilder implementa este filtro de forma transparente
- Garante isolamento completo de dados entre locadoras

**Exemplo:**
```php
// Automaticamente filtra: WHERE chave = $_SESSION['chave']
$clientes = $qb->select('clientes');

// Desabilitar filtragem para tabelas compartilhadas
$dados = $qb->withoutChave()->select('tabela_publica');
```

## Arquitetura do Sistema

O projeto segue uma estrutura **MVC-like customizada**:

```
app/
├── Classes/         # Utilitários customizados (QueryBuilder)
├── Config/          # Constantes e configurações
├── Controllers/     # Lógica de negócio e handlers
├── Core/            # Framework core (Router, Request, Response)
├── Crons/           # Tarefas agendadas
├── Database/        # Migrações
├── Helpers/         # Funções auxiliares
├── Middleware/      # Pipeline de processamento
├── Models/          # Camada de dados
├── Routers/         # Definições de rotas
├── Services/        # Lógica de negócio complexa
└── Views/           # Templates HTML
```

## Planos de Assinatura

O sistema oferece **7 tiers de assinatura** (G, P0, P1, P2, P3, P4, P6):

- **G (Gratuito):** 3 veículos, 1 filial
- **P0 (Junior):** 3 veículos, 1 filial, consulta de multas básica
- **P1 (Iniciante):** 5 veículos, 1 filial
- **P2 (Intermediário):** 10 veículos, 1 filial
- **P3 (Avançado):** 20 veículos, 3 filiais
- **P4 (Ilimitado):** Veículos e filiais ilimitados
- **P6 (Ilimitado Mb):** Ilimitado com precificação por MB

Veja mais detalhes em [plans.md](./plans.md).

## Comandos Rápidos

```bash
# Setup inicial
composer install

# Regenerar autoloader
composer dump-autoload

# Acessar banco de dados
mysql -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora

# Criar backup
mysqldump -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora > backup.sql

# Executar migrations
php migrate.php

# Executar cron jobs
php cron.php
```

## Próximos Passos

Para começar o desenvolvimento:

1. **[Configurar ambiente](./environment.md)** - Setup de variáveis .env
2. **[Entender arquitetura](./architecture.md)** - Estrutura de diretórios e padrões
3. **[Estudar QueryBuilder](./querybuilder.md)** - Camada de abstração de banco
4. **[Revisar boas práticas](./best-practices.md)** - Segurança e guidelines
5. **[Compreender multi-tenancy](./multi-tenancy.md)** - Isolamento de dados

## Metadados do Projeto

| Propriedade | Valor |
|-------------|-------|
| **Nome** | 7Carros Locadora |
| **Tipo** | Sistema de gestão para locadoras de veículos |
| **Arquitetura** | Multi-tenant (session-based) |
| **PHP Version** | 8.3 |
| **Database** | MySQL (utf8mb4) |
| **Package Manager** | Composer |
| **Namespace Root** | `App\` |
| **Autoloading** | PSR-4 |
| **Status** | Early development |

## Documentação Relacionada

- **[Arquitetura](./architecture.md)** - Estrutura detalhada do sistema
- **[Development](./development.md)** - Comandos e setup
- **[Multi-tenancy](./multi-tenancy.md)** - Mecanismo de isolamento
- **[QueryBuilder](./querybuilder.md)** - Camada de dados
- **[Configuração](./environment.md)** - Variáveis de ambiente
