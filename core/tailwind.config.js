module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './app/Filament/**/*.php',                    // resurse/pagini/Widgets PHP
    './resources/views/filament/**/*.blade.php',  // blade-uri custom (ex: widget-ul tău)
    './vendor/filament/**/*.blade.php',
  ],
  theme: { extend: {} },
  plugins: [],
}
