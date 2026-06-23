# Endpoints atualizados do app React Native

Este arquivo mapeia os endpoints antigos de `endpoints_velho.md` para as rotas atuais do sistema 7Carros Locadora.

O objetivo e orientar a atualizacao do app React Native antigo, que usava a API legada `https://api.locadora.7carros.com/v2` com `xAcesso`.

## Configuracao geral atual

- Base URL atual: dominio do sistema web, por exemplo `https://locadora.7carros.com`.
- Autenticacao atual: sessao web por cookie.
- Tenant atual: definido pela sessao autenticada. Nao enviar `chave` no body.
- API antiga: nao usar mais `token`, `xAcesso` nem endpoints `.php` da API v2.
- Endpoints `/api/*`: exigem header `X-CSRF-TOKEN`.
- Endpoints POST fora de `/api/*`: usam CSRF de formulario/sessao.
- Header recomendado para chamadas AJAX:
  - `X-Requested-With: XMLHttpRequest`
- Header obrigatorio para `/api/*`:
  - `X-CSRF-TOKEN: <csrf_token_da_sessao>`
- Body padrao para JSON:
  - `Content-Type: application/json`
- Resposta padrao:
  - sucesso: `{ "success": true, ... }`
  - erro: `{ "success": false, "message": "..." }`

Se a API retornar `419`, o token CSRF expirou. O sistema web renova com:

```http
GET /api/session/refresh
```

Resposta:

```json
{
  "success": true,
  "csrf_token": "novo-token"
}
```

## Resumo de migracao

| Antigo | Novo |
| --- | --- |
| `POST /usuarios.php`, `xAcesso: login` | `POST /login`, fluxo web com redirect |
| `POST /clientes.php`, `xAcesso: gerarNovaSenha` | `POST /auth/redefinir-senha` |
| `POST /checklist.php`, `xAcesso: listar` | `GET /api/checklists` |
| `POST /checklist.php`, `xAcesso: ver` | Sem JSON final equivalente; usar `/checklists/visualizar/{id}` ou `GET /api/checklists/novo/{id}` para pendentes |
| `POST /checklist.php`, `xAcesso: modelos` | `GET /api/checklist-modelos/buscar` e `GET /api/checklist-modelos/{id}` |
| `POST /veiculos.php`, `xAcesso: listar` | `GET /api/checklists/buscar-veiculos` |
| `POST /checklist.php`, `xAcesso: checklistsAvulsoAdicionar` | `POST /api/checklists/criar` + questoes + fotos + assinatura |
| `POST /checklist.php`, `xAcesso: checklistsAvulsoAdicionarFotos` | `POST /api/checklists/{id}/vistoria/upload` |
| `POST /checklist.php`, `xAcesso: uploadVinculadoSaida` | `POST /api/checklists/criar` com `tipo=V`, `momento=S` + etapas seguintes |
| `POST /checklist.php`, `xAcesso: uploadVinculadoChegada` | `POST /api/checklists/criar` com `tipo=V`, `momento=C` + etapas seguintes |
| `POST /checklist.php`, `xAcesso: listagemVinculado` | `GET /api/checklists` |
| `POST /checklist.php`, `xAcesso: uploadVinculadoSaidaFotos` | `POST /api/checklists/{id}/vistoria/upload` |
| `POST /checklist.php`, `xAcesso: uploadVinculadoChegadaFotos` | `POST /api/checklists/{id}/vistoria/upload` |
| `POST /appcliente.dadosiniciais.php`, `xAcesso: ver` | `GET /api/dashboard/stats` |
| `POST /assinarDocumento.php`, `xAcesso: listar` | Sem lista unica atual; usar links publicos `/assinar/{codigo}` de contratos/locacoes |
| `POST /assinarDocumento.php`, `xAcesso: adicionar` | `POST /assinar/{codigo}` |
| `POST /matrizfiliais.php`, `xAcesso: ver` | `GET /api/matrizes-filiais/{id}` |
| `POST /matrizfiliais.php`, `xAcesso: listar` | `GET /api/matrizes-filiais` |
| `POST /matrizfiliais.php`, `xAcesso: adicionar` | `POST /matrizes-filiais/salvar` |
| `POST /matrizfiliais.php`, `xAcesso: editar` | `POST /matrizes-filiais/{id}/atualizar` |
| `POST /matrizfiliais.php`, `xAcesso: apagar` | `POST /matrizes-filiais/{id}/excluir` ou `/desativar` |
| `POST ultimasAtualizacoes.php`, `xAcesso: listar` | `GET /api/public/changelog` |
| `GET viacep JSONP` | `GET https://viacep.com.br/ws/{cep}/json/` |
| `https://locadora.7carros.com/uploads/{chave}/...` | URLs retornadas pelo backend, normalmente `/files/{token}` |

