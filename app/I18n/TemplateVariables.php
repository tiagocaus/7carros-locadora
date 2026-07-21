<?php

declare(strict_types=1);

namespace App\I18n;

use App\Helpers\CurrencyHelper;
use App\Models\ComandoParcela;

/**
 * Define e gerencia variáveis disponíveis para templates de mensagem
 *
 * Variáveis usam o formato {{entidade.campo}}
 *
 * Entidades disponíveis:
 * - cliente: dados do cliente
 * - empresa: dados da empresa/locadora
 * - contrato: dados do contrato
 * - locacao: dados da locação
 * - veiculo: dados do veículo
 * - fatura: dados da fatura
 * - outros: variáveis auxiliares (data_atual, hora_atual, etc.)
 *
 * @example
 * $vars = TemplateVariables::getAll();
 * $clienteVars = TemplateVariables::getForEntity('cliente');
 * $valor = TemplateVariables::resolve('cliente.nome', $context);
 */
class TemplateVariables
{
    /**
     * Definição de todas as variáveis por entidade
     *
     * Estrutura:
     * [
     *   'entidade' => [
     *     'campo' => [
     *       'key' => 'chave no contexto ou callback',
     *       'type' => 'text|currency|date|phone|document|computed',
     *       'label_key' => 'chave de tradução para o nome',
     *       'example' => 'valor de exemplo'
     *     ]
     *   ]
     * ]
     */
    private const VARIABLES = [
        'cliente' => [
            'nome' => [
                'key' => 'cliente.nome',
                'type' => 'text',
                'label_key' => 'variables.cliente.nome',
                'example' => 'João da Silva'
            ],
            'cpf_cnpj' => [
                'key' => 'cliente.cpf_cnpj',
                'type' => 'document',
                'label_key' => 'variables.cliente.cpf_cnpj',
                'example' => '123.456.789-00'
            ],
            'email' => [
                'key' => 'cliente.email',
                'type' => 'text',
                'label_key' => 'variables.cliente.email',
                'example' => 'cliente@email.com'
            ],
            'telefone' => [
                'key' => 'cliente.telefone',
                'type' => 'phone',
                'label_key' => 'variables.cliente.telefone',
                'example' => '(11) 99999-9999'
            ],
            'celular' => [
                'key' => 'cliente.celular',
                'type' => 'phone',
                'label_key' => 'variables.cliente.celular',
                'example' => '(11) 99999-9999'
            ],
            'endereco_completo' => [
                'key' => 'cliente.endereco_completo',
                'type' => 'computed',
                'label_key' => 'variables.cliente.endereco_completo',
                'example' => 'Rua das Flores, 123 - Centro - São Paulo/SP - 01234-567'
            ],
            'cnh_numero' => [
                'key' => 'cliente.cnh_numero',
                'type' => 'text',
                'label_key' => 'variables.cliente.cnh_numero',
                'example' => '12345678900'
            ],
            'cnh_validade' => [
                'key' => 'cliente.cnh_validade',
                'type' => 'date',
                'label_key' => 'variables.cliente.cnh_validade',
                'example' => '31/12/2025'
            ],
            'primeiro_nome' => [
                'key' => 'cliente.primeiro_nome',
                'type' => 'computed',
                'label_key' => 'variables.cliente.primeiro_nome',
                'example' => 'João'
            ],
            'nome_fantasia' => [
                'key' => 'cliente.nome_fantasia',
                'type' => 'text',
                'label_key' => 'variables.cliente.nome_fantasia',
                'example' => 'Transportes Silva'
            ],
            'cep' => [
                'key' => 'cliente.cep',
                'type' => 'text',
                'label_key' => 'variables.cliente.cep',
                'example' => '01234-567'
            ],
            'rg_ie' => [
                'key' => 'cliente.rg_ie',
                'type' => 'text',
                'label_key' => 'variables.cliente.rg_ie',
                'example' => '12.345.678-9'
            ],
            'rg' => [
                'key' => 'cliente.rg',
                'type' => 'text',
                'label_key' => 'variables.cliente.rg',
                'example' => '12.345.678-9'
            ],
            'endereco' => [
                'key' => 'cliente.endereco',
                'type' => 'text',
                'label_key' => 'variables.cliente.endereco',
                'example' => 'Rua das Flores'
            ],
            'numero' => [
                'key' => 'cliente.numero',
                'type' => 'text',
                'label_key' => 'variables.cliente.numero',
                'example' => '123'
            ],
            'complemento' => [
                'key' => 'cliente.complemento',
                'type' => 'text',
                'label_key' => 'variables.cliente.complemento',
                'example' => 'Apto 45'
            ],
            'bairro' => [
                'key' => 'cliente.bairro',
                'type' => 'text',
                'label_key' => 'variables.cliente.bairro',
                'example' => 'Centro'
            ],
            'cidade' => [
                'key' => 'cliente.cidade',
                'type' => 'text',
                'label_key' => 'variables.cliente.cidade',
                'example' => 'São Paulo'
            ],
            'uf' => [
                'key' => 'cliente.uf',
                'type' => 'text',
                'label_key' => 'variables.cliente.uf',
                'example' => 'SP'
            ],
            'data_nascimento' => [
                'key' => 'cliente.data_nascimento',
                'type' => 'date',
                'label_key' => 'variables.cliente.data_nascimento',
                'example' => '15/03/1985'
            ],
            'pais' => [
                'key' => 'cliente.pais',
                'type' => 'text',
                'label_key' => 'variables.cliente.pais',
                'example' => 'Brasil'
            ],
            'profissao' => [
                'key' => 'cliente.profissao',
                'type' => 'text',
                'label_key' => 'variables.cliente.profissao',
                'example' => 'Engenheiro'
            ],
            'estado_civil' => [
                'key' => 'cliente.estado_civil',
                'type' => 'text',
                'label_key' => 'variables.cliente.estado_civil',
                'example' => 'Casado'
            ],
            'cnh_categoria' => [
                'key' => 'cliente.cnh_categoria',
                'type' => 'text',
                'label_key' => 'variables.cliente.cnh_categoria',
                'example' => 'B'
            ],
        ],

        'empresa' => [
            'razao_social' => [
                'key' => 'empresa.razao_social',
                'type' => 'text',
                'label_key' => 'variables.empresa.razao_social',
                'example' => 'Locadora ABC Ltda'
            ],
            'nome_fantasia' => [
                'key' => 'empresa.nome_fantasia',
                'type' => 'text',
                'label_key' => 'variables.empresa.nome_fantasia',
                'example' => 'ABC Locadora'
            ],
            'cnpj' => [
                'key' => 'empresa.cnpj',
                'type' => 'document',
                'label_key' => 'variables.empresa.cnpj',
                'example' => '12.345.678/0001-90'
            ],
            'email' => [
                'key' => 'empresa.email',
                'type' => 'text',
                'label_key' => 'variables.empresa.email',
                'example' => 'contato@locadora.com'
            ],
            'telefone' => [
                'key' => 'empresa.telefone',
                'type' => 'phone',
                'label_key' => 'variables.empresa.telefone',
                'example' => '(11) 3333-4444'
            ],
            'whatsapp' => [
                'key' => 'empresa.whatsapp',
                'type' => 'phone',
                'label_key' => 'variables.empresa.whatsapp',
                'example' => '(11) 99999-8888'
            ],
            'endereco_completo' => [
                'key' => 'empresa.endereco_completo',
                'type' => 'computed',
                'label_key' => 'variables.empresa.endereco_completo',
                'example' => 'Av. Paulista, 1000 - Bela Vista - São Paulo/SP - 01310-100'
            ],
            'site' => [
                'key' => 'empresa.site',
                'type' => 'text',
                'label_key' => 'variables.empresa.site',
                'example' => 'www.locadora.com.br'
            ],
            'endereco' => [
                'key' => 'empresa.endereco',
                'type' => 'text',
                'label_key' => 'variables.empresa.endereco',
                'example' => 'Av. Paulista'
            ],
            'numero' => [
                'key' => 'empresa.numero',
                'type' => 'text',
                'label_key' => 'variables.empresa.numero',
                'example' => '1000'
            ],
            'bairro' => [
                'key' => 'empresa.bairro',
                'type' => 'text',
                'label_key' => 'variables.empresa.bairro',
                'example' => 'Bela Vista'
            ],
            'cidade' => [
                'key' => 'empresa.cidade',
                'type' => 'text',
                'label_key' => 'variables.empresa.cidade',
                'example' => 'São Paulo'
            ],
            'uf' => [
                'key' => 'empresa.uf',
                'type' => 'text',
                'label_key' => 'variables.empresa.uf',
                'example' => 'SP'
            ],
            'cep' => [
                'key' => 'empresa.cep',
                'type' => 'text',
                'label_key' => 'variables.empresa.cep',
                'example' => '01310-100'
            ],
            'pais' => [
                'key' => 'empresa.pais',
                'type' => 'text',
                'label_key' => 'variables.empresa.pais',
                'example' => 'Brasil'
            ],
            'ie' => [
                'key' => 'empresa.ie',
                'type' => 'text',
                'label_key' => 'variables.empresa.ie',
                'example' => '123.456.789.012'
            ],
            'im' => [
                'key' => 'empresa.im',
                'type' => 'text',
                'label_key' => 'variables.empresa.im',
                'example' => '12345678'
            ],
        ],

        'contrato' => [
            // Dados básicos existentes
            'numero' => [
                'key' => 'contrato.numero',
                'type' => 'text',
                'label_key' => 'variables.contrato.numero',
                'example' => 'CTR-2024-0001'
            ],
            'data_inicio' => [
                'key' => 'contrato.data_inicio',
                'type' => 'date',
                'label_key' => 'variables.contrato.data_inicio',
                'example' => '01/01/2024'
            ],
            'data_fim' => [
                'key' => 'contrato.data_fim',
                'type' => 'date',
                'label_key' => 'variables.contrato.data_fim',
                'example' => '31/12/2024'
            ],
            'valor_total' => [
                'key' => 'contrato.valor_total',
                'type' => 'currency',
                'label_key' => 'variables.contrato.valor_total',
                'example' => 'R$ 5.000,00'
            ],
            'valor_diaria' => [
                'key' => 'contrato.valor_diaria',
                'type' => 'currency',
                'label_key' => 'variables.contrato.valor_diaria',
                'example' => 'R$ 150,00'
            ],
            'quantidade_dias' => [
                'key' => 'contrato.quantidade_dias',
                'type' => 'text',
                'label_key' => 'variables.contrato.quantidade_dias',
                'example' => '30'
            ],
            'status' => [
                'key' => 'contrato.status',
                'type' => 'text',
                'label_key' => 'variables.contrato.status',
                'example' => 'Ativo'
            ],

            // Novas variáveis - Dados Básicos
            'hora_inicio' => [
                'key' => 'contrato.hora_inicio',
                'type' => 'text',
                'label_key' => 'variables.contrato.hora_inicio',
                'example' => '08:00'
            ],
            'hora_fim' => [
                'key' => 'contrato.hora_fim',
                'type' => 'text',
                'label_key' => 'variables.contrato.hora_fim',
                'example' => '18:00'
            ],
            'filial_retirada' => [
                'key' => 'contrato.filial_retirada',
                'type' => 'text',
                'label_key' => 'variables.contrato.filial_retirada',
                'example' => 'Filial Centro'
            ],
            'filial_endereco' => [
                'key' => 'contrato.filial_endereco',
                'type' => 'computed',
                'label_key' => 'variables.contrato.filial_endereco',
                'example' => 'Av. Paulista, 1000 - Bela Vista - São Paulo/SP'
            ],
            'observacoes' => [
                'key' => 'contrato.observacoes',
                'type' => 'text',
                'label_key' => 'variables.contrato.observacoes',
                'example' => 'Cliente VIP - prioridade no atendimento'
            ],
            'desconto' => [
                'key' => 'contrato.desconto',
                'type' => 'currency',
                'label_key' => 'variables.contrato.desconto',
                'example' => 'R$ 100,00'
            ],
            'valor_taxas' => [
                'key' => 'contrato.valor_taxas',
                'type' => 'currency',
                'label_key' => 'variables.contrato.valor_taxas',
                'example' => 'R$ 250,00'
            ],
            'forma_pagamento' => [
                'key' => 'contrato.forma_pagamento',
                'type' => 'text',
                'label_key' => 'variables.contrato.forma_pagamento',
                'example' => 'Cartão de Crédito'
            ],
            'comando_parcela' => [
                'key' => 'contrato.comando_parcela',
                'type' => 'computed',
                'label_key' => 'variables.contrato.comando_parcela',
                'example' => 'segundas-feiras'
            ],
            'primeiro_pagamento' => [
                'key' => 'contrato.primeiro_pagamento',
                'type' => 'currency',
                'label_key' => 'variables.contrato.primeiro_pagamento',
                'example' => 'R$ 500,00'
            ],
            'contagem' => [
                'key' => 'contrato.contagem',
                'type' => 'computed',
                'label_key' => 'variables.contrato.contagem',
                'example' => 'dia(s)'
            ],
            'info_plano' => [
                'key' => 'contrato.info_plano',
                'type' => 'text',
                'label_key' => 'variables.contrato.info_plano',
                'example' => 'Km Controlado'
            ],
            'autorenovacao' => [
                'key' => 'contrato.autorenovacao',
                'type' => 'text',
                'label_key' => 'variables.contrato.autorenovacao',
                'example' => 'Automática'
            ],
            'data_renovacao' => [
                'key' => 'contrato.data_renovacao',
                'type' => 'date',
                'label_key' => 'variables.contrato.data_renovacao',
                'example' => '15/02/2024'
            ],
            'km_saida' => [
                'key' => 'contrato.km_saida',
                'type' => 'text',
                'label_key' => 'variables.contrato.km_saida',
                'example' => '45.230'
            ],
            'km_chegada' => [
                'key' => 'contrato.km_chegada',
                'type' => 'text',
                'label_key' => 'variables.contrato.km_chegada',
                'example' => '48.120'
            ],
            'tanque_saida' => [
                'key' => 'contrato.tanque_saida',
                'type' => 'text',
                'label_key' => 'variables.contrato.tanque_saida',
                'example' => 'Cheio'
            ],
            'tanque_chegada' => [
                'key' => 'contrato.tanque_chegada',
                'type' => 'text',
                'label_key' => 'variables.contrato.tanque_chegada',
                'example' => '1/2'
            ],
            'caucao_valor' => [
                'key' => 'contrato.caucao_valor',
                'type' => 'currency',
                'label_key' => 'variables.contrato.caucao_valor',
                'example' => 'R$ 500,00'
            ],
            'deposito_valor' => [
                'key' => 'contrato.deposito_valor',
                'type' => 'currency',
                'label_key' => 'variables.contrato.deposito_valor',
                'example' => 'R$ 500,00'
            ],
            'caucao_status' => [
                'key' => 'contrato.caucao_status',
                'type' => 'text',
                'label_key' => 'variables.contrato.caucao_status',
                'example' => 'Ativa'
            ],
            'caucao_observacoes' => [
                'key' => 'contrato.caucao_observacoes',
                'type' => 'text',
                'label_key' => 'variables.contrato.caucao_observacoes',
                'example' => 'Caução recebida via transferência bancária'
            ],
            'caucao_data_devolucao' => [
                'key' => 'contrato.caucao_data_devolucao',
                'type' => 'date',
                'label_key' => 'variables.contrato.caucao_data_devolucao',
                'example' => '25/01/2024'
            ],
            'caucao_prazo_devolucao' => [
                'key' => 'contrato.caucao_prazo_devolucao',
                'type' => 'text',
                'label_key' => 'variables.contrato.caucao_prazo_devolucao',
                'example' => '5'
            ],
            'caucao_data_prevista_devolucao' => [
                'key' => 'contrato.caucao_data_prevista_devolucao',
                'type' => 'date',
                'label_key' => 'variables.contrato.caucao_data_prevista_devolucao',
                'example' => '25/01/2024'
            ],
            'bloqueio_valor' => [
                'key' => 'contrato.bloqueio_valor',
                'type' => 'currency',
                'label_key' => 'variables.contrato.bloqueio_valor',
                'example' => 'R$ 1.000,00'
            ],
            'bloqueio_status' => [
                'key' => 'contrato.bloqueio_status',
                'type' => 'text',
                'label_key' => 'variables.contrato.bloqueio_status',
                'example' => 'Autorizado'
            ],
            'bloqueio_valor_capturado' => [
                'key' => 'contrato.bloqueio_valor_capturado',
                'type' => 'currency',
                'label_key' => 'variables.contrato.bloqueio_valor_capturado',
                'example' => 'R$ 300,00'
            ],
            'bloqueio_expira_em' => [
                'key' => 'contrato.bloqueio_expira_em',
                'type' => 'date',
                'label_key' => 'variables.contrato.bloqueio_expira_em',
                'example' => '22/01/2024'
            ],

            // Veículos do Contrato
            'veiculos' => [
                'key' => 'contrato.veiculos',
                'type' => 'computed',
                'label_key' => 'variables.contrato.veiculos',
                'example' => "1. Chevrolet Onix 2024 - Prata\n   Placa: ABC-1234\n   Grupo: Hatch Compacto\n   Plano: Km Livre - R\$ 150,00/dia\n\n2. Fiat Argo 2023 - Branco\n   Placa: XYZ-5678\n   Grupo: Hatch Compacto\n   Plano: Km Controlado - R\$ 120,00/dia"
            ],
            'veiculos_tabela' => [
                'key' => 'contrato.veiculos_tabela',
                'type' => 'html',
                'label_key' => 'variables.contrato.veiculos_tabela',
                'example' => '<table style="width:100%; border-collapse:collapse; border:1px solid #ddd;"><thead><tr style="background:#f5f5f5;"><th style="padding:8px; border:1px solid #ddd; text-align:left;">Veículo</th><th style="padding:8px; border:1px solid #ddd; text-align:left;">Placa</th><th style="padding:8px; border:1px solid #ddd; text-align:left;">Plano</th><th style="padding:8px; border:1px solid #ddd; text-align:right;">Valor/Dia</th></tr></thead><tbody><tr><td style="padding:8px; border:1px solid #ddd;">Chevrolet Onix 2024</td><td style="padding:8px; border:1px solid #ddd;">ABC-1234</td><td style="padding:8px; border:1px solid #ddd;">Km Livre</td><td style="padding:8px; border:1px solid #ddd; text-align:right;">R$ 150,00</td></tr></tbody></table>'
            ],
            'veiculos_anexo' => [
                'key' => 'contrato.veiculos_anexo',
                'type' => 'html',
                'label_key' => 'variables.contrato.veiculos_anexo',
                'example' => '<table style="width:100%; border-collapse:collapse; border:1px solid #ddd;"><thead><tr style="background:#f5f5f5;"><th style="padding:6px; border:1px solid #ddd;">Item</th><th style="padding:6px; border:1px solid #ddd;">Veículo</th><th style="padding:6px; border:1px solid #ddd;">Identificação</th><th style="padding:6px; border:1px solid #ddd;">Fornecedor/Investidor</th><th style="padding:6px; border:1px solid #ddd;">Condições</th><th style="padding:6px; border:1px solid #ddd;">Saída</th></tr></thead><tbody><tr><td style="padding:6px; border:1px solid #ddd;">1</td><td style="padding:6px; border:1px solid #ddd;">Chevrolet Onix 2024 - Prata<br>Grupo: Hatch Compacto</td><td style="padding:6px; border:1px solid #ddd;">Placa: ABC-1234<br>RENAVAM: 123456789<br>Chassi: 9BWZZZ377VT004251</td><td style="padding:6px; border:1px solid #ddd;">João Investidor<br>CPF/CNPJ: 123.456.789-00</td><td style="padding:6px; border:1px solid #ddd;">Km Livre<br>Valor: R$ 150,00/dia<br>Seguros: R$ 20,00/dia</td><td style="padding:6px; border:1px solid #ddd;">Data: 01/01/2026<br>Km: 10.000<br>Comb./Carga: Cheio</td></tr></tbody></table>'
            ],

            // Taxas e Serviços
            'taxas' => [
                'key' => 'contrato.taxas',
                'type' => 'computed',
                'label_key' => 'variables.contrato.taxas',
                'example' => "1. Taxa de Limpeza\n   Quantidade: 1 | Valor: R\$ 50,00 | Total: R\$ 50,00\n\n2. Condutor Adicional\n   Quantidade: 2 | Valor: R\$ 30,00/dia | Total: R\$ 420,00"
            ],
            'taxas_tabela' => [
                'key' => 'contrato.taxas_tabela',
                'type' => 'html',
                'label_key' => 'variables.contrato.taxas_tabela',
                'example' => '<table style="width:100%; border-collapse:collapse; border:1px solid #ddd;"><thead><tr style="background:#f5f5f5;"><th style="padding:8px; border:1px solid #ddd; text-align:left;">Descrição</th><th style="padding:8px; border:1px solid #ddd; text-align:center;">Qtd</th><th style="padding:8px; border:1px solid #ddd; text-align:right;">Valor Unit.</th><th style="padding:8px; border:1px solid #ddd; text-align:right;">Total</th></tr></thead><tbody><tr><td style="padding:8px; border:1px solid #ddd;">Taxa de Limpeza</td><td style="padding:8px; border:1px solid #ddd; text-align:center;">1</td><td style="padding:8px; border:1px solid #ddd; text-align:right;">R$ 50,00</td><td style="padding:8px; border:1px solid #ddd; text-align:right;">R$ 50,00</td></tr></tbody></table>'
            ],

            // Financeiro - Parcelas
            'parcelas' => [
                'key' => 'contrato.parcelas',
                'type' => 'computed',
                'label_key' => 'variables.contrato.parcelas',
                'example' => "Parcela 1/12 - Vencimento: 15/01/2024\n   Valor: R\$ 500,00 | Status: PAGO em 14/01/2024\n\nParcela 2/12 - Vencimento: 15/02/2024\n   Valor: R\$ 500,00 | Status: PENDENTE"
            ],
            'parcelas_tabela' => [
                'key' => 'contrato.parcelas_tabela',
                'type' => 'html',
                'label_key' => 'variables.contrato.parcelas_tabela',
                'example' => '<table style="width:100%; border-collapse:collapse; border:1px solid #ddd;"><thead><tr style="background:#f5f5f5;"><th style="padding:8px; border:1px solid #ddd; text-align:center;">Parcela</th><th style="padding:8px; border:1px solid #ddd; text-align:center;">Vencimento</th><th style="padding:8px; border:1px solid #ddd; text-align:right;">Valor</th></tr></thead><tbody><tr><td style="padding:8px; border:1px solid #ddd; text-align:center;">1/12</td><td style="padding:8px; border:1px solid #ddd; text-align:center;">15/01/2024</td><td style="padding:8px; border:1px solid #ddd; text-align:right;">R$ 500,00</td></tr></tbody></table>'
            ],
            'parcelas_tabela_status' => [
                'key' => 'contrato.parcelas_tabela_status',
                'type' => 'html',
                'label_key' => 'variables.contrato.parcelas_tabela_status',
                'example' => '<table style="width:100%; border-collapse:collapse; border:1px solid #ddd;"><thead><tr style="background:#f5f5f5;"><th style="padding:8px; border:1px solid #ddd; text-align:center;">Parcela</th><th style="padding:8px; border:1px solid #ddd; text-align:center;">Vencimento</th><th style="padding:8px; border:1px solid #ddd; text-align:right;">Valor</th><th style="padding:8px; border:1px solid #ddd; text-align:center;">Status</th></tr></thead><tbody><tr><td style="padding:8px; border:1px solid #ddd; text-align:center;">1/12</td><td style="padding:8px; border:1px solid #ddd; text-align:center;">15/01/2024</td><td style="padding:8px; border:1px solid #ddd; text-align:right;">R$ 500,00</td><td style="padding:8px; border:1px solid #ddd; text-align:center;">Pago</td></tr></tbody></table>'
            ],
            'valor.parcela' => [
                'key' => 'contrato.valor.parcela',
                'type' => 'computed',
                'label_key' => 'variables.contrato.valor_parcela',
                'example' => 'R$ 500,00'
            ],
            'total_parcelas' => [
                'key' => 'contrato.total_parcelas',
                'type' => 'text',
                'label_key' => 'variables.contrato.total_parcelas',
                'example' => '12'
            ],
            'parcelas_pagas' => [
                'key' => 'contrato.parcelas_pagas',
                'type' => 'text',
                'label_key' => 'variables.contrato.parcelas_pagas',
                'example' => '3'
            ],
            'parcelas_pendentes' => [
                'key' => 'contrato.parcelas_pendentes',
                'type' => 'text',
                'label_key' => 'variables.contrato.parcelas_pendentes',
                'example' => '9'
            ],
            'valor_pago' => [
                'key' => 'contrato.valor_pago',
                'type' => 'currency',
                'label_key' => 'variables.contrato.valor_pago',
                'example' => 'R$ 1.500,00'
            ],
            'valor_pendente' => [
                'key' => 'contrato.valor_pendente',
                'type' => 'currency',
                'label_key' => 'variables.contrato.valor_pendente',
                'example' => 'R$ 4.500,00'
            ],
            'valor_atrasado' => [
                'key' => 'contrato.valor_atrasado',
                'type' => 'currency',
                'label_key' => 'variables.contrato.valor_atrasado',
                'example' => 'R$ 500,00'
            ],

            // Condutores Adicionais
            'condutores' => [
                'key' => 'contrato.condutores',
                'type' => 'computed',
                'label_key' => 'variables.contrato.condutores',
                'example' => "1. José da Silva\n   CPF: 123.456.789-00\n   CNH: 12345678900 (Categoria B)\n   Validade: 15/12/2025\n\n2. Maria Oliveira\n   CPF: 987.654.321-00\n   CNH: 98765432100 (Categoria AB)\n   Validade: 20/06/2026"
            ],

            // Fiadores
            'fiadores' => [
                'key' => 'contrato.fiadores',
                'type' => 'computed',
                'label_key' => 'variables.contrato.fiadores',
                'example' => "1. Pedro Almeida Santos\n   CPF/CNPJ: 111.222.333-44\n\n2. Ana Paula Costa\n   CPF/CNPJ: 555.666.777-88"
            ],
            'fiadores_assinaturas' => [
                'key' => 'contrato.fiadores_assinaturas',
                'type' => 'html',
                'label_key' => 'variables.contrato.fiadores_assinaturas',
                'example' => '<div style="margin-bottom:60px;"><div style="border-bottom:1px solid #000; width:250px; margin-bottom:5px;"></div><div><strong>Fiador 1:</strong> Pedro Almeida Santos</div><div>CPF: 111.222.333-44</div></div><div style="margin-bottom:60px;"><div style="border-bottom:1px solid #000; width:250px; margin-bottom:5px;"></div><div><strong>Fiador 2:</strong> Ana Paula Costa</div><div>CPF: 555.666.777-88</div></div>'
            ],

            // Avalistas
            'avalistas' => [
                'key' => 'contrato.avalistas',
                'type' => 'computed',
                'label_key' => 'variables.contrato.avalistas',
                'example' => "1. Carlos Eduardo Lima\n   CPF/CNPJ: 222.333.444-55\n\n2. Fernanda Ribeiro\n   CPF/CNPJ: 666.777.888-99"
            ],
            'avalistas_assinaturas' => [
                'key' => 'contrato.avalistas_assinaturas',
                'type' => 'html',
                'label_key' => 'variables.contrato.avalistas_assinaturas',
                'example' => '<div style="margin-bottom:60px;"><div style="border-bottom:1px solid #000; width:250px; margin-bottom:5px;"></div><div><strong>Avalista 1:</strong> Carlos Eduardo Lima</div><div>CPF: 222.333.444-55</div></div><div style="margin-bottom:60px;"><div style="border-bottom:1px solid #000; width:250px; margin-bottom:5px;"></div><div><strong>Avalista 2:</strong> Fernanda Ribeiro</div><div>CPF: 666.777.888-99</div></div>'
            ],

            // Testemunhas
            'testemunhas' => [
                'key' => 'contrato.testemunhas',
                'type' => 'computed',
                'label_key' => 'variables.contrato.testemunhas',
                'example' => "1. Roberto Mendes\n   CPF/CNPJ: 333.444.555-66\n\n2. Juliana Ferreira\n   CPF/CNPJ: 777.888.999-00"
            ],
            'testemunhas_assinaturas' => [
                'key' => 'contrato.testemunhas_assinaturas',
                'type' => 'html',
                'label_key' => 'variables.contrato.testemunhas_assinaturas',
                'example' => '<div style="margin-bottom:60px;"><div style="border-bottom:1px solid #000; width:250px; margin-bottom:5px;"></div><div><strong>Testemunha 1:</strong> Roberto Mendes</div><div>CPF: 333.444.555-66</div></div><div style="margin-bottom:60px;"><div style="border-bottom:1px solid #000; width:250px; margin-bottom:5px;"></div><div><strong>Testemunha 2:</strong> Juliana Ferreira</div><div>CPF: 777.888.999-00</div></div>'
            ],

            // Assinaturas em Colunas (lado a lado)
            'fiadores_assinaturas_colunas' => [
                'key' => 'contrato.fiadores_assinaturas_colunas',
                'type' => 'html',
                'label_key' => 'variables.contrato.fiadores_assinaturas_colunas',
                'example' => '<table style="width:100%; border-collapse:collapse;"><tr><td style="width:50%; padding:20px; vertical-align:top;"><div style="border-bottom:1px solid #000; width:200px; margin-bottom:5px;"></div><div><strong>Fiador 1:</strong> Pedro Almeida Santos</div><div>CPF: 111.222.333-44</div></td><td style="width:50%; padding:20px; vertical-align:top;"><div style="border-bottom:1px solid #000; width:200px; margin-bottom:5px;"></div><div><strong>Fiador 2:</strong> Ana Paula Costa</div><div>CPF: 555.666.777-88</div></td></tr></table>'
            ],
            'avalistas_assinaturas_colunas' => [
                'key' => 'contrato.avalistas_assinaturas_colunas',
                'type' => 'html',
                'label_key' => 'variables.contrato.avalistas_assinaturas_colunas',
                'example' => '<table style="width:100%; border-collapse:collapse;"><tr><td style="width:50%; padding:20px; vertical-align:top;"><div style="border-bottom:1px solid #000; width:200px; margin-bottom:5px;"></div><div><strong>Avalista 1:</strong> Carlos Eduardo Lima</div><div>CPF: 222.333.444-55</div></td><td style="width:50%; padding:20px; vertical-align:top;"><div style="border-bottom:1px solid #000; width:200px; margin-bottom:5px;"></div><div><strong>Avalista 2:</strong> Fernanda Ribeiro</div><div>CPF: 666.777.888-99</div></td></tr></table>'
            ],
            'testemunhas_assinaturas_colunas' => [
                'key' => 'contrato.testemunhas_assinaturas_colunas',
                'type' => 'html',
                'label_key' => 'variables.contrato.testemunhas_assinaturas_colunas',
                'example' => '<table style="width:100%; border-collapse:collapse;"><tr><td style="width:50%; padding:20px; vertical-align:top;"><div style="border-bottom:1px solid #000; width:200px; margin-bottom:5px;"></div><div><strong>Testemunha 1:</strong> Roberto Mendes</div><div>CPF: 333.444.555-66</div></td><td style="width:50%; padding:20px; vertical-align:top;"><div style="border-bottom:1px solid #000; width:200px; margin-bottom:5px;"></div><div><strong>Testemunha 2:</strong> Juliana Ferreira</div><div>CPF: 777.888.999-00</div></td></tr></table>'
            ],

            // Assinatura do Cliente
            'assinatura_cliente' => [
                'key' => 'contrato.assinatura_cliente',
                'type' => 'html',
                'label_key' => 'variables.contrato.assinatura_cliente',
                'example' => '<div style="margin-bottom:60px;"><div style="border-bottom:1px solid #000; width:250px; margin-bottom:5px;"></div><div><strong>LOCATÁRIO:</strong> João da Silva</div><div>CPF: 123.456.789-00</div></div>'
            ],
        ],

        'locacao' => [
            'numero' => [
                'key' => 'locacao.numero',
                'type' => 'text',
                'label_key' => 'variables.locacao.numero',
                'example' => 'LOC-2024-0001'
            ],
            'data_retirada' => [
                'key' => 'locacao.data_retirada',
                'type' => 'date',
                'label_key' => 'variables.locacao.data_retirada',
                'example' => '15/01/2024'
            ],
            'hora_retirada' => [
                'key' => 'locacao.hora_retirada',
                'type' => 'text',
                'label_key' => 'variables.locacao.hora_retirada',
                'example' => '09:00'
            ],
            'data_devolucao' => [
                'key' => 'locacao.data_devolucao',
                'type' => 'date',
                'label_key' => 'variables.locacao.data_devolucao',
                'example' => '20/01/2024'
            ],
            'hora_devolucao' => [
                'key' => 'locacao.hora_devolucao',
                'type' => 'text',
                'label_key' => 'variables.locacao.hora_devolucao',
                'example' => '18:00'
            ],
            'local_retirada' => [
                'key' => 'locacao.local_retirada',
                'type' => 'text',
                'label_key' => 'variables.locacao.local_retirada',
                'example' => 'Filial Centro'
            ],
            'local_devolucao' => [
                'key' => 'locacao.local_devolucao',
                'type' => 'text',
                'label_key' => 'variables.locacao.local_devolucao',
                'example' => 'Filial Aeroporto'
            ],
            'valor_total' => [
                'key' => 'locacao.valor_total',
                'type' => 'currency',
                'label_key' => 'variables.locacao.valor_total',
                'example' => 'R$ 750,00'
            ],
            'valor_diaria' => [
                'key' => 'locacao.valor_diaria',
                'type' => 'currency',
                'label_key' => 'variables.locacao.valor_diaria',
                'example' => 'R$ 150,00'
            ],
            'quantidade_dias' => [
                'key' => 'locacao.quantidade_dias',
                'type' => 'text',
                'label_key' => 'variables.locacao.quantidade_dias',
                'example' => '5'
            ],
            'status' => [
                'key' => 'locacao.status',
                'type' => 'text',
                'label_key' => 'variables.locacao.status',
                'example' => 'Em andamento'
            ],
            'cobertura' => [
                'key' => 'locacao.cobertura',
                'type' => 'text',
                'label_key' => 'variables.locacao.cobertura',
                'example' => 'Cobertura Total'
            ],
            'bloqueio_data_devolucao' => [
                'key' => 'locacao.bloqueio_data_devolucao',
                'type' => 'date',
                'label_key' => 'variables.locacao.bloqueio_data_devolucao',
                'example' => '25/01/2024'
            ],
            'caucao_data_devolucao' => [
                'key' => 'locacao.caucao_data_devolucao',
                'type' => 'date',
                'label_key' => 'variables.locacao.caucao_data_devolucao',
                'example' => '25/01/2024'
            ],
            'caucao_prazo_devolucao' => [
                'key' => 'locacao.caucao_prazo_devolucao',
                'type' => 'text',
                'label_key' => 'variables.locacao.caucao_prazo_devolucao',
                'example' => '5'
            ],
            'caucao_data_prevista_devolucao' => [
                'key' => 'locacao.caucao_data_prevista_devolucao',
                'type' => 'date',
                'label_key' => 'variables.locacao.caucao_data_prevista_devolucao',
                'example' => '25/01/2024'
            ],
            'caucao_observacoes' => [
                'key' => 'locacao.caucao_observacoes',
                'type' => 'text',
                'label_key' => 'variables.locacao.caucao_observacoes',
                'example' => 'Caução recebida via transferência bancária'
            ],
            'fatura_a_pagar' => [
                'key' => 'locacao.fatura_a_pagar',
                'type' => 'currency',
                'label_key' => 'variables.locacao.fatura_a_pagar',
                'example' => 'R$ 500,00'
            ],
            'grupo' => [
                'key' => 'locacao.grupo',
                'type' => 'text',
                'label_key' => 'variables.locacao.grupo',
                'example' => 'Hatch Compacto'
            ],
            'grupo_descricao' => [
                'key' => 'locacao.grupo_descricao',
                'type' => 'text',
                'label_key' => 'variables.locacao.grupo_descricao',
                'example' => 'Veículos econômicos e compactos'
            ],
            'tanque_saida' => [
                'key' => 'locacao.tanque_saida',
                'type' => 'text',
                'label_key' => 'variables.locacao.tanque_saida',
                'example' => 'Cheio'
            ],
            'tanque_chegada' => [
                'key' => 'locacao.tanque_chegada',
                'type' => 'text',
                'label_key' => 'variables.locacao.tanque_chegada',
                'example' => '3/4'
            ],
            'km_saida' => [
                'key' => 'locacao.km_saida',
                'type' => 'text',
                'label_key' => 'variables.locacao.km_saida',
                'example' => '45.230'
            ],
            'km_chegada' => [
                'key' => 'locacao.km_chegada',
                'type' => 'text',
                'label_key' => 'variables.locacao.km_chegada',
                'example' => '45.780'
            ],
            'total_fatura' => [
                'key' => 'locacao.total_fatura',
                'type' => 'currency',
                'label_key' => 'variables.locacao.total_fatura',
                'example' => 'R$ 850,00'
            ],
            'bloqueio_valor' => [
                'key' => 'locacao.bloqueio_valor',
                'type' => 'currency',
                'label_key' => 'variables.locacao.bloqueio_valor',
                'example' => 'R$ 1.000,00'
            ],
            'deposito_valor' => [
                'key' => 'locacao.deposito_valor',
                'type' => 'currency',
                'label_key' => 'variables.locacao.deposito_valor',
                'example' => 'R$ 500,00'
            ],
            'caucao_valor' => [
                'key' => 'locacao.caucao_valor',
                'type' => 'currency',
                'label_key' => 'variables.locacao.caucao_valor',
                'example' => 'R$ 500,00'
            ],
            'cobertura_terceiros' => [
                'key' => 'locacao.cobertura_terceiros',
                'type' => 'text',
                'label_key' => 'variables.locacao.cobertura_terceiros',
                'example' => 'R$ 50.000,00'
            ],
            'fatura_paga' => [
                'key' => 'locacao.fatura_paga',
                'type' => 'currency',
                'label_key' => 'variables.locacao.fatura_paga',
                'example' => 'R$ 350,00'
            ],
            'forma_pagamento' => [
                'key' => 'locacao.forma_pagamento',
                'type' => 'text',
                'label_key' => 'variables.locacao.forma_pagamento',
                'example' => 'Cartão de Crédito'
            ],
            'plano' => [
                'key' => 'locacao.plano',
                'type' => 'text',
                'label_key' => 'variables.locacao.plano',
                'example' => 'Diária'
            ],
            'info_plano' => [
                'key' => 'locacao.info_plano',
                'type' => 'text',
                'label_key' => 'variables.locacao.info_plano',
                'example' => 'Plano com KM livre'
            ],
            'condutores_adicionais' => [
                'key' => 'locacao.condutores_adicionais',
                'type' => 'computed',
                'label_key' => 'variables.locacao.condutores_adicionais',
                'example' => 'José Silva, Ana Costa'
            ],
            'fiadores' => [
                'key' => 'locacao.fiadores',
                'type' => 'computed',
                'label_key' => 'variables.locacao.fiadores',
                'example' => 'Pedro Almeida (CPF: 987.654.321-00)'
            ],
        ],

        'veiculo' => [
            'placa' => [
                'key' => 'veiculo.placa',
                'type' => 'text',
                'label_key' => 'variables.veiculo.placa',
                'example' => 'ABC-1234'
            ],
            'modelo' => [
                'key' => 'veiculo.modelo',
                'type' => 'text',
                'label_key' => 'variables.veiculo.modelo',
                'example' => 'Onix'
            ],
            'marca' => [
                'key' => 'veiculo.marca',
                'type' => 'text',
                'label_key' => 'variables.veiculo.marca',
                'example' => 'Chevrolet'
            ],
            'ano' => [
                'key' => 'veiculo.ano',
                'type' => 'text',
                'label_key' => 'variables.veiculo.ano',
                'example' => '2024'
            ],
            'cor' => [
                'key' => 'veiculo.cor',
                'type' => 'text',
                'label_key' => 'variables.veiculo.cor',
                'example' => 'Prata'
            ],
            'renavam' => [
                'key' => 'veiculo.renavam',
                'type' => 'text',
                'label_key' => 'variables.veiculo.renavam',
                'example' => '12345678901'
            ],
            'descricao_completa' => [
                'key' => 'veiculo.descricao_completa',
                'type' => 'computed',
                'label_key' => 'variables.veiculo.descricao_completa',
                'example' => 'Chevrolet Onix 2024 - Prata - ABC-1234'
            ],
            'categoria' => [
                'key' => 'veiculo.categoria',
                'type' => 'text',
                'label_key' => 'variables.veiculo.categoria',
                'example' => 'Hatch Compacto'
            ],
            'chassi' => [
                'key' => 'veiculo.chassi',
                'type' => 'text',
                'label_key' => 'variables.veiculo.chassi',
                'example' => '9BWZZZ377VT004251'
            ],
            'combustivel_tipo' => [
                'key' => 'veiculo.combustivel_tipo',
                'type' => 'text',
                'label_key' => 'variables.veiculo.combustivel_tipo',
                'example' => 'Flex'
            ],
            'valor_compra' => [
                'key' => 'veiculo.valor_compra',
                'type' => 'currency',
                'label_key' => 'variables.veiculo.valor_compra',
                'example' => 'R$ 65.000,00'
            ],
            'valor_venda' => [
                'key' => 'veiculo.valor_venda',
                'type' => 'currency',
                'label_key' => 'variables.veiculo.valor_venda',
                'example' => 'R$ 58.000,00'
            ],
        ],

        'fatura' => [
            'numero' => [
                'key' => 'fatura.numero',
                'type' => 'text',
                'label_key' => 'variables.fatura.numero',
                'example' => 'FAT-2024-0001'
            ],
            'valor' => [
                'key' => 'fatura.valor',
                'type' => 'currency',
                'label_key' => 'variables.fatura.valor',
                'example' => 'R$ 750,00'
            ],
            'data_vencimento' => [
                'key' => 'fatura.data_vencimento',
                'type' => 'date',
                'label_key' => 'variables.fatura.data_vencimento',
                'example' => '25/01/2024'
            ],
            'data_pagamento' => [
                'key' => 'fatura.data_pagamento',
                'type' => 'date',
                'label_key' => 'variables.fatura.data_pagamento',
                'example' => '24/01/2024'
            ],
            'status' => [
                'key' => 'fatura.status',
                'type' => 'text',
                'label_key' => 'variables.fatura.status',
                'example' => 'Pendente'
            ],
            'link_boleto' => [
                'key' => 'fatura.link_boleto',
                'type' => 'text',
                'label_key' => 'variables.fatura.link_boleto',
                'example' => 'https://pagamento.locadora.com/boleto/123'
            ],
            'codigo_pix' => [
                'key' => 'fatura.codigo_pix',
                'type' => 'text',
                'label_key' => 'variables.fatura.codigo_pix',
                'example' => '00020126580014br.gov.bcb.pix...'
            ],
            'dias_atraso' => [
                'key' => 'fatura.dias_atraso',
                'type' => 'computed',
                'label_key' => 'variables.fatura.dias_atraso',
                'example' => '5'
            ],
            'parcela' => [
                'key' => 'fatura.parcela',
                'type' => 'text',
                'label_key' => 'variables.fatura.parcela',
                'example' => '2'
            ],
            'total_parcelas' => [
                'key' => 'fatura.total_parcelas',
                'type' => 'text',
                'label_key' => 'variables.fatura.total_parcelas',
                'example' => '12'
            ],
            'parcela_descricao' => [
                'key' => 'fatura.parcela_descricao',
                'type' => 'computed',
                'label_key' => 'variables.fatura.parcela_descricao',
                'example' => 'Parcela 2 de 12'
            ],
        ],

        'outros' => [
            'data_atual' => [
                'key' => 'outros.data_atual',
                'type' => 'computed',
                'label_key' => 'variables.outros.data_atual',
                'example' => '15/01/2024'
            ],
            'hora_atual' => [
                'key' => 'outros.hora_atual',
                'type' => 'computed',
                'label_key' => 'variables.outros.hora_atual',
                'example' => '14:30'
            ],
            'link_portal_cliente' => [
                'key' => 'outros.link_portal_cliente',
                'type' => 'text',
                'label_key' => 'variables.outros.link_portal_cliente',
                'example' => 'https://portal.locadora.com/cliente'
            ],
            'link_assinatura' => [
                'key' => 'outros.link_assinatura',
                'type' => 'text',
                'label_key' => 'variables.outros.link_assinatura',
                'example' => 'https://locadora.7carros.com/assinar/C14J222U25'
            ],
            'ano_atual' => [
                'key' => 'outros.ano_atual',
                'type' => 'computed',
                'label_key' => 'variables.outros.ano_atual',
                'example' => '2024'
            ],
            'data_atual_extenso' => [
                'key' => 'outros.data_atual_extenso',
                'type' => 'computed',
                'label_key' => 'variables.outros.data_atual_extenso',
                'example' => '15 de janeiro de 2024'
            ],
            'contagem' => [
                'key' => 'outros.contagem',
                'type' => 'text',
                'label_key' => 'variables.outros.contagem',
                'example' => '1'
            ],
            'reset_url' => [
                'key' => 'outros.reset_url',
                'type' => 'text',
                'label_key' => 'variables.outros.reset_url',
                'example' => 'https://locadora.7carros.com/public/redefinir-senha?token=abc123'
            ],
            'reset_expira_em' => [
                'key' => 'outros.reset_expira_em',
                'type' => 'text',
                'label_key' => 'variables.outros.reset_expira_em',
                'example' => '60 minutos'
            ],
            'nova_senha' => [
                'key' => 'outros.nova_senha',
                'type' => 'computed',
                'label_key' => 'variables.outros.nova_senha',
                'example' => 'a1b2c3d4e5'
            ],
        ],

        'fornecedor' => [
            'nome' => [
                'key' => 'fornecedor.nome',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.nome',
                'example' => 'Auto Peças Silva Ltda'
            ],
            'nome_fantasia' => [
                'key' => 'fornecedor.nome_fantasia',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.nome_fantasia',
                'example' => 'Auto Peças Silva'
            ],
            'cpf_cnpj' => [
                'key' => 'fornecedor.cpf_cnpj',
                'type' => 'document',
                'label_key' => 'variables.fornecedor.cpf_cnpj',
                'example' => '12.345.678/0001-90'
            ],
            'rg_ie' => [
                'key' => 'fornecedor.rg_ie',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.rg_ie',
                'example' => '123.456.789.012'
            ],
            'endereco' => [
                'key' => 'fornecedor.endereco',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.endereco',
                'example' => 'Rua das Indústrias'
            ],
            'numero' => [
                'key' => 'fornecedor.numero',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.numero',
                'example' => '500'
            ],
            'bairro' => [
                'key' => 'fornecedor.bairro',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.bairro',
                'example' => 'Distrito Industrial'
            ],
            'cidade' => [
                'key' => 'fornecedor.cidade',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.cidade',
                'example' => 'São Paulo'
            ],
            'estado' => [
                'key' => 'fornecedor.estado',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.estado',
                'example' => 'SP'
            ],
            'pais' => [
                'key' => 'fornecedor.pais',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.pais',
                'example' => 'Brasil'
            ],
            'email' => [
                'key' => 'fornecedor.email',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.email',
                'example' => 'contato@autopecas.com'
            ],
            'observacoes' => [
                'key' => 'fornecedor.observacoes',
                'type' => 'text',
                'label_key' => 'variables.fornecedor.observacoes',
                'example' => 'Fornecedor de peças para motor'
            ],
        ],

        'multa' => [
            'local' => [
                'key' => 'multa.local',
                'type' => 'text',
                'label_key' => 'variables.multa.local',
                'example' => 'Av. Brasil, km 45'
            ],
            'cidade' => [
                'key' => 'multa.cidade',
                'type' => 'text',
                'label_key' => 'variables.multa.cidade',
                'example' => 'Rio de Janeiro'
            ],
            'estado' => [
                'key' => 'multa.estado',
                'type' => 'text',
                'label_key' => 'variables.multa.estado',
                'example' => 'RJ'
            ],
            'data_hora' => [
                'key' => 'multa.data_hora',
                'type' => 'date',
                'label_key' => 'variables.multa.data_hora',
                'example' => '15/01/2024 14:30'
            ],
            'data_vencimento' => [
                'key' => 'multa.data_vencimento',
                'type' => 'date',
                'label_key' => 'variables.multa.data_vencimento',
                'example' => '15/02/2024'
            ],
            'valor' => [
                'key' => 'multa.valor',
                'type' => 'currency',
                'label_key' => 'variables.multa.valor',
                'example' => 'R$ 293,47'
            ],
            'pago' => [
                'key' => 'multa.pago',
                'type' => 'text',
                'label_key' => 'variables.multa.pago',
                'example' => 'Não'
            ],
            'descricao' => [
                'key' => 'multa.descricao',
                'type' => 'text',
                'label_key' => 'variables.multa.descricao',
                'example' => 'Excesso de velocidade'
            ],
            'orgao_autuador' => [
                'key' => 'multa.orgao_autuador',
                'type' => 'text',
                'label_key' => 'variables.multa.orgao_autuador',
                'example' => 'DETRAN-RJ'
            ],
            'numero_infracao' => [
                'key' => 'multa.numero_infracao',
                'type' => 'text',
                'label_key' => 'variables.multa.numero_infracao',
                'example' => 'A123456789'
            ],
        ],

        'promissoria' => [
            'codigo' => [
                'key' => 'promissoria.codigo',
                'type' => 'text',
                'label_key' => 'variables.promissoria.codigo',
                'example' => 'PRO1010513'
            ],
            'valor_total' => [
                'key' => 'promissoria.valor_total',
                'type' => 'currency',
                'label_key' => 'variables.promissoria.valor_total',
                'example' => 'R$ 5.000,00'
            ],
            'valor_extenso' => [
                'key' => 'promissoria.valor_extenso',
                'type' => 'text',
                'label_key' => 'variables.promissoria.valor_extenso',
                'example' => 'cinco mil reais'
            ],
            'qtd_parcelas' => [
                'key' => 'promissoria.qtd_parcelas',
                'type' => 'text',
                'label_key' => 'variables.promissoria.qtd_parcelas',
                'example' => '12'
            ],
            'qtd_pagas' => [
                'key' => 'promissoria.qtd_pagas',
                'type' => 'text',
                'label_key' => 'variables.promissoria.qtd_pagas',
                'example' => '3'
            ],
            'codigo_contrato' => [
                'key' => 'promissoria.codigo_contrato',
                'type' => 'text',
                'label_key' => 'variables.promissoria.codigo_contrato',
                'example' => 'CTR-2024-0001'
            ],
            'status' => [
                'key' => 'promissoria.status',
                'type' => 'text',
                'label_key' => 'variables.promissoria.status',
                'example' => 'PENDENTE'
            ],
        ],

        'parcela' => [
            'numero' => [
                'key' => 'parcela.numero',
                'type' => 'text',
                'label_key' => 'variables.parcela.numero',
                'example' => '1'
            ],
            'total' => [
                'key' => 'parcela.total',
                'type' => 'text',
                'label_key' => 'variables.parcela.total',
                'example' => '12'
            ],
            'valor' => [
                'key' => 'parcela.valor',
                'type' => 'currency',
                'label_key' => 'variables.parcela.valor',
                'example' => 'R$ 416,67'
            ],
            'valor_extenso' => [
                'key' => 'parcela.valor_extenso',
                'type' => 'text',
                'label_key' => 'variables.parcela.valor_extenso',
                'example' => 'quatrocentos e dezesseis reais e sessenta e sete centavos'
            ],
            'data_vencimento' => [
                'key' => 'parcela.data_vencimento',
                'type' => 'date',
                'label_key' => 'variables.parcela.data_vencimento',
                'example' => '15/02/2024'
            ],
            'data_pagamento' => [
                'key' => 'parcela.data_pagamento',
                'type' => 'date',
                'label_key' => 'variables.parcela.data_pagamento',
                'example' => '14/02/2024'
            ],
            'status' => [
                'key' => 'parcela.status',
                'type' => 'text',
                'label_key' => 'variables.parcela.status',
                'example' => 'PENDENTE'
            ],
        ],

        'funcionario' => [
            'nome' => [
                'key' => 'funcionario.nome',
                'type' => 'text',
                'label_key' => 'variables.funcionario.nome',
                'example' => 'Maria Santos'
            ],
            'email' => [
                'key' => 'funcionario.email',
                'type' => 'text',
                'label_key' => 'variables.funcionario.email',
                'example' => 'maria@locadora.com'
            ],
            'telefone' => [
                'key' => 'funcionario.telefone',
                'type' => 'phone',
                'label_key' => 'variables.funcionario.telefone',
                'example' => '(11) 99999-8888'
            ],
            'cargo' => [
                'key' => 'funcionario.cargo',
                'type' => 'text',
                'label_key' => 'variables.funcionario.cargo',
                'example' => 'Gerente'
            ],
        ],
    ];

