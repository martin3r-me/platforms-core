import { build } from 'esbuild';
import { readFileSync } from 'fs';

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

console.log('Built: resources/dist/platform-tiptap.iife.js');
