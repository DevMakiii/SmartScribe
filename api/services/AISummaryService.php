<?php
// api/services/AISummaryService.php
require_once __DIR__ . '/GeminiAPIClient.php';

class AISummaryService {
    private $apiClient;

    public function __construct() {
        $this->apiClient = new GeminiAPIClient();
    }

    /**
     * Generate summary from text content
     */
    public function generateSummary($text, $length = 'auto', $format = 'paragraph') {
        if (empty($text)) {
            return null;
        }

        // Auto-detect appropriate length based on input text
        if ($length === 'auto') {
            $wordCount = str_word_count($text);
            $sentenceCount = preg_match_all('/[.!?]+/', $text, $matches);

            if ($wordCount < 50 || $sentenceCount < 3) {
                $length = 'short';
            } elseif ($wordCount < 500 || $sentenceCount < 10) {
                $length = 'medium';
            } else {
                $length = 'long';
            }
        }

        if (!$this->apiClient->isAvailable()) {
            error_log("Gemini API not available, using fallback summary generation");
            return $this->generateFallbackSummary($text, $length, $format);
        }

        try {
            $prompt = $this->buildSummaryPrompt($text, $length, $format);
            return $this->apiClient->call($prompt, ['temperature' => 0.1, 'maxTokens' => 1000]);
        } catch (Exception $e) {
            error_log("Gemini API call failed: " . $e->getMessage() . ", falling back to local summary generation");
            return $this->generateFallbackSummary($text, $length, $format);
        }
    }

    /**
     * Build summary prompt for AI
     */
    private function buildSummaryPrompt($text, $length, $format = 'paragraph') {
        // Analyze content type and structure
        $contentAnalysis = $this->analyzeContentStructure($text);

        if ($format === 'bullet_points') {
            $lengthInstructions = [
                'short' => 'Create a concise bullet point summary with 3-5 key points that capture the absolute core essence, main takeaway, and most critical insight.',
                'medium' => 'Create a well-structured bullet point summary with 6-8 key points covering main concepts, their interrelationships, practical applications, and essential insights.',
                'long' => 'Create a comprehensive bullet point summary with 10-12 detailed points organized by: core concepts, key relationships, practical applications, critical insights, and broader implications.'
            ];
        } else {
            $lengthInstructions = [
                'short' => 'Create a concise 2-3 sentence summary that captures the absolute core essence, main takeaway, and most critical insight.',
                'medium' => 'Create a well-structured summary of 4-6 sentences covering key concepts, their interrelationships, practical applications, and essential insights.',
                'long' => 'Create a comprehensive, academically-structured summary with clear sections: core concepts, key relationships, practical applications, critical insights, and broader implications.'
            ];
        }

        $instruction = $lengthInstructions[$length] ?? $lengthInstructions['medium'];

        return "You are an expert educator and content analyst. Your task is to create a comprehensive, well-structured summary that captures the complete essence of the provided content.

{$instruction}

CONTENT ANALYSIS:
{$contentAnalysis}

CONTENT TO SUMMARIZE:
{$text}

ENHANCED SUMMARY REQUIREMENTS:
1. STRUCTURE: Begin with the core concept/main idea, then systematically cover supporting elements
2. COMPREHENSIVENESS: Include ALL major concepts, their relationships, and interconnections
3. PRACTICAL VALUE: Highlight real-world applications, implications, and practical insights
4. TECHNICAL ACCURACY: Maintain precise terminology while ensuring clarity
5. LOGICAL FLOW: Create natural progression from basic concepts to advanced applications
6. CRITICAL INSIGHTS: Include important distinctions, nuances, exceptions, and limitations
7. EDUCATIONAL VALUE: Focus on insights that enhance understanding and application
8. SYNTHESIS: Show how different concepts work together as a cohesive system

CONTENT-SPECIFIC GUIDELINES:
- If this is a process/methodology: Explain the steps, rationale, and expected outcomes
- If this is a concept/theory: Cover definition, components, applications, and limitations
- If this is a comparison/analysis: Highlight similarities, differences, and implications
- If this is a problem/solution: Explain the problem, solution approach, and benefits
- If this is a case study/example: Extract generalizable principles and lessons learned

SUMMARY QUALITY STANDARDS:
- Start with the most fundamental concept or main idea
- Explain how components interact and support each other
- Include specific examples or applications where mentioned
- Highlight any critical distinctions or important nuances
- Connect ideas to show the bigger picture and broader implications
- End with key takeaways that reinforce the main value proposition

FORMATTING REQUIREMENTS:
- Use clear, natural language that flows conversationally
- Maintain academic rigor while ensuring accessibility
- Create logical paragraph breaks for better readability
- Ensure each sentence adds unique value to the summary
- Avoid redundancy while being comprehensive
- For bullet point format: Use • symbol for each bullet, keep each point concise but complete, ensure logical flow between points

IMPORTANT: Provide ONLY the summary text itself. Do NOT include any labels, prefixes, or titles like 'Summary:', 'Detailed Summary:', or similar. Start directly with the summary content and maintain a natural, flowing narrative that reads like a well-written article introduction.";
    }

