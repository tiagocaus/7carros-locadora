<?php

use App\Database\Migration;

/**
 * Migration: Seed templates padrao de promissoria em pt_BR
 *
 * Cria templates com chave='0' (padrao do sistema) para todos os tipos
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buscar IDs dos tipos de template
        $types = $this->getTemplateTypes();

        if (empty($types)) {
            echo "  - AVISO: Nenhum tipo de template encontrado. Execute a migration 00218 primeiro.\n";
            return;
        }

        $locale = 'pt_BR';

        // Templates padrao (sintaxe simples sem condicionais)
        $templates = [
            'promissoria_texto_quitada' => 'Pelo presente instrumento particular, declara-se que <strong>{{cliente.nome}}</strong>, inscrito(a) no CPF/CNPJ sob o n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) do RG n. <strong>{{cliente.rg}}</strong>, residente e domiciliado(a) em <strong>{{cliente.endereco_completo}}</strong>, <strong>PAGOU</strong> ao <strong>CREDOR</strong> a importancia total de <strong>{{promissoria.valor_extenso}}</strong>, em <strong>{{promissoria.qtd_parcelas}}</strong> parcela(s), conforme contrato de locacao n. <strong>{{promissoria.codigo_contrato}}</strong>, dando-se por <strong>QUITADA</strong> a presente promissoria.',

            'promissoria_texto_pendente' => 'Pelo presente instrumento particular de confissao de divida, <strong>{{cliente.nome}}</strong>, inscrito(a) no CPF/CNPJ sob o n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) do RG n. <strong>{{cliente.rg}}</strong>, residente e domiciliado(a) em <strong>{{cliente.endereco_completo}}</strong>, doravante denominado(a) <strong>DEVEDOR(A)</strong>, conforme contrato de locacao n. <strong>{{promissoria.codigo_contrato}}</strong>, promete pagar ao <strong>CREDOR</strong> ou a sua ordem, a importancia total de <strong>{{promissoria.valor_extenso}}</strong>, em <strong>{{promissoria.qtd_parcelas}}</strong> parcela(s), conforme discriminado abaixo, pagavel na praca de <strong>{{empresa.cidade}}</strong>.',

            'parcela_texto_paga' => 'Pelo presente instrumento particular, declara-se que <strong>{{cliente.nome}}</strong>, inscrito(a) no CPF/CNPJ sob o n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) do RG n. <strong>{{cliente.rg}}</strong>, residente e domiciliado(a) em <strong>{{cliente.endereco_completo}}</strong>, <strong>PAGOU</strong> ao <strong>CREDOR</strong> a importancia de <strong>{{parcela.valor_extenso}}</strong>, referente a parcela <strong>{{parcela.numero}}</strong> de <strong>{{parcela.total}}</strong>, conforme contrato de locacao n. <strong>{{promissoria.codigo_contrato}}</strong>, em <strong>{{parcela.data_pagamento}}</strong>, dando-se por <strong>QUITADA</strong> a presente parcela.',

            'parcela_texto_pendente' => 'Pelo presente instrumento particular de confissao de divida, <strong>{{cliente.nome}}</strong>, inscrito(a) no CPF/CNPJ sob o n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) do RG n. <strong>{{cliente.rg}}</strong>, residente e domiciliado(a) em <strong>{{cliente.endereco_completo}}</strong>, doravante denominado(a) <strong>DEVEDOR(A)</strong>, conforme contrato de locacao n. <strong>{{promissoria.codigo_contrato}}</strong>, promete pagar ao <strong>CREDOR</strong> ou a sua ordem, a importancia de <strong>{{parcela.valor_extenso}}</strong>, referente a parcela <strong>{{parcela.numero}}</strong> de <strong>{{parcela.total}}</strong>, com vencimento em <strong>{{parcela.data_vencimento}}</strong>, pagavel na praca de <strong>{{empresa.cidade}}</strong>.',
        ];

        $count = 0;
        foreach ($templates as $slug => $content) {
            if (!isset($types[$slug])) {
                echo "  - AVISO: Tipo '{$slug}' nao encontrado.\n";
                continue;
            }

            $typeId = $types[$slug];
            $contentEscaped = addslashes($content);

            // Verificar se ja existe
            $exists = $this->db()->table('promissoria_templates')
                ->select(['id'])
                ->whereRaw('chave = ? AND template_type_id = ? AND locale = ?', ['0', $typeId, $locale])
                ->first();

            if ($exists) {
                // Atualizar
                $this->execute("
                    UPDATE promissoria_templates
                    SET content = '{$contentEscaped}', updated_at = NOW()
                    WHERE chave = '0' AND template_type_id = {$typeId} AND locale = '{$locale}'
                ");
            } else {
                // Inserir
                $this->execute("
                    INSERT INTO promissoria_templates (chave, template_type_id, locale, content, is_active, created_at)
                    VALUES ('0', {$typeId}, '{$locale}', '{$contentEscaped}', 1, NOW())
                ");
            }
            $count++;
        }

        echo "  - {$count} templates padrao pt_BR criados/atualizados.\n";
    }

    public function down(): void
    {
        // Remover apenas templates padrao (chave = '0') em pt_BR
        $this->execute("DELETE FROM promissoria_templates WHERE chave = '0' AND locale = 'pt_BR'");
        echo "  - Templates padrao pt_BR removidos.\n";
    }

    /**
     * Busca IDs dos tipos de template pelo slug
     */
    private function getTemplateTypes(): array
    {
        $rows = $this->db()->table('promissoria_template_types')
            ->select(['id', 'slug'])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['slug']] = (int) $row['id'];
        }

        return $result;
    }
};
