/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f2f6ff',
          100: '#e3ebff',
          200: '#c3d3ff',
          300: '#9db4ff',
          400: '#7089ff',
          500: '#4b63f6',
          600: '#3a47da',
          700: '#2f39ad',
          800: '#293489',
          900: '#242e6d',
        },
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
