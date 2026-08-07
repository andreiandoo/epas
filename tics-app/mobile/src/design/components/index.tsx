/* =========================================================
   TIXELLO — inventar de componente (§4.3)
   Card · Row · StatTile · Chip/Pill · IconButton · Avatar · Progress ·
   Segmented · Button · Toggle · TabBar · AppBar · Sheet · FullModal ·
   CenterModal · Toast · Input · TypeChip · InfoRow · QR placeholder
   ========================================================= */
import type { CSSProperties, ReactNode } from 'react';
import { Icon, type IconName } from '../icons/Icon';

/* ---------- utilitare ---------- */
export const money = (n: number) => n.toLocaleString('ro-RO') + ' lei';
export const cls = (...xs: (string | false | null | undefined)[]) => xs.filter(Boolean).join(' ');

/* ---------- Card ---------- */
export function Card({
  children,
  pad,
  style,
  onClick,
  className,
}: {
  children: ReactNode;
  pad?: boolean;
  style?: CSSProperties;
  onClick?: () => void;
  className?: string;
}) {
  return (
    <div
      className={cls('card', pad && 'pad', className)}
      style={{ ...(onClick ? { cursor: 'pointer' } : null), ...style }}
      onClick={onClick}
    >
      {children}
    </div>
  );
}

/* ---------- Row (list item) ---------- */
export function Row({
  icon,
  iconClass,
  title,
  meta,
  right,
  onClick,
  style,
}: {
  icon?: IconName;
  iconClass?: string;
  title: ReactNode;
  meta?: ReactNode;
  right?: ReactNode;
  onClick?: () => void;
  style?: CSSProperties;
}) {
  return (
    <div className="row" style={{ ...(onClick ? { cursor: 'pointer' } : null), ...style }} onClick={onClick}>
      {icon && (
        <span className={cls('chip-i', iconClass || 'chip-accent')}>
          <Icon name={icon} size={20} />
        </span>
      )}
      <div className="grow">
        <div className="name">{title}</div>
        {meta ? <div className="meta">{meta}</div> : null}
      </div>
      {right !== undefined ? right : onClick ? <Icon name="chev" size={18} className="chev" /> : null}
    </div>
  );
}

/* ---------- InfoRow (label ↔ valoare) ---------- */
export function InfoRow({
  label,
  value,
  last,
  onClick,
}: {
  label: ReactNode;
  value: ReactNode;
  last?: boolean;
  onClick?: () => void;
}) {
  return (
    <div
      onClick={onClick}
      style={{
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        gap: 12,
        padding: '13px 0',
        borderBottom: last ? 'none' : '1px solid var(--border)',
        cursor: onClick ? 'pointer' : undefined,
      }}
    >
      <span className="muted" style={{ fontSize: 14 }}>
        {label}
      </span>
      <b style={{ fontSize: 14, color: 'var(--text)', display: 'flex', alignItems: 'center', gap: 8 }}>{value}</b>
    </div>
  );
}

/* ---------- StatTile ---------- */
export function StatTile({
  icon,
  iconClass,
  value,
  label,
  unit,
  onClick,
}: {
  icon: IconName;
  iconClass?: string;
  value: ReactNode;
  label: string;
  unit?: string;
  onClick?: () => void;
}) {
  return (
    <div className="stat" onClick={onClick} role={onClick ? 'button' : undefined}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <span className={cls('chip-i', iconClass || 'chip-accent')} style={{ width: 32, height: 32, borderRadius: 9 }}>
          <Icon name={icon} size={17} />
        </span>
        {onClick ? <Icon name="chev" size={15} className="chev" /> : null}
      </div>
      <div className="value tnum">
        {value}
        {unit ? <span className="unit"> {unit}</span> : null}
      </div>
      <div className="label">{label}</div>
    </div>
  );
}

