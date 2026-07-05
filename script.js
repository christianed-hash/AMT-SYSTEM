// ── API base URL ─────────────────────────────────────────────
const API = 'api';

// ── Tool list ────────────────────────────────────────────────
const TOOLS = [
  "Screwdrivers", "Rubber mallets", "Ball-pein hammers", "Claw hammers",
  "Sledgehammers (Creston 2lb)", "Steel pin hammers", "Files", "Utility knives",
  "Measuring tapes", "Spirit levels", "Square ruler",
  "Pliers (cutter, long nose, multi-tester)", "Socket wrench sets",
  "Combination wrenches", "Open-end wrenches", "Adjustable wrenches",
  "Allen keys (hex key set)", "Tin snips / aviation shears", "C-clamps",
  "Pneumatic drills (Sumake, Neiko, Bosch)", "Angle die grinders",
  "Pneumatic riveting guns", "Bosch angle grinders (GWS 6-100)",
  "Center punches", "Automatic center punches", "Pin punchers", "Chisels",
  "Scribes", "Deburring countersinks", "Drill stops", "Side grips",
  "Clamps and pullers", "Toolboxes", "Coiled hoses / cables", "Paint brushes",
  "Measuring instruments (levels, calipers)", "Solar lanterns & chargers (Sierra LED)",
  "Rivets (various sizes)", "Drill bits (assorted sets)",
  "Hydraulic / pneumatic yellow cylinders", "Lubricants, oils, spray cans",
];

// ── Year Level Filter State ───────────────────────────────────
let activeReqYear = 'all';
let activeRecYear = 'all';
let allRequests   = [];

window.setReqYear = function (year, btn) {
  activeReqYear = year;
  document.querySelectorAll('#reqYearTabs .yr-tab').forEach(b => b.classList.remove('yr-tab--active'));
  btn.classList.add('yr-tab--active');
  renderRequestsTable();
};

window.setRecYear = function (year, btn) {
  activeRecYear = year;
  document.querySelectorAll('#recYearTabs .yr-tab').forEach(b => b.classList.remove('yr-tab--active'));
  btn.classList.add('yr-tab--active');
  render();
};

// ── Clock ─────────────────────────────────────────────────────
function updateClock() {
  const el = document.getElementById('clock');
  if (el) el.innerText = new Date().toLocaleString();
}
setInterval(updateClock, 1000);
updateClock();
async function checkAuth() {
  try {
    const res  = await fetch(`${API}/me.php`, { credentials: 'include' });
    const data = await res.json();
    if (!data.ok) { window.location.href = 'login.html'; return null; }
    return data;
  } catch {
    window.location.href = 'login.html';
    return null;
  }
}

window.logout = async function () {
  await fetch(`${API}/logout.php`, { method: 'POST', credentials: 'include' });
  window.location.href = 'login.html';
};

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkAuth();
  if (!user) return;

  // Show username
  const el = document.getElementById('currentUser');
  if (el) el.textContent = user.username || user.email;

  // Populate tool dropdown
  const toolSelect = document.getElementById('toolName');
  TOOLS.forEach(name => {
    const opt = document.createElement('option');
    opt.value = opt.textContent = name;
    toolSelect.appendChild(opt);
  });

  // Set default date/time
  setDefaultDate();

  // Show current SY
  const syRes  = await fetch(`${API}/schoolyear.php`, { credentials: 'include' });
  const syData = await syRes.json();
  const badge  = document.getElementById('syBadge');
  if (badge && syData.sy) badge.textContent = 'S.Y. ' + syData.sy;

  // Load records
  await render();
  // Load pending requests
  await loadRequests();

  // Form submit
  document.getElementById('borrowForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const toolSelect = document.getElementById('toolName');
    const month = new Date().getMonth() + 1;
    const autoSemester = month >= 8 ? '1st Semester' : '2nd Semester';
    await fetch(`${API}/records.php`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        studentName: document.getElementById('studentName').value.trim(),
        studentId:   document.getElementById('studentId').value.trim(),
        yearLevel:   document.getElementById('yearLevel').value,
        semester:    autoSemester,
        toolName:    toolSelect.value,
        dateOut:     document.getElementById('dateOut').value,
      })
    });

    e.target.reset();
    setDefaultDate();
    btn.disabled = false;
    btn.textContent = 'Borrow Tool';
    await render();
  });

  // Search / filter
  document.getElementById('search').addEventListener('input', render);
  document.getElementById('filter').addEventListener('change', render);
});