    /**
     * Analyze content structure for better summary generation
     */
    private function analyzeContentStructure($text) {
        $wordCount = str_word_count($text);
        $sentenceCount = preg_match_all('/[.!?]+/', $text, $matches);
        $paragraphCount = preg_match_all('/\n\s*\n/', $text, $matches);

        // Detect content type patterns
        $contentType = 'general';
        $structureHints = [];

        // Check for different content types
        if (preg_match('/^(step|phase|stage)/im', $text)) {
            $contentType = 'process/methodology';
            $structureHints[] = 'Contains sequential steps or phases';
        }

        if (preg_match('/(definition|defined as|refers to)/i', $text)) {
            $contentType = 'conceptual/theoretical';
            $structureHints[] = 'Contains definitions and theoretical concepts';
        }

        if (preg_match('/(compare|contrast|versus|vs\.|difference|similar)/i', $text)) {
            $contentType = 'comparative';
            $structureHints[] = 'Contains comparisons and contrasts';
        }

        if (preg_match('/(example|case|scenario|situation)/i', $text)) {
            $contentType = 'practical/application';
            $structureHints[] = 'Contains practical examples and applications';
        }

        if (preg_match('/(problem|solution|challenge|issue)/i', $text)) {
            $contentType = 'problem-solution';
            $structureHints[] = 'Contains problem-solution analysis';
        }

        // Analyze structure
        $structure = [];
        if ($paragraphCount > 3) {
            $structure[] = 'Well-structured with multiple paragraphs';
        }

        if (preg_match_all('/(\d+\.|\•|\-)/', $text) > 3) {
            $structure[] = 'Contains lists or numbered items';
        }

        if (preg_match('/(important|key|critical|essential|note|remember)/i', $text)) {
            $structure[] = 'Contains emphasized key points';
        }

        if (preg_match('/(therefore|however|consequently|thus|hence|furthermore)/i', $text)) {
            $structure[] = 'Contains logical connectors and transitions';
        }

        // Extract key indicators
        $technicalTerms = [];
        $actionWords = [];

        // Look for technical/academic terms
        if (preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $text, $matches)) {
            $technicalTerms = array_slice($matches[0], 0, 5);
        }

