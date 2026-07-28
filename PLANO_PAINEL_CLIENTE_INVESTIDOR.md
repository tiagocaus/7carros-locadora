# Plano: Painel do Cliente e do Investidor

## Resumo

Criar no website publicado um portal responsivo e multi-idioma com dois perfis selecionados no login:

- **Cliente:** dados cadastrais, contratos, reservas, locações, faturas, multas, manutenções, veículos utilizados, documentos e indicação.
- **Fornecedor investidor:** veículos fornecidos, operação anonimizada, manutenções, comissões e desempenho do investimento.
- A mesma pessoa poderá existir nos dois cadastros, mas cada perfil terá sessão e permissões isoladas.
- O link “Painel do cliente” do cabeçalho e rodapé passará a abrir o portal; Cliente e Investidor ficarão disponíveis por padrão nos websites atualizados.

## Implementação principal

### Autenticação e segurança

- Adicionar `senha` nullable em `fornecedores`, armazenando somente hash Argon2id.
- No cadastro do fornecedor, colocar o campo **Senha** dentro da seção Investidor, imediatamente antes de “Regras de comissão”; em edição, senha vazia preserva o hash atual.
- Manter o campo de senha existente no cadastro de Cliente e adicionar, para ambos os cadastros, a ação “Enviar link de ativação/definição de senha”.
- Nunca enviar senha em texto: usar token one-time com validade de 60 minutos, uso único e invalidação dos tokens anteriores.
- Preservar o login atual da reserva online. Clientes legados com bcrypt serão migrados para Argon2id após login bem-sucedido; os 347 clientes locais sem senha poderão ativar o acesso por email.
- Aceitar email ou CPF/CNPJ, sempre dentro do tenant e do perfil selecionado. Resultado duplicado ou ambíguo será bloqueado com mensagem neutra.
- Exigir cliente ativo ou fornecedor com `investidor = 1`.
- Criar sessões opacas de portal armazenadas como hash no backend, contendo tenant, perfil e entidade autenticada. Os proxies do website não aceitarão IDs enviados pelo navegador.
- Cookies locais com `Secure`, `HttpOnly`, `SameSite=Lax`, rotação do ID após login, CSRF para alterações, expiração por inatividade, logout com revogação e rate limit.
- Separar Controllers/Models por autenticação, Cliente, Investidor e documentos, mantendo toda consulta em Models com QueryBuilder e contexto de tenant.

### Banco e interfaces

- Criar migrations para:
  - senha e índices de login do investidor;
  - sessões e tokens de ativação do portal;
  - códigos/eventos de indicação;
  - permissão `notificacoes.alteracoes_portal`, concedida inicialmente às roles Proprietário e Gerente;
  - índices compostos necessários para investidor, veículos, comissões e manutenções.
- Criar API pública server-to-server, protegida por `X-Site-Token` e token da sessão do portal, com operações para:
  - login, logout, ativação/reset e troca de senha;
  - dashboard e perfil;
  - listagens paginadas de contratos, locações, faturas, multas, manutenções, veículos e comissões;
  - relatório de desempenho do investidor;
  - obtenção segura de documentos e links de pagamento;
  - geração e atribuição do link de indicação.
- Todas as respostas derivarão o cliente/investidor da sessão, sem aceitar `id_cliente` ou `id_fornecedor` do frontend.

### Experiência Cliente

- **Dashboard:** veículos distintos utilizados, contratos ativos/finalizados, reservas (`R`/`P`), locações abertas/finalizadas, faturas abertas/pagas, multas abertas/pagas e manutenções vinculadas ao cliente.
- **Contratos:** abas abertos/fechados, resumo, veículos e downloads dos documentos próprios disponíveis.
- **Reservas e locações:** reservas separadas das locações abertas/fechadas; voucher, documento e fatura quando aplicáveis.
- **Meus veículos:** veículos distintos vinculados ao histórico do cliente, com foto, marca, modelo, ano, placa, cor e períodos de utilização, sem revelar disponibilidade operacional interna.
- **Faturas:** abertas, vencidas e pagas; faturas abertas oferecem o link de pagamento existente ou sincronizado com o gateway; pagas oferecem recibo.
- **Multas:** situação financeira e de processamento, veículo, infração e documentos próprios disponíveis.
- **Manutenções:** somente registros em que `manutencoes.id_cliente` corresponda ao usuário.
- **Meus dados:** contato, endereço e idioma editáveis. Nome/razão social, CPF/CNPJ, RG/IE, CNH e demais dados de identidade ficam somente leitura; senha possui fluxo separado.
- **Indicação:** código aleatório permanente e link para a reserva do website. O primeiro código válido permanece por 30 dias; registrar clique e conversão quando um novo cliente/reserva for criado, ignorando autoindicação e sem prêmio financeiro nesta versão.

### Experiência Investidor

