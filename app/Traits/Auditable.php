<?php

namespace App\Traits;

use App\Services\AuditLogService;

/**
 * Trait para adicionar auditoria automática em Models
 *
 * Requer que a classe que usa este trait tenha:
 * - Método buscarPorId(int $id): ?array
 * - Método criar(array $dados): int
 * - Método atualizar(int $id, array $dados): int
 * - Método deletar(int $id): int
 *
 * Opcionalmente pode sobrescrever:
 * - getEntidadeAuditoria(): string
 * - getCampoIdentificador(): string
 *
 * Os dados de auditoria são capturados pelo JavaScript (form-audit.js) e
 * enviados automaticamente nos campos _audit_data (cadastro) ou _audit_changes (edição).
 */
trait Auditable
{
    /**
     * Retorna o nome da entidade para mensagens de log
     * Sobrescreva este método na classe para personalizar
     *
     * @return string
     */
    protected function getEntidadeAuditoria(): string
    {
        return 'o registro';
    }

    /**
     * Retorna o campo usado como identificador nas mensagens de log
     * Sobrescreva este método na classe para personalizar
     *
     * @return string
     */
    protected function getCampoIdentificador(): string
    {
        return 'id';
    }

    /**
     * Cria um registro com auditoria automática
     *
     * @param array $dados Dados do novo registro
     * @return int ID do registro criado
     */
    public function criarComAuditoria(array $dados): int
    {
        // Extrair dados de auditoria antes de criar
        $auditData = $dados['_audit_data'] ?? null;
        unset($dados['_audit_data'], $dados['_audit_changes'], $dados['_audit_initial']);

        // Criar o registro
        $id = $this->criar($dados);

        // Buscar registro completo para log
        $registroCriado = $this->buscarPorId($id);

        // Identificador para mensagem
        $identificador = $this->getIdentificadorAuditoria($registroCriado ?? $dados);

        // Montar mensagem
        $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
        $mensagem = "{$nomeUsuario}, adicionou {$this->getEntidadeAuditoria()} [{$identificador}]";

        // Registrar log com dados do frontend
        AuditLogService::registrarComAuditFrontend($mensagem, $auditData, null);

        return $id;
    }

    /**
     * Atualiza um registro com auditoria automática
     *
     * @param int $id ID do registro
     * @param array $dadosNovos Dados atualizados
     * @return int Número de linhas afetadas
     */
    public function atualizarComAuditoria(int $id, array $dadosNovos): int
    {
        // Extrair dados de auditoria antes de atualizar
        $auditChanges = $dadosNovos['_audit_changes'] ?? null;
        unset($dadosNovos['_audit_data'], $dadosNovos['_audit_changes'], $dadosNovos['_audit_initial']);

        // Buscar dados ANTES da alteração
        $dadosAntigos = $this->buscarPorId($id);

        if (!$dadosAntigos) {
            return 0;
        }

        // Identificador para mensagem (antes da atualização)
        $identificador = $this->getIdentificadorAuditoria($dadosAntigos);

        // Atualizar o registro
        $resultado = $this->atualizar($id, $dadosNovos);

        // Montar mensagem
        $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
        $mensagem = "{$nomeUsuario}, atualizou {$this->getEntidadeAuditoria()} [{$identificador}]";

        // Registrar log com dados do frontend
        AuditLogService::registrarComAuditFrontend($mensagem, null, $auditChanges);

        return $resultado;
    }

    /**
     * Deleta um registro com auditoria automática
     *
     * @param int $id ID do registro
     * @return int Número de linhas afetadas
     */
    public function deletarComAuditoria(int $id): int
    {
        // Buscar dados ANTES da exclusão
        $dadosAntigos = $this->buscarPorId($id);

        if (!$dadosAntigos) {
            return 0;
        }

        // Identificador para mensagem
        $identificador = $this->getIdentificadorAuditoria($dadosAntigos);

        // Deletar o registro
        $resultado = $this->deletar($id);

        // Montar mensagem
        $nomeUsuario = $_SESSION['user_name'] ?? 'Sistema';
        $mensagem = "{$nomeUsuario}, excluiu {$this->getEntidadeAuditoria()} [{$identificador}]";

        // Registrar log simples (exclusão não tem campos do frontend)
        AuditLogService::registrar($mensagem);

        return $resultado;
    }

    /**
     * Obtém o identificador do registro para mensagens de log
     *
     * @param array $dados Dados do registro
     * @return string Identificador
     */
    protected function getIdentificadorAuditoria(array $dados): string
    {
        $campoPreferido = $this->getCampoIdentificador();

        // Tentar campo preferido primeiro
        if (!empty($dados[$campoPreferido])) {
            return (string) $dados[$campoPreferido];
        }

        // Tentar campos comuns de identificação
        $camposPossiveis = [
            'nome_rsocial',
            'nome',
            'razao_social',
            'titulo',
            'descricao',
            'placa',
            'id',
        ];

        foreach ($camposPossiveis as $campo) {
            if (!empty($dados[$campo])) {
                return (string) $dados[$campo];
            }
        }

        return (string) ($dados['id'] ?? 'N/A');
    }
}
