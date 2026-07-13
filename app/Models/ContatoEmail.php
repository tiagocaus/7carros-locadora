<?php

namespace App\Models;

/**
 * Model ContatoEmail
 *
 * Gerencia múltiplos emails para matrizes_filiais e clientes.
 * Usa relacionamento polimórfico através de entidade_tipo + entidade_id.
 */
class ContatoEmail extends Model
{
    /**
     * Lista emails de uma entidade
     *
     * @param string $tipo Tipo da entidade ('matriz_filial' ou 'cliente')
     * @param int $id ID da entidade
     * @return array Lista de emails
     */
    public function listarPorEntidade(string $tipo, int $id): array
    {
        return $this->qb
            ->table('contatos_emails')
            ->select(['id', 'email', 'descricao', 'principal', 'recebe_email'])
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $id)
            ->orderByDesc('principal')
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * Retorna o email principal de uma entidade
     *
     * @param string $tipo Tipo da entidade
     * @param int $id ID da entidade
     * @return array|null Dados do email principal ou null
     */
    public function getPrincipal(string $tipo, int $id): ?array
    {
        return $this->qb
            ->table('contatos_emails')
            ->select(['id', 'email', 'descricao', 'recebe_email'])
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $id)
            ->where('principal', '=', 'S')
            ->first();
    }

    /**
     * Salva emails de uma entidade (substitui todos existentes)
     *
     * @param string $tipo Tipo da entidade
     * @param int $id ID da entidade
     * @param array $emails Array de emails no formato:
     *   [
     *     ['email' => 'email@example.com', 'descricao' => 'Comercial', 'principal' => 'S'],
     *   ]
     * @return bool Sucesso
     */
    public function salvar(string $tipo, int $id, array $emails): bool
    {
        $this->qb->beginTransaction();

        try {
            // Remover emails existentes
            $this->qb
                ->table('contatos_emails')
                    ->where('entidade_tipo', '=', $tipo)
                ->where('entidade_id', '=', $id)
                ->delete();

            // Garantir que apenas um seja principal
            $temPrincipal = false;
            foreach ($emails as $email) {
                if (($email['principal'] ?? 'N') === 'S') {
                    $temPrincipal = true;
                    break;
                }
            }

            // Inserir novos emails
            $primeiro = true;
            foreach ($emails as $email) {
                if (empty($email['email'])) {
                    continue;
                }

                // Se nenhum foi marcado como principal, o primeiro será
                $isPrincipal = ($email['principal'] ?? 'N') === 'S';
                if (!$temPrincipal && $primeiro) {
                    $isPrincipal = true;
                }
                $primeiro = false;

                $this->qb
                    ->table('contatos_emails')
                    ->insert([
                        'entidade_tipo' => $tipo,
                        'entidade_id' => $id,
                        'email' => trim($email['email']),
                        'descricao' => trim($email['descricao'] ?? '') ?: null,
                        'principal' => $isPrincipal ? 'S' : 'N',
                        'recebe_email' => ($email['recebe_email'] ?? 'S') === 'N' ? 'N' : 'S',
                    ]);
            }

            $this->qb->commit();
            return true;
        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Adiciona um email a uma entidade
     *
     * @param string $tipo Tipo da entidade
     * @param int $entidadeId ID da entidade
     * @param string $email Email
     * @param string|null $descricao Descrição
     * @param bool $principal Se é o email principal
     * @return int ID do registro criado
     */
    public function adicionar(
        string $tipo,
        int $entidadeId,
        string $email,
        ?string $descricao = null,
        bool $principal = false,
        bool $recebeEmail = true
    ): int
    {
        $this->qb->beginTransaction();

        try {
            // Se for principal, desmarcar outros
            if ($principal) {
                $this->qb
                    ->table('contatos_emails')
                            ->where('entidade_tipo', '=', $tipo)
                    ->where('entidade_id', '=', $entidadeId)
                    ->update(['principal' => 'N']);
            }

            $id = $this->qb
                ->table('contatos_emails')
                ->insert([
                    'entidade_tipo' => $tipo,
                    'entidade_id' => $entidadeId,
                    'email' => trim($email),
                    'descricao' => $descricao ? trim($descricao) : null,
                    'principal' => $principal ? 'S' : 'N',
                    'recebe_email' => $recebeEmail ? 'S' : 'N',
                ]);

            $this->qb->commit();
            return $id;
        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Define qual email é o principal (desmarca outros)
     *
     * @param string $tipo Tipo da entidade
     * @param int $entidadeId ID da entidade
     * @param int $emailId ID do email a marcar como principal
     * @return bool Sucesso
     */
    public function definirPrincipal(string $tipo, int $entidadeId, int $emailId): bool
    {
        $this->qb->beginTransaction();

        try {
            // Desmarcar todos
            $this->qb
                ->table('contatos_emails')
                    ->where('entidade_tipo', '=', $tipo)
                ->where('entidade_id', '=', $entidadeId)
                ->update(['principal' => 'N']);

            // Marcar o selecionado
            $this->qb
                ->table('contatos_emails')
                    ->where('id', '=', $emailId)
                ->update(['principal' => 'S']);

            $this->qb->commit();
            return true;
        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Remove um email específico
     *
     * @param int $id ID do email
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('contatos_emails')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Remove todos os emails de uma entidade
     *
     * @param string $tipo Tipo da entidade
     * @param int $entidadeId ID da entidade
     * @return int Linhas afetadas
     */
    public function excluirPorEntidade(string $tipo, int $entidadeId): int
    {
        return $this->qb
            ->table('contatos_emails')
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $entidadeId)
            ->delete();
    }

    /**
     * Busca entidades por email
     *
     * @param string $email Email a buscar
     * @param string|null $tipo Tipo da entidade (opcional, filtra por tipo)
     * @return array Lista de entidades que possuem o email
     */
    public function buscarPorEmail(string $email, ?string $tipo = null): array
    {
        $query = $this->qb
            ->table('contatos_emails')
            ->select(['id', 'entidade_tipo', 'entidade_id', 'email', 'descricao', 'principal', 'recebe_email'])
            ->where('email', '=', $email);

        if ($tipo) {
            $query->where('entidade_tipo', '=', $tipo);
        }

        return $query->get();
    }

    /**
     * Lista os enderecos autorizados para receber emails de uma entidade.
     *
     * @return array<int, array{id:int,email:string,descricao:?string,principal:string}>
     */
    public function listarParaEnvio(string $tipo, int $id, ?string $chave = null): array
    {
        $query = $this->qb
            ->table('contatos_emails');

        if ($chave !== null && $chave !== '') {
            $query->withChave($chave);
        }

        return $query
            ->select(['id', 'email', 'descricao', 'principal'])
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $id)
            ->where('recebe_email', '=', 'S')
            ->orderByDesc('principal')
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * Confirma se um endereco continua autorizado no momento do envio.
     */
    public function podeEnviarPara(string $tipo, int $id, string $email, ?string $chave = null): bool
    {
        $query = $this->qb
            ->table('contatos_emails');

        if ($chave !== null && $chave !== '') {
            $query->withChave($chave);
        }

        return $query
            ->select(['id'])
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $id)
            ->where('email', '=', trim($email))
            ->where('recebe_email', '=', 'S')
            ->first() !== null;
    }
}
