<?php
require __DIR__ . '/../../includes/bootstrap.php';

$list = search_practitioners(['q' => $_GET['q'] ?? '', 'specialty' => $_GET['specialty'] ?? '']);
json_out(['ok' => true, 'count' => count($list), 'html' => practitioner_cards_html($list)]);
