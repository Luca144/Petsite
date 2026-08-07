# Felkyo Creatures — Deployment Guide

This guide explains how Felkyo goes live, how to update it once it is live, and —
just as importantly — **what this deployment is and is not**. It is written for
someone who has not deployed a website before.

> **Status:** the preparation described in "Before you deploy" is finished and
> tested. The platform-specific steps in "Putting it on the internet" are still to
> be done — the hosting platform has not been chosen yet.

---

## 1. What this deployment is — read this first

Felkyo is deployed as a **closed demo**. That means:

- **Registration is switched off.** Nobody can create an account.
- **The site runs on seeded demo accounts** — a handful of made-up players with
  made-up creatures, created by a script.
- **There are no real users and no real personal data** on it.
- **A banner on every page says so**, so no visitor mistakes it for a real service.

This is deliberate. The point of deploying is to *learn how deploying works*, not
to open and run a real service for real people. Running a real service means
obligations — keeping people's data safe, backups, being available when it breaks,
data-protection law. Those are real commitments, and this project has consciously
chosen not to take them on.

### The security note that matters most

The baseline security in this codebase is solid and not optional: prepared
statements everywhere, passwords hashed with `password_hash()`, CSRF tokens on
every form, input validated, output escaped, rate limits on public actions.

**For a closed demo with registration off and no real data, that baseline is
enough.** The attack surface is small and there is nothing sensitive behind it.

**It is NOT enough for a real public service.** The moment anyone considers opening
registration to real people, a proper security review has to happen *first*,
covering at least:

- authentication and session handling under real-world conditions
- access control on every route, re-checked
- rate limiting and abuse handling at real traffic
- data-protection obligations (GDPR) — what you store, why, for how long, and how
  someone gets their data deleted
- secrets handling and rotation
- known vulnerabilities in dependencies (`composer audit`)

**Do not read "the demo was secure enough" as "the site is production-ready."**
Those are different claims, and only the first one has been made.

---

## 2. Before you deploy (increment D.1 — done)

These pieces are built and covered by tests.

### The registration switch

Public sign-ups are controlled by one setting, `app.registration_open` in
`config/config.php`, which reads the `REGISTRATION_OPEN` environment variable.

**You usually do not need to set it at all.** The default depends on the
environment: registration is **open in development** (so you can make test
accounts) and **closed in production** (so the live demo stays closed). Setting
`REGISTRATION_OPEN=true` or `false` overrides that.

When registration is closed:

- the "sign up" link disappears from the navigation,
- `/register` shows a friendly "the door is closed for now" page (HTTP 403),
- and **posting directly to `/register` still creates nothing** — the refusal is on
  the action itself, not just on the page that shows the form. That distinction is
  the whole point; hiding a form protects nobody.

`tests/Integration/RegistrationClosedTest.php` checks all of this from both
directions.

### The demo banner

`SHOW_DEMO_NOTICE` controls the "this is a development demo" strip at the top of
every page. It defaults to **on in production** and **off in development**. Set
`SHOW_DEMO_NOTICE=true` locally if you want to see how it looks.

### The seed script

`seeds/DemoContent.php` fills an empty database with three demo players, their
creatures, and some petting and guestbook activity — so the demo looks lived-in.

**Set a password first.** Every demo account shares one password, read from
`DEMO_ACCOUNT_PASSWORD`. The script **refuses to run without it** rather than
inventing a weak one. Put a long password in `.env` (locally) or in the platform's
environment variables (in production). It is never written in the code, because a
password committed to a repository is a password everybody has.

Run it from the project root:

```
C:\xampp\php\php.exe vendor/robmorgan/phinx/bin/phinx seed:run -e development
```

Use `-e production` on the live server. **Running it twice is safe** — it checks
whether the demo accounts already exist and does nothing if they do.

`tests/Integration/DemoSeedTest.php` runs the real script against the test database
on every test run, so a typo in it is caught here rather than on the live server.

---

## 3. Putting it on the internet (increment D.2 — not done yet)

**This section is waiting on one decision: which hosting platform.** The choice is
between managed platforms — Railway, Render, or Fly.io. All three handle SSL and
deploy-on-push for you, which is exactly what makes them suitable for a first
deployment.

Once the platform is chosen, this section will cover, step by step:

1. Connecting the Git repository so a push deploys automatically.
2. Provisioning a managed MariaDB/MySQL database.
3. Setting the environment variables on the platform (never in the code):
   `APP_ENV=production`, `APP_DEBUG=false`, the database credentials, and
   `DEMO_ACCOUNT_PASSWORD`.
4. Running the migrations against the production database.
5. Running the seed script against production.
6. Confirming SSL is active (the padlock in the browser).
7. **Finding out and writing down the platform's database backup situation** —
   whether backups happen automatically, how often, and how a restore works. For a
   demo with no real data this is low-stakes, but it should be *known* rather than
   assumed.

---

## 4. Deploying an update (increment D.3 — not done yet)

Once the pipeline exists, updating the live site is: commit your change, push it,
and the platform rebuilds and redeploys on its own. The exact steps get written
here after the first real deploy, so that what is written is what actually
happened rather than what was expected to happen.

---

## 5. If you ever want to open registration

You would need to, in this order:

1. Read the security note in section 1 again, properly.
2. Get a real security review done.
3. Work out your data-protection obligations for real users.
4. Only then set `REGISTRATION_OPEN=true` in the platform's environment variables.

Step 4 is one setting and takes ten seconds. Steps 1–3 are the actual work, and
skipping them is how small demo sites end up quietly holding real people's
passwords without anyone having thought about it.
