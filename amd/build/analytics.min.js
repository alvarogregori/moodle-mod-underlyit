define(['core/ajax', 'core/notification', 'core/str', 'mod_studymark/documentreader'], function(Ajax, Notification, Str, DocumentReader) {
    const call = (method, args) => Ajax.call([{methodname: method, args}])[0];
    const escape = value => { const node = document.createElement('div'); node.textContent = value == null ? '' : String(value); return node.innerHTML; };
    const percent = value => `${Number(value).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}%`;
    function bar(value, label) {
        const safe = Math.max(0, Math.min(100, Number(value) || 0));
        return `<div class="studymark-metric"><span>${escape(label)}</span><strong>${percent(safe)}</strong><div class="progress" role="progressbar" aria-label="${escape(label)}" aria-valuenow="${safe}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:${safe}%"></div></div></div>`;
    }
    const loadStrings = async() => {
        const keys = ['analyzingdocument', 'nodocument', 'referencecontains', 'highlightedcharacters', 'noreferencehelp',
            'readingreached', 'detaillevel', 'comparedaverage', 'match', 'noreference', 'courseaveragedetail',
            'documentlength', 'characters', 'student', 'highlights', 'detail', 'precision', 'recall', 'f1score', 'jaccard', 'nostudents',
            'calculationheading', 'progresscalculation', 'detailcalculation', 'precisioncalculation', 'recallcalculation', 'f1calculation', 'matchcalculation', 'progressestimate',
            'reporterror', 'extractingdocument', 'downloaddocumenterror', 'pdfjsunavailable', 'extractingpage', 'of',
            'jszipunavailable', 'invalidepub', 'invaliddocx'];
        const values = await Str.get_strings(keys.map(key => ({key, component: 'mod_studymark'})));
        return Object.fromEntries(keys.map((key, index) => [key, values[index]]));
    };

    async function init(config) {
        const s = await loadStrings();
        const root = document.getElementById('studymark-analytics');
        root.innerHTML = `<div class="studymark-analytics-loading">${escape(s.analyzingdocument)}</div>`;
        try {
            const documents = await call('mod_studymark_get_documents', {cmid: config.cmid});
            const doc = documents[0];
            if (!doc) { root.innerHTML = `<div class="alert alert-info">${escape(s.nodocument)}</div>`; return; }
            const reader = new DocumentReader({url: doc.url, filename: doc.filename, mimetype: doc.mimetype, workerUrl: config.pdfworkerurl, strings: s,
                progress: message => { root.innerHTML = `<div class="studymark-analytics-loading">${escape(message)}</div>`; }});
            const article = await reader.load();
            const result = await call('mod_studymark_get_analytics', {cmid: config.cmid, documentid: doc.id});
const referenceNote = result.hasreference
                ? `${s.referencecontains} ${Number(result.referencechars).toLocaleString()} ${s.highlightedcharacters}`
                : s.noreferencehelp;
            const comparisonCell = (value, label) => result.hasreference ? bar(value, label) : `<span class="text-muted">${escape(s.noreference)}</span>`;
            const rows = result.students.map(student => `<tr><th scope="row">${escape(student.fullname)}</th><td>${student.highlightcount}</td><td>${bar(student.progress, s.readingreached)}</td><td>${bar(student.detail, s.detaillevel)}<small class="d-block ${student.differencefromaverage >= 0 ? 'text-success' : 'text-muted'}">${student.differencefromaverage >= 0 ? '+' : ''}${percent(student.differencefromaverage)} ${escape(s.comparedaverage)}</small></td><td>${comparisonCell(student.precision, s.precision)}</td><td>${comparisonCell(student.recall, s.recall)}</td><td>${comparisonCell(student.f1score, s.f1score)}</td><td>${comparisonCell(student.referenceoverlap, s.jaccard)}</td></tr>`).join('');
            root.innerHTML = `<section class="studymark-analytics-summary"><div><span>${escape(s.courseaveragedetail)}</span><strong>${percent(result.courseaverage)}</strong></div><div><span>${escape(s.documentlength)}</span><strong>${Number(result.documentchars).toLocaleString()} ${escape(s.characters)}</strong></div></section><div class="alert ${result.hasreference ? 'alert-light' : 'alert-warning'}">${escape(referenceNote)}</div><div class="table-responsive"><table class="table table-striped studymark-analytics-table"><thead><tr><th>${escape(s.student)}</th><th>${escape(s.highlights)}</th><th>${escape(s.readingreached)}</th><th>${escape(s.detail)}</th><th>${escape(s.precision)}</th><th>${escape(s.recall)}</th><th>${escape(s.f1score)}</th><th>${escape(s.jaccard)}</th></tr></thead><tbody>${rows || `<tr><td colspan="8" class="text-muted text-center">${escape(s.nostudents)}</td></tr>`}</tbody></table></div><details class="studymark-analytics-help"><summary>${escape(s.calculationheading)}</summary><ul><li><strong>${escape(s.readingreached)}:</strong> ${escape(s.progresscalculation)}</li><li><strong>${escape(s.detail)}:</strong> ${escape(s.detailcalculation)}</li><li><strong>${escape(s.precision)}:</strong> ${escape(s.precisioncalculation)}</li><li><strong>${escape(s.recall)}:</strong> ${escape(s.recallcalculation)}</li><li><strong>${escape(s.f1score)}:</strong> ${escape(s.f1calculation)}</li><li><strong>${escape(s.jaccard)}:</strong> ${escape(s.matchcalculation)}</li></ul><p>${escape(s.progressestimate)}</p></details>`;
        } catch (error) { root.innerHTML = `<div class="alert alert-danger">${escape(s.reporterror)}</div>`; Notification.exception(error); }
    }
    return {init};
});
