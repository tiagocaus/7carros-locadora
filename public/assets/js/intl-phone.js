/**
 * IntlPhone - International Phone Component (Custom)
 *
 * Componente customizado para campos de telefone com seleção de país
 * Com máscaras específicas por país
 * Sem dependências externas
 *
 * @author Sistema 7Carros
 * @version 2.0.0
 */

class IntlPhone {
    constructor(inputElement) {
        this.input = inputElement;
        this.selectedCountry = countryData[0]; // Brasil por padrão
        this.isDropdownOpen = false;
        this.wrapper = null;
        this.flagButton = null;
        this.dropdown = null;
        this._isDeleting = false; // Flag para detectar exclusão (backspace/delete)

        // Guardar referência da instância no elemento para acesso posterior
        this.input._intlPhone = this;

        this.init();
    }

    /**
     * Inicializa o componente
     */
    init() {
        // Verificar se countryData está disponível
        if (typeof countryData === 'undefined') {
            console.error('IntlPhone: countryData não encontrado. Certifique-se de incluir country-data.js antes de intl-phone.js');
            return;
        }

        // Não inicializar se já foi inicializado
        if (this.input.classList.contains('intl-phone-initialized')) {
            return;
        }

        // Marcar como inicializado
        this.input.classList.add('intl-phone-initialized');

        // Criar estrutura HTML
        this.createStructure();

        // Detectar país do valor existente
        this.detectCountryFromValue();

        // Adicionar event listeners
        this.attachEvents();

        // Fechar dropdown ao clicar fora
        document.addEventListener('click', (e) => this.handleOutsideClick(e));
    }

    /**
     * Cria a estrutura HTML do componente
     */
    createStructure() {
        // Criar wrapper
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'intl-phone-wrapper';

        // Substituir input pelo wrapper
        this.input.parentNode.insertBefore(this.wrapper, this.input);
        this.wrapper.appendChild(this.input);

        // Criar botão da bandeira
        this.flagButton = document.createElement('button');
        this.flagButton.type = 'button';
        this.flagButton.className = 'intl-phone-flag-button';
        this.updateFlagButton();

        // Inserir bandeira antes do input
        this.wrapper.insertBefore(this.flagButton, this.input);

        // Criar dropdown
        this.createDropdown();

        // Adicionar padding ao input para não sobrepor a bandeira
        this.input.style.paddingLeft = '62px';

        // Aplicar placeholder do país selecionado
        if (this.selectedCountry.placeholder) {
            this.input.setAttribute('placeholder', this.selectedCountry.placeholder);
        }
    }

    /**
     * Atualiza o botão de seleção de país
     */
    updateFlagButton() {
        this.flagButton.innerHTML = `
            <span class="flag-emoji">${this.selectedCountry.flag}</span>
            <span class="intl-phone-chevron" aria-hidden="true">▾</span>
        `;
        this.flagButton.title = `${this.selectedCountry.name} (${this.selectedCountry.dialCode})`;
    }

    /**
     * Cria o dropdown de seleção de países
     */
    createDropdown() {
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'intl-phone-dropdown';
        this.dropdown.style.display = 'none';

        const list = document.createElement('ul');
        list.className = 'intl-phone-list';

        countryData.forEach(country => {
            const item = document.createElement('li');
            item.className = 'intl-phone-list-item';
            item.innerHTML = `
                <span class="flag-emoji">${country.flag}</span>
                <span class="country-name">${country.name}</span>
                <span class="dial-code">${country.dialCode}</span>
            `;
            item.dataset.code = country.code;
            item.addEventListener('click', () => this.selectCountry(country));
            list.appendChild(item);
        });

        this.dropdown.appendChild(list);
        this.wrapper.appendChild(this.dropdown);
    }

