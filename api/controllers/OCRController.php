<?php
/**
 * OCR Controller for handling image and PDF text extraction
 */

require_once __DIR__ . '/BaseController.php';

class OCRController extends BaseController {
    private $uploadDir;
    private $cacheDir;

    public function __construct() {
        parent::__construct();
        $this->uploadDir = __DIR__ . '/../../public/uploads/';
        $this->cacheDir = __DIR__ . '/../../public/uploads/cache/';

        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Process an image file for OCR
     */
    public function processImage() {
        try {
            if (!isset($_FILES['image'])) {
                $this->sendError('No image file provided', 400);
            }

            $file = $_FILES['image'];

            // Validate file
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->sendError('File upload error', 400);
            }

            // Check file size (max 10MB for better quality)
            if ($file['size'] > 10 * 1024 * 1024) {
                $this->sendError('File size must be less than 10MB', 400);
            }

            // Check file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                $this->sendError('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed', 400);
            }

            // Save uploaded file temporarily
            $tempPath = $this->uploadDir . uniqid('ocr_') . '_' . basename($file['name']);
            if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
                $this->sendError('Failed to save uploaded file', 500);
            }

            // Perform OCR
            $ocrResult = $this->performOCR($tempPath);

            // Cleanup temp file
            unlink($tempPath);