/* ---------- Pill (Live / Offline / amber) ---------- */
export function Pill({
  tone,
  children,
  onClick,
  style,
}: {
  tone: 'live' | 'off' | 'amber';
  children: ReactNode;
  onClick?: () => void;
  style?: CSSProperties;
}) {
  return (
    <span className={cls('pill', `pill-${tone}`)} onClick={onClick} style={style}>
      {tone !== 'amber' && <span className="dot" />}
      {children}
    </span>
  );
}

/* ---------- IconButton (+ badge) ---------- */
export function IconButton({
  icon,
  badge,
  onClick,
  style,
  children,
}: {
  icon?: IconName;
  badge?: number;
  onClick?: () => void;
  style?: CSSProperties;
  children?: ReactNode;
}) {
  return (
    <button className="iconbtn" onClick={onClick} style={style} type="button">
      {icon ? <Icon name={icon} size={20} /> : children}
      {badge ? <span className="badge">{badge}</span> : null}
    </button>
  );
}

/* ---------- Avatar (gradient rounded-square sau tinta de culoare) ---------- */
export function Avatar({
  initials,
  icon,
  color,
  size = 38,
  radius,
  style,
}: {
  initials?: string;
  icon?: IconName;
  /** cheie de paleta: red|blue|amber|purple|green — sau 'grad' pentru gradientul de brand */
  color?: 'red' | 'blue' | 'amber' | 'purple' | 'green' | 'grad';
  size?: number;
  radius?: number;
  style?: CSSProperties;
}) {
  const grad = color === 'grad' || !color;
  return (
    <span
      className="avatar"
      style={{
        width: size,
        height: size,
        borderRadius: radius ?? Math.round(size * 0.34),
        fontSize: Math.round(size * 0.34),
        background: grad ? 'var(--grad)' : `var(--av-${color}-bg)`,
        color: grad ? '#fff' : `var(--av-${color}-fg)`,
        boxShadow: grad ? 'var(--shadow-btn)' : undefined,
        ...style,
      }}
    >
      {icon ? <Icon name={icon} size={Math.round(size * 0.48)} /> : initials}
    </span>
  );
}

/* ---------- Progress bar ---------- */
export function Progress({ pct, tone }: { pct: number; tone?: 'green' | 'amber' | 'danger' }) {
  return (
    <div className={cls('bar', tone)}>
      <span style={{ width: `${Math.max(0, Math.min(100, pct))}%` }} />
    </div>
  );
}

/* ---------- Segmented bar (online vs. la usa) ---------- */
export function Segmented({
  leftLabel,
  rightLabel,
  leftPct,
  onLeft,
  onRight,
}: {
  leftLabel: ReactNode;
  rightLabel: ReactNode;
  leftPct: number;
  onLeft?: () => void;
  onRight?: () => void;
}) {
  return (
    <div className="seg-bar">
      <div className="on" style={{ width: `${leftPct}%` }} onClick={onLeft}>
        {leftLabel}
      </div>
      <div className="door" onClick={onRight}>
        {rightLabel}
      </div>
    </div>
  );
}

/* ---------- Button ---------- */
export function Button({
  variant = 'ghost',
  icon,
  children,
  onClick,
  style,
  disabled,
}: {
  variant?: 'primary' | 'ghost' | 'green' | 'danger-text';
  icon?: IconName;
  children: ReactNode;
  onClick?: () => void;
  style?: CSSProperties;
  disabled?: boolean;
}) {
  if (variant === 'danger-text') {
    return (
      <button className="btn-danger-text" onClick={onClick} style={style} type="button" disabled={disabled}>
        {children}
      </button>
    );
  }
  return (
    <button
      className={cls('btn', `btn-${variant}`)}
      onClick={onClick}
      style={{ ...(disabled ? { opacity: 0.5 } : null), ...style }}
      type="button"
      disabled={disabled}
    >
      {icon ? <Icon name={icon} size={20} /> : null}
      {children}
    </button>
  );
}

/* ---------- Toggle ---------- */
export function Toggle({ on, onChange }: { on: boolean; onChange?: () => void }) {
  return <button className={cls('tgl', on && 'on')} onClick={onChange} type="button" aria-pressed={on} />;
}

