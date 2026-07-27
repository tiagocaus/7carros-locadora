/**
 * Components JS - Componentes reutilizaveis
 */

/**
 * HelpHint - Componente de ajuda com popover
 *
 * Uso:
 * {{ aviso(t('modules.modulo.hints.campo')) }}
 * {{ aviso('Texto direto') }}
 */
const HelpHint = {
    activePopover: null, // Popover atualmente aberto
    activeHint: null, // Hint associado ao popover aberto
    scrollHandler: null, // Handler do scroll
    resizeHandler: null, // Handler do resize
    rafId: null, // ID do requestAnimationFrame

    init() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('.help-hint')) {
                e.preventDefault();
                e.stopPropagation();
                this.toggle(e.target.closest('.help-hint'));
                return;
            }
            this.closeAll();
        });
    },

    updatePopoverPosition() {
        if (!this.activePopover || !this.activeHint) return;

        // Cancelar RAF anterior se existir
        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
        }

        // Usar RAF para otimizar atualizações
        this.rafId = requestAnimationFrame(() => {
            const rect = this.activeHint.getBoundingClientRect();
            const iconCenterY = rect.top + rect.height / 2;
            const popoverTop = iconCenterY - 37;

            const popoverWidth = 300;
            const offset = 27;
            const viewportWidth = window.innerWidth;

            let popoverLeft = rect.left + offset;
            let showOnLeft = false;

            if (popoverLeft + popoverWidth > viewportWidth) {
                popoverLeft = rect.right - popoverWidth - offset;
                showOnLeft = true;

                if (popoverLeft < 0) {
                    popoverLeft = Math.max(10, rect.left + offset);
                    showOnLeft = false;
                }
            }

            if (!showOnLeft && popoverLeft < 0) {
                popoverLeft = 10;
            }

            // Atualizar classes de posicionamento
            this.activePopover.classList.remove('help-hint-popover-left', 'help-hint-popover-right');
            if (showOnLeft) {
                this.activePopover.classList.add('help-hint-popover-left');
            } else {
                this.activePopover.classList.add('help-hint-popover-right');
            }

            this.activePopover.style.top = popoverTop + 'px';
            this.activePopover.style.left = popoverLeft + 'px';
        });
    },

    toggle(hint) {
        const popover = document.getElementById(hint.dataset.popover);
        if (!popover) return;

        const isOpen = popover.classList.contains('show');
        this.closeAll();

        if (!isOpen) {
            const rect = hint.getBoundingClientRect();
            // Calcular posição para alinhar a seta com o centro vertical do ícone
            // O ícone tem altura de 13px, então o centro está em ~6.5px
            // A seta está em top: 27px do popover, então precisamos ajustar
            const iconCenterY = rect.top + rect.height / 2;
            const popoverTop = iconCenterY - 37; // Usando position fixed, não precisa scrollY

            // Largura do popover (300px conforme CSS)
            const popoverWidth = 300;
            const offset = 27;
            const viewportWidth = window.innerWidth;

            // Tentar posicionar à direita primeiro
            let popoverLeft = rect.left + offset;
            let showOnLeft = false;

            // Verificar se ultrapassa a direita
            if (popoverLeft + popoverWidth > viewportWidth) {
                // Posicionar à esquerda do ícone
                popoverLeft = rect.right - popoverWidth - offset;
                showOnLeft = true;

                // Se ainda ultrapassar a esquerda, manter à direita mas ajustar
                if (popoverLeft < 0) {
                    popoverLeft = Math.max(10, rect.left + offset); // Mínimo 10px da borda
                    showOnLeft = false;
                }
            }

            // Verificar se ultrapassa a esquerda (quando posicionado à direita)
            if (!showOnLeft && popoverLeft < 0) {
                popoverLeft = 10; // Mínimo 10px da borda esquerda
            }

            // Mover popover para o body se ainda não estiver lá
            if (popover.parentElement !== document.body) {
                document.body.appendChild(popover);
            }

            // Remover classes de posicionamento anteriores
            popover.classList.remove('help-hint-popover-left', 'help-hint-popover-right');

            // Adicionar classe baseada na posição
            if (showOnLeft) {
                popover.classList.add('help-hint-popover-left');
            } else {
                popover.classList.add('help-hint-popover-right');
            }

            popover.style.position = 'fixed';
            popover.style.top = popoverTop + 'px';
            popover.style.left = popoverLeft + 'px';
            popover.classList.add('show');

            // Armazenar referências e adicionar listener de scroll
            this.activePopover = popover;
            this.activeHint = hint;

            // Criar handlers de scroll e resize se ainda não existirem
            if (!this.scrollHandler) {
                this.scrollHandler = () => this.updatePopoverPosition();
                window.addEventListener('scroll', this.scrollHandler, true); // true = capture phase para pegar scrolls em elementos filhos
            }
            if (!this.resizeHandler) {
                this.resizeHandler = () => this.updatePopoverPosition();
                window.addEventListener('resize', this.resizeHandler);
            }
        }
    },

    closeAll() {
        // Cancelar RAF pendente
        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = null;
        }

        // Remover listeners
        if (this.scrollHandler) {
            window.removeEventListener('scroll', this.scrollHandler, true);
            this.scrollHandler = null;
        }
        if (this.resizeHandler) {
            window.removeEventListener('resize', this.resizeHandler);
            this.resizeHandler = null;
        }

        // Limpar referências
        this.activePopover = null;
        this.activeHint = null;

        document.querySelectorAll('.help-hint-popover.show').forEach(p => {
            p.classList.remove('show', 'help-hint-popover-left', 'help-hint-popover-right');
            p.style.transform = '';
        });
    }
};

