<?php
// api/services/AIKeywordService.php
require_once __DIR__ . '/GeminiAPIClient.php';

class AIKeywordService {
    private $apiClient;

    public function __construct() {
        $this->apiClient = new GeminiAPIClient();
    }

    /**
     * Extract high-quality keywords using advanced multi-pass technique
     */
    public function extractKeywords($text, $count = 5, $types = ['topic', 'entity', 'concept']) {
        if (empty($text)) {
            return [];
        }

        // Detect content type for specialized processing
        $contentType = $this->detectContentType($text);
        $textAnalysis = $this->analyzeTextStructure($text);

        if (!$this->apiClient->isAvailable()) {
            return $this->extractKeywordsAdvancedFallback($text, $count, $contentType, $textAnalysis);
        }

        try {
            // Multi-pass keyword extraction for highest quality
            $keywords = $this->extractKeywordsMultiPass($text, $count, $types, $contentType, $textAnalysis);

            // Validate and refine keywords
            $validatedKeywords = $this->validateAndRefineKeywords($text, $keywords, $contentType);

            return array_slice($validatedKeywords, 0, $count);
        } catch (Exception $e) {
            error_log("Advanced keyword extraction failed: " . $e->getMessage());
            return $this->extractKeywordsAdvancedFallback($text, $count, $contentType, $textAnalysis);
        }
    }

    /**
     * Multi-pass keyword extraction for superior quality
     */
    private function extractKeywordsMultiPass($text, $count, $types, $contentType, $textAnalysis) {
        // Pass 1: Extract candidate keywords by type
        $candidateKeywords = [];
        foreach ($types as $type) {
            $typeKeywords = $this->extractKeywordsByType($text, $type, $contentType, $textAnalysis);
            $candidateKeywords[$type] = $typeKeywords;
        }

        // Pass 2: Score and rank all candidates
        $scoredKeywords = $this->scoreAndRankKeywords($candidateKeywords, $text, $textAnalysis);

        // Pass 3: Select optimal combination
        return $this->selectOptimalKeywords($scoredKeywords, $count, $types);
    }

    /**
     * Extract keywords by specific type
     */
    private function extractKeywordsByType($text, $type, $contentType, $textAnalysis) {
        $typePrompts = [
            'topic' => "Extract topic keywords that represent the main subjects and themes. Focus on broad concepts that define what the content is about.",
            'entity' => "Extract entity keywords including people, organizations, locations, products, or specific named items mentioned.",
            'concept' => "Extract conceptual keywords representing ideas, theories, principles, methods, or abstract concepts discussed.",
            'action' => "Extract action keywords representing processes, methods, techniques, or verbs that describe what happens or how things work."
        ];

        $specializedInstructions = $this->getSpecializedKeywordInstructions($contentType, $type);

        $prompt = "KEYWORD EXTRACTION - {$type} TYPE
{$typePrompts[$type]}

CONTENT TYPE: {$contentType}
{$specializedInstructions}

TEXT ANALYSIS: {$textAnalysis}

CONTENT TO ANALYZE:
{$text}

Extract 8-12 {$type} keywords that best represent this type of content. Return only the keywords separated by commas, no explanations or other text.";

        $response = $this->apiClient->call($prompt, ['temperature' => 0.2, 'maxTokens' => 500]);
        return array_map('trim', explode(',', $response));
    }

    /**
     * Score and rank keywords across all types
     */
    private function scoreAndRankKeywords($candidateKeywords, $text, $textAnalysis) {
        $allKeywords = [];
        $textLower = strtolower($text);

        foreach ($candidateKeywords as $type => $keywords) {
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (empty($keyword)) continue;

                if (!isset($allKeywords[$keyword])) {
                    $score = $this->calculateKeywordScore($keyword, $text, $textLower, $type, $textAnalysis);
                    $allKeywords[$keyword] = [
                        'score' => $score,
                        'types' => [$type],
                        'frequency' => substr_count($textLower, strtolower($keyword))
                    ];
                } else {
                    // Boost score for keywords appearing in multiple types
                    $allKeywords[$keyword]['score'] *= 1.3;
                    $allKeywords[$keyword]['types'][] = $type;
                }
            }
        }

        // Sort by score descending
        uasort($allKeywords, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $allKeywords;
    }

