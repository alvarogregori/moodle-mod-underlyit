define([], function() {
    const clean = text => (text || '').replace(/\s+/g, ' ').trim();
    const local = (node, name) => Array.from(node.getElementsByTagName('*')).filter(el => el.localName === name);
    const resolvePath = (base, relative) => {
        const parts = (base ? base.split('/').slice(0, -1) : []).concat(relative.split('/'));
        const output = [];
        parts.forEach(part => { if (part === '..') output.pop(); else if (part && part !== '.') output.push(part); });
        return output.join('/');
    };

    class DocumentReader {
        constructor(options) { this.options = options; }

        async load() {
            this.options.progress('Extrayendo el contenido del documento…');
            const response = await fetch(this.options.url, {credentials: 'same-origin'});
            if (!response.ok) throw new Error('No se pudo descargar el documento');
            const buffer = await response.arrayBuffer();
            let blocks;
            if (/pdf/i.test(this.options.mimetype)) blocks = await this.fromPdf(buffer);
            else if (/epub/i.test(this.options.mimetype) || /\.epub$/i.test(this.options.filename)) blocks = await this.fromEpub(buffer);
            else if (/wordprocessingml|docx/i.test(this.options.mimetype) || /\.docx$/i.test(this.options.filename)) blocks = await this.fromDocx(buffer);
            else blocks = [{tag: 'p', text: clean(new TextDecoder().decode(buffer)), page: 0}];
            return this.render(blocks.filter(block => block.text));
        }

        async fromPdf(buffer) {
            if (!window.pdfjsLib) throw new Error('PDF.js no está disponible');
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = this.options.workerUrl;
            const pdf = await window.pdfjsLib.getDocument({data: buffer}).promise;
            const lines = [];
            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                this.options.progress(`Extrayendo página ${pageNumber} de ${pdf.numPages}…`);
                const page = await pdf.getPage(pageNumber);
                const content = await page.getTextContent();
                const pageLines = [];
                content.items.forEach(item => {
                    const text = clean(item.str);
                    if (!text) return;
                    const x = item.transform[4], y = item.transform[5];
                    const size = Math.max(Math.abs(item.transform[0]), Math.abs(item.transform[3]), item.height || 0);
                    let line = pageLines.find(candidate => Math.abs(candidate.y - y) < Math.max(2, size * .25));
                    if (!line) { line = {page: pageNumber, y, size, items: []}; pageLines.push(line); }
                    line.items.push({x, text}); line.size = Math.max(line.size, size);
                });
                pageLines.sort((a, b) => b.y - a.y).forEach(line => {
                    line.text = clean(line.items.sort((a, b) => a.x - b.x).map(item => item.text).join(' '));
                    lines.push(line);
                });
            }
            const sizes = lines.map(line => Math.round(line.size * 2) / 2).filter(Boolean);
            const counts = sizes.reduce((all, size) => (all[size] = (all[size] || 0) + 1, all), {});
            const bodySize = +(Object.keys(counts).sort((a, b) => counts[b] - counts[a])[0] || 12);
            const tagged = lines.map(line => ({...line, tag: line.size >= bodySize * 1.65 ? 'h1' : line.size >= bodySize * 1.38 ? 'h2' : line.size >= bodySize * 1.18 ? 'h3' : 'p'}));
            const blocks = [];
            tagged.forEach(line => {
                const previous = blocks[blocks.length - 1];
                const isContinuation = line.tag === 'p' && previous?.tag === 'p' && previous.page === line.page &&
                    previous.lastY - line.y < Math.max(bodySize * 2, line.size * 2);
                if (isContinuation) { previous.text = clean(previous.text + ' ' + line.text); previous.lastY = line.y; }
                else blocks.push({tag: line.tag, text: line.text, page: line.page, lastY: line.y});
            });
            return blocks;
        }

        async fromEpub(buffer) {
            if (!window.JSZip) throw new Error('JSZip no está disponible');
            const zip = await window.JSZip.loadAsync(buffer);
            const parser = new DOMParser();
            const container = parser.parseFromString(await zip.file('META-INF/container.xml').async('text'), 'application/xml');
            const rootfile = local(container, 'rootfile')[0]?.getAttribute('full-path');
            if (!rootfile || !zip.file(rootfile)) throw new Error('EPUB sin paquete OPF válido');
            const opf = parser.parseFromString(await zip.file(rootfile).async('text'), 'application/xml');
            const manifest = {};
            local(opf, 'item').forEach(item => manifest[item.getAttribute('id')] = item.getAttribute('href'));
            const blocks = [];
            for (const ref of local(opf, 'itemref')) {
                const href = manifest[ref.getAttribute('idref')];
                const path = resolvePath(rootfile, href || '');
                if (!href || !zip.file(path)) continue;
                const doc = parser.parseFromString(await zip.file(path).async('text'), 'application/xhtml+xml');
                Array.from(doc.querySelectorAll('h1,h2,h3,h4,h5,h6,p,li,blockquote')).forEach(node => {
                    const text = clean(node.textContent); if (text) blocks.push({tag: node.tagName.toLowerCase(), text, page: 0});
                });
            }
            return blocks;
        }

        async fromDocx(buffer) {
            if (!window.JSZip) throw new Error('JSZip no está disponible');
            const zip = await window.JSZip.loadAsync(buffer);
            const file = zip.file('word/document.xml');
            if (!file) throw new Error('DOCX sin document.xml');
            const xml = new DOMParser().parseFromString(await file.async('text'), 'application/xml');
            return local(xml, 'p').map(paragraph => {
                const text = clean(local(paragraph, 't').map(node => node.textContent).join(''));
                const style = local(paragraph, 'pStyle')[0]?.getAttribute('w:val') || local(paragraph, 'pStyle')[0]?.getAttribute('val') || '';
                const heading = style.match(/(?:Heading|Título|Titre)([1-6])/i);
                const isList = local(paragraph, 'numPr').length > 0;
                return {tag: heading ? `h${heading[1]}` : (isList ? 'li' : 'p'), text, page: 0};
            });
        }

        render(blocks) {
            const article = document.createElement('article');
            article.className = 'studymark-reading-document';
            blocks.forEach((block, index) => {
                const allowed = /^(h[1-6]|p|li|blockquote)$/.test(block.tag) ? block.tag : 'p';
                const element = document.createElement(allowed);
                element.textContent = block.text;
                element.dataset.blockId = `block-${index}`;
                if (block.page) element.dataset.page = block.page;
                article.append(element);
            });
            return article;
        }
    }
    return DocumentReader;
});
