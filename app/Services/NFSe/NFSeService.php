<?php

namespace App\Services\NFSe;

use App\Config\NFSe as NFSeConfig;
use App\Models\NFSe as NFSeModel;
use App\Models\NFSeConfiguracao;
use App\Models\NFSeEvento;
use App\Models\Financeiro;
use App\Models\FinanceiroItem;
use App\Models\MatrizFilial;
use App\Models\Cliente;
use App\Models\Pais;
use App\Models\ContatoEmail;
use App\Models\LocacaoVeiculo;
use App\Models\ContratoVeiculo;
use App\Services\NFSe\Nacional\NFSeXMLNacional;
use App\Services\NFSe\Nacional\NFSeAPINacional;
use App\Services\NFSe\Nacional\NFSeEventosNacional;
use App\Services\NFSe\Betha\NFSeXMLBetha;
use App\Services\NFSe\Betha\NFSeAPIBetha;
use App\Services\NFSe\ISSNet\NFSeXMLISSNet;
use App\Services\NFSe\ISSNet\NFSeAPIISSNet;

/**
 * NFSeService - Orquestrador principal de NFS-e
 *
 * Coordena todo o ciclo de vida da NFS-e:
 * emissao, cancelamento, consulta, reenvio e envio por email.
 *
 * Roteia entre Nacional (SEFIN/REST) e Betha (SOAP assincrono)
 * baseado na configuracao tipo_emissao da empresa.
 */
class NFSeService
{
    private const FISCAL_TIMEZONE = 'America/Sao_Paulo';
    private const IBSCBS_OBRIGATORIO_GERAL = '2026-08-03';
    private const IBSCBS_OBRIGATORIO_SIMPLES = '2027-01-01';
    private const MOEDAS_BACEN = [
        'BRL' => '790',
        'USD' => '220',
        'EUR' => '978',
        'GBP' => '540',
    ];

    private NFSeModel $nfseModel;
    private NFSeConfiguracao $configModel;
    private NFSeEvento $eventoModel;
    private NFSeCertificado $certificado;
    private NFSeAssinatura $assinatura;
    private NFSePDF $pdf;

    public function __construct()
    {
        $this->nfseModel = new NFSeModel();
        $this->configModel = new NFSeConfiguracao();
        $this->eventoModel = new NFSeEvento();
        $this->certificado = new NFSeCertificado();
        $this->assinatura = new NFSeAssinatura();
        $this->pdf = new NFSePDF();
    }

    /**
     * Emite NFS-e a partir de um registro financeiro
     *
     * @param int $idFinanceiro ID do lancamento financeiro
     * @param string $chave Chave do tenant
     * @param array $dadosExtras Dados adicionais (deducoes, itens nao tributaveis)
     * @return array Resultado da emissao
     */
    public function emitir(int $idFinanceiro, string $chave, array $dadosExtras = []): array
    {
        // 1. Buscar financeiro
        $financeiroModel = new Financeiro();
        $financeiro = $financeiroModel->buscarPorId($idFinanceiro);
        if (!$financeiro) {
            return $this->erro('Lançamento financeiro não encontrado.', 'VALOR_INVALIDO');
        }

        // 2. Verificar duplicidade
        $existente = $this->nfseModel->buscarPorFinanceiro($idFinanceiro);
        if ($existente) {
            return $this->erro('Já existe uma NFS-e emitida para este lançamento.', 'NOTA_DUPLICADA', [], [
                'id' => (int) $existente['id'],
                'numero' => $existente['numero'] ?? null,
                'id_financeiro' => (int) ($existente['id_financeiro'] ?? $idFinanceiro),
            ]);
        }

        // 3. Buscar configuracao
        $idMatrizFilial = (int) $financeiro['id_matriz_filial'];
        $config = $this->configModel->buscarPorMatrizFilial($idMatrizFilial);
        if (!$config) {
            return $this->erro('Configurações de NFS-e não encontradas.', 'CONFIGURACAO_INCOMPLETA');
        }
        if (($config['ativo'] ?? 'N') !== 'S') {
            return $this->erro('Emissão de NFS-e desativada para esta empresa.', 'NFSE_DESATIVADA');
        }

        // 4. Validar certificado
        if (empty($config['certificado_arquivo']) || empty($config['certificado_senha'])) {
            return $this->erro('Certificado digital não configurado.', 'CERT_NAO_ENCONTRADO');
        }

        $analiseCertificado = $this->certificado->analisar(
            $chave,
            $config['certificado_arquivo'],
            $config['certificado_senha'],
            true
        );
        $this->normalizarCertificadoLegado($idMatrizFilial, $config, $analiseCertificado);

        if (($analiseCertificado['status'] ?? null) !== 'valido') {
            return $this->erroCertificado($analiseCertificado);
        }

        // 5. Montar dados
        try {
            $dados = $this->montarDadosNFSe($financeiro, $config, $chave, $dadosExtras);
        } catch (\InvalidArgumentException $e) {
            return $this->erro($e->getMessage(), $this->codigoValidacaoDPS($e->getMessage()));
        }

        // 6. Rotear por tipo de emissao
        $tipoEmissao = $config['tipo_emissao'] ?? 'nacional';

        return match ($tipoEmissao) {
            'nacional' => $this->emitirNacional($dados, $config, $chave),
            'betha' => $this->emitirBetha($dados, $config, $chave),
            'issnet' => $this->emitirISSNet($dados, $config, $chave),
            default => $this->erro('Tipo de emissão NFS-e não suportado: ' . $tipoEmissao, 'CONFIGURACAO_INCOMPLETA'),
        };
    }

    private function normalizarCertificadoLegado(int $idMatrizFilial, array &$config, array $analiseCertificado): void
    {
        if (($analiseCertificado['formato_senha'] ?? null) !== 'legado' || empty($analiseCertificado['senha'])) {
            return;
        }

        $senhaNormalizada = encrypt($analiseCertificado['senha']);
        $validade = $analiseCertificado['validade'] ?? null;

        $this->configModel->normalizarCertificado($idMatrizFilial, $senhaNormalizada, $validade);
        $config['certificado_senha'] = $senhaNormalizada;
        $config['certificado_validade'] = $validade;
    }

    private function erroCertificado(array $analiseCertificado): array
    {
        return match ($analiseCertificado['status'] ?? '') {
            'vencido' => $this->erro('Certificado digital vencido.', 'CERT_EXPIRADO'),
            'arquivo_ausente' => $this->erro('Arquivo do certificado digital não encontrado.', 'CERT_NAO_ENCONTRADO'),
            'senha_invalida' => $this->erro('Senha do certificado incorreta ou arquivo inválido.', 'CERT_SENHA'),
            'descriptografia_invalida' => $this->erro('Erro ao descriptografar a senha do certificado.', 'CERT_SENHA'),
            default => $this->erro('Erro ao ler o certificado digital.', 'CERT_LEITURA'),
        };
    }

    /**
     * Cancela NFS-e autorizada
     */
    public function cancelar(int $idNFSe, string $motivo, string $chave): array
    {
        $nfse = $this->nfseModel->buscarPorId($idNFSe);
        if (!$nfse) {
            return $this->erro('NFS-e não encontrada.', 'NOTA_NAO_ENCONTRADA');
        }
        if ($nfse['status'] !== 'autorizada') {
            return $this->erro('Somente NFS-e autorizadas podem ser canceladas.', 'CANCEL_JA_CANCELADA');
        }
        if (($nfse['tipo_emissao'] ?? '') === 'betha' && !array_key_exists('cancelamento_status', $nfse)) {
            return $this->erro('Atualização do cancelamento Betha pendente no servidor.', 'CONFIGURACAO_INCOMPLETA');
        }
        if (($nfse['tipo_emissao'] ?? '') === 'betha' && ($nfse['cancelamento_status'] ?? '') === 'processando') {
            return $this->erro('O cancelamento desta NFS-e já está sendo processado pela Betha.', 'CANCEL_PROCESSANDO');
        }
        if (strlen($motivo) < 15) {
            return $this->erro('Motivo do cancelamento deve ter no mínimo 15 caracteres.', 'CANCEL_MOTIVO');
        }

        $config = $this->configModel->buscarPorMatrizFilial((int) $nfse['id_matriz_filial']);
        if (!$config) {
            return $this->erro('Configurações de NFS-e não encontradas.', 'CONFIGURACAO_INCOMPLETA');
        }

        $tipoEmissao = $nfse['tipo_emissao'] ?? 'nacional';

        return match ($tipoEmissao) {
            'nacional' => $this->cancelarNacional($nfse, $motivo, $config, $chave),
            'betha' => $this->cancelarBetha($nfse, $motivo, $config, $chave),
            'issnet' => $this->cancelarISSNet($nfse, $motivo, $config, $chave),
            default => $this->erro('Tipo de emissão NFS-e não suportado: ' . $tipoEmissao, 'CONFIGURACAO_INCOMPLETA'),
        };
    }

    /**
     * Consulta status de NFS-e na SEFIN/prefeitura
     */
    public function consultar(int $idNFSe, string $chave): array
    {
        $nfse = $this->nfseModel->buscarPorId($idNFSe);
        if (!$nfse) {
            return $this->erro('NFS-e não encontrada.', 'NOTA_NAO_ENCONTRADA');
        }

        $config = $this->configModel->buscarPorMatrizFilial((int) $nfse['id_matriz_filial']);
        if (!$config) {
            return $this->erro('Configurações não encontradas.', 'CONFIGURACAO_INCOMPLETA');
        }

        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);

