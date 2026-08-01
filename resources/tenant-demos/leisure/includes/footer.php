<?php
/**
 * Footer unificat Nordvale — self-contained (CSS propriu, fără Tailwind/Alpine).
 */
?>
<style>
  .nvf{background:#061a15;color:#fff;padding:64px 16px 32px;font-family:'DM Sans',system-ui,sans-serif}
  @media(min-width:640px){.nvf{padding-left:24px;padding-right:24px}}
  @media(min-width:1024px){.nvf{padding-left:40px;padding-right:40px}}
  .nvf-inner{max-width:1460px;margin:0 auto}
  .nvf-grid{display:grid;gap:40px;padding-bottom:52px;border-bottom:1px solid rgba(255,255,255,.1)}
  @media(min-width:900px){.nvf-grid{grid-template-columns:1.25fr .75fr .75fr .75fr}}
  .nvf-name{font:600 34px/1 'Fraunces',Georgia,serif}
  .nvf-blurb{margin-top:16px;max-width:24rem;font-size:14px;line-height:1.7;color:rgba(255,255,255,.48)}
  .nvf-social{margin-top:22px;display:flex;gap:8px}
  .nvf-social a{width:40px;height:40px;display:grid;place-items:center;border-radius:999px;border:1px solid rgba(255,255,255,.12);color:#fff;font-size:12px;text-decoration:none}
  .nvf-col h4{margin:0 0 18px;font-size:10px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#dffc62}
  .nvf-col a{display:block;margin-bottom:12px;font-size:14px;color:rgba(255,255,255,.58);text-decoration:none;transition:color .2s ease}
  .nvf-col a:hover{color:#fff}
  .nvf-col p{margin:0 0 8px;font-size:14px;color:rgba(255,255,255,.58)}
  .nvf-col .nvf-cta{display:inline-block;margin-top:18px;border-radius:999px;background:#dffc62;color:#061a15;padding:12px 16px;font-weight:800;text-decoration:none}
  .nvf-bottom{display:flex;flex-direction:column;gap:16px;padding-top:24px;font-size:12px;color:rgba(255,255,255,.38)}
  @media(min-width:640px){.nvf-bottom{flex-direction:row;align-items:center;justify-content:space-between}}
  .nvf-bottom .nvf-links{display:flex;flex-wrap:wrap;gap:20px}
  .nvf-bottom a{color:rgba(255,255,255,.38);text-decoration:none}
  .nvf-bottom a:hover{color:#fff}
  .nvf-bottom .nvf-tix{color:#dffc62;font-weight:700;text-decoration:none}
</style>
<footer class="nvf">
  <div class="nvf-inner">
    <div class="nvf-grid">
      <div>
        <div class="nvf-name">Nordvale</div>
        <p class="nvf-blurb">Parc de aventură și rezervație forestieră. Concept demonstrativ pentru un tenant Tixello leisure.</p>
        <div class="nvf-social"><a href="#" aria-label="Instagram">IG</a><a href="#" aria-label="Facebook">FB</a><a href="#" aria-label="YouTube">YT</a></div>
      </div>
      <div class="nvf-col"><h4>Explorează</h4><a href="/experiente">Experiențe</a><a href="/calendar">Calendar</a><a href="/despre">Rezervația</a><a href="/abonamente">Abonamente</a></div>
      <div class="nvf-col"><h4>Vizită</h4><a href="/planifica">Planificator</a><a href="/despre">Acces și parcare</a><a href="/grupuri">Grupuri</a><a href="/contact">Contact</a></div>
      <div class="nvf-col"><h4>Astăzi</h4><p>09:00–20:00</p><p>Ultima intrare: 18:30</p><a class="nvf-cta" href="/bilete">Rezervă intervalul</a></div>
    </div>
    <div class="nvf-bottom">
      <p>© <?= date('Y') ?> Nordvale. Date demonstrative.</p>
      <div class="nvf-links"><a href="/termeni">Termeni</a><a href="/confidentialitate">Confidențialitate</a><a href="/faq">Ajutor</a><a href="/contact">Contact</a><span>Ticketing by <a class="nvf-tix" href="https://tixello.ro">tixello</a></span></div>
    </div>
  </div>
</footer>
