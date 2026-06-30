<?php

namespace App\Models;

class SiteCredencial extends Model
{
    /**
     * Busca credenciais do tenant (dados criptografados)
     */
    public function buscarPorChave(): ?array
    {
        return $this->qb
            ->table('site_credenciais')
            ->select(['*'])
            ->first();
    }

    /**
     * Retorna credenciais com a senha descriptografada
     */
    public function getDecrypted(): ?array
    {
        $cred = $this->buscarPorChave();
        if (!$cred) {
            return null;
        }

        return [
            'tipo'      => $cred['tipo'],
            'host'      => $cred['host'],
            'porta'     => (int) $cred['porta'],
            'usuario'   => $cred['usuario'],
            'senha'     => decrypt($cred['senha']),
            'diretorio' => $cred['diretorio'] ?: '/public_html',
        ];
    }

    /**
     * Salva credenciais (apenas a senha é criptografada)
     */
    public function salvar(array $dados): int
    {
        $payload = [
            'chave'     => $_SESSION['chave'],
            'tipo'      => $dados['tipo'] ?? 'ftp',
            'host'      => $dados['host'],
            'porta'     => (int) ($dados['porta'] ?? 21),
            'usuario'   => $dados['usuario'],
            'senha'     => encrypt($dados['senha']),
            'diretorio' => !empty($dados['diretorio']) ? $dados['diretorio'] : null,
        ];

        $existing = $this->buscarPorChave();

        if ($existing) {
            $payload['updated_at'] = now();
            return $this->qb
                ->table('site_credenciais')
                ->where('id', '=', $existing['id'])
                ->update($payload);
        }

        return $this->qb
            ->table('site_credenciais')
            ->insert($payload);
    }
}
