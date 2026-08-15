/* ===== Pages (no modules) ===== */
(function () {
  'use strict';
  var $ = function (s, p) { return (p || document).querySelector(s); };
  var $$ = function (s, p) { return Array.from((p || document).querySelectorAll(s)); };

  function getTeacherClassId() {
    var token = localStorage.getItem('absen_token');
    if (!token) return null;
    try {
      var payload = JSON.parse(atob(token.split('.')[1]));
      var cls = payload.kelas_id || payload.class_id || payload.kelasId || payload.user?.kelas_id || payload.user?.class_id || payload.user?.kelasId;
      return cls ? String(cls) : null;
    } catch (e) { return null; }
  }

  function getParentChildId() {
    var u = API.getUser();
    return u?.anak_id || u?.child_id || u?.anakId || u?.anak?.id || null;
  }

  function loginPage() {
    var c = UI.el('div', { class: 'login-wrap' });
    var brand = UI.el('div', { class: 'login-brand' });
    brand.appendChild(UI.el('div', { class: 'logo', html: UI.icon('cap', 32) }));
    brand.appendChild(UI.el('h1', { text: 'Absensi Sekolah' }));
    brand.appendChild(UI.el('p', { text: 'Sistem kehadiran digital' }));
    c.appendChild(brand);

    var form = UI.el('div', { class: 'login-form' });
    var err = UI.el('div', { class: 'form-error' });
    var id = UI.input('text', 'Email, NIS, atau nama');
    var pw = UI.input('password', 'Password');
    var btn = UI.el('button', { class: 'btn gradient', html: UI.icon('home', 18) + ' Masuk' });

    btn.addEventListener('click', async function () {
      err.classList.remove('show');
      btn.disabled = true;
      btn.textContent = 'Memproses…';
      try {
        var res = await API.login(id.value.trim(), pw.value);
        if (res.status === 422 || res.status === 401) { err.textContent = 'Email atau password salah.'; err.classList.add('show'); btn.disabled = false; btn.innerHTML = UI.icon('home', 18) + ' Masuk'; return; }
        if (!res.ok) { var j = await res.json().catch(function () { return {}; }); err.textContent = j.message || 'Gagal masuk.'; err.classList.add('show'); btn.disabled = false; btn.innerHTML = UI.icon('home', 18) + ' Masuk'; return; }
        var d = await res.json(); API.setAuth(d.token, d.user); window.APP.routeTo(API.getHome());
      } catch (e) { err.textContent = 'Terjadi kesalahan.'; err.classList.add('show'); btn.disabled = false; btn.innerHTML = UI.icon('home', 18) + ' Masuk'; }
    });
    pw.addEventListener('keydown', function (e) { if (e.key === 'Enter') btn.click(); });

    form.append(err, UI.field('Email / NIS / Nama', id), UI.field('Password', pw), UI.el('div', { style: 'height:8px' }), btn);
    form.appendChild(UI.el('div', { class: 'login-hint', text: 'Gunakan email, nama, atau NIS Anda' }));
    c.appendChild(form);
    return c;
  }

  /* ================= Student ================= */
  async function studentHome() {
    var user = API.getUser(); var c = UI.el('div', { class: 'page' });
    var today = (function(){ var n=new Date(); return n.getFullYear()+'-'+String(n.getMonth()+1).padStart(2,'0')+'-'+String(n.getDate()).padStart(2,'0'); })();

    if (navigator.geolocation && !localStorage.getItem('gps_granted_' + user?.id)) {
      setTimeout(function () {
        var content = UI.el('div', { style: 'text-align:center' });
        content.appendChild(UI.el('div', { style: 'font-size:40px;margin-bottom:12px', html: UI.icon('map-pin', 40) }));
        content.appendChild(UI.el('p', { style: 'font-size:14px;color:var(--text-secondary);margin-bottom:16px', text: 'Aktifkan lokasi GPS untuk melakukan absensi. Data lokasi hanya digunakan untuk validasi kehadiran.' }));
        var btnAllow = UI.el('button', { class: 'btn primary', style: 'width:100%;margin-bottom:8px', text: 'Aktifkan GPS' });
        var btnSkip = UI.el('button', { class: 'btn ghost', style: 'width:100%', text: 'Nanti Saja' });
        btnAllow.onclick = function () {
          navigator.geolocation.getCurrentPosition(
            function () { localStorage.setItem('gps_granted_' + user?.id, '1'); UI.toast('GPS aktif!', 'ok'); UI.closeModal(); },
            function () { UI.toast('GPS gagal diaktifkan', 'error'); UI.closeModal(); },
            { enableHighAccuracy: true, timeout: 10000 }
          );
        };
        btnSkip.onclick = function () { UI.closeModal(); };
        content.append(btnAllow, btnSkip);
        UI.openSheet('Izin Lokasi', content);
      }, 500);
    }

    c.appendChild(UI.el('div', { style: 'display:flex;align-items:center;justify-content:space-between;margin-bottom:4px' }, [
      UI.el('div', {}, [
        UI.el('div', { style: 'font-size:13px;color:var(--text-faint)', text: 'Halo,' }),
        UI.el('h2', { text: (user?.name || 'Siswa').split(' ')[0] }),
      ]),
      UI.el('div', { style: 'font-size:11px;color:var(--text-faint);text-align:right' }, [
        UI.el('div', { text: new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' }) }),
      ]),
    ]));

    var statRow = UI.el('div', { class: 'stat-strip' });
    statRow.append.apply(statRow, UI.skelStats());
    c.appendChild(statRow);

    var hero = UI.el('div', { class: 'hero-absen' });
    var ring = UI.el('button', { class: 'absen-circle' });
    ring.innerHTML = '<span class="pulse"></span><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>';
    ring.onclick = function () { window.APP.routeTo('/student/absen'); };
    hero.append(ring, UI.el('div', { class: 'absen-label', text: 'Absen Hari Ini' }), UI.el('div', { class: 'absen-sub', text: 'Ketik untuk mulai' }));
    c.appendChild(hero);

    var quickGrid = UI.el('div', { style: 'display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px' });
    var btnHistory = UI.el('button', { class: 'btn ghost-2 sm', html: UI.icon('calendar', 16) + ' Riwayat', onclick: function () { window.APP.routeTo('/student/history'); } });
    var btnIzin = UI.el('button', { class: 'btn ghost-2 sm', html: UI.icon('inbox', 16) + ' Izin', onclick: function () { openIzinDialog(user); } });
    btnHistory.style.justifyContent = 'center'; btnIzin.style.justifyContent = 'center';
    quickGrid.append(btnHistory, btnIzin);
    c.appendChild(quickGrid);

    var listWrap = UI.el('div');
    listWrap.append.apply(listWrap, UI.skelRows(3));
    c.appendChild(listWrap);

    try {
      var res = await API.studentDashboard(); if (!res.ok) throw 0;
      var json = await res.json(); var todayData = json.data || json; var todayAtt = todayData.attendance;
      statRow.innerHTML = '';
      if (todayAtt) {
        ring.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.5 2.5L16 9"/></svg>';
        ring.style.background = 'var(--success)';
        hero.querySelector('.absen-label').textContent = 'Sudah Absen';
        hero.querySelector('.absen-sub').textContent = todayAtt.status;
      }

      var histRes = await API.studentHistory();
      var a = [];
      if (histRes.ok) { var histJson = await histRes.json(); a = histJson.data || histJson.attendance || []; }
      listWrap.innerHTML = '';
      if (a.length === 0) { listWrap.appendChild(UI.empty('Belum ada riwayat')); }
      else { a.slice(0, 7).forEach(function (r) {
        listWrap.appendChild(UI.el('div', { class: 'list-item' }, [
          UI.el('div', { class: 'grow' }, [
            UI.el('div', { class: 'name', text: API.fmtDate(r.date || r.tanggal) }),
            UI.el('div', { class: 'sub', text: r.check_in_time || r.time || r.jam || '-' }),
          ]),
          UI.statusPill(r.status),
        ]));
      }); }
    } catch (e) { statRow.innerHTML = ''; statRow.append.apply(statRow, UI.skelStats(4)); }

    return c;
  }

  function openIzinDialog(user) {
    var c = UI.el('div');
    var err = UI.el('div', { class: 'form-error' });
    var tA = UI.select([
      { value: 'izin', label: 'Izin' },
      { value: 'sakit', label: 'Sakit' },
    ]);
    var reason = UI.el('textarea', { rows: '3', placeholder: 'Alasan…' });

    var photoWrap = UI.el('div', { style: 'text-align:center' });
    var photoIn = UI.el('input', { type: 'file', accept: 'image/*', capture: 'environment', style: 'display:none' });
    var photoBtn = UI.el('button', { class: 'btn sm ghost-2', html: UI.icon('camera', 14) + ' Lampirkan Foto (opsional)', style: 'margin-top:4px' });
    var photoPreview = UI.el('div', { style: 'margin-top:8px;display:none;text-align:center' });
    var izinPhoto = null;

    photoBtn.onclick = function () { photoIn.click(); };
    photoIn.onchange = function () {
      var f = photoIn.files && photoIn.files[0];
      if (!f) return;
      izinPhoto = f;
      var reader = new FileReader();
      reader.onload = function (ev) {
        photoPreview.innerHTML = '';
        photoPreview.style.display = 'block';
        var img = UI.el('img', { src: ev.target.result, style: 'max-width:100%;max-height:160px;border-radius:8px;border:1px solid var(--border)' });
        photoPreview.appendChild(img);
      };
      reader.readAsDataURL(f);
    };

    photoWrap.append(photoIn, photoBtn, photoPreview);

    c.append(err, UI.field('Tipe', tA), UI.field('Alasan', reason), photoWrap);
    var btn = UI.el('button', { class: 'btn mt', text: 'Kirim' });
    btn.onclick = async function () {
      btn.disabled = true; btn.textContent = 'Mengirim…'; err.classList.remove('show');
      try {
        var payload = { type: tA.value, reason: reason.value.trim() };
        if (izinPhoto) {
          var b64 = await new Promise(function (res, rej) {
            var r = new FileReader();
            r.onload = function (ev) { res(ev.target.result.split(',')[1]); };
            r.onerror = rej;
            r.readAsDataURL(izinPhoto);
          });
          payload.photo = b64;
        }
        var res = await API.studentIzin(payload);
        var d = await res.json();
        if (!res.ok) { err.textContent = d.message || 'Gagal'; err.classList.add('show'); btn.disabled = false; btn.textContent = 'Kirim'; return; }
        UI.toast('Terkirim', 'ok'); UI.closeModal(h);
      } catch (e) { err.textContent = 'Gagal.'; err.classList.add('show'); btn.disabled = false; btn.textContent = 'Kirim'; }
    };
    c.appendChild(btn);
    var h = UI.openSheet('Ajukan Izin', c);
  }

  async function studentAbsenPage() {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('div', { class: 'section-title', text: 'Absen Hari Ini' }));
    var status = null; var preview = null; var file = null; var photoData = null;
    var gpsLat = null, gpsLng = null;
    var msg = UI.el('div', { class: 'form-error' });
    var gpsInfo = UI.el('div', { style: 'font-size:11px;color:var(--text-faint);margin-bottom:8px;display:flex;align-items:center;gap:6px', text: 'Memuat lokasi GPS…' });
    var gpsRetry = UI.el('button', { class: 'btn sm ghost-2', style: 'display:none;font-size:11px;padding:4px 8px', text: 'Coba Lagi', onclick: function () { requestGPS(); } });
    c.appendChild(UI.el('div', { style: 'display:flex;align-items:center;gap:6px;margin-bottom:8px' }, [gpsInfo, gpsRetry]));

    function requestGPS() {
      gpsInfo.textContent = 'Memuat lokasi GPS…'; gpsInfo.style.color = 'var(--text-faint)'; gpsRetry.style.display = 'none';
      if (!navigator.geolocation) { gpsInfo.textContent = 'Browser tidak mendukung GPS'; gpsInfo.style.color = 'var(--error)'; return; }
      navigator.geolocation.getCurrentPosition(
        function (pos) { gpsLat = pos.coords.latitude; gpsLng = pos.coords.longitude; gpsInfo.textContent = '✓ Lokasi aktif: ' + gpsLat.toFixed(6) + ', ' + gpsLng.toFixed(6); gpsInfo.style.color = 'var(--success)'; },
        function (err) {
          var reason = err.code === 1 ? 'Izin GPS ditolak' : err.code === 2 ? 'Sinyal GPS tidak tersedia' : 'Timeout — coba lagi';
          gpsInfo.textContent = '✗ ' + reason; gpsInfo.style.color = 'var(--error)'; gpsRetry.style.display = 'inline-block';
        },
        { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
      );
    }
    requestGPS();
    var btnKirim = UI.el('button', { class: 'btn gradient', html: UI.icon('check', 16) + ' Kirim' });

    var fileWrap = UI.el('div');
    var fileIn = UI.el('input', { type: 'file', accept: 'image/*', capture: 'environment', style: 'display:none' });
    var isNative = !!(window.Capacitor && window.Capacitor.isNativePlatform);
    var fileBtn = UI.el('button', { class: 'btn ghost-2', html: UI.icon('camera', 16) + ' Ambil Foto', onclick: async function () {
      if (isNative && window.Capacitor.Plugins && window.Capacitor.Plugins.Camera) {
        try {
          var photo = await window.Capacitor.Plugins.Camera.getPhoto({
            quality: 80,
            allowEditing: false,
            resultType: 'dataUrl',
            source: 'CAMERA',
            width: 800,
            height: 800
          });
          var byteStr = atob(photo.dataUrl.split(',')[1]);
          var ab = new ArrayBuffer(byteStr.length);
          var ia = new Uint8Array(ab);
          for (var i = 0; i < byteStr.length; i++) ia[i] = byteStr.charCodeAt(i);
          file = new Blob([ab], { type: 'image/jpeg' });
          preview.src = photo.dataUrl;
          preview.style.display = 'block';
        } catch (e) { console.log('Camera cancelled', e); }
      } else {
        fileIn.click();
      }
    } });
    preview = UI.el('img', { class: 'photo-preview', style: 'display:none' });
    fileIn.onchange = function () {
      if (!fileIn.files || !fileIn.files[0]) return;
      file = fileIn.files[0];
      var reader = new FileReader();
      reader.onload = function (ev) { preview.src = ev.target.result; preview.style.display = 'block'; };
      reader.readAsDataURL(file);
    };
    fileWrap.append(fileIn, fileBtn, preview);

    var tA = UI.el('div', { class: 'filters', style: 'margin-bottom:16px' });
    var btnH = UI.el('button', { class: 'btn sm', text: 'Hadir', onclick: function () { status = 'hadir'; $$('button', tA).forEach(function (b) { b.className = 'btn sm ghost-2'; }); btnH.className = 'btn sm'; } });
    var btnI = UI.el('button', { class: 'btn sm ghost-2', text: 'Izin', onclick: function () { status = 'izin'; $$('button', tA).forEach(function (b) { b.className = 'btn sm ghost-2'; }); btnI.className = 'btn sm'; } });
    var btnS = UI.el('button', { class: 'btn sm ghost-2', text: 'Sakit', onclick: function () { status = 'sakit'; $$('button', tA).forEach(function (b) { b.className = 'btn sm ghost-2'; }); btnS.className = 'btn sm'; } });
    tA.append(btnH, btnI, btnS);
    var reason = UI.el('textarea', { rows: '2', placeholder: 'Alasan (opsional)' });

    btnKirim.onclick = async function () {
      if (!status) { msg.textContent = 'Pilih status'; msg.classList.add('show'); return; }
      if ((status === 'izin' || status === 'sakit') && !reason.value.trim()) { msg.textContent = 'Isi alasan'; msg.classList.add('show'); return; }
      btnKirim.disabled = true; btnKirim.textContent = 'Mengirim…'; msg.classList.remove('show');
      try {
        var res;
        if (status === 'izin' || status === 'sakit') {
          var payload = { type: status, reason: reason.value.trim() };
          if (file) {
            var b64 = await new Promise(function (res, rej) {
              var r = new FileReader();
              r.onload = function (ev) { res(ev.target.result.split(',')[1]); };
              r.onerror = rej;
              r.readAsDataURL(file);
            });
            payload.photo = b64;
          }
          res = await API.studentIzin(payload);
        } else {
          var body = { date: (function(){ var n=new Date(); return n.getFullYear()+'-'+String(n.getMonth()+1).padStart(2,'0')+'-'+String(n.getDate()).padStart(2,'0'); })(), status: status, reason: reason.value.trim() };
          if (gpsLat !== null) body.latitude = gpsLat;
          if (gpsLng !== null) body.longitude = gpsLng;
          var fd = new FormData(); Object.keys(body).forEach(function (k) { fd.append(k, body[k]); });
          if (file) fd.append('photo', file);
          res = await API.studentAbsenSubmit(fd);
        }
        if (res.status === 422) { var d = await res.json(); msg.textContent = d.message || 'Validasi gagal'; msg.classList.add('show'); btnKirim.disabled = false; btnKirim.innerHTML = UI.icon('check', 16) + ' Kirim'; return; }
        if (!res.ok) { var d = await res.json().catch(function () { return {}; }); msg.textContent = d.message || 'Gagal'; msg.classList.add('show'); btnKirim.disabled = false; btnKirim.innerHTML = UI.icon('check', 16) + ' Kirim'; return; }
        UI.toast('Absen berhasil!', 'ok');
        setTimeout(function () { window.APP.routeTo('/student'); }, 600);
      } catch (e) { msg.textContent = 'Gagal mengirim.'; msg.classList.add('show'); btnKirim.disabled = false; btnKirim.innerHTML = UI.icon('check', 16) + ' Kirim'; }
    };

    c.append(msg, fileWrap, UI.el('div', { style: 'height:12px' }), tA, UI.field('Alasan (opsional)', reason), btnKirim);
    return c;
  }

  async function studentHistoryPage() {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('div', { class: 'section-title', text: 'Riwayat Kehadiran' }));
    var list = UI.el('div'); list.append.apply(list, UI.skelRows(4)); c.appendChild(list);
    try {
      var res = await API.studentHistory(); if (!res.ok) throw 0;
      var d = await res.json(); var a = d.attendance || d.data || d || [];
      list.innerHTML = '';
      if (a.length === 0) { list.appendChild(UI.empty('Belum ada riwayat')); return c; }
      a.forEach(function (r) {
        list.appendChild(UI.el('div', { class: 'list-item' }, [
          UI.el('div', { class: 'grow' }, [
            UI.el('div', { class: 'name', text: API.fmtDate(r.date || r.tanggal) }),
            UI.el('div', { class: 'sub', text: r.check_in_time || r.time || r.jam || '-' }),
          ]),
          UI.statusPill(r.status),
        ]));
      });
    } catch (e) { list.innerHTML = ''; list.append.apply(list, UI.skelRows(3)); }
    return c;
  }

  async function studentSchedulePage() {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('div', { class: 'section-title', text: 'Jadwal Pelajaran' }));
    var list = UI.el('div'); list.append.apply(list, UI.skelRows(4)); c.appendChild(list);
    try {
      var res = await API.studentSchedules(); if (!res.ok) throw 0;
      var d = await res.json(); var schedules = d.data || d || [];
      list.innerHTML = '';
      if (!schedules.length) { list.appendChild(UI.empty('Belum ada jadwal')); return c; }
      var dayNames = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
      var today = dayNames[new Date().getDay()];
      var dayOrder = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
      var grouped = {};
      schedules.forEach(function (s) {
        if (!grouped[s.day]) grouped[s.day] = [];
        grouped[s.day].push(s);
      });
      var ordered = dayOrder.filter(function (d) { return grouped[d]; });
      if (today !== 'Sabtu' && today !== 'Minggu' && grouped[today]) {
        ordered = [today].concat(ordered.filter(function (d) { return d !== today; }));
      }
      ordered.forEach(function (day) {
        var isToday = day === today;
        c.appendChild(UI.el('div', { style: 'font-size:12px;font-weight:600;color:' + (isToday ? 'var(--primary)' : 'var(--text-faint)') + ';margin:12px 0 6px' + (isToday ? ';background:var(--primary-bg);padding:6px 10px;border-radius:8px' : ''), text: day + (isToday ? ' (Hari Ini)' : '') }));
        grouped[day].forEach(function (s) {
          list.appendChild(UI.el('div', { class: 'list-item' }, [
            UI.el('div', { class: 'grow' }, [
              UI.el('div', { class: 'name', text: s.subject }),
              UI.el('div', { class: 'sub', text: (s.start_time || '') + ' - ' + (s.end_time || '') + ' | ' + (s.teacher || '') }),
            ]),
          ]));
        });
      });
    } catch (e) { list.innerHTML = ''; list.appendChild(UI.empty('Gagal memuat')); }
    return c;
  }

  /* ================= Admin ================= */
  async function adminOverviewPage() {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('div', { class: 'section-title', text: 'Ringkasan' }));
    var statRow = UI.el('div', { class: 'stat-strip' });
    statRow.append.apply(statRow, UI.skelStats(4)); c.appendChild(statRow);

    var ringWrap = UI.el('div', { style: 'display:flex;justify-content:center;padding:20px 0 12px' });
    ringWrap.appendChild(UI.el('div', { class: 'skeleton', style: 'width:80px;height:80px;border-radius:50%' }));
    c.appendChild(ringWrap);

    var recent = UI.el('div');
    recent.append.apply(recent, UI.skelRows(3)); c.appendChild(recent);

    async function load() {
      try {
        var res = await API.adminDashboard(); if (!res.ok) throw 0;
        var d = await res.json(); var today = d.today || {};
        statRow.innerHTML = '';
        var totalStudents = d.total_students || 0;
        var hadir = today.hadir || 0;
        var terlambat = today.terlambat || 0;
        var belumAbsen = today.belum_absen || 0;
        var pct = totalStudents > 0 ? Math.round((hadir + terlambat) / totalStudents * 100) : 0;
        var items = [
          { n: totalStudents, t: 'Total Siswa', cls: '' },
          { n: hadir, t: 'Hadir', cls: 'green' },
          { n: terlambat, t: 'Terlambat', cls: 'amber' },
          { n: belumAbsen, t: 'Belum Absen', cls: '' },
        ];
        items.forEach(function (it) {
          var card = UI.el('div', { class: 'stat-card ' + it.cls });
          var num = UI.el('div', { class: 'n', text: '0' }); UI.animateCount(num, it.n);
          card.append(num, UI.el('div', { class: 't', text: it.t }));
          statRow.appendChild(card);
        });

        ringWrap.innerHTML = '';
        ringWrap.appendChild(UI.progressRing(pct, 80, 6, 'var(--success)'));

        recent.innerHTML = '';
        recent.appendChild(UI.el('div', { class: 'section-title', text: 'Info Hari Ini' }));
        var infoItems = [
          { n: d.total_teachers || 0, t: 'Guru Aktif' },
          { n: d.total_classes || 0, t: 'Kelas' },
          { n: today.izin || 0, t: 'Izin Hari Ini' },
          { n: today.sakit || 0, t: 'Sakit Hari Ini' },
        ];
        infoItems.forEach(function (it) {
          recent.appendChild(UI.el('div', { class: 'list-item' }, [
            UI.el('div', { class: 'grow' }, [
              UI.el('div', { class: 'name', text: it.t }),
            ]),
            UI.el('div', { class: 'stat-card', style: 'flex:0 0 60px;text-align:center;padding:8px' }, [
              UI.el('div', { class: 'n', text: String(it.n), style: 'font-size:18px' }),
            ]),
          ]));
        });
      } catch (e) { statRow.innerHTML = ''; statRow.append.apply(statRow, UI.skelStats(4)); }
    }
    await load();
    var timer = setInterval(load, 30000);
    c._cleanup = function () { clearInterval(timer); };
    return c;
  }

  async function adminAttendancePage() {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('div', { class: 'section-title', text: 'Absensi Hari Ini' }));
    var sel = {}; var now = new Date(); var filterDate = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');
    var fWrap = UI.el('div', { class: 'filters' });
    var fDate = UI.input('date'); fDate.value = filterDate;
    var fStatus = UI.select([
      { value: '', label: 'Semua Status' }, { value: 'hadir', label: 'Hadir' }, { value: 'terlambat', label: 'Terlambat' },
      { value: 'izin', label: 'Izin' }, { value: 'sakit', label: 'Sakit' }, { value: 'alfa', label: 'Alfa' },
    ]);
    var fSearch = UI.input('text', 'Cari nama…');
    fWrap.append(fDate, fStatus, fSearch); c.appendChild(fWrap);

    var selBar = UI.el('div', { class: 'list-item', style: 'background:var(--primary-tonal);border-radius:var(--r-sm);padding:10px 14px;display:none' });
    var selCount = UI.el('span', { text: '0', style: 'font-weight:700;margin-right:6px' });
    var selDel = UI.el('button', { class: 'btn danger sm', html: UI.icon('trash', 14) + ' Hapus' });
    selBar.append(selCount, UI.el('span', { text: ' dipilih', style: 'font-size:12px;flex:1' }), selDel);
    c.appendChild(selBar);

    var list = UI.el('div');
    list.append.apply(list, UI.skelRows(6)); c.appendChild(list);

    var data = [];
    async function load() {
      list.innerHTML = ''; list.append.apply(list, UI.skelRows(4)); sel = {}; updSelBar();
      try {
        var res = await API.adminAttendance(filterDate); if (!res.ok) throw 0;
        var d = await res.json(); data = d.attendance || d.data || d || [];
        render();
      } catch (e) { list.innerHTML = ''; list.appendChild(UI.empty('Gagal memuat')); }
    }
    function render() {
      var q = fSearch.value.toLowerCase();
      var filtered = data.filter(function (r) {
        var nm = (r.student?.name || r.user?.name || r.user_name || '').toLowerCase();
        var st = r.status || '';
        return (!q || nm.includes(q)) && (!fStatus.value || st === fStatus.value);
      });
      list.innerHTML = '';
      if (!filtered.length) { list.appendChild(UI.empty('Tidak ada data')); return; }
      filtered.forEach(function (r) {
        var id = r.id;
        var row = UI.el('div', { class: 'list-item', style: 'cursor:pointer' });
        var cb = UI.el('input', { type: 'checkbox' }); cb.checked = !!sel[id];
        cb.onchange = function () { if (cb.checked) sel[id] = true; else delete sel[id]; updSelBar(); };
        row.onclick = function (e) { if (e.target.tagName === 'INPUT' || e.target.tagName === 'IMG') return; cb.checked = !cb.checked; cb.onchange(); };
        var detail = UI.el('div', { class: 'grow' }, [
          UI.el('div', { class: 'name', text: r.student?.name || r.user?.name || r.user_name || '-' }),
          UI.el('div', { class: 'sub', text: (r.check_in_time || r.time || r.jam || '-') + (r.notes ? ' · ' + r.notes : '') }),
        ]);
        row.append(cb, UI.avatar(r.student?.name || r.user?.name || r.user_name || '?'), detail, UI.statusPill(r.status));
        if (r.photo_url) {
          var photoImg = UI.el('img', { src: r.photo_url, crossorigin: 'anonymous', style: 'width:40px;height:40px;border-radius:6px;object-fit:cover;margin-left:8px;cursor:pointer;border:1px solid var(--border)', onerror: function() { this.style.display='none'; } });
          photoImg.onclick = function (e) {
            e.stopPropagation();
            var overlay = UI.el('div', { style: 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer' });
            var bigImg = UI.el('img', { src: r.photo_url, crossorigin: 'anonymous', style: 'max-width:90vw;max-height:80vh;border-radius:8px' });
            overlay.onclick = function () { overlay.remove(); };
            overlay.appendChild(bigImg);
            document.body.appendChild(overlay);
          };
          detail.appendChild(photoImg);
        }
        list.appendChild(row);
      });
    }
    function updSelBar() {
      var n = Object.keys(sel).length;
      if (n > 0) { selBar.style.display = 'flex'; selCount.textContent = String(n); }
      else { selBar.style.display = 'none'; }
    }
    selDel.onclick = async function () {
      var ids = Object.keys(sel).map(Number);
      if (!ids.length) return;
      selDel.disabled = true; selDel.textContent = 'Menghapus…';
      try {
        var res = await API.del('/admin/attendance?ids=' + ids.join(','));
        if (!res.ok) throw 0;
        UI.toast(ids.length + ' absen dihapus', 'ok'); await load();
      } catch (e) { UI.toast('Gagal menghapus', 'err'); }
      selDel.disabled = false; selDel.innerHTML = UI.icon('trash', 14) + ' Hapus';
    };
    fDate.onchange = function () { filterDate = fDate.value; load(); };
    fStatus.onchange = function () { render(); };
    fSearch.oninput = function () { render(); };
    await load();
    var timer = setInterval(load, 15000);
    c._cleanup = function () { clearInterval(timer); };
    return c;
  }

  async function adminTeachersPage() {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('div', { class: 'section-title', text: 'Guru' }));
    var list = UI.el('div'); list.append.apply(list, UI.skelRows(4)); c.appendChild(list);
    var fabBtn = UI.fab(UI.icon('plus', 22), function () { openTeacherSheet(); });
    c.appendChild(fabBtn);
    async function load() {
      list.innerHTML = ''; list.append.apply(list, UI.skelRows(4));
      try {
        var res = await API.adminTeachers(); if (!res.ok) throw 0;
        var d = await res.json(); var t = d.teachers || d.data || d || [];
        list.innerHTML = '';
        if (!t.length) { list.appendChild(UI.empty('Belum ada guru')); return; }
        t.forEach(function (r) {
          list.appendChild(UI.el('div', { class: 'list-item' }, [
            UI.avatar(r.name), UI.el('div', { class: 'grow' }, [
              UI.el('div', { class: 'name', text: r.name }), UI.el('div', { class: 'sub', text: r.nip || r.email || '-' }),
            ]),
          ]));
        });
      } catch (e) { list.innerHTML = ''; list.appendChild(UI.empty('Gagal memuat')); }
    }
    function openTeacherSheet() {
      var form = UI.el('div'); var err = UI.el('div', { class: 'form-error' });
      var nm = UI.input('text', 'Nama Lengkap'); var em = UI.input('email', 'Email'); var pw = UI.input('password', 'Password');
      var btn = UI.el('button', { class: 'btn mt', text: 'Simpan' });
      btn.onclick = async function () {
        btn.disabled = true; btn.textContent = 'Menyimpan…'; err.classList.remove('show');
        var body = { name: nm.value.trim(), email: em.value.trim(), password: pw.value };
        if (!body.name || !body.email || !body.password) { err.textContent = 'Semua wajib diisi'; err.classList.add('show'); btn.disabled = false; btn.textContent = 'Simpan'; return; }
        try {
          var res = await API.adminTeachersCreate(body);
          if (!res.ok) { var d = await res.json().catch(function () { return {}; }); err.textContent = d.message || 'Gagal'; err.classList.add('show'); btn.disabled = false; btn.textContent = 'Simpan'; return; }
          UI.closeModal(h); await load();
        } catch (e) { err.textContent = 'Gagal'; err.classList.add('show'); btn.disabled = false; btn.textContent = 'Simpan'; }
      };
      form.append(err, UI.field('Nama', nm), UI.field('Email', em), UI.field('Password', pw), btn);
      var h = UI.openSheet('Tambah Guru', form);
    }
    await load(); return c;
  }

  async function adminSchedulesPage() {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('div', { class: 'section-title', text: 'Jadwal Pelajaran' }));
    var list = UI.el('div'); list.append.apply(list, UI.skelRows(4)); c.appendChild(list);
    var fabBtn = UI.fab(UI.icon('plus', 22), function () { openSheet(); });
    c.appendChild(fabBtn);

    var teachers = []; var classes = [];
    async function load() {
      list.innerHTML = ''; list.append.apply(list, UI.skelRows(4));
      try {
        var [tRes, cRes] = await Promise.all([API.adminTeachers(), API.adminClasses()]);
        if (tRes.ok) { var td = await tRes.json(); teachers = td.teachers || td.data || td || []; }
        if (cRes.ok) { var cd = await cRes.json(); classes = cd.classes || cd.data || cd || []; }
        var res = await API.adminSchedules(); if (!res.ok) throw 0;
        var d = await res.json(); var sc = d.schedules || d.data || d || [];
        list.innerHTML = '';
        if (!sc.length) { list.appendChild(UI.empty('Belum ada jadwal')); return; }
        sc.forEach(function (r) {
          list.appendChild(UI.el('div', { class: 'list-item' }, [
            UI.el('div', { class: 'grow' }, [
              UI.el('div', { class: 'name', text: (r.day || r.hari || '') + ' · ' + (r.subject || r.mapel || '') }),
              UI.el('div', { class: 'sub', text: (r.time || r.jam_mulai || '') + (r.time_end ? ' – ' + r.time_end : r.jam_selesai ? ' – ' + r.jam_selesai : '') }),
              UI.el('div', { class: 'sub', text: 'Guru: ' + (r.teacher?.name || r.guru?.name || r.teacher_name || '-') + ' · Kelas: ' + (r.class?.name || r.kelas?.name || r.class_name || '-') }),
            ]),
          ]));
        });
      } catch (e) { list.innerHTML = ''; list.appendChild(UI.empty('Gagal memuat')); }
    }
    function openSheet() {
      var form = UI.el('div'); var err = UI.el('div', { class: 'form-error' });
      var day = UI.select([{ value: 'Senin', label: 'Senin' },{ value: 'Selasa', label: 'Selasa' },{ value: 'Rabu', label: 'Rabu' },{ value: 'Kamis', label: 'Kamis' },{ value: 'Jumat', label: 'Jumat' },{ value: 'Sabtu', label: 'Sabtu' }]);
      var subject = UI.input('text', 'Nama Pelajaran'); var time = UI.input('text', '08:00'); var timeEnd = UI.input('text', '09:30');
      var tid = UI.select(teachers.map(function (t) { return { value: t.id, label: t.name }; }));
      var cid = UI.select(classes.map(function (cl) { return { value: cl.id, label: cl.name || cl.nama }; }));
      var btn = UI.el('button', { class: 'btn mt', text: 'Simpan' });
      btn.onclick = async function () {
        btn.disabled = true; err.classList.remove('show');
        try {
          var res = await API.adminSchedulesCreate({ day: day.value, subject: subject.value.trim(), time: time.value, time_end: timeEnd.value, teacher_id: tid.value, class_id: cid.value });
          if (!res.ok) { var d = await res.json().catch(function () { return {}; }); err.textContent = d.message || 'Gagal'; err.classList.add('show'); btn.disabled = false; return; }
          UI.closeModal(h); await load();
        } catch (e) { err.textContent = 'Gagal'; err.classList.add('show'); btn.disabled = false; }
      };
      form.append(err, UI.field('Hari', day), UI.field('Pelajaran', subject), UI.field('Jam Mulai', time), UI.field('Jam Selesai', timeEnd), UI.field('Guru', tid), UI.field('Kelas', cid), btn);
      var h = UI.openSheet('Tambah Jadwal', form);
    }
    await load(); return c;
  }

  async function adminStudentsPage() {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('div', { class: 'section-title', text: 'Siswa' }));
    var list = UI.el('div'); list.append.apply(list, UI.skelRows(4)); c.appendChild(list);
    var fabBtn = UI.fab(UI.icon('plus', 22), function () { openSheet(); });
    c.appendChild(fabBtn);
    async function load() {
      list.innerHTML = ''; list.append.apply(list, UI.skelRows(4));
      try {
        var res = await API.adminStudents(); if (!res.ok) throw 0;
        var d = await res.json(); var st = d.students || d.data || d || [];
        list.innerHTML = '';
        if (!st.length) { list.appendChild(UI.empty('Belum ada siswa')); return; }
        st.forEach(function (r) {
          list.appendChild(UI.el('div', { class: 'list-item' }, [
            UI.avatar(r.name), UI.el('div', { class: 'grow' }, [
              UI.el('div', { class: 'name', text: r.name }), UI.el('div', { class: 'sub', text: r.nis || r.email || '-' }),
            ]),
          ]));
        });
      } catch (e) { list.innerHTML = ''; list.appendChild(UI.empty('Gagal memuat')); }
    }
    function openSheet() {
      var form = UI.el('div'); var err = UI.el('div', { class: 'form-error' });
      var nm = UI.input('text', 'Nama Lengkap'); var nis = UI.input('text', 'NIS'); var em = UI.input('email', 'Email');
      var pw = UI.input('password', 'Password'); var cls = UI.input('text', 'Kelas ID');
      var btn = UI.el('button', { class: 'btn mt', text: 'Simpan' });
      btn.onclick = async function () {
        btn.disabled = true; err.classList.remove('show');
        try {
          var res = await API.adminStudentsCreate({ name: nm.value.trim(), nis: nis.value.trim(), email: em.value.trim(), password: pw.value, class_id: cls.value });
          if (!res.ok) { var d = await res.json().catch(function () { return {}; }); err.textContent = d.message || 'Gagal'; err.classList.add('show'); btn.disabled = false; return; }
          UI.closeModal(h); await load();
        } catch (e) { err.textContent = 'Gagal'; err.classList.add('show'); btn.disabled = false; }
      };
      form.append(err, UI.field('Nama', nm), UI.field('NIS', nis), UI.field('Email', em), UI.field('Password', pw), UI.field('Kelas ID', cls), btn);
      var h = UI.openSheet('Tambah Siswa', form);
    }
    await load(); return c;
  }

  async function adminSettingsPage() {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('div', { class: 'section-title', text: 'Pengaturan Sekolah' }));
    var settings = {};
    try {
      var res = await API.adminSettingsGet(); if (res.ok) { var d = await res.json(); settings = d.data || d.settings || d || {}; }
    } catch (e) {}

    var schoolName = UI.input('text', 'Nama Sekolah'); schoolName.value = settings.school_name || '';
    var lat = UI.input('number', 'Latitude'); lat.value = settings.latitude || '';
    var lng = UI.input('number', 'Longitude'); lng.value = settings.longitude || '';
    var rad = UI.input('number', 'Radius (meter)'); rad.value = settings.radius_meters || settings.radius || 100;
    var lateTime = UI.input('text', 'Jam Terlambat (HH:MM)'); lateTime.value = settings.late_time || '07:00';

    var btn = UI.el('button', { class: 'btn mt', html: UI.icon('settings', 16) + ' Simpan Semua' });
    var msg = UI.el('div', { class: 'form-error' });
    btn.onclick = async function () {
      btn.disabled = true; msg.classList.remove('show');
      try {
        var body = {
          school_name: schoolName.value.trim(),
          latitude: lat.value,
          longitude: lng.value,
          radius_meters: rad.value,
          late_time: lateTime.value.trim(),
        };
        var r = await API.adminSettingsSave(body);
        if (!r.ok) { var d = await r.json().catch(function () { return {}; }); msg.textContent = d.message || 'Gagal'; msg.classList.add('show'); btn.disabled = false; return; }
        UI.toast('Tersimpan', 'ok');
      } catch (e) { msg.textContent = 'Gagal'; msg.classList.add('show'); }
      btn.disabled = false;
    };

    c.append(msg,
      UI.field('Nama Sekolah', schoolName),
      UI.field('Latitude', lat),
      UI.field('Longitude', lng),
      UI.field('Radius (meter)', rad),
      UI.field('Jam Terlambat', lateTime),
      btn
    );
    return c;
  }

  /* ================= Teacher ================= */
  async function teacherHomePage() {
    var c = UI.el('div', { class: 'page' }); var user = API.getUser();
    c.appendChild(UI.el('h2', { text: 'Halo, ' + (user?.name || 'Guru').split(' ')[0] }));
    c.appendChild(UI.el('div', { style: 'font-size:12px;color:var(--text-faint);margin-bottom:16px' }, [UI.el('div', { text: 'Kelas Anda:' })]));

    var list = UI.el('div'); list.append.apply(list, UI.skelRows(3)); c.appendChild(list);
    try {
      var res = await API.teacherHome(); if (!res.ok) throw 0;
      var d = await res.json(); var classes = d.data || d || [];
      list.innerHTML = '';
      if (!classes.length) { list.appendChild(UI.empty('Tidak ada kelas')); return c; }
      classes.forEach(function (cl) {
        var subs = (cl.subjects || []).join(', ');
        list.appendChild(UI.el('div', { class: 'list-item', style: 'cursor:pointer', onclick: function () { window.APP.routeTo('/teacher/class/' + cl.id); } }, [
          UI.avatar(cl.name || cl.nama),
          UI.el('div', { class: 'grow' }, [
            UI.el('div', { class: 'name', text: cl.name || cl.nama }),
            UI.el('div', { class: 'sub', text: subs || 'Kelas' }),
          ]),
          UI.el('div', { style: 'font-size:20px;color:var(--text-faint)' }, [document.createTextNode('\u203A')]),
        ]));
      });
    } catch (e) { list.innerHTML = ''; list.appendChild(UI.empty('Gagal memuat')); }
    return c;
  }

  async function teacherClassPage(classId) {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('h2', { text: 'Detail Kelas' }));

    var btnRow = UI.el('div', { style: 'display:flex;gap:8px;margin-bottom:16px' });
    btnRow.appendChild(UI.el('button', {
      class: 'btn btn-primary', style: 'flex:1',
      html: UI.icon('camera', 16) + ' Absen',
      onclick: function () { window.APP.routeTo('/teacher/class/' + classId + '/absen'); }
    }));
    btnRow.appendChild(UI.el('button', {
      class: 'btn btn-outline', style: 'flex:1',
      html: UI.icon('download', 16) + ' Export',
      onclick: async function () {
        try {
          var res = await API.teacherExportAbsen(classId);
          if (!res.ok) throw 0;
          var blob = await res.blob();
          var url = URL.createObjectURL(blob);
          var a = document.createElement('a'); a.href = url; a.download = 'absensi-kelas-' + classId + '.csv';
          a.click(); URL.revokeObjectURL(url);
          UI.toast('File berhasil diunduh', 'success');
        } catch (e) { UI.toast('Gagal export', 'error'); }
      }
    }));
    c.appendChild(btnRow);

    var statRow = UI.el('div', { class: 'stat-strip' });
    statRow.append.apply(statRow, UI.skelStats(4)); c.appendChild(statRow);
    var list = UI.el('div'); list.append.apply(list, UI.skelRows(4)); c.appendChild(list);

    async function load() {
      try {
        var res = await API.teacherClass(classId); if (!res.ok) throw 0;
        var d = await res.json(); var jsonData = d.data || d; var a = jsonData.students || jsonData.attendance || jsonData || [];
        statRow.innerHTML = '';
        var st = { hadir: 0, terlambat: 0, izin: 0, alfa: 0 };
        a.forEach(function (r) { var s = r.attendance?.status || r.status; if (st[s] !== undefined) st[s]++; });
        [
          { n: st.hadir, t: 'Hadir', cls: 'green' }, { n: st.terlambat, t: 'Terlambat', cls: 'amber' },
          { n: st.izin, t: 'Izin', cls: 'blue' }, { n: st.alfa, t: 'Alfa', cls: 'red' },
        ].forEach(function (it) {
          var card = UI.el('div', { class: 'stat-card ' + it.cls });
          var num = UI.el('div', { class: 'n', text: '0' }); UI.animateCount(num, it.n);
          card.append(num, UI.el('div', { class: 't', text: it.t }));
          statRow.appendChild(card);
        });

        list.innerHTML = '';
        if (!a.length) { list.appendChild(UI.empty('Tidak ada data')); return; }
        a.forEach(function (r) {
          var att = r.attendance;
          list.appendChild(UI.el('div', { class: 'list-item' }, [
            UI.avatar(r.name || '-'),
            UI.el('div', { class: 'grow' }, [
              UI.el('div', { class: 'name', text: r.name || '-' }),
              UI.el('div', { class: 'sub', text: att ? (att.check_in_time || '-') : 'Belum absen' }),
            ]),
            att ? UI.statusPill(att.status) : UI.el('span', { class: 'status-pill', style: 'background:var(--bg-2);color:var(--text-faint)', html: '<span class="dot" style="background:var(--text-faint)"></span>Belum' }),
          ]));
        });
      } catch (e) { statRow.innerHTML = ''; statRow.append.apply(statRow, UI.skelStats(4)); }
    }
    await load();
    var timer = setInterval(load, 15000);
    c._cleanup = function () { clearInterval(timer); };
    return c;
  }

  async function teacherAbsenPage(classId) {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('h2', { text: 'Absen Kelas' }));

    var list = UI.el('div'); list.append.apply(list, UI.skelRows(5)); c.appendChild(list);
    try {
      var res = await API.teacherStudents(classId); if (!res.ok) throw 0;
      var d = await res.json(); var students = d.data || d || [];
      list.innerHTML = '';
      if (!students.length) { list.appendChild(UI.empty('Tidak ada siswa')); return c; }

      var currentStatus = {};
      try {
        var attRes = await API.teacherClass(classId);
        if (attRes.ok) {
          var attD = await attRes.json();
          var attData = attD.data || attD;
          (attData.students || attData || []).forEach(function (s) {
            if (s.attendance) currentStatus[s.student_id] = s.attendance.status;
          });
        }
      } catch (e) {}

      students.forEach(function (s) {
        var st = currentStatus[s.id] || '';
        var statuses = ['hadir', 'terlambat', 'izin', 'sakit', 'alfa'];
        var statusColors = { hadir: 'green', terlambat: 'amber', izin: 'blue', sakit: 'purple', alfa: 'red' };
        var statusLabels = { hadir: 'Hadir', terlambat: 'Telat', izin: 'Izin', sakit: 'Sakit', alfa: 'Alfa' };

        var row = UI.el('div', { class: 'list-item', style: 'flex-direction:column;align-items:stretch;gap:8px' });
        row.appendChild(UI.el('div', { style: 'display:flex;align-items:center;gap:12px' }, [
          UI.avatar(s.name),
          UI.el('div', { class: 'grow' }, [
            UI.el('div', { class: 'name', text: s.name }),
            UI.el('div', { class: 'sub', text: 'NIS: ' + s.nis }),
          ]),
        ]));

        var btnGroup = UI.el('div', { style: 'display:flex;gap:6px;flex-wrap:wrap' });
        statuses.forEach(function (stVal) {
          var btn = UI.el('button', {
            class: 'btn btn-sm ' + (st === stVal ? 'btn-' + statusColors[stVal] : 'btn-outline'),
            style: 'flex:1;min-width:60px;font-size:11px;padding:6px 4px',
            onclick: async function () {
              try {
                var res = await API.teacherStoreAbsen(classId, s.id, stVal);
                if (!res.ok) throw 0;
                btnGroup.querySelectorAll('button').forEach(function (b) { b.className = 'btn btn-sm btn-outline'; });
                btn.className = 'btn btn-sm btn-' + statusColors[stVal];
                UI.toast(s.name + ': ' + statusLabels[stVal], 'success');
              } catch (e) { UI.toast('Gagal menyimpan', 'error'); }
            }
          }, [document.createTextNode(statusLabels[stVal])]);
          if (st === stVal) btn.className = 'btn btn-sm btn-' + statusColors[stVal];
          btnGroup.appendChild(btn);
        });
        row.appendChild(btnGroup);
        list.appendChild(row);
      });
    } catch (e) { list.innerHTML = ''; list.appendChild(UI.empty('Gagal memuat')); }
    return c;
  }

  /* ================= Parent ================= */
  async function parentHomePage() {
    var c = UI.el('div', { class: 'page' }); var user = API.getUser();
    c.appendChild(UI.el('h2', { text: 'Halo, ' + (user?.name || 'Orang Tua').split(' ')[0] }));

    var childId = getParentChildId();
    if (!childId) { c.appendChild(UI.empty('Tidak ada data anak')); return c; }

    var today = (function(){ var n=new Date(); return n.getFullYear()+'-'+String(n.getMonth()+1).padStart(2,'0')+'-'+String(n.getDate()).padStart(2,'0'); })();
    var statRow = UI.el('div', { class: 'stat-strip' });
    statRow.append.apply(statRow, UI.skelStats(4)); c.appendChild(statRow);

    var ringWrap = UI.el('div', { style: 'display:flex;justify-content:center;padding:20px 0 12px' });
    c.appendChild(ringWrap);
    var list = UI.el('div'); list.append.apply(list, UI.skelRows(4)); c.appendChild(list);

    async function load() {
      try {
        var res = await API.parentChild(childId); if (!res.ok) throw 0;
        var d = await res.json(); var a = d.attendance || d.data || d || [];
        var st = { hadir: 0, terlambat: 0, izin: 0, alfa: 0 };
        a.forEach(function (r) { if (st[r.status] !== undefined) st[r.status]++; });
        statRow.innerHTML = '';
        var total = a.length || 1;
        var pct = Math.round(((st.hadir || 0) + (st.terlambat || 0)) / total * 100);
        [
          { n: st.hadir, t: 'Hadir', cls: 'green' }, { n: st.terlambat, t: 'Terlambat', cls: 'amber' },
          { n: st.izin, t: 'Izin', cls: 'blue' }, { n: st.alfa, t: 'Alfa', cls: 'red' },
        ].forEach(function (it) {
          var card = UI.el('div', { class: 'stat-card ' + it.cls });
          var num = UI.el('div', { class: 'n', text: '0' }); UI.animateCount(num, it.n);
          card.append(num, UI.el('div', { class: 't', text: it.t }));
          statRow.appendChild(card);
        });

        ringWrap.innerHTML = '';
        ringWrap.appendChild(UI.progressRing(pct, 80, 6, 'var(--success)'));

        list.innerHTML = '';
        if (!a.length) { list.appendChild(UI.empty('Belum ada riwayat')); return; }
        a.forEach(function (r) {
          list.appendChild(UI.el('div', { class: 'list-item' }, [
            UI.el('div', { class: 'grow' }, [
              UI.el('div', { class: 'name', text: API.fmtDate(r.date || r.tanggal) }),
              UI.el('div', { class: 'sub', text: r.check_in_time || r.time || r.jam || '-' }),
            ]),
            UI.statusPill(r.status),
          ]));
        });
      } catch (e) { statRow.innerHTML = ''; statRow.append.apply(statRow, UI.skelStats(4)); }
    }
    await load();
    var timer = setInterval(load, 30000);
    c._cleanup = function () { clearInterval(timer); };
    return c;
  }

  async function parentChildPage(childId) {
    var c = UI.el('div', { class: 'page' });
    c.appendChild(UI.el('h2', { text: 'Detail Kehadiran' }));
    var statRow = UI.el('div', { class: 'stat-strip' });
    statRow.append.apply(statRow, UI.skelStats(4)); c.appendChild(statRow);
    var list = UI.el('div'); list.append.apply(list, UI.skelRows(4)); c.appendChild(list);
    try {
      var res = await API.parentChild(childId); if (!res.ok) throw 0;
      var d = await res.json(); var a = d.attendance || d.data || d || [];
      var st = { hadir: 0, terlambat: 0, izin: 0, alfa: 0 };
      a.forEach(function (r) { if (st[r.status] !== undefined) st[r.status]++; });
      statRow.innerHTML = '';
      [
        { n: st.hadir, t: 'Hadir', cls: 'green' }, { n: st.terlambat, t: 'Terlambat', cls: 'amber' },
        { n: st.izin, t: 'Izin', cls: 'blue' }, { n: st.alfa, t: 'Alfa', cls: 'red' },
      ].forEach(function (it) {
        var card = UI.el('div', { class: 'stat-card ' + it.cls });
        var num = UI.el('div', { class: 'n', text: '0' }); UI.animateCount(num, it.n);
        card.append(num, UI.el('div', { class: 't', text: it.t }));
        statRow.appendChild(card);
      });

      list.innerHTML = '';
      if (!a.length) { list.appendChild(UI.empty('Belum ada data')); return c; }
      a.forEach(function (r) {
        list.appendChild(UI.el('div', { class: 'list-item' }, [
          UI.el('div', { class: 'grow' }, [
            UI.el('div', { class: 'name', text: API.fmtDate(r.date || r.tanggal) }),
            UI.el('div', { class: 'sub', text: r.check_in_time || r.time || r.jam || '-' }),
          ]),
          UI.statusPill(r.status),
        ]));
      });
    } catch (e) { statRow.innerHTML = ''; statRow.append.apply(statRow, UI.skelStats(4)); }
    return c;
  }

  window.PAGES = {
    loginPage: loginPage,
    studentHome: studentHome, studentAbsenPage: studentAbsenPage, studentHistoryPage: studentHistoryPage, studentSchedulePage: studentSchedulePage,
    adminOverviewPage: adminOverviewPage, adminAttendancePage: adminAttendancePage, adminTeachersPage: adminTeachersPage,
    adminSchedulesPage: adminSchedulesPage, adminStudentsPage: adminStudentsPage, adminSettingsPage: adminSettingsPage,
    teacherHomePage: teacherHomePage, teacherClassPage: teacherClassPage, teacherAbsenPage: teacherAbsenPage,
    parentHomePage: parentHomePage, parentChildPage: parentChildPage,
  };
})();
