# -*- coding: utf-8 -*-
"""Convierte las extracciones en bruto (raw_*.csv) en los CSV de carga
`contratos_ss_<localidad>_<anio>.csv`, con el mismo formato y el mismo alcance
que la carga de 2026.

    python3 scripts/tmp_acompanamientos_2020_2025/construir_contratos.py

Alcance (docs/acompanamientos-nomina-2026.md): sólo CCTT y AM. Cualquier paso
acompañado por banda de música, capilla, escolanía, coro o silencio se descarta,
sea Cristo, Misterio, Palio o Cruz de Guía.

Convenios por localidad, heredados de lo ya cargado en la BD:
- Sevilla / Huelva / Cádiz / Granada / Málaga: TITULAR es la etiqueta genérica
  ('Cruz de Guia', 'Paso de Misterio', 'Paso de Cristo', 'Paso de Palio',
  'Paso de Virgen').
- Córdoba: TITULAR es el nombre corto del paso (cordoba_pasos_propuesta.csv).
- Jerez: TITULAR es el nombre de la hermandad, que es el marcador de posición
  que usó la carga de 2026; el titular real de la fuente va en NOTA.
"""
import csv
import os
import re
import sys

AQUI = os.path.dirname(os.path.abspath(__file__))
RAIZ = os.path.abspath(os.path.join(AQUI, '..', '..'))
sys.path.insert(0, AQUI)

from normalizar import clave, en_alcance, expande, sa          # noqa: E402
from prosa import trocea                                        # noqa: E402
from sevilla_bandas import DENTRO as SEV_BANDAS                 # noqa: E402
from vocab import compacta, localiza                            # noqa: E402


_INDICES: dict[int, dict[str, str]] = {}


def busca_rotulo(texto, mapa):
    """Traduce un rótulo de la fuente ignorando espacios, acentos y puntuación.

    pypdf parte las palabras de los programas de 2022 y 2023 ('LA BORRIQUIT A',
    'SANT A CENA', 'P ASIÓN'), así que la comparación literal falla. Si el
    rótulo trae dos hermandades pegadas, gana la primera que aparece.
    """
    idx = _INDICES.get(id(mapa))
    if idx is None:
        idx = {compacta(k): v for k, v in mapa.items()}
        _INDICES[id(mapa)] = idx
    directo = idx.get(compacta(texto))
    if directo is not None:
        return directo
    hits = localiza(texto, list(mapa))
    return mapa[hits[0]] if hits else None

CABECERA = ['ID_BANDA', 'BANDA', 'CLAVE_BANDA', 'HERMANDAD', 'TITULAR', 'ANIO',
            'FUENTE', 'NOTA', 'LOCALIDAD']


def raw(nombre):
    with open(os.path.join(AQUI, nombre), encoding='utf-8') as fh:
        return list(csv.DictReader(fh))


CRISTO = ('Paso de Misterio', 'Paso de Cristo')


def alinea_con_2026(localidad, filas):
    """Usa la etiqueta que ya tiene esa hermandad en 2026.

    Las fuentes de 2022-2025 no distinguen 'Paso de Misterio' de 'Paso de
    Cristo' con el mismo criterio que musicofrades, y si la etiqueta no coincide
    con la de 2026 la serie de ese paso se parte en dos en /acompanamientos.
    """
    ref = os.path.join(RAIZ, f'contratos_ss_{localidad}_2026.csv')
    if not os.path.exists(ref):
        return filas
    por_herm = {}
    with open(ref, encoding='utf-8') as fh:
        for r in csv.DictReader(fh):
            if r['TITULAR'] in CRISTO:
                por_herm.setdefault(sa(r['HERMANDAD']), set()).add(r['TITULAR'])
    salida = []
    for banda, herm, titular, nota in filas:
        opciones = por_herm.get(sa(herm), set())
        if titular in CRISTO and len(opciones) == 1:
            titular = next(iter(opciones))
        salida.append((banda, herm, titular, nota))
    return salida


COLA = re.compile(r'\s*[(,]\s*(?:despu[ée]s|antes|tras|desde|hasta|s[óo]lo|solo|durante|'
                  r'en el tramo|a partir de|ida|vuelta)\b[^)]*\)?\s*$', re.I)


def limpia_banda(nombre):
    """Quita las coletillas del programa que no son parte del nombre.

    Los programas de Málaga marcan con '*' las bandas que van sólo en un tramo
    y aclaran entre paréntesis cuándo ('(después del recorrido oficial)'). El
    tramo no se registra: la fila dice qué banda tocó tras ese paso ese año.
    """
    n = COLA.sub('', nombre)
    return re.sub(r'[\s.*]+$', '', n).strip()


def escribe(localidad, anio, filas, fuente):
    filas = [(limpia_banda(b), h, t, n) for b, h, t, n in filas]
    filas = dedup(alinea_con_2026(localidad, filas))
    destino = os.path.join(RAIZ, f'contratos_ss_{localidad}_{anio}.csv')
    with open(destino, 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh, quoting=csv.QUOTE_MINIMAL)
        w.writerow(CABECERA)
        for banda, herm, titular, nota in filas:
            w.writerow(['', banda, clave(banda), herm, titular, anio, fuente, nota,
                        LOCALIDAD_BD[localidad]])
    print(f'{os.path.basename(destino):34} {len(filas):>3} filas, '
          f'{len({f[0] for f in filas}):>3} bandas, '
          f'{len({f[1] for f in filas}):>3} hermandades')
    return filas


def dedup(filas):
    vistos, out = set(), []
    for f in filas:
        k = (f[0], f[1], f[2])
        if k not in vistos:
            vistos.add(k)
            out.append(f)
    return out


LOCALIDAD_BD = {
    'sevilla': 'Sevilla', 'huelva': 'Huelva', 'cadiz': 'Cadiz', 'granada': 'Granada',
    'malaga': 'Malaga', 'cordoba': 'Córdoba', 'jerez': 'Jerez de la Frontera',
}

# --------------------------------------------------------------------------- #
# Sevilla — El Llamador (Canal Sur)
# --------------------------------------------------------------------------- #

SEV_HERMANDAD = {
    'El Carmen': 'El Carmen Doloroso',
    'Torreblanca': 'Dolores de Torreblanca',
}