- **Dashboard:** veículos ativos, valor investido, receita reconhecida, comissão pendente, comissão paga, manutenções e indicadores operacionais por período.
- **Veículos:** somente veículos com `veiculos.id_fornecedor` igual ao investidor autenticado.
- **Operação anonimizada:** períodos, status, dias ocupados e valores consolidados por veículo, sem nome, CPF/CNPJ, contato, documentos ou downloads do locatário.
- **Manutenções:** histórico dos próprios veículos.
- **Comissões:** pendentes, pagas e canceladas, origem, veículo, referência e valor.
- **Desempenho:** reaproveitar a regra do relatório Fornecedor Investidor, sempre fixando o fornecedor da sessão. Exibir indicadores e detalhamento em tela, sem PDF.
- Mostrar diagnósticos como “sem fatura paga”, “grupo sem comissão” e “comissão mensal ainda não gerada”. Não calcular valores hipotéticos como se fossem comissões efetivamente geradas.
- **Dados cadastrais:** contato e endereço editáveis; identidade, PIX, split e conta bancária somente leitura.

### Auditoria, notificações e documentos

- Atualizações de perfil ocorrerão em transação e aceitarão somente uma lista explícita de campos.
- Registrar no log do sistema cada campo realmente alterado com valor **De → Para**, identificando perfil e usuário do portal.
- Senhas são a única exceção: registrar apenas “senha alterada”, nunca valor anterior, novo ou hash.
- Após o commit, enfileirar email com o resumo das alterações para funcionários ativos com `notificacoes.alteracoes_portal`, deduplicando destinatários. Falha de email não desfaz a atualização, mas deve ser registrada.
- Para Cliente, usar a filial do cadastro; para Investidor sem filial própria, usar a matriz principal.
- Documentos serão servidos apenas após validar tenant, perfil e propriedade do registro.
- Reutilizar os geradores atuais de contrato, locação, multa, fatura e recibo. Qualquer PDF novo seguirá `PdfHelper`, output buffering e nunca `Template::render()`.

## Testes e aceitação

- Testar login por email e documento para ambos os perfis, rehash bcrypt, senha ausente, ativação, token expirado/usado, logout e troca de senha.
- Bloquear perfil incorreto, fornecedor não investidor, cadastro inativo, identificador duplicado e tentativas cross-tenant/IDOR.
- Confirmar que Cliente acessa exclusivamente seus registros e que Investidor nunca recebe PII ou documentos de locatários.
- Validar contagens e classificações de status contra contratos, locações, financeiro, multas, manutenções e comissões.
- Testar atualização permitida, rejeição de campos protegidos, log completo De → Para e email apenas aos funcionários autorizados.
- Em testes que enfileirem email, usar exclusivamente o tenant `1111111111111`.
- Testar recibos, documentos e links de pagamento próprios, incluindo tentativa de acessar recurso de outro usuário.
- Testar indicação: código estável, clique, cookie de 30 dias, primeira atribuição, conversão, autoindicação e ausência de recompensa.
- Validar responsividade, paginação, estados vazios e os cinco idiomas do website.
- Executar testes PHP existentes relacionados e adicionar uma suíte específica do portal.

## Publicação e premissas

- Editar somente CSS/JS fonte, gerar os respectivos minificados e incrementar a versão minor do template por se tratar de uma funcionalidade ampla.
- Validar o build do `WebsiteBuilderService`, publicar pelo FTP apenas os arquivos do escopo e, para CSS/JS, enviar somente os minificados.
- O portal ficará disponível automaticamente em cada website após sua atualização/publicação; não haverá configuração para ocultar Cliente ou Investidor.
- Não haverá republicação automática em massa dos 68 websites ativos sem uma solicitação operacional específica.
- O programa de indicação administrativo existente não será reutilizado, pois indica locadoras para a 7Carros, não clientes para uma locadora.
- Conflito confirmado: o cadastro manual de Cliente atualmente não gera senha obrigatoriamente; somente o pré-cadastro da reserva online usa inicialmente o CPF/CNPJ como senha. O novo fluxo privilegiará ativação one-time segura.
- A documentação de comissões determina que relatórios usem comissões efetivamente geradas; o portal seguirá essa regra, relevante porque o banco local possui apenas uma comissão registrada.

## Documentos consultados

- `AGENTS.md`
- `storage/templates/website/PROCEDIMENTO.md`
- `docs/website.md`
- `docs/architecture.md`
- `docs/querybuilder.md`
- `docs/multi-tenancy.md`
- `docs/security.md`
- `docs/database.md`
- `docs/contratos.md`
- `docs/locacoes.md`
- `docs/financeiro.md`
- `docs/gateways.md`
- `docs/multas.md`
- `docs/preventive-maintenance.md`
- `docs/comissoes-investidores.md`
- `docs/relatorios.md`
- `docs/relatorios-dev.md`
- `docs/pdf.md`
- `docs/modals.md`
- `docs/helpers.md`
- `docs/best-practices.md`
- `docs/logs.md`
- `docs/templates.md`
- `docs/messaging.md`
- `docs/api.md`
- `docs/file-helper.md`
