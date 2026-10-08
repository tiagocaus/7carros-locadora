# Seeds do Changelog

Este documento define como transformar as alteracoes enviadas ao GitHub em notas
publicas do Changelog e como registrar essas notas por migration.

O Changelog aparece para clientes na tela de login e dentro do sistema. Portanto,
ele nao e uma copia do historico tecnico do Git: cada item deve comunicar uma
entrega compreensivel e relevante.

## Referencia atual

O primeiro seed versionado por este processo e:

- migration: `app/Database/migrations/00413_seed_changelog_7_6_0.php`;
- versao: `7.6.0`;
- commit que adicionou a migration: `0e96aafd6b0a6ca1228f77a635349a75994bb0eb`;
- periodo das notas: `2026-06-30` a `2026-07-28`;
- quantidade: 48 notas, produzidas a partir da analise de 62 commits;
- ultima data gravada no banco local: `2026-07-28`.

Para o proximo seed, o intervalo inicial e
`0e96aafd6b0a6ca1228f77a635349a75994bb0eb..main/main`. A primeira data esperada
para novas notas e `2026-07-29`, mas o hash, e nao a data, e o cursor principal.

> A data seguinte a `2026-06-30` e `2026-07-01`. Nao existe `31/06`.

## Regras obrigatorias

1. Analisar somente commits que ja estejam no branch remoto oficial `main/main`.
2. Usar o commit que criou o seed anterior como inicio exclusivo do intervalo.
3. Usar a maior `data` ja registrada apenas como conferencia do intervalo.
4. Nao copiar a mensagem do commit automaticamente. Inspecionar diff,
   documentacao e testes quando a mensagem for extensa, tecnica ou ambigua.
5. Incluir somente entregas classificaveis como `Novo`, `Aprimorado` ou
   `Correcao` conforme os criterios deste documento.
6. Consolidar commits da mesma entrega e separar resultados independentes que
   estejam misturados no mesmo commit.
7. Escrever para o cliente, descrevendo o resultado e nao a implementacao.
8. Criar o seed como migration e executa-lo somente pelo `migrate.php` da raiz.

## Determinacao do intervalo

### 1. Atualizar as referencias do GitHub

```bash
git fetch main
git status --short
git branch -vv
```

O repositorio deve estar na branch `main`, e `main/main` deve apontar para o
estado remoto que sera analisado. Alteracoes locais nao commitadas nao fazem
parte do Changelog.

Capture o hash final antes de iniciar a analise. Ele torna o levantamento
reproduzivel mesmo que novos commits sejam enviados durante o trabalho:

```bash
git rev-parse main/main
```

Use esse resultado como `<hash-final>` nos demais comandos.

### 2. Conferir o banco local

Antes de criar uma migration, conecte ao banco definido em `.env.development`,
confirme que `DB_HOST=localhost` e execute:

```sql
DESCRIBE changelog;

SELECT MAX(data) AS ultima_data
FROM changelog;

SELECT versao, MIN(data) AS inicio, MAX(data) AS fim, COUNT(*) AS total
FROM changelog
GROUP BY versao
ORDER BY MAX(data) DESC, versao DESC;
```

O schema esperado atualmente e:

| Coluna | Tipo | Nulo | Observacao |
|---|---|---|---|
| `id` | `int(11)` | nao | chave primaria, auto incremento |
| `versao` | `char(20)` | nao | versao publicada |
| `tipo` | `char(1)` | nao | `N`, `A` ou `C` |
| `data` | `date` | nao | data da entrega |
| `mensagem` | `varchar(255)` | nao | texto publico |
| `created_at` | `timestamp` | nao | preenchido pelo banco |
| `updated_at` | `datetime` | sim | atualizado pelo banco |

Se o schema local divergir, interrompa a criacao do seed e investigue antes de
continuar. Consulte [database.md](database.md).

### 3. Localizar o cursor anterior

Identifique a migration do seed da ultima versao e o commit que a adicionou:

```bash
git log --all --diff-filter=A --date=short \
  --format='%H %ad %s' -- \
  app/Database/migrations/00413_seed_changelog_7_6_0.php
```

Para seeds futuros, substitua o caminho pelo arquivo da ultima versao. O hash
retornado e o `<hash-inicial>` e fica fora do novo intervalo.

### 4. Listar os commits candidatos

```bash
git log --reverse --date=short \
  --format='%H%x09%ad%x09%s' \
  <hash-inicial>..<hash-final>
```