## Autenticacao

### Login

- Antigo: `POST /usuarios.php`, `xAcesso: login`
- Atual: `POST /login`
- Middleware: `csrf`
- Retorno atual: redirect web, nao JSON mobile.

Request atual:

```json
{
  "username": "email ou usuario",
  "password": "senha",
  "remember": "on"
}
```

Observacoes para o app:

- A rota atual foi desenhada para navegador e redireciona para `/dashboard` ou `/checklists/digital`.
- Para React Native puro, sera necessario tratar cookie de sessao e CSRF ou criar uma API mobile dedicada.
- O campo antigo `usuario` vira `username`.
- O campo antigo `senha` vira `password`.
- Nao enviar `chave`; ela e definida pela autenticacao.

### Verificar usuario

- Antigo: `POST /usuarios.php`, `xAcesso: login` sem senha.
- Atual: nao ha equivalente direto.

Use a sessao existente chamando uma rota autenticada simples, por exemplo:

```http
GET /api/session/refresh
```

Se a sessao estiver valida, retorna novo CSRF. Se nao estiver, o app deve voltar para login.

### Recuperar senha

- Antigo: `POST /clientes.php`, `xAcesso: gerarNovaSenha`
- Atual: `POST /auth/redefinir-senha`
- Middleware: `csrf`, `rate_limit`

Request:

```json
{
  "identifier": "email, CPF/CNPJ ou usuario"
}
```

Response:

```json
{
  "success": true,
  "message": "Se o usuario existir e tiver e-mail cadastrado, enviaremos um link para redefinir a senha."
}
```

### Definir nova senha

- Atual: `POST /auth/redefinir-senha/definir`
- Middleware: `csrf`, `rate_limit`

Request:

```json
{
  "token": "token recebido por email",
  "senha": "nova senha",
  "senha_confirmacao": "nova senha"
}
```

Response:

```json
{
  "success": true,
  "message": "Senha redefinida com sucesso. Acesse o painel com a nova senha."
}
```

## Checklist Digital

Telas HTML atuais:

| Metodo | Rota | Finalidade |
| --- | --- | --- |
| GET | `/checklists/digital` | Listagem mobile |
| GET | `/checklists/novo` | Criar checklist |
| GET | `/checklists/novo?retomar={id}` | Retomar pendente |
| GET | `/checklists/visualizar/{id}` | Visualizar read-only |

### Listar checklists

- Antigo: `POST /checklist.php`, `xAcesso: listar` e `listagemVinculado`
- Atual: `GET /api/checklists`

Query:

| Param | Tipo | Padrao |
| --- | --- | --- |
| `page` | number | `1` |
| `perPage` | number | `10`, max `100` |
| `search` | string | `""` |

Exemplo:

```http
GET /api/checklists?page=1&perPage=20&search=ABC
```

Response:

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

### Criar checklist

- Antigo: `checklistsAvulsoAdicionar`, `uploadVinculadoSaida`, `uploadVinculadoChegada`
- Atual: `POST /api/checklists/criar`

