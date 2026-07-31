#!/usr/bin/env node
/**
 * Build script: minifies every CSS file at the repo root and every JS file in
 * js/ into sibling .min.css / .min.js files via esbuild (clean-css choked on
 * modern at-rules like @starting-style, silently corrupting the output).
 *
 * The PHP side (gh_asset_url) serves the .min build when it exists and falls
 * back to the source, so a stale or missing build never breaks the site —
 * run `npm run build` after editing assets.
 *
 * Usage: node build.js [css|js]
 */
'use strict';

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = __dirname;
const mode = process.argv[2] || 'all';
const esbuild = path.join(root, 'node_modules', '.bin', 'esbuild');

function listFiles(dir, ext) {
    return fs.readdirSync(dir)
        .filter((f) => f.endsWith(ext) && !f.endsWith('.min' + ext))
        .map((f) => path.join(dir, f));
}

function minify(file, out) {
    execFileSync(esbuild, [file, '--minify', '--outfile=' + out, '--log-level=warning'], { stdio: 'inherit' });
    console.log(`${path.extname(file).slice(1).padEnd(3)} ${path.basename(file)} -> ${path.basename(out)}`);
}

if (mode === 'all' || mode === 'css') {
    for (const file of listFiles(root, '.css')) {
        minify(file, file.replace(/\.css$/, '.min.css'));
    }
}

if (mode === 'all' || mode === 'js') {
    for (const file of listFiles(path.join(root, 'js'), '.js')) {
        minify(file, file.replace(/\.js$/, '.min.js'));
    }
}
