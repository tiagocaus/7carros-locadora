@extends('layouts.iframe')

@section('title', t('modules.contratos.quick_odometer.title'))

@section('content')
<?php
$fmtKm = static fn($valor): string => number_format((int) ($valor ?? 0), 0, '', '.') . ' km';
$fmtMediaKm = static fn($valor, string $sufixo): string => number_format((float) ($valor ?? 0), 1, ',', '.') . ' km/' . $sufixo;
$fmtData = static function (?string $data): string {
    return empty($data) ? '-' : format_date($data);
};
$planos = [
    'KMC' => t('modules.contratos.vehicles.plan_km_controlled'),
    'KL' => t('modules.contratos.vehicles.plan_km_free'),
    'KP' => t('modules.contratos.vehicles.plan_km_paid'),
];
$singleMode = count($veiculos) === 1;
?>

<style>
    .odometer-card.is-open .odometer-form { display: block; }
    .odometer-card.is-open .btn-toggle-form { display: none; }
    .odometer-form { display: none; }
    .odometer-card.single .odometer-form { display: block; }
    .odometer-history-row { display: grid; grid-template-columns: 100px 85px minmax(0, 1fr) 34px; gap: .5rem; align-items: center; }
    .odometer-history-date { grid-column: 1; }
    .odometer-history-km { grid-column: 2; }
    .odometer-history-obs { grid-column: 3; }
    .odometer-history-action { grid-column: 4; }
    .odometer-history-registered-at { grid-column: 1 / -1; text-align: right; }
    .odometer-history-edit { grid-column: 1 / -1; }
    @media (max-width: 640px) {
        .odometer-history-row { grid-template-columns: 88px 62px minmax(0, 1fr) 34px; gap: .375rem; }
    }
</style>

