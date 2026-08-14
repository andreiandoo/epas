/* =========================================================
   Selector in foaie de jos — orase, tipuri, genuri.

   Prototipul n-are asa ceva (filtrele lui erau chip-uri decorative), dar din
   momentul in care datele sunt reale trebuie sa poti alege ceva. Foaia
   foloseste aceleasi clase ca restul aplicatiei (.card, .selrow, .chip), ca
   sa nu introduca un limbaj vizual nou.
   ========================================================= */
import { useEffect, useState } from 'react';
import { cn, sx } from '../../design/sx';

export type Option = [value: string, label: string];

export function PickerSheet({
  title,
  options,
  value,
  onPick,
  onClose,
  searchable,
  searchPlaceholder,
  multiple,
}: {
  title: string;
  options: Option[];
  /** Un sir la alegere unica; un tablou la alegere multipla. */
  value: string | string[];
  onPick: (v: string) => void;
  onClose: () => void;
  /** Lista de orase are peste 100 de intrari; derulata, e nefolosibila. */
  searchable?: boolean;
  searchPlaceholder?: string;
  /**
   * Alegere multipla: foaia NU se inchide dupa fiecare bifa, iar semnul devine
   * casuta. O foaie care se inchide dupa primul oras face imposibila alegerea a
   * doua — trebuia redeschisa pentru fiecare.
   */
  multiple?: boolean;
}) {
  const [q, setQ] = useState('');

  /* Cautare fara diacritice si fara majuscule: nimeni nu scrie „Timișoara" cu
     s-cedila pe tastatura telefonului, iar o cautare care nu gaseste orasul
     scris firesc e mai rea decat lipsa ei. */
  const fold = (t: string) =>
    t
      .toLocaleLowerCase('ro-RO')
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      // ș/ț cu virgula dedesubt nu se descompun; le traducem explicit
      .replace(/[șş]/g, 's')
      .replace(/[țţ]/g, 't');

  const needle = fold(q.trim());
  const shown = needle ? options.filter(([, label]) => fold(label).includes(needle)) : options;
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
          <button className="chip ind on" onClick={onClose} style={sx('padding:5px 13px;font-size:11px')}>
            {multiple ? 'Gata' : 'Închide'}
          </button>
        </div>

        {searchable ? (
          <div className="pad" style={sx('flex:none;padding-bottom:6px')}>
            <div className="field">
              <input
                value={q}
                autoFocus
                placeholder={searchPlaceholder ?? 'Caută…'}
                onChange={(e) => setQ(e.target.value)}
              />
            </div>
          </div>
        ) : null}

        <div style={sx('overflow-y:auto;padding:4px 20px 0')}>
          {shown.map(([v, label]) => {
            const on = Array.isArray(value) ? value.includes(v) : v === value;
            /* Randul „toate" (valoare goala) inchide foaia si la alegere
               multipla: e o comanda, nu o bifa. */
            const closes = !multiple || v === '';

            return (
              <div
                key={v || '__all'}
                className="selrow"
                onClick={() => {
                  onPick(v);
                  if (closes) onClose();
                }}
                style={{ cursor: 'pointer', borderTop: '1px solid var(--line)' }}
              >
                <div style={sx('flex:1;min-width:0;font-size:13.5px;font-weight:500')}>{label}</div>
                {multiple && v !== '' ? (
                  <span className={on ? 'cbx on' : 'cbx'} aria-hidden="true" />
                ) : on ? (
                  <span style={sx('color:var(--indigo-2);font-weight:700')}>✓</span>
                ) : null}
              </div>
            );
          })}
          {!shown.length ? (
            <div className="muted" style={sx('font-size:12.5px;text-align:center;padding:20px 0')}>
              Niciun rezultat pentru „{q}".
            </div>
          ) : null}
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
