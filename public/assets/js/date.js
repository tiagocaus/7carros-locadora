/**
 * Date Helper - Formatacao de datas multi-tenant
 *
 * Usa configuracoes de formato de data da empresa ativa na sessao.
 * As configuracoes sao passadas via window.APP_CONFIG.date.
 */
const DateHelper = {
    /**
     * Configuracao padrao (sera sobrescrita por APP_CONFIG)
     */
    config: {
        date_format: 'd/m/Y',
        datetime_format: 'd/m/Y H:i:s'
    },

    /**
     * Inicializa com configuracoes do servidor
     */
    init() {
        if (window.APP_CONFIG && window.APP_CONFIG.date) {
            this.config = { ...this.config, ...window.APP_CONFIG.date };
        }
    },

    /**
     * Formata uma data para exibicao
     *
     * @param {string|Date} date - Data no formato ISO (Y-m-d) ou objeto Date
     * @returns {string} Data formatada (ex: "15/01/2024")
     */
    format(date) {
        if (!date) return '';

        const d = this._toDate(date);
        if (!d || isNaN(d.getTime())) return '';

        return this._formatDate(d, this.config.date_format);
    },

    /**
     * Formata uma data/hora para exibicao
     *
     * @param {string|Date} datetime - Data/hora no formato ISO ou objeto Date
     * @returns {string} Data/hora formatada (ex: "15/01/2024 14:30:00")
     */
    formatDateTime(datetime) {
        if (!datetime) return '';

        const d = this._toDate(datetime);
        if (!d || isNaN(d.getTime())) return '';

        return this._formatDate(d, this.config.datetime_format);
    },

    /**
     * Converte uma data do formato local para formato internacional
     *
     * @param {string} dateStr - Data no formato local (ex: "15/01/2024")
     * @returns {string|null} Data no formato internacional (Y-m-d)
     */
    parse(dateStr) {
        if (!dateStr) return null;
        return this._parseDate(dateStr, this.config.date_format);
    },

    /**
     * Converte uma data/hora do formato local para formato internacional
     *
     * @param {string} datetimeStr - Data/hora no formato local
     * @returns {string|null} Data/hora no formato internacional (Y-m-d H:i:s)
     */
    parseDateTime(datetimeStr) {
        if (!datetimeStr) return null;
        return this._parseDate(datetimeStr, this.config.datetime_format);
    },

    /**
     * Converte string ou Date para objeto Date
     * @private
     */
    _toDate(value) {
        if (value instanceof Date) {
            return value;
        }

        if (typeof value === 'string') {
            // Se ja esta no formato ISO, criar Date diretamente
            // Adicionar T00:00:00 para evitar problemas de timezone em datas sem hora
            if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                value = value + 'T00:00:00';
            }
            return new Date(value);
        }

        return null;
    },

    /**
     * Formata objeto Date conforme padrao PHP
     * @private
     */
    _formatDate(date, format) {
        const pad = n => n.toString().padStart(2, '0');

        const replacements = {
            'd': pad(date.getDate()),
            'j': date.getDate().toString(),
            'm': pad(date.getMonth() + 1),
            'n': (date.getMonth() + 1).toString(),
            'Y': date.getFullYear().toString(),
            'y': date.getFullYear().toString().slice(-2),
            'H': pad(date.getHours()),
            'G': date.getHours().toString(),
            'i': pad(date.getMinutes()),
            's': pad(date.getSeconds())
        };

        let result = '';
        for (let i = 0; i < format.length; i++) {
            const char = format[i];
            result += replacements[char] !== undefined ? replacements[char] : char;
        }

        return result;
    },

    /**
     * Converte string formatada para formato ISO
     * @private
     */
    _parseDate(str, format) {
        // Mapear posicoes dos componentes no formato
        const formatChars = format.match(/[dmYyHGis]/g) || [];
        const dateParts = str.match(/\d+/g) || [];

        if (formatChars.length < 3 || dateParts.length < 3) {
            return null;
        }

        let day = 1, month = 1, year = 2000, hour = 0, minute = 0, second = 0;
        let formatIndex = 0;

        for (let i = 0; i < format.length && formatIndex < dateParts.length; i++) {
            const char = format[i];
            const val = parseInt(dateParts[formatIndex]) || 0;

            switch (char) {
                case 'd':
                case 'j':
                    day = val;
                    formatIndex++;
                    break;
                case 'm':
                case 'n':
                    month = val;
                    formatIndex++;
                    break;
                case 'Y':
                    year = val;
                    formatIndex++;
                    break;
                case 'y':
                    year = val < 50 ? 2000 + val : 1900 + val;
                    formatIndex++;
                    break;
                case 'H':
                case 'G':
                    hour = val;
                    formatIndex++;
                    break;
                case 'i':
                    minute = val;
                    formatIndex++;
                    break;
                case 's':
                    second = val;
                    formatIndex++;
                    break;
            }
        }

        const pad = n => n.toString().padStart(2, '0');

        // Verificar se formato inclui hora
        if (/[HGis]/.test(format)) {
            return `${year}-${pad(month)}-${pad(day)} ${pad(hour)}:${pad(minute)}:${pad(second)}`;
        }

        return `${year}-${pad(month)}-${pad(day)}`;
    },

    /**
     * Retorna a data atual formatada
     *
     * @returns {string} Data atual formatada
     */
    today() {
        return this.format(new Date());
    },

    /**
     * Retorna a data/hora atual formatada
     *
     * @returns {string} Data/hora atual formatada
     */
    now() {
        return this.formatDateTime(new Date());
    },

    /**
     * Aplica mascara de data em um input
     *
     * @param {HTMLInputElement|string} input - Elemento input ou seletor
     * @param {boolean} includeTime - Incluir hora (padrao: false)
     */
    applyMask(input, includeTime = false) {
        const element = typeof input === 'string' ? document.querySelector(input) : input;

        if (!element) {
            console.warn('DateHelper.applyMask: elemento nao encontrado');
            return;
        }

        const format = includeTime ? this.config.datetime_format : this.config.date_format;
        const self = this;

        // Determinar mascara baseada no formato
        const mask = format.replace(/[dmYyHGis]/g, '#');

        element.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            let result = '';
            let valueIndex = 0;

            for (let i = 0; i < mask.length && valueIndex < value.length; i++) {
                if (mask[i] === '#') {
                    result += value[valueIndex];
                    valueIndex++;
                } else {
                    result += mask[i];
                }
            }

            this.value = result;
        });

        // Marcar elemento como mascarado
        element.dataset.dateMask = 'true';
    },

    /**
     * Aplica mascara em todos os inputs com classe especifica
     *
     * @param {string} className - Nome da classe (padrao: 'date-mask')
     * @param {boolean} includeTime - Incluir hora
     */
    applyMaskToAll(className = 'date-mask', includeTime = false) {
        const inputs = document.querySelectorAll('input.' + className);
        inputs.forEach(input => {
            if (!input.dataset.dateMask) {
                this.applyMask(input, includeTime);
            }
        });
    }
};

// Inicializar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => DateHelper.init());
} else {
    DateHelper.init();
}

// Exportar para uso global
window.DateHelper = DateHelper;
