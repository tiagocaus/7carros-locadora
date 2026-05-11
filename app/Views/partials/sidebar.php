<!-- Sidebar - Sistema de Tabs -->
<aside id="sidebar" class="sidebar w-full md:w-64 p-3 shadow-lg flex-shrink-0 overflow-y-auto">
    <!-- Lista de tabs -->
    <div id="sidebarTabsContainer">
        <!-- Tab Início (sempre ativa por padrão, não pode ser fechada) -->
        <div class="sidebar-tab active no-close" data-tab-id="inicio">
            <div class="flex items-center">
                <i class="fas fa-home tab-icon"></i>
                <span>{{ t('menu.sidebar.home') }}</span>
            </div>
            <i class="fas fa-times close-icon"></i>
        </div>
    </div>

    <!-- Busca rápida -->
    <form action="#" id="quickSearchForm" class="hidden md:block">
        <h3 class="font-semibold text-slate-700 mt-4 mb-2 text-sm px-1">{{ t('menu.sidebar.quick_search') }}</h3>
        <select class="w-full p-2 border border-slate-300 rounded-md mb-2 text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500" data-chosen-type="normal">
            <option value="">{{ t('menu.sidebar.select') }}</option>
        </select>
        <div class="relative mb-3">
            <input type="text" placeholder="{{ t('menu.sidebar.vehicle') }}" class="w-full p-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
        </div>
        <div class="relative mb-3 flex items-center">
            <input type="text" placeholder="dd/mm/aaaa" class="w-full p-2 border border-slate-300 rounded-l-md text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
            <span class="inline-flex items-center px-3 h-[38px] border border-l-0 border-slate-300 bg-slate-50 text-slate-500 text-sm rounded-r-md">
                <i class="fas fa-calendar-day"></i>
            </span>
        </div>
        <button class="w-full btn-green py-2 rounded-md text-sm font-medium shadow hover:shadow-md transition-shadow">Ok</button>
    </form>
</aside>
