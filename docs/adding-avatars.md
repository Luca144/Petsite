# How to add an avatar

*A "how to add one" recipe, for a reader who has never programmed.*

Avatars are the faces players choose for their page. **Players never upload their
own**, and that is a deliberate safety decision rather than a missing feature.

## Why players cannot upload one

Accepting uploaded pictures means moderating pictures. That is much harder than
moderating text: a person has to look at every one, quickly, at any hour. It is
also the easiest route for something genuinely harmful onto a site that children
use. A chosen set removes the problem rather than managing it — and it keeps the
site looking of a piece.

This is not something to revisit casually. If it ever is revisited, it becomes a
fresh safety decision by the humans, not a build task.

## What an avatar is, technically

A player's avatar is stored as a **key** — a short word like `default`. It is
never a filename and never a web address.

That distinction is the whole security story. If the column held a filename,
somebody could type `../../../.env` into it, or the address of a server they
control — and then every visitor to their page would quietly fetch a file of
their choosing, handing them the IP address of everyone who looked. Children
included. Holding a key means the value is looked up in a list the project
controls, and anything not in that list simply is not an avatar.

## The steps

1. **Put the picture in `public/assets/avatars/`.** A square PNG. Name it
   sensibly and in lowercase with hyphens: `sleepy-fox.png`.

2. **Add an entry to `config/config.php`** under `avatars`:

   ```php
   'sleepy-fox' => ['name' => 'A sleepy fox', 'file' => 'sleepy-fox.png'],
   ```

   The key on the left is what gets stored. The `file` is the picture. The `name`
   is what a player reads beside it when choosing, and what a screen reader
   announces — **it is not optional**, because a grid of unlabelled pictures is
   unusable without sight.

3. **That is all.** The chooser on the profile page builds itself from the list,
   so there is no form to update and no code to touch.

## If you retire an avatar

Removing an entry is safe. Anyone still wearing it falls back to the default
picture rather than ending up with a broken image — a profile always has a face.
Their stored key stays as it was, so if you put the avatar back, so do they.

## Where this is going

From M2.4 this becomes a panel screen: upload the picture, type the name, save.
The steps above are exactly what that screen will do, which is worth knowing when
it is built.
