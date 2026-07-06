# Checklist Digital

## Objetivo

O Checklist Digital permite que funcionários realizem vistorias de veículos pelo celular, substituindo formulários impressos. Suporta checklists vinculados (atrelados a locação/contrato) e avulsos (independentes), com questionário, fotos de vistoria e assinatura digital.

## Regras de Negócio

### Acesso e Permissões
- **Permissão**: `checklists.criar` — obrigatória para criar/editar checklists digitais
- **Plano**: Apenas planos **P3** (Avançado) e **P4** (Ilimitado)
- **Botão na listagem**: Visível sempre, habilitado apenas em **dispositivos móveis** + planos P3/P4
- **Login redirect**: Usuários sem `dashboard.visualizar` são redirecionados para `/checklists/digital` após login

### Tipos de Checklist
| Tipo | Código | Descrição |
|------|--------|-----------|
| Vinculado | `V` | Atrelado a locação ou contrato. Tem etapas de saída e chegada no mesmo registro. |
| Avulso | `A` | Independente. Sem vínculo com locação/contrato. Usa apenas a etapa de saída. |

### Código do Checklist
- Checklist avulso gera código próprio `CK...`.
- Checklist vinculado usa em `checklist.codigo` o código da locação ou contrato vinculado. Ele não gera código `CK...`.
- Em contratos com múltiplos veículos, mais de um checklist pode compartilhar o mesmo código do contrato; a diferenciação é feita por vínculo + veículo.

### Etapas
| Etapa | Uso |
|-------|-----|
| Saída | Entrega do veículo ao cliente; também usada para checklist avulso |
| Chegada | Devolução do veículo em checklist vinculado |

### Status
| Status | Significado |
|--------|-------------|
| `1` | Avulso iniciado |
| `2` | Avulso concluído |
| `3` | Vinculado saída iniciado |
| `4` | Vinculado saída concluído |
| `5` | Vinculado chegada iniciado |
| `6` | Vinculado chegada concluído |

Na listagem iframe do dashboard (`Veículos > Checklists`), a coluna visual `Status` é resumida para manter a tabela compacta: status `1`, `3` e `5` aparecem como **Pendente**; status `2`, `4` e `6` aparecem como **Finalizado**. O status detalhado continua sendo usado internamente para regras de retomada, saída e chegada, e pode aparecer como tooltip do badge.

### Veículos em Vinculado
- **Locação**: Geralmente 1 veículo
- **Contrato**: Pode ter múltiplos veículos
- Reservas de locacao podem existir apenas por grupo/categoria (`id_veiculo = NULL`). Nesse caso, nao ha checklist vinculado ate a locadora alocar um veiculo especifico na saida.
- Veículos que já possuem a etapa do checklist concluída ficam **desabilitados** (cinza + ✓) no select
- Após finalizar, se há mais veículos pendentes, oferece "Fazer checklist do próximo veículo"
- Cada checklist vinculado é 1 registro por veículo/vínculo, com campos separados para saída e chegada.

### Exclusão em Cascata
- Ao excluir uma **locação**: todos os checklists vinculados + fotos são apagados
- Ao excluir um **contrato**: todos os checklists vinculados + fotos são apagados
- Ao excluir um **checklist** diretamente: fotos de vistoria + assinatura são apagadas do storage

---

## Fluxo do Checklist (4 Abas)

### Aba 1 — Informações
| Campo | Obrigatório | Notas |
|-------|-------------|-------|
| Checklist | Não | Campo de leitura indicando `Checklist avulso`, `Checklist vinculado de saída` ou `Checklist vinculado de chegada` |
| Tipo | Sim | Definido pelo botão de origem (`+ Avulso` ou `+ Vinculado`), não por campo na tela |
| Etapa | Sim (se vinculado) | Definida pela lista de vinculados pendentes: saída ou chegada |
| Locação/Contrato | Sim (se vinculado) | Vem da lista de vinculados e fica em leitura na tela |
| Veículo | Sim | Vinculado: vem da lista de vinculados e fica em leitura. Avulso: chosen-select server-side |
| Tanque/Bateria | Sim quando editável | Avulso: editável. Vinculado saída: leitura. Vinculado chegada: editável |
| Odômetro atual | Sim quando editável | Avulso: editável. Vinculado saída: leitura. Vinculado chegada: editável |
| Modelo do checklist | Sim | Chosen-select client-side. Apenas `tipo=0` (digital). Na chegada vinculada fica em leitura e usa o mesmo modelo da saída |
| Observações | Não | Texto livre |

