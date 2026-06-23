# Endpoints atualizados do app React Native

Este arquivo mapeia os endpoints antigos de `endpoints_velho.md` para as rotas atuais do sistema 7Carros Locadora.

O objetivo e orientar a atualizacao do app React Native antigo, que usava a API legada `https://api.locadora.7carros.com/v2` com `xAcesso`.

## Configuracao geral atual

- Base URL atual: dominio do sistema web, por exemplo `https://locadora.7carros.com`.
- Autenticacao atual: sessao web por cookie PHP. Nao existe Bearer/JWT para funcionario.
- Tenant atual: definido pela sessao autenticada (`chave` em sessao). Nao enviar `chave` no body.
- API antiga: nao usar mais `token`, `xAcesso`, `usuarioLogado`, `chave` nem endpoints `.php` da API v2.
- Cookie jar: o app React Native precisa manter os cookies recebidos no `GET /login` e reaproveitar os mesmos cookies em todas as chamadas seguintes.
- User-Agent: manter consistente entre login e chamadas autenticadas, pois a sessao valida o fingerprint.
- Endpoints `/api/*`: exigem cookie de sessao autenticada e header `X-CSRF-TOKEN`.
- Endpoints POST fora de `/api/*`: usam CSRF de formulario/sessao (`_token` ou `X-CSRF-TOKEN`).
- Header recomendado para chamadas AJAX/API:
  - `X-Requested-With: XMLHttpRequest`
- Header obrigatorio para `/api/*`:
  - `X-CSRF-TOKEN: <csrf_token_da_sessao>`
- Body padrao para JSON:
  - `Content-Type: application/json`
- Body do login web:
  - `Content-Type: application/x-www-form-urlencoded`
- Resposta JSON padrao:
  - sucesso: `{ "success": true, ... }`
  - erro: `{ "success": false, "message": "..." }`

### Renovacao de CSRF e validacao de sessao

Depois do login, use o endpoint abaixo para verificar se a sessao ainda esta valida e renovar o CSRF:

```http
GET /api/session/refresh
Cookie: <cookies_da_sessao>
X-Requested-With: XMLHttpRequest
```

Resposta `200`:

```json
{
  "success": true,
  "csrf_token": "novo-token"
}
```

Se uma chamada `/api/*` retornar `419`, descarte o CSRF atual, chame `GET /api/session/refresh` com os cookies da sessao e repita a requisicao uma vez. Se o refresh falhar com `401` ou redirecionar para `/login`, a sessao expirou e o app deve voltar para a tela de login.

## Resumo de migracao

| Antigo | Novo |
| --- | --- |
| `POST /usuarios.php`, `xAcesso: login` | `GET /login` + `POST /login`, fluxo web com cookie, CSRF e redirect |
| `POST /clientes.php`, `xAcesso: gerarNovaSenha` | `POST /auth/redefinir-senha` |
| `POST /checklist.php`, `xAcesso: listar` | `GET /api/checklists` |
| `POST /checklist.php`, `xAcesso: ver` | `GET /checklists/visualizar/{id}` para HTML ou `GET /api/checklists/novo/{id}` para dados JSON |
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

## Contrato de erros para o app

| HTTP | Formato comum | Acao no app |
| --- | --- | --- |
| `302` | Redirect web | No login, sucesso se `Location` for `/dashboard`, `/checklists/digital` ou `intended_url`; falha se voltar para `/login`. |
| `401` | HTML redirect ou JSON `{success:false,message}` | Sessao invalida/expirada; limpar cookies e voltar ao login. |
| `403` | JSON ou HTML | Sem permissao ou plano sem recurso; mostrar `message` quando existir. |
| `419` | `{ "success": false, "message": "Token CSRF invalido..." }` | Renovar CSRF via `/api/session/refresh` e repetir uma vez. |
| `422` | `{ "success": false, "message": "..." }` | Erro de validacao de negocio; mostrar `message`. |
| `429` | HTML/JSON de rate limit | Aguardar e mostrar mensagem de muitas tentativas/requisicoes. |
| `500` | `{ "success": false, "message": "..." }` | Mostrar erro generico ou `message` tecnico em ambiente interno. |

Mensagens importantes ja retornadas pelo backend:

