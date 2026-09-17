/* bilete.online v2: customer points and referrals (/cont/puncte). Reads the programme rules (/customer/rewards/config),
   the balance (/customer/rewards), the referral code with its stats (/customer/referrals) and the points history
   (/customer/rewards/history, 50 a page, "Încarcă mai multe"). The level ladder compares the points earned so far with
   each level's threshold_points, as the old page did. Points about to expire are asked for (from the dashboard summary)
   only when the programme makes points expire. The referral link is core's own: https://<site>/?ref=<code>.
   Text from the API is always written as text. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('pt-content') || !window.BO_ACCOUNT) return;
  var account = window.BO_ACCOUNT;

  var MONTHS = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'noi', 'dec'];
  var TYPE_LABEL = { earned: 'câștigate', spent: 'folosite', expired: 'expirate', affiliate: 'afiliere' };
  var TYPE_TONE = { earned: 'is-ok', spent: 'is-bad', expired: 'is-muted', affiliate: 'is-wait' };
  var TYPE_TITLE = { earned: 'Puncte câștigate', spent: 'Puncte folosite', expired: 'Puncte expirate', affiliate: 'Afiliere' };
  var ACTIONS = {
    order: 'Comandă', purchase: 'Comandă', refund: 'Retur', referral: 'Afiliere', referral_reward: 'Afiliere', signup: 'Bonus cont nou',
    birthday: 'Bonus zi de naștere', badge_bonus: 'Bonus insignă', redemption: 'Folosite la o comandă', reward_redemption: 'Recompensă',
    expiration: 'Expirare', manual_adjustment: 'Ajustare'
  };
  var SHARE_TEXT = 'Hei! Am descoperit bilete.online — bilete pentru escape rooms, muzee, ateliere și multe altele. Folosește linkul meu: ';
  var num = new Intl.NumberFormat('ro-RO');
  var whole = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 });
  var pct = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 1 });
  var config = null, earnRate = '', ladder = [], history = [], page = 0, lastPage = 1, historyBusy = false;
  var points = { balance: 0, earned: 0, spent: 0 }, referral = { code: '', link: '' }, statusTimer = 0;

  // ---------- helpers ----------
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function strong(text) { return el('strong', null, text); }
  function fill(node, parts) {
    node.textContent = '';
    parts.forEach(function (p) { node.appendChild(typeof p === 'string' ? document.createTextNode(p) : p); });
  }
  function txt(v) {
    if (v && typeof v === 'object') v = v.ro || v.en || v.name || Object.keys(v).map(function (k) { return v[k]; })[0];
    return v == null ? '' : String(v);
  }
  function amount(v) { var n = Number(v); return isFinite(n) ? n : 0; }
  function count(v) { return Math.max(0, Math.floor(amount(v))); }
  function plural(n, one, many) {
    n = count(n);
    if (n === 1) return '1 ' + one;
    var r = n % 100;
    return num.format(n) + (n && (r === 0 || r >= 20) ? ' de ' : ' ') + many;
  }
  function show(id, on) { $(id).hidden = !on; }
  function obj(x) { return x && typeof x === 'object'; }
  function parseDate(v) { var d = v ? new Date(v) : null; return d && !isNaN(d.getTime()) ? d : null; }
  function shortDate(d) { return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear(); }
  function perLei() { return config && amount(config.points_per_lei) > 0 ? amount(config.points_per_lei) : 100; }
  function toLei(p) { return whole.format(Math.floor(count(p) / perLei())) + ' lei'; }
  function guard() { show('pt-content', false); show('pt-guard', true); account.toLogin(); } // the message shows only while the login page loads
  function linkSay(message, tone) {
    var line = $('pt-link-status');
    clearTimeout(statusTimer);
    line.textContent = message;
    line.classList.toggle('is-error', tone === 'error');
    if (tone !== 'error') statusTimer = setTimeout(function () { line.textContent = ''; }, 6000);
  }

  // ---------- balance, level, rules ----------
  function renderPoints() {
    $('pt-balance').textContent = num.format(points.balance);
    $('pt-balance-lei').textContent = toLei(points.balance);
    $('pt-s-balance').textContent = num.format(points.balance);
    $('pt-s-balance-lei').textContent = toLei(points.balance);
    $('pt-s-earned').textContent = num.format(points.earned);
    $('pt-s-spent').textContent = num.format(points.spent);
    $('pt-s-spent-lei').textContent = toLei(points.spent);
    account.setBadges({ points: points.balance });
  }
  function buildLadder(tiers) {
    var last = 0;
    ladder = (Array.isArray(tiers) ? tiers : []).filter(obj).map(function (t, i) {
      var threshold = t.threshold_points != null ? amount(t.threshold_points) : (t.threshold != null ? amount(t.threshold) : (i === 0 ? 0 : null));
      var perks = (Array.isArray(t.benefits) ? t.benefits : (Array.isArray(t.perks) ? t.perks : [])).map(function (p) {
        return typeof p === 'string' ? { label: p, active: true } : { label: txt(obj(p) ? (p.label || p.name) : p), active: !obj(p) || p.active !== false };
      }).filter(function (p) { return p.label; });
      return { name: txt(t.name) || 'Nivel ' + (i + 1), description: txt(t.description), threshold: threshold, perks: perks };
    });
    ladder.forEach(function (t) { if (t.threshold == null) t.threshold = last + 500; last = t.threshold; });
    ladder.sort(function (a, b) { return a.threshold - b.threshold; });
  }
  function setBar(id, value) {
    var bar = $(id);
    bar.firstElementChild.style.width = value + '%';
    bar.setAttribute('aria-valuenow', String(value));
  }
  function renderTier() {
    if (!ladder.length) {
      $('pt-tier').textContent = '—';
      $('pt-next-hero').textContent = 'Nivelurile programului nu sunt disponibile acum.';
      $('pt-next-tier').textContent = 'Nivelurile programului nu sunt disponibile acum.';
      return;
    }
    var score = points.earned || points.balance, idx = 0;
    ladder.forEach(function (t, i) { if (score >= t.threshold) idx = i; });
    var cur = ladder[idx], next = ladder[idx + 1] || null;
    var progress = next ? Math.min(100, Math.max(0, Math.round((score - cur.threshold) / Math.max(1, next.threshold - cur.threshold) * 100))) : 100;
    var toNext = next ? Math.max(0, next.threshold - score) : 0;
    $('pt-tier').textContent = cur.name;
    $('pt-tier-desc').textContent = cur.description;
    show('pt-tier-desc', !!cur.description);
    $('pt-tier-from').textContent = cur.name;
    $('pt-tier-to').textContent = next ? next.name : '—';
    setBar('pt-bar-hero', progress);
    setBar('pt-bar-tier', progress);
    if (next) {
      fill($('pt-next-hero'), ['Încă ', strong(num.format(toNext)), ' puncte până la nivelul ', strong(next.name), '.']);
      fill($('pt-next-tier'), ['Mai ai nevoie de ', strong(num.format(toNext)), ' puncte pentru următorul nivel.']);
    } else {
      $('pt-next-hero').textContent = 'Ești la cel mai înalt nivel — felicitări!';
      $('pt-next-tier').textContent = 'Cel mai înalt nivel atins.';
    }
    var list = $('pt-perks');
    list.textContent = '';
    cur.perks.forEach(function (p) {
      var li = el('li');
      li.appendChild(el('span', null, p.label));
      li.appendChild(el('span', 'acc-tag ' + (p.active ? 'is-ok' : 'is-wait'), p.active ? 'activ' : 'în curând'));
      list.appendChild(li);
    });
  }
  function renderRules() {
    var list = $('pt-rate'), items = [], rule = $('pt-rule-exp');
    list.textContent = '';
    if (!config) {
      items.push(['Regulile programului nu sunt disponibile acum.']);
    } else {
      items.push([strong(num.format(perLei())), ' puncte = 1 leu reducere.']);
      if (earnRate) items.push(['Câștigi ', strong(earnRate), ' la comenzile eligibile.']);
      if (count(config.min_redeem_points) > 0) items.push(['Poți folosi punctele de la minimum ', strong(plural(config.min_redeem_points, 'punct', 'puncte')), '.']);
      if (amount(config.max_redeem_percentage) > 0) items.push(['Reducerea din puncte acoperă cel mult ', strong(pct.format(amount(config.max_redeem_percentage)) + '%'), ' din comandă.']);
      if (count(config.max_redeem_points_per_order) > 0) items.push(['Maximum ', strong(plural(config.max_redeem_points_per_order, 'punct', 'puncte')), ' pe comandă (≈ ' + toLei(config.max_redeem_points_per_order) + ').']);
      var days = count(config.points_expire_days);
      items.push(days ? ['Punctele expiră după ', strong(plural(days, 'zi', 'zile')), '.'] : ['Punctele nu expiră.']);
      fill(rule, [strong('Expirare:'), days ? ' punctele expiră după ' + plural(days, 'zi', 'zile') + '.' : ' punctele nu expiră.']);
    }
    items.forEach(function (parts) { var li = el('li'); fill(li, parts); list.appendChild(li); });
  }
  function renderExpiring(soon, days, expires) {
    days = count(days) || 30;
    $('pt-s-expiring').textContent = num.format(soon);
    $('pt-s-expiring-p').textContent = !expires ? 'punctele nu expiră' : (soon > 0 ? 'în ' + plural(days, 'zi', 'zile') : 'fără expirare imediată');
    $('pt-exp').classList.toggle('is-hot', soon > 0);
    $('pt-exp-h').textContent = soon > 0 ? plural(soon, 'punct expiră curând', 'puncte expiră curând') : 'Niciun punct în expirare';
    $('pt-exp-p').textContent = soon > 0 ? 'Folosește-le în următoarele ' + plural(days, 'zi', 'zile') + ' la o comandă eligibilă.'
      : (expires ? 'Continuă să cumperi pentru a câștiga puncte noi.' : 'Punctele din programul bilete.online nu expiră. Continuă să cumperi pentru a câștiga puncte noi.');
  }
  function loadExpiring() {
    BileteOnlineAPI.get('/customer/dashboard-bundle').then(function (resp) {
      var s = resp && resp.data && resp.data.rewards_summary;
      if (obj(s)) renderExpiring(count(s.expiring_soon), s.expiring_days || config.expiring_warning_days, true);
    }, function () {});
  }

  // ---------- referrals ----------
  function setLink() {
    var has = !!referral.link;
    $('pt-link-display').textContent = has ? referral.link.replace(/^https?:\/\//i, '') : '—';
    $('pt-link').value = referral.link;
    $('pt-code').textContent = referral.code || '—';
    $('pt-copy').disabled = !has;
    $('pt-regen').disabled = false;
    var wa = $('pt-wa');
    wa.hidden = !has;
    wa.href = 'https://wa.me/?text=' + encodeURIComponent(SHARE_TEXT + referral.link);
    $('pt-share').hidden = !(has && typeof navigator.share === 'function');
  }
  function linkFor(code) { return code ? window.location.origin + '/?ref=' + encodeURIComponent(code) : ''; }
  function renderReferral(d) {
    d = obj(d) ? d : {};
    var stats = obj(d.stats) ? d.stats : d, rewards = obj(d.rewards) ? d.rewards : {};
    referral.code = txt(d.code || d.referral_code);
    referral.link = txt(d.link || d.referral_link);
    if (!/^https?:\/\//i.test(referral.link)) referral.link = linkFor(referral.code);
    setLink();
    $('pt-r-clicks').textContent = num.format(count(stats.clicks));
    $('pt-r-accounts').textContent = num.format(count(stats.registrations != null ? stats.registrations : stats.signups));
    $('pt-r-orders').textContent = num.format(count(stats.conversions != null ? stats.conversions : stats.qualified));
    var mine = count(rewards.referrer_reward), theirs = count(rewards.referred_reward), inPoints = !rewards.reward_type || rewards.reward_type === 'points';
    var unit = function (n) { return inPoints ? plural(n, 'punct', 'puncte') : whole.format(n) + ' lei'; };
    if (mine) {
      $('pt-aff-reward').textContent = 'Primești ' + unit(mine) + ' pentru fiecare prieten care cumpără prima activitate eligibilă' + (theirs ? ', iar prietenul primește ' + unit(theirs) + ' la înregistrare.' : '.');
      show('pt-aff-reward', true);
    }
  }
  $('pt-copy').addEventListener('click', function () {
    var btn = this;
    if (!referral.link) return;
    var manual = function () { var input = $('pt-link'); input.focus(); input.select(); linkSay('Linkul e selectat. Copiază-l manual.'); };
    if (!(navigator.clipboard && navigator.clipboard.writeText)) { manual(); return; }
    navigator.clipboard.writeText(referral.link).then(function () {
      linkSay('Link copiat în clipboard.');
      btn.textContent = 'Copiat';
      setTimeout(function () { btn.textContent = 'Copiază link'; }, 2000);
    }, manual);
  });
  $('pt-share').addEventListener('click', function () {
    if (typeof navigator.share !== 'function' || !referral.link) return;
    navigator.share({ title: 'bilete.online', text: SHARE_TEXT.replace(/: $/, '.'), url: referral.link }).catch(function () {});
  });
  function closeConfirm(focus) {
    show('pt-confirm', false);
    $('pt-regen').setAttribute('aria-expanded', 'false');
    if (focus) $('pt-regen').focus();
  }
  $('pt-regen').addEventListener('click', function () {
    var opening = $('pt-confirm').hidden;
    show('pt-confirm', opening);
    this.setAttribute('aria-expanded', String(opening));
    if (opening) $('pt-regen-yes').focus();
  });
  $('pt-regen-no').addEventListener('click', function () { closeConfirm(true); });
  $('pt-regen-yes').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Se generează…';
    BileteOnlineAPI.post('/customer/referrals/regenerate-code', {}).then(function (resp) {
      var d = resp && resp.data;
      if (!(resp && resp.success && obj(d) && (d.code || d.link))) throw { status: -1 };
      referral.code = txt(d.code) || referral.code;
      referral.link = /^https?:\/\//i.test(txt(d.link)) ? txt(d.link) : linkFor(referral.code);
      setLink();
      closeConfirm(false);
      $('pt-copy').focus();
      linkSay('Ai un cod nou. Linkul vechi nu mai funcționează.');
    }).catch(function (err) {
      linkSay(err && err.status === 401 ? 'Sesiunea a expirat. Intră din nou în cont.' : 'Nu am putut genera un cod nou. Încearcă din nou.', 'error');
    }).then(function () {
      btn.disabled = false;
      btn.textContent = 'Da, generează cod nou';
    });
  });

  // ---------- history ----------
  function txType(tx) {
    var raw = String(tx.type || '').toLowerCase(), action = String(tx.action_type || '').toLowerCase(), pts = amount(tx.points != null ? tx.points : tx.amount);
    if (/expir/.test(raw) || action === 'expiration') return 'expired';
    if (/referral/.test(action) || /affiliate|referral/.test(raw)) return 'affiliate';
    if (/spent|redeem/.test(raw) || /redemption/.test(action) || pts < 0) return 'spent';
    return 'earned';
  }
  function row(tx) {
    var type = txType(tx), minus = type === 'spent' || type === 'expired';
    var pts = Math.abs(Math.round(amount(tx.points != null ? tx.points : tx.amount)));
    var tr = el('tr'), d = parseDate(tx.created_at), desc = el('td'), tag = el('td', 'is-type');
    tr.appendChild(el('td', null, d ? shortDate(d) : '—'));
    desc.appendChild(el('b', null, txt(tx.description) || TYPE_TITLE[type]));
    var meta = [ACTIONS[String(tx.action_type || '').toLowerCase()] || '', tx.balance_after != null ? 'sold ' + num.format(count(tx.balance_after)) : ''].filter(Boolean).join(' · ');
    if (meta) desc.appendChild(el('small', null, meta));
    tr.appendChild(desc);
    tag.appendChild(el('span', 'acc-tag ' + TYPE_TONE[type], TYPE_LABEL[type]));
    tr.appendChild(tag);
    tr.appendChild(el('td', 'is-pts ' + (minus ? 'is-minus' : 'is-plus'), (minus ? '−' : '+') + num.format(pts)));
    return tr;
  }
  function renderHistory() {
    var type = $('pt-type').value, body = $('pt-h-rows');
    var list = history.filter(function (tx) { return type === 'all' || txType(tx) === type; });
    body.textContent = '';
    list.forEach(function (tx) { body.appendChild(row(tx)); });
    show('pt-h-skel', false);
    show('pt-h-error', false);
    show('pt-h-table', list.length > 0);
    show('pt-h-empty', !list.length);
    show('pt-h-more', page < lastPage);
  }
  function loadHistory() {
    if (historyBusy) return;
    historyBusy = true;
    var more = $('pt-h-more');
    if (page > 0) { more.disabled = true; more.textContent = 'Se încarcă…'; }
    else { show('pt-h-error', false); show('pt-h-skel', true); }
    BileteOnlineAPI.get('/customer/rewards/history', { per_page: 50, page: page + 1 }).then(function (resp) {
      var data = resp && resp.data;
      var list = Array.isArray(data) ? data : (obj(data) && (data.history || data.items || data.transactions)) || [];
      history = history.concat(list.filter(obj));
      page++;
      lastPage = resp && obj(resp.meta) ? Math.max(1, count(resp.meta.last_page)) : page;
      more.textContent = 'Încarcă mai multe';
      renderHistory();
    }, function (err) {
      if (err && err.status === 401) { guard(); return; }
      if (page === 0) { show('pt-h-skel', false); show('pt-h-error', true); }
      else more.textContent = 'Nu s-a putut încărca. Încearcă din nou';
    }).then(function () {
      historyBusy = false;
      more.disabled = false;
    });
  }
  $('pt-type').addEventListener('change', renderHistory);
  $('pt-h-more').addEventListener('click', loadHistory);
  $('pt-h-retry').addEventListener('click', loadHistory);

  // ---------- load ----------
  if (!account.isCustomer()) { guard(); return; }
  var cached = account.cachedUser();
  if (cached && cached.points != null) { points.balance = count(cached.points); renderPoints(); }

  var configRequest = BileteOnlineAPI.get('/customer/rewards/config').then(function (r) { return r && r.data; }, function () { return null; });
  var rewardsRequest = BileteOnlineAPI.get('/customer/rewards').then(function (r) { return r && r.data; }, function (err) {
    if (err && err.status === 401) throw err;
    return null;
  });
  Promise.all([configRequest, rewardsRequest]).then(function (res) {
    config = obj(res[0]) ? res[0] : null;
    var p = obj(res[1]) && obj(res[1].points) ? res[1].points : null;
    if (p) {
      points.balance = count(p.balance != null ? p.balance : p.current_balance);
      points.earned = count(p.lifetime_earned != null ? p.lifetime_earned : p.total_earned);
      points.spent = count(p.lifetime_spent != null ? p.lifetime_spent : (p.spent != null ? p.spent : p.total_spent));
      earnRate = txt(p.earn_rate);
    }
    buildLadder(config && config.tiers);
    renderPoints();
    renderTier();
    renderRules();
    if (!p) $('pt-next-hero').textContent = 'Nu am putut încărca soldul punctelor. Reîncarcă pagina.';
    if (config) {
      if (count(config.points_expire_days) > 0) loadExpiring();
      else renderExpiring(0, 0, false);
    }
  }, function () { guard(); });

  BileteOnlineAPI.get('/customer/referrals').then(function (r) { renderReferral(r && r.data); }, function (err) {
    if (err && err.status === 401) { guard(); return; }
    $('pt-regen').disabled = true;
    linkSay('Nu am putut încărca linkul de afiliat. Reîncarcă pagina.', 'error');
  });
  loadHistory();
})();
