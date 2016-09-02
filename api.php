<?php

// Get the configuration variables.
require __DIR__ . '/config.php';
if (empty($key)) {
    throw new \Exception('API key value ($key) must be set in config.php');
}

// Set up universal bits.
require __DIR__ . '/vendor/autoload.php';
header("Content-Type:application/json;charset=utf-8");

// Get input, or complain.
if (empty($_REQUEST['image'])) {
    error(["message"=>"Please set the 'image' parameter"]);
}

// Make sure it's a Commons URL.
$uploadUrl = 'https://upload.wikimedia.org/';
if (substr($_REQUEST['image'], 0, strlen($uploadUrl)) !== $uploadUrl) {
    error(["message"=>"Image URL must start with '$uploadUrl'"]);
}

// Otherwise, send the request onwards to Google.
$gcv = new Wikisource\GoogleCloudVisionPHP\GoogleCloudVision;
$gcv->setKey($key);
if (!empty($endpoint)) {
    $gcv->setEndpoint($endpoint);
}
$gcv->setImage($_REQUEST['image']);
$gcv->addFeatureOCR();
if (!empty($_REQUEST['lang']) && $_REQUEST['lang'] !== 'en') {
    $gcv->setImageContext(['languageHints' => [$_REQUEST['lang']]]);
}
$response = $gcv->request();

// Check for errors and pass any through.
if (isset($response['responses'][0]['error'])) {
    error($response['responses'][0]['error']);
}

// Return only the text to the user (it's not an error if there's no text).
if (isset($response['responses'][0]['textAnnotations'][0]['description'])) {
    $text = $response['responses'][0]['textAnnotations'][0]['description'];
} else {
    $text = '';
}
echo json_encode(['text' => $text]);

/**
 * Return a JSON error message to the user (as the top-level 'error' element).
 *
 * @param strong $msg The message to return.
 */
function error($msg) {
    http_response_code(400);
    echo json_encode([ "error" => $msg]);
    exit(1);
}
