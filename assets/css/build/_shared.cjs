// Shared Tailwind config — theme only, no safelist
// Dynamic classes are in dynamic-classes.html (included via content in bundles that need it)
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
  safelist: [],
  plugins: [],
};
