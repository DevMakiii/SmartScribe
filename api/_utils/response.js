// api/_utils/response.js
class ResponseUtils {
  // Set CORS headers
  static setCorsHeaders(res) {
    const origin = res.req.headers.origin || '';
    const allowedOrigins = [
      'https://smartscribe-frontend.vercel.app',
      'https://your-custom-domain.com',
      'http://localhost:8080',
      'http://localhost:3000'
    ];

    // Check if origin is allowed
    const isOriginAllowed = allowedOrigins.includes(origin) || origin === '';

    if (isOriginAllowed) {
      res.setHeader('Access-Control-Allow-Origin', origin || 'https://smartscribe-frontend.vercel.app');
    } else {
      // For debugging, temporarily allow all origins (REMOVE IN PRODUCTION)
      res.setHeader('Access-Control-Allow-Origin', '*');
    }

    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-User-ID, X-Requested-With, Cache-Control, Pragma');
    res.setHeader('Access-Control-Allow-Credentials', 'true');
    res.setHeader('Access-Control-Max-Age', '86400');
  }

  // Success response
  static success(res, data = null, message = 'Success', statusCode = 200) {
    this.setCorsHeaders(res);

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
    this.setCorsHeaders(res);

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
    this.setCorsHeaders(res);

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