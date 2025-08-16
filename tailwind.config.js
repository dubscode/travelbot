/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./templates/**/*.html.twig",
    "./assets/**/*.js",
    "./src/**/*.php"
  ],
  theme: {
    extend: {
      // Minimal extensions - just keep the typing animation
    },
  },
  darkMode: 'media', // Use prefers-color-scheme
  plugins: [],
}