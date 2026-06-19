# Guia de Implementacao - NFS-e (Nota Fiscal de Servico Eletronica)

Referencia tecnica baseada no sistema locadora.7carros.com para replicar a emissao de NFS-e em outro sistema. Preparado para suportar multiplos modelos (Nacional, ABRASF, Betha DPS e futuros estaduais) de forma padronizada.

**Leitura obrigatoria para emissao Nacional (SEFIN):** [§13 Armadilhas SEFIN Nacional](#13-armadilhas-sefin-nacional-leitura-obrigatoria) — erros reais corrigidos em producao (E0116, E0160, E0166).

---

## Sumario

1. [Arquitetura](#1-arquitetura)
2. [Banco de Dados](#2-banco-de-dados)
3. [Classes e Servicos](#3-classes-e-servicos)
4. [Telas do Sistema](#4-telas-do-sistema)
5. [Fluxos de Negocio](#5-fluxos-de-negocio)
6. [Estrutura XML](#6-estrutura-xml)
7. [Endpoints Backend](#7-endpoints-backend)
8. [Certificado Digital](#8-certificado-digital)
9. [Assinatura Digital](#9-assinatura-digital)
10. [Tratamento de Erros](#10-tratamento-de-erros)
11. [Processos Automaticos (CRON)](#11-processos-automaticos-cron)
12. [Validacoes e Regras](#12-validacoes-e-regras)
13. [Armadilhas SEFIN Nacional (leitura obrigatoria)](#13-armadilhas-sefin-nacional-leitura-obrigatoria)

---

## 1. Arquitetura

### Strategy Pattern para Multiplos Modelos

O ponto central da arquitetura e usar **Strategy Pattern** para que o sistema suporte diferentes modelos de emissao (Nacional SEFIN, ABRASF municipal, e futuros modelos estaduais) sem alterar o servico principal.

```
NFSeService (orquestrador)
    |
    |-- decide qual estrategia usar baseado em config `tipo_emissao`
    |
    +-- NFSeXMLInterface (interface)
    |     |-- NFSeXMLNacional   (DPS - Sistema Nacional SEFIN)
    |     |-- NFSeXMLAbrasf     (RPS - ABRASF 2.04 municipal)
    |     |-- NFSeBethaXML      (DPS - Betha Cloud, namespace proprio)
    |     +-- NFSeXML[Estado]   (futuro - modelo estadual especifico)
    |
    +-- NFSeAPIInterface (interface)
    |     |-- NFSeAPINacional   (REST + mTLS)
    |     |-- NFSeAPIAbrasf     (SOAP)
    |     |-- NFSeBethaAPI      (SOAP + mTLS - Betha Cloud DPS)
    |     +-- NFSeAPI[Estado]   (futuro - API estadual)
    |
    +-- NFSeCertificado     (upload, validacao, extracao PEM)
    +-- NFSeAssinatura      (XMLDSIG - SHA256 ou SHA1)
    +-- NFSePDF             (geracao DANFSE com mPDF + QR Code)
    +-- NFSeErros           (mapeamento de 70+ codigos de erro)
```

### Estrutura de Diretorios Sugerida

```
app/
  Classes/
    NFSe/
      NFSeService.php          # Orquestrador principal
      NFSeXML.php              # XML Nacional (DPS)
      NFSeABRASFXML.php        # XML ABRASF (RPS)
      NFSeBethaXML.php         # XML Betha DPS (namespace Betha)
      NFSeAPI.php              # API Nacional (REST)
      NFSeABRASFAPI.php        # API ABRASF (SOAP)
      NFSeBethaAPI.php         # API Betha DPS (SOAP + mTLS)
      NFSeCertificado.php      # Gestao de certificado digital
      NFSeAssinatura.php       # Assinatura XML
      NFSePDF.php              # Geracao PDF (DANFSE)
      NFSeErros.php            # Mapeamento de erros
  Views/
    nfse.php                   # Listagem/Dashboard
    nfseEmitir.php             # Tela de emissao
    nfseVisualizar.php         # Visualizacao detalhada
    nfseCancelar.php           # Cancelamento
    nfseConfiguracoes.php      # Configuracoes
    json/
      nfse.php                 # DataTable AJAX endpoint
  Crons/
    nfse.php                   # Processos automaticos
api/
  app.nfse.php                 # Endpoints REST
sql/
  001_create_table_nfse.sql
  002_create_table_nfse_eventos.sql
  003_create_table_nfse_configuracoes.sql
uploads/
  certificados/                # Certificados .pfx (permissao 0600)
```

---

## 2. Banco de Dados

### 2.1 Tabela `nfse_configuracoes` (NOVA - separada da tabela de empresas)

No sistema original os campos ficam em `matriz_filial`. No novo sistema, usar tabela separada para melhor organizacao.

```sql
-- ============================================================
-- Tabela de configuracao NFS-e por empresa/filial
-- Separada da tabela de empresas para melhor organizacao
-- ============================================================
CREATE TABLE IF NOT EXISTS `nfse_configuracoes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_empresa` INT UNSIGNED NOT NULL COMMENT 'FK para tabela de empresas/filiais',

  -- Certificado Digital
  `certificado_arquivo` VARCHAR(100) NULL COMMENT 'Nome do arquivo .pfx do certificado',
  `certificado_senha` VARCHAR(255) NULL COMMENT 'Senha do certificado (criptografada AES-256-CBC)',
  `certificado_validade` DATE NULL COMMENT 'Data de validade do certificado',

  -- Configuracao Geral
  `ativo` CHAR(1) DEFAULT 'N' COMMENT 'Emissao de NFS-e ativa (S/N)',
  `ambiente` TINYINT(1) DEFAULT 2 COMMENT '1=Producao, 2=Homologacao',
  `tipo_emissao` VARCHAR(20) DEFAULT 'nacional' COMMENT 'nacional, abrasf ou betha (define qual estrategia usar)',
  `serie` VARCHAR(10) NULL COMMENT 'Serie da DPS/RPS',
  `numero_atual` INT UNSIGNED DEFAULT 0 COMMENT 'Ultimo numero de NFS-e emitido (Nacional)',
  `emissao_auto` CHAR(1) DEFAULT 'N' COMMENT 'Emitir automaticamente ao confirmar pagamento (S/N)',
  `enviar_email` CHAR(1) DEFAULT 'S' COMMENT 'Enviar PDF por email automaticamente (S/N)',

  -- Municipio
  `codigo_municipio` VARCHAR(10) NULL COMMENT 'Codigo IBGE do municipio (7 digitos)',

  -- Servico
  `codigo_servico` VARCHAR(20) DEFAULT '1.1101.11' COMMENT 'Codigo NBS do servico',
  `descricao_servico` TEXT NULL COMMENT 'Descricao padrao do servico prestado',

  -- Tributacao
  `regime_tributario` TINYINT(1) DEFAULT 1 COMMENT '1=Simples ME/EPP, 4=Simples MEI, 2=Lucro Presumido, 3=Lucro Real',
  `reg_apuracao_sn` TINYINT(1) DEFAULT 1 COMMENT '1=SN fed+mun, 2=SN fed+ISS mun, 3=NFSe fed+mun (obrigatorio XML se ME/EPP)',
  `enviar_im` CHAR(1) DEFAULT 'N' COMMENT 'S=envia IM no XML Nacional, N=omite (validacao CNC SEFIN)',
  `trib_issqn` TINYINT(1) DEFAULT 4 COMMENT '1=Tributavel, 2=Imunidade, 3=Exportacao Servico, 4=Nao Incidencia',
  `aliquota_iss` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Aliquota de ISS municipal (%)',
  `exigibilidade_iss` TINYINT(1) DEFAULT 1 COMMENT 'Tipo de exigibilidade do ISS',
  `incentivo_fiscal` CHAR(1) DEFAULT 'N' COMMENT 'Possui incentivo fiscal (S/N)',

  -- Campos especificos ABRASF (usados quando tipo_emissao = abrasf)
  `abrasf_item_lista_servico` VARCHAR(10) DEFAULT '' COMMENT 'Item da lista de servico ABRASF',
  `abrasf_codigo_cnae` VARCHAR(10) DEFAULT '' COMMENT 'Codigo CNAE da atividade economica',
  `abrasf_codigo_trib_municipio` VARCHAR(20) DEFAULT '' COMMENT 'Codigo de tributacao do municipio',
  `abrasf_numero_rps` INT UNSIGNED DEFAULT 0 COMMENT 'Contador independente de RPS para ABRASF',

  -- Auditoria
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,

  -- Indices
  UNIQUE INDEX idx_empresa (id_empresa),
  INDEX idx_ativo (ativo),
  INDEX idx_tipo (tipo_emissao)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuracoes de NFS-e por empresa/filial';

-- Descricao padrao para locadoras de veiculos
-- UPDATE nfse_configuracoes SET descricao_servico = 'Locacao de veiculo automotor sem condutor, conforme contrato de locacao.' WHERE descricao_servico IS NULL;
```

**Equivalencia no sistema original (`matriz_filial`):**

| Campo novo (`nfse_configuracoes`) | Campo legado (`matriz_filial`) |
|-----------------------------------|--------------------------------|
| `regime_tributario` | `nfse_regime_tributario` |
| `reg_apuracao_sn` | `nfse_reg_apuracao_sn` (migration `sql/012_add_nfse_reg_apuracao_sn.sql`) |
| `enviar_im` | `nfse_enviar_im` (migration `sql/013_add_nfse_enviar_im.sql`) |
| `inscricao_municipal` (prestador) | `ins_muni` |

Valores de `regime_tributario` / `nfse_regime_tributario`: `1=Simples ME/EPP`, `4=Simples MEI`, `2=Lucro Presumido`, `3=Lucro Real`.

### 2.2 Tabela `nfse` (registro principal)

```sql
-- ============================================================
-- Tabela principal de NFS-e emitidas
-- ============================================================
CREATE TABLE IF NOT EXISTS `nfse` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `chave` VARCHAR(45) NOT NULL COMMENT 'Chave do usuario/empresa',
  `id_empresa` INT UNSIGNED NOT NULL COMMENT 'ID da empresa/filial emissora',
  `id_financeiro` INT UNSIGNED NULL COMMENT 'ID do lancamento financeiro vinculado',
  `id_locacao` INT UNSIGNED NULL COMMENT 'ID da locacao vinculada',
  `id_contrato` INT UNSIGNED NULL COMMENT 'ID do contrato vinculado',

  -- Identificacao da NFS-e
  `numero` INT UNSIGNED NULL COMMENT 'Numero da NFS-e',
  `serie` VARCHAR(10) NULL COMMENT 'Serie da NFS-e',
  `codigo_verificacao` VARCHAR(50) NULL COMMENT 'Codigo de verificacao retornado pela SEFIN',
  `chave_acesso` VARCHAR(60) NULL COMMENT 'Chave de acesso da NFS-e',

  -- Dados do Prestador (copia para historico - nao usar FK)
  `prestador_cnpj` VARCHAR(18) NULL COMMENT 'CNPJ do prestador',
  `prestador_razao_social` VARCHAR(255) NULL COMMENT 'Razao social do prestador',
  `prestador_inscricao_municipal` VARCHAR(20) NULL COMMENT 'Inscricao municipal do prestador',

  -- Dados do Tomador (cliente)
  `tomador_cpf_cnpj` VARCHAR(18) NULL COMMENT 'CPF ou CNPJ do tomador',
  `tomador_nome` VARCHAR(255) NULL COMMENT 'Nome ou razao social do tomador',
  `tomador_email` VARCHAR(100) NULL COMMENT 'Email do tomador',
  `tomador_endereco` TEXT NULL COMMENT 'Endereco completo em JSON: {"logradouro","numero","complemento","bairro","cidade","uf","cep"}',

  -- Servico
  `codigo_servico` VARCHAR(20) NULL COMMENT 'Codigo NBS do servico (ex: 1.1101.11)',
  `descricao_servico` TEXT NULL COMMENT 'Descricao detalhada do servico prestado',
  `valor_servicos` DECIMAL(12,2) NULL COMMENT 'Valor total dos servicos',
  `valor_deducoes` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Valor das deducoes (itens nao tributaveis)',
  `itens_nao_tributaveis` TEXT NULL COMMENT 'JSON: [{"descricao":"Combustivel","valor":150.00}]',
  `base_calculo` DECIMAL(12,2) NULL COMMENT 'Base de calculo = valor_servicos - valor_deducoes',

  -- Tributos
  `aliquota_iss` DECIMAL(5,2) NULL COMMENT 'Aliquota de ISS (%)',
  `valor_iss` DECIMAL(12,2) NULL COMMENT 'Valor do ISS',
  `aliquota_ibs` DECIMAL(5,2) DEFAULT 0.10 COMMENT 'Aliquota IBS - 0,1% em 2026',
  `valor_ibs` DECIMAL(12,2) NULL COMMENT 'Valor do IBS',
  `aliquota_cbs` DECIMAL(5,2) DEFAULT 0.90 COMMENT 'Aliquota CBS - 0,9% em 2026',
  `valor_cbs` DECIMAL(12,2) NULL COMMENT 'Valor do CBS',
  `ambiente` TINYINT UNSIGNED DEFAULT 2 COMMENT '1=Producao, 2=Homologacao',

  -- Retencoes
  `iss_retido` CHAR(1) DEFAULT 'N' COMMENT 'ISS retido na fonte (S/N)',
  `valor_iss_retido` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Valor do ISS retido',

  -- Controle
  `status` ENUM('pendente','processando','autorizada','rejeitada','cancelada') DEFAULT 'pendente',
  `tipo_emissao` VARCHAR(20) DEFAULT 'nacional' COMMENT 'nacional, abrasf ou betha',
  `protocolo` VARCHAR(50) NULL COMMENT 'Protocolo de recepcao DPS (Betha - fluxo assincrono)',
  `motivo_rejeicao` TEXT NULL COMMENT 'Motivo da rejeicao pela SEFIN',
  `xml_envio` LONGTEXT NULL COMMENT 'XML/DPS enviado para SEFIN',
  `xml_retorno` LONGTEXT NULL COMMENT 'XML de retorno da SEFIN',
  `pdf_url` VARCHAR(255) NULL COMMENT 'Caminho do PDF (DANFSE)',

  -- Datas
  `data_emissao` DATETIME NULL COMMENT 'Data e hora de emissao',
  `data_competencia` DATE NULL COMMENT 'Data de competencia do servico',
  `data_cancelamento` DATETIME NULL COMMENT 'Data e hora do cancelamento',
  `motivo_cancelamento` TEXT NULL COMMENT 'Motivo do cancelamento',

  -- Controle de email
  `email_enviado` DATETIME NULL COMMENT 'Data/hora do envio do email',
  `email_destinatario` VARCHAR(100) NULL COMMENT 'Email para onde foi enviado',
  `tentativas_envio` TINYINT DEFAULT 1 COMMENT 'Numero de tentativas de envio API',

  -- Auditoria
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,

  -- Indices
  INDEX idx_chave (chave),
  INDEX idx_empresa (id_empresa),
  INDEX idx_financeiro (id_financeiro),
  INDEX idx_locacao (id_locacao),
  INDEX idx_status (status),
  INDEX idx_numero (numero, serie),
  INDEX idx_data_emissao (data_emissao),
  INDEX idx_tomador (tomador_cpf_cnpj),
  INDEX idx_ambiente (ambiente),
  INDEX idx_email_pendente (status, email_enviado)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Notas Fiscais de Servico Eletronicas emitidas';
```

### 2.3 Tabela `nfse_eventos` (log de auditoria)

```sql
-- ============================================================
-- Tabela de eventos/log de auditoria das NFS-e
-- Cada operacao gera um registro de evento
-- ============================================================
CREATE TABLE IF NOT EXISTS `nfse_eventos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_nfse` INT UNSIGNED NOT NULL COMMENT 'ID da NFS-e relacionada',

  -- Dados do evento
  `tipo_evento` VARCHAR(50) NULL COMMENT 'Tipo: emissao, cancelamento, consulta, erro, reenvio',
  `codigo_retorno` VARCHAR(10) NULL COMMENT 'Codigo de retorno da SEFIN/prefeitura',
  `mensagem` TEXT NULL COMMENT 'Mensagem de retorno ou descricao do evento',
  `xml_evento` LONGTEXT NULL COMMENT 'XML completo do evento (requisicao/resposta)',

  -- Auditoria
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  -- Indices
  INDEX idx_nfse (id_nfse),
  INDEX idx_tipo (tipo_evento),
  INDEX idx_codigo (codigo_retorno),
  INDEX idx_data (created_at),

  -- Chave estrangeira com CASCADE (apagar NFS-e apaga eventos)
  CONSTRAINT fk_nfse_eventos_nfse
    FOREIGN KEY (id_nfse)
    REFERENCES nfse(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log de eventos das NFS-e';
```

### 2.4 Diagrama de Status

```
             +------------+
             |  pendente  |  (registro criado, XML nao enviado)
             +-----+------+
                   |
                   v
            +--------------+
            | processando  |  (XML enviado, aguardando resposta)
            +---+------+---+
                |      |
        Sucesso |      | Rejeicao
                v      v
         +----------+  +----------+
         |autorizada|  | rejeitada|
         +----+-----+  +-----+---+
              |               |
         Cancelar        Reenviar (max 3x)
              |               |
              v               v
         +----------+    (volta para processando)
         |cancelada |
         +----------+
```

---

## 3. Classes e Servicos

### 3.1 NFSeService - Orquestrador Principal

Responsavel por coordenar todo o ciclo de vida da NFS-e. Decide qual estrategia (Nacional ou ABRASF) usar baseado na configuracao `tipo_emissao`.

**Metodos publicos:**

| Metodo | Parametros | Retorno | Descricao |
|--------|-----------|---------|-----------|
| `emitir` | `int $idFinanceiro, ?array $dadosExtras` | `array` | Emite NFS-e a partir de registro financeiro |
| `cancelar` | `int $idNFSe, string $motivo` | `array` | Cancela NFS-e autorizada |
| `consultar` | `int $idNFSe` | `array` | Consulta status (roteia automaticamente: Nacional/ABRASF usa chave de acesso, Betha usa protocolo via `consultarStatusBetha`) |
| `reenviar` | `int $idNFSe` | `array` | Reenvia NFS-e rejeitada |
| `listar` | `int $idEmpresa, array $filtros` | `array` | Lista NFS-e com filtros |
| `enviarPorEmail` | `int $idNFSe` | `array` | Envia PDF por email ao tomador |

**Metodos privados chave:**

| Metodo | Descricao |
|--------|-----------|
| `emitirABRASF` | Fluxo de emissao especifico para ABRASF |
| `emitirBetha` | Fluxo de emissao Betha DPS (assincrono: envia DPS, consulta status) |
| `cancelarABRASF` | Fluxo de cancelamento ABRASF |
| `cancelarBetha` | Fluxo de cancelamento Betha DPS |
| `consultarStatusBetha` | Consulta status de DPS pendente (usado pelo cron) |
| `montarDadosNFSe` | Monta array de dados a partir do financeiro + config |
| `resolverCodigoMunicipioTomador` | Resolve o codigo IBGE do municipio do tomador quando confiavel |
| `criarRegistroNFSe` | Insere registro na tabela nfse com status pendente |
| `atualizarStatusNFSe` | Atualiza status e motivo |
| `atualizarNFSeAutorizada` | Salva dados de autorizacao (chave, codigo, XML retorno) |
| `atualizarNFSeCancelada` | Salva dados de cancelamento |
| `registrarEvento` | Insere evento na tabela nfse_eventos |
| `proximoNumero` | Incrementa e retorna proximo numero (Nacional ou ABRASF) |

**Logica de roteamento:**

```php
// No metodo emitir():
$config = $this->buscarConfiguracao($idEmpresa);

if (($config['tipo_emissao'] ?? 'nacional') === 'abrasf') {
    return $this->emitirABRASF($idFinanceiro, $dadosExtras);
} elseif ($config['tipo_emissao'] === 'betha') {
    return $this->emitirBetha($idFinanceiro, $dadosExtras);
}

// Fluxo Nacional (padrao)
// ...
```

### 3.2 NFSeXML - XML Nacional (DPS)

Gera o XML no formato DPS (Declaracao de Prestacao de Servico) para o Sistema Nacional SEFIN.

**Namespace:** `http://www.sped.fazenda.gov.br/nfse`
**Versao:** `1.00`

| Metodo | Descricao |
|--------|-----------|
| `gerarDPS(array $dados): string` | Gera XML DPS completo |
| `gerarIdDPS(array $dados): string` | Gera ID de 45 chars: DPS + cMun(7) + tpInsc(1) + nInsc(14) + serie(5) + nDPS(15) |
| `gerarXMLCancelamento(string $chave, string $motivo, array $dados): string` | XML de cancelamento |
| `prepararParaEnvio(string $xml): string` | Comprime (gzip) + Base64 encode |
| `validarSchema(string $xml, string $xsdPath): bool` | Valida contra XSD |
| `parseRetorno(string $xml): array` | Extrai dados da resposta SEFIN |

### 3.3 NFSeABRASFXML - XML ABRASF (RPS)

Gera o XML no formato RPS (Recibo Provisorio de Servico) para sistemas municipais ABRASF 2.04.

**Namespace:** `http://www.abrasf.org.br/nfse.xsd`
**Versao:** `2.04`

| Metodo | Descricao |
|--------|-----------|
| `gerarRPS(array $dados): string` | Gera XML RPS completo |
| `gerarXMLCancelamento(...): string` | XML de cancelamento ABRASF |
| `gerarCabecalho(): string` | Cabecalho XML ABRASF |
| `parseRetornoNfse(string $xml): array` | Extrai dados da autorizacao |
| `parseRetornoCancelamento(string $xml): array` | Extrai dados do cancelamento |

### 3.4 NFSeAPI - API Nacional (REST)

Comunicacao com o Sistema Nacional SEFIN via REST + mTLS.

**URLs:**
- Producao: `https://sefin.nfse.gov.br/SefinNacional`
- Homologacao: `https://sefin.producaorestrita.nfse.gov.br/API/SefinNacional`

| Metodo | HTTP | Endpoint | Descricao |
|--------|------|----------|-----------|
| `enviar` | POST | `/nfse` | Envia DPS (payload: `{"dpsXmlGZipB64": "..."}`) |
| `consultar` | GET | `/nfse/{chave}` | Consulta NFS-e por chave |
| `cancelar` | POST | `/nfse/{chave}/eventos` | Registra evento de cancelamento `e101101` (payload: `{"pedidoRegistroEventoXmlGZipB64": "..."}`) |
| `consultarEventos` | GET | `/nfse/{chave}/eventos` | Consulta eventos |
| `testarConexao` | GET | `/` | Testa conexao mTLS |

**Timeouts:** conexao 30s, requisicao 60s

### 3.5 NFSeABRASFAPI - API ABRASF (SOAP)

Comunicacao com sistemas municipais via SOAP 1.1.

**URLs (exemplo Brasilia/DF - ISSNet):**
- Producao: `https://df.issnetonline.com.br/webservicenfse204/nfse.asmx`
- Homologacao: `https://www.issnetonline.com.br/homologaabrasf/webservicenfse204/nfse.asmx`

| Operacao SOAP | Descricao |
|--------------|-----------|
| `GerarNfse` | Emitir NFS-e |
| `CancelarNfse` | Cancelar NFS-e |
| `ConsultarNfsePorRps` | Consultar por numero RPS |

### 3.6 NFSeBethaXML - XML Betha DPS

Gera o XML no formato DPS para o provedor Betha Cloud. Estrutura identica ao Nacional (NFSeXML), mas com namespace Betha e atributo `id` minusculo.

**Namespace:** `http://www.betha.com.br/e-nota-dps`
**Versao:** `1.00`
**Atributo ID:** `id` (minusculo - diferente do Nacional que usa `Id`)

| Metodo | Descricao |
|--------|-----------|
| `gerarDPS(array $dados): string` | Gera XML DPS com namespace Betha |
| `gerarIdDPS(array $dados): string` | Gera ID de 45 chars (mesmo formato Nacional) |
| `gerarXMLCancelamento(string $chave, string $motivo, int $ambiente): string` | XML de cancelamento |
| `parseRetornoDps(string $xml): array` | Parseia resposta RecepcionarDps (protocolo, status) |
| `parseRetornoStatus(string $xml): array` | Parseia resposta ConsultarStatusDps (NFSe autorizada/rejeitada) |
| `parseRetornoCancelamento(string $xml): array` | Parseia resposta de cancelamento |

**Nota:** Resposta Betha usa prefixo `ns2:` nos elementos (ex: `ns2:chaveAcesso`, `ns2:numeroNotaFiscal`). O parser usa `getElementsByTagNameNS('*', $tagName)` para resolver.

### 3.7 NFSeBethaAPI - API Betha DPS (SOAP)

Comunicacao com o webservice Betha Cloud via SOAP 1.1 + mTLS.

**Endpoint:** `https://nota-eletronica.betha.cloud/dps/ws`
**WSDL:** `https://nota-eletronica.betha.cloud/dps/ws/service.wsdl`
**Namespace:** `http://www.betha.com.br/e-nota-dps`

| Operacao SOAP | SOAPAction | Descricao |
|--------------|------------|-----------|
| `RecepcionarDps` | `"RecepcionarDps"` | Envia DPS (retorna protocolo) |
| `ConsultarStatusDps` | `"ConsultarStatusDps"` | Consulta status (retorna NFSe se autorizada) |
| `RecepcionarEventoCancelamento` | `"RecepcionarEventoCancelamento"` | Cancela NFSe |

**Fluxo assincrono (diferente de Nacional e ABRASF):**
1. `RecepcionarDps` → retorna `protocolo` (aceite da DPS)
2. `ConsultarStatusDps` → polling ate `statusProcessamento = "Processado com sucesso"`
3. Resposta contem: `chaveAcesso`, `numeroNotaFiscal`, `numeroDps`

**Envelope SOAP:**
```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
  <soapenv:Header/>
  <soapenv:Body>
    <RecepcionarDpsEnvio xmlns="http://www.betha.com.br/e-nota-dps">
      <DPS versao="1.00">
        <!-- XML DPS assinado -->
      </DPS>
    </RecepcionarDpsEnvio>
  </soapenv:Body>
</soapenv:Envelope>
```

### 3.8 NFSeCertificado - Gestao de Certificado Digital

| Metodo | Descricao |
|--------|-----------|
| `upload(array $file, int $idEmpresa, string $chave): array` | Upload .pfx/.p12 (salva com permissao 0600) |
| `validar(string $arquivo, string $senha): array` | Valida certificado e senha |
| `lerDados(string $arquivo, string $senha): array` | Extrai: CNPJ, razao_social, valido_de, valido_ate, emissor, serial |
| `getChavePrivada(string $arquivo, string $senha): string` | Chave privada em PEM |
| `getChavePublica(string $arquivo, string $senha): string` | Certificado publico em PEM |
| `extrairPEM(string $pfx, string $senha): array` | Extrai para arquivos .pem separados (para cURL) |
| `isValido(string $arquivo, string $senha): bool` | Verifica se nao expirou |
| `diasParaExpirar(string $arquivo, string $senha): int` | Dias ate expirar |
| `criptografarSenha(string $senha): string` | Criptografa com AES-256-CBC + IV |
| `descriptografarSenha(string $criptografada): string` | Descriptografa senha |
| `remover(string $arquivo): bool` | Remove arquivo do servidor |

**Diretorio de upload:** `uploads/certificados/`
**Formato nome:** `{chave}_{idEmpresa}_{timestamp}.pfx`
**Permissao:** `0600` (somente leitura/escrita do dono)

### 3.7 NFSeAssinatura - Assinatura Digital XML

| Metodo | Descricao |
|--------|-----------|
| `assinar(string $xml, string $certArquivo, string $certSenha, string $tagToSign, string $algoritmo, string $idAttribute = 'Id'): array` | Assina XML com XMLDSIG |
| `verificar(string $xml): array` | Verifica validade da assinatura |
| `possuiAssinatura(string $xml): bool` | Checa se XML esta assinado |

**Algoritmos:**
- `sha256` - para Nacional e Betha DPS (RSA-SHA256)
- `sha1` - para ABRASF (RSA-SHA1)

**Parametro `$idAttribute`:**
- `'Id'` (default) - Nacional e ABRASF usam atributo `Id` maiusculo
- `'id'` - Betha DPS exige atributo `id` minusculo (XSD Betha valida case)

**Estrutura da assinatura XMLDSIG:**
```xml
<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
  <SignedInfo>
    <CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
    <SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
    <Reference URI="#ID_DO_ELEMENTO">
      <Transforms>
        <Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
        <Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </Transforms>
      <DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <DigestValue>...</DigestValue>
    </Reference>
  </SignedInfo>
  <SignatureValue>...</SignatureValue>
  <KeyInfo>
    <X509Data>
      <X509Certificate>...</X509Certificate>
    </X509Data>
  </KeyInfo>
</Signature>
```

### 3.8 NFSePDF - Geracao DANFSE

| Metodo | Descricao |
|--------|-----------|
| `gerar(array $dadosNFSe): string` | Gera PDF da DANFSE, retorna caminho do arquivo |

**Dependencias:** mPDF (geracao PDF), QR Code (codigo de verificacao)

### 3.9 NFSeErros - Mapeamento de Erros

Classe estatica com 70+ codigos de erro mapeados.

| Metodo | Descricao |
|--------|-----------|
| `getMensagem(string $codigo): string` | Mensagem amigavel |
| `getInstrucao(string $codigo): string` | Como corrigir o erro |
| `getCategoria(string $codigo): string` | Categoria (certificado, conexao, xml, etc.) |
| `getErro(string $codigo): array` | Dados completos do erro |
| `mapearErroAPI(string $codigoSEFIN): string` | Mapeia codigo SEFIN para codigo interno |
| `formatarParaUsuario(string $codigo): array` | Dados formatados para exibir ao usuario |
| `isRecuperavel(string $codigo): bool` | Se pode reenviar automaticamente |

**Categorias de erro:**
- `certificado` - CERT_EXPIRADO, CERT_INVALIDO, CERT_SENHA, CERT_NAO_ENCONTRADO
- `conexao` - CONN_TIMEOUT, CONN_SSL, CONN_REFUSED, SERVICO_INDISPONIVEL
- `prestador` - CNPJ_INVALIDO, IM_INVALIDA, SERIE_INVALIDA
- `tomador` - TOMADOR_NAO_INFORMADO, TOMADOR_CPF_INVALIDO, TOMADOR_CNPJ_INVALIDO
- `servico` - SERVICO_NAO_INFORMADO, VALOR_INVALIDO, ALIQUOTA_INVALIDA
- `xml` - XML_INVALIDO, XML_ASSINATURA, XML_SCHEMA
- `duplicidade` - NOTA_DUPLICADA, RPS_DUPLICADO
- `cancelamento` - CANCEL_PRAZO, CANCEL_JA_CANCELADA

**Erros recuperaveis (reenvio automatico):** CONN_TIMEOUT, CONN_REFUSED, SERVICO_INDISPONIVEL, CONN_CURL, MANUTENCAO

---

## 4. Telas do Sistema

### 4.1 Listagem/Dashboard (`nfse.php`)

**Funcionalidades:**
- Filtros: status (todos/pendente/autorizada/rejeitada/cancelada), periodo (data inicio/fim), empresa/filial, tomador (busca por nome/CPF/CNPJ), limite de registros
- Estatisticas rapidas: contadores de autorizadas, rejeitadas, canceladas no periodo
- DataTable AJAX com colunas: numero, serie, data emissao, tomador, valor, status (badge colorido), acoes
- Acoes por registro:
  - Visualizar (todos os status)
  - Download XML envio/retorno (autorizada)
  - Download PDF (autorizada)
  - Reenviar (rejeitada)
  - Cancelar (autorizada)

### 4.2 Emissao (`nfseEmitir.php`)

**Funcionalidades:**
- Recebe `id_financeiro` como parametro
- Exibe dados do prestador (empresa emissora)
- Exibe dados do tomador (cliente vinculado ao financeiro)
- Exibe valor total do servico
- Campo para itens nao tributaveis / deducoes:
  - Adicao dinamica de itens (descricao + valor)
  - Exemplos: combustivel, multas, pedagios, lavagem
  - Cada item e salvo em JSON na coluna `itens_nao_tributaveis`
- Calculo automatico de impostos:
  - Base calculo = Valor servicos - Deducoes
  - ISS = Base x Aliquota ISS (se tributavel)
  - IBS = Base x 0.10% (2026)
  - CBS = Base x 0.90% (2026)
- Resumo dos valores antes de confirmar
- Botao "Emitir NFS-e" com confirmacao

### 4.3 Visualizacao (`nfseVisualizar.php`)

**Funcionalidades:**
- Dados completos da NFS-e: numero, serie, chave de acesso, codigo de verificacao
- Dados do tomador: nome, CPF/CNPJ, email, endereco
- Dados do servico: codigo NBS, descricao
- Valores: servicos, deducoes, base calculo, ISS, IBS, CBS
- Indicador de ambiente (Producao/Homologacao)
- Timeline de eventos (lista cronologica de emissao, rejeicao, reenvio, cancelamento, consulta)
- Botoes de acao conforme status:
  - Autorizada: Download XML, Download PDF, Reenviar por email, Cancelar, Consultar status
  - Rejeitada: Reenviar, Ver detalhes do erro
  - Cancelada: Download XML (somente visualizacao)

### 4.4 Cancelamento (`nfseCancelar.php`)

**Funcionalidades:**
- Dados resumidos da NFS-e e do tomador
- Campo motivo do cancelamento (textarea, minimo 15 caracteres)
- Aviso sobre prazo de 30 dias para cancelamento
- Alerta visual se prazo excedido
- Dialog de confirmacao antes de enviar
- Exibe resultado (sucesso ou erro da SEFIN)

### 4.5 Configuracoes (`nfseConfiguracoes.php`)

**Funcionalidades:**
- **Certificado Digital:**
  - Upload de arquivo .pfx/.p12
  - Exibe status: CNPJ vinculado, razao social, data de validade, dias para expirar
  - Alerta se certificado proximo de expirar ou expirado
  - Botao remover certificado

- **Configuracao Geral:**
  - Ativo (S/N)
  - Ambiente (Producao/Homologacao)
  - Tipo emissao (Nacional/ABRASF)
  - Serie
  - Emissao automatica (S/N)
  - Enviar email automaticamente (S/N)

- **Dados Fiscais:**
  - Codigo servico NBS (ex: 1.1101.11)
  - Descricao do servico (texto livre)
  - Tipo tributacao ISSQN (Tributavel/Imunidade/Exportacao/Nao Incidencia)
  - Aliquota ISS (%) - habilitado somente se tributavel
  - Regime tributario (Simples Nacional/Lucro Presumido/Lucro Real)
  - Codigo municipio IBGE

- **Campos ABRASF** (visiveis quando tipo_emissao = abrasf):
  - Item lista servico
  - Codigo CNAE
  - Codigo tributacao municipio
  - Incentivo fiscal (S/N)

- **Teste de Conexao:**
  - Botao para testar conexao com SEFIN/prefeitura usando certificado
  - Exibe resultado do teste (sucesso, erro SSL, timeout, etc.)

---

## 5. Fluxos de Negocio

### 5.1 Fluxo de Emissao (completo)

```
1. Usuario acessa tela de emissao com id_financeiro
2. Sistema valida:
   - Registro financeiro existe
   - Nao existe NFS-e ja emitida para esse financeiro
   - NFS-e esta ativa para a empresa
   - Certificado digital valido e nao expirado
3. Exibe dados para conferencia (prestador, tomador, valores)
4. Usuario informa deducoes (opcional) e confirma
5. NFSeService.emitir():
   a. Incrementa numero (Nacional ou ABRASF)
   b. Monta array de dados (montarDadosNFSe)
   c. Cria registro no BD com status 'pendente'
   d. Gera XML (DPS ou RPS conforme tipo_emissao)
   e. Assina XML digitalmente (SHA256 ou SHA1)
   f. Salva XML de envio no BD
   g. Atualiza status para 'processando'
   h. Envia para SEFIN/prefeitura (REST ou SOAP)
   i. Processa resposta:
      - AUTORIZADA: salva XML retorno, chave, codigo, gera PDF, registra evento
      - REJEITADA: salva motivo, registra evento de erro
   j. Retorna resultado para o usuario
6. Usuario ve mensagem de sucesso ou detalhes do erro
```

### 5.2 Fluxo de Cancelamento

```
1. Usuario acessa cancelamento de NFS-e autorizada
2. Sistema valida:
   - Status e 'autorizada'
   - Aviso se prazo de 30 dias excedido
3. Usuario informa motivo (min 15 chars)
4. NFSeService.cancelar():
   a. Nacional: gera XML `pedRegEvento` do evento `e101101`
   b. Nacional: assina `infPedReg` e envia para `/nfse/{chave}/eventos`
   c. ABRASF/Betha: usa o fluxo de cancelamento especifico do provedor
   d. Se aceito: atualiza status 'cancelada', salva data/motivo, registra evento
   e. Se rejeitado: retorna erro da SEFIN
5. Usuario ve resultado
```

### 5.3 Fluxo de Reenvio

```
1. NFS-e com status 'rejeitada'
2. Usuario clica "Reenviar" ou CRON automatico
3. Sistema verifica tentativas (max 3)
4. Remove o registro rejeitado e chama emitir() novamente
5. Gera nova DPS com dados/cadastro ATUAIS (nao reutiliza XML antigo)
6. Processa nova resposta (pode autorizar ou rejeitar novamente)
```

**Importante:** Apos corrigir cadastro (IM, regime Simples, regApTribSN etc.), o reenvio reflete as configuracoes salvas na empresa.

### 5.4 Dados montados para emissao (montarDadosNFSe)

```php
[
    'numero' => 123,                          // Proximo numero sequencial
    'serie' => 'DPS',                         // Serie configurada
    'ambiente' => 2,                          // 1=Producao, 2=Homologacao
    'municipio_codigo' => '5300108',          // Codigo IBGE do prestador/local emissao (7 digitos)
    'prestador' => [
        'cnpj' => '12345678000199',           // 14 digitos, sem formatacao
        'razao_social' => 'Empresa XYZ Ltda',
        'inscricao_municipal' => '5749719',   // ins_muni — vai ao XML Nacional se nfse_enviar_im=S
        'telefone' => '6132001234',
        'email' => 'contato@empresa.com',
        'regime_tributario' => 1,             // 1=ME/EPP, 4=MEI, 2=Presumido, 3=Real
        'reg_apuracao_sn' => 1                // Obrigatorio no XML quando ME/EPP (opSimpNac=3)
    ],
    'tomador' => [
        'cpf_cnpj' => '12345678901',         // 11 (CPF) ou 14 (CNPJ) digitos
        'nome' => 'Joao da Silva',
        'email' => 'joao@email.com',
        'endereco' => '{"logradouro":"Rua A","numero":"100","bairro":"Centro","cidade":"Brasilia","uf":"DF","cep":"70000000","codigo_municipio":"5300108"}'
    ],
    'servico' => [
        'codigo' => '1.1101.11',              // Codigo NBS
        'descricao' => 'Locacao de veiculo automotor sem condutor...'
    ],
    'valores' => [
        'servicos' => 1500.00,                // Valor total
        'deducoes' => 200.00,                 // Soma dos itens nao tributaveis
        'aliquota_iss' => 0.00,               // 0 se nao incidencia
        'trib_issqn' => 4,                    // 1=Tributavel, 2=Imunidade, 3=Exportacao, 4=Nao Incidencia
        'aliquota_ibs' => 0.10,               // IBS 2026
        'aliquota_cbs' => 0.90,               // CBS 2026
        'iss_retido' => 'N'                   // S ou N
    ],
    'itens_nao_tributaveis' => [              // Deducoes detalhadas
        ['descricao' => 'Combustivel', 'valor' => 150.00],
        ['descricao' => 'Pedagio', 'valor' => 50.00]
    ]
]
```

**Regra do municipio do tomador:** `municipio_codigo` na raiz do array representa o municipio do prestador/local de emissao e tambem compoe o ID da DPS. O endereco do tomador deve usar `tomador.endereco.codigo_municipio`, que precisa ser o codigo IBGE do municipio do tomador. O sistema so envia o endereco do tomador nos XMLs Nacional, Betha e ABRASF quando esse codigo IBGE tem 7 digitos e o CEP tem 8 digitos. Se o municipio do tomador nao puder ser resolvido com seguranca, o endereco do tomador e omitido para evitar rejeicoes como `E0240` (CEP nao pertence ao municipio informado).

---

## 6. Estrutura XML

Cada exemplo abaixo indica **duas origens possiveis**:

1. **Gerado pelo 7Carros** — saida real de `NFSeService` + classes XML (`NFSeXML.php`, `NFSeABRASFXML.php`, `NFSeBethaXML.php`) + assinatura (`NFSeAssinatura.php`).
2. **Referencia externa** — XML de clientes/notas autorizadas em `z-exemplos-nfse-de-clientes/` (util para validar retorno da prefeitura/SEFIN, mas pode divergir do gerador interno).

Certificados (`X509Certificate`) foram truncados por legibilidade.

### 6.0 Fluxo de montagem no codigo (7Carros)

```
NFSeService::emitir()
  → montarDadosNFSe()          # financeiro + config + cliente
  → resolverInscricaoMunicipalXml()  # IM: Nacional condicional; ABRASF/Betha sempre
  → resolverCodigoMunicipioTomador() # endereco tomador so se IBGE+CEP validos

Nacional:  NFSeXML::gerarDPS()           → assinar(infDPS, sha256, Id)
ABRASF:    NFSeABRASFXML::gerarRPS()     → assinar(InfDeclaracaoPrestacaoServico, sha1)
Betha:     NFSeBethaXML::gerarDPS()      → assinar(infDPS, sha256, id)
```

| Regra | Onde no codigo | Comportamento |
|-------|----------------|---------------|
| IM no XML Nacional | `NFSeService::resolverInscricaoMunicipalXml()` | Envia `<IM>` **somente** se `nfse_enviar_im = S`; senao omite (evita E0120) |
| IM ABRASF/Betha | Mesmo metodo | Sempre usa `ins_muni` do cadastro |
| Endereco tomador | `NFSeXML` / `NFSeBethaXML` / `NFSeABRASFXML` | Omitido se `codigo_municipio` (7 dig.) ou CEP (8 dig.) invalidos |
| `opSimpNac` Nacional | `NFSeXML::resolverOpSimpNac()` | ME/EPP→3, MEI→2, Presumido/Real→1 |
| `opSimpNac` Betha | `NFSeBethaXML::adicionarPrestador()` | **Diferente:** regime=1→1, demais→2 (nao usa mapa 1/2/3 do Nacional) |
| `totTrib` Nacional | `NFSeXML::adicionarTributos()` | Bloco `<vTotTrib>` com CBS/IBS/ISS estimados |
| `totTrib` Betha | `NFSeBethaXML::adicionarTributos()` | Apenas `<indTotTrib>0</indTotTrib>` |
| ID da DPS | `gerarIdDPS()` em Nacional e Betha | `DPS` + cMun(7) + tpInsc(1) + nInsc(14) + serie(5) + nDPS(15) |
| ID do RPS ABRASF | `NFSeABRASFXML::gerarRPS()` | `Id="rps_{numero}"` (ex.: `rps_1`) |
| Versao DPS 7Carros | `NFSeXML` / `NFSeBethaXML` | `versao="1.00"` na raiz `<DPS>` |
| Envio Nacional | `NFSeXML::prepararParaEnvio()` | gzip + Base64 → `{"dpsXmlGZipB64":"..."}` |

**Referencias externas (notas reais de clientes):**

| Provedor | Arquivo | Empresa / Municipio |
|----------|---------|---------------------|
| Nacional SEFIN | `z-exemplos-nfse-de-clientes/43114032258475786000183000000000000126011794447071.xml` | DL LOCADORA — Lajeado/RS |
| ABRASF ISSNet | `z-exemplos-nfse-de-clientes/0828516000129_NotaFiscaldeServiçoEletrônicaNFSe_000001.xml` | MOVI RENT A CAR — Brasilia/DF |
| Betha (consulta) | `z-exemplos-nfse-de-clientes/LoteNfse_1379658193_1775139509640-2.xml` | TELES LOCADORA — Correia Pinto/SC |

### 6.1 XML Nacional - DPS (Declaracao de Prestacao de Servico)

#### 6.1.a Referencia externa (NFS-e autorizada — EmissorWeb)

DPS extraida da NFS-e #1 autorizada em 2026-01-05. **Nao foi gerada pelo 7Carros** (`verAplic=EmissorWeb`, `versao=1.01`). Util para ver layout aceito pela SEFIN.

```xml
<?xml version="1.0" encoding="utf-8"?>
<DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01">
  <infDPS Id="DPS431140325847578600018300900000000000000001">
    <tpAmb>1</tpAmb>
    <dhEmi>2026-01-05T08:23:37-03:00</dhEmi>
    <verAplic>EmissorWeb_1.4.0.26</verAplic>
    <serie>900</serie>
    <nDPS>1</nDPS>
    <dCompet>2026-01-05</dCompet>
    <tpEmit>1</tpEmit>
    <cLocEmi>4311403</cLocEmi>
    <prest>
      <CNPJ>58475786000183</CNPJ>
      <fone>5198536700</fone>
      <email>LOCADORA@DLMOTORS.COM.BR</email>
      <regTrib>
        <opSimpNac>1</opSimpNac>
        <regEspTrib>0</regEspTrib>
      </regTrib>
    </prest>
    <toma>
      <CPF>00672738074</CPF>
      <xNome>LETICIA LINKE MATTES</xNome>
    </toma>
    <serv>
      <locPrest><cLocPrestacao>4311403</cLocPrestacao></locPrest>
      <cServ>
        <cTribNac>990101</cTribNac>
        <xDescServ>LOCAÇÃO</xDescServ>
        <cNBS>111011200</cNBS>
      </cServ>
    </serv>
    <valores>
      <vServPrest><vServ>1.00</vServ></vServPrest>
      <trib>
        <tribMun><tribISSQN>4</tribISSQN><tpRetISSQN>1</tpRetISSQN></tribMun>
        <totTrib>
          <vTotTrib>
            <vTotTribFed>0.01</vTotTribFed>
            <vTotTribEst>0.00</vTotTribEst>
            <vTotTribMun>0.00</vTotTribMun>
          </vTotTrib>
        </totTrib>
      </trib>
    </valores>
  </infDPS>
</DPS>
```

#### 6.1.b Gerado pelo 7Carros (`NFSeXML.php`)

Estrutura produzida por `gerarDPS()` + `NFSeAssinatura::assinar(..., 'infDPS', 'sha256', 'Id')`. Diferencas do exemplo externo: `versao="1.00"`, `verAplic="7Carros v8.3"`, email em maiusculas, `<cTribNac>` so quando `trib_issqn != 1`, NBS com pad 9 digitos.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.00">
  <infDPS Id="DPS53001082584757860001839000000000000000123">
    <tpAmb>2</tpAmb>
    <dhEmi>2026-06-16T10:30:00-03:00</dhEmi>
    <verAplic>7Carros v8.3</verAplic>
    <serie>90001</serie>
    <nDPS>123</nDPS>
    <dCompet>2026-06-16</dCompet>
    <tpEmit>1</tpEmit>
    <cLocEmi>5300108</cLocEmi>
    <prest>
      <CNPJ>58475786000183</CNPJ>
      <!-- <IM>5749719</IM> somente se nfse_enviar_im=S -->
      <fone>6132001234</fone>
      <email>CONTATO@EMPRESA.COM</email>
      <regTrib>
        <opSimpNac>3</opSimpNac>
        <regApTribSN>1</regApTribSN>
        <regEspTrib>0</regEspTrib>
      </regTrib>
    </prest>
    <toma>
      <CPF>12345678901</CPF>
      <xNome>JOAO DA SILVA</xNome>
      <end>
        <endNac>
          <cMun>5300108</cMun>
          <CEP>70000000</CEP>
        </endNac>
        <xLgr>RUA A</xLgr>
        <nro>100</nro>
        <xBairro>CENTRO</xBairro>
      </end>
    </toma>
    <serv>
      <locPrest><cLocPrestacao>5300108</cLocPrestacao></locPrest>
      <cServ>
        <cTribNac>990101</cTribNac>
        <xDescServ>LOCACAO DE VEICULO AUTOMOTOR SEM CONDUTOR, CONFORME CONTRATO DE LOCACAO.</xDescServ>
        <cNBS>111011100</cNBS>
      </cServ>
    </serv>
    <valores>
      <vServPrest><vServ>1500.00</vServ></vServPrest>
      <trib>
        <tribMun><tribISSQN>4</tribISSQN><tpRetISSQN>1</tpRetISSQN></tribMun>
        <totTrib>
          <vTotTrib>
            <vTotTribFed>13.50</vTotTribFed>
            <vTotTribEst>0.00</vTotTribEst>
            <vTotTribMun>0.00</vTotTribMun>
          </vTotTrib>
        </totTrib>
      </trib>
    </valores>
  </infDPS>
  <Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><!-- SHA256 --></Signature>
</DPS>
```

**Mapeamento cadastro → XML Nacional (`regTrib`):**

| Cadastro (`regime_tributario`) | XML `opSimpNac` | Envia `regApTribSN`? |
|-------------------------------|-----------------|----------------------|
| 1 — Simples ME/EPP | 3 | Sim |
| 4 — Simples MEI | 2 | Nao |
| 2 — Lucro Presumido | 1 | Nao |
| 3 — Lucro Real | 1 | Nao |

| Cadastro (`reg_apuracao_sn`) | XML `regApTribSN` | Quando usar |
|-----------------------------|-------------------|-------------|
| 1 | Federais e municipais pelo SN | Padrao para locadoras ME/EPP |
| 2 | Federais pelo SN; ISS pela NFS-e | ISS conforme legislacao municipal |
| 3 | Federais e municipais pela NFS-e | Apuracao via NFS-e (fed.+mun.) |

**Regras do prestador no Nacional:**

- `<IM>`: enviar **somente** quando `nfse_enviar_im = S` (legado) / `enviar_im = S` (novo schema) **e** `ins_muni` preenchida; normalizar para **apenas digitos**. Default `N` evita E0120 quando CNC nao tem cadastro complementar do CNPJ.
- **E0116 vs E0120:** dependem do cadastro CNC do CNPJ no municipio emissor (`cLocEmi`), nao de regra fixa por cidade. CNC com complemento exige IM (E0116 se ausente); CNC sem complemento proibe IM (E0120 se enviada).
- `<regApTribSN>`: incluir **somente** quando `opSimpNac = 3` (Simples ME/EPP).
- **Nao copiar** este mapeamento para Betha — namespace e regras proprias (`NFSeBethaXML.php`).

**Formato do ID DPS (45 caracteres):**
```
DPS + cMun(7) + tpInsc(1) + nInsc(14) + serie(5) + nDPS(15)
DPS + 5300108 + 2          + 12345678000199 + DPS00 + 000000000000123
```

**Conversao NBS:** `1.1101.11` -> remove pontos -> `1110111` -> pad direita 9 digitos -> `111011100`

**Envio:** XML e comprimido com gzip e codificado em Base64: `{"dpsXmlGZipB64": "H4sIAAAA..."}`

### 6.2 XML ABRASF - RPS (Recibo Provisorio de Servico) v2.04

#### 6.2.a Referencia externa (NFS-e autorizada ISSNet Brasilia)

Bloco `InfDeclaracaoPrestacaoServico` da NFS-e #1 real (MOVI RENT A CAR). **Nao inclui** sub-bloco `Rps/IdentificacaoRps` — presente apenas no envio.

#### 6.2.b Gerado pelo 7Carros (`NFSeABRASFXML::gerarRPS()`)

Mesmos dados da nota real acima, conforme `gerarRPS()` + assinatura SHA1 em `InfDeclaracaoPrestacaoServico`. Detalhes do codigo:

- `Id="rps_{numero}"` (nao inclui CNPJ)
- `DataEmissao` e `Competencia` = `date('Y-m-d')` no momento da emissao
- Serie padrao `UNICA` se vazia (`NFSeService::emitirABRASF()`)
- `Valores`: sem `BaseCalculo` nem `ValorLiquidoNfse` (diferente de alguns exemplos ABRASF genericos)
- `IssRetido` apenas uma vez dentro de `<Servico>`
- `OptanteSimplesNacional`: `1` se `regime_tributario=1`, senao `2` (MEI nao tem valor separado)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<GerarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">
  <Rps>
    <InfDeclaracaoPrestacaoServico Id="rps_1">

      <Rps>
        <IdentificacaoRps>
          <Numero>1</Numero>
          <Serie>UNICA</Serie>
          <Tipo>1</Tipo>
        </IdentificacaoRps>
        <DataEmissao>2026-02-25</DataEmissao>
        <Status>1</Status>
      </Rps>

      <Competencia>2026-02-25</Competencia>

      <Servico>
        <Valores>
          <ValorServicos>1112.28</ValorServicos>
          <ValorDeducoes>0.00</ValorDeducoes>
          <ValorPis>0.00</ValorPis>
          <ValorCofins>0.00</ValorCofins>
          <ValorInss>0.00</ValorInss>
          <ValorIr>0.00</ValorIr>
          <ValorCsll>0.00</ValorCsll>
          <OutrasRetencoes>0.00</OutrasRetencoes>
          <ValorIss>0.00</ValorIss>
          <Aliquota>0.0000</Aliquota>
          <DescontoIncondicionado>0.00</DescontoIncondicionado>
          <DescontoCondicionado>0.00</DescontoCondicionado>
        </Valores>
        <IssRetido>2</IssRetido>
        <ItemListaServico>99.99</ItemListaServico>
        <CodigoCnae>7711000</CodigoCnae>
        <CodigoTributacaoMunicipio>771100000</CodigoTributacaoMunicipio>
        <CodigoNbs>111011100</CodigoNbs>
        <Discriminacao>Locação de veículo sem condutor conforme contrato...</Discriminacao>
        <CodigoMunicipio>5300108</CodigoMunicipio>
        <ExigibilidadeISS>1</ExigibilidadeISS>
        <MunicipioIncidencia>5300108</MunicipioIncidencia>
      </Servico>

      <Prestador>
        <CpfCnpj><Cnpj>54265333000171</Cnpj></CpfCnpj>
        <InscricaoMunicipal>0828516000129</InscricaoMunicipal>
      </Prestador>

      <TomadorServico>
        <IdentificacaoTomador>
          <CpfCnpj><Cpf>03216444171</Cpf></CpfCnpj>
        </IdentificacaoTomador>
        <RazaoSocial>ADÃO TORQUATO SOBRINHO</RazaoSocial>
        <Endereco>
          <Endereco>Quadra 110, Conjunto 4</Endereco>
          <Numero>10</Numero>
          <Bairro>Recanto das Emas</Bairro>
          <CodigoMunicipio>5300108</CodigoMunicipio>
          <Uf>DF</Uf>
          <Cep>72602206</Cep>
        </Endereco>
      </TomadorServico>

      <OptanteSimplesNacional>1</OptanteSimplesNacional>
      <IncentivoFiscal>2</IncentivoFiscal>

    </InfDeclaracaoPrestacaoServico>
  </Rps>
</GerarNfseEnvio>
```

Assinatura: **RSA-SHA1** sobre `InfDeclaracaoPrestacaoServico` (digest real da nota autorizada: `8uA8MOc8DbMw/J4iSvRdbmmrIEc=`).

### 6.3 XML de Cancelamento - Nacional

Gerado por `NFSeXML::gerarXMLCancelamento()`. ID do pedido: `PRE` + chave (44 dig.) + `101101`. Assinado em `infPedReg` (SHA256). Enviado via `pedidoRegistroEventoXmlGZipB64` em `POST /nfse/{chave}/eventos`.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<pedRegEvento xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01">
  <infPedReg Id="PRE43149032258475786000183000000000003026015411236975101101">
    <tpAmb>1</tpAmb>
    <verAplic>7Carros v8.3</verAplic>
    <dhEvento>2026-05-07T12:00:00-03:00</dhEvento>
    <CNPJAutor>58475786000183</CNPJAutor>
    <chNFSe>43149032258475786000183000000000003026015411236975</chNFSe>
    <e101101>
      <xDesc>Cancelamento de NFS-e</xDesc>
      <cMotivo>9</cMotivo>
      <xMotivo>Erro nos dados do servico prestado, necessario reemissao com valores corretos.</xMotivo>
    </e101101>
  </infPedReg>
  <Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><!-- SHA256 --></Signature>
</pedRegEvento>
```

No fluxo Nacional atual, o cancelamento nao usa mais `pedidoCancelamentoXmlGZipB64` nem `/nfse/{chave}/cancelar`. O XML acima deve ser compactado em GZip/Base64 e enviado no campo `pedidoRegistroEventoXmlGZipB64` para `POST /nfse/{chave}/eventos`.

### 6.4 XML de Cancelamento - ABRASF

Gerado por `NFSeABRASFXML::gerarXMLCancelamento()`. `Id="cancel_{numeroNfse}"`, `CodigoCancelamento` padrao `2`.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<CancelarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">
  <Pedido>
    <InfPedidoCancelamento Id="cancel_1">
      <IdentificacaoNfse>
        <Numero>1</Numero>
        <CpfCnpj><Cnpj>54265333000171</Cnpj></CpfCnpj>
        <InscricaoMunicipal>0828516000129</InscricaoMunicipal>
        <CodigoMunicipio>5300108</CodigoMunicipio>
      </IdentificacaoNfse>
      <CodigoCancelamento>2</CodigoCancelamento>
    </InfPedidoCancelamento>
  </Pedido>
</CancelarNfseEnvio>
```

### 6.5 XML Betha - DPS (Declaracao de Prestacao de Servico)

#### 6.5.a Gerado pelo 7Carros (`NFSeBethaXML.php` + `xml_envio.xml`)

XML real salvo apos emissao TELES LOCADORA (Correia Pinto/SC, 2026-04-16). Conforme `NFSeBethaXML::gerarDPS()` + `assinar(..., 'id')`.

**Atencao — diferencas do Nacional no codigo:**

| Campo | Nacional (`NFSeXML`) | Betha (`NFSeBethaXML`) |
|-------|---------------------|------------------------|
| Atributo ID | `Id` (maiusculo) | `id` (minusculo) |
| `opSimpNac` | Mapa 1/2/3 (MEI, ME/EPP, etc.) | `1` se regime=1, senao `2` |
| `regApTribSN` | Enviado se ME/EPP | **Nao enviado** |
| `<IM>` | Condicional (`nfse_enviar_im`) | Sempre se `ins_muni` preenchida |
| `totTrib` | `<vTotTrib>` com valores | `<indTotTrib>0</indTotTrib>` |

```xml
<?xml version="1.0" encoding="UTF-8"?>
<DPS xmlns="http://www.betha.com.br/e-nota-dps" versao="1.00">
  <infDPS id="DPS420455823570662300016900001000000000000001">
    <tpAmb>1</tpAmb>
    <dhEmi>2026-04-16T09:17:19-03:00</dhEmi>
    <verAplic>7Carros v8.3</verAplic>
    <serie>1</serie>
    <nDPS>1</nDPS>
    <dCompet>2026-04-16</dCompet>
    <tpEmit>1</tpEmit>
    <cLocEmi>4204558</cLocEmi>
    <prest>
      <CNPJ>35706623000169</CNPJ>
      <IM>7138</IM>
      <fone>5549991185001</fone>
      <email>EDNOTELES@HOTMAIL.COM</email>
      <regTrib>
        <opSimpNac>1</opSimpNac>
        <regEspTrib>0</regEspTrib>
      </regTrib>
    </prest>
    <toma>
      <CPF>05538902986</CPF>
      <xNome>RENATO PINHEIRO</xNome>
      <end>
        <endNac>
          <cMun>4204558</cMun>
          <CEP>88570000</CEP>
        </endNac>
        <xLgr>Rua Teodoro Correa Melo </xLgr>
        <nro>229</nro>
        <xBairro>centro</xBairro>
      </end>
    </toma>
    <serv>
      <locPrest>
        <cLocPrestacao>4204558</cLocPrestacao>
      </locPrest>
      <cServ>
        <cTribNac>990101</cTribNac>
        <xDescServ>LOCACAO DE VEICULO AUTOMOTOR SEM CONDUTOR, CONFORME CONTRATO DE LOCACAO.</xDescServ>
        <cNBS>000000000</cNBS>
      </cServ>
    </serv>
    <valores>
      <vServPrest>
        <vServ>390.00</vServ>
      </vServPrest>
      <trib>
        <tribMun>
          <tribISSQN>4</tribISSQN>
          <tpRetISSQN>1</tpRetISSQN>
        </tribMun>
        <totTrib>
          <indTotTrib>0</indTotTrib>
        </totTrib>
      </trib>
    </valores>
  </infDPS>
  <Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
    <SignedInfo>
      <CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      <SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
      <Reference URI="#DPS420455823570662300016900001000000000000001">
        <Transforms>
          <Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
          <Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
        </Transforms>
        <DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <DigestValue>2bzRMZbyCnvgL3q4UrIpdcjXlA8selCposcS5p8CZbo=</DigestValue>
      </Reference>
    </SignedInfo>
    <SignatureValue>OKSNj3xluSM+Bt+pOlGoFZNkXY182reG92NcrKGgraUKMozv8iR50B6HoEuCUUMYDJgc12IiWHmFkuKWs4RkPaBCjRwcSt0hjq2nd0xHf/p+zjEi0MGQELx1Cw24X3BlQtjkO9fzkIdJjXsrUt2qUt9NvN4q60OuVrtxVi+NWjYHe9JEKkVgW7FPO+H0yTkVIpRZCjQ28UgXxQCzTyxaVk9o8M/jSUQ/CXC1MMc2TqsNlmZJJUoAe+sMgrbvhrPXnWr65HmC6rzjelQQxsXrTEQk5EBDVWcT7YV12+UXUg7g4+jCa4Tqb2krCevmw2Hkld7DymzH6p7GPZLlXyiqog==</SignatureValue>
    <KeyInfo>
      <X509Data>
        <X509Certificate>...(certificado TELES LOCADORA truncado)...</X509Certificate>
      </X509Data>
    </KeyInfo>
  </Signature>
</DPS>
```

**Envelope SOAP de envio** (DPS acima inserida no corpo):

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
  <soapenv:Header/>
  <soapenv:Body>
    <RecepcionarDpsEnvio xmlns="http://www.betha.com.br/e-nota-dps">
      <DPS versao="1.00">
        <!-- XML DPS assinado (conteudo §6.5) -->
      </DPS>
    </RecepcionarDpsEnvio>
  </soapenv:Body>
</soapenv:Envelope>
```

**Resposta de erro real** (`xml_retorno.xml`) — validacao XSD Betha exige `id` minusculo:

```xml
<RecepcionarDpsResposta>
  <listaMensagens>
    <mensagem>
      <codigo>E001</codigo>
      <mensagem>cvc-complex-type.3.2.2: O atributo 'Id' não pode aparecer no elemento 'infDPS'.</mensagem>
    </mensagem>
    <mensagem>
      <mensagem>cvc-complex-type.4: O atributo 'id' deve aparecer no elemento 'infDPS'.</mensagem>
    </mensagem>
  </listaMensagens>
</RecepcionarDpsResposta>
```

**NFS-e autorizada Betha** (consulta): ver `z-exemplos-nfse-de-clientes/LoteNfse_1379658193_1775139509640-2.xml` — TELES LOCADORA, NFS-e #2, valor R$ 220,00, tomador CNPJ `09143100000141`.

### 6.6 Diferencas entre Nacional, ABRASF e Betha DPS

| Aspecto | Nacional (SEFIN) | ABRASF (Municipal) | Betha DPS |
|---------|-----------------|-------------------|-----------|
| Formato | DPS | RPS | DPS |
| Namespace | `sped.fazenda.gov.br/nfse` | `abrasf.org.br/nfse.xsd` | `betha.com.br/e-nota-dps` |
| Versao | 1.00 | 2.04 | 1.00 |
| Protocolo API | REST (JSON) | SOAP (XML) | SOAP (XML) |
| Autenticacao | mTLS (certificado) | mTLS (certificado) | mTLS (certificado) |
| Assinatura | SHA256 | SHA1 | SHA256 |
| Atributo ID | `Id` (maiusculo) | `Id` (maiusculo) | `id` (minusculo) |
| Envio XML | gzip + Base64 | XML direto no SOAP | XML direto no SOAP |
| Campos servico | cTribNac, cNBS | ItemListaServico, CodigoCnae, CodigoTribMunicipio | cTribNac, cNBS (igual Nacional) |
| ID formato | 45 chars (`gerarIdDPS()`) | `rps_{numero}` | 45 chars (`gerarIdDPS()`) |
| Texto | MAIUSCULO obrigatorio | Preserva case | MAIUSCULO obrigatorio |
| Contador numero | `numero_atual` (atualiza ao autorizar) | `abrasf_numero_rps` (atualiza ao autorizar) | `numero_atual` (atualiza ao **enviar**, pois ID nao pode ser reutilizado) |
| Fluxo | Sincrono | Sincrono | **Assincrono** (protocolo → consulta status) |
| Endpoint | `sefin.nfse.gov.br` | Varia por municipio | `nota-eletronica.betha.cloud/dps/ws` |
| Resposta prefixo | sem prefixo | sem prefixo | `ns2:` (requer getElementsByTagNameNS) |

---

## 7. Endpoints Backend

### API REST interna (app.nfse.php)

Todas as acoes via POST com parametro `acao`.

| Acao | Parametros | Retorno | Descricao |
|------|-----------|---------|-----------|
| `listar` | id_empresa, status?, data_inicio?, data_fim?, tomador?, limite? | Array de NFS-e | Lista com filtros |
| `emitir` | id_financeiro, dados_extras? | {sucesso, numero, chave_acesso} | Emitir nova NFS-e |
| `consultar` | id | Dados completos + eventos | Detalhes da NFS-e |
| `cancelar` | id, motivo | {sucesso, mensagem} | Cancelar autorizada |
| `reenviar` | id | {sucesso, mensagem} | Reenviar rejeitada |
| `status` | id | {status_atual, dados_sefin} | Consultar status na SEFIN |
| `download` | id, tipo (envio/retorno) | XML file | Download XML |
| `downloadPdf` | id | PDF file | Download PDF DANFSE |
| `gerarPdf` | id | {sucesso, pdf_url} | Gerar PDF para autorizada |
| `estatisticas` | id_empresa, status?, data_inicio?, data_fim? | {autorizadas, rejeitadas, canceladas, totais} | Contadores para dashboard |

**Formato resposta padrao:**
```json
{
  "sucesso": true,
  "mensagem": "NFS-e emitida com sucesso",
  "dados": { ... }
}
```

**Formato erro:**
```json
{
  "sucesso": false,
  "mensagem": "Certificado digital expirado",
  "erro": {
    "codigo": "CERT_EXPIRADO",
    "instrucao": "Faca o upload de um certificado valido nas configuracoes.",
    "categoria": "certificado"
  }
}
```

---

## 8. Certificado Digital

### Fluxo de Upload

```
1. Usuario faz upload do arquivo .pfx/.p12
2. Sistema valida:
   - Extensao .pfx ou .p12
   - Senha correta (tenta abrir com openssl_pkcs12_read)
   - Certificado nao expirado
3. Extrai dados: CNPJ, razao social, validade
4. Salva arquivo com nome unico: {chave}_{idEmpresa}_{timestamp}.pfx
5. Define permissao 0600 no arquivo
6. Criptografa senha com AES-256-CBC + IV
7. Salva na tabela nfse_configuracoes:
   - certificado_arquivo = nome do arquivo
   - certificado_senha = senha criptografada
   - certificado_validade = data de expiração
```

### Funcoes PHP utilizadas

```php
// Ler certificado PFX
openssl_pkcs12_read(file_get_contents($pfx), $certs, $senha);
// $certs['cert']  = certificado publico
// $certs['pkey']  = chave privada

// Extrair dados do certificado
$certData = openssl_x509_parse($certs['cert']);
// $certData['subject']['CN']      = "RAZAO SOCIAL:CNPJ"
// $certData['validFrom_time_t']   = timestamp inicio
// $certData['validTo_time_t']     = timestamp expiracao

// Exportar chave privada como PEM
openssl_pkey_export($certs['pkey'], $pemPrivada);

// Exportar certificado como PEM
openssl_x509_export($certs['cert'], $pemPublica);

// Criptografar senha
$iv = openssl_random_pseudo_bytes(16);
$encrypted = openssl_encrypt($senha, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
$result = base64_encode($iv . $encrypted);
```

### Conexao mTLS com cURL

```php
// Extrair PEM do PFX para usar com cURL
$certPEM = '/tmp/cert_' . uniqid() . '.pem';
$keyPEM = '/tmp/key_' . uniqid() . '.pem';
file_put_contents($certPEM, $pemPublica);
file_put_contents($keyPEM, $pemPrivada);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_SSLCERT, $certPEM);        // Certificado publico
curl_setopt($ch, CURLOPT_SSLKEY, $keyPEM);           // Chave privada
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Limpar arquivos temporarios
unlink($certPEM);
unlink($keyPEM);
```

---

## 9. Assinatura Digital

### Processo de assinatura XMLDSIG

```php
// 1. Carregar XML
$doc = new DOMDocument('1.0', 'UTF-8');
$doc->loadXML($xml);

// 2. Encontrar elemento a assinar (ex: infDPS)
$node = $doc->getElementsByTagName($tagToSign)->item(0);
$id = $node->getAttribute('Id');

// 3. Canonicalizar o elemento
$canonical = $node->C14N(false, false);

// 4. Calcular digest
$digest = base64_encode(hash('sha256', $canonical, true)); // SHA256 para Nacional

// 5. Montar SignedInfo
$signedInfo = '<SignedInfo>...</SignedInfo>'; // com DigestValue

// 6. Canonicalizar SignedInfo
$signedInfoCanonical = $signedInfoNode->C14N(false, false);

// 7. Assinar com chave privada
openssl_sign($signedInfoCanonical, $signature, $privateKey, OPENSSL_ALGO_SHA256);
$signatureValue = base64_encode($signature);

// 8. Montar elemento Signature e inserir no XML
```

**Nacional:** usa `OPENSSL_ALGO_SHA256`
**ABRASF:** usa `OPENSSL_ALGO_SHA1`

---

## 10. Tratamento de Erros

### Fluxo de exibicao de erros

```
NFSeService::erro($mensagem, $codigo, $errosAPI)
    |
    +-- NFSeErros::formatarParaUsuario($codigo)
    |     Retorna: codigo, titulo, mensagem, instrucao, explicacao, categoria, icone
    |
    +-- Adiciona: message, success=false, erros_api (se houver)
    |
    +-- Retorna array completo para a view
```

**Na view (nfseEmitir.php, nfseCancelar.php):**
- Alert vermelho (`alert-danger`) com mensagem principal
- Codigo interno + codigo SEFIN original (se diferente)
- "Acao sugerida" com instrucao de correcao
- Lista de erros retornados pela API (se houver)
- Bloco "Explicacao" detalhada (quando disponivel no mapeamento)

**No endpoint JSON (app.nfse.php):**
```json
{
  "sucesso": false,
  "mensagem": "Certificado digital expirado",
  "erro": {
    "codigo": "CERT_EXPIRADO",
    "instrucao": "Faca o upload de um certificado valido nas configuracoes.",
    "categoria": "certificado"
  }
}
```

### Erros internos por categoria

**Certificado:**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| CERT_EXPIRADO | Seu certificado digital esta vencido | Renove o certificado digital A1 e atualize nas configuracoes |
| CERT_INVALIDO | Certificado digital invalido | Verifique se o arquivo .pfx esta correto e nao esta corrompido |
| CERT_SENHA | Senha do certificado incorreta | Verifique a senha do certificado nas configuracoes |
| CERT_NAO_ENCONTRADO | Certificado digital nao configurado | Acesse Configuracoes > NFS-e para enviar o certificado |
| CERT_LEITURA | Erro ao ler o certificado digital | O arquivo do certificado pode estar corrompido. Faca o upload novamente |

**Conexao:**
| Codigo | Mensagem | Recuperavel? |
|--------|----------|-------------|
| CONN_TIMEOUT | Servidor da SEFIN nao respondeu | Sim |
| CONN_SSL | Erro de conexao segura (SSL/TLS) | Nao |
| CONN_REFUSED | Conexao recusada pelo servidor | Sim |
| SERVICO_INDISPONIVEL | Servico da SEFIN temporariamente indisponivel | Sim |
| CONN_CURL | Erro de comunicacao com o servidor | Sim |

**Prestador:**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| CNPJ_INVALIDO | CNPJ do prestador invalido | Verifique os dados da empresa nas configuracoes |
| CNPJ_NAO_CADASTRADO | CNPJ nao cadastrado no Portal Nacional da NFS-e | Cadastre sua empresa no portal antes de emitir (possui explicacao detalhada) |
| SERIE_INVALIDA | Serie da DPS nao configurada ou invalida | Configure a serie nas configuracoes da empresa (possui explicacao detalhada) |
| IM_INVALIDA | Inscricao Municipal invalida ou nao encontrada | Verifique a IM nas configuracoes da empresa |
| IM_SUSPENSA | Inscricao Municipal suspensa | Regularize sua situacao junto a prefeitura |

**Tomador:**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| TOMADOR_CPF_INVALIDO | CPF do cliente invalido | Verifique o cadastro do cliente |
| TOMADOR_CNPJ_INVALIDO | CNPJ do cliente invalido | Verifique o cadastro do cliente |
| TOMADOR_NAO_INFORMADO | Dados do cliente (tomador) nao informados | Preencha os dados do cliente antes de emitir a nota |
| TOMADOR_ENDERECO | Endereco do cliente incompleto | Verifique CEP, cidade e estado no cadastro do cliente |
| E0240 | CEP do tomador nao pertence ao municipio informado | Verifique CEP, cidade e estado no cadastro do cliente |
| TOMADOR_EMAIL | Email do cliente invalido | Corrija o email no cadastro do cliente |

**Servico:**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| SERVICO_NAO_INFORMADO | Descricao do servico nao informada | Informe a descricao do servico prestado |
| SERVICO_CODIGO_INVALIDO | Codigo do servico (NBS) invalido | Verifique o codigo do servico nas configuracoes de NFS-e |
| VALOR_INVALIDO | Valor do servico invalido | O valor deve ser maior que zero |
| VALOR_ZERADO | Nao e possivel emitir nota fiscal com valor zerado | Informe um valor maior que zero |
| ALIQUOTA_INVALIDA | Aliquota de ISS invalida para este municipio | Verifique a aliquota de ISS nas configuracoes |

**XML:**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| XML_INVALIDO | Erro na geracao do documento fiscal | Contate o suporte tecnico |
| XML_ASSINATURA | Erro ao assinar o documento fiscal | Verifique o certificado digital e tente novamente |
| XML_SCHEMA | Documento fora do padrao da SEFIN | Contate o suporte tecnico |

**Duplicidade:**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| NOTA_DUPLICADA | Ja existe uma NFS-e emitida para este lancamento | Verifique as notas ja emitidas |
| RPS_DUPLICADO | Numero de RPS ja utilizado | Tente novamente, o sistema gerara um novo numero |

**Cancelamento:**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| CANCEL_PRAZO | Prazo para cancelamento expirado | Contate a prefeitura para cancelamento fora do prazo |
| CANCEL_JA_CANCELADA | Esta nota fiscal ja foi cancelada anteriormente | Verifique o status da nota no sistema |
| CANCEL_SUBSTITUIDA | Esta nota foi substituida e nao pode ser cancelada | Cancele a nota substituta em seu lugar |
| CANCEL_MOTIVO | Motivo do cancelamento nao informado | Informe o motivo do cancelamento |

**Consulta:**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| NOTA_NAO_ENCONTRADA | Nota fiscal nao encontrada na base da SEFIN | Verifique se a nota foi emitida corretamente |
| CHAVE_INVALIDA | Chave de acesso da NFS-e invalida | Verifique a chave de acesso e tente novamente |

**Generico / Configuracao:**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| ERRO_DESCONHECIDO | Ocorreu um erro inesperado | Tente novamente ou contate o suporte |
| MANUTENCAO | Sistema em manutencao | Tente novamente mais tarde |
| CONFIGURACAO_INCOMPLETA | Configuracoes de NFS-e incompletas | Complete as configuracoes de NFS-e da empresa |
| NFSE_DESATIVADA | Emissao de NFS-e desativada para esta empresa | Ative a emissao de NFS-e nas configuracoes |

### Erros NFS-e Nacional (API SEFIN)

Codigos retornados diretamente pela API SEFIN, mapeados na classe com mensagem e instrucao especificas:

| Codigo SEFIN | Mensagem | Instrucao |
|-------------|----------|-----------|
| E0039 | Municipio nao habilitado no Sistema Nacional NFS-e | Altere o Tipo de Emissao para "Municipal (ABRASF)" (possui explicacao detalhada) |
| E0116 | Inscricao Municipal obrigatoria na DPS | Confirmar `ins_muni` preenchida e ativar `enviar_im`/`nfse_enviar_im`; confirmar cadastro CNC com IM |
| E0120 | Inscricao Municipal nao deve ser informada | Manter `enviar_im = N`; IM pode permanecer no cadastro interno |
| E0160 | Opcao pelo Simples Nacional diverge do cadastro | Corrija mapeamento ME/EPP/MEI → `opSimpNac` (ver §6.1 e §13) |
| E0166 | Regime de apuracao tributaria pelo SN obrigatorio | Preencha `reg_apuracao_sn` (default 1) quando ME/EPP (`opSimpNac=3`) |
| E0240 | CEP do tomador nao pertence ao municipio informado | Verifique CEP, cidade e estado do cliente |
| E0611 | Nao e permitido informar aliquota de ISS | Configure a aliquota como 0,00 |
| E0615 | Obrigatorio informar aliquota de ISS | Informe a aliquota nas configuracoes (retencao ME/EPP) |
| E0625 | Aliquota de ISS deve ser 0,00 para ME/EPP sem retencao | Configure aliquota ISS como 0,00 |
| E0634 | Aliquota de ISS deve ser 0,00 quando nao ha retencao | Configure a aliquota de ISS como 0,00 |
| E0688 | Aliquota de ISS deve ser 0,00 para ISS nao retido | Configure a aliquota de ISS como 0,00 |
| E0690 | Retencao de PIS/COFINS requer situacao tributaria | Informe a situacao tributaria federal ou aliquota de COFINS |
| E0712 | Regime Especial de Tributacao incorreto | Verifique o regime tributario nas configuracoes |
| E0714 | DPS rejeitada - Erro na estrutura do documento | Verifique campos obrigatorios (possui explicacao detalhada) |
| E1010 | Sociedade de Profissionais obrigada ao Padrao Nacional | Obrigatorio a partir de 01/10/2025 |
| E1011 | Simples Nacional obrigado ao Padrao Nacional | Obrigatorio a partir de 01/11/2025 |

**Implementacao de referencia:** `app/Classes/NFSe/NFSeErros.php` — inclui mapeamento amigavel para E0120, E0166 e demais codigos SEFIN.

### Erros RNG (Validacao Schema XML)

| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| RNG6110 | Falha na validacao do XML contra o Schema (XSD) | Verifique dados da empresa e servico (possui explicacao detalhada) |
| RNG6111 | Elemento inesperado ou fora de ordem no XML | Contate o suporte tecnico |
| RNG6112 | Campo obrigatorio ausente no XML | Verifique dados obrigatorios nas configuracoes |
| RNG6113 | Formato de campo invalido no XML | Verifique formatos numericos e datas |
| RNG9997 | Dados incompativeis com o Padrao Nacional | Verifique natureza da operacao, serie, NBS e regime (possui explicacao detalhada) |

### Erros ABRASF (Sistema Municipal)

**Erros internos ABRASF:**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| ABRASF_SOAP_ERRO | Erro na comunicacao com o sistema municipal | Verifique conexao e tente novamente |
| ABRASF_XML_INVALIDO | XML do RPS rejeitado pelo sistema municipal | Verifique dados da empresa, servico e cliente |
| ABRASF_AUTENTICACAO | Falha na autenticacao com o sistema municipal | Verifique se o certificado esta cadastrado no ISSNet |
| ABRASF_IM_OBRIGATORIA | Inscricao Municipal obrigatoria para emissao ABRASF | Preencha a IM nas configuracoes da empresa |
| ABRASF_CAMPO_OBRIGATORIO | Campo obrigatorio nao preenchido para ABRASF | Verifique Item Lista Servico, CNAE e Cod. Tributacao Municipal |

**Erros ISSNet (codigos retornados pela prefeitura):**
| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| E090 | Numero do RPS invalido | Verifique "Numero RPS Atual" nas configuracoes e AIDF no ISSNet (possui explicacao detalhada) |
| E093 | Serie do RPS invalida | Verifique "Serie" e AIDF no portal ISSNet (possui explicacao detalhada) |
| E160 | XML em desacordo com o XML Schema do webservice | Verifique campos obrigatorios, formatos e codigos (possui explicacao detalhada) |
| E183 | Cabecalho XML fora do padrao do webservice | Contate o suporte tecnico (possui explicacao detalhada) |
| E232 | Erro no processamento do arquivo pelo webservice | Verifique AIDF e acesso ao webservice no ISSNet (possui explicacao detalhada) |

### Mapeamento de codigos SEFIN para codigos internos

O metodo `NFSeErros::mapearErroAPI()` converte codigos da API externa para codigos internos:

| Codigo API | Codigo Interno | | Codigo API | Codigo Interno |
|-----------|---------------|---|-----------|---------------|
| E01 | CNPJ_INVALIDO | | E30 | CERT_EXPIRADO |
| E02 | IM_INVALIDA | | E31 | CERT_INVALIDO |
| E03 | SERVICO_CODIGO_INVALIDO | | E40 | CANCEL_PRAZO |
| E04 | VALOR_INVALIDO | | E41 | CANCEL_JA_CANCELADA |
| E05 | TOMADOR_NAO_INFORMADO | | E42 | CANCEL_SUBSTITUIDA |
| E06 | TOMADOR_CPF_INVALIDO | | E50 | NOTA_NAO_ENCONTRADA |
| E07 | TOMADOR_CNPJ_INVALIDO | | E51 | CHAVE_INVALIDA |
| E08 | TOMADOR_ENDERECO | | E90 | SERVICO_INDISPONIVEL |
| E09 | SERVICO_NAO_INFORMADO | | E91 | MANUTENCAO |
| E10 | XML_SCHEMA | | E99 | ERRO_DESCONHECIDO |
| E11 | XML_ASSINATURA | | | |
| E20 | NOTA_DUPLICADA | | | |
| E21 | RPS_DUPLICADO | | | |

Codigos SEFIN Nacional (E0xxx, E1xxx, RNGxxxx) e ISSNet (E090, E093, E160, E183, E232) mapeiam para si mesmos (possuem entrada propria no array de erros).

**Fallback para codigos nao mapeados:**
```
Prefixo "RNG" → XML_SCHEMA
Prefixo "E0"  → XML_INVALIDO
Padrao "E\d+" → XML_INVALIDO
Default       → ERRO_DESCONHECIDO
```

### Erros recuperaveis (reenvio automatico)

Os seguintes erros permitem reenvio automatico pelo CRON (max 3 tentativas):

`CONN_TIMEOUT`, `CONN_REFUSED`, `SERVICO_INDISPONIVEL`, `CONN_CURL`, `MANUTENCAO`

Verificado por `NFSeErros::isRecuperavel($codigo)`.

### Municipio nao suportado

| Codigo | Mensagem | Instrucao |
|--------|----------|-----------|
| E0039 | Municipio nao habilitado no Sistema Nacional NFS-e | Altere para "Municipal (ABRASF)" nas configuracoes |
| MUNICIPIO_NAO_SUPORTADO | Municipio nao suportado pelo sistema de emissao configurado | Verifique o tipo de emissao nas configuracoes |

---

## 11. Processos Automaticos (CRON)

### Configuracao

```bash
# Executar a cada 5 minutos
*/5 * * * * php /caminho/app/Crons/nfse.php acessar=ok tipo=emitir
*/5 * * * * php /caminho/app/Crons/nfse.php acessar=ok tipo=reenviar
*/5 * * * * php /caminho/app/Crons/nfse.php acessar=ok tipo=consultar_betha
*/10 * * * * php /caminho/app/Crons/nfse.php acessar=ok tipo=enviar_email
```

### Tipos de processamento

**`tipo=emitir`** - Emissao automatica
- Busca pagamentos confirmados nos ultimos 7 dias
- Filtra: empresa com `emissao_auto = 'S'` e `ativo = 'S'`
- Ignora financeiros que ja possuem NFS-e
- Limite: 50 por execucao
- Log: `logs/nfse_emitir.log`

**`tipo=reenviar`** - Reenvio de rejeitadas
- Busca NFS-e com status 'rejeitada' e erro recuperavel
- Maximo 3 tentativas por NFS-e
- Limite: 20 por execucao
- Log: `logs/nfse_reenviar.log`

**`tipo=consultar_betha`** - Consulta status DPS Betha
- Busca NFS-e com status 'processando' e tipo_emissao 'betha'
- Requer campo `protocolo` preenchido
- Chama ConsultarStatusDps na API Betha
- Atualiza status para 'autorizada' ou 'rejeitada'
- Limite: 20 por execucao (ultimas 48 horas)
- Log: `logs/nfse_consultar_betha.log`
- **Nota:** Atualmente apenas Betha e assincrono. Se no futuro houver outro provedor assincrono, tornar generico buscando qualquer NFSe "processando" e roteando pelo tipo_emissao.

**`tipo=enviar_email`** - Envio de email
- Busca NFS-e autorizadas sem email enviado
- Filtra: empresa com `enviar_email = 'S'`
- Limite: 30 por execucao
- Log: `logs/nfse_enviar_email.log`

---

## 12. Validacoes e Regras

### Antes da emissao
- Empresa com NFS-e ativo (`ativo = 'S'`)
- Certificado digital presente e nao expirado
- Registro financeiro existe e nao possui NFS-e vinculada
- Dados do tomador preenchidos (CPF/CNPJ, nome)
- Valor do servico > 0

### Cancelamento
- Status deve ser 'autorizada'
- Motivo com minimo 15 caracteres
- Aviso (nao bloqueio) se prazo > 30 dias

### Reenvio
- Status deve ser 'rejeitada'
- Maximo 3 tentativas
- Gera nova DPS (nao reutiliza XML da tentativa anterior)

### Validacoes Nacional (SEFIN)

Aplicaveis quando `tipo_emissao = nacional`:

- **IM:** enviar `<IM>` no Nacional somente se `nfse_enviar_im = S`. `ins_muni` no cadastro e independente (contratos, ABRASF, PDF).
- **Diagnostico E0116:** se `ins_muni` esta preenchida, mas `nfse_enviar_im = N`, o XML sai sem `<IM>` e a SEFIN pode rejeitar com E0116. Ative `nfse_enviar_im = S` somente quando o CNC do CNPJ/municipio exige IM.
- **`opSimpNac`:** usar mapeamento cadastro → XML da §6.1 (nao tratar como binario Simples/Nao).
- **`regApTribSN`:** incluir no XML **apenas** quando `opSimpNac = 3` (ME/EPP). MEI e nao optantes nao enviam a tag.
- **Codigo IBGE:** `municipio_codigo` e `cLocEmi` com 7 digitos validos.
- **Aliquota ISS:** ME/EPP sem retencao ou nao incidencia (`trib_issqn = 4`) → aliquota 0,00 (evita E0611/E0625).
- **bind_param (legado):** ao adicionar campos no UPDATE de empresa, a string de tipos deve ter **exatamente** o mesmo numero de caracteres que variaveis (ex.: 48 vars = 48 tipos).

**Teste local recomendado:** gerar XML da DPS antes do envio e validar presenca/ausencia de `<IM>`, valor de `<opSimpNac>` e `<regApTribSN>` conforme cadastro.

### Calculo de impostos
```
Base Calculo = Valor Servicos - Valor Deducoes

Se trib_issqn = 1 (Tributavel):
  Valor ISS = Base Calculo x (Aliquota ISS / 100)
Senao:
  Valor ISS = 0

Valor IBS = Valor Servicos x (0.10 / 100)    // 2026
Valor CBS = Valor Servicos x (0.90 / 100)    // 2026
```

**Nota sobre IBS/CBS:** Aliquotas de 2026 (periodo de transicao). IBS e CBS incidem sobre o valor total dos servicos, nao sobre a base de calculo.

### Permissoes de usuario sugeridas
- `nfseVer` - Visualizar NFS-e e dashboard
- `nfseAdicionar` - Emitir novas NFS-e
- `nfseApagar` - Cancelar NFS-e
- `nfseConfigurar` - Acessar configuracoes

---

## 13. Armadilhas SEFIN Nacional (leitura obrigatoria)

Secao pratica para quem implementa emissao Nacional pela primeira vez. Regras abaixo foram validadas em producao apos rejeicoes E0116, E0120, E0160 e E0166.

### Par E0116 / E0120 — validacao contra CNC

A SEFIN compara a tag `<IM>` com o **CNC NFS-e** do CNPJ no municipio emissor (`cLocEmi`):

| CNC do CNPJ no municipio | Enviar `<IM>`? | Erro se violar |
|--------------------------|----------------|----------------|
| Com informacoes complementares (incl. IM) | Sim (`nfse_enviar_im = S`) | E0116 |
| Sem informacoes complementares | Nao (`nfse_enviar_im = N`, padrao) | E0120 |

**Nao e regra fixa por municipio** (ex.: “Recife sempre exige IM”). O cadastro CNC de cada CNPJ define qual erro ocorre.

### Erros comuns e como evitar

| Codigo | Causa | Acao |
|--------|-------|------|
| **E0116** | CNC tem complemento e IM ausente na DPS | Ativar `nfse_enviar_im = S`; manter `ins_muni` no cadastro |
| **E0120** | CNC sem complemento e IM enviada na DPS | Manter `nfse_enviar_im = N` (padrao); regularizar CNC antes de ativar |
| **E0160** | `opSimpNac` diverge do cadastro Simples | Usar mapeamento §6.1: ME/EPP→3, MEI→2, Presumido/Real→1 |
| **E0166** | Falta `regApTribSN` para ME/EPP | Preencher `reg_apuracao_sn` (default 1); enviar tag so se `opSimpNac=3` |

**Caso real validado em producao (28/05/2026):** CNPJ com `ins_muni` preenchida, mas `nfse_enviar_im = N`, gerou XML sem `<IM>` (`LOCATE('<IM>', xml_envio) = 0`) e recebeu E0116. A correcao foi mudar "Enviar IM na DPS Nacional" para **Sim** e reemitir.

### Armadilha: `opSimpNac` nao e binario

Documentacao antiga ou intuitiva costuma dizer `1=Simples, 2=Nao`. No layout **Nacional SEFIN** os valores sao:

- `1` = Nao optante pelo Simples Nacional
- `2` = MEI
- `3` = ME/EPP (Simples Nacional)

Empresa cadastrada como "Simples Nacional ME/EPP" (`regime_tributario = 1`) deve gerar `<opSimpNac>3</opSimpNac>`, **nao** `1`.

### Ordem tipica de rejeicao (cascata SEFIN)

A SEFIN valida em sequencia. Corrija na ordem abaixo ao depurar uma DPS rejeitada:

```
1. IM (<IM> presente/ausente conforme CNC)
2. Simples Nacional (<opSimpNac> correto)
3. Apuracao SN (<regApTribSN> se ME/EPP)
4. ISS / aliquotas / tomador / demais campos
```

Exemplo real (Recife, ME/EPP): E0116 (sem IM no XML) → E0160 (opSimpNac errado) → E0166 (sem regApTribSN) → E0120 (IM enviada com CNC sem complemento). Solucao: `nfse_enviar_im = N` ate cadastro CNC; depois ativar `S`.

### Checklist pre-emissao (Nacional)

Antes de enviar a DPS para a SEFIN:

- [ ] `nfse_enviar_im` definido conforme situacao CNC (padrao **N** se incerto)
- [ ] `ins_muni` preenchida no cadastro (mesmo com `nfse_enviar_im = N`; obrigatoria se `nfse_enviar_im = S`)
- [ ] Se erro anterior foi E0116, confirmar no XML rejeitado se `<IM>` estava ausente e ativar `nfse_enviar_im = S` quando a IM estiver correta
- [ ] Regime tributario correto: ME/EPP (1), MEI (4), Presumido (2) ou Real (3)
- [ ] Se ME/EPP: `reg_apuracao_sn` definido (consultar contador se incerto; default 1)
- [ ] Codigo IBGE do prestador com 7 digitos
- [ ] Aliquota ISS = 0,00 se nao incidencia ou ME/EPP sem retencao
- [ ] XML gerado localmente: `<IM>` conforme `nfse_enviar_im`, `<opSimpNac>`, `<regApTribSN>` conforme tabelas §6.1
- [ ] Tipo de emissao = `nacional` (nao misturar regras Betha/ABRASF)

### Invariantes para nova implementacao

A regra de negocio (cadastro, telas, permissoes) e do **novo sistema**, mas a integracao SEFIN Nacional deve respeitar:

1. **Classes XML separadas** por `tipo_emissao` — nunca misturar Betha/Nacional no mesmo gerador
2. **Campos fiscais explicitos** no XML — nao assumir defaults da SEFIN
3. **Tabela de erros viva** — cada rejeicao em producao vira entrada no guia e em `NFSeErros.php`
4. **Reenvio regenera DPS** — apos corrigir cadastro, reenvio usa dados atuais (§5.3)

---

## Adicionando Novo Modelo Estadual

Para adicionar suporte a um novo estado/municipio:

1. **Criar classe XML:** `NFSeXML[Estado].php` implementando a interface `NFSeXMLInterface`
2. **Criar classe API:** `NFSeAPI[Estado].php` implementando a interface `NFSeAPIInterface`
3. **Adicionar tipo:** Novo valor para `tipo_emissao` (ex: `'sp_ginfes'`, `'rj_nota_carioca'`)
4. **Adicionar campos config:** Se necessario, novos campos em `nfse_configuracoes` especificos do estado
5. **Rotear no Service:** Adicionar condicao no `NFSeService` para o novo tipo:
   ```php
   match ($config['tipo_emissao']) {
       'nacional'  => $this->emitirNacional(...),
       'abrasf'    => $this->emitirABRASF(...),
       'betha'     => $this->emitirBetha(...),    // Exemplo real (DPS assincrono)
       'sp_ginfes' => $this->emitirSPGinfes(...), // Exemplo futuro
       default     => throw new Exception('Tipo de emissao nao suportado')
   };
   ```
6. **Mapear erros:** Adicionar codigos de erro especificos no `NFSeErros`
7. **Se assincrono:** Adicionar cron de consulta de status (como `consultar_betha`)

---

## Referencias do Sistema Original

| Componente | Caminho |
|------------|---------|
| Service principal | `app/Classes/NFSe/NFSeService.php` |
| XML Nacional | `app/Classes/NFSe/NFSeXML.php` |
| XML ABRASF | `app/Classes/NFSe/NFSeABRASFXML.php` |
| XML Betha DPS | `app/Classes/NFSe/NFSeBethaXML.php` |
| API Nacional | `app/Classes/NFSe/NFSeAPI.php` |
| API ABRASF | `app/Classes/NFSe/NFSeABRASFAPI.php` |
| API Betha DPS | `app/Classes/NFSe/NFSeBethaAPI.php` |
| Certificado | `app/Classes/NFSe/NFSeCertificado.php` |
| Assinatura | `app/Classes/NFSe/NFSeAssinatura.php` |
| PDF | `app/Classes/NFSe/NFSePDF.php` |
| Erros | `app/Classes/NFSe/NFSeErros.php` |
| API endpoint | `api/app.nfse.php` |
| CRON | `app/Crons/nfse.php` |
| Views | `app/Views/nfse*.php` (5 telas) |
| SQL | `sql/001-013_*.sql` (13 migracoes) |
| Exemplos XML reais | `z-exemplos-nfse-de-clientes/` (Nacional, ABRASF), `xml_envio.xml` / `xml_retorno.xml` (Betha) |
