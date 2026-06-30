/* Agenda — timeline Gantt horizontal (spec: AGENDA_REPLICACAO.md §5) */
(function () {
    const I18N = window.AGENDA_I18N || {};

    let jsonData = [];
    let originalApiData = null;

    const tipos = {
        'locado': 'Locado',
        'reserva': 'Reserva',
        'contrato': 'Contrato',
        'agenda': 'Agenda',
        'veiculo': 'Veiculo',
        'manutencao_emandamento': 'Manutencao em Andamento',
        'manutencao_programada': 'Manutencao Programada'
    };

    const today = new Date();
    const config = {
        viewStartDate: null,
        viewEndDate: null,
        monthNames: I18N.monthNames || ['Janeiro','Fevereiro','Marco','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
        dayNames: I18N.dayNames || ['DOM','SEG','TER','QUA','QUI','SEX','SAB']
    };

    async function fetchAgendaData() {
        try {
            const resp = await API.get('/api/agenda');
            if (resp && resp.erro) throw new Error(resp.erro);
            return resp;
        } catch (err) {
            console.error('Erro ao buscar dados da agenda:', err);
            if (window.toast) toast.error((I18N.loadError || 'Erro ao carregar agenda') + ': ' + err.message);
            return { grupos: {}, agenda_geral: [] };
        }
    }

    function processApiData(data) {
        if (!data || typeof data !== 'object') return [];
        const processed = [];

        if (Array.isArray(data.agenda_geral)) {
            data.agenda_geral.forEach(item => {
                const ev = processEvent(item, null, null, 'agenda_geral');
                if (ev) processed.push(ev);
            });
        }

        if (Array.isArray(data.reservas_orfas)) {
            data.reservas_orfas.forEach(item => {
                const ev = processEvent(item, null, null, 'reserva_orfa');
                if (ev) processed.push(ev);
            });
        }

        if (data.grupos && typeof data.grupos === 'object') {
            Object.values(data.grupos).forEach(grupo => {
                if (Array.isArray(grupo.reservas_sem_veiculo)) {
                    grupo.reservas_sem_veiculo.forEach(reserva => {
                        const ev = processEvent(reserva, grupo, null, 'reserva_sem_veiculo');
                        if (ev) processed.push(ev);
                    });
                }
                if (Array.isArray(grupo.veiculos)) {
                    grupo.veiculos.forEach(veiculo => {
                        if (!veiculo.eventos) return;
                        ['locacoes','reservas','contratos','manutencoes_andamento','manutencoes_programadas'].forEach(tipoEvento => {
                            if (Array.isArray(veiculo.eventos[tipoEvento])) {
                                veiculo.eventos[tipoEvento].forEach(evento => {
                                    const ev = processEvent(evento, grupo, veiculo, tipoEvento);
                                    if (ev) processed.push(ev);
                                });
                            }
                        });
                    });
                }
            });
        }
        return processed;
    }

    function processEvent(item, grupo, veiculo, tipoOriginal) {
        let dataIni = new Date(item.data_inicio);
        let dataFim = item.data_fim ? new Date(item.data_fim) : null;

        if (!dataFim || isNaN(dataFim.getTime())) {
            const threeDaysAhead = new Date(DateHelper.timestamp() + 3 * 24 * 60 * 60 * 1000);
            switch (tipoOriginal) {
                case 'locacoes':
                    // Em aberto (sem data_chegada nem data_prevista futura): 3d a partir de hoje
                    dataFim = threeDaysAhead; break;
                case 'contratos':
                    dataFim = new Date(dataIni.getTime() + 30 * 24 * 60 * 60 * 1000); break;
                case 'reservas':
                case 'reserva_sem_veiculo': {
                    const defaultEnd = new Date(dataIni.getTime() + 3 * 24 * 60 * 60 * 1000);
                    dataFim = defaultEnd > threeDaysAhead ? defaultEnd : threeDaysAhead;
                    break;
                }
                case 'manutencoes_andamento':
                    // Ongoing: 3d a partir de hoje (sempre visivel na janela atual)
                    dataFim = threeDaysAhead; break;
                case 'manutencoes_programadas':
                    dataFim = new Date(dataIni.getTime() + 7 * 24 * 60 * 60 * 1000); break;
                case 'agenda_geral':
                    dataFim = new Date(dataIni.getTime() + 24 * 60 * 60 * 1000); break;
                default:
                    dataFim = new Date(dataIni.getTime() + 3 * 60 * 60 * 1000);
            }
        }

        let tipo, titulo, resourceId;
        if (tipoOriginal === 'agenda_geral') {
            tipo = 'agenda';
            titulo = item.titulo || item.label || (I18N.generalSchedule || 'Agenda Geral');
            resourceId = 'geral';
        } else if (tipoOriginal === 'reserva_orfa') {
            // Reserva sem grupo nem veiculo → AGENDA GERAL
            tipo = 'reserva';
            titulo = 'Reserva ' + (item.codigo || item.id);
            resourceId = 'geral';
        } else if (tipoOriginal === 'reserva_sem_veiculo') {
            tipo = 'reserva';
            titulo = 'Reserva ' + (item.codigo || item.id);
            resourceId = grupo.nome + '-geral';
        } else {
            const tipoMap = {
                'locacoes': 'locado',
                'reservas': 'reserva',
                'contratos': 'contrato',
                'manutencoes_andamento': 'manutencao_emandamento',
                'manutencoes_programadas': 'manutencao_programada'
            };
            tipo = tipoMap[tipoOriginal] || tipoOriginal;
            titulo = veiculo ? (veiculo.placa + ' - ' + veiculo.modelo) : (item.titulo || 'Sem titulo');
            resourceId = veiculo ? (veiculo.placa + ' - ' + veiculo.modelo) : 'geral';
        }

        return {
            id: item.id,
            tipo: tipo,
            titulo: titulo,
            resourceId: resourceId,
            dataIni: dataIni.toISOString().replace('T',' ').substring(0,19),
            dataFim: dataFim.toISOString().replace('T',' ').substring(0,19),
            grupo_nome: grupo ? grupo.nome : 'GERAL',
            grupo_id: grupo ? grupo.id : null,
            veiculo_id: veiculo ? veiculo.id : null,
            veiculo: veiculo,
            label: item.label || item.titulo || titulo,
            obs: item.obs || '',
            cor: item.cor || '',
            url: item.url || null,
            codigo: item.codigo || null,
            cliente_nome: item.cliente_nome || null
        };
    }

    function processData(data) {
        const structure = new Map();
        if (data && data.grupos && typeof data.grupos === 'object') {
            Object.values(data.grupos).forEach(grupo => {
                if (!structure.has(grupo.nome)) {
                    // Linha "Reservas" e sempre fixa no final do grupo
                    structure.set(grupo.nome, { veiculos: new Set(), hasGeneralEvents: true });
                }
                if (Array.isArray(grupo.veiculos)) {
                    grupo.veiculos.forEach(v => {
                        structure.get(grupo.nome).veiculos.add(v.placa + ' - ' + v.modelo);
                    });
                }
            });
        }
        return structure;
    }

    function getDaysArray(start, end) {
        const arr = [];
        for (let dt = new Date(start); dt <= end; dt.setDate(dt.getDate() + 1)) arr.push(new Date(dt));
        return arr;
    }

    function createHeaderAndRows() {
        const daysInView = getDaysArray(config.viewStartDate, config.viewEndDate);
        const thead = document.getElementById('schedule-table-head');
        const tbody = document.getElementById('schedule-table-body');

        const monthRow = document.createElement('tr');
        monthRow.innerHTML = '<th class="fixed-col fixed-col-1"></th><th class="fixed-col fixed-col-2"></th>';
        const dayRow = document.createElement('tr');
        dayRow.innerHTML =
            '<th class="fixed-col fixed-col-1" style="text-transform:uppercase;">' + (I18N.groups || 'Grupos') + '</th>' +
            '<th class="fixed-col fixed-col-2" style="text-transform:uppercase;">' + (I18N.vehicles || 'Veiculos') + '</th>';

        const months = {};
        daysInView.forEach(date => {
            const monthYear = config.monthNames[date.getMonth()] + ' / ' + date.getFullYear();
            months[monthYear] = (months[monthYear] || 0) + 1;

            const dayName = config.dayNames[date.getDay()];
            const th = document.createElement('th');
            th.innerHTML = dayName + '<br>' + date.getDate();

            const isLastDayOfMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate() === date.getDate();
            if (isLastDayOfMonth && date < config.viewEndDate) th.classList.add('month-separator');
            if (date.getDay() === 0 || date.getDay() === 6) th.style.backgroundColor = '#f7f7f7';
            dayRow.appendChild(th);
        });
        for (const monthYear in months) {
            const th = document.createElement('th');
            th.className = 'month-header-cell';
            th.colSpan = months[monthYear];
            th.textContent = monthYear;
            monthRow.appendChild(th);
        }
        thead.appendChild(monthRow);
        thead.appendChild(dayRow);

        const dayCellsHtml = daysInView.map((date, index) => {
            const isLastDayOfMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate() === date.getDate();
            const separatorClass = (isLastDayOfMonth && date < config.viewEndDate) ? 'month-separator' : '';

            let dayPartsHtml = '<div class="day-part"></div><div class="day-part"></div><div class="day-part"></div><div class="day-part"></div>';

            if (index === 0) {
                const currentHourPeriod = Math.floor(today.getHours() / 6);
                const remainingPeriods = 4 - currentHourPeriod;
                if (remainingPeriods < 4) {
                    dayPartsHtml = '';
                    for (let i = 0; i < remainingPeriods; i++) dayPartsHtml += '<div class="day-part"></div>';
                }
            }
            return '<td class="day-cell ' + separatorClass + '" data-date="' + date.toISOString().split('T')[0] + '"><div class="day-parts">' + dayPartsHtml + '</div></td>';
        }).join('');

        const vehicleStructure = processData(originalApiData);
        vehicleStructure.forEach((groupData, groupName) => {
            const groupVehicles = Array.from(groupData.veiculos);
            const totalRows = groupVehicles.length + 1; // +1 para a linha "Reservas" (sempre presente)
            let isFirstRow = true;

            // Veiculos primeiro
            groupVehicles.forEach(vehicleTitle => {
                const row = document.createElement('tr');
                row.dataset.resourceId = vehicleTitle;
                if (isFirstRow) {
                    row.innerHTML =
                        '<td class="fixed-col fixed-col-1 row-header" rowspan="' + totalRows + '">' + groupName + '</td>' +
                        '<td class="fixed-col fixed-col-2 row-header">' + vehicleTitle + '</td>' +
                        dayCellsHtml;
                    isFirstRow = false;
                } else {
                    row.innerHTML = '<td class="fixed-col fixed-col-2 row-header">' + vehicleTitle + '</td>' + dayCellsHtml;
                }
                tbody.appendChild(row);
            });

            // Linha "Reservas" no fim do grupo (sempre)
            const reservasRow = document.createElement('tr');
            reservasRow.dataset.resourceId = groupName + '-geral';
            reservasRow.classList.add('group-separator');
            const reservasLabel = '<td class="fixed-col fixed-col-2 row-header" style="font-style: italic; color: #666;">' + (I18N.reservations || 'Reservas') + '</td>';
            if (isFirstRow) {
                // Grupo sem veiculos: a linha de Reservas tambem leva o nome do grupo na coluna 1
                reservasRow.innerHTML =
                    '<td class="fixed-col fixed-col-1 row-header" rowspan="' + totalRows + '">' + groupName + '</td>' +
                    reservasLabel + dayCellsHtml;
            } else {
                reservasRow.innerHTML = reservasLabel + dayCellsHtml;
            }
            tbody.appendChild(reservasRow);
        });

        const generalRow = document.createElement('tr');
        generalRow.dataset.resourceId = 'geral';
        generalRow.innerHTML =
            '<td class="fixed-col fixed-col-1 row-header" rowspan="1">' + (I18N.generalSchedule || 'AGENDA GERAL').toUpperCase() + '</td>' +
            '<td class="fixed-col fixed-col-2 row-header"></td>' +
            dayCellsHtml;
        tbody.appendChild(generalRow);
    }

    function createEventLink(event, textContent) {
        if (!event.url || !event.url.url) return textContent;

        let linkClass = 'agendalink';
        if (event.url.class) {
            linkClass += ' ' + event.url.class;
        } else {
            linkClass += event.tipo === 'agenda' ? ' abreLateral' : ' abreTab';
        }
        let dataAttrs = '';
        if (event.url.width) dataAttrs += ' data-width="' + event.url.width + '"';
        if (event.url.title) dataAttrs += ' data-title="' + escapeHtml(event.url.title) + '"';
        if (event.url.icon)  dataAttrs += ' data-icon="' + event.url.icon + '"';
        if (event.url.tabId) dataAttrs += ' data-tab-id="' + event.url.tabId + '"';
        return '<a href="' + event.url.url + '"' + dataAttrs + ' class="' + linkClass + '">' + textContent + '</a>';
    }

    /**
     * Abre ou reaproveita aba existente (Opcao B): se ja existe aba com tabId,
     * atualiza o iframe.src pra apontar para a nova URL e ativa a aba. Caso contrario,
     * usa openOrSwitchToTab do parent para criar nova aba.
     */
    function openOrReplaceTab(url, name, icon, tabId) {
        const p = window.parent;
        if (!p || p === window) { window.location.href = url; return; }
        try {
            const existingTab = p.document.querySelector('.sidebar-tab[data-tab-id="' + tabId + '"]');
            if (existingTab) {
                const content = p.document.querySelector('.tab-content[data-tab-content-id="' + tabId + '"]');
                const iframe = content && content.querySelector('iframe');
                if (iframe) {
                    const fullUrl = new URL(url, p.location.origin).toString();
                    if (iframe.src !== fullUrl) iframe.src = url;
                }
                existingTab.click();
                return;
            }
        } catch (err) {
            console.warn('openOrReplaceTab fallback:', err);
        }
        if (typeof p.openOrSwitchToTab === 'function') {
            p.openOrSwitchToTab(url, name, icon, tabId);
        }
    }

    function placeEvents(data) {
        const visibleData = data.filter(event => {
            const startDate = new Date(event.dataIni);
            const endDate = event.dataFim ? new Date(event.dataFim) : startDate;
            return startDate <= config.viewEndDate && endDate >= config.viewStartDate;
        });

        const groupedEvents = new Map();
        visibleData.forEach(event => {
            const resourceId = event.resourceId || 'geral';
            if (!groupedEvents.has(resourceId)) groupedEvents.set(resourceId, []);
            groupedEvents.get(resourceId).push(event);
        });

        const maxLanesByResource = new Map();
        groupedEvents.forEach((events, resourceId) => {
            events.forEach(event => {
                event.startDate = new Date(event.dataIni);
                event.endDate = event.dataFim ? new Date(event.dataFim) : new Date(event.startDate.getTime() + 3 * 60 * 60 * 1000);
            });
            events.sort((a, b) => a.startDate - b.startDate);

            const lanes = [];
            events.forEach(current => {
                let laneIndex = 0;
                while (true) {
                    if (!lanes[laneIndex]) lanes[laneIndex] = [];
                    const hasCollision = lanes[laneIndex].some(placed =>
                        current.startDate < placed.endDate && current.endDate > placed.startDate
                    );
                    if (!hasCollision) { current.lane = laneIndex; lanes[laneIndex].push(current); break; }
                    laneIndex++;
                }
            });
            maxLanesByResource.set(resourceId, lanes.length);
        });

        visibleData.forEach(event => {
            const renderStart = event.startDate < config.viewStartDate ? config.viewStartDate : event.startDate;
            const renderEnd = event.endDate > config.viewEndDate ? config.viewEndDate : event.endDate;
            const eventStartDateStr = renderStart.toISOString().split('T')[0];

            const resourceId = event.resourceId || 'geral';
            const targetRow = document.querySelector('[data-resource-id="' + cssEscape(resourceId) + '"]');
            if (!targetRow) { console.warn('Linha nao encontrada:', resourceId); return; }

            const cell = targetRow.querySelector('td[data-date="' + eventStartDateStr + '"]');
            if (!cell) { console.warn('Celula nao encontrada:', eventStartDateStr); return; }

            const topPosition = 5 + (event.lane * 32);
            const durationInMs = renderEnd.getTime() - renderStart.getTime();
            if (durationInMs <= 0) return;

            const dayInMillis = 1000 * 60 * 60 * 24;
            const startOfDay = new Date(renderStart.getFullYear(), renderStart.getMonth(), renderStart.getDate()).getTime();
            const endOfDay = new Date(renderEnd.getFullYear(), renderEnd.getMonth(), renderEnd.getDate()).getTime() + dayInMillis;
            const durationDays = (endOfDay - startOfDay) / dayInMillis;

            let barWidth = durationDays * 100;
            let startOffset = Math.floor(renderStart.getHours() / 6) * 25;

            const eventDate = new Date(renderStart.getFullYear(), renderStart.getMonth(), renderStart.getDate());
            const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            if (eventDate.getTime() === todayDate.getTime()) {
                const currentHourPeriod = Math.floor(today.getHours() / 6);
                const eventHourPeriod = Math.floor(renderStart.getHours() / 6);
                startOffset = Math.max(0, (eventHourPeriod - currentHourPeriod) * 25);
            }
            barWidth -= startOffset;

            const eventBar = document.createElement('div');
            eventBar.className = 'event-bar ' + (event.cor || '');
            eventBar.dataset.eventType = event.tipo;

            if (new Date(event.dataIni) < config.viewStartDate) eventBar.classList.add('event-started-in-past');

            eventBar.style.left = startOffset + '%';
            eventBar.style.width = 'calc(' + barWidth + '% - 2px)';
            eventBar.style.top = topPosition + 'px';

            const formatDate = s => new Date(s).toLocaleDateString('pt-BR', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });
            const periodo = formatDate(event.dataIni) + ' - ' + formatDate(event.dataFim);

            let displayText, tooltipText;
            const tipoLabel = tipos[event.tipo];

            if (tipoLabel && ['contrato','locado','reserva'].includes(event.tipo)) {
                let primeiraLinha = tipoLabel;
                if (event.codigo) primeiraLinha += ': ' + event.codigo;
                if (event.cliente_nome) primeiraLinha += ' - ' + event.cliente_nome;
                const linkContent = createEventLink(event, primeiraLinha);
                displayText =
                    '<div style="position:relative; height:30px;">' +
                    '<div style="font-size:14px; line-height:14px;">' + linkContent + '</div>' +
                    '<div style="position:absolute; bottom:2px; font-size:10px; opacity:0.9;">' + periodo + '</div>' +
                    '</div>';
                tooltipText = primeiraLinha + '\n' + periodo;
            } else if (event.tipo === 'agenda') {
                const linkContent = createEventLink(event, event.label || event.titulo);
                displayText =
                    '<div style="position:relative; height:30px;">' +
                    '<div style="font-size:14px; line-height:14px;">' + linkContent + '</div>' +
                    '<div style="position:absolute; bottom:2px; font-size:10px; opacity:0.9;">' + periodo + '</div>' +
                    '</div>';
                tooltipText = tipoLabel + ': ' + event.titulo + '\n' + periodo;
            } else if (tipoLabel) {
                const linkContent = createEventLink(event, tipoLabel);
                displayText =
                    '<div style="position:relative; height:30px;">' +
                    '<div style="font-size:14px; line-height:14px;">' + linkContent + '</div>' +
                    '<div style="position:absolute; bottom:2px; font-size:10px; opacity:0.9;">' + periodo + '</div>' +
                    '</div>';
                tooltipText = tipoLabel + ': ' + event.titulo + '\n' + periodo;
            } else {
                const linkContent = createEventLink(event, event.label || event.titulo);
                displayText =
                    '<div style="position:relative; height:30px;">' +
                    '<div style="font-size:14px; line-height:14px;">' + linkContent + '</div>' +
                    '<div style="position:absolute; bottom:2px; font-size:10px; opacity:0.9;">' + periodo + '</div>' +
                    '</div>';
                tooltipText = event.titulo + '\n' + periodo;
            }

            eventBar.innerHTML =
                '<div style="overflow:hidden; width:100%; height:100%;">' + displayText + '</div>' +
                '<span class="tooltip"><strong>' + escapeHtml(tooltipText) + '</strong><br>' + escapeHtml(event.obs || '') + '</span>';
            cell.appendChild(eventBar);
        });

        const allRows = document.querySelectorAll('tbody tr[data-resource-id]');
        allRows.forEach(row => {
            const resourceId = row.dataset.resourceId;
            if (!maxLanesByResource.has(resourceId)) {
                row.style.setProperty('height', '37px', 'important');
                row.querySelectorAll('td').forEach(cell => cell.style.setProperty('height', '37px', 'important'));
            }
        });
        maxLanesByResource.forEach((maxLanes, resourceId) => {
            const targetRow = document.querySelector('[data-resource-id="' + cssEscape(resourceId) + '"]');
            if (!targetRow) return;
            const totalHeight = maxLanes * 32 + 5 + 3;
            targetRow.style.setProperty('height', totalHeight + 'px', 'important');
            targetRow.querySelectorAll('td').forEach(cell => cell.style.setProperty('height', totalHeight + 'px', 'important'));
        });
    }

    function createFilters() {
        const filtersContainer = document.getElementById('filters');
        filtersContainer.innerHTML = '';

        const tiposCores = {
            'reserva': '#007bff',
            'locado': '#dc3545',
            'contrato': '#dc3545',
            'manutencao_emandamento': '#fd7e14',
            'manutencao_programada': '#fd7e14',
            'agenda': '#6f42c1'
        };

        const labelMap = {
            'reserva': I18N.reservation || 'Reserva',
            'locado': I18N.rental || 'Locado',
            'contrato': I18N.contract || 'Contrato',
            'manutencao_emandamento': I18N.maintenanceOngoing || 'Manutencao em Andamento',
            'manutencao_programada': I18N.maintenanceScheduled || 'Manutencao Programada',
            'agenda': I18N.schedule || 'Agenda'
        };

        const allButton = document.createElement('button');
        allButton.innerHTML = I18N.all || 'Todos';
        allButton.classList.add('active');
        allButton.dataset.typeId = 'all';
        allButton.addEventListener('click', handleFilterClick);
        filtersContainer.appendChild(allButton);

        const ordemFiltros = ['reserva','locado','contrato','manutencao_emandamento','manutencao_programada','agenda'];

        const tiposEncontrados = new Set();
        jsonData.forEach(item => { if (item.tipo && tipos[item.tipo]) tiposEncontrados.add(item.tipo); });

        ordemFiltros.forEach(tipoKey => {
            if (!tiposEncontrados.has(tipoKey)) return;
            const button = document.createElement('button');
            const colorIndicator = document.createElement('span');
            colorIndicator.className = 'filter-color-indicator';
            colorIndicator.style.backgroundColor = tiposCores[tipoKey];
            button.appendChild(colorIndicator);
            button.appendChild(document.createTextNode(labelMap[tipoKey]));
            button.dataset.typeId = tipoKey;
            button.addEventListener('click', handleFilterClick);
            filtersContainer.appendChild(button);
        });
    }

    function handleFilterClick(e) {
        const target = e.currentTarget;
        document.querySelectorAll('.schedule-filters button').forEach(b => b.classList.remove('active'));
        target.classList.add('active');
        const typeId = target.dataset.typeId;
        document.querySelectorAll('.event-bar').forEach(bar => {
            const shouldShow = (typeId === 'all' || bar.dataset.eventType === typeId);
            bar.classList.toggle('hidden', !shouldShow);
        });
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(value);
        return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    }

    window.reloadAgenda = async function () {
        const preloader = document.getElementById('preloader');
        if (preloader) preloader.style.display = 'flex';
        try {
            document.getElementById('schedule-table-head').innerHTML = '';
            document.getElementById('schedule-table-body').innerHTML = '';
            originalApiData = await fetchAgendaData();
            jsonData = processApiData(originalApiData);
            createHeaderAndRows();
            placeEvents(jsonData);
            createFilters();
        } catch (err) {
            console.error('Erro ao recarregar agenda:', err);
        } finally {
            if (preloader) preloader.style.display = 'none';
        }
    };

    document.addEventListener('DOMContentLoaded', async function () {
        const preloader = document.getElementById('preloader');
        if (preloader) preloader.style.display = 'flex';
        if (window.pageLoading) window.pageLoading.start();

        try {
            originalApiData = await fetchAgendaData();
            jsonData = processApiData(originalApiData);

            config.viewStartDate = new Date(today);
            let maxEndDate = new Date(today.getFullYear(), today.getMonth() + 3, 0);

            if (jsonData.length > 0) {
                const endDates = jsonData
                    .map(item => new Date(item.dataFim))
                    .filter(d => !isNaN(d.getTime()) && d > today);

                if (endDates.length > 0) {
                    const furthest = new Date(Math.max.apply(null, endDates));
                    maxEndDate = new Date(furthest.getFullYear(), furthest.getMonth() + 1, 0);
                }
            }
            config.viewEndDate = maxEndDate;

            createHeaderAndRows();
            placeEvents(jsonData);
            createFilters();
            bindEventClicks();
            bindNovaAgendaButton();
        } catch (err) {
            console.error('Erro ao inicializar agenda:', err);
        } finally {
            if (preloader) preloader.style.display = 'none';
            if (window.pageLoading) window.pageLoading.done();
        }
    });

    // Delegation: click em qualquer .agendalink → abre aba ou offcanvas
    function bindEventClicks() {
        const table = document.getElementById('schedule-table');
        if (!table || table._clickBound) return;
        table._clickBound = true;
        table.addEventListener('click', function (e) {
            const link = e.target.closest('.agendalink');
            if (!link) return;
            e.preventDefault();
            const url = link.getAttribute('href');
            const title = link.dataset.title || '';
            const icon = link.dataset.icon || '';
            const tabId = link.dataset.tabId || '';
            if (link.classList.contains('abreLateral')) {
                const width = link.dataset.width || '500px';
                if (window.parent && typeof window.parent.openOffcanvasIframe === 'function') {
                    window.parent.openOffcanvasIframe(url, title || 'Editar', width);
                }
            } else if (link.classList.contains('abreTab') || link.className.indexOf('abreTab') !== -1) {
                openOrReplaceTab(url, title, icon, tabId);
            }
        });
    }

    // Botao "+ Nova Agenda"
    function bindNovaAgendaButton() {
        const btn = document.getElementById('btnNovaAgenda');
        if (!btn || btn._clickBound) return;
        btn._clickBound = true;
        btn.addEventListener('click', function () {
            if (window.parent && typeof window.parent.openOffcanvasIframe === 'function') {
                window.parent.openOffcanvasIframe('/pages/agenda/adicionar', 'Nova Agenda', '40%');
            }
        });
    }

    // Escuta mensagens vindas do offcanvas (form de nova/editar agenda) pedindo reload
    window.addEventListener('message', function (e) {
        if (e && e.data && e.data.action === 'reloadAgendaAndClose') {
            if (typeof window.reloadAgenda === 'function') window.reloadAgenda();
        }
    });
})();