# El Llamador numera los pasos; qué es cada número depende de la hermandad.
SEV_PASOS = {
    None: {'Primer Paso': 'Paso de Misterio', 'Segundo Paso': None, 'Tercer Paso': None},
    'El Cerro del Aguila': {'Primer Paso': 'Paso del Nazareno',
                            'Segundo Paso': 'Paso de Misterio', 'Tercer Paso': None},
    'San Benito': {'Primer Paso': 'Paso de Misterio',
                   'Segundo Paso': 'Paso de Cristo', 'Tercer Paso': None},
    'Las Siete Palabras': {'Primer Paso': 'Paso de Misterio',
                           'Segundo Paso': 'Paso de Cristo', 'Tercer Paso': None},
    'La Cena': {'Primer Paso': 'Paso de Misterio',
                'Segundo Paso': 'Paso de Cristo', 'Tercer Paso': None},
    'La Trinidad': {'Primer Paso': 'Paso Alegorico',
                    'Segundo Paso': 'Paso de Misterio', 'Tercer Paso': None},
}

# Páginas que pypdf lee en un orden que el parser no puede desenredar: se
# corrigen a mano contra el texto de la propia página (ver README).
SEV_QUITAR = {
    # (anio, hermandad_raw, paso, banda_raw)
    ('2024', 'La Soledad', 'Segundo Paso', 'Las Tres Caídas'),
    ('2024', 'La Soledad', 'Tercer Paso', 'La Oliva de Salteras'),
    ('2025', 'La Soledad', 'Segundo Paso', 'Tres Caídas'),
    ('2025', 'La Soledad', 'Tercer Paso', 'La Oliva de Salteras'),
    ('2024', 'El Sol', 'Primer Paso', 'Soledad de Cantillana'),
    ('2024', 'El Sol', 'Segundo Paso', 'Banda Nuestra Señora del Sol'),
    ('2025', 'El Sol', 'Primer Paso', 'Soledad de Cantillana'),
    ('2024', 'Las Cigarreras', 'Segundo Paso', 'Las Cigarreras'),
    ('2024', '? Las Cigarreras/Montesión', 'Primer Paso', 'A.M. La Redención'),
}

# Filas que el parser no llega a construir (fichas sin 'Sede:' o repartidas
# entre dos páginas). Transcritas del texto del propio programa.
SEV_ANADIR = {
    '2024': [
        ('Banda de Cornetas y Tambores Sagrada Columna y Azotes', 'Montesion',
         'Cruz de Guia', ''),
        ('Banda de Cornetas y Tambores Nuestra Señora de la Victoria (Las Cigarreras)',
         'Montesion', 'Paso de Misterio', ''),
        ('Banda de Cornetas y Tambores Santísimo Cristo de las Tres Caídas de Triana',
         'La Trinidad', 'Paso de Misterio', ''),
        ('Banda de Cornetas y Tambores Santa María de Gracia de Carmona', 'La Trinidad',
         'Cruz de Guia', ''),
        ('Banda de Cornetas y Tambores Nuestra Señora de la Victoria (Las Cigarreras)',
         'La Trinidad', 'Paso Alegorico', ''),
        ('Banda de Cornetas y Tambores Nuestra Señora de la Victoria (Las Cigarreras)',
         'La Carreteria', 'Paso de Misterio', ''),
    ],
    '2025': [
        ('Banda de Cornetas y Tambores Sagrada Columna y Azotes', 'Montesion',
         'Cruz de Guia', ''),
        ('Banda de Cornetas y Tambores Nuestra Señora de la Victoria (Las Cigarreras)',
         'Montesion', 'Paso de Misterio', ''),
        ('Agrupación Musical Nuestro Padre Jesús de la Redención de Sevilla',
         'Las Cigarreras', 'Paso de Misterio', ''),
        ('Banda de Cornetas y Tambores Santísimo Cristo de las Tres Caídas de Triana',
         'La Trinidad', 'Paso de Misterio', ''),
        ('Banda de Cornetas y Tambores Santa María de Gracia de Carmona', 'La Trinidad',
         'Cruz de Guia', ''),
        ('Banda de Cornetas y Tambores Nuestra Señora de la Victoria (Las Cigarreras)',
         'La Trinidad', 'Paso Alegorico', ''),
        ('Banda de Cornetas y Tambores Juvenil Centuria Romana Macarena', 'La Macarena',
         'Cruz de Guia', ''),
        ('Banda de Cornetas y Tambores San Juan Evangelista de Sevilla',
         'La Esperanza de Triana', 'Cruz de Guia', ''),
        ('Agrupación Musical María Santísima de las Angustias Coronada de Sevilla',
         'Los Gitanos', 'Cruz de Guia', ''),
        ('Agrupación Musical Virgen de los Reyes', 'Divino Perdon de Alcosa',
         'Paso de Cristo', 'ida y vuelta con bandas distintas en el palio'),
        ('Agrupación Musical La Sentencia de Jerez de la Frontera',
         'Divino Perdon de Alcosa', 'Paso de Misterio', 'segundo cortejo'),
        ('Agrupación Musical María Santísima de las Angustias Coronada de Sevilla',
         'San Jose Obrero', 'Cruz de Guia', ''),
        ('Agrupación Musical Nuestro Padre Jesús de la Salud (Los Gitanos) de Sevilla',
         'San Jose Obrero', 'Paso de Cristo', ''),
    ],
    '2022': [
        # la ficha de La Sed trae dos 'Música:' sin etiqueta de paso: la primera
        # es la cruz de guía (la propia hermandad estrenaba banda ese año), la
        # segunda el misterio, y esa sí la coge el parser
        ('Banda de Cornetas y Tambores Cristo de la Sed', 'La Sed', 'Cruz de Guia',
         'banda de la propia hermandad, recién creada'),
    ],
}

