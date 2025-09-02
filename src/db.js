import 'dotenv/config';
import { createPool } from 'mysql2/promise';

console.log("🚀 ~ process.env.HOST:", process.env.HOST)
console.log("🚀 ~ process.env.PORT:", process.env.PORT)
console.log("🚀 ~ process.env.USER:", process.env.USER)
console.log("🚀 ~ process.env.PASSWORD:", process.env.PASSWORD)
console.log("🚀 ~ process.env.DATABASE:", process.env.DATABASE)

const pool = createPool({
  host: process.env.HOST,
  port: process.env.PORT,
  user: process.env.USER,
  password: process.env.PASSWORD,
  database: process.env.DATABASE,
  waitForConnections: true,
  connectionLimit: 10,
  maxIdle: 10, // max idle connections, the default value is the same as `connectionLimit`
  idleTimeout: 60000, // idle connections timeout, in milliseconds, the default value 60000
  queueLimit: 0,
  enableKeepAlive: true,
  keepAliveInitialDelay: 10000,
});

export default { pool };