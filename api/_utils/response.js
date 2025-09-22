// api/_utils/response.js
class ResponseUtils {
  // Success response
  static success(res, data = null, message = 'Success', statusCode = 200) {
    const response = {
      success: true,
      message
    };

    if (data !== null) {
      response.data = data;
    }

    return res.status(statusCode).json(response);
  }

  // Error response
  static error(res, message = 'An error occurred', statusCode = 500) {
    const response = {
      success: false,
      message
    };

    return res.status(statusCode).json(response);
  }

  // Bad request response
  static badRequest(res, message = 'Bad request') {
    return this.error(res, message, 400);
  }

  // Unauthorized response
  static unauthorized(res, message = 'Unauthorized') {
    return this.error(res, message, 401);
  }

  // Forbidden response
  static forbidden(res, message = 'Forbidden') {
    return this.error(res, message, 403);
  }

  // Not found response
  static notFound(res, message = 'Not found') {
    return this.error(res, message, 404);
  }

  // Validation error response
  static validationError(res, errors) {
    const response = {
      success: false,
      message: 'Validation failed',
      errors
    };

    return res.status(422).json(response);
  }

  // Server error response
  static serverError(res, message = 'Internal server error') {
    return this.error(res, message, 500);
  }

  // Conflict response
  static conflict(res, message = 'Conflict') {
    return this.error(res, message, 409);
  }
}

module.exports = ResponseUtils;