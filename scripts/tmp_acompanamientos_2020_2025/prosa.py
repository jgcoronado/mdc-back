# -*- coding: utf-8 -*-
"""Trocea las líneas de música en prosa ('X (cruz), Y (misterio) y Z (palio)')
en pares (rol, banda). Se usa en las vísperas de Sevilla, en Córdoba y en las
guías por jornada de Cádiz de 2022, que lo escriben todo en frase ('La banda de
cornetas y tambores X acompaña al paso del misterio; y la Filarmónica Y, a la
Virgen')."""
import re

# verbo + preposición + artículo + 'paso de(l)' opcionales delante del papel
_V = (r'(?:acompa[ñn]a(?:r[áa]n?|ndo|dos?|das?)?|ir[áa]n?|van?|va|ser[áa]n?|'
      r'lleva(?:r[áa]n?)?|toca(?:r[áa]n?)?|figura(?:r[áa]n?)?)?\s*')
_P = r'\b(?:en|tras|con|para|ante|delante de|detr[áa]s de|al|a)\s+'
_A = r'(?:el|la|los|las)?\s*'
_D = r'(?:paso\s+(?:de|del)\s+)?'


def _marca(papel):
    return rf'{_V}{_P}{_A}{_D}(?:{papel})\b'


# 'El misterio irá acompañado por...', '... y el palio, por la banda de...':
# el papel encabeza la frase, sin preposición delante. Sólo para 'misterio' y
# 'palio', que nunca son parte del nombre de una banda.
_SUELTA = r'(?:^|(?<=[;:,.])\s*|\s+y\s+)(?:el\s+)?(?:paso\s+de(?:l)?\s+)?'


MARCAS = [
    (r'\(\s*cruz(?:\s+de\s+gu[íi]a)?\s*\)|' + _marca(r'cruz\s+de\s+gu[íi]a|cruz'),
     'Cruz de Guia'),
    (r'\(\s*misterio\s*\)|' + _marca('misterio') + '|' + _SUELTA + r'misterio\b',
     'Paso de Misterio'),
    (r'\(\s*(?:cristo|se[ñn]or|nazareno)\s*\)|' +
     _marca(r'cristo|se[ñn]or|nazareno|crucificado') + r'|tras el paso\b',
     'Paso de Cristo'),
    (r'\(\s*(?:palio|virgen|dolorosa)\s*\)|' + _marca(r'palio|virgen|dolorosa') +
     '|' + _SUELTA + r'palio\b', 'Paso de Palio'),
]

# arranques de frase que quedan pegados al nombre de la banda cuando la marca
# del papel va delante ('Acompañando al misterio irá la Agrupación Musical X')
CONECTOR = re.compile(r'^(?:y|e|o|que|por|con|de|del|la|el|los|las|un|una|'
                      r'ir[áa]n?|van?|va|ser[áa]n?|lleva(?:r[áa]n?)?|toca(?:r[áa]n?)?|'
                      r'acompa[ñn]a(?:r[áa]n?|do|da|ndo)?|interpretar[áa]n?)\s+', re.I)


def trocea(texto, rol_por_defecto='Paso de Cristo'):
    """Devuelve [(rol, nombre_banda)] respetando el orden del texto."""
    marcas = []
    for pat, rol in MARCAS:
        for m in re.finditer(pat, texto, re.I):
            marcas.append((m.start(), m.end(), rol))
    # a igual posición gana la marca más larga ('tras el paso de la Virgen'
    # antes que 'tras el paso'), que es la que trae el rol correcto
    marcas.sort(key=lambda m: (m[0], -m[1]))
    # descarta solapes
    limpio, fin = [], -1
    for a, b, rol in marcas:
        if a >= fin:
            limpio.append((a, b, rol))
            fin = b
    if not limpio:
        return [(rol_por_defecto, limpia_nombre(texto))]
    if limpio[0][0] <= 2:
        # la frase empieza por el papel ('El misterio irá acompañado por X'):
        # aquí el nombre va detrás de cada marca, no delante
        salida = []
        for i, (a, b, rol) in enumerate(limpio):
            fin = limpio[i + 1][0] if i + 1 < len(limpio) else len(texto)
            nombre = limpia_nombre(texto[b:fin])
            if nombre:
                salida.append((rol, nombre))
        return salida
    salida, ini = [], 0
    for a, b, rol in limpio:
        nombre = limpia_nombre(texto[ini:a])
        if nombre:
            salida.append((rol, nombre))
        ini = b
    resto = limpia_nombre(texto[ini:])
    if resto:
        salida.append((rol_por_defecto, resto))
    return salida


def limpia_nombre(t):
    t = re.sub(r'^[\s,;./y]+|[\s,;./]+$', '', t)
    t = t.split(';')[0]        # 'AM X; por su parte, el palio...' -> 'AM X'
    anterior = None
    while anterior != t:
        anterior = t
        t = CONECTOR.sub('', t, count=1)
    t = re.sub(r'\s+', ' ', t).strip(' .,;-')
    return t if len(t) > 3 else ''
