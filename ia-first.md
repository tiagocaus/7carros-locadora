# 7Carros Locadora IA First

## Visao Geral

A proposta nao e transformar o 7Carros em "um sistema com IA", mas sim em uma operacao de locadora comandada por IA.

As telas tradicionais continuam existindo, mas deixam de ser o centro da experiencia. O centro passa a ser uma camada inteligente capaz de conversar, orientar, executar tarefas e explicar o negocio em tempo real.

IA First nao significa deixar a IA decidir tudo. Contratos, cobrancas, caucao, disponibilidade, financeiro, multas e multi-tenancy precisam continuar deterministicos, auditaveis e seguros. A IA deve funcionar como camada de interacao, automacao e analise sobre um core transacional robusto.

## Arquitetura Conceitual

O sistema seria dividido em tres camadas principais.

### 1. Core Operacional

Camada deterministica do sistema, responsavel pelas regras reais do negocio:

- Clientes
- Veiculos
- Reservas
- Locacoes
- Contratos
- Financeiro
- Multas
- Manutencao
- Checklists
- Pagamentos
- Comissoes de investidores/proprietarios

Essa camada continua seguindo regras tradicionais de software: banco de dados, permissoes, logs, auditoria, isolamento por tenant, workflows claros e validacoes consistentes.

### 2. Camada de IA

Camada de interacao e inteligencia sobre o core:

- Chat interno para funcionarios
- Chat/WhatsApp para clientes
- Assistente do proprietario/gestor
- Assistente financeiro
- Assistente de frota/manutencao
- Assistente para investidores/proprietarios de veiculos
- Busca semantica em contratos, documentos, historico, mensagens e ocorrencias

Essa camada entende linguagem natural, resume informacoes, aponta riscos, sugere proximas acoes e ajuda diferentes perfis de usuario a operar o sistema com menos friccao.

### 3. Camada de Acoes

A IA nao apenas responde. Ela pode executar acoes por meio de ferramentas internas do sistema, sempre respeitando permissoes e regras do core.

Exemplos:

- Criar pre-reserva
- Simular orcamento
- Solicitar documentos
- Gerar contrato
- Enviar link de pagamento
- Agendar retirada
- Abrir checklist
- Sugerir cobranca extra
- Apontar inadimplencia
- Alertar veiculo parado
- Explicar lucro ou prejuizo de um veiculo

Acoes sensiveis devem exigir confirmacao humana, principalmente quando envolvem dinheiro, contratos, caucao, cancelamentos, alteracoes cadastrais relevantes ou liberacao de veiculos.

## Experiencia do Cliente

O cliente provavelmente nao precisaria entrar no sistema na maior parte do tempo.

Ele poderia falar por WhatsApp, site ou aplicativo:

> Preciso de um carro automatico de sexta a segunda.

A IA responderia com opcoes reais da locadora:

> Tenho um hatch automatico por R$ X e um SUV por R$ Y. Quer retirar em qual filial?

Depois disso, a IA poderia:

- Coletar documentos
- Validar dados basicos
- Consultar disponibilidade
- Gerar orcamento
- Enviar link de pagamento ou caucao
- Enviar contrato para assinatura
- Abrir checklist digital no momento da retirada
- Acompanhar devolucao
- Responder duvidas sobre cobrancas

Para o cliente, a experiencia seria parecida com falar com um atendente 24 horas.

## Experiencia do Funcionario

O funcionario deixaria de navegar por muitas telas para executar tarefas simples.

Ele poderia escrever:

> Cria uma reserva para Joao Silva, retirada amanha as 9h, devolucao segunda, grupo economico, pagamento PIX.

A IA preencheria os dados, validaria inconsistencias e perguntaria apenas o necessario:

> Encontrei dois clientes chamados Joao Silva. Qual deles?

Ou:

> Esse cliente esta com uma multa pendente e CNH vencida no cadastro. Deseja continuar mesmo assim?

Na rotina diaria, o funcionario teria uma central inteligente mostrando:

- Reservas que precisam de acao
- Veiculos com devolucao atrasada
- Contratos sem assinatura
- Caucoes vencendo
- Pagamentos pendentes
- Veiculos indisponiveis sem motivo claro
- Clientes com risco de inadimplencia
- Checklists com avarias novas

A IA funcionaria como um supervisor operacional, reduzindo esquecimento, retrabalho e erro manual.

## Experiencia do Proprietario ou Gestor da Locadora

O proprietario poderia consultar o negocio em linguagem natural:

> Quanto lucrei este mes?

Tambem poderia perguntar:

