/* Registro de odômetro com prévia oficial e chave de reenvio estável. */
(() => {
    'use strict';
    const contrato = new URLSearchParams(location.search).get('id');
    const escape = value => { const el = document.createElement('span'); el.textContent = String(value ?? ''); return el.innerHTML; };
    const dinheiro = value => Currency.format(value, true);
    const data = value => DateHelper.formatOperationalDateTime(value);
    const aviso = message => window.parent.postMessage({action: 'openAlert', message}, '*');
    document.querySelectorAll('[data-km-cobranca="1"]').forEach(card => {
        const input = card.querySelector('.odometro-input');
        const button = card.querySelector('.btn-salvar-odometro');
        const resumo = card.querySelector('.km-apuracao');
        const financeiro = card.querySelector('.km-financeiro');
        const oldSummary = card.querySelector('.km-rodado-label')?.parentElement?.parentElement;
        if (oldSummary) oldSummary.hidden = true;
        let preview = null, revision = 0, timer, fronteira = null, operation = crypto.randomUUID(), sending = false;
        const payload = () => ({
            id_contrato_veiculo: Number(card.dataset.id),
            odometro: Km.parse(input.value || card.dataset.odometroMinimo || '0'),
            obs: card.querySelector('.odometro-obs').value,
            fronteira_km: fronteira ? 1 : 0,
            ...(fronteira ? {data_referencia: fronteira} : {})
        });
        const labelButton = () => {
            button.textContent = preview?.total > 0
                ? `Registrar odômetro e gerar fatura de ${dinheiro(preview.total)}`
                : 'Registrar odômetro';
        };
        async function refresh() {
            const version = ++revision;
            preview = null; button.disabled = true; financeiro.hidden = true;
            try {
                const result = await API.post(`/api/contratos/${contrato}/odometros/preview`, payload());
                if (version !== revision) return;
                if (!result.success) throw new Error(result.message || 'Não foi possível calcular a prévia.');
                preview = result.data;
                const active = preview.ciclos.at(-1);
                const pending = preview.pendencias || [];
                resumo.innerHTML = (fronteira ? `<div class="mb-3 font-semibold">Leitura da virada: ${escape(data(fronteira))} <button type="button" class="km-cancelar-fronteira text-blue-700">Cancelar</button></div>` : '') +
                    (active ? `<div class="font-semibold">Resumo do ciclo</div><div class="text-xs text-slate-500">Contagem: ${escape(card.dataset.contagem)}</div>
                        <div class="mb-2 text-xs text-slate-500">${escape(data(active.inicio))} a ${escape(data(active.fim))}</div>
                        <div class="flex justify-between"><span>Km utilizados</span><strong>${active.rodados === null ? 'Pendente' : escape(Km.format(active.rodados)) + ' km'}</strong></div>
                        <div class="mt-1 flex justify-between"><span>Franquia</span><strong>${escape(Km.format(active.franquia))} km</strong></div>
                        <div class="mt-1 flex justify-between"><span>Excedente total</span><strong>${active.pendente ? 'Pendente' : escape(Km.format(active.excedente)) + ' km'}</strong></div>
                        <div class="mt-1 flex justify-between"><span>Já faturado neste ciclo</span><strong>${escape(dinheiro(active.faturado))}</strong></div>
                        <div class="mt-1 flex justify-between"><span>Valor por km</span><strong>${escape(dinheiro(active.valor_km))}</strong></div>` : '') +
                    preview.ciclos.filter(c => c.saldo > 0).map(c => `<div class="mt-2 border-t border-slate-200 pt-2 text-sm">${escape(data(c.inicio))}: <strong>${escape(dinheiro(c.saldo))} a faturar</strong></div>`).join('') +
                    (pending.length ? `<details class="mt-3 text-amber-800" open><summary>Apuração pendente (${pending.length})</summary><p class="my-2 text-xs">Informe a leitura real de cada virada. Os períodos incompletos não serão faturados.</p>${pending.map(p => `<button type="button" data-fronteira="${escape(p)}" class="km-fronteira block py-1 text-left text-sm underline">Informar leitura de ${escape(data(p))}</button>`).join('')}</details>` : '');
                financeiro.hidden = !(preview.total > 0);
                card.querySelector('.km-total').textContent = dinheiro(preview.total);
                labelButton(); button.disabled = sending;
            } catch (error) {
                if (version !== revision) return;
                resumo.textContent = error.message; button.disabled = true;
            }
        }
        input.addEventListener('input', () => {
            if (sending) return;
            revision++; preview = null; button.disabled = true;
            clearTimeout(timer); timer = setTimeout(refresh, 350);
        });
        resumo.addEventListener('click', event => {
            if (sending) return;
            const target = event.target.closest('[data-fronteira]');
            if (target) { fronteira = target.dataset.fronteira; operation = crypto.randomUUID(); input.value = ''; input.focus(); preview = null; button.disabled = true;
                resumo.innerHTML = `<strong>Leitura real da virada: ${escape(data(fronteira))}</strong><button type="button" class="km-cancelar-fronteira block text-blue-700">Cancelar</button>`;
                financeiro.hidden = true;
            }
            if (event.target.closest('.km-cancelar-fronteira')) { fronteira = null; input.value = ''; operation = crypto.randomUUID(); refresh(); }
        });
        button.addEventListener('click', async () => {
            if (!preview || sending || !input.value.trim()) { aviso('Informe o odômetro e aguarde a prévia.'); return; }
            const entry = {...payload(), data_referencia: preview.data_referencia, apuracao_em: preview.apuracao_em,
                versao: preview.versao, operacao_km: operation,
                financeiro: {id_conta: card.querySelector('.km-conta').value,
                    id_forma_pagamento: card.querySelector('.km-forma').value, data_venci: card.querySelector('.km-vencimento').value}};
            if (preview.total > 0 && (!entry.financeiro.id_conta || !entry.financeiro.id_forma_pagamento || !entry.financeiro.data_venci)) {
                aviso('Preencha conta, forma de pagamento e vencimento.'); return;
            }
            sending = true; button.disabled = true; input.readOnly = true;
            try {
                const result = await API.post(`/api/contratos/${contrato}/odometros${fronteira ? '/fronteira' : ''}`, entry);
                if (!result.success) throw new Error(result.message || 'Não foi possível salvar.');
                window.parent.postMessage({action: 'contratoOdometroRegistrado', contratoId: Number(contrato)}, '*');
                window.parent.postMessage({action: 'openAlert', type: 'success', message: result.data?.fatura ? `Odômetro registrado. Fatura #${result.data.fatura.sequencia} criada: ${dinheiro(result.data.fatura.valor_total)}.` : 'Odômetro registrado.'}, '*');
                location.reload();
            } catch (error) { aviso(error.message); if (error.message.includes('prévia mudou')) await refresh(); }
            finally { sending = false; input.readOnly = false; button.disabled = !preview; labelButton(); }
        });
        refresh();
    });
})();
