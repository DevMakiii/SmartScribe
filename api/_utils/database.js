// api/_utils/database.js
const mysql = require('mysql2/promise');
require('dotenv').config({ path: '../.env' });

let dbConfig = {
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'smartscribe_new',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
};

// Create connection pool
let pool;

async function getDbConnection() {
  try {
    if (!pool) {
      pool = mysql.createPool(dbConfig);
      console.log('Database connection pool created');
    }

    // Test connection
    const connection = await pool.getConnection();
    console.log('Database connected successfully');
    connection.release();

    return pool;
  } catch (error) {
    console.error('Database connection failed:', error.message);
    throw error;
  }
}

module.exports = { getDbConnection };