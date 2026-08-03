<?php
/**
 * ============================================================
 *  Luna AI Voice Assistant — speak.php
 * ------------------------------------------------------------
 *  Receives Luna's text reply, converts it to English speech,
 *  and streams back an audio/mpeg response that app.js plays
 *  through the <audio> element.
 *
 *  Default provider ("google") uses the free, keyless Google
 *  Translate speech endpoint — perfect for shared hosting like
 *  InfinityFree where installing paid TTS SDKs isn't possible.
 *  Long text is split into safe chunks and stitched together.
 * ============================================================
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
$text = trim($body['text'] ?? ($_POST['text'] ?? ''));

if ($text === '') {
    http_response_code(400);
    echo json_encode(['error' => 'No text provided.']);
    exit;
}

// If the site owner prefers the zero-server-cost option, tell the
// frontend to fall back to the browser's own SpeechSynthesis voice.
if (TTS_PROVIDER === 'browser') {
    echo json_encode(['mode' => 'browser']);
    exit;
}

// ------------------------------------------------------------
// "google" provider: free Translate TTS proxy — English voice only
// ------------------------------------------------------------
$ttsLang = 'en';

// Google's endpoint caps each request at ~200 characters, so split
// the reply into sentence-friendly chunks before requesting audio.
function luna_split_text(string $text, int $limit = 180): array {
    $text = preg_replace('/\s+/u', ' ', trim($text));
    $sentences = preg_split('/(?<=[\.\!\?\؟\۔])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!$sentences) {
        $sentences = [$text];
    }

    $chunks = [];
    $current = '';
    foreach ($sentences as $sentence) {
        if (mb_strlen($sentence) > $limit) {
            // Hard-wrap very long sentences by words.
            $words = explode(' ', $sentence);
            $piece = '';
            foreach ($words as $w) {
                if (mb_strlen($piece . ' ' . $w) > $limit) {
                    $chunks[] = trim($piece);
                    $piece = $w;
                } else {
                    $piece = trim($piece . ' ' . $w);
                }
            }
            if ($piece !== '') $sentence = $piece;
        }
        if (mb_strlen($current . ' ' . $sentence) > $limit) {
            $chunks[] = trim($current);
            $current = $sentence;
        } else {
            $current = trim($current . ' ' . $sentence);
        }
    }
    if ($current !== '') $chunks[] = $current;

    return array_slice($chunks, 0, 12); // safety cap
}

$chunks = luna_split_text($text);
$audioBinary = '';
$ok = true;
$lastError = '';

foreach ($chunks as $i => $chunk) {
    $url = 'https://translate.google.com/translate_tts'
        . '?ie=UTF-8'
        . '&client=tw-ob'
        . '&tl=' . urlencode($ttsLang)
        . '&q=' . urlencode($chunk)
        . '&idx=' . $i
        . '&total=' . count($chunks);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            // A browser-like User-Agent is required or Google rejects the request.
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'Referer: https://translate.google.com/',
        ],
    ]);
    $chunkAudio = curl_exec($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err        = curl_error($ch);
    curl_close($ch);

    if ($chunkAudio === false || $httpCode !== 200) {
        $ok = false;
        $lastError = $err ?: ('HTTP ' . $httpCode);
        break;
    }
    $audioBinary .= $chunkAudio;
}

if (!$ok || $audioBinary === '') {
    http_response_code(502);
    echo json_encode([
        'error'   => 'Text-to-speech generation failed.',
        'details' => $lastError,
        // Frontend can gracefully fall back to the browser voice.
        'fallback' => 'browser',
    ]);
    exit;
}

// Store the stitched MP3 in a short-lived temp file and hand back a
// playable URL (simpler and more reliable for <audio> than inline base64
// for longer replies).
$tmpDir = __DIR__ . '/tmp_audio';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0755, true);
}
// Best-effort cleanup of old files (older than 10 minutes).
foreach (glob($tmpDir . '/*.mp3') ?: [] as $old) {
    if (time() - filemtime($old) > 600) @unlink($old);
}

$filename = 'luna_' . bin2hex(random_bytes(8)) . '.mp3';
file_put_contents($tmpDir . '/' . $filename, $audioBinary);

echo json_encode([
    'mode'  => 'audio',
    'url'   => 'tmp_audio/' . $filename,
]);