SEV_FUERA = re.compile(
    r'banda de m[úu]sica|b\.?\s?m\b|banda municipal|municipal de|capilla|escolan|coral|'
    r'coro\b|ni[ñn]os cantores|voces graves|grupo vocal|no lleva|de capilla|filarm|'
    r'liceo|sinf[óo]nica|cuartel|ej[ée]rcito|cruz roja|juli[áa]n cerd[áa]n|maestro tejera|'
    r'oliva de salteras|carmen de salteras|nieves de olivares|santa ana|'
    r'soledad de cantillana|carmen de villalba|mairena del alcor|coria del r[íi]o|'
    r'puebla del r[íi]o|virgen del [áa]guila|f\. guerrero|g[óo]mez caminero|moguer|'
    r'aznalc[óo]llar|santa cecilia|sanl[úu]car la mayor|virgen de las angustias\s*,?\s*sanl|'
    r'quinteto|cuarteto|tr[íi]o de', re.I)


def desdobla(texto):
    """'Tres Caídas (ida) y la Salud de Córdoba (vuelta)' -> las dos bandas."""
    t = re.sub(r'\s*\(\s*(ida|vuelta|regreso|en el recorrido de ida)\s*\)', '|', texto,
               flags=re.I)
    t = re.sub(r'\s+en el recorrido de ida\s+y\s+', '|', t, flags=re.I)
    return [p.strip(' y.,') for p in t.split('|') if p.strip(' y.,')]


def sevilla_banda(texto):
    """Nombre completo de la banda, o None si está fuera de alcance.

    El descarte va por posición: en 'Banda de la Redención y Santa Ana de Dos
    Hermanas' la que está fuera de alcance es la segunda banda de la frase y no
    descalifica a la primera. En cambio 'La Soledad de Cantillana' empieza donde
    empieza el descarte, así que se va entera.
    """
    txt = re.sub(r'(\w)\s*-\s+(\w)', r'\1\2', texto)
    txt = re.sub(r'\bSta\.?\b', 'Santa', txt)
    k = clave(txt)
    dentro = None
    for patron, completo in SEV_BANDAS.items():
        i = k.find(patron)
        if i >= 0 and (dentro is None or i < dentro[0] or
                       (i == dentro[0] and len(patron) > dentro[2])):
            dentro = (i, completo, len(patron))
    if dentro is None:
        return None
    fuera = SEV_FUERA.search(k)
    if fuera and fuera.start() <= dentro[0]:
        return None
    return dentro[1]


def sevilla(anio):
    filas = []
    for r in raw(f'raw_sevilla_{anio}.csv'):
        herm_raw = r['HERMANDAD']
        if herm_raw.startswith('?'):
            if 'La Carretería' in herm_raw and r['PASO'] == 'Primer Paso':
                herm_raw = 'La Carretería'
            elif 'La Resurrección' in herm_raw:
                herm_raw = 'La Resurrección'
            else:
                continue
        if (anio, herm_raw, r['PASO'], r['BANDA']) in SEV_QUITAR:
            continue
        herm = SEV_HERMANDAD.get(herm_raw, herm_raw)
        herm = sa(herm)
        pares = []
        if r['PASO'] == '(prosa)':
            pares = trocea(r['BANDA'])
        elif r['PASO'] == 'Cruz de Guia':
            pares = [('Cruz de Guia', r['BANDA'])]
        else:
            mapa = SEV_PASOS.get(herm, SEV_PASOS[None])
            titular = mapa.get(r['PASO'])
            if titular:
                pares = [(titular, r['BANDA'])]
        for titular, texto in pares:
            for trozo in desdobla(texto):
                banda = sevilla_banda(trozo)
                if banda:
                    filas.append((banda, herm, titular, ''))
    filas += [(b, h, t, n) for b, h, t, n in SEV_ANADIR.get(anio, [])]
    return filas


# --------------------------------------------------------------------------- #
# Fuentes con el papel del paso escrito en el propio texto
# --------------------------------------------------------------------------- #

ROL = [
    (r'cruz\s*(de)?\s*gu[íi]a|abre calle|\(cruz\)', 'Cruz de Guia'),
    (r'misterio', 'Paso de Misterio'),
    (r'palio', 'Paso de Palio'),
    (r'virgen|dolorosa|mar[íi]a\s+s(an)?t(í|i)?s(i|í)?ma|nuestra\s+se[ñn]ora|ntra\.?\s*sra',
     'Paso de Virgen'),
    (r'cristo|se[ñn]or|nazareno|crucificado|yacente|ecce\s*homo|cautivo|santo\s+lignum',
     'Paso de Cristo'),
]


def rol_de(etiqueta, defecto='Paso de Cristo'):
    e = sa(etiqueta).lower()
    for patron, valor in ROL:
        if re.search(patron, e):
            return valor
    return defecto


def por_etiqueta(nombre_raw, localidad, anio, mapa_herm, col_paso='PASO',
                 defecto='Paso de Cristo', virgen_como='Paso de Virgen'):
    filas = []
    for r in raw(nombre_raw):
        herm = mapa_herm(r['HERMANDAD'])
        if not herm:
            continue
        titular = rol_de(r[col_paso], defecto)
        if titular == 'Paso de Virgen':
            titular = virgen_como
        for trozo in re.split(r'\s+y\s+(?=Banda|Agrupaci|A\.?M\.?\s|B\.?C\.?T)', r['BANDA']):
            if not en_alcance(trozo):
                continue
            filas.append((expande(trozo), herm, titular, ''))
    return filas


# --------------------------------------------------------------------------- #
# Granada — ahoragranada.com (la misma fuente que 2026)
# --------------------------------------------------------------------------- #

