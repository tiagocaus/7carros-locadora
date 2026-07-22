/* ===========================================
   CUSTOM.JS - 7Carros Locadora
   =========================================== */

/* ---- SELETOR DE IDIOMA: grava cookie e recarrega sem query string ---- */
function setSiteLang(lang) {
    if (!lang) return false;
    document.cookie = 'lang=' + encodeURIComponent(lang) + ';path=/;max-age=2592000;samesite=Lax';
    try {
        var u = new URL(window.location.href);
        u.searchParams.delete('lang');
        var qs = u.searchParams.toString();
        window.location.href = u.pathname + (qs ? '?' + qs : '') + u.hash;
    } catch (e) {
        window.location.href = window.location.pathname;
    }
    return false;
}

/* ---- DADOS DAS FILIAIS (horarios, excecoes, feriados) ---- */
/* Populado pelo backend em includes/footer.php via window.FILIAIS_DATA.
   Estrutura: { id: { horarios: {0:[{abertura,fechamento}],...},
                      excecoes: {'Y-m-d':{tipo,abertura,fechamento,descricao}, ...} } } */
var filiaisHorarios = window.FILIAIS_DATA || {};

/* ---- FUNÇÕES DO COMPONENTE DATE+HORA ---- */

function getHojeDateString() {
    var d = new Date();
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
}

function gerarSlotsHora(periodos, step) {
    step = step || 30;
    var slots = [];
    for (var p = 0; p < periodos.length; p++) {
        var inicio = periodos[p].abertura.split(':');
        var fim = periodos[p].fechamento.split(':');
        var h = parseInt(inicio[0]);
        var m = parseInt(inicio[1]);
        var fh = parseInt(fim[0]);
        var fm = parseInt(fim[1]);

        while (h < fh || (h === fh && m < fm)) {
            var slot = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
            slots.push({ valor: slot, periodo: p });
            m += step;
            if (m >= 60) { h++; m -= 60; }
        }
    }
    return slots;
}

/**
 * Popular select de hora com slots disponíveis.
 * @param {jQuery} $select - select de hora
 * @param {string|number} filialId - ID da filial
 * @param {string} dataStr - data yyyy-mm-dd
 * @param {object} opts - { filtrarPassados: bool, aposHora: "HH:MM" }
 * @returns {string|null} - primeiro slot disponível (para auto-select)
 */
function popularSelectHora($select, filialId, dataStr, opts) {
    opts = opts || {};
    $select.empty();

    var filial = filiaisHorarios[filialId];
    if (!filial) {
        $select.append('<option value="" disabled selected>--:--</option>');
        $select.prop('disabled', true);
        return null;
    }

    // Excecoes vêm indexadas por data (Y-m-d) — lookup O(1)
    var excecao = (filial.excecoes && filial.excecoes[dataStr]) || null;

    if (excecao && excecao.tipo === 'fechado') {
        $select.append('<option value="" disabled selected>Fechado - ' + excecao.descricao + '</option>');
        $select.prop('disabled', true);
        return null;
    }

    // Pegar períodos do dia da semana
    var date = new Date(dataStr + 'T12:00:00');
    var diaSemana = date.getDay();
    var periodos = filial.horarios[diaSemana];

    if (!periodos || periodos.length === 0) {
        $select.append('<option value="" disabled selected>Fechado</option>');
        $select.prop('disabled', true);
        return null;
    }

    if (excecao && excecao.tipo === 'especial') {
        periodos = [{ abertura: excecao.abertura, fechamento: excecao.fechamento }];
    }

    var slots = gerarSlotsHora(periodos);

    // Regra 3: filtrar horários passados (quando data = hoje)
    var hoje = getHojeDateString();
    if (opts.filtrarPassados && dataStr === hoje) {
        var agora = new Date();
        var agoraMin = agora.getHours() * 60 + agora.getMinutes();
        slots = slots.filter(function (s) {
            var parts = s.valor.split(':');
            return parseInt(parts[0]) * 60 + parseInt(parts[1]) > agoraMin;
        });
    }

    // Regra 5: filtrar horários anteriores a uma hora específica
    if (opts.aposHora) {
        var refParts = opts.aposHora.split(':');
        var refMin = parseInt(refParts[0]) * 60 + parseInt(refParts[1]);
        slots = slots.filter(function (s) {
            var parts = s.valor.split(':');
            return parseInt(parts[0]) * 60 + parseInt(parts[1]) > refMin;
        });
    }

    if (slots.length === 0) {
        $select.append('<option value="" disabled selected>Sem horários</option>');
        $select.prop('disabled', true);
        return null;
    }

    $select.append('<option value="" disabled selected>Hora</option>');

    var primeiroSlot = slots[0].valor;
    var temMultiplosPeriodos = periodos.length > 1;

    if (temMultiplosPeriodos) {
        var periodoAtual = -1;
        var labels = ['Manhã', 'Tarde', 'Noite'];
        var $group = null;
        for (var s = 0; s < slots.length; s++) {
            if (slots[s].periodo !== periodoAtual) {
                periodoAtual = slots[s].periodo;
                $group = $('<optgroup label="' + (labels[periodoAtual] || 'Período ' + (periodoAtual + 1)) + '">');
                $select.append($group);
            }
            $group.append('<option value="' + slots[s].valor + '">' + slots[s].valor + '</option>');
        }
    } else {
        for (var s = 0; s < slots.length; s++) {
            $select.append('<option value="' + slots[s].valor + '">' + slots[s].valor + '</option>');
        }
    }

    $select.prop('disabled', false);
    return primeiroSlot;
}

/**
 * Retorna data yyyy-mm-dd somando N dias a uma data.
 */
function somarDias(dataStr, dias) {
    var d = new Date(dataStr + 'T12:00:00');
    d.setDate(d.getDate() + dias);
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
}

/**
 * Verificar se todos os campos do form de reserva estão preenchidos e habilitar/desabilitar Continuar.
 */
function verificarFormReserva() {
    var ok = $('#localRetirada').val() &&
             $('#dataSaida').val() &&
             $('#horaSaida').val() &&
             $('#localDevolucao').val() &&
             $('#dataPrevista').val() &&
             $('#horaDevolucao').val();
    $('form[name="reserva_top"] button[type="submit"]').prop('disabled', !ok);
    $('#btn_reserva_inner').prop('disabled', !ok);
}

/**
 * Configura o formulário de reserva completo (index.html).
 */
