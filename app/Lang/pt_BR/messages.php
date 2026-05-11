<?php

/**
 * Mensagens do sistema - Português (Brasil)
 *
 * Contém mensagens de feedback, alertas, notificações e comunicações gerais.
 * Use variáveis com :nome para textos dinâmicos.
 */

return [
    // Mensagens de sucesso
    'success' => [
        'saved' => 'Registro salvo com sucesso!',
        'created' => 'Registro criado com sucesso!',
        'updated' => 'Registro atualizado com sucesso!',
        'deleted' => 'Registro excluído com sucesso!',
        'restored' => 'Registro restaurado com sucesso!',
        'archived' => 'Registro arquivado com sucesso!',
        'activated' => 'Registro ativado com sucesso!',
        'deactivated' => 'Registro desativado com sucesso!',
        'copied' => 'Copiado com sucesso!',
        'sent' => 'Enviado com sucesso!',
        'uploaded' => 'Arquivo enviado com sucesso!',
        'downloaded' => 'Download iniciado com sucesso!',
        'imported' => 'Importação realizada com sucesso!',
        'exported' => 'Exportação realizada com sucesso!',
        'processed' => 'Processamento concluído com sucesso!',
        'login' => 'Login realizado com sucesso!',
        'logout' => 'Logout realizado com sucesso!',
        'password_changed' => 'Senha alterada com sucesso!',
        'profile_updated' => 'Perfil atualizado com sucesso!',
        'settings_saved' => 'Configurações salvas com sucesso!',
        'email_sent' => 'E-mail enviado com sucesso!',
        'operation_completed' => 'Operação concluída com sucesso!',
    ],

    // Mensagens de erro
    'error' => [
        'generic' => 'Ocorreu um erro. Por favor, tente novamente.',
        'unexpected' => 'Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.',
        'save' => 'Erro ao salvar o registro. Por favor, tente novamente.',
        'create' => 'Erro ao criar o registro. Por favor, tente novamente.',
        'update' => 'Erro ao atualizar o registro. Por favor, tente novamente.',
        'delete' => 'Erro ao excluir o registro. Por favor, tente novamente.',
        'not_found' => 'Registro não encontrado.',
        'access_denied' => 'Acesso negado. Você não tem permissão para acessar este recurso.',
        'unauthorized' => 'Não autorizado. Por favor, faça login para continuar.',
        'forbidden' => 'Ação não permitida.',
        'session_expired' => 'Sua sessão expirou. Por favor, faça login novamente.',
        'invalid_credentials' => 'Credenciais inválidas. Por favor, verifique seu e-mail e senha.',
        'account_disabled' => 'Sua conta está desativada. Entre em contato com o suporte.',
        'account_locked' => 'Sua conta foi bloqueada. Entre em contato com o suporte.',
        'upload_failed' => 'Erro ao enviar o arquivo. Por favor, tente novamente.',
        'file_too_large' => 'O arquivo é muito grande. O tamanho máximo permitido é :size.',
        'invalid_file_type' => 'Tipo de arquivo não permitido. Tipos aceitos: :types.',
        'email_failed' => 'Erro ao enviar o e-mail. Por favor, tente novamente.',
        'connection_failed' => 'Erro de conexão. Por favor, verifique sua internet.',
        'timeout' => 'A operação excedeu o tempo limite. Por favor, tente novamente.',
        'server_error' => 'Erro no servidor. Por favor, tente novamente mais tarde.',
        'maintenance' => 'Sistema em manutenção. Por favor, tente novamente mais tarde.',
        'invalid_request' => 'Requisição inválida.',
        'validation_failed' => 'Erro de validação. Por favor, verifique os campos.',
        'duplicate_entry' => 'Este registro já existe.',
        'constraint_violation' => 'Não é possível excluir este registro pois existem dependências.',
        'import_failed' => 'Erro ao importar os dados. Por favor, verifique o arquivo.',
        'export_failed' => 'Erro ao exportar os dados. Por favor, tente novamente.',
    ],

    // Mensagens de aviso
    'warning' => [
        'unsaved_changes' => 'Você tem alterações não salvas.',
        'action_irreversible' => 'Esta ação não pode ser desfeita.',
        'delete_confirm' => 'O registro será excluído permanentemente.',
        'data_loss' => 'Os dados serão perdidos.',
        'session_expiring' => 'Sua sessão irá expirar em breve.',
        'browser_outdated' => 'Seu navegador está desatualizado. Recomendamos atualizá-lo.',
        'incomplete_data' => 'Alguns dados estão incompletos.',
        'pending_approval' => 'Aguardando aprovação.',
        'overdue' => 'Em atraso.',
        'expired' => 'Expirado.',
        'low_stock' => 'Estoque baixo.',
        'limit_reached' => 'Limite atingido.',
    ],

    // Mensagens informativas
    'info' => [
        'no_changes' => 'Nenhuma alteração foi feita.',
        'already_exists' => 'Este registro já existe.',
        'already_processed' => 'Esta operação já foi processada.',
        'pending' => 'Processamento pendente.',
        'in_progress' => 'Em andamento.',
        'scheduled' => 'Agendado.',
        'waiting' => 'Aguardando.',
        'review_required' => 'Revisão necessária.',
        'optional_field' => 'Este campo é opcional.',
        'required_field' => 'Este campo é obrigatório.',
        'help_available' => 'Ajuda disponível.',
        'tip' => 'Dica',
        'note' => 'Nota',
    ],

    // Mensagens de boas-vindas e saudações
    'greeting' => [
        'welcome' => 'Bem-vindo(a)!',
        'welcome_back' => 'Bem-vindo(a) de volta!',
        'welcome_user' => 'Bem-vindo(a), :nome!',
        'good_morning' => 'Bom dia!',
        'good_afternoon' => 'Boa tarde!',
        'good_evening' => 'Boa noite!',
        'hello' => 'Olá!',
        'hi' => 'Oi!',
        'goodbye' => 'Até logo!',
        'see_you' => 'Até mais!',
        'thank_you' => 'Obrigado!',
        'thanks' => 'Valeu!',
    ],

    // Mensagens de autenticação
    'auth' => [
        'login_required' => 'Por favor, faça login para continuar.',
        'login_success' => 'Login realizado com sucesso!',
        'logout_success' => 'Você saiu do sistema com sucesso.',
        'password_reset_sent' => 'E-mail de recuperação de senha enviado.',
        'password_reset_success' => 'Senha redefinida com sucesso.',
        'password_mismatch' => 'As senhas não conferem.',
        'current_password_wrong' => 'A senha atual está incorreta.',
        'email_not_found' => 'E-mail não encontrado no sistema.',
        'account_created' => 'Conta criada com sucesso!',
        'verification_sent' => 'E-mail de verificação enviado.',
        'email_verified' => 'E-mail verificado com sucesso!',
        'two_factor_required' => 'Autenticação de dois fatores necessária.',
        'two_factor_invalid' => 'Código de verificação inválido.',
    ],

    // Mensagens de formulário
    'form' => [
        'fill_required' => 'Por favor, preencha todos os campos obrigatórios.',
        'check_errors' => 'Por favor, corrija os erros no formulário.',
        'changes_saved' => 'Suas alterações foram salvas.',
        'discard_changes' => 'Descartar alterações?',
        'draft_saved' => 'Rascunho salvo.',
        'submit_confirm' => 'Confirma o envio do formulário?',
    ],

    // Mensagens de paginação
    'pagination' => [
        'showing' => 'Exibindo :from a :to de :total resultados',
        'no_results' => 'Nenhum resultado encontrado.',
        'page_of' => 'Página :current de :total',
    ],

    // Mensagens de busca/filtro
    'search' => [
        'no_results' => 'Nenhum resultado encontrado para ":query".',
        'results_found' => ':count resultado(s) encontrado(s) para ":query".',
        'try_different' => 'Tente uma busca diferente.',
        'clear_filters' => 'Limpar filtros para ver todos os resultados.',
    ],

    // Mensagens de tempo relativo
    'relative_time' => [
        'just_now' => 'Agora mesmo',
        'seconds_ago' => 'há :count segundos',
        'minute_ago' => 'há 1 minuto',
        'minutes_ago' => 'há :count minutos',
        'hour_ago' => 'há 1 hora',
        'hours_ago' => 'há :count horas',
        'day_ago' => 'há 1 dia',
        'days_ago' => 'há :count dias',
        'week_ago' => 'há 1 semana',
        'weeks_ago' => 'há :count semanas',
        'month_ago' => 'há 1 mês',
        'months_ago' => 'há :count meses',
        'year_ago' => 'há 1 ano',
        'years_ago' => 'há :count anos',
    ],

    // Mensagens de confirmação/status de operação
    'status' => [
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'pending' => 'Pendente',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
        'cancelled' => 'Cancelado',
        'completed' => 'Concluído',
        'in_progress' => 'Em andamento',
        'on_hold' => 'Em espera',
        'draft' => 'Rascunho',
        'published' => 'Publicado',
        'archived' => 'Arquivado',
        'deleted' => 'Excluído',
        'scheduled' => 'Agendado',
        'sent' => 'Enviado',
        'delivered' => 'Entregue',
        'failed' => 'Falhou',
        'processing' => 'Processando',
        'paid' => 'Pago',
        'unpaid' => 'Não pago',
        'overdue' => 'Em atraso',
        'refunded' => 'Reembolsado',
    ],
];
