/**
 * Currency Helper - Formatacao de valores monetarios multi-tenant
 *
 * Usa configuracoes de locale e moeda da empresa ativa na sessao.
 * As configuracoes sao passadas via window.APP_CONFIG.currency.
 */
const Currency = {
    /**
     * Configuracao padrao (sera sobrescrita por APP_CONFIG)
     */
    config: {
        locale: 'pt_BR',
        currency: 'BRL',
        symbol: 'R$',
        decimal: ',',
        thousands: '.',
        symbolPosition: 'before'
    },

    /**
     * Inicializa com configuracoes do servidor
     */
    init() {
        if (window.APP_CONFIG && window.APP_CONFIG.currency) {
            this.config = { ...this.config, ...window.APP_CONFIG.currency };
        }
    },

    /**
     * Formata um numero para string localizada
     *
     * @param {number|string} value - Valor numerico
     * @param {boolean} showSymbol - Incluir simbolo da moeda (padrao: false para inputs)
     * @returns {string} Valor formatado (ex: "1.234,56" ou "R$ 1.234,56")
     */
    format(value, showSymbol = false) {
        // Converter para numero
        let num = parseFloat(value) || 0;

        // Formatar com 2 casas decimais
        const parts = num.toFixed(2).split('.');
        const intPart = parts[0];
        const decPart = parts[1];

        // Adicionar separador de milhares
        let formatted = '';
        const len = intPart.length;
        for (let i = 0; i < len; i++) {
            if (i > 0 && (len - i) % 3 === 0) {
                formatted += this.config.thousands;
            }
            formatted += intPart[i];
        }

        // Adicionar parte decimal
        formatted += this.config.decimal + decPart;

        // Adicionar simbolo se solicitado
        if (showSymbol) {
            if (this.config.symbolPosition === 'before') {
                formatted = this.config.symbol + ' ' + formatted;
            } else {
                formatted = formatted + ' ' + this.config.symbol;
            }
        }

        return formatted;
    },

    /**
     * Converte string formatada para numero
     *
     * @param {string} value - Valor formatado (ex: "R$ 1.234,56" ou "1.234,56")
     * @returns {number} Valor numerico (ex: 1234.56)
     */
    parse(value) {
        if (typeof value === 'number') {
            return value;
        }

        if (!value || value.trim() === '') {
            return 0;
        }

        // Remover simbolo de moeda e espacos
        let cleaned = value.replace(/[^\d,.\-]/g, '').trim();

        if (cleaned === '' || cleaned === '-') {
            return 0;
        }

        const hasComma = cleaned.indexOf(',') !== -1;
        const hasDot = cleaned.indexOf('.') !== -1;

        if (hasComma && hasDot) {
            // Tem ambos: o ultimo e o decimal
            const lastComma = cleaned.lastIndexOf(',');
            const lastDot = cleaned.lastIndexOf('.');

            if (lastComma > lastDot) {
                // Formato BR: 1.234,56
                cleaned = cleaned.replace(/\./g, '').replace(',', '.');
            } else {
                // Formato US: 1,234.56
                cleaned = cleaned.replace(/,/g, '');
            }
        } else if (hasComma) {
            // So tem virgula
            const parts = cleaned.split(',');
            if (parts.length === 2 && parts[1].length <= 2) {
                // Decimal BR: 1234,56
                cleaned = cleaned.replace(',', '.');
            } else {
                // Milhares US: 1,234,567
                cleaned = cleaned.replace(/,/g, '');
            }
        } else if (hasDot) {
            // So tem ponto
            const parts = cleaned.split('.');
            if (parts.length === 2 && parts[1].length <= 2) {
                // Decimal US: 1234.56 - ja esta correto
            } else {
                // Milhares BR: 1.234.567
                cleaned = cleaned.replace(/\./g, '');
            }
        }

        return parseFloat(cleaned) || 0;
    },

    /**
     * Aplica mascara de moeda em tempo real em um input
     *
     * @param {HTMLInputElement|string} input - Elemento input ou seletor
     */
    applyMask(input) {
        const element = typeof input === 'string' ? document.querySelector(input) : input;

        if (!element) {
            console.warn('Currency.applyMask: elemento nao encontrado');
            return;
        }

        // Armazenar referencia ao Currency no escopo
        const self = this;

        // Handler para formatar em tempo real
        element.addEventListener('input', function(e) {
            const cursorPos = this.selectionStart;
            const oldValue = this.value;
            const oldLength = oldValue.length;

            // Obter apenas digitos
            let digits = oldValue.replace(/\D/g, '');

            // Limitar a um valor razoavel (max 15 digitos = trilhoes)
            if (digits.length > 15) {
                digits = digits.substring(0, 15);
            }

            // Converter para centavos (sempre trabalhar com inteiro)
            let cents = parseInt(digits) || 0;

            // Converter para valor com decimais
            let value = cents / 100;

            // Formatar
            let formatted = self.format(value, false);

            // Se estava vazio e continua vazio, manter vazio
            if (digits === '' || digits === '0' || digits === '00') {
                formatted = '';
            }

            // Atualizar valor
            this.value = formatted;

            // Ajustar posicao do cursor
            const newLength = formatted.length;
            const diff = newLength - oldLength;

            // Tentar manter o cursor na posicao correta
            let newCursorPos = cursorPos + diff;
            if (newCursorPos < 0) newCursorPos = 0;
            if (newCursorPos > newLength) newCursorPos = newLength;

            this.setSelectionRange(newCursorPos, newCursorPos);
        });

        // Formatar valor inicial se existir
        if (element.value) {
            const initialValue = this.parse(element.value);
            if (initialValue > 0) {
                element.value = this.format(initialValue, false);
            }
        }

        // Handler para limpar ao focar se for zero e posicionar cursor no final
        element.addEventListener('focus', function() {
            if (this.value === '0,00' || this.value === '0.00') {
                this.value = '';
            }
            // Mover cursor para o final do input
            const input = this;
            setTimeout(() => {
                input.setSelectionRange(input.value.length, input.value.length);
            }, 0);
        });

        // Handler para garantir formato ao sair
        element.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.value = '';
                return;
            }

            const value = self.parse(this.value);
            this.value = self.format(value, false);
        });

        // Marcar elemento como mascarado
        element.dataset.currencyMask = 'true';
    },

    /**
     * Aplica mascara em todos os inputs com classe especifica
     *
     * @param {string} className - Nome da classe (padrao: 'input-moeda')
     */
    applyMaskToAll(className = 'input-moeda') {
        const inputs = document.querySelectorAll('input.' + className);
        inputs.forEach(input => {
            if (!input.dataset.currencyMask) {
                this.applyMask(input);
            }
        });
    },

    /**
     * Retorna o valor numerico de um input mascarado
     *
     * @param {HTMLInputElement|string} input - Elemento input ou seletor
     * @returns {number} Valor numerico
     */
    getValue(input) {
        const element = typeof input === 'string' ? document.querySelector(input) : input;
        if (!element) return 0;
        return this.parse(element.value);
    },

    /**
     * Define o valor de um input mascarado
     *
     * @param {HTMLInputElement|string} input - Elemento input ou seletor
     * @param {number|string} value - Valor a definir
     */
    setValue(input, value) {
        const element = typeof input === 'string' ? document.querySelector(input) : input;
        if (!element) return;

        const numValue = parseFloat(value) || 0;
        element.value = numValue > 0 ? this.format(numValue, false) : '';
    },

    /**
     * Atualiza todos os simbolos de moeda na pagina
     */
    updateSymbols() {
        const symbol = this.config.symbol || 'R$';
        document.querySelectorAll('.currency-symbol').forEach(el => {
            el.textContent = symbol;
        });
    }
};

// Inicializar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        Currency.init();
        Currency.applyMaskToAll('input-moeda');
        Currency.updateSymbols();
    });
} else {
    Currency.init();
    Currency.applyMaskToAll('input-moeda');
    Currency.updateSymbols();
}

// Exportar para uso global
window.Currency = Currency;
