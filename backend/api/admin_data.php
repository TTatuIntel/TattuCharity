<?php
define('TATTU_INTERNAL', true);
require_once __DIR__ . '/../lib/helpers.php';
require_admin();

json_response([
    'success'     => true,
    'donations'   => array_reverse(read_json(DONATIONS_FILE,   [])),
    'messages'    => array_reverse(read_json(MESSAGES_FILE,    [])),
    'subscribers' => array_reverse(read_json(SUBSCRIBERS_FILE, [])),
]);
