<?php

use App\Database\Migration;

/**
 * Registra as notas publicas da versao 7.7.0.
 *
 * Git: 0e96aafd6b0a6ca1228f77a635349a75994bb0eb..7166009ebf66f7bbd68497878cc63f0ac82f4c88
 * Periodo das notas: 2026-07-29 a 2026-10-07.
 * A tabela changelog e global e nao possui coluna chave.
 */
return new class extends Migration
{
    private const VERSION = '7.7.0';

    private const ITEMS = [
        ['data' => '2026-07-29', 'tipo' => 'C', 'mensagem' => 'O cálculo das parcelas na prévia de contratos deixou de descontar o mesmo valor duas vezes.'],
        ['data' => '2026-07-30', 'tipo' => 'A', 'mensagem' => 'Documentos enviados no pré-cadastro passaram a ser validados e armazenados diretamente no cadastro do cliente.'],
        ['data' => '2026-07-30', 'tipo' => 'A', 'mensagem' => 'O cadastro de matrizes e filiais ganhou salvamento integrado de contatos, horários e locais, com controle de acesso às taxas por filial.'],
        ['data' => '2026-07-31', 'tipo' => 'N', 'mensagem' => 'Adicionado relatório de evolução da quilometragem, com agrupamento por dia, semana, mês ou ano.'],
        ['data' => '2026-08-03', 'tipo' => 'N', 'mensagem' => 'Veículos agora podem ser importados em lote por arquivo CSV, com validação e conferência dos dados.'],
        ['data' => '2026-08-03', 'tipo' => 'A', 'mensagem' => 'A geração de PDFs ganhou controle configurável de memória para documentos mais extensos.'],
        ['data' => '2026-08-03', 'tipo' => 'C', 'mensagem' => 'Canais de notificação desativados ou destinatários indisponíveis deixaram de interromper contratos, locações e pagamentos.'],
        ['data' => '2026-08-03', 'tipo' => 'A', 'mensagem' => 'Faturas ganharam informações fiscais mais claras, incluindo CNAE, retenção de impostos e melhor organização visual.'],
        ['data' => '2026-08-04', 'tipo' => 'C', 'mensagem' => 'A situação de pagamento das multas passou a acompanhar corretamente os lançamentos financeiros vinculados.'],
        ['data' => '2026-08-05', 'tipo' => 'A', 'mensagem' => 'A seleção de veículos em contratos ficou mais rápida e passou a permitir busca por grupo, filial e disponibilidade.'],
        ['data' => '2026-08-05', 'tipo' => 'C', 'mensagem' => 'Documentos estrangeiros alfanuméricos deixaram de ser alterados pela formatação de CPF e CNPJ.'],
        ['data' => '2026-08-06', 'tipo' => 'N', 'mensagem' => 'Adicionado suporte ao gateway Santander, incluindo certificados digitais e processamento de pagamentos.'],
        ['data' => '2026-08-06', 'tipo' => 'A', 'mensagem' => 'O acompanhamento das tarefas automáticas agora distingue execuções concluídas, parcialmente concluídas e com falha.'],
        ['data' => '2026-08-06', 'tipo' => 'C', 'mensagem' => 'Links de pagamento passaram a apresentar corretamente o número do endereço da filial.'],
        ['data' => '2026-08-07', 'tipo' => 'N', 'mensagem' => 'Faturas pendentes agora podem ser convertidas em parcelamentos, preservando a cobrança original como primeira parcela.'],
        ['data' => '2026-08-07', 'tipo' => 'C', 'mensagem' => 'Sites publicados passaram a preservar corretamente fórmulas de estilo que dependem de espaços em cálculos CSS.'],
        ['data' => '2026-08-07', 'tipo' => 'A', 'mensagem' => 'A ativação de websites ganhou normalização de domínio e redirecionamento automático para HTTPS sem www.'],
        ['data' => '2026-08-07', 'tipo' => 'A', 'mensagem' => 'Gateways passaram a validar os métodos de pagamento habilitados e os dados obrigatórios para emissão de boleto.'],
        ['data' => '2026-08-10', 'tipo' => 'A', 'mensagem' => 'A pesquisa de clientes passou a localizar registros também pelo telefone, sem duplicar resultados.'],
        ['data' => '2026-08-10', 'tipo' => 'C', 'mensagem' => 'Alterações financeiras em lote passaram a manter consistência entre a data e a situação do pagamento.'],
        ['data' => '2026-08-10', 'tipo' => 'A', 'mensagem' => 'A auditoria do cadastro de funcionários ficou mais precisa e passou a registrar somente alterações efetivas.'],
        ['data' => '2026-08-11', 'tipo' => 'N', 'mensagem' => 'A devolução de contratos ganhou uma prévia dos valores antes da confirmação.'],
        ['data' => '2026-08-11', 'tipo' => 'A', 'mensagem' => 'Comissões de investidores passaram a ser processadas e estornadas automaticamente conforme a situação das parcelas.'],
        ['data' => '2026-08-14', 'tipo' => 'A', 'mensagem' => 'Contratos passaram a informar com mais clareza quando estão próximos do vencimento, vencem no dia ou já estão vencidos.'],
        ['data' => '2026-08-14', 'tipo' => 'N', 'mensagem' => 'A devolução de contratos agora permite ajustar valores comerciais, como plano, franquia e seguros, antes da confirmação.'],
        ['data' => '2026-08-14', 'tipo' => 'N', 'mensagem' => 'Adicionado relatório de Resultado Gerencial por Caixa, com receitas e despesas pagas no período e exportação.'],
        ['data' => '2026-08-14', 'tipo' => 'A', 'mensagem' => 'Valores do Dashboard e da seleção de veículos passaram a respeitar a formatação monetária configurada pela empresa.'],
        ['data' => '2026-08-14', 'tipo' => 'C', 'mensagem' => 'Consultas de matrizes e filiais passaram a respeitar corretamente o isolamento entre empresas.'],
        ['data' => '2026-08-17', 'tipo' => 'C', 'mensagem' => 'A situação e as datas dos contratos passaram a distinguir corretamente contratos ativos, finalizados e com renovação indeterminada.'],
        ['data' => '2026-08-19', 'tipo' => 'C', 'mensagem' => 'Contratos finalizados passaram a exibir corretamente o veículo atual ou o último veículo do histórico.'],
        ['data' => '2026-08-19', 'tipo' => 'C', 'mensagem' => 'Serviços obrigatórios configurados no site passaram a ser aplicados e preservados corretamente nas reservas.'],
        ['data' => '2026-08-24', 'tipo' => 'N', 'mensagem' => 'Adicionado módulo de orçamentos, com criação, consulta, impressão e acesso pelo menu principal.'],
        ['data' => '2026-08-24', 'tipo' => 'N', 'mensagem' => 'Adicionado módulo de sinistros, com ocorrências vinculadas a contratos e locações, cobranças e exclusão auditada.'],
        ['data' => '2026-08-24', 'tipo' => 'A', 'mensagem' => 'Condutores adicionais, fiadores, avalistas e testemunhas passaram a ser organizados em uma única aba de intervenientes.'],
        ['data' => '2026-08-24', 'tipo' => 'N', 'mensagem' => 'A emissão de NFS-e ganhou suporte a clientes estrangeiros, incluindo passaporte, país e identificação do tomador.'],
        ['data' => '2026-08-25', 'tipo' => 'A', 'mensagem' => 'Contratos finalizados ganharam proteção contra alterações indevidas em dados e ajustes financeiros.'],
        ['data' => '2026-08-26', 'tipo' => 'A', 'mensagem' => 'Locações ganharam revalidação de promoções e controles mais seguros para alterações de situação.'],
        ['data' => '2026-08-26', 'tipo' => 'A', 'mensagem' => 'Cancelamentos de NFS-e Betha passaram a ser acompanhados de forma assíncrona, com atualização da situação fiscal.'],
        ['data' => '2026-09-01', 'tipo' => 'A', 'mensagem' => 'O relatório de faturas agora pode ser filtrado por veículo e passou a identificar os filtros aplicados no PDF.'],
        ['data' => '2026-09-01', 'tipo' => 'A', 'mensagem' => 'NFS-e rejeitadas passaram a contar com reenvio automático controlado e tentativa manual adicional para falhas técnicas.'],
        ['data' => '2026-09-01', 'tipo' => 'C', 'mensagem' => 'Os indicadores de frota passaram a considerar corretamente o histórico de compra, venda e disponibilidade dos veículos.'],
        ['data' => '2026-09-01', 'tipo' => 'A', 'mensagem' => 'A configuração de certificados dos gateways ficou mais clara e passou a aceitar os modos PFX/P12 e PEM/KEY.'],
        ['data' => '2026-09-01', 'tipo' => 'A', 'mensagem' => 'Faturas passaram a detalhar separadamente os condutores adicionais, com quantidade, valor unitário e total.'],
        ['data' => '2026-09-02', 'tipo' => 'N', 'mensagem' => 'A NFS-e ganhou suporte às configurações de IBS/CBS, código de tributação nacional e novos códigos de operação fiscal.'],
        ['data' => '2026-09-03', 'tipo' => 'C', 'mensagem' => 'Consultas de veículos vinculados a locações passaram a considerar corretamente apenas locações ativas.'],
        ['data' => '2026-09-05', 'tipo' => 'A', 'mensagem' => 'Manutenções ganharam conta bancária, plano de contas e situação de pagamento, com validação da classificação financeira das parcelas.'],
        ['data' => '2026-09-07', 'tipo' => 'A', 'mensagem' => 'A edição de locações ganhou proteção contra alterações simultâneas, com opção de recarregar os dados quando houver conflito.'],
        ['data' => '2026-09-07', 'tipo' => 'C', 'mensagem' => 'O teste das configurações de e-mail voltou a utilizar corretamente a conta SMTP selecionada.'],
        ['data' => '2026-09-09', 'tipo' => 'A', 'mensagem' => 'Envios por e-mail, SMS e WhatsApp ganharam controle contra duplicidade e recuperação mais segura em caso de falha.'],
        ['data' => '2026-09-10', 'tipo' => 'N', 'mensagem' => 'A exclusão de contratos e locações ganhou uma prévia dos lançamentos financeiros que serão afetados.'],
        ['data' => '2026-09-11', 'tipo' => 'N', 'mensagem' => 'A devolução de contratos agora permite escolher entre cobrança integral e proporcional.'],
        ['data' => '2026-09-15', 'tipo' => 'A', 'mensagem' => 'O relatório de disponibilidade de veículos agora permite selecionar vários status simultaneamente.'],
        ['data' => '2026-09-15', 'tipo' => 'A', 'mensagem' => 'Faturas de locações passaram a exibir descrição das parcelas, forma de pagamento e situação do pagamento.'],
        ['data' => '2026-09-16', 'tipo' => 'N', 'mensagem' => 'Contratos agora permitem calcular, visualizar e faturar cobranças de quilometragem com base nas leituras do odômetro.'],
        ['data' => '2026-09-16', 'tipo' => 'N', 'mensagem' => 'Ao excluir uma reserva pendente, agora é possível escolher se o cliente deve receber uma notificação.'],
        ['data' => '2026-09-16', 'tipo' => 'N', 'mensagem' => 'Adicionado relatório de histórico de odômetros, com filtros, totalizadores e exportação em PDF.'],
        ['data' => '2026-09-21', 'tipo' => 'A', 'mensagem' => 'O envio por WhatsApp ganhou validação mais segura de números e suporte a novos identificadores de destinatários.'],
        ['data' => '2026-09-22', 'tipo' => 'C', 'mensagem' => 'A listagem de assinaturas pendentes passou a apresentar corretamente o nome do cliente.'],
        ['data' => '2026-09-23', 'tipo' => 'A', 'mensagem' => 'A seleção de vínculos de promissórias agora permite pesquisar pelo código ou pelo nome do cliente.'],
        ['data' => '2026-09-28', 'tipo' => 'N', 'mensagem' => 'O gateway Cora ganhou ativação integrada de webhooks para receber e processar atualizações de pagamentos.'],
        ['data' => '2026-09-30', 'tipo' => 'N', 'mensagem' => 'Clientes agora possuem código IBGE do município, com preenchimento automático pelo CEP e opção de consulta manual.'],
        ['data' => '2026-10-02', 'tipo' => 'C', 'mensagem' => 'Formas de pagamento e gateways passaram a respeitar corretamente o isolamento de dados entre empresas.'],
        ['data' => '2026-10-06', 'tipo' => 'A', 'mensagem' => 'A sincronização das conexões do WhatsApp ficou mais resiliente e passou a alterar o status somente após respostas válidas do serviço.'],
        ['data' => '2026-10-07', 'tipo' => 'C', 'mensagem' => 'A criação de contratos ganhou maior segurança nas operações financeiras, evitando bloqueios durante o registro de ajustes e auditorias.'],
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
