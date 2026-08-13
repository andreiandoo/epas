/* =========================================================
   Personalul de la poartă — date reale, cu cădere pe prototip.

   Serverul deleaga catre acelasi controller ca aplicatia partenerului, deci
   randurile de aici sunt ACELEASI cu cele de acolo. Cine e adaugat din
   Tics apare imediat si in aplicatia partenerului, si invers.
   ========================================================= */
import { useCallback, useEffect, useState } from 'react';
import { fetchStaff, inviteStaff, removeStaff, type StaffMemberApi } from '../../api/orgApp';
import { STAFF } from '../../mock/org';

export type StaffRow = {
  id: number;
  name: string;
  initials: string;
  email: string;
  role: 'admin' | 'manager' | 'staff';
  roleLabel: string;
  gate: string | null;
  status: string;
  /** false = rand din datasetul prototipului, nu de la server */
  live: boolean;
};

const ROLE_LABEL: Record<string, string> = { admin: 'Administrator', manager: 'Manager', staff: 'Staff' };

const initialsOf = (name: string) =>
  name
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('') || '??';

const fromApi = (m: StaffMemberApi): StaffRow => ({
  id: m.id,
  name: m.name,
  initials: initialsOf(m.name),
  email: m.email,
  role: m.role,
  roleLabel: ROLE_LABEL[m.role] ?? m.role,
  gate: m.gate_id ? `Poarta ${m.gate_id}` : null,
  status: m.status,
  live: true,
});

const protoRows = (): StaffRow[] =>
  STAFF.map((s) => ({
    id: s.id,
    name: s.nm,
    initials: s.ini,
    email: s.email,
    role: s.role,
    roleLabel: s.roleL,
    gate: s.gate,
    status: s.status,
    live: false,
  }));

export function useStaff() {
  const [rows, setRows] = useState<StaffRow[]>(protoRows);
  const [live, setLive] = useState(false);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const list = await fetchStaff();
      if (list) {
        setRows(list.map(fromApi));
        setLive(true);
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const add = useCallback(
    async (p: { name?: string; email: string; password: string; role: StaffRow['role'] }) => {
      const r = await inviteStaff({ ...p, send_welcome_email: true });
      if (r.ok) await refresh();
      return r;
    },
    [refresh],
  );

  const remove = useCallback(
    async (id: number) => {
      const ok = await removeStaff(id);
      if (ok) await refresh();
      return ok;
    },
    [refresh],
  );

  return { rows, live, loading, refresh, add, remove };
}
