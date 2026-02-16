<script setup>
import { reactive } from 'vue'
import { login } from '../../services/authService';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const form = reactive({
    username: '',
    password: ''
});
async function handleLogin() {
  let login_status = false;
  console.log("🚀 ~ handleLogin ~ login:", login_status)
  try {
    await login(form);
    console.log(form.username);
    router.push({
      name: 'dashboard',
      params: {
        name: form.username,
      },
    });
  } catch (error) {
    console.error('Error al iniciar sesión', error);
  }
};
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
        @keyup.enter="handleLogin"
        @click="handleLogin"
      >
        Entrar
      </button>
    </form>
  </fieldset>
</template>