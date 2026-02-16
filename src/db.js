import 'dotenv/config';
import { createPool } from 'mysql2/promise';

const dbHost = process.env.DB_HOST || process.env.HOST;
const dbPort = Number(process.env.DB_PORT || process.env.PORT || 3306);
const dbUser = process.env.DB_USER || process.env.USER;
const dbPassword = process.env.DB_PASSWORD || process.env.PASSWORD;
const dbName = process.env.DB_NAME || process.env.DATABASE;

const pool = createPool({
  host: dbHost,
  port: dbPort,
  user: dbUser,
  password: dbPassword,
  database: dbName,
  waitForConnections: true,
  connectionLimit: 10,
  maxIdle: 10, // max idle connections, the default value is the same as `connectionLimit`
  idleTimeout: 60000, // idle connections timeout, in milliseconds, the default value 60000
  queueLimit: 0,
  enableKeepAlive: true,
  keepAliveInitialDelay: 10000,
});

export default { pool };
