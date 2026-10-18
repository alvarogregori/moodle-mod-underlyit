define(['core/ajax', 'core/notification', 'mod_studymark/documentreader'], function(Ajax, Notification, DocumentReader) {
    const call = (method, args) => Ajax.call([{methodname: method, args}])[0];
    const escape = value => { const node = document.createElement('div'); node.textContent = value == null ? '' : String(value); return node.innerHTML; };
    const percent = value => `${Number(value).toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}%`;
    function bar(value, label) {
        const safe = Math.max(0, Math.min(100, Number(value) || 0));
        return `<div class="studymark-metric"><span>${escape(label)}</span><strong>${percent(safe)}</strong><div class="progress" role="progressbar" aria-label="${escape(label)}" aria-valuenow="${safe}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:${safe}%"></div></div></div>`;
    }
    async function init(config) {
        const root = document.getElementById('studymark-analytics');
        root.innerHTML = '<div class="studymark-analytics-loading">Analizando el documento…</div>';
        try {
            const documents = await call('mod_studymark_get_documents', {cmid: config.cmid});
            const doc = documents[0];
            if (!doc) { root.innerHTML = '<div class="alert alert-info">Esta actividad todavía no tiene documento.</div>'; return; }
            const reader = new DocumentReader({url: doc.url, filename: doc.filename, mimetype: doc.mimetype, workerUrl: config.pdfworkerurl,
                progress: message => { root.innerHTML = `<div class="studymark-analytics-loading">${escape(message)}</div>`; }});
            const article = await reader.load();
            const result = await call('mod_studymark_get_analytics', {cmid: config.cmid, documentid: doc.id, totalchars: article.textContent.length});
            const referenceNote = result.hasreference ? `La referencia contiene ${Number(result.referencechars).toLocaleString()} caracteres subrayados.` : 'Aún no hay subrayado de referencia. Subraya el documento con una cuenta gestora para crearlo.';
            const rows = result.students.map(student => `<tr><th scope="row">${escape(student.fullname)}</th><td>${student.highlightcount}</td><td>${bar(student.progress, 'Lectura alcanzada')}</td><td>${bar(student.detail, 'Nivel de detalle')}<small class="d-block ${student.differencefromaverage >= 0 ? 'text-success' : 'text-muted'}">${student.differencefromaverage >= 0 ? '+' : ''}${percent(student.differencefromaverage)} respecto a la media</small></td><td>${result.hasreference ? bar(student.referenceoverlap, 'Coincidencia') : '<span class="text-muted">Sin referencia</span>'}</td></tr>`).join('');
            root.innerHTML = `<section class="studymark-analytics-summary"><div><span>Media de detalle del curso</span><strong>${percent(result.courseaverage)}</strong></div><div><span>Extensión del documento</span><strong>${Number(result.documentchars).toLocaleString()} caracteres</strong></div></section><div class="alert ${result.hasreference ? 'alert-light' : 'alert-warning'}">${escape(referenceNote)}</div><div class="table-responsive"><table class="table table-striped studymark-analytics-table"><thead><tr><th>Alumno</th><th>Subrayados</th><th>Lectura alcanzada</th><th>Detalle</th><th>Coincidencia con referencia</th></tr></thead><tbody>${rows || '<tr><td colspan="5" class="text-muted text-center">No hay alumnos matriculados con permiso para subrayar.</td></tr>'}</tbody></table></div><details class="studymark-analytics-help"><summary>Cómo se calculan estos datos</summary><ul><li><strong>Lectura alcanzada:</strong> posición final del subrayado más avanzado respecto al documento.</li><li><strong>Detalle:</strong> caracteres únicos subrayados respecto al texto total.</li><li><strong>Coincidencia:</strong> intersección dividida por la unión de los intervalos del alumno y de la referencia (índice de Jaccard).</li></ul><p>La lectura alcanzada es una estimación basada en el subrayado, no una confirmación de lectura.</p></details>`;
        } catch (error) { root.innerHTML = '<div class="alert alert-danger">No se pudo generar el informe.</div>'; Notification.exception(error); }
    }
    return {init};
});
