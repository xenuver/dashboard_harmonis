import { beforeEach, describe, expect, it } from 'vitest';
import { Route, Routes } from 'react-router-dom';
import { screen } from '@testing-library/react';
import AutoRedirectIfSignedIn from '../../components/routes/auth/AutoRedirectIfSignedIn';
import AutoRedirectIfSignedOut from '../../components/routes/auth/AutoRedirectIfSignedOut';
import RedirectToLoginOrDashboard from '../../components/routes/auth/RedirectToLoginOrDashboard';
import sessionManager from '../../services/sessionManager';
import { renderWithRouter } from '../../test/renderWithRouter';

describe('Auth route guards', () => {
  beforeEach(() => {
    sessionManager.deleteTokens();
  });

  describe('AutoRedirectIfSignedIn', () => {
    it('renders child route when user is signed out', () => {
      renderWithRouter(
        <Routes>
          <Route element={<AutoRedirectIfSignedIn />}>
            <Route path="/login" element={<div>Login Page Content</div>} />
          </Route>
          <Route path="/dashboard" element={<div>Dashboard Page Content</div>} />
        </Routes>,
        { initialEntries: ['/login'] }
      );

      expect(screen.getByText('Login Page Content')).toBeInTheDocument();
    });

    it('redirects to /dashboard when user has a token', () => {
      sessionManager.setToken('valid-token');

      renderWithRouter(
        <Routes>
          <Route element={<AutoRedirectIfSignedIn />}>
            <Route path="/login" element={<div>Login Page Content</div>} />
          </Route>
          <Route path="/dashboard" element={<div>Dashboard Page Content</div>} />
        </Routes>,
        { initialEntries: ['/login'] }
      );

      expect(screen.getByText('Dashboard Page Content')).toBeInTheDocument();
      expect(screen.queryByText('Login Page Content')).not.toBeInTheDocument();
    });
  });

  describe('AutoRedirectIfSignedOut', () => {
    it('redirects to /login when user has no token', () => {
      renderWithRouter(
        <Routes>
          <Route element={<AutoRedirectIfSignedOut />}>
            <Route path="/dashboard" element={<div>Protected Dashboard</div>} />
          </Route>
          <Route path="/login" element={<div>Login Page Content</div>} />
        </Routes>,
        { initialEntries: ['/dashboard'] }
      );

      expect(screen.getByText('Login Page Content')).toBeInTheDocument();
      expect(screen.queryByText('Protected Dashboard')).not.toBeInTheDocument();
    });

    it('renders protected route when user is signed in', () => {
      sessionManager.setToken('valid-token');

      renderWithRouter(
        <Routes>
          <Route element={<AutoRedirectIfSignedOut />}>
            <Route path="/dashboard" element={<div>Protected Dashboard</div>} />
          </Route>
          <Route path="/login" element={<div>Login Page Content</div>} />
        </Routes>,
        { initialEntries: ['/dashboard'] }
      );

      expect(screen.getByText('Protected Dashboard')).toBeInTheDocument();
    });
  });

  describe('RedirectToLoginOrDashboard', () => {
    it('redirects root path to /login when signed out', () => {
      renderWithRouter(
        <Routes>
          <Route path="/" element={<RedirectToLoginOrDashboard />} />
          <Route path="/login" element={<div>Login Page Content</div>} />
          <Route path="/dashboard" element={<div>Dashboard Page Content</div>} />
        </Routes>,
        { initialEntries: ['/'] }
      );

      expect(screen.getByText('Login Page Content')).toBeInTheDocument();
    });

    it('redirects root path to /dashboard when signed in', () => {
      sessionManager.setToken('valid-token');

      renderWithRouter(
        <Routes>
          <Route path="/" element={<RedirectToLoginOrDashboard />} />
          <Route path="/login" element={<div>Login Page Content</div>} />
          <Route path="/dashboard" element={<div>Dashboard Page Content</div>} />
        </Routes>,
        { initialEntries: ['/'] }
      );

      expect(screen.getByText('Dashboard Page Content')).toBeInTheDocument();
    });
  });
});
