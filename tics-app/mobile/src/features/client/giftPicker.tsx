/* =========================================================
   „Biletul ăsta e pentru un prieten"

   Secțiunea din coș prin care alegi cui îi dai biletele. La plată, serverul
   creează un transfer în așteptare către prietenul respectiv — el îl acceptă
   și biletul devine al lui.

   DE CE TRANSFER, ȘI NU BILET EMIS DIRECT PE NUMELE LUI: dacă adresa e
   greșită, transferul expiră și biletul rămâne la tine. Un bilet emis direct
   pe o adresă greșită s-ar pierde.

   Prietenii vin din contul tics. Cine n-are cont tics legat pe telefon nu
   vede secțiunea deloc — nu-i cerem să se autentifice în mijlocul unei plăți.
   ========================================================= */
import { useEffect, useState } from 'react';
import { Ic, sx } from '../../design/sx';
import { I } from '../../mock/prototype';
import { fetchFriends, type FriendCard } from '../../api/friends';
import { getAppToken } from '../../api/orgApp';

export type GiftTarget = { accountId?: number; email?: string; name: string };

export function GiftPicker({
  max,
  value,
  onChange,
}: {
  /** Câte bilete sunt în coș. Nu poți dărui mai multe decât cumperi. */
  max: number;
  value: GiftTarget[];
  onChange: (next: GiftTarget[]) => void;
}) {
  const [friends, setFriends] = useState<FriendCard[] | null>(null);
  const [open, setOpen] = useState(false);
  const [email, setEmail] = useState('');
  const linked = !!getAppToken();

  useEffect(() => {
    if (!linked) return;

    let alive = true;
    void fetchFriends().then((r) => {
      if (alive && r.ok) setFriends(r.data.friends);
    });

    return () => {
      alive = false;
    };
  }, [linked]);

  if (!linked) return null;

  const toggle = (f: FriendCard) => {
    const has = value.some((g) => g.accountId === f.id);

    if (has) {
      onChange(value.filter((g) => g.accountId !== f.id));

      return;
    }

    if (value.length >= max) return;

    onChange([...value, { accountId: f.id, name: f.name }]);
  };

  const addEmail = () => {
    const e = email.trim().toLowerCase();

    if (!e || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(e)) return;
    if (value.length >= max) return;
    if (value.some((g) => g.email === e)) return;

    onChange([...value, { email: e, name: e }]);
    setEmail('');
  };

  return (
    <div className="pad" style={sx('margin-top:16px')}>
      <div className="card" style={sx('padding:15px')}>
        <div className="between">
          <div className="row" style={sx('gap:9px')}>
            <div
              style={sx('width:32px;height:32px;border-radius:11px;background:var(--indigo-soft);color:var(--indigo-2);display:grid;place-items:center')}
            >
              🎁
            </div>
            <div>
              <div style={sx('font-weight:600;font-size:13.5px')}>Un bilet e pentru un prieten?</div>
              <div className="muted" style={sx('font-size:11px;margin-top:1px')}>
                {value.length ? `${value.length} din ${max} bilete dăruite` : 'Îi trimitem biletul după plată'}
              </div>
            </div>
          </div>
          <button
            className="chip"
            onClick={() => setOpen((v) => !v)}
            style={sx('padding:6px 12px;font-size:11.5px;flex:none')}
          >
            {open ? 'Închide' : 'Alege'}
          </button>
        </div>

        {/* Cine primește, deja ales — vizibil și cu panoul închis: altfel ai
            putea plăti fără să-ți amintești că un bilet nu e al tău. */}
        {value.length ? (
          <div className="row" style={sx('gap:7px;flex-wrap:wrap;margin-top:11px')}>
            {value.map((g) => (
              <span key={g.accountId ?? g.email} className="chip ind on" style={sx('padding:6px 11px;font-size:11.5px')}>
                {g.name}
                <button
                  onClick={() => onChange(value.filter((x) => x !== g))}
                  aria-label={`Scoate ${g.name}`}
                  style={sx('background:none;border:0;color:inherit;cursor:pointer;padding:0 0 0 6px;font:inherit')}
                >
                  ×
                </button>
              </span>
            ))}
          </div>
        ) : null}

        {open ? (
          <>
            <div style={sx('margin-top:13px;display:flex;flex-direction:column;gap:8px')}>
              {(friends ?? []).map((f) => {
                const on = value.some((g) => g.accountId === f.id);

                return (
                  <div
                    key={f.id}
                    className="listitem"
                    onClick={() => toggle(f)}
                    style={sx('cursor:pointer;padding:9px 11px')}
                  >
                    <div
                      style={sx('width:34px;height:34px;border-radius:12px;background:linear-gradient(135deg,var(--indigo-2),var(--indigo-4));display:grid;place-items:center;color:#fff;font-weight:600;font-size:12px')}
                    >
                      {f.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()}
                    </div>
                    <div style={sx('flex:1;min-width:0;font-size:13px;font-weight:500')}>{f.name}</div>
                    <span className={on ? 'cbx on' : 'cbx'} aria-hidden="true" />
                  </div>
                );
              })}

              {friends && !friends.length ? (
                <div className="muted" style={sx('font-size:12px;line-height:1.5;padding:4px 0')}>
                  N-ai încă prieteni în tics. Poți scrie direct adresa lui de email mai jos.
                </div>
              ) : null}
            </div>

            <div className="row" style={sx('gap:8px;margin-top:11px')}>
              <div className="field" style={sx('flex:1')}>
                <Ic svg={I.mail} />
                <input
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="sau email@prieten.ro"
                  inputMode="email"
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') addEmail();
                  }}
                />
              </div>
              <button className="chip ind on" onClick={addEmail} style={sx('padding:10px 14px;flex:none')}>
                Adaugă
              </button>
            </div>

            <div className="muted" style={sx('font-size:11px;margin-top:10px;line-height:1.5')}>
              Prietenul primește biletul după ce plata intră și trebuie să-l accepte. Dacă nu-l acceptă în
              7 zile, biletul rămâne al tău.
            </div>
          </>
        ) : null}
      </div>
    </div>
  );
}
