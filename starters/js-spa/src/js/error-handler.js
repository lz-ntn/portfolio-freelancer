class AppError extends Error {
    constructor(message, type = 'GENERIC', context = {}) {
        super(message);
        this.type = type;
        this.context = context;
    }
}

class ValidationError extends AppError {
    constructor(message, fields = {}) {
        super(message, 'VALIDATION', { fields });
        this.fields = fields;
    }
}

const ErrorHandler = {
    handle(error, context = '') {
        console.error(`[${error.type || 'ERROR'}] ${context}:`, error.message, error.context);

        if (error instanceof ValidationError) {
            Object.entries(error.fields).forEach(([field, msg]) => {
                const input = document.querySelector(`[name="${field}"]`);
                if (input) {
                    input.classList.add('is-invalid');
                    const feedback = input.nextElementSibling;
                    if (feedback?.classList.contains('invalid-feedback')) {
                        feedback.textContent = msg;
                    }
                }
            });
        }

        showToast(error.message || 'Ocorreu um erro inesperado.', 'error');
    },

    wrap(fn, context) {
        return (...args) => {
            try {
                return fn(...args);
            } catch (error) {
                this.handle(error, context);
            }
        };
    },

    async wrapAsync(fn, context) {
        try {
            return await fn();
        } catch (error) {
            this.handle(error, context);
        }
    }
};
