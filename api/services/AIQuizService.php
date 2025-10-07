<?php
// api/services/AIQuizService.php
require_once __DIR__ . '/GeminiAPIClient.php';

class AIQuizService {
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

        error_log("AI_QUIZ_PERF: " . json_encode($logData));
    }

    /**
     * Generate quiz from text content
     */
    public function generateQuiz($text, $options = []) {
        $startTime = microtime(true);
        $textLength = strlen($text);

        if (empty($text)) {
            $this->logPerformance('generateQuiz', $startTime, $textLength, false, 'No content provided');
            return ['quiz' => 'No content provided', 'questions' => []];
        }

        // Extract options with defaults
        $difficulty = $options['difficulty'] ?? 'medium';
        $count = $options['questionCount'] ?? 5;
        $noteTitle = $options['noteTitle'] ?? 'this study material';
        $quizType = $options['quizType'] ?? 'multiple_choice';

        if (!$this->apiClient->isAvailable()) {
            $result = $this->generateFallbackQuiz($text, $difficulty, $count, $noteTitle, $quizType);
            $this->logPerformance('generateQuiz_fallback', $startTime, $textLength, true);
            return $result;
        }

        try {
            $prompt = $this->buildQuizPrompt($text, $difficulty, $count, $noteTitle, $quizType);
            $response = $this->apiClient->call($prompt, ['temperature' => 0.2, 'maxTokens' => 1500]);

            if (!$response) {
                $result = $this->generateFallbackQuiz($text, $difficulty, $count, $noteTitle, $quizType);
                $this->logPerformance('generateQuiz_fallback_no_response', $startTime, $textLength, true);
                return $result;
            }

            $result = $this->parseQuizResponse($response, $quizType);
            $this->logPerformance('generateQuiz_ai', $startTime, $textLength, true);
            return $result;
        } catch (Exception $e) {
            error_log("Quiz generation failed: " . $e->getMessage());
            $result = $this->generateFallbackQuiz($text, $difficulty, $count, $noteTitle, $quizType);
            $this->logPerformance('generateQuiz_error_fallback', $startTime, $textLength, false, $e->getMessage());
            return $result;
        }
    }

    /**
     * Build quiz prompt for AI
     */
    private function buildQuizPrompt($text, $difficulty, $count, $noteTitle, $quizType = 'multiple_choice') {
        $difficultyLevels = [
            'easy' => 'simple recall questions testing basic facts and direct information',
            'medium' => 'questions requiring comprehension and understanding of key concepts and relationships',
            'hard' => 'analytical questions requiring critical thinking, inference, and application of concepts'
        ];

        $level = $difficultyLevels[$difficulty] ?? $difficultyLevels['medium'];

        // Different prompts based on quiz type
        switch ($quizType) {
            case 'true_false':
                return $this->buildTrueFalsePrompt($text, $difficulty, $count, $noteTitle, $level);
            case 'mixed':
                return $this->buildMixedPrompt($text, $difficulty, $count, $noteTitle, $level);
            case 'multiple_choice':
            default:
                return $this->buildMultipleChoicePrompt($text, $difficulty, $count, $noteTitle, $level);
        }
    }

    /**
     * Analyze content type and structure for better question generation
     */
    private function analyzeContentType($text) {
        $wordCount = str_word_count($text);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = count($sentences);

        // Detect content type patterns
        $isTechnical = preg_match_all('/\b(code|function|class|method|algorithm|system|process|framework|library|api|database|server|client)\b/i', $text);
        $isConceptual = preg_match_all('/\b(concept|theory|principle|understanding|knowledge|idea|model|approach|strategy)\b/i', $text);
        $isProcedural = preg_match_all('/\b(step|process|procedure|method|technique|how to|guide|instruction)\b/i', $text);
        $isHistorical = preg_match_all('/\b(history|developed|created|evolution|timeline|era|period)\b/i', $text);
        $isMathematical = preg_match_all('/\b(formula|equation|calculate|compute|algorithm|mathematical|theorem)\b/i', $text);

        $contentType = "general";
        if ($isTechnical > $isConceptual && $isTechnical > $isProcedural) {
            $contentType = "technical";
        } elseif ($isConceptual > $isTechnical && $isConceptual > $isProcedural) {
            $contentType = "conceptual";
        } elseif ($isProcedural > $isTechnical && $isProcedural > $isConceptual) {
            $contentType = "procedural";
        }

        // Extract key terms and concepts
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can'];
        $words = str_word_count(strtolower($text), 1);
        $keyTerms = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords) && !is_numeric($word);
        });
        $keyTerms = array_unique($keyTerms);
        $topKeyTerms = array_slice($keyTerms, 0, min(10, count($keyTerms)));

        return "CONTENT ANALYSIS:
- Word count: {$wordCount}
- Sentence count: {$sentenceCount}
- Content type: {$contentType}
- Key terms identified: " . implode(', ', $topKeyTerms) . "
- Technical indicators: {$isTechnical}
- Conceptual indicators: {$isConceptual}
- Procedural indicators: {$isProcedural}
- Historical indicators: {$isHistorical}
- Mathematical indicators: {$isMathematical}