function setDefaultDate() {
  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  document.getElementById('dateOut').value = now.toISOString().slice(0, 16);
}

// ── Render table ─────────────────────────────────────────────
async function render() {
  const res     = await fetch(`${API}/records.php`, { credentials: 'include' });
  const data    = await res.json();
  const records = data.records || [];

  const borrowed = records.filter(r => !r.returned).length;
  const returned = records.filter(r =>  r.returned).length;
  document.getElementById('totalTools').textContent    = records.length;
  document.getElementById('borrowedCount').textContent = borrowed;
  document.getElementById('returnedCount').textContent = returned;

  const q = (document.getElementById('search').value || '').toLowerCase();
  const f = document.getElementById('filter').value;

  const filtered = records.filter(r => {
    const matchSearch =
      r.studentName.toLowerCase().includes(q) ||
      r.toolName.toLowerCase().includes(q) ||
      (r.section || '').toLowerCase().includes(q);
    const matchFilter =
      f === 'all' ||
      (f === 'borrowed' && !r.returned) ||
      (f === 'returned' &&  r.returned);
    const matchYear =
      activeRecYear === 'all' || (r.yearLevel || '') === activeRecYear;
    return matchSearch && matchFilter && matchYear;
  });

  const tbody = document.getElementById('toolList');
  tbody.innerHTML = '';

  if (!filtered.length) {
    tbody.innerHTML = `<tr><td colspan="8" style="color:#94a3b8;padding:24px">No records found.</td></tr>`;
    return;
  }

  filtered.forEach(r => {
    const now      = new Date();
    const due      = r.dueDate ? new Date(r.dueDate) : null;
    const overdue  = due && !r.returned && due < now;
    const row = document.createElement('tr');
    if (overdue) row.style.background = '#fff5f5';
    row.innerHTML = `
      <td>${r.studentName}</td>
      <td>${r.studentId}</td>
      <td>${r.yearLevel || '—'}</td>
      <td>${r.toolName}</td>
      <td>${new Date(r.dateOut).toLocaleString()}</td>
      <td>${due
        ? `<span style="color:${overdue ? '#dc2626' : '#16a34a'};font-weight:600">
             ${overdue ? '⚠️ ' : ''}${due.toLocaleString()}
           </span>`
        : '<span style="color:#94a3b8">—</span>'}</td>
      <td>
        <span class="${r.returned ? 'returned-status' : 'borrowed-status'}">
          ${r.returned ? 'Returned' : 'Borrowed'}
        </span>
      </td>
      <td>
        ${!r.returned
          ? `<button class="return-btn" onclick="returnTool(${r.id})">Return</button>`
          : ''}
      </td>
    `;
    tbody.appendChild(row);
  });
}

// ── Return ───────────────────────────────────────────────────
window.returnTool = async function (id) {
  await fetch(`${API}/return.php`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });
  await render();
};

// ── New School Year ──────────────────────────────────────────
window.newSchoolYear = async function () {
  const syRes  = await fetch(`${API}/schoolyear.php`, { credentials: 'include' });
  const syData = await syRes.json();
  const currentSY = syData.sy;

  const ok = confirm(
    `Archive all records under S.Y. ${currentSY} and start a new school year?\n\nArchived records can be viewed in the Archives page.`
  );
  if (!ok) return;

  const suggested = parseInt(currentSY.split('-')[1]);
  const newSY = prompt(`Enter the new school year:`, `${suggested}-${suggested + 1}`);
  if (!newSY || !newSY.includes('-')) {
    alert('Invalid format. Use YYYY-YYYY.');
    return;
  }

  const res = await fetch(`${API}/schoolyear.php`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ new_sy: newSY.trim() })
  });
  const data = await res.json();
  if (data.ok) {
    document.getElementById('syBadge').textContent = 'S.Y. ' + newSY.trim();
    alert(`Records archived under S.Y. ${currentSY}.\nNow starting S.Y. ${newSY.trim()}.`);
    await render();
  }
};

