/**
 * Changelog widget — najde `.changelog-widget` element a naplní ho
 * posledními 5 záznamy z /api/list_changelog.php.
 * Jazyk se odvozuje z <html lang>.
 */
(function () {
  'use strict';

  const isEn = (document.documentElement.lang || '').toLowerCase() === 'en';
  const lang = isEn ? 'en' : 'cs';

  const CAT = {
    feature:   { icon: '🆕', cs: 'Novinka',   en: 'New',       cls: 'success'   },
    fix:       { icon: '🔧', cs: 'Oprava',    en: 'Fix',       cls: 'gold'      },
    change:    { icon: '⚔',  cs: 'Změna',     en: 'Change',    cls: 'warning'   },
    important: { icon: '⚠',  cs: 'Důležité',  en: 'Important', cls: 'danger'    },
    event:     { icon: '🎉', cs: 'Událost',   en: 'Event',     cls: 'event'     }
  };

  function formatDate(s) {
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    if (isEn) {
      const m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      return `${m[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
    }
    return `${d.getDate()}. ${d.getMonth()+1}. ${d.getFullYear()}`;
  }

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function clip(s, n) {
    if (!s) return '';
    return s.length > n ? s.slice(0, n).trimEnd() + '…' : s;
  }

  async function load(widget) {
    try {
      const res = await fetch(`/api/list_changelog.php?limit=5&lang=${lang}`, { cache: 'no-store' });
      const data = await res.json();
      if (!data.ok) {
        widget.innerHTML = `<div class="muted">${isEn ? 'Failed to load news.' : 'Nepodařilo se načíst aktuality.'}</div>`;
        return;
      }
      render(widget, data.entries);
    } catch (e) {
      console.error('[changelog]', e);
      widget.innerHTML = `<div class="muted">${isEn ? 'Connection error.' : 'Chyba spojení.'}</div>`;
    }
  }

  function render(widget, entries) {
    const heading  = isEn ? 'Server News' : 'Aktuality serveru';
    const more     = isEn ? 'View all →'   : 'Zobrazit vše →';
    const empty    = isEn ? 'No news yet.'  : 'Žádné aktuality.';
    const pageHref = isEn ? '/pages/news-en.html' : '/pages/news.html';

    let html = `
      <div class="changelog-header">
        <h3>📜 ${heading}</h3>
        <a href="${pageHref}" class="changelog-more-link">${more}</a>
      </div>
      <div class="changelog-items">
    `;

    if (!entries || entries.length === 0) {
      html += `<div class="muted">${empty}</div>`;
    } else {
      entries.forEach(e => {
        const meta = CAT[e.category] || CAT.change;
        const label = isEn ? meta.en : meta.cs;
        const fb = e.lang_fallback ? ' <span class="changelog-fallback" title="CZ original">[CZ]</span>' : '';
        const body = escapeHtml(clip(e.body, 200)).replace(/\n/g, '<br>');
        html += `
          <article class="changelog-item changelog-cat-${meta.cls}">
            <div class="changelog-item-head">
              <span class="changelog-cat">${meta.icon} ${label}</span>
              <span class="changelog-date">${formatDate(e.created_at)}</span>
            </div>
            <h4 class="changelog-title">${escapeHtml(e.title)}${fb}</h4>
            <div class="changelog-body">${body}</div>
          </article>
        `;
      });
    }

    html += '</div>';
    widget.innerHTML = html;
  }

  function init() {
    const widgets = document.querySelectorAll('.changelog-widget');
    widgets.forEach(load);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
