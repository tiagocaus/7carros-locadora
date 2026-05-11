@extends('layouts.iframe')

@section('title', '<?= t("modules.website.config_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="title-page"><?= t('modules.website.config_title') ?></h2>
            <p class="text-sm text-slate-500 mt-1" id="siteDominio"></p>
        </div>
        <span id="siteStatusBadge" class="px-3 py-1 rounded-full text-xs font-medium"></span>
    </div>

    <form id="formConfig" method="POST">
        @csrf

        <!-- Funcionalidades -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.website.config_title') ?></h3>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Modo Manutencao -->
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.website.maintenance') ?></h4>
                        <p class="text-sm text-slate-500"><?= t('modules.website.maintenance_help') ?></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="manutencao" name="manutencao" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Reserva Online -->
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.website.online_reservation') ?></h4>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="reserva_online" name="reserva_online" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Overbooking -->
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.website.overbooking') ?></h4>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="overbooking" name="overbooking" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Pagamento Antecipado -->
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.website.advance_payment') ?></h4>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="pagamento_antecipado" name="pagamento_antecipado" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <!-- Reserva requer confirmacao -->
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.website.reserva_requer_confirmacao') ?></h4>
                        <p class="text-sm text-slate-500"><?= t('modules.website.reserva_requer_confirmacao_help') ?></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="reserva_requer_confirmacao" name="reserva_requer_confirmacao" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Pre-cadastro do Site -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.website.precadastro_title') ?></h3>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.website.cadastro_simples') ?></h4>
                        <p class="text-sm text-slate-500"><?= t('modules.website.cadastro_simples_help') ?></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="cadastro_simples" name="cadastro_simples" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h4 class="font-medium"><?= t('modules.website.envio_documentos') ?></h4>
                        <p class="text-sm text-slate-500"><?= t('modules.website.envio_documentos_help') ?></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="envio_documentos" name="envio_documentos" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>

            <!-- Sub-opcoes: obrigatoriedade por documento. Aparece so quando envio_documentos=1 -->
            <div id="docsObrigatoriosWrapper" class="mt-4 p-4 border border-slate-200 rounded-lg" style="display:none;">
                <p class="text-sm text-slate-600 mb-3"><?= t('modules.website.docs_obrigatorios_help') ?></p>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="doc_cnh_obrigatorio" name="doc_cnh_obrigatorio" value="1">
                        <span class="text-sm">CNH</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="doc_cpf_obrigatorio" name="doc_cpf_obrigatorio" value="1">
                        <span class="text-sm">CPF</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="doc_rg_obrigatorio" name="doc_rg_obrigatorio" value="1">
                        <span class="text-sm">RG / <?= t('modules.website.passaporte') ?></span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="doc_comprovante_obrigatorio" name="doc_comprovante_obrigatorio" value="1">
                        <span class="text-sm"><?= t('modules.website.comprovante') ?></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Idioma Padrao -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.website.default_language') ?></h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="form-input-group">
                    <label for="idioma_padrao" class="form-label-group"><?= t('modules.website.default_language') ?></label>
                    <select id="idioma_padrao" name="idioma_padrao" class="form-input-group-field">
                        <option value="pt_BR">Portugues (Brasil)</option>
                        <option value="en_US">English (US)</option>
                        <option value="es_ES">Espanol</option>
                        <option value="it_IT">Italiano</option>
                        <option value="pt_PT">Portugues (Portugal)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- WhatsApp -->
        <div class="form-section mb-6">
            <h3 class="form-section-title">WhatsApp</h3>

            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg mb-4">
                <div>
                    <h4 class="font-medium"><?= t('modules.website.whatsapp_floating') ?></h4>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="whatsapp_flutuante" name="whatsapp_flutuante" value="1" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="form-input-group">
                    <label for="whatsapp_numero" class="form-label-group"><?= t('modules.website.whatsapp_number') ?></label>
                    <input type="text" id="whatsapp_numero" name="whatsapp_numero" class="form-input-group-field" placeholder="<?= t('modules.website.whatsapp_number_help') ?>">
                </div>
                <div class="form-input-group">
                    <label for="whatsapp_mensagem" class="form-label-group"><?= t('modules.website.whatsapp_message') ?></label>
                    <input type="text" id="whatsapp_mensagem" name="whatsapp_mensagem" class="form-input-group-field">
                </div>
            </div>
        </div>

        <!-- Redes Sociais -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><?= t('modules.website.social_links') ?></h3>
            <div id="linksContainer" class="space-y-3"></div>
            <button type="button" id="btnAddLink" class="mt-3 text-sm text-blue-600 hover:text-blue-800 flex items-center">
                <i class="fas fa-plus mr-1"></i> <?= t('modules.website.add_link') ?>
            </button>
        </div>

        <!-- Botao Salvar -->
        <div class="flex justify-end">
            <button type="submit" class="btn-blue py-2.5 px-6 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow">
                <i class="fas fa-save mr-2"></i> <?= t('common.buttons.save') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const tiposRede = ['instagram', 'facebook', 'twitter', 'youtube', 'linkedin', 'tiktok'];
    let linksData = [];

    pageLoading.start();
    carregarDados();

    async function carregarDados() {
        try {
            const [configRes, linksRes] = await Promise.all([
                API.get('/api/website/config'),
                API.get('/api/website/links')
            ]);

            if (configRes.success && configRes.data) {
                preencherConfig(configRes.data);
            }

            if (linksRes.success) {
                linksData = linksRes.data || [];
                renderizarLinks();
            }
        } catch (error) {
            console.error('Erro ao carregar:', error);
            toast.error('Erro ao carregar configuracoes');
        } finally {
            pageLoading.done();
        }
    }

    function preencherConfig(data) {
        document.getElementById('siteDominio').textContent = data.dominio || '';

        const badge = document.getElementById('siteStatusBadge');
        const statusMap = {
            ativo: { text: '<?= t("modules.website.status.ativo") ?>', cls: 'bg-green-100 text-green-800' },
            pendente: { text: '<?= t("modules.website.status.pendente") ?>', cls: 'bg-yellow-100 text-yellow-800' },
            inativo: { text: '<?= t("modules.website.status.inativo") ?>', cls: 'bg-slate-100 text-slate-800' },
            suspenso: { text: '<?= t("modules.website.status.suspenso") ?>', cls: 'bg-red-100 text-red-800' },
        };
        const s = statusMap[data.status] || statusMap.inativo;
        badge.textContent = s.text;
        badge.className = 'px-3 py-1 rounded-full text-xs font-medium ' + s.cls;

        // Checkboxes
        document.getElementById('manutencao').checked = data.manutencao == 1;
        document.getElementById('reserva_online').checked = data.reserva_online == 1;
        document.getElementById('overbooking').checked = data.overbooking == 1;
        document.getElementById('pagamento_antecipado').checked = data.pagamento_antecipado == 1;
        document.getElementById('whatsapp_flutuante').checked = data.whatsapp_flutuante == 1;
        document.getElementById('reserva_requer_confirmacao').checked = data.reserva_requer_confirmacao == 1;
        document.getElementById('cadastro_simples').checked = data.cadastro_simples == 1;
        document.getElementById('envio_documentos').checked = data.envio_documentos == 1;
        document.getElementById('doc_cnh_obrigatorio').checked = data.doc_cnh_obrigatorio == 1;
        document.getElementById('doc_cpf_obrigatorio').checked = data.doc_cpf_obrigatorio == 1;
        document.getElementById('doc_rg_obrigatorio').checked = data.doc_rg_obrigatorio == 1;
        document.getElementById('doc_comprovante_obrigatorio').checked = data.doc_comprovante_obrigatorio == 1;
        toggleDocsObrigatorios();

        // Campos texto
        if (data.idioma_padrao) document.getElementById('idioma_padrao').value = data.idioma_padrao;
        document.getElementById('whatsapp_numero').value = data.whatsapp_numero || '';
        document.getElementById('whatsapp_mensagem').value = data.whatsapp_mensagem || '';
    }

    function toggleDocsObrigatorios() {
        const show = document.getElementById('envio_documentos').checked;
        document.getElementById('docsObrigatoriosWrapper').style.display = show ? 'block' : 'none';
    }
    document.getElementById('envio_documentos').addEventListener('change', toggleDocsObrigatorios);

    function renderizarLinks() {
        const container = document.getElementById('linksContainer');
        container.innerHTML = '';

        linksData.forEach((link, index) => {
            container.appendChild(criarLinkRow(link, index));
        });
    }

    function criarLinkRow(link, index) {
        const div = document.createElement('div');
        div.className = 'flex items-center gap-3';
        div.innerHTML = `
            <select class="form-input-group-field w-40 link-tipo" data-index="${index}">
                ${tiposRede.map(t => `<option value="${t}" ${link.tipo === t ? 'selected' : ''}>${t.charAt(0).toUpperCase() + t.slice(1)}</option>`).join('')}
            </select>
            <input type="text" class="form-input-group-field flex-1 link-url" data-index="${index}" value="${link.url || ''}" placeholder="https://...">
            <button type="button" class="text-red-500 hover:text-red-700 link-remove" data-index="${index}">
                <i class="fas fa-trash"></i>
            </button>
        `;
        return div;
    }

    document.getElementById('btnAddLink').addEventListener('click', function() {
        linksData.push({ tipo: 'instagram', url: '', ativo: 1 });
        renderizarLinks();
    });

    document.getElementById('linksContainer').addEventListener('click', function(e) {
        const btn = e.target.closest('.link-remove');
        if (btn) {
            linksData.splice(parseInt(btn.dataset.index), 1);
            renderizarLinks();
        }
    });

    // Submit
    document.getElementById('formConfig').addEventListener('submit', async function(e) {
        e.preventDefault();

        // Coletar dados dos links do DOM
        document.querySelectorAll('.link-tipo').forEach((el, i) => {
            linksData[i].tipo = el.value;
        });
        document.querySelectorAll('.link-url').forEach((el, i) => {
            linksData[i].url = el.value;
        });

        try {
            const [configRes, linksRes] = await Promise.all([
                API.post('/api/website/config', {
                    manutencao: document.getElementById('manutencao').checked ? 1 : 0,
                    reserva_online: document.getElementById('reserva_online').checked ? 1 : 0,
                    overbooking: document.getElementById('overbooking').checked ? 1 : 0,
                    pagamento_antecipado: document.getElementById('pagamento_antecipado').checked ? 1 : 0,
                    reserva_requer_confirmacao: document.getElementById('reserva_requer_confirmacao').checked ? 1 : 0,
                    cadastro_simples: document.getElementById('cadastro_simples').checked ? 1 : 0,
                    envio_documentos: document.getElementById('envio_documentos').checked ? 1 : 0,
                    doc_cnh_obrigatorio: document.getElementById('doc_cnh_obrigatorio').checked ? 1 : 0,
                    doc_cpf_obrigatorio: document.getElementById('doc_cpf_obrigatorio').checked ? 1 : 0,
                    doc_rg_obrigatorio: document.getElementById('doc_rg_obrigatorio').checked ? 1 : 0,
                    doc_comprovante_obrigatorio: document.getElementById('doc_comprovante_obrigatorio').checked ? 1 : 0,
                    idioma_padrao: document.getElementById('idioma_padrao').value,
                    whatsapp_flutuante: document.getElementById('whatsapp_flutuante').checked ? 1 : 0,
                    whatsapp_numero: document.getElementById('whatsapp_numero').value,
                    whatsapp_mensagem: document.getElementById('whatsapp_mensagem').value,
                }),
                API.post('/api/website/links', {
                    links: linksData.filter(l => l.url)
                })
            ]);

            if (configRes.success) {
                toast.success('<?= t("common.messages.saved") ?>');
            } else {
                toast.error(configRes.message || '<?= t("common.messages.error") ?>');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            toast.error('<?= t("common.messages.error") ?>');
        }
    });
})();
</script>
@endsection
