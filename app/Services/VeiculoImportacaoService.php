<?php

namespace App\Services;

use App\Helpers\CurrencyHelper;
use App\Helpers\FilialHelper;
use App\Models\Fornecedor;
use App\Models\Grupo;
use App\Models\ManutencaoPlano;
use App\Models\MatrizFilial;
use App\Models\Veiculo;

/**
 * Le, valida e importa veiculos a partir do modelo CSV oficial.
 */
class VeiculoImportacaoService
{
    public const MAX_FILE_SIZE = 2 * 1024 * 1024;
    public const MAX_ROWS = 1000;

    public const HEADERS = [
        'placa',
        'renavam',
        'chassi',
        'odometro',
        'disponibilidade',
        'marca',
        'modelo',
        'ano',
        'cor',
        'transmissao',
        'motor',
        'peso_max',
        'tipo_combustivel',
        'tanque_litros',
        'tanque_fracao',
        'valor_por_fracao',
        'data_compra',
        'valor_compra',
        'vender',
        'data_venda',
        'valor_venda',
        'descricao',
    ];

    private const REQUIRED_FIELDS = ['placa', 'marca', 'modelo', 'ano'];
    private const INACTIVE_AVAILABILITY = ['V', 'E', 'RO'];
    private const MAX_LENGTHS = [
        'placa' => 10,
        'renavam' => 25,
        'chassi' => 45,
        'odometro' => 10,
        'disponibilidade' => 2,
        'marca' => 45,
        'modelo' => 45,
        'ano' => 9,
        'cor' => 45,
        'transmissao' => 45,
        'motor' => 5,
        'peso_max' => 7,
        'tipo_combustivel' => 5,
        'tanque_litros' => 5,
        'tanque_fracao' => 5,
        'descricao' => 255,
    ];

    public function __construct(
        private readonly Veiculo $veiculoModel = new Veiculo(),
        private readonly MatrizFilial $filialModel = new MatrizFilial(),
        private readonly Fornecedor $fornecedorModel = new Fornecedor(),
        private readonly Grupo $grupoModel = new Grupo(),
        private readonly ManutencaoPlano $planoModel = new ManutencaoPlano()
    ) {
    }

    /**
     * @return array{filiais:array,fornecedores:array,grupos:array,planos:array}
     */
    public function listarOpcoesDisponiveis(): array
    {
        [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id');

        return [
            'filiais' => $this->filialModel->listarParaImportacao($filialWhere, $filialParams),
            'fornecedores' => $this->fornecedorModel->listarParaImportacaoVeiculos(),
            'grupos' => $this->grupoModel->listarParaImportacaoVeiculos(),
            'planos' => $this->planoModel->listarParaImportacaoVeiculos(),
        ];
    }

    /**
     * @param array{id_matriz_filial:int,id_fornecedor:int,id_grupo:int,id_matriz_filial_localizacao:?int,id_plano_manutencao:int} $opcoes
     * @return array{success:bool,importados?:int,ignorados?:int,ignorados_detalhes?:array,ativos_importados?:int,errors?:array}
     */
    public function importar(string $arquivo, array $opcoes, int $vagasDisponiveis): array
    {
        $relacionamentos = $this->validarRelacionamentos($opcoes);
        if (!$relacionamentos['success']) {
            return $relacionamentos;
        }

        $leitura = $this->lerCsv($arquivo);
        if (!$leitura['success']) {
            return $leitura;
        }

        $validacao = $this->validarLinhas($leitura['rows'], $opcoes, $relacionamentos['plano']);
        if (!$validacao['success']) {
            return $validacao;
        }

        $registros = $validacao['data'];
        $ativos = count(array_filter(
            $registros,
            static fn(array $registro): bool => !in_array($registro['disponibilidade'], self::INACTIVE_AVAILABILITY, true)
        ));

        if ($ativos > max(0, $vagasDisponiveis)) {
            return $this->erroArquivo(
                "O lote possui {$ativos} veículo(s) ativo(s), mas o plano permite importar apenas " . max(0, $vagasDisponiveis) . '.',
                'limite_plano'
            );
        }

        $importados = $registros === [] ? 0 : $this->veiculoModel->importarLote($registros);
        $ignorados = $validacao['ignored'] ?? [];

        return [
            'success' => true,
            'importados' => $importados,
            'ignorados' => count($ignorados),
            'ignorados_detalhes' => $ignorados,
            'ativos_importados' => $ativos,
        ];
    }

    public function gerarModelo(): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Nao foi possivel gerar o modelo de importacao.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::HEADERS, ';', '"', '');
        fputcsv($stream, [
            'EXEMPLO', '12345678901', '9BWZZZ377VT004251', '25000', 'D',
            'Marca Exemplo', 'Modelo Exemplo', '2025/2026', 'Prata', 'A',
            '1.6', '1800', 'GE', '50', '8', '15,00', '2026-01-15',
            '85000,00', 'N', '', '',
            'EXEMPLO IGNORADO: substitua a placa e mantenha os cabecalhos. Obrigatorios: placa, marca, modelo e ano.',
        ], ';', '"', '');

