/* =========================================================
   SCANARE / CHECK-IN (§6.2) — camera QR + cele 4 stari obligatorii:
   ACCES APROBAT · RE-INTRARE BLOCATA · BILET INVALID · BILET BLOCAT.
   Flash colorat la fiecare scanare; blocare cand tura e pe pauza.
   In APK-ul de test scanarea e simulata (ciclul din prototip);
   camera reala (@capacitor-mlkit/barcode-scanning) intra in Faza 2.
   ========================================================= */
import { useEffect } from 'react';
import { Button, Card, Icon } from '../../design/components';
import { useSession } from '../../store/session';
import { SCAN_RESULTS } from '../../mock/org';
import { useCtx } from './OrgChrome';

function LiveStat({ v, l, color }: { v: string; l: string; color?: string }) {
  return (
    <Card style={{ padding: 12, textAlign: 'center' }}>
      <div className="tnum" style={{ fontSize: 20, fontWeight: 800, color: color || 'var(--text)' }}>
        {v}
      </div>
      <div style={{ fontSize: 11, color: 'var(--text-2)' }}>{l}</div>
    </Card>
  );
}

export function CheckIn() {
  const { scan, shiftPaused, toggleShiftPause, doScan, clearScan, openModal, set, flash, clearFlash } = useSession();
  const c = useCtx();

  /* flash colorat pe telefon la fiecare scanare */
  useEffect(() => {
    if (!flash) return;
    const t = setTimeout(clearFlash, 500);
    return () => clearTimeout(t);
  }, [flash, clearFlash]);

  if (shiftPaused) {
    return (
      <div
        className="screen"
        style={{
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          padding: 32,
          textAlign: 'center',
          gap: 14,
        }}
      >
        <span className="chip-i chip-amber" style={{ width: 64, height: 64, borderRadius: 20 }}>
          <Icon name="pause" size={30} />
        </span>
        <div className="h1">Tură Întreruptă</div>
        <div className="sub">Scanarea e oprită cât timp tura e pe pauză. Reia din bara de sus.</div>
        <Button variant="primary" icon="play" style={{ maxWidth: 220 }} onClick={toggleShiftPause}>
          Continuă tura
        </Button>
      </div>
    );
  }

  const r = scan === 'idle' ? null : SCAN_RESULTS[scan];
  const frameCol =
    scan === 'valid'
      ? 'var(--green)'
      : scan === 'duplicate'
        ? 'var(--amber)'
        : scan === 'invalid' || scan === 'banned'
          ? 'var(--danger)'
          : 'var(--accent)';

  const corner = (pos: 'tl' | 'tr' | 'bl' | 'br') => {
    const base = { position: 'absolute' as const, width: 38, height: 38 };
    if (pos === 'tl') return { ...base, top: 22, left: 22, borderTop: `4px solid ${frameCol}`, borderLeft: `4px solid ${frameCol}`, borderRadius: '8px 0 0 0' };
    if (pos === 'tr') return { ...base, top: 22, right: 22, borderTop: `4px solid ${frameCol}`, borderRight: `4px solid ${frameCol}`, borderRadius: '0 8px 0 0' };
    if (pos === 'bl') return { ...base, bottom: 22, left: 22, borderBottom: `4px solid ${frameCol}`, borderLeft: `4px solid ${frameCol}`, borderRadius: '0 0 0 8px' };
    return { ...base, bottom: 22, right: 22, borderBottom: `4px solid ${frameCol}`, borderRight: `4px solid ${frameCol}`, borderRadius: '0 0 8px 0' };
  };

  return (
    <>
      {flash ? (
        <div
          className="flash"
          style={{
            background:
              flash === 'valid' ? 'var(--green)' : flash === 'duplicate' ? 'var(--amber)' : 'var(--danger)',
          }}
        />
      ) : null}

      <div className="screen pad stack">
        {/* fereastra camerei */}
        <div
          onClick={doScan}
          style={{
            height: 260,
            borderRadius: 22,
            background: '#0c0a16',
            position: 'relative',
            overflow: 'hidden',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            boxShadow: '0 16px 34px -14px rgba(124,58,237,.5)',
            cursor: 'pointer',
          }}
        >
          <div style={corner('tl')} />
          <div style={corner('tr')} />
          <div style={corner('bl')} />
          <div style={corner('br')} />
          <div style={{ width: 110, height: 110, background: '#fff', borderRadius: 8, padding: 9 }}>
            <div
              style={{
                width: '100%',
                height: '100%',
                backgroundImage: 'linear-gradient(90deg,#000 50%,transparent 0),linear-gradient(#000 50%,transparent 0)',
                backgroundSize: '13px 13px',
                opacity: 0.85,
              }}
            />
          </div>
          <div
            style={{
              position: 'absolute',
              bottom: 12,
              left: 0,
              right: 0,
              textAlign: 'center',
              color: 'rgba(255,255,255,.7)',
              fontSize: 12,
              fontWeight: 600,
            }}
          >
            Apasă pentru a simula o scanare
          </div>
        </div>

        {/* card de rezultat */}
        {r ? (
          <div className="card pad" style={{ background: `var(--${r.cls}-tint)`, borderColor: `var(--${r.cls}-border)` }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
              <div
                style={{
                  width: 44,
                  height: 44,
                  borderRadius: '50%',
                  background: `var(--${r.cls})`,
                  color: '#fff',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }}
              >
                <Icon name={r.icon} size={24} />
              </div>
              <div>
                <div style={{ fontSize: 16, fontWeight: 800, color: `var(--${r.cls})`, letterSpacing: 0.3 }}>{r.title}</div>
                <div style={{ fontSize: 12, color: 'var(--text-2)' }}>{r.sub}</div>
              </div>
            </div>

            {scan === 'invalid' ? (
              <div style={{ fontSize: 13, color: 'var(--text-2)' }}>{r.msg}</div>
            ) : (
              <div style={{ background: 'var(--surface)', borderRadius: 12, padding: '12px 14px' }}>
                <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--text)' }}>{r.nm}</div>
                <div style={{ fontSize: 13, color: 'var(--text-2)', marginTop: 2 }}>{r.tt}</div>
                {r.seat ? <div style={{ fontSize: 12, color: 'var(--text-3)', marginTop: 4 }}>{r.seat}</div> : null}
                {r.at ? (
                  <div style={{ fontSize: 12, color: 'var(--amber)', marginTop: 4, display: 'flex', alignItems: 'center', gap: 5 }}>
                    <Icon name="clock" size={13} />
                    {r.at}
                  </div>
                ) : null}
                {scan === 'banned' && r.msg ? (
                  <div style={{ fontSize: 12, color: 'var(--danger)', marginTop: 6 }}>{r.msg}</div>
                ) : null}
              </div>
            )}

            {scan === 'valid' ? (
              <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                <Button variant="ghost" icon="camera" style={{ flex: 1, padding: 11, fontSize: 13 }} onClick={() => openModal('printbadge')}>
                  Badge
                </Button>
                <Button variant="ghost" icon="list" style={{ flex: 1, padding: 11, fontSize: 13 }} onClick={() => openModal('ticketaction')}>
                  Acțiuni
                </Button>
              </div>
            ) : null}

            {scan === 'duplicate' ? (
              <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                <Button variant="ghost" style={{ flex: 1, padding: 11, fontSize: 13 }} onClick={() => openModal('scandetails')}>
                  Vezi scanarea
                </Button>
                <Button
                  variant="ghost"
                  style={{ flex: 1, padding: 11, fontSize: 13, color: 'var(--amber)', borderColor: 'var(--amber-border)' }}
                  onClick={() => openModal('ticketaction')}
                >
                  Permite re-intrare
                </Button>
              </div>
            ) : null}

            {scan === 'banned' ? (
              <Button
                variant="ghost"
                icon="alert"
                style={{ marginTop: 12, color: 'var(--danger)', borderColor: 'var(--danger-border)' }}
                onClick={() => openModal('banlist')}
              >
                Vezi lista neagră
              </Button>
            ) : null}

            {scan === 'valid' && !set.autoconf ? (
              <Button variant="primary" icon="scan" style={{ marginTop: 8 }} onClick={clearScan}>
                Scanează Următorul
              </Button>
            ) : null}
          </div>
        ) : null}

        <div className="grid3">
          <LiveStat v="42" l="scanări/min" />
          <LiveStat v="3s" l="așteptare" />
          <LiveStat v={c.checkedin.toLocaleString('ro-RO')} l="intrați" color="var(--green)" />
        </div>

        <Button variant="primary" icon="scan" onClick={doScan}>
          {scan === 'idle' ? 'Începe Scanarea' : 'Scanează Următorul'}
        </Button>
        <Button variant="ghost" icon="edit" onClick={() => openModal('manualentry')}>
          Cod Manual
        </Button>
      </div>
    </>
  );
}
