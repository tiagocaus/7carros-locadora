<?php
/**
 * Conteúdo padrão das seções editáveis do site público.
 * Estrutura: [pagina][secao] => conteudo (string HTML).
 *
 * Aplicado quando o tenant ativa o site pela primeira vez (via WebsiteSeedService).
 * Depois o cliente edita via backoffice "Website > Conteúdos".
 */
return [
    'inicio' => [
        // Formulário de reserva
        'titulo_reserva' => 'Faça sua reserva online',

        // "Por que nos escolher?" — 4 cards (título + texto editáveis; ícone e layout no template)
        'por_que_titulo' => 'Por que nos escolher?',
        'por_que_1_titulo' => 'Atendimento Exclusivo',
        'por_que_1_texto'  => 'Estaremos prontos para melhor atendê-lo a qualquer hora que precisar. Fornecemos uma assistência 24 horas.',
        'por_que_2_titulo' => 'Descontos progressivos',
        'por_que_2_texto'  => 'Quanto mais você usa, menos você paga! Contamos com um desconto progressivo, calculado com base nos dias de aluguel.',
        'por_que_3_titulo' => 'Carros novos e bem cuidados',
        'por_que_3_texto'  => 'Contamos com uma frota sempre atualizada e conservada, equipados com motores flex para a escolha do melhor custo benefício, além de manutenção periódica.',
        'por_que_4_titulo' => 'Entrega dinâmica',
        'por_que_4_texto'  => 'Combinamos a melhor forma para entregarmos ou recebermos o veículo.',

        // Grupos de veículos
        'grupos_titulo' => 'Grupos de veículos',

        // "Nossos diferenciais" — 8 itens em 2 colunas
        'diferenciais_titulo' => 'Nossos diferenciais',
        'diferencial_esq_1' => 'Tenha maior autonomia e menos custos.',
        'diferencial_esq_2' => 'Tire proveito de vários postos de devolução.',
        'diferencial_esq_3' => 'Alugue uma categoria de carro para cada tipo de viagem.',
        'diferencial_esq_4' => 'Escolha a melhor opção de quilometragem.',
        'diferencial_dir_1' => 'Sem burocracia para alugar, processo rápido e simples.',
        'diferencial_dir_2' => 'Veículos com seguro e assistência 24 horas inclusa.',
        'diferencial_dir_3' => 'Atendimento personalizado para pessoa física e jurídica.',
        'diferencial_dir_4' => 'Condições especiais para locações de longa duração.',
    ],

    'sobre' => [
        'titulo'    => 'Sobre a empresa',
        'subtitulo' => 'Título',
        'texto'     => '<p>Lorem ipsum dolor, sit amet consectetur adipisicing elit. Possimus laborum odio quam.</p>',
    ],

    'veiculos' => [
        'titulo' => 'Nossos grupos de veículos',
        'texto'  => '',
    ],

    'contato' => [
        'titulo' => 'Fale conosco',
        'texto'  => '<p>Entre em contato através dos canais abaixo ou preencha o formulário para que nossa equipe retorne o mais breve possível.</p>',
    ],

    'reserva' => [
        'passo_1_titulo' => 'Local e data',
        'passo_2_titulo' => 'Escolha seu veículo',
        'passo_3_titulo' => 'Serviços adicionais',
        'passo_4_titulo' => 'Pré-cadastro',
        'passo_4_texto'  => 'Preencha os dados abaixo para finalizar sua reserva.',
        'passo_5_titulo' => 'Concluído',
        'passo_5_texto'  => 'Sua pré-reserva foi finalizada com sucesso!',
    ],

    // Conteúdos compartilhados entre todas as páginas
    'global' => [
        // Barra info (4 cards — ícone e layout no template)
        'barra_info_atendimento_titulo' => 'Atendimento',
        'barra_info_atendimento_texto'  => '+55 (99) 123-4560',
        'barra_info_whatsapp_titulo'    => 'WhatsApp',
        'barra_info_whatsapp_texto'     => '+55 (99) 9.9999-9999',
        'barra_info_assistencia_titulo' => 'Assistência 24h',
        'barra_info_assistencia_texto'  => '+55 (99) 4444-5555',
        'barra_info_horario_titulo'     => 'Horário',
        'barra_info_horario_texto'      => 'Seg-Sex 08:00-18:00<br>Sábado 08:00-12:00',

        // Footer
        'footer_empresa' => 'SUA LOCADORA<br>00000000/0000-00<br>Rua Bem Ali, 0<br>Ataíde - Vila Velha - ES - Brasil',
    ],
];
