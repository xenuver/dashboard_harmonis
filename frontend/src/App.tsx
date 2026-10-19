import { Route, Routes } from 'react-router-dom';

import LoginScreen from './view/Login';
import Dashboard from './view/Dashboard';
import Upload from './view/Upload'
import ProdukPenjualan from './view/ProdukPenjualan';
import SupplierPenjualan from './view/SupplierPenjualan';
// import UiTest from './view/UiTest';
import KelolaPengguna from './view/KelolaPengguna';
import { LoggedInLayout } from "./layouts/LoggedInLayout";
import { LoggedOutLayout } from "./layouts/LoggedOutLayout";
import AutoRedirectIfSignedIn from "./components/routes/auth/AutoRedirectIfSignedIn";
import AutoRedirectIfSignedOut from "./components/routes/auth/AutoRedirectIfSignedOut";
import RedirectToLoginOrDashboard from "./components/routes/auth/RedirectToLoginOrDashboard";

import './styles/index.css';

const App = () => {
  return (
    <Routes>
      <Route element={<RedirectToLoginOrDashboard />}>
        <Route path="/" element={<div />} />
      </Route>

      <Route element={<AutoRedirectIfSignedIn />}>
        <Route element={<LoggedOutLayout />}>
          <Route path="/login" element={<LoginScreen />} />
        </Route>
      </Route>

      <Route element={<AutoRedirectIfSignedOut />}>
        <Route element={<LoggedInLayout />}>
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/upload" element={<Upload />} />
          <Route path="/produk-penjualan" element={<ProdukPenjualan />} />
          <Route path="/supplier-penjualan" element={<SupplierPenjualan />} />
          <Route path="/kelola-pengguna" element={<KelolaPengguna />} />
        </Route>
      </Route>
      {/* <Route path="/uitest" element={<UiTest />} /> */}
    </Routes>
  );
}

export default App;