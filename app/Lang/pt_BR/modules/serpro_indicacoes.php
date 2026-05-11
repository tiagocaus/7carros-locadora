<?php

return [
    'instructions' => [
        'button' => 'Instruções',
        'title' => 'Como Funciona a Indicação de Condutor',
        'what_is_title' => 'O que e a Indicação de Condutor?',
        'what_is_text' => 'Quando um veículo da frota recebe uma multa, a autuação vem em nome da empresa (proprietaria). A indicação de condutor e o processo legal de transferir a responsabilidade da infração para a pessoa que realmente estava dirigindo no momento.',

        'real_infrator_title' => 'Tipo 1: Real Infrator',
        'real_infrator_desc' => 'Usado para transferir os pontos e a responsabilidade de uma multa específica para quem realmente cometeu a infração.',
        'real_infrator_when' => 'Ao receber uma multa e identificar quem estava dirigindo',
        'real_infrator_prereq' => 'A multa precisa ter sido importada via SERPRO (com código do órgão, AIT e código da infração)',
        'real_infrator_fields' => 'Selecionar a multa + CPF do condutor',
        'real_infrator_result' => 'Os pontos da infração sao transferidos para a CNH do indicado',

        'principal_title' => 'Tipo 2: Principal Condutor',
        'principal_desc' => 'Usado para registrar o motorista habitual de um veículo. Não está vinculado a uma multa específica.',
        'principal_when' => 'Ao locar um veículo para um cliente, registrar ele como condutor principal',
        'principal_advantage' => 'Futuras multas desse veículo serão automaticamente direcionadas ao condutor registrado',
        'principal_fields' => 'Placa do veículo + CPF + CNH do condutor',
        'principal_important' => 'Pode ser excluído quando a locação terminar',

        'status_title' => 'Status da Indicação',
        'status_enviado' => 'Indicação enviada ao SERPRO',
        'status_processando' => 'Em análise pelo órgão',
        'status_aceito' => 'Indicação aceita com sucesso',
        'status_rejeitado' => 'Indicação recusada pelo órgão',
        'status_cancelado' => 'Cancelada pela locadora',
        'status_expirado' => 'Prazo de indicação venceu',

        'important_title' => 'Informações Importantes',
        'important_1' => 'A indicação de Real Infrator so funciona para multas importadas do SERPRO (não para multas cadastradas manualmente)',
        'important_2' => 'O CNPJ da empresa deve estar configurado em Configurações SERPRO antes de realizar indicações',
        'important_3' => 'Após o envio, use o botão de sincronização para consultar o status atualizado no SERPRO',
        'important_4' => 'Indicações com status "Enviado" ou "Processando" podem ser canceladas',
        'important_5' => 'Respeite os prazos legais: a indicação deve ser feita dentro do prazo estipulado na notificação',

        'steps_title' => 'Passo a Passo',
        'step_1' => 'Clique em "Nova Indicação"',
        'step_2' => 'Escolha o tipo: Real Infrator ou Principal Condutor',
        'step_3' => 'Para Real Infrator: selecione a multa e informe o CPF do condutor',
        'step_4' => 'Para Principal Condutor: informe a placa, CPF e CNH do condutor',
        'step_5' => 'Clique em "Enviar Indicação"',
        'step_6' => 'Acompanhe o status na tabela e use o botão de sincronização para atualizar',

        'label_when' => 'Quando usar:',
        'label_prereq' => 'Pre-requisito:',
        'label_fields' => 'Campos necessarios:',
        'label_result' => 'Resultado:',
        'label_advantage' => 'Vantagem:',
        'label_important' => 'Importante:',
    ],
];
