<?php

namespace App\Services\NFSe;

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
            return $this->erro($e->getMessage(), 'VALOR_INVALIDO');
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
        if (!$nfse || (empty($nfse['chave_acesso']) && empty($nfse['protocolo']))) {
            return $this->erro('NFS-e não encontrada ou sem chave de acesso.', 'NOTA_NAO_ENCONTRADA');
        }

        $config = $this->configModel->buscarPorMatrizFilial((int) $nfse['id_matriz_filial']);
        if (!$config) {
            return $this->erro('Configurações não encontradas.', 'CONFIGURACAO_INCOMPLETA');
        }

        $pem = $this->certificado->extrairPEM($chave, $config['certificado_arquivo'], $config['certificado_senha']);

        try {
            $tipoEmissao = $nfse['tipo_emissao'] ?? 'nacional';
            $api = $this->resolverAPI($tipoEmissao, $config);
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
        $tentativaExtraManual = $permitirTentativaExtraManual && $this->permiteTentativaExtraManual($nfse);
        if ((int) ($nfse['tentativas_envio'] ?? 0) >= 3 && !$tentativaExtraManual) {
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
                $this->nfseModel->incrementarTentativas($idNFSe);
                if ($tentativaExtraManual) {
                    $this->eventoModel->registrar(
                        $idNFSe,
                        'reenvio_manual',
                        'LIMITE_TECNICO',
                        'Tentativa manual extra liberada após correção técnica do XML/data fiscal.'
                    );
                }
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
                $this->nfseModel->incrementarTentativas($idNFSe);
                if ($tentativaExtraManual) {
                    $this->eventoModel->registrar(
                        $idNFSe,
                        'reenvio_manual',
                        'LIMITE_TECNICO',
                        'Tentativa manual extra liberada após correção técnica do XML/data fiscal.'
                    );
                }
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

    private function permiteTentativaExtraManual(array $nfse): bool
    {
        if (empty($nfse['id_financeiro'])) {
            return false;
        }

        $motivoAtual = trim((string) ($nfse['codigo_rejeicao'] ?? '') . ' ' . (string) ($nfse['motivo_rejeicao'] ?? ''));
        if ($this->mensagemPermiteReenvioTecnico($motivoAtual)) {
            return true;
        }

        foreach ($this->eventoModel->listarPorNfse((int) ($nfse['id'] ?? 0)) as $evento) {
            $mensagemEvento = trim((string) ($evento['codigo_retorno'] ?? '') . ' ' . (string) ($evento['mensagem'] ?? ''));
            if ($this->mensagemPermiteReenvioTecnico($mensagemEvento)) {
                return true;
            }
        }

        return false;
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

            return $this->processarRetornoEmissao($idNFSe, $resultado, $preparado['xml_parser'], $chave);
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
            $xmlGenerator = new NFSeXMLBetha();
            $xml = $xmlGenerator->gerarXMLCancelamento($nfse['chave_acesso'], $motivo, []);

            $api = new NFSeAPIBetha();
            $resultado = $api->cancelar($xml, $nfse['chave_acesso'], $pem['certPath'], $pem['keyPath'], (int) $config['ambiente']);
            $retorno = $xmlGenerator->parseRetornoCancelamento($resultado['resposta'] ?? '');

            if (empty($retorno['erros']) && ($retorno['sucesso'] || ($resultado['sucesso'] ?? false))) {
                $this->nfseModel->atualizarCancelada($idNFSe, $motivo);
                $this->eventoModel->registrar($idNFSe, 'cancelamento', null, $motivo, $resultado['resposta'] ?? null);
                return ['sucesso' => true, 'mensagem' => 'NFS-e cancelada com sucesso.'];
            }

            $erroMsg = $retorno['erros'][0]['mensagem'] ?? ($resultado['erro'] ?? 'Erro desconhecido ao cancelar na Betha.');
            $erroCod = $retorno['erros'][0]['codigo'] ?? ($resultado['codigoErro'] ?? 'ERRO_DESCONHECIDO');
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
        $preencherIBSCBS = ($config['preencher_ibscbs'] ?? 'N') === 'S';
        if ($preencherIBSCBS) {
            throw new \InvalidArgumentException('Preenchimento de IBS/CBS ainda não habilitado para este tipo de emissão.');
        }
        $aliquotaIBS = 0.0;
        $aliquotaCBS = 0.0;
        $valorIBS = 0.0;
        $valorCBS = 0.0;
        $dataFiscal = $this->agoraFiscal();
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

        return [
            'ambiente' => (int) ($config['ambiente'] ?? 2),
            'serie' => $config['serie'] ?? 'DPS',
            'data_emissao' => $dataFiscal->format('Y-m-d\TH:i:sP'),
            'data_competencia' => $dataFiscal->format('Y-m-d'),
            'municipio_codigo' => $config['codigo_municipio'] ?? '',
            'tipo_emissao' => $config['tipo_emissao'] ?? 'nacional',
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
                'tipo' => strtoupper(trim((string) ($cliente['tipo'] ?? ''))),
                'pais' => strtoupper(trim((string) ($cliente['pais'] ?? 'BR'))),
                'cpf_cnpj' => trim((string) ($cliente['cpf_cnpj'] ?? '')),
                'nome' => trim((string) ($cliente['nome_rsocial'] ?? '')),
                'email' => $this->valorPreferencial($dadosExtras['tomador_email'] ?? '', $cliente['email'] ?? ''),
                'endereco' => $tomadorEndereco,
            ],
            'servico' => [
                'codigo' => $config['codigo_servico'] ?? '1.1101.11',
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
                'preencher_ibscbs' => 'N',
                'exigibilidade_iss' => (int) ($config['exigibilidade_iss'] ?? 1),
                'aliquota_ibs' => $aliquotaIBS,
                'valor_ibs' => $valorIBS,
                'aliquota_cbs' => $aliquotaCBS,
                'valor_cbs' => $valorCBS,
                'iss_retido' => $dadosExtras['iss_retido'] ?? 'N',
            ],
            'incentivo_fiscal' => $config['incentivo_fiscal'] ?? 'N',
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
