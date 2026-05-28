### INVESTIDORES
- Locadoras cobram taxas em "Porcentagem" ou "Valor fixo mensal".

#### A INVESTIGAR 
- No processo de manutenção, garantir que o staatus de um veículo que esteja locado não seja alterado. Só permitir ir para oficina veículos com status de disponíveis, se estiver com outro, é necessário fazer a substituição do veículo ou dar baixa, para o veículo ficar como disponivel. Na substituição do veículo permitir enviar para oficina pelo campo "Acao para este veiculo".
- Quando tiver substituição veícular, criar um novo checklist digital.
- Impressão dos checklist não está boa, precisa ajudar.
- Tela de contrato, aba financeiro, ao clicar em "+ Adicionar Parcela Avulsa", está aparecendo um prompt, isso ta errado, tem que ser algo moderno seguindo padrao do sistema. Qual sua sugestão para isso?

### SUGESTÕES DE CLIENTES
- Criar um webhook para o sistema enviar notificações POST para determinada URL, quando tiver fatura vencida, fatura paga.
- Plataformas de rastreamento (https://docs.iopgps.com e https://gpswox.stoplight.io/docs/tracking-software/ct4qfhw15b2hc-tracking-software)
- Plano de fidelidade
- Lançar despesas do "Sem Parar", no contrato e locação.precisamos de uma função nova, temos que poder lançar as despesas do SEM PARAR no contrato
- Integração com TAG CRX
- Estatistica de uso do peneu. Ligado com manutenção e manutenção preventiva.
- Gerar fatura para seguradora.
- Importar clientes e veículos por planilha

----
### A FAZER
- teste 3  


### FRANQUIA
Vou pedir para fazer outra analize. Imadinando que um cliente, de locadora normal cresceu e quer mudar para o plano de franaquia; basicamente mudar a coluna "funcionario.plano de P* para F*", onde * seria um numero. Tudo funcionaria corretamente para ele?



### OUTROS
UPDATE usuarios SET funcao = 'proprietário' WHERE chave_mestre IS NOT NULL AND chave_mestre <> '';
UPDATE funcionarios SET id_role = 10 WHERE chave_mestre IS NOT NULL AND chave_mestre <> '';