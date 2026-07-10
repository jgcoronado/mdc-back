#!/usr/bin/env python3
"""
Fase 3: enlazado de SINGLES/PISTAS con marchas estrenadas que no están en ningún
disco enlazado. Alcance: marchas cuya BANDA_ESTRENO es una de las 14 bandas
activas de Sevilla capital y que NO aparecen en disco_marcha.

Heurística del usuario: al estrenar una marcha, la banda suele subir el audio el
MISMO año → el año de estreno es una señal fuerte de desambiguación.

Servicios: Spotify (track), Deezer (track), iTunes (song). Escribe candidatos
TIPO_ENT='marcha' a enlace_candidato (ESTADO='pendiente'). NO toca enlace_streaming.
Re-ejecutable.
"""
import sqlite3, urllib.request, urllib.parse, json, time, sys, re, unicodedata, os, base64, datetime

ROOT = r'C:/Users/usuario/Documents/mysql-simple'
DB = ROOT + '/php/data/mdc.db'
ENV = ROOT + '/.env'
RUN_ID = 'marchas-sevilla-' + datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
IDS_BANDA = [5, 6, 7, 11, 16, 20, 26, 32, 36, 64, 71, 91, 106, 113]

def load_env():
    env = {}
    if os.path.exists(ENV):
        for line in open(ENV, encoding='utf-8'):
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line: continue
            k, v = line.split('=', 1)
            env[k.strip()] = v.strip().strip('\'"')
    return env

def db():
    c = sqlite3.connect(DB)
    c.text_factory = lambda b: b.decode('latin-1')
    return c

def norm(s):
    if not s: return ''
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode('ascii').lower()
    s = re.sub(r'[^a-z0-9 ]', ' ', s)
    return re.sub(r'\s+', ' ', s).strip()

STOP_T = {'de','la','el','los','las','y','del','en','a','marcha'}
STOP_A = {'de','la','el','los','las','y','del','en','a','banda','cornetas','tambores',
          'agrupacion','musical','am','bct','bcct','cc','tt','cctt','ntra','sra','nuestra',
          'senora','sevilla'}
def toks(s, stop):
    return {t for t in norm(s).split() if t and t not in stop}

def jacc(a, b, stop):
    A, B = toks(a, stop), toks(b, stop)
    if not A or not B: return 0.0
    return len(A & B) / len(A | B)

def get(url, headers=None):
    req = urllib.request.Request(url, headers=headers or {'User-Agent': 'Mozilla/5.0'})
    return json.load(urllib.request.urlopen(req, timeout=20))

def deezer_tracks(q):
    try:
        d = get('https://api.deezer.com/search/track?q=' + urllib.parse.quote(q) + '&limit=15')
    except Exception:
        return []
    return [{'servicio':'deezer','title':t.get('title'),'artist':t.get('artist',{}).get('name'),
             'url':t.get('link') or f"https://www.deezer.com/track/{t.get('id')}",'id_ext':str(t.get('id')),
             'year':None} for t in d.get('data', [])]

def itunes_tracks(q):
    try:
        d = get('https://itunes.apple.com/search?term=' + urllib.parse.quote(q) + '&entity=song&limit=15&country=ES')
    except Exception:
        return []
    return [{'servicio':'apple','title':r.get('trackName'),'artist':r.get('artistName'),
             'url':r.get('trackViewUrl'),'id_ext':str(r.get('trackId')),
             'year':(r.get('releaseDate') or '')[:4]} for r in d.get('results', []) if r.get('trackViewUrl')]

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

def spotify_tracks(q, env):
    tk = spotify_token(env)
    if not tk: return []
    u = 'https://api.spotify.com/v1/search?' + urllib.parse.urlencode({'q':q,'type':'track','limit':15,'market':'ES'})
    try:
        d = get(u, headers={'Authorization':'Bearer '+tk})
    except Exception:
        return []
    out = []
    for t in d.get('tracks', {}).get('items', []):
        out.append({'servicio':'spotify','title':t.get('name'),
                    'artist':(t.get('artists') or [{}])[0].get('name'),
                    'url':t.get('external_urls',{}).get('spotify'),'id_ext':t.get('id'),
                    'year':(t.get('album',{}).get('release_date') or '')[:4]})
    return out

