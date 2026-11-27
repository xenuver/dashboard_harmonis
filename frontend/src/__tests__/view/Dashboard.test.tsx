import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import Dashboard from '../../view/Dashboard';
import { api } from '../../services/api';
import sessionManager from '../../services/sessionManager';
import {
  createMockAxiosError,
  mockDashboardData,
  mockItStaffUser,
  mockOwnerUser,
} from '../../test/fixtures';
import { renderWithRouter } from '../../test/renderWithRouter';

vi.mock('../../services/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

vi.mock('recharts', async () => {
  const actual = await vi.importActual<typeof import('recharts')>('recharts');
  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: ReactNode }) => (
      <div data-testid="responsive-container" style={{ width: 800, height: 320 }}>
        {children}
      </div>
    ),
  };
});

describe('Dashboard view', () => {
  const mockedApiGet = vi.mocked(api.get);

  beforeEach(() => {
    sessionManager.deleteTokens();
    mockedApiGet.mockReset();
  });

  it('fetches dashboard data using URL query params and renders KPI cards and tables', async () => {
    mockedApiGet.mockResolvedValueOnce({ data: mockDashboardData });

    renderWithRouter(<Dashboard />, {
      initialEntries: ['/dashboard?jenis_laporan=ampera&periode_bulan=3&periode_tahun=2026'],
    });

    await waitFor(() => {
      expect(screen.getByText('Rp12.500.000,-')).toBeInTheDocument();
    });

    expect(mockedApiGet).toHaveBeenCalledWith(
      '/api/dashboard',
      expect.objectContaining({
        responseType: 'json',
        params: {
          jenis_laporan: 'ampera',
          periode_bulan: 3,
          periode_tahun: 2026,
        },
      })
    );

    expect(screen.getByText('+14.5%')).toBeInTheDocument();
    expect(screen.getByText('450')).toBeInTheDocument();
    expect(screen.getByText('+125K')).toBeInTheDocument();

    expect(screen.getByText('Beras Premium 5kg')).toBeInTheDocument();
    expect(screen.getByText('Minyak Goreng 2L')).toBeInTheDocument();
    expect(screen.getByText('PT Sumber Makmur')).toBeInTheDocument();
    expect(screen.getByText('CV Retur Jaya')).toBeInTheDocument();

    const detailLinks = screen.getAllByRole('link', { name: /lihat peringkat produk/i });
    expect(detailLinks[0]).toHaveAttribute(
      'href',
      '/produk-penjualan?jenis_laporan=ampera&periode_bulan=3&periode_tahun=2026&kategori=tertinggi'
    );
    expect(detailLinks[1]).toHaveAttribute(
      'href',
      '/produk-penjualan?jenis_laporan=ampera&periode_bulan=3&periode_tahun=2026&kategori=terendah'
    );
  });

  it('refetches dashboard data when filter dropdowns change', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockResolvedValue({ data: mockDashboardData });

    const { container } = renderWithRouter(<Dashboard />, {
      initialEntries: ['/dashboard?jenis_laporan=gabungan&periode_bulan=3&periode_tahun=2026'],
    });

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenCalledTimes(1);
    });

    const jenisSelect = container.querySelector('select[name="jenis_laporan"]') as HTMLSelectElement;
    await user.selectOptions(jenisSelect, 'pal');

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenCalledTimes(2);
    });

    expect(mockedApiGet).toHaveBeenLastCalledWith(
      '/api/dashboard',
      expect.objectContaining({
        params: expect.objectContaining({
          jenis_laporan: 'pal',
        }),
      })
    );
  });

  it('renders empty state rows and fallback KPI hyphens when backend returns empty/unexpected KPI data', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    mockedApiGet.mockResolvedValueOnce({
      data: {
        kpi: {
          total_sales: null,
          total_growth: undefined,
          jumlah_transaksi: null,
          growth_member: null,
        },
        trend: {},
        transaksi: { transaksi_member: 0, transaksi_non_member: 0 },
        top_produk_terlaris: [],
        top_produk_terendah: [],
        top_supplier: [],
      },
    });

    renderWithRouter(<Dashboard />);

    await waitFor(() => {
      expect(screen.getAllByText('Tidak ada data')).toHaveLength(3);
    });

    expect(screen.getAllByText('-').length).toBeGreaterThanOrEqual(4);
  });

  it('shows ReportUnavailableCard on 404 with upload CTA only for it_staff', async () => {
    sessionManager.setIdentity(mockItStaffUser);
    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 404 }));

    const first = renderWithRouter(<Dashboard />, {
      initialEntries: ['/dashboard?jenis_laporan=pal&periode_bulan=4&periode_tahun=2026'],
    });

    expect(await screen.findByText('Data belum tersedia')).toBeInTheDocument();
    const uploadLink = screen.getByRole('link', { name: /menuju halaman upload data/i });
    expect(uploadLink).toHaveAttribute(
      'href',
      '/upload?jenis_laporan=pal&periode_bulan=4&periode_tahun=2026'
    );

    first.unmount();

    sessionManager.setIdentity(mockOwnerUser);
    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 404 }));

    renderWithRouter(<Dashboard />);
    expect(await screen.findByText('Data belum tersedia')).toBeInTheDocument();
    expect(
      screen.queryByRole('link', { name: /menuju halaman upload data/i })
    ).not.toBeInTheDocument();
  });

  it('shows NotAllowedCard on 403 and retries successfully after NoConnectionCard', async () => {
    const user = userEvent.setup();
    vi.spyOn(console, 'error').mockImplementation(() => {});

    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 403 }));
    const forbidden = renderWithRouter(<Dashboard />);
    expect(await screen.findByText('Tidak diperbolehkan')).toBeInTheDocument();
    forbidden.unmount();

    mockedApiGet
      .mockRejectedValueOnce(createMockAxiosError({ code: 'ERR_NETWORK' }))
      .mockResolvedValueOnce({ data: mockDashboardData });

    renderWithRouter(<Dashboard />);
    expect(await screen.findByText('Kesalahan Koneksi')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /coba lagi/i }));

    await waitFor(() => {
      expect(screen.getByText('Rp12.500.000,-')).toBeInTheDocument();
    });
  });

  it('shows UnknownErrorCard on non-Axios errors and recovers on retry', async () => {
    const user = userEvent.setup();
    vi.spyOn(console, 'error').mockImplementation(() => {});

    mockedApiGet
      .mockRejectedValueOnce(new Error('Unexpected parse crash'))
      .mockResolvedValueOnce({ data: mockDashboardData });

    renderWithRouter(<Dashboard />);
    expect(await screen.findByText('Kesalahan Tidak Diketahui')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /coba lagi/i }));

    await waitFor(() => {
      expect(screen.getByText('Rp12.500.000,-')).toBeInTheDocument();
    });
  });
});
