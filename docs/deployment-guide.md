# Felkyo Creatures — Deployment Guide

This guide explains how Felkyo goes live, how to update it once it is live, and —
just as importantly — **what this deployment is and is not**. It is written for
someone who has not deployed a website before.

> **Status:** the preparation in "Before you deploy" is finished and tested, and
> the Railway steps in "Putting it on the internet" are written and ready to
> follow — but they have not been run yet. Nothing is live.

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

## 3. Putting it on the internet (increment D.2)

**Platform: Railway.** Chosen because it needs the least new knowledge for a first
deployment — the database is a couple of clicks, deploys happen on push, and SSL is
handled for you.

> **These steps have not been run yet.** They are written from Railway's current
> documentation so you have something to follow, but hosting platforms change their
> interfaces often. If a screen does not look like it does here, trust the screen
> and correct this guide afterwards — a deployment guide that says what *actually*
> happened is worth far more than one that says what was expected.

### Before anything else: two prerequisites

**1. This will cost money — roughly £4/$5 a month.** Railway has no permanent free
tier that can keep a website and a database running around the clock. What it has:

| | What you get |
| --- | --- |
| Free trial | $5 of credit, valid 30 days. **No credit card needed** to start. |
| Free plan | $1 of credit per month — not enough for an always-on site plus a database. |
| Hobby plan | $5/month, which includes $5 of usage. |

So the honest expectation is: the trial covers the first month while you learn the
pipeline, and after that it is about $5 a month for as long as the demo stays up.
Usage above the included credit is billed on top, so **check the usage page after
the first week** rather than assuming. If the demo is only meant to be shown to a
few people, it is entirely reasonable to deploy it, take screenshots, and take it
down again — nothing about this project requires it to run forever.

**2. The code has to be on GitHub first.** Railway deploys *from a repository*, and
at the time of writing this project only exists on one computer — `git remote -v`
shows nothing. Creating the Railway project cannot work until the code is pushed.

Create an empty repository on GitHub (**do not** let it add a README, `.gitignore`
or licence — the project already has those), then connect and push:

```
git remote add origin https://github.com/<your-username>/<repo-name>.git
git push -u origin master
```

**Public or private is your choice, and both are safe here.** It has been checked:
`.env` and the `backups/` folder have never been committed and are gitignored,
`.env.example` contains only empty placeholders, and no passwords or keys are
written anywhere in the tracked files. Nothing secret would become visible.

### What builds the app

Railway's builder is **Railpack**. It notices `composer.json`, installs the
dependencies itself, and serves the site with **FrankenPHP**. You do not have to
configure a web server.

**The one setting that is not optional:**

```
RAILPACK_PHP_ROOT_DIR = /app/public
```

Railpack only defaults the document root to `public/` for Laravel projects; for
everything else it serves from the project root. Without this variable, visitors
would be served the *repository* — including `.env`-style files and `src/` — instead
of the site. Set it before the first deploy.

### Step by step

1. **Create the project.** In Railway, create a new project and choose "Deploy from
   GitHub repo", pointing at this repository. That connection is what makes future
   pushes deploy automatically.
2. **Add the database.** Add a MySQL (MariaDB-compatible) database to the same
   project. Railway creates it and exposes its credentials as variables.
3. **Set the environment variables** on the *app* service. Never in the code:

   | Variable | Value |
   | --- | --- |
   | `RAILPACK_PHP_ROOT_DIR` | `/app/public` |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `DB_HOST` | from the database service |
   | `DB_PORT` | from the database service |
   | `DB_NAME` | from the database service |
   | `DB_USER` | from the database service |
   | `DB_PASSWORD` | from the database service |
   | `DEMO_ACCOUNT_PASSWORD` | a long password you choose |

   Note what is **not** in that list: `REGISTRATION_OPEN` and `SHOW_DEMO_NOTICE`.
   Leave them unset. With `APP_ENV=production` the defaults are already the safe
   ones — registration closed, demo banner shown — and a setting you never typed is
   a setting you cannot mistype.

4. **Run the migrations.** Railpack only runs migrations automatically for Laravel,
   so ours are a manual step. Open a shell on the service and run:

   ```
   php vendor/robmorgan/phinx/bin/phinx migrate -e production
   ```

5. **Run the seed script**, so the demo has creatures in it:

   ```
   php vendor/robmorgan/phinx/bin/phinx seed:run -e production
   ```

   It refuses to run if `DEMO_ACCOUNT_PASSWORD` is not set, and it is safe to run
   twice.

6. **Check the site.** Visit the URL Railway gives you and confirm:
   - the padlock is showing (Railway provides SSL automatically),
   - the "development demo" banner is at the top of every page,
   - there is no "sign up" link anywhere, and `/register` shows the closed page,
   - you can log in as a seeded account (`mira`) and pet a creature.

7. **Find out the backup situation and write it down here.** Check whether Railway
   backs this database up automatically, how often, and how a restore works. For a
   demo with no real data this is low-stakes — but it should be *known* rather than
   assumed, and the answer belongs in this guide rather than in somebody's memory.

### Two things to watch on the first deploy

- **If every page except the home page returns 404**, the server is not sending all
  requests to `public/index.php`. Felkyo is a front-controller app: one file handles
  every address. The fix is a `Caddyfile` in the project root telling FrankenPHP to
  fall back to `index.php`. It is not included here because it may well not be
  needed — add it only if you see that symptom.
- **Sessions are stored as files**, which is fine for one instance. If the service is
  ever scaled to run several copies, people would be logged out at random as their
  requests land on different copies. That is a real change to make deliberately
  (sessions would move into the database), not something to discover live.

---

## 4. Deploying an update (increment D.3 — after the first deploy)

Once the pipeline exists, updating the live site is:

1. Commit your change on your machine.
2. Push it.
3. Railway notices the push, rebuilds, and swaps the site over.

**Before pushing, run the tests** (`C:\xampp\php\php.exe vendor/bin/phpunit`). There
is no CI checking for you, so the test suite is only protecting you if you actually
run it.

**If you added a migration**, run it against production afterwards, the same way as
in step 4 above. Code deploys automatically; database structure does not.

This section gets rewritten with what actually happened after the first real deploy.

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
