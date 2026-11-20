#!/usr/bin/env python3
"""Generates the Think Mybiz Tech static site into ../site from shared templates."""
import os, datetime

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")  # repo root = public_html
DOMAIN = "https://thinkmybiztech.com"
EMAIL = "info@thinkmybiztech.com"
PHONE = "066 210 5631"
TEL = "+27662105631"
ADDRESS = "48 Rabe Street, Mokopane, Limpopo"

LOGO = """<svg viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" rx="9" fill="#fff"/>
<path d="M11 12.5h18v4.2h-6.8V29h-4.4V16.7H11z" fill="#0a1d3f"/><circle cx="12.5" cy="25" r="2.2" fill="#2f6df6"/><circle cx="27.5" cy="25" r="2.2" fill="#19c3a5"/></svg>"""

I = {  # simple stroke icons
 "code": '<path d="M8 8l-5 4 5 4M16 8l5 4-5 4M14 4l-4 16"/>',
 "globe": '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
 "bolt": '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
 "db": '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
 "cloud": '<path d="M7 18h10a4 4 0 00.5-8A6 6 0 006 9.5 4.3 4.3 0 007 18z"/>',
 "shield": '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
 "phone": '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
 "link": '<path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/>',
 "refresh": '<path d="M20 11a8 8 0 10-2.3 5.7M20 4v7h-7"/>',
 "wifi": '<path d="M2 9a15 15 0 0120 0M5 12.5a10 10 0 0114 0M8.5 16a5 5 0 017 0"/><circle cx="12" cy="19.5" r="1"/>',
 "lock": '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>',
 "brand": '<path d="M4 7h16v12H4z"/><path d="M4 7l3-4h10l3 4"/>',
 "mail": '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
 "pin": '<path d="M12 22s7-6.2 7-12a7 7 0 10-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
 "chart": '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
 "users": '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0114 0M16 4.5a3.5 3.5 0 010 7M22 20a7 7 0 00-5-6.7"/>',
}
def icon(n): return f'<div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{I[n]}</svg></div>'

NAV = [("Services","/services/"),("Solutions","/solutions/"),("Taxnify","/taxnify/"),("How we work","/how-we-work/"),
       ("Pricing","/pricing/"),("Insights","/insights/"),("About","/about/")]

def header(active):
    items = "".join(f'<li><a href="{u}"{" class=active" if u==active else ""}>{t}</a></li>' for t,u in NAV)
    return f"""<header class="site-header"><div class="container nav">
<a class="brand" href="/" aria-label="Think Mybiz Tech home">{LOGO}<span class="brand-text"><strong>THINK MYBIZ</strong><span>TECH</span></span></a>
<button class="menu-toggle" aria-label="Open menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
<ul class="nav-links">{items}
<li class="cart-link" style="display:none"><a href="#" data-open-cart aria-label="Cart">Cart <span class="cart-count">0</span></a></li>
<li class="nav-cta"><a class="btn btn-primary btn-sm" href="/contact/" style="color:#fff">Start a project</a></li></ul>
</div></header>"""

FOOTER = f"""<footer class="site-footer"><div class="container">
<div class="footer-grid">
<div><a class="brand" href="/">{LOGO}<span class="brand-text"><strong>THINK MYBIZ</strong><span>TECH</span></span></a>
<p style="margin-top:14px;max-width:320px">South African software engineering and business automation. Build · Automate · Integrate · Grow.</p></div>
<div><h4>Company</h4><ul><li><a href="/about/">About</a></li><li><a href="/how-we-work/">How we work</a></li><li><a href="/insights/">Insights</a></li><li><a href="/privacy/">Privacy &amp; POPIA</a></li></ul></div>
<div><h4>What we do</h4><ul><li><a href="/services/">Services</a></li><li><a href="/solutions/">Industry solutions</a></li><li><a href="/taxnify/">Taxnify platform</a></li><li><a href="/pricing/">Engagements &amp; pricing</a></li></ul></div>
<div><h4>Contact</h4><ul><li><a href="mailto:{EMAIL}">{EMAIL}</a></li><li><a href="tel:{TEL}">{PHONE}</a></li><li>{ADDRESS}</li></ul></div>
</div>
<div class="footer-bottom"><span>© <span id="year">2026</span> Think Mybiz Tech. All rights reserved.</span>
<span>Formal proposals and agreements identify the contracting legal entity and applicable commercial terms.</span></div>
</div></footer>
<div class="drawer-bg"></div>
<aside class="drawer" aria-label="Cart"><header><strong>Your cart</strong><button class="close-x" aria-label="Close">×</button></header>
<div class="items"></div><footer><div class="total"><span>Total</span><span>—</span></div>
<button class="btn btn-primary" style="width:100%;justify-content:center" data-checkout>Proceed to checkout</button>
<p style="font-size:.8rem;color:#6a7891;margin-top:10px">Secure checkout is hosted by Hostinger Ecommerce.</p></footer></aside>
<script src="/assets/js/main.js" defer></script>"""

