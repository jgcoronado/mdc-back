'use client';
import { useState, useEffect, useRef, useCallback } from 'react';

interface Options<T> {
  fetchFn: (query: string) => Promise<T[]>;
  idKey: keyof T;
  minChars?: number;
  limit?: number;
  multiple?: boolean;
}

export function useAutocompleteSelect<T extends Record<string, unknown>>({
  fetchFn,
  idKey,
  minChars = 6,
  limit = 5,
  multiple = true,
}: Options<T>) {
  const [query, setQuery] = useState('');
  const [suggestions, setSuggestions] = useState<T[]>([]);
  const [loading, setLoading] = useState(false);
  const [selected, setSelected] = useState<T[] | T | null>(multiple ? [] : null);
  const seq = useRef(0);

  const getSelectedIds = useCallback((): Set<number> => {
    if (multiple) return new Set((selected as T[]).map((i) => Number(i[idKey])));
    if (selected) return new Set([Number((selected as T)[idKey])]);
    return new Set();
  }, [selected, idKey, multiple]);

  useEffect(() => {
    const text = query.trim();
    if (text.length < minChars) { setSuggestions([]); setLoading(false); return; }
    const current = ++seq.current;
    setLoading(true);
    fetchFn(text).then((rows) => {
      if (current !== seq.current) return;
      const ids = getSelectedIds();
      setSuggestions(rows.filter((r) => !ids.has(Number(r[idKey]))).slice(0, limit));
      setLoading(false);
    }).catch(() => {
      if (current === seq.current) { setSuggestions([]); setLoading(false); }
    });
  }, [query]);

  const selectItem = useCallback((item: T) => {
    if (multiple) {
      setSelected((prev) => {
        const list = (prev as T[]) ?? [];
        if (list.some((x) => Number(x[idKey]) === Number(item[idKey]))) return list;
        return [...list, item];
      });
    } else {
      setSelected(item);
    }
    setQuery('');
    setSuggestions([]);
  }, [idKey, multiple]);

  const removeItem = useCallback((id: unknown) => {
    if (multiple) {
      setSelected((prev) => ((prev as T[]) ?? []).filter((x) => Number(x[idKey]) !== Number(id)));
    } else {
      setSelected(null);
    }
  }, [idKey, multiple]);

  const reset = useCallback(() => {
    setQuery(''); setSuggestions([]); setLoading(false);
    setSelected(multiple ? [] : null);
  }, [multiple]);

  return { query, setQuery, suggestions, loading, selected, selectItem, removeItem, reset };
}
