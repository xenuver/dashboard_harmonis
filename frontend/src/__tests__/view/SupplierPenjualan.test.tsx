import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SupplierPenjualan from '../../view/SupplierPenjualan';
import { api } from '../../services/api';
import sessionManager from '../../services/sessionManager';
import { createMockAxiosError, mockItStaffUser, mockSuppliers } from '../../test/fixtures';
import { renderWithRouter } from '../../test/renderWithRouter';

vi.mock('../../services/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

describe('SupplierPenjualan view', () => {
  const mockedApiGet = vi.mocked(api.get);

  beforeEach(() => {
    sessionManager.deleteTokens();
    sessionManager.setIdentity(mockItStaffUser);
    mockedApiGet.mockReset();
  });

  it('fetches supplier rankings using URL parameters and formats currency cells', async () => {
    mockedApiGet.mockResolvedValueOnce({
      data: {
        current_page: 1,
        data: mockSuppliers,
        last_page: 2,
        per_page: 10,
        total: 15,
      },
    });

    renderWithRouter(<SupplierPenjualan />, {
      initialEntries: [
        '/supplier-penjualan?jenis_laporan=pal&periode_bulan=2&periode_tahun=2026&search=makmur',
      ],
    });

    await waitFor(() => {
      expect(screen.getByText('PT Sumber Makmur')).toBeInTheDocument();
    });

    expect(mockedApiGet).toHaveBeenCalledWith(
      '/api/supplier-penjualan',
      expect.objectContaining({
        params: {
          page: 1,
          jenis_laporan: 'pal',
          periode_tahun: 2026,
          periode_bulan: 2,
          per_page: 10,
          search: 'makmur',
        },
      })
    );

    expect(screen.getByText('SUP-01')).toBeInTheDocument();
    expect(screen.getByText('Rp25.000.000,-')).toBeInTheDocument();
    expect(screen.getByText('Rp22.500.000,-')).toBeInTheDocument();
    expect(screen.getByText('Menampilkan supplier 1-2 dari 15')).toBeInTheDocument();
  });

  it('supports search button click, per-page selection, and pagination', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockResolvedValue({
      data: {
        current_page: 1,
        data: mockSuppliers,
        last_page: 2,
        per_page: 10,
        total: 15,
      },
    });

    const { container } = renderWithRouter(<SupplierPenjualan />, {
      initialEntries: ['/supplier-penjualan?periode_bulan=3&periode_tahun=2026'],
    });

    await waitFor(() => {
      expect(screen.getByText('PT Sumber Makmur')).toBeInTheDocument();
    });

    const searchInput = container.querySelector('input[name="carisupplier"]') as HTMLInputElement;
    await user.type(searchInput, 'retur');
    await user.click(screen.getByRole('button', { name: /cari/i }));

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/supplier-penjualan',
        expect.objectContaining({
          params: expect.objectContaining({ search: 'retur', page: 1 }),
        })
      );
    });

    const perPageSelect = container.querySelector('select[name="jenislaporan"]') as HTMLSelectElement;
    await user.selectOptions(perPageSelect, '20');

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/supplier-penjualan',
        expect.objectContaining({
          params: expect.objectContaining({ per_page: 20, page: 1 }),
        })
      );
    });

    await user.click(screen.getByRole('button', { name: /selanjutnya/i }));
    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/supplier-penjualan',
        expect.objectContaining({
          params: expect.objectContaining({ page: 2 }),
        })
      );
    });
  });

  it('renders empty state, 404, 403, and connection error states with retry', async () => {
    const user = userEvent.setup();
    vi.spyOn(console, 'error').mockImplementation(() => {});

    mockedApiGet.mockResolvedValueOnce({
      data: { current_page: 1, data: [], last_page: 1, per_page: 10, total: 0 },
    });
    const empty = renderWithRouter(<SupplierPenjualan />);
    expect(await screen.findByText('Tidak ada data yang cocok')).toBeInTheDocument();
    empty.unmount();

    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 404 }));
    const notFound = renderWithRouter(<SupplierPenjualan />);
    expect(await screen.findByText('Data belum tersedia')).toBeInTheDocument();
    notFound.unmount();

    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 403 }));
    const forbidden = renderWithRouter(<SupplierPenjualan />);
    expect(await screen.findByText('Tidak diperbolehkan')).toBeInTheDocument();
    forbidden.unmount();

    mockedApiGet
      .mockRejectedValueOnce(createMockAxiosError({ code: 'ERR_NETWORK' }))
      .mockResolvedValueOnce({
        data: { current_page: 1, data: mockSuppliers, last_page: 1, per_page: 10, total: 2 },
      });

    renderWithRouter(<SupplierPenjualan />);
    expect(await screen.findByText('Kesalahan Koneksi')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: /coba lagi/i }));

    expect(await screen.findByText('PT Sumber Makmur')).toBeInTheDocument();
  });
});
