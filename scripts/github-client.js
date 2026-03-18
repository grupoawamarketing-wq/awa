#!/usr/bin/env node
// =============================================================================
// github-client.js — Cliente GitHub API com retry + backoff exponencial
// Uso: node scripts/github-client.js
// Requer: GITHUB_TOKEN no ambiente
// =============================================================================
'use strict';

const https = require('https');

const GITHUB_TOKEN = process.env.GITHUB_TOKEN || process.env.GH_TOKEN;
const GITHUB_API_BASE = 'api.github.com';
const MAX_RETRIES = 3;

if (!GITHUB_TOKEN) {
    console.error('ERRO: Defina GITHUB_TOKEN no ambiente');
    console.error('  export GITHUB_TOKEN="ghp_seu_token"');
    process.exit(1);
}

/**
 * Faz requisição à API do GitHub com retry + backoff exponencial.
 * @param {string} path     - Endpoint (ex: '/rate_limit', '/user')
 * @param {object} options  - { method, body }
 * @param {number} attempt  - Tentativa atual (interno)
 * @returns {Promise<object>}
 */
async function githubRequest(path, options = {}, attempt = 1) {
    const { method = 'GET', body = null } = options;
    const backoff = Math.pow(2, attempt - 1) * 1000; // 1s, 2s, 4s

    return new Promise((resolve, reject) => {
        const reqOptions = {
            hostname: GITHUB_API_BASE,
            path,
            method,
            headers: {
                'Authorization': `Bearer ${GITHUB_TOKEN}`,
                'Accept': 'application/vnd.github+json',
                'X-GitHub-Api-Version': '2022-11-28',
                'User-Agent': 'awamotos-magento2/1.0',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
        };

        const req = https.request(reqOptions, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', async () => {
                const status = res.statusCode;

                if (status === 200 || status === 201 || status === 204) {
                    try {
                        resolve(data ? JSON.parse(data) : {});
                    } catch {
                        resolve(data);
                    }
                    return;
                }

                if ((status === 403 || status === 429) && attempt <= MAX_RETRIES) {
                    // Checar header Retry-After ou X-RateLimit-Reset
                    const retryAfter = res.headers['retry-after'];
                    const resetAt    = res.headers['x-ratelimit-reset'];
                    const waitMs = retryAfter
                        ? parseInt(retryAfter) * 1000
                        : resetAt
                        ? Math.max(0, (parseInt(resetAt) * 1000) - Date.now())
                        : backoff;

                    const waitSec = Math.ceil(waitMs / 1000);
                    console.warn(`[Rate Limit] HTTP ${status} — aguardando ${waitSec}s (tentativa ${attempt}/${MAX_RETRIES})`);
                    await sleep(Math.min(waitMs, 60000)); // cap: 60s
                    return githubRequest(path, options, attempt + 1).then(resolve).catch(reject);
                }

                if (status >= 500 && attempt <= MAX_RETRIES) {
                    console.warn(`[Retry] HTTP ${status} — aguardando ${backoff / 1000}s (tentativa ${attempt}/${MAX_RETRIES})`);
                    await sleep(backoff);
                    return githubRequest(path, options, attempt + 1).then(resolve).catch(reject);
                }

                const err = new Error(`GitHub API ${status}: ${data}`);
                err.status = status;
                reject(err);
            });
        });

        req.on('error', async (err) => {
            if (attempt <= MAX_RETRIES) {
                console.warn(`[Network] Erro: ${err.message} — retry em ${backoff / 1000}s`);
                await sleep(backoff);
                return githubRequest(path, options, attempt + 1).then(resolve).catch(reject);
            }
            reject(err);
        });

        if (body) req.write(JSON.stringify(body));
        req.end();
    });
}

/** Retorna o rate limit atual. */
async function getRateLimit() {
    return githubRequest('/rate_limit');
}

/** Retorna dados do usuário autenticado. */
async function getCurrentUser() {
    return githubRequest('/user');
}

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

// ── Demo ──────────────────────────────────────────────────────────────────────
(async () => {
    try {
        const [user, limits] = await Promise.all([getCurrentUser(), getRateLimit()]);
        const r = limits.rate;
        const reset = new Date(r.reset * 1000).toLocaleTimeString('pt-BR');
        console.log(`Autenticado como: ${user.login} (${user.name})`);
        console.log(`Rate limit: ${r.remaining}/${r.limit} (reset ${reset})`);
    } catch (err) {
        console.error('Erro:', err.message);
        process.exit(1);
    }
})();

module.exports = { githubRequest, getRateLimit, getCurrentUser };
