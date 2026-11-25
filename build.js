// Builds blueprint.json and a shareable WordPress Playground link from setup.php.
const fs = require('fs');
const path = require('path');

const js = fs.readFileSync(path.join(__dirname, 'site.js'), 'utf8');
const drums = fs.readFileSync(path.join(__dirname, 'drums.js'), 'utf8');
const php = fs.readFileSync(path.join(__dirname, 'setup.php'), 'utf8').replace('__SITE_JS__', () => js).replace('__DRUMS_JS__', () => drums);
const plugin = (slug) => ({ step: 'installPlugin', pluginData: { resource: 'wordpress.org/plugins', slug } });

const blueprint = {
  $schema: 'https://playground.wordpress.net/blueprint-schema.json',
  landingPage: '/',
  preferredVersions: { php: '8.2', wp: process.env.WP_VERSION || 'latest' },
  features: { networking: true },
  steps: [
    { step: 'installTheme', themeData: { resource: 'wordpress.org/themes', slug: 'hello-elementor' }, options: { activate: true } },
    plugin('elementor'),
    plugin('wordpress-seo'),                 // Yoast SEO
    plugin('google-analytics-for-wordpress'), // MonsterInsights (free)
    plugin('smart-slider-3'),
    plugin('aryo-activity-log'),              // Activity Log
    // The piano learning app, served as its own page at /piano/
    { step: 'mkdir', path: '/wordpress/piano' },
    { step: 'writeFile', path: '/wordpress/piano/index.html', data: fs.readFileSync(path.join(__dirname, 'piano.html'), 'utf8') },
    { step: 'runPHP', code: php },
  ],
};

fs.writeFileSync(path.join(__dirname, 'blueprint.json'), JSON.stringify(blueprint, null, 2));
const link = 'https://playground.wordpress.net/#' + Buffer.from(JSON.stringify(blueprint)).toString('base64');
fs.writeFileSync(path.join(__dirname, 'share-link.txt'), link + '\n');
console.log('blueprint.json and share-link.txt written; link length', link.length);

// ---------- import-resume.php: one-time importer for a real WordPress host ----------
// Upload to the WordPress folder (next to wp-load.php), open it once while logged in as admin.
// It builds the same page as Playground, writes /piano/, then deletes itself.
function must(src, from, to) {
  if (!src.includes(from)) { throw new Error('importer: text not found: ' + from.slice(0, 60)); }
  return src.replace(from, () => to);
}
const pianoB64 = fs.readFileSync(path.join(__dirname, 'piano.html')).toString('base64');
let imp = php;
imp = must(imp, "define('WP_DISABLE_FATAL_ERROR_HANDLER', true);\n", '');
imp = must(imp,
  "require_once '/wordpress/wp-load.php';\nwp_set_current_user(1); // Elementor checks permissions when site settings change",
  [
    "require_once __DIR__ . '/wp-load.php';",
    "// Only a logged-in administrator can run this.",
    "if (!current_user_can('manage_options')) { wp_die('Log in to wp-admin as an administrator first, then open this page again.'); }",
    "if (get_template() !== 'hello-elementor') { wp_die('Activate the Hello Elementor theme first (Appearance → Themes), then open this page again.'); }",
    "if (!did_action('elementor/loaded')) { wp_die('Install and activate the Elementor plugin first (Plugins → Add New), then open this page again.'); }",
  ].join('\n'));
// Running it twice updates the same Home page instead of making a second one.
imp = must(imp, '$page_id = wp_insert_post([',
  "$existing = get_page_by_path('home', OBJECT, 'page');\n$page_id = $existing ? $existing->ID : wp_insert_post([");
imp = must(imp, "update_post_meta($page_id, '_elementor_edit_mode', 'builder');",
  "update_post_meta($page_id, '_wp_page_template', 'elementor_header_footer');\nupdate_post_meta($page_id, '_elementor_edit_mode', 'builder');");
imp = must(imp, 'flush_rewrite_rules();', "require_once ABSPATH . 'wp-admin/includes/misc.php';\nflush_rewrite_rules(true);");
imp = must(imp, 'echo "Home page id: $page_id\\n";', [
  "// The piano app at /piano/",
  "wp_mkdir_p(ABSPATH . 'piano');",
  "$piano_ok = file_put_contents(ABSPATH . 'piano/index.html', base64_decode('" + pianoB64 + "')) !== false;",
  "$self_deleted = @unlink(__FILE__);",
  "echo '<!doctype html><meta charset=utf-8><title>Import done</title><body style=\"font:16px system-ui;max-width:640px;margin:60px auto\">';",
  "echo '<h1>✅ Resume site imported</h1><ul>';",
  "echo '<li>Home page: <a href=\"' . esc_url(home_url('/')) . '\">' . esc_html(home_url('/')) . '</a> (page id ' . (int) $page_id . ')</li>';",
  "echo '<li>Piano app: ' . ($piano_ok ? '<a href=\"' . esc_url(home_url('/piano/')) . '\">' . esc_html(home_url('/piano/')) . '</a>' : '❌ could not write /piano/ — tell Claude') . '</li>';",
  "echo '<li>This importer file: ' . ($self_deleted ? 'deleted itself ✅' : '⚠️ could NOT delete itself — delete import-resume.php in File Manager now') . '</li></ul>';",
].join('\n'));
fs.writeFileSync(path.join(__dirname, 'import-resume.php'), imp);
console.log('import-resume.php written;', Math.round(imp.length / 1024), 'KB');
