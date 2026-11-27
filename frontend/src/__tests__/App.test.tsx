import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import App from '../App';
import { api } from '../services/api';
import sessionManager from '../services/sessionManager';
import { mockItStaffUser } from '../test/fixtures';
import { renderWithRouter } from '../test/renderWithRouter';

vi.mock('../services/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    postForm: vi.fn(),
  },
}));

describe('App routing and layout shells', () => {
  const mockedApiGet = vi.mocked(api.get);

  beforeEach(() => {
    sessionManager.deleteTokens();
    mockedApiGet.mockReset();
    window.history.replaceState({}, '', '/');
  });

  it('redirects unauthenticated root path to /login inside LoggedOutLayout', async () => {
    renderWithRouter(<App />, { initialEntries: ['/'] });

    expect(
      await screen.findByText('Masuk dengan nama pengguna anda untuk mengakses aplikasi ini')
    ).toBeInTheDocument();
  });

  it('renders 404 PageNotFound inside LoggedOutLayout for unknown routes', async () => {
    renderWithRouter(<App />, { initialEntries: ['/halaman-tidak-ada'] });

    expect(await screen.findByText('404')).toBeInTheDocument();
    expect(screen.getByText('Halaman Tidak Ditemukan')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Kembali ke Halaman Utama' })).toHaveAttribute(
      'href',
      '/'
    );
  });

  it('renders authenticated routes inside LoggedInLayout, refreshes identity, and sets topbar title', async () => {
    sessionManager.setToken('active-token');
    sessionManager.setIdentity(mockItStaffUser);
    mockedApiGet.mockResolvedValue({ data: mockItStaffUser });

    const eksporRender = renderWithRouter(<App />, { initialEntries: ['/ekspor'] });

    const eksporHeadings = await screen.findAllByRole('heading', {
      level: 1,
      name: 'Ekspor Laporan',
    });
    expect(eksporHeadings).toHaveLength(2);
    expect(screen.getByRole('button', { name: /unduh \.xlsx/i })).toBeInTheDocument();

    await waitFor(() => {
      expect(mockedApiGet).toHaveBeenCalledWith('/api/whoami', { responseType: 'json' });
    });

    eksporRender.unmount();

    mockedApiGet.mockResolvedValue({
      data: {
        current_page: 1,
        data: [],
        last_page: 1,
        per_page: 10,
        total: 0,
      },
    });

    renderWithRouter(<App />, { initialEntries: ['/kelola-laporan'] });

    const kelolaHeadings = await screen.findAllByRole('heading', {
      level: 1,
      name: 'Kelola Laporan',
    });
    expect(kelolaHeadings).toHaveLength(2);
  });
});
