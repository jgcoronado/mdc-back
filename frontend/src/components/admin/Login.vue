<script setup>
import { reactive, ref } from 'vue'
import { login } from '../../services/authService';
import { useRouter } from 'vue-router';

const router = useRouter();
const form = reactive({
  username: '',
  password: ''
});
const errorMessage = ref('');

async function handleLogin() {
  errorMessage.value = '';
  try {
    const result = await login(form);
    if (result?.login) {
      router.push({ name: 'dashboard' });
      return;
    }
    errorMessage.value = 'Credenciales no válidas';
  } catch (error) {
    errorMessage.value = error?.response?.data?.msg || 'Error al iniciar sesión';
  }
}
</script>
<template>
  <fieldset
    class="fieldset bg-base-200 border-base-300 rounded-box w-ms border p-4 md:min-w-xl place-items-center"
  >
    <form @submit.prevent="handleLogin">
      <label class="label">Usuario</label>
      <input
      required
        class="input w-full text-base"
        type="text"
        v-model="form.username"
        placeholder="Usuario"
      />
      <label class="label">Contraseña</label>
      <input
        required
        class="input w-full text-base"
        type="password"
        v-model="form.password"
        placeholder="Contraseña"
      />
      <button
        class="btn btn-neutral mt-4"
        type="submit"
      >
        Entrar
      </button>
      <p v-if="errorMessage" class="text-error mt-2">{{ errorMessage }}</p>
    </form>
  </fieldset>
</template>
