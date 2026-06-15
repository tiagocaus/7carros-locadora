<?php

$base = require __DIR__ . '/../../pt_BR/modules/layout.php';

$map = [
    'Usuário' => 'Utilizador',
    'Gravações de Tela' => 'Gravações de Ecrã',
    'Gravar tela' => 'Gravar ecrã',
    'Confirmar exclusão' => 'Confirmar eliminação',
    'Deseja realmente excluir este registro?' => 'Deseja realmente eliminar este registo?',
    'este registro' => 'este registo',
    'Deseja realmente excluir o :type (:name)?' => 'Deseja realmente eliminar o :type (:name)?',
    'Confirmar exclusão' => 'Confirmar eliminação',
    'Campos obrigatórios' => 'Campos obrigatórios',
    'Recarga via cartão' => 'Carregamento por cartão',
    'Valor da recarga' => 'Valor do carregamento',
    'Salvar cartão para auto-recarga' => 'Guardar cartão para carregamento automático',
    'Pagar com cartão' => 'Pagar com cartão',
    'Recarga confirmada!' => 'Carregamento confirmado!',
    'Link de pagamento' => 'Link de pagamento',
    'Link de assinatura' => 'Link de assinatura',
    'Sessão expirada' => 'Sessão expirada',
    'Não foi possível renovar sua sessão automaticamente. Recarregue a página para continuar.' => 'Não foi possível renovar a sua sessão automaticamente. Recarregue a página para continuar.',
    'Recarregar página' => 'Recarregar página',
    'Visualizar gravação' => 'Ver gravação',
    'Consultar multas online' => 'Consultar multas online',
    'Placa do veículo' => 'Matrícula do veículo',
    'Consultar infrações' => 'Consultar infrações',
    'Consulta em lote' => 'Consulta em lote',
    'Buscar clientes, veículos, locações...' => 'Procurar clientes, veículos, alugueres...',
    'Locação' => 'Aluguer',
    'Novo local' => 'Novo local',
    'Editar local' => 'Editar local',
    'Salvar local' => 'Guardar local',
    'Câmera' => 'Câmara',
    'Carregando câmeras...' => 'A carregar câmaras...',
    'Nenhuma câmera encontrada' => 'Nenhuma câmara encontrada',
    'Seu navegador não suporta acesso à câmera. Use a opção de enviar arquivo.' => 'O seu navegador não suporta acesso à câmara. Use a opção de enviar ficheiro.',
    'Permissão de acesso à câmera negada. Por favor, permita o acesso e tente novamente.' => 'Permissão de acesso à câmara negada. Permita o acesso e tente novamente.',
    'Permissão de acesso à câmera negada.' => 'Permissão de acesso à câmara negada.',
    'Nenhuma câmera encontrada. Use a opção de enviar arquivo.' => 'Nenhuma câmara encontrada. Use a opção de enviar ficheiro.',
    'Aguarde a câmera inicializar completamente.' => 'Aguarde até a câmara inicializar completamente.',
    'Erro ao acessar câmeras: :message' => 'Erro ao aceder às câmaras: :message',
    'Erro ao iniciar câmera: :message' => 'Erro ao iniciar câmara: :message',
    'Enviar arquivo' => 'Enviar ficheiro',
    'Importar arquivo' => 'Importar ficheiro',
    'Arraste o arquivo aqui' => 'Arraste o ficheiro para aqui',
    'Arquivo muito grande (máximo 10MB)' => 'Ficheiro demasiado grande (máximo 10MB)',
    'Erro ao enviar arquivo. Tente novamente.' => 'Erro ao enviar ficheiro. Tente novamente.',
];

$translate = function ($value) use (&$translate, $map) {
    if (is_array($value)) return array_map($translate, $value);
    return is_string($value) && array_key_exists($value, $map) ? $map[$value] : $value;
};

return $translate($base);