<div class="p-4">
    <div class="mb-4 border-b border-slate-200 pb-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500"><?= e(t('modules.contratos.quick_odometer.contract')) ?></p>
        <p class="text-lg font-semibold text-slate-800"><?= e($contrato['codigo'] ?? '-') ?></p>
        <p class="text-sm text-slate-600"><?= e($contrato['cliente_nome'] ?? '-') ?></p>
    </div>

    <?php if (empty($veiculos)): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <?= e(t('modules.contratos.quick_odometer.no_active_vehicles')) ?>
        </div>
    <?php else: ?>
        <div class="mb-3 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500"><?= e(t('modules.contratos.quick_odometer.active_vehicles')) ?></p>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= count($veiculos) ?></span>
        </div>

        <div class="space-y-3">
            <?php foreach ($veiculos as $veiculo): ?>
                <?php
                $ultima = $veiculo['ultima_leitura'] ?? null;
                $historico = $veiculo['historico_odometros'] ?? [];
                $plano = $veiculo['plano'] ?? 'KL';
                $kmRodado = (int) ($veiculo['km_rodado_atual'] ?? 0);
                $kmFranquia = (int) ($veiculo['km_franquia_efetiva'] ?? $veiculo['km_franquia'] ?? 0);
                $valorKm = (float) ($veiculo['valor_km_excedente'] ?? 0);
                $kmExcedente = $plano === 'KMC' ? max(0, $kmRodado - $kmFranquia) : 0;
                $diasUso = max(1, (int) ($veiculo['dias_uso'] ?? 1));
                $dataSaida = substr((string) ($veiculo['data_saida'] ?? $contrato['data_ini'] ?? ''), 0, 10);
                ?>
                <div
                    class="odometer-card <?= $singleMode ? 'single is-open' : '' ?> rounded-lg border border-slate-200 bg-white p-3"
                    data-id="<?= (int) $veiculo['id'] ?>"
                    data-odometro-saida="<?= (int) ($veiculo['odometro_saida'] ?? 0) ?>"
                    data-odometro-minimo="<?= (int) ($veiculo['odometro_minimo'] ?? 0) ?>"
                    data-data-saida="<?= e($dataSaida) ?>"
                    data-plano="<?= e($plano) ?>"
                    data-km-franquia="<?= $kmFranquia ?>"
                    data-valor-km="<?= $valorKm ?>"
                    data-dias-uso="<?= $diasUso ?>"
                    data-history="<?= e(json_encode($historico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)) ?>"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-800">
                                    <?= e(trim(($veiculo['veiculo_placa'] ?? '-') . ' - ' . ($veiculo['veiculo_modelo'] ?? ''))) ?>
                                </p>
                                <span class="rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                    <?= e($planos[$plano] ?? $plano) ?>
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                <?= e(t('modules.contratos.quick_odometer.departure')) ?>:
                                <strong><?= $fmtKm($veiculo['odometro_saida'] ?? 0) ?></strong>
                                · <?= e(t('modules.contratos.quick_odometer.registration')) ?>:
                                <strong class="odometro-cadastro-label"><?= $fmtKm($veiculo['veiculo_odometro'] ?? 0) ?></strong>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                <?= e(t('modules.contratos.quick_odometer.last_reading')) ?>:
                                <strong class="ultima-leitura-label">
                                    <?= $ultima ? $fmtKm($ultima['odometro']) . ' - ' . $fmtData($ultima['data']) : '-' ?>
                                </strong>
                            </p>
                        </div>
                        <?php if (!$singleMode): ?>
                            <button type="button" class="btn-toggle-form rounded-md bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">
                                <?= e(t('modules.contratos.quick_odometer.save_reading')) ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="odometer-form mt-4 border-t border-slate-100 pt-4">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="form-input-group">
                                <label class="form-label-group">
                                    <?= e(t('modules.contratos.quick_odometer.current')) ?> <span class="text-red-500">*</span>
                                    <?= aviso(t('modules.contratos.quick_odometer.minimum_hint')) ?>
                                    <span class="ml-1 text-xs font-normal text-slate-500 odometro-minimo-label">
                                        <?= e(t('modules.contratos.quick_odometer.minimum', ['value' => $fmtKm($veiculo['odometro_minimo'] ?? 0)])) ?>
                                    </span>
                                </label>
                                <input type="text" class="form-input-group-field input-km odometro-input" inputmode="numeric" placeholder="0">
                            </div>
                            <div class="form-input-group">
                                <label class="form-label-group"><?= e(t('modules.contratos.quick_odometer.observation')) ?></label>
                                <textarea class="form-input-group-field odometro-obs" rows="2" maxlength="255" placeholder="<?= e(t('modules.contratos.quick_odometer.optional')) ?>"></textarea>
                            </div>
                        </div>

                        <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                            <div class="flex justify-between"><span><?= e(t('modules.contratos.quick_odometer.km_driven')) ?></span><strong class="km-rodado-label"><?= $fmtKm($kmRodado) ?></strong></div>
                            <div class="mt-1 flex justify-between"><span><?= e(t('modules.contratos.quick_odometer.average_day')) ?></span><strong class="media-dia-label"><?= $fmtMediaKm($veiculo['media_km_dia'] ?? 0, 'd') ?></strong></div>
                            <div class="mt-1 flex justify-between"><span><?= e(t('modules.contratos.quick_odometer.average_week')) ?></span><strong class="media-semana-label"><?= $fmtMediaKm($veiculo['media_km_semana'] ?? 0, 's') ?></strong></div>
                            <div class="mt-1 flex justify-between"><span><?= e(t('modules.contratos.quick_odometer.average_month')) ?></span><strong class="media-mes-label"><?= $fmtMediaKm($veiculo['media_km_mes'] ?? 0, 'm') ?></strong></div>
                            <?php if ($plano === 'KMC'): ?>
                                <div class="mt-1 flex justify-between"><span><?= e(t('modules.contratos.quick_odometer.franchise')) ?></span><strong><?= $fmtKm($kmFranquia) ?></strong></div>
                                <div class="mt-1 flex justify-between"><span><?= e(t('modules.contratos.quick_odometer.estimated_excess')) ?></span><strong class="km-excedente-label"><?= $fmtKm($kmExcedente) ?></strong></div>
                                <div class="mt-1 flex justify-between"><span><?= e(t('modules.contratos.quick_odometer.estimated_value')) ?></span><strong class="valor-excedente-label"><?= currency_format($kmExcedente * $valorKm) ?></strong></div>
                            <?php else: ?>
                                <p class="mt-2 text-xs text-slate-500"><?= e(t('modules.contratos.quick_odometer.informative')) ?></p>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="btn-salvar-odometro mt-4 w-full btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center justify-center">
                            <i class="fas fa-save mr-2"></i><?= e(t('modules.contratos.quick_odometer.save_reading')) ?>
                        </button>

                        <details class="mt-4 border-t border-slate-200 pt-3" open>
                            <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <?= e(t('modules.contratos.quick_odometer.recent_records')) ?>
                                (<span class="history-count"><?= count($historico) ?></span>)
                            </summary>
                            <div class="odometer-history mt-2 space-y-2"></div>
                        </details>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        const contratoId = <?= (int) ($contrato['id'] ?? 0) ?>;
        const today = <?= json_encode($hoje, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const i18n = <?= json_encode([
            'noRecords' => t('modules.contratos.quick_odometer.no_records'),
            'edit' => t('modules.contratos.quick_odometer.edit'),
            'registeredAt' => t('modules.contratos.quick_odometer.registered_at'),
            'date' => t('modules.contratos.quick_odometer.date'),
            'odometer' => t('modules.contratos.quick_odometer.odometer'),
            'observation' => t('modules.contratos.quick_odometer.observation'),
            'optional' => t('modules.contratos.quick_odometer.optional'),
            'cancel' => t('modules.contratos.quick_odometer.cancel'),
            'update' => t('modules.contratos.quick_odometer.update'),
            'updating' => t('modules.contratos.quick_odometer.updating'),
            'saving' => t('modules.contratos.quick_odometer.saving'),
            'invalidFields' => t('modules.contratos.quick_odometer.errors.invalid_fields'),
            'registerFailed' => t('modules.contratos.quick_odometer.errors.register_failed'),
            'updateFailed' => t('modules.contratos.quick_odometer.errors.update_failed'),
            'minimum' => t('modules.contratos.quick_odometer.minimum'),
            'minimumError' => t('modules.contratos.quick_odometer.errors.minimum_reading'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        let activeEditCard = null;

        function alertMessage(message, type = null) {
            window.parent.postMessage({ action: 'openAlert', type: type || undefined, message }, '*');
        }

        function escapeText(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function formatKm(value) { return `${Km.format(value)} km`; }
        function formatAverageKm(value, suffix) {
            return `${(Number(value) || 0).toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} km/${suffix}`;
        }

        function normalizeHistory(items) {
            return (Array.isArray(items) ? items : []).map(item => ({
                ...item,
                id: Number(item.id),
                odometro: Number(item.odometro) || 0,
                obs: String(item.obs || ''),
                created_at: item.created_at || ''
            }));
        }

        function renderHistory(card) {
            const container = card.querySelector('.odometer-history');
            const history = card._history || [];
            card.querySelector('.history-count').textContent = String(history.length);
            if (!history.length) {
                container.innerHTML = `<p class="rounded-md bg-slate-50 p-3 text-xs text-slate-500">${escapeText(i18n.noRecords)}</p>`;
                return;
            }
            container.innerHTML = history.map(item => `
                <div class="odometer-history-row rounded-md border border-slate-200 p-2 text-xs" data-reading-id="${item.id}">
                    <span class="odometer-history-date text-slate-600">${escapeText(item.data_formatada || DateHelper.format(item.data))}</span>
                    <strong class="odometer-history-km text-slate-800">${escapeText(item.odometro_formatado || formatKm(item.odometro))}</strong>
                    ${item.obs.trim() ? `<span class="odometer-history-obs truncate text-slate-500" title="${escapeText(item.obs)}">${escapeText(item.obs)}</span>` : ''}
                    <button type="button" class="odometer-history-action btn-edit-reading flex h-8 w-8 items-center justify-center rounded text-blue-600 hover:bg-blue-50" title="${escapeText(i18n.edit)}" aria-label="${escapeText(i18n.edit)}">
                        <i class="fas fa-pen"></i>
                    </button>
                    ${item.created_at ? `<span class="odometer-history-registered-at text-[10px] text-slate-400">${escapeText(i18n.registeredAt)}: ${escapeText(DateHelper.formatDateTime(item.created_at))}</span>` : ''}
                </div>
            `).join('');
        }

        function closeActiveEditor() {
            if (activeEditCard) renderHistory(activeEditCard);
            activeEditCard = null;
        }

        function openEditor(card, readingId) {
            closeActiveEditor();
            const item = (card._history || []).find(reading => reading.id === readingId);
            if (!item) return;
            activeEditCard = card;
            const row = card.querySelector(`[data-reading-id="${readingId}"]`);
            row.innerHTML = `
                <div class="odometer-history-edit grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="form-input-group">
                        <label class="form-label-group">${escapeText(i18n.date)}</label>
                        <input type="date" class="form-input-group-field edit-reading-date" min="${escapeText(card.dataset.dataSaida)}" max="${escapeText(today)}" value="${escapeText(item.data)}">
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group">${escapeText(i18n.odometer)}</label>
                        <input type="text" inputmode="numeric" class="form-input-group-field edit-reading-km" value="${escapeText(Km.format(item.odometro))}">
                    </div>
                    <div class="form-input-group sm:col-span-2">
                        <label class="form-label-group">${escapeText(i18n.observation)}</label>
                        <textarea class="form-input-group-field edit-reading-obs" rows="2" maxlength="255" placeholder="${escapeText(i18n.optional)}">${escapeText(item.obs)}</textarea>
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" class="btn-cancel-reading rounded-md border border-slate-300 px-3 py-2 text-xs font-medium text-slate-600">${escapeText(i18n.cancel)}</button>
                        <button type="button" class="btn-update-reading btn-blue rounded-md px-3 py-2 text-xs font-medium" data-reading-id="${readingId}">${escapeText(i18n.update)}</button>
                    </div>
                </div>`;
            Km.applyMask(row.querySelector('.edit-reading-km'));
            row.querySelector('.edit-reading-km')?.focus();
        }

        function updateCalculation(card) {
            const entered = Km.parse(card.querySelector('.odometro-input').value || '0');
            const minimum = parseInt(card.dataset.odometroMinimo || '0', 10) || 0;
            const current = entered > 0 ? entered : minimum;
            const departure = parseInt(card.dataset.odometroSaida || '0', 10) || 0;
            const driven = Math.max(0, current - departure);
            const franchise = parseInt(card.dataset.kmFranquia || '0', 10) || 0;
            const valueKm = parseFloat(card.dataset.valorKm || '0') || 0;
            const excess = card.dataset.plano === 'KMC' ? Math.max(0, driven - franchise) : 0;
            const days = Math.max(1, parseInt(card.dataset.diasUso || '1', 10) || 1);
            const daily = driven / days;

            card.querySelector('.km-rodado-label').textContent = formatKm(driven);
            card.querySelector('.media-dia-label').textContent = formatAverageKm(daily, 'd');
            card.querySelector('.media-semana-label').textContent = formatAverageKm(daily * 7, 's');
            card.querySelector('.media-mes-label').textContent = formatAverageKm(daily * 30, 'm');
            if (card.querySelector('.km-excedente-label')) card.querySelector('.km-excedente-label').textContent = formatKm(excess);
            if (card.querySelector('.valor-excedente-label')) card.querySelector('.valor-excedente-label').textContent = Currency.format(excess * valueKm, true);
        }

        function applyServerSummary(card, data) {
            const odometer = Number(data.odometro) || Number(card.dataset.odometroSaida) || 0;
            const vehicleOdometer = Number(data.odometro_veiculo ?? odometer) || odometer;
            card.dataset.odometroMinimo = String(Math.max(odometer, vehicleOdometer, Number(card.dataset.odometroSaida) || 0));
            card.querySelector('.odometro-minimo-label').textContent = i18n.minimum.replace(':value', formatKm(card.dataset.odometroMinimo));
            card.querySelector('.odometro-cadastro-label').textContent = formatKm(vehicleOdometer);
            const last = data.ultima_leitura || card._history?.[0];
            card.querySelector('.ultima-leitura-label').textContent = last
                ? `${last.odometro_formatado || formatKm(last.odometro)} - ${last.data_formatada || DateHelper.format(last.data)}`
                : '—';
            updateCalculation(card);
        }

        document.querySelectorAll('.odometer-card').forEach(card => {
            try { card._history = normalizeHistory(JSON.parse(card.dataset.history || '[]')); }
            catch (_) { card._history = []; }
            renderHistory(card);
            updateCalculation(card);
        });

        document.querySelectorAll('.btn-toggle-form').forEach(button => {
            button.addEventListener('click', function() {
                const card = this.closest('.odometer-card');
                document.querySelectorAll('.odometer-card').forEach(item => { if (item !== card) item.classList.remove('is-open'); });
                card.classList.add('is-open');
                card.querySelector('.odometro-input')?.focus();
            });
        });

        document.querySelectorAll('.odometro-input').forEach(input => {
            input.addEventListener('input', () => updateCalculation(input.closest('.odometer-card')));
        });

        document.addEventListener('click', async event => {
            const editButton = event.target.closest('.btn-edit-reading');
            if (editButton) {
                const card = editButton.closest('.odometer-card');
                openEditor(card, Number(editButton.closest('[data-reading-id]').dataset.readingId));
                return;
            }
            if (event.target.closest('.btn-cancel-reading')) {
                closeActiveEditor();
                return;
            }
            const updateButton = event.target.closest('.btn-update-reading');
            if (!updateButton) return;

            const card = updateButton.closest('.odometer-card');
            const row = updateButton.closest('[data-reading-id]');
            const data = row.querySelector('.edit-reading-date').value;
            const odometro = Km.parse(row.querySelector('.edit-reading-km').value || '0');
            if (!data || !odometro) {
                alertMessage(i18n.invalidFields);
                return;
            }

            const original = updateButton.innerHTML;
            updateButton.disabled = true;
            updateButton.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${escapeText(i18n.updating)}`;
            try {
                const result = await API.put(`/api/contratos/${contratoId}/odometros/${updateButton.dataset.readingId}`, {
                    id_contrato_veiculo: card.dataset.id,
                    data,
                    odometro,
                    obs: row.querySelector('.edit-reading-obs').value || ''
                });
                if (!result.success) {
                    alertMessage(result.message || i18n.updateFailed);
                    return;
                }
                card._history = normalizeHistory(result.data?.historico || []);
                activeEditCard = null;
                renderHistory(card);
                applyServerSummary(card, result.data || {});
                window.parent.postMessage({ action: 'contratoOdometroRegistrado', contratoId }, '*');
                alertMessage(result.message, 'success');
            } catch (error) {
                console.error('Erro ao atualizar odometro:', error);
                alertMessage(error.message || i18n.updateFailed);
            } finally {
                updateButton.disabled = false;
                updateButton.innerHTML = original;
            }
        });

        document.querySelectorAll('.btn-salvar-odometro').forEach(button => {
            button.addEventListener('click', async function() {
                const card = this.closest('.odometer-card');
                const odometro = Km.parse(card.querySelector('.odometro-input').value || '0');
                const minimum = parseInt(card.dataset.odometroMinimo || '0', 10) || 0;
                if (!odometro || odometro < minimum) {
                    alertMessage(i18n.minimumError.replace(':referencia', Km.format(minimum)));
                    return;
                }

                const original = this.innerHTML;
                this.disabled = true;
                this.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${escapeText(i18n.saving)}`;
                try {
                    const obs = card.querySelector('.odometro-obs').value || '';
                    const result = await API.post(`/api/contratos/${contratoId}/odometros`, {
                        id_contrato_veiculo: card.dataset.id, odometro, obs
                    });
                    if (!result.success) {
                        alertMessage(result.message || i18n.registerFailed);
                        return;
                    }
                    const data = result.data || {};
                    const reading = {
                        id: Number(data.registro?.id), data: data.data, data_formatada: data.data_formatada,
                        odometro: Number(data.odometro || odometro), odometro_formatado: data.odometro_formatado,
                        diferenca: Number(data.registro?.diferenca || 0), obs, created_at: data.created_at || ''
                    };
                    card._history = [reading, ...(card._history || []).filter(item => item.id !== reading.id)].slice(0, 5);
                    renderHistory(card);
                    applyServerSummary(card, { ...data, odometro_veiculo: data.odometro, ultima_leitura: reading });
                    card.querySelector('.odometro-input').value = '';
                    card.querySelector('.odometro-obs').value = '';
                    updateCalculation(card);
                    window.parent.postMessage({ action: 'contratoOdometroRegistrado', contratoId }, '*');
                    alertMessage(result.message, 'success');
                } catch (error) {
                    console.error('Erro ao registrar odometro:', error);
                    alertMessage(error.message || i18n.registerFailed);
                } finally {
                    this.disabled = false;
                    this.innerHTML = original;
                }
            });
        });
    })();
</script>
@endsection