function inicializarFormReserva() {
    var devolucaoManual = false;

    var $locRet = $('#localRetirada');
    var $dataSaida = $('#dataSaida');
    var $horaSaida = $('#horaSaida');
    var $locDev = $('#localDevolucao');
    var $dataPrev = $('#dataPrevista');
    var $horaDev = $('#horaDevolucao');
    var $btnContinuar = $('form[name="reserva_top"] button[type="submit"]');

    if (!$locRet.length) return;

    // Value do select de local pode vir como "filialId" ou "filialId:localId"
    function locValFilialId($sel) {
        var v = String($sel.val() || '');
        if (!v) return null;
        var n = parseInt(v.split(':')[0]);
        return isNaN(n) ? null : n;
    }
    function locValLocalId($sel) {
        var v = String($sel.val() || '');
        var p = v.split(':');
        if (p.length < 2) return null;
        var n = parseInt(p[1]);
        return isNaN(n) ? null : n;
    }
    // Expoe pra uso fora da funcao (helpers multi-moeda / submit)
    window.__locValFilialId = locValFilialId;
    window.__locValLocalId = locValLocalId;

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
        });
    }

    function metodoPagamentoLabel(metodos) {
        metodos = metodos || [];
        if (metodos.indexOf('pix') !== -1) return 'Pagamento instantaneo';
        if (metodos.indexOf('boleto') !== -1) return 'Boleto bancario';
        if (metodos.indexOf('credit_card') !== -1) return 'Pagamento online';
        if (metodos.indexOf('debit_card') !== -1) return 'Cartao de debito';
        return 'Pagamento online';
    }

    function formasPagamentoSiteFilialAtual() {
        var filialId = locValFilialId($('#localRetirada'));
        if (!filialId || !window.FORMAS_PAGAMENTO_SITE) return [];
        return window.FORMAS_PAGAMENTO_SITE[filialId] || window.FORMAS_PAGAMENTO_SITE[String(filialId)] || [];
    }

    function renderFormasPagamentoSite() {
        var $box = $('#formasPagamentoSiteBox');
        if (!$box.length || !window.PAGAMENTO_ANTECIPADO_SITE) return;

        var formas = formasPagamentoSiteFilialAtual();
        var $lista = $('#formasPagamentoSiteLista');
        var $vazio = $('#formasPagamentoSiteVazio');
        var $feedback = $('#formasPagamentoSiteFeedback');
        $('#id_forma_pagamento_site').val('');
        $lista.empty();
        $feedback.hide();

        if (!formas.length) {
            $vazio.show();
            return;
        }

        $vazio.hide();
        formas.forEach(function (forma, index) {
            var gateways = (forma.gateways || []).map(function (g) { return g.nome; }).join(', ');
            var checked = formas.length === 1 ? ' checked' : '';
            var html = ''
                + '<div class="col-md-6 mb-3">'
                + '  <label class="border rounded p-3 d-block h-100" style="cursor:pointer;">'
                + '    <div class="custom-control custom-radio">'
                + '      <input type="radio" class="custom-control-input forma-pagamento-site-radio" name="forma_pagamento_site" id="forma_pagamento_site_' + forma.id + '" value="' + forma.id + '"' + checked + '>'
                + '      <span class="custom-control-label font-weight-bold">' + escapeHtml(forma.nome) + '</span>'
                + '    </div>'
                + '    <div class="small text-muted mt-2">' + escapeHtml(metodoPagamentoLabel(forma.metodos)) + '</div>'
                + (gateways ? '    <div class="small text-muted">Processado por ' + escapeHtml(gateways) + '</div>' : '')
                + '  </label>'
                + '</div>';
            $lista.append(html);
        });

        var selecionada = $('input[name="forma_pagamento_site"]:checked').val() || '';
        $('#id_forma_pagamento_site').val(selecionada);
    }
    window.__renderFormasPagamentoSite = renderFormasPagamentoSite;

    $(document).on('change', 'input[name="forma_pagamento_site"]', function () {
        $('#id_forma_pagamento_site').val($(this).val() || '');
        $('#formasPagamentoSiteFeedback').hide();
    });

    // Regra 9: botão começa desabilitado
    $btnContinuar.prop('disabled', true);

    // --- RETIRADA ---

    // Regra 1 + 8: Ao selecionar local de retirada
    $locRet.on('change', function () {
        var filialId = $(this).val();
        if (!filialId) return;
        renderFormasPagamentoSite();

        // Habilitar data de saída
        $dataSaida.prop('disabled', false).attr('min', getHojeDateString()).val('');
        $horaSaida.empty().append('<option value="" disabled selected>--:--</option>').prop('disabled', true);

        // Regra 1: auto-fill devolução se não foi mudada manualmente
        if (!devolucaoManual) {
            $locDev.val(filialId).trigger('change');
        }

        // Re-render precos dos servicos na moeda da nova filial e zera totais ja exibidos
        if (typeof window.__renderPrecosServicos === 'function') window.__renderPrecosServicos();
        if (typeof window.__resetResumoValores === 'function') window.__resetResumoValores();

        verificarFormReserva();
    });

    // Regra 2 + 3: Ao selecionar data de saída
    $dataSaida.on('change', function () {
        var filialId = locValFilialId($locRet);
        var dataStr = $(this).val();
        if (!filialId || !dataStr) return;

        var primeiroSlot = popularSelectHora($horaSaida, filialId, dataStr, {
            filtrarPassados: true
        });

        // Regra 2: auto-selecionar próximo slot 30min à frente (se hoje)
        if (dataStr === getHojeDateString() && primeiroSlot) {
            $horaSaida.val(primeiroSlot);
            $horaSaida.trigger('change');
        }

        // Regra 4: data de chegada mínima = data de saída
        $dataPrev.attr('min', dataStr);
        if ($dataPrev.val() && $dataPrev.val() < dataStr) {
            $dataPrev.val('');
            $horaDev.empty().append('<option value="" disabled selected>--:--</option>').prop('disabled', true);
        }

        verificarFormReserva();
    });

    // Regra 6: Ao selecionar hora de saída → auto-preencher chegada +1 dia
    $horaSaida.on('change', function () {
        var dataSaida = $dataSaida.val();
        var horaSaida = $(this).val();
        if (!dataSaida || !horaSaida) { verificarFormReserva(); return; }

        // Auto-fill: chegada = saída + 1 dia
        var dataChegada = somarDias(dataSaida, 1);
        var filialDevId = $locDev.val();

        if (filialDevId) {
            $dataPrev.val(dataChegada);
            var slotDisp = popularSelectHora($horaDev, filialDevId, dataChegada, {});

            // Tentar selecionar a mesma hora de saída na chegada
            if ($horaDev.find('option[value="' + horaSaida + '"]').length) {
                $horaDev.val(horaSaida);
            } else if (slotDisp) {
                $horaDev.val(slotDisp);
            }
        }

        verificarFormReserva();
    });

    // --- DEVOLUÇÃO ---

    // Regra 1: marcar como manual se o cliente mudar
    $locDev.on('change', function (e, auto) {
        if (!auto) devolucaoManual = true;

        var filialId = $(this).val();
        if (!filialId) return;

        // Regra 8: limpar data/hora de chegada ao trocar filial
        $dataPrev.prop('disabled', false);
        if ($dataSaida.val()) {
            $dataPrev.attr('min', $dataSaida.val());
        } else {
            $dataPrev.attr('min', getHojeDateString());
        }
        $dataPrev.val('');
        $horaDev.empty().append('<option value="" disabled selected>--:--</option>').prop('disabled', true);

        verificarFormReserva();
    });

    // Fix: trigger do auto-fill não marca como manual
    var origTrigger = $locDev.data('autoTriggered');
    $locRet.on('change', function () {
        if (!devolucaoManual) {
            $locDev.triggerHandler('change');
        }
    });

    // Regra 5: Ao selecionar data de chegada
    $dataPrev.on('change', function () {
        var filialId = $locDev.val();
        var dataStr = $(this).val();
        if (!filialId || !dataStr) return;

        var opts = { filtrarPassados: true };

        // Regra 5: se mesma data de saída, só horários após hora de saída
        if (dataStr === $dataSaida.val() && $horaSaida.val()) {
            opts.aposHora = $horaSaida.val();
        }

        popularSelectHora($horaDev, filialId, dataStr, opts);
        verificarFormReserva();
    });

    $horaDev.on('change', verificarFormReserva);

    // Pré-preenchimento via query string (vindo do form do index.php)
    hidratarFormDeURL();

    function hidratarFormDeURL() {
        var p = window.RESERVA_PREFILL;
        if (!p) return;

        // Evita auto-fill da devolução (temos o valor real)
        devolucaoManual = true;

        // Os <option> / <input> já vêm do PHP selecionados ou preenchidos.
        // Evitamos .trigger('change') nos campos principais porque os handlers
        // são destrutivos (limpam dataSaida/horaSaida). Só populamos os selects
        // dinâmicos de hora e avançamos pra step 2.

        $dataSaida.prop('disabled', false);
        $dataPrev.prop('disabled', false).attr('min', p.dataSaida);

        // Mantém a regra de negócio: não reservar hora que já passou.
        // Se o usuário ficou inativo e a hora escolhida expirou, avisa e mantém step 1.
        popularSelectHora($horaSaida, p.localRetirada, p.dataSaida, { filtrarPassados: true });
        $horaSaida.val(p.horaSaida).prop('disabled', false);
        var horaSaiValida = $horaSaida.val() === p.horaSaida;

        var optsDev = { filtrarPassados: true };
        if (p.dataPrevista === p.dataSaida) {
            optsDev.aposHora = p.horaSaida;
        }
        popularSelectHora($horaDev, p.localDevolucao, p.dataPrevista, optsDev);
        $horaDev.val(p.horaDevolucao).prop('disabled', false);
        var horaDevValida = $horaDev.val() === p.horaDevolucao;

        verificarFormReserva();

        if (!horaSaiValida || !horaDevValida) {
            alert('O horário selecionado já passou. Por favor, escolha outro horário.');
            return; // mantém na step 1 pra usuário reescolher
        }

        // Tudo válido: avança pra step 2 ("Escolha seu veículo").
        // setTimeout garante que o handler do #btn_reserva_inner (registrado
        // depois de inicializarFormReserva) já está ligado quando disparamos.
        if ($horaDev.val()) {
            setTimeout(function () {
                $('#btn_reserva_inner').trigger('click');
            }, 0);
        }
    }
}


