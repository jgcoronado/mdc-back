import express from 'express';
import adminMarchaRoutes from './adminMarcha.js';
import adminAutorRoutes from './adminAutor.js';

const router = express.Router();

router.use('/', adminMarchaRoutes);
router.use('/', adminAutorRoutes);

export default router;
