<script setup>
const props = defineProps({
  selectedItem: {
    type: Object,
    default: null,
  },
  query: {
    type: String,
    default: '',
  },
  suggestions: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  minChars: {
    type: Number,
    default: 6,
  },
  placeholder: {
    type: String,
    default: '',
  },
  loadingText: {
    type: String,
    default: 'Buscando...',
  },
  noMatchesText: {
    type: String,
    default: 'No hay coincidencias.',
  },
  labelBuilder: {
    type: Function,
    required: true,
  },
  idKey: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['update:query', 'select', 'remove']);
</script>

<template>
  <div class="grid gap-2">
    <div v-if="selectedItem" class="badge badge-neutral gap-2 py-4">
      <span>{{ labelBuilder(selectedItem) }}</span>
      <button
        class="btn btn-xs btn-ghost"
        type="button"
        @click="emit('remove', selectedItem[idKey])"
      >
        x
      </button>
    </div>

    <input
      class="input w-full"
      type="text"
      :value="query"
      :placeholder="placeholder"
      @input="emit('update:query', $event.target.value)"
    />

    <div v-if="loading" class="text-sm opacity-70">{{ loadingText }}</div>
    <ul
      v-else-if="suggestions.length > 0"
      class="menu bg-base-200 rounded-box max-h-56 overflow-auto"
    >
      <li v-for="item in suggestions" :key="item[idKey]">
        <a @click="emit('select', item)">
          {{ labelBuilder(item) }} (ID {{ item[idKey] }})
        </a>
      </li>
    </ul>
    <div
      v-else-if="query.trim().length >= minChars"
      class="text-sm opacity-70"
    >
      {{ noMatchesText }}
    </div>
  </div>
</template>
