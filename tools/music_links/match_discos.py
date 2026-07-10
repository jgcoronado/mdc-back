#!/usr/bin/env python3
"""
Fase 1: enlazado de DISCOS con servicios de streaming.
Alcance: bandas activas de Sevilla capital (LOCALIDAD='Sevilla' AND PROVINCIA='Sevilla', sin FECHA_EXT).
Servicios: Spotify (creds en .env), Deezer e iTunes/Apple (sin credenciales).

Escribe candidatos a la tabla enlace_candidato (ESTADO='pendiente') para curación
en el panel admin. NO toca enlace_streaming (eso es la aprobación). Además deja un
CSV de apoyo. Re-ejecutable: reemplaza candidatos 'pendiente' de este alcance.
"""
import sqlite3, urllib.request, urllib.parse, json, csv, time, sys, re, unicodedata, os, base64, datetime

ROOT = r'C:/Users/usuario/Documents/mysql-simple'
DB = ROOT + '/php/data/mdc.db'
ENV = ROOT + '/.env'
OUT = os.path.join(os.path.dirname(__file__), 'candidatos_discos_sevilla.csv')
RUN_ID = 'discos-sevilla-' + datetime.datetime.now().strftime('%Y%m%d-%H%M%S')

def load_env():
    env = {}
    if os.path.exists(ENV):
        for line in open(ENV, encoding='utf-8'):
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            k, v = line.split('=', 1)
            env[k.strip()] = v.strip().strip('\'"')
    return env

def db():
    c = sqlite3.connect(DB)
    c.text_factory = lambda b: b.decode('latin-1')  # la BD guarda texto en latin-1
    return c

def norm(s):
    if not s: return ''
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode('ascii').lower()
    s = re.sub(r'[^a-z0-9 ]', ' ', s)
    return re.sub(r'\s+', ' ', s).strip()

STOP = {'de','la','el','los','las','y','del','en','a','banda','cornetas','tambores',
        'agrupacion','musical','am','bct','bcct','cc','tt','cctt','ntra','sra','nuestra',
        'senora','sevilla','de la'}
def toks(s):
    return {t for t in norm(s).split() if t and t not in STOP}

def jacc(a, b):
    A, B = toks(a), toks(b)
    if not A or not B: return 0.0
    return len(A & B) / len(A | B)

def get(url, headers=None):
    req = urllib.request.Request(url, headers=headers or {'User-Agent': 'Mozilla/5.0'})
    return json.load(urllib.request.urlopen(req, timeout=20))

# ---------- servicios ----------
def deezer_albums(banda):
    q = urllib.parse.quote(f'artist:"{banda}"')
    try:
        d = get(f'https://api.deezer.com/search/album?q={q}&limit=50')
    except Exception:
        return []
    return [{'servicio':'deezer','album':a.get('title'),'artist':a.get('artist',{}).get('name'),
             'url':a.get('link') or f"https://www.deezer.com/album/{a.get('id')}",
             'id_ext':str(a.get('id')),'year':None} for a in d.get('data', [])]

def itunes_albums(banda):
    q = urllib.parse.quote(banda)
    try:
        d = get(f'https://itunes.apple.com/search?term={q}&entity=album&limit=50&country=ES')
    except Exception:
        return []
    out = []
    for r in d.get('results', []):
        out.append({'servicio':'apple','album':r.get('collectionName'),'artist':r.get('artistName'),
                    'url':r.get('collectionViewUrl'),'id_ext':str(r.get('collectionId')),
                    'year':(r.get('releaseDate') or '')[:4]})
    return out

_SP_TOKEN = None
def spotify_token(env):
    global _SP_TOKEN
    if _SP_TOKEN: return _SP_TOKEN
    cid, cs = env.get('SPOTIFY_CLIENT_ID'), env.get('SPOTIFY_CLIENT_SECRET')
    if not cid or not cs: return None
    data = urllib.parse.urlencode({'grant_type':'client_credentials'}).encode()
    h = {'Authorization':'Basic '+base64.b64encode(f'{cid}:{cs}'.encode()).decode()}
    req = urllib.request.Request('https://accounts.spotify.com/api/token', data=data, headers=h)
    _SP_TOKEN = json.load(urllib.request.urlopen(req, timeout=20))['access_token']
    return _SP_TOKEN

def spotify_albums(banda, env):
    tk = spotify_token(env)
    if not tk: return []
    u = 'https://api.spotify.com/v1/search?' + urllib.parse.urlencode(
        {'q': banda, 'type': 'album', 'limit': 20, 'market': 'ES'})
    try:
        d = get(u, headers={'Authorization': 'Bearer ' + tk})
    except Exception:
        return []
    out = []
    for a in d.get('albums', {}).get('items', []):
        out.append({'servicio':'spotify','album':a.get('name'),
                    'artist':(a.get('artists') or [{}])[0].get('name'),
                    'url':a.get('external_urls',{}).get('spotify'),'id_ext':a.get('id'),
                    'year':(a.get('release_date') or '')[:4]})
    return out