// Inicializar quando DOM estiver pronto
document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', () => HelpHint.init())
    : HelpHint.init();

// Exportar para uso global
window.HelpHint = HelpHint;

/**
 * Km - Formatacao de quilometragem
 */
const Km = {
    /**
     * Formata numero para exibicao (ex: 123456 -> "123.456")
     */
    format(value) {
        const num = parseInt(String(value).replace(/\D/g, '')) || 0;
        return num > 0 ? num.toLocaleString('pt-BR') : '0';
    },

    /**
     * Converte string formatada para numero (ex: "123.456" -> 123456)
     */
    parse(value) {
        if (typeof value === 'number') return value;
        return parseInt(String(value).replace(/\D/g, '')) || 0;
    },

    /**
     * Aplica mascara em input especifico
     */
    applyMask(input) {
        const element = typeof input === 'string' ? document.querySelector(input) : input;
        if (!element || element.dataset.kmMask) return;

        element.addEventListener('input', function (e) {
            const value = e.target.value.replace(/\D/g, '');
            const num = parseInt(value) || 0;
            e.target.value = num > 0 ? num.toLocaleString('pt-BR') : '';
        });

        // Formatar valor inicial
        if (element.value) {
            const num = parseInt(element.value.replace(/\D/g, '')) || 0;
            element.value = num > 0 ? num.toLocaleString('pt-BR') : '';
        }

        element.dataset.kmMask = 'true';
    },

    /**
     * Aplica mascara em todos inputs com a classe
     */
    applyMaskToAll(className = 'input-km') {
        document.querySelectorAll('input.' + className).forEach(input => {
            this.applyMask(input);
        });
    }
};

// Inicializar automaticamente
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Km.applyMaskToAll());
} else {
    Km.applyMaskToAll();
}

window.Km = Km;

/**
 * Str - Helpers de string
 * 
 * Versão JavaScript dos helpers de string. Para uso no PHP, veja str_limit() em helpers.php.
 * 
 * Mantém a mesma assinatura e comportamento do helper PHP para consistência entre backend e frontend.
 * 
 * @see app/Helpers/helpers.php - Função str_limit() para PHP
 */
const Str = {
    /**
     * Limita o tamanho de uma string
     * 
     * Equivalente JavaScript de str_limit() do PHP. Mantém a mesma assinatura e comportamento.
     * 
     * @param {string} value - String original
     * @param {number} limit - Tamanho maximo (padrao: 100)
     * @param {string} end - Sufixo quando truncado (padrao: '...')
     * @returns {string} String truncada ou original
     * 
     * @example
     * Str.limit('Texto muito longo', 10) // "Texto mui..."
     * Str.limit('Texto curto', 20) // "Texto curto"
     * 
     * @see app/Helpers/helpers.php - str_limit() para PHP
     */
    limit(value, limit = 100, end = '...') {
        if (!value) return '';
        const str = String(value);
        if (str.length <= limit) return str;
        return str.substring(0, limit) + end;
    }
};

// Exportar para uso global
window.Str = Str;

/**
 * FuelLabels - Labels dinamicos para niveis de combustivel/bateria
 *
 * Quando o tipo de combustivel eh eletrico (HE), exibe porcentagens ao inves de fracoes.
 * A escala numerica (0-8) no banco de dados nao muda.
 */