**Ao avançar**: salva no BD via `POST /api/checklists/criar`, usando campos da etapa (`*_saida` ou `*_entrada`). `tanque` e `odometro` nao sao colunas da tabela `checklist`; sao campos de tela do cadastro do veiculo. Ao finalizar a assinatura, checklist avulso e checklist vinculado de chegada atualizam `veiculos.odometro` e `veiculos.tanque_fracao`. Checklist vinculado de saida mostra esses valores em leitura e nao atualiza o cadastro do veiculo.

Na chegada vinculada, a tela sempre abre na aba Informações. Os campos Tanque/Bateria e Odômetro atual iniciam vazios para preenchimento da devolução; o select de tanque pode iniciar sem valor selecionado. O modelo do checklist fica em leitura e deve ser o mesmo usado na saída. Se a chegada for enviada sem `id_modelo`, o backend reaproveita o `id_modelo` do checklist de saída aberto para o mesmo vínculo/veículo.

### Aba 2 — Questões
- Questões carregadas do modelo selecionado (`checklist_modelos.questoes`)
- Cada item usa `name` como campo canonico do texto exibido. Campos legados (`content`, `pergunta`, `label`) devem ser convertidos para `name` e removidos ao salvar/migrar.
- 4 opções por questão: **Confere** (verde), **Não confere** (vermelho), **Danificado** (amarelo), **N/A** (púrpura)
- **Todas** devem ser respondidas para avançar
- **Auto-save**: a cada 30 segundos via `setInterval`

### Aba 3 — Vistorias
- Itens de vistoria do modelo (`checklist_modelos.vistoria`)
- Cada item usa `name` como campo canonico do texto exibido. Campos legados (`content`, `pergunta`, `label`) devem ser convertidos para `name` e removidos ao salvar/migrar.
- Foto tirada via câmera (`<input capture="environment">`)
- Foto enviada **imediatamente** ao servidor (resize client-side max 1200px → JPEG 85%)
- **Mínimo 1 foto** obrigatória para avançar
- Botões: câmera (tirar), caneta (abrir editor), lixeira (excluir)

#### Editor de Fotos (Canvas)
Após capturar uma foto, o usuário pode abrir o editor (ícone caneta) para anotar danos:

**Ferramentas disponíveis:**
| Ferramenta | Ícone | Descrição |
|------------|-------|-----------|
| Desenho livre | `fa-pen` | Traço com 8 cores (branco, preto, vermelho, azul, amarelo, verde, cinza, roxo) |
| Marcadores | `fa-map-marker-alt` | 6 tipos de dano: Amassado (vermelho), Falta (cinza), Quebrado (laranja), Riscado (azul), Trincado (verde), Outros (roxo) |
| Desfazer/Refazer | `fa-undo` / `fa-redo` | Histórico de ações (strokes + marcadores) |
| Zoom | `fa-search-plus` / `fa-search-minus` | Zoom via botões (0.5x–4x) + pinch-to-zoom no mobile |
| Limpar tudo | `fa-trash` | Remove todos traços e marcadores (com confirmação) |

**Arquitetura do editor:**
- Overlay fullscreen (`#photoEditor`, z-index 55) com canvas 2D
- Imagem original preservada em `img_original_url` (client-side) para re-edição na sessão
- Anotações (drawings + markers) armazenadas no `vistoriaState` do JS (não persistem no servidor)
- Ao salvar: composita imagem original + strokes + marcadores em canvas temporário → exporta JPEG 0.85 → re-upload via `uploadVistoria`
- Marcadores são renderizados como SVG pins com labels no overlay, e "baked" na imagem JPEG ao salvar
- Coordenadas armazenadas em espaço da imagem (resolution-independent)
- Lazy initialization: DOM do editor é inicializado na primeira abertura (`initEditorDOM` + `initEditorEvents`)

### Aba 4 — Assinatura
- Canvas HTML5 com suporte touch + mouse
- Suporta rotação do celular (canvas redimensiona mantendo conteúdo)
- Exporta como JPEG com fundo branco (evita fundo preto no PDF)
- **Ao salvar**: grava a assinatura da etapa (`assinatura_saida` ou `assinatura_entrada`) e atualiza o status do checklist. Checklist avulso e vinculado de chegada tambem atualizam odometro/tanque no cadastro do veiculo.

---

## Retomar Checklist Pendente

- URL: `/checklists/novo?retomar={id}`
- Vinculado também pode ser retomado por código do vínculo: `/checklists/novo?retomar={codigo_locacao_ou_contrato}&etapa=entrada&id_veiculo={id}`
- Carrega dados salvos e preenche aba Infor (tipo, etapa, vínculo, veículo, modelo)
- Posiciona na aba correta:
  - Questões incompletas → aba Questões
  - Todas questões respondidas, sem foto → aba Vistorias
  - Tudo preenchido → aba Assinatura

