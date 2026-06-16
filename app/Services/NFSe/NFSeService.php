<?php

namespace App\Services\NFSe;

use App\Models\NFSe as NFSeModel;
use App\Models\NFSeConfiguracao;
use App\Models\NFSeEvento;
use App\Models\Financeiro;
use App\Models\MatrizFilial;
use App\Models\Cliente;
use App\Services\NFSe\Nacional\NFSeXMLNacional;
use App\Services\NFSe\Nacional\NFSeAPINacional;
use App\Services\NFSe\Betha\NFSeXMLBetha;
use App\Services\NFSe\Betha\NFSeAPIBetha;

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
            return $this->erro('Já existe uma NFS-e emitida para este lançamento.', 'NOTA_DUPLICADA');
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
        if (!$this->certificado->isValido($chave, $config['certificado_arquivo'], $config['certificado_senha'])) {
            return $this->erro('Certificado digital vencido.', 'CERT_EXPIRADO');
        }

        // 5. Montar dados
        try {
            $dados = $this->montarDadosNFSe($financeiro, $config, $chave, $dadosExtras);
        } catch (\InvalidArgumentException $e) {
            return $this->erro($e->getMessage(), 'VALOR_INVALIDO');
        }

        // 6. Rotear por tipo de emissao
        $tipoEmissao = $config['tipo_emissao'] ?? 'nacional';

        return match ($tipoEmissao) {
            'nacional' => $this->emitirNacional($dados, $config, $chave),
            'betha' => $this->emitirBetha($dados, $config, $chave),
            default => $this->erro('Tipo de emissão NFS-e não suportado: ' . $tipoEmissao, 'CONFIGURACAO_INCOMPLETA'),
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
            default => $this->erro('Tipo de emissão NFS-e não suportado: ' . $tipoEmissao, 'CONFIGURACAO_INCOMPLETA'),
        };
    }

    /**
     * Consulta status de NFS-e na SEFIN/prefeitura
     */
    public function consultar(int $idNFSe, string $chave): array
    {
        $nfse = $this->nfseModel->buscarPorId($idNFSe);
        if (!$nfse || (empty($nfse['chave_acesso']) && empty($nfse['protocolo']))) {
            return $this->erro('NFS-e não encontrada ou sem chave de acesso.', 'NOTA_NAO_ENCONTRADA');
        }

        $config = $this->configModel->buscarPorMatrizFilial((int) $nfse['id_matriz_filial']);
        if (!$config) {
            return $this->erro('Configurações não encontradas.', 'CONFIGURACAO_INCOMPLETA');
        }

        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);

        try {
            $api = $this->resolverAPI($nfse['tipo_emissao'] ?? 'nacional');
            $identificador = ($nfse['tipo_emissao'] ?? 'nacional') === 'betha'
                ? (string) ($nfse['protocolo'] ?? '')
                : (string) ($nfse['chave_acesso'] ?? '');
            $resultado = $api->consultar($identificador, $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);

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
    public function reenviar(int $idNFSe, string $chave): array
    {
        $nfse = $this->nfseModel->buscarPorId($idNFSe);
        if (!$nfse) {
            return $this->erro('NFS-e não encontrada.', 'NOTA_NAO_ENCONTRADA');
        }
        if ($nfse['status'] !== 'rejeitada') {
            return $this->erro('Somente NFS-e rejeitadas podem ser reenviadas.', 'ERRO_DESCONHECIDO');
        }
        if ((int) ($nfse['tentativas_envio'] ?? 0) >= 3) {
            return $this->erro('Número máximo de tentativas atingido (3).', 'ERRO_DESCONHECIDO');
        }

        $config = $this->configModel->buscarPorMatrizFilial((int) $nfse['id_matriz_filial']);
        if (!$config) {
            return $this->erro('Configurações não encontradas.', 'CONFIGURACAO_INCOMPLETA');
        }

        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);

        try {
            $tipoEmissao = $nfse['tipo_emissao'] ?? $config['tipo_emissao'] ?? 'nacional';
            $xmlParser = $this->resolverXML($tipoEmissao);

            // Incrementar tentativas
            $this->nfseModel->incrementarTentativas($idNFSe);

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
                $this->nfseModel->atualizarParaReenvio($idNFSe, [
                    'numero' => $preparado['numero'],
                    'serie' => $dados['serie'] ?? null,
                    'xml_envio' => $xml,
                ]);
            } else {
                $xml = $nfse['xml_envio'];
                if (empty($xml)) {
                    return $this->erro('XML de envio não encontrado.', 'XML_INVALIDO');
                }
                $api = $this->resolverAPI($tipoEmissao);
                $this->nfseModel->atualizarStatus($idNFSe, 'processando');
            }

            $resultado = $api->enviar($xml, $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);

            return $this->processarRetornoEmissao($idNFSe, $resultado, $xmlParser, $nfse['chave']);
        } catch (\Throwable $e) {
            $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $e->getMessage(), 'CONN_CURL');
            $this->eventoModel->registrar($idNFSe, 'erro', 'CONN_CURL', $e->getMessage());
            return $this->erro('Erro no reenvio: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
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
        if (empty($email)) {
            return $this->erro('Email do tomador não informado.', 'TOMADOR_EMAIL');
        }

        // Gerar PDF se nao existe
        if (empty($nfse['pdf_url'])) {
            $pdfResult = $this->pdf->gerar($nfse);
            if (!$pdfResult['sucesso']) {
                return $this->erro('Erro ao gerar PDF: ' . $pdfResult['mensagem'], 'ERRO_DESCONHECIDO');
            }
            $this->nfseModel->salvarPdfUrl($idNFSe, $pdfResult['caminho']);
            $nfse['pdf_url'] = $pdfResult['caminho'];
        }

        // Enviar email via fila
        $caminhoCompleto = $this->pdf->getCaminhoCompleto($nfse['pdf_url']);

        queue_message('email', [
            'to' => $email,
            'to_name' => $nfse['tomador_nome'] ?? '',
            'subject' => 'NFS-e Nº ' . ($nfse['numero'] ?? '') . ' - ' . ($nfse['prestador_razao_social'] ?? ''),
            'body' => $this->gerarCorpoEmail($nfse),
            'attachments' => [$caminhoCompleto],
            'id_matriz_filial' => $nfse['id_matriz_filial'],
        ], $chave);

        // Marcar como enviado
        $this->nfseModel->marcarEmailEnviado($idNFSe, $email);
        $this->eventoModel->registrar($idNFSe, 'email', null, "Email enviado para {$email}");

        return [
            'sucesso' => true,
            'mensagem' => 'Email enviado com sucesso para ' . $email,
        ];
    }

    // ==========================================
    // METODOS PRIVADOS - Emissao
    // ==========================================

    private function emitirNacional(array $dados, array $config, string $chave): array
    {
        $idMatrizFilial = (int) $config['id_matriz_filial'];

        // Proximo numero
        $numero = $this->configModel->proximoNumero($idMatrizFilial, 'nacional', $chave);
        $dados['numero'] = $numero;

        // Criar registro pendente
        $idNFSe = $this->criarRegistroNFSe($dados, $config, $chave);

        // Gerar XML
        $xmlGenerator = new NFSeXMLNacional();
        $xml = $xmlGenerator->gerarXML($dados);

        // Assinar (SHA256)
        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);

        try {
            $assinado = $this->assinatura->assinar($xml, $pem['certPath'], $pem['keyPath'], 'infDPS', 'sha256');
            if (!$assinado['sucesso']) {
                $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $assinado['mensagem'], 'XML_ASSINATURA');
                $this->eventoModel->registrar($idNFSe, 'erro', 'XML_ASSINATURA', $assinado['mensagem']);
                return $this->erro($assinado['mensagem'], 'XML_ASSINATURA');
            }

            $xmlAssinado = $assinado['xml'];

            // Salvar XML de envio
            $this->nfseModel->salvarXmlEnvio($idNFSe, $xmlAssinado);
            $this->nfseModel->atualizarStatus($idNFSe, 'processando');

            // Enviar para SEFIN
            $api = new NFSeAPINacional();
            $resultado = $api->enviar($xmlAssinado, $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);

            return $this->processarRetornoEmissao($idNFSe, $resultado, $xmlGenerator, $chave);
        } catch (\Throwable $e) {
            $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $e->getMessage(), 'CONN_CURL');
            $this->eventoModel->registrar($idNFSe, 'erro', 'CONN_CURL', $e->getMessage());
            return $this->erro('Erro na comunicação: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    private function emitirBetha(array $dados, array $config, string $chave): array
    {
        $idMatrizFilial = (int) $config['id_matriz_filial'];
        try {
            $this->validarDPS($dados);
        } catch (\InvalidArgumentException $e) {
            return $this->erro($e->getMessage(), 'CONFIGURACAO_INCOMPLETA');
        }

        $numero = $this->configModel->proximoNumero($idMatrizFilial, 'betha', $chave);
        $dados['numero'] = $numero;

        $idNFSe = $this->criarRegistroNFSe($dados, $config, $chave);
        $xmlGenerator = new NFSeXMLBetha();
        $xml = $xmlGenerator->gerarXML($dados);
        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);

        try {
            $assinado = $this->assinatura->assinar($xml, $pem['certPath'], $pem['keyPath'], 'infDPS', 'sha256', 'id');
            if (!$assinado['sucesso']) {
                $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $assinado['mensagem'], 'XML_ASSINATURA');
                $this->eventoModel->registrar($idNFSe, 'erro', 'XML_ASSINATURA', $assinado['mensagem']);
                return $this->erro($assinado['mensagem'], 'XML_ASSINATURA');
            }

            $xmlAssinado = $assinado['xml'];
            $this->nfseModel->salvarXmlEnvio($idNFSe, $xmlAssinado);
            $this->nfseModel->atualizarStatus($idNFSe, 'processando');

            $api = new NFSeAPIBetha();
            $resultado = $api->enviar($xmlAssinado, $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);

            return $this->processarRetornoEmissao($idNFSe, $resultado, $xmlGenerator, $chave);
        } catch (\Throwable $e) {
            $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $e->getMessage(), 'CONN_CURL');
            $this->eventoModel->registrar($idNFSe, 'erro', 'CONN_CURL', $e->getMessage());
            return $this->erro('Erro na comunicação Betha: ' . $e->getMessage(), 'CONN_CURL');
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
            $xmlGenerator = new NFSeXMLNacional();
            $xml = $xmlGenerator->gerarXMLCancelamento($nfse['chave_acesso'], $motivo, [
                'ambiente' => $config['ambiente'] ?? 2,
            ]);

            $assinado = $this->assinatura->assinar($xml, $pem['certPath'], $pem['keyPath'], 'infPedReg', 'sha256');
            if (!$assinado['sucesso']) {
                return $this->erro($assinado['mensagem'], 'XML_ASSINATURA');
            }

            $api = new NFSeAPINacional();
            $resultado = $api->cancelar($assinado['xml'], $nfse['chave_acesso'], $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);

            $retorno = $xmlGenerator->parseRetornoCancelamento($resultado['resposta'] ?? '');

            if ($retorno['sucesso'] || ($resultado['sucesso'] ?? false)) {
                $this->nfseModel->atualizarCancelada($idNFSe, $motivo);
                $this->eventoModel->registrar($idNFSe, 'cancelamento', null, $motivo, $resultado['resposta'] ?? null);
                return ['sucesso' => true, 'mensagem' => 'NFS-e cancelada com sucesso.'];
            }

            $erroMsg = $retorno['erros'][0]['mensagem'] ?? 'Erro desconhecido ao cancelar.';
            $erroCod = $retorno['erros'][0]['codigo'] ?? 'ERRO_DESCONHECIDO';
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
            $xmlGenerator = new NFSeXMLBetha();
            $xml = $xmlGenerator->gerarXMLCancelamento($nfse['chave_acesso'], $motivo, []);

            $api = new NFSeAPIBetha();
            $resultado = $api->cancelar($xml, $nfse['chave_acesso'], $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);
            $retorno = $xmlGenerator->parseRetornoCancelamento($resultado['resposta'] ?? '');

            if ($retorno['sucesso'] || ($resultado['sucesso'] ?? false)) {
                $this->nfseModel->atualizarCancelada($idNFSe, $motivo);
                $this->eventoModel->registrar($idNFSe, 'cancelamento', null, $motivo, $resultado['resposta'] ?? null);
                return ['sucesso' => true, 'mensagem' => 'NFS-e cancelada com sucesso.'];
            }

            $erroMsg = $retorno['erros'][0]['mensagem'] ?? 'Erro desconhecido ao cancelar na Betha.';
            $erroCod = $retorno['erros'][0]['codigo'] ?? 'ERRO_DESCONHECIDO';
            $this->eventoModel->registrar($idNFSe, 'erro', $erroCod, $erroMsg, $resultado['resposta'] ?? null);
            return $this->erro($erroMsg, NFSeErros::mapearErroAPI($erroCod));
        } catch (\Throwable $e) {
            return $this->erro('Erro no cancelamento Betha: ' . $e->getMessage(), 'CONN_CURL');
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
    private function processarRetornoEmissao(int $idNFSe, array $resultado, NFSeXMLInterface $xmlParser, string $chave): array
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
            $this->nfseModel->atualizarAutorizada($idNFSe, [
                'numero' => $retorno['numero'],
                'codigo_verificacao' => $retorno['codigo_verificacao'],
                'chave_acesso' => $retorno['chave_acesso'],
                'xml_retorno' => $retorno['xml_retorno'] ?? $resultado['resposta'],
            ]);

            $this->eventoModel->registrar($idNFSe, 'emissao', null, 'NFS-e autorizada com sucesso', $resultado['resposta'] ?? null);

            // Gerar PDF
            $nfse = $this->nfseModel->buscarPorId($idNFSe);
            if ($nfse) {
                $pdfResult = $this->pdf->gerar($nfse);
                if ($pdfResult['sucesso']) {
                    $this->nfseModel->salvarPdfUrl($idNFSe, $pdfResult['caminho']);
                }
            }

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
        $codigoInterno = NFSeErros::mapearErroAPI($primeiroErro['codigo']);
        $mensagem = NFSeErros::getMensagem($codigoInterno);

        $this->nfseModel->atualizarStatus($idNFSe, 'rejeitada', $primeiroErro['mensagem'] ?: $mensagem, $codigoInterno);
        $this->eventoModel->registrar($idNFSe, 'erro', $primeiroErro['codigo'], $primeiroErro['mensagem'], $resultado['resposta'] ?? null);

        return $this->erro($mensagem, $codigoInterno, $erros);
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
        $aliquotaIBS = 0.10;
        $aliquotaCBS = 0.90;
        $valorIBS = $valorServicos * ($aliquotaIBS / 100);
        $valorCBS = $valorServicos * ($aliquotaCBS / 100);

        return [
            'ambiente' => (int) ($config['ambiente'] ?? 2),
            'serie' => $config['serie'] ?? 'DPS',
            'data_emissao' => date('Y-m-d\TH:i:sP'),
            'data_competencia' => date('Y-m-d'),
            'municipio_codigo' => $config['codigo_municipio'] ?? '',
            'tipo_emissao' => $config['tipo_emissao'] ?? 'nacional',
            'id_financeiro' => (int) $financeiro['id'],
            'id_locacao' => $financeiro['id_locacao'] ?? null,
            'id_contrato' => $financeiro['id_contrato'] ?? null,
            'id_matriz_filial' => (int) $financeiro['id_matriz_filial'],
            'prestador' => [
                'cnpj' => $empresa['cpf_cnpj'] ?? '',
                'razao_social' => $empresa['razao_social'] ?? '',
                'inscricao_municipal' => $empresa['inscricao_municipal'] ?? '',
                'telefone' => $empresa['celular'] ?? '',
                'email' => $empresa['email'] ?? '',
                'regime_tributario' => (int) ($config['regime_tributario'] ?? 1),
                'reg_apuracao_sn' => (int) ($config['reg_apuracao_sn'] ?? 1),
                'enviar_im' => $config['enviar_im'] ?? 'N',
            ],
            'tomador' => [
                'cpf_cnpj' => $this->valorPreferencial($dadosExtras['tomador_cpf_cnpj'] ?? '', $cliente['cpf_cnpj'] ?? ''),
                'nome' => $this->valorPreferencial($dadosExtras['tomador_nome'] ?? '', $cliente['nome_rsocial'] ?? ''),
                'email' => $this->valorPreferencial($dadosExtras['tomador_email'] ?? '', $cliente['email'] ?? ''),
                'endereco' => $this->montarEnderecoTomador($cliente),
            ],
            'servico' => [
                'codigo' => $config['codigo_servico'] ?? '1.1101.11',
                'descricao' => $dadosExtras['descricao_servico'] ?? $config['descricao_servico'] ?? 'Locação de veículo automotor sem condutor.',
            ],
            'valores' => [
                'servicos' => $valorServicos,
                'deducoes' => $valorDeducoes,
                'base_calculo' => $baseCalculo,
                'aliquota_iss' => $aliquotaISS,
                'valor_iss' => $valorISS,
                'trib_issqn' => $tribISSQN,
                'aliquota_ibs' => $aliquotaIBS,
                'valor_ibs' => $valorIBS,
                'aliquota_cbs' => $aliquotaCBS,
                'valor_cbs' => $valorCBS,
                'iss_retido' => $dadosExtras['iss_retido'] ?? 'N',
            ],
            'itens_nao_tributaveis' => $itensNaoTributaveis,
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
            'aliquota_ibs' => $valores['aliquota_ibs'] ?? 0.10,
            'valor_ibs' => $valores['valor_ibs'] ?? 0,
            'aliquota_cbs' => $valores['aliquota_cbs'] ?? 0.90,
            'valor_cbs' => $valores['valor_cbs'] ?? 0,
            'iss_retido' => $valores['iss_retido'] ?? 'N',
            'ambiente' => $dados['ambiente'] ?? 2,
            'status' => 'pendente',
            'tipo_emissao' => $dados['tipo_emissao'] ?? 'nacional',
            'data_emissao' => date('Y-m-d H:i:s'),
            'data_competencia' => $dados['data_competencia'] ?? date('Y-m-d'),
        ]);
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

    private function montarEnderecoTomador(?array $cliente): array
    {
        if (!$cliente) {
            return [];
        }

        return [
            'logradouro' => $cliente['logradouro'] ?? $cliente['endereco'] ?? '',
            'numero' => $cliente['numero'] ?? '',
            'complemento' => $cliente['complemento'] ?? '',
            'bairro' => $cliente['bairro'] ?? '',
            'cidade' => $cliente['cidade'] ?? '',
            'uf' => $cliente['estado'] ?? '',
            'cep' => $cliente['cep'] ?? '',
        ];
    }

    private function resolverAPI(string $tipo): NFSeAPIInterface
    {
        return match ($tipo) {
            'nacional' => new NFSeAPINacional(),
            'betha' => new NFSeAPIBetha(),
            default => throw new \InvalidArgumentException('Tipo de emissão NFS-e não suportado: ' . $tipo),
        };
    }

    private function resolverXML(string $tipo): NFSeXMLInterface
    {
        return match ($tipo) {
            'nacional' => new NFSeXMLNacional(),
            'betha' => new NFSeXMLBetha(),
            default => throw new \InvalidArgumentException('Tipo de emissão NFS-e não suportado: ' . $tipo),
        };
    }

    private function prepararXMLAssinado(string $tipoEmissao, array &$dados, array $config, string $chave, array $pem): array
    {
        if (!in_array($tipoEmissao, ['nacional', 'betha'], true)) {
            return ['sucesso' => false, 'mensagem' => 'Tipo de emissão NFS-e não suportado: ' . $tipoEmissao, 'codigo' => 'CONFIGURACAO_INCOMPLETA'];
        }

        $idMatrizFilial = (int) $config['id_matriz_filial'];
        $dados['numero'] = $this->configModel->proximoNumero($idMatrizFilial, $tipoEmissao, $chave);

        try {
            $this->validarDPS($dados);
        } catch (\InvalidArgumentException $e) {
            return ['sucesso' => false, 'mensagem' => $e->getMessage(), 'codigo' => 'CONFIGURACAO_INCOMPLETA'];
        }

        $xmlGenerator = $this->resolverXML($tipoEmissao);
        $xml = $xmlGenerator->gerarXML($dados);
        $tag = 'infDPS';
        $algoritmo = 'sha256';
        $idAttribute = $tipoEmissao === 'betha' ? 'id' : 'Id';
        $assinado = $this->assinatura->assinar($xml, $pem['certPath'], $pem['keyPath'], $tag, $algoritmo, $idAttribute);

        if (!$assinado['sucesso']) {
            return ['sucesso' => false, 'mensagem' => $assinado['mensagem'], 'codigo' => 'XML_ASSINATURA'];
        }

        return [
            'sucesso' => true,
            'xml' => $assinado['xml'],
            'api' => $this->resolverAPI($tipoEmissao),
            'numero' => $dados['numero'],
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
            $resultado = $api->consultar((string) $nfse['protocolo'], $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);
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

            $this->eventoModel->registrar($idNFSe, 'consulta', null, 'NFS-e Betha ainda em processamento.', $resultado['resposta'] ?? null);
            return ['sucesso' => true, 'mensagem' => 'NFS-e Betha ainda em processamento.', 'dados' => $retorno];
        } catch (\Throwable $e) {
            return $this->erro('Erro na consulta Betha: ' . $e->getMessage(), 'CONN_CURL');
        } finally {
            $this->certificado->limparPEM($pem['certPath'], $pem['keyPath']);
        }
    }

    private function validarDPS(array $dados): void
    {
        $codigoMunicipio = preg_replace('/\D/', '', (string) ($dados['municipio_codigo'] ?? ''));
        if (strlen($codigoMunicipio) !== 7) {
            throw new \InvalidArgumentException('Código IBGE do município deve ter 7 dígitos.');
        }
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
    private function erro(string $mensagem, string $codigo, array $errosAPI = []): array
    {
        $erroFormatado = NFSeErros::formatarParaUsuario($codigo);

        return [
            'sucesso' => false,
            'mensagem' => $mensagem,
            'erro' => $erroFormatado,
            'erros_api' => $errosAPI,
        ];
    }
}
