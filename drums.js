// Numpad Drums demo: same pad layout and key mapping as the full app (numpad-drums/index.html),
// but the sounds are made live with Web Audio so the demo needs no sample files.
(function () {
  var box = document.currentScript && document.currentScript.previousElementSibling;
  if (!box || !box.classList.contains('ad-drums')) { return; }
  var grid = box.querySelector('.ad-drums__pads');
  var lcd = box.querySelector('.ad-drums__lcd');

  var PADS = [
    { id: 'Num', codes: ['NumLock', 'NumpadClear'], sound: 'lowTom', name: 'Low Tom', kind: 'drum' },
    { id: '/', codes: ['NumpadDivide', 'Slash'], sound: 'midTom', name: 'Mid Tom', kind: 'drum' },
    { id: '*', codes: ['NumpadMultiply'], sound: 'highTom', name: 'High Tom', kind: 'drum' },
    { id: '-', codes: ['NumpadSubtract', 'Minus'], sound: 'cymbal', name: 'Cymbal A', kind: 'metal' },
    { id: '7', codes: ['Numpad7', 'Digit7'], sound: 'hiHat', name: 'Hi Hat', kind: 'metal' },
    { id: '8', codes: ['Numpad8', 'Digit8'], sound: 'openHat', name: 'Open HH', kind: 'metal' },
    { id: '9', codes: ['Numpad9', 'Digit9'], sound: 'hiHat', name: 'Hi Hat', kind: 'metal' },
    { id: '+', codes: ['NumpadAdd', 'Equal'], sound: 'ride', name: 'Ride', kind: 'metal', cls: 'tall' },
    { id: '4', codes: ['Numpad4', 'Digit4'], sound: 'sidestick', name: 'Sidestick', kind: 'perc' },
    { id: '5', codes: ['Numpad5', 'Digit5'], sound: 'snare', name: 'Snare', kind: 'drum' },
    { id: '6', codes: ['Numpad6', 'Digit6'], sound: 'snare', name: 'Snare', kind: 'drum' },
    { id: '1', codes: ['Numpad1', 'Digit1'], sound: 'crash', name: 'Cymbal B', kind: 'metal' },
    { id: '2', codes: ['Numpad2', 'Digit2'], sound: 'kick', name: 'Kick', kind: 'drum' },
    { id: '3', codes: ['Numpad3', 'Digit3'], sound: 'kick', name: 'Kick', kind: 'drum' },
    { id: '↵', codes: ['NumpadEnter', 'Enter'], sound: 'cymbal', name: 'Cymbal C', kind: 'metal', cls: 'tall' },
    { id: '0', codes: ['Numpad0', 'Digit0'], sound: 'kick', name: 'Kick', kind: 'drum', cls: 'wide' },
    { id: '.', codes: ['NumpadDecimal', 'Period'], sound: 'sidestick', name: 'Sidestick', kind: 'perc' }
  ];

  var ctx = null, noiseBuf = null, master = null;
  function audio() {
    if (!ctx) {
      ctx = new (window.AudioContext || window.webkitAudioContext)();
      master = ctx.createGain(); master.gain.value = 0.8; master.connect(ctx.destination);
      noiseBuf = ctx.createBuffer(1, ctx.sampleRate, ctx.sampleRate);
      var d = noiseBuf.getChannelData(0);
      for (var i = 0; i < d.length; i++) { d[i] = Math.random() * 2 - 1; }
    }
    if (ctx.state === 'suspended') { ctx.resume(); }
    return ctx;
  }
  function env(gain, t, peak, len) {
    gain.gain.setValueAtTime(peak, t);
    gain.gain.exponentialRampToValueAtTime(0.001, t + len);
  }
  function tone(type, f0, f1, len, peak) {
    var t = ctx.currentTime, o = ctx.createOscillator(), g = ctx.createGain();
    o.type = type; o.frequency.setValueAtTime(f0, t); o.frequency.exponentialRampToValueAtTime(f1, t + len);
    env(g, t, peak, len); o.connect(g).connect(master); o.start(t); o.stop(t + len + 0.02);
  }
  function noise(filterType, freq, len, peak, q) {
    var t = ctx.currentTime, s = ctx.createBufferSource(), f = ctx.createBiquadFilter(), g = ctx.createGain();
    s.buffer = noiseBuf; f.type = filterType; f.frequency.value = freq; if (q) { f.Q.value = q; }
    env(g, t, peak, len); s.connect(f).connect(g).connect(master); s.start(t); s.stop(t + len + 0.02);
  }
  var SOUNDS = {
    kick: function () { tone('sine', 160, 42, 0.38, 1); },
    snare: function () { noise('highpass', 1200, 0.2, 0.7); tone('triangle', 220, 160, 0.09, 0.5); },
    sidestick: function () { tone('square', 1100, 700, 0.03, 0.25); noise('bandpass', 2500, 0.04, 0.4, 2); },
    hiHat: function () { noise('highpass', 7500, 0.05, 0.45); },
    openHat: function () { noise('highpass', 7000, 0.32, 0.4); },
    ride: function () { noise('bandpass', 9000, 0.6, 0.3, 1.5); tone('sine', 5200, 5000, 0.4, 0.06); },
    cymbal: function () { noise('highpass', 5500, 0.9, 0.45); },
    crash: function () { noise('highpass', 4000, 1.2, 0.5); },
    lowTom: function () { tone('sine', 140, 80, 0.35, 0.9); },
    midTom: function () { tone('sine', 190, 115, 0.3, 0.85); },
    highTom: function () { tone('sine', 250, 160, 0.26, 0.8); }
  };

  var byCode = {}, els = [];
  PADS.forEach(function (p, i) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'ad-pad ad-pad--' + p.kind + (p.cls ? ' ad-pad--' + p.cls : '');
    b.innerHTML = '<span class="ad-pad__key">' + p.id + '</span><span class="ad-pad__name">' + p.name + '</span>';
    b.setAttribute('aria-label', p.name + ' (key ' + p.id + ')');
    b.addEventListener('pointerdown', function (e) { e.preventDefault(); hit(i); });
    b.addEventListener('keydown', function (e) { if (e.key === ' ') { e.preventDefault(); hit(i); } });
    grid.appendChild(b); els.push(b);
    p.codes.forEach(function (c) { byCode[c] = i; });
  });

  var hits = 0;
  function hit(i) {
    audio();
    var p = PADS[i];
    SOUNDS[p.sound]();
    var el = els[i];
    el.classList.remove('is-hit'); void el.offsetWidth; el.classList.add('is-hit');
    hits++;
    lcd.textContent = '▶ ' + p.name.toUpperCase() + ' · HITS ' + String(hits).padStart(3, '0');
  }

  // Keys only play while the demo is on screen, so normal typing elsewhere is untouched.
  var visible = false;
  new IntersectionObserver(function (entries) { visible = entries[0].isIntersecting; }, { threshold: 0.4 }).observe(box);
  addEventListener('keydown', function (e) {
    if (!visible || e.repeat || e.metaKey || e.ctrlKey || e.altKey) { return; }
    var t = e.target;
    if (t && (t.isContentEditable || /INPUT|TEXTAREA|SELECT/.test(t.tagName))) { return; }
    if (e.code === 'Enter' && t && t.closest && t.closest('a, button')) { return; } // let Enter click links
    var i = byCode[e.code];
    if (i === undefined) { return; }
    e.preventDefault();
    hit(i);
  });
})();