            $this->sendSuccess([
                'text' => $ocrResult['text'],
                'confidence' => $ocrResult['confidence'],
                'quality' => $ocrResult['quality'],
                'fileName' => $file['name'],
                'fileSize' => $file['size'],
                'fileType' => $file['type']
            ]);

        } catch (Exception $e) {
            $this->sendError('Failed to process image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Perform OCR on an image using Tesseract
     */
    private function performOCR($imagePath) {
        try {
            // Check cache first
            $imageHash = md5_file($imagePath);
            $cacheFile = $this->cacheDir . $imageHash . '.json';

            if (file_exists($cacheFile)) {
                $cachedResult = json_decode(file_get_contents($cacheFile), true);
                if ($cachedResult) {
                    return $cachedResult;
                }
            }

            // Check if Tesseract is available
            if (!$this->isTesseractAvailable()) {
                $result = [
                    'text' => 'Tesseract OCR is not installed. Please install Tesseract OCR on your server. For Windows: Download from https://github.com/UB-Mannheim/tesseract/wiki and add to PATH. For Linux: sudo apt-get install tesseract-ocr',
                    'confidence' => 0,
                    'quality' => 'not_installed'
                ];
                file_put_contents($cacheFile, json_encode($result));
                return $result;
            }

            // Preprocess the image
            $processedPath = $this->preprocessImage($imagePath);

            // Perform OCR with confidence scoring
            $result = $this->getOCRWithConfidence($processedPath);

            // Post-process the text
            $result['text'] = $this->postProcessText($result['text']);

            // Cleanup processed image
            if ($processedPath !== $imagePath) {
                unlink($processedPath);
            }

            // Cache the result
            file_put_contents($cacheFile, json_encode($result));

            return $result;
        } catch (Exception $e) {
            // Fallback to basic processing
            $result = [
                'text' => 'OCR processing failed: ' . $e->getMessage(),
                'confidence' => 0,
                'quality' => 'error'
            ];
            // Cache error result too to avoid repeated failures
            $imageHash = md5_file($imagePath);
            $cacheFile = $this->cacheDir . $imageHash . '.json';
            file_put_contents($cacheFile, json_encode($result));
            return $result;
        }
    }

    /**
     * Preprocess image for better OCR results
     */
    private function preprocessImage($imagePath) {
        $outputPath = tempnam(sys_get_temp_dir(), 'ocr_processed_') . '.png';

        // Try ImageMagick if available
        if ($this->isImageMagickAvailable()) {
            $command = "convert \"$imagePath\" -colorspace Gray -noise 1 -blur 0.5x0.5 -contrast-stretch 0 -deskew 40% -adaptive-threshold 50% -resize 300% \"$outputPath\" 2>&1";
            exec($command, $output, $return);

            if ($return === 0 && file_exists($outputPath)) {
                return $outputPath;
            }
        }

        // Fallback to GD library preprocessing
        return $this->gdPreprocessImage($imagePath, $outputPath);
    }

    /**
     * Check if ImageMagick is available
     */
    private function isImageMagickAvailable() {
        $command = "convert -version 2>&1";
        exec($command, $output, $return);
        return $return === 0;
    }

    /**
     * Check if Tesseract is available
     */
    private function isTesseractAvailable() {
        $command = "tesseract --version 2>&1";
        exec($command, $output, $return);
        return $return === 0;
    }

    /**
     * GD-based image preprocessing
     */
    private function gdPreprocessImage($inputPath, $outputPath) {
        $image = imagecreatefromstring(file_get_contents($inputPath));
        if (!$image) {
            return $inputPath; // Return original if processing fails
        }

        // Get image dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Convert to grayscale
        imagefilter($image, IMG_FILTER_GRAYSCALE);

        // Reduce noise
        imagefilter($image, IMG_FILTER_SMOOTH, 5);

        // Enhance contrast more aggressively
        imagefilter($image, IMG_FILTER_CONTRAST, -20);

        // Sharpen for better text edges
        $sharpen = array(
            array(-1, -1, -1),
            array(-1, 20, -1),
            array(-1, -1, -1)
        );
        imageconvolution($image, $sharpen, 8, 0);

        // Additional edge enhancement
        imagefilter($image, IMG_FILTER_EDGEDETECT);
        imagefilter($image, IMG_FILTER_EMBOSS);

        // Save processed image
        imagepng($image, $outputPath, 9); // High quality PNG
        imagedestroy($image);

        return $outputPath;
    }

    /**
     * Perform OCR with Tesseract and get confidence score
     */
    private function getOCRWithConfidence($imagePath) {
        $psmModes = [6, 3, 1]; // Try uniform block, auto, auto with OSD
        $bestResult = null;
        $bestConfidence = 0;

        foreach ($psmModes as $psm) {
            $outputBase = tempnam(sys_get_temp_dir(), 'tesseract_');

            // Tesseract command with optimized settings
            $command = "tesseract \"$imagePath\" \"$outputBase\" -l eng --oem 3 --psm $psm " .
                "-c tessedit_pageseg_mode=$psm " .
                "-c tessedit_ocr_engine_mode=3 " .
                "-c textord_min_linesize=2.5 " .
                "-c classify_bln_numeric_mode=1 " .
                "-c tessedit_char_whitelist=\"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 .,!?-()[]{}:;\'\\\"@#$%&*+=/\\|~`\" " .
                "2>&1";

            exec($command, $output, $return);

            $text = '';
            if (file_exists($outputBase . '.txt')) {
                $text = file_get_contents($outputBase . '.txt');
            }

            // Get confidence from hOCR output
            $confidence = $this->extractConfidenceFromHOCR($imagePath);

            // Keep the best result
            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestResult = [
                    'text' => trim($text),
                    'confidence' => $confidence,
                    'quality' => $this->getQualityLabel($confidence)
                ];
            }

            // Cleanup temp files
            if (file_exists($outputBase . '.txt')) unlink($outputBase . '.txt');
            if (file_exists($outputBase . '.hocr')) unlink($outputBase . '.hocr');

            // If confidence is high enough, stop trying
            if ($confidence >= 80) break;
        }

        return $bestResult ?: [
            'text' => '',
            'confidence' => 0,
            'quality' => 'very_low'
        ];
    }

    /**
     * Extract confidence score from Tesseract hOCR output
     */
    private function extractConfidenceFromHOCR($imagePath) {
        $hocrBase = tempnam(sys_get_temp_dir(), 'hocr_');

        // Generate hOCR output
        $command = "tesseract \"$imagePath\" \"$hocrBase\" -c tessedit_create_hocr=1 2>&1";
        exec($command, $output, $return);

        $confidence = 0;
        if (file_exists($hocrBase . '.hocr')) {
            $hocr = file_get_contents($hocrBase . '.hocr');
            if (preg_match_all('/x_wconf ([\d]+)/', $hocr, $matches)) {
                if (!empty($matches[1])) {
                    $confidence = array_sum($matches[1]) / count($matches[1]);
                }
            }
            unlink($hocrBase . '.hocr');
        }

        return round($confidence);
    }

    /**
     * Get quality label based on confidence score
     */
    private function getQualityLabel($confidence) {
        if ($confidence >= 80) return 'high';
        if ($confidence >= 60) return 'medium';
        if ($confidence >= 30) return 'low';
        return 'very_low';
    }

    /**
     * Post-process extracted text for better readability
     */
    private function postProcessText($text) {
        // Preserve line breaks for layout
        $lines = explode("\n", $text);
        $processedLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // Fix common OCR errors
                $line = $this->fixOCRErrors($line);
                // Simple spell check and correction
                $line = $this->simpleSpellCheck($line);
                $processedLines[] = $line;
            }
        }

        // Rejoin with single newlines, but add paragraph breaks
        $text = implode("\n", $processedLines);

        // Add paragraph breaks after short lines (likely headings) or before indented lines
        $text = preg_replace('/([.!?])\s*\n([A-Z])/', "$1\n\n$2", $text);

        // Remove excessive whitespace but preserve structure
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/ {2,}/', ' ', $text);

        return trim($text);
    }

    /**
     * Fix common OCR character recognition errors
     */
    private function fixOCRErrors($text) {
        $corrections = [
            '/\bl\b/' => 'I',      // lowercase L to I
            '/\b0\b/' => 'O',      // zero to O
            '/\b1\b/' => 'I',      // one to I
            '/\b5\b/' => 'S',      // five to S
            '/\b8\b/' => 'B',      // eight to B
            '/rn\b/' => 'm',       // rn to m
            '/\bcl\b/' => 'd',     // cl to d
            '/\bIl\b/' => 'II',    // Il to II
            '/\bce\b/' => 'ce',    // ce common error
            '/\bth\b/' => 'the',   // th to the (context dependent)
            '/\bte\b/' => 'the',   // te to the
        ];

        foreach ($corrections as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * Simple spell check using common word corrections
     */
    private function simpleSpellCheck($text) {
        $commonCorrections = [
            'teh' => 'the',
            'adn' => 'and',
            'nad' => 'and',
            'taht' => 'that',
            'tihs' => 'this',
            'fo' => 'of',
            'ot' => 'to',
            'wiht' => 'with',
            'fro' => 'for',
            'ont' => 'not',
            'no' => 'on',  // context dependent, but common
            'si' => 'is',
            'a' => 'a',    // keep as is
            'an' => 'an',
            'in' => 'in',
            'on' => 'on',
            'at' => 'at',
            'by' => 'by',
            'for' => 'for',
            'from' => 'from',
            'into' => 'into',
            'of' => 'of',
            'to' => 'to',
            'with' => 'with',
        ];

        $words = explode(' ', $text);
        $correctedWords = [];

        foreach ($words as $word) {
            $lowerWord = strtolower($word);
            if (isset($commonCorrections[$lowerWord])) {
                // Preserve capitalization
                if (ctype_upper($word[0])) {
                    $correctedWords[] = ucfirst($commonCorrections[$lowerWord]);
                } else {
                    $correctedWords[] = $commonCorrections[$lowerWord];
                }
            } else {
                $correctedWords[] = $word;
            }
        }

        return implode(' ', $correctedWords);
    }

    /**
     * Simple text extraction as fallback
     */
    private function extractTextSimple($pdfContent) {
        // Look for text in PDF streams
        $text = '';

        // Find text between parentheses in the PDF
        if (preg_match_all('/\(([^)]*)\)/', $pdfContent, $matches)) {
            foreach ($matches[1] as $match) {
                $decoded = $this->decodePDFText($match);
                if (strlen($decoded) > 2) { // Filter out very short strings
                    $text .= $decoded . ' ';
                }
            }
        }

        return $text;
    }


    /**
     * Send success response
     */
    private function sendSuccess($data, $message = 'Success') {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    /**
     * Send error response
     */
    private function sendError($message, $code = 400) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
}