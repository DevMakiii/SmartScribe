// api/summaries.js
const { getDbConnection } = require('./_utils/database');
const AuthUtils = require('./_utils/auth');
const ResponseUtils = require('./_utils/response');

module.exports = async (req, res) => {
  const action = req.query.action || 'index';
  const db = await getDbConnection();

  try {
    switch (action) {
      case 'index':
        return await handleIndex(req, res, db);
      case 'store':
        return await handleStore(req, res, db);
      case 'show':
        return await handleShow(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('Summaries error:', error);
    return ResponseUtils.error(res, 'Summaries operation failed: ' + error.message);
  }
};

async function handleIndex(req, res, db) {
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

    // Get all summaries for the user
    const [summaries] = await db.execute(`
      SELECT s.*
      FROM summaries s
      INNER JOIN notes n ON s.note_id = n.id
      WHERE n.user_id = ?
      ORDER BY s.created_at DESC
    `, [userId]);

    return ResponseUtils.success(res, summaries, 'Summaries retrieved successfully');

  } catch (error) {
    console.error('Index summaries error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve summaries: ' + error.message);
  }
}

async function handleStore(req, res, db) {
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
    const data = req.body;

    if (!data.note_id) {
      return ResponseUtils.badRequest(res, 'Missing note_id');
    }

    // Verify the note belongs to the authenticated user
    const [notes] = await db.execute(
      "SELECT id, original_text FROM notes WHERE id = ? AND user_id = ?",
      [data.note_id, userId]
    );

    const note = notes[0];
    if (!note) {
      return ResponseUtils.error(res, 'Access denied or note not found', 403);
    }

    const content = data.content || null;
    const length = data.length || 'medium';
    const format = data.format || 'paragraph';

    let summaryContent = content;

    // If content is not provided, generate summary using AI
    if (!content) {
      try {
        // TODO: Integrate with AI service (Google Gemini)
        summaryContent = await generateAISummary(note.original_text, length, format);
      } catch (error) {
        console.error('AI summary generation failed:', error.message);
        summaryContent = generateFallbackSummary(note.original_text, length, format);
      }
    }

    // Insert summary
    const [result] = await db.execute(`
      INSERT INTO summaries (note_id, content, length, created_at, updated_at)
      VALUES (?, ?, ?, NOW(), NOW())
    `, [data.note_id, summaryContent, length]);

    const summaryId = result.insertId;

    if (summaryId) {
      return ResponseUtils.success(res, {
        summary_id: summaryId,
        generated: !content,
        content_preview: summaryContent.substring(0, 100)
      }, 'Summary created successfully', 201);
    } else {
      return ResponseUtils.error(res, 'Failed to create summary');
    }

  } catch (error) {
    console.error('Store summary error:', error);
    return ResponseUtils.error(res, 'Failed to create summary: ' + error.message);
  }
}

async function handleShow(req, res, db) {
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
    const summaryId = req.query.id;

    if (!summaryId) {
      return ResponseUtils.badRequest(res, 'Summary ID is required');
    }

    // Get summary with user verification
    const [summaries] = await db.execute(`
      SELECT s.*
      FROM summaries s
      INNER JOIN notes n ON s.note_id = n.id
      WHERE s.id = ? AND n.user_id = ?
    `, [summaryId, userId]);

    const summary = summaries[0];
    if (!summary) {
      return ResponseUtils.notFound(res, 'Summary not found');
    }

    return ResponseUtils.success(res, summary);

  } catch (error) {
    console.error('Show summary error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve summary: ' + error.message);
  }
}

async function generateAISummary(text, length, format) {
  try {
    // TODO: Integrate with Google Gemini API
    // For now, return a placeholder that indicates AI integration is needed
    const wordCount = text.split(' ').length;

    switch (length) {
      case 'short':
        return `AI Summary (Short): ${text.substring(0, 100)}... (${wordCount} words total)`;
      case 'medium':
        return `AI Summary (Medium): ${text.substring(0, 200)}... (${wordCount} words total)`;
      case 'long':
        return `AI Summary (Long): ${text.substring(0, 300)}... (${wordCount} words total)`;
      default:
        return `AI Summary: ${text.substring(0, 150)}... (${wordCount} words total)`;
    }
  } catch (error) {
    console.error('AI summary generation error:', error);
    throw error;
  }
}

function generateFallbackSummary(text, length, format = 'paragraph') {
  const wordCount = text.split(' ').length;
  const sentences = text.split(/[.!?]+/).filter(s => s.trim().length > 0);
  const firstSentence = sentences[0]?.trim() || '';

  switch (length) {
    case 'short':
      return "Summary: " + firstSentence.substring(0, 100) + "... (" + wordCount + " words)";
    case 'medium':
      const secondSentence = sentences[1] ? " " + sentences[1].trim() : "";
      return "Summary: " + firstSentence + secondSentence + " (Total: " + wordCount + " words)";
    case 'long':
      const summaryText = sentences.slice(0, 3).join(" ");
      return "Detailed Summary: " + summaryText + "... (Total: " + wordCount + " words, " + sentences.length + " sentences)";
    default:
      return "Generated summary for " + wordCount + " words of content.";
  }
}