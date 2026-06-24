/* Info grid (cliente/veiculo) */
.info-grid { display: table; width: 100%; }
.info-row { display: table-row; }
.info-cell { display: table-cell; padding: 2px 5px; border-bottom: 1px solid #eee; font-size: 9pt; }
.info-cell.label { width: 20%; font-weight: bold; color: #666; font-size: 8pt; }

/* Locatario (tabela compacta) */
.locatario-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
}
.locatario-table th {
    background: #f0f0f0;
    padding: 4px 8px;
    text-align: left;
    font-size: 8pt;
    font-weight: bold;
    border: 1px solid #333;
    text-transform: uppercase;
}
.locatario-table td {
    padding: 4px 8px;
    font-size: 9pt;
    border: 1px solid #333;
}

/* Duas colunas (saida/chegada) */
.duas-colunas { display: table; width: 100%; }
.coluna { display: table-cell; width: 50%; vertical-align: top; padding: 0 5px; }
.coluna:first-child { padding-left: 0; }
.coluna:last-child { padding-right: 0; }

/* Titulo da coluna */
.coluna-titulo {
    font-weight: bold;
    font-size: 10pt;
    margin-bottom: 6px;
    text-transform: uppercase;
}

/* Campos de preenchimento manual */
.campo-manual {
    font-size: 9pt;
    margin-bottom: 4px;
}

/* Diagrama dentro da coluna */
.diagrama-coluna {
    text-align: center;
    margin: 8px 0;
}
.diagrama-coluna img {
    display: block;
    margin: 0 auto;
    max-width: 100%;
    max-height: 420px;
    width: auto;
    height: auto;
}

/* Tanque (tabela de fracoes) */
.tanque-table {
    border-collapse: collapse;
    margin: 8px auto;
}
.tanque-table th {
    background: #f0f0f0;
    padding: 3px 2px;
    font-size: 8pt;
    font-weight: bold;
    border: 1px solid #333;
    text-align: center;
    text-transform: uppercase;
}
.tanque-table td {
    padding: 3px 5px;
    font-size: 7pt;
    border: 1px solid #333;
    text-align: center;
    min-width: 22px;
    height: 16px;
}
.tanque-table td.filled { background: #4ade80; }

/* Legenda de avarias */
.legenda-avarias {
    margin: 10px 0;
    padding: 8px;
    font-size: 9pt;
}
.legenda-avarias strong { font-size: 9pt; }
.legenda-avarias p { margin: 2px 0; font-size: 8pt; }

/* Questionario (tabela full-width) */
.questionario-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.questionario-table th {
    background: #f0f0f0;
    padding: 5px 8px;
    font-size: 8pt;
    font-weight: bold;
    border: 1px solid #333;
    text-align: left;
    text-transform: uppercase;
}
.questionario-table td {
    padding: 4px 8px;
    font-size: 9pt;
    border: 1px solid #333;
    height: 20px;
}

/* Observacoes checklist */
.observacoes-box {
    border: 1px solid #ddd;
    padding: 8px;
    min-height: 50px;
    background: #fafafa;
    font-size: 8pt;
}

/* ======= MODO DIGITAL ======= */

/* Veiculo info (digital) */
.veiculo-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    background: #f5f5f5;
}
.veiculo-table td {
    padding: 6px 10px;
    border: 1px solid #ddd;
}
.veiculo-label {
    font-weight: bold;
    color: #666;
    font-size: 8pt;
    width: 12%;
}
.veiculo-value {
    font-size: 10pt;
}

/* Questoes (digital) */
.questoes-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 5px;
}
.questoes-table th {
    background: #f0f0f0;
    padding: 5px 8px;
    text-align: left;
    font-size: 9pt;
    border: 1px solid #ddd;
}
.questoes-table td {
    padding: 4px 8px;
    font-size: 9pt;
    border: 1px solid #eee;
}

/* Badges de resposta */
.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 8pt;
    font-weight: bold;
}
.badge-confere { background: #dcfce7; color: #166534; }
.badge-nao-confere { background: #fef2f2; color: #991b1b; }
.badge-danificado { background: #fff7ed; color: #9a3412; }
.badge-na { background: #f1f5f9; color: #64748b; }

/* Observacoes (digital) */
.obs-box {
    border: 1px solid #ddd;
    padding: 10px;
    min-height: 30px;
    background: #fafafa;
    margin-bottom: 10px;
    font-size: 9pt;
    text-align: justify;
}

/* Fotos vistoria (digital) */
.foto-img {
    max-width: 100%;
    max-height: 190px;
    border: 1px solid #ddd;
}
.foto-legenda {
    font-size: 8pt;
    color: #666;
    margin-top: 3px;
}
