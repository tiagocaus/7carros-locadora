#!/usr/bin/env bash
#
# sync-uploads.sh — Migração de arquivos do servidor antigo para o novo via rsync over SSH.
#
# USO:
#   1) Abra um tmux/screen ANTES (10 GB pode levar horas):
#        tmux new -s sync
#   2) Configure as variáveis (env ou edite os defaults abaixo):
#        export SRC_HOST="antigo.exemplo.com"
#        export SRC_USER="deploy"
#        export SRC_PORT="22"
#        export SRC_PATH="/var/www/locadora/storage/uploads/"   # barra final = conteúdo da pasta
#        export DEST_PATH="./storage/uploads/"
#        export SSHPASS='minha_senha_ssh'                        # senha do SRC_USER (obrigatório)
#        export BWLIMIT="0"                                      # 0=sem limite, ou ex: 50000 (KB/s)
#   3) Preview:    ./scripts/sync-uploads.sh --dry-run
#      Real:       ./scripts/sync-uploads.sh
#      Verificar:  ./scripts/sync-uploads.sh --checksum   (após terminar, valida integridade)
#
# Flags:
#   --list [P]   Sem P: lista pastas de 1º nível (tamanho + nº arquivos). Com P: lista o conteúdo de SRC_PATH/P.
#   --limit N    Sincroniza só as N primeiras pastas (ordem alfabética, respeita EXCLUDE_DIRS).
#   --dry-run    Mostra o que seria copiado, sem copiar.
#   --checksum   Compara por checksum (bem mais lento; usar só na verificação final).
#   --compress   Liga -z (use se link < 100 Mbps; em LAN/datacenter rápido CPU vira gargalo).
#   -h, --help   Esta ajuda.
#
# Saída: log em storage/logs/sync-uploads-YYYY-MM-DD-HHMMSS.log
#
# Pasta: /home/7carros/public_html/locadoranovo.7carros.com/)
#

set -euo pipefail

# ----- Defaults (sobrescreva via env vars) ---------------------------------
SRC_HOST="srv1.hostcia.com.br"
SRC_USER="root"
SRC_PORT="2277"
SRC_PATH="/home/7carros/public_html/sistemas/locadora/uploads"
DEST_PATH="./storage/uploads/"
SSHPASS="LBlctNE8K8OnkG5ESY05fCou"
BWLIMIT="${BWLIMIT:-0}"

# Pastas/padrões a ignorar (vira --exclude no rsync e some do --list).
# Pode ser nome de pasta (ex: "cache") ou padrão rsync (ex: "*.tmp").
EXCLUDE_DIRS=(
  ".DS_Store"
  "Thumbs.db"
)

# ----- Parse de flags ------------------------------------------------------
DRY_RUN=0
CHECKSUM=0
COMPRESS=0
LIST=0
LIST_PATH=""
LIMIT=0
while (( $# )); do
  case "$1" in
    --list)
      LIST=1
      # Próximo arg, se não começar com "--", é o subpath a inspecionar.
      if [[ -n "${2:-}" && "${2:0:2}" != "--" ]]; then
        LIST_PATH="$2"
        shift
      fi
      ;;
    --dry-run)   DRY_RUN=1 ;;
    --checksum)  CHECKSUM=1 ;;
    --compress)  COMPRESS=1 ;;
    --limit)
      shift
      if [[ -z "${1:-}" || ! "$1" =~ ^[1-9][0-9]*$ ]]; then
        echo "Erro: --limit precisa de um inteiro > 0 (ex: --limit 3)" >&2
        exit 2
      fi
      LIMIT="$1"
      ;;
    --limit=*)
      val="${1#--limit=}"
      if [[ ! "$val" =~ ^[1-9][0-9]*$ ]]; then
        echo "Erro: --limit precisa de um inteiro > 0 (ex: --limit=3)" >&2
        exit 2
      fi
      LIMIT="$val"
      ;;
    -h|--help)
      sed -n '2,30p' "$0" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *)
      echo "Flag desconhecida: $1" >&2
      exit 2
      ;;
  esac
  shift
done