- Quais veiculos estao dando prejuizo?
- Qual filial esta performando melhor?
- Tenho carro parado demais?
- Qual cliente mais atrasa pagamento?
- Vale a pena comprar mais SUVs?
- Por que meu caixa esta apertado se o faturamento subiu?

A IA responderia com base em dados reais:

> Voce faturou mais, mas aumentou o prazo medio de recebimento. R$ 42.300 estao em aberto, principalmente em contratos mensais. Alem disso, 3 veiculos tiveram manutencao acima da media.

Esse tipo de inteligencia e mais dificil de copiar do que telas CRUD tradicionais, porque depende de contexto operacional, historico e regras especificas do negocio de locadora.

## Experiencia de Investidores e Proprietarios de Veiculos

Caso a locadora trabalhe com veiculos de terceiros ou investidores, eles poderiam ter uma experiencia propria.

O investidor poderia perguntar:

> Quanto meu Corolla rendeu este mes?

A IA responderia:

> Receita bruta: R$ 4.800. Manutencao: R$ 620. Comissao da locadora: R$ 960. Repasse previsto: R$ 3.220.

Tambem poderia explicar:

- Dias locado
- Dias parado
- Manutencoes
- Multas
- Avarias
- Previsao de repasse
- Rentabilidade acumulada
- Comparacao com outros veiculos da mesma categoria

Isso pode virar um diferencial importante para captar frota de terceiros.

## Principios de Seguranca e Controle

A IA nao deve consultar diretamente o banco e inventar respostas. Ela deve chamar ferramentas internas do sistema, com regras claras e respostas estruturadas.

Exemplos de ferramentas internas:

- `buscarDisponibilidade`
- `simularLocacao`
- `criarReserva`
- `gerarContrato`
- `consultarFinanceiro`
- `listarVeiculosParados`
- `calcularComissaoInvestidor`

A IA conversa, interpreta e orienta. O sistema calcula, decide, valida e registra.

A IA nao deve:

- Fechar financeiro sem regra
- Alterar contrato sem historico
- Liberar caucao sem permissao
- Ignorar isolamento multi-tenant
- Tomar decisao juridica
- Calcular valores fora do motor oficial do sistema

## Governanca de IA em SaaS Multi-Tenant

Em um SaaS, a principal regra e que a IA nunca pode operar como um usuario global do sistema. Ela deve operar sempre dentro de um contexto fechado, com tenant, usuario, permissao e canal bem definidos.

Toda chamada da IA deve carregar obrigatoriamente:

- `chave` do tenant atual
- Usuario autenticado
- Perfil e permissoes do usuario
- Filial ou escopo operacional permitido
- Canal de origem, como sistema interno, WhatsApp, app ou portal
- Nivel de risco da acao solicitada

A `chave` nunca deve vir do prompt do usuario. Ela deve vir apenas da sessao, autenticacao, token ou contexto seguro definido pelo sistema. Se o usuario escrever algo como "acesse a chave X" ou "veja dados de outra locadora", a IA deve recusar e registrar o evento como tentativa suspeita.

### Modelo sem Acesso Direto ao Banco

A IA nao deve executar SQL, consultar tabelas diretamente nem montar queries livres. Ela deve chamar ferramentas internas do sistema, como Services, Models ou endpoints controlados, que ja aplicam:

- QueryBuilder com filtro automatico por `chave`
- RBAC e permissoes do usuario
- Filtros de filial
- Validacoes de negocio
- Logs de auditoria
- Logs de seguranca

O modelo de IA nao e a fonte de verdade. O sistema e a fonte de verdade.

A IA interpreta o pedido, mas quem calcula, valida, grava e decide o resultado final sao as regras internas do sistema.

### Permissoes e Escopo

A IA so pode executar aquilo que o usuario logado poderia executar manualmente.

Exemplos:

- Um atendente pode criar uma reserva, mas nao liberar uma caucao sem permissao.
- Um funcionario de uma filial nao pode consultar dados financeiros de outra filial, se o sistema bloquear esse acesso.
- Um cliente pode consultar a propria reserva, mas nao pode consultar reservas de outros clientes.
- Um investidor pode ver dados dos proprios veiculos, mas nao a operacao completa da locadora.

Isso impede que a IA vire um atalho para burlar permissao.

### Modo Somente Leitura por Padrao

Por padrao, a IA deve comecar em modo de leitura:

- Consultar informacoes
- Resumir dados
- Explicar situacoes
- Sugerir proximas acoes
- Apontar riscos
- Preparar rascunhos

Acoes que alteram dados devem usar ferramentas especificas, com parametros estruturados e regras de permissao.

### Confirmacao Humana para Acoes Sensiveis

Acoes sensiveis devem exigir confirmacao humana antes da execucao.

Exemplos:

