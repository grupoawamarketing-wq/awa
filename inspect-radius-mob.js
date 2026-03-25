const puppeteer = require('./node_modules/puppeteer-core/lib/cjs/puppeteer/puppeteer-core.js');
(async () => {
  const browser = await puppeteer.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox','--disable-gpu','--disable-dev-shm-usage'],headless:'new'});
  const page = await browser.newPage();
  await page.setViewport({width:375,height:812});
  await page.goto('https://awamotos.com/?v=radius_check_mob', {waitUntil:'domcontentloaded'});
  const styles = await page.evaluate(() => getComputedStyle(document.querySelector('#search')).borderRadius);
  const btnStyles = await page.evaluate(() => getComputedStyle(document.querySelector('.action.search')).borderRadius);
  console.log('Mobile Search input:', styles);
  console.log('Mobile Search btn:', btnStyles);
  await browser.close();
})();
