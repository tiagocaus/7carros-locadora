@extends('layouts.auth')

@section('title', 'Acesso Negado - 7Carros Locadora')

@section('content')
<div class="min-h-screen flex items-center justify-center" style="background-color: #f5f7fa;">
    <div class="text-center p-8">
        <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-lock text-red-500 text-5xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-slate-700 mb-2">Acesso Negado</h1>
        <p class="text-slate-500 mb-6 max-w-md mx-auto">
            {{ $message ?? 'Você não tem permissão para acessar este recurso.' }}
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="javascript:history.back()" class="btn-secondary px-6 py-2 rounded-md inline-flex items-center justify-center">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
            <a href="/dashboard" class="btn-blue px-6 py-2 rounded-md inline-flex items-center justify-center">
                <i class="fas fa-home mr-2"></i>Ir para Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