def page(path, title, desc, body, active="", page_id="", noindex=False):
    canon = DOMAIN + path
    html = f"""<!DOCTYPE html><html lang="en-ZA"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{title}</title><meta name="description" content="{desc}">
<link rel="canonical" href="{canon}">{'<meta name="robots" content="noindex">' if noindex else ''}
<meta property="og:type" content="website"><meta property="og:title" content="{title}"><meta property="og:description" content="{desc}">
<meta property="og:url" content="{canon}"><meta property="og:site_name" content="Think Mybiz Tech"><meta name="theme-color" content="#06132b">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head><body data-page="{page_id}">{header(active)}<main>{body}</main>{FOOTER}</body></html>"""
    fp = os.path.join(OUT, path.strip("/"), "index.html") if path.endswith("/") else os.path.join(OUT, path.strip("/"))
    os.makedirs(os.path.dirname(fp), exist_ok=True)
    open(fp, "w").write(html)
    return path

def hero(eyebrow, h1, lead, actions=True, cls="page-hero"):
    a = ('<div class="hero-actions"><a class="btn btn-primary" href="/contact/">Discuss your project</a>'
         '<a class="btn btn-ghost" href="/how-we-work/">How we deliver</a></div>') if actions else ""
    return f'<section class="hero {cls}"><div class="container"><span class="eyebrow light">{eyebrow}</span><h1>{h1}</h1><p class="lead">{lead}</p>{a}</div></section>'

def cta(h="Have a process that should run itself?", p="Tell us about the process you want to automate or the application you need. We respond with a practical proposal."):
    return f"""<section class="section"><div class="container"><div class="cta"><h2>{h}</h2><p>{p}</p>
<div class="hero-actions"><a class="btn btn-primary" href="/contact/" style="background:#fff;color:#0a1d3f">Start a conversation</a>
<a class="btn btn-ghost" href="tel:{TEL}">Call {PHONE}</a></div></div></div></section>"""

SERVICES = [
 ("code","01","Software &amp; Product Engineering","Custom business applications, client and employee portals, multi-company platforms and installable Progressive Web Apps."),
 ("globe","02","Web &amp; Digital Experience","Corporate websites, registration and booking sites, digital forms, information portals and customer self-service."),
 ("bolt","03","Automation &amp; Intelligence","Approvals, notifications, recurring tasks, case management and AI-assisted extraction, classification and drafting — always with human approval."),
 ("db","04","Data &amp; System Integration","Database design, data migration, reporting and analytics, APIs, webhooks, payment providers and approved third-party connections."),
 ("cloud","05","Cloud &amp; Managed Technology","Deployment, separate test and production environments, backups, monitoring, security updates and technical support."),
 ("shield","06","Compliance &amp; Finance Systems","Accounting, invoicing, payroll, POS and inventory; document registers, expiry calendars, inspection checklists, evidence packs and audit trails."),
]
def service_cards(n=6):
    return "".join(f'<div class="card">{icon(i)}<span class="num">{num}</span><h3>{t}</h3><p>{d}</p></div>' for i,num,t,d in SERVICES[:n])

SPECIALIST = ["Invoicing &amp; quotations","General ledger &amp; reporting","VAT, PAYE, UIF &amp; SDL","Payroll &amp; payslips","Leave &amp; HR records",
 "Point of sale","Stock &amp; procurement","Client portals","Member registration","Bookings &amp; scheduling","Fleet &amp; maintenance",
 "Inspections &amp; HSE","Management dashboards","Digital forms &amp; e-signatures","Document management","Data migration"]

INDUSTRIES = [
 ("Finance and accounting","Invoicing, quotations, debtors and creditors, bank reconciliation, budgeting, expense claims, petty cash, fixed asset registers, financial reporting."),
 ("Tax and compliance","VAT and payroll tax calculators, compliance calendars, SARS and CIPC deadline trackers, document vaults, audit-file preparation."),
 ("Payroll and HR","Payroll, payslips, leave, recruitment, onboarding, performance reviews, training records, time and attendance."),
 ("Retail and hospitality","Point of sale, inventory, multi-branch stock, loyalty programmes, table and order management, supplier ordering."),
 ("Transport and logistics","Fleet management, vehicle tracking, trip logs, fuel and maintenance, driver management, delivery and dispatch."),
 ("Mining and industrial","Production tracking, shift and crew management, plant maintenance, safety and incident reporting, permits and inspections."),
 ("Community, sport and events","Member and community registration, venue and arena bookings, event ticketing, attendance, facility management."),
 ("Public sector and NGOs","Beneficiary registration, grant and project tracking, stakeholder databases, programme reporting."),
 ("Construction and property","Project costing, site diaries, tenant and lease management, levies, maintenance requests."),
 ("Health and wellness","Bookings and appointments, membership billing, client records management."),
 ("Education and training","Learner registration, fees and billing, attendance, results, training-provider administration."),
 ("Agriculture","Farm records, livestock and crop tracking, input costs, harvest and sales records."),
 ("Professional services","Client portals, job and time tracking, document management, engagement and billing."),
 ("Sales and customer management","CRM, lead tracking, quotation pipelines, customer portals, service desk and ticketing."),
 ("Security and access","Visitor management, access control registers, guard patrols, incident logs."),
 ("Operations and automation","Approval workflows, digital forms, e-signatures, automated reminders, scheduled reports, dashboards."),
]

