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
 * Creates and restores text-offset highlights in the reading view.
 *
 * @module     mod_underlyit/highlighter
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Returns the element of a node, or its parent for text nodes.
     *
     * @param {Node} node DOM node.
     * @returns {Element|null}
     */
    const elementOf = node => (node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement);

    /**
     * Highlights text in a container and restores saved highlights.
     */
    class Highlighter {
        /**
         * Constructor.
         *
         * @param {HTMLElement} container Element holding the reading view.
         * @param {Function|null} onSave Called with the new highlight data, or null when the user cannot highlight.
         */
        constructor(container, onSave) {
            this.container = container;
            this.onSave = onSave;
            this.color = '#fff176';
            this.mouseup = this.handleSelection.bind(this);
            container.addEventListener('mouseup', this.mouseup);
        }

        /**
         * Stops listening to selections.
         */
        destroy() {
            this.container.removeEventListener('mouseup', this.mouseup);
        }

        /**
         * Sets the colour of new highlights.
         *
         * @param {string} color Colour in #rrggbb form.
         */
        setColor(color) {
            this.color = color;
        }

        /**
         * Calculates the text offsets of a range in the container.
         *
         * @param {Range} range Selected range.
         * @returns {Object} Text-offset selector.
         */
        offsets(range) {
            const before = document.createRange();
            before.selectNodeContents(this.container);
            before.setEnd(range.startContainer, range.startOffset);
            const start = before.toString().length;
            return {type: 'text-offset', start, end: start + range.toString().length};
        }

        /**
         * Saves the current selection as a highlight.
         */
        handleSelection() {
            if (!this.onSave) {
                return;
            }
            const selection = window.getSelection();
            if (!selection || selection.isCollapsed || !selection.rangeCount) {
                return;
            }
            const range = selection.getRangeAt(0);
            if (!this.container.contains(range.commonAncestorContainer)) {
                return;
            }
            if (elementOf(range.startContainer)?.closest('mark[data-highlight-id]') ||
                    elementOf(range.endContainer)?.closest('mark[data-highlight-id]')) {
                return;
            }
            const text = selection.toString().trim();
            if (!text) {
                return;
            }
            const selector = this.offsets(range);
            this.onSave({
                selectedtext: text,
                selectorjson: JSON.stringify(selector),
                color: this.color,
                positionorder: selector.start,
            });
            selection.removeAllRanges();
        }

        /**
         * Draws saved highlights.
         *
         * @param {Array} items Highlights returned by the server.
         */
        apply(items) {
            [...items].sort((a, b) => b.positionorder - a.positionorder).forEach(item => {
                if (this.container.querySelector(`[data-highlight-id="${item.id}"]`)) {
                    return;
                }
                let selector = null;
                try {
                    selector = JSON.parse(item.selectorjson || '{}');
                } catch (error) {
                    selector = null;
                }
                const range = selector?.type === 'text-offset'
                    ? this.rangeFromOffsets(selector.start, selector.end)
                    : this.rangeFromText(item.selectedtext);
                if (range) {
                    this.markRange(range, item);
                }
            });
        }

        /**
         * Wraps every text node of a range in a mark element.
         *
         * @param {Range} range Range to mark.
         * @param {Object} item Highlight.
         */
        markRange(range, item) {
            const walker = document.createTreeWalker(this.container, NodeFilter.SHOW_TEXT);
            const segments = [];
            let node = walker.nextNode();
            while (node) {
                if (range.intersectsNode(node)) {
                    const start = node === range.startContainer ? range.startOffset : 0;
                    const end = node === range.endContainer ? range.endOffset : node.nodeValue.length;
                    if (end > start) {
                        segments.push({node, start, end});
                    }
                }
                node = walker.nextNode();
            }
            segments.reverse().forEach(segment => {
                const part = document.createRange();
                part.setStart(segment.node, segment.start);
                part.setEnd(segment.node, segment.end);
                const mark = document.createElement('mark');
                mark.dataset.highlightId = item.id;
                mark.dataset.reference = item.reference ? '1' : '0';
                if (!item.reference) {
                    // The tutor reference is drawn as an underline by the stylesheet.
                    mark.style.backgroundColor = item.color;
                }
                mark.title = item.userfullname;
                try {
                    part.surroundContents(mark);
                } catch (error) {
                    // A stale fragment that crosses element boundaries is left unmarked.
                }
            });
        }

        /**
         * Finds a text in the container, ignoring differences in white space.
         *
         * @param {string} text Text to find.
         * @returns {Range|null}
         */
        rangeFromText(text) {
            const target = (text || '').replace(/\s+/g, ' ').trim();
            if (!target) {
                return null;
            }
            const flat = this.container.textContent;
            let index = flat.indexOf(target);
            if (index >= 0) {
                return this.rangeFromOffsets(index, index + target.length);
            }
            let compact = '';
            let previousSpace = false;
            const map = [];
            for (let i = 0; i < flat.length; i++) {
                const isSpace = /\s/.test(flat[i]);
                if (isSpace && previousSpace) {
                    continue;
                }
                compact += isSpace ? ' ' : flat[i];
                map.push(i);
                previousSpace = isSpace;
            }
            index = compact.indexOf(target);
            if (index < 0) {
                return null;
            }
            return this.rangeFromOffsets(map[index], map[Math.min(map.length - 1, index + target.length - 1)] + 1);
        }

        /**
         * Builds a range from text offsets in the container.
         *
         * @param {number} start Start offset.
         * @param {number} end End offset.
         * @returns {Range|null}
         */
        rangeFromOffsets(start, end) {
            const walker = document.createTreeWalker(this.container, NodeFilter.SHOW_TEXT);
            let offset = 0;
            let startNode = null;
            let startOffset = 0;
            let endNode = null;
            let endOffset = 0;
            let node = walker.nextNode();
            while (node) {
                const next = offset + node.nodeValue.length;
                if (!startNode && start >= offset && start <= next) {
                    startNode = node;
                    startOffset = start - offset;
                }
                if (end >= offset && end <= next) {
                    endNode = node;
                    endOffset = end - offset;
                    break;
                }
                offset = next;
                node = walker.nextNode();
            }
            if (!startNode || !endNode) {
                return null;
            }
            const range = document.createRange();
            range.setStart(startNode, startOffset);
            range.setEnd(endNode, endOffset);
            return range;
        }
    }

    return Highlighter;
});
