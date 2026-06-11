import { defineConfig } from 'vite';
import { resolve } from 'path';
import obfuscatorPlugin from 'rollup-plugin-obfuscator';

export default defineConfig(({ mode }) => {
    const isProduction = mode === 'production';
    // Set to false to skip obfuscation during development (saves ~8 min build time)
    const enableObfuscation = false; // Change to: isProduction && !process.env.DEV_BUILD for production

    return {
        build: {
            lib: {
                entry: resolve(__dirname, 'src/index.ts'),
                name: 'TixelloLoader',
                fileName: 'tixello-loader',
                formats: ['iife'],
            },
            outDir: 'dist',
            minify: 'terser',
            terserOptions: {
                compress: {
                    drop_console: isProduction,
                    drop_debugger: isProduction,
                    pure_funcs: isProduction ? ['console.log', 'console.info'] : [],
                },
                mangle: {
                    properties: false,
                },
                format: {
                    comments: false,
                },
            },
            rollupOptions: {
                output: {
                    inlineDynamicImports: true,
                },
                plugins: enableObfuscation ? [
                    obfuscatorPlugin({
                        global: true,
                        options: {
                            compact: true,
                            controlFlowFlattening: true,
                            controlFlowFlatteningThreshold: 0.75,
                            deadCodeInjection: true,
                            deadCodeInjectionThreshold: 0.4,
                            debugProtection: false,
                            disableConsoleOutput: true,
                            identifierNamesGenerator: 'hexadecimal',
                            log: false,
                            numbersToExpressions: true,
                            renameGlobals: false,
                            selfDefending: true,
                            simplify: true,
                            splitStrings: true,
                            splitStringsChunkLength: 10,
                            stringArray: true,
                            stringArrayCallsTransform: true,
                            stringArrayCallsTransformThreshold: 0.75,
                            stringArrayEncoding: ['base64'],
                            stringArrayIndexShift: true,
                            stringArrayRotate: true,
                            stringArrayShuffle: true,
                            stringArrayWrappersCount: 2,
                            stringArrayWrappersChainedCalls: true,
                            stringArrayWrappersParametersMaxCount: 4,
                            stringArrayWrappersType: 'function',
                            stringArrayThreshold: 0.75,
                            transformObjectKeys: true,
                            unicodeEscapeSequence: false,
                        },
                    }),
                ] : [],
            },
        },
        resolve: {
            alias: {
                '@': resolve(__dirname, 'src'),
            },
        },
    };
});
