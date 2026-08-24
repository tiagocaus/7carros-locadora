<!-- Estrutura do header (barras superiores) -->
<header class="flex-shrink-0">
    <!-- Top Bar - Seletor de Sistemas -->
    <div class="top-bar text-white text-xs flex shadow-sm relative">
        <span class="top-bar-item top-bar-item-locadora active-system">{{ t('menu.topbar.rental') }}</span>
        <span class="top-bar-item top-bar-item-oficina">{{ t('menu.topbar.workshop') }}</span>
        <span class="top-bar-item top-bar-item-autopecas">{{ t('menu.topbar.parts') }}</span>
        <span class="top-bar-item top-bar-item-vistoria">{{ t('menu.topbar.inspection') }}</span>
        <span class="top-bar-item top-bar-item-revenda">{{ t('menu.topbar.resale') }}</span>
    </div>

    <!-- Main Navigation -->
    <nav class="main-nav text-white p-2 flex items-center justify-between shadow-md relative">
        <div class="flex items-center flex-shrink-0">
            <span class="text-3xl font-bold ml-2">7Carros</span>
        </div>

        <div class="w-full flex justify-between items-center ml-0 md:ml-6">
            <!-- Links principais -->
            <div id="mainNavLinks" class="main-nav-links hidden md:flex items-center space-x-1 text-sm">
                <!-- Sistema -->
                <div class="main-nav-item">
                    <a href="#" class="px-3 py-2 hover:bg-[#3578a0] flex items-center w-full">
                        {{ t('menu.sistema.title') }} <i class="fas fa-chevron-down fa-xs ml-1.5"></i>
                    </a>
                    <div class="submenu">
                        <a href="#" onclick="openOrSwitchToTab('/pages/programa-indicacao', '<?= t('menu.sistema.referral_program') ?>', 'fas fa-users'); return false;">{{ t('menu.sistema.referral_program') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/feature-requests', '<?= t('menu.sistema.feature_request') ?>', 'fas fa-lightbulb'); return false;">{{ t('menu.sistema.feature_request') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/logs', '<?= t('menu.sistema.activity_logs') ?>', 'fas fa-history'); return false;">{{ t('menu.sistema.activity_logs') }}</a>
                        <?php if (\App\Core\Auth::can('suporte.gerenciar') || \App\Core\Auth::can('configuracoes.editar')): ?>
                            <a href="#" onclick="openOrSwitchToTab('/pages/conceder-acesso', '<?= t('menu.sistema.grant_access') ?>', 'fas fa-user-shield'); return false;">{{ t('menu.sistema.grant_access') }}</a>
                        <?php endif; ?>
                        <a href="#" onclick="openOrSwitchToTab('/pages/configuracoes/gerais', '<?= t('menu.sistema.settings') ?>', 'fas fa-cog'); return false;">{{ t('menu.sistema.settings') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/configuracoes/templates', '<?= t('menu.sistema.message_templates') ?>', 'fas fa-envelope'); return false;">{{ t('menu.sistema.message_templates') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/changelog', '<?= t('menu.sistema.changelog') ?>', 'fas fa-list-alt'); return false;">{{ t('menu.sistema.changelog') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/gravacoes', '<?= t('menu.sistema.screen_recording') ?>', 'fas fa-video'); return false;">{{ t('menu.sistema.screen_recording') }}</a>
                        <a href="/logout" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">{{ t('menu.sistema.logout') }}</a>
                    </div>
                </div>

                <!-- Contrato/Locações -->
                <div class="main-nav-item">
                    <a href="#" class="px-3 py-2 hover:bg-[#3578a0] flex items-center w-full">
                        {{ t('menu.contratos_loc.title') }} <i class="fas fa-chevron-down fa-xs ml-1.5"></i>
                    </a>
                    <div class="submenu">
                        <?php if (\App\Core\Auth::can('orcamentos.criar')): ?>
                        <a href="#" onclick="openOrSwitchToTab('/pages/orcamentos/adicionar', '<?= t('menu.contratos_loc.new_quote') ?>', 'fas fa-file-invoice'); return false;">{{ t('menu.contratos_loc.new_quote') }}</a>
                        <?php endif; ?>
                        <?php if (\App\Core\Auth::can('orcamentos.visualizar')): ?>
                        <a href="#" onclick="openOrSwitchToTab('/pages/orcamentos', '<?= t('menu.contratos_loc.quotes') ?>', 'fas fa-file-invoice'); return false;">{{ t('menu.contratos_loc.quotes') }}</a>
                        <div class="submenu-divider"></div>
                        <?php endif; ?>
                        <a href="#" onclick="openOrSwitchToTab('/pages/locacoes/adicionar', '<?= t('menu.contratos_loc.new_rental') ?>', 'fas fa-file-invoice-dollar'); return false;">{{ t('menu.contratos_loc.new_rental') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/locacoes', '<?= t('menu.contratos_loc.rentals_reservations') ?>', 'fas fa-file-invoice-dollar'); return false;">{{ t('menu.contratos_loc.rentals_reservations') }}</a>
                        <div class="submenu-divider"></div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/contratos/adicionar', '<?= t('menu.contratos_loc.new_contract') ?>', 'fas fa-file-signature'); return false;">{{ t('menu.contratos_loc.new_contract') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/contratos', '<?= t('menu.contratos_loc.contracts') ?>', 'fas fa-file-signature'); return false;">{{ t('menu.contratos_loc.contracts') }}</a>
                    </div>
                </div>

                <!-- Empresa -->
                <div class="main-nav-item">
                    <a href="#" class="px-3 py-2 hover:bg-[#3578a0] flex items-center w-full">
                        {{ t('menu.empresa.title') }} <i class="fas fa-chevron-down fa-xs ml-1.5"></i>
                    </a>
                    <div class="submenu">
                        <a href="#" onclick="openOrSwitchToTab('/pages/matrizes-filiais', '<?= t('menu.empresa.branches') ?>', 'fas fa-building'); return false;">{{ t('menu.empresa.branches') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/clientes', '<?= t('menu.empresa.clients') ?>', 'fas fa-users'); return false;">{{ t('menu.empresa.clients') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/mensageria', '<?= t('menu.empresa.messaging') ?>', 'fas fa-comments'); return false;">{{ t('menu.empresa.messaging') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/funcionarios', '<?= t('menu.empresa.employees') ?>', 'fas fa-id-badge'); return false;">{{ t('menu.empresa.employees') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/documentos', '<?= t('menu.empresa.documents') ?>', 'fas fa-file-alt'); return false;">{{ t('menu.empresa.documents') }}</a>
                        <div class="submenu-divider"></div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/taxas-e-servicos', '<?= t('menu.empresa.fees_services') ?>', 'fas fa-receipt'); return false;">{{ t('menu.empresa.fees_services') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/oficinas', '<?= t('menu.empresa.workshops') ?>', 'fas fa-wrench'); return false;">{{ t('menu.empresa.workshops') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/promocoes', '<?= t('menu.empresa.promotions') ?>', 'fas fa-tags'); return false;">{{ t('menu.empresa.promotions') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/central-multas', '<?= t('menu.empresa.fines_central') ?>', 'fas fa-gavel'); return false;">{{ t('menu.empresa.fines_central') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/contas-bancarias', '<?= t('menu.empresa.bank_accounts') ?>', 'fas fa-university'); return false;">{{ t('menu.empresa.bank_accounts') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/formas-pagamento', '<?= t('menu.empresa.payment_methods') ?>', 'fas fa-credit-card'); return false;">{{ t('menu.empresa.payment_methods') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/gateways-pagamento', '<?= t('menu.empresa.payment_gateways') ?>', 'fas fa-plug'); return false;">{{ t('menu.empresa.payment_gateways') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/planos-de-contas', '<?= t('modules.planos_contas.title') ?>', 'fas fa-sitemap'); return false;">{{ t('modules.planos_contas.title') }}</a>
                        <div class="submenu-divider"></div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/fornecedores', '<?= t('menu.empresa.suppliers') ?>', 'fas fa-truck'); return false;">{{ t('menu.empresa.suppliers') }}</a>
                        <div class="submenu-divider"></div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/estoque', '<?= t('menu.empresa.inventory') ?>', 'fas fa-boxes'); return false;">{{ t('menu.empresa.inventory') }}</a>
                    </div>
                </div>

                <!-- Veículos -->
                <div class="main-nav-item">
                    <a href="#" class="px-3 py-2 hover:bg-[#3578a0] flex items-center w-full">
                        {{ t('menu.veiculos_menu.title') }} <i class="fas fa-chevron-down fa-xs ml-1.5"></i>
                    </a>
                    <div class="submenu">
                        <a href="#" onclick="openOrSwitchToTab('/pages/veiculos', '<?= t('menu.veiculos_menu.vehicles') ?>', 'fas fa-car-side'); return false;">{{ t('menu.veiculos_menu.vehicles') }}</a>
                        <div class="submenu-divider"></div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/grupos', '<?= t('menu.veiculos_menu.groups') ?>', 'fas fa-layer-group'); return false;">{{ t('menu.veiculos_menu.groups') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/temporadas', '<?= t('menu.veiculos_menu.seasons') ?>', 'fas fa-calendar-alt'); return false;">{{ t('menu.veiculos_menu.seasons') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/veiculos-acessorios', '<?= t('menu.veiculos_menu.accessories') ?>', 'fas fa-car-side'); return false;">{{ t('menu.veiculos_menu.accessories') }}</a>
                        <div class="submenu-divider"></div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/manutencoes', '<?= t('menu.veiculos_menu.maintenance') ?>', 'fas fa-tools'); return false;">{{ t('menu.veiculos_menu.maintenance') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/manutencoes-planos', '<?= t('menu.veiculos_menu.maintenance_plans') ?>', 'fas fa-tools'); return false;">{{ t('menu.veiculos_menu.maintenance_plans') }}</a>
                        <div class="submenu-divider"></div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/checklists', '<?= t('menu.veiculos_menu.checklist') ?>', 'fas fa-clipboard-check'); return false;">{{ t('menu.veiculos_menu.checklist') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/checklist-modelos', '<?= t('menu.veiculos_menu.checklist_templates') ?>', 'fas fa-list-check'); return false;">{{ t('menu.veiculos_menu.checklist_templates') }}</a>
                    </div>
                </div>

                <!-- Relatórios -->
                <div class="main-nav-item">
                    <a href="#" class="px-3 py-2 hover:bg-[#3578a0] flex items-center w-full">
                        {{ t('menu.relatorios_menu.title') }} <i class="fas fa-chevron-down fa-xs ml-1.5"></i>
                    </a>
                    <div class="submenu submenu-multilevel">
                        <!-- KPIs / Indicadores de Desempenho -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.kpis') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/kpis/taxa-ocupacao', '<?= t('menu.relatorios_menu.kpi_occupancy_rate') ?>', 'fas fa-chart-pie'); return false;">{{ t('menu.relatorios_menu.kpi_occupancy_rate') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/kpis/revpar', '<?= t('menu.relatorios_menu.kpi_revpar') ?>', 'fas fa-dollar-sign'); return false;">{{ t('menu.relatorios_menu.kpi_revpar') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/kpis/adr', '<?= t('menu.relatorios_menu.kpi_adr') ?>', 'fas fa-calculator'); return false;">{{ t('menu.relatorios_menu.kpi_adr') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/kpis/margem-bruta', '<?= t('menu.relatorios_menu.kpi_gross_margin') ?>', 'fas fa-chart-line'); return false;">{{ t('menu.relatorios_menu.kpi_gross_margin') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/kpis/receita-veiculo', '<?= t('menu.relatorios_menu.kpi_revenue_vehicle') ?>', 'fas fa-car-side'); return false;">{{ t('menu.relatorios_menu.kpi_revenue_vehicle') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/kpis/receitas-adicionais', '<?= t('menu.relatorios_menu.kpi_additional_revenue') ?>', 'fas fa-plus-circle'); return false;">{{ t('menu.relatorios_menu.kpi_additional_revenue') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/kpis/tempo-medio', '<?= t('menu.relatorios_menu.kpi_avg_rental_time') ?>', 'fas fa-clock'); return false;">{{ t('menu.relatorios_menu.kpi_avg_rental_time') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/kpis/roi-veiculo', '<?= t('menu.relatorios_menu.kpi_roi_vehicle') ?>', 'fas fa-percentage'); return false;">{{ t('menu.relatorios_menu.kpi_roi_vehicle') }}</a>
                            </div>
                        </div>
                        <!-- Financeiro -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.financial') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/movimentacoes', '<?= t('menu.relatorios_menu.fin_detailed') ?>', 'fas fa-exchange-alt'); return false;">{{ t('menu.relatorios_menu.fin_detailed') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/caucoes', '<?= t('menu.relatorios_menu.fin_deposits') ?>', 'fas fa-shield-alt'); return false;">{{ t('menu.relatorios_menu.fin_deposits') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/faturamento', '<?= t('menu.relatorios_menu.fin_billing') ?>', 'fas fa-file-invoice-dollar'); return false;">{{ t('menu.relatorios_menu.fin_billing') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/dre', '<?= t('menu.relatorios_menu.fin_income_statement') ?>', 'fas fa-balance-scale'); return false;">{{ t('menu.relatorios_menu.fin_income_statement') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/resultado-caixa', '<?= t('menu.relatorios_menu.fin_cash_result') ?>', 'fas fa-coins'); return false;">{{ t('menu.relatorios_menu.fin_cash_result') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/livro-caixa', '<?= t('menu.relatorios_menu.fin_cashbook') ?>', 'fas fa-book'); return false;">{{ t('menu.relatorios_menu.fin_cashbook') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/contas-bancarias', '<?= t('menu.relatorios_menu.fin_bank_accounts') ?>', 'fas fa-university'); return false;">{{ t('menu.relatorios_menu.fin_bank_accounts') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/plano-contas', '<?= t('menu.relatorios_menu.fin_chart_accounts') ?>', 'fas fa-sitemap'); return false;">{{ t('menu.relatorios_menu.fin_chart_accounts') }}</a>

                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/projecao-receitas', '<?= t('menu.relatorios_menu.fin_revenue_projection') ?>', 'fas fa-chart-line'); return false;">{{ t('menu.relatorios_menu.fin_revenue_projection') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/rentabilidade', '<?= t('menu.relatorios_menu.fin_profitability') ?>', 'fas fa-chart-bar'); return false;">{{ t('menu.relatorios_menu.fin_profitability') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/inadimplencia', '<?= t('menu.relatorios_menu.fin_delinquency') ?>', 'fas fa-exclamation-triangle'); return false;">{{ t('menu.relatorios_menu.fin_delinquency') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/financeiro/taxas-servicos', '<?= t('menu.relatorios_menu.fin_fees_charged') ?>', 'fas fa-receipt'); return false;">{{ t('menu.relatorios_menu.fin_fees_charged') }}</a>
                            </div>
                        </div>
                        <!-- Veicular -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.vehicle') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/manutencoes', '<?= t('menu.relatorios_menu.veh_maintenance') ?>', 'fas fa-wrench'); return false;">{{ t('menu.relatorios_menu.veh_maintenance') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/lucro-veiculo', '<?= t('menu.relatorios_menu.veh_profit') ?>', 'fas fa-coins'); return false;">{{ t('menu.relatorios_menu.veh_profit') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/despesas', '<?= t('menu.relatorios_menu.veh_expenses') ?>', 'fas fa-money-bill-wave'); return false;">{{ t('menu.relatorios_menu.veh_expenses') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/veiculo-cliente', '<?= t('menu.relatorios_menu.veh_client') ?>', 'fas fa-user-friends'); return false;">{{ t('menu.relatorios_menu.veh_client') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/licenciamento', '<?= t('menu.relatorios_menu.veh_licensing') ?>', 'fas fa-file-contract'); return false;">{{ t('menu.relatorios_menu.veh_licensing') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/disponibilidade', '<?= t('menu.relatorios_menu.veh_availability') ?>', 'fas fa-tachometer-alt'); return false;">{{ t('menu.relatorios_menu.veh_availability') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/ocupacao-grupo', '<?= t('menu.relatorios_menu.veh_group_occupancy') ?>', 'fas fa-layer-group'); return false;">{{ t('menu.relatorios_menu.veh_group_occupancy') }}</a>

                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/depreciacao', '<?= t('menu.relatorios_menu.veh_depreciation') ?>', 'fas fa-chart-line'); return false;">{{ t('menu.relatorios_menu.veh_depreciation') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/tempo-parado', '<?= t('menu.relatorios_menu.veh_avg_idle_time') ?>', 'fas fa-hourglass-half'); return false;">{{ t('menu.relatorios_menu.veh_avg_idle_time') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/quilometragem-media', '<?= t('menu.relatorios_menu.veh_avg_mileage') ?>', 'fas fa-road'); return false;">{{ t('menu.relatorios_menu.veh_avg_mileage') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/evolucao-quilometragem', '<?= t('menu.relatorios_menu.veh_mileage_evolution') ?>', 'fas fa-chart-line'); return false;">{{ t('menu.relatorios_menu.veh_mileage_evolution') }}</a>

                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/veicular/tco', '<?= t('menu.relatorios_menu.veh_total_cost') ?>', 'fas fa-coins'); return false;">{{ t('menu.relatorios_menu.veh_total_cost') }}</a>
                            </div>
                        </div>
                        <!-- Clientes -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.clients') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/clientes/por-cliente', '<?= t('menu.relatorios_menu.cli_contracts_rentals') ?>', 'fas fa-users'); return false;">{{ t('menu.relatorios_menu.cli_contracts_rentals') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/clientes/aniversariantes', '<?= t('menu.relatorios_menu.cli_birthdays') ?>', 'fas fa-birthday-cake'); return false;">{{ t('menu.relatorios_menu.cli_birthdays') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/clientes/cnh-vencidas', '<?= t('menu.relatorios_menu.cli_expired_license') ?>', 'fas fa-id-card'); return false;">{{ t('menu.relatorios_menu.cli_expired_license') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/clientes/top-clientes', '<?= t('menu.relatorios_menu.cli_top_clients') ?>', 'fas fa-trophy'); return false;">{{ t('menu.relatorios_menu.cli_top_clients') }}</a>

                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/clientes/frequencia', '<?= t('menu.relatorios_menu.cli_rental_frequency') ?>', 'fas fa-chart-pie'); return false;">{{ t('menu.relatorios_menu.cli_rental_frequency') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/clientes/tempo-relacionamento', '<?= t('menu.relatorios_menu.cli_relationship_time') ?>', 'fas fa-hourglass-half'); return false;">{{ t('menu.relatorios_menu.cli_relationship_time') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/clientes/ocorrencias', '<?= t('menu.relatorios_menu.cli_incident_history') ?>', 'fas fa-exclamation-circle'); return false;">{{ t('menu.relatorios_menu.cli_incident_history') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/clientes/inativos', '<?= t('menu.relatorios_menu.cli_inactive') ?>', 'fas fa-user-slash'); return false;">{{ t('menu.relatorios_menu.cli_inactive') }}</a>
                            </div>
                        </div>
                        <!-- Contratos/Locações -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.contracts_rentals') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/contratos/visao-geral', '<?= t('menu.relatorios_menu.cr_general') ?>', 'fas fa-file-contract'); return false;">{{ t('menu.relatorios_menu.cr_general') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/contratos/por-periodo', '<?= t('menu.relatorios_menu.cr_by_period') ?>', 'fas fa-chart-line'); return false;">{{ t('menu.relatorios_menu.cr_by_period') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/contratos/por-forma-pagamento', '<?= t('menu.relatorios_menu.cr_by_payment') ?>', 'fas fa-credit-card'); return false;">{{ t('menu.relatorios_menu.cr_by_payment') }}</a>

                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/contratos/extensoes', '<?= t('menu.relatorios_menu.cr_extensions') ?>', 'fas fa-clock'); return false;">{{ t('menu.relatorios_menu.cr_extensions') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/contratos/trocas-veiculo', '<?= t('menu.relatorios_menu.cr_vehicle_swap') ?>', 'fas fa-exchange-alt'); return false;">{{ t('menu.relatorios_menu.cr_vehicle_swap') }}</a>
                            </div>
                        </div>
                        <!-- Operacional -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.operational') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/operacional/checklists-realizados', '<?= t('menu.relatorios_menu.op_checklists') ?>', 'fas fa-clipboard-check'); return false;">{{ t('menu.relatorios_menu.op_checklists') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/operacional/avarias-sinistros', '<?= t('menu.relatorios_menu.op_damages') ?>', 'fas fa-car-crash'); return false;">{{ t('menu.relatorios_menu.op_damages') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/operacional/multas-transito', '<?= t('menu.relatorios_menu.op_traffic_fines') ?>', 'fas fa-traffic-light'); return false;">{{ t('menu.relatorios_menu.op_traffic_fines') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/operacional/devolucoes-antecipadas', '<?= t('menu.relatorios_menu.op_early_returns') ?>', 'fas fa-undo'); return false;">{{ t('menu.relatorios_menu.op_early_returns') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/operacional/devolucoes-atrasadas', '<?= t('menu.relatorios_menu.op_late_returns') ?>', 'fas fa-history'); return false;">{{ t('menu.relatorios_menu.op_late_returns') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/operacional/reservas-canceladas', '<?= t('menu.relatorios_menu.op_cancelled_reservations') ?>', 'fas fa-times-circle'); return false;">{{ t('menu.relatorios_menu.op_cancelled_reservations') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/operacional/turnaround', '<?= t('menu.relatorios_menu.op_turnaround') ?>', 'fas fa-sync'); return false;">{{ t('menu.relatorios_menu.op_turnaround') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/operacional/combustivel', '<?= t('menu.relatorios_menu.op_fuel') ?>', 'fas fa-gas-pump'); return false;">{{ t('menu.relatorios_menu.op_fuel') }}</a>
                            </div>
                        </div>
                        <!-- Faturas -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.invoices') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/faturas/vencidas-a-vencer', '<?= t('menu.relatorios_menu.inv_due_upcoming') ?>', 'fas fa-file-invoice-dollar'); return false;">{{ t('menu.relatorios_menu.inv_due_upcoming') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/faturas/por-veiculo', '<?= t('menu.relatorios_menu.inv_by_vehicle') ?>', 'fas fa-car-side'); return false;">{{ t('menu.relatorios_menu.inv_by_vehicle') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/faturas/pagar-receber', '<?= t('menu.relatorios_menu.inv_payable_receivable') ?>', 'fas fa-balance-scale'); return false;">{{ t('menu.relatorios_menu.inv_payable_receivable') }}</a>
                            </div>
                        </div>
                        <!-- Comercial / Marketing -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.commercial') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/comercial/taxa-conversao', '<?= t('menu.relatorios_menu.com_conversion_rate') ?>', 'fas fa-percentage'); return false;">{{ t('menu.relatorios_menu.com_conversion_rate') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/comercial/origem-locacoes', '<?= t('menu.relatorios_menu.com_rental_origin') ?>', 'fas fa-map-signs'); return false;">{{ t('menu.relatorios_menu.com_rental_origin') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/comercial/promocoes', '<?= t('menu.relatorios_menu.com_promotions_used') ?>', 'fas fa-tags'); return false;">{{ t('menu.relatorios_menu.com_promotions_used') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/comercial/descontos', '<?= t('menu.relatorios_menu.com_discounts_given') ?>', 'fas fa-arrow-down'); return false;">{{ t('menu.relatorios_menu.com_discounts_given') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/comercial/temporada', '<?= t('menu.relatorios_menu.com_season_analysis') ?>', 'fas fa-umbrella-beach'); return false;">{{ t('menu.relatorios_menu.com_season_analysis') }}</a>
                            </div>
                        </div>
                        <!-- Fornecedores -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.suppliers') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/fornecedores/compras', '<?= t('menu.relatorios_menu.sup_suppliers') ?>', 'fas fa-truck'); return false;">{{ t('menu.relatorios_menu.sup_suppliers') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/fornecedores/investidor', '<?= t('menu.relatorios_menu.sup_investor') ?>', 'fas fa-handshake'); return false;">{{ t('menu.relatorios_menu.sup_investor') }}</a>
                            </div>
                        </div>
                        <!-- Funcionários -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.employees') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/funcionarios/vendas', '<?= t('menu.relatorios_menu.emp_sales') ?>', 'fas fa-chart-line'); return false;">{{ t('menu.relatorios_menu.emp_sales') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/funcionarios/comissoes', '<?= t('menu.relatorios_menu.emp_commissions') ?>', 'fas fa-percentage'); return false;">{{ t('menu.relatorios_menu.emp_commissions') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/funcionarios/produtividade', '<?= t('menu.relatorios_menu.emp_productivity') ?>', 'fas fa-tachometer-alt'); return false;">{{ t('menu.relatorios_menu.emp_productivity') }}</a>

                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/funcionarios/metas', '<?= t('menu.relatorios_menu.emp_goals') ?>', 'fas fa-bullseye'); return false;">{{ t('menu.relatorios_menu.emp_goals') }}</a>
                            </div>
                        </div>
                        <!-- Comparativos / Gerenciais -->
                        <div class="submenu-item-with-submenu">
                            <a href="#" class="submenu-parent"><span>{{ t('menu.relatorios_menu.comparisons') }}</span><i class="fas fa-chevron-right fa-xs"></i></a>
                            <div class="submenu-level-2">
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/comparativos/mensal-anual', '<?= t('menu.relatorios_menu.comp_monthly_annual') ?>', 'fas fa-calendar-alt'); return false;">{{ t('menu.relatorios_menu.comp_monthly_annual') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/comparativos/filiais', '<?= t('menu.relatorios_menu.comp_between_branches') ?>', 'fas fa-building'); return false;">{{ t('menu.relatorios_menu.comp_between_branches') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/comparativos/ranking-veiculos', '<?= t('menu.relatorios_menu.comp_vehicle_ranking') ?>', 'fas fa-trophy'); return false;">{{ t('menu.relatorios_menu.comp_vehicle_ranking') }}</a>
                                <a href="#" onclick="openOrSwitchToTab('/pages/relatorios/comparativos/tendencias', '<?= t('menu.relatorios_menu.comp_trends') ?>', 'fas fa-chart-line'); return false;">{{ t('menu.relatorios_menu.comp_trends') }}</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financeiro -->
                <div class="main-nav-item">
                    <a href="#" class="px-3 py-2 hover:bg-[#3578a0] flex items-center w-full">
                        {{ t('menu.financeiro_menu.title') }} <i class="fas fa-chevron-down fa-xs ml-1.5"></i>
                    </a>
                    <div class="submenu">
                        <a href="#" onclick="openOrSwitchToTab('/pages/financeiro', '<?= t('menu.financeiro_menu.entries') ?>', 'fas fa-file-invoice-dollar'); return false;">{{ t('menu.financeiro_menu.entries') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/financeiro/adicionar', '<?= t('menu.financeiro_menu.new_entry') ?>', 'fas fa-plus-circle'); return false;">{{ t('menu.financeiro_menu.new_entry') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/promissorias', '<?= t('menu.financeiro_menu.promissory_notes') ?>', 'fas fa-file-signature'); return false;">{{ t('menu.financeiro_menu.promissory_notes') }}</a>
                        <div class="submenu-divider"></div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/comissoes-investidores', '<?= t('menu.financeiro_menu.investor_commissions') ?>', 'fas fa-hand-holding-usd'); return false;">{{ t('menu.financeiro_menu.investor_commissions') }}</a>
                        <?php if (\App\Core\Auth::can('nfse.visualizar')): ?>
                        <div class="submenu-divider"></div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/nfse', '<?= t('modules.nfse.title_singular') ?>', 'fas fa-file-invoice'); return false;">{{ t('modules.nfse.title_singular') }}</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- WebSite -->
                <div class="main-nav-item">
                    <a href="#" class="px-3 py-2 hover:bg-[#3578a0] flex items-center w-full">
                        {{ t('menu.website.title') }} <i class="fas fa-chevron-down fa-xs ml-1.5"></i>
                    </a>
                    <div class="submenu">
                        <?php $siteStatus = \App\Models\SiteConfig::getStatus(); ?>
                        <?php if ($siteStatus === 'ativo'): ?>
                        <a href="#" onclick="openOrSwitchToTab('/pages/website/configuracoes', '<?= t('menu.website.settings') ?>', 'fas fa-cog'); return false;">{{ t('menu.website.settings') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/website/aparencia', '<?= t('menu.website.appearance') ?>', 'fas fa-palette'); return false;">{{ t('menu.website.appearance') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/website/conteudos', '<?= t('menu.website.contents') ?>', 'fas fa-file-alt'); return false;">{{ t('menu.website.contents') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/website/banners', '<?= t('menu.website.banners') ?>', 'fas fa-images'); return false;">{{ t('menu.website.banners') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/website/seo', '<?= t('menu.website.seo') ?>', 'fas fa-search'); return false;">{{ t('menu.website.seo') }}</a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/website/integracoes', '<?= t('menu.website.integrations') ?>', 'fas fa-code'); return false;">{{ t('menu.website.integrations') }}</a>
                        <div class="submenu-divider"></div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/website/publicar', '<?= t('menu.website.publish') ?>', 'fas fa-cloud-upload-alt'); return false;">{{ t('menu.website.publish') }}</a>
                        <?php else: ?>
                        <a href="#" onclick="openOrSwitchToTab('/pages/website/ativar', '<?= t('menu.website.activate') ?>', 'fas fa-globe'); return false;">{{ t('menu.website.activate') }}</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Lado direito: Idioma, Notificações, Perfil -->
            @php
                $currentLocale = current_locale();
                $localeInfo = locale_info($currentLocale);
                $currentCountryCode = strtolower(substr($currentLocale, -2));
                $currentFlagUrl = asset('vendor/flag-icons/flags/4x3/' . $currentCountryCode . '.svg');
                $supportedLocales = supported_locales();
            @endphp
            <div class="flex items-center space-x-1 md:space-x-2 ml-2 md:ml-0 relative">
                <!-- Dropdown de idioma -->
                <div class="nav-dropdown-container">
                    <button id="languageButton" class="p-2 rounded-full hover:bg-[#3578a0] focus:outline-none" title="{{ t('menu.tooltips.select_language') }}">
                        <img class="flag-icon-active" id="activeLanguageFlag"
                             src="<?= htmlspecialchars($currentFlagUrl, ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($localeInfo['name'] ?? $currentLocale, ENT_QUOTES, 'UTF-8') ?>">
                    </button>
                    <div id="languageDropdown" class="nav-dropdown">
                        <div class="dropdown-header"><i class="fas fa-check text-green-500"></i><?= t('common.labels.select_language') ?></div>
                        @foreach($supportedLocales as $localeCode => $info)
                            @php
                                $localeCountryCode = strtolower(substr($localeCode, -2));
                                $localeFlagUrl = asset('vendor/flag-icons/flags/4x3/' . $localeCountryCode . '.svg');
                            @endphp
                            <a href="#" class="dropdown-item<?= $localeCode === $currentLocale ? ' active' : '' ?>"
                               data-lang="<?= htmlspecialchars($info['code'], ENT_QUOTES, 'UTF-8') ?>"
                               data-locale="<?= htmlspecialchars($localeCode, ENT_QUOTES, 'UTF-8') ?>"
                               data-flag-src="<?= htmlspecialchars($localeFlagUrl, ENT_QUOTES, 'UTF-8') ?>"
                               data-flag-alt="<?= htmlspecialchars($info['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <span><?= $info['name'] ?></span>
                                <img class="flag-icon flag-icon-sm"
                                     src="<?= htmlspecialchars($localeFlagUrl, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="" aria-hidden="true">
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Dropdown de notificações -->
                @php
                    $notifTotal = $notifications['total'] ?? 0;
                @endphp
                <div class="nav-dropdown-container">
                    <button id="notificationsButton" class="p-2 rounded-full hover:bg-[#3578a0] focus:outline-none flex items-center" title="{{ t('menu.tooltips.notifications') }}">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notifBadgeTotal"><?= $notifTotal ?></span>
                    </button>
                    <div id="notificationsDropdown" class="nav-dropdown">
                        <div class="dropdown-header bg-pink-100 text-pink-700"><i class="fas fa-exclamation-triangle text-pink-600"></i><span id="notifHeaderTotal"><?= $notifTotal ?></span> {{ t('menu.notifications.title') }}</div>
                        <a href="#" onclick="openOrSwitchToTab('/pages/notificacoes?categoria=manutencao', '<?= t('menu.notifications.maintenance') ?>', 'fas fa-tools'); return false;" class="dropdown-item">
                            <div class="flex items-center"><i class="fas fa-tools text-slate-500 mr-2"></i>{{ t('menu.notifications.maintenance') }}</div><span class="badge<?= ($notifications['manutencoes'] ?? 0) > 0 ? ' bg-red-200 text-red-700' : '' ?>" data-notification-key="manutencoes"><?= $notifications['manutencoes'] ?? 0 ?></span>
                        </a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/notificacoes?categoria=tarefa', '<?= t('menu.notifications.tasks') ?>', 'fas fa-calendar-check'); return false;" class="dropdown-item">
                            <div class="flex items-center"><i class="fas fa-calendar-check text-slate-500 mr-2"></i>{{ t('menu.notifications.tasks') }}</div><span class="badge<?= ($notifications['tarefas'] ?? 0) > 0 ? ' bg-red-200 text-red-700' : '' ?>" data-notification-key="tarefas"><?= $notifications['tarefas'] ?? 0 ?></span>
                        </a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/notificacoes?categoria=fatura', '<?= t('menu.notifications.overdue_invoices') ?>', 'fas fa-file-invoice-dollar'); return false;" class="dropdown-item">
                            <div class="flex items-center"><i class="fas fa-file-invoice-dollar text-slate-500 mr-2"></i>{{ t('menu.notifications.overdue_invoices') }}</div><span class="badge<?= ($notifications['faturas_vencidas'] ?? 0) > 0 ? ' bg-red-200 text-red-700' : '' ?>" data-notification-key="faturas_vencidas"><?= $notifications['faturas_vencidas'] ?? 0 ?></span>
                        </a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/notificacoes?categoria=caucao', '<?= t('menu.notifications.deposits') ?>', 'fas fa-shield-alt'); return false;" class="dropdown-item">
                            <div class="flex items-center"><i class="fas fa-shield-alt text-slate-500 mr-2"></i>{{ t('menu.notifications.deposits') }}</div><span class="badge<?= ($notifications['caucoes'] ?? 0) > 0 ? ' bg-red-200 text-red-700' : '' ?>" data-notification-key="caucoes"><?= $notifications['caucoes'] ?? 0 ?></span>
                        </a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/notificacoes?categoria=licenciamento', '<?= t('menu.notifications.licensing') ?>', 'fas fa-id-card'); return false;" class="dropdown-item">
                            <div class="flex items-center"><i class="fas fa-dollar-sign text-slate-500 mr-2"></i>{{ t('menu.notifications.licensing') }}</div><span class="badge<?= ($notifications['licenciamento'] ?? 0) > 0 ? ' bg-red-200 text-red-700' : '' ?>" data-notification-key="licenciamento"><?= $notifications['licenciamento'] ?? 0 ?></span>
                        </a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/notificacoes?categoria=cnh', '<?= t('menu.notifications.expired_license') ?>', 'fas fa-id-badge'); return false;" class="dropdown-item">
                            <div class="flex items-center"><i class="fas fa-id-card text-slate-500 mr-2"></i>{{ t('menu.notifications.expired_license') }}</div><span class="badge<?= ($notifications['cnh_vencidas'] ?? 0) > 0 ? ' bg-red-200 text-red-700' : '' ?>" data-notification-key="cnh_vencidas"><?= $notifications['cnh_vencidas'] ?? 0 ?></span>
                        </a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/notificacoes?categoria=problema', '<?= t('menu.notifications.problems') ?>', 'fas fa-exclamation-circle'); return false;" class="dropdown-item">
                            <div class="flex items-center"><i class="fas fa-exclamation-circle text-red-500 mr-2"></i>{{ t('menu.notifications.problems') }}</div><span class="badge<?= ($notifications['problemas'] ?? 0) > 0 ? ' bg-red-200 text-red-700' : '' ?>" data-notification-key="problemas"><?= $notifications['problemas'] ?? 0 ?></span>
                        </a>
                        <a href="#" onclick="openOrSwitchToTab('/pages/notificacoes', '<?= t('menu.notifications.all_notifications') ?>', 'fas fa-bell'); return false;" class="dropdown-footer">{{ t('menu.notifications.all_notifications') }} <i class="fas fa-arrow-right fa-xs ml-1"></i></a>
                    </div>
                </div>

                <!-- Botões de Perfil e Sair -->
                <button id="btnPerfilUsuario" class="p-1 rounded-full hover:bg-[#3578a0] focus:outline-none hidden md:inline-flex" title="{{ t('menu.tooltips.user_profile') }}">
                    <i class="fas fa-user-circle text-2xl"></i>
                </button>
                <button id="btnLogoutUsuario" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="p-2 rounded-full hover:bg-[#3578a0] focus:outline-none hidden md:inline-flex" title="{{ t('menu.tooltips.logout') }}">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>
        <button id="hamburgerButton" class="md:hidden p-2 rounded-md hover:bg-[#3578a0] focus:outline-none">
            <i class="fas fa-bars text-xl"></i>
        </button>
    </nav>

    <!-- Secondary Navigation - Atalhos rápidos -->
    <div class="secondary-nav py-1.5 px-4 hidden md:flex items-center justify-start space-x-1.5 shadow-sm overflow-x-auto">
        <button id="sidebarToggleBtn" class="sidebar-toggle-btn" title="{{ t('menu.secondary_nav.sidebar_mode') }}">
            <i class="fas fa-bars"></i>
            <span class="hidden sm:inline">{{ t('menu.secondary_nav.sidebar_mode') }}</span>
        </button>
        <button class="icon-button" onclick="openOrSwitchToTab('/pages/locacoes', '<?= t('menu.secondary_nav.rentals') ?>', 'fas fa-file-invoice-dollar'); return false;" data-tab-name="<?= t('menu.secondary_nav.rentals') ?>" data-tab-icon="fas fa-file-invoice-dollar">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>{{ t('menu.secondary_nav.rentals') }}</span>
        </button>
        <button class="icon-button" onclick="openOrSwitchToTab('/pages/contratos', '<?= t('menu.secondary_nav.contracts') ?>', 'fas fa-file-signature'); return false;" data-tab-name="<?= t('menu.secondary_nav.contracts') ?>" data-tab-icon="fas fa-file-signature">
            <i class="fas fa-file-signature"></i>
            <span>{{ t('menu.secondary_nav.contracts') }}</span>
        </button>
        <button class="icon-button" onclick="openOrSwitchToTab('/pages/veiculos', '<?= t('menu.secondary_nav.vehicles') ?>', 'fas fa-car-side'); return false;" data-tab-name="<?= t('menu.secondary_nav.vehicles') ?>" data-tab-icon="fas fa-car-side">
            <i class="fas fa-car-side"></i>
            <span>{{ t('menu.secondary_nav.vehicles') }}</span>
        </button>
        <button class="icon-button" onclick="openOrSwitchToTab('/pages/clientes', '<?= t('menu.secondary_nav.clients') ?>', 'fas fa-users'); return false;" data-tab-name="<?= t('menu.secondary_nav.clients') ?>" data-tab-icon="fas fa-users">
            <i class="fas fa-users"></i>
            <span>{{ t('menu.secondary_nav.clients') }}</span>
        </button>
        <button class="icon-button" onclick="openOrSwitchToTab('/pages/funcionarios', '<?= t('menu.secondary_nav.employees') ?>', 'fas fa-id-badge'); return false;" data-tab-name="<?= t('menu.secondary_nav.employees') ?>" data-tab-icon="fas fa-id-badge">
            <i class="fas fa-id-badge"></i>
            <span>{{ t('menu.secondary_nav.employees') }}</span>
        </button>
        <button class="icon-button" onclick="openSpotlight(); return false;" data-tab-name="<?= t('menu.secondary_nav.find') ?>" data-tab-icon="fas fa-magnifying-glass">
            <i class="fas fa-magnifying-glass"></i>
            <span>{{ t('menu.secondary_nav.find') }}</span>
        </button>
        <button class="icon-button" onclick="openOrSwitchToTab('/pages/agenda', '<?= t('menu.secondary_nav.schedule') ?>', 'far fa-calendar-alt'); return false;" data-tab-id="agenda" data-tab-name="<?= t('menu.secondary_nav.schedule') ?>" data-tab-icon="far fa-calendar-alt">
            <i class="far fa-calendar-alt"></i>
            <span>{{ t('menu.secondary_nav.schedule') }}</span>
        </button>
        <button class="icon-button" onclick="openOrSwitchToTab('/pages/matrizes-filiais', '<?= t('menu.secondary_nav.branches') ?>', 'fas fa-building'); return false;" data-tab-name="<?= t('menu.secondary_nav.branches') ?>" data-tab-icon="fas fa-building">
            <i class="fas fa-building"></i>
            <span>{{ t('menu.secondary_nav.branches') }}</span>
        </button>
        <button class="icon-button" onclick="location.reload(); return false;" title="{{ t('menu.tooltips.refresh_page') }}">
            <i class="fas fa-sync"></i>
            <span>{{ t('menu.secondary_nav.refresh') }}</span>
        </button>
    </div>
</header>

<!-- Form de logout (hidden) -->
<form id="logout-form" action="/logout" method="POST" style="display: none;">
    @csrf
</form>
