# Plano de integração MCP com isolamento por tenant

## 1. Objetivo e entrega

Permitir que locadoras conectem ChatGPT, Claude e assistentes próprios ao 7Carros para consultar frota, clientes, contratos, locações e financeiro.

Este documento é o roteiro de implantação. O checklist ao final registra a implementação e suas evidências; a disponibilidade e a homologação efetivas estão descritas em `docs/mcp.md`.

### Referências de versão e manutenção

**Última verificação das fontes oficiais: 16/11/2026.**

| Componente | Referência verificada | Política para implementação |
|------------|------------------------|-----------------------------|
| Protocolo MCP | `2026-07-28`, identificado como atual na documentação oficial | Utilizar a revisão atual publicada e pronta para uso; revalidar antes de implementar |
| SDK PHP `mcp/sdk` | `v0.10.0`, release mais recente encontrada na consulta | Selecionar a release atual compatível com o protocolo e validar suas dependências |
| Biblioteca `league/oauth2-server` | `9.4.1`, selecionada e resolvida no início da implementação | Revalidar releases mantidas e os complementos exigidos pelo MCP em atualizações |
| PHP do projeto | `8.3`, conforme `composer.json` | Manter o ambiente existente; uma atualização de PHP exige escopo próprio |

A versão do protocolo, identificada por data, é diferente da versão do SDK Composer. Os números acima registram o que foi verificado nesta revisão; não são autorização para manter dependências desatualizadas quando a implementação começar.

