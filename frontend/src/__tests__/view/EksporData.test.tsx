import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import EksporData from '../../view/EksporData';
import { api } from '../../services/api';
import { createMockAxiosError } from '../../test/fixtures';
import { renderWithRouter } from '../../test/renderWithRouter';

vi.mock('../../services/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

describe('EksporData view', () => {
  const mockedApiGet = vi.mocked(api.get);

  beforeEach(() => {
    mockedApiGet.mockReset();
    vi.spyOn(window.URL, 'createObjectURL').mockReturnValue('blob:mock-xlsx-url');
  });

  it('downloads Excel file using Content-Disposition filename when export succeeds', async () => {
    const user = userEvent.setup();
    const anchorClickSpy = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(() => {});

    mockedApiGet.mockResolvedValueOnce({
      data: new Uint8Array([1, 2, 3]),
      headers: {
        'content-disposition': 'attachment; filename="laporan-harmonis-2026.xlsx"',
      },
    });

    const { container } = renderWithRouter(<EksporData />);
    const jenisSelect = container.querySelector('select[name="jenis_laporan"]') as HTMLSelectElement;
    const bulanSelect = container.querySelector('select[name="periode_bulan"]') as HTMLSelectElement;
    const tahunSelect = container.querySelector('select[name="periode_tahun"]') as HTMLSelectElement;

    await user.selectOptions(jenisSelect, 'ampera');
    await user.selectOptions(bulanSelect, '3');
    await user.selectOptions(tahunSelect, '2026');
    await user.click(screen.getByRole('button', { name: /unduh \.xlsx/i }));

    await waitFor(() => {
      expect(screen.getByText('Memulai download...')).toBeInTheDocument();
    });

    expect(mockedApiGet).toHaveBeenCalledWith('/api/export-xlsx', {
      responseType: 'blob',
      params: {
        jenis_laporan: 'ampera',
        periode_bulan: '3',
        periode_tahun: '2026',
      },
    });
    expect(anchorClickSpy).toHaveBeenCalledTimes(1);
  });

  it('shows Data belum tersedia when export endpoint returns 404', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 404 }));

    renderWithRouter(<EksporData />);
    await user.click(screen.getByRole('button', { name: /unduh \.xlsx/i }));

    expect(await screen.findByText('Data belum tersedia')).toBeInTheDocument();
  });

  it('parses and displays Laravel validation error from Blob response on 422', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockRejectedValueOnce(
      createMockAxiosError({
        status: 422,
        data: {
          text: async () =>
            JSON.stringify({
              message: 'The given data was invalid.',
              errors: {
                periode_bulan: ['Periode bulan tidak valid'],
              },
            }),
        },
      })
    );

    renderWithRouter(<EksporData />);
    await user.click(screen.getByRole('button', { name: /unduh \.xlsx/i }));

    expect(await screen.findByText('Periode bulan tidak valid')).toBeInTheDocument();
  });

  it('handles 403 forbidden response without crashing', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 403 }));

    renderWithRouter(<EksporData />);
    await user.click(screen.getByRole('button', { name: /unduh \.xlsx/i }));

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenCalledTimes(1);
    });
    expect(screen.queryByText('Memulai download...')).not.toBeInTheDocument();
  });

  it('uses default fallback filename when Content-Disposition header is missing', async () => {
    const user = userEvent.setup();
    const originalCreateElement = document.createElement.bind(document);
    let capturedDownloadName = '';

    vi.spyOn(document, 'createElement').mockImplementation((tagName: string) => {
      const el = originalCreateElement(tagName);
      if (tagName === 'a') {
        vi.spyOn(el as HTMLAnchorElement, 'click').mockImplementation(() => {
          capturedDownloadName = (el as HTMLAnchorElement).download;
        });
      }
      return el;
    });

    mockedApiGet.mockResolvedValueOnce({
      data: new Uint8Array([1, 2, 3]),
      headers: {},
    });

    const { container } = renderWithRouter(<EksporData />);
    const jenisSelect = container.querySelector('select[name="jenis_laporan"]') as HTMLSelectElement;
    const bulanSelect = container.querySelector('select[name="periode_bulan"]') as HTMLSelectElement;
    const tahunSelect = container.querySelector('select[name="periode_tahun"]') as HTMLSelectElement;

    await user.selectOptions(jenisSelect, 'pal');
    await user.selectOptions(bulanSelect, '4');
    await user.selectOptions(tahunSelect, '2026');
    await user.click(screen.getByRole('button', { name: /unduh \.xlsx/i }));

    await waitFor(() => {
      expect(screen.getByText('Memulai download...')).toBeInTheDocument();
    });

    expect(capturedDownloadName).toBe('sales-report-pal-4-2026.xlsx');
  });

  it('displays unknown error message when export fails with a network error', async () => {
    const user = userEvent.setup();
    vi.spyOn(console, 'error').mockImplementation(() => {});
    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ code: 'ERR_NETWORK' }));

    renderWithRouter(<EksporData />);
    await user.click(screen.getByRole('button', { name: /unduh \.xlsx/i }));

    expect(await screen.findByText('Terjadi kesalahan yang tidak diketahui')).toBeInTheDocument();
  });
});
