import sys

file_path = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-consolidated.less'
target_path = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-pdp-misc-2026-06.less'

with open(file_path, 'r') as f:
    lines = f.readlines()

# Extract lines 8774 to 11492 (0-indexed: 8773 to 11492)
start_idx = 8774 - 1
end_idx = 11492

extracted_lines = lines[start_idx:end_idx]

with open(target_path, 'w') as f:
    f.writelines(extracted_lines)

# Remove extracted lines
new_lines = lines[:start_idx] + lines[end_idx:]

with open(file_path, 'w') as f:
    f.writelines(new_lines)

print(f"Extracted {len(extracted_lines)} lines to {target_path}")
print(f"Deleted {len(lines) - len(new_lines)} lines from {file_path}")