def year_of(fecha_cd):
    m = re.search(r'(\d{4})', fecha_cd or '')
    return m.group(1) if m else None

# ---------- pipeline ----------
def main():
    env = load_env()
    have_spotify = bool(env.get('SPOTIFY_CLIENT_ID'))
    print('Spotify:', 'ON' if have_spotify else 'OFF (sin credenciales)', file=sys.stderr)
    c = db()
    bandas = c.execute("""select ID_BANDA,NOMBRE_BREVE,NOMBRE_COMPLETO from banda
        where LOCALIDAD='Sevilla' and PROVINCIA='Sevilla' and (FECHA_EXT is null or FECHA_EXT='')
        order by NOMBRE_BREVE""").fetchall()

    rows = []          # para CSV de apoyo
    db_rows = []       # para enlace_candidato: mejor candidato por (disco, servicio)
    for bid, breve, completo in bandas:
        discos = c.execute("select ID_DISCO,NOMBRE_CD,FECHA_CD from disco where BANDADISCO=? order by FECHA_CD", (bid,)).fetchall()
        cands = []
        for name in {breve, completo}:
            if not name: continue
            cands += deezer_albums(name) + itunes_albums(name)
            if have_spotify: cands += spotify_albums(name, env)
            time.sleep(0.15)
        for did, cd, fcd in discos:
            byr = year_of(fcd)
            # mejor candidato por servicio
            best_by_srv = {}
            for cand in cands:
                a_score = max(jacc(cand['artist'], breve), jacc(cand['artist'], completo or ''))
                if a_score < 0.34:
                    continue
                t_score = jacc(cand['album'], cd)
                yb = 0.15 if (byr and cand.get('year') == byr) else 0.0
                score = round(0.55*t_score + 0.30*a_score + yb, 3)
                srv = cand['servicio']
                if srv not in best_by_srv or score > best_by_srv[srv][0]:
                    best_by_srv[srv] = (score, cand)
            if best_by_srv:
                for srv, (sc, cand) in best_by_srv.items():
                    conf = 'ALTA' if sc>=0.55 else ('MEDIA' if sc>=0.4 else 'BAJA')
                    rows.append([bid, breve, did, cd, byr or '', srv, cand['album'], cand['artist'],
                                 cand.get('year') or '', sc, cand['url'], conf])
                    db_rows.append(('disco', did, srv, cand['url'], cand.get('id_ext'),
                                    cand['album'], cand['artist'], cand.get('year') or '', sc, conf))
            else:
                rows.append([bid, breve, did, cd, byr or '', '', '', '', '', 0, '', 'SIN_MATCH'])
        print(f'  {breve}: {len(discos)} discos, {len(cands)} candidatos', file=sys.stderr)

    # --- CSV de apoyo ---
    with open(OUT, 'w', newline='', encoding='utf-8-sig') as f:
        w = csv.writer(f)
        w.writerow(['id_banda','banda','id_disco','disco','anio_disco','servicio','album_encontrado',
                    'artista_encontrado','anio_encontrado','score','url','confianza'])
        w.writerows(rows)

    # --- volcado a enlace_candidato ---
    wc = db()
    ids_disco = [x[2] for x in db_rows] or [-1]
    # limpia pendientes previos SOLO de discos de este alcance (re-ejecutable, no borra aprobados/rechazados)
    ph = ','.join('?'*len(ids_disco))
    wc.execute(f"delete from enlace_candidato where TIPO_ENT='disco' and ESTADO='pendiente' and ID_ENT in ({ph})", ids_disco)
    wc.executemany("""insert or ignore into enlace_candidato
        (TIPO_ENT,ID_ENT,SERVICIO,URL,ID_EXT,TITULO_ENC,ARTISTA_ENC,ANIO_ENC,SCORE,CONFIANZA,ESTADO,RUN_ID)
        values ('disco',?,?,?,?,?,?,?,?,?, 'pendiente', ?)""",
        [(d[1],d[2],d[3],d[4],d[5],d[6],d[7],d[8],d[9], RUN_ID) for d in db_rows])
    wc.commit()

    ins = wc.execute("select count(*) from enlace_candidato where RUN_ID=?", (RUN_ID,)).fetchone()[0]
    by = {}
    for r in rows:
        by[r[-1]] = by.get(r[-1], 0) + 1
    print(f'\nRUN_ID={RUN_ID}')
    print('confianza (filas por disco+servicio):', by)
    print(f'candidatos insertados en enlace_candidato: {ins}')
    print('CSV ->', OUT)

if __name__ == '__main__':
    main()
