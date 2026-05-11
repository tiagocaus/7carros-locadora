/**
 * Handler de Auditoria Especializado para Manutenções
 *
 * Trata as particularidades da tela de manutenções:
 * - Transições de status (C → A/F, A → F, F → A)
 * - Auto-preenchimento ao mudar para status "Aberta"
 * - Itens dinâmicos com estados (novo, editando, pago)
 * - Campos de tanque e odômetro
 */
(function() {
    'use strict';

    const ManutencoesAuditHandler = {
        config: {
            // Campos a serem ignorados
            ignoredFields: ['id', 'chave', '_token', '_veiculo_odometro', '_veiculo_tanque']
        },

        _i18n(path, fallback = '') {
            const source = window.manutencoesAuditI18n || {};
            return path.split('.').reduce((value, part) => value && value[part], source) || fallback || path;
        },

        _fieldLabel(nome) {
            const labels = {
                os: 'fields.os',
                status: 'fields.status',
                id_matriz_filial: 'fields.branch',
                id_veiculo: 'fields.vehicle',
                id_oficina: 'fields.workshop',
                data_enviado: 'fields.send_date',
                odo_enviado: 'fields.send_odometer',
                tanque_enviado: 'fields.send_tank',
                motivo: 'fields.send_reason',
                data_retorno: 'fields.return_date',
                odo_retorno: 'fields.return_odometer',
                tanque_retorno: 'fields.return_tank',
                obs_oficina: 'fields.workshop_notes',
                trocou_oleo: 'fields.changed_oil',
                trocou_pneus: 'fields.changed_tires'
            };
            return labels[nome] ? this._i18n(labels[nome], nome) : nome;
        },

        _valueLabel(nome, value) {
            if (nome === 'status') {
                const labels = {
                    C: this._i18n('status.created', value),
                    A: this._i18n('status.open', value),
                    F: this._i18n('status.closed', value)
                };
                return labels[value] || value;
            }

            if (nome === 'tanque_enviado' || nome === 'tanque_retorno') {
                const labels = {
                    '': '-',
                    0: this._i18n('tank.reserve', value),
                    1: '1/8',
                    2: '1/4',
                    3: '3/8',
                    4: '1/2',
                    5: '5/8',
                    6: '3/4',
                    7: '7/8',
                    8: this._i18n('tank.full', value)
                };
                return labels[value] || value;
            }

            return value;
        },

        /**
         * Captura estado atual do formulário
         */
        capture(form) {
            const data = {};

            // Aba Dados
            const dadosAba = [];

            // Dados da Manutenção
            const camposManutencao = ['os', 'status', 'id_matriz_filial', 'id_veiculo', 'id_oficina'];
            camposManutencao.forEach(nome => {
                const valor = this._getCampoValor(form, nome);
                if (valor) {
                    dadosAba.push({
                        label: this._fieldLabel(nome),
                        de: null,
                        para: valor
                    });
                }
            });

            // Envio para oficina
            const camposEnvio = ['data_enviado', 'odo_enviado', 'tanque_enviado', 'motivo'];
            camposEnvio.forEach(nome => {
                const valor = this._getCampoValor(form, nome);
                if (valor) {
                    dadosAba.push({
                        label: this._fieldLabel(nome),
                        de: null,
                        para: valor
                    });
                }
            });

            // Retorno da oficina
            const camposRetorno = ['data_retorno', 'odo_retorno', 'tanque_retorno', 'obs_oficina'];
            camposRetorno.forEach(nome => {
                const valor = this._getCampoValor(form, nome);
                if (valor) {
                    dadosAba.push({
                        label: this._fieldLabel(nome),
                        de: null,
                        para: valor
                    });
                }
            });

            // Serviços realizados
            const camposServicos = ['trocou_oleo', 'trocou_pneus'];
            camposServicos.forEach(nome => {
                const campo = form.querySelector(`[name="${nome}"]`);
                if (campo && campo.type === 'checkbox') {
                    const valor = campo.checked ? this._i18n('common.yes') : this._i18n('common.no');
                    // Só incluir se marcado como Sim
                    if (campo.checked) {
                        dadosAba.push({
                            label: this._fieldLabel(nome),
                            de: null,
                            para: valor
                        });
                    }
                }
            });

            if (dadosAba.length > 0) {
                data[this._i18n('tabs.data')] = dadosAba;
            }

            // Aba Itens
            const itens = this._captureItens();
            if (itens.length > 0) {
                data[this._i18n('tabs.items')] = [{
                    label: this._i18n('sections.maintenance_items'),
                    de: null,
                    para: itens
                }];
            }

            return data;
        },

        /**
         * Captura estado inicial do formulário
         */
        captureInitial(form) {
            return this.capture(form);
        },

        /**
         * Detecta alterações entre estado inicial e atual
         */
        getChanges(form, initialData) {
            const current = this.capture(form);
            const changes = {};

            // Obter todas as abas (inicial + atual)
            const allTabs = new Set([...Object.keys(initialData), ...Object.keys(current)]);

            allTabs.forEach(tabName => {
                const initialFields = initialData[tabName] || [];
                const currentFields = current[tabName] || [];
                const tabChanges = [];

                // Verificar campos alterados ou novos
                currentFields.forEach(field => {
                    const original = initialFields.find(f => f.label === field.label);
                    const valorAntigo = original ? original.para : null;
                    const valorNovo = field.para;

                    if (!this._isEqual(valorAntigo, valorNovo)) {
                        tabChanges.push({
                            label: field.label,
                            de: valorAntigo,
                            para: valorNovo
                        });
                    }
                });

                // Verificar campos removidos
                initialFields.forEach(field => {
                    const exists = currentFields.find(f => f.label === field.label);
                    if (!exists && field.para) {
                        tabChanges.push({
                            label: field.label,
                            de: field.para,
                            para: null
                        });
                    }
                });

                if (tabChanges.length > 0) {
                    changes[tabName] = tabChanges;
                }
            });

            return changes;
        },

        /**
         * Obtém valor formatado de um campo
         */
        _getCampoValor(form, nome) {
            const campo = form.querySelector(`[name="${nome}"]`);
            if (!campo) return null;

            let valor = '';

            // SELECT: capturar texto visível
            if (campo.tagName === 'SELECT') {
                const selected = campo.options[campo.selectedIndex];
                if (!selected || !selected.value) return null;
                valor = selected.text.trim();

                // Verificar se há mapeamento de valor
                valor = this._valueLabel(nome, campo.value);
            }
            // INPUT DATETIME-LOCAL
            else if (campo.type === 'datetime-local' && campo.value) {
                const [date, time] = campo.value.split('T');
                valor = `${DateHelper.format(date)} ${time}`;
            }
            // INPUT com classe input-km (quilometragem)
            else if (campo.classList.contains('input-km') && campo.value) {
                valor = campo.value + ' km';
            }
            // TEXTAREA e outros
            else {
                valor = campo.value || '';
            }

            return valor || null;
        },

        /**
         * Captura itens da manutenção
         * Os itens são mantidos em uma variável global 'itensData' pelo script da página
         */
        _captureItens() {
            const itens = [];

            // Tentar acessar variável global itensData
            if (typeof window.itensData !== 'undefined' && Array.isArray(window.itensData)) {
                window.itensData.forEach(item => {
                    if (!item) return;

                    const itemFormatado = {};
                    let temValor = false;

                    // Descrição
                    if (item.descricao || item.estoque_nome) {
                        itemFormatado[this._i18n('fields.description')] = item.descricao || item.estoque_nome;
                        temValor = true;
                    }

                    // Quantidade com unidade
                    if (item.quantidade) {
                        const qtd = parseFloat(item.quantidade) || 0;
                        const unidade = item.estoque_unidade || 'UN';
                        itemFormatado[this._i18n('fields.qty')] = `${qtd.toFixed(3).replace('.', ',')} ${unidade}`;
                        temValor = true;
                    }

                    // Valor Unitário
                    if (item.valor_unitario) {
                        if (window.Currency) {
                            itemFormatado[this._i18n('fields.unit_value')] = Currency.format(item.valor_unitario, true);
                        } else {
                            itemFormatado[this._i18n('fields.unit_value')] = parseFloat(item.valor_unitario).toFixed(2).replace('.', ',');
                        }
                        temValor = true;
                    }

                    // Valor Total
                    if (item.valor_total) {
                        if (window.Currency) {
                            itemFormatado[this._i18n('fields.total_value')] = Currency.format(item.valor_total, true);
                        } else {
                            itemFormatado[this._i18n('fields.total_value')] = parseFloat(item.valor_total).toFixed(2).replace('.', ',');
                        }
                        temValor = true;
                    }

                    // Status
                    itemFormatado[this._i18n('fields.status')] = item.pago === 'S' ? this._i18n('badges.paid') : this._i18n('badges.pending');

                    if (temValor) {
                        itens.push(itemFormatado);
                    }
                });
            }

            // Se não encontrou variável global, tentar extrair do DOM
            if (itens.length === 0) {
                const tbody = document.getElementById('itensTableBody');
                if (tbody) {
                    tbody.querySelectorAll('tr[data-index]').forEach(tr => {
                        const cells = tr.querySelectorAll('td');
                        if (cells.length >= 5) {
                            itens.push({
                                [this._i18n('fields.description')]: cells[0].textContent.trim(),
                                [this._i18n('fields.qty')]: cells[1].textContent.trim(),
                                [this._i18n('fields.unit_value')]: cells[2].textContent.trim(),
                                [this._i18n('fields.total_value')]: cells[3].textContent.trim(),
                                [this._i18n('fields.status')]: cells[4].textContent.trim()
                            });
                        }
                    });
                }
            }

            return itens;
        },

        /**
         * Compara dois valores para igualdade
         */
        _isEqual(a, b) {
            if (a === b) return true;
            if (a === null && b === null) return true;
            if (a === null || b === null) return false;

            // Arrays
            if (Array.isArray(a) && Array.isArray(b)) {
                return JSON.stringify(a) === JSON.stringify(b);
            }

            return String(a) === String(b);
        }
    };

    // Registrar handler
    if (window.FormAudit && FormAudit.registerHandler) {
        FormAudit.registerHandler('manutencoes-adicionar', ManutencoesAuditHandler);
    }
})();
