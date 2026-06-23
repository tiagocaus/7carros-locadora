# Endpoints atualizados do Checklist Digital

Este arquivo documenta as rotas atuais usadas pelas telas mobile de checklist:

- `/checklists/digital`
- `/checklists/novo`
- `/checklists/novo?retomar={id}`
- `/checklists/visualizar/{id}`

Ele substitui, para o fluxo de checklist, os endpoints antigos documentados em `endpoints_velho.md`.

## Configuracao geral

- Base URL atual: dominio do sistema web da locadora, por exemplo `https://locadora.7carros.com`.
- Autenticacao: sessao web autenticada por cookie.
- Tenant: definido pela sessao autenticada. Nao enviar `chave` no body.
- Endpoints `/api/*`: exigem CSRF.
- Header obrigatorio em `/api/*`:
  - `X-CSRF-TOKEN: <csrf_token_da_sessao>`
- Header recomendado:
  - `X-Requested-With: XMLHttpRequest`
- Body padrao em `POST /api/*`:
  - `Content-Type: application/json`
- Formato padrao de resposta:
  - sucesso: `{ "success": true, ... }`
  - erro: `{ "success": false, "message": "..." }`

Importante: a API antiga `https://api.locadora.7carros.com/v2` com header `xAcesso` nao e usada no fluxo novo. O app React Native precisa trocar o modelo antigo `token + xAcesso + chave` por sessao/cookie/CSRF ou por uma camada propria de API mobile que adapte essas rotas.

## Resumo das rotas

| Metodo | Rota | Finalidade |
| --- | --- | --- |
| GET | `/checklists/digital` | Tela mobile de listagem |
| GET | `/checklists/novo` | Tela mobile para criar checklist |
| GET | `/checklists/novo?retomar={id}` | Tela mobile para retomar checklist pendente |
| GET | `/checklists/visualizar/{id}` | Tela mobile read-only de visualizacao |
| GET | `/api/checklists` | Listar checklists com paginacao |
| GET | `/api/checklists/novo/{id}` | Buscar dados completos para retomar |
| POST | `/api/checklists/criar` | Criar checklist pendente, aba Informacoes |
| POST | `/api/checklists/{id}/questoes` | Salvar respostas do questionario |
| POST | `/api/checklists/{id}/vistoria/upload` | Enviar foto de vistoria |
| POST | `/api/checklists/{id}/vistoria/{itemId}/excluir` | Remover foto de vistoria |
| POST | `/api/checklists/{id}/assinar` | Enviar assinatura e finalizar |
| GET | `/api/checklist-modelos/buscar` | Buscar modelos para select |
| GET | `/api/checklist-modelos/{id}` | Buscar modelo completo |
| GET | `/api/checklists/buscar-vinculos` | Buscar locacoes e contratos ativos |
| GET | `/api/checklists/veiculos-vinculo` | Buscar veiculos de locacao/contrato |
| GET | `/api/checklists/buscar-veiculos` | Buscar veiculos para checklist avulso |
| GET | `/api/checklists/buscar-locacoes` | Buscar apenas locacoes ativas |
| GET | `/api/checklists/buscar-contratos` | Buscar apenas contratos ativos |
| GET | `/checklists/{id}/imprimir` | PDF do checklist |
| GET | `/verificar/checklist/{codigo}` | Pagina publica de verificacao |

## Headers para chamadas JSON

Exemplo para o app:

```http
X-CSRF-TOKEN: abc123
X-Requested-With: XMLHttpRequest
Content-Type: application/json
Cookie: PHPSESSID=<sessao_autenticada>
```

Para `GET`, enviar os mesmos headers, exceto `Content-Type`, que e opcional.

Se a API retornar `419`, o token CSRF expirou. No sistema web, o helper `API` renova chamando:

```http
GET /api/session/refresh
```

Essa rota retorna um novo token quando a sessao ainda esta valida.

## Telas HTML

### Listagem mobile

- Metodo: `GET`
- Rota: `/checklists/digital`
- Permissao: `checklists.criar`
- Retorna: HTML standalone.

