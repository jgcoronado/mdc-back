<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { getCurrentUser, logout } from '../../services/authService';

const router = useRouter();

const user = ref('');

onMounted(() => {
  const session = getCurrentUser();
  user.value = session?.user || '';
});

function goToLogout() {
  logout();
  router.push('/login');
}
</script>

<template>
  <h1>WELCOME TO DASHBOARD {{ user }}</h1>
    <div class="divider"></div>
    <button
      class="btn"
      @click="goToLogout()"
    >
      Logout
    </button>
</template>
