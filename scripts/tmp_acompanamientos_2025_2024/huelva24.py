# -*- coding: utf-8 -*-
"""El Llamador de Huelva (Canal Sur, 2024).

Maquetación: el PDF apila [TITULAR][Capataces][Música:] y coloca el rótulo de la
HERMANDAD en versales entre dos pasos de la misma ficha, así que el nombre de la
hermandad puede quedar antes o después de su propia línea de música. Se resuelve
con un vocabulario de rótulos: para cada 'Música:' gana el rótulo más cercano.
"""
import csv
import re
import unicodedata

ROTULOS = [
    'EL PRADO', 'LOS DOLORES', 'LA BORRIQUITA', 'SAGRADA CENA', 'MUTILAOS', 'REDENCIÓN',
    'CAUTIVO', 'PERDÓN', 'TRES CAÍDAS', 'CALVARIO', 'LANZADA', 'SENTENCIA', 'ESTUDIANTES',
    'PASIÓN', 'EL PRENDIMIENTO', 'SANTA CRUZ', 'VICTORIA', 'ESPERANZA', 'MISERICORDIA',
    'ORACIÓN EN EL HUERTO', 'BUENA MUERTE', 'JUDÍOS', 'NAZARENO', 'DESCENDIMIENTO',
    'FE', 'SANTO ENTIERRO', 'SOLEDAD', 'EL RESUCITADO', 'VERA+CRUZ', 'BENDICIÓN',
]
VIRGEN = re.compile(r'(NUESTRA SE[ÑN]ORA|MAR[ÍI]A|VIRGEN|SOLEDAD DE MAR[ÍI]A|'
                    r'M[ªA]\.? SANT[ÍI]SIMA|NUESTRA MADRE)')


def sa(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn')


def es_caps(s):
    letras = [c for c in s if c.isalpha()]
    return len(letras) >= 3 and all(c.isupper() for c in letras)


def limpia(v):
    v = re.sub(r'(\w)\s*-\s+(\w)', r'\1\2', v)
    v = re.sub(r'\s+', ' ', v).strip(' .')
    return re.split(r'\s+(?:Horas de recorrido|Longitud|Capataces?:|Hermano|Hermana|'
                    r'Datos de inter|Historia:)', v)[0].strip(' .')


def parse(path, anio):
    raw = open(path, encoding='utf-8').read()
    paginas = re.split(r'\n===== PAG (\d+) =====\n', raw)
    it = iter(paginas[1:])
    filas = []
    for num, body in zip(it, it):
        lineas = [l.rstrip() for l in body.split('\n')]
        bloques, i = [], 0
        while i < len(lineas):
            if es_caps(lineas[i].strip()):
                j, tr = i, []
                while j < len(lineas) and es_caps(lineas[j].strip()):
                    tr.append(lineas[j].strip())
                    j += 1
                bloques.append((i, re.sub(r'\s+', ' ', ' '.join(tr)).strip(' :.')))
                i = j
            else:
                i += 1
        rot = [(n, t) for n, t in bloques if sa(t).upper() in [sa(r).upper() for r in ROTULOS]]
        tit = [(n, t) for n, t in bloques if (n, t) not in rot]
        for n, l in enumerate(lineas):
            if not re.match(r'^\s*M[úu]sica\s*:', l.strip()):
                continue
            val, k = l.split(':', 1)[1], n + 1
            while k < len(lineas) and lineas[k].strip() and not es_caps(lineas[k].strip()) \
                    and not re.match(r'^(M[úu]sica|Capataces?|Herman|Datos|Historia|Horas|'
                                     r'Longitud|X:)', lineas[k].strip()):
                val += ' ' + lineas[k]
                k += 1
            herm = min(rot, key=lambda x: abs(x[0] - n))[1] if rot else '?'
            antes = [t for m, t in tit if m < n]
            titular = antes[-1] if antes else ''
            filas.append([anio, num, herm, titular,
                          'Paso de Virgen' if VIRGEN.search(sa(titular).upper()
                                                            .replace('Ñ', 'N'))
                          else 'Paso de Misterio',
                          limpia(val)])
    return filas


if __name__ == '__main__':
    filas = parse('txt/huelva_2024.txt', '2024')
    with open('raw_huelva_2024.csv', 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['ANIO', 'PAG', 'HERMANDAD', 'TITULAR_FUENTE', 'TITULAR', 'BANDA'])
        w.writerows(filas)
    print(len(filas), 'filas;', len({f[2] for f in filas}), 'hermandades')
    for f in filas:
        print(f'{f[1]:>3} {f[2][:22]:24}|{f[4]:17}|{f[3][:30]:32}| {f[5][:52]}')
