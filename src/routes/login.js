import connection from '../db.js';
import express from 'express';
import { resolveQuery, formatAutor } from '../helpers/index.js';

const router = express.Router();

router.post('/', async (req, res, next) => {
    const { username, password } = req.body;
    const sql = `SELECT u.USUARIO, u.CLAVE FROM usuarios u
        WHERE u.USUARIO LIKE ? LIMIT 1`;
    const params = [username];
    const results = await resolveQuery(sql,params);
    console.log("🚀 ~ results:", results.data[0])
    const credenciales = results?.data[0];
    if ( results.rowsReturned !== 1 || credenciales.USUARIO !== username ) {
        res.status(401).json( { msg: 'Wrong user'});
    }
    if( credenciales.CLAVE !== password){
        res.status(401).json( { msg: 'Wrong pass'});
    }
    res.status(200).json(
        {
            code: 201,
            login: true,
            data: {
                text: 'loggin correcto',
                token: 'klapaucius'
            }
        }
    );
});

export default router;