---

## Páginas Standalone Mobile

| Página | Rota | Descrição |
|--------|------|-----------|
| Listagem | `/checklists/digital` | Cards com borda colorida (azul=vinculado, marrom=avulso). Infinite scroll, busca, legenda. |
| Vinculados pendentes | `/checklists/vinculados` | Busca e filtro de vinculados aguardando saída ou chegada. |
| Criar/Editar | `/checklists/novo` | 4 abas: Infor → Questões → Vistorias → Assinatura |
| Visualizar | `/checklists/visualizar/{id}` | Read-only. Avulso: fotos grandes. Vinculado: saída/chegada lado a lado. |

Todas são HTML standalone (não usam template de iframe do dashboard).

---

## Impressão (PDF)

### Avulso (`template.php`)
- Layout coluna única
- Fotos: detecta orientação — **landscape**: 2 por linha, **portrait**: 3 por linha
- Fotos sem `max-height` (tamanho grande para ver detalhes)

### Vinculado (`template-vinculado.php`)
- Layout duas colunas: esquerda = saída, direita = chegada
- 1 foto por linha em cada coluna
- Saída e chegada são lidas do mesmo registro (`*_saida` e `*_entrada`).

### Busca para impressão
- Busca por FK (`id_locacao`/`id_contrato`) primeiro, fallback por `codigo` para registros legados
- Checklist impresso (`checklist_modelos.tipo = 1`) usa o diagrama do cadastro do veículo (`veiculos.diagrama`) em `public/assets/img/diagramas`.
- Diagramas são assets estáticos do projeto e devem ser passados ao mPDF como caminho local absoluto. Use `PdfHelper::resolvePublicAssetImagePath($diagrama, 'assets/img/diagramas')`, que também trata diferenças de maiúsculas/minúsculas entre dados legados do banco (`Sedan.jpg`) e arquivos reais (`sedan.jpg`).
- No PDF impresso, o diagrama deve preservar proporção e respeitar altura máxima de `420px` em cada coluna de saída/chegada.
- A tabela de tanque/combustível deve ficar centralizada horizontalmente abaixo do diagrama.

---

## Banco de Dados

### Tabela `checklist`
```sql
id                INT UNSIGNED PK AUTO_INCREMENT
chave             VARCHAR(45) NOT NULL          -- tenant
codigo            VARCHAR(50)                    -- avulso: CK...; vinculado: codigo da locacao/contrato
tipo              VARCHAR(1)                     -- V=vinculado, A=avulso
id_locacao        INT UNSIGNED NULL FK           -- locacoes.id ON DELETE SET NULL
id_contrato       INT UNSIGNED NULL FK           -- contratos.id ON DELETE SET NULL
id_veiculo        INT UNSIGNED NULL              -- veículos.id
id_modelo         INT UNSIGNED NULL              -- checklist_modelos.id
questoes_saida    MEDIUMTEXT NULL                -- JSON respostas de saída/avulso
vistoria_saida    LONGTEXT NULL                  -- JSON fotos de saída/avulso
observacoes_saida MEDIUMTEXT NULL
data_saida        DATETIME NULL
assinatura_saida  MEDIUMTEXT NULL
questoes_entrada  MEDIUMTEXT NULL                -- JSON respostas de chegada
vistoria_entrada  LONGTEXT NULL                  -- JSON fotos de chegada
observacoes_entrada MEDIUMTEXT NULL
data_entrada      DATETIME NULL
assinatura_entrada MEDIUMTEXT NULL
id_funcionario    INT UNSIGNED NULL
status            VARCHAR(3)                     -- 1..6 conforme tabela de status
created_at        TIMESTAMP
updated_at        DATETIME
```

### Tabela `checklist_modelos`
```sql
id        INT UNSIGNED PK
chave     VARCHAR(45) NOT NULL
nome      VARCHAR(50)
tipo      INT(1) NOT NULL DEFAULT 0    -- 0=digital, 1=impresso
questoes  MEDIUMTEXT                    -- JSON template questões
vistoria  MEDIUMTEXT                    -- JSON template itens vistoria
status    VARCHAR(1)                    -- A=ativo, I=inativo
```

### Permissões
| Chave | Descrição |
|-------|-----------|
| `checklists.visualizar` | Visualizar e listar checklists |
| `checklists.criar` | Criar checklists digitais via mobile |
| `checklists.excluir` | Excluir checklists |

