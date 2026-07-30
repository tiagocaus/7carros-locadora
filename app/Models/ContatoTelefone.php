<?php

namespace App\Models;

/**
 * Model ContatoTelefone
 *
 * Gerencia múltiplos telefones para matrizes_filiais e clientes.
 * Usa relacionamento polimórfico através de entidade_tipo + entidade_id.
 * Suporta flags para WhatsApp, Telegram e SMS.
 */
class ContatoTelefone extends Model
{
    /**
     * Lista telefones de uma entidade
     *
     * @param string $tipo Tipo da entidade ('matriz_filial' ou 'cliente')
     * @param int $id ID da entidade
     * @return array Lista de telefones
     */
    public function listarPorEntidade(string $tipo, int $id): array
    {
        return $this->qb
            ->table('contatos_telefones')
            ->select(['id', 'telefone', 'descricao', 'whatsapp', 'telegram', 'sms', 'principal'])
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $id)
            ->orderByDesc('principal')
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * Retorna o telefone principal de uma entidade
     *
     * @param string $tipo Tipo da entidade
     * @param int $id ID da entidade
     * @return array|null Dados do telefone principal ou null
     */
    public function getPrincipal(string $tipo, int $id): ?array
    {
        return $this->qb
            ->table('contatos_telefones')
            ->select(['id', 'telefone', 'descricao', 'whatsapp', 'telegram', 'sms'])
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $id)
            ->where('principal', '=', 'S')
            ->first();
    }

    /**
     * Retorna telefones com WhatsApp de uma entidade
     *
     * @param string $tipo Tipo da entidade
     * @param int $id ID da entidade
     * @return array Lista de telefones com WhatsApp
     */
    public function getWhatsApp(string $tipo, int $id): array
    {
        return $this->qb
            ->table('contatos_telefones')
            ->select(['id', 'telefone', 'descricao'])
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $id)
            ->where('whatsapp', '=', 'S')
            ->get();
    }

    /**
     * Retorna telefones com Telegram de uma entidade
     *
     * @param string $tipo Tipo da entidade
     * @param int $id ID da entidade
     * @return array Lista de telefones com Telegram
     */
    public function getTelegram(string $tipo, int $id): array
    {
        return $this->qb
            ->table('contatos_telefones')
            ->select(['id', 'telefone', 'descricao'])
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $id)
            ->where('telegram', '=', 'S')
            ->get();
    }

    /**
     * Retorna telefones com SMS de uma entidade
     *
     * @param string $tipo Tipo da entidade
     * @param int $id ID da entidade
     * @return array Lista de telefones com SMS
     */
    public function getSMS(string $tipo, int $id): array
    {
        return $this->qb
            ->table('contatos_telefones')
            ->select(['id', 'telefone', 'descricao'])
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $id)
            ->where('sms', '=', 'S')
            ->get();
    }

    /**
     * Lista todos os telefones autorizados para um canal.
     *
     * @return array<int, array{id:int,telefone:string,descricao:?string,principal:string}>
     */
    public function listarParaEnvio(string $tipo, int $id, string $canal, ?string $chave = null): array
    {
        if (!in_array($canal, ['whatsapp', 'sms'], true)) {
            throw new \InvalidArgumentException('Canal de telefone invalido');
        }

        $query = $this->qb->table('contatos_telefones');
        if ($chave !== null && $chave !== '') {
            $query->withChave($chave);
        }

        return $query
            ->select(['id', 'telefone', 'descricao', 'principal'])
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $id)
            ->where($canal, '=', 'S')
            ->orderByDesc('principal')
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * Revalida um telefone autorizado imediatamente antes do envio.
     */
    public function podeEnviarPara(
        string $tipo,
        int $id,
        string $telefone,
        string $canal,
        ?string $chave = null
    ): bool {
        $procurado = preg_replace('/\D/', '', $telefone);
        if ($procurado === '') {
            return false;
        }

        foreach ($this->listarParaEnvio($tipo, $id, $canal, $chave) as $contato) {
            if (preg_replace('/\D/', '', (string) $contato['telefone']) === $procurado) {
                return true;
            }
        }

        return false;
    }

    /**
     * Salva telefones de uma entidade (substitui todos existentes)
     *
     * @param string $tipo Tipo da entidade
     * @param int $id ID da entidade
     * @param array $telefones Array de telefones no formato:
     *   [
     *     ['telefone' => '+55 11 99999-9999', 'descricao' => 'Celular', 'whatsapp' => 'S', 'telegram' => 'N', 'sms' => 'S', 'principal' => 'S'],
     *   ]
     * @param bool $gerenciarTransacao False quando participa de uma transacao externa
     * @return bool Sucesso
     */
    public function salvar(
        string $tipo,
        int $id,
        array $telefones,
        bool $gerenciarTransacao = true
    ): bool
    {
        if ($gerenciarTransacao) {
            $this->qb->beginTransaction();
        }

        try {
            // Remover telefones existentes
            $this->qb
                ->table('contatos_telefones')
                    ->where('entidade_tipo', '=', $tipo)
                ->where('entidade_id', '=', $id)
                ->delete();

            // Garantir que apenas um seja principal
            $temPrincipal = false;
            foreach ($telefones as $tel) {
                if (($tel['principal'] ?? 'N') === 'S') {
                    $temPrincipal = true;
                    break;
                }
            }

            // Inserir novos telefones
            $primeiro = true;
            foreach ($telefones as $tel) {
                if (empty($tel['telefone'])) {
                    continue;
                }

                // Se nenhum foi marcado como principal, o primeiro será
                $isPrincipal = ($tel['principal'] ?? 'N') === 'S';
                if (!$temPrincipal && $primeiro) {
                    $isPrincipal = true;
                }
                $primeiro = false;

                $this->qb
                    ->table('contatos_telefones')
                    ->insert([
                        'entidade_tipo' => $tipo,
                        'entidade_id' => $id,
                        'telefone' => trim($tel['telefone']),
                        'descricao' => trim($tel['descricao'] ?? '') ?: null,
                        'whatsapp' => ($tel['whatsapp'] ?? 'N') === 'S' ? 'S' : 'N',
                        'telegram' => ($tel['telegram'] ?? 'N') === 'S' ? 'S' : 'N',
                        'sms' => ($tel['sms'] ?? 'N') === 'S' ? 'S' : 'N',
                        'principal' => $isPrincipal ? 'S' : 'N',
                    ]);
            }

            if ($gerenciarTransacao) {
                $this->qb->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($gerenciarTransacao) {
                $this->qb->rollback();
            }
            throw $e;
        }
    }

    /**
     * Adiciona um telefone a uma entidade
     *
     * @param string $tipo Tipo da entidade
     * @param int $entidadeId ID da entidade
     * @param string $telefone Telefone
     * @param string|null $descricao Descrição
     * @param bool $whatsapp Se tem WhatsApp
     * @param bool $telegram Se tem Telegram
     * @param bool $sms Se aceita SMS
     * @param bool $principal Se é o telefone principal
     * @return int ID do registro criado
     */
    public function adicionar(
        string $tipo,
        int $entidadeId,
        string $telefone,
        ?string $descricao = null,
        bool $whatsapp = false,
        bool $telegram = false,
        bool $sms = false,
        bool $principal = false
    ): int {
        $this->qb->beginTransaction();

        try {
            // Se for principal, desmarcar outros
            if ($principal) {
                $this->qb
                    ->table('contatos_telefones')
                            ->where('entidade_tipo', '=', $tipo)
                    ->where('entidade_id', '=', $entidadeId)
                    ->update(['principal' => 'N']);
            }

            $id = $this->qb
                ->table('contatos_telefones')
                ->insert([
                    'entidade_tipo' => $tipo,
                    'entidade_id' => $entidadeId,
                    'telefone' => trim($telefone),
                    'descricao' => $descricao ? trim($descricao) : null,
                    'whatsapp' => $whatsapp ? 'S' : 'N',
                    'telegram' => $telegram ? 'S' : 'N',
                    'sms' => $sms ? 'S' : 'N',
                    'principal' => $principal ? 'S' : 'N',
                ]);

            $this->qb->commit();
            return $id;
        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Define qual telefone é o principal (desmarca outros)
     *
     * @param string $tipo Tipo da entidade
     * @param int $entidadeId ID da entidade
     * @param int $telefoneId ID do telefone a marcar como principal
     * @return bool Sucesso
     */
    public function definirPrincipal(string $tipo, int $entidadeId, int $telefoneId): bool
    {
        $this->qb->beginTransaction();

        try {
            // Desmarcar todos
            $this->qb
                ->table('contatos_telefones')
                    ->where('entidade_tipo', '=', $tipo)
                ->where('entidade_id', '=', $entidadeId)
                ->update(['principal' => 'N']);

            // Marcar o selecionado
            $this->qb
                ->table('contatos_telefones')
                    ->where('id', '=', $telefoneId)
                ->update(['principal' => 'S']);

            $this->qb->commit();
            return true;
        } catch (\Exception $e) {
            $this->qb->rollback();
            throw $e;
        }
    }

    /**
     * Remove um telefone específico
     *
     * @param int $id ID do telefone
     * @return int Linhas afetadas
     */
    public function excluir(int $id): int
    {
        return $this->qb
            ->table('contatos_telefones')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Remove todos os telefones de uma entidade
     *
     * @param string $tipo Tipo da entidade
     * @param int $entidadeId ID da entidade
     * @return int Linhas afetadas
     */
    public function excluirPorEntidade(string $tipo, int $entidadeId): int
    {
        return $this->qb
            ->table('contatos_telefones')
            ->where('entidade_tipo', '=', $tipo)
            ->where('entidade_id', '=', $entidadeId)
            ->delete();
    }

    /**
     * Busca entidades por telefone
     *
     * @param string $telefone Telefone a buscar
     * @param string|null $tipo Tipo da entidade (opcional, filtra por tipo)
     * @return array Lista de entidades que possuem o telefone
     */
    public function buscarPorTelefone(string $telefone, ?string $tipo = null): array
    {
        $query = $this->qb
            ->table('contatos_telefones')
            ->select(['id', 'entidade_tipo', 'entidade_id', 'telefone', 'descricao', 'whatsapp', 'telegram', 'sms', 'principal'])
            ->where('telefone', '=', $telefone);

        if ($tipo) {
            $query->where('entidade_tipo', '=', $tipo);
        }

        return $query->get();
    }
}
