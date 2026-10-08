/* viaqui.com v2: customer points and referrals (/cont/puncte). Reads the programme rules (/customer/rewards/config),
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

  var LOC = VQ.locale === 'en' ? 'en-GB' : VQ.locale;
  var TYPE_LABEL = { earned: VQ.t('earned'), spent: VQ.t('used'), expired: VQ.t('expired'), affiliate: VQ.t('referral') };
  var TYPE_TONE = { earned: 'is-ok', spent: 'is-bad', expired: 'is-muted', affiliate: 'is-wait' };
  var TYPE_TITLE = { earned: VQ.t('Points earned'), spent: VQ.t('Points used'), expired: VQ.t('Points expired'), affiliate: VQ.t('Referral') };
  var ACTIONS = {
    order: VQ.t('Order'), purchase: VQ.t('Order'), refund: VQ.t('Points returned'), referral: VQ.t('Referral'), referral_reward: VQ.t('Referral'), referred: VQ.t('Welcome bonus'),
    signup: VQ.t('New account bonus'), birthday: VQ.t('Birthday bonus'), badge_bonus: VQ.t('Badge bonus'), redemption: VQ.t('Used on an order'),
    checkout: VQ.t('Used on an order'), reward_redemption: VQ.t('Reward'), expiration: VQ.t('Expiry'), manual_adjustment: VQ.t('Adjustment'),
    purchase_reversal: VQ.t('Order refund')
  };
  var SHARE_TEXT = VQ.t('Hi! I found Viaqui: tickets for attractions, tours and experiences across Europe. Use my link: ');
  var num = new Intl.NumberFormat(LOC);
  var pct = new Intl.NumberFormat(LOC, { maximumFractionDigits: 1 });
  var config = null, earnRate = '', ladder = [], history = [], page = 0, lastPage = 1, historyBusy = false;
  var points = { balance: 0, earned: 0, spent: 0, pending: 0 }, referral = { code: '', link: '' }, statusTimer = 0;

  // ---------- helpers ----------
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  // A translated sentence that carries its own <strong>: `html` comes from VQ.t, and any value from the API put
  // into it goes through esc() first.
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); }
  function rich(node, html) { node.innerHTML = html; }
  function txt(v) {
    if (v && typeof v === 'object') v = v[VQ.locale] || v.en || v.ro || v.name || Object.keys(v).map(function (k) { return v[k]; })[0];
    return v == null ? '' : String(v);
  }
  function amount(v) { var n = Number(v); return isFinite(n) ? n : 0; }
  function count(v) { return Math.max(0, Math.floor(amount(v))); }
  function pts(n) { return VQ.n(count(n), 'point', 'points'); }
  function daysText(n) { return VQ.n(count(n), 'day', 'days'); }
  // money is in the marketplace's currency
  function money(n) { n = amount(n); return typeof BileteOnlineUtils !== 'undefined' ? BileteOnlineUtils.formatCurrency(n) : '€' + n; }
  function show(id, on) { $(id).hidden = !on; }
  function obj(x) { return x && typeof x === 'object'; }
  function parseDate(v) { var d = v ? new Date(v) : null; return d && !isNaN(d.getTime()) ? d : null; }
  function shortDate(d) { return d.toLocaleDateString(LOC, { day: 'numeric', month: 'short', year: 'numeric' }); }
  function perLei() { return config && amount(config.points_per_lei) > 0 ? amount(config.points_per_lei) : 100; }
  function toLei(p) { return money(Math.floor(count(p) / perLei())); }
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
    var pending = $('pt-pending');
    if (pending) {
      pending.hidden = points.pending <= 0;
      if (points.pending > 0) rich(pending, VQ.t('+ <strong>{points}</strong> pending: they are added to your account after the activities you booked.', { points: pts(points.pending) }));
    }
    account.setBadges({ points: points.balance });
  }
  function buildLadder(tiers) {
    var last = 0;
    ladder = (Array.isArray(tiers) ? tiers : []).filter(obj).map(function (t, i) {
      var threshold = t.threshold_points != null ? amount(t.threshold_points) : (t.threshold != null ? amount(t.threshold) : (i === 0 ? 0 : null));
      var perks = (Array.isArray(t.benefits) ? t.benefits : (Array.isArray(t.perks) ? t.perks : [])).map(function (p) {
        return typeof p === 'string' ? { label: p, active: true } : { label: txt(obj(p) ? (p.label || p.name) : p), active: !obj(p) || p.active !== false };
      }).filter(function (p) { return p.label; });
      return { name: txt(t.name) || VQ.t('Level {n}', { n: i + 1 }), description: txt(t.description), threshold: threshold, perks: perks };
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
    var level = $('pt-level');
    if (level) {
      level.hidden = !ladder.length;
      if (level.parentNode) level.parentNode.classList.toggle('is-solo', !ladder.length);
    }
    var heroBar = $('pt-bar-hero');
    if (heroBar) heroBar.hidden = !ladder.length;
    if (!ladder.length) {
      $('pt-tier').textContent = '—';
      $('pt-next-hero').textContent = config && config.auto_rewards ? '' : VQ.t('The programme levels are not available right now.');
      $('pt-next-tier').textContent = VQ.t('The programme levels are not available right now.');
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
      rich($('pt-next-hero'), VQ.t('<strong>{n}</strong> more points to reach the <strong>{level}</strong> level.', { n: num.format(toNext), level: esc(next.name) }));
      rich($('pt-next-tier'), VQ.t('You need <strong>{n}</strong> more points for the next level.', { n: num.format(toNext) }));
    } else {
      $('pt-next-hero').textContent = VQ.t('You are at the highest level. Well done!');
      $('pt-next-tier').textContent = VQ.t('Highest level reached.');
    }
    var list = $('pt-perks');
    list.textContent = '';
    cur.perks.forEach(function (p) {
      var li = el('li');
      li.appendChild(el('span', null, p.label));
      li.appendChild(el('span', 'acc-tag ' + (p.active ? 'is-ok' : 'is-wait'), p.active ? VQ.t('active') : VQ.t('coming soon')));
      list.appendChild(li);
    });
  }
  function renderRules() {
    var list = $('pt-rate'), items = [], rule = $('pt-rule-exp');
    list.textContent = '';
    if (!config) {
      items.push(VQ.t('The programme rules are not available right now.'));
    } else {
      items.push(VQ.t('<strong>{n}</strong> points = {amount} off.', { n: num.format(perLei()), amount: money(1) }));
      if (earnRate) {
        items.push(config.auto_rewards && count(config.confirm_days) >= 0
          ? VQ.t('You earn <strong>{rate}</strong> on eligible orders, added to your account after the activity.', { rate: esc(earnRate) })
          : VQ.t('You earn <strong>{rate}</strong> on eligible orders.', { rate: esc(earnRate) }));
      }
      if (count(config.birthday_bonus_points) > 0 && config.auto_rewards) items.push(VQ.t('On your birthday: <strong>{points}</strong> (after your first order; add your date of birth in settings).', { points: pts(config.birthday_bonus_points) }));
      if (count(config.min_redeem_points) > 0) items.push(VQ.t('You can use your points once you have at least <strong>{points}</strong>.', { points: pts(config.min_redeem_points) }));
      if (amount(config.max_redeem_percentage) > 0) items.push(VQ.t('The discount from points covers at most <strong>{percent}%</strong> of an order.', { percent: pct.format(amount(config.max_redeem_percentage)) }));
      if (count(config.max_redeem_points_per_order) > 0) items.push(VQ.t('At most <strong>{points}</strong> per order (≈ {amount}).', { points: pts(config.max_redeem_points_per_order), amount: toLei(config.max_redeem_points_per_order) }));
      var days = count(config.points_expire_days);
      items.push(days ? VQ.t('Points expire after <strong>{days}</strong>.', { days: daysText(days) }) : VQ.t('Points do not expire.'));
      rich(rule, days ? VQ.t('<strong>Expiry:</strong> points expire after {days}.', { days: daysText(days) }) : VQ.t('<strong>Expiry:</strong> points do not expire.'));
      if (config.auto_rewards) {
        if (earnRate) rich($('pt-rule-earn'), VQ.t('<strong>Earning:</strong> {rate}, added to your account after the activity.', { rate: esc(earnRate) }));
        var bday = $('pt-rule-bday');
        if (bday && count(config.birthday_bonus_points) > 0) {
          rich(bday, VQ.t('<strong>Birthday:</strong> {points} every year, if you have bought at least once.', { points: pts(config.birthday_bonus_points) }));
          bday.hidden = false;
        }
      }
    }
    items.forEach(function (html) { var li = el('li'); rich(li, html); list.appendChild(li); });
  }
  function renderExpiring(soon, days, expires) {
    days = count(days) || 30;
    $('pt-s-expiring').textContent = num.format(soon);
    $('pt-s-expiring-p').textContent = !expires ? VQ.t('points do not expire') : (soon > 0 ? VQ.t('within {days}', { days: daysText(days) }) : VQ.t('nothing expiring soon'));
    $('pt-exp').classList.toggle('is-hot', soon > 0);
    $('pt-exp-h').textContent = soon > 0 ? VQ.t('Expiring soon: {points}', { points: pts(soon) }) : VQ.t('No points about to expire');
    $('pt-exp-p').textContent = soon > 0 ? VQ.t('Use them within {days} on an eligible order.', { days: daysText(days) })
      : (expires ? VQ.t('Keep buying to earn new points.') : VQ.t('Points in the Viaqui programme do not expire. Keep buying to earn new points.'));
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
    var unit = function (n) { return inPoints ? pts(n) : money(n); };
    if (mine) {
      var minOrder = amount(rewards.min_purchase);
      $('pt-aff-reward').textContent = (minOrder > 0
        ? VQ.t('You get {reward} for every friend who creates an account through your link and buys their first activity of at least {min}.', { reward: unit(mine), min: money(minOrder) })
        : VQ.t('You get {reward} for every friend who creates an account through your link and buys their first activity.', { reward: unit(mine) })) +
        (theirs ? ' ' + VQ.t('Your friend gets {reward} on the same order.', { reward: unit(theirs) }) : '') +
        (rewards.automatic ? ' ' + VQ.t('The points are added to your account automatically after the activity.') : '');
      show('pt-aff-reward', true);
      var refRule = $('pt-rule-ref');
      if (refRule) {
        var refVars = { mine: unit(mine), theirs: unit(theirs), min: money(minOrder) };
        if (theirs) rich(refRule, minOrder > 0 ? VQ.t('<strong>Referrals:</strong> {mine} for you and {theirs} for your friend, on their first order of at least {min}.', refVars) : VQ.t('<strong>Referrals:</strong> {mine} for you and {theirs} for your friend, on their first order.', refVars));
        else rich(refRule, minOrder > 0 ? VQ.t('<strong>Referrals:</strong> {mine} for you, on the first order of your friend, of at least {min}.', refVars) : VQ.t('<strong>Referrals:</strong> {mine} for you, on the first order of your friend.', refVars));
      }
    }
  }
  $('pt-copy').addEventListener('click', function () {
    var btn = this;
    if (!referral.link) return;
    var manual = function () { var input = $('pt-link'); input.focus(); input.select(); linkSay(VQ.t('The link is selected. Copy it by hand.')); };
    if (!(navigator.clipboard && navigator.clipboard.writeText)) { manual(); return; }
    navigator.clipboard.writeText(referral.link).then(function () {
      linkSay(VQ.t('Link copied to the clipboard.'));
      btn.textContent = VQ.t('Copied');
      setTimeout(function () { btn.textContent = VQ.t('Copy link'); }, 2000);
    }, manual);
  });
  $('pt-share').addEventListener('click', function () {
    if (typeof navigator.share !== 'function' || !referral.link) return;
    navigator.share({ title: 'viaqui.com', text: SHARE_TEXT.replace(/: $/, '.'), url: referral.link }).catch(function () {});
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
    btn.textContent = VQ.t('Making a new code…');
    BileteOnlineAPI.post('/customer/referrals/regenerate-code', {}).then(function (resp) {
      var d = resp && resp.data;
      if (!(resp && resp.success && obj(d) && (d.code || d.link))) throw { status: -1 };
      referral.code = txt(d.code) || referral.code;
      referral.link = /^https?:\/\//i.test(txt(d.link)) ? txt(d.link) : linkFor(referral.code);
      setLink();
      closeConfirm(false);
      $('pt-copy').focus();
      linkSay(VQ.t('You have a new code. The old link no longer works.'));
    }).catch(function (err) {
      linkSay(err && err.status === 401 ? VQ.t('Your session has expired. Sign in again.') : VQ.t('We could not make a new code. Try again.'), 'error');
    }).then(function () {
      btn.disabled = false;
      btn.textContent = VQ.t('Yes, make a new code');
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
    var meta = [ACTIONS[String(tx.action_type || '').toLowerCase()] || '', tx.balance_after != null ? VQ.t('balance {n}', { n: num.format(count(tx.balance_after)) }) : ''].filter(Boolean).join(' · ');
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
    if (page > 0) { more.disabled = true; more.textContent = VQ.t('Loading…'); }
    else { show('pt-h-error', false); show('pt-h-skel', true); }
    BileteOnlineAPI.get('/customer/rewards/history', { per_page: 50, page: page + 1 }).then(function (resp) {
      var data = resp && resp.data;
      var list = Array.isArray(data) ? data : (obj(data) && (data.history || data.items || data.transactions)) || [];
      history = history.concat(list.filter(obj));
      page++;
      lastPage = resp && obj(resp.meta) ? Math.max(1, count(resp.meta.last_page)) : page;
      more.textContent = VQ.t('Load more');
      renderHistory();
    }, function (err) {
      if (err && err.status === 401) { guard(); return; }
      if (page === 0) { show('pt-h-skel', false); show('pt-h-error', true); }
      else more.textContent = VQ.t('It did not load. Try again');
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
      points.pending = count(p.pending);
    }
    buildLadder(config && config.tiers);
    renderPoints();
    renderTier();
    renderRules();
    if (!p) $('pt-next-hero').textContent = VQ.t('We could not load your points balance. Reload the page.');
    if (config) {
      if (count(config.points_expire_days) > 0) {
        if (config.auto_rewards && p && p.expiring_soon != null) renderExpiring(count(p.expiring_soon), config.expiring_warning_days, true);
        else loadExpiring();
      } else renderExpiring(0, 0, false);
    }
  }, function () { guard(); });

  BileteOnlineAPI.get('/customer/referrals').then(function (r) { renderReferral(r && r.data); }, function (err) {
    if (err && err.status === 401) { guard(); return; }
    $('pt-regen').disabled = true;
    linkSay(VQ.t('We could not load your referral link. Reload the page.'), 'error');
  });
  loadHistory();
})();