/* ---------- TypeChip (selectabil) ---------- */
export function TypeChip({ on, children, onClick }: { on?: boolean; children: ReactNode; onClick?: () => void }) {
  return (
    <button className={cls('typechip', on && 'on')} onClick={onClick} type="button">
      {children}
    </button>
  );
}

/* ---------- Input ---------- */
export function Input({
  label,
  value,
  placeholder,
  onChange,
  type = 'text',
  right,
}: {
  label?: string;
  value?: string;
  placeholder?: string;
  onChange?: (v: string) => void;
  type?: string;
  right?: ReactNode;
}) {
  return (
    <div>
      {label ? <label className="fieldlabel">{label}</label> : null}
      <div style={{ position: 'relative' }}>
        <input
          className="inputbox"
          value={value}
          placeholder={placeholder}
          type={type}
          onChange={(e) => onChange?.(e.target.value)}
        />
        {right ? (
          <span style={{ position: 'absolute', right: 14, top: '50%', transform: 'translateY(-50%)' }}>{right}</span>
        ) : null}
      </div>
    </div>
  );
}

/* ---------- Bottom sheet ---------- */
export function Sheet({
  title,
  onClose,
  children,
  extra,
}: {
  title: ReactNode;
  onClose: () => void;
  children: ReactNode;
  extra?: ReactNode;
}) {
  return (
    <div className="overlay" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className="sheet">
        <div className="sheet-handle" />
        <div className="sheet-head">
          <b>{title}</b>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            {extra}
            <IconButton icon="x" onClick={onClose} style={{ width: 32, height: 32 }} />
          </div>
        </div>
        <div className="sheet-body">{children}</div>
      </div>
    </div>
  );
}

/* ---------- Full-screen modal ---------- */
export function FullModal({
  title,
  onClose,
  onBack,
  children,
}: {
  title: ReactNode;
  onClose: () => void;
  onBack?: () => void;
  children: ReactNode;
}) {
  return (
    <div className="fullmodal">
      <div className="modal-topbar">
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          {onBack ? <IconButton icon="back" onClick={onBack} style={{ width: 32, height: 32 }} /> : null}
          <b>{title}</b>
        </div>
        <IconButton icon="x" onClick={onClose} style={{ width: 32, height: 32 }} />
      </div>
      <div className="screen pad">{children}</div>
    </div>
  );
}

/* ---------- Center modal ---------- */
export function CenterModal({ onClose, children }: { onClose: () => void; children: ReactNode }) {
  return (
    <div className="overlay center" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className="card pad" style={{ width: '100%', maxWidth: 330, boxShadow: 'var(--shadow)' }}>
        {children}
      </div>
    </div>
  );
}

/* ---------- Toast ---------- */
export function Toast({ message }: { message: string }) {
  return (
    <div className="toast">
      <Icon name="checkc" size={18} />
      {message}
    </div>
  );
}

/* ---------- QR placeholder (pana la generatorul real) ---------- */
export function QrPlaceholder({ size = 150 }: { size?: number }) {
  return (
    <div style={{ width: size, height: size, background: '#fff', borderRadius: 14, padding: 12, margin: '0 auto' }}>
      <div
        style={{
          width: '100%',
          height: '100%',
          backgroundImage:
            'linear-gradient(90deg,#000 50%,transparent 0),linear-gradient(#000 50%,transparent 0)',
          backgroundSize: '14px 14px',
          opacity: 0.9,
        }}
      />
    </div>
  );
}

/* ---------- Sectiune ---------- */
export function SectionHead({ title, action }: { title: string; action?: { label: string; onClick: () => void } }) {
  return (
    <div className="section-head">
      <h3>{title}</h3>
      {action ? (
        <button className="link" onClick={action.onClick} type="button">
          {action.label}
        </button>
      ) : null}
    </div>
  );
}

export { Icon };
export type { IconName };
