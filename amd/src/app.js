/**
 * StudyMark app module.
 *
 * @module     mod_studymark/app
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str', 'mod_studymark/highlighter', 'mod_studymark/documentreader'], function(Ajax, Notification, Str, Highlighter, DocumentReader) {
    const call = (method, args) => Ajax.call([{methodname: method, args}])[0];
    const escape = value => { const node = document.createElement('div'); node.textContent = value || ''; return node.innerHTML; };
    const svg = path => `<svg class="studymark-icon" aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="${path}"/></svg>`;
    const icons = {
        outline: svg('M3 5h3v3H3V5m5 0h13v3H8V5M3 10.5h3v3H3v-3m5 0h13v3H8v-3M3 16h3v3H3v-3m5 0h13v3H8v-3'),
        summary: svg('M4 5h16v2H4V5m0 4h10v2H4V9m0 4h16v2H4v-2m0 4h10v2H4v-2'),
        full: svg('M4 4h16v2H4V4m0 4h16v2H4V8m0 4h16v2H4v-2m0 4h16v2H4v-2'),
        original: svg('M6 2h9l5 5v15H6V2m8 1.5V8h4.5L14 3.5M8 12v2h8v-2H8m0 4v2h8v-2H8'),
        exit: svg('M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5v-2H5V5h5V3m4.59 4.59L16 9l-3 3H7v2h6l3 3 1.41-1.41L16.83 15H21v-2h-4.17l.58-.59L16 11l-1.41 1.41L14 13h-1l3-3-1.41-1.41Z'),
        reference: svg('M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2'),
        visibility: svg('M12 5c5.5 0 9.5 5.2 9.5 7s-4 7-9.5 7S2.5 13.8 2.5 12 6.5 5 12 5m0 2c-3.8 0-6.8 3.2-7.4 5 .6 1.8 3.6 5 7.4 5s6.8-3.2 7.4-5C18.8 10.2 15.8 7 12 7m0 1.5A3.5 3.5 0 1 1 8.5 12 3.5 3.5 0 0 1 12 8.5m0 2A1.5 1.5 0 1 0 13.5 12 1.5 1.5 0 0 0 12 10.5'),
        remove: svg('M6 7h12l-1 14H7L6 7m3-4h6l1 2h4v2H4V5h4l1-2'),
        analytics: svg('M3 3h2v18H3V3m4 10h3v8H7v-8m5-6h3v14h-3V7m5 3h3v11h-3V10'),
        fullscreen: svg('M4 4h6v2H6v4H4V4m10 0h6v6h-2V6h-4V4M4 14h2v4h4v2H4v-6m14 0h2v6h-6v-2h4v-4')
    };
    const highlightColors = [
        {value: '#fff176', key: 'yellow'},
        {value: '#90caf9', key: 'blue'},
        {value: '#a5d6a7', key: 'green'},
        {value: '#f8bbd0', key: 'pink'},
        {value: '#ffcc80', key: 'orange'}
    ];

    const loadStrings = async() => {
        const keys = ['readertools', 'summaryreading', 'fullreading', 'originaldocument', 'analytics', 'exittocourse',
            'togglefullscreen', 'documentoutline', 'outline', 'loadingdocument', 'noheadings', 'nosummaryhighlights',
            'nodocument', 'documentprocesserror', 'deletehighlightconfirm', 'extractingdocument', 'downloaddocumenterror',
            'pdfjsunavailable', 'extractingpage', 'of', 'jszipunavailable', 'invalidepub', 'invaliddocx',
            'hideoutline', 'showoutline', 'containshighlights', 'referencemode', 'referencemodeactive',
            'showreference', 'hidereference', 'viewreference', 'hidereferenceview',
            'highlightcolor', 'yellow', 'blue', 'green', 'pink', 'orange', 'selectedcolor',
            'deletehighlight', 'selectedhighlight', 'illustration', 'illustrationpage'];
        const values = await Str.get_strings(keys.map(key => ({key, component: 'mod_studymark'})));
        return Object.fromEntries(keys.map((key, index) => [key, values[index]]));
    };

    async function init(config) {
        const s = await loadStrings();
        const root = document.getElementById('studymark-app');
        if (window.matchMedia('(max-width: 700px)').matches) root.classList.add('studymark-focus');
        const palette = highlightColors.map(color => `<button type="button" class="studymark-color-swatch" ` +
            `data-highlight-color="${color.value}" aria-pressed="${color.value === '#fff176'}" ` +
            `aria-label="${escape(s[color.key])}" title="${escape(s[color.key])}" ` +
            `style="--studymark-swatch:${color.value}"><span aria-hidden="true"></span></button>`).join('');
        root.innerHTML = `<nav class="studymark-reader-toolbar" aria-label="${escape(s.readertools)}">` +
            (config.canhighlight ? `<div class="studymark-color-picker" role="group" aria-label="${escape(s.highlightcolor)}">` +
                `<span class="studymark-color-picker-label">${escape(s.highlightcolor)}</span>${palette}` +
                `<button type="button" class="studymark-highlight-delete" data-action="delete-highlight" ` +
                `aria-label="${escape(s.deletehighlight)}" title="${escape(s.deletehighlight)}" hidden disabled>${icons.remove}</button></div>` : '') +
            `<div class="studymark-toolbar-actions">` +
            `<button type="button" class="btn btn-icon btn-outline-secondary" data-action="toggle-outline" aria-expanded="true" aria-label="${escape(s.hideoutline)}" title="${escape(s.hideoutline)}">${icons.outline}</button>` +
            `<button type="button" class="btn btn-icon btn-outline-secondary" data-action="summary" disabled aria-label="${escape(s.summaryreading)}" title="${escape(s.summaryreading)}">${icons.summary}</button>` +
            `<a class="btn btn-icon btn-outline-secondary" data-action="original" href="#" target="_blank" rel="noopener" aria-label="${escape(s.originaldocument)}" title="${escape(s.originaldocument)}">${icons.original}</a>` +
            (config.canmanage ? `<button type="button" class="btn btn-icon btn-outline-secondary active" data-action="reference" aria-pressed="true" aria-label="${escape(s.referencemodeactive)}" title="${escape(s.referencemodeactive)}">${icons.reference}</button><button type="button" class="btn btn-icon btn-outline-secondary ${config.showreference ? 'active' : ''}" data-action="reference-visibility" aria-pressed="${String(Boolean(config.showreference))}" aria-label="${escape(config.showreference ? s.hidereference : s.showreference)}" title="${escape(config.showreference ? s.hidereference : s.showreference)}">${icons.visibility}</button><a class="btn btn-icon btn-outline-secondary" href="${config.reporturl}" aria-label="${escape(s.analytics)}" title="${escape(s.analytics)}">${icons.analytics}</a>` : (config.showreference ? `<button type="button" class="btn btn-icon btn-outline-secondary active" data-action="toggle-reference-view" aria-pressed="true" aria-label="${escape(s.hidereferenceview)}" title="${escape(s.hidereferenceview)}">${icons.visibility}</button>` : '')) +
            `<a class="btn btn-icon btn-outline-secondary" href="${config.courseurl}" aria-label="${escape(s.exittocourse)}" title="${escape(s.exittocourse)}">${icons.exit}</a>` +
            `<button type="button" class="btn btn-icon btn-outline-secondary" data-action="toggle-focus" aria-label="${escape(s.togglefullscreen)}" title="${escape(s.togglefullscreen)}">${icons.fullscreen}</button>` +
            `</div></nav><div class="studymark-layout"><nav class="studymark-outline" aria-label="${escape(s.documentoutline)}"><h2>${escape(s.outline)}</h2><div data-region="outline"></div></nav><main class="studymark-reader"><div data-region="viewer" class="studymark-empty">${escape(s.loadingdocument)}</div></main></div>`;
        const viewer = root.querySelector('[data-region=viewer]');
        const outline = root.querySelector('[data-region=outline]');
        const summaryButton = root.querySelector('[data-action=summary]');
        const outlineButton = root.querySelector('[data-action=toggle-outline]');
        let documentRecord = null;
        let highlighter = null;
        let highlights = [];
        let readingArticle = null;
        let summaryMode = false;
        let referenceMode = Boolean(config.canmanage);
        let referenceVisible = Boolean(config.showreference);
        let highlightColor = '#fff176';
        let selectedHighlightId = null;

        function syncPalette(color) {
            root.querySelectorAll('[data-highlight-color]').forEach(button => {
                const selected = button.dataset.highlightColor === color;
                button.classList.toggle('active', selected);
                button.setAttribute('aria-pressed', String(selected));
                button.title = selected ? `${s.selectedcolor}: ${button.getAttribute('aria-label')}` : button.getAttribute('aria-label');
            });
        }

        function selectHighlight(record) {
            if (!record?.canedit) return;
            selectedHighlightId = +record.id;
            highlightColor = record.color;
            highlighter?.setColor(highlightColor);
            syncPalette(highlightColor);
            readingArticle.querySelectorAll('mark[data-highlight-id]').forEach(mark =>
                mark.classList.toggle('studymark-highlight-selected', +mark.dataset.highlightId === selectedHighlightId));
            const deleteButton = root.querySelector('[data-action=delete-highlight]');
            if (deleteButton) { deleteButton.hidden = false; deleteButton.disabled = false; }
        }

        function clearSelectedHighlight() {
            selectedHighlightId = null;
            readingArticle?.querySelectorAll('.studymark-highlight-selected').forEach(mark =>
                mark.classList.remove('studymark-highlight-selected'));
            const deleteButton = root.querySelector('[data-action=delete-highlight]');
            if (deleteButton) { deleteButton.hidden = true; deleteButton.disabled = true; }
        }

        function renderOutline() {
            const headings = Array.from(readingArticle.querySelectorAll('h1,h2,h3,h4,h5,h6')).filter(heading => !/^\d+$/.test(heading.textContent.trim()));
            if (!headings.length) {
                outline.innerHTML = `<p class="text-muted small">${escape(s.noheadings)}</p>`;
                return;
            }
            outline.innerHTML = headings.map(heading => {
                const level = +heading.tagName.slice(1);
                const id = `studymark-${heading.dataset.blockId}`;
                heading.id = id;
                return `<a class="studymark-outline-link level-${level}" href="#${id}" data-outline-id="${id}"><span class="studymark-outline-marker" aria-hidden="true"></span>${escape(heading.textContent)}</a>`;
            }).join('');
            updateOutlineHighlights();
        }
        function updateOutlineHighlights() {
            readingArticle.querySelectorAll('h1,h2,h3,h4,h5,h6').forEach(heading => {
                const link = outline.querySelector(`[data-outline-id="${heading.id}"]`);
                if (!link) return;
                const level = +heading.tagName.slice(1);
                let block = heading;
                let contains = false;
                while (block) {
                    if (block !== heading && /^H[1-6]$/.test(block.tagName) && +block.tagName.slice(1) <= level) break;
                    if (block.querySelector('mark[data-highlight-id]')) { contains = true; break; }
                    block = block.nextElementSibling;
                }
                link.classList.toggle('has-highlights', contains);
                link.title = contains ? s.containshighlights : '';
            });
        }

        function updateOutlineButton() {
            const expanded = !root.classList.contains('studymark-outline-collapsed');
            const label = expanded ? s.hideoutline : s.showoutline;
            outlineButton.setAttribute('aria-expanded', String(expanded));
            outlineButton.setAttribute('aria-label', label);
            outlineButton.title = label;
        }

        function highlightExcerpt(block) {
            const excerpt = document.createElement(block.tagName.toLowerCase());
            excerpt.dataset.blockId = block.dataset.blockId;
            if (block.dataset.page) excerpt.dataset.page = block.dataset.page;
            Array.from(block.querySelectorAll('mark[data-highlight-id]')).forEach((mark, index) => {
                if (index) excerpt.append(document.createTextNode(' … '));
                excerpt.append(mark.cloneNode(true));
            });
            return excerpt;
        }

        function buildSummary() {
            const summary = document.createElement('article');
            summary.className = 'studymark-reading-document studymark-reading-summary';
            const headings = [], emitted = new Set();
            Array.from(readingArticle.children).forEach(block => {
                const match = block.tagName.match(/^H([1-6])$/);
                if (match) {
                    const level = +match[1]; headings.splice(level - 1); headings[level - 1] = block;
                    if (block.querySelector('mark[data-highlight-id]')) {
                        headings.slice(0, level - 1).forEach(heading => {
                            if (heading && !emitted.has(heading.dataset.blockId)) {
                                summary.append(heading.cloneNode(true)); emitted.add(heading.dataset.blockId);
                            }
                        });
                        if (!emitted.has(block.dataset.blockId)) {
                            summary.append(highlightExcerpt(block)); emitted.add(block.dataset.blockId);
                        }
                    }
                    return;
                }
                if (!block.querySelector('mark[data-highlight-id]')) return;
                headings.forEach(heading => {
                    if (heading && !emitted.has(heading.dataset.blockId)) {
                        summary.append(heading.cloneNode(true)); emitted.add(heading.dataset.blockId);
                    }
                });
                summary.append(highlightExcerpt(block));
            });
            if (!summary.children.length) summary.innerHTML = `<p class="studymark-summary-empty">${escape(s.nosummaryhighlights)}</p>`;
            return summary;
        }

        function renderMode() {
            viewer.replaceChildren(summaryMode ? buildSummary() : readingArticle);
            const label = summaryMode ? s.fullreading : s.summaryreading;
            summaryButton.innerHTML = summaryMode ? icons.full : icons.summary;
            summaryButton.title = label; summaryButton.setAttribute('aria-label', label);
            summaryButton.setAttribute('aria-pressed', String(summaryMode));
        }

        async function openDocument() {
            highlights = await call('mod_studymark_get_highlights', {cmid: config.cmid, documentid: documentRecord.id, groupid: 0});
            viewer.className = 'studymark-reading-host';
            const reader = new DocumentReader({
                url: documentRecord.url, filename: documentRecord.filename, mimetype: documentRecord.mimetype,
                workerUrl: config.pdfworkerurl, strings: s, progress: message => { viewer.innerHTML = `<div class="studymark-reading-progress">${escape(message)}</div>`; }
            });
            readingArticle = await reader.load();
            if (config.canmanage) {
                await call('mod_studymark_set_document_length', {cmid: config.cmid, documentid: documentRecord.id,
                    textlength: readingArticle.textContent.length});
            }
            highlighter = new Highlighter(readingArticle, config.canhighlight ? async data => {
                const saved = await call('mod_studymark_create_highlight', {
                    cmid: config.cmid, documentid: documentRecord.id, groupid: 0, pagenumber: 0,
                    note: '', headingcontext: '', shared: false, ...data, reference: referenceMode,
                    color: referenceMode ? '#90caf9' : data.color
                });
                highlights.push(saved); highlighter.apply([saved]); updateOutlineHighlights();
            } : null);
            highlighter.setColor(highlightColor);
            highlighter.apply(highlights);
            renderOutline(); summaryButton.disabled = false; renderMode();
        }

        try {
            const documents = await call('mod_studymark_get_documents', {cmid: config.cmid});
            documentRecord = documents[0] || null;
            if (!documentRecord) { viewer.innerHTML = `<div class="studymark-empty"><p>${escape(s.nodocument)}</p></div>`; return; }
            root.querySelector('[data-action=original]').href = documentRecord.url;
            await openDocument();
        } catch (error) {
            viewer.innerHTML = `<div class="alert alert-danger m-3">${escape(s.documentprocesserror)}</div>`;
            Notification.exception(error);
        }

        root.addEventListener('click', async event => {
            const colorButton = event.target.closest('[data-highlight-color]');
            if (colorButton) {
                highlightColor = colorButton.dataset.highlightColor;
                syncPalette(highlightColor);
                highlighter?.setColor(highlightColor);
                if (selectedHighlightId) {
                    const record = highlights.find(item => +item.id === selectedHighlightId);
                    if (record?.canedit && record.color !== highlightColor) {
                        const updated = await call('mod_studymark_update_highlight', {
                            cmid: config.cmid, highlightid: selectedHighlightId, color: highlightColor,
                            note: record.note || '', shared: Boolean(record.shared), groupid: record.groupid || 0
                        });
                        record.color = updated.color;
                        readingArticle.querySelectorAll(`[data-highlight-id="${selectedHighlightId}"]`).forEach(mark =>
                            mark.style.backgroundColor = updated.color);
                        renderMode(); updateOutlineHighlights();
                    }
                }
                return;
            }
            const action = event.target.closest('[data-action]')?.dataset.action;
            if (action === 'toggle-focus') { root.classList.toggle('studymark-focus'); return; }
            if (action === 'toggle-outline') { root.classList.toggle('studymark-outline-collapsed'); updateOutlineButton(); return; }
            if (action === 'summary') { summaryMode = !summaryMode; renderMode(); return; }
            if (action === 'delete-highlight' && selectedHighlightId) {
                const id = selectedHighlightId;
                if (!window.confirm(s.deletehighlightconfirm)) return;
                await call('mod_studymark_delete_highlight', {cmid: config.cmid, highlightid: id});
                readingArticle.querySelectorAll(`[data-highlight-id="${id}"]`).forEach(savedMark => {
                    const parent = savedMark.parentNode;
                    while (savedMark.firstChild) parent.insertBefore(savedMark.firstChild, savedMark);
                    savedMark.remove(); parent.normalize();
                });
                highlights = highlights.filter(item => +item.id !== id);
                clearSelectedHighlight(); renderMode(); updateOutlineHighlights();
                return;
            }
            if (action === 'toggle-reference-view') {
                const hidden = root.classList.toggle('studymark-reference-hidden');
                const button = root.querySelector('[data-action=toggle-reference-view]');
                button.classList.toggle('active', !hidden);
                button.setAttribute('aria-pressed', String(!hidden));
                const label = hidden ? s.viewreference : s.hidereferenceview;
                button.title = label; button.setAttribute('aria-label', label);
                return;
            }            if (action === 'reference-visibility') {
                referenceVisible = await call('mod_studymark_set_reference_visibility', {
                    cmid: config.cmid, visible: !referenceVisible
                });
                const button = root.querySelector('[data-action=reference-visibility]');
                button.classList.toggle('active', referenceVisible);
                button.setAttribute('aria-pressed', String(referenceVisible));
                const label = referenceVisible ? s.hidereference : s.showreference;
                button.title = label; button.setAttribute('aria-label', label);
                return;
            }
            if (action === 'reference') {
                referenceMode = !referenceMode;
                const button = root.querySelector('[data-action=reference]');
                button.classList.toggle('active', referenceMode);
                button.setAttribute('aria-pressed', String(referenceMode));
                const label = referenceMode ? s.referencemodeactive : s.referencemode;
                button.title = label; button.setAttribute('aria-label', label);
                return;
            }
            const outlineId = event.target.closest('[data-outline-id]')?.dataset.outlineId;
            if (outlineId) {
                event.preventDefault();
                if (summaryMode) { summaryMode = false; renderMode(); }
                requestAnimationFrame(() => {
                    const target = readingArticle.querySelector(`#${CSS.escape(outlineId)}`);
                    if (target) viewer.scrollTo({top: target.offsetTop - readingArticle.offsetTop - 16, behavior: 'smooth'});
                });
                return;
            }
            const mark = event.target.closest('mark[data-highlight-id]');
            if (!mark) return;
            const selection = window.getSelection();
            if (selection && !selection.isCollapsed) return;
            const id = +mark.dataset.highlightId;
            const record = highlights.find(item => +item.id === id);
            if (!record?.canedit) return;
            selectHighlight(record);
        });
    }
    return {init};
});
