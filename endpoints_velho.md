# Endpoints do app antigo

Este arquivo documenta os endpoints consumidos pelo app.

## Configuracao geral

- Base URL principal: `https://api.locadora.7carros.com/v2`
- Metodo usado na API principal: `POST`
- Header global enviado pelo `axiosInstance`:
  - `token: aUeDzslldDg1S0JK9rttg32yLjdxOh0ErBaoYMRFhP6ea68M3mmQPbZ5byHi6eyc`
- Header por acao:
  - `xAcesso: <acao>`
- Regra de autenticacao no body:
  - Por padrao, o app injeta `chave` no payload quando existe uma chave salva no usuario logado ou no storage.
  - Excecao conhecida: recuperacao de senha envia `{ withKey: false }` e nao injeta `chave`.
- Tratamento global de erro:
  - Se a resposta tiver `retorno === 0`, o interceptor lanca erro usando `erro`, `msg` ou `"Error on request"`.

## Resumo

| Metodo | Endpoint | xAcesso | Finalidade |
| --- | --- | --- | --- |
| POST | `/usuarios.php` | `login` | Login e verificacao de usuario |
| POST | `/clientes.php` | `gerarNovaSenha` | Recuperacao de senha |
| POST | `/checklist.php` | `checklistsAvulsoAdicionar` | Criar checklist avulso |
| POST | `/checklist.php` | `checklistsAvulsoAdicionarFotos` | Enviar fotos/vistorias do checklist avulso |
| POST | `/checklist.php` | `listar` | Listar checklists avulsos |
| POST | `/checklist.php` | `ver` | Ver detalhes de checklist |
| POST | `/checklist.php` | `modelos` | Listar modelos de checklist |
| POST | `/veiculos.php` | `listar` | Listar veiculos |
| POST | `/checklist.php` | `uploadVinculadoSaida` | Salvar checklist vinculado de saida |
| POST | `/checklist.php` | `uploadVinculadoChegada` | Salvar checklist vinculado de chegada |
| POST | `/checklist.php` | `listagemVinculado` | Listar checklists vinculados |
| POST | `/checklist.php` | `uploadVinculadoSaidaFotos` | Enviar fotos da saida vinculada |
| POST | `/checklist.php` | `uploadVinculadoChegadaFotos` | Enviar fotos da chegada vinculada |
| POST | `/appcliente.dadosiniciais.php` | `ver` | Buscar dados iniciais da home |
| POST | `/assinarDocumento.php` | `listar` | Listar documentos para assinatura |
| POST | `/assinarDocumento.php` | `adicionar` | Salvar assinatura de documento |
| POST | `/matrizfiliais.php` | `ver` | Ver matriz/filial |
| POST | `/matrizfiliais.php` | `listar` | Listar matriz/filiais |
| POST | `/matrizfiliais.php` | `adicionar` | Criar matriz/filial |
| POST | `/matrizfiliais.php` | `editar` | Editar matriz/filial |
| POST | `/matrizfiliais.php` | `apagar` | Apagar matriz/filial |
| POST | `ultimasAtualizacoes.php` | `listar` | Listar changelog |
| GET | `https://viacep.com.br/ws/{cep}/json/?callback=?` | N/A | Buscar endereco por CEP |

## Autenticacao

### Login

- Metodo: `POST`
- Endpoint: `/usuarios.php`
- Header `xAcesso`: `login`
- Origem: `services/Authentication/Authentication.ts`

Envia:

```json
{
  "usuario": "email ou usuario",
  "senha": "senha",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
[
  {
    "chave": "string",
    "id": "string",
    "id_matrizFilial": "string",
    "msg": "string",
    "nome": "string",
    "nome_matrizFilial": "string",
    "plano": "string",
    "retorno": 1,
    "permissoes": "string"
  }
]
```

O app considera erro quando `retorno === 0`.

### Verificar usuario

- Metodo: `POST`
- Endpoint: `/usuarios.php`
- Header `xAcesso`: `login`
- Origem: `services/Authentication/Authentication.ts`

Envia:

