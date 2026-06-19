@extends('layouts.iframe')

@section('title', t('modules.agenda.title'))

@section('content')
<link href="<?= asset('css/agenda.min.css'); ?>" rel="stylesheet">

<div class="pl-1 pr-2 py-0">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h2 class="title-section mb-3 sm:mb-0"><?= t('modules.agenda.title') ?></h2>
        <div class="flex items-center flex-wrap gap-2">
            <div id="filters" class="schedule-filters flex items-center flex-wrap"></div>
            <button id="btnNovaAgenda" class="btn-blue py-2 px-4 rounded-md text-sm font-medium flex items-center shadow hover:shadow-md whitespace-nowrap">
                <i class="fas fa-plus mr-2"></i><?= t('common.buttons.new') ?>
            </button>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg schedule-container">
        <div class="schedule-grid-wrapper">
            <table id="schedule-table">
                <thead id="schedule-table-head"></thead>
                <tbody id="schedule-table-body"></tbody>
            </table>
        </div>

        <div class="schedule-legend">
            <h4><?= t('modules.agenda.legend.title') ?></h4>
            <div class="legend-periods">
                <div class="legend-item"><div class="legend-period period-1"></div><span><?= t('modules.agenda.legend.dawn') ?></span></div>
                <div class="legend-item"><div class="legend-period period-2"></div><span><?= t('modules.agenda.legend.morning') ?></span></div>
                <div class="legend-item"><div class="legend-period period-3"></div><span><?= t('modules.agenda.legend.afternoon') ?></span></div>
                <div class="legend-item"><div class="legend-period period-4"></div><span><?= t('modules.agenda.legend.night') ?></span></div>
            </div>
        </div>
    </div>
</div>

<div id="preloader"><span class="loader"></span></div>
@endsection

@section('scripts')
<?php
$jsFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$agendaI18n = [
    'groups' => t('modules.agenda.resources.groups'),
    'vehicles' => t('modules.agenda.resources.vehicles'),
    'general' => t('modules.agenda.resources.general'),
    'generalSchedule' => t('modules.agenda.resources.general_schedule'),
    'reservations' => t('modules.agenda.resources.reservations'),
    'all' => t('modules.agenda.filters.all'),
    'reservation' => t('modules.agenda.filters.reservation'),
    'rental' => t('modules.agenda.filters.rental'),
    'contract' => t('modules.agenda.filters.contract'),
    'maintenanceOngoing' => t('modules.agenda.filters.maintenance_ongoing'),
    'maintenanceScheduled' => t('modules.agenda.filters.maintenance_scheduled'),
    'schedule' => t('modules.agenda.filters.schedule'),
    'monthNames' => [
        t('modules.agenda.months.january'), t('modules.agenda.months.february'), t('modules.agenda.months.march'),
        t('modules.agenda.months.april'), t('modules.agenda.months.may'), t('modules.agenda.months.june'),
        t('modules.agenda.months.july'), t('modules.agenda.months.august'), t('modules.agenda.months.september'),
        t('modules.agenda.months.october'), t('modules.agenda.months.november'), t('modules.agenda.months.december'),
    ],
    'dayNames' => [
        t('modules.agenda.days.sun'), t('modules.agenda.days.mon'), t('modules.agenda.days.tue'),
        t('modules.agenda.days.wed'), t('modules.agenda.days.thu'), t('modules.agenda.days.fri'),
        t('modules.agenda.days.sat'),
    ],
    'loadError' => t('modules.agenda.messages.load_error'),
];
?>
<script>
    window.AGENDA_I18N = <?= json_encode($agendaI18n, $jsFlags) ?>;
</script>
<script src="<?= asset('js/agenda.min.js'); ?>"></script>
@endsection
