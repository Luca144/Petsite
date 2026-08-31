<?php
/**
 * The shopfront: the shop page's header, fed by the panel (M2.4).
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/shop.php (its own partial to keep that page inside its
 * 200-line limit — and because the day M9 gives more shops their own doors,
 * every one of them opens with this same front).
 *
 * Everything here is the shop's own data: the background behind the header,
 * the keeper's portrait, their greeting. A shop with none of it set renders
 * exactly as shops always did — name and description. The background is
 * decoration behind a SOLID parchment panel, so the words never fight the
 * art for contrast (see shopfront.css).
 *
 * Variables: $shop (Felkyo\Economy\Shop).
 */
?>
<header class="shopfront<?= $shop->backgroundUrl() !== null ? ' shopfront--painted' : '' ?>"
    <?= $shop->backgroundUrl() !== null
        ? 'style="--shopfront-bg: url(\'' . $this->e($shop->backgroundUrl()) . '\')"'
        : '' ?>>
    <div class="shopfront__panel">
        <?php if ($shop->portraitUrl() !== null): ?>
            <img class="shopfront__portrait pixelated" src="<?= $this->e($shop->portraitUrl()) ?>"
                 alt="<?= $this->e($shop->keeperName ?? 'The shopkeeper') ?>" loading="lazy">
        <?php endif; ?>
        <div class="shopfront__words">
            <h2 class="panel-label"><?= $this->e($shop->name) ?></h2>
            <?php if ($shop->keeperGreeting !== null): ?>
                <p class="shopfront__greeting">
                    &ldquo;<?= $this->e($shop->keeperGreeting) ?>&rdquo;
                    <?php if ($shop->keeperName !== null): ?>
                        <span class="shopfront__keeper">&mdash; <?= $this->e($shop->keeperName) ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if ($shop->description !== null): ?>
                <p><?= $this->e($shop->description) ?></p>
            <?php endif; ?>
        </div>
    </div>
</header>