    /**
     * Mapeamento de variáveis legadas para novo formato
     *
     * Variáveis encontradas na tabela documentos usam prefixos:
     * - c = cliente
     * - e = empresa
     * - l = locação/locadora
     * - v = veículo
     * - f = fatura/funcionário
     * - o = outros
     */
    private const LEGACY_MAPPING = [
        // Cliente
        '$cRSocial' => 'cliente.nome',
        '$cCPFCNPJ' => 'cliente.cpf_cnpj',
        '$cEmail' => 'cliente.email',
        '$cTelefone' => 'cliente.telefone',
        '$cCelular' => 'cliente.celular',
        '$cEndereco' => 'cliente.endereco_completo',
        '$cCNH' => 'cliente.cnh_numero',
        '$cCNHValidade' => 'cliente.cnh_validade',
        '$cCEP' => 'cliente.cep',
        '$cNFantasia' => 'cliente.nome_fantasia',
        '$cRGIE' => 'cliente.rg_ie',
        '$cRua' => 'cliente.endereco',
        '$cNumero' => 'cliente.numero',
        '$cComple' => 'cliente.complemento',
        '$cBairro' => 'cliente.bairro',
        '$cCidade' => 'cliente.cidade',
        '$cUF' => 'cliente.uf',
        '$cNascimento' => 'cliente.data_nascimento',
        '$cPais' => 'cliente.pais',
        '$cProfissao' => 'cliente.profissao',
        '$cEstadoCivil' => 'cliente.estado_civil',
        '$cCNHCategoria' => 'cliente.cnh_categoria',

        // Empresa
        '$eNFantasia' => 'empresa.nome_fantasia',
        '$eRazaoSocial' => 'empresa.razao_social',
        '$eCNPJ' => 'empresa.cnpj',
        '$eEmail' => 'empresa.email',
        '$eTelefone' => 'empresa.telefone',
        '$eEndereco' => 'empresa.endereco_completo',
        '$eRua' => 'empresa.endereco',
        '$eNumero' => 'empresa.numero',
        '$eBairro' => 'empresa.bairro',
        '$eCidade' => 'empresa.cidade',
        '$eUF' => 'empresa.uf',
        '$eCEP' => 'empresa.cep',
        '$ePais' => 'empresa.pais',
        '$eIE' => 'empresa.ie',
        '$eIM' => 'empresa.im',
        // Mapeamento alternativo com prefixo 'l' (legado de "locadora")
        '$lRazaoSocial' => 'empresa.razao_social',
        '$lFantasia' => 'empresa.nome_fantasia',
        '$lCNPJ' => 'empresa.cnpj',
        '$lEmail' => 'empresa.email',
        '$lTelefone' => 'empresa.telefone',
        '$lEndereco' => 'empresa.endereco_completo',

        // Locação
        '$lNumero' => 'locacao.numero',
        '$lDataRetirada' => 'locacao.data_retirada',
        '$lDataInicio' => 'locacao.data_retirada',
        '$lHoraRetirada' => 'locacao.hora_retirada',
        '$lDataDevolucao' => 'locacao.data_devolucao',
        '$lDataChegada' => 'locacao.data_devolucao',
        '$lHoraDevolucao' => 'locacao.hora_devolucao',
        '$lLocalRetirada' => 'locacao.local_retirada',
        '$lLocalDevolucao' => 'locacao.local_devolucao',
        '$lTotalPagar' => 'locacao.valor_total',
        '$lDiaria' => 'locacao.valor_diaria',
        '$lDias' => 'locacao.quantidade_dias',
        '$lCoberturaVeiculo' => 'locacao.cobertura',
        '$lBloqueioDataDevolucao' => 'locacao.bloqueio_data_devolucao',
        '$lCaucaoDataDevolucao' => 'locacao.caucao_data_devolucao',
        '$lCaucaoPrazoDevolucao' => 'locacao.caucao_prazo_devolucao',
        '$lCaucaoDataPrevistaDevolucao' => 'locacao.caucao_data_prevista_devolucao',
        '$lFaturaAPagar' => 'locacao.fatura_a_pagar',
        '$lVeiculoMarca' => 'veiculo.marca',
        '$lVeiculoModelo' => 'veiculo.modelo',
        '$lPlaca' => 'veiculo.placa',
        '$lAno' => 'veiculo.ano',
        '$lRenavam' => 'veiculo.renavam',
        '$lChassi' => 'veiculo.chassi',
        '$lCombustivel' => 'veiculo.combustivel_tipo',
        '$lGrupo' => 'locacao.grupo',
        '$lGrupoDescricao' => 'locacao.grupo_descricao',
        '$lGrupoTanque' => 'locacao.grupo_tanque',
        '$lPlano' => 'locacao.plano',
        '$lTanqSaida' => 'locacao.tanque_saida',
        '$lTanqChegada' => 'locacao.tanque_chegada',
        '$lOdoSaida' => 'locacao.km_saida',
        '$lOdoChegada' => 'locacao.km_chegada',
        '$lTotalFatura' => 'locacao.total_fatura',
        '$lBloqueioValor' => 'locacao.bloqueio_valor',
        '$lDepositoValor' => 'locacao.deposito_valor',
        '$lCoberturaTerceiros' => 'locacao.cobertura_terceiros',
        '$lFaturaPaga' => 'locacao.fatura_paga',
        '$lValorCompra' => 'veiculo.valor_compra',
        '$lValorVenda' => 'veiculo.valor_venda',
        '$lFormaPagamento' => 'locacao.forma_pagamento',

        // Veículo
        '$vPlaca' => 'veiculo.placa',
        '$vModelo' => 'veiculo.modelo',
        '$vMarca' => 'veiculo.marca',
        '$vAno' => 'veiculo.ano',
        '$vCor' => 'veiculo.cor',
        '$lCor' => 'veiculo.cor', // Variante legada
        '$vRenavam' => 'veiculo.renavam',
        '$vDescricao' => 'veiculo.descricao_completa',

        // Fatura
        '$fNumero' => 'fatura.numero',
        '$fValor' => 'fatura.valor',
        '$fVencimento' => 'fatura.data_vencimento',
        '$fLinkBoleto' => 'fatura.link_boleto',
        '$fCodigoPix' => 'fatura.codigo_pix',

        // Outros
        '$dataAtual' => 'outros.data_atual',
        '$oDataHoje' => 'outros.data_atual',
        '$oDataHojeEscrita' => 'outros.data_atual_extenso',
        '$horaAtual' => 'outros.hora_atual',
        '$oCondutoresAdicionais' => 'locacao.condutores_adicionais',
        '$oFiadores' => 'locacao.fiadores',
        '$oContagem' => 'outros.contagem',
        '$inforPlano' => 'locacao.info_plano',

        // Multa
        '$mValor' => 'multa.valor',
        '$mDataHora' => 'multa.data_hora',
        '$mDescricao' => 'multa.descricao',
        '$mLocal' => 'multa.local',
        '$mCidade' => 'multa.cidade',
        '$mEstado' => 'multa.estado',
        '$mDataVencimento' => 'multa.data_vencimento',
        '$mPago' => 'multa.pago',
        '$mOrgaoAutuador' => 'multa.orgao_autuador',
        '$mOrgaoAtuador' => 'multa.orgao_autuador', // Variante com typo
        '$mNunInfracao' => 'multa.numero_infracao',

        // Fornecedor
        '$fNome' => 'fornecedor.nome',
        '$fNFantasia' => 'fornecedor.nome_fantasia',
        '$fCPFCNPJ' => 'fornecedor.cpf_cnpj',
        '$fRGIE' => 'fornecedor.rg_ie',
        '$fRua' => 'fornecedor.endereco',
        '$fNum' => 'fornecedor.numero',
        '$fBairro' => 'fornecedor.bairro',
        '$fCidade' => 'fornecedor.cidade',
        '$fEstado' => 'fornecedor.estado',
        '$fPais' => 'fornecedor.pais',
        '$fEmail' => 'fornecedor.email',
        '$fObs' => 'fornecedor.observacoes',
    ];

