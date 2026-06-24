(function () {
    // ===== i18n =====
    const i18n = window.i18n_contratos || {};

    // ===== ESTADO =====
    let editando = false;
    let registroId = null;

    // Dados do contrato
    let veiculos = [];
    let condutores = [];
    let fiadores = [];
    let avalistas = [];
    let testemunhas = [];
    let taxas = [];
    let parcelas = []; // Parcelas financeiras
    let parcelasOriginais = []; // Para detectar alteracoes
    let confirmacaoPendente = null;
    let parcelaAvulsaRascunho = null;
    let parcelaContratoAcaoPendente = null;
    let parcelaContratoEdicaoIndex = null;

    // Cache de dados
    let gruposDisponiveis = [];
    let veiculosDisponiveis = [];
    let taxasDisponiveis = [];
    let valoresGrupoCache = {};
    let taxaSelecionadaAtual = null; // Armazena dados da taxa selecionada no select
    let formasPagamentoList = []; // Flat list para selects de parcelas: [{id, nome}]
    let comandosParcelasList = []; // Lista de comandos: [{id, comando, descricao, label}]
    let contasBancariasList = []; // Lista de contas: [{id, nome}]

    // Elementos
    const form = document.getElementById('formContrato');
    const pageTitle = document.getElementById('pageTitle');
    const inputId = document.getElementById('registroId');

    // ===== NAVEGACAO =====

    function navegarPara(page) {
        if (window.parent !== window) {
            window.parent.postMessage({ action: 'navigate', page: page }, '*');
        } else {
            window.location.href = page;
        }
    }

    function voltar() {
        navegarPara('/pages/contratos');
    }

    function abrirLancamentoFinanceiro(idFinanceiro) {
        if (!idFinanceiro) return;

        const page = `/pages/financeiro/adicionar/${encodeURIComponent(idFinanceiro)}`;
        if (window.parent !== window && typeof window.parent.openOrSwitchToTab === 'function') {
            window.parent.openOrSwitchToTab(page, 'Lançamentos', 'fas fa-dollar-sign', `financeiro-${idFinanceiro}`);
            return;
        }

        window.location.href = page;
    }

    // ===== INICIALIZACAO =====

    async function init() {
        // Registrar event listeners ANTES de carregar dados
        // (necessario para que o dispatchEvent funcione ao editar)
        configurarEventos();

        // Carregar dados iniciais
        await Promise.all([
            carregarGrupos(),
            carregarTaxasDisponiveis(),
            carregarFormasPagamento(),
            carregarComandosParcelas(),
            carregarContasBancarias()
        ]);

        // Verificar se estamos editando (suporta /editar/{id} e /adicionar/{id} para compatibilidade)
        const pathMatchEditar = window.location.pathname.match(/\/contratos\/editar\/(\d+)/);
        const pathMatchAdicionar = window.location.pathname.match(/\/contratos\/adicionar\/(\d+)/);
        const pathMatch = pathMatchEditar || pathMatchAdicionar;
        if (pathMatch) {
            registroId = pathMatch[1];
            editando = true;
            if (pageTitle) pageTitle.textContent = i18n.editTitle || 'Editar Contrato';
            await carregarDados(registroId);
            // Configurar toggle da secao de config de pagamento (modo edicao)
            configurarToggleConfigPagamento();
        } else {
            // Novo contrato: preencher datas padrao
            definirDatasPadrao();
        }

        atualizarTotais();
    }

    // Formato para datetime-local: YYYY-MM-DDTHH:MM
    function formatDateTimeLocal(d) {
        const pad = n => n.toString().padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    function definirDatasPadrao() {
        const agora = new Date();
        const dataIni = document.getElementById('data_ini');

        // Apenas data inicio preenchida, data fim e dias ficam vazios
        dataIni.value = formatDateTimeLocal(agora);
    }

    // ===== FUNCOES DE CALCULO DE PERIODO =====

    // Atualiza o texto do label baseado na contagem
    function atualizarLabelDias(contagem) {
        const labels = i18n.periodLabels || {
            'dia': 'Dia(s)',
            'semana': 'Semana(s)',
            'mes': 'Mês(es)',
            'ano': 'Ano(s)'
        };
        const el = document.getElementById('labelDias');
        if (el) el.innerHTML = `${labels[contagem] || (i18n.qty || 'Qtd')} <span class="text-red-500">*</span>`;
    }

    // Calcula data fim baseado na quantidade de períodos (calendário real)
    function calcularDataFimPorPeriodo(dataIni, quantidade, contagem) {
        const inicio = new Date(dataIni);
        const resultado = new Date(inicio);

        switch (contagem) {
            case 'dia':
                resultado.setDate(resultado.getDate() + quantidade);
                break;
            case 'semana':
                resultado.setDate(resultado.getDate() + (quantidade * 7));
                break;
            case 'mes':
                resultado.setMonth(resultado.getMonth() + quantidade);
                break;
            case 'ano':
                resultado.setFullYear(resultado.getFullYear() + quantidade);
                break;
        }

        return resultado;
    }

    // Calcula quantidade de períodos entre duas datas e verifica se é exato
    function calcularQuantidadePeriodos(dataIni, dataFim, contagem) {
        const inicio = new Date(dataIni);
        const fim = new Date(dataFim);

        // Diferença em milissegundos
        const diffMs = fim - inicio;

        if (diffMs <= 0) {
            return { quantidade: 0, exato: false };
        }

        // Diferença em dias (arredondando para cima para considerar períodos parciais)
        const diffDias = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

        switch (contagem) {
            case 'dia':
                // Para dia, qualquer quantidade positiva é válida
                return { quantidade: diffDias, exato: true };

            case 'semana':
                // Para semana, deve ser múltiplo de 7 dias
                const semanas = diffDias / 7;
                return {
                    quantidade: Math.floor(semanas),
                    exato: Number.isInteger(semanas)
                };

            case 'mes':
                // Para mês, calcula diferença de meses
                let meses = (fim.getFullYear() - inicio.getFullYear()) * 12;
                meses += fim.getMonth() - inicio.getMonth();

                // Verifica se dia e horário coincidem
                const dataEsperada = calcularDataFimPorPeriodo(dataIni, meses, 'mes');
                const isExatoMes = dataEsperada.getTime() === fim.getTime();

                return { quantidade: meses, exato: isExatoMes };

            case 'ano':
                // Para ano, calcula diferença de anos
                const anos = fim.getFullYear() - inicio.getFullYear();

                // Verifica se mês, dia e horário coincidem
                const dataEsperadaAno = calcularDataFimPorPeriodo(dataIni, anos, 'ano');
                const isExatoAno = dataEsperadaAno.getTime() === fim.getTime();

                return { quantidade: anos, exato: isExatoAno };

            default:
                return { quantidade: diffDias, exato: true };
        }
    }

    // ===== CARREGAR DADOS AUXILIARES =====

    async function carregarGrupos() {
        try {
            const filialId = document.getElementById('id_matriz_filial_retirada')?.value;

            // Não fazer requisição se filial não estiver selecionada
            if (!filialId) {
                gruposDisponiveis = [];
                return;
            }

            const result = await API.get('/api/grupos', { id_filial: filialId });

            if (result.success) {
                gruposDisponiveis = result.data;
            }
        } catch (error) {
            console.error('Erro ao carregar grupos:', error);
        }
    }

    // ===== OFFCANVAS VEICULO (via postMessage) =====

    function abrirVeiculoOffcanvas(modo, index = null) {
        const filialId = document.getElementById('id_matriz_filial_retirada')?.value;
        const contagem = document.getElementById('contagem')?.value;

        if (!filialId) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectBranchFirst || 'Selecione uma filial primeiro' }, '*');
            return;
        }

        const dataIni = document.getElementById('data_ini')?.value;
        const dataFim = document.getElementById('data_fim')?.value;
        const dias = document.getElementById('dias')?.value;

        if (!dataIni || !dataFim || !dias) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.fillDatesFirst || 'Preencha Data Inicio, Data Fim e Periodo antes de adicionar um veiculo' }, '*');
            return;
        }

        let url = `/pages/contratos/offcanvas-veiculo?modo=${modo}&filial_id=${filialId}&contagem=${contagem}`;

        if (modo === 'editar' && index !== null) {
            const dados = encodeURIComponent(JSON.stringify(veiculos[index]));
            url += `&index=${index}&dados=${dados}`;
        }

        // Abrir offcanvas no layout principal via postMessage
        if (window.parent !== window) {
            window.parent.postMessage({
                action: 'openOffcanvasIframe',
                url: url,
                title: modo === 'editar' ? (i18n.editVehicle || 'Editar Veiculo') : (i18n.addVehicle || 'Adicionar Veiculo'),
                width: '500px'
            }, '*');
        }
    }

    // Listener para receber dados do offcanvas
    window.addEventListener('message', function (event) {
        // Atualizar CSRF token quando offcanvas envia token sincronizado
        if (event.data && event.data.action === 'csrfTokenUpdate' && event.data.csrfToken) {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.content = event.data.csrfToken;
        }

        if (event.data && event.data.action === 'genericConfirmed' && confirmacaoPendente) {
            const acao = confirmacaoPendente;
            confirmacaoPendente = null;

            if (acao === 'resolverDiferenca') {
                executarResolucaoDiferenca('recalcular');
            } else if (acao === 'limparParcelas') {
                executarLimparParcelas();
            } else if (acao === 'regenerarPendentes') {
                executarRegenerarPendentes();
            } else if (acao === 'removerParcelaContrato') {
                executarRemoverParcelaContrato();
            } else if (acao === 'estornarParcelaContrato') {
                executarEstornarParcelaContrato();
            }
            return;
        }

        if (event.data && event.data.action === 'genericModalClosed' && confirmacaoPendente) {
            confirmacaoPendente = null;
            parcelaContratoAcaoPendente = null;
            return;
        }

        if (event.data && event.data.action === 'parcelaAvulsaDataInformada') {
            const dataVenci = (event.data.value || '').trim();
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dataVenci)) {
                window.parent.postMessage({ action: 'openAlert', message: i18n.invalidDate || 'Data invalida. Use o formato AAAA-MM-DD.' }, '*');
                return;
            }

            parcelaAvulsaRascunho = { data_venci: dataVenci };
            window.parent.postMessage({
                action: 'openInputModal',
                title: i18n.addInstallment || 'Adicionar parcela',
                label: i18n.promptInstallmentValue || 'Valor da parcela',
                value: '',
                callbackAction: 'parcelaAvulsaValorInformado'
            }, '*');
            return;
        }

        if (event.data && event.data.action === 'parcelaAvulsaValorInformado') {
            if (!parcelaAvulsaRascunho) return;
            const dataVenci = parcelaAvulsaRascunho.data_venci;
            parcelaAvulsaRascunho = null;
            executarAdicionarParcelaAvulsa(dataVenci, event.data.value || '');
            return;
        }

        if (event.data && event.data.action === 'veiculoSalvo') {
            const { modo, index, dados } = event.data;
            if (modo === 'editar' && index !== null) {
                // Modo editar: verificar se esta trocando para um veiculo que ja existe em outra posicao
                const existeEmOutraPosicao = veiculos.some((v, i) =>
                    String(v.id_veiculo) === String(dados.id_veiculo) && i !== index
                );
                if (existeEmOutraPosicao) {
                    window.parent.postMessage({
                        action: 'openAlert',
                        message: i18n.alreadyAdded || 'Este veiculo ja foi adicionado ao contrato'
                    }, '*');
                    return;
                }
                veiculos[index] = normalizarValoresPlanoVeiculo(dados);
            } else {
                // Modo adicionar: verificar se ja foi adicionado
                if (veiculos.some(v => String(v.id_veiculo) === String(dados.id_veiculo))) {
                    window.parent.postMessage({
                        action: 'openAlert',
                        message: i18n.alreadyAdded || 'Este veiculo ja foi adicionado ao contrato'
                    }, '*');
                    return;
                }
                veiculos.push(normalizarValoresPlanoVeiculo(dados));
            }
            renderizarVeiculos();
            atualizarTotais();
        }
    });

    async function carregarValoresGrupo(grupoId) {
        if (valoresGrupoCache[grupoId]) {
            return valoresGrupoCache[grupoId];
        }

        try {
            const result = await API.get(`/api/grupos/${grupoId}/precos`);
            if (result.success) {
                valoresGrupoCache[grupoId] = result.data;
                return result.data;
            }
        } catch (error) {
            console.error('Erro ao carregar valores do grupo:', error);
        }
        return null;
    }

    async function carregarTaxasDisponiveis() {
        try {
            // Carregar taxas para a variavel taxasDisponiveis
            // O chosen-select server-side carrega as opcoes automaticamente via /api/taxas-e-servicos/buscar
            // Aqui carregamos os dados para usar no evento change
            const result = await API.get('/api/taxas-e-servicos/buscar');

            if (result.success && result.data) {
                taxasDisponiveis = result.data;
            }
        } catch (error) {
            console.error('Erro ao carregar taxas:', error);
        }
    }

    async function carregarFormasPagamento() {
        try {
            const result = await API.get('/api/formas-pagamento/select');
            if (result.success && result.data) {
                formasPagamentoList = result.data.map(forma => ({
                    id: forma.id,
                    nome: forma.nome || forma.text || forma.label || ''
                }));
            }
        } catch (error) {
            console.error('Erro ao carregar formas de pagamento:', error);
        }
    }

    async function carregarComandosParcelas() {
        try {
            const result = await API.get('/api/comandos-parcelas/select');
            if (result.success && result.data) {
                comandosParcelasList = result.data;
            }
        } catch (error) {
            console.error('Erro ao carregar comandos de parcelas:', error);
        }
    }

    async function carregarContasBancarias() {
        try {
            const result = await API.get('/api/contas-bancarias/buscar');
            if (result.success && result.data) {
                contasBancariasList = result.data.map(c => ({ id: c.id, nome: c.text }));
            }
        } catch (error) {
            console.error('Erro ao carregar contas bancárias:', error);
        }
    }

    // ===== CARREGAR DADOS DO CONTRATO =====

    async function carregarDados(id) {
        try {
            const result = await API.get(`/api/contratos/${id}`);

            if (!result.success) {
                window.parent.postMessage({ action: 'openAlert', message: result.message || (i18n.loadDataError || 'Erro ao carregar dados') }, '*');
                voltar();
                return;
            }

            preencherFormulario(result.data);

            // Carregar parcelas financeiras
            await carregarParcelasContrato();
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.loadContractError || 'Erro ao carregar dados do contrato' }, '*');
            voltar();
        }
    }

    function preencherFormulario(data) {
        inputId.value = data.id || '';
        const contratoStatus = document.getElementById('contratoStatus');
        if (contratoStatus) contratoStatus.value = data.status || 'A';

        // Filial
        if (data.id_matriz_filial_retirada && data.filial_nome) {
            const select = document.getElementById('id_matriz_filial_retirada');
            select.innerHTML = `<option value="">${i18n.select || 'Selecione...'}</option><option value="${data.id_matriz_filial_retirada}" selected>${escapeHtml(data.filial_nome)}</option>`;
            select.dispatchEvent(new Event('change'));
        }

        // Datas
        if (data.data_ini) {
            const dataIniEl = document.getElementById('data_ini');
            if (dataIniEl) dataIniEl.value = formatDateTimeForInput(data.data_ini);
        }
        if (data.data_fim) {
            const dataFimEl = document.getElementById('data_fim');
            if (dataFimEl) dataFimEl.value = formatDateTimeForInput(data.data_fim);
        }
        if (data.data_renovacao) {
            const dataRenovacaoEl = document.getElementById('data_renovacao');
            if (dataRenovacaoEl) dataRenovacaoEl.value = data.data_renovacao.substring(0, 10);
        }

        // Periodo
        const contagemEl = document.getElementById('contagem');
        if (contagemEl) contagemEl.value = data.contagem || 'dia';
        atualizarLabelDias(data.contagem || 'dia');
        const diasEl = document.getElementById('dias');
        if (diasEl) diasEl.value = data.dias || 1;
        const autoRenovacaoEl = document.getElementById('auto_renovacao');
        if (autoRenovacaoEl) autoRenovacaoEl.value = data.auto_renovacao || '';
        toggleDataRenovacao();

        // Cliente
        if (data.id_cliente && data.cliente_nome) {
            const select = document.getElementById('id_cliente');
            select.innerHTML = `<option value="">${i18n.select || 'Selecione...'}</option><option value="${data.id_cliente}" selected>${escapeHtml(data.cliente_nome)}</option>`;
            select.dispatchEvent(new Event('change'));

            // Mostrar dados do cliente
            exibirDadosCliente({
                cpf_cnpj: data.cliente_cpf_cnpj,
                telefone: data.cliente_telefone,
                email: data.cliente_email
            });
        }

        // Financeiro
        if (data.id_conta && data.conta_descricao) {
            const select = document.getElementById('id_conta');
            select.innerHTML = `<option value="">${i18n.select || 'Selecione...'}</option><option value="${data.id_conta}" selected>${escapeHtml(data.conta_descricao)}</option>`;
            select.chosenSelect?.refresh();
            select.dispatchEvent(new Event('change'));
        }

        if (data.id_forma_pagamento && data.forma_pagamento_descricao) {
            const select = document.getElementById('id_forma_pagamento');
            select.innerHTML = `<option value="">${i18n.select || 'Selecione...'}</option><option value="${data.id_forma_pagamento}" selected>${escapeHtml(data.forma_pagamento_descricao)}</option>`;
            select.chosenSelect?.refresh();
            select.dispatchEvent(new Event('change'));
        }

        if (data.id_comando_parcela) {
            const select = document.getElementById('id_comando_parcela');
            if (select) {
                const cmdText = (data.comando_parcela_comando != null ? data.comando_parcela_comando : '')
                    + (data.comando_parcela_descricao ? ' - ' + data.comando_parcela_descricao : '');
                select.innerHTML = `<option value="">${i18n.select || 'Selecione...'}</option><option value="${data.id_comando_parcela}" selected>${escapeHtml(cmdText)}</option>`;
                select.chosenSelect?.refresh();
                select.dispatchEvent(new Event('change'));
            }
        }

        preencherCaucao(data);

        // Primeiro vencimento (campo date, não moeda)
        const primeiroVenc = document.getElementById('primeiro_vencimento');
        if (primeiroVenc && data.primeiro_pagamento) {
            primeiroVenc.value = data.primeiro_pagamento.substring(0, 10);
        }
        const valorDescontoEl = document.getElementById('valor_desconto');
        if (valorDescontoEl) valorDescontoEl.value = data.valor_desconto ? Currency.format(data.valor_desconto) : '0,00';

        // Observacoes
        const obsEl = document.getElementById('obs');
        if (obsEl) obsEl.value = data.obs || '';

        // Veiculos
        if (data.veiculos && data.veiculos.length > 0) {
            data.veiculos.forEach(v => {
                adicionarVeiculoNaLista({
                    id_veiculo: v.id_veiculo,
                    id_grupo: v.id_grupo,
                    grupo_nome: v.grupo_nome || '',
                    placa: v.veiculo_placa,
                    modelo: v.veiculo_modelo,
                    plano: v.plano,
                    valor_plano_km_livre: v.valor_plano_km_livre,
                    valor_plano_km_controlado: v.valor_plano_km_controlado,
                    valor_plano_km_pago: v.valor_plano_km_pago,
                    valor_km_excedente: v.valor_km_excedente || 0,
                    km_franquia: v.km_franquia || 0,
                    odometro_saida: v.odometro_saida,
                    combustivel_saida: v.combustivel_saida,
                    seguro_carro: v.seguro_carro,
                    valor_seguro_carro: v.valor_seguro_carro,
                    seguro_terceiros: v.seguro_terceiros,
                    valor_seguro_terceiros: v.valor_seguro_terceiros,
                    _salvo: true
                });
            });
        }

        // Taxas
        if (data.taxas && data.taxas.length > 0) {
            data.taxas.forEach(t => {
                adicionarTaxaNaLista({
                    id_taxa: t.id_taxa,
                    nome: t.nome,
                    quantidade: t.quantidade,
                    valor_unitario: t.valor_unitario,
                    base_calculo: t.base_calculo || 'FIX',
                    tipo_valor: t.tipo_valor || 'MON'
                });
            });
        }

        // Condutores, Fiadores, Avalistas, Testemunhas
        if (data.condutor_adicional) {
            const arr = typeof data.condutor_adicional === 'string' ? JSON.parse(data.condutor_adicional) : data.condutor_adicional;
            arr.forEach(p => adicionarPessoaNaLista('condutor', p));
        }
        if (data.array_fiadores) {
            const arr = typeof data.array_fiadores === 'string' ? JSON.parse(data.array_fiadores) : data.array_fiadores;
            arr.forEach(p => adicionarPessoaNaLista('fiador', p));
        }
        if (data.array_avalistas) {
            const arr = typeof data.array_avalistas === 'string' ? JSON.parse(data.array_avalistas) : data.array_avalistas;
            arr.forEach(p => adicionarPessoaNaLista('avalista', p));
        }
        if (data.array_testemunhas) {
            const arr = typeof data.array_testemunhas === 'string' ? JSON.parse(data.array_testemunhas) : data.array_testemunhas;
            arr.forEach(p => adicionarPessoaNaLista('testemunha', p));
        }

        atualizarTotais();
    }

    function preencherCaucao(data) {
        const caucao = data.caucao || {};
        const valor = caucao.valor ?? data.caucao_valor ?? 0;

        const valorEl = document.getElementById('caucao_valor');
        if (valorEl) valorEl.value = valor ? Currency.format(valor) : '0,00';

        const contaId = caucao.id_conta ?? data.id_conta_caucao;
        const contaDescricao = caucao.conta_descricao ?? data.conta_caucao_descricao;
        const contaEl = document.getElementById('id_conta_caucao');
        if (contaEl && contaId && contaDescricao) {
            contaEl.innerHTML = `<option value="">${i18n.select || 'Selecione...'}</option><option value="${contaId}" selected>${escapeHtml(contaDescricao)}</option>`;
            contaEl.chosenSelect?.refresh();
        }

        const formaPagamentoId = caucao.id_forma_pagamento ?? data.id_forma_pagamento_caucao;
        const formaPagamentoDescricao = caucao.forma_pagamento_descricao ?? data.forma_pagamento_caucao_descricao;
        const formaPagamentoEl = document.getElementById('id_forma_pagamento_caucao');
        if (formaPagamentoEl && formaPagamentoId && formaPagamentoDescricao) {
            formaPagamentoEl.innerHTML = `<option value="">${i18n.select || 'Selecione...'}</option><option value="${formaPagamentoId}" selected>${escapeHtml(formaPagamentoDescricao)}</option>`;
            formaPagamentoEl.chosenSelect?.refresh();
        }

        const prazoEl = document.getElementById('caucao_prazo_devolucao');
        if (prazoEl) prazoEl.value = caucao.prazo_devolucao ?? data.caucao_prazo_devolucao ?? '';

        const lancarEl = document.getElementById('caucao_lancar_financeiro');
        if (lancarEl) lancarEl.value = String(caucao.lancar_financeiro ?? data.caucao_lancar_financeiro ?? '0') === '1' ? '1' : '0';

        const obsEl = document.getElementById('caucao_observacoes');
        if (obsEl) obsEl.value = caucao.observacoes ?? data.caucao_observacoes ?? '';

        const badge = document.getElementById('caucaoStatusBadge');
        if (badge && caucao.status) {
            badge.textContent = caucao.status;
            badge.classList.remove('hidden');
        }

        atualizarCaucaoRequired();
    }

    function formatDateTimeForInput(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        const pad = n => n.toString().padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    // ===== VEICULOS =====

    function adicionarVeiculoNaLista(dados) {
        veiculos.push(normalizarValoresPlanoVeiculo(dados));
        renderizarVeiculos();
    }

    function renderizarVeiculos() {
        const container = document.getElementById('listaVeiculos');
        const cabecalho = document.getElementById('cabecalhoVeiculos');

        if (veiculos.length === 0) {
            container.innerHTML = `<p class="text-slate-500 text-center py-4">${i18n.noVehicles || 'Nenhum veiculo adicionado'}</p>`;
            cabecalho.classList.add('hidden');
            return;
        }

        cabecalho.classList.remove('hidden');
        container.innerHTML = '';
        const template = document.getElementById('templateVeiculoCard');

        // Cores e labels por plano
        const planoLabels = {
            'KL': i18n.planKmFree || 'Km Livre',
            'KMC': i18n.planKmControlled || 'Km Controlado',
            'KP': i18n.planKmPaid || 'Km Pago'
        };
        const planoCores = {
            'KL': { text: 'text-violet-700', bg: 'bg-violet-100' },
            'KMC': { text: 'text-amber-700', bg: 'bg-amber-100' },
            'KP': { text: 'text-blue-700', bg: 'bg-blue-100' }
        };
        const planoOrdem = { 'KL': 0, 'KMC': 1, 'KP': 2 };

        // Ordenar por plano para agrupar visualmente (sem alterar array original)
        const veiculosOrdenados = veiculos
            .map((v, i) => ({ ...v, _originalIndex: i }))
            .sort((a, b) => (planoOrdem[a.plano] ?? 99) - (planoOrdem[b.plano] ?? 99));

        veiculosOrdenados.forEach((v) => {
            const clone = template.content.cloneNode(true);
            const item = clone.querySelector('.veiculo-item');

            item.dataset.index = v._originalIndex;

            // Label e cor do plano
            const planoSpan = item.querySelector('.veiculo-plano-label');
            planoSpan.textContent = planoLabels[v.plano] || v.plano;
            const cores = planoCores[v.plano] || { text: 'text-slate-700', bg: 'bg-slate-50' };
            planoSpan.classList.add(cores.text, cores.bg);

            // Grupo
            item.querySelector('.veiculo-grupo-label').textContent = v.grupo_nome || (i18n.groupDefault || 'Grupo');

            // Veiculo (placa + modelo)
            item.querySelector('.veiculo-info').textContent = `${v.placa} - ${v.modelo}`;

            container.appendChild(clone);
        });

        // Event listeners
        container.querySelectorAll('.btn-editar-veiculo').forEach(btn => {
            btn.addEventListener('click', function () {
                const item = this.closest('.veiculo-item');
                const index = parseInt(item.dataset.index);
                abrirVeiculoOffcanvas('editar', index);
            });
        });

        container.querySelectorAll('.btn-remover-veiculo').forEach(btn => {
            btn.addEventListener('click', function () {
                const item = this.closest('.veiculo-item');
                const index = parseInt(item.dataset.index);
                if (veiculos[index] && veiculos[index]._salvo) {
                    window.parent.postMessage({
                        action: 'openAlert',
                        message: i18n.vehicleSavedUseDevolution || 'Este veiculo ja esta vinculado ao contrato. Para remove-lo, utilize a tela de devolucao na listagem de contratos.'
                    }, '*');
                    return;
                }
                veiculos.splice(index, 1);
                renderizarVeiculos();
                atualizarTotais();
            });
        });
    }

    function getValorPlano(veiculo) {
        let valor;
        switch (veiculo.plano) {
            case 'km_livre':
            case 'KL':
                valor = veiculo.valor_plano_km_livre;
                break;
            case 'km_controlado':
            case 'KMC':
                valor = veiculo.valor_plano_km_controlado;
                break;
            case 'km_pago':
            case 'KP':
                valor = veiculo.valor_plano_km_pago;
                break;
            default:
                valor = 0;
        }
        return parseFloat(valor) || 0;
    }

    function normalizarValoresPlanoVeiculo(veiculo) {
        const normalizado = { ...veiculo };
        const plano = String(normalizado.plano || 'KL').toUpperCase();

        normalizado.plano = plano === 'KC' ? 'KP' : plano;
        normalizado.valor_plano_km_livre = normalizado.plano === 'KL' ? (parseFloat(normalizado.valor_plano_km_livre) || 0) : 0;
        normalizado.valor_plano_km_controlado = normalizado.plano === 'KMC' ? (parseFloat(normalizado.valor_plano_km_controlado) || 0) : 0;
        normalizado.valor_plano_km_pago = normalizado.plano === 'KP' ? (parseFloat(normalizado.valor_plano_km_pago) || 0) : 0;

        return normalizado;
    }

    // ===== PESSOAS (Condutor/Fiador/Avalista/Testemunha) =====

    // Funcoes auxiliares
    function formatarCpfCnpj(valor) {
        if (!valor) return '';
        const numeros = valor.replace(/\D/g, '');
        if (numeros.length === 11) {
            return numeros.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        } else if (numeros.length === 14) {
            return numeros.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
        }
        return valor;
    }

    function limparDocumento(valor) {
        if (!valor) return '';
        return valor.replace(/\D/g, '');
    }

    function formatarDataBr(data) {
        if (!data) return '';
        const d = new Date(data + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        return d.toLocaleDateString('pt-BR');
    }

    function montarEnderecoCompleto(cliente) {
        const partes = [];
        if (cliente.rua) {
            let endereco = cliente.rua;
            if (cliente.num) endereco += `, ${cliente.num}`;
            partes.push(endereco);
        }
        if (cliente.bairro) partes.push(cliente.bairro);
        if (cliente.cidade) partes.push(cliente.cidade);
        if (cliente.estado) partes.push(cliente.estado);
        if (cliente.pais) partes.push(cliente.pais);
        if (cliente.cep) partes.push(`CEP ${cliente.cep}`);
        return partes.join(' - ');
    }

    // Funcao para adicionar Condutor (com CNH)
    function adicionarCondutor(dados = {}) {
        const template = document.getElementById('templateCondutorCard');
        const container = document.getElementById('listaCondutores');
        const clone = template.content.cloneNode(true);
        const card = clone.querySelector('.pessoa-card');
        const index = condutores.length;

        card.dataset.index = index;
        card.querySelector('.pessoa-label').textContent = (i18n.conductorLabel || 'Condutor :num').replace(':num', index + 1);

        // Preencher dados se existirem (edicao)
        if (dados.id) {
            card.querySelector('.pessoa-id').value = dados.id;
            const select = card.querySelector('.pessoa-select-cliente');
            const option = new Option(dados.nome, dados.id, true, true);
            select.appendChild(option);
            card.querySelector('.pessoa-cpf').value = formatarCpfCnpj(dados.cc);
            card.querySelector('.pessoa-cnh').value = dados.cn || '';
            card.querySelector('.pessoa-validade').value = formatarDataBr(dados.va);

            // Verificar CNH vencida
            if (dados.va && new Date(dados.va) < new Date()) {
                card.querySelector('.pessoa-cnh-alerta')?.classList.remove('hidden');
            }
        }

        condutores.push({
            id: dados.id || '',
            nome: dados.nome || '',
            cc: dados.cc || '',
            cn: dados.cn || '',
            va: dados.va || ''
        });

        container.appendChild(card);

        // Inicializar chosen-select
        const select = card.querySelector('.pessoa-select-cliente');
        if (window.initChosenSelects) {
            window.initChosenSelects();
        }

        // Listener para selecao
        select.addEventListener('change', async function () {
            await preencherDadosCondutor(card, this.value, index);
        });

        // Listener para remover
        card.querySelector('.btn-remover-pessoa')?.addEventListener('click', () => {
            condutores.splice(index, 1);
            renderizarCondutores();
        });
    }

    // Funcao para preencher dados do Condutor apos selecao
    async function preencherDadosCondutor(card, clienteId, index) {
        if (!clienteId) {
            card.querySelector('.pessoa-id').value = '';
            card.querySelector('.pessoa-cpf').value = '';
            card.querySelector('.pessoa-cnh').value = '';
            card.querySelector('.pessoa-validade').value = '';
            card.querySelector('.pessoa-cnh-alerta')?.classList.add('hidden');
            condutores[index] = { id: '', nome: '', cc: '', cn: '', va: '' };
            return;
        }

        try {
            const result = await API.get(`/api/clientes/${clienteId}`);
            if (result.success) {
                const cliente = result.data;

                card.querySelector('.pessoa-id').value = cliente.id;
                card.querySelector('.pessoa-cpf').value = formatarCpfCnpj(cliente.cpf_cnpj);
                const cnhNumero = cliente.cnh_numero || cliente.cnh || '';
                card.querySelector('.pessoa-cnh').value = cnhNumero;
                card.querySelector('.pessoa-validade').value = formatarDataBr(cliente.cnh_validade);

                // Verificar CNH vencida
                const alertaCnh = card.querySelector('.pessoa-cnh-alerta');
                if (cliente.cnh_validade && new Date(cliente.cnh_validade) < new Date()) {
                    alertaCnh.classList.remove('hidden');
                } else {
                    alertaCnh.classList.add('hidden');
                }

                // Atualizar array
                condutores[index] = {
                    id: cliente.id,
                    nome: cliente.nome_rsocial,
                    cc: limparDocumento(cliente.cpf_cnpj),
                    cn: cnhNumero,
                    va: cliente.cnh_validade || ''
                };
            }
        } catch (e) {
            console.error('Erro ao buscar cliente:', e);
        }
    }

    // Funcao para renderizar todos os condutores
    function renderizarCondutores() {
        const container = document.getElementById('listaCondutores');
        container.innerHTML = '';
        const dados = [...condutores];
        condutores.length = 0;
        dados.forEach(d => adicionarCondutor(d));
    }

    // Funcao generica para adicionar Fiador/Avalista/Testemunha (sem CNH)
    function adicionarPessoaSemCnh(tipo, dados = {}) {
        let lista, container, label;

        switch (tipo) {
            case 'fiador':
                lista = fiadores;
                container = document.getElementById('listaFiadores');
                label = (i18n.guarantorLabel || 'Fiador :num').replace(':num', '').trim();
                break;
            case 'avalista':
                lista = avalistas;
                container = document.getElementById('listaAvalistas');
                label = (i18n.endorserLabel || 'Avalista :num').replace(':num', '').trim();
                break;
            case 'testemunha':
                lista = testemunhas;
                container = document.getElementById('listaTestemunhas');
                label = (i18n.witnessLabel || 'Testemunha :num').replace(':num', '').trim();
                break;
            default:
                return;
        }

        const template = document.getElementById('templatePessoaSemCnhCard');
        const clone = template.content.cloneNode(true);
        const card = clone.querySelector('.pessoa-card');
        const index = lista.length;

        card.dataset.index = index;
        card.dataset.tipo = tipo;
        card.querySelector('.pessoa-label').textContent = `${label} ${index + 1}`;

        // Preencher dados se existirem (edicao)
        if (dados.id) {
            card.querySelector('.pessoa-id').value = dados.id;
            const select = card.querySelector('.pessoa-select-cliente');
            const option = new Option(dados.nome, dados.id, true, true);
            select.appendChild(option);
            card.querySelector('.pessoa-cpf').value = formatarCpfCnpj(dados.cc);
            card.querySelector('.pessoa-endereco').value = dados.endereco || '';
        }

        lista.push({
            id: dados.id || '',
            nome: dados.nome || '',
            cc: dados.cc || '',
            endereco: dados.endereco || ''
        });

        container.appendChild(card);

        // Inicializar chosen-select
        const select = card.querySelector('.pessoa-select-cliente');
        if (window.initChosenSelects) {
            window.initChosenSelects();
        }

        // Listener para selecao
        select.addEventListener('change', async function () {
            await preencherDadosPessoaSemCnh(card, this.value, index, lista);
        });

        // Listener para remover
        card.querySelector('.btn-remover-pessoa')?.addEventListener('click', () => {
            lista.splice(index, 1);
            renderizarPessoasSemCnh(tipo);
        });
    }

    // Funcao para preencher dados de Fiador/Avalista/Testemunha apos selecao
    async function preencherDadosPessoaSemCnh(card, clienteId, index, lista) {
        if (!clienteId) {
            card.querySelector('.pessoa-id').value = '';
            card.querySelector('.pessoa-cpf').value = '';
            card.querySelector('.pessoa-endereco').value = '';
            lista[index] = { id: '', nome: '', cc: '', endereco: '' };
            return;
        }

        try {
            const result = await API.get(`/api/clientes/${clienteId}`);
            if (result.success) {
                const cliente = result.data;

                card.querySelector('.pessoa-id').value = cliente.id;
                card.querySelector('.pessoa-cpf').value = formatarCpfCnpj(cliente.cpf_cnpj);
                card.querySelector('.pessoa-endereco').value = montarEnderecoCompleto(cliente);

                // Atualizar array
                lista[index] = {
                    id: cliente.id,
                    nome: cliente.nome_rsocial,
                    cc: limparDocumento(cliente.cpf_cnpj),
                    endereco: montarEnderecoCompleto(cliente)
                };
            }
        } catch (e) {
            console.error('Erro ao buscar cliente:', e);
        }
    }

    // Funcao para renderizar Fiador/Avalista/Testemunha
    function renderizarPessoasSemCnh(tipo) {
        let lista, container;

        switch (tipo) {
            case 'fiador':
                lista = fiadores;
                container = document.getElementById('listaFiadores');
                break;
            case 'avalista':
                lista = avalistas;
                container = document.getElementById('listaAvalistas');
                break;
            case 'testemunha':
                lista = testemunhas;
                container = document.getElementById('listaTestemunhas');
                break;
            default:
                return;
        }

        container.innerHTML = '';
        const dados = [...lista];

        switch (tipo) {
            case 'fiador':
                fiadores.length = 0;
                break;
            case 'avalista':
                avalistas.length = 0;
                break;
            case 'testemunha':
                testemunhas.length = 0;
                break;
        }

        dados.forEach(d => adicionarPessoaSemCnh(tipo, d));
    }

    // Funcao de compatibilidade para carregar dados antigos
    function adicionarPessoaNaLista(tipo, dados = {}) {
        if (tipo === 'condutor') {
            // Converter formato antigo para novo
            const dadosNovos = {
                id: dados.id || '',
                nome: dados.nome || dados.nome_rsocial || '',
                cc: dados.cc || dados.cpf || limparDocumento(dados.cpf_cnpj) || '',
                cn: dados.cn || dados.cnh || '',
                va: dados.va || dados.cnh_validade || '',
                endereco: dados.endereco || ''
            };
            adicionarCondutor(dadosNovos);
        } else {
            // Fiador, Avalista, Testemunha
            const dadosNovos = {
                id: dados.id || '',
                nome: dados.nome || dados.nome_rsocial || '',
                cc: dados.cc || dados.cpf || limparDocumento(dados.cpf_cnpj) || '',
                endereco: dados.endereco || ''
            };
            adicionarPessoaSemCnh(tipo, dadosNovos);
        }
    }

    // ===== TAXAS =====

    /**
     * Calcula os dias reais baseado na contagem e quantidade de periodos
     * @param {number} qtdPeriodos - Quantidade de periodos (ex: 1 semana, 2 meses)
     * @param {string} contagem - Tipo de contagem (dia, semana, mes, ano)
     * @returns {number} Numero de dias reais
     */
    function calcularDiasReais(qtdPeriodos, contagem) {
        const multiplicadores = {
            'dia': 1,
            'semana': 7,
            'mes': 30,
            'ano': 365
        };
        return qtdPeriodos * (multiplicadores[contagem] || 1);
    }

    /**
     * Calcula o valor total de uma taxa baseado nas regras de calculo
     * @param {Object} taxa - Dados da taxa (base_calculo, tipo_valor, valor_unitario, quantidade)
     * @param {number} diasReais - Numero de dias reais do contrato
     * @param {number} valorTotalVeiculos - Valor total dos veiculos no periodo
     * @returns {number} Valor total calculado
     */
    function calcularValorTotalTaxa(taxa, diasReais, valorTotalVeiculos) {
        const valor = parseFloat(taxa.valor_unitario) || 0;
        const baseCalculo = taxa.base_calculo || 'FIX';
        const tipoValor = taxa.tipo_valor || 'MON';
        const quantidade = parseInt(taxa.quantidade) || 1;

        let valorBase;

        // Determinar valor base
        if (tipoValor === 'POR') {
            // Porcentagem - calcular sobre a base
            if (baseCalculo === 'VLT') {
                // Porcentagem do valor total
                valorBase = valorTotalVeiculos * (valor / 100);
            } else {
                // PER ou FIX - porcentagem do valor diario
                const valorDiario = diasReais > 0 ? valorTotalVeiculos / diasReais : 0;
                valorBase = valorDiario * (valor / 100);
            }
        } else {
            // MON - valor em moeda
            valorBase = valor;
        }

        // Aplicar multiplicador
        if (baseCalculo === 'PER') {
            // Por periodo (calculado por dia) - multiplica pelos dias reais
            return valorBase * quantidade * diasReais;
        } else {
            // FIX ou VLT - valor unico (ou ja calculado sobre total)
            return valorBase * quantidade;
        }
    }

    function adicionarTaxa() {
        const nome = document.getElementById('taxa_nome')?.value.trim();
        const qtd = parseInt(document.getElementById('taxa_qtd')?.value) || 1;
        const valorUnit = Currency.parse(document.getElementById('taxa_valor')?.value);

        if (!nome) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.informFeeName || 'Informe o nome da taxa' }, '*');
            return;
        }

        const taxa = {
            id_taxa: document.getElementById('taxa_select')?.value || null,
            nome: nome,
            quantidade: qtd,
            valor_unitario: valorUnit,
            base_calculo: taxaSelecionadaAtual?.base_calculo || 'FIX',
            tipo_valor: taxaSelecionadaAtual?.tipo_valor || 'MON'
        };

        adicionarTaxaNaLista(taxa);

        // Limpar campos e resetar taxa selecionada
        const taxaSelectEl = document.getElementById('taxa_select');
        if (taxaSelectEl) taxaSelectEl.value = '';
        const taxaNomeEl = document.getElementById('taxa_nome');
        if (taxaNomeEl) taxaNomeEl.value = '';
        const taxaQtdEl = document.getElementById('taxa_qtd');
        if (taxaQtdEl) taxaQtdEl.value = '1';
        const taxaValorEl = document.getElementById('taxa_valor');
        if (taxaValorEl) taxaValorEl.value = '';
        taxaSelecionadaAtual = null;

        // Resetar chosen-select
        const taxaSelect = document.getElementById('taxa_select');
        if (taxaSelect) {
            taxaSelect.value = '';
            taxaSelect.dispatchEvent(new Event('change'));
        }

        atualizarTotais();
    }

    function adicionarTaxaNaLista(taxa) {
        taxas.push(taxa);
        renderizarTaxas();
    }

    function renderizarTaxas() {
        const container = document.getElementById('listaTaxas');

        if (taxas.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = '';
        const template = document.getElementById('templateTaxaItem');

        // Obter dados para calculo
        const qtdPeriodos = parseInt(document.getElementById('dias')?.value) || 1;
        const contagem = document.getElementById('contagem')?.value || 'dia';
        const diasReais = calcularDiasReais(qtdPeriodos, contagem);
        const valorTotalVeiculos = calcularValorTotalVeiculos();

        taxas.forEach((t, index) => {
            const clone = template.content.cloneNode(true);
            const item = clone.querySelector('.taxa-item');

            // Calcular valor total da taxa
            const valorTotal = calcularValorTotalTaxa(t, diasReais, valorTotalVeiculos);

            // Atualizar valor_total na taxa para manter sincronizado
            t.valor_total_calculado = valorTotal;

            item.dataset.index = index;
            item.querySelector('.taxa-nome').textContent = t.nome;
            item.querySelector('.taxa-qtd').textContent = `${t.quantidade}x`;

            // Mostrar valor unitario com simbolo correto (R$, %, ou R$/dia)
            let valorUnitFormatado;
            if (t.tipo_valor === 'POR') {
                valorUnitFormatado = `${t.valor_unitario}%`;
            } else if (t.base_calculo === 'PER') {
                valorUnitFormatado = `${Currency.format(t.valor_unitario)}/dia`;
            } else {
                valorUnitFormatado = Currency.format(t.valor_unitario);
            }
            item.querySelector('.taxa-valor-unit').textContent = valorUnitFormatado;
            item.querySelector('.taxa-valor-total').textContent = Currency.format(valorTotal);
            item.querySelector('.taxa-id').value = t.id_taxa || '';

            // Adicionar indicador visual do tipo de calculo
            const nomeEl = item.querySelector('.taxa-nome');
            if (t.base_calculo === 'PER') {
                nomeEl.title = i18n.byPeriod || 'Por período';
            } else if (t.base_calculo === 'VLT') {
                nomeEl.title = i18n.onTotalValue || 'Sobre valor total';
            }
            if (t.tipo_valor === 'POR') {
                nomeEl.title = (nomeEl.title ? nomeEl.title + ' - ' : '') + (i18n.percentage || 'Percentual');
            }

            container.appendChild(clone);
        });

        // Event listeners
        container.querySelectorAll('.btn-remover-taxa').forEach(btn => {
            btn.addEventListener('click', function () {
                const item = this.closest('.taxa-item');
                const index = parseInt(item.dataset.index);
                taxas.splice(index, 1);
                renderizarTaxas();
                atualizarTotais();
            });
        });
    }

    /**
     * Calcula o valor total dos veiculos no periodo
     * @returns {number} Valor total
     */
    function calcularValorTotalVeiculos() {
        const dias = parseInt(document.getElementById('dias')?.value) || 1;
        let totalVeiculos = 0;

        veiculos.forEach(v => {
            let valorPlano = getValorPlano(v);
            totalVeiculos += valorPlano;

            if (v.seguro_carro) totalVeiculos += parseFloat(v.valor_seguro_carro) || 0;
            if (v.seguro_terceiros) totalVeiculos += parseFloat(v.valor_seguro_terceiros) || 0;
        });

        return totalVeiculos * dias;
    }

    // ===== CAUCAO =====

    function atualizarCaucaoRequired() {
        const valor = Currency.parse(document.getElementById('caucao_valor')?.value || '0');
        const required = valor > 0;

        ['id_conta_caucao', 'id_forma_pagamento_caucao', 'caucao_prazo_devolucao'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.required = required;
        });
    }

    function validarCaucao() {
        const valor = Currency.parse(document.getElementById('caucao_valor')?.value || '0');
        if (valor <= 0) return true;

        if (!document.getElementById('id_conta_caucao')?.value) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.depositAccountRequired || 'Selecione a conta bancaria da caucao' }, '*');
            document.querySelector('[data-form-tab-target="#tabFinanceiro"]')?.click();
            return false;
        }

        if (!document.getElementById('id_forma_pagamento_caucao')?.value) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.depositPaymentMethodRequired || 'Selecione a forma de pagamento da caucao' }, '*');
            document.querySelector('[data-form-tab-target="#tabFinanceiro"]')?.click();
            return false;
        }

        if (!document.getElementById('caucao_prazo_devolucao')?.value) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.depositDeadlineRequired || 'Informe o prazo de devolucao da caucao' }, '*');
            document.querySelector('[data-form-tab-target="#tabFinanceiro"]')?.click();
            return false;
        }

        return true;
    }

    // ===== TOTAIS =====

    function atualizarTotais() {
        const qtdPeriodos = parseInt(document.getElementById('dias')?.value) || 1;
        const contagem = document.getElementById('contagem')?.value || 'dia';
        const diasReais = calcularDiasReais(qtdPeriodos, contagem);
        const desconto = Currency.parse(document.getElementById('valor_desconto')?.value);

        // Total veiculos no periodo
        const totalVeiculosPeriodo = calcularValorTotalVeiculos();

        // Total taxas (usando dias reais para calculo)
        let totalTaxas = 0;
        taxas.forEach(t => {
            totalTaxas += calcularValorTotalTaxa(t, diasReais, totalVeiculosPeriodo);
        });

        // Total a pagar
        const totalPagar = totalVeiculosPeriodo + totalTaxas - desconto;

        // Atualizar exibicao (elementos opcionais - podem nao existir no layout simplificado)
        const elTotalVeiculos = document.getElementById('totalVeiculos');
        const elTotalTaxas = document.getElementById('totalTaxas');
        const elTotalDesconto = document.getElementById('totalDesconto');
        const elTotalPagar = document.getElementById('totalPagar');

        if (elTotalVeiculos) elTotalVeiculos.textContent = Currency.format(totalVeiculosPeriodo);
        if (elTotalTaxas) elTotalTaxas.textContent = Currency.format(totalTaxas);
        if (elTotalDesconto) elTotalDesconto.textContent = '- ' + Currency.format(desconto);
        if (elTotalPagar) elTotalPagar.textContent = Currency.format(totalPagar);

        // Verificar diferenca financeira com parcelas existentes
        verificarDiferencaFinanceira();

        // Manter resumo atualizado
        atualizarResumoCompleto();
    }

    /**
     * Verifica diferenca entre total do contrato e soma das parcelas existentes.
     * So atua quando editando contrato com parcelas ja geradas.
     */
    function verificarDiferencaFinanceira() {
        const elAvisoDiferenca = document.getElementById('avisoFinanceiroDiferenca');

        if (!editando || parcelas.length === 0) {
            if (elAvisoDiferenca) elAvisoDiferenca.classList.add('hidden');
            return;
        }

        if (contratoTemAutorenovacaoAutomatica()) {
            if (elAvisoDiferenca) elAvisoDiferenca.classList.add('hidden');
            return;
        }

        // Calcular total do contrato
        const totalVeiculosPeriodo = calcularValorTotalVeiculos();
        const qtdPeriodos = parseInt(document.getElementById('dias')?.value) || 1;
        const contagem = document.getElementById('contagem')?.value || 'dia';
        const diasReais = calcularDiasReais(qtdPeriodos, contagem);
        const desconto = Currency.parse(document.getElementById('valor_desconto')?.value);

        let totalTaxas = 0;
        taxas.forEach(t => {
            totalTaxas += calcularValorTotalTaxa(t, diasReais, totalVeiculosPeriodo);
        });

        const totalContrato = totalVeiculosPeriodo + totalTaxas - desconto;

        // Soma das parcelas existentes
        let somaParcelas = 0;
        parcelas.forEach(p => {
            somaParcelas += parseFloat(p.valor_subtotal || p.valor_total || 0);
        });

        const diferenca = totalContrato - somaParcelas;

        // Atualizar resumo total do contrato
        const elContrato = document.getElementById('resumoTotalContrato');
        if (elContrato) elContrato.textContent = Currency.format(totalContrato);

        // Mostrar/esconder aviso de diferenca
        const elValorDiferenca = document.getElementById('valorDiferenca');

        if (Math.abs(diferenca) > 0.01) {
            if (elAvisoDiferenca) elAvisoDiferenca.classList.remove('hidden');
            if (elValorDiferenca) elValorDiferenca.textContent = Currency.format(Math.abs(diferenca));
        } else {
            if (elAvisoDiferenca) elAvisoDiferenca.classList.add('hidden');
        }
    }

    function contratoTemAutorenovacaoAutomatica() {
        const autoRenovacao = document.getElementById('auto_renovacao')?.value
            || window.contratoData?.auto_renovacao
            || '';
        return autoRenovacao === 'auto';
    }

    // ===== RESUMO COMPLETO (ESTILO FATURA) =====

    /**
     * Atualiza a aba Resumo com formato de fatura compacta
     */
    function atualizarResumoCompleto() {
        const tbody = document.getElementById('resumoFaturaBody');
        if (!tbody) return;

        const dias = parseInt(document.getElementById('dias')?.value) || 1;
        const contagem = document.getElementById('contagem')?.value || 'dia';
        const contagemLabels = i18n.billingLabels || { 'dia': 'Dia', 'semana': 'Semana', 'mes': 'Mês', 'ano': 'Ano' };
        const contagemLabel = contagemLabels[contagem] || (i18n.billingLabels?.dia || 'Dia');
        const desconto = Currency.parse(document.getElementById('valor_desconto')?.value);

        let html = '';
        let totalLocacao = 0;

        // ====== SECAO: VEICULOS ======
        html += `<tr class="bg-slate-100"><td colspan="5" class="px-4 py-2 font-semibold text-slate-700 uppercase text-xs">${i18n.summaryVehicles || 'Veiculos'}</td></tr>`;
        html += `<tr class="text-xs text-slate-500 uppercase border-b border-slate-200">
            <td class="px-4 py-1"></td>
            <td class="px-4 py-1 text-center">${i18n.headerVeic || 'Veic.'}</td>
            <td class="px-4 py-1 text-center">${contagemLabel}</td>
            <td class="px-4 py-1 text-right">${i18n.headerValue || 'Valor'}</td>
            <td class="px-4 py-1 text-right">${i18n.headerTotal || 'Total'}</td>
        </tr>`;

        if (veiculos.length === 0) {
            html += `<tr><td colspan="5" class="px-4 py-3 text-slate-400 italic text-center">${i18n.noVehicles || 'Nenhum veiculo adicionado'}</td></tr>`;
        } else {
            // Agrupar por tipo de plano
            const grupos = agruparVeiculosPorPlano();
            for (const [tipo, dados] of Object.entries(grupos)) {
                if (dados.qtd > 0) {
                    const total = dados.valorTotal * dias;
                    totalLocacao += total;
                    html += `<tr class="border-b border-slate-100">
                        <td class="px-4 py-2">${dados.label}</td>
                        <td class="px-4 py-2 text-center">${dados.qtd}</td>
                        <td class="px-4 py-2 text-center">${dias}</td>
                        <td class="px-4 py-2 text-right">${Currency.format(dados.valorTotal)}</td>
                        <td class="px-4 py-2 text-right font-medium">${Currency.format(total)}</td>
                    </tr>`;
                }
            }

            // Seguros
            const seguros = agruparSeguros();
            for (const [tipo, dados] of Object.entries(seguros)) {
                if (dados.qtd > 0) {
                    const total = dados.valorTotal * dias;
                    totalLocacao += total;
                    html += `<tr class="border-b border-slate-100">
                        <td class="px-4 py-2">${dados.label}</td>
                        <td class="px-4 py-2 text-center">${dados.qtd}</td>
                        <td class="px-4 py-2 text-center">${dias}</td>
                        <td class="px-4 py-2 text-right">${Currency.format(dados.valorTotal)}</td>
                        <td class="px-4 py-2 text-right font-medium">${Currency.format(total)}</td>
                    </tr>`;
                }
            }
        }

        // ====== SECAO: TAXAS E SERVICOS ======
        const diasReais = calcularDiasReais(dias, contagem);

        html += `<tr class="bg-slate-100"><td colspan="5" class="px-4 py-2 font-semibold text-slate-700 uppercase text-xs">${i18n.summaryFeesServices || 'Taxas e Servicos'}</td></tr>`;
        html += `<tr class="text-xs text-slate-500 uppercase border-b border-slate-200">
            <td class="px-4 py-1"></td>
            <td class="px-4 py-1 text-center">${i18n.qty || 'Qtd'}</td>
            <td class="px-4 py-1 text-center">${contagemLabel}</td>
            <td class="px-4 py-1 text-right">${i18n.headerValue || 'Valor'}</td>
            <td class="px-4 py-1 text-right">${i18n.headerTotal || 'Total'}</td>
        </tr>`;

        if (taxas.length === 0) {
            html += `<tr><td colspan="5" class="px-4 py-3 text-slate-400 italic text-center">${i18n.noFees || 'Nenhuma taxa adicionada'}</td></tr>`;
        } else {
            const valorTotalVeiculos = calcularValorTotalVeiculos();
            const valorDiario = diasReais > 0 ? valorTotalVeiculos / diasReais : 0;

            taxas.forEach(t => {
                const valorTotal = calcularValorTotalTaxa(t, diasReais, valorTotalVeiculos);
                totalLocacao += valorTotal;

                const baseCalculo = t.base_calculo || 'FIX';
                const tipoValor = t.tipo_valor || 'MON';
                const valorUnit = parseFloat(t.valor_unitario) || 0;

                // Coluna Periodo: Fixo ou numero de periodos
                const colPeriodo = baseCalculo === 'PER' ? dias : (i18n.fixed || 'Fixo');

                // Coluna Valor: valor unitario em R$ (calculado se for %)
                let colValor;
                let linhaExplicacao = '';

                if (tipoValor === 'POR') {
                    // Porcentagem - calcular valor monetario
                    let valorBase;
                    let textoBase;

                    if (baseCalculo === 'VLT' || baseCalculo === 'FIX') {
                        // % sobre valor total
                        valorBase = valorTotalVeiculos * (valorUnit / 100);
                        textoBase = Currency.format(valorTotalVeiculos);
                    } else {
                        // PER - % sobre valor diario
                        valorBase = valorDiario * (valorUnit / 100);
                        textoBase = `${Currency.format(valorDiario)}/dia`;
                    }

                    colValor = Currency.format(valorBase);
                    linhaExplicacao = `<tr class="text-xs text-slate-400">
                        <td colspan="5" class="px-4 pb-2 pt-0">
                            <span class="ml-2">↳ ${valorUnit}% sobre ${textoBase}</span>
                        </td>
                    </tr>`;
                } else if (baseCalculo === 'PER') {
                    // MON + PER - valor por dia
                    colValor = `${Currency.format(valorUnit)}/dia`;
                } else {
                    // MON + FIX/VLT - valor direto
                    colValor = Currency.format(valorUnit);
                }

                html += `<tr class="border-b border-slate-100">
                    <td class="px-4 py-2">${escapeHtml(t.nome)}</td>
                    <td class="px-4 py-2 text-center">${t.quantidade}</td>
                    <td class="px-4 py-2 text-center">${colPeriodo}</td>
                    <td class="px-4 py-2 text-right">${colValor}</td>
                    <td class="px-4 py-2 text-right font-medium">${Currency.format(valorTotal)}</td>
                </tr>`;

                // Adicionar linha de explicacao para porcentagens
                if (linhaExplicacao) {
                    html += linhaExplicacao;
                }
            });
        }

        const caucaoValor = Currency.parse(document.getElementById('caucao_valor')?.value || '0');
        if (caucaoValor > 0) {
            const formaPagamentoSelect = document.getElementById('id_forma_pagamento_caucao');
            const formaPagamentoTexto = formaPagamentoSelect?.options[formaPagamentoSelect.selectedIndex]?.text || '';
            const lancarFinanceiro = document.getElementById('caucao_lancar_financeiro')?.value === '1';

            html += `<tr class="bg-slate-100"><td colspan="5" class="px-4 py-2 font-semibold text-slate-700 uppercase text-xs">${i18n.summaryGuarantees || 'Garantias'}</td></tr>`;
            html += `<tr class="border-b border-slate-100">
                <td class="px-4 py-2">${i18n.depositTitle || 'Caucao (Deposito de Garantia)'}</td>
                <td class="px-4 py-2 text-center">1</td>
                <td class="px-4 py-2 text-center">${escapeHtml(formaPagamentoTexto)}</td>
                <td class="px-4 py-2 text-right">${lancarFinanceiro ? (i18n.yes || 'Sim') : (i18n.no || 'Nao')}</td>
                <td class="px-4 py-2 text-right font-medium">${Currency.format(caucaoValor)}</td>
            </tr>`;
        }

        // ====== SECAO: TOTAIS ======
        html += `<tr class="bg-slate-200"><td colspan="5" class="px-4 py-2 font-semibold text-slate-700 uppercase text-xs">${i18n.summaryTotals || 'Totais'}</td></tr>`;
        html += `<tr class="border-b border-slate-200">
            <td colspan="4" class="px-4 py-2 text-right">${i18n.summaryRentalTotal || 'Total da locacao'}</td>
            <td class="px-4 py-2 text-right font-medium">${Currency.format(totalLocacao)}</td>
        </tr>`;
        html += `<tr class="border-b border-slate-200">
            <td colspan="4" class="px-4 py-2 text-right text-red-600">${i18n.summaryDiscount || 'Desconto(-)'}</td>
            <td class="px-4 py-2 text-right font-medium text-red-600">${Currency.format(desconto)}</td>
        </tr>`;
        html += `<tr class="bg-green-50">
            <td colspan="4" class="px-4 py-3 text-right font-semibold text-slate-700">${i18n.summaryTotalToPay || 'Total a pagar'}</td>
            <td class="px-4 py-3 text-right font-bold text-xl text-green-600">${Currency.format(totalLocacao - desconto, true)}</td>
        </tr>`;

        tbody.innerHTML = html;
    }

    /**
     * Agrupa veiculos por tipo de plano para o resumo
     */
    function agruparVeiculosPorPlano() {
        const grupos = {
            km_livre: { label: i18n.planKmFreeLabel || 'Plano Km Livre', qtd: 0, valorTotal: 0 },
            km_controlado: { label: i18n.planKmControlledLabel || 'Plano Km Controlado', qtd: 0, valorTotal: 0 },
            km_pago: { label: i18n.planKmPaidLabel || 'Plano Km Pago', qtd: 0, valorTotal: 0 }
        };
        veiculos.forEach(v => {
            const plano = mapearPlanoParaGrupo(v.plano);
            if (grupos[plano]) {
                grupos[plano].qtd++;
                grupos[plano].valorTotal += getValorPlano(v);
            }
        });
        return grupos;
    }

    /**
     * Agrupa seguros para o resumo
     */
    function agruparSeguros() {
        const seguros = {
            carro: { label: i18n.summaryVehicleInsurance || 'Seguro do veiculo', qtd: 0, valorTotal: 0 },
            terceiros: { label: i18n.summaryThirdPartyInsurance || 'Seguro para terceiros', qtd: 0, valorTotal: 0 }
        };
        veiculos.forEach(v => {
            if (v.seguro_carro) {
                seguros.carro.qtd++;
                seguros.carro.valorTotal += parseFloat(v.valor_seguro_carro) || 0;
            }
            if (v.seguro_terceiros) {
                seguros.terceiros.qtd++;
                seguros.terceiros.valorTotal += parseFloat(v.valor_seguro_terceiros) || 0;
            }
        });
        return seguros;
    }

    /**
     * Mapeia codigo do plano para chave do grupo
     */
    function mapearPlanoParaGrupo(plano) {
        const mapa = {
            'KL': 'km_livre',
            'KMC': 'km_controlado',
            'KP': 'km_pago'
        };
        return mapa[plano] || 'km_livre';
    }

    /**
     * Recalcula taxas e totais quando o periodo muda
     */
    function recalcularTaxasEPeriodo() {
        renderizarTaxas();
        atualizarTotais();
    }

    // ===== CLIENTE =====

    function exibirDadosCliente(dados) {
        const container = document.getElementById('dadosClienteSelecionado');
        if (!container) return;

        if (dados) {
            container.classList.remove('hidden');
            const elDoc = document.getElementById('clienteDocumento');
            const elTel = document.getElementById('clienteTelefone');
            const elEmail = document.getElementById('clienteEmail');
            if (elDoc) elDoc.textContent = dados.cpf_cnpj || '-';
            if (elTel) elTel.textContent = dados.tel_cel || '-';
            if (elEmail) elEmail.textContent = dados.email || '-';
        } else {
            container.classList.add('hidden');
        }
    }

    // ===== RENOVACAO =====

    function toggleDataRenovacao() {
        const autoRenovacao = document.getElementById('auto_renovacao')?.value;
        const divDataRenovacao = document.getElementById('divDataRenovacao');
        const inputDataRenovacao = document.getElementById('data_renovacao');

        if (autoRenovacao) {
            divDataRenovacao.style.display = 'block';
            inputDataRenovacao.required = true;

            // Calcular data renovacao baseado em data_ini + contagem/dias
            if (!inputDataRenovacao.value) {
                const dataIni = document.getElementById('data_ini')?.value;
                const dias = parseInt(document.getElementById('dias')?.value) || 1;
                const contagem = document.getElementById('contagem')?.value;

                if (dataIni && dias > 0) {
                    const dataRenovacao = calcularDataFimPorPeriodo(dataIni, dias, contagem);
                    inputDataRenovacao.value = dataRenovacao.toISOString().substring(0, 10);
                }
            }
        } else {
            divDataRenovacao.style.display = 'none';
            inputDataRenovacao.required = false;
            inputDataRenovacao.value = '';
        }
    }

    // ===== ABAS =====

    function configurarAbas() {
        const formTabButtons = document.querySelectorAll('#formTabsNav .form-tab-button');
        const formTabContents = document.querySelectorAll('.form-tab-content');
        const btnSalvar = document.getElementById('btnSalvar');
        const isEditing = /\/contratos\/editar\/\d+/.test(window.location.pathname);

        // Ocultar botao salvar inicialmente (aba Cliente esta ativa) — apenas no modo criacao
        if (btnSalvar && !isEditing) {
            btnSalvar.style.display = 'none';
        }

        formTabButtons.forEach(button => {
            button.addEventListener('click', () => {
                formTabButtons.forEach(btn => btn.classList.remove('active'));
                formTabContents.forEach(content => content.classList.remove('active'));

                button.classList.add('active');
                const targetId = button.dataset.formTabTarget;
                document.querySelector(targetId)?.classList.add('active');

                // Mostrar/ocultar botao salvar baseado na aba — apenas no modo criacao
                if (btnSalvar && !isEditing) {
                    btnSalvar.style.display = (targetId === '#tabResumo') ? '' : 'none';
                }

                // Atualizar resumo quando aba Resumo for ativada
                if (targetId === '#tabResumo') {
                    atualizarResumoCompleto();
                }
            });
        });
    }

    // ===== CONFIGURAR EVENTOS =====

    function configurarEventos() {
        // Botao voltar
        document.getElementById('btnVoltar')?.addEventListener('click', voltar);

        // Abas
        configurarAbas();

        // Mascaras de moeda
        Currency.applyMaskToAll('input-moeda');

        // Auto renovacao
        document.getElementById('auto_renovacao')?.addEventListener('change', toggleDataRenovacao);

        // Filial muda -> recarregar grupos
        document.getElementById('id_matriz_filial_retirada')?.addEventListener('change', function () {
            carregarGrupos();
        });

        // ===== EVENTOS DO OFFCANVAS DE VEICULO =====

        // Botao adicionar veiculo -> abre offcanvas via postMessage
        document.getElementById('btnAdicionarVeiculo')?.addEventListener('click', () => {
            abrirVeiculoOffcanvas('adicionar');
        });

        // Adicionar pessoas
        document.getElementById('btnAdicionarCondutor')?.addEventListener('click', () => adicionarCondutor());
        document.getElementById('btnAdicionarFiador')?.addEventListener('click', () => adicionarPessoaSemCnh('fiador'));
        document.getElementById('btnAdicionarAvalista')?.addEventListener('click', () => adicionarPessoaSemCnh('avalista'));
        document.getElementById('btnAdicionarTestemunha')?.addEventListener('click', () => adicionarPessoaSemCnh('testemunha'));

        // Adicionar taxa
        document.getElementById('btnAdicionarTaxa')?.addEventListener('click', adicionarTaxa);

        // Desconto muda -> atualizar totais
        document.getElementById('valor_desconto')?.addEventListener('blur', atualizarTotais);

        document.getElementById('caucao_valor')?.addEventListener('input', () => {
            atualizarCaucaoRequired();
            atualizarResumoCompleto();
        });
        document.getElementById('caucao_valor')?.addEventListener('blur', atualizarTotais);
        document.getElementById('id_forma_pagamento_caucao')?.addEventListener('change', atualizarResumoCompleto);
        document.getElementById('caucao_lancar_financeiro')?.addEventListener('change', atualizarResumoCompleto);

        // ===== EVENTOS DE PERIODO (contagem, data_ini, data_fim, dias) =====

        // Contagem muda -> atualizar label e validar período atual
        document.getElementById('contagem')?.addEventListener('change', function () {
            atualizarLabelDias(this.value);

            const dataIni = document.getElementById('data_ini')?.value;
            const dataFim = document.getElementById('data_fim')?.value;
            const dias = document.getElementById('dias')?.value;

            // Se tem dias preenchido, recalcula data fim
            if (dataIni && dias) {
                const novaDataFim = calcularDataFimPorPeriodo(dataIni, parseInt(dias), this.value);
                const dataFimEl = document.getElementById('data_fim');
                if (dataFimEl) dataFimEl.value = formatDateTimeLocal(novaDataFim);
            }
            // Se tem data fim mas não tem dias, valida o período
            else if (dataIni && dataFim) {
                const resultado = calcularQuantidadePeriodos(dataIni, dataFim, this.value);
                if (!resultado.exato) {
                    const labels = { dia: 'dias', semana: 'semanas', mes: 'meses', ano: 'anos' };
                    window.parent.postMessage({
                        action: 'openAlert',
                        message: `A contagem não é equivalente ao período da data inicial e fim. O período não corresponde a um número exato de ${labels[this.value]}.`
                    }, '*');
                    const dataFimEl = document.getElementById('data_fim');
                    if (dataFimEl) dataFimEl.value = '';
                } else {
                    const diasEl = document.getElementById('dias');
                    if (diasEl) diasEl.value = resultado.quantidade;
                }
            }

            // Recalcular taxas (a base de calculo pode ser por periodo)
            recalcularTaxasEPeriodo();

            // Recalcular data renovacao se ativo
            const dataRenovacaoEl = document.getElementById('data_renovacao');
            if (dataRenovacaoEl) dataRenovacaoEl.value = '';
            toggleDataRenovacao();
        });

        // Data fim muda -> calcular quantidade de períodos
        document.getElementById('data_fim')?.addEventListener('change', function () {
            const dataIni = document.getElementById('data_ini')?.value;
            const dataFim = this.value;
            const contagem = document.getElementById('contagem')?.value;

            if (dataIni && dataFim) {
                const resultado = calcularQuantidadePeriodos(dataIni, dataFim, contagem);

                if (!resultado.exato || resultado.quantidade <= 0) {
                    const labels = { dia: 'dias', semana: 'semanas', mes: 'meses', ano: 'anos' };
                    window.parent.postMessage({
                        action: 'openAlert',
                        message: `A contagem não é equivalente ao período da data inicial e fim. O período não corresponde a um número exato de ${labels[contagem]}.`
                    }, '*');
                    const dataFimEl = document.getElementById('data_fim');
                    if (dataFimEl) dataFimEl.value = '';
                    return;
                }

                const diasEl = document.getElementById('dias');
                if (diasEl) diasEl.value = resultado.quantidade;
                recalcularTaxasEPeriodo();
            }
        });

        // Dias muda -> calcular data fim
        document.getElementById('dias')?.addEventListener('input', function () {
            const dataIni = document.getElementById('data_ini')?.value;
            const dias = parseInt(this.value);
            const contagem = document.getElementById('contagem')?.value;

            if (dataIni && dias > 0) {
                const novaDataFim = calcularDataFimPorPeriodo(dataIni, dias, contagem);
                const dataFimEl = document.getElementById('data_fim');
                if (dataFimEl) dataFimEl.value = formatDateTimeLocal(novaDataFim);
            }

            recalcularTaxasEPeriodo();

            // Recalcular data renovacao se ativo
            const dataRenovacaoEl = document.getElementById('data_renovacao');
            if (dataRenovacaoEl) dataRenovacaoEl.value = '';
            toggleDataRenovacao();
        });

        // Data início muda -> recalcular data fim se dias preenchido
        document.getElementById('data_ini')?.addEventListener('change', function () {
            const dias = parseInt(document.getElementById('dias')?.value);
            const contagem = document.getElementById('contagem')?.value;

            if (this.value && dias > 0) {
                const novaDataFim = calcularDataFimPorPeriodo(this.value, dias, contagem);
                const dataFimEl = document.getElementById('data_fim');
                if (dataFimEl) dataFimEl.value = formatDateTimeLocal(novaDataFim);
            }

            // Recalcular data renovacao se ativo
            const dataRenovacaoEl = document.getElementById('data_renovacao');
            if (dataRenovacaoEl) dataRenovacaoEl.value = '';
            toggleDataRenovacao();
        });

        // Cliente selecionado
        document.getElementById('id_cliente')?.addEventListener('change', async function () {
            const clienteId = this.value;
            if (clienteId) {
                try {
                    const result = await API.get(`/api/clientes/${clienteId}`);
                    if (result.success) {
                        exibirDadosCliente(result.data);
                    }
                } catch (e) {
                    console.error('Erro ao buscar cliente:', e);
                }
            } else {
                exibirDadosCliente(null);
            }
        });

        // Novo cliente
        document.getElementById('btnNovoCliente')?.addEventListener('click', function () {
            window.parent.openOrSwitchToTab('/pages/clientes/adicionar', i18n.newClient || 'Novo Cliente', 'fas fa-user-plus');
        });

        // Taxa selecionada
        document.getElementById('taxa_select')?.addEventListener('change', function () {
            const taxa = taxasDisponiveis.find(t => t.id == this.value);
            const simboloEl = document.querySelector('#taxa_valor')?.parentElement?.querySelector('.currency-symbol');
            const inputValor = document.getElementById('taxa_valor');

            if (taxa) {
                // Armazenar taxa selecionada com todos os dados
                taxaSelecionadaAtual = {
                    id: taxa.id,
                    nome: taxa.text || taxa.nome || '',
                    valor: taxa.valor,
                    base_calculo: taxa.base_calculo || 'FIX',
                    tipo_valor: taxa.tipo_valor || 'MON'
                };

                // Preencher campos
                const taxaNomeEl = document.getElementById('taxa_nome');
                if (taxaNomeEl) taxaNomeEl.value = taxaSelecionadaAtual.nome;
                const taxaQtdEl = document.getElementById('taxa_qtd');
                if (taxaQtdEl) taxaQtdEl.value = 1;

                // Atualizar simbolo e valor baseado no tipo
                if (taxaSelecionadaAtual.tipo_valor === 'POR') {
                    // Porcentagem - mostrar % e valor sem formatacao de moeda
                    if (simboloEl) simboloEl.textContent = '%';
                    inputValor.value = taxa.valor ? taxa.valor.toString().replace('.', ',') : '';
                    inputValor.classList.remove('input-moeda');
                } else {
                    // Monetario - mostrar R$ e valor com formatacao
                    if (simboloEl) simboloEl.textContent = 'R$';
                    inputValor.value = taxa.valor ? Currency.format(taxa.valor) : '';
                    inputValor.classList.add('input-moeda');
                }

                // Verificar permissao para desbloquear campo valor
                if (window.userPermissions?.editar_valor_taxas) {
                    inputValor.readOnly = false;
                    inputValor.classList.remove('bg-slate-100');
                }
            } else {
                taxaSelecionadaAtual = null;
                // Resetar para padrao (R$)
                if (simboloEl) simboloEl.textContent = 'R$';
                inputValor?.classList.add('input-moeda');
            }
        });

        // Eventos da aba Financeiro
        configurarEventosFinanceiro();

        // Submissao do formulario
        form.addEventListener('submit', salvar);
    }

    // ===== AUDITORIA =====

    /**
     * Constroi _audit_data completo para log de criacao de contrato.
     * Usa os arrays JS (veiculos, taxas, etc.) em vez de captura DOM,
     * garantindo que todos os dados sejam registrados no log.
     */
    function buildAuditData() {
        const data = {};

        // Labels auxiliares
        const planoLabelsAudit = { 'KL': 'Km Livre', 'KMC': 'Km Controlado', 'KP': 'Km Pago' };
        const contagemLabels = { 'dia': 'Dia', 'semana': 'Semana', 'mes': 'Mes', 'ano': 'Ano' };
        const autoRenovacaoLabels = {
            '': 'Desativada', 'auto': 'Automatica', 'fim': 'Encerrada',
            '1x': '1 Renovacao', '2x': '2 Renovacoes', '3x': '3 Renovacoes',
            '4x': '4 Renovacoes', '5x': '5 Renovacoes', '6x': '6 Renovacoes',
            '7x': '7 Renovacoes', '8x': '8 Renovacoes', '9x': '9 Renovacoes',
            '10x': '10 Renovacoes', '11x': '11 Renovacoes', '12x': '12 Renovacoes'
        };

        function getSelectText(id) {
            const el = document.getElementById(id);
            if (!el) return '';
            const opt = el.options[el.selectedIndex];
            return (opt && opt.value) ? opt.text.trim() : '';
        }

        function formatDtLocal(val) {
            if (!val) return '';
            try {
                const d = new Date(val);
                return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            } catch { return val; }
        }

        function formatDt(val) {
            if (!val) return '';
            const p = val.split('-');
            return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : val;
        }

        function fmtMoney(val) {
            if (window.Currency) return Currency.format(parseFloat(val) || 0, true);
            return 'R$ ' + (parseFloat(val) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // === Dados Gerais ===
        const dadosGerais = [];
        const filialText = getSelectText('id_matriz_filial_retirada');
        if (filialText) dadosGerais.push({ label: 'Matriz/Filial', de: null, para: filialText });

        const dataIni = document.getElementById('data_ini')?.value;
        if (dataIni) dadosGerais.push({ label: 'Data Inicio', de: null, para: formatDtLocal(dataIni) });

        const dataFim = document.getElementById('data_fim')?.value;
        if (dataFim) dadosGerais.push({ label: 'Data Fim', de: null, para: formatDtLocal(dataFim) });

        const contagem = document.getElementById('contagem')?.value || '';
        dadosGerais.push({ label: 'Contagem', de: null, para: contagemLabels[contagem] || contagem || 'Dia' });

        const dias = document.getElementById('dias')?.value || '1';
        const unidade = (contagemLabels[contagem] || contagem || 'dia').toLowerCase();
        dadosGerais.push({ label: 'Periodo', de: null, para: dias + ' ' + unidade + (parseInt(dias) > 1 ? 's' : '') });

        const autoRenov = document.getElementById('auto_renovacao')?.value || '';
        dadosGerais.push({ label: 'Autorenovacao', de: null, para: autoRenovacaoLabels[autoRenov] || autoRenov || 'Desativada' });

        if (autoRenov && autoRenov !== '') {
            const dataRenov = document.getElementById('data_renovacao')?.value;
            if (dataRenov) dadosGerais.push({ label: 'Data Renovacao', de: null, para: formatDt(dataRenov) });
        }

        data['Dados Gerais'] = dadosGerais;

        // === Cliente ===
        const clienteText = getSelectText('id_cliente');
        data['Cliente'] = [{ label: 'Cliente', de: null, para: clienteText || '' }];

        // === Veiculos ===
        if (veiculos.length > 0) {
            data['Veiculos'] = [{ label: 'Veiculos do Contrato', de: null, para: veiculos.map(v => {
                const item = {};
                item['Veiculo'] = (v.placa || '') + ' - ' + (v.modelo || '');
                item['Plano'] = planoLabelsAudit[v.plano] || v.plano || '';
                item['Valor'] = fmtMoney(getValorPlano(v));
                item['Km Saída'] = (v.odometro_saida || 0) + ' km';
                item['Combustivel'] = (v.combustivel_saida || 0) + '/8';
                item['Seguro Veiculo'] = v.seguro_carro ? 'Sim (' + fmtMoney(v.valor_seguro_carro) + ')' : 'Nao';
                item['Seguro Terceiros'] = v.seguro_terceiros ? 'Sim (' + fmtMoney(v.valor_seguro_terceiros) + ')' : 'Nao';
                return item;
            }) }];
        } else {
            data['Veiculos'] = [{ label: 'Veiculos do Contrato', de: null, para: 'Nenhum veiculo adicionado.' }];
        }

        // === Condutor Adicional ===
        const condutoresValidos = condutores.filter(c => c.id);
        if (condutoresValidos.length > 0) {
            data['Condutor Adicional'] = [{ label: 'Condutores', de: null, para: condutoresValidos.map(c => ({
                'Nome': c.nome || '', 'CPF': c.cc || '', 'CNH': c.cn || ''
            })) }];
        } else {
            data['Condutor Adicional'] = [{ label: 'Condutores', de: null, para: 'Nenhum condutor adicionado.' }];
        }

        // === Fiadores ===
        const fiadoresValidos = fiadores.filter(f => f.id);
        if (fiadoresValidos.length > 0) {
            data['Fiadores'] = [{ label: 'Fiadores', de: null, para: fiadoresValidos.map(f => ({
                'Nome': f.nome || '', 'CPF': f.cc || ''
            })) }];
        } else {
            data['Fiadores'] = [{ label: 'Fiadores', de: null, para: 'Nenhum fiador adicionado.' }];
        }

        // === Avalistas ===
        const avalistasValidos = avalistas.filter(a => a.id);
        if (avalistasValidos.length > 0) {
            data['Avalistas'] = [{ label: 'Avalistas', de: null, para: avalistasValidos.map(a => ({
                'Nome': a.nome || '', 'CPF': a.cc || ''
            })) }];
        } else {
            data['Avalistas'] = [{ label: 'Avalistas', de: null, para: 'Nenhum avalista adicionado.' }];
        }

        // === Testemunhas ===
        const testemunhasValidas = testemunhas.filter(t => t.id);
        if (testemunhasValidas.length > 0) {
            data['Testemunhas'] = [{ label: 'Testemunhas', de: null, para: testemunhasValidas.map(t => ({
                'Nome': t.nome || '', 'CPF': t.cc || ''
            })) }];
        } else {
            data['Testemunhas'] = [{ label: 'Testemunhas', de: null, para: 'Nenhuma testemunha adicionada.' }];
        }

        // === Taxas e Servicos ===
        if (taxas.length > 0) {
            data['Taxas e Servicos'] = [{ label: 'Taxas', de: null, para: taxas.map(t => ({
                'Taxa': t.nome || '', 'Quantidade': String(t.quantidade || 1), 'Valor Unitario': fmtMoney(t.valor_unitario)
            })) }];
        } else {
            data['Taxas e Servicos'] = [{ label: 'Taxas', de: null, para: 'Nenhum taxa/servico adicionado.' }];
        }

        // === Financeiro ===
        const financeiro = [];
        const contaText = getSelectText('id_conta');
        if (contaText) financeiro.push({ label: 'Conta Bancaria', de: null, para: contaText });

        const formaText = getSelectText('id_forma_pagamento');
        if (formaText) financeiro.push({ label: 'Forma de Pagamento', de: null, para: formaText });

        const comandoText = getSelectText('id_comando_parcela');
        if (comandoText) financeiro.push({ label: 'Comando de Parcela', de: null, para: comandoText });

        const primeiroVenci = document.getElementById('primeiro_vencimento')?.value;
        if (primeiroVenci) financeiro.push({ label: 'Primeiro Vencimento', de: null, para: formatDt(primeiroVenci) });

        const desconto = document.getElementById('valor_desconto')?.value;
        financeiro.push({ label: 'Desconto', de: null, para: fmtMoney(window.Currency ? Currency.parse(desconto || '0') : parseFloat(desconto) || 0) });

        const caucaoValor = document.getElementById('caucao_valor')?.value || '0';
        const caucaoValorNum = window.Currency ? Currency.parse(caucaoValor) : parseFloat(caucaoValor) || 0;
        if (caucaoValorNum > 0) {
            const caucaoConta = getSelectText('id_conta_caucao');
            const caucaoFormaPagamento = getSelectText('id_forma_pagamento_caucao');
            const caucaoPrazo = document.getElementById('caucao_prazo_devolucao')?.value || '';
            const caucaoLancar = document.getElementById('caucao_lancar_financeiro')?.value === '1';
            financeiro.push({ label: 'Caucao', de: null, para: fmtMoney(caucaoValorNum) });
            if (caucaoConta) financeiro.push({ label: 'Conta da Caucao', de: null, para: caucaoConta });
            if (caucaoFormaPagamento) financeiro.push({ label: 'Forma de Pagamento da Caucao', de: null, para: caucaoFormaPagamento });
            if (caucaoPrazo) financeiro.push({ label: 'Prazo de Devolucao da Caucao', de: null, para: `${caucaoPrazo} dia(s)` });
            financeiro.push({ label: 'Lancar Caucao no Financeiro', de: null, para: caucaoLancar ? 'Sim' : 'Nao' });
        }

        // Calcular total a pagar
        const qtdPeriodos = parseInt(document.getElementById('dias')?.value) || 1;
        const contagemVal = document.getElementById('contagem')?.value || 'dia';
        const diasReais = calcularDiasReais(qtdPeriodos, contagemVal);
        const totalVeiculos = calcularValorTotalVeiculos();
        let totalTaxasCalc = 0;
        taxas.forEach(t => { totalTaxasCalc += calcularValorTotalTaxa(t, diasReais, totalVeiculos); });
        const descontoVal = window.Currency ? Currency.parse(desconto || '0') : parseFloat(desconto) || 0;
        const totalPagar = totalVeiculos + totalTaxasCalc - descontoVal;
        financeiro.push({ label: 'Total a Pagar', de: null, para: fmtMoney(totalPagar) });

        data['Financeiro'] = financeiro;

        // === Parcelas ===
        if (parcelas.length > 0) {
            data['Parcelas'] = [{ label: 'Parcelas Geradas', de: null, para: parcelas.map(p => ({
                'Parcela': (p.parcela || '?') + '/' + (p.total_parcelas || parcelas.length),
                'Vencimento': formatDt(p.data_venci || ''),
                'Valor': fmtMoney(p.valor_subtotal || p.valor_total || 0)
            })) }];
        } else {
            data['Parcelas'] = [{ label: 'Parcelas Geradas', de: null, para: 'Nenhuma parcela gerada.' }];
        }

        // === Observacoes ===
        const obs = document.getElementById('obs')?.value?.trim();
        data['Observacoes'] = [{ label: 'Observacoes', de: null, para: obs || 'Nenhuma observacao.' }];

        return data;
    }

    // ===== SALVAR =====

    async function salvar(e) {
        e.preventDefault();

        // Validacoes
        if (!document.getElementById('id_cliente')?.value) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectClient || 'Selecione um cliente' }, '*');
            document.querySelector('[data-form-tab-target="#tabCliente"]').click();
            return;
        }

        if (veiculos.length === 0) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.addAtLeastOneVehicle || 'Adicione pelo menos um veiculo' }, '*');
            document.querySelector('[data-form-tab-target="#tabVeiculos"]').click();
            return;
        }

        // Verificar se há taxa selecionada não adicionada
        const taxaSelect = document.getElementById('taxa_select');
        if (taxaSelect && taxaSelect.value) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.feeNotAdded || 'Você selecionou uma taxa/serviço mas não adicionou à lista.' }, '*');
            document.querySelector('[data-form-tab-target="#tabTaxas"]').click();
            return;
        }

        // Validacao de datas
        const dataIni = document.getElementById('data_ini')?.value;
        const dataFim = document.getElementById('data_fim')?.value;
        if (dataIni && dataFim && new Date(dataFim) <= new Date(dataIni)) {
            window.parent.postMessage({ action: 'openAlert', message: 'A data fim deve ser posterior a data inicio' }, '*');
            return;
        }

        // Validacao de parcelas no financeiro (obrigatoria apenas na criacao)
        if (!editando && parcelas.length === 0) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.generateInstallmentsFirst || 'Gere pelo menos uma parcela no financeiro' }, '*');
            document.querySelector('[data-form-tab-target="#tabFinanceiro"]').click();
            return;
        }

        if (!validarCaucao()) {
            return;
        }

        const btnSalvar = document.getElementById('btnSalvar');
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${i18n.saving || 'Salvando...'}`;

        try {
            const formData = new FormData(form);
            const dados = Object.fromEntries(formData.entries());

            // Sobrescrever _audit_data com versao completa (arrays JS, nao DOM)
            if (!editando) {
                dados._audit_data = JSON.stringify(buildAuditData());
            }

            // Converter valores monetarios
            dados.valor_desconto = Currency.parse(dados.valor_desconto || '0');
            dados.primeiro_pagamento = Currency.parse(dados.primeiro_pagamento || '0');
            dados.caucao_valor = Currency.parse(dados.caucao_valor || '0');
            dados.caucao_lancar_financeiro = document.getElementById('caucao_lancar_financeiro')?.value === '1' ? '1' : '0';

            // Veiculos
            dados.veiculos = veiculos.map(v => normalizarValoresPlanoVeiculo({
                id_veiculo: v.id_veiculo,
                id_grupo: v.id_grupo,
                plano: v.plano,
                valor_plano_km_livre: v.valor_plano_km_livre,
                valor_plano_km_controlado: v.valor_plano_km_controlado,
                valor_plano_km_pago: v.valor_plano_km_pago,
                valor_km_excedente: v.valor_km_excedente || 0,
                km_franquia: v.km_franquia || 0,
                odometro_saida: v.odometro_saida,
                combustivel_saida: v.combustivel_saida,
                seguro_carro: v.seguro_carro ? 1 : 0,
                valor_seguro_carro: v.valor_seguro_carro,
                seguro_terceiros: v.seguro_terceiros ? 1 : 0,
                valor_seguro_terceiros: v.valor_seguro_terceiros
            }));

            // Taxas
            dados.taxas = taxas.map(t => ({
                id_taxa: t.id_taxa,
                nome: t.nome,
                quantidade: t.quantidade,
                valor_unitario: t.valor_unitario,
                base_calculo: t.base_calculo || 'FIX',
                tipo_valor: t.tipo_valor || 'MON'
            }));

            // Pessoas - Novo formato JSON
            dados.condutor_adicional = condutores.filter(c => c.id).map(c => ({
                id: c.id,
                nome: c.nome,
                cc: c.cc,
                cn: c.cn,
                va: c.va
            }));
            dados.array_fiadores = fiadores.filter(f => f.id).map(f => ({
                id: f.id,
                nome: f.nome,
                cc: f.cc,
                endereco: f.endereco
            }));
            dados.array_avalistas = avalistas.filter(a => a.id).map(a => ({
                id: a.id,
                nome: a.nome,
                cc: a.cc,
                endereco: a.endereco
            }));
            dados.array_testemunhas = testemunhas.filter(t => t.id).map(t => ({
                id: t.id,
                nome: t.nome,
                cc: t.cc,
                endereco: t.endereco
            }));

            let url;
            if (editando && registroId) {
                url = `/contratos/${registroId}/atualizar`;
            } else {
                url = '/contratos/salvar';
            }

            const result = await API.post(url, dados);

            if (result.success) {
                // Contrato novo com parcelas: salvar parcelas com o novo ID
                if (!editando && parcelas.length > 0 && result.data?.id) {
                    registroId = result.data.id;
                    await salvarParcelas();
                }

                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'showToast',
                        type: 'success',
                        message: editando ? (i18n.contractUpdated || 'Contrato atualizado com sucesso!') : (i18n.contractCreated || 'Contrato criado com sucesso!')
                    }, '*');
                }
                voltar();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || (i18n.saveError || 'Erro ao salvar') }, '*');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            window.parent.postMessage({ action: 'openAlert', message: i18n.saveError || 'Erro ao salvar contrato' }, '*');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = `<i class="fas fa-save mr-2"></i>${i18n.save || 'Salvar'}`;
        }
    }

    // ===== GESTAO FINANCEIRA (PARCELAS) =====

    // Mostra informacoes do comando de parcelas selecionado
    async function mostrarInfoComando() {
        const id = document.getElementById('id_comando_parcela')?.value;
        const painel = document.getElementById('infoComandoParcelas');

        if (!id || !painel) {
            painel?.classList.add('hidden');
            return;
        }

        try {
            const result = await API.get(`/api/comandos-parcelas/${id}`);
            if (result.success) {
                const info = result.data.parsed;
                document.getElementById('comandoParcelasTexto').textContent = result.data.comando;
                document.getElementById('comandoParcelasDescricao').textContent = gerarDescricaoComando(info.tipo, info);
                painel.classList.remove('hidden');
            }
        } catch (e) {
            console.error('Erro ao carregar info do comando:', e);
            painel.classList.add('hidden');
        }
    }

    function gerarDescricaoComando(tipo, info) {
        switch (tipo) {
            case 'avista':
                return '\u2192 Pagamento a vista (1 parcela)';
            case 'prazo_unico':
                return `\u2192 1 parcela com vencimento em +${info.intervalos[0]} dias`;
            case 'mensal':
                return '\u2192 Parcelas mensais (qtd calculada automaticamente pela duracao do contrato)';
            case 'prazos_fixos':
                return `\u2192 ${info.intervalos.length} parcelas: +${info.intervalos.join(', +')} dias`;
            case 'semanal':
                return `\u2192 ${info.max} parcelas semanais`;
            case 'semanal_dia':
                return `\u2192 ${info.max} parcelas semanais (toda ${info.dia_semana})`;
            case 'dia_mes':
                return `\u2192 Parcelas no dia ${info.dia_mes} de cada mes (qtd pela duracao do contrato)`;
            case 'dias_semana':
                return `\u2192 Parcelas em ${info.dias_semana.join(', ')} (qtd pela duracao do contrato)`;
            default:
                return '';
        }
    }

    // Gera preview das parcelas
    async function gerarPreviewParcelas() {
        const idConta = document.getElementById('id_conta')?.value;
        const idFormaPagamento = document.getElementById('id_forma_pagamento')?.value;
        const primeiroVencimento = document.getElementById('primeiro_vencimento')?.value;
        const valorDesconto = Currency.parse(document.getElementById('valor_desconto')?.value || '0');

        if (!idFormaPagamento) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.selectPaymentMethod || 'Selecione uma forma de pagamento.' }, '*');
            return;
        }

        if (!primeiroVencimento) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.informFirstDueDate || 'Informe a data do primeiro vencimento.' }, '*');
            return;
        }

        try {
            let result;
            if (registroId) {
                // Contrato existente: endpoint com ID
                result = await API.post(`/api/contratos/${registroId}/gerar-parcelas`, {
                    id_conta: idConta,
                    id_forma_pagamento: idFormaPagamento,
                    id_comando_parcela: document.getElementById('id_comando_parcela')?.value,
                    primeiro_vencimento: primeiroVencimento,
                    valor_desconto: valorDesconto,
                    salvar: false
                });
            } else {
                // Contrato novo: endpoint stateless (sem precisar salvar antes)
                // Calcular totalPagar direto (elemento DOM nao existe no layout)
                const qtdPeriodos = parseInt(document.getElementById('dias')?.value) || 1;
                const contagem = document.getElementById('contagem')?.value || 'dia';
                const diasReais = calcularDiasReais(qtdPeriodos, contagem);
                const descontoCalc = Currency.parse(document.getElementById('valor_desconto')?.value);
                const totalVeiculosPeriodo = calcularValorTotalVeiculos();
                let totalTaxasCalc = 0;
                taxas.forEach(t => { totalTaxasCalc += calcularValorTotalTaxa(t, diasReais, totalVeiculosPeriodo); });
                const totalPagar = totalVeiculosPeriodo + totalTaxasCalc - descontoCalc;
                const dataFim = document.getElementById('data_fim')?.value;

                result = await API.post('/api/contratos/preview-parcelas', {
                    total_pagar: totalPagar,
                    data_fim: dataFim,
                    id_conta: idConta,
                    id_forma_pagamento: idFormaPagamento,
                    id_comando_parcela: document.getElementById('id_comando_parcela')?.value,
                    primeiro_vencimento: primeiroVencimento,
                    valor_desconto: valorDesconto,
                });
            }

            if (result.success) {
                parcelas = result.data.parcelas;
                renderizarParcelas();
                document.getElementById('secaoParcelasGeradas')?.classList.remove('hidden');
                document.getElementById('btnLimparParcelas')?.classList.remove('hidden');
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || (i18n.generateInstallmentsError || 'Erro ao gerar parcelas') }, '*');
            }
        } catch (e) {
            console.error('Erro ao gerar parcelas:', e);
            window.parent.postMessage({ action: 'openAlert', message: i18n.generateInstallmentsError || 'Erro ao gerar parcelas' }, '*');
        }
    }

    // Salva as parcelas no banco
    async function salvarParcelas() {
        if (!registroId || parcelas.length === 0) {
            return;
        }

        const idConta = document.getElementById('id_conta')?.value;
        const idFormaPagamento = document.getElementById('id_forma_pagamento')?.value;
        const primeiroVencimento = document.getElementById('primeiro_vencimento')?.value;
        const valorDesconto = Currency.parse(document.getElementById('valor_desconto')?.value || '0');

        try {
            const result = await API.post(`/api/contratos/${registroId}/gerar-parcelas`, {
                id_conta: idConta,
                id_forma_pagamento: idFormaPagamento,
                id_comando_parcela: document.getElementById('id_comando_parcela')?.value,
                primeiro_vencimento: primeiroVencimento,
                valor_desconto: valorDesconto,
                parcelas: parcelas,
                salvar: true,
                from_creation: !editando
            });

            if (result.success) {
                const enviosCobranca = result.data?.envios_cobranca || [];
                const enviosOk = enviosCobranca.filter(envio => envio && envio.success).length;
                const message = enviosOk > 0
                    ? (i18n.installmentChargesQueued || 'Parcelas salvas e cobranças enfileiradas para envio.')
                    : result.message;

                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'showToast',
                        type: 'success',
                        message: message
                    }, '*');
                }
                await carregarParcelasContrato();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || (i18n.saveInstallmentsError || 'Erro ao salvar parcelas') }, '*');
            }
        } catch (e) {
            console.error('Erro ao salvar parcelas:', e);
            window.parent.postMessage({ action: 'openAlert', message: i18n.saveInstallmentsError || 'Erro ao salvar parcelas' }, '*');
        }
    }

    // Carrega parcelas existentes do contrato
    async function carregarParcelasContrato() {
        if (!registroId) return;

        try {
            const result = await API.get(`/api/contratos/${registroId}/parcelas`);
            if (result.success) {
                parcelas = result.data.parcelas || [];
                parcelasOriginais = JSON.parse(JSON.stringify(parcelas));
                const resumo = result.data.resumo || {};

                // Atualizar resumo
                atualizarResumoFinanceiro(resumo);

                // Mostrar/esconder secoes
                const elResumoFin = document.getElementById('secaoResumoFinanceiro');
                const elParcelasGeradas = document.getElementById('secaoParcelasGeradas');
                const elBtnLimpar = document.getElementById('btnLimparParcelas');
                const elSemParcelas = document.getElementById('semParcelas');
                const elBtnRegenerar = document.getElementById('btnRegenerarPendentes');

                if (parcelas.length > 0) {
                    if (elResumoFin) elResumoFin.classList.remove('hidden');
                    if (elParcelasGeradas) elParcelasGeradas.classList.remove('hidden');
                    if (elBtnLimpar) elBtnLimpar.classList.remove('hidden');
                    if (elSemParcelas) elSemParcelas.classList.add('hidden');
                    renderizarParcelas();

                    // Mostrar botao "Regenerar Pendentes" se ha parcelas pendentes
                    const temPendentes = parcelas.some(p => p.status !== 'pago');
                    if (elBtnRegenerar && temPendentes) elBtnRegenerar.classList.remove('hidden');
                } else {
                    // Estado vazio (sem parcelas)
                    if (elSemParcelas) elSemParcelas.classList.remove('hidden');
                }

                // Verificar diferenca
                const elAvisoDiferenca = document.getElementById('avisoFinanceiroDiferenca');
                const elValorDiferenca = document.getElementById('valorDiferenca');
                if (!contratoTemAutorenovacaoAutomatica() && Math.abs(resumo.diferenca || 0) > 0.01) {
                    if (elAvisoDiferenca) elAvisoDiferenca.classList.remove('hidden');
                    if (elValorDiferenca) elValorDiferenca.textContent = Currency.format(Math.abs(resumo.diferenca));
                } else {
                    if (elAvisoDiferenca) elAvisoDiferenca.classList.add('hidden');
                }
            }
        } catch (e) {
            console.error('Erro ao carregar parcelas:', e);
        }
    }

    // Atualiza resumo financeiro
    function atualizarResumoFinanceiro(resumo) {
        const elContrato = document.getElementById('resumoTotalContrato');
        const elPago = document.getElementById('resumoTotalPago');
        const elPendente = document.getElementById('resumoTotalPendente');
        const elAtrasado = document.getElementById('resumoTotalAtrasado');

        if (elContrato) elContrato.textContent = Currency.format(resumo.total_contrato || 0);
        if (elPago) elPago.textContent = Currency.format(resumo.total_pago || 0);
        if (elPendente) elPendente.textContent = Currency.format(resumo.total_pendente || 0);
        if (elAtrasado) elAtrasado.textContent = Currency.format(resumo.total_atrasado || 0);
    }

    // Renderiza tabela de parcelas
    function renderizarParcelas() {
        const tbody = document.getElementById('tabelaParcelasBody');
        if (!tbody) return;
        esconderFormulariosPagamentoContrato();
        tbody.innerHTML = '';

        if (parcelas.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="px-3 py-4 text-center text-slate-400">${i18n.noInstallments || 'Nenhuma parcela gerada'}</td></tr>`;
            atualizarTotalParcelas();
            return;
        }

        let total = 0;

        parcelas.forEach((parcela, index) => {
            const valor = parseFloat(parcela.valor_total || parcela.valor_subtotal || 0);
            total += valor;

            const isPago = parcela.pago === 'S';
            const temId = !!parcela.id;
            const isAtrasado = !isPago && parcela.data_venci && parcela.data_venci < new Date().toISOString().slice(0, 10);
            const descricao = parcela.descricao || (i18n.installmentLabel || 'Parcela :num').replace(':num', parcela.parcela || (index + 1));
            const contaNome = obterNomeContaParcela(parcela);
            const formaNome = obterNomeFormaPagamentoParcela(parcela);

            let statusHtml = '';
            if (isPago) {
                statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">${i18n.paid || 'Pago'}</span>`;
            } else if (isAtrasado) {
                statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">${i18n.overdue || 'Atrasado'}</span>`;
            } else {
                statusHtml = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">${i18n.pending || 'Pendente'}</span>`;
            }

            let acoes = '';
            if (isPago && temId) {
                acoes = `<button type="button" class="btn-icon text-amber-600 hover:text-amber-800 btn-estornar-parcela-contrato" data-id="${parcela.id}" title="${i18n.reversePayment || 'Estornar pagamento'}"><i class="fas fa-undo"></i></button>
                   <button type="button" class="btn-icon text-blue-600 hover:text-blue-800 btn-abrir-financeiro" data-id="${parcela.id}" title="${i18n.viewInFinancial || 'Ver no Financeiro'}"><i class="fas fa-external-link-alt"></i></button>`;
            } else if (temId) {
                acoes = `<button type="button" class="btn-icon text-emerald-600 hover:text-emerald-800 btn-marcar-pago-contrato" data-index="${index}" title="${i18n.markPaid || 'Marcar como paga'}"><i class="fas fa-check-circle"></i></button>
                   <button type="button" class="btn-icon text-blue-600 hover:text-blue-800 btn-editar-parcela-contrato" data-index="${index}" title="${i18n.editPayment || 'Editar pagamento'}"><i class="fas fa-edit"></i></button>
                   <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-parcela-contrato" data-id="${parcela.id}" title="${i18n.remove || 'Remover'}"><i class="fas fa-trash"></i></button>`;
            } else {
                acoes = `<button type="button" class="btn-icon text-blue-600 hover:text-blue-800 btn-editar-parcela-contrato" data-index="${index}" title="${i18n.editPayment || 'Editar pagamento'}"><i class="fas fa-edit"></i></button>
                   <button type="button" class="btn-icon text-red-600 hover:text-red-800 btn-remover-parcela-contrato" data-index="${index}" data-draft="1" title="${i18n.remove || 'Remover'}"><i class="fas fa-trash"></i></button>`;
            }

            const tr = document.createElement('tr');
            tr.className = 'border-b border-slate-100 hover:bg-slate-50';
            tr.innerHTML = `
                <td class="px-3 py-2 text-slate-500">${parcela.parcela || (index + 1)}</td>
                <td class="px-3 py-2">${escapeHtml(descricao)}</td>
                <td class="px-3 py-2">${escapeHtml(contaNome || '-')}</td>
                <td class="px-3 py-2">${escapeHtml(formaNome || '-')}</td>
                <td class="px-3 py-2 text-center">${formatarDataTabela(parcela.data_venci)}</td>
                <td class="px-3 py-2 text-right font-medium">${Currency.format(valor)}</td>
                <td class="px-3 py-2 text-center">${statusHtml}</td>
                <td class="px-3 py-2 text-center whitespace-nowrap">${acoes}</td>
            `;
            tbody.appendChild(tr);
        });

        // Atualizar total
        const elTotalParcelas = document.getElementById('totalParcelas');
        const elQtdParcelas = document.getElementById('qtdParcelas');
        if (elTotalParcelas) elTotalParcelas.textContent = Currency.format(total);
        if (elQtdParcelas) elQtdParcelas.textContent = parcelas.length;

        // Atualizar JSON hidden
        const parcelasJsonEl = document.getElementById('parcelasJson');
        if (parcelasJsonEl) parcelasJsonEl.value = JSON.stringify(parcelas);

        tbody.querySelectorAll('.btn-marcar-pago-contrato').forEach(btn => {
            btn.addEventListener('click', function () {
                abrirFormularioBaixaContrato(parseInt(this.dataset.index), this.closest('tr'));
            });
        });

        tbody.querySelectorAll('.btn-editar-parcela-contrato').forEach(btn => {
            btn.addEventListener('click', function () {
                abrirFormularioEdicaoContrato(parseInt(this.dataset.index), this.closest('tr'));
            });
        });

        tbody.querySelectorAll('.btn-remover-parcela-contrato').forEach(btn => {
            btn.addEventListener('click', function () {
                parcelaContratoAcaoPendente = this.dataset.draft === '1'
                    ? { draft: true, index: parseInt(this.dataset.index) }
                    : { id: this.dataset.id };
                confirmacaoPendente = 'removerParcelaContrato';
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.removeTitle || 'Remover parcela',
                    message: i18n.removeMessage || 'Deseja remover esta parcela?',
                    confirmText: i18n.remove || 'Remover'
                }, '*');
            });
        });

        tbody.querySelectorAll('.btn-estornar-parcela-contrato').forEach(btn => {
            btn.addEventListener('click', function () {
                parcelaContratoAcaoPendente = { id: this.dataset.id };
                confirmacaoPendente = 'estornarParcelaContrato';
                window.parent.postMessage({
                    action: 'openGenericConfirmModal',
                    title: i18n.reverseTitle || 'Estornar pagamento',
                    message: i18n.reverseMessage || 'Estornar o pagamento desta parcela?',
                    confirmText: i18n.reverseConfirm || 'Estornar'
                }, '*');
            });
        });

        tbody.querySelectorAll('.btn-abrir-financeiro').forEach(btn => {
            btn.addEventListener('click', function () {
                abrirLancamentoFinanceiro(this.dataset.id);
            });
        });
    }

    // Atualiza total das parcelas
    function atualizarTotalParcelas() {
        let total = 0;
        parcelas.forEach(p => {
            total += parseFloat(p.valor_subtotal || p.valor_total || 0);
        });
        const el = document.getElementById('totalParcelas');
        if (el) el.textContent = Currency.format(total);
    }

    function formatarDataTabela(data) {
        if (!data) return '-';
        const d = new Date(`${data}T00:00:00`);
        return Number.isNaN(d.getTime()) ? '-' : d.toLocaleDateString('pt-BR');
    }

    function obterTextoSelectSelecionado(id) {
        const select = document.getElementById(id);
        if (!select) return '';
        const option = select.options[select.selectedIndex];
        return option && option.value ? option.text.trim() : '';
    }

    function obterNomeListaPorId(lista, id) {
        if (!id) return '';
        const item = lista.find(registro => String(registro.id) === String(id));
        return item?.nome || '';
    }

    function obterNomeContaParcela(parcela) {
        return parcela.conta_nome
            || obterNomeListaPorId(contasBancariasList, parcela.id_conta)
            || (String(parcela.id_conta || '') === String(document.getElementById('id_conta')?.value || '') ? obterTextoSelectSelecionado('id_conta') : '');
    }

    function obterNomeFormaPagamentoParcela(parcela) {
        return parcela.forma_pagamento_nome
            || parcela.forma_pagamento
            || obterNomeListaPorId(formasPagamentoList, parcela.id_forma_pagamento)
            || (String(parcela.id_forma_pagamento || '') === String(document.getElementById('id_forma_pagamento')?.value || '') ? obterTextoSelectSelecionado('id_forma_pagamento') : '');
    }

    function popularSelectSimples(selectId, lista, selectedValue = '') {
        const select = document.getElementById(selectId);
        if (!select) return;

        select.innerHTML = `<option value="">${i18n.select || 'Selecione...'}</option>`;
        lista.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.nome;
            if (String(item.id) === String(selectedValue || '')) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    const origensFormulariosParcelaContrato = {};

    function registrarOrigemFormularioParcelaContrato(formId) {
        if (origensFormulariosParcelaContrato[formId]) return;

        const form = document.getElementById(formId);
        if (!form || !form.parentNode) return;

        origensFormulariosParcelaContrato[formId] = {
            parent: form.parentNode,
            nextSibling: form.nextSibling
        };
    }

    function restaurarFormularioParcelaContrato(formId) {
        registrarOrigemFormularioParcelaContrato(formId);

        const form = document.getElementById(formId);
        const origem = origensFormulariosParcelaContrato[formId];
        if (!form || !origem || !origem.parent) return;

        form.classList.add('hidden');
        origem.parent.insertBefore(form, origem.nextSibling || null);
    }

    function removerLinhaFormularioParcelaContrato() {
        document.getElementById('linhaFormularioParcelaContrato')?.remove();
    }

    function inserirFormularioAbaixoLinhaContrato(formId, linhaReferencia) {
        const form = document.getElementById(formId);
        if (!form || !linhaReferencia) return;

        esconderFormulariosPagamentoContrato();

        const linhaFormulario = document.createElement('tr');
        linhaFormulario.id = 'linhaFormularioParcelaContrato';
        linhaFormulario.className = 'bg-slate-50 border-b border-slate-100';

        const celula = document.createElement('td');
        celula.colSpan = linhaReferencia.children.length || 6;
        celula.className = 'px-3 py-3';

        linhaFormulario.appendChild(celula);
        linhaReferencia.insertAdjacentElement('afterend', linhaFormulario);
        celula.appendChild(form);
        form.classList.remove('hidden');
    }

    function esconderFormulariosPagamentoContrato() {
        restaurarFormularioParcelaContrato('formEditarParcelaContrato');
        restaurarFormularioParcelaContrato('formMarcarPagoContrato');
        removerLinhaFormularioParcelaContrato();
        document.getElementById('formAdicionarAvaria')?.classList.add('hidden');
    }

    function abrirFormularioEdicaoContrato(index, linhaReferencia = null) {
        const parcela = parcelas[index];
        if (!parcela || parcela.pago === 'S') return;

        esconderFormulariosPagamentoContrato();
        parcelaContratoEdicaoIndex = index;
        document.getElementById('editar_id_parcela_contrato').value = parcela.id || '';
        document.getElementById('editar_descricao_contrato').value = parcela.descricao || '';
        document.getElementById('editar_data_venci_contrato').value = parcela.data_venci || '';
        document.getElementById('editar_valor_contrato').value = Currency.format(parseFloat(parcela.valor_total || parcela.valor_subtotal || 0));
        popularSelectSimples('editar_id_conta_contrato', contasBancariasList, parcela.id_conta);
        popularSelectSimples('editar_id_forma_pagamento_contrato', formasPagamentoList, parcela.id_forma_pagamento);
        inserirFormularioAbaixoLinhaContrato('formEditarParcelaContrato', linhaReferencia);
        Currency.applyMaskToAll('input-moeda');
    }

    function abrirFormularioBaixaContrato(index, linhaReferencia = null) {
        const parcela = parcelas[index];
        if (!parcela || parcela.pago === 'S') return;

        esconderFormulariosPagamentoContrato();
        const descricao = parcela.descricao || (i18n.installmentLabel || 'Parcela :num').replace(':num', parcela.parcela || (index + 1));
        document.getElementById('pagar_id_parcela_contrato').value = parcela.id || '';
        document.getElementById('pagar_descricao_contrato').textContent = descricao;
        document.getElementById('pagar_data_pago_contrato').value = new Date().toISOString().slice(0, 10);
        popularSelectSimples('pagar_id_conta_contrato', contasBancariasList, parcela.id_conta);
        popularSelectSimples('pagar_id_forma_pagamento_contrato', formasPagamentoList, parcela.id_forma_pagamento);
        inserirFormularioAbaixoLinhaContrato('formMarcarPagoContrato', linhaReferencia);
    }

    async function confirmarEditarParcelaContrato() {
        const idParcela = document.getElementById('editar_id_parcela_contrato')?.value;

        const dataVenci = document.getElementById('editar_data_venci_contrato')?.value || '';
        const valor = document.getElementById('editar_valor_contrato')?.value || '';
        const idConta = document.getElementById('editar_id_conta_contrato')?.value || '';
        const idForma = document.getElementById('editar_id_forma_pagamento_contrato')?.value || '';
        const descricao = document.getElementById('editar_descricao_contrato')?.value || '';

        if (!dataVenci || Currency.parse(valor) <= 0) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.allRequiredFields || 'Preencha os campos obrigatórios' }, '*');
            return;
        }

        if (!editando || !registroId || !idParcela) {
            if (parcelaContratoEdicaoIndex === null || !parcelas[parcelaContratoEdicaoIndex]) return;

            const valorNumerico = Currency.parse(valor);
            parcelas[parcelaContratoEdicaoIndex] = {
                ...parcelas[parcelaContratoEdicaoIndex],
                descricao: descricao,
                data_venci: dataVenci,
                valor_subtotal: valorNumerico,
                valor_total: valorNumerico,
                id_conta: idConta ? parseInt(idConta) : null,
                id_forma_pagamento: idForma ? parseInt(idForma) : null,
                conta_nome: obterNomeListaPorId(contasBancariasList, idConta),
                forma_pagamento_nome: obterNomeListaPorId(formasPagamentoList, idForma)
            };
            parcelaContratoEdicaoIndex = null;
            esconderFormulariosPagamentoContrato();
            renderizarParcelas();
            return;
        }

        try {
            const result = await API.post(`/api/contratos/${registroId}/parcelas/${idParcela}/atualizar`, {
                data_venci: dataVenci,
                valor: valor,
                id_conta: idConta,
                id_forma_pagamento: idForma,
                descricao: descricao
            });

            if (result.success) {
                esconderFormulariosPagamentoContrato();
                await carregarParcelasContrato();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.installmentUpdateError || 'Erro ao atualizar parcela' }, '*');
            }
        } catch (e) {
            console.error('Erro ao atualizar parcela:', e);
            window.parent.postMessage({ action: 'openAlert', message: i18n.installmentUpdateError || 'Erro ao atualizar parcela' }, '*');
        }
    }

    async function confirmarMarcarPagoContrato() {
        const idParcela = document.getElementById('pagar_id_parcela_contrato')?.value;
        if (!editando || !registroId || !idParcela) return;

        const dataPago = document.getElementById('pagar_data_pago_contrato')?.value || '';
        const idConta = document.getElementById('pagar_id_conta_contrato')?.value || '';
        const idForma = document.getElementById('pagar_id_forma_pagamento_contrato')?.value || '';

        if (!dataPago || !idConta || !idForma) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.allRequiredFields || 'Preencha os campos obrigatórios' }, '*');
            return;
        }

        try {
            const result = await API.post(`/api/contratos/${registroId}/parcelas/${idParcela}/marcar-pago`, {
                data_pago: dataPago,
                id_conta: idConta,
                id_forma_pagamento: idForma
            });

            if (result.success) {
                esconderFormulariosPagamentoContrato();
                await carregarParcelasContrato();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.markPaidError || 'Erro ao marcar como paga' }, '*');
            }
        } catch (e) {
            console.error('Erro ao marcar como paga:', e);
            window.parent.postMessage({ action: 'openAlert', message: i18n.markPaidError || 'Erro ao marcar como paga' }, '*');
        }
    }

    async function executarRemoverParcelaContrato() {
        if (parcelaContratoAcaoPendente?.draft) {
            const index = parcelaContratoAcaoPendente.index;
            parcelaContratoAcaoPendente = null;
            if (Number.isInteger(index) && parcelas[index]) {
                parcelas.splice(index, 1);
                parcelas.forEach((parcela, i) => {
                    parcela.parcela = i + 1;
                    parcela.total_parcelas = parcelas.length;
                });
                renderizarParcelas();
            }
            return;
        }

        const idParcela = parcelaContratoAcaoPendente?.id;
        parcelaContratoAcaoPendente = null;
        if (!editando || !registroId || !idParcela) return;

        try {
            const result = await API.post(`/api/contratos/${registroId}/parcelas/${idParcela}/excluir`);
            if (result.success) {
                await carregarParcelasContrato();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.removeError || 'Erro ao remover parcela' }, '*');
            }
        } catch (e) {
            console.error('Erro ao remover parcela:', e);
            window.parent.postMessage({ action: 'openAlert', message: i18n.removeError || 'Erro ao remover parcela' }, '*');
        }
    }

    async function executarEstornarParcelaContrato() {
        const idParcela = parcelaContratoAcaoPendente?.id;
        parcelaContratoAcaoPendente = null;
        if (!editando || !registroId || !idParcela) return;

        try {
            const result = await API.post(`/api/contratos/${registroId}/parcelas/${idParcela}/estornar`);
            if (result.success) {
                await carregarParcelasContrato();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || i18n.reverseError || 'Erro ao estornar pagamento' }, '*');
            }
        } catch (e) {
            console.error('Erro ao estornar pagamento:', e);
            window.parent.postMessage({ action: 'openAlert', message: i18n.reverseError || 'Erro ao estornar pagamento' }, '*');
        }
    }

    // Limpa todas as parcelas
    function limparParcelas() {
        confirmacaoPendente = 'limparParcelas';
        window.parent.postMessage({
            action: 'openGenericConfirmModal',
            title: i18n.clearInstallmentsTitle || 'Limpar parcelas',
            message: i18n.clearAllConfirm || 'Deseja limpar todas as parcelas?',
            confirmText: i18n.clearInstallmentsConfirm || 'Limpar parcelas'
        }, '*');
    }

    function executarLimparParcelas() {
        parcelas = [];
        renderizarParcelas();
        const elSecao = document.getElementById('secaoParcelasGeradas');
        const elBtn = document.getElementById('btnLimparParcelas');
        if (elSecao) elSecao.classList.add('hidden');
        if (elBtn) elBtn.classList.add('hidden');
    }

    async function executarResolucaoDiferenca(acao = 'recalcular') {
        try {
            const result = await API.post(`/api/contratos/${registroId}/recalcular-parcelas`, { acao });
            if (result.success) {
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'showToast',
                        type: 'success',
                        message: result.message
                    }, '*');
                }
                await carregarParcelasContrato();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || (i18n.resolveError || 'Erro ao resolver diferenca') }, '*');
            }
        } catch (e) {
            console.error('Erro ao resolver diferenca:', e);
            window.parent.postMessage({ action: 'openAlert', message: i18n.resolveError || 'Erro ao resolver diferenca' }, '*');
        }
    }

    // Modal para resolver diferenca
    async function resolverDiferenca() {
        if (contratoTemAutorenovacaoAutomatica()) {
            window.parent.postMessage({
                action: 'openAlert',
                message: 'Contratos com autorrenovação acumulam faturas por ciclo. Esta diferença não deve ser recalculada por este botão.'
            }, '*');
            return;
        }

        confirmacaoPendente = 'resolverDiferenca';
        window.parent.postMessage({
            action: 'openGenericConfirmModal',
            title: 'Resolver diferença financeira',
            message: i18n.recalculateConfirm || 'Deseja recalcular as parcelas pendentes distribuindo a diferença entre elas?',
            confirmText: 'Recalcular parcelas'
        }, '*');
    }

    // Adiciona parcela avulsa
    async function adicionarParcelaAvulsa() {
        parcelaAvulsaRascunho = null;
        window.parent.postMessage({
            action: 'openInputModal',
            title: i18n.addInstallment || 'Adicionar parcela',
            label: i18n.promptDueDate || 'Data de vencimento (AAAA-MM-DD)',
            value: '',
            callbackAction: 'parcelaAvulsaDataInformada'
        }, '*');
    }

    async function executarAdicionarParcelaAvulsa(dataVenci, valorStr) {
        const valor = Currency.parse(valorStr);
        if (valor <= 0) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.invalidValue || 'Valor invalido' }, '*');
            return;
        }

        try {
            const result = await API.post(`/api/contratos/${registroId}/parcela-avulsa`, {
                data_venci: dataVenci,
                valor: valor,
                id_conta: document.getElementById('id_conta')?.value,
                id_forma_pagamento: document.getElementById('id_forma_pagamento')?.value
            });

            if (result.success) {
                if (window.parent !== window) {
                    window.parent.postMessage({
                        action: 'showToast',
                        type: 'success',
                        message: result.message
                    }, '*');
                }
                await carregarParcelasContrato();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || (i18n.addInstallmentError || 'Erro ao adicionar parcela') }, '*');
            }
        } catch (e) {
            console.error('Erro ao adicionar parcela:', e);
        }
    }

    function popularSelectAvariaContrato() {
        const selectConta = document.getElementById('avaria_id_conta');
        const selectForma = document.getElementById('avaria_id_forma_pagamento');

        if (selectConta && selectConta.options.length <= 1) {
            contasBancariasList.forEach(conta => {
                const opt = document.createElement('option');
                opt.value = conta.id;
                opt.textContent = conta.nome;
                if (String(conta.id) === String(document.getElementById('id_conta')?.value || '')) {
                    opt.selected = true;
                }
                selectConta.appendChild(opt);
            });
        }

        if (selectForma && selectForma.options.length <= 1) {
            formasPagamentoList.forEach(forma => {
                const opt = document.createElement('option');
                opt.value = forma.id;
                opt.textContent = forma.nome;
                if (String(forma.id) === String(document.getElementById('id_forma_pagamento')?.value || '')) {
                    opt.selected = true;
                }
                selectForma.appendChild(opt);
            });
        }
    }

    function alternarFormularioAvaria() {
        const formAvaria = document.getElementById('formAdicionarAvaria');
        const deveAbrir = formAvaria?.classList.contains('hidden');
        esconderFormulariosPagamentoContrato();
        popularSelectAvariaContrato();
        if (deveAbrir) {
            formAvaria?.classList.remove('hidden');
        }
    }

    async function confirmarAdicionarAvaria() {
        if (!editando || !registroId) return;

        const valor = document.getElementById('avaria_valor')?.value || '';
        const dataVenci = document.getElementById('avaria_data_venci')?.value || '';
        const idConta = document.getElementById('avaria_id_conta')?.value || '';
        const idFormaPagamento = document.getElementById('avaria_id_forma_pagamento')?.value || '';
        const descricao = document.getElementById('avaria_descricao')?.value || '';

        if (Currency.parse(valor) <= 0) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.invalidValue || 'Valor invalido' }, '*');
            return;
        }

        if (!dataVenci || !idConta || !idFormaPagamento) {
            window.parent.postMessage({ action: 'openAlert', message: i18n.allRequiredFields || 'Preencha os campos obrigatórios' }, '*');
            return;
        }

        try {
            const result = await API.post(`/api/contratos/${registroId}/parcela-avulsa`, {
                tipo_lancamento: 'avaria',
                data_venci: dataVenci,
                valor: valor,
                id_conta: idConta,
                id_forma_pagamento: idFormaPagamento,
                descricao: descricao
            });

            if (result.success) {
                window.parent.postMessage({
                    action: 'showToast',
                    type: 'success',
                    message: result.message
                }, '*');
                document.getElementById('formAdicionarAvaria')?.classList.add('hidden');
                ['avaria_valor', 'avaria_data_venci', 'avaria_descricao'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                await carregarParcelasContrato();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || (i18n.addInstallmentError || 'Erro ao adicionar parcela') }, '*');
            }
        } catch (e) {
            console.error('Erro ao adicionar avaria:', e);
            window.parent.postMessage({ action: 'openAlert', message: i18n.addInstallmentError || 'Erro ao adicionar parcela' }, '*');
        }
    }

    // Event listeners para aba financeiro
    function configurarEventosFinanceiro() {
        // Comando de parcelas: disponivel em ambos os modos
        document.getElementById('id_comando_parcela')?.addEventListener('change', mostrarInfoComando);
        document.getElementById('btnAdicionarAvaria')?.addEventListener('click', alternarFormularioAvaria);
        document.getElementById('btnConfirmarAdicionarAvaria')?.addEventListener('click', confirmarAdicionarAvaria);
        document.getElementById('btnCancelarAdicionarAvaria')?.addEventListener('click', () => {
            document.getElementById('formAdicionarAvaria')?.classList.add('hidden');
        });
        document.getElementById('btnConfirmarEditarParcelaContrato')?.addEventListener('click', confirmarEditarParcelaContrato);
        document.getElementById('btnCancelarEditarParcelaContrato')?.addEventListener('click', () => {
            esconderFormulariosPagamentoContrato();
        });
        document.getElementById('btnConfirmarMarcarPagoContrato')?.addEventListener('click', confirmarMarcarPagoContrato);
        document.getElementById('btnCancelarMarcarPagoContrato')?.addEventListener('click', () => {
            esconderFormulariosPagamentoContrato();
        });

        if (editando) {
            // Modo edicao: eventos de parcelas existentes
            document.getElementById('btnAdicionarParcelaAvulsa')?.addEventListener('click', adicionarParcelaAvulsa);
            document.getElementById('btnResolverDiferenca')?.addEventListener('click', resolverDiferenca);
            document.getElementById('btnRegenerarPendentes')?.addEventListener('click', regenerarPendentes);
        } else {
            // Modo criacao: configuracao de pagamento + gerar parcelas
            document.getElementById('btnGerarParcelas')?.addEventListener('click', async function () {
                await gerarPreviewParcelas();
            });

            document.getElementById('btnLimparParcelas')?.addEventListener('click', limparParcelas);
            document.getElementById('btnAdicionarParcelaAvulsa')?.addEventListener('click', adicionarParcelaAvulsa);

            // Definir data padrao do primeiro vencimento
            const primeiroVencimento = document.getElementById('primeiro_vencimento');
            if (primeiroVencimento && !primeiroVencimento.value) {
                const hoje = new Date();
                primeiroVencimento.value = hoje.toISOString().split('T')[0];
            }
        }
    }

    /**
     * Toggle colapsavel da secao de configuracao de pagamento (modo edicao)
     */
    function configurarToggleConfigPagamento() {
        const toggle = document.getElementById('toggleConfigPagamento');
        const conteudo = document.getElementById('conteudoConfigPagamento');
        const icon = document.getElementById('iconConfigPagamento');

        if (!toggle || !conteudo) return;

        toggle.addEventListener('click', function () {
            const isHidden = conteudo.classList.contains('hidden');
            conteudo.classList.toggle('hidden');
            if (icon) {
                icon.classList.toggle('fa-chevron-down', !isHidden);
                icon.classList.toggle('fa-chevron-up', isHidden);
            }
        });

        // Preencher dados read-only da config original
        const contrato = window.contratoData;
        if (contrato) {
            const configConta = document.getElementById('configContaDisplay');
            if (configConta) configConta.value = contrato.conta_descricao || '-';

            const primeiroVenc = document.getElementById('primeiro_vencimento');
            if (primeiroVenc && contrato.primeiro_vencimento) primeiroVenc.value = contrato.primeiro_vencimento;

            const valorDesconto = document.getElementById('valor_desconto');
            if (valorDesconto && contrato.valor_desconto !== undefined) {
                valorDesconto.value = Currency.format(contrato.valor_desconto || 0);
            }
        }
    }

    /**
     * Regenera parcelas pendentes redistribuindo o saldo restante
     */
    async function regenerarPendentes() {
        if (!registroId) return;

        const pendentes = parcelas.filter(p => p.status !== 'pago');
        if (pendentes.length === 0) {
            window.parent.postMessage({ action: 'openAlert', message: 'Nao ha parcelas pendentes para regenerar.' }, '*');
            return;
        }

        confirmacaoPendente = 'regenerarPendentes';
        window.parent.postMessage({
            action: 'openGenericConfirmModal',
            title: i18n.regenerateInstallmentsTitle || 'Regenerar parcelas',
            message: i18n.recalculateConfirm || 'Deseja redistribuir o saldo entre as parcelas pendentes?',
            confirmText: i18n.regenerateInstallmentsConfirm || 'Regenerar parcelas'
        }, '*');
    }

    async function executarRegenerarPendentes() {
        try {
            const result = await API.post(`/api/contratos/${registroId}/recalcular-parcelas`);
            if (result.success) {
                parcelas = result.data.parcelas || [];
                parcelasOriginais = JSON.parse(JSON.stringify(parcelas));
                renderizarParcelas();
                atualizarResumoFinanceiro();
            } else {
                window.parent.postMessage({ action: 'openAlert', message: result.message || 'Erro ao regenerar parcelas' }, '*');
            }
        } catch (e) {
            console.error('Erro ao regenerar parcelas:', e);
            window.parent.postMessage({ action: 'openAlert', message: 'Erro ao regenerar parcelas' }, '*');
        }
    }

    // ===== HELPERS =====

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Inicializar
    init();
})();
