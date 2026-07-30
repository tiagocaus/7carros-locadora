<?php

namespace App\Services;

use App\Models\ContatoEmail;
use App\Models\ContatoTelefone;
use App\Models\HorarioExcecao;
use App\Models\HorarioFuncionamento;
use App\Models\MatrizFilial;
use App\Models\MatrizFilialLocal;
use App\Models\Model;
use mysqli;
use Throwable;

/**
 * Orquestra o cadastro completo de uma matriz/filial em uma unica transacao.
 */
class MatrizFilialCadastroService
{
    private MatrizFilial $matrizFilial;
    private HorarioFuncionamento $horarioFuncionamento;
    private HorarioExcecao $horarioExcecao;
    private ContatoEmail $contatoEmail;
    private ContatoTelefone $contatoTelefone;
    private MatrizFilialLocal $matrizFilialLocal;
    private mysqli $mysqli;

    public function __construct(
        ?MatrizFilial $matrizFilial = null,
        ?HorarioFuncionamento $horarioFuncionamento = null,
        ?HorarioExcecao $horarioExcecao = null,
        ?ContatoEmail $contatoEmail = null,
        ?ContatoTelefone $contatoTelefone = null,
        ?MatrizFilialLocal $matrizFilialLocal = null,
        ?mysqli $mysqli = null
    ) {
        $this->matrizFilial = $matrizFilial ?? new MatrizFilial();
        $this->horarioFuncionamento = $horarioFuncionamento ?? new HorarioFuncionamento();
        $this->horarioExcecao = $horarioExcecao ?? new HorarioExcecao();
        $this->contatoEmail = $contatoEmail ?? new ContatoEmail();
        $this->contatoTelefone = $contatoTelefone ?? new ContatoTelefone();
        $this->matrizFilialLocal = $matrizFilialLocal ?? new MatrizFilialLocal();
        $this->mysqli = $mysqli ?? Model::sharedMysqli();
    }

    /**
     * @param array<string, mixed> $dados
     * @param array{
     *     horarios?: array<int, array<string, mixed>>,
     *     excecoes?: array<int, array<string, mixed>>,
     *     emails?: array<int, array<string, mixed>>,
     *     telefones?: array<int, array<string, mixed>>,
     *     locais?: array<int, array<string, mixed>>
     * } $relacionados
     */
    public function criar(array $dados, array $relacionados = []): int
    {
        $this->mysqli->begin_transaction();

        try {
            $id = $this->matrizFilial->criar($dados);

            $horarios = $relacionados['horarios'] ?? [];
            if ($horarios !== []) {
                $this->horarioFuncionamento->salvar($id, $horarios, false);
            }

            foreach ($relacionados['excecoes'] ?? [] as $excecao) {
                $excecao['matriz_filial_id'] = $id;
                $this->horarioExcecao->salvar($excecao);
            }

            $emails = $relacionados['emails'] ?? [];
            if ($emails !== []) {
                $this->contatoEmail->salvar('matriz_filial', $id, $emails, false);
            }

            $telefones = $relacionados['telefones'] ?? [];
            if ($telefones !== []) {
                $this->contatoTelefone->salvar('matriz_filial', $id, $telefones, false);
            }

            if (array_key_exists('locais', $relacionados)) {
                $this->matrizFilialLocal->sincronizar($id, $relacionados['locais']);
            }

            $this->mysqli->commit();
            return $id;
        } catch (Throwable $e) {
            try {
                $this->mysqli->rollback();
            } catch (Throwable $rollbackError) {
                error_log('[MatrizFilialCadastro] Falha no rollback: ' . $rollbackError->getMessage());
            }

            throw $e;
        }
    }
}