const FuelLabels = {
    /**
     * Verifica se o tipo de combustivel eh eletrico
     * @param {string} tipo - Codigo do tipo (HE, HI, G, GE, etc.)
     * @returns {boolean}
     */
    isElectric(tipo) {
        return tipo === 'HE';
    },

    /**
     * Retorna mapa de labels por nivel (0-8) baseado no tipo de combustivel
     * @param {string} tipo - Codigo do tipo de combustivel
     * @param {string} fullLabel - Label para "Cheio" (i18n)
     * @param {string} reserveLabel - Label para "Reserva" (i18n)
     * @returns {Object} Mapa { '0': label, '1': label, ..., '8': label }
     */
    getLevelLabels(tipo, fullLabel, reserveLabel) {
        if (this.isElectric(tipo)) {
            return {
                '8': '100%', '7': '87%', '6': '75%', '5': '62%',
                '4': '50%', '3': '37%', '2': '25%', '1': '12%', '0': '0%'
            };
        }
        return {
            '8': fullLabel || 'Cheio', '7': '7/8', '6': '3/4', '5': '5/8',
            '4': '1/2', '3': '3/8', '2': '1/4', '1': '1/8', '0': reserveLabel || 'Reserva'
        };
    },

    /**
     * Atualiza os textos das options de um select de nivel de combustivel/bateria
     * Preserva o valor selecionado.
     * @param {HTMLSelectElement} selectEl - Elemento select
     * @param {string} tipo - Codigo do tipo de combustivel
     * @param {string} fullLabel - Label i18n para "Cheio"
     * @param {string} reserveLabel - Label i18n para "Reserva"
     */
    updateSelectOptions(selectEl, tipo, fullLabel, reserveLabel) {
        const labels = this.getLevelLabels(tipo, fullLabel, reserveLabel);
        const currentValue = selectEl.value;
        Array.from(selectEl.options).forEach(opt => {
            if (opt.value !== '' && labels[opt.value] !== undefined) {
                opt.textContent = labels[opt.value];
            }
        });
        selectEl.value = currentValue;
    },

    /**
     * Retorna o label de exibicao para um nivel especifico
     * @param {string|number} level - Nivel (0-8)
     * @param {string} tipo - Codigo do tipo de combustivel
     * @param {string} fullLabel - Label i18n para "Cheio"
     * @param {string} reserveLabel - Label i18n para "Reserva"
     * @returns {string} Label de exibicao
     */
    getLabel(level, tipo, fullLabel, reserveLabel) {
        const labels = this.getLevelLabels(tipo, fullLabel, reserveLabel);
        return labels[String(level)] || '-';
    }
};

// Exportar para uso global
window.FuelLabels = FuelLabels;

/**
 * ActionMenu - Menu reutilizavel de acoes acionado por um botao compacto.
 */
const ActionMenu = {
    initialized: false,

    init() {
        if (this.initialized) return;
        this.initialized = true;

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-action-menu] .action-menu-trigger');
            if (trigger) {
                event.preventDefault();
                event.stopPropagation();
                this.toggle(trigger.closest('[data-action-menu]'));
                return;
            }

            if (event.target.closest('[data-action-menu] .action-menu-item')) {
                this.closeAll();
                return;
            }

            if (!event.target.closest('[data-action-menu]')) {
                this.closeAll();
            }
        });

        document.addEventListener('keydown', (event) => {
            const menu = event.target.closest('[data-action-menu]');

            if (event.key === 'Escape') {
                const openMenu = document.querySelector('[data-action-menu].is-open');
                if (openMenu) {
                    event.preventDefault();
                    const trigger = openMenu.querySelector('.action-menu-trigger');
                    this.closeAll();
                    trigger?.focus();
                }
                return;
            }

            if (!menu) return;

            const trigger = menu.querySelector('.action-menu-trigger');
            const items = Array.from(menu.querySelectorAll('.action-menu-item:not(:disabled)'));
            const currentIndex = items.indexOf(document.activeElement);

            if (event.target === trigger && ['ArrowDown', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                this.open(menu);
                items[0]?.focus();
            } else if (event.key === 'ArrowDown' && currentIndex >= 0) {
                event.preventDefault();
                items[(currentIndex + 1) % items.length]?.focus();
            } else if (event.key === 'ArrowUp' && currentIndex >= 0) {
                event.preventDefault();
                items[(currentIndex - 1 + items.length) % items.length]?.focus();
            } else if (event.key === 'Home' && currentIndex >= 0) {
                event.preventDefault();
                items[0]?.focus();
            } else if (event.key === 'End' && currentIndex >= 0) {
                event.preventDefault();
                items[items.length - 1]?.focus();
            }
        });
    },

    toggle(menu) {
        if (!menu) return;
        menu.classList.contains('is-open') ? this.close(menu) : this.open(menu);
    },

    open(menu) {
        this.closeAll(menu);
        menu.classList.add('is-open');
        menu.querySelector('.action-menu-trigger')?.setAttribute('aria-expanded', 'true');
    },

    close(menu) {
        if (!menu) return;
        menu.classList.remove('is-open');
        menu.querySelector('.action-menu-trigger')?.setAttribute('aria-expanded', 'false');
    },

    closeAll(except = null) {
        document.querySelectorAll('[data-action-menu].is-open').forEach((menu) => {
            if (menu !== except) this.close(menu);
        });
    }
};

document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', () => ActionMenu.init())
    : ActionMenu.init();

window.ActionMenu = ActionMenu;