Request vinculado:

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

Request avulso:

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

Response:

```json
{
  "success": true,
  "id": 27606,
  "codigo": "CKA1B2C3D4E5F6"
}
```

### Salvar questoes

- Atual: `POST /api/checklists/{id}/questoes`

Request:

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

Opcoes:

| `opt` | Significado |
| --- | --- |
| `1` | Confere |
| `2` | Nao confere |
| `3` | Danificado |
| `4` | N/A |

Response:

```json
{
  "success": true
}
```

### Enviar foto de vistoria

- Antigo: `checklistsAvulsoAdicionarFotos`, `uploadVinculadoSaidaFotos`, `uploadVinculadoChegadaFotos`
- Atual: `POST /api/checklists/{id}/vistoria/upload`

Request:

```json
{
  "item_id": "lataria_dianteira",
  "foto": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ..."
}
```

Response:

```json
{
  "success": true,
  "filename": "vistoria/arquivo.webp",
  "url": "/files/token-ou-url-gerada"
}
```

### Excluir foto de vistoria

- Atual: `POST /api/checklists/{id}/vistoria/{itemId}/excluir`

Request: body vazio.

Response:

```json
{
  "success": true
}
```

### Assinar e finalizar checklist

- Atual: `POST /api/checklists/{id}/assinar`

Request:

```json
{
  "assinatura": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ..."
}
```

Response:

```json
{
  "success": true,
  "message": "Checklist finalizado com sucesso"
}
```

### Retomar checklist pendente

- Atual: `GET /api/checklists/novo/{id}`

Response:

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
    "modelo_questoes": [],
    "modelo_vistoria": [],
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
    "vistoria": [],
    "assinatura_url": null
  }
}
```

### Modelos de checklist

- Antigo: `POST /checklist.php`, `xAcesso: modelos`
- Atual select: `GET /api/checklist-modelos/buscar?q=`
- Atual completo: `GET /api/checklist-modelos/{id}`

Response do select:

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

Response completo:

```json
{
  "success": true,
  "data": {
    "id": 10,
    "nome": "Checklist padrao",
    "tipo": 0,
    "status": "A",
    "questoes": "[{\"id\":1,\"content\":\"Farol funcionando\"}]",
    "vistoria": "[{\"id\":\"lataria_dianteira\",\"content\":\"Lataria dianteira\"}]"
  }
}
```

O app precisa fazer parse de `questoes` e `vistoria`.

### Vinculos para checklist vinculado

- Atual combinado: `GET /api/checklists/buscar-vinculos?q={texto}`
- Atual locacoes: `GET /api/checklists/buscar-locacoes?q={texto}`
- Atual contratos: `GET /api/checklists/buscar-contratos?q={texto}`

Response combinado:

```json
{
  "success": true,
  "data": [
    {
      "id": "L-456",
      "text": "[Locação] L000456 - Cliente Exemplo",
      "id_veiculo": 123,
      "veiculo": "ABC1D23 - Chevrolet Onix",
      "tipo_combustivel": "GE"
    }
  ]
}
```

### Veiculos do vinculo

- Atual: `GET /api/checklists/veiculos-vinculo?tipo=L&id=456&momento=S`

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

### Veiculos para checklist avulso

- Antigo: `POST /veiculos.php`, `xAcesso: listar`
- Atual: `GET /api/checklists/buscar-veiculos?q={texto}`

Response:

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

### PDF e verificacao

- PDF: `GET /checklists/{id}/imprimir?orientacao=L`
- Verificacao publica: `GET /verificar/checklist/{codigo}`

## Empresa / Matriz-Filial

### Listar matriz/filiais

- Antigo: `POST /matrizfiliais.php`, `xAcesso: listar`
- Atual: `GET /api/matrizes-filiais`
- Middleware: `api_csrf`
- Permissao: `matrizes_filiais.visualizar`

Query:

| Param | Tipo | Padrao |
| --- | --- | --- |
| `page` | number | `1` |
| `perPage` | number | `10`, max `100` |
| `search` | string | `""` |

Response:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "chave": "1111111111111",
      "logo": "logo/arquivo.webp",
      "logo_url": "/files/token",
      "tipo": "M",
      "status": "A",
      "razao_social": "Empresa LTDA",
      "nome_fantasia": "Empresa",
      "cpf_cnpj": "00.000.000/0001-00",
      "cidade": "Sao Paulo",
      "estado": "SP",
      "email": "contato@empresa.com",
      "celular": "11999999999",
      "currency_code": "BRL",
      "locale": "pt_BR"
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 10,
    "total": 1,
    "totalPages": 1,
    "hasNext": false,
    "hasPrev": false
  }
}
```

