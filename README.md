# Marchas de Cristo — Backend

Aplicación web de música procesional española ([marchasdecristo.com](https://marchasdecristo.com)).

**Stack actual (julio 2026):** PHP 8.4 plano · PDO/SQLite (FTS5) · sin build, deploy directo a hosting compartido (HelioHost/Plesk).

---

## Estructura del repositorio

```
mdc-back/
├── php/                # Toda la aplicación (ver php/README.md)
│   ├── public/          # document root del hosting
│   ├── app/             # código privado (fuera del document root)
│   ├── data/            # mdc.db en local (no versionado)
│   └── tools/           # scripts de paridad, import, backup
├── tools/ingest/       # herramienta offline de ingesta de YouTube (yt-dlp → candidatos → panel admin)
├── docs/               # contexto, arquitectura, deuda técnica, roadmap
└── .env.ftp            # credenciales de deploy por FTP (gitignored)
```

Detalles de desarrollo local, deploy y estructura interna: [`php/README.md`](php/README.md).

---

## Documentación

Ver `docs/` — empezar por `docs/context.md` para el punto de entrada (stack, convenciones, estado actual).
