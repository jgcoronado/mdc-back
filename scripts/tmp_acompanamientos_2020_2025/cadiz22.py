# -*- coding: utf-8 -*-
"""Cádiz 2022: Semana Mayor (Canal Sur) no traía música hasta 2024, así que la
fuente son las siete guías por jornada de andaluciainformacion.es, que sí dan un
campo `Música:` por hermandad."""
import csv
import glob
import os
import re

from vocab import localiza

VOC = ['Servitas', 'Nazareno de la Obediencia', 'La Borriquita', 'Borriquita', 'Despojado',
       'Las Penas', 'Sagrada Cena', 'Humildad y Paciencia', 'Nazareno del Amor', 'La Palma',
       'Prendimiento', 'Vera+Cruz', 'Veracruz', 'Columna', 'Sanidad', 'Jesús Caído', 'Caído',
       'Ecce-Homo', 'Ecce Homo', 'Piedad', 'Las Aguas', 'Cigarreras', 'Sentencia',
       'El Caminito', 'Caminito', 'Angustias', 'Oración en el Huerto', 'Afligidos',
       'Nazareno de Santa María', 'El Nazareno', 'Medinaceli', 'El Perdón', 'Perdón',
       'Siete Palabras', 'Expiración', 'Descendimiento', 'Buena Muerte', 'Ecce-Mater-Tua',
       'Ecce Mater Tua', 'Santo Entierro', 'Resucitado', 'La Palma', 'Mayor Dolor']

CORTE = re.compile(r'(?i)\s+(?:Itinerario|ITINERARIO|Estrenos?|ESTRENOS?|Imagen|IMAGEN|'
                   r'Horarios?|Hermano mayor|Hermana mayor|Capataz|Capataces|'
                   r'N[ºo°] de penitentes|H[ÁA]BITO)')


def parse(directorio, anio):
    filas = []
    for f in sorted(glob.glob(os.path.join(directorio, '*.txt'))):
        dia = os.path.basename(f).replace('.txt', '')
        s = re.sub(r'\s+', ' ', open(f, encoding='utf-8').read())
        trozos = re.split(r'(?i)m[úu]sica\s*:', s)
        for i in range(1, len(trozos)):
            valor = CORTE.split(trozos[i])[0].strip(' .;')
            # la hermandad es el último rótulo conocido del texto que precede
            previos = localiza(trozos[i - 1][-1200:], VOC)
            filas.append([anio, dia, previos[-1] if previos else '?', valor])
    return filas


if __name__ == '__main__':
    filas = parse('cadiz22', '2022')
    with open('raw_cadiz_2022.csv', 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['ANIO', 'DIA', 'HERMANDAD', 'BANDA'])
        w.writerows(filas)
    print(len(filas), 'filas;', len({f[2] for f in filas}), 'hermandades')
    for f in filas:
        print(f'{f[1][:10]:11}|{f[2][:24]:26}| {f[3][:90]}')
