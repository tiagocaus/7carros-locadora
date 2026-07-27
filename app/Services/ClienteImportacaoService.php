<?php

namespace App\Services;

use App\Helpers\DateHelper;
use App\Helpers\FilialHelper;
use App\Models\Cliente;
use App\Models\MatrizFilial;
use App\Models\Pais;

/**
 * Le, valida e importa clientes a partir do modelo CSV oficial.
 */
class ClienteImportacaoService
{
    public const MAX_FILE_SIZE = 2 * 1024 * 1024;
    public const MAX_ROWS = 1000;

    public const HEADERS = [
        'tipo',
        'cpf_cnpj',
        'nome_rsocial',
        'situacao',
        'nome_fantasia',
        'rg_ie',
        'nascimento',
        'sexo',
        'estado_civil',
        'profissao',
        'preferred_locale',
        'cep',
        'rua',
        'numero',
        'complemento',
        'pais',
        'estado',
        'cidade',
        'bairro',
        'email',
        'email_descricao',
        'email_recebe',
        'telefone',
        'telefone_descricao',
        'telefone_whatsapp',
        'telefone_telegram',
        'telefone_sms',
        'cnh_numero',
        'cnh_codigo_seguranca',
        'cnh_categoria',
        'cnh_validade',
        'obs',
    ];

    private const REQUIRED_FIELDS = ['tipo', 'cpf_cnpj', 'nome_rsocial'];

    private const EXAMPLE_DOCUMENTS = [
        'PF' => '99999999999',
        'PJ' => '99999999999999',
        'ES' => 'X9999999',
    ];

    private const MAX_LENGTHS = [
        'cpf_cnpj' => 18,
        'nome_rsocial' => 255,
        'nome_fantasia' => 255,
        'rg_ie' => 20,
        'profissao' => 100,
        'preferred_locale' => 10,
        'cep' => 15,
        'rua' => 255,
        'numero' => 5,
        'complemento' => 100,
        'pais' => 45,
        'estado' => 45,
        'cidade' => 45,
        'bairro' => 30,
        'email' => 255,
        'email_descricao' => 100,
        'telefone' => 30,
        'telefone_descricao' => 100,
        'cnh_numero' => 45,
        'cnh_codigo_seguranca' => 30,
        'cnh_categoria' => 2,
    ];

    public function __construct(
        private readonly Cliente $clienteModel = new Cliente(),
        private readonly MatrizFilial $filialModel = new MatrizFilial(),
        private readonly Pais $paisModel = new Pais()
    ) {
    }

    /**
     * @return array{
     *     success:bool,
     *     importados?:int,
     *     ignorados?:int,
     *     ignorados_detalhes?:array<int,array{line:int,reason:string}>,
     *     errors?:array<int,array{line:int|null,field:string,message:string}>
     * }
     */
    public function importar(string $arquivo, int $filialId): array
    {
        $filiais = $this->listarFiliaisDisponiveis();
        $filialPermitida = array_filter(
            $filiais,
            static fn(array $filial): bool => (int) $filial['id'] === $filialId
        );
        if ($filialPermitida === []) {
            return $this->erroArquivo(
                'A matriz/filial selecionada nao existe, esta inativa ou nao esta disponivel para o usuario.',
                'id_matriz_filial'
            );
        }

        $leitura = $this->lerCsv($arquivo);
        if (!$leitura['success']) {
            return $leitura;
        }

        $paises = array_column($this->paisModel->listarAtivos(), 'codigo');

        $validacao = $this->validarLinhas($leitura['rows'], $paises, $filialId);
        if (!$validacao['success']) {
            return $validacao;
        }

        $registros = $validacao['data'];
        $importados = $registros === []
            ? 0
            : $this->clienteModel->importarLote($registros);
        $ignoradosDetalhes = $validacao['ignored'] ?? [];

        return [
            'success' => true,
            'importados' => $importados,
            'ignorados' => count($ignoradosDetalhes),
            'ignorados_detalhes' => $ignoradosDetalhes,
        ];
    }

    /**
     * @return array<int,array{id:int,nome:string,nome_fantasia:string}>
     */
    public function listarFiliaisDisponiveis(): array
    {
        [$filialWhere, $filialParams] = FilialHelper::whereFiliais('id');
        return $this->filialModel->listarParaImportacao($filialWhere, $filialParams);
    }

