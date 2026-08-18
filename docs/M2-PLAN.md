# M2 Plan: Economy & Tamagotchi Core

**Goal:** Make Felkyo feel alive. Creatures should feel like *real companions*, not just collectibles. Economy redesign makes player interaction the core loop.

---

## 1. Why This Tamagotchi Works (Psychology)

### The Fun Formula
- **Tiny interactions, big rewards** — 10-second pet interaction feels rewarding
- **Visible consequences** — you see happiness/energy change in real-time
- **Regular checking** — players check their favourite pet multiple times a day
- **Sharing moment** — "look at my creature's mood" becomes social
- **Progression** — watching a creature "react" to care (happiness → new animations/reactions)

### What Makes It NOT a Chore
1. **No punishment for not playing** — creatures don't "die" or get sad permanently
2. **Treats are abundant, not grindy** — you get them from playing naturally
3. **Happiness decays slowly** — playing once per day is enough
4. **Interactions are quick** — 5 seconds, not 5 minutes
5. **Visual feedback is satisfying** — hearts/sparkles when you feed/pet

---

## 2. Core Features to Implement

### A. Shop-Based Pet Acquisition
**Current:** Daily adoption (one free pet per 24h)
**New:** Players buy pets from shop with gems

**Details:**
- Starter species cost 50 gems (balance: easy early access)
- Rare species cost 200-500 gems
- Starter species from `/adopt` → now in `/shop` as purchasable
- Keep one free way to get pets (maybe exploration finds?)
- Gem economy: petting earns gems

**Database:**
- Add `shop_creatures` table (like `shop_items`)
- Add `creature_price` column to species table

### B. Reward Redesign: Petter Earns Gems
**Current:** Pet owner earns currency when someone pets their creature
**New:** The PERSON PETTING earns gems (not owner)

**Why:** 
- Incentivizes visiting other creatures
- Creates positive feedback loop (pet → earn gems → buy treats)
- Owner gets indirect benefit (more creatures petted = site is fun)

