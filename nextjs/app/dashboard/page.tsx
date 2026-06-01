'use client';
import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { verifySession, logout } from '@/lib/auth';

export default function DashboardPage() {
  const router = useRouter();
  const [user, setUser] = useState('');
  const [marchaId, setMarchaId] = useState('');

  useEffect(() => {
    verifySession().then((s) => {
      if (!s) { router.replace('/login'); return; }
      setUser(s.user);
    });
  }, [router]);

  async function handleLogout() {
    await logout();
    router.push('/login');
  }

  function goToMarchaEdit() {
    if (!marchaId) return;
    router.push(`/dashboard/marcha/${marchaId}`);
  }

  return (
    <>
      <h1>WELCOME TO DASHBOARD {user}</h1>
      <div className="divider" />
      <div className="flex flex-wrap gap-3 items-end">
        <fieldset className="fieldset">
          <label className="label">ID de marcha a editar</label>
          <input
            className="input"
            type="number"
            min={1}
            value={marchaId}
            placeholder="Ej: 125"
            onChange={(e) => setMarchaId(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && goToMarchaEdit()}
          />
        </fieldset>
        <button className="btn btn-neutral" onClick={goToMarchaEdit}>Ir a edición</button>
        <button className="btn btn-neutral" onClick={() => router.push('/dashboard/marcha/add')}>Nueva marcha</button>
        <button className="btn btn-neutral" onClick={() => router.push('/dashboard/autor/add')}>Nuevo autor</button>
        <button className="btn" onClick={handleLogout}>Logout</button>
      </div>
    </>
  );
}