        // Look for action/process words
        $actionPatterns = ['implement', 'apply', 'create', 'develop', 'analyze', 'evaluate', 'design', 'build', 'manage', 'process', 'system', 'method', 'approach', 'strategy', 'technique'];
        foreach ($actionPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) {
                $actionWords[] = $pattern;
            }
        }

        $analysis = "CONTENT TYPE: {$contentType}\n";
        $analysis .= "WORD COUNT: {$wordCount}, SENTENCES: {$sentenceCount}, PARAGRAPHS: " . ($paragraphCount + 1) . "\n";

        if (!empty($structureHints)) {
            $analysis .= "CONTENT HINTS: " . implode(', ', $structureHints) . "\n";
        }

        if (!empty($structure)) {
            $analysis .= "STRUCTURE: " . implode(', ', $structure) . "\n";
        }

        if (!empty($technicalTerms)) {
            $analysis .= "KEY TERMS: " . implode(', ', $technicalTerms) . "\n";
        }

        if (!empty($actionWords)) {
            $analysis .= "ACTION CONCEPTS: " . implode(', ', array_slice($actionWords, 0, 5)) . "\n";
        }

        return $analysis;
    }

    /**
     * Generate fallback summary when API is unavailable
     */
    private function generateFallbackSummary($text, $length, $format = 'paragraph') {
        // Enhanced content analysis
        $wordCount = str_word_count($text);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $paragraphs = preg_split('/\n\s*\n/', $text);

        // Extract first meaningful sentence
        $firstSentence = '';
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 20) {
                $firstSentence = $sentence;
                break;
            }
        }

        // Advanced keyword extraction with context
        $words = str_word_count(strtolower($text), 1);
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'also', 'then', 'here', 'there', 'when', 'where', 'how', 'why', 'what', 'which', 'who', 'all', 'some', 'many', 'much', 'few', 'little'];

        // Extract meaningful terms with better filtering
        $keyTerms = [];
        foreach ($words as $word) {
            if (strlen($word) > 3 && !in_array($word, $stopWords) && ctype_alpha($word)) {
                $keyTerms[] = $word;
            }
        }

        // Get term frequency for better ranking
        $termFrequency = array_count_values($keyTerms);
        arsort($termFrequency);
        $keyTerms = array_keys($termFrequency);
        $keyTerms = array_slice($keyTerms, 0, min(10, count($keyTerms)));

        // Detect content type and structure
        $contentType = $this->detectContentType($text);
        $structureElements = $this->analyzeStructureElements($text);

        // Auto-detect length if needed
        if ($length === 'auto') {
            if ($wordCount < 50 || count($sentences) < 3) {
                $length = 'short';
            } elseif ($wordCount < 500 || count($sentences) < 10) {
                $length = 'medium';
            } else {
                $length = 'long';
            }
        }

        switch ($length) {
            case 'short':
                if ($format === 'bullet_points') {
                    $summary = "• " . ($firstSentence ?: "This content explores key concepts and their applications.");
                    if (!empty($keyTerms)) {
                        $mainConcept = $keyTerms[0];
                        $summary .= "\n• The material centers on {$mainConcept} and its fundamental principles.";
                    }
                    return $summary;
                } else {
                    $summary = $firstSentence ?: "This content explores key concepts and their applications.";
                    if (!empty($keyTerms)) {
                        $mainConcept = $keyTerms[0];
                        $summary .= " The material centers on {$mainConcept} and its fundamental principles.";
                    }
                    return $summary;
                }

            case 'medium':
                if ($format === 'bullet_points') {
                    $summary = "• " . ($firstSentence ?: "This educational content covers important concepts and their relationships.");

                    // Add second sentence if available
                    if (isset($sentences[1]) && trim($sentences[1]) !== $firstSentence) {
                        $secondSentence = trim($sentences[1]);
                        if (strlen($secondSentence) > 15) {
                            $summary .= "\n• " . $secondSentence;
                        }
                    }

                    // Add key concepts and relationships
                    if (count($keyTerms) >= 2) {
                        $primaryTerms = array_slice($keyTerms, 0, min(3, count($keyTerms)));
                        $summary .= "\n• The discussion examines the connections between " . implode(" and ", array_slice($primaryTerms, 0, 2));
                        if (count($primaryTerms) > 2) {
                            $summary .= ", including " . $primaryTerms[2];
                        }
                    }

                    // Add content type insights
                    if ($contentType !== 'general') {
                        $summary .= "\n• This {$contentType} content provides structured insights into the topic.";
                    }

                    return $summary;
                } else {
                    $summary = $firstSentence ?: "This educational content covers important concepts and their relationships.";

                    // Add second sentence if available
                    if (isset($sentences[1]) && trim($sentences[1]) !== $firstSentence) {
                        $secondSentence = trim($sentences[1]);
                        if (strlen($secondSentence) > 15) {
                            $summary .= " " . $secondSentence;
                        }
                    }

                    // Add key concepts and relationships
                    if (count($keyTerms) >= 2) {
                        $primaryTerms = array_slice($keyTerms, 0, min(3, count($keyTerms)));
                        $summary .= " The discussion examines the connections between " . implode(" and ", array_slice($primaryTerms, 0, 2));
                        if (count($primaryTerms) > 2) {
                            $summary .= ", including " . $primaryTerms[2];
                        }
                        $summary .= ".";
                    }

                    // Add content type insights
                    if ($contentType !== 'general') {
                        $summary .= " This {$contentType} content provides structured insights into the topic.";
                    }

                    return $summary;
                }

            case 'long':
                if ($format === 'bullet_points') {
                    $summary = "• " . ($firstSentence ?: "This comprehensive content provides detailed insights into key concepts and their applications.");

                    // Add multiple sentences for depth
                    $addedSentences = 0;
                    for ($i = 1; $i < count($sentences) && $addedSentences < 2; $i++) {
                        $sentence = trim($sentences[$i]);
                        if (strlen($sentence) > 20 && $sentence !== $firstSentence) {
                            $summary .= "\n• " . $sentence;
                            $addedSentences++;
                        }
                    }

                    // Add comprehensive key concepts
                    if (!empty($keyTerms)) {
                        $summary .= "\n• Key concepts explored include " . implode(", ", array_slice($keyTerms, 0, min(5, count($keyTerms))));
                        if (count($keyTerms) > 5) {
                            $remaining = count($keyTerms) - 5;
                            $summary .= ", and " . min($remaining, 3) . " additional " . ($remaining === 1 ? "concept" : "concepts");
                        }
                    }

                    // Add structural insights
                    if (!empty($structureElements)) {
                        $summary .= "\n• The content is structured around " . implode(" and ", array_slice($structureElements, 0, 2));
                        if (count($structureElements) > 2) {
                            $summary .= ", providing a comprehensive framework for understanding";
                        }
                    }

                    // Add practical applications if detected
                    if ($this->hasPracticalContent($text)) {
                        $summary .= "\n• Practical applications and implementation considerations are emphasized throughout the material.";
                    }

                    return $summary;
                } else {
                    $summary = $firstSentence ?: "This comprehensive content provides detailed insights into key concepts and their applications.";

                    // Add multiple sentences for depth
                    $addedSentences = 0;
                    for ($i = 1; $i < count($sentences) && $addedSentences < 2; $i++) {
                        $sentence = trim($sentences[$i]);
                        if (strlen($sentence) > 20 && $sentence !== $firstSentence) {
                            $summary .= " " . $sentence;
                            $addedSentences++;
                        }
                    }

                    // Add comprehensive key concepts
                    if (!empty($keyTerms)) {
                        $summary .= " Key concepts explored include " . implode(", ", array_slice($keyTerms, 0, min(5, count($keyTerms))));
                        if (count($keyTerms) > 5) {
                            $remaining = count($keyTerms) - 5;
                            $summary .= ", and " . min($remaining, 3) . " additional " . ($remaining === 1 ? "concept" : "concepts");
                        }
                        $summary .= ".";
                    }

                    // Add structural insights
                    if (!empty($structureElements)) {
                        $summary .= " The content is structured around " . implode(" and ", array_slice($structureElements, 0, 2));
                        if (count($structureElements) > 2) {
                            $summary .= ", providing a comprehensive framework for understanding";
                        }
                        $summary .= ".";
                    }

                    // Add practical applications if detected
                    if ($this->hasPracticalContent($text)) {
                        $summary .= " Practical applications and implementation considerations are emphasized throughout the material.";
                    }

                    return $summary;
                }
        }

        // Fallback for any edge cases
        return "This educational content covers " . implode(", ", array_slice($keyTerms, 0, 3)) . " and related concepts, offering structured insights into their relationships and practical applications.";
    }

    /**
     * Detect content type for fallback processing
     */
    private function detectContentType($text) {
        $textLower = strtolower($text);

        if (preg_match('/(step|phase|stage|process|method|procedure)/i', $text)) {
            return 'process-oriented';
        }

        if (preg_match('/(theory|concept|principle|framework|model)/i', $text)) {
            return 'theoretical';
        }

        if (preg_match('/(application|practical|implementation|case|example|scenario)/i', $text)) {
            return 'applied';
        }

        if (preg_match('/(guide|tutorial|how-to|instruction)/i', $text)) {
            return 'instructional';
        }

        return 'general';
    }

    /**
     * Analyze structure elements for fallback processing
     */
    private function analyzeStructureElements($text) {
        $elements = [];

        if (preg_match_all('/(\d+\.|\•|\-|\*)/', $text) > 2) {
            $elements[] = 'organized lists';
        }

        if (preg_match('/(important|key|critical|essential|note|remember|highlight)/i', $text)) {
            $elements[] = 'key insights';
        }

        if (preg_match('/(therefore|however|consequently|thus|hence|furthermore|moreover)/i', $text)) {
            $elements[] = 'logical connections';
        }

        if (preg_match('/(example|case|scenario|illustration|instance)/i', $text)) {
            $elements[] = 'practical examples';
        }

        if (preg_match('/(benefit|advantage|outcome|result|impact)/i', $text)) {
            $elements[] = 'outcome analysis';
        }

        return $elements;
    }

    /**
     * Check if content has practical elements
     */
    private function hasPracticalContent($text) {
        $practicalIndicators = [
            'application', 'practical', 'implementation', 'apply', 'use', 'utilize',
            'practice', 'real-world', 'hands-on', 'implementation', 'deployment'
        ];

        $textLower = strtolower($text);
        foreach ($practicalIndicators as $indicator) {
            if (strpos($textLower, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }
}
?>