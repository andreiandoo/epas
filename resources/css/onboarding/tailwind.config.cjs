// Doar pentru pagina de înscriere (/inregistrare); restul aplicației are configul ei
module.exports = {
    content: ['./resources/views/onboarding/signup.blade.php', './resources/views/onboarding/signup/**/*.blade.php'],
    theme: { extend: { fontFamily: { sans: ['Geist', 'system-ui', 'sans-serif'] } } },
};
