<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';

$content = read_json(CONTENT_FILE, []);
header('Cache-Control: public, max-age=60');
json_response($content);
