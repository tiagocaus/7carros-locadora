<?php

use App\Database\Migration;

/**
 * Migration: Seed templates de promissoria em outros idiomas
 *
 * Cria templates com chave='0' (padrao do sistema) para en_US, es_ES, pt_PT, it_IT
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

        // Templates por idioma
        $templatesByLocale = $this->getTemplatesByLocale();

        $count = 0;
        foreach ($templatesByLocale as $locale => $templates) {
            foreach ($templates as $slug => $content) {
                if (!isset($types[$slug])) {
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
        }

        echo "  - {$count} templates i18n criados/atualizados.\n";
    }

    public function down(): void
    {
        // Remover templates padrao (chave = '0') em idiomas diferentes de pt_BR
        $this->execute("DELETE FROM promissoria_templates WHERE chave = '0' AND locale != 'pt_BR'");
        echo "  - Templates i18n removidos.\n";
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

    /**
     * Retorna templates traduzidos por idioma (sintaxe simples sem condicionais)
     */
    private function getTemplatesByLocale(): array
    {
        return [
            // English (US)
            'en_US' => [
                'promissoria_texto_quitada' => 'By this private instrument, it is declared that <strong>{{cliente.nome}}</strong>, registered under CPF/CNPJ number <strong>{{cliente.cpf_cnpj}}</strong>, holder of ID number <strong>{{cliente.rg}}</strong>, residing at <strong>{{cliente.endereco_completo}}</strong>, has <strong>PAID</strong> the <strong>CREDITOR</strong> the total amount of <strong>{{promissoria.valor_extenso}}</strong>, in <strong>{{promissoria.qtd_parcelas}}</strong> installment(s), as per rental agreement no. <strong>{{promissoria.codigo_contrato}}</strong>, and this promissory note is hereby declared <strong>PAID IN FULL</strong>.',

                'promissoria_texto_pendente' => 'By this private instrument of debt acknowledgment, <strong>{{cliente.nome}}</strong>, registered under CPF/CNPJ number <strong>{{cliente.cpf_cnpj}}</strong>, holder of ID number <strong>{{cliente.rg}}</strong>, residing at <strong>{{cliente.endereco_completo}}</strong>, hereinafter referred to as <strong>DEBTOR</strong>, as per rental agreement no. <strong>{{promissoria.codigo_contrato}}</strong>, promises to pay the <strong>CREDITOR</strong> or their order, the total amount of <strong>{{promissoria.valor_extenso}}</strong>, in <strong>{{promissoria.qtd_parcelas}}</strong> installment(s), as detailed below, payable in the city of <strong>{{empresa.cidade}}</strong>.',

                'parcela_texto_paga' => 'By this private instrument, it is declared that <strong>{{cliente.nome}}</strong>, registered under CPF/CNPJ number <strong>{{cliente.cpf_cnpj}}</strong>, holder of ID number <strong>{{cliente.rg}}</strong>, residing at <strong>{{cliente.endereco_completo}}</strong>, has <strong>PAID</strong> the <strong>CREDITOR</strong> the amount of <strong>{{parcela.valor_extenso}}</strong>, referring to installment <strong>{{parcela.numero}}</strong> of <strong>{{parcela.total}}</strong>, as per rental agreement no. <strong>{{promissoria.codigo_contrato}}</strong>, on <strong>{{parcela.data_pagamento}}</strong>, and this installment is hereby declared <strong>PAID IN FULL</strong>.',

                'parcela_texto_pendente' => 'By this private instrument of debt acknowledgment, <strong>{{cliente.nome}}</strong>, registered under CPF/CNPJ number <strong>{{cliente.cpf_cnpj}}</strong>, holder of ID number <strong>{{cliente.rg}}</strong>, residing at <strong>{{cliente.endereco_completo}}</strong>, hereinafter referred to as <strong>DEBTOR</strong>, as per rental agreement no. <strong>{{promissoria.codigo_contrato}}</strong>, promises to pay the <strong>CREDITOR</strong> or their order, the amount of <strong>{{parcela.valor_extenso}}</strong>, referring to installment <strong>{{parcela.numero}}</strong> of <strong>{{parcela.total}}</strong>, due on <strong>{{parcela.data_vencimento}}</strong>, payable in the city of <strong>{{empresa.cidade}}</strong>.',
            ],

            // Spanish (Spain)
            'es_ES' => [
                'promissoria_texto_quitada' => 'Por el presente instrumento privado, se declara que <strong>{{cliente.nome}}</strong>, inscrito(a) bajo el CPF/CNPJ n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) del documento de identidad n. <strong>{{cliente.rg}}</strong>, con domicilio en <strong>{{cliente.endereco_completo}}</strong>, ha <strong>PAGADO</strong> al <strong>ACREEDOR</strong> el importe total de <strong>{{promissoria.valor_extenso}}</strong>, en <strong>{{promissoria.qtd_parcelas}}</strong> cuota(s), segun contrato de alquiler n. <strong>{{promissoria.codigo_contrato}}</strong>, quedando <strong>SALDADO</strong> el presente pagare.',

                'promissoria_texto_pendente' => 'Por el presente instrumento privado de reconocimiento de deuda, <strong>{{cliente.nome}}</strong>, inscrito(a) bajo el CPF/CNPJ n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) del documento de identidad n. <strong>{{cliente.rg}}</strong>, con domicilio en <strong>{{cliente.endereco_completo}}</strong>, en adelante denominado(a) <strong>DEUDOR(A)</strong>, segun contrato de alquiler n. <strong>{{promissoria.codigo_contrato}}</strong>, se compromete a pagar al <strong>ACREEDOR</strong> o a su orden, el importe total de <strong>{{promissoria.valor_extenso}}</strong>, en <strong>{{promissoria.qtd_parcelas}}</strong> cuota(s), segun se detalla a continuacion, pagadero en la plaza de <strong>{{empresa.cidade}}</strong>.',

                'parcela_texto_paga' => 'Por el presente instrumento privado, se declara que <strong>{{cliente.nome}}</strong>, inscrito(a) bajo el CPF/CNPJ n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) del documento de identidad n. <strong>{{cliente.rg}}</strong>, con domicilio en <strong>{{cliente.endereco_completo}}</strong>, ha <strong>PAGADO</strong> al <strong>ACREEDOR</strong> el importe de <strong>{{parcela.valor_extenso}}</strong>, correspondiente a la cuota <strong>{{parcela.numero}}</strong> de <strong>{{parcela.total}}</strong>, segun contrato de alquiler n. <strong>{{promissoria.codigo_contrato}}</strong>, en fecha <strong>{{parcela.data_pagamento}}</strong>, quedando <strong>SALDADA</strong> la presente cuota.',

                'parcela_texto_pendente' => 'Por el presente instrumento privado de reconocimiento de deuda, <strong>{{cliente.nome}}</strong>, inscrito(a) bajo el CPF/CNPJ n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) del documento de identidad n. <strong>{{cliente.rg}}</strong>, con domicilio en <strong>{{cliente.endereco_completo}}</strong>, en adelante denominado(a) <strong>DEUDOR(A)</strong>, segun contrato de alquiler n. <strong>{{promissoria.codigo_contrato}}</strong>, se compromete a pagar al <strong>ACREEDOR</strong> o a su orden, el importe de <strong>{{parcela.valor_extenso}}</strong>, correspondiente a la cuota <strong>{{parcela.numero}}</strong> de <strong>{{parcela.total}}</strong>, con vencimiento en <strong>{{parcela.data_vencimento}}</strong>, pagadero en la plaza de <strong>{{empresa.cidade}}</strong>.',
            ],

            // Portuguese (Portugal)
            'pt_PT' => [
                'promissoria_texto_quitada' => 'Pelo presente instrumento particular, declara-se que <strong>{{cliente.nome}}</strong>, inscrito(a) no NIF/NIPC sob o n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) do BI/CC n. <strong>{{cliente.rg}}</strong>, residente e domiciliado(a) em <strong>{{cliente.endereco_completo}}</strong>, <strong>PAGOU</strong> ao <strong>CREDOR</strong> a quantia total de <strong>{{promissoria.valor_extenso}}</strong>, em <strong>{{promissoria.qtd_parcelas}}</strong> prestacao(oes), conforme contrato de aluguer n. <strong>{{promissoria.codigo_contrato}}</strong>, dando-se por <strong>QUITADA</strong> a presente livranca.',

                'promissoria_texto_pendente' => 'Pelo presente instrumento particular de confissao de divida, <strong>{{cliente.nome}}</strong>, inscrito(a) no NIF/NIPC sob o n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) do BI/CC n. <strong>{{cliente.rg}}</strong>, residente e domiciliado(a) em <strong>{{cliente.endereco_completo}}</strong>, doravante denominado(a) <strong>DEVEDOR(A)</strong>, conforme contrato de aluguer n. <strong>{{promissoria.codigo_contrato}}</strong>, promete pagar ao <strong>CREDOR</strong> ou a sua ordem, a quantia total de <strong>{{promissoria.valor_extenso}}</strong>, em <strong>{{promissoria.qtd_parcelas}}</strong> prestacao(oes), conforme discriminado abaixo, pagavel na praca de <strong>{{empresa.cidade}}</strong>.',

                'parcela_texto_paga' => 'Pelo presente instrumento particular, declara-se que <strong>{{cliente.nome}}</strong>, inscrito(a) no NIF/NIPC sob o n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) do BI/CC n. <strong>{{cliente.rg}}</strong>, residente e domiciliado(a) em <strong>{{cliente.endereco_completo}}</strong>, <strong>PAGOU</strong> ao <strong>CREDOR</strong> a quantia de <strong>{{parcela.valor_extenso}}</strong>, referente a prestacao <strong>{{parcela.numero}}</strong> de <strong>{{parcela.total}}</strong>, conforme contrato de aluguer n. <strong>{{promissoria.codigo_contrato}}</strong>, em <strong>{{parcela.data_pagamento}}</strong>, dando-se por <strong>QUITADA</strong> a presente prestacao.',

                'parcela_texto_pendente' => 'Pelo presente instrumento particular de confissao de divida, <strong>{{cliente.nome}}</strong>, inscrito(a) no NIF/NIPC sob o n. <strong>{{cliente.cpf_cnpj}}</strong>, portador(a) do BI/CC n. <strong>{{cliente.rg}}</strong>, residente e domiciliado(a) em <strong>{{cliente.endereco_completo}}</strong>, doravante denominado(a) <strong>DEVEDOR(A)</strong>, conforme contrato de aluguer n. <strong>{{promissoria.codigo_contrato}}</strong>, promete pagar ao <strong>CREDOR</strong> ou a sua ordem, a quantia de <strong>{{parcela.valor_extenso}}</strong>, referente a prestacao <strong>{{parcela.numero}}</strong> de <strong>{{parcela.total}}</strong>, com vencimento em <strong>{{parcela.data_vencimento}}</strong>, pagavel na praca de <strong>{{empresa.cidade}}</strong>.',
            ],

            // Italian
            'it_IT' => [
                'promissoria_texto_quitada' => 'Con il presente atto privato, si dichiara che <strong>{{cliente.nome}}</strong>, iscritto(a) al CF/P.IVA con il n. <strong>{{cliente.cpf_cnpj}}</strong>, titolare del documento d\'identita n. <strong>{{cliente.rg}}</strong>, residente e domiciliato(a) in <strong>{{cliente.endereco_completo}}</strong>, ha <strong>PAGATO</strong> al <strong>CREDITORE</strong> l\'importo totale di <strong>{{promissoria.valor_extenso}}</strong>, in <strong>{{promissoria.qtd_parcelas}}</strong> rata(e), come da contratto di noleggio n. <strong>{{promissoria.codigo_contrato}}</strong>, dichiarando la presente cambiale <strong>SALDATA</strong>.',

                'promissoria_texto_pendente' => 'Con il presente atto privato di riconoscimento del debito, <strong>{{cliente.nome}}</strong>, iscritto(a) al CF/P.IVA con il n. <strong>{{cliente.cpf_cnpj}}</strong>, titolare del documento d\'identita n. <strong>{{cliente.rg}}</strong>, residente e domiciliato(a) in <strong>{{cliente.endereco_completo}}</strong>, di seguito denominato(a) <strong>DEBITORE</strong>, come da contratto di noleggio n. <strong>{{promissoria.codigo_contrato}}</strong>, promette di pagare al <strong>CREDITORE</strong> o a suo ordine, l\'importo totale di <strong>{{promissoria.valor_extenso}}</strong>, in <strong>{{promissoria.qtd_parcelas}}</strong> rata(e), come dettagliato di seguito, pagabile nella piazza di <strong>{{empresa.cidade}}</strong>.',

                'parcela_texto_paga' => 'Con il presente atto privato, si dichiara che <strong>{{cliente.nome}}</strong>, iscritto(a) al CF/P.IVA con il n. <strong>{{cliente.cpf_cnpj}}</strong>, titolare del documento d\'identita n. <strong>{{cliente.rg}}</strong>, residente e domiciliato(a) in <strong>{{cliente.endereco_completo}}</strong>, ha <strong>PAGATO</strong> al <strong>CREDITORE</strong> l\'importo di <strong>{{parcela.valor_extenso}}</strong>, relativo alla rata <strong>{{parcela.numero}}</strong> di <strong>{{parcela.total}}</strong>, come da contratto di noleggio n. <strong>{{promissoria.codigo_contrato}}</strong>, in data <strong>{{parcela.data_pagamento}}</strong>, dichiarando la presente rata <strong>SALDATA</strong>.',

                'parcela_texto_pendente' => 'Con il presente atto privato di riconoscimento del debito, <strong>{{cliente.nome}}</strong>, iscritto(a) al CF/P.IVA con il n. <strong>{{cliente.cpf_cnpj}}</strong>, titolare del documento d\'identita n. <strong>{{cliente.rg}}</strong>, residente e domiciliato(a) in <strong>{{cliente.endereco_completo}}</strong>, di seguito denominato(a) <strong>DEBITORE</strong>, come da contratto di noleggio n. <strong>{{promissoria.codigo_contrato}}</strong>, promette di pagare al <strong>CREDITORE</strong> o a suo ordine, l\'importo di <strong>{{parcela.valor_extenso}}</strong>, relativo alla rata <strong>{{parcela.numero}}</strong> di <strong>{{parcela.total}}</strong>, con scadenza il <strong>{{parcela.data_vencimento}}</strong>, pagabile nella piazza di <strong>{{empresa.cidade}}</strong>.',
            ],
        ];
    }
};
