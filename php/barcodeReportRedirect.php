<?php
/*
  Alma API Proxy — Config-Aware Version
  Author: Henry Steele (Tufts University Libraries)

  Summary:
    - Reads Alma.prop to locate local.prop (contains API secrets)
    - Loads Alma API credentials securely under SELinux
    - Uses CONF_KEY for /conf/libraries endpoints
    - Preserves query params such as item_barcode
*/

class Alma {
    private $ALMA_API_KEY;
    private $CONF_KEY;
    private $ALMA_API_BASE;
    private $ALMA_API_HOST;
    private $config_file;

    public function __construct() {
        $this->loadConfig();
    }

    /**
     * Load configuration by chaining:
     *   1. Read Alma.prop ? find proppath
     *   2. Read local.prop at that path
     */
    private function loadConfig() {
        // Step 1: Read Alma.prop to find path to local.prop
        $alma_prop = __DIR__ . '/Alma.prop';
        if (!file_exists($alma_prop)) {
            throw new Exception("Missing Alma.prop at $alma_prop");
        }

        $configpath = parse_ini_file($alma_prop, false);
        if ($configpath === false || empty($configpath['proppath'])) {
            throw new Exception("Failed to read 'proppath' from Alma.prop");
        }

        $this->config_file = trim($configpath['proppath']);

        // Step 2: Load the actual secrets from local.prop
        if (!file_exists($this->config_file)) {
            throw new Exception("Missing config file: " . $this->config_file);
        }

        $props = parse_ini_file($this->config_file);
        if ($props === false) {
            throw new Exception("Error reading configuration file: " . $this->config_file);
        }

        $this->ALMA_API_KEY  = trim($props['ALMA_API_KEY'] ?? '');
        $this->CONF_KEY      = trim($props['CONF_KEY'] ?? '');
        $this->ALMA_API_BASE = trim($props['ALMA_API_BASE'] ?? 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/');
        $this->ALMA_API_HOST = parse_url($this->ALMA_API_BASE, PHP_URL_HOST);

        if (empty($this->ALMA_API_KEY) || empty($this->CONF_KEY)) {
            throw new Exception("Missing ALMA_API_KEY or CONF_KEY in " . $this->config_file);
        }

        //error_log("Loaded config from " . $this->config_file);
    }

    /**
     * Perform Alma API request with automatic key selection.
     */
    public function getRequest($params) {
        if (empty($params['apipath'])) {
            http_response_code(400);
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode(["error" => "Missing 'apipath' parameter."]);
            return;
        }

        $path = trim($params['apipath']);
        unset($params['apipath']); // avoid duplication

        // Choose key: CONF_KEY for /conf/libraries
        $apikey_to_use = (stripos($path, 'libraries') !== false)
            ? $this->CONF_KEY
            : $this->ALMA_API_KEY;

        // Determine full vs relative path
        if (preg_match('/^https?:\/\//i', $path)) {
            $url = $path;
        } else {
            $url = rtrim($this->ALMA_API_BASE, '/') . '/' . ltrim($path, '/');
        }

        // Parse and preserve query params
        $parsed = parse_url($url);
        $base = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
        $query = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        // Merge in parameters from request
        foreach ($params as $k => $v) {
            $query[$k] = $v;
        }

        // Add or override required fields
        $query['apikey'] = $apikey_to_use;
        $query['format'] = 'json';

        // Final URL
        $url = $base . '?' . http_build_query($query);

        $redacted_url = preg_replace('/apikey=[^&]+/', 'apikey=REDACTED', $url);
        //error_log("Proxy URL (redacted): $redacted_url");

        // Execute cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ["Accept: application/json, application/xml;q=0.9, */*;q=0.8"],
        ]);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log("cURL error: $err");
            http_response_code(500);
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode(["error" => "Request failed", "detail" => $err]);
            return;
        }

        // Detect content type from response
        $ctype = (stripos($response, '<?xml') === 0)
            ? "application/xml"
            : "application/json";

        if (!headers_sent()) {
            header_remove("Content-Type");
            header("Content-Type: {$ctype}; charset=UTF-8");
            http_response_code($httpcode);
        }

        echo trim($response);
    }
}

// === MAIN EXECUTION ===
try {
    $ALMA = new Alma();
    $ALMA->getRequest($_GET);
} catch (Exception $e) {
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["error" => "Initialization failed", "detail" => $e->getMessage()]);
}
?>