STEPS = [("Discover","Workshops, workflow mapping, data review, users, objectives and feasibility."),
 ("Define","Scope, requirements, acceptance criteria, delivery plan and proposal."),
 ("Design","Screen flows, data structures, permissions and integration design."),
 ("Engineer","Iterative development on your private test site, code review and testing."),
 ("Validate","Demonstrations, your user-acceptance sign-off, security and release checks."),
 ("Launch &amp; operate","Go-live on your domain, training, documentation, support and improvement.")]
def steps_html():
    return "".join(f'<div class="card step"><span class="num">{i+1:02d}</span><h3>{t}</h3><p>{d}</p></div>' for i,(t,d) in enumerate(STEPS))

ENGAGEMENTS = [
 ("Managed Dedicated Solution", True, "A client-branded application on your domain or subdomain, hosted, operated and supported by Think Mybiz Tech."),
 ("Custom Build &amp; Handover", False, "Software delivered against agreed specifications, with hosting and source-code rights defined in the contract."),
 ("Taxnify Configuration &amp; Integration", False, "Where suitable, configuring Taxnify capabilities or integrating with your approved existing platforms."),
 ("Long-Term Technology Partnership", False, "Ongoing feature development, support, monitoring and operational continuity under a service agreement."),
]
def engagement_cards(btn=True):
    out = ""
    for t, rec, d in ENGAGEMENTS:
        b = '<span class="badge">Recommended</span>' if rec else '<span class="badge" style="visibility:hidden">.</span>'
        a = f'<a class="btn btn-outline" href="/contact/?service={t.replace("&amp;","and")}">Request a proposal</a>' if btn else ""
        out += f'<div class="card price-card">{b}<h3 style="margin-top:12px">{t}</h3><p class="desc">{d}</p>{a}</div>'
    return out

PROJECTS = [("Fleet management system","Transport and logistics","Vehicle, driver, trip, fuel and maintenance management, built as a dedicated client application."),
 ("Mining operations module — Ditau","Mining","Operational module for production and site management, integrated with the client's business processes."),
 ("Community database — Tshulana Arena","Community and sport","Registration and management system for members, participants and facility use (in development)."),
 ("Taxnify — Account. Comply. Grow.","Proprietary product","Our South African business and compliance operating system: accounting, payroll, POS, tax readiness and reporting.")]
def projects_html():
    return "".join(f'<div class="card"><span class="eyebrow">{s}</span><h3 style="margin:8px 0">{t}</h3><p>{d}</p></div>' for t,s,d in PROJECTS)

DIFF = [("Accounting integrity","balanced double-entry postings, subledgers and reconciliations."),
 ("Tax awareness","VAT, PAYE, UIF, SDL and income tax treatment designed in."),
 ("Audit trails","who did what, when, and with whose approval."),
 ("Data protection","POPIA-aligned handling of personal information and consent."),
 ("Governance","role-based permissions and approval workflows."),
 ("Reliable reporting","one source of truth for every figure.")]
def diff_html(): return "".join(f"<li><strong>{a}</strong> — {b}</li>" for a,b in DIFF)

PWA = [("phone","One build, every device","Android, iPhone, tablet and desktop from one codebase — lower cost, faster delivery."),
 ("link","Install from a link","Users add the app to their home screen. No app-store delays."),
 ("refresh","Instant updates","Fixes and new features reach every user immediately."),
 ("wifi","Works on weak signal","Offline-capable screens and data capture where needed."),
 ("lock","Secure by default","Served only over encrypted HTTPS connections."),
 ("brand","Your brand, your domain","Runs on your own address, e.g. app.yourcompany.co.za, fully managed by us.")]
def pwa_html(): return "".join(f'<div class="card">{icon(i)}<h3>{t}</h3><p>{d}</p></div>' for i,t,d in PWA)

pages = []

