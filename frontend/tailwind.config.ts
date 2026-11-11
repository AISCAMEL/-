import type { Config } from 'tailwindcss';

const config: Config = {
  content: ['./app/**/*.{ts,tsx}', './components/**/*.{ts,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Noto Sans JP"', 'system-ui', 'sans-serif'],
        heading: ['"Zen Kaku Gothic New"', '"Noto Sans JP"', 'sans-serif'],
      },
      colors: {
        // 配色は CSS変数で切替（globals.css の :root / html[data-brand=...]）。
        brand: {
          DEFAULT: 'var(--brand)',
          dark: 'var(--brand-dark)',
          light: 'var(--brand-light)',
        },
      },
    },
  },
  plugins: [],
};

export default config;
