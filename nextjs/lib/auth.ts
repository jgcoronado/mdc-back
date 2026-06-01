export interface SessionUser {
  user: string;
  expiresAt: number;
}

export const login = async (credentials: { username: string; password: string }) => {
  const res = await fetch('/api/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(credentials),
    credentials: 'include',
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data?.msg || 'Error al iniciar sesión');
  return data as { login: boolean; user: string; expiresAt: number };
};

export const logout = async () => {
  await fetch('/api/login/logout', { method: 'POST', credentials: 'include' });
};

export const verifySession = async (): Promise<SessionUser | null> => {
  try {
    const res = await fetch('/api/login/verify', { credentials: 'include' });
    if (!res.ok) return null;
    const data = await res.json();
    return data?.authenticated ? { user: data.user, expiresAt: data.expiresAt } : null;
  } catch {
    return null;
  }
};
