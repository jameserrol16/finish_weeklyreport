<?php
/**
 * CSRF Helper
 * Include this file once per page, then use:
 *   csrf_token_field()  — outputs hidden <input> inside forms
 *   csrf_verify()       — call at top of POST handlers, dies on failure
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_generate(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token_field(): void {
    $token = csrf_generate();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
    // Rotate token after successful verification
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}