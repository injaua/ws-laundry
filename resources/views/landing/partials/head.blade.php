{{-- Landing design system: CDN links, tokens and all component CSS (shared by index + order-status) --}}
<link rel="icon" href="{{ asset("images/logo.png") }}">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">

<style>
    :root {
        --navy: #1a237e; --navy-2: #283593; --navy-dark: #0d1b4a;
        --accent: #42a5f5; --accent-soft: #e3f2fd;
        --ink: #12183a; --muted: #64748b; --surface: #f6f8fc; --line: #e8edf5;
        --gold: #f59e0b; --wa: #25d366; --wa-dark: #1da851;
        --r-sm: 12px; --r-md: 18px; --r-lg: 26px;
        --sh-sm: 0 2px 10px rgba(18,24,58,.05);
        --sh-md: 0 10px 30px rgba(18,24,58,.08);
        --sh-lg: 0 24px 60px rgba(13,27,74,.18);
        --space-section: 5.5rem;
        --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
        --dur-short: 220ms; --dur-long: 420ms;
        --font-body: "Inter", system-ui, -apple-system, sans-serif;
        --font-display: "Fraunces", Georgia, serif;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    html { scroll-behavior: smooth; }
    body { font-family:var(--font-body); color:var(--ink); background:#fff; line-height:1.65; }
    img { max-width:100%; }
    section[id] { scroll-margin-top: 80px; }
    .display { font-family:var(--font-display); font-weight:700; letter-spacing:-.015em; }

    /* ---------- Buttons ---------- */
    .btn-pill { display:inline-flex; align-items:center; gap:.5rem; border-radius:100px; font-weight:600; text-decoration:none; border:none; transition:transform var(--dur-short) var(--ease-out), box-shadow var(--dur-short) var(--ease-out), background var(--dur-short); }
    .btn-pill:hover { transform:translateY(-2px); }
    .btn-wa { background:var(--wa); color:#fff!important; padding:.7rem 1.6rem; font-size:.95rem; }
    .btn-wa:hover { background:var(--wa-dark); box-shadow:0 10px 24px rgba(37,211,102,.28); color:#fff; }
    .btn-wa-sm { background:var(--wa); color:#fff!important; padding:.4rem 1rem; font-size:.8rem; }
    .btn-wa-sm:hover { background:var(--wa-dark); color:#fff; }
    .btn-login-sm { background:#fff; color:var(--navy)!important; border:1px solid var(--line); padding:.28rem .7rem; font-size:.72rem; box-shadow:var(--sh-sm); }
    .btn-login-sm:hover { background:var(--accent-soft); color:var(--navy); }
    .btn-ghost { background:rgba(255,255,255,.08); color:#fff!important; border:1px solid rgba(255,255,255,.25); padding:.7rem 1.6rem; font-size:.95rem; }
    .btn-ghost:hover { background:rgba(255,255,255,.16); color:#fff; }
    .btn-solid { background:var(--navy); color:#fff!important; padding:.7rem 1.6rem; font-size:.95rem; }
    .btn-solid:hover { background:var(--navy-2); color:#fff; box-shadow:0 10px 24px rgba(26,35,126,.28); }

    /* ---------- Navbar ---------- */
    .lp-nav { position:fixed; top:0; left:0; right:0; z-index:1030; background:rgba(255,255,255,.85); backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px); border-bottom:1px solid transparent; transition:background var(--dur-short), border-color var(--dur-short), box-shadow var(--dur-short); }
    .lp-nav.scrolled { background:rgba(255,255,255,.97); border-bottom-color:var(--line); box-shadow:var(--sh-sm); }
    .lp-nav .container { display:flex; align-items:center; justify-content:space-between; height:72px; }
    .lp-brand { display:flex; align-items:center; gap:.6rem; text-decoration:none; color:var(--navy); font-weight:800; font-size:1.05rem; letter-spacing:-.02em; }
    .lp-brand img { height:40px; width:auto; border-radius:8px; }
    .lp-brand small { display:block; font-size:.6rem; font-weight:600; color:var(--muted); letter-spacing:.14em; text-transform:uppercase; line-height:1; }
    .nav-links { display:flex; gap:1.75rem; align-items:center; }
    .nav-links > a, .nav-links .dropdown > a { color:var(--muted); text-decoration:none; font-size:.65rem; font-weight:500; transition:color var(--dur-short); position:relative; }
    .nav-links > a:not(.btn-pill)::after { content:""; position:absolute; bottom:-4px; left:0; width:0; height:2px; background:var(--navy); transition:width var(--dur-short) var(--ease-out); border-radius:1px; }
    .nav-links > a:hover { color:var(--navy); }
    .nav-links > a:not(.btn-pill):hover::after { width:100%; }
    .lp-nav-toggler { display:none; background:none; border:none; font-size:1.5rem; color:var(--navy); cursor:pointer; }
    .lp-nav.on-dark:not(.scrolled) { background:transparent; border-bottom-color:transparent; }
    .lp-nav.on-dark:not(.scrolled) .lp-brand, .lp-nav.on-dark:not(.scrolled) .nav-links > a:not(.btn-pill), .lp-nav.on-dark:not(.scrolled) .lp-nav-toggler { color:#fff; }
    .lp-nav.on-dark:not(.scrolled) .nav-links > a:not(.btn-pill):hover { color:#fff; }
    .lp-nav.on-dark:not(.scrolled) .nav-links > a:not(.btn-pill)::after { background:#fff; }

    /* ---------- Sections ---------- */
    section { padding:var(--space-section) 0; }
    .section-label { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.12em; color:var(--accent); margin-bottom:.8rem; }
    .section-title { font-family:var(--font-display); font-size:clamp(1.6rem,3.2vw,2.4rem); font-weight:700; letter-spacing:-.02em; margin-bottom:.75rem; color:var(--ink); }
    .section-desc { color:var(--muted); max-width:560px; font-size:.95rem; }

    /* ---------- Hero (index) ---------- */
    .lp-hero { padding:9rem 0 5rem; background:linear-gradient(160deg,var(--navy) 0%,var(--navy-dark) 70%,#091233 100%); color:#fff; position:relative; overflow:hidden; }
    .lp-hero::before { content:""; position:absolute; inset:0; background:radial-gradient(ellipse 60% 50% at 20% 30%, rgba(66,165,245,.22) 0%, transparent 60%), radial-gradient(ellipse 50% 40% at 85% 80%, rgba(100,181,246,.12) 0%, transparent 55%); pointer-events:none; }
    .lp-hero .container { position:relative; z-index:1; }
    .hero-grid { display:grid; grid-template-columns:1.05fr .95fr; gap:4rem; align-items:center; }
    .hero-badge { display:inline-flex; align-items:center; gap:.5rem; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18); color:#cfe4ff; border-radius:100px; padding:.35rem 1rem; font-size:.78rem; font-weight:600; letter-spacing:.02em; margin-bottom:1.5rem; }
    .hero-badge i { color:var(--accent); }
    .lp-hero h1 { font-family:var(--font-display); font-size:clamp(2.2rem,5.2vw,3.6rem); font-weight:700; letter-spacing:-.025em; line-height:1.12; margin-bottom:1.25rem; }
    .lp-hero h1 em { font-style:normal; color:#9ec9ff; }
    .lp-hero .hero-sub { font-size:1.05rem; color:rgba(255,255,255,.72); max-width:520px; margin-bottom:2rem; }
    .hero-ctas { display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:3rem; }
    .hero-stats { display:flex; gap:2.5rem; flex-wrap:wrap; padding-top:2rem; border-top:1px solid rgba(255,255,255,.1); }
    .hero-stat { font-size:.8rem; color:rgba(255,255,255,.55); }
    .hero-stat strong { display:block; font-size:1.45rem; font-weight:800; color:#fff; }
    .hero-stat strong i { color:var(--gold); font-size:1.1rem; }
    .hero-visual { position:relative; }
    .hero-photo { position:relative; border-radius:var(--r-lg); overflow:hidden; aspect-ratio:4/5; box-shadow:var(--sh-lg); }
    .hero-photo img { width:100%; height:100%; object-fit:cover; display:block; }
    .hero-photo.icon-fallback { display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); }
    .hero-photo.icon-fallback i { font-size:6rem; color:rgba(255,255,255,.25); }
    .hero-float { position:absolute; left:-1.75rem; bottom:2rem; background:#fff; color:var(--ink); border-radius:var(--r-md); padding:1rem 1.25rem; box-shadow:var(--sh-lg); min-width:200px; }
    .hero-float .label { font-size:.7rem; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); font-weight:700; }
    .hero-float .value { font-size:1.35rem; font-weight:800; color:var(--navy); }
    .hero-float .value small { font-size:.75rem; color:var(--muted); font-weight:600; }
    .hero-float .svc { font-size:.8rem; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .hero-orbit { position:absolute; top:-1.25rem; right:-1rem; background:rgba(37,211,102,.14); border:1px solid rgba(37,211,102,.4); color:#8ff0bb; border-radius:100px; padding:.45rem 1rem; font-size:.75rem; font-weight:700; display:flex; align-items:center; gap:.4rem; backdrop-filter:blur(6px); }

    /* ---------- How it works ---------- */
    .lp-how { background:#fff; }
    .steps { display:grid; grid-template-columns:repeat(4,1fr); gap:1.5rem; counter-reset:step; position:relative; }
    .steps::before { content:""; position:absolute; top:28px; left:12%; right:12%; height:2px; background:linear-gradient(90deg,var(--accent-soft),var(--accent),var(--accent-soft)); z-index:0; }
    .step { position:relative; z-index:1; text-align:center; padding:0 .5rem; }
    .step .dot { width:56px; height:56px; margin:0 auto 1.1rem; border-radius:50%; background:#fff; border:2px solid var(--accent-soft); box-shadow:var(--sh-sm); display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:var(--navy); transition:transform var(--dur-short) var(--ease-out), border-color var(--dur-short); }
    .step:hover .dot { transform:translateY(-4px); border-color:var(--accent); }
    .step .num { position:absolute; top:-6px; left:50%; margin-left:12px; width:22px; height:22px; border-radius:50%; background:var(--navy); color:#fff; font-size:.7rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
    .step h5 { font-size:.95rem; font-weight:700; margin-bottom:.3rem; }
    .step p { font-size:.82rem; color:var(--muted); margin:0; }

    /* ---------- Service cards ---------- */
    .svc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1.25rem; }
    .svc-card { background:#fff; border:1px solid var(--line); border-radius:var(--r-md); padding:1.5rem; display:flex; flex-direction:column; gap:.35rem; transition:transform var(--dur-short) var(--ease-out), box-shadow var(--dur-short) var(--ease-out), border-color var(--dur-short); }
    .svc-card:hover { transform:translateY(-5px); box-shadow:var(--sh-md); border-color:#d5e4fa; }
    .svc-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:.5rem; }
    .svc-icon { width:46px; height:46px; border-radius:14px; background:var(--accent-soft); color:var(--navy); display:flex; align-items:center; justify-content:center; font-size:1.2rem; }
    .svc-badge { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; padding:.3rem .7rem; border-radius:100px; }
    .svc-badge.weight { background:#e0f2f1; color:#00695c; }
    .svc-badge.piece { background:#fff3e0; color:#e65100; }
    .svc-badge.package { background:#e8f5e9; color:#2e7d32; }
    .svc-card h5 { font-size:1rem; font-weight:700; margin:.25rem 0 0; }
    .svc-desc { font-size:.82rem; color:var(--muted); margin:.35rem 0 .25rem; }
    .svc-thumb { position:relative; border-radius:12px; overflow:hidden; aspect-ratio:16/9; margin-bottom:.85rem; background:var(--accent-soft); }
    .svc-thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:transform var(--dur-long) var(--ease-out); }
    .svc-card:hover .svc-thumb img { transform:scale(1.05); }
    .svc-thumb .svc-badge { position:absolute; top:.6rem; right:.6rem; background:rgba(255,255,255,.92); box-shadow:var(--sh-sm); }
    .svc-price { font-size:1.5rem; font-weight:800; color:var(--navy); letter-spacing:-.02em; }
    .svc-price small { font-size:.78rem; font-weight:600; color:var(--muted); }
    .svc-days { margin-top:auto; padding-top:.75rem; border-top:1px dashed var(--line); font-size:.8rem; color:var(--muted); }
    .svc-days i { color:var(--accent); margin-right:.3rem; }

    /* ---------- About ---------- */
    .about-grid { display:grid; grid-template-columns:1.1fr .9fr; gap:4rem; align-items:center; }
    .about-text p { color:var(--muted); font-size:.95rem; line-height:1.8; }
    .about-chips { display:flex; flex-wrap:wrap; gap:.75rem; margin-top:1.5rem; }
    .chip { background:#fff; border:1px solid var(--line); border-radius:100px; padding:.55rem 1.1rem; font-size:.82rem; font-weight:600; color:var(--ink); box-shadow:var(--sh-sm); }
    .chip i { color:var(--accent); margin-right:.4rem; }
    .about-visual { border-radius:var(--r-lg); overflow:hidden; aspect-ratio:5/4; box-shadow:var(--sh-md); position:relative; }
    .about-visual img { width:100%; height:100%; object-fit:cover; }
    .about-visual.icon-fallback { background:linear-gradient(150deg,var(--accent-soft),#fff); display:flex; align-items:center; justify-content:center; }
    .about-visual.icon-fallback i { font-size:4.5rem; color:var(--accent); opacity:.5; }

    /* ---------- Testimonials ---------- */
    .lp-testi { background:var(--surface); }
    .testi-card { background:#fff; border:1px solid var(--line); border-radius:var(--r-md); padding:1.75rem; height:100%; display:flex; flex-direction:column; position:relative; transition:transform var(--dur-short) var(--ease-out), box-shadow var(--dur-short) var(--ease-out); }
    .testi-card:hover { transform:translateY(-4px); box-shadow:var(--sh-md); }
    .testi-card .quote-mark { font-family:var(--font-display); font-size:3.2rem; line-height:1; color:var(--accent-soft); position:absolute; top:.75rem; right:1.25rem; user-select:none; }
    .testi-card .stars { color:var(--gold); font-size:.8rem; margin-bottom:.75rem; letter-spacing:.06em; }
    .testi-card blockquote { font-size:.9rem; color:#3b4368; line-height:1.7; margin-bottom:1.25rem; flex:1; }
    .testi-card .author { display:flex; align-items:center; gap:.75rem; }
    .testi-card .avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(140deg,var(--navy),var(--accent)); color:#fff; font-weight:700; font-size:.9rem; display:flex; align-items:center; justify-content:center; }
    .testi-card .name { font-weight:700; font-size:.85rem; }

    /* ---------- Gallery ---------- */
    .gallery-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1.1rem; grid-auto-rows:210px; }
    .gallery-item { border-radius:var(--r-md); overflow:hidden; position:relative; }
    .gallery-item.featured { grid-column:span 2; grid-row:span 2; }
    .gallery-item img { width:100%; height:100%; object-fit:cover; display:block; transition:transform var(--dur-long) var(--ease-out); }
    .gallery-item:hover img { transform:scale(1.05); }
    .gallery-item .caption { position:absolute; inset:auto 0 0 0; padding:2.25rem 1.1rem .9rem; background:linear-gradient(transparent,rgba(9,13,38,.75)); color:#fff; font-size:.82rem; font-weight:600; opacity:0; transform:translateY(6px); transition:opacity var(--dur-short) var(--ease-out), transform var(--dur-short) var(--ease-out); }
    .gallery-item:hover .caption, .gallery-item:focus-within .caption { opacity:1; transform:translateY(0); }

    /* ---------- CTA band ---------- */
    .lp-cta { padding:0; }
    .cta-band { background:linear-gradient(150deg,var(--navy) 0%,var(--navy-dark) 100%); border-radius:var(--r-lg); padding:3.5rem 2.5rem; text-align:center; color:#fff; position:relative; overflow:hidden; box-shadow:var(--sh-lg); }
    .cta-band::before { content:""; position:absolute; inset:0; background:radial-gradient(ellipse 50% 70% at 80% 20%, rgba(66,165,245,.25), transparent 60%); pointer-events:none; }
    .cta-band > * { position:relative; }
    .cta-band h2 { font-family:var(--font-display); font-size:clamp(1.4rem,3vw,2.1rem); font-weight:700; margin-bottom:.6rem; }
    .cta-band p { color:rgba(255,255,255,.7); max-width:480px; margin:0 auto 1.75rem; font-size:.95rem; }

    /* ---------- Map ---------- */
    .lp-map iframe { width:100%; height:360px; border:0; display:block; border-radius:var(--r-lg) var(--r-lg) 0 0; }

    /* ---------- Footer ---------- */
    .lp-footer { background:var(--navy-dark); color:rgba(255,255,255,.6); font-size:.85rem; padding:4rem 0 0; }
    .footer-grid { display:grid; grid-template-columns:1.4fr 1fr 1.2fr 1fr; gap:2.5rem; padding-bottom:2.5rem; }
    .lp-footer h6 { color:#fff; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; margin-bottom:1.1rem; }
    .footer-brand { display:flex; align-items:center; gap:.6rem; color:#fff; font-weight:800; font-size:1rem; margin-bottom:.9rem; }
    .footer-brand img { height:38px; border-radius:8px; background:#fff; padding:2px; }
    .lp-footer a { color:rgba(255,255,255,.6); text-decoration:none; transition:color var(--dur-short); }
    .lp-footer a:hover { color:#fff; }
    .footer-links { list-style:none; padding:0; display:flex; flex-direction:column; gap:.6rem; }
    .footer-contact { display:flex; flex-direction:column; gap:.65rem; }
    .footer-contact i { color:var(--accent); margin-right:.5rem; }
    .footer-social { display:flex; gap:.5rem; margin-top:1.1rem; }
    .footer-social a { width:38px; height:38px; border-radius:50%; background:rgba(255,255,255,.07); display:flex; align-items:center; justify-content:center; font-size:1.05rem; transition:background var(--dur-short), transform var(--dur-short) var(--ease-out); }
    .footer-social a:hover { background:var(--accent); color:#fff; transform:translateY(-2px); }
    .footer-bottom { border-top:1px solid rgba(255,255,255,.08); padding:1.35rem 0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; font-size:.78rem; }

    /* ---------- Floating WA ---------- */
    .floating-wa { position:fixed; bottom:1.5rem; right:1.5rem; z-index:1050; width:56px; height:56px; border-radius:50%; background:var(--wa); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; box-shadow:0 4px 16px rgba(37,211,102,.35); text-decoration:none; transition:opacity .3s, transform .3s var(--ease-out); opacity:0; transform:scale(0); pointer-events:none; }
    .floating-wa.show { opacity:1; transform:scale(1); pointer-events:auto; }
    .floating-wa:hover { transform:scale(1.08); color:#fff; }

    /* ---------- Order status page ---------- */
    .os-hero { padding:9rem 0 5rem; background:linear-gradient(160deg,var(--navy) 0%,var(--navy-dark) 100%); color:#fff; position:relative; overflow:hidden; }
    .os-hero::before { content:""; position:absolute; inset:0; background:radial-gradient(ellipse 55% 60% at 75% 25%, rgba(66,165,245,.2), transparent 60%); pointer-events:none; }
    .os-hero .container { position:relative; z-index:1; text-align:center; }
    .os-hero.compact { padding:8rem 0 2.5rem; }
    .os-hero.compact h1 { font-size:clamp(1.5rem,3.5vw,2.1rem); }
    .os-hero.compact p { margin-bottom:1.5rem; }
    .os-hero h1 { font-family:var(--font-display); font-size:clamp(1.8rem,4.5vw,2.8rem); font-weight:700; margin-bottom:.6rem; }
    .os-hero p { color:rgba(255,255,255,.7); max-width:480px; margin:0 auto 2.25rem; font-size:.95rem; }
    .search-card { max-width:620px; margin:0 auto; background:#fff; border-radius:100px; padding:.45rem .45rem .45rem 1.5rem; box-shadow:var(--sh-lg); display:flex; gap:.5rem; align-items:center; text-align:left; }
    .search-card input { flex:1; border:none; outline:none; font-size:1rem; color:var(--ink); background:transparent; min-width:0; }
    .search-hint { font-size:.8rem; color:rgba(255,255,255,.5); margin-top:1rem; }
    .os-body { background:var(--surface); padding:3.5rem 0 5rem; scroll-margin-top:84px; }
    .status-card { background:#fff; border:1px solid var(--line); border-radius:var(--r-lg); padding:2.25rem; box-shadow:var(--sh-sm); }
    .status-card .head { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; border-bottom:1px solid var(--line); padding-bottom:1.25rem; margin-bottom:1.5rem; }
    .status-card .head h3 { font-family:var(--font-display); font-size:1.4rem; font-weight:700; margin:0; }
    .status-badge { display:inline-flex; align-items:center; gap:.45rem; font-size:.8rem; font-weight:700; padding:.45rem 1rem; border-radius:100px; }
    .stepper { display:flex; justify-content:space-between; position:relative; margin:2rem 0 2.25rem; }
    .stepper::before { content:""; position:absolute; top:17px; left:6%; right:6%; height:2px; background:var(--line); }
    .stepper .bar { position:absolute; top:17px; left:6%; height:2px; background:var(--accent); transition:width .3s; }
    .st { position:relative; z-index:1; text-align:center; flex:1; }
    .st .ic { width:36px; height:36px; margin:0 auto .5rem; border-radius:50%; background:#fff; border:2px solid var(--line); display:flex; align-items:center; justify-content:center; font-size:.85rem; color:var(--muted); }
    .st.done .ic { background:var(--accent-soft); border-color:var(--accent); color:var(--navy); }
    .st.current .ic { background:var(--navy); border-color:var(--navy); color:#fff; box-shadow:0 0 0 5px rgba(26,35,126,.14); }
    .st small { font-size:.68rem; color:var(--muted); display:block; line-height:1.3; }
    .st.current small, .st.done small { color:var(--ink); font-weight:600; }
    .os-panel { border:1px solid var(--line); border-radius:var(--r-md); padding:1.5rem; background:var(--surface); }
    .os-panel > h6, .os-panel > .panel-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin-bottom:1rem; }
    .os-panel .table { margin-bottom:0; font-size:.85rem; }
    .os-panel .table th { background:transparent; border-bottom:1px solid var(--line); }
    .pay-row { display:flex; justify-content:space-between; font-size:.9rem; padding:.35rem 0; }
    .os-empty { text-align:center; padding:3.5rem 1rem; }
    .os-empty i { font-size:3.5rem; color:var(--accent); opacity:.55; display:block; margin-bottom:1rem; }
    .os-empty h3 { font-family:var(--font-display); font-weight:700; }
    .os-empty p { color:var(--muted); max-width:420px; margin:0 auto 1.5rem; }

    /* ---------- Responsive ---------- */
    @media (max-width:991.98px) {
        .hero-grid { grid-template-columns:1fr; gap:2.5rem; }
        .hero-visual { max-width:420px; }
        .hero-float { left:1rem; }
        .about-grid { grid-template-columns:1fr; gap:2.5rem; }
        .about-visual { max-width:480px; }
        .footer-grid { grid-template-columns:1fr 1fr; }
    }
    @media (max-width:767.98px) {
        :root { --space-section:3.75rem; }
        .nav-links { display:none; position:absolute; top:72px; left:0; right:0; background:#fff; flex-direction:column; align-items:flex-start; padding:1.25rem; border-bottom:1px solid var(--line); gap:1rem; box-shadow:var(--sh-md); }
        .nav-links.open { display:flex; }
        .lp-nav-toggler { display:block; }
        .lp-nav.on-dark:not(.scrolled) { background:rgba(13,19,51,.35); backdrop-filter:blur(14px); }
        .steps { grid-template-columns:1fr 1fr; gap:2rem 1rem; }
        .steps::before { display:none; }
        .gallery-grid { grid-template-columns:1fr 1fr; grid-auto-rows:160px; }
        .lp-footer .footer-bottom { justify-content:center; text-align:center; }
        .lp-hero { padding:8rem 0 3.5rem; }
        .status-card { padding:1.5rem; }
        .stepper .st small { display:none; }
        .search-card { padding:.35rem .35rem .35rem 1rem; }
    }
    @media (max-width:480px) {
        .steps { grid-template-columns:1fr; }
        .gallery-grid { grid-template-columns:1fr; grid-auto-rows:auto; }
        .gallery-item { aspect-ratio:4/3; }
        .gallery-item.featured { grid-column:span 1; grid-row:span 1; }
        .hero-float { position:static; margin-top:1rem; }
        .footer-grid { grid-template-columns:1fr; }
    }
    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior:auto; }
        *, *::before, *::after { animation-duration:.01ms!important; animation-iteration-count:1!important; transition-duration:.01ms!important; }
    }
</style>
