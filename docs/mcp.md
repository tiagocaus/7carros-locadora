# Integração IA/MCP

## Estado da entrega — 16/11/2026

Implementado e validado localmente, com feature flag desativada por padrão. A
publicação dos arquivos não instala dependências, não executa migrations e não
habilita a integração. O [roteiro de implantação](../PLANO-MCP.md) mantém o checklist.

| Parte | Estado |
|---|---|
| Arquivos do escopo | Base e correção CIMD/piloto publicadas por FTP, com hashes conferidos; sem ativação automática |
| Transporte oficial, OAuth, contexto, consultas e administração | Implementados; testes locais aprovados |
| Banco local | Migration `00441_create_mcp_integration.php` aplicada pelo executor oficial |
| Assistente próprio de teste | Fluxo local de autorização, consulta, renovação e revogação validado; não é homologação externa |
| ChatGPT e Claude | ChatGPT: autorização, consulta, renovação após expiração e revogação homologadas externamente; liberação global ativa. Claude web: consulta validada, renovação/revogação pendentes; Claude Code: homologação externa pendente |
| Dependências e migration de produção | Execução confirmada pelo responsável; diagnóstico de produção aprovado na ativação global |
| Liberação de locadoras | Modo global ativo para aplicativos homologados; piloto preservado como opção restrita |

## Arquitetura e dependências

Controller → Service → Model → QueryBuilder, conforme [arquitetura](architecture.md).
Os endpoints de máquina são despachados antes da sessão web em `public/index.php`.
Cookies não identificam usuário, tenant ou autorização nessas rotas. O fluxo web
usa o login existente e CSRF. O transporte MCP fica no SDK, sem implementação
manual do JSON-RPC. O SDK negocia clientes com `initialize` e clientes com
metadados por requisição no mesmo endpoint, sem tradução manual de mensagens.

Componentes em `app/Controllers/Mcp`, `app/Services/Mcp` e `app/Models/Mcp`:

- `Gateway`: HTTP, discovery, autenticação, limites e entrega de respostas.
- `AuthorizationService` e `OAuth/*`: consentimento, grants e persistência dos
  identificadores de credenciais; JWT com emissor e audiência específicos.
- `Context` e `Store`: identidade imutável e autorização atual no banco.
- `Catalog`, `ToolService`, `ReadModel`: schemas fechados, autorização por ferramenta
  e projeções explícitas de leitura, sem uso dos Controllers web de CRUD.
- `WebController`: consentimento, listagem paginada e revogação própria ou do tenant.
- `ClientRegistry`: validação e busca de CIMD exclusivamente para aprovação operacional.

Referências oficiais revalidadas em 16/11/2026: protocolo `2026-07-28`, `mcp/sdk`
`0.10.0` e `league/oauth2-server` `9.4.1`, compatíveis com os requisitos de PHP 8.3.
O SDK ainda é experimental. PSR-7: `nyholm/psr7` `1.8.2` e
`nyholm/psr7-server` `1.1.0`. Versões resolvidas no `composer.lock`, agora versionável.
Não atualizar dependências sem repetir os testes e revisar esta documentação.

