import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ProdukPenjualan from '../../view/ProdukPenjualan';
import { api } from '../../services/api';
import sessionManager from '../../services/sessionManager';
import { createMockAxiosError, mockItStaffUser, mockProducts } from '../../test/fixtures';
import { renderWithRouter } from '../../test/renderWithRouter';

vi.mock('../../services/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

describe('ProdukPenjualan view', () => {
  const mockedApiGet = vi.mocked(api.get);

  beforeEach(() => {
    sessionManager.deleteTokens();
    sessionManager.setIdentity(mockItStaffUser);
    mockedApiGet.mockReset();
  });

  it('loads product rankings from initial URL params and renders formatted rows', async () => {
    mockedApiGet.mockResolvedValueOnce({
      data: {
        current_page: 1,
        data: mockProducts,
        last_page: 2,
        per_page: 10,
        total: 12,
      },
    });

    renderWithRouter(<ProdukPenjualan />, {
      initialEntries: [
        '/produk-penjualan?jenis_laporan=ampera&periode_bulan=3&periode_tahun=2026&kategori=terendah&search=beras',
      ],
    });

    await waitFor(() => {
      expect(screen.getByText('Beras Premium 5kg')).toBeInTheDocument();
    });

    expect(mockedApiGet).toHaveBeenCalledWith(
      '/api/produk-penjualan',
      expect.objectContaining({
        params: {
          page: 1,
          jenis_laporan: 'ampera',
          periode_tahun: 2026,
          periode_bulan: 3,
          per_page: 10,
          kategori: 'terendah',
          search: 'beras',
        },
      })
    );

    expect(screen.getByText('8991234567890')).toBeInTheDocument();
    expect(screen.getByText('Rp75.000,-')).toBeInTheDocument();
    expect(screen.getByText('Menampilkan produk 1-2 dari 12')).toBeInTheDocument();
  });

  it('supports keyword search via Enter/Cari button, category toggle, and pagination', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockResolvedValue({
      data: {
        current_page: 1,
        data: mockProducts,
        last_page: 3,
        per_page: 10,
        total: 25,
      },
    });

    const { container } = renderWithRouter(<ProdukPenjualan />, {
      initialEntries: ['/produk-penjualan?periode_bulan=3&periode_tahun=2026'],
    });

    await waitFor(() => {
      expect(screen.getByText('Beras Premium 5kg')).toBeInTheDocument();
    });

    const searchInput = container.querySelector('input[name="cariproduk"]') as HTMLInputElement;
    await user.type(searchInput, 'minyak{Enter}');

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/produk-penjualan',
        expect.objectContaining({
          params: expect.objectContaining({ search: 'minyak', page: 1 }),
        })
      );
    });

    await user.click(screen.getByRole('button', { name: 'Terendah' }));
    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/produk-penjualan',
        expect.objectContaining({
          params: expect.objectContaining({ kategori: 'terendah', page: 1 }),
        })
      );
    });

    await user.click(screen.getByRole('button', { name: /selanjutnya/i }));
    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/produk-penjualan',
        expect.objectContaining({
          params: expect.objectContaining({ page: 2 }),
        })
      );
    });
  });

  it('renders NoDataFoundCard when search returns an empty array and handles 404/403/network errors', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});

    mockedApiGet.mockResolvedValueOnce({
      data: { current_page: 1, data: [], last_page: 1, per_page: 10, total: 0 },
    });
    const emptyRender = renderWithRouter(<ProdukPenjualan />);
    expect(await screen.findByText('Tidak ada data yang cocok')).toBeInTheDocument();
    emptyRender.unmount();

    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 404 }));
    const notFoundRender = renderWithRouter(<ProdukPenjualan />);
    expect(await screen.findByText('Data belum tersedia')).toBeInTheDocument();
    notFoundRender.unmount();

    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 403 }));
    const forbiddenRender = renderWithRouter(<ProdukPenjualan />);
    expect(await screen.findByText('Tidak diperbolehkan')).toBeInTheDocument();
    forbiddenRender.unmount();

    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ code: 'ERR_NETWORK' }));
    renderWithRouter(<ProdukPenjualan />);
    expect(await screen.findByText('Kesalahan Koneksi')).toBeInTheDocument();
  });
});
