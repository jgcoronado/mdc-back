# -*- coding: utf-8 -*-
"""Programas de mano de Canal Sur: empareja, página a página, las líneas
'Música:' con los nombres de hermandad en versales."""
import re, sys, csv, unicodedata

RUIDO = re.compile(r'^(X:|HORARIOS?|HORAS?\b|CRUZ DE GU|PASO DE|TRONO|©|EGONDI|CANAL SUR|'
                   r'SALIDA|ENTRADA|CATEDRAL|CANDELARIA|PALILLERO|VALVERDE|BARRI|NUEVA\b|'
                   r'DOMI[NG]|LUNES|MARTES|MI[EÉ]RCOLES|JUEVES|VIERNES|S[AÁ]BADO|MADRUGADA|PASO |'
                   r'PARROQUIA|IGLESIA|CAPILLA|CONVENTO|BAS[IÍ]LICA|ERMITA|SANTUARIO|SEDE|'
                   r'TEMPLO|RADIO|C/|CON PRESENCIA|OFICINAS|CMY|EL INDICADOR|ESTE N|MENOR RIESGO|'
                   r'TRANSMISIONES|\(|[0-9])')
SEDE = re.compile(r'^(Parroquia|Capilla|Iglesia|Convento|Bas[íi]lica|Ermita|Santuario|Templo|'
                  r'Real |Sede|X: @|www|http)')

def sa(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn')

def es_caps(s):
    l = [c for c in s if c.isalpha()]
    return len(l) >= 3 and all(c.isupper() for c in l)

def limpia(v):
    v = re.sub(r'(\w)\s*-\s+(\w)', r'\1\2', v)
    v = re.sub(r'\s+', ' ', v).strip(' .')
    for m in re.finditer(r'\.\s+(?=[A-ZÁÉÍÓÚ¿"“])', v):
        pal = re.search(r'[A-Za-záéíóúñ]+$', v[:m.start()])
        if pal and len(pal.group(0)) >= 4 and pal.group(0)[0].islower():
            v = v[:m.start()]
            break
    v = re.split(r'\s+(?:Horas de recorrido|Longitud|A[ñn]o de fundaci|Capataces?:|N[ºo°] de|'
                 r'Sede Can[óo]nica|Hermano Mayor|Hermana Mayor)', v)[0]
    return v.strip(' .')

CORTE = re.compile(r'^(M[úu]sica|Banda\s*:|Capataz|Costaleros|Herman[oa] Mayor|Hermanos|Estrenos?|'
                   r'De inter|De Inter|Datos|Salida|N[ºo°]|A destacar|Efem[ée]|T[úu]nicas|'
                   r'Im[áa]gen|H[áa]bito|Historia|Rese[ñn]a|Lugares|Sede|A[ñn]o|Prior|Horas de|'
                   r'Longitud|Fundaci|Titulares|Recorrido|Itinerario)')

def bloques_caps(lineas, unir=True):
    out, i = [], 0
    while i < len(lineas):
        s = lineas[i].strip()
        if es_caps(s) and not RUIDO.match(sa(s).upper()):
            j, tr = i, []
            while j < len(lineas) and es_caps(lineas[j].strip()) \
                    and not RUIDO.match(sa(lineas[j].strip()).upper()):
                tr.append(lineas[j].strip())
                j += 1
                if not unir:
                    break
            sig = ''
            for k in range(j, min(j + 3, len(lineas))):
                if lineas[k].strip():
                    sig = lineas[k].strip()
                    break
            out.append({'ln': i, 'txt': ' '.join(tr), 'sig': sig})
            i = j
        else:
            i += 1
    return out

def parse(path, anio, modo):
    raw = open(path, encoding='utf-8').read()
    paginas = re.split(r'\n===== PAG (\d+) =====\n', raw)
    it = iter(paginas[1:])
    filas, avisos = [], []
    for num, body in zip(it, it):
        lineas = [l.rstrip() for l in body.split('\n')]
        musicas = []
        for n, l in enumerate(lineas):
            if not re.match(r'^\s*M[úu]sica\s*:', l.strip()):
                continue
            val = l.split(':', 1)[1]
            k = n + 1
            while k < len(lineas) and lineas[k].strip() and not es_caps(lineas[k].strip()) \
                    and not CORTE.match(lineas[k].strip()):
                val += ' ' + lineas[k]
                k += 1
            musicas.append((n, limpia(val)))
        if not musicas:
            continue
        bl = bloques_caps(lineas, unir=(modo != 'ordenlin'))
        if modo == 'sede':
            bl = [b for b in bl if SEDE.match(b['sig'])]
        prev, dedup = None, []
        for b in bl:
            if b['txt'] != prev:
                dedup.append(b)
                prev = b['txt']
        bl = dedup
        if modo == 'sede':
            todos = bloques_caps(lineas)
            for ln, m in musicas:
                sig = [b['txt'] for b in bl if b['ln'] > ln]
                ant = [b['txt'] for b in todos if b['ln'] < ln]
                filas.append([anio, num, sig[0] if sig else '?', ant[-1] if ant else '', m])
            if not bl:
                avisos.append((num, len(musicas), []))
        else:
            nombres = [b['txt'] for b in bl]
            if len(nombres) != len(musicas):
                avisos.append((num, len(musicas), nombres))
            for i, (ln, m) in enumerate(musicas):
                filas.append([anio, num, nombres[i] if i < len(nombres) else '?', '', m])
    return filas, avisos

if __name__ == '__main__':
    ciudad, anio, modo = sys.argv[1], sys.argv[2], sys.argv[3]
    filas, avisos = parse(f'txt/{ciudad}_{anio}.txt', anio, modo)
    with open(f'raw_{ciudad}_{anio}.csv', 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['ANIO', 'PAG', 'HERMANDAD', 'TITULAR_FUENTE', 'BANDA'])
        w.writerows(filas)
    print(ciudad, anio, len(filas), 'filas;', len({f[2] for f in filas}), 'hdades;',
          len(avisos), 'páginas descuadradas')
    for a in avisos:
        print('   PAG', a[0], 'musicas=', a[1], 'nombres=', a[2])
