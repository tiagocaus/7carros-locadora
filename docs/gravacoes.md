# Gravações de Tela

## Fluxo de captura

A gravação é iniciada no layout principal (`app.php`) para continuar ativa durante a navegação entre iframes. O navegador solicita o compartilhamento da tela ou aba com áudio e, em seguida, solicita obrigatoriamente o microfone.

- O áudio do microfone é sempre incluído.
- Quando o navegador disponibiliza o áudio da tela/aba compartilhada, ele é combinado com o microfone.
- O áudio da tela depende do navegador, sistema operacional e da opção marcada pelo usuário no seletor de compartilhamento.
- A duração máxima é de 5 minutos e o arquivo pode ter até 200 MB.

Ao parar, o arquivo é enviado diretamente ao servidor. Não deve existir download automático. Se o upload falhar, a interface mantém o arquivo em memória e oferece três ações explícitas: tentar novamente, salvar uma cópia local ou descartar.

## Upload em partes

O frontend divide o arquivo em blocos de 1 MB para funcionar também em hospedagens PHP-FPM cujo `upload_max_filesize` efetivo não é alterado pelas diretivas `php_value` do `.htaccess`:

1. `POST /api/gravacoes/uploads` cria a sessão de upload.
2. `POST /api/gravacoes/uploads/{uploadId}/chunks` envia cada bloco com seu índice.
3. `POST /api/gravacoes/uploads/{uploadId}/finalize` monta o arquivo, valida conteúdo e tamanho e cria o registro.
4. `POST /api/gravacoes/uploads/{uploadId}/cancelar` cancela e remove as partes temporárias.

Cada bloco pode ser reenviado com segurança e possui até três tentativas automáticas. As partes ficam em `storage/temp/gravacoes/{chave}/{uploadId}` e uploads abandonados são removidos após 24 horas pelo job de limpeza. As gravações concluídas seguem a retenção normal de 30 dias.

As operações de filesystem do upload capturam seus próprios warnings e os convertem em erros tratados pela API. O handler global de produção deve respeitar o nível de `error_reporting()`, inclusive o operador `@`, para não transformar uma limpeza opcional de arquivo temporário em uma resposta HTML 500.

## Atualização e compartilhamento

Depois da conclusão, o gravador envia o evento `screenRecordingSaved` ao iframe que iniciou a captura. A tela **Gravações de Tela** volta para a primeira página e recarrega a listagem.

Tanto o botão **Play** quanto o link compartilhável usam exclusivamente a rota segura `/files/{token}`. A reprodução não utiliza nem expõe o ID do registro. Arquivos de vídeo são servidos como conteúdo `inline`, com MIME de vídeo e suporte a requisições HTTP Range para reprodução progressiva.

A exclusão de uma gravação usa `POST /api/gravacoes/{id}/excluir`. O módulo não utiliza o método HTTP `DELETE`, pois algumas hospedagens o bloqueiam antes que a requisição alcance o PHP.

## Limites e segurança

- Tipos aceitos: WebM, MP4 e Matroska reconhecidos pelo conteúdo do arquivo.
- Tamanho máximo total: 200 MB.
- Tamanho de cada bloco: 1 MB.
- IDs de upload são aleatórios e isolados pelo `chave` do tenant.
- A criação do registro usa o Model `Gravacao`; não há acesso direto ao banco no Controller.
