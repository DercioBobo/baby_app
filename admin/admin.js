const API_URL = '../api';

const DEV_MODE = location.hostname === 'localhost' || location.hostname === '127.0.0.1'
              || location.protocol === 'file:'
              || new URLSearchParams(location.search).has('dev');

let token    = sessionStorage.getItem('adm_token') || '';
let myPhone  = sessionStorage.getItem('adm_phone') || '';
let allUsers = [];
let settings = {};

// ── Dev mock data ─────────────────────────────────────────────────────────────
const DEV_STATS = {
  totalUsers: 12, totalBabies: 10, activeSessions: 3, newThisWeek: 4,
  totalLogs: 348, activeToday: 5, totalMilestones: 24, totalMedLogs: 61,
  logCounts: { sleep: 120, feed: 98, diaper: 87, growth: 18, med: 25 },
  regPerDay: [],
};
const DEV_USERS = [
  { id: 1, phone: '258841000001', role: 'admin',  baby_name: null,    mom_name: null,      birth_date: null,         created_at: '2025-01-10T09:00:00Z', log_count: 0,   last_activity: null },
  { id: 2, phone: '258841000002', role: 'user',   baby_name: 'Amara', mom_name: 'Sofia',   birth_date: '2025-02-14', created_at: '2025-02-20T11:30:00Z', log_count: 142, last_activity: Date.now() - 3600000 },
  { id: 3, phone: '258841000003', role: 'user',   baby_name: 'Liam',  mom_name: 'Beatriz', birth_date: '2024-11-05', created_at: '2024-11-10T08:15:00Z', log_count: 206, last_activity: Date.now() - 7200000 },
  { id: 4, phone: '258841000004', role: 'user',   baby_name: null,    mom_name: null,      birth_date: null,         created_at: '2025-04-30T16:00:00Z', log_count: 0,   last_activity: null },
];
const DEV_SETTINGS = {
  wa_config: { url: 'https://evo.exemplo.com', instance: 'bebe-instance', apiKey: '••••••••', enabled: true, welcome_enabled: true, status: 'connected' },
  templates: {},
};

// ── API ───────────────────────────────────────────────────────────────────────
const api = {
  h() { return { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }; },
  async req(method, path, data) {
    const opts = { method, headers: this.h() };
    if (data !== undefined) opts.body = JSON.stringify(data);
    try {
      const r    = await fetch(API_URL + path, opts);
      const json = await r.json().catch(() => ({}));
      return { ok: r.ok, status: r.status, data: json };
    } catch {
      return { ok: false, status: 0, data: { error: 'Sem ligação ao servidor.' } };
    }
  },
  get:  p     => api.req('GET',    p),
  post: (p,d) => api.req('POST',   p, d),
  del:  p     => api.req('DELETE', p),
};

function normalizePhone(raw) {
  const digits = raw.replace(/\D/g, '');
  return digits.startsWith('258') ? digits : '258' + digits;
}

// ── Auth ──────────────────────────────────────────────────────────────────────
async function doLogin() {
  const phone = normalizePhone(document.getElementById('adm-phone').value);
  const pass  = document.getElementById('adm-pass').value;
  const err   = document.getElementById('login-err');
  err.textContent = '';

  if (phone.length < 10) { err.textContent = 'Introduza o número de telefone.'; return; }
  if (!pass)  { err.textContent = 'Introduza a senha.'; return; }

  const r = await api.post('/auth.php?action=login', { phone, password: pass });
  if (!r.ok) { err.textContent = r.data.error || 'Erro de autenticação.'; return; }
  if (r.data.role !== 'admin') { err.textContent = 'Esta conta não tem permissões de administrador.'; return; }

  token   = r.data.token;
  myPhone = r.data.phone;
  sessionStorage.setItem('adm_token', token);
  sessionStorage.setItem('adm_phone', myPhone);
  startApp();
}

function doLogout() {
  api.post('/auth.php?action=logout').catch(() => {});
  sessionStorage.removeItem('adm_token');
  sessionStorage.removeItem('adm_phone');
  token = '';
  document.getElementById('shell').style.display      = 'none';
  document.getElementById('login-page').style.display = 'flex';
  document.getElementById('login-err').textContent    = '';
  document.getElementById('adm-pass').value           = '';
}