    /**
     * Calculate comprehensive keyword score
     */
    private function calculateKeywordScore($keyword, $text, $textLower, $type, $textAnalysis) {
        $keywordLower = strtolower($keyword);
        $frequency = substr_count($textLower, $keywordLower);

        // Base frequency score
        $frequencyScore = min($frequency * 2, 10);

        // Position bonus (earlier appearance is better)
        $firstPosition = strpos($textLower, $keywordLower);
        $positionScore = $firstPosition !== false ? max(0, 5 - ($firstPosition / 1000)) : 0;

        // Length bonus (meaningful length)
        $lengthScore = min(strlen($keyword) / 3, 3);

        // Type-specific bonuses
        $typeBonuses = [
            'topic' => 2,
            'entity' => 1.5,
            'concept' => 2.5,
            'action' => 1
        ];
        $typeScore = $typeBonuses[$type] ?? 1;

        // Specificity bonus (avoid too common words)
        $commonWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $specificityScore = in_array($keywordLower, $commonWords) ? 0 : 2;

        // Capitalization bonus (proper nouns often more important)
        $capitalizationScore = ctype_upper(substr($keyword, 0, 1)) ? 1.5 : 1;

        return ($frequencyScore * 0.3) + ($positionScore * 0.2) + ($lengthScore * 0.15) +
               ($typeScore * 0.15) + ($specificityScore * 0.1) + ($capitalizationScore * 0.1);
    }

    /**
     * Select optimal keyword combination
     */
    private function selectOptimalKeywords($scoredKeywords, $count, $types) {
        $selected = [];
        $typeCounts = array_fill_keys($types, 0);
        $maxPerType = max(1, $count / count($types));

        foreach ($scoredKeywords as $keyword => $data) {
            // Ensure type diversity
            $keywordTypes = $data['types'];
            $canAdd = false;

            foreach ($keywordTypes as $type) {
                if ($typeCounts[$type] < $maxPerType) {
                    $canAdd = true;
                    $typeCounts[$type]++;
                    break;
                }
            }

            if ($canAdd && count($selected) < $count) {
                $selected[] = $keyword;
            }

            if (count($selected) >= $count) break;
        }

        return $selected;
    }

    /**
     * Validate and refine extracted keywords
     */
    private function validateAndRefineKeywords($text, $keywords, $contentType) {
        if (empty($keywords)) return $keywords;

        $keywordsText = implode(', ', $keywords);

        $validationPrompt = "Validate and refine these keywords for the given text. Remove duplicates, fix typos, ensure relevance, and prioritize the most important ones.

ORIGINAL TEXT:
{$text}

CURRENT KEYWORDS:
{$keywordsText}

CONTENT TYPE: {$contentType}

Return only the refined keywords separated by commas, maintaining the most relevant and accurate terms.";

        try {
            $response = $this->apiClient->call($validationPrompt, ['temperature' => 0.1, 'maxTokens' => 300]);
            return array_map('trim', explode(',', $response));
        } catch (Exception $e) {
            error_log("Keyword validation failed: " . $e->getMessage());
            return $keywords;
        }
    }

    /**
     * Get specialized keyword extraction instructions
     */
    private function getSpecializedKeywordInstructions($contentType, $keywordType) {
        $instructions = [
            'academic_research' => [
                'topic' => 'Focus on research areas, methodologies, and theoretical frameworks.',
                'entity' => 'Include researchers, institutions, journals, and specific studies.',
                'concept' => 'Emphasize theories, hypotheses, and research concepts.',
                'action' => 'Include research methods, analysis techniques, and processes.'
            ],
            'technical_documentation' => [
                'topic' => 'Focus on technologies, systems, and technical domains.',
                'entity' => 'Include specific tools, platforms, libraries, and components.',
                'concept' => 'Emphasize technical concepts, protocols, and standards.',
                'action' => 'Include implementation steps, configuration, and operations.'
            ],
            'business_professional' => [
                'topic' => 'Focus on business domains, industries, and strategic areas.',
                'entity' => 'Include companies, products, services, and key people.',
                'concept' => 'Emphasize business concepts, strategies, and frameworks.',
                'action' => 'Include business processes, methodologies, and actions.'
            ],
            'educational' => [
                'topic' => 'Focus on subjects, learning areas, and educational domains.',
                'entity' => 'Include institutions, educators, and educational resources.',
                'concept' => 'Emphasize learning theories, pedagogical approaches.',
                'action' => 'Include teaching methods, learning activities, and processes.'
            ]
        ];

        return $instructions[$contentType][$keywordType] ?? 'Extract the most relevant and important keywords for this content.';
    }

