/* =========================================================
   Selector in foaie de jos — orase, tipuri, genuri.

   Prototipul n-are asa ceva (filtrele lui erau chip-uri decorative), dar din
   momentul in care datele sunt reale trebuie sa poti alege ceva. Foaia
   foloseste aceleasi clase ca restul aplicatiei (.card, .selrow, .chip), ca
   sa nu introduca un limbaj vizual nou.
   ========================================================= */
import { useEffect } from 'react';
import { cn, sx } from '../../design/sx';

export type Option = [value: string, label: string];

export function PickerSheet({
  title,
  options,
  value,
  onPick,
  onClose,
}: {
  title: string;
  options: Option[];
  value: string;
  onPick: (v: string) => void;
  onClose: () => void;
}) {
  /* pe telefon, "inapoi" trebuie sa inchida foaia, nu sa iasa din ecran */
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div
      onClick={onClose}
      style={sx(
        'position:fixed;inset:0;z-index:60;background:rgba(4,3,9,.6);backdrop-filter:blur(3px);display:flex;align-items:flex-end',
      )}
    >
      <div
        onClick={(e) => e.stopPropagation()}
        style={{
          width: '100%',
          maxHeight: '72%',
          display: 'flex',
          flexDirection: 'column',
          background: 'var(--bg)',
          borderRadius: '24px 24px 0 0',
          border: '1px solid var(--line)',
          borderBottom: 0,
          paddingBottom: 'calc(14px + var(--safe-bottom, 0px))',
        }}
      >
        <div style={sx('width:40px;height:4px;border-radius:9px;background:var(--line-2);margin:10px auto 4px;flex:none')} />
        <div className="between pad" style={sx('flex:none')}>
          <div className="h2" style={sx('font-size:15px')}>
            {title}
          </div>
          <button className="chip" onClick={onClose} style={sx('padding:5px 11px;font-size:11px')}>
            Închide
          </button>
        </div>

        <div style={sx('overflow-y:auto;padding:4px 20px 0')}>
          {options.map(([v, label]) => (
            <div
              key={v || '__all'}
              className="selrow"
              onClick={() => {
                onPick(v);
                onClose();
              }}
              style={{ cursor: 'pointer', borderTop: '1px solid var(--line)' }}
            >
              <div style={sx('flex:1;min-width:0;font-size:13.5px;font-weight:500')}>{label}</div>
              {v === value ? <span style={sx('color:var(--indigo-2);font-weight:700')}>✓</span> : null}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

/** Chip de filtru care deschide o foaie; arata valoarea aleasa. */
export function PickerChip({
  icon,
  label,
  active,
  onClick,
}: {
  icon?: string;
  label: string;
  active: boolean;
  onClick: () => void;
}) {
  return (
    <div className={cn('flt', active && 'on')} onClick={onClick}>
      {icon ? `${icon} ` : ''}
      {label}
    </div>
  );
}
