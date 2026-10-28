import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build } from 'esbuild';

const __dirname = dirname(fileURLToPath(import.meta.url));
const docsRoot = resolve(__dirname, '..');
const repoRoot = resolve(docsRoot, '../..');
const previewsDir = resolve(docsRoot, 'public/previews');
const cacheDir = resolve(docsRoot, '.vitepress/cache');
const manifestPath = resolve(docsRoot, '.vitepress/previews.json');
const compileScriptPath = resolve(repoRoot, 'scripts/compile-docs-examples.php');
const vendorAutoloadPath = resolve(repoRoot, 'vendor/autoload.php');

mkdirSync(previewsDir, { recursive: true });
mkdirSync(cacheDir, { recursive: true });

if (existsSync(compileScriptPath) && existsSync(vendorAutoloadPath)) {
  try {
    execFileSync('php', [compileScriptPath], { cwd: repoRoot, stdio: 'inherit' });
  } catch (err) {
    if (!existsSync(manifestPath)) {
      writeFileSync(manifestPath, '{}\n', 'utf8');
    } else {
      throw err;
    }
  }
} else if (!existsSync(manifestPath)) {
  writeFileSync(manifestPath, '{}\n', 'utf8');
}

const foundationsDir = resolve(repoRoot, 'vendor/anousss007/blatui/stubs/foundations');
const foundationsCssPath = resolve(foundationsDir, 'app.css');
const foundationsCoreJsPath = resolve(foundationsDir, 'blatui-core.js');

if (existsSync(foundationsCssPath)) {
  const rawCss = readFileSync(foundationsCssPath, 'utf8');
  const rewrittenCss = rawCss.replace(
    /@source\s+['"][^'"]+['"];/g,
    '',
  );

  const sourcesBlock = [
    `@source "${resolve(repoRoot, 'vendor/anousss007/blatui/stubs/ui')}/*.blade.php";`,
    `@source "${resolve(repoRoot, 'vendor/anousss007/blatui/stubs/block')}/*.blade.php";`,
    `@source "${resolve(repoRoot, 'vendor/mallardduck/blade-lucide-icons/resources/svg')}/*.svg";`,
    `@source "${resolve(repoRoot, 'vendor/laravel/framework/src/Illuminate/Pagination/resources/views')}/*.blade.php";`,
    `@source "${resolve(repoRoot, 'resources/views')}/**/*.blade.php";`,
    `@source "${resolve(docsRoot, 'examples')}/*.blade.php";`,
    `@source "${previewsDir}/*.html";`,
  ].join('\n');

  const inputCss = rewrittenCss.replace(
    "@import 'tailwindcss';",
    `@import 'tailwindcss';\n${sourcesBlock}`,
  );

  const tempCssInput = resolve(cacheDir, 'preview-input.css');
  writeFileSync(tempCssInput, inputCss, 'utf8');

  const tailwindBin = resolve(
    docsRoot,
    'node_modules/.bin',
    process.platform === 'win32' ? 'tailwindcss.cmd' : 'tailwindcss',
  );

  execFileSync(
    tailwindBin,
    ['-i', tempCssInput, '-o', resolve(previewsDir, 'preview.css'), '--minify'],
    { cwd: docsRoot, stdio: 'inherit' },
  );

  rmSync(tempCssInput, { force: true });
}

if (existsSync(foundationsCoreJsPath)) {
  const tempJsEntry = resolve(cacheDir, 'preview-entry.js');
  writeFileSync(
    tempJsEntry,
    [
      `import Alpine from 'alpinejs';`,
      `import { registerBlatUI } from ${JSON.stringify(foundationsCoreJsPath)};`,
      `window.Alpine = Alpine;`,
      `registerBlatUI(Alpine, { darkMode: false });`,
      `Alpine.start();`,
    ].join('\n'),
    'utf8',
  );

  await build({
    entryPoints: [tempJsEntry],
    outfile: resolve(previewsDir, 'preview.js'),
    bundle: true,
    format: 'esm',
    minify: true,
    platform: 'browser',
    nodePaths: [resolve(docsRoot, 'node_modules')],
  });

  rmSync(tempJsEntry, { force: true });
}
