#!/usr/bin/env python3
"""Acentua valores (lado direito de =>) em app/lang/es_ES e app/lang/it_IT/**/*.php"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

# extract_string_span duplicado para evitar dependência circular
def extract_string_span(line: str) -> tuple[int, int] | None:
    if "=>" not in line:
        return None
    arrow = line.index("=>")
    rest = line[arrow + 2 :]
    m = re.match(r"\s*(['\"])", rest)
    if not m:
        return None
    q = m.group(1)
    i = m.end()
    escaped = False
    while i < len(rest):
        c = rest[i]
        if escaped:
            escaped = False
            i += 1
            continue
        if c == "\\":
            escaped = True
            i += 1
            continue
        if c == q:
            inner_start = arrow + 2 + m.end()
            inner_end = arrow + 2 + i
            return (inner_start, inner_end)
        i += 1
    return None


def transform_es_es(s: str) -> str:
    """Troca seguras para es_ES (evita transformar 'esta' demonstrativo)."""
    rules: list[tuple[str, str]] = [
        (r"\bNingun\b", "Ningún"),
        (r"\bningun\b", "ningún"),
        (r"\bQue es\b", "Qué es"),
        (r"\bque es la\b", "qué es la"),
        (r"\bDano\b", "Daño"),
        (r"\bdano\b", "daño"),
        (r"\bpagina\b", "página"),
        (r"\bPagina\b", "Página"),
        (r"\bpaginas\b", "páginas"),
        (r"\bPaginas\b", "Páginas"),
        (r"\bvehiculo\b", "vehículo"),
        (r"\bVehiculo\b", "Vehículo"),
        (r"\bvehiculos\b", "vehículos"),
        (r"\bVehiculos\b", "Vehículos"),
        (r"\bconexion\b", "conexión"),
        (r"\bConexion\b", "Conexión"),
        (r"\breconexion\b", "reconexión"),
        (r"\bReconexion\b", "Reconexión"),
        (r"\binformacion\b", "información"),
        (r"\bInformacion\b", "Información"),
        (r"\bconfiguracion\b", "configuración"),
        (r"\bConfiguracion\b", "Configuración"),
        (r"\bactualizacion\b", "actualización"),
        (r"\bActualizacion\b", "Actualización"),
        (r"\bvalidacion\b", "validación"),
        (r"\bValidacion\b", "Validación"),
        (r"\bnotificacion\b", "notificación"),
        (r"\bNotificacion\b", "Notificación"),
        (r"\bopcion\b", "opción"),
        (r"\bOpcion\b", "Opción"),
        (r"\boperacion\b", "operación"),
        (r"\bOperacion\b", "Operación"),
        (r"\btransaccion\b", "transacción"),
        (r"\bTransaccion\b", "Transacción"),
        (r"\binfraccion\b", "infracción"),
        (r"\bInfraccion\b", "Infracción"),
        (r"\bIndicacion\b", "Indicación"),
        (r"\bindicacion\b", "indicación"),
        (r"\bGrabacion\b", "Grabación"),
        (r"\bgrabacion\b", "grabación"),
        (r"\bGrabaciones\b", "Grabaciones"),
        (r"\bboton\b", "botón"),
        (r"\bBoton\b", "Botón"),
        (r"\baqui\b", "aquí"),
        (r"\bAqui\b", "Aquí"),
        (r"\btambien\b", "también"),
        (r"\bTambien\b", "También"),
        (r"\bnumero\b", "número"),
        (r"\bNumero\b", "Número"),
        (r"\bnumeros\b", "números"),
        (r"\bNumeros\b", "Números"),
        (r"\butilizaran\b", "utilizarán"),
        (r"\bUtilizaran\b", "Utilizarán"),
        (r"\bestara\b", "estará"),
        (r"\bEstara\b", "Estará"),
        (r"\bestaran\b", "estarán"),
        (r"\bseran\b", "serán"),
        (r"\bSeran\b", "Serán"),
        (r"\bcometio\b", "cometió"),
        (r"\bCometio\b", "Cometió"),
        (r"\bregularizacion\b", "regularización"),
        (r"\bRegularizacion\b", "Regularización"),
        (r"\btransito\b", "tránsito"),
        (r"\bTransito\b", "Tránsito"),
        (r"\blocacion\b", "locación"),
        (r"\bLocacion\b", "Locación"),
        (r"\belectronico\b", "electrónico"),
        (r"\bElectronico\b", "Electrónico"),
        (r"\belectronica\b", "electrónica"),
        (r"\bElectronica\b", "Electrónica"),
        (r"\bcontrasena\b", "contraseña"),
        (r"\bContrasena\b", "Contraseña"),
        (r"\bsincronizacion\b", "sincronización"),
        (r"\bSincronizacion\b", "Sincronización"),
        (r"\bodometro\b", "odómetro"),
        (r"\bOdometro\b", "Odómetro"),
        (r"\baccion\b", "acción"),
        (r"\bAccion\b", "Acción"),
        (r"\benvio\b", "envío"),
        (r"\bEnvio\b", "Envío"),
        (r"\bespecifico\b", "específico"),
        (r"\bEspecifico\b", "Específico"),
        (r"\bespecifica\b", "específica"),
        (r"\bEspecifica\b", "Específica"),
        (r"\bespecificas\b", "específicas"),
        (r"\bcodigo\b", "código"),
        (r"\bCodigo\b", "Código"),
        (r"\bcodigos\b", "códigos"),
        (r"\bCodigos\b", "Códigos"),
        (r"\bautomaticas\b", "automáticas"),
        (r"\bautomatica\b", "automática"),
        (r"\bAutomatica\b", "Automática"),
        (r"\bautomaticos\b", "automáticos"),
        (r"\bautomatico\b", "automático"),
        (r"\bAutomatico\b", "Automático"),
        (r"\bperiodicas\b", "periódicas"),
        (r"\bperiodica\b", "periódica"),
        (r"\bPeriodica\b", "Periódica"),
        (r"\brealizara\b", "realizará"),
        (r"\bRealizara\b", "Realizará"),
        (r"\btermino\b", "término"),
        (r"\bTermino\b", "Término"),
        (r"\bpublico\b", "público"),
        (r"\bPublico\b", "Público"),
        (r"\bpublica\b", "pública"),
        (r"\bdespues\b", "después"),
        (r"\bDespues\b", "Después"),
        (r"\bdevolucion\b", "devolución"),
        (r"\bDevolucion\b", "Devolución"),
        (r"\bautorenovacion\b", "autorrenovación"),
        (r"\bAutorenovacion\b", "Autorrenovación"),
        (r"\baplicacion\b", "aplicación"),
        (r"\bAplicacion\b", "Aplicación"),
        (r"\binspeccion\b", "inspección"),
        (r"\borgano\b", "órgano"),
        (r"\bOrgano\b", "Órgano"),
        (r"\btodavia\b", "todavía"),
        (r"\bTodavia\b", "Todavía"),
        (r"\bbrasilenos\b", "brasileños"),
        (r"\bBrasilenos\b", "Brasileños"),
        # Frases com ser / estar (sem tocar 'esta' como demonstrativo)
        (r"\besta creando\b", "está creando"),
        (r"\bEsta creando\b", "Está creando"),
        (r"\besta vinculado\b", "está vinculado"),
        (r"\besta vinculada\b", "está vinculada"),
        (r"\besta disponible\b", "está disponible"),
        (r"\besta incluido\b", "está incluido"),
        (r"\bvacio\b", "vacío"),
        (r"\bVacio\b", "Vacío"),
        (r"\bvacia\b", "vacía"),
        (r"\besta vacio\b", "está vacío"),
        (r"\besta vacia\b", "está vacía"),
        (r"(?<=\bya\s)esta\b", "está"),
        (r"(?<=\bYa\s)esta\b", "está"),
        (r"(?<=\bno\s)esta\b", "está"),
        (r"(?<=\bNo\s)esta\b", "está"),
        (r"(?<=\baun\s)esta\b", "está"),
        (r"(?<=\bAun\s)esta\b", "está"),
        (r"(?<=\btodavia\s)esta\b", "está"),
        (r"(?<=\bTodavia\s)esta\b", "está"),
        (r"(?<=\btodavía\s)esta\b", "está"),
        (r"(?<=\bTodavía\s)esta\b", "está"),
        # Segunda passagem (verbos/advérbios frequentes sem til)
        (r"\bpromocion\b", "promoción"),
        (r"\bPromocion\b", "Promoción"),
        (r"\bautomaticamente\b", "automáticamente"),
        (r"\bCuando esta\b", "Cuando está"),
        (r"\bcuando esta\b", "cuando está"),
        (r"\bdecrementara\b", "decrementará"),
        (r"\bmodificara\b", "modificará"),
        (r"\bmantendran\b", "mantendrán"),
        (r"\bseleccion\b", "selección"),
        (r"\bSeleccion\b", "Selección"),
        (r"\bdireccion\b", "dirección"),
        (r"\bDireccion\b", "Dirección"),
        (r"\besta seguro\b", "está seguro"),
        (r"\bEsta seguro\b", "Está seguro"),
        (r"\blo que esta\b", "lo que está"),
        (r"\blimite\b", "límite"),
        (r"\bLimite\b", "Límite"),
        (r"\bminimo\b", "mínimo"),
        (r"\bMinimo\b", "Mínimo"),
        (r"\bdias\b", "días"),
        (r"\bDias\b", "Días"),
        (r"\bcredito\b", "crédito"),
        (r"\bCredito\b", "Crédito"),
        (r"\bsera\b", "será"),
        (r"\bMinima\b", "Mínima"),
        (r"\bminima\b", "mínima"),
        (r"\bsea valida\b", "sea válida"),
        (r"\bSea valida\b", "Sea válida"),
        (r"\bse registro\b", "se registró"),
        (r"\bSe registro\b", "Se registró"),
    ]

    for pat, repl in rules:
        s = re.sub(pat, repl, s)

    return s


def transform_it_it(s: str) -> str:
    rules: list[tuple[str, str]] = [
        (r"\bNon e possibile\b", "Non è possibile"),
        (r"\bnon e possibile\b", "non è possibile"),
        (r"\bNon e collegato\b", "Non è collegato"),
        (r"\bnon e collegato\b", "non è collegato"),
        (r"\bche e stata registrata\b", "che è stata registrata"),
        (r"\bche il conducente identificato sopra e stato\b", "che il conducente identificato sopra è stato"),
        (r"\bche e stata\b", "che è stata"),
        (r"\bche e stato\b", "che è stato"),
        (r"\bL\'importo da pagare e di\b", "L\'importo da pagare è di"),
        (r"\bgia pagata\b", "già pagata"),
        (r"\bgia registrato\b", "già registrato"),
        (r"\bgia stato\b", "già stato"),
        (r"\bgia collegato\b", "già collegato"),
        (r"\bgia inattivo\b", "già inattivo"),
        (r"\bgia\b", "già"),
        (r"\bGia\b", "Già"),
        (r"\bresponsabilita\b", "responsabilità"),
        (r"\bResponsabilita\b", "Responsabilità"),
        (r"\bLa nomina del conducente e il\b", "La nomina del conducente è il"),
        (r"\bQuesto veicolo e gia\b", "Questo veicolo è già"),
        (r"\bQuesto veicolo e già\b", "Questo veicolo è già"),
        (r"\bQuesta ricevuta e valida\b", "Questa ricevuta è valida"),
        (r"\bQuesta ricevuta ha validita\b", "Questa ricevuta ha validità"),
        (r"\bil saldo e inferiore\b", "il saldo è inferiore"),
        (r"\bProdotto disattivato\. E collegato\b", "Prodotto disattivato. È collegato"),
        (r"\bIl prodotto e gia\b", "Il prodotto è già"),
        (r"\bnon puo\b", "non può"),
        (r"\bNon puo\b", "Non può"),
        (r"\bcon piu Multe\b", "con più Multe"),
        (r"\bpiu Multe\b", "più Multe"),
        (r"\bpiu\b", "più"),
        (r"\bPiu\b", "Più"),
        (r"\bquantita\b", "quantità"),
        (r"\bQuantita\b", "Quantità"),
        (r"\bcitta\b", "città"),
        (r"\bCitta\b", "Città"),
        (r"\baffinche\b", "affinché"),
        (r"\bAffinche\b", "Affinché"),
        (r"\bC'e una\b", "C'è una"),
        (r"\bCos'e\b", "Cos'è"),
        (r"\bPuo essere\b", "Può essere"),
        (r"\bPuo\b", "Può"),
    ]
    for pat, repl in rules:
        s = re.sub(pat, repl, s)
    return s


def process_file(path: Path, transform) -> bool:
    text = path.read_text(encoding="utf-8")
    lines = text.splitlines(keepends=True)
    changed = False
    out: list[str] = []
    for line in lines:
        stripped = line.lstrip()
        if stripped.startswith("//") or stripped.startswith("*") or stripped.startswith("/*"):
            out.append(line)
            continue
        span = extract_string_span(line)
        if span is None:
            out.append(line)
            continue
        a, b = span
        inner = line[a:b]
        new_inner = transform(inner)
        if new_inner != inner:
            changed = True
            line = line[:a] + new_inner + line[b:]
        out.append(line)
    if changed:
        path.write_text("".join(out), encoding="utf-8")
    return changed


def main() -> int:
    if len(sys.argv) < 2 or sys.argv[1] not in ("es_ES", "it_IT"):
        print("Uso: fix_es_it_accents.py es_ES|it_IT", file=sys.stderr)
        return 2
    lang = sys.argv[1]
    transform = transform_es_es if lang == "es_ES" else transform_it_it
    lang_path = ROOT / "app" / "lang" / lang
    if not lang_path.is_dir():
        print(f"Diretório não encontrado: {lang_path}", file=sys.stderr)
        return 1
    n = 0
    for f in sorted(lang_path.rglob("*.php")):
        if process_file(f, transform):
            print(f"Atualizado: {f.relative_to(ROOT)}")
            n += 1
    print(f"Total de arquivos modificados: {n}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
