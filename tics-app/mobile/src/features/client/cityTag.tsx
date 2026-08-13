/* =========================================================
   Eticheta „rezultate pentru <oras>".

   Ecranele de descoperire filtreaza dupa orasul ales in antetul de pe Acasa,
   dar filtrul era invizibil in restul aplicatiei: o lista scurta parea „nu sunt
   evenimente", cand de fapt insemna „nu sunt in orasul asta". Componenta o
   spune, si o face si reversibila dintr-un tap.

   Nu se randeaza nimic cand e ales „Toată România": o eticheta care spune
   „peste tot" nu adauga nimic si ar fi doar zgomot pe fiecare ecran.
   ========================================================= */
import { Ic, sx } from '../../design/sx';
import { I } from '../../mock/prototype';
import { useClient } from '../../store/client';

export function CityTag({ onChange }: { onChange?: () => void }) {
  const city = useClient((s) => s.city);
  const setCity = useClient((s) => s.setCity);

  if (!city) return null;

  return (
    <span
      className="citytag"
      onClick={() => (onChange ? onChange() : setCity(''))}
      title={onChange ? 'Schimbă orașul' : 'Arată din toată România'}
    >
      <Ic svg={I.pin} />
      {city}
      {onChange ? null : <span className="x" style={sx('font-size:13px')}>×</span>}
    </span>
  );
}
