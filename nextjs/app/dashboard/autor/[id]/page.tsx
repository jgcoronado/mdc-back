'use client';
import { useState, useEffect, useCallback } from 'react';
import { useRouter, useParams } from 'next/navigation';
import type { AutorDetail } from '@/lib/api';
import { buildAutorUpdatePayload, executeAutorUpdate } from '@/lib/adminApi';

type RequestState = { status: 'idle' | 'saving' | 'success' | 'error' | 'no_changes'; code: string; msg: string };

const fmt = (v: unknown) => (v === null || v === undefined || v === '') ? '(vacío)' : String(v);

const fields: { label: string; key: keyof AutorDetail }[] = [
  { label: 'Nombre', key: 'NOMBRE' },
  { label: 'Apellidos', key: 'APELLIDOS' },
  { label: 'Nombre artístico', key: 'NOMBRE_ART' },
  { label: 'Fecha de nacimiento', key: 'F_NAC' },
  { label: 'Lugar de nacimiento', key: 'LUGAR_NAC' },
  { label: 'Fecha de defunción', key: 'F_DEF' },
];

export default function AutorEditPage() {
  const router = useRouter();
  const { id } = useParams<{ id: string }>();
  const [apiData, setApiData] = useState<Partial<AutorDetail> | null>(null);
  const [oldData, setOldData] = useState<Partial<AutorDetail> | null>(null);
  const [state, setState] = useState<RequestState>({ status: 'idle', code: '', msg: '' });

  useEffect(() => {
    fetch(`/api/autor/${id}`).then((r) => r.json()).then((data) => {
      setApiData({ ...data });
      setOldData({ ...data });
    });
  }, [id]);

  const pending = apiData && oldData ? buildAutorUpdatePayload(oldData, apiData) : null;

  const handleChange = useCallback((field: keyof AutorDetail, value: string) => {
    setApiData((prev) => prev ? { ...prev, [field]: value } : prev);
    setState((s) => s.status !== 'saving' ? { status: 'idle', code: '', msg: '' } : s);
  }, []);

  async function save() {
    if (!pending) return;
    if (pending.keysToUpdate.length === 0) {
      setState({ status: 'no_changes', code: 'NO_CHANGES', msg: 'No hay cambios pendientes.' });
      return;
    }
    setState({ status: 'saving', code: '', msg: '' });
    try {
      const result = await executeAutorUpdate(pending);
      setState({ status: result.code === 'UPDATED' ? 'success' : 'error', code: result.code ?? 'UNKNOWN', msg: result.msg ?? '' });
      if (result.code === 'UPDATED') setOldData({ ...apiData });
    } catch {
      setState({ status: 'error', code: 'REQUEST_ERROR', msg: 'No se pudo actualizar el autor.' });
    }
  }

  function reset() {
    setApiData({ ...oldData });
    setState({ status: 'idle', code: '', msg: '' });
  }

  if (!apiData) return <p>Cargando...</p>;

  return (
    <div className="md:min-w-4xl">
      <div className="headDetail">Edición de autor #{apiData.ID_AUTOR} — {apiData.NOMBRE} {apiData.APELLIDOS}</div>
      <table className="table table-zebra">
        <tbody>
          {fields.map(({ label, key }) => (
            <tr key={key}>
              <th>{label}</th>
              <td>
                <input
                  className="input w-full"
                  type="text"
                  value={String(apiData[key] ?? '')}
                  onChange={(e) => handleChange(key, e.target.value)}
                />
              </td>
            </tr>
          ))}
          <tr>
            <th>Bio</th>
            <td>
              <textarea
                className="textarea w-full min-h-28"
                value={String(apiData.BIO ?? '')}
                onChange={(e) => handleChange('BIO', e.target.value)}
              />
            </td>
          </tr>
          <tr>
            <th>Marchas</th>
            <td>
              {(apiData.marchas ?? []).map((m) => (
                <div key={m.ID_MARCHA}>
                  <a className="link" href={`/dashboard/marcha/${m.ID_MARCHA}`}>#{m.ID_MARCHA} {m.TITULO}</a>
                </div>
              ))}
            </td>
          </tr>
        </tbody>
      </table>

      <div className="divider py-2 my-2">Previsualización</div>
      {pending && pending.changedFields.length > 0 ? (
        <div className="overflow-x-auto">
          <table className="table table-zebra">
            <thead className="bg-neutral-content text-neutral"><tr><td>Campo</td><td>Valor actual</td><td>Nuevo valor</td></tr></thead>
            <tbody>
              {pending.changedFields.map((f) => (
                <tr key={f.key}><td>{f.key}</td><td>{fmt(f.previousValue)}</td><td>{fmt(f.newValue)}</td></tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : <div className="alert">No hay cambios pendientes.</div>}

      {state.status !== 'idle' && (
        <div className={`alert mt-3 ${state.status === 'success' ? 'alert-success' : state.status === 'saving' ? 'alert-info' : 'alert-error'}`}>
          <span>{state.code} - {state.msg}</span>
        </div>
      )}

      <div className="flex gap-2 mt-4">
        <button className="btn btn-neutral" disabled={state.status === 'saving'} onClick={save}>Guardar cambios</button>
        <button className="btn" disabled={state.status === 'saving'} onClick={reset}>Revertir cambios</button>
        <button className="btn btn-ghost" onClick={() => router.push('/dashboard')}>← Dashboard</button>
      </div>
    </div>
  );
}
