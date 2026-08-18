/**
 * Country Data for International Phone Component
 *
 * Lista de países suportados para o componente intl customizado.
 * Ordem: Brasil, EUA, Canadá, Portugal, Espanha, Itália, Paraguai,
 * Reino Unido, França, Países Baixos, Bélgica, Alemanha, Argentina, Uruguai, Chile, Colômbia e México.
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
        name: 'Canadá',
        code: 'CA',
        dialCode: '+1',
        flag: '🇨🇦',
        placeholder: '(416) 555-1234',
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
        name: 'Espanha',
        code: 'ES',
        dialCode: '+34',
        flag: '🇪🇸',
        placeholder: '612 345 678',
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
        name: 'Paraguai',
        code: 'PY',
        dialCode: '+595',
        flag: '🇵🇾',
        placeholder: '981 123456',
        // Máscara: ### ######
        maskFormat: function (number) {
            return number.replace(/^(\d{3})(\d{0,6}).*/, '$1 $2');
        }
    },
    {
        name: 'Reino Unido',
        code: 'GB',
        dialCode: '+44',
        flag: '🇬🇧',
        placeholder: '7400 123456',
        // Máscara: #### ######
        maskFormat: function (number) {
            return number.replace(/^(\d{4})(\d{0,6}).*/, '$1 $2');
        }
    },
    {
        name: 'França',
        code: 'FR',
        dialCode: '+33',
        flag: '🇫🇷',
        placeholder: '6 12 34 56 78',
        // Máscara: # ## ## ## ##
        maskFormat: function (number) {
            return number.replace(/^(\d{1})(\d{2})(\d{2})(\d{2})(\d{0,2}).*/, '$1 $2 $3 $4 $5');
        }
    },
    {
        name: 'Países Baixos',
        code: 'NL',
        dialCode: '+31',
        flag: '🇳🇱',
        placeholder: '6 12345678',
        // Máscara: # ######## (celular)
        maskFormat: function (number) {
            return number.replace(/^(\d{1})(\d{0,8}).*/, '$1 $2');
        }
    },
    {
        name: 'Bélgica',
        code: 'BE',
        dialCode: '+32',
        flag: '🇧🇪',
        placeholder: '470 12 34 56',
        // Máscara: ### ## ## ##
        maskFormat: function (number) {
            return number.replace(/^(\d{3})(\d{2})(\d{2})(\d{0,2}).*/, '$1 $2 $3 $4');
        }
    },
    {
        name: 'Alemanha',
        code: 'DE',
        dialCode: '+49',
        flag: '🇩🇪',
        placeholder: '151 23456789',
        // Máscara: ### ########
        maskFormat: function (number) {
            return number.replace(/^(\d{3})(\d{0,8}).*/, '$1 $2');
        }
    },
    {
        name: 'Argentina',
        code: 'AR',
        dialCode: '+54',
        flag: '🇦🇷',
        placeholder: '9 11 1234-5678',
        // Fixo: ## ####-#### | Celular internacional: 9 ## ####-####
        maskFormat: function (number) {
            if (number.startsWith('9')) {
                return number.replace(/^(9)(\d{2})(\d{4})(\d{0,4}).*/, '$1 $2 $3-$4');
            }

            return number.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '$1 $2-$3');
        }
    },
    {
        name: 'Uruguai',
        code: 'UY',
        dialCode: '+598',
        flag: '🇺🇾',
        placeholder: '94 123 456',
        // Máscara: ## ### ###
        maskFormat: function (number) {
            return number.replace(/^(\d{2})(\d{3})(\d{0,3}).*/, '$1 $2 $3');
        }
    },
    {
        name: 'Chile',
        code: 'CL',
        dialCode: '+56',
        flag: '🇨🇱',
        placeholder: '9 1234 5678',
        // Máscara: # #### ####
        maskFormat: function (number) {
            return number.replace(/^(\d{1})(\d{4})(\d{0,4}).*/, '$1 $2 $3');
        }
    },
    {
        name: 'Colômbia',
        code: 'CO',
        dialCode: '+57',
        flag: '🇨🇴',
        placeholder: '300 1234567',
        // Máscara: ### #######
        maskFormat: function (number) {
            return number.replace(/^(\d{3})(\d{0,7}).*/, '$1 $2');
        }
    },
    {
        name: 'México',
        code: 'MX',
        dialCode: '+52',
        flag: '🇲🇽',
        placeholder: '55 1234 5678',
        // Máscara: ## #### ####
        maskFormat: function (number) {
            return number.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '$1 $2 $3');
        }
    }
];
