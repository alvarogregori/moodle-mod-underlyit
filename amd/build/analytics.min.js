define(['core/ajax', 'core/notification', 'core/str', 'mod_studymark/documentreader'], function(Ajax, Notification, Str, DocumentReader) {
    let strings = {};
    const call = (method, args) => Ajax.call([{methodname: method, args}])[0];
    const escape = value => { const node = document.createElement('div'); node.textContent = value == null ? '' : String(value); return node.innerHTML; };
    const percent = value => `${Number(value).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}%`;
    const status = (value, type = 'comparison') => {
        const score = Number(value) || 0;
        const limits = type === 'progress' ? [75, 40] : [50, 25];
        return score >= limits[0] ? 'success' : (score >= limits[1] ? 'warning' : 'danger');
    };
    const difference = value => {
        const number = Number(value) || 0;
        const arrow = number > 0 ? '↑' : (number < 0 ? '↓' : '=');
        const sign = number > 0 ? '+' : '';
        return `<small class="studymark-metric-difference ${number > 0 ? 'text-success' : (number < 0 ? 'text-danger' : 'text-muted')}">${arrow} ${sign}${percent(number)} ${escape(strings.comparedaverage)}</small>`;
    };
    function bar(value, label, averageDifference, type = 'comparison') {
        const safe = Math.max(0, Math.min(100, Number(value) || 0));
        const state = status(safe, type);
        return `<div class="studymark-metric"><div class="studymark-metric-heading"><span>${escape(label)}</span><span class="badge badge-${state}">${percent(safe)}</span></div><div class="progress studymark-progress-point" role="progressbar" aria-label="${escape(label)}" aria-valuenow="${safe}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-${state}" style="width:${safe}%"></div><span class="studymark-progress-marker" style="left:${safe}%"></span></div>${difference(averageDifference)}</div>`;
    }
    function matchBar(student) {
        const matched = Number(student.intersectionchars) || 0;
        const extra = Number(student.studentonlychars) || 0;
        const missing = Number(student.referenceonlychars) || 0;
        const total = matched + extra + missing || 1;
        const width = value => Math.max(0, 100 * value / total);
        const state = status(student.referenceoverlap);
        return `<div class="studymark-metric studymark-match-metric"><div class="studymark-metric-heading"><span>${escape(strings.jaccard)}</span><span class="badge badge-${state}">${percent(student.referenceoverlap)}</span></div><div class="progress studymark-match-bar" aria-label="${escape(strings.matchbreakdown)}"><div class="progress-bar bg-success" style="width:${width(matched)}%" title="${escape(strings.matched)}: ${matched.toLocaleString()}"></div><div class="progress-bar bg-warning" style="width:${width(extra)}%" title="${escape(strings.extra)}: ${extra.toLocaleString()}"></div><div class="progress-bar bg-danger" style="width:${width(missing)}%" title="${escape(strings.missing)}: ${missing.toLocaleString()}"></div></div><div class="studymark-match-legend"><span class="matched">${escape(strings.matched)}</span><span class="extra">${escape(strings.extra)}</span><span class="missing">${escape(strings.missing)}</span></div>${difference(student.jaccarddifference)}</div>`;
    }
    const loadStrings = async() => {
        const keys = ['analyzingdocument', 'nodocument', 'referencecontains', 'highlightedcharacters', 'noreferencehelp',
            'readingreached', 'detaillevel', 'comparedaverage', 'match', 'noreference', 'courseaveragedetail',
            'documentlength', 'characters', 'student', 'highlights', 'detail', 'precision', 'recall', 'f1score', 'jaccard', 'nostudents',
            'calculationheading', 'progresscalculation', 'detailcalculation', 'precisioncalculation', 'recallcalculation', 'f1calculation', 'matchcalculation', 'progressestimate',
            'reporterror', 'extractingdocument', 'downloaddocumenterror', 'pdfjsunavailable', 'extractingpage', 'of',
            'jszipunavailable', 'invalidepub', 'invaliddocx', 'statuslegend', 'matched', 'extra', 'missing', 'matchbreakdown',
            'reportguideheading', 'reportguiderelevance', 'reportguidecoverage', 'reportguidebalance', 'reportguideaverage',
            'simpleview', 'showtechnical', 'hidetechnical', 'generalstatus', 'interpretationhigh', 'interpretationmedium',
            'interpretationlow', 'interpretationnone', 'statusgood', 'statusdeveloping', 'statusreview', 'statusnodata'];
        const values = await Str.get_strings(keys.map(key => ({key, component: 'mod_studymark'})));
        return Object.fromEntries(keys.map((key, index) => [key, values[index]]));
    };

    async function init(config) {
        const s = await loadStrings();
        strings = s;
        const root = document.getElementById('studymark-analytics');
        root.innerHTML = `<div class="studymark-analytics-loading">${escape(s.analyzingdocument)}</div>`;
        try {
            const documents = await call('mod_studymark_get_documents', {cmid: config.cmid});
            const doc = documents[0];
            if (!doc) { root.innerHTML = `<div class="alert alert-info">${escape(s.nodocument)}</div>`; return; }
            const reader = new DocumentReader({url: doc.url, filename: doc.filename, mimetype: doc.mimetype, workerUrl: config.pdfworkerurl, strings: s,
                progress: message => { root.innerHTML = `<div class="studymark-analytics-loading">${escape(message)}</div>`; }});
            await reader.load();
            const result = await call('mod_studymark_get_analytics', {cmid: config.cmid, documentid: doc.id});
            const referenceNote = result.hasreference
                ? `${s.referencecontains} ${Number(result.referencechars).toLocaleString()} ${s.highlightedcharacters}`
                : s.noreferencehelp;
            const comparisonCell = (value, label, delta) => result.hasreference ? bar(value, label, delta) : `<span class="text-muted">${escape(s.noreference)}</span>`;
            const heading = (label, help) => `<span class="studymark-column-heading">${escape(label)} <span class="studymark-info" tabindex="0" title="${escape(help)}" aria-label="${escape(help)}">&#9432;</span></span>`;
            const interpretation = student => {
                if (!student.highlightcount) return {state: 'secondary', label: s.statusnodata, text: s.interpretationnone};
                const score = Number(student.f1score) || 0;
                return score >= 50 ? {state: 'success', label: s.statusgood, text: s.interpretationhigh}
                    : (score >= 25 ? {state: 'warning', label: s.statusdeveloping, text: s.interpretationmedium}
                        : {state: 'danger', label: s.statusreview, text: s.interpretationlow});
            };
            const students = [...result.students].sort((a, b) => (Number(b.f1score) || 0) - (Number(a.f1score) || 0));
            const rows = students.map(student => {
                const summary = interpretation(student);
                return `<tr><th scope="row"><span class="studymark-student-name">${escape(student.fullname)}</span><span class="badge badge-${summary.state}">${escape(summary.label)}</span><small class="studymark-student-interpretation">${escape(summary.text)}</small></th><td>${student.highlightcount}</td><td>${bar(student.progress, s.readingreached, student.progressdifference, 'progress')}</td><td class="studymark-technical-column">${bar(student.detail, s.detaillevel, student.differencefromaverage)}</td><td>${comparisonCell(student.precision, s.precision, student.precisiondifference)}</td><td>${comparisonCell(student.recall, s.recall, student.recalldifference)}</td><td>${comparisonCell(student.f1score, s.f1score, student.f1difference)}</td><td class="studymark-technical-column">${result.hasreference ? matchBar(student) : `<span class="text-muted">${escape(s.noreference)}</span>`}</td></tr>`;
            }).join('');
            root.innerHTML = `<aside class="alert alert-info studymark-report-guide"><strong>${escape(s.reportguideheading)}</strong><ul><li>${escape(s.reportguiderelevance)}</li><li>${escape(s.reportguidecoverage)}</li><li>${escape(s.reportguidebalance)}</li></ul><small>${escape(s.reportguideaverage)}</small></aside><div class="studymark-report-controls"><button type="button" class="btn btn-outline-secondary btn-sm" data-action="toggle-technical" aria-pressed="false">${escape(s.showtechnical)}</button><span>${escape(s.simpleview)}</span></div><section class="studymark-analytics-summary"><div><span>${escape(s.courseaveragedetail)}</span><strong>${percent(result.courseaverage)}</strong></div><div><span>${escape(s.documentlength)}</span><strong>${Number(result.documentchars).toLocaleString()} ${escape(s.characters)}</strong></div></section><div class="alert ${result.hasreference ? 'alert-light' : 'alert-warning'}">${escape(referenceNote)}</div><div class="table-responsive"><table class="table table-striped studymark-analytics-table"><thead><tr><th>${escape(s.student)}</th><th>${escape(s.highlights)}</th><th>${heading(s.readingreached, s.progresscalculation)}</th><th class="studymark-technical-column">${heading(s.detail, s.detailcalculation)}</th><th>${heading(s.precision, s.precisioncalculation)}</th><th>${heading(s.recall, s.recallcalculation)}</th><th>${heading(s.f1score, s.f1calculation)}</th><th class="studymark-technical-column">${heading(s.jaccard, s.matchcalculation)}</th></tr></thead><tbody>${rows || `<tr><td colspan="8" class="text-muted text-center">${escape(s.nostudents)}</td></tr>`}</tbody></table></div><details class="studymark-analytics-help"><summary>${escape(s.calculationheading)}</summary><ul><li><strong>${escape(s.readingreached)}:</strong> ${escape(s.progresscalculation)}</li><li><strong>${escape(s.detail)}:</strong> ${escape(s.detailcalculation)}</li><li><strong>${escape(s.precision)}:</strong> ${escape(s.precisioncalculation)}</li><li><strong>${escape(s.recall)}:</strong> ${escape(s.recallcalculation)}</li><li><strong>${escape(s.f1score)}:</strong> ${escape(s.f1calculation)}</li><li><strong>${escape(s.jaccard)}:</strong> ${escape(s.matchcalculation)}</li></ul><p>${escape(s.statuslegend)}</p><p>${escape(s.progressestimate)}</p></details>`;
            const technicalButton = root.querySelector('[data-action="toggle-technical"]');
            technicalButton.addEventListener('click', () => {
                const expanded = root.classList.toggle('studymark-show-technical');
                technicalButton.setAttribute('aria-pressed', String(expanded));
                technicalButton.textContent = expanded ? s.hidetechnical : s.showtechnical;
            });
        } catch (error) { root.innerHTML = `<div class="alert alert-danger">${escape(s.reporterror)}</div>`; Notification.exception(error); }
    }
    return {init};
});