// api/quizzes.js
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
      case 'update':
        return await handleUpdate(req, res, db);
      case 'destroy':
        return await handleDestroy(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('Quizzes error:', error);
    return ResponseUtils.error(res, 'Quizzes operation failed: ' + error.message);
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

    // Get all quizzes for the user
    const [quizzes] = await db.execute(`
      SELECT q.*
      FROM quizzes q
      INNER JOIN notes n ON q.note_id = n.id
      WHERE n.user_id = ?
      ORDER BY q.created_at DESC
    `, [userId]);

    // Parse JSON fields for each quiz
    const processedQuizzes = quizzes.map(quiz => ({
      ...quiz,
      questions: JSON.parse(quiz.questions || '[]')
    }));

    return ResponseUtils.success(res, processedQuizzes, 'Quizzes retrieved successfully');

  } catch (error) {
    console.error('Index quizzes error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve quizzes: ' + error.message);
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

    // Validate required fields
    if (!data.note_id || !data.questions) {
      return ResponseUtils.badRequest(res, 'Missing note_id or questions');
    }

    // Verify the note belongs to the authenticated user
    const [noteCheck] = await db.execute(
      "SELECT id FROM notes WHERE id = ? AND user_id = ?",
      [data.note_id, userId]
    );

    if (noteCheck.length === 0) {
      return ResponseUtils.error(res, 'Access denied', 403);
    }

    // Insert quiz
    const questionsJson = JSON.stringify(data.questions);
    const [result] = await db.execute(`
      INSERT INTO quizzes (
        note_id, user_id, questions, difficulty, quiz_type, score,
        title, total_questions, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    `, [
      data.note_id,
      userId,
      questionsJson,
      data.difficulty || 'medium',
      data.quiz_type || 'multiple_choice',
      data.score || null,
      data.title || 'Generated Quiz',
      Array.isArray(data.questions) ? data.questions.length : 0
    ]);

    const quizId = result.insertId;

    if (quizId) {
      // Update goal progress for this user
      try {
        await updateGoalProgress(userId, db);
      } catch (error) {
        console.error('Error updating quiz goals:', error.message);
        // Don't fail the quiz creation if goal update fails
      }

      return ResponseUtils.success(res, {
        quiz_id: quizId
      }, 'Quiz created successfully', 201);
    } else {
      return ResponseUtils.error(res, 'Failed to create quiz');
    }

  } catch (error) {
    console.error('Store quiz error:', error);
    return ResponseUtils.error(res, 'Failed to create quiz: ' + error.message);
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
    const quizId = req.query.id;

    if (!quizId) {
      return ResponseUtils.badRequest(res, 'Quiz ID is required');
    }

    // Get quiz with user verification
    const [quizzes] = await db.execute(`
      SELECT q.*
      FROM quizzes q
      INNER JOIN notes n ON q.note_id = n.id
      WHERE q.id = ? AND n.user_id = ?
    `, [quizId, userId]);

    const quiz = quizzes[0];
    if (!quiz) {
      return ResponseUtils.notFound(res, 'Quiz not found');
    }

    // Parse JSON fields
    quiz.questions = JSON.parse(quiz.questions || '[]');

    return ResponseUtils.success(res, quiz);

  } catch (error) {
    console.error('Show quiz error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve quiz: ' + error.message);
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
    const quizId = req.query.id;

    if (!quizId) {
      return ResponseUtils.badRequest(res, 'Quiz ID is required');
    }

    const data = req.body;

    // Verify the quiz belongs to the authenticated user
    const [quizCheck] = await db.execute(`
      SELECT q.id FROM quizzes q
      INNER JOIN notes n ON q.note_id = n.id
      WHERE q.id = ? AND n.user_id = ?
    `, [quizId, userId]);

    if (quizCheck.length === 0) {
      return ResponseUtils.error(res, 'Access denied', 403);
    }

    // Build update query dynamically
    const updateFields = [];
    const params = [];

    if (data.score !== undefined) {
      updateFields.push("score = ?");
      params.push(data.score);
    }

    if (data.questions !== undefined) {
      updateFields.push("questions = ?");
      params.push(JSON.stringify(data.questions));
    }

    if (data.difficulty !== undefined) {
      updateFields.push("difficulty = ?");
      params.push(data.difficulty);
    }

    if (data.quiz_type !== undefined) {
      updateFields.push("quiz_type = ?");
      params.push(data.quiz_type);
    }

    if (data.title !== undefined) {
      updateFields.push("title = ?");
      params.push(data.title);
    }

    if (data.note_title !== undefined) {
      updateFields.push("note_title = ?");
      params.push(data.note_title);
    }

    if (updateFields.length === 0) {
      return ResponseUtils.badRequest(res, 'No valid fields to update');
    }

    updateFields.push("updated_at = NOW()");
    params.push(quizId);

    const query = `UPDATE quizzes SET ${updateFields.join(', ')} WHERE id = ?`;
    const [result] = await db.execute(query, params);

    if (result.affectedRows > 0) {
      // If score was updated, update accuracy goals
      if (data.score !== undefined) {
        try {
          await updateAccuracyGoalProgress(userId, db);
        } catch (error) {
          console.error('Error updating accuracy goals:', error.message);
          // Don't fail the quiz update if goal update fails
        }
      }

      return ResponseUtils.success(res, null, 'Quiz updated successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to update quiz');
    }

  } catch (error) {
    console.error('Update quiz error:', error);
    return ResponseUtils.error(res, 'Failed to update quiz: ' + error.message);
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
    const quizId = req.query.id;

    if (!quizId) {
      return ResponseUtils.badRequest(res, 'Quiz ID is required');
    }

    // Verify the quiz belongs to the authenticated user
    const [quizCheck] = await db.execute(`
      SELECT q.id FROM quizzes q
      INNER JOIN notes n ON q.note_id = n.id
      WHERE q.id = ? AND n.user_id = ?
    `, [quizId, userId]);

    if (quizCheck.length === 0) {
      return ResponseUtils.error(res, 'Access denied or quiz not found', 403);
    }

    // Delete quiz
    const [result] = await db.execute("DELETE FROM quizzes WHERE id = ?", [quizId]);

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, null, 'Quiz deleted successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to delete quiz');
    }

  } catch (error) {
    console.error('Destroy quiz error:', error);
    return ResponseUtils.error(res, 'Failed to delete quiz: ' + error.message);
  }
}

