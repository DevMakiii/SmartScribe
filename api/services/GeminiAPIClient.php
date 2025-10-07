<?php
// api/services/GeminiAPIClient.php

class GeminiAPIClient {
    private $apiKey;
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    private $cacheDir = __DIR__ . '/../cache/';
    private $cacheExpiry = 3600; // 1 hour cache expiry
    private $maxRetries = 3;
    private $retryDelay = 1; // Initial delay in seconds

    public function __construct() {
        $this->apiKey = getenv('GOOGLE_GEMINI_API_KEY');
        if (!$this->apiKey ||
            $this->apiKey === 'your_google_gemini_api_key_here' ||
            $this->apiKey === 'your_actual_api_key_here' ||
            $this->apiKey === 'your_production_google_gemini_api_key' ||
            $this->apiKey === 'dummy_api_key_for_testing' ||
            strlen($this->apiKey) < 20) { // Real API keys are much longer
            error_log("Google Gemini API key not found or is placeholder/dummy in environment variables");
            $this->apiKey = null;
        }

        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Check if API key is available
     */
    public function isAvailable() {
        return $this->apiKey !== null;
    }

    /**
     * Generate cache key from prompt and options
     */
    private function getCacheKey($prompt, $options = []) {
        $keyData = [
            'prompt' => $prompt,
            'temperature' => $options['temperature'] ?? 0.3,
            'maxTokens' => $options['maxTokens'] ?? 1000
        ];
        return md5(json_encode($keyData));
    }

    /**
     * Get cached response if available and not expired
     */
    private function getCachedResponse($cacheKey) {
        $cacheFile = $this->cacheDir . $cacheKey . '.cache';
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            if ($cacheData && (time() - $cacheData['timestamp']) < $this->cacheExpiry) {
                return $cacheData['response'];
            }
            // Remove expired cache
            unlink($cacheFile);
        }
        return null;
    }

    /**
     * Cache response
     */
    private function setCachedResponse($cacheKey, $response) {
        $cacheFile = $this->cacheDir . $cacheKey . '.cache';
        $cacheData = [
            'timestamp' => time(),
            'response' => $response
        ];
        file_put_contents($cacheFile, json_encode($cacheData));
    }

    /**
     * Make API call to Gemini with caching, automatic retry, and exponential backoff
     */
    public function call($prompt, $options = []) {
        if (!$this->apiKey) {
            throw new Exception('Gemini API key not configured');
        }

        // Check cache first
        $cacheKey = $this->getCacheKey($prompt, $options);
        $cachedResponse = $this->getCachedResponse($cacheKey);
        if ($cachedResponse !== null) {
            error_log("Using cached Gemini API response for cache key: {$cacheKey}");
            return $cachedResponse;
        }

        // Set default parameters based on task type
        $temperature = $options['temperature'] ?? 0.3;
        $maxTokens = $options['maxTokens'] ?? 1000;

        $lastException = null;
        $currentDelay = $this->retryDelay;

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                $response = $this->makeApiCall($prompt, $temperature, $maxTokens);

                // Cache successful response
                $this->setCachedResponse($cacheKey, $response);

                return $response;
            } catch (Exception $e) {
                $lastException = $e;
                $errorMessage = strtolower($e->getMessage());

                // Check if error is retryable
                $isRetryable = $this->isRetryableError($errorMessage);

                if (!$isRetryable || $attempt === $this->maxRetries - 1) {
                    // If it's a token limit error, try with reduced tokens on last attempt
                    if (strpos($errorMessage, 'token limit exceeded') !== false && $maxTokens > 500) {
                        error_log("Token limit exceeded, retrying with reduced tokens: {$maxTokens} -> " . ($maxTokens - 200));
                        try {
                            $response = $this->makeApiCall($prompt, $temperature, $maxTokens - 200);
                            $this->setCachedResponse($cacheKey, $response);
                            return $response;
                        } catch (Exception $tokenException) {
                            // Continue to throw original exception
                        }
                    }
                    break;
                }

                // Exponential backoff
                error_log("Gemini API call failed (attempt " . ($attempt + 1) . "/{$this->maxRetries}): " . $e->getMessage() . ". Retrying in {$currentDelay}s...");
                sleep($currentDelay);
                $currentDelay *= 2; // Exponential backoff
            }
        }

        // All retries failed
        throw $lastException;
    }

    /**
     * Check if an error is retryable
     */
    private function isRetryableError($errorMessage) {
        $retryablePatterns = [
            'rate limit exceeded',
            'quota exceeded',
            'too many requests',
            'service unavailable',
            'internal server error',
            'bad gateway',
            'gateway timeout',
            'connection timeout',
            'network error'
        ];

        foreach ($retryablePatterns as $pattern) {
            if (strpos($errorMessage, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Internal method to make the actual API call
     */
    private function makeApiCall($prompt, $temperature, $maxTokens) {
        $url = $this->baseUrl . '?key=' . $this->apiKey;

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
                'topP' => 0.9,
                'topK' => 40
            ]
        ];

        $headers = [
            'Content-Type: application/json'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("Gemini API cURL Error: " . $err);
        }

        if ($httpCode !== 200) {
            throw new Exception("Gemini API HTTP Error: {$httpCode} Response: {$response}");
        }

        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Gemini API JSON Decode Error: " . json_last_error_msg());
        }

        if (isset($responseData['error'])) {
            $errorMessage = $responseData['error']['message'] ?? 'Unknown API error';

            // Handle specific token limit errors
            if (strpos($errorMessage, 'maximum') !== false && strpos($errorMessage, 'token') !== false) {
                throw new Exception("Token limit exceeded. Please try with shorter content or reduce the requested output length.");
            }

            throw new Exception("Gemini API Error: " . $errorMessage);
        }

        // Extract text from Gemini response
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($responseData['candidates'][0]['content']['parts'][0]['text']);
        }

        throw new Exception("Unexpected Gemini API Response Format: " . json_encode($responseData));
    }
}
?>