        try {
            $tipoEmissao = $nfse['tipo_emissao'] ?? 'nacional';
            if ($tipoEmissao === 'betha'
                && ($nfse['status'] ?? '') === 'autorizada'
                && !empty($nfse['chave_acesso'])) {
                return $this->consultarSituacaoFiscalBethaComPem($nfse, $config, $pem, true);
            }

            $api = $this->resolverAPI($tipoEmissao, $config);
            if ($tipoEmissao === 'nacional' && $api instanceof NFSeAPINacional) {
                return $this->consultarNacionalComPem($nfse, $config, $pem, empty($nfse['chave_acesso']));
            }

            if (empty($nfse['chave_acesso']) && empty($nfse['protocolo'])) {
                return $this->erro('NFS-e sem chave de acesso ou protocolo para consulta.', 'NOTA_NAO_ENCONTRADA');
            }

            if ($tipoEmissao === 'issnet' && $api instanceof NFSeAPIISSNet) {
                $xmlGenerator = new NFSeXMLISSNet();
                $xmlConsulta = $xmlGenerator->gerarXMLConsultaPorRps($nfse, $config);
                $resultado = $api->consultarPorRps($xmlConsulta, $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);
            } elseif ($tipoEmissao === 'betha' && $api instanceof NFSeAPIBetha) {
                $resultado = $api->consultarStatusDps(
                    (string) ($nfse['protocolo'] ?? ''),
                    (string) ($config['codigo_municipio'] ?? ''),
                    (string) ($nfse['prestador_cnpj'] ?? ''),
                    $pem['certPath'],
                    $pem['keyPath'],
                    (int) $config['ambiente']
                );
            } else {
                $resultado = $api->consultar((string) ($nfse['chave_acesso'] ?? ''), $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);
            }

            if ($tipoEmissao === 'issnet') {
                $retorno = (new NFSeXMLISSNet())->parseRetorno($resultado['resposta'] ?? '');
                if ($retorno['sucesso']) {
                    $this->nfseModel->atualizarAutorizada($idNFSe, [
                        'numero' => $retorno['numero'] ?: $nfse['numero'],
                        'codigo_verificacao' => $retorno['codigo_verificacao'],
                        'chave_acesso' => $retorno['chave_acesso'],
                        'xml_retorno' => $retorno['xml_retorno'] ?? $resultado['resposta'],
                    ]);
                }
            }

            $this->eventoModel->registrar($idNFSe, 'consulta', null, 'Consulta de status realizada', $resultado['resposta'] ?? null);

            return [
                'sucesso' => true,
                'mensagem' => 'Consulta realizada com sucesso.',
                'dados' => $resultado,
            ];
        } catch (\Throwable $e) {
            return $this->erro('Erro na consulta: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    /**
     * Reenvia NFS-e rejeitada
     */
    public function reenviar(int $idNFSe, string $chave, bool $permitirTentativaExtraManual = false): array
    {
        $nfse = $this->nfseModel->buscarPorId($idNFSe);
        if (!$nfse) {
            return $this->erro('NFS-e não encontrada.', 'NOTA_NAO_ENCONTRADA');
        }
        if ($nfse['status'] !== 'rejeitada') {
            return $this->erro('Somente NFS-e rejeitadas podem ser reenviadas.', 'ERRO_DESCONHECIDO');
        }
        if (($nfse['codigo_rejeicao'] ?? '') === 'DPS_CONFLITO') {
            return $this->erro(NFSeErros::getMensagem('DPS_CONFLITO'), 'DPS_CONFLITO');
        }
        $limiteRegularAtingido = (int) ($nfse['tentativas_envio'] ?? 0) >= NFSeConfig::MAX_ENVIOS;
        $tentativaExtraManual = $limiteRegularAtingido
            && $permitirTentativaExtraManual
            && $this->permiteTentativaExtraManual($nfse);
        if ($limiteRegularAtingido && !$tentativaExtraManual) {
            return $this->erro(
                'Limite regular de envios atingido (' . NFSeConfig::MAX_ENVIOS
                . '). Uma tentativa manual adicional só é permitida para erro técnico elegível e ainda não utilizado.',
                'ERRO_DESCONHECIDO'
            );
        }

        $config = $this->configModel->buscarPorMatrizFilial((int) $nfse['id_matriz_filial']);
        if (!$config) {
            return $this->erro('Configurações não encontradas.', 'CONFIGURACAO_INCOMPLETA');
        }

        if (($nfse['codigo_rejeicao'] ?? '') === 'DPS_JA_GERADA') {
            $this->nfseModel->incrementarTentativas($idNFSe);
            return $this->consultar($idNFSe, $chave);
        }

        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);

        try {
            $tipoEmissao = $nfse['tipo_emissao'] ?? $config['tipo_emissao'] ?? 'nacional';
            $xmlParser = $this->resolverXML($tipoEmissao);

            if (!empty($nfse['id_financeiro'])) {
                $financeiroModel = new Financeiro();
                $financeiro = $financeiroModel->buscarPorId((int) $nfse['id_financeiro']);
                if (!$financeiro) {
                    return $this->erro('Lançamento financeiro vinculado à NFS-e não encontrado.', 'VALOR_INVALIDO');
                }

                $dados = $this->montarDadosNFSe($financeiro, $config, $chave, []);
                $dados['tipo_emissao'] = $tipoEmissao;
                $preparado = $this->prepararXMLAssinado($tipoEmissao, $dados, $config, $chave, $pem);
                if (!$preparado['sucesso']) {
                    return $this->erro($preparado['mensagem'], $preparado['codigo']);
                }

                $xml = $preparado['xml'];
                $api = $preparado['api'];
                $numeroAnterior = (int) ($nfse['numero'] ?? 0);
                if ($tentativaExtraManual && !$this->reservarTentativaExtraManual($nfse)) {
                    return $this->erro('A tentativa manual extra desta NFS-e já foi utilizada.', 'ERRO_DESCONHECIDO');
                }
                $this->nfseModel->incrementarTentativas($idNFSe);
                $this->nfseModel->atualizarParaReenvio($idNFSe, [
                    'numero' => $preparado['numero'],
                    'serie' => $dados['serie'] ?? null,
                    'xml_envio' => $xml,
                ]);
                if ($numeroAnterior > 0 && $numeroAnterior !== (int) $preparado['numero']) {
                    $this->eventoModel->registrar(
                        $idNFSe,
                        'renumeracao',
                        null,
                        "Número alterado no reenvio: {$numeroAnterior} -> {$preparado['numero']}."
                    );
                }
            } else {
                $xml = $nfse['xml_envio'];
                if (empty($xml)) {
                    return $this->erro('XML de envio não encontrado.', 'XML_INVALIDO');
                }
                $api = $this->resolverAPI($tipoEmissao, $config);
                if ($tentativaExtraManual && !$this->reservarTentativaExtraManual($nfse)) {
                    return $this->erro('A tentativa manual extra desta NFS-e já foi utilizada.', 'ERRO_DESCONHECIDO');
                }
                $this->nfseModel->incrementarTentativas($idNFSe);
                $this->nfseModel->atualizarStatus($idNFSe, 'processando');
            }

            $resultado = $api->enviar($xml, $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);

            return $this->processarRetornoEmissao($idNFSe, $resultado, $xmlParser, $nfse['chave'], [
                'config' => $config,
                'pem' => $pem,
            ]);
        } catch (\InvalidArgumentException $e) {
            $codigo = $this->codigoValidacaoDPS($e->getMessage());
            $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $e->getMessage(), $codigo);
            $this->eventoModel->registrar($idNFSe, 'erro_configuracao', $codigo, $e->getMessage());
            return $this->erro($e->getMessage(), $codigo);
        } catch (\Throwable $e) {
            $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $e->getMessage(), 'CONN_CURL');
            $this->eventoModel->registrar($idNFSe, 'erro', 'CONN_CURL', $e->getMessage());
            return $this->erro('Erro no reenvio: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    private function permiteTentativaExtraManual(array $nfse): bool
    {
        if (empty($nfse['id_financeiro'])) {
            return false;
        }

        $motivoAtual = trim((string) ($nfse['codigo_rejeicao'] ?? '') . ' ' . (string) ($nfse['motivo_rejeicao'] ?? ''));
        $erroTecnicoEncontrado = $this->mensagemPermiteReenvioTecnico($motivoAtual);
        $extrasManuaisUtilizados = 0;

        foreach ($this->eventoModel->listarPorNfse((int) ($nfse['id'] ?? 0)) as $evento) {
            if (($evento['tipo_evento'] ?? '') === 'reenvio_manual'
                && ($evento['codigo_retorno'] ?? '') === 'LIMITE_TECNICO') {
                $extrasManuaisUtilizados++;
            }

            $mensagemEvento = trim((string) ($evento['codigo_retorno'] ?? '') . ' ' . (string) ($evento['mensagem'] ?? ''));
            if ($this->mensagemPermiteReenvioTecnico($mensagemEvento)) {
                $erroTecnicoEncontrado = true;
            }
        }

        return $erroTecnicoEncontrado
            && $extrasManuaisUtilizados < NFSeConfig::MAX_ENVIOS_EXTRAS_MANUAIS;
    }

    private function reservarTentativaExtraManual(array $nfse): bool
    {
        return $this->eventoModel->reservarTentativaExtraManual(
            (int) ($nfse['id'] ?? 0),
            (string) ($nfse['chave'] ?? ''),
            'Tentativa manual extra liberada após correção técnica do XML/data fiscal.'
        );
    }

    private function mensagemPermiteReenvioTecnico(string $mensagem): bool
    {
        $mensagem = mb_strtolower($mensagem, 'UTF-8');

        return str_contains($mensagem, 'xml_invalido')
            || str_contains($mensagem, 'e1235')
            || str_contains($mensagem, 'e001')
            || str_contains($mensagem, 'falha no esquema xml')
            || str_contains($mensagem, 'conteúdo inválido')
            || str_contains($mensagem, 'conteudo invalido')
            || str_contains($mensagem, 'data de emissão inválida')
            || str_contains($mensagem, 'data de emissao invalida')
            || str_contains($mensagem, "conteúdo do elemento 'trib' não está completo")
            || str_contains($mensagem, "conteudo do elemento 'trib' nao esta completo")
            || str_contains($mensagem, 'clocalidadeincid')
            || str_contains($mensagem, 'list of possible elements expected');
    }

    /**
     * Envia NFS-e por email ao tomador
     */
    public function enviarPorEmail(int $idNFSe, string $chave): array
    {
        $nfse = $this->nfseModel->buscarPorId($idNFSe);
        if (!$nfse) {
            return $this->erro('NFS-e não encontrada.', 'NOTA_NAO_ENCONTRADA');
        }
        if ($nfse['status'] !== 'autorizada') {
            return $this->erro('Somente NFS-e autorizadas podem ser enviadas por email.', 'ERRO_DESCONHECIDO');
        }

        $email = $nfse['tomador_email'] ?? '';
        $clienteId = 0;
        if (!empty($nfse['id_financeiro'])) {
            $financeiro = (new Financeiro())->buscarPorId((int) $nfse['id_financeiro']);
            $clienteId = (int) ($financeiro['id_cliente'] ?? 0);
        }

        $emailsAutorizados = [];
        if ($clienteId > 0) {
            $emailsAutorizados = (new ContatoEmail())->listarParaEnvio('cliente', $clienteId, $chave);
            if ($emailsAutorizados === []) {
                return $this->erro('Cliente sem email autorizado para envio.', 'TOMADOR_EMAIL');
            }
            $email = (string) $emailsAutorizados[0]['email'];
        } elseif (empty($email)) {
            return $this->erro('Email do tomador não informado.', 'TOMADOR_EMAIL');
        }

        $pdfResult = $this->pdf->gerarTemporario($nfse);
        if (!$pdfResult['sucesso']) {
            return $this->erro('Erro ao gerar PDF: ' . $pdfResult['mensagem'], 'ERRO_DESCONHECIDO');
        }

        // Enviar email via fila
        try {
            $payload = [
                'to' => $email,
                'to_name' => $nfse['tomador_nome'] ?? '',
                'subject' => 'NFS-e Nº ' . ($nfse['numero'] ?? '') . ' - ' . ($nfse['prestador_razao_social'] ?? ''),
                'body' => $this->gerarCorpoEmail($nfse),
                'attachments' => [[
                    'path' => $pdfResult['caminho_completo'],
                    'name' => $pdfResult['nome_arquivo'],
                    'delete_after_send' => true,
                ]],
                'id_matriz_filial' => $nfse['id_matriz_filial'],
            ];

            if ($clienteId > 0) {
                queue_client_email($clienteId, $payload, $chave);
            } else {
                queue_message('email', $payload, $chave);
            }
        } catch (\Throwable $e) {
            if (!empty($pdfResult['caminho_completo']) && file_exists($pdfResult['caminho_completo'])) {
                @unlink($pdfResult['caminho_completo']);
            }
            throw $e;
        }

        // Marcar como enviado
        $emailsEnviados = $emailsAutorizados !== []
            ? implode(', ', array_column($emailsAutorizados, 'email'))
            : $email;
        $this->nfseModel->marcarEmailEnviado($idNFSe, $email);
        $this->eventoModel->registrar($idNFSe, 'email', null, "Email enfileirado para {$emailsEnviados}");

        return [
            'sucesso' => true,
            'mensagem' => 'Email enfileirado com sucesso para ' . $emailsEnviados,
        ];
    }

    // ==========================================
    // METODOS PRIVADOS - Emissao
    // ==========================================

    private function emitirNacional(array $dados, array $config, string $chave): array
    {
        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);
        $idNFSe = null;

        try {
            $preparado = $this->prepararXMLAssinado('nacional', $dados, $config, $chave, $pem);
            if (!$preparado['sucesso']) {
                return $this->erro($preparado['mensagem'], $preparado['codigo']);
            }

            $idNFSe = $this->criarRegistroNFSe($dados, $config, $chave);
            $this->nfseModel->marcarProntaParaEnvio($idNFSe, $preparado['xml']);

            $resultado = $preparado['api']->enviar($preparado['xml'], $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);

            return $this->processarRetornoEmissao($idNFSe, $resultado, $preparado['xml_parser'], $chave, [
                'config' => $config,
                'pem' => $pem,
            ]);
        } catch (\InvalidArgumentException $e) {
            $codigo = $this->codigoValidacaoDPS($e->getMessage());
            if ($idNFSe !== null) {
                $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $e->getMessage(), $codigo);
                $this->eventoModel->registrar($idNFSe, 'erro_configuracao', $codigo, $e->getMessage());
            }
            return $this->erro($e->getMessage(), $codigo);
        } catch (\Throwable $e) {
            if ($idNFSe !== null) {
                $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $e->getMessage(), 'CONN_CURL');
                $this->eventoModel->registrar($idNFSe, 'erro', 'CONN_CURL', $e->getMessage());
            }
            return $this->erro('Erro na comunicação: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    private function emitirBetha(array $dados, array $config, string $chave): array
    {
        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);
        $idNFSe = null;

        try {
            $preparado = $this->prepararXMLAssinado('betha', $dados, $config, $chave, $pem);
            if (!$preparado['sucesso']) {
                return $this->erro($preparado['mensagem'], $preparado['codigo']);
            }

            $idNFSe = $this->criarRegistroNFSe($dados, $config, $chave);
            $this->nfseModel->marcarProntaParaEnvio($idNFSe, $preparado['xml']);

            $resultado = $preparado['api']->enviar($preparado['xml'], $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);

            return $this->processarRetornoEmissao($idNFSe, $resultado, $preparado['xml_parser'], $chave);
        } catch (\Throwable $e) {
            if ($idNFSe !== null) {
                $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $e->getMessage(), 'CONN_CURL');
                $this->eventoModel->registrar($idNFSe, 'erro', 'CONN_CURL', $e->getMessage());
            }
            return $this->erro('Erro na comunicação Betha: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    private function emitirISSNet(array $dados, array $config, string $chave): array
    {
        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);
        $idNFSe = null;

        try {
            $preparado = $this->prepararXMLAssinado('issnet', $dados, $config, $chave, $pem);
            if (!$preparado['sucesso']) {
                return $this->erro($preparado['mensagem'], $preparado['codigo']);
            }

            $idNFSe = $this->criarRegistroNFSe($dados, $config, $chave);
            $this->nfseModel->marcarProntaParaEnvio($idNFSe, $preparado['xml']);

            $resultado = $preparado['api']->enviar($preparado['xml'], $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);

            return $this->processarRetornoEmissao($idNFSe, $resultado, $preparado['xml_parser'], $chave);
        } catch (\Throwable $e) {
            if ($idNFSe !== null) {
                $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $e->getMessage(), 'CONN_CURL');
                $this->eventoModel->registrar($idNFSe, 'erro', 'CONN_CURL', $e->getMessage());
            }
            return $this->erro('Erro na comunicação ISSNet: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    // ==========================================
    // METODOS PRIVADOS - Cancelamento
    // ==========================================

    private function cancelarNacional(array $nfse, string $motivo, array $config, string $chave): array
    {
        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);
        $idNFSe = (int) $nfse['id'];

        try {
            $prestadorCnpj = preg_replace('/\D/', '', (string) ($nfse['prestador_cnpj'] ?? '')) ?? '';
            if (strlen($prestadorCnpj) !== 14) {
                return $this->erro('CNPJ do prestador inválido para cancelamento da NFS-e.', 'NFSE_CNPJ_PRESTADOR');
            }

            $xmlGenerator = new NFSeXMLNacional();
            $xml = $xmlGenerator->gerarXMLCancelamento($nfse['chave_acesso'], $motivo, [
                'ambiente' => $config['ambiente'] ?? 2,
                'prestador_cnpj' => $prestadorCnpj,
            ]);

            $assinado = $this->assinatura->assinar($xml, $pem['certPath'], $pem['keyPath'], 'infPedReg', 'sha256');
            if (!$assinado['sucesso']) {
                return $this->erro($assinado['mensagem'], 'XML_ASSINATURA');
            }

            $api = new NFSeAPINacional();
            $resultado = $api->cancelar($assinado['xml'], $nfse['chave_acesso'], $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);

            $retorno = $xmlGenerator->parseRetornoCancelamento($resultado['resposta'] ?? '');

            if (empty($retorno['erros']) && ($retorno['sucesso'] || ($resultado['sucesso'] ?? false))) {
                $this->nfseModel->atualizarCancelada($idNFSe, $motivo);
                $this->eventoModel->registrar($idNFSe, 'cancelamento', null, $motivo, $resultado['resposta'] ?? null);
                return ['sucesso' => true, 'mensagem' => 'NFS-e cancelada com sucesso.'];
            }

            $erroMsg = $retorno['erros'][0]['mensagem'] ?? ($resultado['erro'] ?? 'Erro desconhecido ao cancelar.');
            $erroCod = $retorno['erros'][0]['codigo'] ?? ($resultado['codigoErro'] ?? 'ERRO_DESCONHECIDO');
            $this->eventoModel->registrar($idNFSe, 'erro', $erroCod, $erroMsg, $resultado['resposta'] ?? null);
            return $this->erro($erroMsg, NFSeErros::mapearErroAPI($erroCod));
        } catch (\Throwable $e) {
            return $this->erro('Erro no cancelamento: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    private function cancelarBetha(array $nfse, string $motivo, array $config, string $chave): array
    {
        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);
        $idNFSe = (int) $nfse['id'];

        try {
            $prestadorCnpj = preg_replace('/\D/', '', (string) ($nfse['prestador_cnpj'] ?? '')) ?? '';
            $chaveAcesso = preg_replace('/\D/', '', (string) ($nfse['chave_acesso'] ?? '')) ?? '';
            if (strlen($prestadorCnpj) !== 14) {
                return $this->erro('CNPJ do prestador inválido para cancelamento da NFS-e.', 'NFSE_CNPJ_PRESTADOR');
            }
            if (strlen($chaveAcesso) !== 50) {
                return $this->erro('Chave de acesso inválida para cancelamento da NFS-e.', 'NOTA_NAO_ENCONTRADA');
            }

            $xmlGenerator = new NFSeXMLBetha();
            $xml = $xmlGenerator->gerarXMLCancelamento($chaveAcesso, $motivo, [
                'ambiente' => $config['ambiente'] ?? 2,
                'prestador_cnpj' => $prestadorCnpj,
            ]);

            $assinado = $this->assinatura->assinar(
                $xml,
                $pem['certPath'],
                $pem['keyPath'],
                'infEvento',
                'sha256',
                'id'
            );
            if (!$assinado['sucesso']) {
                return $this->erro($assinado['mensagem'], 'XML_ASSINATURA');
            }

            $api = new NFSeAPIBetha();
            $resultado = $api->cancelar($assinado['xml'], $chaveAcesso, $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);
            $retorno = $xmlGenerator->parseRetornoCancelamento($resultado['resposta'] ?? '');

            if ($retorno['sucesso']) {
                $this->nfseModel->atualizarCancelada($idNFSe, $motivo);
                $this->eventoModel->registrar($idNFSe, 'cancelamento', null, $motivo, $resultado['resposta'] ?? null);
                return ['sucesso' => true, 'mensagem' => 'NFS-e cancelada com sucesso.'];
            }

            if ($retorno['processando'] && !empty($retorno['protocolo'])) {
                $this->nfseModel->marcarCancelamentoProcessando($idNFSe, $motivo, (string) $retorno['protocolo']);
                $this->eventoModel->registrar(
                    $idNFSe,
                    'cancelamento_processando',
                    null,
                    'Pedido de cancelamento recebido pela Betha e aguardando validação.',
                    $resultado['resposta'] ?? null
                );
                return [
                    'sucesso' => true,
                    'mensagem' => 'Pedido de cancelamento recebido pela Betha. A confirmação será atualizada automaticamente.',
                    'processando' => true,
                ];
            }

            $erroMsg = $retorno['erros'][0]['mensagem']
                ?? ($retorno['mensagem'] ?: null)
                ?? ($resultado['erro'] ?? 'Erro desconhecido ao cancelar na Betha.');
            $erroCod = $retorno['erros'][0]['codigo'] ?? ($resultado['codigoErro'] ?? 'ERRO_DESCONHECIDO');
            if ($this->mensagemIndicaSituacaoFiscalAlterada($erroMsg)) {
                $sincronizacao = $this->consultarSituacaoFiscalBethaComPem($nfse, $config, $pem, false);
                if (($sincronizacao['sucesso'] ?? false) && in_array($sincronizacao['situacao'] ?? '', ['C', 'S'], true)) {
                    return $sincronizacao;
                }
            }
            $this->nfseModel->marcarErroCancelamento($idNFSe);
            $this->eventoModel->registrar($idNFSe, 'erro', $erroCod, $erroMsg, $resultado['resposta'] ?? null);
            return $this->erro($erroMsg, NFSeErros::mapearErroAPI($erroCod));
        } catch (\Throwable $e) {
            return $this->erro('Erro no cancelamento Betha: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    private function cancelarISSNet(array $nfse, string $motivo, array $config, string $chave): array
    {
        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);
        $idNFSe = (int) $nfse['id'];

        try {
            $xmlGenerator = new NFSeXMLISSNet();
            $xml = $xmlGenerator->gerarXMLCancelamento((string) ($nfse['chave_acesso'] ?? ''), $motivo, [
                'numero' => $nfse['numero'] ?? null,
                'prestador_cnpj' => $nfse['prestador_cnpj'] ?? null,
                'prestador_inscricao_municipal' => $nfse['prestador_inscricao_municipal'] ?? null,
                'codigo_municipio' => $config['codigo_municipio'] ?? null,
            ]);

            $assinado = $this->assinatura->assinar($xml, $pem['certPath'], $pem['keyPath'], 'InfPedidoCancelamento', 'sha1', 'Id');
            if (!$assinado['sucesso']) {
                return $this->erro($assinado['mensagem'], 'XML_ASSINATURA');
            }

            $api = new NFSeAPIISSNet($config);
            $resultado = $api->cancelar($assinado['xml'], (string) ($nfse['chave_acesso'] ?? ''), $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);
            $retorno = $xmlGenerator->parseRetornoCancelamento($resultado['resposta'] ?? '');

            if ($retorno['sucesso'] || (($resultado['sucesso'] ?? false) && empty($retorno['erros']))) {
                $this->nfseModel->atualizarCancelada($idNFSe, $motivo);
                $this->eventoModel->registrar($idNFSe, 'cancelamento', null, $motivo, $resultado['resposta'] ?? null);
                return ['sucesso' => true, 'mensagem' => 'NFS-e cancelada com sucesso.'];
            }

            $erroMsg = $retorno['erros'][0]['mensagem'] ?? ($resultado['erro'] ?? 'Erro desconhecido ao cancelar no ISSNet.');
            $erroCod = $retorno['erros'][0]['codigo'] ?? ($resultado['codigoErro'] ?? 'ERRO_DESCONHECIDO');
            $this->eventoModel->registrar($idNFSe, 'erro', $erroCod, $erroMsg, $resultado['resposta'] ?? null);
            return $this->erro($erroMsg, NFSeErros::mapearErroAPI($erroCod));
        } catch (\Throwable $e) {
            return $this->erro('Erro no cancelamento ISSNet: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    // ==========================================
    // METODOS PRIVADOS - Auxiliares
    // ==========================================

    /**
     * Processa retorno da API apos emissao
     */
    private function processarRetornoEmissao(
        int $idNFSe,
        array $resultado,
        NFSeXMLInterface $xmlParser,
        string $chave,
        array $contexto = []
    ): array
    {
        if (!$resultado['sucesso'] && !empty($resultado['codigoErro'])) {
            $codigo = $resultado['codigoErro'];
            $codigoInterno = NFSeErros::mapearErroAPI($codigo);
            $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', NFSeErros::getMensagem($codigoInterno), $codigoInterno);
            $this->eventoModel->registrar($idNFSe, 'erro', $codigo, NFSeErros::getMensagem($codigoInterno));
            return $this->erro(NFSeErros::getMensagem($codigoInterno), $codigoInterno);
        }

        $retorno = $xmlParser->parseRetorno($resultado['resposta'] ?? '');

        if (!empty($retorno['processando']) && !empty($retorno['protocolo'])) {
            $this->nfseModel->atualizarProcessando($idNFSe, [
                'protocolo' => $retorno['protocolo'],
                'xml_retorno' => $retorno['xml_retorno'] ?? $resultado['resposta'],
            ]);
            $this->eventoModel->registrar($idNFSe, 'emissao', null, 'DPS Betha recepcionada. Aguardando processamento.', $resultado['resposta'] ?? null);

            return [
                'sucesso' => true,
                'mensagem' => 'DPS Betha recepcionada. Aguardando processamento.',
                'dados' => [
                    'id' => $idNFSe,
                    'protocolo' => $retorno['protocolo'],
                    'status' => 'processando',
                ],
            ];
        }

        if ($retorno['sucesso']) {
            $chaveAutorizada = trim((string) ($retorno['chave_acesso'] ?? ''));
            if ($chaveAutorizada !== '' && $this->nfseModel->buscarPorChaveAcesso($chaveAutorizada, $idNFSe)) {
                return $this->registrarConflitoDps($idNFSe, ['chave_acesso']);
            }

            $this->nfseModel->atualizarAutorizada($idNFSe, [
                'numero' => $retorno['numero'],
                'codigo_verificacao' => $retorno['codigo_verificacao'],
                'chave_acesso' => $retorno['chave_acesso'],
                'xml_retorno' => $retorno['xml_retorno'] ?? $resultado['resposta'],
                'aliquota_ibs' => $retorno['aliquota_ibs'] ?? 0,
                'valor_ibs' => $retorno['valor_ibs'] ?? 0,
                'aliquota_cbs' => $retorno['aliquota_cbs'] ?? 0,
                'valor_cbs' => $retorno['valor_cbs'] ?? 0,
            ]);

            $this->eventoModel->registrar($idNFSe, 'emissao', null, 'NFS-e autorizada com sucesso', $resultado['resposta'] ?? null);

            return [
                'sucesso' => true,
                'mensagem' => 'NFS-e emitida com sucesso.',
                'dados' => [
                    'id' => $idNFSe,
                    'numero' => $retorno['numero'],
                    'chave_acesso' => $retorno['chave_acesso'],
                    'codigo_verificacao' => $retorno['codigo_verificacao'],
                ],
            ];
        }

        // Rejeitada
        $erros = $retorno['erros'] ?? [];
        $primeiroErro = $erros[0] ?? ['codigo' => 'ERRO_DESCONHECIDO', 'mensagem' => 'Erro desconhecido'];
        $codigoInterno = NFSeErros::mapearErroRetorno($primeiroErro['codigo'], $primeiroErro['mensagem'] ?? '');
        $mensagem = NFSeErros::getMensagem($codigoInterno);

        $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $primeiroErro['mensagem'] ?: $mensagem, $codigoInterno);
        $this->eventoModel->registrar($idNFSe, 'erro', $primeiroErro['codigo'], $primeiroErro['mensagem'], $resultado['resposta'] ?? null);

        if ($codigoInterno === 'DPS_JA_GERADA' && $xmlParser instanceof NFSeXMLNacional) {
            $nfse = $this->nfseModel->buscarPorId($idNFSe);
            if ($nfse && !empty($contexto['config']) && !empty($contexto['pem'])) {
                return $this->consultarNacionalComPem($nfse, $contexto['config'], $contexto['pem'], true);
            }
        }

        return $this->erro($mensagem, $codigoInterno, $erros);
    }

    /**
     * Consulta uma NFS-e Nacional e, quando necessario, recupera primeiro a
     * chave de acesso pelo identificador imutavel da DPS.
     */
    private function consultarNacionalComPem(array $nfse, array $config, array $pem, bool $reconciliacao): array
    {
        $api = new NFSeAPINacional();
        $xmlParser = new NFSeXMLNacional();
        $chaveAcesso = trim((string) ($nfse['chave_acesso'] ?? ''));
        $respostaDps = null;

        if ($chaveAcesso === '') {
            $idDPS = $this->extrairIdDPS($nfse, $config);
            if ($idDPS === null) {
                return $this->erro('Não foi possível identificar a DPS para consultar a NFS-e Nacional.', 'NOTA_NAO_ENCONTRADA');
            }

            $resultadoDps = $api->consultarPorDps(
                $idDPS,
                $pem['certPath'],
                $pem['keyPath'],
                (int) ($config['ambiente'] ?? 2)
            );
            $respostaDps = (string) ($resultadoDps['resposta'] ?? '');
            if (!($resultadoDps['sucesso'] ?? false)) {
                $codigo = (string) ($resultadoDps['codigoErro'] ?? 'DPS_JA_GERADA');
                return $this->erro(
                    $resultadoDps['erro'] ?? 'A DPS existe, mas a chave da NFS-e ainda não pôde ser recuperada.',
                    NFSeErros::mapearErroAPI($codigo)
                );
            }

            $chaveAcesso = $this->extrairChaveAcessoResposta($respostaDps) ?? '';
            if ($chaveAcesso === '') {
                return $this->erro('A consulta da DPS não retornou a chave de acesso da NFS-e.', 'DPS_JA_GERADA');
            }
        }

        $resultado = $api->consultar(
            $chaveAcesso,
            $pem['certPath'],
            $pem['keyPath'],
            (int) ($config['ambiente'] ?? 2)
        );
        $retorno = $xmlParser->parseRetorno((string) ($resultado['resposta'] ?? ''));
        if (!($resultado['sucesso'] ?? false) || !($retorno['sucesso'] ?? false)) {
            $erro = $retorno['erros'][0] ?? [];
            $codigoExterno = (string) ($erro['codigo'] ?? $resultado['codigoErro'] ?? 'ERRO_DESCONHECIDO');
            $codigoInterno = NFSeErros::mapearErroRetorno($codigoExterno, (string) ($erro['mensagem'] ?? ''));
            return $this->erro(
                (string) ($erro['mensagem'] ?? $resultado['erro'] ?? 'Não foi possível consultar a NFS-e Nacional.'),
                $codigoInterno,
                $retorno['erros'] ?? []
            );
        }

        $idNFSe = (int) $nfse['id'];
        $xmlRetorno = (string) ($retorno['xml_retorno'] ?? $resultado['resposta'] ?? '');
        if ($reconciliacao) {
            $comparacao = $this->compararDpsReconciliacao((string) ($nfse['xml_envio'] ?? ''), $xmlRetorno);
            if (!$comparacao['compativel']) {
                return $this->registrarConflitoDps($idNFSe, $comparacao['divergencias']);
            }
        }

        $chaveAutorizada = trim((string) ($retorno['chave_acesso'] ?: $chaveAcesso));
        if ($chaveAutorizada !== '' && $this->nfseModel->buscarPorChaveAcesso($chaveAutorizada, $idNFSe)) {
            return $this->registrarConflitoDps($idNFSe, ['chave_acesso']);
        }

        $this->nfseModel->atualizarAutorizada($idNFSe, [
            'numero' => $retorno['numero'] ?: ($nfse['numero'] ?? null),
            'codigo_verificacao' => $retorno['codigo_verificacao'] ?? null,
            'chave_acesso' => $chaveAutorizada,
            'xml_retorno' => $xmlRetorno,
            'aliquota_ibs' => $retorno['aliquota_ibs'] ?? 0,
            'valor_ibs' => $retorno['valor_ibs'] ?? 0,
            'aliquota_cbs' => $retorno['aliquota_cbs'] ?? 0,
            'valor_cbs' => $retorno['valor_cbs'] ?? 0,
        ]);

        $tipoEvento = $reconciliacao ? 'reconciliacao' : 'consulta';
        $mensagem = $reconciliacao
            ? 'NFS-e Nacional reconciliada pela consulta da DPS após retorno E0014.'
            : 'NFS-e Nacional autorizada confirmada por consulta.';
        $this->eventoModel->registrar($idNFSe, $tipoEvento, null, $mensagem, $resultado['resposta'] ?? $respostaDps);

        return [
            'sucesso' => true,
            'mensagem' => $mensagem,
            'dados' => [
                'id' => $idNFSe,
                'numero' => $retorno['numero'] ?: ($nfse['numero'] ?? null),
                'chave_acesso' => $chaveAutorizada,
                'codigo_verificacao' => $retorno['codigo_verificacao'] ?? null,
                'status' => 'autorizada',
            ],
        ];
    }

    /**
     * Compara os campos fiscais imutaveis da tentativa local com a DPS
     * incorporada na NFS-e consultada. Uma colisao de Id nao autoriza a
     * importacao de documento emitido por outro sistema.
     *
     * @return array{compativel: bool, divergencias: array<int,string>}
     */
    private function compararDpsReconciliacao(string $xmlEnviado, string $xmlRetorno): array
    {
        $enviada = $this->extrairCamposDps($xmlEnviado);
        $recuperada = $this->extrairCamposDps($xmlRetorno);
        if ($enviada === null || $recuperada === null) {
            return ['compativel' => false, 'divergencias' => ['xml_dps']];
        }

        $divergencias = [];
        foreach (array_keys($enviada) as $campo) {
            if (($enviada[$campo] ?? null) !== ($recuperada[$campo] ?? null)) {
                $divergencias[] = $campo;
            }
        }

        return ['compativel' => $divergencias === [], 'divergencias' => $divergencias];
    }

    /**
     * @return array<string,string>|null
     */
    private function extrairCamposDps(string $xml): ?array
    {
        if (trim($xml) === '') {
            return null;
        }

        $anterior = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $carregado = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        if (!$carregado) {
            return null;
        }

        $xpath = new \DOMXPath($doc);
        $infDps = $xpath->query('//*[local-name()="infDPS"]')->item(0);
        if (!$infDps instanceof \DOMElement) {
            return null;
        }

        $valor = static function (string $expressao) use ($xpath, $infDps): string {
            $node = $xpath->query($expressao, $infDps)->item(0);
            return $node ? trim((string) $node->nodeValue) : '';
        };
        $digitos = static fn(string $item): string => preg_replace('/\D+/', '', $item) ?? '';
        $normalizarSerie = static function (string $serie): string {
            $serie = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $serie) ?? '');
            return str_pad(substr($serie, 0, 5), 5, '0', STR_PAD_LEFT);
        };
        $normalizarTexto = static function (string $texto): string {
            $texto = mb_strtoupper(trim($texto), 'UTF-8');
            return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
        };

        $tipoDocumentoTomador = '';
        $documentoTomador = '';
        foreach (['CNPJ', 'CPF', 'NIF', 'cNaoNIF'] as $tag) {
            $conteudo = $valor('./*[local-name()="toma"]/*[local-name()="' . $tag . '"]');
            if ($conteudo !== '') {
                $tipoDocumentoTomador = $tag;
                $documentoTomador = $tag === 'cNaoNIF' ? trim($conteudo) : $digitos($conteudo);
                break;
            }
        }

        $prestadorDocumento = $digitos($valor('./*[local-name()="prest"]/*[local-name()="CNPJ" or local-name()="CPF"]'));
        $municipioEmissor = $digitos($valor('./*[local-name()="cLocEmi"]'));
        $serieOriginal = $valor('./*[local-name()="serie"]');
        $numeroOriginal = $digitos($valor('./*[local-name()="nDPS"]'));
        $competencia = $valor('./*[local-name()="dCompet"]');
        $tomadorNome = $valor('./*[local-name()="toma"]/*[local-name()="xNome"]');
        $valorServicosOriginal = $valor('./*[local-name()="valores"]/*[local-name()="vServPrest"]/*[local-name()="vServ"]');
        if (
            $prestadorDocumento === '' || strlen($municipioEmissor) !== 7 || $serieOriginal === ''
            || $numeroOriginal === '' || $competencia === '' || $tipoDocumentoTomador === ''
            || $documentoTomador === '' || $tomadorNome === '' || $valorServicosOriginal === ''
        ) {
            return null;
        }

        $valorServicos = str_replace(',', '.', $valorServicosOriginal);

        return [
            'prestador_documento' => $prestadorDocumento,
            'municipio_emissor' => $municipioEmissor,
            'serie' => $normalizarSerie($serieOriginal),
            'numero' => (string) (int) $numeroOriginal,
            'competencia' => $competencia,
            'tomador_documento' => $tipoDocumentoTomador . ':' . $documentoTomador,
            'tomador_nome' => $normalizarTexto($tomadorNome),
            'valor_servicos' => (string) (int) round(((float) $valorServicos) * 100),
        ];
    }

    /**
     * Registra uma tentativa local que colidiu com DPS externa incompatível.
     * Nenhum dado fiscal do documento externo e persistido.
     */
    private function registrarConflitoDps(int $idNFSe, array $divergencias): array
    {
        $mensagem = NFSeErros::getMensagem('DPS_CONFLITO');
        $campos = array_values(array_unique(array_filter(array_map('strval', $divergencias))));
        $detalhe = $campos === []
            ? $mensagem
            : $mensagem . ' Campos divergentes: ' . implode(', ', $campos) . '.';

        $this->nfseModel->marcarConflitoDps($idNFSe, $mensagem);
        // CONFLITO cabe no schema anterior à 00427 durante implantação em duas etapas.
        $this->eventoModel->registrar($idNFSe, 'conflito_dps', 'CONFLITO', $detalhe);

        return $this->erro($mensagem, 'DPS_CONFLITO');
    }

    private function extrairIdDPS(array $nfse, array $config): ?string
    {
        $xml = (string) ($nfse['xml_envio'] ?? '');
        if ($xml !== '' && preg_match('/<infDPS\b[^>]*\b(?:Id|id)=["\'](DPS[A-Z0-9]{42})["\']/i', $xml, $matches)) {
            return strtoupper($matches[1]);
        }

        $codigoMunicipio = preg_replace('/\D/', '', (string) ($config['codigo_municipio'] ?? '')) ?? '';
        $documento = preg_replace('/\D/', '', (string) ($nfse['prestador_cnpj'] ?? '')) ?? '';
        $serie = preg_replace('/[^A-Za-z0-9]/', '', (string) ($nfse['serie'] ?? '')) ?? '';
        $numero = preg_replace('/\D/', '', (string) ($nfse['numero'] ?? '')) ?? '';
        if (strlen($codigoMunicipio) !== 7 || !in_array(strlen($documento), [11, 14], true) || $serie === '' || $numero === '') {
            return null;
        }

        $tipoInscricao = strlen($documento) === 14 ? '2' : '1';
        return 'DPS'
            . $codigoMunicipio
            . $tipoInscricao
            . str_pad($documento, 14, '0', STR_PAD_LEFT)
            . str_pad(substr(strtoupper($serie), 0, 5), 5, '0', STR_PAD_LEFT)
            . str_pad($numero, 15, '0', STR_PAD_LEFT);
    }

    private function extrairChaveAcessoResposta(string $resposta): ?string
    {
        $json = json_decode($resposta, true);
        if (is_array($json)) {
            $pilha = [$json];
            while ($pilha !== []) {
                $item = array_pop($pilha);
                foreach ($item as $campo => $valor) {
                    if (is_array($valor)) {
                        $pilha[] = $valor;
                        continue;
                    }
                    if (in_array((string) $campo, ['chaveAcesso', 'chNFSe', 'chave'], true)) {
                        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';
                        if (strlen($digitos) === 50) {
                            return $digitos;
                        }
                    }
                }
            }
        }

        return preg_match('/(?<!\d)(\d{50})(?!\d)/', $resposta, $matches) ? $matches[1] : null;
    }

    /**
     * Monta array de dados para geracao de XML
     */
    private function montarDadosNFSe(array $financeiro, array $config, string $chave, array $dadosExtras): array
    {
        // Buscar dados da empresa
        $matrizFilialModel = new MatrizFilial();
        $empresa = $matrizFilialModel->buscarPorId((int) $financeiro['id_matriz_filial']);

        // Buscar dados do cliente
        $cliente = null;
        if (!empty($financeiro['id_cliente'])) {
            $clienteModel = new Cliente();
            $cliente = $clienteModel->buscarPorId((int) $financeiro['id_cliente']);
        }

        $itensNaoTributaveis = $this->normalizarItensNaoTributaveis($dadosExtras['itens_nao_tributaveis'] ?? []);

        // Calcular valores
        $valorServicos = (float) ($financeiro['valor_total'] ?? 0);
        $valorDeducoes = !empty($itensNaoTributaveis)
            ? array_sum(array_column($itensNaoTributaveis, 'valor'))
            : (float) ($dadosExtras['valor_deducoes'] ?? 0);

        if ($valorDeducoes < 0) {
            throw new \InvalidArgumentException('Deduções não podem ser negativas.');
        }
        if ($valorDeducoes > $valorServicos + 0.01) {
            throw new \InvalidArgumentException('Itens não tributáveis não podem ultrapassar o valor total da NFS-e.');
        }

        $baseCalculo = max(0, $valorServicos - $valorDeducoes);
        $tribISSQN = (int) ($config['trib_issqn'] ?? 4);
        $aliquotaISS = (float) ($config['aliquota_iss'] ?? 0);
        $valorISS = $tribISSQN === 1 ? $baseCalculo * ($aliquotaISS / 100) : 0;
        $preencherIBSCBS = ($config['preencher_ibscbs'] ?? 'N') === 'S';
        $aliquotaIBS = 0.0;
        $aliquotaCBS = 0.0;
        $valorIBS = 0.0;
        $valorCBS = 0.0;
        $dataFiscal = $this->agoraFiscal();
        $tipoEmissao = (string) ($config['tipo_emissao'] ?? 'nacional');
        $regimeTributario = (int) ($config['regime_tributario'] ?? 1);
        $dataObrigatoriedade = in_array($regimeTributario, [1, 4], true)
            ? self::IBSCBS_OBRIGATORIO_SIMPLES
            : self::IBSCBS_OBRIGATORIO_GERAL;
        $dataObrigatoriedadeFiscal = new \DateTimeImmutable(
            $dataObrigatoriedade . ' 00:00:00',
            new \DateTimeZone(self::FISCAL_TIMEZONE)
        );
        $configIBSCBSCompleta = strlen(preg_replace('/\D/', '', (string) ($config['c_ind_op_ibscbs'] ?? '')) ?? '') === 6
            && strlen(preg_replace('/\D/', '', (string) ($config['cst_ibscbs'] ?? '')) ?? '') === 3
            && strlen(preg_replace('/\D/', '', (string) ($config['c_class_trib_ibscbs'] ?? '')) ?? '') === 6;

        // Compatibilidade com a flag legada ativada sem dados declaratorios:
        // no Simples/MEI, antes de 2027, o grupo ainda nao e obrigatorio.
        if ($preencherIBSCBS && in_array($regimeTributario, [1, 4], true)
            && $dataFiscal < $dataObrigatoriedadeFiscal && !$configIBSCBSCompleta) {
            $preencherIBSCBS = false;
        }
        $ibscbsObrigatorio = $tipoEmissao === 'nacional'
            && $dataFiscal >= $dataObrigatoriedadeFiscal;

        if ($preencherIBSCBS && $tipoEmissao !== 'nacional') {
            throw new \InvalidArgumentException('IBS/CBS está homologado somente para a emissão Nacional.');
        }
        if ($ibscbsObrigatorio && !$preencherIBSCBS) {
            throw new \InvalidArgumentException('O preenchimento de IBS/CBS é obrigatório para este regime tributário na DPS Nacional.');
        }
        if ($preencherIBSCBS) {
            $this->validarConfiguracaoIBSCBS($config);
        }
        $tomadorEndereco = $this->montarEnderecoTomador($cliente);
        $codigoMunicipioTomador = preg_replace('/\D/', '', (string) ($dadosExtras['tomador_codigo_municipio'] ?? ''));
        if (strlen($codigoMunicipioTomador) === 7) {
            $tomadorEndereco['codigo_municipio'] = $codigoMunicipioTomador;
        }
        $descricaoBase = $this->valorPreferencial(
            $dadosExtras['descricao_servico'] ?? '',
            $config['descricao_servico'] ?? ''
        );
        if ($descricaoBase === '') {
            $descricaoBase = 'Locação de veículo automotor sem condutor.';
        }
        $descricaoServico = $this->montarDescricaoServicoComPlacas(
            $descricaoBase,
            $this->resolverPlacasFinanceiro($financeiro)
        );
        $tipoTomador = strtoupper(trim((string) ($cliente['tipo'] ?? '')));
        $paisTomador = strtoupper(trim((string) ($cliente['pais'] ?? 'BR')));
        $comercioExterior = $this->montarComercioExteriorBetha(
            $tipoEmissao,
            $tipoTomador,
            $paisTomador,
            (string) ($empresa['currency_code'] ?? 'BRL'),
            $valorServicos
        );

        return [
            'ambiente' => (int) ($config['ambiente'] ?? 2),
            'serie' => $config['serie'] ?? 'DPS',
            'data_emissao' => $dataFiscal->format('Y-m-d\TH:i:sP'),
            'data_competencia' => $dataFiscal->format('Y-m-d'),
            'municipio_codigo' => $config['codigo_municipio'] ?? '',
            'tipo_emissao' => $tipoEmissao,
            'id_financeiro' => (int) $financeiro['id'],
            'id_locacao' => $financeiro['id_locacao'] ?? null,
            'id_contrato' => $financeiro['id_contrato'] ?? null,
            'id_matriz_filial' => (int) $financeiro['id_matriz_filial'],
            'prestador' => [
                'cnpj' => $empresa['cpf_cnpj'] ?? '',
                'razao_social' => $empresa['razao_social'] ?? '',
                'inscricao_municipal' => $empresa['ins_muni'] ?? $empresa['inscricao_municipal'] ?? '',
                'telefone' => $empresa['telefone'] ?? '',
                'email' => $empresa['email'] ?? '',
                'regime_tributario' => (int) ($config['regime_tributario'] ?? 1),
                'reg_apuracao_sn' => (int) ($config['reg_apuracao_sn'] ?? 1),
                'enviar_im' => $config['enviar_im'] ?? 'N',
            ],
            'tomador' => [
                'tipo' => $tipoTomador,
                'pais' => $paisTomador,
                'cpf_cnpj' => trim((string) ($cliente['cpf_cnpj'] ?? '')),
                'nome' => trim((string) ($cliente['nome_rsocial'] ?? '')),
                'email' => $this->valorPreferencial($dadosExtras['tomador_email'] ?? '', $cliente['email'] ?? ''),
                'endereco' => $tomadorEndereco,
            ],
            'servico' => [
                'codigo' => $config['codigo_servico'] ?? '1.1101.11',
                'codigo_tributacao_nacional' => $config['codigo_tributacao_nacional'] ?? null,
                'item_lista_servico' => $config['item_lista_servico'] ?? '',
                'codigo_cnae' => $config['codigo_cnae'] ?? '',
                'codigo_tributacao_municipio' => $config['codigo_tributacao_municipio'] ?? '',
                'descricao' => $descricaoServico,
            ],
            'valores' => [
                'servicos' => $valorServicos,
                'deducoes' => $valorDeducoes,
                'base_calculo' => $baseCalculo,
                'aliquota_iss' => $aliquotaISS,
                'valor_iss' => $valorISS,
                'trib_issqn' => $tribISSQN,
                'preencher_ibscbs' => $preencherIBSCBS ? 'S' : 'N',
                'c_ind_op_ibscbs' => $config['c_ind_op_ibscbs'] ?? null,
                'cst_ibscbs' => $config['cst_ibscbs'] ?? null,
                'c_class_trib_ibscbs' => $config['c_class_trib_ibscbs'] ?? null,
                'exigibilidade_iss' => (int) ($config['exigibilidade_iss'] ?? 1),
                'aliquota_ibs' => $aliquotaIBS,
                'valor_ibs' => $valorIBS,
                'aliquota_cbs' => $aliquotaCBS,
                'valor_cbs' => $valorCBS,
                'iss_retido' => $dadosExtras['iss_retido'] ?? 'N',
            ],
            'comercio_exterior' => $comercioExterior,
            'incentivo_fiscal' => $config['incentivo_fiscal'] ?? 'N',
            'itens_nao_tributaveis' => $itensNaoTributaveis,
        ];
    }

    private function montarComercioExteriorBetha(
        string $tipoEmissao,
        string $tipoTomador,
        string $paisTomador,
        string $moeda,
        float $valorServicos
    ): ?array {
        if ($tipoEmissao !== 'betha' || $tipoTomador !== 'ES' || $paisTomador === 'BR') {
            return null;
        }

        $moeda = strtoupper(trim($moeda));
        $codigoMoedaBacen = self::MOEDAS_BACEN[$moeda] ?? null;
        if ($codigoMoedaBacen === null) {
            throw new \InvalidArgumentException(
                "A moeda {$moeda} da filial não possui código BACEN configurado para emissão Betha com tomador estrangeiro."
            );
        }

        return [
            'mdPrestacao' => 2,
            'vincPrest' => 0,
            'tpMoeda' => $codigoMoedaBacen,
            'vServMoeda' => round($valorServicos, 2),
            'mecAFComexP' => 1,
            'mecAFComexT' => 1,
            'movTempBens' => 1,
            'mdic' => 0,
        ];
    }

    /**
     * Cria registro na tabela nfse com status pendente
     */
    private function criarRegistroNFSe(array $dados, array $config, string $chave): int
    {
        $valores = $dados['valores'] ?? [];

        return $this->nfseModel->criar([
            'chave' => $chave,
            'id_matriz_filial' => $dados['id_matriz_filial'],
            'id_financeiro' => $dados['id_financeiro'] ?? null,
            'id_locacao' => $dados['id_locacao'] ?? null,
            'id_contrato' => $dados['id_contrato'] ?? null,
            'numero' => $dados['numero'] ?? null,
            'serie' => $dados['serie'] ?? null,
            'prestador_cnpj' => $dados['prestador']['cnpj'] ?? null,
            'prestador_razao_social' => $dados['prestador']['razao_social'] ?? null,
            'prestador_inscricao_municipal' => $dados['prestador']['inscricao_municipal'] ?? null,
            'tomador_cpf_cnpj' => $dados['tomador']['cpf_cnpj'] ?? null,
            'tomador_tipo' => $dados['tomador']['tipo'] ?? null,
            'tomador_pais' => $dados['tomador']['pais'] ?? null,
            'tomador_nome' => $dados['tomador']['nome'] ?? null,
            'tomador_email' => $dados['tomador']['email'] ?? null,
            'tomador_endereco' => is_array($dados['tomador']['endereco'] ?? null) ? json_encode($dados['tomador']['endereco']) : ($dados['tomador']['endereco'] ?? null),
            'codigo_servico' => $dados['servico']['codigo'] ?? null,
            'descricao_servico' => $dados['servico']['descricao'] ?? null,
            'valor_servicos' => $valores['servicos'] ?? 0,
            'valor_deducoes' => $valores['deducoes'] ?? 0,
            'itens_nao_tributaveis' => !empty($dados['itens_nao_tributaveis']) ? json_encode($dados['itens_nao_tributaveis']) : null,
            'base_calculo' => $valores['base_calculo'] ?? 0,
            'aliquota_iss' => $valores['aliquota_iss'] ?? 0,
            'valor_iss' => $valores['valor_iss'] ?? 0,
            'aliquota_ibs' => $valores['aliquota_ibs'] ?? 0,
            'valor_ibs' => $valores['valor_ibs'] ?? 0,
            'aliquota_cbs' => $valores['aliquota_cbs'] ?? 0,
            'valor_cbs' => $valores['valor_cbs'] ?? 0,
            'iss_retido' => $valores['iss_retido'] ?? 'N',
            'ambiente' => $dados['ambiente'] ?? 2,
            'status' => 'pendente',
            'tipo_emissao' => $dados['tipo_emissao'] ?? 'nacional',
            'data_emissao' => $this->formatarDataBanco($dados['data_emissao'] ?? null),
            'data_competencia' => $dados['data_competencia'] ?? $this->agoraFiscal()->format('Y-m-d'),
        ]);
    }

    private function agoraFiscal(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(self::FISCAL_TIMEZONE));
    }

    private function formatarDataBanco(?string $data): string
    {
        try {
            $timezone = new \DateTimeZone(self::FISCAL_TIMEZONE);
            $date = $data
                ? new \DateTimeImmutable($data)
                : new \DateTimeImmutable('now', $timezone);

            return $date->setTimezone($timezone)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $this->agoraFiscal()->format('Y-m-d H:i:s');
        }
    }

    private function normalizarItensNaoTributaveis(mixed $itens): array
    {
        if (is_string($itens)) {
            $decoded = json_decode($itens, true);
            $itens = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($itens)) {
            return [];
        }

        $normalizados = [];
        foreach ($itens as $item) {
            if (!is_array($item)) {
                continue;
            }

            $descricao = trim((string) ($item['descricao'] ?? ''));
            $valor = $this->parseValorMonetario($item['valor'] ?? 0);

            if ($descricao === '' && $valor <= 0) {
                continue;
            }
            if ($descricao === '' || $valor <= 0) {
                throw new \InvalidArgumentException('Itens não tributáveis precisam ter descrição e valor maior que zero.');
            }

            $normalizados[] = [
                'descricao' => mb_strtoupper($descricao),
                'valor' => round($valor, 2),
            ];
        }

        return $normalizados;
    }

    private function parseValorMonetario(mixed $valor): float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return 0.0;
        }
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        return function_exists('currency_parse')
            ? (float) currency_parse($valor)
            : (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $valor));
    }

    private function valorPreferencial(mixed $preferencial, mixed $fallback): string
    {
        $preferencial = trim((string) ($preferencial ?? ''));
        if ($preferencial !== '') {
            return $preferencial;
        }

        return trim((string) ($fallback ?? ''));
    }

    private function montarDescricaoServicoComPlacas(string $descricao, array $placas): string
    {
        $descricao = trim($descricao);
        $placas = $this->normalizarPlacas($placas);
        if ($descricao === '' || empty($placas)) {
            return $descricao;
        }

        $descricaoNormalizada = $this->normalizarPlacaComparacao($descricao);
        $placasNovas = [];
        foreach ($placas as $placa) {
            if (!str_contains($descricaoNormalizada, $this->normalizarPlacaComparacao($placa))) {
                $placasNovas[] = $placa;
            }
        }

        if (empty($placasNovas)) {
            return $descricao;
        }

        $rotulo = count($placasNovas) === 1 ? 'Placa' : 'Placas';
        return $descricao . ' ' . $rotulo . ': ' . implode(', ', $placasNovas);
    }

    private function resolverPlacasFinanceiro(array $financeiro): array
    {
        $placas = [];
        if (!empty($financeiro['veiculo_placa'])) {
            $placas[] = $financeiro['veiculo_placa'];
        }

        if (!empty($financeiro['id']) && empty($placas)) {
            $itens = (new FinanceiroItem())->listarComRelacionamentos((int) $financeiro['id']);
            foreach ($itens as $item) {
                if (!empty($item['veiculo_placa'])) {
                    $placas[] = $item['veiculo_placa'];
                }
            }
        }

        if (!empty($financeiro['id_locacao']) && empty($placas)) {
            $veiculo = (new LocacaoVeiculo())->buscarAtualOuUltimo((int) $financeiro['id_locacao']);
            if (!empty($veiculo['veiculo_placa'])) {
                $placas[] = $veiculo['veiculo_placa'];
            }
        }

        if (!empty($financeiro['id_contrato']) && empty($placas)) {
            $contratoVeiculoModel = new ContratoVeiculo();
            $veiculos = $contratoVeiculoModel->listarAtivos((int) $financeiro['id_contrato']);
            if (empty($veiculos)) {
                $veiculos = $contratoVeiculoModel->listarPorContrato((int) $financeiro['id_contrato']);
            }

            foreach ($veiculos as $veiculo) {
                if (!empty($veiculo['veiculo_placa'])) {
                    $placas[] = $veiculo['veiculo_placa'];
                }
            }
        }

        return $this->normalizarPlacas($placas);
    }

    private function normalizarPlacas(array $placas): array
    {
        $normalizadas = [];
        foreach ($placas as $placa) {
            $placa = strtoupper(trim((string) $placa));
            if ($placa === '') {
                continue;
            }

            $chave = $this->normalizarPlacaComparacao($placa);
            if ($chave === '' || isset($normalizadas[$chave])) {
                continue;
            }

            $normalizadas[$chave] = $placa;
        }

        return array_values($normalizadas);
    }

    private function normalizarPlacaComparacao(string $valor): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($valor)) ?? '';
    }

    private function montarEnderecoTomador(?array $cliente): array
    {
        if (!$cliente) {
            return [];
        }

        $pais = strtoupper(trim((string) ($cliente['pais'] ?? 'BR')));
        $paisCadastro = $pais !== '' ? (new Pais())->buscarPorCodigo($pais) : null;

        return [
            'logradouro' => $cliente['rua'] ?? $cliente['logradouro'] ?? $cliente['endereco'] ?? '',
            'numero' => $cliente['numero'] ?? '',
            'complemento' => $cliente['complemento'] ?? '',
            'bairro' => $cliente['bairro'] ?? '',
            'cidade' => $cliente['cidade'] ?? '',
            'uf' => $cliente['estado'] ?? '',
            'cep' => $cliente['cep'] ?? '',
            'codigo_municipio' => $cliente['codigo_municipio'] ?? $cliente['municipio_codigo'] ?? $cliente['codigo_ibge'] ?? '',
            'pais' => $pais,
            'codigo_pais_bacen' => $paisCadastro['codigo_bacen'] ?? '',
        ];
    }

    private function resolverAPI(string $tipo, array $config = []): NFSeAPIInterface
    {
        return match ($tipo) {
            'nacional' => new NFSeAPINacional(),
            'betha' => new NFSeAPIBetha(),
            'issnet' => new NFSeAPIISSNet($config),
            default => throw new \InvalidArgumentException('Tipo de emissão NFS-e não suportado: ' . $tipo),
        };
    }

    private function resolverXML(string $tipo): NFSeXMLInterface
    {
        return match ($tipo) {
            'nacional' => new NFSeXMLNacional(),
            'betha' => new NFSeXMLBetha(),
            'issnet' => new NFSeXMLISSNet(),
            default => throw new \InvalidArgumentException('Tipo de emissão NFS-e não suportado: ' . $tipo),
        };
    }

    private function prepararXMLAssinado(string $tipoEmissao, array &$dados, array $config, string $chave, array $pem): array
    {
        if (!in_array($tipoEmissao, ['nacional', 'betha', 'issnet'], true)) {
            return ['sucesso' => false, 'mensagem' => 'Tipo de emissão NFS-e não suportado: ' . $tipoEmissao, 'codigo' => 'CONFIGURACAO_INCOMPLETA'];
        }

        $idMatrizFilial = (int) $config['id_matriz_filial'];

        try {
            if ($tipoEmissao === 'issnet') {
                $this->validarISSNet($dados);
            } else {
                $this->validarDPS($dados);
            }
        } catch (\InvalidArgumentException $e) {
            return ['sucesso' => false, 'mensagem' => $e->getMessage(), 'codigo' => $this->codigoValidacaoDPS($e->getMessage())];
        }

        $xmlGenerator = $this->resolverXML($tipoEmissao);
        $idAttribute = $tipoEmissao === 'betha' ? 'id' : 'Id';
        $tagToSign = $tipoEmissao === 'issnet' ? 'InfDeclaracaoPrestacaoServico' : 'infDPS';
        $algoritmo = $tipoEmissao === 'issnet' ? 'sha1' : 'sha256';

        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            $dados['numero'] = $this->configModel->consultarProximoNumero($idMatrizFilial, $chave);
            $xml = $xmlGenerator->gerarXML($dados);
            $assinado = $this->assinatura->assinar($xml, $pem['certPath'], $pem['keyPath'], $tagToSign, $algoritmo, $idAttribute);

            if (!$assinado['sucesso']) {
                return ['sucesso' => false, 'mensagem' => $assinado['mensagem'], 'codigo' => 'XML_ASSINATURA'];
            }

            if ($this->configModel->reservarNumero($idMatrizFilial, (int) $dados['numero'], $chave)) {
                return [
                    'sucesso' => true,
                    'xml' => $assinado['xml'],
                    'api' => $this->resolverAPI($tipoEmissao, $config),
                    'xml_parser' => $xmlGenerator,
                    'numero' => $dados['numero'],
                ];
            }
        }

        return [
            'sucesso' => false,
            'mensagem' => 'Não foi possível reservar número sequencial da NFS-e. Tente novamente.',
            'codigo' => 'CONFIGURACAO_INCOMPLETA',
        ];
    }

