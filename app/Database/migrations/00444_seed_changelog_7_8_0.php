<?php

use App\Database\Migration;

/**
 * Registra as notas publicas da versao 7.8.0.
 *
 * Git: 43aa9380bdec92265a2ba62ee5e858b070fa4b43..ac3c33b8925234bfc7abc0cac6c099e76a914a59
 * Periodo das notas: 2026-10-08 a 2026-11-16.
 * A tabela changelog e global e nao possui coluna chave.
 */
return new class extends Migration
{
    private const VERSION = '7.8.0';

    private const ITEMS = [
        ['data' => '2026-10-08', 'tipo' => 'A', 'mensagem' => 'A edição de contratos passou a exibir o código do registro no cabeçalho, facilitando sua identificação.'],
        ['data' => '2026-10-13', 'tipo' => 'A', 'mensagem' => 'Taxas e serviços passaram a aceitar valor zero, permitindo oferecer serviços gratuitos por filial sem substituir a gratuidade pelo preço de outra unidade.'],
        ['data' => '2026-10-13', 'tipo' => 'C', 'mensagem' => 'A geração e o recálculo das parcelas de contratos passaram a preservar as entradas e devoluções de caução, sem confundi-las com parcelas de aluguel.'],
        ['data' => '2026-10-13', 'tipo' => 'N', 'mensagem' => 'Adicionado relatório de Contas a Receber por Contrato, com filtros, totais vencidos e a vencer, detalhamento de parcelas e impressão em PDF.'],
        ['data' => '2026-10-15', 'tipo' => 'C', 'mensagem' => 'A emissão de NFS-e passou a validar o endereço do cliente antes do envio e a permitir nova tentativa para rejeições históricas específicas já corrigidas.'],
        ['data' => '2026-10-16', 'tipo' => 'C', 'mensagem' => 'Previsões de manutenção preventiva atrasadas passaram a avançar até o próximo ciclo fora da faixa de alerta, evitando ordens de serviço repetidas.'],
        ['data' => '2026-10-19', 'tipo' => 'N', 'mensagem' => 'Os modelos de checklist ganharam ações para restaurar as questões e vistorias padrão, com confirmação antes de substituir itens existentes.'],
        ['data' => '2026-10-23', 'tipo' => 'C', 'mensagem' => 'As taxas das locações passaram a manter os valores calculados corretamente nas faturas, em concordância com o resumo da locação.'],
        ['data' => '2026-10-23', 'tipo' => 'A', 'mensagem' => 'Faturas de contratos e locações passaram a apresentar taxas percentuais em valor monetário, com o percentual e a base de cálculo detalhados.'],
        ['data' => '2026-10-26', 'tipo' => 'C', 'mensagem' => 'As reservas pelo website passaram a calcular as diárias considerando os horários de retirada e devolução e a tolerância configurada para cada grupo e filial.'],
        ['data' => '2026-10-28', 'tipo' => 'A', 'mensagem' => 'A substituição de veículos em contratos passou a exigir o nível de combustível ou carga na chegada, aceitando zero como reserva ou ausência de carga.'],
        ['data' => '2026-10-28', 'tipo' => 'C', 'mensagem' => 'Cobranças de combustível e quilometragem na substituição de veículos passaram a ser lançadas separadamente, sem aumentar o aluguel recorrente nem gerar nova cobrança na renovação.'],
        ['data' => '2026-11-02', 'tipo' => 'N', 'mensagem' => 'A assinatura institucional da matriz ou filial agora pode ser atualizada pelo aplicativo externo, preservando a assinatura anterior se a atualização falhar.'],
        ['data' => '2026-11-02', 'tipo' => 'A', 'mensagem' => 'As conexões do WhatsApp passaram a reaplicar o proxy configurado nas reconexões e a interromper a conexão quando essa configuração falha.'],
        ['data' => '2026-11-02', 'tipo' => 'C', 'mensagem' => 'A listagem de unidades no website passou a apresentar somente matrizes e filiais ativas da locadora.'],
        ['data' => '2026-11-03', 'tipo' => 'C', 'mensagem' => 'O parcelamento mensal de contratos passou a calcular a quantidade de parcelas pela duração do período contratado, independentemente da data do primeiro vencimento.'],
        ['data' => '2026-11-03', 'tipo' => 'C', 'mensagem' => 'O cadastro de planos de contas deixou de bloquear um código por já existir em outra locadora, mantendo a proteção contra duplicidade na mesma empresa.'],
        ['data' => '2026-11-03', 'tipo' => 'C', 'mensagem' => 'Novas parcelas de locações finalizadas passaram a manter o vínculo com o último veículo do histórico, mesmo após a devolução.'],
        ['data' => '2026-11-06', 'tipo' => 'A', 'mensagem' => 'A emissão de NFS-e passou a selecionar o emissor conforme o regime tributário e a competência informada, preparando a configuração Nacional quando houver dados fiscais compatíveis.'],
        ['data' => '2026-11-06', 'tipo' => 'A', 'mensagem' => 'A numeração e as configurações de NFS-e passaram a ser preservadas por emissor e ambiente, mantendo o contexto original nas consultas e cancelamentos de notas antigas.'],
        ['data' => '2026-11-06', 'tipo' => 'N', 'mensagem' => 'NFS-e municipais com rejeição definitiva agora podem originar uma nova tentativa no Emissor Nacional, preservando o histórico e verificando o resultado anterior para evitar duplicidade.'],
        ['data' => '2026-11-09', 'tipo' => 'N', 'mensagem' => 'A edição de conteúdos do website ganhou editor visual com formatação, links, imagens e tabelas, incluindo a personalização das informações de planos na página de reservas.'],
        ['data' => '2026-11-09', 'tipo' => 'C', 'mensagem' => 'O parcelamento de manutenções passou a ratear os itens da primeira fatura pelo valor da parcela, evitando que alterações ou baixas restaurem indevidamente o valor total da ordem de serviço.'],
        ['data' => '2026-11-09', 'tipo' => 'N', 'mensagem' => 'Cobranças Cora pendentes ganharam conciliação automática periódica, permitindo reconhecer pagamentos mesmo quando a notificação do gateway não chegar.'],
        ['data' => '2026-11-09', 'tipo' => 'C', 'mensagem' => 'A confirmação de pagamentos Cora passou a concluir a baixa financeira, a atualização dos links e o registro das taxas em conjunto, evitando pagamentos confirmados com faturas ainda abertas.'],
        ['data' => '2026-11-09', 'tipo' => 'A', 'mensagem' => 'Os comandos de parcelamento passaram a aceitar até 500 caracteres, permitindo cadastrar sequências de vencimentos mais extensas.'],
        ['data' => '2026-11-13', 'tipo' => 'N', 'mensagem' => 'O cadastro de multas ganhou uma opção para baixar o arquivo de foto ou PDF exibido na prévia do anexo.'],
        ['data' => '2026-11-16', 'tipo' => 'C', 'mensagem' => 'O prazo de devolução da caução passou a contar do encerramento efetivo do contrato ou locação, e os avisos passaram a aparecer somente no vencimento, enquanto a caução não for devolvida.'],
        ['data' => '2026-11-16', 'tipo' => 'N', 'mensagem' => 'Adicionada integração com assistentes de IA para consultar frota, clientes, contratos, locações e financeiro, mediante autorização e respeitando as permissões e filiais de cada funcionário.'],
        ['data' => '2026-11-16', 'tipo' => 'N', 'mensagem' => 'Os Logs do Sistema ganharam uma aba de consultas por IA, com busca e detalhes da pergunta informada pelo assistente, da operação e do retorno.'],
        ['data' => '2026-11-16', 'tipo' => 'A', 'mensagem' => 'As consultas por IA ganharam limites de uso e suspensão por tentativas de acesso não permitido, com acompanhamento e liberação por usuários autorizados.'],
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