    /**
     * Gera o modelo com instrucoes comentadas, que sao ignoradas na importacao.
     */
    public function gerarModelo(): string
    {
        $formatoData = DateHelper::getConfig()['date_format'] ?? 'd/m/Y';
        $nascimentoExemplo = (new \DateTimeImmutable('1990-01-15'))->format($formatoData);
        $validadeCnhExemplo = (new \DateTimeImmutable('2030-12-31'))->format($formatoData);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Nao foi possivel gerar o modelo de importacao.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::HEADERS, ';', '"', '');
        $exemplos = [
            [
                'PF',
                '999.999.999-99',
                'Maria da Silva',
                'A',
                '',
                '1234567',
                $nascimentoExemplo,
                'M',
                'solteiro',
                'Analista',
                'pt_BR',
                '00000-000',
                'Rua Exemplo',
                '100',
                '',
                'BR',
                'SP',
                'Cidade',
                'Bairro',
                'cliente@exemplo.com',
                'Principal',
                'S',
                '+5511999999999',
                'Celular',
                'S',
                'N',
                'S',
                '01234567890',
                '123456789',
                'AB',
                $validadeCnhExemplo,
                'EXEMPLO IGNORADO: substitua o CPF ficticio para usar. Obrigatorios: tipo, cpf_cnpj e nome_rsocial.',
            ],
            [
                'PJ',
                '99.999.999/9999-99',
                'Empresa Exemplo Ltda.',
                'A',
                'Empresa Exemplo',
                '123456789',
                '',
                '',
                '',
                '',
                'pt_BR',
                '00000-000',
                'Avenida Exemplo',
                '200',
                'Sala 10',
                'BR',
                'SP',
                'Cidade',
                'Centro',
                'financeiro@empresaexemplo.com',
                'Financeiro',
                'S',
                '+551133333333',
                'Comercial',
                'N',
                'N',
                'S',
                '',
                '',
                '',
                '',
                'EXEMPLO IGNORADO: substitua o CNPJ ficticio para usar. Campos pessoais e CNH ficam vazios.',
            ],
            [
                'ES',
                'X9999999',
                'John Smith',
                'A',
                '',
                '',
                $nascimentoExemplo,
                'M',
                'solteiro',
                'Consultor',
                'en_US',
                '10001',
                'Example Street',
                '300',
                '',
                'US',
                'NY',
                'New York',
                'Manhattan',
                'john.smith@example.com',
                'Principal',
                'S',
                '+12125550123',
                'Mobile',
                'S',
                'N',
                'S',
                '',
                '',
                '',
                '',
                'EXEMPLO IGNORADO: substitua o documento ficticio para usar este cliente estrangeiro.',
            ],
        ];

        foreach ($exemplos as $exemplo) {
            fputcsv($stream, $exemplo, ';', '"', '');
        }

        rewind($stream);
        $conteudo = stream_get_contents($stream);
        fclose($stream);

        if ($conteudo === false) {
            throw new \RuntimeException('Nao foi possivel gerar o modelo de importacao.');
        }

