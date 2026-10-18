define([], function() {
    class PdfViewer {
        constructor(container, options) {
            this.container = container;
            this.options = options;
            this.page = 1;
            this.pages = 0;
            this.scale = 1.2;
            this.pdf = null;
            this.mouseup = this.onSelection.bind(this);
        }

        async init() {
            if (!window.pdfjsLib) throw new Error('PDF.js no está disponible');
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = this.options.workerUrl;
            this.container.innerHTML = '<div class="studymark-pdfjs-toolbar" role="toolbar" aria-label="Navegación PDF">' +
                '<button class="btn btn-secondary btn-sm" data-pdfjs="prev">Anterior</button>' +
                '<span data-pdfjs-status></span>' +
                '<button class="btn btn-secondary btn-sm" data-pdfjs="next">Siguiente</button>' +
                '<button class="btn btn-secondary btn-sm" data-pdfjs="out" aria-label="Reducir">−</button>' +
                '<span data-pdfjs-zoom></span>' +
                '<button class="btn btn-secondary btn-sm" data-pdfjs="in" aria-label="Ampliar">+</button>' +
                '</div><div class="studymark-pdfjs-scroll"><div class="studymark-pdfjs-page"></div></div>';
            this.container.addEventListener('click', event => this.onControl(event));
            this.container.addEventListener('mouseup', this.mouseup);
            this.pdf = await window.pdfjsLib.getDocument({url: this.options.url}).promise;
            this.pages = this.pdf.numPages;
            await this.render();
        }

        async onControl(event) {
            const action = event.target.dataset.pdfjs;
            if (!action) return;
            if (action === 'prev' && this.page > 1) this.page--;
            if (action === 'next' && this.page < this.pages) this.page++;
            if (action === 'in') this.scale = Math.min(3, +(this.scale + .2).toFixed(1));
            if (action === 'out') this.scale = Math.max(.6, +(this.scale - .2).toFixed(1));
            await this.render();
        }

        async render() {
            const host = this.container.querySelector('.studymark-pdfjs-page');
            host.innerHTML = '<div class="studymark-pdfjs-loading">Cargando página…</div>';
            const page = await this.pdf.getPage(this.page);
            const viewport = page.getViewport({scale: this.scale});
            const canvas = document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            const layer = document.createElement('div');
            layer.className = 'textLayer studymark-pdfjs-textlayer';
            layer.style.setProperty('--scale-factor', this.scale);
            const wrapper = document.createElement('div');
            wrapper.className = 'studymark-pdfjs-sheet';
            wrapper.style.width = viewport.width + 'px';
            wrapper.style.height = viewport.height + 'px';
            wrapper.append(canvas, layer);
            host.replaceChildren(wrapper);
            await page.render({canvasContext: canvas.getContext('2d'), viewport}).promise;
            const content = await page.getTextContent();
            await window.pdfjsLib.renderTextLayer({textContentSource: content, container: layer, viewport, textDivs: []}).promise;
            this.container.querySelector('[data-pdfjs-status]').textContent = `Página ${this.page} / ${this.pages}`;
            this.container.querySelector('[data-pdfjs-zoom]').textContent = `${Math.round(this.scale * 100)}%`;
            this.applyHighlights();
        }

        async onSelection() {
            if (!this.options.canHighlight) return;
            const selection = window.getSelection();
            if (!selection || selection.isCollapsed || !selection.rangeCount) return;
            const range = selection.getRangeAt(0);
            const layer = this.container.querySelector('.studymark-pdfjs-textlayer');
            if (!layer || !layer.contains(range.commonAncestorContainer)) return;
            const text = selection.toString().trim();
            if (!text) return;
            const base = layer.getBoundingClientRect();
            const rects = Array.from(range.getClientRects()).filter(r => r.width > 0 && r.height > 0).map(r => ({
                l: (r.left - base.left) / this.scale, t: (r.top - base.top) / this.scale,
                w: r.width / this.scale, h: r.height / this.scale
            }));
            selection.removeAllRanges();
            if (!rects.length) return;
            const temporary = {id: 'saving', color: '#fff176', selectorjson: JSON.stringify({type: 'pdf-overlay', rects})};
            this.draw(temporary);
            try {
                const saved = await this.options.onSave({selectedtext: text, selectorjson: temporary.selectorjson,
                    pagenumber: this.page, color: temporary.color, note: '', headingcontext: '',
                    positionorder: rects[0].t, shared: false});
                layer.querySelectorAll('[data-highlight-id="saving"]').forEach(el => el.dataset.highlightId = saved.id);
            } catch (error) {
                layer.querySelectorAll('[data-highlight-id="saving"]').forEach(el => el.remove());
                throw error;
            }
        }

        applyHighlights() {
            this.options.highlights.filter(item => (+item.pagenumber || 1) === this.page).forEach(item => this.draw(item));
        }

        draw(item) {
            let data;
            try { data = JSON.parse(item.selectorjson || '{}'); } catch (error) { return; }
            if (data.type !== 'pdf-overlay' || !Array.isArray(data.rects)) return;
            const layer = this.container.querySelector('.studymark-pdfjs-textlayer');
            data.rects.forEach(rect => {
                const mark = document.createElement('span');
                mark.className = 'studymark-pdf-highlight';
                mark.dataset.highlightId = item.id;
                mark.style.cssText = `left:${rect.l*this.scale}px;top:${rect.t*this.scale}px;width:${rect.w*this.scale}px;height:${rect.h*this.scale}px;background:${item.color}`;
                layer.append(mark);
            });
        }

        destroy() { this.container.removeEventListener('mouseup', this.mouseup); }
    }
    return PdfViewer;
});
