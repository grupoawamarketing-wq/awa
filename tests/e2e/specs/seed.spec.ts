/**
 * Seed test — AWA Motos E-commerce
 *
 * Propósito: define o ambiente de base para os agentes Playwright (planner, generator, healer).
 * - Navega para a homepage da loja
 * - Aguarda carregamento completo (header, busca, produtos)
 * - Serve como ponto de partida para todos os testes gerados
 *
 * URL: https://awamotos.com
 * Plataforma: Magento 2.4.8-p3 CE
 * Tema: AWA_Custom/ayo_home5_child
 */
import { test, expect } from '@playwright/test';

test.describe('AWA Motos — Seed', () => {
  test('seed', async ({ page }) => {
    // Navegar para a homepage da loja
    await page.goto('https://awamotos.com/');

    // Aguardar carregamento do header
    await page.waitForSelector('.page-header', { state: 'visible' });

    // Confirmar que o logo está visível
    await expect(page.locator('.logo')).toBeVisible();

    // Confirmar que o campo de busca está presente
    await expect(page.locator('#search')).toBeVisible();
  });
});
