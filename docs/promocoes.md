# Promoções

## Regra de aplicação

O código promocional é validado exclusivamente no servidor pelo
`PromocaoAplicacaoService`. A promoção precisa:

- pertencer ao tenant atual e estar ativa;
- incluir o canal de uso exato (`SIS`, `SITE` ou `APP`);
- estar vinculada à filial de retirada;
- aceitar o grupo da reserva quando possuir restrição por grupo;
- atender à quantidade mínima de diárias;
- estar dentro da validade na data em que o código é aplicado, usando a data de
  negócio do tenant. A data final é inclusiva e `NULL` significa sem prazo.

Promoções `DPOR` aceitam percentual maior que zero e de até 100%. Promoções
`DFIX` usam obrigatoriamente o valor positivo de
`promocoes_valores_filiais` correspondente à filial; `promocoes.valor` é apenas
fallback de listagem/compatibilidade.

O desconto incide sobre `total_fatura`, depois de diárias, seguros e serviços,
e nunca pode deixar `total_pagar` negativo.

## Grupos participantes

Uma promoção pode ser vinculada a vários grupos por meio de
`promocoes_grupos`. `promocoes.todos_grupos = 1` significa que ela aceita
qualquer grupo; quando o campo é `0`, o grupo da locação/reserva precisa existir
no pivot. Promoção restrita nunca pode ser aplicada sem um grupo definido.

Ao editar uma locação, o snapshot histórico só é preservado se código e grupo
continuarem iguais. Alterar o grupo força nova validação da promoção.

## Canais e fluxos

- Sistema interno: `POST /api/promocoes/validar`, canal `SIS`.
- Website: `POST /api/public/promocao-validar`, canal `SITE`, autenticado pelo
  token do site e pelo contexto temporário do tenant.
- O website usa `ajax-promocao.php` para não expor o token no navegador.
- O parâmetro `promo=CODIGO` pode ser usado tanto em `index.php` quanto em
  `reserva.php`; ao iniciar pela página inicial, o código é propagado para o
  pré-cadastro da reserva.

Nos dois fluxos, a validação de interface é apenas uma prévia. A gravação da
locação/reserva recalcula o desconto no servidor sobre o total oficial.

## Edição e histórico

Ao editar uma locação/reserva, um código já gravado e não alterado preserva o
desconto histórico, mesmo que a promoção tenha vencido ou sido desativada. Um
código novo é validado pelas regras atuais. Sem código, o desconto manual
continua permitido.

`locacoes.promocao_codigo` e `locacoes.valor_desconto` são snapshots da
aplicação. Registros históricos não devem ser reescritos em migrações de
normalização do cadastro de promoções.

## Multi-tenancy e cadastro

O código é único por `(chave, codigo)`, permitindo que tenants distintos usem o
mesmo texto. Códigos são normalizados com `TRIM` e `UPPER`.

O CRUD deve validar no backend todas as filiais, grupos, canais e valores, e
salvar a promoção com seus pivôs/valores na mesma transação. Sem grupo
selecionado, o backend grava `todos_grupos = 1`. Nunca use `withoutChave()` nesse
fluxo.
