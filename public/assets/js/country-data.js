/**
 * Country Data for International Phone Component
 *
 * Lista de países suportados para o componente intl customizado
 * Ordem: Brasil, USA, Portugal (conforme especificado)
 */

const countryData = [
    {
        name: 'Brasil',
        code: 'BR',
        dialCode: '+55',
        flag: '🇧🇷',
        placeholder: '(11) 99999-9999',
        // Máscara: (##) #####-#### para celular (11 dígitos) ou (##) ####-#### para fixo (10 dígitos)
        maskFormat: function (number) {
            if (number.length <= 10) {
                // Fixo: (11) 9999-9999
                return number.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else {
                // Celular: (11) 99999-9999
                return number.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
            }
        }
    },
    {
        name: 'Estados Unidos',
        code: 'US',
        dialCode: '+1',
        flag: '🇺🇸',
        placeholder: '(555) 123-4567',
        // Máscara: (###) ###-####
        maskFormat: function (number) {
            return number.replace(/^(\d{3})(\d{3})(\d{0,4}).*/, '($1) $2-$3');
        }
    },
    {
        name: 'Portugal',
        code: 'PT',
        dialCode: '+351',
        flag: '🇵🇹',
        placeholder: '912 345 678',
        // Máscara: ### ### ###
        maskFormat: function (number) {
            return number.replace(/^(\d{3})(\d{3})(\d{0,3}).*/, '$1 $2 $3');
        }
    },
    {
        name: 'Itália',
        code: 'IT',
        dialCode: '+39',
        flag: '🇮🇹',
        placeholder: '333 123 4567',
        // Máscara: ### ### ####
        maskFormat: function (number) {
            return number.replace(/^(\d{3})(\d{3})(\d{0,4}).*/, '$1 $2 $3');
        }
    },
    {
        name: 'Espanha',
        code: 'ES',
        dialCode: '+34',
        flag: '🇪🇸',
        placeholder: '612 345 678',
        // Máscara: ### ### ###
        maskFormat: function (number) {
            return number.replace(/^(\d{3})(\d{3})(\d{0,3}).*/, '$1 $2 $3');
        }
    }
];
