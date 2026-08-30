# M2.2 — Image upload: where the files live (the decision memo)

**Status: awaiting approval from the Lead Developer and the Product Owner.
No code has been written. This is the MAJOR DECISION gate the build plan
puts in front of M2.2.**

Written for two people who have said they are not weathered in hosting. So
it explains the words before it uses them, and it ends with one clear
recommendation and the exact questions you need to answer.

---

## 1. The problem, in plain words

The site runs on Railway inside a **container** — think of it as a fresh
computer that Railway builds from the code every time we push, switches on,
and later throws away when the next push arrives. Everything on that
computer's disk is rebuilt from the code each time. Which is fine for code,
and fatal for uploads: **an image the artist uploads on Tuesday is written
to Tuesday's computer, and Wednesday's bugfix replaces that computer with a
new one built from the code alone.** The sprite is gone. Nothing warns you.
The site would simply show a broken image where the creature was.

That is why the plan says: before building the upload button, decide where
the uploaded files are going to *live* — somewhere that survives the next
deploy. There are three honest answers, and the point of this memo is that
you choose one knowing what you are choosing.

(We have met the shape of this problem once already: the first deploy
shipped Git LFS pointers instead of images, and every creature was broken
with a cause that looked perfectly fine on the development machine. See the
deployment guide, "the five things that went wrong the first time".)

## 2. What an uploaded picture actually needs

Five things, and every option below is judged on all five:

1. **Persist** — survive deploys, restarts, and Railway rebuilding things.
2. **Be served** — the browser has to fetch it quickly, on every creature
   page, thousands of times.
3. **Be backed up** — a disk can die; a mistaken "replace" can overwrite
   the right sprite with the wrong one. There must be a way back.
4. **Be replaceable** — M2.3 promises "one-click art replacement that
   updates every reference and defeats caching". Wherever the file lives,
   swapping it must be one action and the old picture must not linger in
   anyone's browser.
5. **Be safe** — uploads are the highest-value target on the site
   (CLAUDE.md §7). Section 7 below lists the rules; they apply to every
   option identically, so they are not a factor in the choice.

## 3. Option A — a Railway **volume** (a persistent disk)

**What it is.** Railway can attach a disk to the app that is NOT thrown
away with the container — a "volume". It appears to the code as an
ordinary folder. Uploads are written there and served from there like any
other file on the site.

**Verified facts (Railway docs, 2026-08-21):**
- One volume per service; yours would be attached to the app service.
- Size cap on the Hobby plan (the £4–5/month one you are on): **5 GB**.
  The site's entire art today is 1.4 MB. Even a thousand species with
  every stage and a background each would not approach 5 GB.
- Price: **$0.15 per GB per month**, billed for what is actually used. At
  100 MB of art that is about **1.5 cents a month**.
- Backups exist and are switched on per volume: daily (kept 6 days),
  weekly (kept a month), monthly (kept 3 months), combinable. Billed the
  same way — pennies at this size. Restore is a button in the dashboard.
- Caveat: **a service with a volume has a few seconds of downtime on each
  deploy** (Railway unmounts the disk before the new container takes it,
  so two containers never write to it at once). Today deploys are seamless.
  For a closed demo this does not matter; for a live site it is a few
  seconds of "quiet moment" per push.
- Caveat: **wiping the volume deletes its backups too**, and restores only
  work inside the same Railway project. The backups protect against
  mistakes and disk failure — not against the Railway account itself going
  away (card fails, project deleted). For that, see "keep a copy off the
  platform" in section 8.

**What it means for Skerro.** Upload in the panel, see it on the page.
Nothing else to think about.

**What it means for the code.** The smallest change of the three. PHP
writes the file to the volume folder; the web server serves it. One new
Railway setting tells the code where the folder is; locally it is just a
folder in the project.

**What can go wrong.** The disk is one disk, inside Railway. If you ever
leave Railway, the art has to be copied out first (a maintenance button
can zip it up for download — a natural M2.8 "named button"). If nobody
switches the backup schedule on, there is no backup. That switch is a
deployment-guide step, and this memo says so twice on purpose.

## 4. Option B — **object storage** (Cloudflare R2)

**What it is.** Instead of a disk, a "bucket": a service whose only job is
keeping files, forever, in many copies, and handing them out over the
internet. Amazon S3 invented the shape; Cloudflare R2 is the same shape
with no fee for people downloading. The app uploads each picture to the
bucket; browsers fetch pictures from the bucket, not from the app.

**Verified facts (Cloudflare docs, 2026-08-21):**
- Free tier: **10 GB stored, 1 million uploads and 10 million downloads a
  month, downloading is free**. This site would live inside the free tier
  for years. Beyond it: $0.015/GB/month.
- Files are replicated and durable in a way a single disk is not, and
  they exist outside Railway entirely.
- **The catch, which is the fact that changed my recommendation:** to
  serve the bucket's files to the public *properly*, R2 needs a **custom
  domain on Cloudflare** (e.g. `art.felkyo.example`). Its built-in public
  address (`something.r2.dev`) is documented as "rate-limited and should
  only be used for development purposes". Felkyo does not own a domain
  yet — the site lives at `felkyo-production.up.railway.app`. So R2 means:
  buy a domain (~£10/year), put it on Cloudflare, connect it to the bucket.
  None of that is hard, but all of it is new hosting work for two people
  who have said hosting is not their comfort zone — and every piece of it
  is a place to get stuck at 11pm with the site half-working.

**What it means for Skerro.** Identical: upload in the panel, see it on
the page. He would never know which option was chosen.

