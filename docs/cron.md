# CRON System Documentation

⚠️ **SISTEMA OFICIAL DE TAREFAS AGENDADAS - SEMPRE SEGUIR ESTE PADRÃO**

Este documento descreve o sistema de CRON do projeto, usado para executar tarefas agendadas automaticamente.

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Estrutura de Arquivos](#estrutura-de-arquivos)
3. [Como Funciona](#como-funciona)
4. [Scheduler - Frequências de Jobs](#scheduler---frequências-de-jobs)
5. [Jobs Disponíveis](#jobs-disponíveis)
6. [Como Adicionar Novos Jobs](#como-adicionar-novos-jobs)
7. [Configuração do Servidor](#configuração-do-servidor)
8. [Logs e Monitoramento](#logs-e-monitoramento)
9. [Troubleshooting](#troubleshooting)
10. [Exemplos Práticos](#exemplos-práticos)

---

## Visão Geral

O sistema de CRON permite executar tarefas agendadas de forma automática e organizada. Cada tarefa é um "Job" independente que pode ser adicionado facilmente.

### Características

✅ **Modular**: Cada job em arquivo separado
✅ **Extensível**: Fácil adicionar novos jobs
✅ **Logging robusto**: Logs detalhados de execução
✅ **Error handling**: Captura erros sem interromper execução
✅ **Multi-tenant**: Respeita isolamento por `chave`
✅ **Idempotente**: Seguro executar múltiplas vezes
✅ **Scheduler interno**: Cada job define sua própria frequência (diário, horário, etc.)

### Execução

- **Método**: Comando PHP direto via CLI
- **Frequência crontab**: A cada 1 minuto (o Scheduler decide o que executar)
- **Comando**: `php cron.php`
- **Opções**:
  - `php cron.php --list` - Lista todos os jobs e suas frequências
  - `php cron.php --force` - Força execução de todos os jobs
  - `php cron.php --help` - Exibe ajuda

---

## Estrutura de Arquivos

```
project-root/
│
├── cron.php                          # Entry point CLI (raiz do projeto)
│
├── app/
│   ├── Crons/
│   │   ├── Scheduler.php             # Scheduler principal (gerencia frequências)
│   │   ├── ScheduledJob.php          # Wrapper com métodos fluentes de frequência
│   │   ├── CronRunner.php            # Orquestrador legado (mantido para --force)
│   │   └── Jobs/
│   │       ├── BaseJob.php           # Classe abstrata base
│   │       ├── ProcessMessageQueueJob.php    # Job de fila de mensagens
│   │       ├── CheckPreventiveMaintenanceJob.php  # Job de manutenção preventiva
│   │       └── (futuros jobs aqui)
│   │
│   └── Services/
│       └── ...
│
├── storage/
│   ├── cron/
│   │   └── schedule-state.json       # Estado de execução (última execução por job)
│   │
│   └── logs/
│       └── cron/
│           ├── .gitkeep
│           ├── execution.log         # Log de execuções (auto-criado)
│           └── error.log             # Log de erros fatais (auto-criado)
│
└── docs/
    └── cron.md                       # Esta documentação
```

---

## Como Funciona

### Fluxo de Execução

```
1. Servidor executa: php cron.php (via crontab, a cada 1 minuto)
   ↓
2. cron.php carrega autoloader e ambiente (.env)
   ↓
3. Cria instância de Scheduler
   ↓
4. Registra jobs com suas frequências:
   - ProcessMessageQueueJob -> everyMinute()
   - CheckPreventiveMaintenanceJob -> dailyAt('00:05')
   - RotateAuthorizationHoldsJob -> dailyAt('03:00')
   - SendDailyCronSummaryJob -> dailyAt('04:30')
   ↓
5. Scheduler verifica cada job:
   - isDue()? Verifica se é hora de executar (expressão cron)
   - wasRecentlyRun()? Evita duplicatas no mesmo minuto
   ↓
6. Para cada job que deve executar:
   - Executa método handle()
   - Registra logs
   - Marca como executado (salva em schedule-state.json)
   ↓
7. Jobs que não estão no horário são pulados (SKIP)
   ↓
8. Scheduler gera sumário de execução
   ↓
9. Script termina com exit code (0 = sucesso, 1 = erro)
```

### Componentes Principais

**1. cron.php** (Entry Point)
- Carrega ambiente e dependências
- Cria instância de Scheduler
- Registra jobs com frequências
- Executa e exibe sumário

**2. Scheduler** (Orquestrador Principal)
- Gerencia execução de múltiplos jobs com frequências diferentes
- Verifica se cada job deve executar (isDue)
- Evita duplicatas (wasRecentlyRun)
- Salva estado de execução em JSON
- Coleta resultados e gera estatísticas

**3. ScheduledJob** (Wrapper de Frequência)
- Encapsula um job com sua configuração de schedule
- Métodos fluentes: `everyFifteenMinutes()`, `dailyAt()`, `weekly()`, etc.
- Usa biblioteca `dragonmantank/cron-expression` para parsing

**4. BaseJob** (Classe Abstrata)
- Define estrutura de todos os jobs
- Gerencia execução e erros
- Fornece métodos de logging
- Calcula duração de execução

**5. CronRunner** (Legado)
- Mantido para compatibilidade
- Usado pelo `--force` para executar todos sem verificar schedule

---

## Scheduler - Frequências de Jobs

O Scheduler permite definir frequências diferentes para cada job usando uma API fluente inspirada no Laravel.

### Métodos de Frequência Disponíveis

| Método | Expressão Cron | Descrição |
|--------|---------------|-----------|
| `everyMinute()` | `* * * * *` | A cada minuto |
| `everyTwoMinutes()` | `*/2 * * * *` | A cada 2 minutos |
| `everyFiveMinutes()` | `*/5 * * * *` | A cada 5 minutos |
| `everyTenMinutes()` | `*/10 * * * *` | A cada 10 minutos |
| `everyFifteenMinutes()` | `*/15 * * * *` | A cada 15 minutos |
| `everyThirtyMinutes()` | `*/30 * * * *` | A cada 30 minutos |
| `hourly()` | `0 * * * *` | A cada hora (minuto 0) |
| `hourlyAt(30)` | `30 * * * *` | A cada hora no minuto 30 |
| `everyTwoHours()` | `0 */2 * * *` | A cada 2 horas |
| `everyThreeHours()` | `0 */3 * * *` | A cada 3 horas |
| `everyFourHours()` | `0 */4 * * *` | A cada 4 horas |
| `everySixHours()` | `0 */6 * * *` | A cada 6 horas |
| `daily()` | `0 0 * * *` | Diariamente à meia-noite |
| `dailyAt('08:00')` | `0 8 * * *` | Diariamente às 08:00 |
| `twiceDaily(1, 13)` | `0 1,13 * * *` | Duas vezes por dia (1h e 13h) |
| `weekly()` | `0 0 * * 0` | Semanalmente (domingo meia-noite) |
| `weeklyOn(1, '08:00')` | `0 8 * * 1` | Semanalmente (segunda 08:00) |
| `monthly()` | `0 0 1 * *` | Mensalmente (dia 1 meia-noite) |
| `monthlyOn(15, '10:00')` | `0 10 15 * *` | Mensalmente (dia 15 às 10:00) |
| `quarterly()` | `0 0 1 1,4,7,10 *` | Trimestralmente |
| `yearly()` | `0 0 1 1 *` | Anualmente (1 de janeiro) |

### Modificadores de Dia da Semana

| Método | Descrição |
|--------|-----------|
| `weekdays()` | Apenas dias de semana (seg-sex) |
| `weekends()` | Apenas finais de semana (sab-dom) |
| `sundays()` | Apenas domingos |
| `mondays()` | Apenas segundas |
| `tuesdays()` | Apenas terças |
| `wednesdays()` | Apenas quartas |
| `thursdays()` | Apenas quintas |
| `fridays()` | Apenas sextas |
| `saturdays()` | Apenas sábados |

### Métodos Auxiliares

| Método | Descrição |
|--------|-----------|
| `at('14:30')` | Define horário específico |
| `timezone('America/Sao_Paulo')` | Define timezone |
| `cron('0 */2 * * *')` | Expressão cron customizada |

### Exemplo de Uso no cron.php

```php
$scheduler = new \App\Crons\Scheduler();

// A cada 1 minuto
$scheduler->job(new ProcessMessageQueueJob())
          ->everyMinute();

// Diariamente às 00:05
$scheduler->job(new CheckPreventiveMaintenanceJob())
          ->dailyAt('00:05');

// Diariamente às 08:00 (apenas dias de semana)
$scheduler->job(new SendDailyReportJob())
          ->dailyAt('08:00')
          ->weekdays();

// Semanalmente às segundas 03:00
$scheduler->job(new CleanupOldLogsJob())
          ->weeklyOn(1, '03:00');

// Expressão cron customizada
$scheduler->job(new CustomJob())
          ->cron('0 */4 * * 1-5');  // A cada 4h, seg-sex

$summary = $scheduler->run();
```

### Arquivo de Estado

O Scheduler salva o estado de execução em `storage/cron/schedule-state.json`:

```json
{
  "App\\Crons\\Jobs\\ProcessMessageQueueJob": {
    "last_run": "2025-12-18 10:00:00",
    "next_run": "2025-12-18 10:01:00"
  },
  "App\\Crons\\Jobs\\CheckPreventiveMaintenanceJob": {
    "last_run": "2025-12-18 00:05:00",
    "next_run": "2025-12-19 00:05:00"
  }
}
```

Isso evita que o mesmo job seja executado múltiplas vezes no mesmo minuto, mesmo que o crontab rode mais de uma vez.

---

## Jobs Disponíveis

### 1. ProcessMessageQueueJob

**Descrição**: Processa mensagens da fila RabbitMQ (email, SMS, WhatsApp)

**Arquivo**: `app/Crons/Jobs/ProcessMessageQueueJob.php`

**Frequência**: Executa a cada 1 minuto

**Documentação**: Veja [messaging.md](./messaging.md) para documentação completa do sistema de mensageria.

**Configuração**: Configurado via variáveis de ambiente:
- `QUEUE_MAX_MESSAGES_PER_RUN` - Máximo de mensagens por execução (padrão: 50)
- `QUEUE_MAX_ATTEMPTS` - Tentativas máximas (padrão: 3)
- `QUEUE_CONSUME_TIMEOUT` - Timeout em segundos (padrão: 30)

---

### Jobs Diários e Resumo

Os jobs executados uma vez por dia ficam distribuídos na madrugada:

| Horário | Job |
|---------|-----|
| `00:05` | `CheckPreventiveMaintenanceJob` |
| `01:00` | `CleanupOldRecordingsJob` |
| `01:10` | `RenovarContratosJob` |
| `02:00` | `GerarEncargosFinanceiroJob` |
| `03:00` | `RotateAuthorizationHoldsJob` |
| `03:30` | `SerproAutoConsultaJob` |
| `04:30` | `SendDailyCronSummaryJob` |

O `Scheduler` registra o resultado dos jobs diários em `storage/cron/daily-summary/YYYY-MM-DD.json`.
O `SendDailyCronSummaryJob` envia um email único para `APP_COMPANY_EMAIL` com status, duração, mensagem e contadores de cada job diário.
Jobs recorrentes por minuto, 5, 15 ou 30 minutos não entram nesse resumo.

---

### 2. CalculateOverdueFeesJob

**Descrição**: Calcula juros e multa para lançamentos financeiros vencidos

**Arquivo**: `app/Crons/Jobs/CalculateOverdueFeesJob.php`

**Frequência**: Executa a cada 15 minutos (via crontab)

**Lógica**:

1. Busca lançamentos elegíveis:
   - `financeiro.tipo = 'R'`
   - `financeiro.pago = 'N'`
   - `financeiro.data_venci < CURDATE()`
   - `financeiro.valor_subtotal > 0`
   - `financeiro.id_forma_pagamento` vinculado a uma `formas_pagamento` do mesmo tenant
   - `formas_pagamento.multa > 0` ou `formas_pagamento.juros_por_dia > 0`

2. Atualiza em lote os valores calculados:
   - **Multa**: `financeiro.valor_subtotal * (formas_pagamento.multa / 100)`
   - **Juros**: `financeiro.valor_subtotal * (formas_pagamento.juros_por_dia / 100) * DATEDIFF(CURDATE(), financeiro.data_venci)`
   - **Total**: `valor_subtotal + juros + multa - desconto`

3. Recalcula enquanto o lançamento estiver vencido e pendente. Lançamentos pagos, despesas, sem vencimento válido, sem forma de pagamento ou com forma sem encargos não são alterados.

**Configuração de Taxas**:

As taxas vêm da tabela `formas_pagamento`:
- `multa` - Percentual de multa por atraso (ex: 2.00 = 2%)
- `juros_por_dia` - Percentual de juros por dia de atraso (ex: 0.033 = ~1% ao mês)

**Exemplo**:

```
Lançamento:
- valor_subtotal: R$ 1.000,00
- data_venci: 2025-10-15
- forma de pagamento: Boleto Bancário

Forma de Pagamento (Boleto):
- multa: 2.00 (2%)
- juros_por_dia: 0.033 (~1% ao mês)

Hoje: 2025-10-25 (10 dias de atraso)

Cálculo:
- multa = 1000 * (2.00 / 100) = R$ 20,00
- juros = 1000 * (0.033 / 100) * 10 = R$ 3,30

Total a pagar: R$ 1.023,30
```

---

## Como Adicionar Novos Jobs

### Passo 1: Criar Classe do Job

Crie um novo arquivo em `app/Crons/Jobs/NomeDoJob.php`:

```php
<?php

namespace App\Crons\Jobs;

class NomeDoJob extends BaseJob
{
    // Nome do job (para logs)
    protected string $name = 'Nome do Job';

    // Descrição (opcional)
    protected string $description = 'Descrição do que o job faz';

    /**
     * Implementar a lógica do job
     *
     * @return array Deve retornar: ['success' => bool, 'message' => string, 'data' => array]
     */
    protected function handle(): array
    {
        // Sua lógica aqui
        $this->log("Iniciando processamento...");

        try {
            // Fazer o trabalho
            $resultado = $this->minhaLogica();

            $this->log("Processamento concluído com sucesso");

            return [
                'success' => true,
                'message' => 'Job executado com sucesso',
                'data' => $resultado,
            ];

        } catch (\Exception $e) {
            $this->log("Erro: " . $e->getMessage(), 'ERROR');

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()],
            ];
        }
    }

    private function minhaLogica()
    {
        // Implementar lógica específica
        return ['processed' => 100];
    }
}
```

### Passo 2: Registrar Job no cron.php

Abra `cron.php` e adicione o novo job com sua frequência:

```php
// Create Scheduler
$scheduler = new \App\Crons\Scheduler();

// Jobs existentes
$scheduler->job(new \App\Crons\Jobs\ProcessMessageQueueJob())
          ->everyMinute();

$scheduler->job(new \App\Crons\Jobs\CheckPreventiveMaintenanceJob())
          ->dailyAt('00:05');

// ↓ ADICIONAR NOVO JOB AQUI ↓
$scheduler->job(new \App\Crons\Jobs\NomeDoJob())
          ->dailyAt('08:00');  // Escolha a frequência adequada

// Execute scheduled jobs
$summary = $scheduler->run();
```

**Frequências mais comuns:**
- `->everyMinute()` - Tarefas críticas/frequentes (fila de mensagens)
- `->hourly()` - Tarefas horárias (sincronizações)
- `->dailyAt('00:05')` - Tarefas diárias (relatórios, verificações)
- `->weeklyOn(1, '03:00')` - Tarefas semanais (limpeza, backups)

### Passo 3: Testar Manualmente

```bash
php cron.php
```

### Passo 4: Verificar Logs

```bash
cat storage/logs/cron/execution.log
```

---

## Configuração do Servidor

### Configuração do Crontab

**1. Abrir crontab para edição:**

```bash
crontab -e
```

**2. Adicionar entrada (executar a cada 1 minuto):**

```cron
* * * * * /usr/bin/php /path/to/project/cron.php >> /path/to/project/storage/logs/cron/execution.log 2>&1
```

**Explicação**:
- `* * * * *` - A cada minuto (o Scheduler decide o que executar)
- `/usr/bin/php` - Caminho do PHP (use `which php` para descobrir)
- `/path/to/project/cron.php` - Caminho absoluto do script
- `>> ... .log` - Redireciona output para arquivo de log
- `2>&1` - Redireciona stderr para stdout (captura erros)

**Por que a cada minuto?**
O Scheduler interno verifica a cada execução se cada job deve rodar baseado na frequência configurada. Isso permite ter jobs com frequências diferentes (1min, 5min, diário, semanal) usando uma única entrada no crontab.

**3. Salvar e sair** (`:wq` no vim)

**4. Verificar crontab instalado:**

```bash
crontab -l
```

### Frequências no Código (não no crontab)

Com o Scheduler, você **não precisa de múltiplas entradas no crontab**. Basta uma entrada rodando a cada minuto, e as frequências são definidas no código:

```php
// No cron.php - defina as frequências aqui:
$scheduler->job(new ProcessMessageQueueJob())->everyMinute();
$scheduler->job(new CheckMaintenanceJob())->dailyAt('00:05');
$scheduler->job(new SendReportsJob())->dailyAt('08:00');
$scheduler->job(new CleanupLogsJob())->weeklyOn(0, '03:00');
$scheduler->job(new BackupJob())->monthlyOn(1, '02:00');
```

Isso é mais flexível e fácil de manter do que múltiplas entradas no crontab.

### Descobrir Caminho do PHP

```bash
which php
# Output: /usr/bin/php (use este caminho no crontab)
```

### Descobrir Caminho do Projeto

```bash
pwd
# Copie o output e use no crontab
```

---

## Logs e Monitoramento

### Arquivos de Log

**1. execution.log**
- **Localização**: `storage/logs/cron/execution.log`
- **Conteúdo**: Todos os logs de execução de jobs
- **Formato**: `[YYYY-MM-DD HH:MM:SS] [LEVEL] [JOB NAME] Message`

**2. error.log**
- **Localização**: `storage/logs/cron/error.log`
- **Conteúdo**: Erros fatais que impedem execução
- **Formato**: Timestamp + mensagem + stack trace

### Visualizar Logs

**Últimas 50 linhas:**
```bash
tail -n 50 storage/logs/cron/execution.log
```

**Seguir logs em tempo real:**
```bash
tail -f storage/logs/cron/execution.log
```

**Buscar por erros:**
```bash
grep ERROR storage/logs/cron/execution.log
```

**Buscar por job específico:**
```bash
grep "Calculate Overdue Fees" storage/logs/cron/execution.log
```

**Logs de hoje:**
```bash
grep "$(date +%Y-%m-%d)" storage/logs/cron/execution.log
```

### Limpeza de Logs Antigos

**Manter últimos 30 dias:**
```bash
find storage/logs/cron/ -name "*.log" -mtime +30 -delete
```

**Adicionar ao crontab (executar mensalmente):**
```cron
0 2 1 * * find /path/to/project/storage/logs/cron/ -name "*.log" -mtime +30 -delete
```

---

## Troubleshooting

### Problema: CRON não executa

**1. Verificar permissões:**
```bash
chmod +x cron.php
```

**2. Verificar PHP CLI:**
```bash
php -v
# Deve mostrar versão do PHP
```

**3. Testar execução manual:**
```bash
php cron.php
# Deve executar sem erros
```

**4. Verificar crontab:**
```bash
crontab -l
# Deve mostrar a entrada do CRON
```

**5. Verificar logs do cron do sistema:**
```bash
# Linux/Ubuntu
grep CRON /var/log/syslog

# CentOS/RHEL
grep CRON /var/log/cron

# macOS
log show --predicate 'process == "cron"' --last 1h
```

### Problema: Jobs falham silenciosamente

**1. Verificar error.log:**
```bash
cat storage/logs/cron/error.log
```

**2. Executar com output no terminal:**
```bash
php cron.php
# Ver erros em tempo real
```

**3. Verificar permissões do banco:**
```bash
# Testar conexão
php -r "require 'vendor/autoload.php'; \$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); \$dotenv->load(); echo 'DB: ' . getenv('DB_DATABASE') . PHP_EOL;"
```

### Problema: Logs não são gerados

**1. Verificar permissões do diretório:**
```bash
ls -la storage/logs/
chmod -R 755 storage/logs/cron/
```

**2. Criar diretório se não existir:**
```bash
mkdir -p storage/logs/cron
```

### Problema: Jobs duplicando cálculos

**Causa**: CRON executando mais de uma vez ao mesmo tempo

**Solução**: Adicionar lock file

```php
// No início do cron.php
$lockFile = __DIR__ . '/storage/cron.lock';

if (file_exists($lockFile)) {
    echo "CRON já está em execução.\n";
    exit(0);
}

file_put_contents($lockFile, getmypid());

// No final do cron.php (ou em try/finally)
unlink($lockFile);
```

---

## Exemplos Práticos

### Exemplo 1: Job para Enviar E-mails de Aniversário

**Arquivo**: `app/Crons/Jobs/SendBirthdayEmailsJob.php`

```php
<?php

namespace App\Crons\Jobs;

use App\Models\Member;
use App\Services\MailService;

class SendBirthdayEmailsJob extends BaseJob
{
    protected string $name = 'Send Birthday Emails';
    protected string $description = 'Send birthday emails to members';

    protected function handle(): array
    {
        $this->log("Buscando aniversariantes do dia...");

        // Buscar membros que fazem aniversário hoje
        $today = date('m-d');
        $members = Member::query()
            ->where('birth_date', 'LIKE', "%-{$today}")
            ->get();

        $members = Member::hydrate($members);
        $sent = 0;

        foreach ($members as $member) {
            try {
                // Enviar e-mail
                MailService::make()
                    ->to($member->email, $member->name)
                    ->subject('Feliz Aniversário!')
                    ->body("Feliz aniversário, {$member->name}!")
                    ->send();

                $sent++;
                $this->log("E-mail enviado para {$member->name}");

            } catch (\Exception $e) {
                $this->log("Erro ao enviar para {$member->name}: " . $e->getMessage(), 'ERROR');
            }
        }

        return [
            'success' => true,
            'message' => "Enviados {$sent} e-mails de aniversário",
            'data' => ['total' => count($members), 'sent' => $sent],
        ];
    }
}
```

### Exemplo 2: Job para Limpeza de Logs Antigos

**Arquivo**: `app/Crons/Jobs/CleanupOldLogsJob.php`

```php
<?php

namespace App\Crons\Jobs;

class CleanupOldLogsJob extends BaseJob
{
    protected string $name = 'Cleanup Old Logs';
    protected string $description = 'Remove log files older than 30 days';

    protected function handle(): array
    {
        $logDir = __DIR__ . '/../../storage/logs/cron';
        $retentionDays = (int) env('CRON_LOG_RETENTION_DAYS', 30);
        $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);

        $deleted = 0;

        $files = glob($logDir . '/*.log');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deleted++;
                $this->log("Removido: " . basename($file));
            }
        }

        return [
            'success' => true,
            'message' => "Removidos {$deleted} arquivos de log antigos",
            'data' => ['deleted' => $deleted, 'retention_days' => $retentionDays],
        ];
    }
}
```

### Exemplo 3: Job para Atualizar Estatísticas

**Arquivo**: `app/Crons/Jobs/UpdateStatisticsJob.php`

```php
<?php

namespace App\Crons\Jobs;

use App\Models\Church;
use App\Models\Member;
use App\Models\Transaction;

class UpdateStatisticsJob extends BaseJob
{
    protected string $name = 'Update Statistics';
    protected string $description = 'Update cached statistics for all churches';

    protected function handle(): array
    {
        $churches = Church::all();
        $updated = 0;

        foreach ($churches as $churchData) {
            $church = Church::hydrate([$churchData])[0];

            // Calcular estatísticas
            $stats = [
                'total_members' => Member::where('church_id', '=', $church->id)->count(),
                'total_income' => Transaction::where('church_id', '=', $church->id)
                    ->where('type', '=', 'income')
                    ->where('status', '=', 'paid')
                    ->sum('amount'),
                'total_expenses' => Transaction::where('church_id', '=', $church->id)
                    ->where('type', '=', 'expense')
                    ->where('status', '=', 'paid')
                    ->sum('amount'),
            ];

            // Salvar em cache ou tabela de estatísticas
            // (implementar conforme necessidade)

            $updated++;
            $this->log("Estatísticas atualizadas para igreja #{$church->id}");
        }

        return [
            'success' => true,
            'message' => "Estatísticas atualizadas para {$updated} igrejas",
            'data' => ['churches_updated' => $updated],
        ];
    }
}
```

---

## Boas Práticas

### ✅ SEMPRE FAZER:

1. **Estender BaseJob** - Todos os jobs devem estender BaseJob
2. **Usar logging** - `$this->log()` para registrar progresso
3. **Try/catch** - Capturar exceções dentro do handle()
4. **Retornar resultado padronizado** - `['success', 'message', 'data']`
5. **Testar manualmente** - `php cron.php` antes de adicionar ao crontab
6. **Documentar jobs** - Adicionar descrição clara no `$description`
7. **Respeitar multi-tenancy** - Usar `TenantScoped` ou filtrar por `church_id`

### ❌ NUNCA FAZER:

1. **❌ Executar jobs via HTTP** - Sempre usar CLI (`php cron.php`)
2. **❌ Hardcodar valores** - Usar `.env` para configurações
3. **❌ Ignorar erros** - Sempre logar e retornar no resultado
4. **❌ Processar tudo de uma vez** - Usar batches para grandes volumes
5. **❌ Modificar BaseJob ou CronRunner** - Estender, não modificar
6. **❌ Esquecer de registrar job** - Adicionar em `cron.php`
7. **❌ Usar echo/print** - Usar `$this->log()` para output

---

## Resumo de Comandos Úteis

```bash
# Executar CRON manualmente (apenas jobs agendados para agora)
php cron.php

# Listar todos os jobs e suas frequências
php cron.php --list

# Forçar execução de todos os jobs (ignora schedule)
php cron.php --force

# Ver ajuda
php cron.php --help

# Ver últimos logs
tail -n 50 storage/logs/cron/execution.log

# Seguir logs em tempo real
tail -f storage/logs/cron/execution.log

# Buscar erros
grep ERROR storage/logs/cron/execution.log

# Ver estado de execução dos jobs
cat storage/cron/schedule-state.json

# Editar crontab
crontab -e

# Listar crontab
crontab -l

# Verificar caminho do PHP
which php

# Limpar logs antigos (30 dias)
find storage/logs/cron/ -name "*.log" -mtime +30 -delete

# Rodar cron localmente via terminal
while true; do echo "$(date): Executando cron..."; php cron.php; echo "Próxima execução em 15 minutos"; sleep 900; done
```

---

## Checklist para Adicionar Novo Job

- [ ] Criar classe em `app/Crons/Jobs/NomeDoJob.php`
- [ ] Estender `BaseJob`
- [ ] Definir `$name` e `$description`
- [ ] Implementar método `handle()`
- [ ] Usar `$this->log()` para logging
- [ ] Retornar array padronizado
- [ ] Registrar job em `cron.php` com frequência adequada
- [ ] Testar listagem: `php cron.php --list`
- [ ] Testar execução forçada: `php cron.php --force`
- [ ] Verificar logs: `tail storage/logs/cron/execution.log`
- [ ] Atualizar esta documentação se necessário

---

**Última atualização**: 2025-12-18
**Responsável**: Documentação do Sistema
**Status**: ✅ Sistema Oficial de CRON com Scheduler