        rewind($stream);
        $conteudo = stream_get_contents($stream);
        fclose($stream);

        if ($conteudo === false) {
            throw new \RuntimeException('Nao foi possivel gerar o modelo de importacao.');
        }

        return $conteudo;
    }

    /**
     * @return array{success:bool,rows?:array<int,array{line:int,data:array<string,string>}>,errors?:array}
     */
    public function lerCsv(string $arquivo): array
    {
        $handle = fopen($arquivo, 'rb');
        if ($handle === false) {
            return $this->erroArquivo('Nao foi possivel ler o arquivo CSV.');
        }

        $primeiraLinha = fgets($handle);
        if ($primeiraLinha === false) {
            fclose($handle);
            return $this->erroArquivo('O arquivo CSV esta vazio.');
        }

        $primeiraLinha = preg_replace('/^\xEF\xBB\xBF/', '', $primeiraLinha) ?? $primeiraLinha;
        $delimitador = substr_count($primeiraLinha, ';') >= substr_count($primeiraLinha, ',') ? ';' : ',';
        $headers = array_map(
            static fn($header): string => trim((string) $header),
            str_getcsv(rtrim($primeiraLinha, "\r\n"), $delimitador, '"', '')
        );

        if ($headers !== self::HEADERS) {
            fclose($handle);
            return $this->erroArquivo('O cabecalho do CSV nao corresponde ao modelo oficial.');
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, $delimitador, '"', '')) !== false) {
            $line++;
            if ($values === [null] || $values === []) {
                continue;
            }

            $values = array_map(static fn($value): string => trim((string) $value), $values);
            if (!array_filter($values, static fn(string $value): bool => $value !== '')) {
                continue;
            }
            if (count($values) !== count(self::HEADERS)) {
                fclose($handle);
                return ['success' => false, 'errors' => [[
                    'line' => $line,
                    'field' => 'arquivo',
                    'message' => 'A quantidade de colunas nao corresponde ao modelo oficial.',
                ]]];
            }

            $data = array_combine(self::HEADERS, $values);
            if (strtoupper($data['placa']) === 'EXEMPLO') {
                continue;
            }

            $rows[] = ['line' => $line, 'data' => $data];
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);
                return $this->erroArquivo('O arquivo excede o limite de ' . self::MAX_ROWS . ' veiculos.');
            }
        }
        fclose($handle);

        if ($rows === []) {
            return $this->erroArquivo('O arquivo nao possui veiculos para importar.');
        }

        return ['success' => true, 'rows' => $rows];
    }

    /**
     * @param array<int,array{line:int,data:array<string,string>}> $rows
     * @param array $opcoes
     * @param array $plano
     */
    public function validarLinhas(array $rows, array $opcoes, array $plano): array
    {
        $errors = [];
        $normalizados = [];
        $placasArquivo = [];
        $indicesPorPlaca = [];
        $ignorados = [];

        foreach ($rows as $row) {
            $line = $row['line'];
            $data = $row['data'];
            $rowErrors = [];

            foreach (self::REQUIRED_FIELDS as $field) {
                if (($data[$field] ?? '') === '') {
                    $rowErrors[] = $this->erroLinha($line, $field, 'Campo obrigatorio.');
                }
            }

            foreach (self::MAX_LENGTHS as $field => $maxLength) {
                if (mb_strlen($data[$field] ?? '') > $maxLength) {
                    $rowErrors[] = $this->erroLinha($line, $field, "Use no maximo {$maxLength} caracteres.");
                }
            }

            $placa = strtoupper(trim($data['placa'] ?? ''));
            $placaCanonica = $this->normalizarPlaca($placa);
            if ($placa !== '' && ($placaCanonica === '' || !preg_match('/^[A-Z0-9 -]+$/', $placa))) {
                $rowErrors[] = $this->erroLinha($line, 'placa', 'Use somente letras, numeros, espacos ou hifen.');
            }

            if (($data['ano'] ?? '') !== '' && !preg_match('/^\d{4}(?:\/\d{4})?$/', $data['ano'])) {
                $rowErrors[] = $this->erroLinha($line, 'ano', 'Use AAAA ou AAAA/AAAA.');
            }

            $this->validarOpcoes($data, $line, $rowErrors);
            $odometro = $this->normalizarInteiro($data['odometro'] ?? '', 'odometro', $line, $rowErrors);
            $this->normalizarNumeroOpcional($data['tanque_litros'] ?? '', 'tanque_litros', $line, $rowErrors);
            $this->normalizarNumeroOpcional($data['peso_max'] ?? '', 'peso_max', $line, $rowErrors);

            $dataCompra = $this->normalizarData($data['data_compra'] ?? '', 'data_compra', $line, $rowErrors);
            $dataVenda = $this->normalizarData($data['data_venda'] ?? '', 'data_venda', $line, $rowErrors);
            $valorFracao = $this->normalizarMoeda($data['valor_por_fracao'] ?? '', 'valor_por_fracao', 99999999.99, $line, $rowErrors);
            $valorCompra = $this->normalizarMoeda($data['valor_compra'] ?? '', 'valor_compra', 9999999999.99, $line, $rowErrors);
            $valorVenda = $this->normalizarMoeda($data['valor_venda'] ?? '', 'valor_venda', 9999999999.99, $line, $rowErrors);

            if ($placaCanonica !== '' && isset($placasArquivo[$placaCanonica])) {
                $ignorados[] = ['line' => $line, 'reason' => 'placa_duplicada_arquivo'];
                $errors = array_merge($errors, $rowErrors);
                continue;
            }

            if ($placaCanonica !== '') {
                $placasArquivo[$placaCanonica] = $line;
            }
            $errors = array_merge($errors, $rowErrors);
            if ($rowErrors !== []) {
                continue;
            }

            $registro = [
                'chave' => $_SESSION['chave'] ?? '',
                'id_matriz_filial' => $opcoes['id_matriz_filial'],
                'id_fornecedor' => $opcoes['id_fornecedor'],
                'id_grupo' => $opcoes['id_grupo'],
                'id_matriz_filial_localizacao' => $opcoes['id_matriz_filial_localizacao'],
                'id_plano_manutencao' => $opcoes['id_plano_manutencao'],
                'placa' => $placa,
                'renavam' => $this->nullable($data['renavam']),
                'chassi' => $this->nullable(strtoupper($data['chassi'])),
                'odometro' => (string) $odometro,
                'disponibilidade' => strtoupper($data['disponibilidade'] ?: 'D'),
                'marca' => $data['marca'],
                'modelo' => $data['modelo'],
                'ano' => $data['ano'],
                'cor' => $this->nullable($data['cor']),
                'transmissao' => $this->nullable(strtoupper($data['transmissao'])),
                'motor' => $this->nullable($data['motor']),
                'peso_max' => $this->nullable($data['peso_max']),
                'tipo_combustivel' => $this->nullable(strtoupper($data['tipo_combustivel'])),
                'tanque_litros' => $this->nullable($data['tanque_litros']),
                'tanque_fracao' => $this->nullable($data['tanque_fracao']),
                'valor_por_fracao' => $valorFracao,
                'data_compra' => $dataCompra,
                'valor_compra' => $valorCompra,
                'vender' => strtoupper($data['vender'] ?: 'N'),
                'data_venda' => $dataVenda,
                'valor_venda' => $valorVenda,
                'descricao' => $this->nullable($data['descricao']),
                'plano_manutencao_array' => $this->calcularPlanoVeiculo($plano['array'] ?? null, $odometro),
            ];

            $indicesPorPlaca[$placaCanonica] = count($normalizados);
            $normalizados[] = $registro;
        }

        if ($placasArquivo !== []) {
            $existentes = $this->veiculoModel->buscarPlacasExistentesParaImportacao(array_keys($placasArquivo));
            foreach ($existentes as $placaExistente) {
                $canonica = $this->normalizarPlaca($placaExistente);
                if (isset($placasArquivo[$canonica], $indicesPorPlaca[$canonica])) {
                    $ignorados[] = ['line' => $placasArquivo[$canonica], 'reason' => 'placa_existente'];
                    unset($normalizados[$indicesPorPlaca[$canonica]]);
                }
            }
        }

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors, 'ignored' => $ignorados];
        }

        usort($ignorados, static fn(array $a, array $b): int => $a['line'] <=> $b['line']);
        return ['success' => true, 'data' => array_values($normalizados), 'ignored' => $ignorados];
    }

    private function validarRelacionamentos(array $opcoes): array
    {
        $disponiveis = $this->listarOpcoesDisponiveis();
        $validacoes = [
            'id_matriz_filial' => [$disponiveis['filiais'], 'Selecione uma matriz/filial valida.'],
            'id_fornecedor' => [$disponiveis['fornecedores'], 'Selecione um fornecedor valido.'],
            'id_grupo' => [$disponiveis['grupos'], 'Selecione um grupo valido.'],
            'id_plano_manutencao' => [$disponiveis['planos'], 'Selecione um plano de manutencao ativo.'],
        ];

        foreach ($validacoes as $field => [$lista, $mensagem]) {
            $id = (int) ($opcoes[$field] ?? 0);
            if ($id <= 0 || !in_array($id, array_column($lista, 'id'), true)) {
                return $this->erroArquivo($mensagem, $field);
            }
        }

        $localizacao = $opcoes['id_matriz_filial_localizacao'] ?? null;
        if ($localizacao !== null && !in_array((int) $localizacao, array_column($disponiveis['filiais'], 'id'), true)) {
            return $this->erroArquivo('Selecione uma localizacao atual valida.', 'id_matriz_filial_localizacao');
        }

        $plano = $this->planoModel->buscarPorId((int) $opcoes['id_plano_manutencao']);
        if (!$plano || ($plano['status'] ?? '') !== 'A') {
            return $this->erroArquivo('Selecione um plano de manutencao ativo.', 'id_plano_manutencao');
        }
        if (!is_array(json_decode((string) ($plano['array'] ?? ''), true))) {
            return $this->erroArquivo(
                'O plano de manutencao selecionado possui intervalos invalidos.',
                'id_plano_manutencao'
            );
        }

        return ['success' => true, 'plano' => $plano];
    }

    private function validarOpcoes(array $data, int $line, array &$errors): void
    {
        $opcoes = [
            'disponibilidade' => ['D', 'L', 'R', 'O', 'V', 'AV', 'UI', 'RO', 'E'],
            'transmissao' => ['A', 'M'],
            'tipo_combustivel' => ['GE', 'G', 'E', 'D', 'GAS', 'HE', 'HI'],
            'tanque_fracao' => ['0', '1', '2', '3', '4', '5', '6', '7', '8'],
            'vender' => ['S', 'N'],
        ];

        foreach ($opcoes as $field => $permitidos) {
            $valor = strtoupper($data[$field] ?? '');
            if ($valor !== '' && !in_array($valor, $permitidos, true)) {
                $errors[] = $this->erroLinha($line, $field, 'Valor fora do padrao permitido.');
            }
        }
    }

    private function normalizarInteiro(string $value, string $field, int $line, array &$errors): int
    {
        if ($value === '') {
            return 0;
        }
        if (!preg_match('/^\d+(?:[. ]\d{3})*$/', $value)) {
            $errors[] = $this->erroLinha($line, $field, 'Informe um numero inteiro nao negativo.');
            return 0;
        }
        return (int) preg_replace('/\D/', '', $value);
    }

    private function normalizarNumeroOpcional(string $value, string $field, int $line, array &$errors): void
    {
        if ($value !== '' && !preg_match('/^\d+(?:[.,]\d+)?$/', $value)) {
            $errors[] = $this->erroLinha($line, $field, 'Informe um numero nao negativo.');
        }
    }

    private function normalizarMoeda(string $value, string $field, float $max, int $line, array &$errors): float
    {
        if ($value === '') {
            return 0.0;
        }
        if (!preg_match('/^[\d., ]+$/', $value)) {
            $errors[] = $this->erroLinha($line, $field, 'Informe um valor monetario valido.');
            return 0.0;
        }
        $normalizado = round(CurrencyHelper::parse($value), 2);
        if ($normalizado < 0 || $normalizado > $max) {
            $errors[] = $this->erroLinha($line, $field, 'Valor fora do limite permitido.');
        }
        return $normalizado;
    }

    private function normalizarData(string $value, string $field, int $line, array &$errors): ?string
    {
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            $errors[] = $this->erroLinha($line, $field, 'Use uma data valida no formato AAAA-MM-DD.');
            return null;
        }
        return $value;
    }

    private function calcularPlanoVeiculo(mixed $arrayJson, int $odometro): string
    {
        $intervalos = is_array($arrayJson) ? $arrayJson : json_decode((string) $arrayJson, true);
        if (!is_array($intervalos)) {
            throw new \RuntimeException('O plano de manutencao selecionado possui intervalos invalidos.');
        }

        $proximas = [];
        foreach ($intervalos as $item => $valor) {
            $intervalo = (int) preg_replace('/\D/', '', (string) $valor);
            $proxima = $intervalo > 0 ? $odometro + $intervalo : 0;
            $proximas[$item] = $proxima > 0 ? number_format($proxima, 0, '', '.') : '0';
        }

        return json_encode($proximas, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function normalizarPlaca(string $placa): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($placa)) ?? '';
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function erroLinha(int $line, string $field, string $message): array
    {
        return compact('line', 'field', 'message');
    }

    private function erroArquivo(string $message, string $field = 'arquivo'): array
    {
        return ['success' => false, 'errors' => [[
            'line' => null,
            'field' => $field,
            'message' => $message,
        ]]];
    }
}