    /**
     * Retorna todas as variáveis organizadas por entidade
     */
    public static function getAll(): array
    {
        return self::VARIABLES;
    }

    /**
     * Retorna variáveis de uma entidade específica
     */
    public static function getForEntity(string $entity): array
    {
        return self::VARIABLES[$entity] ?? [];
    }

    /**
     * Retorna lista de entidades disponíveis
     */
    public static function getEntities(): array
    {
        return array_keys(self::VARIABLES);
    }

    /**
     * Retorna lista plana de todas as variáveis (para autocomplete)
     *
     * @return array<string, array> ['cliente.nome' => [...info...]]
     */
    public static function getFlatList(): array
    {
        $flat = [];
        foreach (self::VARIABLES as $entity => $fields) {
            foreach ($fields as $field => $info) {
                $flat["{$entity}.{$field}"] = $info;
            }
        }
        return $flat;
    }

    /**
     * Verifica se uma variável existe
     */
    public static function exists(string $variable): bool
    {
        if ($variable === 'veiculo.combustivel') {
            return true;
        }

        $parts = explode('.', $variable, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$entity, $field] = $parts;
        return isset(self::VARIABLES[$entity][$field]);
    }

    /**
     * Retorna informações de uma variável
     */
    public static function getInfo(string $variable): ?array
    {
        if ($variable === 'veiculo.combustivel') {
            $variable = 'veiculo.combustivel_tipo';
        }

        $parts = explode('.', $variable, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$entity, $field] = $parts;
        return self::VARIABLES[$entity][$field] ?? null;
    }

