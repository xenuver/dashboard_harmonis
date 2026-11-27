import { describe, expect, it } from 'vitest';
import {
  formatAngka,
  formatAngkaSingkat,
  formatDate,
  formatRupiah,
  formatRupiahSingkat,
} from '../../services/formatters';

describe('formatters', () => {
  describe('formatDate', () => {
    it('formats ISO date string with day, localized short month, and year', () => {
      const result = formatDate('2026-03-15T00:00:00');
      const expectedMonth = new Date('2026-03-15T00:00:00').toLocaleString(['id-ID', 'default'], {
        month: 'short',
      });

      expect(result).toBe(`15 ${expectedMonth} 2026`);
    });

    it('formats numeric timestamp values', () => {
      const timestamp = new Date(2025, 0, 5).getTime();
      const expectedMonth = new Date(timestamp).toLocaleString(['id-ID', 'default'], {
        month: 'short',
      });

      expect(formatDate(timestamp)).toBe(`5 ${expectedMonth} 2025`);
    });
  });

  describe('formatRupiah', () => {
    it('formats positive amounts with Indonesian dot separator and Rp suffix', () => {
      expect(formatRupiah(0)).toBe('Rp0,-');
      expect(formatRupiah(150000)).toBe('Rp150.000,-');
      expect(formatRupiah(1234567)).toBe('Rp1.234.567,-');
    });

    it('formats decimal values with comma decimal separator', () => {
      expect(formatRupiah(1250.5)).toBe('Rp1.250,5,-');
    });
  });

  describe('formatAngka', () => {
    it('formats integers and decimals using Indonesian separators', () => {
      expect(formatAngka(0)).toBe('0');
      expect(formatAngka(987654)).toBe('987.654');
      expect(formatAngka(1234.75)).toBe('1.234,75');
    });
  });

  describe('formatAngkaSingkat', () => {
    it('returns plain string for zero and values under 1000', () => {
      expect(formatAngkaSingkat(0)).toBe('0');
      expect(formatAngkaSingkat(750)).toBe('750');
    });

    it('abbreviates positive thousands, millions, billions, and trillions', () => {
      expect(formatAngkaSingkat(1500)).toBe('1.5K');
      expect(formatAngkaSingkat(2500000)).toBe('2.5M');
      expect(formatAngkaSingkat(3200000000)).toBe('3.2B');
      expect(formatAngkaSingkat(4000000000000)).toBe('4T');
    });

    it('abbreviates negative numbers symmetrically', () => {
      expect(formatAngkaSingkat(-2500)).toBe('-2.5K');
      expect(formatAngkaSingkat(-1500000)).toBe('-1.5M');
    });

    it('truncates decimals when decimalLength is specified', () => {
      expect(formatAngkaSingkat(14.5678, 2)).toBe('14.56');
      expect(formatAngkaSingkat(12345, 1)).toBe('12.3K');
    });
  });

  describe('formatRupiahSingkat', () => {
    it('uses full Rupiah format for values under 1000', () => {
      expect(formatRupiahSingkat(500)).toBe('Rp500,-');
      expect(formatRupiahSingkat(0)).toBe('Rp0,-');
    });

    it('uses abbreviated Rp prefix for values >= 1000', () => {
      expect(formatRupiahSingkat(2500)).toBe('Rp2.5K');
      expect(formatRupiahSingkat(1500000)).toBe('Rp1.5M');
    });
  });
});
