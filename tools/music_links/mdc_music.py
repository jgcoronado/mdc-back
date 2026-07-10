#!/usr/bin/env python3
"""
Utilidades compartidas por los matchers de enlaces de streaming
(match_discos.py, match_bandas.py, match_marchas.py).

Centraliza: carga de .env, conexión SQLite (latin-1 + busy_timeout), normalización
y similitud, HTTP con reintentos/backoff (clave para no morir con el rate-limit de
iTunes), token de Spotify, búsquedas por servicio y selección de ámbito de bandas.
"""
import sqlite3, urllib.request, urllib.parse, json, time, sys, re, unicodedata, os, base64

ROOT = r'C:/Users/usuario/Documents/mysql-simple'
DB = ROOT + '/php/data/mdc.db'
ENV = ROOT + '/.env'

# ── entorno / BD ─────────────────────────────────────────────────────────────
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

def connect():
    c = sqlite3.connect(DB, timeout=30)
    c.text_factory = lambda b: b.decode('latin-1')  # la BD guarda texto en latin-1
    c.execute('PRAGMA busy_timeout = 30000')
    return c

# ── normalización / similitud ────────────────────────────────────────────────
STOP_ARTIST = {'de','la','el','los','las','y','del','en','a','banda','cornetas','tambores',
               'agrupacion','musical','am','bct','bcct','cc','tt','cctt','ntra','sra','nuestra',
               'senora','sevilla'}
STOP_TITLE = {'de','la','el','los','las','y','del','en','a','marcha'}

def norm(s):
    if not s: return ''
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode('ascii').lower()
    s = re.sub(r'[^a-z0-9 ]', ' ', s)
    return re.sub(r'\s+', ' ', s).strip()

def toks(s, stop):
    return {t for t in norm(s).split() if t and t not in stop}

def jacc(a, b, stop):
    A, B = toks(a, stop), toks(b, stop)
    if not A or not B: return 0.0
    return len(A & B) / len(A | B)

def year_of(v):
    m = re.search(r'(\d{4})', str(v or ''))
    return m.group(1) if m else None

# ── HTTP robusto (reintentos + backoff ante 429/403/5xx) ─────────────────────
def http_get(url, headers=None, retries=4):
    delay = 2.0
    for intento in range(retries):
        try:
            req = urllib.request.Request(url, headers=headers or {'User-Agent': 'Mozilla/5.0'})
            return json.load(urllib.request.urlopen(req, timeout=25))
        except urllib.error.HTTPError as e:
            if e.code in (429, 403, 500, 502, 503) and intento < retries - 1:
                ra = e.headers.get('Retry-After')
                wait = float(ra) if (ra and ra.isdigit()) else delay
                print(f'    [rate-limit {e.code}] espera {wait:.0f}s', file=sys.stderr)
                time.sleep(wait)
                delay *= 2
                continue
            return None
        except Exception:
            if intento < retries - 1:
                time.sleep(delay); delay *= 2; continue
            return None
    return None

# ── Spotify (client-credentials) ─────────────────────────────────────────────
_SP_TOKEN = None
def spotify_token(env):
    global _SP_TOKEN
    if _SP_TOKEN: return _SP_TOKEN
    cid, cs = env.get('SPOTIFY_CLIENT_ID'), env.get('SPOTIFY_CLIENT_SECRET')
    if not cid or not cs: return None
    data = urllib.parse.urlencode({'grant_type': 'client_credentials'}).encode()
    h = {'Authorization': 'Basic ' + base64.b64encode(f'{cid}:{cs}'.encode()).decode()}
    for intento in range(3):
        try:
            req = urllib.request.Request('https://accounts.spotify.com/api/token', data=data, headers=h)
            _SP_TOKEN = json.load(urllib.request.urlopen(req, timeout=25))['access_token']
            return _SP_TOKEN
        except Exception:
            time.sleep(2 * (intento + 1))
    return None

def _spotify_search(q, typ, env):
    tk = spotify_token(env)
    if not tk: return {}
    u = 'https://api.spotify.com/v1/search?' + urllib.parse.urlencode(
        {'q': q, 'type': typ, 'limit': 20, 'market': 'ES'})
    return http_get(u, headers={'Authorization': 'Bearer ' + tk}) or {}

# ── búsquedas por servicio ───────────────────────────────────────────────────
def deezer_albums(banda):
    d = http_get('https://api.deezer.com/search/album?q=' + urllib.parse.quote(f'artist:"{banda}"') + '&limit=50') or {}
    return [{'servicio':'deezer','album':a.get('title'),'artist':a.get('artist',{}).get('name'),
             'url':a.get('link') or f"https://www.deezer.com/album/{a.get('id')}",
             'id_ext':str(a.get('id')),'year':None} for a in d.get('data', [])]

