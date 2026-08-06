# 7Carros Locadora

Sistema de gestão multi-tenant para locadoras de veículos desenvolvido em PHP 8.3.

## CHECKLIST OBRIGATORIO (Antes de Qualquer Alteracao de Codigo)

**PARE e consulte a documentacao relevante:**

| Topico | Documentacao |
|--------|--------------|
| QueryBuilder / Multi-tenancy | `docs/querybuilder.md` |
| Arquitetura MVC / Models | `docs/architecture.md` |
| Filtros de Filial | `docs/filial-helper.md` |
| Contratos | `docs/contratos.md` |
| Locacoes | `docs/locacoes.md` |
| Taxas e Servicos | `docs/taxaseservicos.md` |
| Geracao de PDF | `docs/pdf.md` |
| Relatorios (especificacao) | `docs/relatorios.md` |
| Relatorios (desenvolvimento) | `docs/relatorios-dev.md` |
| **Modais e Alertas** | `docs/modals.md` |
| Comissões Investidores | `docs/comissoes-investidores.md` |
| Financeiro / Parcelamento | `docs/financeiro.md` |
| Gateways de Pagamento | `docs/gateways.md` |
| Checklist Digital | `docs/checklists.md` |
| Portal Cliente / Investidor | `docs/portal-cliente-investidor.md` |

**Regras de Ouro:**
1. **NUNCA use `withoutChave()` em CRUD normal** - exceto nos contextos listados em `docs/querybuilder.md` (CRON, Auth, migrations, provisioning, webhooks, rotas publicas, templates globais, tabelas sem coluna chave). **NUNCA** faca `withoutChave()->where('chave','=',$chave)` (redundante) nem `withoutChave()->where('id','=',$id)` (bug de cross-tenant)
2. **NUNCA crie conexoes no Controller** - use Models com Singleton
3. **NUNCA use `Template::render()` para gerar PDF** - use output buffering (veja `docs/pdf.md`)
4. **NUNCA use `alert()` nativo do JavaScript** - use `openAlert` via postMessage (veja `docs/modals.md`)
5. **Modais fullscreen devem estar no `app.php`** - NAO no iframe (veja `docs/modals.md`)
6. **Se existir documentacao, siga-a** - nao assuma que codigo existente esta correto
7. **SEMPRE VALIDE O SCHEMA LOCAL via terminal antes de alterar código que acesse dados** - Conecte ao banco configurado em `.env.development` com `DB_HOST=localhost`, execute `DESCRIBE`/`SHOW COLUMNS` nas tabelas afetadas e confirme nomes, tipos e nulabilidade das colunas. Teste a consulta no localhost antes de editar Models, queries, migrations ou integrações. Nunca deduza o nome de uma coluna apenas pelo formulário ou pelo código existente. `temp-bd.txt` serve somente para diagnóstico read-only de produção quando necessário e não substitui a validação local (veja `docs/database.md`).
8. **DEFINER MySQL em producao deve ser `7carros_locador@localhost`** - triggers, views, routines e events nao podem usar usuario pessoal, IP externo, wildcard `%` ou usuario inexistente. Recrie o objeto conectado como `7carros_locador@localhost` (veja `docs/database.md`).

## Diretrizes de Comunicação
- Priorizar respostas técnicas honestas sobre validação de opiniões
- Questionar premissas quando a justificativa for fraca (ex: "fica mais bonito")
- Apontar trade-offs reais mesmo que contradiga a intuição inicial
- Se pensar em fazer algo que não foi pedido, pergunte antes, de forma inteligente.
- Idioma de comunicação pt-BR.
- Quando for gerar algum teste que envie email, sms ou whatsapp, use apenas o tenant com chave = 1111111111111.