# ---------- Home ----------
home = f"""
<section class="hero"><div class="container">
<span class="eyebrow light">Build · Automate · Integrate · Grow</span>
<h1>Software, automation and applications built to be trusted.</h1>
<p class="lead">Think Mybiz Tech is a South African software engineering company. We design, build, host and manage custom applications,
Progressive Web Apps, databases and business automation — engineered with compliance at the core, for any business in any industry.</p>
<div class="hero-actions"><a class="btn btn-primary" href="/contact/">Discuss your project</a><a class="btn btn-ghost" href="/taxnify/">Explore Taxnify</a></div>
<div class="chips"><span class="chip">Custom Applications</span><span class="chip">Progressive Web Apps</span><span class="chip">Business Automation</span>
<span class="chip">Databases</span><span class="chip">Websites</span><span class="chip">AI Solutions</span><span class="chip">Compliance Systems</span></div>
</div></section>
<section class="strip"><div class="container">
<div><strong>Compliance first</strong>SARS, POPIA and audit needs designed in</div>
<div><strong>One app, every device</strong>Installable PWAs on your own domain</div>
<div><strong>In-house engineers</strong>No outsourced black boxes</div>
<div><strong>You own your data</strong>Export at any time</div>
</div></section>

<section class="section"><div class="container">
<div class="section-head"><span class="eyebrow">What we do</span><h2>From the first process review to a live, supported application</h2>
<p class="lead">Commission a complete solution, or combine services into a phased digital transformation programme.</p></div>
<div class="grid g3">{service_cards()}</div>
<p style="margin-top:26px"><a href="/services/">See all services and specialist systems →</a></p>
</div></section>

<section class="section alt"><div class="container grid g2" style="align-items:center;gap:48px">
<div><span class="eyebrow">Our difference</span><h2 style="margin:.35em 0 .5em">Compliance is where we start</h2>
<p>Most software companies add compliance at the end. We start with it. Our team's roots are in professional accounting, tax and compliance work —
so whether we build a payroll system, a fleet tracker or a community registration app, the data it produces can be relied on by management, auditors and regulators.</p>
<a class="btn btn-outline" href="/about/" style="margin-top:22px">About Think Mybiz Tech</a></div>
<div class="card"><ul class="checks">{diff_html()}</ul></div>
</div></section>

<section class="section"><div class="container">
<div class="band"><div><span class="eyebrow light">Our platform</span><h2 style="margin:.35em 0 .4em">Taxnify — a Think Mybiz Tech product</h2>
<p>Taxnify is our proprietary business and compliance operating system for South African businesses: accounting, tax, compliance, payroll,
point of sale and business operations. It is available to any business as a subscription — and it is the proven foundation our engineers
build client applications on, so every custom solution starts with secure sign-in, permissions, audit trails and financial accuracy already in place.</p>
<a class="btn btn-primary" href="/taxnify/">Discover Taxnify</a></div>
<div><ul class="checks" style="color:#d6e1f5"><li>Accounting &amp; financial reporting</li><li>VAT, PAYE, UIF &amp; SDL readiness</li><li>Payroll &amp; payslips</li><li>Point of sale &amp; inventory</li><li>Role-based access &amp; audit trails</li></ul></div>
</div></div></section>

<section class="section alt"><div class="container">
<div class="section-head"><span class="eyebrow">How we deliver apps</span><h2>Progressive Web Apps: one app, every device</h2>
<p class="lead">Every application we build is a single app that runs in the browser and installs on a phone, tablet or computer like a native app.</p></div>
<div class="grid g3">{pwa_html()}</div></div></section>

<section class="section"><div class="container">
<div class="section-head"><span class="eyebrow">Track record</span><h2>Selected project experience</h2></div>
<div class="grid g4">{projects_html()}</div></div></section>

<section class="section alt"><div class="container">
<div class="section-head"><span class="eyebrow">Any industry. Any niche.</span><h2>If a business runs a process, we can build the system for it</h2></div>
<div class="tags">{''.join(f'<span class="tag">{t}</span>' for t,_ in INDUSTRIES)}</div>
<p style="margin-top:24px"><a href="/solutions/">Explore industry solutions →</a></p></div></section>

<section class="section" data-insights><div class="container">
<div class="section-head"><span class="eyebrow">Insights</span><h2>Latest from Think Mybiz Tech</h2></div>
<div class="grid g3" data-posts="3" data-hide-empty><p class="loading">Loading articles…</p></div></div></section>
{cta()}
"""
pages.append(page("/", "Think Mybiz Tech | Custom Software, PWAs & Business Automation — South Africa",
  "South African software engineering and business automation company. Custom applications, Progressive Web Apps, databases, websites, AI and compliance systems — and the makers of Taxnify.", home, "/", "home"))

