const navigation = {
    pages: {
        dashboard: { title: 'Dashboard', module: null },
        clientes: { title: 'Clientes', module: 'src/js/modules/clientes.js' },
        produtos: { title: 'Produtos', module: 'src/js/modules/produtos.js' },
    },

    navigateTo(page) {
        showLoading(true);

        // Carregar módulo dinamicamente
        if (this.pages[page]?.module) {
            this.loadModule(this.pages[page].module);
        }

        // Atualizar conteúdo
        history.pushState(null, '', `#${page}`);
        this.renderPage(page);
    },

    loadModule(path) {
        if (document.querySelector(`script[src="${path}"]`)) return;

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = path;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    },

    renderPage(page) {
        const app = document.querySelector('#app');
        const config = this.pages[page];

        app.innerHTML = `<h1>${config.title}</h1><div id="page-content"></div>`;
        showLoading(false);
    }
};

// Listen for popstate
window.addEventListener('popstate', () => {
    const page = location.hash.slice(1) || 'dashboard';
    navigation.renderPage(page);
});
