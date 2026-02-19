import { createWebHistory, createRouter } from 'vue-router';
import Login from '@/components/admin/Login.vue';
import Dashboard from '@/components/admin/Dashboard.vue';
import MarchaEdit from '@/components/admin/MarchaEdit.vue';
import MarchaAdd from '@/components/admin/MarchaAdd.vue';
import { isAuthenticated } from '@/services/authService';

import viewPages from './viewPages.js';

const routes = [
  { path: '/login', name: 'login', component: Login },
  { path: '/dashboard/',
    name: 'dashboard', 
    component: Dashboard, 
    meta: { requiresAuth: true } 
  },
  { path: '/dashboard/marcha/add',
    name: 'marchaAdd',
    component: MarchaAdd,
    meta: { requiresAuth: true }
  },
  { path: '/dashboard/marcha/:id(\\d+)',
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

router.beforeEach(async (to, from, next) => {
  const authenticated = await isAuthenticated();
  if (to.matched.some(record => record.meta.requiresAuth) && !authenticated) {
    next('/login');
    return;
  }
  if (to.name === 'login' && authenticated) {
    next('/dashboard');
    return;
  }
  next();
});

export { router };
