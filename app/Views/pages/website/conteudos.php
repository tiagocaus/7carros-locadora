@extends('layouts.iframe')

@section('title', '<?= t("modules.website.contents_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0 pb-24">
    <div class="flex items-center justify-between mb-4">
        <h2 class="title-page"><?= t('modules.website.contents_title') ?></h2>
    </div>

    <!-- Bandeiras de idioma -->
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-sm text-slate-500 mr-2">Idioma:</span>
        <div id="langTabs" class="flex flex-wrap gap-2"></div>
    </div>

    <!-- Tabs de página -->
    <div class="border-b border-slate-200 mb-6 overflow-x-auto">
        <div id="pageTabs" class="flex gap-1"></div>
    </div>

    <!-- Container dos grupos -->
    <div id="groupsContainer" class="space-y-3"></div>
</div>

<!-- Rodapé fixo de salvar -->
<div id="saveFooter" class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 shadow-lg px-6 py-3 flex items-center justify-between z-40" style="display:none;">
    <div class="flex items-center gap-2">
        <i class="fas fa-exclamation-circle text-amber-500"></i>
        <span id="dirtyCount" class="text-sm font-medium text-slate-700"></span>
    </div>
    <div class="flex gap-2">
        <button id="btnDescartar" class="px-4 py-2 rounded-md text-sm font-medium text-slate-700 hover:bg-slate-100 transition-colors">
            Descartar
        </button>
        <button id="btnSalvar" class="btn-blue py-2 px-5 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md transition-shadow">
            <i class="fas fa-save mr-2"></i> Salvar tudo
        </button>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
'use strict';

// =========================================================================
// CONFIGURAÇÃO: Páginas, idiomas e schema de seções
// =========================================================================

const PAGES = [
    { key: 'inicio',   label: 'Início' },
    { key: 'sobre',    label: 'Sobre' },
    { key: 'veiculos', label: 'Veículos' },
    { key: 'contato',  label: 'Contato' },
    { key: 'reserva',  label: 'Reserva' },
    { key: 'global',   label: 'Global', icon: 'fa-globe' },
];

const LANGS = [
    { key: 'pt_BR', short: 'PT-BR', label: 'Português (BR)' },
    { key: 'en_US', short: 'EN',    label: 'English' },
    { key: 'es_ES', short: 'ES',    label: 'Español' },
    { key: 'it_IT', short: 'IT',    label: 'Italiano' },
    { key: 'pt_PT', short: 'PT-PT', label: 'Português (PT)' },
];

const SCHEMA = {
    inicio: [
        { title: 'Formulário de reserva', open: true, fields: [
            { key: 'titulo_reserva', label: 'Título do formulário', type: 'text' }
        ]},
        { title: 'Por que nos escolher?', open: true, fields: [
            { key: 'por_que_titulo', label: 'Título da seção', type: 'text' }
        ], cards: [
            { title: 'Card 1', fields: [
                { key: 'por_que_1_titulo', label: 'Título', type: 'text' },
                { key: 'por_que_1_texto',  label: 'Texto',  type: 'textarea', rows: 3 }
            ]},
            { title: 'Card 2', fields: [
                { key: 'por_que_2_titulo', label: 'Título', type: 'text' },
                { key: 'por_que_2_texto',  label: 'Texto',  type: 'textarea', rows: 3 }
            ]},
            { title: 'Card 3', fields: [
                { key: 'por_que_3_titulo', label: 'Título', type: 'text' },
                { key: 'por_que_3_texto',  label: 'Texto',  type: 'textarea', rows: 3 }
            ]},
            { title: 'Card 4', fields: [
                { key: 'por_que_4_titulo', label: 'Título', type: 'text' },
                { key: 'por_que_4_texto',  label: 'Texto',  type: 'textarea', rows: 3 }
            ]}
        ]},
        { title: 'Grupos de veículos', fields: [
            { key: 'grupos_titulo', label: 'Título da seção', type: 'text' }
        ]},
        { title: 'Nossos diferenciais', fields: [
            { key: 'diferenciais_titulo', label: 'Título da seção', type: 'text' }
        ], columns: [
            { title: 'Coluna esquerda', fields: [
                { key: 'diferencial_esq_1', label: 'Item 1', type: 'text' },
                { key: 'diferencial_esq_2', label: 'Item 2', type: 'text' },
                { key: 'diferencial_esq_3', label: 'Item 3', type: 'text' },
                { key: 'diferencial_esq_4', label: 'Item 4', type: 'text' }
            ]},
            { title: 'Coluna direita', fields: [
                { key: 'diferencial_dir_1', label: 'Item 1', type: 'text' },
                { key: 'diferencial_dir_2', label: 'Item 2', type: 'text' },
                { key: 'diferencial_dir_3', label: 'Item 3', type: 'text' },
                { key: 'diferencial_dir_4', label: 'Item 4', type: 'text' }
            ]}
        ]}
    ],

    sobre: [
        { title: 'Conteúdo da página Sobre', open: true, fields: [
            { key: 'titulo',    label: 'Título',    type: 'text' },
            { key: 'subtitulo', label: 'Subtítulo', type: 'text' },
            { key: 'texto',     label: 'Texto (HTML permitido)', type: 'textarea', rows: 10 }
        ]}
    ],

    veiculos: [
        { title: 'Conteúdo da página Veículos', open: true, fields: [
            { key: 'titulo', label: 'Título',                type: 'text' },
            { key: 'texto',  label: 'Texto (HTML permitido)', type: 'textarea', rows: 6 }
        ]}
    ],

    contato: [
        { title: 'Conteúdo da página Contato', open: true, fields: [
            { key: 'titulo', label: 'Título',                type: 'text' },
            { key: 'texto',  label: 'Texto (HTML permitido)', type: 'textarea', rows: 6 }
        ]}
    ],

    reserva: [
        { title: 'Títulos dos passos do formulário', open: true, fields: [
            { key: 'passo_1_titulo', label: 'Passo 1 — Local e datas',    type: 'text' },
            { key: 'passo_2_titulo', label: 'Passo 2 — Veículo',          type: 'text' },
            { key: 'passo_3_titulo', label: 'Passo 3 — Adicionais',       type: 'text' },
            { key: 'passo_4_titulo', label: 'Passo 4 — Dados cadastrais', type: 'text' },
            { key: 'passo_4_texto',  label: 'Passo 4 — Texto introdutório', type: 'text' },
            { key: 'passo_5_titulo', label: 'Passo 5 — Finalização',      type: 'text' },
            { key: 'passo_5_texto',  label: 'Passo 5 — Texto de sucesso', type: 'text' }
        ]}
    ],

    global: [
        { title: 'Barra informativa (4 cards da home)', open: true, cards: [
            { title: '📞 Atendimento', fields: [
                { key: 'barra_info_atendimento_titulo', label: 'Título', type: 'text' },
                { key: 'barra_info_atendimento_texto',  label: 'Texto',  type: 'text' }
            ]},
            { title: '💬 WhatsApp', fields: [
                { key: 'barra_info_whatsapp_titulo', label: 'Título', type: 'text' },
                { key: 'barra_info_whatsapp_texto',  label: 'Texto',  type: 'text' }
            ]},
            { title: '🆘 Assistência 24h', fields: [
                { key: 'barra_info_assistencia_titulo', label: 'Título', type: 'text' },
                { key: 'barra_info_assistencia_texto',  label: 'Texto',  type: 'text' }
            ]},
            { title: '🕐 Horário', fields: [
                { key: 'barra_info_horario_titulo', label: 'Título', type: 'text' },
                { key: 'barra_info_horario_texto',  label: 'Texto',  type: 'text' }
            ]}
        ]},
        { title: 'Rodapé (dados da empresa)', fields: [
            { key: 'footer_empresa', label: 'Dados da empresa (HTML permitido)', type: 'textarea', rows: 6 }
        ]}
    ]
};

// =========================================================================
// STATE
// =========================================================================

const state = {
    activePage: 'inicio',
    activeLang: 'pt_BR',
    cache: {},   // cache[page][lang][key] = valor carregado do servidor (snapshot)
    current: {}, // current[page][lang][key] = valor atual (editado)
};

// =========================================================================
// HELPERS
// =========================================================================

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

function ensurePath(obj, ...keys) {
    let cur = obj;
    for (const k of keys) {
        if (!cur[k]) cur[k] = {};
        cur = cur[k];
    }
    return cur;
}

function isDirty(page, lang, key) {
    const cached = (state.cache[page]?.[lang]?.[key]) ?? '';
    const current = (state.current[page]?.[lang]?.[key]) ?? '';
    return String(cached) !== String(current);
}

function countDirty() {
    let total = 0;
    for (const page in state.current) {
        for (const lang in state.current[page]) {
            for (const key in state.current[page][lang]) {
                if (isDirty(page, lang, key)) total++;
            }
        }
    }
    return total;
}

function dirtyByPageLang() {
    // retorna array de { page, lang, secoes } pra enviar ao backend
    const buckets = {};
    for (const page in state.current) {
        for (const lang in state.current[page]) {
            for (const key in state.current[page][lang]) {
                if (!isDirty(page, lang, key)) continue;
                const bk = page + '|' + lang;
                if (!buckets[bk]) buckets[bk] = { page, lang, secoes: {} };
                buckets[bk].secoes[key] = state.current[page][lang][key];
            }
        }
    }
    return Object.values(buckets);
}

function updateFooter() {
    const n = countDirty();
    const footer = document.getElementById('saveFooter');
    const count = document.getElementById('dirtyCount');
    if (n === 0) {
        footer.style.display = 'none';
    } else {
        footer.style.display = 'flex';
        count.textContent = n + (n === 1 ? ' alteração não salva' : ' alterações não salvas');
    }
    renderLangTabs();
    renderPageTabs();
}

// =========================================================================
// RENDER: tabs, bandeiras
// =========================================================================

function renderPageTabs() {
    const container = document.getElementById('pageTabs');
    container.innerHTML = '';
    PAGES.forEach(p => {
        const btn = document.createElement('button');
        btn.type = 'button';
        const active = p.key === state.activePage;
        btn.className = 'px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap '
            + (active
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300');
        const hasDirtyInPage = Object.keys(state.current[p.key] || {}).some(lang =>
            Object.keys(state.current[p.key][lang] || {}).some(k => isDirty(p.key, lang, k))
        );
        btn.innerHTML = (p.icon ? `<i class="fas ${p.icon} mr-1"></i>` : '')
            + escapeHtml(p.label)
            + (hasDirtyInPage ? ' <span class="inline-block w-2 h-2 bg-amber-500 rounded-full align-middle ml-1"></span>' : '');
        btn.addEventListener('click', () => switchPage(p.key));
        container.appendChild(btn);
    });
}

function renderLangTabs() {
    const container = document.getElementById('langTabs');
    container.innerHTML = '';
    LANGS.forEach(l => {
        const btn = document.createElement('button');
        btn.type = 'button';
        const active = l.key === state.activeLang;
        btn.className = 'px-3 py-1 text-xs font-semibold rounded-full border transition-colors '
            + (active
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white text-slate-700 border-slate-300 hover:border-slate-400');
        const hasDirtyInLang = PAGES.some(p =>
            Object.keys(state.current[p.key]?.[l.key] || {}).some(k => isDirty(p.key, l.key, k))
        );
        btn.innerHTML = escapeHtml(l.short)
            + (hasDirtyInLang ? ' <span class="inline-block w-2 h-2 bg-amber-500 rounded-full align-middle ml-1"></span>' : '');
        btn.title = l.label;
        btn.addEventListener('click', () => switchLang(l.key));
        container.appendChild(btn);
    });
}

// =========================================================================
// RENDER: grupos da página ativa
// =========================================================================

function renderGroups() {
    const container = document.getElementById('groupsContainer');
    container.innerHTML = '';

    const schema = SCHEMA[state.activePage] || [];
    schema.forEach((group, idx) => {
        const details = document.createElement('details');
        details.className = 'bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden';
        if (group.open || idx === 0) details.open = true;

        const summary = document.createElement('summary');
        summary.className = 'cursor-pointer px-5 py-4 font-semibold text-slate-800 hover:bg-slate-50 flex items-center justify-between';
        summary.innerHTML = `
            <span>${escapeHtml(group.title)}</span>
            <i class="fas fa-chevron-down text-xs text-slate-400"></i>
        `;
        details.appendChild(summary);

        const body = document.createElement('div');
        body.className = 'px-5 pb-5 pt-2 space-y-4';

        // Campos soltos do grupo
        if (group.fields && group.fields.length) {
            group.fields.forEach(f => body.appendChild(renderField(f)));
        }

        // Cards (grid 2 colunas)
        if (group.cards && group.cards.length) {
            const grid = document.createElement('div');
            grid.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
            group.cards.forEach(card => {
                const box = document.createElement('div');
                box.className = 'border border-slate-200 rounded-lg p-4 bg-slate-50';
                const h = document.createElement('h5');
                h.className = 'font-semibold text-slate-700 mb-3 text-sm uppercase tracking-wide';
                h.textContent = card.title;
                box.appendChild(h);
                card.fields.forEach(f => box.appendChild(renderField(f)));
                grid.appendChild(box);
            });
            body.appendChild(grid);
        }

        // Columns (grid 2 colunas pra diferenciais)
        if (group.columns && group.columns.length) {
            const grid = document.createElement('div');
            grid.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
            group.columns.forEach(col => {
                const colBox = document.createElement('div');
                colBox.className = 'border border-slate-200 rounded-lg p-4 bg-slate-50';
                const h = document.createElement('h5');
                h.className = 'font-semibold text-slate-700 mb-3 text-sm uppercase tracking-wide';
                h.textContent = col.title;
                colBox.appendChild(h);
                col.fields.forEach(f => colBox.appendChild(renderField(f)));
                grid.appendChild(colBox);
            });
            body.appendChild(grid);
        }

        details.appendChild(body);
        container.appendChild(details);
    });
}

function renderField(field) {
    const wrap = document.createElement('div');
    wrap.className = 'mb-3 last:mb-0';

    const label = document.createElement('label');
    label.className = 'block text-xs font-medium text-slate-600 mb-1';
    label.textContent = field.label;
    wrap.appendChild(label);

    const page = state.activePage;
    const lang = state.activeLang;
    ensurePath(state.current, page, lang);
    const value = state.current[page][lang][field.key] ?? '';

    let input;
    if (field.type === 'textarea') {
        input = document.createElement('textarea');
        input.rows = field.rows || 4;
        input.className = 'w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
        input.value = value;
    } else {
        input = document.createElement('input');
        input.type = 'text';
        input.className = 'w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
        input.value = value;
    }

    // Marca dirty visualmente se já vem dirty do state (ex: voltou pra tab)
    if (isDirty(page, lang, field.key)) {
        input.classList.add('border-amber-400', 'bg-amber-50');
    }

    input.addEventListener('input', () => {
        ensurePath(state.current, page, lang);
        state.current[page][lang][field.key] = input.value;
        if (isDirty(page, lang, field.key)) {
            input.classList.add('border-amber-400', 'bg-amber-50');
        } else {
            input.classList.remove('border-amber-400', 'bg-amber-50');
        }
        updateFooter();
    });

    wrap.appendChild(input);
    return wrap;
}

// =========================================================================
// AÇÕES: trocar página, trocar idioma, carregar, salvar, descartar
// =========================================================================

async function switchPage(page) {
    if (page === state.activePage) return;
    state.activePage = page;
    renderPageTabs();
    await loadIfNeeded(page, state.activeLang);
    renderGroups();
}

async function switchLang(lang) {
    if (lang === state.activeLang) return;
    state.activeLang = lang;
    renderLangTabs();
    await loadIfNeeded(state.activePage, lang);
    renderGroups();
}

async function loadIfNeeded(page, lang) {
    // Se já carregou do servidor uma vez, não chama de novo
    if (state.cache[page]?.[lang]) return;

    pageLoading.start();
    try {
        const result = await API.get('/api/website/conteudos/' + page, { idioma: lang });
        const list = result.success ? (result.data || []) : [];
        const snapshot = {};
        list.forEach(row => { snapshot[row.secao] = row.conteudo || ''; });
        ensurePath(state.cache, page);
        ensurePath(state.current, page);
        state.cache[page][lang] = snapshot;
        // Inicializa current como cópia
        state.current[page][lang] = { ...snapshot };
    } catch (e) {
        toast.error('Erro ao carregar conteúdos');
    } finally {
        pageLoading.done();
    }
}

async function salvarTudo() {
    const buckets = dirtyByPageLang();
    if (buckets.length === 0) return;

    const btn = document.getElementById('btnSalvar');
    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-wait');

    try {
        for (const bucket of buckets) {
            const result = await API.post('/api/website/conteudos/' + bucket.page, {
                idioma: bucket.lang,
                secoes: bucket.secoes,
            });
            if (!result.success) {
                toast.error(result.message || '<?= t("common.messages.error") ?>');
                return;
            }
            // Atualiza snapshot como se fosse o novo estado "limpo"
            ensurePath(state.cache, bucket.page);
            state.cache[bucket.page][bucket.lang] = {
                ...(state.cache[bucket.page][bucket.lang] || {}),
                ...bucket.secoes,
            };
        }

        toast.success('<?= t("common.messages.saved") ?>');
        renderGroups();      // repinta sem dirty
        updateFooter();
    } catch (e) {
        toast.error('<?= t("common.messages.error") ?>');
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-wait');
    }
}

function descartar() {
    const n = countDirty();
    if (n === 0) return;
    if (!confirm('Descartar ' + n + ' alteração(ões) não salva(s)?')) return;

    // Reseta current = cópia do cache em todas as combinações
    for (const page in state.current) {
        for (const lang in state.current[page]) {
            const snap = state.cache[page]?.[lang] || {};
            state.current[page][lang] = { ...snap };
        }
    }
    renderGroups();
    updateFooter();
}

// =========================================================================
// BOOT
// =========================================================================

async function boot() {
    document.getElementById('btnSalvar').addEventListener('click', salvarTudo);
    document.getElementById('btnDescartar').addEventListener('click', descartar);

    // Avisa antes de sair da página com alterações pendentes
    window.addEventListener('beforeunload', (e) => {
        if (countDirty() > 0) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    renderPageTabs();
    renderLangTabs();
    await loadIfNeeded(state.activePage, state.activeLang);
    renderGroups();
    updateFooter();
}

boot();
})();
</script>
@endsection