    /**
     * Anexa eventos aos elementos
     */
    attachEvents() {
        // Toggle dropdown ao clicar na bandeira
        this.flagButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleDropdown();
        });

        // Detectar backspace/delete ANTES do input (keydown vem antes de input)
        this.input.addEventListener('keydown', (e) => {
            this._isDeleting = (e.key === 'Backspace' || e.key === 'Delete');
        });

        // Detectar DDI digitado e aplicar máscara
        this.input.addEventListener('input', (e) => {
            this.handleInput(e);
        });

        // Permitir apenas números, +, espaço, parênteses e hífen
        this.input.addEventListener('keypress', (e) => {
            const char = String.fromCharCode(e.which);
            if (!/[0-9+\s()\-]/.test(char)) {
                e.preventDefault();
            }
        });
    }

    /**
     * Alterna exibição do dropdown
     */
    toggleDropdown() {
        this.isDropdownOpen = !this.isDropdownOpen;

        if (this.isDropdownOpen) {
            this.dropdown.style.display = 'block';
            this.wrapper.classList.add('dropdown-open');
        } else {
            this.dropdown.style.display = 'none';
            this.wrapper.classList.remove('dropdown-open');
        }
    }

    /**
     * Fecha o dropdown
     */
    closeDropdown() {
        this.isDropdownOpen = false;
        this.dropdown.style.display = 'none';
        this.wrapper.classList.remove('dropdown-open');
    }

    /**
     * Seleciona um país
     */
    selectCountry(country) {
        this.selectedCountry = country;

        // Atualizar bandeira
        this.updateFlagButton();

        // Atualizar placeholder com o formato do novo país
        if (country.placeholder) {
            this.input.setAttribute('placeholder', country.placeholder);
        }

        // Atualizar valor do input com nova máscara
        this.updateInputValue();

        // Fechar dropdown
        this.closeDropdown();

        // Focar no input
        this.input.focus();
    }

    /**
     * Extrai apenas os dígitos do número (sem DDI)
     */
    extractDigits(value) {
        // Remove o DDI atual
        let numberOnly = value.trim();

        countryData.forEach(country => {
            if (numberOnly.startsWith(country.dialCode)) {
                numberOnly = numberOnly.substring(country.dialCode.length);
            }
        });

        // Remove tudo que não é dígito
        return numberOnly.replace(/\D/g, '');
    }

    /**
     * Aplica máscara ao número
     */
    applyMask(digits) {
        if (!digits || digits === '') {
            return ''; // Retornar vazio em vez de forçar DDI
        }

        // Aplica a máscara do país
        const masked = this.selectedCountry.maskFormat(digits);

        return this.selectedCountry.dialCode + ' ' + masked.trim();
    }

    /**
     * Atualiza o valor do input com DDI e máscara
     */
    updateInputValue() {
        const currentValue = this.input.value.trim();

        // Se campo estiver vazio, adicionar apenas o DDI
        if (!currentValue || currentValue === '') {
            this.input.value = this.selectedCountry.dialCode + ' ';
            return;
        }

        // Extrair dígitos
        const digits = this.extractDigits(currentValue);

        // Aplicar máscara
        this.input.value = this.applyMask(digits);
    }

    /**
     * Detecta DDI digitado e seleciona país automaticamente
     * Aplica máscara em tempo real
     */
    handleInput(e) {
        const value = this.input.value;

        // Se campo vazio, não processar (permite apagar tudo)
        if (!value || value.trim() === '') {
            this._isDeleting = false;
            return;
        }

        // Se está apagando (backspace/delete), NÃO aplicar máscara
        // Deixar o valor como o usuário deixou
        if (this._isDeleting) {
            this._isDeleting = false;
            return;
        }

        // Se valor não começa com +, adicionar DDI do país selecionado
        if (!value.startsWith('+')) {
            const digits = value.replace(/\D/g, '');
            if (digits.length > 0) {
                // Usuário começou a digitar números sem DDI
                // Adicionar DDI do país selecionado e aplicar máscara
                this.input.value = this.applyMask(digits);
                // Posicionar cursor no final
                this.input.setSelectionRange(this.input.value.length, this.input.value.length);
            }
            return;
        }

        // Se só tem DDI (com ou sem espaço), não processar
        for (const country of countryData) {
            if (value === country.dialCode ||
                value === country.dialCode + ' ' ||
                value.trim() === country.dialCode) {
                return;
            }
        }

        // Verificar cada país e atualizar bandeira se necessário
        for (const country of countryData) {
            if (value.startsWith(country.dialCode)) {
                if (this.selectedCountry.code !== country.code) {
                    this.selectedCountry = country;
                    this.updateFlagButton();
                }
                break;
            }
        }

        // Extrair dígitos e aplicar máscara
        const digits = this.extractDigits(value);

        // Se não há dígitos, não fazer nada
        if (digits.length === 0) {
            return;
        }

        // Salvar posição do cursor
        const cursorPosition = this.input.selectionStart;

        // Aplicar máscara
        const oldValue = this.input.value;
        const newValue = this.applyMask(digits);

        this.input.value = newValue;

        // Ajustar posição do cursor
        const lengthDiff = newValue.length - oldValue.length;
        let newCursorPosition = cursorPosition + lengthDiff;

        if (newCursorPosition < 0) newCursorPosition = newValue.length;
        if (newCursorPosition > newValue.length) newCursorPosition = newValue.length;

        this.input.setSelectionRange(newCursorPosition, newCursorPosition);
    }

    /**
     * Detecta país a partir do valor existente do input
     */
    detectCountryFromValue() {
        const value = this.input.value.trim();

        if (value && value.startsWith('+')) {
            // Verificar cada país
            for (const country of countryData) {
                if (value.startsWith(country.dialCode)) {
                    this.selectedCountry = country;
                    this.updateFlagButton();

                    // Aplicar máscara ao valor existente
                    const digits = this.extractDigits(value);
                    this.input.value = this.applyMask(digits);
                    break;
                }
            }
        } else if (value && value !== '') {
            // Se tiver valor mas não tiver DDI, extrair dígitos e aplicar máscara do país padrão
            const digits = value.replace(/\D/g, '');
            this.input.value = this.applyMask(digits);
        }
        // Campo vazio permanece vazio (DDI será adicionado ao começar a digitar)
    }

    /**
     * Fecha dropdown ao clicar fora
     */
    handleOutsideClick(e) {
        if (!this.wrapper.contains(e.target)) {
            this.closeDropdown();
        }
    }

    /**
     * Obtém o valor limpo (apenas DDI + dígitos)
     * Para ser usado antes de enviar o formulário
     */
    getCleanValue() {
        const digits = this.extractDigits(this.input.value);
        return this.selectedCountry.dialCode + digits;
    }

    /**
     * Inicializa todos os campos .intltel da página
     */
    static initAll() {
        const inputs = document.querySelectorAll('.intltel');
        const instances = [];

        inputs.forEach(input => {
            const instance = new IntlPhone(input);
            instances.push(instance);

            // Adicionar evento ao formulário pai para limpar máscaras antes de enviar
            const form = input.closest('form');
            if (form && !form.hasAttribute('data-intl-phone-processed')) {
                form.setAttribute('data-intl-phone-processed', 'true');

                form.addEventListener('submit', function (e) {
                    // Limpar máscaras de todos os campos intltel antes de enviar
                    const intlInputs = form.querySelectorAll('.intltel');
                    intlInputs.forEach(intlInput => {
                        // Obter apenas DDI + dígitos
                        const digits = intlInput.value.replace(/\D/g, '').replace(/^(\d{1,4})/, '');

                        // Detectar qual país
                        let dialCode = '+55'; // Padrão Brasil
                        countryData.forEach(country => {
                            if (intlInput.value.startsWith(country.dialCode)) {
                                dialCode = country.dialCode;
                            }
                        });

                        // Valor final: DDI + dígitos
                        intlInput.value = dialCode + digits;
                    });
                });
            }
        });

        return instances;
    }
}

// Inicializar automaticamente quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function () {
    IntlPhone.initAll();
});
