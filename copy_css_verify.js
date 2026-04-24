#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const SOURCE = "/home/jessessh/htdocs/srv1113343.hstgr.cloud/app/design/frontend/AWA_Custom/ayo_home5_child/web/css";
const DESTA = "/home/user/htdocs/srv1113343.hstgr.cloud/app/design/frontend/AWA_Custom/ayo_home5_child/web/css";
const DESTB = "/home/user/htdocs/srv1113343.hstgr.cloud/pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css";

const FILES = [
  "awa-global-components.css",
  "awa-bugfixes-v2.css",
  "awa-extra-pages.css",
  "awa-checkout-cart.css",
  "awa-customer-pages.css",
  "awa-cms-404.css",
  "awa-mobile-polish.css",
  "awa-animations-a11y.css",
  "awa-final-polish.css",
  "b2b/shoppinglist.css",
];

function computeSha256(filePath) {
  const hash = crypto.createHash('sha256');
  const data = fs.readFileSync(filePath);
  hash.update(data);
  return hash.digest('hex');
}

console.log("=" . repeat(100));
console.log("STEP 1: CREATE DIRECTORIES");
console.log("=" .repeat(100));

try {
  fs.mkdirSync(path.join(DESTA, "b2b"), { recursive: true });
  console.log(`✓ Created: ${DESTA}/b2b`);
} catch (e) {
  console.log(`✗ Error: ${e.message}`);
}

try {
  fs.mkdirSync(path.join(DESTB, "b2b"), { recursive: true });
  console.log(`✓ Created: ${DESTB}/b2b`);
} catch (e) {
  console.log(`✗ Error: ${e.message}`);
}
console.log("");

console.log("=" .repeat(100));
console.log("STEP 2: COPY FILES SOURCE -> DESTA");
console.log("=" .repeat(100));

for (const file of FILES) {
  try {
    const src = path.join(SOURCE, file);
    const dst = path.join(DESTA, file);
    fs.mkdirSync(path.dirname(dst), { recursive: true });
    fs.copyFileSync(src, dst);
    console.log(`✓ ${file}`);
  } catch (e) {
    console.log(`✗ ${file}: ${e.message}`);
  }
}
console.log("");

console.log("=" .repeat(100));
console.log("STEP 3: COPY FILES SOURCE -> DESTB");
console.log("=" .repeat(100));

for (const file of FILES) {
  try {
    const src = path.join(SOURCE, file);
    const dst = path.join(DESTB, file);
    fs.mkdirSync(path.dirname(dst), { recursive: true });
    fs.copyFileSync(src, dst);
    console.log(`✓ ${file}`);
  } catch (e) {
    console.log(`✗ ${file}: ${e.message}`);
  }
}
console.log("");

console.log("=" .repeat(100));
console.log("STEP 4: COMPUTE SHA-256 HASHES AND VERIFICATION");
console.log("=" .repeat(100));
console.log("");

console.log("| File | Source Hash (first 16) | DestA Hash (first 16) | DestB Hash (first 16) | MatchA | MatchB |");
console.log("|---|---|---|---|---|---|");

let allMatch = true;
const hashes = [];

for (const file of FILES) {
  try {
    const srcPath = path.join(SOURCE, file);
    const destaPath = path.join(DESTA, file);
    const destbPath = path.join(DESTB, file);

    const srcHash = computeSha256(srcPath);
    const destaHash = computeSha256(destaPath);
    const destbHash = computeSha256(destbPath);

    const matchA = srcHash === destaHash ? "✓" : "✗ MISMATCH";
    const matchB = srcHash === destbHash ? "✓" : "✗ MISMATCH";

    if (srcHash !== destaHash || srcHash !== destbHash) {
      allMatch = false;
    }

    const srcShort = srcHash.substring(0, 16) + "...";
    const destaShort = destaHash.substring(0, 16) + "...";
    const destbShort = destbHash.substring(0, 16) + "...";

    console.log(`| ${file} | ${srcShort} | ${destaShort} | ${destbShort} | ${matchA} | ${matchB} |`);
    hashes.push([file, srcHash, destaHash, destbHash, matchA, matchB]);
  } catch (e) {
    console.log(`| ${file} | ERROR | ERROR | ERROR | ERROR | ERROR |`);
    allMatch = false;
  }
}

console.log("");
console.log("=" .repeat(100));
console.log("VERIFICATION SUMMARY");
console.log("=" .repeat(100));
console.log(`Source: ${SOURCE}`);
console.log(`DestA:  ${DESTA}`);
console.log(`DestB:  ${DESTB}`);
console.log("");

if (allMatch) {
  console.log("✓✓✓ ALL FILES VERIFIED - HASHES MATCH ✓✓✓");
} else {
  console.log("✗✗✗ HASH MISMATCH DETECTED - REVIEW REQUIRED ✗✗✗");
}

console.log("");
console.log("=" .repeat(100));
console.log("DETAILED HASH TABLE (FULL SHA-256)");
console.log("=" .repeat(100));
console.log("");
console.log("| File | Source SHA-256 | DestA SHA-256 | DestB SHA-256 | Match |");
console.log("|---|---|---|---|---|");

for (const [file, srcHash, destaHash, destbHash, matchA, matchB] of hashes) {
  const match = (matchA === "✓" && matchB === "✓") ? "✓ BOTH" : "✗ FAIL";
  console.log(`| ${file} | ${srcHash} | ${destaHash} | ${destbHash} | ${match} |`);
}

console.log("");
console.log("=" .repeat(100));
console.log("FILE COUNT AND STATUS");
console.log("=" .repeat(100));
console.log(`Total files processed: ${FILES.length}`);
console.log(`All matches: ${allMatch ? "YES" : "NO"}`);