- [Versionamento MCP](https://modelcontextprotocol.io/specification/versioning)
- [Transporte](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http)
- [Autorização MCP](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization)
- [SDK PHP](https://github.com/modelcontextprotocol/php-sdk/releases)
- [OAuth Server](https://github.com/thephpleague/oauth2-server/releases)

## Identidade do servidor e pacote privado do ChatGPT

A versão de identificação é `1.0.2`: nome `7Carros`, título `7Carros Locadora`,
descrição das cinco categorias de consulta e site `https://7carros.com`. O SDK
serializa essa identidade em `result._meta["io.modelcontextprotocol/serverInfo"]`
na revisão adotada. Não mudar o formato de resposta manualmente.

Os manifests e o ZIP do plugin privado estão em `integrations/chatgpt/`, com
instruções no [README do pacote](../integrations/chatgpt/README.md). O ZIP contém
`plugin.json` e `mcp.json` na raiz e `assets/logo.png`, autor/desenvolvedor `7Carros`, categoria
`Productivity`, campo próprio `websiteURL` e OAuth com preferência CIMD. A autoria
exibida no ChatGPT pode depender da identidade da conta; publicação pública usa a
identidade verificada. A atualização do servidor não preenche por si só os campos
de apresentação do plugin criado apenas pelo formulário de URL.

Caminho confirmado no navegador: **Plugins → Adicionar → Enviar arquivo compactado
do plugin**. O usuário fará o upload e a instalação privados. Não foram alterados
os limites do piloto, as ENVs, as permissões ou o cadastro do cliente OAuth. Não há
submissão pública, migration ou instalação de dependências nesta atualização.

Validação: o manifest do plugin e o núcleo portátil de MCP passaram nos schemas
Agent Plugins 1.0.0; a extensão OAuth/CIMD foi conferida separadamente com a
referência OpenAI. O schema genérico MCP rejeita `extensions`, embora o exemplo
OAuth da OpenAI o exija. O pacote segue o exemplo OpenAI, com essa divergência
registrada. A aceitação do ZIP e a exibição final de site/desenvolvedor dependem
do upload real, ainda pendente; não equivalem a homologação externa concluída.

## Endpoints e autenticação

| Método | Caminho | Finalidade |
|---|---|---|
| POST | `/mcp` | MCP com bearer token |
| GET | `/.well-known/oauth-protected-resource/mcp` | RFC 9728; recurso e servidor de autorização |
| GET | `/.well-known/oauth-authorization-server` | RFC 8414; endpoints, PKCE e escopos |
| GET/POST | `/oauth/mcp/authorize` | Login web, seleção de categorias e consentimento; POST com CSRF |
| POST | `/oauth/mcp/token` | Troca de código ou refresh; formulário URL encoded |
| POST | `/oauth/mcp/revoke` | RFC 7009; revoga a conexão inteira |
| GET | `/pages/integracoes/mcp` | Administração no menu Sistema → Integrações → IA/MCP |
| POST | `/integracoes/mcp/revogar` | Revogação web com CSRF |

Authorization Code + PKCE S256 é obrigatório inclusive para aplicativos
confidenciais. Retornos são HTTPS e previamente cadastrados, com correspondência
exata. A exceção restrita do Claude Code para dois callbacks loopback HTTP
é descrita abaixo e não se aplica aos demais clientes. Não há grant sem funcionário nem DCR público. O parâmetro `resource` deve
ser exatamente `MCP_ISSUER` + `/mcp`, na autorização e no token endpoint. O fluxo
exige `state`; a resposta de autorização identifica o emissor com `iss` (RFC 9207).
Autenticação de cliente: `none` para públicos e `client_secret_post` para
confidenciais. Não anunciar nem utilizar `client_secret_basic` nesta entrega.

Access tokens duram 10 minutos; códigos duram 60 segundos e são de uso único.
Refresh tokens têm rotação, detecção de reutilização e limite absoluto de 90 dias
por consentimento, sem prorrogar esse prazo a cada renovação. Reutilização revoga
a conexão inteira. Emissão e consumo são transacionais, com bloqueio das linhas
de credenciais; JWT valida assinatura RSA, emissor, audiência, validade e registro
persistido. No banco ficam hashes dos identificadores, nunca os tokens completos.

Aplicativos aprovados ficam na tabela global `mcp_clients`, sem `chave`. Conexões,
credenciais e auditorias têm `chave`. A resolução inicial por identificador/hash é
restrita à autenticação; não oferece pesquisa genérica dos dados de negócio.

CIMD usa um client ID HTTPS com caminho, cujo documento é buscado e aprovado pelo
operador antes de qualquer consentimento. Hosts devem constar de `MCP_CIMD_HOSTS`.
A busca valida destinos públicos, fixa o IP na conexão TLS, desabilita proxy e
redirecionamento automático e revalida cada redirecionamento absoluto, até três.
Limites: 64 KiB por resposta e cinco segundos por requisição. Não aceita IPs
privados, especiais ou literais na URL, credenciais, fragmentos ou query na URL
CIMD. O documento deve identificar o client ID original. Quando existe
`token_endpoint_auth_methods_supported`, essa lista define os métodos aceitos;
na sua ausência, usa-se `token_endpoint_auth_method` (padrão `none`). É obrigatório
haver interseção com `none`, o método CIMD implementado. Uma preferência singular
por `private_key_jwt` não invalida uma lista que também oferece `none`.

Somente `authorization_code` e `refresh_token` são negociados; grants extras
anunciados pelo cliente não são habilitados no servidor. `authorization_code` é
obrigatório, com PKCE S256. O cadastro preserva documento original, capacidades
selecionadas, SHA-256 e data da verificação. Alterações não
são adotadas automaticamente: exigem revisão operacional do cadastro e conexões.

## Isolamento, permissões e ciclo de vida

Toda consulta de negócio começa com `table(...)->withChave($context->chave)`.
`table()` relê a sessão; por isso `withChave()` vem depois. Os JOINs com filiais e
funcionários verificam também igualdade de `chave`. Contexto inválido é rejeitado
antes dos Models de leitura. Nenhuma ferramenta aceita `chave`, SQL, tabelas,
caminhos ou URLs. Ver [QueryBuilder](querybuilder.md) e [multi-tenancy](multi-tenancy.md).

A cada chamada, o acesso efetivo é consentimento ∩ permissões atuais ∩ filiais
atuais, consultados no banco sem o cache web nem privilégios especiais de suporte.
A migration cria `mcp.conectar`, `mcp.administrar`, `mcp.documentos` e `mcp.contatos`
sem concessão automática. O administrador deve atribuí-las explicitamente às
roles, em `/pages/roles/gerenciar`, respeitando [roles](roles.md).

A leitura concluída de vínculos vazios significa acesso total conforme a regra
existente. Esse resultado vira um indicador explícito `allBranches`; falha de
consulta nunca vira acesso total. Vínculos inválidos resultam em lista restrita
vazia. Locações permitem retirada OU devolução; contratos usam retirada. Mesmos
filtros em listagens, detalhes, paginação e totais. Ver [filiais](filial-helper.md).

Somente funcionário existente, vinculado à mesma `chave` e com `status = 'A'`
pode autorizar, renovar e consultar. Suspensão preserva consentimentos e refresh
tokens; reativação permite retomada enquanto válidos. A exclusão impede uso de
credenciais remanescentes. O término WHMCS já descobre e remove as novas tabelas
com `chave`, sem estado paralelo de assinatura. Ver [WHMCS](whmcs.md).

A autorização é revalidada antes de entregar resultados. Nas emissões OAuth há
nova leitura após commit para não usar o snapshot anterior a uma suspensão.
Revogação própria permanece disponível ao funcionário ativo mesmo após perder a
permissão de conectar; administradores com `mcp.administrar` revogam no tenant.

### Abrangência exibida no consentimento

O consentimento mostra **Empresas/filiais com acesso**, a quantidade e a lista
completa de unidades disponíveis ao funcionário, exibindo somente os nomes, sem
IDs. Usa nome fantasia, razão social como alternativa e `Empresa/filial` quando
ambos estão vazios. Nomes repetidos permanecem na lista, um por unidade. Nomes
são escapados no HTML; nenhum dado de outro tenant é exibido.

A lista usa a mesma resolução de `Store::branches()` aplicada às consultas MCP:
sem vínculos restritivos, todas as unidades do tenant; com vínculos, somente as
unidades válidas permitidas. Vínculos inválidos não viram acesso total. Lista vazia
exibe ausência de unidades; falhas de leitura impedem apresentar o consentimento.

A abrangência é informativa e acompanha as permissões atuais, revalidadas na
confirmação e nas consultas. A tela não cria uma seleção de empresas por conexão.
Todas as categorias de consulta disponíveis podem ser autorizadas juntas;
começar somente por Frota é uma sugestão para simplificar a homologação inicial.

### Confirmação do consentimento, CSP e reenvios

A CSP permite `form-action 'self'` e somente a origem HTTPS do callback validado
pela biblioteca OAuth para a tentativa atual. O retorno completo continua exigindo
correspondência exata com o cadastro aprovado. `frame-ancestors 'none'` e
`base-uri 'none'` são preservados. Não usar wildcard nem origem retirada de um
parâmetro sem validação: Chromium pode bloquear o retorno externo após o POST
quando a política permite apenas `self`.

Autorização e recusa retornam HTTP 303, com o `Location` produzido pela biblioteca
e os parâmetros `state` e `iss` preservados. O JavaScript específico da tela
copia a decisão para um campo oculto antes de desabilitar ambos os botões,
mostra `Autorizando…`/`Recusando…` e impede um segundo submit. O POST tradicional
continua funcionando sem JavaScript. Somente a versão minificada é publicada.

No servidor, decisão, categorias, identidade e tentativa são validadas antes do
consumo. O callback de confirmação remove o pendente e grava um marcador de uso
único na sessão, sob o lock nativo do PHP. A sessão é persistida e liberada antes
da emissão; uma repetição não emite outro código. Marcadores duram cinco minutos
e são limitados a 20 por sessão. Tentativas consumidas não são restauradas quando
a emissão falha. As permissões atuais continuam sendo revalidadas.

Respostas do consentimento: 400 para formulário inválido, 403 para acesso negado,
409 para tentativa já enviada, 410 para tentativa expirada/inexistente e 503 para
falha interna. Erros mostram orientação de reinício e referência da requisição.
Não repetir o POST nem reutilizar a tela antiga após esta publicação: fechar essa
tela e iniciar uma nova conexão pelo ChatGPT, com novo state/PKCE/consentimento.

Logs `[MCP-CONSENT]` registram request ID, etapa, status, duração e classe da
exceção quando houver; o Ray ID Cloudflare é incluído somente quando corresponde
ao formato esperado. Não gravam exceções completas, queries, URLs completas,
formulário, cookies, nonce, state, códigos OAuth ou tokens. `response_ready`
significa que a resposta foi preparada, não que o ChatGPT concluiu a conexão.
O mesmo request ID identifica a resposta e a auditoria da autorização.

Diagnóstico em 16/11/2026: console Edge confirmou violação de `form-action 'self'`;
duas tentativas recentes tinham autorização e código persistidos, sem tokens.
O erro 520 relatado não teve causa confirmada nos logs acessíveis. Se reaparecer,
correlacionar horário, referência/Ray ID com os logs da origem antes de concluir
a homologação; nenhuma proteção do Cloudflare foi desabilitada.

## Catálogo, argumentos e respostas

| Categoria / escopo | Ferramentas | Permissão |
|---|---|---|
| Frota / `frota:read` | `listar_veiculos`, `obter_veiculo` | `veiculos.visualizar` |
| Clientes / `clientes:read` | `listar_clientes`, `obter_cliente` | `clientes.visualizar` |
| Contratos / `contratos:read` | `listar_contratos`, `obter_contrato` | `contratos.visualizar` |
| Locações / `locacoes:read` | `listar_locacoes`, `obter_locacao` | `locacoes.visualizar` |
| Financeiro / `financeiro:read` | `listar_lancamentos`, `obter_lancamento`, `resumir_financeiro` | `financeiro.visualizar` |

Todas exigem também `mcp.conectar`. `tools/list` exibe somente as disponíveis;
`tools/call` repete a autorização. Schemas completos são definidos em `Catalog`
e publicados pelo SDK, com `additionalProperties: false`.

- Listagens aceitam `filtros`: `pagina` (padrão 1), `por_pagina` (padrão 25, máximo
  100), `busca` (até 100 caracteres), `filial_id` e `status`.
- Clientes, contratos, locações e financeiro também aceitam `data_de` e `data_ate`
  em ISO `YYYY-MM-DD`, inclusivas. Campo de referência: cadastro do cliente, fim
  do contrato, previsão de devolução e vencimento financeiro, respectivamente.
- Financeiro aceita `tipo`: `R` ou `D`. Seu `status` corresponde a `pago` (`S`/`N`).
  Demais status correspondem a disponibilidade de veículos, situação de clientes
  ou status do contrato/locação. Busca usa placa/marca/modelo, nomes de clientes
  ou código do contrato/locação/lancamento; não pesquisa documentos.
- Detalhes exigem `id`. Somente `obter_cliente` aceita `incluir_documentos` e
  `incluir_contatos`, ambos falsos por padrão, com os respectivos escopos
  `clientes:documentos` / `clientes:contatos` e permissões adicionais.
- `resumir_financeiro` aceita os filtros financeiros sem paginação; agrupa por
  moeda, tipo e pagamento. Cada linha financeira é uma parcela, incluindo a
  primeira/origem. Não soma os itens ou a fatura operacional novamente. Moeda
  desconhecida permanece `null` e não é misturada com BRL. Ver
  [financeiro](financeiro.md) e [moeda](currency.md).

Respostas possuem `dados`, `filtros` aplicados e `consultado_em` ISO com offset;
listagens também têm `paginacao` (`pagina`, `por_pagina`, `total`), ordenadas por ID.
Detalhe inexistente ou inacessível retorna `dados: null`. Datas dos registros usam
`YYYY-MM-DD`; campos `DATETIME` usam `YYYY-MM-DD HH:MM:SS` na referência
`APP_TIMEZONE` do armazenamento, sem depender da sessão web. Ver [datas](date.md).
Valores monetários são
strings decimais, sem conversão para float. Campos resumidos excluem observações
livres e dados pessoais adicionais; o lançamento inclui sua descrição. Documentos
permitidos: CPF/CNPJ, RG/IE, número/categoria/validade da CNH. Contatos permitidos:
e-mails e telefones. Nunca retornar senha, código de segurança da CNH, assinaturas,
arquivos privados, tokens ou credenciais de gateway.

O transporte usa `Server::builder()->build()`
e `StreamableHttpTransport`, com seleção do fluxo feita pelo SDK:

- Revisões `2025-03-26`, `2025-06-18` e `2025-11-25`: `initialize` negocia a versão,
  `notifications/initialized` recebe 202 sem corpo e as consultas usam o header
  `MCP-Protocol-Version`. Não exigem `_meta`, `MCP-Method` ou `MCP-Name` da revisão
  de 2026. Na ausência de header, vale o padrão do SDK (`2025-03-26`). O SDK também
  conhece a revisão `2024-11-05`; esta entrega não homologa transporte HTTP+SSE legado.
- Revisão `2026-07-28`: `server/discover` opcional, sem `initialize`; exige `_meta`
  com `io.modelcontextprotocol/protocolVersion` e
  `io.modelcontextprotocol/clientCapabilities`, além de `MCP-Protocol-Version` e
  `MCP-Method`; chamadas de ferramentas também usam `MCP-Name`. Divergências
  são rejeitadas pelo SDK.

Não há sessão MCP persistente nem `Mcp-Session-Id`. Cada requisição valida bearer,
permissões e filiais atuais; nenhum estado de `initialize` autentica a próxima.
GET e DELETE em `/mcp` retornam 405 com `Allow: POST, OPTIONS`; não há stream GET.
A aceitação das revisões negociadas substitui a restrição anterior de revisão
exclusiva, conforme a correção aprovada para descoberta do ChatGPT. Não criar
fallback ou protocolo manual.

Exemplo para `2026-07-28`, com credencial fornecida separadamente no header:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {}
    },
    "name": "listar_contratos",
    "arguments": {"filtros": {"status": "A", "por_pagina": 25}}
  }
}
```

Textos de registros são conteúdo, não instruções. As ferramentas não alteram
cadastros, pagamentos, mensagens, PDFs ou anexos. Escritas limitam-se à própria
infraestrutura OAuth, auditoria e último acesso da conexão.

## Configuração, publicação e operação

Variáveis documentadas em `.env.example`:

- `MCP_ENABLED=false`: desativada por padrão; desligar interrompe acesso e emissões.
- `MCP_PILOT_MODE=false`: em produção, exige aplicativo homologado. Com `true`, permite
  testar aplicativo aprovado ainda pendente, limitado às três listas abaixo.
- `MCP_ALL_TENANTS=false`: fora do piloto, `true` libera locadoras atuais e futuras sem lista; não concede permissões nem dispensa consentimento ou homologação. Em piloto, as listas continuam obrigatórias mesmo com essa flag. Somente o valor literal `true` amplia acesso.
- `MCP_PILOT_EMPLOYEE_IDS`: IDs numéricos de funcionários autorizados, separados por vírgula.
- `MCP_PILOT_CLIENT_IDS`: client IDs exatos autorizados no piloto, separados por vírgula.
- `MCP_ISSUER`: origem HTTPS pública, sem caminho, query ou credenciais.
- `MCP_PILOT_TENANTS`: chaves permitidas, separadas por vírgula; vazio bloqueia quando o piloto está ativo ou a liberação global está desativada.
- `MCP_ALLOWED_ORIGINS`: origens adicionais exatas, separadas por vírgula; sem wildcard.
- `MCP_PRIVATE_KEY`: caminho absoluto da chave RSA privada, com prefixo `file://`.
- `MCP_PUBLIC_KEY`: caminho absoluto da chave pública correspondente.
- `MCP_ENCRYPTION_KEY`: segredo aleatório com pelo menos 32 caracteres.
- `MCP_CIMD_HOSTS`: hosts exatos aprovados para busca de documentos CIMD.
- `MCP_CONNECTION_RATE_LIMIT=60` e `MCP_TENANT_RATE_LIMIT=300`: janela de 60 segundos.