    /**
     * Resolve o valor de uma variável a partir do contexto
     *
     * @param string $variable Ex: 'cliente.nome'
     * @param array $context Dados disponíveis ['cliente' => [...], 'empresa' => [...]]
     * @param string $locale Locale para formatação
     * @return string|null Valor formatado ou null se não encontrado
     */
    public static function resolve(string $variable, array $context, string $locale = 'pt_BR'): ?string
    {
        if ($variable === 'veiculo.combustivel') {
            $variable = 'veiculo.combustivel_tipo';
        }

        $info = self::getInfo($variable);
        if ($info === null) {
            return null;
        }

        $parts = explode('.', $variable, 2);
        [$entity, $field] = $parts;

        // Variáveis computadas/HTML usam builders próprios.
        if (in_array($info['type'], ['computed', 'html'], true)) {
            return self::resolveComputed($variable, $context, $locale);
        }

        // Buscar valor no contexto
        $value = $context[$entity][$field] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        // Formatar valor por tipo
        return self::format($value, $info['type'], $locale, $context);
    }

    /**
     * Resolve variáveis computadas
     */
    private static function resolveComputed(string $variable, array $context, string $locale): ?string
    {
        switch ($variable) {
            case 'cliente.primeiro_nome':
                $nome = $context['cliente']['nome'] ?? '';
                return explode(' ', trim($nome))[0] ?? null;

            case 'cliente.endereco_completo':
                return self::buildEnderecoCompleto($context['cliente'] ?? []);

            case 'empresa.endereco_completo':
                return self::buildEnderecoCompleto($context['empresa'] ?? []);

            case 'veiculo.descricao_completa':
                $v = $context['veiculo'] ?? [];
                $cor = $v['veiculo_cor'] ?? $v['cor'] ?? '';
                $placa = $v['veiculo_placa'] ?? $v['placa'] ?? '';
                $parts = array_filter([
                    $v['veiculo_marca'] ?? $v['marca'] ?? '',
                    $v['veiculo_modelo'] ?? $v['modelo'] ?? '',
                    $v['veiculo_ano'] ?? $v['ano'] ?? '',
                    $cor ? "- {$cor}" : '',
                    $placa ? "- {$placa}" : '',
                ]);
                return implode(' ', $parts) ?: null;

            case 'fatura.dias_atraso':
                $vencimento = $context['fatura']['data_vencimento'] ?? null;
                if (!$vencimento) {
                    return '0';
                }
                $dataVenc = new \DateTime($vencimento);
                $hoje = new \DateTime();
                $diff = $hoje->diff($dataVenc);
                if ($dataVenc > $hoje) {
                    return '0';
                }
                return (string) $diff->days;

            case 'fatura.parcela_descricao':
                return self::formatInvoiceInstallment(
                    (int) ($context['fatura']['parcela'] ?? 0),
                    (int) ($context['fatura']['total_parcelas'] ?? 0),
                    $locale
                );

            case 'outros.data_atual':
                return self::formatDate(date('Y-m-d'), $locale);

            case 'outros.hora_atual':
                return date('H:i');

            case 'outros.ano_atual':
                return date('Y');

            case 'outros.data_atual_extenso':
                return self::formatDateExtensively(date('Y-m-d'), $locale);

            case 'outros.nova_senha':
                // Nova senha gerada (template cliente_nova_senha). Vem em texto claro
                // apenas no momento do disparo — nao persiste no BD.
                return isset($context['outros']['nova_senha']) ? (string) $context['outros']['nova_senha'] : null;

            // ===== CONTRATO - Variáveis Computed =====

            case 'contrato.filial_endereco':
                return self::buildEnderecoCompleto($context['contrato']['filial'] ?? []);

            case 'contrato.contagem':
                $contagem = (string) ($context['contrato']['contagem'] ?? '');
                $translationKey = match ($contagem) {
                    'dia' => 'day',
                    'semana' => 'week',
                    'mes' => 'month',
                    'ano' => 'year',
                    default => null,
                };

                if ($translationKey === null) {
                    return $contagem !== '' ? $contagem : null;
                }

                return Translator::getInstance()->get(
                    'variables.contract_count.' . $translationKey,
                    [],
                    $locale
                );

            case 'contrato.comando_parcela':
                return self::formatContratoComandoParcela(
                    (string) ($context['contrato']['comando_parcela_comando'] ?? ''),
                    (string) ($context['contrato']['comando_parcela_descricao'] ?? ''),
                    $locale
                );

            case 'contrato.veiculos':
                return self::buildContratoVeiculosTexto($context['contrato']['veiculos'] ?? [], $locale, $context);

            case 'contrato.veiculos_tabela':
                return self::buildContratoVeiculosTabela($context['contrato']['veiculos'] ?? [], $locale, $context);

            case 'contrato.veiculos_anexo':
                return self::buildContratoVeiculosAnexo($context['contrato']['veiculos'] ?? [], $locale, $context);

            case 'contrato.taxas':
                return self::buildContratoTaxasTexto($context['contrato']['taxas'] ?? [], $locale, $context);

            case 'contrato.taxas_tabela':
                return self::buildContratoTaxasTabela($context['contrato']['taxas'] ?? [], $locale, $context);

            case 'contrato.parcelas':
                return self::buildContratoParcelasTexto($context['contrato']['parcelas'] ?? [], $locale, $context);

            case 'contrato.parcelas_tabela':
                return self::buildContratoParcelasTabela($context['contrato']['parcelas'] ?? [], $locale, $context);

            case 'contrato.parcelas_tabela_status':
                return self::buildContratoParcelasTabelaStatus($context['contrato']['parcelas'] ?? [], $locale, $context);

            case 'contrato.valor.parcela':
                return self::buildContratoValorParcela($context['contrato']['parcelas'] ?? [], $locale, $context);

            case 'contrato.condutores':
                return self::buildContratoCondutoresTexto($context['contrato']['condutores'] ?? [], $locale);

            case 'contrato.fiadores':
                return self::buildContratoFiadoresTexto($context['contrato']['fiadores'] ?? []);

            case 'contrato.fiadores_assinaturas':
                return self::buildAssinaturasTexto($context['contrato']['fiadores'] ?? [], 'Fiador');

            case 'contrato.avalistas':
                return self::buildContratoAvalistasTexto($context['contrato']['avalistas'] ?? []);

            case 'contrato.avalistas_assinaturas':
                return self::buildAssinaturasTexto($context['contrato']['avalistas'] ?? [], 'Avalista');

            case 'contrato.testemunhas':
                return self::buildContratoTestemunhasTexto($context['contrato']['testemunhas'] ?? []);

            case 'contrato.testemunhas_assinaturas':
                return self::buildAssinaturasTexto($context['contrato']['testemunhas'] ?? [], 'Testemunha');

            // Assinaturas em colunas (lado a lado)
            case 'contrato.fiadores_assinaturas_colunas':
                return self::buildAssinaturasColunas($context['contrato']['fiadores'] ?? [], 'Fiador');

            case 'contrato.avalistas_assinaturas_colunas':
                return self::buildAssinaturasColunas($context['contrato']['avalistas'] ?? [], 'Avalista');

            case 'contrato.testemunhas_assinaturas_colunas':
                return self::buildAssinaturasColunas($context['contrato']['testemunhas'] ?? [], 'Testemunha');

            case 'contrato.assinatura_cliente':
                $cliente = $context['cliente'] ?? [];
                $nome = htmlspecialchars($cliente['nome'] ?? 'Cliente');
                $cpf = !empty($cliente['cpf_cnpj']) ? self::formatDocument($cliente['cpf_cnpj']) : '';
                $html = '<div style="margin-bottom:60px;">';
                $html .= '<div style="border-bottom:1px solid #000; width:250px; margin-bottom:5px;"></div>';
                $html .= '<div><strong>LOCATÁRIO:</strong> ' . $nome . '</div>';
                if ($cpf) {
                    $html .= '<div>CPF: ' . htmlspecialchars($cpf) . '</div>';
                }
                $html .= '</div>';
                return $html;

            // ===== LOCACAO - Variáveis Computed =====

            case 'locacao.condutores_adicionais':
                return self::buildContratoCondutoresTexto($context['locacao']['condutores'] ?? [], $locale);

            case 'locacao.fiadores':
                return self::buildPessoasTexto($context['locacao']['fiadores'] ?? []);

            default:
                return null;
        }
    }

