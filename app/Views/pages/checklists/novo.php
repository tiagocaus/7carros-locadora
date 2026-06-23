<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <title><?= t('modules.checklists.digital.title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/assets/css/chosen-select.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 0;
            padding-bottom: 70px;
            -webkit-tap-highlight-color: transparent;
        }

        /* Header */
        .app-header {
            position: sticky;
            top: 0;
            z-index: 40;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
        }
        .app-header h1 { font-size: 18px; font-weight: 700; color: #1e293b; margin: 0; }
        .btn-voltar {
            background: #475569;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Tab bar */
        .tab-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 40;
            background: #fff;
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.06);
            display: flex;
            padding: 6px 0 max(14px, env(safe-area-inset-bottom, 14px)) 0;
        }
        .tab-btn {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: 6px 0;
            border: none;
            background: none;
            cursor: pointer;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .tab-btn i { font-size: 18px; }
        .tab-btn.active { color: #3b82f6; }
        .tab-btn.completed { color: #22c55e; }
        .tab-btn.disabled { color: #cbd5e1; pointer-events: none; }

        /* Tab panels */
        .tab-panel { display: none; padding: 16px; }
        .tab-panel.active { display: block; }

        /* Forms */
        .form-label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .form-select, .form-input, .form-textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 15px;
            background: #fff;
            color: #1e293b;
            -webkit-appearance: none;
            appearance: none;
        }
        .form-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 32px;
        }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-group { margin-bottom: 16px; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }

        /* Toggle buttons */
        .toggle-group { display: flex; gap: 0; border-radius: 8px; overflow: hidden; border: 1px solid #d1d5db; }
        .toggle-btn {
            flex: 1;
            padding: 10px 12px;
            border: none;
            background: #fff;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            text-align: center;
        }
        .toggle-btn + .toggle-btn { border-left: 1px solid #d1d5db; }
        .toggle-btn.active { background: #3b82f6; color: #fff; }
        .toggle-btn:active { transform: scale(0.98); }

        /* Questao card */
        .questao-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
        }
        .questao-card .title { font-size: 15px; font-weight: 600; color: #1e293b; margin-bottom: 10px; }
        .questao-options { display: flex; gap: 6px; }
        .questao-opt {
            flex: 1;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 4px;
            text-align: center;
            cursor: pointer;
            background: #fff;
            transition: all 0.15s;
            font-size: 11px;
            font-weight: 500;
            color: #64748b;
        }
        .questao-opt i { display: block; font-size: 18px; margin-bottom: 3px; }
        .questao-opt i.fas { font-weight: 900; }
        .questao-opt.selected-1 { border-color: #22c55e; background: #dcfce7; color: #166534; }
        .questao-opt.selected-2 { border-color: #ef4444; background: #fef2f2; color: #991b1b; }
        .questao-opt.selected-3 { border-color: #eab308; background: #fefce8; color: #854d0e; }
        .questao-opt.selected-4 { border-color: #9333ea; background: #faf5ff; color: #6b21a8; }

        /* Vistoria card */
        .vistoria-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .vistoria-thumb {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .vistoria-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .vistoria-thumb i { font-size: 24px; color: #94a3b8; }
        .vistoria-info { flex: 1; }
        .vistoria-info .name { font-size: 15px; font-weight: 500; color: #1e293b; }
        .vistoria-actions { display: flex; gap: 8px; }
        .vistoria-actions button {
            width: 36px; height: 36px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
        }
        .vistoria-actions .btn-camera { color: #6b7280; }
        .vistoria-actions .btn-view { color: #3b82f6; }
        .vistoria-actions .btn-delete { color: #fff; background: #ef4444; border-color: #ef4444; }

        /* Assinatura */
        .signature-area {
            background: #fefce8;
            border: 2px dashed #eab308;
            border-radius: 12px;
            padding: 12px;
            position: relative;
        }
        .signature-area.has-signature { border-style: solid; border-color: #22c55e; }
        .signature-canvas {
            width: 100%;
            background: transparent;
            touch-action: none;
            cursor: crosshair;
            display: block;
        }
        .btn-limpar {
            margin-top: 8px;
            background: #dc2626;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-salvar {
            width: 100%;
            background: #22c55e;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
        }
        .btn-salvar:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-avancar {
            width: 100%;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 16px;
        }
        .btn-avancar:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Success */
        .success-screen { text-align: center; padding: 60px 20px; }
        .success-screen .icon { font-size: 64px; color: #22c55e; margin-bottom: 16px; animation: popIn 0.4s ease; }
        @keyframes popIn { 0% { transform: scale(0.5); opacity: 0; } 50% { transform: scale(1.1); } 100% { transform: scale(1); opacity: 1; } }

        /* Loading overlay */
        .loading-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.4);
            display: none; align-items: center; justify-content: center; z-index: 50;
        }
        .loading-overlay.active { display: flex; }
        .spinner { width: 40px; height: 40px; border: 4px solid #f3f4f6; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Photo Editor Overlay */
        .photo-editor {
            position: fixed; inset: 0; z-index: 55; background: #000;
            display: none; flex-direction: column; width: 100%; height: 100dvh;
        }
        .photo-editor.active { display: flex; }

        .photo-editor .editor-top-bar {
            position: absolute; top: 0; left: 0; right: 0;
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 16px; padding-top: max(12px, env(safe-area-inset-top));
            z-index: 20; background: linear-gradient(to bottom, rgba(0,0,0,0.6) 0%, transparent 100%);
        }
        .photo-editor .editor-btn {
            width: 42px; height: 42px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.15s; border: none;
            background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px); color: white; font-size: 18px;
        }
        .photo-editor .editor-btn:active { transform: scale(0.9); opacity: 0.8; }

        .photo-editor .canvas-container {
            flex: 1; display: flex; align-items: center; justify-content: center;
            overflow: hidden; position: relative; touch-action: none;
        }
        .photo-editor #editorCanvas { display: block; touch-action: none; }

        .photo-editor .editor-toolbar {
            position: absolute; bottom: 0; left: 0; right: 0;
            display: flex; justify-content: center; align-items: flex-end;
            padding: 16px; padding-bottom: max(16px, env(safe-area-inset-bottom));
            z-index: 20; background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 100%);
            gap: 6px;
        }
        .photo-editor .tool-btn {
            width: 44px; height: 44px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; border: none;
            background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px); color: white; font-size: 18px;
            transition: all 0.15s; flex-shrink: 0;
        }
        .photo-editor .tool-btn:active { transform: scale(0.9); }
        .photo-editor .tool-btn.active { background: #3b82f6; box-shadow: 0 4px 15px rgba(59,130,246,0.5); }

        .photo-editor .popup-overlay {
            display: none; position: absolute; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 30;
            align-items: flex-end; justify-content: center;
        }
        .photo-editor .popup-overlay.show { display: flex; }
        .photo-editor .popup-panel {
            background: white; border-radius: 16px 16px 0 0;
            width: 100%; max-width: 420px; padding: 20px;
            padding-bottom: max(20px, env(safe-area-inset-bottom));
            animation: editorSlideUp 0.25s ease-out;
        }
        @keyframes editorSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

        .photo-editor .popup-handle {
            width: 40px; height: 4px; background: #DDD;
            border-radius: 2px; margin: 0 auto 16px;
        }
        .photo-editor .color-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 12px; max-width: 240px; margin: 0 auto;
        }
        .photo-editor .color-swatch {
            width: 48px; height: 48px; border-radius: 50%; cursor: pointer;
            border: 3px solid transparent; transition: all 0.15s;
            display: flex; align-items: center; justify-content: center; margin: 0 auto;
        }
        .photo-editor .color-swatch:active { transform: scale(0.9); }
        .photo-editor .color-swatch.selected { border-color: #3b82f6; box-shadow: 0 0 0 2px #3b82f6; }

        .photo-editor .marker-option {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 16px; border-radius: 10px; cursor: pointer;
            transition: background 0.15s; border: 1px solid #e2e8f0; margin-bottom: 8px;
        }
        .photo-editor .marker-option:active { background: rgba(59,130,246,0.1); }
        .photo-editor .marker-pin {
            width: 32px; height: 40px; display: flex;
            align-items: center; justify-content: center; flex-shrink: 0;
        }

        .photo-editor .canvas-marker {
            position: absolute; pointer-events: none; z-index: 10;
            animation: editorMarkerDrop 0.3s ease-out;
        }
        @keyframes editorMarkerDrop { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .photo-editor .canvas-marker-label {
            position: absolute; left: 22px; top: -2px;
            background: rgba(0,0,0,0.8); color: white;
            padding: 3px 9px; border-radius: 6px;
            font-size: 11px; font-weight: 700; white-space: nowrap;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }

        .editor-save-toast {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            background: rgba(0,0,0,0.85); color: white;
            padding: 20px 32px; border-radius: 12px;
            font-weight: 700; font-size: 16px; z-index: 100;
            opacity: 0; transition: all 0.3s; pointer-events: none;
            display: flex; align-items: center; gap: 10px;
        }
        .editor-save-toast.show { opacity: 1; transform: translate(-50%, -50%) scale(1); }
    </style>
</head>
<body>

<!-- Header -->
<div class="app-header">
    <h1><?= t('modules.checklists.digital.title') ?></h1>
    <div style="display:flex;gap:8px;">
        <a href="/checklists/digital" class="btn-voltar">
            <i class="fas fa-list"></i> <?= t('modules.checklists.digital.list') ?>
        </a>
        <?php if ($tem_dashboard ?? true): ?>
        <button class="btn-voltar" onclick="window.history.back();">
            <i class="fas fa-arrow-left"></i> <?= t('modules.checklists.digital.back') ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Infor -->
<div id="tab-infor" class="tab-panel active">
    <h2 style="font-size:18px;font-weight:700;color:#1e293b;margin-bottom:16px;"><?= t('modules.checklists.digital.information') ?></h2>

    <div class="form-group">
        <label class="form-label"><?= t('modules.checklists.digital.type') ?></label>
        <div class="toggle-group">
            <button type="button" class="toggle-btn" data-value="A" onclick="setTipo('A')"><?= t('modules.checklists.digital.type_standalone') ?></button>
            <button type="button" class="toggle-btn" data-value="V" onclick="setTipo('V')"><?= t('modules.checklists.digital.type_linked') ?></button>
        </div>
        <input type="hidden" id="infor-tipo" value="">
    </div>

    <div id="wrap-momento" class="form-group" style="display:none;">
        <label class="form-label"><?= t('modules.checklists.digital.moment') ?></label>
        <div class="toggle-group">
            <button type="button" class="toggle-btn" data-value="S" onclick="setMomento('S')"><?= t('modules.checklists.digital.moment_departure') ?></button>
            <button type="button" class="toggle-btn" data-value="C" onclick="setMomento('C')"><?= t('modules.checklists.digital.moment_arrival') ?></button>
        </div>
        <input type="hidden" id="infor-momento" value="">
    </div>

    <div id="wrap-vinculo" class="form-group" style="display:none;">
        <label class="form-label"><?= t('modules.checklists.digital.contract_rental') ?></label>
        <select id="infor-vinculo" class="form-select deferred-chosen"
                data-chosen-type="server-side"
                data-chosen-search-url="/api/checklists/buscar-vinculos"
                data-chosen-placeholder="<?= t('modules.checklists.digital.search_code_client') ?>"
                data-chosen-min-chars="2">
            <option value=""><?= t('modules.checklists.digital.select') ?></option>
        </select>
    </div>

    <div id="wrap-veiculo-avulso" class="form-group" style="display:none;">
        <label class="form-label"><?= t('modules.checklists.digital.vehicle') ?></label>
        <select id="infor-veiculo" class="form-select deferred-chosen"
                data-chosen-type="server-side"
                data-chosen-search-url="/api/checklists/buscar-veiculos"
                data-chosen-placeholder="<?= t('modules.checklists.digital.search_plate_model') ?>"
                data-chosen-min-chars="2">
            <option value=""><?= t('modules.checklists.digital.select') ?></option>
        </select>
    </div>

    <div id="wrap-veiculo-vinculado" class="form-group" style="display:none;">
        <label class="form-label"><?= t('modules.checklists.digital.vehicle') ?></label>
        <select id="infor-veiculo-vinculado" class="form-select">
            <option value=""><?= t('modules.checklists.digital.select_link_first') ?></option>
        </select>
    </div>

    <div class="form-group">
        <label class="form-label"><?= t('modules.checklists.digital.checklist_model') ?></label>
        <select id="infor-modelo" class="form-select deferred-chosen"
                data-chosen-placeholder="<?= t('modules.checklists.digital.select_model') ?>">
            <option value=""><?= t('modules.checklists.digital.select') ?></option>
        </select>
    </div>

    <div style="display:flex;gap:12px;">
        <div class="form-group" style="flex:1;">
            <label class="form-label" id="label-tanque"><?= t('modules.checklists.digital.tank') ?></label>
            <select id="infor-tanque" class="form-select">
                <option value=""><?= t('modules.checklists.digital.select') ?></option>
                <option value="8">Cheio</option>
                <option value="7">7/8</option>
                <option value="6">3/4</option>
                <option value="5">5/8</option>
                <option value="4">1/2</option>
                <option value="3">3/8</option>
                <option value="2">1/4</option>
                <option value="1">1/8</option>
                <option value="0">Reserva</option>
            </select>
        </div>
        <div class="form-group" style="flex:1;">
            <label class="form-label"><?= t('modules.checklists.digital.odometer') ?></label>
            <input type="text" id="infor-odometro" class="form-input" inputmode="numeric" placeholder="0" style="text-align:right;">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= t('modules.checklists.digital.observations') ?></label>
        <textarea id="infor-obs" class="form-textarea" placeholder="<?= t('modules.checklists.digital.observations_placeholder') ?>"></textarea>
    </div>

    <button class="btn-avancar" id="btn-avancar-infor" onclick="avancarInfor()"><?= t('modules.checklists.digital.advance') ?></button>
</div>

<!-- Tab Questoes -->
<div id="tab-questoes" class="tab-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="font-size:18px;font-weight:700;color:#1e293b;margin:0;"><?= t('modules.checklists.digital.questionnaire') ?></h2>
        <span id="auto-save-indicator" style="font-size:11px;color:#94a3b8;opacity:0;transition:opacity 0.3s;"><i class="fas fa-check"></i> <?= t('modules.checklists.digital.auto_saved') ?></span>
    </div>
    <div id="questoes-container"></div>
    <button class="btn-avancar" id="btn-avancar-questoes" onclick="avancarQuestoes()"><?= t('modules.checklists.digital.advance') ?></button>
</div>

<!-- Tab Vistorias -->
<div id="tab-vistorias" class="tab-panel">
    <h2 style="font-size:18px;font-weight:700;color:#1e293b;margin-bottom:16px;"><?= t('modules.checklists.digital.tab_inspection') ?></h2>
    <div id="vistorias-container"></div>
    <button class="btn-avancar" id="btn-avancar-vistorias" onclick="avancarVistorias()"><?= t('modules.checklists.digital.advance') ?></button>
</div>

<!-- Tab Assinatura -->
<div id="tab-assinatura" class="tab-panel">
    <h2 style="font-size:18px;font-weight:700;color:#1e293b;margin-bottom:16px;"><?= t('modules.checklists.digital.tab_signature') ?></h2>

    <div id="assinatura-form">
        <div class="signature-area" id="signature-area">
            <canvas id="signatureCanvas" class="signature-canvas" height="200"></canvas>
            <button class="btn-limpar" onclick="limparAssinatura()"><?= t('modules.checklists.digital.clear') ?></button>
        </div>
        <button class="btn-salvar" id="btn-salvar" onclick="salvarChecklist()" disabled><?= t('modules.checklists.digital.save') ?></button>
    </div>

    <div id="success-screen" class="success-screen" style="display:none;">
        <div class="icon"><i class="fas fa-check-circle"></i></div>
        <h2 style="font-size:22px;font-weight:700;color:#1e293b;margin-bottom:8px;"><?= t('modules.checklists.digital.saved_success') ?></h2>
        <p style="color:#64748b;margin-bottom:24px;"><?= t('modules.checklists.digital.saved_message') ?></p>
        <button id="btn-proximo-veiculo" class="btn-avancar" style="display:none;margin-bottom:12px;" onclick="iniciarProximoVeiculo()">
            <i class="fas fa-car" style="margin-right:6px;"></i> <?= t('modules.checklists.digital.next_vehicle') ?>
        </button>
        <button class="btn-avancar" style="background:#64748b;" onclick="window.history.back();"><?= t('modules.checklists.digital.close') ?></button>
    </div>
</div>

<!-- Tab bar -->
<nav class="tab-bar">
    <button class="tab-btn active" data-tab="infor" onclick="switchTab('infor')">
        <i class="fas fa-info-circle"></i>
        <span><?= t('modules.checklists.digital.tab_info') ?></span>
    </button>
    <button class="tab-btn disabled" data-tab="questoes" onclick="switchTab('questoes')">
        <i class="fas fa-check"></i>
        <span><?= t('modules.checklists.digital.tab_questions') ?></span>
    </button>
    <button class="tab-btn disabled" data-tab="vistorias" onclick="switchTab('vistorias')">
        <i class="fas fa-camera"></i>
        <span><?= t('modules.checklists.digital.tab_inspection') ?></span>
    </button>
    <button class="tab-btn disabled" data-tab="assinatura" onclick="switchTab('assinatura')">
        <i class="fas fa-signature"></i>
        <span><?= t('modules.checklists.digital.tab_signature') ?></span>
    </button>
</nav>

<!-- Loading overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div style="background:#fff;border-radius:12px;padding:32px;text-align:center;">
        <div class="spinner" style="margin:0 auto 12px;"></div>
        <p id="loadingText" style="color:#64748b;font-size:14px;">Processando...</p>
    </div>
</div>

<!-- Photo Editor Overlay -->
<div id="photoEditor" class="photo-editor">
    <div class="editor-top-bar">
        <button class="editor-btn" id="btnCloseEditor" title="Fechar">
            <i class="fas fa-times"></i>
        </button>
        <div style="display:flex;gap:8px;">
            <button class="editor-btn" id="btnSaveEditor" title="Salvar" style="background:#3b82f6;">
                <i class="fas fa-save"></i>
            </button>
        </div>
    </div>

    <div class="canvas-container" id="editorCanvasContainer">
        <div id="editorCanvasWrapper" style="position:relative;display:inline-block;transform-origin:center center;transition:transform 0.1s ease-out;">
            <canvas id="editorCanvas"></canvas>
            <div id="editorMarkersOverlay" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;"></div>
        </div>
    </div>

    <div class="editor-toolbar">
        <button class="tool-btn" id="btnEditorDelete" title="Limpar"><i class="fas fa-trash"></i></button>
        <button class="tool-btn" id="btnEditorUndo" title="Desfazer"><i class="fas fa-undo"></i></button>
        <button class="tool-btn" id="btnEditorRedo" title="Refazer"><i class="fas fa-redo"></i></button>
        <button class="tool-btn" id="btnEditorZoomIn" title="Zoom In"><i class="fas fa-search-plus"></i></button>
        <button class="tool-btn" id="btnEditorZoomOut" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
        <button class="tool-btn" id="btnEditorMarker" title="Marcadores"><i class="fas fa-map-marker-alt"></i></button>
        <button class="tool-btn" id="btnEditorDraw" title="Desenhar"><i class="fas fa-pen"></i></button>
    </div>

    <!-- Color Picker Popup -->
    <div class="popup-overlay" id="editorColorPopup">
        <div class="popup-panel" onclick="event.stopPropagation()">
            <div class="popup-handle"></div>
            <h3 style="font-size:16px;font-weight:700;text-align:center;margin-bottom:16px;">Cor do traçado:</h3>
            <div class="color-grid" id="editorColorGrid"></div>
        </div>
    </div>

    <!-- Marker Picker Popup -->
    <div class="popup-overlay" id="editorMarkerPopup">
        <div class="popup-panel" onclick="event.stopPropagation()">
            <div class="popup-handle"></div>
            <h3 style="font-size:16px;font-weight:700;text-align:center;margin-bottom:16px;">Marcadores:</h3>
            <div id="editorMarkerOptions"></div>
        </div>
    </div>
</div>

<!-- Editor save toast -->
<div class="editor-save-toast" id="editorSaveToast">
    <i class="fas fa-check-circle" style="font-size:24px;color:#22c55e;"></i>
    Foto salva!
</div>

<!-- Hidden file input for camera -->
<input type="file" id="cameraInput" accept="image/*" capture="environment" style="display:none;">

<script src="/assets/js/api.min.js"></script>
<script src="/assets/js/components.min.js"></script>
<script src="/assets/js/chosen-select.min.js"></script>
<script>
(function() {
    // ============================
    // Estado global
    // ============================
    let checklistId = null;
    let checklistCodigo = null;
    let modeloData = null;
    let questoesState = [];
    let vistoriaState = [];
    let currentVistoriaItemId = null;
    let tabsCompleted = { infor: false, questoes: false, vistorias: false };

    const TABS = ['infor', 'questoes', 'vistorias', 'assinatura'];

    // i18n strings para JS
    const i18n = {
        processing: '<?= addslashes(t('modules.checklists.digital.processing')) ?>',
        creating: '<?= addslashes(t('modules.checklists.digital.creating')) ?>',
        savingQuestions: '<?= addslashes(t('modules.checklists.digital.saving_questions')) ?>',
        savingChecklist: '<?= addslashes(t('modules.checklists.digital.saving_checklist')) ?>',
        sendingPhoto: '<?= addslashes(t('modules.checklists.digital.sending_photo')) ?>',
        deletingPhoto: '<?= addslashes(t('modules.checklists.digital.deleting_photo')) ?>',
        autoSaved: '<?= addslashes(t('modules.checklists.digital.auto_saved')) ?>',
        departureDone: '<?= addslashes(t('modules.checklists.digital.departure_done')) ?>',
        arrivalDone: '<?= addslashes(t('modules.checklists.digital.arrival_done')) ?>',
        selectVehicle: '<?= addslashes(t('modules.checklists.digital.select_vehicle')) ?>',
        errSelectType: '<?= addslashes(t('modules.checklists.digital.err_select_type')) ?>',
        errSelectMoment: '<?= addslashes(t('modules.checklists.digital.err_select_moment')) ?>',
        errSelectLink: '<?= addslashes(t('modules.checklists.digital.err_select_link')) ?>',
        errSelectVehicle: '<?= addslashes(t('modules.checklists.digital.err_select_vehicle')) ?>',
        errSelectModel: '<?= addslashes(t('modules.checklists.digital.err_select_model')) ?>',
        errSelectTank: '<?= addslashes(t('modules.checklists.digital.err_select_tank')) ?>',
        errFillOdometer: '<?= addslashes(t('modules.checklists.digital.err_fill_odometer')) ?>',
        errAnswerAll: '<?= addslashes(t('modules.checklists.digital.err_answer_all')) ?>',
        errSign: '<?= addslashes(t('modules.checklists.digital.err_sign')) ?>',
        errMinPhoto: <?= js_t('modules.checklists.digital.err_min_photo') ?>,
        tank: '<?= addslashes(t('modules.checklists.digital.tank')) ?>',
        batteryCharge: '<?= addslashes(t('modules.checklists.digital.battery_charge')) ?>',
    };

    // ============================
    // Editor de fotos - constantes e estado
    // ============================
    const EDITOR_COLORS = [
        { hex: '#FFFFFF', name: 'Branco' },
        { hex: '#000000', name: 'Preto' },
        { hex: '#EF4444', name: 'Vermelho' },
        { hex: '#3B82F6', name: 'Azul' },
        { hex: '#F59E0B', name: 'Amarelo' },
        { hex: '#22C55E', name: 'Verde' },
        { hex: '#D1D5DB', name: 'Cinza' },
        { hex: '#A855F7', name: 'Roxo' }
    ];

    const EDITOR_MARKERS = [
        { id: 'amassado', label: 'Amassado', color: '#EF4444' },
        { id: 'falta', label: 'Falta', color: '#6B7280' },
        { id: 'quebrado', label: 'Quebrado', color: '#F59E0B' },
        { id: 'riscado', label: 'Riscado', color: '#3B82F6' },
        { id: 'trincado', label: 'Trincado', color: '#22C55E' },
        { id: 'outros', label: 'Outros', color: '#A855F7' }
    ];

    const EDITOR_STROKE_PX = 3;

    let editorState = {
        currentIdx: null,
        currentItemId: null,
        currentColor: '#FFFFFF',
        currentTool: null,
        pendingMarker: null,
        actionHistory: [],
        historyIndex: -1,
        isDrawing: false,
        currentPath: [],
        zoom: 1,
        baseImage: null
    };

    // ============================
    // Tab navigation
    // ============================
    window.switchTab = function(tab) {
        const idx = TABS.indexOf(tab);
        // Pode voltar para abas anteriores ou ir para a proxima se completa
        for (let i = 0; i < idx; i++) {
            if (!tabsCompleted[TABS[i]]) return;
        }

        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
            const t = btn.dataset.tab;
            const tIdx = TABS.indexOf(t);
            if (tabsCompleted[t]) {
                btn.classList.add('completed');
                btn.classList.remove('disabled');
            } else if (tIdx <= idx) {
                btn.classList.remove('disabled');
            }
        });
        document.querySelector('.tab-btn[data-tab="' + tab + '"]').classList.add('active');
        document.querySelector('.tab-btn[data-tab="' + tab + '"]').classList.remove('disabled');

        if (tab === 'assinatura') initSignatureCanvas();
        window.scrollTo(0, 0);
    };

    function enableTab(tab) {
        document.querySelector('.tab-btn[data-tab="' + tab + '"]').classList.remove('disabled');
    }

    // ============================
    // Loading
    // ============================
    function showLoading(text) {
        document.getElementById('loadingText').textContent = text || 'Processando...';
        document.getElementById('loadingOverlay').classList.add('active');
    }
    function hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('active');
    }

    // ============================
    // Odometro formatting
    // ============================
    const odoInput = document.getElementById('infor-odometro');
    odoInput.addEventListener('input', function() {
        let v = this.value.replace(/\D/g, '');
        if (v) v = parseInt(v).toLocaleString('pt-BR');
        this.value = v;
    });

    // ============================
    // Toggle buttons: Tipo
    // ============================
    let currentTipoCombustivel = 'GE'; // tipo de combustivel do veiculo selecionado

    // Dados do vinculo atual (para fluxo "proximo veiculo")
    let vinculoAtual = null; // { tipo: 'L'|'C', id: int }
    let veiculosVinculo = []; // dados do endpoint veiculos-vinculo

    window.setTipo = function(val) {
        document.getElementById('infor-tipo').value = val;
        document.querySelectorAll('#tab-infor .toggle-group')[0].querySelectorAll('.toggle-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.value === val);
        });

        const isVinculado = val === 'V';
        document.getElementById('wrap-momento').style.display = isVinculado ? 'block' : 'none';
        document.getElementById('wrap-vinculo').style.display = isVinculado ? 'block' : 'none';
        document.getElementById('wrap-veiculo-avulso').style.display = !isVinculado ? 'block' : 'none';
        document.getElementById('wrap-veiculo-vinculado').style.display = 'none';

        if (!isVinculado) {
            document.getElementById('infor-momento').value = '';
            const vinculoSel = document.getElementById('infor-vinculo');
            if (vinculoSel.chosenSelect) vinculoSel.chosenSelect.clear();
            vinculoAtual = null;
            veiculosVinculo = [];
        } else {
            const veicSel = document.getElementById('infor-veiculo');
            if (veicSel.chosenSelect) veicSel.chosenSelect.clear();
        }
        atualizarTanqueLabels('GE');
    };

    // ============================
    // Toggle buttons: Momento
    // ============================
    window.setMomento = function(val) {
        document.getElementById('infor-momento').value = val;
        document.querySelectorAll('#wrap-momento .toggle-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.value === val);
        });
        // Recarregar veiculos do vinculo se ja tem um selecionado
        carregarVeiculosVinculo();
    };

    // ============================
    // Tanque: FuelLabels integration
    // ============================
    function atualizarTanqueLabels(tipoCombustivel) {
        currentTipoCombustivel = tipoCombustivel || 'GE';
        const selectTanque = document.getElementById('infor-tanque');
        const labelTanque = document.getElementById('label-tanque');

        if (typeof FuelLabels !== 'undefined') {
            FuelLabels.updateSelectOptions(selectTanque, currentTipoCombustivel, 'Cheio', 'Reserva');
            labelTanque.textContent = FuelLabels.isElectric(currentTipoCombustivel) ? i18n.batteryCharge : i18n.tank;
        }
    }

    // ============================
    // Chosen-select: Vinculo - ao selecionar, carregar veiculos
    // ============================
    let veiculoDataCache = {};
    const vinculoSelect = document.getElementById('infor-vinculo');

    vinculoSelect.addEventListener('change', function() {
        const val = this.value;
        document.getElementById('wrap-veiculo-vinculado').style.display = 'none';
        vinculoAtual = null;
        veiculosVinculo = [];

        if (!val) return;

        const match = val.match(/^(L|C)-(\d+)$/);
        if (match) {
            vinculoAtual = { tipo: match[1], id: parseInt(match[2]) };
            carregarVeiculosVinculo();
        }
    });

    async function carregarVeiculosVinculo() {
        if (!vinculoAtual) return;
        const momento = document.getElementById('infor-momento').value;
        if (!momento) return;

        try {
            const res = await API.get('/api/checklists/veiculos-vinculo', {
                tipo: vinculoAtual.tipo,
                id: vinculoAtual.id,
                momento: momento,
            });

            if (!res.success) return;

            veiculosVinculo = res.data;
            const select = document.getElementById('infor-veiculo-vinculado');
            select.innerHTML = '<option value="">' + i18n.selectVehicle + '</option>';

            res.data.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id_veiculo;
                if (v.checklist_feito) {
                    opt.textContent = v.text + ' ✓ ' + (momento === 'S' ? i18n.departureDone : i18n.arrivalDone);
                    opt.disabled = true;
                    opt.style.color = '#9ca3af';
                } else {
                    opt.textContent = v.text;
                }
                opt.dataset.tipoCombustivel = v.tipo_combustivel || 'GE';
                opt.dataset.odometro = v.odometro || '';
                select.appendChild(opt);
            });

            document.getElementById('wrap-veiculo-vinculado').style.display = 'block';
        } catch (e) { console.error(e); }
    }

    // Ao selecionar veiculo vinculado, preencher odometro/tanque
    document.getElementById('infor-veiculo-vinculado').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt && opt.value) {
            if (opt.dataset.odometro) {
                odoInput.value = parseInt(opt.dataset.odometro).toLocaleString('pt-BR');
            }
            atualizarTanqueLabels(opt.dataset.tipoCombustivel || 'GE');
        }
    });

    // ============================
    // Chosen-select: Veiculo avulso - ao selecionar, buscar dados extras
    // ============================
    const veiculoSelect = document.getElementById('infor-veiculo');
    veiculoSelect.addEventListener('change', async function() {
        const val = this.value;
        if (!val) return;

        if (veiculoDataCache[val]) {
            aplicarDadosVeiculo(veiculoDataCache[val]);
            return;
        }

        try {
            const res = await API.get('/api/checklists/buscar-veiculos', { q: '' });
            if (res.success && res.data) {
                res.data.forEach(item => { veiculoDataCache[String(item.id)] = item; });
                if (veiculoDataCache[val]) {
                    aplicarDadosVeiculo(veiculoDataCache[val]);
                }
            }
        } catch (e) { console.error(e); }
    });

    function aplicarDadosVeiculo(item) {
        if (item.odometro) {
            odoInput.value = parseInt(item.odometro).toLocaleString('pt-BR');
        }
        atualizarTanqueLabels(item.tipo_combustivel || 'GE');
    }

    // ============================
    // Carregar modelos + inicializar chosen-selects
    // O carregarModelos roda primeiro via API.get (que faz refresh do CSRF se 419),
    // e so depois inicializa os chosen-selects para evitar erros de CSRF expirado.
    // ============================
    async function carregarModelos() {
        try {
            const res = await API.get('/api/checklist-modelos/buscar', { q: '' });
            const select = document.getElementById('infor-modelo');
            select.innerHTML = '<option value="">Selecione...</option>';
            if (res.success || res.data) {
                (res.data || res).forEach(item => {
                    if (parseInt(item.tipo) !== 0) return; // tipo=0 = digital
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.text || item.nome;
                    select.appendChild(opt);
                });
            }
        } catch (e) {
            console.error('Erro ao carregar modelos:', e);
        }

        // Agora que o CSRF token esta valido, ativar chosen-selects
        document.querySelectorAll('.deferred-chosen').forEach(el => {
            el.classList.remove('deferred-chosen');
            el.classList.add('chosen-select');
        });
        if (typeof window.initChosenSelects === 'function') {
            window.initChosenSelects();
        }
    }
    carregarModelos();

    // ============================
    // Avancar Infor -> Questoes
    // ============================
    window.avancarInfor = async function() {
        const tipo = document.getElementById('infor-tipo').value;
        const momento = tipo === 'V' ? document.getElementById('infor-momento').value : 'N';
        const idModelo = document.getElementById('infor-modelo').value;
        const tanque = document.getElementById('infor-tanque').value;
        const odometro = odoInput.value;
        const obs = document.getElementById('infor-obs').value;
        const vinculoVal = document.getElementById('infor-vinculo').value;

        // Validacoes
        if (!tipo) return mostrarErro(i18n.errSelectType);
        if (tipo === 'V' && !momento) return mostrarErro(i18n.errSelectMoment);
        if (tipo === 'V' && !vinculoVal) return mostrarErro(i18n.errSelectLink);
        if (tipo === 'V' && !document.getElementById('infor-veiculo-vinculado').value) return mostrarErro(i18n.errSelectVehicle);
        if (tipo === 'A' && !document.getElementById('infor-veiculo').value) return mostrarErro(i18n.errSelectVehicle);
        if (!idModelo) return mostrarErro(i18n.errSelectModel);
        if (tanque === '') return mostrarErro(i18n.errSelectTank);
        if (!odometro) return mostrarErro(i18n.errFillOdometer);

        // Extrair IDs
        let idLocacao = null, idContrato = null, idVeiculo = null;

        if (tipo === 'V' && vinculoVal) {
            const match = vinculoVal.match(/^(L|C)-(\d+)$/);
            if (match) {
                if (match[1] === 'L') idLocacao = parseInt(match[2]);
                else idContrato = parseInt(match[2]);
            }
            idVeiculo = parseInt(document.getElementById('infor-veiculo-vinculado').value) || null;
        } else if (tipo === 'A') {
            idVeiculo = parseInt(document.getElementById('infor-veiculo').value) || null;
        }

        showLoading(i18n.creating);

        try {
            const payload = {
                tipo,
                momento,
                id_modelo: parseInt(idModelo),
                id_veiculo: idVeiculo,
                id_locacao: idLocacao,
                id_contrato: idContrato,
                tanque,
                odometro,
                obs,
            };

            const res = await API.post('/api/checklists/criar', payload);

            if (!res.success) {
                hideLoading();
                mostrarErro(res.message || 'Erro ao criar checklist');
                return;
            }

            checklistId = res.id;
            checklistCodigo = res.codigo;

            // Carregar dados do modelo para questoes e vistoria
            const modeloRes = await API.get('/api/checklist-modelos/' + idModelo);
            if (modeloRes.success || modeloRes.data) {
                modeloData = modeloRes.data || modeloRes;
            }

            // Montar aba questoes
            montarQuestoes();
            montarVistorias();

            tabsCompleted.infor = true;
            hideLoading();
            switchTab('questoes');
        } catch (e) {
            hideLoading();
            mostrarErro('Erro de conexão: ' + e.message);
        }
    };

    function mostrarErro(msg) {
        // Usar alert simples na pagina standalone (nao tem modal system do parent)
        // Criar toast temporario
        const toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;top:60px;left:50%;transform:translateX(-50%);background:#ef4444;color:#fff;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:500;z-index:60;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // ============================
    // Questoes
    // ============================
    function montarQuestoes() {
        const questoes = JSON.parse(modeloData?.questoes || '[]');
        questoesState = questoes.map(q => ({ ...q, opt: null }));

        const container = document.getElementById('questoes-container');
        let html = '';

        questoesState.forEach((q, idx) => {
            html += '<div class="questao-card" data-idx="' + idx + '">' +
                '<div class="title">' + escapeHtml(q.content || q.pergunta || 'Item ' + (idx + 1)) + '</div>' +
                '<div class="questao-options">' +
                    optBtn(idx, '1', 'fa-check', 'Confere') +
                    optBtn(idx, '2', 'fa-xmark', 'Não confere') +
                    optBtn(idx, '3', 'fa-triangle-exclamation', 'Danificado') +
                    optBtn(idx, '4', 'fa-regular fa-circle', 'N/A') +
                '</div>' +
            '</div>';
        });

        container.innerHTML = html;
    }

    function optBtn(idx, val, icon, label) {
        const iconCls = icon.includes('fa-regular') || icon.includes('far ') ? icon : 'fas ' + icon;
        return '<div class="questao-opt" data-idx="' + idx + '" data-val="' + val + '" onclick="selectOpt(this)">' +
            '<i class="' + iconCls + '"></i>' + label +
        '</div>';
    }

    window.selectOpt = function(el) {
        const idx = parseInt(el.dataset.idx);
        const val = el.dataset.val;

        questoesState[idx].opt = val;

        // Atualizar UI
        const card = el.closest('.questao-card');
        card.querySelectorAll('.questao-opt').forEach(o => {
            o.className = 'questao-opt';
        });
        el.classList.add('selected-' + val);
    };

    window.avancarQuestoes = async function() {
        // Verificar que todas as questoes foram respondidas
        const naoRespondidas = questoesState.filter(q => !q.opt);
        if (naoRespondidas.length > 0) {
            mostrarErro(i18n.errAnswerAll.replace(':count', naoRespondidas.length));
            return;
        }

        showLoading(i18n.savingQuestions);

        try {
            const res = await API.post('/api/checklists/' + checklistId + '/questoes', {
                questoes: questoesState,
            });

            if (!res.success) {
                hideLoading();
                mostrarErro(res.message || 'Erro ao salvar');
                return;
            }

            tabsCompleted.questoes = true;
            hideLoading();
            switchTab('vistorias');
        } catch (e) {
            hideLoading();
            mostrarErro('Erro de conexão: ' + e.message);
        }
    };

    // ============================
    // Vistorias
    // ============================
    function montarVistorias() {
        const items = JSON.parse(modeloData?.vistoria || '[]');
        vistoriaState = items.map(v => ({ ...v, img: null, img_url: null, img_original_url: null, editorDrawings: [], editorMarkers: [] }));
        renderVistorias();
    }

    function renderVistorias() {
        const container = document.getElementById('vistorias-container');
        let html = '';

        vistoriaState.forEach((item, idx) => {
            const hasImg = !!item.img;
            html += '<div class="vistoria-card">' +
                '<div class="vistoria-thumb">' +
                    (hasImg
                        ? '<img src="' + escapeHtml(item.img_url) + '" alt="' + escapeHtml(item.content) + '">'
                        : '<i class="fas fa-camera"></i>') +
                '</div>' +
                '<div class="vistoria-info"><div class="name">' + escapeHtml(item.content || 'Item ' + (idx + 1)) + '</div></div>' +
                '<div class="vistoria-actions">' +
                    (hasImg
                        ? '<button class="btn-view" onclick="abrirEditor(' + idx + ', \'' + escapeHtml(String(item.id)) + '\')"><i class="fas fa-pen"></i></button>' +
                          '<button class="btn-delete" onclick="excluirFoto(' + idx + ', \'' + escapeHtml(String(item.id)) + '\')"><i class="fas fa-trash"></i></button>'
                        : '<button class="btn-camera" onclick="tirarFoto(' + idx + ', \'' + escapeHtml(String(item.id)) + '\')"><i class="fas fa-camera"></i></button>') +
                '</div>' +
            '</div>';
        });

        container.innerHTML = html;
    }

    window.tirarFoto = function(idx, itemId) {
        currentVistoriaItemId = { idx, itemId };
        document.getElementById('cameraInput').click();
    };

    // Camera input change
    document.getElementById('cameraInput').addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file || !currentVistoriaItemId) return;

        const { idx, itemId } = currentVistoriaItemId;

        showLoading(i18n.sendingPhoto);

        try {
            const base64 = await resizeAndConvert(file, 1200);

            const res = await API.post('/api/checklists/' + checklistId + '/vistoria/upload', {
                item_id: itemId,
                foto: base64,
            });

            if (res.success) {
                vistoriaState[idx].img = res.filename;
                vistoriaState[idx].img_url = res.url;
                vistoriaState[idx].img_original_url = res.url;
                vistoriaState[idx].editorDrawings = [];
                vistoriaState[idx].editorMarkers = [];
                renderVistorias();
            } else {
                mostrarErro(res.message || 'Erro ao enviar foto');
            }
        } catch (err) {
            mostrarErro('Erro ao enviar foto: ' + err.message);
        } finally {
            hideLoading();
            this.value = '';
            currentVistoriaItemId = null;
        }
    });

    function resizeAndConvert(file, maxSize) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    let w = img.width, h = img.height;
                    if (w > maxSize || h > maxSize) {
                        if (w > h) { h = Math.round(h * maxSize / w); w = maxSize; }
                        else { w = Math.round(w * maxSize / h); h = maxSize; }
                    }
                    canvas.width = w;
                    canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    resolve(canvas.toDataURL('image/jpeg', 0.85));
                };
                img.onerror = reject;
                img.src = e.target.result;
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    // ============================
    // Editor de fotos
    // ============================
    let editorCanvas, editorCtx, editorContainer, editorOverlay;
    let editorInitialized = false;

    function initEditorDOM() {
        if (editorInitialized) return true;
        editorCanvas = document.getElementById('editorCanvas');
        if (!editorCanvas) return false;
        editorCtx = editorCanvas.getContext('2d');
        editorContainer = document.getElementById('editorCanvasContainer');
        editorOverlay = document.getElementById('editorMarkersOverlay');
        initEditorEvents();
        editorInitialized = true;
        return true;
    }

    window.abrirEditor = function(idx, itemId) {
        const item = vistoriaState[idx];
        if (!item || !item.img_url) return;
        if (!initEditorDOM()) return;

        editorState.currentIdx = idx;
        editorState.currentItemId = itemId;
        editorState.currentTool = null;
        editorState.pendingMarker = null;
        editorState.zoom = 1;
        editorState.isDrawing = false;
        editorState.currentPath = [];

        // Rebuild action history from saved data
        editorState.actionHistory = [];
        if (item.editorDrawings && item.editorDrawings.length) {
            item.editorDrawings.forEach(d => editorState.actionHistory.push({ type: 'stroke', data: JSON.parse(JSON.stringify(d)) }));
        }
        if (item.editorMarkers && item.editorMarkers.length) {
            item.editorMarkers.forEach(m => editorState.actionHistory.push({ type: 'marker', data: JSON.parse(JSON.stringify(m)) }));
        }
        editorState.historyIndex = editorState.actionHistory.length - 1;

        document.getElementById('photoEditor').classList.add('active');
        document.body.style.overflow = 'hidden';
        editorUpdateToolButtons();

        const img = new Image();
        img.onload = () => {
            editorState.baseImage = img;
            editorFitCanvas();
            editorRedraw();
            editorRenderMarkers();
        };
        img.onerror = () => {
            console.error('Erro ao carregar imagem para editor:', item.img_original_url || item.img_url);
            mostrarErro('Erro ao carregar imagem');
            fecharEditor();
        };
        // Use original URL if available, otherwise current URL
        img.src = item.img_original_url || item.img_url;
    };

    window.fecharEditor = function() {
        document.getElementById('photoEditor').classList.remove('active');
        document.body.style.overflow = '';
        editorState.currentIdx = null;
        editorState.currentItemId = null;
        editorState.baseImage = null;
    };

    function editorFitCanvas() {
        const img = editorState.baseImage;
        if (!img) return;
        const cw = editorContainer.clientWidth;
        const ch = editorContainer.clientHeight;
        const baseScale = Math.min(cw / img.width, ch / img.height);
        editorCanvas.width = img.width * baseScale;
        editorCanvas.height = img.height * baseScale;
        editorCanvas.style.width = editorCanvas.width + 'px';
        editorCanvas.style.height = editorCanvas.height + 'px';
        const wrapper = document.getElementById('editorCanvasWrapper');
        wrapper.style.transform = 'scale(' + editorState.zoom + ')';
    }

    function editorRedraw() {
        const img = editorState.baseImage;
        if (!img) return;
        editorCtx.clearRect(0, 0, editorCanvas.width, editorCanvas.height);
        editorCtx.drawImage(img, 0, 0, editorCanvas.width, editorCanvas.height);

        const actions = editorState.actionHistory.slice(0, editorState.historyIndex + 1);
        const scaleX = editorCanvas.width / img.width;
        const scaleY = editorCanvas.height / img.height;

        actions.forEach(action => {
            if (action.type !== 'stroke') return;
            const stroke = action.data;
            if (stroke.points.length < 2) return;
            editorCtx.beginPath();
            editorCtx.strokeStyle = stroke.color;
            editorCtx.lineWidth = stroke.width * scaleX;
            editorCtx.lineCap = 'round';
            editorCtx.lineJoin = 'round';
            editorCtx.moveTo(stroke.points[0].x * scaleX, stroke.points[0].y * scaleY);
            for (let i = 1; i < stroke.points.length; i++) {
                editorCtx.lineTo(stroke.points[i].x * scaleX, stroke.points[i].y * scaleY);
            }
            editorCtx.stroke();
        });

        // Live drawing path
        if (editorState.currentPath.length > 1) {
            editorCtx.beginPath();
            editorCtx.strokeStyle = editorState.currentColor;
            editorCtx.lineWidth = EDITOR_STROKE_PX;
            editorCtx.lineCap = 'round';
            editorCtx.lineJoin = 'round';
            const sx = editorCanvas.width / img.width;
            const sy = editorCanvas.height / img.height;
            editorCtx.moveTo(editorState.currentPath[0].x * sx, editorState.currentPath[0].y * sy);
            for (let i = 1; i < editorState.currentPath.length; i++) {
                editorCtx.lineTo(editorState.currentPath[i].x * sx, editorState.currentPath[i].y * sy);
            }
            editorCtx.stroke();
        }
    }

    function editorRenderMarkers() {
        editorOverlay.innerHTML = '';
        const img = editorState.baseImage;
        if (!img) return;

        const activeMarkers = editorState.actionHistory
            .slice(0, editorState.historyIndex + 1)
            .filter(a => a.type === 'marker')
            .map(a => a.data);

        activeMarkers.forEach(m => {
            const px = (m.x / img.width) * editorCanvas.width;
            const py = (m.y / img.height) * editorCanvas.height;
            const marker = EDITOR_MARKERS.find(mk => mk.id === m.type);
            if (!marker) return;

            const el = document.createElement('div');
            el.className = 'canvas-marker';
            el.style.left = px + 'px';
            el.style.top = (py - 44) + 'px';
            el.innerHTML =
                '<svg width="26" height="40" viewBox="0 0 26 40">' +
                    '<path d="M13 0C5.82 0 0 5.82 0 13c0 4.5 2.2 8.5 5.5 12.5C8.5 29.5 13 40 13 40s4.5-10.5 7.5-14.5C23.8 21.5 26 17.5 26 13 26 5.82 20.18 0 13 0z" fill="' + marker.color + '"/>' +
                    '<circle cx="13" cy="12" r="4.5" fill="white" opacity="0.9"/>' +
                '</svg>' +
                '<span class="canvas-marker-label">' + marker.label + '</span>';
            editorOverlay.appendChild(el);
        });
    }

    function editorGetCanvasCoords(e) {
        const rect = editorCanvas.getBoundingClientRect();
        const touch = e.touches ? e.touches[0] : e;
        const x = touch.clientX - rect.left;
        const y = touch.clientY - rect.top;
        const img = editorState.baseImage;
        return {
            x: (x / rect.width) * img.width,
            y: (y / rect.height) * img.height
        };
    }

    // Pinch-to-zoom state
    let editorPinchStartDist = 0;
    let editorPinchStartZoom = 1;

    function initEditorEvents() {
        // Canvas pointer events
        editorCanvas.addEventListener('pointerdown', (e) => {
            if (editorState.currentTool === 'draw') {
                e.preventDefault();
                editorState.isDrawing = true;
                const pt = editorGetCanvasCoords(e);
                editorState.currentPath = [pt];
                editorCanvas.setPointerCapture(e.pointerId);
            } else if (editorState.pendingMarker) {
                e.preventDefault();
                const pt = editorGetCanvasCoords(e);
                editorState.actionHistory = editorState.actionHistory.slice(0, editorState.historyIndex + 1);
                editorState.actionHistory.push({ type: 'marker', data: { type: editorState.pendingMarker, x: pt.x, y: pt.y } });
                editorState.historyIndex = editorState.actionHistory.length - 1;
                editorState.pendingMarker = null;
                editorState.currentTool = null;
                editorUpdateToolButtons();
                editorRenderMarkers();
                editorClosePopup('editorMarkerPopup');
            }
        });

        editorCanvas.addEventListener('pointermove', (e) => {
            if (!editorState.isDrawing) return;
            e.preventDefault();
            const pt = editorGetCanvasCoords(e);
            editorState.currentPath.push(pt);
            editorRedraw();
        });

        editorCanvas.addEventListener('pointerup', (e) => {
            if (!editorState.isDrawing) return;
            editorState.isDrawing = false;
            if (editorState.currentPath.length > 1) {
                editorState.actionHistory = editorState.actionHistory.slice(0, editorState.historyIndex + 1);
                const imgWidth = editorState.baseImage ? editorState.baseImage.width : 1;
                const strokeImgWidth = EDITOR_STROKE_PX * (imgWidth / editorCanvas.width);
                editorState.actionHistory.push({ type: 'stroke', data: { color: editorState.currentColor, width: strokeImgWidth, points: [...editorState.currentPath] } });
                editorState.historyIndex = editorState.actionHistory.length - 1;
            }
            editorState.currentPath = [];
            editorRedraw();
        });

        editorCanvas.addEventListener('pointercancel', () => {
            editorState.isDrawing = false;
            editorState.currentPath = [];
            editorRedraw();
        });

        // Pinch-to-zoom
        editorContainer.addEventListener('touchstart', (e) => {
            if (e.touches.length === 2) {
                if (editorState.isDrawing) {
                    editorState.isDrawing = false;
                    editorState.currentPath = [];
                    editorRedraw();
                }
                e.preventDefault();
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                editorPinchStartDist = Math.sqrt(dx * dx + dy * dy);
                editorPinchStartZoom = editorState.zoom;
            }
        }, { passive: false });

        editorContainer.addEventListener('touchmove', (e) => {
            if (e.touches.length === 2) {
                e.preventDefault();
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                const dist = Math.sqrt(dx * dx + dy * dy);
                const scale = dist / editorPinchStartDist;
                editorState.zoom = Math.max(0.5, Math.min(4, editorPinchStartZoom * scale));
                editorFitCanvas();
                editorRedraw();
                editorRenderMarkers();
            }
        }, { passive: false });

        // Toolbar button handlers
        document.getElementById('btnCloseEditor').onclick = () => fecharEditor();

        document.getElementById('btnEditorDelete').onclick = () => {
            if (editorState.actionHistory.length === 0 && editorState.historyIndex < 0) return;
            if (!confirm('Limpar todos os traços e marcadores?')) return;
            editorState.actionHistory = [];
            editorState.historyIndex = -1;
            editorRedraw();
            editorRenderMarkers();
        };

        document.getElementById('btnEditorUndo').onclick = () => {
            if (editorState.historyIndex >= 0) {
                editorState.historyIndex--;
                editorRedraw();
                editorRenderMarkers();
            }
        };

        document.getElementById('btnEditorRedo').onclick = () => {
            if (editorState.historyIndex < editorState.actionHistory.length - 1) {
                editorState.historyIndex++;
                editorRedraw();
                editorRenderMarkers();
            }
        };

        document.getElementById('btnEditorZoomIn').onclick = () => {
            editorState.zoom = Math.min(4, editorState.zoom + 0.3);
            editorFitCanvas();
            editorRedraw();
            editorRenderMarkers();
        };

        document.getElementById('btnEditorZoomOut').onclick = () => {
            editorState.zoom = Math.max(0.5, editorState.zoom - 0.3);
            editorFitCanvas();
            editorRedraw();
            editorRenderMarkers();
        };

        document.getElementById('btnEditorDraw').onclick = () => {
            editorState.pendingMarker = null;
            editorShowPopup('editorColorPopup');
            editorState.currentTool = 'draw';
            editorUpdateToolButtons();
        };

        document.getElementById('btnEditorMarker').onclick = () => {
            editorShowPopup('editorMarkerPopup');
            editorState.currentTool = 'marker';
            editorUpdateToolButtons();
        };

        document.getElementById('editorColorPopup').onclick = () => editorClosePopup('editorColorPopup');
        document.getElementById('editorMarkerPopup').onclick = () => editorClosePopup('editorMarkerPopup');

        document.getElementById('btnSaveEditor').onclick = async () => {
        const idx = editorState.currentIdx;
        const item = vistoriaState[idx];
        if (!item || !editorState.baseImage) return;

        const activeActions = editorState.actionHistory.slice(0, editorState.historyIndex + 1);
        const drawings = activeActions.filter(a => a.type === 'stroke').map(a => JSON.parse(JSON.stringify(a.data)));
        const markers = activeActions.filter(a => a.type === 'marker').map(a => JSON.parse(JSON.stringify(a.data)));

        // Composite image at full resolution
        const img = editorState.baseImage;
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = img.width;
        tempCanvas.height = img.height;
        const tctx = tempCanvas.getContext('2d');
        tctx.drawImage(img, 0, 0);

        // Draw strokes
        drawings.forEach(stroke => {
            if (stroke.points.length < 2) return;
            tctx.beginPath();
            tctx.strokeStyle = stroke.color;
            tctx.lineWidth = stroke.width;
            tctx.lineCap = 'round';
            tctx.lineJoin = 'round';
            tctx.moveTo(stroke.points[0].x, stroke.points[0].y);
            for (let i = 1; i < stroke.points.length; i++) {
                tctx.lineTo(stroke.points[i].x, stroke.points[i].y);
            }
            tctx.stroke();
        });

        // Draw markers baked into image
        const pinScale = Math.max(1, Math.min(img.width, img.height) / 500);
        markers.forEach(m => {
            const markerDef = EDITOR_MARKERS.find(mk => mk.id === m.type);
            if (!markerDef) return;

            const px = m.x;
            const py = m.y;
            const pinW = 26 * pinScale;
            const pinH = 40 * pinScale;

            // Pin body
            tctx.save();
            tctx.translate(px - pinW / 2, py - pinH);
            tctx.scale(pinScale, pinScale);
            tctx.beginPath();
            tctx.moveTo(13, 40);
            tctx.bezierCurveTo(8.5, 29.5, 0, 21, 0, 13);
            tctx.arc(13, 13, 13, Math.PI, 0, false);
            tctx.bezierCurveTo(26, 21, 17.5, 29.5, 13, 40);
            tctx.closePath();
            tctx.fillStyle = markerDef.color;
            tctx.fill();

            // Pin inner circle
            tctx.beginPath();
            tctx.arc(13, 12, 4.5, 0, Math.PI * 2);
            tctx.fillStyle = 'rgba(255,255,255,0.9)';
            tctx.fill();
            tctx.restore();

            // Label
            const fontSize = Math.round(12 * pinScale);
            tctx.font = 'bold ' + fontSize + 'px Inter, sans-serif';
            const labelText = markerDef.label;
            const textMetrics = tctx.measureText(labelText);
            const labelPadX = 6 * pinScale;
            const labelPadY = 3 * pinScale;
            const labelX = px + pinW / 2 + 2 * pinScale;
            const labelY = py - pinH + 2 * pinScale;
            const labelW = textMetrics.width + labelPadX * 2;
            const labelH = fontSize + labelPadY * 2;
            const labelR = 4 * pinScale;

            // Label background
            tctx.fillStyle = 'rgba(0,0,0,0.8)';
            tctx.beginPath();
            tctx.moveTo(labelX + labelR, labelY);
            tctx.lineTo(labelX + labelW - labelR, labelY);
            tctx.arcTo(labelX + labelW, labelY, labelX + labelW, labelY + labelR, labelR);
            tctx.lineTo(labelX + labelW, labelY + labelH - labelR);
            tctx.arcTo(labelX + labelW, labelY + labelH, labelX + labelW - labelR, labelY + labelH, labelR);
            tctx.lineTo(labelX + labelR, labelY + labelH);
            tctx.arcTo(labelX, labelY + labelH, labelX, labelY + labelH - labelR, labelR);
            tctx.lineTo(labelX, labelY + labelR);
            tctx.arcTo(labelX, labelY, labelX + labelR, labelY, labelR);
            tctx.closePath();
            tctx.fill();

            // Label text
            tctx.fillStyle = '#FFFFFF';
            tctx.textBaseline = 'middle';
            tctx.fillText(labelText, labelX + labelPadX, labelY + labelH / 2);
        });

        const base64 = tempCanvas.toDataURL('image/jpeg', 0.85);

        showLoading(i18n.sendingPhoto);

        try {
            const res = await API.post('/api/checklists/' + checklistId + '/vistoria/upload', {
                item_id: editorState.currentItemId,
                foto: base64,
            });

            if (res.success) {
                item.img = res.filename;
                item.img_url = res.url;
                item.editorDrawings = drawings;
                item.editorMarkers = markers;
                renderVistorias();
                editorShowToast();
                setTimeout(() => fecharEditor(), 800);
            } else {
                mostrarErro(res.message || 'Erro ao salvar foto editada');
            }
        } catch (err) {
            mostrarErro('Erro ao salvar: ' + err.message);
        } finally {
            hideLoading();
        }
    };
    } // end initEditorEvents

    // Toolbar buttons
    function editorUpdateToolButtons() {
        document.querySelectorAll('#photoEditor .tool-btn').forEach(b => b.classList.remove('active'));
        if (editorState.currentTool === 'draw') document.getElementById('btnEditorDraw').classList.add('active');
        if (editorState.currentTool === 'marker') document.getElementById('btnEditorMarker').classList.add('active');
    }

    // Color picker
    function editorBuildColorPicker() {
        const grid = document.getElementById('editorColorGrid');
        grid.innerHTML = '';
        EDITOR_COLORS.forEach(c => {
            const el = document.createElement('div');
            el.className = 'color-swatch' + (editorState.currentColor === c.hex ? ' selected' : '');
            el.style.background = c.hex;
            if (c.hex === '#FFFFFF') el.style.border = '3px solid #e2e8f0';
            if (editorState.currentColor === c.hex) {
                const checkColor = (c.hex === '#FFFFFF' || c.hex === '#F59E0B' || c.hex === '#D1D5DB') ? '#22c55e' : 'white';
                el.innerHTML = '<i class="fas fa-check" style="font-size:18px;color:' + checkColor + ';"></i>';
            }
            el.onclick = () => {
                editorState.currentColor = c.hex;
                editorBuildColorPicker();
                setTimeout(() => editorClosePopup('editorColorPopup'), 200);
            };
            grid.appendChild(el);
        });
    }

    // Marker picker
    function editorBuildMarkerPicker() {
        const cont = document.getElementById('editorMarkerOptions');
        cont.innerHTML = '';
        EDITOR_MARKERS.forEach(m => {
            const el = document.createElement('div');
            el.className = 'marker-option';
            el.innerHTML =
                '<div class="marker-pin">' +
                    '<svg width="26" height="40" viewBox="0 0 26 40">' +
                        '<path d="M13 0C5.82 0 0 5.82 0 13c0 4.5 2.2 8.5 5.5 12.5C8.5 29.5 13 40 13 40s4.5-10.5 7.5-14.5C23.8 21.5 26 17.5 26 13 26 5.82 20.18 0 13 0z" fill="' + m.color + '"/>' +
                        '<circle cx="13" cy="12" r="4.5" fill="white" opacity="0.9"/>' +
                    '</svg>' +
                '</div>' +
                '<span style="font-weight:600;font-size:15px;">' + m.label + '</span>';
            el.onclick = () => {
                editorState.pendingMarker = m.id;
                editorClosePopup('editorMarkerPopup');
            };
            cont.appendChild(el);
        });
    }

    // Popups
    function editorShowPopup(id) {
        document.getElementById(id).classList.add('show');
        if (id === 'editorColorPopup') editorBuildColorPicker();
        if (id === 'editorMarkerPopup') editorBuildMarkerPicker();
    }
    function editorClosePopup(id) {
        document.getElementById(id).classList.remove('show');
    }

    // Toast
    function editorShowToast() {
        const t = document.getElementById('editorSaveToast');
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 1200);
    }

    // Window resize handler for editor
    window.addEventListener('resize', () => {
        const editor = document.getElementById('photoEditor');
        if (editor && editor.classList.contains('active') && editorState.baseImage) {
            editorFitCanvas();
            editorRedraw();
            editorRenderMarkers();
        }
    });

    window.excluirFoto = async function(idx, itemId) {
        showLoading(i18n.deletingPhoto);
        try {
            const res = await API.post('/api/checklists/' + checklistId + '/vistoria/' + itemId + '/excluir');
            if (res.success) {
                vistoriaState[idx].img = null;
                vistoriaState[idx].img_url = null;
                vistoriaState[idx].img_original_url = null;
                vistoriaState[idx].editorDrawings = [];
                vistoriaState[idx].editorMarkers = [];
                renderVistorias();
            } else {
                mostrarErro(res.message || 'Erro ao excluir');
            }
        } catch (err) {
            mostrarErro('Erro: ' + err.message);
        } finally {
            hideLoading();
        }
    };

    window.avancarVistorias = function() {
        const temFoto = vistoriaState.some(v => v.img);
        if (!temFoto) {
            mostrarErro(i18n.errMinPhoto);
            return;
        }
        tabsCompleted.vistorias = true;
        switchTab('assinatura');
    };

    // ============================
    // Assinatura (canvas)
    // ============================
    let sigCanvas, sigCtx, isDrawing = false, hasSignature = false, lastX = 0, lastY = 0;

    function initSignatureCanvas() {
        sigCanvas = document.getElementById('signatureCanvas');
        if (!sigCanvas || sigCanvas._initialized) return;
        sigCanvas._initialized = true;
        sigCtx = sigCanvas.getContext('2d');

        function resizeCanvas() {
            const rect = sigCanvas.parentElement.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;
            const w = rect.width - 24;

            // Salvar conteudo antes de redimensionar
            let savedImage = null;
            if (hasSignature) {
                savedImage = sigCtx.getImageData(0, 0, sigCanvas.width, sigCanvas.height);
            }

            const oldW = sigCanvas.width;
            const oldH = sigCanvas.height;
            sigCanvas.style.width = w + 'px';
            sigCanvas.style.height = '200px';
            sigCanvas.width = w * dpr;
            sigCanvas.height = 200 * dpr;
            sigCtx.scale(dpr, dpr);
            sigCtx.strokeStyle = '#1f2937';
            sigCtx.lineWidth = 2;
            sigCtx.lineCap = 'round';
            sigCtx.lineJoin = 'round';

            // Restaurar conteudo apos redimensionar
            if (savedImage && hasSignature) {
                const tempCanvas = document.createElement('canvas');
                tempCanvas.width = oldW;
                tempCanvas.height = oldH;
                tempCanvas.getContext('2d').putImageData(savedImage, 0, 0);
                sigCtx.setTransform(1, 0, 0, 1, 0, 0);
                sigCtx.drawImage(tempCanvas, 0, 0, oldW, oldH, 0, 0, sigCanvas.width, sigCanvas.height);
                sigCtx.scale(dpr, dpr);
            }
        }

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        function getPos(e) {
            const rect = sigCanvas.getBoundingClientRect();
            let cx, cy;
            if (e.touches && e.touches.length) { cx = e.touches[0].clientX; cy = e.touches[0].clientY; }
            else { cx = e.clientX; cy = e.clientY; }
            return { x: cx - rect.left, y: cy - rect.top };
        }

        function startDraw(e) { e.preventDefault(); isDrawing = true; const p = getPos(e); lastX = p.x; lastY = p.y; }
        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const p = getPos(e);
            sigCtx.beginPath();
            sigCtx.moveTo(lastX, lastY);
            sigCtx.lineTo(p.x, p.y);
            sigCtx.stroke();
            lastX = p.x; lastY = p.y;
            if (!hasSignature) {
                hasSignature = true;
                document.getElementById('signature-area').classList.add('has-signature');
                document.getElementById('btn-salvar').disabled = false;
            }
        }
        function stopDraw() { isDrawing = false; }

        sigCanvas.addEventListener('mousedown', startDraw);
        sigCanvas.addEventListener('mousemove', draw);
        sigCanvas.addEventListener('mouseup', stopDraw);
        sigCanvas.addEventListener('mouseout', stopDraw);
        sigCanvas.addEventListener('touchstart', startDraw, { passive: false });
        sigCanvas.addEventListener('touchmove', draw, { passive: false });
        sigCanvas.addEventListener('touchend', stopDraw);
        sigCanvas.addEventListener('touchcancel', stopDraw);
    }

    window.limparAssinatura = function() {
        if (!sigCanvas || !sigCtx) return;
        const dpr = window.devicePixelRatio || 1;
        sigCtx.clearRect(0, 0, sigCanvas.width / dpr, sigCanvas.height / dpr);
        hasSignature = false;
        document.getElementById('signature-area').classList.remove('has-signature');
        document.getElementById('btn-salvar').disabled = true;
    };

    // ============================
    // Salvar checklist (finalizar)
    // ============================
    window.salvarChecklist = async function() {
        if (!hasSignature) { mostrarErro(i18n.errSign); return; }

        showLoading(i18n.savingChecklist);

        try {
            // Exportar com fundo branco (PNG transparente vira preto no mPDF)
            const expCanvas = document.createElement('canvas');
            expCanvas.width = sigCanvas.width;
            expCanvas.height = sigCanvas.height;
            const expCtx = expCanvas.getContext('2d');
            expCtx.fillStyle = '#ffffff';
            expCtx.fillRect(0, 0, expCanvas.width, expCanvas.height);
            expCtx.drawImage(sigCanvas, 0, 0);
            const sigData = expCanvas.toDataURL('image/jpeg', 0.9);

            const res = await API.post('/api/checklists/' + checklistId + '/assinar', {
                assinatura: sigData,
            });

            if (res.success) {
                document.getElementById('assinatura-form').style.display = 'none';
                document.getElementById('success-screen').style.display = 'block';
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.add('disabled'));

                // Verificar se ha mais veiculos pendentes (para vinculado)
                if (vinculoAtual && veiculosVinculo.length > 0) {
                    // Marcar veiculo atual como feito
                    const veicFeito = document.getElementById('infor-veiculo-vinculado').value;
                    veiculosVinculo.forEach(v => {
                        if (String(v.id_veiculo) === String(veicFeito)) v.checklist_feito = true;
                    });
                    const pendentes = veiculosVinculo.filter(v => !v.checklist_feito);
                    if (pendentes.length > 0) {
                        document.getElementById('btn-proximo-veiculo').style.display = 'block';
                    }
                }
            } else {
                mostrarErro(res.message || 'Erro ao salvar');
            }
        } catch (e) {
            mostrarErro('Erro de conexão: ' + e.message);
        } finally {
            hideLoading();
        }
    };

    // ============================
    // Helpers
    // ============================
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================
    // Proximo veiculo (contratos multi-veiculo)
    // ============================
    window.iniciarProximoVeiculo = function() {
        // Resetar estado
        checklistId = null;
        checklistCodigo = null;
        modeloData = null;
        questoesState = [];
        vistoriaState = [];
        tabsCompleted = { infor: false, questoes: false, vistorias: false };
        hasSignature = false;
        editorState = { currentIdx: null, currentItemId: null, currentColor: '#FFFFFF', currentTool: null, pendingMarker: null, actionHistory: [], historyIndex: -1, isDrawing: false, currentPath: [], zoom: 1, baseImage: null };

        // Limpar assinatura
        if (sigCanvas && sigCtx) {
            const dpr = window.devicePixelRatio || 1;
            sigCtx.clearRect(0, 0, sigCanvas.width / dpr, sigCanvas.height / dpr);
            document.getElementById('signature-area').classList.remove('has-signature');
            document.getElementById('btn-salvar').disabled = true;
        }
        sigCanvas = null;
        sigCanvas = null;

        // Mostrar form de assinatura, esconder sucesso
        document.getElementById('assinatura-form').style.display = 'block';
        document.getElementById('success-screen').style.display = 'none';
        document.getElementById('btn-proximo-veiculo').style.display = 'none';

        // Limpar abas questoes/vistorias
        document.getElementById('questoes-container').innerHTML = '';
        document.getElementById('vistorias-container').innerHTML = '';

        // Limpar campos que precisam ser preenchidos novamente
        document.getElementById('infor-veiculo-vinculado').value = '';
        document.getElementById('infor-tanque').value = '';
        document.getElementById('infor-odometro').value = '';
        document.getElementById('infor-obs').value = '';

        // Recarregar veiculos do vinculo (para atualizar status checklist_feito)
        carregarVeiculosVinculo();

        // Voltar para aba Infor
        // Tipo, momento e vinculo ficam pre-selecionados
        // Modelo fica pre-selecionado (editavel)
        switchTab('infor');
    };

    // ============================
    // Auto-save questoes (cada 30s)
    // ============================
    let autoSaveInterval = null;

    function iniciarAutoSave() {
        if (autoSaveInterval) return;
        autoSaveInterval = setInterval(async () => {
            if (!checklistId || !questoesState.length) return;
            const respondidas = questoesState.filter(q => q.opt);
            if (respondidas.length === 0) return;
            try {
                await API.post('/api/checklists/' + checklistId + '/questoes', { questoes: questoesState });
                // Indicador discreto
                const el = document.getElementById('auto-save-indicator');
                if (el) { el.style.opacity = '1'; setTimeout(() => { el.style.opacity = '0'; }, 2000); }
            } catch (e) { /* silencioso */ }
        }, 30000);
    }

    function pararAutoSave() {
        if (autoSaveInterval) { clearInterval(autoSaveInterval); autoSaveInterval = null; }
    }

    // Iniciar auto-save quando entra na aba questoes
    const origSwitchTab = window.switchTab;
    window.switchTab = function(tab) {
        origSwitchTab(tab);
        if (tab === 'questoes') iniciarAutoSave();
        else pararAutoSave();
    };

    // ============================
    // Retomar checklist pendente
    // ============================
    const retomarId = <?= json_encode($retomar_id ?? null) ?>;

    async function retomarChecklist(id) {
        showLoading(i18n.processing);
        try {
            const res = await API.get('/api/checklists/novo/' + id);
            if (!res.success) { hideLoading(); return; }

            const d = res.data;
            checklistId = d.id;
            checklistCodigo = d.codigo;

            // Preencher aba Infor com dados salvos
            if (d.tipo) setTipo(d.tipo);
            if (d.momento && d.tipo === 'V') setMomento(d.momento);
            if (d.tanque !== null && d.tanque !== undefined && d.tanque !== '') {
                document.getElementById('infor-tanque').value = String(d.tanque);
            }
            if (d.odometro !== null && d.odometro !== undefined && d.odometro !== 0) {
                document.getElementById('infor-odometro').value = parseInt(d.odometro).toLocaleString('pt-BR');
            }
            if (d.obs) {
                document.getElementById('infor-obs').value = d.obs;
            }

            // Aguardar chosen-selects inicializarem e selecionar modelo
            await carregarModelos();
            if (d.id_modelo) {
                document.getElementById('infor-modelo').value = String(d.id_modelo);
                const modeloSelect = document.getElementById('infor-modelo');
                if (modeloSelect.chosenSelect) modeloSelect.chosenSelect.refresh();
            }

            // Preencher vinculo (Locacao/Contrato) no chosen-select
            if (d.tipo === 'V' && (d.id_locacao || d.id_contrato)) {
                const vinculoId = d.id_locacao ? 'L-' + d.id_locacao : 'C-' + d.id_contrato;
                const vinculoText = d.id_locacao
                    ? '[Locação] ' + (d.locacao_codigo || '') + ' - ' + (d.locacao_cliente || '')
                    : '[Contrato] ' + (d.contrato_codigo || '');
                const vinculoSel = document.getElementById('infor-vinculo');
                const opt = document.createElement('option');
                opt.value = vinculoId;
                opt.textContent = vinculoText;
                opt.selected = true;
                vinculoSel.appendChild(opt);
                if (vinculoSel.chosenSelect) vinculoSel.chosenSelect.refresh();

                // Configurar vinculoAtual para carregar veiculos e pre-selecionar
                const match = vinculoId.match(/^(L|C)-(\d+)$/);
                if (match) {
                    vinculoAtual = { tipo: match[1], id: parseInt(match[2]) };
                    await carregarVeiculosVinculo();
                    if (d.id_veiculo) {
                        document.getElementById('infor-veiculo-vinculado').value = String(d.id_veiculo);
                    }
                }
            }

            // Preencher veiculo avulso
            if (d.tipo === 'A' && d.id_veiculo && d.veiculo) {
                const veicSel = document.getElementById('infor-veiculo');
                const opt = document.createElement('option');
                opt.value = d.id_veiculo;
                opt.textContent = d.veiculo;
                opt.selected = true;
                veicSel.appendChild(opt);
                if (veicSel.chosenSelect) veicSel.chosenSelect.refresh();
            }

            // Carregar modelo para questoes/vistoria
            if (d.id_modelo) {
                const modeloRes = await API.get('/api/checklist-modelos/' + d.id_modelo);
                if (modeloRes.success || modeloRes.data) {
                    modeloData = modeloRes.data || modeloRes;
                }
            }

            // Determinar estado de preenchimento
            const questoesArr = d.questoes && d.questoes.length > 0 ? d.questoes : [];
            const todasQuestoesRespondidas = questoesArr.length > 0 && questoesArr.every(q => q.opt);
            const temAlgumaQuestao = questoesArr.some(q => q.opt);
            const temVistoria = d.vistoria && d.vistoria.length > 0 && d.vistoria.some(v => v.img);

            // Montar questoes e vistorias
            if (temAlgumaQuestao) {
                questoesState = questoesArr;
                montarQuestoesRetomadas();
            } else if (modeloData) {
                montarQuestoes();
            }

            if (temVistoria) {
                vistoriaState = d.vistoria.map(v => ({
                    ...v,
                    img_original_url: v.img_original_url || v.img_url,
                    editorDrawings: [],
                    editorMarkers: []
                }));
                renderVistorias();
            } else if (modeloData) {
                montarVistorias();
            }

            hideLoading();

            // Navegar para aba correta baseado no que está COMPLETO
            tabsCompleted.infor = true;

            if (todasQuestoesRespondidas && temVistoria) {
                tabsCompleted.questoes = true;
                tabsCompleted.vistorias = true;
                switchTab('assinatura');
            } else if (todasQuestoesRespondidas) {
                tabsCompleted.questoes = true;
                switchTab('vistorias');
            } else {
                // Questoes incompletas ou vazias → ir para questoes
                switchTab('questoes');
            }
        } catch (e) {
            hideLoading();
            console.error(e);
        }
    }

    function montarQuestoesRetomadas() {
        const container = document.getElementById('questoes-container');
        let html = '';
        questoesState.forEach((q, idx) => {
            const selected = q.opt ? ' selected-' + q.opt : '';
            html += '<div class="questao-card" data-idx="' + idx + '">' +
                '<div class="title">' + escapeHtml(q.content || q.pergunta || 'Item ' + (idx + 1)) + '</div>' +
                '<div class="questao-options">' +
                    optBtnWithState(idx, '1', 'fa-check', '<?= addslashes(t('modules.checklists.answers.matches')) ?>', q.opt) +
                    optBtnWithState(idx, '2', 'fa-xmark', '<?= addslashes(t('modules.checklists.answers.not_matches')) ?>', q.opt) +
                    optBtnWithState(idx, '3', 'fa-triangle-exclamation', '<?= addslashes(t('modules.checklists.answers.damaged')) ?>', q.opt) +
                    optBtnWithState(idx, '4', 'fa-regular fa-circle', '<?= addslashes(t('modules.checklists.answers.na')) ?>', q.opt) +
                '</div>' +
            '</div>';
        });
        container.innerHTML = html;
    }

    function optBtnWithState(idx, val, icon, label, currentOpt) {
        const cls = currentOpt === val ? ' selected-' + val : '';
        const iconCls = icon.includes('fa-regular') || icon.includes('far ') ? icon : 'fas ' + icon;
        return '<div class="questao-opt' + cls + '" data-idx="' + idx + '" data-val="' + val + '" onclick="selectOpt(this)">' +
            '<i class="' + iconCls + '"></i>' + label +
        '</div>';
    }

    if (retomarId) {
        retomarChecklist(retomarId);
    }

})();
</script>
</body>
</html>
