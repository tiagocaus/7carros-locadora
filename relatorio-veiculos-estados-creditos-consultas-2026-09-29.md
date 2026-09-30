# Relatório de veículos por estado e créditos para consultas online

Data da consulta: 29/09/2026.

Fonte: banco de dados online indicado em `temp-bd.txt`, acessado exclusivamente em modo de leitura, sem alteração de dados. Este arquivo registra os resultados da consulta realizada; não representa uma atualização automática.

## Veículos cadastrados por estado

Ranking do maior para o menor, considerando o estado da matriz/filial proprietária, todos os status dos veículos e excluindo a conta de teste. Estados cadastrados por nome e por sigla foram unificados. A localização representa o endereço da unidade proprietária, não o emplacamento ou a localização física atual do veículo.

| Estado | Veículos |
|---|---:|
| São Paulo | 1.540 |
| Minas Gerais | 1.261 |
| Pernambuco | 1.057 |
| Santa Catarina | 903 |
| Bahia | 582 |
| Goiás | 514 |
| Paraná | 504 |
| Rio Grande do Sul | 473 |
| Maranhão | 421 |
| Rio Grande do Norte | 420 |
| Amazonas | 308 |
| Sergipe | 308 |
| Pará | 267 |
| Mato Grosso | 245 |
| Alagoas | 233 |
| Distrito Federal | 132 |
| Piauí | 119 |
| Roraima | 100 |
| Paraíba | 97 |
| Rio de Janeiro | 94 |
| Ceará | 81 |
| Tocantins | 75 |
| Mato Grosso do Sul | 63 |
| Espírito Santo | 59 |
| Rondônia | 51 |
| Acre | 18 |
| Amapá | 0 |
| **Total** | **9.925** |

Ficaram fora do ranking:

- **61 veículos** vinculados a unidades no exterior.
- **86 veículos** sem localização brasileira válida, incluindo 29 vinculados a “Florida”, embora o país esteja cadastrado como Brasil.
- **19 veículos** da conta de teste.

Total geral de registros de veículos no banco: **10.091**.

## Clientes que adicionaram crédito para consultas online

Foram **18 contas**, com **19 recargas confirmadas por PIX/cartão**, totalizando **R$ 2.000,01**, considerando todo o histórico disponível. Os valores representam créditos adicionados, não o saldo atual.

| Cliente / conta | Cidade–UF da matriz | Total adicionado |
|---|---|---:|
| Geomotors Locadora de Moto | São Paulo–SP | R$ 300,00 |
| Locadora Radial | Camaçari–BA | R$ 100,01 |
| 3S Empreendimentos | Aracaju–SE | R$ 100,00 |
| AP Locadora | Tibau do Sul–RN | R$ 100,00 |
| DC Veículos | Belo Horizonte–MG | R$ 100,00 |
| Eliane L S Lisboa / Locafacil¹ | Toledo–PR | R$ 100,00 |
| Erinaldo Veículos | Agrestina–PE | R$ 100,00 |
| Fly Rent Car Locadora | Foz do Iguaçu–PR | R$ 100,00 |
| Grupo Loka Uai e empresas vinculadas² | Ouro Preto e Belo Horizonte–MG | R$ 100,00 |
| Loca Motos Ipatinga | Ipatinga–MG | R$ 100,00 |
| Locacer | Parnamirim–RN | R$ 100,00 |
| Locar Intermediação e Negócios | Uberlândia–MG | R$ 100,00 |
| Locatena | Governador Valadares–MG | R$ 100,00 |
| MCM Locadora | Cachoeirinha–RS | R$ 100,00 |
| RHS Locadora | Contagem–MG | R$ 100,00 |
| Risco Zero | Governador Valadares–MG | R$ 100,00 |
| VL Locações de Automóveis | Rio de Janeiro–RJ | R$ 100,00 |
| W M Belga | Santa Inês–MA | R$ 100,00 |

¹ Duas matrizes na mesma conta; a recarga pertence à conta compartilhada.

² A conta reúne Grupo Loka Uai, Uai Frotas, Rede Locadora, Facilita Brasil, LHM Logística e Loc Bom Sucesso. O banco registra a recarga por conta, sem identificar qual dessas empresas pagou. A recarga foi contada uma única vez.

Não foram incluídas **6 recargas pendentes**, totalizando **R$ 600,01**, nem o **crédito manual de R$ 100,00 da conta de teste**.

## Documentação consultada

- Instruções do `AGENTS.md` fornecidas na sessão.
- `docs/database.md`.
- `docs/querybuilder.md`.
- Trechos de `docs/multas.md`.
- Trechos de `docs/SERPRO_CENTRAL_MULTAS.md`.
