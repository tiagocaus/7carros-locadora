/* Reset e base */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    line-height: 1.4;
    color: #333;
    padding: 10px;
}

/* Totalizadores */
.totals-table { width: 100%; margin-bottom: 15px; }
.totals-table td {
    text-align: center;
    padding: 8px 5px;
    background: #f8f9fa;
    border: 1px solid #e2e8f0;
}
.totals-label { font-size: 7pt; color: #666; text-transform: uppercase; }
.totals-value { font-size: 12pt; font-weight: bold; color: #1e293b; }

/* Tabela de dados */
.data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
.data-table th {
    background: #334155;
    color: #fff;
    font-size: 8pt;
    font-weight: bold;
    padding: 5px 8px;
    text-align: left;
    text-transform: uppercase;
}
.data-table th.right { text-align: right; }
.data-table th.center { text-align: center; }
.data-table td {
    padding: 4px 8px;
    font-size: 8pt;
    border-bottom: 1px solid #e2e8f0;
}
.data-table td.right { text-align: right; }
.data-table td.center { text-align: center; }
.data-table tr:nth-child(even) td { background: #f8fafc; }

/* Badges */
.badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 7pt;
    font-weight: bold;
}
.badge-green { background: #dcfce7; color: #166534; }
.badge-yellow { background: #fef9c3; color: #854d0e; }
.badge-red { background: #fee2e2; color: #991b1b; }
.badge-blue { background: #dbeafe; color: #1e40af; }
