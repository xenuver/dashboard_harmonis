import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import KelolaPengguna from '../../view/KelolaPengguna';
import { api } from '../../services/api';
import sessionManager from '../../services/sessionManager';
import {
  createMockAxiosError,
  mockItStaffUser,
  mockOwnerUser,
} from '../../test/fixtures';
import { renderWithRouter } from '../../test/renderWithRouter';

vi.mock('../../services/api', () => ({
  api: {
    get: vi.fn(),
    postForm: vi.fn(),
  },
}));

describe('KelolaPengguna view & user management dialogs', () => {
  const mockedApiGet = vi.mocked(api.get);
  const mockedPostForm = vi.mocked(api.postForm);

  beforeEach(() => {
    sessionManager.deleteTokens();
    sessionManager.setIdentity(mockItStaffUser);
    mockedApiGet.mockReset();
    mockedPostForm.mockReset();
  });

  it('renders users, maps role labels, and hides delete button for the signed-in user', async () => {
    mockedApiGet.mockResolvedValueOnce({
      data: {
        current_page: 1,
        data: [
          mockItStaffUser,
          { ...mockOwnerUser, enabled: false },
        ],
        last_page: 1,
        per_page: 10,
        total: 2,
      },
    });

    renderWithRouter(<KelolaPengguna />);

    const table = within(screen.getByRole('table'));
    expect(await table.findByText('admin_it')).toBeInTheDocument();
    expect(table.getByText('owner_toko')).toBeInTheDocument();
    expect(table.getByText('Staf IT')).toBeInTheDocument();
    expect(table.getByText('Owner')).toBeInTheDocument();
    expect(table.getByText('Aktif')).toBeInTheDocument();
    expect(table.getByText('Nonaktif')).toBeInTheDocument();

    expect(screen.getByTitle('Ubah Pengguna admin_it')).toBeInTheDocument();
    expect(screen.getByTitle('Ubah Pengguna owner_toko')).toBeInTheDocument();
    expect(screen.queryByTitle('Hapus Pengguna admin_it')).not.toBeInTheDocument();
    expect(screen.getByTitle('Hapus Pengguna owner_toko')).toBeInTheDocument();
  });

  it('filters users by username and role', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockResolvedValue({
      data: {
        current_page: 1,
        data: [mockOwnerUser],
        last_page: 1,
        per_page: 10,
        total: 1,
      },
    });

    const { container } = renderWithRouter(<KelolaPengguna />);
    await screen.findByText('owner_toko');

    const searchInput = container.querySelector('input[name="caripengguna"]') as HTMLInputElement;
    await user.type(searchInput, 'owner');
    await user.click(screen.getByRole('button', { name: /cari/i }));

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/list-users',
        expect.objectContaining({
          params: expect.objectContaining({ usernamelike: 'owner', page: 1 }),
        })
      );
    });

    const roleSelect = container.querySelector('select[name="role"]') as HTMLSelectElement;
    await user.selectOptions(roleSelect, 'owner');

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/list-users',
        expect.objectContaining({
          params: expect.objectContaining({ role: 'owner', page: 1 }),
        })
      );
    });
  });

  it('creates a new user via AddNewUserDialogFlow and handles 422 validation errors', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockResolvedValue({
      data: {
        current_page: 1,
        data: [mockItStaffUser],
        last_page: 1,
        per_page: 10,
        total: 1,
      },
    });

    renderWithRouter(<KelolaPengguna />);
    await screen.findByText('admin_it');

    await user.click(screen.getByTitle('Tambah Pengguna Baru'));
    expect(await screen.findByRole('heading', { name: 'Tambahkan Pengguna Baru' })).toBeInTheDocument();

    mockedPostForm.mockRejectedValueOnce(
      createMockAxiosError({
        status: 422,
        data: {
          message: 'Validation error',
          errors: { username: ['Username sudah digunakan'] },
        },
      })
    );

    await user.type(screen.getByLabelText('Username'), 'new_staff');
    await user.type(screen.getByLabelText('Password'), 'pass123');
    await user.click(screen.getByRole('button', { name: 'Tambahkan Pengguna' }));

    expect(await screen.findByText('Username sudah digunakan')).toBeInTheDocument();

    mockedPostForm.mockResolvedValueOnce({ status: 200, data: { message: 'Created' } });
    await user.click(screen.getByRole('button', { name: 'Tambahkan Pengguna' }));

    await waitFor(() => {
      expect(mockedPostForm).toHaveBeenLastCalledWith(
        '/api/add-user',
        {
          username: 'new_staff',
          password: 'pass123',
          role: 'owner',
          enabled: 1,
        },
        { responseType: 'json' }
      );
    });

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenCalledTimes(2);
    });
  });

  it('edits only changed fields in EditUserDialogFlow and skips API call when unchanged', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockResolvedValue({
      data: {
        current_page: 1,
        data: [mockOwnerUser],
        last_page: 1,
        per_page: 10,
        total: 1,
      },
    });

    renderWithRouter(<KelolaPengguna />);
    await screen.findByText('owner_toko');

    await user.click(screen.getByTitle('Ubah Pengguna owner_toko'));
    expect(await screen.findByText('Ubah Informasi Pengguna')).toBeInTheDocument();

    // Submit with no changes -> closes without calling /api/update-user
    await user.click(screen.getByRole('button', { name: 'Ubah Pengguna' }));
    await waitFor(() => {
      expect(screen.queryByText('Ubah Informasi Pengguna')).not.toBeInTheDocument();
    });
    expect(mockedPostForm).not.toHaveBeenCalled();

    // Re-open and change username
    await user.click(screen.getByTitle('Ubah Pengguna owner_toko'));
    const usernameInput = await screen.findByLabelText('Username');
    await user.clear(usernameInput);
    await user.type(usernameInput, 'owner_baru');

    mockedPostForm.mockResolvedValueOnce({ status: 200, data: { message: 'Updated' } });
    await user.click(screen.getByRole('button', { name: 'Ubah Pengguna' }));

    await waitFor(() => {
      expect(mockedPostForm).toHaveBeenCalledWith(
        '/api/update-user',
        {
          username: 'owner_baru',
          userid: 2,
        },
        { responseType: 'json' }
      );
    });
  });

  it('deletes a user via DeleteUserDialogFlow and refreshes list', async () => {
    const user = userEvent.setup();
    mockedApiGet.mockResolvedValue({
      data: {
        current_page: 1,
        data: [mockItStaffUser, mockOwnerUser],
        last_page: 1,
        per_page: 10,
        total: 2,
      },
    });
    mockedPostForm.mockResolvedValueOnce({ status: 200, data: { message: 'Deleted' } });

    renderWithRouter(<KelolaPengguna />);
    await screen.findByText('owner_toko');

    await user.click(screen.getByTitle('Hapus Pengguna owner_toko'));
    expect(
      await screen.findByText(/Anda akan menghapus pengguna dengan nama pengguna 'owner_toko'/i)
    ).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Hapus Pengguna' }));

    await waitFor(() => {
      expect(mockedPostForm).toHaveBeenCalledWith(
        '/api/delete-user',
        { userid: 2 },
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
        data: [mockItStaffUser],
        last_page: 2,
        per_page: 10,
        total: 15,
      },
    });

    const { container, unmount } = renderWithRouter(<KelolaPengguna />);
    await screen.findByText('admin_it');

    await user.click(screen.getByRole('button', { name: /selanjutnya/i }));
    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/list-users',
        expect.objectContaining({ params: expect.objectContaining({ page: 2 }) })
      );
    });

    const perPageSelect = container.querySelector('select[name="jenislaporan"]') as HTMLSelectElement;
    await user.selectOptions(perPageSelect, '20');
    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenLastCalledWith(
        '/api/list-users',
        expect.objectContaining({ params: expect.objectContaining({ per_page: 20, page: 1 }) })
      );
    });
    unmount();

    mockedApiGet.mockRejectedValueOnce(createMockAxiosError({ status: 403 }));
    const forbidden = renderWithRouter(<KelolaPengguna />);
    expect(await screen.findByText('Tidak diperbolehkan')).toBeInTheDocument();
    forbidden.unmount();

    mockedApiGet
      .mockRejectedValueOnce(createMockAxiosError({ code: 'ERR_NETWORK' }))
      .mockResolvedValueOnce({
        data: { current_page: 1, data: [], last_page: 1, per_page: 10, total: 0 },
      });

    renderWithRouter(<KelolaPengguna />);
    expect(await screen.findByText('Kesalahan Koneksi')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: /coba lagi/i }));
    expect(await screen.findByText('Tidak ada data yang cocok')).toBeInTheDocument();
  });
});
