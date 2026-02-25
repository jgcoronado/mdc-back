<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { getCurrentUser, logout } from '../../services/authService';

const router = useRouter();

const user = ref('');
const marchaId = ref('');

onMounted(() => {
  const session = getCurrentUser();
  user.value = session?.user || '';
});

async function goToLogout() {
  await logout();
  router.push('/login');
}

function goToMarchaEdit() {
  if (!marchaId.value) {
    return;
  }
  router.push({
    name: 'marchaEdit',
    params: { id: marchaId.value },
  });
}

function goToMarchaAdd() {
  router.push({ name: 'marchaAdd' });
}

function goToAutorAdd() {
  router.push({ name: 'autorAdd' });
}
</script>

<template>
  <h1>WELCOME TO DASHBOARD {{ user }}</h1>
  <div class="divider"></div>
  <div class="flex flex-wrap gap-3 items-end">
    <fieldset class="fieldset">
      <label class="label">ID de marcha a editar</label>
      <input
        class="input"
        type="number"
        min="1"
        v-model="marchaId"
        placeholder="Ej: 125"
        @keyup.enter="goToMarchaEdit()"
      />
    </fieldset>
    <button
      class="btn btn-neutral"
      @click="goToMarchaEdit()"
    >
      Ir a edición
    </button>
    <button
      class="btn btn-neutral"
      @click="goToMarchaAdd()"
    >
      Nueva marcha
    </button>
    <button
      class="btn btn-neutral"
      @click="goToAutorAdd()"
    >
      Nuevo autor
    </button>
    <button
      class="btn"
      @click="goToLogout()"
    >
      Logout
    </button>
  </div>
</template>
