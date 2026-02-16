<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useRouter, useRoute } from 'vue-router';
import { logout } from '../../services/authService';

const router = useRouter();
const route = useRoute();

const user = ref('');

onMounted(() => {
  user.value = route.params.name;

  // let token=localStorage.getItem("token");
  // console.log(token);
  // axios
  //   .get("http://localhost:3000/auth/user", {
  //     headers: {
  //       Authorization: `Bearer ${token}`,
  //       token: token
  //     }
  //   })
  //   .then(res => {
  //     console.log(res);
  //   });
});

function goToLogout() {
  logout();
  router.push('/login');
};
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

