# Portal do Cliente e do Fornecedor Investidor

## Visão Geral

O portal é publicado junto com o website de cada tenant e oferece uma área
autenticada para dois perfis independentes:

- **Cliente:** consulta seu relacionamento com a locadora, acessa faturas e
  atualiza dados de contato e endereço.
- **Fornecedor investidor:** acompanha os veículos fornecidos, operações,
  manutenções, comissões e desempenho do investimento.

A mesma pessoa pode existir nos dois cadastros. O perfil é escolhido antes do
login e cada acesso permanece isolado pelo tenant, perfil e entidade da sessão.

O portal está disponível em `painel.php` nos websites publicados com o template
`1.3.0` ou superior. O cabeçalho e o rodapé do site apontam para essa página.

## Matriz de Funcionalidades

| Funcionalidade | Cliente | Investidor |
|----------------|:-------:|:----------:|
| Dashboard | Sim | Sim |
| Atualização de contato e endereço | Sim | Sim |
| Alteração e redefinição de senha | Sim | Sim |
| Contratos | Sim | Não |
| Reservas e locações | Sim | Não |
| Faturas, pagamento e recibo | Sim | Não |
| Multas | Sim | Não |
| Veículos relacionados | Sim | Sim |
| Manutenções | Sim | Sim |
| Operações dos veículos | Não | Sim |
| Comissões | Não | Sim |
| Desempenho por período | Não | Sim |
| Link de indicação | Sim | Não |

### Dashboard do cliente

Apresenta:

- veículos distintos vinculados ao histórico do cliente;
- contratos abertos e fechados;
- reservas pendentes/reservadas e locações abertas/fechadas;
- faturas abertas, pagas e vencidas, incluindo o valor total em aberto;
- multas abertas e pagas;
- manutenções vinculadas ao cliente;
- próxima reserva, contrato ativo e atividades financeiras recentes.

Os status usados no resumo seguem os códigos atuais dos módulos:

| Recurso | Classificação |
|---------|---------------|
| Contrato | `A` aberto; demais códigos fechados |
| Reserva | locação com status `P` ou `R` |
| Locação | `A` aberta; `F` fechada |
| Fatura | receita (`tipo = R`) com `pago = N/S`; vencida quando não paga e anterior à data atual |
| Multa | `pago = N/S` |
| Manutenção | qualquer manutenção vinculada por `id_cliente` |

### Recursos do cliente

- **Contratos:** código, período, valor e situação.
- **Reservas e locações:** código, retirada, devolução prevista, valor e
  situação.
- **Faturas:** abertas, vencidas e pagas. Faturas não pagas podem solicitar um
  link atualizado de pagamento; faturas pagas podem emitir recibo em PDF.
- **Multas:** auto de infração, veículo, data, valor e situação.
- **Manutenções:** ordem de serviço, veículo, motivo, período, valor e situação.
- **Veículos:** foto, marca, modelo, ano, placa, cor e períodos em que o veículo
  esteve relacionado ao cliente.
- **Indicação:** código permanente do cliente, link copiável para
  `reserva.php?indicacao={codigo}` e contadores de cliques/conversões.

O programa de indicação ainda não possui recompensa financeira. A estrutura
para eventos existe, mas o registro automático de cliques, atribuição e
conversão ainda não está conectado ao fluxo de reserva; até essa integração, os
contadores permanecem apenas como infraestrutura disponível.

### Dashboard e recursos do investidor

O dashboard reutiliza `FornecedoresReport::investidor()` e aceita período de
consulta. Apresenta veículos ativos, valor investido, receita gerada, comissão
pendente, comissão paga, saldo, manutenções abertas, detalhamento dos veículos
e dados para gráfico.

O investidor também acessa:

- **Veículos:** somente registros cujo `id_fornecedor` corresponda à sessão;
- **Operações:** períodos e dias ocupados em contratos e locações dos seus
  veículos, sem dados pessoais dos locatários;
- **Manutenções:** somente as vinculadas aos seus veículos;
- **Comissões:** origem, veículo, valor base, repasse, status e datas dentro do
  período;
