<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router'

const router = useRouter()

const nombre= ref('');
let urlJoin = ref({});

function goToAutorList() {
  const url = [];
  if(nombre.value) url.push(`nombre=${nombre.value}`);
  urlJoin = url.join('&');

  router.push({
    name: 'autorList',
    params: {
      query: urlJoin,
    },
  });
};
</script>

<template>
  <fieldset
    class="fieldset bg-base-200 border-base-300 rounded-box w-ms border p-4 md:min-w-xl place-items-center"
    @keyup.enter="goToAutorList()"
  >
    <legend class="fieldset-legend">Buscador de compositores</legend>

    <label class="label">Nombre</label>
    <input
      class="input w-full text-base"
      type="text"
      v-model="nombre"
      placeholder="Ejemplo: Manuel Rodríguez Ruiz"
    />
    <button
      class="btn btn-neutral mt-4"
      @keyup.enter="goToAutorList()"
      @click="goToAutorList()"
    >
      Buscar
    </button>
  </fieldset>
</template>