// ── Pending Requests ─────────────────────────────────────────
async function loadRequests() {
  const container = document.getElementById('requestsTable');
  if (!container) return;

  try {
    const res  = await fetch(`${API}/requests.php?status=pending`, { credentials: 'include' });
    const data = await res.json();
    allRequests = data.requests || [];

    const badge = document.getElementById('pendingCount');
    if (badge) badge.textContent = allRequests.length > 0 ? `(${allRequests.length})` : '';

    renderRequestsTable();
  } catch {
    container.innerHTML = '<p style="color:#ef4444;font-size:13px">Could not load requests.</p>';
  }
}

function renderRequestsTable() {
  const container = document.getElementById('requestsTable');
  if (!container) return;

  const reqs = activeReqYear === 'all'
    ? allRequests
    : allRequests.filter(r => (r.year_level || '') === activeReqYear);

  if (!reqs.length) {
    container.innerHTML = '<p style="color:#94a3b8;font-size:13px;padding:8px 0">No pending requests.</p>';
    return;
  }

  // ── Group by student_id ──────────────────────────────────────
  const groups = {};
  reqs.forEach(r => {
    const key = r.student_id;
    if (!groups[key]) groups[key] = { info: r, items: [] };
    groups[key].items.push(r);
  });

  const groupList = Object.values(groups);

  let rows = '';
  groupList.forEach(g => {
    const info    = g.info;
    const groupId = 'grp_' + info.student_id.replace(/[^a-z0-9]/gi, '_');
    const ids     = g.items.map(i => i.id);

    const toolRows = g.items.map(item => `
      <tr style="border-bottom:1px solid #f1f5f9">
        <td style="padding:5px 8px;font-size:12px;font-weight:600;color:#0f172a;white-space:nowrap">🔧 ${item.tool_name}</td>
        <td style="padding:5px 8px;font-size:12px;text-align:center">
          <span style="background:#f1f5f9;padding:1px 8px;border-radius:5px;font-weight:600">${item.quantity || 1}</span>
        </td>
        <td style="padding:5px 8px">
          <input type="number" id="aqty-${item.id}" value="${item.quantity || 1}" min="1"
            style="width:55px;padding:3px 6px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;font-weight:600;text-align:center"
            title="Qty to give">
        </td>
        <td style="padding:5px 8px">
          <input type="datetime-local" id="due-${item.id}"
            style="padding:3px 6px;border:1px solid #e2e8f0;border-radius:6px;font-size:11px;width:160px"
            title="Return deadline for this tool">
        </td>
      </tr>
    `).join('');

    rows += `
      <tr id="${groupId}" style="border-bottom:2px solid #e2e8f0;vertical-align:top">
        <td style="padding:12px;font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap">
          ${info.student_name}<br>
          <span style="font-size:11px;color:#94a3b8;font-weight:400">${info.student_id}</span>
        </td>
        <td style="padding:12px;font-size:13px;color:#475569;white-space:nowrap">${info.student_email}</td>
        <td style="padding:12px;font-size:13px;color:#475569;white-space:nowrap">${info.year_level || '—'}</td>
        <td style="padding:12px;font-size:13px;color:#475569;white-space:nowrap">${info.semester || '1st Semester'}</td>
        <td style="padding:8px 12px">
          <table style="border-collapse:collapse;width:100%">
            <thead>
              <tr style="border-bottom:1px solid #e2e8f0">
                <th style="padding:3px 8px;font-size:10px;color:#94a3b8;text-align:left;font-weight:600">Tool</th>
                <th style="padding:3px 8px;font-size:10px;color:#94a3b8;text-align:center;font-weight:600">Requested</th>
                <th style="padding:3px 8px;font-size:10px;color:#94a3b8;text-align:left;font-weight:600">Qty to Give</th>
                <th style="padding:3px 8px;font-size:10px;color:#94a3b8;text-align:left;font-weight:600">Return Deadline</th>
              </tr>
            </thead>
            <tbody>${toolRows}</tbody>
          </table>
        </td>
        <td style="padding:12px;white-space:nowrap;vertical-align:middle">
          <button onclick="confirmAccept('${groupId}', ${JSON.stringify(ids)})"
            style="background:#16a34a;color:white;border:none;padding:6px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;margin-bottom:5px;display:block;width:100%">
            ✅ Accept
          </button>
          <button onclick="handleGroupRequest('${groupId}', ${JSON.stringify(ids)}, 'rejected')"
            style="background:#dc2626;color:white;border:none;padding:6px 14px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:block;width:100%">
            ❌ Reject
          </button>
        </td>
      </tr>
    `;
  });

  container.innerHTML = `
    <div style="overflow-x:auto;width:100%">
    <table style="width:100%;border-collapse:collapse;min-width:700px">
      <thead>
        <tr style="background:#f8fafc">
          <th style="padding:10px 12px;text-align:left;font-size:12px;color:#64748b;border-bottom:2px solid #e2e8f0;font-weight:600">Name</th>
          <th style="padding:10px 12px;text-align:left;font-size:12px;color:#64748b;border-bottom:2px solid #e2e8f0;font-weight:600">Email</th>
          <th style="padding:10px 12px;text-align:left;font-size:12px;color:#64748b;border-bottom:2px solid #e2e8f0;font-weight:600">Year Level</th>
          <th style="padding:10px 12px;text-align:left;font-size:12px;color:#64748b;border-bottom:2px solid #e2e8f0;font-weight:600">Semester</th>
          <th style="padding:10px 12px;text-align:left;font-size:12px;color:#64748b;border-bottom:2px solid #e2e8f0;font-weight:600">Tools Requested</th>
          <th style="padding:10px 12px;text-align:left;font-size:12px;color:#64748b;border-bottom:2px solid #e2e8f0;font-weight:600">Action</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
    </div>`;
}