**Details:**
- Petting someone else's creature: you earn 5 gems
- Petting your own creature: no gems (self-petting doesn't pay)
- Rate limit stays (can't spam-pet same creature)
- Gem icon visible in result message

**Database:**
- Change `currency` column name from "coins" to "gems" (or add gems as separate currency?)
- Update PettingResult message to show gems earned

### C. Tamagotchi Sidebar (The Heart of M2)

#### Visual Design
```
┌──────────────────────────┐
│  Your Favourite          │
│  ┌────────────────────┐  │
│  │  [Creature Image]  │  │
│  │     Biscuit        │  │
│  └────────────────────┘  │
│  ❤️ Happy (95%)          │
│  ⚡ Energy (60%)         │
│                          │
│  [Feed] [Pet] [Go to]   │
└──────────────────────────┘
```

#### Mechanics

**Favourite Pet:**
- Same as current "keepsake" — featured creature from profile
- Shows in sidebar on ALL pages (logged in)
- Click creature card to go to full page

**Stats:**
- **Happiness** (0-100%)
  - Increases: petting (+5), feeding treats (+10), being petted by others (+3)
  - Decreases: slowly over time (~5% per day)
  - Never goes to 0 (no punishment)
  
- **Energy** (0-100%)
  - Increases: feeding certain treats (+20)
  - Decreases: every interaction (-5)
  - Can be 0 (creatures just "sleep", don't die)
  - Recovers over time (+10% per hour)

**Mini-Interactions:**
1. **"Pet" Button** — same as regular petting
   - Animation: tiny heart particle
   - +5 happiness, -5 energy
   - Works cross-browser instantly

2. **"Feed" Button** — open treat selector
   - Shows 3 most common treats from inventory
   - Click to give: happiness +10, energy +20
   - Toast: "Biscuit loved the treat! ❤️"
   - Works if you have treats

3. **"Go to" Button** — navigate to full creature page
   - For detailed view/interactions

**Data Display:**
- Last petted: "2 min ago" or "earlier today"
- Mood text: "Very Happy", "Content", "Sleepy", "Hungry"
- Changes in real-time (WebSocket or auto-refresh every 30s)

#### Sidebar Placement
- Mobile: below the sidebar links, above logout
- Desktop: below featured creature, in same card style
- Always visible when logged in

### D. Item System Refinement

**Treats (New Item Category):**
- Drop from exploration: 10% chance per click
- Craftable? (early: just loot)
- Types:
  - Honey Treat (common): happiness +10, energy +20
  - Mushroom Treat (rare): happiness +20
  - Energy Drink (rare): energy +50 (no happiness)

**Display in Inventory:**
- "Treats" section at top
- Shows icon + quantity
- Quick "use on favourite" action

---

## 3. Implementation Roadmap

### Phase 1: Economy Redesign (Days 1-2)
1. **Database Migration:**
   - Add `gems` currency column to users (or rename `currency`)
   - Add `gem_price` to species table
   - Create `shop_creatures` listing (like shop_items)

2. **Update PettingService:**
   - Change: petter earns gems (not owner)
   - Output: gems earned message

3. **Create CurrencyRepository/GemService:**
   - Handle gem transactions
   - Balance checks

4. **Update Shop:**
   - Add creatures as purchasable items
   - Handle purchase flow

### Phase 2: Tamagotchi MVP (Days 3-5)
1. **Creature Stats System:**
   - Add `happiness`, `energy`, `last_petted_at` to creatures table
   - Migration: backfill existing creatures with default values (100 happiness, 80 energy)

2. **StatService (new):**
   - `calculateHappiness(creature)` — decay over time
   - `calculateEnergy(creature)` — regenerate over time
   - `applyInteraction(creature, type)` — pet/feed

3. **Sidebar Component:**
   - `site-side.php` adds tamagotchi card (when logged in + has favourite)
   - Show stats, mood text, buttons
   - CSS for mood colors (happy=green, sleepy=blue, etc.)

4. **Mini-Interactions:**
   - `/creature/{id}/pet` already exists, use it
   - New `/creature/{id}/feed` POST endpoint
     - Validate player has treat
     - Deduct from inventory
     - Apply happiness/energy
     - Return updated stats

5. **Treat Inventory UI:**
   - Inventory page shows treats separately
   - "Use on favourite" quick action

### Phase 3: Visual Polish (Days 6-7)
1. **Animations:**
   - Heart particle when pet
   - Sparkle when feed
   - Mood emoji changes
   - Smooth stat bar transitions

2. **Mood System:**
   - Happiness 80-100: 😄 "Very Happy"
   - Happiness 60-80: 😊 "Happy"
   - Happiness 40-60: 😐 "Content"
   - Happiness 20-40: 😔 "Sad"
   - Happiness 0-20: 😴 "Very Sad"
   - Energy 0-20: 😴 "Sleeping" (can't interact)

3. **Real-time Updates:**
   - Auto-refresh sidebar every 30s
   - Or WebSocket for live updates (advanced)

---

## 4. Database Schema

### New Columns (creatures table)
```sql
ALTER TABLE creatures ADD COLUMN happiness INT DEFAULT 100;
ALTER TABLE creatures ADD COLUMN energy INT DEFAULT 80;
ALTER TABLE creatures ADD COLUMN last_fed_at TIMESTAMP NULL;
ALTER TABLE creatures ADD COLUMN last_interaction_at TIMESTAMP NULL;
```

### New Tables
```sql
CREATE TABLE treats (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  icon_key VARCHAR(30),
  happiness_bonus INT,
  energy_bonus INT,
  category_id INT,
  FOREIGN KEY (category_id) REFERENCES item_categories(id)
);

CREATE TABLE shop_creatures (
  id INT PRIMARY KEY AUTO_INCREMENT,
  species_id INT NOT NULL,
  gem_price INT NOT NULL,
  FOREIGN KEY (species_id) REFERENCES species(id)
);
```

---

## 5. API/Controller Changes

### New Controllers
1. **CreatureStatsController** (GET `/creature/{id}/stats`)
   - Returns current happiness/energy
   - Used for real-time updates

2. **TreatController** (POST `/creature/{id}/feed`)
   - Validates treat in inventory
   - Applies effects
   - Returns updated creature

### Modified Controllers
1. **PettingController**
   - Change: $gems earned instead of coins
   - Update flash message

2. **ShopController**
   - Add creatures as purchasable
   - Check user gems (not coins)

---

## 6. UI/UX Details

### Sidebar Card (Mobile-First)
```css
.site-side__tamagotchi {
  padding: 1rem;
  background: linear-gradient(135deg, var(--panel), var(--parchment));
  border: 2px solid var(--border);
  border-radius: var(--radius-md);
  margin-top: 1rem;
}

.tamagotchi__image {
  width: 100%;
  max-width: 120px;
  margin: 0 auto 0.5rem;
  display: block;
}

.tamagotchi__name {
  font-size: 1.2rem;
  font-weight: 700;
  text-align: center;
}

.tamagotchi__stats {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin: 1rem 0;
}

.tamagotchi__stat {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.tamagotchi__bar {
  flex: 1;
  height: 8px;
  background: var(--border);
  border-radius: 999px;
  overflow: hidden;
}

.tamagotchi__bar-fill {
  height: 100%;
  background: var(--purple);
  transition: width 300ms ease;
}

.tamagotchi__buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  margin-top: 1rem;
}

.tamagotchi__btn {
  padding: 0.6rem;
  font-size: 0.8rem;
  border-radius: var(--radius-sm);
}
```

### Mood Changes
- Happiness bar color: green → yellow → red
- Emoji updates: 😄 → 😊 → 😐 → 😔 → 😴

---

## 7. Critical Design Decisions

### Why This Won't Be Boring
1. **Frequent small wins:** "pet → see heart" is instant gratification
2. **Treat variety:** different treats feel special
3. **Mood changes:** watching creature mood evolve is engaging
4. **Social moment:** "check my pet's mood" becomes a ritual
5. **No grind:** you can't "lose" — just get happy creatures happier

### Why Gems (not coins)
- Gems feel more "special" than coins
- Petting rewards gems, not coins (psychological difference)
- Opens future: gems for premium, coins for basic economy

### Why Energy System
- Prevents spamming interactions
- Makes treats valuable (recover energy)
- Creates rhythm: interact, wait, interact again

---

## 8. Rollout Strategy

**Soft launch:**
1. Implement shop creatures (Day 2)
2. Deploy reward change (Day 3)
3. Tamagotchi behind feature flag (Days 4-7)
4. Full launch when polish done

**Testing:**
- Unit tests for stat calculations
- Integration tests for treat consumption
- Manual testing: does it FEEL fun?

---

## 9. Success Metrics

- **Daily Active Users check their favourite pet** (goal: 80%+)
- **Treats are consumed** (not just hoarded)
- **Gem economy is balanced** (players earn enough, don't feel like grinding)
- **No "dead pet" feelings** — happiness never permanently tanks

---

## Next Steps

1. Lock this plan with Skerro ✅
2. Create database schema
3. Implement PettingService gem change (easiest, ships value immediately)
4. Build tamagotchi iteration by iteration (stats → UI → polish)
5. Ship & iterate based on player feedback

**Estimated effort: 1-2 weeks with good testing**

---

*This plan balances fun, feasibility, and long-term engagement. The tamagotchi should feel like checking in on a friend, not a chore.*