QUESTION GENERATION GUIDANCE:
- For technical content: Focus on practical applications, implementation details, and technical relationships
- For conceptual content: Emphasize understanding, principles, and theoretical connections
- For procedural content: Include sequence questions, step-by-step processes, and methodology
- Ensure questions test actual knowledge from the content, not generic knowledge
- Balance questions across different sections/topics in the content";
    }

    /**
     * Build multiple choice quiz prompt
     */
    private function buildMultipleChoicePrompt($text, $difficulty, $count, $noteTitle, $level) {
        // Create difficulty-specific prompts
        $difficultySpecificInstructions = $this->getDifficultySpecificInstructions($difficulty);

        // Analyze content type and structure
        $contentAnalysis = $this->analyzeContentType($text);

        return "You are an expert educator and assessment specialist creating a comprehensive exam-style quiz based on the study material. Create {$count} high-quality multiple-choice questions that test deep understanding and critical thinking about the subject matter.

CONTENT ANALYSIS:
{$contentAnalysis}

CONTENT TO CREATE QUIZ FROM:
{$text}

QUIZ REQUIREMENTS:
1. Create questions in a traditional exam format - DO NOT reference the note or source material
2. Questions should be about the subject matter itself, not about the content source
3. Questions should test {$level} with varying complexity
4. Each question must be based on specific concepts, facts, or information from the content
5. Create sophisticated distractors (wrong answers) that represent common misconceptions or partial understanding
6. Questions should assess actual knowledge, understanding, application, and analysis
7. Ensure questions cover different aspects of the content (not all testing the same concepts)
8. Include questions that require synthesis of multiple ideas from the content

{$difficultySpecificInstructions}

QUESTION TYPES TO INCLUDE (mix these throughout):
- Definition and Concept Identification: \"What is the primary function of...\"
- Relationship Analysis: \"How does [concept A] interact with [concept B]?\"
- Application Scenarios: \"In which situation would you apply...\"
- Comparative Analysis: \"Which of the following is most similar to...\"
- Process Understanding: \"What is the correct sequence of...\"
- Critical Evaluation: \"Which statement best represents...\"
- Problem-Solving: \"What would be the most effective approach to...\"
- Cause and Effect: \"What is the most likely outcome of...\"
- Conceptual Understanding: \"Which of the following best explains...\"
- Analytical Reasoning: \"Based on the principles discussed, what can be concluded about...\"

ENHANCED ANSWER CHOICE REQUIREMENTS:
- All options should be realistic and educational
- Wrong answers should represent common mistakes or partial understanding
- Include options that show different levels of understanding
- Make distractors challenging but fair - they should be plausible but clearly incorrect
- Ensure correct answer is clearly the best choice, not just acceptable
- Avoid absolute terms like 'always' or 'never' in distractors unless truly incorrect
- Include distractors that represent:
  * Common misconceptions about the topic
  * Partial understanding of concepts
  * Confusion with related but different concepts
  * Overgeneralization or oversimplification
  * Misapplication of principles

QUESTION QUALITY VALIDATION:
- Each question must be answerable using ONLY the provided content
- Questions should not require external knowledge beyond what's in the content
- Avoid questions that could be answered correctly through general knowledge alone
- Ensure questions test specific information or relationships from the content
- Questions should discriminate between different levels of understanding

EXAMPLE QUESTION FORMATS (exam-style):
- \"What is the primary function of [key concept] in this context?\"
- \"Which of the following best describes the relationship between [concept A] and [concept B]?\"
- \"In which scenario would [approach] be most appropriate?\"
- \"What is the most significant advantage of [method]?\"
- \"Which statement most accurately represents [principle]?\"
- \"What would be the most effective strategy for [situation]?\"
- \"How does [concept] contribute to [larger goal]?\"
- \"Which of the following is NOT a characteristic of [topic]?\"
- \"What is the correct sequence for implementing [concept]?\"
- \"How does [concept] interact with other key concepts in this domain?\"
- \"Which of the following best explains why [concept] is important?\"
- \"What is the most likely consequence of [action] based on the principles discussed?\"

DIFFICULTY VARIATION:
- Include a mix of straightforward and challenging questions
- Some questions should require synthesis of multiple concepts
- Include questions that test understanding of nuances and exceptions

FORMAT REQUIREMENTS:
Return ONLY valid JSON with this exact structure:
{
  \"questions\": [
    {
      \"question\": \"What is the primary function of [key concept] in this context?\",
      \"options\": [
        \"A) Correct and complete answer\",
        \"B) Partially correct but incomplete\",
        \"C) Common misconception about the concept\",
        \"D) Completely incorrect or unrelated\"
      ],
      \"correct_answer\": \"A\"
    }
  ]
}

CRITICAL REQUIREMENTS:
- Questions must be in EXAM FORMAT - no references to \"this note\" or \"according to the content\"
- Focus on testing knowledge of the SUBJECT MATTER, not the source material
- Wrong answers should be sophisticated distractors representing real misconceptions
- Questions should assess genuine understanding, application, and critical thinking
- Each question should test specific knowledge from the content
- Answer choices should be educational and reveal different levels of understanding

