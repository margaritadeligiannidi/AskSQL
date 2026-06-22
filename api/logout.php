<?php
header('Content-Type: application/json; charset=utf-8');

// Token-based logout: client should clear stored token (localStorage/cookie).
ob_clean();
echo json_encode(["success" => true]);
exit;