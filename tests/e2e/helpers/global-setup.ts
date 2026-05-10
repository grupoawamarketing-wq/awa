import { execSync } from 'child_process';

/**
 * Global setup — roda antes de todos os testes Playwright.
 * Mata processos de browsers órfãos de execuções anteriores para evitar
 * instabilidade causada por processos zombie consumindo memória.
 *
 * IMPORTANTE: usa -x (match exato no NOME do processo) em vez de -f (match no
 * command line completo), para não matar o próprio processo Playwright quando
 * invocado com --project firefox-mobile-390 (que contém "firefox" nos args).
 */
export default async function globalSetup(): Promise<void> {
  try {
    execSync(
      'pkill -9 -x "chrome-headless-shell" 2>/dev/null || true; '
      + 'pkill -9 -x "firefox" 2>/dev/null || true; '
      + 'pkill -9 -x "firefox-bin" 2>/dev/null || true; '
      + 'pkill -9 -x "firefox-esr" 2>/dev/null || true; '
      + 'pkill -9 -x "google-chrome" 2>/dev/null || true; '
      + 'pkill -9 -x "chromium" 2>/dev/null || true; '
      + 'pkill -9 -x "chromium-browser" 2>/dev/null || true; '
      + 'pkill -9 -f "Web Content" 2>/dev/null || true',
      { shell: '/bin/bash', stdio: 'ignore' },
    );
    // Small delay to let OS reclaim resources from killed processes
    await new Promise(resolve => setTimeout(resolve, 500));
  } catch {
    // Ignore — no leftover processes is fine
  }
}
