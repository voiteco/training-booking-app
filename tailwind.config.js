/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./assets/js/**/*.{vue,js}",
        "./templates/**/*.{html,twig}"
    ],
    theme: {
        extend: {
            colors: {
                'primary': '#4F46E5',
                'secondary': '#10B981',
            },
        },
    },
    plugins: [],
}