@extends('layouts.iframe')

@section('title', '<?= t("modules.configuracoes.templates_title") ?>')

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Header -->
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-slate-700"><?= t('modules.configuracoes.templates_title') ?></h2>
        <p class="text-sm text-slate-500"><?= t('modules.configuracoes.templates_description') ?></p>
    </div>

    <!-- Filtro por categoria -->
    <div class="mb-4">
        <div class="flex flex-wrap gap-2">
            <button type="button" data-category="" class="category-filter btn-filter active">
                <?= t('modules.configuracoes.categories.all') ?>
            </button>
            <button type="button" data-category="onboarding" class="category-filter btn-filter">
                <?= t('modules.configuracoes.categories.onboarding') ?>
            </button>
            <button type="button" data-category="rental" class="category-filter btn-filter">
                <?= t('modules.configuracoes.categories.rental') ?>
            </button>
            <button type="button" data-category="reminder" class="category-filter btn-filter">
                <?= t('modules.configuracoes.categories.reminder') ?>
            </button>
            <button type="button" data-category="billing" class="category-filter btn-filter">
                <?= t('modules.configuracoes.categories.billing') ?>
            </button>
        </div>
    </div>

    <!-- Grid de Templates -->
    <div id="templatesContainer">
        <div class="flex items-center justify-center py-8">
            <i class="fas fa-spinner fa-spin text-slate-400 mr-2"></i>
            <span class="text-slate-500"><?= t('modules.configuracoes.messages.loading') ?></span>
        </div>
    </div>
</div>

<style>
.btn-filter {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 9999px;
    background: white;
    color: #64748b;
    transition: all 0.15s;
}
.btn-filter:hover {
    background: #f1f5f9;
}
.btn-filter.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.template-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
    transition: all 0.15s;
}
.template-card:hover {
    border-color: #2563eb;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
}

.channel-icon {
    width: 1.5rem;
    height: 1.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.25rem;
    font-size: 0.75rem;
}
.channel-icon.email { background: #dbeafe; color: #2563eb; }
.channel-icon.whatsapp { background: #dcfce7; color: #16a34a; }
.channel-icon.sms { background: #fef3c7; color: #d97706; }

.badge-custom {
    background: #dcfce7;
    color: #15803d;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.625rem;
    font-weight: 600;
}

.category-badge {
    font-size: 0.625rem;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-weight: 500;
}
.category-badge.onboarding { background: #e0e7ff; color: #4338ca; }
.category-badge.rental { background: #fce7f3; color: #be185d; }
.category-badge.reminder { background: #fef3c7; color: #b45309; }
.category-badge.billing { background: #dcfce7; color: #15803d; }
</style>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const i18n = {
        loading: <?= json_encode(t('modules.configuracoes.messages.loading'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        loadError: <?= json_encode(t('modules.configuracoes.messages.load_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        noTemplates: <?= json_encode(t('modules.configuracoes.messages.no_templates'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        customized: <?= json_encode(t('modules.configuracoes.labels.customized'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        actionEdit: <?= json_encode(t('common.buttons.edit'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        catOnboarding: <?= json_encode(t('modules.configuracoes.category_labels.onboarding'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        catRental: <?= json_encode(t('modules.configuracoes.category_labels.rental'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        catReminder: <?= json_encode(t('modules.configuracoes.category_labels.reminder'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        catBilling: <?= json_encode(t('modules.configuracoes.category_labels.billing'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    };

    const container = document.getElementById('templatesContainer');
    let allTemplates = [];
    let currentCategory = '';

    // Carregar templates
    carregarTemplates();

    // Filtros de categoria
    document.querySelectorAll('.category-filter').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.category-filter').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.dataset.category;
            renderizarTemplates();
        });
    });

    async function carregarTemplates() {
        try {
            container.innerHTML = `
                <div class="flex items-center justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-slate-400 mr-2"></i>
                    <span class="text-slate-500">${i18n.loading}</span>
                </div>
            `;

            const result = await API.get('/api/templates/types');

            if (result.success && result.data) {
                allTemplates = result.data;
                renderizarTemplates();
            } else {
                container.innerHTML = '<p class="text-red-500 text-sm py-4">' + i18n.loadError + '</p>';
            }
        } catch (error) {
            console.error('Erro:', error);
            container.innerHTML = '<p class="text-red-500 text-sm py-4">Erro ao carregar templates.</p>';
        }
    }

    function renderizarTemplates() {
        let templates = allTemplates;

        // Filtrar por categoria
        if (currentCategory) {
            templates = templates.filter(t => t.category === currentCategory);
        }

        if (!templates || templates.length === 0) {
            container.innerHTML = '<p class="text-slate-500 text-sm py-4 text-center">' + i18n.noTemplates + '</p>';
            return;
        }

        let html = '<div class="grid gap-3">';

        templates.forEach(template => {
            const channels = template.channels || [];
            const channelIcons = channels.map(ch => {
                const icons = {
                    'email': '<span class="channel-icon email" title="Email"><i class="fas fa-envelope"></i></span>',
                    'whatsapp': '<span class="channel-icon whatsapp" title="WhatsApp"><i class="fab fa-whatsapp"></i></span>',
                    'sms': '<span class="channel-icon sms" title="SMS"><i class="fas fa-sms"></i></span>'
                };
                return icons[ch] || '';
            }).join('');

            const categoryLabels = {
                'onboarding': i18n.catOnboarding,
                'rental': i18n.catRental,
                'reminder': i18n.catReminder,
                'billing': i18n.catBilling
            };

            html += `
                <div class="template-card">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-medium text-slate-700">${escapeHtml(template.name)}</span>
                                <span class="category-badge ${template.category}">${categoryLabels[template.category] || template.category}</span>
                                ${template.is_customized ? '<span class="badge-custom">' + i18n.customized + '</span>' : ''}
                            </div>
                            ${template.description ? `<p class="text-xs text-slate-500 mb-2">${escapeHtml(template.description)}</p>` : ''}
                            <div class="flex items-center gap-1">
                                ${channelIcons}
                            </div>
                        </div>
                        <button type="button" onclick="editarTemplate('${template.slug}')" class="btn-blue text-xs py-1.5 px-3 rounded">
                            <i class="fas fa-pen mr-1"></i>${i18n.actionEdit}
                        </button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    }

    // Função global para editar template
    window.editarTemplate = function(slug) {
        // Navegar para página de edição dentro do iframe
        window.location.href = `/pages/configuracoes/templates/${slug}`;
    };

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endsection