    public function consultarBethaProcessando(int $idNFSe, string $chave): array
    {
        $nfse = $this->nfseModel->buscarPorId($idNFSe);
        if (!$nfse || ($nfse['tipo_emissao'] ?? '') !== 'betha' || empty($nfse['protocolo'])) {
            return $this->erro('NFS-e Betha não encontrada ou sem protocolo.', 'NOTA_NAO_ENCONTRADA');
        }

        $config = $this->configModel->buscarPorMatrizFilial((int) $nfse['id_matriz_filial']);
        if (!$config) {
            return $this->erro('Configurações não encontradas.', 'CONFIGURACAO_INCOMPLETA');
        }

        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);

        try {
            $api = new NFSeAPIBetha();
            $xmlParser = new NFSeXMLBetha();
            $resultado = $api->consultarStatusDps(
                (string) $nfse['protocolo'],
                (string) ($config['codigo_municipio'] ?? ''),
                (string) ($nfse['prestador_cnpj'] ?? ''),
                $pem['certPath'],
                $pem['keyPath'],
                (int) $config['ambiente']
            );
            $retorno = $xmlParser->parseRetornoStatus($resultado['resposta'] ?? '');

            if ($retorno['sucesso']) {
                $this->nfseModel->atualizarAutorizada($idNFSe, [
                    'numero' => $retorno['numero'] ?: $nfse['numero'],
                    'codigo_verificacao' => $retorno['codigo_verificacao'],
                    'chave_acesso' => $retorno['chave_acesso'],
                    'xml_retorno' => $retorno['xml_retorno'] ?? $resultado['resposta'],
                ]);
                $this->eventoModel->registrar($idNFSe, 'consulta', null, 'NFS-e Betha autorizada após consulta de protocolo.', $resultado['resposta'] ?? null);

                return ['sucesso' => true, 'mensagem' => 'NFS-e Betha autorizada.', 'dados' => $retorno];
            }

            if (!empty($retorno['erros'])) {
                $erro = $retorno['erros'][0];
                $codigoInterno = NFSeErros::mapearErroAPI($erro['codigo'] ?? 'ERRO_DESCONHECIDO');
                $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $erro['mensagem'] ?? 'Erro Betha', $codigoInterno);
                $this->eventoModel->registrar($idNFSe, 'erro', $erro['codigo'] ?? null, $erro['mensagem'] ?? '', $resultado['resposta'] ?? null);
                return $this->erro($erro['mensagem'] ?? 'Erro Betha', $codigoInterno, $retorno['erros']);
            }

