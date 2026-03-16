import re

# Update admin-pages/views/edit.php
with open('modules/admin-pages/views/edit.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = re.sub(r"t\('admin\.pages\.", "t('admin-pages.", content)
with open('modules/admin-pages/views/edit.php', 'w', encoding='utf-8') as f:
    f.write(content)

# Update admin-posts/views/list.php
with open('modules/admin-posts/views/list.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = re.sub(r"t\('admin\.posts\.", "t('admin-posts.", content)
with open('modules/admin-posts/views/list.php', 'w', encoding='utf-8') as f:
    f.write(content)

# Update admin-posts/views/edit.php
with open('modules/admin-posts/views/edit.php', 'r', encoding='utf-8') as f:
    content = f.read()
content = re.sub(r"t\('admin\.posts\.", "t('admin-posts.", content)
with open('modules/admin-posts/views/edit.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("All files updated successfully!")
