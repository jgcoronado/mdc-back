# -*- coding: utf-8 -*-
"""Alcance y normalización comunes a las 28 extracciones de 2022 a 2025.

Regla de alcance (la misma que se fijó para 2026, docs/acompanamientos-nomina-2026.md):
sólo cuentan bandas de cornetas y tambores (CCTT/BCT/CyT) y agrupaciones musicales
(AM). Nunca bandas de música, bandas municipales, sinfónicas, filarmónicas,
asociaciones músico-culturales, capillas, escolanías, coros, tríos ni silencio.
"""
import re, unicodedata

def sa(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn')

def clave(s):
    """clave_normalizada: minúsculas, sin acentos ni puntuación (formato de
    bandas_a_crear.csv / bandas_creadas.csv)."""
    return re.sub(r'\s+', ' ', re.sub(r'[^a-z0-9 ]', ' ', sa(s).lower())).strip()

FUERA = re.compile(
    r'no lleva|sin acompa|silencio|de capilla|capilla|escolan[íi]a|coro\b|coral|'
    r'ni[ñn]os cantores|tr[íi]o|quinteto|cuarteto|orfe[óo]n|grupo vocal|voces graves|'
    r'grupo de (viento|cuerda)|banda de m[úu]sica|banda municipal|municipal de|'
    r'banda sinf[óo]nica|sinf[óo]nica|filarm[óo]nica|banda de la legi[óo]n|legi[óo]n|'
    r'regulares|brigada|bripac|ej[ée]rcito|guardia|bomberos|polic[íi]a|cruz roja|'
    r'asociaci[óo]n mus|asociaci[óo]n m[úu]sico|agrupaci[óo]n m[úu]sico|amc\b|'
    r'unidad de m[úu]sica|banda y unidad|secci[óo]n musical|b\.?m\.?m?\b|liceo|'
    r'academia|tambores roncos|corneti?[íi]n|saeta|banda de guerra|fanfarria|'
    r'tercio|cabildo|banda de la oliva|conservatorio', re.I)

ES_AM = re.compile(r'^\s*(a\.?\s*m\.?\b|a\.?\s*m\.?c?\.?\s|agrupaci[óo]n\s+musical\b|'
                   r'agrupaci[óo]n\s+juvenil\b)', re.I)
ES_CCTT = re.compile(r'(b\.?\s*c\.?\s*t\.?\b|banda\s+de\s+cornetas|banda\s+cornetas|'
                     r'cornetas?\s+y\s+tambores|banda\s+de\s+cc\.?\s*y?\s*tt|'
                     r'banda\s+cc\.?tt|cc\.?\s*y\s*tt|c\.?c\.?t\.?t\.?\b|'
                     r'banda\s+de\s+corneta\b|banda\s+de\s+tambores\s+y\s+cornetas)', re.I)

def tipo(nombre):
    """Devuelve 'AM', 'CCTT' o None (fuera de alcance)."""
    n = ' ' + re.sub(r'\s+', ' ', nombre).strip() + ' '
    if ES_CCTT.search(n):
        return 'CCTT'
    if ES_AM.search(n):
        return 'AM'
    return None

# 'Tambores roncos de la Banda de Cornetas y Tambores X', 'Cuatro tambores de
# la Banda X': es un destacamento de la banda, no la banda tocando tras el paso.
DESTACAMENTO = re.compile(
    r'^\s*(?:tambores?\s+roncos?|'
    r'(?:un|dos|tres|cuatro|cinco|seis|siete|ocho|varios|unos|los|las)\s+tambores?\b|'
    r'corneti?[íi]n\b|escuadra\s+de|s[óo]lo\s+tambor)', re.I)


def en_alcance(nombre):
    if DESTACAMENTO.match(nombre.strip()):
        return False
    t = tipo(nombre)
    if t is None:
        return False
    # 'Agrupación Musical' gana a los descartes; 'Banda de Música ...' no es CCTT
    if t == 'CCTT' and re.search(r'banda\s+de\s+m[úu]sica|banda\s+municipal|'
                                r'banda\s+sinf|filarm', nombre, re.I):
        return False
    return True

EXPANDE = [
    (r'^\s*B\.?\s*C\.?\s*T\.?\s*', 'Banda de Cornetas y Tambores '),
    (r'^\s*C\.?C\.?T\.?T\.?\s*', 'Banda de Cornetas y Tambores '),
    (r'^\s*Banda\s+de\s+CC\.?\s*y\s*TT\.?\s*', 'Banda de Cornetas y Tambores '),
    (r'^\s*Banda\s+de\s+CCTT\.?\s*', 'Banda de Cornetas y Tambores '),
    (r'^\s*Banda\s+CCTT\.?\s*', 'Banda de Cornetas y Tambores '),
    (r'^\s*CC\.?\s*y\s*TT\.?\s*', 'Banda de Cornetas y Tambores '),
    (r'^\s*A\.?\s*M\.?\s*(?=[A-ZÁÉÍÓÚ])', 'Agrupación Musical '),
    (r'^\s*Banda\s+de\s+cornetas\s+y\s+tambores\s*', 'Banda de Cornetas y Tambores '),
    (r'^\s*Banda\s+de\s+Corneta\s+y\s+Tambores\s*', 'Banda de Cornetas y Tambores '),
    (r'^\s*Agrupaci[óo]n\s+musical\s*', 'Agrupación Musical '),
    (r'^\s*Banda\s+Cornetas\s+y\s+[Tt]ambores\s*', 'Banda de Cornetas y Tambores '),
]

CONECTORAS = {'de', 'del', 'la', 'el', 'los', 'las', 'y', 'e', 'en', 'con', 'por',
              'para', 'san', 'santa', 'santo'}
ESTILO = re.compile(r'banda\s+de\s+cornetas|agrupaci[óo]n\s+musical|'
                    r'\bB\.?\s?C\.?\s?T\.?\b|\bA\.?\s?M\.?\s', re.I)


def _es_prosa(cola):
    """¿La frase que sigue al punto es prosa de la ficha y no otro nombre?"""
    return any(p not in CONECTORAS for p in re.findall(r'\b[a-záéíóúñ]{3,}\b', cola))


def expande(nombre):
    n = re.sub(r'[«»“”‘’¨\'"´`]', '', nombre).strip()
    # las guías de Cádiz escriben el estilo en minúscula dentro de la frase
    # ('acompaña la banda de cornetas y tambores X')
    if n[:1].islower():
        n = n[0].upper() + n[1:]
    # prosa por delante ('Este año le acompañará la banda de cornetas y tambores X')
    m = ESTILO.search(n)
    if m and m.start() > 0:
        n = n[m.start():]
        n = n[0].upper() + n[1:]
    for pat, rep in EXPANDE:
        n2 = re.sub(pat, rep, n, count=1)
        if n2 != n:
            n = n2
            break
    for corto, largo in (('Ntro', 'Nuestro'), ('Ntra', 'Nuestra'), ('Stmo', 'Santísimo'),
                         ('Stma', 'Santísima'), ('Sta', 'Santa'), ('Sto', 'Santo'),
                         ('Sra', 'Señora'), ('Sres', 'Señores')):
        n = re.sub(rf'\b{corto}\.?(\s|$)', largo + r'\1', n)
    n = re.sub(r'\bM[ªa]\.?\s+Santísima', 'María Santísima', n)
    # cola de prosa que el PDF encadena detrás del nombre (minúscula suelta)
    n = re.sub(r'(\))\s+(?!y\b|de\b)\S.*$', r'\1', n)
    # ... o detrás de un punto, si lo que sigue es prosa de la ficha
    # ('... de Melilla. La Legión acompañará también al Señor de la Caridad')
    while True:
        m = re.search(r'\.\s+(?=[A-ZÁÉÍÓÚ¡])', n)
        if not m or not _es_prosa(n[m.end():]):
            break
        n = n[:m.start()]
    # cola corta en mayúscula tras el punto: es la localidad de la banda
    # ('Agrupación Musical Virgen de la Oliva. Vejer de la Frontera')
    n = re.sub(r'\.\s+((?!(?:La|El|Los|Las|Est[ae])\b)[A-ZÁÉÍÓÚ][^.()]{2,40})\s*$',
               r' (\1)', n)
    # a estas alturas las abreviaturas ya están expandidas: lo que siga a un
    # punto es prosa de la ficha ('... de Espinas. La Legión')
    n = re.split(r'\.\s+', n, maxsplit=1)[0]
    # ... o detrás del verbo, sin punto de por medio
    n = re.sub(r'\s+(?:acompa[ñn]a(?:r[áa]n?|ndo)?|ir[áa]n?|saldr[áa]n?|toca(?:r[áa]n?)?)'
               r'\s+(?:a|al|en|tras|con|por|delante)\b.*$', '', n, flags=re.I)
    n = re.sub(r'\s+(?:Hermano mayor|Hermana mayor|Capataces?|Costaleros|Nº|N\.º|'
               r'Detalles|Estrenos?|Tiempo de|Imaginer|Im[áa]genes|Reseña)\b.*$', '',
               n, flags=re.I)
    n = re.sub(r'\s+', ' ', n).strip(' .,;')
    # el PDF corta la línea dentro del paréntesis ('... del Rosario (Cádiz')
    if n.count('(') == n.count(')') + 1 and not n.endswith(')'):
        n += ')'
    return n
