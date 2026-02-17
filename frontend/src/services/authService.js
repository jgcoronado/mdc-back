import axios from 'axios';

const API_BASE_URL = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');
const API_URL = `${API_BASE_URL}/login`;
const STORAGE_KEY = 'auth_session';

export const login = async (credentials) => {
  const response = await axios.post(`${API_URL}`, credentials);
  if (response.data?.token) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(response.data));
  }
  return response.data;
};

export const logout = () => {
  localStorage.removeItem(STORAGE_KEY);
};

export const getCurrentUser = () => {
  const raw = localStorage.getItem(STORAGE_KEY);
  if (!raw) {
    return null;
  }

  try {
    const session = JSON.parse(raw);
    if (!session?.token || !session?.expiresAt || Date.now() > session.expiresAt) {
      localStorage.removeItem(STORAGE_KEY);
      return null;
    }
    return session;
  } catch {
    localStorage.removeItem(STORAGE_KEY);
    return null;
  }
};

export const isAuthenticated = () => Boolean(getCurrentUser());
