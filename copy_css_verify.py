#!/usr/bin/env python3
"""
Copy CSS files from source to two destinations with SHA-256 verification
Handles both Unix and Windows path styles
"""
import os
import hashlib
import shutil
import sys
from pathlib import Path

# Try to normalize paths - support both Unix /home and Windows C:\Users
def get_path(*parts):
    """Convert path parts to platform-appropriate path"""
    if os.path.exists('/home'):
        # Unix-like environment
        return os.path.join(*parts)
    else:
        # Convert /home/user to C:\Users\user or similar
        path = os.path.join(*parts)
        if path.startswith('/home/'):
            # Map /home/user to C:\Users\user
            home_part = path[6:]  # Remove /home/
            if home_part.startswith('user/'):
                return 'C:\\Users\\user\\' + home_part[5:].replace('/', '\\')
            elif home_part.startswith('jessessh/'):
                return 'C:\\Users\\jessessh\\' + home_part[9:].replace('/', '\\')
        return path

SOURCE = "/home/jessessh/htdocs/srv1113343.hstgr.cloud/app/design/frontend/AWA_Custom/ayo_home5_child/web/css"
DESTA = "/home/user/htdocs/srv1113343.hstgr.cloud/app/design/frontend/AWA_Custom/ayo_home5_child/web/css"
DESTB = "/home/user/htdocs/srv1113343.hstgr.cloud/pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css"

FILES = [
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
]

def compute_sha256(filepath):
    """Compute SHA-256 hash of a file"""
    try:
        sha256_hash = hashlib.sha256()
        with open(filepath, "rb") as f:
            for byte_block in iter(lambda: f.read(4096), b""):
                sha256_hash.update(byte_block)
        return sha256_hash.hexdigest()
    except Exception as e:
        return f"ERROR: {str(e)}"

print("=" * 100)
print("STEP 1: CREATE DIRECTORIES")
print("=" * 100)
try:
    Path(os.path.join(DESTA, "b2b")).mkdir(parents=True, exist_ok=True)
    print(f"✓ Created: {DESTA}/b2b")
except Exception as e:
    print(f"✗ Error: {e}")

try:
    Path(os.path.join(DESTB, "b2b")).mkdir(parents=True, exist_ok=True)
    print(f"✓ Created: {DESTB}/b2b")
except Exception as e:
    print(f"✗ Error: {e}")
print()

print("=" * 100)
print("STEP 2: COPY FILES SOURCE -> DESTA")
print("=" * 100)
for file in FILES:
    try:
        src = os.path.join(SOURCE, file)
        dst = os.path.join(DESTA, file)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copy2(src, dst)
        print(f"✓ {file}")
    except Exception as e:
        print(f"✗ {file}: {e}")
print()

print("=" * 100)
print("STEP 3: COPY FILES SOURCE -> DESTB")
print("=" * 100)
for file in FILES:
    try:
        src = os.path.join(SOURCE, file)
        dst = os.path.join(DESTB, file)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copy2(src, dst)
        print(f"✓ {file}")
    except Exception as e:
        print(f"✗ {file}: {e}")
print()

print("=" * 100)
print("STEP 4: COMPUTE SHA-256 HASHES AND BUILD VERIFICATION TABLE")
print("=" * 100)
print()
print("| File | Source Hash (first 16) | DestA Hash (first 16) | DestB Hash (first 16) | MatchA | MatchB |")
print("|---|---|---|---|---|---|")

all_match = True
hashes_full = []

for file in FILES:
    try:
        src_path = os.path.join(SOURCE, file)
        desta_path = os.path.join(DESTA, file)
        destb_path = os.path.join(DESTB, file)
        
        src_hash = compute_sha256(src_path)
        desta_hash = compute_sha256(desta_path)
        destb_hash = compute_sha256(destb_path)
        
        match_a = "✓" if src_hash == desta_hash else "✗ MISMATCH"
        match_b = "✓" if src_hash == destb_hash else "✗ MISMATCH"
        
        if src_hash != desta_hash or src_hash != destb_hash:
            all_match = False
        
        src_short = src_hash[:16] + "..." if len(src_hash) > 16 else src_hash
        desta_short = desta_hash[:16] + "..." if len(desta_hash) > 16 else desta_hash
        destb_short = destb_hash[:16] + "..." if len(destb_hash) > 16 else destb_hash
        
        print(f"| {file} | {src_short} | {desta_short} | {destb_short} | {match_a} | {match_b} |")
        hashes_full.append((file, src_hash, desta_hash, destb_hash, match_a, match_b))
    except Exception as e:
        print(f"| {file} | ERROR | ERROR | ERROR | ERROR | ERROR |")
        all_match = False

print()
print("=" * 100)
print("VERIFICATION SUMMARY")
print("=" * 100)
print(f"Source: {SOURCE}")
print(f"DestA:  {DESTA}")
print(f"DestB:  {DESTB}")
print()

if all_match:
    print("✓✓✓ ALL FILES VERIFIED - HASHES MATCH ✓✓✓")
else:
    print("✗✗✗ HASH MISMATCH DETECTED - REVIEW REQUIRED ✗✗✗")

print()
print("=" * 100)
print("DETAILED HASH TABLE (FULL SHA-256)")
print("=" * 100)
print()
print("| File | Source SHA-256 | DestA SHA-256 | DestB SHA-256 | Match |")
print("|---|---|---|---|---|")

for file, src_hash, desta_hash, destb_hash, match_a, match_b in hashes_full:
    match = "✓ BOTH" if (match_a == "✓" and match_b == "✓") else "✗ FAIL"
    print(f"| {file} | {src_hash} | {desta_hash} | {destb_hash} | {match} |")

print()
print("=" * 100)
print("FILE COUNT AND STATUS")
print("=" * 100)
print(f"Total files processed: {len(FILES)}")
print(f"All matches: {'YES' if all_match else 'NO'}")
