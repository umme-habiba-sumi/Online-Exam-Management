/* ============================================================
   Online Exam Management System - Shared front-end behaviour
   API helper + UI widgets used across admin/student/auth pages.
   ============================================================ */

// ---- API base: XAMPP uses backend/api; Vercel uses /api/index.php router ----
function isLocalDevHost() {
  const h = window.location.hostname;
  return h === 'localhost' || h === '127.0.0.1' || h.endsWith('.local');
}

/** Root-relative app URL — always stay on current Vercel deployment. */
function appUrl(path) {
  if (!path) return window.location.origin + '/';
  if (/^https?:\/\//i.test(path)) return path;

  const clean = String(path).replace(/^\//, '');

  if (isLocalDevHost()) {
    if (path.startsWith('/')) {
      const base = window.location.pathname.replace(/\/[^/]*$/, '');
      return base + path;
    }
    return clean;
  }

  return window.location.origin + '/' + clean;
}

function buildApiUrl(path) {
  const clean = path.replace(/^\//, '');
  if (!isLocalDevHost()) {
    const qIdx = clean.indexOf('?');
    const file = qIdx >= 0 ? clean.slice(0, qIdx) : clean;
    const qs = qIdx >= 0 ? clean.slice(qIdx + 1) : '';
    let url = `/api/${file}`;
    if (qs) url += `?${qs}`;
    return url;
  }
  const pagePath = window.location.pathname.replace(/\\/g, '/');
  const base = /\/(student|admin)\//i.test(pagePath) ? '../backend/api' : 'backend/api';
  return `${base}/${clean}`;
}

const API_BASE = (() => {
  const path = window.location.pathname.replace(/\\/g, '/');
  if (/\/(student|admin)\//i.test(path)) return '../backend/api';
  return 'backend/api';
})();

window.__CSRF_TOKEN = window.__CSRF_TOKEN || '';

async function api(path, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
  const headers = {
    ...(options.headers || {})
  };

  if (!isFormData) {
    headers['Content-Type'] = headers['Content-Type'] || 'application/json';
  }

  if (method !== 'GET' && method !== 'HEAD' && window.__CSRF_TOKEN) {
    headers['X-CSRF-Token'] = window.__CSRF_TOKEN;
  }

  const opts = {
    credentials: 'include',
    ...options,
    headers
  };

  const res = await fetch(buildApiUrl(path), opts);
  const rawText = await res.text();
  let data = null;
  try {
    data = rawText ? JSON.parse(rawText) : null;
  } catch (_) {
    data = {
      ok: false,
      error: res.ok
        ? 'Invalid server response.'
        : `Server error (${res.status}). Check Vercel API / backend deploy.`,
    };
  }

  if (data && data.csrf_token) {
    window.__CSRF_TOKEN = data.csrf_token;
  }

  if (!res.ok) {
    const err = new Error(data && data.error ? data.error : `Request failed (${res.status})`);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

// ---- Generic tab switcher: works on any .tabs / .tab-panel pair ----
function initTabs(scope) {
  const root = scope || document;
  const tabs = root.querySelectorAll('.tab');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.getAttribute('data-tab');
      const tabGroup = tab.closest('.tabs');
      tabGroup.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      const panelGroup = tabGroup.parentElement;
      panelGroup.querySelectorAll(':scope > .tab-panel').forEach(p => p.classList.remove('active'));
      const panel = panelGroup.querySelector('#' + target);
      if (panel) panel.classList.add('active');
    });
  });
}

// ---- Login / register role toggle ----
function updateLoginFields(role) {
  const emailWrap = document.getElementById('loginEmailWrap');
  const studentWrap = document.getElementById('loginStudentWrap');
  const email = document.getElementById('email');
  const studentId = document.getElementById('studentId');
  if (!emailWrap || !studentWrap) return;

  const isStudent = role === 'student';
  emailWrap.hidden = isStudent;
  studentWrap.hidden = !isStudent;
  if (email) email.required = !isStudent;
  if (studentId) studentId.required = isStudent;
}

function initRoleToggle() {
  const options = document.querySelectorAll('.role-option');
  const input = document.getElementById('roleInput');
  options.forEach(opt => {
    opt.addEventListener('click', () => {
      options.forEach(o => o.classList.remove('active'));
      opt.classList.add('active');
      const role = opt.getAttribute('data-role');
      if (input) input.value = role;
      updateLoginFields(role);
    });
  });
  if (input) updateLoginFields(input.value || 'student');
}

// ---- Simple client-side validation feedback ----
function showFieldError(inputEl, message) {
  let err = inputEl.parentElement.querySelector('.error-text');
  if (!err) {
    err = document.createElement('div');
    err.className = 'error-text';
    inputEl.parentElement.appendChild(err);
  }
  err.textContent = message;
  err.classList.add('show');
}

function clearFieldError(inputEl) {
  const err = inputEl.parentElement.querySelector('.error-text');
  if (err) err.classList.remove('show');
}

// ---- Modal helpers ----
function initModals() {
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    const modal = overlay.querySelector('.modal');
    if (!modal || modal.querySelector('.modal-close')) return;

    const overlayId = overlay.id;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'modal-close';
    btn.setAttribute('aria-label', 'Close');
    btn.innerHTML = '&times;';
    btn.addEventListener('click', () => closeModal(overlayId));

    const h3 = modal.querySelector(':scope > h3, :scope > .modal-header > h3, :scope > form > h3');
    if (h3 && !h3.closest('.modal-header')) {
      const header = document.createElement('div');
      header.className = 'modal-header';
      h3.parentNode.insertBefore(header, h3);
      header.appendChild(h3);
      header.appendChild(btn);
    } else if (modal.querySelector('.modal-header')) {
      modal.querySelector('.modal-header').appendChild(btn);
    } else {
      modal.insertBefore(btn, modal.firstChild);
      btn.style.position = 'absolute';
      btn.style.top = '14px';
      btn.style.right = '14px';
      modal.style.position = 'relative';
    }
  });
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('open');
  // #region agent log
  if (id === 'logoutModal') {
    fetch('http://127.0.0.1:7578/ingest/2dc8a71c-1580-4782-8139-68ca44edb8d0',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'20c118'},body:JSON.stringify({sessionId:'20c118',runId:'signout-direct',hypothesisId:'A',location:'main.js:openModal',message:'logoutModal opened',data:{id,stack:(new Error()).stack?.split('\\n').slice(0,4)},timestamp:Date.now()})}).catch(()=>{});
  }
  if (id === 'createExamModal' && el) {
    requestAnimationFrame(() => {
      const modal = el.querySelector('.modal');
      const scrollEl = modal && modal.querySelector('div[style*="padding"]');
      const er = el.getBoundingClientRect();
      const mr = modal ? modal.getBoundingClientRect() : null;
      const cs = scrollEl ? getComputedStyle(scrollEl) : null;
      fetch('http://127.0.0.1:7578/ingest/2dc8a71c-1580-4782-8139-68ca44edb8d0',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'20c118'},body:JSON.stringify({sessionId:'20c118',runId:'pre-fix',hypothesisId:'C,D,E',location:'main.js:openModal',message:'createExamModal layout metrics',data:{vw:window.innerWidth,vh:window.innerHeight,overlay:{left:er.left,width:er.width,top:er.top,display:getComputedStyle(el).display,align:getComputedStyle(el).alignItems,justify:getComputedStyle(el).justifyContent},modal:mr?{left:mr.left,width:mr.width,centerX:mr.left+mr.width/2,offsetFromCenter:(mr.left+mr.width/2)-window.innerWidth/2}:null,scroll:{overflowY:cs&&cs.overflowY,scrollbarWidth:cs&&cs.scrollbarWidth,clientW:scrollEl&&scrollEl.clientWidth,scrollW:scrollEl&&scrollEl.scrollWidth,hasCreatePanel:!!document.getElementById('create')},parentTransform:!!(el.offsetParent&&getComputedStyle(el.offsetParent).transform!=='none')},timestamp:Date.now()})}).catch(()=>{});
    });
  }
  // #endregion
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('open');
}

