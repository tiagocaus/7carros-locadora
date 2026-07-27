@extends('layouts.iframe')

@section('title', t('modules.veiculos.fraction_adjustment.title'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
        <h2 class="title-section mb-0">{{ t('modules.veiculos.fraction_adjustment.title') }}</h2>
        <button type="button" id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium whitespace-nowrap">
            <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i>{{ t('modules.veiculos.fraction_adjustment.cancel') }}
        </button>
    </div>

    <div class="bg-white shadow-md rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="form-input-group">
                <label for="filialSelect" class="form-label-group">{{ t('modules.veiculos.fraction_adjustment.branch') }}</label>
                <select id="filialSelect"
                    class="form-input-group-field chosen-select"
                    data-chosen-type="server-side"
                    data-chosen-search-url="/api/matrizes-filiais/buscar"
                    data-chosen-placeholder="{{ t('modules.veiculos.placeholders.search_select') }}"
                    data-chosen-allow-clear="false"
                    required>
                    <option value="">{{ t('modules.veiculos.placeholders.select') }}</option>
                    <?php if (!empty($filialSelecionada)): ?>
                        <option value="<?= (int) $filialSelecionada['id'] ?>" selected>
                            <?= htmlspecialchars((string) $filialSelecionada['nome'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-input-group">
                <label for="grupoFilter" class="form-label-group">{{ t('modules.veiculos.fraction_adjustment.group_filter') }}</label>
                <select id="grupoFilter"
                    class="form-input-group-field chosen-select"
                    data-chosen-type="server-side"
                    data-chosen-search-url="/api/grupos"
                    data-chosen-placeholder="{{ t('modules.veiculos.fraction_adjustment.all_groups') }}">
                    <option value="">{{ t('modules.veiculos.fraction_adjustment.all_groups') }}</option>
                </select>
            </div>
            <div class="form-input-group">
                <label for="searchInput" class="form-label-group">{{ t('modules.veiculos.fraction_adjustment.search') }}</label>
                <div class="relative">
                    <input type="text" id="searchInput" class="form-input-group-field pr-9" placeholder="{{ t('modules.veiculos.fraction_adjustment.search_placeholder') }}" autocomplete="off">
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg p-4 mb-4">
        <h3 class="font-semibold text-slate-800 mb-4">
            <i class="fas fa-calculator mr-2 text-blue-600" aria-hidden="true"></i>{{ t('modules.veiculos.fraction_adjustment.bulk_title') }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <div class="form-input-group md:col-span-3">
                <label for="adjustmentValue" class="form-label-group">
                    {{ t('modules.veiculos.fraction_adjustment.adjustment_value') }}
                    {!! aviso(t('modules.veiculos.fraction_adjustment.adjustment_hint')) !!}
                </label>
                <input type="text" id="adjustmentValue" class="form-input-group-field" inputmode="decimal" autocomplete="off">
            </div>
            <div class="form-input-group md:col-span-4">
                <label for="adjustmentType" class="form-label-group">{{ t('modules.veiculos.fraction_adjustment.adjustment_type') }}</label>
                <select id="adjustmentType" class="form-input-group-field">
                    <option value="increase_percent">{{ t('modules.veiculos.fraction_adjustment.types.increase_percent') }}</option>
                    <option value="decrease_percent">{{ t('modules.veiculos.fraction_adjustment.types.decrease_percent') }}</option>
                    <option value="add_value">{{ t('modules.veiculos.fraction_adjustment.types.add_value') }}</option>
                    <option value="subtract_value">{{ t('modules.veiculos.fraction_adjustment.types.subtract_value') }}</option>
                    <option value="set_value">{{ t('modules.veiculos.fraction_adjustment.types.set_value') }}</option>
                </select>
            </div>
            <div class="md:col-span-5 flex flex-wrap gap-2">
                <button type="button" id="btnCalcular" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-calculator mr-2" aria-hidden="true"></i>{{ t('modules.veiculos.fraction_adjustment.calculate') }}
                </button>
                <button type="button" id="btnLimpar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-eraser mr-2" aria-hidden="true"></i>{{ t('modules.veiculos.fraction_adjustment.clear') }}
                </button>
            </div>
        </div>
        <div id="filteredInfo" class="mt-3 text-sm text-slate-500" aria-live="polite"></div>
    </div>

    <div id="vehiclesContainer" aria-live="polite">
        <div class="bg-white shadow-md rounded-lg p-8 text-center text-slate-500">
            <i class="fas fa-spinner fa-spin mr-2" aria-hidden="true"></i>{{ t('modules.veiculos.fraction_adjustment.loading') }}
        </div>
    </div>

    <div class="sticky bottom-0 bg-white border-t border-slate-200 shadow-lg mt-4 px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-3">
        <span id="changedInfo" class="text-sm font-medium text-slate-600" aria-live="polite"></span>
        <button type="button" id="btnSalvar" class="btn-blue py-2 px-5 rounded-md text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <i class="fas fa-save mr-2" aria-hidden="true"></i>{{ t('modules.veiculos.fraction_adjustment.save') }}
        </button>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const i18n = {
        allGroups: <?= js_t('modules.veiculos.fraction_adjustment.all_groups') ?>,
        currentValue: <?= js_t('modules.veiculos.fraction_adjustment.current_value') ?>,
        newValue: <?= js_t('modules.veiculos.fraction_adjustment.new_value') ?>,
        plate: <?= js_t('modules.veiculos.fields.plate') ?>,
        brandModel: <?= js_t('modules.veiculos.fields.brand_model') ?>,
        vehiclesCount: <?= js_t('modules.veiculos.fraction_adjustment.vehicles_count') ?>,
        filteredCount: <?= js_t('modules.veiculos.fraction_adjustment.filtered_count') ?>,
        changedCount: <?= js_t('modules.veiculos.fraction_adjustment.changed_count') ?>,
        noVehicles: <?= js_t('modules.veiculos.fraction_adjustment.no_vehicles') ?>,
        loading: <?= js_t('modules.veiculos.fraction_adjustment.loading') ?>,
        confirmTitle: <?= js_t('modules.veiculos.fraction_adjustment.confirm_title') ?>,
        confirmMessage: <?= js_t('modules.veiculos.fraction_adjustment.confirm_message') ?>,
        confirmButton: <?= js_t('modules.veiculos.fraction_adjustment.confirm_button') ?>,
        branchChangeTitle: <?= js_t('modules.veiculos.fraction_adjustment.branch_change_title') ?>,
        branchChangeMessage: <?= js_t('modules.veiculos.fraction_adjustment.branch_change_message') ?>,
        branchChangeButton: <?= js_t('modules.veiculos.fraction_adjustment.branch_change_button') ?>,
        adjustmentRequired: <?= js_t('modules.veiculos.fraction_adjustment.messages.adjustment_required') ?>,
        percentLimit: <?= js_t('modules.veiculos.fraction_adjustment.messages.percent_limit') ?>,
        valueLimit: <?= js_t('modules.veiculos.fraction_adjustment.messages.value_limit') ?>,
        noFiltered: <?= js_t('modules.veiculos.fraction_adjustment.messages.no_filtered') ?>,
        noChanges: <?= js_t('modules.veiculos.fraction_adjustment.messages.no_changes') ?>,
        loadError: <?= js_t('modules.veiculos.fraction_adjustment.messages.load_error') ?>,
        saveError: <?= js_t('modules.veiculos.fraction_adjustment.messages.save_error') ?>
    };

    const filialSelect = document.getElementById('filialSelect');
    const grupoFilter = document.getElementById('grupoFilter');
    const searchInput = document.getElementById('searchInput');
    let adjustmentValue = document.getElementById('adjustmentValue');
    const adjustmentType = document.getElementById('adjustmentType');
    const vehiclesContainer = document.getElementById('vehiclesContainer');
    const filteredInfo = document.getElementById('filteredInfo');
    const changedInfo = document.getElementById('changedInfo');
    const btnSalvar = document.getElementById('btnSalvar');

    let grupos = [];
    let filialCarregadaId = Number(filialSelect?.value || 0);
    let pendingSaveConfirmation = false;
    let pendingBranchId = null;
    let saving = false;

    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page }, '*');
        } else {
            window.location.href = page;
        }
    }

    function mostrarAlerta(message, type) {
        window.parent.postMessage({ action: 'openAlert', message, type: type || undefined }, '*');
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function normalizar(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    }

    function formatTemplate(template, count) {
        return String(template).replace(':count', String(count));
    }

    function allVehicles() {
        return grupos.flatMap(group => group.veiculos);
    }

    function filteredGroups() {
        const selectedGroup = grupoFilter.value;
        const term = normalizar(searchInput.value);

        return grupos.map(group => {
            const groupKey = group.id === null ? 'sem-grupo' : String(group.id);
            if (selectedGroup && selectedGroup !== groupKey) {
                return { ...group, veiculos: [] };
            }

            const veiculos = group.veiculos.filter(vehicle => {
                if (!term) return true;
                return normalizar(`${vehicle.placa} ${vehicle.marca} ${vehicle.modelo}`).includes(term);
            });

            return { ...group, veiculos };
        }).filter(group => group.veiculos.length > 0);
    }

    function changedVehicles() {
        return allVehicles().filter(vehicle => {
            if (vehicle.novoValor === null) return false;
            return Math.round(vehicle.novoValor * 100) !== Math.round(vehicle.valorAtual * 100);
        });
    }

    function updateSummary() {
        const filteredTotal = filteredGroups().reduce((total, group) => total + group.veiculos.length, 0);
        const changedTotal = changedVehicles().length;
        filteredInfo.textContent = formatTemplate(i18n.filteredCount, filteredTotal);
        changedInfo.textContent = formatTemplate(i18n.changedCount, changedTotal);
        btnSalvar.disabled = changedTotal === 0 || saving;
    }

    function configureAdjustmentMask() {
        const replacement = adjustmentValue.cloneNode(false);
        replacement.value = '';
        adjustmentValue.replaceWith(replacement);
        adjustmentValue = replacement;

        const isPercent = adjustmentType.value === 'increase_percent'
            || adjustmentType.value === 'decrease_percent';

        if (isPercent) {
            Percent.config.decimal = Currency.config.decimal;
            Percent.applyMask(adjustmentValue);
        } else {
            Currency.applyMask(adjustmentValue);
        }
    }

    function syncGroupFilter() {
        const selectedGroup = grupoFilter.value;
        if (!selectedGroup) return;

        const groupExists = grupos.some(group => (
            group.id !== null && String(group.id) === selectedGroup
        ));

        if (!groupExists) {
            grupoFilter.value = '';
            grupoFilter.chosenSelect?.refresh();
        }
    }

    function renderGroups() {
        const visibleGroups = filteredGroups();
        if (visibleGroups.length === 0) {
            vehiclesContainer.innerHTML = `
                <div class="bg-white shadow-md rounded-lg p-8 text-center text-slate-500">
                    <i class="fas fa-car mr-2" aria-hidden="true"></i>${escapeHtml(i18n.noVehicles)}
                </div>
            `;
            updateSummary();
            return;
        }

        vehiclesContainer.innerHTML = visibleGroups.map(group => `
            <section class="bg-white shadow-md rounded-lg overflow-hidden mb-4">
                <div class="bg-slate-100 border-b border-slate-200 px-4 py-3 flex items-center justify-between gap-3">
                    <h3 class="font-semibold text-slate-800">
                        <i class="fas fa-layer-group mr-2 text-blue-600" aria-hidden="true"></i>${escapeHtml(group.nome)}
                    </h3>
                    <span class="text-xs font-medium text-slate-500">${escapeHtml(formatTemplate(i18n.vehiclesCount, group.veiculos.length))}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] divide-y divide-slate-200">
                        <thead class="table-header-custom">
                            <tr>
                                <th class="table-header">${escapeHtml(i18n.plate)}</th>
                                <th class="table-header">${escapeHtml(i18n.brandModel)}</th>
                                <th class="table-header text-right">${escapeHtml(i18n.currentValue)}</th>
                                <th class="table-header w-52">${escapeHtml(i18n.newValue)}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            ${group.veiculos.map(vehicle => `
                                <tr class="hover:bg-slate-50">
                                    <td class="table-cell font-medium">${escapeHtml(vehicle.placa || '-')}</td>
                                    <td class="table-cell">${escapeHtml(`${vehicle.marca || ''} ${vehicle.modelo || ''}`.trim() || '-')}</td>
                                    <td class="table-cell text-right whitespace-nowrap">${escapeHtml(Currency.format(vehicle.valorAtual, true))}</td>
                                    <td class="table-cell">
                                        <input type="text"
                                            class="form-input-focus text-right valor-fracao-input"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            data-vehicle-id="${vehicle.id}"
                                            value="${vehicle.novoValor === null ? '' : escapeHtml(Currency.format(vehicle.novoValor, false))}">
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </section>
        `).join('');

        vehiclesContainer.querySelectorAll('.valor-fracao-input').forEach(input => {
            Currency.applyMask(input);
            input.addEventListener('input', function () {
                const vehicle = allVehicles().find(item => item.id === Number(this.dataset.vehicleId));
                if (!vehicle) return;
                vehicle.novoValor = this.value.trim() === '' ? null : Currency.parse(this.value);
                updateSummary();
            });
        });

        updateSummary();
    }

    async function loadVehicles(filialId) {
        if (!filialId) {
            grupos = [];
            renderGroups();
            return;
        }

        vehiclesContainer.innerHTML = `
            <div class="bg-white shadow-md rounded-lg p-8 text-center text-slate-500">
                <i class="fas fa-spinner fa-spin mr-2" aria-hidden="true"></i>${escapeHtml(i18n.loading)}
            </div>
        `;
        btnSalvar.disabled = true;

        try {
            const result = await API.get('/api/veiculos/valores-fracao', {
                id_matriz_filial: filialId
            });

            if (!result.success) {
                throw new Error(result.message || i18n.loadError);
            }

            Currency.config = { ...Currency.config, ...(result.data.filial.currency || {}) };
            Currency.updateSymbols();
            configureAdjustmentMask();
            filialCarregadaId = Number(filialId);
            grupos = (result.data.grupos || []).map(group => ({
                id: group.id === null ? null : Number(group.id),
                nome: group.nome,
                veiculos: (group.veiculos || []).map(vehicle => ({
                    id: Number(vehicle.id),
                    placa: vehicle.placa,
                    marca: vehicle.marca,
                    modelo: vehicle.modelo,
                    valorAtual: Number(vehicle.valor_por_fracao || 0),
                    novoValor: null
                }))
            }));

            syncGroupFilter();
            renderGroups();
        } catch (error) {
            grupos = [];
            vehiclesContainer.innerHTML = `
                <div class="bg-white shadow-md rounded-lg p-8 text-center text-red-600">
                    <i class="fas fa-exclamation-triangle mr-2" aria-hidden="true"></i>${escapeHtml(error.message || i18n.loadError)}
                </div>
            `;
            updateSummary();
        }
    }

    function roundMoney(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    function calculateValues() {
        const visibleVehicles = filteredGroups().flatMap(group => group.veiculos);
        if (visibleVehicles.length === 0) {
            mostrarAlerta(i18n.noFiltered);
            return;
        }

        if (adjustmentValue.value.trim() === '') {
            mostrarAlerta(i18n.adjustmentRequired);
            adjustmentValue.focus();
            return;
        }

        const type = adjustmentType.value;
        const isPercent = type === 'increase_percent' || type === 'decrease_percent';
        const adjustment = isPercent ? Percent.parse(adjustmentValue.value) : Currency.parse(adjustmentValue.value);

        if (!Number.isFinite(adjustment) || adjustment < 0) {
            mostrarAlerta(i18n.adjustmentRequired);
            return;
        }

        if (type === 'decrease_percent' && adjustment > 100) {
            mostrarAlerta(i18n.percentLimit);
            return;
        }

        const calculated = [];
        for (const vehicle of visibleVehicles) {
            let newValue = vehicle.valorAtual;

            if (type === 'increase_percent') newValue = vehicle.valorAtual * (1 + adjustment / 100);
            if (type === 'decrease_percent') newValue = vehicle.valorAtual * (1 - adjustment / 100);
            if (type === 'add_value') newValue = vehicle.valorAtual + adjustment;
            if (type === 'subtract_value') newValue = Math.max(0, vehicle.valorAtual - adjustment);
            if (type === 'set_value') newValue = adjustment;

            newValue = roundMoney(newValue);
            if (!Number.isFinite(newValue) || newValue < 0 || newValue > 99999999.99) {
                mostrarAlerta(i18n.valueLimit);
                return;
            }

            calculated.push([vehicle, newValue]);
        }

        calculated.forEach(([vehicle, newValue]) => {
            vehicle.novoValor = newValue;
        });
        renderGroups();
    }

    function clearCalculation() {
        allVehicles().forEach(vehicle => {
            vehicle.novoValor = null;
        });
        renderGroups();
    }

    function requestSave() {
        const changes = changedVehicles();
        if (changes.length === 0) {
            mostrarAlerta(i18n.noChanges);
            return;
        }

        pendingSaveConfirmation = true;
        window.parent.postMessage({
            action: 'openGenericConfirmModal',
            title: i18n.confirmTitle,
            message: formatTemplate(i18n.confirmMessage, changes.length),
            confirmText: i18n.confirmButton
        }, '*');
    }

    async function saveChanges() {
        const changes = changedVehicles();
        if (changes.length === 0 || saving) return;

        saving = true;
        updateSummary();

        const payload = {
            id_matriz_filial: filialCarregadaId,
            veiculos: changes.map(vehicle => ({
                id: vehicle.id,
                valor_original: vehicle.valorAtual.toFixed(2),
                novo_valor: vehicle.novoValor.toFixed(2)
            }))
        };

        try {
            const result = await API.post('/veiculos/valores-fracao/atualizar', payload);
            if (!result.success) {
                mostrarAlerta(result.message || i18n.saveError);
                if (result.conflict) {
                    await loadVehicles(filialCarregadaId);
                }
                return;
            }

            const savedById = new Map(payload.veiculos.map(item => [item.id, Number(item.novo_valor)]));
            allVehicles().forEach(vehicle => {
                if (savedById.has(vehicle.id)) {
                    vehicle.valorAtual = savedById.get(vehicle.id);
                }
                vehicle.novoValor = null;
            });

            mostrarAlerta(result.message, 'success');
            renderGroups();
        } catch (error) {
            mostrarAlerta(error.message || i18n.saveError);
        } finally {
            saving = false;
            updateSummary();
        }
    }

    filialSelect?.addEventListener('change', function () {
        const nextFilialId = Number(this.value || 0);
        if (changedVehicles().length === 0) {
            loadVehicles(nextFilialId);
            return;
        }

        pendingBranchId = nextFilialId;
        this.value = String(filialCarregadaId);
        this.chosenSelect?.refresh();
        window.parent.postMessage({
            action: 'openGenericConfirmModal',
            title: i18n.branchChangeTitle,
            message: i18n.branchChangeMessage,
            confirmText: i18n.branchChangeButton
        }, '*');
    });

    grupoFilter.addEventListener('change', renderGroups);
    searchInput.addEventListener('input', renderGroups);
    adjustmentType.addEventListener('change', configureAdjustmentMask);
    document.getElementById('btnCalcular').addEventListener('click', calculateValues);
    document.getElementById('btnLimpar').addEventListener('click', clearCalculation);
    document.getElementById('btnSalvar').addEventListener('click', requestSave);
    document.getElementById('btnVoltar').addEventListener('click', () => navegarPara('/pages/veiculos'));

    window.addEventListener('message', function (event) {
        if (!event.data || !event.data.action) return;

        if (event.data.action === 'genericConfirmed') {
            if (pendingSaveConfirmation) {
                pendingSaveConfirmation = false;
                saveChanges();
                return;
            }

            if (pendingBranchId !== null) {
                const branchId = pendingBranchId;
                pendingBranchId = null;
                filialSelect.value = String(branchId);
                filialSelect.chosenSelect?.refresh();
                loadVehicles(branchId);
            }
        }

        if (event.data.action === 'genericModalClosed') {
            pendingSaveConfirmation = false;
            pendingBranchId = null;
        }
    });

    if (filialCarregadaId > 0) {
        loadVehicles(filialCarregadaId);
    } else {
        configureAdjustmentMask();
        grupos = [];
        renderGroups();
    }
})();
</script>
@endsection
