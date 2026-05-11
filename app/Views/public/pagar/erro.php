@extends('layouts.public')

@section('title', 'Erro - Pagamento')

@section('content')
<div class="card p-8">
    <div class="text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-6">
            <i class="fas fa-exclamation-triangle text-4xl text-red-600"></i>
        </div>

        <h1 class="text-2xl font-bold text-slate-800 mb-3"><?= htmlspecialchars($titulo ?? 'Erro') ?></h1>

        <p class="text-slate-600 mb-6"><?= htmlspecialchars($mensagem ?? 'Ocorreu um erro ao processar sua solicitacao.') ?></p>

        <div class="space-y-3">
            <a href="/" class="block w-full py-3 px-4 bg-slate-100 text-slate-700 rounded-lg font-medium hover:bg-slate-200 transition-colors">
                <i class="fas fa-home mr-2"></i>Voltar ao inicio
            </a>
        </div>
    </div>
</div>
@endsection
