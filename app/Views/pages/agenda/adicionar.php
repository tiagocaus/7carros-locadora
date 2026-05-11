@extends('layouts.iframe')

@section('title', isset($agenda) ? 'Editar Agenda' : 'Nova Agenda')

@section('content')
<?php
    $isEdit = isset($agenda);
    $dataIni = $isEdit ? str_replace(' ', 'T', substr($agenda['data_ini'], 0, 16)) : '';
    $dataFim = $isEdit ? str_replace(' ', 'T', substr($agenda['data_fim'], 0, 16)) : '';
    $cores = [
        'agenda_roxo' => ['Roxo', '#6f42c1'],
        'agenda_azul' => ['Azul', '#007bff'],
        'agenda_verde' => ['Verde', '#28a745'],
        'agenda_verde_claro' => ['Verde Claro', '#90ee90'],
        'agenda_vermelho' => ['Vermelho', '#dc3545'],
        'agenda_laranja' => ['Laranja', '#fd7e14'],
        'agenda_cinza' => ['Cinza', '#6c757d'],
    ];
?>
<div class="p-4">
    <form id="agendaForm" class="space-y-4">
        <input type="hidden" id="agendaId" value="<?= $isEdit ? (int) $agenda['id'] : '' ?>">

        <div>
            <label for="titulo" class="block text-sm font-medium text-slate-700 mb-1">Título <span class="text-red-500">*</span></label>
            <input type="text" id="titulo" name="titulo" required maxlength="50"
                value="<?= $isEdit ? htmlspecialchars($agenda['titulo']) : '' ?>"
                class="form-input-focus w-full">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="data_ini" class="block text-sm font-medium text-slate-700 mb-1">Data Início <span class="text-red-500">*</span></label>
                <input type="datetime-local" id="data_ini" name="data_ini" required
                    value="<?= $dataIni ?>" class="form-input-focus w-full">
            </div>
            <div>
                <label for="data_fim" class="block text-sm font-medium text-slate-700 mb-1">Data Fim <span class="text-red-500">*</span></label>
                <input type="datetime-local" id="data_fim" name="data_fim" required
                    value="<?= $dataFim ?>" class="form-input-focus w-full">
            </div>
        </div>

        <div>
            <label for="label" class="block text-sm font-medium text-slate-700 mb-1">Categoria / Label</label>
            <input type="text" id="label" name="label" maxlength="255"
                value="<?= $isEdit ? htmlspecialchars($agenda['label'] ?? '') : '' ?>"
                class="form-input-focus w-full"
                placeholder="Ex: Reunião, Vistoria, Evento...">
        </div>

        <div>
            <label for="cor" class="block text-sm font-medium text-slate-700 mb-1">Cor</label>
            <select id="cor" name="cor" class="form-input-focus w-full">
                <?php $corAtual = $isEdit ? ($agenda['cor'] ?? 'agenda_roxo') : 'agenda_roxo'; ?>
                <?php foreach ($cores as $key => [$nome, $hex]): ?>
                    <option value="<?= $key ?>" <?= $corAtual === $key ? 'selected' : '' ?>><?= $nome ?></option>
                <?php endforeach; ?>
            </select>
            <div class="mt-2 h-3 rounded" id="corPreview" style="background-color: <?= $cores[$corAtual][1] ?? '#6f42c1' ?>"></div>
        </div>

        <div>
            <label for="obs" class="block text-sm font-medium text-slate-700 mb-1">Observações</label>
            <textarea id="obs" name="obs" rows="4"
                class="form-input-focus w-full"><?= $isEdit ? htmlspecialchars($agenda['obs'] ?? '') : '' ?></textarea>
        </div>

        <div class="flex justify-between pt-4 border-t border-slate-200">
            <?php if ($isEdit): ?>
                <button type="button" id="btnExcluir" class="btn-red py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-trash mr-2"></i>Excluir
                </button>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <div class="flex gap-2">
                <button type="button" id="btnCancelar" class="py-2 px-4 rounded-md text-sm font-medium border border-slate-300 bg-white hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="btn-blue py-2 px-4 rounded-md text-sm font-medium">
                    <i class="fas fa-save mr-2"></i>Salvar
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
    const agendaId = document.getElementById('agendaId').value;
    const coresHex = <?= json_encode(array_map(fn($c) => $c[1], $cores)) ?>;

    // Atualiza preview de cor
    const corSelect = document.getElementById('cor');
    const corPreview = document.getElementById('corPreview');
    corSelect.addEventListener('change', () => {
        corPreview.style.backgroundColor = coresHex[corSelect.value] || '#6f42c1';
    });

    function fecharEpedirReload() {
        const parentWin = window.parent;
        if (!parentWin || parentWin === window) return;
        try {
            // Acha o iframe da aba agenda e pede reload via postMessage
            const agendaIframe = parentWin.document.querySelector('.tab-content[data-tab-content-id="agenda"] iframe');
            if (agendaIframe && agendaIframe.contentWindow) {
                agendaIframe.contentWindow.postMessage({ action: 'reloadAgendaAndClose' }, '*');
            }
        } catch (err) {
            console.warn('reloadAgenda postMessage failed:', err);
        }
        if (typeof parentWin.closeOffcanvas === 'function') {
            parentWin.closeOffcanvas();
        }
    }

    document.getElementById('btnCancelar').addEventListener('click', () => {
        if (window.parent && typeof window.parent.closeOffcanvas === 'function') {
            window.parent.closeOffcanvas();
        }
    });

    document.getElementById('agendaForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            titulo: document.getElementById('titulo').value.trim(),
            data_ini: document.getElementById('data_ini').value,
            data_fim: document.getElementById('data_fim').value,
            label: document.getElementById('label').value.trim(),
            cor: document.getElementById('cor').value,
            obs: document.getElementById('obs').value
        };
        if (!payload.titulo || !payload.data_ini || !payload.data_fim) {
            if (window.toast) toast.error('Preencha título, data início e data fim');
            return;
        }

        const url = isEdit ? `/agenda/${agendaId}/atualizar` : '/agenda/salvar';
        try {
            const resp = await API.post(url, payload);
            if (resp && resp.success) {
                if (window.toast) toast.success('Agenda salva!');
                fecharEpedirReload();
            } else {
                if (window.toast) toast.error((resp && resp.message) || 'Erro ao salvar');
            }
        } catch (err) {
            console.error(err);
            if (window.toast) toast.error('Erro ao salvar: ' + err.message);
        }
    });

    const btnExcluir = document.getElementById('btnExcluir');
    if (btnExcluir) {
        btnExcluir.addEventListener('click', async () => {
            if (!confirm('Excluir este evento da agenda?')) return;
            try {
                const resp = await API.post(`/agenda/${agendaId}/excluir`, {});
                if (resp && resp.success) {
                    if (window.toast) toast.success('Agenda excluída');
                    fecharEpedirReload();
                } else {
                    if (window.toast) toast.error((resp && resp.message) || 'Erro ao excluir');
                }
            } catch (err) {
                console.error(err);
                if (window.toast) toast.error('Erro ao excluir: ' + err.message);
            }
        });
    }
})();
</script>
@endsection
