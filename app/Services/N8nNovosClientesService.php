<?php

namespace App\Services;

use App\Helpers\DateHelper;
use App\Models\N8nCliente;
use InvalidArgumentException;

/**
 * Prepara os contatos de empresas que completam X dias no sistema.
 */
class N8nNovosClientesService
{
    private const MAX_INTERVALOS = 50;
    private const MAX_DIAS = 36500;
    /** @var callable(int): string */
    private $targetDateResolver;

    public function __construct(private ?N8nCliente $model = null, ?callable $targetDateResolver = null)
    {
        $this->model ??= new N8nCliente();
        $this->targetDateResolver = $targetDateResolver
            ?? static fn(int $dia): string => DateHelper::addDaysForDatabase(-$dia);
    }

    /**
     * @return array<int, array{id: int, chave: string, tel_cel: string, email: string}>
     */
    public function listar(mixed $diasInformados): array
    {
        $dias = $this->normalizarDias($diasInformados);
        $proprietarios = $this->model->listarProprietariosAtivosComContato();

        if ($proprietarios === []) {
            return [];
        }

        $porTenant = [];
        foreach ($proprietarios as $proprietario) {
            $chave = trim((string) ($proprietario['chave'] ?? ''));
            if ($chave !== '') {
                $porTenant[$chave][] = $proprietario;
            }
        }

        $tinhaChaveNaSessao = array_key_exists('chave', $_SESSION ?? []);
        $chaveOriginal = $_SESSION['chave'] ?? null;
        $resultado = [];

        try {
            foreach ($porTenant as $chave => $contatos) {
                $_SESSION['chave'] = $chave;
                DateHelper::clearCache();

                $datasAlvo = [];
                foreach ($dias as $dia) {
                    $datasAlvo[($this->targetDateResolver)($dia)] = true;
                }

                foreach ($contatos as $contato) {
                    $dataCadastro = DateHelper::businessDateFromDateTime(
                        (string) ($contato['empresa_created_at'] ?? '')
                    );
                    if (!isset($datasAlvo[$dataCadastro])) {
                        continue;
                    }

                    $celular = preg_replace('/\D+/', '', (string) ($contato['tel_cel'] ?? ''));
                    $email = trim((string) ($contato['email'] ?? ''));

                    if ($celular === '' || $email === '') {
                        continue;
                    }

                    $resultado[] = [
                        'id' => (int) $contato['id'],
                        'chave' => $chave,
                        'tel_cel' => $celular,
                        'email' => $email,
                    ];
                }
            }
        } finally {
            if ($tinhaChaveNaSessao) {
                $_SESSION['chave'] = $chaveOriginal;
            } else {
                unset($_SESSION['chave']);
            }
            DateHelper::clearCache();
        }

        return $resultado;
    }

    /**
     * Aceita a lista CSV usada em ?dias=1,5,10 e elimina repeticoes.
     *
     * @return int[]
     */
    public function normalizarDias(mixed $diasInformados): array
    {
        if (!is_string($diasInformados) && !is_int($diasInformados)) {
            throw new InvalidArgumentException('O parâmetro dias deve ser uma lista de inteiros positivos separados por vírgula.');
        }

        $valor = trim((string) $diasInformados);
        if ($valor === '') {
            throw new InvalidArgumentException('O parâmetro dias é obrigatório.');
        }

        $partes = explode(',', $valor);
        if (count($partes) > self::MAX_INTERVALOS) {
            throw new InvalidArgumentException('O parâmetro dias aceita no máximo 50 intervalos.');
        }

        $dias = [];
        foreach ($partes as $parte) {
            $parte = trim($parte);
            if (
                $parte === ''
                || !ctype_digit($parte)
                || (int) $parte <= 0
                || (int) $parte > self::MAX_DIAS
            ) {
                throw new InvalidArgumentException('O parâmetro dias deve conter inteiros entre 1 e 36500.');
            }

            $dia = (int) $parte;
            $dias[$dia] = $dia;
        }

        return array_values($dias);
    }
}
