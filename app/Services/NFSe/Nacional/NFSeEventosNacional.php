<?php

namespace App\Services\NFSe\Nacional;

/**
 * Interpreta a resposta da API de eventos do ADN e determina a situacao fiscal.
 */
class NFSeEventosNacional
{
    private const EVENTO_CANCELAMENTO = '101101';
    private const EVENTO_CANCELAMENTO_SUBSTITUICAO = '105102';

    public function parseSituacao(string $resposta, string $chaveEsperada): array
    {
        $chaveEsperada = preg_replace('/\D+/', '', $chaveEsperada) ?? '';
        if (strlen($chaveEsperada) !== 50) {
            return $this->erro('Chave de acesso inválida para consulta de eventos.');
        }

        $resposta = trim($resposta);
        if ($resposta === '') {
            return $this->erro('A consulta de eventos retornou uma resposta vazia.');
        }

        $documentos = [];
        if (str_starts_with($resposta, '<')) {
            $documentos[] = $resposta;
        } else {
            try {
                $json = json_decode($resposta, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                return $this->erro('Resposta de eventos inválida: ' . $e->getMessage());
            }

            $this->coletarDocumentos($json, $documentos);
            $eventoMetadados = $this->situacaoPorMetadados($json, $chaveEsperada);
            if ($eventoMetadados !== null) {
                $documentos[] = $eventoMetadados;
            }
        }

        $eventos = [];
        foreach (array_unique($documentos) as $documento) {
            if (str_starts_with($documento, '__META__:')) {
                $meta = json_decode(substr($documento, 9), true);
                if (is_array($meta)) {
                    $eventos[] = $meta;
                }
                continue;
            }

            $evento = $this->parseXml($documento, $chaveEsperada);
            if (isset($evento['erro'])) {
                return $this->erro($evento['erro']);
            }
            if ($evento !== []) {
                $eventos[] = $evento;
            }
        }

        $selecionado = null;
        foreach ($eventos as $evento) {
            if (($evento['tipo_evento'] ?? '') === self::EVENTO_CANCELAMENTO_SUBSTITUICAO) {
                $selecionado = $evento;
                break;
            }
            if (($evento['tipo_evento'] ?? '') === self::EVENTO_CANCELAMENTO) {
                $selecionado = $evento;
            }
        }

        if ($selecionado === null) {
            return ['sucesso' => true, 'situacao' => 'N', 'evento' => null];
        }

        return [
            'sucesso' => true,
            'situacao' => $selecionado['tipo_evento'] === self::EVENTO_CANCELAMENTO_SUBSTITUICAO ? 'S' : 'C',
            'evento' => $selecionado,
        ];
    }

    private function coletarDocumentos(mixed $valor, array &$documentos, string $chave = ''): void
    {
        if (is_array($valor)) {
            foreach ($valor as $itemChave => $item) {
                $this->coletarDocumentos($item, $documentos, (string) $itemChave);
            }
            return;
        }

        if (!is_string($valor) || $valor === '') {
            return;
        }

        if (str_starts_with(ltrim($valor), '<')) {
            $documentos[] = $valor;
            return;
        }

        $chaveNormalizada = strtolower($chave);
        if (!str_contains($chaveNormalizada, 'xml') && !str_contains($chaveNormalizada, 'documento')) {
            return;
        }

        $binario = base64_decode($valor, true);
        if ($binario === false) {
            return;
        }
        $xml = @gzdecode($binario);
        if ($xml === false && str_starts_with(ltrim($binario), '<')) {
            $xml = $binario;
        }
        if (is_string($xml) && str_starts_with(ltrim($xml), '<')) {
            $documentos[] = $xml;
        }
    }

    private function situacaoPorMetadados(mixed $json, string $chaveEsperada): ?string
    {
        if (!is_array($json)) {
            return null;
        }

        foreach ($json as $item) {
            if (is_array($item)) {
                $resultado = $this->situacaoPorMetadados($item, $chaveEsperada);
                if ($resultado !== null) {
                    return $resultado;
                }
            }
        }

        $tipo = preg_replace('/\D+/', '', (string) ($json['tipoEvento'] ?? $json['tpEvento'] ?? '')) ?? '';
        if (!in_array($tipo, [self::EVENTO_CANCELAMENTO, self::EVENTO_CANCELAMENTO_SUBSTITUICAO], true)) {
            return null;
        }
        $chave = preg_replace('/\D+/', '', (string) ($json['chaveAcesso'] ?? $json['chNFSe'] ?? '')) ?? '';
        if ($chave !== $chaveEsperada) {
            return null;
        }

        return '__META__:' . json_encode([
            'tipo_evento' => $tipo,
            'data_evento' => $json['dataHoraEvento'] ?? $json['dhEvento'] ?? null,
            'motivo' => $json['motivo'] ?? $json['xMotivo'] ?? null,
            'chave_substituta' => $json['chaveSubstituta'] ?? $json['chSubstituta'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function parseXml(string $xml, string $chaveEsperada): array
    {
        $dom = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $carregado = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        if (!$carregado) {
            return ['erro' => 'XML de evento inválido retornado pelo ADN.'];
        }

        $xpath = new \DOMXPath($dom);
        $chave = preg_replace('/\D+/', '', $this->primeiroTexto($xpath, ['chNFSe', 'chaveAcesso'])) ?? '';
        $tipo = preg_replace('/\D+/', '', $this->primeiroTexto($xpath, ['tpEvento'])) ?? '';
        if ($tipo === '') {
            foreach ([self::EVENTO_CANCELAMENTO_SUBSTITUICAO, self::EVENTO_CANCELAMENTO] as $codigo) {
                if ($xpath->query("//*[local-name()='e{$codigo}']")->length > 0) {
                    $tipo = $codigo;
                    break;
                }
            }
        }
        if (!in_array($tipo, [self::EVENTO_CANCELAMENTO, self::EVENTO_CANCELAMENTO_SUBSTITUICAO], true)) {
            return [];
        }
        if ($chave === '') {
            return ['erro' => 'O evento fiscal retornado não informa a chave da NFS-e.'];
        }
        if ($chave !== $chaveEsperada) {
            return ['erro' => 'O evento retornado pertence a outra NFS-e.'];
        }

        return [
            'tipo_evento' => $tipo,
            'data_evento' => $this->primeiroTexto($xpath, ['dhEvento', 'dhProc']) ?: null,
            'motivo' => $this->primeiroTexto($xpath, ['xMotivo']) ?: null,
            'chave_substituta' => $this->primeiroTexto($xpath, ['chSubstituta']) ?: null,
        ];
    }

    private function primeiroTexto(\DOMXPath $xpath, array $nomes): string
    {
        foreach ($nomes as $nome) {
            $nodes = $xpath->query("//*[local-name()='{$nome}']");
            if ($nodes !== false && $nodes->length > 0) {
                return trim((string) $nodes->item(0)?->textContent);
            }
        }
        return '';
    }

    private function erro(string $mensagem): array
    {
        return ['sucesso' => false, 'situacao' => null, 'evento' => null, 'mensagem' => $mensagem];
    }
}
