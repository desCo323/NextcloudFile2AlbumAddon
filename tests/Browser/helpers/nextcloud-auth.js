const { expect, test } = require('@playwright/test');

function shouldRunAuthenticatedTests() {
  return process.env.SAKURAALBUM_AUTH_TESTS === '1';
}

function requireCredentials() {
  const username = process.env.SAKURAALBUM_TEST_USER || '';
  const password = process.env.SAKURAALBUM_TEST_PASSWORD || '';

  if (!username || !password) {
    throw new Error(
      'Authenticated SakuraAlbum browser tests require SAKURAALBUM_TEST_USER and SAKURAALBUM_TEST_PASSWORD.',
    );
  }

  return { username, password };
}

async function loginNextcloud(page) {
  const { username, password } = requireCredentials();

  await page.goto('/login', { waitUntil: 'domcontentloaded' });

  const userInput = page.locator('#user');
  const passwordInput = page.locator('#password');
  await expect(userInput).toBeVisible();
  await expect(passwordInput).toBeVisible();

  await userInput.fill(username);
  await passwordInput.fill(password);

  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30000 }).catch(() => null),
    page.locator('button[type="submit"]').first().click(),
  ]);

  if (page.url().includes('/login/challenge')) {
    throw new Error('Nextcloud login reached a second-factor challenge; the test account must not require 2FA.');
  }

  if (new URL(page.url()).pathname.endsWith('/login')) {
    const bodyText = await page.locator('body').innerText().catch(() => '');
    throw new Error(`Nextcloud login did not complete. Visible page text: ${bodyText.slice(0, 300)}`);
  }

  await page.waitForLoadState('domcontentloaded');
}

function skipUnlessAuthEnabled() {
  test.skip(!shouldRunAuthenticatedTests(), 'Set SAKURAALBUM_AUTH_TESTS=1 to run live authenticated tests.');
}

module.exports = {
  loginNextcloud,
  skipUnlessAuthEnabled,
};
