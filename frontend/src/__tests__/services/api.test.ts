import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { AxiosAdapter, InternalAxiosRequestConfig } from 'axios';
import { api } from '../../services/api';
import sessionManager from '../../services/sessionManager';
import { createMockAxiosError } from '../../test/fixtures';

describe('api client interceptors', () => {
  const originalAdapter = api.defaults.adapter;

  beforeEach(() => {
    sessionManager.deleteTokens();
  });

  afterEach(() => {
    api.defaults.adapter = originalAdapter;
    vi.unstubAllGlobals();
  });

  it('injects Authorization Bearer header when a session token is present', async () => {
    sessionManager.setToken('secret-jwt-token');
    let capturedConfig: InternalAxiosRequestConfig | undefined;

    const mockAdapter: AxiosAdapter = async (config) => {
      capturedConfig = config;
      return {
        data: { ok: true },
        status: 200,
        statusText: 'OK',
        headers: {},
        config,
      };
    };

    api.defaults.adapter = mockAdapter;
    await api.get('/api/dashboard', { responseType: 'json' });

    expect(capturedConfig?.headers.Authorization).toBe('Bearer secret-jwt-token');
    expect(capturedConfig?.transitional?.silentJSONParsing).toBe(false);
  });

  it('omits Authorization header when no session token exists', async () => {
    let capturedConfig: InternalAxiosRequestConfig | undefined;

    const mockAdapter: AxiosAdapter = async (config) => {
      capturedConfig = config;
      return {
        data: { ok: true },
        status: 200,
        statusText: 'OK',
        headers: {},
        config,
      };
    };

    api.defaults.adapter = mockAdapter;
    await api.get('/api/health');

    expect(capturedConfig?.headers.Authorization).toBeUndefined();
  });

  it('deletes tokens and redirects to /login with next URL on 401 outside /login', async () => {
    sessionManager.setToken('expired-token');

    const locationMock = {
      protocol: 'http:',
      hostname: 'localhost',
      port: '3000',
      pathname: '/dashboard',
      search: '?periode_bulan=3',
      href: 'http://localhost:3000/dashboard?periode_bulan=3',
    };
    vi.stubGlobal('location', locationMock);

    api.defaults.adapter = async () => {
      throw createMockAxiosError({ status: 401 });
    };

    await expect(api.get('/api/dashboard')).rejects.toBeDefined();

    expect(sessionManager.getToken()).toBeNull();
    expect(locationMock.href).toBe(
      '/login?reason=invalid_session&next=%2Fdashboard%3Fperiode_bulan%3D3'
    );
  });

  it('does not redirect on 401 when already on /login', async () => {
    sessionManager.setToken('existing-token');

    const locationMock = {
      protocol: 'http:',
      hostname: 'localhost',
      port: '3000',
      pathname: '/login',
      search: '',
      href: 'http://localhost:3000/login',
    };
    vi.stubGlobal('location', locationMock);

    api.defaults.adapter = async () => {
      throw createMockAxiosError({ status: 401 });
    };

    await expect(api.postForm('/api/login', {})).rejects.toBeDefined();

    expect(sessionManager.getToken()).toBe('existing-token');
    expect(locationMock.href).toBe('http://localhost:3000/login');
  });
});
