<?php
// api/services/AIQuizService.php
require_once __DIR__ . '/GeminiAPIClient.php';

class AIQuizService {
    private $apiClient;

    public function __construct() {
        $this->apiClient = new GeminiAPIClient();
    }

    /**
     * Generate quiz from text content
     */
    public function generateQuiz($text, $options = []) {
        if (empty($text)) {
            return ['quiz' => 'No content provided', 'questions' => []];
        }

        // Extract options with defaults
        $difficulty = $options['difficulty'] ?? 'medium';
        $count = $options['questionCount'] ?? 5;
        $noteTitle = $options['noteTitle'] ?? 'this study material';
        $quizType = $options['quizType'] ?? 'multiple_choice';

        if (!$this->apiClient->isAvailable()) {
            return $this->generateFallbackQuiz($text, $difficulty, $count, $noteTitle, $quizType);
        }

        $prompt = $this->buildQuizPrompt($text, $difficulty, $count, $noteTitle, $quizType);
        $response = $this->apiClient->call($prompt, ['temperature' => 0.2, 'maxTokens' => 1500]);

        if (!$response) {
            return $this->generateFallbackQuiz($text, $difficulty, $count, $noteTitle, $quizType);
        }

        return $this->parseQuizResponse($response, $quizType);
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
     * Build multiple choice quiz prompt
     */
    private function buildMultipleChoicePrompt($text, $difficulty, $count, $noteTitle, $level) {
        // Create difficulty-specific prompts
        $difficultySpecificInstructions = $this->getDifficultySpecificInstructions($difficulty);

        return "You are an expert educator creating a comprehensive exam-style quiz based on the study material. Create {$count} high-quality multiple-choice questions that test deep understanding and critical thinking about the subject matter.

CONTENT TO CREATE QUIZ FROM:
{$text}

QUIZ REQUIREMENTS:
1. Create questions in a traditional exam format - DO NOT reference the note or source material
2. Questions should be about the subject matter itself, not about the content source
3. Questions should test {$level} with varying complexity
4. Each question must be based on specific concepts, facts, or information from the content
5. Create sophisticated distractors (wrong answers) that represent common misconceptions or partial understanding
6. Questions should assess actual knowledge, understanding, application, and analysis

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

ENHANCED ANSWER CHOICE REQUIREMENTS:
- All options should be realistic and educational
- Wrong answers should represent common mistakes or partial understanding
- Include options that show different levels of understanding
- Make distractors challenging but fair
- Ensure correct answer is clearly the best choice

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

        return "You are an expert educator creating a comprehensive exam-style quiz based on the study material. Create {$count} high-quality true/false questions that test deep understanding and critical thinking about the subject matter.

CONTENT TO CREATE QUIZ FROM:
{$text}

QUIZ REQUIREMENTS:
1. Create questions in a traditional exam format - DO NOT reference the note or source material
2. Questions should be about the subject matter itself, not about the content source
3. Questions should test {$level} with varying complexity
4. Each question must be based on specific concepts, facts, or information from the content
5. Create sophisticated statements that require careful analysis to determine true/false
6. Include statements that represent common misconceptions or partial understanding

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
     * Parse quiz response from AI
     */
    private function parseQuizResponse($response, $quizType = 'multiple_choice') {
        // Clean the response by removing markdown code blocks if present
        $cleanResponse = preg_replace('/```json\s*|\s*```/', '', $response);

        // Try to parse JSON response
        $json = json_decode($cleanResponse, true);
        if ($json && isset($json['questions'])) {
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

        // Get some key terms from the text
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those'];
        $keyTerms = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });
        $keyTerms = array_unique($keyTerms);
        $keyTerms = array_slice($keyTerms, 0, min(10, count($keyTerms)));

        $questions = [];

        // Generate questions based on quiz type
        for ($i = 0; $i < $count; $i++) {
            if ($quizType === 'true_false') {
                // Generate True/False questions
                $question = $this->generateTrueFalseQuestion($text, $sentences, $keyTerms, $i);
                $options = ["A) True", "B) False"];
                $correctAnswer = $question['correct'] ? 'A' : 'B';
                $questionText = $question['text'];
            } elseif ($quizType === 'mixed') {
                // Mix of multiple choice and true/false
                if ($i % 3 === 2) { // Every 3rd question is true/false
                    $question = $this->generateTrueFalseQuestion($text, $sentences, $keyTerms, $i);
                    $options = ["A) True", "B) False"];
                    $correctAnswer = $question['correct'] ? 'A' : 'B';
                    $questionText = $question['text'];
                } else {
                    // Multiple choice question
                    $result = $this->generateMultipleChoiceQuestion($text, $sentences, $keyTerms, $i);
                    $questionText = $result['question'];
                    $options = $result['options'];
                    $correctAnswer = 'A';
                }
            } else {
                // Default multiple choice
                $result = $this->generateMultipleChoiceQuestion($text, $sentences, $keyTerms, $i);
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