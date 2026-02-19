import { ref, watch } from 'vue';
import axios from 'axios';

const BASE_URL = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');

export function useAutocompleteSelect({
  endpoint,
  idKey,
  minChars = 6,
  limit = 5,
  queryParam = 'nombre',
  multiple = true,
}) {
  const query = ref('');
  const suggestions = ref([]);
  const loading = ref(false);
  const selected = ref(multiple ? [] : null);
  let requestSeq = 0;

  const getSelectedIds = () => {
    if (multiple) {
      return new Set((selected.value || []).map((item) => Number(item?.[idKey])));
    }
    if (selected.value && selected.value[idKey] !== undefined) {
      return new Set([Number(selected.value[idKey])]);
    }
    return new Set();
  };

  watch(
    () => query.value,
    async (value) => {
      const text = (value || '').trim();
      if (text.length < minChars) {
        suggestions.value = [];
        loading.value = false;
        return;
      }

      const seq = ++requestSeq;
      loading.value = true;

      try {
        const apiUrl = `${BASE_URL}${endpoint}?${queryParam}=${encodeURIComponent(text)}`;
        const res = await axios.get(apiUrl);
        if (seq !== requestSeq) return;

        const rows = Array.isArray(res?.data?.data) ? res.data.data : [];
        const selectedIds = getSelectedIds();
        suggestions.value = rows
          .filter((item) => !selectedIds.has(Number(item?.[idKey])))
          .slice(0, limit);
      } catch (_) {
        if (seq === requestSeq) {
          suggestions.value = [];
        }
      } finally {
        if (seq === requestSeq) {
          loading.value = false;
        }
      }
    }
  );

  const selectItem = (item) => {
    if (!item || item[idKey] === undefined || item[idKey] === null) return;
    if (multiple) {
      const exists = (selected.value || []).some(
        (current) => Number(current?.[idKey]) === Number(item[idKey])
      );
      if (exists) return;
      selected.value = [...(selected.value || []), item];
    } else {
      selected.value = item;
    }
    query.value = '';
    suggestions.value = [];
  };

  const removeItem = (id) => {
    if (multiple) {
      selected.value = (selected.value || []).filter(
        (item) => Number(item?.[idKey]) !== Number(id)
      );
      return;
    }

    if (id === undefined || id === null) {
      selected.value = null;
      return;
    }
    if (selected.value && Number(selected.value[idKey]) === Number(id)) {
      selected.value = null;
    }
  };

  const reset = () => {
    query.value = '';
    suggestions.value = [];
    loading.value = false;
    selected.value = multiple ? [] : null;
  };

  return {
    query,
    suggestions,
    loading,
    selected,
    selectItem,
    removeItem,
    reset,
  };
}
