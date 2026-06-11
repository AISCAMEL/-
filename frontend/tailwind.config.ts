import type { Config } from 'tailwindcss';

const config: Config = {
  content: ['./app/**/*.{ts,tsx}', './components/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#1f6feb',
          dark: '#1a5fce',
          light: '#e8f1ff',
        },
      },
    },
  },
  plugins: [],
};

export default config;
