<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/lib/helpers.php';
$loggedIn = !empty($_SESSION['is_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - <?= htmlspecialchars(CHARITY_NAME) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --primary:#2ecc71; --primary-dark:#27ae60;
    --bg:#f4f6f8; --card:#fff; --text:#1f2937;
    --muted:#6b7280; --border:#e5e7eb; --danger:#e74c3c;
    --shadow: 0 4px 12px rgba(0,0,0,.06);
    --radius: 10px;
}
*{box-sizing:border-box}
body{margin:0;font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);}
a{color:var(--primary-dark);text-decoration:none}
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-card{background:var(--card);padding:28px 26px;border-radius:var(--radius);box-shadow:var(--shadow);max-width:340px;width:100%;border:1px solid var(--border)}
.login-card h1{margin:0 0 6px;font-size:1.2rem;font-weight:600;display:flex;align-items:center;gap:8px}
.login-card p{color:var(--muted);margin:0 0 18px;font-size:.85rem}
.shell{display:grid;grid-template-columns:215px 1fr;min-height:100vh}
.sidebar{background:#1f2937;color:#fff;padding:18px 0;transition:transform .3s ease}
.sidebar h2{padding:0 18px;font-size:.98rem;margin:0 0 14px;display:flex;align-items:center;gap:7px}
.sidebar h2 i{color:var(--primary);font-size:.95rem}
.sidebar nav button{
    width:100%;text-align:left;padding:9px 18px;
    background:none;border:none;color:#cbd5e1;cursor:pointer;font-family:inherit;
    font-size:.85rem;display:flex;gap:9px;align-items:center;
    transition:.15s;
}
.sidebar nav button i{width:16px;font-size:.8rem;opacity:.85}
.sidebar nav button:hover{background:rgba(0,0,0,.25);color:#fff}
.sidebar nav button.active{background:var(--primary);color:#fff}
.sidebar .logout{padding:8px 18px;margin-top:22px;border-top:1px solid #374151;}
.sidebar .logout button{
    background:none;border:1px solid #4b5563;color:#cbd5e1;padding:6px 12px;
    border-radius:6px;cursor:pointer;font-family:inherit;font-size:.82rem;
}
.sidebar .logout button:hover{background:var(--danger);color:#fff;border-color:var(--danger)}
.main{padding:22px 28px;overflow:auto}

/* Mobile: hamburger toggle + frosted slide-in sidebar */
.admin-hamburger{
    display:none;
    position:fixed;top:14px;left:14px;
    width:42px;height:42px;
    background:#1f2937;color:#fff;border:none;border-radius:10px;
    font-size:1.1rem;cursor:pointer;z-index:1102;
    box-shadow:var(--shadow);
    align-items:center;justify-content:center;
    transition:.2s;
}
.admin-hamburger:hover{background:var(--primary)}
.sidebar-backdrop{
    display:none;
    position:fixed;inset:0;
    background:rgba(0,0,0,.4);
    backdrop-filter:blur(3px);
    z-index:1100;
    opacity:0;
    transition:opacity .25s ease;
}
.sidebar-backdrop.active{opacity:1}

@media(max-width:780px){
    .shell{grid-template-columns:1fr}
    .admin-hamburger{display:inline-flex}
    .sidebar-backdrop.active{display:block}
    .sidebar{
        position:fixed;
        top:0;left:0;
        width:78%;max-width:260px;
        height:100vh;
        z-index:1101;
        background:rgba(31,41,55,.78);
        backdrop-filter:blur(28px) saturate(160%);
        -webkit-backdrop-filter:blur(28px) saturate(160%);
        border-right:1px solid rgba(255,255,255,.06);
        transform:translateX(-105%);
        box-shadow:6px 0 24px rgba(0,0,0,.25);
        overflow-y:auto;
        padding:18px 0;
    }
    .sidebar.active{transform:translateX(0)}
    .sidebar h2{font-size:1rem;padding:0 18px;margin-bottom:14px}
    .sidebar nav button{padding:11px 18px;font-size:.9rem}
    .sidebar .logout{padding:10px 18px;margin-top:18px}
    .main{padding:64px 16px 24px}
    .toolbar{flex-direction:column;align-items:stretch}
    .toolbar h1{font-size:1.15rem}
    .toolbar > div{display:flex;gap:8px;flex-wrap:wrap}
    .toolbar .btn{flex:1;justify-content:center}
    .row2,.row3{grid-template-columns:1fr}
    .card{padding:16px}
}
.toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:10px;flex-wrap:wrap}
.toolbar h1{margin:0;font-size:1.15rem;font-weight:600}
.btn{
    background:var(--primary);color:#fff;border:none;padding:8px 14px;border-radius:6px;
    font-family:inherit;font-weight:600;cursor:pointer;font-size:.85rem;
    display:inline-flex;align-items:center;gap:6px;
    transition:.15s;
}
.btn:hover{background:var(--primary-dark)}
.btn[disabled]{opacity:.6;cursor:not-allowed}
.btn-ghost{background:#fff;color:var(--text);border:1px solid var(--border)}
.btn-ghost:hover{background:#f3f4f6}
.btn-danger{background:var(--danger)}
.card{background:var(--card);padding:16px 18px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:14px;border:1px solid var(--border)}
.card h3{margin:0 0 10px;font-size:.95rem;color:#111;font-weight:600;display:flex;align-items:center;gap:6px}
.card h3 i{color:var(--primary);font-size:.9rem}
.field{margin-bottom:11px}
.field label{display:block;font-size:.72rem;color:var(--muted);margin-bottom:3px;font-weight:500;text-transform:uppercase;letter-spacing:.4px}
.field input, .field textarea, .field select{
    width:100%;padding:8px 11px;border:1px solid var(--border);border-radius:6px;
    font-family:inherit;font-size:.85rem;background:#fff;color:var(--text);
}
.field input:focus,.field textarea:focus,.field select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(46,204,113,.15)}
.field textarea{min-height:62px;resize:vertical}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:11px}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:11px}
@media(max-width:640px){.row2,.row3{grid-template-columns:1fr;gap:8px}}
.list-item{
    border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-bottom:8px;
    background:#fafafa;position:relative;
}
.list-item-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.list-item-head strong{font-size:.85rem;color:#111}
.list-item-actions{display:flex;gap:4px}
.icon-btn{
    background:#fff;border:1px solid var(--border);width:26px;height:26px;
    border-radius:5px;cursor:pointer;color:var(--muted);display:inline-flex;
    align-items:center;justify-content:center;font-size:.72rem;transition:.15s;
}
.icon-btn:hover{color:var(--primary);border-color:var(--primary)}
.icon-btn.danger:hover{color:#fff;background:var(--danger);border-color:var(--danger)}
.tabs-content{display:none}
.tabs-content.active{display:block}
.notice{padding:9px 12px;border-radius:6px;background:#ecfdf5;color:#065f46;margin-bottom:10px;border-left:3px solid var(--primary);font-size:.85rem}
.notice.error{background:#fef2f2;color:#991b1b;border-left-color:var(--danger)}
table{width:100%;border-collapse:collapse;font-size:.82rem}
table th, table td{padding:8px 10px;text-align:left;border-bottom:1px solid var(--border)}
table th{background:#f9fafb;font-weight:600;font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px}
.status-badge{padding:2px 7px;border-radius:99px;font-size:.68rem;font-weight:600}
.status-badge.s-awaiting_approval{background:#fef3c7;color:#92400e}
.status-badge.s-pending,.status-badge.s-manual_pending{background:#dbeafe;color:#1e40af}
.status-badge.s-airtel_pledged,.status-badge.s-bank_pledged{background:#fef3c7;color:#854d0e}
.status-badge.s-failed{background:#fee2e2;color:#991b1b}
.status-badge.s-success{background:#d1fae5;color:#065f46}
.empty{padding:24px;text-align:center;color:var(--muted);font-size:.85rem}
.help{font-size:.75rem;color:var(--muted);margin-top:4px}
.toast-host{position:fixed;top:14px;right:14px;z-index:9999;display:flex;flex-direction:column;gap:6px}
.toast{background:#fff;border-left:3px solid var(--primary);padding:8px 14px;border-radius:5px;box-shadow:var(--shadow);min-width:220px;font-size:.82rem}
.toast.error{border-left-color:var(--danger)}

/* Image upload widget (compact) */
.image-field{position:relative}
.image-field .image-row{display:flex;gap:10px;align-items:flex-start}
.image-field .preview{
    width:72px;height:72px;border-radius:7px;
    background:#f3f4f6 center/cover no-repeat;
    border:1px solid var(--border);flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    color:var(--muted);font-size:.62rem;text-align:center;
}
.image-field .preview.empty{color:var(--muted)}
.image-field .image-controls{flex:1;display:flex;flex-direction:column;gap:6px}
.image-field input[type="file"]{display:none}
.upload-btn{
    background:#fff;color:var(--text);border:1px dashed var(--border);
    padding:6px 11px;border-radius:5px;cursor:pointer;font-family:inherit;
    font-size:.78rem;font-weight:500;display:inline-flex;align-items:center;
    gap:5px;align-self:flex-start;transition:.15s;
}
.upload-btn:hover{border-color:var(--primary);color:var(--primary)}
.upload-btn.uploading{opacity:.6;cursor:wait}
</style>
</head>
<body>

<?php if (!$loggedIn): ?>
<div class="login-wrap">
    <form class="login-card" id="loginForm" novalidate>
        <h1><i class="fas fa-lock" style="color:var(--primary)"></i> Admin Panel</h1>
        <p>Enter the admin password to manage site content.</p>
        <div class="field">
            <label for="pw">Password</label>
            <input type="password" id="pw" name="password" required autofocus>
        </div>
        <button type="submit" class="btn" style="width:100%;justify-content:center">Sign in</button>
        <p class="help" style="margin-top:18px;text-align:center">
            Forgot password? Edit <code>backend/lib/config.php</code> to change it.
        </p>
    </form>
</div>
<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const pw = document.getElementById('pw').value;
    const res = await fetch('api/admin_login.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({password: pw})
    });
    const body = await res.json().catch(()=>({}));
    if (res.ok && body.success) location.reload();
    else alert(body.message || 'Login failed');
});
</script>

<?php else: ?>
<button type="button" class="admin-hamburger" id="adminHamburger" aria-label="Open menu">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="shell">
    <aside class="sidebar" id="adminSidebar">
        <h2><i class="fas fa-heart"></i> Tattu Care Admin</h2>
        <nav id="adminNav">
            <button data-tab="org"        class="active"><i class="fas fa-building"></i> Organization</button>
            <button data-tab="hero"><i class="fas fa-image"></i> Hero & About</button>
            <button data-tab="stats"><i class="fas fa-chart-bar"></i> Stats</button>
            <button data-tab="programs"><i class="fas fa-hand-holding-heart"></i> Programs</button>
            <button data-tab="impact"><i class="fas fa-quote-right"></i> Impact Stories</button>
            <button data-tab="team"><i class="fas fa-users"></i> Team</button>
            <button data-tab="events"><i class="fas fa-calendar"></i> Events</button>
            <button data-tab="donation"><i class="fas fa-donate"></i> Donation</button>
            <button data-tab="trust"><i class="fas fa-shield-alt"></i> Trust & Legal</button>
            <button data-tab="submissions"><i class="fas fa-inbox"></i> Submissions</button>
        </nav>
        <div class="logout">
            <button id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Log out</button>
        </div>
    </aside>

    <main class="main">
        <div class="toolbar">
            <h1 id="tabTitle">Organization</h1>
            <div>
                <a href="../index.html" target="_blank" class="btn btn-ghost"><i class="fas fa-external-link-alt"></i> View site</a>
                <button id="saveBtn" class="btn"><i class="fas fa-save"></i> Save changes</button>
            </div>
        </div>
        <div id="msg"></div>

        <section class="tabs-content active" data-tab="org">
            <div class="card">
                <h3>Basic Information</h3>
                <div class="row2">
                    <div class="field"><label>Organization Name</label><input data-bind="organization.name"></div>
                    <div class="field"><label>Tagline</label><input data-bind="organization.tagline"></div>
                </div>
                <div class="field"><label>Short Description</label><textarea data-bind="organization.shortDescription"></textarea></div>
                <div class="row3">
                    <div class="field"><label>Registration #</label><input data-bind="organization.registrationNumber"></div>
                    <div class="field"><label>Founded (year)</label><input data-bind="organization.founded"></div>
                    <div class="field"><label>Phone</label><input data-bind="organization.phone"></div>
                </div>
                <div class="row2">
                    <div class="field"><label>Email</label><input type="email" data-bind="organization.email"></div>
                    <div class="field"><label>Address</label><input data-bind="organization.address"></div>
                </div>
            </div>
            <div class="card">
                <h3>Social Media (full URLs)</h3>
                <div class="row2">
                    <div class="field"><label>Facebook</label><input data-bind="organization.facebook"  placeholder="https://facebook.com/..."></div>
                    <div class="field"><label>Twitter / X</label><input data-bind="organization.twitter"  placeholder="https://twitter.com/..."></div>
                </div>
                <div class="row2">
                    <div class="field"><label>Instagram</label><input data-bind="organization.instagram" placeholder="https://instagram.com/..."></div>
                    <div class="field"><label>LinkedIn</label><input data-bind="organization.linkedin"   placeholder="https://linkedin.com/company/..."></div>
                </div>
            </div>
        </section>

        <section class="tabs-content" data-tab="hero">
            <div class="card">
                <h3><i class="fas fa-image"></i> Hero Slider <button class="btn" style="float:right" data-add="heroSlides">+ Add slide</button></h3>
                <p class="help" style="margin-top:0">Each slide rotates automatically on the homepage. Add multiple to make the hero come alive. Image + headline + subtitle change together.</p>
                <div class="row2">
                    <div class="field"><label>Rotation interval (ms)</label><input type="number" data-bind="hero.rotateMs" data-as="number" placeholder="6500"></div>
                    <div class="field">
                        <label>CTA Buttons</label>
                        <div style="display:flex;gap:8px">
                            <input data-bind="hero.primaryCtaText"   placeholder="Primary text">
                            <input data-bind="hero.secondaryCtaText" placeholder="Secondary text">
                        </div>
                    </div>
                </div>
                <div data-list="heroSlides"></div>
            </div>

            <div class="card">
                <h3><i class="fas fa-info-circle"></i> About Section</h3>
                <div class="field"><label>Heading</label><input data-bind="about.heading"></div>
            </div>
            <div class="card">
                <h3><i class="fas fa-images"></i> About Slider <button class="btn" style="float:right" data-add="aboutSlides">+ Add slide</button></h3>
                <p class="help" style="margin-top:0">Add multiple "About" slides - the image and the paragraphs will fade between each slide.</p>
                <div class="field"><label>Rotation interval (ms)</label><input type="number" data-bind="about.rotateMs" data-as="number" placeholder="8000"></div>
                <div data-list="aboutSlides"></div>
            </div>
        </section>

        <section class="tabs-content" data-tab="stats">
            <div class="card">
                <h3>Impact Stats <button class="btn" style="float:right" data-add="stats">+ Add stat</button></h3>
                <div data-list="stats"></div>
            </div>
        </section>

        <section class="tabs-content" data-tab="programs">
            <div class="card">
                <h3>Programs <button class="btn" style="float:right" data-add="programs">+ Add program</button></h3>
                <p class="help" style="margin-top:0">FontAwesome icon names (e.g., <code>fa-tint</code>, <code>fa-book-open</code>). See <a href="https://fontawesome.com/icons" target="_blank">fontawesome.com/icons</a>.</p>
                <div data-list="programs"></div>
            </div>
        </section>

        <section class="tabs-content" data-tab="impact">
            <div class="card">
                <h3>Impact Stories <button class="btn" style="float:right" data-add="impactStories">+ Add story</button></h3>
                <div data-list="impactStories"></div>
            </div>
        </section>

        <section class="tabs-content" data-tab="team">
            <div class="card">
                <h3>Team Members <button class="btn" style="float:right" data-add="team">+ Add member</button></h3>
                <p class="help" style="margin-top:0">Adding real names + photos is the single biggest trust signal for sponsors.</p>
                <div data-list="team"></div>
            </div>
        </section>

        <section class="tabs-content" data-tab="events">
            <div class="card">
                <h3>Events <button class="btn" style="float:right" data-add="events">+ Add event</button></h3>
                <div data-list="events"></div>
            </div>
        </section>

        <section class="tabs-content" data-tab="donation">
            <div class="card">
                <h3>Donation Campaign</h3>
                <div class="row3">
                    <div class="field"><label>Currency</label><input data-bind="donation.currency" placeholder="USD"></div>
                    <div class="field"><label>Goal (number)</label><input type="number" data-bind="donation.goal"></div>
                    <div class="field"><label>Raised (number)</label><input type="number" data-bind="donation.raised"></div>
                </div>
                <div class="field">
                    <label>Suggested Amounts (comma-separated)</label>
                    <input data-bind="donation.amounts" data-as="numberList" placeholder="10, 25, 50, 100">
                </div>
                <div class="row2">
                    <div class="field"><label>MTN Merchant Code</label><input data-bind="donation.merchantCode"></div>
                    <div class="field"><label>Airtel Merchant Code</label><input data-bind="donation.airtelMerchantCode"></div>
                </div>
                <div class="field"><label>Donation Note</label><textarea data-bind="donation.note"></textarea></div>
            </div>
            <div class="card">
                <h3><i class="fas fa-university"></i> Bank Transfer Details</h3>
                <p class="help" style="margin-top:0">Shown to donors who choose the Bank tab when donating.</p>
                <div class="row2">
                    <div class="field"><label>Bank Name</label><input data-bind="donation.bank.bankName"></div>
                    <div class="field"><label>Account Name</label><input data-bind="donation.bank.accountName"></div>
                </div>
                <div class="row2">
                    <div class="field"><label>Account Number</label><input data-bind="donation.bank.accountNumber"></div>
                    <div class="field"><label>Branch</label><input data-bind="donation.bank.branch"></div>
                </div>
                <div class="field"><label>SWIFT Code</label><input data-bind="donation.bank.swift"></div>
            </div>
        </section>

        <section class="tabs-content" data-tab="trust">
            <div class="card">
                <h3>Trust & Legal</h3>
                <div class="row2">
                    <div class="field"><label>Country of Registration</label><input data-bind="trust.registrationCountry"></div>
                    <div class="field">
                        <label>Tax Deductible</label>
                        <select data-bind="trust.taxDeductible" data-as="bool">
                            <option value="false">No</option>
                            <option value="true">Yes</option>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label>Annual Report URL (PDF link)</label>
                    <input data-bind="trust.annualReportUrl">
                    <p class="help">Upload your annual report PDF to <code>frontend/images/</code> (or anywhere accessible) and link it here.</p>
                </div>
            </div>
        </section>

        <section class="tabs-content" data-tab="submissions">
            <div class="card">
                <h3><i class="fas fa-donate"></i> Donations</h3>
                <div id="donationsTable"></div>
            </div>
            <div class="card">
                <h3><i class="fas fa-envelope"></i> Contact Messages</h3>
                <div id="messagesTable"></div>
            </div>
            <div class="card">
                <h3><i class="fas fa-paper-plane"></i> Newsletter Subscribers</h3>
                <div id="subscribersTable"></div>
            </div>
        </section>
    </main>
</div>

<div class="toast-host" id="toastHost"></div>

<script>
(function(){
    'use strict';
    let CONTENT = {};
    const $  = (s, c=document)=>c.querySelector(s);
    const $$ = (s, c=document)=>Array.from(c.querySelectorAll(s));

    /* Mobile sidebar toggle */
    const sidebar  = $('#adminSidebar');
    const burger   = $('#adminHamburger');
    const backdrop = $('#sidebarBackdrop');
    function setSidebar(open){
        if(!sidebar) return;
        sidebar.classList.toggle('active', open);
        if(backdrop) backdrop.classList.toggle('active', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }
    if(burger)   burger.addEventListener('click', () => setSidebar(true));
    if(backdrop) backdrop.addEventListener('click', () => setSidebar(false));
    document.addEventListener('keyup', e => { if(e.key==='Escape') setSidebar(false); });
    // Close sidebar when a nav button is tapped on mobile
    $$('#adminNav button').forEach(b => b.addEventListener('click', () => {
        if(window.innerWidth <= 780) setSidebar(false);
    }));
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    function getPath(obj, path){
        return path.split('.').reduce((a,k)=> (a==null?undefined:a[k]), obj);
    }
    function setPath(obj, path, value){
        const ks = path.split('.');
        let cur = obj;
        while (ks.length > 1){
            const k = ks.shift();
            if (cur[k] == null || typeof cur[k] !== 'object') cur[k] = {};
            cur = cur[k];
        }
        cur[ks[0]] = value;
    }
    function toast(msg, type='success'){
        const el = document.createElement('div');
        el.className = 'toast' + (type==='error'?' error':'');
        el.textContent = msg;
        $('#toastHost').appendChild(el);
        setTimeout(()=>{el.style.opacity='0'; el.style.transition='opacity .3s'; setTimeout(()=>el.remove(),300)}, 3500);
    }

    const TEMPLATES = {
        stats:        { icon:'fa-users',   number:'0',  label:'Label' },
        programs:     { icon:'fa-hand-holding-heart', title:'New Program', description:'', stats:'', image:'' },
        impactStories:{ title:'New Story', text:'', quote:'', quoteAuthor:'', image:'' },
        team:         { name:'Team Member', role:'', bio:'', image:'' },
        events:       { title:'New Event', date:'', description:'', items:[], ctaText:'Volunteer', ctaLink:'#contact' },
        heroSlides:   { title:'New headline', subtitle:'A short supporting line', image:'' },
        aboutSlides:  { paragraphs: [], image:'' },
    };
    const FIELDS = {
        stats: [
            ['icon','Icon (e.g., fa-users)','input'],
            ['number','Number (e.g., 5,000+)','input'],
            ['label','Label','input'],
        ],
        programs: [
            ['title','Title','input'],
            ['icon','Icon (e.g., fa-tint)','input'],
            ['description','Description','textarea'],
            ['stats','Stat line (e.g., "25 communities reached")','input'],
            ['image','Image URL','input'],
        ],
        impactStories: [
            ['title','Title','input'],
            ['text','Story (1-2 sentences)','textarea'],
            ['quote','Quote (without quotes)','input'],
            ['quoteAuthor','Quote Author (Name, Location)','input'],
            ['image','Image URL','input'],
        ],
        team: [
            ['name','Name','input'],
            ['role','Role','input'],
            ['bio','Short bio','textarea'],
            ['image','Photo URL (optional)','input'],
        ],
        events: [
            ['title','Title','input'],
            ['date','Date (e.g., April 15-30, 2026)','input'],
            ['description','Description','textarea'],
            ['items','Items needed (comma-separated)','itemsList'],
            ['ctaText','Button text','input'],
            ['ctaLink','Button link (e.g., #contact)','input'],
        ],
        heroSlides: [
            ['title','Headline','input'],
            ['subtitle','Subtitle','textarea'],
            ['image','Background Image','input'],
        ],
        aboutSlides: [
            ['paragraphs','Paragraphs (blank line between paragraphs)','paragraphs'],
            ['image','Image','input'],
        ],
    };

    function renderListItem(listKey, idx, item){
        const fields = FIELDS[listKey];
        const inner = fields.map(([k, label, type]) => {
            const v  = item[k];
            const id = `${listKey}_${idx}_${k}`;
            const liKey = `${listKey}|${idx}|${k}`;
            if (type === 'textarea') {
                return `<div class="field"><label for="${id}">${label}</label><textarea id="${id}" data-li="${liKey}">${esc(v ?? '')}</textarea></div>`;
            }
            if (type === 'paragraphs') {
                const val = Array.isArray(v) ? v.join('\n\n') : (v || '');
                return `<div class="field"><label for="${id}">${label}</label><textarea id="${id}" data-li="${liKey}" data-as="paragraphs" style="min-height:140px">${esc(val)}</textarea></div>`;
            }
            if (type === 'itemsList') {
                const val = Array.isArray(v) ? v.join(', ') : (v||'');
                return `<div class="field"><label for="${id}">${label}</label><input id="${id}" data-li="${liKey}" data-as="commaList" value="${esc(val)}"></div>`;
            }
            // Image fields get the upload widget
            if (k === 'image') {
                return `
                    <div class="field image-field">
                        <label>${label}</label>
                        <div class="image-row">
                            <div class="preview ${v ? '' : 'empty'}" data-preview-for-li="${liKey}" ${v ? `style="background-image:url('${esc(v)}')"` : ''}>${v ? '' : 'No image'}</div>
                            <div class="image-controls">
                                <label class="upload-btn"><i class="fas fa-upload"></i> Choose image
                                    <input type="file" accept="image/*" data-image-upload-li="${liKey}">
                                </label>
                                <input id="${id}" data-li="${liKey}" value="${esc(v ?? '')}" placeholder="Or paste an image URL">
                            </div>
                        </div>
                    </div>`;
            }
            return `<div class="field"><label for="${id}">${label}</label><input id="${id}" data-li="${liKey}" value="${esc(v ?? '')}"></div>`;
        }).join('');
        return `
            <div class="list-item" data-row="${listKey}|${idx}">
                <div class="list-item-head">
                    <strong>${esc(item.title || item.name || item.label || ('#'+(idx+1)))}</strong>
                    <div class="list-item-actions">
                        <button class="icon-btn" title="Move up"   data-move="${listKey}|${idx}|-1"><i class="fas fa-arrow-up"></i></button>
                        <button class="icon-btn" title="Move down" data-move="${listKey}|${idx}|1"><i class="fas fa-arrow-down"></i></button>
                        <button class="icon-btn danger" title="Delete" data-del="${listKey}|${idx}"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                ${inner}
            </div>`;
    }

    function renderList(listKey){
        const host = $(`[data-list="${listKey}"]`);
        if (!host) return;
        const arr = Array.isArray(CONTENT[listKey]) ? CONTENT[listKey] : [];
        if (!arr.length) { host.innerHTML = '<p class="empty">No items yet. Click "+ Add" above.</p>'; return; }
        host.innerHTML = arr.map((it, i) => renderListItem(listKey, i, it)).join('');
        refreshAllPreviews();
    }

    function bindFields(){
        $$('[data-bind]').forEach(el => {
            const path = el.getAttribute('data-bind');
            const as   = el.getAttribute('data-as');
            let v = getPath(CONTENT, path);
            if (as === 'paragraphs' && Array.isArray(v)) v = v.join('\n\n');
            if (as === 'numberList' && Array.isArray(v)) v = v.join(', ');
            if (as === 'bool') v = v ? 'true' : 'false';
            el.value = v ?? '';
        });
        refreshAllPreviews();
    }

    /* ---------- Image upload + preview ---------- */
    function setPreview(host, url){
        if (!host) return;
        if (url) {
            host.style.backgroundImage = `url('${url.replace(/'/g, "%27")}')`;
            host.classList.remove('empty');
            host.textContent = '';
        } else {
            host.style.backgroundImage = '';
            host.classList.add('empty');
            host.textContent = 'No image';
        }
    }
    function refreshAllPreviews(){
        // Static (data-bind) previews
        $$('[data-preview-for]').forEach(host => {
            const path = host.getAttribute('data-preview-for');
            setPreview(host, getPath(CONTENT, path) || '');
        });
        // List-item previews (read from current input value, not CONTENT)
        $$('[data-preview-for-li]').forEach(host => {
            const liKey = host.getAttribute('data-preview-for-li');
            const input = $(`[data-li="${CSS.escape(liKey)}"]`);
            setPreview(host, input ? input.value : '');
        });
    }

    async function uploadImage(file){
        if (!file) throw new Error('No file');
        if (!/^image\//.test(file.type)) throw new Error('Please choose an image file.');
        if (file.size > 5 * 1024 * 1024) throw new Error('File too large (max 5MB).');
        const fd = new FormData();
        fd.append('file', file);
        const res  = await fetch('api/admin_upload.php', { method: 'POST', body: fd });
        const body = await res.json().catch(() => ({}));
        if (res.status === 401) { location.reload(); throw new Error('Session expired'); }
        if (!res.ok || !body.success) throw new Error(body.message || 'Upload failed');
        return body.url;
    }

    async function handleUpload(fileInput, targetSelectorFn, previewSelectorFn){
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;
        const wrap = fileInput.closest('.upload-btn');
        if (wrap) wrap.classList.add('uploading');
        try {
            const url = await uploadImage(file);
            const target = targetSelectorFn();
            if (target) {
                target.value = url;
                target.dispatchEvent(new Event('input', { bubbles: true }));
            }
            const preview = previewSelectorFn();
            setPreview(preview, url);
            toast('Image uploaded.');
        } catch (err) {
            toast(err.message || 'Upload failed', 'error');
        } finally {
            if (wrap) wrap.classList.remove('uploading');
            fileInput.value = '';
        }
    }

    document.addEventListener('change', e => {
        // Static field upload  (data-image-upload="hero.image")
        if (e.target.matches('[data-image-upload]')) {
            const path = e.target.getAttribute('data-image-upload');
            handleUpload(
                e.target,
                () => $(`[data-bind="${CSS.escape(path)}"]`),
                () => $(`[data-preview-for="${CSS.escape(path)}"]`)
            );
            return;
        }
        // List-item upload (data-image-upload-li="programs|0|image")
        if (e.target.matches('[data-image-upload-li]')) {
            const liKey = e.target.getAttribute('data-image-upload-li');
            handleUpload(
                e.target,
                () => $(`[data-li="${CSS.escape(liKey)}"]`),
                () => $(`[data-preview-for-li="${CSS.escape(liKey)}"]`)
            );
        }
    });

    // When admin types/pastes a URL manually, refresh preview live
    document.addEventListener('input', e => {
        if (e.target.matches('[data-bind]')) {
            const path = e.target.getAttribute('data-bind');
            const host = $(`[data-preview-for="${CSS.escape(path)}"]`);
            if (host) setPreview(host, e.target.value);
        }
        if (e.target.matches('[data-li]')) {
            const liKey = e.target.getAttribute('data-li');
            const host  = $(`[data-preview-for-li="${CSS.escape(liKey)}"]`);
            if (host) setPreview(host, e.target.value);
        }
    });
    function collectFields(){
        $$('[data-bind]').forEach(el => {
            const path = el.getAttribute('data-bind');
            const as   = el.getAttribute('data-as');
            let v = el.value;
            if (as === 'paragraphs') v = v.split(/\n\s*\n/).map(s=>s.trim()).filter(Boolean);
            else if (as === 'numberList') v = v.split(',').map(s=>Number(s.trim())).filter(n=>!isNaN(n) && n>0);
            else if (as === 'bool') v = v === 'true';
            else if (el.type === 'number') v = v === '' ? 0 : Number(v);
            setPath(CONTENT, path, v);
        });
        $$('[data-li]').forEach(el => {
            const [listKey, idx, key] = el.getAttribute('data-li').split('|');
            const i = Number(idx);
            const arr = CONTENT[listKey] || (CONTENT[listKey] = []);
            if (!arr[i]) arr[i] = {};
            let v = el.value;
            const as = el.getAttribute('data-as');
            if (as === 'commaList') {
                v = v.split(',').map(s=>s.trim()).filter(Boolean);
            } else if (as === 'paragraphs') {
                v = v.split(/\n\s*\n/).map(s=>s.trim()).filter(Boolean);
            }
            arr[i][key] = v;
        });
    }

    $$('#adminNav button').forEach(b => {
        b.addEventListener('click', () => {
            $$('#adminNav button').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            const t = b.getAttribute('data-tab');
            $$('.tabs-content').forEach(s => s.classList.toggle('active', s.getAttribute('data-tab') === t));
            $('#tabTitle').textContent = b.textContent.trim();
            if (t === 'submissions') loadSubmissions();
        });
    });

    document.addEventListener('click', e => {
        const add = e.target.closest('[data-add]');
        if (add) {
            const k = add.getAttribute('data-add');
            collectFields();
            CONTENT[k] = CONTENT[k] || [];
            CONTENT[k].push(JSON.parse(JSON.stringify(TEMPLATES[k] || {})));
            renderList(k);
            return;
        }
        const del = e.target.closest('[data-del]');
        if (del) {
            const [k, idx] = del.getAttribute('data-del').split('|');
            if (!confirm('Delete this item?')) return;
            collectFields();
            CONTENT[k].splice(Number(idx), 1);
            renderList(k);
            return;
        }
        const mv = e.target.closest('[data-move]');
        if (mv) {
            const [k, idx, dir] = mv.getAttribute('data-move').split('|');
            const i = Number(idx), d = Number(dir);
            const arr = CONTENT[k];
            const j = i + d;
            if (j < 0 || j >= arr.length) return;
            collectFields();
            [arr[i], arr[j]] = [arr[j], arr[i]];
            renderList(k);
        }
    });

    $('#saveBtn').addEventListener('click', async () => {
        collectFields();
        // Mirror the heroSlides/aboutSlides shadow lists into hero.slides / about.slides
        if (Array.isArray(CONTENT.heroSlides))  { (CONTENT.hero  ||= {}).slides = CONTENT.heroSlides;  delete CONTENT.heroSlides; }
        if (Array.isArray(CONTENT.aboutSlides)) { (CONTENT.about ||= {}).slides = CONTENT.aboutSlides; delete CONTENT.aboutSlides; }
        const btn = $('#saveBtn');
        btn.disabled = true; const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        try {
            const res  = await fetch('api/admin_save.php', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify(CONTENT)
            });
            const body = await res.json().catch(()=>({}));
            if (res.ok && body.success) { toast('Saved successfully.'); }
            else if (res.status === 401) { location.reload(); }
            else { toast(body.message || 'Save failed', 'error'); }
        } catch (err) { toast('Network error', 'error'); }
        finally { btn.disabled = false; btn.innerHTML = orig; }
    });

    $('#logoutBtn').addEventListener('click', async () => {
        await fetch('api/admin_logout.php', { method:'POST' });
        location.reload();
    });

    async function loadSubmissions(){
        try {
            const res  = await fetch('api/admin_data.php');
            if (res.status === 401) { location.reload(); return; }
            const body = await res.json();

            const dt = $('#donationsTable');
            if (!body.donations.length) dt.innerHTML = '<p class="empty">No donations yet.</p>';
            else dt.innerHTML = `
                <table>
                    <thead><tr><th>Date</th><th>Amount</th><th>Donor</th><th>Phone</th><th>Status</th><th>Ref</th></tr></thead>
                    <tbody>${body.donations.map(d => `
                        <tr>
                            <td>${esc(new Date(d.createdAt).toLocaleString())}</td>
                            <td>${esc(d.currency || '')} ${esc(d.amount)}</td>
                            <td>${esc(d.name || 'Anonymous')}<br><small>${esc(d.email||'')}</small></td>
                            <td>${esc(d.phone)}</td>
                            <td><span class="status-badge s-${esc(d.status||'')}">${esc(d.status||'')}</span></td>
                            <td><small>${esc(d.reference||'-')}</small></td>
                        </tr>`).join('')}
                    </tbody>
                </table>`;

            const mt = $('#messagesTable');
            if (!body.messages.length) mt.innerHTML = '<p class="empty">No messages yet.</p>';
            else mt.innerHTML = `
                <table>
                    <thead><tr><th>Date</th><th>From</th><th>Email</th><th>Message</th></tr></thead>
                    <tbody>${body.messages.map(m => `
                        <tr>
                            <td>${esc(new Date(m.createdAt).toLocaleString())}</td>
                            <td>${esc(m.name)}</td>
                            <td><a href="mailto:${esc(m.email)}">${esc(m.email)}</a></td>
                            <td>${esc(m.message)}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>`;

            const st = $('#subscribersTable');
            if (!body.subscribers.length) st.innerHTML = '<p class="empty">No subscribers yet.</p>';
            else st.innerHTML = `
                <table>
                    <thead><tr><th>Date</th><th>Email</th></tr></thead>
                    <tbody>${body.subscribers.map(s => `
                        <tr><td>${esc(new Date(s.createdAt).toLocaleString())}</td><td>${esc(s.email)}</td></tr>`).join('')}
                    </tbody>
                </table>`;
        } catch (err) {
            toast('Could not load submissions', 'error');
        }
    }

    async function init(){
        try {
            const res  = await fetch('api/content.php');
            CONTENT    = await res.json();
            bindFields();
            ['stats','programs','impactStories','team','events','heroSlides','aboutSlides']
                .forEach(k => {
                    // Map heroSlides -> hero.slides, aboutSlides -> about.slides for storage
                    if (k === 'heroSlides') {
                        if (!CONTENT.hero) CONTENT.hero = {};
                        CONTENT[k] = CONTENT.hero.slides || [];
                    } else if (k === 'aboutSlides') {
                        if (!CONTENT.about) CONTENT.about = {};
                        CONTENT[k] = CONTENT.about.slides || [];
                    }
                    renderList(k);
                });
        } catch (err) {
            toast('Could not load content', 'error');
        }
    }
    init();
})();
</script>
<?php endif; ?>
</body>
</html>
