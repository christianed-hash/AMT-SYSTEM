const RAW = [
    { cat: "Hand Tools", name: "Screwdrivers", total: 10 },
    { cat: "Hand Tools", name: "Rubber mallets", total: 7 },
    { cat: "Hand Tools", name: "Ball-pein hammers", total: 6 },
    { cat: "Hand Tools", name: "Claw hammers", total: 6 },
    { cat: "Hand Tools", name: "Sledgehammers (Creston 2lb)", total: 2 },
    { cat: "Hand Tools", name: "Steel pin hammers", total: 3 },
    { cat: "Hand Tools", name: "Utility knives", total: 7 },
    { cat: "Hand Tools", name: "Measuring tapes", total: 7 },
    { cat: "Hand Tools", name: "Spirit levels", total: 3 },
    { cat: "Hand Tools", name: "Square ruler", total: 1 },
    { cat: "Hand Tools", name: "Pliers (cutter, long nose, multi-tester)", total: 9 },
    { cat: "Hand Tools", name: "Socket wrench sets", total: 4 },
    { cat: "Hand Tools", name: "Combination wrenches", total: 18 },
    { cat: "Hand Tools", name: "Open-end wrenches", total: 12 },
    { cat: "Hand Tools", name: "Adjustable wrenches", total: 6 },
    { cat: "Hand Tools", name: "Allen keys (hex key set)", total: 1 },
    { cat: "Hand Tools", name: "Tin snips / aviation shears", total: 4 },
    { cat: "Hand Tools", name: "C-clamps", total: 4 },
    { cat: "Pneumatic & Power Tools", name: "Pneumatic drills (Sumake, Neiko, Bosch)", total: 5 },
    { cat: "Pneumatic & Power Tools", name: "Angle die grinders", total: 3 },
    { cat: "Pneumatic & Power Tools", name: "Pneumatic riveting guns", total: 3 },
    { cat: "Pneumatic & Power Tools", name: "Bosch angle grinders (GWS 6-100)", total: 6 },
    { cat: "Precision & Specialty Tools", name: "Center punches", total: 6 },
    { cat: "Precision & Specialty Tools", name: "Automatic center punches", total: 2 },
    { cat: "Precision & Specialty Tools", name: "Pin punchers", total: 5 },
    { cat: "Precision & Specialty Tools", name: "Chisels", total: 6 },
    { cat: "Precision & Specialty Tools", name: "Scriber", total: 4 },
    { cat: "Precision & Specialty Tools", name: "Deburring countersinks", total: 3 },
    { cat: "Precision & Specialty Tools", name: "Drill stops", total: 4 },
    { cat: "Precision & Specialty Tools", name: "Side grips", total: 3 },
    { cat: "Precision & Specialty Tools", name: "Clamps and pullers", total: 6 },
    { cat: "Accessories & Misc", name: "Toolboxes", total: 6 },
    { cat: "Accessories & Misc", name: "Coiled hoses / cables", total: 4 },
    { cat: "Accessories & Misc", name: "Paint brushes", total: 4 },
    { cat: "Uncountable", name: "Rivets (various sizes)", total: null, misc: true },
    { cat: "Uncountable", name: "Drill bits (assorted sets)", total: null, misc: true },
    { cat: "Uncountable", name: "Lubricants, oils, spray cans", total: null, misc: true },
];

const IMAGE_MAP = {
    "Screwdrivers": "tools/screwdriver.jpg",
    "Rubber mallets": "tools/rubber mullets.jpg",
    "Ball-pein hammers": "tools/ball-pein hammer.jpg",
    "Claw hammers": "tools/claw hammer.jpg",
    "Sledgehammers (Creston 2lb)": "tools/sledgehammers.jpg",
    "Steel pin hammers": "tools/steel pin hammer.jpg",
    "Utility knives": "tools/utility knives.jpg",
    "Measuring tapes": "tools/fiberglass long tape.jpg",
    "Spirit levels": "tools/spirit level.jpg",
    "Square ruler": "tools/square ruler.jpg",
    "Pliers (cutter, long nose, multi-tester)": "tools/pliers cutter.jpg",
    "Socket wrench sets": "tools/ratchet set.jpg",
    "Combination wrenches": "tools/combination wrenches.jpg",
    "Open-end wrenches": "tools/open-end wrenches.jpg",
    "Adjustable wrenches": "tools/adjustable wrenches.jpg",
    "Allen keys (hex key set)": "tools/allen keys set.jpg",
    "Tin snips / aviation shears": "tools/tin snips.jpg",
    "C-clamps": "tools/c-clamps.jpg",
    "Pneumatic drills (Sumake, Neiko, Bosch)": "tools/pneumatic drills.png",
    "Angle die grinders": "tools/angle die grinders.png",
    "Pneumatic riveting guns": "tools/pneumatic riveting gun.png",
    "Bosch angle grinders (GWS 6-100)": "tools/GWS 6-100.png",
    "Center punches": "tools/center punches.png",
    "Automatic center punches": "tools/auto center.png",
    "Pin punchers": "tools/pin punchers.png",
    "Chisels": "tools/chisel.png",
    "Scriber": "tools/scriber.jpg",
    "Deburring countersinks": "tools/deburring countersinks.png",
    "Drill stops": "tools/drill stops.png",
    "Side grips": "tools/side grips.png",
    "Clamps and pullers": "tools/clamps and pullers.png",
    "Toolboxes": "tools/toolboxes(orange).jpg",
    "Coiled hoses / cables": "tools/coiled cables.jpg",
    "Paint brushes": "tools/paint brush.jpg",
    "Rivets (various sizes)": "tools/rivet.png",
    "Drill bits (assorted sets)": "tools/drilled bits.jpg",
    "Lubricants, oils, spray cans": "tools/lubricants.jpg",
};

