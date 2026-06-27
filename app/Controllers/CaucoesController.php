<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\FilialHelper;
use App\Models\Contrato;
use App\Models\ContratoCaucao;
use App\Models\Locacao;
use App\Models\LocacaoCaucao;

class CaucoesController
{
    public function devolver(Request $request, string $origem, int $id): void
    {
        try {
            $origem = strtolower($origem);
            $dados = $request->all();

            if ($origem === 'locacao') {
                if (!Auth::can('locacoes.editar')) {
                    Response::json(['success' => false, 'message' => 'Sem permissao para editar locacoes'], 403);
                    return;
                }

                $locacao = (new Locacao())->buscarPorId($id);
                if (!$locacao) {
                    Response::json(['success' => false, 'message' => 'Locacao nao encontrada'], 404);
                    return;
                }
                if (
                    !FilialHelper::temAcessoFilial($locacao['id_matriz_filial_retirada'] ?? null)
                    && !FilialHelper::temAcessoFilial($locacao['id_matriz_filial_devolucao'] ?? null)
                ) {
                    Response::json(['success' => false, 'message' => 'Acesso negado a filial da locacao'], 403);
                    return;
                }

                (new LocacaoCaucao())->devolver($id, $locacao, $dados);
                Response::json(['success' => true, 'message' => 'Devolucao da caucao registrada com sucesso']);
                return;
            }

            if ($origem === 'contrato') {
                if (!Auth::can('contratos.editar')) {
                    Response::json(['success' => false, 'message' => 'Sem permissao para editar contratos'], 403);
                    return;
                }

                $contrato = (new Contrato())->buscarPorId($id);
                if (!$contrato) {
                    Response::json(['success' => false, 'message' => 'Contrato nao encontrado'], 404);
                    return;
                }
                if (!FilialHelper::temAcessoFilial($contrato['id_matriz_filial_retirada'] ?? null)) {
                    Response::json(['success' => false, 'message' => 'Acesso negado a filial do contrato'], 403);
                    return;
                }

                (new ContratoCaucao())->devolver($id, $contrato, $dados);
                Response::json(['success' => true, 'message' => 'Devolucao da caucao registrada com sucesso']);
                return;
            }

            Response::json(['success' => false, 'message' => 'Origem de caucao invalida'], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao registrar devolucao da caucao: ' . $e->getMessage()], 500);
        }
    }
}
