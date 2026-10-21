define([], function() {
    class Highlighter {
        constructor(container, onSave) {
            this.container = container;
            this.onSave = onSave;
            this.color = '#fff176';
            this.mouseup = this.handleSelection.bind(this);
            container.addEventListener('mouseup', this.mouseup);
        }
        destroy() { this.container.removeEventListener('mouseup', this.mouseup); }
        setColor(color) { this.color = color; }
        offsets(range) {
            const before = document.createRange();
            before.selectNodeContents(this.container);
            before.setEnd(range.startContainer, range.startOffset);
            return {type: 'text-offset', start: before.toString().length, end: before.toString().length + range.toString().length};
        }
        handleSelection() {
            if (!this.onSave) return;
            const selection = window.getSelection();
            if (!selection || selection.isCollapsed || !selection.rangeCount) return;
            const range = selection.getRangeAt(0);
            if (!this.container.contains(range.commonAncestorContainer)) return;
            const element = node => node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;
            if (element(range.startContainer)?.closest('mark[data-highlight-id]') ||
                    element(range.endContainer)?.closest('mark[data-highlight-id]')) return;
            const text = selection.toString().trim();
            if (!text) return;
            this.onSave({selectedtext: text, selectorjson: JSON.stringify(this.offsets(range)), color: this.color, positionorder: this.offsets(range).start});
            selection.removeAllRanges();
        }
        apply(items) {
            [...items].sort((a,b) => b.positionorder-a.positionorder).forEach(item => {
                if (this.container.querySelector(`[data-highlight-id="${item.id}"]`)) return;
                let selector = null;
                try { selector = JSON.parse(item.selectorjson || '{}'); } catch (error) { selector = null; }
                const range = selector?.type === 'text-offset'
                    ? this.rangeFromOffsets(selector.start, selector.end)
                    : this.rangeFromText(item.selectedtext);
                if (!range) return;
                this.markRange(range, item);
            });
        }
        markRange(range, item) {
            const walker = document.createTreeWalker(this.container, NodeFilter.SHOW_TEXT);
            const segments = [];
            let node;
            while ((node = walker.nextNode())) {
                if (!range.intersectsNode(node)) continue;
                const start = node === range.startContainer ? range.startOffset : 0;
                const end = node === range.endContainer ? range.endOffset : node.nodeValue.length;
                if (end > start) segments.push({node, start, end});
            }
            segments.reverse().forEach(segment => {
                const part = document.createRange();
                part.setStart(segment.node, segment.start);
                part.setEnd(segment.node, segment.end);
                const mark = document.createElement('mark');
                mark.dataset.highlightId = item.id;
                mark.style.backgroundColor = item.color;
                mark.title = item.note || item.userfullname;
                try { part.surroundContents(mark); } catch (error) { /* Ignore stale fragments only. */ }
            });
        }        rangeFromText(text) {
            const target = (text || '').replace(/\s+/g, ' ').trim();
            if (!target) return null;
            const flat = this.container.textContent;
            let index = flat.indexOf(target);
            if (index >= 0) return this.rangeFromOffsets(index, index + target.length);
            let compact = '', previousSpace = false;
            const map = [];
            for (let i = 0; i < flat.length; i++) {
                const isSpace = /\s/.test(flat[i]);
                if (isSpace && previousSpace) continue;
                compact += isSpace ? ' ' : flat[i]; map.push(i); previousSpace = isSpace;
            }
            index = compact.indexOf(target);
            if (index < 0) return null;
            return this.rangeFromOffsets(map[index], map[Math.min(map.length - 1, index + target.length - 1)] + 1);
        }        rangeFromOffsets(start, end) {
            const walker = document.createTreeWalker(this.container, NodeFilter.SHOW_TEXT);
            let node, offset = 0, startNode, startOffset, endNode, endOffset;
            while ((node = walker.nextNode())) {
                const next = offset + node.nodeValue.length;
                if (!startNode && start >= offset && start <= next) { startNode=node; startOffset=start-offset; }
                if (end >= offset && end <= next) { endNode=node; endOffset=end-offset; break; }
                offset = next;
            }
            if (!startNode || !endNode) return null;
            const range=document.createRange(); range.setStart(startNode,startOffset); range.setEnd(endNode,endOffset); return range;
        }
    }
    return Highlighter;
});