- **Desempenho:** a mesma regra financeira do dashboard e do relatório
  administrativo de fornecedor investidor.

O portal não estima comissões hipotéticas. Os valores financeiros são derivados
dos registros efetivamente existentes em `comissoes_investidores`. Consulte
[Comissões de Investidores](./comissoes-investidores.md) e
[Relatórios](./relatorios.md).

## Perfil e Campos Editáveis

O backend aplica uma whitelist; campos extras enviados pelo navegador são
ignorados.

### Cliente

Editáveis:

- e-mail e telefone principais;
- CEP, rua, número, complemento, bairro, cidade, estado e país;
- idioma preferido (`preferred_locale`).

Somente leitura:

- nome/razão social;
- nome fantasia;
- CPF/CNPJ;
- RG/IE.

E-mail e telefone são atualizados nas tabelas de contatos. Somente o contato
principal é alterado; contatos secundários são preservados.

### Investidor

Editáveis:

- e-mail;
- telefones principal e secundário;
- CEP, rua, número, complemento, bairro, cidade, estado e país.

Somente leitura:

- nome/razão social, nome fantasia, CPF/CNPJ e RG/IE;
- PIX;
- banco, agência, conta e tipo de conta.

Dados bancários, split e regras de comissão continuam administrados
exclusivamente no sistema interno.

## Autenticação e Sessão

### Login

O usuário informa:

1. perfil `cliente` ou `investidor`;
2. e-mail ou CPF/CNPJ;
3. senha.

O login exige exatamente um cadastro compatível dentro do tenant. Identificador
inexistente ou duplicado retorna a mesma resposta neutra de credenciais
inválidas.

- Cliente precisa estar ativo.
- Fornecedor precisa ter `investidor = 1`.
- Senhas são verificadas com `password_verify()`.
- Hash legado aceito é atualizado para Argon2id após login válido.
- Cinco falhas para a mesma combinação de tenant, perfil, identificador e IP
  bloqueiam novas tentativas por 15 minutos.

O cadastro manual de cliente não cria uma senha automaticamente em todos os
fluxos. O acesso deve ser habilitado por uma senha já cadastrada ou pelo fluxo
“Esqueci minha senha”. Nunca envie senha em texto puro.

### Duas camadas de autenticação

```text
Navegador
  └─ cookie da sessão PHP + CSRF
       └─ proxy PHP no website publicado
            ├─ X-Site-Token
            └─ X-Portal-Token
                 └─ API do sistema principal
```

- `X-Site-Token` autentica o website e determina o tenant.
- `X-Portal-Token` autentica a sessão do cliente/investidor.
- O token do portal fica somente na sessão PHP do website e não é exposto ao
  JavaScript.
- O navegador nunca envia `id_cliente` ou `id_fornecedor`; a API deriva a
  entidade do token.
- Alterações feitas no proxy exigem o CSRF da sessão local.

O website encaminha IP e user-agent reais pelos headers
`X-Portal-Client-IP` e `X-Portal-Client-Agent`. Esses headers só são aceitos
depois da validação do `X-Site-Token`.

### Ciclo de vida

- token aleatório de 32 bytes, representado por 64 caracteres hexadecimais;
- somente SHA-256 do token é armazenado em `portal_sessions`;
- expiração após 30 minutos sem uma requisição válida;
- limite absoluto de 12 horas desde a criação;
- vínculo ao hash do user-agent;
- logout grava `revoked_at`;
- troca ou redefinição de senha revoga todas as sessões daquela entidade.

### Redefinição de senha

O pedido sempre retorna uma resposta neutra. Quando existe exatamente um
cadastro com e-mail:

- cliente usa `cliente_password_resets`;
- investidor usa `fornecedor_password_resets`;
- o token é aleatório e somente seu SHA-256 é persistido;
- a validade é de 60 minutos e o uso é único;
- um novo pedido invalida tokens pendentes anteriores;
- o formulário standalone usa CSRF;
- a nova senha precisa ter no mínimo oito caracteres e é salva com Argon2id.