Return only the JSON, no additional text or explanations.";
    }

    /**
     * Get difficulty-specific instructions for quiz generation
     */
    private function getDifficultySpecificInstructions($difficulty) {
        switch ($difficulty) {
            case 'easy':
                return "
DIFFICULTY-SPECIFIC REQUIREMENTS FOR EASY LEVEL:
- Focus on basic facts, definitions, and direct information recall
- Questions should test recognition of key terms and basic concepts
- Use straightforward language and clear question structures
- Include questions that can be answered directly from the content
- Distractors should be obviously incorrect or unrelated
- Examples: 'What is the definition of...', 'Which of the following is...', 'What is the primary purpose of...'
- Avoid complex scenarios, analysis, or application questions
- Keep questions simple and direct with minimal interpretation required";

            case 'medium':
                return "
DIFFICULTY-SPECIFIC REQUIREMENTS FOR MEDIUM LEVEL:
- Focus on understanding relationships and connections between concepts
- Questions should require comprehension and interpretation of information
- Include questions that test understanding of how concepts work together
- Mix direct recall with basic analysis and application
- Distractors should represent partial understanding or common misconceptions
- Examples: 'How does...affect...', 'What is the relationship between...', 'In which situation would...'
- Include some questions requiring synthesis of multiple ideas
- Balance between straightforward and moderately challenging questions";

            case 'hard':
                return "
DIFFICULTY-SPECIFIC REQUIREMENTS FOR HARD LEVEL:
- Focus on critical thinking, analysis, inference, and application
- Questions should require evaluation, synthesis, and problem-solving
- Include complex scenarios requiring strategic thinking
- Test understanding of nuances, exceptions, and advanced relationships
- Distractors should represent sophisticated misconceptions or partial theories
- Examples: 'What would be the most effective strategy for...', 'How would you evaluate...', 'What is the most significant implication of...'
- Include questions requiring integration of multiple concepts
- Emphasize analytical reasoning and application to new situations
- Questions should challenge students to think deeply and critically";

            default:
                return "
DIFFICULTY-SPECIFIC REQUIREMENTS FOR MEDIUM LEVEL:
- Focus on understanding relationships and connections between concepts
- Questions should require comprehension and interpretation of information
- Include questions that test understanding of how concepts work together
- Mix direct recall with basic analysis and application
- Distractors should represent partial understanding or common misconceptions";
        }
    }

    /**
     * Build true/false quiz prompt
     */
    private function buildTrueFalsePrompt($text, $difficulty, $count, $noteTitle, $level) {
        // Create difficulty-specific prompts for True/False
        $difficultySpecificInstructions = $this->getTrueFalseDifficultyInstructions($difficulty);

        // Analyze content type and structure
        $contentAnalysis = $this->analyzeContentType($text);

        return "You are an expert educator creating a comprehensive exam-style quiz based on the study material. Create {$count} high-quality true/false questions that test deep understanding and critical thinking about the subject matter.

CONTENT ANALYSIS:
{$contentAnalysis}

CONTENT TO CREATE QUIZ FROM:
{$text}

QUIZ REQUIREMENTS:
1. Create questions in a traditional exam format - DO NOT reference the note or source material
2. Questions should be about the subject matter itself, not about the content source
3. Questions should test {$level} with varying complexity
4. Each question must be based on specific concepts, facts, or information from the content
5. Create sophisticated statements that require careful analysis to determine true/false
6. Include statements that represent common misconceptions or partial understanding
7. Mix clearly true/false statements with nuanced ones that require deep understanding
8. Ensure statements test actual knowledge from the content, not generic knowledge

{$difficultySpecificInstructions}

QUESTION TYPES TO INCLUDE (mix these throughout):
- Factual Accuracy: \"[Concept] is primarily characterized by [specific attribute]\"
- Relationship Analysis: \"[Concept A] and [Concept B] have a direct causal relationship\"
- Application Scenarios: \"[Concept] would be most effective in [specific situation]\"
- Comparative Analysis: \"[Concept A] is more significant than [Concept B] in this context\"
- Process Understanding: \"The correct sequence involves [specific steps]\"
- Critical Evaluation: \"[Statement] represents the most accurate interpretation\"

TRUE/FALSE STATEMENT REQUIREMENTS:
- Statements should be nuanced and require deep understanding
- Some statements should be partially true but require qualification
- Include statements that represent common student misconceptions
- Mix clearly true, clearly false, and nuanced statements
- Ensure the true/false determination requires analysis of the content
- Avoid statements that can be answered through general knowledge alone
- Include statements that test specific relationships or details from the content
- Some statements should require synthesis of multiple concepts
- Balance statements that are obviously true/false with those requiring careful consideration

STATEMENT QUALITY VALIDATION:
- Each statement must be verifiable using ONLY the provided content
- Statements should not rely on external knowledge beyond what's in the content
- Avoid absolute statements unless they are clearly supported or contradicted by the content
- Include qualifiers like 'typically,' 'generally,' or 'in most cases' when appropriate
- Ensure false statements represent realistic misconceptions, not absurd claims

EXAMPLE STATEMENT FORMATS:
- \"The primary function of [key concept] is to [specific function]\"
- \"[Concept A] and [Concept B] work synergistically to achieve [outcome]\"
- \"In most cases, [approach] would be the most effective strategy for [situation]\"
- \"[Concept] represents a fundamental shift from traditional [previous approach]\"
- \"The most significant advantage of [method] is its [specific benefit]\"
- \"[Statement] is a common misconception about [topic]\"

DIFFICULTY VARIATION:
- Include straightforward factual statements and complex analytical ones
- Some statements should require synthesis of multiple concepts
- Include statements that test understanding of nuances and exceptions

FORMAT REQUIREMENTS:
Return ONLY valid JSON with this exact structure:
{
  \"questions\": [
    {
      \"question\": \"The primary function of [key concept] is to [specific function]\",
      \"options\": [
        \"A) True\",
        \"B) False\"
      ],
      \"correct_answer\": \"A\"
    }
  ]
}

CRITICAL REQUIREMENTS:
- Statements must be in EXAM FORMAT - no references to \"this note\" or \"according to the content\"
- Focus on testing knowledge of the SUBJECT MATTER, not the source material
- Statements should assess genuine understanding, application, and critical thinking
- Each statement should test specific knowledge from the content
- True/False determination should require careful analysis