async function updateGoalProgress(userId, db) {
  try {
    // Update goal progress for quiz completion
    const [goals] = await db.execute(`
      SELECT id, title, target_value, current_value, status
      FROM learning_goals
      WHERE user_id = ? AND target_type = 'quizzes' AND status = 'active'
    `, [userId]);

    for (const goal of goals) {
      const newCurrentValue = goal.current_value + 1;
      const newStatus = newCurrentValue >= goal.target_value ? 'completed' : 'active';

      await db.execute(
        "UPDATE learning_goals SET current_value = ?, status = ? WHERE id = ?",
        [newCurrentValue, newStatus, goal.id]
      );
    }

    return goals.length;

  } catch (error) {
    console.error('Update quiz goal progress error:', error);
    throw error;
  }
}

async function updateAccuracyGoalProgress(userId, db) {
  try {
    // Update goal progress for quiz accuracy
    const [goals] = await db.execute(`
      SELECT id, title, target_value, current_value, status
      FROM learning_goals
      WHERE user_id = ? AND target_type = 'accuracy' AND status = 'active'
    `, [userId]);

    for (const goal of goals) {
      const newCurrentValue = goal.current_value + 1;
      const newStatus = newCurrentValue >= goal.target_value ? 'completed' : 'active';

      await db.execute(
        "UPDATE learning_goals SET current_value = ?, status = ? WHERE id = ?",
        [newCurrentValue, newStatus, goal.id]
      );
    }

    return goals.length;

  } catch (error) {
    console.error('Update accuracy goal progress error:', error);
    throw error;
  }
}