# ---------- Services ----------
svc = hero("What we do","Our services","Services can be commissioned as a complete solution or combined into a phased digital transformation programme — from the first process review to a live, supported application.") + f"""
<section class="section"><div class="container"><div class="grid g3">{service_cards()}</div></div></section>
<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">Specialist systems we deliver</span><h2>Proven building blocks for serious business software</h2></div>
<div class="tags">{''.join(f'<span class="tag">{t}</span>' for t in SPECIALIST)}</div></div></section>
<section class="section"><div class="container"><div class="band"><div><span class="eyebrow light">Digital transformation advisory</span><h2 style="margin:.35em 0 .4em">Not sure where to start?</h2>
<p>We review your processes, identify what to automate first, and give you a practical, phased roadmap from manual to automated.</p>
<a class="btn btn-primary" href="/contact/?service=Digital transformation advisory">Book a process review</a></div>
<div><ul class="checks" style="color:#d6e1f5"><li>Workflow and data review</li><li>Automation priorities and quick wins</li><li>Phased roadmap with clear scope</li><li>Build, buy or configure Taxnify — honest advice</li></ul></div></div></div></section>
<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">Engineering standards</span><h2>Secure by design. Supported beyond launch.</h2>
<p class="lead">Technology choices are guided by each application's requirements, data sensitivity and long-term maintainability — so your system is never locked to one developer.</p></div>
<div class="tags" style="margin-bottom:28px">{''.join(f'<span class="tag">{t}</span>' for t in ["Node.js","React","TypeScript / JavaScript","PostgreSQL","REST APIs","Progressive Web Apps","Git version control","Cloud / VPS deployment"])}</div>
<div class="grid g2">
<div class="card">{icon("lock")}<h3>Access &amp; privacy</h3><p>User roles, least-privilege permissions, strict per-client data separation and POPIA-aligned processing.</p></div>
<div class="card">{icon("chart")}<h3>Traceability &amp; control</h3><p>Audit logs, approvals, review stages, change history and evidence-backed reporting.</p></div>
<div class="card">{icon("refresh")}<h3>Release discipline</h3><p>Separate development, testing and production environments; explicit approval before production changes.</p></div>
<div class="card">{icon("cloud")}<h3>Operational continuity</h3><p>Documented handover, backup and recovery, security updates, monitoring and agreed support.</p></div>
</div></div></section>{cta()}"""
pages.append(page("/services/","Services | Software Engineering, Automation & Integration — Think Mybiz Tech",
  "Software and product engineering, web and digital experience, automation and AI, data and integration, cloud and managed technology, and compliance and finance systems.", svc, "/services/"))

# ---------- Solutions ----------
sol = hero("Applications we build","Any industry. Any niche.","We are not limited to a fixed product list. If a business runs a process — registering members, managing a fleet, running a mine shift, selling at a till or paying staff — we can build the system for it.") + f"""
<section class="section"><div class="container"><div class="grid g4 ind">{''.join(f'<div class="card"><h3>{t}</h3><p>{d}</p></div>' for t,d in INDUSTRIES)}</div></div></section>
<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">Track record</span><h2>Selected project experience</h2></div><div class="grid g4">{projects_html()}</div></div></section>
{cta("Don't see your industry?","The catalogue shows the breadth of what we deliver, not the limit. Describe your process and we'll show you what it looks like as an application.")}"""
pages.append(page("/solutions/","Industry Solutions | Custom Business Applications — Think Mybiz Tech",
  "Custom applications for finance, tax, payroll, retail, logistics, mining, community and sport, NGOs, property, health, education, agriculture and more.", sol, "/solutions/"))

# ---------- Taxnify ----------
tx = hero("A Think Mybiz Tech product","Taxnify — Account. Comply. Grow.","Our proprietary South African business and compliance operating system: accounting, tax, compliance, payroll, point of sale and business operations in one secure platform.", actions=False) + f"""
<section class="section"><div class="container grid g2" style="gap:48px;align-items:start">
<div><span class="eyebrow">The platform</span><h2 style="margin:.35em 0 .5em">Built by compliance professionals, engineered by Think Mybiz Tech</h2>
<p>Taxnify is available to any business as a subscription. It is also the proven foundation our engineers build client applications on,
so custom solutions start with secure sign-in, permissions, audit trails and financial accuracy already in place.</p>
<p>Custom solutions can also be built independently of Taxnify — we recommend whatever fits your requirements best.</p>
<div class="hero-actions"><a class="btn btn-primary" href="https://www.taxnify.co.za" rel="noopener">Visit taxnify.co.za</a>
<a class="btn btn-outline" href="/contact/?service=Taxnify configuration and integration">Talk to us about Taxnify</a></div></div>
<div class="card"><h3 style="margin-bottom:14px">What Taxnify covers</h3><ul class="checks">
<li><strong>Accounting</strong> — general ledger, invoicing, debtors and creditors, reporting</li>
<li><strong>Tax readiness</strong> — VAT, PAYE, UIF, SDL and income tax treatment</li>
<li><strong>Payroll</strong> — payroll runs, payslips and employee records</li>
<li><strong>Point of sale</strong> — till, stock and inventory</li>
<li><strong>Compliance</strong> — deadlines, document registers and audit trails</li>
<li><strong>Business operations</strong> — permissions, approvals and multi-company control</li></ul></div>
</div></section>
<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">Ways to use Taxnify</span><h2>Subscribe, configure or build on it</h2></div>
<div class="grid g3">
<div class="card">{icon("users")}<h3>Subscribe</h3><p>Use Taxnify directly as your business and compliance system. Sign up and manage your subscription at taxnify.co.za.</p></div>
<div class="card">{icon("link")}<h3>Configure &amp; integrate</h3><p>We configure Taxnify capabilities for your business or integrate it with your approved existing platforms.</p></div>
<div class="card">{icon("code")}<h3>Build on the foundation</h3><p>Custom applications built on Taxnify's secure core — sign-in, permissions, audit trails and accounting accuracy from day one.</p></div>
</div></div></section>{cta("Ready to see Taxnify in your business?","Subscribe online or ask us to configure and integrate it with the way you work.")}"""
pages.append(page("/taxnify/","Taxnify | Business & Compliance Operating System by Think Mybiz Tech",
  "Taxnify is Think Mybiz Tech's proprietary South African accounting, tax, payroll, POS and compliance platform — available by subscription and as the foundation for custom applications.", tx, "/taxnify/"))