        return $conteudo;
    }

    /**
     * @return array{success:bool, rows?:array<int,array{line:int,data:array<string,string>}>, errors?:array}
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
        $headers = str_getcsv(rtrim($primeiraLinha, "\r\n"), $delimitador, '"', '');
        $headers = array_map(static fn($header) => trim((string) $header), $headers);

        if ($headers !== self::HEADERS) {
            fclose($handle);
            $modeloAnterior = ($headers[0] ?? '') === 'filial'
                && array_slice($headers, 1) === self::HEADERS;
            return $this->erroArquivo(
                $modeloAnterior
                    ? 'Este CSV usa o modelo anterior com a coluna filial. Baixe o modelo atualizado.'
                    : 'O cabecalho do CSV nao corresponde ao modelo oficial.'
            );
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, $delimitador, '"', '')) !== false) {
            $line++;

            if ($values === [null] || $values === []) {
                continue;
            }

            $values = array_map(static fn($value) => trim((string) $value), $values);
            if (!array_filter($values, static fn($value) => $value !== '')) {
                continue;
            }
            if (count($values) !== count(self::HEADERS)) {
                fclose($handle);
                return [
                    'success' => false,
                    'errors' => [[
                        'line' => $line,
                        'field' => 'arquivo',
                        'message' => 'A quantidade de colunas nao corresponde ao modelo oficial.',
                    ]],
                ];
            }

            $data = array_combine(self::HEADERS, $values);
            if ($this->isExampleRow($data)) {
                continue;
            }

            $rows[] = ['line' => $line, 'data' => $data];
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);
                return $this->erroArquivo('O arquivo excede o limite de ' . self::MAX_ROWS . ' clientes.');
            }
        }
        fclose($handle);

        if ($rows === []) {
            return $this->erroArquivo('O arquivo nao possui clientes para importar.');
        }

        return ['success' => true, 'rows' => $rows];
    }

    /**
     * @param array<int,array{line:int,data:array<string,string>}> $rows
     * @param array<int,string> $paises
     * @return array{
     *     success:bool,
     *     data?:array<int,array>,
     *     ignored?:array<int,array{line:int,reason:string}>,
     *     errors?:array
     * }
     */
    public function validarLinhas(array $rows, array $paises, int $filialId): array
    {
        $errors = [];
        $normalizados = [];
        $documentosArquivo = [];
        $indicesPorDocumento = [];
        $ignorados = [];

        $paisesValidos = array_fill_keys(array_map('strtoupper', $paises), true);

        foreach ($rows as $row) {
            $line = $row['line'];
            $data = $row['data'];
            $rowErrors = [];

            foreach (self::REQUIRED_FIELDS as $field) {
                if (($data[$field] ?? '') === '') {
                    $rowErrors[] = $this->erroLinha($line, $field, 'Campo obrigatorio.');
                }
            }

            $tipo = strtoupper($data['tipo'] ?? '');
            if ($tipo !== '' && !in_array($tipo, ['PF', 'PJ', 'ES'], true)) {
                $rowErrors[] = $this->erroLinha($line, 'tipo', 'Use PF, PJ ou ES.');
            }

            $documento = $this->normalizarDocumento($data['cpf_cnpj'] ?? '', $tipo);
            if ($documento !== '') {
                if ($tipo === 'PF' && !$this->validarCpf($documento)) {
                    $rowErrors[] = $this->erroLinha($line, 'cpf_cnpj', 'CPF invalido.');
                } elseif ($tipo === 'PJ' && !$this->validarCnpj($documento)) {
                    $rowErrors[] = $this->erroLinha($line, 'cpf_cnpj', 'CNPJ invalido.');
                }

            }

            $this->validarOpcoes($data, $line, $rowErrors);
            $this->validarComprimentos($data, $line, $rowErrors);
            $this->validarContatos($data, $line, $rowErrors);

            $pais = strtoupper($data['pais'] ?: 'BR');
            if (!isset($paisesValidos[$pais])) {
                $rowErrors[] = $this->erroLinha($line, 'pais', 'Use um codigo de pais valido, como BR.');
            }

            $nascimento = $this->normalizarData($data['nascimento'] ?? '', $line, 'nascimento', $rowErrors);
            $cnhValidade = $this->normalizarData($data['cnh_validade'] ?? '', $line, 'cnh_validade', $rowErrors);

            if ($tipo === 'PJ') {
                foreach (['nascimento', 'sexo', 'estado_civil', 'profissao', 'cnh_numero', 'cnh_codigo_seguranca', 'cnh_categoria', 'cnh_validade'] as $field) {
                    if (($data[$field] ?? '') !== '') {
                        $rowErrors[] = $this->erroLinha($line, $field, 'Campo nao aplicavel a pessoa juridica.');
                    }
                }
            }

            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);
                continue;
            }

            if (isset($documentosArquivo[$documento])) {
                $ignorados[] = [
                    'line' => $line,
                    'reason' => 'documento_repetido_arquivo',
                ];
                continue;
            }

            $documentosArquivo[$documento] = $line;

            $cliente = [
                'id_matriz_filial' => $filialId,
                'tipo' => $tipo,
                'cpf_cnpj' => $documento,
                'nome_rsocial' => $data['nome_rsocial'],
                'situacao' => strtoupper($data['situacao'] ?: 'A'),
                'nome_fantasia' => $data['nome_fantasia'],
                'rg_ie' => $data['rg_ie'],
                'nascimento' => $nascimento,
                'sexo' => strtoupper($data['sexo']),
                'estado_civil' => strtolower($data['estado_civil']),
                'profissao' => $data['profissao'],
                'preferred_locale' => $data['preferred_locale'] ?: null,
                'cep' => $data['cep'],
                'rua' => $data['rua'],
                'numero' => $data['numero'],
                'complemento' => $data['complemento'],
                'pais' => $pais,
                'estado' => $data['estado'],
                'cidade' => $data['cidade'],
                'bairro' => $data['bairro'],
                'cnh_numero' => $data['cnh_numero'],
                'cnh_codigo_seguranca' => $data['cnh_codigo_seguranca'],
                'cnh_categoria' => strtoupper($data['cnh_categoria']),
                'cnh_validade' => $cnhValidade,
                'obs' => $data['obs'],
                '_email' => $data['email'] === '' ? null : [
                    'email' => $data['email'],
                    'descricao' => $data['email_descricao'] ?: null,
                    'principal' => 'S',
                    'recebe_email' => strtoupper($data['email_recebe'] ?: 'S'),
                ],
                '_telefone' => $data['telefone'] === '' ? null : [
                    'telefone' => $data['telefone'],
                    'descricao' => $data['telefone_descricao'] ?: null,
                    'principal' => 'S',
                    'whatsapp' => strtoupper($data['telefone_whatsapp'] ?: 'N'),
                    'telegram' => strtoupper($data['telefone_telegram'] ?: 'N'),
                    'sms' => strtoupper($data['telefone_sms'] ?: 'N'),
                ],
            ];

            $clienteNormalizado = array_filter(
                $cliente,
                static fn($value, $key) => str_starts_with($key, '_') || ($value !== '' && $value !== null),
                ARRAY_FILTER_USE_BOTH
            );
            // A coluna clientes.foto e NOT NULL e nao possui valor padrao.
            $clienteNormalizado['foto'] = '';
            $indicesPorDocumento[$documento] = count($normalizados);
            $normalizados[] = $clienteNormalizado;
        }

        if ($documentosArquivo !== []) {
            $existentes = $this->clienteModel->buscarDocumentosExistentesParaImportacao(array_keys($documentosArquivo));
            $existentesProcessados = [];
            foreach ($existentes as $documentoExistente) {
                $normalizado = $this->normalizarDocumento((string) $documentoExistente, '');
                if (
                    isset($documentosArquivo[$normalizado], $indicesPorDocumento[$normalizado])
                    && !isset($existentesProcessados[$normalizado])
                ) {
                    $ignorados[] = [
                        'line' => $documentosArquivo[$normalizado],
                        'reason' => 'documento_existente',
                    ];
                    unset($normalizados[$indicesPorDocumento[$normalizado]]);
                    $existentesProcessados[$normalizado] = true;
                }
            }
        }

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors, 'ignored' => $ignorados];
        }

        usort(
            $ignorados,
            static fn(array $a, array $b): int => $a['line'] <=> $b['line']
        );

        return [
            'success' => true,
            'data' => array_values($normalizados),
            'ignored' => $ignorados,
        ];
    }

    private function validarOpcoes(array $data, int $line, array &$errors): void
    {
        $opcoes = [
            'situacao' => ['A', 'I'],
            'sexo' => ['M', 'F'],
            'estado_civil' => ['solteiro', 'casado', 'divorciado', 'viuvo'],
            'email_recebe' => ['S', 'N'],
            'telefone_whatsapp' => ['S', 'N'],
            'telefone_telegram' => ['S', 'N'],
            'telefone_sms' => ['S', 'N'],
        ];

        foreach ($opcoes as $field => $permitidos) {
            $valor = $data[$field] ?? '';
            if ($valor === '') {
                continue;
            }
            $comparacao = $field === 'estado_civil' ? strtolower($valor) : strtoupper($valor);
            if (!in_array($comparacao, $permitidos, true)) {
                $errors[] = $this->erroLinha($line, $field, 'Valor fora do padrao permitido.');
            }
        }

        if (($data['preferred_locale'] ?? '') !== '' && !is_locale_supported($data['preferred_locale'])) {
            $errors[] = $this->erroLinha($line, 'preferred_locale', 'Idioma nao suportado.');
        }
    }

    private function validarComprimentos(array $data, int $line, array &$errors): void
    {
        foreach (self::MAX_LENGTHS as $field => $max) {
            if (mb_strlen($data[$field] ?? '', 'UTF-8') > $max) {
                $errors[] = $this->erroLinha($line, $field, "Use no maximo {$max} caracteres.");
            }
        }
    }

    private function validarContatos(array $data, int $line, array &$errors): void
    {
        if (($data['email'] ?? '') !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = $this->erroLinha($line, 'email', 'E-mail invalido.');
        }

        if (($data['telefone'] ?? '') !== '') {
            $digitos = preg_replace('/\D/', '', $data['telefone']);
            if (strlen($digitos) < 8 || strlen($digitos) > 15) {
                $errors[] = $this->erroLinha($line, 'telefone', 'Telefone deve conter entre 8 e 15 digitos.');
            }
        }
    }

    private function normalizarData(string $valor, int $line, string $field, array &$errors): ?string
    {
        if ($valor === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            [$ano, $mes, $dia] = array_map('intval', explode('-', $valor));
            if (checkdate($mes, $dia, $ano)) {
                return $valor;
            }
        } else {
            $config = DateHelper::getConfig();
            $formato = (string) ($config['date_format'] ?? 'd/m/Y');
            $timezone = new \DateTimeZone((string) ($config['timezone'] ?? 'America/Sao_Paulo'));
            $data = \DateTimeImmutable::createFromFormat('!' . $formato, $valor, $timezone);
            $status = \DateTimeImmutable::getLastErrors();
            $semErros = $status === false
                || (($status['warning_count'] ?? 0) === 0 && ($status['error_count'] ?? 0) === 0);

            if ($data !== false && $semErros && $data->format($formato) === $valor) {
                return $data->format('Y-m-d');
            }
        }

        $errors[] = $this->erroLinha($line, $field, 'Data invalida ou fora do formato configurado.');
        return null;
    }

    private function normalizarDocumento(string $valor, string $tipo): string
    {
        $valor = trim($valor);
        if ($tipo === 'PF' || $tipo === 'PJ') {
            return preg_replace('/\D/', '', $valor);
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $valor));
    }

    private function isExampleRow(array $data): bool
    {
        $tipo = strtoupper(trim((string) ($data['tipo'] ?? '')));
        if (!isset(self::EXAMPLE_DOCUMENTS[$tipo])) {
            return false;
        }

        $documento = $this->normalizarDocumento((string) ($data['cpf_cnpj'] ?? ''), $tipo);
        return hash_equals(self::EXAMPLE_DOCUMENTS[$tipo], $documento);
    }

    private function validarCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($digito = 9; $digito < 11; $digito++) {
            $soma = 0;
            for ($i = 0; $i < $digito; $i++) {
                $soma += (int) $cpf[$i] * (($digito + 1) - $i);
            }
            $calculado = (10 * $soma) % 11;
            $calculado = $calculado === 10 ? 0 : $calculado;
            if ((int) $cpf[$digito] !== $calculado) {
                return false;
            }
        }

        return true;
    }

    private function validarCnpj(string $cnpj): bool
    {
        if (!preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ($pesos as $indice => $listaPesos) {
            $soma = 0;
            foreach ($listaPesos as $i => $peso) {
                $soma += (int) $cnpj[$i] * $peso;
            }
            $resto = $soma % 11;
            $calculado = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[12 + $indice] !== $calculado) {
                return false;
            }
        }

        return true;
    }

    private function erroLinha(int $line, string $field, string $message): array
    {
        return compact('line', 'field', 'message');
    }

    private function erroArquivo(string $message, string $field = 'arquivo'): array
    {
        return [
            'success' => false,
            'errors' => [[
                'line' => null,
                'field' => $field,
                'message' => $message,
            ]],
        ];
    }
}
