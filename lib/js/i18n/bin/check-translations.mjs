#!/usr/bin/env node
import {existsSync, readFileSync} from 'node:fs';
import {dirname, join, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..');
const untranslatedMarker = '__STRING_NOT_TRANSLATED__';
const placeholderPattern = /\{\{\s*[^}]+?\s*\}\}|%[a-zA-Z_]+%|\$t\([^)]*\)|<\/?[a-zA-Z][^>]*>/g;

const i18nextSet = (name, dir) => ({
    name,
    files: locale => [join(dir, `${locale}.json`)],
    parse: text => flatten(JSON.parse(text)),
    pluralSeparator: null,
    duplicateTopLevelKeys: text => duplicates([...text.matchAll(/^ {4}"([^"]+)":/gm)].map(m => m[1])),
});

const symfonySet = (name, dir, domains) => ({
    name,
    files: locale => domains.map(domain => join(dir, `${domain}.${locale}.yaml`)),
    parse: (text, file) => prefixKeys(parseYaml(text), `${domainOf(file)}:`),
    pluralSeparator: '|',
    duplicateTopLevelKeys: text => duplicates([...text.matchAll(/^(\S[^:]*):/gm)].map(m => m[1])),
});

const sets = [
    i18nextSet('databox-client', 'databox/client/translations'),
    i18nextSet('expose-client', 'expose/client/translations'),
    i18nextSet('uploader-client', 'uploader/client/translations'),
    i18nextSet('dashboard-client', 'dashboard/client/translations'),
    i18nextSet('lib-phrasea-framework', 'lib/js/phrasea-framework/translations'),
    i18nextSet('lib-api', 'lib/js/api/translations'),
    i18nextSet('lib-navigation', 'lib/js/navigation/translations'),
    i18nextSet('lib-phrasea-ui', 'lib/js/phrasea-ui/translations'),
    i18nextSet('lib-react-auth', 'lib/js/react-auth/translations'),
    i18nextSet('lib-react-form', 'lib/js/react-form/translations'),
    symfonySet('databox-api', 'databox/api/translations', ['messages', 'notifications', 'validators']),
    symfonySet('uploader-api', 'uploader/api/translations', ['notifications']),
    symfonySet('expose-api', 'expose/api/translations', ['notifications']),
    symfonySet('admin-bundle', 'lib/php/admin-bundle/Resources/translations', ['admin']),
];

function flatten(tree, prefix = '') {
    return Object.entries(tree).reduce((flat, [key, value]) => {
        const path = `${prefix}${key}`;
        return typeof value === 'object' && value !== null
            ? {...flat, ...flatten(value, `${path}.`)}
            : {...flat, [path]: String(value)};
    }, {});
}

function unquote(raw) {
    if (raw.startsWith("'")) return raw.slice(1, -1).replaceAll("''", "'");
    if (raw.startsWith('"')) return JSON.parse(raw);
    return raw;
}

function parseYaml(text) {
    const path = [];
    return Object.fromEntries(text.split('\n').flatMap(line => {
        const match = line.match(/^(\s*)([^\s#][^:]*):(?:\s+(.*))?$/);
        if (!match) return [];
        const [, indent, key, rawValue] = match;
        while (path.length && path.at(-1).indent >= indent.length) path.pop();
        path.push({indent: indent.length, key});
        return rawValue === undefined ? [] : [[path.map(p => p.key).join('.'), unquote(rawValue.trim())]];
    }));
}

const domainOf = file => file.split('/').pop().split('.')[0];
const prefixKeys = (flat, prefix) => Object.fromEntries(Object.entries(flat).map(([k, v]) => [`${prefix}${k}`, v]));
const duplicates = list => [...new Set(list.filter((item, i) => list.indexOf(item) !== i))];

const placeholders = value => [...value.matchAll(placeholderPattern)].map(m => m[0].replace(/\s+/g, '')).sort().join(' ');
const pluralForms = (value, separator) => (separator ? value.split(separator).length : 1);

function load(set, locale) {
    return Object.assign({}, ...set.files(locale).map(file => {
        const absolute = join(repoRoot, file);
        return existsSync(absolute) ? set.parse(readFileSync(absolute, 'utf8'), file) : {};
    }));
}

function compare(set, en, locale) {
    const translated = load(set, locale);
    const shared = Object.keys(en).filter(key => key in translated);
    return {
        locale,
        total: Object.keys(translated).length,
        missing: Object.keys(en).filter(key => !(key in translated)),
        extra: Object.keys(translated).filter(key => !(key in en)),
        placeholders: shared.filter(key => placeholders(en[key]) !== placeholders(translated[key])
            || pluralForms(en[key], set.pluralSeparator) !== pluralForms(translated[key], set.pluralSeparator)),
        markers: Object.keys(translated).filter(key => translated[key].includes(untranslatedMarker)),
    };
}

function sourceDuplicates(set) {
    return set.files('en')
        .filter(file => existsSync(join(repoRoot, file)))
        .flatMap(file => set.duplicateTopLevelKeys(readFileSync(join(repoRoot, file), 'utf8')).map(key => `${file}: ${key}`));
}

function table(rows) {
    const columns = Object.keys(rows[0]);
    const width = columns.map(c => Math.max(c.length, ...rows.map(r => String(r[c]).length)));
    const line = cells => cells.map((cell, i) => String(cell).padEnd(width[i])).join('  ');
    return [line(columns), line(width.map(w => '-'.repeat(w))), ...rows.map(r => line(columns.map(c => r[c])))].join('\n');
}

function report(set, locales, verbose) {
    const en = load(set, 'en');
    const results = locales.filter(l => l !== 'en').map(locale => compare(set, en, locale));
    console.log(`\n${set.name} (${Object.keys(en).length} en leaves)`);
    console.log(table(results.map(r => ({
        locale: r.locale,
        total: r.total,
        missing: r.missing.length,
        extra: r.extra.length,
        placeholders: r.placeholders.length,
        markers: r.markers.length,
    }))));
    sourceDuplicates(set).forEach(dup => console.log(`  warning: duplicate top-level key in ${dup}`));
    if (verbose) {
        results.forEach(r => ['missing', 'placeholders', 'markers'].forEach(kind =>
            r[kind].forEach(key => console.log(`  ${r.locale} ${kind}: ${key}`))));
    }
    return results;
}

function main(argv) {
    const option = (flag, fallback) => argv.find(a => a.startsWith(`${flag}=`))?.slice(flag.length + 1) ?? fallback;
    const locales = option('--locales', 'en,fr,es,de,pt').split(',');
    const strict = argv.includes('--strict');
    const verbose = argv.includes('--verbose');
    const results = sets.flatMap(set => report(set, locales, verbose));
    const failed = results.some(r => r.missing.length || r.placeholders.length || (strict && (r.extra.length || r.markers.length)));
    process.exit(failed ? 1 : 0);
}

main(process.argv.slice(2));