Essa tela chama internamente `GET /api/checklists`.

### Criar checklist

- Metodo: `GET`
- Rota: `/checklists/novo`
- Permissao: `checklists.criar`
- Planos permitidos: `P3`, `P4`
- Retorna: HTML standalone com 4 abas: Informacoes, Questoes, Vistorias, Assinatura.

### Retomar checklist pendente

- Metodo: `GET`
- Rota: `/checklists/novo?retomar={id}`
- Permissao: `checklists.criar`
- Retorna: a mesma tela de criacao, carregando o checklist via `GET /api/checklists/novo/{id}`.

### Visualizar checklist

- Metodo: `GET`
- Rota: `/checklists/visualizar/{id}`
- Permissao: `checklists.criar`
- Retorna: HTML standalone read-only.

## Listagem de checklists

Substitui no app antigo:

- `POST /checklist.php` com `xAcesso: listar`
- `POST /checklist.php` com `xAcesso: listagemVinculado`

### Request

- Metodo: `GET`
- Rota: `/api/checklists`

Query params:

| Param | Tipo | Obrigatorio | Padrao | Observacao |
| --- | --- | --- | --- | --- |
| `page` | number | nao | `1` | Pagina atual |
| `perPage` | number | nao | `10` | Maximo `100`; a tela mobile usa `20` |
| `search` | string | nao | `""` | Busca por codigo, modelo, placa, marca ou modelo do veiculo |

Exemplo:

