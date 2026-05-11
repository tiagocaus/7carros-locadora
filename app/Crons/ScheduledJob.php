<?php

namespace App\Crons;

use App\Crons\Jobs\BaseJob;
use Cron\CronExpression;

/**
 * Wrapper para jobs com configuração de agendamento
 *
 * Permite definir frequências de execução usando métodos fluentes
 */
class ScheduledJob
{
    private BaseJob $job;
    private string $expression = '* * * * *';
    private ?string $timezone = null;
    private Scheduler $scheduler;

    public function __construct(BaseJob $job, Scheduler $scheduler)
    {
        $this->job = $job;
        $this->scheduler = $scheduler;
    }

    /**
     * Obtém o job encapsulado
     */
    public function getJob(): BaseJob
    {
        return $this->job;
    }

    /**
     * Obtém o ID único do job
     */
    public function getJobId(): string
    {
        return get_class($this->job);
    }

    /**
     * Obtém a expressão cron configurada
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    // ========================================
    // Métodos de Frequência (Fluent Interface)
    // ========================================

    /**
     * Executa a cada minuto
     */
    public function everyMinute(): self
    {
        $this->expression = '* * * * *';
        return $this;
    }

    /**
     * Executa a cada 2 minutos
     */
    public function everyTwoMinutes(): self
    {
        $this->expression = '*/2 * * * *';
        return $this;
    }

    /**
     * Executa a cada 5 minutos
     */
    public function everyFiveMinutes(): self
    {
        $this->expression = '*/5 * * * *';
        return $this;
    }

    /**
     * Executa a cada 10 minutos
     */
    public function everyTenMinutes(): self
    {
        $this->expression = '*/10 * * * *';
        return $this;
    }

    /**
     * Executa a cada 15 minutos
     */
    public function everyFifteenMinutes(): self
    {
        $this->expression = '*/15 * * * *';
        return $this;
    }

    /**
     * Executa a cada 30 minutos
     */
    public function everyThirtyMinutes(): self
    {
        $this->expression = '*/30 * * * *';
        return $this;
    }

    /**
     * Executa a cada hora (minuto 0)
     */
    public function hourly(): self
    {
        $this->expression = '0 * * * *';
        return $this;
    }

    /**
     * Executa a cada hora em um minuto específico
     *
     * @param int $minute Minuto (0-59)
     */
    public function hourlyAt(int $minute): self
    {
        $minute = max(0, min(59, $minute));
        $this->expression = "{$minute} * * * *";
        return $this;
    }

    /**
     * Executa a cada 2 horas
     */
    public function everyTwoHours(): self
    {
        $this->expression = '0 */2 * * *';
        return $this;
    }

    /**
     * Executa a cada 3 horas
     */
    public function everyThreeHours(): self
    {
        $this->expression = '0 */3 * * *';
        return $this;
    }

    /**
     * Executa a cada 4 horas
     */
    public function everyFourHours(): self
    {
        $this->expression = '0 */4 * * *';
        return $this;
    }

    /**
     * Executa a cada 6 horas
     */
    public function everySixHours(): self
    {
        $this->expression = '0 */6 * * *';
        return $this;
    }

    /**
     * Executa diariamente à meia-noite
     */
    public function daily(): self
    {
        $this->expression = '0 0 * * *';
        return $this;
    }

    /**
     * Executa diariamente em um horário específico
     *
     * @param string $time Horário no formato "HH:MM" (ex: "08:00", "00:01")
     */
    public function dailyAt(string $time): self
    {
        $parts = explode(':', $time);
        $hour = isset($parts[0]) ? (int) $parts[0] : 0;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;

        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        $this->expression = "{$minute} {$hour} * * *";
        return $this;
    }

    /**
     * Executa duas vezes por dia
     *
     * @param int $firstHour Primeira hora (default: 1)
     * @param int $secondHour Segunda hora (default: 13)
     */
    public function twiceDaily(int $firstHour = 1, int $secondHour = 13): self
    {
        $firstHour = max(0, min(23, $firstHour));
        $secondHour = max(0, min(23, $secondHour));
        $this->expression = "0 {$firstHour},{$secondHour} * * *";
        return $this;
    }

    /**
     * Executa semanalmente (domingo à meia-noite)
     */
    public function weekly(): self
    {
        $this->expression = '0 0 * * 0';
        return $this;
    }

    /**
     * Executa semanalmente em um dia e horário específicos
     *
     * @param int $dayOfWeek Dia da semana (0=domingo, 1=segunda, ..., 6=sábado)
     * @param string $time Horário no formato "HH:MM"
     */
    public function weeklyOn(int $dayOfWeek, string $time = '00:00'): self
    {
        $parts = explode(':', $time);
        $hour = isset($parts[0]) ? (int) $parts[0] : 0;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;

        $dayOfWeek = max(0, min(6, $dayOfWeek));
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        $this->expression = "{$minute} {$hour} * * {$dayOfWeek}";
        return $this;
    }

    /**
     * Executa mensalmente (dia 1 à meia-noite)
     */
    public function monthly(): self
    {
        $this->expression = '0 0 1 * *';
        return $this;
    }

