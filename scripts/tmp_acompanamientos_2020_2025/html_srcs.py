# -*- coding: utf-8 -*-
"""Extractores de las fuentes HTML: Granada 2025 (ahoragranada),
Málaga 2025 (101tv) y Málaga 2024 (malagamusical)."""
import re, html, csv, sys

def bloques(path):
    t = open(path, encoding='utf-8', errors='replace').read()
    t = re.sub(r'(?is)<script.*?</script>|<style.*?</style>', '', t)
    out = []
    for m in re.finditer(r'(?is)<(h[1-6]|p|li)[^>]*>(.*?)</\1>', t):
        c = html.unescape(re.sub(r'(?s)<[^>]+>', '', m.group(2)))
        c = re.sub(r'\s+', ' ', c).strip()
        if c:
            out.append((m.group(1), c))
    return out

DIAS = re.compile(r'^(Domingo de Ramos|Lunes Santo|Martes Santo|Mi[ée]rcoles Santo|Jueves Santo|'
                  r'Madrugada|Viernes Santo|S[áa]bado Santo|Domingo de Resurrecci[óo]n|'
                  r'Viernes de Dolores|S[áa]bado de Pasi[óo]n)$', re.I)

def granada(path, anio):
    """Dos maquetaciones de ahoragranada:
    - 2023-2026: <p>HermandadMúsica misterio: X Música palio: Y</p>
    - 2022:      <p><strong>Hermandad</strong></p><p>Música misterio: X</p>
    """
    filas, dia, actual = [], '', ''
    for tag, c in bloques(path):
        if DIAS.match(c):
            dia = c
            continue
        if not re.search(r'M[úu]sica|Abre calle', c):
            if tag == 'p' and 2 < len(c) < 45 and ':' not in c and not c.endswith('.'):
                actual = c.strip()
            continue
        c = re.sub(r'(?<=[a-zúí])(M[úu]sica)', r' | \1', c)
        partes = re.split(r'(M[úu]sica[^:]{0,30}:|Abre calle:)', c)
        herm = partes[0].strip(' :·-|').strip() or actual
        if not herm or len(herm) > 60:
            continue
        actual = herm
        for i in range(1, len(partes), 2):
            etiqueta = partes[i].strip(':')
            filas.append([anio, dia, herm, etiqueta, partes[i + 1].strip(' .')])
    return filas

def tv101(path, anio):
    """<p>Cofradía</p> seguido de <li>Paso: Banda</li>"""
    filas, dia, herm = [], '', ''
    for tag, c in bloques(path):
        if tag.startswith('h') and DIAS.match(c):
            dia = c
            continue
        if tag == 'p' and len(c) < 45 and ':' not in c and not c.endswith('.'):
            herm = c
            continue
        if tag == 'li' and ':' in c and herm:
            paso, banda = c.split(':', 1)
            filas.append([anio, dia, herm, paso.strip(), banda.strip(' .')])
    return filas

ROLES_MAL = (r'Cruz de Gu[íi]a|Cruz Gu[íi]a|Cristo|Virgen|Se[ñn]or|Misterio|Palio|'
             r'Abriendo secci[óo]n de la Virgen|Abriendo Secci[óo]n de la|'
             r'Delante del Trono|[ÁA]nimas de Ciegos|Azotes y Columna|Exaltaci[óo]n|'
             r'Urna|Duelo|Nazareno|Trono|Crucificado|San Juan|Sant[íi]sima|No Lleva')
DIAS_MAL = (r'DOMINGO DE RAMOS|LUNES SANTO|MARTES SANTO|MI[EÉ]RCOLES SANTO|JUEVES SANTO|'
            r'MADRUGADA|VIERNES SANTO|S[ÁA]BADO SANTO|DOMINGO DE RESURRECCI[ÓO]N|'
            r'V[ÍI]SPERAS')


def malagablog2(path, anio):
    """Años en que el blog pega el rótulo de la cofradía al del paso:
    'PollinicaCruz de Guía', 'Lágrimas y FavoresVirgen', 'LUNES SANTOCrucifixiónCristo'."""
    filas, dia, herm, rol = [], '', '', ''
    for l in open(path, encoding='utf-8'):
        l = l.replace('\xa0', ' ').strip()
        if not l:
            continue
        if re.match(r'^[-–•]', l):
            if herm and rol:
                filas.append([anio, dia, herm, rol, l.lstrip('-–• ').strip(' .*')])
            continue
        m = re.match(rf'^({DIAS_MAL})\s*', l)
        if m:
            dia, l = m.group(1), l[m.end():].strip()
            if not l:
                continue
        m = re.search(rf'({ROLES_MAL})\s*$', l)
        if m:
            pre = l[:m.start()].strip(' .-')
            if pre:
                herm = pre
            rol = m.group(1)
        else:
            herm, rol = l.strip(' .'), ''
    return filas


def malagablog(path, anio):
    """Texto plano del blog: DIA / Cofradía / Rol / -Banda."""
    filas, dia, herm, rol = [], '', '', ''
    for l in open(path, encoding='utf-8'):
        l = l.strip()
        if not l:
            continue
        if l == l.upper() and len(l) > 4 and sum(ch.isalpha() for ch in l) > 4:
            dia, herm, rol = l, '', ''
            continue
        if re.match(r'^[-–•]', l):
            if herm and rol:
                filas.append([anio, dia, herm, rol, l.lstrip('-–• ').strip(' .')])
            continue
        if re.match(r'^(Cruz de Gu[íi]a|Cruz Gu[íi]a|Cristo|Virgen|Se[ñn]or|Misterio|Palio|'
                    r'Abriendo|Urna|Duelo|Nazareno|Trono|Crucificado|San Juan|Sant[íi]sima)\b',
                    l, re.I):
            rol = l.strip(' .')
            continue
        herm, rol = l.strip(' .'), ''
    return filas

if __name__ == '__main__':
    modo, path, anio, out = sys.argv[1:5]
    filas = {'granada': granada, 'tv101': tv101, 'malagablog': malagablog,
             'malagablog2': malagablog2}[modo](path, anio)
    with open(out, 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['ANIO', 'DIA', 'HERMANDAD', 'PASO', 'BANDA'])
        w.writerows(filas)
    print(out, len(filas), 'filas;', len({f[2] for f in filas}), 'hermandades')
