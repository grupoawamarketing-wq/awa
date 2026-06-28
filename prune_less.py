import sys

file_path = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-consolidated.less'

with open(file_path, 'r') as f:
    lines = f.readlines()

ranges_to_delete = [
    (8774, 8840),
    (10842, 10972)
]

# Convert to 0-indexed sets of lines to delete
lines_to_delete = set()
for start, end in ranges_to_delete:
    for i in range(start - 1, end):
        lines_to_delete.add(i)

new_lines = []
for i, line in enumerate(lines):
    if i not in lines_to_delete:
        new_lines.append(line)

with open(file_path, 'w') as f:
    f.writelines(new_lines)

print(f"Deleted {len(lines) - len(new_lines)} lines from {file_path}")
