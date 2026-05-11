<?php

/**
 * Migration: Popula campo descricao_i18n com traduções
 *
 * Migra os valores existentes de descricao para o campo JSON descricao_i18n
 * com traduções em pt_BR, en_US, es_ES, it_IT e pt_PT.
 */

use App\Database\Migration;

return new class extends Migration
{
    /**
     * Mapa de traduções contábeis
     * Chave = descrição em pt_BR, Valor = array com traduções
     */
    private function getTranslations(): array
    {
        return [
            // === ATIVO ===
            'ATIVO' => [
                'en_US' => 'ASSETS',
                'es_ES' => 'ACTIVO',
                'it_IT' => 'ATTIVO',
                'pt_PT' => 'ATIVO',
            ],
            'ATIVO CIRCULANTE' => [
                'en_US' => 'CURRENT ASSETS',
                'es_ES' => 'ACTIVO CORRIENTE',
                'it_IT' => 'ATTIVO CORRENTE',
                'pt_PT' => 'ATIVO CORRENTE',
            ],
            'Caixa' => [
                'en_US' => 'Cash',
                'es_ES' => 'Caja',
                'it_IT' => 'Cassa',
                'pt_PT' => 'Caixa',
            ],
            'Caixa geral' => [
                'en_US' => 'General cash',
                'es_ES' => 'Caja general',
                'it_IT' => 'Cassa generale',
                'pt_PT' => 'Caixa geral',
            ],
            'Bancos com movimento' => [
                'en_US' => 'Bank accounts',
                'es_ES' => 'Bancos con movimiento',
                'it_IT' => 'Conti bancari',
                'pt_PT' => 'Bancos com movimento',
            ],
            'Banco Bradesco' => [
                'en_US' => 'Bradesco Bank',
                'es_ES' => 'Banco Bradesco',
                'it_IT' => 'Banca Bradesco',
                'pt_PT' => 'Banco Bradesco',
            ],
            'Banco Itaú' => [
                'en_US' => 'Itaú Bank',
                'es_ES' => 'Banco Itaú',
                'it_IT' => 'Banca Itaú',
                'pt_PT' => 'Banco Itaú',
            ],
            'Banco do Brasil' => [
                'en_US' => 'Bank of Brazil',
                'es_ES' => 'Banco de Brasil',
                'it_IT' => 'Banca del Brasile',
                'pt_PT' => 'Banco do Brasil',
            ],
            'Banco Caixa' => [
                'en_US' => 'Caixa Bank',
                'es_ES' => 'Banco Caixa',
                'it_IT' => 'Banca Caixa',
                'pt_PT' => 'Banco Caixa',
            ],
            'Banco Santander' => [
                'en_US' => 'Santander Bank',
                'es_ES' => 'Banco Santander',
                'it_IT' => 'Banca Santander',
                'pt_PT' => 'Banco Santander',
            ],
            'Banco Sicoob' => [
                'en_US' => 'Sicoob Bank',
                'es_ES' => 'Banco Sicoob',
                'it_IT' => 'Banca Sicoob',
                'pt_PT' => 'Banco Sicoob',
            ],
            'Banco Cresol' => [
                'en_US' => 'Cresol Bank',
                'es_ES' => 'Banco Cresol',
                'it_IT' => 'Banca Cresol',
                'pt_PT' => 'Banco Cresol',
            ],
            'Banco Cora SD' => [
                'en_US' => 'Cora SD Bank',
                'es_ES' => 'Banco Cora SD',
                'it_IT' => 'Banca Cora SD',
                'pt_PT' => 'Banco Cora SD',
            ],
            'Banco Inter' => [
                'en_US' => 'Inter Bank',
                'es_ES' => 'Banco Inter',
                'it_IT' => 'Banca Inter',
                'pt_PT' => 'Banco Inter',
            ],
            'Contas a receber' => [
                'en_US' => 'Accounts receivable',
                'es_ES' => 'Cuentas por cobrar',
                'it_IT' => 'Crediti',
                'pt_PT' => 'Contas a receber',
            ],
            'Clientes' => [
                'en_US' => 'Customers',
                'es_ES' => 'Clientes',
                'it_IT' => 'Clienti',
                'pt_PT' => 'Clientes',
            ],
            'Outras contas a receber' => [
                'en_US' => 'Other receivables',
                'es_ES' => 'Otras cuentas por cobrar',
                'it_IT' => 'Altri crediti',
                'pt_PT' => 'Outras contas a receber',
            ],
            'Estoque' => [
                'en_US' => 'Inventory',
                'es_ES' => 'Inventario',
                'it_IT' => 'Magazzino',
                'pt_PT' => 'Stock',
            ],
            'Mercadorias' => [
                'en_US' => 'Merchandise',
                'es_ES' => 'Mercancías',
                'it_IT' => 'Merci',
                'pt_PT' => 'Mercadorias',
            ],
            'Produtos acabados' => [
                'en_US' => 'Finished goods',
                'es_ES' => 'Productos terminados',
                'it_IT' => 'Prodotti finiti',
                'pt_PT' => 'Produtos acabados',
            ],
            'Insumos' => [
                'en_US' => 'Supplies',
                'es_ES' => 'Insumos',
                'it_IT' => 'Forniture',
                'pt_PT' => 'Insumos',
            ],
            'Outros' => [
                'en_US' => 'Others',
                'es_ES' => 'Otros',
                'it_IT' => 'Altri',
                'pt_PT' => 'Outros',
            ],
            'Bloqueio/Caução' => [
                'en_US' => 'Block/Deposit',
                'es_ES' => 'Bloqueo/Depósito',
                'it_IT' => 'Blocco/Cauzione',
                'pt_PT' => 'Bloqueio/Caução',
            ],
            'Bloqueio/Caução entrada' => [
                'en_US' => 'Block/Deposit in',
                'es_ES' => 'Bloqueo/Depósito entrada',
                'it_IT' => 'Blocco/Cauzione entrata',
                'pt_PT' => 'Bloqueio/Caução entrada',
            ],
            'Bloqueio/Caução saída' => [
                'en_US' => 'Block/Deposit out',
                'es_ES' => 'Bloqueo/Depósito salida',
                'it_IT' => 'Blocco/Cauzione uscita',
                'pt_PT' => 'Bloqueio/Caução saída',
            ],
            'NÃO CIRCULANTES' => [
                'en_US' => 'NON-CURRENT ASSETS',
                'es_ES' => 'NO CORRIENTES',
                'it_IT' => 'NON CORRENTI',
                'pt_PT' => 'NÃO CORRENTES',
            ],
            'Outras Contas' => [
                'en_US' => 'Other accounts',
                'es_ES' => 'Otras cuentas',
                'it_IT' => 'Altri conti',
                'pt_PT' => 'Outras contas',
            ],
            'INVESTIMENTOS' => [
                'en_US' => 'INVESTMENTS',
                'es_ES' => 'INVERSIONES',
                'it_IT' => 'INVESTIMENTI',
                'pt_PT' => 'INVESTIMENTOS',
            ],
            'Participações societárias' => [
                'en_US' => 'Equity interests',
                'es_ES' => 'Participaciones societarias',
                'it_IT' => 'Partecipazioni societarie',
                'pt_PT' => 'Participações societárias',
            ],
            'IMOBILIZADO' => [
                'en_US' => 'FIXED ASSETS',
                'es_ES' => 'INMOVILIZADO',
                'it_IT' => 'IMMOBILIZZAZIONI',
                'pt_PT' => 'IMOBILIZADO',
            ],
            'Terrenos' => [
                'en_US' => 'Land',
                'es_ES' => 'Terrenos',
                'it_IT' => 'Terreni',
                'pt_PT' => 'Terrenos',
            ],
            'Construções e benfeitorias' => [
                'en_US' => 'Buildings and improvements',
                'es_ES' => 'Construcciones y mejoras',
                'it_IT' => 'Costruzioni e migliorie',
                'pt_PT' => 'Construções e benfeitorias',
            ],
            'Maquinas e ferramentas' => [
                'en_US' => 'Machinery and tools',
                'es_ES' => 'Máquinas y herramientas',
                'it_IT' => 'Macchine e attrezzi',
                'pt_PT' => 'Máquinas e ferramentas',
            ],
            'Veículos' => [
                'en_US' => 'Vehicles',
                'es_ES' => 'Vehículos',
                'it_IT' => 'Veicoli',
                'pt_PT' => 'Veículos',
            ],
            'Móveis' => [
                'en_US' => 'Furniture',
                'es_ES' => 'Muebles',
                'it_IT' => 'Mobili',
                'pt_PT' => 'Móveis',
            ],
            '(-) Depreciação acumulada' => [
                'en_US' => '(-) Accumulated depreciation',
                'es_ES' => '(-) Depreciación acumulada',
                'it_IT' => '(-) Ammortamento accumulato',
                'pt_PT' => '(-) Depreciação acumulada',
            ],
            '(-) Amortização acumulada' => [
                'en_US' => '(-) Accumulated amortization',
                'es_ES' => '(-) Amortización acumulada',
                'it_IT' => '(-) Ammortamento accumulato',
                'pt_PT' => '(-) Amortização acumulada',
            ],
            'INTANGÍVEL' => [
                'en_US' => 'INTANGIBLE ASSETS',
                'es_ES' => 'INTANGIBLE',
                'it_IT' => 'INTANGIBILI',
                'pt_PT' => 'INTANGÍVEL',
            ],
            'Marcas' => [
                'en_US' => 'Trademarks',
                'es_ES' => 'Marcas',
                'it_IT' => 'Marchi',
                'pt_PT' => 'Marcas',
            ],
            'Softwares' => [
                'en_US' => 'Software',
                'es_ES' => 'Software',
                'it_IT' => 'Software',
                'pt_PT' => 'Software',
            ],

            // === PASSIVO ===
            'PASSIVO' => [
                'en_US' => 'LIABILITIES',
                'es_ES' => 'PASIVO',
                'it_IT' => 'PASSIVO',
                'pt_PT' => 'PASSIVO',
            ],
            'CIRCULANTE' => [
                'en_US' => 'CURRENT LIABILITIES',
                'es_ES' => 'CORRIENTE',
                'it_IT' => 'CORRENTE',
                'pt_PT' => 'CORRENTE',
            ],
            'Impostos e contribuições a recolher' => [
                'en_US' => 'Taxes and contributions payable',
                'es_ES' => 'Impuestos y contribuciones a pagar',
                'it_IT' => 'Imposte e contributi da versare',
                'pt_PT' => 'Impostos e contribuições a pagar',
            ],
            'Simples a recolher' => [
                'en_US' => 'Simples tax payable',
                'es_ES' => 'Simples a pagar',
                'it_IT' => 'Simples da versare',
                'pt_PT' => 'Simples a pagar',
            ],
            'INSS' => [
                'en_US' => 'Social security',
                'es_ES' => 'Seguridad social',
                'it_IT' => 'Previdenza sociale',
                'pt_PT' => 'Segurança social',
            ],
            'FGTS' => [
                'en_US' => 'Severance fund',
                'es_ES' => 'Fondo de garantía',
                'it_IT' => 'Fondo di garanzia',
                'pt_PT' => 'FGTS',
            ],
            'Contas a pagar' => [
                'en_US' => 'Accounts payable',
                'es_ES' => 'Cuentas por pagar',
                'it_IT' => 'Debiti',
                'pt_PT' => 'Contas a pagar',
            ],
            'Fornecedores' => [
                'en_US' => 'Suppliers',
                'es_ES' => 'Proveedores',
                'it_IT' => 'Fornitori',
                'pt_PT' => 'Fornecedores',
            ],
            'Outras contas' => [
                'en_US' => 'Other accounts',
                'es_ES' => 'Otras cuentas',
                'it_IT' => 'Altri conti',
                'pt_PT' => 'Outras contas',
            ],
            'Empréstimos bancários' => [
                'en_US' => 'Bank loans',
                'es_ES' => 'Préstamos bancarios',
                'it_IT' => 'Prestiti bancari',
                'pt_PT' => 'Empréstimos bancários',
            ],
            'Banco A - Operação X' => [
                'en_US' => 'Bank A - Operation X',
                'es_ES' => 'Banco A - Operación X',
                'it_IT' => 'Banca A - Operazione X',
                'pt_PT' => 'Banco A - Operação X',
            ],
            'NÃO CIRCULANTE' => [
                'en_US' => 'NON-CURRENT LIABILITIES',
                'es_ES' => 'NO CORRIENTE',
                'it_IT' => 'NON CORRENTE',
                'pt_PT' => 'NÃO CORRENTE',
            ],
            'PATRIMÔNIO LIQUIDO' => [
                'en_US' => 'EQUITY',
                'es_ES' => 'PATRIMONIO NETO',
                'it_IT' => 'PATRIMONIO NETTO',
                'pt_PT' => 'CAPITAL PRÓPRIO',
            ],
            'Capital social' => [
                'en_US' => 'Share capital',
                'es_ES' => 'Capital social',
                'it_IT' => 'Capitale sociale',
                'pt_PT' => 'Capital social',
            ],
            'Reservas' => [
                'en_US' => 'Reserves',
                'es_ES' => 'Reservas',
                'it_IT' => 'Riserve',
                'pt_PT' => 'Reservas',
            ],
            'Capital social subscrito' => [
                'en_US' => 'Subscribed share capital',
                'es_ES' => 'Capital social suscrito',
                'it_IT' => 'Capitale sociale sottoscritto',
                'pt_PT' => 'Capital social subscrito',
            ],
            'Capital social a realizar' => [
                'en_US' => 'Share capital to be paid',
                'es_ES' => 'Capital social a realizar',
                'it_IT' => 'Capitale sociale da versare',
                'pt_PT' => 'Capital social a realizar',
            ],
            'Reservas de capital' => [
                'en_US' => 'Capital reserves',
                'es_ES' => 'Reservas de capital',
                'it_IT' => 'Riserve di capitale',
                'pt_PT' => 'Reservas de capital',
            ],
            'Reservas de lucro' => [
                'en_US' => 'Profit reserves',
                'es_ES' => 'Reservas de utilidades',
                'it_IT' => 'Riserve di utili',
                'pt_PT' => 'Reservas de lucros',
            ],
            'Prejuízos acumulados' => [
                'en_US' => 'Accumulated losses',
                'es_ES' => 'Pérdidas acumuladas',
                'it_IT' => 'Perdite accumulate',
                'pt_PT' => 'Prejuízos acumulados',
            ],
            'Prejuí­zos acumulados' => [
                'en_US' => 'Accumulated losses',
                'es_ES' => 'Pérdidas acumuladas',
                'it_IT' => 'Perdite accumulate',
                'pt_PT' => 'Prejuízos acumulados',
            ],
            'Prejuí­zos acu. de ex. anteriores' => [
                'en_US' => 'Prior years losses',
                'es_ES' => 'Pérdidas de ejercicios anteriores',
                'it_IT' => 'Perdite esercizi precedenti',
                'pt_PT' => 'Prejuízos de exercícios anteriores',
            ],
            'Prejuí­zos do ex. atual' => [
                'en_US' => 'Current year losses',
                'es_ES' => 'Pérdidas del ejercicio actual',
                'it_IT' => 'Perdite esercizio corrente',
                'pt_PT' => 'Prejuízos do exercício atual',
            ],

            // === CUSTOS E DESPESAS ===
            'CUSTOS E DESPESAS' => [
                'en_US' => 'COSTS AND EXPENSES',
                'es_ES' => 'COSTOS Y GASTOS',
                'it_IT' => 'COSTI E SPESE',
                'pt_PT' => 'CUSTOS E GASTOS',
            ],
            'Oficina' => [
                'en_US' => 'Workshop',
                'es_ES' => 'Taller',
                'it_IT' => 'Officina',
                'pt_PT' => 'Oficina',
            ],
            'Manutenções preventivas' => [
                'en_US' => 'Preventive maintenance',
                'es_ES' => 'Mantenimiento preventivo',
                'it_IT' => 'Manutenzione preventiva',
                'pt_PT' => 'Manutenções preventivas',
            ],
            'Custos dos mat. aplicados' => [
                'en_US' => 'Cost of materials applied',
                'es_ES' => 'Costo de materiales aplicados',
                'it_IT' => 'Costo materiali applicati',
                'pt_PT' => 'Custos dos materiais aplicados',
            ],
            'Pneu' => [
                'en_US' => 'Tire',
                'es_ES' => 'Neumático',
                'it_IT' => 'Pneumatico',
                'pt_PT' => 'Pneu',
            ],
            'Custos da mão de obra' => [
                'en_US' => 'Labor costs',
                'es_ES' => 'Costos de mano de obra',
                'it_IT' => 'Costi manodopera',
                'pt_PT' => 'Custos de mão de obra',
            ],
            'Salários' => [
                'en_US' => 'Salaries',
                'es_ES' => 'Salarios',
                'it_IT' => 'Stipendi',
                'pt_PT' => 'Salários',
            ],
            'Encargos sociais' => [
                'en_US' => 'Social charges',
                'es_ES' => 'Cargas sociales',
                'it_IT' => 'Oneri sociali',
                'pt_PT' => 'Encargos sociais',
            ],
            'Custos das mercadorias vendidas' => [
                'en_US' => 'Cost of goods sold',
                'es_ES' => 'Costo de mercancías vendidas',
                'it_IT' => 'Costo merci vendute',
                'pt_PT' => 'Custo das mercadorias vendidas',
            ],
            'Custos das mercadorias' => [
                'en_US' => 'Cost of goods',
                'es_ES' => 'Costo de mercancías',
                'it_IT' => 'Costo merci',
                'pt_PT' => 'Custo das mercadorias',
            ],
            'Custo das mercadorias vencidas' => [
                'en_US' => 'Cost of expired goods',
                'es_ES' => 'Costo de mercancías vencidas',
                'it_IT' => 'Costo merci scadute',
                'pt_PT' => 'Custo das mercadorias vencidas',
            ],
            'Custos dos serviços prestados' => [
                'en_US' => 'Cost of services rendered',
                'es_ES' => 'Costo de servicios prestados',
                'it_IT' => 'Costo servizi resi',
                'pt_PT' => 'Custo dos serviços prestados',
            ],
            'Custos dos serviços' => [
                'en_US' => 'Cost of services',
                'es_ES' => 'Costo de servicios',
                'it_IT' => 'Costo servizi',
                'pt_PT' => 'Custo dos serviços',
            ],
            'Materiais aplicados' => [
                'en_US' => 'Materials applied',
                'es_ES' => 'Materiales aplicados',
                'it_IT' => 'Materiali applicati',
                'pt_PT' => 'Materiais aplicados',
            ],
            'Mão de obra' => [
                'en_US' => 'Labor',
                'es_ES' => 'Mano de obra',
                'it_IT' => 'Manodopera',
                'pt_PT' => 'Mão de obra',
            ],
            'Combustível' => [
                'en_US' => 'Fuel',
                'es_ES' => 'Combustible',
                'it_IT' => 'Carburante',
                'pt_PT' => 'Combustível',
            ],
            'Vistoria veicular' => [
                'en_US' => 'Vehicle inspection',
                'es_ES' => 'Inspección vehicular',
                'it_IT' => 'Ispezione veicolo',
                'pt_PT' => 'Inspeção veicular',
            ],
            'IPVA' => [
                'en_US' => 'Vehicle tax',
                'es_ES' => 'Impuesto vehicular',
                'it_IT' => 'Tassa veicolo',
                'pt_PT' => 'Imposto veículo',
            ],
            'Rastreador' => [
                'en_US' => 'Tracker',
                'es_ES' => 'Rastreador',
                'it_IT' => 'Localizzatore',
                'pt_PT' => 'Rastreador',
            ],
            'Segurança social' => [
                'en_US' => 'Social security',
                'es_ES' => 'Seguridad social',
                'it_IT' => 'Previdenza sociale',
                'pt_PT' => 'Segurança social',
            ],
            'Despesas Operacionais' => [
                'en_US' => 'Operating expenses',
                'es_ES' => 'Gastos operativos',
                'it_IT' => 'Spese operative',
                'pt_PT' => 'Despesas operacionais',
            ],
            'Despesas gerais' => [
                'en_US' => 'General expenses',
                'es_ES' => 'Gastos generales',
                'it_IT' => 'Spese generali',
                'pt_PT' => 'Despesas gerais',
            ],
            'Alugueis' => [
                'en_US' => 'Rent',
                'es_ES' => 'Alquileres',
                'it_IT' => 'Affitti',
                'pt_PT' => 'Rendas',
            ],
            'Água' => [
                'en_US' => 'Water',
                'es_ES' => 'Agua',
                'it_IT' => 'Acqua',
                'pt_PT' => 'Água',
            ],
            'Lúz' => [
                'en_US' => 'Electricity',
                'es_ES' => 'Luz',
                'it_IT' => 'Elettricità',
                'pt_PT' => 'Eletricidade',
            ],
            'Telefone' => [
                'en_US' => 'Phone',
                'es_ES' => 'Teléfono',
                'it_IT' => 'Telefono',
                'pt_PT' => 'Telefone',
            ],
            'Internet' => [
                'en_US' => 'Internet',
                'es_ES' => 'Internet',
                'it_IT' => 'Internet',
                'pt_PT' => 'Internet',
            ],
            'Abastecimento' => [
                'en_US' => 'Fueling',
                'es_ES' => 'Abastecimiento',
                'it_IT' => 'Rifornimento',
                'pt_PT' => 'Abastecimento',
            ],
            'Parcela veicular' => [
                'en_US' => 'Vehicle installment',
                'es_ES' => 'Cuota vehicular',
                'it_IT' => 'Rata veicolo',
                'pt_PT' => 'Prestação veicular',
            ],
            'Seguro' => [
                'en_US' => 'Insurance',
                'es_ES' => 'Seguro',
                'it_IT' => 'Assicurazione',
                'pt_PT' => 'Seguro',
            ],
            'Honorários' => [
                'en_US' => 'Fees',
                'es_ES' => 'Honorarios',
                'it_IT' => 'Onorari',
                'pt_PT' => 'Honorários',
            ],
            'Lavavem veicular' => [
                'en_US' => 'Vehicle wash',
                'es_ES' => 'Lavado vehicular',
                'it_IT' => 'Lavaggio veicolo',
                'pt_PT' => 'Lavagem veicular',
            ],
            'Despesa com refeições' => [
                'en_US' => 'Meal expenses',
                'es_ES' => 'Gastos de comidas',
                'it_IT' => 'Spese pasti',
                'pt_PT' => 'Despesas com refeições',
            ],
            'Despesa com pedágio' => [
                'en_US' => 'Toll expenses',
                'es_ES' => 'Gastos de peaje',
                'it_IT' => 'Spese pedaggio',
                'pt_PT' => 'Despesas com portagem',
            ],
            'Multa/Infração a pagar' => [
                'en_US' => 'Fines payable',
                'es_ES' => 'Multas a pagar',
                'it_IT' => 'Multe da pagare',
                'pt_PT' => 'Multas a pagar',
            ],
            'Despesas com Propaganda' => [
                'en_US' => 'Advertising expenses',
                'es_ES' => 'Gastos de publicidad',
                'it_IT' => 'Spese pubblicità',
                'pt_PT' => 'Despesas com publicidade',
            ],
            'Sistema' => [
                'en_US' => 'System',
                'es_ES' => 'Sistema',
                'it_IT' => 'Sistema',
                'pt_PT' => 'Sistema',
            ],
            'Assessoria Empresarial' => [
                'en_US' => 'Business consulting',
                'es_ES' => 'Asesoría empresarial',
                'it_IT' => 'Consulenza aziendale',
                'pt_PT' => 'Assessoria empresarial',
            ],
            'Alarme' => [
                'en_US' => 'Alarm',
                'es_ES' => 'Alarma',
                'it_IT' => 'Allarme',
                'pt_PT' => 'Alarme',
            ],
            'Monitoramento' => [
                'en_US' => 'Monitoring',
                'es_ES' => 'Monitoreo',
                'it_IT' => 'Monitoraggio',
                'pt_PT' => 'Monitorização',
            ],
            'Taxa de maquininha' => [
                'en_US' => 'Card machine fee',
                'es_ES' => 'Tasa de terminal',
                'it_IT' => 'Commissione POS',
                'pt_PT' => 'Taxa de terminal',
            ],
            'Perdas de capital' => [
                'en_US' => 'Capital losses',
                'es_ES' => 'Pérdidas de capital',
                'it_IT' => 'Perdite di capitale',
                'pt_PT' => 'Perdas de capital',
            ],
            'Baixa de bens do ativo não circulante' => [
                'en_US' => 'Disposal of non-current assets',
                'es_ES' => 'Baja de activos no corrientes',
                'it_IT' => 'Dismissione attività non correnti',
                'pt_PT' => 'Abate de ativos não correntes',
            ],
            'Custos de aplicação de investimentos' => [
                'en_US' => 'Investment application costs',
                'es_ES' => 'Costos de aplicación de inversiones',
                'it_IT' => 'Costi applicazione investimenti',
                'pt_PT' => 'Custos de aplicação de investimentos',
            ],
            'Custos de alienação do imobilizado' => [
                'en_US' => 'Fixed assets disposal costs',
                'es_ES' => 'Costos de enajenación del inmovilizado',
                'it_IT' => 'Costi alienazione immobilizzazioni',
                'pt_PT' => 'Custos de alienação do imobilizado',
            ],

            // === RECEITAS ===
            'RECEITAS' => [
                'en_US' => 'REVENUE',
                'es_ES' => 'INGRESOS',
                'it_IT' => 'RICAVI',
                'pt_PT' => 'RECEITAS',
            ],
            'Receita lí­quida' => [
                'en_US' => 'Net revenue',
                'es_ES' => 'Ingresos netos',
                'it_IT' => 'Ricavi netti',
                'pt_PT' => 'Receita líquida',
            ],
            'Receita bruta de vendas' => [
                'en_US' => 'Gross sales revenue',
                'es_ES' => 'Ingresos brutos de ventas',
                'it_IT' => 'Ricavi lordi vendite',
                'pt_PT' => 'Receita bruta de vendas',
            ],
            'De mercadorias' => [
                'en_US' => 'From merchandise',
                'es_ES' => 'De mercancías',
                'it_IT' => 'Da merci',
                'pt_PT' => 'De mercadorias',
            ],
            'De produtos' => [
                'en_US' => 'From products',
                'es_ES' => 'De productos',
                'it_IT' => 'Da prodotti',
                'pt_PT' => 'De produtos',
            ],
            'De locação' => [
                'en_US' => 'From rental',
                'es_ES' => 'De alquiler',
                'it_IT' => 'Da noleggio',
                'pt_PT' => 'De aluguer',
            ],
            'De serviços prestados' => [
                'en_US' => 'From services rendered',
                'es_ES' => 'De servicios prestados',
                'it_IT' => 'Da servizi resi',
                'pt_PT' => 'De serviços prestados',
            ],
            'De locação Seguradora/Associação' => [
                'en_US' => 'From insurance/association rental',
                'es_ES' => 'De alquiler seguro/asociación',
                'it_IT' => 'Da noleggio assicurazione/associazione',
                'pt_PT' => 'De aluguer seguradora/associação',
            ],
            'Juros sobre locação' => [
                'en_US' => 'Interest on rental',
                'es_ES' => 'Intereses sobre alquiler',
                'it_IT' => 'Interessi su noleggio',
                'pt_PT' => 'Juros sobre aluguer',
            ],
            'Juros sobre contrato' => [
                'en_US' => 'Interest on contract',
                'es_ES' => 'Intereses sobre contrato',
                'it_IT' => 'Interessi su contratto',
                'pt_PT' => 'Juros sobre contrato',
            ],
            'Cobrança por quilometragem excedida' => [
                'en_US' => 'Excess mileage charge',
                'es_ES' => 'Cargo por kilometraje excedido',
                'it_IT' => 'Addebito chilometraggio eccedente',
                'pt_PT' => 'Cobrança por quilometragem excedida',
            ],
            'Deduções da receita bruta' => [
                'en_US' => 'Gross revenue deductions',
                'es_ES' => 'Deducciones de ingresos brutos',
                'it_IT' => 'Deduzioni ricavi lordi',
                'pt_PT' => 'Deduções da receita bruta',
            ],
            'Devoluções' => [
                'en_US' => 'Returns',
                'es_ES' => 'Devoluciones',
                'it_IT' => 'Resi',
                'pt_PT' => 'Devoluções',
            ],
            'Serviços cancelados' => [
                'en_US' => 'Canceled services',
                'es_ES' => 'Servicios cancelados',
                'it_IT' => 'Servizi annullati',
                'pt_PT' => 'Serviços cancelados',
            ],
            'Outras receitas operacionais' => [
                'en_US' => 'Other operating revenue',
                'es_ES' => 'Otros ingresos operativos',
                'it_IT' => 'Altri ricavi operativi',
                'pt_PT' => 'Outras receitas operacionais',
            ],
            'Vendas de ativos não circulantes' => [
                'en_US' => 'Non-current assets sales',
                'es_ES' => 'Ventas de activos no corrientes',
                'it_IT' => 'Vendite attività non correnti',
                'pt_PT' => 'Vendas de ativos não correntes',
            ],
            'Receitas de alienação de investimentos' => [
                'en_US' => 'Investment disposal revenue',
                'es_ES' => 'Ingresos por enajenación de inversiones',
                'it_IT' => 'Ricavi alienazione investimenti',
                'pt_PT' => 'Receitas de alienação de investimentos',
            ],
            'Receitas de alienação imobilizado' => [
                'en_US' => 'Fixed assets disposal revenue',
                'es_ES' => 'Ingresos por enajenación de inmovilizado',
                'it_IT' => 'Ricavi alienazione immobilizzazioni',
                'pt_PT' => 'Receitas de alienação de imobilizado',
            ],
            'Do veículo' => [
                'en_US' => 'From vehicle',
                'es_ES' => 'Del vehículo',
                'it_IT' => 'Dal veicolo',
                'pt_PT' => 'Do veículo',
            ],
            'Avarias' => [
                'en_US' => 'Damages',
                'es_ES' => 'Averías',
                'it_IT' => 'Danni',
                'pt_PT' => 'Avarias',
            ],
            'Lavagem veícular' => [
                'en_US' => 'Vehicle wash',
                'es_ES' => 'Lavado vehicular',
                'it_IT' => 'Lavaggio veicolo',
                'pt_PT' => 'Lavagem veicular',
            ],
            'Multa/Infração a receber' => [
                'en_US' => 'Fines receivable',
                'es_ES' => 'Multas a cobrar',
                'it_IT' => 'Multe da ricevere',
                'pt_PT' => 'Multas a receber',
            ],
            'Pedágio a receber' => [
                'en_US' => 'Toll receivable',
                'es_ES' => 'Peaje a cobrar',
                'it_IT' => 'Pedaggio da ricevere',
                'pt_PT' => 'Portagem a receber',
            ],
        ];
    }

    /**
     * Executa a migration
     */
    public function up(): void
    {
        $translations = $this->getTranslations();

        // Buscar todos os planos de contas
        $planos = $this->db()->table('planos_de_contas')
            ->select(['id', 'descricao'])
            ->get();

        foreach ($planos as $plano) {
            $descricao = trim($plano['descricao']);

            // Construir JSON de traduções
            $i18n = [
                'pt_BR' => $descricao, // Valor original sempre como pt_BR
            ];

            // Se temos tradução mapeada, adicionar os outros idiomas
            if (isset($translations[$descricao])) {
                $i18n['en_US'] = $translations[$descricao]['en_US'];
                $i18n['es_ES'] = $translations[$descricao]['es_ES'];
                $i18n['it_IT'] = $translations[$descricao]['it_IT'];
                $i18n['pt_PT'] = $translations[$descricao]['pt_PT'];
            } else {
                // Se não temos tradução, usar o valor original para todos
                $i18n['en_US'] = $descricao;
                $i18n['es_ES'] = $descricao;
                $i18n['it_IT'] = $descricao;
                $i18n['pt_PT'] = $descricao;
            }

            // Atualizar registro
            $this->db()->table('planos_de_contas')
                ->whereRaw('id = ?', [$plano['id']])
                ->update(['descricao_i18n' => json_encode($i18n, JSON_UNESCAPED_UNICODE)]);
        }
    }

    /**
     * Reverte a migration
     */
    public function down(): void
    {
        // Limpar campo descricao_i18n
        $this->db()->table('planos_de_contas')
            ->whereRaw('1=1')
            ->update(['descricao_i18n' => null]);
    }
};
