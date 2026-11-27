import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import Upload from '../../view/Upload';
import { api } from '../../services/api';
import { createMockAxiosError } from '../../test/fixtures';
import { renderWithRouter } from '../../test/renderWithRouter';

vi.mock('../../services/api', () => ({
  api: {
    postForm: vi.fn(),
  },
}));

function createXlsxFile(name = 'laporan-maret.xlsx') {
  return new File(['mock-xlsx-binary'], name, {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  });
}

function dropFilesOnDropzone(container: HTMLElement, files: File[]) {
  const dropzoneRoot = container.querySelector('input[type="file"]')!.parentElement!;
  fireEvent.drop(dropzoneRoot, {
    dataTransfer: {
      files,
      items: files.map((file) => ({
        kind: 'file',
        type: file.type,
        getAsFile: () => file,
      })),
      types: ['Files'],
    },
  });
}

describe('Upload view & ExcelDropZone', () => {
  const mockedPostForm = vi.mocked(api.postForm);

  beforeEach(() => {
    mockedPostForm.mockReset();
  });

  it('pre-populates report selectors from URL query parameters', () => {
    const { container } = renderWithRouter(<Upload />, {
      initialEntries: ['/upload?jenis_laporan=ampera&periode_bulan=5&periode_tahun=2025'],
    });

    const jenisSelect = container.querySelector('select[name="jenis_laporan"]') as HTMLSelectElement;
    const bulanSelect = container.querySelector('select[name="periode_bulan"]') as HTMLSelectElement;
    const tahunSelect = container.querySelector('select[name="periode_tahun"]') as HTMLSelectElement;

    expect(jenisSelect.value).toBe('ampera');
    expect(bulanSelect.value).toBe('5');
    expect(tahunSelect.value).toBe('2025');
  });

  it('rejects submitting without a file and rejects non-xlsx files in Dropzone', async () => {
    const { container } = renderWithRouter(<Upload />);

    fireEvent.submit(container.querySelector('form')!);
    expect(
      await screen.findByText('File laporan harus berbentuk file .xlsx')
    ).toBeInTheDocument();

    const badFile = new File(['hello'], 'notes.txt', { type: 'text/plain' });
    dropFilesOnDropzone(container, [badFile]);

    expect(
      await screen.findByText('Anda hanya dapat mengunggah file .xlsx (file dipilih: notes.txt)')
    ).toBeInTheDocument();
    expect(mockedPostForm).not.toHaveBeenCalled();
  });

  it('uploads a valid .xlsx file successfully and clears the attached file', async () => {
    mockedPostForm.mockResolvedValueOnce({ status: 200, data: { message: 'OK' } });

    const { container } = renderWithRouter(<Upload />, {
      initialEntries: ['/upload?jenis_laporan=pal&periode_bulan=3&periode_tahun=2026'],
    });

    const validFile = createXlsxFile('laporan-pal.xlsx');
    dropFilesOnDropzone(container, [validFile]);

    expect(await screen.findByText('Selected: laporan-pal.xlsx')).toBeInTheDocument();

    fireEvent.submit(container.querySelector('form')!);

    await waitFor(() => {
      expect(screen.getByText('Upload berhasil')).toBeInTheDocument();
    });

    expect(mockedPostForm).toHaveBeenCalledWith(
      '/api/upload',
      {
        jenis_laporan: 'pal',
        periode_bulan: '3',
        periode_tahun: '2026',
        file: validFile,
      },
      { responseType: 'json' }
    );
    expect(screen.queryByText('Selected: laporan-pal.xlsx')).not.toBeInTheDocument();
  });

  it('displays collapsible per-sheet validation errors on 422 xlsxValidationErrors response', async () => {
    const user = userEvent.setup();
    mockedPostForm.mockRejectedValueOnce(
      createMockAxiosError({
        status: 422,
        data: {
          message: 'Validation failed',
          xlsxValidationErrors: {
            'Penjualan Harian': [
              { location: 'Baris 4 Kolom C', message: 'Nilai total harus berupa angka' },
            ],
            Info: [{ location: 'Cell B2', message: 'Tanggal periode tidak cocok' }],
          },
        },
      })
    );

    const { container } = renderWithRouter(<Upload />);
    dropFilesOnDropzone(container, [createXlsxFile()]);
    expect(await screen.findByText('Selected: laporan-maret.xlsx')).toBeInTheDocument();

    fireEvent.submit(container.querySelector('form')!);

    expect(
      await screen.findByText('Terjadi kesalahan dalam validasi data')
    ).toBeInTheDocument();
    expect(screen.getByText('Penjualan Harian')).toBeInTheDocument();
    expect(screen.getByText('Info')).toBeInTheDocument();

    await user.click(screen.getByText('Penjualan Harian'));
    expect(await screen.findByText('Baris 4 Kolom C')).toBeInTheDocument();
    expect(screen.getByText('Nilai total harus berupa angka')).toBeInTheDocument();
  });

  it.each([
    {
      label: '409 duplicate period',
      error: createMockAxiosError({ status: 409 }),
      expected: 'Data untuk periode ini sudah ada',
    },
    {
      label: '413 payload too large',
      error: createMockAxiosError({ status: 413 }),
      expected: 'File/permintaan yang diunggah terlalu besar',
    },
    {
      label: '403 forbidden',
      error: createMockAxiosError({ status: 403 }),
      expected: 'Anda tidak diperbolehkan untuk mengunggah data',
    },
    {
      label: '422 generic parse error',
      error: createMockAxiosError({ status: 422, data: {} }),
      expected:
        'Terjadi kesalahan dalam membaca data. Pastikan laporan merupakan file .xlsx yang valid',
    },
    {
      label: 'network error',
      error: createMockAxiosError({ code: 'ERR_NETWORK' }),
      expected: 'Terjadi kesalahan dalam mengirmkan data ke server',
    },
    {
      label: 'bad response',
      error: createMockAxiosError({ code: 'ERR_BAD_RESPONSE' }),
      expected: 'Terjadi kesalahan dalam membaca respon dari server',
    },
    {
      label: 'timeout',
      error: createMockAxiosError({ code: 'ECONNABORTED' }),
      expected: 'Permintaan dibatalkan atau waktu tunggu habis',
    },
  ])('handles $label upload error cleanly', async ({ error, expected }) => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    mockedPostForm.mockRejectedValueOnce(error);

    const { container } = renderWithRouter(<Upload />);
    dropFilesOnDropzone(container, [createXlsxFile()]);
    expect(await screen.findByText('Selected: laporan-maret.xlsx')).toBeInTheDocument();

    fireEvent.submit(container.querySelector('form')!);

    expect(await screen.findByText(expected)).toBeInTheDocument();
  });

  it('resets attached file and status message when Reset Form is clicked', async () => {
    const user = userEvent.setup();
    mockedPostForm.mockRejectedValueOnce(createMockAxiosError({ status: 409 }));

    const { container } = renderWithRouter(<Upload />);
    dropFilesOnDropzone(container, [createXlsxFile('reset-me.xlsx')]);
    expect(await screen.findByText('Selected: reset-me.xlsx')).toBeInTheDocument();

    fireEvent.submit(container.querySelector('form')!);
    expect(await screen.findByText('Data untuk periode ini sudah ada')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Reset Form' }));

    await waitFor(() => {
      expect(screen.queryByText('Selected: reset-me.xlsx')).not.toBeInTheDocument();
      expect(screen.queryByText('Data untuk periode ini sudah ada')).not.toBeInTheDocument();
    });
  });

  it('accepts only the first file when more than one .xlsx file is dropped onto ExcelDropZone', async () => {
    const { container } = renderWithRouter(<Upload />);

    dropFilesOnDropzone(container, [
      createXlsxFile('satu.xlsx'),
      createXlsxFile('dua.xlsx'),
    ]);

    expect(await screen.findByText('Selected: satu.xlsx')).toBeInTheDocument();
    expect(screen.queryByText('Selected: dua.xlsx')).not.toBeInTheDocument();
  });
});
