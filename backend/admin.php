<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/lib/helpers.php';
$loggedIn = !empty($_SESSION['is_admin']);
if ($loggedIn) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}
$csrfToken = csrf_token();
$notifyEmail = defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : CHARITY_EMAIL;
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
<link rel="stylesheet" href="css/admin.css">
</head>
<body>

<?php if (!$loggedIn): ?>
<div class="login-wrap">
    <div class="login-brand">
        <div class="login-brand-inner">
            <div class="login-logo"><i class="fas fa-heart"></i></div>
            <h1>Tattu Care Admin</h1>
            <p>Manage your charity website content, events, donations, and submissions — changes go live when you save.</p>
            <div class="login-features">
                <span><i class="fas fa-check-circle"></i> Edit hero, programs, events &amp; team</span>
                <span><i class="fas fa-check-circle"></i> Track donations &amp; messages in real time</span>
                <span><i class="fas fa-check-circle"></i> Publish instantly to the live website</span>
            </div>
        </div>
    </div>
    <div class="login-panel">
        <form class="login-card" id="loginForm" novalidate>
            <h2>Sign in</h2>
            <p>Enter your admin password to continue.</p>
            <div class="field">
                <label for="pw">Password</label>
                <input type="password" id="pw" name="password" required autofocus placeholder="Admin password">
            </div>
            <button type="submit" class="btn" style="width:100%;justify-content:center">Sign in to dashboard</button>
            <a href="../index.html" class="login-back"><i class="fas fa-arrow-left"></i> Back to website</a>
        </form>
    </div>
