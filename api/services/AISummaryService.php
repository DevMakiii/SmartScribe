<?php
// api/services/AISummaryService.php
require_once __DIR__ . '/GeminiAPIClient.php';

class AISummaryService {
    private $apiClient;

    public function __construct() {
        $this->apiClient = new GeminiAPIClient();
    }

    /**
     * Log performance metrics for monitoring
     */
    private function logPerformance($operation, $startTime, $textLength, $success = true, $error = null) {
        $duration = microtime(true) - $startTime;
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'operation' => $operation,
            'duration' => round($duration, 3),
            'text_length' => $textLength,
            'success' => $success,
            'memory_usage' => memory_get_peak_usage(true)
        ];

        if ($error) {
            $logData['error'] = $error;
        }

        error_log("AI_SUMMARY_PERF: " . json_encode($logData));
    }

    /**
     * Generate summary from text content using advanced techniques
     */
    public function generateSummary($text, $length = 'auto', $format = 'paragraph', $keywords = null) {
        $startTime = microtime(true);
        $textLength = strlen($text);

        if (empty($text)) {
            $this->logPerformance('generateSummary', $startTime, $textLength, false, 'Empty text provided');
            return null;
        }

        // Auto-detect appropriate length based on input text - more generous for longer, descriptive summaries
        if ($length === 'auto') {
            $wordCount = str_word_count($text);
            $sentenceCount = preg_match_all('/[.!?]+/', $text, $matches);

            if ($wordCount < 30 || $sentenceCount < 2) {
                $length = 'short';
            } elseif ($wordCount < 200 || $sentenceCount < 5) {
                $length = 'medium';
            } else {
                $length = 'long'; // Default to long for more descriptive summaries
            }
        }

        // Handle very long texts with chunking
        $wordCount = str_word_count($text);
        if ($wordCount > 2000) {
            $result = $this->generateSummaryFromChunks($text, $length, $format);
            $this->logPerformance('generateSummary_chunked', $startTime, $textLength, true);
            return $result;
        }

        if (!$this->apiClient->isAvailable()) {
            error_log("Gemini API not available, using fallback summary generation");
            $result = $this->generateFallbackSummary($text, $length, $format);
            $this->logPerformance('generateSummary_fallback', $startTime, $textLength, true);
            return $result;
        }

        try {
            // Use advanced multi-pass summarization for highest quality
            $result = $this->generateAdvancedSummary($text, $length, $format, $keywords);
            $this->logPerformance('generateSummary_advanced', $startTime, $textLength, true);
            return $result;
        } catch (Exception $e) {
            error_log("Advanced summarization failed, falling back to standard method: " . $e->getMessage());
            try {
                $prompt = $this->buildSummaryPrompt($text, $length, $format);
                $initialSummary = $this->apiClient->call($prompt, ['temperature' => 0.1, 'maxTokens' => 1000]);
                $result = $this->validateAndRefineSummary($text, $initialSummary, $length, $format);
                $this->logPerformance('generateSummary_standard', $startTime, $textLength, true);
                return $result;
            } catch (Exception $e2) {
                error_log("Gemini API call failed in generateSummary: " . $e2->getMessage() . " | Text length: " . strlen($text) . " | Length: {$length} | Format: {$format}");
                $result = $this->generateFallbackSummary($text, $length, $format);
                $this->logPerformance('generateSummary_fallback_error', $startTime, $textLength, false, $e2->getMessage());
                return $result;
            }
        }
    }

    /**
     * Generate advanced high-quality summary using multiple techniques
     */
    private function generateAdvancedSummary($text, $length, $format, $keywords = null) {
        // Step 1: Extract key elements for hybrid approach
        $keyElements = $this->extractKeyElements($text, $keywords);

        // Step 2: Generate multiple summary passes
        $pass1Summary = $this->generateFirstPassSummary($text, $length, $format, $keyElements);
        $pass2Summary = $this->generateSecondPassSummary($text, $pass1Summary, $length, $format, $keyElements);

        // Step 3: Combine and refine
        $finalSummary = $this->combineSummaryPasses($text, $pass1Summary, $pass2Summary, $length, $format, $keyElements);

        // Step 4: Final validation and enhancement
        return $this->finalEnhancement($text, $finalSummary, $length, $format, $keyElements);
    }

    /**
     * Extract key elements for hybrid summarization
     */
    private function extractKeyElements($text, $keywords = null) {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $keyTerms = $keywords ?: $this->extractKeyTerms($text);
        $contentType = $this->detectPreciseContentType($text);

        // Extract most important sentences using advanced scoring
        $scoredSentences = [];
        foreach ($sentences as $index => $sentence) {
            $score = $this->scoreSentenceImportance($sentence, $keyTerms, $index, count($sentences));
            $scoredSentences[] = [
                'text' => trim($sentence),
                'score' => $score,
                'index' => $index
            ];
        }

        usort($scoredSentences, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return [
            'keyTerms' => $keyTerms,
            'contentType' => $contentType,
            'topSentences' => array_slice($scoredSentences, 0, min(8, count($scoredSentences))),
            'structureElements' => $this->analyzeStructureElements($text),
            'wordCount' => str_word_count($text),
            'sentenceCount' => count($sentences)
        ];
    }

    /**
     * Advanced sentence importance scoring
     */
    private function scoreSentenceImportance($sentence, $keyTerms, $position, $totalSentences) {
        $words = str_word_count(strtolower($sentence), 1);
        $termMatches = 0;
        $totalWords = count($words);

        // Count key term matches
        $termSet = array_flip($keyTerms);
        foreach ($words as $word) {
            if (isset($termSet[$word])) {
                $termMatches++;
            }
        }

        // Calculate various scoring factors
        $termDensity = $termMatches / max($totalWords, 1);
        $positionBonus = 1 / (1 + $position * 0.1); // Earlier sentences preferred
        $lengthBonus = min($totalWords / 15, 1.2); // Optimal length bonus
        $uniquenessBonus = $this->calculateSentenceUniqueness($sentence, $words);

        return ($termDensity * 0.4) + ($positionBonus * 0.25) + ($lengthBonus * 0.2) + ($uniquenessBonus * 0.15);
    }

    /**
     * Calculate sentence uniqueness (lower scores for repetitive content)
     */
    private function calculateSentenceUniqueness($sentence, $words) {
        $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were'];
        $uniqueWords = array_diff($words, $commonWords);
        return min(count($uniqueWords) / max(count($words), 1), 1);
    }

    /**
     * Generate summary from long text using chunking strategy
     */
    private function generateSummaryFromChunks($text, $length, $format) {
        // Split text into logical chunks
        $chunks = $this->splitTextIntoChunks($text, 1500); // ~1500 words per chunk

        if (count($chunks) <= 1) {
            // If only one chunk, proceed with normal generation
            return $this->generateSummary($chunks[0], $length, $format);
        }

        // Generate summaries for each chunk
        $chunkSummaries = [];
        foreach ($chunks as $chunk) {
            $chunkSummary = $this->generateSummary($chunk, 'medium', 'paragraph');
            if ($chunkSummary) {
                $chunkSummaries[] = $chunkSummary;
            }
        }

        if (empty($chunkSummaries)) {
            return $this->generateFallbackSummary($text, $length, $format);
        }

        // Combine chunk summaries into final summary
        $combinedText = implode("\n\n", $chunkSummaries);
        $finalPrompt = "You have received summaries of different sections of a longer document. Create a cohesive {$length} summary that integrates all the key points from these section summaries.

SECTION SUMMARIES:
{$combinedText}

Create a unified summary that flows naturally and eliminates redundancy while preserving all important information.";

        try {
            $finalSummary = $this->apiClient->call($finalPrompt, ['temperature' => 0.1, 'maxTokens' => 1000]);
            return $this->validateAndRefineSummary($text, $finalSummary, $length, $format);
        } catch (Exception $e) {
            error_log("Final summary generation failed: " . $e->getMessage());
            // Fallback: combine chunk summaries manually
            return $this->combineChunkSummaries($chunkSummaries, $length, $format);
        }
    }

    /**
     * Split text into logical chunks
     */
    private function splitTextIntoChunks($text, $maxWordsPerChunk = 1500) {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $currentChunk = '';
        $currentWordCount = 0;

        foreach ($sentences as $sentence) {
            $sentenceWordCount = str_word_count($sentence);

            if ($currentWordCount + $sentenceWordCount > $maxWordsPerChunk && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;
                $currentWordCount = $sentenceWordCount;
            } else {
                $currentChunk .= ' ' . $sentence;
                $currentWordCount += $sentenceWordCount;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Combine chunk summaries when API fails
     */
    private function combineChunkSummaries($chunkSummaries, $length, $format) {
        $combinedText = implode(' ', $chunkSummaries);

        // Extract key sentences using simple scoring
        $sentences = preg_split('/[.!?]+/', $combinedText, -1, PREG_SPLIT_NO_EMPTY);
        $keySentences = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 20) {
                $keySentences[] = $sentence;
            }
        }

        // Limit based on desired length - more descriptive
        $maxSentences = ['short' => 3, 'medium' => 6, 'long' => 10];
        $limit = $maxSentences[$length] ?? 6;
        $selectedSentences = array_slice($keySentences, 0, $limit);

        if ($format === 'bullet_points') {
            return implode("\n• ", array_map(function($s) { return "• " . $s; }, $selectedSentences));
        } else {
            return implode(' ', $selectedSentences) . '.';
        }
    }

    /**
     * Validate and refine summary quality
     */
    public function validateAndRefineSummary($originalText, $summary, $length, $format = 'paragraph') {
        if (empty($summary) || !$this->apiClient->isAvailable()) {
            return $summary;
        }

        try {
            $validationPrompt = $this->buildValidationPrompt($originalText, $summary, $length, $format);
            $validationResult = $this->apiClient->call($validationPrompt, ['temperature' => 0.2, 'maxTokens' => 500]);

            // Parse validation result
            $validationData = json_decode($validationResult, true);
            if ($validationData && isset($validationData['quality_score'])) {
                $qualityScore = $validationData['quality_score'];

                // If quality is below threshold, attempt refinement
                if ($qualityScore < 7.0) {
                    error_log("Summary quality score: {$qualityScore}, attempting refinement");
                    return $this->refineSummary($originalText, $summary, $validationData['issues'] ?? [], $length, $format);
                }
            }

            return $summary;
        } catch (Exception $e) {
            error_log("Summary validation failed: " . $e->getMessage() . " | Original text length: " . strlen($originalText) . " | Summary length: " . strlen($summary));
            return $summary;
        }
    }

    /**
     * Build validation prompt for AI
     */
    private function buildValidationPrompt($originalText, $summary, $length, $format) {
        return "You are a summary quality evaluator. Analyze the provided summary against the original text and return a JSON response with quality metrics.

ORIGINAL TEXT:
{$originalText}

GENERATED SUMMARY:
{$summary}

EVALUATION CRITERIA:
1. Completeness: Does it cover all major concepts? (0-10)
2. Accuracy: Is all information factually correct? (0-10)
3. Conciseness: Is it appropriately concise for the length? (0-10)
4. Clarity: Is the language clear and understandable? (0-10)
5. Structure: Is it well-organized and logical? (0-10)
6. Relevance: Does it focus on the most important information? (0-10)

EXPECTED LENGTH: {$length}
FORMAT: {$format}

Return ONLY a JSON object in this exact format:
{
  \"quality_score\": <average of all criteria>,
  \"completeness\": <score>,
  \"accuracy\": <score>,
  \"conciseness\": <score>,
  \"clarity\": <score>,
  \"structure\": <score>,
  \"relevance\": <score>,
  \"issues\": [\"list of specific issues found\"],
  \"strengths\": [\"list of strengths\"]
}";
    }

    /**
     * Refine summary based on validation feedback
     */
    private function refineSummary($originalText, $summary, $issues, $length, $format) {
        if (empty($issues)) {
            return $summary;
        }

        $issuesText = implode(', ', $issues);

        $refinementPrompt = "You are a summary refinement specialist. The original summary has these issues: {$issuesText}

ORIGINAL TEXT:
{$originalText}

CURRENT SUMMARY:
{$summary}

LENGTH: {$length}
FORMAT: {$format}

Please provide an improved version that addresses these issues while maintaining the appropriate length and format. Focus on fixing the identified problems without introducing new issues.

Return ONLY the refined summary text, no explanations or labels.";

        try {
            return $this->apiClient->call($refinementPrompt, ['temperature' => 0.3, 'maxTokens' => 1000]);
        } catch (Exception $e) {
            error_log("Summary refinement failed: " . $e->getMessage() . " | Issues: " . implode(', ', $issues));
            return $summary;
        }
    }

    /**
     * Generate first pass summary (extractive focus)
     */
    private function generateFirstPassSummary($text, $length, $format, $keyElements) {
        $topSentences = array_slice($keyElements['topSentences'], 0, min(5, count($keyElements['topSentences'])));
        $extractedText = implode(' ', array_column($topSentences, 'text'));

        $prompt = "FIRST PASS EXTRACTION: Extract and organize the most important information from the provided key sentences.

KEY SENTENCES FROM ORIGINAL TEXT:
{$extractedText}

CONTENT TYPE: {$keyElements['contentType']}
KEY TERMS: " . implode(', ', $keyElements['keyTerms']) . "
EXTRACTED KEYWORDS: " . (is_array($keyElements['keyTerms']) ? implode(', ', $keyElements['keyTerms']) : $keyElements['keyTerms']) . "

TASK: Create a structured extraction that captures:
1. Main concepts and their relationships
2. Key facts, data, or examples mentioned
3. Important processes, methods, or steps
4. Critical insights or conclusions
5. Any specific terminology or definitions

Present this as a clear, organized list or structured paragraphs that preserve the essential meaning and connections between ideas.

EXTRACTION:";

        return $this->apiClient->call($prompt, ['temperature' => 0.1, 'maxTokens' => 800]);
    }

    /**
     * Generate second pass summary (abstractive synthesis)
     */
    private function generateSecondPassSummary($text, $firstPassSummary, $length, $format, $keyElements) {
        $lengthGuide = [
            'short' => 'highly concise overview (2-3 key points)',
            'medium' => 'focused summary (4-6 key points)',
            'long' => 'comprehensive yet concise synthesis (8-10 focused points)'
        ];

        $formatGuide = $format === 'bullet_points' ? 'bullet points' : 'coherent paragraphs';

        $prompt = "SECOND PASS SYNTHESIS: Transform the extracted information into a high-quality, user-friendly summary.

FIRST PASS EXTRACTION:
{$firstPassSummary}

ORIGINAL TEXT LENGTH: {$keyElements['wordCount']} words
CONTENT TYPE: {$keyElements['contentType']}
KEY TERMS: " . (is_array($keyElements['keyTerms']) ? implode(', ', $keyElements['keyTerms']) : $keyElements['keyTerms']) . "
DESIRED LENGTH: {$lengthGuide[$length]}
OUTPUT FORMAT: {$formatGuide}

SYNTHESIS REQUIREMENTS:
1. CLARITY: Use simple, clear language that anyone can understand
2. COMPLETENESS: Cover all major concepts without overwhelming detail
3. CONNECTIONS: Show how different ideas relate to each other
4. CONTEXT: Provide background that makes the content accessible
5. VALUE: Highlight practical applications and key takeaways
6. FLOW: Create natural progression from basic to advanced concepts

SPECIAL INSTRUCTIONS FOR {$keyElements['contentType']}:
" . $this->getSynthesisInstructions($keyElements['contentType']) . "

Create a summary that not only informs but also helps the reader understand and apply the knowledge.

FINAL SYNTHESIS:";

        return $this->apiClient->call($prompt, ['temperature' => 0.2, 'maxTokens' => 1200]);
    }

    /**
     * Get specialized synthesis instructions for content type
     */
    private function getSynthesisInstructions($contentType) {
        $instructions = [
            'academic_research' => 'Explain research significance, methodology in simple terms, key findings with real-world impact, and implications for the field. Avoid jargon or explain it clearly. Include study limitations and future research directions.',
            'technical_documentation' => 'Break down complex technical concepts into understandable steps. Focus on what users need to know and how to apply the information practically. Include code examples or technical specifications where relevant.',
            'business_professional' => 'Translate business concepts into everyday language. Explain strategies, outcomes, and decisions in terms of real-world benefits and challenges. Include metrics and stakeholder impacts.',
            'educational' => 'Structure information to support learning progression. Include explanations, examples, and connections that help build understanding. Emphasize learning objectives and key takeaways.',
            'creative_literary' => 'Capture the essence of themes, characters, and narrative while making literary concepts accessible to general readers. Include emotional impact and artistic significance.',
            'legal' => 'Explain legal concepts in plain language. Focus on practical implications, rights, and responsibilities without legal jargon. Include real-world consequences and compliance requirements.',
            'medical_health' => 'Use clear, non-technical language to explain medical concepts. Include practical advice and what patients need to understand. Emphasize prevention and treatment options.',
            'news_article' => 'Summarize key facts, context, and implications. Include who, what, when, where, why, and how. Maintain journalistic objectivity and highlight newsworthy elements.',
            'blog_post' => 'Capture the author\'s perspective, key arguments, and personal insights. Include practical tips or advice while acknowledging the subjective nature of the content.',
            'scientific_paper' => 'Explain the research question, methodology, key findings, and scientific implications. Include statistical significance and contributions to the field.',
            'code_documentation' => 'Explain functionality, usage requirements, and implementation details. Focus on practical coding applications and best practices.',
            'process_methodology' => 'Break down processes into clear, actionable steps. Explain why each step matters, potential challenges, and expected outcomes.',
            'conceptual_theoretical' => 'Make abstract concepts concrete through examples and analogies. Show how theories apply to real situations and their broader implications.',
            'applied' => 'Emphasize practical applications and real-world benefits. Include tips, examples, and actionable insights with measurable outcomes.'
        ];

        return $instructions[$contentType] ?? 'Make complex information accessible by using clear language, examples, and logical organization.';
    }

    /**
     * Combine multiple summary passes into final version
     */
    private function combineSummaryPasses($text, $pass1Summary, $pass2Summary, $length, $format, $keyElements) {
        $prompt = "FINAL INTEGRATION: Combine the extraction and synthesis into the highest quality summary.

EXTRACTION (Key Information):
{$pass1Summary}

SYNTHESIS (User-Friendly Summary):
{$pass2Summary}

CONTENT TYPE: {$keyElements['contentType']}
KEY TERMS: " . (is_array($keyElements['keyTerms']) ? implode(', ', $keyElements['keyTerms']) : $keyElements['keyTerms']) . "
LENGTH: {$length}
FORMAT: {$format}

INTEGRATION TASK:
Create a final summary that:
1. PRESERVES ACCURACY: All facts and key information from the extraction
2. MAXIMIZES CLARITY: Uses the clear language and structure from the synthesis
3. OPTIMIZES LENGTH: Fits the requested length perfectly
4. ENHANCES UNDERSTANDING: Includes explanations and context that make complex ideas accessible
5. MAINTAINS FLOW: Creates natural, logical progression

QUALITY CHECKS:
- Is every important concept covered?
- Is the language clear and accessible?
- Does it flow naturally from start to finish?
- Would this help someone understand and use the information?
- Is it neither too brief nor overwhelming?

FINAL SUMMARY:";

        return $this->apiClient->call($prompt, ['temperature' => 0.15, 'maxTokens' => 1500]);
    }

    /**
     * Final enhancement and quality assurance
     */
    private function finalEnhancement($text, $summary, $length, $format, $keyElements) {
        // Validate and refine the final summary
        $enhancedSummary = $this->validateAndRefineSummary($text, $summary, $length, $format);

        // Add final polish for clarity and understanding
        return $this->addClarityEnhancements($enhancedSummary, $keyElements, $length, $format);
    }

    /**
     * Add final clarity enhancements
     */
    private function addClarityEnhancements($summary, $keyElements, $length, $format) {
        if (strlen($summary) < 100) {
            return $summary; // Too short to enhance
        }

        $enhancementPrompt = "CLARITY ENHANCEMENT: Review and improve this summary for maximum user understanding.

CURRENT SUMMARY:
{$summary}

CONTENT TYPE: {$keyElements['contentType']}
LENGTH: {$length}
FORMAT: {$format}

ENHANCEMENT FOCUS:
1. Replace any remaining technical jargon with clear explanations
2. Add brief examples where concepts might be unclear
3. Ensure logical flow between ideas
4. Add transitional phrases for better readability
5. Include practical context where helpful
6. Make sure the summary serves as a complete, standalone explanation

Only make changes that improve clarity and understanding. If the summary is already clear, return it unchanged.

ENHANCED SUMMARY:";

        try {
            return $this->apiClient->call($enhancementPrompt, ['temperature' => 0.1, 'maxTokens' => 1200]);
        } catch (Exception $e) {
            error_log("Clarity enhancement failed: " . $e->getMessage());
            return $summary;
        }
    }

    /**
     * Build summary prompt for AI
     */
    private function buildSummaryPrompt($text, $length, $format = 'paragraph') {
        // Analyze content type and structure
        $contentAnalysis = $this->analyzeContentStructure($text);
        $contentType = $this->detectPreciseContentType($text);

        // Get specialized instructions based on content type
        $specializedInstructions = $this->getSpecializedInstructions($contentType, $format);

        if ($format === 'bullet_points') {
            $lengthInstructions = [
                'short' => 'Create a descriptive bullet point summary with 3-4 key points that provide clear understanding and context. Include essential details, explanations, and practical insights to help users fully grasp the main concepts.',
                'medium' => 'Create a comprehensive bullet point summary with 6-8 detailed points covering all major concepts, relationships, applications, and insights. Provide context and explanations for better understanding.',
                'long' => 'Create an extensive, highly descriptive bullet point summary with 10-12 detailed points that provide deep understanding. Include comprehensive explanations, examples, context, practical applications, and actionable insights to maximize user comprehension.'
            ];
        } else {
            $lengthInstructions = [
                'short' => 'Create a clear and descriptive 2-3 sentence summary that explains the main concepts with sufficient context and detail for good understanding. Include key insights and practical implications.',
                'medium' => 'Create a comprehensive summary of 4-6 sentences that thoroughly explains all major concepts, relationships, and applications. Provide detailed context, examples, and insights to ensure complete understanding.',
                'long' => 'Create an extensive, highly detailed summary of 7-10 sentences that provides deep understanding of all concepts, relationships, applications, and implications. Include comprehensive explanations, examples, context, and practical guidance to maximize educational value.'
            ];
        }

        $instruction = $lengthInstructions[$length] ?? $lengthInstructions['medium'];

        return "You are an expert educator and content analyst specializing in creating high-quality, pedagogically sound summaries. Your task is to create a comprehensive, well-structured summary that captures the complete essence of the provided content while maximizing educational value and practical utility.

{$instruction}

CONTENT ANALYSIS:
{$contentAnalysis}

CONTENT TYPE: {$contentType}
{$specializedInstructions}

CONTENT TO SUMMARIZE:
{$text}

ENHANCED SUMMARY REQUIREMENTS:
1. DESCRIPTIVENESS: Provide detailed explanations and context for all key concepts to ensure complete understanding
2. CLARITY: Use clear, accessible language with explanations of technical terms and complex ideas
3. COMPREHENSIVENESS: Include ALL major concepts, their relationships, interconnections, and practical implications
4. STRUCTURE: Begin with core concepts, then build understanding through detailed explanations and examples
5. PRACTICAL VALUE: Highlight real-world applications, benefits, and actionable insights with concrete examples
6. EDUCATIONAL VALUE: Focus on insights that enhance understanding, retention, and practical application
7. CONTEXT: Provide background information and explanations that make complex topics accessible to learners
8. SYNTHESIS: Demonstrate how different concepts work together as a cohesive, interconnected system with clear relationships

CONTENT-SPECIFIC GUIDELINES:
- If this is a process/methodology: Explain the steps, rationale, expected outcomes, and potential challenges
- If this is a concept/theory: Cover definition, components, applications, limitations, and real-world relevance
- If this is a comparison/analysis: Highlight similarities, differences, trade-offs, and contextual implications
- If this is a problem/solution: Explain the problem context, solution approach, benefits, and implementation considerations
- If this is a case study/example: Extract generalizable principles, lessons learned, and broader applications
- If this is research/academic: Include methodology, findings, implications, and future research directions
- If this is technical documentation: Cover functionality, usage, requirements, and best practices

SUMMARY QUALITY STANDARDS:
- Start with the most fundamental concept or main idea that provides context for everything else
- Explain how components interact, support, and depend on each other
- Include specific examples, data points, or applications where mentioned in the original content
- Highlight critical distinctions, important nuances, and potential misconceptions
- Connect ideas to show the bigger picture, broader implications, and interconnected relationships
- End with key takeaways that reinforce the main value proposition and practical significance

FORMATTING REQUIREMENTS:
- Use clear, natural language that flows conversationally while maintaining professional tone
- Maintain appropriate academic or technical rigor based on content complexity
- Create logical paragraph breaks or bullet separations for better readability and comprehension
- Ensure each sentence or point adds unique, non-redundant value to the summary
- Balance comprehensiveness with conciseness - be thorough but not verbose
- For bullet point format: Use • symbol for each bullet, keep each point focused but complete, ensure logical flow and hierarchy between points

QUALITY ASSURANCE CHECKLIST:
- Does this summary capture the core essence and main value proposition?
- Are all major concepts and their relationships adequately represented?
- Does it provide practical insights or applications?
- Is the language clear, accurate, and appropriate for the audience?
- Does it flow logically from basic to advanced concepts?
- Would this summary help someone understand and apply the original content?

IMPORTANT: Provide ONLY the summary text itself. Do NOT include any labels, prefixes, or titles like 'Summary:', 'Detailed Summary:', or similar. Start directly with the summary content and maintain a natural, flowing narrative that reads like a well-written, pedagogically sound educational piece.";
    }

    /**
     * Detect precise content type for specialized processing
     */
    private function detectPreciseContentType($text) {
        $textLower = strtolower($text);
        $wordCount = str_word_count($text);
        $sentences = preg_match_all('/[.!?]+/', $text, $matches);

        // Score different content types
        $scores = [
            'academic_research' => 0,
            'technical_documentation' => 0,
            'business_professional' => 0,
            'educational' => 0,
            'creative_literary' => 0,
            'legal' => 0,
            'medical_health' => 0,
            'news_article' => 0,
            'blog_post' => 0,
            'scientific_paper' => 0,
            'code_documentation' => 0,
            'process_methodology' => 0
        ];

        // Academic/Research indicators
        $academicPatterns = ['research', 'study', 'methodology', 'findings', 'hypothesis', 'conclusion', 'abstract', 'literature', 'peer review', 'experiment', 'data analysis', 'statistical'];
        foreach ($academicPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['academic_research']++;
        }

        // Technical documentation
        $techPatterns = ['api', 'documentation', 'manual', 'guide', 'tutorial', 'configuration', 'deployment', 'installation', 'framework', 'library', 'function', 'method', 'class'];
        foreach ($techPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['technical_documentation']++;
        }

        // Business content
        $businessPatterns = ['business', 'strategy', 'market', 'analysis', 'report', 'meeting', 'agenda', 'minutes', 'presentation', 'revenue', 'profit', 'stakeholder', 'ROI'];
        foreach ($businessPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['business_professional']++;
        }

        // Educational content
        $eduPatterns = ['lesson', 'course', 'curriculum', 'learning', 'teaching', 'student', 'assignment', 'exam', 'quiz', 'chapter', 'module', 'objective'];
        foreach ($eduPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['educational']++;
        }

        // Creative/Literary content
        $creativePatterns = ['story', 'narrative', 'character', 'plot', 'theme', 'literature', 'fiction', 'poetry', 'novel', 'author', 'setting', 'dialogue'];
        foreach ($creativePatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['creative_literary']++;
        }

        // Legal content
        $legalPatterns = ['contract', 'agreement', 'law', 'legal', 'regulation', 'policy', 'compliance', 'rights', 'obligation', 'clause', 'jurisdiction'];
        foreach ($legalPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['legal']++;
        }

        // Medical/Health content
        $medicalPatterns = ['medical', 'health', 'patient', 'treatment', 'diagnosis', 'symptoms', 'clinical', 'therapy', 'disease', 'condition', 'medication'];
        foreach ($medicalPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['medical_health']++;
        }

        // News article
        $newsPatterns = ['breaking', 'news', 'reported', 'according to', 'source', 'incident', 'event', 'occurred', 'statement', 'official'];
        foreach ($newsPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['news_article']++;
        }

        // Blog post
        $blogPatterns = ['blog', 'post', 'opinion', 'personal', 'experience', 'thoughts', 'perspective', 'tips', 'advice', 'guide'];
        foreach ($blogPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['blog_post']++;
        }

        // Scientific paper
        $sciencePatterns = ['abstract', 'introduction', 'methodology', 'results', 'discussion', 'conclusion', 'references', 'doi', 'et al'];
        foreach ($sciencePatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['scientific_paper']++;
        }

        // Code documentation
        $codePatterns = ['function', 'parameter', 'return', 'variable', 'class', 'method', 'interface', 'import', 'export', 'syntax'];
        foreach ($codePatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['code_documentation']++;
        }

        // Process/Methodology
        $processPatterns = ['step', 'process', 'procedure', 'method', 'approach', 'workflow', 'sequence', 'phase', 'stage'];
        foreach ($processPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) $scores['process_methodology']++;
        }

        // Find the highest scoring content type
        arsort($scores);
        $topType = key($scores);

        // If top score is 0 or very low confidence, use fallback
        if ($scores[$topType] === 0) {
            return $this->detectContentType($text);
        }

        return $topType;
    }

    /**
     * Get specialized instructions based on content type
     */
    private function getSpecializedInstructions($contentType, $format) {
        $instructions = [
            'academic_research' => [
                'paragraph' => 'Focus on research methodology, key findings, implications for the field, and connections to existing literature. Include statistical significance where mentioned and highlight novel contributions.',
                'bullet_points' => 'Structure around: research question, methodology, key findings, implications, and limitations. Include specific data points and statistical results.'
            ],
            'technical_documentation' => [
                'paragraph' => 'Emphasize functionality, usage requirements, implementation steps, and best practices. Include code examples or technical specifications where relevant.',
                'bullet_points' => 'Organize by: purpose, requirements, implementation steps, usage examples, and troubleshooting considerations.'
            ],
            'business_professional' => [
                'paragraph' => 'Highlight business objectives, strategies, outcomes, and actionable recommendations. Include metrics, timelines, and stakeholder implications.',
                'bullet_points' => 'Structure around: objectives, strategies, implementation, results, and recommendations. Include specific metrics and timelines.'
            ],
            'educational' => [
                'paragraph' => 'Focus on learning objectives, key concepts, teaching methods, and assessment approaches. Include pedagogical principles and learning outcomes.',
                'bullet_points' => 'Organize by: learning objectives, key concepts, teaching strategies, assessment methods, and learning outcomes.'
            ],
            'creative_literary' => [
                'paragraph' => 'Capture themes, character development, narrative structure, and literary devices. Include emotional impact and artistic significance.',
                'bullet_points' => 'Structure around: plot summary, character analysis, themes, literary devices, and critical interpretation.'
            ],
            'legal' => [
                'paragraph' => 'Emphasize legal principles, requirements, implications, and compliance considerations. Include relevant precedents or regulations.',
                'bullet_points' => 'Organize by: legal issue, applicable laws, analysis, implications, and recommendations.'
            ],
            'medical_health' => [
                'paragraph' => 'Focus on medical conditions, treatments, outcomes, and patient care considerations. Include evidence-based information and clinical significance.',
                'bullet_points' => 'Structure around: condition/symptoms, diagnosis, treatment, prognosis, and preventive measures.'
            ],
            'process_methodology' => [
                'paragraph' => 'Detail the process steps, rationale, expected outcomes, and potential challenges. Include decision points and success criteria.',
                'bullet_points' => 'Organize by: process overview, step-by-step instructions, decision criteria, expected outcomes, and troubleshooting.'
            ],
            'conceptual_theoretical' => [
                'paragraph' => 'Explain the theory/concept, its components, applications, and limitations. Include foundational principles and real-world connections.',
                'bullet_points' => 'Structure around: definition, key components, applications, limitations, and examples.'
            ],
            'applied' => [
                'paragraph' => 'Emphasize practical applications, implementation strategies, benefits, and real-world examples. Include success factors and challenges.',
                'bullet_points' => 'Organize by: application context, implementation approach, benefits, challenges, and best practices.'
            ]
        ];

        return $instructions[$contentType][$format] ?? 'Provide a balanced summary covering key concepts, applications, and insights.';
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

        // Advanced keyword extraction with TF-IDF-like scoring
        $keyTerms = $this->extractKeyTerms($text);

        // Detect content type and structure
        $contentType = $this->detectPreciseContentType($text);
        $structureElements = $this->analyzeStructureElements($text);

        // Extract key sentences using scoring algorithm
        $keySentences = $this->extractKeySentences($sentences, $keyTerms);

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

        // Build summary using key sentences and terms
        return $this->buildIntelligentFallbackSummary($keySentences, $keyTerms, $contentType, $structureElements, $length, $format);
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
     * Extract key terms using advanced filtering and scoring
     */
    private function extractKeyTerms($text) {
        $words = str_word_count(strtolower($text), 1);

        // Enhanced stop words list
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'also', 'then', 'here', 'there', 'when', 'where', 'how', 'why', 'what', 'which', 'who', 'all', 'some', 'many', 'much', 'few', 'little', 'said', 'says', 'say', 'one', 'two', 'three', 'first', 'second', 'third', 'new', 'old', 'good', 'bad', 'big', 'small', 'long', 'short', 'right', 'wrong', 'high', 'low', 'early', 'late', 'hard', 'easy', 'fast', 'slow', 'hot', 'cold', 'full', 'empty'];

        // Extract meaningful terms with better filtering
        $candidates = [];
        foreach ($words as $word) {
            if (strlen($word) > 3 && !in_array($word, $stopWords) && ctype_alpha($word)) {
                $candidates[] = $word;
            }
        }

        // Calculate term frequency and position scores
        $termStats = [];
        foreach ($candidates as $index => $word) {
            if (!isset($termStats[$word])) {
                $termStats[$word] = ['frequency' => 0, 'positions' => [], 'length' => strlen($word)];
            }
            $termStats[$word]['frequency']++;
            $termStats[$word]['positions'][] = $index;
        }

        // Score terms based on frequency, position, and length
        $scoredTerms = [];
        foreach ($termStats as $term => $stats) {
            $frequencyScore = $stats['frequency'];
            $positionScore = 1 / (1 + min($stats['positions'])); // Earlier appearance is better
            $lengthScore = min($stats['length'] / 10, 1); // Longer terms get slight bonus

            $totalScore = $frequencyScore * $positionScore * (1 + $lengthScore * 0.1);
            $scoredTerms[$term] = $totalScore;
        }

        arsort($scoredTerms);
        return array_slice(array_keys($scoredTerms), 0, min(15, count($scoredTerms)));
    }

    /**
     * Extract key sentences using scoring algorithm
     */
    private function extractKeySentences($sentences, $keyTerms) {
        $keySentences = [];
        $termSet = array_flip($keyTerms);

        foreach ($sentences as $index => $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) < 20) continue;

            $words = str_word_count(strtolower($sentence), 1);
            $termMatches = 0;
            $totalWords = count($words);

            // Count key term matches
            foreach ($words as $word) {
                if (isset($termSet[$word])) {
                    $termMatches++;
                }
            }

            // Calculate sentence score
            $termDensity = $termMatches / max($totalWords, 1);
            $positionBonus = 1 / (1 + $index * 0.1); // Earlier sentences get bonus
            $lengthBonus = min($totalWords / 20, 1); // Optimal length bonus

            $score = ($termDensity * 0.6) + ($positionBonus * 0.3) + ($lengthBonus * 0.1);

            $keySentences[] = [
                'text' => $sentence,
                'score' => $score,
                'index' => $index
            ];
        }

        // Sort by score and return top sentences
        usort($keySentences, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($keySentences, 0, min(10, count($keySentences)));
    }

    /**
     * Build intelligent fallback summary using scored sentences and terms
     */
    private function buildIntelligentFallbackSummary($keySentences, $keyTerms, $contentType, $structureElements, $length, $format) {
        // Sort sentences by score and position for natural flow
        usort($keySentences, function($a, $b) {
            if (abs($a['score'] - $b['score']) < 0.1) {
                return $a['index'] <=> $b['index']; // Prefer earlier sentences if scores are similar
            }
            return $b['score'] <=> $a['score'];
        });

        $maxSentences = ['short' => 2, 'medium' => 5, 'long' => 8];
        $sentenceLimit = $maxSentences[$length] ?? 5;
        $selectedSentences = array_slice($keySentences, 0, $sentenceLimit);

        // Re-sort by original position for coherent flow
        usort($selectedSentences, function($a, $b) {
            return $a['index'] <=> $b['index'];
        });

        if ($format === 'bullet_points') {
            $summary = "";
            foreach ($selectedSentences as $sentenceData) {
                $summary .= "• " . $sentenceData['text'] . "\n";
            }

            // Add key terms if we have space and they're not redundant - more concise
            if (count($keyTerms) >= 2 && strlen($summary) < 300) {
                $termSummary = "• Key concepts: " . implode(", ", array_slice($keyTerms, 0, min(3, count($keyTerms))));
                $summary .= $termSummary;
            }

            return trim($summary);
        } else {
            // Build paragraph summary with proper sentence formatting
            $sentences = array_column($selectedSentences, 'text');
            $summary = '';

            foreach ($sentences as $index => $sentence) {
                $sentence = trim($sentence);
                if (!empty($sentence)) {
                    if ($index > 0) {
                        $summary .= ' ' . $sentence;
                    } else {
                        $summary .= $sentence;
                    }
                }
            }

            // Ensure the summary ends with proper punctuation
            if (!preg_match('/[.!?]$/', $summary)) {
                $summary .= '.';
            }

            // Add key terms integration if appropriate - more descriptive
            if (count($keyTerms) >= 2 && strlen($summary) < 300) {
                $termPhrase = " Key concepts discussed include " . implode(", ", array_slice($keyTerms, 0, min(3, count($keyTerms)))) . ".";
                $summary .= $termPhrase;
            }

            // Add content type context for better understanding
            if ($contentType !== 'general' && strlen($summary) < 500) {
                $typeDescriptions = [
                    'academic_research' => 'This research provides valuable insights into current findings and methodologies',
                    'technical_documentation' => 'This technical guide covers essential specifications and implementation details',
                    'business_professional' => 'This business analysis offers practical insights and strategic recommendations',
                    'educational' => 'This educational content provides comprehensive learning materials and key concepts',
                    'process_methodology' => 'This methodological approach outlines systematic procedures and best practices',
                    'conceptual_theoretical' => 'This theoretical framework explains fundamental concepts and their relationships',
                    'applied' => 'This practical guide demonstrates real-world applications and implementation strategies'
                ];

                if (isset($typeDescriptions[$contentType])) {
                    $summary .= " " . $typeDescriptions[$contentType] . ".";
                }
            }

            return $summary;
        }
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