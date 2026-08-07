/* =========================================================
   Placeholder de tip LQIP, desenat din descriptorul trimis de server (D9).

   Serverul produce `g2x3:<12 culori hex>` — o grila 2x3 de culori medii extrase
   din poster (vezi GenerateBlurhashJob). Aici o randam ca gradient, ca ecranul
   sa nu fie o gaura neagra pana se incarca posterul pe retea slaba.

   Formatul e prefixat cu tipul, deci daca serverul incepe sa trimita un
   BlurHash adevarat, componenta poate randa ambele fara sa se strice.
   ========================================================= */

type Props = { hash: string | null | undefined };

function parseGrid(hash: string): string[] | null {
  if (!hash.startsWith('g2x3:')) return null;

  const body = hash.slice(5);
  if (body.length !== 36) return null;

  return Array.from({ length: 6 }, (_, i) => `#${body.slice(i * 6, i * 6 + 6)}`);
}

export function Blurhash({ hash }: Props) {
  const colours = hash ? parseGrid(hash) : null;

  if (!colours) {
    return <div className="media" style={{ background: '#0b0912' }} aria-hidden="true" />;
  }

  const [tl, tr, ml, mr, bl, br] = colours;

  return (
    <div
      className="media shblur"
      aria-hidden="true"
      style={{
        background: `
          radial-gradient(60% 40% at 20% 12%, ${tl} 0%, transparent 70%),
          radial-gradient(60% 40% at 80% 12%, ${tr} 0%, transparent 70%),
          radial-gradient(60% 40% at 20% 50%, ${ml} 0%, transparent 70%),
          radial-gradient(60% 40% at 80% 50%, ${mr} 0%, transparent 70%),
          radial-gradient(60% 40% at 20% 88%, ${bl} 0%, transparent 70%),
          radial-gradient(60% 40% at 80% 88%, ${br} 0%, transparent 70%),
          ${ml}
        `,
      }}
    />
  );
}
