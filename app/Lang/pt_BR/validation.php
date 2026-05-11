<?php

/**
 * Mensagens de validação - Português (Brasil)
 *
 * Mensagens de erro para validação de formulários.
 * Use :attribute para o nome do campo e outros placeholders conforme a regra.
 */

return [
    // Regras de validação gerais
    'required' => 'O campo :attribute é obrigatório.',
    'required_if' => 'O campo :attribute é obrigatório quando :other é :value.',
    'required_with' => 'O campo :attribute é obrigatório quando :values está presente.',
    'required_without' => 'O campo :attribute é obrigatório quando :values não está presente.',
    'filled' => 'O campo :attribute deve ter um valor.',
    'present' => 'O campo :attribute deve estar presente.',
    'nullable' => 'O campo :attribute pode ser nulo.',

    // Tipos
    'string' => 'O campo :attribute deve ser um texto.',
    'numeric' => 'O campo :attribute deve ser um número.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'decimal' => 'O campo :attribute deve ser um número decimal.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'json' => 'O campo :attribute deve ser um JSON válido.',

    // Tamanho
    'min' => [
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
    ],
    'max' => [
        'string' => 'O campo :attribute deve ter no máximo :max caracteres.',
        'numeric' => 'O campo :attribute deve ser no máximo :max.',
        'array' => 'O campo :attribute deve ter no máximo :max itens.',
        'file' => 'O arquivo :attribute deve ter no máximo :max kilobytes.',
    ],
    'size' => [
        'string' => 'O campo :attribute deve ter exatamente :size caracteres.',
        'numeric' => 'O campo :attribute deve ser :size.',
        'array' => 'O campo :attribute deve ter exatamente :size itens.',
        'file' => 'O arquivo :attribute deve ter :size kilobytes.',
    ],
    'between' => [
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'array' => 'O campo :attribute deve ter entre :min e :max itens.',
        'file' => 'O arquivo :attribute deve ter entre :min e :max kilobytes.',
    ],
    'length' => 'O campo :attribute deve ter exatamente :length caracteres.',
    'digits' => 'O campo :attribute deve ter :digits dígitos.',
    'digits_between' => 'O campo :attribute deve ter entre :min e :max dígitos.',

    // Formato
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'url' => 'O campo :attribute deve ser uma URL válida.',
    'ip' => 'O campo :attribute deve ser um endereço IP válido.',
    'ipv4' => 'O campo :attribute deve ser um endereço IPv4 válido.',
    'ipv6' => 'O campo :attribute deve ser um endereço IPv6 válido.',
    'uuid' => 'O campo :attribute deve ser um UUID válido.',
    'alpha' => 'O campo :attribute deve conter apenas letras.',
    'alpha_num' => 'O campo :attribute deve conter apenas letras e números.',
    'alpha_dash' => 'O campo :attribute deve conter apenas letras, números, hífens e underscores.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'not_regex' => 'O formato do campo :attribute é inválido.',

    // Confirmação/Comparação
    'confirmed' => 'A confirmação do campo :attribute não confere.',
    'same' => 'Os campos :attribute e :other devem ser iguais.',
    'different' => 'Os campos :attribute e :other devem ser diferentes.',
    'gt' => [
        'numeric' => 'O campo :attribute deve ser maior que :value.',
        'string' => 'O campo :attribute deve ter mais de :value caracteres.',
    ],
    'gte' => [
        'numeric' => 'O campo :attribute deve ser maior ou igual a :value.',
        'string' => 'O campo :attribute deve ter no mínimo :value caracteres.',
    ],
    'lt' => [
        'numeric' => 'O campo :attribute deve ser menor que :value.',
        'string' => 'O campo :attribute deve ter menos de :value caracteres.',
    ],
    'lte' => [
        'numeric' => 'O campo :attribute deve ser menor ou igual a :value.',
        'string' => 'O campo :attribute deve ter no máximo :value caracteres.',
    ],

    // Valores
    'in' => 'O valor selecionado para :attribute é inválido.',
    'not_in' => 'O valor selecionado para :attribute é inválido.',
    'accepted' => 'O campo :attribute deve ser aceito.',
    'accepted_if' => 'O campo :attribute deve ser aceito quando :other é :value.',
    'declined' => 'O campo :attribute deve ser recusado.',

    // Datas
    'date' => 'O campo :attribute deve ser uma data válida.',
    'date_format' => 'O campo :attribute deve estar no formato :format.',
    'before' => 'O campo :attribute deve ser uma data anterior a :date.',
    'before_or_equal' => 'O campo :attribute deve ser uma data anterior ou igual a :date.',
    'after' => 'O campo :attribute deve ser uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute deve ser uma data posterior ou igual a :date.',
    'date_equals' => 'O campo :attribute deve ser igual a :date.',
    'timezone' => 'O campo :attribute deve ser um fuso horário válido.',

    // Arquivos
    'file' => 'O campo :attribute deve ser um arquivo.',
    'image' => 'O campo :attribute deve ser uma imagem.',
    'mimes' => 'O campo :attribute deve ser um arquivo do tipo: :values.',
    'mimetypes' => 'O campo :attribute deve ser um arquivo do tipo: :values.',
    'dimensions' => 'O campo :attribute tem dimensões de imagem inválidas.',
    'uploaded' => 'O upload do campo :attribute falhou.',

    // Banco de dados
    'exists' => 'O :attribute selecionado é inválido.',
    'unique' => 'O :attribute informado já está em uso.',

    // Senhas
    'password' => [
        'min' => 'A senha deve ter pelo menos :min caracteres.',
        'mixed' => 'A senha deve conter letras maiúsculas e minúsculas.',
        'letters' => 'A senha deve conter pelo menos uma letra.',
        'numbers' => 'A senha deve conter pelo menos um número.',
        'symbols' => 'A senha deve conter pelo menos um símbolo.',
        'uncompromised' => 'Esta senha foi exposta em vazamento de dados. Escolha outra.',
    ],
    'current_password' => 'A senha atual está incorreta.',

    // Documentos brasileiros
    'cpf' => 'O campo :attribute deve ser um CPF válido.',
    'cnpj' => 'O campo :attribute deve ser um CNPJ válido.',
    'cpf_cnpj' => 'O campo :attribute deve ser um CPF ou CNPJ válido.',
    'cnh' => 'O campo :attribute deve ser uma CNH válida.',
    'rg' => 'O campo :attribute deve ser um RG válido.',
    'cep' => 'O campo :attribute deve ser um CEP válido.',
    'telefone' => 'O campo :attribute deve ser um telefone válido.',
    'celular' => 'O campo :attribute deve ser um celular válido.',
    'placa' => 'O campo :attribute deve ser uma placa de veículo válida.',

    // Cartão de crédito
    'credit_card' => 'O campo :attribute deve ser um número de cartão válido.',
    'credit_card_expiration' => 'A data de validade do cartão é inválida.',
    'credit_card_cvv' => 'O código de segurança do cartão é inválido.',

    // Mensagem customizada para atributos
    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmação de senha',
        'phone' => 'telefone',
        'mobile' => 'celular',
        'address' => 'endereço',
        'city' => 'cidade',
        'state' => 'estado',
        'country' => 'país',
        'zip_code' => 'CEP',
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'cpf_cnpj' => 'CPF/CNPJ',
        'rg' => 'RG',
        'cnh' => 'CNH',
        'birth_date' => 'data de nascimento',
        'start_date' => 'data de início',
        'end_date' => 'data de término',
        'value' => 'valor',
        'amount' => 'quantia',
        'quantity' => 'quantidade',
        'description' => 'descrição',
        'observations' => 'observações',
        'title' => 'título',
        'content' => 'conteúdo',
        'message' => 'mensagem',
        'subject' => 'assunto',
        'file' => 'arquivo',
        'image' => 'imagem',
        'document' => 'documento',
        'terms' => 'termos de uso',
        'privacy' => 'política de privacidade',
    ],
];