Gerar as chaves **no servidor**, em arquivo protegido e fora de `public/`, sem
versionar ou transferir pelo FTP. Na raiz do projeto:

```bash
umask 077
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out storage/certificates/mcp-private.pem
openssl pkey -in storage/certificates/mcp-private.pem -pubout -out storage/certificates/mcp-public.pem
```

Gerar o segredo de criptografia com um gerador criptográfico e inseri-lo somente
na configuração protegida do servidor. A aplicação não gera nem imprime segredos.
Configurar caminhos absolutos das chaves e manter `MCP_ENABLED=false` até a liberação.
Não trocar chaves com autorizações em uso sem planejar sua invalidação.

Após a publicação dos arquivos, conferir as migrations pendentes e, **no terminal
do servidor, na raiz do projeto**, executar:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
php migrate.php --env=production
```

O segundo comando aplica todas as pendências, não somente MCP. Não usar wrappers,
executar `up()` manualmente ou alterar a tabela de migrations. O `.env.production`
do servidor deve usar `DB_HOST=localhost`. Ver [migrations](migrations.md),
[banco](database.md) e [desenvolvimento/publicação](development.md).

Aprovar aplicativo próprio com retornos exatos, depois de configurar o ambiente:

```bash
php scripts/mcp-cadastrar-aplicativo.php --env=production --client-id=assistente-piloto --name='Assistente piloto' --redirect-uri=https://assistente.example/callback --approve
```

Para CIMD, usar `--client-id=https://aplicativo.example/oauth/client.json --cimd`
em vez de `--name`/`--redirect-uri`, com o host previamente aprovado. Segredo de
cliente confidencial é recebido somente por variável de processo
`MCP_CLIENT_SECRET`; persistido como hash. Nunca colocá-lo na linha de comando.
Não há substituição automática de cadastros existentes: aprovação idêntica é
idempotente, mas alterações exigem revisão. `--homologated` não é mais aceito.
A homologação usa uma operação separada, com referência dos testes concluídos.
Esse script é configuração operacional, não executor de migrations ou permissões.

### Primeiro teste do ChatGPT, exclusivo do tiagocaus

A dependência e a migration já foram executadas pelo responsável. Esta correção
não acrescenta dependências ou migrations. Como há classes novas, regenere o
autoload otimizado no terminal do servidor, na raiz do projeto:

```bash
cd /home/7carros/public_html/sistemas/locadora
/opt/alt/php83/usr/bin/php "$(command -v composer)" dump-autoload --no-dev --optimize --no-interaction
```

Mantenha `MCP_ENABLED=false` durante a preparação. Configure no `.env.production`:

```dotenv
MCP_PILOT_MODE=true
MCP_PILOT_EMPLOYEE_IDS=ID_NUMERICO_DO_TIAGOCAUS
MCP_PILOT_CLIENT_IDS=https://chatgpt.com/oauth/client.json
MCP_CIMD_HOSTS=chatgpt.com
```

Preserve `MCP_PILOT_TENANTS` com somente a chave da sua locadora, o emissor
`https://locadora.7carros.com`, as chaves RSA e o segredo já configurados. O valor
de `MCP_PILOT_EMPLOYEE_IDS` é o ID do funcionário, não seu nome de login.
Na primeira execução do diagnóstico, ele aparece em `employee[].employee_id`,
mesmo que a lista ainda esteja vazia. Complete o ID e repita o diagnóstico.

Aprove o documento público oficial, sem homologá-lo antecipadamente:

```bash
/opt/alt/php83/usr/bin/php scripts/mcp-cadastrar-aplicativo.php --env=production --client-id=https://chatgpt.com/oauth/client.json --cimd --approve
/opt/alt/php83/usr/bin/php scripts/mcp-cadastrar-aplicativo.php --env=production --client-id=https://chatgpt.com/oauth/client.json --check --username=tiagocaus
```

O diagnóstico não grava no banco nem imprime segredos. Verifica ambiente CLI,
dependências, migration, par RSA, permissões restritas da chave privada, documento
aprovado e usuário/locadora permitidos. A condição para avançar é
`ready_to_enable: true`. A flag pode continuar `false` nesse resultado. Confira
também se o usuário do PHP web consegue ler as chaves: o resultado CLI não prova
a leitura pelo PHP-FPM. Não gere novamente as chaves existentes se elas passarem.

Depois dos pré-requisitos, configure `MCP_ENABLED=true`. No navegador, confira:

- `https://locadora.7carros.com/.well-known/oauth-authorization-server` deve retornar
  JSON com o emissor e os endpoints, sem login e sem erro 403 de infraestrutura.
- Um POST sem token em `/mcp` deve retornar 401 e o challenge OAuth.

O diagnóstico externo desta máquina identificou Cloudflare **1010**
(`browser_signature_banned`): bloqueio pela assinatura do cliente HTTP, antes do
PHP. A compatibilidade das requisições do ChatGPT com a política do WAF ainda
precisa ser verificada. Não desligue proteções nem confunda essa resposta com um
teste OAuth aprovado.

Na conta do ChatGPT, o usuário confirmou o caminho **Explorar → adicionar plugin**.
Informe `https://locadora.7carros.com/mcp` como URL do servidor. Use OAuth; caso
apareçam campos de client ID e segredo, o ID aprovado é
`https://chatgpt.com/oauth/client.json` e o fluxo público não usa segredo. Se a
interface exigir um segredo ou outro client ID, interrompa e confira a configuração
em vez de inventar credenciais. A disponibilidade e os nomes dos campos dependem
da interface efetivamente apresentada.

Entre no 7Carros como `tiagocaus`, confira aplicativo e locadora e consinta somente
**Frota** no primeiro teste. Peça ao ChatGPT: “Liste até cinco veículos da minha
frota, com placa e situação”. Confirme que a conexão aparece em
**Integrações → IA/MCP**. Registre autorização, consulta, renovação após expiração
do access token (10 minutos), revogação nessa tela e bloqueio da próxima consulta.
Não registre tokens ou dados privados como evidência. O celular é uma verificação
separada; o resultado no navegador não comprova suporte no aplicativo móvel.

Somente depois desses testes, registre a referência real da evidência:

```bash
/opt/alt/php83/usr/bin/php scripts/mcp-cadastrar-aplicativo.php --env=production --client-id=https://chatgpt.com/oauth/client.json --mark-homologated --evidence=REFERENCIA_REAL_DOS_TESTES
```

A operação confere novamente o documento e não amplia o piloto. Com
`MCP_PILOT_MODE=true`, outro funcionário com as mesmas permissões continua
bloqueado, mesmo depois da homologação. Lista vazia de locadoras, funcionários ou
aplicativos bloqueia acesso; remoções bloqueiam autorização, chamadas e renovação
de credenciais já emitidas. `MCP_ENABLED=false` interrompe toda a integração.
Ampliar o acesso exige uma decisão operacional posterior e nova verificação.

Rate limiting usa arquivo por tenant com lock exclusivo compartilhado pelos
workers do mesmo servidor em `storage/cache/mcp-rate`; falhas bloqueiam acesso.
Antes de escalar para múltiplos servidores, substituir por armazenamento atômico
compartilhado e repetir os testes. Endpoints OAuth têm limite pré-autenticação por
IP. Consultas limitam execução MySQL e processamento a 15 segundos e revalidam o
prazo antes da entrega. Validar `Origin` presente e Host/esquema do endpoint.

Auditoria em `mcp_audit`: tenant, funcionário, conexão/aplicativo, evento/ferramenta,
resultado, quantidade, duração e request ID. Auditoria própria evita depender da
sessão usada pelo logger web; ver [logs](logs.md). Conforme a evolução de conteúdo
aprovada em 16/11/2026, chamadas de ferramentas passam a guardar argumentos
declarados e o retorno entregue em colunas próprias. Dados pessoais retornados
com autorização também ficam nesse histórico, acessível por `logs.visualizar`,
sem expiração automática. Nunca gravar tokens, headers, SQL ou exceções internas.
Falhas sem identidade validada registram
somente request ID e resultado no log do servidor. Headers de resposta incluem
`X-Request-Id` para diagnóstico.

