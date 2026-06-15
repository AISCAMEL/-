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
        brand: {
          DEFAULT: '#2563eb',
          dark: '#1d4ed8',
          light: '#eaf1ff',
        },
      },
    },
  },
  plugins: [],
};

export default config;