| Contexto | Mensagem |
| --- | --- |
| Login vazio | `Usuário e senha são obrigatórios` |
| Login invalido, primeira tentativa | `Usuário ou senha inválidos.` |
| Login invalido, tentativas restantes | `Usuário ou senha inválidos. Restam X tentativas antes do bloqueio temporário. Se esqueceu a senha, clique em Redefinir senha antes de tentar novamente.` |
| Login bloqueado ao atingir limite | `Usuário ou senha inválidos. Seu acesso foi bloqueado temporariamente por muitas tentativas. Tente novamente em 15 minutos ou clique em Redefinir senha.` |
| Login ja bloqueado | `Acesso temporariamente bloqueado por muitas tentativas. Tente novamente em X minutos ou redefina sua senha.` |
| Usuario suspenso | `Seu acesso está suspenso. Isso pode acontecer por fatura vencida. Entre em contato com o suporte para regularizar o acesso.` |
| Usuario inativo | `Seu usuário está inativo. Entre em contato com o suporte para verificar o acesso.` |
| CSRF form | `Token CSRF inválido ou expirado` |
| CSRF API | `Token CSRF inválido` ou `Token CSRF inválido ou ausente` |

## Autenticacao

### Login de funcionario

- Antigo: `POST /usuarios.php`, `xAcesso: login`
- Atual: `GET /login` + `POST /login`
- Controller: `AuthController::showLogin()` e `AuthController::login()`
- Middleware do POST: `csrf`
- Retorno atual: redirect no fluxo web; JSON quando a requisicao pedir `Accept: application/json` ou `X-Requested-With: XMLHttpRequest`.

#### 1. Obter cookie de sessao e CSRF

```http
GET /login
Accept: text/html
```

Resposta esperada:

- HTTP `200`
- `Content-Type: text/html`
- Header `Set-Cookie` com cookie de sessao PHP
- HTML contendo o formulario:

```html
<form method="POST" action="/login" id="loginForm">
  <input type="hidden" name="_token" value="csrf-token-64-hex">
  <input type="text" name="username">
  <input type="password" name="password">
  <input type="checkbox" name="remember">
</form>
```

O app deve extrair o valor do input `name="_token"` e preservar todos os cookies retornados.

#### 2. Enviar credenciais

```http
POST /login
Content-Type: application/x-www-form-urlencoded
Accept: text/html,application/xhtml+xml
Cookie: <cookies_do_get_login>
```

Para receber JSON no app/API, envie `Accept: application/json` ou `X-Requested-With: XMLHttpRequest` mantendo o mesmo body e os cookies do `GET /login`.

Body:

```txt
_token=<csrf_extraido_do_html>&username=<email_ou_usuario>&password=<senha>&remember=on
```

Campos:

| Campo | Tipo | Obrigatorio | Observacao |
| --- | --- | --- | --- |
| `_token` | string | Sim | Token CSRF obtido no `GET /login`. |
| `username` | string | Sim | Aceita login (`funcionarios.usuario`) ou e-mail (`funcionarios.email`). |
| `password` | string | Sim | Senha em texto no HTTPS. Backend valida com `password_verify`. |
| `remember` | string | Nao | Enviar exatamente `on` se o usuario marcou "lembrar-me"; omitir se nao marcou. |

Sucesso:

- Web/HTML: HTTP `302`
- Header `Location`:
  - `/dashboard`, se o usuario tiver `dashboard.visualizar`
  - `/checklists/digital`, se nao tiver dashboard e tiver acesso ao checklist
  - `intended_url`, se a sessao guardou uma URL pretendida
- Cookies de sessao autenticada atualizados. O app deve salvar o cookie jar final.
- API/JSON: HTTP `200` com cookies de sessao autenticada atualizados:

```json
{
  "success": true,
  "redirect": "/dashboard",
  "user": {
    "id": 123,
    "nome": "Nome",
    "usuario": "usuario",
    "email": "email@exemplo.com",
    "plano": "P3",
    "id_matriz_filial": 1,
    "filiais_permitidas": []
  }
}
```

Falha:

- Web/HTML: HTTP `302` voltando para `/login` ou HTML da tela de login com `.alert-error`/`.error-message`.
- API/JSON:
  - `422` para campos obrigatorios ausentes.
  - `401` para usuario/senha invalidos.
  - `403` para usuario suspenso ou inativo.
  - `419` para CSRF invalido.
  - `429` para bloqueio temporario por muitas tentativas.

