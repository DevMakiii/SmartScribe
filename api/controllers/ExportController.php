<?php
require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/Summary.php';

class ExportController {
    private $db;
    private $note;
    private $summary;

    public function __construct($db = null) {
        if ($db === null) {
            global $db;
        }
        $this->db = $db;
        $this->note = new Note($db);
        $this->summary = new Summary($db);
    }

    public function export($id, $format) {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "Unauthorized"
            ]);
            return;
        }

        // Get note data
        $noteData = $this->note->getByIdAndUser($id, $userId);
        if (!$noteData) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "Note not found"
            ]);
            return;
        }

        // Get latest summary
        $summaryQuery = "SELECT content FROM summaries WHERE note_id = :note_id ORDER BY created_at DESC LIMIT 1";
        $summaryStmt = $this->db->prepare($summaryQuery);
        $summaryStmt->bindParam(':note_id', $id);
        $summaryStmt->execute();
        $summaryData = $summaryStmt->fetch();

        $summary = $summaryData ? $summaryData['content'] : 'No summary available';

        switch (strtolower($format)) {
            case 'pdf':
                $this->exportToPDF($noteData, $summary);
                break;
            case 'docx':
                $this->exportToDOCX($noteData, $summary);
                break;
            case 'txt':
                $this->exportToTXT($noteData, $summary);
                break;
            default:
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "error" => "Unsupported format"
                ]);
        }
    }

    private function exportToPDF($noteData, $summary) {
        // Create a simple, clean HTML file that will definitely open
        $title = htmlspecialchars($noteData['title'] ?? 'Untitled Note');
        $originalText = htmlspecialchars($noteData['original_text'] ?? 'No content');
        $summaryText = htmlspecialchars($summary ?? 'No summary available');
        $createdDate = htmlspecialchars($noteData['created_at'] ?? 'Unknown');
        $exportDate = date('Y-m-d H:i:s');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - SmartScribe Export</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.6;
            margin: 2cm;
            color: #000;
            background: #fff;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .title {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .meta {
            font-size: 10pt;
            color: #666;
            margin-bottom: 20px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .content {
            text-align: justify;
            white-space: pre-wrap;
        }
        .instructions {
            background: #f0f0f0;
            padding: 15px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
            font-size: 11pt;
        }
    </style>
</head>
<body>
    <div class="instructions">
        <strong>PDF Conversion Instructions:</strong><br>
        1. Open this HTML file in your web browser<br>
        2. Press Ctrl+P (or Cmd+P on Mac) to open print dialog<br>
        3. Select "Save as PDF" or "Print to PDF"<br>
        4. Save the file with your preferred name
    </div>

    <div class="header">
        <div class="title">{$title}</div>
    </div>

    <div class="meta">
        <strong>Created:</strong> {$createdDate}<br>
        <strong>Exported:</strong> {$exportDate}<br>
        <strong>Exported by:</strong> SmartScribe
    </div>

    <div class="section">
        <div class="section-title">Original Text</div>
        <div class="content">{$originalText}</div>
    </div>

    <div class="section">
        <div class="section-title">AI Summary</div>
        <div class="content">{$summaryText}</div>
    </div>
</body>
</html>
HTML;

        // Send as HTML file
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $this->sanitizeFilename($noteData['title']) . '_for_pdf.html"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Length: ' . strlen($html));

        echo $html;
        exit;
    }

    private function exportToDOCX($noteData, $summary) {
        // Generate Word-compatible HTML
        $html = $this->generateWordHTML($noteData, $summary);

        // Send as .doc file that Microsoft Word can open directly
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->sanitizeFilename($noteData['title']) . '.doc"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $html;
        exit;
    }

    private function exportToTXT($noteData, $summary) {
        $content = $this->generateTextContent($noteData, $summary);

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->sanitizeFilename($noteData['title']) . '.txt"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $content;
        exit;
    }

    private function generateHTML($noteData, $summary) {
        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>" . htmlspecialchars($noteData['title']) . "</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        .content { line-height: 1.6; margin: 20px 0; }
        .meta { color: #888; font-size: 0.9em; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>" . htmlspecialchars($noteData['title']) . "</h1>
    <div class='meta'>
        <p><strong>Created:</strong> " . htmlspecialchars($noteData['created_at'] ?? 'Unknown') . "</p>
        <p><strong>Exported:</strong> " . date('Y-m-d H:i:s') . "</p>
    </div>

    <h2>Original Text</h2>
    <div class='content'>" . nl2br(htmlspecialchars($noteData['original_text'])) . "</div>

    <h2>AI Summary</h2>
    <div class='content'>" . nl2br(htmlspecialchars($summary)) . "</div>
</body>
</html>";
    }

    private function generateWordHTML($noteData, $summary) {
        return "
<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
<head>
    <meta charset='UTF-8'>
    <title>" . htmlspecialchars($noteData['title']) . "</title>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; margin: 1in; }
        h1 { font-size: 18pt; font-weight: bold; text-align: center; margin-bottom: 20pt; page-break-after: avoid; }
        h2 { font-size: 14pt; font-weight: bold; margin-top: 20pt; margin-bottom: 10pt; page-break-after: avoid; }
        .meta { font-size: 10pt; color: #666; margin-bottom: 20pt; }
        .content { margin: 10pt 0; text-align: justify; }
        p { margin: 6pt 0; }
    </style>
</head>
<body>
    <h1>" . htmlspecialchars($noteData['title']) . "</h1>

    <div class='meta'>
        <p><strong>Created:</strong> " . htmlspecialchars($noteData['created_at'] ?? 'Unknown') . "</p>
        <p><strong>Exported:</strong> " . date('Y-m-d H:i:s') . "</p>
    </div>

    <h2>Original Text</h2>
    <div class='content'>
        " . nl2br(htmlspecialchars($noteData['original_text'])) . "
    </div>

    <h2>AI Summary</h2>
    <div class='content'>
        " . nl2br(htmlspecialchars($summary)) . "
    </div>
</body>
</html>";
    }

    private function generateTextContent($noteData, $summary) {
        return $noteData['title'] . "\n" . str_repeat("=", strlen($noteData['title'])) . "\n\n" .
               "Created: " . ($noteData['created_at'] ?? 'Unknown') . "\n" .
               "Exported: " . date('Y-m-d H:i:s') . "\n\n" .
               "ORIGINAL TEXT:\n" . str_repeat("-", 50) . "\n" .
               $noteData['original_text'] . "\n\n" .
               "AI SUMMARY:\n" . str_repeat("-", 50) . "\n" .
               $summary . "\n";
    }

    private function sanitizeFilename($filename) {
        // Remove or replace characters that are invalid in filenames
        return preg_replace('/[^A-Za-z0-9\-_]/', '_', $filename);
    }

    private function getUserIdFromHeader() {
        $headers = getallheaders();

        // First try to validate token from Authorization header (case-insensitive)
        $authHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }

        if ($authHeader) {
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                $userId = $this->validateToken($token);
                if ($userId) {
                    return $userId;
                }
            }
        }

        // Fallback to X-User-ID header (for backward compatibility)
        if (isset($headers['X-User-ID']) || isset($headers['x-user-id'])) {
            $userIdHeader = $headers['X-User-ID'] ?? $headers['x-user-id'];
            return intval($userIdHeader);
        }

        return null;
    }

    private function validateToken($token) {
        if (!$token) return null;

        try {
            $stmt = $this->db->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            $result = $stmt->fetch();

            return $result ? $result['user_id'] : null;
        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return null;
        }
    }
}
?>