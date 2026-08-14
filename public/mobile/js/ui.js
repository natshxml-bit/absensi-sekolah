/* ===== UI Helpers (no modules) ===== */
(function () {
  'use strict';

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === 'class') node.className = attrs[k];
        else if (k === 'html') node.innerHTML = attrs[k];
        else if (k === 'text') node.textContent = attrs[k];
        else if (k === 'onclick') node.addEventListener('click', attrs[k]);
        else if (k === 'style') node.setAttribute('style', attrs[k]);
        else node.setAttribute(k, attrs[k]);
      });
    }
    var kids = children || [];
    if (!Array.isArray(kids)) kids = [kids];
    kids.forEach(function (c) {
      if (c == null) return;
      if (Array.isArray(c)) { c.forEach(function (cc) { if (cc != null) node.appendChild(typeof cc === 'string' ? document.createTextNode(cc) : cc); }); }
      else node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return node;
  }

  function toast(msg, type) {
    var wrap = document.querySelector('.toast-wrap');
    if (!wrap) { wrap = el('div', { class: 'toast-wrap' }); document.body.appendChild(wrap); }
    var t = el('div', { class: 'toast ' + (type || '') }, [msg]);
    wrap.appendChild(t);
    setTimeout(function () { t.remove(); }, 3000);
  }

  function openSheet(title, contentNode, actionsNode) {
    var back = el('div', { class: 'bottom-sheet-back' });
    var sheet = el('div', { class: 'bottom-sheet' });
    sheet.appendChild(el('div', { class: 'sheet-handle' }));
    sheet.appendChild(el('div', { style: 'display:flex;align-items:center;justify-content:space-between;margin-bottom:16px' }, [
      el('h2', { text: title }),
      el('button', { class: 'close-handle', html: '&times;', onclick: function () { closeSheet(h); } }),
    ]));
    sheet.appendChild(contentNode);
    if (actionsNode) sheet.appendChild(el('div', { style: 'margin-top:20px' }, [actionsNode]));
    back.appendChild(sheet);
    back.addEventListener('click', function (e) { if (e.target === back) closeSheet(h); });
    document.body.appendChild(back);
    var h = { back: back, sheet: sheet };
    return h;
  }
  function closeSheet(h) { if (h && h.back) h.back.remove(); }
  function openModal(t, c, a) { return openSheet(t, c, a); }
  function closeModal(h) { closeSheet(h); }

  function field(labelText, inputNode, full) {
    return el('div', { class: full ? 'field full' : 'field' }, [el('label', { text: labelText }), inputNode]);
  }
  function input(type, placeholder, value) {
    var i = el('input', { type: type || 'text', placeholder: placeholder || '' });
    if (value !== undefined) i.value = value;
    return i;
  }
  function select(options, selected) {
    var s = el('select');
    (options || []).forEach(function (o) {
      var opt = el('option', { value: o.value, text: o.label });
      if (String(o.value) === String(selected)) opt.selected = true;
      s.appendChild(opt);
    });
    return s;
  }

  function statusPill(status) {
    var map = { hadir: 'Hadir', terlambat: 'Terlambat', izin: 'Izin', sakit: 'Sakit', alfa: 'Alfa' };
    return el('span', { class: 'status-pill ' + status }, [el('span', { class: 'dot' }), map[status] || status]);
  }
  function badge(s) { return statusPill(s); }

  function avatar(name) {
    var initials = String(name || '?').trim().split(/\s+/).map(function (w) { return w[0]; }).slice(0, 2).join('').toUpperCase();
    return el('div', { class: 'avatar', text: initials });
  }

  var ICONS = {
    home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.75V21h14V9.75"/>',
    chart: '<path d="M4 20V9"/><path d="M10 20V5"/><path d="M16 20v-8"/><path d="M2 20h20"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="3"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    camera: '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
    list: '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6"/><path d="M9 16h6"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
    check: '<circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.5 2.5L16 9"/>',
    x: '<circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6"/><path d="M9 9l6 6"/>',
    pin: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
    inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    cap: '<path d="M12 3 1 9l11 6 11-6-11-6z"/><path d="M5 10.5V17c0 1.5 3 3 7 3s7-1.5 7-3v-6.5"/><path d="M23 9v5"/>',
    plus: '<path d="M12 5v14"/><path d="M5 12h14"/>',
    trash: '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    edit: '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
    calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
    refresh: '<path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
  };

  function icon(name, size) {
    var s = size || 20;
    return '<svg class="ic-svg" xmlns="http://www.w3.org/2000/svg" width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + (ICONS[name] || '') + '</svg>';
  }

  function empty(text) {
    return el('div', { class: 'empty' }, [
      el('span', { style: 'display:flex;justify-content:center;margin-bottom:8px;color:var(--text-faint)', html: icon('inbox', 32) }),
      el('div', { text: text }),
    ]);
  }

  function skel(type, count) {
    var items = [];
    for (var i = 0; i < (count || 1); i++) {
      if (type === 'stat') items.push(el('div', { class: 'skel-stat skeleton' }));
      else if (type === 'row') items.push(el('div', { class: 'skel-row skeleton' }));
      else items.push(el('div', { class: 'skel-line skeleton ' + (['w60','w80','w40'][i % 3]) }));
    }
    return items;
  }
  function skelStats() { return el('div', { class: 'stat-strip' }, skel('stat', 4)); }
  function skelRows(n) { return skel('row', n || 4); }
  function skelCard() { return el('div', { style: 'padding:16px' }, skel('line', 3)); }

  function animateCount(el, target, duration) {
    var startTime = null;
    var dur = duration || 600;
    function step(ts) {
      if (!startTime) startTime = ts;
      var p = Math.min((ts - startTime) / dur, 1);
      el.textContent = String(Math.floor(p * target));
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function progressRing(percent, size, strokeWidth, color) {
    var s = size || 64; var sw = strokeWidth || 5; var r = (s - sw) / 2;
    var circ = 2 * Math.PI * r; var offset = circ - (percent / 100) * circ;
    var container = el('div', { class: 'progress-ring' });
    container.innerHTML = '<svg width="' + s + '" height="' + s + '"><circle class="track" cx="' + s/2 + '" cy="' + s/2 + '" r="' + r + '" stroke-width="' + sw + '"/><circle class="fill" cx="' + s/2 + '" cy="' + s/2 + '" r="' + r + '" stroke-width="' + sw + '" stroke="' + (color || 'var(--primary)') + '" stroke-dasharray="' + circ + '" stroke-dashoffset="' + circ + '"/></svg>';
    var label = el('div', { class: 'label', text: Math.round(percent) + '%' });
    container.appendChild(label);
    setTimeout(function () { container.querySelector('.fill').style.strokeDashoffset = String(offset); }, 50);
    return container;
  }

  function fab(iconName, onClick) {
    var btn = el('button', { class: 'fab', html: icon(iconName || 'plus', 22) });
    if (onClick) btn.addEventListener('click', onClick);
    return btn;
  }

  function swipeable(container, items, renderItem) {
    items.forEach(function (item, i) {
      var row = renderItem(item, i);
      container.appendChild(row);
    });
  }

  function pullToRefresh(onRefresh) {
    var indicator = el('div', { style: 'text-align:center;padding:12px;color:var(--text-faint);font-size:12px;display:none', html: icon('refresh', 16) + ' Tarik ke bawah' });
    var startY = 0; var pulling = false;
    document.addEventListener('touchstart', function (e) { if (window.scrollY === 0) startY = e.touches[0].clientY; }, { passive: true });
    document.addEventListener('touchmove', function (e) {
      if (window.scrollY > 0) return;
      var dy = e.touches[0].clientY - startY;
      if (dy > 60 && !pulling) { pulling = true; indicator.style.display = 'block'; onRefresh(); }
    }, { passive: true });
    document.addEventListener('touchend', function () { pulling = false; indicator.style.display = 'none'; }, { passive: true });
    return indicator;
  }

  function staggerReveal(container, delay) {
    var d = delay || 40;
    var children = Array.from(container.children);
    children.forEach(function (child, i) {
      child.style.opacity = '0';
      child.style.transform = 'translateY(8px)';
      setTimeout(function () {
        child.style.transition = 'opacity .25s ease, transform .25s ease';
        child.style.opacity = '1';
        child.style.transform = 'translateY(0)';
      }, i * d);
    });
  }

  function fmtDate(d) { return d ? String(d).slice(0, 10) : ''; }
  function fmtDateTime(d) { return d ? String(d).slice(0, 16).replace('T', ' ') : ''; }

  /* Ripple */
  document.addEventListener('pointerdown', function (e) {
    var target = e.target.closest('.btn, .absen-circle, .bottomnav a, .fab');
    if (!target || target.disabled) return;
    var rect = target.getBoundingClientRect();
    var size = Math.max(rect.width, rect.height) * 1.2;
    var ripple = document.createElement('span');
    ripple.className = 'ripple';
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
    if (getComputedStyle(target).position === 'static') target.style.position = 'relative';
    target.appendChild(ripple);
    ripple.addEventListener('animationend', function () { ripple.remove(); });
  }, { passive: true });

  window.UI = {
    el: el, toast: toast,
    openSheet: openSheet, closeSheet: closeSheet,
    openModal: openModal, closeModal: closeModal,
    field: field, input: input, select: select,
    badge: badge, statusPill: statusPill, avatar: avatar,
    icon: icon, empty: empty,
    skel: skel, skelStats: skelStats, skelRows: skelRows, skelCard: skelCard,
    animateCount: animateCount, progressRing: progressRing, fab: fab,
    swipeable: swipeable, pullToRefresh: pullToRefresh, staggerReveal: staggerReveal,
    fmtDate: fmtDate, fmtDateTime: fmtDateTime,
  };
})();
