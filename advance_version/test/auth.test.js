const test = require('node:test');
const assert = require('node:assert/strict');
const { allowedNext, comparePassword, hashPassword } = require('../lib/auth');

const DEMO_HASH = '$2y$10$bZyjLPb/bnoHIAvo2vCy/OEaS2N0iTudfw4gfh/G1uzHfTxZZbTou';

test('allowedNext only allows known internal pages', () => {
  assert.equal(allowedNext('/booking'), '/booking');
  assert.equal(allowedNext('/my-appointments'), '/my-appointments');
  assert.equal(allowedNext('/'), '/');
  assert.equal(allowedNext('https://evil.example'), '/');
  assert.equal(allowedNext('//evil.example'), '/');
  assert.equal(allowedNext(undefined), '/');
});

test('comparePassword handles PHP $2y$ hashes', async () => {
  assert.equal(await comparePassword('bryan123', DEMO_HASH), true);
  assert.equal(await comparePassword('wrong-password', DEMO_HASH), false);
});

test('hashPassword produces a verifiable hash', async () => {
  const hash = await hashPassword('secret123');
  assert.match(hash, /^\$2b\$/);
  assert.equal(await comparePassword('secret123', hash), true);
  assert.equal(await comparePassword('secret124', hash), false);
});
