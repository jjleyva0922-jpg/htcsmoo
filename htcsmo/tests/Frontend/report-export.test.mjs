import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const view = readFileSync(new URL('../../resources/views/admin/reports/index.blade.php', import.meta.url), 'utf8');
const source = view.slice(view.lastIndexOf('<script>') + '<script>'.length, view.lastIndexOf('</script>'));

function browser(response) {
    const elements = new Map();
    const downloads = [];
    const requests = [];
    function element() {
        return {
            hidden: false, disabled: false, textContent: '', listeners: new Map(),
            addEventListener(event, callback) {this.listeners.set(event, callback);},
            remove() {},
            click() {downloads.push({href: this.href, filename: this.download});},
        };
    }
    for (const id of ['reportForm', 'downloadPdfButton', 'pdfGenerationModal', 'cancelPdfGeneration', 'retryPdfGeneration', 'pdfGenerationTitle', 'pdfGenerationMessage', 'pdfGenerationSpinner', 'pdfGenerationErrorIcon', 'pdfGenerationProgress']) {
        elements.set(id, element());
    }
    const form = elements.get('reportForm');
    form.action = 'https://app.test/admin/reports/builder';
    form.closest = () => ({});
    const modal = {show() {}, hide() {this.closed = true;}};
    const bootstrap = {Modal: {getOrCreateInstance: () => modal}};
    class DownloadURL extends URL {
        static createObjectURL() {return 'blob:report';}
        static revokeObjectURL() {}
    }
    const window = {
        bootstrap, location: {href: form.action}, htcFilters: {validate: () => true},
        setTimeout() {},
    };
    const document = {
        readyState: 'complete', body: {appendChild() {}},
        getElementById: id => elements.get(id), createElement: element,
    };
    vm.runInNewContext(source, {
        window, document, bootstrap, AbortController, URL: DownloadURL, URLSearchParams,
        FormData: class {
            *[Symbol.iterator]() {yield ['report_type', 'user_activity']; yield ['dept', 'CETE'];}
        },
        fetch: async (url, options) => {requests.push({url, options}); return response;},
    });
    return {elements, requests, downloads, window, modal, generate: () => elements.get('downloadPdfButton').listeners.get('click')()};
}

test('PDF row-limit errors are displayed to the user and do not create a download', async () => {
    const message = 'PDF reports support up to 500 rows. Narrow the filters or export CSV for all matching records.';
    const app = browser({ok: false, status: 422, headers: new Headers({'Content-Type': 'application/json'}), json: async () => ({message})});
    await app.generate();
    assert.equal(app.elements.get('pdfGenerationTitle').textContent, 'PDF generation failed');
    assert.equal(app.elements.get('pdfGenerationMessage').textContent, message);
    assert.equal(app.elements.get('pdfGenerationSpinner').hidden, true);
    assert.equal(app.elements.get('pdfGenerationErrorIcon').hidden, false);
    assert.equal(app.elements.get('downloadPdfButton').disabled, false);
    assert.equal(app.downloads.length, 0);
    assert.equal(app.requests[0].url.searchParams.get('dept'), 'CETE');
    assert.equal(app.requests[0].url.searchParams.get('download_pdf'), '1');
});

test('PDF server failures keep the modal usable with clear feedback', async () => {
    const app = browser({ok: false, status: 500, headers: new Headers({'Content-Type': 'text/html'})});
    await app.generate();
    assert.match(app.elements.get('pdfGenerationMessage').textContent, /Check your connection and try again/);
    assert.equal(app.elements.get('retryPdfGeneration').hidden, false);
    assert.equal(app.elements.get('cancelPdfGeneration').textContent, 'Close');
    assert.equal(app.downloads.length, 0);
});

test('supported PDF downloads retain the server filename and selected filters', async () => {
    const filename = 'HTC_SMO_user_activity_20261022_120000.pdf';
    const app = browser({ok: true, headers: new Headers({'Content-Type': 'application/pdf', 'Content-Disposition': `attachment; filename="${filename}"`}), blob: async () => new Blob(['PDF'])});
    await app.generate();
    assert.deepEqual(app.downloads, [{href: 'blob:report', filename}]);
    assert.equal(app.modal.closed, true);
    assert.equal(app.elements.get('downloadPdfButton').disabled, false);
});
