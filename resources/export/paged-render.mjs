#!/usr/bin/env node

// Minimal example. You will wire in Paged.js properly here.

import fs from 'node:fs';
import path from 'node:path';

// Arguments: node paged-render.mjs input.html output.pdf [jsonOptions]
const [, , inputHtml, outputPdf, rawOptions] = process.argv;

if (!inputHtml || !outputPdf) {
  console.error('Usage: paged-render.mjs <input.html> <output.pdf> [optionsJson]');
  process.exit(1);
}

const options = rawOptions ? JSON.parse(rawOptions) : {};

// TODO: Integrate Paged.js browserless / headless rendering.
// For now, just log so you can see it runs:
console.log('Paged render stub');
console.log('Input:', inputHtml);
console.log('Output:', outputPdf);
console.log('Options:', options);

// In a real implementation:
// - spin up headless Chrome / Playwright
// - load HTML (file:// inputHtml)
// - Paged.js paginates and exports PDF to outputPdf
// For now, just fake a file so PHP side doesn’t explode:
fs.copyFileSync(
  path.resolve(inputHtml),
  path.resolve(outputPdf)
);

process.exit(0);