def itunes_albums(banda):
    d = http_get('https://itunes.apple.com/search?term=' + urllib.parse.quote(banda) + '&entity=album&limit=50&country=ES') or {}
    return [{'servicio':'apple','album':r.get('collectionName'),'artist':r.get('artistName'),
             'url':r.get('collectionViewUrl'),'id_ext':str(r.get('collectionId')),
             'year':(r.get('releaseDate') or '')[:4]} for r in d.get('results', [])]

def spotify_albums(banda, env):
    d = _spotify_search(banda, 'album', env)
    return [{'servicio':'spotify','album':a.get('name'),'artist':(a.get('artists') or [{}])[0].get('name'),
             'url':a.get('external_urls',{}).get('spotify'),'id_ext':a.get('id'),
             'year':(a.get('release_date') or '')[:4]} for a in d.get('albums', {}).get('items', [])]

def deezer_artists(name):
    d = http_get('https://api.deezer.com/search/artist?q=' + urllib.parse.quote(name) + '&limit=10') or {}
    return [{'servicio':'deezer','name':a.get('name'),'url':a.get('link') or f"https://www.deezer.com/artist/{a.get('id')}",
             'id_ext':str(a.get('id'))} for a in d.get('data', [])]

def itunes_artists(name):
    d = http_get('https://itunes.apple.com/search?term=' + urllib.parse.quote(name) + '&entity=musicArtist&limit=10&country=ES') or {}
    return [{'servicio':'apple','name':r.get('artistName'),'url':r.get('artistLinkUrl'),
             'id_ext':str(r.get('artistId'))} for r in d.get('results', []) if r.get('artistLinkUrl')]

def spotify_artists(name, env):
    d = _spotify_search(name, 'artist', env)
    return [{'servicio':'spotify','name':a.get('name'),'url':a.get('external_urls',{}).get('spotify'),
             'id_ext':a.get('id')} for a in d.get('artists', {}).get('items', [])]

def deezer_tracks(q):
    d = http_get('https://api.deezer.com/search/track?q=' + urllib.parse.quote(q) + '&limit=15') or {}
    return [{'servicio':'deezer','title':t.get('title'),'artist':t.get('artist',{}).get('name'),
             'url':t.get('link') or f"https://www.deezer.com/track/{t.get('id')}",'id_ext':str(t.get('id')),
             'year':None} for t in d.get('data', [])]

def itunes_tracks(q):
    d = http_get('https://itunes.apple.com/search?term=' + urllib.parse.quote(q) + '&entity=song&limit=15&country=ES') or {}
    return [{'servicio':'apple','title':r.get('trackName'),'artist':r.get('artistName'),
             'url':r.get('trackViewUrl'),'id_ext':str(r.get('trackId')),
             'year':(r.get('releaseDate') or '')[:4]} for r in d.get('results', []) if r.get('trackViewUrl')]

def spotify_tracks(q, env):
    d = _spotify_search(q, 'track', env)
    out = []
    for t in d.get('tracks', {}).get('items', []):
        out.append({'servicio':'spotify','title':t.get('name'),
                    'artist':(t.get('artists') or [{}])[0].get('name'),
                    'url':t.get('external_urls',{}).get('spotify'),'id_ext':t.get('id'),
                    'year':(t.get('album',{}).get('release_date') or '')[:4]})
    return out

# ── ámbito de bandas ─────────────────────────────────────────────────────────
ACTIVA = "(FECHA_EXT is null or FECHA_EXT='')"
SCOPES = {
    'sevilla-capital':  f"{ACTIVA} and PROVINCIA='Sevilla' and LOCALIDAD='Sevilla'",
    'sevilla-provincia': f"{ACTIVA} and PROVINCIA='Sevilla'",
    'all-active':       ACTIVA,
    'all':              '1=1',
}

def bandas(conn, scope):
    if scope not in SCOPES:
        raise SystemExit(f'ámbito desconocido: {scope}. Válidos: {", ".join(SCOPES)}')
    return conn.execute(
        f"select ID_BANDA, NOMBRE_BREVE, NOMBRE_COMPLETO from banda where {SCOPES[scope]} order by NOMBRE_BREVE"
    ).fetchall()

def scope_arg(default='all-active'):
    """Lee --scope de argv (o el primer argumento posicional)."""
    for i, a in enumerate(sys.argv[1:]):
        if a == '--scope' and i + 2 <= len(sys.argv) - 1:
            return sys.argv[i + 2]
        if a.startswith('--scope='):
            return a.split('=', 1)[1]
    return default
