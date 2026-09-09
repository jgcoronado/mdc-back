# -*- coding: utf-8 -*-
"""Cruz de Guía (Huelva 2025): ficha por página + página de horarios con los
rótulos de hermandad; se emparejan por coincidencia con los titulares."""
import re, csv, unicodedata

CORTE = re.compile(r'^(Capataz|H[áa]bito|Herman[oa]|N[úu]m|N[ºo°]|Titulares|Templo|Fundaci|'
                   r'Radio|Cruz de Gu[íi]a 20|Escudo|Estrenos|Curiosidad|Tiempo)')
DIAS = ('VIERNES DOLORES', 'SÁBADO DE PASIÓN', 'SÁBADO DE P ASIÓN', 'DOMINGO DE RAMOS',
        'LUNES SANTO', 'MARTES SANTO', 'MIÉRCOLES SANTO', 'JUEVES SANTO', 'MADRUGADA',
        'VIERNES SANTO', 'SÁBADO SANTO', 'DOMINGO DE RESURRECCIÓN')

def sa(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn')

def norm(s):
    return re.sub(r'[^a-z ]', ' ', sa(s).lower())

def parse(path, anio):
    raw = open(path, encoding='utf-8').read()
    parts = re.split(r'\n===== PAG (\d+) =====\n', raw)
    it = iter(parts[1:])
    grupos, pendientes, filas = [], [], []
    for num, body in zip(it, it):
        lineas = [l.strip() for l in body.split('\n')]
        rot = [lineas[i - 1] for i, l in enumerate(lineas) if l == 'Salida Templo' and i > 0]
        mus = []
        for i, l in enumerate(lineas):
            if l != 'Música':
                continue
            val, k = '', i + 1
            while k < len(lineas) and lineas[k] and not CORTE.match(lineas[k]):
                val += ' ' + lineas[k]
                k += 1
            mus.append(re.sub(r'\s+', ' ', val).strip(' .'))
        tit = [l.lstrip('• ').strip() for l in lineas if l.startswith('•')]
        if mus:
            pendientes.append({'pag': num, 'mus': mus, 'tit': ' '.join(tit)})
        if rot and pendientes:
            grupos.append((pendientes, rot))
            pendientes = []
    if pendientes:
        grupos.append((pendientes, []))
    for fichas, rot in grupos:
        libres = list(rot)
        asign = [None] * len(fichas)
        for i, f in enumerate(fichas):
            for r in libres:
                if norm(r).split() and all(p in norm(f['tit']) for p in norm(r).split()
                                           if len(p) > 3):
                    asign[i] = r
                    libres.remove(r)
                    break
        for i in range(len(fichas)):
            if asign[i] is None and libres:
                asign[i] = libres.pop(0)
        for i, f in enumerate(fichas):
            for m in f['mus']:
                filas.append([anio, f['pag'], asign[i] or '?', m])
    return filas

if __name__ == '__main__':
    filas = parse('txt/huelva_2025.txt', '2025')
    with open('raw_huelva_2025.csv', 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['ANIO', 'PAG', 'HERMANDAD', 'BANDA'])
        w.writerows(filas)
    print(len(filas), 'filas;', len({f[2] for f in filas}), 'hermandades')
    for f in filas:
        print(f'{f[1]:>3} {f[2][:26]:28}| {f[3][:70]}')
