const fs = require('fs');
const html = fs.readFileSync('homepage.html', 'utf8');
const match = html.match(/<link[^>]*>/g);
console.log("Total link tags:", match ? match.length : 0);
