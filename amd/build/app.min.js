define(['core/ajax', 'core/notification', 'mod_studymark/highlighter', 'mod_studymark/documentreader'], function(Ajax, Notification, Highlighter, DocumentReader) {
    const call = (method, args) => Ajax.call([{methodname: method, args}])[0];
    const escape = value => { const node = document.createElement('div'); node.textContent = value || ''; return node.innerHTML; };
    const svg = path => `<svg class="studymark-icon" aria-hidden="true" viewBox="0 0 24 24"><path fill="currentColor" d="${path}"/></svg>`;
    const icons = {
        summary: svg('M4 5h16v2H4V5m0 4h10v2H4V9m0 4h16v2H4v-2m0 4h10v2H4v-2'),
        full: svg('M4 4h16v2H4V4m0 4h16v2H4V8m0 4h16v2H4v-2m0 4h16v2H4v-2'),
        original: svg('M6 2h9l5 5v15H6V2m8 1.5V8h4.5L14 3.5M8 12v2h8v-2H8m0 4v2h8v-2H8'),
        exit: svg('M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5v-2H5V5h5V3m4.59 4.59L16 9l-3 3H7v2h6l3 3 1.41-1.41L16.83 15H21v-2h-4.17l.58-.59L16 11l-1.41 1.41L14 13h-1l3-3-1.41-1.41Z'),
        analytics: svg('M3 3h2v18H3V3m4 10h3v8H7v-8m5-6h3v14h-3V7m5 3h3v11h-3V10'),
        fullscreen: svg('M4 4h6v2H6v4H4V4m10 0h6v6h-2V6h-4V4M4 14h2v4h4v2H4v-6m14 0h2v6h-6v-2h4v-4')
    };

    async function init(config) {
        const root = document.getElementById('studymark-app');
        if (window.matchMedia('(max-width: 700px)').matches) root.classList.add('studymark-focus');
        root.innerHTML = '<nav class="studymark-reader-toolbar" aria-label="Herramientas de lectura">' +
            `<button type="button" class="btn btn-icon btn-outline-secondary" data-action="summary" disabled aria-label="Lectura abreviada" title="Lectura abreviada">${icons.summary}</button>` +
            `<a class="btn btn-icon btn-outline-secondary" data-action="original" href="#" target="_blank" rel="noopener" aria-label="Documento original" title="Documento original">${icons.original}</a>` +
            (config.canmanage ? `<a class="btn btn-icon btn-outline-secondary" href="${config.reporturl}" aria-label="Informe de subrayado" title="Informe de subrayado">${icons.analytics}</a>` : '') +
            `<a class="btn btn-icon btn-outline-secondary" href="${config.courseurl}" aria-label="Salir al curso" title="Salir al curso">${icons.exit}</a>` +
            `<button type="button" class="btn btn-icon btn-outline-secondary" data-action="toggle-focus" aria-label="Alternar pantalla completa" title="Alternar pantalla completa">${icons.fullscreen}</button>` +
            '</nav><div class="studymark-layout"><nav class="studymark-outline" aria-label="Índice del documento"><h2>Índice</h2><div data-region="outline"></div></nav><main class="studymark-reader"><div data-region="viewer" class="studymark-empty">Cargando documento…</div></main></div>';
        const viewer = root.querySelector('[data-region=viewer]');
        const outline = root.querySelector('[data-region=outline]');
        const summaryButton = root.querySelector('[data-action=summary]');
        let documentRecord = null;
        let highlighter = null;
        let highlights = [];
        let readingArticle = null;
        let summaryMode = false;

        function renderOutline() {
            const headings = Array.from(readingArticle.querySelectorAll('h1,h2,h3,h4,h5,h6')).filter(heading => !/^\d+$/.test(heading.textContent.trim()));
            if (!headings.length) {
                outline.innerHTML = '<p class="text-muted small">Este documento no contiene encabezados.</p>';
                return;
            }
            outline.innerHTML = headings.map(heading => {
                const level = +heading.tagName.slice(1);
                const id = `studymark-${heading.dataset.blockId}`;
                heading.id = id;
                return `<a class="studymark-outline-link level-${level}" href="#${id}" data-outline-id="${id}">${escape(heading.textContent)}</a>`;
            }).join('');
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
            if (!summary.children.length) summary.innerHTML = '<p class="studymark-summary-empty">Aún no hay subrayados para construir la lectura abreviada.</p>';
            return summary;
        }

        function renderMode() {
            viewer.replaceChildren(summaryMode ? buildSummary() : readingArticle);
            const label = summaryMode ? 'Lectura completa' : 'Lectura abreviada';
            summaryButton.innerHTML = summaryMode ? icons.full : icons.summary;
            summaryButton.title = label; summaryButton.setAttribute('aria-label', label);
            summaryButton.setAttribute('aria-pressed', String(summaryMode));
        }

        async function openDocument() {
            highlights = await call('mod_studymark_get_highlights', {cmid: config.cmid, documentid: documentRecord.id, groupid: 0});
            viewer.className = 'studymark-reading-host';
            const reader = new DocumentReader({
                url: documentRecord.url, filename: documentRecord.filename, mimetype: documentRecord.mimetype,
                workerUrl: config.pdfworkerurl, progress: message => { viewer.innerHTML = `<div class="studymark-reading-progress">${escape(message)}</div>`; }
            });
            readingArticle = await reader.load();
            if (config.canhighlight) {
                highlighter = new Highlighter(readingArticle, async data => {
                    const saved = await call('mod_studymark_create_highlight', {
                        cmid: config.cmid, documentid: documentRecord.id, groupid: 0, pagenumber: 0,
                        note: '', headingcontext: '', shared: false, totalchars: readingArticle.textContent.length, ...data
                    });
                    highlights.push(saved); highlighter.apply([saved]);
                });
                highlighter.apply(highlights);
            }
            renderOutline(); summaryButton.disabled = false; renderMode();
        }

        try {
            const documents = await call('mod_studymark_get_documents', {cmid: config.cmid});
            documentRecord = documents[0] || null;
            if (!documentRecord) { viewer.innerHTML = '<div class="studymark-empty"><p>Esta actividad todavía no tiene documento.</p></div>'; return; }
            root.querySelector('[data-action=original]').href = documentRecord.url;
            await openDocument();
        } catch (error) {
            viewer.innerHTML = '<div class="alert alert-danger m-3">No se pudo procesar el documento.</div>';
            Notification.exception(error);
        }

        root.addEventListener('click', async event => {
            const action = event.target.closest('[data-action]')?.dataset.action;
            if (action === 'toggle-focus') { root.classList.toggle('studymark-focus'); return; }
            if (action === 'summary') { summaryMode = !summaryMode; renderMode(); return; }
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
            const id = +mark.dataset.highlightId;
            const record = highlights.find(item => +item.id === id);
            if (!record?.canedit || !window.confirm('¿Eliminar este subrayado?')) return;
            await call('mod_studymark_delete_highlight', {cmid: config.cmid, highlightid: id});
            readingArticle.querySelectorAll(`[data-highlight-id="${id}"]`).forEach(savedMark => {
                const parent = savedMark.parentNode;
                while (savedMark.firstChild) parent.insertBefore(savedMark.firstChild, savedMark);
                savedMark.remove(); parent.normalize();
            });
            highlights = highlights.filter(item => +item.id !== id); renderMode();
        });
    }
    return {init};
});