function avatarInitials(name) {
  return String(name || '?')
    .trim()
    .split(/\s+/)
    .map(w => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase() || '?';
}

function avatarUrl(path) {
  if (!path) return '';
  if (/^https?:\/\//i.test(path) || path.startsWith('data:')) return path;
  const clean = String(path).replace(/^\//, '');
  const base = API_BASE.replace(/\/api\/?$/, '/');
  // API_BASE is ../backend/api or backend/api → project root is one level above backend
  if (API_BASE.startsWith('../')) return '../' + clean;
  return clean;
}

function renderAvatarInto(el, user) {
  if (!el || !user) return;
  if (user.avatar) {
    el.innerHTML = '';
    const img = document.createElement('img');
    img.src = avatarUrl(user.avatar);
    img.alt = user.name || 'Profile';
    el.appendChild(img);
  } else {
    el.textContent = avatarInitials(user.name);
  }
}

function formatNotifTime(iso) {
  if (!iso) return '';
  const d = new Date(String(iso).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function populateProfileModal(user, role) {
  const modal = document.getElementById('profileModal');
  if (!modal || !user) return;

  const set = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val || '—';
  };

  set('profileModalName', user.name);
  set('profileModalEmail', user.email);
  set('profileModalDept', user.department || 'CSE');
  set('profileModalId', user.roll_or_id || '—');
  set('profileModalRole', role === 'admin' ? (user.designation || 'Admin / Teacher') : 'Student');

  const avatar = document.getElementById('profileModalAvatar');
  if (avatar) renderAvatarInto(avatar, user);

  const badge = document.getElementById('profileModalBadge');
  if (badge) badge.textContent = role === 'admin' ? 'Admin / Teacher' : 'Student';
}

function updateBreadcrumb(rootLabel, currentLabel) {
  const root = document.getElementById('breadcrumbRoot');
  const current = document.getElementById('breadcrumbCurrent');
  if (root && rootLabel) root.textContent = rootLabel;
  if (current && currentLabel) current.textContent = currentLabel;
}

function initAppHeader(user, role) {
  const profileBtn = document.getElementById('headerProfileBtn');
  const profileAvatar = document.getElementById('headerProfileAvatar');
  const profileName = document.getElementById('headerProfileName');
  const profileMeta = document.getElementById('headerProfileMeta');

  if (user) {
    const metaText = role === 'admin'
      ? (user.designation || 'Admin / Teacher')
      : `${user.department || 'CSE'} · ${user.roll_or_id || 'Student'}`;

    if (profileAvatar) renderAvatarInto(profileAvatar, user);
    if (profileName) profileName.textContent = user.name;
    if (profileMeta) profileMeta.textContent = metaText;
  }

  if (profileBtn && user) {
    profileBtn.addEventListener('click', () => {
      populateProfileModal(user, role);
      openModal('profileModal');
      // #region agent log
      fetch('http://127.0.0.1:7578/ingest/2dc8a71c-1580-4782-8139-68ca44edb8d0',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'20c118'},body:JSON.stringify({sessionId:'20c118',runId:'profile-close-rm',hypothesisId:'A',location:'main.js:profileBtn.click',message:'profile modal open — close btn audit',data:{footerCloseBtns:Array.from(document.querySelectorAll('#profileModal .modal-actions button')).map(b=>b.textContent.trim())},timestamp:Date.now()})}).catch(()=>{});
      // #endregion
    });
  }

  // #region agent log
  fetch('http://127.0.0.1:7578/ingest/2dc8a71c-1580-4782-8139-68ca44edb8d0',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'20c118'},body:JSON.stringify({sessionId:'20c118',runId:'notif-modal',hypothesisId:'A',location:'main.js:initAppHeader',message:'header layout audit',data:{sidebarProfileLinks:document.querySelectorAll('.sidebar a[href*="profile"]').length,hasBreadcrumb:!!document.getElementById('breadcrumbCurrent'),hasProfileChip:!!document.getElementById('headerProfileBtn'),hasNotifModal:!!document.getElementById('notifModal'),hasNotifDropdown:!!document.getElementById('notifDropdown')},timestamp:Date.now()})}).catch(()=>{});
  // #endregion

  const notifBtn = document.getElementById('notifBtn');
  const badge = document.getElementById('notifBadge');
  const listEl = document.getElementById('notifModalList');
  if (!notifBtn || !badge || !listEl) return;

  async function renderNotifications(openAfter) {
    try {
      const data = await api('notifications/list.php');
      const items = data.notifications || [];
      const unread = Number(data.unread_count) || 0;
      badge.textContent = unread > 99 ? '99+' : String(unread);
      badge.hidden = unread < 1;

      // #region agent log
      fetch('http://127.0.0.1:7578/ingest/2dc8a71c-1580-4782-8139-68ca44edb8d0',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'20c118'},body:JSON.stringify({sessionId:'20c118',runId:'notif-modal',hypothesisId:'B',location:'main.js:renderNotifications',message:'notifications rendered',data:{count:items.length,unread:unread,willOpen:!!openAfter},timestamp:Date.now()})}).catch(()=>{});
      // #endregion

      if (!items.length) {
        listEl.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
      } else {
        listEl.innerHTML = items.map(item => `
          <button type="button" class="notif-item ${item.is_read ? '' : 'unread'}" data-id="${item.id}" data-link="${item.link || ''}">
            <div class="notif-title">${escapeHtml(item.title)}</div>
            <div class="notif-body">${escapeHtml(item.body || '')}</div>
            <div class="notif-time">${formatNotifTime(item.created_at)}</div>
          </button>
        `).join('');

        listEl.querySelectorAll('.notif-item').forEach(el => {
          el.addEventListener('click', async () => {
            const id = Number(el.getAttribute('data-id'));
            const link = el.getAttribute('data-link');
            try {
              await api('notifications/mark-read.php', {
                method: 'POST',
                body: JSON.stringify({ id })
              });
            } catch (_) {}
            closeModal('notifModal');
            renderNotifications(false);
            if (link) {
              const hashIdx = link.indexOf('#');
              if (hashIdx >= 0) {
                const hash = link.slice(hashIdx + 1);
                const page = link.slice(0, hashIdx) || 'dashboard.html';
                const onSamePage = !page || page === 'dashboard.html' ||
                  window.location.pathname.endsWith('/' + page) ||
                  window.location.pathname.endsWith(page);
                if (onSamePage && hash && typeof window.goto === 'function') {
                  window.goto(hash);
                  return;
                }
              }
              window.location.href = link;
            }
          });
        });
      }
    } catch (_) {
      listEl.innerHTML = '<div class="notif-empty">Could not load notifications.</div>';
    }

    if (openAfter) openModal('notifModal');
  }

  notifBtn.addEventListener('click', () => {
    // #region agent log
    fetch('http://127.0.0.1:7578/ingest/2dc8a71c-1580-4782-8139-68ca44edb8d0',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'20c118'},body:JSON.stringify({sessionId:'20c118',runId:'notif-modal',hypothesisId:'C',location:'main.js:notifBtn.click',message:'notif icon clicked — opening modal',data:{modalExists:!!document.getElementById('notifModal')},timestamp:Date.now()})}).catch(()=>{});
    // #endregion
    renderNotifications(true);
  });

  const markAllBtn = document.getElementById('notifMarkAllBtn');
  if (markAllBtn) {
    markAllBtn.addEventListener('click', async () => {
      try {
        await api('notifications/mark-read.php', {
          method: 'POST',
          body: JSON.stringify({})
        });
      } catch (_) {}
      renderNotifications(false);
    });
  }

  renderNotifications(false);
  window.__refreshNotifications = () => renderNotifications(false);
  setInterval(() => renderNotifications(false), 30000);
}

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function initPanelNav(panels, titles, defaultPanel, breadcrumbRoot) {
  const rootLabel = breadcrumbRoot || 'Workspace';

  function goto(tabName) {
    panels.forEach(id => {
      const panel = document.getElementById(id);
      if (panel) panel.classList.toggle('active', id === tabName);
    });
    document.querySelectorAll('.side-link[data-goto]').forEach(link => {
      link.classList.toggle('active', link.getAttribute('data-goto') === tabName);
    });
    const meta = titles[tabName];
    if (meta) {
      updateBreadcrumb(rootLabel, meta[0]);
    }
  }

  document.querySelectorAll('.side-link[data-goto]').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      goto(link.getAttribute('data-goto'));
    });
  });

  window.goto = goto;
  goto(defaultPanel || panels[0]);
}

