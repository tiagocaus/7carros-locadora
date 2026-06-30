<?php

namespace App\Crons\Jobs;

/**
 * Classe base abstrata para todos os Jobs CRON
 *
 * Fornece estrutura comum e métodos de logging para todos os jobs
 */
abstract class BaseJob
{
    protected string $name = 'Unnamed Job';
    protected string $description = '';
    protected float $startTime = 0;
    protected array $logs = [];

    /**
     * Executa o job
     *
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function run(): array
    {
        $this->startTime = microtime(true);
        $this->logs = [];

        try {
            $result = $this->handle();
            
            // Garante que o resultado tem o formato esperado
            if (!isset($result['success'])) {
                $result['success'] = true;
            }
            if (!isset($result['message'])) {
                $result['message'] = 'Job executado';
            }
            if (!isset($result['data'])) {
                $result['data'] = [];
            }

            $result['duration'] = $this->getDuration();
            $result['logs'] = $this->logs;

            return $result;

        } catch (\Exception $e) {
            $this->log("Erro fatal: " . $e->getMessage(), 'ERROR');
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()],
                'duration' => $this->getDuration(),
                'logs' => $this->logs,
            ];
        }
    }

    /**
     * Implementa a lógica do job (deve ser implementado pelas classes filhas)
     *
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    abstract protected function handle(): array;

    /**
     * Registra uma mensagem de log
     *
     * @param string $message Mensagem
     * @param string $level Nível (INFO, WARNING, ERROR)
     */
    protected function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = now();
        $logEntry = "[{$timestamp}] [{$level}] [{$this->name}] {$message}";
        
        $this->logs[] = $logEntry;
        echo $logEntry . "\n";
    }

    /**
     * Obtém o nome do job
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Obtém a descrição do job
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Calcula a duração da execução em segundos
     */
    protected function getDuration(): float
    {
        if ($this->startTime === 0) {
            return 0;
        }

        return round(microtime(true) - $this->startTime, 2);
    }

    /**
     * Retorna identificador único do job para controle de execução
     *
     * @return string Nome completo da classe (namespace + classe)
     */
    public function getJobId(): string
    {
        return static::class;
    }
}