$(function () {

    // ---- Menu mobile ----
    function navbarScrollbarWidth() {
        return window.innerWidth - document.documentElement.clientWidth;
    }

    var $nav = $('#navbarNav');
    var $backdrop = $('#navbarSlideBackdrop');
    var $toggler = $('.navbar-toggler');

    function closeNavbarMobile() {
        $nav.removeClass('show');
        $toggler.attr('aria-expanded', 'false');
        $('body').removeClass('navbar-menu-open').css('padding-right', '');
        if ($backdrop.length) {
            $backdrop.attr('aria-hidden', 'true');
        }
    }

    function openNavbarMobile() {
        $('body').addClass('navbar-menu-open');
        var sbw = navbarScrollbarWidth();
        if (sbw > 0) {
            $('body').css('padding-right', sbw + 'px');
        }
        if ($backdrop.length) {
            $backdrop.attr('aria-hidden', 'false');
        }
        $toggler.attr('aria-expanded', 'true');
    }

    if ($nav.length) {
        $toggler.on('click', function (e) {
            if (!window.matchMedia('(max-width: 991.98px)').matches) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            if ($nav.hasClass('show')) {
                closeNavbarMobile();
            } else {
                $nav.addClass('show');
                openNavbarMobile();
            }
        });

        $backdrop.on('click', closeNavbarMobile);

        $nav.find('.nav-link').on('click', function () {
            if (window.matchMedia('(max-width: 991.98px)').matches) closeNavbarMobile();
        });

        $(document).on('keydown.navbarMenu', function (e) {
            if (e.key === 'Escape' && $nav.hasClass('show')) closeNavbarMobile();
        });

        $(window).on('resize.navbarMenu', function () {
            if (window.matchMedia('(min-width: 992px)').matches && $nav.hasClass('show')) closeNavbarMobile();
        });
    }

    // ---- Carousel Bootstrap ----
    $('.carousel').carousel({ interval: 5000 });

    // ---- Slick Vehicles ----
    var $vlist = $(".vehicles-list");
    var totalItems = $vlist.find(".item").length;

    if (totalItems >= 3) {
        if (totalItems === 3) {
            $vlist.find(".item").each(function () {
                $vlist.append($(this).clone());
            });
        }

        function markCenter() {
            $vlist.find('.slick-slide').removeClass('is-center');
            var $active = $vlist.find('.slick-active');
            if ($active.length >= 3) {
                $active.eq(1).addClass('is-center');
            } else if ($active.length === 1) {
                $active.eq(0).addClass('is-center');
            }
        }

        $vlist.on('init reInit afterChange', markCenter);

        $vlist.slick({
            slidesToShow: 3,
            slidesToScroll: 1,
            autoplay: true,
            autoplaySpeed: 2500,
            speed: 600,
            infinite: true,
            arrows: true,
            prevArrow: '<button type="button" class="slick-prev slick-arrow"></button>',
            nextArrow: '<button type="button" class="slick-next slick-arrow"></button>',
            responsive: [{
                breakpoint: 768,
                settings: { slidesToShow: 1, adaptiveHeight: true }
            }]
        });

    } else if (totalItems === 2) {
        $vlist.slick({
            slidesToShow: 2,
            slidesToScroll: 1,
            autoplay: true,
            autoplaySpeed: 3000,
            infinite: true,
            responsive: [{
                breakpoint: 768,
                settings: { slidesToShow: 1, adaptiveHeight: true }
            }]
        });
    }

    // ---- Componente de reserva: date+hora ----
    inicializarFormReserva();

    // Form topo submete nativamente via GET para reserva.php (action definido no index.php).
    // Campos obrigatórios já são validados por verificarFormReserva() que desabilita o botão.

    var currentTab = 0;
    showTab(currentTab);

    // Botão "Continuar" dentro da reserva (step 1)
    var ultimaSubmissaoStep1 = null;
    $(document).on('click', '#btn_reserva_inner', function () {
        var loc = $('#localRetirada').val();
        var dat = $('#dataSaida').val();
        var hora = $('#horaSaida').val();
        if (!loc || !dat || !hora) return;

        // Se o usuario mudou filial/datas/horas, reseta a selecao do passo 2
        // (precos e disponibilidade podem ter mudado)
        var snapshot = [loc, dat, hora, $('#localDevolucao').val(), $('#dataPrevista').val(), $('#horaDevolucao').val()].join('|');
        if (ultimaSubmissaoStep1 !== null && ultimaSubmissaoStep1 !== snapshot) {
            resetResumoValores();
            renderPrecosServicos();
        }
        ultimaSubmissaoStep1 = snapshot;

        // Preenche resumo
        $('.resumo-retirada-local').text($('#localRetirada option:selected').text());
        $('.resumo-devolucao-local').text($('#localDevolucao option:selected').text());
        $('.resumo-retirada-data').text(formatDateBR(dat) + ' ' + hora);
        $('.resumo-devolucao-data').text(formatDateBR($('#dataPrevista').val()) + ' ' + ($('#horaDevolucao').val() || ''));

        // Calcula dias
        var d1 = new Date(dat + 'T12:00:00');
        var d2 = new Date($('#dataPrevista').val() + 'T12:00:00');
        if (d1 && d2) {
            var dias = Math.max(1, Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24)));
            $('.dias').text(dias);
            $('#dias').val(dias);
            // Se usuario mudou datas apos ter marcado plano/adicionais, recomputar totais com dias novos
            if (window.__precoPlanoAtual) {
                var subtotalAtual = parseFloat(window.__subtotalPlanoAtual || (window.__precoPlanoAtual * dias));
                $('.plano-soma').text(formatarMoeda(subtotalAtual));
                renderAdicionais();
                calcTotal();
            }
        }

        atualizarPlanosDisponiveis();

        // Verifica disponibilidade dos grupos para o periodo/filial informados
        carregarDisponibilidadeGrupos();

        nextTab();
    });

    // Consulta o backend e marca cada grupo como disponivel/esgotado
    window.__disponibilidadeGrupos = {}; // { idGrupo: true/false }
    window.__precosPeriodoGrupos = {}; // { idGrupo: { plano: { valor_dia, subtotal } } }
    window.__cotacaoPeriodoCarregando = false;
    function carregarDisponibilidadeGrupos() {
        var filialId = window.__locValFilialId ? window.__locValFilialId($('#localRetirada')) : null;
        if (!filialId) return;
        window.__precosPeriodoGrupos = {};
        window.__cotacaoPeriodoCarregando = true;
        atualizarPlanosDisponiveis();
        var params = {
            id_matriz_filial: filialId,
            data_saida:       $('#dataSaida').val() || '',
            hora_saida:       $('#horaSaida').val() || '',
            data_prevista:    $('#dataPrevista').val() || '',
            hora_devolucao:   $('#horaDevolucao').val() || '',
        };
        $.ajax({
            url: 'ajax-disponibilidade.php',
            method: 'GET',
            data: params,
            dataType: 'json'
        }).done(function (resp) {
            if (!resp || !resp.success) return;
            window.__disponibilidadeGrupos = resp.grupos || {};
            window.__precosPeriodoGrupos = resp.precos || {};
            window.__cotacaoPeriodoCarregando = false;
            atualizarPlanosDisponiveis();
            aplicarDisponibilidadeNosBotoes();
        }).fail(function () {
            window.__cotacaoPeriodoCarregando = false;
            window.__disponibilidadeGrupos = {};
            atualizarPlanosDisponiveis();
            aplicarDisponibilidadeNosBotoes();
        });
    }

    function aplicarDisponibilidadeNosBotoes() {
        var i18n = window.I18N_WEBSITE || {};
        var txtEsgotado = i18n.btn_esgotado || 'Esgotado';
        var txtSelecionePlano = i18n.btn_selecione_plano || 'Selecione o plano';
        var map = window.__disponibilidadeGrupos || {};
        $('.btnSelecionarGrupo').each(function () {
            var $btn = $(this);
            var idGrupo = parseInt($btn.data('id-grupo')) || parseInt($btn.data('id'));
            var disponivel = !!map[idGrupo];
            $btn.data('esgotado', !disponivel);
            if (!disponivel) {
                $btn.prop('disabled', true).text(txtEsgotado);
            } else {
                // Se plano ainda nao foi marcado nesse grupo, mostrar "Selecione o plano"
                var planoMarcado = $btn.closest('.reserva-grupo-card').find('.plano-grupo:checked').length > 0;
                var planosDisponiveis = parseInt($btn.closest('.reserva-plano-col').data('planos-disponiveis')) || 0;
                if (!planoMarcado || planosDisponiveis === 0) {
                    $btn.prop('disabled', true).text(txtSelecionePlano);
                }
            }
        });
    }
    window.__aplicarDisponibilidadeNosBotoes = aplicarDisponibilidadeNosBotoes;

    // Botão "Proximo" genérico
    $(document).on('click', '.botao_', function () {
        nextTab();
    });

    // Progressbar: clicar em um step já visitado volta pra ele.
    // Steps ainda não visitados (sem classe active) são ignorados.
    $(document).on('click', '.progressbar li', function () {
        var $li = $(this);
        if (!$li.hasClass('active')) return;
        var idx = $('.progressbar li').index(this);
        showTab(idx);
        window.scrollTo(0, 0);
    });

    // ---- Helpers multi-moeda ----
    // Config da filial de retirada (fonte de verdade pra pricing e formatacao)
    function filialAtiva() {
        var idRet = window.__locValFilialId ? window.__locValFilialId($('#localRetirada')) : null;
        return (window.FILIAIS_DATA && window.FILIAIS_DATA[idRet]) || null;
    }
    function formatarMoeda(valor) {
        var f = filialAtiva();
        var sym = f ? (f.simbolo_moeda || 'R$') : 'R$';
        var dec = f ? (f.separador_decimal || ',') : ',';
        var mil = f ? (f.separador_milhar || '.') : '.';
        var num = (parseFloat(valor) || 0).toFixed(2);
        var parts = num.split('.');
        parts[0] = parts[0].replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1' + mil);
        return sym + ' ' + parts.join(dec);
    }
    function precosGrupoAtual(idGrupo) {
        var f = filialAtiva();
        if (!f || !f.precos_grupos) return null;
        return f.precos_grupos[idGrupo] || null;
    }
    function seguroEstaSelecionado(tipo) {
        if (tipo === 'carro') {
            return !!window.SEGURO_CARRO_OBRIGATORIO || $('.seguroCarro input[type="checkbox"]:checked').length > 0;
        }
        return !!window.SEGURO_TERCEIROS_OBRIGATORIO || $('.seguroTerceiro input[type="checkbox"]:checked').length > 0;
    }
    function aplicarObrigatoriedadeSeguros() {
        var i18n = window.I18N_WEBSITE || {};
        [
            { selector: '.seguroCarro', obrigatorio: !!window.SEGURO_CARRO_OBRIGATORIO },
            { selector: '.seguroTerceiro', obrigatorio: !!window.SEGURO_TERCEIROS_OBRIGATORIO }
        ].forEach(function (seguro) {
            var $row = $(seguro.selector);
            var $input = $row.find('input[type="checkbox"]');
            $input.attr('data-obrigatorio', seguro.obrigatorio ? '1' : '0')
                .attr('aria-disabled', seguro.obrigatorio ? 'true' : 'false');
            $row.toggleClass('obrigatorio', seguro.obrigatorio);
            $row.find('.addCheck').text(seguro.obrigatorio ? (i18n.obrigatorio || 'Obrigatorio') : (i18n.adicionar || 'Adicionar'));
            if (seguro.obrigatorio) $input.prop('checked', true);
        });
    }
    function diasReservaAtual() {
        return parseInt($('#dias').val()) || 1;
    }
    function tipoPlanoPreco(plano) {
        var mapa = {
            KML: 'km_livre',
            KMC: 'km_controlado',
            DIA: 'diaria'
        };
        return mapa[plano] || 'diaria';
    }
    function campoBasePlano(plano) {
        var mapa = {
            KML: 'valor_plano_km_livre',
            KMC: 'valor_plano_km_controlado',
            DIA: 'valor_plano_km_pago'
        };
        return mapa[plano] || 'valor_plano_km_pago';
    }
    function precoEfetivoPlano(idGrupo, plano) {
        if (window.__cotacaoPeriodoCarregando) return 0;
        var precosPeriodo = window.__precosPeriodoGrupos || {};
        var precosGrupoPeriodo = precosPeriodo[idGrupo] || precosPeriodo[String(idGrupo)] || {};
        var precoPeriodo = precosGrupoPeriodo[plano] || null;
        if (precoPeriodo && typeof precoPeriodo.valor_dia !== 'undefined') {
            return parseFloat(precoPeriodo.valor_dia) || 0;
        }

        var dias = diasReservaAtual();
        var filial = filialAtiva();
        var tipo = tipoPlanoPreco(plano);
        var gruposFaixas = (filial && filial.precos_dias_grupos) ? filial.precos_dias_grupos : {};
        var faixasGrupo = gruposFaixas[idGrupo] || gruposFaixas[String(idGrupo)] || {};
        var faixas = faixasGrupo[tipo] || [];

        for (var i = 0; i < faixas.length; i++) {
            var faixa = faixas[i] || {};
            var inicio = parseInt(faixa.dia_inicio) || 0;
            var fim = faixa.dia_fim === null || typeof faixa.dia_fim === 'undefined' ? null : parseInt(faixa.dia_fim);
            if (inicio > 0 && dias >= inicio && (fim === null || dias <= fim)) {
                return parseFloat(faixa.valor) || 0;
            }
        }

        var p = precosGrupoAtual(idGrupo) || {};
        return parseFloat(p[campoBasePlano(plano)] || 0);
    }
    function subtotalEfetivoPlano(idGrupo, plano) {
        var precosPeriodo = window.__precosPeriodoGrupos || {};
        var precosGrupoPeriodo = precosPeriodo[idGrupo] || precosPeriodo[String(idGrupo)] || {};
        var precoPeriodo = precosGrupoPeriodo[plano] || null;
        if (precoPeriodo && typeof precoPeriodo.subtotal !== 'undefined') {
            return parseFloat(precoPeriodo.subtotal) || 0;
        }
        return precoEfetivoPlano(idGrupo, plano) * diasReservaAtual();
    }
    function atualizarPlanosDisponiveis() {
        $('.reserva-plano-col').each(function () {
            var $col = $(this);
            var $row = $col.closest('.reserva-grupo-card');
            var idGrupo = parseInt($row.find('.btnSelecionarGrupo').data('id-grupo')) || parseInt($row.find('.btnSelecionarGrupo').data('id'));
            var disponiveis = 0;

            $col.find('.plano-grupo').each(function () {
                var $input = $(this);
                var parts = String($input.val() || '').split('|');
                var plano = parts[0];
                var preco = precoEfetivoPlano(idGrupo, plano);
                var mostrar = preco > 0;
                var $label = $col.find('label[for="' + this.id + '"]');

                $input.prop('disabled', !mostrar).toggle(mostrar);
                $label.toggle(mostrar);

                if (!mostrar && $input.is(':checked')) {
                    $input.prop('checked', false);
                }
                if (mostrar) {
                    disponiveis++;
                }
            });

            $col.data('planos-disponiveis', disponiveis);
        });
    }
    function diariaSufixo() {
        return (window.I18N_WEBSITE && window.I18N_WEBSITE.diaria) || 'diaria';
    }
    // Valor do servico na moeda da filial ativa. Taxas MON usam valores_servicos[id];
    // taxas POR (%) usam o valor global (data-valor-global).
    function servicoValorAtual($el) {
        var id = parseInt($el.data('servico-id'));
        var tipo = String($el.data('tipo-valor') || 'MON');
        if (tipo === 'POR') {
            return parseFloat($el.data('valor-global') || 0);
        }
        var f = filialAtiva();
        if (!f || !f.valores_servicos) return 0;
        return parseFloat(f.valores_servicos[id] || 0);
    }
    function renderPrecosServicos() {
        $('.servico-preco').each(function () {
            var $el = $(this);
            var tipo = String($el.data('tipo-valor') || 'MON');
            var valor = servicoValorAtual($el);
            var $span = $el.find('.servico-valor');
            if (tipo === 'POR') {
                $span.text(valor.toFixed(2).replace('.', ',') + '% /' + diariaSufixo());
            } else {
                $span.text(formatarMoeda(valor) + ' /' + diariaSufixo());
            }
        });
        renderPrecosSeguros();
    }
    function renderPrecosSeguros() {
        var precos = precosGrupoAtual(grupoSelecionadoId()) || {};
        var i18n = window.I18N_WEBSITE || {};
        [
            { selector: '.seguroCarro', valor: parseFloat(precos.valor_seguro_carro || 0) },
            { selector: '.seguroTerceiro', valor: parseFloat(precos.valor_seguro_terceiros || 0) }
        ].forEach(function (seguro) {
            var texto = seguro.valor > 0 ? formatarMoeda(seguro.valor) : (i18n.gratis || 'Gratis');
            $(seguro.selector).find('.seguro-valor').text(texto + ' /' + diariaSufixo());
        });
    }
    function resetResumoValores() {
        $('.reserva-preco span').text('selecione');
        $('.plano-valor, .plano-soma, .seg_carro_valor, .seg_terceiros_valor, .resumo-plano').text('');
        $('.resumo-adicionais').empty();
        $('.total-geral span').text(formatarMoeda(0).replace((filialAtiva() || {}).simbolo_moeda || 'R$', '').trim());
        window.__precoPlanoAtual = 0;
        window.__subtotalPlanoAtual = 0;
        window.__grupoSelecionadoId = null;
        window.__planoSelecionado = '';
        // Desmarca planos selecionados e volta botoes ao estado inicial (filial/datas podem mudar precos e disponibilidade)
        $('.plano-grupo').prop('checked', false);
        var i18nReset = window.I18N_WEBSITE || {};
        $('.btnSelecionarGrupo').prop('disabled', true).removeData('esgotado').text(i18nReset.btn_selecione_plano || 'Selecione o plano');
        atualizarPlanosDisponiveis();
        // Reaplica a ultima disponibilidade conhecida (marca 'Esgotado' nos grupos sem veiculo livre)
        if (typeof window.__aplicarDisponibilidadeNosBotoes === 'function') {
            window.__aplicarDisponibilidadeNosBotoes();
        }
        // Desmarca servicos adicionais e seguros selecionados (precos mudaram)
        $('#itens-adicionais input[type="checkbox"]').prop('checked', false);
        $('.seguroCarro input[type="checkbox"], .seguroTerceiro input[type="checkbox"]').prop('checked', false);
        aplicarObrigatoriedadeSeguros();
    }
    // Renderiza os servicos adicionais marcados no resumo lateral (ambas as tabs que tem .resumo-adicionais)
    function renderAdicionais() {
        var dias = parseInt($('#dias').val()) || 1;
        var planoValor = parseFloat(window.__precoPlanoAtual || 0);
        var planoSubtotal = parseFloat(window.__subtotalPlanoAtual || (planoValor * dias));
        // monta HTML uma vez e replica para os containers (um em cada tab)
        var linhas = [];
        $('#itens-adicionais .itens-adicionais').each(function () {
            var $row = $(this);
            var $chk = $row.find('input[type="checkbox"]');
            if (!$chk.is(':checked')) return;
            var $preco = $row.find('.servico-preco');
            var nome = String($preco.data('servico-nome') || $row.find('.nome strong').text() || '').trim();
            var tipo = String($preco.data('tipo-valor') || 'MON');
            var baseCalc = String($preco.data('base-calculo') || 'PER');
            var total, label;
            if (tipo === 'POR') {
                var pct = parseFloat($preco.data('valor-global') || 0);
                var baseValor = baseCalc === 'FIX' ? planoValor : planoSubtotal;
                total = baseValor * pct / 100;
                label = nome + ' (' + pct.toFixed(2).replace('.', ',') + '%)';
            } else {
                var valorDia = servicoValorAtual($preco);
                total = baseCalc === 'FIX' ? valorDia : valorDia * dias;
                label = baseCalc === 'FIX' ? nome : (nome + ' (' + dias + ' x ' + formatarMoeda(valorDia) + ')');
            }
            linhas.push({ label: label, total: total });
        });
        var precos = precosGrupoAtual(grupoSelecionadoId()) || {};
        [
            {
                selecionado: seguroEstaSelecionado('carro'),
                nome: (window.I18N_WEBSITE || {}).seguro_veiculo || 'Seguro do veiculo',
                valorDia: parseFloat(precos.valor_seguro_carro || 0)
            },
            {
                selecionado: seguroEstaSelecionado('terceiros'),
                nome: (window.I18N_WEBSITE || {}).seguro_terceiros || 'Seguro para terceiros',
                valorDia: parseFloat(precos.valor_seguro_terceiros || 0)
            }
        ].forEach(function (seguro) {
            if (!seguro.selecionado) return;
            linhas.push({
                label: seguro.nome + ' (' + dias + ' x ' + formatarMoeda(seguro.valorDia) + ')',
                total: seguro.valorDia * dias
            });
        });
        $('.resumo-adicionais').each(function () {
            var $c = $(this).empty();
            linhas.forEach(function (li) {
                var $r = $('<div class="row pb-2"></div>');
                $('<div class="col-sm-8 label"></div>').text(li.label).appendTo($r);
                $('<div class="col-sm-4 text-right label somar"></div>').text(formatarMoeda(li.total)).appendTo($r);
                $c.append($r);
            });
        });
    }
    window.__renderAdicionais = renderAdicionais;

    // Ao marcar/desmarcar um servico adicional, renderiza no resumo e recalcula total
    $(document).on('change', '#itens-adicionais input[type="checkbox"]', function () {
        renderAdicionais();
        calcTotal();
    });
    // Expoe pros handlers registrados fora deste escopo (ex: inicializarFormReserva)
    window.__renderPrecosServicos = renderPrecosServicos;
    window.__resetResumoValores = resetResumoValores;

    // Seleção de plano de locação
    $(document).on('change', '.plano-grupo', function () {
        var val = $(this).val();
        var parts = val.split('|');
        var plano = parts[0];
        var idGrupo = parseInt(parts[1]);
        var $card = $(this).closest('.reserva-grupo-card');
        var $btn = $card.find('.btnSelecionarGrupo');
        var i18n = window.I18N_WEBSITE || {};

        var precoNum = precoEfetivoPlano(idGrupo, plano);
        if (precoNum <= 0) {
            $(this).prop('checked', false);
            $card.find('.reserva-preco span').text('selecione');
            $btn.prop('disabled', true).text(i18n.btn_selecione_plano || 'Selecione o plano');
            return;
        }

        $card.find('.reserva-preco span').text(formatarMoeda(precoNum) + ' /' + diariaSufixo());

        if ($btn.data('esgotado') === true) {
            // Grupo indisponivel no periodo: mantem 'Esgotado' e bloqueia selecao
            $btn.prop('disabled', true).text(i18n.btn_esgotado || 'Esgotado');
        } else {
            $btn.prop('disabled', false).text(i18n.btn_selecionar || 'Selecionar');
        }
    });

    // Botão selecionar grupo
    $(document).on('click', '.btnSelecionarGrupo:not(:disabled)', function () {
        var $card = $(this).closest('.reserva-grupo-card');
        var $plano = $card.find('.plano-grupo:checked');
        if (!$plano.length) return;

        var parts = String($plano.val() || '').split('|');
        var plano = parts[0];
        var idGrupo = parseInt(parts[1]) || null;
        var precoNum = idGrupo ? precoEfetivoPlano(idGrupo, plano) : 0;
        var subtotalPlano = idGrupo ? subtotalEfetivoPlano(idGrupo, plano) : 0;
        if (!idGrupo || precoNum <= 0) return;

        var i18n = window.I18N_WEBSITE || {};
        var planoNome = {
            'KML': i18n.plano_km_livre || 'Km Livre',
            'KMC': i18n.plano_km_controlado || 'Km Controlado',
            'DIA': i18n.plano_km_pago || 'Km pago'
        };
        var dias = parseInt($('#dias').val()) || 1;

        window.__grupoSelecionadoId = idGrupo;
        window.__planoSelecionado = plano;
        window.__precoPlanoAtual = precoNum;
        window.__subtotalPlanoAtual = subtotalPlano;
        $('.resumo-plano').text(planoNome[plano] || plano);
        $('.plano-valor').text(formatarMoeda(precoNum));
        $('.plano-soma').text(formatarMoeda(subtotalPlano));
        renderPrecosSeguros();
        renderAdicionais();
        calcTotal();
        nextTab();
    });

    // Helper: retorna o grupo confirmado pelo botao "Selecionar" do card.
    function grupoSelecionadoId() {
        return parseInt(window.__grupoSelecionadoId) || null;
    }

    window.__promocaoAplicada = null;
    window.__promocaoAssinaturaTestada = '';
    var __promoTimer = null;
    var __validandoPromo = false;

    function contextoPromocao() {
        var servicos = [];
        $('#itens-adicionais input[type="checkbox"]:checked').each(function () {
            var id = parseInt(String($(this).attr('name') || '').replace('servico_', ''));
            if (id) servicos.push(id);
        });
        servicos.sort(function (a, b) { return a - b; });
        return {
            filial_id: window.__locValFilialId ? window.__locValFilialId($('#localRetirada')) : 0,
            grupo_id: grupoSelecionadoId(),
            plano: String(window.__planoSelecionado || ''),
            dias: parseInt($('#dias').val()) || 1,
            data_inicio: $('#dataSaida').val() || '',
            servicos: servicos,
            seguro_carro: seguroEstaSelecionado('carro'),
            seguro_terceiros: seguroEstaSelecionado('terceiros')
        };
    }

    function assinaturaContextoPromocao(ctx, codigo) {
        return [codigo, ctx.filial_id, ctx.grupo_id, ctx.plano, ctx.dias, ctx.data_inicio, ctx.servicos.join(','), ctx.seguro_carro ? 1 : 0, ctx.seguro_terceiros ? 1 : 0].join('|');
    }

    async function validarPromocaoSite() {
        var codigo = String($('#promocao_codigo').val() || '').trim().toUpperCase();
        var $feedback = $('#promocaoFeedback');
        if (!codigo) {
            window.__promocaoAplicada = null;
            window.__promocaoAssinaturaTestada = '';
            $('.promo-resumo-linha').hide();
            $feedback.hide();
            calcTotal();
            return false;
        }

        var ctx = contextoPromocao();
        if (!ctx.filial_id || !ctx.grupo_id || !ctx.plano) {
            $feedback.removeClass('text-success').addClass('text-danger').text('Selecione filial, período e veículo antes de aplicar o código.').show();
            return false;
        }

        __validandoPromo = true;
        var assinaturaTentativa = assinaturaContextoPromocao(ctx, codigo);
        $('#btnAplicarPromocao').prop('disabled', true);
        try {
            ctx.promocao_codigo = codigo;
            var resp = await $.ajax({
                url: 'ajax-promocao.php',
                method: 'POST',
                contentType: 'application/json; charset=utf-8',
                data: JSON.stringify(ctx),
                dataType: 'json'
            });
            if (!resp || !resp.success) throw new Error(resp && resp.message ? resp.message : 'Código promocional inválido.');

            var aplicacao = resp.data.promocao;
            aplicacao.assinatura = assinaturaContextoPromocao(ctx, aplicacao.codigo);
            window.__promocaoAplicada = aplicacao;
            window.__promocaoAssinaturaTestada = aplicacao.assinatura;
            $('#promocao_codigo').val(aplicacao.codigo);
            $('.promo-desconto-valor').text(formatarMoeda(aplicacao.valor_desconto));
            $('.promo-resumo-linha').show();
            $feedback.removeClass('text-danger').addClass('text-success').text(aplicacao.nome + ': -' + formatarMoeda(aplicacao.valor_desconto)).show();
            calcTotal();
            return true;
        } catch (error) {
            window.__promocaoAplicada = null;
            window.__promocaoAssinaturaTestada = assinaturaTentativa;
            $('.promo-resumo-linha').hide();
            $feedback.removeClass('text-success').addClass('text-danger').text(error.message || 'Código promocional inválido.').show();
            calcTotal();
            return false;
        } finally {
            __validandoPromo = false;
            $('#btnAplicarPromocao').prop('disabled', false);
        }
    }

    function agendarRevalidacaoPromocao() {
        var codigo = String($('#promocao_codigo').val() || '').trim().toUpperCase();
        if (!codigo || __validandoPromo) return;
        var assinatura = assinaturaContextoPromocao(contextoPromocao(), codigo);
        if (window.__promocaoAplicada && window.__promocaoAplicada.assinatura === assinatura) return;
        if (window.__promocaoAssinaturaTestada === assinatura) return;
        clearTimeout(__promoTimer);
        __promoTimer = setTimeout(validarPromocaoSite, 250);
    }
    window.__agendarRevalidacaoPromocao = agendarRevalidacaoPromocao;

    $(document).on('click', '#btnAplicarPromocao', validarPromocaoSite);
    $(document).on('input', '#promocao_codigo', function () {
        this.value = this.value.toUpperCase();
        window.__promocaoAplicada = null;
        window.__promocaoAssinaturaTestada = '';
        $('.promo-resumo-linha').hide();
        $('#promocaoFeedback').hide();
        calcTotal();
    });

    // O bloqueio no browser melhora a UX; a obrigatoriedade real e aplicada no backend.
    $(document).on('click keydown', '.seguro-item input[data-obrigatorio="1"]', function (event) {
        if (event.type === 'click' || event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            $(this).prop('checked', true);
        }
    });
    $(document).on('change', '.seguro-item input[type="checkbox"]', function () {
        aplicarObrigatoriedadeSeguros();
        renderAdicionais();
        calcTotal();
    });

    // ---- Check "eh cliente?" + bloco de login ----
    // Ao sair do campo CPF/CNPJ: consulta se o documento ja eh cliente.
    // Se sim, mostra bloco de login (senha + Entrar + Esqueci). Se nao, segue pre-cadastro normal.
    var __ultimoDocConsultado = null;
    $(document).on('blur', '#cpf_cnpj', function () {
        var doc = ($(this).val() || '').replace(/\D/g, '');
        if (doc.length < 11 || doc === __ultimoDocConsultado) return;
        __ultimoDocConsultado = doc;
        $.ajax({
            url: 'ajax-cliente-existe.php',
            method: 'GET',
            data: { documento: doc },
            dataType: 'json'
        }).done(function (resp) {
            if (resp && resp.success && resp.existe) {
                $('#loginFeedback').hide().text('');
                $('#loginSenha').val('');
                $('#modalLoginCliente').modal('show');
            }
        });
    });

    // Entrar
    $(document).on('click', '#btnLoginCliente', function () {
        var $btn = $(this);
        var doc = ($('#cpf_cnpj').val() || '').replace(/\D/g, '');
        var senha = $('#loginSenha').val() || '';
        if (!doc || !senha) return;
        $btn.prop('disabled', true);
        $('#loginFeedback').hide().text('');
        $.ajax({
            url: 'ajax-cliente-login.php',
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            data: JSON.stringify({ usuario: doc, senha: senha }),
            dataType: 'json'
        }).done(function (resp) {
            if (resp && resp.success) {
                // Substitui o form de pre-cadastro pelo bloco "logado" sem recarregar a pagina.
                // Mantem o step atual (4) e o resumo de reserva ja montado.
                var c = (resp && resp.cliente) || {};
                $('#cld_nome').text(c.nome || '');
                $('#cld_email').text(c.email || '');
                $('#cld_telefone').text(c.telefone || '');
                $('#formPrecadastro').hide();
                $('#blocoClienteLogado').show();
                $('#cliente_logado_flag').val('1');
                $('#loginFeedback').hide().text('');
                $('#modalLoginCliente').modal('hide');
            } else {
                var i18n = window.I18N_WEBSITE || {};
                $('#loginFeedback').text((resp && resp.message) || i18n.login_erro || 'Senha incorreta.').show();
            }
        }).fail(function () {
            var i18n = window.I18N_WEBSITE || {};
            $('#loginFeedback').text(i18n.login_erro || 'Senha incorreta.').show();
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // Focar campo senha ao abrir o modal de login
    $(document).on('shown.bs.modal', '#modalLoginCliente', function () {
        $('#loginSenha').focus();
    });

    // Submit via Enter no campo senha
    $(document).on('keypress', '#loginSenha', function (e) {
        if (e.which === 13) { e.preventDefault(); $('#btnLoginCliente').click(); }
    });

    // Sair (logout) — intercepta clique, faz AJAX e alterna UI pro form de pre-cadastro
    $(document).on('click', '#btnLogoutCliente', function (e) {
        e.preventDefault();
        $.ajax({ url: 'ajax-cliente-logout.php', method: 'GET', dataType: 'json' })
            .always(function () {
                $('#cld_nome').text('');
                $('#cld_email').text('');
                $('#cld_telefone').text('');
                $('#blocoClienteLogado').hide();
                $('#formPrecadastro').show();
                $('#cliente_logado_flag').val('0');
                $('#cpf_cnpj').val('').focus();
                $('#nome_rsocial, #email_reserva, #tel_cel, #loginSenha').val('');
                $('#loginFeedback').hide().text('');
                $('#modalLoginCliente').modal('hide');
            });
    });

    // Esqueci minha senha
    $(document).on('click', '#linkEsqueciSenha', function (e) {
        e.preventDefault();
        var doc = ($('#cpf_cnpj').val() || '').replace(/\D/g, '');
        if (!doc) return;
        $.ajax({
            url: 'ajax-cliente-senha-reset.php',
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            data: JSON.stringify({ documento: doc }),
            dataType: 'json'
        }).always(function () {
            var i18n = window.I18N_WEBSITE || {};
            alert(i18n.reset_enviado || 'Se o documento estiver cadastrado, uma nova senha foi enviada por email.');
        });
    });

    // Le um <input type="file"> como base64 (dataURL). Retorna Promise que resolve para null se vazio.
    function lerArquivoComoBase64(input) {
        return new Promise(function (resolve, reject) {
            if (!input || !input.files || input.files.length === 0) return resolve(null);
            var file = input.files[0];
            if (file.size > 5 * 1024 * 1024) {
                return reject(new Error('Arquivo acima de 5MB: ' + (input.id || '')));
            }
            var reader = new FileReader();
            reader.onload = function () { resolve(reader.result); };
            reader.onerror = function () { reject(new Error('Erro ao ler arquivo')); };
            reader.readAsDataURL(file);
        });
    }

    function coletarDocumentosOuErro() {
        var tipos = ['cnh', 'cpf', 'rg', 'comprovante'];
        var tasks = [];
        var errosObrigatorios = [];

        tipos.forEach(function (tipo) {
            var input = document.getElementById('doc_' + tipo);
            if (!input) return;
            var obr = input.getAttribute('data-required') === '1';
            if (obr && (!input.files || input.files.length === 0)) {
                errosObrigatorios.push(tipo);
                return;
            }
            tasks.push(lerArquivoComoBase64(input).then(function (b64) {
                return b64 ? { tipo: tipo, base64: b64 } : null;
            }));
        });

        if (errosObrigatorios.length > 0) {
            return Promise.reject(new Error('Documentos obrigatórios faltando: ' + errosObrigatorios.join(', ')));
        }

        return Promise.all(tasks).then(function (list) {
            var out = {};
            list.forEach(function (d) { if (d) out[d.tipo] = d.base64; });
            return out;
        });
    }

    // Preenche o comprovante de impressao (#reservaPrintComprovante) com dados da reserva concluida
    function preencherComprovanteImpressao(resp) {
        function fmtData(iso) {
            if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return iso || '—';
            var p = iso.split('-');
            return p[2] + '/' + p[1] + '/' + p[0];
        }

        var retLocal = $('#localRetirada option:selected').text().trim() || '—';
        var devLocal = $('#localDevolucao option:selected').text().trim() || retLocal;
        var retData  = fmtData($('#dataSaida').val());
        var retHora  = $('#horaSaida').val() || '—';
        var devData  = fmtData($('#dataPrevista').val());
        var devHora  = $('#horaDevolucao').val() || '—';

        var planoCodigo = String(window.__planoSelecionado || '');
        var planoLabels = window.I18N_WEBSITE || {};
        var planoTxt = {
            KML: planoLabels.plano_km_livre || 'Km Livre',
            KMC: planoLabels.plano_km_controlado || 'Km Controlado',
            DIA: planoLabels.plano_km_pago || 'Km Pago'
        }[planoCodigo] || planoCodigo;

        var totalTxt = $('.total-geral span').first().text().trim() || '—';

        var clienteTxt = '';
        if (($('#cliente_logado_flag').val() || '0') === '1') {
            clienteTxt = $('#cld_nome').text().trim();
        } else {
            clienteTxt = ($('#nome_rsocial').val() || '').trim();
        }
        clienteTxt = clienteTxt || '—';

        $('#prt_codigo').text(resp.requer_confirmacao ? 'Pendente de confirmacao' : (resp.codigo || '—'));
        $('#prt_cliente').text(clienteTxt);
        $('#prt_ret_local').text(retLocal);
        $('#prt_ret_data').text(retData);
        $('#prt_ret_hora').text(retHora);
        $('#prt_dev_local').text(devLocal);
        $('#prt_dev_data').text(devData);
        $('#prt_dev_hora').text(devHora);
        $('#prt_plano').text(planoTxt || '—');
        $('#prt_total').text(totalTxt);

        var msg = resp.requer_confirmacao
            ? 'Sua solicitacao foi recebida e aguarda confirmacao da locadora. Voce sera notificado por email, WhatsApp ou SMS.'
            : 'Sua reserva foi confirmada. Guarde este comprovante.';
        $('#prt_mensagem').text(msg);

        var agora = new Date();
        var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
        $('#prt_emitido_em').text(pad(agora.getDate()) + '/' + pad(agora.getMonth() + 1) + '/' + agora.getFullYear() + ' ' + pad(agora.getHours()) + ':' + pad(agora.getMinutes()));
    }

    // Concluir reserva (handler cobre o botao original e a copia abaixo do resumo)
    $(document).on('click', '.btnConcluirReserva', async function () {
        var $btn = $(this);

        // Cliente logado: o form de pre-cadastro fica oculto. Pula validacao e envia
        // apenas filial/datas/plano/servicos; o backend lê $_SESSION['cliente_id'] no proxy.
        var clienteLogado = ($('#cliente_logado_flag').val() || '0') === '1';

        var cliente = null;
        var documentos = {};

        if (!clienteLogado) {
            var nome = ($('#nome_rsocial').val() || '').trim();
            var cpf = ($('#cpf_cnpj').val() || '').trim();
            var email = ($('#email_reserva').val() || '').trim();
            var tel = ($('#tel_cel').val() || '').trim();
            if (!nome || !cpf || !email || !tel) {
                alert('Preencha documento, nome, email e celular.');
                return;
            }

            // Endereco so eh coletado se os campos existem (cadastro_simples=0 renderiza; =1 nao)
            var endereco = null;
            if ($('#cep').length) {
                endereco = {
                    cep: ($('#cep').val() || '').trim(),
                    rua: ($('#rua').val() || '').trim(),
                    numero: ($('#numero').val() || '').trim(),
                    bairro: ($('#bairro').val() || '').trim(),
                    cidade: ($('#cidade').val() || '').trim(),
                    estado: ($('#estado').val() || '').trim(),
                    pais: ($('#pais').val() || '').trim(),
                };
                for (var k in endereco) {
                    if (!endereco[k]) { alert('Preencha todos os campos do endereco.'); return; }
                }
            }

            cliente = {
                nome: nome, documento: cpf, email: email, telefone: tel, endereco: endereco,
            };

            try {
                documentos = await coletarDocumentosOuErro();
            } catch (err) {
                alert(err && err.message ? err.message : String(err));
                return;
            }
        }

        var idFormaPagamentoSite = 0;
        if (window.PAGAMENTO_ANTECIPADO_SITE) {
            idFormaPagamentoSite = parseInt($('#id_forma_pagamento_site').val() || $('input[name="forma_pagamento_site"]:checked').val() || '0');
            if (!idFormaPagamentoSite) {
                $('#formasPagamentoSiteFeedback').show();
                document.getElementById('formasPagamentoSiteBox')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
        }

        $btn.prop('disabled', true);

        try {
            var grupoId = grupoSelecionadoId();
            var plano = String(window.__planoSelecionado || '');
            var servicos = [];
            $('#itens-adicionais input[type="checkbox"]:checked').each(function () {
                var nm = $(this).attr('name') || '';
                var id = parseInt(nm.replace('servico_', ''));
                if (id) servicos.push(id);
            });

            var payload = {
                filial_retirada_id: window.__locValFilialId($('#localRetirada')),
                filial_devolucao_id: window.__locValFilialId($('#localDevolucao')),
                data_saida: $('#dataSaida').val(),
                hora_saida: $('#horaSaida').val(),
                data_chegada: $('#dataPrevista').val(),
                hora_chegada: $('#horaDevolucao').val(),
                grupo_id: grupoId,
                plano: plano,
                servicos: servicos,
                seguro_carro: seguroEstaSelecionado('carro'),
                seguro_terceiros: seguroEstaSelecionado('terceiros'),
                promocao_codigo: String($('#promocao_codigo').val() || '').trim().toUpperCase(),
                cliente: cliente,
                documentos: documentos,
            };
            if (idFormaPagamentoSite) {
                payload.id_forma_pagamento = idFormaPagamentoSite;
            }

            var resp = await $.ajax({
                url: 'ajax-reserva.php',
                method: 'POST',
                contentType: 'application/json; charset=utf-8',
                data: JSON.stringify(payload),
                dataType: 'json'
            });

            if (!resp || !resp.success) {
                alert(resp && resp.message ? resp.message : 'Erro ao enviar reserva.');
                return;
            }

            // Pagamento antecipado: backend retorna URL do link de pagamento (valor calculado server-side)
            if (resp.pagamento_url) {
                window.location.href = resp.pagamento_url;
                return;
            }

            if (resp.requer_confirmacao) {
                // Aguardando confirmacao manual — nao mostrar codigo
                $('#reservaOkBloco').hide();
                $('#reservaAguardandoBloco').show();
                $('.codigo-reserva').text('');
            } else {
                // Reserva confirmada na hora
                $('#reservaAguardandoBloco').hide();
                $('#reservaOkBloco').show();
                $('.codigo-reserva').text('Codigo: ' + resp.codigo);
            }

            preencherComprovanteImpressao(resp);
            nextTab();
        } catch (err) {
            alert(err && err.message ? err.message : String(err));
        } finally {
            $btn.prop('disabled', false);
        }
    });

    // ---- Contato: habilitar botão ----
    function verificarCampos() {
        var nome = $('#nome').val();
        var email = $('#email_contato').val();
        var tel = $('#telefone').val();
        var msg = $('#mensagem').val();
        if (nome && email && tel && msg) {
            $('button[type="submit"]').prop('disabled', false);
        } else {
            $('button[type="submit"]').prop('disabled', true);
        }
    }
    $('#nome, #email_contato, #telefone, #mensagem').on('input', verificarCampos);

    // ---- Login simulado ----
    $('form[name="login"]').on('submit', function (e) {
        e.preventDefault();
        window.location.href = 'painel.html';
    });

    // Render inicial dos precos de servicos (caso a filial ja esteja pre-selecionada via query string)
    if ($('.servico-preco').length && window.__locValFilialId && window.__locValFilialId($('#localRetirada'))) {
        renderPrecosServicos();
    }
    atualizarPlanosDisponiveis();
    aplicarObrigatoriedadeSeguros();
    if (typeof window.__renderFormasPagamentoSite === 'function') {
        window.__renderFormasPagamentoSite();
    }
});

// ---- FUNÇÕES AUXILIARES ----

function formatDateBR(dateStr) {
    if (!dateStr) return '';
    var parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    return parts[2] + '/' + parts[1] + '/' + parts[0];
}

function showTab(n) {
    var tabs = $('.tabs_');
    if (tabs.length === 0) return;
    tabs.removeClass('active');
    tabs.eq(n).addClass('active');

    $('.progressbar li').removeClass('active');
    for (var i = 0; i <= n; i++) {
        $('.progressbar li').eq(i).addClass('active');
    }
}

function nextTab() {
    var tabs = $('.tabs_');
    var current = tabs.index($('.tabs_.active'));
    if (current < tabs.length - 1) {
        showTab(current + 1);
        window.scrollTo(0, 0);
    }
}

function calcTotal() {
    var total = 0;
    // Remove qualquer simbolo de moeda (R$, €, $, £, ...) e espacos; mantem digitos,
    // milhares/decimais. Depois normaliza sep_milhar/sep_decimal da filial ativa.
    var fId = window.__locValFilialId ? window.__locValFilialId($('#localRetirada')) : null;
    var f = (window.FILIAIS_DATA || {})[fId] || null;
    var dec = f ? (f.separador_decimal || ',') : ',';
    var mil = f ? (f.separador_milhar || '.') : '.';

    // Os resumos sao duplicados nas tabs 3 e 4. Iterar apenas a tab ativa evita contar 2x.
    // Fallback: primeiro resumo do DOM.
    var $scope = $('.tabs_.active .resumo-detalhes');
    if (!$scope.length) $scope = $('.resumo-detalhes').first();

    $scope.find('.somar').each(function () {
        var txt = $(this).text().replace(/[^\d.,\-]/g, '').trim();
        if (!txt) return;
        // Converte string localizada em float
        var normalized = txt.split(mil).join('').replace(dec, '.');
        var val = parseFloat(normalized);
        if (!isNaN(val)) total += val;
    });

    var descontoPromo = parseFloat((window.__promocaoAplicada || {}).valor_desconto || 0);
    total = Math.max(0, total - descontoPromo);

    // Formata o total na moeda da filial
    var sym = f ? (f.simbolo_moeda || 'R$') : 'R$';
    var parts = total.toFixed(2).split('.');
    parts[0] = parts[0].replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1' + mil);
    // Prefixa o simbolo da moeda direto no span (HTML nao tem .currency-symbol separado)
    $('.total-geral span').text(sym + ' ' + parts.join(dec));

    if (typeof window.__agendarRevalidacaoPromocao === 'function') {
        window.__agendarRevalidacaoPromocao();
    }
}
