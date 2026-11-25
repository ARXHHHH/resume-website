// Interactive layer for the resume site. Inserted into the page by build.js.
// 1. Neon liquid behind the page that the mouse pushes around (WebGL fluid sim,
//    ported from the Moremta site, restyled flat with hard black outlines).
// 2. XP bar + chapter label that follow scroll.
// 3. Side quests scroll sideways past a parallax skyline (desktop only).
// Nothing moves for people who ask for reduced motion.
// Runs once the whole page is parsed: this script sits in the first section, before the rest exists.
document.addEventListener("DOMContentLoaded", function () {
  var root = document.documentElement;
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fine = matchMedia('(pointer: fine)').matches;

  // Move the fixed layers to <body> so they sit outside Elementor's sections.
  ['.ad-liquid', '.ad-hud'].forEach(function (sel) {
    var el = document.querySelector(sel);
    if (el) { document.body.insertBefore(el, document.body.firstChild); }
  });

  /* ---------- 1. Liquid ---------- */

  var PRESSURE_STEPS = 18, VELOCITY_FADE = 0.45, DYE_FADE = 0.62, SWIRL = 7, SPLAT_RADIUS = 0.0017;
  var PALETTE = [[1, 0.18, 0.53], [0, 0.9, 1], [0.89, 1, 0.23], [0.65, 0.55, 0.98], [1, 0.18, 0.53]];
  var PIXEL = matchMedia("(pointer: coarse)").matches ? 5 : 6;
  var IDLE_MS = 7000, MAX_STEPS = 8, STEP_SIZE = 0.035, DYE_STRENGTH = 0.55, FORCE = 5200;

  var VERTEX = '#version 300 es\nin vec2 aPos;\nout vec2 vUv;\nvoid main() { vUv = aPos * .5 + .5; gl_Position = vec4(aPos, 0., 1.); }';
  function frag(body) { return '#version 300 es\nprecision highp float;\nin vec2 vUv;\nout vec4 color;\nuniform vec2 uT;\n' + body; }

  var SHADERS = {
    advect: frag('uniform sampler2D uVel, uSrc; uniform float uDt, uFade;\nvoid main() { vec2 back = vUv - uDt * texture(uVel, vUv).xy * uT; color = texture(uSrc, back) * exp(-uFade * uDt); }'),
    splat: frag('uniform sampler2D uSrc; uniform vec2 uPoint; uniform vec3 uValue; uniform float uAspect, uRadius;\nvoid main() { vec2 p = vUv - uPoint; p.x *= uAspect; color = vec4(texture(uSrc, vUv).rgb + uValue * exp(-dot(p, p) / uRadius), 1.); }'),
    curl: frag('uniform sampler2D uVel;\nvoid main() { float l = texture(uVel, vUv - vec2(uT.x, 0.)).y, r = texture(uVel, vUv + vec2(uT.x, 0.)).y; float b = texture(uVel, vUv - vec2(0., uT.y)).x, t = texture(uVel, vUv + vec2(0., uT.y)).x; color = vec4(.5 * (r - l - t + b), 0., 0., 1.); }'),
    swirl: frag('uniform sampler2D uVel, uCurl; uniform float uDt, uSwirl;\nvoid main() { float l = texture(uCurl, vUv - vec2(uT.x, 0.)).x, r = texture(uCurl, vUv + vec2(uT.x, 0.)).x; float b = texture(uCurl, vUv - vec2(0., uT.y)).x, t = texture(uCurl, vUv + vec2(0., uT.y)).x; float c = texture(uCurl, vUv).x; vec2 force = .5 * vec2(abs(t) - abs(b), abs(r) - abs(l)); force = force / (length(force) + .0001) * uSwirl * c; force.y *= -1.; color = vec4(texture(uVel, vUv).xy + force * uDt, 0., 1.); }'),
    divergence: frag('uniform sampler2D uVel;\nvoid main() { float l = texture(uVel, vUv - vec2(uT.x, 0.)).x, r = texture(uVel, vUv + vec2(uT.x, 0.)).x; float b = texture(uVel, vUv - vec2(0., uT.y)).y, t = texture(uVel, vUv + vec2(0., uT.y)).y; color = vec4(.5 * (r - l + t - b), 0., 0., 1.); }'),
    pressure: frag('uniform sampler2D uPressure, uDivergence;\nvoid main() { float l = texture(uPressure, vUv - vec2(uT.x, 0.)).x, r = texture(uPressure, vUv + vec2(uT.x, 0.)).x; float b = texture(uPressure, vUv - vec2(0., uT.y)).x, t = texture(uPressure, vUv + vec2(0., uT.y)).x; color = vec4((l + r + b + t - texture(uDivergence, vUv).x) * .25, 0., 0., 1.); }'),
    gradient: frag('uniform sampler2D uPressure, uVel;\nvoid main() { float l = texture(uPressure, vUv - vec2(uT.x, 0.)).x, r = texture(uPressure, vUv + vec2(uT.x, 0.)).x; float b = texture(uPressure, vUv - vec2(0., uT.y)).x, t = texture(uPressure, vUv + vec2(0., uT.y)).x; color = vec4(texture(uVel, vUv).xy - .5 * vec2(r - l, t - b), 0., 1.); }'),
    // 8-bit look: drawn at low resolution with chunky pixels, colours locked to a 4-colour neon
    // palette, two-tone shading with checkerboard dithering, a 1-pixel black outline and white glints.
    display: frag([
      'uniform sampler2D uDye;',
      'const vec3 P0 = vec3(1., .18, .53), P1 = vec3(0., .9, 1.), P2 = vec3(.89, 1., .23), P3 = vec3(.65, .55, .98);',
      'float height(vec2 uv) { vec3 c = texture(uDye, uv).rgb; return max(c.r, max(c.g, c.b)); }',
      'vec3 nearest(vec3 c) {',
      '  vec3 best = P0; float d = distance(c, P0), e;',
      '  e = distance(c, P1); if (e < d) { d = e; best = P1; }',
      '  e = distance(c, P2); if (e < d) { d = e; best = P2; }',
      '  e = distance(c, P3); if (e < d) { best = P3; }',
      '  return best;',
      '}',
      'void main() {',
      '  vec3 dye = texture(uDye, vUv).rgb;',
      '  float depth = max(dye.r, max(dye.g, dye.b));',
      '  vec3 hue = nearest(dye / max(depth, .001));',
      '  vec2 slope = vec2(height(vUv - vec2(uT.x, 0.)) - height(vUv + vec2(uT.x, 0.)), height(vUv - vec2(0., uT.y)) - height(vUv + vec2(0., uT.y)));',
      '  vec3 normal = normalize(vec3(slope * 2.2, 1.));',
      '  vec3 light = normalize(vec3(-.45, .6, .8));',
      '  float lit = dot(normal, light);',
      '  float checker = mod(floor(gl_FragCoord.x) + floor(gl_FragCoord.y), 2.);',
      '  float shade = lit > .78 ? .8 : (lit > .62 ? mix(.48, .8, checker) : .48);',
      '  float fill = step(.14, depth);',
      '  float edge = step(.06, depth) - fill;',
      '  float glint = step(.99, dot(normal, normalize(light + vec3(0., 0., 1.))));',
      '  vec3 ink = vec3(.043, .043, .063);',
      '  vec3 col = mix(ink, hue * shade, fill);',
      '  col = mix(col, vec3(0.), edge);',
      '  col = mix(col, vec3(1.), glint * fill * .85);',
      '  color = vec4(col, 1.);',
      '}'
    ].join('\n'))
  };

  function createFluid(canvas, simHeight, dyeHeight) {
    var gl = canvas.getContext('webgl2', { alpha: false, antialias: false, depth: false, stencil: false, powerPreference: 'high-performance' });
    if (!gl) { return null; }
    if (!(gl.getExtension('EXT_color_buffer_float') || gl.getExtension('EXT_color_buffer_half_float'))) { return null; }
    gl.getExtension('OES_texture_float_linear');

    function compile(type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
    var vertex = compile(gl.VERTEX_SHADER, VERTEX);
    var programs = {};
    for (var name in SHADERS) {
      var program = gl.createProgram();
      gl.attachShader(program, vertex);
      gl.attachShader(program, compile(gl.FRAGMENT_SHADER, SHADERS[name]));
      gl.bindAttribLocation(program, 0, 'aPos');
      gl.linkProgram(program);
      if (!gl.getProgramParameter(program, gl.LINK_STATUS)) { return null; }
      var uniforms = {};
      var count = gl.getProgramParameter(program, gl.ACTIVE_UNIFORMS);
      for (var i = 0; i < count; i++) { var u = gl.getActiveUniform(program, i).name; uniforms[u] = gl.getUniformLocation(program, u); }
      programs[name] = { program: program, uniforms: uniforms };
    }

    gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    gl.enableVertexAttribArray(0);
    gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
    gl.disable(gl.BLEND);

    function makeTarget(w, h, filter) {
      var tex = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, tex);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, filter);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, filter);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA16F, w, h, 0, gl.RGBA, gl.HALF_FLOAT, null);
      var fbo = gl.createFramebuffer();
      gl.bindFramebuffer(gl.FRAMEBUFFER, fbo);
      gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, tex, 0);
      gl.clearColor(0, 0, 0, 1);
      gl.clear(gl.COLOR_BUFFER_BIT);
      return { texture: tex, fbo: fbo, width: w, height: h };
    }
    function makePair(w, h, filter) {
      var pair = { read: makeTarget(w, h, filter), write: makeTarget(w, h, filter) };
      pair.swap = function () { var t = pair.read; pair.read = pair.write; pair.write = t; };
      return pair;
    }
    function free(t) { if (t) { gl.deleteTexture(t.texture); gl.deleteFramebuffer(t.fbo); } }

    var aspect = 1, velocity, dye, pressure, curl, divergence;
    function allocate() {
      if (velocity) { [velocity.read, velocity.write, dye.read, dye.write, pressure.read, pressure.write, curl, divergence].forEach(free); }
      var simW = Math.round(simHeight * aspect), dyeW = Math.round(dyeHeight * aspect);
      velocity = makePair(simW, simHeight, gl.LINEAR);
      dye = makePair(dyeW, dyeHeight, gl.LINEAR);
      pressure = makePair(simW, simHeight, gl.NEAREST);
      curl = makeTarget(simW, simHeight, gl.NEAREST);
      divergence = makeTarget(simW, simHeight, gl.NEAREST);
    }
    function bind(name, values) {
      var p = programs[name], unit = 0;
      gl.useProgram(p.program);
      for (var key in values) {
        var loc = p.uniforms[key], v = values[key];
        if (!loc) { continue; }
        if (typeof v === 'number') { gl.uniform1f(loc, v); }
        else if (v.texture) { gl.activeTexture(gl.TEXTURE0 + unit); gl.bindTexture(gl.TEXTURE_2D, v.texture); gl.uniform1i(loc, unit); unit++; }
        else if (v.length === 2) { gl.uniform2f(loc, v[0], v[1]); }
        else { gl.uniform3f(loc, v[0], v[1], v[2]); }
      }
    }
    function draw(target) {
      gl.bindFramebuffer(gl.FRAMEBUFFER, target ? target.fbo : null);
      gl.viewport(0, 0, target ? target.width : canvas.width, target ? target.height : canvas.height);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }

    return {
      resize: function (w, h) {
        // Chunky pixels: the canvas is drawn small and scaled up without smoothing (CSS image-rendering).
        canvas.width = Math.round(w / PIXEL);
        canvas.height = Math.round(h / PIXEL);
        var next = w / h;
        // A phone's address bar changes the height a little. That should not wipe the liquid.
        if (!velocity || Math.abs(next - aspect) / aspect > 0.12) { aspect = next; allocate(); }
      },
      splat: function (x, y, dx, dy, rgb) {
        bind('splat', { uSrc: velocity.read, uPoint: [x, y], uValue: [dx, dy, 0], uAspect: aspect, uRadius: SPLAT_RADIUS * 1.4 });
        draw(velocity.write); velocity.swap();
        bind('splat', { uSrc: dye.read, uPoint: [x, y], uValue: rgb, uAspect: aspect, uRadius: SPLAT_RADIUS });
        draw(dye.write); dye.swap();
      },
      step: function (dt) {
        var t = [1 / velocity.read.width, 1 / velocity.read.height];
        bind('curl', { uT: t, uVel: velocity.read }); draw(curl);
        bind('swirl', { uT: t, uVel: velocity.read, uCurl: curl, uDt: dt, uSwirl: SWIRL }); draw(velocity.write); velocity.swap();
        bind('divergence', { uT: t, uVel: velocity.read }); draw(divergence);
        for (var i = 0; i < PRESSURE_STEPS; i++) { bind('pressure', { uT: t, uPressure: pressure.read, uDivergence: divergence }); draw(pressure.write); pressure.swap(); }
        bind('gradient', { uT: t, uPressure: pressure.read, uVel: velocity.read }); draw(velocity.write); velocity.swap();
        bind('advect', { uT: t, uVel: velocity.read, uSrc: velocity.read, uDt: dt, uFade: VELOCITY_FADE }); draw(velocity.write); velocity.swap();
        bind('advect', { uT: t, uVel: velocity.read, uSrc: dye.read, uDt: dt, uFade: DYE_FADE }); draw(dye.write); dye.swap();
      },
      render: function () {
        bind('display', { uT: [1 / dye.read.width, 1 / dye.read.height], uDye: dye.read });
        draw(null);
      }
    };
  }

  function colorAt(pos) {
    var scaled = (((pos % 1) + 1) % 1) * PALETTE.length;
    var i = Math.floor(scaled), mix = scaled - i;
    var a = PALETTE[i], b = PALETTE[(i + 1) % PALETTE.length];
    return a.map(function (v, c) { return (v + (b[c] - v) * mix) * DYE_STRENGTH; });
  }

  (function startLiquid() {
    var canvas = document.querySelector('.ad-liquid canvas');
    if (!canvas || reduce) { if (canvas) { canvas.hidden = true; } return; }
    var coarse = matchMedia('(pointer: coarse)').matches;
    var fluid = createFluid(canvas, coarse ? 96 : 128, coarse ? 480 : 720);
    if (!fluid) { canvas.hidden = true; return; }
    root.classList.add('ad-liquid-on');

    var frame = 0, lastTime = 0, lastActivity = 0, last = null, hue = Math.random(), queued = [];

    function pour(x, y, dx, dy) {
      hue += 0.012 + Math.hypot(dx, dy) * 0.5;
      fluid.splat(x, y, dx * FORCE, dy * FORCE, colorAt(hue));
    }
    function tick(now) {
      var dt = Math.min((now - lastTime) / 1000 || 1 / 60, 1 / 60);
      lastTime = now;
      queued = queued.filter(function (q) { if (q.at > now) { return true; } pour(q.x, q.y, q.dx, q.dy); return false; });
      fluid.step(dt);
      fluid.render();
      // Stop drawing after a few idle seconds to save battery.
      frame = (queued.length || now - lastActivity < IDLE_MS) ? requestAnimationFrame(tick) : 0;
    }
    function wake() {
      lastActivity = performance.now();
      if (frame) { return; }
      lastTime = lastActivity;
      frame = requestAnimationFrame(tick);
    }
    // Long pointer jumps are filled in, so the stream stays one unbroken ribbon.
    function moveTo(cx, cy) {
      var x = cx / innerWidth, y = 1 - cy / innerHeight;
      if (last) {
        var dx = x - last.x, dy = y - last.y, dist = Math.hypot(dx, dy);
        if (dist > 0.001) {
          var steps = Math.min(MAX_STEPS, Math.ceil(dist / STEP_SIZE));
          for (var i = 1; i <= steps; i++) { pour(last.x + dx * i / steps, last.y + dy * i / steps, dx / steps, dy / steps); }
          wake();
        }
      }
      last = { x: x, y: y };
    }
    // One slow pour on load shows the effect without a mouse, including on phones.
    function sweep() {
      var start = performance.now() + 350, n = 26;
      function path(v) { var z = (v * 4) % 1; return { x: 0.08 + v * 0.84, y: 0.5 + (z < 0.5 ? z : 1 - z) * 0.6 - 0.15 }; } // zigzag, like a level path
      queued = [];
      for (var i = 0; i < n; i++) {
        var a = path(i / (n - 1)), b = path((i + 1) / (n - 1));
        queued.push({ at: start + i * 42, x: a.x, y: a.y, dx: b.x - a.x, dy: b.y - a.y });
      }
      wake();
    }
    function fit() { fluid.resize(innerWidth, innerHeight); }

    fit();
    sweep();
    addEventListener('pointermove', function (e) { moveTo(e.clientX, e.clientY); }, { passive: true });
    addEventListener('touchmove', function (e) { var t = e.touches[0]; if (t) { moveTo(t.clientX, t.clientY); } }, { passive: true });
    addEventListener('touchend', function () { last = null; }, { passive: true });
    root.addEventListener('pointerleave', function () { last = null; });
    addEventListener('resize', fit);
    canvas.addEventListener('webglcontextlost', function () { cancelAnimationFrame(frame); });
  })();

  /* ---------- 2 + 3. Scroll: XP bar, chapter label, sideways side quests ---------- */

  var fill = document.querySelector('.ad-xp__fill');
  var player = document.querySelector('.ad-xp__player');
  var label = document.querySelector('.ad-hud__chapter');
  var chapters = [['boot', 'BOOT'], ['origin', 'ORIGIN'], ['missions', 'MISSIONS'], ['projects', 'SIDE QUESTS'], ['loadout', 'LOADOUT'], ['contact', 'NEXT MISSION']];

  var quest = document.getElementById('projects');
  var track = quest && quest.querySelector('.ad-row');
  var stage = quest && quest.querySelector(':scope > .e-con-inner');
  var sideways = matchMedia('(min-width: 1025px)');
  var travel = 0;

  // Maze chase: a square chomper eats pellets and bugs as you scroll through the side quests.
  var maze, chomper, scoreEl, pellets = [], bugs = [], lastScore = 0, lastQ = 0;
  var BUG_NAMES = ['BUG', '404', 'NaN', 'NULL'];
  function buildMaze() {
    maze = document.createElement('div');
    maze.className = 'ad-maze';
    maze.setAttribute('aria-hidden', 'true');
    maze.innerHTML = '<div class="ad-maze__lane"></div><div class="ad-maze__score">SCORE 000000</div>';
    var lane = maze.firstChild;
    var bugAt = [0.2, 0.42, 0.64, 0.86];
    for (var i = 0; i < 48; i++) {
      var x = 0.04 + i * (0.94 / 47);
      if (bugAt.some(function (b) { return Math.abs(b - x) < 0.02; })) { continue; }
      var d = document.createElement('i'); d.className = 'ad-pellet'; d.style.left = (x * 100) + '%';
      lane.appendChild(d); pellets.push({ el: d, x: x });
    }
    bugAt.forEach(function (x, i) {
      var b = document.createElement('b'); b.className = 'ad-bug'; b.textContent = BUG_NAMES[i]; b.style.left = (x * 100) + '%';
      lane.appendChild(b); bugs.push({ el: b, x: x });
    });
    chomper = document.createElement('div'); chomper.className = 'ad-chomper';
    lane.appendChild(chomper);
    scoreEl = maze.lastChild;
    stage.appendChild(maze);
  }
  function popup(text, x) {
    var p = document.createElement('span'); p.className = 'ad-xp-pop'; p.textContent = text; p.style.left = (x * 100) + '%';
    maze.firstChild.appendChild(p);
    setTimeout(function () { p.remove(); }, 900);
  }
  function updateMaze(q) {
    var x = 0.02 + q * 0.94;
    chomper.style.left = (x * 100) + '%';
    chomper.classList.toggle('is-moving', q > 0 && q < 1);
    chomper.classList.toggle('is-back', q < lastQ);
    var score = 0;
    pellets.forEach(function (p) { var eaten = p.x < x; p.el.classList.toggle('is-eaten', eaten); if (eaten) { score += 10; } });
    var fixed = 0;
    bugs.forEach(function (b) {
      var eaten = b.x < x;
      if (eaten && !b.el.classList.contains('is-eaten') && q > lastQ) { popup('+100 XP', b.x); }
      b.el.classList.toggle('is-eaten', eaten);
      if (eaten) { score += 100; fixed++; }
    });
    if (score !== lastScore) {
      scoreEl.textContent = fixed === bugs.length ? 'ALL BUGS FIXED · ' + String(score).padStart(6, '0') : 'SCORE ' + String(score).padStart(6, '0');
      scoreEl.classList.toggle('is-done', fixed === bugs.length);
      lastScore = score;
    }
    lastQ = q;
  }

  function setupSideways() {
    if (!quest || !track || !stage) { return; }
    var on = sideways.matches && !reduce;
    root.classList.toggle('ad-sideways', on);
    if (!on) { quest.style.height = ''; track.style.transform = ''; return; }
    if (!maze) { buildMaze(); }
    travel = Math.max(0, track.scrollWidth - stage.clientWidth + 80);
    quest.style.height = (innerHeight + travel) + 'px';
  }

  function onScroll() {
    var max = root.scrollHeight - root.clientHeight;
    var p = max > 0 ? Math.min(1, root.scrollTop / max) : 0;
    fill.style.width = (p * 100) + '%';
    player.style.left = 'calc(' + (p * 100) + '% - ' + (p * 22) + 'px)';
    var cur = 0;
    chapters.forEach(function (c, i) {
      var el = document.getElementById(c[0]);
      if (el && el.getBoundingClientRect().top < innerHeight * 0.5) { cur = i; }
    });
    label.textContent = 'CH 0' + cur + ' / 05 · ' + chapters[cur][1];

    if (root.classList.contains('ad-sideways') && travel > 0) {
      var r = quest.getBoundingClientRect();
      var q = Math.min(1, Math.max(0, -r.top / (r.height - innerHeight)));
      track.style.transform = 'translateX(' + (-q * travel) + 'px)';
      updateMaze(q);
    }
  }

  setupSideways();
  addEventListener('scroll', onScroll, { passive: true });
  addEventListener('resize', function () { setupSideways(); onScroll(); });
  addEventListener('load', function () { setupSideways(); onScroll(); });
  onScroll();

  /* ---------- Mouse light: hard shadows fall away from the pointer ---------- */
  if (!reduce && fine) {
    addEventListener('mousemove', function (e) {
      var mx = (e.clientX / innerWidth) * 2 - 1, my = (e.clientY / innerHeight) * 2 - 1;
      root.style.setProperty('--sx', (9 - mx * 6).toFixed(1) + 'px');
      root.style.setProperty('--sy', (9 - my * 6).toFixed(1) + 'px');
      root.style.setProperty('--gx', (mx * 9).toFixed(1) + 'px');
      root.style.setProperty('--gy', (my * 4).toFixed(1) + 'px');
    });
  }
});
