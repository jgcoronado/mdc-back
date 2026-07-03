<?php

declare(strict_types=1);

namespace App;

/**
 * Controladores del panel de administración (server-rendered).
 * Ports de app/login, app/dashboard/* y app/api/admin/* (formularios + PRG + CSRF).
 */
final class Admin
{
    private static function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    /** ¿Cambió el valor (con normalización, ignorando tipo)? */
    private static function changed(mixed $a, mixed $b): bool
    {
        $an = AdminRepo::normalize($a);
        $bn = AdminRepo::normalize($b);
        return ((string) ($an ?? "\x00")) !== ((string) ($bn ?? "\x00"));
    }

    /** @return list<int> IDs de autor enviados (dedup, positivos, ordenados). */
    private static function postAutoresIds(): array
    {
        $raw = $_POST['autoresIds'] ?? [];
        if (!is_array($raw)) $raw = [$raw];
        $ids = array_values(array_unique(array_filter(
            array_map(static fn($v): int => (int) $v, $raw),
            static fn(int $n): bool => $n > 0
        )));
        sort($ids);
        return $ids;
    }

    private static function noticeFromQuery(): ?array
    {
        if (isset($_GET['saved'])) return ['type' => 'ok', 'msg' => 'Cambios guardados.'];
        if (isset($_GET['created'])) return ['type' => 'ok', 'msg' => 'Creado correctamente.'];
        if (isset($_GET['nochanges'])) return ['type' => 'info', 'msg' => 'No había cambios que guardar.'];
        if (isset($_GET['err'])) return ['type' => 'error', 'msg' => 'Error: ' . preg_replace('/[^A-Z_]/', '', (string) $_GET['err'])];
        return null;
    }

    // ── Login / logout ─────────────────────────────────────────────────────
    public static function loginForm(): void
    {
        if (Auth::currentSession() !== null) Http::redirect('/dashboard', 302);
        View::render('admin/login', ['error' => null, 'username' => ''], ['title' => 'Acceso — Marchas de Cristo', 'noindex' => true]);
    }