    /**
     * Formata a identificacao da parcela para notificacoes financeiras.
     */
    public static function formatInvoiceInstallment(int $parcela, int $totalParcelas, string $locale = 'pt_BR'): ?string
    {
        if ($parcela <= 0) {
            return null;
        }

        $key = $totalParcelas > 0
            ? 'templates.installment.with_total'
            : 'templates.installment.without_total';

        return Translator::getInstance()->get($key, [
            'parcela' => $parcela,
            'total' => $totalParcelas,
        ], $locale);
    }

    /**
     * Converte o comando tecnico de parcelas em uma descricao apropriada para documentos.
     */
    private static function formatContratoComandoParcela(
        string $comando,
        string $descricao,
        string $locale
    ): string {
        $translator = Translator::getInstance();
        $comando = trim($comando);

        if ($comando === '') {
            return $translator->get('variables.installment_command.not_informed', [], $locale);
        }

        $info = ComandoParcela::parseComando($comando);

        switch ($info['tipo']) {
            case 'avista':
                return $translator->get('variables.installment_command.cash', [], $locale);

            case 'prazo_unico':
                $dias = (int) ($info['intervalos'][0] ?? 0);
                $key = $dias === 1 ? 'single_day' : 'single_days';
                return $translator->get('variables.installment_command.' . $key, ['days' => $dias], $locale);

            case 'mensal':
                return $translator->get('variables.installment_command.monthly_range', [
                    'min' => (int) $info['min'],
                    'max' => (int) $info['max'],
                ], $locale);

            case 'prazos_fixos':
                $intervalos = array_map('intval', $info['intervalos'] ?? []);
                $quantidade = count($intervalos);
                $temEntrada = ($intervalos[0] ?? null) === 0;
                $prazos = $temEntrada ? array_slice($intervalos, 1) : $intervalos;

                return $translator->get(
                    'variables.installment_command.' . ($temEntrada ? 'fixed_with_upfront' : 'fixed'),
                    [
                        'count' => $quantidade,
                        'days' => self::formatLocalizedList($prazos, $locale),
                    ],
                    $locale
                );

            case 'semanal':
                return $translator->get('variables.installment_command.weekly', [], $locale);

            case 'semanal_dia':
                return self::translatedInstallmentWeekday((string) ($info['dia_semana'] ?? ''), 'plural', $locale);

            case 'dia_mes':
                return $translator->get('variables.installment_command.monthly_day', [
                    'day' => (int) ($info['dia_mes'] ?? 0),
                ], $locale);

            case 'dias_semana':
                return self::translatedInstallmentWeekday((string) ($info['dia_semana'] ?? ''), 'singular', $locale);

            default:
                $descricao = trim($descricao);
                return $descricao !== ''
                    ? $descricao
                    : $translator->get('variables.installment_command.not_informed', [], $locale);
        }
    }

