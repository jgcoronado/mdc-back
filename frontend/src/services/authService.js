import axios from 'axios';

const API_BASE_URL = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');
const API_URL = `${API_BASE_URL}/login`;

export const login = async (credentials) => {
  const response = await axios.post(`${API_URL}`, credentials);
  if (response.data.token) {
    localStorage.setItem('user', JSON.stringify(response.data));
  }
  return response.data;
};

export const logout = () => {
  localStorage.removeItem('user');
};

export const getCurrentUser = () => {
  return JSON.parse(localStorage.getItem('user'));
};