**What it means for the code.** More. The app must sign every upload
request the way S3 requires ("SigV4"). That is either a dependency (the
official AWS SDK is enormous; there are small S3 clients, but adding any
package is an ask-first decision under CLAUDE.md §12) or ~150 lines of our
own signing code plus tests. Plus a Cloudflare account, an API key kept in
Railway's variables, and a second place to look when an image fails to
appear ("is it the app, the bucket, the domain, or the cache?").

**What can go wrong.** Mostly configuration, mostly at setup: the
domain, the key, the bucket permissions. Once running it is the most
robust of the three by a distance.

## 5. Option C — keep art in the **repository** (no upload)

**What it is.** What happens today. The artist sends files to whoever has
a developer's machine; they are committed and pushed; the deploy carries
them. The panel would edit *words and numbers* about a species, but never
its pictures.

**Cost:** nothing. **Durability:** excellent — Git keeps every version
forever, in every clone. **Code:** none to write.

**Why the plan says no, and I agree:** *this is the exact problem M2
exists to solve.* The build plan's definition of done for M2 is "the
artist adds a complete new species — every stage, stats, blurbs, library
entry — alone, start to finish." Option C keeps a developer in the loop for
every single picture, forever. The artist's previous project died partly
under that weight (build plan, M2.3 rationale). It is listed here because
it is genuinely the cheapest and you should see it refused with reasons,
not omitted.

## 6. Side by side

| | A: Railway volume | B: Cloudflare R2 | C: repository |
|---|---|---|---|
| Survives deploys | yes | yes | yes |
| Cost at this size | ~cents/month | free | free |
| New accounts / keys | none | Cloudflare account + API key + **a domain** | none |
| Code to write | small | moderate (S3 signing) | none |
| Backups | switch on in Railway (pennies) | built in | Git itself |
| Survives leaving Railway | only if copied out first | yes | yes |
| Deploy downtime | a few seconds per deploy | none | none |
| Artist independent of developers | **yes** | **yes** | **no** |
| Things that can go wrong at 11pm | few | more, all at setup | none |

## 7. The safety rules — the same whichever you choose

These are not part of the decision; they are conditions of building it at
all (build plan M2.2, CLAUDE.md §7 "Uploads"). Listed so you know what
"upload" will mean in practice:

- **The file's real content is checked, never its name or declared type.**
  A file called `sprite.gif` that is actually a PHP script is refused by
  reading its bytes.
- **Size and dimensions are capped** (named config values, e.g. 2 MB and
  1024×1024 — numbers for you to sign off in the M2.2 build plan).
- **The stored name is generated by the site**, never taken from the
  upload, so a hostile filename cannot escape its folder.
- **Served from a path where nothing can execute.** On a volume that is a
  web-server rule; on R2 it is automatic (a bucket runs nothing).
- **Metadata is stripped where it can be.** Honest caveat: re-encoding an
  animated GIF/WebP through PHP's image library flattens it to one frame,
  and CLAUDE.md forbids ever baking a static frame from an animated source.
  So animated files are validated structurally and stored byte-for-byte;
  only still images are re-encoded. Sprites are animated, so most uploads
  will take the first path. The image library (`gd`) is available on
  Railway's PHP by adding one line to `composer.json`, no deploy setting.
- **Tests** for each refusal: a disguised non-image, an oversized file, a
  hostile filename, and that a served file is inert.

## 8. Recommendation

**Option A — a Railway volume — now, built so that moving to R2 later is a
swap, not a rewrite.**

I said earlier my instinct was R2. Verification changed it, and here is
exactly why: R2's proper public serving needs a domain you do not own yet,
plus an account, a key, and signing code, and every one of those is a new
thing to learn and a new place for a Tuesday-night outage to hide. For a
closed demo run by two people new to hosting, the volume gives the artist
the same independence with one Railway setting and a small amount of
plain code — and the durability gap is closed by two habits rather than a
second platform: **switch on the volume's daily + weekly backups**, and
**keep the originals off the platform** (Skerro's own files, plus an M2.8
"download all art" button so a full copy is one click).

The code will keep the file-storage part behind a small, single-purpose
class (`ImageStore`: put, replace, delete, public URL) with the volume as
its only implementation. When Felkyo gets a domain — which it will want
anyway before registration opens — an R2 implementation of that same
class is a contained job, and the safety rules, the panel, and every
template stay untouched. That is the whole "swap, not rewrite" promise,
and it is cheap to keep because it is one class, not a plugin system.

**When to revisit:** at the "open registration" gate (the plan's Part One
checkpoint and the first external security review), which is also when a
domain becomes necessary for other reasons. Put it on that checklist now.

## 9. What I need from you (and Skerro)

1. **Approve option A**, or tell me you'd rather go straight to B knowing
   it means a domain + Cloudflare setup first.
2. **Who switches on the volume and its backups?** It is a few clicks in
   Railway: App service → Volume → mount path, then Backups → daily +
   weekly. I will write the exact steps into the deployment guide; you or
   I can drive, but it happens in your dashboard.
3. **The upload limits** for the M2.2 build plan: I will propose 2 MB per
   file and 1024×1024 pixels, GIF/PNG/WebP only. Say if you want different.
4. **Confirm the animated-GIF rule** is understood: animated uploads are
   kept exactly as uploaded (no re-encoding), so the artist's export is
   what players see, pixel for pixel.

Once you answer, M2.2's build plan (three security questions, tests, the
`ImageStore` class, the panel's upload control) follows the same
plan-then-build path M2.1 did, and M2.3 — one page per creature — is the
first screen to use it.
