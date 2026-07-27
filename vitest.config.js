import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
        coverage: {
            provider: 'v8',
            include: ['resources/js/**/*.js'],
            exclude: ['resources/js/**/*.test.js', 'resources/js/alpine.js'],
            reporter: ['text', 'html'],
            reportsDirectory: 'coverage',
            thresholds: {
                branches: 75,
                functions: 95,
                lines: 88,
                statements: 89,
            },
        },
    },
});