Para entender um candidato, use:

```bash
git show --stat <hash>
git show <hash> -- <caminho-relevante>
```

Nao use apenas `--since`. O filtro por data pode perder commits enviados mais
tarde no mesmo dia da ultima nota. A data seguinte a `MAX(data)` serve como
conferencia: se aparecer uma entrega anterior a ela, verifique se o seed passado
deixou algo de fora ou se a data do commit foi alterada.

## Selecao e classificacao

### Novo (`N`)

Use quando surge uma capacidade que antes nao existia para clientes ou para a
operacao relevante do produto.

Exemplos:

- novo relatorio ou novo modulo;
- novo gateway ou canal de integracao;
- nova acao disponivel em um fluxo existente;
- nova automacao operacional com resultado relevante.

### Aprimorado (`A`)

Use quando uma capacidade existente fica objetivamente melhor, sem representar
a correcao de um defeito.

Exemplos:

- fluxo mais simples ou informacoes mais claras;
- ganho relevante de desempenho;
- reforco de seguranca ou confiabilidade;
- automacao ou validacao adicional;
- ampliacao de filtros, opcoes ou dados apresentados.

### Correcao (`C`)

Use quando um comportamento incorreto deixa de ocorrer.

Exemplos:

- calculo ou total incorreto;
- registro salvo com informacao errada;
- permissao, filtro ou isolamento aplicado incorretamente;
- falha de integracao, impressao ou notificacao;
- fluxo que nao concluia ou apresentava resultado divergente do esperado.

### Nao incluir

Exclua itens que nao tenham impacto relevante de produto ou operacao, como:

- alteracao apenas em documentacao ou testes;
- formatacao, minificacao ou reorganizacao de arquivos;
- refatoracao interna sem mudanca observavel;
- renomeacao tecnica sem efeito no uso;
- limpeza de codigo ou remocao de arquivo obsoleto;
- ajuste de processo de desenvolvimento sem impacto no sistema publicado.

Dependencias, infraestrutura, logs, seguranca e manutencao podem entrar como
`Aprimorado` ou `Correcao` quando produzirem efeito operacional relevante. Nao
devem entrar apenas por terem sido modificados.

## Analise alem da mensagem do commit

A mensagem do commit e uma pista, nao a fonte final. Para cada candidato:

1. leia a mensagem completa;
2. confira os arquivos modificados com `git show --stat`;
3. abra os diffs que determinam o comportamento;
4. consulte a documentacao funcional alterada;
5. use os testes para confirmar o resultado esperado;
6. registre a decisao em uma tabela de trabalho.

Modelo da tabela de trabalho:

| Commit(s) | Incluir? | Tipo | Mensagem proposta | Motivo da exclusao |
|---|---|---|---|---|
| `abc1234` | sim | `N` | Nova capacidade descrita para o cliente. | — |
| `def5678` | nao | — | — | Refatoracao interna sem efeito observavel. |

Um commit pode gerar mais de uma nota quando entrega resultados independentes.
Varios commits da mesma funcionalidade devem virar uma unica nota para evitar
repeticao. Nesse caso, use a data do ultimo commit consolidado.

## Redacao das notas

- Escreva uma frase curta, objetiva e completa.
- Limite cada mensagem a 255 caracteres, conforme o schema e a validacao do
  Model `Changelog`.
- Descreva o beneficio ou comportamento entregue.
- Evite nomes de classes, metodos, tabelas, migrations e detalhes de framework.
- Evite promessas vagas como "melhorias gerais" ou "diversas correcoes".
- Nao exponha vulnerabilidades, credenciais, dados de clientes ou detalhes que
  facilitem exploracao de seguranca.
- Use um unico resultado principal por nota.

Exemplo tecnico inadequado:

> Refatora `LocacaoVeiculo::listar()` e altera o `where` do status para `A`.

Exemplo publico:

> A consulta de veiculos em locacoes passou a considerar corretamente apenas
> locacoes ativas.

## Inferencia da versao

Parta da versao mais recente do Changelog e aplique a primeira regra compativel:

1. **Major (`X.0.0`)**: existe quebra de compatibilidade que exige coordenacao de
   clientes, integracoes ou operacao. Incrementar o primeiro numero e zerar os
   demais.
2. **Minor (`X.Y.0`)**: nao ha quebra de compatibilidade, mas existe ao menos um
   item `Novo`. Incrementar o segundo numero e zerar o terceiro.
