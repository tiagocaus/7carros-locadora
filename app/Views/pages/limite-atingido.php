@extends('layouts.iframe')

@section('title', 'Limite Atingido')

@section('content')
<div class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <div class="w-20 h-20 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-lock text-amber-500 text-3xl"></i>
            </div>

            <h1 class="text-2xl font-bold text-slate-800 mb-3">
                Limite do plano atingido
            </h1>

            <p class="text-slate-600 mb-6">
                Seu plano <span class="font-semibold text-slate-800"><?= htmlspecialchars($plano ?? 'Atual') ?></span>
                permite apenas <span class="font-semibold text-slate-800"><?= (int)($limite ?? 0) ?></span>
                <?= htmlspecialchars($label ?? 'registros') ?>.
            </p>

            <div class="bg-slate-50 rounded-lg p-4 mb-6">
                <p class="text-sm text-slate-600">
                    Para adicionar mais <?= htmlspecialchars($label ?? 'registros') ?>,
                    faça upgrade do seu plano.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <button id="btnVoltar" class="btn-secondary py-2.5 px-6 rounded-lg text-sm font-medium flex items-center justify-center">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </button>
                <a href="#" id="btnUpgrade" target="_blank" class="btn-green py-2.5 px-6 rounded-lg text-sm font-medium flex items-center justify-center shadow hover:shadow-md transition-shadow">
                    <i class="fab fa-whatsapp mr-2"></i>Fazer Upgrade
                </a>
            </div>
        </div>

        <p class="text-xs text-slate-400 mt-6">
            Precisa de ajuda? Entre em contato com nosso suporte.
        </p>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        // Botao voltar - volta na historia do navegador
        document.getElementById('btnVoltar')?.addEventListener('click', function() {
            window.history.back();
        });

        // Botao upgrade - abre WhatsApp em nova aba
        const btnUpgrade = document.getElementById('btnUpgrade');
        if (btnUpgrade) {
            // Detectar se e mobile
            const isMobile = /Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const mensagem = encodeURIComponent('Solicitar mudança de plano.');
            const numero = '5527998927997';

            // Definir link correto baseado no dispositivo
            if (isMobile) {
                btnUpgrade.href = `whatsapp://send?phone=${numero}&text=${mensagem}`;
            } else {
                btnUpgrade.href = `https://wa.me/${numero}?text=${mensagem}`;
            }
        }
    })();
</script>
@endsection
