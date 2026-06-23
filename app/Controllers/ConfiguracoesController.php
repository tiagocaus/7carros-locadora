<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\I18n\Translator;
use App\Models\MatrizFilial;

/**
 * Controller de Configurações Gerais
 *
 * Gerencia configurações da empresa vinculada ao funcionário logado
 */
class ConfiguracoesController
{
    /**
     * Busca o registro da empresa do funcionário logado (filial ou matriz como fallback)
     */
    private function buscarEmpresa(MatrizFilial $model): ?array
    {
        $filialId = (int) ($_SESSION['id_matriz_filial'] ?? 0);
        return $filialId ? $model->buscarPorId($filialId) : $model->buscarMatriz();
    }

    /**
     * Retorna as configurações atuais da empresa do funcionário
     *
     * GET /api/configuracoes/gerais
     */
    public function show(Request $request): void
    {
        try {
            if (!Auth::can('configuracoes.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar configurações'
                ], 403);
                return;
            }

            $model = new MatrizFilial();
            $matriz = $this->buscarEmpresa($model);

            if (!$matriz) {
                Response::json([
                    'success' => false,
                    'message' => 'Empresa não encontrada'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'data' => [
                    'id' => $matriz['id'],
                    'razao_social' => $matriz['razao_social'] ?? '',
                    'nome_fantasia' => $matriz['nome_fantasia'] ?? '',
                    'locale' => $matriz['locale'] ?? 'pt_BR',
                    'currency_code' => $matriz['currency_code'] ?? 'BRL',
                    'date_format' => $matriz['date_format'] ?? 'd/m/Y',
                    'datetime_format' => $matriz['datetime_format'] ?? 'd/m/Y H:i:s',
                    'notificacao_sms' => $matriz['notificacao_sms'] ?? 'N',
                    'notificacao_email' => $matriz['notificacao_email'] ?? 'N',
                    'notificacao_whatsapp' => $matriz['notificacao_whatsapp'] ?? 'N',
                    'notificacao_titulo' => $matriz['notificacao_titulo'] ?? '',
                    'impressao_variavel_negrito' => $matriz['impressao_variavel_negrito'] ?? 'N',
                    'impressao_remover_tarja_amarela' => $matriz['impressao_remover_tarja_amarela'] ?? 'N',
                    'sequencia_locacoes' => (int) ($matriz['sequencia_locacoes'] ?? 1),
                    'sequencia_contratos' => (int) ($matriz['sequencia_contratos'] ?? 1),
                    'sequencia_financeiro' => (int) ($matriz['sequencia_financeiro'] ?? 1),
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao carregar configurações'
            ], 500);
        }
    }

    /**
     * Salva as configurações da empresa do funcionário
     *
     * POST /configuracoes/gerais/salvar
     */
    public function update(Request $request): void
    {
        try {
            if (!Auth::can('configuracoes.editar')) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para editar configurações'
                ], 403);
                return;
            }

            $model = new MatrizFilial();
            $matriz = $this->buscarEmpresa($model);

            if (!$matriz) {
                Response::json([
                    'success' => false,
                    'message' => 'Empresa não encontrada'
                ], 404);
                return;
            }

            // Campos permitidos
            $allowedLocales = ['pt_BR', 'en_US', 'es_ES', 'pt_PT', 'it_IT'];
            $allowedCurrencies = ['BRL', 'USD', 'EUR'];
            $allowedDateFormats = ['d/m/Y', 'm/d/Y', 'Y-m-d'];
            $allowedDatetimeFormats = ['d/m/Y H:i:s', 'm/d/Y H:i:s', 'Y-m-d H:i:s'];

            // Validar locale
            $locale = $request->input('locale', 'pt_BR');
            if (!in_array($locale, $allowedLocales, true)) {
                $locale = 'pt_BR';
            }

            // Validar moeda
            $currency = $request->input('currency_code', 'BRL');
            if (!in_array($currency, $allowedCurrencies, true)) {
                $currency = 'BRL';
            }

            // Validar formato de data
            $dateFormat = $request->input('date_format', 'd/m/Y');
            if (!in_array($dateFormat, $allowedDateFormats, true)) {
                $dateFormat = 'd/m/Y';
            }

            // Validar formato de datetime
            $datetimeFormat = $request->input('datetime_format', 'd/m/Y H:i:s');
            if (!in_array($datetimeFormat, $allowedDatetimeFormats, true)) {
                $datetimeFormat = 'd/m/Y H:i:s';
            }

            // Validar sequências (não podem ser menores que o atual)
            $seqLocacoes = max((int) $matriz['sequencia_locacoes'], (int) $request->input('sequencia_locacoes', $matriz['sequencia_locacoes']));
            $seqContratos = max((int) $matriz['sequencia_contratos'], (int) $request->input('sequencia_contratos', $matriz['sequencia_contratos']));
            $seqFinanceiro = max((int) $matriz['sequencia_financeiro'], (int) $request->input('sequencia_financeiro', $matriz['sequencia_financeiro']));

            // Campos toggle (S/N)
            $toggleFields = ['notificacao_sms', 'notificacao_email', 'notificacao_whatsapp', 'impressao_variavel_negrito', 'impressao_remover_tarja_amarela'];
            $dados = [
                'locale' => $locale,
                'currency_code' => $currency,
                'date_format' => $dateFormat,
                'datetime_format' => $datetimeFormat,
                'notificacao_titulo' => trim($request->input('notificacao_titulo', '')),
                'sequencia_locacoes' => $seqLocacoes,
                'sequencia_contratos' => $seqContratos,
                'sequencia_financeiro' => $seqFinanceiro,
            ];

            foreach ($toggleFields as $field) {
                $dados[$field] = $request->input($field) === 'S' ? 'S' : 'N';
            }

            $model->atualizar((int) $matriz['id'], $dados);
            $_SESSION['empresa_ui_locale'] = $locale;

            if (empty($_SESSION['user_locale']) && empty($_SESSION['ui_locale'])) {
                Translator::getInstance()->setLocale($locale);
                unset($_SESSION['ui_locale']);
            }

            Response::json([
                'success' => true,
                'message' => 'Configurações salvas com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao salvar configurações'
            ], 500);
        }
    }
}
