<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router'

const router = useRouter()

const nombre= ref('');
let urlJoin = ref({});

function goToDiscoList() {
  const url = [];
  if(nombre.value) url.push(`nombre=${nombre.value}`);
  urlJoin = url.join('&');

  router.push({
    name: 'discoList',
    params: {
      query: urlJoin,
    },
  });
};
</script>

<template>
  <fieldset
    class="fieldset bg-base-200 border-base-300 rounded-box w-ms border p-4 md:min-w-xl place-items-center"
    @keyup.enter="goToDiscoList()"
  >
    <legend class="fieldset-legend">Buscador de discos de música procesional</legend>

    <label class="label">Nombre</label>
    <input
      class="input w-full text-base"
      type="text"
      v-model="nombre"
      placeholder="Ejemplo: Fons Vitae"
    />
    <button
      class="btn btn-neutral mt-4"
      @keyup.enter="goToDiscoList()"
      @click="goToDiscoList()"
    >
      Buscar
    </button>
  </fieldset>
</template>

