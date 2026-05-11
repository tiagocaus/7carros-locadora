/* Reset e base */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: Arial, sans-serif;
    font-size: 10pt;
    line-height: 1.4;
    color: #333;
    padding: 15px;
}

/* Sections */
.section { margin-bottom: 12px; }
.section-title {
    font-size: 9pt;
    font-weight: bold;
    background: #f0f0f0;
    padding: 4px 8px;
    margin-bottom: 8px;
    border-left: 3px solid #333;
    text-transform: uppercase;
}

/* Grid layout (display: table) */
.grid { display: table; width: 100%; }
.grid-row { display: table-row; }
.grid-cell {
    display: table-cell;
    padding: 3px 8px;
    font-size: 9pt;
    border-bottom: 1px solid #eee;
}
.grid-cell.label {
    font-weight: bold;
    color: #666;
    font-size: 8pt;
    white-space: nowrap;
}
.grid-cell.w25 { width: 25%; }
.grid-cell.w50 { width: 50%; }

/* Tabelas de dados */
table.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}
table.data-table th {
    background: #f0f0f0;
    padding: 5px 8px;
    text-align: left;
    font-size: 8pt;
    font-weight: bold;
    border: 1px solid #ddd;
    text-transform: uppercase;
}
table.data-table td {
    padding: 5px 8px;
    font-size: 9pt;
    border: 1px solid #eee;
}
.text-right { text-align: right; }
.text-center { text-align: center; }

/* Page break para combos */
.page { padding: 15px; }

/* Header padrao (usado em todas as paginas) */
.header-table {
    width: 100%;
    border-bottom: 2px solid #333;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.header-table td { vertical-align: top; padding: 0; }
.empresa-nome { font-size: 14pt; font-weight: bold; margin-bottom: 3px; }
.empresa-detalhe { font-size: 8pt; color: #666; }
.doc-titulo { font-size: 13pt; font-weight: bold; text-align: right; margin-bottom: 5px; }
.doc-detalhe { font-size: 9pt; text-align: right; color: #555; }

/* Logo */
.logo-img { max-height: 70px; max-width: 120px; margin-bottom: 5px; }

/* QR Code */
.qr-img { width: 80px; height: 80px; }
