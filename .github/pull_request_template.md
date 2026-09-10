## What this changes

<!-- One subject per pull request. Say what an application using this bundle
     will be able to do, or stop having to do. -->

Closes #

## Type

- [ ] Bug fix — **patch**
- [ ] New feature, existing code untouched — **minor**
- [ ] Breaking change — **major** (removed or renamed configuration key, new
      method on a contract, narrowed type, changed default)
- [ ] Documentation or tooling only

<!-- A new method on an interface applications implement is breaking, even
     though PHP only says so when someone upgrades. -->

## Quality gate

```
composer qa
```

- [ ] `cs-check` — php-cs-fixer clean
- [ ] `rector-check` — Rector clean
- [ ] `phpstan` — clean at `level: max`, with no new baseline entry
- [ ] `test` — PHPUnit green, with no deprecation, notice, warning or risky test

<details>
<summary>Output</summary>

```
```

</details>

## Tests

- [ ] A bug fix ships the test that fails without it
- [ ] A new feature ships tests for what it adds
- [ ] I checked the `lowest` dependency set matters here (widened constraint,
      new vendor call) and said so below

## Documentation

- [ ] The README is updated in this pull request *(new or changed configuration
      key, contract, public service or behaviour)*
- [ ] The house rules in [CONTRIBUTING.md](CONTRIBUTING.md) still hold for what
      I added

## Notes for the reviewer

<!-- Trade-offs, anything left out on purpose, follow-up work, and which
     application this came out of. -->