const API = 'api';
let tools = RAW.map((t, i) => ({ ...t, id: i, borrowed: 0 }));
let filterMode = 'all';

// Sync borrowed counts from PHP API
async function syncFromAPI() {
    try {
        const res  = await fetch(`${API}/records.php`, { credentials: 'include' });
        if (!res.ok) return;
        const data = await res.json();
        const records = data.records || [];
        tools.forEach(t => t.borrowed = 0);
        records.forEach(r => {
            if (!r.returned) {
                const t = tools.find(x => x.name === r.toolName);
                if (t && !t.misc) t.borrowed += (parseInt(r.quantity) || 1);
            }
        });
    } catch (e) {
        // API failed — keep counts at 0, still show tools
    }
}

function getStatus(t) {
    if (t.misc) return 'misc';
    const avail = t.total - t.borrowed;
    if (avail === 0) return 'out';
    if (avail < 3) return 'low';
    return 'available';
}

function setFilter(mode, btn) {
    filterMode = mode;
    document.querySelectorAll('.inv-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    render();
}

function createCardHtml(t) {
    const s    = getStatus(t);
    const avail = t.misc ? null : t.total - t.borrowed;

    const badge = t.misc
        ? `<span class="inv-badge inv-badge--misc">In Stock</span>`
        : s === 'out'
            ? `<span class="inv-badge inv-badge--out">Out of Stock</span>`
            : s === 'low'
                ? `<span class="inv-badge inv-badge--low">${avail} left — Low</span>`
                : `<span class="inv-badge inv-badge--available">${avail} Available</span>`;

    const imgSrc  = IMAGE_MAP[t.name];
    const imgHtml = imgSrc
        ? `<div class="inv-card-img-wrap"><img src="${imgSrc}" alt="${t.name}" class="inv-card-img" loading="lazy"></div>`
        : `<div class="inv-card-img-wrap inv-card-img-placeholder">🔧</div>`;

    return `
        <div class="inv-card${s === 'out' ? ' inv-card--out' : ''}">
            ${imgHtml}
            <div class="inv-card-body">
                <div class="inv-card-name">${t.name}</div>
                ${!t.misc
                    ? `<div class="inv-card-meta">Total: ${t.total} &nbsp;|&nbsp; Borrowed: ${t.borrowed}</div>`
                    : `<div class="inv-card-meta">Quantity not tracked</div>`}
                ${badge}
            </div>
        </div>`;
}

async function render() {
    await syncFromAPI();

    const q = document.getElementById('search').value.toLowerCase();
    const cats = {};
    const matched = [];
    let totalTypes = 0, totalUnits = 0, totalBorrowed = 0, totalAvail = 0;

    tools.forEach(t => {
        const s = getStatus(t);
        if (filterMode !== 'all' && s !== filterMode) return;
        if (q && !t.name.toLowerCase().includes(q) && !t.cat.toLowerCase().includes(q)) return;
        totalTypes++;
        if (!t.misc) {
            totalUnits   += t.total;
            totalBorrowed += t.borrowed;
            totalAvail   += (t.total - t.borrowed);
        }
        if (!cats[t.cat]) cats[t.cat] = [];
        cats[t.cat].push(t);
        matched.push(t);
    });

    document.getElementById('s-types').textContent    = totalTypes;
    document.getElementById('s-units').textContent    = totalUnits;
    document.getElementById('s-borrowed').textContent = totalBorrowed;
    document.getElementById('s-avail').textContent    = totalAvail;

    const content = document.getElementById('content');

    if (!matched.length) {
        content.innerHTML = '<div class="inv-no-results">No tools match your search.</div>';
        return;
    }

    if (q) {
        content.innerHTML = `
            <div class="inv-section-title">Search results</div>
            <div class="inv-grid">${matched.map(createCardHtml).join('')}</div>`;
        return;
    }

    content.innerHTML = Object.keys(cats).map(cat => `
        <div class="inv-section-title">${cat}</div>
        <div class="inv-grid">${cats[cat].map(createCardHtml).join('')}</div>
    `).join('');
}

// Start — render tools immediately, sync API in background
render();