```json
{
  "usuario": "email ou usuario",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
[
  {
    "retorno": 1,
    "msg": "string opcional"
  }
]
```

### Recuperar senha

- Metodo: `POST`
- Endpoint: `/clientes.php`
- Header `xAcesso`: `gerarNovaSenha`
- Origem: `services/Authentication/Authentication.ts`
- Observacao: nao injeta `chave`.

Envia:

```json
{
  "email_cpfcnpj": "email, CPF ou CNPJ"
}
```

Recebe:

```json
[
  {
    "retorno": 1,
    "msg": "string opcional"
  }
]
```

O app considera sucesso apenas quando `data[0].retorno === 1`.

## Checklist avulso

### Criar checklist avulso

- Metodo: `POST`
- Endpoint: `/checklist.php`
- Header `xAcesso`: `checklistsAvulsoAdicionar`
- Origem: `services/Checklist/ChecklistService.ts`

Envia:

```json
{
  "info": {
    "id_modelo": "string",
    "odometro_atual": "string opcional",
    "tanque_atual": "string opcional",
    "id_veiculo": "string"
  },
  "assinatura": "base64 ou string da assinatura",
  "questoes": [
    {
      "id": 1,
      "content": "string",
      "opt": 1
    }
  ],
  "tipo": "V ou A",
  "id_usuario": "string",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "retorno": 1,
  "erro": "string opcional",
  "codigo": "string",
  "id": 123
}
```

### Enviar fotos/vistorias do checklist avulso

- Metodo: `POST`
- Endpoint: `/checklist.php`
- Header `xAcesso`: `checklistsAvulsoAdicionarFotos`
- Origem: `services/Checklist/ChecklistService.ts`

Envia:

```json
{
  "id": "string",
  "codigo": "string",
  "vistorias": [
    {
      "id": "number ou string",
      "content": "string",
      "img": "base64 opcional",
      "done": true
    }
  ],
  "quantidade": 10,
  "falta": 0,
  "fim": true,
  "inicio_intervalo": 0,
  "fim_intervalo": 9,
  "id_usuario": "string",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "retorno": 1
}
```

### Listar checklists avulsos

- Metodo: `POST`
- Endpoint: `/checklist.php`
- Header `xAcesso`: `listar`
- Origem: `services/Checklist/ChecklistService.ts`

Envia:

```json
{
  "pagina": 1,
  "busca": "texto opcional",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "dados": [
    {
      "id": "string",
      "codigo": "string",
      "data_chegada": "string",
      "data_saida": "string",
      "modelo": "string",
      "status": "string",
      "tipo": "A ou V",
      "veiculo": "string"
    }
  ],
  "meta": {
    "pagina_atual": 1,
    "registros_por_pagina": 20,
    "tem_mais_paginas": false,
    "total_registros": 0
  }
}
```

O app remove duplicados pelo campo `codigo`.

### Ver detalhe de checklist

- Metodo: `POST`
- Endpoint: `/checklist.php`
- Header `xAcesso`: `ver`
- Origem: `services/Checklist/ChecklistService.ts`

Envia:

```json
{
  "id": "string",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
[
  {
    "id": "string",
    "codigo": "string",
    "data_chegada": "string",
    "data_saida": "string",
    "modelo": "string",
    "status": "string",
    "tipo": "string",
    "veiculo": "string",
    "obs": "string",
    "questoes_chegada": "JSON string",
    "questoes_saida": "JSON string",
    "assinatura_chegada": "string",
    "assinatura_saida": "string",
    "vistoria_chegada": "JSON string",
    "vistoria_saida": "JSON string"
  }
]
```

No app, `questoes_chegada`, `questoes_saida`, `vistoria_chegada` e `vistoria_saida` sao convertidos de JSON string para arrays.

## Checklist vinculado

### Salvar checklist vinculado de saida

- Metodo: `POST`
- Endpoint: `/checklist.php`
- Header `xAcesso`: `uploadVinculadoSaida`
- Origem: `services/Checklist/LinkedChecklistService.ts`

Envia:

