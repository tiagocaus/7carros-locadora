@extends('layouts.iframe')

@section('title', '<?= t("modules.website.publish_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page"><?= t('modules.website.publish_title') ?></h2>
    </div>

    <!-- Status Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white shadow-md rounded-lg p-5">
            <p class="text-xs text-slate-400 mb-1"><?= t('modules.website.current_version') ?></p>
            <p id="versaoAtual" class="text-2xl font-bold text-slate-700">-</p>
        </div>
        <div class="bg-white shadow-md rounded-lg p-5">
            <p class="text-xs text-slate-400 mb-1"><?= t('modules.website.template_version') ?></p>
            <p id="versaoTemplate" class="text-2xl font-bold text-slate-700">-</p>
        </div>
        <div class="bg-white shadow-md rounded-lg p-5">
            <p class="text-xs text-slate-400 mb-1"><?= t('modules.website.last_deploy') ?></p>
            <p id="ultimoDeploy" class="text-lg font-semibold text-slate-700">-</p>
        </div>
    </div>

    <!-- Alerta de atualização -->
    <div id="alertaUpdate" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <i class="fas fa-arrow-circle-up text-blue-500 text-xl"></i>
            <div>
                <p class="font-medium text-blue-800"><?= t('modules.website.update_available') ?></p>
                <p class="text-sm text-blue-600" id="updateMsg"></p>
            </div>
        </div>
        <button id="btnAtualizar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow">
            <i class="fas fa-cloud-upload-alt mr-2"></i> <?= t('modules.website.deploy_update_button') ?>
        </button>
    </div>

    <!-- Botão Publicar -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-6 text-center">
        <button id="btnDeploy" class="btn-blue py-3 px-8 rounded-md text-sm font-medium inline-flex items-center shadow hover:shadow-md transition-shadow disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="fas fa-rocket mr-2" id="deployIcon"></i> <span id="deployText"><?= t('modules.website.deploy_button') ?></span>
        </button>
        <p id="deployMsg" class="text-xs text-slate-400 mt-2"></p>
    </div>

    <!-- Histórico de Deploys -->
    <div class="form-section">
        <h3 class="form-section-title"><?= t('modules.website.deploy_history') ?></h3>
        <div class="bg-white shadow-md rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-600 text-left">
                        <th class="px-4 py-3 font-medium">Data</th>
                        <th class="px-4 py-3 font-medium">Tipo</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Versao</th>
                        <th class="px-4 py-3 font-medium">Responsavel</th>
                    </tr>
                </thead>
                <tbody id="deployHistorico"></tbody>
            </table>
            <div id="historicoVazio" class="hidden p-6 text-center text-slate-400 text-sm">
                Nenhum deploy realizado
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const statusClasses = {
        iniciado: 'bg-yellow-100 text-yellow-700',
        sucesso: 'bg-green-100 text-green-700',
        falha: 'bg-red-100 text-red-700',
    };
    const tipoLabels = {
        deploy: '<?= t("modules.website.deploy_types.deploy") ?>',
        redeploy: '<?= t("modules.website.deploy_types.redeploy") ?>',
        update: '<?= t("modules.website.deploy_types.update") ?>',
        rollback: '<?= t("modules.website.deploy_types.rollback") ?>',
    };
    const statusLabels = {
        iniciado: '<?= t("modules.website.deploy_status.iniciado") ?>',
        sucesso: '<?= t("modules.website.deploy_status.sucesso") ?>',
        falha: '<?= t("modules.website.deploy_status.falha") ?>',
    };

    pageLoading.start();
    Promise.all([carregarStatus(), carregarHistorico()]).finally(() => pageLoading.done());

    async function carregarStatus() {
        try {
            const result = await API.get('/api/website/deploy/status');
            if (result.success) {
                document.getElementById('versaoAtual').textContent = result.versao_atual || '-';
                document.getElementById('versaoTemplate').textContent = result.versao_template || '-';
                document.getElementById('ultimoDeploy').textContent = result.ultimo_deploy_em ? new Date(result.ultimo_deploy_em).toLocaleString() : '-';

                if (result.update_disponivel) {
                    document.getElementById('alertaUpdate').classList.remove('hidden');
                    document.getElementById('updateMsg').textContent = result.versao_atual + ' → ' + result.versao_template;
                } else {
                    document.getElementById('alertaUpdate').classList.add('hidden');
                }
            }
        } catch (error) {
            console.error('Erro ao carregar status:', error);
        }
    }

    async function carregarHistorico() {
        try {
            const result = await API.get('/api/website/deploy/log');
            const tbody = document.getElementById('deployHistorico');
            const vazio = document.getElementById('historicoVazio');

            if (!result.success || !result.data || result.data.length === 0) {
                vazio.classList.remove('hidden');
                return;
            }

            vazio.classList.add('hidden');
            tbody.innerHTML = result.data.map(d => `
                <tr class="border-t border-slate-100 hover:bg-slate-50">
                    <td class="px-4 py-3">${new Date(d.created_at).toLocaleString()}</td>
                    <td class="px-4 py-3">${tipoLabels[d.tipo] || d.tipo}</td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs ${statusClasses[d.status] || ''}">${statusLabels[d.status] || d.status}</span></td>
                    <td class="px-4 py-3 font-mono text-xs">${d.versao}</td>
                    <td class="px-4 py-3">${d.funcionario_nome || '-'}</td>
                </tr>
            `).join('');
        } catch (error) {
            console.error('Erro ao carregar historico:', error);
        }
    }

    // Deploy
    document.getElementById('btnDeploy').addEventListener('click', async function() {
        var btn = this;
        var icon = document.getElementById('deployIcon');
        var text = document.getElementById('deployText');
        var msg = document.getElementById('deployMsg');

        btn.disabled = true;
        icon.className = 'fas fa-spinner fa-spin mr-2';
        text.textContent = 'Publicando...';
        msg.textContent = '';

        try {
            const result = await API.post('/api/website/deploy');
            if (result.success) {
                toast.success(result.message);
                msg.textContent = result.message;
                msg.className = 'text-xs text-green-600 mt-2';
                carregarStatus();
                carregarHistorico();
            } else {
                toast.error(result.message || '<?= t("common.messages.error") ?>');
                msg.textContent = result.message || '';
                msg.className = 'text-xs text-red-600 mt-2';
            }
        } catch (error) {
            toast.error('<?= t("common.messages.error") ?>');
            msg.textContent = '<?= t("common.messages.error") ?>';
            msg.className = 'text-xs text-red-600 mt-2';
        } finally {
            btn.disabled = false;
            icon.className = 'fas fa-rocket mr-2';
            text.textContent = '<?= t("modules.website.deploy_button") ?>';
        }
    });

    // Botao atualizar (mesmo que deploy)
    var btnAtualizar = document.getElementById('btnAtualizar');
    if (btnAtualizar) {
        btnAtualizar.addEventListener('click', function() {
            document.getElementById('btnDeploy').click();
        });
    }
})();
</script>
@endsection
