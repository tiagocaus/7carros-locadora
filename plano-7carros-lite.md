# 7Carros Lite — Plano de construção

**Data:** 24/09/2026

**Status:** especificação para implementação em um projeto novo.

**Público:** equipe ou agente de IA responsável por construir o produto.

## 1. Objetivo e decisões do produto

Construir um sistema independente de gestão de locadoras, com aplicativo Android/iOS e acesso pelo navegador. Todas as operações de negócio devem estar disponíveis pelas telas e pelo assistente de IA, respeitando as permissões do usuário.

“Lite” significa interface simples, poucos passos e configurações progressivas. Não significa remover controles financeiros, segurança ou rastreabilidade.

### Decisões confirmadas

| Tema | Decisão |
|---|---|
| Aplicativo | React Native, com suporte a celular e navegador |
| Backend | API PHP independente |
| Banco | MariaDB próprio |
| Empresas | Multi-tenant, com isolamento entre locadoras |
| Unidades | Uma ou várias por locadora |
| Operação | Contratos e locações em um único módulo |
| Contratos | Períodos semanais, mensais ou anuais; múltiplos veículos |
| Locações | Cobrança por diárias |
| Renovação | Automática por CRON, quando habilitada |
| Financeiro | Faturas automáticas na criação e nas renovações |
| Pagamentos | Stripe Connect e página hospedada da Stripe |
| Caução | Registro manual e pré-autorização no cartão |
| Documentos | PDF e assinatura por link |
| Vistoria | Saída e devolução, com fotos |
| Multas | Registro manual e cobrança vinculada |
| Comunicação | Cobranças por e-mail |
| IA | Texto e áudio gravado por botão |
| Confirmação | Toda ação de gravação proposta pela IA exige confirmação em tela |
| Países iniciais | Brasil, Portugal e Estados Unidos |
| Moedas | BRL, USD e EUR, cadastradas em tabela |
| Localização | Moeda, locale e fuso horário por unidade |

### Limites da primeira versão

- Sem migração ou sincronização com o sistema atual.
- Sem operação offline com sincronização posterior.
- Sem consulta automática de multas, comissões de investidores, estoque ou oficina completa.
- Sem WhatsApp, SMS, câmbio automático ou cobrança recorrente no cartão sem participação do pagador.
- Faturas são documentos financeiros internos; emissão fiscal e cálculo tributário automatizado ficam fora desta entrega.
- Assinatura eletrônica por link, sem prometer certificação ou equivalência automática a assinaturas qualificadas.
- Cobrança da assinatura do próprio software não integra este escopo.
- A IA auxilia a operação; fotos, assinaturas, autorizações Stripe e confirmações continuam dependendo das pessoas responsáveis.

## 2. Arquitetura, dados e segurança

### Stack e organização

Adotar:

- **Frontend:** React Native, Expo, TypeScript e Expo Router; versão web responsiva com React Native Web.
- **Backend:** Laravel 13, PHP 8.3 ou superior compatível, API REST JSON.
- **Persistência:** MariaDB, InnoDB e `utf8mb4`.
- **Processamento assíncrono:** filas e agendador Laravel; Redis para filas, locks e cache.
- **Arquivos:** armazenamento privado compatível com S3.
- **Autenticação:** Laravel Sanctum; sessão segura no navegador e tokens revogáveis no aplicativo.
- **Contrato de API:** OpenAPI versionada em `/api/v1`.
- **Estrutura:** um repositório novo com aplicativo, API, documentação e infraestrutura separados.

