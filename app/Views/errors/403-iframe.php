@extends('layouts.iframe')

@section('title', 'Acesso Negado')

@section('content')
<div class="flex items-center justify-center min-h-[50vh]">
    <div class="text-center p-8">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-lock text-red-500 text-4xl"></i>
        </div>
        <h2 class="text-xl font-bold text-slate-700 mb-2">Acesso Negado</h2>
        <p class="text-slate-500 text-sm mb-4">
            {{ $message ?? 'Você não tem permissão para acessar este recurso.' }}
        </p>
        <button onclick="history.back()" class="btn-secondary px-4 py-2 rounded-md text-sm">
            <i class="fas fa-arrow-left mr-2"></i>Voltar
        </button>
    </div>
</div>
@endsection
