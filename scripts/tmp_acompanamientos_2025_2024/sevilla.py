# -*- coding: utf-8 -*-
"""El Llamador (Canal Sur, Sevilla) -> una fila por (hermandad, paso, banda)."""
import re, sys, unicodedata, csv

VOCAB = ['Bendición y Esperanza','Cristo de la Corona','Divino Perdón de Alcosa',
 'Dolores de Torreblanca','Dulce Nombre de Bellavista','El Amor','El Baratillo','El Buen Fin',
 'El Cachorro','El Calvario','El Carmen Doloroso','El Carmen','El Cerro del Águila',
 'El Cristo de Burgos','El Dulce Nombre','El Museo','El Santo Entierro','El Santo Ángel',
 'El Silencio','El Sol','El Valle','Gran Poder','Jesús Despojado','La Amargura','La Borriquita',
 'La Candelaria','La Carretería','La Cena','La Esperanza de Triana','La Espiga','La Estrella',
 'La Exaltación','La Hiniesta','La Lanzada','La Macarena','La Milagrosa','La Misión','La Mortaja',
 'La O','La Paz','La Quinta Angustia','La Redención','La Resurrección','La Sed',
 'La Soledad de San Buenaventura','La Soledad de San Lorenzo','La Soledad','La Trinidad',
 'Las Aguas','Las Cigarreras','Las Maravillas','Las Penas de San Vicente','Las Penas',
 'Las Siete Palabras','Los Estudiantes','Los Gitanos','Los Javieres','Los Negritos',
 'Los Panaderos','Los Servitas','Montesión','Montserrat','Padre Pío','Pasión y Muerte','Pasión',
 'Paz y Misericordia','Pino Montano','San Benito','San Bernardo','San Esteban','San Gonzalo',
 'San Isidoro','San Jerónimo','San José Obrero','San Pablo','San Roque','Santa Cruz',
 'Santa Genoveva','Santa Marta','Torreblanca','Vera Cruz']

DIAS = ['VIERNES DE DOLORES','SABADO DE PASION','DOMINGO DE RAMOS','LUNES SANTO','MARTES SANTO',
        'MIERCOLES SANTO','JUEVES SANTO','MADRUGADA','VIERNES SANTO','SABADO SANTO',
        'DOMINGO DE RESURRECCION']

def sa(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn')

def key(s):
    return re.sub(r'[^A-Z ]', ' ', sa(s).upper())

KEYS = sorted(((key(v), v) for v in VOCAB), key=lambda x: -len(x[0]))

def split_names(caps):
    txt = ' ' + re.sub(r'\s+', ' ', key(caps)).strip() + ' '
    found = []
    while True:
        best = None
        for k, v in KEYS:
            i = txt.find(' ' + k + ' ')
            if i >= 0 and (best is None or i < best[0]):
                best = (i, k, v)
        if not best:
            break
        i, k, v = best
        found.append((i, v))
        txt = txt[:i + 1] + ' ' * len(k) + txt[i + 1 + len(k):]
    return [v for _, v in sorted(found)]

def unwrap(txt):
    """Une los cortes de línea del PDF: guion de partición y continuación."""
    txt = re.sub(r'(\w)-\s*\n\s*(\w)', r'\1\2', txt)
    return re.sub(r'\s*\n\s*', ' ', txt).strip()

CAMPOS = (r'Sede:|Direcci[óo]n:|A[ñn]o fundaci[óo]n:|S[íi]ntesis hist[óo]rica:|Autor de las im|'
          r'Estrenos?:|Capataces?:|M[úu]sica(?: Cruz de Gu[íi]a)?:|De inter[ée]s:|P[áa]gina web:|'
          r'X: @|[PS]rimer [Pp]aso|Segundo [Pp]aso|Tercer [Pp]aso|Cuarto [Pp]aso|Quinto [Pp]aso|'
          r'\d[\d.]* ?(?:pasos?|nazarenos?|hermanos?|costaleros?|minutos?)|Cortejo:|Nazarenos:|'
          r'Hermano Mayor:|Tiempo de paso:')

ROLES = [(r'cruz de gu[íi]a|\(cruz\)|en la cruz', 'Cruz de Guia'),
         (r'misterio|cristo|se[ñn]or|\(cristo\)', 'Paso de Cristo'),
         (r'palio|virgen|dolorosa', 'Paso de Palio')]

def prosa(m):
    """'Banda A en la cruz de guía y Banda B en el misterio' -> [(rol, banda)]."""
    out, resto = [], m
    trozos = re.split(r'\s+y\s+|,\s*|;\s*|\.\s+', resto)
    for tr in trozos:
        tr = tr.strip(' .')
        if not tr:
            continue
        rol = None
        for pat, r in ROLES:
            if re.search(pat, tr, re.I):
                rol = r
                break
        nombre = re.sub(r'\(.*?\)', ' ', tr)
        nombre = re.sub(r'\b(en|tras|para|con|el|la|los|las)\b\s+(cruz de gu[íi]a|misterio|palio|'
                        r'cristo|se[ñn]or|virgen|dolorosa)\b.*$', '', nombre, flags=re.I)
        nombre = re.sub(r'\s+', ' ', nombre).strip(' .-')
        if nombre:
            out.append((rol, nombre))
    return out

def parse(path, anio):
    raw = open(path, encoding='utf-8').read()
    parts = re.split(r'\n===== PAG (\d+) =====\n', raw)
    it = iter(parts[1:])
    filas, dia = [], ''
    for num, body in zip(it, it):
        k = key(body)[:400]
        cand = [(k.find(d), d) for d in DIAS if d in k]
        if cand:
            dia = min(cand)[1]
        if 'Sede:' not in body:
            continue
        # los titulares van en versales; en 2024 aparecen dispersos por la página
        caps = []
        for ln in body.split('\n'):
            ln = ln.strip()
            if len(ln) > 2 and ln == ln.upper() and sum(c.isalpha() for c in ln) >= 3:
                k2 = key(ln)
                for d in DIAS:
                    k2 = k2.replace(d, ' ')
                if k2.strip():
                    caps.append(k2)
        nombres = split_names(' '.join(caps))
        fichas = re.split(r'(?m)^\s*Sede:', body)[1:]
        cuadra = len(fichas) == len(nombres)
        for idx, f in enumerate(fichas):
            herm = nombres[idx] if cuadra else '? ' + '/'.join(nombres)
            f = unwrap(f)
            campos = re.split(f'({CAMPOS})', f)
            paso, vistos = None, []
            for j in range(1, len(campos), 2):
                lab, val = campos[j], campos[j + 1]
                if re.match(r'(?i)(primer|segundo|tercer|cuarto|quinto) paso', lab):
                    paso = lab.title()
                elif re.match(r'M[úu]sica Cruz', lab):
                    vistos.append(('Cruz de Guia', val.strip(' .')))
                elif re.match(r'M[úu]sica:', lab):
                    val = val.strip(' .')
                    if paso:
                        vistos.append((paso, val))
                    else:
                        vistos.append(('(prosa)', val))
            for p, b in vistos:
                filas.append([anio, num, dia, herm, p, b])
    return filas

if __name__ == '__main__':
    anio = sys.argv[1]
    filas = parse(f'txt/sevilla_{anio}.txt', anio)
    with open(f'raw_sevilla_{anio}.csv', 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['ANIO', 'PAG', 'DIA', 'HERMANDAD', 'PASO', 'BANDA'])
        w.writerows(filas)
    print(len(filas), 'filas;', len({f[3] for f in filas}), 'hermandades')
