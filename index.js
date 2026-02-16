import express from 'express';
import loginRoutes from './src/routes/login.js';
import marchaRoutes from './src/routes/marcha.js';
import autorRoutes from './src/routes/autor.js';
import bandaRoutes from './src/routes/banda.js';
import discoRoutes from './src/routes/disco.js';
import statsRoutes from './src/routes/stats.js';


import cors from 'cors';

const app = express();
const port = 3000;  

app.use(express.json());
app.use(cors());
app.use('/api/login', loginRoutes);
app.use('/api/marcha', marchaRoutes);
app.use('/api/autor', autorRoutes);
app.use('/api/banda', bandaRoutes);
app.use('/api/disco', discoRoutes);
app.use('/api/stats', statsRoutes);
app.set('trust proxy', true);

app.get('/', (req, res) => {
  res.send('Hello World');  
});

app.listen(port, () => {
  console.log(`Server listening on port ${port}`);  
});