```json
{
  "codigo": "string",
  "id_modelo": "string",
  "id_veiculo": "string",
  "questoes_saida": [
    {
      "id": 1,
      "content": "string",
      "opt": 1
    }
  ],
  "assinatura": "base64 ou string da assinatura",
  "obs": "string",
  "id_usuario": "string",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "retorno": 1,
  "erro": "string opcional",
  "codigo": "string",
  "id": 123
}
```

### Salvar checklist vinculado de chegada

- Metodo: `POST`
- Endpoint: `/checklist.php`
- Header `xAcesso`: `uploadVinculadoChegada`
- Origem: `services/Checklist/LinkedChecklistService.ts`

Envia:

```json
{
  "codigo": "string",
  "id_veiculo": "string",
  "id_usuario": "string",
  "questoes_chegada": [
    {
      "id": 1,
      "content": "string",
      "opt": 1
    }
  ],
  "assinatura_chegada": "base64 ou string da assinatura",
  "obs_chegada": "string",
  "tanque_chegada": "string",
  "odometro_chegada": "string",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "retorno": 1,
  "erro": "string opcional",
  "codigo": "string",
  "id": 123
}
```

### Listar checklists vinculados

- Metodo: `POST`
- Endpoint: `/checklist.php`
- Header `xAcesso`: `listagemVinculado`
- Origem: `services/Checklist/LinkedChecklistService.ts`

Envia:

```json
{
  "situacao": "saida ou chegada",
  "pagina": "string",
  "placa": "string opcional",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "dados": [
    {
      "checklist_id": "string",
      "codigo": "string",
      "modelo": "string",
      "placa": "string",
      "status": "string",
      "checklist_status": "string",
      "tipo": "string",
      "id_modelo": "string"
    }
  ],
  "meta": {
    "pagina_atual": 1,
    "registros_por_pagina": 20,
    "tem_mais_paginas": false,
    "total_registros": 0
  }
}
```

O app remove duplicados pelo campo `codigo`.

### Enviar fotos de saida vinculada

- Metodo: `POST`
- Endpoint: `/checklist.php`
- Header `xAcesso`: `uploadVinculadoSaidaFotos`
- Origem: `services/Checklist/LinkedChecklistService.ts`

Envia:

```json
{
  "id": "string",
  "codigo": "string",
  "vistoria_saida": [
    {
      "id": "number ou string",
      "content": "string",
      "img": "base64 opcional",
      "done": true
    }
  ],
  "quantidade": 10,
  "falta": 0,
  "fim": true,
  "inicio_intervalo": 0,
  "fim_intervalo": 9,
  "id_usuario": "string",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "retorno": 1
}
```

### Enviar fotos de chegada vinculada

- Metodo: `POST`
- Endpoint: `/checklist.php`
- Header `xAcesso`: `uploadVinculadoChegadaFotos`
- Origem: `services/Checklist/LinkedChecklistService.ts`

Envia:

```json
{
  "id": "string",
  "codigo": "string",
  "vistoria_chegada": [
    {
      "id": "number ou string",
      "content": "string",
      "img": "base64 opcional",
      "done": true
    }
  ],
  "quantidade": 10,
  "falta": 0,
  "fim": true,
  "inicio_intervalo": 0,
  "fim_intervalo": 9,
  "id_usuario": "string",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "retorno": 1
}
```

## Veiculos e modelos

### Listar veiculos

- Metodo: `POST`
- Endpoint: `/veiculos.php`
- Header `xAcesso`: `listar`
- Origem: `services/Checklist/ChecklistService.ts`

Envia:

```json
{
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
[
  {
    "id": "string",
    "placa": "string",
    "descricao": "string",
    "marca": "string",
    "modelo": "string",
    "ano": "string",
    "cor": "string",
    "odometro": "string",
    "tanque_fracao": "string",
    "tanque_litros": "string"
  }
]
```

O tipo `Vehicle` tem outros campos retornados pela API, como `chassi`, `renavam`, `foto`, `grupo`, `disponibilidade`, `localizacao`, `tipo_combustivel`, `transmissao`, valores de compra/venda e dados de fornecedor.