```http
GET /api/checklists?page=1&perPage=20&search=ABC
```

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 27606,
      "codigo": "CKA1B2C3D4E5F6",
      "tipo": "V",
      "data_checklist": "2026-06-22 10:30:00",
      "status": "2",
      "created_at": "2026-06-22 10:00:00",
      "modelo_nome": "Checklist padrao",
      "placa": "ABC1D23",
      "veiculo_modelo": "Onix",
      "marca": "Chevrolet",
      "id_matriz_filial": 1
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 20,
    "total": 1,
    "totalPages": 1,
    "hasNext": false,
    "hasPrev": false
  }
}
```

Observacoes:

- `tipo`: `V` vinculado, `A` avulso.
- `status`: `1` pendente, `2` finalizado.
- Para pendentes, abrir `/checklists/novo?retomar={id}` ou chamar `GET /api/checklists/novo/{id}` no app nativo.
- Para finalizados, abrir `/checklists/visualizar/{id}` ou montar tela nativa com dados proprios, se criada futuramente.

## Criar checklist

Substitui parcialmente no app antigo:

- `checklistsAvulsoAdicionar`
- `uploadVinculadoSaida`
- `uploadVinculadoChegada`

No fluxo atual, a criacao e dividida em etapas.

### Request

- Metodo: `POST`
- Rota: `/api/checklists/criar`

Body:

```json
{
  "tipo": "V",
  "momento": "S",
  "id_modelo": 10,
  "id_veiculo": 123,
  "id_locacao": 456,
  "id_contrato": null,
  "tanque": "8",
  "odometro": "12345",
  "obs": "Observacao opcional"
}
```

Campos:

| Campo | Tipo | Obrigatorio | Observacao |
| --- | --- | --- | --- |
| `tipo` | string | sim | `V` vinculado, `A` avulso |
| `momento` | string | se `tipo=V` | `S` saida, `C` chegada; para avulso o backend grava `N` |
| `id_modelo` | number | sim | Modelo digital, `tipo=0` |
| `id_veiculo` | number | sim | Veiculo vistoriado |
| `id_locacao` | number/null | se vinculado a locacao | Usar apenas quando vinculo for locacao |
| `id_contrato` | number/null | se vinculado a contrato | Usar apenas quando vinculo for contrato |
| `tanque` | string | sim | Escala `0` a `8` |
| `odometro` | string/number | sim | Pode vir formatado; backend remove `.` e `,` |
| `obs` | string | nao | Observacao livre |

Para checklist avulso:

```json
{
  "tipo": "A",
  "momento": "N",
  "id_modelo": 10,
  "id_veiculo": 123,
  "id_locacao": null,
  "id_contrato": null,
  "tanque": "8",
  "odometro": "12345",
  "obs": ""
}
```

### Response

```json
{
  "success": true,
  "id": 27606,
  "codigo": "CKA1B2C3D4E5F6"
}
```

Depois de criar, carregar o modelo completo com:

```http
GET /api/checklist-modelos/{id_modelo}
```

## Salvar questoes

Substitui parte dos endpoints antigos de upload de checklist, que enviavam questoes junto com assinatura.

### Request

- Metodo: `POST`
- Rota: `/api/checklists/{id}/questoes`

Body:

```json
{
  "questoes": [
    {
      "id": 1,
      "content": "Farol funcionando",
      "opt": "1"
    }
  ]
}
```

Opcoes de resposta:

| `opt` | Significado |
| --- | --- |
| `1` | Confere |
| `2` | Nao confere |
| `3` | Danificado |
| `4` | N/A |

### Response

```json
{
  "success": true
}
```

Observacoes:

- A tela atual faz auto-save a cada 30 segundos nessa mesma rota.
- O backend exige que o checklist exista no tenant da sessao.
- Se o checklist ja estiver finalizado, retorna erro `422`.

## Enviar foto de vistoria

Substitui no app antigo:

- `checklistsAvulsoAdicionarFotos`
- `uploadVinculadoSaidaFotos`
- `uploadVinculadoChegadaFotos`

### Request

- Metodo: `POST`
- Rota: `/api/checklists/{id}/vistoria/upload`

Body:

```json
{
  "item_id": "lataria_dianteira",
  "foto": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ..."
}
```

Campos:

| Campo | Tipo | Obrigatorio | Observacao |
| --- | --- | --- | --- |
| `item_id` | string/number | sim | ID do item de vistoria do modelo |
| `foto` | string | sim | Data URL/base64 da imagem |

### Response

```json
{
  "success": true,
  "filename": "vistoria/arquivo.webp",
  "url": "/files/token-ou-url-gerada"
}
```

Observacoes para o app:

- A tela web redimensiona a imagem no cliente para ate `1200px` e envia JPEG.
- O backend salva como WebP.
- Para editar/anotar dano, a tela web reenvia a imagem final pela mesma rota.

## Excluir foto de vistoria

### Request

- Metodo: `POST`
- Rota: `/api/checklists/{id}/vistoria/{itemId}/excluir`

Body: vazio.

Exemplo:

```http
POST /api/checklists/27606/vistoria/lataria_dianteira/excluir
```

### Response

```json
{
  "success": true
}
```

## Assinar e finalizar checklist

Substitui a finalizacao antiga feita dentro de:

- `checklistsAvulsoAdicionar`
- `uploadVinculadoSaida`
- `uploadVinculadoChegada`

### Request

- Metodo: `POST`
- Rota: `/api/checklists/{id}/assinar`

Body:

```json
{
  "assinatura": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ..."
}
```

### Response

```json
{
  "success": true,
  "message": "Checklist finalizado com sucesso"
}
```

Observacoes:

- A assinatura e obrigatoria.
- O backend salva como WebP.
- Ao finalizar, o status muda para `2`.
- Depois disso, endpoints de edicao retornam erro se exigirem checklist pendente.

## Retomar checklist pendente

### Request

- Metodo: `GET`
- Rota: `/api/checklists/novo/{id}`

Exemplo:

```http
GET /api/checklists/novo/27606
```

### Response

```json
{
  "success": true,
  "data": {
    "id": 27606,
    "codigo": "CKA1B2C3D4E5F6",
    "tipo": "V",
    "momento": "S",
    "status": "1",
    "id_modelo": 10,
    "modelo_nome": "Checklist padrao",
    "modelo_questoes": [
      {
        "id": 1,
        "content": "Farol funcionando"
      }
    ],
    "modelo_vistoria": [
      {
        "id": "lataria_dianteira",
        "content": "Lataria dianteira"
      }
    ],
    "id_veiculo": 123,
    "veiculo": "ABC1D23 - Chevrolet Onix",
    "id_locacao": 456,
    "locacao_codigo": "L000456",
    "locacao_cliente": "Cliente Exemplo",
    "id_contrato": null,
    "contrato_codigo": null,
    "tanque": "8",
    "odometro": 12345,
    "obs": "Observacao opcional",
    "questoes": [],
    "vistoria": [
      {
        "id": "lataria_dianteira",
        "content": "Lataria dianteira",
        "img": "vistoria/arquivo.webp",
        "img_url": "/files/token-ou-url-gerada"
      }
    ],
    "assinatura_url": null
  }
}
```

Uso no app:

- Se houver questoes sem `opt`, abrir etapa Questoes.
- Se todas as questoes estiverem respondidas e nao houver foto em `vistoria`, abrir etapa Vistorias.
- Se houver questoes e fotos, abrir etapa Assinatura.

## Buscar modelos de checklist

Substitui no app antigo:

- `POST /checklist.php` com `xAcesso: modelos`

### Listar modelos para select

- Metodo: `GET`
- Rota: `/api/checklist-modelos/buscar`

Query params:

| Param | Tipo | Obrigatorio | Observacao |
| --- | --- | --- | --- |
| `q` | string | nao | Termo de busca |

Exemplo:

```http
GET /api/checklist-modelos/buscar?q=
```

Response:

```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "text": "Checklist padrao",
      "tipo": 0
    }
  ]
}
```

Observacoes:

- Usar apenas modelos com `tipo = 0` para checklist digital.
- `tipo = 1` e modelo impresso.

### Buscar modelo completo

- Metodo: `GET`
- Rota: `/api/checklist-modelos/{id}`

Response:

```json
{
  "success": true,
  "data": {
    "id": 10,
    "chave": "1111111111111",
    "nome": "Checklist padrao",
    "tipo": 0,
    "status": "A",
    "questoes": "[{\"id\":1,\"content\":\"Farol funcionando\"}]",
    "vistoria": "[{\"id\":\"lataria_dianteira\",\"content\":\"Lataria dianteira\"}]"
  }
}
```

Observacao:

- `questoes` e `vistoria` chegam como JSON string. O app precisa fazer parse.

## Buscar vinculos para checklist vinculado

### Buscar locacoes e contratos juntos

- Metodo: `GET`
- Rota: `/api/checklists/buscar-vinculos`

Query params:

| Param | Tipo | Obrigatorio | Observacao |
| --- | --- | --- | --- |
| `q` | string | nao | Busca por codigo/cliente |

Exemplo:

```http
GET /api/checklists/buscar-vinculos?q=joao
```

Response:

```json
{
  "success": true,
  "data": [
    {
      "id": "L-456",
      "text": "[Locacao] L000456 - Cliente Exemplo",
      "id_veiculo": 123,
      "veiculo": "ABC1D23 - Chevrolet Onix",
      "tipo_combustivel": "GE"
    },
    {
      "id": "C-789",
      "text": "[Contrato] C000789 - Cliente Exemplo",
      "id_veiculo": 124,
      "veiculo": "DEF4G56 - Fiat Argo",
      "tipo_combustivel": "GE"
    }
  ]
}
```

Uso:

- IDs com prefixo `L-` sao locacoes.
- IDs com prefixo `C-` sao contratos.
- Ao criar checklist, transformar `L-456` em `id_locacao: 456` e `id_contrato: null`.
- Transformar `C-789` em `id_contrato: 789` e `id_locacao: null`.

### Buscar veiculos do vinculo

- Metodo: `GET`
- Rota: `/api/checklists/veiculos-vinculo`

Query params:

| Param | Tipo | Obrigatorio | Observacao |
| --- | --- | --- | --- |
| `tipo` | string | sim | `L` locacao, `C` contrato |
| `id` | number | sim | ID da locacao ou contrato |
| `momento` | string | sim | `S` saida, `C` chegada |

Exemplo:

```http
GET /api/checklists/veiculos-vinculo?tipo=L&id=456&momento=S
```

Response:

```json
{
  "success": true,
  "data": [
    {
      "id_veiculo": 123,
      "placa": "ABC1D23",
      "marca": "Chevrolet",
      "modelo": "Onix",
      "tipo_combustivel": "GE",
      "odometro": 12345,
      "tanque_fracao": "8",
      "checklist_feito": false,
      "text": "ABC1D23 - Chevrolet Onix"
    }
  ]
}
```

Observacoes:

- Se `checklist_feito = true`, a tela web desabilita o veiculo para aquele momento.
- Contratos podem retornar mais de um veiculo.

### Buscar apenas locacoes

- Metodo: `GET`
- Rota: `/api/checklists/buscar-locacoes`

Exemplo:

```http
GET /api/checklists/buscar-locacoes?q=joao
```

Response:

```json
{
  "success": true,
  "data": [
    {
      "id": 456,
      "codigo": "L000456",
      "cliente": "Cliente Exemplo",
      "id_veiculo": 123,
      "veiculo": "ABC1D23 - Chevrolet Onix",
      "text": "L000456 - Cliente Exemplo"
    }
  ]
}
```

### Buscar apenas contratos

- Metodo: `GET`
- Rota: `/api/checklists/buscar-contratos`

Exemplo:

```http
GET /api/checklists/buscar-contratos?q=joao
```

Response:

```json
{
  "success": true,
  "data": [
    {
      "id": 789,
      "codigo": "C000789",
      "cliente": "Cliente Exemplo",
      "id_veiculo": 124,
      "veiculo": "DEF4G56 - Fiat Argo",
      "text": "C000789 - Cliente Exemplo"
    }
  ]
}
```

## Buscar veiculos para checklist avulso

Substitui no app antigo:

- `POST /veiculos.php` com `xAcesso: listar`

### Request

- Metodo: `GET`
- Rota: `/api/checklists/buscar-veiculos`

Query params:

| Param | Tipo | Obrigatorio | Observacao |
| --- | --- | --- | --- |
| `q` | string | nao | Busca por placa/modelo/marca |

Exemplo:

```http
GET /api/checklists/buscar-veiculos?q=ABC
```

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "placa": "ABC1D23",
      "modelo": "Onix",
      "marca": "Chevrolet",
      "odometro": 12345,
      "tanque_fracao": "8",
      "tipo_combustivel": "GE",
      "text": "ABC1D23 - Chevrolet Onix"
    }
  ]
}
```

