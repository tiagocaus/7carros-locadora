<?php
/**
 * Partial: Paginação padrão para relatórios com tabela paginada
 */
?>
<div id="reportPagination" class="table-pagination-controls mt-4 flex flex-wrap justify-between items-center" style="display: none;">
    <div>
        <label for="rowsPerPage" class="text-sm text-slate-600 mr-2"><?= t('modules.relatorios.common.rows_per_page') ?></label>
        <select id="rowsPerPage" class="form-input-focus select-pagination">
            <option value="10">10</option>
            <option value="20" selected>20</option>
            <option value="30">30</option>
            <option value="50">50</option>
        </select>
    </div>
    <div class="text-sm text-slate-600 mt-2 sm:mt-0">
        <span id="registrosInfo"></span>
    </div>
    <nav aria-label="Page navigation" class="mt-2 sm:mt-0">
        <ul id="paginationButtons" class="inline-flex items-center -space-x-px">
            <li><button class="pagination-button arrow-button rounded-l-md" id="btnPrevPage" disabled><i class="fas fa-chevron-left"></i></button></li>
            <li><button class="pagination-button numbered active">1</button></li>
            <li><button class="pagination-button arrow-button rounded-r-md" id="btnNextPage" disabled><i class="fas fa-chevron-right"></i></button></li>
        </ul>
    </nav>
</div>