Nao enviar:

- `chave`
- `token`
- `xAcesso`
- `Authorization: Bearer`
- `Content-Type: application/json` no login web

### Verificar sessao autenticada

```http
GET /api/session/refresh
Cookie: <cookies_da_sessao>
X-Requested-With: XMLHttpRequest
```

Resposta `200`:

```json
{
  "success": true,
  "csrf_token": "novo-token"
}
```

Use esse endpoint no boot do app para validar a sessao salva. Se falhar com `401`, `302 /login` ou erro de parse HTML, limpar cookies e exigir novo login.

### Logout

```http
POST /logout
Cookie: <cookies_da_sessao>
```

- Middleware: `auth`
- Nao exige CSRF.
- Sucesso: redirect para `/login` com mensagem `Você saiu com sucesso`.
- O app deve limpar cookies locais apos chamar o endpoint ou se a chamada falhar por sessao expirada.

### Recuperar senha

- Antigo: `POST /clientes.php`, `xAcesso: gerarNovaSenha`
- Atual: `POST /auth/redefinir-senha`
- Middleware: `csrf`, `rate_limit`

Request JSON, usando o `_token` obtido em `GET /login`:

```http
POST /auth/redefinir-senha
Content-Type: application/json
X-Requested-With: XMLHttpRequest
X-CSRF-TOKEN: <csrf_do_login>
Cookie: <cookies_do_get_login>
```

```json
{
  "identifier": "email ou usuario"
}
```

Response `200`:

```json
{
  "success": true,
  "message": "Se o usuario existir e tiver e-mail cadastrado, enviaremos um link para redefinir a senha."
}
```

Observacao: o texto sempre e generico para nao revelar se o usuario existe.

### Definir nova senha

- Atual: `POST /auth/redefinir-senha/definir`
- Middleware: `csrf`, `rate_limit`

Request:

```json
{
  "token": "token recebido por email",
  "senha": "nova senha com no minimo 8 caracteres",
  "senha_confirmacao": "nova senha com no minimo 8 caracteres"
}
```

Responses:

```json
{
  "success": true,
  "message": "Senha redefinida com sucesso. Acesse o painel com a nova senha."
}
```

```json
{
  "success": false,
  "message": "A senha deve ter pelo menos 8 caracteres."
}
```

```json
{
  "success": false,
  "message": "As senhas nao coincidem."
}
```

```json
{
  "success": false,
  "message": "Link invalido ou expirado."
}
```

## Checklist Digital

Regras de acesso:

- Autenticacao: cookie de sessao.
- CSRF: obrigatorio em todos os endpoints `/api/checklists*`.
- Permissao para criar/editar: `checklists.criar`.
- Permissao para listar/visualizar: `checklists.visualizar`.
- Plano para criar checklist digital: `P3` ou `P4`.
- Tenant: obtido de `Auth::chave()`; nunca enviar `chave`.
- Filial: filtros aplicados pelo backend via `FilialHelper`.

Telas HTML atuais:

| Metodo | Rota | Finalidade |
| --- | --- | --- |
| GET | `/checklists/digital` | Listagem mobile HTML. |
| GET | `/checklists/novo` | Criar checklist HTML. |
| GET | `/checklists/novo?retomar={id}` | Retomar pendente HTML. |
| GET | `/checklists/visualizar/{id}` | Visualizar read-only HTML. |

### Listar checklists

- Antigo: `POST /checklist.php`, `xAcesso: listar` e `listagemVinculado`
- Atual: `GET /api/checklists`
- Controller: `ChecklistsController::index()`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`
- Permissao: `checklists.visualizar`

Request:

```http
GET /api/checklists?page=1&perPage=20&search=ABC
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

Query:

| Param | Tipo | Padrao | Regra |
| --- | --- | --- | --- |
| `page` | int | `1` | Minimo `1`. |
| `perPage` | int | `10` | Minimo `1`, maximo `100`. |
| `search` | string | `""` | Busca textual. |

Response `200`:

