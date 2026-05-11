<?php

namespace App\Models;

/**
 * Model CodigoIndicacao
 *
 * Gerencia codigos de indicacao dos tenants.
 */
class CodigoIndicacao extends Model
{
    /**
     * Busca o codigo de indicacao do tenant
     *
     * @param string $chave Chave do tenant
     * @return array|null Dados ou null
     */
    public function buscarPorChave(string $chave): ?array
    {
        return $this->qb
            ->table('codigos_indicacao')
            ->withoutChave()
            ->where('chave', '=', $chave)
            ->first();
    }

    /**
     * Cria um novo codigo de indicacao
     *
     * @param string $chave Chave do tenant
     * @param string $codigo Codigo de indicacao
     * @return int ID criado
     */
    public function criar(string $chave, string $codigo): int
    {
        return $this->qb
            ->table('codigos_indicacao')
            ->insert([
                'chave' => $chave,
                'codigo' => $codigo,
            ]);
    }

    /**
     * Gera um codigo unico de 6 caracteres alfanumericos
     *
     * @return string Codigo gerado
     */
    public function gerarCodigoUnico(): string
    {
        $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $tamanho = 6;
        $tentativas = 0;
        $maxTentativas = 100;

        do {
            $codigo = '';
            for ($i = 0; $i < $tamanho; $i++) {
                $codigo .= $caracteres[random_int(0, strlen($caracteres) - 1)];
            }

            $existe = $this->qb
                ->table('codigos_indicacao')
                ->withoutChave()
                ->where('codigo', '=', $codigo)
                ->first();

            $tentativas++;
        } while ($existe !== null && $tentativas < $maxTentativas);

        if ($tentativas >= $maxTentativas) {
            throw new \RuntimeException('Nao foi possivel gerar um codigo unico');
        }

        return $codigo;
    }

    /**
     * Busca ou cria o codigo de indicacao do tenant
     *
     * @param string $chave Chave do tenant
     * @return array Dados do codigo
     */
    public function buscarOuCriar(string $chave): array
    {
        $registro = $this->buscarPorChave($chave);

        if ($registro !== null) {
            return $registro;
        }

        $codigo = $this->gerarCodigoUnico();
        $id = $this->criar($chave, $codigo);

        return [
            'id' => $id,
            'chave' => $chave,
            'codigo' => $codigo,
        ];
    }
}
