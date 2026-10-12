import { Route, Routes } from 'react-router-dom';

import LoginScreen from './view/Login';
import Dashboard from './view/Dashboard';
import Upload from './view/Upload'
import ProdukPenjualan from './view/ProdukPenjualan';
import SupplierPenjualan from './view/SupplierPenjualan';
import UiTest from './view/UiTest';
import { LoggedInLayout } from "./layouts/LoggedInLayout";
import { LoggedOutLayout } from "./layouts/LoggedOutLayout";
import AutoRedirectIfSignedIn from "./components/routes/auth/AutoRedirectIfSignedIn";
import AutoRedirectIfSignedOut from "./components/routes/auth/AutoRedirectIfSignedOut";

import './styles/index.css';

const App = () => {
  return (
    <Routes>
      <Route element={<AutoRedirectIfSignedIn />}>
        <Route path="/" />
        <Route element={<LoggedOutLayout />}>
          <Route path="/login" element={<LoginScreen />} />
        </Route>
      </Route>

      <Route element={<AutoRedirectIfSignedOut />}>
        <Route path="/" />
        <Route element={<LoggedInLayout />}>
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/upload" element={<Upload />} />
          <Route path="/produk-penjualan" element={<ProdukPenjualan />} />
          <Route path="/supplier-penjualan" element={<SupplierPenjualan />} />
        </Route>
      </Route>
      <Route path="/uitest" element={<UiTest />} />
    </Routes>
  );
}

export default App;