            return ['sucesso' => true, 'mensagem' => 'NFS-e Betha ainda em processamento.', 'dados' => $retorno];
        } catch (\Throwable $e) {
            return $this->erro('Erro na consulta Betha: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    /**
     * Reconcilia no ADN cancelamentos/substituicoes feitos fora do sistema.
     */
    public function consultarSituacaoFiscalBetha(int $idNFSe, string $chave, bool $registrarConsulta = false): array
    {
        $nfse = $this->nfseModel->buscarPorId($idNFSe);
        if (!$nfse
            || ($nfse['tipo_emissao'] ?? '') !== 'betha'
            || ($nfse['status'] ?? '') !== 'autorizada'
            || empty($nfse['chave_acesso'])) {
            return $this->erro('NFS-e Betha autorizada não encontrada ou sem chave de acesso.', 'NOTA_NAO_ENCONTRADA');
        }
        if (!array_key_exists('situacao_fiscal', $nfse)
            || !array_key_exists('situacao_fiscal_consultada_em', $nfse)) {
            return $this->erro('Atualização da sincronização fiscal pendente no servidor.', 'CONFIGURACAO_INCOMPLETA');
        }

        $config = $this->configModel->buscarPorMatrizFilial((int) $nfse['id_matriz_filial']);
        if (!$config) {
            return $this->erro('Configurações não encontradas.', 'CONFIGURACAO_INCOMPLETA');
        }

        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);
        try {
            return $this->consultarSituacaoFiscalBethaComPem($nfse, $config, $pem, $registrarConsulta);
        } catch (\Throwable $e) {
            return $this->erro('Erro na sincronização fiscal Betha: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    private function consultarSituacaoFiscalBethaComPem(
        array $nfse,
        array $config,
        array $pem,
        bool $registrarConsulta
    ): array {
        if (!array_key_exists('situacao_fiscal', $nfse)
            || !array_key_exists('situacao_fiscal_consultada_em', $nfse)) {
            return $this->erro('Atualização da sincronização fiscal pendente no servidor.', 'CONFIGURACAO_INCOMPLETA');
        }

        $idNFSe = (int) $nfse['id'];
        $api = new NFSeAPINacional();
        $resultado = $api->consultarEventos(
            (string) $nfse['chave_acesso'],
            $pem['certPath'],
            $pem['keyPath'],
            (int) ($config['ambiente'] ?? $nfse['ambiente'] ?? 2)
        );

        if (!($resultado['sucesso'] ?? false)) {
            $httpCode = (int) ($resultado['httpCode'] ?? 0);
            $mensagem = $resultado['erro'] ?? "ADN retornou HTTP {$httpCode} ao consultar os eventos.";
            return $this->erro($mensagem, $resultado['codigoErro'] ?? 'CONN_CURL');
        }

        $resposta = (string) ($resultado['resposta'] ?? '');
        if ($resposta === '' && (int) ($resultado['httpCode'] ?? 0) === 204) {
            $resposta = '[]';
        }
        $situacao = (new NFSeEventosNacional())->parseSituacao($resposta, (string) $nfse['chave_acesso']);
        if (!($situacao['sucesso'] ?? false)) {
            $mensagem = $situacao['mensagem'] ?? 'Não foi possível interpretar os eventos retornados pelo ADN.';
            $this->eventoModel->registrar($idNFSe, 'erro_integracao', 'ADN_EVENTOS_INVALIDO', $mensagem, $resposta);
            return $this->erro($mensagem, 'ERRO_DESCONHECIDO');
        }

        $codigoSituacao = (string) ($situacao['situacao'] ?? 'N');
        if ($codigoSituacao === 'N') {
            $this->nfseModel->registrarSituacaoFiscalNormal($idNFSe);
            if ($registrarConsulta) {
                $this->eventoModel->registrar(
                    $idNFSe,
                    'consulta_situacao',
                    'N',
                    'Situação fiscal consultada no ADN: NFS-e normal.',
                    $resposta
                );
            }
            return [
                'sucesso' => true,
                'situacao' => 'N',
                'mensagem' => 'Situação fiscal sincronizada: NFS-e normal.',
                'dados' => $situacao,
            ];
        }

        $evento = is_array($situacao['evento'] ?? null) ? $situacao['evento'] : [];
        $dataEvento = $this->normalizarDataEvento($evento['data_evento'] ?? null);
        $motivo = trim((string) ($evento['motivo'] ?? ''));
        if ($codigoSituacao === 'S' && !empty($evento['chave_substituta'])) {
            $motivo = trim($motivo . ' Chave substituta: ' . $evento['chave_substituta']);
        }
        $this->nfseModel->atualizarSituacaoFiscalExterna($idNFSe, $codigoSituacao, $dataEvento, $motivo ?: null);

        $substituida = $codigoSituacao === 'S';
        $this->eventoModel->registrar(
            $idNFSe,
            'reconciliacao',
            $substituida ? 'SUBSTITUICAO_EXTERNA' : 'CANCELAMENTO_EXTERNO',
            $substituida
                ? 'Substituição registrada fora do sistema e confirmada no ADN.'
                : 'Cancelamento registrado fora do sistema e confirmado no ADN.',
            $resposta
        );

        return [
            'sucesso' => true,
            'situacao' => $codigoSituacao,
            'mensagem' => $substituida
                ? 'NFS-e sincronizada como substituída.'
                : 'NFS-e sincronizada como cancelada.',
            'dados' => $situacao,
        ];
    }

    /**
     * Consulta um protocolo de cancelamento Betha ate a confirmacao fiscal final.
     */
    public function consultarBethaCancelamentoProcessando(int $idNFSe, string $chave): array
    {
        $nfse = $this->nfseModel->buscarPorId($idNFSe);
        if (!$nfse
            || ($nfse['tipo_emissao'] ?? '') !== 'betha'
            || ($nfse['status'] ?? '') !== 'autorizada'
            || ($nfse['cancelamento_status'] ?? '') !== 'processando'
            || empty($nfse['cancelamento_protocolo'])) {
            return $this->erro('Cancelamento Betha não encontrado ou sem protocolo.', 'NOTA_NAO_ENCONTRADA');
        }

        $config = $this->configModel->buscarPorMatrizFilial((int) $nfse['id_matriz_filial']);
        if (!$config) {
            return $this->erro('Configurações não encontradas.', 'CONFIGURACAO_INCOMPLETA');
        }

        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);

        try {
            $api = new NFSeAPIBetha();
            $xmlParser = new NFSeXMLBetha();
            $resultado = $api->consultarStatusDps(
                (string) $nfse['cancelamento_protocolo'],
                (string) ($config['codigo_municipio'] ?? ''),
                (string) ($nfse['prestador_cnpj'] ?? ''),
                $pem['certPath'],
                $pem['keyPath'],
                (int) $config['ambiente'],
                'CANCELAMENTO'
            );
            $retorno = $xmlParser->parseRetornoCancelamento($resultado['resposta'] ?? '');

            if ($retorno['sucesso']) {
                $this->nfseModel->atualizarCancelada($idNFSe, (string) ($nfse['motivo_cancelamento'] ?? ''));
                $this->eventoModel->registrar(
                    $idNFSe,
                    'cancelamento',
                    null,
                    'NFS-e Betha cancelada após consulta de protocolo.',
                    $resultado['resposta'] ?? null
                );
                return ['sucesso' => true, 'mensagem' => 'NFS-e Betha cancelada.', 'dados' => $retorno];
            }

            if (!empty($retorno['erros'])) {
                $erro = $retorno['erros'][0];
                if ($this->mensagemIndicaSituacaoFiscalAlterada((string) ($erro['mensagem'] ?? ''))) {
                    $sincronizacao = $this->consultarSituacaoFiscalBethaComPem($nfse, $config, $pem, false);
                    if (($sincronizacao['sucesso'] ?? false) && in_array($sincronizacao['situacao'] ?? '', ['C', 'S'], true)) {
                        return $sincronizacao;
                    }
                }
                $this->nfseModel->marcarErroCancelamento($idNFSe);
                $this->eventoModel->registrar(
                    $idNFSe,
                    'erro',
                    $erro['codigo'] ?? null,
                    $erro['mensagem'] ?? 'Erro no cancelamento Betha.',
                    $resultado['resposta'] ?? null
                );
                return $this->erro(
                    $erro['mensagem'] ?? 'Erro no cancelamento Betha.',
                    NFSeErros::mapearErroAPI($erro['codigo'] ?? 'ERRO_DESCONHECIDO'),
                    $retorno['erros']
                );
            }

            return ['sucesso' => true, 'mensagem' => 'Cancelamento Betha ainda em processamento.', 'dados' => $retorno];
        } catch (\Throwable $e) {
            return $this->erro('Erro na consulta do cancelamento Betha: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    private function mensagemIndicaSituacaoFiscalAlterada(string $mensagem): bool
    {
        $normalizada = mb_strtolower($mensagem, 'UTF-8');

        return str_contains($normalizada, 'situação n - normal')
            || str_contains($normalizada, 'situacao n - normal')
            || str_contains($normalizada, 'nota fiscal deve estar com a situação n')
            || str_contains($normalizada, 'nota fiscal deve estar com a situacao n');
    }

    private function normalizarDataEvento(mixed $data): ?string
    {
        $data = trim((string) $data);
        if ($data === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($data))
                ->setTimezone(new \DateTimeZone(self::FISCAL_TIMEZONE))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function validarDPS(array $dados): void
    {
        $codigoMunicipio = preg_replace('/\D/', '', (string) ($dados['municipio_codigo'] ?? ''));
        if (strlen($codigoMunicipio) !== 7) {
            throw new \InvalidArgumentException('Código IBGE do município deve ter 7 dígitos.');
        }

        $tomador = $dados['tomador'] ?? [];
        $nomeTomador = trim((string) ($tomador['nome'] ?? ''));
        if ($nomeTomador === '') {
            throw new \InvalidArgumentException('Nome do cliente não informado.');
        }

        if (($tomador['tipo'] ?? '') === 'ES') {
            $passaporte = trim((string) ($tomador['cpf_cnpj'] ?? ''));
            $pais = strtoupper(trim((string) ($tomador['pais'] ?? '')));
            if ($passaporte === '' || mb_strlen($passaporte, 'UTF-8') > 40) {
                throw new \InvalidArgumentException('Passaporte do cliente estrangeiro deve ter entre 1 e 40 caracteres.');
            }
            if (!preg_match('/^[A-Z]{2}$/', $pais) || $pais === 'BR' || !(new Pais())->buscarPorCodigo($pais)) {
                throw new \InvalidArgumentException('Informe o país estrangeiro do cliente antes de emitir a NFS-e.');
            }
            return;
        }

        $cpfCnpj = preg_replace('/\D/', '', (string) ($tomador['cpf_cnpj'] ?? ''));
        if ($cpfCnpj === '') {
            throw new \InvalidArgumentException(NFSeErros::getInstrucao('TOMADOR_DOCUMENTO_AUSENTE'));
        }
        if (strlen($cpfCnpj) === 11 && !$this->cpfValido($cpfCnpj)) {
            throw new \InvalidArgumentException(NFSeErros::getMensagem('TOMADOR_CPF_INVALIDO'));
        }
        if (strlen($cpfCnpj) === 14 && !$this->cnpjValido($cpfCnpj)) {
            throw new \InvalidArgumentException(NFSeErros::getMensagem('TOMADOR_CNPJ_INVALIDO'));
        }
        if (!in_array(strlen($cpfCnpj), [11, 14], true)) {
            throw new \InvalidArgumentException(NFSeErros::getInstrucao('TOMADOR_DOCUMENTO_AUSENTE'));
        }
    }

    private function validarISSNet(array $dados): void
    {
        $this->validarDPS($dados);

        $tomador = $dados['tomador'] ?? [];
        if (($tomador['tipo'] ?? '') === 'ES') {
            $endereco = is_array($tomador['endereco'] ?? null) ? $tomador['endereco'] : [];
            if (!preg_match('/^\d{4}$/', (string) ($endereco['codigo_pais_bacen'] ?? ''))) {
                throw new \InvalidArgumentException('O país do cliente não possui código BACEN configurado para emissão pela ISSNet.');
            }
            foreach (['logradouro', 'numero', 'cidade', 'uf'] as $campo) {
                if (trim((string) ($endereco[$campo] ?? '')) === '') {
                    throw new \InvalidArgumentException('Complete o endereço estrangeiro do cliente (logradouro, número, cidade e estado/província) antes de emitir pela ISSNet.');
                }
            }
        }

        $prestador = $dados['prestador'] ?? [];
        $servico = $dados['servico'] ?? [];

        $im = preg_replace('/\D/', '', (string) ($prestador['inscricao_municipal'] ?? ''));
        if ($im === '') {
            throw new \InvalidArgumentException('Inscrição Municipal do prestador é obrigatória para ISSNet.');
        }

        if (trim((string) ($servico['item_lista_servico'] ?? '')) === '') {
            throw new \InvalidArgumentException('Item da lista de serviço é obrigatório para ISSNet.');
        }
    }

    private function codigoValidacaoDPS(string $mensagem): string
    {
        $mensagemLower = mb_strtolower($mensagem, 'UTF-8');

        if (str_contains($mensagemLower, 'ibs/cbs')) {
            return 'IBSCBS_CONFIGURACAO';
        }
        if (str_contains($mensagemLower, 'cpf ou cnpj')) {
            return 'TOMADOR_DOCUMENTO_AUSENTE';
        }
        if (str_contains($mensagemLower, 'cpf')) {
            return 'TOMADOR_CPF_INVALIDO';
        }
        if (str_contains($mensagemLower, 'cnpj')) {
            return 'TOMADOR_CNPJ_INVALIDO';
        }
        if (str_contains($mensagemLower, 'nome do cliente')) {
            return 'TOMADOR_NAO_INFORMADO';
        }

        return 'CONFIGURACAO_INCOMPLETA';
    }

    private function validarConfiguracaoIBSCBS(array $config): void
    {
        $campos = [
            'c_ind_op_ibscbs' => ['tamanho' => 6, 'rotulo' => 'Código indicador da operação IBS/CBS'],
            'cst_ibscbs' => ['tamanho' => 3, 'rotulo' => 'CST do IBS/CBS'],
            'c_class_trib_ibscbs' => ['tamanho' => 6, 'rotulo' => 'Classificação tributária do IBS/CBS'],
        ];

        foreach ($campos as $campo => $regra) {
            $valor = preg_replace('/\D/', '', (string) ($config[$campo] ?? '')) ?? '';
            if (strlen($valor) !== $regra['tamanho']) {
                throw new \InvalidArgumentException($regra['rotulo'] . ' deve ter ' . $regra['tamanho'] . ' dígitos.');
            }
        }

        if (!str_starts_with((string) $config['c_class_trib_ibscbs'], (string) $config['cst_ibscbs'])) {
            throw new \InvalidArgumentException('Os 3 primeiros dígitos da classificação tributária devem ser iguais ao CST do IBS/CBS.');
        }
    }

    private function cpfValido(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    private function cnpjValido(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesosPrimeiro = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesosSegundo = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $calcular = static function (string $base, array $pesos): int {
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += (int) $base[$i] * $peso;
            }
            $resto = $soma % 11;
            return $resto < 2 ? 0 : 11 - $resto;
        };

        $primeiro = $calcular($cnpj, $pesosPrimeiro);
        $segundo = $calcular($cnpj, $pesosSegundo);

        return (int) $cnpj[12] === $primeiro && (int) $cnpj[13] === $segundo;
    }

    private function gerarCorpoEmail(array $nfse): string
    {
        $numero = $nfse['numero'] ?? '';
        $prestador = $nfse['prestador_razao_social'] ?? '';
        $valor = currency_format((float) ($nfse['valor_servicos'] ?? 0));

        return "<p>Prezado(a) <strong>" . htmlspecialchars($nfse['tomador_nome'] ?? '') . "</strong>,</p>"
            . "<p>Segue em anexo a Nota Fiscal de Serviço Eletrônica (NFS-e):</p>"
            . "<ul>"
            . "<li><strong>Número:</strong> {$numero}</li>"
            . "<li><strong>Prestador:</strong> " . htmlspecialchars($prestador) . "</li>"
            . "<li><strong>Valor:</strong> {$valor}</li>"
            . "</ul>"
            . "<p>Atenciosamente,<br>" . htmlspecialchars($prestador) . "</p>";
    }

    /**
     * Formata retorno de erro padronizado
     */
    private function erro(string $mensagem, string $codigo, array $errosAPI = [], ?array $dados = null): array
    {
        $erroFormatado = NFSeErros::formatarParaUsuario($codigo);

        $retorno = [
            'sucesso' => false,
            'mensagem' => $mensagem,
            'erro' => $erroFormatado,
            'erros_api' => $errosAPI,
        ];

        if ($dados !== null) {
            $retorno['dados'] = $dados;
        }

        return $retorno;
    }
}