    /**
     * Detect content type for specialized processing
     */
    private function detectContentType($text) {
        $textLower = strtolower($text);

        if (preg_match('/(research|study|methodology|findings|hypothesis|conclusion|abstract|literature|peer.review)/i', $text)) {
            return 'academic_research';
        }

        if (preg_match('/(api|documentation|manual|guide|tutorial|configuration|deployment|installation)/i', $text)) {
            return 'technical_documentation';
        }

        if (preg_match('/(business|strategy|market|analysis|report|meeting|agenda|minutes|presentation)/i', $text)) {
            return 'business_professional';
        }

        if (preg_match('/(lesson|course|curriculum|learning|teaching|student|assignment|exam|quiz)/i', $text)) {
            return 'educational';
        }

        return 'general';
    }

    /**
     * Analyze text structure for better keyword extraction
     */
    private function analyzeTextStructure($text) {
        $wordCount = str_word_count($text);
        $sentenceCount = preg_match_all('/[.!?]+/', $text, $matches);
        $paragraphCount = preg_match_all('/\n\s*\n/', $text, $matches);

        $analysis = "Word count: {$wordCount}, Sentences: {$sentenceCount}, Paragraphs: " . ($paragraphCount + 1);

        // Detect structural elements
        if (preg_match_all('/(\d+\.|\•|\-)/', $text) > 2) {
            $analysis .= ", Contains lists";
        }

        if (preg_match('/(important|key|critical|essential|note|remember)/i', $text)) {
            $analysis .= ", Has emphasized terms";
        }

        return $analysis;
    }

    /**
     * Advanced fallback keyword extraction when API is unavailable
     */
    private function extractKeywordsAdvancedFallback($text, $count, $contentType, $textAnalysis) {
        $words = str_word_count(strtolower($text), 1);

        // Enhanced stop words based on content type
        $baseStopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those'];

        $contentStopWords = [
            'academic_research' => ['study', 'research', 'paper', 'article', 'author', 'authors'],
            'technical_documentation' => ['user', 'guide', 'manual', 'documentation', 'version', 'page'],
            'business_professional' => ['company', 'business', 'market', 'report', 'analysis', 'strategy'],
            'educational' => ['student', 'students', 'course', 'lesson', 'learning', 'education']
        ];

        $stopWords = array_merge($baseStopWords, $contentStopWords[$contentType] ?? []);

        // Extract candidate terms with better filtering
        $candidates = [];
        foreach ($words as $index => $word) {
            if (strlen($word) > 3 && !in_array($word, $stopWords) && ctype_alpha($word)) {
                $candidates[] = [
                    'word' => $word,
                    'position' => $index,
                    'length' => strlen($word)
                ];
            }
        }

        // Score candidates using TF-IDF-like approach
        $termStats = [];
        foreach ($candidates as $candidate) {
            $word = $candidate['word'];
            if (!isset($termStats[$word])) {
                $termStats[$word] = [
                    'frequency' => 0,
                    'positions' => [],
                    'totalLength' => 0
                ];
            }
            $termStats[$word]['frequency']++;
            $termStats[$word]['positions'][] = $candidate['position'];
            $termStats[$word]['totalLength'] += $candidate['length'];
        }

        // Calculate scores
        $scoredTerms = [];
        foreach ($termStats as $term => $stats) {
            $avgLength = $stats['totalLength'] / $stats['frequency'];
            $positionScore = 1 / (1 + min($stats['positions']) / 100); // Earlier is better
            $frequencyScore = min($stats['frequency'], 5); // Cap frequency score
            $lengthScore = min($avgLength / 8, 2); // Longer terms get bonus

            $totalScore = ($frequencyScore * 0.5) + ($positionScore * 0.3) + ($lengthScore * 0.2);
            $scoredTerms[$term] = $totalScore;
        }

        arsort($scoredTerms);
        return array_slice(array_keys($scoredTerms), 0, $count);
    }
}
?>