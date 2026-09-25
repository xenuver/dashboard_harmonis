import { Route, Routes } from 'react-router-dom';

import LoginScreen from './view/Login';
import Dashboard from './view/Dashboard';

const App = () => {
  return (
    <Routes>
      <Route path="/login" element={<LoginScreen />} />
      <Route path="/dashboard" element={<Dashboard />} />
    </Routes>
  );
}

export default App;