GRA_HERMANDAD = {
    'Borriquilla': 'Borriquilla', 'Santa Cena': 'Santa Cena', 'Maravillas': 'Maravillas',
    'Despojado': 'Despojado', 'Cautivo': 'Cautivo y Encarnacion',
    'Cautivo y Encarnación': 'Cautivo y Encarnacion', 'Huerto': 'Huerto',
    'Trabajo y Luz': 'Trabajo y Luz', 'Los Dolores': 'Los Dolores',
    'Rescate': 'El Rescate', 'El Rescate': 'El Rescate', 'San Agustín': 'San Agustin',
    'Lanzada': 'Lanzada', 'Vía Crucis': 'Via Crucis', 'Esperanza': 'Esperanza',
    'Cañilla': 'Canilla', 'Los Gitanos': 'Los Gitanos', 'Estudiantes': 'Estudiantes',
    'Los Estudiantes': 'Estudiantes', 'Paciencia y Penas': 'Paciencia y Penas',
    'Rosario': 'Rosario', 'NazarenoSeñor en silencio': 'Nazareno', 'Concha': 'Concha',
    'Salesianos': 'Salesianos', 'Aurora': 'Aurora', 'Estrella': 'Estrella',
    'Silencio': 'Silencio', 'Escolapios': 'Escolapios', 'Ferroviarios': 'Ferroviarios',
    'Favores': 'Los Favores', 'Los Favores': 'Los Favores',
    'Sepulcro': 'Santo Sepulcro', 'Santo Sepulcro': 'Santo Sepulcro',
    'Soledad de San Jerónimo': 'Soledad de San Jeronimo', 'Alhambra': 'Alhambra',
    'Facundillos': 'Facundillos', 'Resurrección y Triunfo': 'Resurreccion y Triunfo',
    'Resucitado y Alegría': 'Resucitado y Alegria',
    'La Borriquilla': 'Borriquilla', 'Las Maravillas': 'Maravillas',
    'La Lanzada': 'Lanzada', 'La Esperanza': 'Esperanza', 'La Concha': 'Concha',
    'El Huerto': 'Huerto', 'La Aurora': 'Aurora', 'La Estrella': 'Estrella',
    'Los Ferroviarios': 'Ferroviarios', 'Redención (Salesianos)': 'Salesianos',
    'Santa María de la Alhambra': 'Alhambra', 'Humildad': 'Canilla',
}


PROV = {'gr': 'de Granada', 'ja': 'de Jaén', 'al': 'de Almería', 'co': 'de Córdoba',
        'se': 'de Sevilla', 'ma': 'de Málaga', 'ca': 'de Cádiz', 'hu': 'de Huelva',
        'c.r.': 'de Ciudad Real'}


def granada(anio):
    filas = []
    for banda, herm, titular, nota in por_etiqueta(
            f'raw_granada_{anio}.csv', 'granada', anio,
            lambda h: GRA_HERMANDAD.get(h.strip()), defecto='Paso de Cristo'):
        b = banda.rstrip(' |')
        m = re.search(r'\(([A-Za-zÁÉÍÓÚáéíóú.]{2,3})\)\s*$', b)
        if m and sa(m.group(1)).lower() in PROV:
            b = b[:m.start()].strip()
            if not re.search(r'\bde\s+\w', b.split()[-2] if len(b.split()) > 1 else ''):
                b = f'{b} {PROV[sa(m.group(1)).lower()]}'
        filas.append((re.sub(r'\s+', ' ', b).strip(' |.'), herm, titular, nota))
    return filas


# --------------------------------------------------------------------------- #
# Málaga — malagamusical.blogspot.com (2024) y 101tv.es (2025)
# --------------------------------------------------------------------------- #

MAL_HERMANDAD = {
    'Pollinica': 'Pollinica', 'Lágrimas y Favores': 'Lágrimas y Favores',
    'Dulce Nombre': 'Dulce Nombre', 'Salutación': 'Salutación',
    'Humildad y Paciencia': 'Humildad y Paciencia', 'Humildad': 'Humidad',
    'Huerto': 'Huerto', 'Salud': 'Salud', 'Prendimiento': 'Prendimiento',
    'Crucifixión': 'Crucifixión', 'Pasión': 'Pasión', 'Gitanos': 'Columna',
    'Columna': 'Columna', 'Dolores del Puente': 'Dolores del Puente',
    'Cautivo': 'Cautivo', 'Estudiantes': 'Estudiantes', 'Rocío': 'Rocío',
    'Penas': 'Penas', 'Nueva Esperanza': 'Nueva Esperanza', 'Estrella': 'Estrella',
    'Humillación': 'Estrella', 'Rescate': 'Rescate', 'Sentencia': 'Sentencia',
    'Fusionadas': 'Fusionadas', 'Mediadora': 'Mediadora', 'Salesianos': 'Salesianos',
    'Sangre': 'Sangre', 'Rico': 'Rico', 'Paloma': 'Paloma', 'La Puente': 'Paloma',
    'Expiración': 'Expiración', 'Cena': 'Cena', 'Viñeros': 'Viñeros',
    'Vera Cruz': 'Vera+Cruz', 'Zamarrilla': 'Zamarrilla', 'Amor': 'Amor y Caridad',
    'Mena': 'Mena', 'Misericordia': 'Misericordia', 'Descendimiento': 'Descendimiento',
    'Dolores de San Juan': 'Dolores de Churriana', 'Monte Calvario': 'Monte Calvario',
    'Calvario': 'Monte Calvario', 'Santa Cruz': 'Santa Cruz', 'Piedad': 'Piedad',
    'Sepulcro': 'Sepulcro', 'Santo Sepulcro': 'Sepulcro', 'Servitas': 'Servitas',
    'Soledad de San Pablo': 'Santo Traslado', 'Resucitado': 'Resucitado',
    'Esperanza': 'Esperanza', 'Santo Traslado': 'Santo Traslado',
    'Dolores de San Juan': 'Dolores de San Juan', 'Gitanos': 'Columna',
}


def malaga(anio):
    return por_etiqueta(f'raw_malaga_{anio}.csv', 'malaga', anio,
                        lambda h: MAL_HERMANDAD.get(h.strip()),
                        defecto='Paso de Cristo')


# --------------------------------------------------------------------------- #
# Cádiz — Semana Mayor (2024) y semanasantacadiz.com (2025)
# --------------------------------------------------------------------------- #