| Resposta | Diagnóstico |
|---|---|
| 503 | Flag desligada, dependências/configuração ausentes ou serviço indisponível |
| 401 + challenge | Token ausente/inválido ou vínculo sem autorização atual |
| 403 | Origem inválida, autorização negada ou alteração de acesso durante consulta |
| 429 | Limite atingido; respeitar `Retry-After` |
| Erro MCP de revisão/metadados | Conferir o fluxo negociado; `_meta` e headers específicos são obrigatórios em `2026-07-28` |
| Resultado de ferramenta com `isError` | Filtros inválidos ou consulta não autorizada/indisponível |

## Evidências e manutenção

Em 16/11/2026, `php tests/test_mcp_integration.php` aprovou **137 verificações** com
fixtures descartáveis e sem enviar mensagens. Abrange OAuth completo, schemas,
11 ferramentas, cookie de outro tenant, IDs externos, filiais, paginação, moedas,
credenciais inválidas, documentos/contatos, suspensão/reativação e suspensão
concorrente durante leitura, usuário excluído, revogação, refresh reutilizado e
limites. A regressão `tests/test_whmcs_tenant_termination.php` passou, incluindo o
inventário das novas tabelas tenant. Templates administrativo e de consentimento
renderizados; consentimento inclui CSRF. Endpoint real local com flag desligada
retorna 503 sem cookie de sessão. A verificação HTTP externa após a publicação
recebeu 403 tanto em `/mcp` quanto em `/login`; a resposta da aplicação em
produção ainda precisa ser validada no ambiente do servidor. A publicação foi
conferida separadamente por SHA-256 via FTP.

O ciclo local aplicar → reverter → reaplicar passou pelo executor oficial. O teste
revelou e corrigiu no rollback do `migrate.php` a ausência da verificação de
transação ativa após DDL MySQL, já existente no fluxo de aplicação.

PHP lint e validação Composer fazem parte da entrega. O Composer audit encontrou
21 avisos em cinco dependências já presentes em produção; nenhum nos pacotes novos MCP/OAuth.
O lock final preserva todas as versões já instaladas no servidor e acrescenta
26 pacotes, sem downgrade. O ambiente local foi alinhado a essa base antes da
validação final. Os avisos restantes exigem atualização de outros módulos. Testes locais não comprovam conformidade integral nem compatibilidade de
aplicativos externos. A busca real do CIMD oficial do ChatGPT passou localmente; o fluxo externo completo continua pendente. A suíte `tests/test_mcp_cimd.php` aprovou 51 verificações adicionais de negociação, URLs e política do piloto.

Antes de liberar, registrar para cada aplicativo: data, nome/versão disponível,
revisão, autorização, consulta, renovação, revogação e resultado. Clientes
incompatíveis permanecem bloqueados. Quem alterar ferramentas, autenticação,
permissões, configuração, dependências, protocolo ou operação deve atualizar esta
documentação e os testes na mesma entrega. Sem documentação atualizada e
homologação dos aplicativos, não ampliar a liberação.

Em 16/11/2026, a correção da abrangência no consentimento acrescentou 11
verificações à suíte de integração: uma/várias unidades, acesso total/restrito,
isolamento entre tenants, vínculos inválidos, nomes vazios/repetidos, escape HTML,
CSRF e mudança de vínculos entre apresentação e confirmação com todas as
categorias. Nenhuma migration, dependência ou fonte CSS/JS foi alterada.

Publicação da correção de abrangência: cinco arquivos conferidos por SHA-256 via FTP; tela de produção validada no navegador com quatro unidades e cinco categorias disponíveis. O consentimento não foi enviado pelo agente. Não é necessário executar Composer ou migrations para esta correção.

Correção de confirmação do consentimento: suíte ampliada para 137 verificações de
integração, incluindo HTTP real em loopback com sessão/CSRF, GET/POST, CSP,
303 com state/iss, recusa, reenvio, expiração, entrada inválida, suspensão e erro
interno sem detalhes sensíveis. A suíte CIMD/piloto manteve 51 verificações.
No Edge, teste descartável com o JS minificado confirmou clique duplo com um POST,
retorno 303 entre duas origens locais e recusa por teclado. Não foi enviado
consentimento real do usuário nem concluída homologação externa nessa verificação.


## Correção de descoberta após OAuth — 16/11/2026

A tentativa real registrou autorização e três emissões de token bem-sucedidas,
sem auditoria de requisição MCP autenticada. Isso confirma o avanço do OAuth, mas
não identifica a causa final da falha de descoberta. O transporte anterior
reproduziu localmente HTTP 400 para `initialize` de 2025 e para `tools/list` sem
os metadados de 2026. A correção passa a usar os dois fluxos nativos do SDK
`0.10.0`, com o mesmo catálogo e contexto por requisição, mantendo a identidade
`1.0.1`. Nenhuma dependência, migration, configuração do piloto ou fonte JS/CSS
foi alterada.

Logs `[MCP]` em JSON identificam `request_received`, `authenticated`,
`authentication_rejected`, `transport_response`, `oauth_rejected`, `unavailable`
e `response_ready`. Campos técnicos: request ID, método HTTP normalizado, rota
conhecida, revisão do header normalizada, status, duração e classe de exceção.
Não há logging de corpo, tokens, argumentos, respostas de negócio, query strings,
origens ou mensagens completas de exceção. Todas as respostas do Gateway, inclusive
antecipadas e exceções, recebem `X-Request-Id` e `Cache-Control: no-store`.
`response_ready` não comprova recebimento pelo cliente.

Para diagnóstico real, correlacionar o horário da tentativa com acesso HTTP a
`/mcp`, logs `[MCP]` do PHP e eventos Cloudflare. Sem `request_received`, verificar
infraestrutura/roteamento antes de atribuir a falha ao protocolo. Com
`authentication_rejected`, investigar o bearer; com `transport_response`, conferir
status e revisão. Os logs de erro PHP não estavam disponíveis no diretório FTP
consultado; a requisição exata do ChatGPT permanece sem confirmação.

Schema conferido por terminal: `mcp_clients`, `mcp_connections`,
`mcp_credentials` e `mcp_audit`. A diferença de nulabilidade de `created_at`
(local aceita NULL, produção não) decorre de `TIMESTAMP DEFAULT CURRENT_TIMESTAMP`
sem cláusula NULL explícita no helper de migrations e da configuração
`explicit_defaults_for_timestamp` (local 1, produção 0). Ambos têm default atual,
não há timestamps nulos em produção e as inserções MCP omitem essa coluna.
Não há impacto na troca de transporte nem necessidade de alterar o schema.

A homologação externa continua pendente: nova conexão pelo ChatGPT, descoberta,
consulta de até cinco veículos, renovação após dez minutos e revogação. Registrar
a revisão efetiva observada; não marcar homologação com base apenas no teste local.

Validação da correção: `php tests/test_mcp_integration.php` passou com 178
verificações, incluindo o novo bloco `tests/mcp-discovery-checks.php`;
`php tests/test_mcp_cimd.php` manteve 51 verificações aprovadas. PHP lint passou
nos arquivos PHP alterados. O bloco novo cobre negociação/inicialização,
catálogo, chamadas autenticadas, credencial inválida/revogada, cookie de outro
tenant, restrição por consentimento, headers de erro, limite de corpo e logs sem
credenciais; a suíte também verifica filiais, permissões e suspensão pelo fluxo
de 2025. Não envia e-mail, SMS ou WhatsApp.

A sondagem HTTP externa desta máquina continua recebendo 403 do Cloudflare, sem
`X-Request-Id`, nos metadados OAuth e em `/mcp` sem token. Esse resultado não
comprova o comportamento do tráfego do ChatGPT e não motivou alteração do WAF.

Publicação desta correção: seis arquivos (Gateway, ToolService, Readiness,
entrypoint público e os dois documentos) enviados por LFTP, com upload temporário,
renomeação e SHA-256 conferido por download. Cópias anteriores foram preservadas
localmente para reversão. Os checks do entrypoint e do diagnóstico CLI agora
verificam `StreamableHttpTransport`. Não é necessário executar Composer ou
migrations para esta correção. Os testes permanecem somente no repositório local.


## Repasse de Authorization no Apache/suPHP — 16/11/2026

O diagnóstico pelo console CWP localizou os logs reais em
`/usr/local/apache/domlogs/locadora.7carros.com.error.log`. Às 14:11:18 e 14:11:20
(horário do log), o ChatGPT enviou POST `/mcp` com revisão `2026-07-28` e recebeu
401 em `authentication_rejected`, antes do transporte. O HTTP 403 das sondagens
de outra máquina não representa essas chamadas reais.

O domínio utiliza Apache com suPHP para PHP 8.3. O header Authorization não estava
sendo repassado utilizavelmente ao PHP. A prova comparou o mesmo bearer sintético:
HTTP direto no Apache e pelo Nginx caía em RuntimeException antes do parser;
validação direta no PHP, como usuário da aplicação, atingia
`Lcobucci\JWT\Token\InvalidTokenStructure`. Nenhuma credencial real foi exibida.

A correção adiciona em `public/.htaccess`, antes do front controller, uma regra
restrita a `/mcp` que copia o Authorization recebido para `HTTP_AUTHORIZATION`.
O redirect interno pode expô-lo como `REDIRECT_HTTP_AUTHORIZATION`.
`Gateway::request()` preserva Authorization já presente no objeto PSR-7 e,
quando ausente, recupera as variáveis CGI por
`ServerRequestCreator::getHeadersFromServer()`. Não usa query string ou headers
alternativos como credenciais. A publicação preservou o AddHandler PHP 8.3 já
configurado no servidor, sem acrescentá-lo à fonte local usada pelo MAMP.

