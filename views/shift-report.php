<?php

use CseLog\Support;

/** @var array<int, array<string, mixed>> $open */
/** @var array<int, array<string, mixed>> $closed */
?>
<div class="page-head">
    <h1>Shift report — <?= Support::e($site['name']) ?></h1>
    <span class="muted"><?= Support::e($generatedAt) ?></span>
    <div class="spacer"></div>
    <button class="btn" onclick="window.print()">Print</button>
</div>

<div class="card">
    <h2>Still open (<?= count($open) ?>)</h2>
    <table>
        <thead><tr><th>Location</th><th>Work type</th><th>Open for</th><th>Radio</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach ($open as $entry): ?>
            <tr>
                <td><?= Support::e($entry['location_label']) ?></td>
                <td><?= Support::e($entry['work_type']) ?></td>
                <td><span class="pill pill-<?= Support::e($entry['age_level']) ?>"><?= Support::e($entry['age_human']) ?></span></td>
                <td><?= Support::e($entry['reported_by'] ?? '—') ?></td>
                <td class="muted"><?= Support::e($entry['notes'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($open === []): ?><tr><td colspan="5" class="empty">Nothing open at handover.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Closed in the last 12 hours (<?= count($closed) ?>)</h2>
    <table>
        <thead><tr><th>Location</th><th>Work type</th><th>Opened</th><th>Closed</th><th>Duration</th><th>Close note</th></tr></thead>
        <tbody>
        <?php foreach ($closed as $entry): ?>
            <tr>
                <td><?= Support::e($entry['location_label']) ?></td>
                <td><?= Support::e($entry['work_type']) ?></td>
                <td><?= Support::e(Support::local((string) $entry['opened_at'], 'j M, H:i')) ?></td>
                <td><?= Support::e(Support::local((string) $entry['closed_at'], 'j M, H:i')) ?></td>
                <td><?= Support::e($entry['duration_human']) ?></td>
                <td class="muted"><?= Support::e($entry['close_note'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($closed === []): ?><tr><td colspan="6" class="empty">Nothing closed in the last 12 hours.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
