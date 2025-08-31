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
app.use('/login', loginRoutes);
app.use('/marcha', marchaRoutes);
app.use('/autor', autorRoutes);
app.use('/banda', bandaRoutes);
app.use('/disco', discoRoutes);
app.use('/stats', statsRoutes);

app.get('/', (req, res) => {
  res.send('Hello World');  
});

app.listen(port, () => {
  console.log(`Server listening on port ${port}`);  
});