O diagnóstico de rejeição inclui somente motivos fixos:
`missing_authorization`, `invalid_bearer` e `token_rejected`, além da referência e
classe de exceção. Tokens e headers completos continuam fora dos logs MCP.
A validação JWT, os escopos, isolamento e limites não mudaram.

Validação local: 181 verificações de integração, 51 de CIMD/piloto e sete cenários
sem banco/rede em `php tests/test_mcp_request_headers.php`; lint PHP aprovado.
Os testes de headers cobrem CGI direto, redirect interno, precedência da variável
canônica e do header existente, header vazio, ausência e rejeição de credencial
por query string/header alternativo. A integração verifica os três motivos de
rejeição sem expor credenciais nos logs.

Após a publicação, os testes com bearer sintético no Apache às 14:22:11 e no Nginx
às 14:22:29 chegaram a `token_rejected` com `InvalidTokenStructure`. Referências:
`495cb2bce79b7a3bcd888059c1e3541d` e `36f949ace1ddf6228807f85a62a4c2a7`.
Continuam retornando 401 porque o bearer é propositalmente inválido; a mudança
confirma o repasse corrigido, não uma autenticação de usuário concluída.
Login e ambos os metadados OAuth retornaram 200; GET `/mcp` retornou 405 esperado,
sem erro 500 na origem.

Runtime e documentos publicados por LFTP com conferência SHA-256 e cópias
anteriores locais. Testes não são publicados. Não executar Composer, migrations
ou reiniciar serviços para esta correção. Nova conexão no ChatGPT, descoberta,
consulta autorizada, renovação e revogação reais continuam pendentes antes de
marcar homologação. Se surgir outra falha, correlacionar sua referência no log;
não atribuí-la automaticamente ao transporte ou Cloudflare.


## Host duplicado na criação PSR-7 — 16/11/2026

A tentativa posterior ao repasse do bearer chegou a `authenticated` e recebeu
403 no transporte, referência `c47b0f5aef5a82f745325e1e1e696b98`. Portanto,
a correção de Authorization funcionou, mas não concluiu a descoberta.

A reprodução no PHP do servidor mostrou duas entradas Host idênticas:
`locadora.7carros.com, locadora.7carros.com`. O `Psr17Factory` cria o header a
partir da URI; `ServerRequestCreator` acrescenta novamente o Host da SAPI por
`withAddedHeader()`. O middleware de proteção contra DNS rebinding rejeita a
linha resultante com `403 Forbidden: Invalid Host header.`

`Gateway::request()` agora remove somente essa duplicação de construção: o
primeiro valor precisa corresponder à autoridade da URI e os valores seguintes
precisam ser idênticos ao `HTTP_HOST` original. Isso preserva o Host recebido,
inclusive sua porta explícita, quando a URI omite uma porta padrão. Hosts
conflitantes não são sobrescritos e continuam sujeitos à rejeição do SDK.
Não há wildcard, troca pelo emissor configurado ou remoção da proteção.

Lacuna dos testes anteriores: chamadas de serviço criavam `ServerRequest`
diretamente, e os fixtures CGI omitiam Host em `getallheaders()`. Esses caminhos
não reproduziam a duplicação de `Gateway::request()`. Os fixtures CGI agora
incluem Host; a regressão cobre porta explícita/padrão, host ausente na SAPI,
valores conflitantes e domínio não permitido.

O harness HTTP local agora despacha `/mcp` antes da sessão web e chama
`Gateway::request()` e `Gateway::emit()` reais. Apenas a terminação TLS é simulada
no loopback. Os headers Host, Authorization, versão, cookie e o corpo JSON chegam
pela rede. Com OAuth e fixtures descartáveis, foram validados initialize,
notificação, tools/list e tools/call para os fluxos de 2025 e 2026, isolamento por
tenant/filial, ausência de bearer, Host não autorizado e revogação. Nenhum fixture
ou token de teste é publicado em produção.

Validação: 193 verificações de integração MCP, 51 CIMD/piloto e 14 cenários
CGI/SAPI aprovados, além de lint PHP. No PHP do servidor publicado, Host passou
a ter um único valor; o middleware real aceitou esse Host (200 no handler de
diagnóstico) e rejeitou Host conflitante (403). Esse diagnóstico em memória não
é uma descoberta real do ChatGPT e não consulta dados da locadora.

Gateway e os dois documentos publicados por LFTP, com SHA-256 conferido e cópias
anteriores locais. Nenhuma alteração de dependência, schema, piloto, JS/CSS ou
configuração do servidor. Não executar Composer nem migrations.
A homologação final continua exigindo nova conexão no ChatGPT, catálogo visível,
consulta autorizada, renovação e revogação, correlacionadas com os logs reais.


## Compatibilidade de timeout MySQL/MariaDB — 16/11/2026

A consulta real `listar_veiculos` falhou nas referências
`a8841d4181874819b2f7cee4d97d1933` e `71fdb6d419a9e870690e33e7d7734c62`.
A autenticação e o transporte concluíram, mas a ferramenta retornou isError.
Produção usa MariaDB 10.2.44; o ambiente local usa MySQL 5.7.44. O construtor de
ReadModel executava `SET SESSION MAX_EXECUTION_TIME = 15000`, que no MariaDB
produziu erro 1193 (variável desconhecida), antes da consulta de negócio.
A mensagem genérica ao usuário não significava, neste caso, falta de permissão.

ReadModel identifica MariaDB por `mysqli::server_info` na conexão Singleton do
Model e configura `max_statement_time = 15` (segundos). No MySQL, configura
`max_execution_time = 15000` (milissegundos). A configuração é de sessão, nunca
global. Se a configuração falhar, o erro é propagado e nenhuma consulta é
executada sem limite. No MariaDB o limite também alcança comandos que não sejam
SELECT; no MySQL limita SELECT. A revalidação de contexto e o limite total de
15 segundos do fluxo MCP continuam ativos.

Logs `[MCP-TOOL]` incluem somente request ID, nome fixo da ferramenta, etapa
(authorization, arguments, database_limit, query, revalidation, audit ou
serialization), classe da exceção e código numérico. Não contêm mensagens de
exceção, SQL, filtros, argumentos, tokens ou dados retornados. A resposta externa
permanece genérica e a auditoria continua isolada por tenant.

Schema local e de produção de veiculos, mcp_connections e mcp_audit conferido
via terminal; SELECT de contagem executado no localhost. Não há alteração de
schema ou da projeção de campos. Testes: 196 verificações MCP, 51 CIMD/piloto,
14 CGI/SAPI e lint aprovados. A suíte verifica o limite efetivo na sessão MySQL
e o diagnóstico seguro para autorização/filtros inválidos. A lacuna anterior
era testar apenas MySQL sem validar a configuração no MariaDB de produção.

Após publicar, a listagem pelo ReadModel no PHP do servidor, como usuário da
aplicação e com o contexto da conexão que falhou, passou: limite efetivo de
15 segundos, total de 19 veículos acessíveis e uma linha lida como amostra.
A resolução de contexto revalidou piloto, funcionário, escopos, permissões e
filiais; nenhum token novo foi emitido e nenhum cadastro foi alterado.

Publicação por LFTP com hashes e cópias anteriores; sem Composer, migrations,
assets ou mudança de permissões. O teste no mesmo chat do ChatGPT é registrado
separadamente abaixo; a validação do Model não substitui o teste externo.


Validação externa desta correção concluída no mesmo chat indicado pelo usuário
em 16/11/2026: foi enviada a pergunta “Consulte novamente pelo plugin 7Carros:
quantos veículos tenho no total?”. O ChatGPT retornou “19 veículos cadastrados
no total”. A auditoria registrou `listar_veiculos: success`, dez linhas na página,
47 ms, e `request: success`, 53 ms, às 14:46:57 (horário do banco), referência
`808d45d7a036b544773bd5463df77848`. Houve emissão de token bem-sucedida às 14:46:56,
sem novo consentimento. Não foram copiados tokens ou registros individuais.

Autorização, descoberta e consulta real do ChatGPT no piloto estão comprovadas.
Essa evidência não substitui a verificação específica de renovação após expiração
e de revogação; a homologação completa e a ampliação do piloto continuam pendentes.


## Visualização da auditoria em Logs do Sistema — 16/11/2026

A nova aba IA/MCP e `GET /api/logs/mcp` expõem uma projeção somente de leitura de
`mcp_audit`, com permissão `logs.visualizar` e isolamento pelo tenant do usuário
web. O contexto do funcionário que executou a ferramenta continua vindo do OAuth;
a sessão web serve somente para autorizar o visualizador do histórico.

São exibidas chamadas das ferramentas do catálogo, com funcionário, aplicativo,
data, operação, resultado, quantidade retornada, duração e referência técnica.
Eventos técnicos de transporte/token não aparecem nessa aba. `blocked` é exibido
como Não concluída, sem presumir que foi uma negação de permissão. Quantidade
retornada não significa total de registros da consulta.

A pergunta original e a resposta final do ChatGPT não chegam automaticamente ao
servidor MCP. A evolução de repasse abaixo permite receber a pergunta como
argumento informado pela IA.
A entrega inicial reutilizou apenas os metadados históricos. A evolução abaixo
passa a capturar solicitação e retorno das ferramentas, sem alterar o transporte.
A especificação e os testes estão em `docs/logs.md`.

Publicada por FTP e verificada na interface de produção em 16/11/2026: histórico
real, busca pelo nome traduzido, estado vazio e alternância entre as três abas
aprovados. A validação reutilizou as chamadas existentes, sem executar novas
consultas MCP ou enviar mensagens. Os arquivos publicados foram conferidos por
hash; os testes locais e a evidência da interface estão descritos em `docs/logs.md`.


## Conteúdo das chamadas em Logs do Sistema — 16/11/2026