### Buscar matriz/filial por ID

- Antigo: `POST /matrizfiliais.php`, `xAcesso: ver`
- Atual: `GET /api/matrizes-filiais/{id}`
- Middleware: `api_csrf`
- Permissao: `matrizes_filiais.visualizar`

Response inclui dados principais e dados relacionados:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "tipo": "M",
    "status": "A",
    "razao_social": "Empresa LTDA",
    "nome_fantasia": "Empresa",
    "cpf_cnpj": "00.000.000/0001-00",
    "ins_muni": "123",
    "ins_esta": "456",
    "cep": "01001000",
    "rua": "Rua Exemplo",
    "num": "100",
    "compl": "Sala 1",
    "bairro": "Centro",
    "cidade": "Sao Paulo",
    "estado": "SP",
    "pais": "Brasil",
    "fixo": "1133333333",
    "celular": "11999999999",
    "email": "contato@empresa.com",
    "site": "https://empresa.com",
    "logo": "logo/arquivo.webp",
    "logo_url": "/files/token",
    "locale": "pt_BR",
    "currency_code": "BRL",
    "date_format": "d/m/Y H:i:s",
    "datetime_format": "d/m/Y H:i:s",
    "horarios_funcionamento": [],
    "horarios_excecoes": [],
    "proximos_feriados": [],
    "emails": [],
    "telefones": [],
    "locais": []
  }
}
```

### Buscar matriz/filial para select

- Atual: `GET /api/matrizes-filiais/buscar?q={texto}`

Response:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "text": "Empresa LTDA",
      "nome": "Empresa LTDA",
      "nome_fantasia": "Empresa",
      "currency_code": "BRL",
      "locale": "pt_BR"
    }
  ]
}
```

### Criar matriz/filial

- Antigo: `POST /matrizfiliais.php`, `xAcesso: adicionar`
- Atual: `POST /matrizes-filiais/salvar`
- Middleware: `csrf`
- Permissao: `matrizes_filiais.criar`

Request atual:

```json
{
  "tipo": "M",
  "status": "A",
  "razao_social": "Empresa LTDA",
  "nome_fantasia": "Empresa",
  "cpf_cnpj": "00.000.000/0001-00",
  "inscricao_municipal": "123",
  "inscricao_estadual": "456",
  "cep": "01001000",
  "rua": "Rua Exemplo",
  "numero": "100",
  "complemento": "Sala 1",
  "bairro": "Centro",
  "cidade": "Sao Paulo",
  "estado": "SP",
  "pais": "Brasil",
  "telefone_fixo": "1133333333",
  "celular": "11999999999",
  "email": "contato@empresa.com",
  "site": "https://empresa.com",
  "logo_base64": "data:image/png;base64,...",
  "locale": "pt_BR",
  "currency_code": "BRL",
  "date_format": "d/m/Y H:i:s",
  "datetime_format": "d/m/Y H:i:s",
  "sequencia_locacoes": 1,
  "sequencia_contratos": 1,
  "sequencia_financeiro": 1,
  "notificacao_sms": "N",
  "notificacao_email": "N",
  "notificacao_whatsapp": "N",
  "notificacao_titulo": "",
  "impressao_variavel_negrito": "N",
  "impressao_remover_tarja_amarela": "N",
  "horarios_funcionamento": [],
  "horarios_excecoes": [],
  "emails": [],
  "telefones": [],
  "locais": []
}
```

