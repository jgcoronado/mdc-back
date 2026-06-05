'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import AutocompleteMulti from '@/components/admin/AutocompleteMulti';
import AutocompleteSingle from '@/components/admin/AutocompleteSingle';
import { useAutocompleteSelect } from '@/hooks/useAutocompleteSelect';
import { buildMarchaInsertPayload, executeMarchaInsert, searchAutores, searchBandas, type MarchaInsertDraft } from '@/lib/adminApi';

type RequestState = { status: 'idle' | 'saving' | 'success' | 'error'; code: string; msg: string };
type AutorRow = { ID_AUTOR: number; NOMBRE: string; APELLIDOS: string; NOMBRE_COMPLETO: string };
type BandaRow = { ID_BANDA: number; NOMBRE_BREVE: string; NOMBRE_COMPLETO: string; LOCALIDAD: string };

const initialDraft: MarchaInsertDraft = {
  TITULO: '', FECHA: '', DEDICATORIA: '', LOCALIDAD: '', PROVINCIA: '',
  BANDA_ESTRENO: null, DETALLES_MARCHA: '', AUTORES_IDS: '',
};

const fmt = (v: unknown) => (v === null || v === undefined || v === '') ? '(vacío)' : String(v);

const autorLabel = (a: AutorRow) => `${(a.APELLIDOS ?? '').trim()} ${(a.NOMBRE ?? '').trim()}`.trim() || a.NOMBRE_COMPLETO || '';
const bandaLabel = (b: BandaRow) => {
  const base = (b.NOMBRE_COMPLETO || b.NOMBRE_BREVE || '')
    .replace(/agrupaci[oó]n musical/gi, 'AM')
    .replace(/banda de cornetas y tambores/gi, 'CCTT')
    .replace(/\s{2,}/g, ' ').trim();
  return b.LOCALIDAD ? `${base} (${b.LOCALIDAD})` : base;
};

