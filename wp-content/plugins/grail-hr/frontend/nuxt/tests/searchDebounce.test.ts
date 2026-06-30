// Documents and tests the search normalization rule used by the profile table.
import { describe, expect, it } from 'vitest';

function normalizeSearch(value: string): string {
  const normalized = value.trim().replace(/\s+/g, ' ');
  return normalized.length === 1 ? '' : normalized;
}

describe('profile global search normalization', () => {
  it('trims and compresses spaces', () => {
    expect(normalizeSearch('  wordpress   docker  ')).toBe('wordpress docker');
  });

  it('does not query for one character', () => {
    expect(normalizeSearch('a')).toBe('');
  });
});