Expo contempla aplicações para Android, iOS e web. Laravel oferece suporte a MariaDB e autenticação para aplicações móveis e SPA. Referências: [Expo](https://docs.expo.dev/workflow/web/), [Laravel Database](https://laravel.com/framework/docs/13.x/database), [Sanctum](https://laravel.com/framework/docs/12.x/sanctum), [Laravel 13](https://laravel.com/framework/docs/13.x/releases).

```text
Aplicativo Android/iOS       Navegador
          |                    |
          +---------+----------+
                    |
                 API PHP
                    |
       Autenticação e autorização
                    |
          Contexto tenant/unidade
                    |
          Serviços de negócio
           /        |        \
      MariaDB     Filas     Arquivos privados
                    |
          Stripe / E-mail / IA
```

Controllers recebem e validam requisições. Serviços executam regras de negócio. Models/repositórios acessam dados por conexões gerenciadas pelo framework. Telas, IA e tarefas automáticas reutilizam esses serviços.

### Entidades principais

- Locadoras, unidades, usuários, vínculos e permissões.
- Países, moedas e configurações regionais.
- Clientes, condutores, veículos e categorias.
- Aluguéis, vínculos de veículos e ciclos contratuais.
- Vistorias, fotos, documentos e assinaturas.
- Faturas, itens, pagamentos, estornos e cauções.
- Multas e bloqueios de disponibilidade.
- Conexões Stripe, eventos recebidos e operações externas.
- Conversas, propostas de ação da IA e aprovações.
- Auditoria, notificações e tarefas pendentes.

Não copiar o schema do sistema atual. Criar migrations próprias do novo projeto e executá-las pelo executor oficial Laravel. Antes de alterar consultas sobre tabelas já existentes no Lite, inspecionar o schema local pelo terminal.

### Isolamento obrigatório

- Todas as entidades privadas pertencem a um `tenant_id`.
- A API resolve o tenant pela autenticação e pelo vínculo autorizado. Um identificador enviado pelo aplicativo ou pela IA nunca basta para conceder acesso.
- Consultas, relacionamentos, agregações, exportações e downloads aplicam o contexto do tenant.
- Ausência de contexto bloqueia a operação.
- Foreign keys compostas com tenant impedem relacionamentos entre empresas diferentes.
- Tabelas globais, como moedas, usam acesso explicitamente separado.
- Arquivos, cache, filas, conversas e eventos também são isolados.
- Jobs recebem o tenant explicitamente e limpam seu contexto ao terminar.
- Nunca disponibilizar SQL arbitrário, acesso ao filesystem ou credenciais como ferramentas da IA.

Usar isolamento na aplicação e constraints como camadas complementares; um filtro automático isolado não constitui garantia suficiente.

### Unidades e permissões

- Perfis iniciais: proprietário, gerente, atendimento e financeiro.
- Permissões por ação, com acesso limitado às unidades atribuídas.
- Proprietário gerencia usuários, unidades e integrações.
- Atendimento gerencia clientes, reservas, retirada, devolução e vistoria.
- Financeiro gerencia faturas, pagamentos, cauções e estornos.
- Exclusões, estornos e gestão de acessos possuem permissões específicas.
- Clientes podem ser compartilhados dentro da locadora; histórico operacional e financeiro respeita as unidades autorizadas.
- Cada aluguel pertence a uma unidade. Movimentações entre unidades exigem permissão nas unidades envolvidas e veículo sem vínculo operacional ativo.

### Datas e moedas

Criar helpers centrais equivalentes no PHP e no TypeScript:

- `formatDate`, `formatDateTime`, `parseDate`.
- `formatMoney`, `parseMoney` e conversão para unidades monetárias mínimas.
- Cálculo de períodos no fuso da unidade.

A tabela de moedas contém código ISO, casas decimais e situação ativa. País, moeda, idioma e fuso são configurações distintas.

- Valores monetários usam aritmética decimal exata, nunca `float`.
- API transporta valores decimais como strings e informa a moeda.
- Datas civis usam `YYYY-MM-DD`; instantes usam UTC com contexto de fuso.
- Datas ambíguas recebidas pela IA exigem esclarecimento.
- Moeda e condições ficam registradas no aluguel e na fatura; alterações na unidade não modificam documentos anteriores.
- Relatórios agrupam valores por moeda, sem conversão implícita.
- Incluir traduções iniciais `pt-BR`, `pt-PT` e `en-US`.
- Dicas de preenchimento ficam associadas aos rótulos, em componente de ajuda.

## 3. Operação simplificada

### Navegação

Menus principais: **Início, Aluguéis, Veículos, Clientes, Financeiro e Mais**.

“Mais” reúne multas, unidades, usuários e configurações. Vistorias e documentos também são acessíveis diretamente pelo aluguel.

O botão da IA fica disponível em toda a aplicação. No computador, abre painel lateral; no celular, uma tela dedicada. Ambos exibem claramente a locadora e a unidade em uso.

### Aluguéis

Um único cadastro com escolha entre **Locação** e **Contrato**:

| Comportamento | Locação | Contrato |
|---|---|---|
| Veículos simultâneos | Um | Um ou vários |
| Preço | Por diária | Por semana, mês ou ano |
| Reserva | Por categoria ou veículo | Com veículos definidos |
| Renovação automática | Não | Opcional |
| Devolução | Integral | Parcial ou integral |
| Substituição | Com histórico | Com histórico |

Fluxo: **Rascunho → Reservado → Em andamento → Encerrado**. Cancelamento disponível antes da retirada. Após a retirada, usar devolução e acerto financeiro, preservando o histórico.

Formulário em quatro etapas:

1. Cliente e unidade.
2. Veículo ou categoria e período.
3. Preço, adicionais, caução e pagamento.
4. Resumo e confirmação.

Campos avançados ficam recolhidos. O tipo do aluguel não pode ser alterado após sua confirmação.

### Disponibilidade, retirada e devolução

- Reservas por categoria consomem capacidade da categoria; preferência de veículo não equivale a alocação definitiva.
- Na retirada, atribuir veículo disponível e bloquear concorrência com outras retiradas.
- Intervalos consecutivos podem compartilhar a fronteira de horário; intervalos sobrepostos não podem ocupar o mesmo veículo.
- Bloqueios de manutenção retiram o veículo da disponibilidade.
- Saída registra horário, odômetro, combustível/carga e vistoria.
- Devolução exige odômetro não inferior ao da saída.
- Substituição encerra um vínculo e abre outro atomicamente, preservando multas, vistoria e responsabilidade por período.
- Contrato encerra quando todos os veículos forem devolvidos.
- Pendência de pagamento não impede registrar a devolução física.

### Preços e renovação

Defaults comerciais do Lite:

- Locação: mínimo de uma diária; períodos iniciados de 24 horas arredondam para cima, sem tolerância automática.
- Contrato: semana de sete dias; mês e ano por calendário, preservando a data de origem como âncora.
- Em datas inexistentes, usar o último dia do mês de destino, sem perder a âncora original.
- Devolução antecipada de contrato mantém o período iniciado integral; redução exige ajuste explícito, motivo e permissão.
- Planos de quilometragem: livre ou franquia por período, com excedente apurado na devolução/substituição.
- Adicionais simples com descrição, quantidade e preço. Sem motor de taxas condicionais.
- Toda prévia financeira vem do servidor, usando o mesmo cálculo da confirmação.

A renovação automática:

1. É habilitada explicitamente no contrato.
2. Gera um ciclo e sua fatura para os veículos ainda ativos.
3. Preserva as datas originais e registra o próximo vencimento de renovação separadamente.
4. Usa uma chave única por contrato/ciclo para impedir duplicidade.
5. Executa por CRON, com locks e recuperação de ciclos pendentes após indisponibilidade.
6. Só avança o ciclo quando a fatura estiver persistida.
7. Enfileira cobrança por e-mail após o commit.
8. Continua com parcelas anteriores em aberto, destacando a inadimplência.
9. Para com o encerramento ou a desativação da recorrência.

Renovar gera uma cobrança; não significa debitar automaticamente um cartão.

### Financeiro, Stripe Connect e caução

Fatura inicial criada quando o aluguel é confirmado. Rascunhos não geram cobrança. Contratos faturam inicialmente o primeiro período; renovações geram os períodos seguintes.

Faturas possuem itens, vencimento, moeda, unidade e vínculo de origem. Pagamentos parciais e estornos são movimentos separados. Não apagar pagamentos nem reescrever silenciosamente uma fatura já paga.

**Stripe Connect:**

- Plataforma vinculada à empresa brasileira responsável pelo Lite.
- Locadoras conectam suas próprias contas Stripe.
- Cobranças diretas na conta conectada, com Checkout hospedado.
- Sem comissão da plataforma sobre pagamentos na primeira versão.
- Uma locadora pode conectar contas de suas diferentes entidades legais; cada unidade escolhe uma conexão autorizada.
- Conta e tenant são resolvidos pelo backend, nunca escolhidos livremente pela IA.
- Configurar conexão de contas existentes pelo fluxo autorizado da Stripe e validar o vínculo durante o retorno.
- Antes de produção, comprovar a elegibilidade da plataforma brasileira para os países pretendidos. Se um país não for habilitado, bloquear sua ativação financeira e registrar o impedimento; não trocar para chaves de API sem nova decisão.

A cobrança direta mantém o pagamento na conta conectada. Os meios disponíveis dependem da conta, moeda e elegibilidade. Referência: [Stripe Connect](https://docs.stripe.com/connect/direct-charges?platform=web&ui=stripe-hosted).

```text
[Sua empresa no Brasil]
          |
          v
[7Carros Lite + Stripe Connect]
          |
          +---- [Conta Stripe da Locadora A]
          |
          +---- [Conta Stripe da Locadora B]

[Cliente da Locadora A]
          |
          | Paga no Checkout hospedado da Stripe
          v
[Saldo Stripe da Locadora A]
          |
          v
[Conta bancária da Locadora A]

[Stripe] -- webhook validado --> [Lite atualiza a fatura]
```

Criar Checkout individual por tentativa de pagamento da fatura. O link enviado por e-mail aponta para uma página segura do Lite, que valida saldo e encaminha para a Stripe.

Webhooks devem validar assinatura, conta conectada, valor, moeda e vínculo. Eventos duplicados ou fora de ordem não podem duplicar baixas. A página de sucesso não comprova pagamento. Usar idempotência e reconciliação para resultados incertos. Referência: [Webhooks Stripe](https://docs.stripe.com/webhooks).

**Caução:**

- Controle separado do aluguel e das receitas.
- Modalidades: recebimento manual ou pré-autorização no cartão.
- Pré-autorização usa pagamento separado com captura manual.
- Registrar validade retornada pela Stripe e avisar antes da expiração.
- Captura, liberação e devolução exigem permissão, confirmação e auditoria.
- Autorização expirada exige nova participação do cliente; não presumir cobertura durante todo um contrato.
- Captura para quitar débito deve apontar para a cobrança correspondente, sem criar receita em duplicidade.

A Stripe informa a validade da autorização; ela varia conforme o pagamento. Referência: [Pré-autorização e captura](https://docs.stripe.com/payments/place-a-hold-on-a-payment-method).

### Assinaturas, vistorias, multas e e-mails

**Assinatura:** link aleatório, expirável e revogável, limitado a uma versão do documento. Exibir o documento completo, colher aceite e assinatura, registrar evidências e hash. Alterações posteriores geram nova versão e nova assinatura. A IA pode preparar o envio, mas nunca assinar pelo cliente.

**Vistoria:** checklist simples por veículo, fotos, observações, odômetro e combustível/carga na saída e na devolução. Preservar evidências originais. A IA pode preencher observações ditadas; não declarar inspeção concluída nem inventar evidências.

**Multas:** cadastro manual com veículo, data, identificação, valor, vencimento e anexo. Sugerir responsável pelo histórico, exigindo confirmação. Cobrança ao cliente é uma ação explícita e não se confunde com pagamento da multa ao órgão.

**E-mails:** envio na emissão e renovação, lembrete no vencimento e três dias depois, desde que ainda exista saldo. Configuração habilitada explicitamente por unidade. Usar templates traduzidos, fila, deduplicação e registro de entrega/falha. Reenvios solicitados pela IA exigem confirmação.

### Exclusões e histórico

No Lite, exclusão operacional será arquivamento lógico. Cadastros vinculados, contratos assinados, faturas e pagamentos devem manter rastreabilidade.

Cancelamentos e estornos são operações próprias. Expurgo definitivo não fica disponível à IA e não integra o CRUD comum.

Essa é uma decisão deliberada do novo produto: o sistema atual possui fluxos documentados de exclusão definitiva que não serão reproduzidos.

## 4. IA com controle de execução pelo servidor

### Integração e capacidades

Adotar inicialmente OpenAI, atrás de uma interface de provedor. Modelo e limites ficam em configuração do servidor.

Usar Responses API com ferramentas de schemas estritos, também validados pelo backend. Schemas controlam formato; não substituem autorização. Referência: [OpenAI: definição de ferramentas](https://developers.openai.com/api/docs/guides/migrate-to-responses#5-update-function-definitions-and-outputs).

A IA poderá:

- Consultar disponibilidade, cadastros, aluguéis e financeiro autorizado.
- Preparar cadastros, alterações, reservas, contratos e devoluções.
- Preparar faturas, cobranças, multas e operações de caução.
- Abrir telas preenchidas e explicar resultados calculados pelo sistema.
- Responder indicadores usando consultas determinísticas da API.

Áudio será gravado por botão, transcrito e apresentado no histórico. Informações incertas devem ser esclarecidas. Resposta inicial em texto e cartões de ação; conversa contínua por voz fica fora desta versão.

### Fluxo obrigatório de confirmação

```text
Usuário pede uma operação
          |
          v
IA consulta ferramentas autorizadas
          |
          v
Servidor prepara uma proposta
          |
          v
Tela mostra dados, alterações e efeitos
          |
          +---- Cancelar -> nada é executado
          |
          +---- Editar -> gera nova proposta
          |
          +---- Confirmar
                    |
                    v
          Servidor revalida e executa
                    |
                    v
          Resultado real + auditoria
```

Toda gravação de negócio e todo envio externo solicitado pela IA exige confirmação. Gravar conversa, proposta e auditoria são operações técnicas e não exigem confirmação individual.

**Contrato de proposta:**

- Identificador, tenant, usuário e unidades.
- Operação permitida e argumentos normalizados.
- Registros envolvidos e versões consultadas.
- Diferenças, valores, moeda e efeitos externos.
- Validade de dez minutos e estado de execução.

O cartão de confirmação é renderizado a partir da proposta persistida pelo servidor. A IA não fornece HTML executável nem decide o conteúdo final autorizado.

**Confirmação:**

- Endpoint separado, chamado pela interface autenticada.
- Não disponibilizado como ferramenta da IA.
- “Sim” escrito ou falado no chat não executa a operação.
- Aprovação vinculada ao usuário, proposta e conteúdo exatos.
- Revalidar permissões, saldo, disponibilidade e versões.
- Alteração concorrente invalida a proposta e exige nova revisão.
- Aprovação consumida atomicamente; reenvio devolve o resultado existente.
- Operações externas podem ficar pendentes; nunca apresentar sucesso antes da confirmação real.

Endpoints centrais:

- `POST /api/v1/ai/conversations`
- `POST /api/v1/ai/conversations/{id}/messages`
- `POST /api/v1/ai/transcriptions`
- `GET /api/v1/action-proposals/{id}`
- `POST /api/v1/action-proposals/{id}/confirm`
- `POST /api/v1/action-proposals/{id}/cancel`

APIs de negócio usam permissões e serviços compartilhados. Não permitir que uma ferramenta da IA contorne o fluxo chamando endpoints de gravação diretamente.

### Automação e proteção

- CRON e webhooks executam regras previamente autorizadas, sem participação decisória da IA.
- Ativar recorrência ou notificações pela IA exige confirmação.
- Tratar mensagens, anexos e dados cadastrados como conteúdo não confiável.
- Instruções encontradas em documentos não podem alterar permissões ou executar ferramentas.
- Enviar ao provedor apenas os dados necessários; excluir credenciais e dados de cartão.
- Conversas são privadas por usuário e tenant; mudança de tenant inicia novo contexto.
- Aplicar limites por usuário/tenant, timeout, limite de chamadas e orçamento configurável.
- Indisponibilidade da IA não bloqueia a operação pelas telas.
- Cada função do produto deve ter uma matriz indicando consulta, proposta de alteração ou intervenção humana obrigatória.

## 5. Entrega, testes e referências

### Ordem de implementação

1. **Fundação:** projeto independente, autenticação, tenants, unidades, permissões, helpers e auditoria.
2. **Operação visual:** clientes, veículos, disponibilidade e aluguéis.
3. **Financeiro:** faturas, ciclos, CRON e consistência transacional.
4. **Integrações:** Connect, Checkout, caução, webhooks e e-mails.
5. **Documentos:** PDF, assinatura, vistorias e multas.
6. **IA:** consultas, propostas e confirmação reutilizando os serviços prontos.
7. **Homologação:** cenários completos nas três plataformas e validação dos países.
8. **Piloto:** locadoras de teste, monitoramento e liberação gradual.

Entregar migrations, dados fictícios, testes automatizados, OpenAPI, exemplos de ambiente sem segredos, manual de implantação, backup/restauração e matriz de recursos da IA.

Deploy independente do sistema atual, com aplicação, workers e agendador monitorados. Usar outbox transacional para encaminhar efeitos externos após commit. Falhas externas devem admitir reconciliação e retentativa sem duplicar cobranças.

### Critérios de aceite

| Área | Cenários obrigatórios |
|---|---|
| Tenants | Usuário A não acessa registros, arquivos, propostas ou resultados do tenant B, mesmo conhecendo seus IDs |
| Unidades | Permissão removida bloqueia consultas e confirmações já preparadas |
| IA | Comando malicioso em mensagem ou documento não contorna autorização |
| Aprovação | Mensagem “confirmo”, replay, proposta expirada ou payload alterado não executam gravação |
| Concorrência | Duas retiradas simultâneas do mesmo veículo produzem somente uma alocação |
| Renovação | CRON duplicado, interrupção e retentativa geram uma única fatura por ciclo |
| Períodos | Semana, fim de mês, ano bissexto e mudanças de horário preservam as regras definidas |
| Financeiro | Pagamento parcial, cancelamento, estorno e devolução parcial não duplicam valores |
| Stripe | Pagamento aprovado, pendente, recusado, evento duplicado, evento fora de ordem e conta incorreta |
| Caução | Autorização, captura parcial, liberação, expiração e falha de comunicação |
| Assinatura | Link expirado/revogado e documento alterado não reutilizam assinatura anterior |
| Moedas | BRL/USD/EUR formatados corretamente, sem arredondamento binário ou soma entre moedas |
| Plataformas | Jornada completa em Android, iOS e navegador |
| Resiliência | Falha da IA, Stripe ou e-mail preserva estado verificável e recuperação segura |

Testes de e-mail devem usar transporte simulado por padrão. Qualquer teste com envio real utiliza exclusivamente o tenant de chave `1111111111111`.

Antes da produção, exigir restauração de backup testada, alertas para falhas de renovação/webhook e comprovação da configuração Connect para cada país habilitado.

### Premissas e documentação consultada

O sistema atual é referência de domínio. O Lite terá código, banco, autenticação e implantação próprios. Os padrões específicos de iframe, QueryBuilder e executor de migrations do legado não serão transplantados para a nova arquitetura.

Os defaults comerciais explicitados neste plano são regras do Lite, incluindo cobrança por períodos, arquivamento lógico e simplificação das taxas; não representam reprodução integral do sistema atual.

Foram consultados o **AGENTS.md fornecido** e trechos relevantes de:

- `docs/overview.md`, `architecture.md`, `querybuilder.md`, `multi-tenancy.md`, `database.md` e `migrations.md`.
- `docs/security.md`, `roles.md`, `filial-helper.md` e `logs.md`.
- `docs/contratos.md`, `locacoes.md`, `financeiro.md` e `taxaseservicos.md`.
- `docs/gateways.md`, `assinaturas.md`, `checklists.md`, `multas.md` e `messaging.md`.
- `docs/pdf.md`, `modals.md`, `helpers.md`, `date.md` e `currency.md`.
- `docs/relatorios.md` e `relatorios-dev.md`.

Também foram consultadas as documentações oficiais citadas de Expo, Laravel, Stripe e OpenAI, com uso da skill OpenAI Docs para a integração da IA. As capacidades de provedores e versões devem ser conferidas novamente antes da implantação.
