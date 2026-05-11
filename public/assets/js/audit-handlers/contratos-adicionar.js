/**
 * Handler de Auditoria Especializado para Contratos
 *
 * Trata as particularidades da tela de contratos:
 * - Veiculos dinamicos (veiculos[INDEX][campo])
 * - Condutores adicionais (condutor[INDEX][campo])
 * - Fiadores, Avalistas, Testemunhas
 * - Taxas e servicos (taxas[INDEX][campo])
 * - Labels customizados para todos os campos
 */
(function () {
    'use strict';

    const ContratosAuditHandler = {
        config: {
            // Labels customizados para campos especificos
            customLabels: {
                // Cabecalho
                'id_matriz_filial_retirada': 'Matriz/Filial',
                'data_ini': 'Data Inicio',
                'data_fim': 'Data Fim',
                'contagem': 'Contagem',
                'dias': 'Periodo',
                'auto_renovacao': 'Autorenovacao',
                'data_renovacao': 'Data Renovacao',

                // Cliente
                'id_cliente': 'Cliente',

                // Veiculo
                'id_grupo': 'Grupo',
                'id_veiculo': 'Veiculo',
                'plano': 'Plano',
                'valor_plano_km_pago': 'Valor Km Pago',
                'valor_plano_km_livre': 'Valor Km Livre',
                'valor_plano_km_controlado': 'Valor Km Controlado',
                'km_franquia': 'Km Franquia',
                'valor_km_excedente': 'Valor Km Excedente',
                'valor_condutor_adicional': 'Valor Condutor Adicional',
                'seguro_carro': 'Seguro Veiculo',
                'valor_seguro_carro': 'Valor Seguro Veiculo',
                'cobertura_carro': 'Cobertura Veiculo',
                'seguro_terceiros': 'Seguro Terceiros',
                'valor_seguro_terceiros': 'Valor Seguro Terceiros',
                'cobertura_terceiros': 'Cobertura Terceiros',
                'odometro_saida': 'Km Saída',
                'combustivel_saida': 'Combustivel Saída',

                // Condutor
                'nome': 'Nome',
                'cpf': 'CPF',
                'cnh': 'CNH',
                'cnh_validade': 'Validade CNH',

                // Financeiro
                'id_conta': 'Conta Bancaria',
                'id_forma_pagamento': 'Forma de Pagamento',
                'valor_desconto': 'Desconto',
                'primeiro_pagamento': 'Primeiro Pagamento',
                'total_fatura': 'Total Fatura',
                'total_pagar': 'Total a Pagar',

                // Taxas
                'id_taxa': 'Taxa/Servico',
                'quantidade': 'Quantidade',
                'valor_unitario': 'Valor Unitario',
                'valor_total': 'Valor Total',

                // Observacoes
                'obs': 'Observacoes'
            },

            // Campos a serem ignorados
            ignoredFields: ['id', 'chave', '_token', 'csrf_token'],

            // Mapeamento de valores para exibicao
            valueLabels: {
                'contagem': {
                    'dia': 'Dia',
                    'semana': 'Semana',
                    'mes': 'Mes',
                    'ano': 'Ano'
                },
                'auto_renovacao': {
                    '': 'Desativada',
                    'auto': 'Automatica',
                    'fim': 'Encerrada',
                    '1x': '1 Renovacao',
                    '2x': '2 Renovacoes',
                    '3x': '3 Renovacoes'
                },
                'plano': {
                    'KMC': 'Km Controlado',
                    'KL': 'Km Livre',
                    'KC': 'Km Cobrado'
                },
                'seguro_carro': {
                    '1': 'Sim',
                    '0': 'Nao',
                    'true': 'Sim',
                    'false': 'Nao'
                },
                'seguro_terceiros': {
                    '1': 'Sim',
                    '0': 'Nao',
                    'true': 'Sim',
                    'false': 'Nao'
                }
            }
        },

        /**
         * Captura estado atual do formulario
         */
        capture(form) {
            const data = {};

            // Dados Gerais (cabecalho)
            const dadosGerais = this._captureDadosGerais(form);
            if (dadosGerais.length > 0) {
                data['Dados Gerais'] = dadosGerais;
            }

            // Cliente
            const cliente = this._captureCliente(form);
            if (cliente.length > 0) {
                data['Cliente'] = cliente;
            }

            // Veiculos
            const veiculos = this._captureVeiculos(form);
            if (veiculos.length > 0) {
                data['Veiculos'] = [{
                    label: 'Veiculos do Contrato',
                    de: null,
                    para: veiculos
                }];
            }

            // Condutor Adicional
            const condutores = this._captureCondutores(form);
            if (condutores.length > 0) {
                data['Condutor Adicional'] = [{
                    label: 'Condutores',
                    de: null,
                    para: condutores
                }];
            }

            // Fiadores
            const fiadores = this._capturePessoas(form, 'fiador');
            if (fiadores.length > 0) {
                data['Fiadores'] = [{
                    label: 'Fiadores',
                    de: null,
                    para: fiadores
                }];
            }

            // Avalistas
            const avalistas = this._capturePessoas(form, 'avalista');
            if (avalistas.length > 0) {
                data['Avalistas'] = [{
                    label: 'Avalistas',
                    de: null,
                    para: avalistas
                }];
            }

            // Testemunhas
            const testemunhas = this._capturePessoas(form, 'testemunha');
            if (testemunhas.length > 0) {
                data['Testemunhas'] = [{
                    label: 'Testemunhas',
                    de: null,
                    para: testemunhas
                }];
            }

            // Financeiro
            const financeiro = this._captureFinanceiro(form);
            if (financeiro.length > 0) {
                data['Financeiro'] = financeiro;
            }

            // Taxas e Servicos
            const taxas = this._captureTaxas(form);
            if (taxas.length > 0) {
                data['Taxas e Servicos'] = [{
                    label: 'Taxas',
                    de: null,
                    para: taxas
                }];
            }

            // Observacoes
            const obs = this._captureObservacoes(form);
            if (obs.length > 0) {
                data['Observacoes'] = obs;
            }

            return data;
        },

        /**
         * Captura estado inicial do formulario
         */
        captureInitial(form) {
            return this.capture(form);
        },

        /**
         * Detecta alteracoes entre estado inicial e atual
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
         * Captura dados gerais (cabecalho)
         */
        _captureDadosGerais(form) {
            const campos = [];
            const camposGerais = [
                'id_matriz_filial_retirada', 'data_ini', 'data_fim',
                'contagem', 'dias', 'auto_renovacao', 'data_renovacao'
            ];

            camposGerais.forEach(nome => {
                const campo = form.querySelector(`[name="${nome}"]`);
                if (!campo) return;

                let valor = this._getFieldValue(campo);
                if (valor === '' || valor === null) return;

                // Transformar valor se necessario
                if (this.config.valueLabels[nome] && this.config.valueLabels[nome][valor]) {
                    valor = this.config.valueLabels[nome][valor];
                }

                // Adicionar sufixo dinamico para periodo
                if (nome === 'dias') {
                    const contagem = form.querySelector('[name="contagem"]');
                    if (contagem) {
                        const unidade = this.config.valueLabels['contagem'][contagem.value] || contagem.value;
                        valor = valor + ' ' + unidade.toLowerCase() + (parseInt(valor) > 1 ? 's' : '');
                    }
                }

                campos.push({
                    label: this.config.customLabels[nome] || nome,
                    de: null,
                    para: valor
                });
            });

            return campos;
        },

        /**
         * Captura dados do cliente
         */
        _captureCliente(form) {
            const campos = [];
            const campo = form.querySelector('[name="id_cliente"]');
            if (!campo) return campos;

            const valor = this._getFieldValue(campo);
            if (valor) {
                campos.push({
                    label: 'Cliente',
                    de: null,
                    para: valor
                });
            }

            return campos;
        },

        /**
         * Captura array de veiculos
         */
        _captureVeiculos(form) {
            const veiculos = [];
            const cards = form.querySelectorAll('.veiculo-card, [data-veiculo-index]');

            cards.forEach((card, index) => {
                const veiculo = {};
                let temValor = false;

                // Veiculo (placa/modelo)
                const idVeiculo = card.querySelector('[name*="[id_veiculo]"]');
                if (idVeiculo && idVeiculo.value) {
                    veiculo['Veiculo'] = this._getFieldValue(idVeiculo);
                    temValor = true;
                }

                // Plano
                const plano = card.querySelector('[name*="[plano]"]');
                if (plano && plano.value) {
                    const planoValor = plano.value;
                    veiculo['Plano'] = this.config.valueLabels['plano'][planoValor] || planoValor;
                    temValor = true;
                }

                // Valor do plano selecionado
                const valorKmPago = card.querySelector('[name*="[valor_plano_km_pago]"]');
                const valorKmLivre = card.querySelector('[name*="[valor_plano_km_livre]"]');
                const valorKmControlado = card.querySelector('[name*="[valor_plano_km_controlado]"]');

                if (valorKmPago && valorKmPago.value && valorKmPago.value !== '0,00') {
                    veiculo['Valor'] = this._formatMoney(valorKmPago.value);
                    temValor = true;
                } else if (valorKmLivre && valorKmLivre.value && valorKmLivre.value !== '0,00') {
                    veiculo['Valor'] = this._formatMoney(valorKmLivre.value);
                    temValor = true;
                } else if (valorKmControlado && valorKmControlado.value && valorKmControlado.value !== '0,00') {
                    veiculo['Valor'] = this._formatMoney(valorKmControlado.value);
                    temValor = true;
                }

                // Km saida
                const odometro = card.querySelector('[name*="[odometro_saida]"]');
                if (odometro && odometro.value) {
                    veiculo['Km Saída'] = odometro.value + ' km';
                    temValor = true;
                }

                if (temValor) {
                    veiculos.push(veiculo);
                }
            });

            return veiculos;
        },

        /**
         * Captura array de condutores adicionais
         */
        _captureCondutores(form) {
            const condutores = [];
            const cards = form.querySelectorAll('.condutor-card, [data-condutor-index]');

            cards.forEach((card, index) => {
                const condutor = {};
                let temValor = false;

                const nome = card.querySelector('[name*="[nome]"]');
                if (nome && nome.value) {
                    condutor['Nome'] = nome.value;
                    temValor = true;
                }

                const cpf = card.querySelector('[name*="[cpf]"]');
                if (cpf && cpf.value) {
                    condutor['CPF'] = cpf.value;
                    temValor = true;
                }

                const cnh = card.querySelector('[name*="[cnh]"]');
                if (cnh && cnh.value) {
                    condutor['CNH'] = cnh.value;
                    temValor = true;
                }

                if (temValor) {
                    condutores.push(condutor);
                }
            });

            return condutores;
        },

        /**
         * Captura array de pessoas (fiadores, avalistas, testemunhas)
         */
        _capturePessoas(form, tipo) {
            const pessoas = [];
            const cards = form.querySelectorAll(`.${tipo}-card, [data-${tipo}-index]`);

            cards.forEach((card, index) => {
                const pessoa = {};
                let temValor = false;

                const id = card.querySelector(`[name*="[id_cliente]"], [name*="${tipo}"]`);
                if (id && id.value) {
                    pessoa['Cliente'] = this._getFieldValue(id);
                    temValor = true;
                }

                const nome = card.querySelector('[name*="[nome]"]');
                if (nome && nome.value) {
                    pessoa['Nome'] = nome.value;
                    temValor = true;
                }

                const cpf = card.querySelector('[name*="[cpf]"]');
                if (cpf && cpf.value) {
                    pessoa['CPF'] = cpf.value;
                    temValor = true;
                }

                if (temValor) {
                    pessoas.push(pessoa);
                }
            });

            return pessoas;
        },

        /**
         * Captura dados financeiros
         */
        _captureFinanceiro(form) {
            const campos = [];
            const camposFinanceiros = [
                'id_conta', 'id_forma_pagamento', 'valor_desconto',
                'primeiro_pagamento', 'total_fatura', 'total_pagar'
            ];

            camposFinanceiros.forEach(nome => {
                const campo = form.querySelector(`[name="${nome}"]`);
                if (!campo) return;

                let valor = this._getFieldValue(campo);
                if (valor === '' || valor === null) return;

                // Formatar valores monetarios
                if (['valor_desconto', 'primeiro_pagamento', 'total_fatura', 'total_pagar'].includes(nome)) {
                    valor = this._formatMoney(valor);
                    if (valor === 'R$ 0,00') return;
                }

                campos.push({
                    label: this.config.customLabels[nome] || nome,
                    de: null,
                    para: valor
                });
            });

            return campos;
        },

        /**
         * Captura array de taxas e servicos
         */
        _captureTaxas(form) {
            const taxas = [];
            const cards = form.querySelectorAll('.taxa-card, [data-taxa-index]');

            cards.forEach((card, index) => {
                const taxa = {};
                let temValor = false;

                const nome = card.querySelector('[name*="[nome]"]');
                if (nome && nome.value) {
                    taxa['Taxa'] = nome.value;
                    temValor = true;
                }

                const id = card.querySelector('[name*="[id_taxa]"]');
                if (id && id.value && !nome?.value) {
                    taxa['Taxa'] = this._getFieldValue(id);
                    temValor = true;
                }

                const qtd = card.querySelector('[name*="[quantidade]"]');
                if (qtd && qtd.value && qtd.value !== '1') {
                    taxa['Quantidade'] = qtd.value;
                    temValor = true;
                }

                const valorTotal = card.querySelector('[name*="[valor_total]"]');
                if (valorTotal && valorTotal.value && valorTotal.value !== '0,00') {
                    taxa['Valor'] = this._formatMoney(valorTotal.value);
                    temValor = true;
                }

                if (temValor) {
                    taxas.push(taxa);
                }
            });

            return taxas;
        },

        /**
         * Captura observacoes
         */
        _captureObservacoes(form) {
            const campos = [];
            const obs = form.querySelector('[name="obs"]');

            if (obs && obs.value && obs.value.trim()) {
                campos.push({
                    label: 'Observacoes',
                    de: null,
                    para: obs.value.trim()
                });
            }

            return campos;
        },

        /**
         * Obtem valor formatado do campo
         */
        _getFieldValue(field) {
            // SELECT: capturar texto visivel
            if (field.tagName === 'SELECT') {
                const selected = field.options[field.selectedIndex];
                if (!selected || !selected.value) return '';
                return selected.text.trim();
            }

            // CHECKBOX
            if (field.type === 'checkbox') {
                return field.checked ? 'Sim' : 'Nao';
            }

            // INPUT-MOEDA: formatar com simbolo de moeda
            if (field.classList.contains('input-moeda')) {
                if (!field.value || field.value === '0,00' || field.value === '0.00') return '';
                return this._formatMoney(field.value);
            }

            // INPUT DATE: formatar dd/mm/yyyy
            if (field.type === 'date' && field.value) {
                const parts = field.value.split('-');
                if (parts.length === 3) {
                    return `${parts[2]}/${parts[1]}/${parts[0]}`;
                }
                return field.value;
            }

            // DATETIME-LOCAL: formatar dd/mm/yyyy HH:mm
            if (field.type === 'datetime-local' && field.value) {
                try {
                    const date = new Date(field.value);
                    return date.toLocaleString('pt-BR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } catch {
                    return field.value;
                }
            }

            // TEXTAREA e outros
            return field.value || '';
        },

        /**
         * Formata valor monetario
         */
        _formatMoney(valor) {
            if (window.Currency) {
                const parsed = Currency.parse(valor);
                return Currency.format(parsed, true);
            }
            // Fallback simples
            if (typeof valor === 'string') {
                valor = parseFloat(valor.replace(/\./g, '').replace(',', '.')) || 0;
            }
            return 'R$ ' + valor.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
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
        FormAudit.registerHandler('contratos-adicionar', ContratosAuditHandler);
    }
})();