Response:

```json
{
  "success": true,
  "message": "Matriz/Filial criada com sucesso",
  "data": {
    "id": 1
  }
}
```

### Editar matriz/filial

- Antigo: `POST /matrizfiliais.php`, `xAcesso: editar`
- Atual: `POST /matrizes-filiais/{id}/atualizar`
- Middleware: `csrf`
- Permissao: `matrizes_filiais.editar`

Request: mesmo formato da criacao.

Response:

```json
{
  "success": true,
  "message": "Matriz/Filial atualizada com sucesso"
}
```

### Excluir ou desativar matriz/filial

- Antigo: `POST /matrizfiliais.php`, `xAcesso: apagar`
- Atual excluir: `POST /matrizes-filiais/{id}/excluir`
- Atual desativar: `POST /matrizes-filiais/{id}/desativar`
- Middleware: `csrf`
- Permissao: `matrizes_filiais.excluir`

Response de exclusao:

```json
{
  "success": true,
  "message": "Matriz/Filial excluída com sucesso"
}
```

Se houver vinculos:

```json
{
  "success": false,
  "message": "Não é possível excluir esta matriz/filial pois existem registros vinculados.",
  "vinculos": [],
  "pode_desativar": true
}
```

Nesse caso, usar `/desativar`.

### Mapeamento de campos antigos de empresa

| Antigo | Atual |
| --- | --- |
| `empresa` | `razao_social` e/ou `nome_fantasia` |
| `cnpj` | `cpf_cnpj` |
| `ins_muni` | `inscricao_municipal` no request, `ins_muni` no response |
| `ins_esta` | `inscricao_estadual` no request, `ins_esta` no response |
| `num` | `numero` no request, `num` no response |
| `compl` | `complemento` no request, `compl` no response |
| `fixo` | `telefone_fixo` no request, `fixo` no response |
| `logo` | `logo_base64` no request, `logo`/`logo_url` no response |
| `dias_uteis`, `hora_ini`, `hora_fim` | `horarios_funcionamento` |
| `assinatura` da empresa | campo legado; assinatura de documentos agora usa tabela `assinaturas` |

## Assinatura de documentos

O sistema atual nao tem um endpoint JSON unico igual a `assinarDocumento.php/listar` para o app listar todos os documentos assinaveis.

O fluxo atual e por link publico:

- `GET /assinar/{codigo}`: abre pagina publica de assinatura.
- `POST /assinar/{codigo}`: salva assinatura do cliente.

O `{codigo}` pode ser de contrato ou locacao. A rota resolve automaticamente:

- codigo com prefixo `C`: tenta contrato primeiro.
- codigo com prefixo `L`: tenta locacao primeiro.

### Abrir pagina publica

- Antigo: parte de `assinarDocumento.php/listar`
- Atual: `GET /assinar/{codigo}`
- Autenticacao: nao exige sessao.
- Retorno: HTML.

Se o app for manter tela nativa, hoje nao existe rota JSON publica para buscar esse resumo. O app pode abrir a WebView em `/assinar/{codigo}`.

### Salvar assinatura

- Antigo: `POST /assinarDocumento.php`, `xAcesso: adicionar`
- Atual: `POST /assinar/{codigo}`
- Autenticacao: nao exige sessao.
- CSRF: nao usa `api_csrf`; rota publica com `rate_limit`.

Request:

```json
{
  "assinatura": "data:image/png;base64,...",
  "latitude": -23.55052,
  "longitude": -46.633308
}
```

Response:

