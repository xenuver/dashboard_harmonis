import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

afterEach(() => {
  cleanup();
  localStorage.clear();
  sessionStorage.clear();
});

class ResizeObserverMock {
  observe() {}
  unobserve() {}
  disconnect() {}
}

if (typeof window.ResizeObserver === 'undefined') {
  window.ResizeObserver = ResizeObserverMock as unknown as typeof ResizeObserver;
}

if (typeof window.matchMedia !== 'function') {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  });
}

if (typeof window.URL.createObjectURL !== 'function') {
  window.URL.createObjectURL = vi.fn(() => 'blob:mock-download-url');
}

if (typeof window.URL.revokeObjectURL !== 'function') {
  window.URL.revokeObjectURL = vi.fn();
}

if (typeof Element !== 'undefined' && typeof Element.prototype.scrollIntoView !== 'function') {
  Element.prototype.scrollIntoView = vi.fn();
}

if (typeof HTMLElement !== 'undefined' && typeof HTMLElement.prototype.hasPointerCapture !== 'function') {
  HTMLElement.prototype.hasPointerCapture = vi.fn(() => false);
}

if (typeof globalThis.DataTransfer === 'undefined') {
  class DataTransferPolyfill {
    private fileList: File[] = [];

    items = {
      add: (file: File) => {
        this.fileList.push(file);
      },
    };

    get files(): FileList {
      const list = [...this.fileList] as unknown as FileList & File[];
      Object.defineProperty(list, 'item', {
        value: (index: number) => this.fileList[index] ?? null,
      });
      return list;
    }
  }

  globalThis.DataTransfer = DataTransferPolyfill as unknown as typeof DataTransfer;

  const originalFilesDescriptor = Object.getOwnPropertyDescriptor(
    HTMLInputElement.prototype,
    'files'
  );
  const customFilesMap = new WeakMap<HTMLInputElement, FileList>();

  Object.defineProperty(HTMLInputElement.prototype, 'files', {
    configurable: true,
    get() {
      if (customFilesMap.has(this)) {
        return customFilesMap.get(this) ?? null;
      }
      return originalFilesDescriptor?.get?.call(this) ?? null;
    },
    set(value: FileList | null) {
      if (value === null || (typeof FileList !== 'undefined' && value instanceof FileList)) {
        customFilesMap.delete(this);
        originalFilesDescriptor?.set?.call(this, value);
      } else {
        customFilesMap.set(this, value);
      }
    },
  });
}