```json
{
  "success": true,
  "data": [
    {
      "id": 27606,
      "codigo": "CKA1B2C3D4E5F6",
      "tipo": "V",
      "momento": "S",
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

Erros:

```json
{ "success": false, "message": "Voce nao tem permissao para visualizar checklists" }
```

```json
{ "success": false, "message": "Erro ao buscar checklists: <detalhe>" }
```

### Criar checklist

- Antigo: `checklistsAvulsoAdicionar`, `uploadVinculadoSaida`, `uploadVinculadoChegada`
- Atual: `POST /api/checklists/criar`
- Controller: `ChecklistNovoController::criar()`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`
- Permissao: `checklists.criar`
- Plano: `P3` ou `P4`
- Efeito: salva somente a aba de informacoes e cria registro com `status = "1"` (pendente). Questoes, fotos e assinatura sao salvas nas etapas seguintes.

Persistencia da etapa:

- O primeiro salvamento real do checklist acontece ao avancar da aba Informacoes.
- O backend gera `codigo = CK...`, grava `id_funcionario` do usuario autenticado e retorna `id`/`codigo`.
- Para checklist avulso (`tipo=A`), o backend ignora qualquer vinculo enviado e força `momento = "N"`, `id_locacao = null` e `id_contrato = null`.
- Para checklist vinculado (`tipo=V`), o backend exige `momento = "S"` ou `"C"` e exige uma locacao ou contrato.
- O endpoint de criacao nao salva `questoes`, `vistoria` nem `assinatura_unica`.
- O endpoint de criacao nao bloqueia sozinho duplicidade de checklist vinculado; a tela/API de veiculos do vinculo sinaliza `checklist_feito` para o app nao permitir selecionar veiculo ja finalizado naquele momento.

Headers:

