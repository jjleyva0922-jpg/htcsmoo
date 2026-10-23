import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const layout = readFileSync(new URL('../../resources/views/layouts/app.blade.php', import.meta.url), 'utf8');
const metricSource = layout.slice(layout.indexOf('function countUp('), layout.lastIndexOf('</script>'));

test('dashboard metrics keep their final value on initialization and refresh without timers', () => {
    const context = vm.createContext({
        setInterval() { throw new Error('Metrics must not restart a counter animation'); },
        setTimeout() { throw new Error('Metrics must not defer their displayed value'); },
    });
    vm.runInContext(metricSource, context);
    for (const target of [0, 1, 42, 1500.5]) {
        assert.equal(vm.runInContext(`countUp(${target}).current`, context), target);
    }
});
