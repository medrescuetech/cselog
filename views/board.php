<?php

use CseLog\Auth;
use CseLog\Http;
use CseLog\Support;

/** @var array<int, array<string, mixed>> $entries */
$counts = ['grey' => 0, 'amber' => 0, 'red' => 0];
foreach ($entries as $entry) {
    $counts[$entry['age_level']]++;
}
?>
<div class="page-head">
    <h1>Open work — <?= Support::e($site['name']) ?></h1>
    <span class="muted"><?= Support::e(Support::local(Support::nowUtc(), 'D j M Y, H:i')) ?></span>
    <div class="spacer"></div>
    <a class="btn" href="/map">Map view</a>
    <a class="btn" href="/shift-report">Shift report</a>
    <a class="btn btn-primary" href="/log">+ Log entry</a>
</div>

<div class="stat-row">
    <div class="stat"><b><?= count($entries) ?></b><span>open now</span></div>
    <div class="stat"><b style="color:var(--amber)"><?= $counts['amber'] ?></b><span>over <?= \CseLog\Config::int('ALERT_AMBER_HOURS', 2) ?>h</span></div>
    <div class="stat"><b style="color:var(--red)"><?= $counts['red'] ?></b><span>over <?= \CseLog\Config::int('ALERT_RED_HOURS', 4) ?>h</span></div>
</div>

<?php if ($entries === []): ?>
    <div class="card empty">
        Nothing open. When a call comes in, hit <a href="/log">Log entry</a>.
    </div>
<?php else: ?>
    <div class="board-grid">
        <?php foreach ($entries as $entry): ?>
            <article class="entry-card age-<?= Support::e($entry['age_level']) ?><?= (int) $entry['id'] === ($highlight ?? 0) ? ' highlight' : '' ?>"
                     data-age-level="<?= Support::e($entry['age_level']) ?>">
                <h3><a href="/entries/<?= (int) $entry['id'] ?>"><?= Support::e($entry['location_label']) ?></a></h3>
                <div class="meta">
                    <span class="pill" style="border-color:<?= Support::e($entry['work_colour']) ?>;color:<?= Support::e($entry['work_colour']) ?>">
                        <?= Support::e($entry['work_type']) ?>
                    </span>
                    <?php if ($entry['area_name'] !== null): ?>
                        <span class="pill"><?= Support::e($entry['area_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($entry['ad_hoc']): ?>
                        <span class="pill">ad-hoc pin</span>
                    <?php elseif ((int) ($entry['location_verified'] ?? 1) === 0): ?>
                        <span class="pill pill-amber">unverified location</span>
                    <?php endif; ?>
                </div>

                <div class="clock pill-<?= Support::e($entry['age_level']) ?>" data-age-seconds="<?= (int) $entry['age_seconds'] ?>">
                    <?= Support::e($entry['age_human']) ?>
                </div>
                <div class="muted">open since <?= Support::e(Support::local((string) $entry['opened_at'])) ?></div>

                <?php if (!empty($entry['notes'])): ?>
                    <p class="notes"><?= Support::e($entry['notes']) ?></p>
                <?php endif; ?>

                <div class="muted">
                    <?= $entry['reported_by'] !== null ? 'Radio: ' . Support::e($entry['reported_by']) . ' · ' : '' ?>
                    logged by <?= Support::e($entry['opened_by_name'] ?? 'unknown') ?>
                </div>

                <div class="actions">
                    <?php if (Auth::can('logger')): ?>
                        <form method="post" action="/entries/<?= (int) $entry['id'] ?>/close"
                              onsubmit="return confirm('Close <?= Support::e(addslashes((string) $entry['location_label'])) ?>?');">
                            <input type="hidden" name="_csrf" value="<?= Support::e(Http::csrfToken()) ?>">
                            <input type="hidden" name="close_note" value="">
                            <button class="btn btn-primary btn-sm" type="submit">Close out</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-sm" href="/entries/<?= (int) $entry['id'] ?>">Details</a>
                    <?php if ($entry['x'] !== null): ?>
                        <a class="btn btn-sm" href="/map?focus=<?= (int) $entry['id'] ?>">On map</a>
                    <?php else: ?>
                        <a class="btn btn-sm" href="/entries/<?= (int) $entry['id'] ?>/pin">Drop pin</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
