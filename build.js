#!/usr/bin/env node
/**
 * Build script: minifies every CSS file at the repo root and every JS file in
 * js/ into sibling .min.css / .min.js files. The PHP side (gh_asset_url)
 * serves the .min build when it exists and falls back to the source, so a
 * stale or missing build never breaks the site — run `npm run build` after
 * editing assets.
 *
 * Usage: node build.js [css|js]
 */
'use strict';

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = __dirname;
const mode = process.argv[2] || 'all';

function listFiles(dir, ext) {
    return fs.readdirSync(dir)
        .filter((f) => f.endsWith(ext) && !f.endsWith('.min' + ext))
        .map((f) => path.join(dir, f));
}

function run(bin, args) {
    execFileSync(path.join(root, 'node_modules', '.bin', bin), args, { stdio: 'inherit' });
}

if (mode === 'all' || mode === 'css') {
    for (const file of listFiles(root, '.css')) {
        const out = file.replace(/\.css$/, '.min.css');
        run('cleancss', ['-o', out, file]);
        console.log(`css  ${path.basename(file)} -> ${path.basename(out)}`);
    }
}

if (mode === 'all' || mode === 'js') {
    const jsDir = path.join(root, 'js');
    for (const file of listFiles(jsDir, '.js')) {
        const out = file.replace(/\.js$/, '.min.js');
        run('terser', [file, '--compress', '--mangle', '-o', out]);
        console.log(`js   ${path.basename(file)} -> ${path.basename(out)}`);
    }
}