A migration `00442_add_mcp_audit_payloads.php` adiciona `request_payload` e
`response_payload` LONGTEXT NULL em `mcp_audit`. Somente o executor oficial
`migrate.php` aplica a migration. Em produção, a execução é manual pelo responsável;
publicar o código dependente somente após confirmar a aplicação.

ToolService serializa o retorno antes de gravar sucesso e inclui os argumentos
declarados da ferramenta e o conteúdo devolvido na mesma inserção da auditoria.
Listagens, detalhes e resumo financeiro usam o mesmo fluxo. Falhas tratadas na
ferramenta guardam somente a mensagem pública de erro, nunca exceção/SQL. Rejeições
anteriores à execução pelo SDK/Gateway continuam como eventos técnicos, fora da aba.
As verificações de permissão/filial antes da leitura e entrega permanecem vigentes.

A captura começa após ativação do código: o histórico anterior possui conteúdo
nulo, sem reconstrução. O histórico não expira automaticamente. `logs.visualizar`
autoriza prévias e conteúdo completo do tenant, inclusive documentos/contatos ou
financeiro que tenham sido autorizados na consulta original. Não há nova permissão.
Tokens, credenciais, headers e diagnósticos internos continuam fora dos payloads.
Os logs técnicos `[MCP]` e `[MCP-TOOL]` continuam contendo somente metadados seguros.

A listagem fornece prévias de até 255 caracteres Unicode. O olho solicita
`GET /api/logs/mcp/{id}` e abre o painel lateral com o JSON completo e a quantidade
retornada. Essa entrega inicial não capturava a pergunta; o repasse descrito
abaixo acrescenta o texto informado pela IA, sem acesso direto à conversa.


Publicação desta evolução confirmada em 16/11/2026 após o responsável informar
sucesso na migration manual de produção. Os arquivos foram enviados por FTP e
conferidos por hash. A consulta real no mesmo chat do piloto retornou 19 veículos;
o histórico mostrou a chamada nova, prévias e painel completo, com dez registros
na página e 49 ms, referência `1c7619c05cf424e6f275879b5dd9b435`. O registro
histórico anterior permaneceu acessível com conteúdo nulo. Essa verificação não
substitui a homologação específica de renovação/revogação, ainda separada.

## Apresentação administrativa — 16/11/2026

O acesso fica em **Sistema → Integrações → IA/MCP**, usando o submenu de segundo
nível existente e preservando a habilitação por configuração/tenant. A rota
administrativa continua `/pages/integracoes/mcp`.

A tela apresenta um bloco informativo com **Nome do MCP: 7Carros**, **URL do MCP**
obtida de `Config::resource()` (em produção, `https://locadora.7carros.com/mcp`) e
**Autenticação: OAuth**. Esses dados não são campos editáveis. Abaixo, mantém
**Conexões autorizadas**, revogação com CSRF e o aviso sobre dados já compartilhados.
Títulos, tabela, estado vazio, botões e paginação usam os componentes visuais
existentes; validade e último acesso usam `format_datetime()` para respeitar
a configuração de exibição da empresa. O escopo não altera OAuth, permissões,
consultas, schema nem a página pública de consentimento.


## Repasse fiel da pergunta do cliente — 16/11/2026

As 11 ferramentas anunciam `pergunta_usuario` como argumento string opcional,
fora de `filtros`. O schema, as descrições e as instruções do servidor pedem uma
cópia fiel e literal da mensagem do usuário que originou a consulta, preservando
palavras, pontuação e quebras de linha. Não resumir, traduzir, reformular ou
completar; continuações como “E as ativas?” são copiadas literalmente. Chamadas
múltiplas/páginas da mesma solicitação repetem a mesma mensagem. Sem mensagem
disponível, omitir o campo: não inventar nem copiar histórico/instruções internas.

O argumento opcional preserva compatibilidade com clientes e schemas anteriores.
A identificação do servidor passa a 1.0.2. Clientes com catálogo em cache precisam
receber as ferramentas atualizadas; a versão sozinha não garante atualização.
A ausência da pergunta não impede consultas autorizadas.

O texto é gravado integralmente em `request_payload.argumentos.pergunta_usuario`,
sem normalizar espaços, pontuação ou quebras de linha, na mesma entrada da chamada.
Texto vazio/apenas espaços é tratado como ausência. Não participa de queries de
negócio, permissões, filtros nem dados de saída. O limite HTTP existente de 64 KiB
continua vigente. Não há migration, novo provedor de IA ou nova tabela.

A origem é a IA/cliente MCP, não uma captura direta da interface do ChatGPT.
Não é possível verificar independentemente que o texto repassado corresponde
exatamente à mensagem original. O histórico não é reconstruído; chamadas sem
campo mostram ausência explícita. Só há registro de perguntas que geram chamadas
MCP; mensagens respondidas pela IA sem ferramenta não passam pelo servidor.

A documentação oficial descreve chamadas com nome/argumentos e os metadados do
cliente, sem um campo público de transcrição original independente:
- https://modelcontextprotocol.io/specification/2025-11-25/server/tools
- https://developers.openai.com/plugins/reference#_meta-fields-the-client-provides

A listagem usa o texto em Solicitação ao MCP; Retorno do MCP fica somente no olho,
com os parâmetros técnicos e demais detalhes. Ver `docs/logs.md`.

Validação local desta evolução: 280 verificações de integração MCP, 51
CIMD/piloto e 14 CGI/SAPI aprovadas; teste de interface, lint PHP e sintaxe JS
aprovados. Sem execução de migrations nesta entrega.


Validação externa concluída em 16/11/2026 no mesmo chat do piloto. Após usar
“Atualizar ferramentas” em Gerenciar aplicativo no ChatGPT, as 11 descrições
passaram a anunciar `pergunta_usuario`; a conexão OAuth existente foi preservada.
A mensagem de teste teve duas linhas e emoji: “Consulte o 7Carros agora: quantos
veículos tenho no total?” seguida de “Pode conferir novamente, por favor? 🚙”.
O ChatGPT retornou 19 veículos e a chamada `listar_veiculos` registrou dez linhas,
49 ms, referência `a3c2284e631516e30e55d9c3604ad1e9`. O JSON recebido preservou
literalmente o texto; após corrigir a extração de Unicode do MariaDB antigo,
a listagem e o olho também mostraram o emoji e o painel preservou a quebra de
linha. Retorno do MCP não aparece na tabela de oito colunas e permanece nos
detalhes. Registros anteriores mostram “Pergunta não informada pela IA”.
Arquivos publicados por FTP com backups e hashes verificados. Nenhuma migration,
recriação de conexão ou alteração de permissões foi necessária.


## Pacotes com logo e cliente nativo Claude Code — 16/11/2026

O ZIP ChatGPT 1.0.2 contém os manifests portáteis e `assets/logo.png`, uma cópia
exata de `public/assets/img/ico.png` (151×151). `logo`, `logoDark`, `composerIcon`
e `composerIconDark` em `extensions.com.openai.interface` apontam para o mesmo
asset. A versão 1.0.1 permanece como histórico. Atualizar o plugin já existente
por **Mais ações → Enviar nova versão**, sem recriar a conexão OAuth.

O pacote Claude Code 1.0.0 tem `.claude-plugin/plugin.json`, `.mcp.json`, a logo e
README. É um plugin para extrair e carregar com `claude --plugin-dir`, não um ZIP
para upload no Claude Desktop. A configuração usa transporte `http` (Streamable
HTTP), endpoint HTTPS atual e `oauth.clientId=7carros-claude-code`,
`oauth.callbackPort=8765`. Nenhum segredo, bearer fixo ou arquivo de ambiente vai
nos ZIPs. O campo `icon` é metadado do diretório; Claude Code não o exibe ao carregar.
Na versão local 2.1.170, o validador passou com aviso de campo desconhecido, como
previsto na referência para versões anteriores a 2.1.281.

### Exceção restrita de callback nativo

Somente o cliente público estático `7carros-claude-code` aceita estas duas URLs,
comparadas literalmente, sem query, fragmento, credenciais, hosts ou portas extras:

- `http://localhost:8765/callback`
- `http://127.0.0.1:8765/callback`

Esse perfil não aceita client secret nem metadados CIMD. Os demais clientes
mantêm a exigência de HTTPS. Em `AuthorizationService::prepare`, o callback é
validado antes de entrar na biblioteca OAuth: `league/oauth2-server` ignora a
porta de loopback IPv4 por padrão (RFC 8252), mas o perfil aprovado aqui exige
porta fixa. A origem da CSP usa o esquema da URL previamente validada; não há
wildcard nem ampliação de CORS/Origins do endpoint remoto. O token endpoint
continua exigindo o redirect exato do código e o verificador PKCE S256.

### Operação no servidor

Após publicar os arquivos, executar na raiz do projeto no servidor (PHP 8.3):

```bash
/opt/alt/php83/usr/bin/php scripts/mcp-cadastrar-aplicativo.php --env=production --client-id=7carros-claude-code --name='Claude Code' --redirect-uri=http://localhost:8765/callback --redirect-uri=http://127.0.0.1:8765/callback --approve
```

Acrescentar `7carros-claude-code` ao valor existente de `MCP_PILOT_CLIENT_IDS` pelo
terminal do servidor, preservando todos os IDs atuais e sem alterar tenants,
funcionários ou permissões. Nunca enviar `.env.production` por FTP. Não mudar
`MCP_CIMD_HOSTS`: o perfil Claude estático dispensa CIMD e DCR. Conferir prontidão:

```bash
/opt/alt/php83/usr/bin/php scripts/mcp-cadastrar-aplicativo.php --env=production --client-id=7carros-claude-code --check --username=tiagocaus
```

O cadastro é uma aprovação operacional pelo executor já existente, não uma
migration. Nenhuma migration é necessária. Instalação local do pacote não autoriza
por si só uma locadora; o responsável deve entrar no 7Carros e confirmar as
categorias. Permissões, filiais, suspensão, auditoria, renovação e revogação seguem
o fluxo existente. Não marcar homologação completa sem as evidências específicas.

