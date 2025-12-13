module.exports = {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.js',
    './app/Filament/**/*.php',
    './core/vendor/filament/**/*.blade.php',
  ],
  // Enable dark mode with class strategy (Filament uses .dark class)
  darkMode: 'class',
  // Safelist classes that are used dynamically or in PHP strings
  safelist: [
    // Background colors with dark variant
    {
      pattern: /^bg-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(50|100|200|300|400|500|600|700|800|900|950)$/,
      variants: ['dark', 'hover', 'dark:hover']
    },
    // Text colors with dark variant
    {
      pattern: /^text-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(50|100|200|300|400|500|600|700|800|900|950)$/,
      variants: ['dark', 'hover', 'dark:hover']
    },
    // Border colors with dark variant
    {
      pattern: /^border-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(50|100|200|300|400|500|600|700|800|900|950)$/,
      variants: ['dark', 'hover']
    },
    // Common utilities
    'flex', 'inline-flex', 'grid', 'hidden', 'block', 'inline-block',
    'items-center', 'items-start', 'items-end', 'justify-center', 'justify-between', 'justify-start', 'justify-end',
    'gap-1', 'gap-2', 'gap-3', 'gap-4', 'gap-5', 'gap-6', 'gap-8',
    'p-1', 'p-2', 'p-3', 'p-4', 'p-5', 'p-6', 'p-8',
    'px-1', 'px-2', 'px-3', 'px-4', 'px-5', 'px-6', 'px-8',
    'py-1', 'py-2', 'py-3', 'py-4', 'py-5', 'py-6', 'py-8',
    'm-1', 'm-2', 'm-3', 'm-4', 'm-5', 'm-6', 'm-8',
    'mx-auto', 'my-auto',
    'rounded', 'rounded-md', 'rounded-lg', 'rounded-xl', 'rounded-full',
    'shadow', 'shadow-sm', 'shadow-md', 'shadow-lg', 'shadow-xl',
    'font-medium', 'font-semibold', 'font-bold',
    'text-xs', 'text-sm', 'text-base', 'text-lg', 'text-xl', 'text-2xl',
    'w-full', 'h-full', 'w-auto', 'h-auto',
    'overflow-hidden', 'overflow-auto', 'overflow-scroll',
  ],
  theme: { extend: {} },
  plugins: [],
}
