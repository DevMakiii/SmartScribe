// api/notes.js
const { getDbConnection } = require('./_utils/database');
const AuthUtils = require('./_utils/auth');
const ResponseUtils = require('./_utils/response');
const multer = require('multer');
const path = require('path');
const fs = require('fs').promises;

// Configure multer for file uploads
const upload = multer({
  storage: multer.memoryStorage(),
  limits: {
    fileSize: 5 * 1024 * 1024, // 5MB limit
  },
  fileFilter: (req, file, cb) => {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (allowedTypes.includes(file.mimetype)) {
      cb(null, true);
    } else {
      cb(new Error('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.'));
    }
  }
});

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
      case 'update':
        return await handleUpdate(req, res, db);
      case 'destroy':
        return await handleDestroy(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('Notes error:', error);
    return ResponseUtils.error(res, 'Notes operation failed: ' + error.message);
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

    // Get all notes for the user
    const [notes] = await db.execute(
      "SELECT n.* FROM notes n WHERE user_id = ? ORDER BY created_at DESC",
      [userId]
    );

    return ResponseUtils.success(res, notes, 'Notes retrieved successfully');

  } catch (error) {
    console.error('Index notes error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve notes: ' + error.message);
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
    let title = null;
    let text = null;
    let imagePath = null;

    // Check if this is a JSON request or FormData
    const contentType = req.headers['content-type'] || '';

    if (contentType.includes('application/json')) {
      // Handle JSON data
      title = req.body.title;
      text = req.body.text;
    } else {
      // Handle FormData
      title = req.body.title;
      text = req.body.text;
    }

    // Validate required fields
    if (!title || !text) {
      return ResponseUtils.badRequest(res, 'Missing title or text');
    }

    // Handle file upload if present
    if (req.file) {
      const uploadResult = await handleFileUpload(req.file, userId);
      if (!uploadResult.success) {
        return ResponseUtils.error(res, uploadResult.error);
      }
      imagePath = uploadResult.file_path;
    }

    // Insert note into database
    const [result] = await db.execute(
      "INSERT INTO notes (user_id, title, original_text, image_path, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
      [userId, AuthUtils.sanitizeInput(title), AuthUtils.sanitizeInput(text), imagePath]
    );

    const noteId = result.insertId;

    if (noteId) {
      // Auto-extract keywords from the note content
      const keywords = await extractKeywords(AuthUtils.sanitizeInput(text), 5);
      const keywordsString = keywords.join(',');

      // Update the note with extracted keywords
      await db.execute(
        "UPDATE notes SET keywords = ? WHERE id = ? AND user_id = ?",
        [keywordsString, noteId, userId]
      );

      // Update goal progress for this user
      try {
        await updateGoalProgress(userId, db);
      } catch (error) {
        console.error('Error updating goals:', error.message);
        // Don't fail the note creation if goal update fails
      }

      return ResponseUtils.success(res, {
        note_id: noteId,
        keywords: keywords
      }, 'Note saved successfully with auto-extracted keywords', 201);
    } else {
      return ResponseUtils.error(res, 'Failed to save note');
    }

  } catch (error) {
    console.error('Store note error:', error);
    return ResponseUtils.error(res, 'Failed to create note: ' + error.message);
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
    const noteId = req.query.id;

    if (!noteId) {
      return ResponseUtils.badRequest(res, 'Note ID is required');
    }

    // Get note with summary
    const [notes] = await db.execute(
      `SELECT n.*,
              DATE_FORMAT(n.created_at, '%M %e, %Y at %l:%i %p') as last_edited,
              s.content as summary
       FROM notes n
       LEFT JOIN summaries s ON n.id = s.note_id
       WHERE n.id = ? AND n.user_id = ?
       ORDER BY s.created_at DESC
       LIMIT 1`,
      [noteId, userId]
    );

    const note = notes[0];
    if (!note) {
      return ResponseUtils.notFound(res, 'Note not found');
    }

    return ResponseUtils.success(res, note);

  } catch (error) {
    console.error('Show note error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve note: ' + error.message);
  }
}

