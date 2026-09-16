# Relatório de avaliação de adequação à LGPD — 7Carros Locadora

Data da investigação: 16/09/2026.

Natureza: avaliação técnica e documental, com pesquisa em fontes oficiais, análise estática do código e inspeção de metadados do banco local. Não constitui certificação de conformidade nem parecer jurídico definitivo.

## 1. Conclusão e alcance

### 1.1. Situação geral

O sistema possui controles relevantes de segurança e documentos públicos de privacidade, mas as evidências examinadas ainda não sustentam uma declaração de adequação integral à LGPD. Foram identificadas falhas concretas de acesso e descarte, lacunas de transparência e processos organizacionais cuja execução não foi comprovada.

Isso não significa que todo tratamento realizado seja ilícito, nem que tenha ocorrido vazamento. A conformidade depende do software, das configurações efetivas, dos contratos, das decisões de cada locadora e da operação diária.

**SUGESTÃO:** tratar os achados deste relatório como um programa de adequação, com responsáveis, prioridades, evidências de correção e validação jurídica das bases legais; não anunciar conformidade integral antes dessa verificação.

**EXEMPLO PRATICO:** Hoje, este relatório mostra problemas que ainda precisam ser resolvidos. Depois da melhoria, cada problema terá uma pessoa responsável, uma data combinada e uma verificação mostrando que foi corrigido.

### 1.2. Método e limites

A investigação contou com três agentes auxiliares: segurança e isolamento; direitos dos titulares e transparência; inventário de dados e integrações. Foram examinados fluxos críticos, documentação, páginas públicas e dependências do projeto.

No MySQL local, acessado via terminal com a configuração de desenvolvimento e host `localhost`, foram consultadas estruturas de tabelas, chaves estrangeiras e triggers. Não foram consultados registros pessoais de clientes. Foram inspecionadas, entre outras, as estruturas de `clientes`, `assinaturas`, `messages_queue`, `serpro_consultas_log`, `clientes_cartoes`, `logs`, `security_logs`, `cliente_password_resets`, `contatos_emails`, `contatos_telefones` e `portal_audit_logs`.

Não houve pentest, acesso autenticado à produção, envio de mensagens, submissão de cadastros ou modificação de código durante a investigação. O código local possui alterações preexistentes; não foi demonstrado que corresponda exatamente à versão publicada. As referências de linha indicam a versão examinada e podem mudar.

**SUGESTÃO:** complementar esta avaliação com testes controlados em homologação, comparação da versão publicada e verificação operacional de permissões, backups, cron, infraestrutura e contratos, utilizando dados sintéticos.

**EXEMPLO PRATICO:** Hoje, analisamos os arquivos do projeto, mas não confirmamos todos os comportamentos no sistema que os clientes usam. Depois da melhoria, uma cópia separada do sistema será testada com clientes fictícios, por exemplo, para conferir se uma locadora consegue abrir documentos de outra.

### 1.3. Informação fornecida sobre o servidor

O responsável informou que o servidor está nos Estados Unidos, é administrado pela própria equipe e que a infraestrutura é considerada própria. Não foi confirmado se o equipamento é próprio em colocation, dedicado alugado ou VPS/nuvem, nem a empresa que fornece instalações/conectividade, os acessos de terceiros ou a localização dos backups.

Administrar o servidor não demonstra, isoladamente, ausência de terceiros ou de obrigações relacionadas ao tratamento no exterior. Também não se deve presumir a existência de um operador estrangeiro sem identificar a relação jurídica e o acesso efetivo. O enquadramento de transferência internacional precisa considerar os agentes envolvidos, o fluxo dos dados e eventual destinatário no exterior.

**SUGESTÃO:** documentar propriedade e localização da infraestrutura, pessoas jurídicas envolvidas, acessos administrativos, suporte físico/remoto, subcontratados e destinos dos backups; com esse mapa, definir o enquadramento jurídico e os instrumentos necessários, sem presumir que a administração própria dispense a análise.

**EXEMPLO PRATICO:** Hoje, sabemos que vocês administram o servidor nos EUA, mas não sabemos onde ficam todas as cópias de segurança nem quem pode acessar os equipamentos. Depois da melhoria, essas informações estarão registradas, incluindo quem cuida de cada parte e quem tem acesso.

## 2. Site institucional, contratos e transparência

### 2.1. Política de privacidade e canal existentes