CAD_HERMANDAD = {
    'SERVITAS': 'Servitas', 'Dolores Servita': 'Servitas',
    'NAZARENO DE LA': 'La Merced', 'Nazareno de la Obediencia': 'La Merced',
    'LA BORRIQUITA': 'La Borriquita', 'La Borriquita': 'La Borriquita',
    'LAS PENAS': 'Las Penas', 'Las Penas': 'Las Penas',
    'SAGRADA CENA': 'Sagrada Cena', 'Sagrada Cena': 'Sagrada Cena',
    'DESPOJADO': 'Despojado', 'Despojado': 'Despojado',
    'HIMILDAD Y PACIENCIA': 'Humildad y Paciencia',
    'Humildad y Paciencia': 'Humildad y Paciencia',
    'NAZARENO DEL AMOR': 'Nazareno del Amor', 'Nazareno del Amor': 'Nazareno del Amor',
    'LA PALMA': 'La Palma', 'La Palma': 'La Palma',
    'EL PRENDIMIENTO': 'Prendimiento', 'El Prendimiento': 'Prendimiento',
    'VERA+CRUZ': 'Veracruz', 'Veracruz': 'Veracruz',
    'SANIDAD': 'Sanidad', 'Sanidad': 'Sanidad',
    'PIEDAD': 'Piedad', 'Piedad': 'Piedad',
    'JESÚS CAÍDO': 'Caido', 'El Caido': 'Caido',
    'COLUMNA': 'Columna', 'Columna': 'Columna',
    'ECCE-HOMO': 'Ecce Homo', 'Ecce-Homo': 'Ecce Homo',
    'LA SENTENCIA': 'Sentencia', 'Sentencia': 'Sentencia',
    'LAS AGUAS': 'Las Aguas', 'Las Aguas': 'Las Aguas',
    'LAS CIGARRERAS': 'Las Cigarreras', 'Cigarreras': 'Las Cigarreras',
    'AFLIGIDOS': 'Afligidos', 'Afligidos': 'Afligidos',
    'EL NAZARENO': 'El Nazareno', 'Nazareno de Santa Maria': 'El Nazareno',
    'MEDINACELI': 'Medinaceli', 'Medinaceli': 'Medinaceli',
    'EL PERDÓN': 'El Perdon', 'El Perdon': 'El Perdon',
    'SIETE PALABRAS': 'Siete Palabras', 'Siete Palabras': 'Siete Palabras',
    'EXPIRACIÓN': 'Expiracion', 'Expiracion': 'Expiracion',
    'DESCENDIMIENTO': 'Descendimiento', 'Descendimiento': 'Descendimiento',
    'BUENA MUERTE': 'Buena Muerte', 'Buena Muerte': 'Buena Muerte',
    'SANTO ENTIERRO': 'Santo Entierro', 'Santo Entierro': 'Santo Entierro',
    'LA RESURRECCIÓN': 'El Resucitado', 'Resucitado': 'El Resucitado',
    'Oracion en el Huerto': 'La Oracion en el Huerto',
    'Oración en el Huerto': 'La Oracion en el Huerto',
    'Dolores Servitas': 'Servitas', 'Dolores Servita': 'Servitas',
    'El Nazareno': 'El Nazareno', 'El Perdón': 'El Perdon',
    'Expiración': 'Expiracion', 'Jesús Caído': 'Caido',
    'Prendimiento': 'Prendimiento', 'Ecce Homo': 'Ecce Homo',
    'Borriquita': 'La Borriquita',
    # Sin fila en la carga de 2026 (aquel año no llevaron CCTT/AM): se nombran
    # como las nombra la fuente para que el resolutor las dé de alta.
    'Angustias': 'El Caminito', 'Angustias (El Caminito)': 'El Caminito',
    'CAMINITO': 'El Caminito', 'Caminito': 'El Caminito',
    'Ecce-Mater-Tua': 'Ecce Mater Tua', 'Ecce Mater Tua': 'Ecce Mater Tua',
    'ECCE-MATER-TUA': 'Ecce Mater Tua',
}


CAD_BANDA_FIX = [
    ('agrupacion de la salud',
     'Agrupación Musical Nuestro Padre Jesús de la Salud de Cádiz'),
    ('asociacion musical encarnacion',
     'Agrupación Musical Nuestra Señora de la Encarnación (San Benito) de Sevilla'),
    ('banda de la humildad de huelva',
     'Banda de Cornetas y Tambores Nuestro Padre Jesús de la Humildad de Huelva'),
]


def cadiz(anio):
    filas = []
    fichero = f'raw_cadiz_{anio}.csv'
    for r in raw(fichero):
        herm = CAD_HERMANDAD.get(r['HERMANDAD'].strip())
        if not herm:
            continue
        if anio in ('2023', '2025'):
            titular = 'Paso de Misterio' if r['PASO'] == 'Misterio' else 'Paso de Palio'
            textos = [(titular, r['BANDA'])]
        else:
            textos = [('Paso de Palio' if rol in ('Paso de Palio', 'Paso de Virgen')
                       else 'Paso de Misterio', t)
                      for rol, t in trocea(r['BANDA'], 'Paso de Misterio')]
        for titular, texto in textos:
            for trozo in re.split(r'\s+y\s+(?=Banda|Agrupaci|AM |BCT|A\.M|B\.C)|'
                                 r',\s*(?=BCT|BM|AM |Banda|Agrupaci)', texto):
                arreglada = None
                for pista, completo in CAD_BANDA_FIX:
                    if pista in clave(trozo):
                        arreglada = completo
                        break
                if arreglada is not None:
                    filas.append((arreglada, herm, titular, 'estilo de banda confirmado con las fichas de 2023-2026'))
                elif en_alcance(trozo):
                    filas.append((expande(trozo), herm, titular, ''))
    return filas


# --------------------------------------------------------------------------- #
# Huelva — El Llamador de Huelva (2024) y Cruz de Guía (2025)
# --------------------------------------------------------------------------- #

