<?php

/**
 * Migration: cria modelos padrao globais de contrato e locacao.
 */

use App\Database\Migration;

return new class extends Migration
{
    private const TITULO_CONTRATO = 'Contrato de Locacao de Veiculo(s) - Padrao do Sistema';
    private const TITULO_LOCACAO = 'Termo de Locacao de Veiculo - Padrao do Sistema';

    public function up(): void
    {
        $textoContrato = <<<'HTML'
<h2 style="text-align:center;">CONTRATO DE LOCACAO DE VEICULO(S)</h2>

<p><strong>LOCADORA:</strong> {{empresa.razao_social}}, inscrita no CPF/CNPJ sob nº {{empresa.cnpj}}, com sede em {{empresa.endereco}}, nº {{empresa.numero}}, {{empresa.bairro}}, {{empresa.cidade}}/{{empresa.uf}}, neste ato denominada simplesmente <strong>LOCADORA</strong>.</p>

<p><strong>LOCATARIO:</strong> {{cliente.nome}}, inscrito no CPF/CNPJ sob nº {{cliente.cpf_cnpj}}, residente/sediado em {{cliente.endereco}}, nº {{cliente.numero}}, {{cliente.bairro}}, {{cliente.cidade}}/{{cliente.uf}}, telefone {{cliente.telefone}}, e-mail {{cliente.email}}, neste ato denominado simplesmente <strong>LOCATARIO</strong>.</p>

<p>As partes acima identificadas resolvem celebrar o presente Contrato de Locacao de Veiculo(s), que sera regido pelas clausulas e condicoes abaixo.</p>

<h3>1. OBJETO</h3>
<p>1.1. A LOCADORA entrega ao LOCATARIO, em locacao, o(s) veiculo(s) descrito(s) no <strong>ANEXO I - DEMONSTRATIVO DE VEICULO(S)</strong>, que passa a integrar este contrato para todos os fins de direito.</p>
<p>1.2. O(s) veiculo(s) podera(ao) ser de propriedade da LOCADORA ou de terceiros, fornecedores ou investidores, desde que esteja(m) sob posse, gestao, autorizacao, administracao ou disponibilidade legitima da LOCADORA, respondendo esta perante o LOCATARIO pela execucao deste contrato.</p>
<p>1.3. A inclusao de fornecedor, proprietario ou investidor no Anexo I tem finalidade de identificacao do veiculo e de controle operacional, nao tornando tal pessoa parte contratual perante o LOCATARIO, salvo se houver instrumento proprio assinado pelas partes.</p>

<h3>2. PRAZO, ENTREGA E DEVOLUCAO</h3>
<p>2.1. O prazo de locacao inicia-se em {{contrato.data_inicio}} as {{contrato.hora_inicio}} e tem termino previsto para {{contrato.data_fim}} as {{contrato.hora_fim}}, salvo renovacao, prorrogacao, substituicao ou devolucao antecipada registrada pela LOCADORA.</p>
<p>2.2. A entrega e a devolucao do(s) veiculo(s) serao comprovadas por registros do sistema, checklist, fotos, assinatura digital, odometro, combustivel/carga, recibos ou demais documentos emitidos pela LOCADORA.</p>
<p>2.3. Havendo permanencia do LOCATARIO na posse do(s) veiculo(s) apos o termino previsto, poderao ser cobradas diarias, periodos adicionais, encargos, multas contratuais e demais valores previstos neste contrato ou na tabela vigente da LOCADORA.</p>

<h3>3. PRECO, PAGAMENTO E ENCARGOS</h3>
<p>3.1. O valor total contratado e de <strong>{{contrato.valor_total}}</strong>, conforme plano, periodo, taxas, servicos e condicoes comerciais registrados no sistema da LOCADORA.</p>
<p>3.2. A forma de pagamento pactuada e: <strong>{{contrato.forma_pagamento}}</strong>. Primeiro pagamento: <strong>{{contrato.primeiro_pagamento}}</strong>.</p>
<p>3.3. Alem do preco da locacao, poderao ser cobrados do LOCATARIO, quando aplicaveis: quilometragem excedente, combustivel/carga, multas de transito, pedagios, estacionamentos, avarias, sinistros, franquias, limpeza, guincho, despesas administrativas, taxas, servicos adicionais e demais valores vinculados ao uso ou posse do(s) veiculo(s).</p>

<h3>4. USO, GUARDA E RESPONSABILIDADE DO LOCATARIO</h3>
<p>4.1. O LOCATARIO declara receber o(s) veiculo(s) em condicoes de uso e obriga-se a utiliza-lo(s) de forma regular, prudente e em conformidade com a legislacao de transito.</p>
<p>4.2. O LOCATARIO assume a guarda e responsabilidade pelo(s) veiculo(s) durante todo o periodo em que estiver(em) sob sua posse, inclusive por atos de condutores adicionais, prepostos, empregados, familiares, terceiros autorizados ou nao autorizados que venham a utilizar o(s) veiculo(s).</p>
<p>4.3. E vedado ao LOCATARIO utilizar o(s) veiculo(s) em competicoes, transporte ilicito, sublocacao, aprendizagem de direcao, reboque nao autorizado, transporte remunerado nao contratado, areas de risco incompatíveis com o uso normal, ou qualquer finalidade diversa daquela contratada.</p>

<h3>5. MULTAS, DANOS, SINISTROS E INFRACOES</h3>
<p>5.1. O LOCATARIO sera responsavel por multas, pontuacoes, infracoes, despesas, danos, avarias, sinistros, perdas, furtos, roubos, apropriacao indevida, mau uso, negligencia, imprudencia ou impericia ocorridos durante sua posse ou decorrentes de sua conduta.</p>
<p>5.2. A existencia de seguro, protecao, cobertura ou responsabilidade de terceiros nao afasta a obrigacao do LOCATARIO de pagar franquias, coparticipacoes, diferencas nao cobertas, despesas administrativas e valores nao indenizados pela seguradora ou protecao contratada.</p>
<p>5.3. O LOCATARIO devera comunicar imediatamente a LOCADORA sobre acidente, furto, roubo, apreensao, pane, multa, dano, sinistro ou qualquer evento relevante envolvendo o(s) veiculo(s), apresentando boletim de ocorrencia e demais documentos quando exigidos.</p>

<h3>6. SUBSTITUICAO, ACRESCIMO OU RETIRADA DE VEICULOS</h3>
<p>6.1. A LOCADORA podera substituir, acrescentar ou retirar veiculo(s) do contrato mediante registro no sistema, checklist, termo, demonstrativo, anexo atualizado ou outro documento equivalente.</p>
<p>6.2. A substituicao por veiculo equivalente ou superior nao implica novacao contratual, permanecendo validas as demais clausulas deste instrumento, salvo ajuste expresso em contrario.</p>
<p>6.3. Cada veiculo entregue ao LOCATARIO sera considerado vinculado a este contrato enquanto constar dos registros da LOCADORA ou enquanto permanecer sob posse do LOCATARIO.</p>

<h3>7. AUTORIZACOES, ASSINATURA DIGITAL E DOCUMENTOS ELETRONICOS</h3>
<p>7.1. O LOCATARIO reconhece como validos os documentos, comprovantes, checklists, fotos, registros de sistema, logs, mensagens, aceite eletronico e assinatura digital vinculados a este contrato.</p>
<p>7.2. A assinatura digital ou eletronica deste contrato, quando utilizada, tera validade entre as partes, vinculando o LOCATARIO as condicoes aqui estabelecidas e aos anexos gerados no sistema.</p>

<h3>8. RESCISAO E INADIMPLEMENTO</h3>
<p>8.1. O atraso no pagamento, uso irregular, negativa de devolucao, descumprimento de obrigacoes, risco ao patrimonio, fraude, informacao falsa ou violacao deste contrato autoriza a LOCADORA a rescindir o contrato, exigir a devolucao imediata do(s) veiculo(s), cobrar os valores devidos e adotar as medidas administrativas, extrajudiciais ou judiciais cabiveis.</p>
<p>8.2. A tolerancia da LOCADORA quanto a eventual descumprimento contratual nao constituira renuncia, novacao ou alteracao das condicoes pactuadas.</p>

<h3>9. DISPOSICOES GERAIS</h3>
<p>9.1. As observacoes operacionais registradas no contrato integram este instrumento: {{contrato.observacoes}}</p>
<p>9.2. Fica eleito o foro competente conforme a sede da LOCADORA, salvo disposicao legal obrigatoria diversa.</p>

<h3>ANEXO I - DEMONSTRATIVO DE VEICULO(S)</h3>
<p>O(s) veiculo(s) abaixo identificado(s) integra(m) o objeto deste contrato, juntamente com seus dados de saida, condicoes comerciais e informacoes de fornecedor/proprietario/investidor quando existentes.</p>

{{contrato.veiculos_anexo}}

<p style="margin-top:30px;">E, por estarem justas e contratadas, as partes firmam o presente instrumento.</p>

<table style="width:100%; margin-top:50px; border-collapse:collapse;">
    <tr>
        <td style="width:50%; text-align:center; padding:20px;">
            <div style="border-top:1px solid #000; padding-top:6px;"><strong>LOCADORA</strong><br>{{empresa.razao_social}}</div>
        </td>
        <td style="width:50%; text-align:center; padding:20px;">
            <div style="border-top:1px solid #000; padding-top:6px;"><strong>LOCATARIO</strong><br>{{cliente.nome}}</div>
        </td>
    </tr>
</table>

{{contrato.fiadores_assinaturas_colunas}}
{{contrato.avalistas_assinaturas_colunas}}
{{contrato.testemunhas_assinaturas_colunas}}
HTML;

        $this->salvarDocumentoPadrao(self::TITULO_CONTRATO, 1, $textoContrato, 'contrato');

        $textoLocacao = <<<'HTML'
<h2 style="text-align:center;">TERMO DE LOCACAO DE VEICULO</h2>

<p><strong>LOCADORA:</strong> {{empresa.razao_social}}, inscrita no CPF/CNPJ sob nº {{empresa.cnpj}}, com sede em {{empresa.endereco}}, nº {{empresa.numero}}, {{empresa.bairro}}, {{empresa.cidade}}/{{empresa.uf}}, neste ato denominada simplesmente <strong>LOCADORA</strong>.</p>

<p><strong>LOCATARIO:</strong> {{cliente.nome}}, inscrito no CPF/CNPJ sob nº {{cliente.cpf_cnpj}}, residente/sediado em {{cliente.endereco}}, nº {{cliente.numero}}, {{cliente.bairro}}, {{cliente.cidade}}/{{cliente.uf}}, telefone {{cliente.telefone}}, e-mail {{cliente.email}}, neste ato denominado simplesmente <strong>LOCATARIO</strong>.</p>

<p>As partes acima identificadas celebram o presente Termo de Locacao de Veiculo, vinculado a locacao nº <strong>{{locacao.numero}}</strong>, mediante as clausulas e condicoes seguintes.</p>

<h3>1. VEICULO LOCADO</h3>
<p>1.1. A LOCADORA entrega ao LOCATARIO o veiculo <strong>{{veiculo.descricao_completa}}</strong>, placa <strong>{{veiculo.placa}}</strong>, categoria/grupo <strong>{{locacao.grupo}}</strong>, para uso temporario durante o periodo contratado.</p>
<p>1.2. O veiculo podera ser de propriedade da LOCADORA ou de terceiro/fornecedor/investidor, desde que esteja sob posse, gestao, autorizacao, administracao ou disponibilidade legitima da LOCADORA.</p>
<p>1.3. Quando houver fornecedor ou investidor vinculado ao veiculo, essa informacao tera finalidade operacional e de identificacao, sem tornar tal pessoa parte contratual perante o LOCATARIO, salvo instrumento proprio assinado.</p>

<h3>2. RETIRADA, DEVOLUCAO E VISTORIA</h3>
<p>2.1. A retirada esta prevista para {{locacao.data_retirada}} as {{locacao.hora_retirada}}, no local {{locacao.local_retirada}}.</p>
<p>2.2. A devolucao esta prevista para {{locacao.data_devolucao}} as {{locacao.hora_devolucao}}, no local {{locacao.local_devolucao}}.</p>
<p>2.3. A entrega e a devolucao serao comprovadas por registros do sistema, checklist, fotos, assinatura digital, odometro, combustivel/carga e demais documentos emitidos pela LOCADORA.</p>
<p>2.4. A devolucao fora do prazo, em local diverso ou em condicoes diferentes das registradas na retirada podera gerar cobranca de diarias adicionais, deslocamento, avarias, limpeza, combustivel/carga, km excedente e demais encargos aplicaveis.</p>

<h3>3. VALORES, PAGAMENTO E GARANTIAS</h3>
<p>3.1. O valor total da locacao e de <strong>{{locacao.valor_total}}</strong>, conforme periodo, plano, taxas, servicos e condicoes registrados no sistema da LOCADORA.</p>
<p>3.2. A forma de pagamento pactuada e: <strong>{{locacao.forma_pagamento}}</strong>.</p>
<p>3.3. A caucao, deposito, bloqueio ou garantia vinculada a locacao, quando existente, e de <strong>{{locacao.deposito_valor}}</strong> ou conforme registro financeiro do sistema.</p>
<p>3.4. A garantia podera ser utilizada para compensar valores devidos pelo LOCATARIO, inclusive danos, multas, franquias, combustivel/carga, km excedente, diarias adicionais, despesas administrativas e demais encargos.</p>

<h3>4. USO DO VEICULO</h3>
<p>4.1. O LOCATARIO declara receber o veiculo em condicoes de uso e obriga-se a utiliza-lo com prudencia, zelo e observancia da legislacao de transito.</p>
<p>4.2. O LOCATARIO assume a guarda e responsabilidade pelo veiculo durante todo o periodo de posse, inclusive por atos de condutores adicionais, terceiros autorizados ou nao autorizados, empregados, prepostos ou familiares.</p>
<p>4.3. E proibido utilizar o veiculo para fins ilicitos, competicoes, sublocacao, aprendizagem de direcao, transporte remunerado nao contratado, reboque nao autorizado, areas de risco incompatíveis com o uso normal ou finalidade diversa da contratada.</p>

<h3>5. MULTAS, DANOS, SINISTROS E ENCARGOS</h3>
<p>5.1. O LOCATARIO sera responsavel por multas, infracoes, pontuacoes, pedagios, estacionamentos, danos, avarias, sinistros, perdas, furto, roubo, apropriacao indevida, mau uso, negligencia, imprudencia ou impericia ocorridos durante sua posse.</p>
<p>5.2. A existencia de seguro, protecao ou cobertura nao afasta a obrigacao do LOCATARIO de pagar franquias, coparticipacoes, diferencas nao cobertas, despesas administrativas e valores nao indenizados.</p>
<p>5.3. O LOCATARIO devera comunicar imediatamente a LOCADORA sobre acidente, pane, dano, multa, apreensao, furto, roubo ou qualquer evento relevante, apresentando boletim de ocorrencia e documentos exigidos quando aplicavel.</p>

<h3>6. QUILOMETRAGEM, COMBUSTIVEL/CARGA E CONDICOES OPERACIONAIS</h3>
<p>6.1. O plano contratado e: <strong>{{locacao.info_plano}}</strong>.</p>
<p>6.2. O veiculo devera ser devolvido com combustivel/carga, odometro, acessorios e condicoes compatíveis com os registros de retirada, ressalvado o desgaste natural de uso regular.</p>
<p>6.3. Diferencas de combustivel/carga, quilometragem excedente, avarias, ausencia de acessorios ou divergencias registradas no checklist poderao ser cobradas do LOCATARIO.</p>

<h3>7. ASSINATURA DIGITAL E DOCUMENTOS ELETRONICOS</h3>
<p>7.1. O LOCATARIO reconhece como validos os documentos, comprovantes, checklists, fotos, registros de sistema, logs, mensagens, aceite eletronico e assinatura digital vinculados a esta locacao.</p>
<p>7.2. A assinatura digital ou eletronica deste termo, quando utilizada, vincula o LOCATARIO as condicoes aqui estabelecidas e aos registros gerados pela LOCADORA.</p>

<h3>8. INADIMPLEMENTO E DEVOLUCAO IMEDIATA</h3>
<p>8.1. O atraso no pagamento, uso irregular, recusa de devolucao, informacao falsa, risco ao patrimonio, fraude ou descumprimento deste termo autoriza a LOCADORA a encerrar a locacao, exigir a devolucao imediata do veiculo, cobrar os valores devidos e adotar as medidas cabiveis.</p>

<h3>9. OBSERVACOES</h3>
<p>{{locacao.observacoes}}</p>

<p style="margin-top:30px;">E, por estarem justas e contratadas, as partes firmam o presente termo.</p>

<table style="width:100%; margin-top:50px; border-collapse:collapse;">
    <tr>
        <td style="width:50%; text-align:center; padding:20px;">
            <div style="border-top:1px solid #000; padding-top:6px;"><strong>LOCADORA</strong><br>{{empresa.razao_social}}</div>
        </td>
        <td style="width:50%; text-align:center; padding:20px;">
            <div style="border-top:1px solid #000; padding-top:6px;"><strong>LOCATARIO</strong><br>{{cliente.nome}}</div>
        </td>
    </tr>
</table>
HTML;

        $this->salvarDocumentoPadrao(self::TITULO_LOCACAO, 2, $textoLocacao, 'locacao');
    }

    private function salvarDocumentoPadrao(string $titulo, int $tipo, string $texto, string $label): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM documentos
            WHERE chave = '0' AND tipo = :tipo AND titulo = :titulo
            LIMIT 1
        ");
        $stmt->execute([
            'tipo' => $tipo,
            'titulo' => $titulo,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $update = $this->pdo->prepare("
                UPDATE documentos
                SET texto = :texto, status = 1, updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute([
                'texto' => $texto,
                'id' => (int) $row['id'],
            ]);
            echo "  - modelo padrao de {$label} atualizado.\n";
            return;
        }

        $insert = $this->pdo->prepare("
            INSERT INTO documentos (chave, titulo, texto, tipo, status, created_at)
            VALUES ('0', :titulo, :texto, :tipo, 1, NOW())
        ");
        $insert->execute([
            'titulo' => $titulo,
            'texto' => $texto,
            'tipo' => $tipo,
        ]);

        echo "  - modelo padrao de {$label} criado.\n";
    }

    public function down(): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM documentos
            WHERE chave = '0' AND titulo IN (:titulo_contrato, :titulo_locacao)
        ");
        $stmt->execute([
            'titulo_contrato' => self::TITULO_CONTRATO,
            'titulo_locacao' => self::TITULO_LOCACAO,
        ]);
    }
};
