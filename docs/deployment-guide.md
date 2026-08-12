# Felkyo Creatures — Deployment Guide

This guide explains how Felkyo goes live, how to update it once it is live, and —
just as importantly — **what this deployment is and is not**. It is written for
someone who has not deployed a website before.

> **Status: Felkyo is live** at <https://felkyo-production.up.railway.app> as a
> closed demo, and Phase D is complete. Sections 3 and 4 describe what actually
> happened, including the five things that went wrong on the way. The backup
> situation is written up at the end of section 3 — **read it before M2**, which is
> the milestone that starts putting irreplaceable work into the database.

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

Use `-e production` on the live server. **Running it twice is safe, and useful:**
if the demo accounts already exist it does not duplicate them, it sets their
password to the current `DEMO_ACCOUNT_PASSWORD`. That is how you change the demo
password. Either way it prints which of the two things it did.

`tests/Integration/DemoSeedTest.php` runs the real script against the test database
on every test run, so a typo in it is caught here rather than on the live server.

---

## 3. Putting it on the internet (increment D.2 — done)

**Felkyo is live at <https://felkyo-production.up.railway.app>.** This section is
written from what actually happened, not from what was expected to happen — several
of the steps below only exist because they went wrong first.

### The two prerequisites

**1. This costs money — roughly £4/$5 a month.** Railway has no permanent free tier
that keeps a website and a database running around the clock.

| | What you get |
| --- | --- |
| Free trial | $5 of credit, valid 30 days. **No credit card needed** to start. |
| Free plan | $1 of credit per month — not enough for an always-on site plus a database. |
| Hobby plan | $5/month, which includes $5 of usage. |

The trial covers the first month while you learn the pipeline; after that it is
about $5 a month for as long as the demo stays up. **Check the usage page after the
first week** rather than assuming. And remember a demo does not have to run forever
— deploying it, showing it, and taking it down again is a perfectly good outcome.

**2. The code has to be on GitHub.** Railway deploys *from a repository*. This
project lives at <https://github.com/Luca144/Petsite>.

Publishing it is safe, and that was checked rather than assumed: `.env` and
`backups/` have never been committed and are gitignored, `.env.example` holds only
empty placeholders, and no passwords or keys are written in any tracked file.

### Setting it up

