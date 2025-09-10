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
const cssContent = fs.readFileSync('src/dashboard.css', 'utf8');
const minifiedCSS = cssContent
  .replace(/\/\*[\s\S]*?\*\//g, '') // Remove comments
  .replace(/\s+/g, ' ') // Minify whitespace
  .replace(/;\s*}/g, '}') // Remove semicolons before closing braces
  .replace(/:\s+/g, ':') // Remove space after colons
  .replace(/,\s+/g, ',') // Remove space after commas
  .trim();

fs.writeFileSync(`${outputDir}/dashboard.css`, minifiedCSS);

console.log('🎨 CSS minified');

if (isWatch) {
  console.log('👀 Watching for changes...');
  esbuild.build({
    ...buildOptions,
    watch: {
      onRebuild(error, result) {
        if (error) console.error('❌ Build failed:', error);
        else console.log('✅ Rebuild completed');
      },
    },
  });
} else {
  esbuild.build(buildOptions).then((result) => {
    const jsSize = (result.outputFiles[0].contents.length / 1024).toFixed(2);
    const cssSize = (minifiedCSS.length / 1024).toFixed(2);
    
    console.log('✅ Dashboard built successfully!');
    console.log(`📁 JS: ${outputDir}/dashboard.js (${jsSize}KB)`);
    console.log(`📁 CSS: ${outputDir}/dashboard.css (${cssSize}KB)`);
    console.log(`📊 Total: ${(parseFloat(jsSize) + parseFloat(cssSize)).toFixed(2)}KB`);
  }).catch(() => process.exit(1));
}