Antes da implementação e de cada atualização relevante, consultar a [versão vigente do MCP](https://modelcontextprotocol.io/specification/versioning), as [releases do SDK](https://github.com/modelcontextprotocol/php-sdk/releases) e as [releases da biblioteca OAuth](https://github.com/thephpleague/oauth2-server/releases). Atualizar esta tabela, os links e os critérios de teste se houver mudanças. Não adotar especificações marcadas como draft nem branches de desenvolvimento para substituir releases publicadas.

Decisões confirmadas:

- Primeira versão somente para consultas.
- Usuários autorizados pelo administrador poderão conectar aplicativos.
- Cada conexão respeitará as permissões e filiais do usuário que a autorizou.
- Documentos e contatos de clientes dependerão de autorização adicional explícita.
- A suspensão bloqueará o acesso; a reativação permitirá retomá-lo enquanto a autorização permanecer válida.

Não incluir alterações de cadastros, pagamentos, envio de mensagens, PDFs, anexos ou acesso direto ao banco.

Exemplos práticos:

- **ChatGPT/Claude:** a locadora conecta o 7Carros ao aplicativo de IA que já utiliza e pergunta: “Quais contratos vencem nesta semana e quanto tenho a receber?”.
- **Assistente próprio:** um desenvolvedor da locadora cria um robô ou aplicativo interno que consulta o mesmo MCP. O cliente desenvolve e mantém esse aplicativo.

Os dois cenários seguem as mesmas regras de tenant, permissões, filiais e suspensão.

## 2. Arquitetura e autenticação

### Servidor MCP

Implementar no próprio projeto PHP, seguindo Controller → Service → Model → QueryBuilder. Controllers não abrirão conexões de banco.

Expor um endpoint HTTPS `/mcp` usando Streamable HTTP e JSON-RPC. Utilizar o SDK oficial `mcp/sdk`, selecionando a release atual na implementação e registrando as versões resolvidas no `composer.lock`. Validar a compatibilidade do protocolo e das dependências no PHP 8.3; o requisito PHP do SDK consultado admite essa versão, mas isso não substitui a resolução e os testes do projeto. O SDK ainda é anterior à versão principal estável. Referências: [SDK oficial](https://github.com/modelcontextprotocol/php-sdk) e [requisitos da release verificada](https://github.com/modelcontextprotocol/php-sdk/blob/v0.10.0/composer.json).

Adotar `2026-07-28` e a negociação nativa do SDK para clientes com `initialize`, validada nas revisões `2025-03-26`, `2025-06-18` e `2025-11-25`. Esta decisão substitui a restrição original de revisão exclusiva para corrigir a descoberta no ChatGPT. Não implementar o protocolo manualmente. Referências: [versionamento](https://modelcontextprotocol.io/docs/2026-07-28/learn/versioning) e [Streamable HTTP](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http).

- Processar cada chamada por HTTP POST independente, sem sessões de protocolo e sem stream por GET. As autorizações OAuth persistidas continuam existindo; não são sessões do transporte MCP.
- Disponibilizar `server/discover` em 2026; atender `initialize` e `notifications/initialized` nas revisões de 2025, usando `StreamableHttpTransport` e `Server::builder()->build()` sem sessões persistentes.
- Na revisão de 2026, validar os metadados de versão por requisição, incluindo `io.modelcontextprotocol/protocolVersion` em `_meta` e sua correspondência com o header `MCP-Protocol-Version`.
- Rejeitar versões não suportadas com o erro definido pela especificação, informando as versões aceitas, sem downgrade silencioso.
- Usar respostas JSON para as consultas iniciais. Caso SSE seja necessário, mantê-lo vinculado à requisição, seguindo a especificação vigente.

ChatGPT, Claude e assistentes próprios são alvos de homologação. A compatibilidade não está comprovada por este documento. Homologar cada aplicativo na revisão efetivamente negociada pelo SDK. Não acrescentar tradução manual de protocolo nem transporte HTTP+SSE legado; clientes incompatíveis com os fluxos suportados permanecem sem liberação.

### Autorização das conexões

Seguir o perfil OAuth 2.1 da especificação MCP vigente, com Authorization Code e PKCE S256. Aplicativos próprios também usarão autorização de um funcionário; credenciais sem vínculo com usuário ficam fora desta versão.

Implementar o servidor de autorização com uma release atual mantida de `league/oauth2-server`. A biblioteca não garante, isoladamente, conformidade com o perfil MCP: implementar e testar os complementos necessários. Disponibilizar autorização, troca/renovação de tokens e revogação, além de:

- Metadados do recurso protegido conforme RFC 9728, com indicação do servidor de autorização e challenges `WWW-Authenticate` apropriados.
- Descoberta do servidor de autorização conforme RFC 8414.
- Identificação e validação do emissor na resposta de autorização, conforme RFC 9207.
- Vínculo do token ao recurso MCP, utilizando o parâmetro `resource` conforme RFC 8707 e validação de audiência.

Referências: [biblioteca OAuth](https://oauth2.thephpleague.com/) e [autorização MCP atual](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization).

Fluxo:

1. O cliente informa o endereço MCP ao aplicativo de IA.
2. O aplicativo abre a autorização no 7Carros.
3. O usuário faz login e visualiza aplicativo, locadora e categorias solicitadas.
4. O servidor limita as categorias às permissões atuais do funcionário.
5. Após consentimento, emite credenciais vinculadas ao aplicativo, funcionário, tenant e escopos aprovados.
6. Cada chamada valida novamente essas condições.

Cadastrar previamente aplicativos OAuth e seus endereços de retorno exatos. Assistentes próprios terão cadastro administrativo. Suportar também Client ID Metadata Documents (CIMD) para aplicativos aprovados, conforme a especificação atual, sem aprovação automática por apresentar metadados. Validar os documentos e aplicar proteção contra SSRF ao buscá-los: HTTPS, destinos públicos aprovados e validação de cada redirecionamento. Não disponibilizar registro público irrestrito nem implementar Dynamic Client Registration (DCR), depreciado na especificação consultada.

Adotar access tokens de 10 minutos, códigos de autorização de uso único de 60 segundos e refresh tokens com validade absoluta de 90 dias, rotação e detecção de reutilização. Guardar identificadores ou hashes necessários à validação e revogação, sem registrar credenciais nos logs.

Validar assinatura, emissor, expiração e audiência específica do MCP. Nunca aceitar tokens de outros serviços nem repassá-los a integrações externas. Referência: [segurança MCP atual](https://modelcontextprotocol.io/docs/2026-07-28/tutorials/security/security_best_practices).

## 3. Isolamento e controle da conta

### Isolamento dos registros pela `chave`

A coluna `chave` identifica a locadora (tenant) e separa seus registros dos registros das demais locadoras em todas as tabelas que armazenam dados de tenants. O MCP reutilizará esse mecanismo existente. Tabelas globais compartilhadas seguem somente as exceções documentadas em [Multi-tenancy](docs/multi-tenancy.md) e [QueryBuilder](docs/querybuilder.md).

Exemplo: uma conexão vinculada à locadora com `chave = A` só poderá consultar registros com `chave = A`. Informar o ID de um cliente, veículo ou contrato da locadora B não permitirá acessar esse registro. O ID sozinho não estabelece autorização.

Permissões e filiais restringem o acesso dentro da locadora e complementam o isolamento pela `chave`.

### Contexto obrigatório por requisição

Criar um contexto imutável contendo tenant, funcionário, conexão, permissões atuais e filiais autorizadas. Sua origem será exclusivamente a credencial validada e os registros do servidor.

- Não aceitar `chave` como argumento das ferramentas.
- Não usar cookies do navegador para identificar chamadas MCP.
- Bloquear o processamento quando o contexto estiver ausente, inválido ou incompleto.
- Implementar Models de leitura MCP com contexto obrigatório e `table(...)->withChave($contexto->chave)` em cada consulta.
- Preservar o filtro nos detalhes por ID, buscas, totais, relacionamentos e subconsultas.
- Em JOINs entre dados de tenants, validar também a igualdade das respectivas chaves.
- Não usar `withoutChave()` nas consultas de negócio.

A resolução inicial de credenciais será uma operação restrita de autenticação, antes de existir contexto. Não permitirá pesquisa genérica de dados.

**Ponto encontrado:** o QueryBuilder atual só acrescenta o filtro quando existe uma `chave`. Portanto, uma chamada sem contexto não pode chegar à camada de consultas.

### Permissões e filiais

Criar permissões para conectar IA, administrar conexões e liberar documentos/contatos.

O acesso efetivo será a interseção entre:

**escopos consentidos ∩ permissões atuais do usuário ∩ filiais autorizadas.**

Consultar autorização atual no banco a cada chamada, sem depender do cache de permissões de uma hora usado pelo fluxo web. Não aplicar privilégios especiais de suporte às conexões MCP.

Reproduzir as regras documentadas de filiais, inclusive retirada/devolução nas locações. Diferenciar explicitamente “acesso a todas as filiais” de “nenhuma filial autorizada”; uma falha de carregamento nunca significará acesso total.

### Suspensão e reativação

Reutilizar `funcionarios.status` como regra de acesso do funcionário vinculado à conexão MCP, sem criar tabela ou coluna paralela para controlar a situação da locadora ou da assinatura.

O WHMCS já suspende os funcionários de uma locadora, identificada pela `chave`, alterando `funcionarios.status` de `A` (ativo) para `S` (suspenso), e reativa de `S` para `A`. O MCP respeitará diretamente essas alterações, conforme [WHMCS](docs/whmcs.md).

- Em cada chamada, nova autorização e renovação de tokens, consultar no banco o funcionário pelo identificador e pela `chave` vinculados à conexão, com o contexto validado e `withChave($contexto->chave)`.
- Somente `status = 'A'` permite prosseguir, respeitando também credenciais, consentimentos, permissões e filiais atuais. `S`, qualquer outro valor, funcionário inexistente, vínculo de tenant divergente ou falha de validação bloqueiam o acesso, mesmo com token ainda válido.
- Preservar consentimentos e refresh tokens durante a suspensão. Após reativação, permitir retomada quando as credenciais e autorizações continuarem válidas.
- Encerramento da locadora ou revogação manual invalidará definitivamente as conexões. A exclusão do funcionário impedirá o uso de credenciais remanescentes.
- Aplicar a mesma regra às contas existentes, sem criar estado “não confirmado” nem exigir reconciliação adicional com o WHMCS para habilitar o MCP.

Revalidar o funcionário e seu status antes de entregar a resposta, evitando liberar resultados de uma consulta que terminou depois da suspensão. A retomada depende também de o aplicativo voltar a tentar a conexão; credenciais expiradas exigirão nova autorização.

## 4. Ferramentas, dados e administração

### Catálogo inicial

| Categoria | Ferramentas | Permissão exigida |
|-----------|-------------|-------------------|
| Frota | `listar_veiculos`, `obter_veiculo` | `veiculos.visualizar` |
| Clientes | `listar_clientes`, `obter_cliente` | `clientes.visualizar` |
| Contratos | `listar_contratos`, `obter_contrato` | `contratos.visualizar` |
| Locações | `listar_locacoes`, `obter_locacao` | `locacoes.visualizar` |
| Financeiro | `listar_lancamentos`, `obter_lancamento`, `resumir_financeiro` | `financeiro.visualizar` |

Associar cada categoria a um escopo OAuth de leitura. Documentos e contatos exigirão também escopo e permissão específicos. O catálogo retornado por `tools/list` mostrará apenas ferramentas disponíveis; `tools/call` repetirá a autorização.

Implementar schemas fechados para argumentos e respostas. Aceitar somente filtros documentados, sem SQL, nomes de tabelas, caminhos de arquivos ou URLs fornecidas pela IA.

Adotar paginação padrão de 25 registros e máximo de 100. Respostas indicarão paginação, filtros aplicados e horário da consulta. Listagens usarão campos resumidos; documentos e contatos serão retornados somente em consultas individuais autorizadas.

Nunca expor senhas, tokens, credenciais de gateway, código de segurança da CNH, assinaturas ou arquivos privados.

No financeiro, reutilizar as regras documentadas para receitas, despesas, pagamento e parcelamento, sem duplicar valores entre faturas e parcelas. Informar moeda e separar totais de moedas diferentes.

### Administração e rastreabilidade

Criar uma tela “Integrações → IA/MCP” para listar conexões, aplicativo, usuário, categorias autorizadas, validade e último acesso. O usuário poderá revogar suas conexões; administradores autorizados poderão revogar conexões do tenant.

Registrar autorizações, revogações, consultas e bloqueios, com tenant, funcionário, aplicativo, ferramenta, resultado, quantidade de registros, duração e identificador de requisição. Não registrar respostas completas, documentos pessoais ou tokens.

Aplicar limites iniciais configuráveis de 60 chamadas/minuto por conexão e 300 por tenant, com timeout de 15 segundos. Usar HTTPS, validação de `Origin` quando presente e proteção CSRF nas telas de autorização.

Dados textuais retornados serão tratados como conteúdo, nunca como instruções capazes de modificar autorização. A tela de consentimento explicará que informações enviadas ao aplicativo de IA poderão permanecer nele mesmo após revogar a conexão.

## 5. Implementação, validação e liberação

### Documentação do MCP

Criar `docs/mcp.md` no início da implementação como referência permanente para desenvolvedores, mantenedores e responsáveis pela configuração da integração. Este `PLANO-MCP.md` continuará sendo o roteiro de implantação; a nova documentação descreverá o funcionamento entregue, distinguindo explicitamente funcionalidades planejadas, implementadas e homologadas. Não apresentar decisões do plano como recursos já disponíveis.

Conteúdo obrigatório:

- Arquitetura, componentes, responsabilidades das camadas e fluxo das requisições.
- Configuração, dependências, versões adotadas, data de verificação e referências oficiais.
- Autenticação, consentimento, escopos, permissões, isolamento dos registros pela coluna `chave` e restrições de filiais, com links para as regras existentes de multi-tenancy e QueryBuilder.
- Catálogo das ferramentas, argumentos, respostas, filtros, paginação, limites e exemplos de uso.
- Administração das conexões, suspensão e reativação por `funcionarios.status` (`A`/`S`), vínculo do funcionário pela `chave`, encerramento e revogação, com referência ao fluxo existente do WHMCS.
- Auditoria, diagnóstico de erros, testes e compatibilidade homologada dos aplicativos, com datas e resultados.
- Instruções para conectar, consultar e revogar o acesso, conforme os fluxos efetivamente homologados.

Preferir links para as documentações existentes de arquitetura, QueryBuilder, permissões, filiais, WHMCS e regras dos módulos. Reproduzir trechos somente quando necessários para compreender ou aplicar uma instrução específica do MCP, mantendo referência à origem e coerência com ela. A nova documentação não deverá criar versões paralelas das regras gerais do sistema. Exemplos não poderão conter credenciais ou dados pessoais reais.

Durante a implementação, adicionar a referência a `docs/mcp.md` no catálogo do `AGENTS.md`, incluindo sua consulta obrigatória antes de alterações no MCP, e em `docs/integrations.md`, com uma descrição breve e link para a documentação completa.

Quem alterar ferramentas, autenticação, permissões, configuração, dependências, protocolo ou operação deverá atualizar `docs/mcp.md` na mesma entrega. Em atualizações relevantes, revisar as versões, a data de verificação e os resultados de homologação, identificando o que precisa de nova validação. Documentação atualizada será requisito de liberação da integração e de suas alterações posteriores.

### Sequência

1. Criar `docs/mcp.md` com os tópicos obrigatórios e o estado inicial de funcionalidades planejadas; adicionar suas referências no `AGENTS.md` e em `docs/integrations.md`. Revalidar versões e referências oficiais; selecionar as releases atuais, registrar as dependências no `composer.lock` e validar SDK, OAuth e transporte com respostas fictícias. Registrar os resultados na documentação.
2. Criar migrations para clientes OAuth, consentimentos, credenciais e permissões. Não criar estrutura paralela de situação da locadora; reutilizar `funcionarios.status` e o isolamento pela `chave`.
3. Implementar a validação do funcionário vinculado à conexão por identificador, `chave` e `status`, respeitando a suspensão, reativação e encerramento existentes no WHMCS e a revogação das conexões.
4. Implementar contexto obrigatório, políticas de acesso e ferramentas.
5. Implementar administração, auditoria e limites.
6. Homologar com ChatGPT, Claude e um assistente próprio; registrar em `docs/mcp.md` a data, aplicativo, versão quando disponível, protocolo utilizado e resultado, incluindo incompatibilidades.
7. Validar a documentação atualizada e liberar por feature flag para tenants piloto com funcionários ativos e autorizados; ampliar após os testes. Atualizar `docs/mcp.md` em cada entrega das etapas anteriores.

Antes de implementar acesso a dados, repetir `DESCRIBE`/`SHOW COLUMNS`, conferir índices e testar consultas no localhost. A inspeção da fase de planejamento foi somente de leitura.

Executar migrations exclusivamente com `php migrate.php --env=development` e, no servidor, `php migrate.php --env=production`. Publicar apenas arquivos do escopo pelo procedimento FTP do projeto; minificar fontes CSS/JS alteradas.

### Testes de aceitação

- Dois tenants com `chave` distintas: IDs, buscas, JOINs, subconsultas, paginação e totais nunca retornam dados do outro. Informar um ID do tenant B com a credencial do tenant A não expõe o registro.
- Credencial de A acompanhada de cookie de B continua limitada a A.
- Contexto ausente, incompleto ou sem `chave` válida impede qualquer consulta de negócio; a ferramenta não aceita `chave` como argumento.
- Restrição de filial funciona em listagem, detalhe e agregações.
- Remover permissões tem efeito na próxima chamada.
- Documentos e contatos permanecem ausentes sem autorização adicional.
- Token expirado, revogado, com audiência incorreta ou código OAuth reutilizado é rejeitado.
- Suspensão pelo WHMCS (`funcionarios.status` de `A` para `S`) bloqueia consultas com tokens ainda válidos, novas autorizações e renovação, preservando consentimentos e refresh tokens.
- Reativação de `S` para `A` permite retomada com credenciais e autorizações ainda válidas, sem cadastro paralelo ou reconciliação adicional.
- Suspender um funcionário bloqueia suas conexões sem bloquear as de outros funcionários ativos da mesma locadora.
- Suspensão durante uma consulta impede a entrega dos resultados pela revalidação antes da resposta.
- Funcionário removido, status diferente de `A`, vínculo com outra `chave` ou falha de validação impede acesso; encerramento da locadora ou revogação invalida definitivamente as conexões.
- Consultas financeiras conferem com os valores exibidos pelo sistema.
- Entradas maliciosas não executam SQL, acessam arquivos ou alteram dados.
- `server/discover` atende a revisão de 2026 e `initialize` negocia as revisões de 2025; nenhum fluxo depende de sessão MCP persistente.
- Cada POST segue a validação de versão do SDK para seu fluxo; metadados de 2026 não são impostos às revisões de 2025, e divergências entre header e corpo moderno são rejeitadas.
- Descoberta OAuth, PKCE S256, identificação do emissor, vínculo ao recurso e challenges de autorização seguem a especificação adotada.
- CIMD aceita somente aplicativos aprovados e rejeita destinos não permitidos; DCR não é disponibilizado.
- ChatGPT, Claude e assistente próprio completam autorização, consulta, renovação e revogação usando uma revisão suportada, com a revisão efetiva e resultados registrados por aplicativo. Clientes incompatíveis permanecem sem liberação.
- A documentação corresponde às ferramentas disponíveis, seus argumentos e respostas; links e exemplos foram conferidos, sem credenciais ou dados pessoais reais.
- As instruções documentadas de conexão, consulta e revogação foram validadas nos aplicativos homologados; funcionalidades planejadas, implementadas e homologadas estão diferenciadas.
- Os trechos aproveitados de outras documentações são necessários, indicam sua origem e não introduzem regras divergentes.

Critério de liberação: testes de protocolo, autorização, isolamento e suspensão aprovados, sem acesso cruzado e sem alteração de dados de negócio pelas ferramentas, com `docs/mcp.md` atualizada e validada. Liberar somente os aplicativos homologados na revisão efetivamente negociada. Aplicar a exigência documental também às alterações posteriores.

### Manutenção e validação deste documento

- Conferir as versões em fontes oficiais no início da implementação e antes de cada atualização relevante; registrar a data da consulta.
- Atualizar conjuntamente protocolo, transporte, autorização, links e testes quando uma nova revisão atual for adotada.
- Revisar as releases e os requisitos do SDK e da biblioteca OAuth, sem confundir release publicada com garantia de estabilidade de API ou compatibilidade já testada.
- Conferir os links locais e oficiais, remover recomendações de versões antigas e recursos depreciados e revisar a coerência do documento.
- A revisão documental original abrangia somente `PLANO-MCP.md`. A criação da documentação permanente, as referências e as alterações em código, banco e dependências agora são acompanhadas pelo checklist de implantação ao final deste roteiro.

### Documentação consultada

- [AGENTS.md](AGENTS.md)
- [Arquitetura](docs/architecture.md)
- [QueryBuilder](docs/querybuilder.md)
- [Multi-tenancy](docs/multi-tenancy.md)
- [Segurança](docs/security.md)
- [API](docs/api.md)
- [Roles e permissões](docs/roles.md)
- [Filtros por filiais](docs/filial-helper.md)
- [WHMCS](docs/whmcs.md)
- [Planos](docs/plans.md)
- [Banco de dados](docs/database.md)
- [Migrations](docs/migrations.md)
- [Logs](docs/logs.md)
- [Contratos](docs/contratos.md)
- [Locações](docs/locacoes.md)
- [Financeiro](docs/financeiro.md)
- [Desenvolvimento e publicação](docs/development.md)

Na revisão de versões de 16/11/2026, foram consultados novamente `AGENTS.md`, este plano, `docs/architecture.md`, `docs/api.md` e `composer.json`, além das fontes oficiais de MCP, SDK e autorização vinculadas acima. As demais referências locais registram as consultas feitas na elaboração original do plano.

Para incluir a entrega e a manutenção da documentação permanente do MCP, foram consultados as instruções do `AGENTS.md` fornecidas na conversa, este plano, `docs/integrations.md`, `docs/architecture.md` e `docs/development.md`. Essa revisão documental não revalida as versões do protocolo ou das dependências.

Há exemplos antigos de APIs e estruturas na documentação. A implementação deverá seguir a API fluente documentada do QueryBuilder e o schema local validado; divergências de estrutura deverão ser investigadas antes das alterações. Por exemplo, a visão geral de arquitetura menciona `app/Routers/`, enquanto o entry point atual carrega `app/Routes/web.php`; essa diferença deverá ser reconciliada na documentação durante a implementação.

Nesta revisão de isolamento e suspensão, foram consultados as instruções do `AGENTS.md` fornecidas na conversa, este plano, `docs/whmcs.md`, `docs/querybuilder.md`, `docs/multi-tenancy.md` e `docs/development.md`, além do fluxo existente de suspensão e reativação no `TenantProvisioningService`. A revisão é documental: reutiliza `funcionarios.status` e a separação por `chave`, sem implementar a integração ou revalidar versões do protocolo e dependências.

## Checklist de implantação (acompanhamento)

Atualizado em 16/11/2026. Marcar somente com evidência; homologação e publicação
não são equivalentes a implementação local.

- [x] Ler instruções, roteiro e mapear camadas afetadas.
- [x] Revalidar referências oficiais: MCP 2026-07-28, SDK 0.10.0, OAuth 9.4.1.
- [x] Resolver e instalar dependências no PHP 8.3, preservando dependências existentes.
- [x] Criar documentação permanente e referências obrigatórias.
- [x] Validar transporte oficial com dados fictícios, sem protocolo legado.
- [x] Validar schema, índices e consultas no MySQL localhost.
- [x] Criar e testar migrations de aplicativos, consentimentos, credenciais e permissões.
- [x] Implementar OAuth, PKCE, descoberta, audiência, rotação e revogação.
- [x] Implementar aprovação de aplicativos e CIMD com proteção SSRF.
- [x] Implementar contexto obrigatório, permissões e filiais atuais por chamada.
- [x] Implementar suspensão/reativação e revalidação antes da resposta.
- [x] Implementar as 11 ferramentas, schemas fechados e minimização de dados.
- [x] Validar financeiro, parcelamento e moedas contra o sistema.
- [x] Implementar administração, consentimento, auditoria e limites.
- [x] Executar testes de isolamento, segurança, protocolo e regressão.
- [ ] Homologar ChatGPT, Claude e assistente próprio; registrar evidências individuais.
- [x] Minificar CSS/JS alterados, conferir documentação e arquivos de publicação.
- [x] Publicar arquivos do escopo pelo FTP.
- [ ] Instalar dependências e executar migrations no terminal do servidor (responsável: usuário).
- [ ] Configurar chaves RSA, segredo, emissor, origens, permissões, aplicativos e tenants piloto no servidor, mantendo a flag desligada até homologar.
- [ ] Validar HTTP da aplicação em produção e diagnosticar o 403 observado externamente em `/mcp` e `/login`.
- [ ] Homologar a busca CIMD real em host aprovado, incluindo redirecionamentos e rejeições de destinos.
- [ ] Validar piloto com feature flag; monitorar antes de ampliar.

### Evidências desta implementação — 16/11/2026

- Componentes MCP/OAuth implementados com feature flag desligada por padrão; sem liberação automática de tenants ou aplicativos.
- Migration `00441_create_mcp_integration.php` executada somente por `php migrate.php --env=development`; nenhuma outra pendência local.
- `php tests/test_mcp_integration.php`: 84 verificações aprovadas; inclui suspensão durante consulta bloqueada e revalidação antes da resposta, isolamento, filiais, moedas, OAuth, rotação e revogação.
- `php tests/test_whmcs_tenant_termination.php`: aprovado; novas tabelas reconhecidas pelo inventário de término.
- Templates administrativo/consentimento renderizados e CSRF presente; HTTP local com flag desligada retorna 503 sem sessão.
- `composer validate --no-check-publish`: válido, com avisos de versões fixas. Audit final: 21 avisos em cinco pacotes já presentes em produção; nenhum nos novos pacotes.
- Nenhum arquivo fonte CSS/JS alterado; não há minificação pendente nesta entrega.
- Comparação FTP: entry point, rotas, navbar e manifesto remoto coincidem com a base Git anterior às alterações.
- Dependências MCP ausentes no servidor na consulta FTP; instalação e migration serão executadas pelo usuário no terminal do servidor.
- Homologações externas de ChatGPT, Claude, assistente próprio e busca CIMD real continuam pendentes. Teste local de assistente próprio não equivale a homologação externa.
- A divergência `app/Routers/` versus `app/Routes/` foi corrigida em `docs/architecture.md` conforme o entry point real.

Documentos consultados nesta implementação: `AGENTS.md` (destino `CLAUDE.md`), este roteiro, `docs/mcp.md`, `docs/architecture.md`, `docs/querybuilder.md`, `docs/database.md`, `docs/migrations.md`, `docs/development.md`, `docs/integrations.md`, `docs/roles.md`, `docs/filial-helper.md`, `docs/whmcs.md`, `docs/api.md`, `docs/security.md`, `docs/multi-tenancy.md`, `docs/logs.md`, `docs/modals.md`, `docs/helpers.md`, `docs/best-practices.md`, `docs/financeiro.md`, `docs/contratos.md`, `docs/locacoes.md`, `docs/currency.md` e `docs/date.md`.

O ciclo local aplicar → reverter → reaplicar da migration foi validado pelo executor oficial. O teste revelou um defeito no rollback do `migrate.php` após commit implícito de DDL MySQL; foi corrigido com a mesma verificação `inTransaction()` já usada em `up()`, e documentado em `docs/migrations.md`. Nenhum histórico de migration foi manipulado manualmente.

Antes da publicação, o lock foi reconciliado com o `composer.lock` e o inventário de pacotes instalados do servidor: todas as versões preexistentes de produção foram preservadas e foram acrescentados 26 pacotes. A primeira resolução local usava uma base mais antiga e não foi enviada ao servidor. A suíte foi repetida após alinhar o ambiente local à base de produção.

A publicação também revelou um exemplo inválido de ordem de argumentos LFTP em `docs/development.md`: a versão instalada exige `put arquivo-local -o arquivo-remoto`. O exemplo foi corrigido mantendo o upload temporário e a renomeação atômica previstos na documentação. As tentativas com argumentos inválidos falharam antes de substituir arquivos de runtime.

### Publicação dos arquivos — 16/11/2026

- 38 arquivos do escopo publicados pelo FTP configurado, com todos os uploads temporários concluídos antes das renomeações; 38/38 cópias remotas conferidas por SHA-256.
- Não foram enviados `vendor/`, testes, `.env*`, credenciais ou arquivos ignorados pelo Git. `.env.example` e `.gitignore` permanecem alterações locais para desenvolvimento/versionamento.
- Não houve ativação do MCP, alteração da configuração de produção ou execução de migrations no servidor.
- O usuário confirmou que executará os comandos no terminal do servidor: `composer install --no-dev --optimize-autoloader --no-interaction` e `php migrate.php --env=production`, na raiz do projeto.
- A verificação HTTP externa recebeu 403 em `/mcp` e `/login`; não foi possível confirmar por HTTP a resposta da aplicação publicada. Isso não altera a conferência dos arquivos via FTP. Diagnóstico HTTP no ambiente do servidor e homologação dos aplicativos permanecem pendentes.
- A configuração de chaves, permissões, aplicativos homologados e tenants piloto continua obrigatória antes de habilitar; seguir `docs/mcp.md`.

## Correção para o primeiro teste real do ChatGPT

Execução iniciada; a homologação continua pendente até comprovar o fluxo real.

- [x] Revalidar documento oficial do ChatGPT, instruções e schema local via terminal.
- [x] Negociar métodos/grants CIMD compatíveis e persistir capacidades selecionadas.
- [x] Centralizar piloto por locadora, funcionário e aplicativo, com listas vazias bloqueadas.
- [x] Completar cadastro idempotente, diagnóstico read-only e marcação de homologação com evidências.
- [x] Ampliar testes CIMD/piloto e reexecutar regressões existentes.
- [x] Publicar arquivos do escopo e conferir hashes remotos.
- [ ] Validar par de chaves/leitura pelo usuário PHP no servidor.
- [ ] Aprovar cadastro pendente do ChatGPT e configurar somente o tiagocaus no piloto.
- [ ] Diagnosticar acesso HTTP externo e habilitar piloto apenas após pré-requisitos.
- [ ] Homologar autorização, consulta de frota, renovação e revogação no ChatGPT.
- [ ] Registrar revisão efetiva do protocolo e verificar separadamente o uso no celular.

Caminho confirmado pelo usuário na própria conta: **Explorar → adicionar plugin**.

### Evidências da correção CIMD/piloto — 16/11/2026

- Busca real do documento oficial `https://chatgpt.com/oauth/client.json` aprovada localmente; negociação seleciona `none`, `authorization_code` e `refresh_token`, sem habilitar grants extras.
- 51 verificações de CIMD/política e 107 de integração MCP passaram; regressão de término WHMCS aprovada. Incluem outro funcionário, outro tenant, listas vazias, remoção de piloto, bloqueio de tokens e renovação, retomada do refresh preservado e homologação sem ampliar o piloto.
- PHP lint: 24 arquivos aprovados. Nenhuma dependência, migration, CSS ou JavaScript acrescentados nesta correção.
- Cloudflare respondeu externamente com 403/1010 `browser_signature_banned`: bloqueio pela assinatura HTTP antes do PHP. Acesso real do ChatGPT e resposta do PHP web continuam pendentes.
- Dependências e migration de produção foram executadas pelo responsável na etapa anterior. Regenerar autoload otimizado por causa das classes novas, depois executar aprovação e diagnóstico conforme `docs/mcp.md`.
- Nenhuma alteração de `.env.production`, concessão automática de permissões ou homologação antecipada. O responsável continuará executando os comandos do servidor.

- Correção publicada: 10/10 arquivos do escopo conferidos por SHA-256 via FTP (oito PHP e dois documentos). Configuração e execução de comandos no servidor continuam pendentes do responsável; nenhuma homologação externa foi marcada.

## Correção da abrangência no consentimento — 16/11/2026

- [x] Consultar documentação e validar schema local via terminal.
- [x] Substituir nome único por quantidade e lista completa de empresas/filiais permitidas, com nome e ID.
- [x] Reutilizar resolução atual de filiais, sem seletor adicional nem alteração de permissões/categorias.
- [x] Tratar nomes vazios, nomes repetidos, ausência de unidades e escape HTML.
- [x] Validar isolamento e mudança de vínculos entre apresentação e confirmação.
- [x] Executar 118 verificações de integração, 51 CIMD/piloto e PHP lint dos quatro PHP alterados.
- [x] Atualizar documentação MCP; sem migrations, dependências ou alterações CSS/JS.
- [x] Publicar os cinco arquivos do escopo e conferir hashes remotos.
- [x] Conferir a apresentação no navegador, sem autorizar pelo usuário.

Documentos consultados: `AGENTS.md`, `docs/mcp.md`, `docs/architecture.md`,
`docs/querybuilder.md`, `docs/database.md`, `docs/filial-helper.md`,
`docs/multi-tenancy.md`, `docs/helpers.md`, `docs/best-practices.md` e
`docs/development.md`. Tabelas locais consultadas: `matrizes_filiais` e
`funcionarios_filiais`. A correção não modifica as ferramentas de negócio.

Evidência de publicação: 5/5 arquivos conferidos por SHA-256. A tela real de produção foi recarregada no navegador e mostrou “Empresas/filiais com acesso (4)”, as quatro unidades com nome e ID e as cinco categorias marcadas. Nenhum consentimento foi enviado; a homologação completa continua pendente. Não há comandos de Composer ou migration necessários para esta correção.

### Ajuste solicitado — nomes sem IDs

- [x] Remover os IDs da lista de empresas/filiais, mantendo apenas os nomes.
- [x] Atualizar a documentação e a verificação existente do template; 118 verificações MCP e PHP lint aprovados.
- A identificação interna das unidades permanece preservada; não há mudança nas regras de acesso.

## Metadados e pacote privado 7Carros — 1.0.1

- [x] Confirmar desenvolvedor/autor 7Carros e site https://7carros.com com o usuário.
- [x] Completar nome, título, descrição, versão e site do servidor pelo SDK.
- [x] Criar manifests Agent Plugins com apresentação e OAuth/CIMD, sem credenciais.
- [x] Gerar ZIP com apenas plugin.json e mcp.json na raiz, inspecionar conteúdo e integridade.
- [x] Validar schemas do manifest e núcleo portátil MCP e conferir a extensão OpenAI separadamente.
- [x] Registrar divergência entre o schema genérico MCP e o exemplo OAuth oficial OpenAI.
- [x] Executar 119 verificações de integração, 51 CIMD/piloto e PHP lint dos PHP alterados.
- [x] Conferir no navegador o caminho de upload privado; não enviar ou instalar pelo usuário.
- [x] Documentar instalação, autoria, reconstrução do ZIP e limitações da validação.
- [x] Publicar runtime/documentação alterados via FTP e conferir hashes.
- [ ] Usuário enviar o ZIP no ChatGPT e conferir site e desenvolvedor apresentados.
- [ ] Usuário validar conexão, consulta, renovação e revogação reais.

O ZIP é entregue localmente; os manifests não são runtime PHP e não precisam ser
instalados no servidor. Não há mudanças nas ENVs, migrations, dependências ou
fontes CSS/JS. Nenhuma submissão pública foi realizada. O site respondeu HTTP 200
após redirecionar para https://www.7carros.com.br/.

Documentos consultados: AGENTS.md, docs/mcp.md, docs/architecture.md,
docs/development.md, referências oficiais OpenAI de metadados/autenticação,
schemas Agent Plugins 1.0.0 e SDK local (Builder, Implementation e Rev2026Codec).

Publicação dos metadados: 3/3 arquivos de runtime/documentação conferidos por SHA-256. Artefato local: integrations/chatgpt/7carros-chatgpt-1.0.1.zip, com dois manifests na raiz; SHA-256 b264740a3ce24e599a67728013df8352474be60053cc2fed52e9f61dff9f6121. Upload/instalação no ChatGPT e apresentação final de site/desenvolvedor continuam pendentes do usuário.

## Correção da confirmação do consentimento — 16/11/2026

- [x] Confirmar CSP bloqueada no console e autorizações sem troca por token no diagnóstico read-only.
- [x] Permitir na CSP apenas self e origem HTTPS do callback validado, preservando as demais diretivas.
- [x] Usar HTTP 303 em autorização e recusa, preservando state e iss.
- [x] Bloquear cliques repetidos, mostrar processamento e preservar a decisão no POST.
- [x] Validar antes de consumir a tentativa; persistir claim exclusivo e não restaurá-lo após falha.
- [x] Diferenciar erros 400/403/409/410/503 e incluir referência e logs sem dados sensíveis.
- [x] Executar 137 verificações de integração e 51 CIMD/piloto, incluindo o fluxo HTTP com CSRF.
- [x] Validar no navegador local clique duplo com um POST, redirecionamento entre origens e recusa por teclado.
- [x] Minificar o JavaScript com Terser; PHP lint, JS syntax e diff check aprovados.
- [x] Atualizar documentação e critérios para diagnóstico de eventual 520.
- [x] Publicar arquivos do escopo via FTP e conferir hashes, enviando somente JS minificado.
- [ ] Usuário iniciar tentativa nova no ChatGPT e confirmar retorno, troca por token e consulta com um clique.

Sem novas classes de runtime, dependências, migrations, alteração de ENVs ou
ampliação do piloto. Nenhum ZIP novo necessário. A tela antiga deve ser fechada;
reutilizá-la preserva a CSP antiga e o state/consentimento podem ter expirado.
O erro Cloudflare 520 permanece sem causa comprovada e não foi atribuído à CSP.
O teste local do formulário não concede acesso real ao ChatGPT.

Documentos consultados: AGENTS.md, docs/mcp.md, docs/architecture.md,
docs/querybuilder.md, docs/database.md, docs/security.md, docs/logs.md,
docs/modals.md e docs/development.md. Schema local de mcp_connections,
mcp_credentials e mcp_audit consultado via terminal; não há novas queries de produção.

Publicação da correção de consentimento: 6/6 arquivos conferidos por SHA-256. O JavaScript original permanece local/versionável; somente a versão minificada foi enviada ao servidor. Não é necessário executar Composer, migrations ou reenviar o ZIP do plugin. Homologação externa permanece pendente da nova tentativa do usuário no ChatGPT.


## Correção da descoberta após OAuth — 16/11/2026

- [x] Inspecionar schema local/produção e explicar a diferença de timestamp sem migration.
- [x] Substituir transporte exclusivo pelo transporte do SDK que atende ambos os fluxos, sem sessão persistente.
- [x] Adicionar diagnóstico por etapa e referência em todas as respostas do Gateway.
- [x] Concluir lint e regressões MCP/CIMD: 178 e 51 verificações aprovadas, incluindo descoberta e consultas pelas revisões de 2025.
- [x] Publicar os seis arquivos de runtime/documentação por FTP com hashes SHA-256 conferidos; HTTP externo desta máquina permanece 403 Cloudflare sem X-Request-Id.
- [ ] Repetir conexão real no ChatGPT e correlacionar logs PHP/acesso/Cloudflare; registrar revisão efetiva.
- [ ] Concluir consulta de frota, renovação e revogação antes de registrar homologação externa.

A correção não altera dependências, banco, configuração do piloto ou assets.
As evidências e limites do diagnóstico estão em `docs/mcp.md`.


## Correção de Authorization Apache/suPHP — 16/11/2026

- [x] Identificar no console CWP os 401 reais do ChatGPT antes do transporte.
- [x] Reproduzir a perda do bearer diretamente no Apache, isolando Nginx e Cloudflare.
- [x] Preservar Authorization em `/mcp` antes do rewrite para o front controller e normalizar CGI somente quando o header PSR-7 estiver ausente.
- [x] Diferenciar header ausente, bearer malformado e token rejeitado sem logging de credenciais.
- [x] Validar 181 verificações de integração, 51 CIMD/piloto e sete cenários CGI/SAPI; lint aprovado.
- [x] Publicar runtime e documentação por LFTP com hashes conferidos, preservando AddHandler de produção e cópias anteriores.
- [x] Comprovar na origem Apache e via Nginx que o bearer sintético atinge o parser JWT; verificar login/metadados 200 e GET `/mcp` 405.
- [ ] Concluir nova conexão real no ChatGPT, descoberta, consulta, renovação e revogação antes de homologar.

As referências dos testes e o caminho correto do log estão em `docs/mcp.md`.
Sem alterações de schema, dependências, piloto, configuração global ou WAF.


## Correção do Host duplicado — 16/11/2026

- [x] Correlacionar a nova tentativa: authenticated seguido de transport_response 403.
- [x] Reproduzir duplicação de Host no PHP local e no servidor; confirmar rejeição pelo middleware DNS rebinding.
- [x] Normalizar somente o Host acrescentado pela construção PSR-7, preservando rejeição de conflitos e portas.
- [x] Cobrir o caminho HTTP real por Gateway::request(), autenticação, transporte, catálogo e consulta com fixtures locais descartáveis.
- [x] Aprovar 193 verificações MCP, 51 CIMD/piloto, 14 cenários CGI/SAPI e lint.
- [x] Publicar Gateway e documentação por LFTP com hashes conferidos; validar Host único no servidor e proteção mantida (200 permitido, 403 conflitante).
- [ ] Concluir descoberta/consulta reais no ChatGPT e correlacionar os logs antes de declarar homologação, incluindo renovação e revogação.

A lacuna dos testes anteriores e seus limites estão registrados em `docs/mcp.md`.
Não são necessárias migrations, Composer ou alterações de WAF/configuração global.


## Correção de timeout MariaDB — 16/11/2026

- [x] Confirmar erro 1193 em produção ao configurar MAX_EXECUTION_TIME; banco local MySQL 5.7.44 e produção MariaDB 10.2.44.
- [x] Conferir schema via terminal e configurar limite equivalente de 15 segundos por sessão, pela conexão existente do Model.
- [x] Acrescentar diagnóstico técnico seguro por etapa em falhas de ferramenta.
- [x] Aprovar 196 verificações MCP, 51 CIMD/piloto, 14 CGI/SAPI e lint.
- [x] Publicar runtime por LFTP com hashes e cópias anteriores; validar ReadModel no servidor com contexto da conexão existente (19 veículos acessíveis, limite de 15 segundos).
- [x] Validar nova pergunta no mesmo chat: ChatGPT respondeu total de 19 veículos; auditoria listar_veiculos success às 14:46:57, referência 808d45d7a036b544773bd5463df77848.

Sem nova autorização OAuth, migrations, Composer, mudanças de schema ou permissões.


### Liberação global por configuração — 16/11/2026

- Adicionada `MCP_ALL_TENANTS=false` por padrão. Com `true` e fora do piloto,
  disponibiliza MCP às locadoras atuais e futuras sem manter uma lista de chaves.
- O piloto prevalece; homologação de aplicativos, permissões, filiais e
  consentimento permanecem obrigatórios. Não há concessão automática ou migration.
- Diagnóstico global exige `--tenant=CHAVE` junto ao usuário escolhido e reutiliza
  a consulta tenant-scoped existente, sem varredura de locadoras.
- Implantação: publicar o runtime e configurar o ENV diretamente no servidor
  conforme `docs/mcp.md`. Publicação não ativa a flag global.
- Homologação externa de renovação/revogação e validação de uma segunda locadora
  devem ser registradas separadamente; testes locais não substituem essas etapas.


### Homologação ChatGPT e modo global ativos — 16/11/2026

- [x] Diagnosticar reconexão bloqueada: modo de produção exigia homologação e o
  cadastro ChatGPT ainda tinha `homologated=0`, com usuário ativo e autorizado.
- [x] Restabelecer piloto temporário, reconectar plugin existente e validar consulta.
- [x] Revogar pela tela IA/MCP e confirmar bloqueio da chamada seguinte no ChatGPT.
- [x] Reconectar e aguardar expiração natural de dez minutos; comprovar renovação
  automática e consulta real sem novo consentimento.
- [x] Registrar homologação ChatGPT pelo script operacional, com referências reais
  descritas em `docs/mcp.md`, e restaurar modo global com diagnóstico aprovado.
- [ ] Validar uma segunda locadora com seu próprio usuário autorizado.

Nenhuma mudança adicional de runtime, migration, permissões ou chaves. Os clientes
Claude mantêm a homologação externa de renovação/revogação pendente.