## PDF e verificacao publica

### Imprimir checklist

- Metodo: `GET`
- Rota: `/checklists/{id}/imprimir`
- Permissao: `checklists.visualizar`
- Retorna: PDF inline.

Query params opcionais:

| Param | Tipo | Observacao |
| --- | --- | --- |
| `orientacao` | string | `L` para landscape; qualquer outro valor usa portrait |

Exemplo:

```http
GET /checklists/27606/imprimir?orientacao=L
```

### Verificacao publica por codigo

- Metodo: `GET`
- Rota: `/verificar/checklist/{codigo}`
- Autenticacao: nao exige sessao.
- Retorna: HTML publico de verificacao.

Exemplo:

```http
GET /verificar/checklist/CKA1B2C3D4E5F6
```

## Mapeamento do app antigo para o fluxo atual

| Antigo | Novo |
| --- | --- |
| `POST /checklist.php`, `xAcesso: listar` | `GET /api/checklists` |
| `POST /checklist.php`, `xAcesso: listagemVinculado` | `GET /api/checklists` com filtro por `search` quando necessario |
| `POST /checklist.php`, `xAcesso: ver` | Sem JSON equivalente completo para visualizacao final; usar `/checklists/visualizar/{id}` ou `GET /api/checklists/novo/{id}` para pendentes |
| `POST /checklist.php`, `xAcesso: modelos` | `GET /api/checklist-modelos/buscar` e `GET /api/checklist-modelos/{id}` |
| `POST /veiculos.php`, `xAcesso: listar` | `GET /api/checklists/buscar-veiculos` |
| `POST /checklist.php`, `xAcesso: checklistsAvulsoAdicionar` | `POST /api/checklists/criar` + `POST /api/checklists/{id}/questoes` + `POST /api/checklists/{id}/assinar` |
| `POST /checklist.php`, `xAcesso: checklistsAvulsoAdicionarFotos` | `POST /api/checklists/{id}/vistoria/upload` |
| `POST /checklist.php`, `xAcesso: uploadVinculadoSaida` | `POST /api/checklists/criar` com `tipo=V`, `momento=S`, depois questoes/vistoria/assinatura |
| `POST /checklist.php`, `xAcesso: uploadVinculadoChegada` | `POST /api/checklists/criar` com `tipo=V`, `momento=C`, depois questoes/vistoria/assinatura |
| `POST /checklist.php`, `xAcesso: uploadVinculadoSaidaFotos` | `POST /api/checklists/{id}/vistoria/upload` |
| `POST /checklist.php`, `xAcesso: uploadVinculadoChegadaFotos` | `POST /api/checklists/{id}/vistoria/upload` |

