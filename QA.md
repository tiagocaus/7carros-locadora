# Base de Perguntas e Respostas
Documento gerado a partir da analise de mensagens reais de clientes e da validacao das informacoes disponiveis no sistema.

## Indice
- [Financeiro e pagamentos](#financeiro-e-pagamentos)
- [Contratos e locacoes](#contratos-e-locacoes)
- [Multas](#multas)
- [Checklist, aplicativo e assinatura](#checklist-aplicativo-e-assinatura)
- [Permissoes e acesso](#permissoes-e-acesso)
- [Relatorios](#relatorios)
- [Website e planos](#website-e-planos)
- [Perguntas complementares](#perguntas-complementares)

---

## Financeiro e pagamentos

### 1. Como configurar juros e multa em boletos ou outras formas de pagamento?
Acesse o menu: `Empresa > Formas de pagamento`

Na tela de formas de pagamento, abra o cadastro da forma desejada e localize os campos `Multa` e `Juros por dia`. Informe os percentuais desejados e clique em `Salvar`.

Esses campos fazem parte da configuracao da forma de pagamento. Quando um lancamento financeiro usa essa forma, o sistema tem informacao para aplicar multa e juros conforme as regras financeiras configuradas.

Veja: https://www.7carros.com.br/videos/formas-de-pagamento

---

### 2. Como configurar um gateway de pagamento, como Asaas, para gerar PIX, boleto ou cartao?
Acesse o menu: `Empresa > Gateways de pagamento`

Na tela de gateways, cadastre ou edite o gateway desejado, selecione o gateway no campo `Gateway`, configure as credenciais e marque os metodos em `Metodos de Pagamento Habilitados`.

Depois, acesse o menu: `Empresa > Formas de pagamento`

Na forma de pagamento que sera usada nas cobrancas, vincule o gateway correspondente em `Gateways de Pagamento` e clique em `Salvar`. Sem esse vinculo, a forma de pagamento pode aparecer no sistema, mas nao processa pagamento online automaticamente.

Veja: https://www.7carros.com.br/videos/gateways-de-pagamento

Veja: https://www.7carros.com.br/videos/formas-de-pagamento

---

### 3. Por que o link de pagamento, PIX ou boleto nao foi gerado?
Para gerar pagamento online, a receita precisa estar vinculada a uma forma de pagamento que tenha gateway ativo e configurado.

Verifique:
1. Se o gateway esta cadastrado em `Empresa > Gateways de pagamento`.
2. Se os metodos de pagamento, como PIX, boleto ou cartao, estao habilitados no gateway.
3. Se a forma de pagamento usada na fatura esta vinculada ao gateway em `Empresa > Formas de pagamento`.
4. Se a fatura esta pendente e possui cliente vinculado.

O sistema tambem reaproveita o mesmo link publico de pagamento da fatura pendente e sincroniza valor, vencimento, cliente e status quando esses dados mudam.

Veja: https://www.7carros.com.br/videos/gateways-de-pagamento

---

### 4. O sistema envia automaticamente as faturas que vao vencer?
Sim. O sistema possui rotina automatica diaria de cobranca, executada as 08:00.

A rotina envia:
1. Lembrete de faturas que vencem no dia seguinte, usando o template `Lembrete de Pagamento`.
2. Aviso de faturas vencidas, usando o template `Aviso de Atraso`, no maximo uma vez a cada 7 dias por fatura.

Os canais sao email, WhatsApp e SMS, conforme o cliente tenha email/telefone cadastrado e a filial tenha WhatsApp/SMS configurados. Antes do envio, o sistema cria ou reutiliza o link publico de pagamento da fatura.

Nao existe tela para ligar ou desligar essa rotina por empresa. Para personalizar os textos, acesse o menu: `Sistema > Templates de Mensagem`.

---

### 5. Como responder quando o cliente nao quer receber emails automaticos de cobranca?
Entendemos a solicitacao.

O recomendado e dar baixa nas faturas assim que o pagamento for identificado. Se a fatura continua pendente, o sistema entende que ainda existe cobranca em aberto e pode enviar lembretes ou avisos de atraso.

Para dar baixa, acesse o menu: `Financeiro > Lancamentos`. Abra a fatura em `Editar`, altere `Lancamento Pago` para pago, informe a data do pagamento quando necessario e clique em `Salvar`.

Para interromper os emails automaticos de cobranca, acesse:

`Sistema > Configuracoes`, na secao `Notificacoes`, desative a opcao `E-mail` e clique em `Salvar`. OBS, isso interrompe todos os envios de emails do sistema.

---

### 6. Como gerar ou imprimir a fatura de um lancamento financeiro?
Acesse o menu: `Financeiro > Lancamentos`

Na tela de lancamentos, localize a fatura desejada e use a acao de impressao. A tela de impressao possui o botao `Gerar PDF` e opcoes de envio por email, WhatsApp ou SMS quando aplicavel.

Observacao: o envio por e-mail, WhatsApp ou SMS foi confirmado para receitas com cliente vinculado. Despesas podem ser impressas em PDF, mas nao sao enviadas por esse fluxo.

---

### 7. Como registrar um recebimento parcial com duas formas de pagamento diferentes?
O sistema permite recebimento parcial, mas nao permite duas formas de pagamento na mesma fatura.

Acesse o menu: `Financeiro > Lancamentos`

Abra o lancamento pela acao `Editar`. No campo `Lancamento Pago`, selecione `Pago parcial`. A tela exibe a secao `Pagamento parcial` com os campos `Valor original da fatura`, `Valor recebido`, `Diferenca a criar`, `Vencimento da diferenca` e `Data do Pagamento`. Clique em `Criar diferenca`.

O sistema marca a fatura original como paga pelo valor recebido e cria uma nova fatura pendente com a diferenca, herdando a mesma forma de pagamento. Para receber o restante em outra forma (ex: parte cartao, parte dinheiro), edite a fatura de diferenca criada e altere a forma de pagamento nela antes de dar baixa.

---

### 8. Como gerar promissorias?
Acesse o menu: `Financeiro > Promissorias`

Na tela de promissorias, use o cadastro de promissoria para criar o documento e depois utilize a acao de impressao quando necessario.

Foi confirmado tambem na base de conhecimento existente que promissorias ficam no menu financeiro.

---

## Contratos e locacoes

### 9. Qual e a diferenca entre Contrato e Locacao no sistema?
`Contratos` sao usados para contratos de locacao com ciclo completo, multiplos veiculos, autorrenovacao, substituicao, devolucao, assinatura digital e impressao de documentos.

`Locacoes` sao usadas para locacoes de curta duracao, com fluxo de status `Reserva`, `Aberto` e `Fechado`, incluindo saida, devolucao, taxas, parcelas, assinatura e impressao.

Acesse o menu: `Contrato/Locacoes > Novo contrato` para criar contrato.

Acesse o menu: `Contrato/Locacoes > Nova Locacao` para criar locacao ou reserva.

Veja: https://www.7carros.com.br/videos/reservas-e-locacoes

Veja: https://www.7carros.com.br/videos/criando-um-contrato-de-locacao

---

### 10. Preciso escolher um veiculo especifico ao criar uma reserva?
Nao obrigatoriamente.

Em locacoes com status de reserva, o sistema permite reservar por grupo ou categoria. O veiculo especifico pode ser informado como preferencia operacional, mas so passa a ser obrigatorio ao abrir a locacao ou registrar a saida.

Acesse o menu: `Contrato/Locacoes > Nova Locacao`

Na tela de locacao, use o status de reserva, informe o grupo/categoria e preencha os demais dados necessarios. Ao registrar a saida, selecione o veiculo que sera efetivamente entregue ao cliente.

Veja: https://www.7carros.com.br/videos/reservas-e-locacoes

---

### 11. Como dar baixa ou fechar uma locacao?
Acesse o menu: `Contrato/Locacoes > Locacoes/Reservas`

Na tela de locacoes, abra a locacao desejada e registre a devolucao alterando o fluxo de `Aberto` para `Fechado`. A devolucao exige `data_chegada`, `odometro_entrada` e `combustivel_entrada`.

O sistema calcula automaticamente odometro usado, km excedente no plano KMC e combustivel usado quando houver diferenca. Antes de fechar, tambem exige parcelas financeiras lancadas com total igual ao total final da locacao.

---

### 12. Como cobrar km excedente na locacao?
O km excedente depende do plano `KMC` ou `Km Controlado`.

Acesse o menu: `Contrato/Locacoes > Nova Locacao`

Na aba de veiculo da locacao, selecione o plano de km controlado, informe a franquia de km e o valor por km excedente. No fechamento da locacao, preencha o odometro de entrada. O sistema calcula o excedente com base na diferenca entre odometro de saida e entrada e na franquia definida.

---

### 13. Como lancar despesas extras, como lavagem, estacionamento ou taxa adicional?
Acesse o menu: `Empresa > Taxas e servicos`

Na tela de taxas e servicos, cadastre a taxa com `Nome da taxa`, `Base de calculo`, `Tipo`, `Valor`, onde sera usada e se deve ser aplicada automaticamente. Clique em `Salvar`.

Depois, ao criar ou editar contrato/locacao, use a aba `Taxas e Servicos` para incluir a taxa desejada. O sistema preserva a regra da taxa usada naquele contrato ou locacao, mesmo que o cadastro da taxa seja alterado depois.

Veja: https://www.7carros.com.br/videos/taxas-e-servicos

---

### 14. A reserva imprime fatura ou voucher?
Em locacoes com status `Reserva`, a impressao deve apresentar o documento como `Voucher`, nao como fatura.

Acesse o menu: `Contrato/Locacoes > Locacoes/Reservas`

Na tela de locacoes, abra a reserva e use a acao de impressao. A tela de impressao mostra as opcoes disponiveis e, para reserva confirmada, os rotulos que usam fatura passam a usar voucher.

---

### 15. Como imprimir fatura, documento, checklist ou recibo de contrato/locacao?
Acesse o menu: `Contrato/Locacoes > Contratos`

Ou acesse o menu: `Contrato/Locacoes > Locacoes/Reservas`

Na listagem, abra a acao de impressao do registro desejado. A tela de impressao permite gerar PDF e selecionar tipos como fatura, documento, checklist, recibo e combinacoes, conforme disponibilidade do registro. Quando a opcao exigir documento ou checklist, selecione o modelo antes de clicar em `Gerar PDF`.

---

### 16. Posso desfazer uma devolucao de veiculo depois de fechar?
Nao. O sistema nao possui acao para desfazer devolucao nem para reabrir locacao ou contrato fechado.

Nas locacoes, as unicas transicoes de status permitidas sao `Reserva > Aberto` e `Aberto > Fechado`; quando a locacao esta `Fechado`, o campo de status fica bloqueado. Nos contratos, quando o ultimo veiculo e devolvido, o contrato e finalizado automaticamente e nao ha fluxo de reversao.

Se uma devolucao foi registrada por engano, o caso precisa ser tratado pelo suporte/equipe tecnica.

---

## Multas

### 17. Como cadastrar uma multa manualmente?
Acesse o menu: `Empresa > Central de Multas`

Na Central de Multas, use `Adicionar Multa`. A tela possui a secao `Identificar Responsavel`, onde e possivel informar dados da multa e buscar o responsavel pelo veiculo na data/hora da infracao. Depois de preencher os dados obrigatorios, clique em `Salvar`.

Ao cadastrar multa manualmente, o sistema cria um lancamento financeiro vinculado. Se o pagador for `cliente`, gera receita; se for `empresa`, gera despesa.

---

### 18. Como o sistema identifica o responsavel por uma multa?
O sistema busca quem estava com o veiculo na data e hora da infracao, considerando contratos e locacoes.

A regra confirmada e:
1. O vinculo cobre a multa quando a saida do veiculo ocorreu antes ou na data/hora da infracao.
2. Se a entrada/devolucao ainda nao foi registrada, o vinculo continua ativo.
3. Se mais de um vinculo cobrir a data, o sistema escolhe o mais recente.

Se nenhum contrato ou locacao for encontrado, a tela permite selecionar responsavel manualmente.

---

### 19. O sistema tem modulo de gestao de multas e indicacao de condutor?
Sim. O sistema possui Central de Multas com cadastro manual, filtros, impressao, envio de documentos, indicacao de condutor quando os dados oficiais estao preenchidos e integracao com sistema de consultas online.

Acesse o menu: `Empresa > Central de Multas`

Na tabela da Central de Multas, as acoes confirmadas incluem `Imprimir`, `Editar`, `Indicar Real Infrator`, `Marcar como Pago`, `Marcar como Nao Pago` e `Excluir`, conforme os dados da multa.

---

### 20. O sistema integra diretamente com SNE/Senatran?
Sim, por meio da integracao chamada `Consulta Online`.

A integracao cobre:
1. Consulta de infracoes por placa (individual e em lote).
2. Download de notificacoes SNE em PDF (Notificacao de Autuacao e Notificacao de Penalidade).
3. Indicacao de real infrator e de principal condutor.
4. Eventos automaticos de novas multas.
5. Controle de saldo prepago, com recarga por PIX ou cartao.

Acesse o menu: `Empresa > Central de Multas` para a gestao das multas e as telas de consultas online.

---

## Checklist, aplicativo e assinatura

### 21. Como fazer checklist digital pelo celular?
O checklist digital e feito pelo celular, em uma tela propria do sistema.

Acesse o menu: `Veiculos > Checklist`

Na listagem de checklists, use a opcao de criar checklist digital quando ela estiver disponivel no celular. O checklist digital exige permissao para criar checklists e plano P3 ou P4.

Na criacao, o fluxo possui 4 abas: `Informacoes`, `Questoes`, `Vistorias` e `Assinatura`. Em `Vistorias`, pelo menos uma foto e obrigatoria para avancar.

---

### 22. O aplicativo de vistoria substitui o checklist no navegador?
Nao foi possivel confirmar a resposta completa com os dados disponiveis.

Foi possivel confirmar que existe checklist digital pelo navegador do celular e que o appLoja e citado nas respostas rapidas como aplicativo focado em checklist, assinatura da empresa e assinatura de documentos. Tambem foi encontrado atendimento orientando uso temporario pelo navegador do celular.

Falta confirmar, pela documentacao do projeto, o status operacional atual do appLoja e se ele deve substituir ou apenas complementar o checklist via navegador.

---

### 23. Como retomar um checklist pendente?
Nao foi possivel confirmar o caminho exato do menu com os dados disponiveis.

Foi confirmado que o sistema possui uma listagem mobile de checklists e permite continuar um checklist pendente. Ao retomar, o sistema carrega os dados ja salvos e leva o usuario para a etapa correta.

As etapas possiveis sao:
1. `Questoes`, se ainda houver perguntas incompletas.
2. `Vistorias`, se as questoes estiverem completas mas nao houver foto.
3. `Assinatura`, se tudo estiver preenchido.

Para confirmar o procedimento operacional, falta validar o nome exato do botao ou acao exibida na listagem mobile.

---

### 24. Como funciona a assinatura digital de contrato ou locacao?
Acesse o menu: `Contrato/Locacoes > Contratos` ou `Contrato/Locacoes > Locacoes/Reservas`

Na coluna de acoes da listagem, clique no icone `Assinatura` (icone de assinatura na linha do registro). Se ainda nao houver assinatura, abre a janela `Link de assinatura`, com os botoes `WhatsApp`, `Copiar` e `Abrir`. Se ja houver assinatura, o mesmo icone abre a visualizacao da assinatura feita.

O cliente acessa o link recebido, visualiza o resumo do contrato ou locacao, desenha a assinatura na tela e confirma. A assinatura e salva com fundo branco, dados de auditoria do acesso, geolocalizacao quando enviada e verificacao de integridade.

---

### 25. E possivel enviar contrato para assinatura enquanto a locacao ainda esta como reserva?
Sim. A acao `Assinatura` esta disponivel na listagem para locacoes com status `Reserva`, `Aberto` e `Fechado` (fica oculta apenas para o status `Pendente`).

A assinatura feita na reserva permanece valida quando a locacao e aberta, pois abrir a locacao apenas altera o status do mesmo registro; a assinatura continua vinculada a essa mesma locacao.

Acesse o menu: `Contrato/Locacoes > Locacoes/Reservas` e use o icone `Assinatura` na linha da reserva para copiar, abrir ou enviar o link por WhatsApp.

---

## Permissoes e acesso

### 26. Como liberar permissoes para um funcionario?
Acesse o menu: `Empresa > Funcionarios`

Na tela de funcionarios, abra o cadastro do funcionario desejado e ajuste a funcao atribuida. As permissoes ficam associadas a uma funcao, e o funcionario recebe as permissoes dessa funcao.

As funcoes padrao confirmadas incluem `Proprietario`, `Gerente`, `Coordenador Administrativo`, `Assistente Administrativo` e `Atendente`. Depois de alterar a funcao ou permissoes, clique em `Salvar`.

Veja: https://www.7carros.com.br/videos/funcionarios-e-permissoes

---

### 27. Por que uma opcao nao aparece para um funcionario?
Geralmente isso depende da funcao/permissao do funcionario e, em alguns casos, do plano contratado.

Acesse o menu: `Empresa > Funcionarios`

Abra o cadastro do funcionario e confira a funcao atribuida. Para checklists digitais, por exemplo, alem da permissao para criar checklists, a documentacao confirma exigencia de plano P3 ou P4.

Se a permissao foi alterada recentemente e ainda nao apareceu, pode haver atraso de ate 1 hora para a atualizacao de algumas permissoes, conforme documentacao consultada.

Veja: https://www.7carros.com.br/videos/funcionarios-e-permissoes

---

## Relatorios

### 28. Como tirar um relatorio de veiculos locados com o cliente atual?
Acesse o menu: `Relatorios > Veicular > Veiculo/cliente`

O relatorio exibe as colunas: `Tipo` (Locacao ou Contrato), `Codigo`, `Placa`, `Veiculo`, `Cliente`, `Inicio`, `Fim`, `Dias`, `Km Rodado` e `Valor`. Registros sem data de fim aparecem com o marcador `Em uso`, indicando que o veiculo esta com o cliente atualmente.

Os filtros disponiveis sao: periodo (data inicio e fim), `Filial`, `Status` (Todos, Abertos ou Fechados) e `Grupo de veiculos`. Para ver apenas veiculos locados no momento, use o filtro `Status = Abertos`.

---

### 29. Como consultar faturas vencidas ou a vencer?
Acesse o menu: `Relatorios > Faturas > Vencidas/a vencer`

Os filtros disponiveis sao: periodo de vencimento (data inicio e fim), a visao `Vencidas` ou `A vencer` (botoes de alternancia), `Filial` e `Cliente`. O relatorio considera apenas faturas a receber pendentes.

As colunas exibidas incluem fatura, cliente, vencimento, valor original, juros/multa, valor total, dias e status, alem da acao de link de pagamento.

---

### 30. O relatorio de lucratividade pode ser filtrado por placa?
Depende do relatorio. Existem dois relatorios relacionados:

1. `Relatorios > Veicular > Lucro por veiculo`: possui filtro `Placa` (busca de veiculo especifico), alem de periodo, filial e grupo. Exibe as colunas `Placa`, `Veiculo`, `Grupo`, `Receita`, `Despesa`, `Lucro` e `Margem`. Use este relatorio quando o cliente quiser lucratividade de uma placa especifica.

2. `Relatorios > Financeiro > Analise de rentabilidade`: nao possui filtro por placa. Ele agrupa os resultados por dimensao (`Grupo`, `Veiculo`, `Filial` ou `Cliente`). Com a dimensao `Veiculo`, lista todos os veiculos identificados por placa, mas sem filtrar um veiculo especifico.

---

## Website e planos

### 31. O sistema oferece site integrado para reservas online?
Sim. A documentacao do modulo Website confirma site publico da empresa, dominio personalizado, customizacao visual e reserva online integrada ao sistema.

Acesse o menu: `WebSite > Ativar`

Quando o site estiver ativo, o menu passa a exibir configuracoes como `Configuracoes`, `Aparencia`, `Conteudos`, `Banners`, `SEO`, `Integracoes` e `Publicar`.

---

### 32. Como ativar o website com dominio proprio?
Acesse o menu: `WebSite > Ativar`

Na tela de ativacao, informe o dominio, selecione se deseja registrar dominio ou usar dominio proprio, escolha se deseja hospedagem e use a verificacao de DNS. Depois, solicite a ativacao do site.

O sistema muda o status para pendente e o processo depende de configuracao administrativa para publicar o site inicial.

---

### 33. Como publicar alteracoes do website?
Acesse o menu: `WebSite > Publicar`

Esse caminho aparece quando o website esta com status ativo. A documentacao confirma que a publicacao envia o site gerado para o ambiente configurado do cliente, mantendo o modelo interno protegido no servidor.

---

### 34. Como cancelar a assinatura ou alterar plano?
O cancelamento e a troca de plano nao sao feitos dentro do sistema 7Carros. A gestao comercial da assinatura (mudar plano, suspender, reativar, cancelar) acontece no sistema externo de cobranca (area do cliente da Hostcia/WHMCS).

Dentro do sistema, o unico ponto relacionado e a tela de limite do plano: quando um limite e atingido, o sistema exibe a pagina `Limite do plano atingido` com o botao `Fazer Upgrade`, que abre o WhatsApp do suporte com a mensagem de solicitacao de mudanca de plano.

Oriente o cliente a usar a area do cliente da Hostcia ou falar com o comercial/suporte para cancelar ou alterar o plano.

---

### 35. O teste gratuito esta sempre disponivel?
Nao foi possivel confirmar disponibilidade atual do teste gratuito com os dados disponiveis.

Foi encontrada uma resposta rapida informando que normalmente existe teste de 30 dias, mas que em periodos promocionais essa opcao pode ficar desativada. Como isso depende de regra comercial vigente, a disponibilidade precisa ser confirmada no momento do atendimento.

---

## Perguntas complementares

### 36. Como configurar templates de mensagens automaticas?
Acesse o menu: `Sistema > Templates de Mensagem`

Na tela de templates, escolha o tipo de mensagem e o canal desejado, como email, WhatsApp ou SMS. Edite o conteudo usando as variaveis disponiveis no formato `{{entidade.campo}}` e salve.

Os templates confirmados incluem boas-vindas, confirmacao de locacao, confirmacao de contrato, pedido de assinatura digital, lembrete de devolucao, lembrete de pagamento, fatura gerada, aviso de atraso e CNH proxima do vencimento.

---

### 37. Quais canais de mensagem o sistema suporta?
O sistema suporta `email`, `whatsapp` e `sms`.

Email pode usar SMTP da empresa ou, quando permitido, a configuracao padrao do sistema. WhatsApp exige instancia conectada por filial. SMS exige conexao validada por filial. Sem configuracao valida de WhatsApp ou SMS, o envio falha e nao fica aguardando envio.

---

### 38. Como configurar WhatsApp, SMS ou SMTP da empresa?
Acesse o menu: `Empresa > WhatsApp, SMS e SMTP`

Nessa area ficam as configuracoes de envio. Foi confirmado que existem opcoes para adicionar, editar e testar WhatsApp, SMS e SMTP. Para WhatsApp, a documentacao confirma que e necessaria uma instancia conectada por filial para envios da empresa.

---

### 39. O que significa quando uma mensagem fica com status "Falhou"?
Significa que o envio nao foi concluido com sucesso.

Pelos dados disponiveis, os motivos mais provaveis dependem do canal: WhatsApp sem instancia conectada, SMS sem conexao validada, erro no provedor, credenciais invalidas ou falha no envio. Para confirmar o motivo exato, e necessario consultar o historico da mensagem ou a tela de configuracao do canal relacionado.

---

### 40. Como criar ou editar modelos de documentos para contrato, locacao ou multa?
Acesse o menu: `Empresa > Documentos`

Na tela de documentos, crie ou edite um modelo informando `titulo`, `tipo`, `status` e o conteudo no editor. O tipo define onde o modelo aparece: `Contrato`, `Locacao` ou `Multa`.

Modelos padrao do sistema podem aparecer junto com os modelos da empresa. Ao editar um modelo padrao, o sistema cria uma copia para a empresa e preserva o original do sistema.

Veja: https://www.7carros.com.br/videos/cadastro-de-documentos

---

### 41. Quais variaveis posso usar em modelos de documentos?
Os modelos aceitam placeholders no formato `{{entidade.campo}}`.

Exemplos confirmados incluem variaveis de cliente, empresa, contrato, locacao, veiculo, fatura e outros dados do contexto. Para contratos com multiplos veiculos, a variavel `{{contrato.veiculos_anexo}}` gera uma tabela completa com os veiculos do contrato.

Acesse o menu: `Empresa > Documentos`

Na tela de edicao do documento, use o painel lateral de variaveis disponiveis para inserir os placeholders corretamente.

Veja: https://www.7carros.com.br/videos/cadastro-de-documentos

---

### 42. Por que uma variavel do documento aparece vazia ou incompleta no PDF?
Isso acontece quando o dado usado pela variavel nao existe no contexto do contrato, locacao ou multa, ou quando o modelo usa uma variavel que nao e valida para aquele tipo de documento.

Verifique se:
1. O modelo esta no tipo correto: `Contrato`, `Locacao` ou `Multa`.
2. A variavel existe na lista de variaveis disponiveis.
3. O cadastro relacionado tem a informacao preenchida.
4. O documento foi salvo apos a alteracao.

Se mesmo assim a variavel continuar vazia, nao foi possivel confirmar a causa sem analisar o registro e o modelo especifico.

---

### 43. Como configurar os grupos de veiculos e seus valores?
Acesse o menu: `Veiculos > Grupos`

Na tela de grupos, cadastre ou edite a categoria do veiculo. O grupo centraliza precos por plano, seguros, precos progressivos por dias e configuracoes usadas nas reservas e locacoes.

O sistema usa o grupo como unidade de reserva: a locadora pode reservar uma categoria e alocar um veiculo disponivel desse grupo na retirada.

Veja: https://www.7carros.com.br/videos/grupos-veiculares

---

### 44. Como o sistema calcula o valor da diaria pelo grupo?
O calculo segue esta ordem:
1. Usa o valor base do grupo conforme o plano escolhido.
2. Se houver preco progressivo para a quantidade de dias, esse valor substitui o valor base.
3. Se houver temporada ativa com ajuste para o grupo, aplica o percentual da temporada sobre o valor encontrado.

Ou seja, preco progressivo substitui o valor base; temporada ajusta o valor.

Veja: https://www.7carros.com.br/videos/grupos-veiculares

---

### 45. Como configurar valores diferentes por temporada?
Acesse o menu: `Veiculos > Temporadas`

Use temporadas para aplicar ajustes percentuais sobre os valores dos grupos em periodos especificos. O ajuste de temporada nao substitui o valor do grupo; ele aplica um percentual sobre o valor base ou sobre o preco progressivo encontrado para a locacao.

---

### 46. Como tornar um grupo disponivel no site?
Acesse o menu: `Veiculos > Grupos`

No cadastro do grupo, na aba `Dados do Grupo`, marque a opcao `Visivel no site` (vem marcada por padrao) e clique em `Salvar`.

Na listagem de grupos, a coluna `Site` mostra um indicador de visivel/oculto para cada grupo. Apenas grupos com essa opcao ativa aparecem no site publico.

Veja: https://www.7carros.com.br/videos/grupos-veiculares

---

### 47. Como configurar seguro para aparecer na reserva do site?
Os valores de seguro sao configurados no grupo do veiculo, nao no Website.

Acesse o menu: `Veiculos > Grupos`

No cadastro do grupo, na aba `Valores por filial`, secao `Seguros`, preencha os campos `Valor Seguro Carro (por dia)`, `Valor Seguro Terceiros (por dia)`, `Cobertura Carro` e `Cobertura Terceiros`.

Nao existe configuracao de seguro em `WebSite > Configuracoes`. Observacao importante: o backend calcula os seguros na reserva, mas o template atual do site publico nao exibe a opcao de marcar seguro para o visitante. Se o cliente relatar que o seguro nao aparece na reserva do site, esse e o comportamento atual do template e deve ser encaminhado a equipe tecnica.

---

### 48. Como configurar pagamento antecipado no site?
Acesse o menu: `WebSite > Configuracoes`

Ative a opcao `Pagamento antecipado`. Com ela ativa, o ultimo passo da reserva no site exibe o bloco `Forma de pagamento`, exige a escolha de uma forma e, ao confirmar, o sistema cria o lancamento financeiro e redireciona o visitante para o link de pagamento. Com a opcao desligada, a reserva e criada sem exigir pagamento.

Depois, acesse o menu: `Empresa > Formas de pagamento`

Na forma de pagamento que deve aparecer na reserva online, marque `Site` no campo `Onde Exibir`, deixe a forma ativa, vincule um gateway ativo e associe a filial de retirada. Sem esses requisitos, o site exibe a mensagem de que nenhuma forma de pagamento online esta disponivel para a filial.

---

### 49. Por que a opcao de pagamento nao aparece para o cliente no site?
Verifique quatro pontos:
1. Se a opcao `Pagamento antecipado` esta ativa em `WebSite > Configuracoes` (sem ela, o site nao mostra pagamento).
2. Se a forma de pagamento esta ativa e marcada com `Site` no campo `Onde Exibir`.
3. Se a forma esta vinculada a um gateway ativo.
4. Se a forma esta associada a filial de retirada escolhida na reserva.

Acesse o menu: `Empresa > Formas de pagamento`

Depois, se for pagamento online, acesse o menu: `Empresa > Gateways de pagamento`

Quando nenhum desses requisitos e atendido para a filial, o site exibe a mensagem de que nenhuma forma de pagamento online esta disponivel.

Veja: https://www.7carros.com.br/videos/dica001

Veja: https://www.7carros.com.br/videos/gateways-de-pagamento

---

### 50. Como recuperar acesso ao sistema quando aparece usuario nao encontrado ou sem acesso?
O sistema possui recuperacao de senha na tela de login: clique em `Redefinir senha`, informe o campo `E-mail ou usuario` e clique em `Enviar link`. O link de redefinicao so e enviado se o funcionario estiver ativo e tiver email cadastrado.

As mensagens de erro do login indicam a causa:
1. `Usuario ou senha invalidos.`: usuario inexistente ou senha errada (a mensagem e a mesma para os dois casos).
2. `Seu acesso esta suspenso...`: conta suspensa, geralmente por fatura vencida da assinatura; o cliente deve regularizar com o suporte/comercial.
3. `Seu usuario esta inativo...`: funcionario com cadastro inativo; um administrador deve reativa-lo em `Empresa > Funcionarios`.
4. `Acesso temporariamente bloqueado...`: bloqueio por muitas tentativas; aguardar o tempo informado ou redefinir a senha.

Se o funcionario nao existe, o administrador deve cadastra-lo em `Empresa > Funcionarios` com a funcao/permissoes adequadas.

Veja: https://www.7carros.com.br/videos/login-instrucao

---

### 51. O que e a busca rapida?
A busca funcional do sistema e a busca global `Localizar`, aberta pelo botao `Localizar` na barra superior ou pelo atalho `Ctrl+K` (ou `Cmd+K` no Mac).

Ela pesquisa, a partir de 2 caracteres e respeitando as permissoes do usuario:
1. Clientes (nome, CPF/CNPJ).
2. Veiculos (placa, marca, modelo, RENAVAM).
3. Locacoes (codigo, nome do cliente).
4. Contratos (codigo, nome/CPF/CNPJ do cliente).
5. Funcionarios (nome, email).

Ao clicar em um resultado, o sistema abre a tela de edicao do registro em uma aba.

Observacao: o bloco `Busca rapida` que aparece na barra lateral (com campos de veiculo e data) e um elemento sem funcao ativa no momento; oriente o cliente a usar o `Localizar`.

---

### 52. Como consultar contratos vinculados a uma placa?
Nao existe tela dedicada de historico por placa. O caminho mais proximo e:

Acesse o menu: `Relatorios > Veicular > Veiculo/cliente`

Esse relatorio lista o historico de locacoes e contratos com a coluna `Placa`, mas a tela nao possui filtro por placa; filtre por periodo, filial, status e grupo, e localize a placa desejada na listagem (ou exporte e filtre externamente).

Alternativa rapida: use o `Localizar` (`Ctrl+K`/`Cmd+K`) para buscar a placa e abrir o cadastro do veiculo. O cadastro do veiculo possui as abas `Manutencoes` e `Faturas`, mas nao possui aba de historico de contratos/locacoes.

---

### 53. Como ver faturas por veiculo?
Acesse o menu: `Veiculos > Veiculos`

Abra a edicao do veiculo desejado e clique na aba `Faturas` (a aba so aparece em veiculos ja salvos). A aba possui os filtros `A receber` e `A pagar` e exibe tipo, vencimento, descricao, cliente/fornecedor, forma, origem, valor e status de cada fatura.

Cada linha possui a acao `Abrir fatura`, que leva ao lancamento no financeiro.

---

### 54. Como selecionar apenas despesas na listagem do financeiro?
Acesse o menu: `Financeiro > Lancamentos`

No bloco de filtros da listagem (Filial, Ano, Mes, Status, Tipo), use o filtro `Tipo` e selecione `Despesa (Pagar)`. As opcoes disponiveis sao `Todos`, `Receita (Receber)` e `Despesa (Pagar)`.

---

### 55. Como lancar uma despesa de manutencao no financeiro?
Acesse o menu: `Veiculos > Manutencoes`

Abra a ordem de servico ja salva e clique na aba `Financeiro` (a aba so aparece apos salvar a OS). Na secao `Lancamentos Financeiros`, use um dos botoes:
1. `Criar Lancamento Completo`: lanca todos os itens da OS.
2. `Fechar Itens Selecionados`: lanca apenas os itens marcados (lancamento parcial).

Em seguida, preencha a `Configuracao do Lancamento` (forma de pagamento, parcelas, data do primeiro vencimento e intervalo em dias) e clique em `Confirmar`.

O lancamento nao e automatico. Se a OS tiver cliente vinculado, o sistema gera receita; sem cliente, gera despesa.

Veja: https://www.7carros.com.br/videos/manutencoes-veiculares

---

### 56. Como funciona a manutencao preventiva?
Acesse o menu: `Veiculos > Plano de manutencoes`

Cadastre um plano com intervalos de km para cada item de manutencao. Depois vincule esse plano ao veiculo. O sistema compara o odometro atual com a proxima km prevista e gera uma OS automaticamente quando a diferenca fica dentro da margem configurada.

Por padrao documentado, a margem e de 500 km.

Veja: https://www.7carros.com.br/videos/plano-de-manutencoes

---

### 57. Por que o sistema abriu uma manutencao preventiva automaticamente?
Isso pode acontecer quando um veiculo possui plano de manutencao vinculado e o odometro esta dentro da margem de alerta para algum item.

A rotina de manutencao preventiva verifica a diferenca entre a proxima km do item e o odometro atual. Se a diferenca for menor ou igual a margem configurada, o sistema gera uma OS e atualiza a proxima km.

Veja: https://www.7carros.com.br/videos/plano-de-manutencoes

---

### 58. Como configurar baixa automatica de estoque em manutencoes?
Acesse o menu: `Empresa > Estoque`

No cadastro do produto, ative `baixa_automatica`. Quando esse produto for usado em um item de OS, o estoque sera ajustado automaticamente ao criar, alterar quantidade ou remover o item.

Observacao: a baixa automatica so ocorre para produtos vinculados ao estoque. Itens manuais de OS nao movimentam estoque.

---

### 59. O sistema permite estoque negativo?
Acesse o menu: `Empresa > Estoque`

No cadastro do produto, configure `permitir_estoque_negativo`. Quando estiver como `N`, a selecao do produto pode ser bloqueada se o estoque estiver zerado ou negativo, e a quantidade fica limitada ao disponivel. Quando estiver como `S`, o produto pode ser usado mesmo sem estoque.

---

### 60. Como emitir NFS-e pelo sistema?
Acesse o menu: `Financeiro > NFS-e`

O modulo emite NFS-e por filial a partir das configuracoes em `nfse_configuracoes`. Os emissores suportados na documentacao sao `nacional`, `betha` e `issnet`.

Antes de emitir, a filial precisa estar configurada com dados fiscais, ambiente, serie, codigo de municipio, codigo de servico, regime tributario e certificado digital quando exigido.

---

### 61. Onde configurar NFS-e da filial?
Acesse o menu: `Empresa > Matriz e filiais`

Abra a edicao da matriz/filial desejada e clique na aba `NFS-e`. A aba so aparece para usuarios com permissao de configurar NFS-e e em filiais ja salvas.

A aba possui as secoes:
1. `Certificado Digital`: campos `Arquivo .pfx/.p12` e `Senha do Certificado`, com os botoes `Enviar Certificado` e `Remover Certificado`.
2. Configuracoes gerais: emissao ativa, ambiente, tipo de emissao, serie, numero atual, emissao automatica e envio de email ao tomador.
3. Dados fiscais: codigo IBGE do municipio, codigo de servico, CNAE, regime tributario, aliquota ISS, IBS/CBS, entre outros.

Ao final, use `Testar Conexao` e `Salvar`.

Veja: https://www.7carros.com.br/videos/cadastrar-matriz-e-filiais

---

### 62. O que fazer quando o certificado da NFS-e nao gera ou nao valida?
Verifique se o certificado digital esta presente, se a senha esta correta e se o arquivo do certificado nao esta corrompido.

A documentacao orienta diferenciar certificado vencido, arquivo ausente, senha invalida e erro de leitura do certificado. Se o certificado veio de importacao do legado, o sistema pode precisar regravar a senha no formato atual.

O upload do certificado fica em: `Empresa > Matriz e filiais` > editar a filial > aba `NFS-e` > secao `Certificado Digital`, com os campos `Arquivo .pfx/.p12` e `Senha do Certificado` e o botao `Enviar Certificado`. Para trocar o certificado, use `Remover Certificado` e envie o novo arquivo.

---

### 63. Quando devo preencher IBS/CBS na NFS-e?
O preenchimento de IBS/CBS depende da configuracao fiscal da filial.

Pela documentacao, quando o preenchimento de IBS/CBS estiver desativado, o sistema nao deve enviar essas informacoes e deve usar total de tributos zerado. Portanto, nao preencha aliquotas IBS/CBS se essa configuracao estiver desativada.

Se a emissao exigir IBS/CBS, confirme antes a configuracao fiscal da filial e o emissor usado.

---

### 64. Como gerar link de assinatura digital e enviar por WhatsApp?
Na listagem de contratos e locacoes, foi confirmado que a janela `Link de Assinatura` permite copiar, abrir ou enviar o link por WhatsApp.

O envio por WhatsApp usa o modelo de mensagem de pedido de assinatura e depende de WhatsApp conectado na filial. Se o link nao chegar ao cliente, verifique:
1. Se a filial possui instancia WhatsApp conectada.
2. Se o cliente possui telefone valido.
3. Se o modelo de mensagem de assinatura esta ativo.
4. Se o historico de mensagens registrou falha.

---

### 65. Como salvar cartao de credito do cliente?
Acesse o menu: `Clientes`

Abra a edicao do cliente (o cliente precisa estar salvo) e, na aba `Dados`, localize a secao `Cartoes de Credito`. Clique em `Adicionar Cartao`, selecione o `Gateway` e preencha os dados do cartao. Para Stripe, o formulario e o proprio componente seguro do gateway; para outros gateways (ex: Asaas), preencha titular, CPF/CNPJ, numero, validade e CVV. Clique em `Salvar Cartao`.

O cartao tambem pode ser salvo pelo proprio cliente na pagina publica de pagamento, e na edicao do contrato existe o botao `Adicionar cartao` na secao de bloqueio de valor.

---

### 66. O bloqueio de valor no cartao funciona?
Sim, o sistema possui suporte a bloqueio de valor no cartao para locacoes e contratos quando o gateway suporta essa funcao.

O bloqueio reserva valor no limite do cartao sem cobrar imediatamente, podendo ser capturado ou liberado depois.

O funcionamento depende do gateway configurado e das credenciais corretas.

---

### 67. Como configurar formas de pagamento parceladas por semana, mes ou dia fixo?
Acesse o menu: `Empresa > Formas de pagamento`

Na tela de formas de pagamento, use a area de `Comandos de parcelas` para cadastrar ou editar comandos. Exemplos confirmados:
1. `0`: pagamento a vista.
2. `15`: pagamento unico para daqui a 15 dias.
3. `1-12`: parcelas mensais de 1 a 12 vezes.
4. `w36`: 36 parcelas semanais.
5. `w36-Seg`: 36 parcelas semanais com vencimento toda segunda-feira.
6. `d15`: vencimento todo dia 15.

Veja: https://www.7carros.com.br/videos/comandos-de-parcelas

---

### 68. Por que parcelas semanais estao sendo geradas no dia errado?
Quase sempre e o comando de parcelas usado. A regra confirmada e:

1. `w36` (semanal simples): a primeira parcela cai exatamente na data base informada e as demais somam 1 semana cada. O dia da semana das parcelas e o mesmo dia da semana da data base; o comando nao fixa dia.
2. `w36-Seg` (semanal com dia fixo): o sistema calcula a data semanal e avanca ate a proxima segunda-feira; nunca retrocede. Se a data base for uma quarta, a primeira parcela vai para a segunda seguinte.

Se o cliente espera "toda segunda" mas usou `w36`, o dia vai depender da data base. Verifique o comando aplicado no contrato/forma de pagamento e a data base da primeira parcela.

Veja: https://www.7carros.com.br/videos/comandos-de-parcelas

---

### 69. Como configurar taxas cobradas pela operadora de pagamento?
Acesse o menu: `Empresa > Formas de pagamento`

Na forma de pagamento, configure os campos de taxa:
1. `Taxa Fixa`: valor fixo total diluido entre parcelas.
2. `Taxa Fixa por Parcela`: valor fixo cobrado em cada parcela.
3. `Taxa Percentual por Parcela`: percentual cobrado sobre cada parcela.

Ao criar uma receita com forma de pagamento, o financeiro preserva as taxas usadas naquele lancamento para manter o historico correto.

Veja: https://www.7carros.com.br/videos/formas-de-pagamento

---

### 70. Como acessar o link publico de pagamento de uma fatura?
Acesse o menu: `Financeiro > Lancamentos`

Na linha da fatura, clique na acao `Link de Pagamento` (icone de link externo, disponivel apenas para receitas nao pagas). Abre a janela `Link de pagamento` com os botoes `Copiar`, `Abrir` e `Fechar`.

O link tambem aparece na fatura em PDF como `Link para pagamento online` e na janela `Imprimir / Enviar Fatura`.

---

### 71. O link de pagamento muda quando altero valor ou vencimento da fatura?
Nao deve mudar.

A documentacao confirma que links publicos de pagamento sao estaveis. O link ja enviado ao cliente deve continuar valido enquanto a receita estiver pendente. Quando valor, vencimento, cliente, juros, multa, desconto ou itens mudam, o sistema atualiza os dados do link existente.

---

### 72. Posso excluir um link de pagamento de uma fatura?
Nao. Nao existe acao manual para excluir ou regenerar o link publico de pagamento.

O link e estavel e reutilizado enquanto a fatura estiver pendente. Quando valor, vencimento ou outros dados mudam, o sistema atualiza o link existente e invalida automaticamente cobrancas externas abertas no gateway, sem acao do usuario. Quando a fatura e paga, o link deixa de aceitar pagamento.

---

### 73. Por que o WhatsApp nao identifica ou nao envia para um numero?
Nao foi possivel confirmar uma causa unica com os dados disponiveis.

Pelos fluxos confirmados, verifique:
1. Se a instancia WhatsApp da filial esta conectada.
2. Se o numero do cliente esta cadastrado corretamente.
3. Se o envio esta sendo feito por um canal que exige WhatsApp conectado da empresa.
4. Se o historico de mensagens registrou falha no envio.

Se a instancia estiver desconectada, o sistema nao deve deixar mensagem pendente para envio por WhatsApp da empresa.

---

### 74. Como conectar ou testar a instancia de WhatsApp?
Acesse o menu: `Empresa > WhatsApp, SMS e SMTP`

Foram confirmadas telas de adicionar, editar, testar e QR Code para WhatsApp. Na area de WhatsApp, use as acoes da instancia para conectar, testar ou abrir o QR Code conforme a tela exibir.

---

### 75. O sistema envia email mesmo sem SMTP configurado?
Sim, a documentacao confirma que o email pode usar SMTP da empresa ou, quando permitido, a configuracao padrao do sistema.

Para WhatsApp e SMS, o comportamento e diferente: sem configuracao valida da filial, o envio falha.

---

### 76. Como obter uma segunda via de boleto?
Nao existe acao especifica de "segunda via". O boleto e gerado pelo link publico de pagamento, que e estavel enquanto a fatura estiver pendente.

Acesse o menu: `Financeiro > Lancamentos`

Na linha da fatura, use a acao `Link de Pagamento` para copiar e reenviar o mesmo link ao cliente, ou a acao `Imprimir / Enviar Fatura` para reenviar por email, WhatsApp ou SMS. Ao acessar o link e escolher boleto, o cliente obtem o boleto atualizado (se a cobranca anterior foi invalidada por mudanca de valor/vencimento, uma nova e gerada automaticamente no gateway).

---

### 77. Como filtrar contratos por vencidos, vencendo hoje ou amanha?
A listagem de contratos nao possui esse filtro. Os filtros disponiveis sao apenas `Todos`, `Ativos` e `Finalizados`, alem da busca por codigo ou cliente.

A listagem exibe, na coluna Info, indicadores visuais como `Venceu` (autorrenovacao vencida) e `Renov. em X dias` (quando faltam 7 dias ou menos), mas eles nao sao filtros clicaveis.

Para vencimentos financeiros, use: `Relatorios > Faturas > Vencidas/a vencer`, alternando a visao entre `Vencidas` e `A vencer`.

---

### 78. Por que a lista de contratos nao aparece na ordem esperada?
A listagem de contratos e ordenada pela data de inicio do contrato, da mais recente para a mais antiga (com desempate pelo cadastro mais recente).

A tela nao permite ordenar por coluna. Se o contrato desejado nao aparece no topo, use a busca por codigo ou cliente, ou os filtros `Ativos`/`Finalizados`.

---

### 79. Como emitir contrato em PDF para cliente ler antes de assinar?
Acesse o menu: `Contrato/Locacoes > Contratos`

Ou acesse o menu: `Contrato/Locacoes > Locacoes/Reservas`

Na listagem, use a acao de impressao do contrato ou locacao e selecione a opcao de documento/fatura adequada. Para locacoes em reserva, a documentacao confirma que a impressao apresenta `Voucher` em vez de `Fatura`.

---

### 80. Como cadastrar condutor adicional?
Acesse o menu: `Contrato/Locacoes > Novo contrato`

Ou acesse o menu: `Contrato/Locacoes > Nova Locacao`

Nos formularios de contrato e locacao existe a aba `Condutor Adicional`. Nessa aba, cadastre os dados do condutor adicional, como nome, CPF, CNH e validade, conforme os campos exibidos.

---

### 81. Como cadastrar fiador, avalista ou testemunha no contrato?
Acesse o menu: `Contrato/Locacoes > Novo contrato`

No formulario de contrato, use as abas `Fiador`, `Avalista` e `Testemunhas`. A documentacao confirma que esses dados ficam registrados no contrato.

Para locacoes, a documentacao tambem confirma campos equivalentes de referencias/intervenientes usados na fatura.

---

### 82. Como substituir um veiculo em uma locacao?
Acesse o menu: `Contrato/Locacoes > Locacoes/Reservas`

Na coluna de acoes da listagem, clique no icone `Substituir Veiculo` (disponivel apenas para locacoes com status `Aberto` e para usuarios com a permissao de substituir).

A tela de substituicao exige novo plano, novo grupo e novo veiculo, alem de data e odometro de entrada do veiculo atual. O sistema registra a saida do veiculo anterior e o novo vinculo no historico da locacao.

---

### 83. Como substituir um veiculo em um contrato?
Acesse o menu: `Contrato/Locacoes > Contratos`

Na coluna de acoes da listagem, clique no icone `Substituir Veiculo` (disponivel apenas para contratos com status `Ativo` e para usuarios com a permissao de substituir).

A documentacao confirma o fluxo de substituicao de veiculo em contratos, incluindo historico de veiculos e opcao de manter valores.

---

### 84. O que fazer quando a substituicao de veiculo nao passa por campos obrigatorios?
Verifique os campos obrigatorios da tela de substituicao.

Na substituicao de locacao, foram confirmados campos como `novoPlano`, `novoGrupo` e `novoVeiculo`, alem de dados de data/odometro de entrada do veiculo atual. Se algum deles estiver vazio, o sistema pode bloquear a conclusao.

Se todos estiverem preenchidos e o erro continuar, nao foi possivel confirmar a causa sem analisar a locacao/contrato especifico.

---

### 85. Como registrar odometro durante um contrato ativo?
Acesse o menu: `Contrato/Locacoes > Contratos`

Na primeira coluna da listagem, clique no icone `Registrar odometro` (icone de velocimetro). Abre um painel lateral onde se informa a leitura e clica em `Salvar leitura`.

Requisitos: contrato com status `Ativo`, pelo menos um veiculo ativo e permissao de editar contratos. Fora dessas condicoes, o icone aparece desabilitado com aviso `Disponivel apenas para contratos ativos com veiculo` ou `Sem permissao para registrar odometro`.

---

### 86. Como alterar um km registrado errado?
Depende de onde o km foi gravado. O que e possivel corrigir pela tela:

1. Locacao aberta: o campo `Odometro (km)` de saida pode ser editado na tela de edicao da locacao.
2. Cadastro do veiculo: o campo de odometro pode ser ajustado em `Veiculos > Veiculos` > editar.

O que nao possui edicao pela tela:
1. Leituras do historico de odometro de contratos: so e possivel incluir novas leituras pelo `Registrar odometro`; nao ha tela para editar ou excluir leituras antigas.
2. Locacao fechada: nao ha reabertura, portanto o odometro de entrada nao pode ser corrigido pelo usuario.
3. Odometro gravado por checklist digital.

Nesses casos sem edicao, o ajuste precisa ser tratado pelo suporte/equipe tecnica. Alterar km afeta cobranca de km excedente, manutencao preventiva e historico do veiculo.

---

### 87. A vistoria pode ser impressa antes de ser feita no checklist digital?
Sim. E possivel imprimir um checklist em branco, para preencher a mao, mesmo sem nenhum checklist digital realizado.

Acesse o menu: `Contrato/Locacoes > Contratos` ou `Contrato/Locacoes > Locacoes/Reservas`

Use a acao de impressao do registro e selecione um tipo que inclua checklist. No campo `Selecione o Modelo de Checklist`, aparecem dois grupos:
1. `Modelos impressos`: gera o checklist em branco, com campos manuais de data, odometro, tanque, diagrama do veiculo e questionario sem respostas.
2. `Checklists digitais realizados`: imprime um checklist digital ja finalizado, com respostas, fotos e assinaturas.

Escolha um item do grupo `Modelos impressos` e clique em `Gerar PDF`.

---

### 88. Como cadastrar modelos de checklist?
Acesse o menu: `Veiculos > Checklist modelos`

Use essa tela para cadastrar modelos usados em checklist digital ou impresso. A documentacao confirma que o checklist digital carrega questoes e itens de vistoria a partir de `checklist_modelos`.

---

### 89. Por que o checklist digital nao aparece para uma reserva sem veiculo?
Porque reservas podem existir apenas por grupo/categoria, sem veiculo especifico definido.

A documentacao confirma que, nesse caso, nao ha checklist vinculado ate a locadora alocar um veiculo especifico na saida. O veiculo especifico e necessario para checklist vinculado.

---

### 90. Como configurar imagens do site, como banners e logo?
Acesse o menu: `WebSite > Banners` para banners.

Para o logo, acesse o menu: `WebSite > Aparencia` e use o campo `Logo do site` (independente do logo do cadastro da empresa).

Limites confirmados para banners: formatos JPEG, PNG ou WebP, tamanho maximo de 5 MB, e o sistema redimensiona automaticamente imagens maiores que 1920 x 1080 pixels mantendo a proporcao. Recomende imagens em orientacao paisagem proximas de 1920 x 1080. A tela nao exibe proporcao recomendada; esses limites sao aplicados automaticamente no envio.

---

### 91. Como recuperar informacoes do site apos alteracao ou perda de conteudo?
O sistema nao possui backup ou restauracao de conteudo do site (textos, banners, SEO) pelo usuario. Conteudo sobrescrito e salvo nao pode ser recuperado pela tela.

O que existe:
1. Na tela `WebSite > Publicar`, ha `Historico de deploys` com as versoes publicadas.
2. Na aparencia, o CSS customizado possui a acao de desfazer o reset de CSS.

Se o cliente perdeu conteudo importante, o caso deve ser encaminhado a equipe tecnica para avaliar recuperacao via backup de banco de dados.

---

### 92. O site pode operar em mais de um pais ou idioma?
O modulo Website possui suporte multi-idioma e moeda por filial, mas nao foi possivel confirmar regras comerciais/operacionais para operar simultaneamente em mais de um pais.

Foi confirmado que o sistema suporta idiomas e que o site usa configuracoes por empresa/filial. Para afirmar funcionamento em um pais especifico, e necessario validar dominio, moeda, regras fiscais, formas de pagamento e emissores aplicaveis.

---

### 93. Como configurar dominio e DNS do site?
Acesse o menu: `WebSite > Ativar`

Na ativacao, informe o dominio e use a verificacao. A documentacao confirma que a verificacao usa DNS real e checa registros A ou CNAME para confirmar que o dominio existe e esta registrado.

Depois da ativacao administrativa, a publicacao inicial e feita com as configuracoes definidas no fluxo de ativacao.

---

### 94. Como publicar alteracoes depois de editar conteudo ou banners do site?
Acesse o menu: `WebSite > Publicar`

Esse menu aparece quando o Website esta ativo. A publicacao envia o site gerado para o ambiente configurado do cliente.

---

### 95. Como configurar reservas online no site?
Acesse o menu: `WebSite > Configuracoes`

Os campos confirmados na tela sao:
1. `Modo manutencao`: exibe pagina de manutencao no site.
2. `Reserva online`: ativa ou desativa a reserva pelo site.
3. `Permitir overbooking`: mantem grupos disponiveis mesmo sem veiculo livre.
4. `Pagamento antecipado`: exige pagamento na reserva.
5. `Reservas requerem confirmacao manual`: pedidos do site ficam pendentes ate aprovacao.

Ha tambem a secao `Pre-cadastro` (`Cadastro simples`, `Exigir envio de documentos` e quais documentos: CNH, CPF, RG/Passaporte, comprovante de endereco), alem de idioma padrao, WhatsApp flutuante e redes sociais.

---

### 96. Como saber se uma reserva veio do site?
Reservas feitas pelo site gravam a marcacao de origem `site` nas observacoes da locacao. Ao abrir a locacao para edicao, o campo de observacoes contem essa informacao (junto de email, telefone e documento informados pelo visitante).

A listagem de locacoes nao exibe coluna de origem.

Sobre o relatorio `Relatorios > Comercial > Origem das locacoes`: ele agrupa pelo campo `canal` da locacao (Balcao, Telefone, Website, WhatsApp etc.). Como a reserva do site nao preenche automaticamente o campo `canal` hoje, essas reservas tendem a aparecer como `Sem canal informado` no relatorio. Para o relatorio refletir a origem, o canal precisa ser preenchido na locacao.

---

### 97. O sistema tem videos de treinamento?
Sim. Os videos de treinamento ficam centralizados no site oficial, em https://www.7carros.com.br/videos

Os videos disponiveis cobrem: instrucoes iniciais (playlist completa), email de ativacao, login, matriz e filiais, funcionarios e permissoes, clientes, gateways de pagamento, contas bancarias, formas de pagamento, comandos de parcelas, plano de manutencoes, grupos veiculares, fornecedores e investidores, veiculos, documentos, manutencoes veiculares, taxas e servicos, reservas e locacoes, contratos e dicas.

Nao existe tela interna de treinamentos dentro do sistema; oriente o cliente a acessar a pagina de videos do site.

Veja: https://www.7carros.com.br/videos

---

### 98. O suporte faz reunioes ou atendimento por mensagem?
Nao foi possivel confirmar uma politica oficial geral com os dados disponiveis.

Foi encontrada resposta rapida informando que o suporte prefere receber as duvidas por mensagem, uma por vez, e que reunioes podem nao ser feitas. Como isso e regra operacional de atendimento e pode mudar, deve ser confirmado com a equipe responsavel.

---

### 99. Como centralizar atendimento quando ha varios operadores da locadora?
Foi encontrada resposta rapida orientando criar um grupo no WhatsApp quando a empresa possui dois ou mais operadores do sistema.

A orientacao e adicionar todos os operadores e a equipe de suporte no grupo, centralizando duvidas e solicitacoes para agilizar o atendimento.

Nao foi possivel confirmar se essa regra esta documentada formalmente fora das respostas rapidas.

---

### 100. Como conceder acesso temporario ao suporte?
Acesse o menu: `Sistema > Conceder acesso`

Na tela `Conceder Acesso ao Suporte`, clique em `Gerar Usuario de Suporte`. O sistema cria um usuario de suporte com senha, exibidos na tela com botao de copiar e a data de criacao. Informe as credenciais ao suporte por escrito (a tela orienta: nao enviar print ou foto).

Nao existe prazo automatico de expiracao. Para encerrar o acesso, clique em `Excluir Usuario de Suporte` e confirme; a exclusao revoga o acesso imediatamente. So pode existir um usuario de suporte ativo por empresa.

---

### 101. Como consultar logs de atividades?
Acesse o menu: `Sistema > Logs de atividades`

O caminho foi confirmado no menu e a documentacao de logs existe no projeto. Use essa area para consultar auditoria/atividades quando for necessario entender alteracoes feitas no sistema.

---

### 102. O que e um fornecedor investidor?
Um fornecedor investidor e um fornecedor que disponibiliza veiculos para a locadora operar e recebe repasses/comissoes conforme as regras configuradas no sistema.

Acesse o menu: `Empresa > Fornecedores`

No cadastro do fornecedor, marque a opcao `E Investidor?`. Com essa opcao ativa, o fornecedor pode ser vinculado aos veiculos e passa a participar dos calculos de comissao de investidor.

---

### 103. Onde configuro que um fornecedor e investidor?
Acesse o menu: `Empresa > Fornecedores`

Abra o cadastro do fornecedor e marque a opcao `E Investidor?`. Depois, preencha os dados de pagamento, como PIX, dados bancarios ou conta/wallet de split, quando aplicavel.

Para que o sistema gere comissao, o fornecedor investidor tambem precisa estar vinculado ao veiculo no cadastro do veiculo.

---

### 104. Como vincular um veiculo a um fornecedor investidor?
Acesse o menu: `Veiculos > Veiculos`

Abra o cadastro do veiculo e informe o fornecedor investidor no campo de fornecedor/investidor do veiculo. Esse vinculo indica que aquele veiculo pertence ou esta associado ao investidor informado.

Quando uma fatura vinculada a contrato ou locacao desse veiculo e paga, o sistema identifica o veiculo, identifica o investidor vinculado e calcula a comissao conforme a regra aplicavel.

---

### 105. O que e a Regra padrao do investidor?
A `Regra padrao` e a regra geral de comissao daquele investidor.

Use essa regra quando o investidor tiver a mesma negociacao para todos os veiculos dele, independentemente do grupo/categoria do veiculo.

Exemplo: se a regra padrao do investidor for `percentual da locadora` com valor `20%`, todos os veiculos desse investidor usam essa regra, exceto quando existir uma excecao por grupo cadastrada.

---

### 106. Para que serve a excecao por grupo nas regras de comissao do investidor?
A excecao por grupo serve quando o mesmo investidor tem uma negociacao diferente para um grupo especifico de veiculos.

Acesse o cadastro do fornecedor investidor e, na secao `Regras de comissao`, clique em `Adicionar excecao por grupo`. Depois selecione o grupo, o tipo de comissao e o valor.

Quando existir excecao para o grupo do veiculo, o sistema usa essa excecao antes da regra padrao do investidor.

---

### 107. Qual regra o sistema usa quando existe regra no investidor e tambem no grupo do veiculo?
O sistema usa a regra nesta ordem de prioridade:

1. Regra especifica do investidor para o grupo do veiculo.
2. Regra padrao do investidor.
3. Regra configurada no grupo do veiculo.

Ou seja, a regra do investidor pode sobrepor a regra do grupo. A regra do grupo continua funcionando como padrao quando o investidor nao tem uma regra especifica cadastrada.

---

### 108. Posso ter mais de um investidor no mesmo grupo de veiculos com percentuais diferentes?
Sim, desde que cada veiculo esteja vinculado ao seu respectivo fornecedor investidor e que as regras estejam configuradas no cadastro de cada investidor.

Mesmo que os veiculos estejam no mesmo grupo, o sistema pode aplicar uma regra diferente para cada investidor usando a `Regra padrao` do investidor ou uma `excecao por grupo`.

---

### 109. Quando a comissao do investidor e gerada?
A comissao por fatura e gerada quando uma receita de contrato ou locacao e marcada como paga.

Nesse momento, o sistema verifica o veiculo vinculado a fatura, identifica o fornecedor investidor do veiculo, resolve a regra de comissao aplicavel e cria a comissao com status `pendente`.

Comissoes mensais, quando configuradas, sao geradas pela rotina automatica mensal.

---

### 110. Alterar a regra de comissao muda comissoes antigas?
Nao. As comissoes ja geradas permanecem com os valores calculados no momento em que foram criadas.

Alterar a regra do grupo ou do fornecedor investidor afeta apenas novas comissoes geradas depois da alteracao. Se uma comissao antiga estiver incorreta, o caso deve ser avaliado operacionalmente ou pelo suporte.

---

### 111. Onde vejo as comissoes e repasses dos fornecedores investidores?
Acesse o menu: `Financeiro > Comissoes Investidores`

Nessa tela e possivel visualizar comissoes geradas, status, valores pendentes, valores pagos e acoes como marcar comissao como paga ou cancelar, conforme permissoes do usuario.

Tambem existe relatorio de fornecedor investidor em `Relatorios > Fornecedores > Investidor`, com resumo por investidor e detalhamento por veiculo.
