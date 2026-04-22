# Tasty Fonts Agent Lessons

- Do not assume Composer, npm, or a build pipeline exists here. Most verification runs directly from the checkout.
- Use the existing harnesses first: `php tests/run.php`, `node --test tests/js/*.test.cjs`, and targeted script tests when release flow is involved.
- Verify behavior in WordPress terms before changing code. A reported issue may be expected behavior or a publish-state/runtime mismatch.
- Watch delivery-profile side effects. Import, storage, publish-state, and provider changes often affect generated CSS, runtime planning, and external stylesheet behavior together.
- Admin renderer changes often span PHP templates, renderer helpers, and shared JS contracts. Check all three before concluding a fix is complete.
- Keep release-related edits aligned with `plugin.php`, `CHANGELOG.md`, `bin/release`, and `tests/bin/release-scripts.test.sh`.
