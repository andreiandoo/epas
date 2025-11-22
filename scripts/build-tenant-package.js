#!/usr/bin/env node

/**
 * Build script for tenant packages
 * Generates obfuscated, tenant-specific JavaScript bundles
 */

import { execSync } from 'child_process';
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'fs';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// Parse arguments
const args = process.argv.slice(2);
const configIndex = args.indexOf('--config');
const outputIndex = args.indexOf('--output');
const obfuscateIndex = args.indexOf('--obfuscate');

if (configIndex === -1 || outputIndex === -1) {
    console.error('Usage: node build-tenant-package.js --config <path> --output <path> [--obfuscate true|false]');
    process.exit(1);
}

const configPath = args[configIndex + 1];
const outputPath = args[outputIndex + 1];
const shouldObfuscate = obfuscateIndex !== -1 ? args[obfuscateIndex + 1] === 'true' : true;

// Read tenant configuration
const config = JSON.parse(readFileSync(configPath, 'utf-8'));

console.log(`Building package for tenant ${config.tenantId}, domain: ${config.domain}`);

// Change to tenant-client directory
const clientDir = resolve(__dirname, '../resources/tenant-client');
process.chdir(clientDir);

// Ensure dependencies are installed
if (!existsSync(resolve(clientDir, 'node_modules'))) {
    console.log('Installing dependencies...');
    execSync('npm install', { stdio: 'inherit' });
}

// Build the package
console.log('Building package...');
const buildMode = shouldObfuscate ? 'production' : 'development';
execSync(`npm run build -- --mode ${buildMode}`, { stdio: 'inherit' });

// Read the built file
const builtFile = resolve(clientDir, 'dist/tixello-loader.iife.js');
if (!existsSync(builtFile)) {
    console.error('Build output not found');
    process.exit(1);
}

let content = readFileSync(builtFile, 'utf-8');

// Inject tenant configuration
const encodedConfig = Buffer.from(JSON.stringify({
    tenantId: config.tenantId,
    domainId: config.domainId,
    domain: config.domain,
    apiEndpoint: config.apiEndpoint,
    modules: config.modules,
    theme: config.theme,
    version: config.version,
    packageHash: config.packageHash,
})).toString('base64');

// Inject configuration at the start
content = `window.__TIXELLO_CONFIG__="${encodedConfig}";${content}`;

// Add domain lock check and anti-tampering
const securityWrapper = `
(function(){
    var d="${config.domain}";
    var h=window.location.hostname;
    if(h!=="localhost"&&h!=="127.0.0.1"&&h!==d&&h!=="www."+d&&!h.endsWith("."+d)){
        console.error("Tixello: Domain mismatch");
        document.body.innerHTML="<div style='padding:20px;text-align:center;'><h1>Invalid License</h1><p>This application is not licensed for this domain.</p></div>";
        throw new Error("Invalid domain");
    }
    // Anti-tampering check
    if(typeof window.__TIXELLO_TAMPER_CHECK__!=="undefined"){
        throw new Error("Tampering detected");
    }
    window.__TIXELLO_TAMPER_CHECK__="${config.packageHash}";
})();
`;

// Add header
const header = \`/**
 * Tixello Event Platform - Tenant Client
 * Domain: \${config.domain}
 * Version: \${config.version}
 * Generated: \${new Date().toISOString()}
 *
 * This code is proprietary and confidential.
 * Unauthorized copying or distribution is prohibited.
 */
\`;

content = header + securityWrapper + content;

// Ensure output directory exists
const outputDir = dirname(outputPath);
if (!existsSync(outputDir)) {
    mkdirSync(outputDir, { recursive: true });
}

// Write to output
writeFileSync(outputPath, content);

const size = Buffer.byteLength(content, 'utf-8');
console.log(`Package built successfully: ${outputPath} (${(size / 1024).toFixed(2)} KB)`);
