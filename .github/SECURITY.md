# Security Policy

## Supported versions

`jul6art/push-bundle` is installed by other applications through Composer, so a fix here
reaches them the moment they update. Only the current major line gets one.

| Version | Supported |
| --- | --- |
| `2.x` | ✅ |
| `1.x` | ❌ |
| any older tag or fork | ❌ |

Support means security fixes on the latest release of that line — upgrade to it before
reporting, in case the problem is already gone.

## What is in scope

This bundle decides who hears about a change, which makes its topics an access-control
surface:

* **A topic that reaches the wrong subscriber** — too broad, built from client input, or
  resolved from an identifier the subscriber chose.
* **Sensitive data in a payload** — row content, personal data or a secret pushed to
  subscribers whose right to read it was never checked.
* **Anything wrong with the Mercure JWT** — a secret in a log, a template or a
  client-visible variable, a token without expiry, or a subscribe token granting more topics
  than the subscriber may hear.
* **A message published before the transaction commits**, announcing state that never
  existed, or published for a change that was rolled back.
* **An unthrottled fan-out** — one domain change producing an unbounded number of messages,
  against the hub or against every connected client.

Out of scope: vulnerabilities in Symfony, Doctrine, API Platform or any other third-party
package — report those to the project that owns the code, and they will reach you through
your own `composer update`. Also out of scope: an application that misconfigures this bundle
in a way the README warns against, though a warning that turns out to be easy to miss is
worth an issue of its own.

## Reporting a vulnerability

**Do not open a public issue for a security problem.**

Use [GitHub's private vulnerability reporting](https://github.com/jul6art/push-bundle/security/advisories/new)
(the **Security** tab → *Report a vulnerability*). It opens a draft advisory only
you and the maintainers can read, and it is the channel this project prefers —
no email address needs to be published for it to work.

Please include:

* the version of `jul6art/push-bundle` and of Symfony you are running,
* the relevant part of your bundle configuration,
* the shortest reproduction you have — ideally a failing test against this
  repository, since that is what a fix will be built on,
* what an attacker gains: which check is bypassed, which data is read or
  written, and whether authentication is required.

## What to expect

* An acknowledgement within **7 days**.
* An assessment — accepted, out of scope, or needing more detail — within
  **14 days**.
* For an accepted report: a fix released on the supported line, a
  [security advisory](https://github.com/jul6art/push-bundle/security/advisories)
  describing the impact and the version to upgrade to, and credit in it unless
  you ask otherwise.

Please give the maintainers a reasonable window to ship a release before disclosing
publicly. This project runs no bug-bounty programme and offers no payment.