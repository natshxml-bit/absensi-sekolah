/* ===== App: router, layout, auth guard ===== */
(function () {
  'use strict';
  var E = UI.el;

  var ROUTES = {
    '/login': { page: 'loginPage', title: 'Masuk', login: true },
    '/admin': { page: 'adminOverviewPage', title: 'Dashboard' },
    '/admin/classes': { page: 'adminStudentsPage', title: 'Kelas' },
    '/admin/students': { page: 'adminStudentsPage', title: 'Siswa' },
    '/admin/teachers': { page: 'adminTeachersPage', title: 'Guru' },
    '/admin/students': { page: 'adminStudentsPage', title: 'Siswa' },
    '/admin/settings': { page: 'adminSettingsPage', title: 'Pengaturan' },
    '/admin/attendance': { page: 'adminAttendancePage', title: 'Absensi' },
    '/student': { page: 'studentHome', title: 'Beranda' },
    '/student/absen': { page: 'studentAbsenPage', title: 'Absen' },
    '/student/history': { page: 'studentHistoryPage', title: 'Riwayat' },
    '/student/schedule': { page: 'studentSchedulePage', title: 'Jadwal' },
    '/teacher': { page: 'teacherHomePage', title: 'Kelas Saya' },
    '/parent': { page: 'parentHomePage', title: 'Anak Saya' },
  };

  var ADMIN_NAV = [
    { href: '#/admin', ic: 'home', label: 'Beranda' },
    { href: '#/admin/attendance', ic: 'chart', label: 'Absensi' },
    { href: '#/admin/classes', ic: 'users', label: 'Data' },
    { href: '#/admin/settings', ic: 'settings', label: 'Atur' },
  ];
  var STUDENT_NAV = [
    { href: '#/student', ic: 'home', label: 'Beranda' },
    { href: '#/student/absen', ic: 'camera', label: 'Absen' },
    { href: '#/student/history', ic: 'list', label: 'Riwayat' },
    { href: '#/student/schedule', ic: 'calendar', label: 'Jadwal' },
  ];
  var TEACHER_NAV = [
    { href: '#/teacher', ic: 'home', label: 'Kelas' },
  ];
  var ORTU_NAV = [
    { href: '#/parent', ic: 'home', label: 'Beranda' },
  ];

  var app = document.getElementById('app');
  var shell = null;
  var currentNav = null;

  function roleLabel(role) {
    return { admin: 'Admin', guru: 'Guru', siswa: 'Siswa', orangtua: 'Orang Tua' }[role] || role;
  }

  function buildShell() {
    app.innerHTML = '';
    var topbar = E('div', { class: 'topbar' });
    var titleEl = E('h1', { text: '' });
    var subEl = E('span', { class: 'sub', text: '' });
    topbar.appendChild(E('div', {}, [titleEl, subEl]));
    topbar.appendChild(E('button', { class: 'btn-icon', html: UI.icon('logout', 20), title: 'Logout', onclick: logout }));
    app.appendChild(topbar);
    var contentEl = E('div', { class: 'page' });
    app.appendChild(contentEl);
    var navEl = E('nav', { class: 'bottomnav' });
    app.appendChild(navEl);
    shell = { topbar: topbar, titleEl: titleEl, subEl: subEl, contentEl: contentEl, navEl: navEl };
  }

  function renderLogin() {
    document.title = 'Masuk · Absensi Sekolah';
    app.innerHTML = '';
    app.appendChild(PAGES.loginPage());
    shell = null;
    currentNav = null;
  }

  function renderPage(title, nav, user, content) {
    if (!shell) buildShell();
    var oldContent = shell.contentEl.firstChild;
    if (oldContent && oldContent._cleanup) oldContent._cleanup();
    shell.titleEl.textContent = title;
    shell.subEl.textContent = roleLabel(user.role) + ' · ' + (user.name || '');
    document.title = title + ' · Absensi Sekolah';

    shell.contentEl.innerHTML = '';
    if (content && content.nodeType === 1) {
      shell.contentEl.appendChild(content);
    } else {
      shell.contentEl.appendChild(document.createTextNode(''));
    }

    if (nav) {
      var navKey = nav.map(function (n) { return n.href; }).join(',');
      if (navKey !== currentNav) {
        currentNav = navKey;
        shell.navEl.innerHTML = '';
        shell.navEl.style.display = 'flex';
        nav.forEach(function (n) {
          shell.navEl.appendChild(E('a', { href: n.href }, [
            E('span', { class: 'ic', html: UI.icon(n.ic, 22) }),
            E('span', { text: n.label }),
          ]));
        });
      }
      var links = shell.navEl.querySelectorAll('a');
      var hash = window.location.hash;
      nav.forEach(function (n, i) {
        if (links[i]) {
          var isActive = hash === n.href ||
            (n.href === '#/admin' && hash.startsWith('#/admin') && hash !== '#/admin/attendance');
          links[i].className = isActive ? 'active' : '';
        }
      });
    } else {
      shell.navEl.style.display = 'none';
    }
    window.scrollTo({ top: 0, behavior: 'instant' });
  }

  function logout() {
    API.logout().catch(function () {});
    localStorage.removeItem('absen_token');
    localStorage.removeItem('absen_user');
    window.location.hash = '#/login';
  }

  function homeForRole(role) {
    return { admin: '#/admin', guru: '#/teacher', siswa: '#/student', orangtua: '#/parent' }[role] || '#/login';
  }

  function routeTo(hash) {
    var path = hash.replace(/^#/, '') || '/login';
    var user = API.getUser();
    var token = API.getToken();

    if (!token) {
      if (path !== '/login') { window.location.hash = '#/login'; return; }
      renderLogin();
      return;
    }

    if (!user) {
      API.getProfile().then(function (d) {
        var u = d.user || d;
        API.setUser(u);
        window.location.hash = homeForRole(u.role);
      }).catch(function () {
        localStorage.removeItem('absen_token');
        localStorage.removeItem('absen_user');
        window.location.hash = '#/login';
      });
      return;
    }

    if (path === '/login') { window.location.hash = homeForRole(user.role); return; }

    var allowedPrefix = {
      '/admin': ['admin'],
      '/teacher': ['guru'],
      '/parent': ['orangtua'],
      '/student': ['siswa'],
    };
    var prefix = '/' + path.split('/')[1];
    if (allowedPrefix[prefix] && allowedPrefix[prefix].indexOf(user.role) === -1) {
      UI.toast('Akses ditolak.', 'err');
      window.location.hash = homeForRole(user.role);
      return;
    }

    var title = null;
    var content = null;

    var mClass = path.match(/^\/teacher\/class\/(\d+)$/);
    if (mClass) {
      title = 'Absensi Kelas';
      var r = PAGES.teacherClassPage(mClass[1]);
      if (r && typeof r.then === 'function') { r.then(function (n) { renderPage(title, navFor(user.role), user, n); }).catch(function () { renderPage(title, navFor(user.role), user, UI.empty('Gagal')); }); return; }
      content = r;
    }

    var mAbsen = path.match(/^\/teacher\/class\/(\d+)\/absen$/);
    if (mAbsen) {
      title = 'Absen Manual';
      var rAbsen = PAGES.teacherAbsenPage(mAbsen[1]);
      if (rAbsen && typeof rAbsen.then === 'function') { rAbsen.then(function (n) { renderPage(title, navFor(user.role), user, n); }).catch(function () { renderPage(title, navFor(user.role), user, UI.empty('Gagal')); }); return; }
      content = rAbsen;
    }

    var mChild = path.match(/^\/parent\/child\/(\d+)$/);
    if (mChild) {
      title = 'Kehadiran Anak';
      var r2 = PAGES.parentChildPage(mChild[1]);
      if (r2 && typeof r2.then === 'function') { r2.then(function (n) { renderPage(title, navFor(user.role), user, n); }).catch(function () { renderPage(title, navFor(user.role), user, UI.empty('Gagal')); }); return; }
      content = r2;
    }

    var match = ROUTES[path];
    if (content === null && match) {
      title = match.title;
      var result = PAGES[match.page]();
      if (result && typeof result.then === 'function') {
        result.then(function (node) { renderPage(title, navFor(user.role), user, node); }).catch(function () { renderPage(title, navFor(user.role), user, UI.empty('Gagal memuat')); });
        return;
      }
      content = result;
    }

    if (content === null) { window.location.hash = homeForRole(user.role); return; }

    renderPage(title, navFor(user.role), user, content);
  }

  function navFor(role) {
    if (role === 'admin') return ADMIN_NAV;
    if (role === 'siswa') return STUDENT_NAV;
    if (role === 'guru') return TEACHER_NAV;
    if (role === 'orangtua') return ORTU_NAV;
    return null;
  }

  window.addEventListener('hashchange', function () { routeTo(window.location.hash); });
  window.APP.routeTo = routeTo;
  routeTo(window.location.hash);
})();
