<?php
// Builds the resume site inside WordPress Playground.
// Theme: cyberpunk x neobrutalism. Structure: a story told in chapters.
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e) { echo "\nLAST ERROR: " . $e['message'] . ' in ' . $e['file'] . ':' . $e['line'] . "\n"; }
});
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once '/wordpress/wp-load.php';
wp_set_current_user(1); // Elementor checks permissions when site settings change

/* ---------- Elementor building blocks (all built-in free widgets) ---------- */

function ad_rid() { return substr(md5(uniqid('', true) . mt_rand()), 0, 7); }

function ad_w($type, $settings, $class = '') {
    if ($class) { $settings['_css_classes'] = $class; }
    return ['id' => ad_rid(), 'elType' => 'widget', 'widgetType' => $type, 'elements' => [], 'settings' => $settings];
}
function ad_heading($text, $class = 'ad-h2', $tag = 'h2') { return ad_w('heading', ['title' => $text, 'header_size' => $tag], $class); }
function ad_text($html, $class = 'ad-body') { return ad_w('text-editor', ['editor' => $html], $class); }
function ad_html($code, $class = '') { return ad_w('html', ['html' => $code], $class); }
function ad_button($label, $url, $class = 'ad-btn') {
    return ad_w('button', ['text' => $label, 'link' => ['url' => $url, 'is_external' => (strpos($url, 'http') === 0 ? 'on' : ''), 'nofollow' => '']], $class);
}
function ad_list($items, $class = 'ad-list') {
    $list = [];
    foreach ($items as $t) { $list[] = ['_id' => ad_rid(), 'text' => $t, 'selected_icon' => ['value' => 'fas fa-caret-right', 'library' => 'fa-solid']]; }
    return ad_w('icon-list', ['icon_list' => $list], $class);
}
function ad_box($children, $class, $settings = [], $inner = true) {
    $settings = array_merge([
        'content_width' => $inner ? 'full' : 'boxed',
        'flex_direction' => 'column',
        'flex_gap' => ['unit' => 'px', 'size' => 18, 'column' => '18', 'row' => '18'],
        'css_classes' => $class,
    ], $settings);
    return ['id' => ad_rid(), 'elType' => 'container', 'isInner' => $inner, 'settings' => $settings, 'elements' => $children];
}
// Elementor's built-in entrance animation (plays when the element scrolls into view).
function ad_animate($el, $name, $delay) {
    if (!empty($el['ad_static'])) { unset($el['ad_static']); return $el; }
    if ($el['elType'] === 'widget' && $el['widgetType'] === 'html') { return $el; }
    if (!empty($el['settings']['_animation']) || !empty($el['settings']['animation'])) { return $el; }
    $p = $el['elType'] === 'widget' ? '_' : '';
    $el['settings'][$p . 'animation'] = $name;
    $el['settings'][$p . 'animation_delay'] = $delay;
    return $el;
}
// A chapter = one full-width section with an anchor id. Its parts slide in one after another.
function ad_chapter($id, $children, $extra_class = '') {
    foreach ($children as $i => $c) {
        $is_tag = isset($c['settings']['_css_classes']) && strpos($c['settings']['_css_classes'], 'ad-tag') !== false;
        $children[$i] = ad_animate($c, $is_tag ? 'zoomIn' : 'fadeInUp', $i * 120);
    }
    return ad_box($children, trim("ad-chapter $extra_class"), ['_element_id' => $id], false);
}
function ad_row($children) {
    foreach ($children as $i => $c) { $children[$i] = ad_animate($c, 'fadeInUp', $i * 150); }
    $row = ad_box($children, 'ad-row', ['flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_wrap' => 'wrap', 'flex_gap' => ['unit' => 'px', 'size' => 28, 'column' => '28', 'row' => '28']]);
    $row['ad_static'] = true; // the row itself stays put; its cards animate
    return $row;
}
function ad_panel($children, $variant = 'pink', $half = true) {
    $s = [];
    if ($half) { $s['width'] = ['unit' => '%', 'size' => 47]; $s['width_mobile'] = ['unit' => '%', 'size' => 100]; }
    return ad_box($children, "ad-panel ad-panel--$variant", $s);
}
// A row of small "try it" buttons inside a card.
function ad_links($links) {
    $buttons = [];
    foreach ($links as $l) { $buttons[] = ad_button($l[0], $l[1], 'ad-btn ad-btn--sm'); }
    return ad_box($buttons, 'ad-links', ['flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => ['unit' => 'px', 'size' => 12, 'column' => '12', 'row' => '12']]);
}
function ad_chips($items) {
    return ad_text('<p class="ad-chips"><span>' . implode('</span><span>', $items) . '</span></p>', 'ad-chips-wrap');
}

/* ---------- The story ---------- */

$marquee_items = 'REACT NATIVE ✦ NODE.JS ✦ JAVA ✦ SPRING BOOT ✦ PYTHON ✦ KAFKA ✦ NEO4J ✦ POSTGRESQL ✦ AWS ✦ WORDPRESS ✦ OPEN TO REMOTE WORK ✦ ';

// Fixed layers + interactive script. The script lives in site.js; build.js inserts it here.
$hud = <<<'HTML'
<div class="ad-liquid" aria-hidden="true"><canvas></canvas></div>
<div class="ad-hud" aria-hidden="true">
  <div class="ad-xp"><div class="ad-xp__fill"></div><div class="ad-xp__player"></div></div>
  <div class="ad-hud__chapter">CH 00 / 05 · BOOT</div>
</div>
<script>
__SITE_JS__
</script>
HTML;

// Numpad Drums demo markup + script (drums.js, inserted by build.js).
$drums_html = <<<'HTML'
<div class="ad-drums"><div class="ad-drums__lcd" aria-live="polite">READY · TAP A PAD OR PRESS A NUMPAD KEY</div><div class="ad-drums__pads"></div></div><script>
__DRUMS_JS__
</script>
HTML;

$data = [
    // 00 — BOOT (hero)
    ad_chapter('boot', [
        ad_html($hud),
        ad_text('<p>&gt; booting anushka.exe<br>&gt; loading: apps, apis, websites<br>&gt; status: <span class="ad-ok">READY FOR NEXT MISSION</span><span class="ad-cursor">_</span></p>', 'ad-term'),
        ad_heading('ANUSHKA DAS', 'ad-name', 'h1'),
        ad_heading('Full-stack software engineer. Almost 3 years shipping apps, APIs and data systems at Verizon and duCharcha.', 'ad-lede', 'p'),
        ad_box([ad_button('▶ Press start', '#origin'), ad_button('Skip to hire me', '#contact', 'ad-btn ad-btn--ghost')], 'ad-cta', ['flex_direction' => 'row', 'flex_wrap' => 'wrap']),
    ], 'ad-hero'),

    ad_box([ad_html('<div class="ad-marquee" aria-hidden="true"><div class="ad-marquee__track"><span>' . $marquee_items . '</span><span>' . $marquee_items . '</span></div></div>')], 'ad-marquee-wrap', ['content_width' => 'full', 'padding' => ['unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true]], false),

    // 01 — ORIGIN
    ad_chapter('origin', [
        ad_heading('// Chapter 01 — Origin', 'ad-tag', 'p'),
        ad_heading('Full stack, end to end.', 'ad-h2'),
        ad_text('<p>I am a full-stack software engineer with almost three years of experience across enterprise and startup teams, from high-volume data pipelines at Verizon to shipping a consumer mobile app as a founding engineer at duCharcha.</p><p>I work across the whole product: mobile and web front ends, backend services and APIs, databases and data pipelines, and the analytics that show how people use it. I care about reliable systems, clear interfaces, and code the next engineer can easily understand.</p><p>Outside work, I am drawn to films and games, where every detail is deliberate. I bring the same attention to detail to the software I build.</p>'),
    ]),

    // 02 — THE GRIND (experience, from the resume)
    ad_chapter('missions', [
        ad_heading('// Chapter 02 — Missions completed', 'ad-tag', 'p'),
        ad_heading('Where I levelled up.', 'ad-h2'),
        ad_panel([
            ad_heading('Mission 03 · Now', 'ad-meta', 'p'),
            ad_heading('Co-founder & Developer — Moremta Technologies', 'ad-h3', 'h3'),
            ad_text('<p>Building websites and web apps for clients with my co-founder.</p>'),
            ad_chips(['React', 'Websites', 'Client work']),
        ], 'cyan', false),
        ad_panel([
            ad_heading('Mission 02 · Jan 2026 – Nov 2026 · Bengaluru', 'ad-meta', 'p'),
            ad_heading('Full-Stack Software Engineer — duCharcha', 'ad-h3', 'h3'),
            ad_text('<p>One of 4 founding engineers on an AI-powered, gamified debate and employability platform. 590+ commits (~107K lines) across 3 codebases in 8 months.</p>'),
            ad_list([
                'Owned Loud, a short-form audio feature, end to end: waveform recorder and player, infinite feed, real-time voting, direct-to-cloud uploads and a streak system',
                'Fixed a crash that disabled VoIP incoming calls on iOS and Android; built native iOS call support and the call screen from scratch',
                'Built the judging and reputation system across 3 services: an AI evaluation service in Python/FastAPI, ranking APIs with queue-based processing, and the Hall of Fame UI',
                'Wrote the app’s first test suite: 563 Jest unit tests plus Maestro end-to-end flows; built Career DNA, a guided onboarding flow',
                'Rebuilt signup, onboarding and profiles (v1 → v2) with a gesture-based image crop editor and referrals; shipped group debates and subscriptions',
            ], 'ad-list ad-list--story'),
            ad_chips(['React Native', 'Expo', 'Node.js', 'Fastify', 'Prisma', 'Python', 'FastAPI', 'LiveKit', 'AWS']),
        ], 'pink', false),
        ad_panel([
            ad_heading('Mission 01 · Feb 2024 – Dec 2025 · Bengaluru', 'ad-meta', 'p'),
            ad_heading('Software Engineer — Verizon', 'ad-h3', 'h3'),
            ad_text('<p>SDE intern to SDE-1, converted to full-time within six months. Worked on network topology and inventory, then Internet Number Asset Management.</p>'),
            ad_list([
                'Rebuilt core ingestion for a Neo4j network topology graph on Flink and Kafka: idempotent MERGE queries, 11 new indices, on a high-volume production pipeline',
                'Cut PostgreSQL export runtime from timeouts to minutes with CTEs, window functions and GIN/GiST indexes; built a Python streaming extractor',
                'Designed Safe-Sync and Safe-Write data-integrity systems (3-layer validation, reviewable SQL, audit archiving) to safely automate corrections across a large production database',
                'Traced a React tree-view bug and rebuilt it as a 6-level auto-expanding navigator (Redux-Saga); shipped dashboard features in React and Spring Boot',
                'Resolved critical API security findings and owned daily root-cause triage',
            ], 'ad-list ad-list--story'),
            ad_chips(['Java', 'Spring Boot', 'Neo4j', 'Kafka', 'Flink', 'PostgreSQL', 'React', 'Redux-Saga']),
        ], 'acid', false),
        ad_panel([
            ad_heading('Tutorial level · 2020 – 2024', 'ad-meta', 'p'),
            ad_heading('B.Tech, Computer Science & Engineering — Manipal Institute of Technology', 'ad-h3', 'h3'),
            ad_text('<p>CGPA 7.64 / 10</p>'),
        ], 'violet', false),
    ]),

    // 03 — SIDE QUESTS (projects)
    ad_chapter('projects', [
        ad_heading('// Chapter 03 — Side quests', 'ad-tag', 'p'),
        ad_heading('Things I shipped. Go play them.', 'ad-h2'),
        ad_row([
            ad_panel([
                ad_heading('Live · iOS · Android · Web', 'ad-meta', 'p'),
                ad_heading('duCharcha', 'ad-h3', 'h3'),
                ad_text('<p>Speak for two minutes and get your Career DNA: a read on your communication and industry fit. Built for Indian students facing placements. I worked on the app, the website and the API behind them.</p>'),
                ad_chips(['React Native', 'Node.js', 'PostgreSQL', 'Analytics']),
                ad_links([
                    ['Try on web ↗', 'https://app.ducharcha.com/'],
                    ['App Store ↗', 'https://apps.apple.com/in/app/ducharcha/id6741090837'],
                    ['Google Play ↗', 'https://play.google.com/store/apps/details?id=com.duonomic.ducharcha'],
                ]),
            ], 'pink'),
            ad_panel([
                ad_heading('Live · Web · Moremta', 'ad-meta', 'p'),
                ad_heading('moremta.in', 'ad-h3', 'h3'),
                ad_text('<p>Our company website, designed and built by us: interactive demos instead of a features list, and a real-time WebGL liquid background you can push around with your mouse.</p>'),
                ad_chips(['React', 'Interactive demos', 'WebGL']),
                ad_links([['Visit moremta.in ↗', 'https://www.moremta.in/']]),
            ], 'acid'),
            ad_panel([
                ad_heading('Python · Automation', 'ad-meta', 'p'),
                ad_heading('Lead Finder', 'ad-h3', 'h3'),
                ad_text('<p>Finds local businesses through the Google Places API, collects public emails from their sites while respecting robots.txt, writes a personalised message for each, and reports where leads drop off.</p>'),
                ad_chips(['Python', 'Google Places API', 'Tests']),
            ], 'cyan'),
            ad_panel([
                ad_heading('Browser · Audio', 'ad-meta', 'p'),
                ad_heading('Numpad Drums', 'ad-h3', 'h3'),
                ad_text('<p>A drum machine you play with your keyboard’s number pad, using real recorded drum samples. <b>Play the demo:</b> tap the pads or use your numpad. (The demo makes its sounds live; the full app uses recorded samples.)</p>'),
                ad_html($drums_html),
                ad_chips(['JavaScript', 'Web Audio', 'Node.js']),
            ], 'violet'),
            ad_panel([
                ad_heading('Web app · Music · Mic', 'ad-meta', 'p'),
                ad_heading('Read Piano Music: Play Along', 'ad-h3', 'h3'),
                ad_text('<p>An app that teaches you to read sheet music. Learn 7 landmark notes, then read the rest by counting steps. Lessons, a note drill, rhythm, songs and a 4-week plan. It listens through your microphone and checks each note you play on a real piano.</p>'),
                ad_chips(['JavaScript', 'Web Audio', 'YIN pitch detection', 'SVG notation']),
                ad_links([['Open the app ▶', '/piano/']]),
            ], 'acid'),
            ad_panel([
                ad_heading('Python · Data', 'ad-meta', 'p'),
                ad_heading('Market Lab', 'ad-h3', 'h3'),
                ad_text('<p>A backtesting lab that tested 240 short-term trading rules on Nifty 50 stocks, with shuffled-data checks to catch luck, plus a daily paper-trading tracker. No real money, just evidence.</p>'),
                ad_chips(['Python', 'Backtesting', 'Data analysis']),
            ], 'pink'),
        ]),
    ]),

    // 04 — LOADOUT (skills, from the resume)
    ad_chapter('loadout', [
        ad_heading('// Chapter 04 — Loadout', 'ad-tag', 'p'),
        ad_heading('What I bring to the next mission.', 'ad-h2'),
        ad_row([
            ad_panel([ad_heading('Languages', 'ad-h3', 'h3'), ad_list(['Java', 'Python', 'JavaScript / TypeScript', 'SQL'])], 'cyan'),
            ad_panel([ad_heading('Front end & mobile', 'ad-h3', 'h3'), ad_list(['React', 'React Native / Expo', 'Redux & Redux-Saga', 'iOS CallKit & APNs', 'HTML & CSS'])], 'pink'),
            ad_panel([ad_heading('Backend', 'ad-h3', 'h3'), ad_list(['Node.js / Fastify', 'Java / Spring Boot', 'Python / FastAPI', 'REST APIs', 'LiveKit (real-time audio)'])], 'acid'),
            ad_panel([ad_heading('Databases', 'ad-h3', 'h3'), ad_list(['PostgreSQL', 'Neo4j', 'MongoDB', 'Oracle', 'Prisma'])], 'violet'),
            ad_panel([ad_heading('Data & cloud', 'ad-h3', 'h3'), ad_list(['Apache Kafka', 'Apache Flink', 'AWS: S3, SQS, CloudWatch', 'Elasticsearch', 'Docker'])], 'cyan'),
            ad_panel([ad_heading('Testing & security', 'ad-h3', 'h3'), ad_list(['Jest (563 unit tests written)', 'Maestro E2E', 'Sysdig', '42Crunch'])], 'pink'),
            ad_panel([ad_heading('Websites', 'ad-h3', 'h3'), ad_list(['WordPress', 'Elementor + Hello Elementor', 'Yoast SEO: meta, keywords, sitemap', 'Brand & style guidelines'])], 'acid'),
            ad_panel([ad_heading('Analytics & tools', 'ad-h3', 'h3'), ad_list(['PostHog', 'AppsFlyer', 'OneSignal push', 'Google Analytics (MonsterInsights)', 'GitHub Copilot'])], 'violet'),
        ]),
    ]),

    // 05 — NEXT MISSION (contact)
    ad_chapter('contact', [
        ad_heading('// Chapter 05 — Next mission', 'ad-tag', 'p'),
        ad_heading('Your project could be the next chapter.', 'ad-h2 ad-h2--big'),
        ad_text('<p>Open to freelance and remote work. Send me the brief. I reply fast and ship on time.</p>'),
        ad_box([ad_button('✉ Email me', 'mailto:arxh.2108@gmail.com'), ad_button('LinkedIn', 'https://www.linkedin.com/in/anushka-das-a67159226/', 'ad-btn ad-btn--ghost')], 'ad-cta', ['flex_direction' => 'row', 'flex_wrap' => 'wrap']),
    ], 'ad-final'),

    // END CREDITS (how this site is built)
    ad_chapter('credits', [
        ad_heading('— End credits —', 'ad-credits-title', 'p'),
        ad_text('<p>Built on <b>WordPress</b> with the <b>Hello Elementor</b> theme and the <b>Elementor</b> page builder, using only Elementor’s built-in widgets and icons.<br>SEO by <b>Yoast</b> · analytics by <b>MonsterInsights</b> · edits tracked with <b>Activity Log</b> · <b>Smart Slider 3</b> installed.</p><p>Written, designed and directed by Anushka Das.</p>', 'ad-credits'),
    ], 'ad-credits-wrap'),
];

/* ---------- Styles (WordPress Additional CSS, small and readable) ---------- */

$css = <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Archivo+Black&family=JetBrains+Mono:wght@400;700&family=Space+Grotesk:wght@400;500;700&display=swap');

:root { --ink:#0b0b10; --paper:#f3efe4; --pink:#ff2e88; --cyan:#00e5ff; --acid:#e4ff3a; --violet:#a78bfa; --muted:#cfcabd; }

body { background:var(--ink); color:var(--paper); font-family:'Space Grotesk',system-ui,sans-serif; overflow-x:clip; }
body::after { content:''; position:fixed; inset:0; pointer-events:none; z-index:9999; background:repeating-linear-gradient(0deg,rgba(0,0,0,.16) 0 1px,transparent 1px 3px); }
.site-header, .site-footer, .page-header { display:none !important; }
::selection { background:var(--pink); color:var(--ink); }

/* Chapters */
body .ad-chapter { padding:110px 20px; }
body .ad-chapter > .e-con-inner { gap:22px; }
body .ad-hero { min-height:min(92vh,880px); justify-content:center; padding-top:60px; }
body .ad-hero > .e-con-inner { justify-content:center; }

/* Type */
body .ad-tag .elementor-heading-title { display:inline-block; font-family:'JetBrains Mono',monospace !important; font-size:13px !important; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--ink) !important; background:var(--acid); padding:7px 14px; border:3px solid #000; box-shadow:5px 5px 0 var(--pink); transform:rotate(-2deg); }
body .ad-h2 .elementor-heading-title { font-family:'Archivo Black',sans-serif !important; font-size:clamp(34px,6vw,68px) !important; line-height:1 !important; text-transform:uppercase; color:var(--paper) !important; max-width:900px; }
body .ad-h2--big .elementor-heading-title { font-size:clamp(40px,8vw,96px) !important; }
body .ad-h3 .elementor-heading-title { font-family:'Archivo Black',sans-serif !important; font-size:24px !important; line-height:1.15 !important; text-transform:uppercase; color:var(--ink) !important; }
body .ad-meta .elementor-heading-title { font-family:'JetBrains Mono',monospace !important; font-size:12px !important; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--ink) !important; opacity:.75; }
body .ad-body, body .ad-body p { font-family:'Space Grotesk',system-ui,sans-serif !important; font-size:20px !important; line-height:1.6 !important; color:var(--muted) !important; max-width:720px; }
body .ad-panel .ad-body, body .ad-panel .ad-body p { color:#22222a !important; font-size:17px !important; }

/* Hero */
body .ad-term, body .ad-term p { font-family:'JetBrains Mono',monospace !important; font-size:15px !important; line-height:1.7 !important; color:var(--cyan) !important; }
.ad-ok { color:var(--acid); font-weight:700; }
.ad-cursor { animation:ad-blink 1s steps(1) infinite; }
body .ad-name .elementor-heading-title { font-family:'Archivo Black',sans-serif !important; font-size:clamp(54px,13vw,168px) !important; line-height:.9 !important; color:var(--paper) !important; text-shadow:var(--gx,5px) var(--gy,0px) var(--pink),calc(var(--gx,5px) * -1) calc(var(--gy,0px) * -1) var(--cyan); animation:ad-glitch 4s infinite; letter-spacing:-.02em; transition:text-shadow .1s; }
body .ad-lede .elementor-heading-title { font-family:'Space Grotesk',sans-serif !important; font-size:clamp(20px,2.6vw,30px) !important; font-weight:500; line-height:1.3 !important; color:var(--paper) !important; max-width:760px; }
body .ad-cta { gap:18px; margin-top:10px; }
body .ad-cta > .e-con-inner { flex-direction:row; flex-wrap:wrap; gap:18px; }

/* Buttons: chunky, press down on hover */
body .ad-btn .elementor-button { font-family:'JetBrains Mono',monospace; font-weight:700; font-size:15px; text-transform:uppercase; letter-spacing:.06em; background:var(--acid); color:var(--ink); border:3px solid #000; border-radius:0; box-shadow:5px 5px 0 var(--pink); padding:17px 26px; transition:transform .1s, box-shadow .1s; }
body .ad-btn .elementor-button:hover { transform:translate(-2px,-2px); box-shadow:7px 7px 0 var(--pink); color:var(--ink); }
body .ad-btn .elementor-button:active { transform:translate(3px,3px); box-shadow:none; }
body .ad-btn .elementor-button:focus-visible { outline:3px solid var(--cyan); outline-offset:3px; }
body .ad-btn--ghost .elementor-button { background:transparent; color:var(--paper); border-color:var(--paper); box-shadow:5px 5px 0 var(--cyan); }
body .ad-btn--ghost .elementor-button:hover { color:var(--paper); box-shadow:7px 7px 0 var(--cyan); }
body .ad-btn--sm .elementor-button { font-size:12px; padding:10px 14px; background:var(--ink); color:var(--paper); box-shadow:4px 4px 0 var(--c,var(--pink)); }
body .ad-btn--sm .elementor-button:hover { color:var(--paper); box-shadow:6px 6px 0 var(--c,var(--pink)); }
body .ad-links { margin-top:6px; }

/* Panels: paper cards, black border, hard neon shadow */
body .ad-panel { background:var(--paper); border:3px solid #000; padding:30px; gap:12px; transition:transform .15s, box-shadow .15s; }
body .ad-panel--pink { --c:var(--pink); }
body .ad-panel--cyan { --c:var(--cyan); }
body .ad-panel--acid { --c:var(--acid); }
body .ad-panel--violet { --c:var(--violet); }
body .ad-panel { box-shadow:var(--sx,9px) var(--sy,9px) 0 var(--c,var(--pink)); }
body .ad-panel:hover { transform:translate(-3px,-3px); box-shadow:calc(var(--sx,9px) + 3px) calc(var(--sy,9px) + 3px) 0 var(--c,var(--pink)); }
body .ad-row > .e-con-inner, body .ad-row { gap:30px; }

/* Chips */
.ad-chips { display:flex; flex-wrap:wrap; gap:8px; margin:6px 0 0; }
.ad-chips span { font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:700; text-transform:uppercase; color:#000; background:#fff; border:2px solid #000; padding:4px 9px; }
.ad-panel--pink .ad-chips span { background:#ffd1e6; }
.ad-panel--cyan .ad-chips span { background:#c7f8ff; }
.ad-panel--acid .ad-chips span { background:#f4ffb3; }
.ad-panel--violet .ad-chips span { background:#e4dcff; }

/* Lists */
body .ad-list .elementor-icon-list-text { font-family:'JetBrains Mono',monospace !important; font-size:16px !important; font-weight:700; color:#16161c !important; }
body .ad-list .elementor-icon-list-icon i { color:var(--pink) !important; }
body .ad-list .elementor-icon-list-item { padding-bottom:6px !important; }
body .ad-list--story .elementor-icon-list-text { font-family:'Space Grotesk',sans-serif !important; font-size:16px !important; font-weight:400 !important; line-height:1.5 !important; color:#22222a !important; }
body .ad-list--story .elementor-icon-list-item { align-items:flex-start !important; padding-bottom:8px !important; }
body .ad-list--story .elementor-icon-list-icon { margin-top:4px; }

/* Marquee strip */
.ad-marquee { overflow:hidden; background:var(--pink); border-block:3px solid #000; transform:rotate(-1.5deg) scale(1.03); }
.ad-marquee__track { display:flex; width:max-content; animation:ad-scroll 32s linear infinite; }
.ad-marquee__track span { font-family:'Archivo Black',sans-serif; font-size:22px; color:var(--ink); padding:14px 0; white-space:pre; }

/* Finale + credits */
body .ad-final { background:linear-gradient(180deg,transparent,rgba(255,46,136,.12)); border-top:3px dashed var(--pink); }
body .ad-credits-wrap { padding:60px 20px 80px; text-align:center; }
body .ad-credits-title .elementor-heading-title { font-family:'JetBrains Mono',monospace !important; font-size:13px !important; letter-spacing:.3em; text-transform:uppercase; color:var(--cyan) !important; text-align:center; }
body .ad-credits, body .ad-credits p { font-family:'JetBrains Mono',monospace !important; font-size:13px !important; line-height:1.9 !important; color:#8d8a80 !important; text-align:center; max-width:760px; margin:0 auto; }
body .ad-credits b { color:var(--paper); }

/* Game HUD: XP bar + walking player + chapter label */
.ad-hud { position:fixed; inset:0 0 auto 0; z-index:9998; pointer-events:none; }
.ad-xp { position:relative; height:12px; background:#000; border-bottom:3px solid #000; }
.ad-xp__fill { height:100%; width:0; background:repeating-linear-gradient(90deg,var(--acid) 0 14px,#c9e600 14px 18px); }
.ad-xp__player { position:absolute; top:6px; left:0; width:22px; height:22px; background:var(--pink); border:3px solid #000; box-shadow:3px 3px 0 var(--cyan); animation:ad-hop .5s steps(2) infinite; }
.ad-hud__chapter { position:fixed; right:16px; top:26px; font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:700; letter-spacing:.12em; color:var(--ink); background:var(--acid); border:3px solid #000; box-shadow:4px 4px 0 var(--pink); padding:6px 10px; }

/* Liquid layer behind everything; the grid sits on top of it like a screen */
.ad-liquid { position:fixed; inset:0; z-index:0; pointer-events:none; height:100lvh; background:radial-gradient(55% 50% at 80% 20%,rgba(255,46,136,.16),transparent 70%),radial-gradient(45% 45% at 12% 80%,rgba(0,229,255,.12),transparent 70%); }
.ad-liquid canvas { display:block; width:100%; height:100%; }
body::before { content:''; position:fixed; inset:0; z-index:1; pointer-events:none; background-image:linear-gradient(rgba(0,229,255,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,255,.07) 1px,transparent 1px); background-size:44px 44px; }
body .elementor { position:relative; z-index:2; }
/* Text that sits straight on the liquid gets a hard black outline so it stays readable */
.ad-liquid-on body .ad-h2 .elementor-heading-title, .ad-liquid-on body .ad-lede .elementor-heading-title { text-shadow:3px 3px 0 #000; }
/* Paragraphs that sit straight on the liquid go in a solid game-style dialog box */
body .ad-chapter > .e-con-inner > .ad-body { background:#0b0b10; border:3px solid #000; outline:2px solid var(--violet); outline-offset:-7px; box-shadow:8px 8px 0 var(--cyan); padding:26px 28px; }
body .ad-chapter > .e-con-inner > .ad-body p:last-child { margin-bottom:0; }
.ad-liquid-on body .ad-term p { text-shadow:2px 2px 0 #000, -1px -1px 0 #000; }

/* Side quests scroll sideways past a parallax skyline (desktop; turned on by site.js) */
.ad-sideways #projects { padding:0 !important; }
.ad-sideways #projects > .e-con-inner { position:sticky !important; top:0; height:100vh !important; min-height:0 !important; max-height:100vh; justify-content:flex-start; padding-top:70px !important; overflow:hidden; padding:0 20px; max-width:none; }
.ad-sideways #projects > .e-con-inner > .elementor-element:not(.ad-row) { width:min(1140px,100%); margin-left:max(0px,calc((100% - 1140px) / 2)); position:relative; z-index:2; }
.ad-sideways #projects .ad-row { flex-wrap:nowrap !important; align-items:flex-start !important; width:max-content; padding-left:max(20px,calc((100vw - 1140px) / 2)); padding-right:120px; position:relative; z-index:2; will-change:transform; }
.ad-sideways #projects .ad-row > .ad-panel { width:420px !important; flex:0 0 420px; }
.ad-sideways #projects .ad-row > .ad-panel:has(.ad-drums) { width:600px !important; flex:0 0 600px; }
.ad-sideways #projects .ad-h2 .elementor-heading-title { font-size:clamp(30px,3.4vw,50px) !important; }
.ad-sideways #projects .ad-drums__pads { grid-auto-rows:38px; }
.ad-sideways #projects .ad-panel:has(.ad-drums) .ad-chips-wrap { display:none; }
.ad-sideways #projects .ad-row > .ad-panel { padding:24px 26px; }
/* Maze chase under the side quests: chomper eats pellets and bugs as you scroll */
.ad-maze { position:absolute; left:0; right:0; bottom:14px; z-index:3; padding:0 max(20px,calc((100vw - 1140px) / 2)); pointer-events:none; }
.ad-maze__lane { position:relative; height:54px; background:#000; border:4px solid var(--cyan); box-shadow:inset 0 0 0 4px #000, inset 0 0 0 7px var(--violet), 6px 6px 0 var(--pink); }
.ad-pellet { position:absolute; top:50%; width:8px; height:8px; margin:-4px 0 0 -4px; background:var(--acid); transition:transform .12s steps(2); }
.ad-pellet.is-eaten, .ad-bug.is-eaten { transform:scale(0); }
.ad-bug { position:absolute; top:50%; transform:translate(-50%,-50%); font:700 11px/1 'JetBrains Mono',monospace; color:#000; background:var(--pink); border:3px solid #000; box-shadow:3px 3px 0 var(--cyan); padding:5px 6px; animation:ad-wiggle .6s steps(2) infinite; transition:transform .15s steps(3); }
.ad-bug.is-eaten { transform:translate(-50%,-50%) scale(0); animation:none; }
.ad-chomper { position:absolute; top:50%; left:2%; width:40px; height:40px; margin:-20px 0 0 -20px; background:var(--acid); border:3px solid #000; box-shadow:4px 4px 0 var(--pink); z-index:2; }
.ad-chomper::before { content:''; position:absolute; top:6px; left:12px; width:6px; height:6px; background:#000; }
.ad-chomper::after { content:''; position:absolute; top:50%; right:-3px; width:22px; height:22px; margin-top:-11px; background:#000; clip-path:polygon(100% 0,0 50%,100% 100%); }
.ad-chomper.is-moving::after { animation:ad-chomp .22s steps(2) infinite; }
.ad-chomper.is-back { transform:scaleX(-1); }
.ad-xp-pop { position:absolute; top:-8px; transform:translateX(-50%); font:700 13px/1 'JetBrains Mono',monospace; color:#000; background:var(--acid); border:2px solid #000; padding:4px 6px; animation:ad-pop .9s steps(6) forwards; }
.ad-maze__score { position:absolute; right:max(20px,calc((100vw - 1140px) / 2)); top:-34px; font:700 13px/1 'JetBrains Mono',monospace; letter-spacing:.12em; color:var(--acid); background:#000; border:3px solid var(--acid); padding:6px 10px; }
.ad-maze__score.is-done { color:#000; background:var(--acid); }
@keyframes ad-chomp { 50% { clip-path:polygon(100% 45%,0 50%,100% 55%); } }
@keyframes ad-wiggle { 50% { margin-top:-3px; } }
@keyframes ad-pop { to { top:-46px; opacity:0; } }

/* Numpad Drums demo: a mini numpad inside the card */
.ad-drums { background:var(--ink); border:3px solid #000; box-shadow:5px 5px 0 var(--violet); padding:12px; }
.ad-drums__lcd { font:700 11px/1.3 'JetBrains Mono',monospace; letter-spacing:.08em; color:var(--acid); background:#000; border:2px solid var(--acid); padding:7px 9px; margin-bottom:12px; min-height:16px; }
.ad-drums__pads { display:grid; grid-template-columns:repeat(4,1fr); grid-auto-rows:46px; gap:8px; }
.ad-pad { all:unset; box-sizing:border-box; cursor:pointer; display:flex; flex-direction:column; justify-content:space-between; padding:5px 6px; background:#1b1b24; border:2px solid #000; box-shadow:3px 3px 0 var(--k); color:var(--paper); transition:transform .06s, box-shadow .06s; touch-action:manipulation; }
.ad-pad--drum { --k:var(--pink); } .ad-pad--metal { --k:var(--cyan); } .ad-pad--perc { --k:var(--acid); }
.ad-pad--tall { grid-row:span 2; } .ad-pad--wide { grid-column:span 2; }
.ad-pad__key { font:700 15px/1 'Archivo Black',sans-serif; }
.ad-pad__name { font:700 8px/1 'JetBrains Mono',monospace; letter-spacing:.06em; text-transform:uppercase; opacity:.75; }
.ad-pad:hover { transform:translate(-1px,-1px); box-shadow:4px 4px 0 var(--k); }
.ad-pad:focus-visible { outline:3px solid var(--acid); outline-offset:2px; }
.ad-pad.is-hit { animation:ad-hit .18s steps(2); }
@keyframes ad-hit { 0% { transform:translate(3px,3px); box-shadow:none; background:var(--k); color:#000; } }
.ad-liquid canvas { image-rendering:pixelated; }

@keyframes ad-hop { 50% { transform:translateY(-4px); } }
@keyframes ad-blink { 50% { opacity:0; } }
@keyframes ad-scroll { to { transform:translateX(-50%); } }
@keyframes ad-glitch {
  0%, 88%, 94%, 100% { transform:none; }
  90% { transform:translateX(4px); }
  92% { transform:translateX(-4px) skewX(-6deg); }
}

@media (max-width:767px) {
  body .ad-chapter { padding:70px 16px; }
  body .ad-panel { padding:22px; }
  body .ad-panel { box-shadow:6px 6px 0 var(--c,var(--pink)); }
  body .ad-name .elementor-heading-title { text-shadow:3px 0 var(--pink),-3px 0 var(--cyan); }
  .ad-hud__chapter { right:10px; top:22px; font-size:10px; }
}
@media (prefers-reduced-motion:reduce) {
  .ad-marquee__track, .ad-cursor, .ad-xp__player, .ad-bug, .ad-chomper::after, body .ad-name .elementor-heading-title { animation:none !important; }
  body .ad-panel, body .ad-btn .elementor-button { transition:none; }
  body .elementor-invisible { visibility:visible !important; }
  body .animated { animation:none !important; }
}
CSS;

/* ---------- Site setup ---------- */

update_option('blogname', 'Anushka Das');
update_option('blogdescription', 'Full-Stack Software Engineer · Apps, APIs, data systems & WordPress');
update_option('permalink_structure', '/%postname%/');

foreach (['hello-world' => 'post', 'sample-page' => 'page'] as $slug => $type) {
    $p = get_page_by_path($slug, OBJECT, $type);
    if ($p) { wp_delete_post($p->ID, true); }
}

$page_id = wp_insert_post([
    'post_title' => 'Home', 'post_name' => 'home', 'post_type' => 'page', 'post_status' => 'publish',
    'page_template' => 'elementor_header_footer',
]);
update_post_meta($page_id, '_elementor_edit_mode', 'builder');
update_post_meta($page_id, '_elementor_template_type', 'wp-page');
update_post_meta($page_id, '_elementor_version', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '3.25.0');
update_post_meta($page_id, '_elementor_data', wp_slash(wp_json_encode($data)));

// Yoast SEO
update_post_meta($page_id, '_yoast_wpseo_title', 'Anushka Das · Full-Stack Software Engineer');
update_post_meta($page_id, '_yoast_wpseo_metadesc', 'Full-stack software engineer (ex-Verizon, duCharcha founding engineer). React Native, Node.js, Java/Spring Boot, Kafka, Neo4j, PostgreSQL and WordPress + Elementor. Open to freelance and remote work.');
update_post_meta($page_id, '_yoast_wpseo_focuskw', 'full-stack software engineer');

update_option('show_on_front', 'page');
update_option('page_on_front', $page_id);

wp_update_custom_css_post($css);

flush_rewrite_rules();
try { if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->files_manager) { \Elementor\Plugin::$instance->files_manager->clear_cache(); } } catch (\Throwable $e) { echo 'Cache clear skipped: ' . $e->getMessage() . "\n"; }
echo "Home page id: $page_id\n";