## Regras de Desenvolvimento
- **NUNCA editar arquivos `*.min.*`** - sempre editar o arquivo original (ex: `components.css`, não `components.min.css`)
- **SEMPRE minificar arquivos CSS e JS após edição** - usar `npx terser arquivo.js -o arquivo.min.js --compress --mangle` para JS e ferramenta equivalente para CSS
- **SEMPRE publicar no FTP após alterações** - usar `temp-lftp.txt`, preservar os caminhos relativos e enviar somente os arquivos do escopo alterado. Para CSS/JS, editar a fonte e gerar o respectivo `*.min.*`, mas enviar ao FTP somente a versão minificada. Nunca enviar `temp-lftp.txt`, arquivos de credenciais ou outros arquivos ignorados pelo Git.
- **ATUALIZACOES EM LOTE DOS WEBSITES DEVEM SER EXECUTADAS NO TERMINAL DO SERVIDOR** - ao concluir uma alteracao em `storage/templates/website`, informe os comandos completos de dry-run e aplicacao de `scripts/publicar-atualizacao-websites.php`, usando `--env=production` e a versao real de `versao.json`. O comando usa o `.env.production` do servidor com `DB_HOST=localhost`; nunca fixe host ou credenciais no PHP e nunca use `temp-bd.txt` para essa publicacao.
- **NUNCA exibir instruções, explicações ou avisos abaixo de inputs, selects ou textareas** - associe o texto ao rótulo do campo usando o helper `aviso()` (veja `docs/helpers.md` e `docs/best-practices.md`)

## Documentação

### 🚀 Primeiros Passos
- **[Visão Geral do Projeto](docs/overview.md)** - Introdução, status e stack tecnológico
- **[Comandos de Desenvolvimento](docs/development.md)** - Setup, Composer, database e testes

### 🏗️ Arquitetura & Design
- **[Arquitetura do Sistema](docs/architecture.md)** - Estrutura de diretórios e padrões
- **[Helpers do Sistema](docs/helpers.md)** - Funções auxiliares PHP e JavaScript
- **[Sistema de Iframes](docs/iframe-system.md)** - Navegação por abas, loading e comunicação
- **[Sistema de Modais](docs/modals.md)** - Modais globais, alertas e comunicação iframe-parent
- **[Geração de PDF](docs/pdf.md)** - mPDF, output buffering e padrões de impressão
- **[Chosen Select](docs/chosen-select.md)** - Componente de select com busca (client/server-side)
- **[Boas Práticas](docs/best-practices.md)** - Segurança e guidelines de desenvolvimento
- **[Lista de componentes](_backup/v2/componentes.html)** - Lista com todos os componentes que podem ser usados

### 💾 Banco de Dados
- **[QueryBuilder](docs/querybuilder.md)** - Camada de abstração de queries
- **[Padrões de Banco](docs/database.md)** - Schema, convenções e otimização
- **[Migrações](docs/migrations.md)** - Gerenciamento de schema
- **[Cache](docs/cache.md)** - Sistema de cache da aplicação
- **[Formatação de Moeda](docs/currency.md)** - Sistema multi-tenant de formatação monetária
- **[Formatação de Data](docs/date.md)** - Sistema multi-tenant de formatação de datas

### 🔐 Segurança & Multi-tenancy
- **[Segurança](docs/security.md)** - Visão geral de todos os recursos de segurança
- **[Sistema de Roles](docs/roles.md)** - RBAC, permissões e atribuição a funcionários
- **[Sistema de Logs](docs/logs.md)** - Auditoria, segurança, FormAudit e handlers especializados
- **[Multi-tenancy](docs/multi-tenancy.md)** - Mecanismo de isolamento via `chave`
- **[Filtros por Filiais](docs/filial-helper.md)** - Controle de acesso multi-filial
- **[Planos de Assinatura](docs/plans.md)** - Tiers e limites por plano
- **[Configuração de Ambiente](docs/environment.md)** - Variáveis .env