window.confirmAccept = async function (groupId, ids) {
  const missing = ids.find(id => !document.getElementById(`due-${id}`)?.value);
  if (missing) {
    alert('Please set a return deadline for every tool before accepting.');
    return;
  }

  // Use semester from the stored request data (set by student)
  const firstReq = allRequests.find(r => ids.includes(r.id));
  const semester = firstReq?.semester || '1st Semester';

  const card = document.getElementById(groupId);
  if (card) { card.style.opacity = '0.5'; card.style.pointerEvents = 'none'; }

  await Promise.all(ids.map((id, index) => {
    const approved_qty = Math.max(1, parseInt(document.getElementById(`aqty-${id}`)?.value || '1'));
    const due_date     = document.getElementById(`due-${id}`)?.value || null;
    const isLastItem   = index === ids.length - 1;
    return fetch(`${API}/requests.php`, {
      method: 'PATCH',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, status: 'approved', due_date, semester, approved_qty, batchIds: ids, isLastItem })
    });
  }));

  await loadRequests();
  await render();
};

window.handleGroupRequest = async function (groupId, ids, status) {
  const card = document.getElementById(groupId);
  if (card) { card.style.opacity = '0.5'; card.style.pointerEvents = 'none'; }

  await Promise.all(ids.map((id, index) =>
    fetch(`${API}/requests.php`, {
      method: 'PATCH',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, status, due_date: null, semester: '1st Semester', approved_qty: 1, batchIds: ids, isLastItem: index === ids.length - 1 })
    })
  ));

  await loadRequests();
};

// Auto-refresh requests every 30 seconds
setInterval(loadRequests, 30000);
