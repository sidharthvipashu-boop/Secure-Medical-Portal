# Deny direct browser access to stored (encrypted) report files.
# Files here are served only through /download.php after authentication and a
# SHA-256 integrity check. This prevents anyone from grabbing ciphertext directly.
Require all denied
# Fallback for older Apache 2.2:
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
