'use client';
import { useState, useEffect, useCallback } from 'react';
import { useRouter, useParams } from 'next/navigation';
import type { MarchaDetail } from '@/lib/api';
import { buildMarchaUpdatePayload, executeMarchaUpdate, executeEditMarchaAutores, searchAutores } from '@/lib/adminApi';
import AutocompleteMulti from '@/components/admin/AutocompleteMulti';
import { useAutocompleteSelect } from '@/hooks/useAutocompleteSelect';

type AutorRow = { ID_AUTOR: number; NOMBRE: string; APELLIDOS: string; NOMBRE_COMPLETO: string; NOMBRE_ART: string | null };

type RequestState = { status: 'idle' | 'saving' | 'success' | 'error' | 'no_changes'; code: string; msg: string };
type AutoresState = { status: 'idle' | 'saving' | 'success' | 'error'; code: string; msg: string };

const autorLabel = (a: AutorRow) => `${(a.APELLIDOS ?? '').trim()} ${(a.NOMBRE ?? '').trim()}`.trim() || a.NOMBRE_COMPLETO || '';

const fmt = (v: unknown) => (v === null || v === undefined || v === '') ? '(vacío)' : String(v);

export default function MarchaEditPage() {
  const router = useRouter();
  const { id } = useParams<{ id: string }>();
  const [apiData, setApiData] = useState<Partial<MarchaDetail> | null>(null);
  const [oldData, setOldData] = useState<Partial<MarchaDetail> | null>(null);
  const [state, setState] = useState<RequestState>({ status: 'idle', code: '', msg: '' });
  const [autoresState, setAutoresState] = useState<AutoresState>({ status: 'idle', code: '', msg: '' });

  const autorSelector = useAutocompleteSelect<AutorRow>({ fetchFn: searchAutores, idKey: 'ID_AUTOR', minChars: 4, limit: 8, multiple: true });

  useEffect(() => {
    fetch(`/api/marcha/${id}`).then((r) => r.json()).then((data) => {
      setApiData({ ...data });
      setOldData({ ...data });
      if (Array.isArray(data.AUTOR)) {
        autorSelector.setInitialItems(data.AUTOR.map((a: { autorId: string; nombre: string }) => ({
          ID_AUTOR: Number(a.autorId),
          NOMBRE: '',
          APELLIDOS: '',
          NOMBRE_COMPLETO: a.nombre,
          NOMBRE_ART: null,
        })));
      }
    });
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const pending = apiData && oldData ? buildMarchaUpdatePayload(oldData, apiData) : null;

  const handleChange = useCallback((field: keyof MarchaDetail, value: unknown) => {
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
      const result = await executeMarchaUpdate(pending);
      setState({ status: result.code === 'UPDATED' ? 'success' : 'error', code: result.code ?? 'UNKNOWN', msg: result.msg ?? '' });
      if (result.code === 'UPDATED') setOldData({ ...apiData });
    } catch (err: unknown) {
      const e = err as { response?: { data?: { code?: string; msg?: string } } };
      setState({ status: 'error', code: e?.response?.data?.code ?? 'REQUEST_ERROR', msg: e?.response?.data?.msg ?? 'No se pudo actualizar la marcha.' });
    }
  }

  function reset() {
    setApiData({ ...oldData });
    setState({ status: 'idle', code: '', msg: '' });
  }

  async function saveAutores() {
    const selectedAutores = autorSelector.selected as AutorRow[];
    if (selectedAutores.length === 0) {
      setAutoresState({ status: 'error', code: 'EMPTY', msg: 'Debe haber al menos un autor.' });
      return;
    }
    setAutoresState({ status: 'saving', code: '', msg: '' });
    try {
      const result = await executeEditMarchaAutores({
        marchaId: apiData!.ID_MARCHA as number,
        autoresIds: selectedAutores.map((a) => a.ID_AUTOR),
      });
      setAutoresState({ status: result.code === 'UPDATED' ? 'success' : 'error', code: result.code ?? 'UNKNOWN', msg: result.msg ?? '' });
    } catch {
      setAutoresState({ status: 'error', code: 'REQUEST_ERROR', msg: 'No se pudieron actualizar los autores.' });
    }
  }

  if (!apiData) return <p>Cargando...</p>;

  const fields: { label: string; key: keyof MarchaDetail; type?: string }[] = [
    { label: 'Título', key: 'TITULO' },
    { label: 'Fecha', key: 'FECHA' },
    { label: 'Dedicatoria', key: 'DEDICATORIA' },
    { label: 'Localidad', key: 'LOCALIDAD' },
    { label: 'Provincia', key: 'PROVINCIA' },
    { label: 'Audio', key: 'AUDIO' },
    { label: 'ID banda estreno', key: 'BANDA_ESTRENO', type: 'number' },
  ];

  return (
    <div className="md:min-w-4xl">
      <div className="headDetail">Edición de marcha #{apiData.ID_MARCHA}</div>
      <table className="table table-zebra">
        <tbody>
          {fields.map(({ label, key, type }) => (
            <tr key={key}>
              <th>{label}</th>
              <td>
                <input
                  className="input w-full"
                  type={type ?? 'text'}
                  value={String(apiData[key] ?? '')}
                  onChange={(e) => handleChange(key, type === 'number' ? (e.target.value === '' ? null : Number(e.target.value)) : e.target.value)}
                />
              </td>
            </tr>
          ))}
          <tr>
            <th>Detalles</th>
            <td>
              <textarea
                className="textarea w-full min-h-28"
                value={String(apiData.DETALLES_MARCHA ?? '')}
                onChange={(e) => handleChange('DETALLES_MARCHA', e.target.value)}
              />
            </td>
          </tr>
          <tr>
            <th>Autor(es)</th>
            <td>
              <AutocompleteMulti<AutorRow>
                selectedItems={autorSelector.selected as AutorRow[]}
                query={autorSelector.query}
                suggestions={autorSelector.suggestions}
                loading={autorSelector.loading}
                idKey="ID_AUTOR"
                minChars={4}
                placeholder="Escribe apellido/nombre (min. 4 caracteres)"
                loadingText="Buscando autores..."
                labelBuilder={autorLabel}
                onQueryChange={autorSelector.setQuery}
                onSelect={autorSelector.selectItem}
                onRemove={autorSelector.removeItem}
              />
              <div className="flex gap-2 mt-2 items-center">
                <button className="btn btn-sm btn-neutral" disabled={autoresState.status === 'saving'} onClick={saveAutores}>
                  Guardar autores
                </button>
                {autoresState.status !== 'idle' && (
                  <span className={`text-sm ${autoresState.status === 'success' ? 'text-success' : autoresState.status === 'saving' ? 'text-info' : 'text-error'}`}>
                    {autoresState.code} - {autoresState.msg}
                  </span>
                )}
              </div>
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

      {pending?.sqlPreview && (
        <div className="mt-4">
          <p className="font-semibold">SQL preparada:</p>
          <pre className="bg-base-200 p-3 rounded-box overflow-x-auto">{pending.sqlPreview}</pre>
          <p className="font-semibold mt-2">Parámetros:</p>
          <pre className="bg-base-200 p-3 rounded-box overflow-x-auto">{JSON.stringify(pending.params)}</pre>
        </div>
      )}

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