// ---- Exam countdown timer + tick sound ----
let __tickAudioCtx = null;
let __tickPlayCount = 0;

function ensureTickAudio() {
  if (!__tickAudioCtx) {
    __tickAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
  }
  if (__tickAudioCtx.state === 'suspended') {
    __tickAudioCtx.resume();
  }
  return __tickAudioCtx;
}

function playCountdownTick(urgent) {
  try {
    const ctx = ensureTickAudio();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.value = urgent ? 880 : 660;
    gain.gain.value = urgent ? 0.08 : 0.045;
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + (urgent ? 0.12 : 0.08));
    osc.stop(ctx.currentTime + (urgent ? 0.12 : 0.08));
    __tickPlayCount += 1;
    // #region agent log
    if (__tickPlayCount <= 3 || urgent || __tickPlayCount % 30 === 0) {
      fetch('http://127.0.0.1:7578/ingest/2dc8a71c-1580-4782-8139-68ca44edb8d0',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'20c118'},body:JSON.stringify({sessionId:'20c118',runId:'countdown-sound',hypothesisId:'A,B',location:'main.js:playCountdownTick',message:'tick played',data:{urgent:!!urgent,playCount:__tickPlayCount,ctxState:ctx.state},timestamp:Date.now()})}).catch(()=>{});
    }
    // #endregion
  } catch (err) {
    // #region agent log
    fetch('http://127.0.0.1:7578/ingest/2dc8a71c-1580-4782-8139-68ca44edb8d0',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'20c118'},body:JSON.stringify({sessionId:'20c118',runId:'countdown-sound',hypothesisId:'B',location:'main.js:playCountdownTick',message:'tick failed',data:{error:String(err&&err.message||err)},timestamp:Date.now()})}).catch(()=>{});
    // #endregion
  }
}

