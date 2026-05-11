/**
 * Percent Helper - Formatacao de valores percentuais
 *
 * Formata valores com virgula como separador decimal (pt_BR).
 * Suporta casas decimais configuraveis via data-decimals.
 */
const Percent = {
    /**
     * Configuracao padrao
     */
    config: {
        decimal: ',',
        defaultDecimals: 2
    },

    /**
     * Inicializa o helper
     */
    init() {
        // Nada a inicializar por enquanto
    },

    /**
     * Formata um numero para string com virgula
     *
     * @param {number|string} value - Valor numerico
     * @param {number} decimals - Casas decimais (padrao: 2)
     * @returns {string} Valor formatado (ex: "2,50")
     */
    format(value, decimals = 2) {
        let num = parseFloat(value) || 0;
        const formatted = num.toFixed(decimals);
        return formatted.replace('.', this.config.decimal);
    },

    /**
     * Converte string formatada para numero
     *
     * @param {string} value - Valor formatado (ex: "2,50")
     * @returns {number} Valor numerico (ex: 2.5)
     */
    parse(value) {
        if (typeof value === 'number') {
            return value;
        }

        if (!value || value.trim() === '') {
            return 0;
        }

        // Remover tudo que nao seja digito, virgula ou ponto
        let cleaned = value.replace(/[^\d,.\-]/g, '').trim();

        if (cleaned === '' || cleaned === '-') {
            return 0;
        }

        // Converter virgula para ponto
        cleaned = cleaned.replace(',', '.');

        return parseFloat(cleaned) || 0;
    },

    /**
     * Aplica mascara em tempo real em um input
     *
     * @param {HTMLInputElement|string} input - Elemento input ou seletor
     * @param {number} decimals - Casas decimais (se nao informado, usa data-decimals ou 2)
     */
    applyMask(input, decimals = null) {
        const element = typeof input === 'string' ? document.querySelector(input) : input;

        if (!element) {
            console.warn('Percent.applyMask: elemento nao encontrado');
            return;
        }

        // Determinar casas decimais
        const decimalPlaces = decimals !== null ? decimals : (parseInt(element.dataset.decimals) || this.config.defaultDecimals);

        // Armazenar referencia ao Percent no escopo
        const self = this;

        // Handler para formatar em tempo real
        element.addEventListener('input', function() {
            var cursorPos = this.selectionStart;
            var oldValue = this.value;
            var oldLength = oldValue.length;

            // Obter apenas digitos
            var digits = oldValue.replace(/\D/g, '');

            // Limitar a um valor razoavel (max 10 digitos)
            if (digits.length > 10) {
                digits = digits.substring(0, 10);
            }

            // Converter para inteiro e dividir pelo fator de casas decimais
            var intValue = parseInt(digits) || 0;
            var divisor = Math.pow(10, decimalPlaces); // 100 para 2 casas, 1000 para 3 casas
            var value = intValue / divisor;

            // Formatar
            var formatted = self.format(value, decimalPlaces);

            // Se estava vazio, manter vazio
            if (digits === '' || intValue === 0) {
                formatted = '';
            }

            // Atualizar valor
            this.value = formatted;

            // Ajustar posicao do cursor
            var newLength = formatted.length;
            var diff = newLength - oldLength;
            var newCursorPos = cursorPos + diff;
            if (newCursorPos < 0) newCursorPos = 0;
            if (newCursorPos > newLength) newCursorPos = newLength;
            this.setSelectionRange(newCursorPos, newCursorPos);
        });

        // Formatar valor inicial se existir
        if (element.value) {
            const initialValue = this.parse(element.value);
            if (initialValue > 0) {
                element.value = this.format(initialValue, decimalPlaces);
            }
        }

        // Handler para limpar ao focar se for zero
        element.addEventListener('focus', function() {
            const zeroValue = self.format(0, decimalPlaces);
            if (this.value === zeroValue) {
                this.value = '';
            }
        });

        // Handler para garantir formato ao sair
        element.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.value = '';
                return;
            }

            const value = self.parse(this.value);
            this.value = self.format(value, decimalPlaces);
        });

        // Marcar elemento como mascarado e armazenar casas decimais
        element.dataset.percentMask = 'true';
        element.dataset.decimals = decimalPlaces;
    },

    /**
     * Aplica mascara em todos os inputs com classe especifica
     *
     * @param {string} className - Nome da classe (padrao: 'input-percent')
     */
    applyMaskToAll(className = 'input-percent') {
        const inputs = document.querySelectorAll('input.' + className);
        inputs.forEach(input => {
            if (!input.dataset.percentMask) {
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
     * @param {number} decimals - Casas decimais (se nao informado, usa data-decimals ou 2)
     */
    setValue(input, value, decimals = null) {
        const element = typeof input === 'string' ? document.querySelector(input) : input;
        if (!element) return;

        const decimalPlaces = decimals !== null ? decimals : (parseInt(element.dataset.decimals) || this.config.defaultDecimals);
        const numValue = parseFloat(value) || 0;
        element.value = numValue > 0 ? this.format(numValue, decimalPlaces) : '';
    }
};

// Inicializar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        Percent.init();
        Percent.applyMaskToAll('input-percent');
    });
} else {
    Percent.init();
    Percent.applyMaskToAll('input-percent');
}

// Exportar para uso global
window.Percent = Percent;
