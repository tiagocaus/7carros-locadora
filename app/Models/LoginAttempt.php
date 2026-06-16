<?php

namespace App\Models;

/**
 * Model para gerenciamento de tentativas de login (rate limiting)
 *
 * Tabela: security_login_attempts
 * Multi-tenancy: NÃO tem coluna chave (tabela cross-tenant de segurança)
 */
class LoginAttempt extends Model
{
    /**
     * Busca tentativa de login bloqueada
     *
     * @param string $usuario Nome de usuário
     * @param string $ip Endereço IP
     * @return array|null Dados do bloqueio ou null se não bloqueado
     */
    public function buscarBloqueio(string $usuario, string $ip): ?array
    {
        return $this->qb
            ->table('security_login_attempts')
            ->withoutChave()
            ->where('usuario', '=', $usuario)
            ->where('ip_address', '=', $ip)
            ->whereNotNull('bloqueado_ate')
            ->first();
    }

    /**
     * Busca tentativa de login existente
     *
     * @param string $usuario Nome de usuário
     * @param string $ip Endereço IP
     * @return array|null Dados da tentativa ou null
     */
    public function buscar(string $usuario, string $ip): ?array
    {
        return $this->qb
            ->table('security_login_attempts')
            ->withoutChave()
            ->where('usuario', '=', $usuario)
            ->where('ip_address', '=', $ip)
            ->first();
    }

    /**
     * Registra uma nova tentativa de login
     *
     * @param string $usuario Nome de usuário
     * @param string $ip Endereço IP
     * @return int ID inserido
     */
    public function registrar(string $usuario, string $ip): int
    {
        return $this->qb
            ->table('security_login_attempts')
            ->withoutChave()
            ->insert([
                'usuario' => $usuario,
                'ip_address' => $ip,
                'tentativas' => 1
            ]);
    }

    /**
     * Incrementa contador de tentativas
     *
     * @param string $usuario Nome de usuário
     * @param string $ip Endereço IP
     * @param int $tentativas Novo número de tentativas
     * @param string|null $bloqueadoAte Data/hora de bloqueio (opcional)
     * @return int Linhas afetadas
     */
    public function incrementar(string $usuario, string $ip, int $tentativas, ?string $bloqueadoAte = null): int
    {
        return $this->qb
            ->table('security_login_attempts')
            ->withoutChave()
            ->where('usuario', '=', $usuario)
            ->where('ip_address', '=', $ip)
            ->update([
                'tentativas' => $tentativas,
                'bloqueado_ate' => $bloqueadoAte,
                'created_at' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Limpa tentativas de login (após sucesso)
     *
     * @param string $usuario Nome de usuário
     * @param string $ip Endereço IP
     * @return int Linhas afetadas
     */
    public function limpar(string $usuario, string $ip): int
    {
        return $this->qb
            ->table('security_login_attempts')
            ->withoutChave()
            ->where('usuario', '=', $usuario)
            ->where('ip_address', '=', $ip)
            ->delete();
    }
}