### Listar modelos de checklist

- Metodo: `POST`
- Endpoint: `/checklist.php`
- Header `xAcesso`: `modelos`
- Origem: `services/Checklist/ChecklistService.ts`

Envia:

```json
{
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
[
  {
    "id": "string",
    "nome": "string",
    "questoes": "JSON string",
    "vistoria": "JSON string"
  }
]
```

No app, `questoes` e `vistoria` sao parseados para arrays quando usados.

## Matriz/Filial

### Ver matriz/filial

- Metodo: `POST`
- Endpoint: `/matrizfiliais.php`
- Header `xAcesso`: `ver`
- Origem: `services/MatrizFilial/MatrizFilial.ts`

Envia:

```json
{
  "id": "string",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
[
  {
    "id": "string",
    "logo": "string",
    "tipo": "string",
    "empresa": "string",
    "cnpj": "string",
    "ins_muni": "string",
    "ins_esta": null,
    "cep": "string",
    "rua": "string",
    "num": "string",
    "compl": "string",
    "bairro": "string",
    "cidade": "string",
    "estado": "string",
    "pais": "string",
    "fixo": "string ou null",
    "fax": "string ou null",
    "email": "string",
    "site": "string ou null",
    "celular": "string ou null",
    "dias_uteis": "string",
    "hora_ini": "string",
    "hora_fim": "string",
    "hora_ini_f": "string",
    "hora_fim_f": "string",
    "hora_ini_sd": "string",
    "hora_fim_sd": "string",
    "assinatura": "string"
  }
]
```

### Listar matriz/filiais

- Metodo: `POST`
- Endpoint: `/matrizfiliais.php`
- Header `xAcesso`: `listar`
- Origem: `services/MatrizFilial/MatrizFilial.ts`

Envia:

```json
{
  "pagina": 1,
  "busca": "",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "dados": [
    {
      "id": "string",
      "empresa": "string",
      "cnpj": "string"
    }
  ],
  "meta": {
    "pagina_atual": 1,
    "registros_por_pagina": 20,
    "tem_mais_paginas": false,
    "total_registros": 0
  }
}
```

### Criar matriz/filial

- Metodo: `POST`
- Endpoint: `/matrizfiliais.php`
- Header `xAcesso`: `adicionar`
- Origem: `services/MatrizFilial/MatrizFilial.ts`

Envia:

```json
{
  "tipo": "string",
  "empresa": "string",
  "cnpj": "string",
  "ins_muni": "string",
  "ins_esta": "string",
  "cep": "string",
  "rua": "string",
  "num": "string",
  "compl": "string opcional",
  "bairro": "string",
  "cidade": "string",
  "estado": "string",
  "pais": "string",
  "fixo": "string",
  "celular": "string",
  "email": "string",
  "site": "string opcional",
  "dias_uteis": ["string"],
  "hora_ini": "string",
  "hora_fim": "string",
  "hora_ini_sd": "string",
  "hora_fim_sd": "string",
  "hora_ini_f": "string",
  "hora_fim_f": "string",
  "assinatura": "string",
  "id_usuario": "string opcional",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "retorno": 1
}
```

Antes de enviar, o app valida campos obrigatorios. `compl`, `site` e `dias_uteis` nao entram nessa validacao obrigatoria.

### Editar matriz/filial

- Metodo: `POST`
- Endpoint: `/matrizfiliais.php`
- Header `xAcesso`: `editar`
- Origem: `services/MatrizFilial/MatrizFilial.ts`

Envia o mesmo formato de criacao de matriz/filial:

```json
{
  "tipo": "string",
  "empresa": "string",
  "cnpj": "string",
  "ins_muni": "string",
  "ins_esta": "string",
  "cep": "string",
  "rua": "string",
  "num": "string",
  "compl": "string opcional",
  "bairro": "string",
  "cidade": "string",
  "estado": "string",
  "pais": "string",
  "fixo": "string",
  "celular": "string",
  "email": "string",
  "site": "string opcional",
  "dias_uteis": ["string"],
  "hora_ini": "string",
  "hora_fim": "string",
  "hora_ini_sd": "string",
  "hora_fim_sd": "string",
  "hora_ini_f": "string",
  "hora_fim_f": "string",
  "assinatura": "string",
  "id_usuario": "string opcional",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "retorno": 1
}
```

