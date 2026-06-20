<?php
// for encryptying individuals id card number
define('ENCRYPTION_KEY', 'secret_key');

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'lawyer');
define('DB_USER', 'root');
define('DB_PASS', '');

// Imported documents (Типски Документи → imported [placeholder] .docx files)
// Base folder where uploaded .docx files live. Never served directly —
// only streamed through api/document_api.php after an auth + company check.
define('UPLOADS_DIR', __DIR__ . '/uploads');