Foram encontrados links públicos para [Política de Privacidade](https://www.7carros.com.br/privacidade), [Termos de Uso](https://www.7carros.com.br/termos) e [Contato](https://www.7carros.com.br/contato). A política menciona acesso, correção, exclusão, inadimplência, cancelamento e backups, e encaminha solicitações ao contato geral. Não foi testada a efetividade desse atendimento.

O texto examinado não detalha suficientemente hospedagem nos EUA, compartilhamentos, papéis das partes, cookies e duração do ciclo de backups. Não se deve confundir essa lacuna pública com prova de ausência de controles internos.

**SUGESTÃO:** atualizar o aviso para refletir os fluxos reais, distinguir visitantes, contratantes e clientes das locadoras, explicar finalidades e compartilhamentos, esclarecer tratamento no exterior e tornar o canal de direitos fácil de identificar; verificar seu funcionamento por um pedido simulado.

**EXEMPLO PRATICO:** Hoje, quem lê a política encontra informações gerais sobre o uso de seus dados. Depois da melhoria, encontrará explicações como: por que seu CPF é solicitado, com quem seus dados são compartilhados, onde são guardados e como pedir uma cópia ou correção.

### 2.2. Relação contratual com as locadoras

Os termos públicos permitem contestação e cancelamento mesmo sem acesso ao painel, mas não foi localizado neles um acordo operacional completo de tratamento de dados. Também contêm exclusões amplas de responsabilidade por acessos indevidos e perdas. Não foi verificado se existem contratos complementares privados. [Termos publicados](https://www.7carros.com.br/termos).

Cláusulas contratuais não afastam automaticamente a responsabilidade prevista na LGPD. [LGPD, arts. 42–45](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709compilado.htm).

**SUGESTÃO:** revisar os termos com assessoria jurídica e formalizar instruções de tratamento, responsabilidades, confidencialidade, fornecedores autorizados, cooperação em direitos/incidentes, devolução e eliminação, evidências de segurança e regras de saída do serviço.

**EXEMPLO PRATICO:** Hoje, os termos publicados não deixam suficientemente claro quem faz o quê quando um cliente da locadora pede a exclusão de seus dados. Depois da melhoria, o contrato explicará quem recebe o pedido, quem decide o que pode ser apagado e como a 7Carros ajuda a executar a decisão.

### 2.3. Regra de inadimplência, cancelamento e backups

A política pública prevê continuidade operacional durante inadimplência, encerramento após 400 dias, eliminação após cancelamento e exceções legais, com backups residuais isolados até sobrescrita. O prazo de 400 dias é uma escolha contratual, não um prazo geral da LGPD. [Política publicada](https://www.7carros.com.br/privacidade).

O código de término existe em `app/Services/TenantProvisioningService.php:308`, com limpeza de uploads/certificados a partir da linha 456. Não foi localizado nesse repositório o agendamento dos 400 dias; `docs/whmcs.md` atribui o ciclo de vida ao WHMCS. A execução externa e o ciclo de backups não foram comprovados.

**SUGESTÃO:** validar o agendamento no WHMCS, as notificações e a possibilidade de interrupção das automações; comprovar o prazo e a execução da eliminação, definir o ciclo de backups e impedir que uma restauração reative dados já destinados à exclusão.

**EXEMPLO PRATICO:** Hoje, a política promete excluir dados após o cancelamento, mas não confirmamos como isso acontece em todas as cópias de segurança. Depois da melhoria, o cancelamento terá um registro do que foi apagado, do que precisa ser conservado e de quando as cópias restantes serão removidas; restaurar uma cópia antiga não fará esses dados voltarem ao uso normal.

### 2.4. Conteúdo jurídico incorreto no blog

O [artigo sobre uma nova lei de locação](https://www.7carros.com.br/blog/nova-lei-locacao-veiculos-mudancas-2024) atribui regras à “Lei 14.789/2024” e afirma obrigatoriedade de DPO para empresas com mais de 50 veículos. A [Lei 14.789 é de 2023 e trata de crédito fiscal de subvenções](https://www.planalto.gov.br/ccivil_03/_ato2023-2026/2023/lei/l14789.htm). O critério de 50 veículos não consta do regime de pequeno porte consultado.

**SUGESTÃO:** submeter o artigo à revisão jurídica, corrigir ou retirar as afirmações sem fundamento e revisar outros conteúdos legais publicados; não usar esse artigo como especificação do sistema ou orientação de conformidade.

**EXEMPLO PRATICO:** Hoje, o blog diz que uma locadora com mais de 50 veículos precisa de um encarregado de dados e cita uma lei que trata de outro assunto. Depois da melhoria, o texto apresentará as regras conferidas por um profissional e os links das normas que realmente sustentam a orientação.

## 3. Aplicação da LGPD ao negócio

### 3.1. Papéis de controlador e operador

A hipótese inicial é que a locadora controla o tratamento relativo aos seus clientes e contratos, enquanto a 7Carros opera esses dados conforme instruções. A 7Carros tende a atuar como controladora de suas próprias cobranças, relacionamento comercial, suporte e marketing. O papel de cada fornecedor depende das decisões efetivamente tomadas e do serviço prestado. [Orientação da ANPD](https://www.gov.br/anpd/pt-br/assuntos/titular-de-dados).

**SUGESTÃO:** registrar esses papéis por operação, validar situações em que a 7Carros define finalidades próprias e alinhar contratos, avisos e instruções internas a essa classificação.

**EXEMPLO PRATICO:** Hoje, a divisão de responsabilidades precisa ser confirmada: a locadora cadastra o motorista e a 7Carros guarda os dados no sistema. Depois da melhoria, ficará escrito que a locadora decide o uso desses dados para a locação e quais tarefas a 7Carros executa em seu nome, separando isso dos dados que a própria 7Carros usa para cobrar sua assinatura.

### 3.2. Tratamento nos EUA e transferência internacional

Na relação oficial consultada, os EUA não possuem decisão de adequação da ANPD. Reconhecimentos emitidos por outros países não substituem a decisão brasileira. A ANPD prevê mecanismos como cláusulas-padrão contratuais; a existência de servidor no exterior exige mapear a operação e seus agentes antes de escolher o mecanismo. [Orientação oficial](https://www.gov.br/anpd/pt-br/assuntos/assuntos-internacionais/transferencia-internacional-de-dados).

O prazo de 12 meses previsto para incorporação das cláusulas-padrão às transferências amparadas em cláusulas contratuais já terminou. O regulamento também estabelece transparência sobre país, finalidade e responsabilidades. Não foi apresentado instrumento que comprove o mecanismo aplicável à operação. [Resolução 19/2024](https://www.gov.br/anpd/pt-br/acesso-a-informacao/institucional/atos-normativos/regulamentacoes_anpd/resolucao-cd-anpd-no-19-de-23-de-agosto-de-2024).

**SUGESTÃO:** identificar exportador, eventual importador e transferências posteriores; verificar o mecanismo válido para cada fluxo e, quando cabível, formalizar as cláusulas-padrão da ANPD com o destinatário correto; publicar as informações exigíveis e incluir backups e acessos internacionais nessa avaliação.

**EXEMPLO PRATICO:** Hoje, sabemos que os dados ficam em um servidor nos EUA, mas ainda falta confirmar todas as empresas envolvidas e os documentos aplicáveis. Depois da melhoria, esse caminho estará explicado; se houver uma empresa estrangeira recebendo os dados, a relação terá o instrumento jurídico adequado e o aviso informará esse compartilhamento.

### 3.3. Inventário de dados pessoais

| Fluxo | Dados identificados no código | Evidência principal |
|---|---|---|
| Clientes e condutores | Identificação, CPF/CNPJ/passaporte, endereço, contatos, nascimento, foto, CNH e código de segurança, observações | `app/Controllers/ClientesController.php:519` |
| Funcionários | Identificação, contatos, endereço, documentos trabalhistas, CNH e salário | `app/Controllers/FuncionariosController.php:181` |
| Assinaturas | Imagem manuscrita, IP, navegador, coordenadas e vínculo contratual | `app/Controllers/AssinaturaController.php:148` |
| Checklists | Fotos, observações, assinatura e identificação dos envolvidos | `docs/checklists.md` |
| Pagamentos | Tokens, bandeira, últimos dígitos e referências do gateway; no fluxo Asaas o backend manipula cartão/CVV durante tokenização | `app/Models/ClienteCartao.php:85`; `app/Services/Gateways/AsaasGateway.php:669` |
| Mensageria | Destinatários, conteúdo, anexos/links e contexto contratual/financeiro | `app/Services/MessageQueueService.php:155` |
| SERPRO | Placas, infrações, CPF de indicado e payloads de consultas | `app/Services/SerproService.php:307` |
| Automação n8n | Contatos de proprietários de contas e identificação do tenant | `app/Services/N8nNovosClientesService.php:75` |
| Gravações | Tela/aba, áudio compartilhado e microfone | `public/assets/js/screen-recorder.js:68` |

Dados financeiros, fotos e assinaturas não são automaticamente dados sensíveis. A avaliação muda quando há conteúdo sensível ou tratamento biométrico relacionado à pessoa. Não foi identificado mecanismo de comparação biométrica nessa revisão. [Definições da ANPD](https://www.gov.br/anpd/pt-br/acesso-a-informacao/perguntas-frequentes).

**SUGESTÃO:** transformar o inventário em registro de operações com titular, finalidade, base legal, origem, campos necessários, destinatários, país, acesso, prazo e descarte; justificar especialmente código de segurança da CNH, dados trabalhistas, localização e gravação de áudio.

**EXEMPLO PRATICO:** Hoje, o cadastro permite guardar CPF, CNH, endereço e outros campos, mas este levantamento ainda não explica a necessidade de cada um. Depois da melhoria, haverá uma lista dizendo, por exemplo, para que a CNH é usada, quem pode vê-la, por quanto tempo será guardada e como será apagada.

### 3.4. Bases legais por finalidade

Hipóteses iniciais, sujeitas à validação jurídica e ao contexto concreto:

| Finalidade | Base a avaliar |
|---|---|
| Reserva e execução da locação | Contrato e procedimentos preliminares solicitados pelo titular |
| Obrigações fiscais e de trânsito | Obrigação legal ou regulatória específica |
| Preservação de provas | Exercício regular de direitos |
| Segurança e prevenção de abuso | Base pertinente, considerando necessidade e salvaguardas |
| Marketing e rastreamento | Consentimento ou outra hipótese demonstrável, conforme a operação |
| Dados adicionais e gravações | Análise individual de finalidade, necessidade e proporcionalidade |

Consentimento não é exigido para todo cadastro. A base contratual também não autoriza qualquer coleta acessória. [LGPD, arts. 6º–11](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709compilado.htm). Quando houver legítimo interesse, deve-se avaliar finalidade, necessidade e equilíbrio com direitos do titular. [Guia da ANPD](https://www.gov.br/anpd/pt-br/assuntos/noticias/anpd-lanca-guia-orientativo-sobre-legitimo-interesse).

**SUGESTÃO:** atribuir e documentar a base por finalidade, registrar o teste de balanceamento quando pertinente e reservar consentimento para os tratamentos em que seja adequado, com prova e mecanismo de revogação.

**EXEMPLO PRATICO:** Hoje, ainda falta registrar a justificativa de cada uso dos dados. Depois da melhoria, os dados necessários à locação terão sua justificativa documentada e, se o envio de promoções depender de autorização, o cliente poderá recusá-lo sem perder a reserva.

## 4. Achados técnicos

As prioridades abaixo expressam risco técnico e urgência de investigação/correção, não uma decisão administrativa de infração. As medidas sugeridas devem ser verificadas em homologação antes da publicação.

### 4.1. Links permanentes para documentos privados — alta prioridade

`app/Routes/web.php:109` expõe `/files/{token}` publicamente. `app/Helpers/FileHelper.php:132` gera token determinístico sem expiração ou revogação individual. `app/Controllers/FileController.php:26` valida o token sem conferir sessão/permissão do solicitante e, na linha 73, permite cache público por um ano. Anexos de clientes, assinaturas e gravações utilizam esse fluxo.

O HMAC dificulta fabricar um link válido; o risco é o acesso por qualquer portador de um link compartilhado ou vazado. A assinatura do token não equivale à autorização de quem acessa. `docs/file-helper.md` documenta a rota pública, portanto a inadequação para documentos privados é também uma questão de desenho, não apenas de implementação divergente.

**SUGESTÃO:** classificar arquivos públicos e privados; exigir autorização por objeto, tenant e filial nos privados; usar links temporários e revogáveis para compartilhamentos necessários e respostas sem cache público para documentos pessoais, preservando os fluxos públicos legítimos.

**EXEMPLO PRATICO:** Hoje, se alguém encaminhar um link válido da CNH de um cliente, outra pessoa poderá abrir o documento mesmo sem entrar no sistema. Depois da melhoria, o acesso interno exigirá permissão; quando for necessário compartilhar, o link terá prazo para vencer e poderá ser cancelado.

### 4.2. Funcionário inativo mantém sessão aberta — alta prioridade

`app/Controllers/FuncionariosController.php:392` atualiza o funcionário sem revogar a sessão existente. `app/Core/Auth.php:318` verifica apenas a flag de autenticação; a consulta de permissões na linha 429 não exige status ativo. Existe cache de permissões por uma hora. O método `Auth::refresh()` verifica status, mas não foram encontrados chamadores no escopo pesquisado.

Login novo e remember-token verificam status; a deficiência apontada é a sessão já autenticada. O timeout de inatividade não resolve se a pessoa continua utilizando o sistema.

**SUGESTÃO:** revalidar no backend o estado do usuário ou uma versão de sessão e invalidar sessões, tokens e permissões ao desativar ou restringir acesso; testar com duas sessões simultâneas que a próxima requisição seja recusada.

**EXEMPLO PRATICO:** Hoje, você desativa um funcionário, mas ele pode continuar usando o sistema se já estiver conectado. Depois da melhoria, ao desativá-lo, o sistema bloqueará também o acesso que já estava aberto.

### 4.3. SVG ativo servido no domínio administrativo — alta prioridade

`app/Controllers/PerfilController.php:140` aceita foto diretamente por `FileHelper::save`. O helper admite SVG e o controller de arquivos o serve como imagem inline, sem sanitização específica identificada. Ao abrir o link como documento, conteúdo ativo pode executar na origem administrativa, salvo proteção adicional de infraestrutura não verificada.

Não se afirma execução automática em `<img>`. Não houve upload ou exploração; a validação auxiliar usou somente uma string sintética em memória.

**SUGESTÃO:** rejeitar SVG em fotos ou converter conteúdo permitido para formato raster por processamento seguro; impedir conteúdo ativo em uploads e avaliar domínio isolado e cabeçalhos restritivos para arquivos não confiáveis.

**EXEMPLO PRATICO:** Hoje, alguém pode enviar como foto um arquivo SVG que contenha comandos escondidos; ao abrir o link diretamente, esses comandos podem rodar no navegador, dependendo das proteções existentes. Depois da melhoria, esse arquivo será recusado ou transformado em uma imagem sem comandos executáveis.

### 4.4. Exclusão de cliente deixa contatos e não resolve retenção — alta prioridade

`app/Controllers/ClientesController.php:798` bloqueia exclusão quando há vínculos e sugere inativação. `app/Models/Cliente.php:512` considera vínculos históricos sem uma política de idade/encerramento. Há divergência entre a mensagem que menciona registros em aberto e consultas que não restringem todos os vínculos dessa forma.

O método `apagarRegistrosRelacionados`, na linha 968 do controller, remove arquivos e cartões, mas não contatos normalizados. O schema local confirmou ausência de FKs nessas tabelas e de triggers de limpeza em clientes/contatos. O fluxo pode deixar e-mails e telefones após remover o cadastro. Não foi feita exclusão experimental de dados reais.

**SUGESTÃO:** corrigir a limpeza dos contatos com escopo tenant e integridade transacional; separar exclusão operacional de atendimento LGPD; definir retenção por categoria e tratamento de vínculos encerrados, com conservação fundamentada quando necessária e eliminação/anonimização do restante.

**EXEMPLO PRATICO:** Hoje, você exclui o cliente, mas seu telefone e e-mail podem permanecer guardados. Depois da melhoria, esses contatos também serão apagados quando não houver motivo legal para conservá-los; um documento que precise permanecer terá motivo e prazo definidos.

### 4.5. Gravações acessíveis sem segregação específica — alta prioridade

`public/assets/js/screen-recorder.js:68` captura tela e microfone. `app/Controllers/GravacoesController.php:134` lista gravações e links sem permissão específica por papel/criador no fluxo examinado. `app/Models/Gravacao.php:26` isola o tenant, mas disponibiliza suas gravações em conjunto.

Uma gravação pode mostrar dados de telas que outro funcionário não teria permissão para abrir. Não foi identificado vazamento entre tenants nesse fluxo.

**SUGESTÃO:** estabelecer finalidade, acesso por papel/proprietário e auditoria; permitir gravação sem microfone quando suficiente, instruir o usuário a evitar dados de terceiros e utilizar compartilhamento temporário restrito ao atendimento necessário.

**EXEMPLO PRATICO:** Hoje, uma gravação feita por um funcionário pode mostrar documentos de clientes e ficar disponível para outros funcionários da mesma locadora. Depois da melhoria, somente as pessoas autorizadas para aquele atendimento poderão vê-la, e será possível gravar sem microfone quando o áudio não for necessário.

### 4.6. Falha no descarte físico de gravações — alta prioridade

Há retenção de 30 dias e limpeza de uploads incompletos. Entretanto, `app/Crons/Jobs/CleanupOldRecordingsJob.php:57` apenas registra aviso quando `unlink()` falha e ainda pode apagar a linha do banco na sequência. O arquivo órfão deixa de aparecer nas próximas buscas por registros antigos.

**SUGESTÃO:** manter estado rastreável de exclusão até confirmar a remoção física, repetir falhas, alertar o responsável e reconciliar arquivos órfãos; simular falha de permissão em homologação para comprovar que o descarte será concluído depois.

**EXEMPLO PRATICO:** Hoje, se o sistema tentar apagar uma gravação e falhar, pode retirar o registro da lista mesmo deixando o vídeo no servidor. Depois da melhoria, a gravação continuará marcada como pendente de exclusão, o sistema tentará novamente e avisará o responsável se não conseguir.

### 4.7. Cópias de dados em logs e mensageria — prioridade média/alta

`app/Services/SerproService.php:576` persiste conteúdo de respostas e requisições, com sanitização limitada. `app/Services/MessageQueueService.php:155` armazena payloads no banco e publica mensagens persistentes no RabbitMQ. Não foram localizadas rotinas específicas de expurgo para todos esses conjuntos nos jobs examinados.

A ausência de rotina localizada não prova retenção infinita em produção. Preferências de recebimento por canal existem, mas não equivalem por si só à prova completa de consentimento por finalidade.

**SUGESTÃO:** limitar payloads ao necessário, ocultar segredos/dados excessivos, restringir leitura e definir prazos distintos para mensagens pendentes, entregues, falhas e evidências; verificar também filas, tentativas e cópias mantidas pelos provedores.

**EXEMPLO PRATICO:** Hoje, uma mensagem de cobrança pode deixar cópias do telefone e do conteúdo enviado em diferentes lugares do sistema, sem que tenhamos confirmado a limpeza de todos eles. Depois da melhoria, será definido o que precisa ficar como comprovante e por quanto tempo, e as cópias desnecessárias serão apagadas.

### 4.8. Auditoria depende parcialmente do navegador — prioridade média

`app/Traits/Auditable.php:86` e `app/Services/AuditLogService.php:47` utilizam detalhes enviados em `_audit_changes`. O usuário autorizado pode omitir ou alterar parte desses detalhes. A filtragem de campos no frontend não substitui controle no servidor.

A mensagem básica da ação permanece. Não se deve generalizar a deficiência para toda a auditoria: `docs/logs.md` já descreve comparação confiável no servidor em fluxos específicos de locações e exclusão financeira.

**SUGESTÃO:** calcular alterações críticas no servidor, aplicar lista de campos permitidos nos logs e controlar sua retenção e acesso, preservando as evidências necessárias sem duplicar dados pessoais indiscriminadamente.

**EXEMPLO PRATICO:** Hoje, parte do histórico de alterações depende do que o navegador informa, e essa informação pode ser omitida ou modificada. Depois da melhoria, ao trocar um endereço, o próprio sistema conferirá o valor anterior e o novo e registrará a mudança de forma confiável, guardando apenas o necessário.

### 4.9. Localização precisa obrigatória na assinatura — prioridade média/alta

`app/Views/public/assinatura/index.php:861` impede assinatura sem localização; `app/Controllers/AssinaturaController.php:155` aceita coordenadas nulas. A obrigatoriedade descrita em `docs/assinaturas.md` como apoio à auditoria jurídica não demonstra obrigação legal de coletar localização precisa.

**SUGESTÃO:** validar a necessidade e a base legal dessa coleta, informar a finalidade e oferecer alternativa proporcional quando a localização não for indispensável; alinhar frontend, backend e documentação à decisão jurídica e de produto.

**EXEMPLO PRATICO:** Hoje, o cliente precisa permitir o acesso à sua localização para conseguir assinar pela tela. Depois da melhoria, ele verá para que a localização é usada e, quando ela não for indispensável, poderá concluir a assinatura sem compartilhá-la.

### 4.10. Transparência e rastreamento nos sites das locadoras — alta prioridade

`storage/templates/website/reserva.php:351` coleta identificação, contatos, endereço e documentos. Não foi localizada estrutura nativa suficiente para aviso próprio da locadora e atendimento ao titular. Os templates permitem customização, de modo que um aviso externo pode ter sido inserido em determinado site.

`storage/templates/website/includes/head.php:65` e `includes/footer.php:102` inserem códigos configuráveis sem bloqueio nativo por preferência. A exposição depende das tags e de eventual solução de consentimento instalada por customização. Nem todo cookie exige consentimento. [Guia da ANPD](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/anonimizado___guia_de_cookies.pdf).

**SUGESTÃO:** disponibilizar aviso contextual e política configurável por locadora; inventariar tags e bloquear previamente as que dependam de consentimento, permitindo recusa, alteração de preferências e revogação com facilidade equivalente.

**EXEMPLO PRATICO:** Hoje, se a locadora instalar um código de publicidade no site, ele pode carregar antes de o visitante escolher se aceita esse uso. Depois da melhoria, os recursos que dependerem de autorização só carregarão após a escolha, e o visitante poderá recusar ou mudar de ideia facilmente.

### 4.11. Webhook Asaas sem token é aceito — alta prioridade quando configurado assim

`app/Services/Gateways/AsaasGateway.php:84` permite token opcional; na linha 357, a validação retorna sucesso quando ele está vazio. `app/Controllers/PagamentoPublicoController.php:646` e linha 713 utilizam o validador antes de processar eventos financeiros.

O risco é condicionado à configuração e aos identificadores conhecidos, não uma afirmação de exposição de todos os tenants. Há conflito com a promessa geral de validação descrita em `docs/gateways.md`.

**SUGESTÃO:** rejeitar eventos quando a autenticação exigida não estiver configurada, validar o mecanismo oficial do provedor e revisar as configurações ativas; testar eventos válidos, inválidos e repetidos sem alterar cobranças reais.

**EXEMPLO PRATICO:** Hoje, se a chave de verificação do Asaas estiver vazia, o sistema aceita avisos de pagamento sem confirmar sua origem. Depois da melhoria, um aviso sem identificação válida será recusado e não poderá mudar uma cobrança para paga.

### 4.12. Dependências com avisos de segurança — alta prioridade para triagem

Na investigação, `composer audit --locked --no-interaction --format=json` retornou 22 avisos em seis bibliotecas: três altos, quinze médios e quatro baixos.

| Biblioteca | Versão no lock examinado | Avisos |
|---|---|---:|
| guzzlehttp/guzzle | 7.10.0 | 9 |
| guzzlehttp/psr7 | 2.8.0 | 4 |
| phpseclib/phpseclib | 3.0.48 | 4 |
| setasign/fpdi | 2.6.4 | 1 |
| symfony/http-foundation | 7.4.3 | 1 |
| symfony/yaml | 7.4.1 | 3 |

Esses números são um retrato da consulta e podem mudar. Um aviso no lock não demonstra explorabilidade pela aplicação nem a versão efetivamente instalada em produção.

**SUGESTÃO:** avaliar os caminhos atingíveis e priorizar atualizações compatíveis, executar regressões dos fluxos afetados e confirmar o inventário publicado; manter auditoria periódica e justificativa documentada para qualquer risco temporariamente aceito.

**EXEMPLO PRATICO:** Hoje, a lista de bibliotecas do projeto contém versões com avisos de segurança, mas ainda falta confirmar quais problemas podem atingir o sistema e quais versões estão no servidor. Depois da melhoria, essa conferência será feita, as atualizações necessárias serão testadas e qualquer pendência terá responsável e justificativa registrados.

### 4.13. Possíveis credenciais em documentação versionada — alta prioridade para verificação

Foram identificadas senhas literais em exemplos de `docs/overview.md`, `docs/database.md` e `docs/environment.md`. Não foi verificado se são válidas, e nenhum valor é reproduzido neste relatório.

**SUGESTÃO:** verificar sua origem sem expô-las, substituir exemplos por placeholders e revogar/rotacionar qualquer segredo que tenha sido real; considerar histórico Git e cópias, pois apagar o texto atual não invalida a credencial.

**EXEMPLO PRATICO:** Hoje, alguns exemplos da documentação contêm textos que parecem senhas, e não sabemos se já foram usados de verdade. Depois da melhoria, os exemplos usarão textos como SUA_SENHA; se alguma senha tiver sido real, ela será substituída para que cópias antigas não permitam acesso.

## 5. Direitos e governança operacional

### 5.1. Atendimento integral ao titular

O portal permite consultas e correções parciais por whitelist, mas não foi encontrado fluxo nativo completo para confirmação, acesso integral, informação sobre compartilhamentos, oposição, revogação ou eliminação. A ausência de automação não demonstra descumprimento se houver atendimento manual eficaz.

O art. 19 prevê confirmação/acesso simplificado imediato ou declaração completa em até 15 dias, ressalvadas regras aplicáveis. Esse não é um prazo universal para todos os pedidos. [LGPD, arts. 18–19](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709compilado.htm).

**SUGESTÃO:** instituir protocolo gratuito com verificação proporcional de identidade, responsável, prazo aplicável, decisão fundamentada e resposta segura; reunir dados entre módulos e comunicar providências aos destinatários quando exigível, preservando dados de outras pessoas.

**EXEMPLO PRATICO:** Hoje, o cliente encontra um contato para pedir seus dados, mas não confirmamos como o pedido é acompanhado até a resposta. Depois da melhoria, ao pedir uma cópia, sua identidade será conferida, ele receberá um número de atendimento e a resposta será enviada com segurança dentro do prazo aplicável.

### 5.2. Matriz de retenção e saída do serviço

Não foi localizada matriz geral que cubra cadastros, anexos, contratos, financeiro, mensagens, logs, gravações, backups e terceiros. O término de um tenant não equivale ao pedido individual de um cliente. A LGPD não estabelece um prazo único de conservação para todos os dados. [Orientação da ANPD](https://www.gov.br/anpd/pt-br/acesso-a-informacao/perguntas-frequentes).

O término implementado elimina dados por tenant e uploads, mas não comprova eliminação de todas as cópias externas, temporários, websites publicados ou backups.

**SUGESTÃO:** definir prazos e gatilhos por finalidade, fundamento de conservação, acesso restrito durante guarda legal, descarte verificável e exportação segura na saída; evitar tanto retenção indefinida quanto eliminação indiscriminada de provas necessárias.

**EXEMPLO PRATICO:** Hoje, marcar um cliente como inativo mantém seus dados, sem resolver sozinho por quanto tempo eles devem ficar guardados. Depois da melhoria, cada tipo de informação terá uma regra: um comprovante necessário será conservado pelo motivo e prazo definidos, enquanto dados sem necessidade serão apagados.

### 5.3. Incidentes de segurança

Não foi demonstrado procedimento operacional completo de resposta a incidentes. Incidentes sujeitos à comunicação têm, em regra, prazo de três dias úteis a partir do conhecimento pelo controlador de que afetaram dados pessoais, com particularidades para agentes elegíveis de pequeno porte. O registro de incidentes, inclusive os não comunicados, deve ser mantido por pelo menos cinco anos. [Resolução 15/2024](https://dspace.mj.gov.br/bitstream/1/12879/1/RES_ANPD_2024_15.pdf).

Encontrar vulnerabilidade não significa, por si só, que houve incidente notificável. [Orientação da ANPD](https://www.gov.br/anpd/pt-br/canais_atendimento/agente-de-tratamento/comunicado-de-incidente-de-seguranca-cis).

**SUGESTÃO:** formalizar detecção, contenção, preservação de evidências, avaliação de risco, comunicação entre 7Carros e locadoras e decisão de notificação; designar responsáveis/substitutos e testar o procedimento em simulação.

**EXEMPLO PRATICO:** Hoje, não foi demonstrado quem deve agir se uma CNH for enviada por engano à pessoa errada. Depois da melhoria, a equipe saberá quem avisar, como interromper o acesso, como registrar o ocorrido e como avaliar se precisa comunicar o cliente e a autoridade responsável.

### 5.4. Diferentes tipos de logs

O sistema configura 30 dias para logs de segurança. Isso não demonstra nem substitui a guarda de registros de acesso por seis meses exigida para provedores enquadrados no art. 15 do Marco Civil. Logs de auditoria, registros de acesso e registros de incidentes têm finalidades diferentes. A configuração do servidor não foi inspecionada. [Marco Civil da Internet](https://www.planalto.gov.br/ccivil_03/_ato2011-2014/2014/lei/l12965.htm).

**SUGESTÃO:** classificar os logs, confirmar os registros e prazos legalmente aplicáveis, restringir acesso e comprovar tanto preservação quanto descarte, sem aplicar uma regra única de 30 dias a todas as categorias.

**EXEMPLO PRATICO:** Hoje, há uma configuração de 30 dias para registros de segurança, mas isso não mostra como todos os outros históricos são guardados. Depois da melhoria, o registro de entrada no sistema, o histórico de alterações e o registro de um vazamento terão regras próprias de acesso e conservação.

### 5.5. Encarregado, canal e porte

Não foi identificada nas páginas examinadas a identificação nominal de encarregado. Agentes elegíveis de pequeno porte podem ser dispensados de indicá-lo, mas devem manter canal para titulares. A dispensa não pode ser presumida apenas por tamanho da frota, e o regime possui critérios e exceções. [Resolução 2/2022](https://www.gov.br/anpd/pt-br/acesso-a-informacao/institucional/atos-normativos/regulamentacoes_anpd/resolucao-cd-anpd-no-2-de-27-de-janeiro-de-2022).

A indicação, quando aplicável, deve observar formalização e atribuições. [Resolução 18/2024](https://www.gov.br/anpd/pt-br/acesso-a-informacao/institucional/atos-normativos/regulamentacoes_anpd/processo_integra_-resolucao_cd_anpd_no_18_2024.pdf).

**SUGESTÃO:** verificar o enquadramento da 7Carros e orientar cada locadora sobre sua própria avaliação; formalizar o encarregado quando exigível e manter canal funcional e equipe preparada em qualquer cenário.

**EXEMPLO PRATICO:** Hoje, existe um contato geral, mas ainda falta confirmar quem acompanha os pedidos de privacidade e se a empresa precisa indicar formalmente um encarregado. Depois da melhoria, haverá um responsável definido para acompanhar os pedidos e, quando exigido, o encarregado estará formalmente indicado e seu contato divulgado.

### 5.6. Fornecedores, integrações e treinamento

O repositório prevê gateways, SMTP, WhatsApp, SMS, RabbitMQ, SERPRO e n8n. A existência do código não comprova quais serviços estão ativos, onde processam dados ou quais acessos possuem. Contratos, treinamento e revisões periódicas de acesso não foram demonstrados pelo repositório.

**SUGESTÃO:** manter inventário dos fornecedores efetivos, finalidades, dados compartilhados, países e contratos; revisar acessos de suporte e administração, treinar a equipe e limitar o uso de dados reais em desenvolvimento e atendimento.

**EXEMPLO PRATICO:** Hoje, o código prevê serviços de pagamento e envio de mensagens, mas não confirmamos quais estão ativos nem todos os dados que recebem. Depois da melhoria, haverá uma lista atualizada; por exemplo, ela mostrará qual serviço recebe o telefone do cliente para enviar uma cobrança, e a equipe saberá como compartilhar somente o necessário.

## 6. Controles positivos e evidências de conclusão

### 6.1. Controles existentes a preservar

Foram encontrados isolamento por tenant no QueryBuilder, consultas parametrizadas, permissões e filtros de filial, senhas com Argon2id, CSRF, limitação de requisições, cookies de sessão protegidos, tokens de recuperação/remember armazenados por hash, tokenização de cartões e portal com restrições por perfil/titular. Há retenção de gravações e término de tenant com limpeza de arquivos.

Esses controles são relevantes, mas não certificam todos os endpoints nem substituem governança. A tokenização armazenada também não significa que o backend nunca manipule cartão completo.

**SUGESTÃO:** preservar esses mecanismos nas correções e demonstrar sua eficácia com testes de acesso indevido, isolamento entre tenants, revogação, descarte e proteção de dados em erros/logs.

**EXEMPLO PRATICO:** Hoje, já existem senhas protegidas e separação dos dados entre locadoras. Depois das melhorias, essas proteções continuarão funcionando e serão conferidas com testes, como tentar abrir pela conta da locadora A um documento exclusivo da locadora B e confirmar que o acesso é negado.

### 6.2. Prioridade de execução e aceite

A ordem recomendada é conter exposição de documentos, sessões e uploads; corrigir descarte e configurações inseguras; esclarecer o tratamento nos EUA; e alinhar contratos, avisos, retenção e atendimento ao funcionamento real.

O fechamento deve produzir evidências verificáveis: acesso negado entre tenants e após revogação; links privados expirados/revogados; descarte completo com repetição de falhas; bloqueio de tags quando necessário; pedido de titular atendido com segurança; contrato/mecanismo internacional aplicável; restauração que respeite exclusões; e simulação de incidente.

**SUGESTÃO:** abrir tarefas rastreáveis para cada achado, atribuir responsáveis técnicos e jurídicos e concluir cada item somente após comprovar o comportamento esperado; reavaliar a declaração de adequação com base nessas evidências, sem prometer conformidade apenas por implementar uma lista de funcionalidades.

**EXEMPLO PRATICO:** Hoje, os problemas estão descritos neste relatório, mas sua correção ainda precisa ser comprovada. Depois da melhoria, um item como bloquear funcionário desligado só será marcado como concluído depois de testar que ele perdeu o acesso mesmo com a tela aberta.

## 7. Documentos consultados

As fontes externas estão vinculadas junto das afirmações. A investigação utilizou a LGPD, o Marco Civil, resoluções e orientações oficiais da ANPD, páginas públicas da 7Carros e documentação do repositório. Normas e avisos de segurança devem ser reconferidos quando o trabalho de adequação for executado.

Documentos internos consultados pela equipe, integralmente ou em trechos relevantes ao escopo:

- [AGENTS.md](AGENTS.md).
- [Visão geral](docs/overview.md), [arquitetura](docs/architecture.md), [QueryBuilder](docs/querybuilder.md), [banco de dados](docs/database.md) e [ambiente](docs/environment.md).
- [Segurança](docs/security.md), [multi-tenancy](docs/multi-tenancy.md), [roles](docs/roles.md), [arquivos](docs/file-helper.md) e [logs](docs/logs.md).
- [Portal cliente/investidor](docs/portal-cliente-investidor.md), [website](docs/website.md) e [WHMCS](docs/whmcs.md).
- [Checklists](docs/checklists.md), [assinaturas](docs/assinaturas.md), [integrações](docs/integrations.md), [mensageria](docs/messaging.md) e [gravações](docs/gravacoes.md).
- Trechos pertinentes de [gateways](docs/gateways.md) e [cron](docs/cron.md).

**SUGESTÃO:** manter este relatório e a documentação operacional atualizados conforme as correções e decisões jurídicas, registrando divergências entre documentação, código e configuração efetiva.

**EXEMPLO PRATICO:** Hoje, o relatório registra o que foi encontrado na data da investigação. Depois de cada melhoria, o documento indicará o que mudou e o que continua pendente; por exemplo, informará que o acesso de funcionários desativados foi corrigido e como isso foi conferido.
