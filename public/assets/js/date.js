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
        datetime_format: 'd/m/Y H:i:s',
        timezone: 'America/Sao_Paulo',
        app_timezone: 'America/Sao_Paulo'
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

        const d = this._toDate(date, false);
        if (!d || isNaN(d.getTime())) return '';

        return this._formatDate(d, this.config.date_format, false);
    },

    /**
     * Formata uma data/hora para exibicao
     *
     * @param {string|Date} datetime - Data/hora no formato ISO ou objeto Date
     * @returns {string} Data/hora formatada (ex: "15/01/2024 14:30:00")
     */
    formatDateTime(datetime) {
        if (!datetime) return '';

        const d = this._toDate(datetime, true);
        if (!d || isNaN(d.getTime())) return '';

        return this._formatDate(d, this.config.datetime_format, true);
    },

    /**
     * Formata data/hora operacional sem converter timezone.
     *
     * Use para horarios locais escolhidos pelo usuario e gravados no banco como
     * valor operacional: retirada/devolucao, inicio/fim de contrato, checklist,
     * multas, agenda e manutencoes programadas.
     */
    formatOperationalDateTime(datetime, withoutSeconds = true) {
        if (!datetime) return '';

        const match = String(datetime).trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!match) {
            return withoutSeconds
                ? String(datetime).replace(/(\d{1,2}:\d{2}):\d{2}\b/g, '$1')
                : String(datetime);
        }

        const hasTime = match[4] !== undefined;
        let format = hasTime ? this.config.datetime_format : this.config.date_format;
        if (withoutSeconds && hasTime) {
            format = format.replace(/:s\b/g, '').replace(/\bs\b/g, '').trim();
        }

        const parts = {
            d: match[3],
            j: String(parseInt(match[3], 10)),
            m: match[2],
            n: String(parseInt(match[2], 10)),
            Y: match[1],
            y: match[1].slice(-2),
            H: match[4] || '00',
            G: String(parseInt(match[4] || '0', 10)),
            i: match[5] || '00',
            s: match[6] || '00'
        };

        return format.replace(/[dmYyHGis]/g, token => parts[token] ?? token);
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
    _toDate(value, includeTime = false) {
        if (value instanceof Date) {
            return value;
        }

        if (typeof value === 'string') {
            // Adicionar T00:00:00 para evitar problemas de timezone em datas sem hora
            if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                value = value + 'T00:00:00';
            }

            if (includeTime) {
                const match = value.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
                if (match) {
                    return this._dateFromTimeZone({
                        year: parseInt(match[1], 10),
                        month: parseInt(match[2], 10),
                        day: parseInt(match[3], 10),
                        hour: parseInt(match[4], 10),
                        minute: parseInt(match[5], 10),
                        second: parseInt(match[6] || '0', 10)
                    }, this.config.app_timezone);
                }
            }

            return new Date(value);
        }

        return null;
    },

    /**
     * Formata objeto Date conforme padrao PHP
     * @private
     */
    _formatDate(date, format, useTimezone = false) {
        const pad = n => n.toString().padStart(2, '0');
        const parts = useTimezone ? this._getDateParts(date, this.config.timezone) : {
            day: date.getDate(),
            month: date.getMonth() + 1,
            year: date.getFullYear(),
            hour: date.getHours(),
            minute: date.getMinutes(),
            second: date.getSeconds()
        };

        const replacements = {
            'd': pad(parts.day),
            'j': parts.day.toString(),
            'm': pad(parts.month),
            'n': parts.month.toString(),
            'Y': parts.year.toString(),
            'y': parts.year.toString().slice(-2),
            'H': pad(parts.hour),
            'G': parts.hour.toString(),
            'i': pad(parts.minute),
            's': pad(parts.second)
        };

        let result = '';
        for (let i = 0; i < format.length; i++) {
            const char = format[i];
            result += replacements[char] !== undefined ? replacements[char] : char;
        }

        return result;
    },

    /**
     * Retorna componentes da data em um timezone IANA.
     * @private
     */
    _getDateParts(date, timezone) {
        try {
            const formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: timezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hourCycle: 'h23'
            });

            const parts = {};
            formatter.formatToParts(date).forEach(part => {
                if (part.type !== 'literal') {
                    parts[part.type] = parseInt(part.value, 10);
                }
            });

            return {
                day: parts.day,
                month: parts.month,
                year: parts.year,
                hour: parts.hour,
                minute: parts.minute,
                second: parts.second
            };
        } catch (e) {
            return {
                day: date.getDate(),
                month: date.getMonth() + 1,
                year: date.getFullYear(),
                hour: date.getHours(),
                minute: date.getMinutes(),
                second: date.getSeconds()
            };
        }
    },

    /**
     * Converte uma data/hora sem offset, gravada no timezone da aplicação,
     * para um Date absoluto.
     * @private
     */
    _dateFromTimeZone(parts, timezone) {
        const utcDate = new Date(Date.UTC(
            parts.year,
            parts.month - 1,
            parts.day,
            parts.hour,
            parts.minute,
            parts.second
        ));
        const offset = this._getTimezoneOffset(utcDate, timezone);

        return new Date(utcDate.getTime() - offset);
    },

    /**
     * Retorna offset do timezone em milissegundos para uma data.
     * @private
     */
    _getTimezoneOffset(date, timezone) {
        try {
            const parts = this._getDateParts(date, timezone);
            const asUtc = Date.UTC(
                parts.year,
                parts.month - 1,
                parts.day,
                parts.hour,
                parts.minute,
                parts.second
            );

            return asUtc - date.getTime();
        } catch (e) {
            return 0;
        }
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
            const date = this._dateFromTimeZone({ year, month, day, hour, minute, second }, this.config.timezone);
            const appParts = this._getDateParts(date, this.config.app_timezone);

            return `${appParts.year}-${pad(appParts.month)}-${pad(appParts.day)} ${pad(appParts.hour)}:${pad(appParts.minute)}:${pad(appParts.second)}`;
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
     * Retorna a data atual de negocio em Y-m-d.
     */
    todayISO() {
        const parts = this._getDateParts(new Date(), this.config.timezone);
        return this._partsToDateString(parts);
    },

    /**
     * Retorna a data/hora atual de negocio em Y-m-d H:i:s.
     */
    nowISO() {
        const parts = this._getDateParts(new Date(), this.config.timezone);
        return `${this._partsToDateString(parts)} ${this._partsToTimeString(parts)}`;
    },

    /**
     * Retorna timestamp tecnico em milissegundos.
     */
    timestamp() {
        return Date.now();
    },

    /**
     * Retorna ano atual de negocio.
     */
    currentYear() {
        return Number(this.todayISO().substring(0, 4));
    },

    /**
     * Retorna mes atual de negocio (1-12).
     */
    currentMonth() {
        return Number(this.todayISO().substring(5, 7));
    },

    /**
     * Retorna data atual para input type=date.
     */
    todayInput() {
        return this.todayISO();
    },

    /**
     * Retorna data/hora atual para input type=datetime-local.
     */
    nowInput() {
        const parts = this._getDateParts(new Date(), this.config.timezone);
        return `${this._partsToDateString(parts)}T${this._partsToTimeString(parts).slice(0, 5)}`;
    },

    /**
     * Soma dias a uma data ISO e retorna Y-m-d.
     */
    addDays(date, days) {
        const base = this._toDate(date || this.todayISO(), false);
        if (!base || isNaN(base.getTime())) return '';

        base.setDate(base.getDate() + Number(days || 0));
        return this._formatDate(base, 'Y-m-d', false);
    },

    /**
     * Soma meses a uma data ISO e retorna Y-m-d.
     */
    addMonths(date, months) {
        const base = this._toDate(date || this.todayISO(), false);
        if (!base || isNaN(base.getTime())) return '';

        base.setMonth(base.getMonth() + Number(months || 0));
        return this._formatDate(base, 'Y-m-d', false);
    },

    /**
     * Diferença em dias entre duas datas ISO.
     */
    diffDays(startDate, endDate) {
        const start = this._toDate(startDate, false);
        const end = this._toDate(endDate, false);
        if (!start || !end || isNaN(start.getTime()) || isNaN(end.getTime())) return 0;

        start.setHours(0, 0, 0, 0);
        end.setHours(0, 0, 0, 0);
        return Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));
    },

    /**
     * Compara duas datas/horas e retorna a diferenca em milissegundos.
     */
    diffDateTime(startDateTime, endDateTime) {
        const start = this._toDate(startDateTime, true);
        const end = this._toDate(endDateTime, true);
        if (!start || !end || isNaN(start.getTime()) || isNaN(end.getTime())) return 0;

        return end.getTime() - start.getTime();
    },

    /**
     * Primeiro dia do mes atual em Y-m-d.
     */
    startOfCurrentMonthISO() {
        const today = this.todayISO();
        return `${today.substring(0, 7)}-01`;
    },

    /**
     * Ultimo dia do mes atual em Y-m-d.
     */
    endOfCurrentMonthISO() {
        const today = this._toDate(this.todayISO(), false);
        today.setMonth(today.getMonth() + 1, 0);
        return this._formatDate(today, 'Y-m-d', false);
    },

    /**
     * Formata Date para input datetime-local no timezone de negocio.
     */
    toDateTimeInput(date) {
        const d = date instanceof Date ? date : this._toDate(date, true);
        if (!d || isNaN(d.getTime())) return '';

        const parts = this._getDateParts(d, this.config.timezone);
        return `${this._partsToDateString(parts)}T${this._partsToTimeString(parts).slice(0, 5)}`;
    },

    /**
     * Formata data/hora operacional para input datetime-local sem converter timezone.
     */
    toOperationalDateTimeInput(datetime) {
        if (!datetime) return '';

        const match = String(datetime).trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!match || match[4] === undefined) return '';

        return `${match[1]}-${match[2]}-${match[3]}T${match[4]}:${match[5]}`;
    },

    _partsToDateString(parts) {
        const pad = n => n.toString().padStart(2, '0');
        return `${parts.year}-${pad(parts.month)}-${pad(parts.day)}`;
    },

    _partsToTimeString(parts) {
        const pad = n => n.toString().padStart(2, '0');
        return `${pad(parts.hour)}:${pad(parts.minute)}:${pad(parts.second)}`;
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