## Fluxo recomendado no React Native

### Checklist avulso

1. Buscar modelos: `GET /api/checklist-modelos/buscar?q=`
2. Buscar veiculos: `GET /api/checklists/buscar-veiculos?q=`
3. Criar checklist: `POST /api/checklists/criar` com `tipo=A`.
4. Buscar modelo completo: `GET /api/checklist-modelos/{id_modelo}`.
5. Salvar questoes: `POST /api/checklists/{id}/questoes`.
6. Enviar uma ou mais fotos: `POST /api/checklists/{id}/vistoria/upload`.
7. Finalizar com assinatura: `POST /api/checklists/{id}/assinar`.

### Checklist vinculado

1. Buscar modelos: `GET /api/checklist-modelos/buscar?q=`.
2. Buscar vinculo: `GET /api/checklists/buscar-vinculos?q=`.
3. Escolher momento `S` ou `C`.
4. Buscar veiculos do vinculo: `GET /api/checklists/veiculos-vinculo?tipo=L|C&id={id}&momento=S|C`.
5. Criar checklist: `POST /api/checklists/criar` com `tipo=V`, `momento`, `id_locacao` ou `id_contrato`.
6. Buscar modelo completo: `GET /api/checklist-modelos/{id_modelo}`.
7. Salvar questoes: `POST /api/checklists/{id}/questoes`.
8. Enviar fotos: `POST /api/checklists/{id}/vistoria/upload`.
9. Finalizar com assinatura: `POST /api/checklists/{id}/assinar`.

### Retomar pendente

1. Listar checklists: `GET /api/checklists`.
2. Se `status=1`, buscar dados: `GET /api/checklists/novo/{id}`.
3. Continuar da etapa incompleta.

## Codigos de erro comuns

| Status | Significado | Acao no app |
| --- | --- | --- |
| `401` | Sessao invalida ou usuario nao autenticado | Enviar para login |
| `403` | Sem permissao ou plano sem acesso | Mostrar mensagem ao usuario |
| `419` | CSRF ausente/expirado | Renovar token com `/api/session/refresh` ou refazer login |
| `422` | Dados invalidos ou checklist ja finalizado | Mostrar `message` retornada |
| `500` | Erro interno | Mostrar `message` retornada e registrar log no app |

## Permissoes e regras

- Criar/editar checklist digital exige `checklists.criar`.
- Listar/imprimir checklists no painel exige `checklists.visualizar`.
- Criacao digital exige plano `P3` ou `P4`.
- Checklist pendente usa `status=1`.
- Checklist finalizado usa `status=2`.
- Checklist vinculado usa `tipo=V`.
- Checklist avulso usa `tipo=A`.
- Momento de saida usa `S`.
- Momento de chegada usa `C`.
- Avulso usa momento `N`.
