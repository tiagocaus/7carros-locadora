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

/* Tabela de pares label/valor (preferida em mPDF — substitui display:table em divs) */
table.kv { width: 100%; border-collapse: collapse; }
table.kv td { padding: 3px 8px; font-size: 9pt; border-bottom: 1px solid #eee; vertical-align: top; }
table.kv td.label { font-weight: bold; color: #666; font-size: 8pt; white-space: nowrap; }
table.kv td.label.w25 { width: 25%; }
table.kv td.w25 { width: 25%; }
table.kv td.w50 { width: 50%; }

/* QR code de validacao */
.qr-img { width: 70px; height: 70px; }

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

/* Page break */
.page { padding: 15px; }

/* Header padrao */
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

/* Valor destacado (notificacao/comprovante) */
.valor-destaque { text-align: center; margin: 12px 0; padding: 10px; background: #fafafa; border: 1px solid #eee; }
.valor-destaque .label { font-size: 9pt; color: #666; }
.valor-destaque .valor { font-size: 18pt; font-weight: bold; color: #b91c1c; }
.valor-destaque .valor.pago { color: #15803d; }
.valor-extenso {
    text-align: center;
    font-style: italic;
    font-size: 9pt;
    color: #666;
    margin-bottom: 10px;
    padding: 5px;
    background: #fafafa;
    border: 1px dashed #ddd;
}

/* Texto justificado (notificacao/comprovante) */
.texto-doc {
    text-align: justify;
    margin: 10px 0;
    line-height: 1.6;
    font-size: 10pt;
}
.texto-doc strong { color: #000; }

/* Local + data */
.local-data {
    text-align: right;
    margin: 12px 0;
    font-size: 9pt;
}

/* Assinatura */
.assinatura {
    text-align: center;
    margin-top: 40px;
}
.assinatura .linha {
    border-top: 1px solid #333;
    width: 320px;
    margin: 0 auto;
    padding-top: 5px;
}
.assinatura .nome { font-weight: bold; }
.assinatura .doc { font-size: 8pt; color: #666; }

/* Campos para preenchimento (termo) */
.campo-preencher {
    border-bottom: 1px solid #333;
    display: inline-block;
    min-width: 200px;
    height: 14pt;
}
.linha-preencher {
    border-bottom: 1px solid #333;
    height: 18pt;
    margin-bottom: 6px;
}

/* Footer */
.footer {
    margin-top: 20px;
    text-align: center;
    font-size: 7pt;
    color: #999;
    border-top: 1px solid #eee;
    padding-top: 8px;
}
