import 'dotenv/config';
import { createPool } from 'mysql2/promise';

const dbHost = process.env.DB_HOST
const dbPort = Number(process.env.DB_PORT || 3306);
const dbUser = process.env.DB_USER_ADMIN;
const dbPassword = process.env.DB_PASSWORD_ADMIN;
const dbName = process.env.DB_NAME;

const poolAdmin = createPool({
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

const resolveQueryAdmin = async (sql, params) => {
    const conn = await poolAdmin.getConnection();
    const [queryResults] = await conn.execute(sql, params);
    poolAdmin.releaseConnection(conn);
    const queryRows = queryResults.length;
    return { rowsReturned: queryRows, data: queryResults };
};

const poolExecuteAdmin = async (sql, params = []) => {
  const conn = await poolAdmin.getConnection();
  const result = await conn.execute(sql, params);
  poolAdmin.releaseConnection(conn);
  return result;  
}

export { resolveQueryAdmin, poolExecuteAdmin };