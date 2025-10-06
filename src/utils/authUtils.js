export function isTokenExpired(token) {
  if (!token) return true;
  try {
    const payload = JSON.parse(atob(token.split('.')[1]));
    const exp = payload.exp * 1000; // Convert to milliseconds
    return Date.now() > exp;
  } catch (e) {
    // Token is invalid - clear it from localStorage to prevent future errors
    console.warn('Invalid token detected, clearing from localStorage');
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    return true;
  }
}