O template de mensagem utilizado é `cliente_nova_senha`, também para o
investidor.

## Arquitetura e Fluxo de Dados

### Componentes no sistema principal

| Componente | Responsabilidade |
|------------|------------------|
| `PortalController` | Interface HTTP, autenticação do site/portal e respostas |
| `PortalAuthService` | Login, bloqueio, reset, troca de senha e revogação |
| `PortalRepository` | Consultas e atualizações permitidas para os dois perfis |
| `PortalSession` | Criação, validação e revogação das sessões opacas |
| `PortalAuditLog` | Auditoria das alterações cadastrais e de senha |
| `PortalIndicacao` | Código e resumo de indicação |
| `PortalProfileNotificationService` | Notificação das alterações de perfil |

Controllers não abrem conexões. Models usam a conexão Singleton e o
QueryBuilder com o contexto de `chave`. As rotas públicas são uma exceção
documentada de autenticação: o tenant é estabelecido após validar
`X-Site-Token`; a partir daí as consultas permanecem tenant-scoped.

### Componentes no website publicado

| Arquivo | Responsabilidade |
|---------|------------------|
| `painel.php` | Login e aplicação do portal |
| `ajax-portal-login.php` | Login e criação da sessão PHP local |
| `ajax-portal-api.php` | Proxy JSON autenticado para recursos e alterações |
| `ajax-portal-logout.php` | Revogação e limpeza da sessão local |
| `portal-recibo.php` | Proxy autenticado para o PDF do recibo |
| `includes/portal-session.php` | Cookie, sessão e CSRF locais |
| `includes/api.php` | Chamadas server-to-server e encaminhamento dos headers |
| `assets/css/portal.min.css` | Estilos publicados do portal |
| `assets/js/portal.min.js` | Interface publicada do portal |

Os arquivos fonte `portal.css` e `portal.js` ficam no template para
desenvolvimento. O build publica somente as versões minificadas.

## API do Portal

Todas as rotas abaixo usam `rate_limit`. As rotas autenticadas exigem
`X-Site-Token` e `X-Portal-Token`.

| Método | Endpoint | Uso |
|--------|----------|-----|
| POST | `/api/public/portal/login` | Autenticar no perfil selecionado |
| POST | `/api/public/portal/logout` | Revogar a sessão |
| GET | `/api/public/portal/sessao` | Obter o perfil da sessão |
| GET | `/api/public/portal/dashboard` | Obter o dashboard |
| GET | `/api/public/portal/{recurso}` | Listar recurso permitido |
| PUT | `/api/public/portal/perfil` | Atualizar campos autorizados |
| POST | `/api/public/portal/senha` | Trocar a senha autenticada |
| POST | `/api/public/portal/senha/solicitar` | Solicitar redefinição |
| GET | `/public/portal/redefinir-senha` | Exibir formulário standalone |
| POST | `/api/public/portal/senha/definir` | Definir senha pelo token |
| POST | `/api/public/portal/faturas/{id}/link-pagamento` | Obter/criar link da própria fatura |
| GET | `/api/public/portal/faturas/{id}/recibo` | Emitir recibo da própria fatura paga |

Recursos de cliente: `contratos`, `locacoes`, `faturas`, `multas`,
`manutencoes`, `veiculos` e `indicacao`.

Recursos de investidor: `veiculos`, `manutencoes`, `comissoes`, `operacoes` e
`desempenho`.

As listagens aceitam `page` e `per_page`; o máximo é 100 registros por página.
Dashboard, comissões e desempenho do investidor aceitam `data_inicio` e
`data_fim`. Na ausência de período, a API utiliza os últimos 12 meses até a
data atual.

O `PUT` existe entre o proxy PHP e o sistema principal. A restrição de PUT/DELETE
da hospedagem descrita em [API](./api.md) aplica-se ao navegador do sistema
administrativo, não a essa chamada server-to-server.

## Financeiro, PDF e Privacidade

- O link de pagamento só pode ser solicitado para uma receita não paga do
  cliente autenticado.
