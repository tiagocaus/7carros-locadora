(function () {
    'use strict';

    var boot = window.PORTAL_BOOT || {};
    var tr = boot.i18n || {};
    var state = {
        profile: boot.profile || 'cliente',
        csrf: boot.csrf || '',
        person: null,
        page: 'dashboard',
        pageNumber: 1,
        start: dateOffset(-365),
        end: dateOffset(0)
    };

    var $ = function (selector) { return document.querySelector(selector); };
    var loginView = $('#loginView');
    var appView = $('#appView');
    var content = $('#portalContent');

    function text(key, fallback) {
        return typeof tr[key] === 'string' ? tr[key] : fallback;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function dateOffset(days) {
        var date = new Date();
        date.setDate(date.getDate() + days);
        return date.toISOString().slice(0, 10);
    }

    function formatDate(value) {
        if (!value) return '—';
        var raw = String(value).slice(0, 10).split('-');
        return raw.length === 3 ? raw[2] + '/' + raw[1] + '/' + raw[0] : escapeHtml(value);
    }

    function money(value) {
        var number = Number(value || 0);
        try {
            return new Intl.NumberFormat(document.documentElement.lang || 'pt-BR', {
                style: 'currency',
                currency: boot.currency === '€' ? 'EUR' : 'BRL'
            }).format(number);
        } catch (error) {
            return escapeHtml(boot.currency || 'R$') + ' ' + number.toFixed(2);
        }
    }

    function toast(message, type) {
        var el = $('#portalToast');
        el.textContent = message;
        el.className = 'portal-toast show ' + (type || '');
        clearTimeout(toast.timer);
        toast.timer = setTimeout(function () { el.className = 'portal-toast'; }, 3800);
    }

    async function request(action, options) {
        options = options || {};
        var method = options.method || 'GET';
        var query = new URLSearchParams(options.query || {});
        query.set('action', action);
        var config = { method: method, headers: { Accept: 'application/json' } };
        if (method !== 'GET') {
            config.headers['Content-Type'] = 'application/json';
            config.body = JSON.stringify(Object.assign({}, options.body || {}, { _csrf: state.csrf }));
        }
        var response = await fetch('ajax-portal-api.php?' + query.toString(), config);
        var data = await response.json().catch(function () {
            return { success: false, message: text('network_error', 'Erro de comunicacao.') };
        });
        if (response.status === 401 || data.session_expired) {
            showLogin();
            throw new Error(data.message || text('session_expired', 'Sessao expirada.'));
        }
        return data;
    }

    function setBusy(button, busy) {
        if (!button) return;
        button.disabled = busy;
        if (!button.dataset.label) button.dataset.label = button.innerHTML;
        button.innerHTML = busy
            ? '<i class="fa fa-circle-o-notch fa-spin"></i> ' + escapeHtml(text('loading', 'Carregando...'))
            : button.dataset.label;
    }

    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return ((parts[0] || '-')[0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
    }

    var menuClient = [
        ['main_section', '', 'Minha area'],
        ['dashboard', 'fa-th-large', 'overview'],
        ['contratos', 'fa-file-text-o', 'contracts'],
        ['locacoes', 'fa-calendar-check-o', 'rentals'],
        ['faturas', 'fa-credit-card', 'invoices'],
        ['multas', 'fa-exclamation-circle', 'fines'],
        ['manutencoes', 'fa-wrench', 'maintenance'],
        ['veiculos', 'fa-car', 'vehicles'],
        ['account_section', '', 'Conta'],
        ['indicacao', 'fa-users', 'referrals'],
        ['perfil', 'fa-user-o', 'my_data']
    ];
    var menuInvestor = [
        ['investment_section', '', 'Meu investimento'],
        ['dashboard', 'fa-th-large', 'overview'],
        ['veiculos', 'fa-car', 'my_vehicles'],
        ['operacoes', 'fa-road', 'operations'],
        ['manutencoes', 'fa-wrench', 'maintenance'],
        ['comissoes', 'fa-money', 'commissions'],
        ['desempenho', 'fa-line-chart', 'performance'],
        ['account_section', '', 'Conta'],
        ['perfil', 'fa-user-o', 'my_data']
    ];

    function buildMenu() {
        var entries = state.profile === 'investidor' ? menuInvestor : menuClient;
        $('#portalNav').innerHTML = entries.map(function (item) {
            if (!item[1]) return '<span class="portal-nav-label">' + escapeHtml(text(item[0], item[2])) + '</span>';
            return '<button type="button" data-page="' + item[0] + '"><i class="fa ' + item[1] + '"></i><span>'
                + escapeHtml(text(item[2], item[2])) + '</span></button>';
        }).join('');
        $('#portalNav').addEventListener('click', function (event) {
            var button = event.target.closest('[data-page]');
            if (!button) return;
            loadPage(button.dataset.page, 1);
            closeSidebar();
        });
    }

    function updateHeader() {
        var name = (state.person && state.person.nome) || boot.name || '';
        $('#profileName').textContent = name;
        $('#profileInitials').textContent = initials(name);
        $('#profileRole').textContent = text(state.profile, state.profile);
        document.querySelectorAll('#portalNav [data-page]').forEach(function (button) {
            button.classList.toggle('active', button.dataset.page === state.page);
        });
        var active = $('#portalNav [data-page="' + state.page + '"] span');
        $('#pageTitle').textContent = active ? active.textContent : text('overview', 'Visao geral');
    }

    function showApp() {
        loginView.hidden = true;
        appView.hidden = false;
        buildMenu();
        updateHeader();
    }

    function showLogin() {
        appView.hidden = true;
        loginView.hidden = false;
        boot.logged = false;
        state.person = null;
        window.scrollTo(0, 0);
    }

    async function initialize() {
        showApp();
        try {
            var session = await request('sessao');
            if (!session.success) throw new Error(session.message || text('load_error', 'Erro ao carregar.'));
            state.person = session.data;
            state.profile = session.data.perfil;
            buildMenu();
            updateHeader();
            await loadPage('dashboard', 1);
        } catch (error) {
            if (!appView.hidden) {
                toast(error.message, 'error');
                content.innerHTML = empty(error.message);
            }
        }
    }

    function empty(message) {
        return '<div class="portal-empty"><i class="fa fa-inbox fa-2x"></i><span>'
            + escapeHtml(message || text('no_records', 'Nenhum registro encontrado.')) + '</span></div>';
    }

    function metric(icon, label, value) {
        return '<article class="portal-metric"><div class="portal-metric-head"><span>' + escapeHtml(label)
            + '</span><span class="portal-metric-icon"><i class="fa ' + icon + '"></i></span></div><strong>'
            + escapeHtml(value) + '</strong></article>';
    }

    function pageIntro(title, subtitle, extra) {
        return '<section class="portal-welcome"><div><span class="portal-eyebrow">'
            + escapeHtml(state.profile === 'investidor' ? text('investor_area', 'Area do investidor') : text('client_area', 'Area do cliente'))
            + '</span><h1>' + escapeHtml(title) + '</h1><p>' + escapeHtml(subtitle || '') + '</p></div>' + (extra || '') + '</section>';
    }

    async function loadPage(page, number) {
        state.page = page;
        state.pageNumber = number || 1;
        updateHeader();
        content.innerHTML = '<div class="portal-loading"><i class="fa fa-circle-o-notch fa-spin"></i><span>'
            + escapeHtml(text('loading', 'Carregando...')) + '</span></div>';
        try {
            if (page === 'dashboard') return renderDashboard();
            if (page === 'perfil') return renderProfile();
            if (page === 'indicacao') return renderReferral();
            return renderResource(page);
        } catch (error) {
            content.innerHTML = empty(error.message);
            toast(error.message, 'error');
        }
    }

    async function renderDashboard() {
        var response = await request('dashboard', {
            query: { data_inicio: state.start, data_fim: state.end }
        });
        if (!response.success) throw new Error(response.message || text('load_error', 'Erro ao carregar.'));
        var totals = response.data.totais || {};
        var name = ((state.person && state.person.nome) || '').split(' ')[0];
        var period = state.profile === 'investidor'
            ? '<div class="portal-period"><label>' + escapeHtml(text('from', 'De')) + '<input id="periodStart" type="date" value="' + state.start + '"></label>'
                + '<label>' + escapeHtml(text('to', 'Ate')) + '<input id="periodEnd" type="date" value="' + state.end + '"></label>'
                + '<button id="applyPeriod" class="portal-secondary">' + escapeHtml(text('apply', 'Aplicar')) + '</button></div>'
            : '<a class="portal-secondary" href="' + escapeHtml(boot.reservation_url) + '"><i class="fa fa-plus"></i> '
                + escapeHtml(text('new_reservation', 'Nova reserva')) + '</a>';
        var html = pageIntro(
            text('hello', 'Ola') + (name ? ', ' + name + '!' : '!'),
            text('dashboard_subtitle', 'Acompanhe aqui as informacoes mais importantes.'),
            period
        );

        if (state.profile === 'investidor') {
            html += '<section class="portal-metrics">'
                + metric('fa-car', text('active_vehicles', 'Veiculos ativos'), totals.veiculos_ativos || 0)
                + metric('fa-line-chart', text('generated_revenue', 'Receita gerada'), money(totals.receita_gerada))
                + metric('fa-clock-o', text('pending_commission', 'Comissao pendente'), money(totals.comissao_pendente))
                + metric('fa-check-circle', text('paid_commission', 'Comissao paga'), money(totals.comissao_paga))
                + metric('fa-money', text('balance', 'Saldo'), money(totals.saldo))
                + metric('fa-wrench', text('open_maintenance', 'Manutencoes abertas'), totals.manutencoes_abertas || 0)
                + '</section>';
            html += '<section class="portal-panel"><div class="portal-panel-head"><h2>'
                + escapeHtml(text('vehicle_performance', 'Desempenho por veiculo')) + '</h2></div>'
                + renderSimpleVehiclePerformance(response.data.veiculos || []) + '</section>';
        } else {
            html += '<section class="portal-metrics">'
                + metric('fa-car', text('vehicles_used', 'Veiculos utilizados'), totals.veiculos || 0)
                + metric('fa-file-text-o', text('open_contracts', 'Contratos abertos'), totals.contratos_abertos || 0)
                + metric('fa-calendar', text('reservations', 'Reservas'), totals.reservas || 0)
                + metric('fa-road', text('rentals', 'Locacoes'), (totals.locacoes_abertas || 0) + (totals.locacoes_fechadas || 0))
                + metric('fa-credit-card', text('open_invoices', 'Faturas abertas'), totals.faturas_abertas || 0)
                + metric('fa-exclamation-circle', text('open_fines', 'Multas abertas'), totals.multas_abertas || 0)
                + metric('fa-wrench', text('maintenance', 'Manutencoes'), totals.manutencoes || 0)
                + metric('fa-money', text('amount_due', 'Valor em aberto'), money(totals.valor_faturas_abertas))
                + '</section>';
            html += '<section class="portal-panel"><div class="portal-panel-head"><h2>'
                + escapeHtml(text('recent_activity', 'Atividades recentes')) + '</h2></div>'
                + renderActivities(response.data.atividades || []) + '</section>';
        }
        content.innerHTML = html;
        var apply = $('#applyPeriod');
        if (apply) apply.addEventListener('click', function () {
            state.start = $('#periodStart').value;
            state.end = $('#periodEnd').value;
            loadPage('dashboard', 1);
        });
    }

    function renderActivities(rows) {
        if (!rows.length) return empty();
        return '<div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>'
            + escapeHtml(text('description', 'Descricao')) + '</th><th>' + escapeHtml(text('date', 'Data'))
            + '</th><th>' + escapeHtml(text('value', 'Valor')) + '</th></tr></thead><tbody>'
            + rows.map(function (row) {
                return '<tr><td><strong>' + escapeHtml(row.titulo) + '</strong><br><small>'
                    + escapeHtml(row.descricao) + '</small></td><td>' + formatDate(row.data) + '</td><td>'
                    + money(row.valor) + '</td></tr>';
            }).join('') + '</tbody></table></div>';
    }

    function renderSimpleVehiclePerformance(rows) {
        if (!rows.length) return empty();
        return '<div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>'
            + escapeHtml(text('vehicle', 'Veiculo')) + '</th><th>' + escapeHtml(text('generated_revenue', 'Receita gerada'))
            + '</th><th>' + escapeHtml(text('pending_commission', 'Comissao')) + '</th></tr></thead><tbody>'
            + rows.map(function (row) {
                return '<tr><td><strong>' + escapeHtml(row.placa || row.veiculo || '—') + '</strong><br><small>'
                    + escapeHtml([row.marca, row.modelo].filter(Boolean).join(' ')) + '</small></td><td>'
                    + money(row.receita_gerada || row.receita || 0) + '</td><td>'
                    + money(row.comissao_devida || row.comissao || 0) + '</td></tr>';
            }).join('') + '</tbody></table></div>';
    }

    var columns = {
        contratos: [['codigo', 'code'], ['data_ini', 'start_date', 'date'], ['data_fim', 'end_date', 'date'], ['total_pagar', 'value', 'money'], ['situacao', 'status', 'badge']],
        locacoes: [['codigo', 'code'], ['data_saida', 'pickup', 'date'], ['data_prevista', 'expected_return', 'date'], ['total_pagar', 'value', 'money'], ['situacao', 'status', 'badge']],
        faturas: [['codigo', 'code'], ['descricao', 'description'], ['data_venci', 'due_date', 'date'], ['valor_total', 'value', 'money'], ['situacao', 'status', 'badge'], ['_actions', 'actions']],
        multas: [['numero_ait', 'notice'], ['placa', 'plate'], ['data_hora', 'date', 'date'], ['valor', 'value', 'money'], ['situacao', 'status', 'badge']],
        manutencoes: [['os', 'work_order'], ['placa', 'plate'], ['motivo', 'reason'], ['data_enviado', 'start_date', 'date'], ['total_servicos', 'value', 'money'], ['situacao', 'status', 'badge']],
        veiculos: [['placa', 'plate'], ['marca', 'brand'], ['modelo', 'model'], ['ano', 'year'], ['cor', 'color'], ['disponibilidade', 'availability']],
        operacoes: [['tipo', 'type'], ['placa', 'plate'], ['data_saida', 'pickup', 'date'], ['data_entrada', 'return_date', 'date'], ['dias_ocupados', 'occupied_days'], ['status', 'status', 'badge']],
        comissoes: [['data_referencia', 'reference_date', 'date'], ['placa', 'plate'], ['tipo_origem', 'origin'], ['valor_base', 'base_value', 'money'], ['valor_repasse_investidor', 'commission', 'money'], ['status', 'status', 'badge']],
        desempenho: []
    };

    async function renderResource(resource) {
        var response = await request(resource, {
            query: { page: state.pageNumber, per_page: 20, data_inicio: state.start, data_fim: state.end }
        });
        if (!response.success) throw new Error(response.message || text('load_error', 'Erro ao carregar.'));
        if (resource === 'desempenho') {
            var report = (response.data || [])[0] || {};
            content.innerHTML = pageIntro(text('performance', 'Desempenho'), text('performance_subtitle', 'Resultados do periodo selecionado.'))
                + '<section class="portal-panel">' + renderSimpleVehiclePerformance(report.veiculos || []) + '</section>';
            return;
        }
        var title = text(resource, resource);
        var rows = response.data || [];
        var html = pageIntro(title, text(resource + '_subtitle', text('records_subtitle', 'Consulte seus registros.')));
        html += '<section class="portal-panel"><div class="portal-panel-head"><h2>' + escapeHtml(title) + '</h2></div>';
        html += renderTable(resource, rows);
        html += renderPagination(response.pagination);
        html += '</section>';
        content.innerHTML = html;
        bindTableActions();
        bindPagination(resource);
    }

    function renderTable(resource, rows) {
        if (!rows.length) return empty();
        var defs = columns[resource] || [];
        return '<div class="portal-table-wrap"><table class="portal-table"><thead><tr>'
            + defs.map(function (col) { return '<th>' + escapeHtml(text(col[1], col[1])) + '</th>'; }).join('')
            + '</tr></thead><tbody>' + rows.map(function (row) {
                return '<tr>' + defs.map(function (col) {
                    var value = row[col[0]];
                    if (col[0] === '_actions') return '<td>' + invoiceActions(row) + '</td>';
                    if (col[2] === 'date') value = formatDate(value);
                    else if (col[2] === 'money') value = money(value);
                    else if (col[2] === 'badge') return '<td><span class="portal-badge ' + escapeHtml(String(value || '').toLowerCase()) + '">' + escapeHtml(value || '—') + '</span></td>';
                    return '<td>' + escapeHtml(value == null || value === '' ? '—' : value) + '</td>';
                }).join('') + '</tr>';
            }).join('') + '</tbody></table></div>';
    }

    function invoiceActions(row) {
        var html = '';
        if (row.pode_pagar) html += '<button class="portal-action" data-pay="' + Number(row.id) + '">' + escapeHtml(text('pay', 'Pagar')) + '</button>';
        if (row.pode_emitir_recibo) html += '<a class="portal-action" target="_blank" rel="noopener" href="portal-recibo.php?id='
            + Number(row.id) + '">' + escapeHtml(text('receipt', 'Recibo')) + '</a>';
        return html || '—';
    }

    function renderPagination(page) {
        if (!page || page.last_page <= 1) return '';
        return '<div class="portal-pagination"><button data-page-number="' + (page.page - 1) + '" '
            + (page.page <= 1 ? 'disabled' : '') + '><i class="fa fa-angle-left"></i></button><span>'
            + page.page + ' / ' + page.last_page + '</span><button data-page-number="' + (page.page + 1) + '" '
            + (page.page >= page.last_page ? 'disabled' : '') + '><i class="fa fa-angle-right"></i></button></div>';
    }

    function bindPagination(resource) {
        document.querySelectorAll('[data-page-number]').forEach(function (button) {
            button.addEventListener('click', function () { loadPage(resource, Number(button.dataset.pageNumber)); });
        });
    }

    function bindTableActions() {
        document.querySelectorAll('[data-pay]').forEach(function (button) {
            button.addEventListener('click', async function () {
                setBusy(button, true);
                try {
                    var result = await request('link-pagamento', { method: 'POST', body: { id: Number(button.dataset.pay) } });
                    if (!result.success || !result.url) throw new Error(result.message || text('payment_error', 'Nao foi possivel abrir o pagamento.'));
                    window.open(result.url, '_blank', 'noopener');
                } catch (error) {
                    toast(error.message, 'error');
                } finally {
                    setBusy(button, false);
                }
            });
        });
    }

    async function renderReferral() {
        var response = await request('indicacao');
        if (!response.success) throw new Error(response.message || text('load_error', 'Erro ao carregar.'));
        var data = (response.data || [])[0] || {};
        var link = new URL(boot.referral_base + encodeURIComponent(data.codigo || ''), window.location.href).href;
        content.innerHTML = pageIntro(text('referrals', 'Indicacoes'), text('referral_subtitle', 'Compartilhe seu link pessoal.'))
            + '<section class="portal-referral"><h2>' + escapeHtml(text('your_referral_link', 'Seu link de indicacao')) + '</h2><p>'
            + escapeHtml(text('referral_note', 'As regras de beneficio serao divulgadas pela locadora.')) + '</p><div class="portal-referral-code">'
            + '<input id="referralLink" readonly value="' + escapeHtml(link) + '"><button id="copyReferral"><i class="fa fa-copy"></i> '
            + escapeHtml(text('copy', 'Copiar')) + '</button></div><p>' + escapeHtml(text('clicks', 'Cliques')) + ': '
            + Number(data.cliques || 0) + ' · ' + escapeHtml(text('conversions', 'Conversoes')) + ': ' + Number(data.conversoes || 0) + '</p></section>';
        $('#copyReferral').addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(link);
                toast(text('copied', 'Link copiado.'), 'success');
            } catch (error) {
                $('#referralLink').select();
                document.execCommand('copy');
                toast(text('copied', 'Link copiado.'), 'success');
            }
        });
    }

    async function renderProfile() {
        if (!state.person) {
            var session = await request('sessao');
            if (!session.success) throw new Error(session.message);
            state.person = session.data;
        }
        var p = state.person;
        var readonly = p.campos_somente_leitura || [];
        var fields = [
            ['nome', 'name'], ['cpf_cnpj', 'document'], ['email', 'email'], ['telefone', 'phone'],
            ['cep', 'zip'], ['rua', 'street'], ['numero', 'number'], ['complemento', 'complement'],
            ['bairro', 'neighborhood'], ['cidade', 'city'], ['estado', 'state'], ['pais', 'country']
        ];
        if (state.profile === 'investidor') fields.splice(4, 0, ['telefone_secundario', 'secondary_phone']);
        var form = fields.map(function (field) {
            return '<label>' + escapeHtml(text(field[1], field[1])) + '<input name="' + field[0] + '" value="'
                + escapeHtml(p[field[0]] || '') + '" ' + (readonly.indexOf(field[0]) >= 0 ? 'readonly' : '') + '></label>';
        }).join('');
        content.innerHTML = pageIntro(text('my_data', 'Meus dados'), text('profile_subtitle', 'Mantenha seus dados de contato atualizados.'))
            + '<section class="portal-panel"><form id="profileForm"><div class="portal-form-grid">' + form
            + '</div><div class="portal-form-actions"><button class="portal-primary" type="submit">'
            + escapeHtml(text('save_changes', 'Salvar alteracoes')) + '</button></div></form></section>'
            + '<section class="portal-panel"><div class="portal-panel-head"><h2>' + escapeHtml(text('change_password', 'Alterar senha'))
            + '</h2></div><form id="passwordForm"><div class="portal-form-grid"><label>'
            + escapeHtml(text('current_password', 'Senha atual')) + '<input name="senha_atual" type="password" autocomplete="current-password" required></label><label>'
            + escapeHtml(text('new_password', 'Nova senha')) + '<input name="nova_senha" type="password" minlength="8" autocomplete="new-password" required></label></div>'
            + '<div class="portal-form-actions"><button class="portal-primary" type="submit">' + escapeHtml(text('change_password', 'Alterar senha')) + '</button></div></form></section>';
        $('#profileForm').addEventListener('submit', saveProfile);
        $('#passwordForm').addEventListener('submit', changePassword);
    }

    async function saveProfile(event) {
        event.preventDefault();
        var button = event.currentTarget.querySelector('button[type="submit"]');
        setBusy(button, true);
        var body = Object.fromEntries(new FormData(event.currentTarget).entries());
        try {
            var result = await request('perfil', { method: 'PUT', body: body });
            if (!result.success) throw new Error(result.message || text('save_error', 'Nao foi possivel salvar.'));
            state.person = result.data;
            updateHeader();
            toast(result.message || text('saved', 'Dados atualizados.'), 'success');
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            setBusy(button, false);
        }
    }

    async function changePassword(event) {
        event.preventDefault();
        var button = event.currentTarget.querySelector('button[type="submit"]');
        setBusy(button, true);
        var body = Object.fromEntries(new FormData(event.currentTarget).entries());
        try {
            var result = await request('senha', { method: 'POST', body: body });
            if (!result.success) throw new Error(result.message || text('save_error', 'Nao foi possivel salvar.'));
            toast(result.message, 'success');
            setTimeout(logout, 900);
        } catch (error) {
            toast(error.message, 'error');
            setBusy(button, false);
        }
    }

    function openSidebar() {
        $('#sidebar').classList.add('open');
        $('#sidebarBackdrop').classList.add('open');
    }

    function closeSidebar() {
        $('#sidebar').classList.remove('open');
        $('#sidebarBackdrop').classList.remove('open');
    }

    async function logout() {
        try {
            await fetch('ajax-portal-logout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _csrf: state.csrf })
            });
        } finally {
            window.location.reload();
        }
    }

    var selectedRole = 'cliente';
    document.querySelectorAll('.portal-role').forEach(function (button) {
        button.addEventListener('click', function () {
            selectedRole = button.dataset.role;
            document.querySelectorAll('.portal-role').forEach(function (item) {
                var active = item === button;
                item.classList.toggle('active', active);
                item.setAttribute('aria-selected', String(active));
            });
        });
    });

    $('#loginForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        var button = event.currentTarget.querySelector('button[type="submit"]');
        setBusy(button, true);
        try {
            var response = await fetch('ajax-portal-login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    _csrf: state.csrf,
                    perfil: selectedRole,
                    usuario: $('#loginUser').value,
                    senha: $('#loginPassword').value
                })
            });
            var result = await response.json();
            if (!result.success) throw new Error(result.message || text('login_error', 'Credenciais invalidas.'));
            state.profile = result.data.perfil;
            state.csrf = result.data.csrf;
            boot.name = result.data.nome || '';
            boot.logged = true;
            await initialize();
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            setBusy(button, false);
        }
    });

    $('#togglePassword').addEventListener('click', function () {
        var input = $('#loginPassword');
        input.type = input.type === 'password' ? 'text' : 'password';
        this.querySelector('i').className = input.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
    });

    $('#forgotPassword').addEventListener('click', function () { $('#forgotModal').hidden = false; });
    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
        button.addEventListener('click', function () { $('#forgotModal').hidden = true; });
    });
    $('#sendReset').addEventListener('click', async function () {
        var user = $('#loginUser').value.trim();
        if (!user) {
            toast(text('enter_user_first', 'Informe seu e-mail ou CPF/CNPJ.'), 'error');
            return;
        }
        setBusy(this, true);
        try {
            var response = await fetch('ajax-portal-login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ _csrf: state.csrf, acao: 'reset', perfil: selectedRole, usuario: user })
            });
            var result = await response.json();
            toast(result.message || text('reset_sent', 'Verifique seu e-mail.'), result.success ? 'success' : 'error');
            if (result.success) $('#forgotModal').hidden = true;
        } catch (error) {
            toast(text('network_error', 'Erro de comunicacao.'), 'error');
        } finally {
            setBusy(this, false);
        }
    });

    $('#openSidebar').addEventListener('click', openSidebar);
    $('#closeSidebar').addEventListener('click', closeSidebar);
    $('#sidebarBackdrop').addEventListener('click', closeSidebar);
    $('#logoutButton').addEventListener('click', logout);

    if (boot.logged) initialize();
}());
