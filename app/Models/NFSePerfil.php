<?php

namespace App\Models;

use App\Services\NFSe\NFSeContextoFiscal;

/** Perfis operacionais sem credenciais; o certificado pertence à filial. */
class NFSePerfil extends Model
{
    public function disponivel(): bool
    {
        $result = $this->getMysqli()->query("SELECT COUNT(*) total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('nfse_perfis_emissao','nfse_sequencias')");
        if ((int) $result->fetch_assoc()['total'] !== 2) {
            return false;
        }
        $column = $this->getMysqli()->query("SHOW COLUMNS FROM nfse LIKE 'contexto_fiscal'");
        if ($column->num_rows !== 1) {
            return false;
        }
        $migration = $this->getMysqli()->query("SELECT 1 FROM migrations WHERE migration = '00439_create_nfse_perfis_emissao.php' LIMIT 1");
        return $migration->num_rows === 1;
    }

    public function listar(int $filial): array
    {
        if (!$this->disponivel()) {
            return [];
        }
        $rows = $this->qb->table('nfse_perfis_emissao')->where('id_matriz_filial', '=', $filial)->get();
        return array_map(function (array $row): array {
            $config = json_decode((string) $row['configuracao'], true) ?: [];
            return array_replace(NFSeContextoFiscal::snapshot($config), [
                'perfil_id' => (int) $row['id'],
                'id_matriz_filial' => (int) $row['id_matriz_filial'],
                'tipo_emissao' => $row['tipo_emissao'],
                'ambiente' => (int) $row['ambiente'],
                'serie' => $row['serie'],
                'numero_atual' => (int) $row['numero_atual'],
                'nacional_confirmado' => $row['confirmado'],
                '_exigir_ctrib_nacional' => !empty($config['exigir_ctrib_nacional']),
            ]);
        }, $rows);
    }

    public function buscar(int $filial, string $tipo, int $ambiente): ?array
    {
        foreach ($this->listar($filial) as $perfil) {
            if ($perfil['tipo_emissao'] === $tipo && $perfil['ambiente'] === $ambiente) {
                return $perfil;
            }
        }
        return null;
    }

