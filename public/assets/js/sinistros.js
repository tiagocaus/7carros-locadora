(function () {
    'use strict';

    const config = window.SINISTROS_CONFIG || null;
    const root = document.getElementById('sinistrosApp');
    if (!config || !root) return;

    const t = config.i18n || {};
    const types = {
        colisao: t.types?.collision || 'Colisão/acidente',
        furto_roubo: t.types?.theft || 'Furto/roubo',
        incendio: t.types?.fire || 'Incêndio',
        alagamento: t.types?.flood || 'Alagamento',
        danos_terceiros: t.types?.third_party || 'Danos a terceiros',
        perda_total: t.types?.total_loss || 'Perda total',
        outros: t.types?.other || 'Outros'
    };
    let items = [];
    let vehicles = [];
    let loadedFor = null;

    const el = id => document.getElementById(id);
    const recordId = () => parseInt(el('registroId')?.value || 0, 10);
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const money = value => window.Currency ? Currency.format(parseFloat(value) || 0, true) : Number(value || 0).toFixed(2);
    const parseMoney = value => window.Currency ? Currency.parse(value || '') : parseFloat(value || 0);
    const alertMessage = message => window.parent.postMessage({ action: 'openAlert', message }, '*');

    function localNow() {
        const date = new Date();
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        return date.toISOString().slice(0, 16);
    }

    function formatDate(value) {
        if (!value) return '-';
        const raw = String(value).replace(' ', 'T');
        if (window.DateHelper?.formatOperationalDateTime) return DateHelper.formatOperationalDateTime(raw);
        return raw.slice(0, 16).replace('T', ' ');
    }

    function setChosenValue(select, value, label) {
        if (!select) return;
        if (value && !Array.from(select.options).some(option => option.value === String(value))) {
            select.add(new Option(label || String(value), String(value), true, true));
        }
        select.value = value ? String(value) : '';
        select.chosenSelect?.refresh();
    }

    function renderVehicles(selected = '') {
        const select = el('sinistro_id_veiculo');
        if (!select) return;
        const unique = new Map();
        vehicles.forEach(vehicle => unique.set(String(vehicle.id), vehicle));
        select.innerHTML = '<option value=""></option>' + Array.from(unique.values()).map(vehicle => {
            const label = [vehicle.placa, vehicle.marca, vehicle.modelo].filter(Boolean).join(' - ');
            return `<option value="${vehicle.id}" ${String(vehicle.id) === String(selected) ? 'selected' : ''}>${escapeHtml(label)}</option>`;
        }).join('');
    }

    function render() {
        const list = el('sinistrosLista');
        if (!list) return;
        if (!items.length) {
            list.innerHTML = `<div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">${escapeHtml(t.empty || 'Nenhum sinistro registrado.')}</div>`;
            return;
        }

        list.innerHTML = items.map(item => {
            const charged = !!item.id_financeiro;
            const chargeLabel = charged
                ? `${item.financeiro_pago === 'S' ? (t.charge?.paid || 'Paga') : (t.charge?.pending || 'Pendente')} · ${money(item.financeiro_valor)}`
                : (t.not_generated || 'Não gerada');
            const statusClass = item.status === 'C' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700';
            const statusLabel = item.status === 'C' ? (t.status?.completed || 'Concluído') : (t.status?.open || 'Aberto');
            const vehicle = [item.veiculo_placa, item.veiculo_marca, item.veiculo_modelo].filter(Boolean).join(' - ');
            const canDelete = config.canDelete && (!charged || config.canDeleteFinance);
            return `<div class="rounded-lg border border-slate-200 bg-white p-4" data-sinistro-id="${item.id}">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                    <div class="md:col-span-2"><div class="text-xs text-slate-500">${escapeHtml(t.fields?.date || 'Data')}</div><div class="text-sm font-medium">${escapeHtml(formatDate(item.data_ocorrencia))}</div></div>
                    <div class="md:col-span-2"><div class="text-xs text-slate-500">${escapeHtml(t.fields?.vehicle || 'Veículo')}</div><div class="text-sm font-medium">${escapeHtml(vehicle)}</div></div>
                    <div class="md:col-span-2"><div class="text-xs text-slate-500">${escapeHtml(t.fields?.type || 'Tipo')}</div><div class="text-sm">${escapeHtml(types[item.tipo] || item.tipo)}</div></div>
                    <div class="md:col-span-2"><div class="text-xs text-slate-500">${escapeHtml(t.fields?.estimated_value || 'Valor estimado')}</div><div class="text-sm">${item.valor_estimado !== null ? money(item.valor_estimado) : '-'}</div></div>
                    <div class="md:col-span-2"><div class="text-xs text-slate-500">${escapeHtml(t.fields?.charge || 'Cobrança')}</div><div class="text-sm">${escapeHtml(chargeLabel)}</div></div>
                    <div class="md:col-span-2 flex md:justify-end items-center gap-2"><span class="rounded-full px-2 py-1 text-xs font-medium ${statusClass}">${escapeHtml(statusLabel)}</span></div>
                </div>
                <div class="mt-3 border-t border-slate-100 pt-3 flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="text-sm text-slate-700"><strong>${escapeHtml(item.descricao)}</strong>${item.observacoes ? `<div class="mt-1 text-slate-500">${escapeHtml(item.observacoes)}</div>` : ''}</div>
                    <div class="flex flex-wrap gap-2 shrink-0">
                        <button type="button" class="btn-secondary rounded-md px-3 py-1.5 text-xs btn-editar-sinistro" data-id="${item.id}"><i class="fas fa-edit mr-1"></i>${escapeHtml(t.edit_action || 'Editar')}</button>
                        ${!charged && config.canCreateFinance ? `<button type="button" class="btn-secondary rounded-md px-3 py-1.5 text-xs btn-cobrar-sinistro" data-id="${item.id}"><i class="fas fa-file-invoice-dollar mr-1"></i>${escapeHtml(t.generate_charge_action || 'Gerar cobrança')}</button>` : ''}
                        ${charged ? `<button type="button" class="btn-secondary rounded-md px-3 py-1.5 text-xs btn-ver-cobranca" data-id="${item.id_financeiro}"><i class="fas fa-external-link-alt mr-1"></i>${escapeHtml(t.view_charge || 'Ver cobrança')}</button>` : ''}
                        ${canDelete ? `<button type="button" class="btn-secondary rounded-md px-3 py-1.5 text-xs text-red-600 btn-excluir-sinistro" data-id="${item.id}"><i class="fas fa-trash-alt mr-1"></i>${escapeHtml(t.delete_action || 'Excluir')}</button>` : ''}
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    async function load(force = false) {
        const id = recordId();
        el('sinistrosEstadoNovo')?.classList.toggle('hidden', id > 0);
        el('btnNovoSinistro')?.classList.toggle('hidden', id <= 0);
        if (!id) {
            el('sinistrosLista').innerHTML = '';
            return;
        }
        if (!force && loadedFor === id) return;
        el('sinistrosCarregando')?.classList.remove('hidden');
        try {
            const result = await API.get('/api/sinistros', { vinculo: config.vinculo, id_vinculo: id });
            if (!result.success) throw new Error(result.message || t.load_error);
            items = result.data || [];
            vehicles = result.veiculos || [];
            loadedFor = id;
            renderVehicles();
            render();
        } catch (error) {
            alertMessage(error.message || t.load_error || 'Não foi possível carregar os sinistros.');
        } finally {
            el('sinistrosCarregando')?.classList.add('hidden');
        }
    }

    function resetEditor() {
        el('sinistroEditor').dataset.mode = 'new';
        el('sinistro_id').value = '';
        el('sinistro_data_ocorrencia').value = localNow();
        el('sinistro_tipo').value = '';
        el('sinistro_descricao').value = '';
        el('sinistro_valor_estimado').value = '';
        el('sinistro_observacoes').value = '';
        el('sinistro_status').value = 'A';
        renderVehicles();
        el('sinistroCamposCadastro').querySelectorAll('input, select, textarea').forEach(input => input.disabled = false);
        el('sinistroGerarCobrancaLabel').classList.toggle('hidden', !config.canCreateFinance);
        el('sinistro_gerar_cobranca').checked = false;
        el('sinistroCobrancaCampos').classList.add('hidden');
        clearCharge();
    }

    function clearCharge() {
        el('sinistro_cobranca_valor').value = '';
        el('sinistro_cobranca_vencimento').value = '';
        setChosenValue(el('sinistro_cobranca_conta'), '');
        setChosenValue(el('sinistro_cobranca_forma'), '');
    }

    function openNew() {
        resetEditor();
        el('sinistroEditorTitulo').textContent = t.register || 'Registrar sinistro';
        el('sinistroEditor').classList.remove('hidden');
        el('sinistro_data_ocorrencia').focus();
    }

    function openEdit(item) {
        resetEditor();
        el('sinistroEditor').dataset.mode = 'edit';
        el('sinistroEditorTitulo').textContent = t.edit || 'Editar sinistro';
        el('sinistro_id').value = item.id;
        el('sinistro_data_ocorrencia').value = String(item.data_ocorrencia || '').replace(' ', 'T').slice(0, 16);
        renderVehicles(item.id_veiculo);
        el('sinistro_tipo').value = item.tipo || '';
        el('sinistro_descricao').value = item.descricao || '';
        el('sinistro_valor_estimado').value = item.valor_estimado !== null ? Currency.format(item.valor_estimado, false) : '';
        el('sinistro_observacoes').value = item.observacoes || '';
        el('sinistro_status').value = item.status || 'A';
        el('sinistroGerarCobrancaLabel').classList.add('hidden');
        el('sinistroCobrancaCampos').classList.add('hidden');
        el('sinistroEditor').classList.remove('hidden');
    }

    function openCharge(item) {
        openEdit(item);
        el('sinistroEditor').dataset.mode = 'charge';
        el('sinistroEditorTitulo').textContent = t.charge_title || 'Gerar cobrança do sinistro';
        el('sinistroCamposCadastro').querySelectorAll('input, select, textarea').forEach(input => input.disabled = true);
        el('sinistroGerarCobrancaLabel').classList.add('hidden');
        el('sinistroCobrancaCampos').classList.remove('hidden');
        el('sinistro_cobranca_valor').value = item.valor_estimado !== null ? Currency.format(item.valor_estimado, false) : '';
    }

    function basePayload() {
        return {
            vinculo: config.vinculo,
            id_vinculo: recordId(),
            id_veiculo: parseInt(el('sinistro_id_veiculo').value || 0, 10),
            data_ocorrencia: el('sinistro_data_ocorrencia').value,
            tipo: el('sinistro_tipo').value,
            descricao: el('sinistro_descricao').value.trim(),
            valor_estimado: el('sinistro_valor_estimado').value.trim() === ''
                ? null
                : parseMoney(el('sinistro_valor_estimado').value),
            observacoes: el('sinistro_observacoes').value.trim(),
            status: el('sinistro_status').value
        };
    }

    function chargePayload() {
        return {
            valor: parseMoney(el('sinistro_cobranca_valor').value),
            data_venci: el('sinistro_cobranca_vencimento').value,
            id_conta: parseInt(el('sinistro_cobranca_conta').value || 0, 10),
            id_forma_pagamento: parseInt(el('sinistro_cobranca_forma').value || 0, 10)
        };
    }

    async function save() {
        const button = el('btnSalvarSinistro');
        const mode = el('sinistroEditor').dataset.mode || 'new';
        const id = parseInt(el('sinistro_id').value || 0, 10);
        button.disabled = true;
        try {
            let result;
            if (mode === 'charge') {
                const charge = chargePayload();
                if (!charge.valor || !charge.data_venci || !charge.id_conta || !charge.id_forma_pagamento) throw new Error(t.charge_required);
                result = await API.post(`/api/sinistros/${id}/gerar-cobranca`, charge);
            } else {
                const payload = basePayload();
                if (!payload.id_veiculo || !payload.data_ocorrencia || !payload.tipo || !payload.descricao) throw new Error(t.required);
                if (mode === 'new' && el('sinistro_gerar_cobranca').checked) {
                    payload.gerar_cobranca = true;
                    payload.cobranca = chargePayload();
                    if (!payload.cobranca.valor || !payload.cobranca.data_venci || !payload.cobranca.id_conta || !payload.cobranca.id_forma_pagamento) throw new Error(t.charge_required);
                }
                result = mode === 'edit' ? await API.put(`/api/sinistros/${id}`, payload) : await API.post('/api/sinistros', payload);
            }
            if (!result.success) throw new Error(result.message || (mode === 'charge' ? t.charge_error : t.save_error));
            el('sinistroEditor').classList.add('hidden');
            alertMessage(result.message || (mode === 'charge' ? t.charge_created : t.saved));
            await load(true);
        } catch (error) {
            alertMessage(error.message || (mode === 'charge' ? t.charge_error : t.save_error));
        } finally {
            button.disabled = false;
        }
    }

    function requestDelete(item) {
        if (item.id_financeiro && item.financeiro_pago === 'S') {
            alertMessage(t.delete_paid_blocked || 'Estorne a cobrança paga antes de excluir o sinistro.');
            return;
        }

        const recordName = `#${item.id}${item.id_financeiro ? ` — ${t.delete_with_charge || 'a cobrança vinculada também será excluída'}` : ''}`;
        window.parent.postMessage({
            action: 'openDeleteModal',
            recordId: item.id,
            recordName,
            recordType: t.delete_record_type || 'sinistro',
            confirmType: 'text',
            customAction: 'deleteSinistro'
        }, '*');
    }

    async function deleteRecord(id) {
        try {
            const result = await API.post(`/api/sinistros/${id}`, { _method: 'DELETE' });
            if (!result.success) throw new Error(result.message || t.delete_error);
            if (String(el('sinistro_id')?.value || '') === String(id)) {
                el('sinistroEditor')?.classList.add('hidden');
            }
            alertMessage(result.message || t.deleted || 'Sinistro excluído com sucesso.');
            await load(true);
        } catch (error) {
            alertMessage(error.message || t.delete_error || 'Não foi possível excluir o sinistro.');
        }
    }

    el('btnNovoSinistro')?.addEventListener('click', openNew);
    el('btnCancelarSinistro')?.addEventListener('click', () => el('sinistroEditor').classList.add('hidden'));
    el('btnSalvarSinistro')?.addEventListener('click', save);
    el('sinistro_gerar_cobranca')?.addEventListener('change', event => {
        el('sinistroCobrancaCampos').classList.toggle('hidden', !event.target.checked);
        if (event.target.checked && !el('sinistro_cobranca_valor').value) el('sinistro_cobranca_valor').value = el('sinistro_valor_estimado').value;
    });
    el('sinistrosLista')?.addEventListener('click', event => {
        const button = event.target.closest('button[data-id]');
        if (!button) return;
        if (button.classList.contains('btn-ver-cobranca')) {
            window.parent.openOrSwitchToTab?.(`/pages/financeiro/adicionar?id=${button.dataset.id}`, t.view_charge || 'Ver cobrança', 'fas fa-file-invoice-dollar');
            return;
        }
        const item = items.find(row => String(row.id) === String(button.dataset.id));
        if (!item) return;
        if (button.classList.contains('btn-editar-sinistro')) openEdit(item);
        if (button.classList.contains('btn-cobrar-sinistro')) openCharge(item);
        if (button.classList.contains('btn-excluir-sinistro')) requestDelete(item);
    });
    window.addEventListener('message', event => {
        if (event.data?.action === 'confirmDelete' && event.data.customAction === 'deleteSinistro') {
            deleteRecord(event.data.recordId);
        }
    });
    document.querySelector('[data-form-tab-target="#tabSinistros"]')?.addEventListener('click', () => load());
    el('sinistro_valor_estimado') && Currency.applyMask(el('sinistro_valor_estimado'));
    el('sinistro_cobranca_valor') && Currency.applyMask(el('sinistro_cobranca_valor'));
    load();
})();
