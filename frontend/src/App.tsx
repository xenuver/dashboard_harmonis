import { Route, Routes } from 'react-router-dom';

import LoginScreen from './view/Login';
import Dashboard from './view/Dashboard';
import Upload from './view/Upload'
import ProdukPenjualan from './view/ProdukPenjualan';
import SupplierPenjualan from './view/SupplierPenjualan';

const App = () => {
  return (
    <Routes>
      <Route path="/login" element={<LoginScreen />} />
      <Route path="/dashboard" element={<Dashboard />} />
      <Route path="/upload" element={<Upload />} />
      <Route path="/produk-penjualan" element={<ProdukPenjualan />} />
      <Route path="/supplier-penjualan" element={<SupplierPenjualan />} />
    </Routes>
  );
}

export default App;