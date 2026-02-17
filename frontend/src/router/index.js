import { createWebHistory, createRouter } from 'vue-router';
import Login from '@/components/admin/Login.vue';
import Dashboard from '@/components/admin/Dashboard.vue';
import MarchaEdit from '@/components/admin/MarchaEdit.vue';
import { isAuthenticated } from '@/services/authService';

import viewPages from './viewPages.js';

const routes = [
  { path: '/login', name: 'login', component: Login },
  { path: '/dashboard/',
    name: 'dashboard', 
    component: Dashboard, 
    meta: { requiresAuth: true } 
  },
  { path: '/dashboard/marcha/:id',
    name: 'marchaEdit', 
    component: MarchaEdit, 
    meta: { requiresAuth: true } 
  },
  ...viewPages,
]

const router = createRouter({
  history: createWebHistory(),
  linkExactActiveClass: "active",
  base: import.meta.env.BASE_URL,
  routes,
})

router.beforeEach((to, from, next) => {
  if (to.matched.some(record => record.meta.requiresAuth) && !isAuthenticated()) {
    next('/login');
  } else if (to.name === 'login' && isAuthenticated()) {
    next('/dashboard');
  } else {
    next();
  }
});

export { router };
