@extends('layouts.iframe')

@section('title', '<?= t("modules.website.seo_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page"><?= t('modules.website.seo_title') ?></h2>
    </div>

    <!-- Seletores -->
    <input type="hidden" id="selectPagina" value="inicio">
    <input type="hidden" id="selectIdioma" value="pt_BR">

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-sm text-slate-500 mr-2">Idioma:</span>
        <div id="langTabs" class="flex flex-wrap gap-2"></div>
    </div>

    <div class="border-b border-slate-200 mb-6 overflow-x-auto">
        <div id="pageTabs" class="flex gap-1"></div>
    </div>

    <form id="formSeo">
        @csrf
        <!-- Meta Tags -->
        <div class="form-section mb-6">
            <h3 class="form-section-title">Meta Tags</h3>
            <div class="space-y-4">
                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.website.meta_title') ?> <span id="titleCount" class="text-xs text-slate-400 ml-2">0/60</span></label>
                    <input type="text" id="meta_titulo" class="form-input-group-field" maxlength="255">
                </div>
                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.website.meta_description') ?> <span id="descCount" class="text-xs text-slate-400 ml-2">0/160</span></label>
                    <textarea id="meta_descricao" class="form-input-group-field" rows="3" maxlength="500"></textarea>
                </div>
                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.website.meta_keywords') ?></label>
                    <input type="text" id="meta_keywords" class="form-input-group-field" placeholder="palavra1, palavra2, palavra3">
                </div>
            </div>
        </div>

        <!-- Open Graph -->
        <div class="form-section mb-6">
            <h3 class="form-section-title">Open Graph</h3>
            <div class="space-y-4">
                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.website.og_title') ?></label>
                    <input type="text" id="og_titulo" class="form-input-group-field">
                </div>
                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.website.og_description') ?></label>
                    <textarea id="og_descricao" class="form-input-group-field" rows="2"></textarea>
                </div>
                <div class="form-input-group">
                    <label class="form-label-group"><?= t('modules.website.og_image') ?></label>
                    <input type="text" id="og_imagem" class="form-input-group-field" placeholder="https://...">
                </div>
            </div>
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
    const PAGES = [
        { key: 'inicio',   label: 'Início' },
        { key: 'sobre',    label: 'Sobre' },
        { key: 'veiculos', label: 'Veículos' },
        { key: 'contato',  label: 'Contato' },
        { key: 'reserva',  label: 'Reserva' },
    ];
    const LANGS = [
        { key: 'pt_BR', short: 'PT-BR', label: 'Português (BR)' },
        { key: 'en_US', short: 'EN',    label: 'English' },
        { key: 'es_ES', short: 'ES',    label: 'Español' },
        { key: 'it_IT', short: 'IT',    label: 'Italiano' },
        { key: 'pt_PT', short: 'PT-PT', label: 'Português (PT)' },
    ];

    const paginaInput = document.getElementById('selectPagina');
    const idiomaInput = document.getElementById('selectIdioma');

    function renderPageTabs() {
        const container = document.getElementById('pageTabs');
        container.innerHTML = '';
        PAGES.forEach(p => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const active = p.key === paginaInput.value;
            btn.className = 'px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap '
                + (active
                    ? 'border-blue-600 text-blue-600'
                    : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300');
            btn.textContent = p.label;
            btn.addEventListener('click', () => {
                if (paginaInput.value === p.key) return;
                paginaInput.value = p.key;
                renderPageTabs();
                carregar();
            });
            container.appendChild(btn);
        });
    }

    function renderLangTabs() {
        const container = document.getElementById('langTabs');
        container.innerHTML = '';
        LANGS.forEach(l => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const active = l.key === idiomaInput.value;
            btn.className = 'px-3 py-1 text-xs font-semibold rounded-full border transition-colors '
                + (active
                    ? 'bg-blue-600 text-white border-blue-600'
                    : 'bg-white text-slate-700 border-slate-300 hover:border-slate-400');
            btn.textContent = l.short;
            btn.title = l.label;
            btn.addEventListener('click', () => {
                if (idiomaInput.value === l.key) return;
                idiomaInput.value = l.key;
                renderLangTabs();
                carregar();
            });
            container.appendChild(btn);
        });
    }

    renderPageTabs();
    renderLangTabs();
    pageLoading.start();
    carregar();

    // Contadores de caracteres
    document.getElementById('meta_titulo').addEventListener('input', function() {
        document.getElementById('titleCount').textContent = this.value.length + '/60';
        document.getElementById('titleCount').className = 'text-xs ml-2 ' + (this.value.length > 60 ? 'text-red-500' : 'text-slate-400');
    });
    document.getElementById('meta_descricao').addEventListener('input', function() {
        document.getElementById('descCount').textContent = this.value.length + '/160';
        document.getElementById('descCount').className = 'text-xs ml-2 ' + (this.value.length > 160 ? 'text-red-500' : 'text-slate-400');
    });

    async function carregar() {
        const pagina = document.getElementById('selectPagina').value;
        const idioma = document.getElementById('selectIdioma').value;

        try {
            const result = await API.get('/api/website/seo/' + pagina, { idioma });
            const data = result.success ? result.data : null;

            document.getElementById('meta_titulo').value = data?.meta_titulo || '';
            document.getElementById('meta_descricao').value = data?.meta_descricao || '';
            document.getElementById('meta_keywords').value = data?.meta_keywords || '';
            document.getElementById('og_titulo').value = data?.og_titulo || '';
            document.getElementById('og_descricao').value = data?.og_descricao || '';
            document.getElementById('og_imagem').value = data?.og_imagem || '';

            // Disparar contadores
            document.getElementById('meta_titulo').dispatchEvent(new Event('input'));
            document.getElementById('meta_descricao').dispatchEvent(new Event('input'));
        } catch (error) {
            toast.error('Erro ao carregar SEO');
        } finally {
            pageLoading.done();
        }
    }

    // Salvar
    document.getElementById('formSeo').addEventListener('submit', async function(e) {
        e.preventDefault();

        const pagina = document.getElementById('selectPagina').value;
        const idioma = document.getElementById('selectIdioma').value;

        try {
            const result = await API.post('/api/website/seo/' + pagina, {
                idioma,
                meta_titulo: document.getElementById('meta_titulo').value,
                meta_descricao: document.getElementById('meta_descricao').value,
                meta_keywords: document.getElementById('meta_keywords').value,
                og_titulo: document.getElementById('og_titulo').value,
                og_descricao: document.getElementById('og_descricao').value,
                og_imagem: document.getElementById('og_imagem').value,
            });

            if (result.success) {
                toast.success('<?= t("common.messages.saved") ?>');
            } else {
                toast.error(result.message || '<?= t("common.messages.error") ?>');
            }
        } catch (error) {
            toast.error('<?= t("common.messages.error") ?>');
        }
    });
})();
</script>
@endsection
