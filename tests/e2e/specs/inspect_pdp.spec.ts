import { test, expect } from '@playwright/test';

test('Inspect PDP alignment', async ({ page }) => {
  await page.goto('https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html', { waitUntil: 'networkidle' });
  await page.setViewportSize({ width: 1280, height: 1024 });

  const boxMedia = await page.locator('.product.media').boundingBox();
  const boxInfo = await page.locator('.product-info-main').boundingBox();
  const boxTitle = await page.locator('.page-title-wrapper').boundingBox();
  const boxTitleInner = await page.locator('.page-title').boundingBox();
  
  const mediaStyles = await page.evaluate(() => {
    const el = document.querySelector('.product.media');
    const style = window.getComputedStyle(el);
    return { marginTop: style.marginTop, paddingTop: style.paddingTop };
  });

  const infoStyles = await page.evaluate(() => {
    const el = document.querySelector('.product-info-main');
    const style = window.getComputedStyle(el);
    return { marginTop: style.marginTop, paddingTop: style.paddingTop };
  });

  const titleWrapperStyles = await page.evaluate(() => {
    const el = document.querySelector('.page-title-wrapper');
    const style = window.getComputedStyle(el);
    return { marginTop: style.marginTop, paddingTop: style.paddingTop, marginBottom: style.marginBottom };
  });

  const titleStyles = await page.evaluate(() => {
    const el = document.querySelector('.page-title');
    const style = window.getComputedStyle(el);
    return { marginTop: style.marginTop, paddingTop: style.paddingTop, lineHeight: style.lineHeight, fontSize: style.fontSize };
  });

  console.log('BOX Media:', boxMedia);
  console.log('BOX Info:', boxInfo);
  console.log('BOX Title Wrapper:', boxTitle);
  console.log('BOX Title Inner:', boxTitleInner);
  console.log('STYLES Media:', mediaStyles);
  console.log('STYLES Info:', infoStyles);
  console.log('STYLES Title Wrapper:', titleWrapperStyles);
  console.log('STYLES Title Inner:', titleStyles);
});
