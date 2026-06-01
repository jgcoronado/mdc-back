'use client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { buildAutorInsertPayload, executeAutorInsert, type AutorInsertDraft } from '@/lib/adminApi';

type RequestState = { status: 'idle' | 'saving' | 'success' | 'error'; code: string; msg: string };

const initialDraft: AutorInsertDraft = { NOMBRE: '', APELLIDOS: '', F_NAC: '', LUGAR_NAC: '', F_DEF: '', BIO: '' };
const fmt = (v: unknown) => (v === null || v === undefined || v === '') ? '(vacío)' : String(v);

export default function AutorAddPage() {
  const router = useRouter();
  const [draft, setDraft] = useState<AutorInsertDraft>({ ...initialDraft });
  const [state, setState] = useState<RequestState>({ status: 'idle', code: '', msg: '' });

  const pending = buildAutorInsertPayload(draft);

  const update = (key: keyof AutorInsertDraft) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setDraft((d) => ({ ...d, [key]: e.target.value }));
    setState((s) => s.status !== 'saving' ? { status: 'idle', code: '', msg: '' } : s);
  };

  async function handleSubmit() {
    setState({ status: 'saving', code: '', msg: '' });
    try {
      const result = await executeAutorInsert(pending);
      setState({ status: result.code === 'CREATED' ? 'success' : 'error', code: result.code ?? 'UNKNOWN', msg: result.msg ?? '' });
    } catch (err: unknown) {
      const e = err as { response?: { data?: { code?: string; msg?: string } } };
      setState({ status: 'error', code: e?.response?.data?.code ?? 'REQUEST_ERROR', msg: e?.response?.data?.msg ?? 'No se pudo crear el autor.' });
    }
  }

  function resetForm() {
    setDraft({ ...initialDraft });
    setState({ status: 'idle', code: '', msg: '' });
  }

  return (
    <div className="md:min-w-4xl">
      <div className="headDetail">Alta de autor</div>
      <table className="table table-zebra">
        <tbody>
          {([
            ['Nombre', 'NOMBRE', 'Nombre'],
            ['Apellidos', 'APELLIDOS', 'Apellidos'],
            ['Fecha de nacimiento', 'F_NAC', 'Ej: 05/12/1982'],
            ['Lugar de nacimiento', 'LUGAR_NAC', 'Localidad o ciudad'],
            ['Fecha de defunción', 'F_DEF', 'Opcional'],
          ] as [string, keyof AutorInsertDraft, string][]).map(([label, key, placeholder]) => (
            <tr key={key}>
              <th>{label}</th>
              <td><input className="input w-full" type="text" value={draft[key]} placeholder={placeholder} onChange={update(key)} /></td>
            </tr>
          ))}
          <tr>
            <th>Bio</th>
            <td><textarea className="textarea w-full min-h-28" value={draft.BIO} placeholder="Resumen breve" onChange={update('BIO')} /></td>
          </tr>
        </tbody>
      </table>

      <div className="divider py-2 my-2">Previsualización</div>
      <div className="overflow-x-auto">
        <table className="table table-zebra">
          <thead className="bg-neutral-content text-neutral"><tr><td>Campo</td><td>Valor nuevo</td></tr></thead>
          <tbody>{pending.previewFields.map((f) => <tr key={f.key}><td>{f.key}</td><td>{fmt(f.newValue)}</td></tr>)}</tbody>
        </table>
      </div>

      <div className="mt-4">
        <p className="font-semibold">SQL preparada:</p>
        <pre className="bg-base-200 p-3 rounded-box overflow-x-auto">{pending.sqlPreview}</pre>
        <p className="font-semibold mt-2">Parámetros:</p>
        <pre className="bg-base-200 p-3 rounded-box overflow-x-auto">{JSON.stringify(pending.valuesToInsert)}</pre>
      </div>

      {state.status !== 'idle' && (
        <div className={`alert mt-3 ${state.status === 'success' ? 'alert-success' : state.status === 'saving' ? 'alert-info' : 'alert-error'}`}>
          <span>{state.code} - {state.msg}</span>
        </div>
      )}

      <div className="flex gap-2 mt-4">
        <button className="btn btn-neutral" disabled={state.status === 'saving'} onClick={handleSubmit}>Crear autor</button>
        <button className="btn" disabled={state.status === 'saving'} onClick={resetForm}>Limpiar formulario</button>
        <button className="btn btn-ghost" onClick={() => router.push('/dashboard')}>← Dashboard</button>
      </div>
    </div>
  );
}
