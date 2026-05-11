<?php

namespace App\Models;

/**
 * Model LocacaoDocumento
 *
 * Documentos enviados pelo cliente no passo 4 do site publico (CNH, CPF,
 * RG/Passaporte, Comprovante). Cada linha aponta para um arquivo salvo em
 * storage/uploads/{chave}/ via ImageHelper/FileHelper.
 *
 * Acesso publico ao arquivo: FileHelper::url($arquivo, $chave) gera URL com
 * token HMAC assinado. A tabela possui UK (id_locacao, tipo), entao uma nova
 * linha do mesmo tipo substitui a anterior por ON DUPLICATE KEY UPDATE.
 */
class LocacaoDocumento extends Model
{
    public const TIPOS = ['cnh', 'cpf', 'rg', 'comprovante'];

    public function listarPorLocacao(int $idLocacao): array
    {
        return $this->qb
            ->table('locacoes_documentos')
            ->where('id_locacao', '=', $idLocacao)
            ->orderBy('tipo', 'ASC')
            ->get();
    }

    public function buscarPorLocacaoTipo(int $idLocacao, string $tipo): ?array
    {
        return $this->qb
            ->table('locacoes_documentos')
            ->where('id_locacao', '=', $idLocacao)
            ->where('tipo', '=', $tipo)
            ->first();
    }

    /**
     * Cria/substitui o documento de um tipo para a locacao. Retorna o id da linha.
     */
    public function upsert(int $idLocacao, string $tipo, string $arquivo): int
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException("Tipo de documento invalido: {$tipo}");
        }

        $chave = $_SESSION['chave'] ?? '';
        $existente = $this->buscarPorLocacaoTipo($idLocacao, $tipo);

        if ($existente) {
            $this->qb
                ->table('locacoes_documentos')
                ->where('id', '=', (int) $existente['id'])
                ->update(['arquivo' => $arquivo]);
            return (int) $existente['id'];
        }

        return $this->qb
            ->table('locacoes_documentos')
            ->insert([
                'chave'      => $chave,
                'id_locacao' => $idLocacao,
                'tipo'       => $tipo,
                'arquivo'    => $arquivo,
            ]);
    }

    public function excluir(int $id): int
    {
        return $this->qb
            ->table('locacoes_documentos')
            ->where('id', '=', $id)
            ->delete();
    }
}
