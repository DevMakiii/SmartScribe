// api/goals.js
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
      case 'updateProgress':
        return await handleUpdateProgress(req, res, db);
      case 'stats':
        return await handleStats(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('Goals error:', error);
    return ResponseUtils.error(res, 'Goals operation failed: ' + error.message);
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

    // Get all goals for the user
    const [goals] = await db.execute(
      "SELECT * FROM learning_goals WHERE user_id = ? ORDER BY created_at DESC",
      [userId]
    );

    return ResponseUtils.success(res, goals, 'Goals retrieved successfully');

  } catch (error) {
    console.error('Index goals error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve goals: ' + error.message);
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
    if (!data.title || !data.target_type || !data.target_value) {
      return ResponseUtils.badRequest(res, 'Missing required fields: title, target_type, target_value');
    }

    // Insert goal
    const [result] = await db.execute(`
      INSERT INTO learning_goals (
        user_id, title, description, target_type, target_value,
        current_value, deadline, status, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    `, [
      userId,
      AuthUtils.sanitizeInput(data.title),
      AuthUtils.sanitizeInput(data.description || ''),
      data.target_type,
      data.target_value,
      data.current_value || 0,
      data.deadline || null,
      data.status || 'active'
    ]);

    const goalId = result.insertId;

    if (goalId) {
      return ResponseUtils.success(res, {
        goal_id: goalId
      }, 'Goal created successfully', 201);
    } else {
      return ResponseUtils.error(res, 'Failed to create goal');
    }

  } catch (error) {
    console.error('Store goal error:', error);
    return ResponseUtils.error(res, 'Failed to create goal: ' + error.message);
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
    const goalId = req.query.id;

    if (!goalId) {
      return ResponseUtils.badRequest(res, 'Goal ID is required');
    }

    // Get goal by ID and user
    const [goals] = await db.execute(
      "SELECT * FROM learning_goals WHERE id = ? AND user_id = ?",
      [goalId, userId]
    );

    const goal = goals[0];
    if (!goal) {
      return ResponseUtils.notFound(res, 'Goal not found');
    }

    return ResponseUtils.success(res, goal);

  } catch (error) {
    console.error('Show goal error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve goal: ' + error.message);
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
    const goalId = req.query.id;

    if (!goalId) {
      return ResponseUtils.badRequest(res, 'Goal ID is required');
    }

    const data = req.body;

    // Verify the goal belongs to the authenticated user
    const [goalCheck] = await db.execute(
      "SELECT id FROM learning_goals WHERE id = ? AND user_id = ?",
      [goalId, userId]
    );

    if (goalCheck.length === 0) {
      return ResponseUtils.error(res, 'Access denied or goal not found', 403);
    }

    // Build update query dynamically
    const updateFields = [];
    const params = [];

    if (data.title !== undefined) {
      updateFields.push("title = ?");
      params.push(AuthUtils.sanitizeInput(data.title));
    }

    if (data.description !== undefined) {
      updateFields.push("description = ?");
      params.push(AuthUtils.sanitizeInput(data.description));
    }

    if (data.target_type !== undefined) {
      updateFields.push("target_type = ?");
      params.push(data.target_type);
    }

    if (data.target_value !== undefined) {
      updateFields.push("target_value = ?");
      params.push(data.target_value);
    }

    if (data.current_value !== undefined) {
      updateFields.push("current_value = ?");
      params.push(data.current_value);
    }

    if (data.deadline !== undefined) {
      updateFields.push("deadline = ?");
      params.push(data.deadline);
    }

    if (data.status !== undefined) {
      updateFields.push("status = ?");
      params.push(data.status);
    }

    if (updateFields.length === 0) {
      return ResponseUtils.badRequest(res, 'No valid fields to update');
    }

    updateFields.push("updated_at = NOW()");
    params.push(goalId);

    const query = `UPDATE learning_goals SET ${updateFields.join(', ')} WHERE id = ?`;
    const [result] = await db.execute(query, params);

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, null, 'Goal updated successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to update goal');
    }

  } catch (error) {
    console.error('Update goal error:', error);
    return ResponseUtils.error(res, 'Failed to update goal: ' + error.message);
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
    const goalId = req.query.id;

    if (!goalId) {
      return ResponseUtils.badRequest(res, 'Goal ID is required');
    }

    // Verify the goal belongs to the authenticated user
    const [goalCheck] = await db.execute(
      "SELECT id FROM learning_goals WHERE id = ? AND user_id = ?",
      [goalId, userId]
    );

    if (goalCheck.length === 0) {
      return ResponseUtils.error(res, 'Access denied or goal not found', 403);
    }

    // Delete goal
    const [result] = await db.execute("DELETE FROM learning_goals WHERE id = ?", [goalId]);

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, null, 'Goal deleted successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to delete goal');
    }

  } catch (error) {
    console.error('Destroy goal error:', error);
    return ResponseUtils.error(res, 'Failed to delete goal: ' + error.message);
  }
}

async function handleUpdateProgress(req, res, db) {
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
    const goalId = req.query.id;

    if (!goalId) {
      return ResponseUtils.badRequest(res, 'Goal ID is required');
    }

    const data = req.body;

    // Validate required fields
    if (data.current_value === undefined) {
      return ResponseUtils.badRequest(res, 'Missing current_value');
    }

    // Verify the goal belongs to the authenticated user
    const [goalCheck] = await db.execute(
      "SELECT id, target_value, status FROM learning_goals WHERE id = ? AND user_id = ?",
      [goalId, userId]
    );

    if (goalCheck.length === 0) {
      return ResponseUtils.error(res, 'Access denied or goal not found', 403);
    }

    const goal = goalCheck[0];
    const newStatus = data.current_value >= goal.target_value ? 'completed' : 'active';

    // Update goal progress
    const [result] = await db.execute(
      "UPDATE learning_goals SET current_value = ?, status = ?, updated_at = NOW() WHERE id = ?",
      [data.current_value, newStatus, goalId]
    );

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, null, 'Goal progress updated successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to update goal progress');
    }

  } catch (error) {
    console.error('Update progress error:', error);
    return ResponseUtils.error(res, 'Failed to update goal progress: ' + error.message);
  }
}

async function handleStats(req, res, db) {
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

    // Get active goals count
    const [activeGoals] = await db.execute(
      "SELECT COUNT(*) as count FROM learning_goals WHERE user_id = ? AND status = 'active'",
      [userId]
    );

    // Get completed goals this month
    const [completedGoals] = await db.execute(`
      SELECT COUNT(*) as count
      FROM learning_goals
      WHERE user_id = ? AND status = 'completed'
      AND updated_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    `, [userId]);

    return ResponseUtils.success(res, {
      active_goals: parseInt(activeGoals[0].count) || 0,
      completed_goals: parseInt(completedGoals[0].count) || 0
    }, 'Goal stats retrieved successfully');

  } catch (error) {
    console.error('Goal stats error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve goal stats: ' + error.message);
  }
}