document.addEventListener('DOMContentLoaded', () => {
    const page = location.hash.slice(1) || 'dashboard';
    navigation.navigateTo(page);
});