function startExamTimer(durationSeconds, onTick, onExpire) {
  let remaining = durationSeconds;
  // #region agent log
  fetch('http://127.0.0.1:7578/ingest/2dc8a71c-1580-4782-8139-68ca44edb8d0',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'20c118'},body:JSON.stringify({sessionId:'20c118',runId:'countdown-sound',hypothesisId:'A,C',location:'main.js:startExamTimer',message:'countdown started',data:{durationSeconds,willTickEverySecond:true},timestamp:Date.now()})}).catch(()=>{});
  // #endregion
  try { ensureTickAudio(); } catch (_) {}
  onTick(remaining);
  // Start-of-countdown chime, then tick every second while running
  if (remaining > 0) playCountdownTick(remaining <= 10);
  const handle = setInterval(() => {
    remaining -= 1;
    onTick(remaining);
    // Hypothesis A fix: tick for full countdown, not only last 60s
    if (remaining > 0) {
      playCountdownTick(remaining <= 10);
    }
    if (remaining <= 0) {
      clearInterval(handle);
      onExpire();
    }
  }, 1000);
  return handle;
}

function formatClock(totalSeconds) {
  const s = Math.max(0, totalSeconds);
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  const pad = n => String(n).padStart(2, '0');
  return h > 0 ? `${pad(h)}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
}

window.API_BASE = API_BASE;
window.buildApiUrl = buildApiUrl;
window.appUrl = appUrl;
window.isLocalDevHost = isLocalDevHost;
window.api = api;
window.initTabs = initTabs;
window.initRoleToggle = initRoleToggle;
window.updateLoginFields = updateLoginFields;
window.showFieldError = showFieldError;
window.clearFieldError = clearFieldError;
window.openModal = openModal;
window.closeModal = closeModal;
window.initModals = initModals;
window.initAppHeader = initAppHeader;
window.initPanelNav = initPanelNav;
window.updateBreadcrumb = updateBreadcrumb;
window.avatarUrl = avatarUrl;
window.renderAvatarInto = renderAvatarInto;
window.avatarInitials = avatarInitials;
window.startExamTimer = startExamTimer;
window.formatClock = formatClock;
window.ensureTickAudio = ensureTickAudio;
window.playCountdownTick = playCountdownTick;

document.addEventListener('DOMContentLoaded', () => {
  initModals();
  // #region agent log
  const closeBtn = document.querySelector('.modal-close');
  fetch('http://127.0.0.1:7578/ingest/2dc8a71c-1580-4782-8139-68ca44edb8d0',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'20c118'},body:JSON.stringify({sessionId:'20c118',runId:'close-red',hypothesisId:'A',location:'main.js:DOMContentLoaded',message:'close button color audit',data:{modalCloseCount:document.querySelectorAll('.modal-close').length,dangerCloseCount:document.querySelectorAll('.btn-danger').length,modalCloseColor:closeBtn?getComputedStyle(closeBtn).color:null},timestamp:Date.now()})}).catch(()=>{});
  // #endregion
});
