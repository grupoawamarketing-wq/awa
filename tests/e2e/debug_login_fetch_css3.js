const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('https://awamotos.com/customer/account/login/');
  const size = await page.evaluate(() => {
    const el = document.querySelector('#b2b-email');
    // Try manually adding the class/id to a dummy element
    const div = document.createElement('div');
    div.innerHTML = '<input id="b2b-email" class="input-text">';
    document.body.appendChild(div);
    const s = window.getComputedStyle(div.firstChild).fontSize;
    document.body.removeChild(div);
    return { actual: window.getComputedStyle(el).fontSize, dummy: s };
  });
  console.log(size);
  await browser.close();
})();