```json
{
  "success": true,
  "message": "Contrato assinado com sucesso!",
  "data": {
    "codigo": "C000123",
    "data_assinatura": "22/06/2026 10:30:00",
    "ip": "127.0.0.1"
  }
}
```

Erros comuns:

```json
{
  "success": false,
  "message": "Documento não encontrado"
}
```

```json
{
  "success": false,
  "message": "Este contrato já foi assinado"
}
```

Observacoes:

- A assinatura deve ser enviada como Data URL iniciando com `data:image`.
- A assinatura e salva em WebP via `ImageHelper`.
- O backend registra IP, user agent, latitude e longitude.
- O canvas deve exportar com fundo branco para evitar assinatura preta em PDF.

## Home / Dashboard

### Dados iniciais

- Antigo: `POST /appcliente.dadosiniciais.php`, `xAcesso: ver`
- Atual: `GET /api/dashboard/stats`
- Middleware: `api_csrf`

Response atual:

```json
{
  "success": true,
  "data": {
    "fleet": {
      "total": 10,
      "available": 4,
      "rented": 3,
      "reserved": 2,
      "maintenance": 1,
      "expected_revenue_today": 1000,
      "average_daily_rate": 333.33
    },
    "operations": {
      "overdue": 1
    },
    "financial": {
      "overdue_total": 500,
      "overdue_count": 2,
      "upcoming_total": 1000
    },
    "alerts": []
  },
  "timestamp": "22/06/2026 10:30:00"
}
```

Mapeamento aproximado para campos antigos:

| Antigo | Atual |
| --- | --- |
| `veiculos_disponiveis` | `data.fleet.available` |
| `veiculos_locados` | `data.fleet.rented` |
| `veiculos_reservados` | `data.fleet.reserved` |
| `veiculos_oficina` | `data.fleet.maintenance` |
| `financeiro_vencidas_valor` | `data.financial.overdue_total` |
| `financeiro_avencer_valor` | `data.financial.upcoming_total` |
| `clientes_qtd` | sem campo direto nessa rota atual |

## Changelog

### Listar ultimas atualizacoes

- Antigo: `POST ultimasAtualizacoes.php`, `xAcesso: listar`
- Atual publico: `GET /api/public/changelog?limite=50&offset=0`
- Autenticacao: nao exige sessao.

Response:

```json
{
  "success": true,
  "data": [
    {
      "versao": "8.4.0",
      "data": "2026-06-22",
      "destaque": true,
      "itens": [
        {
          "tipo": "N",
          "tipo_label": "Novo",
          "mensagens": [
            "Mensagem da atualizacao"
          ]
        }
      ]
    }
  ],
  "hasMore": false,
  "offset": 0,
  "limite": 50
}
```

### Changelog autenticado

- Atual: `GET /api/changelog`
- Middleware: `api_csrf`

Use essa rota apenas se o usuario ja estiver autenticado e o app precisar do formato administrativo.

## Servicos externos

### Buscar endereco por CEP

- Antigo: `GET https://viacep.com.br/ws/{cep}/json/?callback=?` com JSONP.
- Recomendado: `GET https://viacep.com.br/ws/{cep}/json/` com JSON normal.

Exemplo:

```http
GET https://viacep.com.br/ws/01001000/json/
```

Response:

```json
{
  "cep": "01001-000",
  "logradouro": "Praça da Sé",
  "complemento": "lado ímpar",
  "bairro": "Sé",
  "localidade": "São Paulo",
  "uf": "SP",
  "ibge": "3550308",
  "gia": "1004",
  "ddd": "11",
  "siafi": "7107"
}
```

Preenchimento no app:

| ViaCEP | Campo app |
| --- | --- |
| `logradouro` | `rua` / address |
| `bairro` | `bairro` / neighborhood |
| `localidade` | `cidade` / city |
| `uf` | `estado` / state |

## Assets e imagens

### Regra antiga