---

## API Endpoints

### Páginas (GET, retorna HTML)
| Rota | Método Controller | Descrição |
|------|-------------------|-----------|
| `/checklists/digital` | `viewDigital()` | Listagem mobile |
| `/checklists/vinculados` | `viewVinculados()` | Lista de vinculados pendentes |
| `/checklists/novo` | `viewNovo()` | Criar checklist |
| `/checklists/visualizar/{id}` | `viewVisualizar()` | Visualizar checklist |

### API CRUD (JSON)
| Rota | Método | Descrição |
|------|--------|-----------|
| `POST /api/checklists/criar` | `criar()` | Cria checklist (aba Infor) |
| `POST /api/checklists/{id}/questoes` | `salvarQuestoes()` | Salva questionário |
| `POST /api/checklists/{id}/vistoria/upload` | `uploadVistoria()` | Upload foto vistoria |
| `POST /api/checklists/{id}/vistoria/{itemId}/excluir` | `excluirVistoria()` | Remove foto |
| `POST /api/checklists/{id}/assinar` | `assinar()` | Salva assinatura + finaliza |
| `GET /api/checklists/novo/{id}` | `show()` | Dados do checklist (para retomar) |

### API Busca (JSON)
| Rota | Descrição | Retorno |
|------|-----------|---------|
| `GET /api/checklists/buscar-locacoes?q=` | Locações ativas | `{id, codigo, cliente, id_veiculo, veiculo, text}` |
| `GET /api/checklists/buscar-contratos?q=` | Contratos ativos | `{id, codigo, cliente, id_veiculo, veiculo, text}` |
| `GET /api/checklists/buscar-veiculos?q=` | Veículos disponíveis | `{id, placa, modelo, marca, tipo_combustivel, text}` |
| `GET /api/checklists/buscar-vinculos?q=` | Locações + contratos combinados | `{id: codigo, codigo, tipo_vinculo, id_vinculo, id_veiculo, veiculo}` |
| `GET /api/checklists/vinculados?search=&status=` | Vinculados pendentes | Lista itens aguardando saída ou chegada |
| `GET /api/checklists/veiculos-vinculo?tipo=L&id=123&etapa=saida` | Veículos de um vínculo | `{id_veiculo, placa, modelo, checklist_feito}` |

---

## Arquivos

| Arquivo | Descrição |
|---------|-----------|
| `app/Controllers/ChecklistNovoController.php` | Controller principal (criar, editar, APIs) |
| `app/Controllers/ChecklistsController.php` | Controller listagem iframe + impressão PDF |
| `app/Models/Checklist.php` | Model com queries e lógica de dados |
| `app/Models/ChecklistModelo.php` | Model dos templates de checklist |
| `app/Views/pages/checklists/novo.php` | Página standalone criação (4 abas) |
| `app/Views/pages/checklists/digital.php` | Página standalone listagem mobile |
| `app/Views/pages/checklists/vinculados.php` | Página standalone de vinculados pendentes |
| `app/Views/pages/checklists/visualizar.php` | Página standalone visualização |
| `app/Views/pages/checklists/index.php` | Listagem iframe (dashboard) |
| `app/Views/pages/checklists/imprimir/template.php` | Template PDF avulso |
| `app/Views/pages/checklists/imprimir/template-vinculado.php` | Template PDF vinculado |
| `app/Lang/*/modules/checklists.php` | Traduções (pt_BR, en_US, es_ES, pt_PT, it_IT) |

---

## Sessão e Segurança

- **Timeout**: 4 horas (`gc_maxlifetime` + `cookie_lifetime`)
- **Validação**: Apenas User-Agent (sem IP — celulares mudam de IP frequentemente)
- **CSRF**: Token via `<meta name="csrf-token">` + header `X-CSRF-TOKEN`
- **Multi-tenancy**: Filtro por `chave` em todas as queries
- **Intended URL**: Se sessão expirar, redireciona para login e volta à página após autenticação

## Padrao de Datas

Datas de saida, entrada/chegada e vistoria (`data_saida`, `data_entrada`) sao horarios operacionais locais e devem usar `format_operational_datetime()` / `DateHelper.formatOperationalDateTime()`, sem conversao de timezone. Datas tecnicas de criacao/visualizacao (`created_at`, logs) podem usar `format_datetime()` / `DateHelper.formatDateTime()`. Nao use `date()`, `new DateTime()`, `new Date()` ou `NOW()/CURDATE()` diretamente fora das excecoes documentadas em [date.md](./date.md).
