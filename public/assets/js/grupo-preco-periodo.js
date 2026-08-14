(function(global) {
    'use strict';

    const multiplicadores = {
        dia: 1,
        semana: 7,
        mes: 30,
        ano: 365
    };

    const planos = {
        KP: { tipo: 'diaria', campo: 'valor_plano_km_pago' },
        KMC: { tipo: 'km_controlado', campo: 'valor_plano_km_controlado' },
        KL: { tipo: 'km_livre', campo: 'valor_plano_km_livre' }
    };

    function obterMultiplicador(contagem) {
        return multiplicadores[contagem] || 1;
    }

    function calcularQuantidadeDias(quantidadePeriodos, contagem) {
        const periodos = Math.max(1, parseInt(quantidadePeriodos, 10) || 1);
        return periodos * obterMultiplicador(contagem);
    }

    function obterValorProgressivo(faixas, quantidadeDias) {
        let faixaAplicada = null;

        (Array.isArray(faixas) ? faixas : []).forEach(faixa => {
            const inicio = parseInt(faixa.dia_inicio, 10) || 0;
            const fim = faixa.dia_fim === null || faixa.dia_fim === undefined || faixa.dia_fim === ''
                ? Infinity
                : (parseInt(faixa.dia_fim, 10) || 0);

            if (quantidadeDias >= inicio
                && quantidadeDias <= fim
                && (!faixaAplicada || inicio > faixaAplicada.inicio)) {
                faixaAplicada = {
                    inicio,
                    valor: parseFloat(faixa.valor || 0)
                };
            }
        });

        return faixaAplicada ? faixaAplicada.valor : null;
    }

    function resolverValorDiario(payload, plano, quantidadeDias) {
        const configuracao = planos[String(plano || '').toUpperCase()];
        if (!configuracao) return 0;

        const valores = payload?.valores || payload || {};
        const precosDias = payload?.precos_dias || {};
        const valorProgressivo = obterValorProgressivo(precosDias[configuracao.tipo], quantidadeDias);

        return valorProgressivo !== null
            ? valorProgressivo
            : (parseFloat(valores[configuracao.campo] || 0) || 0);
    }

    function resolverValoresPorPeriodo(payload, quantidadePeriodos, contagem) {
        const valores = payload?.valores || payload || {};
        const multiplicador = obterMultiplicador(contagem);
        const quantidadeDias = calcularQuantidadeDias(quantidadePeriodos, contagem);

        return {
            valor_plano_km_pago: resolverValorDiario(payload, 'KP', quantidadeDias) * multiplicador,
            valor_plano_km_controlado: resolverValorDiario(payload, 'KMC', quantidadeDias) * multiplicador,
            valor_plano_km_livre: resolverValorDiario(payload, 'KL', quantidadeDias) * multiplicador,
            valor_seguro_carro: (parseFloat(valores.valor_seguro_carro || 0) || 0) * multiplicador,
            valor_seguro_terceiros: (parseFloat(valores.valor_seguro_terceiros || 0) || 0) * multiplicador,
            km_franquia: (parseInt(valores.km_franquia, 10) || 0) * multiplicador,
            quantidade_dias: quantidadeDias
        };
    }

    global.GrupoPrecoPeriodo = Object.freeze({
        calcularQuantidadeDias,
        obterValorProgressivo,
        resolverValorDiario,
        resolverValoresPorPeriodo
    });
})(typeof window !== 'undefined' ? window : globalThis);
