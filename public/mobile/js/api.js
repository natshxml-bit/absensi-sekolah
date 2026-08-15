/* ===== API Client ===== */
(function () {
  'use strict';

  var TOKEN_KEY = 'absen_token';
  var USER_KEY = 'absen_user';

  function getToken() {
    try { return localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { return ''; }
  }
  function setToken(t) { try { localStorage.setItem(TOKEN_KEY, t); } catch (e) {} }
  function getUser() {
    try { return JSON.parse(localStorage.getItem(USER_KEY) || 'null'); } catch (e) { return null; }
  }
  function setUser(u) { try { localStorage.setItem(USER_KEY, JSON.stringify(u)); } catch (e) {} }
  function clearSession() {
    try { localStorage.removeItem(TOKEN_KEY); localStorage.removeItem(USER_KEY); } catch (e) {}
  }
  function setAuth(token, user) { setToken(token); setUser(user); }

  var RAILWAY_URL = 'https://absensi-app.up.railway.app';
  var isNative = !!(window.Capacitor && window.Capacitor.isNativePlatform);
  var BASE = isNative ? RAILWAY_URL + '/api' : '/api';

  function headers(json) {
    var h = { 'Accept': 'application/json' };
    var t = getToken();
    if (t) h['Authorization'] = 'Bearer ' + t;
    if (json) h['Content-Type'] = 'application/json';
    return h;
  }

  function request(method, url, body, isForm) {
    var opts = { method: method, headers: headers(!isForm) };
    if (body && !isForm) opts.body = JSON.stringify(body);
    if (body && isForm) opts.body = body;
    return fetch(BASE + url, opts).then(function (res) {
      if (res.status === 401) { clearSession(); window.location.hash = '#/login'; throw new Error('Sesi berakhir'); }
      return res;
    }).catch(function (err) {
      if (err && err.status) throw err;
      throw new Error('Gagal terhubung ke server');
    });
  }

  function get(url) { return request('GET', url); }
  function post(url, body) { return request('POST', url, body, false); }
  function put(url, body) { return request('PUT', url, body, false); }
  function del(url) { return request('DELETE', url); }
  function postForm(url, fd) { return request('POST', url, fd, true); }
  function putForm(url, fd) { return request('PUT', url, fd, true); }

  function getHome() {
    var u = getUser();
    if (!u) return '#/login';
    return { admin: '#/admin', guru: '#/teacher', siswa: '#/student', orangtua: '#/parent' }[u.role] || '#/login';
  }

  function fmtDate(d) { return d ? String(d).slice(0, 10) : ''; }

  async function login(identifier, password) {
    var res = await fetch(BASE + '/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ identifier: identifier, password: password }),
    });
    return res;
  }

  async function logout() {
    try { await post('/auth/logout'); } catch (e) {}
    clearSession();
  }

  async function getProfile() {
    var res = await get('/me');
    if (!res.ok) throw new Error('Gagal');
    return res.json();
  }

  async function studentDashboard() { return get('/student/today'); }
  async function studentAbsenSubmit(body) { return postForm('/student/attendance', body); }
  async function studentHistory() { return get('/student/attendance'); }
  async function studentIzin(body) { return post('/student/attendance/izin', body); }
  async function studentSchedules() { return get('/student/schedules'); }

  async function adminDashboard() { return get('/admin/overview'); }
  async function adminAttendance(date) { return get('/admin/attendance' + (date ? '?date=' + date : '')); }
  async function adminTeachers() { return get('/admin/teachers'); }
  async function adminTeachersCreate(body) { return post('/admin/teachers', body); }
  async function adminClasses() { return get('/admin/classes'); }
  async function adminSchedules() { return get('/admin/schedules'); }
  async function adminSchedulesCreate(body) { return post('/admin/schedules', body); }
  async function adminStudents() { return get('/admin/students'); }
  async function adminStudentsCreate(body) { return post('/admin/students', body); }
  async function adminSettingsGet() { return get('/admin/settings'); }
  async function adminSettingsSave(body) { return put('/admin/settings', body); }

  async function teacherHome() { return get('/teacher/classes'); }
  async function teacherClass(id) { return get('/teacher/classes/' + id + '/attendance'); }
  async function teacherStudents(id) { return get('/teacher/classes/' + id + '/students'); }
  async function teacherStoreAbsen(classId, studentId, status, notes) {
    return post('/teacher/classes/' + classId + '/attendance', {
      student_id: studentId, status: status, notes: notes || ''
    });
  }
  async function teacherExportAbsen(classId, from, to) {
    var url = '/teacher/classes/' + classId + '/attendance/export';
    var params = [];
    if (from) params.push('from=' + from);
    if (to) params.push('to=' + to);
    if (params.length) url += '?' + params.join('&');
    return get(url);
  }

  async function parentHome() { return get('/parent/children'); }
  async function parentChild(id) { return get('/parent/children/' + id + '/attendance'); }

  window.API = {
    getToken: getToken, setToken: setToken,
    getUser: getUser, setUser: setUser, setAuth: setAuth,
    clearSession: clearSession,
    getHome: getHome, fmtDate: fmtDate,
    login: login, logout: logout, getProfile: getProfile,
    get: get, post: post, put: put, del: del, postForm: postForm, putForm: putForm,
    studentDashboard: studentDashboard, studentAbsenSubmit: studentAbsenSubmit,
    studentHistory: studentHistory, studentIzin: studentIzin, studentSchedules: studentSchedules,
    adminDashboard: adminDashboard, adminAttendance: adminAttendance,
    adminTeachers: adminTeachers, adminTeachersCreate: adminTeachersCreate,
    adminClasses: adminClasses, adminSchedules: adminSchedules, adminSchedulesCreate: adminSchedulesCreate,
    adminStudents: adminStudents, adminStudentsCreate: adminStudentsCreate,
    adminSettingsGet: adminSettingsGet, adminSettingsSave: adminSettingsSave,
    teacherHome: teacherHome, teacherClass: teacherClass, teacherStudents: teacherStudents,
    teacherStoreAbsen: teacherStoreAbsen, teacherExportAbsen: teacherExportAbsen,
    parentHome: parentHome, parentChild: parentChild,
  };

  window.homeForRole = function (role) {
    return { admin: '#/admin', guru: '#/teacher', siswa: '#/student', orangtua: '#/parent' }[role] || '#/login';
  };
})();
