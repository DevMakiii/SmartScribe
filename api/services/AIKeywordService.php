<?php
// api/services/AIKeywordService.php
require_once __DIR__ . '/GeminiAPIClient.php';

class AIKeywordService {
    private $apiClient;

    public function __construct() {
        $this->apiClient = new GeminiAPIClient();
    }

    /**
     * Extract keywords from text content
     */
    public function extractKeywords($text, $count = 5) {
        if (empty($text)) {
            return [];
        }

        if (!$this->apiClient->isAvailable()) {
            return $this->extractKeywordsFallback($text, $count);
        }

        $prompt = "Extract {$count} most important keywords from the following text. Return only the keywords separated by commas, no other text:\n\n{$text}";
        $response = $this->apiClient->call($prompt, ['temperature' => 0.3, 'maxTokens' => 1000]);

        if (!$response) {
            return $this->extractKeywordsFallback($text, $count);
        }

        return array_map('trim', explode(',', $response));
    }

    /**
     * Fallback keyword extraction when API is unavailable
     */
    private function extractKeywordsFallback($text, $count) {
        $words = str_word_count(strtolower($text), 1);
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those'];

        $filteredWords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });

        $wordCount = array_count_values($filteredWords);
        arsort($wordCount);
        return array_slice(array_keys($wordCount), 0, $count);
    }
}
?>