import { Capacitor, CapacitorHttp } from '@capacitor/core';
import './styles.css';

const STORAGE_KEY = 'doujin-helper-settings';
const DEFAULT_API_BASE = 'https://doujin.artick.tw';

const state = {
  settings: loadSettings(),
  activeTab: 'books',
  error: '',
  message: '',
  books: {
    loading: false,
    loaded: false,
    rows: [],
    pagination: {
      page: 1,
      limit: 30,
      total: 0,
      total_pages: 1,
    },
    filters: {
      q: '',
      type: '',
      status: '',
      tag_id: '',
    },
    options: {
      types: [],
      statuses: [],
      tags: [],
    },
  },
  circles: {
    loading: false,
    loaded: false,
    rows: [],
    pagination: {
      page: 1,
      limit: 30,
      total: 0,
      total_pages: 1,
    },
    filters: {
      q: '',
      tracked: '',
      priority: '',
    },
    options: {
      priorities: [],
    },
  },
  maps: [],
  map: {
    loading: false,
    loaded: false,
    day: '1',
    map: 'E123',
    relation: 'known',
    priority: '',
    q: '',
    data: null,
  },
  selectedCircle: null,
};

const app = document.querySelector('#app');

function loadSettings() {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    return {
      apiBase: saved.apiBase || DEFAULT_API_BASE,
      passcode: saved.passcode || '',
      showImages: saved.showImages === true || saved.showImages === '1',
    };
  } catch {
    return {
      apiBase: DEFAULT_API_BASE,
      passcode: '',
      showImages: false,
    };
  }
}

function saveSettings() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state.settings));
}

