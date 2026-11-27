import { AxiosError, AxiosHeaders, type InternalAxiosRequestConfig } from 'axios';
import type { DashboardResponseData } from '../../types/DashboardResponseData';
import type { Laporan } from '../../types/Models/Laporan';
import type { Product } from '../../types/Models/Product';
import type { Supplier } from '../../types/Models/Supplier';
import type { UserInformation } from '../../types/UserInformation';

export const mockItStaffUser: UserInformation = {
  id: 1,
  username: 'admin_it',
  role: 'it_staff',
  enabled: true,
};

export const mockOwnerUser: UserInformation = {
  id: 2,
  username: 'owner_toko',
  role: 'owner',
  enabled: true,
};

export const mockProducts: Product[] = [
  {
    id: 1,
    upload_id: 10,
    barcode: '8991234567890',
    kode_brg: 'BRG-001',
    nama_brg: 'Beras Premium 5kg',
    satuan: 'SAK',
    harga_jual: 75000,
    jumlah: 1500000,
    qty: 20,
    stok: 12,
  },
  {
    id: 2,
    upload_id: 10,
    barcode: '',
    kode_brg: 'BRG-002',
    nama_brg: 'Minyak Goreng 2L',
    satuan: '',
    harga_jual: 36000,
    jumlah: -72000,
    qty: -2,
    stok: -1,
  },
];

export const mockSuppliers: Supplier[] = [
  {
    id: 1,
    upload_id: 10,
    kode_supp: 'SUP-01',
    nama_supp: 'PT Sumber Makmur',
    gross_total: 25000000,
    net_sales_total: 22500000,
  },
  {
    id: 2,
    upload_id: 10,
    kode_supp: 'SUP-02',
    nama_supp: 'CV Retur Jaya',
    gross_total: -500000,
    net_sales_total: -450000,
  },
];

export const mockLaporans: Laporan[] = [
  {
    id: 101,
    jenis_laporan: 'gabungan',
    periode_bulan: 3,
    periode_tahun: 2026,
    jumlah_hari: 31,
    format_data: 'v2',
    catatan: 'Catatan singkat',
    status: 'completed',
  },
  {
    id: 102,
    jenis_laporan: 'ampera',
    periode_bulan: 2,
    periode_tahun: 2026,
    jumlah_hari: 28,
    format_data: 'v2',
    catatan: 'Ini adalah catatan laporan yang sangat panjang sekali melebihi dua puluh lima karakter',
    status: 'completed',
  },
];

export const mockDashboardData: DashboardResponseData = {
  kpi: {
    id: 1,
    upload_id: 10,
    total_sales: 12500000,
    total_growth: '14.5',
    jumlah_transaksi: 450,
    growth_member: 125000,
  },
  trend: {
    '2026-03-01': [
      { cabang: 'Ampera', total: 4000000 },
      { cabang: 'Pal', total: 3500000 },
    ],
    '2026-03-02': [
      { cabang: 'Ampera', total: 2500000 },
      { cabang: 'Pal', total: 2500000 },
    ],
  },
  transaksi: {
    transaksi_member: 300,
    transaksi_non_member: 150,
  },
  top_produk_terlaris: [
    { ...mockProducts[0], kategori: 'tertinggi' },
  ],
  top_produk_terendah: [
    { ...mockProducts[1], kategori: 'terendah' },
  ],
  top_supplier: mockSuppliers,
};

export function createMockAxiosError(options: {
  status?: number;
  code?: string;
  data?: unknown;
  message?: string;
}): AxiosError {
  const config: InternalAxiosRequestConfig = {
    headers: new AxiosHeaders(),
  };

  const error = new AxiosError(
    options.message ?? 'Mock Axios Error',
    options.code ?? 'ERR_BAD_REQUEST',
    config,
    undefined,
    options.status !== undefined
      ? {
          data: options.data ?? {},
          status: options.status,
          statusText: 'Error',
          headers: {},
          config,
        }
      : undefined
  );

  if (options.status !== undefined) {
    error.status = options.status;
  }

  return error;
}