// ── Boot ──────────────────────────────────────────────────────────────────────
async function startApp() {
  document.getElementById('login-page').style.display = 'none';
  document.getElementById('shell').style.display      = 'grid';
  document.getElementById('hdr-phone').textContent    = '+' + myPhone;
  document.getElementById('s-admin-phone').value      = '+' + myPhone;
  await Promise.all([loadStats(), loadUsers(), loadSettings()]);
}

// ── Navigation ────────────────────────────────────────────────────────────────
function nav(page) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + page).classList.add('active');
  document.querySelector(`.nav-item[data-page="${page}"]`).classList.add('active');
  if (page === 'templates') renderTemplates();
  closeSidebar();
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-overlay').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('open');
}

// ── Stats ─────────────────────────────────────────────────────────────────────
async function loadStats() {
  const d = DEV_MODE ? DEV_STATS : (await api.get('/admin/stats.php')).data;
  document.getElementById('s-users').textContent      = d.totalUsers      ?? '—';
  document.getElementById('s-babies').textContent     = d.totalBabies     ?? '—';
  document.getElementById('s-sessions').textContent   = d.activeSessions  ?? '—';
  document.getElementById('s-week').textContent       = d.newThisWeek     ?? '—';
  document.getElementById('s-logs').textContent       = d.totalLogs       ?? '—';
  document.getElementById('s-today').textContent      = d.activeToday     ?? '—';
  document.getElementById('s-milestones').textContent = d.totalMilestones ?? '—';
  document.getElementById('s-medlogs').textContent    = d.totalMedLogs    ?? '—';

  const lc    = d.logCounts || {};
  const total = d.totalLogs || 1;
  const types = [
    { key: 'sleep',  label: '🌙 Sono',       color: '#7B9FBF' },
    { key: 'feed',   label: '🍼 Refeição',   color: '#E8968A' },
    { key: 'diaper', label: '🧷 Fralda',     color: '#C4A87D' },
    { key: 'med',    label: '💊 Medicamento',color: '#A07BBF' },
    { key: 'growth', label: '📏 Crescimento',color: '#8DC17A' },
  ];
  document.getElementById('s-log-breakdown').innerHTML = types.map(t => {
    const count = lc[t.key] || 0;
    const pct   = total ? Math.round((count / total) * 100) : 0;
    return `<div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
        <span>${t.label}</span>
        <span style="font-weight:600;color:var(--muted)">${count} <span style="font-weight:400;opacity:.6">(${pct}%)</span></span>
      </div>
      <div style="height:6px;background:var(--bg);border-radius:3px;overflow:hidden">
        <div style="height:100%;width:${pct}%;background:${t.color};border-radius:3px;transition:width .6s ease"></div>
      </div>
    </div>`;
  }).join('');
}

// ── Users ─────────────────────────────────────────────────────────────────────
async function loadUsers() {
  if (DEV_MODE) {
    allUsers = DEV_USERS;
  } else {
    const r = await api.get('/admin/users.php');
    if (!r.ok) return;
    allUsers = r.data;
  }
  renderUsers(allUsers);
  renderRecent(allUsers.slice(0, 6));
}

