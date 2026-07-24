import { Capacitor, CapacitorHttp } from '@capacitor/core';
import './styles.css';

const STORAGE_KEY = 'c108-helper-settings';

const state = {
  settings: loadSettings(),
  activeTab: 'summary',
  summary: null,
  maps: [],
  notices: [],
  map: {
    day: '1',
    map: 'E123',
    relation: 'known',
    priority: '',
    q: '',
    data: null,
    loading: false,
  },
  loading: false,
  error: '',
  selectedCircle: null,
};

const app = document.querySelector('#app');

function loadSettings() {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    return {
      apiBase: saved.apiBase || 'https://doujin.artick.tw',
      passcode: saved.passcode || '',
    };
  } catch {
    return {
      apiBase: 'https://doujin.artick.tw',
      passcode: '',
    };
  }
}

function saveSettings() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state.settings));
}

function endpoint(path, params = {}) {
  const base = state.settings.apiBase.replace(/\/+$/, '');
  const url = new URL(`${base}${path}`);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value) !== '') {
      url.searchParams.set(key, String(value));
    }
  });
  return url.toString();
}

async function requestJson(path, params = {}) {
  const url = endpoint(path, params);
  const headers = {
    Accept: 'application/json',
    'X-App-Passcode': state.settings.passcode,
  };

  if (Capacitor.isNativePlatform()) {
    const response = await CapacitorHttp.get({ url, headers });
    if (response.status < 200 || response.status >= 300) {
      throw new Error(response.data?.message || `HTTP ${response.status}`);
    }
    return typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
  }

  const response = await fetch(url, { headers });
  const text = await response.text();
  const data = text ? JSON.parse(text) : {};
  if (!response.ok) {
    throw new Error(data.message || `HTTP ${response.status}`);
  }
  return data;
}

function setError(error) {
  state.error = error instanceof Error ? error.message : String(error || '');
}

async function loadSummary() {
  state.loading = true;
  state.error = '';
  render();

  try {
    const data = await requestJson('/api/app/c108/summary');
    state.summary = data.summary || null;
    state.maps = data.maps || [];
    state.notices = data.unread_notices || [];

    const selected = state.maps.find((item) => String(item.day) === state.map.day && item.map === state.map.map)
      || state.maps[0];
    if (selected) {
      state.map.day = String(selected.day);
      state.map.map = selected.map;
    }
  } catch (error) {
    setError(error);
  } finally {
    state.loading = false;
    render();
  }
}

async function loadMap() {
  state.map.loading = true;
  state.error = '';
  render();

  try {
    state.map.data = await requestJson('/api/app/c108/map', {
      day: state.map.day,
      map: state.map.map,
      relation: state.map.relation,
      priority: state.map.priority,
      q: state.map.q,
    });
  } catch (error) {
    setError(error);
  } finally {
    state.map.loading = false;
    render();
  }
}

async function loadNotices() {
  state.loading = true;
  state.error = '';
  render();

  try {
    const data = await requestJson('/api/app/c108/notices', { limit: 80 });
    state.notices = data.notices || [];
  } catch (error) {
    setError(error);
  } finally {
    state.loading = false;
    render();
  }
}

function switchTab(tab) {
  state.activeTab = tab;
  if (tab === 'summary' && !state.summary) {
    loadSummary();
    return;
  }
  if (tab === 'map' && !state.map.data) {
    loadMap();
    return;
  }
  if (tab === 'notices') {
    loadNotices();
    return;
  }
  render();
}