```http
POST /api/checklists/criar
Content-Type: application/json
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

Request vinculado a locacao:

```json
{
  "tipo": "V",
  "momento": "S",
  "id_modelo": 10,
  "id_veiculo": 123,
  "id_locacao": 456,
  "id_contrato": null,
  "tanque": "8",
  "odometro": "12.345",
  "obs": "Observacao opcional"
}
```

Request vinculado a contrato:

```json
{
  "tipo": "V",
  "momento": "C",
  "id_modelo": 10,
  "id_veiculo": 123,
  "id_locacao": null,
  "id_contrato": 789,
  "tanque": "4",
  "odometro": "12345",
  "obs": ""
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

Campos:

| Campo | Tipo | Obrigatorio | Regra |
| --- | --- | --- | --- |
| `tipo` | string | Sim | `V` vinculado ou `A` avulso. |
| `momento` | string | Sim para `V` | `S` saida ou `C` chegada. Para `A`, backend força `N`. |
| `id_modelo` | int | Sim | Modelo de checklist digital. |
| `id_veiculo` | int | Sim | Veiculo vistoriado. |
| `id_locacao` | int/null | Condicional | Obrigatorio para `V` se `id_contrato` nao for enviado. |
| `id_contrato` | int/null | Condicional | Obrigatorio para `V` se `id_locacao` nao for enviado. |
| `tanque` | string | Nao | Escala usada pela UI, normalmente `0` a `8`. |
| `odometro` | string/int | Nao | Backend remove `.` e `,` antes de converter para int. |
| `obs` | string | Nao | Salvo em `obs_unica`. |

Comportamento para o app:

- Depois do `200`, guarde o `id` retornado como `checklistId`; ele sera usado em todas as proximas chamadas.
- Em caso de perda de conexao depois dessa etapa, o checklist fica pendente e pode ser retomado por `GET /api/checklists/novo/{id}`.
- Para checklist vinculado, prefira obter o veiculo por `GET /api/checklists/veiculos-vinculo?...` e respeitar `checklist_feito = true`.

Response `200`:

```json
{
  "success": true,
  "id": 27606,
  "codigo": "CKA1B2C3D4E5F6"
}
```

Erros `403`:

```json
{ "success": false, "message": "Sem permissao" }
```

```json
{ "success": false, "message": "Recurso nao disponivel para seu plano" }
```

Erros `422`:

```json
{ "success": false, "message": "Tipo invalido" }
```

```json
{ "success": false, "message": "Selecione uma locacao ou contrato" }
```

```json
{ "success": false, "message": "Selecione o momento (saida/chegada)" }
```

```json
{ "success": false, "message": "Selecione um modelo de checklist" }
```

```json
{ "success": false, "message": "Selecione um veiculo" }
```

Erro `500`:

```json
{ "success": false, "message": "Erro ao criar checklist: <detalhe>" }
```

### Salvar questoes

- Atual: `POST /api/checklists/{id}/questoes`
- Controller: `ChecklistNovoController::salvarQuestoes()`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`
- Permissao: `checklists.criar`
- Efeito: atualiza `checklist.questoes` com JSON das respostas.
- Restricao: checklist precisa existir no tenant e nao pode estar finalizado.

Autosave atual da tela web:

- Ao entrar na aba `questoes`, a tela inicia um `setInterval` de 30 segundos.
- O autosave so envia se ja existir `checklistId`, houver questoes carregadas e pelo menos uma questao tiver `opt` preenchido.
- O envio do autosave usa este mesmo endpoint e manda o array completo `questoes`.
- O autosave e silencioso; em sucesso, a tela mostra apenas um indicador discreto de salvo.
- Ao sair da aba `questoes`, o intervalo e parado.
- Ao clicar em avancar, a tela valida que todas as questoes possuem `opt` e salva novamente antes de ir para Vistorias.

Observacoes para app nativo:

- Pode replicar o autosave de 30 segundos, mas deve manter o salvamento do botao avancar como confirmacao final da etapa.
- Se houver autosave parcial, `GET /api/checklists/novo/{id}` retorna as respostas ja gravadas para retomar.
- O backend aceita o array de questoes como recebido; a obrigatoriedade de todas respondidas e regra da tela/app.

Request:

```http
POST /api/checklists/27606/questoes
Content-Type: application/json
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

```json
{
  "questoes": [
    {
      "id": 1,
      "content": "Farol funcionando",
      "opt": "1"
    },
    {
      "id": 2,
      "content": "Pneu estepe",
      "opt": "4"
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

Response `200`:

```json
{ "success": true }
```

Erros:

```json
{ "success": false, "message": "Sem permissao" }
```

```json
{ "success": false, "message": "Checklist nao encontrado" }
```

```json
{ "success": false, "message": "Checklist ja finalizado" }
```

```json
{ "success": false, "message": "Dados de questoes invalidos" }
```

```json
{ "success": false, "message": "Erro ao salvar questoes: <detalhe>" }
```

### Enviar foto de vistoria

- Antigo: `checklistsAvulsoAdicionarFotos`, `uploadVinculadoSaidaFotos`, `uploadVinculadoChegadaFotos`
- Atual: `POST /api/checklists/{id}/vistoria/upload`
- Controller: `ChecklistNovoController::uploadVistoria()`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`
- Permissao: `checklists.criar`
- Restricao: checklist precisa existir no tenant e nao pode estar finalizado.
- Efeito: salva imagem via `ImageHelper::save(..., 'vistoria', 'webp', 80, chave)` e atualiza o item em `checklist.vistoria`.

Upload automatico atual:

- A tela dispara o upload imediatamente apos o usuario tirar/selecionar a foto no input de camera.
- Antes de enviar, a tela redimensiona/converte a imagem no client-side para Data URL JPEG com tamanho maximo de 1200px.
- O backend recebe `foto` como Data URL base64, converte/salva em WebP qualidade 80 e atualiza `checklist.vistoria`.
- Se `checklist.vistoria` ainda estiver vazio, o backend carrega os itens do template `modelo_vistoria` e preenche o item correspondente.
- Se `item_id` nao existir no template, o backend adiciona um item novo com esse `id` e `img`.
- O response retorna `filename` e `url`; o app deve atualizar o estado local da foto com esses valores.
- Para avancar para assinatura, a tela atual exige pelo menos uma foto em `vistoria`.

Edicao de foto:

- A edicao e feita no canvas do frontend.
- Ao salvar a edicao, a tela compoe imagem original + desenhos + marcadores, exporta JPEG 0.85 e reenvia pelo mesmo endpoint.
- O reenvio substitui o `img` do mesmo `item_id` no JSON de vistoria.
- As anotacoes vetoriais do editor nao sao persistidas separadamente; elas ficam incorporadas na imagem reenviada.

Request:

```http
POST /api/checklists/27606/vistoria/upload
Content-Type: application/json
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

```json
{
  "item_id": "lataria_dianteira",
  "foto": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ..."
}
```

Campos:

| Campo | Tipo | Obrigatorio | Regra |
| --- | --- | --- | --- |
| `item_id` | string/int | Sim | Deve bater com `id` de um item do template de vistoria. Se nao existir, backend adiciona um item com esse id. |
| `foto` | string | Sim | Data URL base64 da imagem. A UI web envia JPEG; backend converte para WebP. |

Response `200`:

```json
{
  "success": true,
  "filename": "vistoria/arquivo.webp",
  "url": "/files/token-ou-url-gerada"
}
```

Erros:

```json
{ "success": false, "message": "Dados incompletos" }
```

```json
{ "success": false, "message": "Erro ao salvar foto" }
```

```json
{ "success": false, "message": "Erro ao enviar foto: <detalhe>" }
```

### Excluir foto de vistoria

- Atual: `POST /api/checklists/{id}/vistoria/{itemId}/excluir`
- Controller: `ChecklistNovoController::excluirVistoria()`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`
- Permissao: `checklists.criar`
- Restricao: checklist precisa existir no tenant e nao pode estar finalizado.
- Efeito: apaga arquivo via `FileHelper::delete()` e seta `img = null` no item.

Comportamento:

- A tela chama este endpoint ao tocar na lixeira do item de vistoria.
- Em sucesso, o app deve limpar `img`, `img_url` e qualquer estado local de edicao daquele item.
- A exclusao nao remove o item do template; apenas remove a imagem vinculada ao item.

Request:

```http
POST /api/checklists/27606/vistoria/lataria_dianteira/excluir
Content-Type: application/json
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

Body: `{}` ou vazio.

Response `200`:

```json
{ "success": true }
```

Erros:

```json
{ "success": false, "message": "Checklist nao encontrado" }
```

```json
{ "success": false, "message": "Checklist ja finalizado" }
```

```json
{ "success": false, "message": "Erro ao excluir foto: <detalhe>" }
```

### Assinar e finalizar checklist

- Atual: `POST /api/checklists/{id}/assinar`
- Controller: `ChecklistNovoController::assinar()`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`
- Permissao: `checklists.criar`
- Restricao: checklist precisa existir no tenant e nao pode estar finalizado.
- Efeito: antes de finalizar, atualiza `veiculos.odometro` e `veiculos.tanque_fracao` somente para checklist avulso ou vinculado de chegada; depois salva assinatura em `assinatura_checklist` como WebP 90 e finaliza checklist (`status = "2"` pelo model).

Comportamento:

- Esta e a ultima etapa do fluxo.
- A assinatura e enviada como Data URL base64.
- Se o checklist for avulso (`tipo=A`) e tiver `id_veiculo`, `odometro` e/ou `tanque`, o cadastro do veiculo e atualizado antes de mudar o checklist para finalizado.
- Se o checklist for vinculado de chegada (`tipo=V`, `momento=C`) e tiver `id_veiculo`, `odometro` e/ou `tanque`, o cadastro do veiculo e atualizado antes de finalizar.
- Se o checklist for vinculado de saida (`tipo=V`, `momento=S`), o cadastro do veiculo nao e atualizado, pois a saida vem da tela de contrato/locacao.
- O odometro do veiculo e atualizado sempre que o checklist tiver odometro maior que zero, mesmo se for menor que o valor atual do cadastro.
- O tanque do checklist atualiza `veiculos.tanque_fracao` quando estiver preenchido.
- Quando houver atualizacao do veiculo, registra log de auditoria com valores anteriores e novos de Odometro/Tanque.
- Em sucesso, o checklist deixa de ser editavel pelas rotas que exigem pendente.
- Depois de finalizado, `POST /api/checklists/{id}/questoes`, upload/exclusao de vistoria e nova assinatura retornam erro de checklist finalizado.

Request:

```http
POST /api/checklists/27606/assinar
Content-Type: application/json
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

```json
{
  "assinatura": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ..."
}
```

Response `200`:

```json
{
  "success": true,
  "message": "Checklist finalizado com sucesso"
}
```

Erros:

```json
{ "success": false, "message": "Assinatura obrigatoria" }
```

```json
{ "success": false, "message": "Erro ao salvar assinatura" }
```

```json
{ "success": false, "message": "Erro ao salvar assinatura: <detalhe>" }
```

### Retomar ou consultar checklist

- Atual: `GET /api/checklists/novo/{id}`
- Controller: `ChecklistNovoController::show()`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`
- Permissao: `checklists.criar`
- Observacao: apesar do nome da rota, retorna JSON do checklist para retomar/preencher. Aceita checklist pendente ou finalizado.

Request:

```http
GET /api/checklists/novo/27606
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

Response `200`:

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
      { "id": 1, "content": "Farol funcionando" }
    ],
    "modelo_vistoria": [
      { "id": "lataria_dianteira", "content": "Lataria dianteira" }
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
    "questoes": [
      { "id": 1, "content": "Farol funcionando", "opt": "1" }
    ],
    "vistoria": [
      {
        "id": "lataria_dianteira",
        "content": "Lataria dianteira",
        "img": "vistoria/arquivo.webp",
        "img_url": "/files/token"
      }
    ],
    "assinatura_url": null
  }
}
```

Erros:

```json
{ "success": false, "message": "Checklist nao encontrado" }
```

```json
{ "success": false, "message": "Erro ao buscar checklist: <detalhe>" }
```

### Modelos de checklist

- Antigo: `POST /checklist.php`, `xAcesso: modelos`
- Select atual: `GET /api/checklist-modelos/buscar?q=`
- Completo atual: `GET /api/checklist-modelos/{id}`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`

Request select:

```http
GET /api/checklist-modelos/buscar?q=padrao
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

Response `200`:

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

Request completo:

```http
GET /api/checklist-modelos/10
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

Response `200`:

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

O app precisa fazer `JSON.parse` de `questoes` e `vistoria` quando vierem como string no modelo completo. No retorno de `GET /api/checklists/novo/{id}`, `modelo_questoes` e `modelo_vistoria` ja saem como arrays.

Erros:

```json
{ "success": false, "message": "Modelo nao encontrado" }
```

```json
{ "success": false, "message": "Erro ao buscar modelos: <detalhe>" }
```

### Vinculos para checklist vinculado

- Atual combinado: `GET /api/checklists/buscar-vinculos?q={texto}`
- Atual locacoes: `GET /api/checklists/buscar-locacoes?q={texto}`
- Atual contratos: `GET /api/checklists/buscar-contratos?q={texto}`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`
- Retorno: listas ja filtradas por tenant e filiais permitidas.

Request combinado:

```http
GET /api/checklists/buscar-vinculos?q=456
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

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
    },
    {
      "id": "C-789",
      "text": "[Contrato] C000789 - Cliente Exemplo",
      "id_veiculo": 124,
      "veiculo": "XYZ9A99 - Fiat Argo",
      "tipo_combustivel": "GE"
    }
  ]
}
```

Response de locacoes:

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

Response de contratos:

```json
{
  "success": true,
  "data": [
    {
      "id": 789,
      "codigo": "C000789",
      "cliente": "Cliente Exemplo",
      "id_veiculo": 123,
      "veiculo": "ABC1D23 - Chevrolet Onix",
      "text": "C000789 - Cliente Exemplo"
    }
  ]
}
```

Erro sem sessao:

```json
{ "success": false, "message": "Sessao invalida" }
```

### Veiculos do vinculo

- Atual: `GET /api/checklists/veiculos-vinculo?tipo=L&id=456&momento=S`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`

Query:

| Param | Tipo | Obrigatorio | Regra |
| --- | --- | --- | --- |
| `tipo` | string | Sim | `L` para locacao, `C` para contrato. |
| `id` | int | Sim | ID da locacao ou contrato. |
| `momento` | string | Nao | `S` ou `C`; padrao `S`. |

Response `200`:

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

Erros:

```json
{ "success": false, "message": "Parametros invalidos" }
```

```json
{ "success": false, "message": "Sessao invalida" }
```

### Veiculos para checklist avulso

- Antigo: `POST /veiculos.php`, `xAcesso: listar`
- Atual: `GET /api/checklists/buscar-veiculos?q={texto}`
- Middleware: `auth`, `api_csrf`, `rate_limit`, `throttle`
- Retorno: somente veiculos do tenant/filiais permitidas.

Request:

```http
GET /api/checklists/buscar-veiculos?q=ABC1D23
Cookie: <cookies_da_sessao>
X-CSRF-TOKEN: <csrf_token_da_sessao>
X-Requested-With: XMLHttpRequest
```

Response `200`:

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

- PDF autenticado: `GET /checklists/{id}/imprimir?orientacao=L`
  - Retorna PDF inline.
  - Requer sessao, permissao `checklists.visualizar` e acesso a filial.
  - `orientacao=L` gera landscape; qualquer outro valor usa portrait.
- Verificacao publica: `GET /verificar/checklist/{codigo}`
  - Retorna HTML publico.
  - Nao requer autenticacao.

## Prompt tecnico para outra IA

Use este prompt se outra IA for implementar/refatorar o fluxo no React Native:

```text
Refatore o login e o fluxo de Checklist Digital do app React Native para consumir o backend atual do 7Carros Locadora.

Nao use a API legada `https://api.locadora.7carros.com/v2`, `xAcesso`, `token`, `chave` no body, JWT ou Bearer token. O backend atual autentica funcionarios por sessao web PHP com cookie + CSRF.

Implemente um AuthService com cookie jar persistente:
1. GET {BASE_URL}/login com Accept: text/html.
2. Salvar cookies retornados.
3. Extrair do HTML o input hidden `name="_token"`.
4. POST {BASE_URL}/login com Content-Type: application/x-www-form-urlencoded e os mesmos cookies.
5. Enviar `_token`, `username`, `password` e, se marcado, `remember=on`.
6. Considerar sucesso quando o POST responder JSON `{ success: true, redirect, user }` ou, no fluxo HTML, 302 para `/dashboard`, `/checklists/digital` ou intended_url. Considerar falha se voltar para `/login` ou retornar JSON `{ success: false, message }`.
7. Em falha, extrair `.alert-error`/`.error-message` do HTML ou mostrar as mensagens oficiais documentadas.
8. Guardar os cookies finais para as chamadas autenticadas.

Depois do login, obter/renovar CSRF com:
GET {BASE_URL}/api/session/refresh
Headers: Cookie, X-Requested-With: XMLHttpRequest
Response: `{ success: true, csrf_token }`.

Todas as chamadas `/api/*` devem enviar:
- Cookie da sessao
- X-CSRF-TOKEN: csrf_token atual
- X-Requested-With: XMLHttpRequest
- Content-Type: application/json quando houver body JSON

Se receber 419, chamar `/api/session/refresh` e repetir a requisicao uma vez. Se receber 401, redirect para `/login` ou HTML de login, limpar cookies e voltar para login.

Implemente os endpoints de checklist conforme `endpoints_checklists_atualizado.md`: listar, criar, salvar questoes, upload/exclusao de vistoria, assinar/finalizar, retomar checklist, buscar modelos, buscar vinculos e buscar veiculos.
```

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
3. `POST /api/checklists/criar` com `tipo=A` ao avancar da aba Informacoes; guardar `id` e `codigo` retornados.
4. `GET /api/checklist-modelos/{id_modelo}`.
5. Na aba Questoes, autosalvar a cada 30s quando houver pelo menos uma resposta; ao avancar, chamar `POST /api/checklists/{id}/questoes` exigindo todas respondidas.
6. Na aba Vistorias, chamar `POST /api/checklists/{id}/vistoria/upload` imediatamente apos cada foto capturada/selecionada; reusar o mesmo endpoint ao salvar foto editada.
7. Se remover foto, chamar `POST /api/checklists/{id}/vistoria/{itemId}/excluir`.
8. Ao finalizar, chamar `POST /api/checklists/{id}/assinar`.

### Checklist vinculado

1. `GET /api/checklist-modelos/buscar?q=`
2. `GET /api/checklists/buscar-vinculos?q=`
3. Escolher `momento=S` ou `momento=C`.
4. `GET /api/checklists/veiculos-vinculo?tipo=L|C&id={id}&momento=S|C` e nao permitir selecionar veiculo com `checklist_feito = true`.
5. `POST /api/checklists/criar` com `tipo=V` ao avancar da aba Informacoes; guardar `id` e `codigo` retornados.
6. `GET /api/checklist-modelos/{id_modelo}`.
7. Na aba Questoes, autosalvar a cada 30s quando houver pelo menos uma resposta; ao avancar, chamar `POST /api/checklists/{id}/questoes` exigindo todas respondidas.
8. Na aba Vistorias, chamar `POST /api/checklists/{id}/vistoria/upload` imediatamente apos cada foto capturada/selecionada; reusar o mesmo endpoint ao salvar foto editada.
9. Se remover foto, chamar `POST /api/checklists/{id}/vistoria/{itemId}/excluir`.
10. Ao finalizar, chamar `POST /api/checklists/{id}/assinar`.

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
- `docs/api.md`
- `docs/security.md`