</div>
<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const pw = document.getElementById('pw').value;
    const res = await fetch('api/admin_login.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json', 'X-CSRF-Token': <?= json_encode($csrfToken) ?>},
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
        <div class="sidebar-brand">
            <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close menu"><i class="fas fa-times"></i></button>
            <h2><i class="fas fa-heart"></i> <span class="sidebar-brand-text">Tattu Care</span></h2>
            <small class="sidebar-brand-text">Content Management</small>
        </div>
        <nav id="adminNav">
            <button data-tab="dashboard"  class="active"><i class="fas fa-th-large"></i> Dashboard</button>
            <div class="nav-section">CONTENT</div>
            <button data-tab="org"><i class="fas fa-building"></i> Organization</button>
            <button data-tab="hero"><i class="fas fa-image"></i> Hero & About</button>
            <button data-tab="stats"><i class="fas fa-chart-bar"></i> Stats</button>
            <button data-tab="programs"><i class="fas fa-hand-holding-heart"></i> Programs</button>
            <button data-tab="impact"><i class="fas fa-quote-right"></i> Impact Stories</button>
            <button data-tab="team"><i class="fas fa-users"></i> Team</button>
            <button data-tab="events"><i class="fas fa-calendar"></i> Events</button>
            <div class="nav-section">SETTINGS</div>
            <button data-tab="donation"><i class="fas fa-donate"></i> Donation</button>
            <button data-tab="trust"><i class="fas fa-shield-alt"></i> Trust & Legal</button>
            <button data-tab="submissions"><i class="fas fa-inbox"></i> Submissions <span class="nav-badge" id="navUnreadBadge" hidden>0</span></button>
            <button data-tab="reports"><i class="fas fa-file-export"></i> Reports</button>
        </nav>
        <div class="logout">
            <button id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Log out</button>
        </div>
    </aside>

    <main class="main">
        <header class="admin-topbar">
            <div class="topbar-title-group">
                <h1 id="tabTitle"><i class="fas fa-th-large"></i> Dashboard</h1>
                <p class="topbar-sub" id="tabSubtitle">Overview of your site content and recent activity</p>
            </div>
            <div class="topbar-actions">
                <span class="live-badge" title="Changes publish to the live site when you save"><span class="dot"></span> Live site</span>
                <span class="save-status" id="saveStatus">All changes saved</span>
                <a href="../index.html" target="_blank" rel="noopener" class="btn btn-ghost admin-view-site" id="viewSiteBtn"><i class="fas fa-external-link-alt"></i> View site</a>
                <button id="saveBtn" class="btn"><i class="fas fa-save"></i> Save &amp; publish</button>
            </div>
        </header>
        <div class="main-body">
        <div id="msg"></div>

        <!-- ============ DASHBOARD ============ -->
        <section class="tabs-content active page-view" data-tab="dashboard">
            <div class="dash-stats-strip">
                <button type="button" class="dash-stat" data-go-tab="programs" title="Manage programs">
                    <span class="dash-stat-icon" style="background:rgba(46,204,113,.12);color:#27ae60"><i class="fas fa-hand-holding-heart"></i></span>
                    <span class="dash-stat-body"><span class="dash-stat-val" data-stat="programs">0</span><span class="dash-stat-lbl">Programs</span></span>
                    <i class="fas fa-chevron-right dash-stat-go" aria-hidden="true"></i>
                </button>
                <button type="button" class="dash-stat" data-go-tab="impact" title="Manage impact stories">
                    <span class="dash-stat-icon" style="background:rgba(243,156,18,.12);color:#f39c12"><i class="fas fa-quote-right"></i></span>
                    <span class="dash-stat-body"><span class="dash-stat-val" data-stat="impactStories">0</span><span class="dash-stat-lbl">Stories</span></span>
                    <i class="fas fa-chevron-right dash-stat-go" aria-hidden="true"></i>
                </button>
                <button type="button" class="dash-stat" data-go-tab="team" title="Manage team">
                    <span class="dash-stat-icon" style="background:rgba(52,152,219,.12);color:#3498db"><i class="fas fa-users"></i></span>
                    <span class="dash-stat-body"><span class="dash-stat-val" data-stat="team">0</span><span class="dash-stat-lbl">Team</span></span>
                    <i class="fas fa-chevron-right dash-stat-go" aria-hidden="true"></i>
                </button>
                <button type="button" class="dash-stat" data-go-tab="events" title="Manage events">
                    <span class="dash-stat-icon" style="background:rgba(155,89,182,.12);color:#9b59b6"><i class="fas fa-calendar"></i></span>
                    <span class="dash-stat-body"><span class="dash-stat-val" data-stat="events">0</span><span class="dash-stat-lbl">Events</span><span class="dash-stat-sub" data-stat="eventsUpcoming"></span></span>
                    <i class="fas fa-chevron-right dash-stat-go" aria-hidden="true"></i>
                </button>
                <button type="button" class="dash-stat" data-go-tab="submissions" data-inbox-panel="inbox-donations" title="Open donations inbox">
                    <span class="dash-stat-icon" style="background:rgba(46,204,113,.12);color:#27ae60"><i class="fas fa-donate"></i></span>
                    <span class="dash-stat-body"><span class="dash-stat-val" data-stat="donations">0</span><span class="dash-stat-lbl">Donations</span><span class="dash-stat-sub" data-stat="raised"></span></span>
                    <i class="fas fa-chevron-right dash-stat-go" aria-hidden="true"></i>
                </button>
                <button type="button" class="dash-stat" data-go-tab="submissions" data-inbox-panel="inbox-messages" title="Open messages inbox">
                    <span class="dash-stat-icon" style="background:rgba(231,76,60,.12);color:#e74c3c"><i class="fas fa-envelope"></i></span>
                    <span class="dash-stat-body"><span class="dash-stat-val" data-stat="messages">0</span><span class="dash-stat-lbl">Messages</span><span class="dash-stat-sub" data-stat="unreadMessages"></span></span>
                    <i class="fas fa-chevron-right dash-stat-go" aria-hidden="true"></i>
                </button>
                <button type="button" class="dash-stat" data-go-tab="submissions" data-inbox-panel="inbox-subscribers" title="Open subscribers list">
                    <span class="dash-stat-icon" style="background:rgba(52,152,219,.12);color:#3498db"><i class="fas fa-paper-plane"></i></span>
                    <span class="dash-stat-body"><span class="dash-stat-val" data-stat="subscribers">0</span><span class="dash-stat-lbl">Subscribers</span></span>
                    <i class="fas fa-chevron-right dash-stat-go" aria-hidden="true"></i>
                </button>
            </div>

            <div class="dash-toolbar">
                <button type="button" class="qa-sm" data-go-tab="programs"><i class="fas fa-plus"></i> Program</button>
                <button type="button" class="qa-sm" data-go-tab="impact"><i class="fas fa-plus"></i> Story</button>
                <button type="button" class="qa-sm" data-go-tab="team"><i class="fas fa-plus"></i> Member</button>
                <button type="button" class="qa-sm" data-go-tab="events"><i class="fas fa-plus"></i> Event</button>
                <button type="button" class="qa-sm" data-go-tab="submissions" data-inbox-panel="inbox-donations"><i class="fas fa-inbox"></i> Inbox</button>
            </div>

            <div class="dash-split">
                <div class="card card-compact">
                    <div class="card-head-row">
                        <h3><i class="fas fa-donate"></i> Recent donations</h3>
                        <button type="button" class="link-btn" data-go-tab="submissions" data-inbox-panel="inbox-donations">View all</button>
                    </div>
                    <div id="dashRecentDonations"><p class="empty">No donations yet.</p></div>
                </div>
                <div class="card card-compact">
                    <div class="card-head-row">
                        <h3><i class="fas fa-envelope"></i> Recent messages</h3>
                        <button type="button" class="link-btn" data-go-tab="submissions" data-inbox-panel="inbox-messages">View all</button>
                    </div>
                    <div id="dashRecentMessages"><p class="empty">No messages yet.</p></div>
                </div>
            </div>
        </section>

        <!-- ============ ORGANIZATION ============ -->
        <section class="tabs-content page-view" data-tab="org">
            <div class="page-grid-2">
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
            </div>
        </section>

        <section class="tabs-content page-view" data-tab="hero">
            <div class="card">
                <h3><i class="fas fa-image"></i> Hero Slider <button class="btn btn-sm" data-add="heroSlides">+ Add slide</button></h3>
                <p class="help" >Each slide rotates automatically on the homepage. Add multiple to make the hero come alive. Image + headline + subtitle change together.</p>
                <div class="row2">
                    <div class="field"><label>Rotation interval (ms)</label><input type="number" data-bind="hero.rotateMs" data-as="number" placeholder="4000"></div>
                    <div class="field">
                        <label>CTA Buttons</label>
                        <div class="field-inline">
                            <input data-bind="hero.primaryCtaText"   placeholder="Primary text">
                            <input data-bind="hero.secondaryCtaText" placeholder="Secondary text">
                        </div>
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label>Auto-promote events in hero</label>
                        <select data-bind="hero.autoPromoteEvents" data-as="bool">
                            <option value="true">Yes — inject upcoming &amp; featured events</option>
                            <option value="false">No — manual slides only</option>
                        </select>
                    </div>
                    <div class="field"><label>Promote events within (days)</label><input type="number" data-bind="hero.eventPromoteDays" data-as="number" placeholder="60"></div>
                </div>
                <p class="help">Featured events appear first in the hero. Set <strong>Feature on Hero</strong> per event under Events. Use ISO dates (YYYY-MM-DD) for reliable scheduling.</p>
                <div data-list="heroSlides"></div>
            </div>

            <div class="card">
                <h3><i class="fas fa-images"></i> About <button class="btn btn-sm" data-add="aboutSlides">+ Add slide</button></h3>
                <div class="row2">
                    <div class="field"><label>Heading</label><input data-bind="about.heading"></div>
                    <div class="field"><label>Rotation interval (ms)</label><input type="number" data-bind="about.rotateMs" data-as="number" placeholder="3600"></div>
                </div>
                <p class="help" >Multiple slides — image and paragraphs crossfade on the site.</p>
                <div data-list="aboutSlides"></div>
            </div>
        </section>

        <section class="tabs-content page-view" data-tab="stats">
            <div class="page-grid-2 page-grid-aside">
            <div class="card">
                <h3>Hero stats card</h3>
                <p class="help" >Glass card below the hero. Pick the source and how many items appear (max 4).</p>
                <div class="field">
                    <label>Show on website</label>
                    <select data-bind="statsDisplay.showSection" data-as="bool">
                        <option value="true">Yes — visible</option>
                        <option value="false">No — hidden</option>
                    </select>
                </div>
                <div class="field">
                    <label>Content source</label>
                    <select data-bind="statsDisplay.source">
                        <option value="custom">Custom stats (list below)</option>
                        <option value="programs">Pull from Programs</option>
                    </select>
                </div>
                <div class="field">
                    <label>Max items shown</label>
                    <input type="number" data-bind="statsDisplay.maxItems" data-as="number" min="1" max="4" placeholder="4">
                </div>
            </div>
            <div class="card">
                <h3>Custom stats <button class="btn btn-sm" data-add="stats">+ Add stat</button></h3>
                <p class="help" >Uncheck <strong>Show on site</strong> to hide from visitors. Reorder with ↑↓.</p>
                <div data-list="stats"></div>
            </div>
            </div>
        </section>

        <section class="tabs-content page-view" data-tab="programs">
            <div class="card">
                <h3>Programs <button class="btn btn-sm" data-add="programs">+ Add program</button></h3>
                <p class="help" >FontAwesome icon names (e.g., <code>fa-tint</code>, <code>fa-book-open</code>). See <a href="https://fontawesome.com/icons" target="_blank">fontawesome.com/icons</a>.</p>
                <div data-list="programs"></div>
            </div>
        </section>

        <section class="tabs-content page-view" data-tab="impact">
            <div class="card">
                <h3>Impact Stories <button class="btn btn-sm" data-add="impactStories">+ Add story</button></h3>
                <div data-list="impactStories"></div>
            </div>
        </section>

        <section class="tabs-content page-view" data-tab="team">
            <div class="card">
                <h3>Team Members <button class="btn btn-sm" data-add="team">+ Add member</button></h3>
                <p class="help" >Adding real names + photos is the single biggest trust signal for sponsors.</p>
                <div data-list="team"></div>
            </div>
        </section>

        <section class="tabs-content page-view" data-tab="events">
            <div class="card">
                <h3>Events <button class="btn btn-sm" data-add="events">+ Add event</button></h3>
                <p class="help" >Use <strong>Featured</strong> + <strong>Feature on Hero</strong> to spotlight drives on the homepage. Set <code>startDate</code> / <code>endDate</code> as YYYY-MM-DD for auto hero promotion.</p>
                <div data-list="events"></div>
            </div>
        </section>

        <section class="tabs-content page-view" data-tab="donation">
            <div class="page-grid-2">
            <div class="card">
                <h3>Donation Campaign</h3>
                <div class="row3">
                    <div class="field"><label>Currency (site default)</label><input data-bind="donation.currency" placeholder="UGX"></div>
                    <div class="field"><label>Goal (number)</label><input type="number" data-bind="donation.goal" placeholder="37000000"></div>
                    <div class="field"><label>Raised (number)</label><input type="number" data-bind="donation.raised" placeholder="27750000"></div>
                </div>
                <p class="help" >
                    <button type="button" class="btn btn-ghost help-action-btn" id="syncRaisedBtn"><i class="fas fa-sync"></i> Sync raised from recorded donations</button>
                    — sums confirmed donations converted to site currency (UGX).
                </p>
                <div class="field">
                    <label>Suggested Amounts (comma-separated, in site currency)</label>
                    <input data-bind="donation.amounts" data-as="numberList" placeholder="10000, 25000, 50000, 100000">
                </div>
                <div class="field">
                    <label>Supported currencies (comma-separated codes)</label>
                    <input data-bind="donation.supportedCurrencies" data-as="stringList" placeholder="UGX, USD, EUR, BTC, ETH, USDT">
                    <p class="help">Shown in the donor currency picker — fiat and crypto codes.</p>
                </div>
                <div class="row2">
                    <div class="field"><label>MTN Merchant Code</label><input data-bind="donation.merchantCode"></div>
                    <div class="field"><label>Airtel Merchant Code</label><input data-bind="donation.airtelMerchantCode"></div>
                </div>
                <div class="field"><label>Donation Note</label><textarea data-bind="donation.note"></textarea></div>
            </div>
            <div class="card">
                <h3><i class="fas fa-university"></i> Bank Transfer Details</h3>
                <p class="help" >Shown to donors who choose the Bank tab when donating.</p>
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
            <div class="card">
                <h3><i class="fab fa-bitcoin"></i> Crypto wallet addresses</h3>
                <p class="help">Paste your receiving addresses — donors see these on the Crypto tab with a copy button.</p>
                <div class="row2">
                    <div class="field"><label>BTC</label><input data-bind="donation.crypto.BTC" placeholder="bc1… or 1…"></div>
                    <div class="field"><label>ETH</label><input data-bind="donation.crypto.ETH" placeholder="0x…"></div>
                </div>
                <div class="row2">
                    <div class="field"><label>USDT</label><input data-bind="donation.crypto.USDT" placeholder="TRC20 / ERC20 address"></div>
                    <div class="field"><label>USDC</label><input data-bind="donation.crypto.USDC" placeholder="0x…"></div>
                </div>
            </div>
            </div>
        </section>

        <section class="tabs-content page-view" data-tab="trust">
            <div class="page-grid-3">
            <div class="card">
                <h3><i class="fas fa-shield-alt"></i> Homepage trust bar</h3>
                <p class="help" >The bar below the hero (Reg #, country, founded, secure donations). Off by default — turn on only when you want visitors to see it.</p>
                <div class="field">
                    <label>Show trust bar on homepage</label>
                    <select data-bind="trust.showTrustBar" data-as="bool">
                        <option value="false">No — hidden (recommended default)</option>
                        <option value="true">Yes — show trust bar</option>
                    </select>
                </div>
            </div>
            <div class="card">
                <h3><i class="fas fa-copyright"></i> Footer copyright line</h3>
                <p class="help" >Controls the line above “© All rights reserved” on the website footer and legal pages.</p>
                <div class="row2">
                    <div class="field">
                        <label>Show registration line in footer</label>
                        <select data-bind="trust.showFooterLegal" data-as="bool">
                            <option value="false">No — hide this line (default)</option>
                            <option value="true">Yes — display Reg # and country</option>
                        </select>
                    </div>
                    <div class="field"><label>Registration #</label><input data-bind="organization.registrationNumber" placeholder="e.g. 12qwer4567"></div>
                </div>
                <div class="field"><label>Registered in (country)</label><input data-bind="trust.registrationCountry" placeholder="e.g. Uganda"></div>
            </div>
            <div class="card">
                <h3>Trust & Legal</h3>
                <div class="row2">
                    <div class="field">
                        <label>Tax Deductible</label>
                        <select data-bind="trust.taxDeductible" data-as="bool">
                            <option value="false">No</option>
                            <option value="true">Yes</option>
                        </select>
                    </div>
                    <div class="field"></div>
                </div>
                <div class="field">
                    <label>Annual Report URL (PDF link)</label>
                    <input data-bind="trust.annualReportUrl">
                    <p class="help">Upload your annual report PDF to <code>frontend/images/</code> (or anywhere accessible) and link it here.</p>
                </div>
            </div>
            <div class="card card-span-2">
                <h3><i class="fas fa-handshake"></i> Partners &amp; sponsors <button class="btn btn-sm" data-add="trustPartners">+ Add partner</button></h3>
                <p class="help">Shown in the homepage partners carousel. Add logo URL or leave blank for a text-only tile.</p>
                <div data-list="trustPartners"></div>
            </div>
            </div>
        </section>

        <section class="tabs-content page-view" data-tab="reports">
            <div class="reports-hero">
                <div class="reports-hero-copy">
                    <h3><i class="fas fa-chart-pie"></i> Accountability &amp; exports</h3>
                    <p>Download CSV reports for donors, messages, subscribers, and a full accountability summary. Sync confirmed donations to update the public progress bar.</p>
                </div>
                <div class="reports-hero-actions">
                    <button type="button" class="btn btn-sm" id="reportsSyncRaised"><i class="fas fa-sync"></i> Sync raised total</button>
                    <button type="button" class="btn btn-ghost btn-sm" id="reportsPollMomo"><i class="fas fa-mobile-alt"></i> Poll pending MoMo</button>
                    <button type="button" class="btn btn-ghost btn-sm" id="reportsRefresh"><i class="fas fa-sync"></i> Refresh</button>
                </div>
            </div>

            <div class="reports-kpi-grid" id="reportsKpi">
                <div class="reports-kpi"><span class="reports-kpi-val" data-rpt="confirmedRaised">—</span><span class="reports-kpi-lbl">Confirmed raised</span></div>
                <div class="reports-kpi"><span class="reports-kpi-val" data-rpt="pendingRaised">—</span><span class="reports-kpi-lbl">Pending pledged</span></div>
                <div class="reports-kpi"><span class="reports-kpi-val" data-rpt="confirmedCount">—</span><span class="reports-kpi-lbl">Confirmed gifts</span></div>
                <div class="reports-kpi"><span class="reports-kpi-val" data-rpt="pendingCount">—</span><span class="reports-kpi-lbl">Awaiting action</span></div>
                <div class="reports-kpi"><span class="reports-kpi-val" data-rpt="messagesTotal">—</span><span class="reports-kpi-lbl">Messages</span></div>
                <div class="reports-kpi"><span class="reports-kpi-val" data-rpt="subscribersTotal">—</span><span class="reports-kpi-lbl">Subscribers</span></div>
            </div>

            <div class="card">
                <h3><i class="fas fa-download"></i> Export data</h3>
                <p class="help">CSV files open in Excel or Google Sheets. Exports are logged in the activity trail below.</p>
                <div class="export-grid">
                    <a class="export-card" href="api/admin_export.php?type=summary" download><i class="fas fa-file-alt"></i><strong>Accountability summary</strong><span>Full report with totals &amp; breakdowns</span></a>
                    <a class="export-card" href="api/admin_export.php?type=donations" download><i class="fas fa-donate"></i><strong>All donations</strong><span>Every record with status history</span></a>
                    <a class="export-card" href="api/admin_export.php?type=messages" download><i class="fas fa-envelope"></i><strong>Contact messages</strong><span>Full inbox export</span></a>
                    <a class="export-card" href="api/admin_export.php?type=subscribers" download><i class="fas fa-paper-plane"></i><strong>Newsletter list</strong><span>Subscriber emails &amp; dates</span></a>
                    <a class="export-card" href="api/admin_export.php?type=activity" download><i class="fas fa-history"></i><strong>Activity log</strong><span>System audit trail</span></a>
                </div>
            </div>

            <div class="card">
                <div class="card-head-row">
                    <h3><i class="fas fa-history"></i> Recent activity</h3>
                    <span class="help" id="reportsGeneratedAt"></span>
                </div>
                <div id="activityLog"><p class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading…</p></div>
            </div>
        </section>

        <section class="tabs-content page-view" data-tab="submissions">
            <div class="inbox-bar">
                <div class="inbox-tabs" id="inboxTabs" role="tablist">
                    <button type="button" class="inbox-tab active" data-inbox-panel="inbox-donations" role="tab" aria-selected="true">
                        <i class="fas fa-donate"></i> Donations <span class="inbox-count" id="inboxDonCount">0</span>
                        <span class="inbox-alert" id="inboxDonPending" hidden title="Donations needing confirmation">0</span>
                    </button>
                    <button type="button" class="inbox-tab" data-inbox-panel="inbox-messages" role="tab" aria-selected="false">
                        <i class="fas fa-envelope"></i> Messages <span class="inbox-count" id="inboxMsgCount">0</span>
                        <span class="inbox-alert" id="inboxUnreadCount" hidden>0</span>
                    </button>
                    <button type="button" class="inbox-tab" data-inbox-panel="inbox-subscribers" role="tab" aria-selected="false">
                        <i class="fas fa-paper-plane"></i> Subscribers <span class="inbox-count" id="inboxSubCount">0</span>
                    </button>
                </div>
                <div class="inbox-bar-actions">
                    <button type="button" class="btn btn-ghost btn-sm" id="testSmtpBtn" title="Send test email to notify inbox"><i class="fas fa-paper-plane"></i> Test email</button>
                    <button type="button" class="btn btn-ghost btn-sm" id="refreshSubmissions" title="Refresh inbox"><i class="fas fa-sync"></i></button>
                    <button type="button" class="btn btn-ghost btn-sm" id="markAllReadBtn"><i class="fas fa-check-double"></i> Mark all read</button>
                </div>
            </div>

            <details class="info-callout info-callout-compact info-fold">
                <summary><i class="fas fa-info-circle"></i> Email &amp; SMTP <span id="smtpStatusPill" class="smtp-pill">checking…</span></summary>
                <div class="info-fold-body">
                    <p>Submissions are saved here and emailed to <strong id="smtpNotifyLabel">tattuintel@gmail.com</strong> (temporary inbox until <code>info@tattucare.org</code> is ready). Outgoing mail uses the same Gmail account via SMTP.</p>
                    <p class="help">Use <strong>Test email</strong> to verify delivery. Reply with <strong>Send via SMTP</strong> or your mail app.</p>
                </div>
            </details>

            <div class="inbox-panel active" id="inbox-donations" role="tabpanel">
                <div class="inbox-toolbar inbox-toolbar-split">
                    <input type="search" class="inbox-search" id="searchDonations" placeholder="Search donations…" aria-label="Search donations">
                    <button type="button" class="btn btn-sm" id="addManualDonation"><i class="fas fa-plus"></i> Record manual donation</button>
                </div>
                <p class="help inbox-help">Click any donation card to see <strong>full details</strong>. Use <strong>Confirm</strong> or <strong>Edit</strong> for offline payments. Sync raised totals on the Donation tab.</p>
                <div class="inbox-table-host" id="donationsTable"><p class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading…</p></div>
            </div>

            <div class="inbox-panel" id="inbox-messages" role="tabpanel" hidden>
                <div class="inbox-toolbar">
                    <input type="search" class="inbox-search" id="searchMessages" placeholder="Search messages…" aria-label="Search messages">
                </div>
                <p class="help inbox-help">Click a message to read the <strong>full text</strong> and reply via SMTP or your mail app.</p>
                <div class="inbox-table-host" id="messagesTable"><p class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading…</p></div>
            </div>

            <div class="inbox-panel" id="inbox-subscribers" role="tabpanel" hidden>
                <div class="inbox-toolbar">
                    <input type="search" class="inbox-search" id="searchSubscribers" placeholder="Search subscribers…" aria-label="Search subscribers">
                </div>
                <div class="inbox-table-host" id="subscribersTable"><p class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading…</p></div>
            </div>
        </section>
        </div><!-- .main-body -->
    </main>
</div>

<nav class="admin-bottom-nav" id="adminBottomNav" aria-label="Admin quick navigation">
    <button type="button" class="abn-item active" data-bnav="dashboard"><i class="fas fa-th-large"></i><span>Home</span></button>
    <button type="button" class="abn-item" data-bnav="hero"><i class="fas fa-pen"></i><span>Content</span></button>
    <button type="button" class="abn-item" data-bnav="events"><i class="fas fa-calendar"></i><span>Events</span></button>
    <button type="button" class="abn-item" data-bnav="submissions"><i class="fas fa-inbox"></i><span>Inbox</span></button>
    <button type="button" class="abn-item" id="adminMenuOpen"><i class="fas fa-bars"></i><span>Menu</span></button>
</nav>

<div class="admin-modal" id="donationModal" hidden aria-hidden="true">
    <div class="admin-modal-backdrop" data-close-donation-modal></div>
    <div class="admin-modal-panel" role="dialog" aria-labelledby="donationModalTitle">
        <header class="admin-modal-head">
            <h3 id="donationModalTitle">Edit donation</h3>
            <button type="button" class="icon-btn" data-close-donation-modal aria-label="Close"><i class="fas fa-times"></i></button>
        </header>
        <form id="donationForm" class="admin-modal-body">
            <input type="hidden" id="donFormId">
            <div class="row2">
                <div class="field"><label for="donFormAmount">Amount</label><input type="number" id="donFormAmount" min="0" step="0.01" required></div>
                <div class="field"><label for="donFormCurrency">Currency</label><input id="donFormCurrency" maxlength="5" placeholder="UGX" required></div>
            </div>
            <div class="row2">
                <div class="field"><label for="donFormMethod">Method</label>
                    <select id="donFormMethod">
                        <option value="manual">Manual / offline</option>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank transfer</option>
                        <option value="crypto">Cryptocurrency</option>
                        <option value="momo">MTN MoMo</option>
                        <option value="airtel">Airtel Money</option>
                    </select>
                </div>
                <div class="field"><label for="donFormStatus">Status</label>
                    <select id="donFormStatus">
                        <option value="success">success — confirmed received</option>
                        <option value="bank_pledged">bank_pledged — awaiting verification</option>
                        <option value="crypto_pledged">crypto_pledged — awaiting on-chain verify</option>
                        <option value="airtel_pledged">airtel_pledged — awaiting Airtel</option>
                        <option value="awaiting_approval">awaiting_approval — MoMo prompt sent</option>
                        <option value="manual_pending">manual_pending — needs follow-up</option>
                        <option value="pending">pending</option>
                        <option value="failed">failed</option>
                        <option value="cancelled">cancelled</option>
                        <option value="rejected">rejected</option>
                        <option value="completed">completed (legacy)</option>
                    </select>
                </div>
            </div>
            <div class="row2">
                <div class="field"><label for="donFormName">Donor name</label><input id="donFormName" placeholder="Anonymous"></div>
                <div class="field"><label for="donFormEmail">Email</label><input type="email" id="donFormEmail" placeholder="optional"></div>
            </div>
            <div class="row2">
                <div class="field"><label for="donFormPhone">Phone (256…)</label><input id="donFormPhone" placeholder="256770000000"></div>
                <div class="field"><label for="donFormReference">Reference / txn ID</label><input id="donFormReference" placeholder="Bank ref or MoMo ID"></div>
            </div>
            <div class="field"><label for="donFormMessage">Public note</label><textarea id="donFormMessage" rows="2" placeholder="Shown in records (e.g. donor message)"></textarea></div>
            <div class="field"><label for="donFormAdminNote">Admin note (internal)</label><textarea id="donFormAdminNote" rows="2" placeholder="e.g. Verified via SMS on 5 Jun — bank ref ABC123"></textarea></div>
            <label class="field-check"><input type="checkbox" id="donFormNotify"> Email donor confirmation (if email set &amp; status is success)</label>
            <footer class="admin-modal-foot">
                <button type="button" class="btn btn-ghost" data-close-donation-modal>Cancel</button>
                <button type="submit" class="btn" id="donFormSubmit"><i class="fas fa-save"></i> Save donation</button>
            </footer>
        </form>
    </div>
</div>

<!-- Detail drawer — full record view when a submission or dashboard row is clicked -->
<div class="detail-drawer" id="detailDrawer" hidden aria-hidden="true">
    <div class="detail-drawer-backdrop" data-close-detail></div>
    <aside class="detail-drawer-panel" role="dialog" aria-labelledby="detailDrawerTitle">
        <header class="detail-drawer-head">
            <div class="detail-drawer-titles">
                <p class="detail-drawer-kicker" id="detailKicker"></p>
                <h3 id="detailDrawerTitle"></h3>
            </div>
            <button type="button" class="icon-btn" data-close-detail aria-label="Close"><i class="fas fa-times"></i></button>
        </header>
        <div class="detail-drawer-body" id="detailBody"></div>
        <footer class="detail-drawer-foot" id="detailFoot"></footer>
    </aside>
</div>

<div class="admin-modal" id="emailModal" hidden aria-hidden="true">
    <div class="admin-modal-backdrop" data-close-email-modal></div>
    <div class="admin-modal-panel" role="dialog" aria-labelledby="emailModalTitle">
        <header class="admin-modal-head">
            <h3 id="emailModalTitle">Send email</h3>
            <button type="button" class="icon-btn" data-close-email-modal aria-label="Close"><i class="fas fa-times"></i></button>
        </header>
        <form id="emailForm" class="admin-modal-body">
            <input type="hidden" id="emailFormMessageId">
            <div class="field"><label for="emailFormTo">To</label><input type="email" id="emailFormTo" required></div>
            <div class="field"><label for="emailFormSubject">Subject</label><input id="emailFormSubject" required></div>
            <div class="field"><label for="emailFormBody">Message</label><textarea id="emailFormBody" rows="6" required></textarea></div>
            <p class="help">Sent from <?= htmlspecialchars($notifyEmail) ?> via your configured SMTP.</p>
            <footer class="admin-modal-foot">
                <button type="button" class="btn btn-ghost" data-close-email-modal>Cancel</button>
                <button type="submit" class="btn" id="emailFormSubmit"><i class="fas fa-paper-plane"></i> Send via SMTP</button>
            </footer>
        </form>
    </div>
</div>

<div class="toast-host" id="toastHost"></div>

<script>
(function(){
    'use strict';
    let CONTENT = {};
    let isDirty = false;
    let savedSnapshot = '';
    let DONATIONS_CACHE = [];
    let MESSAGES_CACHE = [];
    let SUBSCRIBERS_CACHE = [];
    const CSRF = <?= json_encode($csrfToken) ?>;
    const DON_PENDING = new Set(['bank_pledged', 'crypto_pledged', 'airtel_pledged', 'awaiting_approval', 'manual_pending', 'pending']);
    const DON_CONFIRMED = new Set(['success', 'completed']);
    const NOTIFY_EMAIL = <?= json_encode($notifyEmail) ?>;
    const $  = (s, c=document)=>c.querySelector(s);
    const $$ = (s, c=document)=>Array.from(c.querySelectorAll(s));

    function apiHeaders(json = true) {
        const h = {'X-CSRF-Token': CSRF};
        if (json) h['Content-Type'] = 'application/json';
        return h;
    }

    function updateUnreadBadge(count) {
        const n = Number(count) || 0;
        const badge = $('#navUnreadBadge');
        if (badge) {
            badge.hidden = n <= 0;
            badge.textContent = n > 99 ? '99+' : String(n);
        }
        const abnInbox = $('.abn-item[data-bnav="submissions"]');
        if (abnInbox) {
            let abnBadge = abnInbox.querySelector('.nav-badge');
            if (n > 0) {
                if (!abnBadge) {
                    abnBadge = document.createElement('span');
                    abnBadge.className = 'nav-badge';
                    abnInbox.appendChild(abnBadge);
                }
                abnBadge.hidden = false;
                abnBadge.textContent = n > 99 ? '99+' : String(n);
            } else if (abnBadge) {
                abnBadge.hidden = true;
            }
        }
        const sub = $('[data-stat="unreadMessages"]');
        if (sub) sub.textContent = n > 0 ? `${n} unread` : '';
    }

    const TAB_ICONS = {
        dashboard: 'fa-th-large',
        org: 'fa-building',
        hero: 'fa-image',
        stats: 'fa-chart-bar',
        programs: 'fa-hand-holding-heart',
        impact: 'fa-quote-right',
        team: 'fa-users',
        events: 'fa-calendar',
        donation: 'fa-donate',
        trust: 'fa-shield-alt',
        submissions: 'fa-inbox',
        reports: 'fa-file-export',
    };

    const TAB_HINTS = {
        dashboard: 'Overview of your site content and recent activity',
        org: 'Organization details shown across the public website',
        hero: 'Homepage hero slider and about section content',
        stats: 'Floating stats card below the hero on your site',
        programs: 'Charity programs in the homepage carousel',
        impact: 'Impact stories visitors can read and expand',
        team: 'Team members shown on the homepage',
        events: 'Events, drives, and hero promotions',
        donation: 'Donation goals, amounts, and payment details',
        trust: 'Trust bar, footer legal line, and compliance',
        submissions: 'Donations, messages, and newsletter sign-ups',
        reports: 'Accountability exports, live stats, and activity log',
    };

    function setDirty(flag = true) {
        isDirty = flag;
        const pill = $('#saveStatus');
        const saveBtn = $('#saveBtn');
        if (pill) {
            pill.textContent = flag ? 'Unsaved changes' : 'All changes saved';
            pill.classList.toggle('unsaved', flag);
        }
        if (saveBtn) saveBtn.classList.toggle('needs-save', flag);
    }

    function snapshotContent() {
        try { return JSON.stringify(CONTENT); } catch (_) { return ''; }
    }

    function markClean() {
        savedSnapshot = snapshotContent();
        setDirty(false);
    }

    function checkDirty() {
        if (savedSnapshot && snapshotContent() !== savedSnapshot) setDirty(true);
    }

    /* Mobile sidebar toggle */
    const sidebar  = $('#adminSidebar');
    const burger   = $('#adminHamburger');
    const backdrop = $('#sidebarBackdrop');
    const sidebarClose = $('#sidebarClose');
    const adminMenuOpen = $('#adminMenuOpen');
    function setSidebar(open){
        if(!sidebar) return;
        sidebar.classList.toggle('active', open);
        if(backdrop) backdrop.classList.toggle('active', open);
        document.body.style.overflow = open ? 'hidden' : '';
        if (burger) {
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            burger.innerHTML = open ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        }
    }
    if(burger)   burger.addEventListener('click', () => setSidebar(!sidebar.classList.contains('active')));
    if(backdrop) backdrop.addEventListener('click', () => setSidebar(false));
    if(sidebarClose) sidebarClose.addEventListener('click', () => setSidebar(false));
    if(adminMenuOpen) adminMenuOpen.addEventListener('click', () => setSidebar(true));
    document.addEventListener('keyup', e => { if(e.key==='Escape') setSidebar(false); });

    function syncBottomNav(tab) {
        $$('.abn-item[data-bnav]').forEach(b => {
            b.classList.toggle('active', b.getAttribute('data-bnav') === tab);
        });
    }

    function switchTab(tab, navBtn) {
        if (isDirty && !confirm('You have unsaved changes. Switch tabs anyway?')) return;
        $$('#adminNav button').forEach(x => x.classList.remove('active'));
        if (navBtn) navBtn.classList.add('active');
        $$('.tabs-content').forEach(s => {
            const on = s.getAttribute('data-tab') === tab;
            s.classList.toggle('active', on);
            if (on) {
                s.classList.remove('page-enter');
                void s.offsetWidth;
                s.classList.add('page-enter');
            }
        });
        const icon = TAB_ICONS[tab] || 'fa-circle';
        let label = tab;
        if (navBtn) {
            const clone = navBtn.cloneNode(true);
            clone.querySelector('.nav-badge')?.remove();
            label = clone.textContent.replace(/\s+/g, ' ').trim() || tab;
        }
        $('#tabTitle').innerHTML = `<i class="fas ${icon}"></i> ${esc(label)}`;
        const subEl = $('#tabSubtitle');
        if (subEl) subEl.textContent = TAB_HINTS[tab] || '';
        syncBottomNav(tab);
        if (tab === 'submissions') loadSubmissions();
        if (tab === 'dashboard')   loadDashboard();
        if (tab === 'reports')     loadReports();
        if (window.innerWidth <= 1024) setSidebar(false);
        const main = $('.main');
        if (main) main.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function navigateAdmin(tab, opts = {}) {
        const navBtn = $(`#adminNav button[data-tab="${tab}"]`);
        if (!navBtn) return false;
        switchTab(tab, navBtn);
        if (opts.inboxPanel) switchInboxPanel(opts.inboxPanel);
        return true;
    }

    async function openDashDonation(id) {
        navigateAdmin('submissions', { inboxPanel: 'inbox-donations' });
        let rec = DONATIONS_CACHE.find(d => d.id === id);
        if (!rec) { await loadSubmissions(); rec = DONATIONS_CACHE.find(d => d.id === id); }
        if (rec) setTimeout(() => showDonationDetail(rec), 80);
    }

    async function openDashMessage(id) {
        navigateAdmin('submissions', { inboxPanel: 'inbox-messages' });
        let rec = MESSAGES_CACHE.find(m => m.id === id);
        if (!rec) { await loadSubmissions(); rec = MESSAGES_CACHE.find(m => m.id === id); }
        if (rec) setTimeout(() => showMessageDetail(rec), 80);
    }

    function switchInboxPanel(panelId) {
        $$('.inbox-tab').forEach(t => {
            const on = t.getAttribute('data-inbox-panel') === panelId;
            t.classList.toggle('active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        $$('.inbox-panel').forEach(p => {
            const on = p.id === panelId;
            p.classList.toggle('active', on);
            p.hidden = !on;
        });
        const markBtn = $('#markAllReadBtn');
        if (markBtn) markBtn.style.display = panelId === 'inbox-messages' ? '' : 'none';
    }

    function bindInboxTabs() {
        const host = $('#inboxTabs');
        if (!host) return;
        host.addEventListener('click', e => {
            const tab = e.target.closest('.inbox-tab');
            if (!tab) return;
            switchInboxPanel(tab.getAttribute('data-inbox-panel'));
        });
        switchInboxPanel('inbox-donations');
    }

    function filterTableRows(host, query) {
        if (!host) return;
        const q = String(query || '').trim().toLowerCase();
        $$('tbody tr', host).forEach(row => {
            row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
        $$('.record-card', host).forEach(card => {
            card.style.display = !q || card.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    function bindInboxSearch() {
        const pairs = [
            ['#searchDonations', '#donationsTable'],
            ['#searchMessages', '#messagesTable'],
            ['#searchSubscribers', '#subscribersTable'],
        ];
        pairs.forEach(([inputSel, tableHostSel]) => {
            const input = $(inputSel);
            const host = $(tableHostSel);
            if (!input || !host) return;
            input.addEventListener('input', () => filterTableRows(host, input.value));
        });
    }

    function updateSmtpStatus(body) {
        const pill = $('#smtpStatusPill');
        const label = $('#smtpNotifyLabel');
        if (label && body.notifyEmail) label.textContent = body.notifyEmail;
        if (!pill) return;
        if (body.smtpConfigured) {
            pill.textContent = 'SMTP ready';
            pill.className = 'smtp-pill smtp-ok';
            pill.title = 'From: ' + (body.smtpFrom || NOTIFY_EMAIL);
        } else {
            pill.textContent = 'SMTP not configured';
            pill.className = 'smtp-pill smtp-warn';
            pill.title = 'Add smtp.local.php credentials';
        }
    }

    function openEmailModal(opts = {}) {
        const modal = $('#emailModal');
        if (!modal) return;
        $('#emailModalTitle').textContent = opts.title || 'Send email';
        $('#emailFormMessageId').value = opts.messageId || '';
        $('#emailFormTo').value = opts.to || '';
        $('#emailFormSubject').value = opts.subject || '';
        $('#emailFormBody').value = opts.body || '';
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        ($('#emailFormBody').value ? $('#emailFormSubject') : $('#emailFormTo'))?.focus();
    }

    function closeEmailModal() {
        const modal = $('#emailModal');
        if (!modal || modal.hidden) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    async function sendAdminEmail(payload) {
        const res = await fetch('api/admin_send_email.php', {
            method: 'POST',
            headers: apiHeaders(),
            body: JSON.stringify(payload),
        });
        const body = await res.json().catch(() => ({}));
        if (res.status === 401) { location.reload(); return null; }
        if (!res.ok || !body.success) {
            throw new Error(body.message || 'Email failed');
        }
        return body;
    }

    function updateInboxCounts(body) {
        const d = (body.donations || []).length;
        const m = (body.messages || []).length;
        const s = (body.subscribers || []).length;
        const u = Number(body.unreadMessages) || 0;
        const p = Number(body.pendingDonations) || 0;
        const set = (id, val) => { const el = $(id); if (el) el.textContent = String(val); };
        set('#inboxDonCount', d);
        set('#inboxMsgCount', m);
        set('#inboxSubCount', s);
        const unread = $('#inboxUnreadCount');
        if (unread) {
            unread.hidden = u <= 0;
            unread.textContent = u > 99 ? '99+' : String(u);
        }
        const pending = $('#inboxDonPending');
        if (pending) {
            pending.hidden = p <= 0;
            pending.textContent = p > 99 ? '99+' : String(p);
            pending.title = p > 0 ? `${p} donation(s) need confirmation` : '';
        }
    }

    function donationNeedsAction(d) {
        return DON_PENDING.has(String(d?.status || '').toLowerCase());
    }

    function openDonationModal(mode, record) {
        const modal = $('#donationModal');
        if (!modal) return;
        const isCreate = mode === 'create';
        $('#donationModalTitle').textContent = isCreate ? 'Record manual donation' : 'Edit donation';
        $('#donFormSubmit').innerHTML = isCreate
            ? '<i class="fas fa-plus"></i> Add donation'
            : '<i class="fas fa-save"></i> Save donation';
        $('#donFormId').value = isCreate ? '' : (record?.id || '');
        $('#donFormAmount').value = isCreate ? '' : (record?.amount ?? '');
        $('#donFormCurrency').value = isCreate
            ? String((CONTENT.donation && CONTENT.donation.currency) || 'UGX').toUpperCase()
            : (record?.currency || '');
        $('#donFormMethod').value = isCreate ? 'manual' : (record?.method || 'manual');
        $('#donFormStatus').value = isCreate ? 'success' : (record?.status || 'pending');
        $('#donFormName').value = record?.name || '';
        $('#donFormEmail').value = record?.email || '';
        $('#donFormPhone').value = record?.phone || '';
        $('#donFormReference').value = record?.reference || '';
        $('#donFormMessage').value = record?.message || '';
        $('#donFormAdminNote').value = record?.adminNote || '';
        $('#donFormNotify').checked = isCreate || donationNeedsAction(record);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        $('#donFormAmount')?.focus();
    }

    function closeDonationModal() {
        const modal = $('#donationModal');
        if (!modal || modal.hidden) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    async function donationApi(payload) {
        const res = await fetch('api/admin_donations.php', {
            method: 'POST',
            headers: apiHeaders(),
            body: JSON.stringify(payload),
        });
        const body = await res.json().catch(() => ({}));
        if (res.status === 401) { location.reload(); return null; }
        if (!res.ok || !body.success) {
            throw new Error(body.message || 'Donation update failed');
        }
        return body;
    }

    async function confirmDonation(id, notifyDonor = true) {
        const note = prompt('Optional admin note (e.g. verified via bank SMS):', 'Confirmed manually by admin.');
        if (note === null) return;
        try {
            await donationApi({
                action: 'confirm',
                id,
                adminNote: note.trim(),
                notifyDonor,
            });
            toast('Donation confirmed');
            loadSubmissions();
            if ($('.tabs-content.active')?.getAttribute('data-tab') === 'dashboard') loadDashboard();
        } catch (err) {
            toast(err.message || 'Could not confirm donation', 'error');
        }
    }

    async function deleteDonation(id) {
        if (!confirm('Delete this donation record permanently?')) return;
        try {
            await donationApi({ action: 'delete', id });
            toast('Donation deleted');
            loadSubmissions();
            if ($('.tabs-content.active')?.getAttribute('data-tab') === 'dashboard') loadDashboard();
        } catch (err) {
            toast(err.message || 'Could not delete donation', 'error');
        }
    }

    function fmtDateShort(iso) {
        try {
            return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        } catch (_) { return iso || ''; }
    }

    function fmtDateFull(iso) {
        try {
            return new Date(iso).toLocaleString(undefined, {
                weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
                hour: '2-digit', minute: '2-digit',
            });
        } catch (_) { return iso || '—'; }
    }

    function openDetailDrawer({ kicker, title, bodyHtml, footHtml }) {
        const drawer = $('#detailDrawer');
        if (!drawer) return;
        $('#detailKicker').textContent = kicker || '';
        $('#detailDrawerTitle').textContent = title || '';
        $('#detailBody').innerHTML = bodyHtml || '';
        const foot = $('#detailFoot');
        if (foot) foot.innerHTML = footHtml || '';
        drawer.hidden = false;
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => drawer.classList.add('is-open'));
    }

    function closeDetailDrawer() {
        const drawer = $('#detailDrawer');
        if (!drawer || drawer.hidden) return;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        setTimeout(() => { drawer.hidden = true; }, 280);
    }

    function detailGrid(items) {
        return `<dl class="detail-grid">${items.map(([label, value]) =>
            `<div class="detail-item"><dt>${esc(label)}</dt><dd>${value}</dd></div>`
        ).join('')}</dl>`;
    }

    function renderStatusHistory(d) {
        const hist = Array.isArray(d.statusHistory) ? d.statusHistory : [];
        if (!hist.length) return '';
        const items = [...hist].reverse().map(h => `
            <li class="status-timeline-item">
                <span class="status-badge s-${esc(h.status || '')}">${esc(h.status || '')}</span>
                <span class="status-timeline-meta">${esc(fmtDateShort(h.at))} · ${esc(h.by || '')}</span>
                ${h.note ? `<span class="status-timeline-note">${esc(h.note)}</span>` : ''}
            </li>`).join('');
        return `<div class="detail-item status-timeline-wrap"><dt>Status history</dt><dd><ul class="status-timeline">${items}</ul></dd></div>`;
    }

    function showDonationDetail(d) {
        if (!d) return;
        const pending = donationNeedsAction(d);
        const note = [d.adminNote, d.message].filter(Boolean).join('\n\n');
        const canPollMomo = String(d.method || '').toLowerCase() === 'momo'
            && String(d.status || '').toLowerCase() === 'awaiting_approval'
            && d.reference;
        const bodyHtml = `
            <div class="detail-amount-hero">
                <strong>${esc(d.currency || '')} ${esc(d.amount)}</strong>
                <span>${esc(d.method || '—')}${d.source === 'manual' ? ' · manual entry' : ''}</span>
            </div>
            <div class="detail-status-row">
                <span class="status-badge s-${esc(d.status || '')}">${esc(d.status || '—')}</span>
                ${d.momoStatus ? `<span class="record-date">MoMo: ${esc(d.momoStatus)}</span>` : ''}
                ${pending ? '<span class="unread-pill">Needs action</span>' : ''}
            </div>
            ${detailGrid([
                ['Date', esc(fmtDateFull(d.createdAt))],
                ['Updated', d.updatedAt ? esc(fmtDateFull(d.updatedAt)) : '—'],
                ['Donor', esc(d.name || 'Anonymous')],
                ['Email', d.email ? `<a href="mailto:${esc(d.email)}">${esc(d.email)}</a>` : '—'],
                ['Phone', esc(d.phone || '—')],
                ['Reference / txn ID', esc(d.reference || '—')],
                ['Record ID', `<code style="font-size:.76rem">${esc(d.id || '—')}</code>`],
                ['IP / source', esc(d.ip || '—') + (d.source ? ` · ${esc(d.source)}` : '')],
            ])}
            ${renderStatusHistory(d)}
            ${note ? `<div class="detail-item" style="margin-top:12px"><dt>Notes</dt><dd class="detail-message-box" style="margin-top:6px;border-left-color:var(--accent)">${esc(note)}</dd></div>` : ''}`;
        const footHtml = `
            ${canPollMomo ? `<button type="button" class="btn btn-sm" data-detail-don-poll="${esc(d.id)}"><i class="fas fa-sync"></i> Check MoMo status</button>` : ''}
            ${pending ? `<button type="button" class="btn btn-sm" data-detail-don-confirm="${esc(d.id)}"><i class="fas fa-check"></i> Confirm received</button>` : ''}
            <button type="button" class="btn btn-ghost btn-sm" data-detail-don-edit="${esc(d.id)}"><i class="fas fa-pen"></i> Edit</button>
            <button type="button" class="btn btn-ghost btn-sm danger-text" data-detail-don-delete="${esc(d.id)}"><i class="fas fa-trash"></i> Delete</button>`;
        openDetailDrawer({ kicker: 'Donation', title: `${d.currency || ''} ${d.amount} — ${d.name || 'Anonymous'}`, bodyHtml, footHtml });
    }

    function showMessageDetail(m) {
        if (!m) return;
        const unread = !m.read;
        const bodyHtml = `
            <div class="detail-status-row">
                ${unread ? '<span class="unread-pill">Unread</span>' : '<span class="status-badge s-success">Read</span>'}
            </div>
            ${detailGrid([
                ['Date', esc(fmtDateFull(m.createdAt))],
                ['From', `<strong>${esc(m.name)}</strong>`],
                ['Email', `<a href="mailto:${esc(m.email)}">${esc(m.email)}</a>`],
            ])}
            <div class="detail-item" style="margin-top:14px">
                <dt>Message</dt>
                <dd class="detail-message-box" style="margin-top:8px">${esc(m.message || '—')}</dd>
            </div>`;
        const footHtml = `
            <button type="button" class="btn btn-sm" data-detail-smtp-reply="${esc(m.id)}" data-email="${esc(m.email)}" data-name="${esc(m.name)}"><i class="fas fa-paper-plane"></i> Reply via SMTP</button>
            <a href="mailto:${esc(m.email)}?subject=${encodeURIComponent('Re: Your message to Tattu Care')}" class="btn btn-ghost btn-sm"><i class="fas fa-reply"></i> Mail app</a>
            ${unread ? `<button type="button" class="btn btn-ghost btn-sm" data-detail-mark-read="${esc(m.id)}"><i class="fas fa-check"></i> Mark read</button>` : ''}`;
        openDetailDrawer({ kicker: 'Contact message', title: m.name || 'Message', bodyHtml, footHtml });
    }

    function showSubscriberDetail(s) {
        if (!s) return;
        const bodyHtml = detailGrid([
            ['Subscribed', esc(fmtDateFull(s.createdAt))],
            ['Email', `<a href="mailto:${esc(s.email)}">${esc(s.email)}</a>`],
        ]);
        const footHtml = `
            <a href="mailto:${esc(s.email)}" class="btn btn-sm"><i class="fas fa-envelope"></i> Send email</a>
            <button type="button" class="btn btn-ghost btn-sm" data-detail-copy="${esc(s.email)}"><i class="fas fa-copy"></i> Copy email</button>`;
        openDetailDrawer({ kicker: 'Newsletter subscriber', title: s.email || 'Subscriber', bodyHtml, footHtml });
    }

    function renderDonationCards(donations) {
        return `<div class="record-list">${donations.map(d => {
            const pending = donationNeedsAction(d);
            const note = [d.adminNote, d.message].filter(Boolean).join(' · ');
            return `
            <article class="record-card${pending ? ' record-unread' : ''}" data-open-donation="${esc(d.id)}" tabindex="0" role="button" aria-label="View donation from ${esc(d.name || 'Anonymous')}">
                <div class="record-card-main">
                    <div class="record-card-top">
                        <span class="record-date">${esc(fmtDateShort(d.createdAt))}</span>
                        <span class="status-badge s-${esc(d.status || '')}">${esc(d.status || '')}</span>
                    </div>
                    <div class="record-card-title">${esc(d.currency || '')} ${esc(d.amount)}</div>
                    <div class="record-card-sub">${esc(d.name || 'Anonymous')} · ${esc(d.method || '—')}${d.source === 'manual' ? ' · manual' : ''}</div>
                    ${note ? `<p class="record-card-preview">${esc(note)}</p>` : ''}
                    <span class="record-card-hint"><i class="fas fa-expand-alt"></i> Tap to view full details</span>
                </div>
                <div class="record-card-actions">
                    ${pending ? `<button type="button" class="icon-btn don-confirm" title="Confirm" data-don-confirm="${esc(d.id)}"><i class="fas fa-check"></i></button>` : ''}
                    <button type="button" class="icon-btn" title="Edit" data-don-edit="${esc(d.id)}"><i class="fas fa-pen"></i></button>
                </div>
            </article>`;
        }).join('')}</div>`;
    }

    function renderMessageCards(messages) {
        return `<div class="record-list">${messages.map(m => {
            const unread = !m.read;
            return `
            <article class="record-card${unread ? ' record-unread' : ''}" data-open-message="${esc(m.id)}" tabindex="0" role="button">
                <div class="record-card-main">
                    <div class="record-card-top">
                        <span class="record-date">${esc(fmtDateShort(m.createdAt))}</span>
                        ${unread ? '<span class="unread-pill">New</span>' : '<span class="record-date">Read</span>'}
                    </div>
                    <div class="record-card-title">${esc(m.name)}</div>
                    <div class="record-card-sub">${esc(m.email)}</div>
                    <p class="record-card-preview">${esc(m.message || '')}</p>
                    <span class="record-card-hint"><i class="fas fa-expand-alt"></i> Tap to read full message</span>
                </div>
                <div class="record-card-actions">
                    <button type="button" class="icon-btn" title="Reply via SMTP" data-smtp-reply="${esc(m.id)}" data-email="${esc(m.email)}" data-name="${esc(m.name)}"><i class="fas fa-paper-plane"></i></button>
                </div>
            </article>`;
        }).join('')}</div>`;
    }

    function renderSubscriberCards(subscribers) {
        return `<div class="record-list">${subscribers.map(s => `
            <article class="record-card" data-open-subscriber="${esc(s.email)}" data-sub-date="${esc(s.createdAt || '')}" tabindex="0" role="button">
                <div class="record-card-main">
                    <div class="record-card-top"><span class="record-date">${esc(fmtDateShort(s.createdAt))}</span></div>
                    <div class="record-card-title">${esc(s.email)}</div>
                    <span class="record-card-hint"><i class="fas fa-expand-alt"></i> Tap for details</span>
                </div>
                <div class="record-card-actions">
                    <a href="mailto:${esc(s.email)}" class="icon-btn" title="Email"><i class="fas fa-envelope"></i></a>
                </div>
            </article>`).join('')}</div>`;
    }
    // Close sidebar when a nav button is tapped on mobile
    $$('#adminNav button').forEach(b => b.addEventListener('click', () => {
        if(window.innerWidth <= 1024) setSidebar(false);
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
        el.innerHTML = type === 'success' && msg.includes('Published')
            ? `<strong>${esc(msg)}</strong>`
            : esc(msg);
        $('#toastHost').appendChild(el);
        setTimeout(()=>{el.style.opacity='0'; el.style.transition='opacity .3s'; setTimeout(()=>el.remove(),300)}, 4000);
    }

    const TEMPLATES = {
        stats:        { enabled: true, icon:'fa-users',   number:'0',  label:'Label' },
        programs:     { icon:'fa-hand-holding-heart', title:'New Program', description:'', stats:'', image:'' },
        impactStories:{ title:'New Story', text:'', quote:'', quoteAuthor:'', image:'' },
        team:         { name:'Team Member', role:'', bio:'', image:'' },
        events:       { title:'New Event', date:'', startDate:'', endDate:'', location:'', status:'upcoming', featured:false, featureOnHero:true, heroTitle:'', heroSubtitle:'', image:'', description:'', items:[], ctaText:'Volunteer', ctaLink:'#contact' },
        heroSlides:   { title:'New headline', subtitle:'A short supporting line', image:'' },
        aboutSlides:  { paragraphs: [], image:'' },
        trustPartners: { name: 'Partner name', url: '', logo: '' },
    };
    const FIELDS = {
        stats: [
            ['enabled','Show on site','checkbox'],
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
            ['date','Display date (e.g., April 15-30, 2026)','input'],
            ['startDate','Start date (YYYY-MM-DD)','input'],
            ['endDate','End date (YYYY-MM-DD)','input'],
            ['location','Location','input'],
            ['status','Status','select'],
            ['featured','Featured (priority)','checkbox'],
            ['featureOnHero','Feature on Hero','checkbox'],
            ['heroTitle','Hero headline (optional)','input'],
            ['heroSubtitle','Hero subtitle (optional)','textarea'],
            ['image','Hero / event image','input'],
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
        trustPartners: [
            ['name','Partner name','input'],
            ['url','Website URL (optional)','input'],
            ['logo','Logo URL (optional)','input'],
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
            if (type === 'checkbox') {
                const checked = v ? ' checked' : '';
                return `<div class="field field-check"><label for="${id}"><input type="checkbox" id="${id}" data-li="${liKey}" data-as="checkbox"${checked}> ${label}</label></div>`;
            }
            if (type === 'select') {
                const opts = { upcoming:'Upcoming', ongoing:'Ongoing', archived:'Archived' };
                const optsHtml = Object.entries(opts).map(([val, lab]) =>
                    `<option value="${val}"${v === val ? ' selected' : ''}>${lab}</option>`).join('');
                return `<div class="field"><label for="${id}">${label}</label><select id="${id}" data-li="${liKey}">${optsHtml}</select></div>`;
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
        // 1-line snippet (description / role / date) shown when collapsed
        const snippetSrc = item.description || item.role || item.text || item.date || item.bio || '';
        const snippet = String(snippetSrc).slice(0, 80);
        const titleText = item.title || item.name || item.label || ('Item ' + (idx + 1));
        return `
            <div class="list-item collapsible" data-row="${listKey}|${idx}">
                <div class="list-item-head">
                    <div class="li-title">
                        <span class="li-index">${idx + 1}</span>
                        <i class="fas fa-chevron-right chevron"></i>
                        <strong>${esc(titleText)}</strong>
                        ${snippet ? `<span class="li-snippet">${esc(snippet)}${snippetSrc.length > 80 ? '…' : ''}</span>` : ''}
                    </div>
                    <div class="list-item-actions">
                        <button class="icon-btn"        title="Move up"   data-move="${listKey}|${idx}|-1"><i class="fas fa-arrow-up"></i></button>
                        <button class="icon-btn"        title="Move down" data-move="${listKey}|${idx}|1"><i class="fas fa-arrow-down"></i></button>
                        <button class="icon-btn danger" title="Delete"   data-del="${listKey}|${idx}"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="list-item-body">
                    <div class="list-item-fields">${inner}</div>
                </div>
            </div>`;
    }

    function renderList(listKey){
        const host = $(`[data-list="${listKey}"]`);
        if (!host) return;
        const arr = Array.isArray(CONTENT[listKey]) ? CONTENT[listKey] : [];
        if (!arr.length) {
            host.innerHTML = '<div class="empty-state"><i class="fas fa-layer-group"></i><p>No items yet</p><span>Click <strong>+ Add</strong> above to create one.</span></div>';
            return;
        }
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
            if (as === 'stringList' && Array.isArray(v)) v = v.join(', ');
            if (as === 'bool') v = v === false ? 'false' : 'true';
            if (el.type === 'checkbox' || as === 'checkbox') { el.checked = !!v; return; }
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
        const res  = await fetch('api/admin_upload.php', { method: 'POST', headers: apiHeaders(false), body: fd });
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

    // Track unsaved edits
    document.addEventListener('input', e => {
        if (e.target.matches('[data-bind], [data-li]')) checkDirty();
    });
    document.addEventListener('change', e => {
        if (e.target.matches('[data-bind], [data-li], [data-image-upload], [data-image-upload-li]')) checkDirty();
    });
    window.addEventListener('beforeunload', e => {
        if (isDirty) { e.preventDefault(); e.returnValue = ''; }
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
            let v;
            if (el.type === 'checkbox' || as === 'checkbox') v = el.checked;
            else v = el.value;
            if (as === 'paragraphs') v = v.split(/\n\s*\n/).map(s=>s.trim()).filter(Boolean);
            else if (as === 'numberList') v = v.split(',').map(s=>Number(s.trim())).filter(n=>!isNaN(n) && n>0);
            else if (as === 'stringList') v = v.split(',').map(s=>s.trim().toUpperCase()).filter(Boolean);
            else if (as === 'bool') v = v === 'true' || v === true;
            else if (el.type === 'number') v = v === '' ? 0 : Number(v);
            setPath(CONTENT, path, v);
        });
        $$('[data-li]').forEach(el => {
            const [listKey, idx, key] = el.getAttribute('data-li').split('|');
            const i = Number(idx);
            const arr = CONTENT[listKey] || (CONTENT[listKey] = []);
            if (!arr[i]) arr[i] = {};
            let v;
            if (el.type === 'checkbox' || el.getAttribute('data-as') === 'checkbox') v = el.checked;
            else v = el.value;
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
        b.addEventListener('click', () => switchTab(b.getAttribute('data-tab'), b));
    });

    $$('.abn-item[data-bnav]').forEach(b => {
        b.addEventListener('click', () => {
            const tab = b.getAttribute('data-bnav');
            const navBtn = $(`#adminNav button[data-tab="${tab}"]`);
            if (navBtn) switchTab(tab, navBtn);
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
            setDirty(true);
            // Auto-expand the new (last) item so admin can edit immediately
            requestAnimationFrame(() => {
                const items = $$(`[data-list="${k}"] .list-item.collapsible`);
                if (items.length) items[items.length - 1].classList.add('expanded');
            });
            return;
        }
        const del = e.target.closest('[data-del]');
        if (del) {
            const [k, idx] = del.getAttribute('data-del').split('|');
            if (!confirm('Delete this item?')) return;
            collectFields();
            CONTENT[k].splice(Number(idx), 1);
            renderList(k);
            setDirty(true);
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
            setDirty(true);
            return;
        }

        // Toggle accordion — one item open at a time per list
        const head = e.target.closest('.list-item.collapsible .list-item-head');
        if (head && !e.target.closest('.list-item-actions')) {
            const item = head.parentElement;
            const list = item.closest('[data-list]');
            const willExpand = !item.classList.contains('expanded');
            if (list) $$('.list-item.collapsible.expanded', list).forEach(li => li.classList.remove('expanded'));
            if (willExpand) item.classList.add('expanded');
            return;
        }

        // Dashboard & links: stay inside admin
        const goTab = e.target.closest('[data-go-tab]');
        if (goTab) {
            e.preventDefault();
            navigateAdmin(goTab.getAttribute('data-go-tab'), {
                inboxPanel: goTab.getAttribute('data-inbox-panel') || '',
            });
            return;
        }

        const dashDon = e.target.closest('[data-dash-donation]');
        if (dashDon) {
            e.preventDefault();
            openDashDonation(dashDon.getAttribute('data-dash-donation'));
            return;
        }
        const dashMsg = e.target.closest('[data-dash-message]');
        if (dashMsg) {
            e.preventDefault();
            openDashMessage(dashMsg.getAttribute('data-dash-message'));
            return;
        }
    });

    $('#saveBtn').addEventListener('click', async () => {
        collectFields();
        // Mirror shadow lists into nested storage paths
        if (Array.isArray(CONTENT.heroSlides))  { (CONTENT.hero  ||= {}).slides = CONTENT.heroSlides;  delete CONTENT.heroSlides; }
        if (Array.isArray(CONTENT.aboutSlides)) { (CONTENT.about ||= {}).slides = CONTENT.aboutSlides; delete CONTENT.aboutSlides; }
        if (Array.isArray(CONTENT.trustPartners)) { (CONTENT.trust ||= {}).partners = CONTENT.trustPartners; delete CONTENT.trustPartners; }
        const btn = $('#saveBtn');
        btn.disabled = true; const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        try {
            const res  = await fetch('api/admin_save.php', {
                method:'POST', headers: apiHeaders(),
                body: JSON.stringify(CONTENT)
            });
            const body = await res.json().catch(()=>({}));
            if (res.ok && body.success) {
                toast('Published to website — visitors will see your updates.');
                markClean();
            }
            else if (res.status === 401) { location.reload(); }
            else { toast(body.message || 'Save failed', 'error'); }
        } catch (err) { toast('Network error', 'error'); }
        finally { btn.disabled = false; btn.innerHTML = orig; }
    });

    $('#logoutBtn').addEventListener('click', async () => {
        await fetch('api/admin_logout.php', { method:'POST', headers: apiHeaders() });
        location.reload();
    });

    async function markMessagesRead(ids) {
        const payload = (ids && ids.length)
            ? { action: 'mark_read', ids }
            : { action: 'mark_all_read' };
        const res = await fetch('api/admin_messages.php', {
            method: 'POST',
            headers: apiHeaders(),
            body: JSON.stringify(payload)
        });
        const body = await res.json().catch(() => ({}));
        if (res.status === 401) { location.reload(); return; }
        if (!res.ok || !body.success) {
            toast(body.message || 'Could not update messages', 'error');
            return;
        }
        updateUnreadBadge(body.unreadMessages);
        loadSubmissions();
        loadDashboard();
        toast(ids ? 'Message marked as read.' : 'All messages marked as read.');
    }

    const markAllBtn = $('#markAllReadBtn');
    if (markAllBtn) markAllBtn.addEventListener('click', () => markMessagesRead([]));

    const refreshSubBtn = $('#refreshSubmissions');
    if (refreshSubBtn) refreshSubBtn.addEventListener('click', () => loadSubmissions());

    bindInboxTabs();
    bindInboxSearch();

    document.addEventListener('click', e => {
        const readBtn = e.target.closest('[data-mark-read]');
        if (readBtn) {
            markMessagesRead([readBtn.getAttribute('data-mark-read')]);
        }
    });

    function countUpcomingEvents() {
        const now = new Date(); now.setHours(0, 0, 0, 0);
        return (CONTENT.events || []).filter(ev => {
            if (ev.status === 'archived') return false;
            const end = ev.endDate ? new Date(ev.endDate + 'T23:59:59') : null;
            if (end && end < now && ev.status !== 'ongoing') return false;
            return true;
        }).length;
    }

    const syncBtn = $('#syncRaisedBtn');
    async function syncRaisedFromDonations(showPublishHint = true) {
        try {
            const body = await donationApi({ action: 'sync_raised' });
            if (!body) return null;
            if (!CONTENT.donation) CONTENT.donation = {};
            CONTENT.donation.raised = body.raised;
            bindFields();
            if (showPublishHint) {
                setDirty(true);
                toast(`Raised synced to ${body.stats?.currency || ''} ${Number(body.raised).toLocaleString()} — click Save to publish.`);
            } else {
                toast(body.message || 'Raised total synced.');
            }
            return body;
        } catch (err) {
            toast(err.message || 'Could not sync raised total', 'error');
            return null;
        }
    }
    if (syncBtn) syncBtn.addEventListener('click', () => syncRaisedFromDonations(true));

    async function loadSubmissions(){
        const refreshBtn = $('#refreshSubmissions');
        if (refreshBtn) refreshBtn.classList.add('is-spinning');
        try {
            const res  = await fetch('api/admin_data.php');
            if (res.status === 401) { location.reload(); return; }
            const body = await res.json();
            updateUnreadBadge(body.unreadMessages);
            updateInboxCounts(body);
            updateSmtpStatus(body);

            DONATIONS_CACHE = body.donations || [];
            MESSAGES_CACHE = body.messages || [];
            SUBSCRIBERS_CACHE = body.subscribers || [];

            const dt = $('#donationsTable');
            if (!DONATIONS_CACHE.length) {
                dt.innerHTML = '<div class="empty-state"><i class="fas fa-donate"></i><p>No donations yet</p><span>They appear when visitors donate on the site, or use Record manual donation.</span></div>';
            } else {
                dt.innerHTML = renderDonationCards(DONATIONS_CACHE);
            }

            const mt = $('#messagesTable');
            if (!MESSAGES_CACHE.length) {
                mt.innerHTML = '<div class="empty-state"><i class="fas fa-envelope-open"></i><p>No messages yet</p><span>Contact form submissions show up here.</span></div>';
            } else {
                mt.innerHTML = renderMessageCards(MESSAGES_CACHE);
            }

            const st = $('#subscribersTable');
            if (!SUBSCRIBERS_CACHE.length) {
                st.innerHTML = '<div class="empty-state"><i class="fas fa-paper-plane"></i><p>No subscribers yet</p><span>Newsletter sign-ups from the website.</span></div>';
            } else {
                st.innerHTML = renderSubscriberCards(SUBSCRIBERS_CACHE);
            }

            filterTableRows($('#donationsTable'), $('#searchDonations')?.value);
            filterTableRows($('#messagesTable'), $('#searchMessages')?.value);
            filterTableRows($('#subscribersTable'), $('#searchSubscribers')?.value);
        } catch (err) {
            toast('Could not load submissions', 'error');
        } finally {
            if (refreshBtn) refreshBtn.classList.remove('is-spinning');
        }
    }

    /* Dashboard: counts + recent activity */
    function renderActivityLog(items) {
        const host = $('#activityLog');
        if (!host) return;
        if (!items || !items.length) {
            host.innerHTML = '<div class="empty-state"><i class="fas fa-history"></i><p>No activity yet</p><span>Donations, exports, and sync actions appear here.</span></div>';
            return;
        }
        host.innerHTML = `<div class="activity-log">${items.map(a => {
            const data = a.data ? JSON.stringify(a.data) : '';
            return `<article class="activity-row">
                <span class="activity-type">${esc(a.type || '')}</span>
                <span class="activity-when">${esc(fmtDateShort(a.at))}</span>
                <span class="activity-by">${esc(a.by || '')}</span>
                ${data ? `<span class="activity-data">${esc(data.length > 120 ? data.slice(0, 120) + '…' : data)}</span>` : ''}
            </article>`;
        }).join('')}</div>`;
    }

    function updateReportsKpi(ls) {
        if (!ls) return;
        const cur = ls.raisedCurrency || 'USD';
        const set = (key, val) => { const el = $(`[data-rpt="${key}"]`); if (el) el.textContent = val; };
        set('confirmedRaised', `${cur} ${Number(ls.raised || 0).toLocaleString()}`);
        set('pendingRaised', `${cur} ${Number(ls.pendingRaised || 0).toLocaleString()}`);
        set('confirmedCount', String(ls.confirmedDonations ?? '0'));
        set('pendingCount', String(ls.pendingDonations ?? '0'));
        set('messagesTotal', String(ls.messagesTotal ?? '0'));
        set('subscribersTotal', String(ls.subscribersTotal ?? '0'));
        const gen = $('#reportsGeneratedAt');
        if (gen && ls.generatedAt) {
            gen.textContent = 'Updated ' + fmtDateShort(ls.generatedAt);
        }
    }

    async function loadReports() {
        try {
            const res = await fetch('api/admin_data.php');
            if (res.status === 401) { location.reload(); return; }
            const b = await res.json();
            updateReportsKpi(b.liveStats);
            renderActivityLog(b.activity || []);
        } catch (err) {
            toast('Could not load reports', 'error');
        }
    }

    async function loadDashboard(){
        // Content-driven counts (already in CONTENT)
        const counts = {
            programs:      (CONTENT.programs || []).length,
            impactStories: (CONTENT.impactStories || []).length,
            team:          (CONTENT.team || []).length,
            events:        (CONTENT.events || []).length,
        };
        Object.entries(counts).forEach(([k, v]) => {
            const el = $(`[data-stat="${k}"]`);
            if (el) el.textContent = String(v);
        });
        const upEl = $('[data-stat="eventsUpcoming"]');
        if (upEl) {
            const up = countUpcomingEvents();
            upEl.textContent = up ? `${up} upcoming` : '';
        }

        // Submissions data (donations / messages / subscribers)
        try {
            const res = await fetch('api/admin_data.php');
            if (res.status === 401) { location.reload(); return; }
            const b = await res.json();
            DONATIONS_CACHE = b.donations || [];
            MESSAGES_CACHE = b.messages || [];
            SUBSCRIBERS_CACHE = b.subscribers || [];

            $('[data-stat="donations"]').textContent   = DONATIONS_CACHE.length;
            $('[data-stat="messages"]').textContent    = MESSAGES_CACHE.length;
            $('[data-stat="subscribers"]').textContent = SUBSCRIBERS_CACHE.length;
            updateUnreadBadge(b.unreadMessages);

            const ls = b.liveStats || {};
            const baseCur = ls.raisedCurrency || String((CONTENT.donation && CONTENT.donation.currency) || 'USD').toUpperCase();
            const raised = Number(ls.raised) || 0;
            const rEl = $('[data-stat="raised"]');
            if (rEl) rEl.textContent = raised > 0 ? `${baseCur} ${Math.round(raised).toLocaleString()}` : '';

            // Recent donations (top 5)
            const dHost = $('#dashRecentDonations');
            if (dHost) {
                const recent = (b.donations || []).slice(0, 5);
                if (!recent.length) {
                    dHost.innerHTML = '<p class="empty">No donations yet.</p>';
                } else {
                    dHost.innerHTML = '<div class="dash-recent">' + recent.map(d => `
                        <button type="button" class="row dash-row-clickable${donationNeedsAction(d) ? ' row-unread' : ''}" data-dash-donation="${esc(d.id)}" title="View full donation details">
                            <span class="when">${esc(new Date(d.createdAt).toLocaleDateString())}</span>
                            <span class="who">
                                <strong>${esc(d.name || 'Anonymous')}</strong>
                                <small>${esc(d.method || 'momo')} · <span class="status-badge s-${esc(d.status || '')}">${esc(d.status || '')}</span></small>
                            </span>
                            <span class="amount">${esc(d.currency || '')} ${esc(d.amount)} <i class="fas fa-chevron-right dash-row-go" aria-hidden="true"></i></span>
                        </button>`).join('') + '</div>';
                }
            }
            // Recent messages (top 5)
            const mHost = $('#dashRecentMessages');
            if (mHost) {
                const recent = (b.messages || []).slice(0, 5);
                if (!recent.length) {
                    mHost.innerHTML = '<p class="empty">No messages yet.</p>';
                } else {
                    mHost.innerHTML = '<div class="dash-recent">' + recent.map(m => `
                        <button type="button" class="row dash-row-clickable${!m.read ? ' row-unread' : ''}" data-dash-message="${esc(m.id)}" title="Read full message">
                            <span class="when">${esc(new Date(m.createdAt).toLocaleDateString())}</span>
                            <span class="who">
                                <strong>${esc(m.name)}${!m.read ? ' <span class="unread-pill">New</span>' : ''}</strong>
                                <small>${esc((m.message || '').slice(0, 60))}${(m.message||'').length > 60 ? '…' : ''}</small>
                            </span>
                            <span class="amount dash-row-go-wrap"><i class="fas fa-chevron-right dash-row-go" aria-hidden="true"></i></span>
                        </button>`).join('') + '</div>';
                }
            }
        } catch (err) {
            // Network error - keep zero counts visible, no toast (silent)
        }
    }

    $('#testSmtpBtn')?.addEventListener('click', async () => {
        const btn = $('#testSmtpBtn');
        btn.disabled = true;
        try {
            const body = await sendAdminEmail({ action: 'test' });
            toast(body?.message || 'Test email sent');
        } catch (err) {
            toast(err.message || 'Test email failed', 'error');
        } finally {
            btn.disabled = false;
        }
    });
    $$('[data-close-email-modal]').forEach(el => el.addEventListener('click', closeEmailModal));
    $('#emailForm')?.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = $('#emailFormSubmit');
        btn.disabled = true;
        try {
            await sendAdminEmail({
                action: 'reply',
                to: $('#emailFormTo').value.trim(),
                subject: $('#emailFormSubject').value.trim(),
                body: $('#emailFormBody').value.trim(),
                messageId: $('#emailFormMessageId').value.trim(),
            });
            toast('Email sent');
            closeEmailModal();
            loadSubmissions();
        } catch (err) {
            toast(err.message || 'Could not send email', 'error');
        } finally {
            btn.disabled = false;
        }
    });
    $('#messagesTable')?.addEventListener('click', e => {
        const replyBtn = e.target.closest('[data-smtp-reply]');
        if (!replyBtn) return;
        const name = replyBtn.getAttribute('data-name') || 'there';
        openEmailModal({
            messageId: replyBtn.getAttribute('data-smtp-reply'),
            to: replyBtn.getAttribute('data-email') || '',
            subject: 'Re: Your message to Tattu Care',
            body: `Dear ${name},\n\nThank you for contacting Tattu Care.\n\n\n\nWith gratitude,\nThe Tattu Care Team`,
        });
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (!$('#detailDrawer')?.hidden) closeDetailDrawer();
            else if (!$('#emailModal')?.hidden) closeEmailModal();
        }
    });

    $$('[data-close-detail]').forEach(el => el.addEventListener('click', closeDetailDrawer));

    document.addEventListener('keydown', e => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const card = e.target.closest('.record-card[tabindex="0"], .dash-row-clickable');
        if (!card || e.target.closest('button, a') && !card.classList.contains('dash-row-clickable')) return;
        e.preventDefault();
        card.click();
    });

    document.addEventListener('click', e => {
        const donCard = e.target.closest('[data-open-donation]');
        if (donCard && !e.target.closest('.record-card-actions, [data-don-confirm], [data-don-edit], [data-don-delete]')) {
            const rec = DONATIONS_CACHE.find(d => d.id === donCard.getAttribute('data-open-donation'));
            if (rec) showDonationDetail(rec);
            return;
        }
        const msgCard = e.target.closest('[data-open-message]');
        if (msgCard && !e.target.closest('.record-card-actions, [data-smtp-reply]')) {
            const rec = MESSAGES_CACHE.find(m => m.id === msgCard.getAttribute('data-open-message'));
            if (rec) showMessageDetail(rec);
            return;
        }
        const subCard = e.target.closest('[data-open-subscriber]');
        if (subCard && !e.target.closest('.record-card-actions')) {
            const email = subCard.getAttribute('data-open-subscriber');
            const rec = SUBSCRIBERS_CACHE.find(s => s.email === email) || { email, createdAt: subCard.getAttribute('data-sub-date') };
            showSubscriberDetail(rec);
            return;
        }
        const detailConfirm = e.target.closest('[data-detail-don-confirm]');
        if (detailConfirm) { closeDetailDrawer(); confirmDonation(detailConfirm.getAttribute('data-detail-don-confirm')); return; }
        const detailPoll = e.target.closest('[data-detail-don-poll]');
        if (detailPoll) {
            const id = detailPoll.getAttribute('data-detail-don-poll');
            detailPoll.disabled = true;
            donationApi({ action: 'poll_momo', id }).then(body => {
                if (!body) return;
                toast(body.poll?.changed ? `MoMo status: ${body.donation?.status}` : 'No change from MoMo yet.');
                loadSubmissions();
                loadDashboard();
                if ($('.tabs-content.active')?.getAttribute('data-tab') === 'reports') loadReports();
                const rec = body.donation || DONATIONS_CACHE.find(d => d.id === id);
                if (rec) showDonationDetail(rec);
            }).catch(err => toast(err.message || 'MoMo poll failed', 'error'))
              .finally(() => { detailPoll.disabled = false; });
            return;
        }
        const detailEdit = e.target.closest('[data-detail-don-edit]');
        if (detailEdit) {
            const rec = DONATIONS_CACHE.find(d => d.id === detailEdit.getAttribute('data-detail-don-edit'));
            closeDetailDrawer();
            if (rec) openDonationModal('edit', rec);
            return;
        }
        const detailDel = e.target.closest('[data-detail-don-delete]');
        if (detailDel) { closeDetailDrawer(); deleteDonation(detailDel.getAttribute('data-detail-don-delete')); return; }
        const detailReply = e.target.closest('[data-detail-smtp-reply]');
        if (detailReply) {
            const name = detailReply.getAttribute('data-name') || 'there';
            closeDetailDrawer();
            openEmailModal({
                messageId: detailReply.getAttribute('data-detail-smtp-reply'),
                to: detailReply.getAttribute('data-email') || '',
                subject: 'Re: Your message to Tattu Care',
                body: `Dear ${name},\n\nThank you for contacting Tattu Care.\n\n\n\nWith gratitude,\nThe Tattu Care Team`,
            });
            return;
        }
        const detailRead = e.target.closest('[data-detail-mark-read]');
        if (detailRead) { closeDetailDrawer(); markMessagesRead([detailRead.getAttribute('data-detail-mark-read')]); return; }
        const detailCopy = e.target.closest('[data-detail-copy]');
        if (detailCopy) {
            const text = detailCopy.getAttribute('data-detail-copy') || '';
            navigator.clipboard?.writeText(text).then(() => toast('Email copied')).catch(() => toast('Could not copy', 'error'));
        }
    });

    $('#addManualDonation')?.addEventListener('click', () => openDonationModal('create'));
    $$('[data-close-donation-modal]').forEach(el => el.addEventListener('click', closeDonationModal));
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !$('#donationModal')?.hidden) closeDonationModal();
        if (e.key === 'Escape' && !$('#detailDrawer')?.hidden) closeDetailDrawer();
    });
    $('#donationForm')?.addEventListener('submit', async e => {
        e.preventDefault();
        const id = $('#donFormId').value.trim();
        const payload = {
            action: id ? 'update' : 'create',
            amount: Number($('#donFormAmount').value),
            currency: $('#donFormCurrency').value.trim().toUpperCase(),
            method: $('#donFormMethod').value,
            status: $('#donFormStatus').value,
            name: $('#donFormName').value.trim(),
            email: $('#donFormEmail').value.trim(),
            phone: $('#donFormPhone').value.trim(),
            reference: $('#donFormReference').value.trim(),
            message: $('#donFormMessage').value.trim(),
            adminNote: $('#donFormAdminNote').value.trim(),
            notifyDonor: $('#donFormNotify').checked,
        };
        if (id) payload.id = id;
        const btn = $('#donFormSubmit');
        btn.disabled = true;
        try {
            await donationApi(payload);
            toast(id ? 'Donation updated' : 'Manual donation recorded');
            closeDonationModal();
            loadSubmissions();
            if ($('.tabs-content.active')?.getAttribute('data-tab') === 'dashboard') loadDashboard();
        } catch (err) {
            toast(err.message || 'Could not save donation', 'error');
        } finally {
            btn.disabled = false;
        }
    });
    $('#donationsTable')?.addEventListener('click', e => {
        const confirmBtn = e.target.closest('[data-don-confirm]');
        const editBtn = e.target.closest('[data-don-edit]');
        const delBtn = e.target.closest('[data-don-delete]');
        if (confirmBtn) {
            confirmDonation(confirmBtn.getAttribute('data-don-confirm'));
            return;
        }
        if (editBtn) {
            const rec = DONATIONS_CACHE.find(d => d.id === editBtn.getAttribute('data-don-edit'));
            if (rec) openDonationModal('edit', rec);
            return;
        }
        if (delBtn) deleteDonation(delBtn.getAttribute('data-don-delete'));
    });

    $('#reportsSyncRaised')?.addEventListener('click', async () => {
        const body = await syncRaisedFromDonations(false);
        if (body) loadReports();
    });
    $('#reportsPollMomo')?.addEventListener('click', async () => {
        const btn = $('#reportsPollMomo');
        if (btn) btn.disabled = true;
        try {
            const body = await donationApi({ action: 'poll_all_momo' });
            if (!body) return;
            toast(body.message || 'MoMo poll complete.');
            loadSubmissions();
            loadDashboard();
            loadReports();
        } catch (err) {
            toast(err.message || 'MoMo poll failed', 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    });
    $('#reportsRefresh')?.addEventListener('click', () => loadReports());

    async function init(){
        try {
            const res  = await fetch('api/content.php');
            CONTENT    = await res.json();
            bindFields();
            ['stats','programs','impactStories','team','events','heroSlides','aboutSlides','trustPartners']
                .forEach(k => {
                    // Map shadow lists into nested storage paths
                    if (k === 'heroSlides') {
                        if (!CONTENT.hero) CONTENT.hero = {};
                        CONTENT[k] = CONTENT.hero.slides || [];
                    } else if (k === 'aboutSlides') {
                        if (!CONTENT.about) CONTENT.about = {};
                        CONTENT[k] = CONTENT.about.slides || [];
                    } else if (k === 'trustPartners') {
                        if (!CONTENT.trust) CONTENT.trust = {};
                        CONTENT[k] = CONTENT.trust.partners || [];
                    }
                    renderList(k);
                });
            markClean();
            // Populate dashboard right after content arrives
            loadDashboard();
        } catch (err) {
            toast('Could not load content', 'error');
        }
    }
    init();

    const activePage = $('.tabs-content.active');
    if (activePage) activePage.classList.add('page-enter');

    // Auto-refresh submissions data every 45s on dashboard / submissions tabs
    setInterval(() => {
        const tab = $('.tabs-content.active')?.getAttribute('data-tab');
        if (tab === 'dashboard') loadDashboard();
        else if (tab === 'submissions') loadSubmissions();
        else if (tab === 'reports') loadReports();
    }, 45000);
})();
</script>
<?php endif; ?>
</body>
</html>