HUE_HERMANDAD = {
    'EL PRADO': 'Hermandad del Prado', 'El Prado': 'Hermandad del Prado',
    'LA BORRIQUITA': 'La Borriquita', 'La Borriquita': 'La Borriquita',
    'SAGRADA CENA': 'La Cena', 'Sagrada Cena': 'La Cena',
    'MUTILAOS': 'Los Mutilados', 'Mutilados': 'Los Mutilados',
    'REDENCIÓN': 'La Redencion', 'Redención': 'La Redencion',
    'CAUTIVO': 'El Cautivo', 'Cautivo': 'El Cautivo',
    'PERDÓN': 'El Perdon', 'El Perdón': 'El Perdon',
    'TRES CAÍDAS': 'Las Tres Caidas', 'Tres Caídas': 'Las Tres Caidas',
    'CALVARIO': 'El Calvario', 'Calvario': 'El Calvario',
    'LANZADA': 'La Lanzada', 'SENTENCIA': 'La Salud',
    'ESTUDIANTES': 'Los Estudiantes', 'Los Estudiantes': 'Los Estudiantes',
    'PASIÓN': 'Pasion', 'EL PRENDIMIENTO': 'El Prendimiento',
    'El Prendimiento': 'El Prendimiento',
    'VICTORIA': 'La Victoria', 'Victoria': 'La Victoria',
    'ESPERANZA': 'La Esperanza', 'Esperanza': 'La Esperanza',
    'MISERICORDIA': 'La Misericordia',
    'ORACIÓN EN EL HUERTO': 'La Oracion en el Huerto',
    'Oración en el Huerto': 'La Oracion en el Huerto',
    'BUENA MUERTE': 'La Buena Muerte', 'Buena Muerte': 'La Buena Muerte',
    'JUDÍOS': 'Los Judios', 'Los Judios': 'Los Judios', 'Los Judíos': 'Los Judios',
    'NAZARENO': 'El Nazareno', 'Nazareno': 'El Nazareno',
    'DESCENDIMIENTO': 'El Descendimiento', 'Descendimiento': 'El Descendimiento',
    'SANTO ENTIERRO': 'El Santo Entierro', 'Santo Entierro': 'El Santo Entierro',
    'EL RESUCITADO': 'El Resucitado', 'El Resucitado': 'El Resucitado',
    'La Bendición': 'Asociacion Parroquial de la Bendicion',
    'La Fe': 'La Fe', 'Los Dolores': 'Los Dolores', 'La Soledad': 'La Soledad',
    'Santa Cruz': 'Santa Cruz', 'VeraCruz': 'La Vera Cruz',
    'FE': 'La Fe', 'LOS DOLORES': 'Los Dolores', 'SANTA CRUZ': 'Santa Cruz',
    'SOLEDAD': 'La Soledad', 'VERA+CRUZ': 'La Vera Cruz',
    'BENDICIÓN': 'Asociacion Parroquial de la Bendicion',
}

# El programa Cruz de Guía de 2025 pone dos hermandades en el mismo rótulo de
# horarios; se reparten a mano por el titular de cada ficha.
HUE_2025_PAG = {'28': 'La Salud', '29': 'La Lanzada', '32': 'Los Estudiantes',
                '33': 'Pasion', '50': 'La Oracion en el Huerto', '51': 'La Misericordia'}


def huelva(anio):
    """En Huelva los palios van siempre con banda de música; toda fila CCTT/AM
    que sobrevive al filtro es de un paso de Cristo o de la cruz de guía."""
    filas = []
    for r in raw(f'raw_huelva_{anio}.csv'):
        if anio == '2025' and r['PAG'] in HUE_2025_PAG:
            herm = HUE_2025_PAG[r['PAG']]
        else:
            herm = busca_rotulo(r['HERMANDAD'].strip(), HUE_HERMANDAD)
        if not herm:
            continue
        texto = r['BANDA']
        pares = trocea(texto, 'Paso de Misterio') if re.search(
            r'\(|misterio|palio|cristo|cruz', texto, re.I) else [('Paso de Misterio', texto)]
        for titular, trozo in pares:
            for banda in re.split(r'\s+y\s+(?=Banda|Agrupaci|AM |BCT)', trozo):
                if not en_alcance(banda):
                    continue
                if titular != 'Cruz de Guia':
                    titular = 'Paso de Misterio'
                filas.append((expande(banda), herm, titular, ''))
    return filas


# --------------------------------------------------------------------------- #
# Jerez — Estación de Penitencia (Canal Sur)
# --------------------------------------------------------------------------- #

JER_HERMANDAD = {
    'LA BORRIQUITA': 'La Borriquita', 'LA CORONACIÓN': 'La Coronación',
    'EL PERDÓN': 'El Perdón', 'PASIÓN': 'Pasión', 'EL TRANSPORTE': 'El Transporte',
    'LA CANDELARIA': 'La Candelaria', 'LA SAGRADA CENA': 'Sagrada Cena',
    'SANTA CENA': 'Sagrada Cena', 'LA CLEMENCIA': 'La Clemencia',
    'LA DEFENSIÓN': 'La Defensión', 'EL AMOR': 'El Amor',
    'EL CRISTO DEL AMOR': 'El Amor', 'LA SALVACIÓN': 'La Salvación',
    'EL DESCONSUELO': 'Mayor Dolor', 'EL SOBERANO PODER': 'Soberano Poder',
    'EL CONSUELO': 'El Consuelo', 'LAS TRES CAÍDAS': 'Las Tres Caídas',
    'LA AMARGURA': 'La Flagelación', 'LA FLAGELACIÓN': 'La Flagelación',
    'EL PRENDIMIENTO': 'Prendimiento', 'LA VERA CRUZ': 'Vera-Cruz',
    'LA REDENCIÓN': 'La Redención', 'LA ORACIÓN EN EL HUERTO': 'Oración en el Huerto',
    'LA SAGRADA LANZADA': 'La Lanzada', 'LA LANZADA': 'La Lanzada',
    'HUMILDAD Y PACIENCIA': 'Humildad de Barbadillo',
    'EL MAYOR DOLOR': 'Mayor Dolor', 'NTRA. SRA. DEL MAYOR DOLOR': 'Mayor Dolor',
    'EL NAZARENO': 'Nazareno', 'NTRO. PADRE JESÚS NAZARENO': 'Nazareno',
    'LA YEDRA': 'La Yedra', 'LA MISIÓN': 'Misión',
    'LAS VIÑAS': 'Las Viñas', 'EL CRISTO': 'El Cristo', 'LA SOLEDAD': 'La Soledad',
    'CRISTO DE LA EXPIRACIÓN': 'El Cristo', 'LA SED': 'La Sed',
    'LA PAZ DE FÁTIMA': 'La Paz de Fátima', 'AMOR Y SACRIFICIO': 'Amor y Sacrificio',
    'CRISTO DE LA VIGA': 'La Viga', 'LA VIGA': 'La Viga',
    'BONDAD Y MISERICORDIA': 'Bondad y Misericordia',
    'SALUD DE SAN RAFAEL': 'Salud de San Rafael',
    'LOS JUDÍOS DE SAN MATEO': 'Judíos de San Mateo',
    'SACRAMENTAL DE SANTIAGO': 'Sacramental de Santiago',
    'LA SACRAMENTAL DE SANTIAGO': 'Sacramental de Santiago',
    'SANTA MARTA': 'Santa Marta', 'LA MORTAJA': 'La Mortaja',
    'LA SAGRADA MORTAJA': 'La Mortaja', 'LA EXALTACIÓN': 'La Exaltación',
    'EL SANTO CRUCIFIJO': 'El Santo Crucifijo', 'JEREZ': 'Entrega de Guadalcacín',
    'LAS CINCO LLAGAS': 'Las Cinco Llagas', 'EL SILENCIO': 'El Silencio',
    'LAS ANGUSTIAS': 'Las Angustias', 'DEFENSIÓN': 'La Defensión',
    'LA BUENA MUERTE': 'Buena Muerte', 'LOS JUDIOS': 'Judíos de San Mateo',
    'EL LORETO': 'El Loreto', 'LA ENTREGA': 'Entrega de Guadalcacín',
    'LA MORTAJA': 'La Mortaja', 'MORTAJA': 'La Mortaja', 'PASIÓN': 'Pasión',
}

