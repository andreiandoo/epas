/* =========================================================
   Fluxul de intrare, portat din prototipul de client:
     splash -> onboarding -> login (-> register / forgot)

   Diferenta fata de prototip, ceruta de §3: dupa autentificare NU intram
   direct in Acasa. `session.login()` decide: daca emailul are mai multe
   proprietati -> pasul "Alege contul"; daca are una -> intrare directa.
   Vizualul ecranelor ramane cel din prototip.

   Ecranele traiesc in `.app-client`, ca sa primeasca CSS-ul portat.
   ========================================================= */
import { useState } from 'react';
import { Forgot, Login, Onboarding, Register, Splash } from '../client/screens/Auth';
import { useClient } from '../../store/client';

type Step = 'splash' | 'onboarding' | 'login' | 'register' | 'forgot';

export function AuthFlow() {
  const [step, setStep] = useState<Step>('splash');
  const toast = useClient((s) => s.toast);

  return (
    <div className="app-client">
      <div className="screen">
        {step === 'splash' ? <Splash onDone={() => setStep('onboarding')} /> : null}
        {step === 'onboarding' ? <Onboarding onDone={() => setStep('login')} /> : null}
        {step === 'login' ? <Login onForgot={() => setStep('forgot')} onRegister={() => setStep('register')} /> : null}
        {step === 'register' ? <Register onBack={() => setStep('login')} /> : null}
        {step === 'forgot' ? <Forgot onBack={() => setStep('login')} /> : null}
      </div>
      <div id="toast" className={toast ? 'show' : ''}>
        {toast}
      </div>
    </div>
  );
}