async function handleUpdate(req, res, db) {
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

    if (!noteId) {
      return ResponseUtils.badRequest(res, 'Note ID is required');
    }

    const title = req.body.title;
    const text = req.body.text;
    const summary = req.body.summary;
    const keywords = req.body.keywords;
    const isFavoriteRaw = req.body.is_favorite;
    const isFavorite = isFavoriteRaw !== undefined ? Boolean(isFavoriteRaw) : undefined;

    // For favorite updates, we only need the is_favorite field
    const isFavoriteOnly = (title === undefined && text === undefined && isFavorite !== undefined);

    if (!isFavoriteOnly && (!title || !text)) {
      return ResponseUtils.badRequest(res, 'Missing title or text');
    }

    // Check if note exists and belongs to user
    const [existingNotes] = await db.execute(
      "SELECT id FROM notes WHERE id = ? AND user_id = ?",
      [noteId, userId]
    );

    if (existingNotes.length === 0) {
      return ResponseUtils.notFound(res, 'Note not found');
    }

    // Build dynamic UPDATE query based on provided fields
    const updateFields = [];
    const params = [];

    if (title !== undefined) {
      updateFields.push("title = ?");
      params.push(AuthUtils.sanitizeInput(title));
    }

    if (text !== undefined) {
      updateFields.push("original_text = ?");
      params.push(AuthUtils.sanitizeInput(text));
    }

    if (isFavorite !== undefined) {
      updateFields.push("is_favorite = ?");
      params.push(isFavorite);
    }

    // Always update the updated_at timestamp
    updateFields.push("updated_at = NOW()");

    params.push(noteId, userId);
    const query = `UPDATE notes SET ${updateFields.join(', ')} WHERE id = ? AND user_id = ?`;

    const [result] = await db.execute(query, params);

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, null, 'Note updated successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to update note');
    }

  } catch (error) {
    console.error('Update note error:', error);
    return ResponseUtils.error(res, 'Failed to update note: ' + error.message);
  }
}

async function handleDestroy(req, res, db) {
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

    if (!noteId) {
      return ResponseUtils.badRequest(res, 'Note ID is required');
    }

    const [result] = await db.execute(
      "DELETE FROM notes WHERE id = ? AND user_id = ?",
      [noteId, userId]
    );

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, null, 'Note deleted successfully');
    } else {
      return ResponseUtils.notFound(res, 'Note not found');
    }

  } catch (error) {
    console.error('Destroy note error:', error);
    return ResponseUtils.error(res, 'Failed to delete note: ' + error.message);
  }
}

async function handleFileUpload(file, userId) {
  try {
    // Validate file
    if (!file || !file.buffer) {
      return { success: false, error: "No valid image uploaded" };
    }

    // Generate unique filename
    const fileExtension = path.extname(file.originalname).toLowerCase();
    const fileName = `note_${userId}_${Date.now()}${fileExtension}`;

    // In Vercel, we'll store files in /tmp directory or use a cloud storage service
    // For now, we'll save to a temporary location
    const uploadDir = '/tmp/uploads/';

    try {
      await fs.access(uploadDir);
    } catch {
      await fs.mkdir(uploadDir, { recursive: true });
    }

    const filePath = path.join(uploadDir, fileName);

    // Write file to disk
    await fs.writeFile(filePath, file.buffer);

    // Return relative path (you might want to upload to cloud storage instead)
    return {
      success: true,
      file_path: `uploads/${fileName}`
    };

  } catch (error) {
    console.error('File upload error:', error);
    return { success: false, error: "Failed to upload file: " + error.message };
  }
}

async function extractKeywords(text, maxKeywords = 5) {
  // Simple keyword extraction - you can integrate with AI services here
  // For now, we'll do basic text processing
  const words = text.toLowerCase()
    .replace(/[^\w\s]/g, ' ')
    .split(/\s+/)
    .filter(word => word.length > 3)
    .filter(word => !['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'].includes(word));

  // Count word frequency
  const wordCount = {};
  words.forEach(word => {
    wordCount[word] = (wordCount[word] || 0) + 1;
  });

  // Sort by frequency and return top keywords
  return Object.entries(wordCount)
    .sort(([,a], [,b]) => b - a)
    .slice(0, maxKeywords)
    .map(([word]) => word);
}

async function updateGoalProgress(userId, db) {
  try {
    // Update goal progress for notes
    const [goals] = await db.execute(
      "SELECT id, title, target_value, current_value, status FROM learning_goals WHERE user_id = ? AND target_type = 'notes' AND status = 'active'",
      [userId]
    );

    for (const goal of goals) {
      // Increment current value for each active note goal
      const newCurrentValue = goal.current_value + 1;
      const newStatus = newCurrentValue >= goal.target_value ? 'completed' : 'active';

      await db.execute(
        "UPDATE learning_goals SET current_value = ?, status = ? WHERE id = ?",
        [newCurrentValue, newStatus, goal.id]
      );
    }

    return goals.length;

  } catch (error) {
    console.error('Update goal progress error:', error);
    throw error;
  }
}