- `PagamentoLinkSyncService::obterOuCriarLinkAtualizado()` reaproveita ou
  sincroniza a cobrança conforme as regras de [Gateways](./gateways.md).
- O recibo só existe para uma receita paga do próprio cliente.
- O PDF é gerado por `PdfHelper::outputInline()`, conforme [PDF](./pdf.md).
- O investidor não recebe nome, documento, contato ou arquivos de locatários
  nas operações dos veículos.

## Auditoria e Notificações

Atualizações de perfil são transacionais. Quando há mudança efetiva:

- `portal_audit_logs` registra perfil, entidade, ação, campos `de`/`para`, IP e
  hash do user-agent;
- a senha gera apenas a ação `senha_alterada`, sem persistir senha ou hash;
- funcionários ativos com a permissão
  `notificacoes.alteracoes_portal` recebem o resumo por e-mail;
- os destinatários são deduplicados;
- falha na fila de e-mail é registrada, mas não desfaz a atualização.

Para cliente, a notificação considera a filial do cadastro. Para investidor, é
utilizada a matriz principal.

## Banco de Dados

A migration `00412_create_portal_cliente_investidor.php`:

- adiciona `fornecedores.senha`;
- cria `portal_sessions`;
- cria `fornecedor_password_resets`;
- cria `portal_audit_logs`;
- cria `portal_indicacao_codigos` e `portal_indicacao_eventos`;
- adiciona índices de consulta em fornecedores, veículos, contratos, locações,
  multas, manutenções e comissões;
- cria `notificacoes.alteracoes_portal` e a concede inicialmente às roles
  Proprietário e Gerente.

Todas as tabelas do portal possuem `chave`. Não use `withoutChave()` nos fluxos
normais do portal. Consulte [QueryBuilder](./querybuilder.md) e
[Multi-tenancy](./multi-tenancy.md).

## Cadastro do Fornecedor Investidor

O campo **Senha do portal** fica na seção **Investidor**, imediatamente antes de
**Regras de comissão**.

- mínimo de oito caracteres;
- o Controller transforma o valor em hash Argon2id antes de chamar o Model;
- o Model recebe e persiste somente o hash;
- em edição, campo vazio preserva o hash atual;
- se a opção Investidor for desmarcada, a senha enviada pelo formulário não é
  aplicada;
- o hash não é retornado pela API de edição.

## Publicação

- O portal faz parte do template `1.3.0`.
- `WebsiteBuilderService` copia as páginas, proxies, helper de sessão e assets
  minificados.
- A publicação é feita pelo fluxo normal do módulo Website.
- Sites já publicados só recebem o portal após nova publicação/atualização do
  template; não existe republicação automática em massa.
- O token `deploy` deve ser usado no cache-busting de `portal.min.css` e
  `portal.min.js`.

## Checklist de Validação

- [ ] Login por e-mail e CPF/CNPJ nos dois perfis.
- [ ] Cliente inativo, fornecedor não investidor e identificador duplicado.
- [ ] Bloqueio após cinco falhas e liberação após 15 minutos.
- [ ] Expiração por inatividade, expiração absoluta e mudança de user-agent.
- [ ] Logout, troca de senha, reset expirado/usado e revogação das sessões.
- [ ] Isolamento entre tenants, perfis e entidades.
- [ ] Paginação e período do investidor.
- [ ] Fatura própria/terceira, link de pagamento e recibo.
- [ ] Whitelist do perfil, auditoria e notificação.
- [ ] Ausência de PII de locatários no perfil investidor.
- [ ] Layout responsivo e idiomas habilitados no website.
- [ ] Build e publicação no tenant de teste `1111111111111`.

Testes que enfileirem e-mail, SMS ou WhatsApp devem usar exclusivamente o
tenant `1111111111111`.

## Documentação Relacionada

- [Website](./website.md)
- [Segurança](./security.md)
- [Arquitetura](./architecture.md)
- [QueryBuilder](./querybuilder.md)
- [Comissões de Investidores](./comissoes-investidores.md)
- [Relatórios](./relatorios.md)
- [Gateways de Pagamento](./gateways.md)
- [Geração de PDF](./pdf.md)