# ----- Validação -----------------------------------------------------------
missing=()
[[ -z "$SRC_HOST" ]] && missing+=("SRC_HOST")
[[ -z "$SRC_USER" ]] && missing+=("SRC_USER")
[[ -z "$SRC_PATH" ]] && missing+=("SRC_PATH")
[[ -z "${SSHPASS:-}" || "$SSHPASS" == "COLOQUE_A_SENHA_AQUI" ]] && missing+=("SSHPASS")
if (( ${#missing[@]} > 0 )); then
  echo "Erro: variáveis obrigatórias não definidas: ${missing[*]}" >&2
  echo "Use --help para ver instruções." >&2
  exit 2
fi
export SSHPASS

if ! command -v rsync >/dev/null 2>&1; then
  echo "Erro: rsync não está instalado neste servidor." >&2
  exit 127
fi

if ! command -v sshpass >/dev/null 2>&1; then
  echo "Erro: sshpass não está instalado. Instale com: brew install hudochenkov/sshpass/sshpass" >&2
  exit 127
fi

mkdir -p "$DEST_PATH"
mkdir -p storage/logs
LOG_FILE="storage/logs/sync-uploads-$(date +%Y-%m-%d-%H%M%S).log"

# ----- Monta comando SSH ---------------------------------------------------
ssh_opts=("-p" "$SRC_PORT" "-o" "ServerAliveInterval=60" "-o" "ServerAliveCountMax=3" "-o" "StrictHostKeyChecking=accept-new" "-o" "PubkeyAuthentication=no")
SSH_CMD="sshpass -e ssh ${ssh_opts[*]}"

# ----- Monta flags do rsync ------------------------------------------------
rsync_flags=(
  -a                              # archive: recursivo + preserva perms/times/symlinks
  -v                              # verbose
  --info=progress2                # progresso global (não por arquivo)
  --human-readable                # MB/GB
  --partial                       # mantém parciais para retomar
  --append-verify                 # retoma com verificação de integridade
  --stats                         # sumário final
  --bwlimit="$BWLIMIT"
  -e "$SSH_CMD"
)
for ex in "${EXCLUDE_DIRS[@]}"; do
  rsync_flags+=(--exclude="$ex")
done
(( DRY_RUN  )) && rsync_flags+=(--dry-run)
(( CHECKSUM )) && rsync_flags+=(--checksum)
(( COMPRESS )) && rsync_flags+=(-z)

SRC="${SRC_USER}@${SRC_HOST}:${SRC_PATH%/}/"

# ----- Modo --list: inventário remoto e sai --------------------------------
if (( LIST )); then
  # Resolve destino absoluto pra confirmação.
  dest_abs="$(cd "$DEST_PATH" 2>/dev/null && pwd || echo "$DEST_PATH")"

  echo "=========================================="
  echo "Inventário da origem — $(date '+%Y-%m-%d %H:%M:%S')"
  if [[ -n "$LIST_PATH" ]]; then
    echo "Origem : ${SRC_USER}@${SRC_HOST}:${SRC_PATH}/${LIST_PATH}"
  else
    echo "Origem : ${SRC_USER}@${SRC_HOST}:${SRC_PATH}"
  fi
  echo "Destino: $DEST_PATH"
  echo "         (resolvido: $dest_abs)"
  if [[ -z "$LIST_PATH" ]] && (( ${#EXCLUDE_DIRS[@]} )); then
    echo "Ignorando: ${EXCLUDE_DIRS[*]}"
  fi
  echo "=========================================="

  if [[ -n "$LIST_PATH" ]]; then
    # ----- Listagem profunda de uma pasta específica ---------------------
    # Escapa aspas simples no LIST_PATH pro heredoc abaixo.
    sub_escaped="$(printf '%s' "$LIST_PATH" | sed "s/'/'\\\\''/g")"
    remote_script=$(cat <<REMOTE
set -e
cd "$SRC_PATH/$sub_escaped" 2>/dev/null || { echo "ERRO: não consegui entrar em $SRC_PATH/$sub_escaped" >&2; exit 1; }
echo "Conteúdo (recursivo):"
echo
# Lista todos os arquivos com tamanho human-readable, ordenado.
find . -mindepth 1 \\( -type f -o -type d \\) -printf '%y %s %p\n' 2>/dev/null | LC_ALL=C sort -k3 | while read -r kind bytes path; do
  if [ "\$kind" = "d" ]; then
    printf '  [DIR]  %s\n' "\$path"
  else
    hsize=\$(numfmt --to=iec --suffix=B \$bytes 2>/dev/null || echo "\${bytes}B")
    printf '  %8s  %s\n' "\$hsize" "\$path"
  fi
done
echo
total_bytes=\$(find . -type f -printf '%s\n' 2>/dev/null | awk '{s+=\$1} END{print s+0}')
total_files=\$(find . -type f 2>/dev/null | wc -l)
htotal=\$(numfmt --to=iec --suffix=B \$total_bytes 2>/dev/null || echo "\${total_bytes}B")
printf 'TOTAL: %s arquivos, %s\n' "\$total_files" "\$htotal"
REMOTE
)
  else
    # ----- Inventário top-level (comportamento original) -----------------
    # Monta cláusulas -name '<padrão>' para o find remoto pular os excludes.
    prune_expr=""
    for ex in "${EXCLUDE_DIRS[@]}"; do
      [[ -n "$prune_expr" ]] && prune_expr+=" -o "
      prune_expr+="-name '$(printf '%s' "$ex" | sed "s/'/'\\\\''/g")'"
    done
    prune_clause=""
    [[ -n "$prune_expr" ]] && prune_clause="\\( $prune_expr \\) -prune -o"

    remote_script=$(cat <<REMOTE
set -e
cd "$SRC_PATH" 2>/dev/null || { echo "ERRO: não consegui entrar em $SRC_PATH" >&2; exit 1; }
total_files=0
total_bytes=0
printf '%-40s %12s %14s\n' "PASTA" "TAMANHO" "ARQUIVOS"
printf '%-40s %12s %14s\n' "----------------------------------------" "------------" "--------------"
for d in */; do
  name="\${d%/}"
  case "\$name" in
$(for ex in "${EXCLUDE_DIRS[@]}"; do echo "    $ex) continue ;;"; done)
  esac
  bytes=\$(find "\$d" $prune_clause -type f -printf '%s\n' 2>/dev/null | awk '{s+=\$1} END{print s+0}')
  files=\$(find "\$d" $prune_clause -type f 2>/dev/null | wc -l)
  hsize=\$(numfmt --to=iec --suffix=B \$bytes 2>/dev/null || echo "\${bytes}B")
  printf '%-40s %12s %14s\n' "\$name" "\$hsize" "\$files"
  total_files=\$((total_files + files))
  total_bytes=\$((total_bytes + bytes))
done
# Arquivos soltos na raiz
root_bytes=\$(find . -maxdepth 1 -type f -printf '%s\n' 2>/dev/null | awk '{s+=\$1} END{print s+0}')
root_files=\$(find . -maxdepth 1 -type f 2>/dev/null | wc -l)
if [ "\$root_files" -gt 0 ]; then
  hsize=\$(numfmt --to=iec --suffix=B \$root_bytes 2>/dev/null || echo "\${root_bytes}B")
  printf '%-40s %12s %14s\n' "(arquivos na raiz)" "\$hsize" "\$root_files"
  total_files=\$((total_files + root_files))
  total_bytes=\$((total_bytes + root_bytes))
fi
printf '%-40s %12s %14s\n' "----------------------------------------" "------------" "--------------"
htotal=\$(numfmt --to=iec --suffix=B \$total_bytes 2>/dev/null || echo "\${total_bytes}B")
printf '%-40s %12s %14s\n' "TOTAL" "\$htotal" "\$total_files"
REMOTE
)
  fi

  sshpass -e ssh "${ssh_opts[@]}" "${SRC_USER}@${SRC_HOST}" "bash -s" <<<"$remote_script"
  exit $?
fi

# ----- Modo --limit N: seleciona as N primeiras pastas e restringe rsync ---
LIMIT_FOLDERS=()
if (( LIMIT > 0 )); then
  # Monta filtro grep -v -e "^pasta$" pra cada exclude (apenas matches exatos de nome).
  exclude_grep=()
  for ex in "${EXCLUDE_DIRS[@]}"; do
    exclude_grep+=(-e "^${ex}$")
  done

  remote_ls=$(cat <<REMOTE
cd "$SRC_PATH" 2>/dev/null || { echo "ERRO: não consegui entrar em $SRC_PATH" >&2; exit 1; }
for d in */; do echo "\${d%/}"; done | LC_ALL=C sort
REMOTE
)
  all_dirs=$(sshpass -e ssh "${ssh_opts[@]}" "${SRC_USER}@${SRC_HOST}" "bash -s" <<<"$remote_ls")

  if (( ${#exclude_grep[@]} )); then
    selected=$(echo "$all_dirs" | grep -v "${exclude_grep[@]}" | head -n "$LIMIT")
  else
    selected=$(echo "$all_dirs" | head -n "$LIMIT")
  fi

  if [[ -z "$selected" ]]; then
    echo "Erro: --limit $LIMIT mas nenhuma pasta encontrada em $SRC_PATH (após EXCLUDE_DIRS)." >&2
    exit 1
  fi

  while IFS= read -r d; do
    [[ -z "$d" ]] && continue
    LIMIT_FOLDERS+=("$d")
    rsync_flags+=(--include="/${d}/" --include="/${d}/***")
  done <<<"$selected"
  rsync_flags+=(--exclude="/*")
fi

# ----- Execução ------------------------------------------------------------
{
  echo "=========================================="
  echo "sync-uploads.sh — $(date '+%Y-%m-%d %H:%M:%S')"
  echo "Origem : $SRC"
  echo "Destino: $DEST_PATH"
  if (( ${#LIMIT_FOLDERS[@]} )); then
    echo "Limite : $LIMIT pastas → ${LIMIT_FOLDERS[*]}"
  fi
  echo "Flags  : ${rsync_flags[*]}"
  echo "=========================================="
} | tee -a "$LOG_FILE"

# Roda rsync, espelha stdout/stderr para log e terminal.
set +e
rsync "${rsync_flags[@]}" "$SRC" "$DEST_PATH" 2>&1 | tee -a "$LOG_FILE"
status=${PIPESTATUS[0]}
set -e

echo "" | tee -a "$LOG_FILE"
echo "Exit code: $status" | tee -a "$LOG_FILE"
echo "Log: $LOG_FILE"
exit "$status"
