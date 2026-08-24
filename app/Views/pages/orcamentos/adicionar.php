@extends('layouts.iframe')

@section('title', 'Orçamento')

@section('content')
<?php
    $editando = isset($orcamento);
    $data = $orcamento ?? [];
    $validadeDefault = (new DateTimeImmutable(\App\Helpers\DateHelper::todayForDatabase()))->modify('+3 days')->format('Y-m-d');
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
    $initialState = [
        'data_saida' => $data['data_saida'] ?? null,
        'data_prevista' => $data['data_prevista'] ?? null,
        'subtotal_diarias' => (float) ($data['subtotal_diarias'] ?? 0),
        'subtotal_adicionais' => (float) ($data['subtotal_adicionais'] ?? 0),
        'valor_desconto' => (float) ($data['valor_desconto'] ?? 0),
        'total_pagar' => (float) ($data['total_pagar'] ?? 0),
        'veiculo_label' => trim(($data['veiculo_placa'] ?? '') . ' - ' . ($data['veiculo_marca'] ?? '') . ' ' . ($data['veiculo_modelo'] ?? '')),
    ];
?>
<div class="pl-1 pr-2 py-0">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <div><h2 class="title-section"><?= $editando ? 'Editar orçamento' : 'Novo orçamento' ?></h2><?php if ($editando): ?><span class="font-mono text-sm text-slate-500"><?= e($data['codigo']) ?></span><?php endif; ?></div>
        <div class="flex flex-wrap gap-2">
            <?php if ($editando && \App\Core\Auth::can('orcamentos.enviar')): ?>
            <button type="button" data-send="email" class="btn-secondary py-2 px-3 rounded-md" title="Enviar por e-mail"><i class="fas fa-envelope"></i></button>
            <button type="button" data-send="whatsapp" class="btn-secondary py-2 px-3 rounded-md" title="Enviar por WhatsApp"><i class="fab fa-whatsapp"></i></button>
            <button type="button" data-send="sms" class="btn-secondary py-2 px-3 rounded-md" title="Enviar por SMS"><i class="fas fa-comment-alt"></i></button>
            <?php endif; ?>
            <?php if ($editando && \App\Core\Auth::can('orcamentos.imprimir')): ?>
            <button type="button" id="btnPdf" class="btn-secondary py-2 px-3 rounded-md" title="Visualizar PDF"><i class="fas fa-file-pdf"></i></button>
            <?php endif; ?>
            <button type="button" id="btnVoltar" class="btn-secondary py-2 px-4 rounded-md">Voltar</button><button type="button" id="btnSalvar" class="btn-blue py-2 px-4 rounded-md"><i class="fas fa-save mr-2"></i>Salvar orçamento</button>
        </div>
    </div>

    <?php if (($data['status'] ?? '') === 'C'): ?>
    <div class="mb-4 p-3 rounded-md bg-purple-50 text-purple-800"><i class="fas fa-check-circle mr-2"></i>Convertido na reserva <strong><?= e($data['locacao_codigo'] ?? '') ?></strong>. Este orçamento é somente leitura.</div>
    <?php endif; ?>

    <form id="orcamentoForm" class="space-y-4">
        <div class="form-section">
            <h3 class="form-section-title"><i class="fas fa-info-circle mr-2"></i>Identificação comercial</h3>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-2 form-input-group"><label class="form-label-group" for="status">Status</label><select id="status" class="form-input-group-field"><option value="R">Rascunho</option><option value="E">Enviado</option><option value="A">Aceito</option><option value="N">Recusado</option></select></div>
                <div class="md:col-span-2 form-input-group"><label class="form-label-group" for="validade">Validade <span class="text-red-500">*</span></label><input type="date" id="validade" class="form-input-group-field" value="<?= e($data['validade'] ?? $validadeDefault) ?>" required></div>
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="origem">Origem</label><select id="origem" class="form-input-group-field"><option value="">Não informada</option><option>WhatsApp</option><option>Telefone</option><option>E-mail</option><option>Site</option><option>Presencial</option><option>Indicação</option></select></div>
                <div class="md:col-span-5 form-input-group"><label class="form-label-group" for="id_cliente">Cliente <span class="text-red-500">*</span></label><select id="id_cliente" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/clientes/buscar" data-chosen-placeholder="Digite nome, CPF/CNPJ ou telefone" required><?php if (!empty($data['id_cliente'])): ?><option value="<?= (int)$data['id_cliente'] ?>" selected><?= e($data['cliente_nome']) ?></option><?php endif; ?></select></div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><i class="fas fa-calendar-alt mr-2"></i>Período e local</h3>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="filial_retirada">Filial de retirada <span class="text-red-500">*</span></label><select id="filial_retirada" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" required><?php if (!empty($data['id_matriz_filial_retirada'])): ?><option value="<?= (int)$data['id_matriz_filial_retirada'] ?>" selected><?= e($data['filial_retirada_nome']) ?></option><?php endif; ?></select></div>
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="filial_devolucao">Filial de devolução <span class="text-red-500">*</span></label><select id="filial_devolucao" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/matrizes-filiais/buscar" required><?php if (!empty($data['id_matriz_filial_devolucao'])): ?><option value="<?= (int)$data['id_matriz_filial_devolucao'] ?>" selected><?= e($data['filial_devolucao_nome']) ?></option><?php endif; ?></select></div>
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="data_saida">Retirada <span class="text-red-500">*</span></label><input type="datetime-local" id="data_saida" class="form-input-group-field" required></div>
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="data_prevista">Devolução <span class="text-red-500">*</span></label><input type="datetime-local" id="data_prevista" class="form-input-group-field" required></div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><i class="fas fa-car mr-2"></i>Veículo e plano</h3>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="id_grupo">Grupo <span class="text-red-500">*</span></label><select id="id_grupo" class="form-input-group-field chosen-select" required><?php if (!empty($data['id_grupo'])): ?><option value="<?= (int)$data['id_grupo'] ?>" selected><?= e($data['grupo_nome']) ?></option><?php endif; ?></select></div>
                <div class="md:col-span-4 form-input-group"><label class="form-label-group" for="id_veiculo">Veículo preferencial <?= aviso('Opcional. Não bloqueia disponibilidade; a reserva continua sendo feita pelo grupo.') ?></label><select id="id_veiculo" class="form-input-group-field chosen-select"><option value="">Nenhuma preferência</option><?php if (!empty($data['id_veiculo'])): ?><option value="<?= (int)$data['id_veiculo'] ?>" selected><?= e(trim(($data['veiculo_placa'] ?? '') . ' - ' . ($data['veiculo_marca'] ?? '') . ' ' . ($data['veiculo_modelo'] ?? ''))) ?></option><?php endif; ?></select></div>
                <div class="md:col-span-2 form-input-group"><label class="form-label-group" for="plano">Plano</label><select id="plano" class="form-input-group-field"><option value="KL">Km Livre</option><option value="KMC">Km Controlado</option><option value="DI">Km Pago</option></select></div>
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="diaria_valor">Valor médio da diária <?= aviso('Calculado pelo preço progressivo e pelas temporadas. Marque como manual apenas para uma negociação específica.') ?></label><div class="flex gap-2"><input type="text" id="diaria_valor" class="form-input-group-field input-moeda" value="<?= e(currency_format($data['diaria_valor'] ?? 0, false)) ?>"><label class="inline-flex items-center text-xs"><input type="checkbox" id="diaria_manual" <?= $editando ? 'checked' : '' ?> class="mr-1">Manual</label></div></div>
            </div>
            <div id="kmcFields" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 hidden">
                <div class="form-input-group"><label class="form-label-group" for="km_franquia">Franquia de km por dia</label><input type="number" id="km_franquia" class="form-input-group-field" value="<?= e($data['km_franquia'] ?? 0) ?>"></div>
                <div class="form-input-group"><label class="form-label-group" for="valor_km_excedente">Valor do km excedente</label><input type="text" id="valor_km_excedente" class="form-input-group-field input-moeda" value="<?= e(currency_format($data['valor_km_excedente'] ?? 0, false)) ?>"></div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><i class="fas fa-shield-alt mr-2"></i>Proteções, taxas e serviços</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-center gap-3 p-3 border rounded-md"><input type="checkbox" id="seguro_carro" <?= !empty($data['seguro_carro']) ? 'checked' : '' ?>><span class="flex-1">Proteção do veículo</span><input type="text" id="valor_seguro_carro" class="form-input-group-field input-moeda w-36" value="<?= e(currency_format($data['valor_seguro_carro'] ?? 0, false)) ?>"></label>
                <label class="flex items-center gap-3 p-3 border rounded-md"><input type="checkbox" id="seguro_terceiros" <?= !empty($data['seguro_terceiros']) ? 'checked' : '' ?>><span class="flex-1">Proteção para terceiros</span><input type="text" id="valor_seguro_terceiros" class="form-input-group-field input-moeda w-36" value="<?= e(currency_format($data['valor_seguro_terceiros'] ?? 0, false)) ?>"></label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mt-4 items-end">
                <div class="md:col-span-7 form-input-group"><label class="form-label-group" for="taxaSelect">Taxa ou serviço</label><select id="taxaSelect" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/taxas-e-servicos/buscar" data-chosen-placeholder="Pesquisar taxa ou serviço"></select></div>
                <div class="md:col-span-2 form-input-group"><label class="form-label-group" for="taxaQtd">Quantidade</label><input type="number" id="taxaQtd" class="form-input-group-field" min="1" value="1"></div>
                <button type="button" id="btnAdicionarTaxa" class="md:col-span-3 btn-secondary py-2 rounded-md"><i class="fas fa-plus mr-2"></i>Adicionar</button>
            </div>
            <div id="taxasLista" class="mt-3 space-y-2"></div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><i class="fas fa-handshake mr-2"></i>Condições comerciais</h3>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="id_conta">Conta/Caixa <?= aviso('Não gera financeiro. É necessária apenas para converter o orçamento em reserva.') ?></label><select id="id_conta" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/contas-bancarias/buscar"><?php if (!empty($data['id_conta'])): ?><option value="<?= (int)$data['id_conta'] ?>" selected><?= e($data['conta_nome']) ?></option><?php endif; ?></select></div>
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="id_forma_pagamento">Forma pretendida</label><select id="id_forma_pagamento" class="form-input-group-field chosen-select" data-chosen-type="server-side" data-chosen-search-url="/api/formas-pagamento/select"><?php if (!empty($data['id_forma_pagamento'])): ?><option value="<?= (int)$data['id_forma_pagamento'] ?>" selected><?= e($data['forma_pagamento_nome']) ?></option><?php endif; ?></select></div>
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="condicao_pagamento">Condição de pagamento</label><input type="text" id="condicao_pagamento" maxlength="150" class="form-input-group-field" value="<?= e($data['condicao_pagamento'] ?? '') ?>" placeholder="Ex.: até 3x sem juros"></div>
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="promocao_codigo">Código promocional</label><input type="text" id="promocao_codigo" class="form-input-group-field uppercase" value="<?= e($data['promocao_codigo'] ?? '') ?>"></div>
                <div class="md:col-span-3 form-input-group"><label class="form-label-group" for="valor_desconto">Desconto manual</label><input type="text" id="valor_desconto" class="form-input-group-field input-moeda" value="<?= e(currency_format($data['valor_desconto'] ?? 0, false)) ?>"></div>
                <div class="md:col-span-9 form-input-group"><label class="form-label-group" for="observacoes_cliente">Observações ao cliente</label><textarea id="observacoes_cliente" rows="2" class="form-input-group-field"><?= e($data['observacoes_cliente'] ?? 'Valores sujeitos à disponibilidade no momento da confirmação.') ?></textarea></div>
                <div class="md:col-span-12 form-input-group"><label class="form-label-group" for="observacoes_internas">Observações internas <?= aviso('Não aparecem no PDF entregue ao cliente.') ?></label><textarea id="observacoes_internas" rows="2" class="form-input-group-field"><?= e($data['observacoes_internas'] ?? '') ?></textarea></div>
            </div>
        </div>

        <div class="form-section bg-slate-50">
            <h3 class="form-section-title"><i class="fas fa-file-invoice-dollar mr-2"></i>Resumo</h3>
            <div class="max-w-md ml-auto space-y-2 text-sm">
                <div class="flex justify-between"><span>Diárias</span><strong id="resumoDiarias">-</strong></div>
                <div class="flex justify-between"><span>Proteções, taxas e serviços</span><strong id="resumoAdicionais">-</strong></div>
                <div class="flex justify-between text-red-600"><span>Desconto</span><strong id="resumoDesconto">-</strong></div>
                <div class="flex justify-between border-t pt-2 text-lg"><span>Total</span><strong id="resumoTotal">-</strong></div>
            </div>
            <p class="mt-3 text-xs text-slate-500 text-right">Este orçamento não garante disponibilidade até sua conversão em reserva.</p>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const editingId = <?= $editando ? (int)$data['id'] : 'null' ?>;
    const readOnly = <?= (($data['status'] ?? '') === 'C') ? 'true' : 'false' ?>;
    const initialState = <?= json_encode($initialState, $jsonFlags) ?>;
    let taxas = <?= json_encode(array_map(fn($t) => ['id_taxa'=>(int)($t['id_taxa'] ?? 0),'nome'=>$t['nome'] ?? 'Taxa','quantidade'=>(int)($t['quantidade'] ?? 1)], is_array($data['taxas'] ?? null) ? $data['taxas'] : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let calculateTimer = null;
    const $ = id => document.getElementById(id);
    $('status').value = <?= json_encode($data['status'] ?? 'R') ?>;
    $('origem').value = <?= json_encode($data['origem'] ?? '') ?>;
    $('plano').value = <?= json_encode($data['plano'] ?? 'KL') ?>;
    $('data_saida').value = DateHelper.toOperationalDateTimeInput(initialState.data_saida);
    $('data_prevista').value = DateHelper.toOperationalDateTimeInput(initialState.data_prevista);

    function notify(message) { window.parent.postMessage({action:'openAlert', message}, '*'); }
    function navigate(url) { window.parent !== window ? window.parent.postMessage({action:'navigate',page:url}, '*') : window.location.href=url; }
    function money(value) { return Currency.format(Number(value || 0), true); }
    function rawMoney(id) { return $(id).value || '0'; }
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    function refreshChosen(select) {
        if (select?.chosenSelect) select.chosenSelect.refresh();
    }
    function clearChosen(select) {
        if (select?.chosenSelect) {
            select.chosenSelect.clear();
            return;
        }
        if (select) {
            select.value = '';
            select.dispatchEvent(new Event('change', {bubbles:true}));
        }
    }
    function formData() { return {
        id_orcamento:editingId,
        status:$('status').value, validade:$('validade').value, origem:$('origem').value,
        id_cliente:$('id_cliente').value, id_matriz_filial_retirada:$('filial_retirada').value, id_matriz_filial_devolucao:$('filial_devolucao').value,
        data_saida:$('data_saida').value, data_prevista:$('data_prevista').value, id_grupo:$('id_grupo').value, id_veiculo:$('id_veiculo').value,
        plano:$('plano').value, diaria_valor:rawMoney('diaria_valor'), diaria_valor_origem:$('diaria_manual').checked?'manual':'auto',
        km_franquia:$('km_franquia').value, valor_km_excedente:rawMoney('valor_km_excedente'),
        seguro_carro:$('seguro_carro').checked?'S':'N', valor_seguro_carro:rawMoney('valor_seguro_carro'),
        seguro_terceiros:$('seguro_terceiros').checked?'S':'N', valor_seguro_terceiros:rawMoney('valor_seguro_terceiros'),
        id_conta:$('id_conta').value, id_forma_pagamento:$('id_forma_pagamento').value, condicao_pagamento:$('condicao_pagamento').value,
        promocao_codigo:$('promocao_codigo').value, valor_desconto:rawMoney('valor_desconto'), taxas,
        observacoes_cliente:$('observacoes_cliente').value, observacoes_internas:$('observacoes_internas').value
    }; }

    async function loadGroups() {
        try {
            const result = await API.get('/api/grupos', {page:1,perPage:100}); if (!result.success) return;
            const current = $('id_grupo').value;
            result.data.forEach(g => { if ([...$('id_grupo').options].some(o=>o.value==g.id)) return; const o=new Option(g.nome,g.id); $('id_grupo').add(o); });
            $('id_grupo').value=current; refreshChosen($('id_grupo'));
        } catch (_) {}
    }

    async function loadVehicles() {
        const select=$('id_veiculo'),group=$('id_grupo').value,branch=$('filial_retirada').value,current=select.value;
        const currentOption=[...select.options].find(option=>option.value===String(current));
        const currentLabel=currentOption?.textContent?.trim()||initialState.veiculo_label||'Veículo preferencial';
        select.innerHTML='<option value="">Nenhuma preferência</option>';
        if (group && branch) {
            const result=await API.get('/api/veiculos/por-grupo',{id_grupo:group,id_filial:branch,contexto:'reserva'});
            (result.data||[]).forEach(v=>select.add(new Option([v.placa,v.marca,v.modelo].filter(Boolean).join(' - '),v.id)));
        }
        if(current&&![...select.options].some(option=>option.value===String(current)))select.add(new Option(currentLabel,current));
        select.value=current; refreshChosen(select);
    }

    async function loadGroupDefaults() {
        const group=$('id_grupo').value, branch=$('filial_retirada').value; if(!group||!branch)return;
        try {
            const result=await API.get(`/api/grupos/${group}/precos-filial/${branch}`); const v=result.data?.valores||{};
            if (!editingId) {
                $('valor_seguro_carro').value = Currency.format(v.valor_seguro_carro || 0);
                $('valor_seguro_terceiros').value = Currency.format(v.valor_seguro_terceiros || 0);
                $('km_franquia').value = v.km_franquia || v.km_controlado_franquia || 0;
                $('valor_km_excedente').value = Currency.format(v.valor_km_excedente || v.valor_km_controlado || 0);
            }
        } catch (_) {}
    }

    function renderFees() {
        $('taxasLista').innerHTML = taxas.length ? taxas.map((t,i)=>`<div class="flex items-center justify-between p-2 bg-slate-50 rounded"><span>${escapeHtml(t.nome||'Taxa')} <small class="text-slate-500">× ${t.quantidade}</small></span><button type="button" class="text-red-600" data-remove-fee="${i}"><i class="fas fa-times"></i></button></div>`).join('') : '<p class="text-sm text-slate-500">Nenhuma taxa ou serviço adicionado.</p>';
    }

    function renderSummary(values) {
        $('resumoDiarias').textContent=money(values.subtotal_diarias);
        $('resumoAdicionais').textContent=money(values.subtotal_adicionais);
        $('resumoDesconto').textContent='- '+money(values.valor_desconto);
        $('resumoTotal').textContent=money(values.total_pagar);
    }

    function updatePlanFields() {
        $('kmcFields').classList.toggle('hidden',$('plano').value!=='KMC');
    }

    async function calculate(showError=false) {
        const d=formData(); if(!d.id_cliente||!d.id_matriz_filial_retirada||!d.id_matriz_filial_devolucao||!d.data_saida||!d.data_prevista||!d.id_grupo)return null;
        try {
            const result=await API.post('/api/orcamentos/calcular',d); if(!result.success)throw new Error(result.message);
            const c=result.data; if(!$('diaria_manual').checked)$('diaria_valor').value=Currency.format(c.diaria_valor);
            renderSummary(c); return c;
        } catch(error){if(showError)notify(error.message||'Não foi possível calcular o orçamento.');return null;}
    }
    function scheduleCalculate(){clearTimeout(calculateTimer);calculateTimer=setTimeout(()=>calculate(false),350);}

    $('btnAdicionarTaxa').addEventListener('click',()=>{const select=$('taxaSelect'),option=select.selectedOptions[0];if(!option?.value)return notify('Selecione uma taxa ou serviço.');taxas.push({id_taxa:Number(option.value),nome:option.textContent,quantidade:Math.max(1,Number($('taxaQtd').value||1))});renderFees();clearChosen(select);$('taxaQtd').value='1';scheduleCalculate();});
    $('taxasLista').addEventListener('click',e=>{const b=e.target.closest('[data-remove-fee]');if(!b)return;taxas.splice(Number(b.dataset.removeFee),1);renderFees();scheduleCalculate();});
    $('plano').addEventListener('change',()=>{updatePlanFields();scheduleCalculate();});
    ['id_cliente','filial_retirada','filial_devolucao','data_saida','data_prevista','id_grupo','id_veiculo','diaria_valor','diaria_manual','seguro_carro','valor_seguro_carro','seguro_terceiros','valor_seguro_terceiros','promocao_codigo','valor_desconto'].forEach(id=>$(id).addEventListener('change',scheduleCalculate));
    $('id_grupo').addEventListener('change',async()=>{await loadVehicles();await loadGroupDefaults();scheduleCalculate();});
    $('filial_retirada').addEventListener('change',async()=>{await loadVehicles();await loadGroupDefaults();scheduleCalculate();});

    $('btnSalvar').addEventListener('click',async()=>{const calculated=await calculate(true);if(!calculated)return;const url=editingId?`/orcamentos/${editingId}/atualizar`:'/orcamentos/salvar';const result=await API.post(url,formData());if(result.success){notify(result.message);navigate('/pages/orcamentos');}else notify(result.message||'Não foi possível salvar.');});
    document.querySelectorAll('[data-send]').forEach(button=>button.addEventListener('click',async()=>{button.disabled=true;const result=await API.post(`/orcamentos/${editingId}/enviar`,{canal:button.dataset.send});button.disabled=false;notify(result.message||'Não foi possível enviar o orçamento.');if(result.success&&$('status').value!=='C')$('status').value='E';}));
    $('btnPdf')?.addEventListener('click',()=>window.parent.postMessage({action:'openPrintModal',url:`/orcamentos/${editingId}/imprimir`,title:'Orçamento <?= e($data['codigo'] ?? '') ?>'}, '*'));
    $('btnVoltar').addEventListener('click',()=>navigate('/pages/orcamentos'));
    if(readOnly){document.querySelectorAll('input,select,textarea,button:not(#btnVoltar):not(#btnPdf):not([data-send])').forEach(el=>el.disabled=true);}
    renderFees();
    updatePlanFields();
    if(editingId)renderSummary(initialState);else calculate(false);
    loadGroups().then(()=>loadVehicles());
})();
</script>
@endsection
