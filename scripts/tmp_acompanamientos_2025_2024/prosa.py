# -*- coding: utf-8 -*-
"""Trocea las líneas de música en prosa ('X (cruz), Y (misterio) y Z (palio)')
en pares (rol, banda). Se usa en las vísperas de Sevilla y en Córdoba."""
import re

MARCAS = [
    (r'\(\s*cruz(?:\s+de\s+gu[íi]a)?\s*\)|en la [Cc]ruz de [Gg]u[íi]a|'
     r'\ben la cruz\b|\(cruz de gu[íi]a\)', 'Cruz de Guia'),
    (r'\(\s*misterio\s*\)|en el misterio|tras el misterio|\bel misterio\b', 'Paso de Misterio'),
    (r'\(\s*(?:cristo|se[ñn]or|nazareno)\s*\)|en el (?:Cristo|Se[ñn]or|Nazareno|Crucificado)|'
     r'tras el (?:Cristo|Se[ñn]or|paso del Se[ñn]or)|con el (?:Cristo|Se[ñn]or)|tras el paso',
     'Paso de Cristo'),
    (r'\(\s*(?:palio|virgen|dolorosa)\s*\)|en el palio|tras el palio|con el palio|'
     r'en la Virgen|tras la Virgen|para el palio|en el paso de palio', 'Paso de Palio'),
]

def trocea(texto, rol_por_defecto='Paso de Cristo'):
    """Devuelve [(rol, nombre_banda)] respetando el orden del texto."""
    marcas = []
    for pat, rol in MARCAS:
        for m in re.finditer(pat, texto, re.I):
            marcas.append((m.start(), m.end(), rol))
    marcas.sort()
    # descarta solapes
    limpio, fin = [], -1
    for a, b, rol in marcas:
        if a >= fin:
            limpio.append((a, b, rol))
            fin = b
    if not limpio:
        return [(rol_por_defecto, limpia_nombre(texto))]
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
    t = re.sub(r'^(?:y|e|la|el|los|las|de|del)\s+', '', t, flags=re.I)
    t = re.sub(r'\s+', ' ', t).strip(' .,;-')
    return t if len(t) > 3 else ''
