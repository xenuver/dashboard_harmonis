import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Route, Routes } from 'react-router-dom';
import { AppSidebar } from '../../components/AppSidebar';
import { TopbarLogin } from '../../components/TopbarLogin';
import { SidebarProvider } from '../../components/ui/sidebar';
import sessionManager from '../../services/sessionManager';
import { mockItStaffUser, mockOwnerUser } from '../../test/fixtures';
import { renderWithRouter } from '../../test/renderWithRouter';

vi.mock('../../services/api', () => ({
  api: {
    post: vi.fn(),
    get: vi.fn(),
  },
}));

describe('Navigation shell (AppSidebar & TopbarLogin)', () => {
  beforeEach(() => {
    sessionManager.deleteTokens();
  });

  it('shows all navigation links including upload, kelola laporan, and kelola pengguna for it_staff', () => {
    sessionManager.setToken('token-it');
    sessionManager.setIdentity(mockItStaffUser);

    renderWithRouter(
      <SidebarProvider>
        <AppSidebar />
      </SidebarProvider>,
      { initialEntries: ['/dashboard'] }
    );

    expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Peringkat Produk' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Peringkat Supplier' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Upload Laporan' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Kelola Laporan' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ekspor Laporan' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Kelola Pengguna' })).toBeInTheDocument();
  });

  it('hides staff-only navigation items for owner role', () => {
    sessionManager.setToken('token-owner');
    sessionManager.setIdentity(mockOwnerUser);

    renderWithRouter(
      <SidebarProvider>
        <AppSidebar />
      </SidebarProvider>,
      { initialEntries: ['/dashboard'] }
    );

    expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Peringkat Produk' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Peringkat Supplier' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ekspor Laporan' })).toBeInTheDocument();

    expect(screen.queryByRole('link', { name: 'Upload Laporan' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Kelola Laporan' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Kelola Pengguna' })).not.toBeInTheDocument();
  });

  it('displays user avatar initial, username in menu, and logs out to /login', async () => {
    const user = userEvent.setup();
    sessionManager.setToken('token-it');
    sessionManager.setIdentity(mockItStaffUser);

    const revokeSpy = vi.spyOn(sessionManager, 'revokeCurrentToken').mockResolvedValue();
    const deleteSpy = vi.spyOn(sessionManager, 'deleteTokens');

    renderWithRouter(
      <Routes>
        <Route
          path="/dashboard"
          element={
            <SidebarProvider>
              <TopbarLogin titleBar="Dashboard Utama" />
            </SidebarProvider>
          }
        />
        <Route path="/login" element={<div>Login Page</div>} />
      </Routes>,
      { initialEntries: ['/dashboard'] }
    );

    expect(screen.getByText('Dashboard Utama')).toBeInTheDocument();
    expect(screen.getByText('A')).toBeInTheDocument();

    const triggerButton = screen.getByText('A').closest('button')!;
    await user.click(triggerButton);

    expect(await screen.findByText('Masuk sebagai admin_it')).toBeInTheDocument();

    await user.click(screen.getByText('Keluar'));

    await waitFor(() => {
      expect(screen.getByText('Login Page')).toBeInTheDocument();
    });
    expect(revokeSpy).toHaveBeenCalledTimes(1);
    expect(deleteSpy).toHaveBeenCalledTimes(1);
  });
});