Return only the JSON, no additional text or explanations.";
    }

    /**
     * Get difficulty-specific instructions for True/False questions
     */
    private function getTrueFalseDifficultyInstructions($difficulty) {
        switch ($difficulty) {
            case 'easy':
                return "
DIFFICULTY-SPECIFIC REQUIREMENTS FOR EASY TRUE/FALSE:
- Focus on clear, direct factual statements that can be answered with certainty
- Use straightforward statements about basic concepts and definitions
- Include obviously true or false statements based on direct information
- Avoid nuanced or ambiguous statements that require interpretation
- Examples: 'The sky is blue', 'Water boils at 100°C' (adapted to content)
- Statements should be clearly verifiable from the content without analysis";

            case 'medium':
                return "
DIFFICULTY-SPECIFIC REQUIREMENTS FOR MEDIUM TRUE/FALSE:
- Include statements that require understanding of relationships and connections
- Mix statements that are clearly true/false with those requiring some interpretation
- Include statements about how concepts work together or interact
- Some statements should represent common student misconceptions
- Examples: 'Concept A and Concept B work together to achieve X'
- Balance between direct facts and statements requiring comprehension";

            case 'hard':
                return "
DIFFICULTY-SPECIFIC REQUIREMENTS FOR HARD TRUE/FALSE:
- Create sophisticated statements requiring deep analysis and critical thinking
- Include nuanced statements that may be partially true but require qualification
- Statements should test understanding of complex relationships and implications
- Include statements that represent advanced misconceptions or partial theories
- Examples: 'The most significant implication of X is Y under complex scenario Z'
- Statements should challenge students to evaluate accuracy and context
- Include statements requiring synthesis of multiple concepts and ideas";

            default:
                return "
DIFFICULTY-SPECIFIC REQUIREMENTS FOR MEDIUM TRUE/FALSE:
- Include statements that require understanding of relationships and connections
- Mix statements that are clearly true/false with those requiring some interpretation
- Include statements about how concepts work together or interact
- Some statements should represent common student misconceptions";
        }
    }

    /**
     * Build mixed quiz prompt (combination of multiple choice and true/false)
     */
    private function buildMixedPrompt($text, $difficulty, $count, $noteTitle, $level) {
        $mcCount = ceil($count * 0.6); // 60% multiple choice
        $tfCount = $count - $mcCount; // 40% true/false

        // Get difficulty-specific instructions for both question types
        $mcInstructions = $this->getDifficultySpecificInstructions($difficulty);
        $tfInstructions = $this->getTrueFalseDifficultyInstructions($difficulty);

        return "You are an expert educator creating a comprehensive exam-style quiz based on the study material. Create a mixed quiz with {$mcCount} multiple-choice questions and {$tfCount} true/false questions that test deep understanding and critical thinking about the subject matter.

CONTENT TO CREATE QUIZ FROM:
{$text}

QUIZ REQUIREMENTS:
1. Create questions in a traditional exam format - DO NOT reference the note or source material
2. Questions should be about the subject matter itself, not about the content source
3. Questions should test {$level} with varying complexity
4. Each question must be based on specific concepts, facts, or information from the content
5. Mix multiple-choice and true/false questions throughout the quiz

MULTIPLE CHOICE QUESTIONS ({$mcCount} questions):
{$mcInstructions}
- Create sophisticated distractors (wrong answers) that represent common misconceptions
- Include 4 options (A, B, C, D) for each question
- Wrong answers should represent different levels of understanding

TRUE/FALSE QUESTIONS ({$tfCount} questions):
{$tfInstructions}
- Create nuanced statements requiring careful analysis
- Include statements that represent common misconceptions
- Mix clearly true, clearly false, and nuanced statements

QUESTION TYPES TO INCLUDE (mix these throughout):
- Definition and Concept Identification
- Relationship Analysis
- Application Scenarios
- Comparative Analysis
- Process Understanding
- Critical Evaluation
- Problem-Solving
- Cause and Effect

FORMAT REQUIREMENTS:
Return ONLY valid JSON with this exact structure:
{
  \"questions\": [
    {
      \"question\": \"What is the primary function of [key concept]?\",
      \"options\": [
        \"A) Correct and complete answer\",
        \"B) Partially correct but incomplete\",
        \"C) Common misconception\",
        \"D) Completely incorrect\"
      ],
      \"correct_answer\": \"A\"
    },
    {
      \"question\": \"[Concept] is primarily characterized by [specific attribute]\",
      \"options\": [
        \"A) True\",
        \"B) False\"
      ],
      \"correct_answer\": \"A\"
    }
  ]
}

CRITICAL REQUIREMENTS:
- Questions must be in EXAM FORMAT - no references to source material
- Focus on testing knowledge of the SUBJECT MATTER
- Mix question types throughout the quiz
- Each question should test specific knowledge from the content