3. **Patch (`X.Y.Z`)**: existem apenas itens `Aprimorado` e/ou `Correcao`.
   Incrementar o terceiro numero.

Exemplos a partir de `7.6.0`:

- com quebra de compatibilidade: `8.0.0`;
- com ao menos uma novidade e sem quebra: `7.7.0`;
- somente melhorias e correcoes: `7.6.1`.

Uma migration de banco, por si so, nao caracteriza quebra de compatibilidade.
Se a analise nao identificar nenhuma nota elegivel, nao crie versao nem seed.

## Criacao da migration

Consulte [migrations.md](migrations.md), confirme o maior numero existente e use
o proximo numero estritamente sequencial:

```bash
ls app/Database/migrations/[0-9]*.php | sort | tail -1
```

Nome esperado:

```text
XXXXX_seed_changelog_X_Y_Z.php
```

O docblock deve registrar a origem da analise:

```php
/**
 * Registra as notas publicas da versao X.Y.Z.
 *
 * Git: <hash-inicial>..<hash-final>
 * Periodo das notas: YYYY-MM-DD a YYYY-MM-DD.
 * A tabela changelog e global e nao possui coluna chave.
 */
```

Use o mesmo formato estrutural de
`00413_seed_changelog_7_6_0.php`:

```php
private const VERSION = 'X.Y.Z';

private const ITEMS = [
    [
        'data' => 'YYYY-MM-DD',
        'tipo' => 'N',
        'mensagem' => 'Mensagem publica com no maximo 255 caracteres.',
    ],
];
```

No `up()`, para cada item:

1. consulte a tabela `changelog` com `withoutChave()`;
2. verifique a existencia pela combinacao exata de `versao`, `tipo`, `data` e
   `mensagem`;
3. insira apenas quando ainda nao existir;
4. informe a quantidade de registros inseridos.

`withoutChave()` e permitido aqui porque `changelog` e uma tabela global sem a
coluna `chave`. Nao adicione `where('chave', ...)`. Nao use SQL bruto quando o
QueryBuilder atender a operacao. Consulte [querybuilder.md](querybuilder.md).

O `down()` deve ser no-op. Notas do Changelog sao historico publicado e podem
ter sido criadas ou ajustadas antes da migration; o rollback nao deve apaga-las.

## Validacao antes da publicacao

1. Confirme que todos os commits do intervalo aparecem na tabela de trabalho.
2. Confira classificacao, consolidacao, datas e limite de 255 caracteres.
3. Verifique que o hash final ainda e o snapshot escolhido.
4. Valide a sintaxe:

   ```bash
   php -l app/Database/migrations/XXXXX_seed_changelog_X_Y_Z.php
   ```

5. Confira todas as migrations pendentes. O executor nao permite selecionar
   somente uma migration e nao possui dry-run.
6. Aplique no banco local pelo unico executor oficial:

   ```bash
   php migrate.php --env=development
   ```

7. Valide o resultado:

   ```sql
   SELECT versao, tipo, data, mensagem
   FROM changelog
   WHERE versao = 'X.Y.Z'
   ORDER BY data, id;
   ```

8. Compare a quantidade inserida com a quantidade aprovada na tabela de
   trabalho.
9. Nao teste rollback dessa migration para limpar os dados: o `down()` e
   intencionalmente no-op.
10. Em producao, execute todas as migrations pendentes exclusivamente pelo
    terminal do servidor:

    ```bash
    php migrate.php --env=production
    ```

## Checklist rapido

- [ ] `git fetch main` executado.
- [ ] Hash inicial localizado pela migration anterior.
- [ ] Hash final de `main/main` congelado.
- [ ] `DESCRIBE changelog` e `MAX(data)` conferidos no localhost.
- [ ] Todos os commits do intervalo analisados.
- [ ] Itens sem impacto relevante excluidos.
- [ ] Commits relacionados consolidados.
- [ ] Tipos `N`, `A` e `C` revisados.
- [ ] Mensagens publicas com ate 255 caracteres.
- [ ] Versao inferida pelas regras de impacto.
- [ ] Proximo numero sequencial de migration confirmado.
- [ ] QueryBuilder e protecao contra duplicidade utilizados.
- [ ] Hashes e periodo registrados no docblock.
- [ ] Sintaxe PHP validada.
- [ ] Migration aplicada e registros conferidos no banco local.

