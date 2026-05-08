import { execSync } from 'child_process';
import path from 'path';

/**
 * Global teardown — roda após todos os testes Playwright.
 * Garante que processos de browser não fiquem órfãos no servidor.
 * Corrige permissões de artefatos criados como root por subprocessos do browser.
 */
export default async function globalTeardown(): Promise<void> {
  // Mata qualquer processo de browser remanescente.
  // IMPORTANTE: usar -x (match no nome do processo) em vez de -f (match no command line)
  // porque -f "firefox" também faz match em "--project=firefox-notebook-1280" do
  // próprio processo Playwright, matando o test runner antes de finalizar.
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
  } catch {
    // Ignorar erros — pode não haver processos para matar
  }

  // Corrige permissões de artefatos criados como root.
  // Sem isso, o próximo run pode falhar com EACCES ao tentar limpar saídas antigas.
  try {
    const uid = process.getuid ? process.getuid() : 1007;
    const gid = process.getgid ? process.getgid() : 1007;
    const resultsDirs = [
      path.resolve(__dirname, '..', 'test-results'),
      path.resolve(__dirname, '..', 'test-results-safe'),
    ];

    for (const resultsDir of resultsDirs) {
      execSync(
        `sudo chown -R ${uid}:${gid} "${resultsDir}" 2>/dev/null || true`,
        { shell: '/bin/bash', stdio: 'ignore' },
      );
    }
  } catch {
    // Melhor esforço — não bloquear o teardown se sudo não disponível
  }
}