function syncSettingInput(input) {
  if (input.dataset.field === 'showImages') {
    state.settings.showImages = input.value === '1';
  } else {
    state.settings[input.dataset.field] = input.value;
  }
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

function webUrl(path, params = {}) {
  return endpoint(path, params);
}

function openExternal(url) {
  window.open(url, '_blank', 'noopener,noreferrer');
}

function imagesVisible() {
  return state.settings.showImages === true;
}

function coverElement(url, className, alt = '') {
  if (imagesVisible() && url) {
    return `<img class="${h(className)}" src="${h(url)}" alt="${h(alt)}" loading="lazy" />`;
  }

  return `<div class="${h(className)} placeholder" aria-label="圖片已遮蔽"></div>`;
}

async function requestJson(path, params = {}, options = {}) {
  const url = endpoint(path, params);
  const headers = {
    Accept: 'application/json',
    'X-App-Passcode': state.settings.passcode,
  };
  const method = options.method || 'GET';
  const body = options.body || null;
  if (body !== null) {
    headers['Content-Type'] = 'application/json';
  }

  if (Capacitor.isNativePlatform()) {
    const request = { url, headers };
    if (body !== null) {
      request.data = body;
    }
    const response = method === 'POST'
      ? await CapacitorHttp.post(request)
      : await CapacitorHttp.get(request);
    if (response.status < 200 || response.status >= 300) {
      throw new Error(response.data?.message || `HTTP ${response.status}`);
    }
    return typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
  }

  const response = await fetch(url, {
    method,
    headers,
    body: body === null ? undefined : JSON.stringify(body),
  });
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

function clearStatus() {
  state.error = '';
  state.message = '';
}

async function loadBooks(page = state.books.pagination.page) {
  state.books.loading = true;
  clearStatus();
  render();

  try {
    const data = await requestJson('/api/app/books', {
      q: state.books.filters.q,
      type: state.books.filters.type,
      status: state.books.filters.status,
      tag_id: state.books.filters.tag_id,
      page,
      limit: 30,
    });
    state.books.rows = data.books || [];
    state.books.pagination = data.pagination || state.books.pagination;
    state.books.options = data.options || state.books.options;
    state.books.loaded = true;
  } catch (error) {
    setError(error);
  } finally {
    state.books.loading = false;
    render();
  }
}

async function loadCircles(page = state.circles.pagination.page) {
  state.circles.loading = true;
  clearStatus();
  render();

  try {
    const data = await requestJson('/api/app/circles', {
      q: state.circles.filters.q,
      tracked: state.circles.filters.tracked,
      priority: state.circles.filters.priority,
      page,
      limit: 30,
    });
    state.circles.rows = data.circles || [];
    state.circles.pagination = data.pagination || state.circles.pagination;
    state.circles.options = data.options || state.circles.options;
    state.circles.loaded = true;
  } catch (error) {
    setError(error);
  } finally {
    state.circles.loading = false;
    render();
  }
}

async function toggleCircleTracking(circleId) {
  clearStatus();
  try {
    const result = await requestJson(`/api/app/circles/${circleId}/track`, {}, { method: 'POST', body: {} });
    state.circles.rows = state.circles.rows.map((circle) => (
      Number(circle.id) === Number(circleId)
        ? { ...circle, is_tracked: result.is_tracked === true }
        : circle
    ));
    state.message = result.is_tracked ? '已加入追蹤。' : '已取消追蹤。';
  } catch (error) {
    setError(error);
  } finally {
    render();
  }
}

async function loadMaps() {
  if (state.maps.length > 0) {
    return;
  }

  const data = await requestJson('/api/app/c108/maps');
  state.maps = data.maps || [];
  const selected = state.maps.find((item) => String(item.day) === state.map.day && item.map === state.map.map)
    || state.maps[0];
  if (selected) {
    state.map.day = String(selected.day);
    state.map.map = selected.map;
  }
}

async function loadMap() {
  state.map.loading = true;
  clearStatus();
  render();

  try {
    await loadMaps();
    state.map.data = await requestJson('/api/app/c108/map', {
      day: state.map.day,
      map: state.map.map,
      relation: state.map.relation,
      priority: state.map.priority,
      q: state.map.q,
    });
    state.map.loaded = true;
  } catch (error) {
    setError(error);
  } finally {
    state.map.loading = false;
    render();
  }
}

function switchTab(tab) {
  state.activeTab = tab;
  if (tab === 'books' && !state.books.loaded) {
    loadBooks(1);
    return;
  }
  if (tab === 'circles' && !state.circles.loaded) {
    loadCircles(1);
    return;
  }
  if (tab === 'map' && !state.map.loaded) {
    loadMap();
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

function option(value, label, selected) {
  return `<option value="${h(value)}" ${String(selected) === String(value) ? 'selected' : ''}>${h(label)}</option>`;
}

function formatPrice(value) {
  if (value === null || value === undefined || value === '') {
    return '';
  }
  return `¥${Number(value).toLocaleString('ja-JP')}`;
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
    return '買過的社團';
  }
  return '一般社團';
}

function boothLabel(row) {
  if (!row) {
    return '';
  }
  if (row.position_label) {
    return row.position_label;
  }

  const day = row.day ? `${row.day}日目` : '';
  const block = `${row.map_name || row.map || ''} ${row.block_name || ''}${String(row.space_no || '').padStart(2, '0')}${row.space_no_sub || ''}`;
  return `${day} ${block}`.trim();
}

function render() {
  const title = {
    books: '藏書',
    circles: '社團',
    map: '地圖',
    settings: '設定',
  }[state.activeTab] || '藏書';

  app.innerHTML = `
    <div class="app-shell">
      <header class="topbar">
        <div>
          <div class="eyebrow">Personal Doujin Helper</div>
          <h1>${title}</h1>
        </div>
        <div class="topbar-actions">
          <button class="icon-button" data-action="refresh" title="重新整理">↻</button>
          <button class="icon-button" data-action="open-settings" title="設定">⚙</button>
        </div>
      </header>

      <nav class="tabs">
        ${tabButton('books', '書籍')}
        ${tabButton('circles', '社團')}
        ${tabButton('map', '地圖')}
      </nav>

      ${state.error ? `<div class="notice error">${h(state.error)}</div>` : ''}
      ${state.message ? `<div class="notice">${h(state.message)}</div>` : ''}

      <main>
        ${state.activeTab === 'books' ? renderBooks() : ''}
        ${state.activeTab === 'circles' ? renderCircles() : ''}
        ${state.activeTab === 'map' ? renderMap() : ''}
        ${state.activeTab === 'settings' ? renderSettings() : ''}
      </main>
    </div>
    ${state.selectedCircle ? renderCircleModal(state.selectedCircle) : ''}
  `;

  bindEvents();
}

function tabButton(id, label) {
  return `<button class="${state.activeTab === id ? 'active' : ''}" data-tab="${id}">${label}</button>`;
}

function renderBooks() {
  const page = state.books.pagination.page || 1;
  const totalPages = state.books.pagination.total_pages || 1;

  return `
    <section class="panel book-search">
      <input data-book-field="q" value="${h(state.books.filters.q)}" placeholder="搜尋書名、社團、作者、tag" enterkeyhint="search" />
      <div class="control-row three">
        <select data-book-field="type">
          ${option('', '全部類型', state.books.filters.type)}
          ${state.books.options.types.map((item) => option(item.value, item.label, state.books.filters.type)).join('')}
        </select>
        <select data-book-field="status">
          ${option('', '全部狀態', state.books.filters.status)}
          ${state.books.options.statuses.map((item) => option(item.value, item.label, state.books.filters.status)).join('')}
        </select>
        <select data-book-field="tag_id">
          ${option('', '全部分類', state.books.filters.tag_id)}
          ${state.books.options.tags.map((item) => option(item.value, item.label, state.books.filters.tag_id)).join('')}
        </select>
      </div>
      <div class="button-row">
        <button data-action="search-books">搜尋</button>
        <button data-action="open-url" data-url="${h(state.books.options.new_book_url || webUrl('/books/new'))}">新增書本</button>
      </div>
    </section>

    <section class="list-meta">
      <span>${h(state.books.pagination.total || 0)} 本</span>
      <span>第 ${h(page)} / ${h(totalPages)} 頁</span>
    </section>

    ${state.books.loading ? '<div class="notice">讀取書籍中...</div>' : ''}
    ${!state.books.loaded && !state.books.loading ? '<section class="empty"><button data-action="search-books">讀取藏書</button></section>' : ''}
    ${state.books.loaded && state.books.rows.length === 0 ? '<section class="empty">沒有符合條件的書。</section>' : ''}
    <section class="book-list">
      ${state.books.rows.map(renderBookCard).join('')}
    </section>
    ${state.books.loaded ? renderPager(page, totalPages) : ''}
  `;
}

function renderBookCard(book) {
  const tags = (book.tags || []).slice(0, 5);
  const works = (book.works || []).slice(0, 3);
  const meta = [
    book.type_label,
    book.circle ? `社團 ${book.circle}` : '',
    book.author ? `作者 ${book.author}` : '',
  ].filter(Boolean).join(' / ');
  const source = Number(book.source_count || 0) > 0
    ? `${book.source_count} 件來源${book.min_price ? `，最低 ${formatPrice(book.min_price)}` : ''}`
    : '';

  return `
    <article class="book-card">
      ${coverElement(book.cover_url, 'book-cover')}
      <div class="book-info">
        <div class="book-title-row">
          <h2>${h(book.title)}</h2>
          <span class="status-badge status-${h(book.status)}">${h(book.status_label)}</span>
        </div>
        <p class="muted">${h(meta)}</p>
        ${book.location ? `<p class="muted">位置 ${h(book.location)}</p>` : ''}
        ${source ? `<p class="muted">${h(source)}</p>` : ''}
        ${tags.length > 0 ? `<div class="chips">${tags.map((tag) => `<span>${h(tag)}</span>`).join('')}</div>` : ''}
        ${works.length > 0 ? `<div class="chips subtle">${works.map((tag) => `<span>${h(tag)}</span>`).join('')}</div>` : ''}
        <div class="card-actions">
          <button data-action="open-url" data-url="${h(book.edit_url || webUrl(`/books/${book.id}/edit`))}">編輯</button>
        </div>
      </div>
    </article>
  `;
}

function renderPager(page, totalPages) {
  return `
    <section class="pager">
      <button data-action="page-books" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>上一頁</button>
      <span>${h(page)} / ${h(totalPages)}</span>
      <button data-action="page-books" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>下一頁</button>
    </section>
  `;
}

function renderCircles() {
  const page = state.circles.pagination.page || 1;
  const totalPages = state.circles.pagination.total_pages || 1;

  return `
    <section class="panel circle-search">
      <input data-circle-field="q" value="${h(state.circles.filters.q)}" placeholder="搜尋社團、首字、備註" enterkeyhint="search" />
      <div class="control-row two">
        <select data-circle-field="tracked">
          ${option('', '全部社團', state.circles.filters.tracked)}
          ${option('1', '追蹤中', state.circles.filters.tracked)}
          ${option('0', '未追蹤', state.circles.filters.tracked)}
        </select>
        <select data-circle-field="priority">
          ${option('', '全部優先度', state.circles.filters.priority)}
          ${state.circles.options.priorities.map((item) => option(item.value, item.label, state.circles.filters.priority)).join('')}
        </select>
      </div>
      <button data-action="search-circles">搜尋</button>
    </section>

    <section class="list-meta">
      <span>${h(state.circles.pagination.total || 0)} 個社團</span>
      <span>第 ${h(page)} / ${h(totalPages)} 頁</span>
    </section>

    ${state.circles.loading ? '<div class="notice">讀取社團中...</div>' : ''}
    ${!state.circles.loaded && !state.circles.loading ? '<section class="empty"><button data-action="search-circles">讀取社團</button></section>' : ''}
    ${state.circles.loaded && state.circles.rows.length === 0 ? '<section class="empty">沒有符合條件的社團。</section>' : ''}
    <section class="circle-list">
      ${state.circles.rows.map(renderCircleCard).join('')}
    </section>
    ${state.circles.loaded ? renderCirclePager(page, totalPages) : ''}
  `;
}

function renderCircleCard(circle) {
  const links = circle.links || [];
  const books = circle.books || [];
  const stats = [
    `${circle.book_count || 0} 本`,
    circle.wishlist_count ? `願望 ${circle.wishlist_count} 本` : '',
    circle.circlems_id ? `Circle.ms ${circle.circlems_id}` : '',
  ].filter(Boolean).join(' / ');

  return `
    <article class="circle-card ${circle.is_tracked ? 'tracked' : ''}">
      ${coverElement(circle.cut_url, 'circle-cut')}
      <div class="circle-info">
        <div class="circle-title-row">
          <h2>${h(circle.name)}</h2>
          <span class="status-badge">${circle.is_tracked ? '追蹤中' : '未追蹤'}</span>
        </div>
        <p class="muted">${h([circle.name_kana, circle.priority_label].filter(Boolean).join(' / '))}</p>
        ${stats ? `<p class="muted">${h(stats)}</p>` : ''}
        ${circle.note ? `<p class="circle-note">${h(circle.note)}</p>` : ''}
        ${links.length > 0 ? `<div class="link-chips">${links.map((link) => `<a href="${h(link.url)}" target="_blank" rel="noreferrer">${h(link.label)}</a>`).join('')}</div>` : ''}
        ${books.length > 0 ? renderCircleBooks(books) : ''}
        <div class="card-actions">
          <button data-action="toggle-circle-track" data-id="${h(circle.id)}">${circle.is_tracked ? '取消追蹤' : '追蹤'}</button>
        </div>
      </div>
    </article>
  `;
}

function renderCircleBooks(books) {
  return `
    <div class="related-books">
      ${books.slice(0, 6).map((book) => `
        <button class="related-book" data-action="open-url" data-url="${h(book.edit_url || webUrl(`/books/${book.id}/edit`))}">
          ${coverElement(book.cover_url, 'related-book-cover')}
          <span>
            <strong>${h(book.title)}</strong>
            <small>${h(book.status_label || '')}</small>
          </span>
        </button>
      `).join('')}
    </div>
  `;
}

function renderCirclePager(page, totalPages) {
  return `
    <section class="pager">
      <button data-action="page-circles" data-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>上一頁</button>
      <span>${h(page)} / ${h(totalPages)}</span>
      <button data-action="page-circles" data-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>下一頁</button>
    </section>
  `;
}

function renderMap() {
  const data = state.map.data;
  const dayOptions = [...new Set(state.maps.map((item) => String(item.day)))];
  const visibleDays = dayOptions.length > 0 ? dayOptions : ['1', '2'];
  const mapOptions = state.maps.filter((item) => String(item.day) === state.map.day);

  return `
    <section class="panel map-controls">
      <input data-map-field="q" value="${h(state.map.q)}" placeholder="搜尋社團、作者、攤位、備註" enterkeyhint="search" />
      <div class="control-row two">
        <select data-map-field="day">
          ${visibleDays.map((day) => option(day, `${day}日目`, state.map.day)).join('')}
        </select>
        <select data-map-field="map">
          ${mapOptions.map((item) => option(item.map, item.name || item.map, state.map.map)).join('')}
        </select>
      </div>
      <div class="control-row three">
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
        <button data-action="load-map">顯示地圖</button>
      </div>
    </section>
    ${state.map.loading ? '<div class="notice">讀取地圖中...</div>' : ''}
    ${data ? renderMapCanvas(data) : '<section class="empty"><button data-action="load-map">讀取地圖</button></section>'}
  `;
}

function renderMapCanvas(data) {
  if (!data.image?.url) {
    return '<section class="empty">這張地圖沒有可用圖片。</section>';
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
        <img src="${h(data.image.url)}" alt="event map" />
        ${(data.rows || []).map((row, index) => renderMarker(row, index)).join('')}
      </div>
    </section>
  `;
}

function renderMarker(row, index) {
  const marker = row.marker || {};
  const classes = ['marker', marker.axis === 'y' ? 'axis-y' : 'axis-x'];
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

function renderSettings() {
  return `
    <section class="panel settings">
      <label>
        API URL
        <input data-field="apiBase" value="${h(state.settings.apiBase)}" inputmode="url" />
      </label>
      <label>
        管理碼
        <input data-field="passcode" value="${h(state.settings.passcode)}" type="password" autocomplete="current-password" />
      </label>
      <label>
        圖片顯示
        <select data-field="showImages">
          ${option('0', '遮蔽圖片', state.settings.showImages ? '1' : '0')}
          ${option('1', '顯示圖片', state.settings.showImages ? '1' : '0')}
        </select>
      </label>
      <div class="settings-actions">
        <button data-action="save-settings">儲存設定</button>
        <button data-action="test-connection">測試連線</button>
      </div>
    </section>
    <section class="hint-panel">
      這個 APK 只連到你的 Doujin Shelf 私有 API。書籍資料來自藏書清單，地圖資料來自目前已匯入的離線活動資料。
    </section>
  `;
}

function renderCircleModal(row) {
  return `
    <div class="modal-backdrop" data-action="close-modal">
      <article class="modal" role="dialog" aria-modal="true">
        <button class="modal-close" data-action="close-modal">×</button>
        <header class="circle-header">
          ${coverElement(row.cut_url, 'modal-cut')}
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
            ${coverElement(book.cover_url, 'book-chip-cover')}
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
    input.addEventListener('input', () => syncSettingInput(input));
    input.addEventListener('change', () => syncSettingInput(input));
  });

  app.querySelectorAll('[data-book-field]').forEach((input) => {
    input.addEventListener('input', () => {
      state.books.filters[input.dataset.bookField] = input.value;
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        loadBooks(1);
      }
    });
  });

  app.querySelectorAll('[data-circle-field]').forEach((input) => {
    input.addEventListener('input', () => {
      state.circles.filters[input.dataset.circleField] = input.value;
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        loadCircles(1);
      }
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
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        loadMap();
      }
    });
  });

  app.querySelectorAll('[data-action]').forEach((element) => {
    element.addEventListener('click', (event) => {
      const action = element.dataset.action;
      if (action === 'save-settings') {
        app.querySelectorAll('[data-field]').forEach((input) => syncSettingInput(input));
        saveSettings();
        state.message = '已儲存設定。';
        render();
      } else if (action === 'test-connection') {
        saveSettings();
        state.activeTab = 'books';
        loadBooks(1);
      } else if (action === 'open-settings') {
        state.activeTab = 'settings';
        render();
      } else if (action === 'refresh') {
        refreshActive();
      } else if (action === 'search-books') {
        loadBooks(1);
      } else if (action === 'page-books') {
        loadBooks(Number(element.dataset.page || 1));
      } else if (action === 'search-circles') {
        loadCircles(1);
      } else if (action === 'page-circles') {
        loadCircles(Number(element.dataset.page || 1));
      } else if (action === 'toggle-circle-track') {
        toggleCircleTracking(Number(element.dataset.id || 0));
      } else if (action === 'open-url') {
        const url = element.dataset.url || '';
        if (url) {
          openExternal(url);
        }
      } else if (action === 'load-map') {
        loadMap();
      } else if (action === 'circle') {
        const row = state.map.data?.rows?.[Number(element.dataset.index)];
        state.selectedCircle = row || null;
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
  } else if (state.activeTab === 'circles') {
    loadCircles(state.circles.pagination.page || 1);
  } else if (state.activeTab === 'settings') {
    state.message = '設定頁沒有需要重新整理的資料。';
    render();
  } else {
    loadBooks(state.books.pagination.page || 1);
  }
}

render();
if (state.settings.passcode) {
  loadBooks(1);
}
