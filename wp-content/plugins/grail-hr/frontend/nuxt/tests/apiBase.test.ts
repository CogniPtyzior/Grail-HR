// Tests runtime API base resolution used by standalone dev and embedded WordPress production builds.
import { describe, expect, it, vi } from 'vitest';
import { grailHrApiBase } from '../utils/apiBase';

describe('grailHrApiBase', () => {
  it('trims the fallback URL trailing slash', () => {
    expect(grailHrApiBase('https://example.test/wp-json/grail-hr/v1/')).toBe('https://example.test/wp-json/grail-hr/v1');
  });

  it('uses the WordPress runtime config when available in the browser', async () => {
    vi.stubGlobal('window', { GrailHRConfig: { apiBase: '/wp-json/grail-hr/v1/' } });
    const imported = await import('../utils/apiBase');

    expect(imported.grailHrApiBase('https://fallback.test/')).toBe('/wp-json/grail-hr/v1');
    vi.unstubAllGlobals();
  });
});