    /**
     * Executa mensalmente em um dia e horário específicos
     *
     * @param int $dayOfMonth Dia do mês (1-31)
     * @param string $time Horário no formato "HH:MM"
     */
    public function monthlyOn(int $dayOfMonth, string $time = '00:00'): self
    {
        $parts = explode(':', $time);
        $hour = isset($parts[0]) ? (int) $parts[0] : 0;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;

        $dayOfMonth = max(1, min(31, $dayOfMonth));
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        $this->expression = "{$minute} {$hour} {$dayOfMonth} * *";
        return $this;
    }

    /**
     * Executa trimestralmente (primeiro dia de cada trimestre)
     */
    public function quarterly(): self
    {
        $this->expression = '0 0 1 1,4,7,10 *';
        return $this;
    }

    /**
     * Executa anualmente (1 de janeiro à meia-noite)
     */
    public function yearly(): self
    {
        $this->expression = '0 0 1 1 *';
        return $this;
    }

    // ========================================
    // Modificadores de Dia da Semana
    // ========================================

    /**
     * Restringe execução apenas em dias de semana (segunda a sexta)
     */
    public function weekdays(): self
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '1-5';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Restringe execução apenas em finais de semana
     */
    public function weekends(): self
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '0,6';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Restringe execução para domingos
     */
    public function sundays(): self
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '0';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Restringe execução para segundas
     */
    public function mondays(): self
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '1';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Restringe execução para terças
     */
    public function tuesdays(): self
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '2';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Restringe execução para quartas
     */
    public function wednesdays(): self
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '3';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Restringe execução para quintas
     */
    public function thursdays(): self
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '4';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Restringe execução para sextas
     */
    public function fridays(): self
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '5';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Restringe execução para sábados
     */
    public function saturdays(): self
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '6';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    // ========================================
    // Métodos Auxiliares
    // ========================================

    /**
     * Define uma expressão cron customizada
     *
     * @param string $expression Expressão cron (ex: "0 *\/2 * * *")
     */
    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     * Define o timezone para o job
     *
     * @param string $timezone Timezone (ex: "America/Sao_Paulo")
     */
    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * Define o horário de execução (modifica apenas hora e minuto)
     *
     * @param string $time Horário no formato "HH:MM"
     */
    public function at(string $time): self
    {
        $parts = explode(':', $time);
        $hour = isset($parts[0]) ? (int) $parts[0] : 0;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;

        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        $cronParts = explode(' ', $this->expression);
        $cronParts[0] = (string) $minute;
        $cronParts[1] = (string) $hour;
        $this->expression = implode(' ', $cronParts);

        return $this;
    }

    // ========================================
    // Verificação de Execução
    // ========================================

    /**
     * Verifica se o job deve ser executado agora
     *
     * @param \DateTimeInterface|null $currentTime Horário para verificação (default: agora)
     */
    public function isDue(?\DateTimeInterface $currentTime = null): bool
    {
        if ($currentTime === null) {
            $timezone = $this->timezone ?? date_default_timezone_get();
            $currentTime = new \DateTimeImmutable('now', new \DateTimeZone($timezone));
        }

        $cron = new CronExpression($this->expression);
        return $cron->isDue($currentTime);
    }

    /**
     * Obtém a próxima data de execução
     *
     * @param \DateTimeInterface|null $currentTime Horário base (default: agora)
     */
    public function getNextRunDate(?\DateTimeInterface $currentTime = null): \DateTimeInterface
    {
        if ($currentTime === null) {
            $timezone = $this->timezone ?? date_default_timezone_get();
            $currentTime = new \DateTimeImmutable('now', new \DateTimeZone($timezone));
        }

        $cron = new CronExpression($this->expression);
        return $cron->getNextRunDate($currentTime);
    }

    /**
     * Obtém a data da última execução programada
     *
     * @param \DateTimeInterface|null $currentTime Horário base (default: agora)
     */
    public function getPreviousRunDate(?\DateTimeInterface $currentTime = null): \DateTimeInterface
    {
        if ($currentTime === null) {
            $timezone = $this->timezone ?? date_default_timezone_get();
            $currentTime = new \DateTimeImmutable('now', new \DateTimeZone($timezone));
        }

        $cron = new CronExpression($this->expression);
        return $cron->getPreviousRunDate($currentTime);
    }

    /**
     * Retorna descrição legível da frequência
     */
    public function getFrequencyDescription(): string
    {
        $descriptions = [
            '* * * * *' => 'A cada minuto',
            '*/2 * * * *' => 'A cada 2 minutos',
            '*/5 * * * *' => 'A cada 5 minutos',
            '*/10 * * * *' => 'A cada 10 minutos',
            '*/15 * * * *' => 'A cada 15 minutos',
            '*/30 * * * *' => 'A cada 30 minutos',
            '0 * * * *' => 'A cada hora',
            '0 */2 * * *' => 'A cada 2 horas',
            '0 */3 * * *' => 'A cada 3 horas',
            '0 */4 * * *' => 'A cada 4 horas',
            '0 */6 * * *' => 'A cada 6 horas',
            '0 0 * * *' => 'Diariamente à meia-noite',
            '0 0 * * 0' => 'Semanalmente aos domingos',
            '0 0 1 * *' => 'Mensalmente no dia 1',
            '0 0 1 1 *' => 'Anualmente em 1 de janeiro',
        ];

        return $descriptions[$this->expression] ?? "Cron: {$this->expression}";
    }
}
