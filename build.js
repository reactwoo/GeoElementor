const esbuild = require('esbuild');
const fs = require('fs');

const isWatch = process.argv.includes('--watch');

// Ensure output directory exists
const outputDir = 'assets/js/dashboard';
if (!fs.existsSync(outputDir)) {
  fs.mkdirSync(outputDir, { recursive: true });
}

// Build configuration - ultra lightweight
const buildOptions = {
  entryPoints: ['src/dashboard.js'],
  bundle: true,
  minify: true,
  sourcemap: false,
  target: 'es2015',
  outfile: `${outputDir}/dashboard.js`,
  format: 'iife',
  globalName: 'GeoElementorDashboard',
  treeShaking: true,
  drop: ['console', 'debugger']
};

// CSS processing - basic minification
function buildCSS() {
  const cssContent = fs.readFileSync('src/dashboard.css', 'utf8');
  const minifiedCSS = cssContent
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\s+/g, ' ')
    .replace(/;\s*}/g, '}')
    .replace(/:\s+/g, ':')
    .replace(/,\s+/g, ',')
    .trim();

  fs.writeFileSync(`${outputDir}/dashboard.css`, minifiedCSS);
  console.log('🎨 CSS minified');
  return minifiedCSS.length;
}

async function buildJSOnce() {
  await esbuild.build(buildOptions);
}

async function watchJS() {
  const ctx = await esbuild.context(buildOptions);
  await ctx.watch();
  console.log('👀 Watching for changes...');
}

async function run() {
  // Always (re)build CSS once on startup
  const cssBytes = buildCSS();

  if (isWatch) {
    await watchJS();
  } else {
    try {
      await buildJSOnce();
      const jsStats = fs.statSync(`${outputDir}/dashboard.js`);
      const jsSizeKb = (jsStats.size / 1024).toFixed(2);
      const cssSizeKb = (cssBytes / 1024).toFixed(2);
      const totalKb = (parseFloat(jsSizeKb) + parseFloat(cssSizeKb)).toFixed(2);
      console.log('✅ Dashboard built successfully!');
      console.log(`📁 JS: ${outputDir}/dashboard.js (${jsSizeKb}KB)`);
      console.log(`📁 CSS: ${outputDir}/dashboard.css (${cssSizeKb}KB)`);
      console.log(`📊 Total: ${totalKb}KB`);
    } catch (err) {
      console.error('❌ Build failed:', err);
      process.exit(1);
    }
  }
}

run();