function fmtDate(s) {
  if (!s) return '—';
  return new Date(s).toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function ageStr(d) {
  if (!d) return '';
  const days   = Math.floor((Date.now() - new Date(d)) / 86400000);
  if (days < 30)   return `${days}d`;
  const months = Math.floor(days / 30.44);
  if (months < 24) return `${months}m`;
  return `${Math.floor(months / 12)}a`;
}

function fmtActivity(tsMs) {
  if (!tsMs) return '<span class="muted-cell">—</span>';
  const diff = Date.now() - tsMs;
  if (diff < 60000)           return '<span style="color:var(--success);font-weight:600">agora</span>';
  if (diff < 3600000)         return `<span style="color:var(--success)">${Math.floor(diff/60000)}min atrás</span>`;
  if (diff < 86400000)        return `${Math.floor(diff/3600000)}h atrás`;
  if (diff < 86400000 * 7)   return `${Math.floor(diff/86400000)}d atrás`;
  return fmtDate(new Date(tsMs).toISOString());
}

function renderUsers(list) {
  const tbody = document.getElementById('users-tbody');
  if (!list.length) {
    tbody.innerHTML = `<tr class="empty-row"><td colspan="8">Nenhum utilizador encontrado.</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(u => `
    <tr>
      <td>${u.baby_name ? `<strong>${u.baby_name}</strong> <span class="muted-cell">${u.birth_date ? '('+ageStr(u.birth_date)+')' : ''}</span>` : '<span class="muted-cell">—</span>'}</td>
      <td>${u.mom_name ? `<span style="font-weight:500">${u.mom_name}</span>` : '<span class="muted-cell">—</span>'}</td>
      <td><span class="mono">+${u.phone}</span></td>
      <td><span class="badge ${u.role === 'admin' ? 'badge-admin' : 'badge-user'}">${u.role === 'admin' ? 'Admin' : 'Utilizador'}</span></td>
      <td style="font-weight:600;text-align:center">${u.log_count || 0}</td>
      <td>${fmtActivity(u.last_activity ? u.last_activity * 1 : null)}</td>
      <td class="muted-cell">${fmtDate(u.created_at)}</td>
      <td>
        <div class="actions-cell">
          <button class="btn btn-ghost btn-sm" onclick="openReset(${u.id},'${u.phone}')">🔑 Senha</button>
          ${u.role !== 'admin' ? `<button class="btn btn-danger btn-sm" onclick="openDelete(${u.id},'${u.phone}')">🗑</button>` : ''}
        </div>
      </td>
    </tr>`).join('');
}

function renderRecent(list) {
  const tbody = document.getElementById('recent-tbody');
  if (!list.length) {
    tbody.innerHTML = `<tr class="empty-row"><td colspan="6">Sem utilizadores ainda.</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(u => `
    <tr>
      <td>${u.baby_name ? `<strong>${u.baby_name}</strong>` : '<span class="muted-cell">—</span>'}</td>
      <td>${u.mom_name ? `<span style="font-weight:500">${u.mom_name}</span>` : '<span class="muted-cell">—</span>'}</td>
      <td><span class="mono">+${u.phone}</span></td>
      <td style="font-weight:600;text-align:center">${u.log_count || 0}</td>
      <td>${fmtActivity(u.last_activity ? u.last_activity * 1 : null)}</td>
      <td class="muted-cell">${fmtDate(u.created_at)}</td>
    </tr>`).join('');
}

function filterUsers() {
  const q = document.getElementById('user-search').value.toLowerCase();
  renderUsers(allUsers.filter(u =>
    u.phone.includes(q) ||
    (u.baby_name || '').toLowerCase().includes(q) ||
    (u.mom_name  || '').toLowerCase().includes(q)
  ));
}

// ── Reset password ────────────────────────────────────────────────────────────
function openReset(id, phone) {
  document.getElementById('modal-reset-id').value        = id;
  document.getElementById('modal-reset-sub').textContent = `Utilizador: +${phone}`;
  document.getElementById('modal-reset-pass').value      = '';
  fb('modal-reset-fb', '', '');
  document.getElementById('modal-reset').style.display   = 'flex';
}

async function submitReset() {
  const id   = document.getElementById('modal-reset-id').value;
  const pass = document.getElementById('modal-reset-pass').value;
  if (pass.length < 6) { fb('modal-reset-fb', 'Mínimo 6 caracteres.', 'err'); return; }
  const r = await api.post('/admin/users.php?action=reset_password', { id: +id, password: pass });
  if (r.ok) { fb('modal-reset-fb', 'Senha alterada!', 'ok'); setTimeout(() => closeModal('reset'), 1200); }
  else fb('modal-reset-fb', r.data.error || 'Erro.', 'err');
}

// ── Delete user ───────────────────────────────────────────────────────────────
function openDelete(id, phone) {
  document.getElementById('modal-delete-id').value        = id;
  document.getElementById('modal-delete-sub').textContent =
    `Eliminar +${phone}? Esta acção apaga todos os dados do utilizador incluindo bebé, registos e medicamentos. É irreversível.`;
  document.getElementById('modal-delete').style.display   = 'flex';
}

async function submitDelete() {
  const id = document.getElementById('modal-delete-id').value;
  const r  = await api.del(`/admin/users.php?id=${id}`);
  if (r.ok) { closeModal('delete'); await Promise.all([loadUsers(), loadStats()]); }
  else alert(r.data.error || 'Erro ao eliminar.');
}

function closeModal(id) { document.getElementById('modal-' + id).style.display = 'none'; }

// ── Settings / WA ─────────────────────────────────────────────────────────────
async function loadSettings() {
  if (DEV_MODE) {
    settings = DEV_SETTINGS;
  } else {
    const r = await api.get('/settings.php');
    if (!r.ok) return;
    settings = r.data;
  }

  const wa = settings.wa_config || {};
  document.getElementById('wa-url').value            = wa.url      || '';
  document.getElementById('wa-instance').value       = wa.instance || '';
  document.getElementById('wa-key').value            = wa.apiKey   || '';
  document.getElementById('wa-enabled').checked      = wa.enabled !== false;
  document.getElementById('wa-welcome').checked      = wa.welcome_enabled !== false;
  setWaStatus(wa.status || 'unknown');

  const testPhoneEl = document.getElementById('wa-test-phone');
  if (testPhoneEl && !testPhoneEl.value) testPhoneEl.value = myPhone;
}

function setWaStatus(s) {
  const dot  = document.getElementById('wa-dot');
  const text = document.getElementById('wa-status-text');
  if (s === 'connected')         { dot.className = 'dot dot-green'; text.textContent = '● WhatsApp ligado e pronto'; }
  else if (s === 'disconnected') { dot.className = 'dot dot-red';   text.textContent = '○ Instância desligada'; }
  else                           { dot.className = 'dot dot-gray';  text.textContent = '○ Estado não verificado'; }
}

function buildWaPayload() {
  return {
    url:             document.getElementById('wa-url').value.trim().replace(/\/$/, ''),
    instance:        document.getElementById('wa-instance').value.trim(),
    apiKey:          document.getElementById('wa-key').value.trim(),
    enabled:         document.getElementById('wa-enabled').checked,
    welcome_enabled: document.getElementById('wa-welcome').checked,
    status:          (settings.wa_config || {}).status || 'unknown',
  };
}

async function saveWa() {
  const wa = buildWaPayload();
  const r  = await api.post('/settings.php', { wa_config: wa });
  if (r.ok) { settings.wa_config = wa; fb('wa-fb', 'Configuração guardada!', 'ok'); }
  else fb('wa-fb', r.data.error || 'Erro ao guardar.', 'err');
}

async function saveWaToggles() { await saveWa(); }

async function testWa() {
  const url = document.getElementById('wa-url').value.trim();
  const key = document.getElementById('wa-key').value.trim();
  if (!url || !key) { fb('wa-fb', 'Preencha URL e API Key antes de testar.', 'err'); return; }
  fb('wa-fb', 'A verificar ligação…', '');
  try {
    const res  = await fetch(`${url}/instance/fetchInstances`, { headers: { apikey: key }, signal: AbortSignal.timeout(8000) });
    const json = await res.json().catch(() => null);
    const ok   = res.ok && json && !json.error;
    const st   = ok ? 'connected' : 'disconnected';
    setWaStatus(st);
    if (settings.wa_config) settings.wa_config.status = st;
    await api.post('/settings.php', { wa_config: { ...buildWaPayload(), status: st } });
    fb('wa-fb', ok ? '✅ Ligação estabelecida!' : '❌ Não foi possível ligar. Verifique os dados.', ok ? 'ok' : 'err');
  } catch {
    setWaStatus('disconnected');
    fb('wa-fb', '❌ Servidor inacessível ou tempo esgotado.', 'err');
  }
}

// ── Templates ─────────────────────────────────────────────────────────────────
const DEFAULT_TPL = {
  welcome: '🌸 *Bem-vinda ao bebélog, {mamã}!*\n\nEstamos felizes por estar consigo nesta jornada com a {bebé}. 💕\n\nJá pode registar o sono, alimentação, fraldas e muito mais.\n\n_bebélog — feito com amor para as mamãs de Moçambique_ 🌺',
  meds:    '💊 *{bebé} tomou medicamento*\n\n{med}{dose}\n🕐 {hora}\n\n_bebélog_ 💕',
  summary: '🌸 *Resumo do dia — {bebé}*\n📅 {data}\n👶 {idade}\n\n🌙 Sono: {sono} ({sestas} sestas)\n🍼 Refeições: {refeições}\n🧷 Fraldas: {fraldas}\n⏰ Próx. sono: ~{proxSono}\n\n_bebélog_ 💕',
  sleep:   '⏰ *Hora de dormir!*\n\nA {bebé} está acordada há {acordada}. Está na hora do soninho!\n\n_bebélog_ 💕',
};

const TPL_META = {
  welcome: { title: '🌸 Boas-vindas (novo registo)', vars: ['bebé', 'mamã'] },
  meds:    { title: '💊 Medicamento administrado',   vars: ['bebé', 'mamã', 'med', 'dose', 'hora'] },
  summary: { title: '📊 Resumo diário',              vars: ['bebé', 'mamã', 'data', 'idade', 'sono', 'sestas', 'refeições', 'fraldas', 'proxSono'] },
  sleep:   { title: '⏰ Alerta de sono',             vars: ['bebé', 'mamã', 'acordada'] },
};

const PREVIEW = {
  bebé: 'Amara', mamã: 'Sofia', med: 'Paracetamol', dose: ' — 5ml', hora: '14h30',
  data: 'Segunda, 7 de maio', idade: '3 meses e 2 dias',
  sono: '4h 30min', sestas: '3', refeições: '7', fraldas: '8',
  proxSono: '15h45', acordada: '2h 15min',
};

function fillTpl(t, v) { return (t || '').replace(/\{([^}]+)\}/g, (_, k) => v[k] ?? `{${k}}`); }

function renderTemplates() {
  const tpl = settings.templates || {};
  document.getElementById('tpl-container').innerHTML = Object.keys(TPL_META).map(id => {
    const { title, vars } = TPL_META[id];
    const val   = tpl[id] ?? DEFAULT_TPL[id];
    const chips = vars.map(v => `<span class="tpl-var" onclick="insertVar('ta-${id}','${v}')">{${v}}</span>`).join('');
    return `
    <div class="card" style="margin-bottom:22px">
      <div class="card-head">
        <div class="card-title">${title}</div>
        <button class="btn btn-ghost btn-sm" onclick="resetTpl('${id}')">Repor padrão</button>
      </div>
      <div class="card-body">
        <div class="fgroup">
          <label class="flabel">Mensagem</label>
          <textarea class="ftextarea" id="ta-${id}" oninput="updPreview('${id}')">${val}</textarea>
        </div>
        <div class="tpl-vars">${chips}</div>
        <div class="flabel" style="margin-top:14px;margin-bottom:5px">Preview (dados de exemplo)</div>
        <div class="tpl-preview" id="prev-${id}">${fillTpl(val, PREVIEW)}</div>
        <div class="factions">
          <button class="btn btn-primary" onclick="saveTpl('${id}')">Guardar template</button>
          <button class="btn btn-ghost" onclick="toggleTestRow('${id}')">📤 Enviar teste</button>
        </div>
        <div class="tpl-test-row" id="test-row-${id}">
          <input class="finput" id="test-phone-${id}" type="tel" placeholder="258841234567" style="font-family:'IBM Plex Mono',monospace;max-width:240px">
          <button class="btn btn-primary btn-sm" onclick="sendTestTpl('${id}')">Enviar agora</button>
        </div>
        <div class="fb" id="fb-${id}"></div>
        <div class="fb" id="fb-test-${id}"></div>
      </div>
    </div>`;
  }).join('');
}

function insertVar(taId, v) {
  const ta = document.getElementById(taId);
  const s  = ta.selectionStart, e = ta.selectionEnd;
  ta.value = ta.value.slice(0, s) + `{${v}}` + ta.value.slice(e);
  ta.selectionStart = ta.selectionEnd = s + v.length + 2;
  ta.focus();
  updPreview(taId.replace('ta-', ''));
}

function updPreview(id) {
  const el = document.getElementById('prev-' + id);
  if (el) el.textContent = fillTpl(document.getElementById('ta-' + id).value, PREVIEW);
}

function resetTpl(id) {
  document.getElementById('ta-' + id).value = DEFAULT_TPL[id];
  updPreview(id);
}

function toggleTestRow(id) {
  const row  = document.getElementById('test-row-' + id);
  const isOpen = row.classList.toggle('open');
  if (isOpen) {
    const ph = document.getElementById('test-phone-' + id);
    if (!ph.value) ph.value = myPhone;
    ph.focus();
    fb('fb-test-' + id, '', '');
  }
}

async function sendTestTpl(id) {
  const phoneRaw = document.getElementById('test-phone-' + id).value.trim();
  if (!phoneRaw) { fb('fb-test-' + id, 'Introduza um número de destino.', 'err'); return; }
  const tpl = (settings.templates?.[id]) ?? DEFAULT_TPL[id];
  const msg = fillTpl(tpl, PREVIEW);
  fb('fb-test-' + id, 'A enviar…', '');
  if (DEV_MODE) {
    await new Promise(r => setTimeout(r, 600));
    fb('fb-test-' + id, `✅ [DEV] Simulado para +${phoneRaw.replace(/\D/g, '')}`, 'ok');
    return;
  }
  const r = await api.post('/send_notification.php', { message: msg, phones: [phoneRaw] });
  fb('fb-test-' + id, r.ok ? `✅ Enviado para +${phoneRaw.replace(/\D/g, '')}!` : (r.data.error || 'Erro ao enviar.'), r.ok ? 'ok' : 'err');
}

async function sendWaTest() {
  const phoneRaw = document.getElementById('wa-test-phone').value.trim();
  const msg      = document.getElementById('wa-test-msg').value.trim();
  if (!phoneRaw) { fb('wa-test-fb', 'Introduza um número de destino.', 'err'); return; }
  if (!msg)      { fb('wa-test-fb', 'Introduza uma mensagem.', 'err'); return; }
  fb('wa-test-fb', 'A enviar…', '');
  if (DEV_MODE) {
    await new Promise(r => setTimeout(r, 600));
    fb('wa-test-fb', `✅ [DEV] Simulado para +${phoneRaw.replace(/\D/g, '')}`, 'ok');
    return;
  }
  const r = await api.post('/send_notification.php', { message: msg, phones: [phoneRaw] });
  fb('wa-test-fb', r.ok ? `✅ Mensagem enviada para +${phoneRaw.replace(/\D/g, '')}!` : (r.data.error || 'Erro ao enviar.'), r.ok ? 'ok' : 'err');
}

async function saveTpl(id) {
  const val = document.getElementById('ta-' + id).value;
  if (!settings.templates) settings.templates = {};
  settings.templates[id] = val;
  const r = await api.post('/settings.php', { templates: settings.templates });
  fb('fb-' + id, r.ok ? 'Guardado!' : (r.data.error || 'Erro.'), r.ok ? 'ok' : 'err');
}

// ── Admin password ────────────────────────────────────────────────────────────
async function changePass() {
  const p1 = document.getElementById('s-newpass').value;
  const p2 = document.getElementById('s-confirm').value;
  if (p1.length < 6) { fb('pass-fb', 'Mínimo 6 caracteres.', 'err'); return; }
  if (p1 !== p2)     { fb('pass-fb', 'As senhas não coincidem.', 'err'); return; }
  const me = allUsers.find(u => u.phone === myPhone);
  if (!me) { fb('pass-fb', 'Não foi possível identificar a conta.', 'err'); return; }
  const r = await api.post('/admin/users.php?action=reset_password', { id: me.id, password: p1 });
  fb('pass-fb', r.ok ? '✅ Senha alterada com sucesso!' : (r.data.error || 'Erro.'), r.ok ? 'ok' : 'err');
  if (r.ok) { document.getElementById('s-newpass').value = ''; document.getElementById('s-confirm').value = ''; }
}

// ── Util ──────────────────────────────────────────────────────────────────────
function fb(id, msg, type) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent   = msg;
  el.className     = 'fb' + (type ? ' ' + type : '');
  el.style.display = msg ? 'block' : 'none';
}

// ── Init ──────────────────────────────────────────────────────────────────────
(async function init() {
  if (DEV_MODE) {
    myPhone = '258841000001';
    const banner = document.createElement('div');
    banner.style.cssText = 'position:fixed;bottom:12px;right:12px;background:#f59e0b;color:#fff;font-size:11px;font-weight:700;padding:4px 10px;border-radius:6px;z-index:9999;letter-spacing:.5px';
    banner.textContent = 'DEV MODE';
    document.body.appendChild(banner);
    await startApp();
    return;
  }
  if (token) {
    const r = await api.get('/admin/stats.php');
    if (r.status === 401) { sessionStorage.removeItem('adm_token'); token = ''; }
    else { await startApp(); return; }
  }
  document.getElementById('login-page').style.display = 'flex';
})();