Validação local: 312 verificações de integração MCP, 69 CIMD/política e 14 CGI/SAPI
aprovadas. Cobertura inclui os dois callbacks, CSP e redirect HTTP reais,
verificador incorreto, porta divergente bloqueada antes da biblioteca, consulta
isolada, rotação e revogação. Os ZIPs passaram em integridade/JSON/caminhos; a logo
coincide por hash com a original. O manifest ChatGPT passou no schema oficial e
o núcleo MCP portátil foi validado sem a extensão OAuth, conferida separadamente.

Publicação concluída em 16/11/2026: os 14 arquivos do escopo foram enviados por
FTP e conferidos por SHA-256 após download. Os dois endpoints de descoberta
retornaram HTTP 200 com JSON válido. Pelo terminal CWP, como usuário `7carros`,
o cliente público foi aprovado e seu ID acrescentado ao piloto, preservando as
demais configurações. O diagnóstico de produção para `tiagocaus` retornou
`ready_to_enable: true`, com todas as verificações positivas. Não houve migration.

A homologação no aplicativo Claude Code permanece pendente de login na conta e
consentimento do responsável. A execução isolada local chegou à seleção de login
da conta Claude; não foi concedido acesso nem realizada consulta externa por esse
cliente. O pacote e os testes automatizados não substituem essa homologação.

Referências:
- https://developers.openai.com/plugins/deploy/submission#icons
- https://code.claude.com/docs/en/plugins-reference#directory-listing-fields
- https://code.claude.com/docs/en/mcp#use-pre-configured-oauth-credentials


## Claude no site claude.ai — conector remoto

Para consultas nas conversas normais do site, usar **Personalização → Conectores
→ Adicionar conector personalizado**. Esse fluxo é diferente do plugin carregado
pelo programa Claude Code no terminal; o ZIP `integrations/claude-code` destina-se
ao programa, e não ao formulário de conector web.

Configuração web:

- Nome: `7Carros`.
- URL: `https://locadora.7carros.com/mcp`.
- Autenticação: Entrar agora (OAuth).
- Cliente OAuth: Use seu próprio cliente OAuth.
- ID: `7carros-claude-web`.
- Segredo: vazio (cliente público com PKCE).
- Callback cadastrado: `https://claude.ai/api/mcp/auth_callback`.
- Sem cabeçalhos fixos ou tokens inseridos manualmente.

Esse cliente usa o fluxo HTTPS existente, sem a exceção de loopback nativo.
Cadastro aprovado em produção pelo script operacional e ID acrescentado ao piloto,
preservando os demais aplicativos, tenants e funcionários. Comando de cadastro:

```bash
/opt/alt/php83/usr/bin/php scripts/mcp-cadastrar-aplicativo.php --env=production --client-id=7carros-claude-web --name='Claude' --redirect-uri=https://claude.ai/api/mcp/auth_callback --approve
```

O responsável confirmou Adicionar e o consentimento das categorias no navegador.
Conexão externa validada em 16/11/2026: o Claude mostrou as 11 ferramentas somente
leitura, mantendo a opção Requer aprovação. Na conversa
`https://claude.ai/chat/08df722d-cf7b-4587-995a-18b457269255`, a pergunta
“Consulte o 7Carros: quantos veículos tenho no total?” acionou `listar_veiculos`,
com pagina=1, por_pagina=100 e `pergunta_usuario` literal. A chamada foi permitida
uma única vez e a resposta informou 19 veículos. O Claude acrescentou uma divisão
por situação cuja soma estava incorreta; esse texto extra é interpretação da IA
e não constitui validação de agrupamentos do MCP. Nenhum conector de outro serviço
foi removido ou substituído. Rotação e revogação deste cliente web ainda não foram
homologadas externamente; permanecem cobertas nos testes locais do fluxo comum.

Referência oficial consultada em 16/11/2026:
https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp

## Downloads na administração IA/MCP — 16/11/2026

A tela apresenta duas seções no topo: Informações do MCP à esquerda e Downloads
à direita em telas grandes; em telas menores as seções são empilhadas. Downloads
contém cartões ChatGPT 1.0.2 e Claude Code 1.0.0, com ícones de ZIP/download,
versão e botão Baixar ZIP. Claude Code é identificado como Para uso no terminal.
A URL do MCP continua nas informações; os endereços dos arquivos não aparecem
como texto. Links usam `download` sem nova aba e o servidor envia attachment.

`GET /integracoes/mcp/pacotes/{aplicativo}` usa middleware auth e as condições de
acesso da tela: integração habilitada para o tenant e funcionário ativo. Os únicos
identificadores aceitos são `chatgpt` e `claude-code`, com catálogo fixo no
WebController apontando para os ZIPs versionados em integrations. A rota não
recebe caminhos ou nomes de arquivos. Pacote desconhecido, integração indisponível
ou arquivo ausente/ilegível retornam 404; funcionário inválido/inativo retorna 403.
Os pacotes não contêm credenciais; nenhuma autorização OAuth é criada pelo download.

Schema de funcionarios confirmado no localhost e SELECT tenant-scoped executado
antes da alteração. Validação local HTTP: ambos os ZIPs retornam 200,
application/zip, Content-Disposition attachment, tamanho e bytes idênticos aos
originais; identificador desconhecido, caminho manipulado, tenant não habilitado,
integração desativada e funcionário inexistente foram rejeitados. Renderização
HTML e lint PHP aprovados. Sem migrations ou alterações em CSS/JS.

Publicação dos arquivos do escopo e ZIPs concluída por FTP com backup e conferência
SHA-256. Conferência visual no dashboard de produção confirmou Informações do MCP
à esquerda e Downloads à direita, com ícones, cartões e botões alinhados. Prévia
local em iframe de 375 px confirmou empilhamento dos cartões. Os dois links foram
clicados na tela real: os downloads concluíram sem substituir IA/MCP ou abrir
abas, e os arquivos salvos conferiram por SHA-256 com os originais. Requisições
externas sem sessão às duas rotas retornaram 302 para login. Nenhuma migration.


## Liberação global de locadoras — 16/11/2026

A regra de `Config::enabled($chave)` atende menu, tela administrativa, downloads,
autorização, contexto das chamadas e renovação. `MCP_ENABLED=false` interrompe o
acesso. O piloto tem prioridade e continua exigindo as listas de locadoras,
funcionários e aplicativos. Fora dele, `MCP_ALL_TENANTS=true` permite qualquer
locadora atual ou futura, sem consultar uma lista de chaves. Identidade ativa,
aplicativo habilitado e homologado em produção, permissões, escopos, filiais,
consentimento e revogação continuam obrigatórios. Chave vazia não é aceita.

Sem a nova variável ou com valor diferente de `true`, o comportamento restrito
anterior permanece. Não há mudança de schema, concessão automática de permissões,
novo pacote Composer nem troca de chaves. Publicar os arquivos não ativa o modo
global. Atribuir `mcp.conectar` e as permissões dos módulos nas roles autorizadas.

O diagnóstico recebe `--tenant=CHAVE` opcional. Em modo global efetivo, esse
argumento é obrigatório para obter `ready_to_enable: true`: sem ele, não busca
usuários entre locadoras e retorna orientação. Em modo restrito/piloto, o tenant
informado também precisa estar na lista permitida. Sem o argumento, mantém a
busca nas locadoras configuradas. O relatório acrescenta `all_tenants` (modo global
efetivo) e `checks.locadora_diagnostico_permitida`, preservando as demais chaves.

Depois da homologação externa de autorização, consulta, renovação após expiração
e revogação de cada aplicativo liberado, registrar a referência real pelo script
com `--mark-homologated --evidence=REFERENCIA_REAL_DOS_TESTES`. Não usar os testes
locais como evidência de homologação externa.

No terminal do servidor, preservar os demais valores do `.env.production` e usar:

```dotenv
MCP_ENABLED=true
MCP_PILOT_MODE=false
MCP_ALL_TENANTS=true
```

Validar um usuário autorizado de uma segunda locadora (substituir os dois valores
indicados pelos reais), na raiz do projeto:

```bash
/opt/alt/php83/usr/bin/php scripts/mcp-cadastrar-aplicativo.php --env=production --client-id=https://chatgpt.com/oauth/client.json --check --tenant=CHAVE_DA_LOCADORA --username=USUARIO_AUTORIZADO
```

O diagnóstico é somente leitura e pode ser executado antes de ligar
`MCP_ENABLED`. Conferir também consentimento, consulta isolada e auditoria na
aplicação real. Para voltar à lista restrita, definir `MCP_ALL_TENANTS=false`;
para interromper todo o MCP, `MCP_ENABLED=false`. As conexões fora da lista
perdem acesso e renovação, sem exclusão automática de consentimentos.


Validação local da liberação global: schema e consultas do diagnóstico conferidos
via terminal no localhost; 324 verificações de integração MCP, 83 de política/CIMD,
14 de headers CGI/SAPI e lint PHP aprovados. Os cenários globais usam fixtures
locais e cobrem OAuth, isolamento, renovação, suspensão, remoção de permissão e
revogação. Não são homologação externa nem confirmação de ativação em produção.


## Homologação externa do ChatGPT e ativação global — 16/11/2026

O erro de reconexão informado com referência
`7676105e009140cdb45edd50b820da72` foi diagnosticado na política de produção:
`MCP_PILOT_MODE=false`, `MCP_ALL_TENANTS=true` e aplicativo ChatGPT habilitado,
mas ainda com `homologated=0`. O funcionário estava ativo e possuía
`mcp.conectar`; o bloqueio não exigiu alteração de código ou permissões.

