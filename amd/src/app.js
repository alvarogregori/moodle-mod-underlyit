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
 * Reader of the Underly.it activity: renders the document, highlights and reading tools.
 *
 * The toolbar and layout come from the mod_underlyit/reader template rendered by view.php.
 *
 * @module     mod_underlyit/app
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/ajax',
    'core/notification',
    'core/str',
    'core/templates',
    'mod_underlyit/highlighter',
    'mod_underlyit/documentreader',
], function(Ajax, Notification, Str, Templates, Highlighter, DocumentReader) {
    /** Colour of tutor reference highlights; the CSS always draws them as a blue underline. */
    const REFERENCE_COLOR = '#90caf9';

    /** Strings of mod_underlyit used by the reader and the document reader. */
    const STRING_KEYS = [
        'containshighlights', 'deletehighlight', 'deletehighlightconfirm', 'documentprocesserror',
        'downloaddocumenterror', 'extractingdocument', 'fullreading', 'hidereference',
        'hidereferenceview', 'hideoutline', 'illustration', 'invaliddocx', 'invalidepub',
        'jszipunavailable', 'nodocument', 'nosummaryhighlights', 'pdfjsunavailable', 'referencecolorhint',
        'referencemode', 'referencemodeactive', 'selectedcolor', 'showoutline', 'showreference', 'summaryreading',
        'viewreference',
    ];

    /**
     * Calls one external function.
     *
     * @param {string} methodname Function name.
     * @param {Object} args Arguments.
     * @returns {Promise}
     */
    const call = (methodname, args) => Ajax.call([{methodname, args}])[0];

    /**
     * Loads the strings of the reader.
     *
     * @returns {Promise<Object>} Strings by key, plus "delete" from core.
     */
    const loadStrings = async() => {
        const requests = STRING_KEYS.map(key => ({key, component: 'mod_underlyit'}));
        requests.push({key: 'delete', component: 'core'});
        const values = await Str.get_strings(requests);
        return Object.fromEntries(requests.map((request, index) => [request.key, values[index]]));
    };

    /**
     * Replaces the content of an element with a single element of text.
     *
     * @param {HTMLElement} target Element to fill.
     * @param {string} tag Tag of the message element.
     * @param {string} className Class of the message element.
     * @param {string} text Message.
     */
    const showMessage = (target, tag, className, text) => {
        const message = document.createElement(tag);
        message.className = className;
        message.textContent = text;
        target.replaceChildren(message);
    };

    /**
     * Sets the pressed state and label of a toggle button.
     *
     * @param {HTMLElement} button Button.
     * @param {boolean} pressed Whether the button is pressed.
     * @param {string} label Accessible label and tooltip.
     */
    const setToggle = (button, pressed, label) => {
        button.classList.toggle('active', pressed);
        button.setAttribute('aria-pressed', String(pressed));
        button.setAttribute('aria-label', label);
        button.title = label;
    };

    /**
     * Whether a block has highlights before the next heading of the same or a higher level.
     *
     * @param {HTMLElement} heading Heading element.
     * @returns {boolean}
     */
    const sectionHasHighlights = heading => {
        const level = +heading.tagName.slice(1);
        let block = heading;
        while (block) {
            if (block !== heading && /^H[1-6]$/.test(block.tagName) && +block.tagName.slice(1) <= level) {
                return false;
            }
            if (block.querySelector('mark[data-highlight-id]')) {
                return true;
            }
            block = block.nextElementSibling;
        }
        return false;
    };

    /**
     * Copy of a block that keeps only its highlighted fragments.
     *
     * @param {HTMLElement} block Block of the reading view.
     * @returns {HTMLElement}
     */
    const highlightExcerpt = block => {
        const excerpt = document.createElement(block.tagName.toLowerCase());
        excerpt.dataset.blockId = block.dataset.blockId;
        if (block.dataset.page) {
            excerpt.dataset.page = block.dataset.page;
        }
        Array.from(block.querySelectorAll('mark[data-highlight-id]')).forEach((mark, index) => {
            if (index) {
                excerpt.append(document.createTextNode(' … '));
            }
            excerpt.append(mark.cloneNode(true));
        });
        return excerpt;
    };

    /**
     * Summary view: highlighted fragments under the headings of their sections.
     *
     * @param {HTMLElement} article Reading view.
     * @param {string} emptyMessage Message shown when nothing is highlighted.
     * @returns {HTMLElement}
     */
    const buildSummary = (article, emptyMessage) => {
        const summary = document.createElement('article');
        summary.className = 'underlyit-reading-document underlyit-reading-summary';
        const headings = [];
        const emitted = new Set();
        const emit = (block, element) => {
            if (!emitted.has(block.dataset.blockId)) {
                summary.append(element);
                emitted.add(block.dataset.blockId);
            }
        };
        const emitHeadings = list => list.forEach(heading => heading && emit(heading, heading.cloneNode(true)));
        Array.from(article.children).forEach(block => {
            const match = block.tagName.match(/^H([1-6])$/);
            const highlighted = Boolean(block.querySelector('mark[data-highlight-id]'));
            if (match) {
                const level = +match[1];
                headings.splice(level - 1);
                headings[level - 1] = block;
                if (highlighted) {
                    emitHeadings(headings.slice(0, level - 1));
                    emit(block, highlightExcerpt(block));
                }
            } else if (highlighted) {
                emitHeadings(headings);
                summary.append(highlightExcerpt(block));
            }
        });
        if (!summary.children.length) {
            showMessage(summary, 'p', 'underlyit-summary-empty', emptyMessage);
        }
        return summary;
    };

    /**
     * Removes the mark elements of a highlight, keeping their text.
     *
     * @param {HTMLElement} article Reading view.
     * @param {number} id Highlight ID.
     */
    const unmark = (article, id) => {
        article.querySelectorAll(`[data-highlight-id="${id}"]`).forEach(mark => {
            const parent = mark.parentNode;
            while (mark.firstChild) {
                parent.insertBefore(mark.firstChild, mark);
            }
            mark.remove();
            parent.normalize();
        });
    };

    /**
     * Starts the reader.
     *
     * @param {Object} config cmid, canhighlight, canmanage, showreference and pdfworkerurl.
     */
    async function init(config) {
        const s = await loadStrings();
        const root = document.getElementById('underlyit-app');
        const viewer = root.querySelector('[data-region=viewer]');
        const outline = root.querySelector('[data-region=outline]');
        const summaryButton = root.querySelector('[data-action=summary]');
        const deleteButton = root.querySelector('[data-action=delete-highlight]');
        const state = {
            documentRecord: null,
            highlighter: null,
            highlights: [],
            article: null,
            summaryMode: false,
            referenceMode: Boolean(config.canmanage),
            referenceVisible: Boolean(config.showreference),
            color: '#fff176',
            selectedId: null,
        };
        if (window.matchMedia('(max-width: 700px)').matches) {
            root.classList.add('underlyit-focus');
        }

        /**
         * Marks the chosen colour in the palette.
         */
        const syncPalette = () => {
            root.querySelectorAll('[data-highlight-color]').forEach(button => {
                const selected = button.dataset.highlightColor === state.color;
                const name = button.getAttribute('aria-label');
                button.classList.toggle('active', selected);
                button.setAttribute('aria-pressed', String(selected));
                button.title = selected ? `${s.selectedcolor}: ${name}` : name;
            });
        };

        /**
         * Disables the palette in reference mode, where it has no effect.
         */
        const syncPaletteAvailability = () => {
            const picker = root.querySelector('.underlyit-color-picker');
            if (!picker) {
                return;
            }
            picker.classList.toggle('underlyit-color-picker-disabled', state.referenceMode);
            picker.title = state.referenceMode ? s.referencecolorhint : '';
            picker.querySelectorAll('[data-highlight-color]').forEach(button => {
                button.disabled = state.referenceMode;
            });
        };

        /**
         * Shows which outline sections contain highlights.
         */
        const updateOutlineHighlights = () => {
            state.article.querySelectorAll('h1,h2,h3,h4,h5,h6').forEach(heading => {
                const link = outline.querySelector(`[data-outline-id="${heading.id}"]`);
                if (link) {
                    const contains = sectionHasHighlights(heading);
                    link.classList.toggle('has-highlights', contains);
                    link.title = contains ? s.containshighlights : '';
                }
            });
        };

        /**
         * Renders the outline from the headings of the document.
         */
        const renderOutline = async() => {
            const headings = Array.from(state.article.querySelectorAll('h1,h2,h3,h4,h5,h6'))
                .filter(heading => !/^\d+$/.test(heading.textContent.trim()))
                .map(heading => {
                    heading.id = `underlyit-${heading.dataset.blockId}`;
                    return {id: heading.id, level: +heading.tagName.slice(1), text: heading.textContent};
                });
            const {html, js} = await Templates.renderForPromise('mod_underlyit/outline',
                {headings, hasheadings: headings.length > 0});
            Templates.replaceNodeContents(outline, html, js);
            updateOutlineHighlights();
        };

        /**
         * Shows the full reading view or the summary.
         */
        const renderMode = () => {
            viewer.replaceChildren(state.summaryMode ? buildSummary(state.article, s.nosummaryhighlights) : state.article);
            // SVG elements have no hidden property, so toggle the attribute.
            summaryButton.querySelector('[data-icon=summary]').toggleAttribute('hidden', state.summaryMode);
            summaryButton.querySelector('[data-icon=full]').toggleAttribute('hidden', !state.summaryMode);
            const label = state.summaryMode ? s.fullreading : s.summaryreading;
            summaryButton.setAttribute('aria-label', label);
            summaryButton.title = label;
            summaryButton.setAttribute('aria-pressed', String(state.summaryMode));
        };

        /**
         * Selects one of the user's highlights so that it can be recoloured or deleted.
         *
         * @param {Object} record Highlight.
         */
        const selectHighlight = record => {
            state.selectedId = +record.id;
            if (!record.reference) {
                state.color = record.color;
                state.highlighter?.setColor(state.color);
                syncPalette();
            }
            state.article.querySelectorAll('mark[data-highlight-id]').forEach(mark =>
                mark.classList.toggle('underlyit-highlight-selected', +mark.dataset.highlightId === state.selectedId));
            if (deleteButton) {
                deleteButton.hidden = false;
                deleteButton.disabled = false;
            }
        };

        /**
         * Clears the highlight selection.
         */
        const clearSelectedHighlight = () => {
            state.selectedId = null;
            state.article?.querySelectorAll('.underlyit-highlight-selected').forEach(mark =>
                mark.classList.remove('underlyit-highlight-selected'));
            if (deleteButton) {
                deleteButton.hidden = true;
                deleteButton.disabled = true;
            }
        };

        /**
         * Saves a new highlight made by the user.
         *
         * @param {Object} data Selection data from the highlighter.
         */
        const saveHighlight = async data => {
            const saved = await call('mod_underlyit_create_highlight', {
                cmid: config.cmid,
                documentid: state.documentRecord.id,
                pagenumber: 0,
                headingcontext: '',
                ...data,
                reference: state.referenceMode,
                color: state.referenceMode ? REFERENCE_COLOR : data.color,
            });
            state.highlights.push(saved);
            state.highlighter.apply([saved]);
            updateOutlineHighlights();
        };

        /**
         * Downloads and renders the document with the visible highlights.
         */
        const openDocument = async() => {
            const record = state.documentRecord;
            state.highlights = await call('mod_underlyit_get_highlights', {cmid: config.cmid, documentid: record.id});
            viewer.className = 'underlyit-reading-host';
            const reader = new DocumentReader({
                url: record.url,
                filename: record.filename,
                mimetype: record.mimetype,
                workerUrl: config.pdfworkerurl,
                strings: s,
                progress: message => showMessage(viewer, 'div', 'underlyit-reading-progress', message),
            });
            state.article = await reader.load();
            if (config.canmanage) {
                await call('mod_underlyit_set_document_length', {
                    cmid: config.cmid,
                    documentid: record.id,
                    textlength: state.article.textContent.length,
                });
            }
            state.highlighter = new Highlighter(state.article, config.canhighlight ? saveHighlight : null);
            state.highlighter.setColor(state.color);
            state.highlighter.apply(state.highlights);
            await renderOutline();
            summaryButton.disabled = false;
            renderMode();
        };

        /**
         * Chooses a colour and recolours the selected personal highlight.
         *
         * @param {string} color Colour in #rrggbb form.
         */
        const chooseColor = async color => {
            state.color = color;
            syncPalette();
            state.highlighter?.setColor(color);
            const record = state.highlights.find(item => +item.id === state.selectedId);
            if (!record?.canedit || record.reference || record.color === color) {
                return;
            }
            const updated = await call('mod_underlyit_update_highlight', {
                cmid: config.cmid,
                highlightid: record.id,
                color,
            });
            record.color = updated.color;
            state.article.querySelectorAll(`[data-highlight-id="${record.id}"]`).forEach(mark => {
                mark.style.backgroundColor = updated.color;
            });
            renderMode();
            updateOutlineHighlights();
        };

        /**
         * Deletes the selected highlight after confirmation.
         */
        const deleteSelected = async() => {
            const id = state.selectedId;
            try {
                await Notification.deleteCancelPromise(s.deletehighlight, s.deletehighlightconfirm, s.delete,
                    {triggerElement: deleteButton});
            } catch (cancelled) {
                return;
            }
            await call('mod_underlyit_delete_highlight', {cmid: config.cmid, highlightid: id});
            unmark(state.article, id);
            state.highlights = state.highlights.filter(item => +item.id !== id);
            clearSelectedHighlight();
            renderMode();
            updateOutlineHighlights();
        };

        /**
         * Toolbar actions by data-action value.
         */
        const actions = {
            'toggle-focus': () => root.classList.toggle('underlyit-focus'),
            'toggle-outline': button => {
                const expanded = !root.classList.toggle('underlyit-outline-collapsed');
                button.setAttribute('aria-expanded', String(expanded));
                button.setAttribute('aria-label', expanded ? s.hideoutline : s.showoutline);
                button.title = expanded ? s.hideoutline : s.showoutline;
            },
            'summary': () => {
                state.summaryMode = !state.summaryMode;
                renderMode();
            },
            'delete-highlight': () => (state.selectedId ? deleteSelected() : null),
            'toggle-reference-view': button => {
                const hidden = root.classList.toggle('underlyit-reference-hidden');
                setToggle(button, !hidden, hidden ? s.viewreference : s.hidereferenceview);
            },
            'reference-visibility': async button => {
                state.referenceVisible = await call('mod_underlyit_set_reference_visibility', {
                    cmid: config.cmid,
                    visible: !state.referenceVisible,
                });
                setToggle(button, state.referenceVisible, state.referenceVisible ? s.hidereference : s.showreference);
            },
            'reference': button => {
                state.referenceMode = !state.referenceMode;
                setToggle(button, state.referenceMode, state.referenceMode ? s.referencemodeactive : s.referencemode);
                syncPaletteAvailability();
            },
        };

        /**
         * Scrolls to a heading chosen in the outline.
         *
         * @param {string} id ID of the heading.
         */
        const goToHeading = id => {
            if (state.summaryMode) {
                state.summaryMode = false;
                renderMode();
            }
            requestAnimationFrame(() => {
                const target = state.article.querySelector(`#${CSS.escape(id)}`);
                if (target) {
                    viewer.scrollTo({top: target.offsetTop - state.article.offsetTop - 16, behavior: 'smooth'});
                }
            });
        };

        /**
         * Selects a clicked highlight unless the user is selecting text.
         *
         * @param {HTMLElement} mark Clicked mark element.
         */
        const clickHighlight = mark => {
            const selection = window.getSelection();
            if (selection && !selection.isCollapsed) {
                return;
            }
            const record = state.highlights.find(item => +item.id === +mark.dataset.highlightId);
            if (record?.canedit) {
                selectHighlight(record);
            }
        };

        syncPaletteAvailability();
        try {
            const documents = await call('mod_underlyit_get_documents', {cmid: config.cmid});
            state.documentRecord = documents[0] || null;
            if (!state.documentRecord) {
                showMessage(viewer, 'p', 'underlyit-empty', s.nodocument);
                return;
            }
            root.querySelector('[data-action=original]').href = state.documentRecord.url;
            await openDocument();
        } catch (error) {
            showMessage(viewer, 'div', 'alert alert-danger m-3', s.documentprocesserror);
            Notification.exception(error);
            return;
        }

        root.addEventListener('click', event => {
            const colorButton = event.target.closest('[data-highlight-color]');
            const actionButton = event.target.closest('[data-action]');
            const outlineLink = event.target.closest('[data-outline-id]');
            const mark = event.target.closest('mark[data-highlight-id]');
            let result = null;
            if (colorButton) {
                result = chooseColor(colorButton.dataset.highlightColor);
            } else if (actionButton && actions[actionButton.dataset.action]) {
                result = actions[actionButton.dataset.action](actionButton);
            } else if (outlineLink) {
                event.preventDefault();
                goToHeading(outlineLink.dataset.outlineId);
            } else if (mark) {
                clickHighlight(mark);
            }
            Promise.resolve(result).catch(Notification.exception);
        });
    }

    return {init};
});
