/**
 * StudyMark documentreader module.
 *
 * @module     mod_studymark/documentreader
 * @copyright  2026 Alvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    const clean = text => (text || '').replace(/\s+/g, ' ').trim();
    const local = (node, name) => Array.from(node.getElementsByTagName('*')).filter(el => el.localName === name);
    const resolvePath = (base, relative) => {
        const parts = (base ? base.split('/').slice(0, -1) : []).concat((relative || '').split('/'));
        const output = [];
        parts.forEach(part => { if (part === '..') output.pop(); else if (part && part !== '.') output.push(part); });
        return output.join('/');
    };
    const imageMime = path => ({png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif',
        webp: 'image/webp', svg: 'image/svg+xml', bmp: 'image/bmp'})[(path.split('.').pop() || '').toLowerCase()] || 'image/png';

    class DocumentReader {
        constructor(options) { this.options = options; this.objectUrls = []; }

        async load() {
            this.options.progress(this.options.strings.extractingdocument);
            const response = await fetch(this.options.url, {credentials: 'same-origin'});
            if (!response.ok) throw new Error(this.options.strings.downloaddocumenterror);
            const buffer = await response.arrayBuffer();
            let blocks;
            if (/pdf/i.test(this.options.mimetype)) blocks = await this.fromPdf(buffer);
            else if (/epub/i.test(this.options.mimetype) || /\.epub$/i.test(this.options.filename)) blocks = await this.fromEpub(buffer);
            else if (/wordprocessingml|docx/i.test(this.options.mimetype) || /\.docx$/i.test(this.options.filename)) blocks = await this.fromDocx(buffer);
            else if (/html/i.test(this.options.mimetype) || /\.html?$/i.test(this.options.filename)) blocks = this.fromHtml(buffer);
            else blocks = this.fromText(buffer);
            return this.render(blocks.filter(block => block.text || block.src));
        }

        objectUrl(blob) {
            const url = URL.createObjectURL(blob);
            this.objectUrls.push(url);
            return url;
        }

        safeImageSource(source, base = this.options.url) {
            if (!source) return '';
            if (/^data:image\/(?:png|jpe?g|gif|webp|svg\+xml|bmp);base64,/i.test(source)) return source;
            try {
                const url = new URL(source, base);
                return /^(https?:)$/i.test(url.protocol) ? url.href : '';
            } catch (error) { return ''; }
        }

        imageBlock(source, caption = '', page = 0) {
            return {tag: 'figure', src: source, caption: clean(caption), page};
        }

        fromHtml(buffer) {
            const doc = new DOMParser().parseFromString(new TextDecoder().decode(buffer), 'text/html');
            doc.querySelectorAll('script,style,noscript,template,iframe,object,embed').forEach(node => node.remove());
            const blocks = [];
            Array.from(doc.body.querySelectorAll('h1,h2,h3,h4,h5,h6,p,li,blockquote,img')).forEach(node => {
                if (node.tagName.toLowerCase() === 'img') {
                    const source = this.safeImageSource(node.getAttribute('src'));
                    if (source) blocks.push(this.imageBlock(source, node.getAttribute('alt') || node.getAttribute('title') || ''));
                    return;
                }
                const text = clean(node.textContent);
                if (text) blocks.push({tag: node.tagName.toLowerCase(), text, page: 0});
            });
            return blocks.length ? blocks : [{tag: 'p', text: clean(doc.body.textContent), page: 0}];
        }

        fromText(buffer) {
            const text = new TextDecoder().decode(buffer).replace(/\r\n?/g, '\n').trim();
            return text.split(/\n\s*\n+/).map(paragraph => ({
                tag: 'p', text: clean(paragraph.replace(/\n/g, ' ')), page: 0
            })).filter(block => block.text);
        }

        pdfImageData(image, pageNumber) {
            const width = image?.width || image?.bitmap?.width || 0;
            const height = image?.height || image?.bitmap?.height || 0;
            if (width < 80 || height < 60) return null;
            const canvas = document.createElement('canvas');
            canvas.width = width; canvas.height = height;
            const context = canvas.getContext('2d');
            try {
                if (image.bitmap || image instanceof HTMLImageElement || image instanceof HTMLCanvasElement) {
                    context.drawImage(image.bitmap || image, 0, 0, width, height);
                } else if (image.data) {
                    const source = image.data;
                    const rgba = new Uint8ClampedArray(width * height * 4);
                    if (source.length === width * height * 4) rgba.set(source);
                    else {
                        const channels = source.length >= width * height * 3 ? 3 : 1;
                        for (let pixel = 0; pixel < width * height; pixel++) {
                            const offset = pixel * 4;
                            rgba[offset] = source[pixel * channels];
                            rgba[offset + 1] = channels > 1 ? source[pixel * channels + 1] : source[pixel];
                            rgba[offset + 2] = channels > 1 ? source[pixel * channels + 2] : source[pixel];
                            rgba[offset + 3] = 255;
                        }
                    }
                    context.putImageData(new ImageData(rgba, width, height), 0, 0);
                } else return null;
                return this.imageBlock(canvas.toDataURL('image/png'), `${this.options.strings.illustrationpage} ${pageNumber}`, pageNumber);
            } catch (error) { return null; }
        }

        async pdfImages(page, pageNumber) {
            const operators = await page.getOperatorList();
            const ids = [];
            operators.fnArray.forEach((operation, index) => {
                if (operation === window.pdfjsLib.OPS.paintImageXObject) ids.push(operators.argsArray[index][0]);
            });
            const unique = [...new Set(ids)];
            const images = [];
            for (const id of unique) {
                const image = await new Promise(resolve => {
                    try { page.objs.get(id, resolve); } catch (error) { resolve(null); }
                });
                const block = this.pdfImageData(image, pageNumber);
                if (block) images.push(block);
            }
            return images;
        }

        async fromPdf(buffer) {
            if (!window.pdfjsLib) throw new Error(this.options.strings.pdfjsunavailable);
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = this.options.workerUrl;
            const pdf = await window.pdfjsLib.getDocument({data: buffer}).promise;
            const lines = [];
            const imageBlocks = [];
            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                this.options.progress(`${this.options.strings.extractingpage} ${pageNumber} ${this.options.strings.of} ${pdf.numPages}...`);
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
                imageBlocks.push(...await this.pdfImages(page, pageNumber));
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
            imageBlocks.forEach(image => {
                let index = -1;
                for (let candidate = blocks.length - 1; candidate >= 0; candidate--) {
                    if (Number(blocks[candidate].page) === Number(image.page)) {
                        index = candidate;
                        break;
                    }
                }
                if (index < 0) {
                    index = blocks.findIndex(block => Number(block.page) > Number(image.page));
                    blocks.splice(index < 0 ? blocks.length : index, 0, image);
                } else {
                    blocks.splice(index + 1, 0, image);
                }
            });
            return blocks;
        }

        async fromEpub(buffer) {
            if (!window.JSZip) throw new Error(this.options.strings.jszipunavailable);
            const zip = await window.JSZip.loadAsync(buffer);
            const parser = new DOMParser();
            const containerFile = zip.file('META-INF/container.xml');
            if (!containerFile) throw new Error(this.options.strings.invalidepub);
            const container = parser.parseFromString(await containerFile.async('text'), 'application/xml');
            const rootfile = local(container, 'rootfile')[0]?.getAttribute('full-path');
            if (!rootfile || !zip.file(rootfile)) throw new Error(this.options.strings.invalidepub);
            const opf = parser.parseFromString(await zip.file(rootfile).async('text'), 'application/xml');
            const manifest = {};
            local(opf, 'item').forEach(item => manifest[item.getAttribute('id')] = {
                href: item.getAttribute('href'), media: item.getAttribute('media-type') || ''
            });
            const blocks = [];
            for (const ref of local(opf, 'itemref')) {
                const entry = manifest[ref.getAttribute('idref')];
                const path = resolvePath(rootfile, entry?.href || '');
                if (!entry?.href || !zip.file(path)) continue;
                const doc = parser.parseFromString(await zip.file(path).async('text'), 'application/xhtml+xml');
                for (const node of Array.from(doc.querySelectorAll('h1,h2,h3,h4,h5,h6,p,li,blockquote,img'))) {
                    if (node.localName === 'img') {
                        const imagePath = resolvePath(path, node.getAttribute('src') || '');
                        const imageFile = zip.file(imagePath);
                        if (imageFile) {
                            const blob = await imageFile.async('blob');
                            blocks.push(this.imageBlock(this.objectUrl(new Blob([blob], {type: imageMime(imagePath)})),
                                node.getAttribute('alt') || node.getAttribute('title') || ''));
                        }
                    } else {
                        const text = clean(node.textContent);
                        if (text) blocks.push({tag: node.tagName.toLowerCase(), text, page: 0});
                    }
                }
            }
            return blocks;
        }

        async fromDocx(buffer) {
            if (!window.JSZip) throw new Error(this.options.strings.jszipunavailable);
            const zip = await window.JSZip.loadAsync(buffer);
            const file = zip.file('word/document.xml');
            if (!file) throw new Error(this.options.strings.invaliddocx);
            const parser = new DOMParser();
            const xml = parser.parseFromString(await file.async('text'), 'application/xml');
            const relationships = {};
            const relationshipFile = zip.file('word/_rels/document.xml.rels');
            if (relationshipFile) {
                const relationXml = parser.parseFromString(await relationshipFile.async('text'), 'application/xml');
                Array.from(relationXml.getElementsByTagName('*')).filter(node => node.localName === 'Relationship').forEach(node => {
                    relationships[node.getAttribute('Id')] = node.getAttribute('Target');
                });
            }
            const blocks = [];
            for (const paragraph of local(xml, 'p')) {
                const text = clean(local(paragraph, 't').map(node => node.textContent).join(''));
                const styleNode = local(paragraph, 'pStyle')[0];
                const style = styleNode?.getAttribute('w:val') || styleNode?.getAttribute('val') || '';
                const heading = style.match(/(?:Heading|T\u00edtulo|Titre)([1-6])/i);
                const isList = local(paragraph, 'numPr').length > 0;
                if (text) blocks.push({tag: heading ? `h${heading[1]}` : (isList ? 'li' : 'p'), text, page: 0});
                for (const drawing of local(paragraph, 'drawing')) {
                    const blip = local(drawing, 'blip')[0];
                    const relationId = blip?.getAttribute('r:embed') || blip?.getAttribute('embed');
                    const target = relationships[relationId];
                    const imagePath = target ? resolvePath('word/document.xml', target) : '';
                    const imageFile = imagePath ? zip.file(imagePath) : null;
                    if (!imageFile) continue;
                    const props = local(drawing, 'docPr')[0];
                    const caption = props?.getAttribute('descr') || props?.getAttribute('title') || props?.getAttribute('name') || '';
                    const blob = await imageFile.async('blob');
                    blocks.push(this.imageBlock(this.objectUrl(new Blob([blob], {type: imageMime(imagePath)})), caption));
                }
            }
            return blocks;
        }

        render(blocks) {
            const article = document.createElement('article');
            article.className = 'studymark-reading-document';
            blocks.forEach((block, index) => {
                let element;
                if (block.tag === 'figure' && block.src) {
                    element = document.createElement('figure');
                    element.className = 'studymark-illustration';
                    element.dataset.caption = block.caption || '';
                    element.dataset.studymarkNontext = 'true';
                    const image = document.createElement('img');
                    image.src = block.src;
                    image.alt = block.caption || this.options.strings.illustration;
                    image.loading = 'lazy';
                    image.decoding = 'async';
                    element.append(image);
                } else {
                    const allowed = /^(h[1-6]|p|li|blockquote)$/.test(block.tag) ? block.tag : 'p';
                    element = document.createElement(allowed);
                    element.textContent = block.text;
                }
                element.dataset.blockId = `block-${index}`;
                if (block.page) element.dataset.page = block.page;
                article.append(element);
            });
            return article;
        }
    }
    return DocumentReader;
});
