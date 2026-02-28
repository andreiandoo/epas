// Shared Tailwind config — imported by all per-bundle configs
module.exports = {
  theme: {
    extend: {
      fontFamily: {
        'sans': ['Plus Jakarta Sans', 'sans-serif']
      },
      colors: {
        'primary': '#A51C30',
        'primary-dark': '#8B1728',
        'primary-light': '#C41E3A',
        'secondary': '#1E293B',
        'accent': '#E67E22',
        'surface': '#F8FAFC',
        'muted': '#64748B',
        'border': '#E2E8F0',
        'success': '#10B981',
        'warning': '#F59E0B',
        'error': '#EF4444',
      }
    },
    screens: {
      mobile: { max: "768px" },
      xs: "480px",
      sm: "600px",
      md: "782px",
      lg: "1024px",
      xl: "1280px",
      "2xl": "1440px",
    },
  },
  safelist: [
    // Grid columns used dynamically
    'grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4', 'grid-cols-5',
    'lg:grid-cols-2', 'lg:grid-cols-3', 'lg:grid-cols-4', 'lg:grid-cols-5',
    'md:grid-cols-2', 'md:grid-cols-3', 'md:grid-cols-4',
    'xl:grid-cols-5',
    // Common dynamic widths
    'w-1/2', 'w-1/3', 'w-1/4', 'w-2/3', 'w-3/4', 'w-full',
    'md:w-1/4', 'md:w-1/3', 'md:w-1/2', 'md:w-2/5', 'md:w-3/5', 'md:w-3/4',
    // Dynamic opacity
    'opacity-0', 'opacity-100',
    // Aspect ratios
    'aspect-square', 'aspect-video',
    // Common gap values
    'gap-2', 'gap-3', 'gap-4', 'gap-5', 'gap-6', 'gap-8',
    // Spacing used in JS
    'p-2', 'p-3', 'p-4', 'p-6', 'p-8',
    'px-2', 'px-3', 'px-4', 'px-6', 'px-8',
    'py-1', 'py-2', 'py-3', 'py-4', 'py-6', 'py-8',
    'mb-2', 'mb-3', 'mb-4', 'mb-6', 'mb-8',
    'mt-2', 'mt-4', 'mt-6', 'mt-8', 'mt-18',
    // Height values
    'h-32', 'h-40', 'h-52', 'h-64', 'h-72', 'h-96',
    // Text sizes used in JS
    'text-xs', 'text-sm', 'text-base', 'text-lg', 'text-xl', 'text-2xl', 'text-3xl', 'text-4xl',
    // Font weights
    'font-medium', 'font-semibold', 'font-bold', 'font-extrabold',
    // Color variants used in JS
    'text-primary', 'text-secondary', 'text-muted', 'text-white', 'text-success', 'text-error',
    'bg-primary', 'bg-secondary', 'bg-surface', 'bg-white', 'bg-success', 'bg-error',
    'border-primary', 'border-border', 'border-secondary',
    // Hover states from JS
    'hover:text-primary', 'hover:bg-primary', 'hover:border-primary',
    'hover:bg-surface', 'hover:bg-gray-200', 'hover:bg-gray-50',
    'hover:text-white', 'hover:scale-105', 'hover:scale-110',
    // Transitions
    'transition-all', 'transition-colors', 'transition-transform', 'transition-opacity',
    'duration-200', 'duration-300', 'duration-500', 'duration-700',
    // Rounded
    'rounded-lg', 'rounded-xl', 'rounded-2xl', 'rounded-3xl', 'rounded-full',
    // Position
    'absolute', 'relative', 'fixed', 'sticky',
    // Display
    'hidden', 'block', 'flex', 'grid', 'inline-flex',
    // Flex
    'flex-col', 'flex-row', 'flex-wrap', 'flex-shrink-0', 'flex-1',
    'items-center', 'items-start', 'items-end',
    'justify-center', 'justify-between', 'justify-start',
    // Overflow
    'overflow-hidden', 'overflow-x-auto', 'overflow-y-auto',
    // Object
    'object-cover', 'object-contain',
    // Border width
    'border', 'border-t', 'border-b', 'border-l', 'border-r',
    // Z-index
    'z-10', 'z-20', 'z-30', 'z-40', 'z-50',
    // Line clamp
    'line-clamp-1', 'line-clamp-2', 'line-clamp-3',
    'truncate',
    // Max width
    'max-w-none', 'max-w-md', 'max-w-7xl',
    // Mobile prefix classes
    'mobile:hidden', 'mobile:block', 'mobile:flex', 'mobile:p-0', 'mobile:px-0', 'mobile:px-4',
    'mobile:py-6', 'mobile:mb-0', 'mobile:mb-8', 'mobile:mt-18',
    'mobile:rounded-none', 'mobile:border-0', 'mobile:border-b', 'mobile:border-border',
    'mobile:bg-transparent', 'mobile:justify-center', 'mobile:flex-col',
    'mobile:h-40',
    // Gradient classes used in JS classList
    'from-primary', 'to-primary-light', 'from-gray-600', 'to-gray-700',
    // Hover with bg-primary-dark
    'hover:bg-primary-dark', 'hover:bg-white/30',
    // Visibility
    'invisible', 'visible',
    // Gap-10 used in JS
    'gap-10',
    // Arbitrary values used in templates
    {
      pattern: /bg-(primary|secondary|accent|surface|muted|border|success|warning|error|white)\/(5|10|20|30|40|50|60|70|80|90)/,
    },
    {
      pattern: /text-(primary|secondary|accent|surface|muted|white)\/(50|60|70|80|90)/,
    },
  ],
  plugins: [],
};
