import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../assets/style.css';
import { SessionProvider } from './session.jsx';
import App from './App.jsx';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <SessionProvider>
      <App />
    </SessionProvider>
  </StrictMode>,
);