    /**
     * Formata uma lista numerica com a conjuncao do idioma do documento.
     */
    private static function formatLocalizedList(array $values, string $locale): string
    {
        $values = array_map(static fn ($value) => (string) $value, $values);
        $count = count($values);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $values[0];
        }

        $translator = Translator::getInstance();
        $last = array_pop($values);

        return implode(
            $translator->get('variables.installment_command.list_separator', [], $locale),
            $values
        ) . $translator->get('variables.installment_command.list_last_separator', [], $locale) . $last;
    }

    /**
     * Traduz o dia da semana usado pelos comandos de parcelas.
     */
    private static function translatedInstallmentWeekday(string $weekday, string $form, string $locale): string
    {
        $translator = Translator::getInstance();
        $key = 'variables.installment_command.weekdays.' . $weekday . '.' . $form;
        $translated = $translator->get($key, [], $locale);

        return $translated !== $key
            ? $translated
            : $translator->get('variables.installment_command.not_informed', [], $locale);
    }

    // ===== Métodos auxiliares para Contrato =====

    /**
     * Gera texto formatado da lista de veículos do contrato
     */
    private static function buildContratoVeiculosTexto(array $veiculos, string $locale, array $context = []): ?string
    {
        if (empty($veiculos)) {
            return null;
        }

        $linhas = [];
        $i = 1;
        foreach ($veiculos as $v) {
            $descricao = trim(implode(' ', array_filter([
                $v['veiculo_marca'] ?? $v['marca'] ?? '',
                $v['veiculo_modelo'] ?? $v['modelo'] ?? '',
                $v['veiculo_ano'] ?? $v['ano'] ?? '',
            ])));
            $cor = $v['veiculo_cor'] ?? $v['cor'] ?? '';
            $placa = $v['veiculo_placa'] ?? $v['placa'] ?? '';
            $grupo = $v['grupo_nome'] ?? $v['grupo'] ?? '';

            // Plano e valor
            $plano = $v['plano'] ?? 'KL';
            $planoNome = match ($plano) {
                'KL' => 'Km Livre',
                'KMC' => 'Km Controlado',
                'KP' => 'Km Pago',
                default => $plano,
            };
            $valorPlano = match ($plano) {
                'KL' => $v['valor_plano_km_livre'] ?? 0,
                'KMC' => $v['valor_plano_km_controlado'] ?? 0,
                'KP' => $v['valor_plano_km_pago'] ?? 0,
                default => 0,
            };

            $linha = "{$i}. {$descricao}" . ($cor ? " - {$cor}" : '');
            $linha .= "\n   Placa: {$placa}";
            if ($grupo) {
                $linha .= "\n   Grupo: {$grupo}";
            }
            $linha .= "\n   Plano: {$planoNome} - " . self::formatCurrency((float)$valorPlano, $locale, $context) . "/dia";

            // Seguros
            if (!empty($v['seguro_carro'])) {
                $valorSeguro = $v['valor_seguro_carro'] ?? 0;
                $cobertura = $v['cobertura_carro'] ?? 0;
                $linha .= "\n   Seguro Veículo: Sim (" . self::formatCurrency((float)$valorSeguro, $locale, $context) . "/dia)";
                if ($cobertura > 0) {
                    $linha .= " - Cobertura: " . self::formatCurrency((float)$cobertura, $locale, $context);
                }
            }
            if (!empty($v['seguro_terceiros'])) {
                $valorSeguro = $v['valor_seguro_terceiros'] ?? 0;
                $cobertura = $v['cobertura_terceiros'] ?? 0;
                $linha .= "\n   Seguro Terceiros: Sim (" . self::formatCurrency((float)$valorSeguro, $locale, $context) . "/dia)";
                if ($cobertura > 0) {
                    $linha .= " - Cobertura: " . self::formatCurrency((float)$cobertura, $locale, $context);
                }
            }

            // Odômetro e combustível
            $km = $v['odometro_saida'] ?? '';
            $comb = $v['combustivel_saida'] ?? '';
            if ($km || $comb) {
                $linha .= "\n   ";
                if ($km) {
                    $linha .= "Km Saída: " . number_format((float)$km, 0, '', '.');
                }
                if ($km && $comb) {
                    $linha .= " | ";
                }
                if ($comb) {
                    $combNome = self::getCombustivelNome($comb);
                    $linha .= "Combustível: {$combNome}";
                }
            }

            $linhas[] = $linha;
            $i++;
        }

        return implode("\n\n", $linhas);
    }

    /**
     * Gera tabela HTML de veículos do contrato
     */
    private static function buildContratoVeiculosTabela(array $veiculos, string $locale, array $context = []): ?string
    {
        if (empty($veiculos)) {
            return null;
        }

        $html = '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<thead><tr style="background:#f5f5f5;">';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:left;">Veículo</th>';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:left;">Placa</th>';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:left;">Plano</th>';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:right;">Valor/Dia</th>';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:right;">Seguros</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($veiculos as $v) {
            $descricao = trim(implode(' ', array_filter([
                $v['veiculo_marca'] ?? $v['marca'] ?? '',
                $v['veiculo_modelo'] ?? $v['modelo'] ?? '',
                $v['veiculo_ano'] ?? $v['ano'] ?? '',
            ])));
            $placa = $v['veiculo_placa'] ?? $v['placa'] ?? '';

            $plano = $v['plano'] ?? 'KL';
            $planoNome = match ($plano) {
                'KL' => 'Km Livre',
                'KMC' => 'Km Controlado',
                'KP' => 'Km Pago',
                default => $plano,
            };
            $valorPlano = match ($plano) {
                'KL' => $v['valor_plano_km_livre'] ?? 0,
                'KMC' => $v['valor_plano_km_controlado'] ?? 0,
                'KP' => $v['valor_plano_km_pago'] ?? 0,
                default => 0,
            };

            $totalSeguros = 0;
            if (!empty($v['seguro_carro'])) {
                $totalSeguros += (float)($v['valor_seguro_carro'] ?? 0);
            }
            if (!empty($v['seguro_terceiros'])) {
                $totalSeguros += (float)($v['valor_seguro_terceiros'] ?? 0);
            }

            $html .= '<tr>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;">' . htmlspecialchars($descricao) . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;">' . htmlspecialchars($placa) . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;">' . htmlspecialchars($planoNome) . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:right;">' . self::formatCurrency((float)$valorPlano, $locale, $context) . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:right;">' . self::formatCurrency($totalSeguros, $locale, $context) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Gera anexo juridico/operacional da lista de veiculos do contrato.
     */
    private static function buildContratoVeiculosAnexo(array $veiculos, string $locale, array $context = []): ?string
    {
        if (empty($veiculos)) {
            return null;
        }

        $html = '<table style="width:100%;border-collapse:collapse;font-size:10px;">';
        $html .= '<thead><tr style="background:#f5f5f5;">';
        $html .= '<th style="border:1px solid #ddd;padding:6px;text-align:center;width:5%;">Item</th>';
        $html .= '<th style="border:1px solid #ddd;padding:6px;text-align:left;width:20%;">Veiculo</th>';
        $html .= '<th style="border:1px solid #ddd;padding:6px;text-align:left;width:20%;">Identificacao</th>';
        $html .= '<th style="border:1px solid #ddd;padding:6px;text-align:left;width:18%;">Fornecedor/Investidor</th>';
        $html .= '<th style="border:1px solid #ddd;padding:6px;text-align:left;width:22%;">Condicoes</th>';
        $html .= '<th style="border:1px solid #ddd;padding:6px;text-align:left;width:15%;">Saida</th>';
        $html .= '</tr></thead><tbody>';

        $i = 1;
        foreach ($veiculos as $v) {
            $descricao = trim(implode(' ', array_filter([
                $v['veiculo_marca'] ?? $v['marca'] ?? '',
                $v['veiculo_modelo'] ?? $v['modelo'] ?? '',
                $v['veiculo_ano'] ?? $v['ano'] ?? '',
            ])));
            $cor = $v['veiculo_cor'] ?? $v['cor'] ?? '';
            $grupo = $v['grupo_nome'] ?? $v['grupo'] ?? '';

            $veiculoInfo = self::htmlLines(array_filter([
                $descricao ?: 'Veiculo nao informado',
                $cor ? 'Cor: ' . $cor : '',
                $grupo ? 'Grupo: ' . $grupo : '',
            ]));

            $identificacao = self::htmlLines(array_filter([
                'Placa: ' . (($v['veiculo_placa'] ?? $v['placa'] ?? '') ?: '-'),
                !empty($v['veiculo_renavam'] ?? $v['renavam'] ?? '') ? 'RENAVAM: ' . ($v['veiculo_renavam'] ?? $v['renavam']) : '',
                !empty($v['veiculo_chassi'] ?? $v['chassi'] ?? '') ? 'Chassi: ' . ($v['veiculo_chassi'] ?? $v['chassi']) : '',
            ]));

            $fornecedorNome = $v['fornecedor_nome'] ?? $v['fornecedor']['nome'] ?? '';
            $fornecedorDocumento = $v['fornecedor_cpf_cnpj'] ?? $v['fornecedor']['cpf_cnpj'] ?? '';
            $fornecedorTipo = !empty($v['fornecedor_investidor']) ? 'Investidor' : 'Fornecedor';
            $fornecedor = $fornecedorNome
                ? self::htmlLines(array_filter([
                    $fornecedorTipo . ': ' . $fornecedorNome,
                    $fornecedorDocumento ? 'CPF/CNPJ: ' . $fornecedorDocumento : '',
                ]))
                : 'Proprio/nao informado';

            $plano = $v['plano'] ?? 'KL';
            $planoNome = match ($plano) {
                'KL' => 'Km Livre',
                'KMC' => 'Km Controlado',
                'KP' => 'Km Pago',
                default => (string) $plano,
            };
            $valorPlano = match ($plano) {
                'KL' => $v['valor_plano_km_livre'] ?? 0,
                'KMC' => $v['valor_plano_km_controlado'] ?? 0,
                'KP' => $v['valor_plano_km_pago'] ?? 0,
                default => 0,
            };

            $seguros = [];
            if (!empty($v['seguro_carro'])) {
                $seguros[] = 'Seguro veiculo: ' . self::formatCurrency((float) ($v['valor_seguro_carro'] ?? 0), $locale, $context)
                    . (!empty($v['cobertura_carro']) ? ' | Cobertura: ' . self::formatCurrency((float) $v['cobertura_carro'], $locale, $context) : '');
            }
            if (!empty($v['seguro_terceiros'])) {
                $seguros[] = 'Seguro terceiros: ' . self::formatCurrency((float) ($v['valor_seguro_terceiros'] ?? 0), $locale, $context)
                    . (!empty($v['cobertura_terceiros']) ? ' | Cobertura: ' . self::formatCurrency((float) $v['cobertura_terceiros'], $locale, $context) : '');
            }

            $condicoes = self::htmlLines(array_filter(array_merge([
                'Plano: ' . $planoNome,
                'Valor: ' . self::formatCurrency((float) $valorPlano, $locale, $context) . '/periodo',
                !empty($v['km_franquia']) ? 'Franquia: ' . (int) $v['km_franquia'] . ' km' : '',
                !empty($v['valor_km_excedente']) ? 'Km excedente: ' . self::formatCurrency((float) $v['valor_km_excedente'], $locale, $context) : '',
            ], $seguros)));

            $saida = [];
            if (!empty($v['data_saida'])) {
                $saida[] = 'Data: ' . self::formatDate((string) $v['data_saida'], $locale)
                    . (strtotime((string) $v['data_saida']) ? ' ' . date('H:i', strtotime((string) $v['data_saida'])) : '');
            }
            if (!empty($v['odometro_saida'])) {
                $saida[] = 'Km: ' . number_format((float) $v['odometro_saida'], 0, '', '.');
            }
            if (array_key_exists('combustivel_saida', $v) && $v['combustivel_saida'] !== null && $v['combustivel_saida'] !== '') {
                $saida[] = 'Comb./Carga: ' . self::getCombustivelNome($v['combustivel_saida']);
            }

            $html .= '<tr>';
            $html .= '<td style="border:1px solid #ddd;padding:6px;text-align:center;vertical-align:top;">' . $i . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:6px;vertical-align:top;">' . $veiculoInfo . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:6px;vertical-align:top;">' . $identificacao . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:6px;vertical-align:top;">' . $fornecedor . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:6px;vertical-align:top;">' . $condicoes . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:6px;vertical-align:top;">' . ($saida ? self::htmlLines($saida) : '-') . '</td>';
            $html .= '</tr>';
            $i++;
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private static function htmlLines(array $lines): string
    {
        return implode('<br>', array_map(
            static fn($line) => htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8'),
            array_values($lines)
        ));
    }

    /**
     * Gera texto formatado da lista de taxas do contrato
     */
    private static function buildContratoTaxasTexto(array $taxas, string $locale, array $context = []): ?string
    {
        if (empty($taxas)) {
            return null;
        }

        $linhas = [];
        $i = 1;
        foreach ($taxas as $t) {
            $nome = $t['nome'] ?? 'Taxa';
            $qtd = $t['quantidade'] ?? 1;
            $valorUnit = $t['valor_unitario'] ?? 0;
            $valorTotal = $t['valor_total'] ?? ($qtd * $valorUnit);

            $linha = "{$i}. {$nome}";
            $linha .= "\n   Quantidade: {$qtd} | Valor Unitário: " . self::formatCurrency((float)$valorUnit, $locale, $context);
            $linha .= " | Total: " . self::formatCurrency((float)$valorTotal, $locale, $context);

            $linhas[] = $linha;
            $i++;
        }

        return implode("\n\n", $linhas);
    }

    /**
     * Gera tabela HTML de taxas do contrato
     */
    private static function buildContratoTaxasTabela(array $taxas, string $locale, array $context = []): ?string
    {
        if (empty($taxas)) {
            return null;
        }

        $html = '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<thead><tr style="background:#f5f5f5;">';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:left;">Descrição</th>';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:center;">Qtd</th>';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:right;">Valor Unit.</th>';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:right;">Total</th>';
        $html .= '</tr></thead><tbody>';

        $totalGeral = 0;
        foreach ($taxas as $t) {
            $nome = $t['nome'] ?? 'Taxa';
            $qtd = $t['quantidade'] ?? 1;
            $valorUnit = $t['valor_unitario'] ?? 0;
            $valorTotal = $t['valor_total'] ?? ($qtd * $valorUnit);
            $totalGeral += (float)$valorTotal;

            $html .= '<tr>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;">' . htmlspecialchars($nome) . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:center;">' . $qtd . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:right;">' . self::formatCurrency((float)$valorUnit, $locale, $context) . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:right;">' . self::formatCurrency((float)$valorTotal, $locale, $context) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody><tfoot>';
        $html .= '<tr style="background:#f5f5f5;font-weight:bold;">';
        $html .= '<td colspan="3" style="border:1px solid #ddd;padding:8px;text-align:right;">Total Taxas</td>';
        $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:right;">' . self::formatCurrency($totalGeral, $locale, $context) . '</td>';
        $html .= '</tr></tfoot></table>';

        return $html;
    }

    /**
     * Gera texto formatado da lista de parcelas do contrato
     */
    private static function buildContratoParcelasTexto(array $parcelas, string $locale, array $context = []): ?string
    {
        if (empty($parcelas)) {
            return null;
        }

        $linhas = [];
        foreach ($parcelas as $p) {
            $num = $p['parcela'] ?? 1;
            $total = $p['total_parcelas'] ?? 1;
            $vencimento = !empty($p['data_venci']) ? self::formatDate($p['data_venci'], $locale) : '-';
            $valor = $p['valor_total'] ?? $p['valor_subtotal'] ?? 0;
            $pago = ($p['pago'] ?? 'N') === 'S';
            $dataPago = !empty($p['data_pago']) ? self::formatDate($p['data_pago'], $locale) : null;
            $forma = $p['forma_pagamento_nome'] ?? '';
            $conta = $p['conta_nome'] ?? '';

            $statusTxt = $pago ? "PAGO" . ($dataPago ? " em {$dataPago}" : '') : "PENDENTE";

            $linha = "Parcela {$num}/{$total} - Vencimento: {$vencimento}";
            $linha .= "\n   Valor: " . self::formatCurrency((float)$valor, $locale, $context) . " | Status: {$statusTxt}";
            if ($forma || $conta) {
                $linha .= "\n   ";
                if ($forma) {
                    $linha .= "Forma: {$forma}";
                }
                if ($forma && $conta) {
                    $linha .= " | ";
                }
                if ($conta) {
                    $linha .= "Conta: {$conta}";
                }
            }

            $linhas[] = $linha;
        }

        return implode("\n\n", $linhas);
    }

    /**
     * Gera tabela HTML de parcelas do contrato
     */
    private static function buildContratoParcelasTabela(array $parcelas, string $locale, array $context = []): ?string
    {
        return self::buildContratoParcelasTabelaHtml($parcelas, $locale, $context, false);
    }

    /**
     * Gera tabela HTML de parcelas do contrato com status
     */
    private static function buildContratoParcelasTabelaStatus(array $parcelas, string $locale, array $context = []): ?string
    {
        return self::buildContratoParcelasTabelaHtml($parcelas, $locale, $context, true);
    }

    private static function buildContratoParcelasTabelaHtml(array $parcelas, string $locale, array $context, bool $incluirStatus): ?string
    {
        if (empty($parcelas)) {
            return null;
        }

        $html = '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<thead><tr style="background:#f5f5f5;">';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:center;">Parcela</th>';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:center;">Vencimento</th>';
        $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:right;">Valor</th>';
        if ($incluirStatus) {
            $html .= '<th style="border:1px solid #ddd;padding:8px;text-align:center;">Status</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($parcelas as $p) {
            $num = $p['parcela'] ?? 1;
            $total = $p['total_parcelas'] ?? 1;
            $vencimento = !empty($p['data_venci']) ? self::formatDate($p['data_venci'], $locale) : '-';
            $valor = $p['valor_total'] ?? $p['valor_subtotal'] ?? 0;
            $pago = ($p['pago'] ?? 'N') === 'S';
            $statusTxt = $pago ? 'Pago' : 'Pendente';
            $statusStyle = $pago ? 'color:green;' : 'color:orange;';

            $html .= '<tr>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:center;">' . $num . '/' . $total . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:center;">' . $vencimento . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:right;">' . self::formatCurrency((float)$valor, $locale, $context) . '</td>';
            if ($incluirStatus) {
                $html .= '<td style="border:1px solid #ddd;padding:8px;text-align:center;' . $statusStyle . '">' . $statusTxt . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Retorna o valor mais comum entre as parcelas do contrato.
     */
    private static function buildContratoValorParcela(array $parcelas, string $locale, array $context = []): ?string
    {
        if (empty($parcelas)) {
            return null;
        }

        $grupos = [];
        $ordem = 0;

        foreach ($parcelas as $parcela) {
            $valor = (float) ($parcela['valor_total'] ?? $parcela['valor_subtotal'] ?? 0);
            if ($valor <= 0) {
                continue;
            }

            $valor = round($valor, 2);
            $chave = number_format($valor, 2, '.', '');

            if (!isset($grupos[$chave])) {
                $grupos[$chave] = [
                    'valor' => $valor,
                    'quantidade' => 0,
                    'ordem' => $ordem,
                ];
            }

            $grupos[$chave]['quantidade']++;
            $ordem++;
        }

        if (empty($grupos)) {
            return null;
        }

        $selecionado = null;
        foreach ($grupos as $grupo) {
            if (
                $selecionado === null
                || $grupo['quantidade'] > $selecionado['quantidade']
                || (
                    $grupo['quantidade'] === $selecionado['quantidade']
                    && $grupo['ordem'] < $selecionado['ordem']
                )
            ) {
                $selecionado = $grupo;
            }
        }

        return self::formatCurrency((float) $selecionado['valor'], $locale, $context);
    }

    /**
     * Gera texto formatado da lista de condutores adicionais
     */
    private static function buildContratoCondutoresTexto(mixed $condutores, string $locale): ?string
    {
        $condutores = self::normalizeList($condutores);

        if (empty($condutores)) {
            return null;
        }

        $linhas = [];
        $i = 1;
        foreach ($condutores as $c) {
            $nome = $c['nome'] ?? '';
            $cpf = !empty($c['cpf']) ? self::formatDocument($c['cpf']) : '';
            $cnh = $c['cnh'] ?? '';
            $categoria = $c['categoria'] ?? '';
            $validade = !empty($c['validade']) ? self::formatDate($c['validade'], $locale) : '';

            $linha = "{$i}. {$nome}";
            if ($cpf) {
                $linha .= "\n   CPF: {$cpf}";
            }
            if ($cnh) {
                $linha .= "\n   CNH: {$cnh}";
                if ($categoria) {
                    $linha .= " (Categoria {$categoria})";
                }
            }
            if ($validade) {
                $linha .= "\n   Validade: {$validade}";
            }

            $linhas[] = $linha;
            $i++;
        }

        return implode("\n\n", $linhas);
    }

    /**
     * Gera texto formatado da lista de fiadores
     */
    private static function buildContratoFiadoresTexto(array $fiadores): ?string
    {
        return self::buildPessoasTexto($fiadores);
    }

    /**
     * Gera texto formatado da lista de avalistas
     */
    private static function buildContratoAvalistasTexto(array $avalistas): ?string
    {
        return self::buildPessoasTexto($avalistas);
    }

    /**
     * Gera texto formatado da lista de testemunhas
     */
    private static function buildContratoTestemunhasTexto(array $testemunhas): ?string
    {
        return self::buildPessoasTexto($testemunhas);
    }

    /**
     * Gera texto formatado para lista de pessoas (fiadores, avalistas, testemunhas)
     */
    private static function buildPessoasTexto(mixed $pessoas): ?string
    {
        $pessoas = self::normalizeList($pessoas);

        if (empty($pessoas)) {
            return null;
        }

        $linhas = [];
        $i = 1;
        foreach ($pessoas as $p) {
            $nome = $p['nome'] ?? '';
            $cc = !empty($p['cc']) ? self::formatDocument($p['cc']) : '';

            $linha = "{$i}. {$nome}";
            if ($cc) {
                $linha .= "\n   CPF/CNPJ: {$cc}";
            }

            $linhas[] = $linha;
            $i++;
        }

        return implode("\n\n", $linhas);
    }

    /**
     * Normaliza listas vindas como array, JSON ou valor vazio.
     */
    private static function normalizeList(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Gera espaços para assinaturas (versão vertical)
     */
    private static function buildAssinaturasTexto(array $pessoas, string $tipo): ?string
    {
        if (empty($pessoas)) {
            return null;
        }

        // Pessoas podem vir como JSON string
        if (is_string($pessoas)) {
            $pessoas = json_decode($pessoas, true) ?? [];
        }

        if (empty($pessoas)) {
            return null;
        }

        $html = '';
        $i = 1;
        foreach ($pessoas as $p) {
            $nome = htmlspecialchars($p['nome'] ?? '');
            $cc = !empty($p['cc']) ? self::formatDocument($p['cc']) : '';

            $html .= '<div style="margin-bottom:60px;">';
            $html .= '<div style="border-bottom:1px solid #000; width:250px; margin-bottom:5px;"></div>';
            $html .= '<div><strong>' . $tipo . ' ' . $i . ':</strong> ' . $nome . '</div>';
            if ($cc) {
                $html .= '<div>CPF: ' . htmlspecialchars($cc) . '</div>';
            }
            $html .= '</div>';
            $i++;
        }

        return $html;
    }

    /**
     * Gera espaços para assinaturas (versão em colunas, lado a lado)
     */
    private static function buildAssinaturasColunas(array $pessoas, string $tipo): ?string
    {
        if (empty($pessoas)) {
            return null;
        }

        // Pessoas podem vir como JSON string
        if (is_string($pessoas)) {
            $pessoas = json_decode($pessoas, true) ?? [];
        }

        if (empty($pessoas)) {
            return null;
        }

        $html = '<table style="width:100%; border-collapse:collapse;">';
        $i = 1;
        $total = count($pessoas);

        for ($idx = 0; $idx < $total; $idx += 2) {
            $html .= '<tr>';

            // Primeira pessoa da linha
            $p1 = $pessoas[$idx];
            $nome1 = htmlspecialchars($p1['nome'] ?? '');
            $cc1 = !empty($p1['cc']) ? self::formatDocument($p1['cc']) : '';

            $html .= '<td style="width:50%; padding:20px; vertical-align:top;">';
            $html .= '<div style="border-bottom:1px solid #000; width:200px; margin-bottom:5px;"></div>';
            $html .= '<div><strong>' . $tipo . ' ' . $i . ':</strong> ' . $nome1 . '</div>';
            if ($cc1) {
                $html .= '<div>CPF: ' . htmlspecialchars($cc1) . '</div>';
            }
            $html .= '</td>';
            $i++;

            // Segunda pessoa da linha (se existir)
            if (isset($pessoas[$idx + 1])) {
                $p2 = $pessoas[$idx + 1];
                $nome2 = htmlspecialchars($p2['nome'] ?? '');
                $cc2 = !empty($p2['cc']) ? self::formatDocument($p2['cc']) : '';

                $html .= '<td style="width:50%; padding:20px; vertical-align:top;">';
                $html .= '<div style="border-bottom:1px solid #000; width:200px; margin-bottom:5px;"></div>';
                $html .= '<div><strong>' . $tipo . ' ' . $i . ':</strong> ' . $nome2 . '</div>';
                if ($cc2) {
                    $html .= '<div>CPF: ' . htmlspecialchars($cc2) . '</div>';
                }
                $html .= '</td>';
                $i++;
            } else {
                $html .= '<td style="width:50%;"></td>';
            }

            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Retorna nome do nível de combustível
     */
    private static function getCombustivelNome($nivel): string
    {
        $niveis = [
            '0' => 'Reserva',
            '1' => '1/8',
            '2' => '1/4',
            '3' => '3/8',
            '4' => '1/2',
            '5' => '5/8',
            '6' => '3/4',
            '7' => '7/8',
            '8' => 'Cheio',
        ];

        return $niveis[(string)$nivel] ?? (string)$nivel;
    }

    /**
     * Formata data por extenso
     */
    private static function formatDateExtensively(string $date, string $locale): string
    {
        try {
            $dt = new \DateTime($date);
            $day = $dt->format('j');
            $month = (int) $dt->format('n');
            $year = $dt->format('Y');

            $months = [
                'pt_BR' => [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
                'pt_PT' => [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
                'en_US' => [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                'es_ES' => [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
                'it_IT' => [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
            ];

            $monthName = $months[$locale][$month] ?? $months['pt_BR'][$month];

            // Formato por locale
            if ($locale === 'en_US') {
                return "{$monthName} {$day}, {$year}";
            }

            return "{$day} de {$monthName} de {$year}";
        } catch (\Exception) {
            return $date;
        }
    }

    /**
     * Constrói endereço completo a partir de partes
     */
    private static function buildEnderecoCompleto(array $data): ?string
    {
        $parts = [];

        if (!empty($data['endereco'])) {
            $endereco = $data['endereco'];
            if (!empty($data['numero'])) {
                $endereco .= ', ' . $data['numero'];
            }
            if (!empty($data['complemento'])) {
                $endereco .= ' - ' . $data['complemento'];
            }
            $parts[] = $endereco;
        }

        if (!empty($data['bairro'])) {
            $parts[] = $data['bairro'];
        }

        $cidadeUf = [];
        if (!empty($data['cidade'])) {
            $cidadeUf[] = $data['cidade'];
        }
        if (!empty($data['uf'])) {
            $cidadeUf[] = $data['uf'];
        }
        if ($cidadeUf) {
            $parts[] = implode('/', $cidadeUf);
        }

        if (!empty($data['cep'])) {
            $parts[] = self::formatCep($data['cep']);
        }

        return $parts ? implode(' - ', $parts) : null;
    }

    /**
     * Formata valor conforme tipo
     */
    public static function format(mixed $value, string $type, string $locale = 'pt_BR', array $context = []): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        switch ($type) {
            case 'currency':
                return self::formatCurrency((float) $value, $locale, $context);

            case 'date':
                return self::formatDate($value, $locale);

            case 'phone':
                return self::formatPhone((string) $value);

            case 'document':
                return self::formatDocument((string) $value);

            default:
                return (string) $value;
        }
    }

    /**
     * Formata valor monetário
     */
    private static function formatCurrency(float $value, string $locale, array $context = []): string
    {
        $empresa = $context['empresa'] ?? [];
        $matrizId = isset($empresa['id']) && (int) $empresa['id'] > 0 ? (int) $empresa['id'] : null;

        if ($matrizId !== null) {
            try {
                return CurrencyHelper::format($value, true, $matrizId);
            } catch (\Throwable) {
                // Fallback abaixo mantém documentos imprimíveis mesmo sem acesso ao BD.
            }
        }

        $currency = strtoupper((string) ($empresa['currency_code'] ?? $empresa['currency'] ?? ''));
        $locale = (string) ($empresa['locale'] ?? $locale);

        $localeConfigs = [
            'pt_BR' => ['decimal' => ',', 'thousands' => '.', 'position' => 'before'],
            'pt_PT' => ['decimal' => ',', 'thousands' => '.', 'position' => 'after'],
            'en_US' => ['decimal' => '.', 'thousands' => ',', 'position' => 'before'],
            'es_ES' => ['decimal' => ',', 'thousands' => '.', 'position' => 'after'],
            'it_IT' => ['decimal' => ',', 'thousands' => '.', 'position' => 'after'],
        ];

        $currencySymbols = [
            'BRL' => 'R$',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];

        if ($currency === '') {
            $currency = match ($locale) {
                'en_US' => 'USD',
                'pt_PT', 'es_ES', 'it_IT' => 'EUR',
                default => 'BRL',
            };
        }

        $config = $localeConfigs[$locale] ?? $localeConfigs['pt_BR'];
        $symbol = $currencySymbols[$currency] ?? $currency;
        $formatted = number_format($value, 2, $config['decimal'], $config['thousands']);

        return $config['position'] === 'after'
            ? $formatted . ' ' . $symbol
            : $symbol . ' ' . $formatted;
    }

    /**
     * Formata data
     */
    private static function formatDate(string $date, string $locale): string
    {
        try {
            $dt = new \DateTime($date);

            $formats = [
                'pt_BR' => 'd/m/Y',
                'pt_PT' => 'd/m/Y',
                'en_US' => 'm/d/Y',
                'es_ES' => 'd/m/Y',
                'it_IT' => 'd/m/Y',
            ];

            return $dt->format($formats[$locale] ?? 'd/m/Y');
        } catch (\Exception) {
            return $date;
        }
    }

    /**
     * Formata telefone brasileiro
     */
    private static function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (strlen($phone) === 11) {
            return sprintf('(%s) %s-%s', substr($phone, 0, 2), substr($phone, 2, 5), substr($phone, 7));
        }

        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s', substr($phone, 0, 2), substr($phone, 2, 4), substr($phone, 6));
        }

        return $phone;
    }

    /**
     * Formata CPF ou CNPJ
     */
    private static function formatDocument(string $doc): string
    {
        $doc = preg_replace('/\D/', '', $doc);

        if (strlen($doc) === 11) {
            return sprintf('%s.%s.%s-%s', substr($doc, 0, 3), substr($doc, 3, 3), substr($doc, 6, 3), substr($doc, 9, 2));
        }

        if (strlen($doc) === 14) {
            return sprintf('%s.%s.%s/%s-%s', substr($doc, 0, 2), substr($doc, 2, 3), substr($doc, 5, 3), substr($doc, 8, 4), substr($doc, 12, 2));
        }

        return $doc;
    }

    /**
     * Formata CEP
     */
    private static function formatCep(string $cep): string
    {
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) === 8) {
            return sprintf('%s-%s', substr($cep, 0, 5), substr($cep, 5, 3));
        }

        return $cep;
    }

    /**
     * Retorna mapeamento de variáveis legadas
     */
    public static function getLegacyMapping(): array
    {
        return self::LEGACY_MAPPING;
    }

    /**
     * Converte variável legada para novo formato
     */
    public static function convertLegacy(string $legacyVar): ?string
    {
        return self::LEGACY_MAPPING[$legacyVar] ?? null;
    }

    /**
     * Retorna variáveis para exibição no frontend
     * Agrupa por entidade com labels traduzidas
     */
    public static function getForFrontend(string $locale = 'pt_BR'): array
    {
        $translator = Translator::getInstance();
        $result = [];

        foreach (self::VARIABLES as $entity => $fields) {
            $entityLabel = $translator->get("variables.entities.{$entity}", [], $locale);

            $result[$entity] = [
                'label' => $entityLabel !== "variables.entities.{$entity}" ? $entityLabel : ucfirst($entity),
                'variables' => [],
            ];

            foreach ($fields as $field => $info) {
                $label = $translator->get($info['label_key'], [], $locale);

                // Para tipo 'html', não enviar HTML completo (quebraria o layout da listagem)
                $example = $info['example'];
                if (($info['type'] ?? '') === 'html') {
                    $example = '(Conteúdo HTML formatado)';
                }

                $result[$entity]['variables'][] = [
                    'variable' => '{{' . $entity . '.' . $field . '}}',
                    'label' => $label !== $info['label_key'] ? $label : $field,
                    'type' => $info['type'],
                    'example' => $example,
                ];
            }
        }

        return $result;
    }
}
