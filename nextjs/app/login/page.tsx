'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { login, verifySession } from '@/lib/auth';

export default function LoginPage() {
  const router = useRouter();
  const [form, setForm] = useState({ username: '', password: '' });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    verifySession().then((s) => { if (s) router.replace('/dashboard'); });
  }, [router]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const result = await login(form);
      if (result?.login) router.push('/dashboard');
      else setError('Credenciales no válidas');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Error al iniciar sesión');
    } finally {
      setLoading(false);
    }
  }

  return (
    <fieldset className="fieldset bg-base-200 border-base-300 rounded-box w-ms border p-4 md:min-w-xl place-items-center">
      <form onSubmit={handleSubmit}>
        <label className="label">Usuario</label>
        <input
          required
          className="input w-full text-base"
          type="text"
          value={form.username}
          placeholder="Usuario"
          onChange={(e) => setForm((f) => ({ ...f, username: e.target.value }))}
        />
        <label className="label">Contraseña</label>
        <input
          required
          className="input w-full text-base"
          type="password"
          value={form.password}
          placeholder="Contraseña"
          onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
        />
        <button className="btn btn-neutral mt-4" type="submit" disabled={loading}>
          {loading ? 'Entrando...' : 'Entrar'}
        </button>
        {error && <p className="text-error mt-2">{error}</p>}
      </form>
    </fieldset>
  );
}
