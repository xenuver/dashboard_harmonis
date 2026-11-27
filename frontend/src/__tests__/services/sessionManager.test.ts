import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mockItStaffUser, mockOwnerUser } from '../../test/fixtures';

const mockApiGet = vi.fn();
const mockApiPost = vi.fn();

vi.mock('../../services/api', () => ({
  api: {
    get: mockApiGet,
    post: mockApiPost,
  },
}));

describe('sessionManager', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.resetModules();
    mockApiGet.mockReset();
    mockApiPost.mockReset();
  });

  it('loads pre-existing token and identity from localStorage on module initialization', async () => {
    localStorage.setItem('session_token', 'persisted-token');
    localStorage.setItem('session_identity', JSON.stringify(mockItStaffUser));

    const { default: sessionManager } = await import('../../services/sessionManager');

    expect(sessionManager.getToken()).toBe('persisted-token');
    expect(sessionManager.getIdentity()).toEqual(mockItStaffUser);
  });

  it('sets, reads, and deletes token and identity in memory and localStorage', async () => {
    const { default: sessionManager } = await import('../../services/sessionManager');

    expect(sessionManager.getToken()).toBeNull();
    expect(sessionManager.getIdentity()).toBeNull();

    sessionManager.setToken('abc-123');
    sessionManager.setIdentity(mockOwnerUser);

    expect(sessionManager.getToken()).toBe('abc-123');
    expect(localStorage.getItem('session_token')).toBe('abc-123');
    expect(sessionManager.getIdentity()).toEqual(mockOwnerUser);
    expect(localStorage.getItem('session_identity')).toBe(JSON.stringify(mockOwnerUser));

    sessionManager.deleteTokens();

    expect(sessionManager.getToken()).toBeNull();
    expect(sessionManager.getIdentity()).toBeNull();
    expect(localStorage.getItem('session_token')).toBeNull();
    expect(localStorage.getItem('session_identity')).toBeNull();
  });

  it('returns null and logs an error when cached identity JSON is malformed', async () => {
    localStorage.setItem('session_identity', '{broken-json');
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});

    const { default: sessionManager } = await import('../../services/sessionManager');

    expect(sessionManager.getIdentity()).toBeNull();
    expect(errorSpy).toHaveBeenCalled();
    expect(warnSpy).toHaveBeenCalledWith('Failed to parse JSON');
  });

  it('synchronizes token and identity when window storage events fire', async () => {
    const { default: sessionManager } = await import('../../services/sessionManager');

    window.dispatchEvent(
      new StorageEvent('storage', {
        key: 'session_token',
        newValue: 'cross-tab-token',
      })
    );
    window.dispatchEvent(
      new StorageEvent('storage', {
        key: 'session_identity',
        newValue: JSON.stringify(mockItStaffUser),
      })
    );

    expect(sessionManager.getToken()).toBe('cross-tab-token');
    expect(sessionManager.getIdentity()).toEqual(mockItStaffUser);
  });

  it('does not call /api/whoami in updateIdentity when there is no active token', async () => {
    const { default: sessionManager } = await import('../../services/sessionManager');

    await sessionManager.updateIdentity();

    expect(mockApiGet).not.toHaveBeenCalled();
  });

  it('fetches /api/whoami and updates identity only when data differs from cached identity', async () => {
    const { default: sessionManager } = await import('../../services/sessionManager');
    sessionManager.setToken('valid-token');
    sessionManager.setIdentity(mockOwnerUser);

    mockApiGet.mockResolvedValueOnce({ data: { ...mockOwnerUser } });
    await sessionManager.updateIdentity();

    expect(mockApiGet).toHaveBeenCalledWith('/api/whoami', { responseType: 'json' });
    expect(sessionManager.getIdentity()).toEqual(mockOwnerUser);

    mockApiGet.mockResolvedValueOnce({ data: mockItStaffUser });
    await sessionManager.updateIdentity();

    expect(sessionManager.getIdentity()).toEqual(mockItStaffUser);
  });

  it('handles errors in updateIdentity and revokeCurrentToken gracefully', async () => {
    const { default: sessionManager } = await import('../../services/sessionManager');
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});

    sessionManager.setToken('valid-token');
    mockApiGet.mockRejectedValueOnce(new Error('Network error'));
    await sessionManager.updateIdentity();

    expect(warnSpy).toHaveBeenCalledWith('Terjadi kesalahan dalam memperbarui profil');

    mockApiPost.mockRejectedValueOnce(new Error('Logout failed'));
    await sessionManager.revokeCurrentToken();

    expect(mockApiPost).toHaveBeenCalledWith('/api/logout');
    expect(warnSpy).toHaveBeenCalledWith('Gagal revoke token');
    expect(errorSpy).toHaveBeenCalledTimes(2);
  });

  it('calls /api/logout on revokeCurrentToken', async () => {
    const { default: sessionManager } = await import('../../services/sessionManager');
    mockApiPost.mockResolvedValueOnce({ status: 200 });

    await sessionManager.revokeCurrentToken();

    expect(mockApiPost).toHaveBeenCalledWith('/api/logout');
  });
});
