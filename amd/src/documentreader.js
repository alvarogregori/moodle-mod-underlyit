// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Converts PDF, EPUB, DOCX, HTML and text documents into a normalised reading view.
 *
 * The text of the reading view is what highlight offsets refer to, so changes to
 * the extraction must keep producing the same text for the same document.
 *
 * @module     mod_underlyit/documentreader
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {
    /** Image MIME types by file extension. */
    const IMAGE_TYPES = {
        png: 'image/png',
        jpg: 'image/jpeg',
        jpeg: 'image/jpeg',
        gif: 'image/gif',
        webp: 'image/webp',
        svg: 'image/svg+xml',
        bmp: 'image/bmp',
    };

    /**
     * Collapses white space.
     *
     * @param {string} text Text.
     * @returns {string}
     */
    const clean = text => (text || '').replace(/\s+/g, ' ').trim();

    /**
     * Elements of a given local name, ignoring XML namespaces.
     *
     * @param {Element|Document} node Root node.
     * @param {string} name Local name.
     * @returns {Element[]}
     */
    const local = (node, name) => Array.from(node.getElementsByTagName('*')).filter(el => el.localName === name);

    /**
     * Resolves a relative path inside an archive.
     *
     * @param {string} base Path of the referring file.
     * @param {string} relative Relative path.
     * @returns {string}
     */
    const resolvePath = (base, relative) => {
        const parts = (base ? base.split('/').slice(0, -1) : []).concat((relative || '').split('/'));
        const output = [];
        parts.forEach(part => {
            if (part === '..') {
                output.pop();
            } else if (part && part !== '.') {
                output.push(part);
            }
        });
        return output.join('/');
    };

    /**
     * MIME type of an image path.
     *
     * @param {string} path File path.
     * @returns {string}
     */
    const imageMime = path => IMAGE_TYPES[(path.split('.').pop() || '').toLowerCase()] || 'image/png';

    /**
     * Tag of a PDF line from its font size relative to the body text.
     *
     * @param {number} size Font size of the line.
     * @param {number} bodySize Most common font size.
     * @returns {string}
     */
    const pdfLineTag = (size, bodySize) => {
        if (size >= bodySize * 1.65) {
            return 'h1';
        }
        if (size >= bodySize * 1.38) {
            return 'h2';
        }
        if (size >= bodySize * 1.18) {
            return 'h3';
        }
        return 'p';
    };

    /**
     * Extracts a document and renders the reading view.
     */
    class DocumentReader {
        /**
         * Constructor.
         *
         * @param {Object} options url, filename, mimetype, workerUrl, strings and a progress callback.
         */
        constructor(options) {
            this.options = options;
            this.objectUrls = [];
        }

        /**
         * Downloads the document and renders it.
         *
         * @returns {Promise<HTMLElement>} The reading view.
         */
        async load() {
            const {strings, mimetype, filename} = this.options;
            this.options.progress(strings.extractingdocument);
            const response = await fetch(this.options.url, {credentials: 'same-origin'});
            if (!response.ok) {
                throw new Error(strings.downloaddocumenterror);
            }
            const buffer = await response.arrayBuffer();
            let blocks;
            if (/pdf/i.test(mimetype)) {
                blocks = await this.fromPdf(buffer);
            } else if (/epub/i.test(mimetype) || /\.epub$/i.test(filename)) {
                blocks = await this.fromEpub(buffer);
            } else if (/wordprocessingml|docx/i.test(mimetype) || /\.docx$/i.test(filename)) {
                blocks = await this.fromDocx(buffer);
            } else if (/html/i.test(mimetype) || /\.html?$/i.test(filename)) {
                blocks = this.fromHtml(buffer);
            } else {
                blocks = this.fromText(buffer);
            }
            return this.render(blocks.filter(block => block.text || block.src));
        }

        /**
         * Creates an object URL that is kept for later release.
         *
         * @param {Blob} blob Image data.
         * @returns {string}
         */
        objectUrl(blob) {
            const url = URL.createObjectURL(blob);
            this.objectUrls.push(url);
            return url;
        }

        /**
         * Accepts only base64 image data or http(s) image URLs.
         *
         * @param {string} source Image source.
         * @param {string} base Base URL for relative sources.
         * @returns {string} The safe source, or an empty string.
         */
        safeImageSource(source, base = this.options.url) {
            if (!source) {
                return '';
            }
            if (/^data:image\/(?:png|jpe?g|gif|webp|svg\+xml|bmp);base64,/i.test(source)) {
                return source;
            }
            try {
                const url = new URL(source, base);
                return /^(https?:)$/i.test(url.protocol) ? url.href : '';
            } catch (error) {
                return '';
            }
        }

        /**
         * Block of an illustration.
         *
         * @param {string} source Image source.
         * @param {string} caption Caption or alternative text.
         * @param {number} page Page number, 0 if unknown.
         * @returns {Object}
         */
        imageBlock(source, caption = '', page = 0) {
            return {tag: 'figure', src: source, caption: clean(caption), page};
        }

        /**
         * Blocks of an HTML document.
         *
         * @param {ArrayBuffer} buffer File content.
         * @returns {Object[]}
         */
        fromHtml(buffer) {
            const doc = new DOMParser().parseFromString(new TextDecoder().decode(buffer), 'text/html');
            doc.querySelectorAll('script,style,noscript,template,iframe,object,embed').forEach(node => node.remove());
            const blocks = [];
            Array.from(doc.body.querySelectorAll('h1,h2,h3,h4,h5,h6,p,li,blockquote,img')).forEach(node => {
                if (node.tagName.toLowerCase() === 'img') {
                    const source = this.safeImageSource(node.getAttribute('src'));
                    if (source) {
                        blocks.push(this.imageBlock(source, node.getAttribute('alt') || node.getAttribute('title') || ''));
                    }
                    return;
                }
                const text = clean(node.textContent);
                if (text) {
                    blocks.push({tag: node.tagName.toLowerCase(), text, page: 0});
                }
            });
            return blocks.length ? blocks : [{tag: 'p', text: clean(doc.body.textContent), page: 0}];
        }

        /**
         * Blocks of a plain-text document: one paragraph per blank-line-separated chunk.
         *
         * @param {ArrayBuffer} buffer File content.
         * @returns {Object[]}
         */
        fromText(buffer) {
            const text = new TextDecoder().decode(buffer).replace(/\r\n?/g, '\n').trim();
            return text.split(/\n\s*\n+/)
                .map(paragraph => ({tag: 'p', text: clean(paragraph.replace(/\n/g, ' ')), page: 0}))
                .filter(block => block.text);
        }

        /**
         * Converts a PDF.js image object into an illustration block.
         *
         * @param {Object} image PDF.js image object.
         * @param {number} pageNumber Page number.
         * @param {string} caption Caption of the illustration.
         * @returns {Object|null}
         */
        pdfImageData(image, pageNumber, caption) {
            const width = image?.width || image?.bitmap?.width || 0;
            const height = image?.height || image?.bitmap?.height || 0;
            if (width < 80 || height < 60) {
                return null;
            }
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d');
            try {
                if (image.bitmap || image instanceof HTMLImageElement || image instanceof HTMLCanvasElement) {
                    context.drawImage(image.bitmap || image, 0, 0, width, height);
                } else if (image.data) {
                    const source = image.data;
                    const rgba = new Uint8ClampedArray(width * height * 4);
                    if (source.length === width * height * 4) {
                        rgba.set(source);
                    } else {
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
                } else {
                    return null;
                }
                return this.imageBlock(canvas.toDataURL('image/png'), caption, pageNumber);
            } catch (error) {
                return null;
            }
        }

        /**
         * Illustrations painted on a PDF page.
         *
         * @param {Object} page PDF.js page.
         * @param {number} pageNumber Page number.
         * @returns {Promise<Object[]>}
         */
        async pdfImages(page, pageNumber) {
            const operators = await page.getOperatorList();
            const ids = [];
            operators.fnArray.forEach((operation, index) => {
                if (operation === window.pdfjsLib.OPS.paintImageXObject) {
                    ids.push(operators.argsArray[index][0]);
                }
            });
            const images = [];
            const caption = ids.length ? await Str.get_string('illustrationpage', 'mod_underlyit', pageNumber) : '';
            for (const id of new Set(ids)) {
                const image = await new Promise(resolve => {
                    try {
                        page.objs.get(id, resolve);
                    } catch (error) {
                        resolve(null);
                    }
                });
                const block = this.pdfImageData(image, pageNumber, caption);
                if (block) {
                    images.push(block);
                }
            }
            return images;
        }

        /**
         * Blocks of a PDF document: lines grouped into paragraphs, headings guessed from font size.
         *
         * @param {ArrayBuffer} buffer File content.
         * @returns {Promise<Object[]>}
         */
        async fromPdf(buffer) {
            const strings = this.options.strings;
            if (!window.pdfjsLib) {
                throw new Error(strings.pdfjsunavailable);
            }
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = this.options.workerUrl;
            const pdf = await window.pdfjsLib.getDocument({data: buffer, isEvalSupported: false}).promise;
            const lines = [];
            const imageBlocks = [];
            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                this.options.progress(await Str.get_string('extractingpage', 'mod_underlyit',
                    {page: pageNumber, total: pdf.numPages}));
                const page = await pdf.getPage(pageNumber);
                const content = await page.getTextContent();
                const pageLines = [];
                content.items.forEach(item => {
                    const text = clean(item.str);
                    if (!text) {
                        return;
                    }
                    const x = item.transform[4];
                    const y = item.transform[5];
                    const size = Math.max(Math.abs(item.transform[0]), Math.abs(item.transform[3]), item.height || 0);
                    let line = pageLines.find(candidate => Math.abs(candidate.y - y) < Math.max(2, size * 0.25));
                    if (!line) {
                        line = {page: pageNumber, y, size, items: []};
                        pageLines.push(line);
                    }
                    line.items.push({x, text});
                    line.size = Math.max(line.size, size);
                });
                pageLines.sort((a, b) => b.y - a.y).forEach(line => {
                    line.text = clean(line.items.sort((a, b) => a.x - b.x).map(item => item.text).join(' '));
                    lines.push(line);
                });
                imageBlocks.push(...await this.pdfImages(page, pageNumber));
            }

            const counts = {};
            lines.map(line => Math.round(line.size * 2) / 2).filter(Boolean).forEach(size => {
                counts[size] = (counts[size] || 0) + 1;
            });
            const bodySize = +(Object.keys(counts).sort((a, b) => counts[b] - counts[a])[0] || 12);

            const blocks = [];
            lines.forEach(line => {
                const tag = pdfLineTag(line.size, bodySize);
                const previous = blocks[blocks.length - 1];
                const isContinuation = tag === 'p' && previous?.tag === 'p' && previous.page === line.page &&
                    previous.lastY - line.y < Math.max(bodySize * 2, line.size * 2);
                if (isContinuation) {
                    previous.text = clean(previous.text + ' ' + line.text);
                    previous.lastY = line.y;
                } else {
                    blocks.push({tag, text: line.text, page: line.page, lastY: line.y});
                }
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

        /**
         * Blocks of an EPUB document, following the spine order.
         *
         * @param {ArrayBuffer} buffer File content.
         * @returns {Promise<Object[]>}
         */
        async fromEpub(buffer) {
            const strings = this.options.strings;
            if (!window.JSZip) {
                throw new Error(strings.jszipunavailable);
            }
            const zip = await window.JSZip.loadAsync(buffer);
            const parser = new DOMParser();
            const containerFile = zip.file('META-INF/container.xml');
            if (!containerFile) {
                throw new Error(strings.invalidepub);
            }
            const container = parser.parseFromString(await containerFile.async('text'), 'application/xml');
            const rootfile = local(container, 'rootfile')[0]?.getAttribute('full-path');
            if (!rootfile || !zip.file(rootfile)) {
                throw new Error(strings.invalidepub);
            }
            const opf = parser.parseFromString(await zip.file(rootfile).async('text'), 'application/xml');
            const manifest = {};
            local(opf, 'item').forEach(item => {
                manifest[item.getAttribute('id')] = {
                    href: item.getAttribute('href'),
                    media: item.getAttribute('media-type') || '',
                };
            });
            const blocks = [];
            for (const ref of local(opf, 'itemref')) {
                const entry = manifest[ref.getAttribute('idref')];
                const path = resolvePath(rootfile, entry?.href || '');
                if (!entry?.href || !zip.file(path)) {
                    continue;
                }
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
                        if (text) {
                            blocks.push({tag: node.tagName.toLowerCase(), text, page: 0});
                        }
                    }
                }
            }
            return blocks;
        }

        /**
         * Blocks of a DOCX document: paragraphs, headings by style, list items and embedded images.
         *
         * @param {ArrayBuffer} buffer File content.
         * @returns {Promise<Object[]>}
         */
        async fromDocx(buffer) {
            const strings = this.options.strings;
            if (!window.JSZip) {
                throw new Error(strings.jszipunavailable);
            }
            const zip = await window.JSZip.loadAsync(buffer);
            const file = zip.file('word/document.xml');
            if (!file) {
                throw new Error(strings.invaliddocx);
            }
            const parser = new DOMParser();
            const xml = parser.parseFromString(await file.async('text'), 'application/xml');
            const relationships = {};
            const relationshipFile = zip.file('word/_rels/document.xml.rels');
            if (relationshipFile) {
                const relationXml = parser.parseFromString(await relationshipFile.async('text'), 'application/xml');
                local(relationXml, 'Relationship').forEach(node => {
                    relationships[node.getAttribute('Id')] = node.getAttribute('Target');
                });
            }
            const blocks = [];
            for (const paragraph of local(xml, 'p')) {
                const text = clean(local(paragraph, 't').map(node => node.textContent).join(''));
                const styleNode = local(paragraph, 'pStyle')[0];
                const style = styleNode?.getAttribute('w:val') || styleNode?.getAttribute('val') || '';
                const heading = style.match(/(?:Heading|Título|Titre)([1-6])/i);
                if (text) {
                    let tag = 'p';
                    if (heading) {
                        tag = `h${heading[1]}`;
                    } else if (local(paragraph, 'numPr').length > 0) {
                        tag = 'li';
                    }
                    blocks.push({tag, text, page: 0});
                }
                for (const drawing of local(paragraph, 'drawing')) {
                    const blip = local(drawing, 'blip')[0];
                    const relationId = blip?.getAttribute('r:embed') || blip?.getAttribute('embed');
                    const target = relationships[relationId];
                    const imagePath = target ? resolvePath('word/document.xml', target) : '';
                    const imageFile = imagePath ? zip.file(imagePath) : null;
                    if (!imageFile) {
                        continue;
                    }
                    const props = local(drawing, 'docPr')[0];
                    const caption = props?.getAttribute('descr') || props?.getAttribute('title') ||
                        props?.getAttribute('name') || '';
                    const blob = await imageFile.async('blob');
                    blocks.push(this.imageBlock(this.objectUrl(new Blob([blob], {type: imageMime(imagePath)})), caption));
                }
            }
            return blocks;
        }

        /**
         * Renders the blocks as the reading view.
         *
         * @param {Object[]} blocks Text and illustration blocks.
         * @returns {HTMLElement}
         */
        render(blocks) {
            const article = document.createElement('article');
            article.className = 'underlyit-reading-document';
            blocks.forEach((block, index) => {
                let element;
                if (block.tag === 'figure' && block.src) {
                    element = document.createElement('figure');
                    element.className = 'underlyit-illustration';
                    element.dataset.caption = block.caption || '';
                    element.dataset.underlyitNontext = 'true';
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
                if (block.page) {
                    element.dataset.page = block.page;
                }
                article.append(element);
            });
            return article;
        }
    }

    return DocumentReader;
});
