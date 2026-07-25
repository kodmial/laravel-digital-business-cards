import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
        coverage: {
            provider: 'v8',
            include: ['resources/js/**/*.js'],
            exclude: ['resources/js/**/*.test.js'],
            reporter: ['text', 'html'],
            reportsDirectory: 'coverage',
            thresholds: {
                branches: 80,
                functions: 80,
                lines: 80,
                statements: 80,
            },
        },
    },
});
