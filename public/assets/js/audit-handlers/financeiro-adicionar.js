/**
 * Handler de Auditoria Especializado para Financeiro
 *
 * Trata as particularidades da tela de lançamento financeiro:
 * - Itens dinâmicos (itens[INDEX][campo])
 * - Parcelas geradas (parcelas[INDEX][campo])
 * - Config de parcelas (ignorado se não houver parcelas)
 * - Labels customizados para campos de vínculo
 */
(function() {
    'use strict';

    const FinanceiroAuditHandler = {
        config: {
            // Labels customizados para campos específicos
            customLabels: {
                'tipo': 'Tipo',
                'id_conta': 'Conta Bancária',
                'id_forma_pagamento': 'Forma de Pagamento',
                'id_plano_de_conta': 'Plano de Contas',
                'descricao': 'Descrição',
                'documento': 'Documento',
                'data_criada': 'Data Criação',
                'data_venci': 'Data Vencimento',
                'pago': 'Lançamento Pago',
                'data_pago': 'Data do Pagamento',
                'id_matriz_filial': 'Matriz/Filial',
                'id_cliente': 'Cliente',
                'id_fornecedor': 'Fornecedor',
                'id_funcionario': 'Funcionário',
                'valor_subtotal': 'Subtotal',
                'juros': 'Juros',
                'multa': 'Multa',
                'desconto': 'Desconto',
                'valor_total': 'Valor Total'
            },

            // Campos a serem ignorados
            ignoredFields: ['id', 'chave', '_token', 'parcela'],

            // Mapeamento de valores para exibição
            valueLabels: {
                'tipo': { 'D': 'Despesa (Pagar)', 'R': 'Receita (Receber)' },
                'pago': { 'S': 'Sim', 'N': 'Não' }
            }
        },

        /**
         * Captura estado atual do formulário
         */
        capture(form) {
            const data = {};
            const helpers = FormAudit.helpers;

            // Verificar se há parcelas reais
            const temParcelas = form.querySelectorAll('[name^="parcelas["]').length > 0;

            // Aba Dados Principais
            const dadosPrincipais = [];

            // Campos simples da aba principal
            const camposPrincipais = [
                'tipo', 'id_conta', 'id_forma_pagamento', 'id_plano_de_conta',
                'descricao', 'documento', 'data_criada', 'data_venci',
                'pago', 'data_pago',
                'id_matriz_filial', 'id_cliente', 'id_fornecedor', 'id_funcionario',
                'valor_subtotal', 'juros', 'multa', 'desconto', 'valor_total'
            ];

            camposPrincipais.forEach(nome => {
                const campo = form.querySelector(`[name="${nome}"]`);
                if (!campo) return;

                let valor = this._getFieldValue(campo);
                if (valor === '' || valor === null) return;

                // Transformar valor se necessário
                if (this.config.valueLabels[nome] && this.config.valueLabels[nome][valor]) {
                    valor = this.config.valueLabels[nome][valor];
                }

                dadosPrincipais.push({
                    label: this.config.customLabels[nome] || helpers.formatFieldName(nome),
                    de: null,
                    para: valor
                });
            });

            // Capturar itens
            const itens = this._captureItens(form);
            if (itens.length > 0) {
                dadosPrincipais.push({
                    label: 'Itens do Lançamento',
                    de: null,
                    para: itens
                });
            }

            if (dadosPrincipais.length > 0) {
                data['Dados Principais'] = dadosPrincipais;
            }

            // Aba Parcelamento (somente se houver parcelas)
            if (temParcelas) {
                const parcelas = this._captureParcelas(form);
                if (parcelas.length > 0) {
                    data['Parcelamento'] = [{
                        label: 'Parcelas',
                        de: null,
                        para: parcelas
                    }];
                }
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
         * Obtém valor formatado do campo
         */
        _getFieldValue(field) {
            // SELECT: capturar texto visível
            if (field.tagName === 'SELECT') {
                const selected = field.options[field.selectedIndex];
                if (!selected || !selected.value) return '';
                return selected.text.trim();
            }

            // CHECKBOX
            if (field.type === 'checkbox') {
                return field.checked ? 'Sim' : 'Não';
            }

            // INPUT-MOEDA: formatar com símbolo de moeda
            if (field.classList.contains('input-moeda')) {
                if (!field.value || field.value === '0,00' || field.value === '0.00') return '';
                if (window.Currency) {
                    const valor = Currency.parse(field.value);
                    return Currency.format(valor, true);
                }
                return field.value;
            }

            // INPUT DATE: formatar dd/mm/yyyy
            if (field.type === 'date' && field.value) {
                const parts = field.value.split('-');
                if (parts.length === 3) {
                    return `${parts[2]}/${parts[1]}/${parts[0]}`;
                }
                return field.value;
            }

            // TEXTAREA e outros
            return field.value || '';
        },

        /**
         * Captura array de itens
         */
        _captureItens(form) {
            const itens = [];
            const indices = new Set();

            // Encontrar todos os índices de itens
            form.querySelectorAll('[name^="itens["]').forEach(field => {
                const match = field.name.match(/^itens\[(\d+)\]/);
                if (match) indices.add(match[1]);
            });

            // Para cada índice, capturar os campos
            indices.forEach(index => {
                const item = {};
                let temValor = false;

                // Descrição
                const descricao = form.querySelector(`[name="itens[${index}][descricao]"]`);
                if (descricao && descricao.value) {
                    item['Descrição'] = descricao.value;
                    temValor = true;
                }

                // Veículo
                const veiculo = form.querySelector(`[name="itens[${index}][id_veiculo]"]`);
                if (veiculo && veiculo.value) {
                    const selected = veiculo.options[veiculo.selectedIndex];
                    if (selected && selected.text) {
                        item['Veículo'] = selected.text.trim();
                        temValor = true;
                    }
                }

                // Plano de Contas
                const plano = form.querySelector(`[name="itens[${index}][id_plano_de_conta]"]`);
                if (plano && plano.value) {
                    const selected = plano.options[plano.selectedIndex];
                    if (selected && selected.text) {
                        item['Plano de Contas'] = selected.text.trim();
                        temValor = true;
                    }
                }

                // Valor
                const valor = form.querySelector(`[name="itens[${index}][valor]"]`);
                if (valor && valor.value && valor.value !== '0,00') {
                    if (window.Currency) {
                        item['Valor'] = Currency.format(Currency.parse(valor.value), true);
                    } else {
                        item['Valor'] = valor.value;
                    }
                    temValor = true;
                }

                if (temValor) {
                    itens.push(item);
                }
            });

            return itens;
        },

        /**
         * Captura array de parcelas
         */
        _captureParcelas(form) {
            const parcelas = [];
            const indices = new Set();

            // Encontrar todos os índices de parcelas
            form.querySelectorAll('[name^="parcelas["]').forEach(field => {
                const match = field.name.match(/^parcelas\[(\d+)\]/);
                if (match) indices.add(match[1]);
            });

            // Para cada índice, capturar os campos
            Array.from(indices).sort((a, b) => parseInt(a) - parseInt(b)).forEach(index => {
                const parcela = {};
                let temValor = false;

                // Parcela (ex: 1/3)
                const parcelaNum = form.querySelector(`[name="parcelas[${index}][parcela]"]`);
                if (parcelaNum && parcelaNum.value) {
                    parcela['Parcela'] = parcelaNum.value;
                    temValor = true;
                }

                // Vencimento
                const vencimento = form.querySelector(`[name="parcelas[${index}][data_venci]"]`);
                if (vencimento && vencimento.value) {
                    const parts = vencimento.value.split('-');
                    if (parts.length === 3) {
                        parcela['Vencimento'] = `${parts[2]}/${parts[1]}/${parts[0]}`;
                    } else {
                        parcela['Vencimento'] = vencimento.value;
                    }
                    temValor = true;
                }

                // Valor
                const valor = form.querySelector(`[name="parcelas[${index}][valor]"]`);
                if (valor && valor.value) {
                    if (window.Currency) {
                        parcela['Valor'] = Currency.format(parseFloat(valor.value), true);
                    } else {
                        parcela['Valor'] = valor.value;
                    }
                    temValor = true;
                }

                if (temValor) {
                    parcelas.push(parcela);
                }
            });

            return parcelas;
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
        FormAudit.registerHandler('financeiro-adicionar', FinanceiroAuditHandler);
    }
})();