### 📦 Módulos do Sistema
- **[Checklist Digital](docs/checklists.md)** - Vistoria digital mobile, questionário, fotos e assinatura
- **[Contratos](docs/contratos.md)** - Gestão de contratos de locação e impressão
- **[Locações](docs/locacoes.md)** - Gestão de locações de curta duração (diárias)
- **[Multas](docs/multas.md)** - Central de Multas, integração SERPRO, indicação de condutor, impressão (4 tipos de PDF)
- **[Documentos (modelos)](docs/documentos.md)** - Modelos de documento por tenant, tipos e usos em contratos/locações/multas
- **[Taxas e Serviços](docs/taxaseservicos.md)** - Taxas adicionais, regras de cálculo e integração com contratos
- **[Assinaturas Digitais](docs/assinaturas.md)** - Sistema de assinatura digital para contratos e locações
- **[Upload de Arquivos](docs/file-helper.md)** - FileHelper e ImageHelper (conversão para WebP)
- **[Formas de Pagamento](docs/formas-pagamento.md)** - Taxas, descontos por antecipação, multa e juros
- **[Sistema de Grupos](docs/grupos.md)** - Categorização de veículos e precificação
- **[Estoque](docs/estoque.md)** - Inventário de peças, baixa automática e estoque negativo
- **[Manutenção Preventiva](docs/preventive-maintenance.md)** - Planos de manutenção e geração automática de OS
- **[Templates de Mensagem](docs/templates.md)** - Sistema de templates para email, WhatsApp e SMS
- **[Comissões Investidores](docs/comissoes-investidores.md)** - Comissões para fornecedores investidores de veículos
- **[Portal Cliente/Investidor](docs/portal-cliente-investidor.md)** - Área autenticada publicada no website para clientes e fornecedores investidores
- **[Relatórios](docs/relatorios.md)** - Especificação funcional de todos os relatórios do sistema
- **[Relatórios - Dev](docs/relatorios-dev.md)** - Guia técnico para desenvolvimento de relatórios (arquitetura, padrões, checklist)
- **[Gateways de Pagamento](docs/gateways.md)** - Multi-gateway, links públicos, webhooks e tokenização

### 🔌 Integrações & Automação
- **[API - Requisições JavaScript](docs/api.md)** - Helper para requisições HTTP com CSRF
- **[Integrações Externas](docs/integrations.md)** - APIs de pagamento, email, WhatsApp
- **[Integração WHMCS](docs/whmcs.md)** - Provisionamento de tenants via WHMCS (criar, suspender, reativar, mudar plano, senha, terminar)
- **[Sistema de Mensageria](docs/messaging.md)** - Fila de mensagens com RabbitMQ
- **[Cron Jobs](docs/cron.md)** - Tarefas agendadas

### 🌍 Internacionalização
- **[Sistema i18n](docs/i18n.md)** - Tradução e suporte a múltiplos idiomas

## 📋 Referência Rápida

| Informação | Valor |
|------------|-------|
| **PHP Version** | 8.3 |
| **Database** | MySQL (utf8mb4) |
| **Package Manager** | Composer |
| **Autoloading** | PSR-4 (App\ → app/) |
| **Multi-tenancy** | Session-based (chave) |

## 🔍 Tópicos Essenciais

### Para começar o desenvolvimento
1. Leia [overview.md](docs/overview.md) para entender o projeto
2. Configure o ambiente com [environment.md](docs/environment.md)
3. Execute os comandos em [development.md](docs/development.md)
4. Entenda [multi-tenancy.md](docs/multi-tenancy.md) antes de criar features

### Para implementar features
1. Consulte [architecture.md](docs/architecture.md) para estrutura
2. Use [querybuilder.md](docs/querybuilder.md) para queries de banco
3. Siga [best-practices.md](docs/best-practices.md) para segurança
4. Revise [database.md](docs/database.md) para padrões de schema

### Para integrar serviços externos
- Veja [integrations.md](docs/integrations.md) para APIs disponíveis
- Configure credenciais em [environment.md](docs/environment.md)
