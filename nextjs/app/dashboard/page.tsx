'use client';
import { useState, useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { verifySession, logout } from '@/lib/auth';

type MarchaResult = { ID_MARCHA: number; TITULO: string; DEDICATORIA: string; LOCALIDAD: string };
type AutorResult = { ID_AUTOR: number; NOMBRE: string; APELLIDOS: string; MARCHAS: number };

export default function DashboardPage() {
  const router = useRouter();
  const [user, setUser] = useState('');
  const [marchaId, setMarchaId] = useState('');
  const [autorId, setAutorId] = useState('');

  const [marchaQuery, setMarchaQuery] = useState('');
  const [autorQuery, setAutorQuery] = useState('');
  const [marchaResults, setMarchaResults] = useState<MarchaResult[]>([]);
  const [autorResults, setAutorResults] = useState<AutorResult[]>([]);
  const marchaSeq = useRef(0);
  const autorSeq = useRef(0);

  useEffect(() => {
    verifySession().then((s) => {
      if (!s) { router.replace('/login'); return; }
      setUser(s.user);
    });
  }, [router]);

  useEffect(() => {
    const q = marchaQuery.trim();
    if (q.length < 3) { setMarchaResults([]); return; }
    const current = ++marchaSeq.current;
    fetch(`/api/admin/searchMarchas?titulo=${encodeURIComponent(q)}`)
      .then((r) => r.json())
      .then((d) => { if (current === marchaSeq.current) setMarchaResults(d.data ?? []); })
      .catch(() => { if (current === marchaSeq.current) setMarchaResults([]); });
  }, [marchaQuery]);

  useEffect(() => {
    const q = autorQuery.trim();
    if (q.length < 3) { setAutorResults([]); return; }
    const current = ++autorSeq.current;
    fetch(`/api/admin/searchAutores?nombre=${encodeURIComponent(q)}`)
      .then((r) => r.json())
      .then((d) => { if (current === autorSeq.current) setAutorResults(d.data ?? []); })
      .catch(() => { if (current === autorSeq.current) setAutorResults([]); });
  }, [autorQuery]);

  async function handleLogout() {
    await logout();
    router.push('/login');
  }

  function goToMarchaEdit() {
    if (!marchaId) return;
    router.push(`/dashboard/marcha/${marchaId}`);
  }

  function goToAutorEdit() {
    if (!autorId) return;
    router.push(`/dashboard/autor/${autorId}`);
  }

  return (
    <>
      <h1>WELCOME TO DASHBOARD {user}</h1>
      <div className="divider" />
      <div className="flex flex-wrap gap-3 items-end">
        <fieldset className="fieldset">
          <label className="label">ID de marcha a editar</label>
          <input
            className="input"
            type="number"
            min={1}
            value={marchaId}
            placeholder="Ej: 125"
            onChange={(e) => setMarchaId(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && goToMarchaEdit()}
          />
        </fieldset>
        <button className="btn btn-neutral" onClick={goToMarchaEdit}>Ir a edición de marcha</button>
        <fieldset className="fieldset">
          <label className="label">ID de autor a editar</label>
          <input
            className="input"
            type="number"
            min={1}
            value={autorId}
            placeholder="Ej: 42"
            onChange={(e) => setAutorId(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && goToAutorEdit()}
          />
        </fieldset>
        <button className="btn btn-neutral" onClick={goToAutorEdit}>Ir a edición de autor</button>
        <button className="btn btn-neutral" onClick={() => router.push('/dashboard/marcha/add')}>Nueva marcha</button>
        <button className="btn btn-neutral" onClick={() => router.push('/dashboard/autor/add')}>Nuevo autor</button>
        <button className="btn" onClick={handleLogout}>Logout</button>
      </div>

      <div className="divider mt-6" />
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <p className="font-semibold mb-2">Buscar marchas</p>
          <input
            className="input w-full"
            type="text"
            placeholder="Título (min. 3 caracteres)"
            value={marchaQuery}
            onChange={(e) => setMarchaQuery(e.target.value)}
          />
          {marchaResults.length > 0 && (
            <ul className="menu bg-base-200 rounded-box mt-2 max-h-96 overflow-auto">
              {marchaResults.map((m) => (
                <li key={m.ID_MARCHA}>
                  <a onClick={() => router.push(`/dashboard/marcha/${m.ID_MARCHA}`)}>
                    <span className="font-mono text-xs opacity-60">#{m.ID_MARCHA}</span>
                    {m.TITULO}
                    {m.DEDICATORIA && <span className="text-xs opacity-60">— {m.DEDICATORIA}</span>}
                  </a>
                </li>
              ))}
            </ul>
          )}
          {marchaQuery.trim().length >= 3 && marchaResults.length === 0 && (
            <p className="text-sm opacity-60 mt-2">Sin resultados.</p>
          )}
        </div>

        <div>
          <p className="font-semibold mb-2">Buscar autores</p>
          <input
            className="input w-full"
            type="text"
            placeholder="Nombre o apellidos (min. 3 caracteres)"
            value={autorQuery}
            onChange={(e) => setAutorQuery(e.target.value)}
          />
          {autorResults.length > 0 && (
            <ul className="menu bg-base-200 rounded-box mt-2 max-h-96 overflow-auto">
              {autorResults.map((a) => (
                <li key={a.ID_AUTOR}>
                  <a onClick={() => router.push(`/dashboard/autor/${a.ID_AUTOR}`)}>
                    <span className="font-mono text-xs opacity-60">#{a.ID_AUTOR}</span>
                    {a.APELLIDOS} {a.NOMBRE}
                    <span className="text-xs opacity-60">({a.MARCHAS} marchas)</span>
                  </a>
                </li>
              ))}
            </ul>
          )}
          {autorQuery.trim().length >= 3 && autorResults.length === 0 && (
            <p className="text-sm opacity-60 mt-2">Sin resultados.</p>
          )}
        </div>
      </div>
    </>
  );
}
