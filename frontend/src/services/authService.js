import axios from 'axios';

const API_BASE_URL = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');
const API_URL = `${API_BASE_URL}/login`;
const STORAGE_KEY = 'auth_session';

export const login = async (credentials) => {
  const response = await axios.post(`${API_URL}`, credentials, { withCredentials: true });
  if (response.data?.login) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      user: response.data.user,
      expiresAt: response.data.expiresAt,
    }));
  }
  return response.data;
};

export const logout = async () => {
  try {
    await axios.post(`${API_URL}/logout`, {}, { withCredentials: true });
  } finally {
    localStorage.removeItem(STORAGE_KEY);
  }
};

export const getCurrentUser = () => {
  const raw = localStorage.getItem(STORAGE_KEY);
  if (!raw) {
    return null;
  }

  try {
    const session = JSON.parse(raw);
    if (!session?.user || !session?.expiresAt || Date.now() > session.expiresAt) {
      localStorage.removeItem(STORAGE_KEY);
      return null;
    }
    return session;
  } catch {
    localStorage.removeItem(STORAGE_KEY);
    return null;
  }
};

export const isAuthenticated = async () => {
  try {
    const response = await axios.get(`${API_URL}/verify`, { withCredentials: true });
    if (response.data?.authenticated) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        user: response.data.user,
        expiresAt: response.data.expiresAt,
      }));
      return true;
    }
    localStorage.removeItem(STORAGE_KEY);
    return false;
  } catch {
    localStorage.removeItem(STORAGE_KEY);
    return false;
  }
};