def main():
    env = load_env()
    have_spotify = bool(env.get('SPOTIFY_CLIENT_ID'))
    print('Spotify:', 'ON' if have_spotify else 'OFF', file=sys.stderr)
    c = db()
    ph = ','.join('?'*len(IDS_BANDA))
    marchas = c.execute(f"""
        select m.ID_MARCHA, m.TITULO, m.FECHA, b.NOMBRE_BREVE, b.NOMBRE_COMPLETO
        from marcha m join banda b on b.ID_BANDA = m.BANDA_ESTRENO
        where m.BANDA_ESTRENO in ({ph})
          and not exists (select 1 from disco_marcha dm where dm.IDMARCHA = m.ID_MARCHA)
        order by m.FECHA desc""", IDS_BANDA).fetchall()
    print(f'objetivo: {len(marchas)} marchas', file=sys.stderr)

    db_rows = []
    for i, (mid, titulo, fecha, breve, completo) in enumerate(marchas):
        anio = str(int(fecha)) if fecha else None
        q = f'{titulo} {breve}'
        cands = deezer_tracks(q) + itunes_tracks(q)
        if have_spotify: cands += spotify_tracks(q, env)
        time.sleep(0.12)
        best_by_srv = {}
        for cand in cands:
            t_score = jacc(cand['title'], titulo, STOP_T)
            a_score = max(jacc(cand['artist'], breve, STOP_A), jacc(cand['artist'], completo or '', STOP_A))
            if t_score < 0.5 or a_score < 0.34:
                continue
            yb = 0.2 if (anio and cand.get('year') == anio) else 0.0
            score = round(min(0.5*t_score + 0.3*a_score + yb, 1.0), 3)
            srv = cand['servicio']
            if srv not in best_by_srv or score > best_by_srv[srv][0]:
                best_by_srv[srv] = (score, cand)
        for srv, (sc, cand) in best_by_srv.items():
            conf = 'ALTA' if sc >= 0.6 else ('MEDIA' if sc >= 0.45 else 'BAJA')
            db_rows.append((mid, srv, cand['url'], cand['id_ext'], cand['title'], cand['artist'],
                            cand.get('year') or '', sc, conf))
        if (i+1) % 40 == 0:
            print(f'  ...{i+1}/{len(marchas)}', file=sys.stderr)

    wc = db()
    # marchas objetivo (para limpiar solo su ámbito)
    mids = [m[0] for m in marchas] or [-1]
    phm = ','.join('?'*len(mids))
    wc.execute(f"delete from enlace_candidato where TIPO_ENT='marcha' and ESTADO='pendiente' and ID_ENT in ({phm})", mids)
    wc.executemany("""insert or ignore into enlace_candidato
        (TIPO_ENT,ID_ENT,SERVICIO,URL,ID_EXT,TITULO_ENC,ARTISTA_ENC,ANIO_ENC,SCORE,CONFIANZA,ESTADO,RUN_ID)
        values ('marcha',?,?,?,?,?,?,?,?,?, 'pendiente', ?)""",
        [(d[0],d[1],d[2],d[3],d[4],d[5],d[6],d[7],d[8], RUN_ID) for d in db_rows])
    wc.commit()

    from collections import Counter
    conf = Counter(d[8] for d in db_rows)
    marchas_con = len({d[0] for d in db_rows})
    ins = wc.execute("select count(*) from enlace_candidato where RUN_ID=?", (RUN_ID,)).fetchone()[0]
    print(f'\nRUN_ID={RUN_ID}')
    print('confianza:', dict(conf))
    print(f'marchas con >=1 candidato: {marchas_con}/{len(marchas)}')
    print(f'candidatos de marcha insertados: {ins}')

if __name__ == '__main__':
    main()