# ---------- How we work ----------
hw = hero("Delivery &amp; collaboration","From business problem to working solution","Our delivery process keeps requirements visible, decisions documented and releases controlled. Nothing goes live without your approval.") + f"""
<section class="section"><div class="container"><div class="grid g3">{steps_html()}</div></div></section>
<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">Flexible delivery arrangements</span><h2>Choose how you want to own and run your system</h2></div>
<div class="grid g4">{engagement_cards()}</div></div></section>
<section class="section"><div class="container grid g2" style="gap:30px">
<div class="card"><h3 style="margin-bottom:12px">What every client gets</h3><ul class="checks">
<li><strong>Named project lead</strong> — one accountable contact.</li><li><strong>Private test site</strong> — try progress before go-live.</li>
<li><strong>Shared change log</strong> — every request approved first.</li><li><strong>Regular progress meetings</strong> — clear status and risks.</li></ul></div>
<div class="card dark"><h3 style="margin-bottom:12px">Commercial clarity</h3><p>Each engagement specifies deliverables, data ownership, intellectual-property rights, licensing, access, acceptance,
service levels and exit arrangements. You can export your data at any time.</p></div></div></section>{cta()}"""
pages.append(page("/how-we-work/","How We Work | Delivery Process & Engagement Models — Think Mybiz Tech",
  "Discover, define, design, engineer, validate, launch and operate. Managed dedicated solutions, custom build and handover, Taxnify integration and long-term partnerships.", hw, "/how-we-work/"))

# ---------- Pricing ----------
pr = hero("Engagements &amp; pricing","Clear scope. Clear pricing.","Custom software is priced against an agreed scope after a discovery conversation. Fixed-price services can be paid for online below.", actions=False) + f"""
<section class="section"><div class="container">
<div class="section-head"><span class="eyebrow">Fixed-price services</span><h2>Buy and pay online</h2></div>
<div id="store-note" class="notice" style="display:none">Online packages are being finalised. In the meantime, <a href="/contact/">request a quote</a> and we'll send a formal proposal and invoice.</div>
<div class="grid g3" id="store-grid"><p class="loading">Loading packages…</p></div>
</div></section>
<section class="section alt"><div class="container"><div class="section-head"><span class="eyebrow">Custom engagements</span><h2>Quoted against your requirements</h2>
<p class="lead">Every proposal specifies deliverables, acceptance criteria, service levels, data ownership and exit arrangements.</p></div>
<div class="grid g4">{engagement_cards()}</div></div></section>
<section class="section"><div class="container"><div class="band"><div><span class="eyebrow light">Taxnify subscriptions</span><h2 style="margin:.35em 0 .4em">Looking for Taxnify plans?</h2>
<p>Taxnify subscriptions are managed on the Taxnify website.</p><a class="btn btn-primary" href="https://www.taxnify.co.za" rel="noopener">View Taxnify</a></div>
<div><ul class="checks" style="color:#d6e1f5"><li>Fixed-price services: secure hosted checkout</li><li>Prices in South African rand</li><li>Custom work: formal proposal before any commitment</li></ul></div></div></div></section>"""
pages.append(page("/pricing/","Pricing & Engagements | Think Mybiz Tech",
  "Fixed-price services you can pay for online, and custom software engagements quoted against your requirements.", pr, "/pricing/", "pricing"))

# ---------- Insights ----------
ins = hero("Insights","Software, automation and compliance — practical thinking","Articles from the Think Mybiz Tech team on building trustworthy business systems in South Africa.", actions=False) + """
<section class="section"><div class="container"><div class="grid g3" data-posts="9"><p class="loading">Loading articles…</p></div>
<p style="text-align:center;margin-top:30px"><button class="btn btn-outline" data-more-posts style="display:none">Load more</button></p></div></section>"""
pages.append(page("/insights/","Insights | Think Mybiz Tech","Articles on custom software, business automation, Progressive Web Apps and compliance for South African businesses.", ins, "/insights/"))
pages.append(page("/insights/post/","Insights | Think Mybiz Tech","Article from Think Mybiz Tech.",
  '<section class="section"><div class="container"><div class="article" id="article"><p class="loading">Loading article…</p></div></div></section>', "/insights/"))

