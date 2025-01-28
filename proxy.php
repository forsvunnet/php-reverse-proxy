<?php

// Detect if running in CLI or as a built-in server
if (php_sapi_name() === 'cli') {
    // Command-line usage
    if ($argc !== 3) {
        echo "\033[1;32mPHP Proxy Server\033[0m\n";
        echo "Usage: php proxy.php targetHost proxyIp:proxyPort\n";
        exit(1);
    }

    $targetHost = $argv[1];
    $proxyAddress = $argv[2];

    // Validate proxy address
    if (!filter_var("http://$proxyAddress", FILTER_VALIDATE_URL)) {
        echo "\033[1;31mError:\033[0m Invalid proxy address format.\n";
        exit(1);
    }

    // Parse proxy IP and port
    [$proxyIp, $proxyPort] = explode(':', $proxyAddress);
    if (!filter_var($proxyIp, FILTER_VALIDATE_IP) || !is_numeric($proxyPort)) {
        echo "\033[1;31mError:\033[0m Invalid IP or port.\n";
        exit(1);
    }

    // Start the server with the router
    echo "\033[1;32mStarting proxy server at http://$proxyIp:$proxyPort\033[0m\n";
    putenv("TARGET_HOST=$targetHost");
    putenv("PROXY_BASE_URL=http://$proxyIp:$proxyPort");
    passthru("php -S $proxyIp:$proxyPort " . __FILE__);
    exit(0);
}

// If running as a server, handle the request
$targetHost = getenv('TARGET_HOST');
$proxyBaseUrl = getenv('PROXY_BASE_URL');

// Ensure environment variables are set
if (!$targetHost || !$proxyBaseUrl) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Proxy server misconfigured. TARGET_HOST or PROXY_BASE_URL is missing.';
    exit;
}

// Capture the incoming request
$requestUri = $_SERVER['REQUEST_URI'];
$requestHeaders = getallheaders();
$requestBody = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'];

// Prepare the target URL
$targetUrl = "https://$targetHost$requestUri";

// Initialize cURL
$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

// Add headers
$forwardedHeaders = [];
foreach ($requestHeaders as $key => $value) {
    if (strtolower($key) === 'host') {
        $value = $targetHost;
    }
    if (strtolower($key) === 'accept-encoding') {
        $value = 'identity'; // Avoid gzip for easier debugging
    }
    $forwardedHeaders[] = "$key: $value";
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardedHeaders);

// Execute the request
$response = curl_exec($ch);

// Handle cURL errors
if (curl_errno($ch)) {
    header("HTTP/1.1 502 Bad Gateway");
    echo "Proxy error: " . curl_error($ch);
    exit;
}

// Parse response
$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);

// Extract the proxy IP from PROXY_BASE_URL
$parsedProxyBaseUrl = parse_url($proxyBaseUrl);
$proxyIp = $parsedProxyBaseUrl['host']; // Extract the proxy IP (e.g., 192.168.0.196)

// Extract the host part from targetHost
$parsedTargetHost = parse_url("http://$targetHost");
$hostOnly = $parsedTargetHost['host']; // Extract the host (e.g., ingrain.test)

// Replace occurrences of the target host with the proxy IP while preserving ports and scheme
$responseBody = preg_replace_callback(
    [
        '#(https?)://(' . preg_quote($hostOnly, '#') . ')(:\d+)?#', // Match http:// or https:// with optional port
        '#//(' . preg_quote($hostOnly, '#') . ')(:\d+)?#'          // Match protocol-relative URLs with optional port
    ],
    function ($matches) use ($proxyIp) {
        $scheme = $matches[1] ?? 'http'; // Preserve the scheme (http or https), default to http
        $port = $matches[3] ?? '';      // Preserve the original port if present
        return $scheme . '://' . $proxyIp . $port;
    },
    $responseBody
);


// Rewrite headers
$headers = array_filter(explode("\r\n", $responseHeaders));
foreach ($headers as $header) {
    if (stripos($header, 'Location:') === 0) {
        $header = str_replace(
            ["http://$targetHost", "https://$targetHost"],
            $proxyBaseUrl,
            $header
        );
    }
    if (stripos($header, 'Set-Cookie:') === 0) {
        $header = str_replace("Domain=$targetHost", "Domain=" . parse_url($proxyBaseUrl, PHP_URL_HOST), $header);
    }
    if (!stripos($header, 'Transfer-Encoding') && !stripos($header, 'Content-Length')) {
        header($header, false);
    }
}

// Ensure Content-Length is set
if (!stripos($responseHeaders, 'Content-Length')) {
    header("Content-Length: " . strlen($responseBody));
}

// Send HTTP status code and body
header("HTTP/1.1 $httpStatusCode");
echo $responseBody;