    /** Consulta somente: recupera o maior número sem criar ou reservar sequências. */
    public function maiorNumeroConhecido(array $config, int $inicial = 0): int
    {
        $serie = trim((string) ($config['serie'] ?? ''));
        if ($serie === '') {
            return max(0, $inicial);
        }
        $filial = (int) $config['id_matriz_filial'];
        $tipo = (string) $config['tipo_emissao'];
        $ambiente = (int) $config['ambiente'];
        $sequencia = $this->qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $filial)
            ->where('tipo_emissao', '=', $tipo)->where('ambiente', '=', $ambiente)->where('serie', '=', $this->serieSequencia($config))->first();
        $q = $this->qb->table('nfse')->selectRaw('MAX(numero) AS maior')->where('id_matriz_filial', '=', $filial)
            ->where('tipo_emissao', '=', $tipo)->where('ambiente', '=', $ambiente);
        if ($tipo === 'nacional' && preg_match('/^[0-9]{1,5}$/D', $serie)) {
            $q->whereRaw('CAST(serie AS UNSIGNED) = ?', [(int) $serie]);
        } else {
            $q->where('serie', '=', $serie);
        }
        $nota = $q->first();
        return max(0, $inicial, (int) ($sequencia['numero_atual'] ?? 0), (int) ($nota['maior'] ?? 0));
    }

    /** Criação concorrente não sobrescreve uma configuração salva por outra requisição. */
    public function garantir(int $filial, array $config): array
    {
        if (!$this->disponivel() || !$this->qb->table('nfse_configuracoes')->where('id_matriz_filial', '=', $filial)->first()) {
            throw new \InvalidArgumentException('A configuração da emissão está indisponível. Contate o suporte.');
        }
        $chave = (string) $_SESSION['chave'];
        $tipo = (string) $config['tipo_emissao'];
        $ambiente = (int) $config['ambiente'];
        $serie = $config['serie'] ?? null;
        $numero = $this->maiorNumeroConhecido($config, (int) ($config['numero_atual'] ?? 0));
        $fiscal = NFSeContextoFiscal::snapshot($config);
        $fiscal['exigir_ctrib_nacional'] = !empty($config['_novo_nacional']);
        $json = json_encode($fiscal, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $stmt = $this->getMysqli()->prepare("INSERT INTO nfse_perfis_emissao (chave,id_matriz_filial,tipo_emissao,ambiente,serie,numero_atual,configuracao,confirmado) VALUES (?,?,?,?,?,?,?,'S') ON DUPLICATE KEY UPDATE id=id");
        $stmt->bind_param('sisisis', $chave, $filial, $tipo, $ambiente, $serie, $numero, $json);
        $stmt->execute();
        $stmt->close();
        return $this->buscar($filial, $tipo, $ambiente);
    }

    public function salvar(int $filial, array $campos, bool $confirmado): int
    {
        if (!$this->disponivel()) {
            throw new \InvalidArgumentException('Atualização dos perfis NFS-e pendente. Execute as migrations no servidor.');
        }
        if (!$this->qb->table('nfse_configuracoes')->where('id_matriz_filial', '=', $filial)->first()) {
            throw new \InvalidArgumentException('Configuração da filial não encontrada neste tenant.');
        }
        $existente = $this->buscar($filial, $campos['tipo_emissao'], (int) $campos['ambiente']);
        $fiscal = NFSeContextoFiscal::snapshot($campos);
        $fiscal['exigir_ctrib_nacional'] = $existente ? !empty($existente['_exigir_ctrib_nacional']) : $campos['tipo_emissao'] === 'nacional';
        $dados = [
            'serie' => $campos['serie'],
            'numero_atual' => max((int) ($campos['numero_atual'] ?? 0), (int) ($existente['numero_atual'] ?? 0)),
            'configuracao' => json_encode($fiscal, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'confirmado' => $confirmado ? 'S' : 'N',
        ];
        if ($existente) {
            unset($dados['numero_atual']);
            $this->qb->table('nfse_perfis_emissao')->where('id', '=', $existente['perfil_id'])->update($dados);
            $id = $existente['perfil_id'];
        } else {
            $id = $this->qb->table('nfse_perfis_emissao')->insert($dados + [
            'chave' => (string) $_SESSION['chave'], 'id_matriz_filial' => $filial,
            'tipo_emissao' => $campos['tipo_emissao'], 'ambiente' => (int) $campos['ambiente'],
        ]);
        }
        $chave = (string) $_SESSION['chave'];
        $tipo = (string) $campos['tipo_emissao'];
        $ambiente = (int) $campos['ambiente'];
        $serie = $this->serieSequencia($campos);
        $mesmaSerie = $existente && $this->serieSequencia($existente) === $serie;
        $numero = max(0, (int) ($campos['numero_atual'] ?? 0), $mesmaSerie ? (int) $existente['numero_atual'] : 0);
        if (trim((string) $campos['serie']) !== '' && ($tipo !== 'nacional' || preg_match('/^[0-9]{1,5}$/D', trim((string) $campos['serie'])))) {
            $stmt = $this->getMysqli()->prepare('INSERT INTO nfse_sequencias (chave,id_matriz_filial,tipo_emissao,ambiente,serie,numero_atual) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE numero_atual=GREATEST(numero_atual,VALUES(numero_atual))');
            $stmt->bind_param('sisisi', $chave, $filial, $tipo, $ambiente, $serie, $numero);
            $stmt->execute();
            $stmt->close();
        }
        $row = $this->qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $filial)->where('tipo_emissao', '=', $tipo)
            ->where('ambiente', '=', $ambiente)->where('serie', '=', $serie)->first();
        $this->qb->table('nfse_perfis_emissao')->where('id', '=', $id)->update(['numero_atual' => (int) ($row['numero_atual'] ?? $numero)]);
        return $id;
    }

    private function validarContexto(array $config): void
    {
        $perfil = $this->qb->table('nfse_perfis_emissao')->where('id', '=', (int) ($config['perfil_id'] ?? 0))
            ->where('id_matriz_filial', '=', (int) $config['id_matriz_filial'])->where('tipo_emissao', '=', $config['tipo_emissao'])
            ->where('ambiente', '=', (int) $config['ambiente'])->first();
        if (!$perfil) {
            throw new \InvalidArgumentException('Perfil de emissão não encontrado neste tenant e filial.');
        }
    }

    private function serieSequencia(array $config): string
    {
        $serie = trim((string) $config['serie']);
        return $config['tipo_emissao'] === 'nacional' ? (ltrim($serie, '0') ?: '0') : $serie;
    }

    public function proximo(array $config): int
    {
        $this->validarContexto($config);
        $chave = (string) $_SESSION['chave'];
        $filial = (int) $config['id_matriz_filial'];
        $tipo = (string) $config['tipo_emissao'];
        $ambiente = (int) $config['ambiente'];
        $serie = $this->serieSequencia($config);
        $mysqli = $this->getMysqli();
        // Inicialização não reserva número; preserva também o contador da origem migrada.
        $inicial = $this->maiorNumeroConhecido($config, (int) ($config['numero_atual'] ?? 0));
        $stmt = $mysqli->prepare('INSERT INTO nfse_sequencias (chave,id_matriz_filial,tipo_emissao,ambiente,serie,numero_atual) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE numero_atual=GREATEST(numero_atual,VALUES(numero_atual))');
        $stmt->bind_param('sisisi', $chave, $filial, $tipo, $ambiente, $serie, $inicial);
        $stmt->execute();
        $stmt->close();
        $row = $this->qb->table('nfse_sequencias')->where('id_matriz_filial', '=', $filial)
            ->where('tipo_emissao', '=', $tipo)->where('ambiente', '=', $ambiente)->where('serie', '=', $serie)->first();
        return ((int) $row['numero_atual']) + 1;
    }

    public function reservar(array $config, int $numero): bool
    {
        $this->validarContexto($config);
        $afetados = $this->qb->table('nfse_sequencias')
            ->where('id_matriz_filial', '=', (int) $config['id_matriz_filial'])
            ->where('tipo_emissao', '=', $config['tipo_emissao'])->where('ambiente', '=', (int) $config['ambiente'])
            ->where('serie', '=', $this->serieSequencia($config))->where('numero_atual', '=', $numero - 1)
            ->update(['numero_atual' => $numero]);
        if ($afetados !== 1) {
            return false;
        }
        $this->qb->table('nfse_perfis_emissao')->where('id', '=', (int) $config['perfil_id'])
            ->where('numero_atual', '<', $numero)->update(['numero_atual' => $numero]);
        // Contador legado apenas como espelho do perfil padrão, nunca como fonte da reserva.
        $this->qb->table('nfse_configuracoes')->where('id_matriz_filial', '=', (int) $config['id_matriz_filial'])
            ->where('tipo_emissao', '=', $config['tipo_emissao'])->where('ambiente', '=', (int) $config['ambiente'])
            ->where('serie', '=', $config['serie'])->where('numero_atual', '<', $numero)->update(['numero_atual' => $numero]);
        return true;
    }
}
