@extends('layouts.iframe')

@section('title', '<?= t("modules.temporadas.templates.title") ?>')

@section('content')
<div class="p-4">
    <!-- Filtro por pais -->
    <div class="mb-4">
        <label for="filtroTemplatePais" class="form-label-group"><?= t('modules.temporadas.templates.filter_country') ?></label>
        <select id="filtroTemplatePais" class="form-input-group-field">
            <option value=""><?= t('modules.temporadas.templates.all_countries') ?></option>
            <option value="BR"><?= t('modules.temporadas.countries.BR') ?></option>
            <option value="US"><?= t('modules.temporadas.countries.US') ?></option>
            <option value="IT"><?= t('modules.temporadas.countries.IT') ?></option>
            <option value="ES"><?= t('modules.temporadas.countries.ES') ?></option>
            <option value="PT"><?= t('modules.temporadas.countries.PT') ?></option>
        </select>
    </div>

    <!-- Lista de templates -->
    <div id="templatesContainer">
        <div class="flex items-center justify-center py-8">
            <i class="fas fa-spinner fa-spin text-slate-400 mr-2"></i>
            <span class="text-slate-500"><?= t('modules.temporadas.templates.loading') ?></span>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const i18n = {
        loading: '<?= addslashes(t('modules.temporadas.templates.loading')) ?>',
        loadError: '<?= addslashes(t('modules.temporadas.templates.load_error')) ?>',
        noTemplates: '<?= addslashes(t('modules.temporadas.templates.no_templates')) ?>',
        activate: '<?= addslashes(t('modules.temporadas.templates.activate')) ?>',
        activating: '<?= addslashes(t('modules.temporadas.templates.activating')) ?>',
        activateError: '<?= addslashes(t('modules.temporadas.templates.activate_error')) ?>',
    };

    const container = document.getElementById('templatesContainer');
    const filtro = document.getElementById('filtroTemplatePais');

    // Carregar templates ao iniciar
    carregarTemplates();

    // Recarregar ao mudar filtro
    filtro.addEventListener('change', carregarTemplates);

    async function carregarTemplates() {
        const pais = filtro.value;

        try {
            container.innerHTML = `
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-slate-400 mr-2"></i>
                    <span class="text-slate-500">${i18n.loading}</span>
                </div>
            `;

            const params = pais ? { pais } : {};
            const result = await API.get('/api/temporadas/templates', params);

            if (result.success && result.data) {
                renderizarTemplates(result.data);
            } else {
                container.innerHTML = `<p class="text-red-500 text-sm py-4 text-center">${i18n.loadError}</p>`;
            }
        } catch (error) {
            console.error('Erro:', error);
            container.innerHTML = `<p class="text-red-500 text-sm py-4 text-center">${i18n.loadError}</p>`;
        }
    }

    function renderizarTemplates(templates) {
        if (!templates || templates.length === 0) {
            container.innerHTML = `<p class="text-slate-500 text-sm py-8 text-center">${i18n.noTemplates}</p>`;
            return;
        }

        let html = '<div class="space-y-3">';

        templates.forEach(t => {
            const paisLabel = getPaisLabel(t.pais);

            html += `
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors border border-slate-200">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-slate-800">${escapeHtml(t.nome)}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">${escapeHtml(t.pais)}</span>
                        </div>
                        <p class="text-sm text-slate-500 mt-1">
                            <i class="fas fa-calendar-alt mr-1"></i>${escapeHtml(t.periodo)}
                        </p>
                    </div>
                    <button type="button" class="btn-blue py-2 px-4 rounded-md text-sm font-medium whitespace-nowrap ml-3 btn-ativar" data-id="${t.id}" data-nome="${escapeHtml(t.nome)}">
                        <i class="fas fa-check mr-1"></i>${i18n.activate}
                    </button>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

        // Event listeners para botoes Ativar
        container.querySelectorAll('.btn-ativar').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const nome = this.getAttribute('data-nome');
                ativarTemplate(id, nome, this);
            });
        });
    }

    async function ativarTemplate(templateId, templateNome, btn) {
        const originalText = btn.innerHTML;

        try {
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i>${i18n.activating}`;

            const result = await API.post('/temporadas/ativar-template', { template_id: templateId });

            if (result.success) {
                // PRIMEIRO notificar para atualizar (antes de fechar o offcanvas)
                window.parent.postMessage({
                    action: 'templateActivated',
                    templateId: templateId,
                    templateNome: templateNome
                }, '*');

                // DEPOIS fechar o offcanvas (isso destroi o iframe)
                window.parent.postMessage({
                    action: 'closeOffcanvas'
                }, '*');
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.activateError }, '*');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao ativar template');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    function getPaisLabel(codigo) {
        const paises = {
            'BR': 'Brasil',
            'US': 'Estados Unidos',
            'IT': 'Italia',
            'ES': 'Espanha',
            'PT': 'Portugal'
        };
        return paises[codigo] || codigo;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
@endsection