function h(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function priorityLabel(priority) {
  return {
    must: '必看',
    high: '優先',
    normal: '普通',
  }[priority] || '未設定';
}

function relationLabel(row) {
  if (row?.is_tracked) {
    return '追蹤中';
  }
  if (row?.is_known) {
    return '買過';
  }
  return '一般';
}

function boothLabel(row) {
  if (!row) {
    return '';
  }
  const day = row.day ? `${row.day}日目` : '';
  const block = `${row.map_name || row.map || ''} ${row.block_name || ''}${String(row.space_no || '').padStart(2, '0')}${row.space_no_sub || ''}`;
  return `${day} ${block}`.trim();
}

function render() {
  app.innerHTML = `
    <div class="app-shell">
      <header class="topbar">
        <div>
          <div class="eyebrow">Personal Doujin Helper</div>
          <h1>C108</h1>
        </div>
        <button class="icon-button" data-action="refresh" title="重新整理">↻</button>
      </header>

      <section class="settings">
        <label>
          API
          <input data-field="apiBase" value="${h(state.settings.apiBase)}" inputmode="url" />
        </label>
        <label>
          暗碼
          <input data-field="passcode" value="${h(state.settings.passcode)}" type="password" autocomplete="current-password" />
        </label>
        <button data-action="save-settings">儲存</button>
      </section>

      <nav class="tabs">
        ${tabButton('summary', '摘要')}
        ${tabButton('map', '地圖')}
        ${tabButton('notices', '通知')}
      </nav>

      ${state.error ? `<div class="notice error">${h(state.error)}</div>` : ''}
      ${state.loading ? '<div class="notice">讀取中...</div>' : ''}

      <main>
        ${state.activeTab === 'summary' ? renderSummary() : ''}
        ${state.activeTab === 'map' ? renderMap() : ''}
        ${state.activeTab === 'notices' ? renderNotices() : ''}
      </main>
    </div>
    ${state.selectedCircle ? renderCircleModal(state.selectedCircle) : ''}
  `;

  bindEvents();
}

function tabButton(id, label) {
  return `<button class="${state.activeTab === id ? 'active' : ''}" data-tab="${id}">${label}</button>`;
}

function renderSummary() {
  if (!state.summary) {
    return `
      <section class="empty">
        <button data-action="load-summary">讀取 C108 摘要</button>
      </section>
    `;
  }

  return `
    <section class="metric-grid">
      <div class="metric"><span>總社團</span><strong>${h(state.summary.total)}</strong></div>
      <div class="metric"><span>買過/連動</span><strong>${h(state.summary.known)}</strong></div>
      <div class="metric"><span>追蹤中</span><strong>${h(state.summary.tracked)}</strong></div>
      <div class="metric"><span>未讀通知</span><strong>${h(state.notices.length)}</strong></div>
    </section>
    <section class="section">
      <h2>地圖</h2>
      <div class="map-list">
        ${state.maps.map((item) => `
          <button data-action="open-map" data-day="${h(item.day)}" data-map="${h(item.map)}">
            <strong>${h(item.day)}日目 / ${h(item.name || item.map)}</strong>
            <span>${h(item.known_count)} 買過 · ${h(item.tracked_count)} 追蹤</span>
          </button>
        `).join('')}
      </div>
    </section>
  `;
}

function renderMap() {
  const data = state.map.data;
  const mapOptions = state.maps.filter((item) => String(item.day) === state.map.day);

  return `
    <section class="map-controls">
      <input data-map-field="q" value="${h(state.map.q)}" placeholder="搜尋社團、作者、攤位、備註" />
      <div class="control-row">
        <select data-map-field="day">
          ${['1', '2'].map((day) => `<option value="${day}" ${state.map.day === day ? 'selected' : ''}>${day}日目</option>`).join('')}
        </select>
        <select data-map-field="map">
          ${mapOptions.map((item) => `<option value="${h(item.map)}" ${state.map.map === item.map ? 'selected' : ''}>${h(item.name || item.map)}</option>`).join('')}
        </select>
      </div>
      <div class="control-row">
        <select data-map-field="relation">
          ${option('known', '追蹤 + 買過', state.map.relation)}
          ${option('tracked', '追蹤中', state.map.relation)}
          ${option('all', '全部社團', state.map.relation)}
        </select>
        <select data-map-field="priority">
          ${option('', '全部優先度', state.map.priority)}
          ${option('must', '必看', state.map.priority)}
          ${option('high', '優先', state.map.priority)}
          ${option('normal', '普通', state.map.priority)}
        </select>
        <button data-action="load-map">顯示</button>
      </div>
    </section>
    ${state.map.loading ? '<div class="notice">地圖讀取中...</div>' : ''}
    ${data ? renderMapCanvas(data) : '<section class="empty"><button data-action="load-map">讀取地圖</button></section>'}
  `;
}

function option(value, label, selected) {
  return `<option value="${h(value)}" ${String(selected) === String(value) ? 'selected' : ''}>${h(label)}</option>`;
}

function renderMapCanvas(data) {
  if (!data.image?.url) {
    return '<section class="empty">這張地圖沒有圖片。</section>';
  }

  const width = data.image.width || 1200;
  const height = data.image.height || 800;

  return `
    <section class="map-meta">
      <strong>${h(data.day)}日目 / ${h(data.image.file || data.map)}</strong>
      <span>${h(data.count)} 個標記</span>
    </section>
    <section class="map-scroll">
      <div class="map-stage" style="width:${width}px;height:${height}px">
        <img src="${h(data.image.url)}" alt="C108 map" />
        ${(data.rows || []).map((row, index) => renderMarker(row, index)).join('')}
      </div>
    </section>
  `;
}

function renderMarker(row, index) {
  const marker = row.marker || {};
  const classes = ['marker'];
  if (row.is_tracked) {
    classes.push('tracked');
  } else if (row.is_known) {
    classes.push('known');
  }
  return `
    <button
      class="${classes.join(' ')}"
      style="left:${h(marker.left)}px;top:${h(marker.top)}px"
      data-action="circle"
      data-index="${index}"
      title="${h(row.circle_name)}"
    >${h(row.space_no_sub || '')}</button>
  `;
}

function renderNotices() {
  if (state.notices.length === 0 && !state.loading) {
    return '<section class="empty"><button data-action="load-notices">讀取通知</button></section>';
  }

  return `
    <section class="section">
      <div class="section-title">
        <h2>更新通知</h2>
        <button data-action="load-notices">重新讀取</button>
      </div>
      <div class="notice-list">
        ${state.notices.map((item, index) => `
          <button class="notice-item ${item.update_read ? '' : 'unread'}" data-action="notice-circle" data-index="${index}">
            <strong>${h(item.circle_name)}</strong>
            <span>${h(boothLabel(item))}</span>
            <p>${h(item.update_notice_text || item.description || '')}</p>
          </button>
        `).join('')}
      </div>
    </section>
  `;
}

function renderCircleModal(row) {
  return `
    <div class="modal-backdrop" data-action="close-modal">
      <article class="modal" role="dialog" aria-modal="true">
        <button class="modal-close" data-action="close-modal">×</button>
        <header class="circle-header">
          ${row.cut_url ? `<img src="${h(row.cut_url)}" alt="" />` : '<div class="cut-placeholder"></div>'}
          <div>
            <h2>${h(row.circle_name)}</h2>
            <p>${h(row.circle_kana)}${row.pen_name ? ` / ${h(row.pen_name)}` : ''}</p>
          </div>
        </header>
        <dl class="detail-list">
          <div><dt>攤位</dt><dd>${h(boothLabel(row))}</dd></div>
          <div><dt>分類</dt><dd>${h(relationLabel(row))}</dd></div>
          <div><dt>優先度</dt><dd>${h(priorityLabel(row.priority))}</dd></div>
          ${row.book_name ? `<div><dt>預定發布物</dt><dd>${h(row.book_name)}</dd></div>` : ''}
          ${row.description ? `<div><dt>簡介</dt><dd>${h(row.description)}</dd></div>` : ''}
          ${row.note ? `<div><dt>備註</dt><dd>${h(row.note)}</dd></div>` : ''}
        </dl>
        ${renderOwnedBooks(row)}
        <footer class="modal-actions">
          ${row.webcatalog_url ? `<a href="${h(row.webcatalog_url)}" target="_blank" rel="noreferrer">Web Catalog</a>` : ''}
        </footer>
      </article>
    </div>
  `;
}

function renderOwnedBooks(row) {
  const books = row.owned_books || [];
  if (books.length === 0) {
    return '';
  }
  return `
    <section class="owned-books">
      <h3>已擁有</h3>
      <div>
        ${books.map((book) => `
          <div class="book-chip">
            ${book.cover_url ? `<img src="${h(book.cover_url)}" alt="" />` : ''}
            <span>${h(book.title)}</span>
          </div>
        `).join('')}
      </div>
    </section>
  `;
}

function bindEvents() {
  app.querySelectorAll('[data-tab]').forEach((button) => {
    button.addEventListener('click', () => switchTab(button.dataset.tab));
  });

  app.querySelectorAll('[data-field]').forEach((input) => {
    input.addEventListener('input', () => {
      state.settings[input.dataset.field] = input.value;
    });
  });

  app.querySelectorAll('[data-map-field]').forEach((input) => {
    input.addEventListener('input', () => {
      state.map[input.dataset.mapField] = input.value;
      if (input.dataset.mapField === 'day') {
        const firstMap = state.maps.find((item) => String(item.day) === state.map.day);
        if (firstMap) {
          state.map.map = firstMap.map;
        }
        render();
      }
    });
  });

  app.querySelectorAll('[data-action]').forEach((element) => {
    element.addEventListener('click', (event) => {
      const action = element.dataset.action;
      if (action === 'save-settings') {
        saveSettings();
        loadSummary();
      } else if (action === 'refresh') {
        refreshActive();
      } else if (action === 'load-summary') {
        loadSummary();
      } else if (action === 'load-map') {
        loadMap();
      } else if (action === 'load-notices') {
        loadNotices();
      } else if (action === 'open-map') {
        state.map.day = element.dataset.day;
        state.map.map = element.dataset.map;
        state.activeTab = 'map';
        loadMap();
      } else if (action === 'circle') {
        const row = state.map.data?.rows?.[Number(element.dataset.index)];
        state.selectedCircle = row || null;
        render();
      } else if (action === 'notice-circle') {
        state.selectedCircle = state.notices[Number(element.dataset.index)] || null;
        render();
      } else if (action === 'close-modal') {
        if (event.target === element || element.classList.contains('modal-close')) {
          state.selectedCircle = null;
          render();
        }
      }
    });
  });
}

function refreshActive() {
  if (state.activeTab === 'map') {
    loadMap();
  } else if (state.activeTab === 'notices') {
    loadNotices();
  } else {
    loadSummary();
  }
}

render();