    public static function loginPost(): void
    {
        $username = (string) ($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $fail = static function (string $msg, int $status) use ($username): void {
            http_response_code($status);
            View::render('admin/login', ['error' => $msg, 'username' => $username], ['title' => 'Acceso — Marchas de Cristo', 'noindex' => true]);
        };

        if ($username === '' || $password === '') { $fail('Introduce usuario y contraseña.', 400); return; }
        if (strlen($username) > 120 || strlen($password) > 512) { $fail('Credenciales no válidas.', 400); return; }

        $key = Auth::rateKey($username);
        $retry = Auth::rateRetryAfter($key);
        if ($retry > 0) { $fail("Demasiados intentos. Reinténtalo en {$retry}s.", 429); return; }

        $row = Db::one('SELECT usuario, clave FROM usuarios WHERE usuario = ? LIMIT 1', [trim($username)]);
        if ($row === null || !Auth::verifyPassword($password, (string) $row['clave'])) {
            Auth::rateFail($key);
            $fail('Usuario o contraseña incorrectos.', 401);
            return;
        }
        Auth::rateClear($key);

        if (Auth::isLegacyMd5((string) $row['clave'])) {
            Db::run('UPDATE usuarios SET clave = ? WHERE usuario = ?', [Auth::hashPassword($password), $row['usuario']]);
        }

        $ttl = (int) ($GLOBALS['config']['login_ttl_ms'] ?? 8 * 60 * 60 * 1000);
        $now = self::nowMs();
        $token = Auth::signSession([
            'user' => $row['usuario'],
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ]);
        Auth::setSessionCookie($token);
        Http::redirect('/dashboard', 302);
    }

    public static function logout(): void
    {
        $session = Auth::currentSession();
        if ($session !== null && !Auth::checkCsrf($_POST['_csrf'] ?? null, $session)) {
            Http::redirect('/dashboard', 302);
        }
        Auth::clearSessionCookie();
        Http::redirect('/login', 302);
    }

    // ── Dashboard ──────────────────────────────────────────────────────────
    public static function dashboard(): void
    {
        $session = Auth::requireAuth();
        $q = trim((string) ($_GET['q'] ?? ''));
        $marchas = [];
        $autores = [];
        if ($q !== '') {
            $marchas = array_slice(Repo::searchMarchas('titulo=' . rawurlencode($q))['data'], 0, 15);
            $autores = array_slice(Repo::searchAutores('nombre=' . rawurlencode($q))['data'], 0, 15);
        }
        View::render('admin/dashboard', compact('q', 'marchas', 'autores', 'session'),
            ['title' => 'Panel de administración — Marchas de Cristo', 'noindex' => true]);
    }

    // ── Marcha: edición ──────────────────────────────────────────────────────
    public static function marchaEditForm(array $p): void
    {
        $session = Auth::requireAuth();
        $id = (string) $p['id'];
        $marcha = Repo::fetchMarchaRaw($id);
        if ($marcha === null) Http::notFound();
        View::render('admin/marcha_form', [
            'mode' => 'edit', 'session' => $session, 'action' => "/dashboard/marcha/$id",
            'marcha' => $marcha, 'authors' => Repo::currentAutoresForMarcha($id),
            'notice' => self::noticeFromQuery(), 'error' => null,
        ], ['title' => "Editar marcha #$id — Marchas de Cristo", 'noindex' => true]);
    }

    public static function marchaEditPost(array $p): void
    {
        $session = Auth::requireAuth();
        $id = (string) $p['id'];
        if (!Auth::checkCsrf($_POST['_csrf'] ?? null, $session)) Http::redirect("/dashboard/marcha/$id?err=CSRF");

        $current = Repo::fetchMarchaRaw($id);
        if ($current === null) Http::notFound();

        // Delta de campos editables.
        $keys = [];
        $values = [];
        foreach (AdminRepo::EDITABLE_MARCHA as $f) {
            $sub = $_POST[$f] ?? null;
            if (self::changed($current[$f] ?? null, $sub)) {
                $keys[] = $f;
                $values[] = AdminRepo::normalize($sub);
            }
        }
        // Delta de autores.
        $curIds = array_map('intval', array_column(Repo::currentAutoresForMarcha($id), 'ID_AUTOR'));
        sort($curIds);
        $subIds = self::postAutoresIds();
        $authorChanged = $curIds !== $subIds;

        if ($keys === [] && !$authorChanged) Http::redirect("/dashboard/marcha/$id?nochanges=1");

        if ($keys !== []) {
            $r = AdminRepo::editMarcha((int) $id, $keys, $values);
            if ($r['code'] === 'NOT_FOUND') Http::notFound();
            if ($r['code'] !== 'UPDATED') Http::redirect("/dashboard/marcha/$id?err=" . $r['code']);
        }
        if ($authorChanged) {
            if ($subIds === []) Http::redirect("/dashboard/marcha/$id?err=AUTHORS_REQUIRED");
            $r = AdminRepo::editMarchaAutores((int) $id, $subIds);
            if ($r['code'] !== 'UPDATED') Http::redirect("/dashboard/marcha/$id?err=" . $r['code']);
        }
        Http::redirect("/dashboard/marcha/$id?saved=1");
    }

    // ── Marcha: alta ─────────────────────────────────────────────────────────
    public static function marchaAddForm(): void
    {
        $session = Auth::requireAuth();
        View::render('admin/marcha_form', [
            'mode' => 'add', 'session' => $session, 'action' => '/dashboard/marcha/add',
            'marcha' => [], 'authors' => [], 'notice' => null, 'error' => null,
        ], ['title' => 'Añadir marcha — Marchas de Cristo', 'noindex' => true]);
    }

    public static function marchaAddPost(): void
    {
        $session = Auth::requireAuth();
        $fields = [];
        foreach (AdminRepo::INSERTABLE_MARCHA as $f) $fields[$f] = $_POST[$f] ?? '';
        $ids = self::postAutoresIds();

        $reRender = static function (string $err) use ($session, $fields, $ids): void {
            http_response_code(400);
            View::render('admin/marcha_form', [
                'mode' => 'add', 'session' => $session, 'action' => '/dashboard/marcha/add',
                'marcha' => $fields, 'authors' => Repo::autoresByIds($ids), 'notice' => null, 'error' => $err,
            ], ['title' => 'Añadir marcha — Marchas de Cristo', 'noindex' => true]);
        };

        if (!Auth::checkCsrf($_POST['_csrf'] ?? null, $session)) { $reRender('CSRF'); return; }
        $r = AdminRepo::addMarcha($fields, $ids);
        if (($r['code'] ?? '') === 'CREATED') Http::redirect('/dashboard/marcha/' . $r['marchaId'] . '?created=1');
        $reRender($r['code'] ?? 'ERROR');
    }

    // ── Autor: edición ───────────────────────────────────────────────────────
    public static function autorEditForm(array $p): void
    {
        $session = Auth::requireAuth();
        $id = (string) $p['id'];
        $autor = Repo::fetchAutorRaw($id);
        if ($autor === null) Http::notFound();
        View::render('admin/autor_form', [
            'mode' => 'edit', 'session' => $session, 'action' => "/dashboard/autor/$id",
            'autor' => $autor, 'notice' => self::noticeFromQuery(), 'error' => null,
        ], ['title' => "Editar compositor #$id — Marchas de Cristo", 'noindex' => true]);
    }

    public static function autorEditPost(array $p): void
    {
        $session = Auth::requireAuth();
        $id = (string) $p['id'];
        if (!Auth::checkCsrf($_POST['_csrf'] ?? null, $session)) Http::redirect("/dashboard/autor/$id?err=CSRF");

        $current = Repo::fetchAutorRaw($id);
        if ($current === null) Http::notFound();

        $keys = [];
        $values = [];
        foreach (AdminRepo::EDITABLE_AUTOR as $f) {
            $sub = $_POST[$f] ?? null;
            if (self::changed($current[$f] ?? null, $sub)) {
                $keys[] = $f;
                $values[] = AdminRepo::normalize($sub);
            }
        }
        if ($keys === []) Http::redirect("/dashboard/autor/$id?nochanges=1");

        $r = AdminRepo::editAutor((int) $id, $keys, $values);
        if ($r['code'] !== 'UPDATED') Http::redirect("/dashboard/autor/$id?err=" . $r['code']);
        Http::redirect("/dashboard/autor/$id?saved=1");
    }

    // ── Autor: alta ──────────────────────────────────────────────────────────
    public static function autorAddForm(): void
    {
        $session = Auth::requireAuth();
        View::render('admin/autor_form', [
            'mode' => 'add', 'session' => $session, 'action' => '/dashboard/autor/add',
            'autor' => [], 'notice' => null, 'error' => null,
        ], ['title' => 'Añadir compositor — Marchas de Cristo', 'noindex' => true]);
    }

    public static function autorAddPost(): void
    {
        $session = Auth::requireAuth();
        $fields = [];
        foreach (AdminRepo::EDITABLE_AUTOR as $f) $fields[$f] = $_POST[$f] ?? '';

        $reRender = static function (string $err) use ($session, $fields): void {
            http_response_code(400);
            View::render('admin/autor_form', [
                'mode' => 'add', 'session' => $session, 'action' => '/dashboard/autor/add',
                'autor' => $fields, 'notice' => null, 'error' => $err,
            ], ['title' => 'Añadir compositor — Marchas de Cristo', 'noindex' => true]);
        };

        if (!Auth::checkCsrf($_POST['_csrf'] ?? null, $session)) { $reRender('CSRF'); return; }
        $r = AdminRepo::addAutor($fields);
        if (($r['code'] ?? '') === 'CREATED') Http::redirect('/dashboard/autor/' . $r['autorId'] . '?created=1');
        $reRender($r['code'] ?? 'ERROR');
    }

    // ── Autocomplete de autores (JSON) ───────────────────────────────────────
    public static function autorFastSearch(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (Auth::currentSession() === null) {
            http_response_code(401);
            echo json_encode(['code' => 'AUTH_REQUIRED', 'data' => []]);
            return;
        }
        $nombre = trim((string) ($_GET['nombre'] ?? ''));
        if ($nombre === '') { echo json_encode(['rowsReturned' => 0, 'data' => []]); return; }
        $data = array_slice(Repo::searchAutores('nombre=' . rawurlencode($nombre))['data'], 0, 15);
        echo json_encode(['rowsReturned' => count($data), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
