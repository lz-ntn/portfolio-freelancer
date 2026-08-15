// Mocks globais para testes
global.showToast = () => {};
global.showLoading = () => {};
global.navigation = { navigateTo: () => {}, loadModule: () => Promise.resolve() };

// Mock localStorage
const store = {};
Object.defineProperty(window, 'localStorage', {
    value: {
        getItem: (key) => store[key] ?? null,
        setItem: (key, val) => { store[key] = String(val); },
        removeItem: (key) => { delete store[key]; },
        clear: () => { Object.keys(store).forEach(k => delete store[k]); },
    },
    writable: true,
});
