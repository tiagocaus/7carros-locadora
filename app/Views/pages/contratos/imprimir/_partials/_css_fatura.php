/* Sections */
.section {
    margin-bottom: 14px;
    border: 1px solid #ccc;
}
.section-title {
    margin-bottom: 0;
    border-bottom: 1px solid #ccc;
    background: #e4e4e4;
}
.section-body {
    padding: 4px 10px 8px 10px;
}
.section-body .fields-table tr:first-child td,
.section-body .obs-box {
    padding-top: 4px;
}

/* Campos estaticos */
table.fields-table {
    width: 100%;
    border-collapse: collapse;
}
table.fields-table td {
    padding: 4px 8px 8px 0;
    vertical-align: top;
    border: 0;
    font-size: 9pt;
}
table.fields-table td.w50 { width: 50%; }
table.fields-table td.w25 { width: 25%; }
table.fields-table td.w100 { width: 100%; }
.field-label {
    font-size: 8pt;
    font-weight: bold;
    color: #000;
    text-transform: uppercase;
    margin-bottom: 2px;
}
.field-value {
    font-size: 9pt;
    color: #000;
    line-height: 1.35;
}

/* Listagens clean */
table.data-table {
    margin-bottom: 0;
}
table.data-table th {
    background: transparent;
    border: 0;
    border-bottom: 1px solid #ddd;
    font-weight: bold;
    padding: 3px 8px 5px 8px;
    text-align: left;
    color: #000;
}
table.data-table th.text-right { text-align: right; }
table.data-table th.text-center { text-align: center; }
table.data-table td.text-right { text-align: right; }
table.data-table td.text-center { text-align: center; }
table.data-table td {
    border: 0;
    border-bottom: 1px solid #eee;
    vertical-align: top;
    color: #000;
}
table.data-table tbody tr:last-child td {
    border-bottom: 0;
}
table.data-table thead + tbody tr:first-child td {
    padding-top: 6px;
}

/* Veiculos */
table.data-table tr.vehicle-group td {
    padding-bottom: 6px;
}
table.data-table tr.vehicle-group-last td {
    border-bottom: 1px solid #eee;
    padding-bottom: 8px;
}
table.data-table tr.vehicle-group-last:last-child td {
    border-bottom: 0;
}
table.data-table .vehicle-primary { font-weight: bold; }
table.data-table .vehicle-meta {
    margin-top: 2px;
    font-size: 8pt;
    line-height: 1.25;
    color: #000;
}
table.data-table .vehicle-insurance-separator { padding: 0 5px; color: #000; }

/* Totais */
.totals { margin-top: 10px; border-top: 2px solid #333; padding-top: 10px; }
.totals-table { width: 100%; }
.totals-table td { padding: 3px 8px; font-size: 9pt; color: #000; }
.totals-table .label-col { width: 75%; text-align: right; }
.totals-table .value-col { width: 25%; text-align: right; font-weight: bold; }
.totals-table .total-row td {
    font-size: 12pt;
    font-weight: bold;
    border-top: 1px solid #333;
    padding-top: 8px;
}

/* Observacoes */
.obs-box { padding: 0; font-size: 9pt; border: 0; background: transparent; line-height: 1.45; color: #000; }

/* Texto preto em header/footer da fatura */
body,
.section-title,
.empresa-nome,
.empresa-detalhe,
.doc-titulo,
.doc-detalhe,
.assinatura-nome,
.page-number {
    color: #000 !important;
}
