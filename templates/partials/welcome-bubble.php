<?php
/**
 * Ruinily's welcome on the home page (M7): her portrait, a speech bubble
 * with one step, and three plain choices — go where she's pointing, hear
 * the next thing, or "don't show me this again".
 *
 * @package Felkyo\Templates
 *
 * No scripting: "next" is a link to ?welcome=N, dismissing is a real POST
 * with a CSRF token. The bubble pops in gently, and not at all for anyone
 * who prefers reduced motion (character.css).
 *
 * Variable: $welcome (null, or ['steps' => [...], 'step' => int,
 * 'keeperName' => string, 'portraitUrl' => ?string]).
 */
if (empty($welcome) || $welcome['steps'] === []) {
    return;
}
$steps = $welcome['steps'];
$index = max(0, min($welcome['step'], count($steps) - 1));
$step = $steps[$index];
$isLast = $index === count($steps) - 1;
?>
<section class="welcome" aria-label="A welcome from <?= $this->e($welcome['keeperName']) ?>">
    <span class="welcome__portrait" aria-hidden="true">
        <?php if ($welcome['portraitUrl'] !== null): ?>
            <img src="<?= $this->e($welcome['portraitUrl']) ?>" alt="" width="72" height="72">
        <?php else: ?>
            <span class="welcome__initial"><?= $this->e(mb_substr($welcome['keeperName'], 0, 1)) ?></span>
        <?php endif; ?>
    </span>
    <div class="welcome__bubble">
        <p class="welcome__who"><?= $this->e($welcome['keeperName']) ?> <span><?= $this->t('welcome-bubble.of', '({index} of {steps})', ['index' => (string) ($index + 1), 'steps' => (string) count($steps)]) ?></span></p>
        <p class="welcome__words"><?= $this->e($step['words']) ?></p>
        <div class="welcome__actions">
            <?php if (!empty($step['link_href'])): ?>
                <?php /* Safe: links were checked to be on-site ("/...") when saved. */ ?>
                <a class="btn btn--secondary" href="<?= $this->e($step['link_href']) ?>"><?= $this->e((string) $step['link_label']) ?></a>
            <?php endif; ?>
            <?php if (!$isLast): ?>
                <a class="btn btn--primary" href="/?welcome=<?= $this->e((string) ($index + 1)) ?>"><?= $this->t('welcome-bubble.next', 'Next') ?></a>
            <?php endif; ?>
            <form method="post" action="/welcome/dismiss">
                <?= $this->csrf_field() ?>
                <button class="btn--quiet" type="submit"><?= $isLast ? 'Thanks, Ruinily!' : 'don\'t show me this again' ?></button>
            </form>
        </div>
    </div>
</section>
