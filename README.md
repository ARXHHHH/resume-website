# Anushka Das — Resume Website

My personal resume site, built on **WordPress + Elementor** with the **Hello Elementor** theme.
Cyberpunk × neobrutalism look, told as a game in chapters.

**Features**
- Story in chapters: Boot → Origin → Missions → Side Quests → Loadout → Next Mission
- 8-bit neon liquid background you push around with the mouse (WebGL fluid simulation)
- XP bar and chapter HUD that follow your scroll
- Side quests scroll sideways while a chomper eats bugs in a maze
- Playable Numpad Drums demo (Web Audio) and the full Read Piano Music app at `/piano/`
- Elementor entrance animations, Yoast SEO meta + sitemap, reduced-motion support

## Files

| File | What it is |
|---|---|
| `setup.php` | Builds the Elementor page (all sections, content and CSS) |
| `site.js` | Liquid background, XP bar, sideways scroll, maze chase |
| `drums.js` | Numpad Drums demo |
| `piano.html` | Read Piano Music app, served at `/piano/` |
| `build.js` | Generates `blueprint.json` (WordPress Playground) and `import-resume.php` (real host) |
| `blueprint.json` | WordPress Playground blueprint |

## Try it locally

```bash
node build.js
npx @wp-playground/cli@latest server --blueprint=blueprint.json --port=9400
# open http://127.0.0.1:9400
```

## Deploy to a WordPress host

1. Install WordPress, activate **Hello Elementor**, and install + activate: Elementor, Yoast SEO, MonsterInsights, Smart Slider 3, Activity Log.
2. Run `node build.js`, then upload `import-resume.php` next to `wp-load.php`.
3. While logged in as admin, open `https://your-site/import-resume.php` once. It builds the site, writes `/piano/`, and deletes itself.
