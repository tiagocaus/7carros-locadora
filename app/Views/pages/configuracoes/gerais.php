@extends('layouts.iframe')

@section('title', '<?= t("modules.configuracoes_gerais.title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="title-page"><?= t('modules.configuracoes_gerais.title') ?></h2>
            <p class="text-sm text-slate-500 mt-1" id="empresaNome"></p>
        </div>
    </div>

    <form id="formConfiguracoes" method="POST">
        @csrf
        <input type="hidden" id="matrizId" name="id" value="">

        <!-- Secao: Localizacao -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.configuracoes_gerais.sections.locale') ?></h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="form-input-group">
                    <label for="locale" class="form-label-group"><?= t('modules.configuracoes_gerais.fields.locale') ?></label>
                    <select id="locale" name="locale" class="form-input-group-field">
                        <option value="pt_BR">Portugues (Brasil)</option>
                        <option value="en_US">English (US)</option>
                        <option value="es_ES">Espanol (Espana)</option>
                        <option value="pt_PT">Portugues (Portugal)</option>
                        <option value="it_IT">Italiano (Italia)</option>
                    </select>
                </div>

                <div class="form-input-group">
                    <label for="currency_code" class="form-label-group"><?= t('modules.configuracoes_gerais.fields.currency') ?></label>
                    <select id="currency_code" name="currency_code" class="form-input-group-field">
                        <option value="BRL">Real (R$)</option>
                        <option value="USD">Dolar (US$)</option>
                        <option value="EUR">Euro (€)</option>
                    </select>
                </div>

                <div class="form-input-group">
                    <label for="date_format" class="form-label-group"><?= t('modules.configuracoes_gerais.fields.date_format') ?></label>
                    <select id="date_format" name="date_format" class="form-input-group-field">
                        <option value="d/m/Y">DD/MM/AAAA</option>
                        <option value="m/d/Y">MM/DD/AAAA</option>
                        <option value="Y-m-d">AAAA-MM-DD</option>
                    </select>
                </div>

                <div class="form-input-group">
                    <label for="datetime_format" class="form-label-group"><?= t('modules.configuracoes_gerais.fields.datetime_format') ?></label>
                    <select id="datetime_format" name="datetime_format" class="form-input-group-field">
                        <option value="d/m/Y H:i:s">DD/MM/AAAA HH:MM:SS</option>
                        <option value="m/d/Y H:i:s">MM/DD/AAAA HH:MM:SS</option>
                        <option value="Y-m-d H:i:s">AAAA-MM-DD HH:MM:SS</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Secao: Notificacoes -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.configuracoes_gerais.sections.notifications') ?></h3>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.configuracoes_gerais.notifications.sms_title') ?></h4>
                        <p class="text-sm text-slate-500"><?= t('modules.configuracoes_gerais.notifications.sms_desc') ?></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="notificacao_sms" name="notificacao_sms" value="S" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.configuracoes_gerais.notifications.email_title') ?></h4>
                        <p class="text-sm text-slate-500"><?= t('modules.configuracoes_gerais.notifications.email_desc') ?></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="notificacao_email" name="notificacao_email" value="S" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.configuracoes_gerais.notifications.whatsapp_title') ?></h4>
                        <p class="text-sm text-slate-500"><?= t('modules.configuracoes_gerais.notifications.whatsapp_desc') ?></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="notificacao_whatsapp" name="notificacao_whatsapp" value="S" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>

            <div class="form-input-group mt-4">
                <label for="notificacao_titulo" class="form-label-group"><?= t('modules.configuracoes_gerais.fields.notification_title') ?></label>
                <input type="text" id="notificacao_titulo" name="notificacao_titulo" class="form-input-group-field" placeholder="<?= t('modules.configuracoes_gerais.fields.notification_title_placeholder') ?>">
            </div>
        </div>

        <!-- Secao: Impressao -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.configuracoes_gerais.sections.print') ?></h3>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.configuracoes_gerais.print.bold_variables') ?></h4>
                        <p class="text-sm text-slate-500"><?= t('modules.configuracoes_gerais.print.bold_variables_desc') ?></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="impressao_variavel_negrito" name="impressao_variavel_negrito" value="S" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.configuracoes_gerais.print.remove_yellow_stripe') ?></h4>
                        <p class="text-sm text-slate-500"><?= t('modules.configuracoes_gerais.print.remove_yellow_stripe_desc') ?></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="impressao_remover_tarja_amarela" name="impressao_remover_tarja_amarela" value="S" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Secao: Sequencias de Numeracao -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.configuracoes_gerais.sections.sequences') ?></h3>
            <p class="text-sm text-slate-500 mb-4"><?= t('modules.configuracoes_gerais.sections.sequences_desc') ?></p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="form-input-group">
                    <label for="sequencia_locacoes" class="form-label-group"><?= t('modules.configuracoes_gerais.fields.next_rental_number') ?></label>
                    <input type="number" id="sequencia_locacoes" name="sequencia_locacoes" class="form-input-group-field" min="1" value="1">
                </div>

                <div class="form-input-group">
                    <label for="sequencia_contratos" class="form-label-group"><?= t('modules.configuracoes_gerais.fields.next_contract_number') ?></label>
                    <input type="number" id="sequencia_contratos" name="sequencia_contratos" class="form-input-group-field" min="1" value="1">
                </div>

                <div class="form-input-group">
                    <label for="sequencia_financeiro" class="form-label-group"><?= t('modules.configuracoes_gerais.fields.next_financial_number') ?></label>
                    <input type="number" id="sequencia_financeiro" name="sequencia_financeiro" class="form-input-group-field" min="1" value="1">
                </div>
            </div>
        </div>

        <!-- Botoes de acao -->
        <div class="mt-6 flex justify-end space-x-3">
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-save mr-2"></i><?= t('common.buttons.save') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        saveSuccess: '<?= t("modules.configuracoes_gerais.messages.save_success") ?>',
        saveError: '<?= t("modules.configuracoes_gerais.messages.save_error") ?>',
        loadError: '<?= t("modules.configuracoes_gerais.messages.load_error") ?>',
    };

    // Carregar dados ao iniciar
    pageLoading.start();
    carregarConfiguracoes();

    async function carregarConfiguracoes() {
        try {
            const result = await API.get('/api/configuracoes/gerais');

            if (result.success && result.data) {
                preencherFormulario(result.data);
            } else {
                toast.error(i18n.loadError);
            }
        } catch (error) {
            console.error('Erro ao carregar configuracoes:', error);
            toast.error(i18n.loadError);
        } finally {
            pageLoading.done();
        }
    }

    function preencherFormulario(data) {
        document.getElementById('matrizId').value = data.id || '';
        document.getElementById('empresaNome').textContent = data.nome_fantasia || data.razao_social || '';

        // Selects
        if (data.locale) document.getElementById('locale').value = data.locale;
        if (data.currency_code) document.getElementById('currency_code').value = data.currency_code;
        if (data.date_format) document.getElementById('date_format').value = data.date_format;
        if (data.datetime_format) document.getElementById('datetime_format').value = data.datetime_format;

        // Campos texto
        document.getElementById('notificacao_titulo').value = data.notificacao_titulo || '';

        // Sequencias
        const seqLocacoes = document.getElementById('sequencia_locacoes');
        const seqContratos = document.getElementById('sequencia_contratos');
        const seqFinanceiro = document.getElementById('sequencia_financeiro');

        seqLocacoes.value = data.sequencia_locacoes || 1;
        seqLocacoes.min = data.sequencia_locacoes || 1;
        seqContratos.value = data.sequencia_contratos || 1;
        seqContratos.min = data.sequencia_contratos || 1;
        seqFinanceiro.value = data.sequencia_financeiro || 1;
        seqFinanceiro.min = data.sequencia_financeiro || 1;

        // Checkboxes (S/N)
        document.getElementById('notificacao_sms').checked = data.notificacao_sms === 'S';
        document.getElementById('notificacao_email').checked = data.notificacao_email === 'S';
        document.getElementById('notificacao_whatsapp').checked = data.notificacao_whatsapp === 'S';
        document.getElementById('impressao_variavel_negrito').checked = data.impressao_variavel_negrito === 'S';
        document.getElementById('impressao_remover_tarja_amarela').checked = data.impressao_remover_tarja_amarela === 'S';
    }

    // Submit do formulario
    document.getElementById('formConfiguracoes')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const dados = {};

        for (let [key, value] of formData.entries()) {
            dados[key] = value;
        }

        // Converter checkboxes para S/N
        dados.notificacao_sms = document.getElementById('notificacao_sms').checked ? 'S' : 'N';
        dados.notificacao_email = document.getElementById('notificacao_email').checked ? 'S' : 'N';
        dados.notificacao_whatsapp = document.getElementById('notificacao_whatsapp').checked ? 'S' : 'N';
        dados.impressao_variavel_negrito = document.getElementById('impressao_variavel_negrito').checked ? 'S' : 'N';
        dados.impressao_remover_tarja_amarela = document.getElementById('impressao_remover_tarja_amarela').checked ? 'S' : 'N';

        try {
            const result = await API.post('/configuracoes/gerais/salvar', dados);

            if (result.success) {
                toast.success(i18n.saveSuccess);
            } else {
                toast.error(result.message || i18n.saveError);
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            toast.error(i18n.saveError);
        }
    });
})();
</script>
@endsection
