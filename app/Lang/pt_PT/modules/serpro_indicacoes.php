<?php

return [
    'instructions' => [
        'button' => 'Instruções',
        'title' => 'Como Funciona a Indicação de Condutor',
        'what_is_title' => 'O que e a Indicação de Condutor?',
        'what_is_text' => 'Quando um veículo da frota recebe uma multa, a autuação vem em nome da empresa (proprietaria). A indicação de condutor e o processo legal de transferir a responsabilidade da infração para a pessoa que realmente estava a conduzir no momento.',

        'real_infrator_title' => 'Tipo 1: Infrator Real',
        'real_infrator_desc' => 'Utilizado para transferir os pontos e a responsabilidade de uma multa específica para quem realmente cometeu a infração.',
        'real_infrator_when' => 'Ao receber uma multa e identificar quem estava a conduzir',
        'real_infrator_prereq' => 'A multa precisa de ter sido importada via SERPRO (com código do órgão, AIT e código da infração)',
        'real_infrator_fields' => 'Selecionar a multa + CPF do condutor',
        'real_infrator_result' => 'Os pontos da infração sao transferidos para a carta de condução do indicado',

        'principal_title' => 'Tipo 2: Condutor Principal',
        'principal_desc' => 'Utilizado para registar o motorista habitual de um veículo. Não está vinculado a uma multa específica.',
        'principal_when' => 'Ao alugar um veículo a um cliente, regista-lo como condutor principal',
        'principal_advantage' => 'Futuras multas deste veículo serão automaticamente direcionadas ao condutor registado',
        'principal_fields' => 'Matrícula do veículo + CPF + carta de condução',
        'principal_important' => 'Pode ser removido quando a locação terminar',

        'status_title' => 'Estado da Indicação',
        'status_enviado' => 'Indicação enviada ao SERPRO',
        'status_processando' => 'Em análise pelo órgão',
        'status_aceito' => 'Indicação aceite com sucesso',
        'status_rejeitado' => 'Indicação recusada pelo órgão',
        'status_cancelado' => 'Cancelada pela empresa de aluguer',
        'status_expirado' => 'Prazo de indicação expirou',

        'important_title' => 'Informações Importantes',
        'important_1' => 'A indicação de Infrator Real so funciona para multas importadas do SERPRO (não para multas registadas manualmente)',
        'important_2' => 'O CNPJ da empresa deve estar configurado nas Configurações SERPRO antes de realizar indicações',
        'important_3' => 'Após o envio, utilize o botão de sincronização para consultar o estado atualizado no SERPRO',
        'important_4' => 'Indicações com estado "Enviado" ou "Em processamento" podem ser canceladas',
        'important_5' => 'Respeite os prazos legais: a indicação deve ser feita dentro do prazo estipulado na notificação',

        'steps_title' => 'Passo a Passo',
        'step_1' => 'Clique em "Nova Indicação"',
        'step_2' => 'Escolha o tipo: Infrator Real ou Condutor Principal',
        'step_3' => 'Para Infrator Real: selecione a multa e informe o CPF do condutor',
        'step_4' => 'Para Condutor Principal: informe a matrícula, CPF e carta de condução',
        'step_5' => 'Clique em "Enviar Indicação"',
        'step_6' => 'Acompanhe o estado na tabela e utilize o botão de sincronização para atualizar',

        'label_when' => 'Quando usar:',
        'label_prereq' => 'Pre-requisito:',
        'label_fields' => 'Campos necessarios:',
        'label_result' => 'Resultado:',
        'label_advantage' => 'Vantagem:',
        'label_important' => 'Importante:',
    ],
];