1. **Sign up** at [railway.com](https://railway.com) with **Login with GitHub** —
   Railway needs GitHub access anyway, so this is one step instead of two.
2. **Create the project** from the GitHub repo. When Railway asks which
   repositories its GitHub App may see, granting only this one is the tidier choice.
3. **Add the database:** **+ Create → Database → MySQL**.
4. **Generate the public address:** service → **Settings → Networking → Public
   Networking → Generate Domain**.

   Do not confuse this with **Private Networking**, which shows an address ending
   in `.railway.internal`. That one only works *inside* Railway — it is how the app
   reaches the database, and it is not reachable from the internet at all.

5. **Set the variables** on the **app** service (not on the MySQL service — a
   common wrong turn, and the reason the reference picker shows "no suggestions"):

   | Variable | Value |
   | --- | --- |
   | `RAILPACK_PHP_ROOT_DIR` | `/app/public` |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `DEMO_ACCOUNT_PASSWORD` | a long password you choose |
   | `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
   | `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
   | `DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
   | `DB_USER` | `${{MySQL.MYSQLUSER}}` |
   | `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |

   The `${{…}}` form is Railway's way of pointing one service at another's values,
   so the credentials are never copied by hand.

   Leave `REGISTRATION_OPEN` and `SHOW_DEMO_NOTICE` **unset**. With
   `APP_ENV=production` the defaults are already the safe ones — registration
   closed, demo banner shown — and a variable you never type is one you cannot
   mistype.

6. **Deploy.** Variables alone change nothing until a build runs with them. If
   pushing does not trigger a build, press **`Ctrl + K`** and choose **Deploy Latest
   Commit**. (This is not in Settings, which is why it is easy to hunt for.)

7. **Create the tables.** App service → **Console**:

   ```
   php vendor/robmorgan/phinx/bin/phinx migrate -e production
   ```

8. **Fill the demo world:**

   ```
   php vendor/robmorgan/phinx/bin/phinx seed:run -e production
   ```

9. **Check it.** Log in as `mira` with your `DEMO_ACCOUNT_PASSWORD`, confirm the
   demo banner is on every page, that there is no "sign up" link, and that
   `/register` shows the closed page.

### The five things that went wrong the first time

Every one of these produced the same symptom — a blank HTTP 500 — from a completely
different cause. If a deploy misbehaves, work through them in this order.

**1. The document root was wrong.** Railway's builder is **Railpack**, which only
defaults the document root to `public/` for *Laravel* projects. For everything else
it serves the repository root, so visitors got a file listing and the site never
ran. `RAILPACK_PHP_ROOT_DIR=/app/public` is not optional.

*How to spot it:* `/composer.json` returns 200. It should return 404.

**2. PHP had no MySQL driver.** The log said `PDOException: could not find driver` —
not a wrong password, but no driver at all, so the connection was never attempted.
Railpack builds a lean PHP and installs only what the project asks for.

Fixed in `composer.json`, not in a Railway setting: `ext-pdo_mysql` and
`ext-mbstring` were added. `ext-pdo` alone was already there and was not enough —
that gives you PDO but not the MySQL *driver*.

**3. Images were in Git LFS.** Railway does not fetch LFS files. The build received
small text pointers instead of the artwork, so every creature and the logo would
have been broken — with a confusing cause, because the files look perfectly fine on
a development machine. All site artwork is now ordinary Git content; see
`.gitattributes` for the full reasoning.

**4. Pushing did not deploy.** The container kept running old code for a quarter of
an hour after a push, so the same error repeated and looked like the fix had failed.

*How to spot it:* in the logs, look for `Starting Container`. If there is no new one
after your push, nothing was rebuilt and you are still watching the old code.

**5. The seed script skipped silently.** Changing `DEMO_ACCOUNT_PASSWORD` and
re-running the seeder appeared to do nothing, because it found the demo accounts
already existed and returned without a word. The accounts kept their original
password and login kept failing. **This is fixed** — re-running now updates the
password and reports what it did.

### Two things to watch

- **If every page except the home page returns 404**, the server is not sending all
  requests to `public/index.php`. Felkyo is a front-controller app: one file handles
  every address. The fix would be a `Caddyfile` in the project root telling
  FrankenPHP to fall back to `index.php`. This did not turn out to be necessary.
- **Sessions are stored as files**, which is fine for one instance. If the service
  is ever scaled to several copies, people would be logged out at random as their
  requests land on different ones. Moving sessions into the database is a deliberate
  change to make first, not something to discover live.

### Backups — what Railway actually does

**The short version: Railway does not back your database up unless you tell it to.**
Backups exist, but they are a *schedule you switch on per volume*, not something that
happens quietly in the background because you are paying. Assuming otherwise is the
expensive mistake here, so it is written down plainly.

**Where it lives:** MySQL service → **Backups** tab. A backup is taken of the
**volume** — the disk the database sits on — not of Felkyo specifically.

**The three schedules Railway offers**, which can be combined on one volume:

| Schedule | Taken | Kept for |
| --- | --- | --- |
| Daily | every 24 hours | 6 days |
| Weekly | every 7 days | 1 month |
| Monthly | every 30 days | 3 months |

Backups are billed the same way volumes are — per gigabyte, on top of the usual cost.
Felkyo's database is tiny, so this is pennies rather than a real decision.

**How a restore works:** find the backup by its date stamp, press **Restore**, and
Railway stages the change for you to confirm before it is applied.

**Two things about restoring that are easy to be caught out by:**

1. **Restoring deletes every backup newer than the one you restore.** So if you
   restore to Tuesday to check something, Wednesday and Thursday are gone. If you are
   ever unsure which backup you want, that uncertainty is the reason to take a manual
   copy *first*.
2. **Railway describes this feature as still under development.** That is their word,
   not a criticism — but it means the safety net is newer than the thing it is
   catching, and it should not be the only copy of anything irreplaceable.

**What to switch on now:** turn **Daily** on. It costs almost nothing and it covers
the ordinary disaster — a bad migration, a mistaken delete, a seed run against the
wrong environment.

**Why this stops being low-stakes sooner than it looks.** Today the live database
holds seeded demo accounts, and losing it would cost one `seed:run`. That changes at
**M2**, when the creator's panel starts holding uploaded artwork, per-stage blurbs,
lore and hand-entered loot tables — work that exists nowhere else and represents
weeks of one person's time. It is worth being clear-eyed about which loss actually
ends a project: player accounts can be apologised for, but a year of somebody's
artwork cannot be re-made from a backup that was never taken.

**So, before M2 ships, do both of these:**

- Confirm Daily backups are on, and confirm it by looking at the list of backups
  rather than at the setting — a schedule that has never produced a file is a setting,
  not a backup.
- Work out how to take a **manual export** you hold yourself (a `mysqldump` from the
  service console, downloaded and kept off Railway). One copy on the platform and one
  copy off it is the whole of what "backed up" means. A copy that lives only inside
  the same account that could be lost, suspended, or mis-clicked is one copy.

---

## 4. Deploying an update (increment D.3)

**The normal case is three steps:**

1. Run the tests: `C:\xampp\php\php.exe vendor/bin/phpunit`. There is no CI checking
   for you, so the suite only protects you if you actually run it.
2. Commit your change.
3. Push it. Railway rebuilds and swaps the site over.

**If the push does not trigger a build:** `Ctrl + K` → **Deploy Latest Commit**. Then
confirm in **Deployments** that the top entry really shows your newest commit — a
"Redeploy" on an older entry rebuilds the *old* code and changes nothing.

**If you added a migration**, run it against production afterwards:

```
php vendor/robmorgan/phinx/bin/phinx migrate -e production
```

Code deploys automatically; database structure does not.

### Changing the demo password

Two steps, in this order:

1. Change `DEMO_ACCOUNT_PASSWORD` in the app service's variables.
2. App service → Console:
   `php vendor/robmorgan/phinx/bin/phinx seed:run -e production`

The second step is what actually changes the accounts — step 1 on its own only
changes what *future* seeding would use. The seeder now prints which of the two
things it did, so you can see it worked.

### Turning registration on or off

Set `REGISTRATION_OPEN` to `true` or `false` in the app service's variables and let
it redeploy. No code change, no migration. **But read section 5 before switching it
on.**

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
