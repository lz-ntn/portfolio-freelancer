/**
 * Store genérico — sincroniza com localStorage
 * Subsistituir sync*() por chamadas REST API no projeto real
 */
class Store {
    constructor(prefix = 'app_') {
        this.prefix = prefix;
    }

    getAll(key) {
        try {
            return JSON.parse(localStorage.getItem(this.prefix + key)) || [];
        } catch {
            return [];
        }
    }

    getById(key, id) {
        return this.getAll(key).find(item => item.id === id) || null;
    }

    save(key, data) {
        const items = this.getAll(key);
        if (data.id) {
            const idx = items.findIndex(i => i.id === data.id);
            if (idx >= 0) items[idx] = { ...items[idx], ...data };
            else items.push({ ...data, id: Date.now().toString() });
        } else {
            items.push({ ...data, id: Date.now().toString() });
        }
        localStorage.setItem(this.prefix + key, JSON.stringify(items));
        return data;
    }

    remove(key, id) {
        const items = this.getAll(key).filter(i => i.id !== id);
        localStorage.setItem(this.prefix + key, JSON.stringify(items));
    }
}

const store = new Store();
