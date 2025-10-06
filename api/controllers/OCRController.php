<?php
/**
 * OCR Controller for handling image and PDF text extraction
 */


class OCRController {
    private $db;
    private $uploadDir;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->uploadDir = __DIR__ . '/../public/uploads/';
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

            // Check file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                $this->sendError('File size must be less than 5MB', 400);
            }

            // Check file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                $this->sendError('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed', 400);
            }

            // For now, return a placeholder response
            // In a real implementation, you would use Tesseract OCR or similar
            $this->sendSuccess([
                'text' => 'Image processed successfully. OCR functionality would extract text here.',
                'fileName' => $file['name'],
                'fileSize' => $file['size'],
                'fileType' => $file['type']
            ]);

        } catch (Exception $e) {
            $this->sendError('Failed to process image: ' . $e->getMessage(), 500);
        }
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