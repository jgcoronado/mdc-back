'use client';

interface Props<T> {
  selectedItem: T | null;
  query: string;
  suggestions: T[];
  loading: boolean;
  minChars?: number;
  idKey: keyof T;
  placeholder?: string;
  loadingText?: string;
  noMatchesText?: string;
  labelBuilder: (item: T) => string;
  onQueryChange: (v: string) => void;
  onSelect: (item: T) => void;
  onRemove: (id: unknown) => void;
}

export default function AutocompleteSingle<T extends Record<string, unknown>>({
  selectedItem, query, suggestions, loading, minChars = 6, idKey,
  placeholder = '', loadingText = 'Buscando...', noMatchesText = 'No hay coincidencias.',
  labelBuilder, onQueryChange, onSelect, onRemove,
}: Props<T>) {
  return (
    <div className="grid gap-2">
      {selectedItem && (
        <div className="badge badge-neutral gap-2 py-4">
          <span>{labelBuilder(selectedItem)}</span>
          <button className="btn btn-xs btn-ghost" type="button" onClick={() => onRemove(selectedItem[idKey])}>x</button>
        </div>
      )}
      <input
        className="input w-full"
        type="text"
        value={query}
        placeholder={placeholder}
        onChange={(e) => onQueryChange(e.target.value)}
      />
      {loading && <div className="text-sm opacity-70">{loadingText}</div>}
      {!loading && suggestions.length > 0 && (
        <ul className="menu bg-base-200 rounded-box max-h-56 overflow-auto">
          {suggestions.map((item) => (
            <li key={String(item[idKey])}>
              <a onClick={() => onSelect(item)}>{labelBuilder(item)} (ID {String(item[idKey])})</a>
            </li>
          ))}
        </ul>
      )}
      {!loading && suggestions.length === 0 && query.trim().length >= minChars && (
        <div className="text-sm opacity-70">{noMatchesText}</div>
      )}
    </div>
  );
}