JER_VIRGEN = re.compile(r'(mar[íi]a|virgen|ntra\.?\s*sra|nuestra\s+se[ñn]ora|dolorosa|'
                        r'madre\s+de\s+dios|santa\s+mar[íi]a|reina)', re.I)


def jerez(anio):
    filas = []
    for r in raw(f'raw_jerez_{anio}.csv'):
        herm = busca_rotulo(r['HERMANDAD'].strip(), JER_HERMANDAD)
        if not herm:
            continue
        titular_fuente = r['TITULAR'].strip()
        if re.search(r'RESURRECCI|RESUCITADO', sa(titular_fuente).upper()):
            herm = 'Resucitado'
        if JER_VIRGEN.search(titular_fuente) and not re.search(
                r'cristo|se[ñn]or|nazareno|jes[úu]s|crucifijo|lignum', titular_fuente, re.I):
            continue     # paso de palio: fuera del sitio
        # el programa de 2022 sale de pypdf con las dos columnas entremezcladas y
        # hay fichas cuyo titular no se puede emparejar: se quedan sin nota
        nota = (f'titular real: {titular_fuente.title()}'
                if titular_fuente not in ('', '?') else '')
        for trozo in re.split(r'\s+y\s+(?=Banda|Agrupaci|A\.?M\.?\s|B\.?C\.?T)', r['BANDA']):
            if not en_alcance(trozo):
                continue
            filas.append((expande(trozo), herm, herm, nota))
    return filas


# --------------------------------------------------------------------------- #
# Córdoba — Paso a Paso (2024) y gentedepaz.es (2025)
# --------------------------------------------------------------------------- #

COR_HERMANDAD = {
    'PRO-HERMANDAD DE LA BONDAD': 'Bondad', 'PRO-HERMANDAD DE LA SALUD': 'Salud',
    'PRESENTACIÓN': 'Presentación al Pueblo', 'HERMANDAD DE LA O': 'La O',
    'AURORA': 'Lágrimas', 'HERMANDAD SANTÍSIMO CRISTO DE LA SANGRE': 'Sangre',
    'LAS PENAS DE SANTIAGO': 'Penas de Santiago', 'EL RESCATADO': 'Rescatado',
    'VERA-CRUZ': 'Vera Cruz', 'LA ESPERANZA': 'Esperanza', 'EL AMOR': 'Amor',
    'EL HUERTO': 'Huerto', 'LA ESTRELLA': 'Redención', 'LA MERCED': 'Merced',
    'LA SENTENCIA': 'Sentencia', 'LA AGONÍA': 'Agonía', 'LA SANGRE': 'Sangre',
    'EL BUEN SUCESO': 'Buen Suceso', 'LA SANTA FAZ': 'Santa Faz',
    'EL PRENDIMIENTO': 'Prendimiento', 'EL PERDÓN': 'Perdón',
    'EL CALVARIO REAL PARROQUIA DE SAN LORENZO MÁRTIR': 'Calvario',
    'LA PAZ': 'Paz y Esperanza', 'LA MISERICORDIA': 'Misericordia',
    'LA PASIÓN': 'Pasión', 'LA PIEDAD DE LAS PALMERAS': 'Piedad',
    'LA CARIDAD': 'Caridad', 'EL CAÍDO': 'Caído', 'LA SAGRADA CENA': 'Sagrada Cena',
    'CRISTO DE GRACIA': 'Cristo de Gracia', 'EL DESCENDIMIENTO': 'Descendimiento',
    'LA CONVERSIÓN': 'Conversión', 'LOS DOLORES': 'Dolores',
    'EL RESUCITADO': 'Resucitado', 'FUENSANTA': 'Quinta Angustia',
    'ENTRADA TRIUNFAL': 'Entrada Triunfal',
    'ENTRADA TRIUNFAL (LA BORRIQUITA)': 'Entrada Triunfal',
    'COFRADÍA UNIVERSITARIA': 'Universitaria', 'EL CALVARIO': 'Calvario',
    'EL NAZARENO': 'Nazareno', 'EL SANTO SEPULCRO': 'Sepulcro',
    'HDAD. DEL STMO. CRISTO DE LAS LÁGRIMAS': 'Lágrimas',
    'HERMANDAD QUINTA ANGUSTIA': 'Quinta Angustia',
    'LA BUENA MUERTE': 'Buena Muerte', 'LA EXPIRACIÓN': 'Expiración',
    'LA SOLEDAD': 'Soledad', 'LAS ANGUSTIAS': 'Angustias',
    'PRO-HERMANDAD TRASLADO AL SEPULCRO': 'Traslado al Sepulcro',
    'VÍA CRUCIS': 'Vía Crucis', 'ÁNIMAS': 'Ánimas',
    'PRO-HERMANDAD DE LA O': 'La O', 'LA PROVIDENCIA': 'Providencia',
    'EL BUEN SUCESO': 'Buen Suceso', 'LA SANTA FAZ': 'Santa Faz',
}

