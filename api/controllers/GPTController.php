<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../services/AIQuizService.php';
require_once __DIR__ . '/../services/AISummaryService.php';
require_once __DIR__ . '/../services/AIKeywordService.php';
require_once __DIR__ . '/../models/Note.php';

class GPTController extends BaseController {
    private $quizService;
    private $summaryService;
    private $keywordService;
    private $note;

    public function __construct() {
        parent::__construct();
        $this->quizService = new AIQuizService();
        $this->summaryService = new AISummaryService();
        $this->keywordService = new AIKeywordService();
        $this->note = new Note($this->db);
    }

    public function generateSummary() {
        if (!$this->authenticateUser()) {
            $this->unauthorizedResponse();
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['text'])) {
            $this->badRequestResponse('Missing text');
            return;
        }

        $length = $data['length'] ?? 'medium';
        $format = $data['format'] ?? 'paragraph';

        try {
            $summary = $this->summaryService->generateSummary($data['text'], $length, $format);
        } catch (Exception $e) {
            error_log("Summary generation failed in GPTController: " . $e->getMessage());
            // Return fallback summary
            $wordCount = str_word_count($data['text']);
            $sentences = preg_split('/[.!?]+/', $data['text'], -1, PREG_SPLIT_NO_EMPTY);
            $firstSentence = trim($sentences[0] ?? '');

            switch ($length) {
                case 'short':
                    $summary = "Summary: " . substr($firstSentence, 0, 100) . "... (" . $wordCount . " words)";
                    break;
                case 'medium':
                    $secondSentence = isset($sentences[1]) ? " " . trim($sentences[1]) : "";
                    $summary = "Summary: " . $firstSentence . $secondSentence . " (Total: " . $wordCount . " words)";
                    break;
                case 'long':
                    $summaryText = implode(" ", array_slice($sentences, 0, 3));
                    $summary = "Detailed Summary: " . $summaryText . "... (Total: " . $wordCount . " words, " . count($sentences) . " sentences)";
                    break;
                default:
                    $summary = "Generated summary for " . $wordCount . " words of content.";
            }
        }

        // Always return a summary - either AI-generated or fallback
        if ($summary) {
            $this->successResponse($summary);
        } else {
            // Use fallback summary if AI generation fails
            $wordCount = str_word_count($data['text']);
            $sentences = preg_split('/[.!?]+/', $data['text'], -1, PREG_SPLIT_NO_EMPTY);
            $firstSentence = trim($sentences[0] ?? '');

            switch ($length) {
                case 'short':
                    $fallbackSummary = "Summary: " . substr($firstSentence, 0, 100) . "... (" . $wordCount . " words)";
                    break;
                case 'medium':
                    $secondSentence = isset($sentences[1]) ? " " . trim($sentences[1]) : "";
                    $fallbackSummary = "Summary: " . $firstSentence . $secondSentence . " (Total: " . $wordCount . " words)";
                    break;
                case 'long':
                    $summaryText = implode(" ", array_slice($sentences, 0, 3));
                    $fallbackSummary = "Detailed Summary: " . $summaryText . "... (Total: " . $wordCount . " words, " . count($sentences) . " sentences)";
                    break;
                default:
                    $fallbackSummary = "Generated summary for " . $wordCount . " words of content.";
            }

            $this->successResponse($fallbackSummary);
        }
    }

    public function generateQuiz() {
        error_log("=== QUIZ GENERATION START ===");

        if (!$this->authenticateUser()) {
            error_log("QUIZ GENERATION: Unauthorized - authentication failed");
            $this->unauthorizedResponse();
            return;
        }

        $userId = $this->getUserId();
        error_log("User ID: " . ($userId ?: 'null'));

        $rawInput = file_get_contents('php://input');
        error_log("Raw input length: " . strlen($rawInput));
        error_log("Raw input content: " . substr($rawInput, 0, 200) . "...");

        $data = json_decode($rawInput, true);
        error_log("JSON decode result: " . ($data ? 'success' : 'failed'));

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
        }

        if (!isset($data['text'])) {
            error_log("QUIZ GENERATION: Missing text parameter");
            $this->badRequestResponse('Missing text');
            return;
        }

        $difficulty = $data['difficulty'] ?? 'medium';
        $questionCount = $data['questionCount'] ?? 5;
        $noteTitle = $data['noteTitle'] ?? 'this study material';
        $quizType = $data['quizType'] ?? 'multiple_choice';

        error_log("QUIZ GENERATION: Parameters - difficulty: $difficulty, questionCount: $questionCount, noteTitle: $noteTitle, quizType: $quizType");
        error_log("QUIZ GENERATION: Text length: " . strlen($data['text']));

        $options = [
            'difficulty' => $difficulty,
            'questionCount' => $questionCount,
            'noteTitle' => $noteTitle,
            'quizType' => $quizType
        ];

        error_log("QUIZ GENERATION: Calling Quiz service...");
        $quiz = $this->quizService->generateQuiz($data['text'], $options);
        error_log("QUIZ GENERATION: Quiz service returned: " . (is_array($quiz) ? 'array with ' . count($quiz) . ' keys' : gettype($quiz)));

        // Check if this is a fallback quiz due to API unavailability
        if (is_array($quiz) && isset($quiz['quiz']) && strpos($quiz['quiz'], 'AI quiz generation unavailable') !== false) {
            error_log("QUIZ GENERATION: Using fallback quiz generation - API key not configured");
        }

        if (is_array($quiz) && isset($quiz['questions'])) {
            error_log("QUIZ GENERATION: Questions count: " . count($quiz['questions']));
        }

        // Always return quiz data - either AI-generated or fallback
        $this->successResponse($quiz);
        error_log("=== QUIZ GENERATION END ===");
    }

    public function extractKeywords() {
        if (!$this->authenticateUser()) {
            $this->unauthorizedResponse();
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['text'])) {
            $this->badRequestResponse('Missing text');
            return;
        }

        $count = $data['count'] ?? 5;
        $keywords = $this->keywordService->extractKeywords($data['text'], $count);

        // Always return keywords - either AI-generated or fallback
        $this->successResponse($keywords);
    }
}
?>