### Apagar matriz/filial

- Metodo: `POST`
- Endpoint: `/matrizfiliais.php`
- Header `xAcesso`: `apagar`
- Origem: `services/MatrizFilial/MatrizFilial.ts`

Envia:

```json
{
  "id": "string",
  "empresa": "string",
  "logo": "string",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "retorno": 1
}
```

## Assinatura de documentos

### Listar documentos para assinatura

- Metodo: `POST`
- Endpoint: `/assinarDocumento.php`
- Header `xAcesso`: `listar`
- Origem: `services/Signature/Signature.ts`

Envia:

```json
{
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
[
  {
    "id": "string",
    "tipo": "string",
    "codigo": "string",
    "cliente": "string",
    "veiculo": "string",
    "datas": "string",
    "assinatura": "string ou null"
  }
]
```

### Salvar assinatura de documento

- Metodo: `POST`
- Endpoint: `/assinarDocumento.php`
- Header `xAcesso`: `adicionar`
- Origem: `services/Signature/Signature.ts`

Envia:

```json
{
  "id_usuario": "string",
  "codigo": "string",
  "assinatura": "base64 ou string da assinatura",
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
{
  "retorno": 1
}
```

O app retorna a resposta bruta da API.

## Home/Dashboard

### Buscar dados iniciais

- Metodo: `POST`
- Endpoint: `/appcliente.dadosiniciais.php`
- Header `xAcesso`: `ver`
- Origem: `services/HomeInfos/HomeInfos.ts`

Envia:

```json
{
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
[
  {
    "veiculos_disponiveis": 0,
    "veiculos_locados": 0,
    "veiculos_reservados": 0,
    "veiculos_oficina": 0,
    "clientes_qtd": 0,
    "financeiro_vencidas_valor": "string",
    "financeiro_avencer_valor": "string"
  }
]
```

O app usa o primeiro item do array.

## Changelog

### Listar ultimas atualizacoes

- Metodo: `POST`
- Endpoint: `ultimasAtualizacoes.php`
- Header `xAcesso`: `listar`
- Origem: `app/(app)/(drawer)/changelog.tsx`

Envia:

```json
{
  "chave": "injetada automaticamente quando existir"
}
```

Recebe:

```json
[
  {
    "versao": "string",
    "atualizacoes": [
      {
        "tipo": "Novo, Aprimorado ou Correcao",
        "mensagem": "string"
      }
    ]
  }
]
```

## Servicos externos

### Buscar endereco por CEP

- Metodo: `GET`
- Endpoint: `https://viacep.com.br/ws/{cep}/json/?callback=?`
- Origem: `app/(app)/(drawer)/matriz-filial/(tabs)/address.tsx`

Envia:

- `cep` na URL, somente numeros, com 8 digitos.

Recebe uma string JSONP, que o app limpa com regex antes de fazer `JSON.parse`:

```json
{
  "erro": "string opcional",
  "bairro": "string",
  "cep": "string",
  "complemento": "string",
  "ddd": "string",
  "estado": "string",
  "gia": "string",
  "ibge": "string",
  "localidade": "string",
  "logradouro": "string",
  "regiao": "string",
  "siafi": "string",
  "uf": "string",
  "unidade": "string"
}
```

O app preenche:

- `address` com `logradouro`
- `neighborhood` com `bairro`
- `city` com `localidade`
- `state` com `uf`

Se `erro` existir ou `cep` vier vazio, o app marca o CEP como invalido.

## URLs de assets

O app tambem monta URLs de imagem usando:

```text
https://locadora.7carros.com/uploads/{chave}/
```

Essa URL vem de `EXPO_PUBLIC_IMAGE_URL` e e usada como base para exibir imagens. Nao ha chamada transacional especifica para ela na camada `request`.
