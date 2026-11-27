import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Route, Routes } from 'react-router-dom';
import LoginScreen from '../../view/Login';
import { api } from '../../services/api';
import sessionManager from '../../services/sessionManager';
import { createMockAxiosError, mockItStaffUser } from '../../test/fixtures';
import { renderWithRouter } from '../../test/renderWithRouter';

vi.mock('../../services/api', () => ({
  api: {
    postForm: vi.fn(),
    get: vi.fn(),
    post: vi.fn(),
  },
}));

describe('LoginScreen', () => {
  const mockedPostForm = vi.mocked(api.postForm);

  beforeEach(() => {
    sessionManager.deleteTokens();
    mockedPostForm.mockReset();
    window.history.replaceState({}, '', '/login');
  });

  function renderLogin(initialEntry = '/login') {
    return renderWithRouter(
      <Routes>
        <Route path="/login" element={<LoginScreen />} />
        <Route path="/dashboard" element={<div>Dashboard Route</div>} />
        <Route path="/produk-penjualan" element={<div>Produk Route</div>} />
      </Routes>,
      { initialEntries: [initialEntry] }
    );
  }

  it('displays invalid session message when reason=invalid_session is in URL params', () => {
    window.history.replaceState({}, '', '/login?reason=invalid_session');
    renderLogin('/login?reason=invalid_session');

    expect(screen.getByText('Sesi sudah kedaluwarsa atau tidak valid')).toBeInTheDocument();
  });

  it('validates empty or whitespace-only username and empty password client-side', async () => {
    const user = userEvent.setup();
    const { container } = renderLogin();

    const usernameInput = container.querySelector('input[name="username"]') as HTMLInputElement;
    const passwordInput = container.querySelector('input[name="password"]') as HTMLInputElement;

    await user.type(usernameInput, '   ');
    await user.type(passwordInput, 'secret');
    await user.click(screen.getByRole('button', { name: /masuk/i }));

    expect(screen.getByText('Username tidak boleh kosong')).toBeInTheDocument();
    expect(mockedPostForm).not.toHaveBeenCalled();

    await user.clear(usernameInput);
    await user.type(usernameInput, 'admin_it');
    await user.clear(passwordInput);
    fireEvent.submit(container.querySelector('form')!);

    expect(screen.getByText('Password tidak boleh kosong')).toBeInTheDocument();
    expect(mockedPostForm).not.toHaveBeenCalled();
  });

  it('logs in successfully, stores token and identity, and redirects to /dashboard by default', async () => {
    const user = userEvent.setup();
    mockedPostForm.mockResolvedValueOnce({
      status: 200,
      data: {
        token: 'fresh-token',
        user: mockItStaffUser,
      },
    });

    const { container } = renderLogin();
    const usernameInput = container.querySelector('input[name="username"]') as HTMLInputElement;
    const passwordInput = container.querySelector('input[name="password"]') as HTMLInputElement;

    await user.type(usernameInput, '  admin_it  ');
    await user.type(passwordInput, 'password123');
    await user.click(screen.getByRole('button', { name: /masuk/i }));

    await waitFor(() => {
      expect(screen.getByText('Dashboard Route')).toBeInTheDocument();
    });

    expect(mockedPostForm).toHaveBeenCalledWith(
      '/api/login',
      { username: 'admin_it', password: 'password123' },
      { responseType: 'json' }
    );
    expect(sessionManager.getToken()).toBe('fresh-token');
    expect(sessionManager.getIdentity()).toEqual(mockItStaffUser);
  });

  it('redirects to safe relative next URL after login and blocks unsafe open redirects', async () => {
    const user = userEvent.setup();
    mockedPostForm.mockResolvedValue({
      status: 200,
      data: {
        token: 'fresh-token',
        user: mockItStaffUser,
      },
    });

    window.history.replaceState({}, '', '/login?next=%2Fproduk-penjualan');
    const firstRender = renderLogin('/login?next=%2Fproduk-penjualan');

    await user.type(
      firstRender.container.querySelector('input[name="username"]') as HTMLInputElement,
      'admin_it'
    );
    await user.type(
      firstRender.container.querySelector('input[name="password"]') as HTMLInputElement,
      'secret'
    );
    await user.click(screen.getByRole('button', { name: /masuk/i }));

    await waitFor(() => {
      expect(screen.getByText('Produk Route')).toBeInTheDocument();
    });

    firstRender.unmount();

    window.history.replaceState({}, '', '/login?next=//evil.example.com');
    const secondRender = renderLogin('/login?next=//evil.example.com');

    await user.type(
      secondRender.container.querySelector('input[name="username"]') as HTMLInputElement,
      'admin_it'
    );
    await user.type(
      secondRender.container.querySelector('input[name="password"]') as HTMLInputElement,
      'secret'
    );
    await user.click(screen.getByRole('button', { name: /masuk/i }));

    await waitFor(() => {
      expect(screen.getByText('Dashboard Route')).toBeInTheDocument();
    });
  });

  it.each([
    {
      label: '401 invalid credentials',
      error: createMockAxiosError({ status: 401 }),
      expected: 'username atau password salah',
    },
    {
      label: '429 rate limited',
      error: createMockAxiosError({ status: 429 }),
      expected:
        'anda membuat permintaan login terlalu cepat. mohon coba login lagi setelah beberapa saat',
    },
    {
      label: 'network error',
      error: createMockAxiosError({ code: 'ERR_NETWORK' }),
      expected: 'Terjadi kesalahan dalam mendapatkan respon dari server',
    },
    {
      label: 'bad response',
      error: createMockAxiosError({ code: 'ERR_BAD_RESPONSE' }),
      expected: 'Server tidak merespon dengan format data yang tepat',
    },
    {
      label: 'timeout/aborted',
      error: createMockAxiosError({ code: 'ECONNABORTED' }),
      expected: 'Permintaan dibatalkan atau waktu habis',
    },
  ])('shows user-friendly message on $label', async ({ error, expected }) => {
    const user = userEvent.setup();
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    mockedPostForm.mockRejectedValueOnce(error);

    const { container } = renderLogin();
    await user.type(
      container.querySelector('input[name="username"]') as HTMLInputElement,
      'admin_it'
    );
    await user.type(
      container.querySelector('input[name="password"]') as HTMLInputElement,
      'wrong'
    );
    await user.click(screen.getByRole('button', { name: /masuk/i }));

    expect(await screen.findByText(expected)).toBeInTheDocument();
  });
});