export default function MarchaAddPage() {
  const router = useRouter();
  const [draft, setDraft] = useState<MarchaInsertDraft>({ ...initialDraft });
  const [state, setState] = useState<RequestState>({ status: 'idle', code: '', msg: '' });
  const [createdId, setCreatedId] = useState<number | null>(null);

  const autorSelector = useAutocompleteSelect<AutorRow>({ fetchFn: searchAutores, idKey: 'ID_AUTOR', minChars: 6, limit: 8, multiple: true });
  const bandaSelector = useAutocompleteSelect<BandaRow>({ fetchFn: searchBandas, idKey: 'ID_BANDA', minChars: 6, limit: 5, multiple: false });

  useEffect(() => {
    const ids = (autorSelector.selected as AutorRow[]).map((a) => a.ID_AUTOR).join(',');
    setDraft((d) => ({ ...d, AUTORES_IDS: ids }));
  }, [autorSelector.selected]);

  useEffect(() => {
    const banda = autorSelector.selected as BandaRow | null;
    setDraft((d) => ({ ...d, BANDA_ESTRENO: (bandaSelector.selected as BandaRow | null)?.ID_BANDA ?? null }));
  }, [bandaSelector.selected]);

  const pending = buildMarchaInsertPayload(draft);

  async function handleSubmit() {
    setState({ status: 'saving', code: '', msg: '' });
    try {
      const result = await executeMarchaInsert(pending);
      setState({ status: result.code === 'CREATED' ? 'success' : 'error', code: result.code ?? 'UNKNOWN', msg: result.msg ?? '' });
      if (result.code === 'CREATED') setCreatedId(result.marchaId ?? null);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { code?: string; msg?: string } } };
      setState({ status: 'error', code: e?.response?.data?.code ?? 'REQUEST_ERROR', msg: e?.response?.data?.msg ?? 'No se pudo crear la marcha.' });
    }
  }

  function resetForm() {
    setDraft({ ...initialDraft });
    autorSelector.reset();
    bandaSelector.reset();
    setState({ status: 'idle', code: '', msg: '' });
    setCreatedId(null);
  }

  const textField = (label: string, key: keyof MarchaInsertDraft, placeholder = '') => (
    <tr key={key}>
      <th>{label}</th>
      <td><input className="input w-full" type="text" value={String(draft[key] ?? '')} placeholder={placeholder || label} onChange={(e) => { setDraft((d) => ({ ...d, [key]: e.target.value })); setState((s) => s.status !== 'saving' ? { status: 'idle', code: '', msg: '' } : s); }} /></td>
    </tr>
  );

  return (
    <div>
      <div className="headDetail">Alta de marcha</div>
      <table className="table table-zebra">
        <tbody>
          {textField('Título', 'TITULO')}
          {textField('Fecha', 'FECHA', 'Ej: 1998')}
          {textField('Dedicatoria', 'DEDICATORIA')}
          {textField('Localidad', 'LOCALIDAD')}
          {textField('Provincia', 'PROVINCIA')}
          <tr>
            <th>ID banda estreno</th>
            <td>
              <AutocompleteSingle<BandaRow>
                selectedItem={bandaSelector.selected as BandaRow | null}
                query={bandaSelector.query}
                suggestions={bandaSelector.suggestions}
                loading={bandaSelector.loading}
                idKey="ID_BANDA"
                placeholder="Escribe nombre de banda (min. 6 caracteres)"
                loadingText="Buscando bandas..."
                labelBuilder={bandaLabel}
                onQueryChange={bandaSelector.setQuery}
                onSelect={bandaSelector.selectItem}
                onRemove={bandaSelector.removeItem}
              />
            </td>
          </tr>
          <tr>
            <th>Detalles</th>
            <td><textarea className="textarea w-full min-h-28" value={draft.DETALLES_MARCHA} placeholder="Información adicional" onChange={(e) => setDraft((d) => ({ ...d, DETALLES_MARCHA: e.target.value }))} /></td>
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
                placeholder="Escribe apellido/nombre (min. 6 caracteres)"
                loadingText="Buscando autores..."
                labelBuilder={autorLabel}
                onQueryChange={autorSelector.setQuery}
                onSelect={autorSelector.selectItem}
                onRemove={autorSelector.removeItem}
              />
            </td>
          </tr>
        </tbody>
      </table>

      <div className="divider py-2 my-2">Previsualización</div>
      <div className="overflow-x-auto">
        <table className="table table-zebra">
          <thead className="bg-neutral-content text-neutral"><tr><td>Campo</td><td>Valor nuevo</td></tr></thead>
          <tbody>
            {pending.previewFields.map((f) => <tr key={f.key}><td>{f.key}</td><td>{fmt(f.newValue)}</td></tr>)}
            <tr><td>AUTORES_IDS</td><td>{fmt(pending.autoresIds)}</td></tr>
          </tbody>
        </table>
      </div>

      {state.status !== 'idle' && (
        <div className={`alert mt-3 ${state.status === 'success' ? 'alert-success' : state.status === 'saving' ? 'alert-info' : 'alert-error'}`}>
          <span>{state.code} - {state.msg}</span>
        </div>
      )}

      {createdId && (
        <div className="flex gap-2 mt-3 items-center flex-wrap">
          <span className="font-semibold">Marcha #{createdId} creada.</span>
          <button className="btn btn-sm btn-neutral" onClick={() => router.push(`/dashboard/marcha/${createdId}`)}>Ir a editar</button>
          <button className="btn btn-sm" onClick={() => window.open(`/marcha/${createdId}`, '_blank')}>Ver en público</button>
        </div>
      )}

      {!draft.AUTORES_IDS && (
        <div className="alert alert-warning mt-3">
          <span>Debes añadir al menos un autor antes de crear la marcha.</span>
        </div>
      )}

      <div className="flex gap-2 mt-4">
        <button className="btn btn-neutral" disabled={state.status === 'saving' || !draft.AUTORES_IDS} onClick={handleSubmit}>Crear marcha</button>
        <button className="btn" disabled={state.status === 'saving'} onClick={resetForm}>Limpiar formulario</button>
        <button className="btn btn-ghost" onClick={() => router.push('/dashboard')}>← Dashboard</button>
      </div>
    </div>
  );
}
