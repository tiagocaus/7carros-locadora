<?php

namespace App\Models;

/**
 * Model Pais
 *
 * Gerencia operacoes na tabela global `paises` (sem chave de tenant).
 * Usa nome_i18n (JSON) com traducoes nos 5 idiomas do sistema.
 */
class Pais extends Model
{
    /**
     * Obtem o nome traduzido de um pais
     *
     * @param array $pais Dados do pais (com campo nome_i18n)
     * @param string|null $locale Locale desejado (usa current_locale() se null)
     * @return string Nome no idioma solicitado
     */
    public static function getNome(array $pais, ?string $locale = null): string
    {
        $locale = $locale ?? current_locale();

        if (!empty($pais['nome_i18n'])) {
            $translations = is_array($pais['nome_i18n'])
                ? $pais['nome_i18n']
                : json_decode($pais['nome_i18n'], true);

            if (isset($translations[$locale])) {
                return $translations[$locale];
            }

            // Fallback para pt_BR
            if (isset($translations['pt_BR'])) {
                return $translations['pt_BR'];
            }
        }

        return '';
    }

    /**
     * Lista todos os paises ativos ordenados pelo nome no locale atual
     * Retorna array formatado para uso em chosen-select client-side
     *
     * @param string|null $locale Locale para ordenacao/nome
     * @return array Lista de paises [{codigo, nome_i18n}, ...]
     */
    public function listarAtivos(?string $locale = null): array
    {
        $locale = $locale ?? current_locale();

        return $this->qb
            ->table('paises')
            ->withoutChave()
            ->select(['id', 'codigo', 'nome_i18n', 'codigo_telefone', 'formato_cep'])
            ->where('situacao', '=', 'A')
            ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(nome_i18n, '$.{$locale}')) ASC")
            ->get();
    }

    /**
     * Busca um pais pelo codigo ISO alpha-2
     *
     * @param string $codigo Codigo ISO (ex: 'BR', 'US')
     * @return array|null Dados do pais ou null
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        return $this->qb
            ->table('paises')
            ->withoutChave()
            ->where('codigo', '=', strtoupper($codigo))
            ->first();
    }
}
