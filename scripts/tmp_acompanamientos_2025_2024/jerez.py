# -*- coding: utf-8 -*-
"""Estación de Penitencia (Canal Sur, Jerez) -> (hermandad, titular, banda)."""
import re, sys, csv, unicodedata

DIAS = ['SABADO DE PASION','DOMINGO DE RAMOS','LUNES SANTO','MARTES SANTO','MIERCOLES SANTO',
        'JUEVES SANTO','NOCHE DE JESUS','MADRUGADA','VIERNES SANTO','SABADO SANTO',
        'DOMINGO DE RESURRECCION','RESURRECCION','VIERNES DE DOLORES']

def sa(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn')

def es_caps(s):
    letras = [c for c in s if c.isalpha()]
    return len(letras) >= 3 and all(c.isupper() for c in letras)

def unwrap(v):
    v = re.sub(r'(\w)\s*-\s+(\w)', r'\1\2', v)
    v = re.sub(r'\s+', ' ', v).strip(' .')
    # el PDF encadena la prosa de la ficha detrás del nombre de la banda
    for m in re.finditer(r'\.\s+(?=[A-Z\u00c1\u00c9\u00cd\u00d3\u00da])', v):
        prev = v[:m.start()]
        pal = re.search(r"[A-Za-z\u00e1\u00e9\u00ed\u00f3\u00fa\u00f1]+$", prev)
        if pal and len(pal.group(0)) >= 4 and pal.group(0)[0].islower():
            v = v[:m.start()]
            break
    v = re.sub(r'\s+\d{1,3}$', '', v)
    return v.strip(' .')

def parse(path, anio):
    raw = open(path, encoding='utf-8').read()
    raw = re.sub(r'\n===== PAG \d+ =====\n', '\n', raw)
    lineas = [l.rstrip() for l in raw.split('\n')]
    # bloques de mayúsculas consecutivas
    bloques, i = [], 0
    while i < len(lineas):
        if es_caps(lineas[i].strip()):
            j = i
            trozo = []
            while j < len(lineas) and es_caps(lineas[j].strip()):
                trozo.append(lineas[j].strip())
                j += 1
            bloques.append((i, j, ' '.join(trozo)))
            i = j
        else:
            i += 1
    def siguiente(idx):
        for k in range(idx, min(idx + 3, len(lineas))):
            if lineas[k].strip():
                return lineas[k].strip()
        return ''
    titulares = {}   # linea_inicio -> texto
    hermandades = {}
    for n, (a, b, txt) in enumerate(bloques):
        if re.match(r'^(Capataz|Costaleros|M[úu]sica|Banda\s*:|Acompa)', siguiente(b)):
            titulares[a] = txt
        elif any(d in sa(txt).upper() for d in DIAS):
            continue
        else:
            hermandades[a] = txt
    filas, dia = [], ''
    for n, l in enumerate(lineas):
        s = sa(l).upper()
        for d in DIAS:
            if d in s and len(l.strip()) < 40:
                dia = d
        if not re.match(r'^(M[úu]sica|Banda|Acompa[ñn]amiento)\s*:', l.strip()):
            continue
        val = l.split(':', 1)[1]
        k = n + 1
        while k < len(lineas) and lineas[k].strip() and not re.match(
                r'^(Capataz|Costaleros|M[úu]sica|Banda\s*:|Acompa|Herman|Estrenos|Tiempo|Rese|'
                r'Imaginer|Lugares|N[ºo°]|N\.º|Pasaje|Escudo|Túnica)',
                lineas[k].strip()) and not es_caps(lineas[k].strip()):
            val += ' ' + lineas[k]
            k += 1
        tit = max((a for a in titulares if a < n), default=None)
        her = max((a for a in hermandades if a < n), default=None)
        filas.append([anio, dia, hermandades.get(her, '?'), titulares.get(tit, '?'), unwrap(val)])
    return filas

if __name__ == '__main__':
    anio = sys.argv[1]
    filas = parse(f'txt/jerez_{anio}.txt', anio)
    with open(f'raw_jerez_{anio}.csv', 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['ANIO', 'DIA', 'HERMANDAD', 'TITULAR', 'BANDA'])
        w.writerows(filas)
    print(len(filas), 'filas;', len({f[2] for f in filas}), 'hermandades')