- Cancelar locacao ou contrato
- Excluir registros
- Alterar valores de contrato
- Conceder desconto relevante
- Liberar ou capturar caucao
- Fechar financeiro
- Estornar pagamento
- Enviar comunicacao juridica
- Alterar dados cadastrais sensiveis
- Liberar veiculo bloqueado por risco

Nesses casos, a IA deve apresentar um resumo claro da acao, impactos e dados envolvidos. A execucao so acontece depois de confirmacao explicita de um usuario com permissao.

### Auditoria Completa

Toda acao executada pela IA deve gerar log.

O log deve registrar:

- Quem pediu
- Tenant (`chave`)
- Canal de origem
- Prompt ou intencao resumida
- Ferramenta chamada
- Parametros estruturados usados
- Resultado da acao
- Horario
- IP ou identificador do canal
- Se houve confirmacao humana

Para auditoria, deve ficar claro que a acao foi feita via IA, mas solicitada por um usuario real ou por uma automacao autorizada.

### Limites de Dano

Mesmo com permissao, a IA deve ter limites operacionais.

Exemplos:

- Limite maximo de desconto sem aprovacao
- Limite de valor para estorno, caucao ou lancamento financeiro
- Limite de quantidade de registros alterados por acao
- Limite de mensagens enviadas por periodo
- Bloqueio de exclusoes em massa
- Bloqueio de alteracoes financeiras fora de periodo permitido

Quando o pedido ultrapassar esses limites, a IA deve transformar a acao em sugestao ou solicitar aprovacao de um perfil superior.

### Lista Permitida de Ferramentas

A IA deve operar apenas com uma lista permitida de ferramentas.

Exemplos de ferramentas seguras:

- `buscarDisponibilidade`
- `simularLocacao`
- `criarPreReserva`
- `gerarOrcamento`
- `gerarContrato`
- `consultarFinanceiro`
- `listarVeiculosParados`
- `calcularComissaoInvestidor`
- `enviarLinkPagamento`
- `abrirChecklist`

Operacoes fora dessa lista nao devem ser improvisadas pela IA. Se uma capacidade nova for necessaria, ela deve ser implementada como ferramenta interna, com permissao, validacao, auditoria e testes.

### Minimizacao de Dados

A IA deve expor apenas os dados necessarios para responder ou executar a tarefa.

Exemplos:

- Mascarar CPF, CNPJ, documentos e dados bancarios quando o dado completo nao for necessario.
- Nao exibir cartoes, tokens, credenciais ou payloads sensiveis.
- Nao enviar dados financeiros detalhados para canais externos sem permissao.
- Responder ao cliente apenas sobre as proprias reservas, contratos e cobrancas.

Isso reduz o impacto caso uma conversa seja aberta em canal menos seguro, como WhatsApp.

### Deteccao de Risco

O sistema deve registrar e bloquear comportamentos suspeitos.

Exemplos:

- Pedido para acessar outro tenant
- Pedido para ignorar regras de seguranca
- Tentativa de extrair muitos dados
- Prompt pedindo SQL direto
- Pedido para revelar dados sensiveis sem permissao
- Repetidas tentativas negadas
- Acao incomum para o perfil do usuario

Esses eventos devem alimentar logs de seguranca e podem acionar throttling, bloqueio temporario ou revisao manual.

### Fallback Seguro

Quando houver duvida, conflito de dados ou baixa confianca, a IA nao deve executar a acao.

Ela deve responder de forma segura:

- Explicar o que esta inconsistente
- Pedir revisao humana
- Sugerir o proximo passo
- Manter os dados sem alteracao

Em sistema de locadora, errar por nao executar e melhor do que liberar veiculo indevidamente, vazar dados, alterar financeiro ou prejudicar juridicamente a empresa.

## Valor do Produto

Desenvolver sistema ainda vale a pena, mas o valor mudou.

Antes, o valor principal era ter telas para cadastrar cliente, veiculo, reserva e contrato. Hoje, isso tende a virar commodity.

O valor passa a estar em:

- Conhecer profundamente o negocio de locadora
- Automatizar decisoes operacionais
- Integrar WhatsApp, pagamento, assinatura, multas, checklist e financeiro
- Transformar dados em acao
- Reduzir trabalho humano repetitivo
- Dar visao gerencial que o dono nao consegue montar sozinho
- Criar um historico operacional que melhora a IA com o tempo

## Posicionamento Sugerido

Se o 7Carros fosse refeito com conceito IA First, o posicionamento poderia ser:

> A central inteligente que atende clientes, opera reservas, controla frota, protege o financeiro e explica o negocio da locadora em tempo real.

Esse caminho e mais defensavel do que apenas criar mais um sistema de locadora com um chat de IA acoplado.
