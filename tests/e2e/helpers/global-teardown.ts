import { execSync } from 'child_process';

/**
 * Global teardown — roda após todos os testes Playwright.
 * Garante que processos Chrome/Chromium não fiquem órfãos no servidor.
 */
export default async function globalTeardown(): Promise<void> {
  try {
    // Mata qualquer processo Chrome/Chromium remanescente
    execSync('pkill -9 -f "chrome-headless-shell|google-chrome|chromium" 2>/dev/null || true', {
      shell: '/bin/bash',
      stdio: 'ignore',
    });
  } catch {
    // Ignorar erros — pode não haver processos para matar
  }
}
