import { build } from 'esbuild';
import { readFileSync, writeFileSync } from 'fs';
import { createHash } from 'crypto';

const pkg = JSON.parse(readFileSync('package.json', 'utf8'));
const version = pkg.version || '0.0.0';

await build({
  entryPoints: ['resources/js/tiptap/index.js'],
  bundle: true,
  format: 'iife',
  globalName: 'PlatformTiptap',
  outfile: 'resources/dist/platform-tiptap.iife.js',
  minify: true,
  sourcemap: false,
  target: ['es2020'],
  loader: { '.css': 'text' },
  define: {
    'process.env.NODE_ENV': '"production"',
  },
  banner: {
    js: `/* platform-tiptap v${version} | MIT */`,
  },
});

// Generate content hash for cache busting
const bundle = readFileSync('resources/dist/platform-tiptap.iife.js');
const hash = createHash('md5').update(bundle).digest('hex').slice(0, 8);

writeFileSync('resources/dist/manifest.json', JSON.stringify({
  'platform-tiptap.iife.js': hash,
}, null, 2) + '\n');

console.log(`Built: resources/dist/platform-tiptap.iife.js (hash: ${hash})`);
