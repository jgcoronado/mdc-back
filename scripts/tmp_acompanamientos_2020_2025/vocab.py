# -*- coding: utf-8 -*-
"""Emparejar rótulos de hermandad en versales cuando el PDF los parte.

Los programas de Canal Sur de 2022 y 2023 salen de pypdf con el espaciado de
letras metido dentro de la palabra ('PRESENT ACIÓN', 'EL RESUCIT ADO') y con la
cabecera del día pegada al primer rótulo. Comparar sin espacios resuelve las dos
cosas de golpe.
"""
import re
import unicodedata


def sa(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn')


def compacta(s):
    return re.sub(r'[^A-Z0-9]', '', sa(s).upper())


def localiza(texto, vocabulario):
    """Devuelve los rótulos del vocabulario presentes en `texto`, en orden."""
    plano = compacta(texto)
    # posición en el texto compactado de cada rótulo, el más largo primero
    encontrados = []
    ocupado = [False] * len(plano)
    for rotulo in sorted(vocabulario, key=lambda x: -len(compacta(x))):
        clave = compacta(rotulo)
        if not clave:
            continue
        desde = 0
        while True:
            i = plano.find(clave, desde)
            if i < 0:
                break
            if not any(ocupado[i:i + len(clave)]):
                for j in range(i, i + len(clave)):
                    ocupado[j] = True
                encontrados.append((i, rotulo))
            desde = i + 1
    return [r for _, r in sorted(encontrados)]
