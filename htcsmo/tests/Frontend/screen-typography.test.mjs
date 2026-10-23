import assert from 'node:assert/strict';
import {readdirSync, readFileSync} from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import test from 'node:test';
import postcss from 'postcss';

const root = new URL('../../', import.meta.url);
const css = readFileSync(new URL('resources/css/app.css', root), 'utf8');
const tokens = new Map();
postcss.parse(css).walkRules(':root', rule => rule.walkDecls(decl => tokens.set(decl.prop, decl.value)));
const files = directory => readdirSync(directory, {withFileTypes: true}).flatMap(entry => entry.isDirectory() ? files(path.join(directory, entry.name)) : [path.join(directory, entry.name)]);
const screenFiles = files(fileURLToPath(new URL('resources/views', root))).filter(file => file.endsWith('.blade.php') && !/[/\\]emails[/\\]|[/\\]pdf[^/\\]*\.blade\.php$|forecast-report\.blade\.php$/.test(file));
function inPrint(decl) {
    for (let ancestor = decl.parent; ancestor; ancestor = ancestor.parent) {
        if (ancestor.type === 'atrule' && /\bprint\b/.test(ancestor.params)) return true;
    }
    return false;
}
function pixels(value) {
    const variable = value.match(/^var\((--[\w-]+)\)$/);
    if (variable) return pixels(tokens.get(variable[1]) || 'unknown');
    const number = value.match(/^([\d.]+)(rem|px)$/);
    return number ? Number(number[1]) * (number[2] === 'rem' ? 16 : 1) : NaN;
}

test('screen typography stays on the readable scale across shared, page and inline styles', () => {
    const failures = [];
    const inspect = (source, file) => {
        postcss.parse(source).walkDecls('font-size', decl => {
            if (inPrint(decl) || decl.parent.selector === '.request-purpose-block::after') return;
            if (['inherit', 'initial', 'unset'].includes(decl.value)) return;
            const size = pixels(decl.value);
            if (![12, 14, 16, 20, 24].includes(size)) failures.push(`${file}: ${decl.parent.selector || 'inline'} = ${decl.value}`);
            if (decl.parent.selector === 'html' && size !== 16) failures.push(`${file}: root must remain 16px`);
        });
    };
    inspect(css, 'app.css');
    for (const file of screenFiles) {
        const view = readFileSync(file, 'utf8');
        for (const [,block] of view.matchAll(/<style(?:\s[^>]*)?>([\s\S]*?)<\/style>/g)) inspect(block, file);
        for (const [,inline] of view.matchAll(/(?<![:\w-])style="([^"]*)"/g)) {
            if (!inline.includes('{{') && !inline.includes('{!!')) inspect(inline, file);
        }
    }
    assert.deepEqual(failures, []);
});

test('nested captions and badges use root-relative text rather than compounding em sizes', () => {
    const screen = postcss.parse(css).nodes.find(node => node.type === 'atrule' && node.name === 'media' && node.params === 'screen');
    let captionSize;
    let badgeSize;
    screen.walkRules(rule => {
        if (rule.selector.includes('small, .small')) captionSize = rule.nodes.find(decl => decl.prop === 'font-size')?.value;
        if (rule.selector === '.badge') badgeSize = rule.nodes.find(decl => decl.prop === '--bs-badge-font-size')?.value;
    });
    assert.equal(pixels(captionSize), 12);
    assert.equal(pixels(badgeSize), 12);
    assert.equal(tokens.get('--bs-body-font-family'), 'var(--bs-font-sans-serif)');
});