COR_PASO = {
    'Entrada Triunfal': 'El Señor de los Reyes', 'Penas de Santiago': 'Cristo de las Penas',
    'Huerto': 'La Oración en el Huerto', 'Rescatado': 'El Rescatado',
    'Vera Cruz': 'Cristo del Amor', 'Esperanza': 'Jesús de las Penas',
    'Amor': 'Jesús del Silencio', 'Merced': 'Jesús Humilde',
    'Presentación al Pueblo': 'Jesús de los Afligidos', 'Redención': 'La Redención',
    'Sentencia': 'La Sentencia', 'Agonía': 'Cristo de la Agonía',
    'Sangre': 'Jesús de la Sangre', 'Buen Suceso': 'Jesús del Buen Suceso',
    'Santa Faz': 'La Santa Faz', 'Prendimiento': 'El Prendimiento',
    'Perdón': 'El Perdón', 'Paz y Esperanza': 'Jesús de la Humildad y Paciencia',
    'Calvario': 'El Calvario', 'Misericordia': 'Cristo de la Misericordia',
    'Pasión': 'Jesús de la Pasión', 'Piedad': 'Cristo de la Piedad',
    'Caridad': 'Cristo de la Caridad', 'Caído': 'Jesús Caído',
    'Sagrada Cena': 'Jesús de la Fe', 'Cristo de Gracia': 'Cristo de Gracia',
    'Expiración': 'Cristo de la Expiración', 'Descendimiento': 'Cristo del Descendimiento',
    'Conversión': 'Cristo de la Conversión', 'Dolores': 'Cristo de la Clemencia',
    'Resucitado': 'El Resucitado',
}
# Pasos de Cristo que la nómina de 2026 no llegó a nombrar: se dejan con la
# etiqueta genérica para no inventar un titular.
# Las dos hermandades cordobesas con DOS pasos de Cristo: cuál es cada uno se
# decide por lo que dice el propio trozo de texto de esa banda, no por la ficha
# entera (si no, las dos filas se llevarían el mismo titular).
COR_PASO_2 = {('Huerto', 'columna'): 'El Señor Amarrado a la Columna',
              ('Amor', 'amor'): 'Cristo del Amor',
              ('Amor', 'silencio'): 'Jesús del Silencio'}


def cordoba(anio):
    filas = []
    for r in raw(f'raw_cordoba_{anio}.csv'):
        herm = r['HERMANDAD'].strip()
        if anio != '2025':
            herm = busca_rotulo(herm, COR_HERMANDAD) or herm
        texto = r['BANDA']
        pares = trocea(texto, 'Paso de Cristo')
        # 'Paso a Paso' encadena dos bandas en frases distintas
        pares = [(rol, t) for rol, texto_rol in pares
                 for t in re.split(r'\.\s+(?=Banda|Agrupaci|A\.?M\.?\s|B\.?C\.?T)', texto_rol)]
        for rol, trozo in pares:
            if rol in ('Paso de Palio', 'Paso de Virgen'):
                continue
            if not en_alcance(trozo):
                continue
            titular = COR_PASO.get(herm)
            for (h, pista), nombre in COR_PASO_2.items():
                if h == herm and pista in sa(trozo).lower():
                    titular = nombre
            if not titular:
                continue
            filas.append((expande(trozo), herm, titular, ''))
    return filas


# --------------------------------------------------------------------------- #

ANIOS = ('2022', '2023', '2024', '2025')

FUENTES = {
    ('sevilla', '2022'): 'El Llamador (Canal Sur) Sevilla 2022',
    ('sevilla', '2023'): 'El Llamador (Canal Sur) Sevilla 2023',
    ('huelva', '2022'): 'El Llamador de Huelva (Canal Sur) 2022',
    ('huelva', '2023'): 'El Llamador de Huelva (Canal Sur) 2023',
    ('cadiz', '2022'): 'andaluciainformacion.es (guias por jornada)',
    ('cadiz', '2023'): 'semanasantacadiz.com',
    ('granada', '2022'): 'ahoragranada.com',
    ('granada', '2023'): 'ahoragranada.com',
    ('malaga', '2022'): 'malagamusical.blogspot.com',
    ('malaga', '2023'): 'malagamusical.blogspot.com',
    ('cordoba', '2022'): 'Paso a Paso (Canal Sur) Cordoba 2022',
    ('cordoba', '2023'): 'Paso a Paso (Canal Sur) Cordoba 2023',
    ('jerez', '2022'): 'Estacion de Penitencia (Canal Sur) Jerez 2022',
    ('jerez', '2023'): 'Estacion de Penitencia (Canal Sur) Jerez 2023',
    ('sevilla', '2024'): 'El Llamador (Canal Sur) Sevilla 2024',
    ('sevilla', '2025'): 'El Llamador (Canal Sur) Sevilla 2025',
    ('huelva', '2024'): 'El Llamador de Huelva (Canal Sur) 2024',
    ('huelva', '2025'): 'Cruz de Guia (programa de mano) Huelva 2025',
    ('cadiz', '2024'): 'Semana Mayor (Canal Sur) Cadiz 2024',
    ('cadiz', '2025'): 'semanasantacadiz.com',
    ('granada', '2024'): 'ahoragranada.com',
    ('granada', '2025'): 'ahoragranada.com',
    ('malaga', '2024'): 'malagamusical.blogspot.com',
    ('malaga', '2025'): '101tv.es',
    ('cordoba', '2024'): 'Paso a Paso (Canal Sur) Cordoba 2024',
    ('cordoba', '2025'): 'gentedepaz.es',
    ('jerez', '2024'): 'Estacion de Penitencia (Canal Sur) Jerez 2024',
    ('jerez', '2025'): 'Estacion de Penitencia (Canal Sur) Jerez 2025',
}

CONSTRUCTORES = {'sevilla': sevilla, 'granada': granada, 'malaga': malaga,
                 'cadiz': cadiz, 'huelva': huelva, 'jerez': jerez, 'cordoba': cordoba}

if __name__ == '__main__':
    total = 0
    for localidad, fn in CONSTRUCTORES.items():
        for anio in ANIOS:
            filas = escribe(localidad, anio, fn(anio), FUENTES[(localidad, anio)])
            total += len(filas)
    print(f'\nTOTAL {total} filas')