# ---------- About ----------
ab = hero("About us","Compliance professionals who build software","Think Mybiz Tech designs, builds, hosts and manages systems that keep a business's records, people and processes accurate, secure and audit-ready.", actions=False) + f"""
<section class="section"><div class="container grid g2" style="gap:48px">
<div><span class="eyebrow">Who we are</span><h2 style="margin:.35em 0 .5em">A South African software engineering and business automation company</h2>
<p>Our team's roots are in professional accounting, tax and compliance work. That experience shapes everything we build: we understand what business owners,
auditors and regulators expect from a system, and we design for it from the first day.</p>
<p>We turn business requirements into usable software — from discovery and solution design to implementation, hosting and long-term support.
We are not limited to one product or one industry, and every system is delivered as a modern application that works on every device.</p></div>
<div class="grid" style="gap:16px">
<div class="card"><span class="eyebrow">Vision</span><p style="margin-top:8px;color:var(--ink);font-weight:600">Every business, whatever its size or industry, runs on systems that are automated, compliant and audit-ready.</p></div>
<div class="card"><span class="eyebrow">Mission</span><p style="margin-top:8px;color:var(--ink);font-weight:600">To turn manual, paper-based and spreadsheet processes into secure, mobile-ready applications — built with the discipline of compliance professionals and the speed of a modern development team.</p></div>
</div></div></section>
<section class="section alt"><div class="container"><div class="card" style="display:flex;gap:22px;align-items:center;flex-wrap:wrap">
<div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#0f2a57,#2f6df6);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.4rem;flex:none">MB</div>
<div style="flex:1;min-width:240px"><span class="eyebrow">Led by our founder</span><h3 style="margin:6px 0">MF Banda</h3>
<p>Think Mybiz Tech is led by founder MF Banda, whose work in accounting, tax and compliance practice shapes how every system is designed:
accurate records, clear audit trails and controls that business owners, auditors and regulators can rely on.</p></div></div></div></section>
<section class="section"><div class="container"><div class="section-head"><span class="eyebrow">Our values</span><h2>What you can hold us to</h2></div>
<div class="grid g3">
<div class="card"><h3>Integrity</h3><p>Accurate, traceable records with full audit trails.</p></div>
<div class="card"><h3>Ownership</h3><p>Your data and business rules belong to you.</p></div>
<div class="card"><h3>Privacy</h3><p>Personal information handled in line with POPIA.</p></div>
<div class="card"><h3>Isolation</h3><p>Each client's data kept strictly separate.</p></div>
<div class="card"><h3>Delivery</h3><p>Working software in short, tested cycles.</p></div>
<div class="card"><h3>In-house team</h3><p>Our own engineers, working to documented standards.</p></div>
</div></div></section>{cta()}"""
pages.append(page("/about/","About Think Mybiz Tech | Software Built by Compliance Professionals",
  "Think Mybiz Tech is a South African software engineering and business automation company led by founder MF Banda, with roots in accounting, tax and compliance.", ab, "/about/"))

# ---------- Contact ----------
opts = ["Custom application / PWA","Website or portal","Business automation / AI","Data, database or integration","Cloud hosting &amp; support",
        "Compliance &amp; finance system","Taxnify configuration and integration","Digital transformation advisory","Other"]
ct = hero("Let's build together","Tell us what you want to automate","Describe the process or application you need. We'll respond with a practical proposal.", actions=False) + f"""
<section class="section"><div class="container grid g2" style="gap:40px;align-items:start">
<div class="card"><form id="enquiry" class="form">
<div class="row"><div><label for="name">Full name</label><input id="name" name="name" required autocomplete="name"></div>
<div><label for="company">Company / organisation</label><input id="company" name="company" autocomplete="organization"></div></div>
<div class="row"><div><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email"></div>
<div><label for="phone">Phone</label><input id="phone" name="phone" type="tel" autocomplete="tel"></div></div>
<div class="row"><div><label for="service">What do you need?</label><select id="service" name="service">{''.join(f'<option>{o}</option>' for o in opts)}</select></div>
<div><label for="budget">Timeline</label><select id="budget" name="budget"><option>Exploring options</option><option>Within 1 month</option><option>1–3 months</option><option>3+ months</option></select></div></div>
<div><label for="message">About your project</label><textarea id="message" name="message" rows="6" required placeholder="The process you want to automate, who uses it, and what success looks like."></textarea></div>
<button class="btn btn-primary" type="submit">Send enquiry</button>
<p id="enquiry-ok" class="notice" style="display:none">Your email app has opened with your enquiry. If it didn't, email us directly at <a href="mailto:{EMAIL}">{EMAIL}</a>.</p>
<p style="font-size:.8rem;color:var(--muted)">We use your details only to respond to your enquiry. See our <a href="/privacy/">privacy notice</a>.</p>
</form></div>
<div><div class="card">
<div class="contact-item">{icon("mail")}<div><small>Email</small><a href="mailto:{EMAIL}">{EMAIL}</a></div></div>
<div class="contact-item">{icon("phone")}<div><small>Telephone</small><a href="tel:{TEL}">{PHONE}</a></div></div>
<div class="contact-item">{icon("pin")}<div><small>Address</small><span>{ADDRESS}</span></div></div>
</div>
<div class="card dark" style="margin-top:20px"><h3>What happens next</h3><ul class="checks" style="margin-top:12px">
<li>We review your enquiry and arrange a discovery conversation.</li><li>We define scope, acceptance criteria and a delivery plan.</li><li>You receive a formal proposal with clear commercial terms.</li></ul></div>
</div></div></section>
<script>(function(){{var s=new URLSearchParams(location.search).get("service");if(!s)return;var sel=document.getElementById("service");
for(var i=0;i<sel.options.length;i++){{if(sel.options[i].text.toLowerCase().indexOf(s.toLowerCase().split(" ")[0])===0){{sel.selectedIndex=i;break;}}}}
var m=document.getElementById("message");if(m&&!m.value)m.value="I'm interested in: "+s+"\\n\\n";}})();</script>"""
pages.append(page("/contact/","Contact Think Mybiz Tech | Start a Software Project",
  "Contact Think Mybiz Tech in Mokopane, Limpopo. Email info@thinkmybiztech.com or call 066 210 5631 to discuss your software, automation or Taxnify project.", ct, "/contact/"))

