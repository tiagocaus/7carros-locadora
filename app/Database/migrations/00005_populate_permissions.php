<?php

use App\Database\Migration;

/**
 * Migration: Popular tabela permissions
 *
 * Insere todas as permissões do sistema convertendo do formato legado (camelCase)
 * para o novo formato (dot notation).
 *
 * Formato: {modulo}.{acao}
 * Exemplo: clientes.visualizar, clientes.criar, clientes.editar, clientes.excluir
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            $this->db()->table('permissions')->insert($permission);
        }
    }

    public function down(): void
    {
        $this->db()->table('permissions')->whereRaw('1=1')->delete();
    }

    /**
     * Retorna array com todas as permissões do sistema
     */
    private function getPermissions(): array
    {
        return [
            // Dashboard
            ['key' => 'dashboard.visualizar', 'name' => 'Visualizar Dashboard', 'description' => 'Acesso à página inicial do sistema', 'module' => 'dashboard'],

            // Locações
            ['key' => 'locacoes.visualizar', 'name' => 'Visualizar Locações', 'description' => 'Listar e visualizar locações', 'module' => 'locacoes'],
            ['key' => 'locacoes.criar', 'name' => 'Criar Locações', 'description' => 'Adicionar novas locações', 'module' => 'locacoes'],
            ['key' => 'locacoes.editar', 'name' => 'Editar Locações', 'description' => 'Modificar locações existentes', 'module' => 'locacoes'],
            ['key' => 'locacoes.excluir', 'name' => 'Excluir Locações', 'description' => 'Remover locações do sistema', 'module' => 'locacoes'],

            // Contratos
            ['key' => 'contratos.visualizar', 'name' => 'Visualizar Contratos', 'description' => 'Listar e visualizar contratos', 'module' => 'contratos'],
            ['key' => 'contratos.criar', 'name' => 'Criar Contratos', 'description' => 'Adicionar novos contratos', 'module' => 'contratos'],
            ['key' => 'contratos.editar', 'name' => 'Editar Contratos', 'description' => 'Modificar contratos existentes', 'module' => 'contratos'],
            ['key' => 'contratos.excluir', 'name' => 'Excluir Contratos', 'description' => 'Remover contratos do sistema', 'module' => 'contratos'],

            // Veículos
            ['key' => 'veiculos.visualizar', 'name' => 'Visualizar Veículos', 'description' => 'Listar e visualizar veículos', 'module' => 'veiculos'],
            ['key' => 'veiculos.criar', 'name' => 'Criar Veículos', 'description' => 'Adicionar novos veículos', 'module' => 'veiculos'],
            ['key' => 'veiculos.editar', 'name' => 'Editar Veículos', 'description' => 'Modificar veículos existentes', 'module' => 'veiculos'],
            ['key' => 'veiculos.excluir', 'name' => 'Excluir Veículos', 'description' => 'Remover veículos do sistema', 'module' => 'veiculos'],

            // Clientes
            ['key' => 'clientes.visualizar', 'name' => 'Visualizar Clientes', 'description' => 'Listar e visualizar clientes', 'module' => 'clientes'],
            ['key' => 'clientes.criar', 'name' => 'Criar Clientes', 'description' => 'Adicionar novos clientes', 'module' => 'clientes'],
            ['key' => 'clientes.editar', 'name' => 'Editar Clientes', 'description' => 'Modificar clientes existentes', 'module' => 'clientes'],
            ['key' => 'clientes.excluir', 'name' => 'Excluir Clientes', 'description' => 'Remover clientes do sistema', 'module' => 'clientes'],

            // Funcionários
            ['key' => 'funcionarios.visualizar', 'name' => 'Visualizar Funcionários', 'description' => 'Listar e visualizar funcionários', 'module' => 'funcionarios'],
            ['key' => 'funcionarios.criar', 'name' => 'Criar Funcionários', 'description' => 'Adicionar novos funcionários', 'module' => 'funcionarios'],
            ['key' => 'funcionarios.editar', 'name' => 'Editar Funcionários', 'description' => 'Modificar funcionários existentes', 'module' => 'funcionarios'],
            ['key' => 'funcionarios.excluir', 'name' => 'Excluir Funcionários', 'description' => 'Remover funcionários do sistema', 'module' => 'funcionarios'],

            // Empresas
            ['key' => 'empresas.visualizar', 'name' => 'Visualizar Empresas', 'description' => 'Listar e visualizar empresas', 'module' => 'empresas'],
            ['key' => 'empresas.criar', 'name' => 'Criar Empresas', 'description' => 'Adicionar novas empresas', 'module' => 'empresas'],
            ['key' => 'empresas.editar', 'name' => 'Editar Empresas', 'description' => 'Modificar empresas existentes', 'module' => 'empresas'],
            ['key' => 'empresas.excluir', 'name' => 'Excluir Empresas', 'description' => 'Remover empresas do sistema', 'module' => 'empresas'],

            // Fornecedores
            ['key' => 'fornecedores.visualizar', 'name' => 'Visualizar Fornecedores', 'description' => 'Listar e visualizar fornecedores', 'module' => 'fornecedores'],
            ['key' => 'fornecedores.criar', 'name' => 'Criar Fornecedores', 'description' => 'Adicionar novos fornecedores', 'module' => 'fornecedores'],
            ['key' => 'fornecedores.editar', 'name' => 'Editar Fornecedores', 'description' => 'Modificar fornecedores existentes', 'module' => 'fornecedores'],
            ['key' => 'fornecedores.excluir', 'name' => 'Excluir Fornecedores', 'description' => 'Remover fornecedores do sistema', 'module' => 'fornecedores'],

            // Acessórios
            ['key' => 'acessorios.criar', 'name' => 'Criar Acessórios', 'description' => 'Adicionar novos acessórios', 'module' => 'acessorios'],
            ['key' => 'acessorios.editar', 'name' => 'Editar Acessórios', 'description' => 'Modificar acessórios existentes', 'module' => 'acessorios'],
            ['key' => 'acessorios.excluir', 'name' => 'Excluir Acessórios', 'description' => 'Remover acessórios do sistema', 'module' => 'acessorios'],

            // Grupos
            ['key' => 'grupos.visualizar', 'name' => 'Visualizar Grupos', 'description' => 'Listar e visualizar grupos de veículos', 'module' => 'grupos'],
            ['key' => 'grupos.criar', 'name' => 'Criar Grupos', 'description' => 'Adicionar novos grupos', 'module' => 'grupos'],
            ['key' => 'grupos.editar', 'name' => 'Editar Grupos', 'description' => 'Modificar grupos existentes', 'module' => 'grupos'],
            ['key' => 'grupos.excluir', 'name' => 'Excluir Grupos', 'description' => 'Remover grupos do sistema', 'module' => 'grupos'],

            // Taxas e Serviços
            ['key' => 'taxas_servicos.visualizar', 'name' => 'Visualizar Taxas e Serviços', 'description' => 'Listar e visualizar taxas e serviços', 'module' => 'taxas_servicos'],
            ['key' => 'taxas_servicos.criar', 'name' => 'Criar Taxas e Serviços', 'description' => 'Adicionar novas taxas e serviços', 'module' => 'taxas_servicos'],
            ['key' => 'taxas_servicos.editar', 'name' => 'Editar Taxas e Serviços', 'description' => 'Modificar taxas e serviços existentes', 'module' => 'taxas_servicos'],
            ['key' => 'taxas_servicos.excluir', 'name' => 'Excluir Taxas e Serviços', 'description' => 'Remover taxas e serviços do sistema', 'module' => 'taxas_servicos'],

            // Oficinas
            ['key' => 'oficinas.visualizar', 'name' => 'Visualizar Oficinas', 'description' => 'Listar e visualizar oficinas', 'module' => 'oficinas'],
            ['key' => 'oficinas.criar', 'name' => 'Criar Oficinas', 'description' => 'Adicionar novas oficinas', 'module' => 'oficinas'],
            ['key' => 'oficinas.editar', 'name' => 'Editar Oficinas', 'description' => 'Modificar oficinas existentes', 'module' => 'oficinas'],
            ['key' => 'oficinas.excluir', 'name' => 'Excluir Oficinas', 'description' => 'Remover oficinas do sistema', 'module' => 'oficinas'],

            // Localizar
            ['key' => 'localizar.visualizar', 'name' => 'Localizar Veículos', 'description' => 'Acessar funcionalidade de localização de veículos', 'module' => 'localizar'],

            // Agenda
            ['key' => 'agenda.visualizar', 'name' => 'Visualizar Agenda', 'description' => 'Listar e visualizar agenda', 'module' => 'agenda'],
            ['key' => 'agenda.criar', 'name' => 'Criar Agendamentos', 'description' => 'Adicionar novos agendamentos', 'module' => 'agenda'],
            ['key' => 'agenda.editar', 'name' => 'Editar Agendamentos', 'description' => 'Modificar agendamentos existentes', 'module' => 'agenda'],
            ['key' => 'agenda.excluir', 'name' => 'Excluir Agendamentos', 'description' => 'Remover agendamentos do sistema', 'module' => 'agenda'],

            // Website
            ['key' => 'website.editar', 'name' => 'Editar Website', 'description' => 'Modificar configurações do website', 'module' => 'website'],

            // Logs
            ['key' => 'logs.visualizar', 'name' => 'Visualizar Logs', 'description' => 'Acessar logs do sistema', 'module' => 'logs'],

            // App Vistoria
            ['key' => 'app_vistoria.visualizar', 'name' => 'Visualizar App Vistoria', 'description' => 'Acessar funcionalidades do app de vistoria', 'module' => 'app_vistoria'],

            // Financeiro
            ['key' => 'financeiro.visualizar', 'name' => 'Visualizar Financeiro', 'description' => 'Listar e visualizar informações financeiras', 'module' => 'financeiro'],
            ['key' => 'financeiro.criar', 'name' => 'Criar Lançamentos', 'description' => 'Adicionar novos lançamentos financeiros', 'module' => 'financeiro'],
            ['key' => 'financeiro.editar', 'name' => 'Editar Lançamentos', 'description' => 'Modificar lançamentos financeiros', 'module' => 'financeiro'],
            ['key' => 'financeiro.excluir', 'name' => 'Excluir Lançamentos', 'description' => 'Remover lançamentos financeiros', 'module' => 'financeiro'],

            // Multas
            ['key' => 'multas.visualizar', 'name' => 'Visualizar Multas', 'description' => 'Listar e visualizar multas', 'module' => 'multas'],
            ['key' => 'multas.criar', 'name' => 'Criar Multas', 'description' => 'Adicionar novas multas', 'module' => 'multas'],
            ['key' => 'multas.editar', 'name' => 'Editar Multas', 'description' => 'Modificar multas existentes', 'module' => 'multas'],
            ['key' => 'multas.excluir', 'name' => 'Excluir Multas', 'description' => 'Remover multas do sistema', 'module' => 'multas'],

            // Promoções
            ['key' => 'promocoes.visualizar', 'name' => 'Visualizar Promoções', 'description' => 'Listar e visualizar promoções', 'module' => 'promocoes'],
            ['key' => 'promocoes.criar', 'name' => 'Criar Promoções', 'description' => 'Adicionar novas promoções', 'module' => 'promocoes'],
            ['key' => 'promocoes.editar', 'name' => 'Editar Promoções', 'description' => 'Modificar promoções existentes', 'module' => 'promocoes'],
            ['key' => 'promocoes.excluir', 'name' => 'Excluir Promoções', 'description' => 'Remover promoções do sistema', 'module' => 'promocoes'],

            // Relatórios
            ['key' => 'relatorios.visualizar', 'name' => 'Visualizar Relatórios', 'description' => 'Acessar e gerar relatórios', 'module' => 'relatorios'],

            // Manutenções
            ['key' => 'manutencoes.visualizar', 'name' => 'Visualizar Manutenções', 'description' => 'Listar e visualizar manutenções', 'module' => 'manutencoes'],
            ['key' => 'manutencoes.criar', 'name' => 'Criar Manutenções', 'description' => 'Adicionar novas manutenções', 'module' => 'manutencoes'],
            ['key' => 'manutencoes.editar', 'name' => 'Editar Manutenções', 'description' => 'Modificar manutenções existentes', 'module' => 'manutencoes'],
            ['key' => 'manutencoes.excluir', 'name' => 'Excluir Manutenções', 'description' => 'Remover manutenções do sistema', 'module' => 'manutencoes'],

            // Planos de Manutenção
            ['key' => 'manutencoes_planos.visualizar', 'name' => 'Visualizar Planos de Manutenção', 'description' => 'Listar e visualizar planos de manutenção', 'module' => 'manutencoes_planos'],
            ['key' => 'manutencoes_planos.criar', 'name' => 'Criar Planos de Manutenção', 'description' => 'Adicionar novos planos de manutenção', 'module' => 'manutencoes_planos'],
            ['key' => 'manutencoes_planos.editar', 'name' => 'Editar Planos de Manutenção', 'description' => 'Modificar planos de manutenção', 'module' => 'manutencoes_planos'],
            ['key' => 'manutencoes_planos.excluir', 'name' => 'Excluir Planos de Manutenção', 'description' => 'Remover planos de manutenção', 'module' => 'manutencoes_planos'],

            // Formas de Pagamento
            ['key' => 'formas.visualizar', 'name' => 'Visualizar Formas de Pagamento', 'description' => 'Listar e visualizar formas de pagamento', 'module' => 'formas'],
            ['key' => 'formas.criar', 'name' => 'Criar Formas de Pagamento', 'description' => 'Adicionar novas formas de pagamento', 'module' => 'formas'],
            ['key' => 'formas.editar', 'name' => 'Editar Formas de Pagamento', 'description' => 'Modificar formas de pagamento', 'module' => 'formas'],
            ['key' => 'formas.excluir', 'name' => 'Excluir Formas de Pagamento', 'description' => 'Remover formas de pagamento', 'module' => 'formas'],

            // Checklists
            ['key' => 'checklists.visualizar', 'name' => 'Visualizar Checklists', 'description' => 'Listar e visualizar checklists', 'module' => 'checklists'],
            ['key' => 'checklists.excluir', 'name' => 'Excluir Checklists', 'description' => 'Remover checklists do sistema', 'module' => 'checklists'],

            // Modelos de Checklist
            ['key' => 'checklists_modelos.visualizar', 'name' => 'Visualizar Modelos de Checklist', 'description' => 'Listar e visualizar modelos de checklist', 'module' => 'checklists_modelos'],
            ['key' => 'checklists_modelos.criar', 'name' => 'Criar Modelos de Checklist', 'description' => 'Adicionar novos modelos de checklist', 'module' => 'checklists_modelos'],
            ['key' => 'checklists_modelos.editar', 'name' => 'Editar Modelos de Checklist', 'description' => 'Modificar modelos de checklist', 'module' => 'checklists_modelos'],
            ['key' => 'checklists_modelos.excluir', 'name' => 'Excluir Modelos de Checklist', 'description' => 'Remover modelos de checklist', 'module' => 'checklists_modelos'],

            // Contas Bancárias
            ['key' => 'contas.visualizar', 'name' => 'Visualizar Contas', 'description' => 'Listar e visualizar contas bancárias', 'module' => 'contas'],
            ['key' => 'contas.criar', 'name' => 'Criar Contas', 'description' => 'Adicionar novas contas bancárias', 'module' => 'contas'],
            ['key' => 'contas.editar', 'name' => 'Editar Contas', 'description' => 'Modificar contas bancárias', 'module' => 'contas'],
            ['key' => 'contas.excluir', 'name' => 'Excluir Contas', 'description' => 'Remover contas bancárias', 'module' => 'contas'],

            // Cartão
            ['key' => 'cartao.visualizar', 'name' => 'Visualizar Cartões', 'description' => 'Visualizar informações de cartões', 'module' => 'cartao'],

            // Documentos
            ['key' => 'documentos.visualizar', 'name' => 'Visualizar Documentos', 'description' => 'Listar e visualizar documentos', 'module' => 'documentos'],
            ['key' => 'documentos.criar', 'name' => 'Criar Documentos', 'description' => 'Adicionar novos documentos', 'module' => 'documentos'],
            ['key' => 'documentos.editar', 'name' => 'Editar Documentos', 'description' => 'Modificar documentos existentes', 'module' => 'documentos'],
            ['key' => 'documentos.excluir', 'name' => 'Excluir Documentos', 'description' => 'Remover documentos do sistema', 'module' => 'documentos'],

            // Estoque
            ['key' => 'estoque.visualizar', 'name' => 'Visualizar Estoque', 'description' => 'Listar e visualizar estoque', 'module' => 'estoque'],
            ['key' => 'estoque.criar', 'name' => 'Criar Itens de Estoque', 'description' => 'Adicionar novos itens ao estoque', 'module' => 'estoque'],
            ['key' => 'estoque.editar', 'name' => 'Editar Estoque', 'description' => 'Modificar itens do estoque', 'module' => 'estoque'],
            ['key' => 'estoque.excluir', 'name' => 'Excluir Itens de Estoque', 'description' => 'Remover itens do estoque', 'module' => 'estoque'],

            // Acesso
            ['key' => 'acesso.criar', 'name' => 'Criar Acessos', 'description' => 'Gerenciar acessos ao sistema', 'module' => 'acesso'],

            // Notificações
            ['key' => 'notificacoes.visualizar', 'name' => 'Visualizar Notificações', 'description' => 'Listar e visualizar notificações', 'module' => 'notificacoes'],
            ['key' => 'notificacoes.excluir', 'name' => 'Excluir Notificações', 'description' => 'Remover notificações', 'module' => 'notificacoes'],

            // WhatsApp
            ['key' => 'whatsapp.editar', 'name' => 'Configurar WhatsApp', 'description' => 'Modificar configurações do WhatsApp', 'module' => 'whatsapp'],

            // Configurações
            ['key' => 'configuracoes.editar', 'name' => 'Editar Configurações', 'description' => 'Modificar configurações do sistema', 'module' => 'configuracoes'],

            // Promissórias
            ['key' => 'promissorias.visualizar', 'name' => 'Visualizar Promissórias', 'description' => 'Listar e visualizar promissórias', 'module' => 'promissorias'],
            ['key' => 'promissorias.criar', 'name' => 'Criar Promissórias', 'description' => 'Adicionar novas promissórias', 'module' => 'promissorias'],
            ['key' => 'promissorias.editar', 'name' => 'Editar Promissórias', 'description' => 'Modificar promissórias existentes', 'module' => 'promissorias'],
            ['key' => 'promissorias.excluir', 'name' => 'Excluir Promissórias', 'description' => 'Remover promissórias do sistema', 'module' => 'promissorias'],
        ];
    }
};
