<?php

require __DIR__ . '/vendor/autoload.php';
header("Content-Type:application/json;charset=utf-8");
$ocr = new Wikisource\GoogleOcr\Ocr(__DIR__, $_REQUEST);

// Get the text.
$text = '';
try {
    $text = $ocr->getText();
} catch (\Wikisource\GoogleCloudVisionPHP\LimitExceededException $e) {
    error(["message" => "Limit exceeded: " . $e->getMessage()]);
} catch (\Exception $e) {
    error(["message" => $e->getMessage()]);
}

echo json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);

/**
 * Return a JSON error message to the user (as the top-level 'error' element).
 *
 * @param string $msg The message to return.
 */
function error($msg)
{
    http_response_code(400);
    echo json_encode([ "error" => $msg]);
    exit(1);
}
