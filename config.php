<?php
/**
 * ============================================================
 *  Luna AI Voice Assistant — config.php
 * ------------------------------------------------------------
 *  1) Holds all Cohere API configuration (kept server-side only).
 *  2) Also acts as the chat endpoint: app.js POSTs the recognized
 *     speech text here, this file calls Cohere, and returns the
 *     assistant's reply as JSON. The API key never reaches the
 *     browser because everything happens in this PHP file.
 * ============================================================
 */

// ------------------------------------------------------------
// 1. COHERE CONFIGURATION  (fill these in before deploying)
// ------------------------------------------------------------

// Get your key from https://dashboard.cohere.com/api-keys
define('COHERE_API_KEY', 'YOUR_COHERE_API_KEY_HERE');

// Any current Cohere chat model works. command-r-plus-08-2024 gives the
// best quality; command-r is a faster/cheaper option.
define('COHERE_MODEL', 'command-r-plus-08-2024');

// Personality for Luna. She always replies in English only.
define('SYSTEM_PROMPT',
    "You are Luna, a warm, elegant, and helpful AI voice assistant. " .
    "Keep replies natural, conversational, and reasonably short since " .
    "they will be read aloud. ALWAYS reply in English only, regardless " .
    "of the language the user typed or spoke in."
);

// ------------------------------------------------------------
// 2. TEXT-TO-SPEECH CONFIGURATION (used by speak.php)
// ------------------------------------------------------------

// "google"  -> free Google Translate TTS proxy, no key required (default)
// "browser" -> tells the frontend to skip speak.php and use the
//              device's own built-in speech synthesis instead
define('TTS_PROVIDER', 'google');

// ------------------------------------------------------------
// 3. CHAT ENDPOINT
//    Only runs when this file is called directly (e.g. fetch('config.php')
//    with method POST). When another PHP file does `require 'config.php'`
//    for the constants above, this block is simply skipped.
// ------------------------------------------------------------

$isDirectPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && basename($_SERVER['SCRIPT_FILENAME']) === 'config.php';

if ($isDirectPost) {
    header('Content-Type: application/json; charset=utf-8');

    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $userText = trim($body['text'] ?? ($_POST['text'] ?? ''));

    if ($userText === '') {
        http_response_code(400);
        echo json_encode(['error' => 'No text provided.']);
        exit;
    }

    if (COHERE_API_KEY === 'YOUR_COHERE_API_KEY_HERE' || COHERE_API_KEY === '') {
        http_response_code(500);
        echo json_encode(['error' => 'Cohere API key is not configured on the server.']);
        exit;
    }

    $payload = [
        'model'   => COHERE_MODEL,
        'message' => $userText,
        'preamble'=> SYSTEM_PROMPT,
        'temperature' => 0.6,
    ];

    $ch = curl_init('https://api.cohere.ai/v1/chat');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . COHERE_API_KEY,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Could not reach Cohere API.', 'details' => $curlErr]);
        exit;
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($data['text'])) {
        echo json_encode([
            'reply' => trim($data['text']),
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error'   => 'Cohere API returned an error.',
            'details' => $data['message'] ?? $response,
        ]);
    }
    exit;
}
