Me ajude a montar um plano para criar um novo recurso. Vou dar mais detalhes:

Esse novo recurso/tela vai ser para cadastrar rastreadores veícular. Alguns funcionam com API e outros funcionam com GPRS.

Inicialmente eu queria operar dois recursos:
- Bloquear veiculo (comando de bloquar);
- Desbloquear veiculo (comando de desbloquear);

Como eu imagino:
- Tela para cadastrar as APIS ou GPRS dos rastreadores;
- Campo para colocar o comando de bloqueio e desbloqueio, assim eu não preciso me preocupar com o comando específico de cada rastreador;
- Botão para enviar o comando de bloqueio e desbloqueio. Acho que esse botão pode estar na tela do veículo "Aceito opnião sua", para caso a locadora queira fazer o bloqueio/desbloqueio manual.
- Ativar ou não o bloqueio/desbloqueio automático. Caso ativado, ter um input para informar quantos dias depois de uma fatura vencida o CRON vai executar o bloqueio. E quando acontecer o pagamento o CRON vai executar o desbloqueio.




Links de referencia de rastreadores, são de clientes, não é para seguir o padrão deles, apenas ver como eles se comportam para ajudar na criação:
- https://gpswox.stoplight.io/docs/tracking-software/ct4qfhw15b2hc-tracking-software
- https://servidor02.gpsrastreamento.com/ui/auth/login (LOGIN: locamotosipatinga@gmail.com SENHA: Calebe22@)
- https://github.com/3stecnologia/manual-consumo-dataexportapi-3stecnologia