# ---------- Privacy ----------
pv = hero("Privacy","Privacy &amp; POPIA notice","How this website handles personal information.", actions=False) + f"""
<section class="section"><div class="container"><div class="article"><div class="content">
<p>Think Mybiz Tech respects your privacy and processes personal information in line with the Protection of Personal Information Act, 2013 (POPIA).</p>
<h3>What we collect</h3><p>When you contact us or place an order, we receive the details you choose to give us — such as your name, company, email address, phone number and project information. Orders are processed through Hostinger Ecommerce's hosted checkout.</p>
<h3>How we use it</h3><p>We use your information only to respond to enquiries, prepare proposals, deliver services you have ordered, issue invoices and meet our legal obligations. We do not sell your personal information.</p>
<h3>Your rights</h3><p>You may ask to access, correct or delete the personal information we hold about you, or object to its processing, by emailing <a href="mailto:{EMAIL}">{EMAIL}</a>.</p>
<h3>Client systems</h3><p>Data held in applications we build or host for clients belongs to those clients. Each client's data is kept strictly separate and handled under the terms of its service agreement.</p>
<h3>Contact</h3><p>Questions about this notice: <a href="mailto:{EMAIL}">{EMAIL}</a> · {PHONE} · {ADDRESS}.</p>
</div></div></div></section>"""
pages.append(page("/privacy/","Privacy & POPIA Notice | Think Mybiz Tech","How Think Mybiz Tech handles personal information in line with POPIA.", pv))

# ---------- Checkout result pages ----------
pages.append(page("/checkout/success/","Order received | Think Mybiz Tech","Thank you for your order.",
  hero("Order received","Thank you — your order has been placed","You'll receive an order confirmation by email. Our team will be in touch to schedule the next steps.", actions=False) +
  '<section class="section"><div class="container"><a class="btn btn-primary" href="/">Back to home</a></div></section>', "", "checkout-success", noindex=True))
pages.append(page("/checkout/cancel/","Checkout cancelled | Think Mybiz Tech","Checkout was cancelled.",
  hero("Checkout cancelled","Your payment was not completed","Your cart is saved. You can return to it at any time, or contact us for an invoice.", actions=False) +
  '<section class="section"><div class="container"><a class="btn btn-primary" href="/pricing/">Back to pricing</a> <a class="btn btn-outline" href="/contact/">Contact us</a></div></section>', "", "checkout-cancel", noindex=True))
page("/404.html","Page not found | Think Mybiz Tech","Page not found.",
  hero("404","We couldn't find that page","The page may have moved. Try one of the links above, or go back home.", actions=False) +
  '<section class="section"><div class="container"><a class="btn btn-primary" href="/">Back to home</a></div></section>', noindex=True)

# ---------- SEO files ----------
today = datetime.date.today().isoformat()
public = [p for p in pages if "checkout" not in p and p != "/insights/post/"]
open(os.path.join(OUT,"sitemap.xml"),"w").write('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' +
  "".join(f"<url><loc>{DOMAIN}{p}</loc><lastmod>{today}</lastmod></url>\n" for p in public) + "</urlset>\n")
open(os.path.join(OUT,"robots.txt"),"w").write(f"User-agent: *\nAllow: /\nDisallow: /checkout/\nSitemap: {DOMAIN}/sitemap.xml\n")
open(os.path.join(OUT,"favicon.svg"),"w").write(LOGO.replace('aria-hidden="true"','xmlns="http://www.w3.org/2000/svg"'))
open(os.path.join(OUT,".htaccess"),"w").write("""Options -Indexes
RedirectMatch 404 /\\.(git|gitignore)
RedirectMatch 404 ^/_src/
ErrorDocument 404 /404.html
<IfModule mod_headers.c>
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>
<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType text/css "access plus 7 days"
ExpiresByType application/javascript "access plus 7 days"
ExpiresByType image/svg+xml "access plus 30 days"
</IfModule>
""")
print("built", len(pages), "pages")
