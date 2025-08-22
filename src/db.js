import 'dotenv/config';
import { createConnection } from 'mysql2/promise';

const connection = await createConnection({
  host: process.env.HOST,
  port: process.env.PORT,
  user: process.env.USER,
  password: process.env.PASSWORD,
  database: process.env.DATABASE,
});

export default connection;