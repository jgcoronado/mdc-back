#!/usr/bin/env python3
"""
Fase 2: enlazado de la PÁGINA DE ARTISTA de cada banda con los servicios.
Alcance: bandas activas de Sevilla capital (mismas que fase 1).
Servicios: Spotify (creds en .env), Deezer e iTunes/Apple.

Señal de calidad extra: el nombre de artista encontrado se contrasta con el
artista de los discos ya localizados para esa banda (enlace_candidato de fase 1);
si coincide, sube la confianza.

Escribe candidatos TIPO_ENT='banda' a enlace_candidato (ESTADO='pendiente').
NO toca enlace_streaming. Re-ejecutable.
"""
import sqlite3, urllib.request, urllib.parse, json, time, sys, re, unicodedata, os, base64, datetime

ROOT = r'C:/Users/usuario/Documents/mysql-simple'
DB = ROOT + '/php/data/mdc.db'
ENV = ROOT + '/.env'
RUN_ID = 'bandas-sevilla-' + datetime.datetime.now().strftime('%Y%m%d-%H%M%S')

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

STOP = {'de','la','el','los','las','y','del','en','a','banda','cornetas','tambores',
        'agrupacion','musical','am','bct','bcct','cc','tt','cctt','ntra','sra','nuestra',
        'senora','sevilla'}
def toks(s):
    return {t for t in norm(s).split() if t and t not in STOP}

def jacc(a, b):
    A, B = toks(a), toks(b)
    if not A or not B: return 0.0
    return len(A & B) / len(A | B)

def get(url, headers=None):
    req = urllib.request.Request(url, headers=headers or {'User-Agent': 'Mozilla/5.0'})
    return json.load(urllib.request.urlopen(req, timeout=20))

# ---------- artistas por servicio ----------
def deezer_artists(name):
    try:
        d = get('https://api.deezer.com/search/artist?q=' + urllib.parse.quote(name) + '&limit=10')
    except Exception:
        return []
    return [{'servicio':'deezer','name':a.get('name'),'url':a.get('link') or f"https://www.deezer.com/artist/{a.get('id')}",
             'id_ext':str(a.get('id'))} for a in d.get('data', [])]

def itunes_artists(name):
    try:
        d = get('https://itunes.apple.com/search?term=' + urllib.parse.quote(name) + '&entity=musicArtist&limit=10&country=ES')
    except Exception:
        return []
    return [{'servicio':'apple','name':r.get('artistName'),'url':r.get('artistLinkUrl'),
             'id_ext':str(r.get('artistId'))} for r in d.get('results', []) if r.get('artistLinkUrl')]

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

def spotify_artists(name, env):
    tk = spotify_token(env)
    if not tk: return []
    u = 'https://api.spotify.com/v1/search?' + urllib.parse.urlencode({'q':name,'type':'artist','limit':10,'market':'ES'})
    try:
        d = get(u, headers={'Authorization':'Bearer '+tk})
    except Exception:
        return []
    return [{'servicio':'spotify','name':a.get('name'),'url':a.get('external_urls',{}).get('spotify'),
             'id_ext':a.get('id')} for a in d.get('artists', {}).get('items', [])]

def main():
    env = load_env()
    have_spotify = bool(env.get('SPOTIFY_CLIENT_ID'))
    print('Spotify:', 'ON' if have_spotify else 'OFF', file=sys.stderr)
    c = db()
    bandas = c.execute("""select ID_BANDA,NOMBRE_BREVE,NOMBRE_COMPLETO from banda
        where LOCALIDAD='Sevilla' and PROVINCIA='Sevilla' and (FECHA_EXT is null or FECHA_EXT='')
        order by NOMBRE_BREVE""").fetchall()

    db_rows = []
    for bid, breve, completo in bandas:
        # artistas ya vistos en los discos de fase 1 de esta banda (señal de refuerzo)
        artistas_disco = [r[0] for r in c.execute(
            "select distinct ARTISTA_ENC from enlace_candidato where TIPO_ENT='disco' and ID_ENT in "
            "(select ID_DISCO from disco where BANDADISCO=?) and ARTISTA_ENC is not null and ARTISTA_ENC<>''", (bid,))]
        cands = []
        for name in {breve, completo}:
            if not name: continue
            cands += deezer_artists(name) + itunes_artists(name)
            if have_spotify: cands += spotify_artists(name, env)
            time.sleep(0.15)
        banda_toks = toks(breve) | toks(completo or '')
        best_by_srv = {}
        for cand in cands:
            base = max(jacc(cand['name'], breve), jacc(cand['name'], completo or ''))
            if base < 0.30:
                continue
            refuerzo = 0.0
            for ad in artistas_disco:
                if jacc(cand['name'], ad) >= 0.6:
                    refuerzo = 0.2
                    break
            score = round(min(base + refuerzo, 1.0), 3)
            # nº de tokens significativos compartidos: 1 solo token (p.ej. "angeles")
            # es señal débil y produce falsos positivos ("Los Angeles").
            compartidos = len(toks(cand['name']) & banda_toks)
            srv = cand['servicio']
            if srv not in best_by_srv or score > best_by_srv[srv][0]:
                best_by_srv[srv] = (score, cand, compartidos, refuerzo > 0)
        for srv, (sc, cand, compartidos, reforzado) in best_by_srv.items():
            fiable = compartidos >= 2 or reforzado
            if sc >= 0.6 and fiable:
                conf = 'ALTA'
            elif sc >= 0.4:
                conf = 'MEDIA'
            else:
                conf = 'BAJA'
            db_rows.append((bid, srv, cand['url'], cand['id_ext'], cand['name'], sc, conf))
        estado = ','.join(f"{s}:{best_by_srv[s][1]['name']}({best_by_srv[s][0]})" for s in best_by_srv)
        print(f'  {breve}: {estado or "sin match"}', file=sys.stderr)

    wc = db()
    ids = [b[0] for b in bandas] or [-1]
    ph = ','.join('?'*len(ids))
    wc.execute(f"delete from enlace_candidato where TIPO_ENT='banda' and ESTADO='pendiente' and ID_ENT in ({ph})", ids)
    wc.executemany("""insert or ignore into enlace_candidato
        (TIPO_ENT,ID_ENT,SERVICIO,URL,ID_EXT,TITULO_ENC,ARTISTA_ENC,ANIO_ENC,SCORE,CONFIANZA,ESTADO,RUN_ID)
        values ('banda',?,?,?,?,?,'','',?,?, 'pendiente', ?)""",
        [(d[0],d[1],d[2],d[3],d[4],d[5],d[6], RUN_ID) for d in db_rows])
    wc.commit()

    ins = wc.execute("select count(*) from enlace_candidato where RUN_ID=?", (RUN_ID,)).fetchone()[0]
    from collections import Counter
    conf = Counter(d[6] for d in db_rows)
    print(f'\nRUN_ID={RUN_ID}')
    print('confianza:', dict(conf))
    print(f'candidatos de banda insertados: {ins}')

if __name__ == '__main__':
    main()
