# Modulo de Sinistros

## Objetivo

Registrar sinistros ocorridos durante contratos e locacoes, mantendo o evento
operacional separado da cobranca financeira opcional ao cliente.

## Interface

A aba **Sinistros** aparece imediatamente depois de **Taxas** nos formularios de
contratos e locacoes. Em registros ainda nao salvos, a aba orienta o usuario a
salvar antes de registrar um sinistro.

Campos do cadastro simples:

- Data e hora (obrigatorio)
- Veiculo atual ou historico do vinculo (obrigatorio)
- Tipo (obrigatorio): colisao/acidente, furto/roubo, incendio, alagamento,
  danos a terceiros, perda total ou outros
- Descricao (obrigatorio)
- Valor estimado (opcional)
- Observacoes (opcional)
- Status: aberto ou concluido

Local, fotos, documentos, boletim de ocorrencia, seguradora, apolice, franquia e
fluxo de reparo nao fazem parte desta versao.

## Banco de Dados

`sinistros` e tenant-scoped por `chave` e possui exatamente um vinculo preenchido:
`id_contrato` ou `id_locacao`. `id_veiculo` deve existir no historico de veiculos
do respectivo vinculo. `id_financeiro` e opcional e unico.

Consultas e gravacoes usam `Sinistro` com QueryBuilder normal. Nunca use
`withoutChave()` neste fluxo.

## Integracao Financeira

O checkbox **Gerar cobranca para o cliente** e opcional e inicia desmarcado.
Quando marcado, exige valor, vencimento, conta bancaria, forma de pagamento e a
permissao `financeiro.criar`.

A receita usa a hierarquia `4.2.2.05` (**Sinistros**) e recebe os
vinculos de cliente, veiculo, contrato ou locacao. Sinistro e cobranca sao
criados na mesma transacao. Se o sinistro for salvo sem cobranca, ela pode ser
gerada posteriormente uma unica vez.

Editar o valor estimado nao altera uma cobranca ja criada.

## API

```text
GET  /api/sinistros?vinculo=contrato&id_vinculo={id}
GET  /api/sinistros?vinculo=locacao&id_vinculo={id}
POST /api/sinistros
PUT  /api/sinistros/{id}
DELETE /api/sinistros/{id}
POST /api/sinistros/{id}/gerar-cobranca
```

Visualizacao e edicao respeitam as permissoes e o acesso a filial do contrato
ou locacao. Gerar cobranca exige adicionalmente `financeiro.criar`.

No frontend, `PUT` e `DELETE` devem ser enviados com `API.post()` e method
spoofing (`_method`), conforme `docs/api.md`.

## Exclusao e auditoria

- Excluir um sinistro exige `contratos.editar` ou `locacoes.editar`, conforme o
  vinculo, e respeita o acesso a filial.
- Quando existe cobranca vinculada, a operacao exige adicionalmente
  `financeiro.excluir` e remove o sinistro e a cobranca na mesma transacao.
- Cobrancas pagas bloqueiam a exclusao. O usuario deve estornar a cobranca
  antes de excluir o sinistro.
- Os bloqueios normais do financeiro continuam validos, incluindo promissorias
  vinculadas.
- Sinistro, cobranca e auditoria usam a mesma conexao e transacao. Qualquer
  falha desfaz toda a operacao.
- O log registra os dados anteriores do sinistro e, quando aplicavel, da
  cobranca vinculada, agrupados separadamente em `campos_alterados`.

## Relatorio

O relatorio operacional **Sinistros** usa `sinistros` como fonte de
verdade. A classificacao antiga baseada na quantidade de itens problematicos
do checklist nao deve ser reutilizada.

## Separacao contabil entre avarias e sinistros

- `4.2.2.01` e exclusivo para receitas de **Avarias**.
- `4.2.2.05` e exclusivo para receitas de **Sinistros**.
- Um sinistro nunca deve reutilizar o plano de avarias, mesmo quando possuir
  valor estimado ou cobranca ao cliente.
- A migration de separacao reclassifica somente lancamentos ligados por
  `sinistros.id_financeiro`; os demais registros historicos de `4.2.2.01`
  permanecem como avarias.
- As URLs e a permissao tecnica `relatorios.operacional.avarias_sinistros`
  permanecem legadas por compatibilidade, mas o nome exibido e **Sinistros**.
