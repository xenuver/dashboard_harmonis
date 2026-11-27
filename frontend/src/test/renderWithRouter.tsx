import type { ReactElement } from 'react';
import { render, type RenderOptions } from '@testing-library/react';
import { MemoryRouter, type MemoryRouterProps } from 'react-router-dom';

type RenderWithRouterOptions = Omit<RenderOptions, 'wrapper'> & {
  initialEntries?: MemoryRouterProps['initialEntries'];
};

export function renderWithRouter(
  ui: ReactElement,
  { initialEntries = ['/'], ...options }: RenderWithRouterOptions = {}
) {
  return render(
    <MemoryRouter initialEntries={initialEntries}>{ui}</MemoryRouter>,
    options
  );
}
