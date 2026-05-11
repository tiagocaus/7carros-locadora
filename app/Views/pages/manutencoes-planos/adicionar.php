@extends('layouts.iframe')

@section('title', t('modules.manutencao_plano.title_new'))

@section('content')
<div class="pl-1 pr-2 py-0">
    <!-- Cabecalho -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="title-page" id="pageTitle"><?= t('modules.manutencao_plano.title_new') ?></h2>
        <button id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium flex items-center">
            <i class="fas fa-arrow-left mr-2"></i><?= t('modules.manutencao_plano.btn_back') ?>
        </button>
    </div>

    <!-- Formulario -->
    <form id="formPlano" method="POST">
        @csrf
        <input type="hidden" id="planoId" name="id">

        <!-- Secao 1: Dados Basicos -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-info-circle mr-2"></i><?= t('modules.manutencao_plano.section_basic') ?></h3>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <div class="md:col-span-6 form-input-group">
                    <label for="planoNome" class="form-label-group">
                        <?= t('modules.manutencao_plano.field_name') ?> <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="planoNome" name="nome" class="form-input-group-field" required maxlength="100" placeholder="<?= t('modules.manutencao_plano.field_name_placeholder') ?>">
                </div>

                <div class="md:col-span-3 form-input-group">
                    <label for="tipoVeiculo" class="form-label-group"><?= t('modules.manutencao_plano.field_vehicle_type') ?></label>
                    <select id="tipoVeiculo" name="tipo_veiculo" class="form-input-group-field">
                        <option value="C"><?= t('modules.manutencao_plano.vehicle_car') ?></option>
                        <option value="M"><?= t('modules.manutencao_plano.vehicle_motorcycle') ?></option>
                    </select>
                </div>

                <div class="md:col-span-3 form-input-group">
                    <label for="planoStatus" class="form-label-group"><?= t('modules.manutencao_plano.field_status') ?></label>
                    <select id="planoStatus" name="status" class="form-input-group-field">
                        <option value="A"><?= t('modules.manutencao_plano.field_status_active') ?></option>
                        <option value="I"><?= t('modules.manutencao_plano.field_status_inactive') ?></option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Secao 2: Intervalos de Manutencao -->
        <div class="form-section mb-6">
            <h3 class="form-section-title"><i class="fas fa-wrench mr-2"></i><?= t('modules.manutencao_plano.section_intervals') ?></h3>
            <p class="text-sm text-slate-500 mb-4"><?= t('modules.manutencao_plano.section_intervals_hint') ?></p>

            <!-- Motor -->
            <div class="mb-6">
                <h4 class="text-md font-semibold text-slate-700 mb-3 flex items-center">
                    <i class="fas fa-cog text-slate-500 mr-2"></i><?= t('modules.manutencao.categories.motor') ?>
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_oleo') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_oleo]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_filtrooleo') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_filtrooleo]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group" data-tipo="carro">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_correiadentada') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_correiadentada]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group" data-tipo="carro">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_correiaalternador') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_correiaalternador]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group" data-tipo="carro">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_correiaarcondicionado') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_correiaarcondicionado]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group" data-tipo="carro">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_correiabombadagua') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_correiabombadagua]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_filtrodear') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_filtrodear]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group" data-tipo="carro">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_filtrodecabine') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_filtrodecabine]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_filtrodecombustivel') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_filtrodecombustivel]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_fluidodofreio') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_fluidodofreio]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group" data-tipo="carro">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_fluidoembreagem') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_fluidoembreagem]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group" data-tipo="carro">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_discodeembreagem') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_discodeembreagem]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group" data-tipo="carro">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_fluidocaixademarcha') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_fluidocaixademarcha]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_limpesaarrefecimento') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_limpesaarrefecimento]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_vejas') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_vejas]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.motor_bateria') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[motor_bateria]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rodagem -->
            <div class="mb-6">
                <h4 class="text-md font-semibold text-slate-700 mb-3 flex items-center">
                    <i class="fas fa-tire text-slate-500 mr-2"></i><?= t('modules.manutencao.categories.rodagem') ?>
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.rodagem_pneus') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[rodagem_pneus]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group" data-tipo="carro">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.rodagem_alinhamento') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[rodagem_alinhamento]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.rodagem_pastilhasdefreio') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[rodagem_pastilhasdefreio]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.rodagem_discodefreios') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[rodagem_discodefreios]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group" data-tipo="carro">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.rodagem_rodiziodepneus') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[rodagem_rodiziodepneus]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acessorios -->
            <div class="mb-6" data-tipo="carro">
                <h4 class="text-md font-semibold text-slate-700 mb-3 flex items-center">
                    <i class="fas fa-tools text-slate-500 mr-2"></i><?= t('modules.manutencao.categories.acessorio') ?>
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.acessorio_paletasparabrisa') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[acessorio_paletasparabrisa]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Moto -->
            <div class="mb-2" data-tipo="moto" style="display: none;">
                <h4 class="text-md font-semibold text-slate-700 mb-3 flex items-center">
                    <i class="fas fa-motorcycle text-slate-500 mr-2"></i><?= t('modules.manutencao.categories.moto') ?>
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.moto_corrente') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[moto_corrente]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.moto_kitrelacao') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[moto_kitrelacao]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.moto_oleosuspensao') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[moto_oleosuspensao]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.moto_caboembreagem') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[moto_caboembreagem]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                    <div class="form-input-group">
                        <label class="form-label-group text-xs"><?= t('modules.manutencao.items.moto_caboacelerador') ?></label>
                        <div class="relative">
                            <input type="text" name="intervalos[moto_caboacelerador]" class="form-input-group-field pr-12 input-km" value="0" placeholder="0">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 text-sm">km</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botoes de acao -->
        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" id="btnCancelar" class="btn-secondary py-2 px-4 rounded-md text-sm font-medium">
                <?= t('modules.manutencao_plano.btn_cancel') ?>
            </button>
            <button type="submit" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center">
                <i class="fas fa-save mr-2"></i><?= t('modules.manutencao_plano.btn_save') ?>
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<?php
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<script>
(function() {
    let currentId = null;
    let isEditMode = false;

    // Traducoes
    const translations = {
        titleNew: <?= json_encode(t('modules.manutencao_plano.title_new'), $jsonFlags) ?>,
        titleEdit: <?= json_encode(t('modules.manutencao_plano.title_edit'), $jsonFlags) ?>,
        nameRequired: <?= json_encode(t('modules.manutencao_plano.messages.name_required'), $jsonFlags) ?>,
        loadError: <?= json_encode(t('modules.manutencao_plano.messages.load_error'), $jsonFlags) ?>,
        saveError: <?= json_encode(t('modules.manutencao_plano.messages.save_error'), $jsonFlags) ?>
    };

    const form = document.getElementById('formPlano');
    const pageTitle = document.getElementById('pageTitle');
    const tipoVeiculoSelect = document.getElementById('tipoVeiculo');

    // ===== NAVEGACAO =====

    function navegarParaLista() {
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'navigate',
                page: '/pages/manutencoes-planos'
            }, '*');
        } else {
            window.location.href = '/pages/manutencoes-planos';
        }
    }

    document.getElementById('btnVoltar')?.addEventListener('click', navegarParaLista);
    document.getElementById('btnCancelar')?.addEventListener('click', navegarParaLista);

    // ===== TOGGLE TIPO VEICULO =====

    function toggleTipoVeiculo(tipo) {
        // Mostrar/ocultar itens exclusivos de carro
        document.querySelectorAll('[data-tipo="carro"]').forEach(el => {
            if (tipo === 'C') {
                el.style.display = '';
            } else {
                el.style.display = 'none';
                // Zerar inputs ocultos
                el.querySelectorAll('.input-km').forEach(input => { input.value = '0'; });
            }
        });

        // Mostrar/ocultar itens exclusivos de moto
        document.querySelectorAll('[data-tipo="moto"]').forEach(el => {
            if (tipo === 'M') {
                el.style.display = '';
            } else {
                el.style.display = 'none';
                // Zerar inputs ocultos
                el.querySelectorAll('.input-km').forEach(input => { input.value = '0'; });
            }
        });
    }

    tipoVeiculoSelect.addEventListener('change', function() {
        toggleTipoVeiculo(this.value);
    });

    // Aplicar estado inicial
    toggleTipoVeiculo(tipoVeiculoSelect.value);

    // ===== MODO EDICAO =====

    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('id');

    if (editId) {
        isEditMode = true;
        currentId = editId;
        carregarPlano(editId);
    }

    async function carregarPlano(id) {
        try {
            const result = await API.get(`/api/manutencoes-planos/${id}`);

            if (!result.success) {
                window.parent.postMessage({ action: 'openAlert', message: translations.loadError }, '*');
                navegarParaLista();
                return;
            }

            const p = result.data;

            // Preencher formulario
            document.getElementById('planoId').value = p.id;
            document.getElementById('planoNome').value = p.nome || '';
            tipoVeiculoSelect.value = p.tipo_veiculo || 'C';
            document.getElementById('planoStatus').value = p.status || 'A';

            // Aplicar toggle antes de preencher intervalos
            toggleTipoVeiculo(tipoVeiculoSelect.value);

            // Preencher intervalos
            if (p.intervalos) {
                for (const [key, value] of Object.entries(p.intervalos)) {
                    const input = document.querySelector(`input[name="intervalos[${key}]"]`);
                    if (input) {
                        input.value = formatarKm(value);
                    }
                }
            }

            // Atualizar titulo
            pageTitle.textContent = translations.titleEdit;

        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: translations.loadError }, '*');
            navegarParaLista();
        }
    }

    // ===== MASCARA DE KM =====

    function formatarKm(valor) {
        if (!valor || valor === '0') return '0';
        // Remove pontos existentes e formata
        const num = parseInt(String(valor).replace(/\D/g, ''));
        if (isNaN(num)) return '0';
        return window.Km ? Km.format(num) : String(num);
    }

    function parseKm(valor) {
        if (!valor) return 0;
        return parseInt(String(valor).replace(/\D/g, '')) || 0;
    }

    // ===== SUBMISSAO DO FORMULARIO =====

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validar nome
        const nome = document.getElementById('planoNome').value.trim();
        if (!nome) {
            window.parent.postMessage({ action: 'openAlert', message: translations.nameRequired }, '*');
            document.getElementById('planoNome').focus();
            return;
        }

        // Coletar intervalos (apenas dos campos visiveis + compartilhados)
        const intervalos = {};
        document.querySelectorAll('.input-km').forEach(input => {
            const name = input.name.match(/intervalos\[(.+)\]/);
            if (name && name[1]) {
                intervalos[name[1]] = parseKm(input.value);
            }
        });

        // Montar dados
        const dados = {
            nome: nome,
            tipo_veiculo: tipoVeiculoSelect.value,
            status: document.getElementById('planoStatus').value,
            intervalos: intervalos
        };

        try {
            let result;
            if (isEditMode && currentId) {
                result = await API.post(`/manutencoes-planos/${currentId}/atualizar`, dados);
            } else {
                result = await API.post('/manutencoes-planos/salvar', dados);
            }

            if (result.success) {
                window.parent.postMessage({ action: 'openAlert', message: result.message }, '*');
                navegarParaLista();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || translations.saveError }, '*');
            }
        } catch (error) {
            console.error('Erro:', error);
            window.parent.postMessage({ action: 'openAlert', message: translations.saveError + ': ' + error.message }, '*');
        }
    });
})();
</script>
@endsection