Pelo console CWP, como usuário de sistema `7carros`, foi criado backup protegido
do ENV fora do diretório público e restabelecido temporariamente o modo piloto,
preservando as listas existentes. O diagnóstico retornou `ready_to_enable: true`.
No Edge, a reconexão preservou o plugin existente e as cinco categorias já
consentidas. A conversa de homologação é
`https://chatgpt.com/c/6afb60e1-1f74-83ed-9fb2-c3078f8a6503`.

Evidências na auditoria (horários do banco):

- Consulta real às 17:00:56: 19 veículos no total, uma linha solicitada na página,
  referência `998684653f981c4d03e569fe5f8ffba6`.
- Revogação pela tela IA/MCP às 17:01:33:
  `487bd0e111ac0c589a41d1688f8f30ff`. A chamada seguinte foi bloqueada às 17:01:54,
  `76ae1ebf02e02863098e7a17b75b17f1`, e o ChatGPT pediu reconexão sem entregar
  novos dados.
- Nova autorização às 17:02:21:
  `e850305163055efc942f09bd58d977a8`. Consulta após reconexão às 17:03:10:
  `ffcf4cfb5b936c66662a3ed066b2350d`, novamente 19 veículos.
- O último access token expirava às 17:13:08. A conexão ficou sem chamadas até
  passar esse prazo, sem modificar credenciais ou seu tempo de vida. Às 17:13:51
  o ChatGPT renovou automaticamente, referência
  `eef8099bab775fb0d97a80254e7a6b2d`; às 17:13:52, a consulta
  `ce90345998b1be682440ea87aa5c175d` retornou 19 veículos sem novo consentimento.
  Na mesma conexão, os totais de access/refresh emitidos passaram de três para
  quatro e os refresh revogados de dois para três, comprovando rotação.

Com essas evidências, o script operacional existente registrou a homologação
somente do cliente `https://chatgpt.com/oauth/client.json`, conferindo novamente
seu documento CIMD. Em seguida, foi restaurado `MCP_PILOT_MODE=false`, mantendo
`MCP_ENABLED=true` e `MCP_ALL_TENANTS=true`. O diagnóstico final em novo processo
retornou `ready_to_enable: true`, `pilot_mode: false` e `all_tenants: true`.
A conexão final do usuário permaneceu ativa.

A ativação disponibiliza ChatGPT às locadoras atuais e futuras que autorizarem
seus usuários pelas roles existentes; não concede permissões ou consentimento
automático. A homologação dos clientes Claude não foi antecipada. A verificação
externa desta operação utilizou somente a locadora do responsável; uma segunda
locadora permanece como validação operacional separada. Sem migrations, Composer,
troca de chaves ou publicação do ENV por FTP.


A consulta final externa, já com o piloto desativado e modo global ativo, passou
às 17:15:16 (horário do banco): `listar_veiculos: success`, uma linha na página,
referência `c4297a55d249d778e590d573b7e4aab7`; o ChatGPT respondeu 19 veículos.
O cadastro de produção foi conferido por consulta somente leitura e retornou
`enabled=1`, `homologated=1`.


## Proteção contra abuso e cotas — 16/11/2026

A migration `00443_add_mcp_security_state.php` acrescenta uma linha por
`chave`/funcionário em `mcp_security_state`. Não altera históricos nem concede
permissões. Código dependente só pode ser publicado após a migration de produção.

`SecurityToolHandler` é registrado com `Builder::addRequestHandler` e delega a
execução normal ao `CallToolHandler` do SDK, usando o mesmo Registry. Inspeciona
requisições tipadas `tools/call` antes da validação de argumentos e da execução.
Ferramenta inexistente, operação sem consentimento/permissão (inclusive documentos
ou contatos) e propriedades adicionais no argumento/filtro contam uma infração
por chamada. Erros comuns de tipo, data, registro inacessível, autenticação,
protocolo, infraestrutura e cota não contam. Uma string SQL no campo de busca
continua sendo dado parametrizado, não uma operação de SQL livre.

Três infrações em janela móvel de cinco minutos suspendem o acesso MCP do
funcionário naquele tenant por 30 minutos, somando todos os aplicativos/conexões.
Tentativas durante a pausa não a estendem. O login web e a revogação continuam
disponíveis. A pausa impede consultas, novo consentimento e troca de código ou
refresh. Credenciais não são consumidas durante a pausa; após liberação/expiração,
continuam sujeitas à validade, revogação e rotação OAuth normais. Reutilização de
refresh já consumido continua revogando a família.

Cotas usam hora e dia fixos em UTC, compartilhadas por funcionário/tenant:

| Variável | Padrão |
|---|---:|
| `MCP_SECURITY_INFRACTIONS` | 3 |
| `MCP_SECURITY_HOUR_RECORDS` | 3000 |
| `MCP_SECURITY_DAY_RECORDS` | 10000 |
| `MCP_SECURITY_HOUR_SENSITIVE` | 100 |
| `MCP_SECURITY_DAY_SENSITIVE` | 300 |

Configurações devem ser inteiros positivos. Valores inválidos impedem acesso.
Cada registro entregue conta, inclusive repetições; os dados duplicados em
`content`/`structuredContent` contam somente uma vez. Documento e contato juntos
contam um cliente sensível por chamada e também um registro geral. Detalhe nulo
conta zero. Resumo financeiro conta somente linhas agregadas. A resposta que
ultrapassaria qualquer cota é bloqueada inteira, sem débito nem infração.

`SecurityState` usa Models/Singleton e QueryBuilder com `withChave` depois de
`table`. Escritas bloqueiam o funcionário e seu estado em ordem consistente,
inclusive a criação inicial. A consulta de negócio ocorre antes da transação;
débito e auditoria de sucesso são atômicos. Falha no controle ou na auditoria
impede entrega. Falha posterior de rede mantém o consumo conservador: não há
comprovação de recebimento pelo assistente. O Gateway revalida a autorização e a
suspensão antes de entregar, além das validações dentro da ferramenta.

Negação de operação retorna `isError` com código `mcp_operation_denied`; cota
retorna `mcp_quota_exceeded` e `retry_after_seconds`; suspensão retorna HTTP 403
com `mcp_suspended`, `retry_after_seconds` e `Retry-After`. OAuth preserva seus
erros padronizados. Schemas de sucesso e limites anteriores permanecem iguais.

Em Sistema → Integrações → IA/MCP, cada funcionário acompanha suas cotas e
suspensão. `mcp.administrar` permite acompanhar o tenant e encerrar a pausa por
`POST /integracoes/mcp/liberar` com CSRF e conexão pertencente ao tenant. Liberação
limpa as infrações, preserva cotas e não reativa conexões revogadas. Os últimos
50 eventos visíveis são exibidos na mesma página. Histórico de consultas em
Logs do Sistema permanece com as regras existentes.

Eventos `security_infraction`, `security_suspended`, `security_released` e
`security_quota` usam `mcp_audit` sem duplicar conteúdo pessoal, SQL ou credenciais.
A liberação registra o administrador como ator e o ID do funcionário afetado no
payload técnico. Não há envio de mensagens, classificador de intenção, nova IA
ou bloqueio baseado no texto de `pergunta_usuario`/registros. Esses textos são
conteúdo não confiável; esta proteção não controla o uso dos dados no assistente.

### Implantação desta evolução

1. Validar localmente schema, migration, suites MCP/CIMD/headers e interface.
2. Publicar somente a migration e executar no terminal do servidor, na raiz:
   `php migrate.php --env=production`. O executor aplica todas as pendências;
   conferir o conjunto antes da execução. Usar `DB_HOST=localhost` do servidor.
3. Após confirmar a migration, publicar arquivos dependentes via FTP com
   `.uploading`/rename e hashes; regenerar o autoload otimizado no servidor com
   `composer dump-autoload --no-dev --optimize --no-interaction`.
4. Homologar consulta, terceira infração, pausa, liberação e OAuth nos aplicativos
   externos; testes locais não comprovam homologação externa. Acompanhar cotas e
   suspensões nos primeiros sete dias. `MCP_ENABLED=false` interrompe o MCP se
   necessário, preservando estado e auditoria. Não retornar código antigo que
   ignore a suspensão como procedimento de recuperação de segurança.

Validação local: 389 verificações MCP aprovadas, incluindo cotas gerais e
sensíveis, conexões distintas, seis workers concorrentes, suspensão durante
leitura, retomada OAuth, erros comuns sem infração e administração HTTP/CSRF.
Também aprovados: 83 verificações CIMD/piloto, 14 cenários CGI/SAPI, regressão
WHMCS (descoberta/remoção das tabelas tenant), regressão JavaScript da aba de
histórico e lint dos 18 arquivos PHP do escopo. Autoload local regenerado.

Publicação: migration enviada por FTP e SHA-256 conferido; execução em produção
confirmada pelo responsável. Os 12 arquivos PHP dependentes foram ativados após
essa confirmação, com backups locais, comparação da base remota e SHA-256 em
todos os arquivos. O autoload remoto permite fallback PSR-4; a regeneração do
mapa otimizado após a publicação foi confirmada pelo responsável.

Uma consulta real de um veículo pelo plugin 7Carros foi aprovada após ativação.
Diagnóstico read-only em produção confirmou as 13 colunas do estado, o registro
da migration no executor, a auditoria da consulta e o débito da cota. Não foram
expostos dados pessoais no diagnóstico nem provocadas infrações no tenant real.
Essa evidência confirma consulta/contabilização externa. As regras de suspensão
e retomada passaram na suíte local pelo mesmo transporte; o ciclo completo não
foi homologado de ponta a ponta em ChatGPT e Claude externos nesta entrega.

A documentação remota `docs/security.md` possui uma versão anterior de trechos
sobre sessão web, fora deste escopo. Ela foi preservada; as novas regras MCP
estão nesta página e os eventos em `docs/logs.md`, sem publicar alterações de
outros módulos junto desta entrega.
