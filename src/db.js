import 'dotenv/config';
import { createPool } from 'mysql2/promise';

const dbHost = process.env.DB_HOST || process.env.HOST;
console.log("🚀 ~ dbHost:", dbHost)
const dbPort = Number(process.env.DB_PORT || process.env.PORT || 3306);
console.log("🚀 ~ dbPort:", dbPort)
const dbUser = process.env.DB_USER || process.env.USER;
console.log("🚀 ~ dbUser:", dbUser)
const dbPassword = process.env.DB_PASSWORD || process.env.PASSWORD;
console.log("🚀 ~ dbPassword:", dbPassword)
const dbName = process.env.DB_NAME || process.env.DATABASE;
console.log("🚀 ~ dbName:", dbName)

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
