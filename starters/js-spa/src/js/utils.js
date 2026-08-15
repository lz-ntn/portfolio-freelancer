function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('pt-PT');
}

function formatCurrency(value) {
    return Number(value || 0).toLocaleString('pt-PT', {
        style: 'currency',
        currency: 'EUR'
    });
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function showLoading(show = true) {
    let el = document.getElementById('loading-spinner');
    if (show) {
        if (!el) {
            el = document.createElement('div');
            el.id = 'loading-spinner';
            el.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(el);
        }
        el.style.display = 'flex';
    } else if (el) {
        el.style.display = 'none';
    }
}
