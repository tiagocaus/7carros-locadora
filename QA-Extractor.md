# Extração de perguntas e respostas

Analise todo o histórico disponível neste terminal e extraia somente dúvidas operacionais que tenham evidência clara de que poderiam compor uma central de ajuda para usuários finais do sistema.

O histórico do terminal contém principalmente trabalho técnico. Portanto, descarte o conteúdo por padrão. Não tente aproveitar cada assunto encontrado e não transforme automaticamente funcionalidades, alterações ou correções em perguntas.

Uma pergunta só pode ser incluída quando o histórico contiver informação suficiente e explicitamente voltada ao uso do sistema, como:

- uma dúvida operacional feita ou simulada do ponto de vista do usuário final;
- uma explicação clara de como executar uma tarefa pela interface;
- uma regra de negócio estável que o usuário precise conhecer para utilizar o sistema;
- a descrição completa de um comportamento visível que seja útil em uma central de ajuda.

O simples fato de uma funcionalidade ter sido criada, alterada, corrigida ou testada não torna o assunto elegível. Nomes de módulos, botões, relatórios, notificações ou e-mails mencionados durante uma implementação também não são evidência suficiente.

Não crie perguntas e respostas sobre:

- código-fonte, classes, métodos, funções, variáveis ou arquivos internos;
- banco de dados, tabelas, colunas, queries, migrations ou detalhes de persistência;
- APIs, endpoints, payloads, integrações internas ou formatos técnicos;
- Git, commits, branches, deploy, terminal, comandos, testes, logs ou depuração;
- arquitetura, padrões de projeto, bibliotecas, dependências, servidores ou infraestrutura;
- detalhes técnicos de implementação, correções internas ou decisões tomadas durante o desenvolvimento;
- instruções destinadas a programadores, administradores de infraestrutura ou à equipe técnica.
- o que foi implementado, corrigido, ajustado, traduzido ou passou a funcionar;
- resultados de testes ou confirmações de que determinada correção funciona;
- possíveis dúvidas apenas imaginadas a partir de código, planos, tarefas ou alterações;
- listas de destinatários, estados, regras ou comportamentos descobertos apenas durante análise técnica, sem contexto explícito de orientação ao usuário.

Não converta informações técnicas em “consequências visíveis ao usuário”. Não crie uma pergunta apenas para reaproveitar uma informação técnica omitindo os detalhes de implementação. Se for necessário deduzir qual seria a dúvida do usuário, descarte o conteúdo.

Antes de incluir uma pergunta, aplique este teste:

1. A dúvida existe no histórico em contexto de uso, suporte ou documentação para usuário final?
2. A resposta ensina algo que o usuário pode fazer ou precisa saber para operar o sistema?
3. A resposta é independente da implementação, da correção e do momento específico do desenvolvimento?
4. Há informações completas e confirmadas para responder sem inferências?

Inclua a pergunta somente se todas as respostas forem “sim”. Em caso de dúvida, não inclua. É aceitável não encontrar nenhuma pergunta válida.

Para cada dúvida:

1. Escreva uma pergunta clara, como um usuário real faria.
2. Responda de forma objetiva, usando somente informações confirmadas no histórico.
3. Inclua passos de uso quando estiverem documentados.
4. Coloque entre crases qualquer caminho de navegação, tela, botão, opção ou campo, por exemplo: `Financeiro > Lançamentos > Editar > Lançamento Pago`.
5. Preserve entre crases apenas nomes técnicos que apareçam na interface e sejam úteis ao usuário final.
6. Não invente telas, caminhos, comportamentos ou configurações. Se não houver informação suficiente para uma resposta segura, não inclua a pergunta.
7. Evite perguntas repetidas e agrupe variações com a mesma resposta.

Use este formato:

## [Assunto]

### [Pergunta]
[Resposta]

Gere um novo arquivo Markdown com o resultado, usando no nome a data e a hora da execução no formato `QA-AAAA-MM-DD HH:MM.md`, por exemplo: `QA-2025-07-15 20:50.md`.

Não envie ao FTP o arquivo `QA-AAAA-MM-DD HH:MM.md` gerado. Ele deve permanecer somente no ambiente local.

No arquivo gerado, entregue apenas as perguntas e respostas finais, organizadas por assunto, sem relatar o processo de análise.

Se nenhuma pergunta atender integralmente aos critérios, não crie o arquivo e apenas informe que o histórico não contém dúvidas de usuários finais qualificadas.
