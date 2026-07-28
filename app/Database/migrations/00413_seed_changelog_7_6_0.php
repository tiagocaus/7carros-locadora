<?php

use App\Database\Migration;

/**
 * Registra as notas publicas da versao 7.6.0.
 *
 * A tabela changelog e global e nao possui coluna chave.
 */
return new class extends Migration
{
    private const VERSION = '7.6.0';

    private const ITEMS = [
        ['data' => '2026-06-30', 'tipo' => 'N', 'mensagem' => 'Pagamentos PIX passaram a contar com reconciliação automática periódica.'],
        ['data' => '2026-06-30', 'tipo' => 'N', 'mensagem' => 'Veículos agora podem ser duplicados para agilizar o cadastro de unidades semelhantes.'],
        ['data' => '2026-06-30', 'tipo' => 'A', 'mensagem' => 'A seleção de veículos ficou mais clara durante o carregamento e a troca de grupos.'],
        ['data' => '2026-07-01', 'tipo' => 'C', 'mensagem' => 'Datas de vencimento enviadas aos gateways de pagamento passaram a ser normalizadas com mais segurança.'],
        ['data' => '2026-07-21', 'tipo' => 'N', 'mensagem' => 'A devolução de contratos ganhou resumo financeiro, taxas adicionais e gerenciamento de faturas abertas.'],
        ['data' => '2026-07-09', 'tipo' => 'A', 'mensagem' => 'Relatórios veiculares ganharam filtros de situação, níveis de detalhamento e cálculo de novas despesas.'],
        ['data' => '2026-07-02', 'tipo' => 'N', 'mensagem' => 'Adicionado suporte ao gateway Sicoob, incluindo configuração, certificado digital e pagamentos.'],
        ['data' => '2026-07-02', 'tipo' => 'A', 'mensagem' => 'A página pública de pagamento passou a exibir somente formas de pagamento válidas para a cobrança.'],
        ['data' => '2026-07-17', 'tipo' => 'A', 'mensagem' => 'Planos por km controlado ganharam cálculos mais precisos e melhor exibição da franquia disponível.'],
        ['data' => '2026-07-02', 'tipo' => 'A', 'mensagem' => 'Reservas confirmadas ganharam mais opções de impressão de vouchers e checklists.'],
        ['data' => '2026-07-06', 'tipo' => 'A', 'mensagem' => 'Checklists vinculados passaram a reunir as etapas de saída e chegada em um fluxo mais simples.'],
        ['data' => '2026-07-07', 'tipo' => 'N', 'mensagem' => 'Promissórias agora podem ser assinadas digitalmente e consultadas entre as assinaturas pendentes.'],
        ['data' => '2026-07-06', 'tipo' => 'A', 'mensagem' => 'Descrições de serviços da NFS-e passaram a identificar melhor os veículos relacionados.'],
        ['data' => '2026-07-10', 'tipo' => 'A', 'mensagem' => 'Faturas passaram a apresentar um bloco organizado com os veículos relacionados.'],
        ['data' => '2026-07-07', 'tipo' => 'A', 'mensagem' => 'Manutenções ganharam conta bancária, situação de pagamento e melhor visualização das parcelas geradas.'],
        ['data' => '2026-07-08', 'tipo' => 'A', 'mensagem' => 'Multas passaram a exibir informações mais completas sobre o veículo relacionado.'],
        ['data' => '2026-07-09', 'tipo' => 'A', 'mensagem' => 'Documentos em PDF ganharam composição mais consistente de margens, cabeçalhos e rodapés.'],
        ['data' => '2026-07-09', 'tipo' => 'C', 'mensagem' => 'Totais, quantidades e filtros dos relatórios financeiros foram corrigidos para maior precisão.'],
        ['data' => '2026-07-09', 'tipo' => 'C', 'mensagem' => 'Reservas online passaram a bloquear corretamente grupos esgotados e registrar a filial de retirada.'],
        ['data' => '2026-07-09', 'tipo' => 'C', 'mensagem' => 'O cancelamento de NFS-e ganhou validações e tratamento mais confiável das informações do prestador.'],
        ['data' => '2026-07-09', 'tipo' => 'C', 'mensagem' => 'A devolução de veículos passou a validar divergências de odômetro com mensagens mais claras.'],
        ['data' => '2026-07-10', 'tipo' => 'A', 'mensagem' => 'Documentos de contratos, locações e multas ganharam valores destacados e melhor adaptação ao idioma.'],
        ['data' => '2026-07-10', 'tipo' => 'C', 'mensagem' => 'O fechamento de locações passou a calcular parcelas com base nos dados corretos da saída e devolução.'],
        ['data' => '2026-07-13', 'tipo' => 'N', 'mensagem' => 'Gravações agora podem ser enviadas em partes, com retomada e limpeza automática de arquivos antigos.'],
        ['data' => '2026-07-13', 'tipo' => 'A', 'mensagem' => 'Mensagens por e-mail passaram a respeitar todos os endereços autorizados de cada cliente.'],
        ['data' => '2026-07-13', 'tipo' => 'N', 'mensagem' => 'Templates de cobrança ganharam variáveis para identificar a parcela atual e o total de parcelas.'],
        ['data' => '2026-07-13', 'tipo' => 'N', 'mensagem' => 'Lançamentos financeiros agora podem ser selecionados e excluídos em lote.'],
        ['data' => '2026-07-14', 'tipo' => 'N', 'mensagem' => 'Formas de pagamento agora permitem definir a conta de despesa usada para contabilizar taxas.'],
        ['data' => '2026-07-15', 'tipo' => 'N', 'mensagem' => 'Funcionários autorizados agora podem receber notificações internas sobre novas reservas do site.'],
        ['data' => '2026-07-15', 'tipo' => 'A', 'mensagem' => 'A troca do cliente pagador em manutenções ganhou confirmação e auditoria para evitar alterações indevidas.'],
        ['data' => '2026-07-16', 'tipo' => 'N', 'mensagem' => 'O aplicativo da loja ganhou opções de assinatura para fiador, avalista e testemunhas.'],
        ['data' => '2026-07-16', 'tipo' => 'A', 'mensagem' => 'Seletores de forma de pagamento e conta bancária nas locações ficaram mais rápidos e fáceis de pesquisar.'],
        ['data' => '2026-07-16', 'tipo' => 'N', 'mensagem' => 'Locações e reservas online agora aceitam códigos promocionais com validação automática das regras.'],
        ['data' => '2026-07-16', 'tipo' => 'C', 'mensagem' => 'O acesso ao painel administrativo passou a bloquear corretamente usuários sem permissão.'],
        ['data' => '2026-07-16', 'tipo' => 'N', 'mensagem' => 'A devolução de veículos agora pode gerar uma ordem de serviço de manutenção.'],
        ['data' => '2026-07-17', 'tipo' => 'A', 'mensagem' => 'A ativação de websites ganhou verificação automática da disponibilidade do domínio.'],
        ['data' => '2026-07-21', 'tipo' => 'N', 'mensagem' => 'Contratos ganharam histórico e edição de odômetros com proteção contra alterações simultâneas.'],
        ['data' => '2026-07-17', 'tipo' => 'N', 'mensagem' => 'Modelos de documentos de contratos ganharam uma variável com as informações do plano dos veículos.'],
        ['data' => '2026-07-17', 'tipo' => 'C', 'mensagem' => 'O fechamento de locações passou a considerar corretamente avarias no cálculo da diferença financeira.'],
        ['data' => '2026-07-21', 'tipo' => 'N', 'mensagem' => 'Cauções de contratos e locações agora permitem registrar observações adicionais.'],
        ['data' => '2026-07-21', 'tipo' => 'A', 'mensagem' => 'Parcelamentos passaram a validar limites entre 2 e 120 parcelas, e relatórios ganharam situação de pagamento.'],
        ['data' => '2026-07-22', 'tipo' => 'N', 'mensagem' => 'Reservas online agora permitem configurar seguros obrigatórios para o veículo e para terceiros.'],
        ['data' => '2026-07-22', 'tipo' => 'N', 'mensagem' => 'A substituição de veículos em contratos agora pode gerar uma ordem de serviço de manutenção.'],
        ['data' => '2026-07-27', 'tipo' => 'A', 'mensagem' => 'Mensagens por WhatsApp e SMS passaram a respeitar todos os telefones autorizados de cada cliente.'],
        ['data' => '2026-07-27', 'tipo' => 'N', 'mensagem' => 'Clientes agora podem ser importados em lote por arquivo CSV, com modelo e validações.'],
        ['data' => '2026-07-27', 'tipo' => 'C', 'mensagem' => 'A exclusão de contratos e locações passou a liberar bloqueios no gateway antes de remover os registros.'],
        ['data' => '2026-07-27', 'tipo' => 'N', 'mensagem' => 'Veículos agora permitem ajustar o valor por fração em lote.'],
        ['data' => '2026-07-28', 'tipo' => 'N', 'mensagem' => 'Adicionado Portal do Cliente e do Investidor, com acesso seguro a dados, pagamentos e documentos.'],
    ];

    public function up(): void
    {
        $inseridos = 0;

        foreach (self::ITEMS as $item) {
            $existe = $this->db()
                ->table('changelog')
                ->withoutChave()
                ->where('versao', '=', self::VERSION)
                ->where('tipo', '=', $item['tipo'])
                ->where('data', '=', $item['data'])
                ->where('mensagem', '=', $item['mensagem'])
                ->exists();

            if ($existe) {
                continue;
            }

            $this->db()
                ->table('changelog')
                ->withoutChave()
                ->insert([
                    'versao' => self::VERSION,
                    'tipo' => $item['tipo'],
                    'data' => $item['data'],
                    'mensagem' => $item['mensagem'],
                ]);

            $inseridos++;
        }

        echo "  - changelog " . self::VERSION . ": {$inseridos} registro(s) inserido(s).\n";
    }

    public function down(): void
    {
        // No-op: changelog e historico publicado e pode ter sido inserido antes da migration.
    }
};
