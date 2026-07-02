@extends('layouts.iframe')

@section('title', 'Registrar odômetro')

@section('content')
<?php
$fmtKm = static fn($valor): string => number_format((int) ($valor ?? 0), 0, '', '.') . ' km';
$fmtMediaKm = static fn($valor, string $sufixo): string => number_format((float) ($valor ?? 0), 1, ',', '.') . ' km/' . $sufixo;
$fmtData = static function (?string $data): string {
    if (empty($data)) {
        return '-';
    }
    $ts = strtotime($data);
    return $ts ? \App\Helpers\DateHelper::formatTimestamp($ts, 'd/m/Y') : $data;
};
$planos = [
    'KMC' => 'Km Controlado',
    'KL' => 'Km Livre',
    'KP' => 'Km Pago',
];
$singleMode = count($veiculos) === 1;
?>

<style>
    .odometer-card.is-open .odometer-form {
        display: block;
    }
    .odometer-card.is-open .btn-toggle-form {
        display: none;
    }
    .odometer-form {
        display: none;
    }
    .odometer-card.single .odometer-form {
        display: block;
    }
</style>

<div class="p-4">
    <div class="mb-4 border-b border-slate-200 pb-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Contrato</p>
        <p class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($contrato['codigo'] ?? '-') ?></p>
        <p class="text-sm text-slate-600"><?= htmlspecialchars($contrato['cliente_nome'] ?? '-') ?></p>
    </div>

    <?php if (empty($veiculos)): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Nenhum veículo ativo encontrado para registrar odômetro.
        </div>
    <?php else: ?>
        <div class="mb-3 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Veículos ativos</p>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= count($veiculos) ?></span>
        </div>

        <div class="space-y-3">
            <?php foreach ($veiculos as $index => $veiculo): ?>
                <?php
                $ultima = $veiculo['ultima_leitura'] ?? null;
                $plano = $veiculo['plano'] ?? 'KL';
                $kmRodado = (int) ($veiculo['km_rodado_atual'] ?? 0);
                $kmFranquia = (int) ($veiculo['km_franquia_efetiva'] ?? $veiculo['km_franquia'] ?? 0);
                $valorKm = (float) ($veiculo['valor_km_excedente'] ?? 0);
                $kmExcedente = $plano === 'KMC' ? max(0, $kmRodado - $kmFranquia) : 0;
                $diasUso = max(1, (int) ($veiculo['dias_uso'] ?? 1));
                ?>
                <div
                    class="odometer-card <?= $singleMode ? 'single is-open' : '' ?> rounded-lg border border-slate-200 bg-white p-3"
                    data-id="<?= (int) $veiculo['id'] ?>"
                    data-odometro-saida="<?= (int) ($veiculo['odometro_saida'] ?? 0) ?>"
                    data-odometro-minimo="<?= (int) ($veiculo['odometro_minimo'] ?? 0) ?>"
                    data-plano="<?= htmlspecialchars($plano) ?>"
                    data-km-franquia="<?= $kmFranquia ?>"
                    data-valor-km="<?= $valorKm ?>"
                    data-dias-uso="<?= $diasUso ?>"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-800">
                                    <?= htmlspecialchars(trim(($veiculo['veiculo_placa'] ?? '-') . ' - ' . ($veiculo['veiculo_modelo'] ?? ''))) ?>
                                </p>
                                <span class="rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                    <?= htmlspecialchars($planos[$plano] ?? $plano) ?>
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                Saída: <strong><?= $fmtKm($veiculo['odometro_saida'] ?? 0) ?></strong>
                                · Cadastro: <strong class="odometro-cadastro-label"><?= $fmtKm($veiculo['veiculo_odometro'] ?? 0) ?></strong>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Última leitura:
                                <strong class="ultima-leitura-label">
                                    <?= $ultima ? $fmtKm($ultima['odometro']) . ' - ' . $fmtData($ultima['data']) : '-' ?>
                                </strong>
                            </p>
                        </div>
                        <?php if (!$singleMode): ?>
                            <button type="button" class="btn-toggle-form btn-icon text-blue-600 hover:text-blue-800" title="Registrar">
                                <i class="fas fa-gauge-high"></i>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="odometer-form mt-4 border-t border-slate-100 pt-4">
                        <div class="form-input-group mb-3">
                            <label class="form-label-group">Odômetro atual <span class="text-red-500">*</span></label>
                            <input type="text" class="form-input-group-field input-km odometro-input" placeholder="0">
                            <p class="mt-1 text-xs text-slate-500">Mínimo permitido: <span class="odometro-minimo-label"><?= $fmtKm($veiculo['odometro_minimo'] ?? 0) ?></span></p>
                        </div>

                        <div class="form-input-group mb-3">
                            <label class="form-label-group">Observação</label>
                            <textarea class="form-input-group-field odometro-obs" rows="2" maxlength="255" placeholder="Opcional"></textarea>
                        </div>

                        <div class="calculo-box rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                            <div class="flex justify-between">
                                <span>Km rodado atual</span>
                                <strong class="km-rodado-label"><?= $fmtKm($kmRodado) ?></strong>
                            </div>
                            <div class="mt-1 flex justify-between">
                                <span>Média por dia</span>
                                <strong class="media-dia-label"><?= $fmtMediaKm($veiculo['media_km_dia'] ?? 0, 'd') ?></strong>
                            </div>
                            <div class="mt-1 flex justify-between">
                                <span>Média por semana</span>
                                <strong class="media-semana-label"><?= $fmtMediaKm($veiculo['media_km_semana'] ?? 0, 's') ?></strong>
                            </div>
                            <div class="mt-1 flex justify-between">
                                <span>Média por mês</span>
                                <strong class="media-mes-label"><?= $fmtMediaKm($veiculo['media_km_mes'] ?? 0, 'm') ?></strong>
                            </div>
                            <?php if ($plano === 'KMC'): ?>
                                <div class="mt-1 flex justify-between">
                                    <span>Franquia</span>
                                    <strong><?= $fmtKm($kmFranquia) ?></strong>
                                </div>
                                <div class="mt-1 flex justify-between">
                                    <span>Excedente estimado</span>
                                    <strong class="km-excedente-label"><?= $fmtKm($kmExcedente) ?></strong>
                                </div>
                                <div class="mt-1 flex justify-between">
                                    <span>Valor estimado</span>
                                    <strong class="valor-excedente-label"><?= currency_format($kmExcedente * $valorKm) ?></strong>
                                </div>
                            <?php else: ?>
                                <p class="text-xs text-slate-500">Leitura informativa para histórico e manutenção preventiva.</p>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="btn-salvar-odometro mt-4 w-full btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center justify-center">
                            <i class="fas fa-save mr-2"></i>Salvar leitura
                        </button>
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

        function alertMessage(message, type = null) {
            window.parent.postMessage({
                action: 'openAlert',
                type: type || undefined,
                message: message
            }, '*');
        }

        function formatKm(value) {
            return `${Km.format(value)} km`;
        }

        function formatAverageKm(value, suffix) {
            const number = Number(value) || 0;
            return `${number.toLocaleString('pt-BR', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            })} km/${suffix}`;
        }

        function updateCalculation(card) {
            const input = card.querySelector('.odometro-input');
            const odometroDigitado = Km.parse(input.value || '0');
            const odometroMinimo = parseInt(card.dataset.odometroMinimo || '0', 10) || 0;
            const odometroAtual = odometroDigitado > 0 ? odometroDigitado : odometroMinimo;
            const odometroSaida = parseInt(card.dataset.odometroSaida || '0', 10) || 0;
            const kmRodado = Math.max(0, odometroAtual - odometroSaida);
            const plano = card.dataset.plano || 'KL';
            const kmFranquia = parseInt(card.dataset.kmFranquia || '0', 10) || 0;
            const valorKm = parseFloat(card.dataset.valorKm || '0') || 0;
            const kmExcedente = plano === 'KMC' ? Math.max(0, kmRodado - kmFranquia) : 0;
            const diasUso = Math.max(1, parseInt(card.dataset.diasUso || '1', 10) || 1);
            const mediaDia = kmRodado / diasUso;

            card.querySelector('.km-rodado-label').textContent = formatKm(kmRodado);
            card.querySelector('.media-dia-label').textContent = formatAverageKm(mediaDia, 'd');
            card.querySelector('.media-semana-label').textContent = formatAverageKm(mediaDia * 7, 's');
            card.querySelector('.media-mes-label').textContent = formatAverageKm(mediaDia * 30, 'm');
            const excedenteLabel = card.querySelector('.km-excedente-label');
            const valorLabel = card.querySelector('.valor-excedente-label');
            if (excedenteLabel) excedenteLabel.textContent = formatKm(kmExcedente);
            if (valorLabel) valorLabel.textContent = Currency.format(kmExcedente * valorKm, true);
        }

        document.querySelectorAll('.btn-toggle-form').forEach(button => {
            button.addEventListener('click', function() {
                const card = this.closest('.odometer-card');
                document.querySelectorAll('.odometer-card').forEach(item => {
                    if (item !== card) item.classList.remove('is-open');
                });
                card.classList.add('is-open');
                card.querySelector('.odometro-input')?.focus();
            });
        });

        document.querySelectorAll('.odometro-input').forEach(input => {
            input.addEventListener('input', function() {
                updateCalculation(this.closest('.odometer-card'));
            });
            updateCalculation(input.closest('.odometer-card'));
        });

        document.querySelectorAll('.btn-salvar-odometro').forEach(button => {
            button.addEventListener('click', async function() {
                const card = this.closest('.odometer-card');
                const odometro = Km.parse(card.querySelector('.odometro-input').value || '0');
                const minimo = parseInt(card.dataset.odometroMinimo || '0', 10) || 0;

                if (!odometro || odometro < minimo) {
                    alertMessage(`Odômetro atual não pode ser menor que ${formatKm(minimo)}.`);
                    return;
                }

                const originalHtml = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Salvando...';

                try {
                    const result = await API.post(`/api/contratos/${contratoId}/odometros`, {
                        id_contrato_veiculo: card.dataset.id,
                        odometro: odometro,
                        obs: card.querySelector('.odometro-obs').value || ''
                    });

                    if (!result.success) {
                        alertMessage(result.message || 'Erro ao registrar odômetro.');
                        return;
                    }

                    const data = result.data || {};
                    card.dataset.odometroMinimo = String(data.odometro || odometro);
                    card.querySelector('.odometro-minimo-label').textContent = data.odometro_formatado || formatKm(odometro);
                    card.querySelector('.odometro-cadastro-label').textContent = data.odometro_formatado || formatKm(odometro);
                    card.querySelector('.ultima-leitura-label').textContent = `${data.odometro_formatado || formatKm(odometro)} - ${data.data_formatada || DateHelper.today()}`;
                    card.querySelector('.odometro-input').value = '';
                    card.querySelector('.odometro-obs').value = '';
                    updateCalculation(card);

                    window.parent.postMessage({ action: 'contratoOdometroRegistrado', contratoId: contratoId }, '*');
                    alertMessage(result.message || 'Odômetro registrado com sucesso.', 'success');
                } catch (error) {
                    console.error('Erro ao registrar odômetro:', error);
                    alertMessage(error.message || 'Erro ao registrar odômetro.');
                } finally {
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                }
            });
        });
    })();
</script>
@endsection
