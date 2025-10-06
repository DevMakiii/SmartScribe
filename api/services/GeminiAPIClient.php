<?php
// api/services/GeminiAPIClient.php

class GeminiAPIClient {
    private $apiKey;
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

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
    }

    /**
     * Check if API key is available
     */
    public function isAvailable() {
        return $this->apiKey !== null;
    }

    /**
     * Make API call to Gemini with automatic retry on token limits
     */
    public function call($prompt, $options = []) {
        if (!$this->apiKey) {
            throw new Exception('Gemini API key not configured');
        }

        // Set default parameters based on task type
        $temperature = $options['temperature'] ?? 0.3;
        $maxTokens = $options['maxTokens'] ?? 1000;

        // Try with original token limit first
        try {
            return $this->makeApiCall($prompt, $temperature, $maxTokens);
        } catch (Exception $e) {
            // If it's a token limit error, try with reduced tokens
            if (strpos($e->getMessage(), 'Token limit exceeded') !== false && $maxTokens > 500) {
                error_log("Token limit exceeded, retrying with reduced tokens: {$maxTokens} -> " . ($maxTokens - 200));
                return $this->makeApiCall($prompt, $temperature, $maxTokens - 200);
            }

            // Re-throw the original error if it's not a token limit issue
            throw $e;
        }
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