Return only the JSON, no additional text or explanations.";
    }

    /**
     * Validate and balance quiz questions for diversity
     */
    private function validateAndBalanceQuestions($questions, $text, $count) {
        if (!is_array($questions) || count($questions) === 0) {
            return $questions;
        }

        // Extract key concepts from content for validation
        $words = str_word_count(strtolower($text), 1);
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can'];
        $keyConcepts = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords) && !is_numeric($word);
        });
        $keyConcepts = array_unique($keyConcepts);

        $validatedQuestions = [];
        $usedConcepts = [];
        $questionTypes = [];

        foreach ($questions as $question) {
            $questionText = strtolower($question['question'] ?? $question['text'] ?? '');

            // Check if question is too similar to existing ones
            $isTooSimilar = false;
            foreach ($validatedQuestions as $existing) {
                $existingText = strtolower($existing['question'] ?? $existing['text'] ?? '');
                similar_text($questionText, $existingText, $similarity);
                if ($similarity > 70) { // 70% similarity threshold
                    $isTooSimilar = true;
                    break;
                }
            }

            if ($isTooSimilar) {
                continue; // Skip this question
            }

            // Check if question tests a concept that's already been tested
            $questionConcepts = array_intersect($keyConcepts, explode(' ', $questionText));
            $overlap = array_intersect($questionConcepts, $usedConcepts);

            // Allow some overlap but not too much
            if (count($overlap) > count($questionConcepts) * 0.8) {
                continue; // Skip if 80% or more concepts already tested
            }

            // Track question types for diversity
            $type = $this->categorizeQuestionType($questionText);
            $questionTypes[] = $type;

            // Add used concepts
            $usedConcepts = array_merge($usedConcepts, $questionConcepts);
            $usedConcepts = array_unique($usedConcepts);

            $validatedQuestions[] = $question;

            // Stop if we have enough questions
            if (count($validatedQuestions) >= $count) {
                break;
            }
        }

        // If we don't have enough questions, add back some that were filtered out
        if (count($validatedQuestions) < $count && count($questions) > count($validatedQuestions)) {
            $remaining = array_slice($questions, count($validatedQuestions));
            foreach ($remaining as $question) {
                if (count($validatedQuestions) >= $count) break;
                $validatedQuestions[] = $question;
            }
        }

        return $validatedQuestions;
    }

    /**
     * Categorize question type for diversity analysis
     */
    private function categorizeQuestionType($questionText) {
        $text = strtolower($questionText);

        if (strpos($text, 'what is') === 0 || strpos($text, 'what is the') === 0) {
            return 'definition';
        } elseif (strpos($text, 'how does') !== false || strpos($text, 'how do') !== false) {
            return 'relationship';
        } elseif (strpos($text, 'in which') !== false || strpos($text, 'when') !== false) {
            return 'application';
        } elseif (strpos($text, 'what is the most') !== false || strpos($text, 'which of the following') !== false) {
            return 'comparison';
        } elseif (strpos($text, 'what is the correct sequence') !== false || strpos($text, 'what is the sequence') !== false) {
            return 'process';
        } elseif (strpos($text, 'which statement') !== false || strpos($text, 'which of the following') !== false) {
            return 'evaluation';
        } elseif (strpos($text, 'what would be') !== false) {
            return 'problem_solving';
        } else {
            return 'general';
        }
    }

    /**
     * Parse quiz response from AI
     */
    private function parseQuizResponse($response, $quizType = 'multiple_choice') {
        // Clean the response by removing markdown code blocks if present
        $cleanResponse = preg_replace('/```json\s*|\s*```/', '', $response);

        // Try to parse JSON response
        $json = json_decode($cleanResponse, true);
        if ($json && isset($json['questions'])) {
            // Validate and balance questions for better quality and diversity
            $originalText = ""; // We don't have access to original text here, but validation will still work
            $json['questions'] = $this->validateAndBalanceQuestions($json['questions'], $originalText, 5);
            return $json;
        }

        // Try to extract JSON from the response if it's embedded in text
        if (preg_match('/\{.*\}/s', $cleanResponse, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json && isset($json['questions'])) {
                return $json;
            }
        }

        // Fallback if JSON parsing fails
        return $this->generateFallbackQuiz("", "medium", 5, "this study material", $quizType);
    }

    /**
     * Generate fallback quiz when API is unavailable
     */
    private function generateFallbackQuiz($text, $difficulty, $count, $noteTitle, $quizType = 'multiple_choice') {
        // Extract key information from the text for exam-style questions
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = str_word_count(strtolower($text), 1);

        // Enhanced key term extraction with better NLP-like processing
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those'];

        // Extract compound terms (two-word phrases) first
        $compoundTerms = [];
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($sentences as $sentence) {
            // Look for technical compound terms
            if (preg_match_all('/\b(?:web|server|client|data|user|system|application|software|development|programming|database|framework|library|api|machine|learning|artificial|intelligence|neural|network|algorithm|function|method|class|object|variable|constant|array|string|integer|boolean)\s+(?:side|scripting|language|development|application|system|learning|intelligence|network|processing|management|interface|protocol|architecture|structure|component|element|feature|capability|functionality)\b/i', $sentence, $matches)) {
                $compoundTerms = array_merge($compoundTerms, $matches[0]);
            }
        }

        // Extract single meaningful terms
        $keyTerms = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 &&
                   !in_array($word, $stopWords) &&
                   !is_numeric($word) &&
                   !preg_match('/^\d/', $word) && // No words starting with numbers
                   preg_match('/[a-zA-Z]/', $word); // Must contain letters
        });
        $keyTerms = array_unique($keyTerms);

        // Categorize and prioritize terms
        $technicalTerms = [];
        $conceptTerms = [];
        $processTerms = [];
        $domainTerms = [];

        foreach ($keyTerms as $term) {
            $termLower = strtolower($term);

            // Technical programming terms
            if (preg_match('/\b(code|function|class|method|algorithm|system|process|framework|library|api|database|server|client|variable|constant|array|object|string|integer|boolean|script|query|table|column|index|key|value|type|interface|class|inheritance|polymorphism|encapsulation|abstraction)\b/i', $term)) {
                $technicalTerms[] = $term;
            }
            // Conceptual terms
            elseif (preg_match('/\b(concept|theory|principle|understanding|knowledge|idea|model|approach|strategy|technique|method|paradigm|pattern|design|architecture|structure|logic|reasoning|thinking|analysis|synthesis|evaluation)\b/i', $term)) {
                $conceptTerms[] = $term;
            }
            // Process/procedural terms
            elseif (preg_match('/\b(step|process|procedure|guide|instruction|implementation|development|creation|execution|operation|workflow|sequence|stage|phase|cycle|iteration|loop|condition|validation|verification|testing|deployment)\b/i', $term)) {
                $processTerms[] = $term;
            }
            // Domain-specific terms (PHP, ML, etc.)
            elseif (preg_match('/\b(php|javascript|python|java|machine|learning|artificial|intelligence|neural|network|deep|supervised|unsupervised|reinforcement|regression|classification|clustering|prediction|training|validation|testing|dataset|feature|label|accuracy|precision|recall|f1.score|confusion|matrix)\b/i', $term)) {
                $domainTerms[] = $term;
            }
        }

        // Combine compound terms with single terms, prioritizing by relevance
        $allTerms = array_merge($compoundTerms, $technicalTerms, $domainTerms, $conceptTerms, $processTerms);

        // Remove duplicates and limit
        $keyTerms = array_unique($allTerms);
        $keyTerms = array_slice($keyTerms, 0, min(20, count($keyTerms)));

        $questions = [];

        // Determine content type for better question generation
        $isTechnical = count(array_intersect($keyTerms, ['code', 'function', 'class', 'method', 'algorithm', 'system', 'process', 'framework', 'library', 'api', 'database'])) > 0;
        $isConceptual = count(array_intersect($keyTerms, ['concept', 'theory', 'principle', 'understanding', 'knowledge', 'idea', 'model', 'approach'])) > 0;
        $isProcedural = count(array_intersect($keyTerms, ['step', 'process', 'procedure', 'method', 'technique', 'guide', 'instruction'])) > 0;

        // Generate questions based on quiz type
        for ($i = 0; $i < $count; $i++) {
            if ($quizType === 'true_false') {
                // Generate True/False questions
                $question = $this->generateEnhancedTrueFalseQuestion($text, $sentences, $keyTerms, $i, $isTechnical, $isConceptual, $isProcedural);
                $options = ["A) True", "B) False"];
                $correctAnswer = $question['correct'] ? 'A' : 'B';
                $questionText = $question['text'];
            } elseif ($quizType === 'mixed') {
                // Mix of multiple choice and true/false
                if ($i % 3 === 2) { // Every 3rd question is true/false
                    $question = $this->generateEnhancedTrueFalseQuestion($text, $sentences, $keyTerms, $i, $isTechnical, $isConceptual, $isProcedural);
                    $options = ["A) True", "B) False"];
                    $correctAnswer = $question['correct'] ? 'A' : 'B';
                    $questionText = $question['text'];
                } else {
                    // Multiple choice question
                    $result = $this->generateEnhancedMultipleChoiceQuestion($text, $sentences, $keyTerms, $i, $isTechnical, $isConceptual, $isProcedural);
                    $questionText = $result['question'];
                    $options = $result['options'];
                    $correctAnswer = 'A';
                }
            } else {
                // Default multiple choice
                $result = $this->generateEnhancedMultipleChoiceQuestion($text, $sentences, $keyTerms, $i, $isTechnical, $isConceptual, $isProcedural);
                $questionText = $result['question'];
                $options = $result['options'];
                $correctAnswer = 'A';
            }

            $questions[] = [
                'question' => $questionText,
                'options' => $options,
                'correct_answer' => $correctAnswer
            ];
        }

        return [
            'quiz' => 'AI quiz generation unavailable - using exam-style fallback questions',
            'questions' => $questions
        ];
    }

    /**
     * Generate a true/false question
     */
    private function generateTrueFalseQuestion($text, $sentences, $keyTerms, $index) {
        if (count($sentences) > 0 && count($keyTerms) > 0) {
            $keyTerm = $keyTerms[$index % count($keyTerms)];

            $trueFalseStatements = [
                ["text" => "{$keyTerm} serves as a fundamental building block with comprehensive functionality", "correct" => true],
                ["text" => "{$keyTerm} has minimal impact and operates in isolation", "correct" => false],
                ["text" => "{$keyTerm} establishes direct connections and influences outcomes significantly", "correct" => true],
                ["text" => "{$keyTerm} is completely unrelated to the core subject matter", "correct" => false],
                ["text" => "{$keyTerm} provides basic utility but lacks advanced features", "correct" => false],
                ["text" => "{$keyTerm} functions independently without integration capabilities", "correct" => false],
                ["text" => "The primary function of {$keyTerm} is to serve as a comprehensive framework", "correct" => true],
                ["text" => "{$keyTerm} creates barriers rather than facilitating relationships", "correct" => false]
            ];

            return $trueFalseStatements[$index % count($trueFalseStatements)];
        } else {
            $genericStatements = [
                ["text" => "The main concept discussed serves as a fundamental framework guiding the entire approach", "correct" => true],
                ["text" => "The discussed methodology provides basic structure with limited scope", "correct" => false],
                ["text" => "Key concepts work synergistically to create comprehensive understanding", "correct" => true],
                ["text" => "The presented approach functions as supplementary support rather than core functionality", "correct" => false],
                ["text" => "The core principle represents a fundamental shift from traditional methods", "correct" => true],
                ["text" => "The discussed concepts are completely unrelated and serve different purposes", "correct" => false]
            ];

            return $genericStatements[$index % count($genericStatements)];
        }
    }

    /**
     * Generate an enhanced multiple choice question with better content awareness
     */
    private function generateEnhancedMultipleChoiceQuestion($text, $sentences, $keyTerms, $index, $isTechnical, $isConceptual, $isProcedural) {
        if (count($sentences) > 0 && count($keyTerms) > 0) {
            $keyTerm = $keyTerms[$index % count($keyTerms)];

            // Content-aware question types
            if ($isTechnical) {
                $questionTypes = [
                    "What is the primary function of {$keyTerm} in software development?",
                    "Which of the following best describes how {$keyTerm} operates in this context?",
                    "In which scenario would {$keyTerm} be most appropriately implemented?",
                    "What is the most significant advantage of using {$keyTerm} in this system?",
                    "Which statement most accurately represents the concept of {$keyTerm}?",
                    "What would be the most effective approach when working with {$keyTerm}?",
                    "How does {$keyTerm} contribute to overall system architecture?",
                    "Which of the following is NOT a characteristic of {$keyTerm}?",
                    "What is the correct sequence for implementing {$keyTerm}?",
                    "How does {$keyTerm} interact with other components in this framework?"
                ];
            } elseif ($isConceptual) {
                $questionTypes = [
                    "What is the primary principle underlying {$keyTerm}?",
                    "Which of the following best describes the relationship between {$keyTerm} and related concepts?",
                    "In which situation would {$keyTerm} be most appropriately applied?",
                    "What is the most significant advantage of understanding {$keyTerm}?",
                    "Which statement most accurately represents the theory of {$keyTerm}?",
                    "What would be the most effective strategy for implementing {$keyTerm}?",
                    "How does {$keyTerm} contribute to the broader understanding of this topic?",
                    "Which of the following is NOT a fundamental aspect of {$keyTerm}?",
                    "What is the correct sequence for developing {$keyTerm}?",
                    "How does {$keyTerm} interact with other key concepts in this domain?"
                ];
            } elseif ($isProcedural) {
                $questionTypes = [
                    "What is the primary step involved in {$keyTerm}?",
                    "Which of the following best describes the process of {$keyTerm}?",
                    "In which scenario would {$keyTerm} be most appropriately executed?",
                    "What is the most significant advantage of following {$keyTerm}?",
                    "Which statement most accurately represents the methodology of {$keyTerm}?",
                    "What would be the most effective approach for implementing {$keyTerm}?",
                    "How does {$keyTerm} contribute to achieving the desired outcome?",
                    "Which of the following is NOT a step in {$keyTerm}?",
                    "What is the correct sequence for {$keyTerm}?",
                    "How does {$keyTerm} interact with other processes in this workflow?"
                ];
            } else {
                $questionTypes = [
                    "What is the primary function of {$keyTerm} in this context?",
                    "Which of the following best describes the relationship between {$keyTerm} and the main topic?",
                    "In which scenario would {$keyTerm} be most appropriately applied?",
                    "What is the most significant advantage of using {$keyTerm}?",
                    "Which statement most accurately represents the concept of {$keyTerm}?",
                    "What would be the most effective approach when working with {$keyTerm}?",
                    "How does {$keyTerm} contribute to achieving the overall objectives?",
                    "Which of the following is NOT a characteristic of {$keyTerm}?",
                    "What is the correct sequence for implementing {$keyTerm}?",
                    "How does {$keyTerm} interact with other key concepts in this domain?"
                ];
            }

            $question = $questionTypes[$index % count($questionTypes)];

            // Enhanced answer sets based on content type
            if ($isTechnical) {
                $answerSets = [
                    [
                        "{$keyTerm} serves as a fundamental building block with comprehensive functionality and integration capabilities",
                        "{$keyTerm} provides basic utility but lacks advanced features and scalability",
                        "{$keyTerm} is primarily decorative with limited practical application in development",
                        "{$keyTerm} functions independently without any integration with other system components"
                    ],
                    [
                        "{$keyTerm} establishes direct connections and influences system behavior significantly",
                        "{$keyTerm} has minimal impact and operates in isolation from other components",
                        "{$keyTerm} creates barriers rather than facilitating component interaction",
                        "{$keyTerm} is completely unrelated to the core system architecture"
                    ]
                ];
            } elseif ($isConceptual) {
                $answerSets = [
                    [
                        "{$keyTerm} serves as a fundamental principle guiding the entire theoretical framework",
                        "{$keyTerm} provides basic understanding but lacks depth in theoretical application",
                        "{$keyTerm} is primarily supplementary with limited theoretical significance",
                        "{$keyTerm} functions independently without connection to other theoretical concepts"
                    ],
                    [
                        "{$keyTerm} establishes direct relationships and influences theoretical understanding significantly",
                        "{$keyTerm} has minimal impact and operates in isolation from other concepts",
                        "{$keyTerm} creates confusion rather than facilitating theoretical clarity",
                        "{$keyTerm} is completely unrelated to the core theoretical framework"
                    ]
                ];
            } else {
                $answerSets = [
                    [
                        "{$keyTerm} serves as a fundamental building block with comprehensive functionality",
                        "{$keyTerm} provides basic utility but lacks advanced features",
                        "{$keyTerm} is primarily decorative with limited practical application",
                        "{$keyTerm} functions independently without integration capabilities"
                    ],
                    [
                        "{$keyTerm} establishes direct connections and influences outcomes significantly",
                        "{$keyTerm} has minimal impact and operates in isolation",
                        "{$keyTerm} creates barriers rather than facilitating relationships",
                        "{$keyTerm} is completely unrelated to the core subject matter"
                    ]
                ];
            }

            $options = $answerSets[$index % count($answerSets)];
        } else {
            // Fallback for when key terms are not available
            $examQuestions = [
                "What is the primary function of the main concept discussed?",
                "Which of the following best describes the relationship between key ideas?",
                "In which scenario would the discussed approach be most effective?",
                "What is the most significant advantage of the presented methodology?",
                "Which statement most accurately represents the core principle?",
                "What would be the most effective strategy for implementation?",
                "How do the concepts contribute to achieving the stated objectives?",
                "Which of the following is NOT a characteristic of the main topic?",
                "What is the correct sequence for applying the discussed principles?",
                "How do the key concepts interact within this domain?"
            ];

            $question = $examQuestions[$index % count($examQuestions)];

            $genericAnswerSets = [
                [
                    "A) Serves as a fundamental framework guiding the entire approach",
                    "B) Provides basic structure with limited scope and application",
                    "C) Functions as supplementary support rather than core functionality",
                    "D) Operates independently without connection to other elements"
                ],
                [
                    "A) They work synergistically to create comprehensive understanding",
                    "B) They function independently with minimal interaction",
                    "C) They compete with each other for dominance in the system",
                    "D) They are completely unrelated and serve different purposes"
                ]
            ];

            $options = $genericAnswerSets[$index % count($genericAnswerSets)];
        }

        return [
            'question' => $question,
            'options' => $options
        ];
    }

    /**
     * Generate an enhanced true/false question with better content awareness
     */
    private function generateEnhancedTrueFalseQuestion($text, $sentences, $keyTerms, $index, $isTechnical, $isConceptual, $isProcedural) {
        if (count($sentences) > 0 && count($keyTerms) > 0) {
            $keyTerm = $keyTerms[$index % count($keyTerms)];

            // Content-aware true/false statements
            if ($isTechnical) {
                $trueFalseStatements = [
                    ["text" => "{$keyTerm} serves as a fundamental building block with comprehensive functionality and integration capabilities", "correct" => true],
                    ["text" => "{$keyTerm} provides basic utility but lacks advanced features and scalability", "correct" => false],
                    ["text" => "{$keyTerm} establishes direct connections and influences system behavior significantly", "correct" => true],
                    ["text" => "{$keyTerm} creates barriers rather than facilitating component interaction", "correct" => false],
                    ["text" => "{$keyTerm} functions independently without any integration with other system components", "correct" => false],
                    ["text" => "The primary function of {$keyTerm} is to serve as a comprehensive framework in software development", "correct" => true],
                    ["text" => "{$keyTerm} has minimal impact and operates in isolation from other components", "correct" => false],
                    ["text" => "{$keyTerm} represents a core concept in modern software architecture", "correct" => true],
                    ["text" => "{$keyTerm} is completely unrelated to the core system architecture", "correct" => false],
                    ["text" => "{$keyTerm} requires integration with other components to function effectively", "correct" => true]
                ];
            } elseif ($isConceptual) {
                $trueFalseStatements = [
                    ["text" => "{$keyTerm} serves as a fundamental principle guiding the entire theoretical framework", "correct" => true],
                    ["text" => "{$keyTerm} provides basic understanding but lacks depth in theoretical application", "correct" => false],
                    ["text" => "{$keyTerm} establishes direct relationships and influences theoretical understanding significantly", "correct" => true],
                    ["text" => "{$keyTerm} creates confusion rather than facilitating theoretical clarity", "correct" => false],
                    ["text" => "{$keyTerm} functions independently without connection to other theoretical concepts", "correct" => false],
                    ["text" => "The primary principle of {$keyTerm} guides the entire theoretical approach", "correct" => true],
                    ["text" => "{$keyTerm} has minimal impact and operates in isolation from other concepts", "correct" => false],
                    ["text" => "{$keyTerm} represents a core concept in this theoretical framework", "correct" => true],
                    ["text" => "{$keyTerm} is completely unrelated to the core theoretical framework", "correct" => false],
                    ["text" => "{$keyTerm} requires connection to other concepts to be fully understood", "correct" => true]
                ];
            } elseif ($isProcedural) {
                $trueFalseStatements = [
                    ["text" => "{$keyTerm} serves as a fundamental step in the overall process", "correct" => true],
                    ["text" => "{$keyTerm} provides basic guidance but lacks detailed procedural information", "correct" => false],
                    ["text" => "{$keyTerm} establishes direct connections and influences process outcomes significantly", "correct" => true],
                    ["text" => "{$keyTerm} creates obstacles rather than facilitating process completion", "correct" => false],
                    ["text" => "{$keyTerm} functions independently without connection to other process steps", "correct" => false],
                    ["text" => "The primary step of {$keyTerm} is essential for successful completion", "correct" => true],
                    ["text" => "{$keyTerm} has minimal impact and operates in isolation from other steps", "correct" => false],
                    ["text" => "{$keyTerm} represents a core step in this procedural framework", "correct" => true],
                    ["text" => "{$keyTerm} is completely unrelated to the core process framework", "correct" => false],
                    ["text" => "{$keyTerm} requires coordination with other steps to be effective", "correct" => true]
                ];
            } else {
                $trueFalseStatements = [
                    ["text" => "{$keyTerm} serves as a fundamental building block with comprehensive functionality", "correct" => true],
                    ["text" => "{$keyTerm} provides basic utility but lacks advanced features", "correct" => false],
                    ["text" => "{$keyTerm} establishes direct connections and influences outcomes significantly", "correct" => true],
                    ["text" => "{$keyTerm} creates barriers rather than facilitating relationships", "correct" => false],
                    ["text" => "{$keyTerm} functions independently without integration capabilities", "correct" => false],
                    ["text" => "The primary function of {$keyTerm} is to serve as a comprehensive framework", "correct" => true],
                    ["text" => "{$keyTerm} has minimal impact and operates in isolation", "correct" => false],
                    ["text" => "{$keyTerm} represents a core concept in this domain", "correct" => true],
                    ["text" => "{$keyTerm} is completely unrelated to the core subject matter", "correct" => false],
                    ["text" => "{$keyTerm} requires integration with other elements to function effectively", "correct" => true]
                ];
            }

            return $trueFalseStatements[$index % count($trueFalseStatements)];
        } else {
            // Fallback for when key terms are not available
            $genericStatements = [
                ["text" => "The main concept discussed serves as a fundamental framework guiding the entire approach", "correct" => true],
                ["text" => "The discussed methodology provides basic structure with limited scope", "correct" => false],
                ["text" => "Key concepts work synergistically to create comprehensive understanding", "correct" => true],
                ["text" => "The presented approach functions as supplementary support rather than core functionality", "correct" => false],
                ["text" => "The core principle represents a fundamental shift from traditional methods", "correct" => true],
                ["text" => "The discussed concepts are completely unrelated and serve different purposes", "correct" => false],
                ["text" => "The methodology requires integration with other approaches to be effective", "correct" => true],
                ["text" => "The concepts operate independently without any interaction", "correct" => false],
                ["text" => "Understanding these concepts is essential for successful implementation", "correct" => true],
                ["text" => "The concepts are interchangeable and serve the same purpose", "correct" => false]
            ];

            return $genericStatements[$index % count($genericStatements)];
        }
    }

    /**
     * Generate a multiple choice question
     */
    private function generateMultipleChoiceQuestion($text, $sentences, $keyTerms, $index) {
        if (count($sentences) > 0 && count($keyTerms) > 0) {
            $keyTerm = $keyTerms[$index % count($keyTerms)];

            $questionTypes = [
                "What is the primary function of {$keyTerm} in this context?",
                "Which of the following best describes the relationship between {$keyTerm} and the main topic?",
                "In which scenario would {$keyTerm} be most appropriately applied?",
                "What is the most significant advantage of using {$keyTerm}?",
                "Which statement most accurately represents the concept of {$keyTerm}?",
                "What would be the most effective approach when working with {$keyTerm}?",
                "How does {$keyTerm} contribute to achieving the overall objectives?",
                "Which of the following is NOT a characteristic of {$keyTerm}?",
                "What is the correct sequence for implementing {$keyTerm}?",
                "How does {$keyTerm} interact with other key concepts in this domain?"
            ];

            $question = $questionTypes[$index % count($questionTypes)];

            $answerSets = [
                [
                    "{$keyTerm} serves as a fundamental building block with comprehensive functionality",
                    "{$keyTerm} provides basic utility but lacks advanced features",
                    "{$keyTerm} is primarily decorative with limited practical application",
                    "{$keyTerm} functions independently without integration capabilities"
                ],
                [
                    "{$keyTerm} establishes direct connections and influences outcomes significantly",
                    "{$keyTerm} has minimal impact and operates in isolation",
                    "{$keyTerm} creates barriers rather than facilitating relationships",
                    "{$keyTerm} is completely unrelated to the core subject matter"
                ]
            ];

            $options = $answerSets[$index % count($answerSets)];
        } else {
            $examQuestions = [
                "What is the primary function of the main concept discussed?",
                "Which of the following best describes the relationship between key ideas?",
                "In which scenario would the discussed approach be most effective?",
                "What is the most significant advantage of the presented methodology?",
                "Which statement most accurately represents the core principle?",
                "What would be the most effective strategy for implementation?",
                "How do the concepts contribute to achieving the stated objectives?",
                "Which of the following is NOT a characteristic of the main topic?",
                "What is the correct sequence for applying the discussed principles?",
                "How do the key concepts interact within this domain?"
            ];

            $question = $examQuestions[$index % count($examQuestions)];

            $genericAnswerSets = [
                [
                    "A) Serves as a fundamental framework guiding the entire approach",
                    "B) Provides basic structure with limited scope and application",
                    "C) Functions as supplementary support rather than core functionality",
                    "D) Operates independently without connection to other elements"
                ],
                [
                    "A) They work synergistically to create comprehensive understanding",
                    "B) They function independently with minimal interaction",
                    "C) They compete with each other for dominance in the system",
                    "D) They are completely unrelated and serve different purposes"
                ]
            ];

            $options = $genericAnswerSets[$index % count($genericAnswerSets)];
        }

        return [
            'question' => $question,
            'options' => $options
        ];
    }
}
?>