<?php

namespace App\Models;

/**
 * Model para gerenciamento de Templates de Mensagem
 *
 * Tabela: message_templates, message_template_types
 */
class MessageTemplate extends Model
{
    /**
     * Verifica se existe template customizado para um tipo
     *
     * @param string $slug Slug do tipo de template
     * @param string $chave Chave do tenant
     * @return bool True se existe template customizado
     */
    public function hasCustom(string $slug, string $chave): bool
    {
        $result = $this->qb
            ->table('message_templates', 'mt')
            ->innerJoin('message_template_types', 'mtt', 'mtt.id', '=', 'mt.template_type_id')
            ->where('mtt.slug', '=', $slug)
            ->first();

        return $result !== null;
    }

    /**
     * Busca template customizado por slug
     *
     * @param string $slug Slug do tipo de template
     * @param string $chave Chave do tenant
     * @return array|null Dados do template ou null
     */
    public function buscarPorSlug(string $slug, string $chave): ?array
    {
        return $this->qb
            ->table('message_templates', 'mt')
            ->select([
                'mt.*',
                'mtt.slug',
                'mtt.name as type_name',
                'mtt.description as type_description'
            ])
            ->innerJoin('message_template_types', 'mtt', 'mtt.id', '=', 'mt.template_type_id')
            ->where('mtt.slug', '=', $slug)
            ->first();
    }

    /**
     * Busca tipo de template por slug
     *
     * @param string $slug Slug do tipo
     * @return array|null Dados do tipo ou null
     */
    public function buscarTipoPorSlug(string $slug): ?array
    {
        return $this->qb
            ->table('message_template_types')
            ->withoutChave()
            ->where('slug', '=', $slug)
            ->first();
    }

    /**
     * Lista todos os tipos de template
     *
     * @return array Lista de tipos
     */
    public function listarTipos(): array
    {
        return $this->qb
            ->table('message_template_types')
            ->withoutChave()
            ->orderBy('name', 'ASC')
            ->get();
    }

    /**
     * Retorna conexão mysqli para uso no Service
     * (temporário para manter compatibilidade)
     *
     * @return \mysqli
     */
    public function getMysqliConnection(): \mysqli
    {
        return $this->getMysqli();
    }
}
