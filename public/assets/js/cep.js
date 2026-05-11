/**
 * Busca Automática de CEP/Código Postal
 * - Brasil: ViaCEP (retorna rua, bairro, cidade, estado)
 * - Outros países: zippopotam.us (retorna cidade e estado)
 * JavaScript Vanilla (sem dependências jQuery)
 */

document.addEventListener('DOMContentLoaded', function () {

    // ========================================
    // MÁSCARAS DE ENTRADA
    // ========================================

    /**
     * Aplica máscara de CEP brasileiro (99999-999)
     */
    function aplicarMascaraCEP(valor) {
        valor = valor.replace(/\D/g, '');
        valor = valor.replace(/^(\d{5})(\d)/, '$1-$2');
        return valor.substring(0, 9);
    }

    /**
     * Aplica máscara de CPF (999.999.999-99)
     */
    function aplicarMascaraCPF(valor) {
        valor = valor.replace(/\D/g, '');
        valor = valor.replace(/^(\d{3})(\d)/, '$1.$2');
        valor = valor.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
        valor = valor.replace(/\.(\d{3})(\d)/, '.$1-$2');
        return valor.substring(0, 14);
    }

    // Aplicar máscara de CPF nos campos
    const camposCPF = document.querySelectorAll('.cpf');
    camposCPF.forEach(campo => {
        campo.addEventListener('input', function (e) {
            e.target.value = aplicarMascaraCPF(e.target.value);
        });
    });

    // Nota: Máscara de telefone agora é gerenciada pelo componente intl-phone
    // Campos com classe .intltel são tratados automaticamente pelo intl-phone.js


    // ========================================
    // DETECÇÃO DE PAÍS SELECIONADO
    // ========================================

    const campoPais = document.getElementById('pais');
    const campoCEP = document.getElementById('cep');

    if (!campoCEP) {
        return; // Não continua se não existir campo CEP na página
    }

    /**
     * Retorna o código ISO do país selecionado
     */
    function getPaisSelecionado() {
        return campoPais ? campoPais.value : 'BR';
    }

    /**
     * Verifica se o país é Brasil
     */
    function isBrasil() {
        return getPaisSelecionado() === 'BR';
    }

    // ========================================
    // MÁSCARA DE CEP DINÂMICA POR PAÍS
    // ========================================

    /**
     * Aplica ou remove máscara de CEP conforme o país
     */
    function ajustarMascaraCEP() {
        if (isBrasil()) {
            campoCEP.maxLength = 9;
            campoCEP.placeholder = '00000-000';
            // Reaplicar máscara brasileira no valor atual
            campoCEP.value = aplicarMascaraCEP(campoCEP.value);
        } else {
            campoCEP.maxLength = 15;
            campoCEP.placeholder = '';
        }
    }

    // Aplicar máscara CEP no input
    const camposCEPMask = document.querySelectorAll('.cep');
    camposCEPMask.forEach(campo => {
        campo.addEventListener('input', function (e) {
            if (isBrasil()) {
                e.target.value = aplicarMascaraCEP(e.target.value);
            }
        });
    });

    // Ajustar máscara quando mudar o país
    if (campoPais) {
        campoPais.addEventListener('change', function () {
            ajustarMascaraCEP();
        });
    }

    // Inicializar máscara
    ajustarMascaraCEP();


    // ========================================
    // BUSCA AUTOMÁTICA DE CEP
    // ========================================

    // Evento quando o campo CEP perde o foco
    campoCEP.addEventListener('blur', function () {
        buscarCEP();
    });

    /**
     * Função principal - decide qual API usar
     */
    function buscarCEP() {
        const cepField = document.getElementById('cep');
        const postalCode = cepField.value.replace(/\D/g, '').trim();

        if (!postalCode || postalCode.length < 3) {
            return;
        }

        if (isBrasil()) {
            buscarCEPViaCep(postalCode);
        } else {
            buscarCEPZippopotamus(getPaisSelecionado().toLowerCase(), cepField.value.trim());
        }
    }

    /**
     * Busca CEP brasileiro via ViaCEP
     * Retorna: rua, bairro, cidade, estado
     */
    function buscarCEPViaCep(cep) {
        if (cep.length !== 8) {
            return;
        }

        // CEP inválido (todos dígitos iguais)
        if (/^(\d)\1+$/.test(cep)) {
            mostrarErro('CEP inválido');
            return;
        }

        mostrarLoading();

        fetch(`https://viacep.com.br/ws/${cep}/json/`, {
            method: 'GET',
            mode: 'cors',
            cache: 'default'
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na requisição');
                }
                return response.json();
            })
            .then(dados => {
                esconderLoading();

                if (dados.erro) {
                    mostrarErro('CEP não encontrado');
                    limparCamposEndereco();
                    return;
                }

                // Preenche os campos com dados do ViaCEP
                const campoRua = document.getElementById('rua');
                const campoBairro = document.getElementById('bairro');
                const campoCidade = document.getElementById('cidade');
                const campoUF = document.getElementById('uf') || document.getElementById('estado');

                if (campoRua) campoRua.value = dados.logradouro || '';
                if (campoBairro) campoBairro.value = dados.bairro || '';
                if (campoCidade) campoCidade.value = dados.localidade || '';
                if (campoUF) campoUF.value = dados.uf || '';

                // Selecionar Brasil no campo país
                selecionarPais('BR');

                if (dados.logradouro || dados.bairro) {
                    mostrarSucesso();
                }

                // Focar no campo número
                const campoNumero = document.getElementById('numero') || document.getElementById('clienteNumero');
                if (campoNumero) {
                    campoNumero.focus();
                }
            })
            .catch(error => {
                esconderLoading();
                mostrarErro('Erro ao buscar CEP. Tente novamente.');
                console.error('Erro na busca do CEP:', error);
            });
    }

    /**
     * Busca código postal internacional via zippopotam.us
     * Retorna: cidade e estado (NÃO retorna rua/bairro)
     */
    function buscarCEPZippopotamus(countryCode, postalCode) {
        if (!postalCode || postalCode.length < 3) {
            return;
        }

        mostrarLoading();

        fetch(`https://api.zippopotam.us/${countryCode}/${postalCode}`, {
            method: 'GET',
            mode: 'cors',
            cache: 'default'
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Código postal não encontrado');
                }
                return response.json();
            })
            .then(dados => {
                esconderLoading();

                if (!dados.places || dados.places.length === 0) {
                    mostrarErro('Código postal não encontrado');
                    limparCamposEndereco();
                    return;
                }

                const place = dados.places[0];

                // Preenche cidade e estado (zippopotam.us não retorna rua/bairro)
                const campoCidade = document.getElementById('cidade');
                const campoUF = document.getElementById('uf') || document.getElementById('estado');

                if (campoCidade) campoCidade.value = place['place name'] || '';
                if (campoUF) campoUF.value = place['state abbreviation'] || place['state'] || '';

                mostrarSucesso();

                // Focar no campo rua (usuário precisará preencher manualmente)
                const campoRua = document.getElementById('rua');
                if (campoRua) {
                    campoRua.focus();
                }
            })
            .catch(error => {
                esconderLoading();
                mostrarErro('Código postal não encontrado');
                console.error('Erro na busca do código postal:', error);
            });
    }

    /**
     * Seleciona um país no chosen-select
     */
    function selecionarPais(codigo) {
        if (campoPais && campoPais.value !== codigo) {
            campoPais.value = codigo;
            campoPais.dispatchEvent(new Event('change'));
            if (typeof jQuery !== 'undefined') {
                jQuery(campoPais).trigger('chosen:updated');
            }
        }
    }

    /**
     * Limpa os campos de endereço
     */
    function limparCamposEndereco() {
        const campoRua = document.getElementById('rua');
        const campoBairro = document.getElementById('bairro');
        const campoCidade = document.getElementById('cidade');
        const campoUF = document.getElementById('uf') || document.getElementById('estado');

        if (campoRua) campoRua.value = '';
        if (campoBairro) campoBairro.value = '';
        if (campoCidade) campoCidade.value = '';
        if (campoUF) campoUF.value = '';
    }


    // ========================================
    // FEEDBACK VISUAL
    // ========================================

    /**
     * Mostra indicador de loading no campo CEP
     */
    function mostrarLoading() {
        const cepField = document.getElementById('cep');
        cepField.style.backgroundColor = '#ffffcc';

        if (!document.getElementById('cep-loading')) {
            const parent = cepField.parentElement;
            parent.style.position = 'relative';

            const loadingIcon = document.createElement('i');
            loadingIcon.id = 'cep-loading';
            loadingIcon.className = 'fa fa-spinner fa-spin';
            loadingIcon.style.cssText = 'position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #4682B4;';

            parent.appendChild(loadingIcon);
        }
    }

    /**
     * Esconde o indicador de loading
     */
    function esconderLoading() {
        const cepField = document.getElementById('cep');
        const loadingIcon = document.getElementById('cep-loading');

        if (cepField) {
            cepField.style.backgroundColor = '';
        }

        if (loadingIcon) {
            loadingIcon.remove();
        }
    }

    /**
     * Mostra feedback de sucesso
     */
    function mostrarSucesso() {
        const cepField = document.getElementById('cep');
        cepField.style.backgroundColor = '#d4edda';

        setTimeout(function () {
            cepField.style.backgroundColor = '';
        }, 2000);
    }

    /**
     * Mostra mensagem de erro
     */
    function mostrarErro(mensagem) {
        const cepField = document.getElementById('cep');
        cepField.style.backgroundColor = '#f8d7da';

        let erroDiv = document.getElementById('cep-erro');

        if (!erroDiv) {
            erroDiv = document.createElement('div');
            erroDiv.id = 'cep-erro';
            erroDiv.style.cssText = 'color: #721c24; font-size: 12px; margin-top: 5px;';
            cepField.parentElement.appendChild(erroDiv);
        }

        erroDiv.textContent = mensagem;

        setTimeout(function () {
            cepField.style.backgroundColor = '';
            if (erroDiv) {
                erroDiv.remove();
            }
        }, 3000);
    }

});