O app antigo montava imagens com:

```text
https://locadora.7carros.com/uploads/{chave}/
```

### Regra atual

Nao montar URL manualmente com `chave`.

Usar a URL retornada pelo backend, normalmente no formato:

```text
/files/{token}
```

Exemplos de campos atuais:

- `logo_url` em `GET /api/matrizes-filiais`
- `url` em `POST /api/checklists/{id}/vistoria/upload`
- `img_url` em `GET /api/checklists/novo/{id}`
- `assinatura_url` em `GET /api/checklists/novo/{id}`

## Codigos de erro comuns

| Status | Significado | Acao no app |
| --- | --- | --- |
| `400` | Payload invalido ou assinatura duplicada | Mostrar `message` |
| `401` | Sessao invalida | Voltar para login |
| `403` | Sem permissao ou plano sem acesso | Mostrar `message` |
| `419` | CSRF ausente ou expirado | Renovar CSRF ou refazer login |
| `422` | Validacao de negocio | Mostrar `message` |
| `500` | Erro interno | Mostrar `message` e registrar log no app |

## Fluxos recomendados

### Checklist avulso

1. `GET /api/checklist-modelos/buscar?q=`
2. `GET /api/checklists/buscar-veiculos?q=`
3. `POST /api/checklists/criar` com `tipo=A`.
4. `GET /api/checklist-modelos/{id_modelo}`.
5. `POST /api/checklists/{id}/questoes`.
6. `POST /api/checklists/{id}/vistoria/upload`.
7. `POST /api/checklists/{id}/assinar`.

### Checklist vinculado

1. `GET /api/checklist-modelos/buscar?q=`
2. `GET /api/checklists/buscar-vinculos?q=`
3. Escolher `momento=S` ou `momento=C`.
4. `GET /api/checklists/veiculos-vinculo?tipo=L|C&id={id}&momento=S|C`.
5. `POST /api/checklists/criar` com `tipo=V`.
6. `GET /api/checklist-modelos/{id_modelo}`.
7. `POST /api/checklists/{id}/questoes`.
8. `POST /api/checklists/{id}/vistoria/upload`.
9. `POST /api/checklists/{id}/assinar`.

### Atualizar empresa

1. `GET /api/matrizes-filiais?page=1&perPage=20`.
2. `GET /api/matrizes-filiais/{id}`.
3. `POST /matrizes-filiais/{id}/atualizar`.
4. Se precisar remover: tentar `POST /matrizes-filiais/{id}/excluir`; se retornar `pode_desativar`, usar `POST /matrizes-filiais/{id}/desativar`.

### Assinar documento

1. Obter o codigo do contrato ou locacao a partir do fluxo que gera o documento.
2. Abrir WebView em `/assinar/{codigo}` ou implementar tela nativa usando `POST /assinar/{codigo}`.
3. Enviar `assinatura`, `latitude`, `longitude`.

Se o app precisar listar todos os documentos assinaveis como antes, sera necessario criar uma nova API mobile, porque o sistema atual trabalha por link publico individual.

## Permissoes relevantes

| Modulo | Permissao |
| --- | --- |
| Checklist criar/editar | `checklists.criar` |
| Checklist listar/imprimir | `checklists.visualizar` |
| Matrizes/filiais listar | `matrizes_filiais.visualizar` |
| Matrizes/filiais criar | `matrizes_filiais.criar` |
| Matrizes/filiais editar | `matrizes_filiais.editar` |
| Matrizes/filiais excluir/desativar | `matrizes_filiais.excluir` |
| Dashboard | `dashboard.visualizar` |

## Documentos consultados

- `AGENTS.md`
- `docs/checklists.md`
- `docs/architecture.md`
- `docs/querybuilder.md`
- `docs/modals.md`
- `docs/api.md`
- `docs/assinaturas.md`
- `docs/documentos.md`
- `docs/development.md`
- `docs/overview.md`
