const puppeteer = require('./node_modules/puppeteer-core/lib/cjs/puppeteer/puppeteer-core.js');
(async () => {
  const browser = await puppeteer.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox','--disable-gpu','--disable-dev-shm-usage'],headless:'new'});
  const page = await browser.newPage();
  
  await page.setViewport({width:1440,height:900});
  await page.goto('https://awamotos.com/?v=radius_check_real_css', {waitUntil:'domcontentloaded'});

  const styles = await page.evaluate(() => {
    const s = document.querySelector('#search');
    return s ? getComputedStyle(s).borderRadius : 'not found';
  });

  console.log('Search input border-radius is:', styles);

  const btnStyles = await page.evaluate(() => {
    const s = document.querySelector('.action.search');
    return s ? getComputedStyle(s).borderRadius : 'not found';
  });
  console.log('Search button border-radius is:', btnStyles);

  await browser.close();
})();
