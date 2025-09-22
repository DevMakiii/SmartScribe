// api/export.js
const { getDbConnection } = require('./_utils/database');
const AuthUtils = require('./_utils/auth');
const ResponseUtils = require('./_utils/response');

module.exports = async (req, res) => {
  const action = req.query.action || 'export';
  const db = await getDbConnection();

  try {
    switch (action) {
      case 'export':
        return await handleExport(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('Export error:', error);
    return ResponseUtils.error(res, 'Export operation failed: ' + error.message);
  }
};

async function handleExport(req, res, db) {
  try {
    // Get token from Authorization header
    const authHeader = req.headers.authorization;
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return ResponseUtils.unauthorized(res);
    }

    const token = authHeader.substring(7);
    const decoded = AuthUtils.decodeJWT(token);

    if (!decoded) {
      return ResponseUtils.unauthorized(res);
    }

    const userId = decoded.user_id;
    const noteId = req.query.id;
    const format = req.query.format;

    if (!noteId || !format) {
      return ResponseUtils.badRequest(res, 'Note ID and format are required');
    }

    // Get note data
    const [notes] = await db.execute(
      "SELECT * FROM notes WHERE id = ? AND user_id = ?",
      [noteId, userId]
    );

    const noteData = notes[0];
    if (!noteData) {
      return ResponseUtils.notFound(res, 'Note not found');
    }

    // Get latest summary
    const [summaries] = await db.execute(
      "SELECT content FROM summaries WHERE note_id = ? ORDER BY created_at DESC LIMIT 1",
      [noteId]
    );

    const summary = summaries[0] ? summaries[0].content : 'No summary available';

    switch (format.toLowerCase()) {
      case 'pdf':
        return exportToPDF(res, noteData, summary);
      case 'docx':
        return exportToDOCX(res, noteData, summary);
      case 'txt':
        return exportToTXT(res, noteData, summary);
      default:
        return ResponseUtils.badRequest(res, 'Unsupported format. Supported formats: pdf, docx, txt');
    }

  } catch (error) {
    console.error('Export error:', error);
    return ResponseUtils.error(res, 'Export failed: ' + error.message);
  }
}

function exportToPDF(res, noteData, summary) {
  // Create a simple, clean HTML file that will definitely open
  const title = escapeHtml(noteData.title || 'Untitled Note');
  const originalText = escapeHtml(noteData.original_text || 'No content');
  const summaryText = escapeHtml(summary || 'No summary available');
  const createdDate = escapeHtml(noteData.created_at || 'Unknown');
  const exportDate = new Date().toISOString().replace('T', ' ').substring(0, 19);

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${title} - SmartScribe Export</title>
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
        <div class="title">${title}</div>
    </div>

    <div class="meta">
        <strong>Created:</strong> ${createdDate}<br>
        <strong>Exported:</strong> ${exportDate}<br>
        <strong>Exported by:</strong> SmartScribe
    </div>

    <div class="section">
        <div class="section-title">Original Text</div>
        <div class="content">${originalText}</div>
    </div>

    <div class="section">
        <div class="section-title">AI Summary</div>
        <div class="content">${summaryText}</div>
    </div>
</body>
</html>`;

  // Send as HTML file
  res.setHeader('Content-Type', 'text/html; charset=UTF-8');
  res.setHeader('Content-Disposition', `attachment; filename="${sanitizeFilename(noteData.title)}_for_pdf.html"`);
  res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
  res.setHeader('Pragma', 'no-cache');
  res.setHeader('Expires', '0');
  res.setHeader('Content-Length', Buffer.byteLength(html, 'utf8'));

  res.end(html);
}

function exportToDOCX(res, noteData, summary) {
  // Generate Word-compatible HTML
  const html = generateWordHTML(noteData, summary);

  // Send as .doc file that Microsoft Word can open directly
  res.setHeader('Content-Type', 'application/msword; charset=utf-8');
  res.setHeader('Content-Disposition', `attachment; filename="${sanitizeFilename(noteData.title)}.doc"`);
  res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
  res.setHeader('Pragma', 'no-cache');
  res.setHeader('Expires', '0');

  res.end(html);
}

function exportToTXT(res, noteData, summary) {
  const content = generateTextContent(noteData, summary);

  res.setHeader('Content-Type', 'text/plain; charset=utf-8');
  res.setHeader('Content-Disposition', `attachment; filename="${sanitizeFilename(noteData.title)}.txt"`);
  res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
  res.setHeader('Pragma', 'no-cache');
  res.setHeader('Expires', '0');

  res.end(content);
}

function generateWordHTML(noteData, summary) {
  return `<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
<head>
    <meta charset='UTF-8'>
    <title>${escapeHtml(noteData.title)}</title>
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
    <h1>${escapeHtml(noteData.title)}</h1>

    <div class='meta'>
        <p><strong>Created:</strong> ${escapeHtml(noteData.created_at || 'Unknown')}</p>
        <p><strong>Exported:</strong> ${new Date().toISOString().replace('T', ' ').substring(0, 19)}</p>
    </div>

    <h2>Original Text</h2>
    <div class='content'>
        ${escapeHtml(noteData.original_text).replace(/\n/g, '<br>')}
    </div>

    <h2>AI Summary</h2>
    <div class='content'>
        ${escapeHtml(summary).replace(/\n/g, '<br>')}
    </div>
</body>
</html>`;
}

function generateTextContent(noteData, summary) {
  return `${noteData.title}\n${'='.repeat(noteData.title.length)}\n\n` +
         `Created: ${noteData.created_at || 'Unknown'}\n` +
         `Exported: ${new Date().toISOString().replace('T', ' ').substring(0, 19)}\n\n` +
         `ORIGINAL TEXT:\n${'-'.repeat(50)}\n` +
         `${noteData.original_text}\n\n` +
         `AI SUMMARY:\n${'-'.repeat(50)}\n` +
         `${summary}\n`;
}

function sanitizeFilename(filename) {
  // Remove or replace characters that are invalid in filenames
  return (filename || 'untitled')
    .replace(/[^A-Za-z0-9\-_\s]/g, '_')
    .replace(/\s+/g, '_')
    .substring(0, 50);
}

function escapeHtml(text) {
  const map = {
    '&': '&',
    '<': '<',
    '>': '>',
    '"': '"',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}