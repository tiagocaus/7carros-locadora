# Módulo de Orçamentos

## Objetivo

O módulo registra propostas comerciais de locações de curta duração antes da
reserva. Um orçamento não bloqueia veículo, não altera disponibilidade e não
cria lançamentos financeiros.

Fluxo:

```text
Rascunho -> Enviado -> Aceito -> Convertido em reserva
                    \-> Recusado
Qualquer orçamento fora da validade -> Expirado para exibição/conversão
```

## Precificação e snapshots

- O preço das diárias usa `GrupoPrecoPeriodoService`, incluindo preço
  progressivo e temporadas.
- Taxas e serviços são reconsultados no servidor e resolvidos pela filial de
  retirada. IDs, nomes, regras, quantidades e valores ficam gravados como
  snapshot em `orcamentos.taxas`.
- Promoções são validadas pelo canal `SIS` e o desconto fica congelado no
  orçamento.
- Alterações posteriores em grupos, temporadas, seguros ou taxas não reescrevem
  um orçamento salvo.
- O veículo específico é opcional e representa somente preferência operacional.

## Conversão em reserva

A conversão é transacional e idempotente:

1. Confirma tenant, acesso à filial, validade e estado do orçamento.
2. Exige conta/caixa e forma de pagamento, compatíveis com o cadastro da reserva.
3. Cria `locacoes` com status `R` e copia o snapshot comercial.
4. Cria o vínculo em `locacoes_veiculos`, permitindo `id_veiculo = NULL`.
5. Copia taxas para `locacoes_taxaseservicos`.
6. Marca o orçamento como convertido e vincula `id_locacao_convertida`.

O orçamento não pode ser convertido mais de uma vez. A conversão não cria
parcelas nem bloqueia o veículo preferencial.

## Status

| Código | Status | Descrição |
|---|---|---|
| `R` | Rascunho | Em elaboração |
| `E` | Enviado | Entregue ao cliente |
| `A` | Aceito | Aceite registrado pelo atendente |
| `N` | Recusado | Recusado pelo cliente |
| `X` | Expirado | Estado calculado quando a validade passou |
| `C` | Convertido | Reserva criada |

## Permissões

- `orcamentos.visualizar`
- `orcamentos.criar`
- `orcamentos.editar`
- `orcamentos.converter`
- `orcamentos.imprimir`
- `orcamentos.enviar`

## PDF

O PDF usa output buffering e `PdfHelper::outputInline()`. Deve sempre exibir a
validade e avisar que o orçamento não garante disponibilidade até sua conversão
em reserva.

O orçamento salvo pode ser enfileirado para e-mail, WhatsApp ou SMS. O envio
usa os contatos do cliente autorizados para o canal, as credenciais da filial e
o sistema central de mensageria. E-mail e WhatsApp levam o PDF; o SMS leva o
link do arquivo. Um envio bem-sucedido muda o status para `E`, exceto quando o
orçamento já estiver convertido.

## Multi-tenancy e filiais

Todas as consultas normais usam o filtro automático por `chave`. Listagens e
ações aplicam `FilialHelper` sobre a filial de retirada. Não use
`withoutChave()` neste módulo.
