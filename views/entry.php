<?php

use CseLog\Auth;
use CseLog\Http;
use CseLog\Support;

/** @var array<string, mixed> $entry */
/** @var array<int, array<string, mixed>> $events */
$seconds = Support::durationSeconds((string) $entry['opened_at'], $entry['closed_at'] === null ? null : (string) $entry['closed_at']);
$csrf = Support::e(Http::csrfToken());
?>
<div class="page-head">
    <h1><?= Support::e($entry['location_label']) ?></h1>
    <span class="pill pill-<?= $entry['status'] === 'open' ? Support::e(Support::ageLevel($seconds)) : 'green' ?>">
        <?= Support::e(ucfirst((string) $entry['status'])) ?>
    </span>
    <div class="spacer"></div>
    <a class="btn" href="/board">Back to board</a>
</div>

<div class="card">
    <table>
        <tr><th>Work type</th><td><?= Support::e($entry['work_type']) ?></td></tr>
        <tr><th>Area</th><td><?= Support::e($entry['area_name'] ?? '—') ?></td></tr>
        <tr><th>Opened</th><td><?= Support::e(Support::local((string) $entry['opened_at'])) ?> by <?= Support::e($entry['opened_by_name'] ?? '—') ?></td></tr>
        <tr><th>Closed</th><td>
            <?= $entry['closed_at'] === null ? '—' : Support::e(Support::local((string) $entry['closed_at'])) . ' by ' . Support::e($entry['closed_by_name'] ?? '—') ?>
        </td></tr>
        <tr><th><?= $entry['status'] === 'open' ? 'Open for' : 'Duration' ?></th><td><?= Support::e(Support::humanDuration($seconds)) ?></td></tr>
        <tr><th>Radio</th><td><?= Support::e($entry['reported_by'] ?? '—') ?></td></tr>
        <tr><th>Notes</th><td><?= nl2br(Support::e($entry['notes'] ?? '—')) ?></td></tr>
        <tr><th>Close note</th><td><?= nl2br(Support::e($entry['close_note'] ?? '—')) ?></td></tr>
        <tr><th>Pin</th><td>
            <?php if ($entry['x'] === null): ?>
                Not placed — <a href="/entries/<?= (int) $entry['id'] ?>/pin">drop a pin</a>
            <?php else: ?>
                <?= (float) $entry['x'] ?>, <?= (float) $entry['y'] ?>
                (<a href="/map?focus=<?= (int) $entry['id'] ?>">show on map</a>)
                <?= $entry['location_id'] === null ? ' · ad-hoc, not in the saved list' : '' ?>
            <?php endif; ?>
        </td></tr>
    </table>
</div>

<?php if ($entry['status'] === 'open' && Auth::can('logger')): ?>
    <form class="card" method="post" action="/entries/<?= (int) $entry['id'] ?>/close">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <h2>Close out</h2>
        <div class="field">
            <label for="close_note">Close note (optional)</label>
            <input type="text" name="close_note" id="close_note" placeholder="All crew out, space secured.">
        </div>
        <button class="btn btn-primary" type="submit">Close entry</button>
    </form>
<?php endif; ?>

<?php if (Auth::can('supervisor')): ?>
    <div class="card">
        <h2>Supervisor</h2>
        <div class="field-row">
            <?php if ($entry['status'] === 'open'): ?>
                <form method="post" action="/entries/<?= (int) $entry['id'] ?>/cancel"
                      onsubmit="return confirm('Cancel this entry as logged in error?');">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="close_note" value="Logged in error">
                    <button class="btn btn-danger" type="submit">Cancel (logged in error)</button>
                </form>
            <?php else: ?>
                <form method="post" action="/entries/<?= (int) $entry['id'] ?>/reopen">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <button class="btn" type="submit">Reopen</button>
                </form>
            <?php endif; ?>

            <?php if ($entry['location_id'] === null && $entry['x'] !== null): ?>
                <form method="post" action="/entries/<?= (int) $entry['id'] ?>/promote" class="field-row" style="flex:1 1 320px">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="text" name="name" placeholder="Save this pin as…" style="flex:1 1 200px">
                    <button class="btn" type="submit">Add to location list</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Audit trail</h2>
    <table>
        <thead><tr><th>When</th><th>Event</th><th>Who</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <tr>
                <td><?= Support::e(Support::local((string) $event['occurred_at'], 'j M, H:i:s')) ?></td>
                <td><?= Support::e($event['event']) ?></td>
                <td><?= Support::e($event['actor_name'] ?? 'system') ?></td>
                <td class="muted"><?= Support::e($event['changes'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
