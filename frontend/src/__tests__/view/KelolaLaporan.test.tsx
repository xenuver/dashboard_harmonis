import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import KelolaLaporan from '../../view/KelolaLaporan';
import { api } from '../../services/api';
import { createMockAxiosError, mockLaporans } from '../../test/fixtures';
import { renderWithRouter } from '../../test/renderWithRouter';

vi.mock('../../services/api', () => ({
  api: {
    get: vi.fn(),
    postForm: vi.fn(),
  },
}));

describe('KelolaLaporan view & report management dialogs', () => {
  const mockedApiGet = vi.mocked(api.get);
  const mockedPostForm = vi.mocked(api.postForm);

  beforeEach(() => {
    mockedApiGet.mockReset();
    mockedPostForm.mockReset();
  });

  it('renders reports table with month names and truncated long notes', async () => {
    mockedApiGet.mockResolvedValueOnce({
      data: {
        current_page: 1,
        data: mockLaporans,
        last_page: 1,
        per_page: 10,
        total: 2,
      },
    });

    renderWithRouter(<KelolaLaporan />);

    const table = within(screen.getByRole('table'));
    expect(await table.findByText('Catatan singkat')).toBeInTheDocument();
    expect(table.getByText('Maret')).toBeInTheDocument();
    expect(table.getByText('Februari')).toBeInTheDocument();
    expect(table.getByText('Ini adalah catatan lapora...')).toBeInTheDocument();
  });

  it('opens CatatanPopupDialogFlow with full note text when clicking truncated note cell', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockResolvedValueOnce({
      data: {
        current_page: 1,
        data: mockLaporans,
        last_page: 1,
        per_page: 10,
        total: 2,
      },
    });

    renderWithRouter(<KelolaLaporan />);
    const truncatedCell = await screen.findByText('Ini adalah catatan lapora...');
    await user.click(truncatedCell);

    expect(await screen.findByText('Catatan Laporan')).toBeInTheDocument();
    expect(
      screen.getByText(
        'Ini adalah catatan laporan yang sangat panjang sekali melebihi dua puluh lima karakter'
      )
    ).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Tutup' }));
    await waitFor(() => {
      expect(screen.queryByText('Catatan Laporan')).not.toBeInTheDocument();
    });
  });

  it('filters reports by jenis_laporan, bulan, and tahun', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockResolvedValue({
      data: {
        current_page: 1,
        data: mockLaporans,
        last_page: 1,
        per_page: 10,
        total: 2,
      },
    });

    const { container } = renderWithRouter(<KelolaLaporan />);
    await screen.findByText('Catatan singkat');

    const jenisSelect = container.querySelector('select[name="jenis_laporan"]') as HTMLSelectElement;
    await user.selectOptions(jenisSelect, 'ampera');

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/list-reports',
        expect.objectContaining({
          params: expect.objectContaining({ jenis_laporan: 'ampera', page: 1 }),
        })
      );
    });

    const bulanSelect = container.querySelector('select[name="periode_bulan"]') as HTMLSelectElement;
    await user.selectOptions(bulanSelect, '3');

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/list-reports',
        expect.objectContaining({
          params: expect.objectContaining({ periode_bulan: 3, page: 1 }),
        })
      );
    });
  });

  it('deletes a report via DeleteReportDialogFlow and surfaces 422 errors if deletion fails', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockResolvedValue({
      data: {
        current_page: 1,
        data: [mockLaporans[0]],
        last_page: 1,
        per_page: 10,
        total: 1,
      },
    });

    renderWithRouter(<KelolaLaporan />);
    await screen.findByText('Catatan singkat');

    await user.click(screen.getByTitle('Hapus Pengguna ini'));
    expect(
      await screen.findByRole('heading', { name: 'Hapus Laporan' })
    ).toBeInTheDocument();

    mockedPostForm.mockRejectedValueOnce(
      createMockAxiosError({
        status: 422,
        data: { message: 'Laporan sedang diproses' },
      })
    );
    await user.click(screen.getByRole('button', { name: 'Hapus Laporan' }));
    expect(await screen.findByText('Laporan sedang diproses')).toBeInTheDocument();

    mockedPostForm.mockResolvedValueOnce({ status: 200, data: { message: 'Deleted' } });
    await user.click(screen.getByRole('button', { name: 'Hapus Laporan' }));

    await waitFor(() => {
      expect(mockedPostForm).toHaveBeenLastCalledWith(
        '/api/delete-report',
        { reportid: 101 },
        { responseType: 'json' }
      );
    });
    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenCalledTimes(2);
    });
  });

  it('supports pagination, page size changes, and error cards', async () => {
    const user = userEvent.setup();
    vi.spyOn(console, 'error').mockImplementation(() => {});

    mockedApiGet.mockResolvedValue({
      data: {
        current_page: 1,
        data: mockLaporans,
        last_page: 2,
        per_page: 10,
        total: 15,
      },
    });

    const { container, unmount } = renderWithRouter(<KelolaLaporan />);
    await screen.findByText('Catatan singkat');

    await user.click(screen.getByRole('button', { name: /selanjutnya/i }));
    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/list-reports',
        expect.objectContaining({ params: expect.objectContaining({ page: 2 }) })
      );
    });

    const perPageSelect = container.querySelector('select[name="jenislaporan"]') as HTMLSelectElement;
    await user.selectOptions(perPageSelect, '50');
    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/list-reports',
        expect.objectContaining({ params: expect.objectContaining({ per_page: 50, page: 1 }) })
      );
    });
    unmount();

    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 403 }));
    const forbidden = renderWithRouter(<KelolaLaporan />);
    expect(await screen.findByText('Tidak diperbolehkan')).toBeInTheDocument();
    forbidden.unmount();

    mockedApiGet
      .mockRejectedValueOnce(new Error('Unexpected failure'))
      .mockResolvedValueOnce({
        data: { current_page: 1, data: [], last_page: 1, per_page: 10, total: 0 },
      });

    renderWithRouter(<KelolaLaporan />);
    expect(await screen.findByText('Kesalahan Tidak Diketahui')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: /coba lagi/i }));
    expect(await screen.findByText('Tidak ada data yang cocok')).toBeInTheDocument();
  });
});
